<?php
// ============================================================
// ZONE85 — Helpers Admin V8
// ============================================================

if (!function_exists('current_user')) {
    require_once __DIR__ . '/auth.php';
}
if (!function_exists('url')) {
    require_once __DIR__ . '/functions.php';
}

// Les pages admin ne doivent jamais être bloquées par le mode maintenance.
// Cette constante est vérifiée dans header.php → middleware.
if (!defined('SKIP_MAINTENANCE_CHECK')) {
    define('SKIP_MAINTENANCE_CHECK', true);
}

// ── Vérification rôle ─────────────────────────────────────

function is_admin(): bool {
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function require_admin(): void {
    if (!is_logged_in()) {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        header('Location: ' . $base . '/login.php');
        exit;
    }
    if (!is_admin()) {
        http_response_code(403);
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Accès refusé — ZONE85</title>
  <style>
    body { font-family: 'Inter', sans-serif; display: flex; align-items: center;
           justify-content: center; min-height: 100vh; margin: 0;
           background: #F5F1ED; color: #0f1e2d; }
    .box { text-align: center; padding: 48px; background: #fff;
           border-radius: 16px; box-shadow: 0 8px 32px rgba(12,30,46,.1);
           max-width: 480px; }
    h1 { color: #ea5649; margin: 0 0 12px; font-size: 1.8rem; }
    p  { color: #6b7f96; margin: 0 0 24px; }
    a  { display: inline-block; padding: 12px 28px; background: #ea5649;
         color: #fff; border-radius: 8px; text-decoration: none;
         font-weight: 700; font-size: .9rem; }
  </style>
</head>
<body>
  <div class="box">
    <div style="font-size:3rem;margin-bottom:16px">🚫</div>
    <h1>Accès refusé</h1>
    <p>Tu n'as pas les droits pour accéder à cette section.<br>
       Réserve aux admins de la Zone85.</p>
    <a href="<?= $base ?>/index.php">Retour au site</a>
  </div>
</body>
</html>
        <?php
        exit;
    }
}
