<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\TipoContacto;
use App\Models\Huesped;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Posibles fichas duplicadas de una propiedad (D-088): mismo nombre, correo, teléfono (últimas 9 cifras) o documento. Son candidatas: fusionar lo
 * decide una persona, y no se puede deshacer. Solo fichas propias: las de una principal se fusionan allá.
 */
final class DetectorDuplicados
{
    private const GRUPOS_MAXIMOS = 200;

    /**
     * @return list<array{motivo: string, huespedes: list<Huesped>}>
     */
    public function grupos(int $propiedadId): array
    {
        // Solo los números: con muchas fichas, tenerlas todas en memoria no cabe en un hosting compartido.
        $porClave = [];
        Huesped::query()->with('contactos')->where('propiedad_id', $propiedadId)->where('sincronizada', false)->chunk(500, function (Collection $huespedes) use (&$porClave): void {
            /** @var Collection<int, Huesped> $huespedes */
            foreach ($huespedes as $huesped) {
                foreach ($this->claves($huesped) as $clave) {
                    $porClave[$clave][] = $huesped->id;
                }
            }
        });

        $elegidos = [];
        $vistos = [];
        foreach ($porClave as $clave => $ids) {
            // Dos personas con el mismo nombre y el mismo correo no salen dos veces.
            $firma = implode(',', $ids);
            if (count($ids) < 2 || isset($vistos[$firma])) {
                continue;
            }
            $vistos[$firma] = true;
            $elegidos[] = ['motivo' => explode(':', (string) $clave, 2)[0], 'ids' => $ids];
            if (count($elegidos) >= self::GRUPOS_MAXIMOS) {
                break;
            }
        }

        $fichas = Huesped::query()->with('contactos')->withCount('reservasComoTitular')
            ->whereIn('id', array_merge(...array_column($elegidos, 'ids') ?: [[]]))->get()->keyBy('id');

        return array_map(fn (array $grupo): array => [
            'motivo' => $grupo['motivo'],
            'huespedes' => array_map(fn (int $id): Huesped => $fichas[$id], $grupo['ids']),
        ], $elegidos);
    }

    /**
     * @return list<string>
     */
    private function claves(Huesped $huesped): array
    {
        $claves = [];
        $nombre = trim(Str::lower(Str::ascii(implode(' ', array_filter([$huesped->nombre, $huesped->apellido1, $huesped->apellido2])))));
        // Una sola palabra no basta: «Ana» no dice que sea la misma persona.
        if (str_contains($nombre, ' ')) {
            $claves[] = 'nombre:'.$nombre;
        }
        if (filled($huesped->getAttribute('documento_numero_hmac'))) {
            $claves[] = 'documento:'.$huesped->getAttribute('documento_numero_hmac');
        }
        foreach ($huesped->contactos as $contacto) {
            if ($contacto->tipo === TipoContacto::Correo && filled($contacto->valor)) {
                $claves[] = 'correo:'.mb_strtolower(trim($contacto->valor));
            }
            if ($contacto->tipo === TipoContacto::Telefono) {
                $cifras = (string) preg_replace('/\D+/', '', (string) $contacto->valor);
                if (strlen($cifras) >= 7) {
                    $claves[] = 'telefono:'.substr($cifras, -9);
                }
            }
        }

        return array_values(array_unique($claves));
    }
}
