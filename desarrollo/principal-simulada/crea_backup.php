<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/*
 * Principal simulada, solo para desarrollo y demostraciones: responde como el crea_backup.php
 * de HotelDruid 1.2.3 (subordinacion.md A3) y sirve la copia indicada en COPIA_HOTELDRUID, sin
 * credenciales. No forma parte de AlojaFlow ni se instala en producción. Uso (PowerShell):
 *
 *   $env:COPIA_HOTELDRUID = "E:\HotelDruid\respaldos\demo-alojaflow-2026-respaldo-original.php"
 *   C:\wamp64\bin\php\php8.3.28\php.exe -S 127.0.0.1:8090 -t desarrollo\principal-simulada
 *
 * y en AlojaFlow, la principal en http://127.0.0.1:8090/ con «Permitir red local».
 */

$copia = (string) getenv('COPIA_HOTELDRUID');
$peticion = $_REQUEST;

if (($peticion['crea_backup'] ?? '') === 'SI') {
    echo "<html><body>File created.<br>\n<!-- Backup_created --></body></html>";

    return;
}

if (($peticion['salva_backup'] ?? '') === 'SI') {
    if ($copia === '' || ! is_file($copia)) {
        http_response_code(500);
        echo 'Define COPIA_HOTELDRUID con la ruta de una copia de HotelDruid.';

        return;
    }
    $datos = (string) file_get_contents($copia);
    if (($peticion['compresso'] ?? '') === 'SI') {
        header('Content-Type: application/x-gzip; name="hoteld_backup.php.gz"');
        echo gzencode($datos, 9);
    } else {
        header('Content-Type: application/octet-stream; name="hoteld_backup.php"');
        echo $datos;
    }

    return;
}

if (($peticion['cancella_backup'] ?? '') === 'SI') {
    echo '<html><body>ok</body></html>';

    return;
}

echo '<html><body><form method="post"><button type="submit" name="crea_backup" value="1">Backup</button></form></body></html>';
