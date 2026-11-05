<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\RelationManagers;

use App\Enums\EstadoGrupo;
use App\Enums\EstadoReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Grupo;
use App\Models\GrupoHabitacion;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cada habitación es una reserva normal; aquí se cambia lo que le toca como parte del grupo. Si algo ya no está libre, se queda como estaba.
 */
class HabitacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'reservas';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('grupos.habitaciones.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['unidad', 'habitacionGrupo']))
            ->defaultSort('llegada')
            ->paginated(false)
            ->columns([
                TextColumn::make('numero')->label(__('ui.reservas.numero'))->url(fn (Reserva $record): string => ReservaResource::getUrl('view', ['record' => $record])),
                TextColumn::make('unidad.nombre')->label(__('ui.comun.unidad')),
                TextColumn::make('llegada')->label(__('ui.comun.llegada'))->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->llegada)),
                TextColumn::make('salida')->label(__('ui.comun.salida'))->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->salida)),
                TextColumn::make('habitacionGrupo.responsable')->label(__('grupos.lista.responsable'))->placeholder('—')->wrap(),
                TextColumn::make('personas')->label(__('ui.comun.personas'))->alignEnd(),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()->formatStateUsing(fn (Reserva $record): string => $record->estado->etiqueta())
                    ->color(fn (Reserva $record): string => $record->estado === EstadoReserva::Cancelada ? 'gray' : ($record->estado === EstadoReserva::Confirmada ? 'success' : 'warning')),
                TextColumn::make('total')->label(__('ui.comun.total'))->alignEnd()->formatStateUsing(fn (Reserva $record): string => Formato::dinero($record->total, $record->moneda)),
                TextColumn::make('pendiente')->label(__('ui.comun.pendiente'))->alignEnd()->formatStateUsing(fn (Reserva $record): string => Formato::dinero($record->pendiente, $record->moneda)),
            ])
            ->emptyStateHeading(__('grupos.habitaciones.vacio'))
            ->headerActions([$this->accionAgregar()])
            ->recordActions([
                ActionGroup::make([$this->accionDatos(), $this->accionCambiar(), $this->accionPrecio(), $this->accionCancelar(), $this->accionReactivar()]),
            ]);
    }

    private function grupo(): Grupo
    {
        /** @var Grupo $grupo */
        $grupo = $this->getOwnerRecord();

        return $grupo;
    }

    private function accionAgregar(): Action
    {
        return Action::make('agregarHabitacion')
            ->label(__('grupos.habitaciones.agregar'))
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => $this->grupo()->estado !== EstadoGrupo::Cancelado)
            ->fillForm(fn (): array => [
                'llegada' => $this->grupo()->primeraLlegada()?->format('Y-m-d'),
                'salida' => $this->grupo()->ultimaSalida()?->format('Y-m-d'),
                'personas' => 2,
            ])
            ->schema([
                Select::make('unidad_id')->label(__('ui.comun.unidad'))->required()->native(false)->searchable()->options(fn (): array => $this->unidades()),
                DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required(),
                DatePicker::make('salida')->label(__('ui.comun.salida'))->required()->after('llegada'),
                TextInput::make('personas')->label(__('ui.comun.personas'))->numeric()->integer()->minValue(1)->required(),
            ])
            ->action(fn (array $data) => $this->intentar(fn () => app(GestorGrupos::class)->agregarHabitacion(
                $this->grupo(),
                (int) $data['unidad_id'],
                CarbonImmutable::parse((string) $data['llegada'])->startOfDay(),
                CarbonImmutable::parse((string) $data['salida'])->startOfDay(),
                (int) $data['personas'],
            )));
    }

    private function accionDatos(): Action
    {
        return Action::make('datosHabitacion')
            ->label(__('grupos.habitaciones.datos'))
            ->icon(Heroicon::OutlinedUser)
            ->visible(fn (Reserva $record): bool => $record->estado !== EstadoReserva::Cancelada)
            ->fillForm(fn (Reserva $record): array => [
                'responsable' => $record->habitacionGrupo?->responsable,
                'telefono' => $record->habitacionGrupo?->telefono,
                'correo' => $record->habitacionGrupo?->correo,
                'acompanantes' => $record->habitacionGrupo?->acompanantes,
                'personas' => $record->personas,
            ])
            ->schema([
                TextInput::make('responsable')->label(__('grupos.lista.responsable'))->maxLength(150),
                TextInput::make('telefono')->label(__('grupos.lista.telefono'))->maxLength(50),
                TextInput::make('correo')->label(__('grupos.lista.correo'))->email()->maxLength(150),
                TextInput::make('personas')->label(__('grupos.lista.personas'))->numeric()->integer()->minValue(1)->required(),
                Textarea::make('acompanantes')->label(__('grupos.lista.acompanantes'))->rows(3)->maxLength(2000),
            ])
            ->action(fn (Reserva $record, array $data) => $this->intentar(fn () => app(GestorGrupos::class)->guardarHabitacion($record, $data)));
    }

    private function accionCambiar(): Action
    {
        return Action::make('cambiarHabitacion')
            ->label(__('grupos.habitaciones.cambiar'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalDescription(__('grupos.habitaciones.cambiar_ayuda'))
            ->visible(fn (Reserva $record): bool => $record->estado !== EstadoReserva::Cancelada)
            ->fillForm(fn (Reserva $record): array => ['unidad_id' => $record->unidad_id, 'llegada' => $record->llegada->format('Y-m-d'), 'salida' => $record->salida->format('Y-m-d')])
            ->schema([
                Select::make('unidad_id')->label(__('ui.comun.unidad'))->required()->native(false)->searchable()->options(fn (): array => $this->unidades()),
                DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required(),
                DatePicker::make('salida')->label(__('ui.comun.salida'))->required()->after('llegada'),
            ])
            ->action(fn (Reserva $record, array $data) => $this->intentar(fn () => app(GestorGrupos::class)->cambiarHabitacion(
                $record,
                (int) $data['unidad_id'],
                CarbonImmutable::parse((string) $data['llegada'])->startOfDay(),
                CarbonImmutable::parse((string) $data['salida'])->startOfDay(),
            )));
    }

    private function accionPrecio(): Action
    {
        return Action::make('precioHabitacion')
            ->label(__('grupos.habitaciones.precio'))
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->modalDescription(__('grupos.habitaciones.precio_ayuda'))
            ->visible(fn (Reserva $record): bool => $record->estado !== EstadoReserva::Cancelada)
            ->fillForm(fn (Reserva $record): array => ['precio_noche' => $record->habitacionGrupo?->precio_noche === null ? null : Dinero::aDecimal($record->habitacionGrupo->precio_noche, $record->moneda)])
            ->schema([TextInput::make('precio_noche')->label(__('grupos.precio.precio_noche'))->numeric()->minValue(0)->step('any')])
            ->action(function (Reserva $record, array $data): void {
                $this->intentar(function () use ($record, $data): void {
                    GrupoHabitacion::query()->updateOrCreate(['reserva_id' => $record->id], [
                        'grupo_id' => $this->grupo()->id,
                        'precio_noche' => blank($data['precio_noche'] ?? null) ? null : Dinero::aUnidadMenor((string) $data['precio_noche'], $record->moneda),
                    ]);
                    app(GestorGrupos::class)->aplicarPrecios($this->grupo(), forzar: true);
                });
            });
    }

    private function accionCancelar(): Action
    {
        return Action::make('cancelarHabitacion')
            ->label(__('grupos.habitaciones.cancelar'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => $record->estado !== EstadoReserva::Cancelada)
            ->action(fn (Reserva $record) => $this->intentar(fn () => app(GestorGrupos::class)->cancelarHabitacion($record)));
    }

    private function accionReactivar(): Action
    {
        return Action::make('reactivarHabitacion')
            ->label(__('grupos.habitaciones.reactivar'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => $record->estado === EstadoReserva::Cancelada && $this->grupo()->estado !== EstadoGrupo::Cancelado)
            ->action(fn (Reserva $record) => $this->intentar(fn () => app(GestorGrupos::class)->reactivarHabitacion($record)));
    }

    /**
     * @return array<int, string>
     */
    private function unidades(): array
    {
        return Unidad::query()->where('propiedad_id', $this->grupo()->propiedad_id)->where('activa', true)->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    private function intentar(Closure $accion): void
    {
        try {
            $accion();
            Notification::make()->success()->title(__('grupos.habitaciones.guardado'))->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->persistent()->title($e->getMessage())->send();
        }
    }
}
