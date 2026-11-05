<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Autoridades\Ses;

use App\Autoridades\ErrorAutoridad;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use ZipArchive;

/**
 * El Servicio de Comunicación del Ministerio del Interior: SOAP, con usuario y contraseña, y el fichero en XML dentro de un ZIP en Base64
 * (MIR-HOSPE-DSI-WS, versión 3.1.2). Se escribe a mano para no pedirle al hosting la extensión soap.
 *
 * Su servidor no manda el certificado intermedio de la FNMT, y un hosting con OpenSSL no podría comprobar con quién habla: va con la instalación.
 */
final class ClienteSes
{
    private const DIRECCIONES = [
        AjustesSes::MODO_PRUEBAS => 'https://hospedajes.pre-ses.mir.es/hospedajes-web/ws/v1/comunicacion',
        AjustesSes::MODO_PRODUCCION => 'https://hospedajes.ses.mir.es/hospedajes-web/ws/v1/comunicacion',
    ];

    private const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';

    private const NS_SERVICIO = 'http://www.soap.servicios.hospedajes.mir.es/comunicacion';

    private const APLICACION = 'AlojaFlow';

    public function __construct(
        private readonly string $modo,
        private readonly string $usuario,
        #[SensitiveParameter] private readonly string $contrasena,
        private readonly string $arrendador,
    ) {
    }

    /**
     * Sirve de prueba de la conexión: sin credenciales buenas no contesta.
     *
     * @return array<string, string> código => descripción
     *
     * @throws ErrorAutoridad
     */
    public function catalogo(?string $nombre = null): array
    {
        $soap = $this->sobre('catalogoRequest', function (DOMDocument $documento, DOMElement $peticion) use ($nombre): void {
            if ($nombre !== null) {
                $this->anadir($documento, $peticion, 'catalogo', $nombre);
            }
        });
        $consulta = $this->llamar($soap);
        $tuplas = [];
        foreach ($consulta->query('//*[local-name()="tupla"]') ?: [] as $tupla) {
            $codigo = $this->hijo($consulta, $tupla, 'codigo');
            if ($codigo !== null) {
                $tuplas[$codigo] = (string) $this->hijo($consulta, $tupla, 'descripcion');
            }
        }

        return $tuplas;
    }

    /**
     * @return string el número de lote
     *
     * @throws ErrorAutoridad
     */
    public function alta(string $tipoComunicacion, string $xml): string
    {
        return $this->operacion('A', $tipoComunicacion, $xml);
    }

    /**
     * @return string el número de lote
     *
     * @throws ErrorAutoridad
     */
    public function anulacion(string $xml): string
    {
        return $this->operacion('B', null, $xml);
    }

    /**
     * @return array<string, array{estado: int, descripcion: string, comunicaciones: array<int, array{codigo: string|null, error: string|null}>}>
     *
     * @throws ErrorAutoridad
     */
    public function consulta(string $xml): array
    {
        $consulta = $this->llamar($this->sobreComunicacion('C', null, $xml));
        $lotes = [];
        foreach ($consulta->query('//*[local-name()="resultado"]') ?: [] as $resultado) {
            $lote = $this->hijo($consulta, $resultado, 'lote');
            if ($lote === null) {
                continue;
            }
            $comunicaciones = [];
            // El ejemplo del propio documento escribe «resutadoComunicacion»: se toma cualquier hijo del bloque.
            foreach ($consulta->query('./*[local-name()="resultadoComunicaciones"]/*', $resultado) ?: [] as $fila) {
                $orden = (int) $this->hijo($consulta, $fila, 'orden');
                if ($orden > 0) {
                    $error = trim((string) $this->hijo($consulta, $fila, 'tipoError').' '.(string) $this->hijo($consulta, $fila, 'error'));
                    $comunicaciones[$orden] = ['codigo' => $this->hijo($consulta, $fila, 'codigoComunicacion'), 'error' => $error === '' ? null : $error];
                }
            }
            $lotes[$lote] = ['estado' => (int) $this->hijo($consulta, $resultado, 'codigoEstado'), 'descripcion' => (string) $this->hijo($consulta, $resultado, 'descEstado'), 'comunicaciones' => $comunicaciones];
        }

        return $lotes;
    }

    private function operacion(string $operacion, ?string $tipoComunicacion, string $xml): string
    {
        $consulta = $this->llamar($this->sobreComunicacion($operacion, $tipoComunicacion, $xml));
        $lote = $this->primero($consulta, '//*[local-name()="respuesta"]/*[local-name()="lote"]');
        if ($lote === null) {
            throw new ErrorAutoridad(__('autoridad.error.respuesta_rara'));
        }

        return $lote;
    }

