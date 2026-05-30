<?php
// offline.php — Page affichée par le Service Worker hors connexion
$page_title       = 'Hors ligne — Zone85';
$page_description = 'Vous êtes actuellement hors ligne.';
$current_page     = '';
require_once 'includes/config.php';
require_once 'includes/functions.php';
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hors ligne — Zone85</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Inter', sans-serif;
      background: #0c1e2e; color: #fff;
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      text-align: center; padding: 24px;
    }
    .offline-card {
      max-width: 420px; width: 100%;
    }
    .offline-icon { font-size: 5rem; margin-bottom: 24px; line-height: 1; }
    h1 { font-size: 2rem; font-weight: 900; margin: 0 0 12px; letter-spacing: -.5px; }
    p  { font-size: .95rem; color: rgba(255,255,255,.65); line-height: 1.7; margin: 0 0 32px; }
    .btn-retry {
      display: inline-block; background: #ea5649; color: #fff;
      padding: 12px 28px; border-radius: 10px; font-weight: 800; font-size: .9rem;
      text-decoration: none; cursor: pointer; border: none; font-family: inherit;
      transition: opacity .15s;
    }
    .btn-retry:hover { opacity: .88; }
    .logo { font-size: .72rem; font-weight: 900; letter-spacing: .2em;
            text-transform: uppercase; color: #ea5649; margin-bottom: 40px; }
  </style>
</head>
<body>
<div class="offline-card">
  <div class="logo">ZONE85</div>
  <div class="offline-icon">📡</div>
  <h1>Connexion perdue</h1>
  <p>
    Il semblerait que tu sois hors ligne.<br>
    Vérifie ta connexion internet et réessaie.
  </p>
  <button class="btn-retry" onclick="window.location.reload()">Réessayer →</button>
</div>
</body>
</html>
