<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas;

use App\Caja\GestorTurnos;
use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Dinero\GestorPoliticas;
use App\Documentos\GeneradorPdf;
use App\Documentos\MotorDocumentos;
use App\Enums\AlcanceDocumento;
use App\Enums\CanalEnvio;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\FrecuenciaLimpieza;
use App\Enums\TipoReserva;
use App\Filament\Resources\DocumentosPropios\DocumentoPropioResource;
use App\Limpieza\GestorLimpieza;
use App\Llegada\GestorLlegadas;
use App\Models\Caja;
use App\Models\DocumentoPropio;
use App\Models\Plantilla;
use App\Models\RegistroAuditoria;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\Pasarelas;
use App\Pagos\GestorEnlacesPago;
use App\Reservas\CodigosAcceso;
use App\Reservas\GestorEstancias;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Dinero;
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
            ->visible(fn (Reserva $record): bool => self::marcable($record) && $record->tipo === TipoReserva::Reserva
                && $record->estado !== EstadoReserva::Cancelada && $record->entradaEn() === null)
            ->action(fn (Reserva $record) => self::marcar($record, fn (GestorEstancias $g) => $g->registrarEntrada($record, Acceso::usuarioActual()), __('ui.reservas.entrada_ok')));
    }

    /**
     * Limpiezas fijas durante la estancia. Vale también en subordinadas: es un dato propio.
     */
    /**
     * Vale también en subordinadas: la marca es un dato propio.
     */
    /**
     * El código de la cerradura o de la caja de llaves (D-156). Vale también en subordinadas: es un dato propio.
     */
    public static function codigoAcceso(): Action
    {
        return Action::make('codigoAcceso')
            ->label(__('ui.reservas.codigo_acceso'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva && $record->estado !== EstadoReserva::Cancelada
                && Acceso::puede(Acceso::usuarioActual(), Acceso::RESERVAS, $record->propiedad_id))
            ->modalDescription(__('ui.reservas.codigo_acceso_ayuda'))
            ->fillForm(fn (Reserva $record): array => ['codigo' => CodigosAcceso::propio($record)])
            ->schema(fn (Reserva $record): array => [
                TextInput::make('codigo')->label(__('ui.reservas.codigo_acceso'))->maxLength(CodigosAcceso::LARGO_MAXIMO)->autocomplete('off')
                    ->helperText(fn (): ?string => ($fijo = CodigosAcceso::deUnidad($record->unidad_id)) === null ? null : __('ui.reservas.codigo_acceso_fijo', ['codigo' => $fijo]))
                    ->suffixAction(
                        Action::make('generarCodigo')->label(__('ui.reservas.codigo_generar'))->tooltip(__('ui.reservas.codigo_generar'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->action(fn (Set $set) => $set('codigo', CodigosAcceso::generar()))
                    ),
            ])
            ->action(function (Reserva $record, array $data): void {
                CodigosAcceso::guardar($record, $data['codigo'] ?? null);
                Notification::make()->success()->title(__('ui.reservas.codigo_acceso_ok'))->send();
            });
    }

    public static function noShow(): Action
    {
        return Action::make('noShow')
            ->label(__('dinero.politicas.no_show_accion'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('warning')
            ->modalDescription(__('dinero.politicas.no_show_ayuda'))
            ->visible(fn (Reserva $record): bool => Acceso::puede(Acceso::usuarioActual(), Acceso::RESERVAS, $record->propiedad_id) && app(GestorPoliticas::class)->puedeMarcarNoShow($record))
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

    public static function enlaceLlegada(): Action
    {
        return Action::make('enlaceLlegada')
            ->label(__('llegada.panel.enlace'))
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => Modulos::activo(Modulo::Llegada) && $record->tipo === TipoReserva::Reserva && $record->estado !== EstadoReserva::Cancelada && Acceso::puede(Acceso::usuarioActual(), Acceso::HUESPEDES, $record->propiedad_id))
            ->modalHeading(__('llegada.panel.enlace'))
            ->modalContent(function (Reserva $record) {
                $idioma = Mensajero::idiomaDe($record);
                $enlace = app(GestorLlegadas::class)->urlDe($record, $idioma);
                $texto = __('llegada.panel.mensaje_whatsapp', ['nombre' => (string) ($record->titular->nombre ?? ''), 'propiedad' => $record->propiedad->nombre, 'enlace' => $enlace], $idioma);

                return view('filament.modales.enlace-llegada', ['enlace' => $enlace, 'whatsapp' => VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($record), $texto, $record->propiedad->pais)]);
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
            ->visible(fn (Reserva $record): bool => Modulos::activo(Modulo::Mensajes) && $record->tipo === TipoReserva::Reserva && Acceso::puede(Acceso::usuarioActual(), Acceso::MENSAJES, $record->propiedad_id))
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
                    $enlace = VariablesMensaje::enlaceWhatsapp($data['destino_telefono'] ?? null, (string) $data['cuerpo'], $record->propiedad->pais);
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

    /**
     * Un enlace para cobrar lo que se decida: un anticipo, un extra, un daño (D-085).
     */
    public static function enlacePago(): Action
    {
        return Action::make('enlacePago')
            ->label(__('operacion.enlaces.crear'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->modalDescription(__('operacion.enlaces.crear_ayuda'))
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && Modulos::activo(Modulo::Pagos) && $record->tipo === TipoReserva::Reserva
                && $record->estado !== EstadoReserva::Cancelada && (new Pasarelas($record->propiedad))->disponibles() !== [])
            ->fillForm(fn (Reserva $record): array => [
                'importe' => $record->pendiente > 0 ? Dinero::aDecimal($record->pendiente, $record->moneda) : null,
                'dias' => 3,
                'caja_id' => GestorTurnos::cajaPropuesta($record->propiedad_id),
            ])
            ->schema(fn (Reserva $record): array => [
                TextInput::make('importe')->label(__('ui.pagos.importe'))->required()->numeric()->minValue(0.01)->step('any')->prefix($record->moneda),
                TextInput::make('concepto')->label(__('operacion.enlaces.concepto_campo'))->helperText(__('operacion.enlaces.concepto_ayuda'))->required()->maxLength(150),
                TextInput::make('dias')->label(__('operacion.enlaces.dias'))->required()->integer()->minValue(1)->maxValue(60),
                Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->native(false)->visible(fn (): bool => Modulos::activo(Modulo::Caja))
                    ->options(fn (): array => Caja::query()->where('propiedad_id', $record->propiedad_id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
            ])
            ->action(function (Reserva $record, array $data, GestorEnlacesPago $enlaces): void {
                $cajaId = filled($data['caja_id'] ?? null) ? Caja::query()->where('propiedad_id', $record->propiedad_id)->whereKey((int) $data['caja_id'])->value('id') : null;
                $enlace = $enlaces->crear($record, Dinero::aUnidadMenor((string) $data['importe'], $record->moneda), (string) $data['concepto'], (int) $data['dias'], $cajaId);
                Notification::make()->success()->persistent()->title(__('operacion.enlaces.creado'))->body((string) $enlaces->url($enlace, Mensajero::idiomaDe($record)))->send();
            });
    }

    public static function limpiezaEstancia(): Action
    {
        return Action::make('limpiezaEstancia')
            ->label(__('limpieza.regla.accion'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => Modulos::activo(Modulo::Limpieza) && $record->estado !== EstadoReserva::Cancelada && Acceso::puede(Acceso::usuarioActual(), Acceso::LIMPIEZA, $record->propiedad_id))
            ->fillForm(function (Reserva $record): array {
                $regla = ReglaLimpieza::query()->where('reserva_id', $record->id)->first();
                $dias = [];
                for ($i = 1; $i <= 7; $i++) {
                    if (((int) $regla?->dias_semana & (1 << ($i - 1))) !== 0) {
                        $dias[] = (string) $i;
                    }
                }

                return ['frecuencia' => $regla->frecuencia->value ?? '', 'dias' => $dias, 'fechas' => (array) $regla?->fechas];
            })
            ->schema(fn (Reserva $record): array => [
                Select::make('frecuencia')->label(__('limpieza.regla.frecuencia'))->native(false)->live()->placeholder(__('limpieza.regla.sin_limpieza'))->options(FrecuenciaLimpieza::opciones()),
                // Sin el día de llegada ni el de salida: esos no llevan limpieza aparte.
                CheckboxList::make('fechas')->label(__('limpieza.regla.fechas'))->columns(3)->required()
                    ->visible(fn (Get $get): bool => $get('frecuencia') === FrecuenciaLimpieza::Fechas->value || $get('frecuencia') === FrecuenciaLimpieza::Fechas)
                    ->options(function () use ($record): array {
                        $opciones = [];
                        for ($dia = $record->llegada->addDay(); $dia->lt($record->salida); $dia = $dia->addDay()) {
                            $opciones[$dia->format('Y-m-d')] = Formato::fecha($dia, 'ddd D MMM');
                        }

                        return $opciones;
                    }),
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
                        'fechas' => $frecuencia === FrecuenciaLimpieza::Fechas ? array_values((array) ($data['fechas'] ?? [])) : null,
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
            ->visible(fn (Reserva $record): bool => self::marcable($record) && $record->entradaEn() !== null && $record->salidaEn() === null)
            ->action(fn (Reserva $record) => self::marcar($record, fn (GestorEstancias $g) => $g->registrarSalida($record, Acceso::usuarioActual()), __('ui.reservas.salida_ok')));
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

    /**
     * Un documento que escribió el alojamiento (D-123). También en una subordinada: no cambia nada de la reserva.
     */
    public static function documentoPropio(): Action
    {
        return Action::make('documentoPropio')
            ->label(__('documentos_propios.accion'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva && Modulos::activo(Modulo::Documentos) && self::documentosDe($record) !== [])
            ->schema(fn (Reserva $record): array => [
                Select::make('documento')->label(__('documentos_propios.elegir'))->required()->native(false)->options(self::documentosDe($record)),
            ])
            ->action(function (Reserva $record, array $data, MotorDocumentos $motor, GeneradorPdf $pdf): StreamedResponse {
                $documento = DocumentoPropio::query()->where('propiedad_id', $record->propiedad_id)->where('activo', true)->findOrFail((int) $data['documento']);
                $cuerpo = $motor->deReserva($documento, $record, Acceso::puede(Acceso::usuarioActual(), Acceso::HUESPEDES, $record->propiedad_id));
                RegistroAuditoria::anotar('documento_propio.generar', $record, ['documento' => $documento->nombre], $record->propiedad_id);

                return DocumentoPropioResource::descargar($pdf->documentoPropio($cuerpo, $documento->nombre, $record->propiedad, $record, $record->idioma), $documento->nombre);
            });
    }

    /**
     * @return array<int, string>
     */
    private static function documentosDe(Reserva $reserva): array
    {
        return DocumentoPropio::query()->where('propiedad_id', $reserva->propiedad_id)->where('activo', true)->where('alcance', AlcanceDocumento::Reserva->value)->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all();
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
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado !== EstadoReserva::Cancelada && ! $record->estaEnCasa() && Limites::puedeCancelar(Acceso::usuarioActual(), $record))
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->cancelar($record), __('ui.reservas.cancelada_ok')));
    }

    public static function reactivar(): Action
    {
        return Action::make('reactivar')
            ->label(__('ui.reservas.reactivar'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado === EstadoReserva::Cancelada && Limites::puedeModificar(Acceso::usuarioActual(), $record))
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->reactivar($record), __('ui.reservas.reactivada_ok')));
    }

    /**
     * Llegó o salió: también en una subordinada, con una marca propia (D-153).
     */
    private static function marcable(Reserva $reserva): bool
    {
        return Acceso::puede(Acceso::usuarioActual(), Acceso::RESERVAS, $reserva->propiedad_id) && ($reserva->sincronizada || self::gestionable($reserva));
    }

    /**
     * @param  callable(GestorEstancias): mixed  $accion
     */
    private static function marcar(Reserva $reserva, callable $accion, string $mensaje): void
    {
        try {
            $accion(app(GestorEstancias::class));
            Notification::make()->success()->title($mensaje)->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->persistent()->send();
        }
        $reserva->refresh();
        $reserva->unsetRelation('marcaEstancia');
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
