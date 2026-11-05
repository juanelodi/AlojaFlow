<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Documentos;

use App\Archivos\GestorArchivos;
use App\Enums\EstadoFactura;
use App\Enums\EstadoReserva;
use App\Enums\TipoDocumentoFiscal;
use App\Facturacion\DatosTimbre;
use App\Models\Aceptacion;
use App\Models\DocumentoLegal;
use App\Models\EstadoCuenta;
use App\Models\Factura;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Paises\Paises;
use App\Reservas\Cotizador;
use App\Support\Formato;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Documentos en PDF de una reserva: confirmación y recibos. Sin recursos externos, para que se
 * generen igual en cualquier hosting.
 */
final class GeneradorPdf
{
    public function confirmacion(Reserva $reserva): string
    {
        return $this->pdf('documentos.confirmacion', $this->datos($reserva), $reserva->idioma);
    }

    public function estadoCuenta(EstadoCuenta $estado): string
    {
        return $this->pdf('documentos.estado-cuenta', ['estado' => $estado, 'propiedad' => $estado->propiedad], $estado->propietario->idioma ?? $estado->propiedad->idioma);
    }

    public function recibo(Pago $pago): string
    {
        $reserva = $pago->reserva;

        return $this->pdf('documentos.recibo', $this->datos($reserva) + ['pago' => $pago], $reserva->idioma);
    }

    public function copiaAceptacion(Aceptacion $aceptacion, DocumentoLegal $documento, RegistroLlegada $registro): string
    {
        return $this->pdf('documentos.copia-aceptacion', ['aceptacion' => $aceptacion, 'documento' => $documento, 'registro' => $registro], $documento->idioma);
    }

    public function copiaAceptacionGrupo(Aceptacion $aceptacion, DocumentoLegal $documento, string $texto, Grupo $grupo): string
    {
        return $this->pdf('documentos.copia-aceptacion-grupo', ['aceptacion' => $aceptacion, 'documento' => $documento, 'texto' => $texto, 'grupo' => $grupo], $documento->idioma);
    }

    /**
     * La lista para tener a mano el día que llegan.
     */
    public function listaGrupo(Grupo $grupo): string
    {
        $reservas = $grupo->reservas()->with(['unidad', 'habitacionGrupo'])->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('llegada')->orderBy('id')->get();

        return $this->pdf('documentos.lista-grupo', ['grupo' => $grupo, 'reservas' => $reservas], null);
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<list<string>>  $filas
     */
    public function informe(string $titulo, string $propiedades, string $periodo, array $columnas, array $filas): string
    {
        return Pdf::loadView('documentos.informe', ['titulo' => $titulo, 'propiedades' => $propiedades, 'periodo' => $periodo, 'columnas' => $columnas, 'filas' => $filas])
            ->setPaper('letter', count($columnas) > 5 ? 'landscape' : 'portrait')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])
            ->output();
    }

    public function factura(Factura $factura): string
    {
        $factura->loadMissing(['lineas.impuestos', 'propiedad']);
        $impuestos = [];
        foreach ($factura->lineas as $linea) {
            foreach ($linea->impuestos as $impuesto) {
                $impuestos[$impuesto->nombre.' '.(float) $impuesto->tasa.' %'] = ($impuestos[$impuesto->nombre.' '.(float) $impuesto->tasa.' %'] ?? 0) + $impuesto->importe;
            }
        }
        $esFiscal = $factura->tipo === TipoDocumentoFiscal::Factura && Paises::de($factura->propiedad)->generadorFiscal() !== null;
        $sinValidez = match (true) {
            $factura->estado === EstadoFactura::Cancelada => __('dinero.cancelacion.aviso_pdf', ['fecha' => Formato::momento($factura->cancelada_en, $factura->propiedad->zona_horaria)]),
            ! $esFiscal => __('dinero.facturas.no_fiscal'),
            $factura->uuid_fiscal === null => __('dinero.facturas.sin_timbrar'),
            default => null,
        };

        $timbre = $factura->uuid_fiscal !== null && $factura->xml !== null ? DatosTimbre::de(app(GestorArchivos::class)->contenido($factura->xml)) : null;

        return $this->pdf('documentos.factura', ['factura' => $factura, 'impuestos' => $impuestos, 'sinValidez' => $sinValidez, 'timbre' => $timbre], $factura->propiedad->idioma);
    }

    public function registroLlegada(RegistroLlegada $registro): string
    {
        $registro->loadMissing(['personas', 'reserva.unidad', 'propiedad']);
        $pais = Paises::de($registro->propiedad);

        return $this->pdf('documentos.registro-llegada', [
            'registro' => $registro,
            'reserva' => $registro->reserva,
            'propiedad' => $registro->propiedad,
            'tipos' => $pais->tiposDocumento(),
            'campos' => $pais->camposLlegada(),
        ], $registro->propiedad->idioma);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Reserva $reserva): array
    {
        $reserva->loadMissing(['propiedad', 'unidad', 'titular.contactos', 'cargos', 'pagos']);
        // Lo que se guardó al crearla; las de antes de la 1.0.15 no lo tienen, y se calcula con los datos de hoy.
        $impuestos = $reserva->impuestos()->where('cobrado_por_canal', false)->get(['nombre', 'importe', 'incluido'])->toArray();
        if ($impuestos === []) {
            $impuestos = Cotizador::presupuesto($reserva->unidad, $reserva->llegada, $reserva->salida, max(1, $reserva->personas))->impuestos;
        }
        $alojamiento = $reserva->total - (int) $reserva->cargos->sum('importe');

        return [
            'reserva' => $reserva,
            'propiedad' => $reserva->propiedad,
            'alojamiento' => $alojamiento,
            'impuestos' => $impuestos,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdf(string $vista, array $datos, ?string $idioma): string
    {
        $anterior = app()->getLocale();
        if ($idioma !== null && in_array($idioma, (array) config('alojaflow.idiomas'), true)) {
            app()->setLocale($idioma);
        }
        try {
            return Pdf::loadView($vista, $datos)->setPaper('letter')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output();
        } finally {
            app()->setLocale($anterior);
        }
    }
}
