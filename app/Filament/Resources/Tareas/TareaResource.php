<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tareas;

use App\Enums\EstadoTarea;
use App\Enums\TipoTarea;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Tareas\Pages\CreateTarea;
use App\Filament\Resources\Tareas\Pages\EditTarea;
use App\Filament\Resources\Tareas\Pages\ListTareas;
use App\Models\Tarea;
use App\Models\Unidad;
use App\Models\User;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Todas las tareas de limpieza, inspección y mantenimiento, también las de días futuros.
 */
class TareaResource extends RecursoDePropiedad
{
    protected static ?string $model = Tarea::class;

    protected static ?string $slug = 'tareas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 4;

    protected static function permiso(): string
    {
        return Acceso::LIMPIEZA;
    }

    /**
     * Las tareas son datos propios de AlojaFlow: también en las subordinadas.
     */
    public static function propiedadesGestionables(): array
    {
        return Acceso::propiedades(Acceso::usuarioActual(), self::permiso());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getModelLabel(): string
    {
        return __('limpieza.tareas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('limpieza.tareas.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('unidad_id')->label(__('limpieza.tareas.unidad'))->native(false)->searchable()
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', (int) $get('propiedad_id'))->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Select::make('tipo')->label(__('limpieza.tareas.tipo'))->options(TipoTarea::opciones())->required()->native(false)->default(TipoTarea::Mantenimiento->value),
                TextInput::make('titulo')->label(__('limpieza.tareas.titulo'))->required()->maxLength(150),
                DatePicker::make('fecha')->label(__('limpieza.tareas.fecha'))->required()->default(now()),
                Select::make('asignada_a')->label(__('limpieza.tareas.asignada'))->native(false)->placeholder(__('limpieza.tareas.sin_asignar'))
                    ->options(fn (Get $get): array => self::personal((int) $get('propiedad_id'))),
                Select::make('estado')->label(__('limpieza.tareas.estado'))->options(EstadoTarea::opciones())->required()->native(false)->default(EstadoTarea::Pendiente->value),
                Toggle::make('prioridad')->label(__('limpieza.tareas.urgente'))
                    ->formatStateUsing(fn (mixed $state): bool => (int) $state > 0)
                    ->dehydrateStateUsing(fn (mixed $state): int => $state ? 1 : 0),
                Textarea::make('descripcion')->label(__('limpieza.tareas.descripcion'))->rows(3)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['unidad', 'propiedad', 'asignada']))
            ->defaultSort('fecha')
            ->columns([
                TextColumn::make('fecha')->label(__('limpieza.tareas.fecha'))->date()->sortable(),
                TextColumn::make('titulo')->label(__('limpieza.tareas.titulo'))->searchable()->wrap(),
                TextColumn::make('tipo')->label(__('limpieza.tareas.tipo'))->formatStateUsing(fn (TipoTarea $state): string => $state->etiqueta())->toggleable(),
                self::columnaPropiedad(),
                TextColumn::make('asignada.name')->label(__('limpieza.tareas.asignada'))->placeholder(__('limpieza.tareas.sin_asignar'))->toggleable(),
                TextColumn::make('estado')->label(__('limpieza.tareas.estado'))->badge()->formatStateUsing(fn (EstadoTarea $state): string => $state->etiqueta())
                    ->color(fn (EstadoTarea $state): string => match ($state) {
                        EstadoTarea::Pendiente => 'warning',
                        EstadoTarea::EnCurso => 'info',
                        EstadoTarea::Hecha => 'success',
                        EstadoTarea::Cancelada => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('abiertas')->label(__('limpieza.tareas.estado'))
                    ->options(['abiertas' => __('limpieza.tareas.abiertas')] + EstadoTarea::opciones())
                    ->default('abiertas')
                    ->query(function (Builder $query, array $data): Builder {
                        $valor = $data['value'] ?? null;

                        return match (true) {
                            $valor === 'abiertas' => $query->whereIn('estado', [EstadoTarea::Pendiente->value, EstadoTarea::EnCurso->value]),
                            filled($valor) => $query->where('estado', $valor),
                            default => $query,
                        };
                    }),
                SelectFilter::make('tipo')->label(__('limpieza.tareas.tipo'))->options(TipoTarea::opciones()),
            ])
            ->emptyStateHeading(__('limpieza.tareas.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Tarea $record): bool => self::canEdit($record))]);
    }

    /**
     * Quienes trabajan en esa propiedad, para asignarles la tarea.
     *
     * @return array<int, string>
     */
    private static function personal(int $propiedadId): array
    {
        return User::query()->where('activo', true)
            ->whereIn('id', fn ($q) => $q->select('model_id')->from('model_has_roles')->where('propiedad_id', $propiedadId))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTareas::route('/'),
            'create' => CreateTarea::route('/create'),
            'edit' => EditTarea::route('/{record}/edit'),
        ];
    }
}
