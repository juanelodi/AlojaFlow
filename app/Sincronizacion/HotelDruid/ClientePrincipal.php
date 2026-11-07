<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use App\Enums\MetodoPeticion;
use App\Models\Principal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Habla con crea_backup.php de la principal: probar, crear, descargar y borrar la copia
 * (subordinacion.md A3). Nunca envía otro parámetro ni llama a otro archivo de la principal.
 */
class ClientePrincipal
{
    /** Lo que se espera de las respuestas que no son la copia: unas pocas líneas de texto. */
    private const BYTES_MAXIMOS_RESPUESTA = 1048576;

    /** Lista blanca: la garantía técnica de que AlojaFlow nunca escribe en la principal. */
    public const PARAMETROS_PERMITIDOS = [
        'nome_utente_phpr', 'password_phpr', 'anno', 'azione', 'crea_backup', 'mostra_header',
        'salva_backup', 'compresso', 'cancella_backup', 'id_sessione',
    ];

    public const ARCHIVO = 'crea_backup.php';

    private const MARCA_COPIA_CREADA = '<!-- Backup_created -->';

    private const CAMPO_ACCESO = 'name="password_phpr"';

    private const BOTON_COPIA = 'name="crea_backup"';

    /** Está en includes/head.php y en la de cada tema, también con la marca de HotelDruid oculta. */
    private const MARCA_CABECERA = '--t1color:';

    private const TEXTO_ANIO_INEXISTENTE = 'The requested year does not exist';

    // Mensajes de la principal en italiano, español e inglés (idiomas de 1.2.x), sin acentos: HotelDruid 3.0.8 escribe «lìmite», con acento grave.
    private const TEXTOS_LIMITE = ['limite giornaliero di backup', 'limite de backups diarios', 'limit of daily backups'];

    private const TEXTOS_SIN_ESCRITURA = ['permesso di scrittura sul file', 'permiso de escritura sobre el archivo', 'write permissions on the file'];

    private const TEXTOS_ERROR_BORRADO = ['errore cancellando il file', 'error al borrar el archivo', 'error deleting the file'];


    public function __construct(
        private readonly Principal $principal,
        private readonly Factory $http,
        private readonly ValidadorDestino $validador,
    ) {
    }

    /**
     * Paso 0: entró y puede hacer copias. La cabecera de la página solo sale con el privilegio de copia, y el
     * formulario, además, si el usuario tiene activado el año que se consulta: sin él, las copias funcionan igual.
     */
    public function probarConexion(): void
    {
        $cuerpo = $this->peticion([], 'probar')->body();
        $this->detectarErroresComunes($cuerpo, 'probar');

        if (str_contains($cuerpo, self::BOTON_COPIA) || str_contains($cuerpo, self::MARCA_CABECERA)) {
            return;
        }
        if (trim($cuerpo) === '') {
            throw new ErrorSincronizacion(CodigoError::Credenciales, 'probar');
        }

        throw new ErrorSincronizacion(CodigoError::RespuestaDesconocida, 'probar', $this->extracto($cuerpo));
    }

    /**
     * Paso 1: crear la copia en dati/hoteld_backup.php de la principal.
     */
    public function crearCopia(): void
    {
        $cuerpo = $this->peticion(['azione' => 'SI', 'crea_backup' => 'SI'], 'crear')->body();
        if (str_contains($cuerpo, self::MARCA_COPIA_CREADA)) {
            return;
        }

        $this->detectarErroresComunes($cuerpo, 'crear');
        if ($this->contiene($cuerpo, self::TEXTOS_LIMITE)) {
            throw new ErrorSincronizacion(CodigoError::LimiteDiario, 'crear');
        }
        if ($this->contiene($cuerpo, self::TEXTOS_SIN_ESCRITURA)) {
            throw new ErrorSincronizacion(CodigoError::EscrituraPrincipal, 'crear');
        }

        throw new ErrorSincronizacion(CodigoError::RespuestaDesconocida, 'crear', $this->extracto($cuerpo));
    }

