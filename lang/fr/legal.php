<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'documentos' => [
        'ine' => 'INE (carte d’électeur mexicaine)',
        'pasaporte' => 'Passeport',
        'licencia' => 'Permis de conduire',
        'fmm' => 'Formulaire migratoire (FMM)',
        'dni' => 'DNI',
        'nie' => 'NIE',
        'identificacion' => 'Pièce d’identité',
        'otro' => 'Autre',
    ],
    'campos' => [
        'procedencia' => 'Ville de provenance',
        'motivo_viaje' => 'Motif du voyage',
        'placas' => 'Plaque d’immatriculation',
        'rfc' => 'RFC (si vous avez besoin d’une facture)',
        'soporte' => 'Numéro de support',
        'parentesco' => 'Lien de parenté (pour les mineurs)',
    ],
    'generico' => [
        'aviso_privacidad' => [
            'titulo' => 'Politique de confidentialité',
            'cuerpo' => "{propiedad} ({domicilio}) est responsable de vos données personnelles.\n\nNous les utilisons pour gérer votre réservation et votre séjour, vous enregistrer, encaisser et respecter la loi. Nous ne les partageons qu’avec les prestataires qui nous aident à fournir le service et avec les autorités lorsque la loi l’exige. Nous ne les vendons pas.\n\nLa photo de votre pièce d’identité est conservée jusqu’à 30 jours après votre départ ; les données d’enregistrement, un an.\n\nVous pouvez accéder à vos données, les corriger ou demander leur suppression en écrivant à {correo}.\n\nCe texte est une base : il doit être adapté à la loi de votre pays.",
        ],
        'contrato_grupo' => [
            'titulo' => 'Contrat de groupe',
            'cuerpo' => <<<'TEXTO'
Ce contrat régit le séjour du groupe « {grupo} » ({codigo}) à {propiedad}, coordonné par {coordinador}.

1. SÉJOUR. Du {llegada} au {salida}, avec {habitaciones} chambres.
2. PRIX. Le total est de {total}. Paiements convenus :
{pagos}
3. LISTE DES CLIENTS. Le coordinateur indiquera qui va dans chaque chambre au plus tard le {fecha_limite}.
4. ANNULATION. La politique d’annulation communiquée s’applique.
5. CONDITIONS. Le coordinateur fait connaître à son groupe le règlement de l’établissement. Chaque client répond des dommages qu’il cause.
6. RÉCLAMATIONS. Écrivez-nous à {correo}.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Conditions d’hébergement',
            'cuerpo' => "Ces conditions régissent votre séjour à {propiedad}.\n\n1. Le prix est celui indiqué dans votre réservation.\n2. L’arrivée et le départ se font aux heures indiquées.\n3. Seules les personnes enregistrées peuvent séjourner.\n4. La politique d’annulation communiquée à la réservation s’applique.\n5. Le client est responsable des dommages qu’il cause.\n\nPour toute réclamation, écrivez à {correo}.",
        ],
        'reglamento' => [
            'titulo' => 'Règlement intérieur',
            'cuerpo' => 'Pour le confort de tous, à {propiedad} nous vous demandons de respecter le repos des autres, de ne pas fumer dans les chambres, d’enregistrer toutes les personnes présentes et de nous signaler tout dégât.',
        ],
        'politica_cancelacion' => [
            'titulo' => 'Politique d’annulation',
            'cuerpo' => 'Vous pouvez annuler sans frais jusqu’à [nombre] jours avant votre arrivée. Ensuite, [pourcentage] % du total sera facturé. En cas de non-présentation, la première nuit sera facturée. Pour annuler, écrivez à {correo}.',
        ],
    ],
];
