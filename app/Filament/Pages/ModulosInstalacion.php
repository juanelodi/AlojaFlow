<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Sistema → Módulos: prender y apagar lo que usa esta instalación (D-069). Solo el super administrador.
 */
class ModulosInstalacion extends Page
{
    protected string $view = 'filament.pages.modulos-instalacion';

    protected static ?string $slug = 'modulos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 70;

    /** @var array<string, bool> */
    public array $elegidos = [];

    /** @var list<string> Los que llegaron con la última actualización y se ven aquí por primera vez. */
    public array $nuevos = [];

    public static function canAccess(): bool
    {
        return Acceso::esSuper(Acceso::usuarioActual()) && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('modulos.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('modulos.titulo');
    }

    public function mount(): void
    {
        foreach (Modulo::cases() as $modulo) {
            $this->elegidos[$modulo->value] = Modulos::activo($modulo);
        }
        $this->nuevos = array_map(fn (Modulo $m): string => $m->value, Modulos::nuevos());
        Modulos::marcarConocidos();
    }

    public function updatedElegidos(mixed $valor, string $clave): void
    {
        $this->elegidos = Modulos::ajustarElegidos($this->elegidos, $clave);
    }

    public function guardar(): void
    {
        $elegidos = array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => (bool) ($this->elegidos[$m->value] ?? false)));
        Modulos::guardar($elegidos);
        RegistroAuditoria::anotar('modulos.cambiar', null, ['activos' => array_map(fn (Modulo $m): string => $m->value, Modulos::prendidos())]);
        foreach (Modulo::cases() as $modulo) {
            $this->elegidos[$modulo->value] = Modulos::activo($modulo);
        }
        Notification::make()->success()->title(__('modulos.guardado'))->send();
        // El menú se arma al cargar la página: con un módulo apagado, debe desaparecer ya.
        $this->redirect(self::getUrl());
    }

    /**
     * @return array<string, list<Modulo>>
     */
    public function grupos(): array
    {
        $grupos = [];
        foreach (Modulo::cases() as $modulo) {
            $grupos[$modulo->grupo()][] = $modulo;
        }

        return $grupos;
    }

    public function enUso(Modulo $modulo): int
    {
        return Modulos::enUso($modulo);
    }
}
