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
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use LogicException;

/**
 * Habla con la principal por el protocolo de subordinacion.md A3: pide que cree su copia, la descarga y pide que la
 * borre, siempre a un solo archivo y con parámetros de una lista cerrada. No hay forma de pedirle otra cosa: es la
 * garantía de que AlojaFlow nunca escribe en la principal.
 */
class ClientePrincipal
{
    /** Lo único que se le puede mandar a la principal. Lo que restaura, sube o cambia algo allá no está. */
    public const PARAMETROS_PERMITIDOS = [
        'nome_utente_phpr', 'password_phpr', 'anno', 'azione', 'crea_backup', 'mostra_header', 'salva_backup',
        'compresso', 'cancella_backup', 'id_sessione',
    ];

    /** El único archivo de la principal al que se llama. */
    public const ARCHIVO = 'crea_backup.php';

    private const PROBAR = 'probar';

    private const CREAR = 'crear';

    private const DESCARGAR = 'descargar';

    private const BORRAR = 'borrar';

    /** Lo que distingue cada paso; los valores son fijos. */
    private const PEDIDOS = [
        self::PROBAR => [],
        self::CREAR => ['azione' => 'SI', 'crea_backup' => 'SI'],
        self::DESCARGAR => ['azione' => 'SI', 'mostra_header' => 'NO', 'salva_backup' => 'SI'],
        self::BORRAR => ['azione' => 'SI', 'cancella_backup' => 'SI'],
    ];

    /** Una página de la principal mide unos kilobytes; solo la copia puede ser grande. */
    private const BYTES_MAXIMOS_PAGINA = 2 * 1024 * 1024;

    /** Cuánto del principio de la descarga se mira para saber si es una copia. */
    private const BYTES_DE_MUESTRA = 65536;

    /** Códigos de cURL: tiempo agotado, y fallos al negociar o comprobar el certificado. */
    private const CURL_TIEMPO = [28];

    private const CURL_TLS = [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91];

    private int $anio;

    /** La primera vez no se sabe qué años tiene la principal: se supone el actual. */
    private bool $anioSupuesto;

    public function __construct(
        private readonly Principal $principal,
        private readonly Factory $http,
        private readonly ValidadorDestino $validador,
    ) {
        $this->anioSupuesto = $principal->anio_consulta === null;
        $this->anio = $principal->anio_consulta ?? (int) CarbonImmutable::now($principal->propiedad->zona_horaria)->format('Y');
    }

    /**
     * Paso 0: la página de copias responde y reconoce al usuario. No crea nada.
     */
    public function probarConexion(): void
    {
        $respuesta = $this->pagina(self::PROBAR);
        // Sin el privilegio de copia la principal no escribe nada.
        if ($respuesta->vacia()) {
            throw new ErrorSincronizacion(CodigoError::Credenciales, self::PROBAR);
        }
        if (! $respuesta->ofreceCrearCopia() && ! $respuesta->soloCabecera()) {
            throw $this->noReconocida($respuesta, self::PROBAR);
        }
    }

    /**
     * Paso 1: la principal escribe su copia en su carpeta de datos.
     */
    public function crearCopia(): void
    {
        $respuesta = $this->pagina(self::CREAR);
        if ($respuesta->copiaCreada()) {
            return;
        }

        throw match (true) {
            $respuesta->alcanzoLimiteDiario() => new ErrorSincronizacion(CodigoError::LimiteDiario, self::CREAR),
            $respuesta->noPudoEscribir() => new ErrorSincronizacion(CodigoError::EscrituraPrincipal, self::CREAR),
            default => $this->noReconocida($respuesta, self::CREAR),
        };
    }

