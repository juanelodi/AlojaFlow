<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'nombres' => [
        'operacion' => [
            'ver' => 'Betriebsansichten sehen (Heute, Kalender, Reinigung)',
        ],
        'datos' => [
            'ver' => 'Buchungen, Gäste und andere Daten sehen',
        ],
        'indicadores' => [
            'ver' => 'Kennzahlen und Berichte sehen',
        ],
        'sincronizacion' => [
            'gestionar' => 'Synchronisierung mit der Hauptinstallation verwalten',
        ],
        'reservas' => [
            'gestionar' => 'Buchungen, Gäste und Zahlungen anlegen und ändern',
        ],
        'catalogo' => [
            'gestionar' => 'Einheiten und Tarife anlegen und ändern',
        ],
        'limpieza' => [
            'gestionar' => 'Reinigung und Wartung verwalten',
        ],
        'mensajes' => [
            'gestionar' => 'Nachrichten an Gäste senden',
        ],
        'ajustes' => [
            'gestionar' => 'Vorlagen, Automatisierungen, Kalender und Buchungsmaschine',
        ],
        'huespedes' => [
            'gestionar' => 'Gästeanmeldung, Dokumente und Datenschutz',
        ],
        'cuentas' => [
            'gestionar' => 'Eigentümerabrechnungen und Zahlungspläne',
        ],
    ],
    'roles' => [
        'modelo' => 'Rolle',
        'modelos' => 'Rollen',
        'nombre' => 'Name',
        'permisos' => 'Berechtigungen',
        'en_uso' => 'Benutzer mit dieser Rolle',
        'ayuda' => 'Was Sie hier ändern, gilt nach dem Speichern für alle Personen mit dieser Rolle.',
    ],
    'usuario' => [
        'ajustes' => 'Einstellungen dieser Person',
        'ajustes_ayuda' => 'Optional. Nur für jemanden, der etwas anderes als seine Rolle braucht.',
        'agregados' => 'Zusätzlich zur Rolle darf',
        'quitados' => 'Auch wenn die Rolle es erlaubt, darf nicht',
        'solo_propias' => 'Nur eigene Buchungen und Gäste',
        'solo_propias_ayuda' => 'Heute und die Listen zeigen nur selbst Angelegtes. Der Kalender zeigt, was belegt ist, ohne Namen.',
        'sin_iniciadas' => 'Bereits begonnene Buchungen nicht ändern',
        'sin_iniciadas_ayuda' => 'Die bereits angereist sind oder heute anreisen. An- und Abreise sowie Zahlungen erfassen bleibt erlaubt.',
        'horas_modificar' => 'Stunden zum Ändern einer Buchung',
        'horas_cancelar' => 'Stunden zum Stornieren einer Buchung',
        'horas_ayuda' => 'Ab der Erstellung gezählt. Leer: keine Grenze.',
    ],
    'limites' => [
        'no_modificar' => 'Ihre Berechtigungen erlauben es nicht, diese Buchung zu ändern.',
        'ocupada' => 'Belegt',
    ],
    'sesiones' => [
        'titulo' => 'Sitzungen und Geräte',
        'abiertas' => 'Offene Sitzungen',
        'sin_sesiones' => 'Keine Sitzungen vorhanden.',
        'recordados' => 'Gespeicherte Geräte',
        'recordados_ayuda' => 'Wo bei der Anmeldung „Angemeldet bleiben“ gewählt wurde. Sie können sich bis zum angegebenen Datum ohne Passwort wieder anmelden.',
        'sin_dispositivos' => 'Keine gespeicherten Geräte.',
        'usuario' => 'Benutzer',
        'dispositivo' => 'Gerät',
        'ultimo_uso' => 'Zuletzt verwendet',
        'caduca' => 'Läuft ab',
        'acciones' => 'Aktionen',
        'esta' => 'Diese Sitzung',
        'cerrar' => 'Schließen',
        'olvidar' => 'Vergessen',
        'cerrar_demas' => 'Meine anderen Sitzungen und Geräte schließen',
        'cerradas' => 'Ihre anderen Sitzungen und Geräte wurden geschlossen.',
        'dias' => 'Tage, die ein Gerät gespeichert bleibt',
        'dias_ayuda' => 'Bei 0 wird keines gespeichert, und vorhandene werden vergessen.',
        'guardar' => 'Speichern',
        'guardado' => 'Gespeichert.',
    ],
    'dos_pasos' => [
        'quitar' => 'Zwei-Schritt-Verifizierung entfernen',
        'quitar_texto' => 'Für jemanden, der Telefon und Wiederherstellungscodes verloren hat. Die Anmeldung geht dann nur mit Passwort, bis sie im Profil wieder aktiviert wird.',
        'quitada' => 'Die Zwei-Schritt-Verifizierung von :usuario wurde entfernt.',
    ],
    'auditoria' => [
        'exportar' => 'CSV exportieren',
        'plazo' => 'Aufbewahrungsdauer',
        'dias' => 'Tage',
        'dias_ayuda' => 'Das Älteste wird automatisch gelöscht. Mindestens 30 Tage.',
        'plazo_guardado' => 'Gespeichert.',
    ],
    'crear_contrasena' => [
        'titulo' => 'Erstellen Sie Ihr Passwort',
        'ayuda' => 'Dies ist Ihre erste Anmeldung. Wählen Sie das Passwort, mit dem Sie sich ab jetzt mit :correo anmelden.',
        'nueva' => 'Neues Passwort',
        'confirmar' => 'Erneut eingeben',
        'guardar' => 'Passwort speichern',
        'lista' => 'Fertig: Sie können sich jetzt mit Ihrer E-Mail-Adresse und diesem Passwort anmelden.',
    ],
];
