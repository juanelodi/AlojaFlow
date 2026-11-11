<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Motor;

use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
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

    /**
     * Cuántas unidades se pueden pedir en una sola reserva (D-158). 1 lo apaga; con el plano de lugares siempre es una.
     */
    public function unidadesMaximas(): int
    {
        return $this->elegirLugar() ? 1 : max(1, min(10, (int) $this->valor('unidades_maximas', 3)));
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

    /**
     * Lo que se ofrece en pesos, además de la tarjeta (D-099).
     *
     * @return array{oxxo: bool, oxxo_dias: int, meses: bool, spei: bool}
     */
    public function opcionesStripe(): array
    {
        return [
            'oxxo' => (bool) $this->valor('stripe_oxxo', false),
            'oxxo_dias' => min(7, max(1, (int) $this->valor('stripe_oxxo_dias', 3))),
            'meses' => (bool) $this->valor('stripe_meses', false),
            'spei' => (bool) $this->valor('stripe_spei', false),
        ];
    }

    /**
     * El TPV de Redsys (D-102). La clave va aparte, cifrada.
     *
     * @return array{comercio: string, terminal: string, pruebas: bool, bizum: bool}
     */
    public function redsys(): array
    {
        return [
            'comercio' => trim((string) $this->valor('redsys_comercio', '')),
            'terminal' => trim((string) $this->valor('redsys_terminal', '1')) ?: '1',
            'pruebas' => (bool) $this->valor('redsys_pruebas', true),
            'bizum' => (bool) $this->valor('redsys_bizum', false),
        ];
    }

    /**
     * PayPal (D-159): el identificador de la aplicación y si es la de pruebas.
     *
     * @return array{cliente: string, pruebas: bool}
     */
    public function paypal(): array
    {
        return [
            'cliente' => trim((string) $this->valor('paypal_cliente', '')),
            'pruebas' => (bool) $this->valor('paypal_pruebas', true),
        ];
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

    /**
     * Si le pregunta al huésped con qué equipo viene (D-110).
     */
    public function pideEquipo(): bool
    {
        return Modulos::activo(Modulo::Camping) && (bool) $this->valor('pide_equipo', false);
    }

    /**
     * Si el huésped elige su lugar en el plano (D-110).
     */
    /**
     * Si cualquiera puede ver qué días hay lugar (D-126). Dice algo de cómo va el negocio: se prende a propósito.
     */
    public function calendarioPublico(): bool
    {
        return (bool) $this->valor('calendario_publico', false);
    }

    public function elegirLugar(): bool
    {
        return Modulos::activo(Modulo::Camping) && Modulos::activo(Modulo::Plano) && (bool) $this->valor('elegir_lugar', false);
    }

    /**
     * El cargo del catálogo que se suma al elegir un lugar concreto. Sin él, elegir no cuesta.
     */
    public function cargoLugarId(): ?int
    {
        $id = $this->valor('cargo_lugar_id', null);

        return is_numeric($id) ? (int) $id : null;
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