    /**
     * Paso 2: guarda la copia en $destino, tal como llega (comprimida o no), y devuelve cuántos bytes son.
     */
    public function descargarCopia(string $destino): int
    {
        $limite = (int) config('alojaflow.subordinacion.tamano_maximo_descarga');
        $respuesta = $this->enviar(self::DESCARGAR, $limite, $destino);
        if ($respuesta->redirect()) {
            throw $this->redireccion($respuesta, self::DESCARGAR);
        }

        clearstatcache(true, $destino);
        $bytes = is_file($destino) ? (int) filesize($destino) : 0;
        if ($bytes > $limite) {
            throw new ErrorSincronizacion(CodigoError::TamanoExcedido, self::DESCARGAR);
        }
        $muestra = $bytes === 0 ? '' : (string) file_get_contents($destino, false, null, 0, self::BYTES_DE_MUESTRA);
        if (str_starts_with($muestra, Descompresor::FIRMA_GZIP) || (str_starts_with($muestra, LectorCopia::PRIMERA_LINEA) && str_contains($muestra, '<versione>'))) {
            return $bytes;
        }

        // En vez de la copia llegó una página: la de acceso, o cualquier otra.
        $pagina = new RespuestaPrincipal($respuesta->status(), $this->sinCredenciales($muestra));
        throw $pagina->pideCredenciales()
            ? new ErrorSincronizacion(CodigoError::Credenciales, self::DESCARGAR)
            : new ErrorSincronizacion(CodigoError::FormatoInvalido, self::DESCARGAR, $pagina->extracto());
    }

    /**
     * Paso 3: pide a la principal que borre su copia. No lanza nada: si no se puede, la sincronización sigue y se avisa.
     */
    public function borrarCopia(): bool
    {
        try {
            $respuesta = $this->enviar(self::BORRAR, self::BYTES_MAXIMOS_PAGINA);
        } catch (ErrorSincronizacion) {
            return false;
        }
        $pagina = new RespuestaPrincipal($respuesta->status(), $respuesta->body());

        return $respuesta->successful() && ! $pagina->pideCredenciales() && ! $pagina->noPudoBorrar();
    }

    /**
     * Una página de la principal ya revisada: ni redirección ni formulario de acceso.
     */
    private function pagina(string $paso): RespuestaPrincipal
    {
        $respuesta = $this->enviar($paso, self::BYTES_MAXIMOS_PAGINA);
        if ($respuesta->redirect() && $this->anioSupuesto) {
            // Con un año que no tiene, la principal contesta con un aviso o una redirección: se prueba una vez con el anterior.
            $this->anioSupuesto = false;
            $this->anio--;
            $conAnterior = $this->enviar($paso, self::BYTES_MAXIMOS_PAGINA);
            if ($conAnterior->redirect()) {
                $this->anio++;
            } else {
                $respuesta = $conAnterior;
            }
        }
        if ($respuesta->redirect()) {
            throw $this->redireccion($respuesta, $paso);
        }

        $pagina = new RespuestaPrincipal($respuesta->status(), $this->sinCredenciales($respuesta->body()));
        // Tras un rechazo no se reintenta: siete contraseñas erróneas bloquean la cuenta en la principal.
        if ($pagina->pideCredenciales()) {
            throw new ErrorSincronizacion(CodigoError::Credenciales, $paso);
        }

        return $pagina;
    }

