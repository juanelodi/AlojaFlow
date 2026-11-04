<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Webhooks;

use App\Demo\ModoDemo;
use App\Enums\DisparadorAutomatizacion;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Webhooks\Pages\CreateWebhook;
use App\Filament\Resources\Webhooks\Pages\EditWebhook;
use App\Filament\Resources\Webhooks\Pages\ListWebhooks;
use App\Models\Webhook;
use App\Seguridad\Acceso;
use App\Seguridad\ClienteExterno;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisos firmados a otros sistemas cuando cambia una reserva.
 */
class WebhookResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Webhook::class;

    protected static ?string $slug = 'avisos-a-otros-sistemas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTopRightOnSquare;

    protected static ?int $navigationSort = 4;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function canViewAny(): bool
    {
        return parent::canViewAny() && ! ModoDemo::activo();
    }

    public static function getModelLabel(): string
    {
        return __('mensajes.webhooks.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mensajes.webhooks.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $eventos = DisparadorAutomatizacion::opciones();
        foreach (DisparadorAutomatizacion::cases() as $caso) {
            if ($caso->esDeTiempo()) {
                unset($eventos[$caso->value]);
            }
        }

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('url')->label(__('mensajes.webhooks.url'))->helperText(__('mensajes.webhooks.url_ayuda'))->url()->required()->minLength(16)->maxLength(500)
                    ->rule(fn (): Closure => function (string $atributo, mixed $valor, Closure $falla): void {
                        try {
                            app(ClienteExterno::class)->comprobar((string) $valor);
                        } catch (Throwable) {
                            $falla(__('mensajes.webhooks.url_invalida'));
                        }
                    }),
                TextInput::make('secreto')->label(__('mensajes.webhooks.secreto'))->helperText(__('mensajes.webhooks.secreto_ayuda'))->required()->minLength(16)->maxLength(200)
                    ->default(fn (): string => Str::random(40))->autocomplete('off'),
                Toggle::make('activo')->label(__('mensajes.webhooks.activo'))->default(true),
                CheckboxList::make('eventos')->label(__('mensajes.webhooks.eventos'))->options($eventos)->required()->columnSpanFull()->columns(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->columns([
                TextColumn::make('url')->label(__('mensajes.webhooks.url'))->limit(60),
                self::columnaPropiedad(),
                IconColumn::make('activo')->label(__('mensajes.webhooks.activo'))->boolean(),
            ])
            ->emptyStateHeading(__('mensajes.webhooks.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Webhook $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhooks::route('/'),
            'create' => CreateWebhook::route('/create'),
            'edit' => EditWebhook::route('/{record}/edit'),
        ];
    }
}
