<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ClaseCargoCanal' => [
        'servicio' => 'Taxa de serviço',
        'procesamiento_pago' => 'Taxa de cobrança',
        'promocion' => 'Promoção ou visibilidade',
        'impuesto_sobre_cargos' => 'Imposto sobre os seus encargos',
        'retencion_renta' => 'Retenção de imposto sobre o rendimento',
        'retencion_iva' => 'Retenção de IVA',
        'otro' => 'Outro',
    ],
    'CalculoCargoCanal' => [
        'porcentaje' => 'Percentagem',
        'fijo_reserva' => 'Valor fixo por reserva',
        'fijo_noche' => 'Valor fixo por noite',
    ],
    'BaseCargoCanal' => [
        'tarifa_base' => 'Tarifa base (alojamento, sem extras nem impostos)',
        'sin_impuestos' => 'Total sem impostos',
        'total_final' => 'Total final (o que o hóspede paga)',
        'comisiones' => 'Comissão e encargos do canal',
    ],
    'TipoEmpresa' => ['empresa' => 'Empresa', 'agencia' => 'Agência'],
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
    'EntidadCampo' => ['huesped' => 'Um hóspede', 'reserva' => 'Uma reserva'],
    'TipoCampoPropio' => ['texto' => 'Texto', 'si_no' => 'Sim ou não'],
    'AlcanceDocumento' => ['reserva' => 'De uma reserva', 'periodo' => 'De várias reservas'],
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
        'exportacion' => 'Exportação',
        'comprobante' => 'Comprovativo de pagamento',
        'foto_publica' => 'Foto da página pública',
        'comprobante_gasto' => 'Comprovativo de despesa',
    ],
    'CanalEnvio' => [
        'correo' => 'E-mail',
        'whatsapp_enlace' => 'WhatsApp',
        'whatsapp_api' => 'WhatsApp (API da Meta)',
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
        'antes_cuota' => 'Dias antes de um pagamento do calendário',
        'cuota_vencida' => 'Dias depois de um pagamento vencido',
    ],
    'AccionAutomatizacion' => [
        'crear_tarea' => 'Criar uma tarefa para a equipe',
        'nota_bitacora' => 'Deixar uma nota importante no diário',
        'whatsapp_huesped' => 'Modelo de WhatsApp ao hóspede',
        'correo_huesped' => 'E-mail ao hóspede',
        'correo_fijo' => 'E-mail para um endereço fixo',
    ],
    'TipoEnlace' => [
        'pago' => 'Pagamento',
        'opinion' => 'Opinião',
        'ical' => 'Calendário iCal',
        'llegada' => 'Check-in online',
        'contrato' => 'Contrato',
        'grupo' => 'Grupo',
        'mi_reserva' => 'A minha reserva',
        'propietario' => 'Portal do proprietário',
        'guia' => 'Guia',
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
        'contrato_grupo' => 'Contrato de grupo',
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
    'EstadoGrupo' => [
        'tentativo' => 'Provisório',
        'confirmado' => 'Confirmado',
        'cancelado' => 'Cancelado',
    ],
    'ModoPrecioGrupo' => [
        'tarifa' => 'A tarifa de cada unidade',
        'total' => 'Um preço total, repartido por noites',
        'por_capacidad' => 'Um preço por noite segundo a capacidade',
    ],
    'EstadoComprobante' => [
        'por_confirmar' => 'Por confirmar',
        'confirmado' => 'Confirmado',
        'rechazado' => 'Rejeitado',
    ],
    'EstadoEnvioAutoridad' => [
        'pendiente' => 'Por enviar',
        'falta_datos' => 'Faltam dados',
        'enviado' => 'Enviado, sem resposta',
        'aceptado' => 'Comunicado',
        'rechazado' => 'Rejeitado',
        'por_anular' => 'Por anular',
        'anulando' => 'A anular',
        'anulado' => 'Anulado',
    ],
    'MomentoGuia' => [
        'siempre' => 'Sempre',
        'antes' => 'Antes de chegar',
        'durante' => 'Durante a estadia',
        'despues' => 'Ao sair',
    ],
    'TipoSeccionGuia' => [
        'llegada' => 'Como chegar',
        'wifi' => 'Wi-Fi',
        'reglas' => 'Regras da casa',
        'equipo' => 'Como usar o equipamento',
        'recomendaciones' => 'Recomendações',
        'contactos' => 'Contactos',
        'salida' => 'Antes de sair',
        'otro' => 'Outro',
    ],
    'TipoLugar' => [
        'tienda' => 'Tenda',
        'vehiculo' => 'Autocaravana ou caravana',
        'cabana' => 'Bungalow',
        'glamping' => 'Glamping',
        'otro' => 'Outro',
    ],
    'TipoEquipo' => [
        'tienda' => 'Tenda',
        'camper' => 'Campervan',
        'remolque' => 'Caravana',
        'casa_rodante' => 'Autocaravana',
        'otro' => 'Outro',
    ],
    'ServicioMedido' => [
        'luz' => 'Eletricidade',
        'agua' => 'Água',
        'gas' => 'Gás',
    ],
    'FaseEstadoCuenta' => [
        'borrador' => 'Rascunho',
        'enviado' => 'Enviado',
        'pagado' => 'Pago',
    ],
];
