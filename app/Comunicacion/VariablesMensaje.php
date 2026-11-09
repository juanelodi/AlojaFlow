<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Documentos\CamposPropios;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Publico\PaginaPublica;
use App\Reservas\CodigosAcceso;
use App\Reservas\Mensualidades;
use App\Support\Ajustes;
use App\Support\Contactos;
use App\Support\Formato;
use Illuminate\Support\Str;

/**
 * Las variables de las plantillas. Se sustituyen como texto: una plantilla nunca ejecuta código.
 */
final class VariablesMensaje
{
    public const DISPONIBLES = ['huesped', 'nombre', 'propiedad', 'telefono_propiedad', 'correo_propiedad', 'numero', 'codigo', 'llegada', 'salida', 'noches', 'personas', 'unidad', 'total', 'pagado', 'pendiente', 'enlace_reserva', 'enlace_llegada', 'enlace_opinion', 'enlace_mi_reserva', 'cuota_importe', 'cuota_vence', 'cuota_periodo', 'pagina_propiedad', 'whatsapp_propiedad', 'mapa_propiedad', 'codigo_acceso'];

    public const AJUSTE_PROPIAS = 'mensajes.variables.';

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
            // El de la reserva o el fijo de su unidad (D-156).
            'codigo_acceso' => (string) CodigosAcceso::de($reserva),
            'total' => Formato::dinero($reserva->total, $reserva->moneda),
            'pagado' => Formato::dinero($reserva->pagado, $reserva->moneda),
            'pendiente' => Formato::dinero($reserva->pendiente, $reserva->moneda),
            'enlace_reserva' => $propiedad->esIndependiente() && Modulos::activo(Modulo::Motor) ? self::enlaceInstalado('motor.reserva', ['codigo' => $propiedad->codigo, 'publico' => $reserva->codigo_publico, 'idioma' => $idioma]) : '',
        ];
        $variables += self::dePropiedad($propiedad);
        $pago = Mensualidades::siguiente($reserva);
        $variables['cuota_importe'] = $pago === null ? '' : Formato::dinero($pago['cuota']->importe - $pago['pagado'], $reserva->moneda);
        $variables['cuota_vence'] = $pago === null ? '' : Formato::fecha($pago['cuota']->vence_en, 'LL');
        $variables['cuota_periodo'] = $pago === null ? '' : $pago['cuota']->periodo();
        app()->setLocale($idiomaAnterior);

        // Las propias del alojamiento (la clave del wifi…), sin pisar las del sistema.
        return $variables + self::propias($reserva->propiedad_id) + CamposPropios::variables($reserva);
    }

    /**
     * La página pública, su WhatsApp y su mapa. Vacías si la propiedad no los tiene: una dirección que no abre es peor que ninguna.
     *
     * @return array<string, string>
     */
    public static function dePropiedad(Propiedad $propiedad): array
    {
        $pagina = new PaginaPublica($propiedad);
        $whatsapp = $pagina->whatsapp();

        return [
            'pagina_propiedad' => (new AjustesMotor($propiedad))->activo() && Modulos::activo(Modulo::Motor) ? self::enlaceInstalado('motor.buscar', ['codigo' => $propiedad->codigo]) : '',
            'whatsapp_propiedad' => (string) $whatsapp,
            'mapa_propiedad' => (string) $pagina->mapaUrl(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function propias(int $propiedadId): array
    {
        $propias = Ajustes::obtener(self::AJUSTE_PROPIAS.$propiedadId);

        return is_array($propias) ? array_map('strval', $propias) : [];
    }

    /**
     * «Mi clave» se vuelve mi_clave: lo que se escribe entre llaves en una plantilla.
     *
     * @param  array<string, string|null>  $variables
     */
    public static function guardarPropias(int $propiedadId, array $variables): void
    {
        $limpias = [];
        foreach ($variables as $nombre => $valor) {
            $clave = (string) preg_replace('/[^a-z0-9_]+/', '_', (string) Str::of((string) $nombre)->ascii()->lower());
            if (trim($clave, '_') !== '' && ! in_array($clave, self::DISPONIBLES, true)) {
                $limpias[trim($clave, '_')] = (string) $valor;
            }
        }
        Ajustes::guardar(self::AJUSTE_PROPIAS.$propiedadId, $limpias);
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

    /**
     * Enlace de WhatsApp con el texto listo; sin teléfono, WhatsApp pregunta a quién mandarlo.
     */
    public static function enlaceWhatsapp(?string $telefono, string $texto, ?string $pais = null): string
    {
        $numero = Contactos::internacional($telefono, $pais) ?? (string) $telefono;

        return 'https://wa.me/'.preg_replace('/\D+/', '', $numero).'?text='.rawurlencode($texto);
    }
}
