<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Planta',
    'vacio' => 'Ainda não há uma planta. Crie uma e coloque os seus quartos.',
    'nuevo' => 'Nova planta',
    'nombre' => 'Planta',
    'nombre_ayuda' => 'Por exemplo: Rés do chão.',
    'imagen' => 'Imagem da planta (opcional)',
    'imagen_ayuda' => 'Um desenho ou uma foto. Sem imagem, os quartos são colocados sobre um fundo liso.',
    'creado' => 'Planta criada: arraste cada quarto para o seu lugar.',
    'editar' => 'Colocar quartos',
    'terminar' => 'Terminar',
    'editar_ayuda' => 'Arraste cada quarto para o seu lugar. É guardado automaticamente.',
    'borrar' => 'Apagar esta planta',
    'estados' => [
        'libre' => 'Livre',
        'llega' => 'Chega hoje',
        'sale' => 'Sai hoje',
        'ocupada' => 'Ocupado',
    ],
    'colores' => [
        'modelo' => 'cor de cliente',
        'modelos' => 'Cores de clientes',
        'ayuda' => 'Pinta as reservas no calendário segundo um dado da ficha do hóspede, como «Tipo de cliente: Empresa».',
        'campo' => 'Campo da ficha',
        'campo_ayuda' => 'O nome do campo personalizado, tal como está na ficha.',
        'valor' => 'Valor',
        'color' => 'Cor',
        'vacio' => 'Ainda não há cores.',
    ],
];
