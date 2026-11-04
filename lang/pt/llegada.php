<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Check-in online',
    'ayuda' => 'Faça o registo antecipadamente e vá diretamente para o seu quarto. Só o alojamento vê os seus dados.',
    'ya_recibido' => 'Já recebemos os seus dados.',
    'titular' => 'Quem reserva',
    'acompanante' => 'Acompanhante :n',
    'nombre' => 'Nome',
    'apellido1' => 'Apelido',
    'apellido2' => 'Segundo apelido',
    'fecha_nacimiento' => 'Data de nascimento',
    'nacionalidad' => 'Nacionalidade',
    'documento_tipo' => 'Tipo de documento',
    'documento_numero' => 'Número do documento',
    'foto' => 'Foto do documento (opcional)',
    'correo' => 'E-mail',
    'telefono' => 'Telefone',
    'domicilio' => 'Morada',
    'domicilio_calle' => 'Rua e número',
    'domicilio_ciudad' => 'Cidade',
    'domicilio_region' => 'Estado ou região',
    'domicilio_cp' => 'Código postal',
    'domicilio_pais' => 'País',
    'hora' => 'A que horas chega, mais ou menos?',
    'notas' => 'Algo que devamos saber',
    'acepto' => 'Li e aceito: :documento',
    'confirmo' => 'Confirmo que estes dados estão corretos.',
    'privacidad_nota' => 'A foto do seu documento é guardada cifrada e apagada pouco depois da sua saída.',
    'enviar' => 'Enviar os meus dados',
    'verificar_titulo' => 'Introduza o código',
    'verificar_ayuda' => 'Enviámos um código de 6 dígitos para :correo. Confirme que é você quem aceita os documentos.',
    'codigo' => 'Código',
    'verificar' => 'Confirmar',
    'reenviar' => 'Enviar outro código',
    'codigo_incorrecto' => 'O código está incorreto ou expirou.',
    'codigo_no_enviado' => 'Não foi possível enviar o código. Tente novamente dentro de alguns minutos.',
    'codigo_reenviado' => 'Enviámos-lhe um novo código.',
    'otp_asunto' => 'O seu código para :propiedad',
    'otp_cuerpo' => "O seu código é: :codigo\n\nExpira em :minutos minutos. Se não foi você, ignore esta mensagem.\n\n:propiedad",
    'listo_titulo' => 'Pronto! Recebemos os seus dados.',
    'listo_ayuda' => 'Se precisar de alterar algo, avise o alojamento. Esperamos por si.',
    'tarjeta' => 'Ficha de registo',
    'firma' => 'Assinatura do hóspede',
    'tarjeta_nota' => 'Dados fornecidos pelo hóspede no check-in online.',
    'anonimizado' => 'Anonimizado #:id',
    'constancia' => [
        'firmante' => 'Aceite por',
        'reserva' => 'Reserva',
        'fecha' => 'Data e hora (UTC)',
        'verificacion' => 'Como foi verificado',
        'ip' => 'Endereço IP',
        'version' => 'Versão do documento',
        'huella' => 'Impressão digital do texto (SHA-256)',
    ],
];
