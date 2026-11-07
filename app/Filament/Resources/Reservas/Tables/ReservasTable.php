<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Tables;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Filament\Campos\RangoFechas;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Servicios\Buscador;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservasTable
{
    /** Son para elegir de un vistazo: una lista más larga ya no se lee. */
    private const OPCIONES_MAXIMAS = 300;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['unidad', 'titular', 'propiedad']))
            ->searchable()
            ->searchUsing(fn (Builder $query, string $search): Builder => Buscador::reservas($query, $search))
            ->searchPlaceholder(__('ui.reservas.buscar'))
            ->columns([
                // En el teléfono, una sola tarjeta con lo importante; desde tableta, las columnas.
                ViewColumn::make('tarjeta')->label(__('ui.reservas.modelo'))->view('filament.tablas.reserva-tarjeta')->hiddenFrom('sm'),
                TextColumn::make('numero')->label(__('ui.reservas.numero'))->visibleFrom('sm'),
                TextColumn::make('titular.apellido1')->label(__('ui.comun.huesped'))->visibleFrom('sm')
                    ->formatStateUsing(fn (mixed $state, Reserva $record): string => $record->titular?->nombreCompleto() ?? '—')
                    ->description(fn (Reserva $record): ?string => $record->titular?->domicilio_ciudad),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))->visibleFrom('sm')
                    ->visible(fn (): bool => count(ReservaResource::propiedadesPermitidas()) > 1),
                TextColumn::make('unidad.nombre')->label(__('ui.comun.unidad'))->visibleFrom('sm'),
                TextColumn::make('llegada')->label(__('ui.comun.llegada'))->visibleFrom('sm')
                    ->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->llegada)),
                TextColumn::make('salida')->label(__('ui.comun.salida'))->visibleFrom('sm')
                    ->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->salida)),
                TextColumn::make('noches')->label(__('ui.comun.noches'))->alignEnd()->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('personas')->label(__('ui.comun.personas'))->alignEnd()->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()->visibleFrom('sm')
                    ->formatStateUsing(fn (Reserva $record): string => $record->eliminada_en_principal_en ? __('ui.reservas.eliminada') : ($record->tipo === TipoReserva::Bloqueo ? TipoReserva::Bloqueo->etiqueta() : $record->estado->etiqueta()))
                    ->color(fn (Reserva $record): string => match (true) {
                        $record->eliminada_en_principal_en !== null => 'danger',
                        $record->tipo === TipoReserva::Bloqueo => 'gray',
                        $record->estado === EstadoReserva::Cancelada => 'danger',
                        $record->estado === EstadoReserva::SinConfirmar => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('total')->label(__('ui.comun.total'))->alignEnd()->visibleFrom('sm')->toggleable()
                    ->formatStateUsing(fn (Reserva $record): string => Formato::dinero($record->total, $record->moneda)),
                TextColumn::make('pendiente')->label(__('ui.comun.pendiente'))->alignEnd()->visibleFrom('sm')
                    ->formatStateUsing(fn (Reserva $record): string => $record->pendiente > 0 ? Formato::dinero($record->pendiente, $record->moneda) : '—'),
                // Oculta al empezar, para que la tabla quepa; también se busca y se filtra por él.
                TextColumn::make('origen_texto')->label(__('ui.comun.origen'))->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('tarifa_nombre')->label(__('ui.reservas.tarifa'))->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('titular.nacionalidad_texto')->label(__('ui.huespedes.nacionalidad'))->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('titular.domicilio_region')->label(__('ui.huespedes.region'))->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('titular.domicilio_pais_texto')->label(__('ui.huespedes.pais'))->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('comentario')->label(__('ui.reservas.comentario'))->visibleFrom('sm')->toggleable(isToggledHiddenByDefault: true)->limit(60)->placeholder('—'),
            ])
            ->defaultSort('llegada', 'desc')
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->filters([
                // Sin nada que ocultar, no estorba. Se oculta aunque la lista recuerde sus filtros de antes de que hubiera algo que ocultar: por eso no es una casilla.
                TernaryFilter::make('vigentes')->label(__('ui.reservas.eliminada'))
                    ->visible(fn (): bool => ReservaResource::hayEliminadasEnPrincipal())
                    ->placeholder(__('ui.reservas.solo_vigentes'))->trueLabel(__('ui.reservas.con_eliminadas'))->falseLabel(__('ui.reservas.solo_eliminadas'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query,
                        false: fn (Builder $query): Builder => $query->whereNotNull('eliminada_en_principal_en'),
                        blank: fn (Builder $query): Builder => $query->whereNull('eliminada_en_principal_en'),
                    ),
                SelectFilter::make('propiedad_id')->label(__('ui.comun.propiedad'))->multiple()
                    ->visible(fn (): bool => count(ReservaResource::propiedadesPermitidas()) > 1)
                    ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesPermitidas())->orderBy('nombre')->pluck('nombre', 'id')->all()),
                SelectFilter::make('estado')->label(__('ui.comun.estado'))->multiple()->options(EstadoReserva::opciones()),
                SelectFilter::make('tipo')->label(__('ui.reservas.tipo'))->options(TipoReserva::opciones()),
                SelectFilter::make('unidad_id')->label(__('ui.comun.unidad'))->multiple()->searchable()
                    ->options(fn (): array => Unidad::query()->whereIn('propiedad_id', ReservaResource::propiedadesPermitidas())->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                self::filtroDeTexto('origen_texto', __('ui.comun.origen')),
                self::filtroDeTexto('tarifa_nombre', __('ui.reservas.tarifa')),
                self::filtroDelTitular('nacionalidad_texto', __('ui.huespedes.nacionalidad')),
                self::filtroDelTitular('domicilio_pais_texto', __('ui.huespedes.pais')),
                self::filtroDelTitular('domicilio_region', __('ui.huespedes.region')),
                self::filtroDelTitular('domicilio_ciudad', __('ui.huespedes.ciudad')),
                Filter::make('con_saldo')->label(__('ui.reservas.con_saldo'))->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('pendiente', '>', 0)),
                self::filtroDeFechas('llegada', 'llegada', __('ui.reservas.filtro_llegada')),
                self::filtroDeFechas('salida', 'salida', __('ui.reservas.filtro_salida')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Reserva $record): bool => ReservaResource::canEdit($record)),
            ])
            ->recordUrl(fn (Reserva $record): string => ReservaResource::getUrl('view', ['record' => $record]));
    }

    /**
     * Las opciones salen de las reservas que quien mira puede ver: ni el origen de una reserva ajena se asoma aquí.
     */
    private static function filtroDeTexto(string $campo, string $etiqueta): SelectFilter
    {
        return SelectFilter::make($campo)->label($etiqueta)->multiple()->searchable()
            ->options(fn (): array => self::comoOpciones(ReservaResource::getEloquentQuery()->whereNotNull($campo)->where($campo, '!=', '')->distinct()->orderBy($campo)->limit(self::OPCIONES_MAXIMAS)->pluck($campo)->all()));
    }

    private static function filtroDelTitular(string $campo, string $etiqueta): SelectFilter
    {
        return SelectFilter::make('titular_'.$campo)->label($etiqueta)->multiple()->searchable()
            ->options(fn (): array => self::comoOpciones(Huesped::query()
                ->whereIn('id', ReservaResource::getEloquentQuery()->whereNotNull('titular_id')->select('titular_id'))
                ->whereNotNull($campo)->where($campo, '!=', '')->distinct()->orderBy($campo)->limit(self::OPCIONES_MAXIMAS)->pluck($campo)->all()))
            ->query(fn (Builder $query, array $data): Builder => ($data['values'] ?? []) === [] ? $query
                : $query->whereHas('titular', fn (Builder $h): Builder => $h->whereIn($campo, $data['values'])));
    }

    private static function filtroDeFechas(string $nombre, string $campo, string $etiqueta): Filter
    {
        return Filter::make($nombre)->label($etiqueta)->columnSpan(['sm' => 2])->columns(2)
            ->schema([
                DatePicker::make('desde')->extraInputAttributes(RangoFechas::inicio('filtro_'.$nombre))->label(__('ui.comun.'.$campo).': '.__('ui.indicadores.desde')),
                DatePicker::make('hasta')->extraInputAttributes(RangoFechas::fin('filtro_'.$nombre))->label(__('ui.comun.'.$campo).': '.__('ui.indicadores.hasta')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->where($campo, '>=', $desde))
                ->when($data['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->where($campo, '<=', $hasta)))
            ->indicateUsing(function (array $data) use ($etiqueta): array {
                $partes = array_filter([
                    ($data['desde'] ?? null) ? Formato::fechaNumerica(CarbonImmutable::parse((string) $data['desde'])) : null,
                    ($data['hasta'] ?? null) ? Formato::fechaNumerica(CarbonImmutable::parse((string) $data['hasta'])) : null,
                ]);

                return $partes === [] ? [] : [$etiqueta.': '.implode(' → ', $partes)];
            });
    }

    /**
     * @param  array<int, mixed>  $valores
     * @return array<string, string>
     */
    private static function comoOpciones(array $valores): array
    {
        $textos = array_map(fn (mixed $valor): string => (string) $valor, $valores);

        return $textos === [] ? [] : array_combine($textos, $textos);
    }
}
