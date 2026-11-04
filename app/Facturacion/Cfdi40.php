<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use App\Models\Factura;
use App\Support\Dinero;
use DOMDocument;
use DOMElement;

/**
 * CFDI 4.0 de México, listo para que un PAC lo selle y lo timbre. Sin timbrar no tiene validez
 * fiscal. Sello y certificado quedan vacíos: los pone el PAC junto con el CSD del emisor (D-049).
 *
 * Las claves del SAT son de sus catálogos públicos; un contador debe confirmarlas para cada negocio.
 */
final class Cfdi40 implements GeneradorFiscal
{
    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';

    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const NS_LOCAL = 'http://www.sat.gob.mx/implocal';

    private const ESQUEMAS = 'http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd';

    private const ESQUEMA_LOCAL = ' http://www.sat.gob.mx/implocal http://www.sat.gob.mx/sitio_internet/cfd/implocal/implocal.xsd';

    private const RFC = '/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u';

    /** Los impuestos federales van en el nodo de traslados; los demás (ISH) en el complemento local. */
    private const CODIGO_IVA = '002';

    private static function esIva(?string $codigo): bool
    {
        return in_array(mb_strtoupper((string) $codigo), [self::CODIGO_IVA, 'IVA'], true);
    }

    public function catalogos(): array
    {
        return [
            'regimen' => [
                '601' => '601 · General de Ley Personas Morales',
                '603' => '603 · Personas Morales con Fines no Lucrativos',
                '605' => '605 · Sueldos y Salarios',
                '606' => '606 · Arrendamiento',
                '612' => '612 · Personas Físicas con Actividades Empresariales y Profesionales',
                '616' => '616 · Sin obligaciones fiscales',
                '621' => '621 · Incorporación Fiscal',
                '626' => '626 · Régimen Simplificado de Confianza',
            ],
            'uso' => [
                'G03' => 'G03 · Gastos en general',
                'G01' => 'G01 · Adquisición de mercancías',
                'S01' => 'S01 · Sin efectos fiscales',
                'CP01' => 'CP01 · Pagos',
            ],
            'forma_pago' => [
                '01' => '01 · Efectivo',
                '03' => '03 · Transferencia',
                '04' => '04 · Tarjeta de crédito',
                '28' => '28 · Tarjeta de débito',
                '99' => '99 · Por definir',
            ],
            'metodo_pago' => [
                'PUE' => 'PUE · Pago en una sola exhibición',
                'PPD' => 'PPD · Pago en parcialidades o diferido',
            ],
        ];
    }

    public function errorEnReceptor(array $receptor): ?string
    {
        if (preg_match(self::RFC, mb_strtoupper((string) ($receptor['identificador_fiscal'] ?? ''))) !== 1) {
            return __('dinero.facturas.error_rfc');
        }
        if (preg_match('/^\d{5}$/', (string) ($receptor['codigo_postal'] ?? '')) !== 1) {
            return __('dinero.facturas.error_cp');
        }
        if (blank($receptor['regimen_fiscal'] ?? null) || blank($receptor['uso'] ?? null)) {
            return __('dinero.facturas.error_regimen');
        }

        return null;
    }

    public function clavesHospedaje(): array
    {
        return ['producto' => '90111800', 'unidad' => 'E48'];
    }

    public function nombreArchivo(Factura $factura): string
    {
        return 'cfdi-'.$factura->folio().'.xml';
    }

    public function generar(Factura $factura): string
    {
        $factura->loadMissing('lineas.impuestos');
        $moneda = $factura->moneda;
        $importe = fn (int $cantidad): string => Dinero::aDecimal($cantidad, $moneda);

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $comprobante = $xml->createElementNS(self::NS_CFDI, 'cfdi:Comprobante');
        $xml->appendChild($comprobante);
        $comprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);

        [$federales, $locales] = $this->separarImpuestos($factura);
        $esquemas = self::ESQUEMAS;
        if ($locales !== []) {
            $comprobante->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:implocal', self::NS_LOCAL);
            $esquemas .= self::ESQUEMA_LOCAL;
        }
        $comprobante->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', $esquemas);

        $totalFederales = array_sum(array_column($federales, 'importe'));
        $this->atributos($comprobante, [
            'Version' => '4.0',
            'Serie' => $factura->serie,
            'Folio' => (string) $factura->numero,
            'Fecha' => $factura->emitida_en->setTimezone($factura->propiedad->zona_horaria)->format('Y-m-d\TH:i:s'),
            'Sello' => '',
            'FormaPago' => $factura->forma_pago,
            'NoCertificado' => '',
            'Certificado' => '',
            'SubTotal' => $importe($factura->subtotal),
            'Moneda' => $moneda,
            'Total' => $importe($factura->total),
            'TipoDeComprobante' => 'I',
            'Exportacion' => '01',
            'MetodoPago' => $factura->metodo_pago,
            'LugarExpedicion' => $factura->lugar_expedicion,
        ]);

        $emisor = $xml->createElementNS(self::NS_CFDI, 'cfdi:Emisor');
        $this->atributos($emisor, [
            'Rfc' => mb_strtoupper((string) $factura->emisor['identificador_fiscal']),
            'Nombre' => mb_strtoupper((string) $factura->emisor['nombre']),
            'RegimenFiscal' => (string) $factura->emisor['regimen_fiscal'],
        ]);
        $comprobante->appendChild($emisor);

