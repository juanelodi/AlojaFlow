<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'comision_base' => 'La commission est calculée sur',
    'comision_base_ayuda' => 'Vérifiez votre contrat : certains portails la prélèvent sur le total, d’autres hors taxes.',
    'cargos' => 'Autres frais et retenues',
    'cargos_ayuda' => 'Ce que le canal prélève en plus de sa commission. Sert à connaître le bénéfice brut et net de chaque réservation.',
    'anadir' => 'Ajouter des frais',
    'nombre' => 'Nom',
    'clase' => 'Type',
    'calculo' => 'Mode de calcul',
    'valor' => 'Valeur (% ou montant)',
    'base' => 'Calculé sur',
    'base_ayuda' => 'Tarif de base ou final : voyez comment le portail le calcule dans votre contrat ou relevé.',
    'sugerir' => 'Suggérer les frais d’un portail',
    'sugerir_ayuda' => 'Ajoute les frais habituels de ce portail à ajuster. C’est une référence : votre contrat prévaut. Ceux qui dépendent de votre compte arrivent désactivés.',
    'plataforma' => 'Portail',
    'elige_propiedad' => 'Choisissez d’abord l’établissement.',
    'sugeridos' => [
        'pago_booking' => 'Frais de paiement (uniquement avec Payments by Booking.com)',
        'visibilidad_booking' => 'Genius ou Partenaire Préféré',
        'pago_expedia' => 'Frais d’encaissement Expedia',
        'visibilidad_expedia' => 'Programmes de visibilité',
        'iva_comision' => 'TVA sur commission et frais',
        'retencion_isr' => 'Retenue d’impôt sur le revenu (avec n° fiscal ; sans, 20 %)',
        'retencion_iva' => 'Retenue de TVA (avec n° fiscal ; sans, 16 %)',
    ],
    'utilidad' => [
        'titulo' => 'Bénéfice',
        'ingreso' => 'Payé par le client',
        'impuestos' => 'Taxes perçues',
        'bruta' => 'Brut',
        'neta' => 'Net',
        'ayuda' => 'Avec les frais actuels du canal. Les retenues sont en général imputables sur votre déclaration : demandez à votre comptable.',
        'sin_canal' => 'L’origine de cette réservation ne correspond à aucun canal : seule sa commission est déduite.',
    ],
];
