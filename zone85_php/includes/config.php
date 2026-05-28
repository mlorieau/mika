<?php
// ============================================================
// ZONE 85 — Configuration
// ============================================================

// Environnement
define('APP_ENV', 'dev'); // 'dev' | 'prod' — passer à 'prod' avant toute mise en ligne

// Outils de diagnostic (uniquement en dev, jamais en prod)
// Mettre à true uniquement pour une session de debug locale et remettre à false ensuite.
define('DEV_TOOLS_ALLOWED', false);

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

// Upload — avatars : 2 Mo max, types image uniquement
define('UPLOAD_MAX_SIZE',          2 * 1024 * 1024); // 2 Mo (avatars)
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

// Base de données (désactivée par défaut — activer quand la base est installée)
define('DB_ENABLED', true);        // Mettre true après avoir importé schema.sql + seed.sql
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'qg_');
define('DB_USER',    'AdminQg85');
define('DB_PASS',    'AdminQg85!'); // Renseigner via variable d'environnement en prod
define('DB_CHARSET', 'utf8mb4');

// ── Session auto-start ──────────────────────────────────────
// Démarré ici pour être disponible avant header.php (cookies/CSRF).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('zone85_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

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
