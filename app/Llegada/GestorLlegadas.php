<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Llegada;

use App\Archivos\GestorArchivos;
use App\Comunicacion\AjustesCorreo;
use App\Comunicacion\MensajeTexto;
use App\Documentos\GeneradorPdf;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoRegistroLlegada;
use App\Enums\EstadoReserva;
use App\Enums\TipoContacto;
use App\Enums\TipoEnlace;
use App\Enums\VerificacionAceptacion;
use App\Models\Aceptacion;
use App\Models\CodigoOtp;
use App\Models\ContactoHuesped;
use App\Models\DocumentoLegal;
use App\Models\EnlacePublico;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Paises\Paises;
use App\Support\Contactos;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Llegada en línea (M5): el huésped manda sus datos por un enlace, acepta los documentos y el personal
 * revisa. No toca la principal: en una subordinada, los datos se quedan en AlojaFlow.
 */
final class GestorLlegadas
{
    private const MINUTOS_OTP = 10;

    private const INTENTOS_OTP = 5;

    private const ANIOS_CONSERVAR_ACEPTACION = 5;

    public function __construct(private readonly GestorArchivos $archivos, private readonly AjustesCorreo $correo)
    {
    }

    public function enlaceDe(Reserva $reserva): EnlacePublico
    {
        $existente = EnlacePublico::query()->where('objeto_type', $reserva->getMorphClass())->where('objeto_id', $reserva->id)
            ->where('tipo', TipoEnlace::Llegada->value)->whereNull('revocado_en')->latest('id')->first();
        if ($existente !== null && $existente->vigente()) {
            return $existente;
        }

        return EnlacePublico::nuevo($reserva->propiedad_id, TipoEnlace::Llegada, $reserva, $reserva->salida->addDay()->endOfDay());
    }

    public function urlDe(Reserva $reserva, ?string $idioma = null): string
    {
        return route('llegada.formulario', ['token' => $this->enlaceDe($reserva)->token()] + ($idioma !== null ? ['idioma' => $idioma] : []));
    }

    public function reservaDelToken(string $token): ?Reserva
    {
        $enlace = EnlacePublico::buscar(TipoEnlace::Llegada, $token);
        $reserva = $enlace?->objeto;
        if (! $reserva instanceof Reserva || $reserva->estado === EstadoReserva::Cancelada) {
            return null;
        }

        return $reserva;
    }

    public function pedirFoto(Propiedad $propiedad): bool
    {
        return (bool) (((array) $propiedad->ajustes)['llegada']['pedir_foto'] ?? true);
    }

    /**
     * La verificación por código solo tiene sentido si hay por dónde mandarlo.
     */
    public function usaCodigo(Propiedad $propiedad): bool
    {
        return (bool) (((array) $propiedad->ajustes)['llegada']['otp'] ?? false) && $this->correo->configurado();
    }

    /**
     * Los documentos vigentes, en el idioma del huésped o, si falta, en el de la propiedad.
     *
     * @return Collection<int, DocumentoLegal>
     */
    public function documentosPara(Propiedad $propiedad, string $idioma): Collection
    {
        $vigentes = DocumentoLegal::query()->where('propiedad_id', $propiedad->id)->whereNotNull('publicado_en')->whereNull('retirado_en')
            ->orderBy('tipo')->get();

        $elegidos = new Collection();
        foreach ($vigentes->groupBy(fn (DocumentoLegal $d): string => $d->tipo->value) as $deUnTipo) {
            $elegidos->push($deUnTipo->firstWhere('idioma', $idioma) ?? $deUnTipo->firstWhere('idioma', $propiedad->idioma) ?? $deUnTipo->firstOrFail());
        }

        return $elegidos;
    }

