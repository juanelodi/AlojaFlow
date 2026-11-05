<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\RelationManagers;

use App\Demo\ModoDemo;
use App\Models\ConexionCompartida;
use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use App\Sincronizacion\AlojaFlow\GestorConexiones;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las instalaciones que pueden copiar esta propiedad (D-073). Solo el código se ve una vez: aquí se guarda su huella.
 */
class ConexionesRelationManager extends RelationManager
{
    protected static string $relationship = 'conexionesCompartidas';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Propiedad && $ownerRecord->esIndependiente() && Modulos::activo(Modulo::Subordinacion) && ! ModoDemo::activo()
            && (bool) Acceso::usuarioActual()?->es_administrador;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sincronizacion.compartir.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(__('sincronizacion.compartir.ayuda'))
            ->recordTitleAttribute('nombre')
            ->paginated(false)
            ->emptyStateHeading(__('sincronizacion.compartir.sin_conexiones'))
            ->columns([
                TextColumn::make('nombre')->label(__('sincronizacion.compartir.nombre')),
                IconColumn::make('incluir_documentos')->label(__('sincronizacion.compartir.documentos'))->boolean(),
                TextColumn::make('ultimo_uso_en')->label(__('sincronizacion.compartir.ultimo_uso'))
                    ->formatStateUsing(fn (ConexionCompartida $record): string => Formato::momento($record->ultimo_uso_en))->placeholder('—'),
                TextColumn::make('estado')->label(__('sincronizacion.compartir.estado'))->badge()
                    ->state(fn (ConexionCompartida $record): string => $record->revocada_en === null ? __('sincronizacion.compartir.activa') : __('sincronizacion.compartir.revocada'))
                    ->color(fn (ConexionCompartida $record): string => $record->revocada_en === null ? 'success' : 'gray'),
            ])
            ->headerActions([
                Action::make('compartir')
                    ->label(__('sincronizacion.compartir.crear'))
                    ->icon(Heroicon::OutlinedShare)
                    ->schema([
                        TextInput::make('nombre')->label(__('sincronizacion.compartir.nombre'))->helperText(__('sincronizacion.compartir.nombre_ayuda'))->required()->maxLength(100),
                        Toggle::make('incluir_documentos')->label(__('sincronizacion.compartir.documentos'))->helperText(__('sincronizacion.compartir.documentos_ayuda'))->default(false),
                    ])
                    ->action(function (array $data, GestorConexiones $conexiones): void {
                        /** @var Propiedad $propiedad */
                        $propiedad = $this->getOwnerRecord();
                        $nueva = $conexiones->crear($propiedad, (string) $data['nombre'], (bool) ($data['incluir_documentos'] ?? false));
                        $this->replaceMountedAction('verCodigo', ['codigo' => $nueva['codigo']]);
                    }),
            ])
            ->recordActions([
                Action::make('revocar')
                    ->label(__('sincronizacion.compartir.revocar'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('sincronizacion.compartir.revocar_texto'))
                    ->visible(fn (ConexionCompartida $record): bool => $record->revocada_en === null)
                    ->action(fn (ConexionCompartida $record, GestorConexiones $conexiones) => $conexiones->revocar($record)),
            ]);
    }

    public function verCodigoAction(): Action
    {
        return Action::make('verCodigo')
            ->modalHeading(__('sincronizacion.compartir.codigo_titulo'))
            ->modalContent(fn (array $arguments) => view('filament.conexion-codigo', ['codigo' => (string) ($arguments['codigo'] ?? '')]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('sincronizacion.compartir.codigo_listo'));
    }
}
