<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Lee una copia de HotelDruid (subordinacion.md, parte B) y entrega, fila por fila, las tablas que se conservan.
 *
 * La copia parece XML pero no lo es, y trae código PHP de la principal: se recorre como texto, de arriba abajo y en
 * el orden en que la escribe la principal (cabecera, archivos, tablas y cierre), sin analizador XML y sin ejecutar
 * nada (regla 11). Lo que no se conserva se salta sin decodificar.
 */
class LectorCopia
{
    public const PRIMERA_LINEA = '<?php exit(); ?>';

    private const PASO = 'leer';

    private const BYTES_MAXIMOS_POR_VALOR = 16 * 1024 * 1024;

    /** La cabecera son unos cuantos comentarios: si `<backup>` no llega pronto, no es una copia. */
    private const LINEAS_DE_CABECERA = 40;

    /** De un archivo de configuración solo se lee esto. */
    private const BYTES_DE_ARCHIVO = 65536;

    private const ARCHIVO_IDIOMA = 'lingua.php';

    private const ARCHIVO_INICIO_SESION = 'abilita_login';

    private const SEPARADOR = '@%&@';

    /** Lo que la principal escapa dentro de un valor, en el orden en que se deshace (B3). */
    private const ESCAPES = [
        '</righetabella@%&@>' => '</righetabella>',
        '</riga@%&@>' => '</riga>',
        '<riga@%&@>' => '<riga>',
        '</cmp@%&@>' => '</cmp>',
        '@%&@@%&@' => '@%&@',
    ];

    private LineasCopia $lineas;

    private int $bytesMaximosPorFila;

    /** @var array<string, int> */
    private array $filas;

    /** @var array<string, true> */
    private array $tablasVistas;

    /** @var list<int> */
    private array $anios;

    /** @var array<string, int> */
    private array $incidencias;

    /**
     * @param  callable(string, int|null, array<string, string|null>): void  $alFila  recibe la tabla sin su año, el año y la fila
     */
    public function leer(string $ruta, callable $alFila): ResumenCopia
    {
        $this->filas = $this->tablasVistas = $this->anios = $this->incidencias = [];
        $this->bytesMaximosPorFila = (int) config('alojaflow.subordinacion.tamano_maximo_linea');
        $this->lineas = new LineasCopia($ruta, $this->bytesMaximosPorFila);

        try {
            $fecha = $this->cabecera();
            $version = $this->version();
            [$idioma, $inicioSesion] = $this->archivos();
            $this->tablas($alFila);
            $this->cierre();
        } finally {
            $this->lineas->cerrar();
        }
        $this->comprobarTablas();

        return new ResumenCopia($version, $fecha, $idioma, $inicioSesion, $this->filas, $this->anios, $this->incidencias);
    }

    /**
     * De la primera línea a `<backup>`. Devuelve la hora de la copia, que va en un comentario.
     */
    private function cabecera(): ?string
    {
        if ($this->lineas->siguiente() !== self::PRIMERA_LINEA) {
            throw $this->invalida();
        }
        $fecha = null;
        for ($i = 0; $i < self::LINEAS_DE_CABECERA; $i++) {
            $linea = $this->lineas->siguiente();
            if ($linea === null) {
                break;
            }
            if ($linea === '<backup>') {
                return $fecha;
            }
            if ($fecha === null && preg_match('/^<!--\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s*-->$/', $linea, $partes) === 1) {
                $fecha = $partes[1];
            }
        }

        throw $this->invalida();
    }

    private function version(): string
    {
        $version = $this->contenido('versione', $this->lineaConAlgo());
        if ($version === null) {
            throw $this->invalida();
        }
        if (! in_array($version, Esquema::VERSIONES_ADMITIDAS, true)) {
            $legible = preg_match('/^[0-9.]{1,10}$/', $version) === 1 ? $version : '?';
            throw new ErrorSincronizacion(CodigoError::VersionNoAdmitida, self::PASO, __('sincronizacion.version_no_admitida', ['version' => $legible]));
        }

        return $version;
    }

