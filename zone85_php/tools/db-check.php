<?php
// ── Protection : ne jamais exposer en production ──────────────
// Fichier de diagnostic à supprimer ou protéger par IP/htpasswd avant déploiement.
if (defined('APP_ENV') && APP_ENV === 'prod') {
    http_response_code(403);
    die('403 Forbidden — Ce fichier de diagnostic ne doit pas être accessible en production.');
}

// ============================================================
// ZONE 85 — Diagnostic de connexion MySQL
// ⚠ SUPPRIMER ou PROTÉGER ce fichier avant toute mise en production.
// Il ne doit jamais être accessible publiquement sur un serveur live.
// ============================================================

$_root = dirname(__DIR__);
require_once $_root . '/includes/config.php';
require_once $_root . '/includes/functions.php';
require_once $_root . '/includes/data.php';
require_once $_root . '/includes/db.php';
require_once $_root . '/includes/repositories.php';

// ── Fonctions helper locales ──────────────────────────────────

function _diag_row(string $label, bool $ok, string $detail, string $badge_override = ''): void {
    $badge = $badge_override ?: ($ok ? 'OK' : 'ERR');
    $cls   = $ok ? 'ok' : 'err';
    echo '<div class="row ' . $cls . '">';
    echo '<span class="badge">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="lbl">'  . htmlspecialchars($label,  ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="det">'  . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</div>' . "\n";
}

function _diag_section(string $title): void {
    echo '<div class="sec-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>' . "\n";
}

// ── Collecte des résultats ────────────────────────────────────

$overall_ok  = true;
$db_enabled  = defined('DB_ENABLED') && DB_ENABLED;
$pdo         = null;
$connect_ok  = false;

// 1. DB_ENABLED
$check_enabled_detail = $db_enabled
    ? 'true — tentative de connexion MySQL'
    : 'false — mode mock actif, aucune connexion tentée';

// 2. Connexion PDO
$connect_detail = '';
if ($db_enabled) {
    $pdo = db();
    $connect_ok = ($pdo !== null);
    if ($connect_ok) {
        try {
            $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
            $connect_detail = 'Serveur : ' . $ver
                . ' | Base : ' . (defined('DB_NAME') ? DB_NAME : '?')
                . ' | Host : ' . (defined('DB_HOST') ? DB_HOST : '?');
            // DB_USER et DB_PASS ne sont jamais affichés
        } catch (PDOException $e) {
            $connect_detail = 'Connexion OK mais VERSION() a échoué.';
        }
    } else {
        $connect_detail  = 'Échec — vérifier DB_HOST, DB_NAME, DB_USER, DB_PASS dans config.php.';
        $overall_ok      = false;
    }
} else {
    $connect_ok     = true; // non-erreur : simplement désactivé
    $connect_detail = 'Skippé (DB_ENABLED = false).';
}

// 3. Tables requises
$required_tables = [
    'clans', 'seasons', 'games', 'missions', 'badges',
    'users', 'participations', 'xp_logs', 'clan_score_logs', 'hall_items',
];
$table_results = [];
if ($pdo) {
    try {
        $placeholders = implode(',', array_fill(0, count($required_tables), '?'));
        $stmt = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})"
        );
        $stmt->execute($required_tables);
        $found = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'TABLE_NAME');
        foreach ($required_tables as $t) {
            $exists = in_array($t, $found);
            if (!$exists) $overall_ok = false;
            $table_results[$t] = $exists;
        }
    } catch (PDOException $e) {
        foreach ($required_tables as $t) {
            $table_results[$t] = false;
            $overall_ok = false;
        }
    }
}

// 4. Comptage des lignes
$count_tables  = ['clans', 'seasons', 'games', 'missions', 'users'];
$count_results = [];
if ($pdo) {
    foreach ($count_tables as $t) {
        try {
            $n = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            if ($n === 0) $overall_ok = false;
            $count_results[$t] = $n;
        } catch (PDOException $e) {
            $count_results[$t] = -1; // erreur
            $overall_ok = false;
        }
    }
}

// 5. Tests repositories
$repo_tests = [
    'fetch_all_clans()'         => fn() => fetch_all_clans(),
    'fetch_active_season()'     => fn() => fetch_active_season(),
    'fetch_featured_missions()' => fn() => fetch_featured_missions(3),
    'fetch_top_members()'       => fn() => fetch_top_members(3),
    'fetch_hall_photos()'       => fn() => fetch_hall_photos(3),
];
$repo_results = [];
foreach ($repo_tests as $fname => $fn) {
    try {
        $res = $fn();
        if ($res === null) {
            if ($db_enabled) {
                $repo_results[$fname] = ['ok' => false, 'detail' => 'null retourné — DB active mais aucune donnée.'];
                $overall_ok = false;
            } else {
                $repo_results[$fname] = ['ok' => true, 'detail' => 'null (normal — DB désactivée, fallback data.php)'];
            }
        } else {
            $n = is_array($res) ? count($res) : 1;
            $repo_results[$fname] = ['ok' => true, 'detail' => "{$n} élément(s) retourné(s)"];
        }
    } catch (Throwable $e) {
        $repo_results[$fname] = [
            'ok'     => false,
            'detail' => 'Exception : ' . $e->getMessage(),
        ];
        $overall_ok = false;
    }
}

// ── Résumé global ─────────────────────────────────────────────
if (!$db_enabled) {
    $summary_cls  = 'warn';
    $summary_msg  = '⚙ Mode mock actif — DB_ENABLED = false. Passer à true après l\'import MySQL pour tester la connexion réelle.';
} elseif ($overall_ok) {
    $summary_cls  = 'ok';
    $summary_msg  = '✓ Tout est opérationnel — MySQL actif, tables présentes, repositories fonctionnels.';
} else {
    $summary_cls  = 'err';
    $summary_msg  = '✗ Des problèmes ont été détectés. Voir les détails ci-dessous.';
}