    private function sobreComunicacion(string $operacion, ?string $tipoComunicacion, string $xml): string
    {
        return $this->sobre('comunicacionRequest', function (DOMDocument $documento, DOMElement $peticion) use ($operacion, $tipoComunicacion, $xml): void {
            $cabecera = $documento->createElement('cabecera');
            $peticion->appendChild($cabecera);
            $this->anadir($documento, $cabecera, 'codigoArrendador', $this->arrendador);
            $this->anadir($documento, $cabecera, 'aplicacion', self::APLICACION);
            $this->anadir($documento, $cabecera, 'tipoOperacion', $operacion);
            if ($tipoComunicacion !== null) {
                $this->anadir($documento, $cabecera, 'tipoComunicacion', $tipoComunicacion);
            }
            $this->anadir($documento, $peticion, 'solicitud', self::comprimir($xml));
        });
    }

    /**
     * @param  callable(DOMDocument, DOMElement): void  $llenar
     */
    private function sobre(string $operacion, callable $llenar): string
    {
        $documento = new DOMDocument('1.0', 'UTF-8');
        $sobre = $documento->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $documento->appendChild($sobre);
        $sobre->appendChild($documento->createElementNS(self::NS_SOAP, 'soapenv:Header'));
        $cuerpo = $sobre->appendChild($documento->createElementNS(self::NS_SOAP, 'soapenv:Body'));
        $operacionNodo = $cuerpo->appendChild($documento->createElementNS(self::NS_SERVICIO, 'com:'.$operacion));
        $peticion = $documento->createElement('peticion');
        $operacionNodo->appendChild($peticion);
        $llenar($documento, $peticion);

        return (string) $documento->saveXML();
    }

    private function anadir(DOMDocument $documento, DOMElement $padre, string $nombre, string $texto): void
    {
        $elemento = $documento->createElement($nombre);
        $elemento->appendChild($documento->createTextNode($texto));
        $padre->appendChild($elemento);
    }

    /**
     * El servicio pide el XML comprimido en ZIP y en Base64 (error 10111 si no).
     */
    public static function comprimir(string $xml): string
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'ses');
        try {
            $zip = new ZipArchive();
            if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true || ! $zip->addFromString('solicitud.xml', $xml) || ! $zip->close()) {
                throw new ErrorAutoridad(__('autoridad.error.comprimir'));
            }

            return base64_encode((string) file_get_contents($ruta));
        } finally {
            @unlink($ruta);
        }
    }

    private function llamar(string $soap): DOMXPath
    {
        try {
            $respuesta = Http::withBasicAuth($this->usuario, $this->contrasena)
                ->withOptions(['verify' => resource_path('certificados/fnmt.pem')])
                ->withHeaders(['SOAPAction' => '""'])
                ->withBody($soap, 'text/xml; charset=utf-8')
                ->timeout(60)->connectTimeout(15)
                ->post(self::DIRECCIONES[$this->modo]);
        } catch (ConnectionException) {
            throw new ErrorAutoridad(__('autoridad.error.sin_conexion'));
        }
        if ($respuesta->status() === 401 || $respuesta->status() === 403) {
            throw new ErrorAutoridad(__('autoridad.error.credenciales'));
        }

        return $this->leer($respuesta);
    }

    private function leer(Response $respuesta): DOMXPath
    {
        $documento = new DOMDocument();
        if (! @$documento->loadXML($respuesta->body(), LIBXML_NONET)) {
            // En su ambiente de pruebas, un usuario que no existe contesta 502 después de medio minuto, y no 401.
            throw new ErrorAutoridad($respuesta->successful() ? __('autoridad.error.respuesta_rara') : __('autoridad.error.servicio', ['codigo' => $respuesta->status()]));
        }
        $consulta = new DOMXPath($documento);
        $falla = $this->primero($consulta, '//*[local-name()="faultstring"]');
        if ($falla !== null) {
            throw new ErrorAutoridad(__('autoridad.error.rechazo', ['detalle' => mb_substr($falla, 0, 300)]));
        }
        // La tabla del documento dice «codigo» y sus ejemplos, «codigoRetorno».
        $codigo = $this->primero($consulta, '(//*[local-name()="respuesta" or local-name()="resultado"]/*[local-name()="codigo" or local-name()="codigoRetorno"])[1]');
        if ($codigo !== null && $codigo !== '0') {
            $detalle = (string) $this->primero($consulta, '(//*[local-name()="respuesta" or local-name()="resultado"]/*[local-name()="descripcion"])[1]');

            throw new ErrorAutoridad(__('autoridad.error.rechazo', ['detalle' => trim($codigo.' '.mb_substr($detalle, 0, 300))]));
        }

        return $consulta;
    }

    private function primero(DOMXPath $consulta, string $ruta): ?string
    {
        $nodos = $consulta->query($ruta);
        $nodo = $nodos !== false ? $nodos->item(0) : null;

        return $nodo === null ? null : trim((string) $nodo->textContent);
    }

    private function hijo(DOMXPath $consulta, DOMNode $padre, string $nombre): ?string
    {
        $nodos = $consulta->query('./*[local-name()="'.$nombre.'"]', $padre);
        $nodo = $nodos !== false ? $nodos->item(0) : null;

        return $nodo === null ? null : trim((string) $nodo->textContent);
    }
}
