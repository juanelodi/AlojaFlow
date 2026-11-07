<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

use App\Autoridades\RegistroViajeros;

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
     * A quién hay que comunicarle las reservas y las llegadas; null si en este país no hay un registro al que se le pueda mandar.
     */
    public function registroViajeros(): ?RegistroViajeros;

    /**
     * El archivo para declarar las estancias de una tasa turística, si hay un formato oficial que se conozca.
     */
    public function declaracionEstancias(): ?DeclaracionEstancias;

    /**
     * Cómo guarda Excel una fecha en ese país: 03/04/2027 es el 3 de abril o el 4 de marzo.
     */
    public function fechaConMesPrimero(): bool;

    /**
     * En qué se dicen los largos de un lugar y de un equipo. Se usa como llega: no se convierte.
     */
    public function unidadLargo(): string;

    /**
     * Impuestos al hospedaje que se pueden proponer, por estado o provincia. Vacío si no se tiene un catálogo.
     *
     * @return list<SugerenciaImpuesto>
     */
    public function sugerenciasImpuesto(): array;

    /**
     * Los documentos que se proponen de partida, en un idioma.
     *
     * @return array<string, array{titulo: string, cuerpo: string}> tipo => texto
     */
    public function textosBase(string $idioma): array;

    /**
     * Lo que cambia por país en los canales de venta (D-149): comisiones distintas a la habitual y los impuestos y retenciones que aplican.
     *
     * @return array{comisiones: array<string, float>, cargos: list<array{clave: string, clase: \App\Enums\ClaseCargoCanal, valor: float, base: \App\Enums\BaseCargoCanal}>}
     */
    public function cargosDeCanales(): array;
}
