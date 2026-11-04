<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

use App\Facturacion\GeneradorFiscal;

/**
 * Lo que cambia de un país a otro. Todo lo legal va detrás de esta interfaz: el resto del código no
 * pregunta en qué país está (CLAUDE.md, regla de países).
 */
interface Pais
{
    /**
     * @return array<string, string> código => nombre, para el formulario de llegada
     */
    public function tiposDocumento(): array;

    /**
     * Datos extra del registro: campo => [etiqueta, ¿obligatorio?, ¿solo el titular?].
     *
     * @return array<string, array{etiqueta: string, requerido: bool, solo_titular: bool}>
     */
    public function camposLlegada(): array;

    /**
     * ¿A quién se le pide el documento? A todos, o solo a quien reserva.
     */
    public function documentoDeTodos(): bool;

    /** Días tras la salida que se conservan las imágenes de los documentos. */
    public function diasConservarImagenes(): int;

    /** Días tras la salida que se conservan los datos del registro. */
    public function diasConservarRegistro(): int;

    /**
     * Si los textos y plazos de este país están revisados contra sus fuentes oficiales.
     */
    public function legalRevisado(): bool;

    /**
     * El documento fiscal del país; null si solo se emiten recibos sin valor fiscal.
     */
    public function generadorFiscal(): ?GeneradorFiscal;

    /**
     * Los documentos que se proponen de partida, en un idioma.
     *
     * @return array<string, array{titulo: string, cuerpo: string}> tipo => texto
     */
    public function textosBase(string $idioma): array;
}
