<?php
// ============================================================
// manifest.php — Manifest PWA dynamique Zone85
// Lit les paramètres depuis la DB (settings) avec fallback constantes.
// ============================================================

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/settings.php';

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/');

$name        = function_exists('get_setting') ? get_setting('pwa_app_name',   "ZONE85 — L'Esprit Vendée") : (defined('PWA_APP_NAME')   ? PWA_APP_NAME   : "ZONE85 — L'Esprit Vendée");
$short_name  = function_exists('get_setting') ? get_setting('pwa_short_name', 'Zone85')     : (defined('PWA_SHORT_NAME') ? PWA_SHORT_NAME : 'Zone85');
$theme_color = function_exists('get_setting') ? get_setting('pwa_theme_color','#0c1e2e')    : (defined('PWA_THEME_COLOR')? PWA_THEME_COLOR : '#0c1e2e');
$bg_color    = function_exists('get_setting') ? get_setting('pwa_bg_color',   '#f8f4ef')    : (defined('PWA_BG_COLOR')   ? PWA_BG_COLOR   : '#f8f4ef');
$display     = function_exists('get_setting') ? get_setting('pwa_display',    'standalone') : (defined('PWA_DISPLAY')    ? PWA_DISPLAY    : 'standalone');

$manifest = [
    'name'             => $name,
    'short_name'       => $short_name,
    'description'      => 'Terrain de jeu communautaire vendéen. Rejoins un clan, gagne des XP, fais vivre la Vendée autrement.',
    'start_url'        => $base . '/',
    'scope'            => $base . '/',
    'display'          => in_array($display, ['standalone','fullscreen','minimal-ui','browser'], true) ? $display : 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => $bg_color,
    'theme_color'      => $theme_color,
    'lang'             => 'fr',
    'categories'       => ['games', 'social', 'entertainment'],
    'icons'            => [
        ['src' => $base . '/assets/img/pwa/icon-72.png',  'sizes' => '72x72',   'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/pwa/icon-96.png',  'sizes' => '96x96',   'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/pwa/icon-128.png', 'sizes' => '128x128', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/pwa/icon-144.png', 'sizes' => '144x144', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/pwa/icon-152.png', 'sizes' => '152x152', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => $base . '/assets/img/pwa/icon-384.png', 'sizes' => '384x384', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $base . '/assets/img/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
    'shortcuts' => [
        [
            'name'        => 'Mes missions',
            'short_name'  => 'Missions',
            'description' => 'Voir les missions actives',
            'url'         => $base . '/missions.php',
            'icons'       => [['src' => $base . '/assets/img/pwa/shortcut-missions.png', 'sizes' => '96x96']],
        ],
        [
            'name'        => 'Mon profil',
            'short_name'  => 'Profil',
            'description' => 'Accéder à mon profil',
            'url'         => $base . '/profil.php',
            'icons'       => [['src' => $base . '/assets/img/pwa/shortcut-profil.png', 'sizes' => '96x96']],
        ],
        [
            'name'        => 'Classement',
            'short_name'  => 'Classement',
            'description' => 'Voir le classement des clans',
            'url'         => $base . '/classement.php',
            'icons'       => [['src' => $base . '/assets/img/pwa/shortcut-classement.png', 'sizes' => '96x96']],
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
