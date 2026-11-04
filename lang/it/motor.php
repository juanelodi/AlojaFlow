<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Prenota il tuo soggiorno',
    'idioma' => 'Lingua',
    'buscar' => 'Cerca',
    'reservar' => 'Prenota',
    'capacidad' => 'Fino a :personas persone',
    'quedan' => '{1} ne resta 1|[2,*] ne restano :count',
    'sin_resultados' => 'Nessuna disponibilità per queste date. Provane altre.',
    'tus_datos' => 'I tuoi dati',
    'n_personas' => ':personas persone',
    'anticipo' => 'Per garantire la prenotazione è richiesto un acconto del :porcentaje %.',
    'no_llenar' => 'Non compilare questo campo',
    'nombre' => 'Nome',
    'apellido' => 'Cognome',
    'correo' => 'E-mail',
    'telefono' => 'Telefono',
    'extras' => 'Extra',
    'cupon' => 'Codice sconto',
    'comentario' => 'Commenti (ora di arrivo, richieste…)',
    'condiciones' => 'le condizioni',
    'acepto' => 'Accetto le condizioni della prenotazione e l\'uso dei miei dati per gestirla.',
    'confirmar' => 'Effettua la prenotazione',
    'volver' => 'Indietro',
    'origen' => 'Motore di prenotazione',
    'concepto' => 'Prenotazione :numero presso :propiedad',
    'tu_reserva' => 'La tua prenotazione',
    'estado_confirmada' => 'La tua prenotazione è confermata. Ti aspettiamo!',
    'estado_cancelada' => 'Questa prenotazione è annullata.',
    'estado_pendiente' => 'Abbiamo ricevuto la tua richiesta. Ti avviseremo non appena sarà confermata.',
    'estado_pagar' => 'Il tuo posto è riservato fino a :fecha. Paga l\'acconto per confermare la prenotazione.',
    'pagar_titulo' => 'Paga :importe',
    'pagar_ayuda' => 'Si apre la pagina sicura della società di pagamento. I dati della tua carta non passano da questo sito.',
    'pagar_con' => [
        'stripe' => 'Paga con carta (Stripe)',
        'mercadopago' => 'Paga con Mercado Pago',
    ],
    'instrucciones' => 'Come pagare',
    'pie' => 'Prenotazioni con AlojaFlow',
    'error' => [
        'cerrado' => 'Le prenotazioni online non sono disponibili in questo momento.',
        'fechas' => 'Controlla le date: la partenza deve essere dopo l\'arrivo.',
        'fecha_pasada' => 'Questa data di arrivo non è più prenotabile online.',
        'sin_lugar' => 'Non c’è più posto con questa tariffa per queste date.',
        'estancia_minima' => 'Soggiorno minimo: :noches notti.',
        'pasarela' => 'Non è stato possibile aprire il pagamento. Riprova tra qualche minuto.',
        'nada_que_cobrar' => 'Questa prenotazione non ha nulla da pagare online.',
    ],
];
