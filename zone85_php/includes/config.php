<?php
// ============================================================
// ZONE 85 — Configuration
// ============================================================

// Environnement
define('APP_ENV', 'dev'); // 'dev' | 'prod'

// Site
define('SITE_NAME',    'ZONE85');
define('SITE_TAGLINE', "La Vendée qui joue, qui marche, qui enquête et qui se raconte.");
define('SITE_EMAIL',   'contact@zone85.fr');
define('SITE_URL',     'https://www.zone85.fr'); // sans slash final

// Chemins (utilise __DIR__ pour être robuste)
define('BASE_PATH',   dirname(__DIR__) . '/');
define('ASSETS_PATH', 'assets/');
define('CSS_PATH',    'assets/css/');
define('JS_PATH',     'assets/js/');
define('IMG_PATH',    'assets/img/');
define('UPLOAD_PATH', 'uploads/'); // futur

// Upload (futur)
define('UPLOAD_MAX_SIZE',          5 * 1024 * 1024); // 5 Mo
define('ALLOWED_IMAGE_TYPES',      ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Session (futur)
define('SESSION_NAME',    'zone85_session');
define('SESSION_TIMEOUT', 3600); // 1h

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
