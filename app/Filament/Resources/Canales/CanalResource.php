<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Canales;

use App\Enums\BaseCargoCanal;
use App\Enums\CalculoCargoCanal;
use App\Enums\ClaseCargoCanal;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Canales\Pages\CreateCanal;
use App\Filament\Resources\Canales\Pages\EditCanal;
use App\Filament\Resources\Canales\Pages\ListCanales;
use App\Models\Canal;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Paises\Paises;
use App\Reservas\CargosSugeridos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * De dónde vienen las reservas, con su comisión, sus cargos y retenciones (D-149): dan la utilidad de cada reserva. Datos propios: también en subordinadas.
 */
class CanalResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Canal::class;

    protected static ?string $slug = 'canales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('ui.canales.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.canales.modelos');
    }

    protected static function referencias(): array
    {
        return ['politica_id' => Politica::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.canales.nombre'))->helperText(__('ui.canales.nombre_ayuda'))->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                TextInput::make('comision_porcentaje')->label(__('ui.canales.comision'))->helperText(__('ui.canales.comision_ayuda'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%'),
                Select::make('comision_base')->label(__('canales.comision_base'))->helperText(__('canales.comision_base_ayuda'))->native(false)->required()->default(BaseCargoCanal::TotalFinal->value)
                    ->options(fn (): array => array_diff_key(BaseCargoCanal::opciones(), [BaseCargoCanal::Comisiones->value => true])),
                Select::make('politica_id')->label(__('dinero.politicas.modelo'))->native(false)
                    ->options(fn (Get $get): array => Politica::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Toggle::make('cobra_impuestos')->label(__('caja.impuestos.canal_cobra'))->helperText(__('caja.impuestos.canal_cobra_ayuda')),
                Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
            ]),
            Section::make(__('canales.cargos'))->columnSpanFull()->description(__('canales.cargos_ayuda'))
                ->headerActions([
                    Action::make('sugerir')->label(__('canales.sugerir'))->icon(Heroicon::OutlinedSparkles)->color('gray')
                        ->modalDescription(__('canales.sugerir_ayuda'))
                        ->schema([Select::make('plataforma')->label(__('canales.plataforma'))->required()->native(false)->options(CargosSugeridos::plataformas())])
                        ->action(function (array $data, Get $get, Set $set): void {
                            $propiedad = Propiedad::query()->find(self::propiedadDe($get));
                            if ($propiedad === null) {
                                Notification::make()->warning()->title(__('canales.elige_propiedad'))->send();

                                return;
                            }
                            $sugeridos = CargosSugeridos::de((string) $data['plataforma'], Paises::de($propiedad));
                            if ($sugeridos['comision'] !== null && blank($get('comision_porcentaje'))) {
                                $set('comision_porcentaje', $sugeridos['comision']);
                            }
                            $set('comision_base', $sugeridos['comision_base']);
                            $actuales = (array) $get('cargos');
                            $nombres = array_column($actuales, 'nombre');
                            foreach ($sugeridos['cargos'] as $cargo) {
                                if (! in_array($cargo['nombre'], $nombres, true)) {
                                    $actuales['record-'.Str::uuid()->toString()] = $cargo;
                                }
                            }
                            $set('cargos', $actuales);
                        }),
                ])
                ->schema([
                    Repeater::make('cargos')->label(__('canales.cargos'))->hiddenLabel()->relationship()->defaultItems(0)
                        ->addActionLabel(__('canales.anadir'))->columns(['default' => 1, 'md' => 3])
                        ->itemLabel(fn (array $state): ?string => filled($state['nombre'] ?? null) ? (string) $state['nombre'] : null)->collapsible()
                        ->schema([
                            TextInput::make('nombre')->label(__('canales.nombre'))->required()->maxLength(100),
                            Select::make('clase')->label(__('canales.clase'))->required()->native(false)->options(ClaseCargoCanal::opciones())->default(ClaseCargoCanal::Servicio->value),
                            Select::make('calculo')->label(__('canales.calculo'))->required()->native(false)->live()->options(CalculoCargoCanal::opciones())->default(CalculoCargoCanal::Porcentaje->value),
                            TextInput::make('valor')->label(__('canales.valor'))->required()->numeric()->minValue(0)->step('any')->default(0),
                            Select::make('base')->label(__('canales.base'))->helperText(__('canales.base_ayuda'))->required()->native(false)->options(BaseCargoCanal::opciones())->default(BaseCargoCanal::TotalFinal->value)
                                ->visible(fn (Get $get): bool => ($get('calculo') instanceof CalculoCargoCanal ? $get('calculo')->value : $get('calculo')) === CalculoCargoCanal::Porcentaje->value),
                            Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('ui.canales.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('comision_porcentaje')->label(__('ui.canales.comision'))->suffix(' %')->placeholder('—')->alignEnd(),
                IconColumn::make('activo')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('ui.canales.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Canal $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCanales::route('/'),
            'create' => CreateCanal::route('/create'),
            'edit' => EditCanal::route('/{record}/edit'),
        ];
    }
}
