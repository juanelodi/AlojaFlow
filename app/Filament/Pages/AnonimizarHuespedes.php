<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Privacidad\GestorPrivacidad;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Anonimizar de una vez a quienes llevan años sin venir (D-088). Es irreversible y alcanza a muchos: es para quien administra, y pide su contraseña.
 */
class AnonimizarHuespedes extends Page
{
    use ConPropiedadSeleccionada;

    private const FILAS_VISIBLES = 100;

    private const LOTE = 200;

    protected string $view = 'filament.pages.anonimizar-huespedes';

    protected static ?string $slug = 'anonimizar-huespedes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEyeSlash;

    protected static ?int $navigationSort = 6;

    public ?int $anios = 5;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
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

        return array_values(array_diff(Acceso::propiedades($usuario, self::permiso()), Limites::propiedadesSoloPropias($usuario)));
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
        return __('herramientas.anonimizar.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('herramientas.anonimizar.titulo');
    }

    public function aniosValidos(): int
    {
        return min(50, max(1, (int) $this->anios));
    }

    public function total(): int
    {
        return app(GestorPrivacidad::class)->sinEstanciasDesde($this->propiedadesElegidas(), $this->aniosValidos())->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Huesped>
     */
    public function huespedes(): \Illuminate\Database\Eloquent\Collection
    {
        return app(GestorPrivacidad::class)->sinEstanciasDesde($this->propiedadesElegidas(), $this->aniosValidos())->orderBy('apellido1')->orderBy('nombre')->limit(self::FILAS_VISIBLES)->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('anonimizarTodos')
                ->label(__('herramientas.anonimizar.accion'))
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->disabled(fn (): bool => $this->total() === 0)
                ->modalDescription(fn (): string => trans_choice('herramientas.anonimizar.confirmar', $this->total(), ['n' => $this->total(), 'anios' => $this->aniosValidos()]))
                ->schema([TextInput::make('contrasena')->label(__('actualizaciones.contrasena'))->password()->required()->currentPassword()])
                ->action(function (): void {
                    $privacidad = app(GestorPrivacidad::class);
                    $hechos = 0;
                    // Por tandas, para no pasar del tiempo de una petición: lo que falte se ve al volver.
                    foreach ($privacidad->sinEstanciasDesde($this->propiedadesElegidas(), $this->aniosValidos())->limit(self::LOTE)->get() as $huesped) {
                        $privacidad->anonimizar($huesped);
                        $hechos++;
                    }
                    RegistroAuditoria::anotar('privacidad.anonimizar_lote', null, ['fichas' => $hechos, 'anios' => $this->aniosValidos()]);
                    Notification::make()->success()->title(trans_choice('herramientas.anonimizar.hecho', $hechos, ['n' => $hechos]))->send();
                }),
        ];
    }
}
