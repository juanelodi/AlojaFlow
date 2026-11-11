<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use Illuminate\Support\Str;

/**
 * Lo que contestó la principal a una petición, leído como texto: nunca se interpreta ni se ejecuta. Sus mensajes
 * salen en el idioma de la principal, así que se reconoce por marcadores y por la estructura de la página
 * (subordinacion.md A3); solo el límite diario y los errores de escritura y de borrado se buscan por palabras.
 */
final class RespuestaPrincipal
{
    public const MARCADOR_COPIA_CREADA = '<!-- Backup_created -->';

    private const EXTRACTO_MAXIMO = 500;

    /** Palabras de la principal en italiano, español e inglés, ya sin acentos y en minúsculas. */
    private const LIMITE_DIARIO = '/limit\w*.{0,80}backup|backup\w*.{0,80}limit/s';

    private const SIN_ESCRITURA = '/(permis|permess|permission|privileg)\w*.{0,80}(escri|scri|writ)|(escri|scri|writ)\w*.{0,80}(permis|permess|permission|privileg)/s';

    /** Un «no se pudo» pegado al verbo de borrar: la página correcta también puede decir «no hay copias». */
    private const BORRADO_FALLIDO = '/\b(no se (ha |han )?(pud|pod)\w+|imposible|non e stato possibile|impossibile|non riesc\w+|could not|couldn t|unable to|cannot|can t|not possible to|failed to)\W+(\w+\W+){0,3}?(borr|elimin|cancel|delet|remov)/';

    public function __construct(
        private readonly int $estado,
        private readonly string $cuerpo,
        private readonly ?string $destinoRedireccion = null,
    ) {
    }

    public function esRedireccion(): bool
    {
        return $this->estado >= 300 && $this->estado < 400;
    }

    /**
     * Adónde mandaba la redirección, sin consulta: en el modo por GET podría repetir las credenciales.
     */
    public function destinoRedireccion(): string
    {
        return mb_substr((string) preg_replace('/[?#].*$/s', '', (string) $this->destinoRedireccion), 0, 200);
    }

    public function vacia(): bool
    {
        return trim($this->cuerpo) === '';
    }

    /** El formulario de acceso: la principal no aceptó al usuario. */
    public function pideCredenciales(): bool
    {
        return preg_match('/name\s*=\s*["\']?password_phpr\b/i', $this->cuerpo) === 1;
    }

    /** El formulario de copias, con su botón «crea_backup». */
    public function ofreceCrearCopia(): bool
    {
        return preg_match('/name\s*=\s*["\']?crea_backup\b/i', $this->cuerpo) === 1;
    }

    /**
     * Solo la cabecera de la página, con su hoja de estilo: así contesta a un usuario con privilegio de copia que no
     * tiene activado el año, y las copias le funcionan.
     */
    public function soloCabecera(): bool
    {
        return str_contains($this->cuerpo, '--t1color');
    }

    public function copiaCreada(): bool
    {
        return str_contains($this->cuerpo, self::MARCADOR_COPIA_CREADA);
    }

    public function alcanzoLimiteDiario(): bool
    {
        return preg_match(self::LIMITE_DIARIO, $this->palabras()) === 1;
    }

    public function noPudoEscribir(): bool
    {
        return preg_match(self::SIN_ESCRITURA, $this->palabras()) === 1;
    }

    public function noPudoBorrar(): bool
    {
        return preg_match(self::BORRADO_FALLIDO, $this->palabras()) === 1;
    }

    /**
     * El texto visible de la página, sin etiquetas, estilos ni guiones, para enseñarlo en un aviso.
     */
    public function extracto(): string
    {
        $texto = mb_scrub($this->cuerpo, 'UTF-8');
        $texto = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $texto);
        $texto = (string) preg_replace('/<!--.*?-->/s', ' ', $texto);
        $texto = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $texto));
        $prefijo = $this->estado >= 400 ? 'HTTP '.$this->estado.($texto === '' ? '' : ': ') : '';

        return $prefijo.mb_substr($texto, 0, self::EXTRACTO_MAXIMO - mb_strlen($prefijo));
    }

    /**
     * Sin acentos, en minúsculas y sin signos: HotelDruid 3.0.8 en español escribe «lìmite».
     */
    private function palabras(): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($this->extracto())));
    }
}
