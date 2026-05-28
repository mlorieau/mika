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
$_og_image    = isset($page_og_image) ? htmlspecialchars($_site_url . '/' . ltrim($page_og_image, '/'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($_og_image_default, ENT_QUOTES, 'UTF-8');
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

  <!-- Fonts & CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= CSS_PATH ?>zone85.css">

  <?php if (!empty($page_styles)) echo $page_styles . "\n"; ?>

  <!-- JSON-LD -->
  <?php if (!empty($page_schema) && is_array($page_schema)): ?>
  <script type="application/ld+json">
  <?= json_encode($page_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
  </script>
  <?php endif; ?>
</head>
<body>