    /**
     * Guarda lo que manda el huésped. Se puede repetir mientras nadie lo haya revisado: corrige.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, UploadedFile|null>  $fotos  por número de persona
     * @param  list<int>  $documentosMarcados
     */
    public function guardar(Reserva $reserva, array $datos, array $fotos, array $documentosMarcados, string $idioma): RegistroLlegada
    {
        $pais = Paises::de($reserva->propiedad);
        $registro = DB::transaction(function () use ($reserva, $datos, $documentosMarcados, $idioma, $pais): RegistroLlegada {
            $registro = RegistroLlegada::query()->lockForUpdate()->firstOrNew(['reserva_id' => $reserva->id]);
            if ($registro->exists && $registro->estado !== EstadoRegistroLlegada::Pendiente) {
                throw new RuntimeException(__('llegada.ya_recibido'));
            }
            $registro->fill([
                'propiedad_id' => $reserva->propiedad_id,
                'estado' => EstadoRegistroLlegada::Pendiente,
                'idioma' => $idioma,
                'hora_llegada_estimada' => $datos['hora_llegada'] ?? null,
                'notas_huesped' => $datos['notas'] ?? null,
                'documentos_marcados' => $documentosMarcados,
                'conservar_hasta' => $reserva->salida->addDays($pais->diasConservarRegistro()),
            ])->save();

            $registro->personas()->delete();
            foreach (array_values((array) $datos['personas']) as $i => $persona) {
                /** @var array<string, mixed> $persona */
                $extras = array_filter(array_intersect_key($persona, $pais->camposLlegada()), fn (mixed $v): bool => filled($v));
                $registro->personas()->create(array_intersect_key($persona, array_flip([
                    'nombre', 'apellido1', 'apellido2', 'sexo', 'fecha_nacimiento', 'nacionalidad_texto', 'documento_tipo', 'documento_numero', 'documento_vencimiento',
                    'domicilio_calle', 'domicilio_ciudad', 'domicilio_region', 'domicilio_codigo_postal', 'domicilio_pais_texto', 'correo', 'telefono',
                ])) + ['orden' => $i, 'es_titular' => $i === 0, 'datos_pais' => $extras === [] ? null : $extras]);
            }

            return $registro;
        });

        foreach ($fotos as $i => $foto) {
            if ($foto instanceof UploadedFile) {
                $this->archivos->guardar(
                    (string) file_get_contents($foto->getRealPath()),
                    'documento-'.($i + 1).'.'.$foto->extension(),
                    (string) $foto->getMimeType(),
                    ClaseArchivo::DocumentoIdentidad,
                    $registro,
                    $reserva->propiedad_id,
                    cifrar: true,
                    conservarHasta: $reserva->salida->addDays($pais->diasConservarImagenes()),
                );
            }
        }

        return $registro->refresh();
    }

    public function correoDe(RegistroLlegada $registro): ?string
    {
        return $registro->personas->first()?->correo;
    }

    /**
     * Manda un código nuevo al correo del titular; el anterior deja de servir.
     */
    public function mandarCodigo(RegistroLlegada $registro): void
    {
        $destino = (string) $this->correoDe($registro);
        $huella = hash_hmac('sha256', mb_strtolower($destino), (string) config('app.key'));
        CodigoOtp::query()->where('objeto_type', $registro->getMorphClass())->where('objeto_id', $registro->id)->whereNull('verificado_en')->delete();

        $codigo = (string) random_int(100000, 999999);
        $otp = new CodigoOtp(['proposito' => 'llegada', 'canal' => 'correo', 'destino_hmac' => $huella, 'codigo_hash' => Hash::make($codigo), 'expira_en' => now('UTC')->addMinutes(self::MINUTOS_OTP)]);
        $otp->objeto()->associate($registro);
        $otp->save();

        $propiedad = $registro->propiedad;
        $idioma = (string) ($registro->idioma ?: $propiedad->idioma);
        $this->correo->mailer()->to($destino)->send(new MensajeTexto(
            __('llegada.otp_asunto', ['propiedad' => $propiedad->nombre], $idioma),
            __('llegada.otp_cuerpo', ['codigo' => $codigo, 'minutos' => self::MINUTOS_OTP, 'propiedad' => $propiedad->nombre], $idioma),
            $this->correo->remitente(),
            $propiedad->nombre,
        ));
    }

    /**
     * El código, si es correcto; tras varios intentos deja de servir.
     */
    public function verificarCodigo(RegistroLlegada $registro, string $codigo): ?CodigoOtp
    {
        $otp = CodigoOtp::query()->where('objeto_type', $registro->getMorphClass())->where('objeto_id', $registro->id)->whereNull('verificado_en')->latest('id')->first();
        if ($otp === null || $otp->expira_en->isPast() || $otp->intentos >= self::INTENTOS_OTP) {
            return null;
        }
        if (! Hash::check($codigo, $otp->codigo_hash)) {
            $otp->increment('intentos');

            return null;
        }
        $otp->update(['verificado_en' => now('UTC')]);

        return $otp;
    }

