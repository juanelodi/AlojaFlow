<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\EntidadesFiscales;

use App\Demo\ModoDemo;
use App\Enums\ModoFiscal;
use App\Enums\TipoDocumentoFiscal;
use App\Facturacion\ConectorSw;
use App\Facturacion\ErrorFacturacion;
use App\Facturacion\FabricaPac;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\EntidadesFiscales\Pages\CreateEntidadFiscal;
use App\Filament\Resources\EntidadesFiscales\Pages\EditEntidadFiscal;
use App\Filament\Resources\EntidadesFiscales\Pages\ListEntidadesFiscales;
use App\Models\EntidadFiscal;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Paises\Paises;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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

/**
 * Quién emite las facturas y con qué series.
 */
class EntidadFiscalResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = EntidadFiscal::class;

    protected static ?string $slug = 'datos-fiscales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 9;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Facturacion;
    }

    protected static function permiso(): string
    {
        return Acceso::FACTURACION;
    }

    public static function getModelLabel(): string
    {
        return __('dinero.entidades.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dinero.entidades.modelos');
    }

    /**
     * @return array<string, string>
     */
    private static function regimenes(Get $get): array
    {
        $propiedad = Propiedad::query()->find(self::propiedadDe($get));

        return $propiedad === null ? [] : (Paises::de($propiedad)->generadorFiscal()?->catalogos()['regimen'] ?? []);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('dinero.entidades.ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('dinero.entidades.nombre'))->required()->maxLength(200),
                TextInput::make('identificador_fiscal')->label(__('dinero.entidades.identificador'))->required()->maxLength(30),
                TextInput::make('pais')->label(__('dinero.entidades.pais'))->required()->length(2)->default('MX'),
                Select::make('regimen_fiscal')->label(__('dinero.entidades.regimen'))->native(false)->options(fn (Get $get): array => self::regimenes($get))
                    ->required(fn (Get $get): bool => self::regimenes($get) !== [])->visible(fn (Get $get): bool => self::regimenes($get) !== []),
                TextInput::make('codigo_postal')->label(__('dinero.entidades.codigo_postal'))->maxLength(10),
                TextInput::make('domicilio')->label(__('dinero.entidades.domicilio'))->maxLength(255)->columnSpanFull(),
                Select::make('modo')->label(__('dinero.entidades.modo'))->options(ModoFiscal::opciones())->required()->native(false)->default(ModoFiscal::Pruebas->value),
            ]),
            Section::make(__('dinero.entidades.series'))->columnSpanFull()->schema([
                Repeater::make('series')->label(__('dinero.entidades.series'))->hiddenLabel()->relationship()->addActionLabel(__('dinero.entidades.anadir_serie'))->defaultItems(1)->minItems(1)
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextInput::make('serie')->label(__('dinero.entidades.serie'))->required()->maxLength(25)->alphaNum()->default('A'),
                        Select::make('tipo_documento')->label(__('dinero.entidades.tipo_documento'))->options(TipoDocumentoFiscal::opciones())->required()->native(false)->default(TipoDocumentoFiscal::Factura->value),
                        TextInput::make('siguiente_numero')->label(__('dinero.entidades.siguiente'))->integer()->minValue(1)->default(1)->required(),
                        Toggle::make('activa')->label(__('dinero.entidades.activa'))->default(true),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->columns([
                TextColumn::make('nombre')->label(__('dinero.entidades.nombre'))->searchable(),
                TextColumn::make('identificador_fiscal')->label(__('dinero.entidades.identificador')),
                self::columnaPropiedad(),
            ])
            ->emptyStateHeading(__('dinero.entidades.vacio'))
            ->recordActions([self::accionConectarPac(), EditAction::make()->visible(fn (EntidadFiscal $record): bool => self::canEdit($record))]);
    }

    /**
     * El token se guarda solo si funciona: un error de dedo se ve aquí y no al timbrar la primera factura de un huésped.
     */
    private static function accionConectarPac(): Action
    {
        return Action::make('conectarPac')
            ->label(__('dinero.pac.conectar'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn (EntidadFiscal $record): bool => ! ModoDemo::activo() && self::canEdit($record) && Paises::de($record->propiedad)->generadorFiscal() !== null)
            ->modalDescription(fn (EntidadFiscal $record): string => __('dinero.pac.ayuda', ['modo' => $record->modo->etiqueta()]))
            ->fillForm(fn (EntidadFiscal $record): array => ['pac' => ((array) $record->configuracion)['pac'] ?? null])
            ->schema([
                Select::make('pac')->label(__('dinero.pac.proveedor'))->native(false)->live()->placeholder(__('dinero.pac.ninguno'))->options([FabricaPac::SW => 'SW Sapien']),
                TextInput::make('token')->label(__('dinero.pac.token'))->helperText(fn (EntidadFiscal $record): ?string => filled(((array) $record->configuracion)['pac_token'] ?? null) ? __('dinero.pac.token_guardado') : null)
                    ->password()->revealable()->autocomplete('new-password')->maxLength(2000)
                    ->visible(fn (Get $get): bool => filled($get('pac')))
                    ->required(fn (Get $get, EntidadFiscal $record): bool => filled($get('pac')) && blank(((array) $record->configuracion)['pac_token'] ?? null)),
            ])
            ->action(function (EntidadFiscal $record, array $data): void {
                $configuracion = (array) $record->configuracion;
                if (blank($data['pac'] ?? null)) {
                    unset($configuracion['pac'], $configuracion['pac_token']);
                    $record->update(['configuracion' => $configuracion]);
                    Notification::make()->success()->title(__('dinero.pac.quitado'))->send();

                    return;
                }
                $token = filled($data['token'] ?? null) ? (string) $data['token'] : (string) ($configuracion['pac_token'] ?? '');
                try {
                    $timbres = (new ConectorSw($token, $record->modo))->probar();
                } catch (ErrorFacturacion $e) {
                    Notification::make()->danger()->persistent()->title(__('dinero.pac.no_conecto'))->body($e->getMessage())->send();

                    return;
                }
                $record->update(['configuracion' => ['pac' => (string) $data['pac'], 'pac_token' => $token] + $configuracion]);
                RegistroAuditoria::anotar('factura.pac_conectado', $record, ['pac' => (string) $data['pac'], 'modo' => $record->modo->value], $record->propiedad_id);
                Notification::make()->success()->title(__('dinero.pac.conectado'))->body($timbres === null ? null : __('dinero.pac.timbres', ['timbres' => $timbres]))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntidadesFiscales::route('/'),
            'create' => CreateEntidadFiscal::route('/create'),
            'edit' => EditEntidadFiscal::route('/{record}/edit'),
        ];
    }
}
