<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dépôt des pièces justificatives
    |--------------------------------------------------------------------------
    |
    | Les scans du bureau courrier atterrissent sur le disque « sinistres »,
    | hors du dossier public. Un scan d'acte de décès en A4 couleur pèse
    | rarement plus de 3 Mo ; la marge couvre les envois multi-pages.
    |
    */

    'disque' => 'sinistres',

    'taille_max_ko' => (int) env('SINISTRES_TAILLE_MAX_KO', 10240),

    'types_acceptes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SINISTRES_TYPES_ACCEPTES', 'pdf,jpg,jpeg,png,tiff,heic')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Délais
    |--------------------------------------------------------------------------
    |
    | « instruction_jours » est un objectif interne de service : il pilote
    | l'échéance affichée au gestionnaire et la liste des dossiers en retard.
    |
    | Les autres valeurs reprennent des délais du code CIMA. ELLES SONT À FAIRE
    | VALIDER par le service juridique avant mise en production : elles ne sont
    | utilisées qu'à titre indicatif (alertes), jamais pour rejeter un dossier
    | automatiquement.
    |
    */

    'delais' => [
        'instruction_jours' => (int) env('SINISTRES_INSTRUCTION_JOURS', 30),
        'relance_pieces_jours' => (int) env('SINISTRES_RELANCE_JOURS', 15),
        'prescription_mois' => (int) env('SINISTRES_PRESCRIPTION_MOIS', 60),
    ],

];
