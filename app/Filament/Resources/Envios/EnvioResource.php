<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Envios;

use App\Comunicacion\VariablesMensaje;
use App\Enums\CanalEnvio;
use App\Enums\EstadoEnvio;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Envios\Pages\ListEnvios;
use App\Models\Envio;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial y cola de mensajes. Sin correo configurado, aquí queda el texto listo para copiarlo.
 */
class EnvioResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Envio::class;

    protected static ?string $slug = 'mensajes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 1;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Mensajes;
    }

    protected static function permiso(): string
    {
        return Acceso::MENSAJES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getModelLabel(): string
    {
        return __('mensajes.envios.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mensajes.envios.modelos');
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

    public static function getNavigationBadge(): ?string
    {
        $manuales = Envio::query()->whereIn('propiedad_id', self::propiedadesGestionables())->where('estado', EstadoEnvio::Manual->value)->count();

        return $manuales > 0 ? (string) $manuales : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'reserva']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('creado_en')->label(__('mensajes.envios.fecha'))->sortable()->formatStateUsing(fn (Envio $record): string => Formato::momento($record->creado_en, $record->propiedad->zona_horaria)),
                TextColumn::make('destino')->label(__('mensajes.envios.destino'))->limit(40)->searchable(),
                TextColumn::make('asunto')->label(__('mensajes.envios.asunto'))->limit(50)->searchable()->toggleable(),
                TextColumn::make('reserva.numero')->label(__('mensajes.envios.reserva'))->toggleable(),
                TextColumn::make('canal')->label(__('mensajes.envios.canal'))->formatStateUsing(fn (CanalEnvio $state): string => $state->etiqueta())->toggleable(),
                self::columnaPropiedad(),
                TextColumn::make('estado')->label(__('mensajes.envios.estado'))->badge()->formatStateUsing(fn (EstadoEnvio $state): string => $state->etiqueta())
                    ->color(fn (EstadoEnvio $state): string => match ($state) {
                        EstadoEnvio::Pendiente => 'info',
                        EstadoEnvio::Enviado => 'success',
                        EstadoEnvio::Fallido => 'danger',
                        EstadoEnvio::Omitido => 'gray',
                        EstadoEnvio::Manual => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('estado')->label(__('mensajes.envios.estado'))->options(EstadoEnvio::opciones()),
                // WhatsApp por la API de Meta solo queda en el historial (D-171): se ofrece donde lo hubo.
                SelectFilter::make('canal')->label(__('mensajes.envios.canal'))->options(fn (): array => Envio::query()->where('canal', CanalEnvio::WhatsappApi)->exists()
                    ? CanalEnvio::opciones() : array_diff_key(CanalEnvio::opciones(), [CanalEnvio::WhatsappApi->value => true])),
            ])
            ->emptyStateHeading(__('mensajes.envios.vacio'))
            ->recordActions([
                Action::make('ver')
                    ->label(__('mensajes.envios.ver'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (Envio $record): string => (string) ($record->asunto ?: $record->destino))
                    ->modalContent(fn (Envio $record) => view('filament.modales.envio', ['envio' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('ui.comun.cerrar')),
                Action::make('marcarEnviado')
                    ->label(__('mensajes.envios.marcar_enviado'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Envio $record): bool => $record->estado === EstadoEnvio::Manual)
                    ->action(fn (Envio $record) => $record->update(['estado' => EstadoEnvio::Enviado, 'enviado_en' => now('UTC')])),
                Action::make('reintentar')
                    ->label(__('mensajes.envios.reintentar'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (Envio $record): bool => in_array($record->estado, [EstadoEnvio::Fallido, EstadoEnvio::Manual], true) && $record->cuerpo !== null)
                    ->action(function (Envio $record): void {
                        $record->update(['estado' => EstadoEnvio::Pendiente, 'intentos' => 0, 'programado_para' => null]);
                        Notification::make()->success()->title(__('mensajes.envios.reintentado'))->send();
                    }),
            ]);
    }

    public static function enlaceWhatsapp(Envio $envio): string
    {
        return VariablesMensaje::enlaceWhatsapp($envio->canal === CanalEnvio::WhatsappEnlace ? $envio->destino : null, (string) $envio->cuerpo, $envio->propiedad->pais);
    }

    public static function getPages(): array
    {
        return ['index' => ListEnvios::route('/')];
    }
}
