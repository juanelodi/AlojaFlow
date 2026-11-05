<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'tarifa' => [
        'seccion' => 'Longs séjours',
        'seccion_ayuda' => 'Prix à la semaine à partir de 7 nuits et au mois à partir de 28. Un mois complet coûte ce prix qu’il ait 28 ou 31 nuits, et les nuits en plus sont facturées au trentième. Laissez vide pour facturer à la nuit.',
        'semana' => 'Prix à la semaine',
        'mes' => 'Prix au mois',
        'temporada_ayuda' => 'Une saison sans ces prix applique son prix à la nuit, même pour un long séjour ; vous pouvez lui donner les siens.',
    ],
    'impuesto' => [
        'exento' => 'Ne pas facturer à partir de (nuits)',
        'exento_ayuda' => 'Un séjour qui atteint ce nombre de nuits ne le paie pas. Certains lieux exonèrent ainsi les séjours de 30 nuits ou plus ; vérifiez auprès de votre comptable. Vide : toujours facturé.',
    ],
    'motor' => [
        'semana' => 'Par semaine : :precio',
        'mes' => 'Par mois : :precio',
        'por_persona' => 'par personne',
        'aplicado' => [
            'semana' => 'Avec le prix à la semaine',
            'mes' => 'Avec le prix au mois',
        ],
    ],
    'cuotas' => [
        'titulo' => 'Calendrier de paiements',
        'vacio' => 'Pas de calendrier de paiements',
        'vacio_ayuda' => 'Pour un long séjour : répartit le total en paiements mensuels ou hebdomadaires, avec des rappels et une facture par paiement.',
        'periodo' => 'du :desde au :hasta',
        'vence' => 'Échéance',
        'factura' => 'Facture',
        'generar' => 'Générer le calendrier',
        'generar_ayuda' => 'Répartit le total de la réservation en paiements égaux, et le dernier couvre ce qui ne remplit pas une période. Remplace les paiements existants, sauf ceux déjà facturés. Les montants payés s’appliquent d’abord aux premières échéances.',
        'cada_cuanto' => 'Fréquence des paiements',
        'cada_mes' => 'Chaque mois',
        'cada_semana' => 'Chaque semaine',
        'primer_vencimiento' => 'Première échéance',
        'generadas' => '{0} Rien à répartir : tout est déjà facturé.|{1} Calendrier prêt, avec :count paiement.|[2,*] Calendrier prêt, avec :count paiements.',
        'agregar' => 'Ajouter un paiement',
        'editar' => 'Modifier',
        'quitar' => 'Retirer',
        'concepto' => 'Libellé',
        'concepto_ayuda' => 'Ce que voit le client et ce qui figure sur la facture, par exemple « Dépôt ».',
        'no_cuadra' => 'Attention : le calendrier totalise :suma et le total de la réservation est de :total. Ajustez les paiements ou générez à nouveau le calendrier.',
        'saldo' => 'Solde du séjour',
        'concepto_factura' => 'Hébergement à :unidad, :periodo (réservation :numero)',
        'error_factura_total' => 'Avec ce paiement, le montant facturé dépasserait le total de la réservation. Vérifiez le calendrier.',
        'recordatorio' => 'Bonjour :nombre : ici :propiedad, pour vous rappeler votre paiement de :importe (:periodo), dû le :fecha. Merci !',
        'estados' => [
            'pagada' => 'Payé',
            'atrasada' => 'En retard',
            'parcial' => 'Partiel',
            'pendiente' => 'En attente',
        ],
    ],
    'publico' => [
        'titulo' => 'Vos paiements',
        'periodo' => 'Période',
        'vence' => 'Échéance le :fecha',
    ],
    'pagina' => [
        'titulo' => 'Mensualités',
        'ayuda' => 'Ce que doivent encore les longs séjours avec calendrier de paiements. AlojaFlow vous le rappelle ; l’encaissement, c’est vous qui décidez.',
        'recordatorios' => 'Rappels par e-mail',
        'recordatorios_ayuda' => 'Envoie au client un e-mail 3 jours avant chaque paiement et un autre le lendemain s’il n’est pas arrivé. Vous pouvez changer les jours et le texte dans Automatisations et Modèles.',
        'recordatorios_prender' => 'Activer les rappels',
        'recordatorios_ok' => 'Rappels activés.',
        'vencidas' => 'En retard',
        'proximas' => 'Dans les :dias prochains jours',
        'sin_vencidas' => 'Rien en retard.',
        'sin_proximas' => 'Rien à échéance.',
        'falta' => 'Reste dû',
    ],
    'mensajes' => [
        'cuota_por_vencer' => [
            'nombre' => 'Rappel de paiement',
            'asunto' => 'Votre prochain paiement à {propiedad}',
            'cuerpo' => "Bonjour {nombre},\n\nNous vous rappelons votre paiement de {cuota_importe} ({cuota_periodo}), dû le {cuota_vence}.\n\n{enlace_mi_reserva}\n\nMerci.\n{propiedad}",
        ],
        'cuota_vencida' => [
            'nombre' => 'Paiement en retard',
            'asunto' => 'Votre paiement à {propiedad} est toujours en attente',
            'cuerpo' => "Bonjour {nombre},\n\nVotre paiement de {cuota_importe} ({cuota_periodo}) était dû le {cuota_vence} et nous ne l’avons pas encore reçu. Si vous l’avez déjà effectué, envoyez-nous votre justificatif et ne tenez pas compte de ce message.\n\n{enlace_mi_reserva}\n\nMerci.\n{propiedad}",
        ],
    ],
];
