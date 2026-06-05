<?php
// Génère un sitemap XML dynamique pour Zone 85
// Accessible à : https://www.zone85.fr/sitemap.php
header('Content-Type: application/xml; charset=UTF-8');

$base_url  = 'https://www.zone85.fr';
$today     = date('Y-m-d');

$pages = [
    ['loc' => '/',                   'priority' => '1.0', 'changefreq' => 'daily',   'lastmod' => $today],
    ['loc' => '/concept.php',        'priority' => '0.9', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => '/clans.php',          'priority' => '0.9', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/missions.php',       'priority' => '0.9', 'changefreq' => 'daily',   'lastmod' => $today],
    ['loc' => '/communaute.php',      'priority' => '0.8', 'changefreq' => 'daily',   'lastmod' => $today],
    ['loc' => '/hall.php',           'priority' => '0.8', 'changefreq' => 'weekly',  'lastmod' => $today],
    ['loc' => '/contact.php',        'priority' => '0.5', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/mentions-legales.php','priority'=> '0.3', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/confidentialite.php','priority' => '0.3', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/cookies.php',        'priority' => '0.3', 'changefreq' => 'yearly',  'lastmod' => $today],
    ['loc' => '/cgu.php',            'priority' => '0.4', 'changefreq' => 'yearly',  'lastmod' => $today],
    // Pages non indexées (profil, inscription) volontairement exclues
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
          http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
<?php foreach ($pages as $page): ?>
  <url>
    <loc><?= htmlspecialchars($base_url . $page['loc'], ENT_QUOTES, 'UTF-8') ?></loc>
    <lastmod><?= $page['lastmod'] ?></lastmod>
    <changefreq><?= $page['changefreq'] ?></changefreq>
    <priority><?= $page['priority'] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
