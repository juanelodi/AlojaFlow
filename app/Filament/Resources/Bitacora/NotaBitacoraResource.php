<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Bitacora;

use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Bitacora\Pages\CreateNotaBitacora;
use App\Filament\Resources\Bitacora\Pages\EditNotaBitacora;
use App\Filament\Resources\Bitacora\Pages\ListNotasBitacora;
use App\Models\NotaBitacora;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que un turno le deja dicho al siguiente (D-080). No toca reservas: sirve igual en una subordinada.
 */
class NotaBitacoraResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = NotaBitacora::class;

    protected static ?string $slug = 'bitacora';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 6;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Bitacora;
    }

    protected static function permiso(): string
    {
        return Acceso::OPERACION;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getModelLabel(): string
    {
        return __('caja.bitacora.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('caja.bitacora.modelos');
    }

    /**
     * La nota de otro no se reescribe, salvo quien administra: es lo que dijo esa persona.
     */
    public static function canEdit(Model $record): bool
    {
        $usuario = Acceso::usuarioActual();

        return parent::canEdit($record) && ((bool) $usuario?->es_administrador || $record->getAttribute('user_id') === $usuario?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                self::campoPropiedad(),
                Textarea::make('texto')->label(__('caja.bitacora.texto'))->helperText(__('caja.bitacora.texto_ayuda'))->required()->rows(4)->maxLength(2000),
                Toggle::make('importante')->label(__('caja.bitacora.importante'))->helperText(__('caja.bitacora.importante_ayuda')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'autor']))
            ->defaultSort('creado_en', 'desc')
            ->columns([
                TextColumn::make('creado_en')->label(__('caja.bitacora.cuando'))->sortable()
                    ->formatStateUsing(fn (NotaBitacora $record): string => Formato::momento($record->creado_en, $record->propiedad->zona_horaria)),
                self::columnaPropiedad(),
                TextColumn::make('autor.name')->label(__('caja.bitacora.quien'))->placeholder('—'),
                TextColumn::make('texto')->label(__('caja.bitacora.texto'))->wrap()->searchable(),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->state(fn (NotaBitacora $record): ?string => match (true) {
                        $record->resuelta_en !== null => __('caja.bitacora.resuelta'),
                        $record->importante => __('caja.bitacora.importante'),
                        default => null,
                    })
                    ->color(fn (NotaBitacora $record): string => $record->resuelta_en !== null ? 'gray' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('pendientes')->label(__('caja.bitacora.pendientes'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('importante', true)->whereNull('resuelta_en'),
                        false: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->emptyStateHeading(__('caja.bitacora.vacio'))
            ->recordActions([
                Action::make('resolver')
                    ->label(__('caja.bitacora.resolver'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (NotaBitacora $record): bool => $record->importante && $record->resuelta_en === null && in_array($record->propiedad_id, self::propiedadesGestionables(), true))
                    ->action(fn (NotaBitacora $record) => $record->forceFill(['resuelta_en' => now('UTC'), 'resuelta_por' => auth()->id()])->save()),
                EditAction::make()->visible(fn (NotaBitacora $record): bool => self::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotasBitacora::route('/'),
            'create' => CreateNotaBitacora::route('/create'),
            'edit' => EditNotaBitacora::route('/{record}/edit'),
        ];
    }
}