    /**
     * Da por recibido el registro y deja constancia de cada documento aceptado.
     */
    public function completar(RegistroLlegada $registro, VerificacionAceptacion $verificacion, ?CodigoOtp $otp, ?string $ip, ?string $agente): void
    {
        DB::transaction(function () use ($registro, $verificacion, $otp, $ip, $agente): void {
            $firmante = $registro->personas()->first()?->nombreCompleto() ?? '';
            $reserva = $registro->reserva;
            $documentos = DocumentoLegal::query()->where('propiedad_id', $registro->propiedad_id)->whereIn('id', (array) $registro->documentos_marcados)->get();
            foreach ($documentos as $documento) {
                $aceptacion = Aceptacion::query()->create([
                    'documento_legal_id' => $documento->id,
                    'propiedad_id' => $registro->propiedad_id,
                    'reserva_id' => $registro->reserva_id,
                    'registro_llegada_id' => $registro->id,
                    'firmante_nombre' => mb_substr($firmante, 0, 200),
                    'huella_documento' => (string) $documento->huella,
                    'aceptada_en' => now('UTC'),
                    'ip' => $ip,
                    'agente' => $agente === null ? null : mb_substr($agente, 0, 255),
                    'verificacion' => $verificacion,
                    'codigo_otp_id' => $otp?->id,
                    'conservar_hasta' => $reserva->salida->addYears(self::ANIOS_CONSERVAR_ACEPTACION),
                ]);
                $copia = $this->archivos->guardar(
                    app(GeneradorPdf::class)->copiaAceptacion($aceptacion, $documento, $registro),
                    'aceptacion-'.$aceptacion->id.'.pdf',
                    'application/pdf',
                    ClaseArchivo::CopiaAceptacion,
                    $aceptacion,
                    $registro->propiedad_id,
                    cifrar: true,
                    conservarHasta: $aceptacion->conservar_hasta,
                );
                $aceptacion->update(['copia_archivo_id' => $copia->id]);
            }
            $registro->update(['estado' => EstadoRegistroLlegada::Recibido, 'recibido_en' => now('UTC'), 'documentos_marcados' => null]);
        });
    }

    public function marcarRevisado(RegistroLlegada $registro): void
    {
        $registro->update(['estado' => EstadoRegistroLlegada::Revisado, 'revisado_por' => auth()->id(), 'revisado_en' => now('UTC')]);
        RegistroAuditoria::anotar('llegada.revisar', $registro, [], $registro->propiedad_id);
    }

    /**
     * Pasa los datos a las fichas de huésped, solo en una propiedad independiente: en una subordinada
     * las fichas son de la principal.
     *
     * @return int personas pasadas
     */
    public function pasarAFichas(RegistroLlegada $registro): int
    {
        $reserva = $registro->reserva;
        if (! $registro->propiedad->esIndependiente()) {
            return 0;
        }

        return DB::transaction(function () use ($registro, $reserva): int {
            $pasadas = 0;
            foreach ($registro->personas as $persona) {
                $titular = $persona->es_titular ? $reserva->titular : null;
                $huesped = $titular ?? Huesped::query()->create(['propiedad_id' => $reserva->propiedad_id, 'nombre' => $persona->nombre]);
                if ($huesped->sincronizada) {
                    continue;
                }
                $huesped->fill(array_filter([
                    'nombre' => $persona->nombre,
                    'apellido1' => $persona->apellido1,
                    'apellido2' => $persona->apellido2,
                    'sexo' => $persona->sexo,
                    'fecha_nacimiento' => $persona->fecha_nacimiento,
                    'nacionalidad_texto' => $persona->nacionalidad_texto,
                    'documento_tipo_texto' => $persona->documento_tipo,
                    'documento_numero' => $persona->documento_numero,
                    'documento_vencimiento' => $persona->documento_vencimiento,
                    'domicilio_calle' => $persona->domicilio_calle,
                    'domicilio_ciudad' => $persona->domicilio_ciudad,
                    'domicilio_region' => $persona->domicilio_region,
                    'domicilio_codigo_postal' => $persona->domicilio_codigo_postal,
                    'domicilio_pais_texto' => $persona->domicilio_pais_texto,
                ], fn (mixed $v): bool => filled($v)))->save();
                $this->anadirContacto($huesped, TipoContacto::Correo, $persona->correo);
                $this->anadirContacto($huesped, TipoContacto::Telefono, $persona->telefono);
                $reserva->huespedes()->syncWithoutDetaching([$huesped->id => ['orden' => $persona->orden]]);
                $pasadas++;
            }

            return $pasadas;
        });
    }

    private function anadirContacto(Huesped $huesped, TipoContacto $tipo, ?string $valor): void
    {
        if (blank($valor)) {
            return;
        }
        $normalizado = Contactos::normalizar($tipo->value, (string) $valor);
        if (! ContactoHuesped::query()->where('huesped_id', $huesped->id)->where('valor_normalizado', $normalizado)->exists()) {
            $huesped->contactos()->create(['tipo' => $tipo, 'valor' => $valor, 'valor_normalizado' => $normalizado, 'orden' => $huesped->contactos()->count()]);
        }
    }
}
