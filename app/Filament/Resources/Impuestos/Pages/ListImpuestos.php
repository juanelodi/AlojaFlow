<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Impuestos\Pages;

use App\Enums\BaseCargo;
use App\Filament\Resources\Impuestos\ImpuestoResource;
use App\Models\Impuesto;
use App\Models\Propiedad;
use App\Paises\CatalogoRegiones;
use App\Paises\DeclaracionEstancias;
use App\Paises\Paises;
use App\Paises\SugerenciaImpuesto;
use App\Seguridad\Acceso;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListImpuestos extends ListRecords
{
    protected static string $resource = ImpuestoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catalogo')
                ->label(__('caja.impuestos.catalogo'))
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('gray')
                ->visible(fn (): bool => $this->propiedadesConCatalogo() !== [])
                ->modalDescription(__('caja.impuestos.catalogo_ayuda'))
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => $this->propiedadesConCatalogo())
                        ->default(fn (): ?int => count($this->propiedadesConCatalogo()) === 1 ? (int) array_key_first($this->propiedadesConCatalogo()) : null)
                        ->visible(fn (): bool => count($this->propiedadesConCatalogo()) > 1),
                    Select::make('region')->label(__('caja.impuestos.catalogo_region'))->required()->native(false)->searchable()->live()
                        ->default(fn (Get $get): ?string => $this->regionDe($get('propiedad_id')))
                        ->options(fn (Get $get): array => array_map(fn (SugerenciaImpuesto $s): string => $s->region, $this->sugerencias($get('propiedad_id'))))
                        ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => $set('tasa', $this->sugerencias($get('propiedad_id'))[(string) $state]->tasa ?? null)),
                    Placeholder::make('tramos')->label(__('autoridad.tasa.tarifa'))
                        ->visible(fn (Get $get): bool => ($this->sugerencias($get('propiedad_id'))[(string) $get('region')] ?? null)?->tramos !== [] && filled($get('region')))
                        ->content(fn (Get $get): string => $this->explicacionTramos($this->sugerencias($get('propiedad_id'))[(string) $get('region')] ?? null))
                        ->helperText(fn (Get $get): ?string => $this->explicacion($this->sugerencias($get('propiedad_id'))[(string) $get('region')] ?? null)),
                    TextInput::make('tasa')->label(__('ui.impuestos.tasa'))->required()->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')
                        ->visible(fn (Get $get): bool => ($this->sugerencias($get('propiedad_id'))[(string) $get('region')] ?? null)?->tramos === [] || blank($get('region')))
                        ->default(fn (Get $get): ?float => $this->sugerencias($get('propiedad_id'))[(string) $this->regionDe($get('propiedad_id'))]->tasa ?? null)
                        ->helperText(fn (Get $get): ?string => $this->explicacion($this->sugerencias($get('propiedad_id'))[(string) $get('region')] ?? null)),
                ])
                ->action(function (array $data): void {
                    $sugerencia = $this->sugerencias($data['propiedad_id'] ?? null)[(string) $data['region']] ?? null;
                    if ($sugerencia === null) {
                        return;
                    }
                    foreach ($sugerencia->tramos as $tramo) {
                        Impuesto::query()->create([
                            'propiedad_id' => $this->propiedadElegida($data['propiedad_id'] ?? null),
                            'codigo' => $sugerencia->codigo,
                            'nombre' => __('caja.impuestos.catalogo_nombre', ['codigo' => $sugerencia->codigo, 'region' => $sugerencia->region]),
                            'base' => BaseCargo::PorPersonaNoche,
                            'importe' => $tramo['importe'],
                            'incluido_en_precio' => false,
                            'vigente_desde' => $tramo['desde'],
                            'vigente_hasta' => $tramo['hasta'],
                            'tope_noches' => $sugerencia->topeNoches,
                            'edad_minima' => $sugerencia->edadMinima,
                            'fijar_al_reservar' => $sugerencia->fijarAlReservar,
                            'temporada_baja_desde' => $sugerencia->temporadaBaja['desde'] ?? null,
                            'temporada_baja_hasta' => $sugerencia->temporadaBaja['hasta'] ?? null,
                            'temporada_baja_descuento' => $sugerencia->temporadaBaja['descuento'] ?? null,
                            'dias_largos_desde' => $sugerencia->diasLargos['desde'] ?? null,
                            'dias_largos_descuento' => $sugerencia->diasLargos['descuento'] ?? null,
                            'activo' => true,
                        ]);
                    }
                    if ($sugerencia->tramos !== []) {
                        Notification::make()->success()->title(__('caja.impuestos.catalogo_listo'))->body(__('autoridad.tasa.lista'))->send();

                        return;
                    }
                    Impuesto::query()->create([
                        'propiedad_id' => $this->propiedadElegida($data['propiedad_id'] ?? null),
                        'codigo' => $sugerencia->codigo,
                        'nombre' => __('caja.impuestos.catalogo_nombre', ['codigo' => $sugerencia->codigo, 'region' => $sugerencia->region]),
                        'base' => BaseCargo::PorcentajeAlojamiento,
                        'tasa' => $data['tasa'],
                        'incluido_en_precio' => false,
                        'activo' => true,
                    ]);
                    Notification::make()->success()->title(__('caja.impuestos.catalogo_listo'))->body(__('caja.impuestos.catalogo_listo_ayuda'))->send();
                }),
            Action::make('declaracion')
                ->label(__('autoridad.tasa.declaracion'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->propiedadesConDeclaracion() !== [])
                ->modalDescription(__('autoridad.tasa.declaracion_ayuda'))
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)
                        ->options(fn (): array => $this->propiedadesConDeclaracion())
                        ->default(fn (): ?int => count($this->propiedadesConDeclaracion()) === 1 ? (int) array_key_first($this->propiedadesConDeclaracion()) : null),
                    TextInput::make('ejercicio')->label(__('autoridad.tasa.ejercicio'))->helperText(__('autoridad.tasa.ejercicio_ayuda'))->required()->numeric()->integer()->minValue(2017)->maxValue(2100)->default(fn (): int => (int) now()->format('Y')),
                    Select::make('periodo')->label(__('autoridad.tasa.periodo'))->required()->native(false)->options(fn (): array => array_combine(DeclaracionEstancias::PERIODOS, array_map(fn (string $p): string => __('autoridad.tasa.periodos.'.$p), DeclaracionEstancias::PERIODOS))),
                    TextInput::make('nif')->label(__('autoridad.tasa.nif'))->required()->maxLength(12),
                    TextInput::make('nombre')->label(__('autoridad.tasa.nombre'))->required()->maxLength(60),
                    Select::make('tipo')->label(__('autoridad.tasa.tipo_establecimiento'))->required()->native(false)->options(fn (): array => array_combine(DeclaracionEstancias::TIPOS, array_map(fn (string $t): string => __('autoridad.tasa.tipos_atc.'.$t), DeclaracionEstancias::TIPOS))),
                    TextInput::make('registro')->label(__('autoridad.tasa.registro'))->helperText(__('autoridad.tasa.registro_ayuda'))->required()->maxLength(15),
                    Select::make('categoria')->label(__('autoridad.tasa.categoria'))->required()->native(false)->options(fn (): array => array_combine(DeclaracionEstancias::CATEGORIAS, array_map(fn (string $c): string => __('autoridad.tasa.categorias.'.$c), DeclaracionEstancias::CATEGORIAS))),
                ])
                ->action(function (array $data): StreamedResponse {
                    $permitidas = array_keys($this->propiedadesConDeclaracion());
                    $propiedad = Propiedad::query()->findOrFail(Acceso::propiedadElegida($data['propiedad_id'] ?? null, $permitidas));
                    $declaracion = Paises::de($propiedad)->declaracionEstancias();
                    abort_if($declaracion === null, 404);
                    $csv = $declaracion->csv($propiedad, (int) $data['ejercicio'], (string) $data['periodo'], ['nif' => (string) $data['nif'], 'nombre' => (string) $data['nombre'], 'tipo' => (string) $data['tipo'], 'registro' => (string) $data['registro'], 'categoria' => (string) $data['categoria']]);

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv;
                    }, 'estancias-'.$data['ejercicio'].'-'.$data['periodo'].'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
                }),
            CreateAction::make()->visible(fn (): bool => ImpuestoResource::canCreate()),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function propiedadesConCatalogo(): array
    {
        return Propiedad::query()->whereIn('id', ImpuestoResource::propiedadesGestionables())->orderBy('nombre')->get()
            ->filter(fn (Propiedad $propiedad): bool => Paises::de($propiedad)->sugerenciasImpuesto() !== [])
            ->pluck('nombre', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private function propiedadesConDeclaracion(): array
    {
        return Propiedad::query()->whereIn('id', ImpuestoResource::propiedadesGestionables())->orderBy('nombre')->get()
            ->filter(fn (Propiedad $propiedad): bool => Paises::de($propiedad)->declaracionEstancias()?->aplica($propiedad) === true)
            ->pluck('nombre', 'id')->all();
    }

    private function explicacionTramos(?SugerenciaImpuesto $sugerencia): string
    {
        if ($sugerencia === null) {
            return '';
        }
        $partes = [];
        foreach ($sugerencia->tramos as $tramo) {
            $importe = Formato::dinero($tramo['importe'], 'EUR');
            $partes[] = match (true) {
                $tramo['desde'] !== null && $tramo['hasta'] !== null => __('autoridad.tasa.tramo_entre', ['importe' => $importe, 'desde' => Formato::fecha(CarbonImmutable::parse($tramo['desde'])), 'hasta' => Formato::fecha(CarbonImmutable::parse($tramo['hasta']))]),
                $tramo['desde'] !== null => __('autoridad.tasa.tramo_desde', ['importe' => $importe, 'desde' => Formato::fecha(CarbonImmutable::parse($tramo['desde']))]),
                default => __('autoridad.tasa.tramo', ['importe' => $importe]),
            };
        }
        if ($sugerencia->topeNoches !== null) {
            $partes[] = __('autoridad.tasa.con_tope', ['noches' => $sugerencia->topeNoches]);
        }
        if ($sugerencia->edadMinima !== null) {
            $partes[] = __('autoridad.tasa.con_edad', ['edad' => $sugerencia->edadMinima]);
        }
        if ($sugerencia->temporadaBaja !== null) {
            $partes[] = __('autoridad.tasa.con_baja', ['descuento' => (int) $sugerencia->temporadaBaja['descuento'], 'desde' => Formato::fecha(CarbonImmutable::parse('2000-'.$sugerencia->temporadaBaja['desde']), 'D MMMM'), 'hasta' => Formato::fecha(CarbonImmutable::parse('2000-'.$sugerencia->temporadaBaja['hasta']), 'D MMMM')]);
        }
        if ($sugerencia->diasLargos !== null) {
            $partes[] = __('autoridad.tasa.con_largos', ['descuento' => (int) $sugerencia->diasLargos['descuento'], 'dia' => $sugerencia->diasLargos['desde']]);
        }

        return implode(' ', $partes);
    }

    private function propiedadElegida(mixed $propiedadId): int
    {
        $permitidas = array_keys($this->propiedadesConCatalogo());

        return Acceso::propiedadElegida($propiedadId ?? (count($permitidas) === 1 ? $permitidas[0] : null), $permitidas);
    }

    /**
     * @return array<string, SugerenciaImpuesto> por el nombre de la región
     */
    private function sugerencias(mixed $propiedadId): array
    {
        $propiedad = Propiedad::query()->find($this->propiedadElegida($propiedadId));
        if ($propiedad === null) {
            return [];
        }
        $lista = [];
        foreach (Paises::de($propiedad)->sugerenciasImpuesto() as $sugerencia) {
            $lista[$sugerencia->region] = $sugerencia;
        }

        return $lista;
    }

    /**
     * La región de la propiedad, si está en el catálogo (D-092).
     */
    private function regionDe(mixed $propiedadId): ?string
    {
        $region = CatalogoRegiones::nombre(Propiedad::query()->whereKey($this->propiedadElegida($propiedadId))->value('subdivision'));

        return isset($this->sugerencias($propiedadId)[(string) $region]) ? $region : null;
    }

    private function explicacion(?SugerenciaImpuesto $sugerencia): ?string
    {
        if ($sugerencia === null) {
            return null;
        }
        $partes = [__('caja.impuestos.catalogo_ley', ['ley' => $sugerencia->ley])];
        if ($sugerencia->tasaPlataforma !== null) {
            $partes[] = __('caja.impuestos.catalogo_plataforma', ['tasa' => rtrim(rtrim(number_format($sugerencia->tasaPlataforma, 2, '.', ''), '0'), '.')]);
        }
        $partes[] = __($sugerencia->verificada ? 'caja.impuestos.catalogo_verificada' : 'caja.impuestos.catalogo_sin_verificar', ['fuente' => $sugerencia->fuente, 'fecha' => $sugerencia->fecha]);

        return implode(' ', $partes);
    }
}
