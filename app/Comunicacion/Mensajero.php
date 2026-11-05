<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Enums\AccionAutomatizacion;
use App\Enums\CanalEnvio;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\LugarOmision;
use App\Enums\NivelAviso;
use App\Enums\TipoContacto;
use App\Enums\TipoEventoReserva;
use App\Enums\TipoReserva;
use App\Enums\TipoTarea;
use App\Limpieza\Omisiones;
use App\Llegada\GestorLlegadas;
use App\Models\Automatizacion;
use App\Models\Aviso;
use App\Models\CuotaReserva;
use App\Models\Envio;
use App\Models\EventoReserva;
use App\Models\NotaBitacora;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Webhook;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Publico\MiReserva;
use App\Reservas\Mensualidades;
use App\Seguridad\ClienteExterno;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;
use Throwable;

/**
 * Convierte lo que pasa con las reservas en mensajes (M6). Todo pasa por `envios`, cuya clave única
 * garantiza un mensaje por regla, reserva y ocasión aunque el trabajo se repita.
 */
final class Mensajero
{
    public const INTENTOS_MAXIMOS = 5;

    private const SEGUNDOS_MAXIMOS_ENVIO = 600;

    private const DIAS_MARGEN_CUOTA = 2;

    public function __construct(private readonly AjustesCorreo $correo, private readonly ClienteExterno $cliente)
    {
    }

    /**
     * @return int eventos atendidos
     */
    public function procesarEventos(int $limite = 500): int
    {
        $eventos = EventoReserva::query()->with('reserva.propiedad')->whereNull('procesado_en')->orderBy('id')->limit($limite)->get();
        foreach ($eventos as $evento) {
            $reserva = $evento->reserva;
            $disparador = $this->disparadorDe($evento);
            // Un dato raro en una reserva no debe dejar sin mensajes a todas las demás.
            try {
                if ($reserva->tipo === TipoReserva::Reserva && $disparador !== null) {
                    $this->atenderEvento($evento, $reserva, $disparador);
                }
            } catch (Throwable $e) {
                report($e);
            }
            $evento->update(['procesado_en' => now('UTC')]);
        }

        return $eventos->count();
    }

    /**
     * Los mensajes que dependen del calendario: faltan dos días, hoy llega, ayer salió…
     *
     * @return int mensajes generados
     */
    public function procesarTiempo(): int
    {
        if (! Modulos::activo(Modulo::Mensajes)) {
            return 0;
        }
        $generados = 0;
        $tiempo = array_values(array_filter(DisparadorAutomatizacion::cases(), fn (DisparadorAutomatizacion $d): bool => $d->esDeTiempo()));
        $automatizaciones = Automatizacion::query()->with(['propiedad', 'plantilla'])->where('activa', true)->whereIn('disparador', array_map(fn (DisparadorAutomatizacion $d): string => $d->value, $tiempo))->get();

        foreach ($automatizaciones as $automatizacion) {
            $propiedad = $automatizacion->propiedad;
            $ahora = CarbonImmutable::now($propiedad->zona_horaria);
            if ($automatizacion->hora !== null && $ahora->format('H:i') < substr($automatizacion->hora, 0, 5)) {
                continue;
            }
            $hoy = $ahora->startOfDay();
            if ($automatizacion->disparador->esDeCuota()) {
                $generados += $this->recordarCuotas($automatizacion, $hoy);

                continue;
            }
            [$columna, $fecha] = match ($automatizacion->disparador) {
                DisparadorAutomatizacion::AntesLlegada => ['llegada', $hoy->addDays($automatizacion->dias)],
                DisparadorAutomatizacion::Llegada => ['llegada', $hoy],
                DisparadorAutomatizacion::Salida => ['salida', $hoy],
                default => ['salida', $hoy->subDays($automatizacion->dias)],
            };
            $reservas = Reserva::query()->with(['propiedad', 'titular.contactos', 'unidad'])->where('propiedad_id', $propiedad->id)
                ->where('tipo', TipoReserva::Reserva->value)->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en')
                ->when(
                    $automatizacion->esperaACumplir(),
                    fn ($q) => $q->whereBetween($columna, [$hoy->format('Y-m-d'), $fecha->format('Y-m-d')]),
                    fn ($q) => $q->where($columna, $fecha->format('Y-m-d')),
                )->get();
            foreach ($reservas as $reserva) {
                // La ocasión es la fecha de la reserva: si se revisa cada día, se manda una sola vez.
                if ($automatizacion->cumpleCondiciones($reserva)
                    && $this->programar($automatizacion, $reserva, $automatizacion->disparador->value.':'.$reserva->{$columna}->format('Y-m-d')) !== null) {
                    $generados++;
                }
            }
        }

        return $generados;
    }

