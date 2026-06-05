<?php
// ============================================================
// ZONE 85 — Middleware global V10.2
// À inclure en tête de chaque page publique (via header.php).
// Gère : mode maintenance.
// ============================================================

/**
 * Vérifie si le mode maintenance est actif.
 * Si oui et que l'utilisateur n'est pas admin → affiche maintenance.php et exit.
 * Les admins passent toujours.
 */
function check_maintenance(): void {
    // Charger les settings si disponible
    $is_maintenance = false;

    if (function_exists('get_setting')) {
        $val = get_setting('maintenance_mode', false);
        $is_maintenance = ($val === true || $val === '1' || $val === 'true');
    }

    if (!$is_maintenance) return;

    // Vérifier si l'utilisateur est admin
    $user = null;
    if (function_exists('current_user')) {
        $user = current_user();
    }
    if ($user && ($user['role'] ?? '') === 'admin') {
        // Afficher un bandeau discret pour l'admin
        // (injecté via $GLOBALS pour header.php)
        $GLOBALS['_maintenance_admin_banner'] = true;
        return;
    }

    // Non admin (ou non connecté) → page maintenance
    require_once __DIR__ . '/../maintenance.php';
    exit;
}

/**
 * Affiche le bandeau maintenance visible uniquement pour les admins.
 * À appeler dans le HTML après <body>.
 */
function render_maintenance_banner(): void {
    if (empty($GLOBALS['_maintenance_admin_banner'])) return;
    echo '<div style="position:fixed;top:0;left:0;right:0;z-index:9998;background:#C9962A;color:#fff;'
       . 'text-align:center;padding:8px 16px;font-size:.78rem;font-weight:800;font-family:inherit;'
       . 'display:flex;align-items:center;justify-content:center;gap:12px">'
       . '⚠️ MODE MAINTENANCE ACTIF — Seuls les admins voient le site. '
       . '<a href="' . (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/admin/settings.php?cat=general" '
       . 'style="color:#fff;text-decoration:underline">Désactiver →</a>'
       . '</div>'
       . '<div style="height:38px"></div>'; // décale le contenu
}
