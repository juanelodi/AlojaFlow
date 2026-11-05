<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Support\Formato;

/**
 * Las variables de las plantillas. Se sustituyen como texto: una plantilla nunca ejecuta código.
 */
final class VariablesMensaje
{
    public const DISPONIBLES = ['huesped', 'nombre', 'propiedad', 'telefono_propiedad', 'correo_propiedad', 'numero', 'codigo', 'llegada', 'salida', 'noches', 'personas', 'unidad', 'total', 'pagado', 'pendiente', 'enlace_reserva', 'enlace_llegada'];

    /**
     * @return array<string, string>
     */
    public static function de(Reserva $reserva, string $idioma): array
    {
        $idiomaAnterior = app()->getLocale();
        app()->setLocale($idioma);
        $propiedad = $reserva->propiedad;
        $variables = [
            'huesped' => (string) $reserva->titular?->nombreCompleto(),
            'nombre' => (string) ($reserva->titular->nombre ?? ''),
            'propiedad' => $propiedad->nombre,
            'telefono_propiedad' => (string) $propiedad->getAttribute('telefono'),
            'correo_propiedad' => (string) $propiedad->getAttribute('correo'),
            'numero' => $reserva->numero,
            'codigo' => $reserva->codigo_publico,
            'llegada' => Formato::fecha($reserva->llegada, 'dddd LL'),
            'salida' => Formato::fecha($reserva->salida, 'dddd LL'),
            'noches' => (string) $reserva->llegada->diffInDays($reserva->salida),
            'personas' => (string) $reserva->personas,
            'unidad' => (string) ($reserva->unidad->nombre ?? ''),
            'total' => Formato::dinero($reserva->total, $reserva->moneda),
            'pagado' => Formato::dinero($reserva->pagado, $reserva->moneda),
            'pendiente' => Formato::dinero($reserva->pendiente, $reserva->moneda),
            'enlace_reserva' => $propiedad->esIndependiente() && Modulos::activo(Modulo::Motor) ? self::enlaceInstalado('motor.reserva', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => $idioma]) : '',
        ];
        app()->setLocale($idiomaAnterior);

        return $variables;
    }

    /**
     * Con la dirección de la instalación, no la de la petición: el formulario público lo puede abrir
     * cualquiera, con la cabecera Host que quiera, y este enlace termina en un correo.
     *
     * @param  array<string, mixed>  $parametros
     */
    private static function enlaceInstalado(string $ruta, array $parametros): string
    {
        return rtrim((string) config('app.url'), '/').route($ruta, $parametros, false);
    }

    /**
     * @param  array<string, string>  $variables
     */
    public static function aplicar(string $texto, array $variables): string
    {
        $reemplazos = [];
        foreach ($variables as $clave => $valor) {
            $reemplazos['{'.$clave.'}'] = $valor;
        }

        return strtr($texto, $reemplazos);
    }

    public static function ayuda(): string
    {
        return implode(' ', array_map(fn (string $v): string => '{'.$v.'}', self::DISPONIBLES));
    }

    /**
     * Enlace de WhatsApp con el texto listo; sin teléfono, WhatsApp pregunta a quién mandarlo.
     */
    public static function enlaceWhatsapp(?string $telefono, string $texto): string
    {
        return 'https://wa.me/'.preg_replace('/\D+/', '', (string) $telefono).'?text='.rawurlencode($texto);
    }
}
