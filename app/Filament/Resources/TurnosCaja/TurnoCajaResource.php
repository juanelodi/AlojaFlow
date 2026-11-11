<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\TurnosCaja;

use App\Caja\GestorTurnos;
use App\Caja\TurnoRechazado;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\TurnosCaja\Pages\ListTurnosCaja;
use App\Models\TurnoCaja;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Dinero;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Los turnos de caja, con su arqueo (D-080). Se abren desde la lista y nadie los edita: un acta que se puede cambiar no sirve de acta.
 */
class TurnoCajaResource extends RecursoDePropiedad
{
    protected static ?string $model = TurnoCaja::class;

    protected static ?string $slug = 'turnos-de-caja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 3;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Caja;
    }

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('caja.turnos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('caja.turnos.modelos');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'caja', 'abridor', 'cerrador']))
            ->defaultSort('abierto_en', 'desc')
            ->columns([
                TextColumn::make('caja.nombre')->label(__('ui.cajas.modelo_campo'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('abierto_en')->label(__('caja.turnos.abierto'))->sortable()
                    ->formatStateUsing(fn (TurnoCaja $record): string => Formato::momento($record->abierto_en, $record->propiedad->zona_horaria)),
                TextColumn::make('abridor.name')->label(__('caja.turnos.abrio'))->placeholder('—'),
                TextColumn::make('fondo_inicial')->label(__('caja.turnos.fondo'))->alignEnd()
                    ->formatStateUsing(fn (TurnoCaja $record): string => Formato::dinero($record->fondo_inicial, $record->moneda)),
                TextColumn::make('cerrado_en')->label(__('caja.turnos.cerrado'))->placeholder(__('caja.turnos.sigue_abierto'))
                    ->formatStateUsing(fn (TurnoCaja $record): string => Formato::momento($record->cerrado_en, $record->propiedad->zona_horaria)),
                TextColumn::make('diferencia')->label(__('caja.turnos.diferencia'))->alignEnd()->placeholder('—')->badge()
                    ->formatStateUsing(fn (TurnoCaja $record): string => Formato::dinero((int) $record->diferencia, $record->moneda))
                    ->color(fn (TurnoCaja $record): string => (int) $record->diferencia === 0 ? 'success' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('abiertos')->label(__('caja.turnos.abiertos'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('cerrado_en'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('cerrado_en'),
                    ),
            ])
            ->emptyStateHeading(__('caja.turnos.vacio'))
            ->emptyStateDescription(__('caja.turnos.vacio_ayuda'))
            ->recordActions([self::accionCerrar(), self::accionActa()]);
    }

    private static function accionCerrar(): Action
    {
        return Action::make('cerrar')
            ->label(__('caja.turnos.cerrar'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->visible(fn (TurnoCaja $record): bool => $record->estaAbierto() && in_array($record->propiedad_id, self::propiedadesGestionables(), true))
            ->modalDescription(__('caja.turnos.cerrar_ayuda'))
            ->schema([
                TextInput::make('contado')->label(__('caja.turnos.contado'))->required()->numeric()->minValue(0)->step('any')
                    ->prefix(fn (TurnoCaja $record): string => $record->moneda),
                Textarea::make('notas')->label(__('caja.turnos.notas'))->maxLength(1000),
            ])
            ->action(function (TurnoCaja $record, array $data): void {
                try {
                    $turno = app(GestorTurnos::class)->cerrar($record, Dinero::aUnidadMenor((string) $data['contado'], $record->moneda), $data['notas'] ?? null);
                } catch (TurnoRechazado $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }
                $diferencia = (int) $turno->diferencia;
                Notification::make()->title($diferencia === 0 ? __('caja.turnos.cuadra') : __($diferencia < 0 ? 'caja.turnos.faltante' : 'caja.turnos.sobrante', ['importe' => Formato::dinero(abs($diferencia), $turno->moneda)]))
                    ->status($diferencia === 0 ? 'success' : 'warning')->send();
            });
    }

    private static function accionActa(): Action
    {
        return Action::make('acta')
            ->label(__('caja.turnos.acta'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(fn (TurnoCaja $record): bool => ! $record->estaAbierto())
            ->modalHeading(fn (TurnoCaja $record): string => __('caja.turnos.acta_de', ['caja' => $record->caja->nombre]))
            ->modalSubmitAction(false)
            ->modalContent(fn (TurnoCaja $record) => view('filament.partes.acta-turno', ['turno' => $record]));
    }

    public static function getPages(): array
    {
        return ['index' => ListTurnosCaja::route('/')];
    }
}
