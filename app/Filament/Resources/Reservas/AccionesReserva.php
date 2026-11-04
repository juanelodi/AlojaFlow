<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas;

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Dinero\GestorPoliticas;
use App\Documentos\GeneradorPdf;
use App\Enums\CanalEnvio;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\FrecuenciaLimpieza;
use App\Enums\TipoReserva;
use App\Facturacion\ErrorFacturacion;
use App\Facturacion\GestorFacturas;
use App\Limpieza\GestorLimpieza;
use App\Llegada\GestorLlegadas;
use App\Models\Plantilla;
use App\Models\ReceptorFiscal;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Models\SerieFacturacion;
use App\Paises\Paises;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cancelar, reactivar y registrar la entrada y la salida de una reserva propia.
 */
final class AccionesReserva
{
    public static function entrada(): Action
    {
        return Action::make('registrarEntrada')
            ->label(__('ui.reservas.registrar_entrada'))
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('success')
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->tipo === TipoReserva::Reserva
                && $record->estado !== EstadoReserva::Cancelada && $record->entrada_registrada_en === null)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->registrarEntrada($record), __('ui.reservas.entrada_ok')));
    }

    /**
     * Limpiezas fijas durante la estancia. Vale también en subordinadas: es un dato propio.
     */
    /**
     * Vale también en subordinadas: la marca es un dato propio.
     */
    public static function noShow(): Action
    {
        return Action::make('noShow')
            ->label(__('dinero.politicas.no_show_accion'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('warning')
            ->modalDescription(__('dinero.politicas.no_show_ayuda'))
            ->visible(fn (Reserva $record): bool => Acceso::puede(Acceso::usuarioActual(), Acceso::OPERACION, $record->propiedad_id) && app(GestorPoliticas::class)->puedeMarcarNoShow($record))
            ->schema([Textarea::make('nota')->label(__('dinero.politicas.no_show_nota'))->rows(2)->maxLength(1000)])
            ->action(function (Reserva $record, array $data, GestorPoliticas $politicas): void {
                try {
                    $marca = $politicas->marcarNoShow($record, $data['nota'] ?? null);
                    Notification::make()->success()->title(__('dinero.politicas.no_show_ok', ['importe' => Formato::dinero($marca->penalizacion, $marca->moneda)]))->send();
                } catch (ReservaRechazada $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function facturar(): Action
    {
        return Action::make('facturar')
            ->label(__('dinero.facturas.facturar'))
            ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva && $record->estado !== EstadoReserva::Cancelada && Acceso::puede(Acceso::usuarioActual(), Acceso::FACTURACION, $record->propiedad_id))
            ->fillForm(function (Reserva $record): array {
                $guardado = ReceptorFiscal::query()->where('propiedad_id', $record->propiedad_id)->where('huesped_id', $record->titular_id)->latest('id')->first();

                $primeraSerie = SerieFacturacion::query()->where('activa', true)->whereHas('entidad', fn ($q) => $q->where('propiedad_id', $record->propiedad_id))->value('id');

                return [
                    'serie_id' => $primeraSerie,
                    'metodo_pago' => 'PUE',
                    'identificador_fiscal' => $guardado->identificador_fiscal ?? ($record->titular->id_fiscal_empresa ?? $record->titular->id_fiscal_personal ?? null),
                    'nombre' => $guardado->nombre ?? $record->titular?->nombreCompleto(),
                    'regimen_fiscal' => $guardado->regimen_fiscal ?? null,
                    'codigo_postal' => $guardado->codigo_postal ?? null,
                    'uso' => $guardado->uso ?? null,
                    'correo' => $guardado->correo ?? Mensajero::correoDe($record),
                ];
            })
            ->schema(function (Reserva $record): array {
                $catalogos = Paises::de($record->propiedad)->generadorFiscal()?->catalogos() ?? [];
                $series = SerieFacturacion::query()->with('entidad')->where('activa', true)
                    ->whereHas('entidad', fn ($q) => $q->where('propiedad_id', $record->propiedad_id))->get()
                    ->mapWithKeys(fn (SerieFacturacion $s): array => [$s->id => $s->serie.' · '.$s->tipo_documento->etiqueta().' · '.$s->entidad->nombre])->all();
                $campos = [
                    Select::make('serie_id')->label(__('dinero.facturas.serie'))->native(false)->required()->options($series)->helperText($series === [] ? __('dinero.facturas.sin_entidad') : null),
                    TextInput::make('identificador_fiscal')->label(__('dinero.facturas.identificador'))->required()->maxLength(30),
                    TextInput::make('nombre')->label(__('dinero.facturas.nombre'))->required()->maxLength(200),
                    TextInput::make('correo')->label(__('dinero.facturas.correo'))->email()->maxLength(150),
                ];
                if ($catalogos !== []) {
                    $campos[] = Select::make('regimen_fiscal')->label(__('dinero.facturas.regimen'))->native(false)->options($catalogos['regimen'])->required();
                    $campos[] = TextInput::make('codigo_postal')->label(__('dinero.facturas.codigo_postal'))->required()->maxLength(10);
                    $campos[] = Select::make('uso')->label(__('dinero.facturas.uso'))->native(false)->options($catalogos['uso'])->required();
                    $campos[] = Select::make('forma_pago')->label(__('dinero.facturas.forma_pago'))->native(false)->options($catalogos['forma_pago'])->required();
                    $campos[] = Select::make('metodo_pago')->label(__('dinero.facturas.metodo_pago'))->native(false)->options($catalogos['metodo_pago'])->required();
                }

                return $campos;
            })
            ->action(function (Reserva $record, array $data, GestorFacturas $facturas): void {
                $serie = SerieFacturacion::query()->with('entidad')->findOrFail((int) $data['serie_id']);
                $receptor = array_intersect_key($data, array_flip(['identificador_fiscal', 'nombre', 'regimen_fiscal', 'codigo_postal', 'uso', 'correo']));
                try {
                    $factura = $facturas->emitir($record, $serie, $receptor, $data['forma_pago'] ?? null, $data['metodo_pago'] ?? null);
                    Notification::make()->success()->persistent()->title(__('dinero.facturas.emitida_ok', ['folio' => $factura->folio()]))
                        ->actions([Action::make('pdf')->label(__('dinero.facturas.descargar_pdf'))->url(route('archivos.ver', (int) $factura->pdf_archivo_id), shouldOpenInNewTab: true)])->send();
                } catch (ErrorFacturacion $e) {
                    Notification::make()->danger()->persistent()->title($e->getMessage())->send();
                }
            });
    }

    public static function enlaceLlegada(): Action
    {
        return Action::make('enlaceLlegada')
            ->label(__('llegada.panel.enlace'))
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva && $record->estado !== EstadoReserva::Cancelada && Acceso::puede(Acceso::usuarioActual(), Acceso::HUESPEDES, $record->propiedad_id))
            ->modalHeading(__('llegada.panel.enlace'))
            ->modalContent(function (Reserva $record) {
                $idioma = Mensajero::idiomaDe($record);
                $enlace = app(GestorLlegadas::class)->urlDe($record, $idioma);
                $texto = __('llegada.panel.mensaje_whatsapp', ['nombre' => (string) ($record->titular->nombre ?? ''), 'propiedad' => $record->propiedad->nombre, 'enlace' => $enlace], $idioma);

                return view('filament.modales.enlace-llegada', ['enlace' => $enlace, 'whatsapp' => VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($record), $texto)]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('ui.comun.cerrar'));
    }

    public static function mandarMensaje(): Action
    {
        return Action::make('mandarMensaje')
            ->label(__('mensajes.mandar.accion'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva && Acceso::puede(Acceso::usuarioActual(), Acceso::MENSAJES, $record->propiedad_id))
            ->fillForm(fn (Reserva $record): array => [
                'canal' => CanalEnvio::Correo->value,
                'destino_correo' => Mensajero::correoDe($record),
                'destino_telefono' => Mensajero::telefonoDe($record),
            ])
            ->schema(fn (Reserva $record): array => [
                Select::make('plantilla')->label(__('mensajes.mandar.plantilla'))->native(false)->live()->placeholder(__('mensajes.mandar.sin_plantilla'))
                    ->options(Plantilla::query()->where('propiedad_id', $record->propiedad_id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->afterStateUpdated(function (mixed $state, Set $set) use ($record): void {
                        $plantilla = Plantilla::query()->where('propiedad_id', $record->propiedad_id)->find((int) $state);
                        if ($plantilla !== null) {
                            $texto = Mensajero::textoDe($plantilla, $record, Mensajero::idiomaDe($record));
                            $set('asunto', $texto['asunto']);
                            $set('cuerpo', $texto['cuerpo']);
                        }
                    }),
                Select::make('canal')->label(__('mensajes.mandar.canal'))->native(false)->live()->required()
                    ->options([CanalEnvio::Correo->value => CanalEnvio::Correo->etiqueta(), CanalEnvio::WhatsappEnlace->value => CanalEnvio::WhatsappEnlace->etiqueta()]),
                TextInput::make('destino_correo')->label(__('mensajes.mandar.destino_correo'))->email()->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('canal') === CanalEnvio::Correo->value)->required(fn (Get $get): bool => $get('canal') === CanalEnvio::Correo->value),
                TextInput::make('destino_telefono')->label(__('mensajes.mandar.destino_telefono'))->tel()->maxLength(50)
                    ->visible(fn (Get $get): bool => $get('canal') === CanalEnvio::WhatsappEnlace->value),
                TextInput::make('asunto')->label(__('mensajes.plantillas.asunto'))->maxLength(200)
                    ->visible(fn (Get $get): bool => $get('canal') === CanalEnvio::Correo->value)->required(fn (Get $get): bool => $get('canal') === CanalEnvio::Correo->value),
                Textarea::make('cuerpo')->label(__('mensajes.plantillas.cuerpo'))->rows(10)->maxLength(10000)->required(),
            ])
            ->action(function (Reserva $record, array $data, Mensajero $mensajero, Component $livewire): void {
                $idioma = Mensajero::idiomaDe($record);
                if ($data['canal'] === CanalEnvio::WhatsappEnlace->value) {
                    $mensajero->anotarWhatsapp($record, $data['destino_telefono'] ?? null, (string) $data['cuerpo'], $idioma);
                    $enlace = VariablesMensaje::enlaceWhatsapp($data['destino_telefono'] ?? null, (string) $data['cuerpo']);
                    $livewire->js('window.open('.json_encode($enlace).', "_blank", "noopener")');

                    return;
                }
                $envio = $mensajero->mandarCorreo($record, (string) $data['destino_correo'], (string) ($data['asunto'] ?? ''), (string) $data['cuerpo'], $idioma);
                match ($envio->estado) {
                    EstadoEnvio::Enviado => Notification::make()->success()->title(__('mensajes.mandar.enviado'))->send(),
                    EstadoEnvio::Manual => Notification::make()->warning()->persistent()->title(__('mensajes.mandar.manual'))->send(),
                    default => Notification::make()->danger()->title(__('mensajes.mandar.fallo'))->body($envio->ultimo_error)->send(),
                };
            });
    }

    public static function limpiezaEstancia(): Action
    {
        return Action::make('limpiezaEstancia')
            ->label(__('limpieza.regla.accion'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->estado !== EstadoReserva::Cancelada && Acceso::puede(Acceso::usuarioActual(), Acceso::LIMPIEZA, $record->propiedad_id))
            ->fillForm(function (Reserva $record): array {
                $regla = ReglaLimpieza::query()->where('reserva_id', $record->id)->first();
                $dias = [];
                for ($i = 1; $i <= 7; $i++) {
                    if (((int) $regla?->dias_semana & (1 << ($i - 1))) !== 0) {
                        $dias[] = (string) $i;
                    }
                }

                return ['frecuencia' => $regla->frecuencia->value ?? '', 'dias' => $dias];
            })
            ->schema([
                Select::make('frecuencia')->label(__('limpieza.regla.frecuencia'))->native(false)->live()->placeholder(__('limpieza.regla.sin_limpieza'))->options(FrecuenciaLimpieza::opciones()),
                CheckboxList::make('dias')->label(__('limpieza.regla.dias'))->columns(4)->required()
                    ->visible(fn (Get $get): bool => $get('frecuencia') === FrecuenciaLimpieza::Semanal->value || $get('frecuencia') === FrecuenciaLimpieza::Semanal)
                    ->options(function (): array {
                        $opciones = [];
                        foreach (range(1, 7) as $i) {
                            $opciones[(string) $i] = CarbonImmutable::create(2024, 1, $i)?->locale(app()->getLocale())->isoFormat('dddd') ?? (string) $i;
                        }

                        return $opciones;
                    }),
            ])
            ->action(function (Reserva $record, array $data): void {
                $frecuencia = FrecuenciaLimpieza::tryFrom((string) ($data['frecuencia'] ?? ''));
                if ($frecuencia === null) {
                    ReglaLimpieza::query()->where('reserva_id', $record->id)->delete();
                } else {
                    $mascara = 0;
                    foreach ((array) ($data['dias'] ?? []) as $dia) {
                        $mascara |= 1 << ((int) $dia - 1);
                    }
                    ReglaLimpieza::query()->updateOrCreate(['reserva_id' => $record->id], [
                        'frecuencia' => $frecuencia,
                        'dias_semana' => $frecuencia === FrecuenciaLimpieza::Semanal ? $mascara : null,
                        'creada_por' => auth()->id(),
                    ]);
                }
                app(GestorLimpieza::class)->generarTareas($record->propiedad);
                Notification::make()->success()->title(__('limpieza.regla.guardada'))->send();
            });
    }

    public static function confirmar(): Action
    {
        return Action::make('confirmar')
            ->label(__('ui.reservas.confirmar'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado === EstadoReserva::SinConfirmar)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->confirmar($record), __('ui.reservas.confirmada_ok')));
    }

    public static function salida(): Action
    {
        return Action::make('registrarSalida')
            ->label(__('ui.reservas.registrar_salida'))
            ->icon(Heroicon::OutlinedArrowLeftStartOnRectangle)
            ->color('info')
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estaEnCasa())
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->registrarSalida($record), __('ui.reservas.salida_ok')));
    }

    public static function confirmacionPdf(): Action
    {
        return Action::make('confirmacionPdf')
            ->label(__('documentos.descargar_confirmacion'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva)
            ->action(fn (Reserva $record): StreamedResponse => response()->streamDownload(function () use ($record): void {
                echo app(GeneradorPdf::class)->confirmacion($record);
            }, 'reserva-'.str_replace('/', '-', $record->numero).'.pdf', ['Content-Type' => 'application/pdf']));
    }

    public static function deshacerRegistro(): Action
    {
        return Action::make('deshacerRegistro')
            ->label(fn (Reserva $record): string => $record->salida_registrada_en !== null ? __('ui.reservas.deshacer_salida') : __('ui.reservas.deshacer_entrada'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->entrada_registrada_en !== null)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->deshacerRegistro($record), __('ui.reservas.deshecho_ok')));
    }

    public static function cancelar(): Action
    {
        return Action::make('cancelar')
            ->label(__('ui.reservas.cancelar'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Reserva $record): string => trim(__('ui.reservas.cancelar_ayuda').' '.app(GestorPoliticas::class)->resumenCancelacion($record)))
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado !== EstadoReserva::Cancelada && ! $record->estaEnCasa())
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->cancelar($record), __('ui.reservas.cancelada_ok')));
    }

    public static function reactivar(): Action
    {
        return Action::make('reactivar')
            ->label(__('ui.reservas.reactivar'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado === EstadoReserva::Cancelada)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->reactivar($record), __('ui.reservas.reactivada_ok')));
    }

    private static function gestionable(Reserva $reserva): bool
    {
        return ! $reserva->sincronizada && in_array($reserva->propiedad_id, ReservaResource::propiedadesGestionables(), true);
    }

    /**
     * @param  callable(GestorReservas): mixed  $accion
     */
    private static function ejecutar(Reserva $reserva, callable $accion, string $mensaje): void
    {
        try {
            $accion(app(GestorReservas::class));
            Notification::make()->success()->title($mensaje)->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->persistent()->send();
        }
        // La pantalla sigue usando esta misma instancia: se recarga para que muestre el cambio.
        $reserva->refresh();
    }
}
