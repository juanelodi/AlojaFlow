<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'AlojaFlow-Support',
    'explicacion' => 'Standardmäßig betritt niemand aus dem AlojaFlow-Team Ihr System. Wenn Sie Hilfe anfordern, können Sie dem Support den Zugang für 24 Stunden erlauben. Wer Ihnen hilft, meldet sich unter eigenem Namen an, alles wird im Prüfprotokoll festgehalten, und nach Ablauf der Frist wird der Benutzer automatisch deaktiviert.',
    'permitir' => 'Dem AlojaFlow-Support 24 Stunden lang Zugang erlauben',
    'extender' => 'Erlaubnis ab jetzt um 24 Stunden verlängern',
    'permitir_ayuda' => 'Das AlojaFlow-Team kann sich in den nächsten 24 Stunden als Administrator anmelden, ohne Module oder Administratoren zu ändern. Sie können die Erlaubnis jederzeit zurückziehen.',
    'permitido' => 'Erledigt: Der Support kann sich bis :hasta anmelden.',
    'retirar' => 'Erlaubnis zurückziehen',
    'retirar_ayuda' => 'Wer vom Support angemeldet ist, wird sofort abgemeldet und kann sich nicht mehr anmelden.',
    'retirado' => 'Erlaubnis zurückgezogen.',
    'sin_permiso_estado' => 'Der Support hat keine Erlaubnis zur Anmeldung.',
    'permitido_estado' => 'Der Support kann sich bis :hasta anmelden.',
    'accesos' => 'Anmeldungen des Supports (letzte 90 Tage)',
    'sin_accesos' => 'Der Support hat sich nicht angemeldet.',
    'quien' => 'Wer',
    'cuando' => 'Wann',
    'hasta' => 'Bis',
    'motivo' => 'Grund',
    'dentro' => 'Jetzt angemeldet',
    'con_permiso' => 'Mit Ihrer Erlaubnis',
    'emergencia' => 'Notfall: :motivo',
    'usuario' => 'AlojaFlow-Support — :nombre',
    'solo_instancia' => 'Der Support-Zugang gibt es nur im gehosteten AlojaFlow-Dienst.',
    'sin_permiso' => 'Der Kunde hat dem Support keine Erlaubnis gegeben. Ohne Erlaubnis ist die Anmeldung nur im Notfall mit Angabe des Grundes möglich (--emergencia).',
    'nombre_invalido' => 'Der Name der helfenden Person fehlt (bis zu 80 Zeichen).',
    'minutos_invalidos' => 'Die Minuten liegen zwischen 1 und :maximo.',
    'emergencia_asunto' => 'Der AlojaFlow-Support hat sich wegen eines Notfalls in Ihrem System angemeldet',
    'emergencia_cuerpo' => "Hallo,\n\n:nombre vom AlojaFlow-Support hat sich wegen eines Notfalls ohne Ihre Erlaubnis in Ihrem System angemeldet.\n\nGrund: :motivo\n\nDer Zugang endet am :hasta. Alles, was dabei geschieht, wird im Prüfprotokoll festgehalten (System → Prüfprotokoll). Bei Fragen antworten Sie auf diese E-Mail oder schreiben Sie dem Support.",
    'consola' => [
        'listo' => 'Zugang für :usuario bis :hasta bereit. Der Link funktioniert einmal und läuft in 5 Minuten ab.',
    ],
];
