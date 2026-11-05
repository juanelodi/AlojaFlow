<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ModoPropiedad' => [
        'nativa' => 'Indipendente',
        'subordinada' => 'Subordinata',
    ],
    'MetodoPago' => [
        'efectivo' => 'Contanti',
        'tarjeta' => 'Carta',
        'transferencia' => 'Bonifico',
        'otro' => 'Altro',
    ],
    'ModoTarifa' => [
        'por_noche' => 'Per notte (con persona in più)',
        'por_persona' => 'Per persona',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Per soggiorno',
        'por_noche' => 'Per notte',
        'por_persona' => 'Per persona',
        'por_persona_noche' => 'Per persona e notte',
        'porcentaje_alojamiento' => 'Percentuale dell’alloggio',
    ],
    'TipoMovimiento' => [
        'gasto' => 'Spesa',
        'ingreso' => 'Entrata',
    ],
    'TipoCupon' => [
        'porcentaje' => 'Percentuale',
        'importe' => 'Importo fisso',
    ],
    'PaquetePais' => [
        'mexico' => 'Messico',
        'espana' => 'Spagna',
        'estados_unidos' => 'Stati Uniti',
        'generico' => 'Generico',
    ],
    'EstadoPrincipal' => [
        'activa' => 'Attiva',
        'pausada' => 'In pausa',
    ],
    'MetodoPeticion' => [
        'post' => 'POST (consigliato)',
        'get' => 'GET (modalità compatibile)',
    ],
    'EstadoEjecucion' => [
        'en_curso' => 'In corso',
        'correcta' => 'Riuscita',
        'fallida' => 'Non riuscita',
        'omitida' => 'Saltata',
    ],
    'OrigenEjecucion' => [
        'programada' => 'Pianificata',
        'manual' => 'Manuale',
    ],
    'TipoReserva' => [
        'reserva' => 'Prenotazione',
        'bloqueo' => 'Blocco',
    ],
    'EstadoReserva' => [
        'sin_confirmar' => 'Da confermare',
        'confirmada' => 'Confermata',
        'cancelada' => 'Annullata',
    ],
    'TipoEventoReserva' => [
        'creada' => 'Nuova',
        'modificada' => 'Modificata',
        'cancelada' => 'Annullata',
        'eliminada' => 'Eliminata nell’installazione principale',
        'reactivada' => 'Riattivata',
    ],
    'NivelAviso' => [
        'informacion' => 'Informazione',
        'advertencia' => 'Avviso',
        'error' => 'Errore',
    ],
    'TipoContacto' => [
        'telefono' => 'Telefono',
        'correo' => 'E-mail',
        'fax' => 'Fax',
    ],
    'TipoPrincipal' => ['hoteldruid' => 'HotelDruid o AlojaFlow 1.2.x', 'alojaflow' => 'Un’altra installazione di AlojaFlow'],
    'OrigenFila' => [
        'hoteldruid' => 'HotelDruid',
        'alojaflow' => 'AlojaFlow', 'otra_instalacion' => 'Altra installazione'],
    'EstadoActualizacion' => [
        'preparada' => 'Preparata',
        'aplicando' => 'In applicazione',
        'archivos_aplicados' => 'Non terminata',
        'completada' => 'Completata',
        'fallida' => 'Non riuscita',
        'revertida' => 'Annullata',
    ],
    'EstadoLimpieza' => [
        'limpia' => 'Pulita',
        'sucia' => 'Sporca',
        'inspeccion' => 'Da controllare',
    ],
    'TipoTarea' => [
        'limpieza_salida' => 'Pulizia di partenza',
        'limpieza_estancia' => 'Pulizia durante il soggiorno',
        'limpieza_profunda' => 'Pulizia a fondo',
        'inspeccion' => 'Ispezione',
        'mantenimiento' => 'Manutenzione',
        'otra' => 'Altra',
    ],
    'EstadoTarea' => [
        'pendiente' => 'Da fare',
        'en_curso' => 'In corso',
        'hecha' => 'Fatta',
        'cancelada' => 'Annullata',
    ],
    'FrecuenciaLimpieza' => [
        'diaria' => 'Tutti i giorni',
        'semanal' => 'Alcuni giorni della settimana',
        'fechas' => 'Giorni scelti',
    ],
    'ClaseArchivo' => [
        'documento_identidad' => 'Documento d’identità',
        'firma' => 'Firma',
        'copia_aceptacion' => 'Copia firmata',
        'foto_tarea' => 'Foto',
        'plano' => 'Planimetria',
        'factura_xml' => 'Fattura (XML)',
        'factura_pdf' => 'Fattura (PDF)',
        'exportacion' => 'Esportazione',
    ],
    'CanalEnvio' => [
        'correo' => 'E-mail',
        'whatsapp_enlace' => 'WhatsApp',
        'webhook' => 'Notifica a un altro sistema',
    ],
    'EstadoEnvio' => [
        'pendiente' => 'Da inviare',
        'enviado' => 'Inviato',
        'fallido' => 'Non riuscito',
        'omitido' => 'Non inviato',
        'manual' => 'Pronto da copiare',
    ],
    'DisparadorAutomatizacion' => [
        'reserva' => [
            'creada' => 'Alla creazione della prenotazione',
            'confirmada' => 'Alla conferma',
            'modificada' => 'Alla modifica',
            'cancelada' => 'All’annullamento',
        ],
        'antes_llegada' => 'Giorni prima dell’arrivo',
        'llegada' => 'Il giorno dell’arrivo',
        'salida' => 'Il giorno della partenza',
        'despues_salida' => 'Giorni dopo la partenza',
    ],
    'AccionAutomatizacion' => [
        'correo_huesped' => 'E-mail all’ospite',
        'correo_fijo' => 'E-mail a un indirizzo fisso',
    ],
    'TipoEnlace' => [
        'ical' => 'Calendario iCal',
        'llegada' => 'Check-in online',
        'contrato' => 'Contratto',
        'factura' => 'Fattura',
    ],
    'EstadoFuenteIcal' => [
        'sin_leer' => 'Non letto',
        'correcta' => 'Corretto',
        'error' => 'Con errori',
    ],
    'TipoChoque' => [
        'bloqueo_reserva' => 'Portale e prenotazione',
        'reserva_reserva' => 'Due prenotazioni',
    ],
    'TipoDocumentoLegal' => [
        'aviso_privacidad' => 'Informativa sulla privacy',
        'contrato' => 'Condizioni di soggiorno',
        'reglamento' => 'Regolamento interno',
        'politica_cancelacion' => 'Politica di cancellazione',
    ],
    'EstadoRegistroLlegada' => [
        'pendiente' => 'Non terminato',
        'recibido' => 'Da controllare',
        'revisado' => 'Controllato',
    ],
    'VerificacionAceptacion' => [
        'casilla' => 'Casella selezionata',
        'otp_correo' => 'Codice via e-mail',
    ],
    'TipoSolicitudPrivacidad' => [
        'exportacion' => 'Esportazione',
        'anonimizacion' => 'Anonimizzazione',
    ],
    'EstadoFactura' => [
        'emitida' => 'Emessa',
        'registrada' => 'Timbrata',
        'cancelada' => 'Annullata',
    ],
    'TipoDocumentoFiscal' => [
        'factura' => 'Fattura',
        'recibo' => 'Ricevuta senza valore fiscale',
    ],
    'ModoFiscal' => [
        'pruebas' => 'Test',
        'produccion' => 'Produzione',
    ],
];
