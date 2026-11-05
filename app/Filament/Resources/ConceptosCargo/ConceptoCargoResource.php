<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\ConceptosCargo;

use App\Enums\BaseCargo;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\ConceptosCargo\Pages\CreateConceptoCargo;
use App\Filament\Resources\ConceptosCargo\Pages\EditConceptoCargo;
use App\Filament\Resources\ConceptosCargo\Pages\ListConceptosCargo;
use App\Models\ConceptoCargo;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\ReglasCargo;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cargos adicionales: cama extra, limpieza, desayuno, mascota…
 */
class ConceptoCargoResource extends RecursoDePropiedad
{
    protected static ?string $model = ConceptoCargo::class;

    protected static ?string $slug = 'cargos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('ui.cargos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.cargos.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $esPorcentaje = fn (Get $get): bool => self::base($get) === BaseCargo::PorcentajeAlojamiento;
        $porNoche = fn (Get $get): bool => self::base($get)?->esPorNoche() === true;
        $lista = fn (mixed $state): ?array => $state === null || $state === [] ? null : array_values(array_map('intval', (array) $state));

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.cargos.nombre'))->helperText(__('ui.cargos.nombre_ayuda'))->required()->maxLength(100),
                Select::make('base')->label(__('ui.cargos.base'))->options(BaseCargo::opciones())->required()->native(false)->live()
                    ->default(BaseCargo::PorEstancia->value),
                CampoDinero::make('importe')->label(__('ui.cargos.importe'))->default(0)
                    ->required(fn (Get $get): bool => ! $esPorcentaje($get))->visible(fn (Get $get): bool => ! $esPorcentaje($get)),
                TextInput::make('porcentaje')->label(__('ui.cargos.porcentaje'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')
                    ->required($esPorcentaje)->visible($esPorcentaje),
                TextInput::make('cupo_diario')->label(__('camping.extras.cupo'))->helperText(__('camping.extras.cupo_ayuda'))->numeric()->integer()->minValue(0)->maxValue(100000)
                    ->visible(fn (): bool => Modulos::activo(Modulo::Camping)),
                Toggle::make('para_administrador')->label(__('propietarios.cargo.para_administrador'))->helperText(__('propietarios.cargo.para_administrador_ayuda'))
                    ->visible(fn (): bool => Modulos::activo(Modulo::Propietarios)),
                Toggle::make('obligatorio')->label(__('ui.cargos.obligatorio'))->helperText(__('ui.cargos.obligatorio_ayuda')),
                Toggle::make('visible_motor')->label(__('ui.cargos.visible_motor'))->helperText(__('ui.cargos.visible_motor_ayuda'))->default(true),
                TextInput::make('orden')->label(__('ui.unidades.orden'))->numeric()->default(0),
                Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
            ]),
            Section::make(__('ui.cargos.reglas'))->description(__('ui.cargos.reglas_ayuda'))->columnSpanFull()->collapsible()->columns(['default' => 1, 'md' => 2])
                ->collapsed(fn (?ConceptoCargo $record): bool => $record === null || ! ReglasCargo::tieneReglas($record))->schema([
                CheckboxList::make('dias_semana')->label(__('ui.cargos.dias_semana'))->helperText(__('ui.cargos.dias_semana_ayuda'))->columnSpanFull()->columns(['default' => 2, 'md' => 4])
                    ->options(fn (): array => Formato::diasDeLaSemana())->visible($porNoche)->dehydrateStateUsing($lista),
                TextInput::make('cada_noches')->label(__('ui.cargos.cada_noches'))->helperText(__('ui.cargos.cada_noches_ayuda'))->numeric()->integer()->minValue(1)->maxValue(366)->visible($porNoche),
                TextInput::make('noches_minimas')->label(__('ui.cargos.noches_minimas'))->numeric()->integer()->minValue(1)->maxValue(366),
                DatePicker::make('desde')->label(__('ui.cargos.desde'))->helperText(__('ui.cargos.fechas_ayuda')),
                DatePicker::make('hasta')->label(__('ui.cargos.hasta'))->minDate(fn (Get $get): mixed => $get('desde')),
                Select::make('solo_tarifas')->label(__('ui.cargos.solo_tarifas'))->multiple()->native(false)->dehydrateStateUsing($lista)
                    ->options(fn (Get $get): array => Tarifa::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Select::make('solo_unidades')->label(__('ui.cargos.solo_unidades'))->multiple()->native(false)->dehydrateStateUsing($lista)
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all()),
            ]),
        ]);
    }

    /**
     * Las tarifas y unidades de sus reglas también son de esa propiedad.
     *
     * @param  array<string, mixed>  $data
     */
    public static function comprobarReferencias(array $data, int $propiedadId): void
    {
        parent::comprobarReferencias($data, $propiedadId);
        foreach (['solo_tarifas' => Tarifa::class, 'solo_unidades' => Unidad::class] as $campo => $modelo) {
            $ids = (array) ($data[$campo] ?? []);
            abort_if($modelo::query()->where('propiedad_id', $propiedadId)->whereIn('id', $ids)->count() !== count($ids), 403);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label(__('ui.cargos.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('base')->label(__('ui.cargos.base'))->formatStateUsing(fn (ConceptoCargo $record): string => $record->base->etiqueta()),
                TextColumn::make('importe')->label(__('ui.cargos.importe'))->alignEnd()
                    ->formatStateUsing(fn (ConceptoCargo $record): string => $record->base === BaseCargo::PorcentajeAlojamiento
                        ? Formato::tasa($record->porcentaje)
                        : Formato::dinero($record->importe, $record->propiedad->moneda)),
                TextColumn::make('reglas')->label(__('ui.cargos.reglas'))->placeholder('—')->toggleable()
                    ->state(fn (ConceptoCargo $record): string => ReglasCargo::resumen($record)),
                IconColumn::make('obligatorio')->label(__('ui.cargos.obligatorio'))->boolean(),
                IconColumn::make('activo')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('ui.cargos.vacio'))
            ->recordActions([EditAction::make()->visible(fn (ConceptoCargo $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConceptosCargo::route('/'),
            'create' => CreateConceptoCargo::route('/create'),
            'edit' => EditConceptoCargo::route('/{record}/edit'),
        ];
    }

    private static function base(Get $get): ?BaseCargo
    {
        $base = $get('base');

        return $base instanceof BaseCargo ? $base : BaseCargo::tryFrom((string) $base);
    }
}
