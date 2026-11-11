<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Pages;

use App\Filament\Resources\Tarifas\Schemas\TarifaForm;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTarifa extends EditRecord
{
    protected static string $resource = TarifaResource::class;

    /** @var array<int, mixed> */
    private array $otrasUnidades = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription(__('ui.tarifas.eliminar_ayuda'))
                ->before(function (DeleteAction $action): void {
                    /** @var Tarifa $tarifa */
                    $tarifa = $this->record;
                    $derivadas = $tarifa->derivadas()->pluck('nombre');
                    if ($derivadas->isNotEmpty()) {
                        Notification::make()->danger()->title(__('ui.tarifas.eliminar_con_derivadas', ['tarifas' => $derivadas->implode(', ')]))->send();
                        $action->cancel();
                    }
                })
                ->after(fn () => RegistroAuditoria::anotar('tarifa.eliminar', $this->record, [], (int) $this->record->getAttribute('propiedad_id'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Tarifa $tarifa */
        $tarifa = $this->record;
        $this->otrasUnidades = (array) ($data['otras_unidades'] ?? []);

        return TarifaForm::normalizar($data, $tarifa->propiedad_id, $tarifa);
    }

    protected function afterSave(): void
    {
        /** @var Tarifa $tarifa */
        $tarifa = $this->record;
        TarifaForm::guardarOtrasUnidades($tarifa, $this->otrasUnidades);
        RegistroAuditoria::anotar('tarifa.editar', $this->record, ['campos' => array_keys($this->record->getChanges())], (int) $this->record->getAttribute('propiedad_id'));
    }

    protected function getRedirectUrl(): string
    {
        return TarifaResource::getUrl('index');
    }
}
