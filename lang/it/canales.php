<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'comision_base' => 'La commissione si calcola su',
    'comision_base_ayuda' => 'Controlla il contratto: alcuni portali la applicano sul totale, altri senza imposte.',
    'cargos' => 'Altri costi e ritenute',
    'cargos_ayuda' => 'Ciò che il canale trattiene oltre alla commissione. Serve a conoscere l’utile lordo e netto di ogni prenotazione.',
    'anadir' => 'Aggiungi costo',
    'nombre' => 'Nome',
    'clase' => 'Tipo',
    'calculo' => 'Come si calcola',
    'valor' => 'Valore (% o importo)',
    'base' => 'Calcolato su',
    'base_ayuda' => 'Tariffa base o finale: guarda come la calcola il portale nel contratto o estratto conto.',
    'sugerir' => 'Suggerisci i costi di un portale',
    'sugerir_ayuda' => 'Aggiunge i costi abituali di quel portale da adattare. Sono un riferimento: vale il tuo contratto. Quelli che dipendono dal tuo account arrivano spenti.',
    'plataforma' => 'Portale',
    'elige_propiedad' => 'Scegli prima la struttura.',
    'sugeridos' => [
        'pago_booking' => 'Costo di pagamento (solo con Payments by Booking.com)',
        'visibilidad_booking' => 'Genius o Partner Preferito',
        'pago_expedia' => 'Costo di incasso Expedia',
        'visibilidad_expedia' => 'Programmi di visibilità',
        'iva_comision' => 'IVA su commissione e costi',
        'retencion_isr' => 'Ritenuta sul reddito (con codice fiscale; senza, 20%)',
        'retencion_iva' => 'Ritenuta IVA (con codice fiscale; senza, 16%)',
    ],
    'utilidad' => [
        'titulo' => 'Utile',
        'ingreso' => 'Paga l’ospite',
        'impuestos' => 'Imposte incassate',
        'bruta' => 'Lordo',
        'neta' => 'Netto',
        'ayuda' => 'Con i costi attuali del canale. Le ritenute di solito si detraggono nella dichiarazione: chiedi al commercialista.',
        'sin_canal' => 'L’origine di questa prenotazione non corrisponde a un canale: si sottrae solo la commissione.',
    ],
];
