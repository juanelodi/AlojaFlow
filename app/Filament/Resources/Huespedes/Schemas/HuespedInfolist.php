<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Schemas;

use App\Documentos\CamposPropios;
use App\Models\CampoHuesped;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Support\Formato;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HuespedInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('ui.huespedes.datos_personales'))->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->schema([
                    TextEntry::make('nombre')->label(__('ui.huespedes.nombre'))->placeholder('—'),
                    TextEntry::make('apellidos')->label(__('ui.huespedes.apellidos'))
                        ->state(fn (Huesped $record): string => trim($record->apellido1.' '.$record->apellido2))->placeholder('—'),
                    TextEntry::make('idioma')->label(__('ui.huespedes.idioma'))->placeholder('—'),
                    TextEntry::make('nacionalidad_texto')->label(__('ui.huespedes.nacionalidad'))->placeholder('—'),
                    TextEntry::make('nacimiento')->label(__('ui.huespedes.nacimiento'))
                        ->state(fn (Huesped $record): ?string => $record->fecha_nacimiento ? Formato::fecha($record->fecha_nacimiento).($record->lugar_nacimiento ? ' · '.$record->lugar_nacimiento : '') : null)
                        ->placeholder('—'),
                    // Solo los últimos caracteres: el número completo se guarda cifrado (D-022).
                    TextEntry::make('documento')->label(__('ui.huespedes.documento'))
                        ->state(fn (Huesped $record): ?string => $record->documento_numero
                            ? trim(($record->documento_tipo_texto ?? '').' •••• '.mb_substr($record->documento_numero, -4))
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('fiscal')->label(__('ui.huespedes.fiscal'))
                        ->state(fn (Huesped $record): ?string => $record->id_fiscal_empresa ?? $record->id_fiscal_personal)->placeholder('—'),
                    TextEntry::make('domicilio')->label(__('ui.huespedes.domicilio'))->columnSpan(['lg' => 2])
                        ->state(fn (Huesped $record): ?string => implode(', ', array_filter([
                            trim($record->domicilio_calle.' '.$record->domicilio_numero), $record->domicilio_codigo_postal,
                            $record->domicilio_ciudad, $record->domicilio_region, $record->domicilio_pais_texto,
                        ])) ?: null)
                        ->placeholder('—'),
                    TextEntry::make('notas')->label(__('ui.huespedes.notas'))->columnSpanFull()->placeholder('—'),
                ]),
            Section::make(__('ui.huespedes.contactos'))->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextEntry::make('lista_contactos')->label(__('ui.huespedes.contactos'))
                        ->state(fn (Huesped $record): array => $record->contactos->map(fn ($c): string => $c->tipo->etiqueta().': '.$c->valor)->all())
                        ->listWithLineBreaks()->placeholder('—'),
                    TextEntry::make('lista_campos')->label(__('ui.huespedes.campos'))
                        ->state(fn (Huesped $record): array => $record->campos->map(fn (CampoHuesped $c): string => $c->campo.': '.$c->valor)->all())
                        ->listWithLineBreaks()->placeholder('—'),
                    TextEntry::make('lista_campos_propios')->label(__('documentos_propios.campos.seccion'))->visible(fn (Huesped $record): bool => CamposPropios::lineas($record) !== [])
                        ->state(fn (Huesped $record): array => CamposPropios::lineas($record))->listWithLineBreaks(),
                ]),
            Section::make(__('ui.huespedes.reservas'))->columnSpanFull()
                ->schema([
                    TextEntry::make('lista_reservas')->label(__('ui.huespedes.reservas'))->hiddenLabel()
                        ->state(fn (Huesped $record): array => $record->reservasComoTitular()->with('unidad')->latest('llegada')->limit(30)->get()
                            ->map(fn (Reserva $r): string => $r->numero.' · '.$r->unidad->nombre.' · '.Formato::fecha($r->llegada).' → '.Formato::fecha($r->salida).' · '.$r->estado->etiqueta())->all())
                        ->listWithLineBreaks()->bulleted()->placeholder('—'),
                ]),
        ]);
    }
}
