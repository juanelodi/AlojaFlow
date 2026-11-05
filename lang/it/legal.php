<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'documentos' => [
        'ine' => 'INE (documento elettorale messicano)',
        'pasaporte' => 'Passaporto',
        'licencia' => 'Patente di guida',
        'fmm' => 'Modulo migratorio (FMM)',
        'dni' => 'DNI',
        'nie' => 'NIE',
        'identificacion' => 'Documento di identità',
        'otro' => 'Altro',
    ],
    'campos' => [
        'procedencia' => 'Città di provenienza',
        'motivo_viaje' => 'Motivo del viaggio',
        'placas' => 'Targa del veicolo',
        'rfc' => 'RFC (se ti serve una fattura)',
        'soporte' => 'Numero di supporto',
        'parentesco' => 'Parentela (per i minori)',
    ],
    'generico' => [
        'aviso_privacidad' => [
            'titulo' => 'Informativa sulla privacy',
            'cuerpo' => "{propiedad} ({domicilio}) è responsabile dei tuoi dati personali.\n\nLi usiamo per gestire la tua prenotazione e il tuo soggiorno, registrarti come ospite, incassare e rispettare la legge. Li condividiamo solo con i fornitori che ci aiutano a prestare il servizio e con le autorità quando la legge lo richiede. Non li vendiamo.\n\nLa foto del tuo documento è conservata fino a 30 giorni dopo la partenza; i dati di registrazione, un anno.\n\nPuoi accedere ai tuoi dati, correggerli o chiederne la cancellazione scrivendo a {correo}.\n\nQuesto testo è una base: deve essere adattato alla legge del tuo paese.",
        ],
        'contrato_grupo' => [
            'titulo' => 'Contratto di gruppo',
            'cuerpo' => <<<'TEXTO'
Questo contratto regola il soggiorno del gruppo «{grupo}» ({codigo}) presso {propiedad}, coordinato da {coordinador}.

1. SOGGIORNO. Dal {llegada} al {salida}, con {habitaciones} camere.
2. PREZZO. Il totale è {total}. Pagamenti concordati:
{pagos}
3. ELENCO OSPITI. Il coordinatore indicherà chi va in ogni camera entro il {fecha_limite}.
4. CANCELLAZIONE. Si applica la politica di cancellazione comunicata.
5. CONDIZIONI. Il coordinatore fa conoscere al gruppo il regolamento della struttura. Ogni ospite risponde dei danni che causa.
6. RECLAMI. Scrivici a {correo}.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Condizioni di soggiorno',
            'cuerpo' => "Queste condizioni regolano il tuo soggiorno presso {propiedad}.\n\n1. Il prezzo è quello indicato nella prenotazione.\n2. Arrivo e partenza avvengono negli orari comunicati.\n3. Possono soggiornare solo le persone registrate.\n4. Si applica la politica di cancellazione comunicata alla prenotazione.\n5. L'ospite risponde dei danni che causa.\n\nPer qualsiasi reclamo, scrivi a {correo}.",
        ],
        'reglamento' => [
            'titulo' => 'Regolamento interno',
            'cuerpo' => 'Per il comfort di tutti, presso {propiedad} ti chiediamo di rispettare il riposo degli altri, non fumare nelle camere, registrare tutte le persone che soggiornano e segnalarci qualsiasi danno.',
        ],
        'politica_cancelacion' => [
            'titulo' => 'Politica di cancellazione',
            'cuerpo' => 'Puoi cancellare gratuitamente fino a [numero] giorni prima dell\'arrivo. Dopo verrà addebitato il [percentuale] % del totale. In caso di mancata presentazione verrà addebitata la prima notte. Per cancellare, scrivi a {correo}.',
        ],
    ],
];
