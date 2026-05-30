<?php
// _admin-header.php — En-tête partagé du back-office admin
// Variables attendues : $admin_page_title (string), $admin_current (string)
$_adm_user = current_user();
$_adm_title = $admin_page_title ?? 'Admin';
$_adm_base  = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$_adm_css   = $_adm_base . '/assets/css/zone85.css';
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
  /* ── ADMIN CHROME ── */
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; height: 100%; }
  body { font-family: 'Inter', sans-serif; background: #f0ece7; color: #0f1e2d; min-height: 100vh; }

  .adm-wrap   { display: flex; flex-direction: column; min-height: 100vh; }

  /* Topbar */
  .adm-topbar {
    position: sticky; top: 0; z-index: 100;
    background: #0c1e2e;
    border-bottom: 2px solid #ea5649;
    padding: 0 24px;
    height: 58px;
    display: flex; align-items: center; gap: 20px;
  }
  .adm-brand {
    font-size: .72rem; font-weight: 900; letter-spacing: .2em;
    text-transform: uppercase; color: #ea5649; text-decoration: none;
    white-space: nowrap;
  }
  .adm-brand span { color: rgba(255,255,255,.45); margin-left: 8px; font-weight: 500; letter-spacing: 0; }
  .adm-topnav {
    display: flex; align-items: center; gap: 4px; flex: 1;
  }
  .adm-topnav a {
    padding: 6px 14px; border-radius: 6px;
    font-size: .78rem; font-weight: 600; text-decoration: none;
    color: rgba(255,255,255,.55); transition: background .15s, color .15s;
    white-space: nowrap;
  }
  .adm-topnav a:hover  { background: rgba(255,255,255,.07); color: #fff; }
  .adm-topnav a.active { background: rgba(234,86,73,.18); color: #f07066; }
  .adm-topright {
    display: flex; align-items: center; gap: 12px; margin-left: auto;
  }
  .adm-topright .adm-user-chip {
    font-size: .75rem; color: rgba(255,255,255,.45); font-weight: 600;
  }
  .adm-topright a {
    font-size: .75rem; font-weight: 700; text-decoration: none; padding: 6px 14px;
    border-radius: 6px; color: rgba(255,255,255,.6); border: 1px solid rgba(255,255,255,.15);
    transition: background .15s, color .15s;
  }
  .adm-topright a:hover { background: rgba(255,255,255,.08); color: #fff; }
  .adm-topright .adm-link-site {
    color: #7ab8e8; border-color: rgba(122,184,232,.3);
  }

  /* Main */
  .adm-main {
    flex: 1;
    max-width: 1300px;
    width: 100%;
    margin: 0 auto;
    padding: 32px 24px 64px;
  }

  /* Page header */
  .adm-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; margin-bottom: 28px; flex-wrap: wrap;
  }
  .adm-page-title {
    font-size: 1.6rem; font-weight: 900; color: #0c1e2e; letter-spacing: -.5px;
    margin: 0; line-height: 1.2;
  }
  .adm-page-sub { font-size: .82rem; color: #6b7f96; margin: 4px 0 0; }
  .adm-page-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

  /* Boutons admin */
  .btn-adm {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 8px;
    font-size: .84rem; font-weight: 700; text-decoration: none; cursor: pointer;
    border: none; font-family: 'Inter', sans-serif; transition: opacity .15s, transform .1s;
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
    padding: 24px;
    margin-bottom: 20px;
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
  .adm-stat-value.coral  { color: #ea5649; }
  .adm-stat-value.green  { color: #2a9d5c; }
  .adm-stat-value.blue   { color: #12314e; }
  .adm-stat-value.amber  { color: #C9962A; }

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
  .badge-active     { background: rgba(42,157,92,.12);  color: #1a7a42; }
  .badge-draft      { background: rgba(201,150,42,.12); color: #8a6020; }
  .badge-archived   { background: rgba(107,127,150,.12);color: #4a5f73; }
  .badge-closed     { background: rgba(107,127,150,.12);color: #4a5f73; }
  .badge-pending    { background: rgba(14,165,233,.12); color: #0369a1; }
  .badge-validated  { background: rgba(42,157,92,.12);  color: #1a7a42; }
  .badge-auto_validated { background: rgba(42,157,92,.1); color: #2a9d5c; }
  .badge-rejected   { background: rgba(234,86,73,.12);  color: #c0392b; }
  .badge-manual     { background: rgba(201,150,42,.12); color: #8a6020; }
  .badge-auto       { background: rgba(42,157,92,.1);   color: #2a9d5c; }
  .badge-hybrid     { background: rgba(14,165,233,.1);  color: #0369a1; }

  /* Alerts flash */
  .adm-flash {
    border-radius: 10px; padding: 14px 18px;
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 20px; font-size: .88rem; font-weight: 600;
  }
  .adm-flash-ok     { background: rgba(42,157,92,.1);  border: 1px solid rgba(42,157,92,.25); color: #1a7a42; }
  .adm-flash-err    { background: rgba(234,86,73,.08); border: 1px solid rgba(234,86,73,.25); color: #c0392b; }
  .adm-flash-info   { background: rgba(14,165,233,.08);border: 1px solid rgba(14,165,233,.2); color: #0369a1; }

  /* Form */
  .adm-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
  .adm-form-full { grid-column: 1 / -1; }
  .adm-field { display: flex; flex-direction: column; gap: 6px; }
  .adm-label { font-size: .76rem; font-weight: 700; color: #3d5166; }
  .adm-label span { color: #ea5649; }
  .adm-input, .adm-select, .adm-textarea {
    padding: 10px 14px; border-radius: 8px;
    border: 1.5px solid #d0cbc5; font-family: 'Inter', sans-serif;
    font-size: .88rem; color: #0f1e2d; background: #fff;
    transition: border-color .15s;
    width: 100%;
  }
  .adm-input:focus, .adm-select:focus, .adm-textarea:focus {
    outline: none; border-color: #ea5649; box-shadow: 0 0 0 3px rgba(234,86,73,.1);
  }
  .adm-textarea { resize: vertical; min-height: 100px; }
  .adm-hint { font-size: .72rem; color: #6b7f96; }

  /* Filters bar */
  .adm-filters {
    display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    margin-bottom: 18px;
  }
  .adm-filters select, .adm-filters input {
    padding: 8px 12px; border-radius: 7px;
    border: 1.5px solid #d0cbc5; font-family: 'Inter', sans-serif;
    font-size: .82rem; background: #fff; color: #0f1e2d;
  }
  .adm-filters select:focus, .adm-filters input:focus {
    outline: none; border-color: #ea5649;
  }

  /* Detail rows */
  .adm-detail-row {
    display: flex; gap: 12px; justify-content: space-between;
    padding: 11px 0; border-bottom: 1px solid #f0ece7; font-size: .88rem;
  }
  .adm-detail-row:last-child { border-bottom: none; }
  .adm-detail-label { color: #6b7f96; font-weight: 600; white-space: nowrap; min-width: 160px; }
  .adm-detail-value { color: #0f1e2d; font-weight: 700; text-align: right; }

  /* Empty state */
  .adm-empty {
    text-align: center; padding: 56px 24px; color: #6b7f96;
  }
  .adm-empty-icon { font-size: 3rem; margin-bottom: 12px; }
  .adm-empty p { font-size: .9rem; margin: 0; }

  @media (max-width: 700px) {
    .adm-topnav { display: none; }
    .adm-main { padding: 20px 14px 48px; }
    .adm-stats { grid-template-columns: 1fr 1fr; }
  }
  </style>
</head>
<body>
<div class="adm-wrap">
<!-- Topbar -->
<header class="adm-topbar">
  <a class="adm-brand" href="index.php">
    Zone85 <span>Admin</span>
  </a>
  <nav class="adm-topnav">
    <a href="dashboard.php"      <?= ($admin_current ?? '') === 'dashboard'     ? 'class="active"' : '' ?>>Dashboard</a>
    <a href="missions.php"       <?= ($admin_current ?? '') === 'missions'      ? 'class="active"' : '' ?>>Missions</a>
    <a href="participations.php" <?= ($admin_current ?? '') === 'participations'? 'class="active"' : '' ?>>Participations</a>
    <a href="users.php"          <?= ($admin_current ?? '') === 'users'         ? 'class="active"' : '' ?>>Utilisateurs</a>
    <a href="seasons.php"        <?= ($admin_current ?? '') === 'seasons'       ? 'class="active"' : '' ?>>🗓 Saisons</a>
    <a href="clans.php"          <?= ($admin_current ?? '') === 'clans'         ? 'class="active"' : '' ?>>🛡 Clans</a>
    <a href="events.php"         <?= ($admin_current ?? '') === 'events'        ? 'class="active"' : '' ?>>⚡ Flash</a>
    <a href="badges.php"         <?= ($admin_current ?? '') === 'badges'        ? 'class="active"' : '' ?>>🏅 Badges</a>
    <a href="echos.php"          <?= ($admin_current ?? '') === 'echos'         ? 'class="active"' : '' ?>>📰 Échos</a>
    <a href="ktc.php"            <?= ($admin_current ?? '') === 'ktc'           ? 'class="active"' : '' ?>>🥐 KTC</a>
    <a href="settings.php"       <?= ($admin_current ?? '') === 'settings'      ? 'class="active"' : '' ?>>⚙️ Settings</a>
  </nav>
  <div class="adm-topright">
    <span class="adm-user-chip">👤 <?= htmlspecialchars($_adm_user['pseudo'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="<?= $_adm_base ?>/index.php" class="adm-link-site" target="_blank">← Site</a>
    <a href="<?= $_adm_base ?>/logout.php" onclick="return confirm('Se déconnecter ?')">Déconnexion</a>
  </div>
</header>
<!-- Main -->
<main class="adm-main">
