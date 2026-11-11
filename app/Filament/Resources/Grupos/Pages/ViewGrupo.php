<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos\Pages;

use App\Caja\GestorTurnos;
use App\Comprobantes\GestorComprobantes;
use App\Comunicacion\VariablesMensaje;
use App\Documentos\GeneradorPdf;
use App\Enums\EstadoGrupo;
use App\Enums\MetodoPago;
use App\Enums\TipoContacto;
use App\Filament\Campos\RangoFechas;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Grupos\AccesoCoordinador;
use App\Grupos\ListaHuespedes;
use App\Models\Caja;
use App\Models\ContactoHuesped;
use App\Models\Grupo;
use App\Models\MensajeGrupo;
use App\Models\RegistroAuditoria;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use App\Support\Csv;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewGrupo extends ViewRecord
{
    protected static string $resource = GrupoResource::class;

    /**
     * Quien abre el grupo ya vio lo que escribió el coordinador.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);
        MensajeGrupo::query()->where('grupo_id', $this->record->getKey())->where('del_coordinador', true)->whereNull('leido_en')->update(['leido_en' => now('UTC')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cobrarGrupo')
                ->label(__('ui.grupos.cobrar'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->modalDescription(__('ui.grupos.cobrar_ayuda'))
                ->visible(fn (Grupo $record): bool => $record->estado !== EstadoGrupo::Cancelado)
                ->fillForm(function (Grupo $record): array {
                    $saldo = app(GestorGrupos::class)->saldo($record);

                    return [
                        'importe' => $saldo['pendiente'] > 0 ? Dinero::aDecimal($saldo['pendiente'], $saldo['moneda']) : null,
                        'fecha' => $record->propiedad->hoy()->format('Y-m-d'),
                        'metodo' => MetodoPago::Efectivo->value,
                        'caja_id' => GestorTurnos::cajaPropuesta($record->propiedad_id),
                    ];
                })
                ->schema(fn (Grupo $record): array => [
                    TextInput::make('importe')->label(__('ui.pagos.importe'))->required()->numeric()->minValue(0.01)->step('any'),
                    DatePicker::make('fecha')->label(__('ui.pagos.fecha'))->required(),
                    Select::make('metodo')->label(__('ui.pagos.metodo'))->options(MetodoPago::opciones())->required()->native(false),
                    Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->native(false)->visible(fn (): bool => Modulos::activo(Modulo::Caja))
                        ->options(fn (): array => Caja::query()->where('propiedad_id', $record->propiedad_id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    TextInput::make('referencia')->label(__('ui.pagos.referencia'))->maxLength(100),
                ])
                ->action(function (Grupo $record, array $data): void {
                    try {
                        app(GestorGrupos::class)->cobrar(
                            $record,
                            Dinero::aUnidadMenor((string) $data['importe'], $record->propiedad->moneda),
                            MetodoPago::from((string) $data['metodo']),
                            CarbonImmutable::parse((string) $data['fecha']),
                            $data['referencia'] ?? null,
                            self::cajaDe($record, $data),
                        );
                        Notification::make()->success()->title(__('ui.pagos.registrado'))->send();
                    } catch (ReservaRechazada $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            EditAction::make()->visible(fn (Grupo $record): bool => GrupoResource::canEdit($record)),
            ActionGroup::make([
                Action::make('darAcceso')
                    ->label(fn (Grupo $record): string => __($record->clave_hash === null ? 'grupos.acceso.dar' : 'grupos.acceso.clave_nueva'))
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Grupo $record): string => __($record->clave_hash === null ? 'grupos.acceso.dar_ayuda' : 'grupos.acceso.clave_nueva_ayuda'))
                    ->visible(fn (Grupo $record): bool => $record->estado !== EstadoGrupo::Cancelado)
                    ->action(function (Grupo $record): void {
                        app(AccesoCoordinador::class)->generarClave($record);
                        $this->replaceMountedAction('verAcceso');
                    }),
                Action::make('verAcceso')
                    ->label(__('grupos.acceso.ver'))
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Grupo $record): bool => $record->clave_hash !== null)
                    ->modalHeading(__('grupos.acceso.titulo'))
                    ->modalContent(fn (Grupo $record) => view('filament.modales.acceso-grupo', self::datosAcceso($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('ui.comun.cerrar')),
                Action::make('apagarAcceso')
                    ->label(fn (Grupo $record): string => __($record->acceso_activo ? 'grupos.acceso.desactivar' : 'grupos.acceso.activar'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->visible(fn (Grupo $record): bool => $record->clave_hash !== null)
                    ->requiresConfirmation()
                    ->action(function (Grupo $record): void {
                        $acceso = app(AccesoCoordinador::class);
                        $record->acceso_activo ? $acceso->desactivar($record) : $acceso->activar($record);
                    }),
            ])->label(__('grupos.acceso.menu'))->icon(Heroicon::OutlinedUser)->button()->color('gray'),
            ActionGroup::make([
                Action::make('generarCuotas')
                    ->label(__('grupos.cuotas.generar'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->modalDescription(__('grupos.cuotas.generar_ayuda'))
                    ->fillForm(fn (Grupo $record): array => ['anticipo_tipo' => 'porcentaje', 'anticipo' => 30, 'fecha_anticipo' => $record->propiedad->hoy()->format('Y-m-d'), 'cuotas' => 1, 'hasta' => $record->primeraLlegada()?->format('Y-m-d')])
                    ->schema([
                        Select::make('anticipo_tipo')->label(__('grupos.cuotas.anticipo_tipo'))->native(false)->required()->live()
                            ->options(['porcentaje' => __('grupos.cuotas.porcentaje'), 'importe' => __('ui.pagos.importe')]),
                        TextInput::make('anticipo')->label(__('grupos.cuotas.anticipo'))->numeric()->minValue(0)->step('any')->required()
                            ->suffix(fn (Get $get): ?string => $get('anticipo_tipo') === 'porcentaje' ? '%' : null),
                        DatePicker::make('fecha_anticipo')->extraInputAttributes(RangoFechas::inicio('cuotas'))->label(__('grupos.cuotas.fecha_anticipo'))->required(),
                        TextInput::make('cuotas')->label(__('grupos.cuotas.cuantas'))->numeric()->integer()->minValue(1)->maxValue(60)->required(),
                        DatePicker::make('hasta')->extraInputAttributes(RangoFechas::fin('cuotas'))->label(__('grupos.cuotas.hasta'))->required()->afterOrEqual('fecha_anticipo'),
                    ])
                    ->action(function (Grupo $record, array $data): void {
                        $gestor = app(GestorGrupos::class);
                        $anticipo = $data['anticipo_tipo'] === 'porcentaje'
                            ? (int) round($gestor->saldo($record)['total'] * min(100.0, (float) $data['anticipo']) / 100)
                            : Dinero::aUnidadMenor((string) $data['anticipo'], $record->propiedad->moneda);
                        $gestor->generarCuotas($record, $anticipo, CarbonImmutable::parse((string) $data['fecha_anticipo'])->startOfDay(), (int) $data['cuotas'], CarbonImmutable::parse((string) $data['hasta'])->startOfDay());
                        Notification::make()->success()->title(__('grupos.cuotas.generadas'))->send();
                    }),
                Action::make('recalcular')
                    ->label(__('grupos.precio.recalcular'))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->requiresConfirmation()
                    ->modalDescription(__('grupos.precio.recalcular_ayuda'))
                    ->action(function (Grupo $record): void {
                        app(GestorGrupos::class)->aplicarPrecios($record, forzar: true);
                        Notification::make()->success()->title(__('grupos.precio.recalculado'))->send();
                    }),
                Action::make('listaPdf')
                    ->label(__('grupos.lista.pdf'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->action(fn (Grupo $record, GeneradorPdf $pdf): StreamedResponse => response()->streamDownload(function () use ($record, $pdf): void {
                        echo $pdf->listaGrupo($record);
                    }, 'lista-'.$record->codigo.'.pdf', ['Content-Type' => 'application/pdf'])),
                Action::make('exportarLista')
                    ->label(__('grupos.lista.exportar'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (Grupo $record, ListaHuespedes $lista): StreamedResponse => response()->streamDownload(function () use ($record, $lista): void {
                        echo $lista->exportar($record);
                    }, 'lista-'.$record->codigo.'.csv', ['Content-Type' => Csv::TIPO_EXCEL])),
                Action::make('importarLista')
                    ->label(__('grupos.lista.importar'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->modalDescription(__('grupos.lista.importar_ayuda'))
                    ->schema([Textarea::make('texto')->label(__('grupos.lista.pegar'))->rows(8)->required()->maxLength(ListaHuespedes::BYTES_MAXIMOS)])
                    ->action(function (Grupo $record, array $data, ListaHuespedes $lista): void {
                        $resultado = $lista->importar($record, (string) $data['texto']);
                        $aviso = Notification::make()->title(trans_choice('grupos.lista.importadas', $resultado['guardadas'], ['n' => $resultado['guardadas']]))
                            ->status($resultado['errores'] === [] ? 'success' : 'warning');
                        if ($resultado['errores'] !== []) {
                            $aviso->body(implode("\n", array_slice($resultado['errores'], 0, 10)))->persistent();
                        }
                        $aviso->send();
                    }),
                Action::make('copiar')
                    ->label(__('grupos.copiar.accion'))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->modalDescription(__('grupos.copiar.ayuda'))
                    ->fillForm(fn (Grupo $record): array => ['nombre' => $record->nombre])
                    ->schema([
                        TextInput::make('nombre')->label(__('ui.grupos.nombre'))->required()->maxLength(150),
                        DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required(),
                    ])
                    ->action(function (Grupo $record, array $data): void {
                        try {
                            $copia = app(GestorGrupos::class)->copiar($record, CarbonImmutable::parse((string) $data['llegada'])->startOfDay(), (string) $data['nombre']);
                        } catch (ReservaRechazada $e) {
                            Notification::make()->danger()->persistent()->title($e->getMessage())->send();

                            return;
                        }
                        Notification::make()->success()->title(__('grupos.copiar.listo'))->send();
                        $this->redirect(GrupoResource::getUrl('view', ['record' => $copia]));
                    }),
                Action::make('confirmarGrupo')
                    ->label(__('grupos.estado.confirmar'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Grupo $record): bool => $record->estado === EstadoGrupo::Tentativo)
                    ->requiresConfirmation()
                    ->modalDescription(__('grupos.estado.confirmar_ayuda'))
                    ->action(fn (Grupo $record) => $this->cambiarEstado($record, EstadoGrupo::Confirmado)),
                Action::make('cancelarGrupo')
                    ->label(__('grupos.estado.cancelar'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Grupo $record): bool => $record->estado !== EstadoGrupo::Cancelado)
                    ->requiresConfirmation()
                    ->modalDescription(__('grupos.estado.cancelar_ayuda'))
                    ->action(fn (Grupo $record) => $this->cambiarEstado($record, EstadoGrupo::Cancelado)),
                Action::make('borrarGrupo')
                    ->label(__('grupos.estado.borrar'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (Grupo $record): bool => GrupoResource::canDelete($record))
                    ->requiresConfirmation()
                    ->modalDescription(__('grupos.estado.borrar_ayuda'))
                    ->action(function (Grupo $record): void {
                        app(GestorComprobantes::class)->borrarGrupo($record);
                        $this->redirect(GrupoResource::getUrl('index'));
                    }),
            ])->label(__('grupos.mas'))->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
        ];
    }

    public function verAccesoAction(): Action
    {
        return Action::make('verAcceso')
            ->modalHeading(__('grupos.acceso.titulo'))
            ->modalContent(fn () => view('filament.modales.acceso-grupo', self::datosAcceso($this->grupo())))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('ui.comun.cerrar'));
    }

    private function cambiarEstado(Grupo $grupo, EstadoGrupo $estado): void
    {
        try {
            app(GestorGrupos::class)->cambiarEstado($grupo, $estado);
            Notification::make()->success()->title(__('grupos.estado.cambiado'))->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->persistent()->title($e->getMessage())->send();
        }
    }

    private function grupo(): Grupo
    {
        /** @var Grupo $grupo */
        $grupo = $this->record;

        return $grupo;
    }

    /**
     * @return array<string, mixed>
     */
    private static function datosAcceso(Grupo $grupo): array
    {
        $idioma = $grupo->coordinador->idioma ?? $grupo->propiedad->idioma;
        $url = app(AccesoCoordinador::class)->url($grupo, $idioma);
        $clave = $grupo->clave_cifrada;
        if ($clave !== null) {
            RegistroAuditoria::anotar('grupo.ver_clave', $grupo, [], $grupo->propiedad_id);
        }
        $telefono = $grupo->coordinador_id === null ? null : ContactoHuesped::query()->where('huesped_id', $grupo->coordinador_id)->where('tipo', TipoContacto::Telefono->value)->value('valor');

        return [
            'grupo' => $grupo,
            'url' => $url,
            'clave' => $clave,
            'whatsapp' => $telefono === null || $url === null ? null : VariablesMensaje::enlaceWhatsapp((string) $telefono, __('grupos.acceso.whatsapp', [
                'grupo' => $grupo->nombre,
                'propiedad' => $grupo->propiedad->nombre,
                'enlace' => $url,
                'clave' => (string) $clave,
            ], $idioma), $grupo->propiedad->pais),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function cajaDe(Grupo $grupo, array $data): ?int
    {
        return filled($data['caja_id'] ?? null) ? Caja::query()->where('propiedad_id', $grupo->propiedad_id)->whereKey((int) $data['caja_id'])->value('id') : null;
    }
}
