<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propiedades;

use App\Copias\GestorCopias;
use App\Enums\EstadoEjecucion;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Models\Aviso;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deja de subordinar una propiedad: sus datos pasan a ser de AlojaFlow y la principal se olvida
 * (D-043). La instalación principal no se toca: la sincronización nunca le escribe (regla 2).
 */
final class Independizador
{
    private const TABLAS_CON_ORIGEN = ['unidades', 'huespedes', 'reservas'];

    public function __construct(private readonly GestorCopias $copias)
    {
    }

    /**
     * Noches con más de una reserva en la misma unidad. HotelDruid las permite; una propiedad
     * independiente no: hay que resolverlas allá antes de independizarla.
     *
     * @return list<array{unidad: string, fecha: string, reservas: list<string>}>
     */
    public function choques(Propiedad $propiedad): array
    {
        $dobles = DB::table('reservas_noches')->where('propiedad_id', $propiedad->id)
            ->select('unidad_id', 'fecha')->groupBy('unidad_id', 'fecha')->havingRaw('COUNT(*) > 1')
            ->orderBy('fecha')->limit(50)->get();

        $choques = [];
        foreach ($dobles as $doble) {
            $choques[] = [
                'unidad' => (string) DB::table('unidades')->where('id', $doble->unidad_id)->value('nombre'),
                'fecha' => (string) $doble->fecha,
                'reservas' => DB::table('reservas_noches as n')->join('reservas as r', 'r.id', '=', 'n.reserva_id')
                    ->where('n.unidad_id', $doble->unidad_id)->where('n.fecha', $doble->fecha)
                    ->orderBy('r.id')->pluck('r.numero')->map(fn (mixed $numero): string => (string) $numero)->all(),
            ];
        }

        return $choques;
    }

    /**
     * @return int las reservas que pasaron a la propiedad independiente
     */
    public function independizar(Propiedad $propiedad): int
    {
        $principal = $propiedad->principal;
        if ($propiedad->esIndependiente() || $principal === null) {
            throw new ErrorIndependizar(__('ui.independizar.error_no_subordinada'));
        }
        $this->comprobarSinSincronizacion($principal);
        $this->comprobarChoques($propiedad);

        $this->copias->crear('antes-de-independizar');

        try {
            return DB::transaction(fn (): int => $this->convertir($propiedad, $principal));
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) { // entró una reserva doble entre la revisión y aquí
                $this->comprobarChoques($propiedad);
            }

            throw $e;
        }
    }

    /**
     * Que esté corriendo una ahora. `ejecucion_vigente_id` no sirve para esto: es la última que salió
     * bien, y la tiene toda principal que haya sincronizado alguna vez.
     */
    private function comprobarSinSincronizacion(Principal $principal): void
    {
        if (EjecucionSincronizacion::query()->where('principal_id', $principal->id)->where('estado', EstadoEjecucion::EnCurso->value)->exists()) {
            throw new ErrorIndependizar(__('ui.independizar.error_sincronizando'));
        }
    }

    private function convertir(Propiedad $propiedad, Principal $principal): int
    {
        // Con la principal bloqueada, una sincronización no puede empezar a medias de la conversión.
        $bloqueada = Principal::query()->whereKey($principal->id)->lockForUpdate()->firstOrFail();
        $this->comprobarSinSincronizacion($bloqueada);

        $this->crearSaldosIniciales($propiedad);
        foreach (self::TABLAS_CON_ORIGEN as $tabla) {
            DB::table($tabla)->where('propiedad_id', $propiedad->id)->update(['sincronizada' => false]);
        }
        // Desde aquí, la base de datos impide la doble reserva (D-019).
        DB::table('reservas_noches')->where('propiedad_id', $propiedad->id)->update(['exclusiva' => 1]);

        $consecutivo = (int) DB::table('reservas')->where('propiedad_id', $propiedad->id)->selectRaw('MAX(CAST(numero AS UNSIGNED)) as ultimo')->value('ultimo');
        DB::table('propiedades')->where('id', $propiedad->id)->update([
            'modo' => ModoPropiedad::Nativa->value,
            'consecutivo_reserva' => $consecutivo,
            'actualizado_en' => now('UTC'),
        ]);

        // Con la principal se van sus credenciales y su historial; las filas conservan de dónde vinieron.
        $bloqueada->delete();
        Aviso::query()->where('propiedad_id', $propiedad->id)->whereNotNull('clave_unica')
            ->update(['clave_unica' => null, 'resuelto_en' => now()]);

        $reservas = DB::table('reservas')->where('propiedad_id', $propiedad->id)->count();
        RegistroAuditoria::anotar('propiedad.independizar', $propiedad, ['reservas' => $reservas], $propiedad->id);

        return $reservas;
    }

    /**
     * Lo pagado venía como un total de la principal, sin pagos: en una independiente lo pagado es
     * la suma de los pagos, así que cada reserva arranca con uno por ese total.
     */
    private function crearSaldosIniciales(Propiedad $propiedad): void
    {
        $hoy = $propiedad->hoy()->format('Y-m-d');
        DB::table('reservas as r')->where('r.propiedad_id', $propiedad->id)->where('r.pagado', '<>', 0)
            ->select('r.id', 'r.pagado', 'r.moneda')->orderBy('r.id')
            ->chunk(500, function ($reservas) use ($propiedad, $hoy): void {
                $filas = [];
                foreach ($reservas as $reserva) {
                    $yaPagado = (int) DB::table('pagos')->where('reserva_id', $reserva->id)->sum('importe');
                    if ((int) $reserva->pagado === $yaPagado) {
                        continue;
                    }
                    $filas[] = [
                        'propiedad_id' => $propiedad->id,
                        'reserva_id' => $reserva->id,
                        'fecha' => $hoy,
                        'metodo' => MetodoPago::Otro->value,
                        'importe' => (int) $reserva->pagado - $yaPagado,
                        'moneda' => $reserva->moneda,
                        'referencia' => __('ui.independizar.saldo_inicial'),
                        'creado_en' => now('UTC'),
                        'actualizado_en' => now('UTC'),
                    ];
                }
                if ($filas !== []) {
                    DB::table('pagos')->insert($filas);
                }
            });
    }

    private function comprobarChoques(Propiedad $propiedad): void
    {
        $choques = $this->choques($propiedad);
        if ($choques === []) {
            return;
        }
        $lista = array_map(fn (array $c): string => $c['unidad'].' · '.$c['fecha'].' ('.implode(', ', $c['reservas']).')', array_slice($choques, 0, 10));

        throw new ErrorIndependizar(__('ui.independizar.error_choques', ['lista' => implode('; ', $lista)]));
    }
}
