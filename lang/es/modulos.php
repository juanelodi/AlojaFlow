<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Módulos',
    'explicacion' => 'Prende solo lo que usas: lo demás sale del menú y no estorba. Apagar un módulo no borra sus datos, y si lo vuelves a prender, todo sigue ahí. Mientras esté apagado, sus páginas no abren y sus tareas se detienen.',
    'siempre' => 'Siempre están: Hoy, calendario, reservas, huéspedes, usuarios, copias de seguridad, actualizaciones, privacidad y el enlace al código fuente.',
    'guardar' => 'Guardar',
    'guardado' => 'Módulos guardados.',
    'repetir' => 'Volver a responder las preguntas',
    'nuevo' => 'Nuevo',
    'requiere' => 'Necesita: :modulos',
    'omitido_al_apagar' => 'No se mandó porque se apagó el módulo.',
    'aviso_nuevos' => 'Llegaron módulos nuevos con la actualización: :modulos. Llegan apagados.',
    'ver' => 'Ver módulos',

    'grupos' => [
        'propiedades' => 'Propiedades',
        'reservas' => 'Reservas',
        'operacion' => 'Operación',
        'dinero' => 'Dinero',
        'sistema' => 'Sistema',
    ],

    'lista' => [
        'varias_propiedades' => ['nombre' => 'Varias propiedades', 'descripcion' => 'Manejar más de un alojamiento desde esta instalación.'],
        'subordinacion' => ['nombre' => 'Subordinación', 'descripcion' => 'Copiar las reservas de otro sistema que sigue siendo el principal: HotelDruid, AlojaFlow 1.2.x u otra instalación de AlojaFlow. Nunca se escribe en él. También sirve para compartir tus propiedades con otra instalación.'],
        'motor' => ['nombre' => 'Motor de reservas', 'descripcion' => 'Una página donde los huéspedes buscan fechas y reservan solos.'],
        'pagos' => ['nombre' => 'Cobros en línea', 'descripcion' => 'Cobrar con Stripe o Mercado Pago desde el motor de reservas.'],
        'ical' => ['nombre' => 'Calendarios externos', 'descripcion' => 'Bloquear fechas con los calendarios de Airbnb, Booking y Vrbo, y darles el tuyo.'],
        'cupones' => ['nombre' => 'Cupones', 'descripcion' => 'Códigos de descuento para las reservas.'],
        'grupos' => ['nombre' => 'Grupos', 'descripcion' => 'Reservar varias unidades juntas y llevarlas como una sola.'],
        'limpieza' => ['nombre' => 'Limpieza y mantenimiento', 'descripcion' => 'Estado de cada unidad, tareas para el personal y reglas de limpieza.'],
        'aviso_equipo' => ['nombre' => 'Aviso de entradas y salidas', 'descripcion' => 'Un parte para el equipo con quién sale, quién llega y qué se limpia, listo para mandar por WhatsApp.'],
        'plano' => ['nombre' => 'Plano de la propiedad', 'descripcion' => 'Ver las unidades acomodadas en un dibujo de tu alojamiento.'],
        'llegada' => ['nombre' => 'Llegada en línea', 'descripcion' => 'El huésped llena sus datos y acepta los documentos antes de llegar.'],
        'mensajes' => ['nombre' => 'Mensajes a huéspedes', 'descripcion' => 'Plantillas, envíos por correo y WhatsApp, y mensajes automáticos.'],
        'camping' => ['nombre' => 'Campings y parcelas', 'descripcion' => 'Qué tiene cada lugar, el equipo del huésped, elegir en el plano, lecturas de luz y agua, residentes de temporada y extras con cupo.'],
        'propietarios' => ['nombre' => 'Propietarios', 'descripcion' => 'Si administras unidades de terceros: dueños y comisión, gastos, estados de cuenta, su portal y sus estancias.'],
        'documentos' => ['nombre' => 'Documentos y campos propios', 'descripcion' => 'Tus propios documentos con los datos de cada reserva, como un contrato o la lista de llegadas, y los datos que quieras guardar de huéspedes y reservas.'],
        'estancias_largas' => ['nombre' => 'Estancias largas y mensualidades', 'descripcion' => 'Precio por semana y por mes, calendario de cobros con recordatorios y una factura por cada pago.'],
        'portal' => ['nombre' => 'Mi reserva y guía digital', 'descripcion' => 'Una página para cada huésped, con su reserva y lo que necesita saber, y un código QR en cada unidad.'],
        'bitacora' => ['nombre' => 'Bitácora de turnos', 'descripcion' => 'Lo que un turno le deja dicho al siguiente. Lo importante aparece en Hoy.'],
        'empresas' => ['nombre' => 'Empresas y agencias', 'descripcion' => 'Quién reserva o paga por sus huéspedes, con sus condiciones y su estado de cuenta.'],
        'opiniones' => ['nombre' => 'Opiniones de los huéspedes', 'descripcion' => 'Una encuesta tras la estancia, con enlace a tu página de reseñas.'],
        'caja' => ['nombre' => 'Caja y cierre del día', 'descripcion' => 'Cajas, movimientos de dinero y el corte diario.'],
        'facturacion' => ['nombre' => 'Facturas y recibos', 'descripcion' => 'Datos fiscales, facturas, recibos y exportaciones para el contador.'],
        'informes' => ['nombre' => 'Informes e indicadores', 'descripcion' => 'Ocupación, ingresos y demás reportes.'],
        'integraciones' => ['nombre' => 'Avisos a otros sistemas', 'descripcion' => 'Mandar lo que pasa con las reservas a otros programas (webhooks). Si no sabes qué es, no lo necesitas.'],
    ],

    'en_uso' => [
        'varias_propiedades' => 'Ya tienes :n propiedad más. Seguirá funcionando, pero no podrás crear otras.|Ya tienes :n propiedades más. Seguirán funcionando, pero no podrás crear otras.',
        'subordinacion' => ':n propiedad depende de esto: dejará de actualizarse.|:n propiedades dependen de esto: dejarán de actualizarse.',
        'ical' => ':n calendario dejará de actualizarse, y los portales ya no podrán leer los tuyos.|:n calendarios dejarán de actualizarse, y los portales ya no podrán leer los tuyos.',
        'grupos' => 'Ya tienes :n grupo. Sus reservas siguen ahí, pero la pantalla de grupos no abrirá.|Ya tienes :n grupos. Sus reservas siguen ahí, pero la pantalla de grupos no abrirá.',
        'integraciones' => ':n aviso dejará de mandarse.|:n avisos dejarán de mandarse.',
    ],

    'preguntas' => [
        'titulo' => 'Vamos a dejarlo a tu medida',
        'explicacion' => 'Cinco preguntas rápidas para prender solo lo que te sirve. Puedes cambiarlo después en Sistema → Módulos.',
        'paso' => 'Pregunta :paso de :total',
        'atras' => 'Atrás',
        'siguiente' => 'Siguiente',
        'terminar' => 'Terminar',
        'saltar' => 'Saltar y dejar todo prendido',
        'listo' => 'Listo. Puedes cambiarlo cuando quieras en Sistema → Módulos.',
        'propiedades' => [
            'titulo' => '¿Cuántos alojamientos vas a manejar aquí?',
            'una' => 'Uno',
            'una_ayuda' => 'Un hotel, unas cabañas, una casa o varias habitaciones en un mismo lugar.',
            'varias' => 'Varios',
            'varias_ayuda' => 'Alojamientos distintos, cada uno con sus unidades y sus datos.',
        ],
        'otro_sistema' => [
            'titulo' => '¿Ya usas otro sistema que va a seguir siendo el principal?',
            'no' => 'No, AlojaFlow será mi sistema',
            'no_ayuda' => 'Aquí capturas tus reservas, tarifas y huéspedes.',
            'principal' => 'Sí, HotelDruid o AlojaFlow 1.2.x',
            'principal_ayuda' => 'AlojaFlow copia sus reservas y agrega lo suyo encima, sin escribir nunca en él.',
            'alojaflow' => 'Sí, otra instalación de AlojaFlow',
            'alojaflow_ayuda' => 'Esta copia una propiedad de la otra con un código de conexión, sin escribir nunca en ella.',
        ],
        'reservas' => [
            'titulo' => '¿Qué más necesitas para tus reservas?',
            'ayuda' => 'Las que tomas por teléfono, por WhatsApp o en persona siempre se pueden capturar.',
        ],
        'operacion' => [
            'titulo' => '¿Qué quieres organizar aquí?',
            'ayuda' => 'Marca lo que hagas todos los días, o lo que te gustaría dejar de llevar en papel.',
        ],
        'dinero' => [
            'titulo' => '¿Cómo llevas el dinero y los papeles?',
            'ayuda' => 'Los pagos de cada reserva siempre se anotan. Esto es lo que se agrega encima.',
        ],
        'resumen' => [
            'titulo' => 'Así queda',
            'ayuda' => 'Si algo no te convence, vuelve atrás. También puedes cambiarlo más adelante.',
            'prendidos' => 'Prendido',
            'apagados' => 'Apagado',
            'base' => 'Hoy, calendario, reservas, huéspedes y lo demás de siempre',
        ],
    ],

    'pasos' => [
        'titulo' => 'Primeros pasos',
        'avance' => ':hechos de :total listos',
        'ocultar' => 'No mostrar más',
        'ir' => 'Ir',
        'copiar' => 'Copiar',
        'copiado' => 'Copiado',
        'propiedad' => ['titulo' => 'Crea tu propiedad', 'ayuda' => 'El nombre, el país y la moneda de tu alojamiento.'],
        'unidades' => ['titulo' => 'Agrega tus unidades', 'ayuda' => 'Las habitaciones, cabañas o departamentos que rentas.'],
        'tarifa' => ['titulo' => 'Define una tarifa', 'ayuda' => 'Cuánto cobras por noche, con sus temporadas si las tienes.'],
        'motor' => ['titulo' => 'Abre el motor de reservas', 'ayuda' => 'Para que los huéspedes reserven solos desde tu sitio.'],
        'correo' => ['titulo' => 'Configura el correo', 'ayuda' => 'Sin correo no salen los mensajes ni se puede recuperar una contraseña.'],
        'dos_pasos' => ['titulo' => 'Activa la verificación en dos pasos', 'ayuda' => 'Tu cuenta tiene acceso a todo: es la que más conviene proteger.'],
        'cron' => ['titulo' => 'Crea la tarea programada', 'ayuda' => 'Hace que las sincronizaciones, los mensajes y las copias corran a su hora. En el panel de tu hosting, crea una tarea programada (cron) con el intervalo más corto que te permita, con este comando. Si no la creas, esas tareas corren cuando alguien usa el sistema.'],
        'copia' => ['titulo' => 'Haz tu primera copia de seguridad', 'ayuda' => 'Y guárdala fuera del hosting.'],
        'equipo' => ['titulo' => 'Invita a tu equipo', 'ayuda' => 'Cada quien con su usuario y solo con lo que necesita ver. Si trabajas solo, ignora este paso.'],
    ],
];
