<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\Pages;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListGrupos extends ListRecords
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('desdeReservas')->label(__('ui.grupos.desde_reservas'))->icon(Heroicon::OutlinedRectangleStack)->color('gray')
                ->modalDescription(__('ui.grupos.desde_reservas_ayuda'))
                ->visible(fn (): bool => GrupoResource::canCreate())
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', GrupoResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => count(GrupoResource::propiedadesGestionables()) === 1 ? GrupoResource::propiedadesGestionables()[0] : null),
                    Select::make('huesped_id')->label(__('ui.grupos.buscar_cliente'))->required()->native(false)->searchable()->live()
                        ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->clientes($get)->conNombre($search)->orderBy('apellido1')->limit(15)->get()
                            ->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all())
                        ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => $this->clientes($get)->find((int) $value)?->nombreCompleto()),
                    CheckboxList::make('reservas')->label(__('ui.grupos.reservas_elegir'))->required()->bulkToggleable()
                        ->noSearchResultsMessage(__('ui.grupos.sin_reservas_cliente'))
                        ->helperText(fn (Get $get): ?string => filled($get('huesped_id')) && $this->reservasDe($get) === [] ? __('ui.grupos.sin_reservas_cliente') : null)
                        ->options(fn (Get $get): array => $this->reservasDe($get)),
                    TextInput::make('nombre')->label(__('ui.grupos.nombre'))->helperText(__('ui.grupos.nombre_ayuda'))->required()->maxLength(255),
                ])
                ->action(fn (array $data) => $this->crearDesdeReservas($data)),
            CreateAction::make()->visible(fn (): bool => GrupoResource::canCreate()),
        ];
    }

    private function propiedadElegida(Get $get): int
    {
        return Acceso::propiedadElegida($get('propiedad_id'), GrupoResource::propiedadesGestionables());
    }

    /**
     * @return Builder<Huesped>
     */
    private function clientes(Get $get): Builder
    {
        return Limites::huespedesVisibles(Huesped::query(), Acceso::usuarioActual())->where('propiedad_id', $this->propiedadElegida($get));
    }

    /**
     * Las que se pueden juntar: del cliente, vigentes, sin grupo y que este usuario puede cambiar.
     *
     * @return array<int, string>
     */
    private function reservasDe(Get $get): array
    {
        if (blank($get('huesped_id'))) {
            return [];
        }
        $usuario = Acceso::usuarioActual();

        return Limites::reservasVisibles(Reserva::query(), $usuario)->with('unidad')->where('propiedad_id', $this->propiedadElegida($get))->where('titular_id', (int) $get('huesped_id'))
            ->where('sincronizada', false)->where('tipo', TipoReserva::Reserva->value)->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('grupo_id')->whereNull('salida_registrada_en')
            ->orderByDesc('llegada')->limit(100)->get()
            ->filter(fn (Reserva $r): bool => Limites::puedeModificar($usuario, $r))
            ->mapWithKeys(fn (Reserva $r): array => [$r->id => implode(' · ', [$r->numero, (string) ($r->unidad->nombre ?? ''), Formato::fechaCorta($r->llegada).' → '.Formato::fechaCorta($r->salida), Formato::dinero($r->total, $r->moneda)])])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function crearDesdeReservas(array $data): void
    {
        $propiedad = Propiedad::query()->whereIn('id', GrupoResource::propiedadesGestionables())->findOrFail((int) $data['propiedad_id']);
        $huesped = Limites::huespedesVisibles(Huesped::query(), Acceso::usuarioActual())->where('propiedad_id', $propiedad->id)->findOrFail((int) $data['huesped_id']);
        $usuario = Acceso::usuarioActual();
        $ids = Limites::reservasVisibles(Reserva::query(), $usuario)->where('propiedad_id', $propiedad->id)->where('titular_id', $huesped->id)->whereIn('id', array_map('intval', (array) $data['reservas']))->get()
            ->filter(fn (Reserva $r): bool => Limites::puedeModificar($usuario, $r))->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        try {
            $grupo = app(GestorGrupos::class)->crearDesdeReservas($propiedad, (string) $data['nombre'], $huesped->id, $ids);
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        Notification::make()->success()->title(__('ui.grupos.creado_desde_reservas', ['n' => count($ids)]))->send();
        $this->redirect(GrupoResource::getUrl('view', ['record' => $grupo]));
    }
}
