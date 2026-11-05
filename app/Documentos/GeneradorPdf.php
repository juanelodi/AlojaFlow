<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Documentos;

use App\Enums\EstadoReserva;
use App\Models\Aceptacion;
use App\Models\DocumentoLegal;
use App\Models\EstadoCuenta;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Paises\Paises;
use App\Reservas\Cotizador;
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

    /**
     * Un documento del alojamiento (D-123), ya lleno por MotorDocumentos.
     */
    public function documentoPropio(string $cuerpo, string $titulo, Propiedad $propiedad, ?Reserva $reserva, ?string $idioma): string
    {
        return $this->pdf('documentos.propio', ['cuerpo' => $cuerpo, 'titulo' => $titulo, 'propiedad' => $propiedad] + ($reserva === null ? [] : ['reserva' => $reserva]), $idioma);
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
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->output();
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
            $impuestos = Cotizador::presupuesto($reserva->unidad, $reserva->llegada, $reserva->salida, max(1, $reserva->personas), tarifaId: $reserva->tarifa_id)->impuestos;
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
            return Pdf::loadView($vista, $datos)->setPaper('letter')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])->output();
        } finally {
            app()->setLocale($anterior);
        }
    }
}
