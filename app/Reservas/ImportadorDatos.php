<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\TipoContacto;
use App\Models\ContactoHuesped;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Unidad;
use App\Paises\Paises;
use App\Support\Contactos;
use App\Support\Csv;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traer huéspedes y reservas de un archivo CSV, para quien viene de otro sistema (D-127). Primero se revisa sin cambiar nada y se dice qué le pasa a cada línea.
 * Lo que se importa no dispara los mensajes de «reserva creada»: nadie espera un correo de una reserva de hace meses.
 */
final class ImportadorDatos
{
    /** Sobra para las filas de abajo, y cabe en lo que Livewire acepta por petición: 1 MB, contando lo que ya trae la pantalla. */
    public const BYTES_MAXIMOS = 512000;

    /** Más que esto no cabe en una petición de un hosting compartido: se divide el archivo. */
    public const FILAS_MAXIMAS = 1000;

    public const HUESPEDES = 'huespedes';

    public const RESERVAS = 'reservas';

    private const COLUMNAS_HUESPEDES = ['nombre', 'apellido1', 'apellido2', 'correo', 'telefono', 'nacionalidad', 'documento_tipo', 'documento_numero', 'nacimiento', 'notas'];

    private const COLUMNAS_RESERVAS = ['unidad', 'llegada', 'salida', 'personas', 'nombre', 'apellido1', 'correo', 'telefono', 'total', 'pagado', 'estado', 'origen', 'comentario'];

    /**
     * Lo que se va a hacer con cada línea, sin hacerlo.
     *
     * @return list<array<string, mixed>> cada fila con linea, resumen, error y aviso, más sus datos
     */
    public function revisar(Propiedad $propiedad, string $tipo, string $texto): array
    {
        $lineas = $this->lineas($tipo, $texto);
        if ($lineas === null) {
            return [['linea' => 0, 'resumen' => '', 'error' => __('importar.error_tamano', ['filas' => self::FILAS_MAXIMAS]), 'aviso' => null]];
        }

        return $tipo === self::RESERVAS ? $this->revisarReservas($propiedad, $lineas) : $this->revisarHuespedes($propiedad, $lineas);
    }

    /**
     * Se vuelve a revisar aquí: lo que viene del navegador no se da por bueno. Cada línea es aparte: una que falle no detiene a las demás.
     *
     * @return array{importadas: int, fallidas: list<array<string, mixed>>, pendiente: string} pendiente: el texto, solo con lo que falló
     */
    public function importar(Propiedad $propiedad, string $tipo, string $texto): array
    {
        $importadas = 0;
        $fallidas = [];
        foreach ($this->revisar($propiedad, $tipo, $texto) as $fila) {
            if ($fila['error'] !== null) {
                $fallidas[] = $fila;

                continue;
            }
            if ($fila['aviso'] !== null) {
                continue;
            }
            try {
                $tipo === self::RESERVAS ? $this->crearReserva($propiedad, $fila) : $this->crearHuesped($propiedad, $fila);
                $importadas++;
            } catch (ReservaRechazada $e) {
                $fallidas[] = ['linea' => $fila['linea'], 'resumen' => $fila['resumen'], 'error' => $e->getMessage(), 'aviso' => null];
            } catch (QueryException) {
                // El mensaje de la base trae los datos de la línea: no se muestra.
                $fallidas[] = ['linea' => $fila['linea'], 'resumen' => $fila['resumen'], 'error' => __('importar.error_guardar'), 'aviso' => null];
            }
        }
        RegistroAuditoria::anotar('importar.'.$tipo, $propiedad, ['importadas' => $importadas, 'fallidas' => count($fallidas)], $propiedad->id);

        return ['importadas' => $importadas, 'fallidas' => $fallidas, 'pendiente' => $this->soloFallidas($tipo, $texto, $fallidas)];
    }

