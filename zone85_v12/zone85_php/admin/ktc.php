<?php
// admin/ktc.php — LEGACY NOTICE
// Ce fichier remplace l'ancien module question-bank KTC.
// Le nouveau systeme editorial (ktc_episodes) sera administrable via admin/ktc-episodes.php (V12).
$admin_current    = 'ktc';
$admin_page_title = 'KTC Editorial — V12';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_admin();
require_once '_admin-header.php';
?>
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">&#x1F950; KTC Editorial</h1>
    <p class="adm-page-sub">Keto Kole Tche — Rubrique editoriale mensuelle premium</p>
  </div>
</div>

<div class="adm-card" style="border-left:4px solid #C9962A;background:rgba(201,150,42,.04)">
  <div class="adm-card-title" style="color:#8a6020">&#x1F6A7; Module en cours de developpement (V12)</div>
  <p style="font-size:.92rem;color:#0f1e2d;line-height:1.7;margin-bottom:20px">
    Le module KTC editorial est en cours de developpement pour la V12.<br>
    Il permettra de creer et gerer les episodes mensuels : objet mystere, brocanteur, photos, votes et revelation.
  </p>
  <div style="background:#f8f4ef;border-radius:10px;padding:18px 20px;margin-bottom:20px">
    <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7f96;margin-bottom:12px">
      Structure V12 prevue
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.84rem;color:#3d5166">
      <div>&#x2705; admin/ktc-episodes.php — Creer un episode</div>
      <div>&#x2705; admin/ktc-photos.php — Photos par semaine</div>
      <div>&#x2705; admin/ktc-propositions.php — Gerer les propositions</div>
      <div>&#x2705; Statut automatique semaine 1/2/3/revelation</div>
    </div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="dashboard.php" class="btn-adm btn-adm-primary">Retour au dashboard</a>
    <a href="../ktc.php" target="_blank" class="btn-adm btn-adm-ghost">Voir la page KTC publique</a>
  </div>
</div>

<?php require_once '_admin-footer.php'; ?>