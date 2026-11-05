<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Autoridades\Ses;

use App\Autoridades\Comunicacion;
use App\Models\EnvioAutoridad;
use DOMDocument;
use DOMElement;

/**
 * Los XML que van dentro de la petición. Solo la raíz lleva espacio de nombres: los esquemas del servicio no califican los elementos.
 */
final class MensajeSes
{
    private const NS_PARTE = 'http://www.neg.hospedajes.mir.es/altaParteHospedaje';

    private const NS_RESERVA = 'http://www.neg.hospedajes.mir.es/altaReservaHospedaje';

    private const NS_ANULAR = 'http://www.neg.hospedajes.mir.es/anularComunicacion';

    private const NS_CONSULTA = 'http://www.neg.hospedajes.mir.es/consultarComunicacion';

    /**
     * @param  list<Comunicacion>  $comunicaciones
     */
    public static function alta(string $tipo, string $establecimiento, array $comunicaciones): string
    {
        $documento = new DOMDocument('1.0', 'UTF-8');
        $parte = $tipo === EnvioAutoridad::PARTE;
        $raiz = $documento->createElementNS($parte ? self::NS_PARTE : self::NS_RESERVA, 'alt:peticion');
        $documento->appendChild($raiz);
        $solicitud = $documento->createElement('solicitud');
        $raiz->appendChild($solicitud);
        if ($parte) {
            self::anadir($documento, $solicitud, 'codigoEstablecimiento', $establecimiento);
        }
        foreach ($comunicaciones as $comunicacion) {
            $nodo = $documento->createElement('comunicacion');
            $solicitud->appendChild($nodo);
            if (! $parte) {
                self::llenar($documento, $nodo, ['establecimiento' => ['codigo' => $establecimiento]]);
            }
            self::llenar($documento, $nodo, ['contrato' => $comunicacion->datos['contrato']]);
            foreach ((array) $comunicacion->datos['personas'] as $persona) {
                self::llenar($documento, $nodo, ['persona' => $persona]);
            }
        }

        return (string) $documento->saveXML();
    }

    /**
     * @param  list<string>  $codigos
     */
    public static function anulacion(array $codigos): string
    {
        return self::lista(self::NS_ANULAR, 'anul:comunicaciones', 'anul:codigoComunicacion', $codigos);
    }

    /**
     * @param  list<string>  $lotes
     */
    public static function consulta(array $lotes): string
    {
        return self::lista(self::NS_CONSULTA, 'con:lotes', 'con:lote', $lotes);
    }

    /**
     * @param  list<string>  $valores
     */
    private static function lista(string $espacio, string $raiz, string $elemento, array $valores): string
    {
        $documento = new DOMDocument('1.0', 'UTF-8');
        $nodo = $documento->createElementNS($espacio, $raiz);
        $documento->appendChild($nodo);
        foreach ($valores as $valor) {
            $hijo = $documento->createElementNS($espacio, $elemento);
            $hijo->appendChild($documento->createTextNode($valor));
            $nodo->appendChild($hijo);
        }

        return (string) $documento->saveXML();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function llenar(DOMDocument $documento, DOMElement $padre, array $datos): void
    {
        foreach ($datos as $nombre => $valor) {
            if (is_array($valor)) {
                $hijo = $documento->createElement($nombre);
                $padre->appendChild($hijo);
                self::llenar($documento, $hijo, $valor);
            } elseif ($valor !== null) {
                self::anadir($documento, $padre, $nombre, is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor);
            }
        }
    }

    private static function anadir(DOMDocument $documento, DOMElement $padre, string $nombre, string $texto): void
    {
        $elemento = $documento->createElement($nombre);
        $elemento->appendChild($documento->createTextNode($texto));
        $padre->appendChild($elemento);
    }
}