    /**
     * Paso 2: descargar la copia (comprimida si así está configurada) a un archivo local.
     *
     * @return int bytes descargados
     */
    public function descargarCopia(string $destino): int
    {
        $limite = (int) config('alojaflow.subordinacion.tamano_maximo_descarga');
        $respuesta = $this->peticion([
            'azione' => 'SI',
            'mostra_header' => 'NO',
            'salva_backup' => 'SI',
            'compresso' => $this->principal->compresion ? 'SI' : 'NO',
        ], 'descargar', $destino, $limite);

        clearstatcache(true, $destino);
        if (! is_file($destino) || filesize($destino) === 0) {
            file_put_contents($destino, $respuesta->body());
            clearstatcache(true, $destino);
        }
        $bytes = (int) filesize($destino);
        if ($bytes > $limite) {
            throw new ErrorSincronizacion(CodigoError::TamanoExcedido, 'descargar');
        }

        $inicio = (string) file_get_contents($destino, false, null, 0, 4096);
        if (str_starts_with($inicio, "\x1f\x8b")) {
            return $bytes;
        }
        if (str_starts_with($inicio, '<?php exit(); ?>') && str_contains($inicio, '<versione>')) {
            return $bytes;
        }

        $this->detectarErroresComunes($inicio, 'descargar');
        throw new ErrorSincronizacion(CodigoError::FormatoInvalido, 'descargar', $this->extracto($inicio));
    }

    /**
     * Paso 3: borrar la copia del servidor de la principal. Falso si no se pudo (SUB-013).
     */
    public function borrarCopia(): bool
    {
        try {
            $cuerpo = $this->peticion(['azione' => 'SI', 'cancella_backup' => 'SI'], 'borrar')->body();
        } catch (ErrorSincronizacion) {
            return false;
        }

        return ! $this->contiene($cuerpo, self::TEXTOS_ERROR_BORRADO) && ! str_contains($cuerpo, self::CAMPO_ACCESO);
    }

    /**
     * @param  array<string, string>  $adicionales
     */
    private function peticion(array $adicionales, string $paso, ?string $destino = null, ?int $limite = null): Response
    {
        $redLocal = (bool) config('alojaflow.subordinacion.red_local') && $this->principal->permitir_red_local;
        $destinoValidado = $this->validador->validar($this->principal->url_base, $redLocal);

        $parametros = array_merge([
            'nome_utente_phpr' => (string) $this->principal->usuario,
            'password_phpr' => (string) $this->principal->contrasena,
            'anno' => (string) $this->anio(),
        ], $adicionales);
        $this->comprobarListaBlanca($parametros);

        // Sin descomprimir al vuelo: con «Content-Encoding: gzip», pocos MB de red serían GB en el disco. El
        // tope se cuenta sobre lo que llega, y LectorCopia y Descompresor ya limitan lo descomprimido.
        $opciones = ['allow_redirects' => false, 'decode_content' => false, 'headers' => ['Accept-Encoding' => 'identity']];
        if (defined('CURLOPT_RESOLVE')) {
            // Fija la IP ya validada para que no cambie entre la validación y la conexión.
            $opciones['curl'] = [CURLOPT_RESOLVE => [$destinoValidado['host'].':'.$destinoValidado['puerto'].':'.$destinoValidado['ip']]];
        }
        $limite ??= self::BYTES_MAXIMOS_RESPUESTA;
        $excedido = false;
        $opciones['progress'] = function (int|float $total, int|float $descargado) use ($limite, &$excedido): void {
            if ($total > $limite || $descargado > $limite) {
                $excedido = true;
                throw new LogicException('limite');
            }
        };
        if ($destino !== null) {
            $opciones['sink'] = $destino;
        }

        $cliente = $this->http
            ->withUserAgent(sprintf((string) config('alojaflow.subordinacion.agente'), (string) config('alojaflow.version')))
            ->connectTimeout((int) config('alojaflow.subordinacion.tiempo_conexion'))
            ->timeout((int) config('alojaflow.subordinacion.tiempo_peticion'))
            ->withOptions($opciones);
        $url = $this->principal->url_base.self::ARCHIVO;

        try {
            $respuesta = $this->principal->metodo === MetodoPeticion::Get
                ? $cliente->get($url, $parametros)
                : $cliente->asForm()->post($url, $parametros);
        } catch (ConnectionException $e) {
            throw new ErrorSincronizacion($this->clasificarConexion($e), $paso, $this->limpiar($e->getMessage()), $e);
        } catch (Throwable $e) {
            if ($excedido) {
                throw new ErrorSincronizacion(CodigoError::TamanoExcedido, $paso);
            }
            throw new ErrorSincronizacion(CodigoError::Conexion, $paso, $this->limpiar($e->getMessage()), $e);
        }

        if ($respuesta->redirect()) {
            $hacia = (string) parse_url((string) $respuesta->header('Location'), PHP_URL_HOST);
            throw new ErrorSincronizacion(CodigoError::RespuestaDesconocida, $paso, __('sincronizacion.redireccion', ['destino' => $hacia]));
        }
        if (! $respuesta->successful()) {
            throw new ErrorSincronizacion(CodigoError::RespuestaDesconocida, $paso, 'HTTP '.$respuesta->status());
        }

        return $respuesta;
    }