    /**
     * Los bloques `<file>`, hasta `<database>`. Son archivos de configuración de la principal escritos en PHP: del de
     * idioma se saca el idioma con un patrón fijo, del de inicio de sesión solo importa que esté, y lo demás se tira.
     *
     * @return array{0: string|null, 1: bool}
     */
    private function archivos(): array
    {
        $idioma = null;
        $inicioSesion = false;
        while (true) {
            $linea = $this->lineaConAlgo();
            if ($linea === '<database>') {
                return [$idioma, $inicioSesion];
            }
            if ($this->contenido('log', $linea) !== null) {
                continue;
            }
            if ($linea !== '<file>') {
                throw $this->invalida();
            }

            $nombre = basename(str_replace('\\', '/', (string) $this->contenido('nomefile', $this->lineaConAlgo())));
            $texto = $this->contenidoDeArchivo();
            if ($nombre === self::ARCHIVO_INICIO_SESION) {
                $inicioSesion = true;
            } elseif ($nombre === self::ARCHIVO_IDIOMA && preg_match('/\$lingua\[1\]\s*=\s*"([a-z]{2,3})"\s*;/', $texto, $partes) === 1) {
                $idioma = $partes[1];
            }
        }
    }

    /**
     * Lo que hay entre `<contenuto>` y `</contenuto>`, que va pegado al final de su última línea. Solo el principio:
     * nada de esto se guarda.
     */
    private function contenidoDeArchivo(): string
    {
        if ($this->lineaConAlgo() !== '<contenuto>') {
            throw $this->invalida();
        }
        $texto = '';
        do {
            $linea = $this->linea();
            if (strlen($texto) < self::BYTES_DE_ARCHIVO) {
                $texto .= $linea."\n";
            }
        } while (! str_ends_with($linea, '</contenuto>'));

        if ($this->lineaConAlgo() !== '</file>') {
            throw $this->invalida();
        }

        return $texto;
    }

    /**
     * @param  callable(string, int|null, array<string, string|null>): void  $alFila
     */
    private function tablas(callable $alFila): void
    {
        while (($linea = $this->lineaConAlgo()) !== '</database>') {
            if ($linea !== '<tabella>') {
                throw $this->invalida();
            }
            $this->tabla($alFila);
        }
    }

    /**
     * @param  callable(string, int|null, array<string, string|null>): void  $alFila
     */
    private function tabla(callable $alFila): void
    {
        $nombre = (string) $this->contenido('nometabella', $this->lineaConAlgo());
        if (preg_match('/^[a-z]+([0-9]{4})?$/', $nombre) !== 1) {
            throw $this->invalida();
        }
        $columnas = $this->columnas();
        if ($this->lineaConAlgo() !== '<righetabella>') {
            throw $this->invalida();
        }

        [$base, $anio] = Esquema::separar($nombre);
        $this->tablasVistas[$nombre] = true;
        if (Esquema::seConserva($base, $anio)) {
            $this->filas[$nombre] = 0;
            while (($fila = $this->fila($columnas)) !== null) {
                $this->filas[$nombre]++;
                if ($nombre === 'anni' && ctype_digit((string) ($fila['idanni'] ?? ''))) {
                    $this->anios[] = (int) $fila['idanni'];
                }
                $alFila($base, $anio, $fila);
            }
        } else {
            // Contraseñas, sesiones, claves: ni siquiera se decodifican.
            while ($this->linea() !== '</righetabella>');
        }

        if ($this->lineaConAlgo() !== '</tabella>') {
            throw $this->invalida();
        }
    }

    /**
     * @return list<string>
     */
    private function columnas(): array
    {
        if ($this->lineaConAlgo() !== '<colonnetabella>') {
            throw $this->invalida();
        }
        $columnas = [];
        while (($linea = $this->lineaConAlgo()) !== '</colonnetabella>') {
            // El tipo depende del motor de la principal y no se usa (B5).
            if ($this->contenido('tipocolonna', $linea) !== null) {
                continue;
            }
            $columna = (string) $this->contenido('nomecolonna', $linea);
            if (preg_match('/^[a-z0-9_]+$/', $columna) !== 1) {
                throw $this->invalida();
            }
            $columnas[] = $columna;
        }
        if ($columnas === []) {
            throw $this->invalida();
        }

        return $columnas;
    }

