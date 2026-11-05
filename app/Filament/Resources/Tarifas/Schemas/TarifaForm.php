<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Schemas;

use App\Enums\ModoTarifa;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TarifaForm
{
    public static function configure(Schema $schema): Schema
    {
        $conPrecios = fn (Get $get): bool => blank($get('tarifa_base_id'));
        $porNoche = fn (Get $get): bool => $conPrecios($get) && self::modo($get) === ModoTarifa::PorNoche;

        return $schema->components([
            Section::make()->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', TarifaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(function (): ?int {
                            $permitidas = TarifaResource::propiedadesGestionables();

                            return count($permitidas) === 1 ? $permitidas[0] : null;
                        })
                        ->disabledOn('edit'),
                    TextInput::make('nombre')->label(__('ui.tarifas.nombre'))->required()->maxLength(100)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), TarifaResource::propiedadesGestionables()))),
                    Select::make('tarifa_base_id')->label(__('ui.tarifas.basada_en'))->helperText(__('ui.tarifas.basada_en_ayuda'))->native(false)->live()
                        ->placeholder(__('ui.tarifas.con_precios_propios'))
                        ->options(fn (Get $get, ?Tarifa $record): array => self::bases($get('propiedad_id'), $record))
                        // De una que ya tiene otras saliendo de ella no se puede sacar: el cálculo da una sola vuelta.
                        ->disabled(fn (?Tarifa $record): bool => $record !== null && $record->derivadas()->exists())
                        ->columnSpanFull(),
                    TextInput::make('ajuste_porcentaje')->label(__('ui.tarifas.ajuste_porcentaje'))->helperText(__('ui.tarifas.ajuste_porcentaje_ayuda'))->numeric()->minValue(-100)->maxValue(1000)->step('any')->suffix('%')
                        ->visible(fn (Get $get): bool => ! $conPrecios($get)),
                    CampoDinero::make('ajuste_noche')->label(__('ui.tarifas.ajuste_noche'))->helperText(__('ui.tarifas.ajuste_noche_ayuda'))
                        ->visible(fn (Get $get): bool => ! $conPrecios($get)),
                    Select::make('modo')->label(__('ui.tarifas.modo'))->helperText(__('ui.tarifas.modo_ayuda'))->options(ModoTarifa::opciones())->required()->native(false)->live()
                        ->default(ModoTarifa::PorNoche->value)->visible($conPrecios),
                    CampoDinero::make('precio_noche')
                        ->label(fn (Get $get): string => $porNoche($get) ? __('ui.tarifas.precio_base') : __('ui.tarifas.precio_persona'))
                        ->helperText(__('ui.tarifas.precio_base_ayuda'))->required()->visible($conPrecios),
                    TextInput::make('personas_incluidas')->label(__('ui.tarifas.personas_incluidas'))->helperText(__('ui.tarifas.personas_incluidas_ayuda'))->numeric()->minValue(1)->default(2)
                        ->required($porNoche)->visible($porNoche),
                    CampoDinero::make('precio_persona_extra')->label(__('ui.tarifas.persona_extra'))->default(0)->visible($porNoche),
                    CampoDinero::make('precio_fin_semana')->label(__('ui.tarifas.fin_semana'))->helperText(__('ui.tarifas.fin_semana_ayuda'))->visible($conPrecios)
                        ->dehydrateStateUsing(fn (mixed $state, Get $get): ?int => blank($state) ? null : \App\Support\Dinero::aUnidadMenor((string) $state, CampoDinero::moneda($get('propiedad_id')))),
                    TextInput::make('estancia_minima')->label(__('ui.tarifas.estancia_minima'))->numeric()->minValue(1)->default(1)->required(),
                    TextInput::make('personas_minimas')->label(__('ui.tarifas.personas_minimas'))->helperText(__('ui.tarifas.personas_ayuda'))->numeric()->integer()->minValue(1)->maxValue(1000)->live(onBlur: true),
                    TextInput::make('personas_maximas')->label(__('ui.tarifas.personas_maximas'))->numeric()->integer()->maxValue(1000)
                        ->minValue(fn (Get $get): int => max(1, (int) $get('personas_minimas'))),
                    Select::make('politica_id')->label(__('ui.tarifas.politica'))->helperText(__('ui.tarifas.politica_ayuda'))->native(false)->placeholder(__('ui.tarifas.politica_general'))
                        ->options(fn (Get $get): array => blank($get('propiedad_id')) ? [] : Politica::query()->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), TarifaResource::propiedadesGestionables()))->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->columnSpanFull(),
                    Toggle::make('activa')->label(__('ui.tarifas.activa'))->helperText(__('ui.tarifas.activa_ayuda'))->default(true),
                    Toggle::make('visible_motor')->label(__('ui.tarifas.visible_motor'))->default(true),
                    Textarea::make('descripcion_publica')->label(__('ui.tarifas.descripcion_publica'))->helperText(__('ui.tarifas.descripcion_publica_ayuda'))->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
            Section::make(__('estancias.tarifa.seccion'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])
                ->description(__('estancias.tarifa.seccion_ayuda').' '.__('estancias.tarifa.temporada_ayuda'))
                ->visible(fn (Get $get): bool => $conPrecios($get) && Modulos::activo(Modulo::EstanciasLargas))
                ->schema(self::preciosLargos('propiedad_id')),
            Section::make(__('ui.tarifas.otras_unidades'))->columnSpanFull()->collapsible()
                ->description(__('ui.tarifas.otras_unidades_ayuda'))
                ->schema([
                    CheckboxList::make('otras_unidades')->label(__('ui.tarifas.otras_unidades'))->hiddenLabel()->bulkToggleable()->columns(['default' => 1, 'md' => 3])
                        ->options(fn (Get $get, ?Tarifa $record): array => self::otrasUnidades($get('propiedad_id'), $record))
                        ->formatStateUsing(fn (?Tarifa $record): array => $record === null ? [] : $record->otrasUnidades()->where(fn (Builder $q): Builder => $q->whereNull('unidades.tarifa_id')->orWhere('unidades.tarifa_id', '!=', $record->id))->pluck('unidades.id')->map(fn (mixed $id): int => (int) $id)->all()),
                ]),
            Section::make(__('ui.tarifas.temporadas'))->columnSpanFull()
                ->description(__('ui.tarifas.temporadas_ayuda'))
                ->visible($conPrecios)
                ->schema([
                    Repeater::make('temporadas')->label(__('ui.tarifas.temporadas'))->hiddenLabel()->relationship()
                        ->defaultItems(0)
                        ->addActionLabel(__('ui.tarifas.anadir_temporada'))
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            TextInput::make('nombre')->label(__('ui.tarifas.temporada'))->required()->maxLength(100),
                            DatePicker::make('desde')->label(__('ui.tarifas.desde'))->required(),
                            DatePicker::make('hasta')->label(__('ui.tarifas.hasta'))->required()->afterOrEqual('desde'),
                            CampoDinero::make('precio_noche', '../../propiedad_id')->label(__('ui.tarifas.precio_noche'))->required(),
                            CampoDinero::make('precio_fin_semana', '../../propiedad_id')->label(__('ui.tarifas.fin_semana'))
                                ->dehydrateStateUsing(fn (mixed $state, Get $get): ?int => blank($state) ? null : \App\Support\Dinero::aUnidadMenor((string) $state, CampoDinero::moneda($get('../../propiedad_id')))),
                            TextInput::make('estancia_minima')->label(__('ui.tarifas.estancia_minima'))->numeric()->minValue(1),
                            Grid::make(['default' => 1, 'md' => 2])->columnSpanFull()
                                ->visible(fn (): bool => Modulos::activo(Modulo::EstanciasLargas))
                                ->schema(self::preciosLargos('../../propiedad_id')),
                            CampoDinero::make('precio_completa', '../../propiedad_id')->label(__('camping.temporada.completa'))->helperText(__('camping.temporada.completa_ayuda'))->columnSpanFull()
                                ->visible(fn (): bool => Modulos::activo(Modulo::Camping))
                                ->dehydrateStateUsing(fn (mixed $state, Get $get): ?int => blank($state) ? null : \App\Support\Dinero::aUnidadMenor((string) $state, CampoDinero::moneda($get('../../propiedad_id')))),
                        ]),
                ]),
        ]);
    }

    /**
     * Una que sale de otra no guarda precios propios, y una con precios propios no lleva ajuste (D-119). La base es de su propiedad y no sale de otra.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizar(array $data, int $propiedadId, ?Tarifa $tarifa = null): array
    {
        $data['politica_id'] = blank($data['politica_id'] ?? null) ? null : Politica::query()->where('propiedad_id', $propiedadId)->whereKey((int) $data['politica_id'])->value('id');
        unset($data['otras_unidades']);
        if (blank($data['tarifa_base_id'] ?? null)) {
            return ['tarifa_base_id' => null, 'ajuste_porcentaje' => null, 'ajuste_noche' => null] + $data;
        }
        $base = Tarifa::query()->where('propiedad_id', $propiedadId)->whereNull('tarifa_base_id')->whereKeyNot($tarifa->id ?? 0)->find((int) $data['tarifa_base_id']);
        abort_if($base === null || ($tarifa !== null && $tarifa->derivadas()->exists()), 403);

        return [
            'tarifa_base_id' => $base->id, 'ajuste_porcentaje' => blank($data['ajuste_porcentaje'] ?? null) ? null : $data['ajuste_porcentaje'],
            'modo' => $base->modo, 'precio_noche' => 0, 'personas_incluidas' => $base->personas_incluidas, 'precio_persona_extra' => 0,
            'precio_fin_semana' => null, 'precio_semana' => null, 'precio_mes' => null,
        ] + $data;
    }

    /**
     * Las que se eligieron en la ficha, solo de su propiedad y sin las que ya la tienen como principal.
     *
     * @param  array<int, mixed>  $unidades
     */
    public static function guardarOtrasUnidades(Tarifa $tarifa, array $unidades): void
    {
        $tarifa->otrasUnidades()->sync(Unidad::query()->where('propiedad_id', $tarifa->propiedad_id)->whereIn('id', array_map('intval', $unidades))
            ->where(fn (Builder $q): Builder => $q->whereNull('tarifa_id')->orWhere('tarifa_id', '!=', $tarifa->id))->pluck('id')->all());
    }

    /**
     * @return array<int, string>
     */
    private static function bases(mixed $propiedadId, ?Tarifa $tarifa): array
    {
        return blank($propiedadId) ? [] : Tarifa::query()->where('propiedad_id', Acceso::propiedadElegida($propiedadId, TarifaResource::propiedadesGestionables()))
            ->whereNull('tarifa_base_id')->whereKeyNot($tarifa->id ?? 0)->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private static function otrasUnidades(mixed $propiedadId, ?Tarifa $tarifa): array
    {
        return blank($propiedadId) ? [] : Unidad::query()->where('propiedad_id', Acceso::propiedadElegida($propiedadId, TarifaResource::propiedadesGestionables()))
            ->where(fn (Builder $q): Builder => $q->whereNull('tarifa_id')->orWhere('tarifa_id', '!=', $tarifa->id ?? 0))->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /**
     * Vacío es «sin este precio», no cero.
     *
     * @return list<TextInput>
     */
    private static function preciosLargos(string $rutaPropiedad): array
    {
        $campo = fn (string $nombre, string $etiqueta): TextInput => CampoDinero::make($nombre, $rutaPropiedad)->label($etiqueta)
            ->dehydrateStateUsing(fn (mixed $state, Get $get): ?int => blank($state) ? null : \App\Support\Dinero::aUnidadMenor((string) $state, CampoDinero::moneda($get($rutaPropiedad))));

        return [$campo('precio_semana', __('estancias.tarifa.semana')), $campo('precio_mes', __('estancias.tarifa.mes'))];
    }

    private static function modo(Get $get): ModoTarifa
    {
        $modo = $get('modo');

        return $modo instanceof ModoTarifa ? $modo : (ModoTarifa::tryFrom((string) $modo) ?? ModoTarifa::PorNoche);
    }
}
