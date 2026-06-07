<?php
// ============================================================
// sitemap.php — Sitemap XML dynamique
// Accessible à : https://www.zone85.fr/sitemap.php
// ============================================================

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/settings.php';

$site_url = rtrim(
    function_exists('get_setting') ? get_setting('site_url', SITE_URL) : SITE_URL,
    '/'
);
$pdo   = db();
$today = date('Y-m-d');

// ── Pages statiques ────────────────────────────────────────
$static_pages = [
    ['loc' => '/',                    'priority' => '1.0', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/concept.php',         'priority' => '0.9', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => '/les-echos.php',       'priority' => '0.9', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/missions.php',        'priority' => '0.8', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/randos.php',          'priority' => '0.8', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/clans.php',           'priority' => '0.7', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/communaute.php',      'priority' => '0.7', 'changefreq' => 'daily',   'lastmod' => $today],
    ['loc' => '/hall.php',            'priority' => '0.6', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/contact.php',         'priority' => '0.5', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/mentions-legales.php','priority' => '0.3', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/confidentialite.php', 'priority' => '0.3', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/cookies.php',         'priority' => '0.3', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/cgu.php',             'priority' => '0.4', 'changefreq' => 'yearly',  'lastmod' => $today],
];

// ── Articles publiés ───────────────────────────────────────
$articles = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT slug, COALESCE(updated_at, published_at) AS lastmod
             FROM articles WHERE status='published'
             ORDER BY published_at DESC LIMIT 500"
        );
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ── Pages CMS publiées ─────────────────────────────────────
$cms_pages = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT slug, updated_at AS lastmod FROM pages
             WHERE status='published' ORDER BY id ASC"
        );
        $cms_pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ── Randos publiées ────────────────────────────────────────
$randos = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT slug, updated_at AS lastmod FROM randos
             WHERE status='published' ORDER BY created_at DESC LIMIT 200"
        );
        $randos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ── Sortie XML ─────────────────────────────────────────────
header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

function sitemap_entry(string $loc, string $changefreq, string $priority, ?string $lastmod): string {
    $out  = "  <url>\n";
    $out .= "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    if ($lastmod) {
        $out .= "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n";
    }
    $out .= "    <changefreq>{$changefreq}</changefreq>\n";
    $out .= "    <priority>{$priority}</priority>\n";
    $out .= "  </url>\n";
    return $out;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
          http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
<?php

// Pages statiques
foreach ($static_pages as $p) {
    echo sitemap_entry($site_url . $p['loc'], $p['changefreq'], $p['priority'], $p['lastmod']);
}

// Articles
foreach ($articles as $a) {
    echo sitemap_entry(
        $site_url . '/article.php?slug=' . rawurlencode($a['slug']),
        'monthly', '0.7', $a['lastmod']
    );
}

// CMS pages (index et concept déjà dans les pages statiques)
$cms_excluded = ['index', 'concept'];
foreach ($cms_pages as $p) {
    if (in_array($p['slug'], $cms_excluded, true)) continue;
    echo sitemap_entry(
        $site_url . '/page.php?slug=' . rawurlencode($p['slug']),
        'monthly', '0.6', $p['lastmod']
    );
}

// Randos
foreach ($randos as $r) {
    echo sitemap_entry(
        $site_url . '/rando.php?slug=' . rawurlencode($r['slug']),
        'monthly', '0.6', $r['lastmod']
    );
}

?>
</urlset>
