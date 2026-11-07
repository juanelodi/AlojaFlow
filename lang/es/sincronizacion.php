<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Este dato viene de la instalación principal y solo se puede cambiar allí.',
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
    'respondio' => 'Eso pasó :paso, y respondió: «:detalle».',
    'pasos' => [
        'probar' => 'al probar la conexión',
        'validar' => 'al revisar la dirección',
        'crear' => 'al pedir la copia',
        'descargar' => 'al descargar la copia',
        'borrar' => 'al borrar la copia',
        'descomprimir' => 'al descomprimir la copia',
        'leer' => 'al leer la copia',
        'interno' => 'al procesar la copia',
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
        'SUB-017' => 'La otra instalación no respondió como se esperaba. Revisa que tenga AlojaFlow 1.0.12 o posterior y el módulo de subordinación prendido.',
        'SUB-018' => 'La otra instalación rechazó el código de conexión. Pudieron haberlo revocado: pide uno nuevo.',
        'SUB-098' => 'AlojaFlow no pudo procesar lo que recibió de la principal. La falla es de AlojaFlow, no de la principal: quedó anotada en el registro.',
        'SUB-099' => 'La principal dio una respuesta que AlojaFlow no reconoce.',
    ],

    'compartir' => [
        'titulo' => 'Compartir con otra instalación',
        'ayuda' => 'Otra instalación de AlojaFlow puede copiar esta propiedad, solo para leerla: las reservas se siguen cambiando aquí. Las fotos y los archivos nunca viajan.',
        'sin_conexiones' => 'Todavía no la compartes con nadie',
        'nombre' => 'Nombre',
        'nombre_ayuda' => 'Para reconocerla, por ejemplo «Oficina central».',
        'documentos' => 'Incluir documentos de identidad',
        'documentos_ayuda' => 'Apagado, no viaja ni el número ni los demás datos del documento. Actívalo solo si la otra instalación los necesita.',
        'ultimo_uso' => 'Último uso',
        'estado' => 'Estado',
        'activa' => 'Activa',
        'revocada' => 'Revocada',
        'crear' => 'Compartir',
        'revocar' => 'Revocar',
        'revocar_texto' => 'La otra instalación dejará de poder copiar. Lo que ya copió se queda allá.',
        'codigo_titulo' => 'Código de conexión',
        'codigo_texto' => 'Cópialo y pégalo en la otra instalación, al crear la propiedad subordinada. Por seguridad, no se vuelve a mostrar.',
        'codigo_listo' => 'Ya lo copié',
    ],

    'formulario' => [
        'tipo' => 'De qué sistema se copia',
        'codigo' => 'Código de conexión',
        'codigo_ayuda' => 'Se genera en la otra instalación: al editar la propiedad, en «Compartir con otra instalación». Si ya está conectada, déjalo vacío para conservar el actual.',
        'codigo_invalido' => 'Ese no es un código de conexión de AlojaFlow. Cópialo completo.',
    ],
];
