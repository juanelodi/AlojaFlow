<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propietarios\RelationManagers;

use App\Models\Propietario;
use App\Models\Unidad;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las unidades de un dueño, con la comisión de cada una si es distinta. Una unidad tiene un solo dueño.
 */
class UnidadesRelationManager extends RelationManager
{
    protected static string $relationship = 'unidades';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('propietarios.unidades.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label(__('ui.comun.unidad')),
                TextColumn::make('comision_propietario')->label(__('propietarios.unidades.comision'))->alignEnd()
                    ->formatStateUsing(fn (Unidad $record): string => Formato::tasa($record->comision_propietario))->placeholder(fn (): string => Formato::tasa($this->propietario()->comision_porcentaje)),
            ])
            ->emptyStateHeading(__('propietarios.unidades.vacio'))
            ->headerActions([
                Action::make('asignarUnidades')
                    ->label(__('propietarios.unidades.asignar'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalDescription(__('propietarios.unidades.asignar_ayuda'))
                    ->schema([
                        Select::make('unidades')->label(__('propietarios.unidades.elegir'))->multiple()->required()->native(false)
                            ->options(fn (): array => $this->disponibles()),
                    ])
                    ->action(function (array $data): void {
                        // Por el modelo no: las de otro dueño o de otra propiedad no están en la lista, y aquí se vuelve a acotar.
                        Unidad::query()->whereKey(array_keys(array_intersect_key($this->disponibles(), array_flip(array_map('intval', (array) $data['unidades'])))))->update(['propietario_id' => $this->propietario()->id]);
                    }),
            ])
            ->recordActions([
                Action::make('comisionUnidad')
                    ->label(__('propietarios.unidades.comision'))
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->modalDescription(__('propietarios.unidades.comision_ayuda'))
                    ->fillForm(fn (Unidad $record): array => ['comision' => $record->comision_propietario])
                    ->schema([TextInput::make('comision')->label(__('propietarios.unidades.comision'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')])
                    ->action(function (Unidad $record, array $data): void {
                        $record->update(['comision_propietario' => blank($data['comision']) ? null : $data['comision']]);
                    }),
                Action::make('quitarUnidad')
                    ->label(__('propietarios.unidades.quitar'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Unidad $record): void {
                        $record->update(['propietario_id' => null, 'comision_propietario' => null]);
                    }),
            ]);
    }

    /**
     * Las de esta propiedad que no tienen dueño. Las de una subordinada no: ahí los números los lleva la principal.
     *
     * @return array<int, string>
     */
    private function disponibles(): array
    {
        return Unidad::query()->where('propiedad_id', $this->propietario()->propiedad_id)->where('sincronizada', false)->whereNull('propietario_id')->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    private function propietario(): Propietario
    {
        /** @var Propietario $propietario */
        $propietario = $this->getOwnerRecord();

        return $propietario;
    }
}
