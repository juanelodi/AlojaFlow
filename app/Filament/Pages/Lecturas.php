<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Camping\AjustesCamping;
use App\Camping\ErrorLectura;
use App\Camping\GestorLecturas;
use App\Enums\ServicioMedido;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Lectura;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use App\Support\Dinero;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Capturar de una vez lo que marcan los medidores (D-111). Se cobra a quien está en cada lugar.
 */
class Lecturas extends Page
{
    use ConPropiedadSeleccionada;

    private const HISTORIAL = 30;

    protected string $view = 'filament.pages.lecturas';

    protected static ?string $slug = 'lecturas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 7;

    public string $servicio = 'luz';

    public string $fecha = '';

    /** @var array<int, array{actual?: mixed, inicial?: mixed}> */
    public array $capturas = [];

    protected static function modulo(): ?Modulo
    {
        return Modulo::Camping;
    }

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('camping.lecturas.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('camping.lecturas.titulo');
    }

    public function mount(): void
    {
        $this->fecha = Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
    }

    /**
     * Las lecturas son de una propiedad a la vez: cada una tiene su moneda y sus precios. En una subordinada, los cobros los lleva la principal.
     */
    public function propiedad(): ?Propiedad
    {
        $gestionables = $this->propiedadesGestionables();

        return count($gestionables) === 1 ? Propiedad::query()->find($gestionables[0]) : null;
    }

    public function servicioElegido(): ServicioMedido
    {
        return ServicioMedido::tryFrom($this->servicio) ?? ServicioMedido::Luz;
    }

    private function fechaElegida(): ?CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fecha) === 1 ? CarbonImmutable::parse($this->fecha) : null;
    }

    /**
     * Cada lugar con quién está, cuánto marcó la última vez y, con lo capturado, lo que se le va a cobrar.
     *
     * @return list<array{unidad: Unidad, huesped: string|null, anterior: Lectura|null, cobrable: bool, importe: int|null}>
     */
    public function filas(): array
    {
        $propiedad = $this->propiedad();
        $fecha = $this->fechaElegida();
        if ($propiedad === null || $fecha === null) {
            return [];
        }
        $gestor = app(GestorLecturas::class);
        $servicio = $this->servicioElegido();
        $tarifa = (new AjustesCamping($propiedad))->tarifa($servicio);
        $ultimas = $gestor->ultimas($propiedad, $servicio);
        $reservas = $gestor->reservasDe($propiedad, $fecha);
        $filas = [];
        foreach (Unidad::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->orderBy('orden')->orderBy('nombre')->get() as $unidad) {
            $anterior = $ultimas->get($unidad->id);
            $reserva = $reservas->get($unidad->id);
            $inicial = ! empty($this->capturas[$unidad->id]['inicial']);
            $cobrable = $gestor->cobrable($anterior, $reserva, $inicial);
            $actual = $this->capturas[$unidad->id]['actual'] ?? null;
            $importe = $cobrable && $tarifa !== null && is_numeric($actual) && (float) $actual >= (float) $anterior?->actual
                ? Dinero::aUnidadMenor(number_format(((float) $actual - (float) $anterior?->actual) * (float) $tarifa, 4, '.', ''), $propiedad->moneda)
                : null;
            $filas[] = ['unidad' => $unidad, 'huesped' => $reserva?->titular?->nombreCompleto(), 'anterior' => $anterior, 'cobrable' => $cobrable, 'importe' => $importe];
        }

        return $filas;
    }

    /**
     * @return Collection<int, Lectura>
     */
    public function historial(): Collection
    {
        $propiedad = $this->propiedad();

        return $propiedad === null ? new Collection() : Lectura::query()->with(['unidad', 'reserva.titular'])->where('propiedad_id', $propiedad->id)->where('servicio', $this->servicioElegido()->value)
            ->orderByDesc('leida_en')->orderByDesc('id')->limit(self::HISTORIAL)->get();
    }

    public function tarifaActual(): ?string
    {
        $propiedad = $this->propiedad();

        return $propiedad === null ? null : (new AjustesCamping($propiedad))->tarifa($this->servicioElegido());
    }

    public function guardar(): void
    {
        $propiedad = $this->propiedad();
        $fecha = $this->fechaElegida();
        if ($propiedad === null || $fecha === null) {
            return;
        }
        try {
            $guardadas = app(GestorLecturas::class)->registrar($propiedad, $this->servicioElegido(), $fecha, $this->capturas);
            $this->capturas = [];
            Notification::make()->success()->title(trans_choice('camping.lecturas.guardadas', $guardadas))->send();
        } catch (ErrorLectura|ReservaRechazada $e) {
            Notification::make()->danger()->persistent()->title($e->getMessage())->send();
        }
    }

    public function quitar(int $lecturaId): void
    {
        $lectura = Lectura::query()->whereIn('propiedad_id', $this->propiedadesGestionables())->findOrFail($lecturaId);
        try {
            app(GestorLecturas::class)->quitar($lectura);
            Notification::make()->success()->title(__('camping.lecturas.quitada'))->send();
        } catch (ErrorLectura|ReservaRechazada $e) {
            Notification::make()->danger()->persistent()->title($e->getMessage())->send();
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('precios')
                ->label(__('camping.lecturas.precios'))
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('gray')
                ->visible(fn (): bool => $this->propiedad() !== null && Acceso::puede(Acceso::usuarioActual(), Acceso::CATALOGO, $this->propiedad()->id))
                ->modalDescription(fn (): string => __('camping.lecturas.precios_ayuda', ['moneda' => (string) $this->propiedad()?->moneda]))
                ->fillForm(fn (): array => collect(ServicioMedido::cases())->mapWithKeys(fn (ServicioMedido $s): array => [$s->value => (new AjustesCamping($this->propiedad()))->tarifa($s)])->all())
                ->schema(array_map(
                    fn (ServicioMedido $s): TextInput => TextInput::make($s->value)->label(__('camping.lecturas.precio_por', ['servicio' => $s->etiqueta(), 'medida' => $s->medida()]))->numeric()->minValue(0)->step('any'),
                    ServicioMedido::cases(),
                ))
                ->action(function (array $data): void {
                    $propiedad = $this->propiedad();
                    if ($propiedad !== null) {
                        (new AjustesCamping($propiedad))->guardarTarifas($data);
                        Notification::make()->success()->title(__('camping.lecturas.precios_ok'))->send();
                    }
                }),
        ];
    }
}
