<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\RelationManagers;

use App\Grupos\CalendarioPagos;
use App\Models\CuotaGrupo;
use App\Models\Grupo;
use App\Support\Dinero;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * El calendario de pagos, a mano. Para hacerlo de una vez está «Generar cuotas» en el grupo.
 */
class CuotasRelationManager extends RelationManager
{
    protected static string $relationship = 'cuotas';

    /** @var array<int, array{cuota: CuotaGrupo, pagado: int, estado: string}>|null */
    private ?array $filas = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('grupos.cuotas.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('vence_en')
            ->paginated(false)
            ->columns([
                TextColumn::make('concepto')->label(__('grupos.cuotas.concepto'))->wrap(),
                TextColumn::make('vence_en')->label(__('grupos.cuotas.vence'))->formatStateUsing(fn (CuotaGrupo $record): string => Formato::fecha($record->vence_en)),
                TextColumn::make('importe')->label(__('ui.pagos.importe'))->alignEnd()->formatStateUsing(fn (CuotaGrupo $record): string => Formato::dinero($record->importe, $this->moneda())),
                TextColumn::make('pagado')->label(__('ui.comun.pagado'))->alignEnd()->state(fn (CuotaGrupo $record): string => Formato::dinero($this->filaDe($record)['pagado'] ?? 0, $this->moneda())),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->state(fn (CuotaGrupo $record): string => __('grupos.cuotas.estados.'.($this->filaDe($record)['estado'] ?? 'pendiente')))
                    ->color(fn (CuotaGrupo $record): string => match ($this->filaDe($record)['estado'] ?? 'pendiente') {
                        'pagada' => 'success',
                        'atrasada' => 'danger',
                        'parcial' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading(__('grupos.cuotas.vacio'))
            ->headerActions([
                Action::make('agregarCuota')
                    ->label(__('grupos.cuotas.agregar'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema($this->campos())
                    ->action(function (array $data): void {
                        CuotaGrupo::query()->create($this->datos($data) + ['grupo_id' => $this->grupo()->id]);
                    }),
            ])
            ->recordActions([
                Action::make('editarCuota')
                    ->label(__('grupos.cuotas.editar'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (CuotaGrupo $record): array => ['concepto' => $record->concepto, 'importe' => Dinero::aDecimal($record->importe, $this->moneda()), 'vence_en' => $record->vence_en->format('Y-m-d')])
                    ->schema($this->campos())
                    ->action(function (CuotaGrupo $record, array $data): void {
                        $record->update($this->datos($data));
                    }),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Field>
     */
    private function campos(): array
    {
        return [
            TextInput::make('concepto')->label(__('grupos.cuotas.concepto'))->required()->maxLength(150),
            TextInput::make('importe')->label(__('ui.pagos.importe'))->required()->numeric()->minValue(0.01)->step('any'),
            DatePicker::make('vence_en')->label(__('grupos.cuotas.vence'))->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function datos(array $data): array
    {
        return ['concepto' => (string) $data['concepto'], 'importe' => Dinero::aUnidadMenor((string) $data['importe'], $this->moneda()), 'vence_en' => (string) $data['vence_en']];
    }

    /**
     * @return array{cuota: CuotaGrupo, pagado: int, estado: string}|null
     */
    private function filaDe(CuotaGrupo $cuota): ?array
    {
        $this->filas ??= collect(CalendarioPagos::de($this->grupo())['cuotas'])->keyBy(fn (array $fila): int => $fila['cuota']->id)->all();

        return $this->filas[$cuota->id] ?? null;
    }

    private function moneda(): string
    {
        return $this->grupo()->propiedad->moneda;
    }

    private function grupo(): Grupo
    {
        /** @var Grupo $grupo */
        $grupo = $this->getOwnerRecord();

        return $grupo;
    }
}
