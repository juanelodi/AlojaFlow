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
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Plano;
use App\Models\PlanoUnidad;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Las habitaciones sobre el plano, con su estado de hoy de un vistazo (M2).
 */
class PlanoPropiedad extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.plano';

    protected static ?string $slug = 'plano';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 3;

    public ?int $planoId = null;

    public bool $editando = false;

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
        return __('plano.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('plano.titulo');
    }

    public function mount(): void
    {
        $this->planoId = $this->planos()->first()?->id;
    }

    /**
     * @return Collection<int, Plano>
     */
    public function planos(): Collection
    {
        return Plano::query()->whereIn('propiedad_id', $this->propiedadesElegidas())->orderBy('orden')->orderBy('nombre')->get();
    }

    public function plano(): ?Plano
    {
        return Plano::query()->whereIn('propiedad_id', $this->propiedadesVisibles())->find($this->planoId);
    }

    public function puedeEditar(): bool
    {
        $plano = $this->plano();

        return $plano !== null && Acceso::puede(Acceso::usuarioActual(), Acceso::AJUSTES, $plano->propiedad_id);
    }

    /**
     * Cada unidad del plano con lo que pasa hoy en ella.
     *
     * @return list<array{id: int, nombre: string, x: float, y: float, estado: string, limpieza: EstadoLimpieza, fuera: bool}>
     */
    public function unidades(): array
    {
        $plano = $this->plano();
        if ($plano === null) {
            return [];
        }
        $hoy = $plano->propiedad->hoy()->format('Y-m-d');
        $reservas = Reserva::query()->where('propiedad_id', $plano->propiedad_id)->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en')
            ->where('llegada', '<=', $hoy)->where('salida', '>=', $hoy)->get(['unidad_id', 'llegada', 'salida'])->groupBy('unidad_id');
        $filas = [];
        foreach ($plano->posiciones()->with('unidad.estado')->get() as $posicion) {
            /** @var PlanoUnidad $posicion */
            $suyas = $reservas->get($posicion->unidad_id, collect());
            $estado = match (true) {
                $suyas->contains(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') < $hoy && $r->salida->format('Y-m-d') > $hoy) => 'ocupada',
                $suyas->contains(fn (Reserva $r): bool => $r->llegada->format('Y-m-d') === $hoy) => 'llega',
                $suyas->contains(fn (Reserva $r): bool => $r->salida->format('Y-m-d') === $hoy) => 'sale',
                default => 'libre',
            };
            $filas[] = [
                'id' => $posicion->unidad_id,
                'nombre' => $posicion->unidad->nombre,
                'x' => (float) $posicion->x,
                'y' => (float) $posicion->y,
                'estado' => $estado,
                'limpieza' => $posicion->unidad->estado->limpieza ?? EstadoLimpieza::Limpia,
                'fuera' => (bool) ($posicion->unidad->estado->fuera_de_servicio ?? false),
            ];
        }

        return $filas;
    }

    public function moverUnidad(int $unidadId, float $x, float $y): void
    {
        abort_unless($this->puedeEditar(), 403);
        PlanoUnidad::query()->where('plano_id', $this->planoId)->where('unidad_id', $unidadId)
            ->update(['x' => max(0, min(100, round($x, 2))), 'y' => max(0, min(100, round($y, 2)))]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('editar')
                ->label(fn (): string => $this->editando ? __('plano.terminar') : __('plano.editar'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->puedeEditar())
                ->action(fn () => $this->editando = ! $this->editando),
            Action::make('nuevo')
                ->label(__('plano.nuevo'))
                ->icon(Heroicon::OutlinedPlus)
                ->color('gray')
                ->visible(fn (): bool => Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES) !== [])
                ->schema([
                    Select::make('propiedad')->label(__('ui.comun.propiedad'))->required()->native(false)
                        ->options(fn (): array => Propiedad::query()->whereIn('id', Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES))->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES)[0] ?? null),
                    TextInput::make('nombre')->label(__('plano.nombre'))->helperText(__('plano.nombre_ayuda'))->required()->maxLength(100),
                    FileUpload::make('imagen')->label(__('plano.imagen'))->helperText(__('plano.imagen_ayuda'))->image()->maxSize(10240)->disk('local')->directory('subidas-temporales')->visibility('private'),
                ])
                ->action(function (array $data, GestorArchivos $archivos): void {
                    $propiedad = Propiedad::query()->whereIn('id', Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES))->findOrFail((int) $data['propiedad']);
                    $plano = Plano::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => $data['nombre']]);
                    if (filled($data['imagen'] ?? null)) {
                        $plano->update(['archivo_id' => $archivos->guardarSubida((string) $data['imagen'], ClaseArchivo::Plano, $plano, $propiedad->id)->id]);
                    }
                    // Colocadas en cuadrícula, para que se vean todas y solo haya que arrastrarlas.
                    foreach (Unidad::query()->where('propiedad_id', $propiedad->id)->where('activa', true)->orderBy('orden')->orderBy('nombre')->get()->values() as $i => $unidad) {
                        PlanoUnidad::query()->create(['plano_id' => $plano->id, 'unidad_id' => $unidad->id, 'x' => 2 + ($i % 7) * 13.5, 'y' => 4 + (int) floor($i / 7) * 16]);
                    }
                    $this->planoId = $plano->id;
                    $this->editando = true;
                    Notification::make()->success()->title(__('plano.creado'))->send();
                }),
            Action::make('borrar')
                ->label(__('plano.borrar'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->editando && $this->puedeEditar())
                ->action(function (GestorArchivos $archivos): void {
                    $plano = $this->plano();
                    if ($plano !== null) {
                        if ($plano->archivo !== null) {
                            $archivos->eliminar($plano->archivo);
                        }
                        $plano->delete();
                    }
                    $this->planoId = $this->planos()->first()?->id;
                    $this->editando = false;
                }),
        ];
    }
}
