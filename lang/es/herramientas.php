<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'duplicados' => [
        'titulo' => 'Fichas duplicadas',
        'ayuda' => 'Fichas que pueden ser la misma persona. Revísalas antes de juntarlas: las reservas, contactos y notas pasan a la que conserves, y no se puede deshacer.',
        'vacio' => 'No se encontraron fichas duplicadas.',
        'contacto' => 'Contacto',
        'reservas' => 'Reservas',
        'conservar' => 'Fusionar en esta',
        'confirmar' => 'Las demás fichas de este grupo se unirán a la de :nombre y se borrarán. No se puede deshacer.',
        'motivos' => [
            'nombre' => 'Mismo nombre',
            'correo' => 'Mismo correo',
            'telefono' => 'Mismo teléfono',
            'documento' => 'Mismo documento',
        ],
    ],
    'anonimizar' => [
        'titulo' => 'Anonimizar por antigüedad',
        'ayuda' => 'Quienes llevan años sin venir. Sus datos personales se borran y sus reservas quedan a nombre de «Anonimizado», para que las estadísticas sigan cuadrando. Los documentos ya guardados no cambian, y lo que viene de una principal se anonimiza allá.',
        'anios' => 'Años sin estancias',
        'encontrados' => '{0} Nadie lleva tanto sin venir|{1} :n persona lleva tanto sin venir|[2,*] :n personas llevan tanto sin venir',
        'vacio' => 'No hay nada que anonimizar con este plazo.',
        'accion' => 'Anonimizar a todos',
        'confirmar' => '{1} Se anonimizará :n ficha sin estancias en :anios años. No se puede deshacer.|[2,*] Se anonimizarán :n fichas sin estancias en :anios años, hasta 200 cada vez. No se puede deshacer.',
        'hecho' => '{1} :n ficha anonimizada.|[0,*] :n fichas anonimizadas.',
    ],
    'tipo_cliente' => [
        'titulo' => 'Tipo de cliente',
        'ayuda' => 'Datos libres de esta ficha, como «Tipo: VIP». Si coinciden con un color de Colores de clientes, sus reservas se marcan así en el calendario.',
        'agregar' => 'Agregar dato',
    ],
    'franja' => [
        'dias' => 'Días en que se copia',
        'dias_ayuda' => 'Sin marcar ninguno, todos los días.',
        'desde' => 'Copiar desde las',
        'hasta' => 'Hasta las',
        'ayuda' => 'En la hora de la propiedad. Puede cruzar la medianoche, como de 22:00 a 06:00. Vacío, a cualquier hora. Copiar a mano no tiene este límite.',
    ],
    'region' => [
        'campo' => 'Estado o región',
        'ayuda' => 'Con ella se propone el impuesto de hospedaje que corresponde.',
    ],
    'colores' => [
        'titulo' => 'Colores del calendario',
        'ayuda' => 'Solo cambian para ti. Vacío, el color de siempre.',
    ],
    'registro' => [
        'modelo' => 'registro turístico',
        'modelos' => 'Registros turísticos',
        'ayuda' => 'El número de registro o licencia de la propiedad o de una unidad. Se enseña en las páginas públicas y en los documentos, y AlojaFlow avisa 30 días antes de que venza.',
        'toda' => 'Toda la propiedad',
        'tipo' => 'Tipo',
        'tipo_ayuda' => 'Como se conoce: RNT, Registro Nacional de Turismo, VUT…',
        'numero' => 'Número',
        'vigente_hasta' => 'Vigente hasta',
        'vigente_hasta_ayuda' => 'Vacío si no vence.',
        'mostrar' => 'Enseñarlo al público',
        'mostrar_ayuda' => 'En las páginas públicas y en los documentos.',
        'vacio' => 'Sin registros todavía',
        'responsables' => 'Responsable y quejas',
        'responsables_ayuda' => 'Lo que algunos países piden tener a la vista. Se enseña junto al número de registro.',
        'responsable' => 'Responsable',
        'autoridad_quejas' => 'Autoridad para quejas',
        'autoridad_quejas_ayuda' => 'Por ejemplo, en México, la Profeco y la Secretaría de Turismo.',
    ],
];
