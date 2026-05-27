<?php
// ============================================================
// ZONE 85 — Configuration globale
// ============================================================

define('SITE_NAME',    'Zone 85 — L\'Esprit Vendée');
define('SITE_TAGLINE', 'La Vendée qui joue, qui marche, qui enquête et qui se raconte.');
define('SITE_EMAIL',   'contact@zone85.fr');

// Chemins assets (relatifs depuis la racine du projet)
define('ASSETS_PATH', 'assets/');
define('CSS_PATH',    'assets/css/');
define('JS_PATH',     'assets/js/');
define('IMG_PATH',    'assets/img/');

// Saisons (labels pour l'interface)
define('SEASONS', [
    'saison-du-reveil'         => 'Saison du Réveil',
    'camp-ete-zone85'          => 'Camp d\'Été Zone85',
    'saison-des-chemins-creux' => 'Saison des Chemins Creux',
    'saison-des-veillees'      => 'Saison des Veillées',
]);

// Clans (slugs valides)
define('CLAN_SLUGS', ['bocage', 'littoral', 'marais']);

// Barème XP (référence)
define('XP_RATES', [
    'vote'                        => 2,
    'quiz_attempt'                => 5,
    'quiz_success'                => 10,
    'photo_posted'                => 10,
    'photo_coup_de_coeur'         => 50,
    'rando_review'                => 15,
    'ktc_participation'           => 10,
    'ktc_correct'                 => 50,
    'investigation_participation' => 10,
    'investigation_solved'        => 80,
    'hidden_hunt_element'         => 5,
    'grande_mission_participation'=> 20,
    'grande_mission_selected'     => 100,
]);
