<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Copias de seguridad',
    'explicacion' => 'Cada copia guarda toda la base de datos y los archivos subidos, en un zip que puedes descargar y volver a cargar aquí o en otra instalación.',
    'estado_automaticas' => 'Se crea una copia automática cada noche y se conservan las últimas :cuantas.',
    'estado_manuales' => 'Las copias automáticas están apagadas: solo se crean cuando lo pides.',
    'aviso_fuera' => 'Estas copias viven en este mismo servidor. Descarga una de vez en cuando y guárdala en otro lugar, junto con el archivo de configuración: es lo único que te protege si se pierde el servidor.',
    'crear' => 'Crear copia ahora',
    'creada' => 'Copia creada: :nombre',
    'subir' => 'Subir una copia',
    'subir_ayuda' => 'Sube un zip descargado antes, de esta o de otra instalación de AlojaFlow. Solo se guarda: para usarla, pulsa después «Restaurar».',
    'archivo' => 'Archivo de la copia (zip)',
    'subida' => 'Copia subida: :nombre',
    'ajustes' => 'Ajustes',
    'automaticas' => 'Crear una copia automática cada noche',
    'automaticas_ayuda' => 'Necesita la tarea programada del servidor o que alguien use el sistema ese día.',
    'conservar' => 'Cuántas copias conservar',
    'conservar_ayuda' => 'Al crear una nueva, se borran las más viejas. Cada copia lleva datos personales: no guardes más de las que necesites.',
    'ajustes_guardados' => 'Ajustes guardados.',
    'descargar_env' => 'Descargar configuración',
    'descargar_env_ayuda' => 'El archivo .env tiene la llave con la que se cifran los documentos y contraseñas guardados, y la contraseña de la base de datos. Sin esa llave, una copia restaurada en otro servidor no podrá leer esos datos. Guárdalo en un lugar seguro, separado de las copias.',
    'contrasena' => 'Tu contraseña',
    'lista' => 'Copias guardadas',
    'vacio' => 'Todavía no hay copias.',
    'fecha' => 'Fecha',
    'origen' => 'Origen',
    'version' => 'Versión',
    'tamano' => 'Tamaño',
    'acciones' => 'Acciones',
    'otra_clave' => 'De otra instalación: sus datos cifrados no se podrán leer con la llave actual.',
    'origenes' => [
        'manual' => 'Manual',
        'programada' => 'Automática',
        'antes_de_restaurar' => 'Antes de restaurar',
        'antes-de-independizar' => 'Antes de independizar',
        'antes-de-importar' => 'Antes de importar',
    ],
    'descargar' => 'Descargar',
    'restaurar' => 'Restaurar',
    'restaurar_titulo' => '¿Restaurar esta copia?',
    'restaurar_texto' => 'Todos los datos actuales se sustituyen por los de la copia. Antes se guarda otra copia del estado actual, por si hace falta volver. Al terminar tendrás que iniciar sesión de nuevo.',
    'eliminar' => 'Eliminar',
    'eliminar_titulo' => '¿Eliminar esta copia?',
    'eliminada' => 'Copia eliminada.',
    'error' => [
        'no_escribe' => 'No se pudo escribir la copia en storage/app/private/copias. Revisa los permisos y el espacio libre.',
        'no_existe' => 'Esa copia ya no existe.',
        'no_es_copia' => 'Ese archivo no es una copia de seguridad de AlojaFlow o está dañado.',
        'mas_nueva' => 'Esta copia viene de la versión :version, más nueva que la instalada. Actualiza AlojaFlow antes de restaurarla.',
        'restaurar' => 'No se pudo restaurar la copia; los datos volvieron a como estaban. Detalle: :detalle',
    ],
];
