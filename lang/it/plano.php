<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Planimetria',
    'vacio' => 'Non c’è ancora una planimetria. Creane una e posiziona le camere.',
    'nuevo' => 'Nuova planimetria',
    'nombre' => 'Planimetria',
    'nombre_ayuda' => 'Per esempio: Piano terra.',
    'imagen' => 'Immagine della planimetria (facoltativa)',
    'imagen_ayuda' => 'Un disegno o una foto. Senza immagine, le camere si posizionano su uno sfondo uniforme.',
    'creado' => 'Planimetria creata: trascina ogni camera al suo posto.',
    'editar' => 'Posiziona le camere',
    'terminar' => 'Termina',
    'editar_ayuda' => 'Trascina ogni camera al suo posto. Si salva da sola.',
    'borrar' => 'Elimina questa planimetria',
    'estados' => [
        'libre' => 'Libera',
        'llega' => 'Arriva oggi',
        'sale' => 'Parte oggi',
        'ocupada' => 'Occupata',
    ],
    'colores' => [
        'modelo' => 'colore cliente',
        'modelos' => 'Colori clienti',
        'ayuda' => 'Colora le prenotazioni nel calendario secondo un dato della scheda dell’ospite, come «Tipo di cliente: Azienda».',
        'campo' => 'Campo della scheda',
        'campo_ayuda' => 'Il nome del campo personalizzato, così come appare nella scheda.',
        'valor' => 'Valore',
        'color' => 'Colore',
        'vacio' => 'Non ci sono ancora colori.',
    ],
];
