<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\EnviosAutoridad;

use App\Autoridades\Ses\FichaViajero;
use App\Enums\EstadoEnvioAutoridad;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\EnviosAutoridad\Pages\ListEnviosAutoridad;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\EnvioAutoridad;
use App\Models\Propiedad;
use App\Paises\Paises;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que se comunica al registro de viajeros (D-100). Solo existe para propiedades de un país que lo tiene. Nada se crea a mano: lo decide `GestorEnvios`.
 */
class EnvioAutoridadResource extends RecursoDePropiedad
{
    protected static ?string $model = EnvioAutoridad::class;

    protected static ?string $slug = 'registro-de-viajeros';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 30;

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function propiedadesGestionables(): array
    {
        $usuario = Acceso::usuarioActual();
        $permitidas = array_diff(array_intersect(parent::propiedadesGestionables(), Acceso::propiedades($usuario, Acceso::DATOS)), Limites::propiedadesSoloPropias($usuario));

        return Propiedad::query()->whereIn('id', $permitidas)->whereNotNull('paquete_pais')->get()
            ->filter(fn (Propiedad $propiedad): bool => Paises::de($propiedad)->registroViajeros() !== null)
            ->pluck('id')->values()->all();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getModelLabel(): string
    {
        return __('autoridad.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('autoridad.titulo');
    }

    public static function getNavigationBadge(): ?string
    {
        $porAtender = EnvioAutoridad::query()->whereIn('propiedad_id', self::propiedadesGestionables())
            ->whereIn('estado', [EstadoEnvioAutoridad::Rechazado->value, EstadoEnvioAutoridad::FaltanDatos->value])->count();

        return $porAtender > 0 ? (string) $porAtender : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['reserva', 'propiedad']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reserva.numero')->label(__('ui.reservas.numero'))
                    ->url(fn (EnvioAutoridad $record): ?string => $record->reserva !== null ? ReservaResource::getUrl('view', ['record' => $record->reserva]) : null),
                TextColumn::make('tipo')->label(__('autoridad.tipo'))->formatStateUsing(fn (string $state): string => __('autoridad.tipos.'.$state)),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->formatStateUsing(fn (EstadoEnvioAutoridad $state): string => $state->etiqueta())
                    ->color(fn (EstadoEnvioAutoridad $state): string => $state->color()),
                TextColumn::make('detalle')->label(__('autoridad.detalle'))->wrap()->state(fn (EnvioAutoridad $record): ?string => self::detalle($record)),
                TextColumn::make('vence_en')->label(__('autoridad.vence'))
                    ->state(fn (EnvioAutoridad $record): ?string => in_array($record->estado, [EstadoEnvioAutoridad::Pendiente, EstadoEnvioAutoridad::FaltanDatos], true) && $record->vence_en !== null ? Formato::momento($record->vence_en, $record->propiedad->zona_horaria) : null)
                    ->color(fn (EnvioAutoridad $record): ?string => $record->vence_en?->isPast() ? 'danger' : null),
                TextColumn::make('codigo_comunicacion')->label(__('autoridad.codigo'))->toggleable(isToggledHiddenByDefault: true),
                self::columnaPropiedad(),
            ])
            ->filters([SelectFilter::make('estado')->label(__('ui.comun.estado'))->options(EstadoEnvioAutoridad::opciones())])
            ->emptyStateHeading(__('autoridad.vacio'))
            ->recordActions([
                self::accionCompletar(),
                Action::make('reintentar')->label(__('autoridad.reintentar'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (EnvioAutoridad $record): bool => $record->estado === EstadoEnvioAutoridad::Rechazado || ($record->estado === EstadoEnvioAutoridad::Pendiente && $record->proximo_intento_en !== null))
                    ->action(function (EnvioAutoridad $record): void {
                        $record->update(['estado' => EstadoEnvioAutoridad::Pendiente, 'proximo_intento_en' => null, 'lote' => null, 'orden' => null]);
                        Notification::make()->success()->title(__('autoridad.reintentado'))->send();
                    }),
            ]);
    }

    /**
     * Lo que falta se pide aquí, para este envío, y no toca la ficha del huésped: en una subordinada ni se podría. Se guarda cifrado y se usa para los siguientes envíos
     * de la misma reserva.
     */
    private static function accionCompletar(): Action
    {
        return Action::make('completar')->label(__('autoridad.completar'))->icon(Heroicon::OutlinedPencilSquare)->color('warning')
            ->visible(fn (EnvioAutoridad $record): bool => $record->estado === EstadoEnvioAutoridad::FaltanDatos || $record->estado === EstadoEnvioAutoridad::Rechazado)
            ->modalDescription(__('autoridad.completar_ayuda'))
            ->fillForm(function (EnvioAutoridad $record): array {
                $valores = [];
                foreach (self::porCorregir($record) as [$clave, $campo, $ficha]) {
                    $valores[$clave][$campo] = $ficha->valor($campo);
                }

                return ['correcciones' => $valores];
            })
            ->schema(function (EnvioAutoridad $record): array {
                $campos = [];
                foreach (self::porCorregir($record) as [$clave, $campo, $ficha]) {
                    $campos[] = TextInput::make('correcciones.'.$clave.'.'.$campo)
                        ->label($ficha->nombreVisible().': '.__('autoridad.campos.'.$campo))
                        ->helperText(__('autoridad.ayudas.'.$campo) !== 'autoridad.ayudas.'.$campo ? __('autoridad.ayudas.'.$campo) : null)
                        ->maxLength(250);
                }

                return $campos;
            })
            ->action(function (EnvioAutoridad $record, array $data): void {
                /** @var array<string, array<string, mixed>> $nuevas */
                $nuevas = (array) ($data['correcciones'] ?? []);
                $guardadas = (array) $record->correcciones;
                foreach ($nuevas as $clave => $campos) {
                    foreach ($campos as $campo => $valor) {
                        if (filled($valor)) {
                            $guardadas[$clave][$campo] = (string) $valor;
                        }
                    }
                }
                $record->update(['correcciones' => $guardadas, 'estado' => EstadoEnvioAutoridad::Pendiente, 'faltan' => null, 'proximo_intento_en' => null, 'lote' => null, 'orden' => null]);
                Notification::make()->success()->title(__('autoridad.completado'))->send();
            });
    }

    /**
     * Lo que dice que falta. Si la autoridad la rechazó, no se sabe qué campo fue hasta leer su mensaje: lo que más suele fallar.
     *
     * @return list<array{0: string, 1: string, 2: FichaViajero}>
     */
    private static function porCorregir(EnvioAutoridad $record): array
    {
        $lista = [];
        foreach (self::personas($record) as $clave => $ficha) {
            $campos = $record->estado === EstadoEnvioAutoridad::Rechazado
                ? ['documento_numero', 'soporte', 'municipio', 'codigo_postal', 'parentesco']
                : array_column(array_filter((array) $record->faltan, fn (array $fila): bool => $fila['persona'] === $clave), 'campo');
            foreach ($campos as $campo) {
                $lista[] = [$clave, $campo, $ficha];
            }
        }

        return $lista;
    }

    /**
     * @return array<string, FichaViajero>
     */
    private static function personas(EnvioAutoridad $record): array
    {
        if ($record->reserva === null) {
            return [];
        }
        $personas = [];
        foreach (FichaViajero::deReserva($record->reserva, (array) $record->correcciones) as $ficha) {
            $personas[$ficha->clave] = $ficha;
        }

        return $personas;
    }

    private static function detalle(EnvioAutoridad $record): ?string
    {
        if ($record->estado === EstadoEnvioAutoridad::FaltanDatos && filled($record->faltan)) {
            $personas = self::personas($record);
            $partes = [];
            foreach ((array) $record->faltan as $fila) {
                $nombre = isset($personas[$fila['persona']]) ? $personas[$fila['persona']]->nombreVisible() : __('autoridad.titular');
                $partes[$nombre][] = __('autoridad.campos.'.$fila['campo']);
            }

            return collect($partes)->map(fn (array $campos, string $nombre): string => $nombre.': '.implode(', ', $campos))->implode('. ');
        }

        return $record->ultimo_error;
    }

    public static function getPages(): array
    {
        return ['index' => ListEnviosAutoridad::route('/')];
    }
}
