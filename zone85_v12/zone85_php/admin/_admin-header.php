<?php
// _admin-header.php — Back-office Zone85 · Layout sidebar gauche
$_adm_user  = current_user();
$_adm_title = $admin_page_title ?? 'Admin';
$_adm_base  = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$_adm_css   = $_adm_base . '/assets/css/zone85.css';
$_adm_cur   = $admin_current ?? '';

$_adm_nav = [
  '' => [
    ['dashboard.php', 'dashboard', '⊞', 'Dashboard'],
  ],
  'CONTENUS' => [
    ['pages.php',              'pages',              '📄', 'Pages CMS'],
    ['echos.php',              'echos',              '📰', 'Échos'],
    ['meteo.php',              'meteo',              '🌦️', 'Météo'],
    ['randos.php',             'randos',             '🥾', 'Randos'],
    ['rando-validations.php',  'rando-validations',  '✅', 'Validations randos'],
    ['ktc-episodes.php',       'ktc-episodes',       '🥐', 'KTC'],
    ['missions.php',           'missions',           '🎯', 'Missions'],
    ['participations.php',     'participations',     '👀', 'Participations'],
    ['events.php',             'events',             '⚡', 'Flash events'],
  ],
  'COMMUNAUTÉ' => [
    ['users.php',   'users',   '👥', 'Membres'],
    ['clans.php',   'clans',   '🛡', 'Clans'],
    ['badges.php',  'badges',  '🏅', 'Badges'],
    ['seasons.php', 'seasons', '🗓', 'Saisons'],
  ],
  'STATISTIQUES' => [
    ['victor.php',  'victor',  '📖', 'VICTOR — Téléchargements'],
    ['seo.php',     'seo',     '🔍', 'SEO — Vue d\'ensemble'],
  ],
  'SYSTÈME' => [
    ['collectibles.php',  'collectibles',  '🔍', 'Collectibles'],
    ['media.php',         'media',         '🗂️', 'Médias'],
    ['pwa-settings.php',  'pwa-settings',  '📱', 'PWA — Icônes'],
    ['settings.php',      'settings',      '⚙️', 'Paramètres'],
  ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($_adm_title, ENT_QUOTES, 'UTF-8') ?> — Admin Zone85</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $_adm_css ?>">
  <style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; height: 100%; }
  body { font-family: 'Inter', sans-serif; background: #f0ece7; color: #0f1e2d; min-height: 100vh; }

  /* ── Layout principal ── */
  .adm-wrap { display: flex; min-height: 100vh; }

  /* ── Sidebar ── */
  .adm-sidebar {
    width: 224px;
    min-height: 100vh;
    background: #0c1e2e;
    border-right: 1px solid rgba(255,255,255,.06);
    position: fixed;
    top: 0; left: 0; bottom: 0;
    display: flex; flex-direction: column;
    overflow-y: auto;
    z-index: 200;
    transition: transform .25s cubic-bezier(.4,0,.2,1);
  }
  .adm-sidebar-brand {
    padding: 20px 18px 18px;
    border-bottom: 1px solid rgba(255,255,255,.07);
    flex-shrink: 0;
  }
  .adm-sidebar-brand a {
    font-size: .7rem; font-weight: 900; letter-spacing: .2em;
    text-transform: uppercase; color: #ea5649; text-decoration: none;
    display: flex; align-items: center; gap: 8px;
  }
  .adm-sidebar-brand a span {
    color: rgba(255,255,255,.35); font-weight: 500;
    letter-spacing: 0; font-size: .68rem; text-transform: none;
  }

  .adm-sidenav { flex: 1; padding: 8px 0 16px; overflow-y: auto; }

  .adm-sidenav-group { margin-bottom: 2px; }
  .adm-sidenav-label {
    font-size: .57rem; font-weight: 800; letter-spacing: .15em;
    text-transform: uppercase; color: rgba(255,255,255,.22);
    padding: 12px 18px 4px;
  }
  .adm-sidenav-link {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 18px;
    font-size: .8rem; font-weight: 600; text-decoration: none;
    color: rgba(255,255,255,.52);
    transition: background .12s, color .12s, border-color .12s;
    border-left: 3px solid transparent;
    line-height: 1.3;
  }
  .adm-sidenav-link .adm-sn-icon { font-size: 1rem; flex-shrink: 0; width: 18px; text-align: center; }
  .adm-sidenav-link:hover { background: rgba(255,255,255,.06); color: rgba(255,255,255,.85); }
  .adm-sidenav-link.active {
    background: rgba(234,86,73,.14);
    color: #f07066;
    border-left-color: #ea5649;
    font-weight: 700;
  }

  .adm-sidebar-footer {
    padding: 14px 16px;
    border-top: 1px solid rgba(255,255,255,.07);
    flex-shrink: 0;
    display: flex; flex-direction: column; gap: 4px;
  }
  .adm-sidebar-footer a {
    font-size: .76rem; font-weight: 600; text-decoration: none;
    color: rgba(255,255,255,.4); padding: 7px 10px; border-radius: 6px;
    transition: background .12s, color .12s;
    display: flex; align-items: center; gap: 6px;
  }
  .adm-sidebar-footer a:hover { background: rgba(255,255,255,.07); color: rgba(255,255,255,.8); }
  .adm-footer-site { color: #7ab8e8 !important; }
  .adm-sidebar-footer .adm-user-name {
    font-size: .72rem; color: rgba(255,255,255,.28); padding: 4px 10px 8px;
    font-weight: 600;
  }

  /* ── Zone contenu ── */
  .adm-content {
    margin-left: 224px;
    flex: 1;
    display: flex; flex-direction: column;
    min-height: 100vh;
  }

  /* Topbar contenu */
  .adm-topbar {
    background: #fff;
    border-bottom: 1px solid #e8e2db;
    padding: 0 24px 0 20px;
    height: 52px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
    gap: 14px;
    flex-shrink: 0;
  }
  .adm-topbar-left { display: flex; align-items: center; gap: 12px; }
  .adm-topbar-title { font-size: .86rem; font-weight: 800; color: #0c1e2e; }
  .adm-topbar-right { display: flex; align-items: center; gap: 8px; font-size: .76rem; color: #6b7f96; font-weight: 600; }

  /* Hamburger (mobile) */
  .adm-hamburger {
    display: none; background: none; border: none;
    cursor: pointer; padding: 6px; border-radius: 6px;
    color: #0c1e2e; transition: background .12s;
  }
  .adm-hamburger:hover { background: #f0ece7; }
  .adm-hamburger svg { width: 20px; height: 20px; display: block; }

  /* Overlay mobile */
  .adm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.4); z-index: 150;
  }
  .adm-overlay.show { display: block; }

  /* Main */
  .adm-main {
    flex: 1;
    padding: 28px 32px 64px;
    max-width: 1200px;
  }

  /* Page header */
  .adm-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; margin-bottom: 28px; flex-wrap: wrap;
  }
  .adm-page-title {
    font-size: 1.5rem; font-weight: 900; color: #0c1e2e;
    letter-spacing: -.5px; margin: 0; line-height: 1.2;
  }
  .adm-page-sub { font-size: .82rem; color: #6b7f96; margin: 4px 0 0; }
  .adm-page-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

  /* Boutons */
  .btn-adm {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 8px;
    font-size: .84rem; font-weight: 700; text-decoration: none; cursor: pointer;
    border: none; font-family: 'Inter', sans-serif;
    transition: opacity .15s, transform .1s;
    white-space: nowrap; line-height: 1;
  }
  .btn-adm:hover { opacity: .88; transform: translateY(-1px); }
  .btn-adm:active { transform: translateY(0); }
  .btn-adm-primary { background: #ea5649; color: #fff; }
  .btn-adm-ghost   { background: #fff; color: #0c1e2e; border: 1.5px solid #d0cbc5; }
  .btn-adm-success { background: #2a9d5c; color: #fff; }
  .btn-adm-danger  { background: #c0392b; color: #fff; }
  .btn-adm-sm { padding: 7px 14px; font-size: .76rem; }

  /* Cards */
  .adm-card {
    background: #fff; border-radius: 14px;
    box-shadow: 0 4px 18px rgba(12,30,46,.07);
    border: 1px solid rgba(18,49,78,.07);
    padding: 24px; margin-bottom: 20px;
  }
  .adm-card-title {
    font-size: .68rem; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: #6b7f96; margin: 0 0 16px;
  }

  /* Stats grid */
  .adm-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
  .adm-stat {
    background: #fff; border-radius: 12px;
    box-shadow: 0 2px 10px rgba(12,30,46,.06);
    border: 1px solid rgba(18,49,78,.07);
    padding: 20px 20px 18px;
  }
  .adm-stat-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #6b7f96; margin-bottom: 8px; }
  .adm-stat-value { font-size: 2rem; font-weight: 900; color: #0c1e2e; letter-spacing: -1px; line-height: 1; }
  .adm-stat-value.coral { color: #ea5649; }
  .adm-stat-value.green { color: #2a9d5c; }
  .adm-stat-value.blue  { color: #12314e; }
  .adm-stat-value.amber { color: #C9962A; }

  /* Tableau */
  .adm-table-wrap { overflow-x: auto; }
  table.adm-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
  table.adm-table th {
    background: #f8f4ef; text-align: left; padding: 10px 14px;
    font-size: .68rem; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: #6b7f96; border-bottom: 2px solid #e8e2db;
    white-space: nowrap;
  }
  table.adm-table td {
    padding: 11px 14px; border-bottom: 1px solid #f0ece7;
    vertical-align: middle; color: #0f1e2d;
  }
  table.adm-table tr:last-child td { border-bottom: none; }
  table.adm-table tr:hover td { background: #faf7f4; }

  /* Status badges */
  .adm-badge {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
  }
  .badge-active        { background: rgba(42,157,92,.12);  color: #1a7a42; }
  .badge-draft         { background: rgba(201,150,42,.12); color: #8a6020; }
  .badge-archived      { background: rgba(107,127,150,.12);color: #4a5f73; }
  .badge-closed        { background: rgba(107,127,150,.12);color: #4a5f73; }
  .badge-pending       { background: rgba(14,165,233,.12); color: #0369a1; }
  .badge-validated     { background: rgba(42,157,92,.12);  color: #1a7a42; }
  .badge-auto_validated{ background: rgba(42,157,92,.1);   color: #2a9d5c; }
  .badge-rejected      { background: rgba(234,86,73,.12);  color: #c0392b; }
  .badge-manual        { background: rgba(201,150,42,.12); color: #8a6020; }
  .badge-auto          { background: rgba(42,157,92,.1);   color: #2a9d5c; }
  .badge-hybrid        { background: rgba(14,165,233,.1);  color: #0369a1; }

  /* Alerts flash */
  .adm-flash {
    border-radius: 10px; padding: 14px 18px;
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 20px; font-size: .88rem; font-weight: 600;
  }
  .adm-flash-ok   { background: rgba(42,157,92,.1);  border: 1px solid rgba(42,157,92,.25); color: #1a7a42; }
  .adm-flash-err  { background: rgba(234,86,73,.08); border: 1px solid rgba(234,86,73,.25); color: #c0392b; }
  .adm-flash-warn { background: rgba(201,150,42,.1); border: 1px solid rgba(201,150,42,.3); color: #8a6020; }
  .adm-flash-info { background: rgba(14,165,233,.08);border: 1px solid rgba(14,165,233,.2); color: #0369a1; }

  /* Forms */
  .adm-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
  .adm-form-full { grid-column: 1 / -1; }
  .adm-field { display: flex; flex-direction: column; gap: 6px; }
  .adm-label { font-size: .76rem; font-weight: 700; color: #3d5166; }
  .adm-label span { color: #ea5649; }
  .adm-input, .adm-select, .adm-textarea {
    padding: 10px 14px; border-radius: 8px;
    border: 1.5px solid #d0cbc5; font-family: 'Inter', sans-serif;
    font-size: .88rem; color: #0f1e2d; background: #fff;
    transition: border-color .15s; width: 100%;
  }
  .adm-input:focus, .adm-select:focus, .adm-textarea:focus {
    outline: none; border-color: #ea5649; box-shadow: 0 0 0 3px rgba(234,86,73,.1);
  }
  .adm-textarea { resize: vertical; min-height: 100px; }
  .adm-hint { font-size: .72rem; color: #6b7f96; }

  /* Filters bar */
  .adm-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
  .adm-filters select, .adm-filters input {
    padding: 8px 12px; border-radius: 7px;
    border: 1.5px solid #d0cbc5; font-family: 'Inter', sans-serif;
    font-size: .82rem; background: #fff; color: #0f1e2d;
  }
  .adm-filters select:focus, .adm-filters input:focus { outline: none; border-color: #ea5649; }

  /* Detail rows */
  .adm-detail-row {
    display: flex; gap: 12px; justify-content: space-between;
    padding: 11px 0; border-bottom: 1px solid #f0ece7; font-size: .88rem;
  }
  .adm-detail-row:last-child { border-bottom: none; }
  .adm-detail-label { color: #6b7f96; font-weight: 600; white-space: nowrap; min-width: 160px; }
  .adm-detail-value { color: #0f1e2d; font-weight: 700; text-align: right; }

  /* Empty state */
  .adm-empty { text-align: center; padding: 56px 24px; color: #6b7f96; }
  .adm-empty-icon { font-size: 3rem; margin-bottom: 12px; }
  .adm-empty p { font-size: .9rem; margin: 0; }

  /* Responsive */
  @media (max-width: 900px) {
    .adm-sidebar { transform: translateX(-100%); box-shadow: none; }
    .adm-sidebar.open { transform: translateX(0); box-shadow: 4px 0 32px rgba(0,0,0,.25); }
    .adm-content { margin-left: 0; }
    .adm-hamburger { display: flex; }
    .adm-main { padding: 20px 16px 48px; }
    .adm-stats { grid-template-columns: 1fr 1fr; }
  }
  </style>
</head>
<body>
<div id="adm-overlay" class="adm-overlay" onclick="closeSidebar()"></div>
<div class="adm-wrap">

<!-- ── SIDEBAR ─────────────────────────────────────────── -->
<aside class="adm-sidebar" id="adm-sidebar">

  <div class="adm-sidebar-brand">
    <a href="dashboard.php">Zone85 <span>Admin</span></a>
  </div>

  <nav class="adm-sidenav">
    <?php foreach ($_adm_nav as $_grp_label => $_grp_items): ?>
    <div class="adm-sidenav-group">
      <?php if ($_grp_label): ?>
      <div class="adm-sidenav-label"><?= htmlspecialchars($_grp_label) ?></div>
      <?php endif; ?>
      <?php foreach ($_grp_items as [$_f, $_slug, $_ico, $_lbl]): ?>
      <a href="<?= $_f ?>" class="adm-sidenav-link<?= $_adm_cur === $_slug ? ' active' : '' ?>">
        <span class="adm-sn-icon"><?= $_ico ?></span>
        <?= htmlspecialchars($_lbl) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <div class="adm-sidebar-footer">
    <div class="adm-user-name">👤 <?= htmlspecialchars($_adm_user['pseudo'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <a href="<?= $_adm_base ?>/index.php" class="adm-footer-site" target="_blank">← Voir le site</a>
    <a href="<?= $_adm_base ?>/logout.php" onclick="return confirm('Se déconnecter ?')">Déconnexion</a>
  </div>

</aside>

<!-- ── CONTENU ─────────────────────────────────────────── -->
<div class="adm-content">

  <!-- Topbar contenu -->
  <div class="adm-topbar">
    <div class="adm-topbar-left">
      <button class="adm-hamburger" onclick="toggleSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <span class="adm-topbar-title"><?= htmlspecialchars($_adm_title, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="adm-topbar-right">
      <span>Admin Zone85</span>
    </div>
  </div>

  <main class="adm-main">
<script>
function toggleSidebar() {
  document.getElementById('adm-sidebar').classList.toggle('open');
  document.getElementById('adm-overlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('adm-sidebar').classList.remove('open');
  document.getElementById('adm-overlay').classList.remove('show');
}
</script>