    /**
     * @param  array<string, string>  $parametros
     */
    private function comprobarListaBlanca(array $parametros): void
    {
        $sobrantes = array_diff(array_keys($parametros), self::PARAMETROS_PERMITIDOS);
        if ($sobrantes !== []) {
            throw new LogicException('Parámetro no permitido para la principal: '.implode(', ', $sobrantes));
        }
    }

    private function anio(): int
    {
        return $this->principal->anio_consulta
            ?? CarbonImmutable::now($this->principal->propiedad->zona_horaria)->year;
    }

    private function detectarErroresComunes(string $cuerpo, string $paso): void
    {
        if (str_contains($cuerpo, self::CAMPO_ACCESO)) {
            throw new ErrorSincronizacion(CodigoError::Credenciales, $paso);
        }
        if (str_contains($cuerpo, self::TEXTO_ANIO_INEXISTENTE)) {
            throw new ErrorSincronizacion(CodigoError::AnioInexistente, $paso);
        }
    }

    /**
     * @param  list<string>  $textos
     */
    private function contiene(string $cuerpo, array $textos): bool
    {
        $normalizado = mb_strtolower(Str::ascii(html_entity_decode($cuerpo, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        foreach ($textos as $texto) {
            if (str_contains($normalizado, $texto)) {
                return true;
            }
        }

        return false;
    }

    private function clasificarConexion(ConnectionException $e): CodigoError
    {
        $mensaje = $e->getMessage();
        if (str_contains($mensaje, 'cURL error 28') || stripos($mensaje, 'timed out') !== false) {
            return CodigoError::TiempoAgotado;
        }
        if (preg_match('/cURL error (35|51|58|60|77)\b/', $mensaje) || stripos($mensaje, 'SSL') !== false || stripos($mensaje, 'certificate') !== false) {
            return CodigoError::Tls;
        }

        return CodigoError::Conexion;
    }

    /**
     * Texto legible de la respuesta, sin etiquetas y sin credenciales, para el aviso.
     */
    private function extracto(string $html): string
    {
        $sinCodigo = (string) preg_replace('#<(style|script)\b.*?</\1>#is', ' ', $html);
        $legible = html_entity_decode(strip_tags($sinCodigo), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $legible));

        return $this->limpiar(mb_substr($texto, 0, 500));
    }

    /**
     * Quita credenciales y cadenas de consulta de cualquier mensaje (regla 13).
     */
    private function limpiar(string $mensaje): string
    {
        $mensaje = (string) preg_replace('/\?\S*/', '?…', $mensaje);
        foreach ([(string) $this->principal->contrasena, (string) $this->principal->usuario] as $secreto) {
            if ($secreto !== '') {
                $mensaje = str_replace([$secreto, rawurlencode($secreto), urlencode($secreto)], '***', $mensaje);
            }
        }

        return $mensaje;
    }
}
