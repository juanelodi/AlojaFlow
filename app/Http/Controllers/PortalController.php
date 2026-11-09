<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Archivos\GestorArchivos;
use App\Comprobantes\GestorComprobantes;
use App\Dinero\GestorPoliticas;
use App\Documentos\GeneradorPdf;
use App\Enums\EstadoReserva;
use App\Http\Controllers\Concerns\RecibeComprobante;
use App\Llegada\GestorLlegadas;
use App\Models\Foto;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\Pasarelas;
use App\Pagos\CobrosEnLinea;
use App\Publico\GuiaDigital;
use App\Publico\MiReserva;
use App\Publico\PaginaPublica;
use App\Reservas\CodigosAcceso;
use App\Reservas\Mensualidades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Lo que ve el huésped sin cuenta: su reserva, la guía de la unidad y las fotos.
 */
class PortalController extends Controller
{
    use RecibeComprobante;

    public function __construct(private readonly MiReserva $miReserva, private readonly GuiaDigital $guia)
    {
    }

    public function foto(GestorArchivos $archivos, int $foto, string $tamano): Response
    {
        $fila = Foto::query()->with(['archivo', 'miniatura', 'propiedad'])->find($foto);
        abort_if($fila === null || ! $fila->propiedad->activa, 404);
        $archivo = $tamano === 'chica' ? $fila->miniatura : $fila->archivo;
        abort_if($archivo === null, 404);

        return response($archivos->contenido($archivo), 200, [
            'Content-Type' => 'image/jpeg',
            // Una foto nunca cambia: una nueva tiene otra dirección.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function miReserva(Request $request, string $token, CobrosEnLinea $cobros, GestorPoliticas $politicas, GestorLlegadas $llegadas): View
    {
        $reserva = $this->reservaDe($request, $token);
        $propiedad = $reserva->propiedad;
        $cancelada = $reserva->estado === EstadoReserva::Cancelada;
        $independiente = $propiedad->esIndependiente();
        $porCobrar = $independiente ? $cobros->porCobrar($reserva) : 0;
        $conLlegada = Modulos::activo(Modulo::Llegada) && ! $cancelada && $reserva->entrada_registrada_en === null && $reserva->salida->format('Y-m-d') >= $propiedad->hoy()->format('Y-m-d');
        $aplicable = $politicas->aplicable($reserva);

        return view('publico.mi-reserva', [
            'propiedad' => $propiedad,
            'reserva' => $reserva,
            'token' => $token,
            'pagina' => new PaginaPublica($propiedad),
            'porCobrar' => $porCobrar,
            'pasarelas' => $independiente && ! $cancelada && Modulos::activo(Modulo::Motor) ? array_keys((new Pasarelas($propiedad))->disponibles()) : [],
            'espera' => $independiente && ! $cancelada && $porCobrar > 0 ? $cobros->enEspera($reserva) : null,
            'enlaceLlegada' => $conLlegada ? $llegadas->urlDe($reserva, app()->getLocale()) : null,
            'guia' => $this->guia->secciones($propiedad, $reserva->unidad_id, app()->getLocale(), $reserva),
            // Sus condiciones, no el resumen para el personal, que habla de lo que hay que devolverle.
            'politica' => $cancelada || $aplicable === null ? null : $politicas->textoDe($aplicable),
            'puedeCancelar' => $this->miReserva->puedeCancelarSolo($reserva),
            'aviso' => $request->query('aviso'),
            'calendario' => $independiente && ! $cancelada ? Mensualidades::de($reserva) : null,
            'ajustes' => new AjustesMotor($propiedad),
            'comprobantes' => $reserva->comprobantes()->latest('id')->limit(20)->get(),
            'codigoAcceso' => CodigosAcceso::visibleParaHuesped($reserva) ? CodigosAcceso::de($reserva) : null,
        ]);
    }

    /**
     * La transferencia que ya hizo (D-097, D-108). Solo donde AlojaFlow lleva los pagos.
     */
    public function comprobante(Request $request, string $token, GestorComprobantes $comprobantes): RedirectResponse
    {
        $reserva = $this->reservaDe($request, $token);
        abort_unless($reserva->propiedad->esIndependiente() && (new AjustesMotor($reserva->propiedad))->aceptaComprobantes(), 404);

        return redirect()->to(route('mi-reserva.ver', ['token' => $token, 'idioma' => app()->getLocale(), 'aviso' => $this->recibirComprobante($request, $reserva, $comprobantes)]).'#comprobante');
    }

    public function pedir(Request $request, string $token): RedirectResponse
    {
        $reserva = $this->reservaDe($request, $token);
        $datos = $request->validate(['mensaje' => ['required', 'string', 'min:3', 'max:2000'], 'sitio_web' => ['prohibited']], [], ['mensaje' => __('portal.mensaje')]);
        $this->miReserva->pedir($reserva, (string) $datos['mensaje']);

        return redirect()->route('mi-reserva.ver', ['token' => $token, 'idioma' => app()->getLocale(), 'aviso' => 'pedido']);
    }

    public function cancelar(Request $request, string $token): RedirectResponse
    {
        $reserva = $this->reservaDe($request, $token);

        return redirect()->route('mi-reserva.ver', ['token' => $token, 'idioma' => app()->getLocale(), 'aviso' => $this->miReserva->cancelar($reserva) ? 'cancelada' : 'no_cancelada']);
    }

    public function confirmacion(Request $request, string $token, GeneradorPdf $pdf): Response
    {
        $reserva = $this->reservaDe($request, $token);

        return response($pdf->confirmacion($reserva), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reserva-'.$reserva->codigo_publico.'.pdf"',
        ]);
    }

    public function guia(Request $request, string $token): View
    {
        $unidad = $this->guia->unidadDelToken($token);
        abort_if($unidad === null, 404);
        $propiedad = $unidad->propiedad;
        $this->fijarIdioma($request, $propiedad->idioma);

        return view('publico.guia', [
            'propiedad' => $propiedad,
            'unidad' => $unidad,
            'pagina' => new PaginaPublica($propiedad),
            'guia' => $this->guia->secciones($propiedad, $unidad->id, app()->getLocale()),
        ]);
    }

    private function reservaDe(Request $request, string $token): Reserva
    {
        $reserva = $this->miReserva->reservaDelToken($token);
        abort_if($reserva === null, 404);
        $this->fijarIdioma($request, $reserva->propiedad->idioma);

        return $reserva;
    }

    private function fijarIdioma(Request $request, string $principal): void
    {
        $idiomas = (array) config('alojaflow.idiomas');
        $idioma = $request->query('idioma');
        if (! in_array($idioma, $idiomas, true)) {
            $idioma = $request->headers->has('Accept-Language') ? substr((string) $request->getPreferredLanguage($idiomas), 0, 2) : $principal;
        }
        app()->setLocale(in_array($idioma, $idiomas, true) ? $idioma : $principal);
    }
}
