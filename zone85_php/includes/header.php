<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($page_description ?? 'Zone85 — Le terrain de jeu vendéen. Explore, contribue et bâtis ta légende en Vendée.') ?>">
  <title><?= e($page_title ?? 'Zone 85') ?> — Zone 85 · L'Esprit Vendée</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= CSS_PATH ?>zone85.css">
<?php if (!empty($page_styles)) echo $page_styles . "\n"; ?>
</head>
<body>