        $receptor = $xml->createElementNS(self::NS_CFDI, 'cfdi:Receptor');
        $this->atributos($receptor, [
            'Rfc' => mb_strtoupper((string) $factura->receptor['identificador_fiscal']),
            'Nombre' => mb_strtoupper((string) $factura->receptor['nombre']),
            'DomicilioFiscalReceptor' => (string) $factura->receptor['codigo_postal'],
            'RegimenFiscalReceptor' => (string) $factura->receptor['regimen_fiscal'],
            'UsoCFDI' => (string) $factura->receptor['uso'],
        ]);
        $comprobante->appendChild($receptor);

        $conceptos = $xml->createElementNS(self::NS_CFDI, 'cfdi:Conceptos');
        $comprobante->appendChild($conceptos);
        foreach ($factura->lineas as $linea) {
            $concepto = $xml->createElementNS(self::NS_CFDI, 'cfdi:Concepto');
            $federalesLinea = $linea->impuestos->filter(fn ($i): bool => self::esIva($i->codigo));
            $this->atributos($concepto, [
                'ClaveProdServ' => $linea->clave_producto,
                'Cantidad' => rtrim(rtrim($linea->cantidad, '0'), '.'),
                'ClaveUnidad' => $linea->clave_unidad,
                'Descripcion' => $linea->concepto,
                'ValorUnitario' => $importe($linea->importe),
                'Importe' => $importe($linea->importe),
                'ObjetoImp' => $federalesLinea->isEmpty() ? '01' : '02',
            ]);
            if ($federalesLinea->isNotEmpty()) {
                $impuestos = $xml->createElementNS(self::NS_CFDI, 'cfdi:Impuestos');
                $traslados = $xml->createElementNS(self::NS_CFDI, 'cfdi:Traslados');
                foreach ($federalesLinea as $impuesto) {
                    $traslado = $xml->createElementNS(self::NS_CFDI, 'cfdi:Traslado');
                    $this->atributos($traslado, [
                        'Base' => $importe($impuesto->base),
                        'Impuesto' => self::CODIGO_IVA,
                        'TipoFactor' => 'Tasa',
                        'TasaOCuota' => number_format((float) $impuesto->tasa / 100, 6, '.', ''),
                        'Importe' => $importe($impuesto->importe),
                    ]);
                    $traslados->appendChild($traslado);
                }
                $impuestos->appendChild($traslados);
                $concepto->appendChild($impuestos);
            }
            $conceptos->appendChild($concepto);
        }

        if ($federales !== []) {
            $impuestos = $xml->createElementNS(self::NS_CFDI, 'cfdi:Impuestos');
            $impuestos->setAttribute('TotalImpuestosTrasladados', $importe((int) $totalFederales));
            $traslados = $xml->createElementNS(self::NS_CFDI, 'cfdi:Traslados');
            foreach ($federales as $grupo) {
                $traslado = $xml->createElementNS(self::NS_CFDI, 'cfdi:Traslado');
                $this->atributos($traslado, [
                    'Base' => $importe($grupo['base']),
                    'Impuesto' => self::CODIGO_IVA,
                    'TipoFactor' => 'Tasa',
                    'TasaOCuota' => number_format($grupo['tasa'] / 100, 6, '.', ''),
                    'Importe' => $importe($grupo['importe']),
                ]);
                $traslados->appendChild($traslado);
            }
            $impuestos->appendChild($traslados);
            $comprobante->appendChild($impuestos);
        }

        if ($locales !== []) {
            $complemento = $xml->createElementNS(self::NS_CFDI, 'cfdi:Complemento');
            $imp = $xml->createElementNS(self::NS_LOCAL, 'implocal:ImpuestosLocales');
            $this->atributos($imp, ['version' => '1.0', 'TotaldeRetenciones' => $importe(0), 'TotaldeTraslados' => $importe((int) array_sum(array_column($locales, 'importe')))]);
            foreach ($locales as $grupo) {
                $traslado = $xml->createElementNS(self::NS_LOCAL, 'implocal:TrasladosLocales');
                $this->atributos($traslado, ['ImpLocTrasladado' => $grupo['nombre'], 'TasadeTraslado' => number_format($grupo['tasa'], 2, '.', ''), 'Importe' => $importe($grupo['importe'])]);
                $imp->appendChild($traslado);
            }
            $complemento->appendChild($imp);
            $comprobante->appendChild($complemento);
        }

        return (string) $xml->saveXML();
    }

    /**
     * @return array{0: list<array{nombre: string, tasa: float, base: int, importe: int}>, 1: list<array{nombre: string, tasa: float, base: int, importe: int}>}
     */
    private function separarImpuestos(Factura $factura): array
    {
        $federales = [];
        $locales = [];
        foreach ($factura->lineas as $linea) {
            foreach ($linea->impuestos as $impuesto) {
                $clave = $impuesto->nombre.'|'.$impuesto->tasa;
                if (self::esIva($impuesto->codigo)) {
                    $federales[$clave] ??= ['nombre' => $impuesto->nombre, 'tasa' => (float) $impuesto->tasa, 'base' => 0, 'importe' => 0];
                    $federales[$clave]['base'] += $impuesto->base;
                    $federales[$clave]['importe'] += $impuesto->importe;
                } else {
                    $locales[$clave] ??= ['nombre' => $impuesto->nombre, 'tasa' => (float) $impuesto->tasa, 'base' => 0, 'importe' => 0];
                    $locales[$clave]['base'] += $impuesto->base;
                    $locales[$clave]['importe'] += $impuesto->importe;
                }
            }
        }

        return [array_values($federales), array_values($locales)];
    }

    /**
     * @param  array<string, string|null>  $atributos
     */
    private function atributos(DOMElement $elemento, array $atributos): void
    {
        foreach ($atributos as $nombre => $valor) {
            if ($valor !== null) {
                $elemento->setAttribute($nombre, $valor);
            }
        }
    }
}
