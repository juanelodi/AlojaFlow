<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'tarifa' => [
        'seccion' => 'Estancias largas',
        'seccion_ayuda' => 'Precio por semana desde 7 noches y por mes desde 28. Un mes completo cuesta esto aunque tenga 28 o 31 noches, y lo que sobra se cobra a treintavos. Déjalos vacíos para cobrar por noche.',
        'semana' => 'Precio por semana',
        'mes' => 'Precio por mes',
        'temporada_ayuda' => 'Una temporada sin estos precios cobra su precio por noche, también en una estancia larga; si quieres, ponle los suyos.',
    ],
    'impuesto' => [
        'exento' => 'No cobrar desde (noches)',
        'exento_ayuda' => 'Una estancia que llega a estas noches no lo paga. Algunos lugares exentan así a quien se queda 30 noches o más; confírmalo con tu contador. Vacío: siempre se cobra.',
    ],
    'motor' => [
        'semana' => 'Por semana: :precio',
        'mes' => 'Por mes: :precio',
        'por_persona' => 'por persona',
        'aplicado' => [
            'semana' => 'Con precio por semana',
            'mes' => 'Con precio por mes',
            'temporada' => 'Con precio de temporada completa',
        ],
    ],
    'cuotas' => [
        'titulo' => 'Calendario de cobros',
        'vacio' => 'Sin calendario de cobros',
        'vacio_ayuda' => 'Para una estancia larga: reparte el total en pagos por mes o por semana, con recordatorios y un recibo por pago.',
        'periodo' => 'del :desde al :hasta',
        'vence' => 'Vence',
        'generar' => 'Generar calendario',
        'generar_ayuda' => 'Reparte el total de la reserva en pagos iguales, y el último cubre lo que no llene un periodo. Sustituye los pagos que haya. Lo pagado se aplica a los primeros en vencer.',
        'cada_cuanto' => 'Cada cuánto paga',
        'cada_mes' => 'Cada mes',
        'cada_semana' => 'Cada semana',
        'primer_vencimiento' => 'Primer vencimiento',
        'generadas' => '{0} No quedó nada por repartir.|{1} Calendario listo, con :count pago.|[2,*] Calendario listo, con :count pagos.',
        'agregar' => 'Agregar pago',
        'editar' => 'Editar',
        'quitar' => 'Quitar',
        'concepto' => 'Concepto',
        'concepto_ayuda' => 'Cómo lo ve el huésped, por ejemplo «Depósito».',
        'no_cuadra' => 'Ojo: el calendario suma :suma y el total de la reserva es :total. Ajusta los pagos o genera el calendario otra vez.',
        'saldo' => 'Saldo de la estancia',
        'recordatorio' => 'Hola :nombre: te escribimos de :propiedad para recordarte tu pago de :importe (:periodo), con vencimiento el :fecha. ¡Gracias!',
        'estados' => [
            'pagada' => 'Pagado',
            'atrasada' => 'Atrasado',
            'parcial' => 'Parcial',
            'pendiente' => 'Pendiente',
        ],
    ],
    'publico' => [
        'titulo' => 'Tus pagos',
        'periodo' => 'Periodo',
        'vence' => 'Vence el :fecha',
    ],
    'pagina' => [
        'titulo' => 'Mensualidades',
        'ayuda' => 'Lo que deben las estancias largas con calendario de cobros. AlojaFlow te recuerda; cobrar lo decides tú.',
        'recordatorios' => 'Recordatorios por correo',
        'recordatorios_ayuda' => 'Manda al huésped un correo 3 días antes de cada pago y otro al día siguiente si no llegó. Puedes cambiar los días y el texto en Automatizaciones y Plantillas.',
        'recordatorios_prender' => 'Prender recordatorios',
        'recordatorios_ok' => 'Recordatorios prendidos.',
        'vencidas' => 'Vencidas',
        'proximas' => 'En los próximos :dias días',
        'sin_vencidas' => 'Nada vencido.',
        'sin_proximas' => 'Nada por vencer.',
        'falta' => 'Falta',
    ],
    'mensajes' => [
        'cuota_por_vencer' => [
            'nombre' => 'Recordatorio de pago',
            'asunto' => 'Tu próximo pago en {propiedad}',
            'cuerpo' => "Hola {nombre}:\n\nTe recordamos tu pago de {cuota_importe} ({cuota_periodo}), con vencimiento el {cuota_vence}.\n\n{enlace_mi_reserva}\n\nGracias.\n{propiedad}",
        ],
        'cuota_vencida' => [
            'nombre' => 'Pago vencido',
            'asunto' => 'Tu pago en {propiedad} sigue pendiente',
            'cuerpo' => "Hola {nombre}:\n\nTu pago de {cuota_importe} ({cuota_periodo}) venció el {cuota_vence} y todavía no lo vemos. Si ya lo hiciste, mándanos tu comprobante y no hagas caso de este mensaje.\n\n{enlace_mi_reserva}\n\nGracias.\n{propiedad}",
        ],
    ],
];
