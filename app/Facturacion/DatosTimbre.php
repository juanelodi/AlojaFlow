<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use DOMDocument;
use DOMElement;

/**
 * Lo que la impresión de un CFDI tiene que decir de su timbre, sacado del XML que devolvió el PAC: no se guarda aparte, para que no pueda diferir de él.
 */
final class DatosTimbre
{
    private const NS_TIMBRE = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';

    private const VERIFICACION = 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx';

    /**
     * @return array{uuid: string, fecha: string, proveedor: string, certificado_sat: string, certificado_emisor: string, sello_emisor: string, sello_sat: string, cadena: string, verificacion: string}|null
     */
    public static function de(string $xml): ?array
    {
        $documento = new DOMDocument();
        if (! @$documento->loadXML($xml, LIBXML_NONET)) {
            return null;
        }
        $timbre = $documento->getElementsByTagNameNS(self::NS_TIMBRE, 'TimbreFiscalDigital')->item(0);
        $comprobante = $documento->documentElement;
        if (! $timbre instanceof DOMElement || ! $comprobante instanceof DOMElement) {
            return null;
        }
        $uuid = $timbre->getAttribute('UUID');
        $fecha = $timbre->getAttribute('FechaTimbrado');
        $proveedor = $timbre->getAttribute('RfcProvCertif');
        $selloEmisor = $timbre->getAttribute('SelloCFD');
        $certificadoSat = $timbre->getAttribute('NoCertificadoSAT');
        $emisor = $documento->getElementsByTagNameNS(self::NS_CFDI, 'Emisor')->item(0);
        $receptor = $documento->getElementsByTagNameNS(self::NS_CFDI, 'Receptor')->item(0);

        return [
            'uuid' => $uuid,
            'fecha' => $fecha,
            'proveedor' => $proveedor,
            'certificado_sat' => $certificadoSat,
            'certificado_emisor' => $comprobante->getAttribute('NoCertificado'),
            'sello_emisor' => $selloEmisor,
            'sello_sat' => $timbre->getAttribute('SelloSAT'),
            'cadena' => '||1.1|'.$uuid.'|'.$fecha.'|'.$proveedor.'|'.($timbre->hasAttribute('Leyenda') ? $timbre->getAttribute('Leyenda').'|' : '').$selloEmisor.'|'.$certificadoSat.'||',
            'verificacion' => self::VERIFICACION.'?'.http_build_query([
                'id' => $uuid,
                're' => $emisor instanceof DOMElement ? $emisor->getAttribute('Rfc') : '',
                'rr' => $receptor instanceof DOMElement ? $receptor->getAttribute('Rfc') : '',
                'tt' => $comprobante->getAttribute('Total'),
                'fe' => substr($selloEmisor, -8),
            ]),
        ];
    }
}
