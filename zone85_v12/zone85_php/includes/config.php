<?php
// ============================================================
// ZONE 85 — Configuration
// ============================================================

// Chargement du fichier .env (développement local uniquement)
// En prod, les variables sont injectées par le serveur (Apache SetEnv / .env Nginx / secrets hébergeur).
$_env_file = dirname(__DIR__) . '/.env';
if (file_exists($_env_file)) {
    foreach (parse_ini_file($_env_file, false, INI_SCANNER_RAW) as $_k => $_v) {
        if (getenv($_k) === false) putenv("$_k=$_v");
        if (!isset($_ENV[$_k])) $_ENV[$_k] = $_v;
    }
    unset($_env_file, $_k, $_v);
} else {
    unset($_env_file);
}

/** Lit une variable d'environnement avec valeur par défaut */
function _env(string $key, string $default = ''): string {
    $v = $_ENV[$key] ?? getenv($key);
    return ($v !== false && $v !== null && $v !== '') ? (string)$v : $default;
}

// ── Environnement ──────────────────────────────────────────
// Surcharger via APP_ENV=prod dans .env ou variable serveur avant mise en ligne.
define('APP_ENV',           _env('APP_ENV', 'dev'));            // 'dev' | 'prod'
define('DEV_TOOLS_ALLOWED', _env('DEV_TOOLS_ALLOWED', 'false') === 'true');

// ── Identité du site ───────────────────────────────────────
define('SITE_NAME',    'ZONE85');
define('SITE_TAGLINE', "La Vendée qui joue, qui marche, qui enquête et qui se raconte.");
define('SITE_EMAIL',   'contact@zone85.fr');
define('SITE_URL',     'https://www.zone85.fr'); // sans slash final

// ── Chemins filesystem ─────────────────────────────────────
define('BASE_PATH',   dirname(__DIR__) . '/');
define('ASSETS_PATH', 'assets/');
define('CSS_PATH',    'assets/css/');
define('JS_PATH',     'assets/js/');
define('IMG_PATH',    'assets/img/');
define('UPLOAD_PATH', 'uploads/');

// URL de base — '/' si la racine du domaine, '/sous-dossier/' sinon
define('BASE_URL', '/test/zone85_php/');

// ── Upload ─────────────────────────────────────────────────
define('UPLOAD_MAX_SIZE',          2 * 1024 * 1024);                     // 2 Mo (avatars)
define('UPLOAD_MAX_SIZE_MEDIA',    5 * 1024 * 1024);                     // 5 Mo (randos/médias)
define('UPLOAD_MAX_DIM',           4000);                                 // px max par côté (anti-DoS)
define('ALLOWED_IMAGE_TYPES',      ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// ── Session ────────────────────────────────────────────────
define('SESSION_NAME',    'zone85_session');
define('SESSION_TIMEOUT', 3600); // 1h

// ── Données métier ─────────────────────────────────────────
define('CLAN_SLUGS', ['bocage', 'littoral', 'marais']);
define('SEASONS', [
    'saison-du-reveil'         => 'Saison du Réveil',
    'camp-ete-zone85'          => 'Camp d\'Été Zone85',
    'saison-des-chemins-creux' => 'Saison des Chemins Creux',
    'saison-des-veillees'      => 'Saison des Veillées',
]);

// ── Base de données ────────────────────────────────────────
// Toujours surcharger DB_USER et DB_PASS via .env ou variables serveur en prod.
define('DB_ENABLED', _env('DB_ENABLED', 'true') !== 'false');
define('DB_HOST',    _env('DB_HOST',    'localhost'));
define('DB_PORT',    (int)_env('DB_PORT', '3306'));
define('DB_NAME',    _env('DB_NAME',    'qg_'));
define('DB_USER',    _env('DB_USER',    'AdminQg85'));
define('DB_PASS',    _env('DB_PASS',    'AdminQg85!')); // TOUJOURS via .env en prod
define('DB_CHARSET', 'utf8mb4');

// ── Session auto-start ─────────────────────────────────────
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

// ── Emails transactionnels (Brevo) ─────────────────────────
// Renseigner BREVO_API_KEY dans .env pour activer — sinon fallback sur mail().
define('BREVO_API_KEY',    _env('BREVO_API_KEY', ''));  // xkeysib-...
define('BREVO_API_URL',    'https://api.brevo.com/v3/smtp/email');
define('BREVO_FROM_EMAIL', 'noreply@zone85.fr');
define('BREVO_FROM_NAME',  'ZONE85');
define('BREVO_ENABLED',    _env('BREVO_API_KEY', '') !== '');

// ── Analytics ──────────────────────────────────────────────
// GA4 : renseigner G-XXXXXXXXXX pour activer.
define('GA4_MEASUREMENT_ID', _env('GA4_MEASUREMENT_ID', ''));

// Matomo : alternative privacy-friendly à GA4.
define('MATOMO_URL',     _env('MATOMO_URL', ''));
define('MATOMO_SITE_ID', (int)_env('MATOMO_SITE_ID', '0'));

// ── PWA ────────────────────────────────────────────────────
define('PWA_ENABLED',    true);
define('PWA_APP_NAME',   'ZONE85');
define('PWA_SHORT_NAME', 'Zone85');
define('PWA_THEME_COLOR','#0c1e2e');
define('PWA_BG_COLOR',   '#f8f4ef');
define('PWA_DISPLAY',    'standalone');

// ── Settings DB ────────────────────────────────────────────
// La table `settings` écrase ces constantes dès que la DB est disponible.
// Utiliser get_setting('clé') dans le code pour bénéficier de la priorité DB → config.
// includes/settings.php est auto-chargé par db.php.

// ── Barème XP ──────────────────────────────────────────────
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
