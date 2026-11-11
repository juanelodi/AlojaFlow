<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'ocupado' => 'Indisponível',
    'error_formato' => 'O endereço não devolveu um calendário iCal válido.',
    'error_tamano' => 'O calendário é demasiado grande.',
    'calendario_externo' => 'Ocupado em :fuente',
    'fuentes' => [
        'modelo' => 'calendário de um portal',
        'modelos' => 'Calendários iCal',
        'ayuda' => 'Cole a ligação iCal fornecida por cada portal (Airbnb, Booking.com, Vrbo…). Essas datas deixam de ser vendidas aqui e, se houver conflito com uma reserva, é avisado.',
        'unidad' => 'Quarto',
        'nombre' => 'Nome',
        'nombre_ayuda' => 'Por exemplo: Airbnb.',
        'canal' => 'Canal',
        'url' => 'Ligação iCal do portal',
        'url_ayuda' => 'É guardada cifrada porque contém uma chave do portal.',
        'url_invalida' => 'Esse endereço não é permitido: deve ser público.',
        'frecuencia' => 'Ler a cada',
        'activa' => 'Ativo',
        'estado' => 'Estado',
        'ultima_lectura' => 'Última leitura',
        'vacio' => 'Ainda não há calendários de portais.',
        'leer' => 'Ler agora',
        'leida' => 'Calendário lido: :n datas ocupadas.',
        'no_leida' => 'Não foi possível ler o calendário',
    ],
    'exportar' => [
        'accion' => 'Ligações para os portais',
        'propiedad' => 'Alojamento',
        'ayuda' => 'Cole em cada portal a ligação do respetivo quarto. Mostra apenas datas ocupadas: nunca dados de hóspedes.',
        'vacio' => 'Este alojamento não tem quartos.',
        'regenerar' => 'Mudar as ligações',
        'regenerar_ayuda' => 'As ligações atuais deixarão de funcionar e terá de colar as novas em cada portal. Use esta opção se alguém que não deve as tiver.',
        'regenerados' => 'Ligações alteradas.',
    ],
    'choques' => [
        'modelo' => 'conflito',
        'modelos' => 'Conflitos',
        'desde' => 'De',
        'hasta' => 'Até',
        'unidad' => 'Quarto',
        'reserva' => 'Reserva',
        'reserva_n' => 'Reserva :numero',
        'con' => 'Em conflito com',
        'nota' => 'Nota',
        'abiertos' => 'Por resolver',
        'vacio' => 'Não há conflitos: nenhum quarto está ocupado duas vezes.',
        'resolver' => 'Marcar como resolvido',
    ],
];
