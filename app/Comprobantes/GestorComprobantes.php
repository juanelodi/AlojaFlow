<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comprobantes;

use App\Archivos\GestorArchivos;
use App\Comunicacion\Mensajero;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoComprobante;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\NivelAviso;
use App\Models\Aviso;
use App\Models\ComprobantePago;
use App\Models\EnlacePublico;
use App\Models\Grupo;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Motor\AjustesMotor;
use App\Pagos\CobrosEnLinea;
use App\Reservas\GestorGrupos;
use App\Reservas\GestorReservas;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Un pago que alguien dice haber hecho (D-097). No cuenta hasta que el alojamiento mira la imagen y su banco: entonces se vuelve un pago normal, de la
 * reserva o repartido entre las del grupo.
 */
final class GestorComprobantes
{
    private const MAXIMOS_POR_OBJETO = 300;

    private const MESES_CONSERVAR = 24;

    private const TIPO_AVISO = 'comprobantes_por_revisar';

    public function __construct(
        private readonly GestorArchivos $archivos,
        private readonly GestorReservas $reservas,
        private readonly GestorGrupos $grupos,
        private readonly CobrosEnLinea $cobros,
    ) {
    }

    /**
     * @throws ComprobanteRechazado
     */
    public function recibirDeGrupo(Grupo $grupo, string $contenido, int $importe, CarbonImmutable $fecha, ?string $referencia): ComprobantePago
    {
        if ($grupo->comprobantes()->count() >= self::MAXIMOS_POR_OBJETO) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_demasiados'));
        }

