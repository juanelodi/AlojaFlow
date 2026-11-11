<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Huesped;
use App\Reservas\DetectorDuplicados;
use App\Reservas\FusionHuespedes;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fichas que pueden ser la misma persona (D-088). Solo en propiedades independientes, y no para quien solo ve lo suyo: fusionar junta fichas ajenas.
 */
class HuespedesDuplicados extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.huespedes-duplicados';

    protected static ?string $slug = 'huespedes-duplicados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 5;

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function canAccess(): bool
    {
        return self::permitidas() !== [];
    }

    /**
     * @return list<int>
     */
    private static function permitidas(): array
    {
        $usuario = Acceso::usuarioActual();

        return array_values(array_diff(Acceso::propiedadesIndependientes($usuario, Acceso::RESERVAS), Limites::propiedadesSoloPropias($usuario)));
    }

    /**
     * @return list<int>
     */
    public function propiedadesVisibles(): array
    {
        return self::permitidas();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.reservas');
    }

    public static function getNavigationLabel(): string
    {
        return __('herramientas.duplicados.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('herramientas.duplicados.titulo');
    }

    /**
     * @return list<array{motivo: string, huespedes: list<Huesped>}>
     */
    public function grupos(): array
    {
        $grupos = [];
        foreach ($this->propiedadesElegidas() as $propiedadId) {
            $grupos = [...$grupos, ...app(DetectorDuplicados::class)->grupos($propiedadId)];
        }

        return $grupos;
    }

    /**
     * @param  list<int>  $otras
     */
    public function fusionar(int $conservar, array $otras): void
    {
        $permitidas = $this->propiedadesVisibles();
        $ficha = Huesped::query()->whereIn('propiedad_id', $permitidas)->where('sincronizada', false)->findOrFail($conservar);
        $fusion = app(FusionHuespedes::class);
        foreach (Huesped::query()->where('propiedad_id', $ficha->propiedad_id)->where('sincronizada', false)->whereIn('id', array_map(intval(...), $otras))->whereKeyNot($ficha->id)->get() as $otra) {
            $fusion->fusionar($ficha, $otra);
        }
        Notification::make()->success()->title(__('ui.huespedes.fusionada'))->send();
    }
}
