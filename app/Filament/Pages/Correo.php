<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\AjustesCorreo;
use App\Comunicacion\MensajeTexto;
use App\Demo\ModoDemo;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * El correo de salida de toda la instalación (D-045). La contraseña se guarda cifrada y no vuelve
 * a mostrarse.
 *
 * @property-read Schema $form
 */
class Correo extends Page
{
    protected string $view = 'filament.pages.correo';

    protected static ?string $slug = 'correo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public static function canAccess(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('mensajes.correo.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mensajes.correo.titulo');
    }

    public function mount(): void
    {
        $this->form->fill([
            'host' => Ajustes::obtener('correo.host'),
            'puerto' => Ajustes::obtener('correo.puerto', 587),
            'cifrado' => Ajustes::obtener('correo.cifrado', 'tls'),
            'usuario' => Ajustes::obtener('correo.usuario'),
            'remitente_correo' => Ajustes::obtener('correo.remitente_correo'),
            'remitente_nombre' => Ajustes::obtener('correo.remitente_nombre', 'AlojaFlow'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('datos')->components([
            Section::make()->description(__('mensajes.correo.ayuda'))->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('host')->label(__('mensajes.correo.host'))->maxLength(255)->required(),
                TextInput::make('puerto')->label(__('mensajes.correo.puerto'))->integer()->minValue(1)->maxValue(65535)->required(),
                Select::make('cifrado')->label(__('mensajes.correo.cifrado'))->helperText(__('mensajes.correo.cifrado_ayuda'))->native(false)->required()
                    ->options(['tls' => __('mensajes.correo.cifrado_tls'), 'ssl' => __('mensajes.correo.cifrado_ssl'), 'ninguno' => __('mensajes.correo.cifrado_ninguno')]),
                TextInput::make('usuario')->label(__('mensajes.correo.usuario'))->maxLength(255)->autocomplete('off'),
                TextInput::make('contrasena')->label(__('mensajes.correo.contrasena'))->password()->revealable()->autocomplete('new-password')->maxLength(500)
                    ->helperText(fn (): string => filled(Ajustes::obtener('correo.contrasena')) ? __('mensajes.correo.contrasena_guardada') : ''),
                TextInput::make('remitente_correo')->label(__('mensajes.correo.remitente_correo'))->email()->maxLength(255)->required(),
                TextInput::make('remitente_nombre')->label(__('mensajes.correo.remitente_nombre'))->maxLength(100),
            ]),
        ]);
    }

    public function guardar(): void
    {
        $datos = $this->form->getState();
        foreach (['host', 'puerto', 'cifrado', 'usuario', 'remitente_correo', 'remitente_nombre'] as $campo) {
            Ajustes::guardar('correo.'.$campo, $datos[$campo] ?? null);
        }
        if (filled($datos['contrasena'] ?? null)) {
            Ajustes::guardarSecreto('correo.contrasena', (string) $datos['contrasena']);
        }
        $this->datos['contrasena'] = null;
        RegistroAuditoria::anotar('correo.ajustes', null, ['host' => $datos['host'] ?? null]);
        Notification::make()->success()->title(__('mensajes.correo.guardado'))->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('probar')
                ->label(__('mensajes.correo.probar'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->visible(fn (): bool => app(AjustesCorreo::class)->configurado())
                ->action(function (AjustesCorreo $correo): void {
                    $destino = (string) Acceso::usuarioActual()?->email;
                    try {
                        $correo->mailer()->to($destino)->send(new MensajeTexto(__('mensajes.correo.prueba_asunto'), __('mensajes.correo.prueba_cuerpo'), $correo->remitente(), $correo->nombreRemitente()));
                        Notification::make()->success()->title(__('mensajes.correo.prueba_ok', ['correo' => $destino]))->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->persistent()->title(__('mensajes.correo.prueba_fallo'))->body(mb_substr($e->getMessage(), 0, 500))->send();
                    }
                }),
            Action::make('quitar')
                ->label(__('mensajes.correo.quitar'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->visible(fn (): bool => filled(Ajustes::obtener('correo.host')))
                ->action(function (): void {
                    foreach (['host', 'puerto', 'cifrado', 'usuario', 'contrasena', 'remitente_correo', 'remitente_nombre'] as $campo) {
                        Ajustes::borrar('correo.'.$campo);
                    }
                    RegistroAuditoria::anotar('correo.quitar', null);
                    $this->mount();
                }),
        ];
    }
}
