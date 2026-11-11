<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\Mensajero;
use App\Comunicacion\PlantillasBase;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\CuotaReserva;
use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\Mensualidades as Calendario;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use App\Servicios\SeguimientoMensualidades;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Lo que deben las estancias largas (D-108). AlojaFlow recuerda: no cobra ni cancela nada por sí solo.
 */
class Mensualidades extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.mensualidades';

    protected static ?string $slug = 'mensualidades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 6;

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    protected static function modulo(): ?Modulo
    {
        return Modulo::EstanciasLargas;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getNavigationLabel(): string
    {
        return __('estancias.pagina.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('estancias.pagina.titulo');
    }

    /**
     * @return array{vencidas: list<array<string, mixed>>, proximas: list<array<string, mixed>>}
     */
    public function resumen(): array
    {
        return SeguimientoMensualidades::resumen($this->propiedadesElegidas());
    }

    public function diasProximas(): int
    {
        return SeguimientoMensualidades::DIAS_PROXIMAS;
    }

    /**
     * Deja anotado en la bitácora de envíos que se le escribió.
     */
    public function anotarRecordatorio(int $cuotaId): void
    {
        $cuota = CuotaReserva::query()->findOrFail($cuotaId);
        $reserva = Operacion::reservas($this->propiedadesVisibles())->findOrFail($cuota->reserva_id);
        $fila = collect(Calendario::de($reserva)['cuotas'])->first(fn (array $f): bool => $f['cuota']->id === $cuota->id);
        if ($fila !== null) {
            app(Mensajero::class)->anotarWhatsapp($reserva, Mensajero::telefonoDe($reserva), Calendario::recordatorio($reserva, $cuota, $cuota->importe - $fila['pagado']), Mensajero::idiomaDe($reserva));
        }
    }

    /**
     * Propiedades elegidas en las que los recordatorios por correo siguen sin prender, y en las que este usuario los puede prender.
     *
     * @return list<Propiedad>
     */
    public function sinRecordatorios(): array
    {
        if (! Modulos::activo(Modulo::Mensajes)) {
            return [];
        }
        $permitidas = array_values(array_intersect($this->propiedadesElegidas(), Acceso::propiedades(Acceso::usuarioActual(), Acceso::MENSAJES)));

        return Propiedad::query()->whereIn('id', $permitidas)->orderBy('nombre')->get()
            ->reject(fn (Propiedad $propiedad): bool => PlantillasBase::recordatoriosDeCuotaPrendidos($propiedad))->values()->all();
    }

    public function prenderRecordatorios(): void
    {
        foreach ($this->sinRecordatorios() as $propiedad) {
            PlantillasBase::prenderRecordatoriosDeCuota($propiedad);
        }
        Notification::make()->success()->title(__('estancias.pagina.recordatorios_ok'))->send();
    }
}
