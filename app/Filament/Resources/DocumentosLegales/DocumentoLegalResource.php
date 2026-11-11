<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosLegales;

use App\Enums\TipoDocumentoLegal;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\DocumentosLegales\Pages\CreateDocumentoLegal;
use App\Filament\Resources\DocumentosLegales\Pages\EditDocumentoLegal;
use App\Filament\Resources\DocumentosLegales\Pages\ListDocumentosLegales;
use App\Llegada\GestorDocumentosLegales;
use App\Models\DocumentoLegal;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aviso de privacidad, condiciones, reglamento y política de cancelación. Publicado, no cambia.
 */
class DocumentoLegalResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = DocumentoLegal::class;

    protected static ?string $slug = 'documentos-legales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 8;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getModelLabel(): string
    {
        return __('llegada.documentos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('llegada.documentos.modelos');
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record instanceof DocumentoLegal && ! $record->publicado();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('llegada.documentos.revisar_abogado'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('tipo')->label(__('llegada.documentos.tipo'))->options(TipoDocumentoLegal::opciones())->required()->native(false),
                Select::make('idioma')->label(__('llegada.documentos.idioma'))->options(Formato::idiomas())->required()->native(false)->default(fn (): string => app()->getLocale()),
                TextInput::make('titulo')->label(__('llegada.documentos.titulo'))->required()->maxLength(150),
                Textarea::make('cuerpo')->label(__('llegada.documentos.cuerpo'))->required()->rows(18)->maxLength(60000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (DocumentoLegal $record): ?string => self::canEdit($record) ? self::getUrl('edit', ['record' => $record]) : null)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('titulo')
            ->columns([
                TextColumn::make('titulo')->label(__('llegada.documentos.titulo'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('idioma')->label(__('llegada.documentos.idioma')),
                TextColumn::make('version')->label(__('llegada.documentos.version'))->alignEnd(),
                TextColumn::make('estado')->label(__('llegada.documentos.estado'))->badge()
                    ->state(fn (DocumentoLegal $record): string => match (true) {
                        ! $record->publicado() => __('llegada.documentos.borrador'),
                        $record->vigente() => __('llegada.documentos.vigente'),
                        default => __('llegada.documentos.retirado'),
                    })
                    ->color(fn (DocumentoLegal $record): string => match (true) {
                        ! $record->publicado() => 'warning',
                        $record->vigente() => 'success',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading(__('llegada.documentos.vacio'))
            ->recordActions([
                Action::make('publicar')
                    ->label(__('llegada.documentos.publicar'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->requiresConfirmation()
                    ->modalDescription(__('llegada.documentos.publicar_ayuda'))
                    ->visible(fn (DocumentoLegal $record): bool => self::canEdit($record))
                    ->action(function (DocumentoLegal $record, GestorDocumentosLegales $documentos): void {
                        $documentos->publicar($record);
                        Notification::make()->success()->title(__('llegada.documentos.publicado_ok'))->send();
                    }),
                Action::make('nuevaVersion')
                    ->label(__('llegada.documentos.nueva_version'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->visible(fn (DocumentoLegal $record): bool => $record->vigente() && parent::canEdit($record))
                    ->action(function (DocumentoLegal $record, GestorDocumentosLegales $documentos, Action $action): void {
                        $action->redirect(self::getUrl('edit', ['record' => $documentos->nuevaVersion($record)]));
                    }),
                EditAction::make()->visible(fn (DocumentoLegal $record): bool => self::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentosLegales::route('/'),
            'create' => CreateDocumentoLegal::route('/create'),
            'edit' => EditDocumentoLegal::route('/{record}/edit'),
        ];
    }
}
