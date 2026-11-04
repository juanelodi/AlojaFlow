<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'sincronizacion_fallida' => 'A sincronização com a instalação principal está a falhar (:codigo): :mensaje',
    'principal_sin_inicio_sesion' => 'A instalação principal :url não exige início de sessão: qualquer pessoa que conheça o endereço pode descarregar a sua cópia de dados. Ative-o no HotelDruid (Configurar → Utilizadores).',
    'ical_falla' => 'Há algum tempo que não é possível ler o calendário «:fuente». Verifique-o em Calendários iCal.',
    'choques' => 'Há datas ocupadas duas vezes. Verifique-as em Conflitos.',
    'envios_fallidos' => 'Algumas mensagens não puderam ser enviadas após várias tentativas. Verifique-as em Mensagens.',
    'pago_no_coincide' => 'Um pagamento online da reserva :reserva não coincide com o montante esperado. Verifique-o na sua conta da empresa de pagamentos.',
    'pago_duplicado' => 'Um pagamento online da reserva :reserva excede o que era devido. Verifique-o em Pagamentos e, se necessário, devolva a diferença a partir da sua conta do gateway.',
    'pago_en_cancelada' => 'Foi recebido um pagamento online da reserva :reserva, que já estava cancelada. Decida com o hóspede se a reativa ou lhe devolve o dinheiro.',
    'pago_sin_lugar' => 'Foi recebido um pagamento online da reserva :reserva, mas as suas noites já tinham sido vendidas. Resolva-o com o hóspede.',
    'motor_saturado' => 'Chegaram demasiadas reservas online numa hora e o formulário foi parado durante uma hora. Verifique em Reservas se são reais; as que não forem confirmadas são canceladas automaticamente.',
    'copia_no_borrada' => 'Não foi possível apagar a cópia na instalação principal; fica na sua pasta dati/. Verifique as permissões dessa pasta.',
];
