<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Motor;

use App\Models\Propiedad;
use App\Support\Ajustes;
use SensitiveParameter;

/**
 * Ajustes del motor de reservas de una propiedad. Las llaves de las pasarelas van cifradas en la
 * tabla de ajustes y nunca vuelven a mostrarse.
 */
final class AjustesMotor
{
    public function __construct(private readonly Propiedad $propiedad)
    {
    }

    public function activo(): bool
    {
        return $this->propiedad->esIndependiente() && $this->propiedad->activa && (bool) $this->valor('activo', false);
    }

    /**
     * Sin anticipo que cobrar en línea: ¿la reserva queda confirmada o espera a que el alojamiento la revise?
     */
    public function confirmacionInmediata(): bool
    {
        return (bool) $this->valor('confirmacion_inmediata', false);
    }

    /** Porcentaje del total que se cobra para garantizar la reserva. */
    public function anticipoPorcentaje(): int
    {
        return min(100, max(0, (int) $this->valor('anticipo_porcentaje', 30)));
    }

    public function minutosRetencion(): int
    {
        return max(5, (int) $this->valor('minutos_retencion', 30));
    }

    /**
     * Cuánto espera una reserva sin pagar a que el alojamiento la revise antes de liberar sus noches.
     * Con 0 no caduca: quien abuse del formulario puede dejar la propiedad sin lugar.
     */
    public function horasRevision(): int
    {
        return max(0, (int) $this->valor('horas_revision', 48));
    }

    public function diasMinimosAntelacion(): int
    {
        return max(0, (int) $this->valor('dias_antelacion', 0));
    }

    public function instrucciones(): ?string
    {
        $texto = trim((string) $this->valor('instrucciones', ''));

        return $texto === '' ? null : $texto;
    }

    /**
     * Si la página de la reserva deja subir el comprobante de una transferencia. Tiene sentido si se dice a dónde transferir.
     */
    public function aceptaComprobantes(): bool
    {
        return $this->instrucciones() !== null && (bool) $this->valor('acepta_comprobantes', true);
    }

    public function condiciones(): ?string
    {
        $texto = trim((string) $this->valor('condiciones', ''));

        return $texto === '' ? null : $texto;
    }

    public function correoAviso(): ?string
    {
        $texto = trim((string) ($this->valor('correo_aviso', '') ?: $this->propiedad->getAttribute('correo')));

        return $texto === '' ? null : $texto;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public function guardar(array $valores): void
    {
        $ajustes = (array) $this->propiedad->ajustes;
        $ajustes['motor'] = $valores + (array) ($ajustes['motor'] ?? []);
        $this->propiedad->ajustes = $ajustes;
        $this->propiedad->save();
    }

    public function llave(string $nombre): ?string
    {
        return Ajustes::secreto($this->claveLlave($nombre));
    }

    public function guardarLlave(string $nombre, #[SensitiveParameter] ?string $valor): void
    {
        Ajustes::guardarSecreto($this->claveLlave($nombre), $valor);
    }

    public function tieneLlave(string $nombre): bool
    {
        return filled(Ajustes::obtener($this->claveLlave($nombre)));
    }

    private function claveLlave(string $nombre): string
    {
        return 'pagos.'.$this->propiedad->id.'.'.$nombre;
    }

    private function valor(string $clave, mixed $porOmision): mixed
    {
        return ((array) $this->propiedad->ajustes)['motor'][$clave] ?? $porOmision;
    }
}
