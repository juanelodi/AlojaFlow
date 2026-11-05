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
use App\Paises\Paises;
use App\Paises\SugerenciaImpuesto;
use App\Seguridad\Acceso;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

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
                        ->options(fn (Get $get): array => array_map(fn (SugerenciaImpuesto $s): string => $s->region, $this->sugerencias($get('propiedad_id'))))
                        ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => $set('tasa', $this->sugerencias($get('propiedad_id'))[(string) $state]->tasa ?? null)),
                    TextInput::make('tasa')->label(__('ui.impuestos.tasa'))->required()->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')
                        ->helperText(fn (Get $get): ?string => $this->explicacion($this->sugerencias($get('propiedad_id'))[(string) $get('region')] ?? null)),
                ])
                ->action(function (array $data): void {
                    $sugerencia = $this->sugerencias($data['propiedad_id'] ?? null)[(string) $data['region']] ?? null;
                    if ($sugerencia === null) {
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
