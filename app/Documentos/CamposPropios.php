<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Documentos;

use App\Enums\EntidadCampo;
use App\Enums\TipoCampoPropio;
use App\Models\CampoPropio;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Models\ValorCampoPropio;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que cada alojamiento quiere guardar además de lo que pide el sistema (D-122). Vale igual en independientes y subordinadas: la sincronización no toca sus valores.
 */
final class CamposPropios
{
    /**
     * @return Collection<int, CampoPropio>
     */
    public static function de(int $propiedadId, EntidadCampo $entidad): Collection
    {
        return CampoPropio::query()->where('propiedad_id', $propiedadId)->where('entidad', $entidad->value)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get();
    }

    public static function hay(int $propiedadId, EntidadCampo $entidad): bool
    {
        return Modulos::activo(Modulo::Documentos) && CampoPropio::query()->where('propiedad_id', $propiedadId)->where('entidad', $entidad->value)->where('activo', true)->exists();
    }

    /**
     * Lo guardado, por campo, para llenar un formulario.
     *
     * @return array<int, string|bool>
     */
    public static function valoresParaFormulario(Reserva|Huesped $objeto): array
    {
        $valores = self::consulta($objeto)->pluck('valor', 'campo_propio_id')->all();
        $salida = [];
        foreach (self::de($objeto->propiedad_id, self::entidadDe($objeto)) as $campo) {
            $valor = $valores[$campo->id] ?? null;
            $salida[$campo->id] = $campo->tipo === TipoCampoPropio::SiNo ? $valor !== null : (string) $valor;
        }

        return $salida;
    }

    /**
     * Solo los campos de su propiedad y los que llegaron: otro formulario no borra lo que no conoce.
     *
     * @param  array<int|string, mixed>  $valores
     */
    public static function guardar(Reserva|Huesped $objeto, array $valores): void
    {
        $entidad = self::entidadDe($objeto);
        $columna = $entidad === EntidadCampo::Huesped ? 'huesped_id' : 'reserva_id';
        foreach (self::de($objeto->propiedad_id, $entidad) as $campo) {
            if (! array_key_exists($campo->id, $valores)) {
                continue;
            }
            $valor = $campo->tipo === TipoCampoPropio::SiNo ? ($valores[$campo->id] ? '1' : '') : mb_substr(trim((string) $valores[$campo->id]), 0, 1000);
            if ($valor === '') {
                ValorCampoPropio::query()->where('campo_propio_id', $campo->id)->where($columna, $objeto->id)->delete();
            } else {
                ValorCampoPropio::query()->updateOrCreate(['campo_propio_id' => $campo->id, $columna => $objeto->id], ['valor' => $valor]);
            }
        }
    }

    /**
     * «Placa: ABC-123», para leerlo en una ficha.
     *
     * @return list<string>
     */
    public static function lineas(Reserva|Huesped $objeto): array
    {
        if (! Modulos::activo(Modulo::Documentos)) {
            return [];
        }

        return self::consulta($objeto)->with('campo')->get()
            ->sortBy(fn (ValorCampoPropio $v): array => [$v->campo->orden, $v->campo->nombre])
            ->map(fn (ValorCampoPropio $v): string => $v->campo->nombre.': '.$v->campo->legible($v->valor))->values()->all();
    }

    /**
     * Las variables de una reserva: sus campos y los de su titular. Sin valor quedan vacías, para que no salga la variable en un documento.
     *
     * @return array<string, string>
     */
    public static function variables(Reserva $reserva): array
    {
        if (! Modulos::activo(Modulo::Documentos)) {
            return [];
        }
        $campos = CampoPropio::query()->where('propiedad_id', $reserva->propiedad_id)->get();
        if ($campos->isEmpty()) {
            return [];
        }
        $valores = ValorCampoPropio::query()->whereIn('campo_propio_id', $campos->modelKeys())
            ->where(fn ($q) => $q->where('reserva_id', $reserva->id)->when($reserva->titular_id !== null, fn ($t) => $t->orWhere('huesped_id', $reserva->titular_id)))
            ->pluck('valor', 'campo_propio_id');

        return $campos->mapWithKeys(fn (CampoPropio $campo): array => ['campo_'.$campo->clave => $campo->legible($valores->get($campo->id))])->all();
    }

    /**
     * Los de varios huéspedes, por clave: para la fila de cada uno en un documento.
     *
     * @param  list<int>  $huespedes
     * @return array<int, array<string, string>>
     */
    public static function deHuespedes(int $propiedadId, array $huespedes): array
    {
        if (! Modulos::activo(Modulo::Documentos) || $huespedes === []) {
            return [];
        }
        $campos = CampoPropio::query()->where('propiedad_id', $propiedadId)->where('entidad', EntidadCampo::Huesped->value)->get();
        $salida = [];
        foreach ($huespedes as $huesped) {
            $salida[$huesped] = $campos->mapWithKeys(fn (CampoPropio $campo): array => ['h_campo_'.$campo->clave => ''])->all();
        }
        $valores = ValorCampoPropio::query()->whereIn('campo_propio_id', $campos->modelKeys())->whereIn('huesped_id', $huespedes)->get();
        foreach ($valores as $valor) {
            /** @var CampoPropio $campo */
            $campo = $campos->firstWhere('id', $valor->campo_propio_id);
            $salida[(int) $valor->huesped_id]['h_campo_'.$campo->clave] = $campo->legible($valor->valor);
        }

        return $salida;
    }

    /**
     * Lo de quien se fusiona pasa a quien se queda, sin pisar lo que ya tiene.
     */
    public static function fusionar(Huesped $conservar, Huesped $otra): void
    {
        foreach (ValorCampoPropio::query()->where('huesped_id', $otra->id)->get() as $valor) {
            $yaTiene = ValorCampoPropio::query()->where('huesped_id', $conservar->id)->where('campo_propio_id', $valor->campo_propio_id)->exists();
            $yaTiene ? $valor->delete() : $valor->update(['huesped_id' => $conservar->id]);
        }
    }

    /**
     * Pueden ser datos personales: se van con el resto, también los de sus reservas.
     */
    public static function borrarDe(Huesped $huesped): void
    {
        ValorCampoPropio::query()->where('huesped_id', $huesped->id)->delete();
        ValorCampoPropio::query()->whereIn('reserva_id', Reserva::query()->where('titular_id', $huesped->id)->select('id'))->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ValorCampoPropio>
     */
    private static function consulta(Reserva|Huesped $objeto): \Illuminate\Database\Eloquent\Builder
    {
        return ValorCampoPropio::query()->where(self::entidadDe($objeto) === EntidadCampo::Huesped ? 'huesped_id' : 'reserva_id', $objeto->id);
    }

    private static function entidadDe(Reserva|Huesped $objeto): EntidadCampo
    {
        return $objeto instanceof Huesped ? EntidadCampo::Huesped : EntidadCampo::Reserva;
    }
}
