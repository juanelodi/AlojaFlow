<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\EnviosAutoridad\Pages;

use App\Autoridades\ErrorAutoridad;
use App\Autoridades\GestorEnvios;
use App\Autoridades\Ses\AjustesSes;
use App\Autoridades\Ses\ClienteSes;
use App\Demo\ModoDemo;
use App\Filament\Resources\EnviosAutoridad\EnvioAutoridadResource;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ListEnviosAutoridad extends ListRecords
{
    protected static string $resource = EnvioAutoridadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ahora')->label(__('autoridad.ahora'))->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                ->visible(fn (): bool => $this->conectadas() !== [])
                ->action(function (GestorEnvios $envios): void {
                    $enviadas = 0;
                    $respondidas = 0;
                    foreach ($this->conectadas() as $propiedad) {
                        $resultado = $envios->correr($propiedad);
                        $enviadas += $resultado['enviadas'];
                        $respondidas += $resultado['respondidas'];
                    }
                    Notification::make()->success()->title(__('autoridad.corrida', ['enviadas' => $enviadas, 'respondidas' => $respondidas]))->send();
                }),
            Action::make('conectar')->label(__('autoridad.conectar'))->icon(Heroicon::OutlinedLink)
                ->visible(fn (): bool => ! ModoDemo::activo() && $this->configurables() !== [])
                ->modalDescription(__('autoridad.conectar_ayuda'))
                ->fillForm(function (): array {
                    $id = (int) (array_key_first($this->configurables()) ?? 0);

                    return ['propiedad_id' => $id ?: null] + $this->valoresDe($id);
                })
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => $this->configurables())
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            foreach ($this->valoresDe((int) $state) as $campo => $valor) {
                                $set($campo, $valor);
                            }
                        }),
                    Select::make('modo')->label(__('autoridad.modo'))->helperText(__('autoridad.modo_ayuda'))->required()->native(false)
                        ->options([AjustesSes::MODO_PRUEBAS => __('autoridad.modos.pruebas'), AjustesSes::MODO_PRODUCCION => __('autoridad.modos.produccion')]),
                    TextInput::make('arrendador')->label(__('autoridad.arrendador'))->helperText(__('autoridad.arrendador_ayuda'))->required()->maxLength(10),
                    TextInput::make('establecimiento')->label(__('autoridad.establecimiento'))->required()->maxLength(10),
                    TextInput::make('usuario')->label(__('autoridad.usuario'))->required()->maxLength(100)->autocomplete('off'),
                    TextInput::make('contrasena')->label(__('autoridad.contrasena'))->password()->revealable()->autocomplete('new-password')->maxLength(200)
                        ->helperText(fn (Get $get): ?string => filled((new AjustesSes($this->propiedad($get('propiedad_id'))))->contrasena()) ? __('autoridad.contrasena_guardada') : null)
                        ->required(fn (Get $get): bool => blank((new AjustesSes($this->propiedad($get('propiedad_id'))))->contrasena())),
                    Toggle::make('internet')->label(__('autoridad.internet'))->helperText(__('autoridad.internet_ayuda')),
                ])
                ->action(function (array $data): void {
                    $propiedad = $this->propiedad($data['propiedad_id'] ?? null);
                    $ajustes = new AjustesSes($propiedad);
                    $contrasena = filled($data['contrasena'] ?? null) ? (string) $data['contrasena'] : (string) $ajustes->contrasena();
                    try {
                        (new ClienteSes((string) $data['modo'], (string) $data['usuario'], $contrasena, (string) $data['arrendador']))->catalogo('TIPO_PAGO');
                    } catch (ErrorAutoridad $e) {
                        Notification::make()->danger()->persistent()->title(__('autoridad.no_conecto'))->body($e->getMessage())->send();

                        return;
                    }
                    $ajustes->guardar([
                        'modo' => (string) $data['modo'],
                        'arrendador' => trim((string) $data['arrendador']),
                        'establecimiento' => trim((string) $data['establecimiento']),
                        'usuario' => trim((string) $data['usuario']),
                        'internet' => (bool) ($data['internet'] ?? false),
                        // Lo anterior no se manda de golpe: desde aquí en adelante.
                        'desde' => $ajustes->desde()?->toIso8601String() ?? now('UTC')->toIso8601String(),
                    ], filled($data['contrasena'] ?? null) ? (string) $data['contrasena'] : null);
                    RegistroAuditoria::anotar('autoridad.conectar', $propiedad, ['servicio' => 'ses', 'modo' => (string) $data['modo']], $propiedad->id);
                    Notification::make()->success()->title(__('autoridad.conectado'))->send();
                }),
            Action::make('desconectar')->label(__('autoridad.desconectar'))->icon(Heroicon::OutlinedXMark)->color('gray')
                ->visible(fn (): bool => ! ModoDemo::activo() && $this->configurablesConectadas() !== [])
                ->requiresConfirmation()->modalDescription(__('autoridad.desconectar_ayuda'))
                ->schema([Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->options(fn (): array => $this->configurablesConectadas())])
                ->action(function (array $data): void {
                    $propiedad = Propiedad::query()->findOrFail(Acceso::propiedadElegida($data['propiedad_id'] ?? null, array_keys($this->configurablesConectadas())));
                    (new AjustesSes($propiedad))->desconectar();
                    RegistroAuditoria::anotar('autoridad.desconectar', $propiedad, ['servicio' => 'ses'], $propiedad->id);
                    Notification::make()->success()->title(__('autoridad.desconectado'))->send();
                }),
        ];
    }

    /**
     * @return list<Propiedad>
     */
    private function conectadas(): array
    {
        return Propiedad::query()->whereIn('id', EnvioAutoridadResource::propiedadesGestionables())->get()
            ->filter(fn (Propiedad $propiedad): bool => (new AjustesSes($propiedad))->conectada())->values()->all();
    }

    /**
     * Conectar es de quien configura la propiedad; ver y corregir, de recepción.
     *
     * @return array<int, string>
     */
    private function configurables(): array
    {
        return Propiedad::query()->whereIn('id', array_intersect(EnvioAutoridadResource::propiedadesGestionables(), Acceso::propiedades(Acceso::usuarioActual(), Acceso::AJUSTES)))
            ->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private function configurablesConectadas(): array
    {
        return Propiedad::query()->whereIn('id', array_keys($this->configurables()))->orderBy('nombre')->get()
            ->filter(fn (Propiedad $propiedad): bool => (new AjustesSes($propiedad))->conectada())->pluck('nombre', 'id')->all();
    }

    private function propiedad(mixed $id): Propiedad
    {
        return Propiedad::query()->findOrFail(Acceso::propiedadElegida($id, array_keys($this->configurables())));
    }

    /**
     * @return array<string, mixed>
     */
    private function valoresDe(int $id): array
    {
        if (! isset($this->configurables()[$id])) {
            return [];
        }
        $ajustes = new AjustesSes($this->propiedad($id));

        return ['modo' => $ajustes->modo(), 'arrendador' => $ajustes->arrendador(), 'establecimiento' => $ajustes->establecimiento(), 'usuario' => $ajustes->usuario(), 'internet' => (bool) $ajustes->internet()];
    }
}
