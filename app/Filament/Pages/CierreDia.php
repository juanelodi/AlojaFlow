<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\CierreDia as ModeloCierre;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use App\Servicios\CierreDelDia;
use App\Servicios\Operacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Revisar el día y dejar constancia de cómo terminó (M3).
 */
class CierreDia extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.cierre-dia';

    protected static ?string $slug = 'cierre-del-dia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?int $navigationSort = 2;

    #[Url]
    public ?string $fecha = null;

    protected static function permiso(): string
    {
        return Acceso::OPERACION;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('cierre.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('cierre.titulo');
    }

    public function mount(): void
    {
        $this->fecha ??= Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
    }

    public function fechaElegida(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->fecha) ?: Operacion::hoyDe($this->propiedadUnica());
        } catch (Throwable) {
            return Operacion::hoyDe($this->propiedadUnica());
        }
    }

    /**
     * @return list<array{propiedad: Propiedad, resumen: array<string, mixed>, cierre: ModeloCierre|null}>
     */
    public function dias(): array
    {
        $servicio = app(CierreDelDia::class);
        $fecha = $this->fechaElegida();
        $filas = [];
        foreach (Propiedad::query()->whereIn('id', $this->propiedadesElegidas())->orderBy('nombre')->get() as $propiedad) {
            $filas[] = [
                'propiedad' => $propiedad,
                'resumen' => $servicio->resumen($propiedad, $fecha),
                'cierre' => ModeloCierre::query()->with('usuario')->where('propiedad_id', $propiedad->id)->whereDate('fecha', $fecha->format('Y-m-d'))->first(),
            ];
        }

        return $filas;
    }

    /**
     * @return Collection<int, ModeloCierre>
     */
    public function anteriores(): Collection
    {
        return ModeloCierre::query()->with(['propiedad', 'usuario'])->whereIn('propiedad_id', $this->propiedadesElegidas())->orderByDesc('fecha')->limit(10)->get();
    }

    public function cerrarAction(): Action
    {
        return Action::make('cerrar')
            ->label(__('cierre.cerrar'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->modalDescription(__('cierre.cerrar_ayuda'))
            ->schema([Textarea::make('notas')->label(__('cierre.notas'))->rows(3)->maxLength(2000)])
            ->action(function (array $data, array $arguments, CierreDelDia $servicio): void {
                $propiedad = Propiedad::query()->whereIn('id', $this->propiedadesVisibles())->findOrFail((int) ($arguments['propiedad'] ?? 0));
                $servicio->cerrar($propiedad, $this->fechaElegida(), $data['notas'] ?? null);
                Notification::make()->success()->title(__('cierre.cerrado'))->send();
            });
    }
}
