<?php
// header.php — En-tête HTML commun
// Variables attendues (toutes optionnelles sauf $page_title) :
//   $page_title, $page_description, $page_styles
//   $page_canonical, $page_robots
//   $page_og_title, $page_og_description, $page_og_image
//   $page_schema (array ou null)

global $seo_defaults;

$_site_url   = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
$_site_name  = defined('SITE_NAME') ? SITE_NAME : 'ZONE85';
$_og_image_default = $_site_url . '/' . (($seo_defaults['default_og_image'] ?? 'assets/img/ZONE852025.png'));

// Calcul des valeurs finales
$_title       = isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : $_site_name;
$_full_title  = isset($page_title) ? $_title . " — Zone 85 · L'Esprit Vendée" : "ZONE85 — Zone 85 · L'Esprit Vendée";
$_description = isset($page_description) ? htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') : htmlspecialchars($seo_defaults['default_description'] ?? '', ENT_QUOTES, 'UTF-8');
$_canonical   = isset($page_canonical) ? htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8') : '';
$_robots      = isset($page_robots) ? htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8') : 'index,follow';
$_og_title    = isset($page_og_title) ? htmlspecialchars($page_og_title, ENT_QUOTES, 'UTF-8') : $_title;
$_og_desc     = isset($page_og_description) ? htmlspecialchars($page_og_description, ENT_QUOTES, 'UTF-8') : $_description;
if (isset($page_og_image)) {
    if (function_exists('media_absolute_url')) {
        $_og_image = htmlspecialchars(media_absolute_url($page_og_image), ENT_QUOTES, 'UTF-8');
    } elseif (preg_match('~^https?://~i', (string)$page_og_image)) {
        $_og_image = htmlspecialchars((string)$page_og_image, ENT_QUOTES, 'UTF-8');
    } else {
        $_og_image = htmlspecialchars($_site_url . '/' . ltrim((string)$page_og_image, '/'), ENT_QUOTES, 'UTF-8');
    }
} else {
    $_og_image = htmlspecialchars($_og_image_default, ENT_QUOTES, 'UTF-8');
}

if (function_exists('set_security_headers')) { set_security_headers(); }

// ── Mode maintenance ─────────────────────────────────────────
// middleware.php est chargé ici systématiquement.
// Dépend de get_setting() → chargé via db.php → settings.php.
// Les pages admin incluent admin.php qui définit SKIP_MAINTENANCE_CHECK.
if (!defined('SKIP_MAINTENANCE_CHECK')) {
    if (!function_exists('check_maintenance')) {
        $__mw = __DIR__ . '/middleware.php';
        if (file_exists($__mw)) require_once $__mw;
        unset($__mw);
    }
    if (function_exists('check_maintenance')) {
        check_maintenance();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Title & Description -->
  <title><?= $_full_title ?></title>
  <meta name="description" content="<?= $_description ?>">
  <meta name="robots" content="<?= $_robots ?>">
  <meta name="author" content="ZONE85">

  <?php if ($_canonical): ?>
  <link rel="canonical" href="<?= $_canonical ?>">
  <?php endif; ?>

  <!-- Open Graph -->
  <meta property="og:type"        content="website">
  <meta property="og:site_name"   content="ZONE85">
  <meta property="og:title"       content="<?= $_og_title ?>">
  <meta property="og:description" content="<?= $_og_desc ?>">
  <meta property="og:image"       content="<?= $_og_image ?>">
  <?php if ($_canonical): ?>
  <meta property="og:url"         content="<?= $_canonical ?>">
  <?php endif; ?>
  <meta property="og:locale"      content="fr_FR">

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= $_og_title ?>">
  <meta name="twitter:description" content="<?= $_og_desc ?>">
  <meta name="twitter:image"       content="<?= $_og_image ?>">

  <!-- PWA Manifest & icons -->
  <?php if (!defined('PWA_ENABLED') || PWA_ENABLED): ?>
  <link rel="manifest" href="<?= rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') ?>/manifest.json">
  <meta name="theme-color" content="<?= defined('PWA_THEME_COLOR') ? PWA_THEME_COLOR : '#0c1e2e' ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Zone85">
  <link rel="apple-touch-icon" href="<?= rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') ?>/assets/img/pwa/icon-152.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?= rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') ?>/assets/img/pwa/icon-152.png">
  <link rel="apple-touch-icon" sizes="192x192" href="<?= rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') ?>/assets/img/pwa/icon-192.png">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') ?>/assets/img/pwa/icon-96.png">
  <?php endif; ?>

  <!-- Fonts & CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Syne:wght@700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= CSS_PATH ?>zone85.css">

  <?php if (!empty($page_styles)) echo $page_styles . "\n"; ?>

  <!-- JSON-LD -->
  <?php if (!empty($page_schema) && is_array($page_schema)): ?>
  <script type="application/ld+json">
  <?= json_encode($page_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
  </script>
  <?php endif; ?>

  <!-- Analytics -->
  <?php
  $_ga4_id    = defined('GA4_MEASUREMENT_ID') ? GA4_MEASUREMENT_ID : '';
  $_matomo_url = defined('MATOMO_URL') ? MATOMO_URL : '';
  $_matomo_id  = defined('MATOMO_SITE_ID') ? (int)MATOMO_SITE_ID : 0;
  ?>
  <?php if (!empty($_ga4_id)): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($_ga4_id, ENT_QUOTES) ?>"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?= htmlspecialchars($_ga4_id, ENT_QUOTES) ?>', { anonymize_ip: true });
    window.Zone85Analytics = { engine: 'ga4', id: '<?= htmlspecialchars($_ga4_id, ENT_QUOTES) ?>' };
  </script>
  <?php elseif (!empty($_matomo_url) && $_matomo_id > 0): ?>
  <script>
    var _paq = window._paq = window._paq || [];
    _paq.push(['trackPageView']); _paq.push(['enableLinkTracking']);
    (function(){var u='<?= rtrim(htmlspecialchars($_matomo_url, ENT_QUOTES), '/') ?>/';
      _paq.push(['setTrackerUrl', u+'matomo.php']);
      _paq.push(['setSiteId', '<?= $_matomo_id ?>']);
      var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
      g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
    window.Zone85Analytics = { engine: 'matomo', siteId: <?= $_matomo_id ?> };
  </script>
  <?php else: ?>
  <script>
    // Analytics non configuré — stub pour les événements
    window.Zone85Analytics = {
      engine: 'none',
      track: function(event, params) { console.debug('[Analytics]', event, params || {}); }
    };
  </script>
  <?php endif; ?>
</head>
<body>
<?php if (function_exists('render_maintenance_banner') && !empty($GLOBALS['_maintenance_admin_banner'])) render_maintenance_banner(); ?>
