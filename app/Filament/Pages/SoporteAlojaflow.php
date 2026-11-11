<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Seguridad\AccesoSoporte;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sistema → Soporte de AlojaFlow (D-166), solo en el servicio alojado: el administrador deja entrar al equipo de
 * AlojaFlow durante 24 horas, lo retira cuando quiera y ve quién entró. El soporte mismo no puede darse permiso.
 */
class SoporteAlojaflow extends Page
{
    protected string $view = 'filament.pages.soporte-alojaflow';

    protected static ?string $slug = 'soporte-alojaflow';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 62;

    public static function canAccess(): bool
    {
        $usuario = Acceso::usuarioActual();

        return AccesoSoporte::disponible() && (bool) $usuario?->es_administrador && ! $usuario->es_soporte;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('soporte.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('soporte.titulo');
    }

    public function permitidoHasta(): ?string
    {
        $hasta = app(AccesoSoporte::class)->permitidoHasta();

        return $hasta === null ? null : Formato::momento($hasta);
    }

    /**
     * Quién del soporte entró en los últimos 90 días, y si sigue dentro.
     *
     * @return Collection<int, array{nombre: string, cuando: string, orden: string, hasta: string, emergencia: string|null, dentro: bool}>
     */
    public function accesos(): Collection
    {
        $usuarios = User::query()->where('es_soporte', true)->get()->keyBy('id');

        return RegistroAuditoria::query()->where('accion', 'soporte.acceso')->where('creado_en', '>=', now()->subDays(90))
            ->latest('id')->limit(50)->get()
            ->map(function (RegistroAuditoria $registro) use ($usuarios): array {
                $usuario = $usuarios->get((int) $registro->modelo_id);
                $cambios = (array) $registro->cambios;

                return [
                    'nombre' => (string) ($cambios['nombre'] ?? $usuario->name ?? '—'),
                    'cuando' => Formato::momento($registro->creado_en),
                    'orden' => $registro->creado_en->format('Y-m-d H:i:s'),
                    'hasta' => isset($cambios['hasta']) ? Formato::momento(Carbon::parse((string) $cambios['hasta'])) : '—',
                    'emergencia' => isset($cambios['emergencia']) ? (string) $cambios['emergencia'] : null,
                    'dentro' => $usuario !== null && $usuario->activo && $usuario->soporte_hasta?->isFuture() === true,
                ];
            });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('permitir')
                ->label(fn (): string => app(AccesoSoporte::class)->permitidoHasta() === null ? __('soporte.permitir') : __('soporte.extender'))
                ->icon(Heroicon::OutlinedLockOpen)
                ->requiresConfirmation()
                ->modalDescription(__('soporte.permitir_ayuda'))
                ->action(function (): void {
                    $usuario = Acceso::usuarioActual();
                    abort_unless($usuario !== null && self::canAccess(), 403);
                    app(AccesoSoporte::class)->permitir($usuario);
                    Notification::make()->success()->title(__('soporte.permitido', ['hasta' => (string) $this->permitidoHasta()]))->send();
                }),
            Action::make('retirar')
                ->label(__('soporte.retirar'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->modalDescription(__('soporte.retirar_ayuda'))
                ->visible(fn (): bool => app(AccesoSoporte::class)->permitidoHasta() !== null || User::query()->where('es_soporte', true)->where('activo', true)->exists())
                ->action(function (): void {
                    abort_unless(self::canAccess(), 403);
                    app(AccesoSoporte::class)->retirar();
                    Notification::make()->success()->title(__('soporte.retirado'))->send();
                }),
        ];
    }
}
