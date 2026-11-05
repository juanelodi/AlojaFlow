<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Dinero\GestorPoliticas;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Politica;
use App\Models\Reserva;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use App\Support\Formato;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Lo que hay que recordar y lo que no llegó (D-083). AlojaFlow calcula y recuerda: no cancela ni cobra nada por sí solo.
 */
class DepositosNoShow extends Page
{
    use ConPropiedadSeleccionada;

    private const FILAS_MAXIMAS = 300;

    private const DIAS_NO_SHOW = 7;

    protected string $view = 'filament.pages.depositos-no-show';

    protected static ?string $slug = 'depositos-y-no-shows';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 5;

    public string $numero = '';

    public ?string $calculo = null;

    /** @var array<string, Politica|null> */
    private array $politicas = [];

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getNavigationLabel(): string
    {
        return __('operacion.depositos.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('operacion.depositos.titulo');
    }

    /**
     * Lo que pide la política, o el anticipo de la reserva si es mayor. Vencido, si ya pasó el plazo desde que se hizo.
     *
     * @return list<array{reserva: Reserva, importe: int, vence: CarbonImmutable, vencido: bool, whatsapp: string}>
     */
    public function depositos(): array
    {
        $politicas = app(GestorPoliticas::class);
        $filas = [];
        $reservas = Operacion::reservas($this->propiedadesElegidas())->whereColumn('pagado', '<', 'total')->orderBy('llegada')->limit(self::FILAS_MAXIMAS)->get();
        foreach ($reservas as $reserva) {
            $hoy = $reserva->propiedad->hoy();
            if ($reserva->llegada->format('Y-m-d') < $hoy->format('Y-m-d')) {
                continue;
            }
            $politica = $this->politicaDe($reserva);
            $importe = max($reserva->deposito - $reserva->pagado, $politica === null ? 0 : $politicas->depositoPendiente($reserva, $politica));
            if ($importe <= 0) {
                continue;
            }
            $vence = ($reserva->creado_en ?? now())->toImmutable()->setTimezone($reserva->propiedad->zona_horaria)->addDays($politica->deposito_plazo_dias ?? 0)->startOfDay();
            $filas[] = [
                'reserva' => $reserva,
                'importe' => $importe,
                'vence' => $vence,
                'vencido' => $vence->lt($hoy),
                'whatsapp' => VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($reserva), $this->recordatorio($reserva, $importe, $vence), $reserva->propiedad->pais),
            ];
        }
        usort($filas, fn (array $a, array $b): int => [$b['vencido'], $a['vence']] <=> [$a['vencido'], $b['vence']]);

        return $filas;
    }

    /**
     * Llegada pasada en los últimos días y sin entrada, con lo que costaría.
     *
     * @return list<array{reserva: Reserva, penalizacion: int}>
     */
    public function noShows(): array
    {
        $politicas = app(GestorPoliticas::class);
        $filas = [];
        $hoy = CarbonImmutable::now();
        $reservas = Operacion::reservas($this->propiedadesElegidas())->whereNull('entrada_registrada_en')
            ->whereBetween('llegada', [$hoy->subDays(self::DIAS_NO_SHOW + 1)->format('Y-m-d'), $hoy->addDay()->format('Y-m-d')])->orderByDesc('llegada')->limit(self::FILAS_MAXIMAS)->get();
        foreach ($reservas as $reserva) {
            $hoyAlla = $reserva->propiedad->hoy();
            if ($reserva->llegada->format('Y-m-d') >= $hoyAlla->format('Y-m-d') || $reserva->llegada->format('Y-m-d') < $hoyAlla->subDays(self::DIAS_NO_SHOW)->format('Y-m-d') || ! $politicas->puedeMarcarNoShow($reserva)) {
                continue;
            }
            $politica = $this->politicaDe($reserva);
            $filas[] = ['reserva' => $reserva, 'penalizacion' => $politica === null ? 0 : $politicas->penalizacionNoShow($reserva, $politica)];
        }

        return $filas;
    }

    public function marcarNoShow(int $reservaId): void
    {
        $reserva = Operacion::reservas($this->propiedadesVisibles())->findOrFail($reservaId);
        try {
            $marca = app(GestorPoliticas::class)->marcarNoShow($reserva, null);
            Notification::make()->success()->title(__('dinero.politicas.no_show_ok', ['importe' => Formato::dinero($marca->penalizacion, $marca->moneda)]))->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function anotarRecordatorio(int $reservaId): void
    {
        $reserva = Operacion::reservas($this->propiedadesVisibles())->findOrFail($reservaId);
        $fila = collect($this->depositos())->first(fn (array $f): bool => $f['reserva']->id === $reserva->id);
        if ($fila !== null) {
            app(Mensajero::class)->anotarWhatsapp($reserva, Mensajero::telefonoDe($reserva), $this->recordatorio($reserva, $fila['importe'], $fila['vence']), Mensajero::idiomaDe($reserva));
        }
    }

    /**
     * Cuánto cuesta cancelar hoy una reserva.
     */
    public function calcular(): void
    {
        $reserva = Operacion::reservas($this->propiedadesElegidas())->where('numero', trim($this->numero))->first();
        $this->calculo = $reserva === null
            ? __('operacion.depositos.calculadora_no_existe')
            : (app(GestorPoliticas::class)->resumenCancelacion($reserva) ?? __('operacion.depositos.calculadora_sin_politica'));
    }

    private function politicaDe(Reserva $reserva): ?Politica
    {
        $clave = $reserva->propiedad_id.'|'.$reserva->origen_texto;
        if (! array_key_exists($clave, $this->politicas)) {
            $this->politicas[$clave] = app(GestorPoliticas::class)->aplicable($reserva);
        }

        return $this->politicas[$clave];
    }

    private function recordatorio(Reserva $reserva, int $importe, CarbonImmutable $vence): string
    {
        $idioma = Mensajero::idiomaDe($reserva);

        return __('operacion.depositos.recordatorio', [
            'nombre' => (string) ($reserva->titular->nombre ?? ''),
            'importe' => Formato::dinero($importe, $reserva->moneda),
            'fecha' => Formato::fecha($vence),
            'propiedad' => $reserva->propiedad->nombre,
        ], $idioma);
    }
}
