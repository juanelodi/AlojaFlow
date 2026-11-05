<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Archivos\GestorArchivos;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoLimpieza;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Limpieza\GestorLimpieza;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Lo que hay que limpiar y arreglar hoy, pensado para usarse con el teléfono en la mano (M4).
 */
class Limpieza extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.limpieza';

    protected static ?string $slug = 'limpieza';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 4;

    #[Url]
    public ?string $fecha = null;

    #[Url]
    public bool $soloMias = false;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Limpieza;
    }

    protected static function permiso(): string
    {
        return Acceso::LIMPIEZA;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('limpieza.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('limpieza.titulo');
    }

    public function mount(): void
    {
        $this->fecha ??= Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
        // Sin esperar al programador: quien entra ve las salidas de las reservas recién creadas.
        $limpieza = app(GestorLimpieza::class);
        foreach ($this->propiedadesElegidas() as $id) {
            $propiedad = Propiedad::query()->find($id);
            if ($propiedad !== null) {
                $limpieza->generarTareas($propiedad);
            }
        }
    }

    public function fechaElegida(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->fecha) ?: Operacion::hoyDe($this->propiedadUnica());
        } catch (Throwable) {
            return Operacion::hoyDe($this->propiedadUnica());
        }
    }

    public function moverDia(int $dias): void
    {
        $this->fecha = $this->fechaElegida()->addDays($dias)->format('Y-m-d');
    }

    public function irAHoy(): void
    {
        $this->fecha = Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
    }

    /**
     * Las unidades, con lo que hay que hacer primero arriba.
     *
     * @return list<array{unidad: Unidad, limpieza: EstadoLimpieza, fuera: bool, nota: string|null, tareas: list<Tarea>, sale: bool, llega: bool, alojada: bool}>
     */
    public function tablero(): array
    {
        $fecha = $this->fechaElegida()->format('Y-m-d');
        $propiedades = $this->propiedadesElegidas();
        $unidades = Unidad::query()->with(['propiedad', 'estado'])->whereIn('propiedad_id', $propiedades)->where('activa', true)
            ->orderBy('propiedad_id')->orderBy('orden')->orderBy('nombre')->get();

        $tareas = Tarea::query()->with('archivos')->whereIn('propiedad_id', $propiedades)->where('fecha', '<=', $fecha)
            ->whereIn('estado', [EstadoTarea::Pendiente->value, EstadoTarea::EnCurso->value])
            ->when($this->soloMias, fn ($q) => $q->where(fn ($q) => $q->where('asignada_a', auth()->id())->orWhereNull('asignada_a')))
            ->orderByDesc('prioridad')->orderBy('fecha')->get()->groupBy('unidad_id');

        $reservas = Reserva::query()->whereIn('propiedad_id', $propiedades)->where('estado', '!=', EstadoReserva::Cancelada->value)
            ->where('llegada', '<=', $fecha)->where('salida', '>=', $fecha)->get(['unidad_id', 'llegada', 'salida']);

        $filas = [];
        foreach ($unidades as $unidad) {
            $suyas = $reservas->where('unidad_id', $unidad->id);
            $fila = [
                'unidad' => $unidad,
                'limpieza' => $unidad->estado->limpieza ?? EstadoLimpieza::Limpia,
                'fuera' => (bool) ($unidad->estado->fuera_de_servicio ?? false),
                'nota' => $unidad->estado->nota ?? null,
                'tareas' => array_values($tareas->get($unidad->id)?->all() ?? []),
                'sale' => $suyas->contains(fn (Reserva $r): bool => $r->salida->format('Y-m-d') === $fecha),
                'llega' => $suyas->contains(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') === $fecha),
                'alojada' => $suyas->contains(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') < $fecha && $r->salida->format('Y-m-d') > $fecha),
            ];
            if (! $this->soloMias || $fila['tareas'] !== []) {
                $filas[] = $fila;
            }
        }
        usort($filas, fn (array $a, array $b): int => [count($b['tareas']) > 0, $b['limpieza'] !== EstadoLimpieza::Limpia] <=> [count($a['tareas']) > 0, $a['limpieza'] !== EstadoLimpieza::Limpia]);

        return $filas;
    }

    public function marcar(int $unidadId, string $estado): void
    {
        $unidad = $this->unidad($unidadId);
        $limpieza = EstadoLimpieza::tryFrom($estado);
        abort_if($limpieza === null, 404);
        app(GestorLimpieza::class)->cambiar($unidad, $limpieza);
        Notification::make()->success()->title(__('limpieza.marcada', ['unidad' => $unidad->nombre, 'estado' => $limpieza->etiqueta()]))->send();
    }

    public function alternarServicio(int $unidadId): void
    {
        $unidad = $this->unidad($unidadId);
        $gestor = app(GestorLimpieza::class);
        $gestor->cambiar($unidad, fueraDeServicio: ! $gestor->estado($unidad)->fuera_de_servicio, nota: '');
    }

    public function empezar(int $tareaId): void
    {
        app(GestorLimpieza::class)->empezar($this->tarea($tareaId));
    }

    public function marcarPunto(int $tareaId, int $indice): void
    {
        app(GestorLimpieza::class)->marcarPunto($this->tarea($tareaId), $indice);
    }

    public function terminar(int $tareaId): void
    {
        $tarea = $this->tarea($tareaId);
        app(GestorLimpieza::class)->terminar($tarea);
        Notification::make()->success()->title(__('limpieza.terminada', ['tarea' => $tarea->titulo]))->send();
    }

    public function incidenciaAction(): Action
    {
        return Action::make('incidencia')
            ->label(__('limpieza.incidencia'))
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('limpieza.incidencia_de', ['unidad' => $this->unidad((int) ($arguments['unidad'] ?? 0))->nombre]))
            ->modalSubmitActionLabel(__('limpieza.incidencia_reportar'))
            ->schema([
                TextInput::make('titulo')->label(__('limpieza.incidencia_titulo'))->helperText(__('limpieza.incidencia_titulo_ayuda'))->required()->maxLength(150),
                Textarea::make('descripcion')->label(__('limpieza.incidencia_descripcion'))->rows(3)->maxLength(2000),
                FileUpload::make('fotos')->label(__('limpieza.fotos'))->multiple()->maxFiles(5)->image()->maxSize(10240)->disk('local')->directory(GestorArchivos::CARPETA_SUBIDAS)->preventFilePathTampering()->visibility('private'),
                Toggle::make('urgente')->label(__('limpieza.urgente')),
                Toggle::make('fuera_de_servicio')->label(__('limpieza.dejar_fuera')),
            ])
            ->action(function (array $data, array $arguments): void {
                $unidad = $this->unidad((int) ($arguments['unidad'] ?? 0));
                $tarea = app(GestorLimpieza::class)->reportarIncidencia($unidad, (string) $data['titulo'], $data['descripcion'] ?? null, (bool) ($data['urgente'] ?? false), (bool) ($data['fuera_de_servicio'] ?? false));
                foreach ((array) ($data['fotos'] ?? []) as $ruta) {
                    app(GestorArchivos::class)->guardarSubida((string) $ruta, ClaseArchivo::FotoTarea, $tarea, $unidad->propiedad_id);
                }
                Notification::make()->success()->title(__('limpieza.incidencia_ok'))->send();
            });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajustesLimpieza')
                ->label(__('limpieza.inspeccion'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->visible(fn (): bool => $this->propiedadUnica() !== null && Acceso::puede(Acceso::usuarioActual(), Acceso::AJUSTES, $this->propiedadUnica()->id))
                ->fillForm(fn (): array => ['inspeccion' => app(GestorLimpieza::class)->pideInspeccion($this->propiedadUnica())])
                ->schema([Toggle::make('inspeccion')->label(__('limpieza.inspeccion'))->helperText(__('limpieza.inspeccion_ayuda'))])
                ->action(function (array $data): void {
                    $propiedad = $this->propiedadUnica();
                    if ($propiedad !== null) {
                        $ajustes = (array) $propiedad->ajustes;
                        $ajustes['limpieza'] = ['inspeccion' => (bool) ($data['inspeccion'] ?? false)];
                        $propiedad->update(['ajustes' => $ajustes]);
                    }
                }),
            Action::make('avisoWhatsapp')
                ->label(__('limpieza.aviso_whatsapp'))
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->visible(fn (): bool => $this->propiedadUnica() !== null)
                ->url(function (): ?string {
                    $propiedad = $this->propiedadUnica();

                    return $propiedad === null ? null : app(GestorLimpieza::class)->enlaceAvisoDia($propiedad, $this->fechaElegida());
                }, shouldOpenInNewTab: true),
        ];
    }

    private function unidad(int $id): Unidad
    {
        return Unidad::query()->with('propiedad')->whereIn('propiedad_id', $this->propiedadesVisibles())->findOrFail($id);
    }

    private function tarea(int $id): Tarea
    {
        return Tarea::query()->with(['unidad', 'propiedad'])->whereIn('propiedad_id', $this->propiedadesVisibles())->findOrFail($id);
    }
}