    /**
     * Con margen, por si un día no corrieron las tareas: el mensaje sale al siguiente, y una sola vez por pago (D-108). Un pago que ya estaba
     * vencido cuando se capturó, o cuando se creó la regla, no se reclama: seguro alguien ya lo sabe y puede que falte anotar lo pagado.
     */
    private function recordarCuotas(Automatizacion $automatizacion, CarbonImmutable $hoy): int
    {
        if (! Modulos::activo(Modulo::EstanciasLargas)) {
            return 0;
        }
        $vencida = $automatizacion->disparador === DisparadorAutomatizacion::CuotaVencida;
        [$desde, $hasta] = $vencida
            ? [$hoy->subDays($automatizacion->dias + self::DIAS_MARGEN_CUOTA), $hoy->subDays($automatizacion->dias)]
            : [$hoy, $hoy->addDays($automatizacion->dias)];
        $cuotas = CuotaReserva::query()->with(['reserva.propiedad', 'reserva.titular.contactos', 'reserva.unidad'])
            ->whereBetween('vence_en', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
            ->whereHas('reserva', fn ($q) => $q->where('propiedad_id', $automatizacion->propiedad_id)->where('tipo', TipoReserva::Reserva->value)
                ->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en'))
            ->get();

        $generados = 0;
        foreach ($cuotas as $cuota) {
            $reserva = $cuota->reserva;
            $vence = $cuota->vence_en->format('Y-m-d');
            if ($vencida && ($this->diaEn($cuota->creado_en, $hoy) > $vence || $this->diaEn($automatizacion->creado_en, $hoy) > $vence)) {
                continue;
            }
            $fila = collect(Mensualidades::de($reserva)['cuotas'])->first(fn (array $f): bool => $f['cuota']->id === $cuota->id);
            if ($fila !== null && $fila['estado'] !== 'pagada' && $automatizacion->cumpleCondiciones($reserva)
                && $this->programar($automatizacion, $reserva, $automatizacion->disparador->value.':'.$cuota->id) !== null) {
                $generados++;
            }
        }

        return $generados;
    }

    private function diaEn(?\Illuminate\Support\Carbon $momento, CarbonImmutable $hoy): string
    {
        return $momento === null ? '' : $momento->copy()->setTimezone($hoy->getTimezone())->format('Y-m-d');
    }

    /**
     * @return int mensajes enviados
     */
    public function enviarPendientes(int $limite = 50): int
    {
        $enviados = 0;
        $inicio = microtime(true);
        $envios = Envio::query()->with(['propiedad', 'webhook'])->where('estado', EstadoEnvio::Pendiente->value)
            ->where(fn ($q) => $q->whereNull('programado_para')->orWhere('programado_para', '<=', now('UTC')))
            ->orderBy('id')->limit($limite)->get();
        foreach ($envios as $envio) {
            // Se para antes de que venza el candado de la tarea: otra corrida repetiría estos mismos envíos.
            if (microtime(true) - $inicio > self::SEGUNDOS_MAXIMOS_ENVIO) {
                break;
            }
            $enviados += $this->enviar($envio) ? 1 : 0;
        }

        return $enviados;
    }

    public function enviar(Envio $envio): bool
    {
        $envio->intentos++;
        try {
            match ($envio->canal) {
                CanalEnvio::Correo => $this->enviarCorreo($envio),
                CanalEnvio::Webhook => $this->enviarWebhook($envio),
                CanalEnvio::WhatsappEnlace => null,
            };
        } catch (Throwable $e) {
            $this->anotarFallo($envio, $e);

            return false;
        }
        $envio->estado = EstadoEnvio::Enviado;
        $envio->enviado_en = now('UTC');
        $envio->ultimo_error = null;
        $envio->save();

        return true;
    }

    /**
     * Mensaje suelto escrito o revisado por el personal. Sin clave: se puede repetir a propósito.
     */
    public function mandarCorreo(Reserva $reserva, string $destino, string $asunto, string $cuerpo, string $idioma): Envio
    {
        $envio = Envio::query()->create([
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'huesped_id' => $reserva->titular_id,
            'canal' => CanalEnvio::Correo,
            'destino' => $destino,
            'idioma' => $idioma,
            'asunto' => mb_substr($asunto, 0, 255),
            'cuerpo' => $cuerpo,
            'estado' => $this->correo->configurado() ? EstadoEnvio::Pendiente : EstadoEnvio::Manual,
        ]);
        if ($envio->estado === EstadoEnvio::Pendiente) {
            $this->enviar($envio);
        }

        return $envio;
    }

    public function anotarWhatsapp(Reserva $reserva, ?string $telefono, string $cuerpo, string $idioma): Envio
    {
        return Envio::query()->create([
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'huesped_id' => $reserva->titular_id,
            'canal' => CanalEnvio::WhatsappEnlace,
            'destino' => $telefono,
            'idioma' => $idioma,
            'cuerpo' => $cuerpo,
            'estado' => EstadoEnvio::Enviado,
            'intentos' => 1,
            'enviado_en' => now('UTC'),
        ]);
    }

    /**
     * Avisa al alojamiento de una reserva hecha en el motor, aunque no tenga automatizaciones.
     */
    public function avisarNuevaReserva(Reserva $reserva, ?string $destino): void
    {
        // Quien prueba la demostración no llena el correo del dueño.
        if (blank($destino) || $reserva->propiedad->es_demo) {
            return;
        }
        $idioma = $reserva->propiedad->idioma;
        $variables = VariablesMensaje::de($reserva, $idioma);
        $this->crearUnaVez('motor:reserva:'.$reserva->id, [
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'canal' => CanalEnvio::Correo,
            'destino' => $destino,
            'idioma' => $idioma,
            'asunto' => mb_substr(VariablesMensaje::aplicar(__('mensajes.aviso_motor.asunto', [], $idioma), $variables), 0, 255),
            'cuerpo' => VariablesMensaje::aplicar(__('mensajes.aviso_motor.cuerpo', [], $idioma), $variables),
            'estado' => $this->correo->configurado() ? EstadoEnvio::Pendiente : EstadoEnvio::Manual,
        ]);
    }

    /**
     * Lo que un huésped pidió desde su página, para quien atiende el correo del alojamiento.
     */
    public function avisarSolicitud(Reserva $reserva, string $mensaje, ?string $destino): void
    {
        if (blank($destino) || $reserva->propiedad->es_demo) {
            return;
        }
        $idioma = $reserva->propiedad->idioma;
        $variables = VariablesMensaje::de($reserva, $idioma) + ['mensaje' => $mensaje];
        $this->crearUnaVez('portal:pide:'.$reserva->id.':'.sha1($mensaje), [
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'canal' => CanalEnvio::Correo,
            'destino' => $destino,
            'idioma' => $idioma,
            'asunto' => mb_substr(VariablesMensaje::aplicar(__('mensajes.aviso_portal.asunto', [], $idioma), $variables), 0, 255),
            'cuerpo' => VariablesMensaje::aplicar(__('mensajes.aviso_portal.cuerpo', [], $idioma), $variables),
            'estado' => $this->correo->configurado() ? EstadoEnvio::Pendiente : EstadoEnvio::Manual,
        ]);
    }

    /**
     * Lo que no es de una reserva, como el aviso al dueño de que ya está su estado de cuenta. Sin correo configurado queda para mandarlo a mano.
     */
    public function avisarSinReserva(Propiedad $propiedad, string $clave, ?string $destino, string $asunto, string $cuerpo, string $idioma): void
    {
        if (blank($destino) || $propiedad->es_demo) {
            return;
        }
        $this->crearUnaVez('aviso:'.$propiedad->id.':'.$clave, [
            'propiedad_id' => $propiedad->id,
            'canal' => CanalEnvio::Correo,
            'destino' => $destino,
            'idioma' => $idioma,
            'asunto' => mb_substr($asunto, 0, 255),
            'cuerpo' => $cuerpo,
            'estado' => $this->correo->configurado() ? EstadoEnvio::Pendiente : EstadoEnvio::Manual,
        ]);
    }

    public static function telefonoDe(Reserva $reserva): ?string
    {
        return self::contactoDe($reserva, TipoContacto::Telefono);
    }

    public static function correoDe(Reserva $reserva): ?string
    {
        return self::contactoDe($reserva, TipoContacto::Correo);
    }

    public static function idiomaDe(Reserva $reserva): string
    {
        // La ficha puede venir de una principal con cualquier texto: solo vale un idioma de los nuestros.
        foreach ([$reserva->getAttribute('idioma'), $reserva->titular?->idioma] as $idioma) {
            if (in_array($idioma, (array) config('alojaflow.idiomas'), true)) {
                return $idioma;
            }
        }

        return $reserva->propiedad->idioma;
    }

    /**
     * @return array{asunto: string, cuerpo: string}
     */
    public static function textoDe(Plantilla $plantilla, Reserva $reserva, string $idioma): array
    {
        $variables = VariablesMensaje::de($reserva, $idioma);
        $cuerpo = $plantilla->texto('cuerpo', $idioma);
        // El enlace se crea solo si la plantilla lo usa: no hace falta uno por cada mensaje.
        if (str_contains($cuerpo, '{enlace_llegada}')) {
            $variables['enlace_llegada'] = Modulos::activo(Modulo::Llegada) ? app(GestorLlegadas::class)->urlDe($reserva, $idioma) : '';
        }
        if (str_contains($cuerpo, '{enlace_mi_reserva}')) {
            $variables['enlace_mi_reserva'] = Modulos::activo(Modulo::Portal) ? app(MiReserva::class)->urlDe($reserva, $idioma) : '';
        }
        if (str_contains($cuerpo, '{enlace_opinion}')) {
            $variables['enlace_opinion'] = Modulos::activo(Modulo::Opiniones) ? app(Opiniones::class)->urlDe($reserva, $idioma) : '';
        }

        return [
            'asunto' => VariablesMensaje::aplicar($plantilla->texto('asunto', $idioma), $variables),
            'cuerpo' => VariablesMensaje::aplicar($cuerpo, $variables),
        ];
    }

    private static function contactoDe(Reserva $reserva, TipoContacto $tipo): ?string
    {
        return $reserva->titular?->contactos->firstWhere('tipo', $tipo)?->valor;
    }

    private function disparadorDe(EventoReserva $evento): ?DisparadorAutomatizacion
    {
        $datos = (array) $evento->datos;

        return match ($evento->tipo) {
            TipoEventoReserva::Creada => DisparadorAutomatizacion::ReservaCreada,
            TipoEventoReserva::Cancelada, TipoEventoReserva::Eliminada => DisparadorAutomatizacion::ReservaCancelada,
            TipoEventoReserva::Reactivada => DisparadorAutomatizacion::ReservaModificada,
            TipoEventoReserva::Modificada => $this->disparadorDeModificacion($datos),
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function disparadorDeModificacion(array $datos): ?DisparadorAutomatizacion
    {
        $accion = $datos['accion'] ?? null;
        if (in_array($accion, ['entrada', 'salida', 'deshacer'], true)) {
            return null; // Registrar la llegada no es un cambio del que avisar al huésped.
        }
        $confirmada = EstadoReserva::Confirmada->value;
        $antes = $datos['antes']['estado'] ?? null;
        $despues = $datos['despues']['estado'] ?? null;
        if ($accion === 'confirmar' || ($antes !== $confirmada && $despues === $confirmada)) {
            return DisparadorAutomatizacion::ReservaConfirmada;
        }

        return DisparadorAutomatizacion::ReservaModificada;
    }

    private function atenderEvento(EventoReserva $evento, Reserva $reserva, DisparadorAutomatizacion $disparador): void
    {
        // Lo que pasó antes de crear la regla no la dispara; a quien ya se fue no se le escribe.
        $pasada = $reserva->salida->format('Y-m-d') < $reserva->propiedad->hoy()->format('Y-m-d');
        if (! $pasada && Modulos::activo(Modulo::Mensajes)) {
            $automatizaciones = Automatizacion::query()->with(['propiedad', 'plantilla'])->where('propiedad_id', $reserva->propiedad_id)
                ->where('activa', true)->where('disparador', $disparador->value)->get();
            foreach ($automatizaciones as $automatizacion) {
                if ($automatizacion->creado_en !== null && $evento->ocurrido_en->lt($automatizacion->creado_en)) {
                    continue;
                }
                if ($automatizacion->cumpleCondiciones($reserva)) {
                    $this->programar($automatizacion, $reserva, 'evento:'.$evento->id);
                }
            }
        }

        if (! Modulos::activo(Modulo::Integraciones)) {
            return;
        }
        foreach (Webhook::query()->where('propiedad_id', $reserva->propiedad_id)->where('activo', true)->get() as $webhook) {
            if (in_array($disparador->value, $webhook->eventos, true)) {
                $this->crearUnaVez('webhook:'.$webhook->id.':evento:'.$evento->id, [
                    'propiedad_id' => $reserva->propiedad_id,
                    'reserva_id' => $reserva->id,
                    'webhook_id' => $webhook->id,
                    'canal' => CanalEnvio::Webhook,
                    // Solo el sitio: la dirección completa suele llevar un token, y esta lista la ve recepción.
                    'destino' => (string) parse_url($webhook->url, PHP_URL_HOST),
                    'cuerpo' => (string) json_encode($this->cargaWebhook($disparador, $reserva, $evento), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'estado' => EstadoEnvio::Pendiente,
                ]);
            }
        }
    }

    /**
     * Lo que no sale hacia afuera: una tarea para el equipo o una nota en la bitácora. Una vez por ocasión.
     */
    private function accionInterna(Automatizacion $automatizacion, Reserva $reserva, string $ocasion): void
    {
        $clave = mb_substr('auto:'.$automatizacion->id.':reserva:'.$reserva->id.':'.$ocasion, 0, 150);
        $texto = $automatizacion->plantilla === null ? null : self::textoDe($automatizacion->plantilla, $reserva, $automatizacion->propiedad->idioma)['cuerpo'];
        $titulo = $automatizacion->nombre.' · '.__('ui.reservas.modelo', [], $automatizacion->propiedad->idioma).' '.$reserva->numero;
        if ($automatizacion->accion === AccionAutomatizacion::CrearTarea && Modulos::activo(Modulo::Limpieza)) {
            Tarea::query()->firstOrCreate(['clave_generacion' => $clave], [
                'propiedad_id' => $reserva->propiedad_id,
                'unidad_id' => $reserva->unidad_id,
                'reserva_id' => $reserva->id,
                'tipo' => TipoTarea::Otra,
                'titulo' => mb_substr($titulo, 0, 150),
                'descripcion' => $texto,
                'fecha' => $reserva->propiedad->hoy()->format('Y-m-d'),
                'estado' => EstadoTarea::Pendiente,
            ]);
        }
        if ($automatizacion->accion === AccionAutomatizacion::NotaBitacora && Modulos::activo(Modulo::Bitacora)) {
            NotaBitacora::query()->firstOrCreate(['clave_generacion' => $clave], [
                'propiedad_id' => $reserva->propiedad_id,
                'texto' => $texto === null ? $titulo : $titulo."\n".$texto,
                'importante' => true,
            ]);
        }
    }

    private function programar(Automatizacion $automatizacion, Reserva $reserva, string $ocasion): ?Envio
    {
        if (! $automatizacion->accion->esCorreo()) {
            $this->accionInterna($automatizacion, $reserva, $ocasion);

            return null;
        }
        $reserva->loadMissing(['titular.contactos', 'unidad']);
        $aHuesped = $automatizacion->accion === AccionAutomatizacion::CorreoHuesped;
        if ($aHuesped && Omisiones::estaOmitido($reserva->titular_id, $reserva->propiedad_id, LugarOmision::Mensajes)) {
            return null;
        }
        $idioma = $aHuesped ? self::idiomaDe($reserva) : $automatizacion->propiedad->idioma;
        $destino = $aHuesped ? self::correoDe($reserva) : $automatizacion->destino;
        $plantilla = $automatizacion->plantilla;
        $texto = $plantilla === null ? ['asunto' => '', 'cuerpo' => ''] : self::textoDe($plantilla, $reserva, $idioma);
        $estado = match (true) {
            blank($destino) || $plantilla === null || ! $plantilla->activa => EstadoEnvio::Omitido,
            $this->correo->configurado() => EstadoEnvio::Pendiente,
            default => EstadoEnvio::Manual,
        };

        return $this->crearUnaVez('auto:'.$automatizacion->id.':reserva:'.$reserva->id.':'.$ocasion, [
            'propiedad_id' => $reserva->propiedad_id,
            'reserva_id' => $reserva->id,
            'huesped_id' => $reserva->titular_id,
            'automatizacion_id' => $automatizacion->id,
            'canal' => CanalEnvio::Correo,
            'destino' => $destino,
            'idioma' => $idioma,
            'asunto' => mb_substr($texto['asunto'], 0, 255),
            'cuerpo' => $texto['cuerpo'],
            'estado' => $estado,
            'ultimo_error' => $estado === EstadoEnvio::Omitido ? __('mensajes.omitido_sin_destino', [], $idioma) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearUnaVez(string $clave, array $datos): ?Envio
    {
        if (Envio::query()->where('clave_idempotencia', $clave)->exists()) {
            return null;
        }
        try {
            return Envio::query()->create($datos + ['clave_idempotencia' => $clave]);
        } catch (UniqueConstraintViolationException) {
            return null; // Otro proceso lo creó entre medias: justo lo que la clave debe evitar.
        }
    }

    private function enviarCorreo(Envio $envio): void
    {
        if (! $this->correo->configurado()) {
            throw new RuntimeException(__('mensajes.correo_sin_configurar'));
        }
        $this->correo->mailer()->to((string) $envio->destino)->send(new MensajeTexto(
            (string) $envio->asunto,
            (string) $envio->cuerpo,
            $this->correo->remitente(),
            $envio->propiedad->nombre ?: $this->correo->nombreRemitente(),
            filled($envio->propiedad->getAttribute('correo')) ? (string) $envio->propiedad->getAttribute('correo') : null,
        ));
    }

    private function enviarWebhook(Envio $envio): void
    {
        $webhook = $envio->webhook ?? throw new RuntimeException(__('mensajes.webhook_borrado'));
        $cuerpo = (string) $envio->cuerpo;
        $respuesta = $this->cliente->peticion($webhook->url)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-AlojaFlow-Entrega' => (string) $envio->id,
                'X-AlojaFlow-Firma' => 'sha256='.hash_hmac('sha256', $cuerpo, $webhook->secreto),
            ])
            ->withBody($cuerpo, 'application/json')->post($webhook->url);
        if (! $respuesta->successful()) {
            throw new RuntimeException('HTTP '.$respuesta->status());
        }
    }

    /**
     * El servidor de correo repite en sus errores la dirección del huésped y el usuario de la cuenta,
     * y este texto lo ve quien gestiona mensajes y se conserva tras anonimizar.
     */
    private static function sinDatosPersonales(string $mensaje): string
    {
        $mensaje = (string) preg_replace('/[^\s<>"\']+@[^\s<>"\']+/', '***', $mensaje);

        return (string) preg_replace('/(username) "[^"]*"/i', '$1 "***"', $mensaje);
    }

    /**
     * Se reintenta cada vez más tarde; después de varios intentos se avisa al alojamiento.
     */
    private function anotarFallo(Envio $envio, Throwable $e): void
    {
        $envio->ultimo_error = mb_substr(self::sinDatosPersonales($e->getMessage()), 0, 1000);
        if ($envio->intentos >= self::INTENTOS_MAXIMOS) {
            $envio->estado = EstadoEnvio::Fallido;
            Aviso::abrir($envio->propiedad_id, 'envios_fallidos', NivelAviso::Advertencia);
        } else {
            $envio->programado_para = now('UTC')->addMinutes(5 * (2 ** ($envio->intentos - 1)));
        }
        $envio->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function cargaWebhook(DisparadorAutomatizacion $disparador, Reserva $reserva, EventoReserva $evento): array
    {
        $reserva->loadMissing(['titular.contactos', 'unidad']);

        return [
            'evento' => $disparador->value,
            'ocurrido_en' => $evento->ocurrido_en->toIso8601String(),
            'propiedad' => $reserva->propiedad->codigo,
            'reserva' => [
                'numero' => $reserva->numero,
                'codigo' => $reserva->codigo_publico,
                'estado' => $reserva->estado->value,
                'llegada' => $reserva->llegada->format('Y-m-d'),
                'salida' => $reserva->salida->format('Y-m-d'),
                'personas' => $reserva->personas,
                'unidad' => $reserva->unidad->nombre ?? null,
                'moneda' => $reserva->moneda,
                'total' => $reserva->total,
                'pagado' => $reserva->pagado,
            ],
            'huesped' => [
                'nombre' => $reserva->titular?->nombreCompleto(),
                'correo' => self::correoDe($reserva),
                'telefono' => self::telefonoDe($reserva),
                'idioma' => self::idiomaDe($reserva),
            ],
        ];
    }
}
