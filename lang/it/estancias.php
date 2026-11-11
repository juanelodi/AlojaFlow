<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'tarifa' => [
        'seccion' => 'Soggiorni lunghi',
        'seccion_ayuda' => 'Prezzo a settimana da 7 notti e al mese da 28. Un mese intero costa questo che abbia 28 o 31 notti, e le notti in più si addebitano a trentesimi. Lascia vuoto per addebitare a notte.',
        'semana' => 'Prezzo a settimana',
        'mes' => 'Prezzo al mese',
        'temporada_ayuda' => 'Una stagione senza questi prezzi applica il suo prezzo a notte, anche in un soggiorno lungo; se vuoi, dagliene di propri.',
    ],
    'impuesto' => [
        'exento' => 'Non addebitare da (notti)',
        'exento_ayuda' => 'Un soggiorno che raggiunge queste notti non la paga. Alcuni luoghi esentano così chi resta 30 notti o più; verifica con il tuo commercialista. Vuoto: si addebita sempre.',
    ],
    'motor' => [
        'semana' => 'A settimana: :precio',
        'mes' => 'Al mese: :precio',
        'por_persona' => 'a persona',
        'aplicado' => [
            'semana' => 'Con prezzo a settimana',
            'mes' => 'Con prezzo al mese',
            'temporada' => 'Con prezzo della stagione completa',
        ],
    ],
    'cuotas' => [
        'titulo' => 'Calendario dei pagamenti',
        'vacio' => 'Nessun calendario dei pagamenti',
        'vacio_ayuda' => 'Per un soggiorno lungo: suddivide il totale in pagamenti mensili o settimanali, con promemoria e una ricevuta per pagamento.',
        'periodo' => 'dal :desde al :hasta',
        'vence' => 'Scadenza',
        'generar' => 'Genera calendario',
        'generar_ayuda' => 'Suddivide il totale della prenotazione in pagamenti uguali, e l’ultimo copre ciò che non riempie un periodo. Sostituisce i pagamenti esistenti. Gli importi pagati si applicano prima alle scadenze più vicine.',
        'cada_cuanto' => 'Ogni quanto paga',
        'cada_mes' => 'Ogni mese',
        'cada_semana' => 'Ogni settimana',
        'primer_vencimiento' => 'Prima scadenza',
        'generadas' => '{0} Niente da suddividere.|{1} Calendario pronto, con :count pagamento.|[2,*] Calendario pronto, con :count pagamenti.',
        'agregar' => 'Aggiungi pagamento',
        'editar' => 'Modifica',
        'quitar' => 'Rimuovi',
        'concepto' => 'Descrizione',
        'concepto_ayuda' => 'Come lo vede l’ospite, ad esempio «Deposito».',
        'no_cuadra' => 'Attenzione: il calendario somma :suma e il totale della prenotazione è :total. Regola i pagamenti o genera di nuovo il calendario.',
        'saldo' => 'Saldo del soggiorno',
        'recordatorio' => 'Ciao :nombre: ti scriviamo da :propiedad per ricordarti il pagamento di :importe (:periodo), con scadenza il :fecha. Grazie!',
        'estados' => [
            'pagada' => 'Pagato',
            'atrasada' => 'In ritardo',
            'parcial' => 'Parziale',
            'pendiente' => 'In attesa',
        ],
    ],
    'publico' => [
        'titulo' => 'I tuoi pagamenti',
        'periodo' => 'Periodo',
        'vence' => 'Scade il :fecha',
    ],
    'pagina' => [
        'titulo' => 'Mensilità',
        'ayuda' => 'Quanto devono ancora i soggiorni lunghi con calendario dei pagamenti. AlojaFlow ricorda; come incassare lo decidi tu.',
        'recordatorios' => 'Promemoria via e-mail',
        'recordatorios_ayuda' => 'Invia all’ospite un’e-mail 3 giorni prima di ogni pagamento e un’altra il giorno dopo se non è arrivato. Puoi cambiare i giorni e il testo in Automazioni e Modelli.',
        'recordatorios_prender' => 'Attiva i promemoria',
        'recordatorios_ok' => 'Promemoria attivati.',
        'vencidas' => 'In ritardo',
        'proximas' => 'Nei prossimi :dias giorni',
        'sin_vencidas' => 'Niente in ritardo.',
        'sin_proximas' => 'Niente in scadenza.',
        'falta' => 'Da pagare',
    ],
    'mensajes' => [
        'cuota_por_vencer' => [
            'nombre' => 'Promemoria di pagamento',
            'asunto' => 'Il tuo prossimo pagamento a {propiedad}',
            'cuerpo' => "Ciao {nombre},\n\nti ricordiamo il pagamento di {cuota_importe} ({cuota_periodo}), con scadenza il {cuota_vence}.\n\n{enlace_mi_reserva}\n\nGrazie.\n{propiedad}",
        ],
        'cuota_vencida' => [
            'nombre' => 'Pagamento in ritardo',
            'asunto' => 'Il tuo pagamento a {propiedad} è ancora in sospeso',
            'cuerpo' => "Ciao {nombre},\n\nil tuo pagamento di {cuota_importe} ({cuota_periodo}) scadeva il {cuota_vence} e non lo vediamo ancora. Se l’hai già fatto, mandaci la ricevuta e ignora questo messaggio.\n\n{enlace_mi_reserva}\n\nGrazie.\n{propiedad}",
        ],
    ],
];
