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
use App\Documentos\GeneradorPdf;
use App\Enums\CanalEnvio;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\FrecuenciaLimpieza;
use App\Enums\TipoReserva;
use App\Limpieza\GestorLimpieza;
use App\Models\Plantilla;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
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
            ->modalDescription(__('ui.reservas.cancelar_ayuda'))
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
