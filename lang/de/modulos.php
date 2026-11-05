<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Module',
    'explicacion' => 'Schalten Sie nur ein, was Sie nutzen: Der Rest verschwindet aus dem Menü und stört nicht. Das Ausschalten eines Moduls löscht seine Daten nicht, und wenn Sie es wieder einschalten, ist noch alles da. Solange es ausgeschaltet ist, öffnen sich seine Seiten nicht und seine Aufgaben ruhen.',
    'siempre' => 'Immer vorhanden: Heute, Kalender, Buchungen, Gäste, Benutzer, Sicherungen, Aktualisierungen, Datenschutz und der Link zum Quellcode.',
    'guardar' => 'Speichern',
    'guardado' => 'Module gespeichert.',
    'repetir' => 'Fragen erneut beantworten',
    'nuevo' => 'Neu',
    'requiere' => 'Benötigt: :modulos',
    'omitido_al_apagar' => 'Nicht gesendet, weil das Modul ausgeschaltet wurde.',
    'aviso_nuevos' => 'Mit der Aktualisierung sind neue Module gekommen: :modulos. Sie sind zunächst ausgeschaltet.',
    'ver' => 'Module ansehen',

    'grupos' => [
        'propiedades' => 'Unterkünfte',
        'reservas' => 'Buchungen',
        'operacion' => 'Betrieb',
        'dinero' => 'Geld',
        'sistema' => 'System',
    ],

    'lista' => [
        'varias_propiedades' => ['nombre' => 'Mehrere Unterkünfte', 'descripcion' => 'Mehr als eine Unterkunft von dieser Installation aus verwalten.'],
        'subordinacion' => ['nombre' => 'Unterordnung', 'descripcion' => 'Die Buchungen aus einem anderen System kopieren, das das Hauptsystem bleibt: HotelDruid, AlojaFlow 1.2.x oder eine andere AlojaFlow-Installation. Dorthin wird nie geschrieben. Damit können Sie auch Ihre Unterkünfte mit einer anderen Installation teilen.'],
        'motor' => ['nombre' => 'Buchungsmaschine', 'descripcion' => 'Eine Seite, auf der Gäste Termine suchen und selbst buchen.'],
        'pagos' => ['nombre' => 'Online-Zahlungen', 'descripcion' => 'Mit Stripe oder Mercado Pago über die Buchungsmaschine kassieren.'],
        'ical' => ['nombre' => 'Externe Kalender', 'descripcion' => 'Termine mit den Kalendern von Airbnb, Booking und Vrbo sperren und ihnen Ihren geben.'],
        'cupones' => ['nombre' => 'Gutscheine', 'descripcion' => 'Rabattcodes für Buchungen.'],
        'grupos' => ['nombre' => 'Gruppen', 'descripcion' => 'Mehrere Einheiten zusammen buchen und wie eine behandeln.'],
        'limpieza' => ['nombre' => 'Reinigung und Wartung', 'descripcion' => 'Zustand jeder Einheit, Aufgaben für das Personal und Reinigungsregeln.'],
        'aviso_equipo' => ['nombre' => 'Meldung zu An- und Abreisen', 'descripcion' => 'Ein Bericht für das Team: wer abreist, wer anreist und was gereinigt wird, bereit zum Senden per WhatsApp.'],
        'plano' => ['nombre' => 'Lageplan der Unterkunft', 'descripcion' => 'Die Einheiten auf einer Zeichnung Ihrer Unterkunft sehen.'],
        'llegada' => ['nombre' => 'Online-Anreise', 'descripcion' => 'Der Gast trägt seine Daten ein und akzeptiert die Dokumente vor der Ankunft.'],
        'mensajes' => ['nombre' => 'Nachrichten an Gäste', 'descripcion' => 'Vorlagen, Versand per E-Mail und WhatsApp sowie automatische Nachrichten.'],
        'bitacora' => ['nombre' => 'Schichtbuch', 'descripcion' => 'Was eine Schicht der nächsten hinterlässt. Wichtiges erscheint unter Heute.'],
        'empresas' => ['nombre' => 'Unternehmen und Agenturen', 'descripcion' => 'Wer für seine Gäste bucht oder zahlt, mit Konditionen und Kontoauszug.'],
        'caja' => ['nombre' => 'Kasse und Tagesabschluss', 'descripcion' => 'Kassen, Geldbewegungen und der tägliche Abschluss.'],
        'facturacion' => ['nombre' => 'Rechnungen und Quittungen', 'descripcion' => 'Steuerdaten, Rechnungen, Quittungen und Exporte für die Buchhaltung.'],
        'informes' => ['nombre' => 'Berichte und Kennzahlen', 'descripcion' => 'Auslastung, Einnahmen und weitere Berichte.'],
        'integraciones' => ['nombre' => 'Meldungen an andere Systeme', 'descripcion' => 'Was mit den Buchungen passiert, an andere Programme senden (Webhooks). Wenn Sie nicht wissen, was das ist, brauchen Sie es nicht.'],
    ],

    'en_uso' => [
        'varias_propiedades' => 'Sie haben bereits :n weitere Unterkunft. Sie funktioniert weiter, aber Sie können keine weiteren anlegen.|Sie haben bereits :n weitere Unterkünfte. Sie funktionieren weiter, aber Sie können keine weiteren anlegen.',
        'subordinacion' => ':n Unterkunft hängt davon ab: Sie wird nicht mehr aktualisiert.|:n Unterkünfte hängen davon ab: Sie werden nicht mehr aktualisiert.',
        'ical' => ':n Kalender wird nicht mehr aktualisiert, und die Portale können Ihre nicht mehr lesen.|:n Kalender werden nicht mehr aktualisiert, und die Portale können Ihre nicht mehr lesen.',
        'grupos' => 'Sie haben bereits :n Gruppe. Ihre Buchungen bleiben, aber die Gruppenansicht öffnet sich nicht.|Sie haben bereits :n Gruppen. Ihre Buchungen bleiben, aber die Gruppenansicht öffnet sich nicht.',
        'integraciones' => ':n Meldung wird nicht mehr gesendet.|:n Meldungen werden nicht mehr gesendet.',
    ],

    'preguntas' => [
        'titulo' => 'Passen wir es für Sie an',
        'explicacion' => 'Fünf kurze Fragen, um nur einzuschalten, was Ihnen nützt. Sie können es später unter System → Module ändern.',
        'paso' => 'Frage :paso von :total',
        'atras' => 'Zurück',
        'siguiente' => 'Weiter',
        'terminar' => 'Fertig',
        'saltar' => 'Überspringen und alles eingeschaltet lassen',
        'listo' => 'Fertig. Sie können es jederzeit unter System → Module ändern.',
        'propiedades' => [
            'titulo' => 'Wie viele Unterkünfte werden Sie hier verwalten?',
            'una' => 'Eine',
            'una_ayuda' => 'Ein Hotel, einige Hütten, ein Haus oder mehrere Zimmer an einem Ort.',
            'varias' => 'Mehrere',
            'varias_ayuda' => 'Verschiedene Unterkünfte, jede mit ihren Einheiten und Daten.',
        ],
        'otro_sistema' => [
            'titulo' => 'Nutzen Sie bereits ein anderes System, das das Hauptsystem bleibt?',
            'no' => 'Nein, AlojaFlow wird mein System',
            'no_ayuda' => 'Hier erfassen Sie Ihre Buchungen, Tarife und Gäste.',
            'principal' => 'Ja, HotelDruid oder AlojaFlow 1.2.x',
            'principal_ayuda' => 'AlojaFlow kopiert dessen Buchungen und ergänzt seine eigenen Funktionen, ohne je dorthin zu schreiben.',
            'alojaflow' => 'Ja, eine andere AlojaFlow-Installation',
            'alojaflow_ayuda' => 'Diese kopiert eine Unterkunft von der anderen mit einem Verbindungscode, ohne je dorthin zu schreiben.',
        ],
        'reservas' => [
            'titulo' => 'Was brauchen Sie noch für Ihre Buchungen?',
            'ayuda' => 'Buchungen per Telefon, WhatsApp oder persönlich lassen sich immer erfassen.',
        ],
        'operacion' => [
            'titulo' => 'Was möchten Sie hier organisieren?',
            'ayuda' => 'Markieren Sie, was Sie täglich tun oder nicht mehr auf Papier führen möchten.',
        ],
        'dinero' => [
            'titulo' => 'Wie führen Sie Geld und Unterlagen?',
            'ayuda' => 'Die Zahlungen jeder Buchung werden immer erfasst. Das hier kommt hinzu.',
        ],
        'resumen' => [
            'titulo' => 'So sieht es aus',
            'ayuda' => 'Wenn Sie etwas nicht überzeugt, gehen Sie zurück. Sie können es auch später ändern.',
            'prendidos' => 'Eingeschaltet',
            'apagados' => 'Ausgeschaltet',
            'base' => 'Heute, Kalender, Buchungen, Gäste und alles andere Wesentliche',
        ],
    ],

    'pasos' => [
        'titulo' => 'Erste Schritte',
        'avance' => ':hechos von :total erledigt',
        'ocultar' => 'Nicht mehr anzeigen',
        'ir' => 'Öffnen',
        'copiar' => 'Kopieren',
        'copiado' => 'Kopiert',
        'propiedad' => ['titulo' => 'Legen Sie Ihre Unterkunft an', 'ayuda' => 'Name, Land und Währung Ihrer Unterkunft.'],
        'unidades' => ['titulo' => 'Fügen Sie Ihre Einheiten hinzu', 'ayuda' => 'Die Zimmer, Hütten oder Wohnungen, die Sie vermieten.'],
        'tarifa' => ['titulo' => 'Legen Sie einen Tarif fest', 'ayuda' => 'Was Sie pro Nacht berechnen, mit Saisons, falls vorhanden.'],
        'motor' => ['titulo' => 'Öffnen Sie die Buchungsmaschine', 'ayuda' => 'Damit Gäste selbst über Ihre Website buchen.'],
        'correo' => ['titulo' => 'Richten Sie die E-Mail ein', 'ayuda' => 'Ohne E-Mail gehen keine Nachrichten raus und kein Passwort lässt sich wiederherstellen.'],
        'dos_pasos' => ['titulo' => 'Schalten Sie die Zwei-Schritt-Prüfung ein', 'ayuda' => 'Ihr Konto hat Zugriff auf alles: Es lohnt sich am meisten, es zu schützen.'],
        'cron' => ['titulo' => 'Erstellen Sie die geplante Aufgabe', 'ayuda' => 'Sie sorgt dafür, dass Synchronisierungen, Nachrichten und Sicherungen pünktlich laufen. Erstellen Sie im Panel Ihres Hostings eine geplante Aufgabe (Cron) mit dem kürzesten erlaubten Intervall und diesem Befehl. Ohne sie laufen diese Aufgaben, wenn jemand das System benutzt.'],
        'copia' => ['titulo' => 'Erstellen Sie Ihre erste Sicherung', 'ayuda' => 'Und bewahren Sie sie außerhalb des Hostings auf.'],
        'equipo' => ['titulo' => 'Laden Sie Ihr Team ein', 'ayuda' => 'Jede Person mit eigenem Benutzer und nur dem, was sie sehen muss. Wenn Sie allein arbeiten, überspringen Sie diesen Schritt.'],
    ],
];
