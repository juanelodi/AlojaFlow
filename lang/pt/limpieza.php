<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Limpeza',
    'tarea_salida' => 'Limpar :unidad (saída)',
    'tarea_estancia' => 'Limpar :unidad (hóspede alojado)',
    'solo_mias' => 'Só as minhas',
    'vacio' => 'Nada para mostrar.',
    'sale_hoy' => 'Sai hoje.',
    'llega_hoy' => 'Chega hoje.',
    'alojada' => 'Hóspede alojado.',
    'libre' => 'Livre.',
    'fuera_de_servicio' => 'Fora de serviço',
    'urgente_corto' => 'Urgente',
    'ver_foto' => 'Foto :n',
    'empezar' => 'Começar',
    'terminar' => 'Terminar',
    'marcar_limpia' => 'Marcar como limpa',
    'marcar_sucia' => 'Marcar como suja',
    'volver_servicio' => 'Voltar ao serviço',
    'poner_fuera' => 'Colocar fora de serviço',
    'marcada' => ':unidad: :estado.',
    'terminada' => 'Pronto: :tarea.',
    'incidencia' => 'Comunicar problema',
    'incidencia_de' => 'Problema em :unidad',
    'incidencia_reportar' => 'Comunicar',
    'incidencia_titulo' => 'O que se passa?',
    'incidencia_titulo_ayuda' => 'Por exemplo: o chuveiro pinga.',
    'incidencia_descripcion' => 'Detalhes',
    'incidencia_ok' => 'Problema comunicado.',
    'fotos' => 'Fotos',
    'urgente' => 'É urgente',
    'dejar_fuera' => 'Deixar a unidade fora de serviço (não poderá ser vendida)',
    'aviso_whatsapp' => 'Enviar por WhatsApp',
    'aviso_titulo' => ':propiedad · limpeza de :fecha',
    'aviso_vacio' => 'Sem tarefas.',
    'tareas' => [
        'modelo' => 'tarefa',
        'modelos' => 'Tarefas',
        'tipo' => 'Tipo',
        'titulo' => 'Tarefa',
        'descripcion' => 'Descrição',
        'fecha' => 'Data',
        'estado' => 'Estado',
        'unidad' => 'Unidade',
        'asignada' => 'Atribuída a',
        'sin_asignar' => 'Sem atribuição',
        'urgente' => 'Urgente',
        'vacio' => 'Não há tarefas.',
        'abiertas' => 'Por fazer',
    ],
    'regla' => [
        'accion' => 'Limpeza durante a estadia',
        'frecuencia' => 'Com que frequência?',
        'sin_limpieza' => 'Só à saída',
        'dias' => 'Dias',
        'fechas' => 'Datas',
        'guardada' => 'Limpeza da estadia guardada.',
    ],
    'inspeccion' => 'Verificar as unidades depois de as limpar',
    'inspeccion_ayuda' => 'Uma unidade limpa fica «Por verificar» até alguém a inspecionar.',
];