?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZONE85 — Diagnostic DB</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#0d1e2c;color:#e8e0d4;padding:32px 16px;min-height:100vh;line-height:1.5}
    .wrap{max-width:740px;margin:0 auto}

    .warning-banner{background:#ea5649;color:#fff;padding:12px 18px;border-radius:8px;margin-bottom:24px;font-size:.85rem;font-weight:700;line-height:1.5}

    h1{font-size:1.5rem;font-weight:900;color:#fff;letter-spacing:-.5px;margin-bottom:2px}
    .meta{font-size:.78rem;color:rgba(255,255,255,.35);margin-bottom:28px}

    .summary{padding:14px 18px;border-radius:8px;margin-bottom:28px;font-weight:700;font-size:.95rem}
    .summary.ok  {background:rgba(42,157,92,.15);border:1px solid rgba(42,157,92,.35);color:#5dd09a}
    .summary.warn{background:rgba(201,150,42,.15);border:1px solid rgba(201,150,42,.35);color:#f0c060}
    .summary.err {background:rgba(234,86,73,.15); border:1px solid rgba(234,86,73,.35); color:#f07868}

    .section{margin-bottom:22px}
    .sec-title{font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid rgba(255,255,255,.06)}

    .row{display:flex;align-items:baseline;gap:10px;padding:8px 12px;border-radius:6px;margin-bottom:4px;background:rgba(255,255,255,.04);font-size:.85rem}
    .row.ok  .badge{background:rgba(42,157,92,.3); color:#5dd09a}
    .row.err .badge{background:rgba(234,86,73,.3); color:#f07868}
    .row.warn .badge{background:rgba(201,150,42,.3);color:#f0c060}
    .badge{padding:1px 7px;border-radius:4px;font-size:.68rem;font-weight:900;flex-shrink:0;letter-spacing:.04em}
    .lbl{font-weight:600;color:#fff;min-width:220px;flex-shrink:0}
    .det{color:rgba(255,255,255,.45);flex:1;font-size:.8rem}

    .footer{margin-top:32px;padding-top:16px;border-top:1px solid rgba(255,255,255,.07);font-size:.73rem;color:rgba(255,255,255,.25);line-height:1.8}
  </style>
</head>
<body>
<div class="wrap">

  <div class="warning-banner">
    ⚠&nbsp; FICHIER DE DIAGNOSTIC — À supprimer ou protéger par IP / htpasswd avant toute mise en production.
    Ne jamais exposer publiquement sur un serveur live.
  </div>

  <h1>ZONE85 — Diagnostic MySQL</h1>
  <p class="meta">
    <?= date('Y-m-d H:i:s') ?> &nbsp;·&nbsp;
    PHP <?= PHP_VERSION ?> &nbsp;·&nbsp;
    APP_ENV : <?= defined('APP_ENV') ? e(APP_ENV) : 'non défini' ?>
  </p>

  <div class="summary <?= $summary_cls ?>">
    <?= htmlspecialchars($summary_msg, ENT_QUOTES, 'UTF-8') ?>
  </div>

  <!-- 1. Configuration -->
  <div class="section">
    <?php _diag_section('1 — Configuration'); ?>
    <?php _diag_row('DB_ENABLED', $db_enabled, $check_enabled_detail, $db_enabled ? 'ON' : 'OFF'); ?>
    <?php _diag_row('Connexion PDO', $connect_ok, $connect_detail); ?>
  </div>

  <!-- 2. Tables -->
  <?php if (!empty($table_results)): ?>
  <div class="section">
    <?php _diag_section('2 — Tables MySQL'); ?>
    <?php foreach ($table_results as $t => $exists): ?>
      <?php _diag_row("`{$t}`", $exists, $exists ? 'Présente' : 'MANQUANTE — importer schema.sql'); ?>
    <?php endforeach; ?>
  </div>
  <?php elseif ($db_enabled && !$pdo): ?>
  <div class="section">
    <?php _diag_section('2 — Tables MySQL'); ?>
    <?php _diag_row('Vérification', false, 'Connexion échouée — impossible de vérifier les tables.'); ?>
  </div>
  <?php endif; ?>

  <!-- 3. Données (seed) -->
  <?php if (!empty($count_results)): ?>
  <div class="section">
    <?php _diag_section('3 — Données (seed.sql)'); ?>
    <?php foreach ($count_results as $t => $n): ?>
      <?php
        if ($n < 0)      { _diag_row("`{$t}`", false, 'Erreur — table inaccessible'); }
        elseif ($n === 0) { _diag_row("`{$t}`", false, '0 ligne — importer seed.sql', 'VIDE'); }
        else              { _diag_row("`{$t}`", true,  "{$n} ligne(s)"); }
      ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 4. Repositories -->
  <div class="section">
    <?php _diag_section('4 — Repositories (includes/repositories.php)'); ?>
    <?php foreach ($repo_results as $fname => $r): ?>
      <?php _diag_row($fname, $r['ok'], $r['detail']); ?>
    <?php endforeach; ?>
  </div>

  <div class="footer">
    DB_HOST : <?= defined('DB_HOST') ? e(DB_HOST) : 'non défini' ?> &nbsp;·&nbsp;
    DB_NAME : <?= defined('DB_NAME') ? e(DB_NAME) : 'non défini' ?> &nbsp;·&nbsp;
    DB_PORT : <?= defined('DB_PORT') ? (int)DB_PORT : 3306 ?>
    &nbsp;— DB_USER et DB_PASS ne sont jamais affichés.
  </div>

</div>
</body>
</html>
