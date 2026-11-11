<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Guia\GuiaSeccionResource;
use App\Models\RegistroAuditoria;
use App\Models\Unidad;
use App\Publico\GuiaDigital;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Un código QR por unidad, para imprimirlo y dejarlo a la vista. Lleva a la guía sin lo que es solo de quien tiene reserva (D-106).
 */
class CodigosGuia extends Page
{
    protected string $view = 'filament.pages.codigos-guia';

    protected static ?string $slug = 'guia-codigos';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return GuiaSeccionResource::canViewAny();
    }

    public function getTitle(): string|Htmlable
    {
        return __('portal.guia_panel.codigos');
    }

    /**
     * @return list<array{unidad: Unidad, url: string, qr: string}>
     */
    public function codigos(): array
    {
        $guia = app(GuiaDigital::class);

        return Unidad::query()->with('propiedad')->whereIn('propiedad_id', GuiaSeccionResource::propiedadesGestionables())->where('activa', true)->orderBy('propiedad_id')->orderBy('nombre')->get()
            ->map(function (Unidad $unidad) use ($guia): array {
                $url = $guia->urlDe($unidad);

                return ['unidad' => $unidad, 'url' => $url, 'qr' => $guia->qr($url)];
            })->all();
    }

    public function cambiar(int $unidad): void
    {
        $fila = Unidad::query()->whereIn('propiedad_id', GuiaSeccionResource::propiedadesGestionables())->findOrFail($unidad);
        app(GuiaDigital::class)->cambiarEnlace($fila);
        RegistroAuditoria::anotar('guia.cambiar_codigo', $fila, ['unidad' => $fila->nombre], $fila->propiedad_id);
        Notification::make()->success()->title(__('portal.guia_panel.codigo_cambiado'))->send();
    }
}