    private function enviar(string $paso, int $bytesMaximos, ?string $archivo = null): Response
    {
        $redLocal = (bool) config('alojaflow.subordinacion.red_local') && $this->principal->permitir_red_local;
        $destino = $this->validador->validar($this->principal->url_base, $redLocal);

        $excedido = false;
        $opciones = [
            // Sin seguir redirecciones: llevarían las credenciales a otro sitio (A6).
            'allow_redirects' => false,
            'decode_content' => false,
            'progress' => function (int|float $total, int|float $descargado) use ($bytesMaximos, &$excedido): void {
                if ($total > $bytesMaximos || $descargado > $bytesMaximos) {
                    $excedido = true;
                    throw new LogicException('limite');
                }
            },
        ];
        if ($archivo !== null) {
            $opciones['sink'] = $archivo;
        }
        if (defined('CURLOPT_RESOLVE')) {
            // La IP ya comprobada queda fija: no puede cambiar entre la revisión y la conexión.
            $opciones['curl'] = [CURLOPT_RESOLVE => [$destino['host'].':'.$destino['puerto'].':'.$destino['ip']]];
        }

        $peticion = $this->http
            ->withUserAgent(sprintf((string) config('alojaflow.subordinacion.agente'), (string) config('alojaflow.version')))
            ->connectTimeout((int) config('alojaflow.subordinacion.tiempo_conexion'))
            ->timeout((int) config('alojaflow.subordinacion.tiempo_peticion'))
            ->withOptions($opciones);
        $url = $this->principal->url_base.self::ARCHIVO;
        $parametros = $this->parametros($paso);

        try {
            // Por POST, para que la contraseña no quede en la dirección ni en los registros de la principal.
            return $this->principal->metodo === MetodoPeticion::Get
                ? $peticion->get($url, $parametros)
                : $peticion->asForm()->post($url, $parametros);
        } catch (ConnectionException $e) {
            // Solo el código: el mensaje del cliente HTTP puede llevar la dirección con la contraseña.
            throw new ErrorSincronizacion($this->codigoDeConexion($e), $paso);
        } catch (LogicException $e) {
            if ($excedido) {
                throw new ErrorSincronizacion($archivo === null ? CodigoError::RespuestaDesconocida : CodigoError::TamanoExcedido, $paso);
            }
            throw $e;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parametros(string $paso): array
    {
        $parametros = self::PEDIDOS[$paso] + [
            'nome_utente_phpr' => (string) $this->principal->usuario,
            'password_phpr' => (string) $this->principal->contrasena,
            'anno' => (string) $this->anio,
        ];
        if ($paso === self::DESCARGAR && $this->principal->compresion) {
            $parametros['compresso'] = 'SI';
        }
        if (array_diff(array_keys($parametros), self::PARAMETROS_PERMITIDOS) !== []) {
            throw new LogicException('Parámetro fuera de la lista permitida.');
        }

        return $parametros;
    }

    private function codigoDeConexion(ConnectionException $error): CodigoError
    {
        $origen = $error->getPrevious();
        $contexto = $origen instanceof TransferException && method_exists($origen, 'getHandlerContext') ? $origen->getHandlerContext() : [];
        $numero = (int) ($contexto['errno'] ?? 0);
        $mensaje = strtolower($error->getMessage());

        return match (true) {
            in_array($numero, self::CURL_TIEMPO, true), $numero === 0 && str_contains($mensaje, 'timed out') => CodigoError::TiempoAgotado,
            in_array($numero, self::CURL_TLS, true), $numero === 0 && (str_contains($mensaje, 'ssl') || str_contains($mensaje, 'certificate')) => CodigoError::Tls,
            // La descarga se cortó a medias.
            $numero === 18 => CodigoError::TamanoExcedido,
            default => CodigoError::Conexion,
        };
    }

    private function redireccion(Response $respuesta, string $paso): ErrorSincronizacion
    {
        $pagina = new RespuestaPrincipal($respuesta->status(), '', $respuesta->header('Location'));

        return new ErrorSincronizacion(CodigoError::RespuestaDesconocida, $paso, __('sincronizacion.redireccion', ['destino' => $this->sinCredenciales($pagina->destinoRedireccion())]));
    }

    private function noReconocida(RespuestaPrincipal $respuesta, string $paso): ErrorSincronizacion
    {
        return new ErrorSincronizacion(CodigoError::RespuestaDesconocida, $paso, $respuesta->extracto());
    }

    /**
     * Si la principal repitiera la contraseña en su respuesta, no debe llegar a un aviso ni al registro (regla 13).
     */
    private function sinCredenciales(string $texto): string
    {
        $contrasena = (string) $this->principal->contrasena;

        return strlen($contrasena) < 4 ? $texto : str_replace([$contrasena, rawurlencode($contrasena)], '***', $texto);
    }
}