    /**
     * El encabezado, en el idioma de quien la baja, y una línea de ejemplo con datos inventados.
     *
     * @return list<list<string>>
     */
    public function plantilla(string $tipo): array
    {
        if ($tipo === self::RESERVAS) {
            return [
                array_map(fn (string $columna): string => __('importar.columnas.'.$columna), self::COLUMNAS_RESERVAS),
                ['101', '2027-03-01', '2027-03-04', '2', __('importar.ejemplo.nombre'), __('importar.ejemplo.apellido'), 'ana@example.com', '55 5550 0101', '3000.00', '1000.00', EstadoReserva::Confirmada->etiqueta(), __('importar.ejemplo.origen'), ''],
            ];
        }

        return [
            array_map(fn (string $columna): string => __('importar.columnas.'.$columna), self::COLUMNAS_HUESPEDES),
            [__('importar.ejemplo.nombre'), __('importar.ejemplo.apellido'), '', 'ana@example.com', '55 5550 0101', __('importar.ejemplo.pais'), '', '', '1990-05-20', ''],
        ];
    }

    /**
     * @return list<array{linea: int, campos: list<string>, crudo: string, sin_cerrar: bool}>|null null: es demasiado
     */
    private function lineas(string $tipo, string $texto): ?array
    {
        if (strlen($texto) > self::BYTES_MAXIMOS) {
            return null;
        }
        $registros = $this->registros($texto);
        if ($registros !== [] && $this->esEncabezado($tipo, $registros[0]['campos'])) {
            array_shift($registros);
        }

        return count($registros) > self::FILAS_MAXIMAS ? null : $registros;
    }

    /**
     * Registro por registro, no línea por línea: una nota de Excel puede traer saltos de línea entre comillas. El número es el de la línea donde empieza.
     * Unas comillas sin cerrar se tragan lo que sigue hasta el final: se avisa, para que esas filas no se pierdan calladas.
     *
     * @return list<array{linea: int, campos: list<string>, crudo: string, sin_cerrar: bool}>
     */
    private function registros(string $texto): array
    {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", Csv::enUtf8($texto)), "\n ");
        $flujo = fopen('php://memory', 'r+b');
        if ($flujo === false) {
            return [];
        }
        fwrite($flujo, $texto);
        rewind($flujo);
        $separador = Csv::separador((string) strtok($texto, "\n"));
        $registros = [];
        $linea = 1;
        while (true) {
            $inicio = (int) ftell($flujo);
            $campos = fgetcsv($flujo, null, $separador, '"', '');
            if ($campos === false) {
                break;
            }
            $crudo = substr($texto, $inicio, (int) ftell($flujo) - $inicio);
            $campos = array_map(fn (?string $campo): string => Csv::sinProteccion(trim((string) $campo)), $campos);
            // Excel deja filas de puros separadores cuando se borra una.
            if (implode('', $campos) !== '') {
                $registros[] = ['linea' => $linea, 'campos' => $campos, 'crudo' => $crudo, 'sin_cerrar' => feof($flujo) && substr_count($crudo, '"') % 2 === 1];
            }
            $linea += substr_count($crudo, "\n");
        }
        fclose($flujo);

        return $registros;
    }

    /**
     * @param  list<string>  $campos
     */
    private function esEncabezado(string $tipo, array $campos): bool
    {
        if ($tipo === self::RESERVAS) {
            return Csv::encabezadoDeFechas($campos);
        }
        $nombres = array_map(fn (string $idioma): string => mb_strtolower(__('importar.columnas.nombre', [], $idioma)), (array) config('alojaflow.idiomas'));

        return in_array(mb_strtolower($campos[0]), $nombres, true);
    }

    /**
     * Lo que queda por resolver, con su encabezado. Lo demás ya entró o se omitió: importarlo otra vez repetiría a quien no trae correo.
     *
     * @param  list<array<string, mixed>>  $fallidas
     */
    private function soloFallidas(string $tipo, string $texto, array $fallidas): string
    {
        $numeros = array_column($fallidas, 'linea');
        if ($numeros === []) {
            return '';
        }
        // El archivo entero: era demasiado y ni se leyó.
        if (in_array(0, $numeros, true)) {
            return $texto;
        }
        $quedan = array_filter(
            $this->registros($texto),
            fn (array $registro, int $i): bool => in_array($registro['linea'], $numeros, true) || ($i === 0 && $this->esEncabezado($tipo, $registro['campos'])),
            ARRAY_FILTER_USE_BOTH,
        );

        return rtrim(implode('', array_column($quedan, 'crudo')), "\n");
    }

