<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'segunda' => [
        'moneda' => 'Segunda moneda',
        'moneda_ayuda' => 'Opcional, como USD. Se muestra junto a los precios y se puede recibir efectivo en ella; todo se suma en la moneda de la propiedad.',
        'tipo' => 'Tipo de cambio',
        'tipo_ayuda' => 'Cuánto vale 1 :segunda en :moneda. Lo escribes tú: cámbialo cuando cambie.',
        'recibido_en' => 'Recibido en',
        'tipo_pago' => 'Tipo de cambio de este pago',
        'se_anota' => 'En la reserva se anotan :importe.',
        'recibido' => ':importe a :tipo',
        'recibido_titulo' => 'Recibido',
        'efectivo_en' => 'Efectivo en :moneda (se cuenta aparte)',
    ],
    'politicas' => [
        'no_reembolsable' => 'No reembolsable',
        'no_reembolsable_ayuda' => 'Si cancelan, en cualquier momento, se cobra el total.',
        'modelo' => 'política',
        'modelos' => 'Políticas',
        'nombre' => 'Nombre',
        'dias_gratis' => 'Cancelación gratis hasta (días antes)',
        'penalizacion' => 'Penalización después (%)',
        'no_show' => 'Penalización si no llega (%)',
        'deposito' => 'Anticipo (%)',
        'deposito_plazo' => 'Plazo para pagar el anticipo (días)',
        'texto' => 'Texto para el huésped',
        'predeterminada' => 'Predeterminada',
        'predeterminada_ayuda' => 'Se aplica cuando el canal de la reserva no tiene una propia.',
        'vacio' => 'Todavía no hay políticas.',
        'resumen_cancelacion' => 'Según la política «:politica», la penalización es de :penalizacion. Ya pagó :pagado: a devolver :devolver, por cobrar :cobrar.',
        'no_show_accion' => 'No llegó',
        'no_show_ayuda' => 'Marca que el huésped no se presentó. En una propiedad independiente, además cancela la reserva y libera las noches.',
        'no_show_nota' => 'Nota',
        'no_show_ok' => 'Marcado como no llegó. Penalización: :importe.',
        'error_no_show' => 'Solo se puede marcar el día de llegada o después, y si no se registró la entrada.',
    ],
];
