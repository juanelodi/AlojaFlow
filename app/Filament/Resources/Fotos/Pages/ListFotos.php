<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Fotos\Pages;

use App\Archivos\GestorArchivos;
use App\Comprobantes\ComprobanteRechazado;
use App\Filament\Resources\Fotos\FotoResource;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Publico\GestorFotos;
use App\Seguridad\Acceso;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListFotos extends ListRecords
{
    protected static string $resource = FotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('subir')->label(__('portal.fotos.subir'))->icon(Heroicon::OutlinedArrowUpTray)
                ->modalDescription(__('portal.fotos.subir_ayuda'))
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', FotoResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => count(FotoResource::propiedadesGestionables()) === 1 ? FotoResource::propiedadesGestionables()[0] : null),
                    Select::make('tarifa_id')->label(__('portal.fotos.de'))->native(false)->placeholder(__('portal.fotos.de_propiedad'))
                        ->options(fn (Get $get): array => Tarifa::query()->where('propiedad_id', (int) $get('propiedad_id'))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    FileUpload::make('archivos')->label(__('portal.fotos.archivos'))->required()->multiple()->image()->maxFiles(20)->maxSize(5120)
                        ->disk('local')->directory(GestorArchivos::CARPETA_SUBIDAS)->storeFiles(true)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                ])
                ->action(function (array $data, GestorFotos $fotos): void {
                    $propiedad = Propiedad::query()->findOrFail(Acceso::propiedadElegida($data['propiedad_id'] ?? null, FotoResource::propiedadesGestionables()));
                    $tarifa = filled($data['tarifa_id'] ?? null) ? Tarifa::query()->where('propiedad_id', $propiedad->id)->findOrFail((int) $data['tarifa_id'])->id : null;
                    $subidas = 0;
                    $errores = [];
                    foreach ((array) ($data['archivos'] ?? []) as $ruta) {
                        try {
                            $fotos->recibir((string) Storage::disk('local')->get((string) $ruta), $propiedad, $tarifa);
                            $subidas++;
                        } catch (ComprobanteRechazado $e) {
                            $errores[] = $e->getMessage();
                        } finally {
                            Storage::disk('local')->delete((string) $ruta);
                        }
                    }
                    $aviso = Notification::make()->title(__('portal.fotos.subidas', ['cuantas' => $subidas]));
                    ($errores === [] ? $aviso->success() : $aviso->warning()->body(implode(' ', array_unique($errores))))->send();
                }),
        ];
    }
}
