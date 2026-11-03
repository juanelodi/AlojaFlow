<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Este dato viene de la principal (HotelDruid) y solo se puede cambiar allí.',
    'redireccion' => 'La principal respondió con una redirección hacia :destino. Revisa la dirección.',
    'version_no_admitida' => 'La principal tiene el esquema :version, que AlojaFlow no admite (admite 3.08 y 3.081).',
    'copia_cortada' => 'La copia llegó cortada: falta el final.',
    'falta_tabla' => 'A la copia le falta la tabla :tabla.',
    'destino' => [
        'url_invalida' => 'La dirección de la principal no es válida.',
        'sin_barra_final' => 'La dirección debe apuntar a la carpeta de la instalación y terminar en «/».',
        'solo_https' => 'La dirección debe empezar por https://.',
        'sin_resolver' => 'No se encontró el servidor :host.',
        'red_local' => 'El servidor :host está en una red local; solo se permite si la instalación lo autoriza y la principal tiene activado «Permitir red local».',
    ],
    'errores' => [
        'SUB-001' => 'No se pudo conectar con la principal.',
        'SUB-002' => 'El certificado HTTPS de la principal no es válido.',
        'SUB-003' => 'La principal rechazó el usuario o la contraseña, el usuario no puede crear copias o tiene la verificación en dos pasos activada. La sincronización queda en pausa para no bloquear la cuenta.',
        'SUB-005' => 'La principal alcanzó su límite diario de copias. Se reanuda mañana; conviene bajar la frecuencia.',
        'SUB-006' => 'El año consultado no existe en la principal.',
        'SUB-007' => 'La principal no puede escribir la copia en su carpeta de datos.',
        'SUB-008' => 'La copia supera el tamaño máximo permitido o llegó incompleta.',
        'SUB-009' => 'La copia comprimida llegó dañada.',
        'SUB-010' => 'La respuesta no es una copia válida de HotelDruid.',
        'SUB-011' => 'La versión del esquema de la principal no es compatible.',
        'SUB-012' => 'La copia está incompleta.',
        'SUB-013' => 'No se pudo borrar la copia en la principal.',
        'SUB-014' => 'Ya hay una sincronización de esta principal en marcha.',
        'SUB-015' => 'La principal tardó demasiado en responder.',
        'SUB-016' => 'La dirección de la principal no está permitida.',
        'SUB-099' => 'La principal dio una respuesta que AlojaFlow no reconoce.',
    ],
];