    /**
     * La fila siguiente de la tabla, o nulo al llegar a `</righetabella>`. Empieza en una línea que comienza por
     * `<riga>` y acaba en la primera que termina en `</riga>`: un valor puede ocupar varias líneas.
     *
     * @param  list<string>  $columnas
     * @return array<string, string|null>|null
     */
    private function fila(array $columnas): ?array
    {
        $linea = $this->linea();
        if ($linea === '</righetabella>') {
            return null;
        }
        if (! str_starts_with($linea, '<riga><cmp>')) {
            throw $this->invalida();
        }
        $texto = $linea;
        while (! str_ends_with($linea, '</riga>')) {
            $linea = $this->linea();
            $texto .= "\n".$linea;
            if (strlen($texto) > $this->bytesMaximosPorFila) {
                throw $this->invalida();
            }
        }
        if (! str_ends_with($texto, '</cmp></riga>')) {
            throw $this->invalida();
        }

        $valores = explode('</cmp><cmp>', substr($texto, 11, -13));
        if (count($valores) !== count($columnas)) {
            throw $this->invalida();
        }

        return array_combine($columnas, array_map($this->valor(...), $valores));
    }

    /**
     * Deshace los escapes de la principal. Un vacío es un nulo: la copia los escribe igual (B4).
     */
    private function valor(string $crudo): ?string
    {
        if ($crudo === '') {
            return null;
        }
        if (strlen($crudo) > self::BYTES_MAXIMOS_POR_VALOR) {
            throw $this->invalida();
        }
        if (str_contains($crudo, self::SEPARADOR)) {
            $crudo = str_replace(array_keys(self::ESCAPES), array_values(self::ESCAPES), $crudo);
        }
        if (! mb_check_encoding($crudo, 'UTF-8')) {
            $this->incidencias['valores_no_utf8'] = ($this->incidencias['valores_no_utf8'] ?? 0) + 1;
            $anterior = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            $crudo = mb_scrub($crudo, 'UTF-8');
            mb_substitute_character($anterior);
        }

        return $crudo;
    }

    /**
     * De `</database>` a `</backup>`. En medio pueden venir modelos de documentos, que no se piden ni se leen.
     */
    private function cierre(): void
    {
        while ($this->linea() !== '</backup>');
    }

    /**
     * Las mismas tablas que la principal exige para restaurar: las globales y, por cada año, sus nueve tablas.
     */
    private function comprobarTablas(): void
    {
        $esperadas = Esquema::TABLAS_GLOBALES_OBLIGATORIAS;
        foreach ($this->anios as $anio) {
            foreach (Esquema::TABLAS_ANUALES as $tabla) {
                $esperadas[] = $tabla.$anio;
            }
        }
        foreach ($esperadas as $tabla) {
            if (! isset($this->tablasVistas[$tabla])) {
                throw new ErrorSincronizacion(CodigoError::CopiaIncompleta, self::PASO, __('sincronizacion.falta_tabla', ['tabla' => $tabla]));
            }
        }
        foreach (array_keys($this->tablasVistas) as $tabla) {
            [$base, $anio] = Esquema::separar($tabla);
            if ($anio !== null && in_array($base, Esquema::TABLAS_ANUALES, true) && ! in_array($anio, $this->anios, true)) {
                throw $this->invalida();
            }
        }
    }

    /**
     * La línea siguiente. Si la copia se acaba antes de su cierre, llegó cortada.
     *
     * @phpstan-impure
     */
    private function linea(): string
    {
        return $this->lineas->siguiente()
            ?? throw new ErrorSincronizacion(CodigoError::CopiaIncompleta, self::PASO, __('sincronizacion.copia_cortada'));
    }

    /**
     * La línea siguiente que no esté vacía: entre una etiqueta y otra puede haber renglones en blanco.
     *
     * @phpstan-impure
     */
    private function lineaConAlgo(): string
    {
        do {
            $linea = $this->linea();
        } while ($linea === '');

        return $linea;
    }

    /**
     * Lo que hay dentro de una etiqueta que ocupa toda la línea (`<versione>3.081</versione>`), o nulo si la línea es otra cosa.
     */
    private function contenido(string $etiqueta, string $linea): ?string
    {
        $abre = '<'.$etiqueta.'>';
        $cierra = '</'.$etiqueta.'>';
        if (! str_starts_with($linea, $abre) || ! str_ends_with($linea, $cierra) || strlen($linea) < strlen($abre) + strlen($cierra)) {
            return null;
        }

        return substr($linea, strlen($abre), -strlen($cierra));
    }

    private function invalida(): ErrorSincronizacion
    {
        return new ErrorSincronizacion(CodigoError::FormatoInvalido, self::PASO);
    }
}
