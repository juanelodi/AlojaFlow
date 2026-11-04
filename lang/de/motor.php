<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Buchen Sie Ihren Aufenthalt',
    'idioma' => 'Sprache',
    'buscar' => 'Suchen',
    'reservar' => 'Buchen',
    'capacidad' => 'Bis zu :personas Personen',
    'quedan' => '{1} noch 1 verfügbar|[2,*] noch :count verfügbar',
    'sin_resultados' => 'Für diese Daten ist nichts frei. Versuchen Sie andere.',
    'tus_datos' => 'Ihre Daten',
    'n_personas' => ':personas Personen',
    'anticipo' => 'Zur Sicherung der Buchung wird eine Anzahlung von :porcentaje % verlangt.',
    'no_llenar' => 'Dieses Feld nicht ausfüllen',
    'nombre' => 'Vorname',
    'apellido' => 'Nachname',
    'correo' => 'E-Mail',
    'telefono' => 'Telefon',
    'extras' => 'Extras',
    'cupon' => 'Rabattcode',
    'comentario' => 'Anmerkungen (Ankunftszeit, Wünsche…)',
    'condiciones' => 'die Bedingungen',
    'acepto' => 'Ich akzeptiere die Buchungsbedingungen und die Verwendung meiner Daten zu deren Abwicklung.',
    'confirmar' => 'Jetzt buchen',
    'volver' => 'Zurück',
    'origen' => 'Buchungsmaschine',
    'concepto' => 'Buchung :numero bei :propiedad',
    'tu_reserva' => 'Ihre Buchung',
    'estado_confirmada' => 'Ihre Buchung ist bestätigt. Wir freuen uns auf Sie!',
    'estado_cancelada' => 'Diese Buchung ist storniert.',
    'estado_pendiente' => 'Wir haben Ihre Anfrage erhalten. Wir melden uns, sobald wir sie bestätigt haben.',
    'estado_pagar' => 'Ihr Platz ist bis :fecha reserviert. Zahlen Sie die Anzahlung, um die Buchung zu bestätigen.',
    'pagar_titulo' => ':importe zahlen',
    'pagar_ayuda' => 'Die sichere Seite des Zahlungsanbieters wird geöffnet. Ihre Kartendaten laufen nicht über diese Website.',
    'pagar_con' => [
        'stripe' => 'Mit Karte zahlen (Stripe)',
        'mercadopago' => 'Mit Mercado Pago zahlen',
    ],
    'instrucciones' => 'So zahlen Sie',
    'pie' => 'Buchungen mit AlojaFlow',
    'error' => [
        'cerrado' => 'Online-Buchungen sind derzeit nicht möglich.',
        'fechas' => 'Prüfen Sie die Daten: Die Abreise muss nach der Ankunft liegen.',
        'fecha_pasada' => 'Dieses Ankunftsdatum kann nicht mehr online gebucht werden.',
        'sin_lugar' => 'Mit diesem Tarif ist für diese Daten nichts mehr frei.',
        'estancia_minima' => 'Mindestaufenthalt: :noches Nächte.',
        'pasarela' => 'Die Zahlung konnte nicht geöffnet werden. Versuchen Sie es in einigen Minuten erneut.',
        'nada_que_cobrar' => 'Für diese Buchung ist online nichts mehr zu zahlen.',
    ],
];
