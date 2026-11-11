<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Lo que cuenta la página pública de cada propiedad independiente, en cada idioma (D-104). Las fotos están en su propia lista.
 *
 * @property-read Schema $form
 */
class PaginaPublicaAjustes extends Page
{
    protected string $view = 'filament.pages.pagina-publica';

    protected static ?string $slug = 'pagina-publica';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 6;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public ?int $propiedadId = null;

    /**
     * @return list<int>
     */
    public static function propiedadesGestionables(): array
    {
        return Modulos::activo(Modulo::Motor) ? Acceso::propiedadesIndependientes(Acceso::usuarioActual(), Acceso::CATALOGO) : [];
    }

    public static function canAccess(): bool
    {
        return self::propiedadesGestionables() !== [];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getNavigationLabel(): string
    {
        return __('portal.panel.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('portal.panel.titulo');
    }

    public function mount(): void
    {
        $this->propiedadId = self::propiedadesGestionables()[0] ?? null;
        $this->cargar();
    }

    public function updatedPropiedadId(): void
    {
        $this->cargar();
    }

    public function propiedad(): ?Propiedad
    {
        return in_array($this->propiedadId, self::propiedadesGestionables(), true) ? Propiedad::query()->find($this->propiedadId) : null;
    }

    /**
     * @return array<int, string>
     */
    public function opcionesPropiedad(): array
    {
        return Propiedad::query()->whereIn('id', self::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function enlace(): ?string
    {
        $propiedad = $this->propiedad();

        return $propiedad === null ? null : route('motor.buscar', ['codigo' => $propiedad->codigo]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('datos')
            ->components([
                Section::make(__('portal.panel.textos'))->description(__('portal.panel.textos_ayuda'))->schema([
                    Tabs::make('idiomas')->tabs(array_map(fn (string $idioma, string $nombre): Tab => Tab::make($nombre)->schema([
                        Textarea::make('descripcion.'.$idioma)->label(__('portal.panel.descripcion'))->rows(4)->maxLength(3000),
                        Textarea::make('ubicacion.'.$idioma)->label(__('portal.panel.ubicacion'))->helperText(__('portal.panel.ubicacion_ayuda'))->rows(3)->maxLength(1000),
                        Textarea::make('servicios.'.$idioma)->label(__('portal.panel.servicios'))->helperText(__('portal.panel.servicios_ayuda'))->rows(3)->maxLength(1000),
                    ]), array_keys(Formato::idiomas()), array_values(Formato::idiomas()))),
                ]),
                Section::make(__('portal.panel.contacto'))->columns(['default' => 1, 'md' => 2])->schema([
                    TextInput::make('whatsapp')->label(__('portal.panel.whatsapp'))->helperText(__('portal.panel.whatsapp_ayuda'))->tel()->maxLength(30),
                    TextInput::make('mapa_url')->label(__('portal.panel.mapa'))->helperText(__('portal.panel.mapa_ayuda'))->url()->startsWith(['https://'])->maxLength(500),
                ]),
                Section::make(__('portal.panel.opciones'))->schema([
                    Toggle::make('mostrar_politicas')->label(__('portal.panel.mostrar_politicas'))->helperText(__('portal.panel.mostrar_politicas_ayuda')),
                    Toggle::make('huesped_cancela')->label(__('portal.panel.huesped_cancela'))->helperText(__('portal.panel.huesped_cancela_ayuda')),
                ]),
            ]);
    }

    public function guardar(): void
    {
        $propiedad = $this->propiedad();
        abort_if($propiedad === null, 403);
        $datos = $this->form->getState();
        $propiedad->update(['pagina_publica' => [
            'descripcion' => array_filter((array) ($datos['descripcion'] ?? [])),
            'ubicacion' => array_filter((array) ($datos['ubicacion'] ?? [])),
            'servicios' => array_filter((array) ($datos['servicios'] ?? [])),
            'whatsapp' => trim((string) ($datos['whatsapp'] ?? '')),
            'mapa_url' => trim((string) ($datos['mapa_url'] ?? '')),
            'mostrar_politicas' => (bool) ($datos['mostrar_politicas'] ?? false),
            'huesped_cancela' => (bool) ($datos['huesped_cancela'] ?? false),
        ]]);
        RegistroAuditoria::anotar('pagina_publica.guardar', $propiedad, [], $propiedad->id);
        Notification::make()->success()->title(__('portal.panel.guardado'))->send();
    }

    private function cargar(): void
    {
        $guardado = (array) $this->propiedad()?->pagina_publica;
        $this->form->fill([
            'descripcion' => (array) ($guardado['descripcion'] ?? []),
            'ubicacion' => (array) ($guardado['ubicacion'] ?? []),
            'servicios' => (array) ($guardado['servicios'] ?? []),
            'whatsapp' => $guardado['whatsapp'] ?? null,
            'mapa_url' => $guardado['mapa_url'] ?? null,
            'mostrar_politicas' => (bool) ($guardado['mostrar_politicas'] ?? true),
            'huesped_cancela' => (bool) ($guardado['huesped_cancela'] ?? false),
        ]);
    }
}
