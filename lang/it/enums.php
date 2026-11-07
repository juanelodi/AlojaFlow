<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'ClaseCargoCanal' => [
        'servicio' => 'Costo di servizio',
        'procesamiento_pago' => 'Costo di incasso',
        'promocion' => 'Promozione o visibilità',
        'impuesto_sobre_cargos' => 'Imposta sui suoi costi',
        'retencion_renta' => 'Ritenuta sul reddito',
        'retencion_iva' => 'Ritenuta IVA',
        'otro' => 'Altro',
    ],
    'CalculoCargoCanal' => [
        'porcentaje' => 'Percentuale',
        'fijo_reserva' => 'Importo fisso per prenotazione',
        'fijo_noche' => 'Importo fisso per notte',
    ],
    'BaseCargoCanal' => [
        'tarifa_base' => 'Tariffa base (alloggio, senza extra né imposte)',
        'sin_impuestos' => 'Totale senza imposte',
        'total_final' => 'Totale finale (ciò che paga l’ospite)',
        'comisiones' => 'Commissione e costi del canale',
    ],
    'TipoEmpresa' => ['empresa' => 'Azienda', 'agencia' => 'Agenzia'],
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
    'EntidadCampo' => ['huesped' => 'Un ospite', 'reserva' => 'Una prenotazione'],
    'TipoCampoPropio' => ['texto' => 'Testo', 'si_no' => 'Sì o no'],
    'AlcanceDocumento' => ['reserva' => 'Di una prenotazione', 'periodo' => 'Di più prenotazioni'],
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
        'exportacion' => 'Esportazione',
        'comprobante' => 'Ricevuta di pagamento',
        'foto_publica' => 'Foto della pagina pubblica',
        'comprobante_gasto' => 'Ricevuta di spesa',
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
        'antes_cuota' => 'Giorni prima di un pagamento del calendario',
        'cuota_vencida' => 'Giorni dopo un pagamento scaduto',
    ],
    'AccionAutomatizacion' => [
        'crear_tarea' => 'Crea un’attività per il team',
        'nota_bitacora' => 'Lascia una nota importante nel registro',
        'correo_huesped' => 'E-mail all’ospite',
        'correo_fijo' => 'E-mail a un indirizzo fisso',
    ],
    'TipoEnlace' => [
        'pago' => 'Pagamento',
        'opinion' => 'Opinione',
        'ical' => 'Calendario iCal',
        'llegada' => 'Check-in online',
        'contrato' => 'Contratto',
        'grupo' => 'Gruppo',
        'mi_reserva' => 'La mia prenotazione',
        'propietario' => 'Portale del proprietario',
        'guia' => 'Guida',
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
        'contrato_grupo' => 'Contratto di gruppo',
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
    'EstadoGrupo' => [
        'tentativo' => 'Provvisorio',
        'confirmado' => 'Confermato',
        'cancelado' => 'Annullato',
    ],
    'ModoPrecioGrupo' => [
        'tarifa' => 'La tariffa di ogni unità',
        'total' => 'Un prezzo totale, diviso per notti',
        'por_capacidad' => 'Un prezzo per notte in base ai posti',
    ],
    'EstadoComprobante' => [
        'por_confirmar' => 'Da confermare',
        'confirmado' => 'Confermato',
        'rechazado' => 'Rifiutato',
    ],
    'EstadoEnvioAutoridad' => [
        'pendiente' => 'Da inviare',
        'falta_datos' => 'Dati mancanti',
        'enviado' => 'Inviato, senza risposta',
        'aceptado' => 'Comunicato',
        'rechazado' => 'Rifiutato',
        'por_anular' => 'Da annullare',
        'anulando' => 'Annullamento',
        'anulado' => 'Annullato',
    ],
    'MomentoGuia' => [
        'siempre' => 'Sempre',
        'antes' => 'Prima dell’arrivo',
        'durante' => 'Durante il soggiorno',
        'despues' => 'Alla partenza',
    ],
    'TipoSeccionGuia' => [
        'llegada' => 'Come arrivare',
        'wifi' => 'Wi-Fi',
        'reglas' => 'Regole della casa',
        'equipo' => 'Come usare le attrezzature',
        'recomendaciones' => 'Consigli',
        'contactos' => 'Contatti',
        'salida' => 'Prima di partire',
        'otro' => 'Altro',
    ],
    'TipoLugar' => [
        'tienda' => 'Tenda',
        'vehiculo' => 'Camper o roulotte',
        'cabana' => 'Bungalow',
        'glamping' => 'Glamping',
        'otro' => 'Altro',
    ],
    'TipoEquipo' => [
        'tienda' => 'Tenda',
        'camper' => 'Camper o furgone',
        'remolque' => 'Roulotte',
        'casa_rodante' => 'Autocaravan',
        'otro' => 'Altro',
    ],
    'ServicioMedido' => [
        'luz' => 'Elettricità',
        'agua' => 'Acqua',
        'gas' => 'Gas',
    ],
    'FaseEstadoCuenta' => [
        'borrador' => 'Bozza',
        'enviado' => 'Inviato',
        'pagado' => 'Pagato',
    ],
];
