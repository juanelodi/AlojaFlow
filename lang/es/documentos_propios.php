<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'modelo' => 'documento',
    'modelos' => 'Documentos propios',
    'nombre' => 'Nombre',
    'alcance' => 'De qué sale',
    'alcance_ayuda' => 'El de una reserva se saca desde esa reserva, en «Más acciones». El de varias, desde esta lista, eligiendo las fechas.',
    'activo' => 'Activo',
    'cuerpo' => 'Texto del documento',
    'vacio' => 'Todavía no hay documentos',
    'vacio_ayuda' => 'Un contrato, una hoja de registro o la lista de llegadas del día, con tu propio texto. Puedes empezar con los de partida.',
    'variables' => 'Escribe el documento como cualquier texto. Donde quieras un dato, pon su variable entre llaves, como {huesped}. En un idioma vacío se usa el de la propiedad.',
    'accion' => 'Documento propio',
    'elegir' => 'Documento',
    'generar' => 'Generar',
    'vista_previa' => 'Vista previa',
    'sin_reservas_previa' => 'Esa propiedad todavía no tiene reservas con que probarlo.',
    'desde' => 'Desde',
    'hasta' => 'Hasta',
    'criterio' => 'Qué reservas',
    'criterios' => [
        'llegadas' => 'Las que llegan en esas fechas',
        'salidas' => 'Las que salen en esas fechas',
        'en_casa' => 'Las que se hospedan en esas fechas',
    ],
    'demasiadas' => 'Son más de :maximo reservas para un solo documento. Elige menos días.',
    'de_partida' => 'Crear los de partida',
    'de_partida_ayuda' => 'Crea un contrato, una hoja de registro y una lista de llegadas, para que los adaptes. El contrato es solo un punto de partida: revísalo con quien te asesore.',
    'de_partida_listo' => 'Listo: revísalos y adáptalos.',
    'si' => 'Sí',

    'campos' => [
        'modelo' => 'campo propio',
        'modelos' => 'Campos propios',
        'seccion' => 'Campos propios',
        'accion' => 'Campos propios',
        'guardados' => 'Campos guardados.',
        'nombre' => 'Nombre',
        'nombre_ayuda' => 'Por ejemplo: placa del coche, habitación preferida.',
        'entidad' => 'De qué es',
        'tipo' => 'Qué guarda',
        'activo' => 'Activo',
        'activo_ayuda' => 'Uno inactivo ya no se pide, y lo guardado se conserva.',
        'variable' => 'Variable',
        'vacio' => 'Todavía no hay campos propios',
        'vacio_ayuda' => 'Lo que quieras guardar de un huésped o de una reserva y el sistema no pide. Sale en sus fichas, en los documentos y en los mensajes.',
    ],

    'base' => [
        'reglamento' => [
            'nombre' => 'Reglamento de la casa',
            'intro' => 'Reglas de {propiedad} para la reserva {numero}, del {llegada} al {salida}:',
            'horarios' => 'Respeta los horarios de entrada y salida que te indicamos.',
            'ruido' => 'Guarda silencio en la noche y cuida a los demás huéspedes.',
            'visitas' => 'Solo pueden quedarse las personas registradas; avísanos de cualquier visita.',
            'fumar' => 'No se fuma dentro de las unidades.',
            'danos' => 'Quien se hospeda responde por los daños que cause.',
            'firma' => 'Enterado, el {hoy}:',
        ],
        'limpieza' => [
            'nombre' => 'Hoja para limpieza',
            'intro' => 'Salidas del {desde} al {hasta}:',
            'salida' => 'Salida',
            'unidad' => 'Unidad',
            'personas' => 'Personas',
            'comentario' => 'Comentario',
        ],
        'contrato' => [
            'nombre' => 'Contrato de hospedaje',
            'partes' => 'Entre {propiedad}, con domicilio en {domicilio_propiedad}, y {huesped}, con identificación {huesped_documento}, se acuerda lo siguiente:',
            'estancia' => 'Estancia en {unidad}, del {llegada} al {salida}: {noches} noches, para {personas} personas.',
            'precio' => 'Precio total: {total}. Pagado hasta hoy: {pagado}. Falta por pagar: {pendiente}.',
            'personas' => 'Solo pueden quedarse las personas registradas en esta reserva.',
            'danos' => 'Quien se hospeda responde por los daños que cause en la unidad.',
            'firma' => 'Firmado el {hoy}:',
        ],
        'registro' => [
            'nombre' => 'Hoja de registro',
            'intro' => 'Reserva {numero} · {unidad} · del {llegada} al {salida}.',
            'persona' => 'Persona',
            'nacimiento' => 'Nacimiento',
            'nacionalidad' => 'Nacionalidad',
            'documento' => 'Documento',
            'firma' => 'Firma de {huesped}:',
        ],
        'llegadas' => [
            'nombre' => 'Lista de llegadas',
            'intro' => 'Del {desde} al {hasta}: {cuantas} reservas.',
            'llegada' => 'Llegada',
            'salida' => 'Salida',
            'unidad' => 'Unidad',
            'huesped' => 'Huésped',
            'personas' => 'Personas',
            'pendiente' => 'Falta pagar',
        ],
    ],
];
