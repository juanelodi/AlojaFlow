<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosPropios;

use App\Documentos\CatalogoVariables;
use App\Documentos\GeneradorPdf;
use App\Documentos\MotorDocumentos;
use App\Enums\AlcanceDocumento;
use App\Enums\TipoReserva;
use App\Filament\Campos\RangoFechas;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\DocumentosPropios\Pages\CreateDocumentoPropio;
use App\Filament\Resources\DocumentosPropios\Pages\EditDocumentoPropio;
use App\Filament\Resources\DocumentosPropios\Pages\ListDocumentosPropios;
use App\Models\DocumentoPropio;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Formato;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los documentos que escribe el alojamiento (D-123): un contrato, una hoja de registro, la lista de llegadas.
 */
class DocumentoPropioResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = DocumentoPropio::class;

    protected static ?string $slug = 'documentos-propios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 12;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Documentos;
    }

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('documentos_propios.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('documentos_propios.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $pestanas = [];
        foreach (Formato::idiomas() as $codigo => $nombre) {
            $esDeLaPropiedad = fn (Get $get): bool => Propiedad::query()->whereKey(self::propiedadDe($get))->value('idioma') === $codigo;
            $pestanas[] = Tab::make($nombre)->schema([
                RichEditor::make('cuerpo.'.$codigo)->label(__('documentos_propios.cuerpo'))->required($esDeLaPropiedad)
                    ->toolbarButtons([['bold', 'italic', 'underline'], ['h2', 'h3'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']]),
            ]);
        }

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('documentos_propios.nombre'))->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                Select::make('alcance')->label(__('documentos_propios.alcance'))->helperText(__('documentos_propios.alcance_ayuda'))->options(AlcanceDocumento::opciones())->required()->native(false)
                    ->default(AlcanceDocumento::Reserva->value),
                Toggle::make('activo')->label(__('documentos_propios.activo'))->default(true),
            ]),
            // El texto y, a su lado, las variables: se ven las dos cosas al escribir.
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make()->columnSpan(['lg' => 2])
                    ->description(__('documentos_propios.variables'))
                    ->schema([Tabs::make()->tabs($pestanas)]),
                View::make('filament.partes.variables')->columnSpan(['lg' => 1])
                    ->viewData(fn (Get $get): array => ['grupos' => CatalogoVariables::paraDocumento(self::propiedadDe($get))]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label(__('documentos_propios.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('alcance')->label(__('documentos_propios.alcance'))->formatStateUsing(fn (DocumentoPropio $record): string => $record->alcance->etiqueta()),
                IconColumn::make('activo')->label(__('documentos_propios.activo'))->boolean(),
            ])
            ->emptyStateHeading(__('documentos_propios.vacio'))
            ->emptyStateDescription(__('documentos_propios.vacio_ayuda'))
            ->recordActions([self::accionGenerar(), self::accionVistaPrevia(), EditAction::make()->visible(fn (DocumentoPropio $record): bool => self::canEdit($record))]);
    }

    /**
     * El de varias reservas: se eligen las fechas y de cuáles.
     */
    private static function accionGenerar(): Action
    {
        return Action::make('generar')->label(__('documentos_propios.generar'))->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
            ->visible(fn (DocumentoPropio $record): bool => $record->alcance === AlcanceDocumento::Periodo && Acceso::puede(Acceso::usuarioActual(), Acceso::RESERVAS, $record->propiedad_id))
            ->fillForm(fn (DocumentoPropio $record): array => ['desde' => $record->propiedad->hoy()->format('Y-m-d'), 'hasta' => $record->propiedad->hoy()->format('Y-m-d'), 'criterio' => 'llegadas'])
            ->schema([
                DatePicker::make('desde')->extraInputAttributes(RangoFechas::inicio('periodo'))->label(__('documentos_propios.desde'))->required(),
                DatePicker::make('hasta')->extraInputAttributes(RangoFechas::fin('periodo'))->label(__('documentos_propios.hasta'))->required()->afterOrEqual('desde'),
                Select::make('criterio')->label(__('documentos_propios.criterio'))->required()->native(false)
                    ->options(array_map(fn (string $criterio): string => __('documentos_propios.criterios.'.$criterio), array_combine(MotorDocumentos::CRITERIOS, MotorDocumentos::CRITERIOS))),
            ])
            ->action(function (DocumentoPropio $record, array $data, MotorDocumentos $motor, GeneradorPdf $pdf): ?StreamedResponse {
                $usuario = Acceso::usuarioActual();
                $desde = CarbonImmutable::parse((string) $data['desde']);
                $hasta = CarbonImmutable::parse((string) $data['hasta']);
                $reservas = $motor->reservasDelPeriodo($record->propiedad, $desde, $hasta, (string) $data['criterio'], $usuario);
                if ($reservas->count() > MotorDocumentos::RESERVAS_MAXIMAS) {
                    Notification::make()->danger()->title(__('documentos_propios.demasiadas', ['maximo' => MotorDocumentos::RESERVAS_MAXIMAS]))->send();

                    return null;
                }
                RegistroAuditoria::anotar('documento_propio.generar', $record, ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d'), 'reservas' => $reservas->count()], $record->propiedad_id);
                $cuerpo = $motor->dePeriodo($record, $record->propiedad, $desde, $hasta, $reservas, Acceso::puede($usuario, Acceso::HUESPEDES, $record->propiedad_id));

                return self::descargar($pdf->documentoPropio($cuerpo, $record->nombre, $record->propiedad, null, $record->propiedad->idioma), $record->nombre);
            });
    }

    /**
     * Para ver cómo queda uno de reserva, con la última que se pueda ver.
     */
    private static function accionVistaPrevia(): Action
    {
        return Action::make('vistaPrevia')->label(__('documentos_propios.vista_previa'))->icon(Heroicon::OutlinedEye)->color('gray')
            ->visible(fn (DocumentoPropio $record): bool => $record->alcance === AlcanceDocumento::Reserva && Acceso::puede(Acceso::usuarioActual(), Acceso::RESERVAS, $record->propiedad_id))
            ->action(function (DocumentoPropio $record, MotorDocumentos $motor, GeneradorPdf $pdf): ?StreamedResponse {
                $usuario = Acceso::usuarioActual();
                $reserva = Limites::reservasVisibles(Reserva::query()->where('propiedad_id', $record->propiedad_id)->where('tipo', TipoReserva::Reserva->value), $usuario)->latest('llegada')->first();
                if ($reserva === null) {
                    Notification::make()->warning()->title(__('documentos_propios.sin_reservas_previa'))->send();

                    return null;
                }
                $cuerpo = $motor->deReserva($record, $reserva, Acceso::puede($usuario, Acceso::HUESPEDES, $record->propiedad_id));

                return self::descargar($pdf->documentoPropio($cuerpo, $record->nombre, $record->propiedad, $reserva, $reserva->idioma), $record->nombre);
            });
    }

    public static function descargar(string $contenido, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($contenido): void {
            echo $contenido;
        }, (Str::slug($nombre) ?: 'documento').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentosPropios::route('/'),
            'create' => CreateDocumentoPropio::route('/create'),
            'edit' => EditDocumentoPropio::route('/{record}/edit'),
        ];
    }
}
