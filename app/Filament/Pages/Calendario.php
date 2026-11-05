<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\TipoReserva;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Servicios\Calendario as ServicioCalendario;
use App\Servicios\Operacion;
use App\Support\Formato;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Calendario por habitaciones de un mes (M2). En una propiedad independiente, un día libre abre
 * una reserva nueva; en una subordinada es solo consulta y se cambia en la principal.
 */
class Calendario extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.calendario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    #[Url]
    public ?string $mes = null;

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
        return __('ui.calendario.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.calendario.titulo');
    }

    public function mount(): void
    {
        $this->mes ??= Operacion::hoyDe($this->propiedadUnica())->format('Y-m');
    }

    public function inicioMes(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m', (string) $this->mes) ?: Operacion::hoyDe(null)->startOfMonth();
        } catch (Throwable) {
            return Operacion::hoyDe(null)->startOfMonth();
        }
    }

    public function moverMes(int $meses): void
    {
        $this->mes = $this->inicioMes()->addMonths($meses)->format('Y-m');
    }

    public function irAEsteMes(): void
    {
        $this->mes = Operacion::hoyDe($this->propiedadUnica())->format('Y-m');
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return ServicioCalendario::mes($this->propiedadesElegidas(), $this->inicioMes());
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevaReserva')->label(__('ui.reservas.nueva'))->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => ReservaResource::getUrl('create'))
                ->visible(fn (): bool => ReservaResource::canCreate()),
            Action::make('bloquearFechas')->label(__('operacion.bloqueos.accion'))->icon(Heroicon::OutlinedNoSymbol)->color('gray')
                ->modalDescription(__('operacion.bloqueos.ayuda'))
                ->visible(fn (): bool => ReservaResource::canCreate())
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => count(ReservaResource::propiedadesGestionables()) === 1 ? ReservaResource::propiedadesGestionables()[0] : null),
                    CheckboxList::make('unidades')->label(__('operacion.bloqueos.unidades'))->required()->columns(2)
                        ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), ReservaResource::propiedadesGestionables()))->where('activa', true)->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    DatePicker::make('desde')->label(__('ui.indicadores.desde'))->required(),
                    DatePicker::make('hasta')->label(__('operacion.bloqueos.hasta'))->helperText(__('operacion.bloqueos.hasta_ayuda'))->required()->afterOrEqual('desde'),
                    TextInput::make('motivo')->label(__('ui.reservas.motivo'))->maxLength(100),
                ])
                ->action(fn (array $data) => $this->bloquearFechas($data)),
        ];
    }

    /**
     * Cierra sin crear una reserva. Si ya hay algo en una unidad, esa se salta y se avisa: nada se mueve solo.
     *
     * @param  array<string, mixed>  $data
     */
    private function bloquearFechas(array $data): void
    {
        $propiedad = Propiedad::query()->whereIn('id', ReservaResource::propiedadesGestionables())->findOrFail((int) $data['propiedad_id']);
        $salida = CarbonImmutable::parse((string) $data['hasta'])->addDay()->format('Y-m-d');
        $bloqueadas = 0;
        $saltadas = [];
        foreach (Unidad::query()->where('propiedad_id', $propiedad->id)->whereIn('id', array_map('intval', (array) $data['unidades']))->get() as $unidad) {
            try {
                app(GestorReservas::class)->crear($propiedad, [
                    'tipo' => TipoReserva::Bloqueo->value,
                    'unidad_id' => $unidad->id,
                    'llegada' => (string) $data['desde'],
                    'salida' => $salida,
                    'origen_texto' => $data['motivo'] ?? null,
                ]);
                $bloqueadas++;
            } catch (ReservaRechazada) {
                $saltadas[] = $unidad->nombre;
            }
        }
        $aviso = Notification::make()->title(__('operacion.bloqueos.hecho', ['n' => $bloqueadas]))->status($saltadas === [] ? 'success' : 'warning');
        if ($saltadas !== []) {
            // Para que dé tiempo de leer cuáles no se bloquearon.
            $aviso->body(__('operacion.bloqueos.saltadas', ['unidades' => implode(', ', $saltadas)]))->persistent();
        }
        $aviso->send();
    }

    /**
     * Arrastrar y soltar: la reserva empieza el día donde se soltó, con las mismas noches.
     */
    public function moverReserva(int $reservaId, int $unidadId, string $llegada): void
    {
        $reserva = $this->reservaParaCambiar($reservaId);
        if ($reserva === null) {
            return;
        }
        try {
            app(GestorReservas::class)->mover($reserva, $unidadId, CarbonImmutable::createFromFormat('!Y-m-d', $llegada) ?: throw new ReservaRechazada(__('ui.reservas.error_fechas')));
            Notification::make()->success()->title(__('ui.calendario.movida'))->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    /**
     * Arrastrar un extremo de la barra (D-124). El día en que se suelta es la nueva llegada o la última noche. El total se queda: si con la tarifa fuera otro, se le pregunta.
     */
    public function cambiarFechas(int $reservaId, string $lado, string $fecha): void
    {
        $reserva = $this->reservaParaCambiar($reservaId);
        if ($reserva === null) {
            return;
        }
        $gestor = app(GestorReservas::class);
        try {
            $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha) ?: throw new ReservaRechazada(__('ui.reservas.error_fechas'));
            [$llegada, $salida] = $lado === 'inicio'
                ? [$dia, CarbonImmutable::parse($reserva->salida->format('Y-m-d'))]
                : [CarbonImmutable::parse($reserva->llegada->format('Y-m-d')), $dia->addDay()];
            $cambiada = $gestor->cambiarFechas($reserva, $llegada, $salida);
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        $aviso = Notification::make()->success()->title(__('ui.calendario.fechas_cambiadas'));
        $nuevo = $cambiada->tipo === TipoReserva::Reserva ? $gestor->totalSegunTarifa($cambiada) : null;
        if ($nuevo !== null && $nuevo !== $cambiada->total) {
            $aviso->persistent()
                ->body(__('ui.calendario.total_distinto', ['total' => Formato::dinero($cambiada->total, $cambiada->moneda), 'nuevo' => Formato::dinero($nuevo, $cambiada->moneda)]))
                ->actions([Action::make('usarTotal')->label(__('ui.calendario.usar_ese_total'))->button()->close()->dispatch('aplicarTarifaEnCalendario', ['reservaId' => $cambiada->id])]);
        }
        $aviso->send();
    }

    #[On('aplicarTarifaEnCalendario')]
    public function aplicarTarifa(int $reservaId): void
    {
        $reserva = $this->reservaParaCambiar($reservaId);
        if ($reserva === null) {
            return;
        }
        try {
            app(GestorReservas::class)->aplicarTarifa($reserva);
            Notification::make()->success()->title(__('ui.calendario.total_aplicado'))->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    /**
     * La que este usuario puede cambiar; si no, se lo dice.
     */
    private function reservaParaCambiar(int $reservaId): ?Reserva
    {
        $reserva = Reserva::query()->whereIn('propiedad_id', $this->propiedadesGestionables())->where('sincronizada', false)->findOrFail($reservaId);
        if (! Limites::puedeModificar(Acceso::usuarioActual(), $reserva)) {
            Notification::make()->danger()->title(__('permisos.limites.no_modificar'))->send();

            return null;
        }

        return $reserva;
    }

    public function hoy(): CarbonImmutable
    {
        return Operacion::hoyDe($this->propiedadUnica());
    }
}
