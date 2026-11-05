<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Publico;

use App\Enums\EstadoReserva;
use App\Enums\MomentoGuia;
use App\Enums\TipoEnlace;
use App\Models\EnlacePublico;
use App\Models\GuiaSeccion;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * La guía de la estancia (D-106). Dos puertas: el QR de la unidad, que está pegado en la habitación pero cualquiera puede fotografiar, y «Mi reserva», que es personal y
 * caduca. **Lo delicado, como la clave de la puerta, solo sale por la segunda, y solo mientras dura la estancia.**
 */
final class GuiaDigital
{
    /**
     * @return list<array{tipo: string, titulo: string, cuerpo: string}>
     */
    public function secciones(Propiedad $propiedad, ?int $unidadId, string $idioma, ?Reserva $reserva = null): array
    {
        $hoy = $propiedad->hoy()->format('Y-m-d');
        $filas = GuiaSeccion::query()->where('propiedad_id', $propiedad->id)->where('activa', true)
            ->where(fn ($consulta) => $consulta->whereNull('unidad_id')->orWhere('unidad_id', $unidadId))
            ->orderBy('orden')->orderBy('id')->get();
        $lista = [];
        foreach ($filas as $seccion) {
            if (! $this->seVe($seccion, $hoy, $reserva)) {
                continue;
            }
            $titulo = Textos::elegir($seccion->titulo, $idioma, $propiedad->idioma);
            $cuerpo = Textos::elegir($seccion->cuerpo, $idioma, $propiedad->idioma);
            if ($cuerpo !== null) {
                $lista[] = ['tipo' => $seccion->tipo->value, 'titulo' => $titulo ?? $seccion->tipo->etiqueta(), 'cuerpo' => $cuerpo];
            }
        }

        return $lista;
    }

    private function seVe(GuiaSeccion $seccion, string $hoy, ?Reserva $reserva): bool
    {
        if ($reserva === null) {
            return ! $seccion->solo_con_reserva && in_array($seccion->momento, [MomentoGuia::Siempre, MomentoGuia::Durante], true);
        }
        if ($reserva->estado === EstadoReserva::Cancelada) {
            return ! $seccion->solo_con_reserva && $seccion->momento === MomentoGuia::Siempre;
        }
        $llegada = $reserva->llegada->format('Y-m-d');
        $salida = $reserva->salida->format('Y-m-d');
        $durante = $hoy >= $llegada && $hoy <= $salida;
        if ($seccion->solo_con_reserva && ! $durante) {
            return false;
        }
        if ($seccion->requiere_pago && $reserva->pendiente > 0) {
            return false;
        }

        return match ($seccion->momento) {
            MomentoGuia::Siempre => true,
            MomentoGuia::Antes => $hoy <= $llegada,
            MomentoGuia::Durante => $durante,
            MomentoGuia::Despues => $hoy >= $salida,
        };
    }

    public function enlaceDe(Unidad $unidad): EnlacePublico
    {
        $existente = EnlacePublico::query()->where('objeto_type', $unidad->getMorphClass())->where('objeto_id', $unidad->id)
            ->where('tipo', TipoEnlace::Guia->value)->whereNull('revocado_en')->latest('id')->first();

        return $existente ?? EnlacePublico::nuevo($unidad->propiedad_id, TipoEnlace::Guia, $unidad);
    }

    public function urlDe(Unidad $unidad): string
    {
        return route('guia.ver', ['token' => $this->enlaceDe($unidad)->token()]);
    }

    /**
     * Por si alguien se llevó la foto del QR: el anterior deja de valer y hay que imprimirlo otra vez.
     */
    public function cambiarEnlace(Unidad $unidad): void
    {
        EnlacePublico::query()->where('objeto_type', $unidad->getMorphClass())->where('objeto_id', $unidad->id)
            ->where('tipo', TipoEnlace::Guia->value)->whereNull('revocado_en')->update(['revocado_en' => now('UTC')]);
    }

    public function unidadDelToken(string $token): ?Unidad
    {
        $enlace = EnlacePublico::buscar(TipoEnlace::Guia, $token);
        $objeto = $enlace !== null && $enlace->vigente() ? $enlace->objeto : null;

        return $objeto instanceof Unidad ? $objeto : null;
    }

    /**
     * @return string un SVG, que se imprime con la nitidez que haga falta
     */
    public function qr(string $url): string
    {
        return (new QRCode(new QROptions(['outputInterface' => QRMarkupSVG::class, 'eccLevel' => EccLevel::M, 'outputBase64' => false, 'svgAddXmlHeader' => false])))->render($url);
    }
}
