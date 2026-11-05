<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'nombres' => [
        'operacion' => [
            'ver' => 'Ver las pantallas de operación (Hoy, calendario, limpieza)',
        ],
        'datos' => [
            'ver' => 'Ver reservas, huéspedes y demás datos',
        ],
        'indicadores' => [
            'ver' => 'Ver indicadores e informes',
        ],
        'sincronizacion' => [
            'gestionar' => 'Administrar la sincronización con la principal',
        ],
        'reservas' => [
            'gestionar' => 'Crear y cambiar reservas, huéspedes y pagos',
        ],
        'catalogo' => [
            'gestionar' => 'Crear y cambiar unidades y tarifas',
        ],
        'limpieza' => [
            'gestionar' => 'Administrar limpieza y mantenimiento',
        ],
        'mensajes' => [
            'gestionar' => 'Mandar mensajes a los huéspedes',
        ],
        'ajustes' => [
            'gestionar' => 'Plantillas, automatizaciones, calendarios y motor de reservas',
        ],
        'huespedes' => [
            'gestionar' => 'Registro de llegada, documentos y privacidad',
        ],
        'facturacion' => [
            'gestionar' => 'Datos fiscales, facturas y exportaciones',
        ],
    ],
    'roles' => [
        'modelo' => 'rol',
        'modelos' => 'Roles',
        'nombre' => 'Nombre',
        'permisos' => 'Permisos',
        'en_uso' => 'Usuarios con este rol',
        'ayuda' => 'Lo que cambies aquí vale para todas las personas con este rol, en cuanto lo guardes.',
    ],
    'usuario' => [
        'ajustes' => 'Ajustes de esta persona',
        'ajustes_ayuda' => 'Opcional. Solo para quien necesita algo distinto de su rol.',
        'agregados' => 'Además de su rol, puede',
        'quitados' => 'Aunque su rol lo permita, no puede',
        'solo_propias' => 'Solo sus propias reservas y huéspedes',
        'solo_propias_ayuda' => 'En Hoy y en las listas verá solo lo que creó. En el calendario verá qué está ocupado, sin nombres.',
        'sin_iniciadas' => 'No cambiar reservas ya iniciadas',
        'sin_iniciadas_ayuda' => 'Las que ya llegaron o llegan hoy. Registrar la entrada, la salida y los pagos sigue permitido.',
        'horas_modificar' => 'Horas para cambiar una reserva',
        'horas_cancelar' => 'Horas para cancelar una reserva',
        'horas_ayuda' => 'Contadas desde que se creó. Vacío: sin límite.',
    ],
    'limites' => [
        'no_modificar' => 'Tus permisos no dejan cambiar esta reserva.',
        'ocupada' => 'Ocupada',
    ],
    'sesiones' => [
        'titulo' => 'Sesiones y dispositivos',
        'abiertas' => 'Sesiones abiertas',
        'sin_sesiones' => 'No hay sesiones que mostrar.',
        'recordados' => 'Dispositivos recordados',
        'recordados_ayuda' => 'Donde se marcó «Recordarme» al entrar. Pueden volver a entrar sin contraseña hasta la fecha indicada.',
        'sin_dispositivos' => 'No hay dispositivos recordados.',
        'usuario' => 'Usuario',
        'dispositivo' => 'Dispositivo',
        'ultimo_uso' => 'Último uso',
        'caduca' => 'Caduca',
        'acciones' => 'Acciones',
        'esta' => 'Esta sesión',
        'cerrar' => 'Cerrar',
        'olvidar' => 'Olvidar',
        'cerrar_demas' => 'Cerrar mis demás sesiones y dispositivos',
        'cerradas' => 'Se cerraron tus demás sesiones y dispositivos.',
        'dias' => 'Días que se recuerda un dispositivo',
        'dias_ayuda' => 'Con 0 no se recuerda ninguno, y se olvidan los que haya.',
        'guardar' => 'Guardar',
        'guardado' => 'Guardado.',
    ],
    'dos_pasos' => [
        'quitar' => 'Quitar la verificación en dos pasos',
        'quitar_texto' => 'Para quien perdió su teléfono y sus códigos de recuperación. Podrá entrar solo con su contraseña hasta que la vuelva a activar en su perfil.',
        'quitada' => 'Se quitó la verificación en dos pasos de :usuario.',
    ],
    'auditoria' => [
        'exportar' => 'Exportar CSV',
        'plazo' => 'Tiempo que se guarda',
        'dias' => 'Días',
        'dias_ayuda' => 'Lo más antiguo se borra solo. Mínimo 30 días.',
        'plazo_guardado' => 'Guardado.',
    ],
];
