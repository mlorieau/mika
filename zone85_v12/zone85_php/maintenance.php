<?php
// maintenance.php — Page affichée en mode maintenance
// Ne pas inclure header.php (évite les dépendances circulaires)
http_response_code(503);
header('Retry-After: 3600');
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Zone85 — Maintenance en cours</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #0c1e2e; color: #fff;
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      text-align: center; padding: 32px 24px;
    }
    .card { max-width: 480px; width: 100%; }
    .logo { font-size: .72rem; font-weight: 900; letter-spacing: .2em;
            text-transform: uppercase; color: #ea5649; margin-bottom: 48px; }
    .icon { font-size: 4rem; margin-bottom: 28px; line-height: 1; }
    h1 { font-size: clamp(1.6rem, 4vw, 2.2rem); font-weight: 900; margin-bottom: 16px;
         letter-spacing: -.5px; }
    p  { font-size: .95rem; color: rgba(255,255,255,.6); line-height: 1.8; margin-bottom: 32px; }
    .badge {
      display: inline-block; background: rgba(234,86,73,.15); color: #f07066;
      border: 1px solid rgba(234,86,73,.3); padding: 8px 20px; border-radius: 999px;
      font-size: .82rem; font-weight: 700; letter-spacing: .05em;
    }
    .footer { margin-top: 48px; font-size: .74rem; color: rgba(255,255,255,.25); }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">ZONE85</div>
  <div class="icon">⚙️</div>
  <h1>Maintenance en cours</h1>
  <p>
    Zone85 est temporairement hors ligne pour une mise à jour.<br>
    Nous serons de retour très bientôt !
  </p>
  <span class="badge">Retour imminent</span>
  <div class="footer">© 2025 Zone85 — L'Esprit Vendée</div>
  <div style="margin-top:24px">
    <a href="login.php?redirect=admin/dashboard.php"
       style="font-size:.72rem;color:rgba(255,255,255,.2);text-decoration:none"
       title="Accès administrateur">·</a>
  </div>
</div>
</body>
</html>
