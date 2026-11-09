<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\WhatsappCloud;
use App\Demo\ModoDemo;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use App\Support\Contactos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * WhatsApp por la API de Meta, propiedad por propiedad (D-160). El token se guarda cifrado y no vuelve a mostrarse.
 *
 * @property-read Schema $form
 */
class WhatsApp extends Page
{
    protected string $view = 'filament.pages.correo';

    protected static ?string $slug = 'whatsapp';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 6;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public static function canAccess(): bool
    {
        return ! ModoDemo::activo() && Modulos::activo(Modulo::Mensajes) && self::propiedades() !== [];
    }

    /**
     * @return array<int, string>
     */
    private static function propiedades(): array
    {
        return Propiedad::query()->whereIn('id', Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES))->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('mensajes.whatsapp.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mensajes.whatsapp.titulo');
    }

    public function mount(): void
    {
        $this->cargar((int) array_key_first(self::propiedades()));
    }

    private function cargar(int $propiedadId): void
    {
        $whatsapp = new WhatsappCloud($propiedadId);
        $this->form->fill(['propiedad_id' => $propiedadId, 'numero_id' => $whatsapp->numeroId(), 'version' => $whatsapp->version()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('datos')->components([
            Section::make()->description(__('mensajes.whatsapp.ayuda'))->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->native(false)->required()->live()
                    ->options(fn (): array => self::propiedades())
                    ->afterStateUpdated(fn (?int $state) => $this->cargar((int) $state)),
                TextInput::make('numero_id')->label(__('mensajes.whatsapp.numero_id'))->helperText(__('mensajes.whatsapp.numero_id_ayuda'))->regex('/^[0-9]{5,30}$/')->maxLength(30)->required(),
                TextInput::make('token')->label(__('mensajes.whatsapp.token'))->password()->revealable()->autocomplete('new-password')->maxLength(1000)
                    ->helperText(fn (Get $get): string => (new WhatsappCloud((int) $get('propiedad_id')))->configurado() ? __('mensajes.whatsapp.token_guardado') : __('mensajes.whatsapp.token_ayuda')),
                TextInput::make('version')->label(__('mensajes.whatsapp.version'))->helperText(__('mensajes.whatsapp.version_ayuda'))->regex('/^v[0-9]{2}\.[0-9]$/')->maxLength(6),
            ]),
        ]);
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();
        $propiedadId = (int) $datos['propiedad_id'];
        abort_unless(array_key_exists($propiedadId, self::propiedades()), 403);
        (new WhatsappCloud($propiedadId))->guardar($datos['numero_id'] ?? null, $datos['token'] ?? null, $datos['version'] ?? null);
        $this->datos['token'] = null;
        // Nunca el token en la auditoría.
        RegistroAuditoria::anotar('whatsapp.ajustes', null, ['numero_id' => $datos['numero_id'] ?? null], $propiedadId);
        Notification::make()->success()->title(__('mensajes.whatsapp.guardado'))->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // hello_world es la plantilla de ejemplo que Meta pone en toda cuenta nueva, en inglés.
            Action::make('probar')
                ->label(__('mensajes.whatsapp.probar'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->visible(fn (): bool => (new WhatsappCloud((int) ($this->datos['propiedad_id'] ?? 0)))->configurado())
                ->modalDescription(__('mensajes.whatsapp.probar_ayuda'))
                ->schema([TextInput::make('telefono')->label(__('mensajes.whatsapp.telefono'))->tel()->required()->maxLength(30)])
                ->action(function (array $data): void {
                    $propiedad = Propiedad::query()->whereKey(array_keys(self::propiedades()))->findOrFail((int) ($this->datos['propiedad_id'] ?? 0));
                    $telefono = Contactos::internacional((string) $data['telefono'], $propiedad->pais) ?? (string) $data['telefono'];
                    try {
                        (new WhatsappCloud($propiedad->id))->enviarPlantilla($telefono, 'hello_world', 'en_US', []);
                        Notification::make()->success()->title(__('mensajes.whatsapp.prueba_ok'))->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->persistent()->title(__('mensajes.whatsapp.prueba_fallo'))->body(mb_substr($e->getMessage(), 0, 500))->send();
                    }
                }),
            Action::make('quitar')
                ->label(__('mensajes.whatsapp.quitar'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->visible(fn (): bool => (new WhatsappCloud((int) ($this->datos['propiedad_id'] ?? 0)))->configurado())
                ->action(function (): void {
                    $propiedadId = (int) ($this->datos['propiedad_id'] ?? 0);
                    abort_unless(array_key_exists($propiedadId, self::propiedades()), 403);
                    (new WhatsappCloud($propiedadId))->quitar();
                    RegistroAuditoria::anotar('whatsapp.quitar', null, [], $propiedadId);
                    $this->cargar($propiedadId);
                }),
        ];
    }
}