    /**
     * @param  list<array{linea: int, campos: list<string>, crudo: string, sin_cerrar: bool}>  $lineas
     * @return list<array<string, mixed>>
     */
    private function revisarHuespedes(Propiedad $propiedad, array $lineas): array
    {
        $mesPrimero = Paises::de($propiedad)->fechaConMesPrimero();
        $vistos = [];
        $filas = [];
        foreach ($lineas as ['linea' => $linea, 'campos' => $campos, 'sin_cerrar' => $sinCerrar]) {
            $fila = array_combine(self::COLUMNAS_HUESPEDES, array_pad(array_slice($campos, 0, count(self::COLUMNAS_HUESPEDES)), count(self::COLUMNAS_HUESPEDES), ''));
            $nacimiento = $fila['nacimiento'] === '' ? null : Csv::fecha($fila['nacimiento'], $mesPrimero);
            $correo = $fila['correo'] === '' ? '' : Contactos::normalizar(TipoContacto::Correo->value, $fila['correo']);
            $error = match (true) {
                $sinCerrar => __('importar.error_comillas'),
                $fila['nombre'] === '' => __('importar.error_nombre'),
                $correo !== '' && filter_var($fila['correo'], FILTER_VALIDATE_EMAIL) === false => __('importar.error_correo'),
                $fila['nacimiento'] !== '' && $nacimiento === null => __('importar.error_fecha', ['formato' => Csv::formatoFecha($mesPrimero)]),
                default => null,
            };
            $aviso = null;
            if ($error === null && $correo !== '') {
                // Una familia puede compartir correo: la misma persona es con el mismo nombre, también dentro del archivo.
                $persona = $correo.'|'.$this->normal($fila['nombre']).'|'.$this->normal($fila['apellido1']);
                $aviso = isset($vistos[$persona]) || $this->conCorreo($propiedad, $correo, $fila['nombre'], $fila['apellido1']) !== null ? __('importar.ya_existe') : null;
                $vistos[$persona] = true;
            }
            $filas[] = $fila + [
                'linea' => $linea,
                'resumen' => $this->resumen([trim($fila['nombre'].' '.$fila['apellido1'].' '.$fila['apellido2']), $fila['correo'], $fila['nacimiento'] === '' ? '' : $this->dia($nacimiento, $fila['nacimiento'])]),
                'nacimiento_fecha' => $nacimiento,
                'error' => $error,
                'aviso' => $aviso,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array{linea: int, campos: list<string>, crudo: string, sin_cerrar: bool}>  $lineas
     * @return list<array<string, mixed>>
     */
    private function revisarReservas(Propiedad $propiedad, array $lineas): array
    {
        $mesPrimero = Paises::de($propiedad)->fechaConMesPrimero();
        $unidades = Unidad::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->get();
        $apartadas = [];
        $filas = [];
        foreach ($lineas as ['linea' => $linea, 'campos' => $campos, 'sin_cerrar' => $sinCerrar]) {
            $fila = array_combine(self::COLUMNAS_RESERVAS, array_pad(array_slice($campos, 0, count(self::COLUMNAS_RESERVAS)), count(self::COLUMNAS_RESERVAS), ''));
            $unidad = $unidades->first(fn (Unidad $u): bool => in_array(mb_strtolower($fila['unidad']), [mb_strtolower($u->nombre), mb_strtolower($u->codigo)], true));
            $llegada = Csv::fecha($fila['llegada'], $mesPrimero);
            $salida = Csv::fecha($fila['salida'], $mesPrimero);
            $total = $fila['total'] === '' ? 0 : Csv::importe($fila['total'], $propiedad->moneda);
            $pagado = $fila['pagado'] === '' ? 0 : Csv::importe($fila['pagado'], $propiedad->moneda);
            $estado = $this->estado($fila['estado']);
            $cancelada = $estado === EstadoReserva::Cancelada;
            $personas = max(1, (int) $fila['personas']);
            $error = match (true) {
                $sinCerrar => __('importar.error_comillas'),
                // Se va a omitir: no importa que su unidad ya no exista.
                $cancelada => null,
                $unidad === null => __('importar.error_unidad'),
                $llegada === null || $salida === null => __('importar.error_fecha', ['formato' => Csv::formatoFecha($mesPrimero)]),
                $salida <= $llegada => __('importar.error_orden'),
                CarbonImmutable::parse($llegada)->diffInDays(CarbonImmutable::parse($salida)) > GestorReservas::NOCHES_MAXIMAS => __('importar.error_orden'),
                $fila['nombre'] === '' => __('importar.error_nombre'),
                $fila['correo'] !== '' && filter_var($fila['correo'], FILTER_VALIDATE_EMAIL) === false => __('importar.error_correo'),
                $total === null || $pagado === null => __('importar.error_importe'),
                $estado === null => __('importar.error_estado'),
                $unidad->capacidad > 0 && $personas > $unidad->capacidadMaxima() => __('ui.reservas.error_capacidad', ['capacidad' => $unidad->capacidadMaxima()]),
                $this->choca($unidad, $llegada, $salida, $apartadas) => __('importar.error_ocupada'),
                default => null,
            };
            if ($error === null && ! $cancelada) {
                $this->apartar($unidad->id, (string) $llegada, (string) $salida, $apartadas);
            }
            $filas[] = $fila + [
                'linea' => $linea,
                'resumen' => $this->resumen([
                    $fila['unidad'],
                    $this->dia($llegada, $fila['llegada']).' → '.$this->dia($salida, $fila['salida']),
                    trim($fila['nombre'].' '.$fila['apellido1']),
                    $total === null ? $fila['total'] : Formato::dinero($total, $propiedad->moneda),
                    (int) $pagado > 0 ? __('importar.columnas.pagado').' '.Formato::dinero((int) $pagado, $propiedad->moneda) : '',
                ]),
                'unidad_id' => $unidad?->id,
                'llegada_fecha' => $llegada,
                'salida_fecha' => $salida,
                'personas_numero' => $personas,
                'total_importe' => $total,
                'pagado_importe' => $pagado,
                'estado_reserva' => $estado?->value,
                'error' => $error,
                'aviso' => $error === null && $cancelada ? __('importar.cancelada_omitida') : null,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<string>  $partes
     */
    private function resumen(array $partes): string
    {
        return implode(' · ', array_filter($partes, fn (string $parte): bool => $parte !== ''));
    }

    /**
     * Como se entendió y no como venía: si 03/04 se leyó al revés, en la vista previa se nota.
     */
    private function dia(?string $fecha, string $comoVenia): string
    {
        return $fecha === null ? $comoVenia : Formato::fecha(CarbonImmutable::parse($fecha));
    }

    /**
     * Vacío es confirmada; se acepta el valor o cómo se llama en cualquier idioma.
     */
    private function estado(string $texto): ?EstadoReserva
    {
        if ($texto === '') {
            return EstadoReserva::Confirmada;
        }
        $buscado = mb_strtolower($texto);
        foreach (EstadoReserva::cases() as $estado) {
            foreach ((array) config('alojaflow.idiomas') as $idioma) {
                if ($buscado === $estado->value || $buscado === mb_strtolower(__('enums.EstadoReserva.'.$estado->value, [], $idioma))) {
                    return $estado;
                }
            }
        }

        return null;
    }

    /**
     * Si esas noches ya están ocupadas, por la base o por otra línea del mismo archivo.
     *
     * @param  array<int, array<string, true>>  $apartadas
     */
    private function choca(Unidad $unidad, string $llegada, string $salida, array $apartadas): bool
    {
        if (DB::table('reservas_noches')->where('unidad_id', $unidad->id)->where('fecha', '>=', $llegada)->where('fecha', '<', $salida)->exists()) {
            return true;
        }
        for ($dia = CarbonImmutable::parse($llegada); $dia->format('Y-m-d') < $salida; $dia = $dia->addDay()) {
            if (isset($apartadas[$unidad->id][$dia->format('Y-m-d')])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, true>>  $apartadas
     */
    private function apartar(int $unidadId, string $llegada, string $salida, array &$apartadas): void
    {
        for ($dia = CarbonImmutable::parse($llegada); $dia->format('Y-m-d') < $salida; $dia = $dia->addDay()) {
            $apartadas[$unidadId][$dia->format('Y-m-d')] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function crearHuesped(Propiedad $propiedad, array $fila): Huesped
    {
        return DB::transaction(function () use ($propiedad, $fila): Huesped {
            $huesped = Huesped::query()->create([
                'propiedad_id' => $propiedad->id,
                'nombre' => mb_substr((string) $fila['nombre'], 0, 100),
                'apellido1' => $fila['apellido1'] === '' ? null : mb_substr((string) $fila['apellido1'], 0, 100),
                'apellido2' => $fila['apellido2'] === '' ? null : mb_substr((string) $fila['apellido2'], 0, 100),
                'nacionalidad_texto' => $fila['nacionalidad'] === '' ? null : mb_substr((string) $fila['nacionalidad'], 0, 100),
                'documento_tipo_texto' => $fila['documento_tipo'] === '' ? null : mb_substr((string) $fila['documento_tipo'], 0, 100),
                'documento_numero' => $fila['documento_numero'] === '' ? null : mb_substr((string) $fila['documento_numero'], 0, 100),
                'fecha_nacimiento' => $fila['nacimiento_fecha'],
                'notas' => $fila['notas'] === '' ? null : mb_substr((string) $fila['notas'], 0, 5000),
            ]);
            $this->crearContactos($huesped, (string) $fila['correo'], (string) $fila['telefono']);

            return $huesped;
        });
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function crearReserva(Propiedad $propiedad, array $fila): void
    {
        DB::transaction(function () use ($propiedad, $fila): void {
            // Sin eventos ni auditoría de cada una: los mensajes de «reserva creada» salen de ahí, y al importar nadie espera un correo.
            $gestor = new GestorReservas(registrar: false);
            $huesped = ($fila['correo'] === '' ? null : $this->conCorreo($propiedad, Contactos::normalizar(TipoContacto::Correo->value, (string) $fila['correo']), (string) $fila['nombre'], (string) $fila['apellido1']))
                ?? $this->crearHuesped($propiedad, ['apellido2' => '', 'nacionalidad' => '', 'documento_tipo' => '', 'documento_numero' => '', 'nacimiento_fecha' => null, 'notas' => ''] + $fila);
            $reserva = $gestor->crear($propiedad, [
                'unidad_id' => $fila['unidad_id'],
                'titular_id' => $huesped->id,
                'llegada' => $fila['llegada_fecha'],
                'salida' => $fila['salida_fecha'],
                'personas' => $fila['personas_numero'],
                'estado' => $fila['estado_reserva'],
                'total' => $fila['total_importe'],
                'origen_texto' => $fila['origen'],
                'comentario' => mb_substr((string) $fila['comentario'], 0, 5000),
            ]);
            if ($fila['pagado_importe'] > 0) {
                // Un anticipo de una reserva futura ya se cobró: con fecha de mañana no cuadraría ningún informe.
                $fecha = min((string) $fila['llegada_fecha'], $propiedad->hoy()->format('Y-m-d'));
                $gestor->registrarPago($reserva, (int) $fila['pagado_importe'], MetodoPago::Otro, CarbonImmutable::parse($fecha), __('importar.pago_importado'));
            }
        });
    }

    private function crearContactos(Huesped $huesped, string $correo, string $telefono): void
    {
        if ($correo !== '') {
            $huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => $correo, 'valor_normalizado' => Contactos::normalizar(TipoContacto::Correo->value, $correo), 'orden' => 0]);
        }
        if ($telefono !== '') {
            $telefono = mb_substr($telefono, 0, 100);
            $huesped->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => $telefono, 'valor_normalizado' => Contactos::normalizar(TipoContacto::Telefono->value, $telefono), 'orden' => 1]);
        }
    }

    /**
     * Es la misma persona si coinciden el correo y el nombre, como en el motor: con el correo nada más, se le pegarían reservas de otro.
     */
    private function conCorreo(Propiedad $propiedad, string $correo, string $nombre, string $apellido): ?Huesped
    {
        $contactos = ContactoHuesped::query()->with('huesped')->where('valor_normalizado', $correo)->whereHas('huesped', fn ($q) => $q->where('propiedad_id', $propiedad->id)->where('sincronizada', false))->get();
        foreach ($contactos as $contacto) {
            if ($this->normal($contacto->huesped->nombre) === $this->normal($nombre) && $this->normal($contacto->huesped->apellido1) === $this->normal($apellido)) {
                return $contacto->huesped;
            }
        }

        return null;
    }

    private function normal(?string $texto): string
    {
        return Str::lower(Str::ascii(trim((string) $texto)));
    }
}