        return $this->guardar($grupo->propiedad_id, $grupo->propiedad->moneda, ['grupo_id' => $grupo->id], $contenido, $importe, $fecha, $referencia, $grupo->ultimaSalida());
    }

    /**
     * Mientras alguien lo revisa, la reserva no caduca: ya mandaron el dinero. Lo que caduca, si se rechaza, se decide al rechazar.
     *
     * @throws ComprobanteRechazado
     */
    public function recibirDeReserva(Reserva $reserva, string $contenido, int $importe, CarbonImmutable $fecha, ?string $referencia): ComprobantePago
    {
        if ($reserva->estado === EstadoReserva::Cancelada) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_cancelada'));
        }
        if ($reserva->comprobantes()->count() >= self::MAXIMOS_POR_OBJETO) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_demasiados'));
        }
        $comprobante = $this->guardar($reserva->propiedad_id, $reserva->moneda, ['reserva_id' => $reserva->id], $contenido, $importe, $fecha, $referencia, $reserva->salida);
        $this->reservas->fijarVencimiento($reserva, null);

        return $comprobante;
    }

    public function confirmar(ComprobantePago $comprobante, ?int $cajaId = null): void
    {
        DB::transaction(function () use ($comprobante, $cajaId): void {
            $actual = ComprobantePago::query()->lockForUpdate()->findOrFail($comprobante->id);
            if ($actual->estado !== EstadoComprobante::PorConfirmar) {
                return;
            }
            if ($actual->grupo !== null) {
                $this->grupos->cobrar($actual->grupo, $actual->importe, $actual->metodo, $actual->fecha, $actual->referencia, $cajaId);
            } elseif ($actual->reserva !== null) {
                $this->reservas->registrarPago($actual->reserva, $actual->importe, $actual->metodo, $actual->fecha, $actual->referencia, $cajaId);
                $this->cobros->asegurarReserva($actual->reserva->refresh());
            }
            $actual->update(['estado' => EstadoComprobante::Confirmado, 'revisado_por' => auth()->id(), 'revisado_en' => now('UTC')]);
            RegistroAuditoria::anotar('comprobante.confirmar', $actual, ['importe' => $actual->importe, 'moneda' => $actual->moneda], $actual->propiedad_id);
        });
        $this->revisarAviso($comprobante->propiedad_id);
    }

    public function rechazar(ComprobantePago $comprobante, string $motivo): void
    {
        $rechazado = DB::transaction(function () use ($comprobante, $motivo): bool {
            $actual = ComprobantePago::query()->lockForUpdate()->findOrFail($comprobante->id);
            if ($actual->estado !== EstadoComprobante::PorConfirmar) {
                return false;
            }
            $actual->update(['estado' => EstadoComprobante::Rechazado, 'motivo_rechazo' => mb_substr($motivo, 0, 255), 'revisado_por' => auth()->id(), 'revisado_en' => now('UTC')]);
            RegistroAuditoria::anotar('comprobante.rechazar', $actual, ['importe' => $actual->importe, 'moneda' => $actual->moneda], $actual->propiedad_id);

            return true;
        });
        $this->revisarAviso($comprobante->propiedad_id);
        if ($rechazado && $comprobante->reserva !== null) {
            $this->avisarRechazo($comprobante->refresh(), $comprobante->reserva);
        }
    }

    /**
     * Las imágenes no esperan a su plazo: nadie va a poder llegar a ellas. Las aceptaciones se quedan, porque son la prueba de lo que se aceptó.
     */
    public function borrarGrupo(Grupo $grupo): void
    {
        $propiedadId = $grupo->propiedad_id;
        DB::transaction(function () use ($grupo): void {
            foreach ($grupo->comprobantes()->with('archivo')->get() as $comprobante) {
                /** @var ComprobantePago $comprobante */
                if ($comprobante->archivo !== null) {
                    $this->archivos->eliminar($comprobante->archivo);
                }
            }
            EnlacePublico::query()->where('objeto_type', $grupo->getMorphClass())->where('objeto_id', $grupo->id)->delete();
            RegistroAuditoria::anotar('grupo.borrar', $grupo, ['codigo' => $grupo->codigo], $grupo->propiedad_id);
            $grupo->delete();
        });
        $this->revisarAviso($propiedadId);
    }

    /**
     * @param  array<string, int>  $objeto
     */
    private function guardar(int $propiedadId, string $moneda, array $objeto, string $contenido, int $importe, CarbonImmutable $fecha, ?string $referencia, ?CarbonImmutable $salida): ComprobantePago
    {
        if ($importe <= 0) {
            throw new ComprobanteRechazado(__('ui.pagos.error_importe'));
        }
        $limpia = ImagenComprobante::limpiar($contenido);

        $comprobante = DB::transaction(function () use ($propiedadId, $moneda, $objeto, $limpia, $importe, $fecha, $referencia, $salida): ComprobantePago {
            $comprobante = ComprobantePago::query()->create($objeto + [
                'propiedad_id' => $propiedadId,
                'importe' => $importe,
                'moneda' => $moneda,
                'fecha' => $fecha,
                'metodo' => MetodoPago::Transferencia,
                'referencia' => filled($referencia) ? mb_substr((string) $referencia, 0, 100) : null,
            ]);
            $archivo = $this->archivos->guardar(
                $limpia,
                'comprobante-'.$comprobante->id.'.jpg',
                'image/jpeg',
                ClaseArchivo::Comprobante,
                $comprobante,
                $propiedadId,
                cifrar: true,
                conservarHasta: ($salida ?? CarbonImmutable::now())->addMonths(self::MESES_CONSERVAR),
            );
            $comprobante->update(['archivo_id' => $archivo->id]);

            return $comprobante;
        });
        Aviso::abrir($propiedadId, self::TIPO_AVISO, NivelAviso::Informacion);

        return $comprobante;
    }

    /**
     * Le queda tiempo para mandar otro, pero no indefinidamente: si no, un comprobante falso apartaría las noches para siempre.
     */
    private function avisarRechazo(ComprobantePago $comprobante, Reserva $reserva): void
    {
        $horas = (new AjustesMotor($reserva->propiedad))->horasRevision();
        $otros = $reserva->comprobantes()->where('estado', EstadoComprobante::PorConfirmar->value)->exists();
        if ($reserva->estado === EstadoReserva::SinConfirmar && $horas > 0 && ! $otros) {
            $this->reservas->fijarVencimiento($reserva, now('UTC')->addHours($horas));
        }

        $destino = Mensajero::correoDe($reserva);
        if ($destino === null) {
            return;
        }
        $idioma = Mensajero::idiomaDe($reserva);
        app(Mensajero::class)->mandarCorreo(
            $reserva,
            $destino,
            __('grupos.comprobante.rechazo_asunto', ['propiedad' => $reserva->propiedad->nombre], $idioma),
            __('grupos.comprobante.rechazo_cuerpo', [
                'numero' => $reserva->numero,
                'importe' => Formato::dinero($comprobante->importe, $comprobante->moneda),
                'motivo' => (string) $comprobante->motivo_rechazo,
                'enlace' => route('motor.reserva', ['codigo' => $reserva->propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => $idioma]),
            ], $idioma),
            $idioma,
        );
    }

    private function revisarAviso(int $propiedadId): void
    {
        if (! ComprobantePago::query()->where('propiedad_id', $propiedadId)->where('estado', EstadoComprobante::PorConfirmar->value)->exists()) {
            Aviso::resolver($propiedadId, self::TIPO_AVISO);
        }
    }
}
