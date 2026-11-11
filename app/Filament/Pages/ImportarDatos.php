<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Copias\ErrorCopia;
use App\Copias\GestorCopias;
use App\Demo\ModoDemo;
use App\Models\Propiedad;
use App\Reservas\ImportadorDatos;
use App\Seguridad\Acceso;
use App\Support\Csv;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Traer huéspedes y reservas de un archivo (D-127). Solo a propiedades independientes: en una subordinada, los datos son de la principal.
 */
class ImportarDatos extends Page
{
    protected string $view = 'filament.pages.importar-datos';

    protected static ?string $slug = 'importar-datos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 13;

    public ?int $propiedadId = null;

    public string $tipo = ImportadorDatos::RESERVAS;

    /** @var list<array{linea: int, resumen: string, error: string|null, aviso: string|null}> */
    public array $filas = [];

    /**
     * En la demostración pública no: cada importación hace una copia de seguridad, y la base es de todos.
     */
    public static function canAccess(): bool
    {
        return self::gestionables() !== [] && ! ModoDemo::activo();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getNavigationLabel(): string
    {
        return __('importar.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('importar.titulo');
    }

    /**
     * Importar se lleva por delante mucho de una vez: solo quien administra la propiedad.
     *
     * @return list<int>
     */
    private static function gestionables(): array
    {
        return Acceso::propiedadesIndependientes(Acceso::usuarioActual(), Acceso::AJUSTES);
    }

    public function mount(): void
    {
        $propiedades = self::gestionables();
        $this->propiedadId = count($propiedades) === 1 ? $propiedades[0] : null;
    }

    /**
     * @return array<int, string>
     */
    public function propiedades(): array
    {
        return Propiedad::query()->whereIn('id', self::gestionables())->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function propiedadElegida(): ?Propiedad
    {
        return $this->propiedadId !== null && in_array($this->propiedadId, self::gestionables(), true) ? Propiedad::query()->find($this->propiedadId) : null;
    }

    public function updatedTipo(): void
    {
        $this->tipo = $this->tipo === ImportadorDatos::HUESPEDES ? ImportadorDatos::HUESPEDES : ImportadorDatos::RESERVAS;
        $this->filas = [];
    }

    public function updatedPropiedadId(): void
    {
        $this->filas = [];
    }

    public function revisar(string $texto): void
    {
        $propiedad = $this->propiedadElegida();
        $this->filas = $propiedad === null || trim($texto) === '' ? [] : self::resumen(app(ImportadorDatos::class)->revisar($propiedad, $this->tipo, $texto));
    }

    /**
     * Antes hace una copia de seguridad: lo importado no tiene botón de deshacer.
     *
     * @return string lo que queda en el cuadro: las líneas que fallaron, para corregirlas ahí mismo
     */
    public function importar(string $texto): string
    {
        $propiedad = $this->propiedadElegida();
        if ($propiedad === null || trim($texto) === '') {
            return $texto;
        }
        try {
            app(GestorCopias::class)->crear('antes-de-importar');
        } catch (ErrorCopia $e) {
            Notification::make()->danger()->title(__('importar.error_copia'))->body($e->getMessage())->send();

            return $texto;
        }
        $resultado = app(ImportadorDatos::class)->importar($propiedad, $this->tipo, $texto);
        $this->filas = self::resumen($resultado['fallidas']);
        $aviso = Notification::make()->title(trans_choice('importar.importadas', $resultado['importadas'], ['n' => $resultado['importadas']]))
            ->body($resultado['fallidas'] === [] ? null : trans_choice('importar.fallidas', count($resultado['fallidas']), ['n' => count($resultado['fallidas'])]));
        ($resultado['fallidas'] === [] ? $aviso->success() : $aviso->warning())->send();

        return $resultado['pendiente'];
    }

    public function descargarPlantilla(): StreamedResponse
    {
        return Csv::descargaParaExcel(app(ImportadorDatos::class)->plantilla($this->tipo), 'importar-'.$this->tipo.'.csv');
    }

    /**
     * En la pantalla, solo lo que hay que leer: los datos no viajan de ida y vuelta.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{linea: int, resumen: string, error: string|null, aviso: string|null}>
     */
    private static function resumen(array $filas): array
    {
        return array_map(fn (array $fila): array => ['linea' => (int) $fila['linea'], 'resumen' => (string) $fila['resumen'], 'error' => $fila['error'], 'aviso' => $fila['aviso']], $filas);
    }
}
