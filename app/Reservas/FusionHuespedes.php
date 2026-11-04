<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Models\ContactoHuesped;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Junta dos fichas de la misma persona: la otra se borra y todo pasa a la que se conserva. Los datos
 * que le falten a la que se conserva se toman de la otra; nunca se pisa lo que ya tiene.
 */
final class FusionHuespedes
{
    private const CAMPOS = [
        'nombre', 'apellido1', 'apellido2', 'idioma', 'fecha_nacimiento', 'lugar_nacimiento', 'nacionalidad_texto',
        'documento_tipo_texto', 'domicilio_calle', 'domicilio_numero', 'domicilio_ciudad', 'domicilio_region',
        'domicilio_codigo_postal', 'domicilio_pais_texto', 'id_fiscal_personal', 'id_fiscal_empresa',
    ];

    public function fusionar(Huesped $conservar, Huesped $otra): Huesped
    {
        if ($conservar->id === $otra->id || $conservar->propiedad_id !== $otra->propiedad_id) {
            throw new LogicException('Solo se fusionan dos fichas distintas de la misma propiedad.');
        }
        if ($conservar->sincronizada || $otra->sincronizada) {
            throw new LogicException('Una ficha sincronizada se cambia en la principal.');
        }

        return DB::transaction(function () use ($conservar, $otra): Huesped {
            foreach (self::CAMPOS as $campo) {
                if (blank($conservar->getAttribute($campo)) && filled($otra->getAttribute($campo))) {
                    $conservar->setAttribute($campo, $otra->getAttribute($campo));
                }
            }
            if (blank($conservar->documento_numero) && filled($otra->documento_numero)) {
                $conservar->documento_numero = $otra->documento_numero;
            }
            if (filled($otra->notas)) {
                $conservar->notas = trim((string) $conservar->notas."\n".$otra->notas);
            }
            $conservar->save();

            Reserva::query()->where('titular_id', $otra->id)->update(['titular_id' => $conservar->id]);
            Grupo::query()->where('coordinador_id', $otra->id)->update(['coordinador_id' => $conservar->id]);

            // Acompañantes: si los dos estaban en la misma reserva, queda una sola fila.
            $yaEstan = DB::table('reservas_huespedes')->where('huesped_id', $conservar->id)->pluck('reserva_id');
            DB::table('reservas_huespedes')->where('huesped_id', $otra->id)->whereIn('reserva_id', $yaEstan)->delete();
            DB::table('reservas_huespedes')->where('huesped_id', $otra->id)->update(['huesped_id' => $conservar->id]);

            $existentes = $conservar->contactos()->pluck('valor_normalizado')->all();
            $orden = (int) $conservar->contactos()->max('orden');
            foreach ($otra->contactos as $contacto) {
                /** @var ContactoHuesped $contacto */
                if (! in_array($contacto->valor_normalizado, $existentes, true)) {
                    $contacto->update(['huesped_id' => $conservar->id, 'orden' => ++$orden]);
                }
            }

            RegistroAuditoria::anotar('huesped.fusionar', $conservar, ['otra' => $otra->id], $conservar->propiedad_id);
            $otra->delete();

            return $conservar->refresh();
        });
    }
}
