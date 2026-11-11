<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Supporto AlojaFlow',
    'explicacion' => 'Per impostazione predefinita, nessuno del team di AlojaFlow entra nel tuo sistema. Se chiedi aiuto, puoi permettere al supporto di entrare per 24 ore. Chi ti aiuta entra con il proprio nome, tutto ciò che fa resta nel registro di controllo e, alla scadenza, il suo utente viene disattivato da solo.',
    'permitir' => 'Permetti al supporto di AlojaFlow di entrare per 24 ore',
    'extender' => 'Estendi il permesso di 24 ore da adesso',
    'permitir_ayuda' => 'Il team di AlojaFlow potrà entrare come amministratore, senza cambiare i moduli né gli amministratori, per le prossime 24 ore. Puoi ritirare il permesso quando vuoi.',
    'permitido' => 'Fatto: il supporto può entrare fino al :hasta.',
    'retirar' => 'Ritira il permesso',
    'retirar_ayuda' => 'Chi del supporto è dentro esce subito e non potrà più entrare.',
    'retirado' => 'Permesso ritirato.',
    'sin_permiso_estado' => 'Il supporto non ha il permesso di entrare.',
    'permitido_estado' => 'Il supporto può entrare fino al :hasta.',
    'accesos' => 'Accessi del supporto (ultimi 90 giorni)',
    'sin_accesos' => 'Il supporto non è entrato.',
    'quien' => 'Chi',
    'cuando' => 'Quando',
    'hasta' => 'Fino a',
    'motivo' => 'Motivo',
    'dentro' => 'Dentro ora',
    'con_permiso' => 'Con il tuo permesso',
    'emergencia' => 'Emergenza: :motivo',
    'usuario' => 'Supporto AlojaFlow — :nombre',
    'solo_instancia' => 'L\'accesso del supporto esiste solo nel servizio ospitato di AlojaFlow.',
    'sin_permiso' => 'Il cliente non ha dato il permesso al supporto. Senza permesso si entra solo in un\'emergenza, con il motivo (--emergencia).',
    'nombre_invalido' => 'Manca il nome di chi assiste (fino a 80 caratteri).',
    'minutos_invalidos' => 'I minuti vanno da 1 a :maximo.',
    'emergencia_asunto' => 'Il supporto di AlojaFlow è entrato nel tuo sistema per un\'emergenza',
    'emergencia_cuerpo' => "Ciao,\n\n:nombre, del supporto di AlojaFlow, è entrato nel tuo sistema senza il tuo permesso per un'emergenza.\n\nMotivo: :motivo\n\nL'accesso termina il :hasta. Tutto ciò che fa resta nel registro di controllo (Sistema → Registro). Per qualsiasi dubbio, rispondi a questa email o scrivi al supporto.",
    'consola' => [
        'listo' => 'Accesso pronto per :usuario fino al :hasta. Il link funziona una volta e scade tra 5 minuti.',
    ],
];
