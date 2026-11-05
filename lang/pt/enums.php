<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ModoPropiedad' => [
        'nativa' => 'Independente',
        'subordinada' => 'Subordinada',
    ],
    'MetodoPago' => [
        'efectivo' => 'Dinheiro',
        'tarjeta' => 'Cartão',
        'transferencia' => 'Transferência',
        'otro' => 'Outro',
    ],
    'ModoTarifa' => [
        'por_noche' => 'Por noite (com pessoa extra)',
        'por_persona' => 'Por pessoa',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Por estadia',
        'por_noche' => 'Por noite',
        'por_persona' => 'Por pessoa',
        'por_persona_noche' => 'Por pessoa e noite',
        'porcentaje_alojamiento' => 'Percentagem do alojamento',
    ],
    'TipoMovimiento' => [
        'gasto' => 'Despesa',
        'ingreso' => 'Receita',
    ],
    'TipoCupon' => [
        'porcentaje' => 'Percentagem',
        'importe' => 'Montante fixo',
    ],
    'PaquetePais' => [
        'mexico' => 'México',
        'espana' => 'Espanha',
        'estados_unidos' => 'Estados Unidos',
        'generico' => 'Genérico',
    ],
    'EstadoPrincipal' => [
        'activa' => 'Ativa',
        'pausada' => 'Em pausa',
    ],
    'MetodoPeticion' => [
        'post' => 'POST (recomendado)',
        'get' => 'GET (modo compatível)',
    ],
    'EstadoEjecucion' => [
        'en_curso' => 'Em curso',
        'correcta' => 'Correta',
        'fallida' => 'Falhada',
        'omitida' => 'Omitida',
    ],
    'OrigenEjecucion' => [
        'programada' => 'Agendada',
        'manual' => 'Manual',
    ],
    'TipoReserva' => [
        'reserva' => 'Reserva',
        'bloqueo' => 'Bloqueio',
    ],
    'EstadoReserva' => [
        'sin_confirmar' => 'Por confirmar',
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
    ],
    'TipoEventoReserva' => [
        'creada' => 'Nova',
        'modificada' => 'Alterada',
        'cancelada' => 'Cancelada',
        'eliminada' => 'Apagada na instalação principal',
        'reactivada' => 'Reativada',
    ],
    'NivelAviso' => [
        'informacion' => 'Informação',
        'advertencia' => 'Aviso',
        'error' => 'Erro',
    ],
    'TipoContacto' => [
        'telefono' => 'Telefone',
        'correo' => 'E-mail',
        'fax' => 'Fax',
    ],
    'TipoPrincipal' => ['hoteldruid' => 'HotelDruid ou AlojaFlow 1.2.x', 'alojaflow' => 'Outra instalação do AlojaFlow'],
    'OrigenFila' => [
        'hoteldruid' => 'HotelDruid',
        'alojaflow' => 'AlojaFlow', 'otra_instalacion' => 'Outra instalação'],
    'EstadoActualizacion' => [
        'preparada' => 'Preparada',
        'aplicando' => 'A aplicar',
        'archivos_aplicados' => 'Por terminar',
        'completada' => 'Concluída',
        'fallida' => 'Falhada',
        'revertida' => 'Revertida',
    ],
    'EstadoLimpieza' => [
        'limpia' => 'Limpa',
        'sucia' => 'Suja',
        'inspeccion' => 'Por verificar',
    ],
    'TipoTarea' => [
        'limpieza_salida' => 'Limpeza de saída',
        'limpieza_estancia' => 'Limpeza durante a estadia',
        'limpieza_profunda' => 'Limpeza profunda',
        'inspeccion' => 'Inspeção',
        'mantenimiento' => 'Manutenção',
        'otra' => 'Outra',
    ],
    'EstadoTarea' => [
        'pendiente' => 'Pendente',
        'en_curso' => 'Em curso',
        'hecha' => 'Feita',
        'cancelada' => 'Cancelada',
    ],
    'FrecuenciaLimpieza' => [
        'diaria' => 'Todos os dias',
        'semanal' => 'Alguns dias da semana',
        'fechas' => 'Dias escolhidos',
    ],
    'ClaseArchivo' => [
        'documento_identidad' => 'Documento de identificação',
        'firma' => 'Assinatura',
        'copia_aceptacion' => 'Cópia assinada',
        'foto_tarea' => 'Foto',
        'plano' => 'Planta',
        'factura_xml' => 'Fatura (XML)',
        'factura_pdf' => 'Fatura (PDF)',
        'exportacion' => 'Exportação',
    ],
    'CanalEnvio' => [
        'correo' => 'E-mail',
        'whatsapp_enlace' => 'WhatsApp',
        'webhook' => 'Aviso a outro sistema',
    ],
    'EstadoEnvio' => [
        'pendiente' => 'Por enviar',
        'enviado' => 'Enviado',
        'fallido' => 'Falhou',
        'omitido' => 'Não enviado',
        'manual' => 'Pronto para copiar',
    ],
    'DisparadorAutomatizacion' => [
        'reserva' => [
            'creada' => 'Ao criar a reserva',
            'confirmada' => 'Ao confirmá-la',
            'modificada' => 'Ao alterá-la',
            'cancelada' => 'Ao cancelá-la',
        ],
        'antes_llegada' => 'Dias antes da chegada',
        'llegada' => 'No dia da chegada',
        'salida' => 'No dia da saída',
        'despues_salida' => 'Dias depois da saída',
    ],
    'AccionAutomatizacion' => [
        'correo_huesped' => 'E-mail ao hóspede',
        'correo_fijo' => 'E-mail para um endereço fixo',
    ],
    'TipoEnlace' => [
        'ical' => 'Calendário iCal',
        'llegada' => 'Check-in online',
        'contrato' => 'Contrato',
        'factura' => 'Fatura',
    ],
    'EstadoFuenteIcal' => [
        'sin_leer' => 'Por ler',
        'correcta' => 'Correto',
        'error' => 'Com erro',
    ],
    'TipoChoque' => [
        'bloqueo_reserva' => 'Portal e reserva',
        'reserva_reserva' => 'Duas reservas',
    ],
    'TipoDocumentoLegal' => [
        'aviso_privacidad' => 'Aviso de privacidade',
        'contrato' => 'Condições de alojamento',
        'reglamento' => 'Regulamento interno',
        'politica_cancelacion' => 'Política de cancelamento',
    ],
    'EstadoRegistroLlegada' => [
        'pendiente' => 'Por terminar',
        'recibido' => 'Por verificar',
        'revisado' => 'Verificado',
    ],
    'VerificacionAceptacion' => [
        'casilla' => 'Caixa assinalada',
        'otp_correo' => 'Código por e-mail',
    ],
    'TipoSolicitudPrivacidad' => [
        'exportacion' => 'Exportação',
        'anonimizacion' => 'Anonimização',
    ],
    'EstadoFactura' => [
        'emitida' => 'Emitida',
        'registrada' => 'Timbrada',
        'cancelada' => 'Cancelada',
    ],
    'TipoDocumentoFiscal' => [
        'factura' => 'Fatura',
        'recibo' => 'Recibo sem valor fiscal',
    ],
    'ModoFiscal' => [
        'pruebas' => 'Testes',
        'produccion' => 'Produção',
    ],
];
