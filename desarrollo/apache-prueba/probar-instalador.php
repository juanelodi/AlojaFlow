<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

[$_, $base, $archivo] = $argv;

$entorno = [];
preg_match_all('/^(DB_[A-Z]+)=(.*)$/m', (string) file_get_contents(__DIR__.'/../../.env'), $lineas, PREG_SET_ORDER);
foreach ($lineas as [, $clave, $valor]) {
    $entorno[$clave] = trim($valor);
}
$bd = [
    'servidor' => $entorno['DB_HOST'], 'puerto' => (int) $entorno['DB_PORT'], 'base' => 'alojaflow_pruebas',
    'usuario' => $entorno['DB_USERNAME'], 'contrasena' => trim((string) $entorno['DB_PASSWORD'], '"'),
];
$administrador = [
    'nombre' => 'Prueba', 'correo' => 'prueba@example.com', 'admin_contrasena' => 'contrasena-de-prueba-1', 'admin_repite' => 'contrasena-de-prueba-1',
];

$galleta = sys_get_temp_dir().'/af-galleta-'.getmypid();

function peticion(string $url, ?array $cuerpo, string $galleta): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $galleta, CURLOPT_COOKIEFILE => $galleta, CURLOPT_TIMEOUT => 300,
    ]);
    if ($cuerpo !== null) {
        curl_setopt_array($c, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($cuerpo),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-AlojaFlow: 1'],
        ]);
    }
    $texto = (string) curl_exec($c);
    $estado = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    curl_close($c);

    return [$estado, $texto];
}

function accion(string $accion, array $extra = []): array
{
    global $base, $archivo, $galleta;
    [$estado, $texto] = peticion($base.'/'.$archivo, ['accion' => $accion] + $extra, $galleta);
    $json = json_decode($texto, true);
    if (! is_array($json)) {
        fwrite(STDERR, "$accion: respuesta no JSON ($estado): ".substr($texto, 0, 500)."\n");
        exit(1);
    }

    return $json;
}

function exigir(string $accion, array $extra = []): array
{
    $r = accion($accion, $extra);
    if (! ($r['ok'] ?? false)) {
        fwrite(STDERR, "$accion falló: ".($r['error'] ?? json_encode($r))."\n");
        exit(1);
    }
    echo "  $accion: ok\n";

    return $r;
}

function noExpuesto(string $ruta): void
{
    global $base, $galleta;
    [$estado, $texto] = peticion($base.'/'.$ruta, null, $galleta);
    $expuesto = $estado === 200 && (str_contains($texto, 'juanelodi/alojaflow') || str_contains($texto, 'APP_NAME=') || str_contains($texto, 'DB_PASSWORD') || str_contains($texto, 'LARAVEL_START'));
    echo "  /$ruta: $estado".($expuesto ? ' EXPUESTO' : ' protegido')."\n";
    if ($expuesto) {
        exit(1);
    }
}

function vaciar(array $bd): void
{
    $pdo = new PDO("mysql:host={$bd['servidor']};port={$bd['puerto']};dbname={$bd['base']}", $bd['usuario'], $bd['contrasena']);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
        $pdo->exec('DROP TABLE `'.$tabla.'`');
    }
}

echo "Base vacía\n";
vaciar($bd);

echo "Análisis\n";
[$estado] = peticion($base.'/'.$archivo, null, $galleta);
echo "  página: $estado\n";
$analisis = accion('analizar');
foreach ($analisis['filas'] as $fila) {
    echo '  ['.$fila['estado'].'] '.$fila['titulo'].': '.$fila['valor']."\n";
}
if (! $analisis['puede']) {
    exit(1);
}

echo "Datos incorrectos\n";
$r = accion('probar_base', ['contrasena' => 'mala'] + $bd + $administrador);
echo '  contraseña mala: '.($r['ok'] ? 'ACEPTADA' : $r['error'])."\n";
$r = accion('probar_base', ['base' => 'no_existe_af'] + $bd + $administrador);
echo '  base que no existe: '.($r['ok'] ? 'ACEPTADA' : $r['error'])."\n";
$r = accion('probar_base', $bd + ['admin_repite' => 'otra'] + $administrador);
echo '  contraseñas distintas: '.($r['ok'] ? 'ACEPTADA' : $r['error'])."\n";

echo "Instalación\n";
exigir('probar_base', $bd + $administrador);
$preparado = exigir('preparar');
if ($preparado['falta'] ?? false) {
    do {
        $bajando = exigir('descargar');
        printf("  %.0f de %.0f MB\n", $bajando['llevamos'] / 1048576, $bajando['total'] / 1048576);
    } while (! ($bajando['terminado'] ?? false));
    exigir('preparar');
}
$desde = 0;
$inicio = microtime(true);
do {
    $r = exigir('extraer', ['desde' => $desde]);
    $desde = $r['siguiente'];
} while (! ($r['terminado'] ?? false));
printf("  %d archivos en %.1f s\n", $desde, microtime(true) - $inicio);
exigir('proteger');
foreach (['composer.json', '.env.example', 'artisan', 'vendor/autoload.php', 'storage/logs/laravel.log', 'config/app.php', '.htaccess'] as $ruta) {
    noExpuesto($ruta);
}
exigir('configurar', $bd);
noExpuesto('.env');
exigir('migrar');
exigir('administrador', $administrador);
$inicio = microtime(true);
exigir('demo');
printf("  demo en %.1f s\n", microtime(true) - $inicio);
[$estado] = peticion($base.'/login', null, $galleta);
echo "  /login: $estado\n";
if ($estado !== 200) {
    print_r(accion('ultimo_error'));
    exit(1);
}
$fin = exigir('terminar');
echo '  instalador borrado: '.($fin['borrado'] ? 'sí' : 'NO')."\n";
echo '  enlace de entrada: '.$fin['url']."\n";
[$estado] = peticion($fin['url'], null, $galleta);
echo "  entrar con el enlace: $estado\n";
[$estado] = peticion($fin['url'], null, $galleta.'.otra');
echo "  otra vez con el mismo enlace (a iniciar sesión): $estado\n";
@unlink($galleta.'.otra');

echo "Después de instalar\n";
foreach (['/', '/login', '/up', '/css/filament/filament/app.css', '/'.$archivo, '/codigo-fuente'] as $ruta) {
    [$estado] = peticion($base.$ruta, null, $galleta);
    echo "  $ruta: $estado\n";
}
foreach (['.env', 'composer.json', 'storage/app/private/instalado.json'] as $ruta) {
    noExpuesto($ruta);
}

@unlink($galleta);
echo "Listo\n";
