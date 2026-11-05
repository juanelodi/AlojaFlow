<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\FuentesIcal;

use App\Demo\ModoDemo;
use App\Enums\EstadoFuenteIcal;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\FuentesIcal\Pages\CreateFuenteIcal;
use App\Filament\Resources\FuentesIcal\Pages\EditFuenteIcal;
use App\Filament\Resources\FuentesIcal\Pages\ListFuentesIcal;
use App\Ical\GestorIcal;
use App\Models\Canal;
use App\Models\FuenteIcal;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Seguridad\ClienteExterno;
use App\Support\Formato;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Los calendarios de Airbnb, Booking.com, Vrbo… que se leen para cerrar esas fechas.
 */
class FuenteIcalResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = FuenteIcal::class;

    protected static ?string $slug = 'calendarios-ical';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 6;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Ical;
    }

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getModelLabel(): string
    {
        return __('ical.fuentes.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ical.fuentes.modelos');
    }

    public static function canCreate(): bool
    {
        return parent::canCreate() && ! ModoDemo::activo();
    }

    protected static function referencias(): array
    {
        return ['unidad_id' => Unidad::class, 'canal_id' => Canal::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('ical.fuentes.ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('unidad_id')->label(__('ical.fuentes.unidad'))->required()->native(false)->searchable()
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', self::propiedadDe($get))->where('activa', true)->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => Unidad::query()->where('propiedad_id', self::propiedadDe($get))->whereKey($value)->value('nombre')),
                TextInput::make('nombre')->label(__('ical.fuentes.nombre'))->helperText(__('ical.fuentes.nombre_ayuda'))->required()->maxLength(100),
                Select::make('canal_id')->label(__('ical.fuentes.canal'))->native(false)
                    ->options(fn (Get $get): array => Canal::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('url')->label(__('ical.fuentes.url'))->helperText(__('ical.fuentes.url_ayuda'))->required()->url()->maxLength(1000)->password()->revealable()->columnSpanFull()
                    ->rule(fn (): Closure => function (string $atributo, mixed $valor, Closure $falla): void {
                        try {
                            app(ClienteExterno::class)->comprobar((string) $valor, permitirHttp: true);
                        } catch (Throwable) {
                            $falla(__('ical.fuentes.url_invalida'));
                        }
                    }),
                TextInput::make('frecuencia_minutos')->label(__('ical.fuentes.frecuencia'))->integer()->minValue(15)->maxValue(1440)->default(30)->required()->suffix('min'),
                Toggle::make('activa')->label(__('ical.fuentes.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'unidad']))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('unidad.nombre')->label(__('ical.fuentes.unidad'))->searchable(),
                TextColumn::make('nombre')->label(__('ical.fuentes.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('ultimo_exito_en')->label(__('ical.fuentes.ultima_lectura'))->formatStateUsing(fn (FuenteIcal $record): string => Formato::momento($record->ultimo_exito_en, $record->propiedad->zona_horaria))->placeholder('—'),
                TextColumn::make('estado')->label(__('ical.fuentes.estado'))->badge()->formatStateUsing(fn (EstadoFuenteIcal $state): string => $state->etiqueta())
                    ->color(fn (EstadoFuenteIcal $state): string => match ($state) {
                        EstadoFuenteIcal::SinLeer => 'gray',
                        EstadoFuenteIcal::Correcta => 'success',
                        EstadoFuenteIcal::Error => 'danger',
                    })
                    ->tooltip(fn (FuenteIcal $record): ?string => $record->ultimo_error),
            ])
            ->emptyStateHeading(__('ical.fuentes.vacio'))
            ->recordActions([
                Action::make('leer')
                    ->label(__('ical.fuentes.leer'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (FuenteIcal $record): bool => self::canEdit($record))
                    ->action(function (FuenteIcal $record, GestorIcal $ical): void {
                        if ($ical->leerFuente($record)) {
                            $ical->revisarChoques($record->propiedad);
                            Notification::make()->success()->title(__('ical.fuentes.leida', ['n' => $record->bloqueos()->whereNull('eliminado_en')->count()]))->send();

                            return;
                        }
                        Notification::make()->danger()->title(__('ical.fuentes.no_leida'))->body(e((string) $record->ultimo_error))->send();
                    }),
                EditAction::make()->visible(fn (FuenteIcal $record): bool => self::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuentesIcal::route('/'),
            'create' => CreateFuenteIcal::route('/create'),
            'edit' => EditFuenteIcal::route('/{record}/edit'),
        ];
    }
}
