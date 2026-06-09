<?php
// ============================================================
// mission.php — Détail d'une mission + participation V1
// ============================================================
$current_page = 'missions';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';
require_once 'includes/auth.php';

// ── Charger la mission ────────────────────────────────────────
$mission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($mission_id <= 0) {
    header('Location: missions.php');
    exit;
}

$mission = db_enabled() ? fetch_mission_by_id($mission_id) : null;
if (!$mission) {
    header('HTTP/1.1 404 Not Found');
    $page_title = 'Mission introuvable';
    require_once 'includes/header.php';
    require_once 'includes/nav.php';
    ?>
    <div style="max-width:640px;margin:120px auto;padding:0 24px;text-align:center">
      <div style="font-size:3rem;margin-bottom:16px">🗺️</div>
      <h1 style="font-size:1.6rem;font-weight:900;color:var(--navy-dark);margin-bottom:12px">Mission introuvable</h1>
      <p style="color:var(--text-muted);margin-bottom:28px">Cette mission n'existe pas ou n'est plus disponible.</p>
      <a href="missions.php" class="btn btn-primary">Voir toutes les missions</a>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

$page_title       = $mission['title'];
$page_description = 'Mission Zone85 — ' . ($mission['description'] ?? '');
$page_robots      = 'noindex,follow';
$page_canonical   = null;

// ── Auth + état participation ─────────────────────────────────
$is_logged_in        = is_logged_in();
$current_user_data   = $is_logged_in ? current_user() : null;
$already_participated = false;

if ($is_logged_in && $current_user_data) {
    $already_participated = has_user_participated((int)$current_user_data['id'], $mission_id);
}

// ── Traitement POST ───────────────────────────────────────────
$flash_success = null;
$flash_error   = null;

// Après redirection POST/GET, afficher message succès
if (isset($_GET['done']) && $_GET['done'] === '1') {
    $xp_done = (int)($_GET['xp'] ?? 0);
    if ($xp_done === 0 && ($mission['validation_mode'] ?? '') === 'manual') {
        $flash_success = 'Participation envoyée. L\'équipe Zone85 va la vérifier sous 24–48h.';
    } else {
        $flash_success = 'Participation enregistrée ! Tes XP ont été crédités.';
    }
}
if (isset($_GET['err'])) {
    $flash_error = match($_GET['err']) {
        'duplicate' => 'Tu as déjà participé à cette mission.',
        'inactive'  => 'Cette mission n\'est plus active.',
        'csrf'      => 'Token de sécurité invalide. Recharge la page.',
        default     => 'Une erreur est survenue. Réessaie.',
    };
}

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'participate') {
    // Vérification CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: mission.php?id=' . $mission_id . '&err=csrf');
        exit;
    }
    // Mission active ?
    if ($mission['status'] !== 'active') {
        header('Location: mission.php?id=' . $mission_id . '&err=inactive');
        exit;
    }
    // Doublon ?
    if ($already_participated) {
        header('Location: mission.php?id=' . $mission_id . '&err=duplicate');
        exit;
    }

    $extra = [];
    if (!empty($_POST['answer_text'])) {
        $extra['answer_text'] = safe_input($_POST['answer_text'], 1000);
    }
    if (!empty($_POST['selected_option_id']) && ctype_digit($_POST['selected_option_id'])) {
        $extra['selected_option_id'] = (int)$_POST['selected_option_id'];
    }

    $result = create_participation((int)$current_user_data['id'], $mission_id, $extra);

    if ($result['ok']) {
        // Rafraîchir XP en session
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['xp_total'] = $result['new_xp_total'];
            if (function_exists('get_user_level_from_xp')) {
                $_SESSION['user']['level'] = get_user_level_from_xp($result['new_xp_total']);
            }
        }
        header('Location: mission.php?id=' . $mission_id . '&done=1&xp=' . $result['xp_awarded']);
        exit;
    } else {
        if (defined('APP_ENV') && APP_ENV === 'dev' && !empty($result['debug_error'])) {
            error_log('[ZONE85 mission.php] ' . $result['debug_error']);
        }
        $flash_error = $result['error'] ?? 'Une erreur est survenue.';
    }
}

// Récupérer XP affichés après redirection
$xp_shown = isset($_GET['xp']) ? (int)$_GET['xp'] : (int)($mission['xp_participation'] ?: 5);

// Hidden Hunt : progression utilisateur
$is_hidden_hunt = $mission['mission_type'] === 'hidden_hunt';
$hh_found = 0; $hh_total = 0; $hh_completed = false;
if ($is_hidden_hunt && $is_logged_in && $current_user_data) {
    $hh_found     = count_user_collectibles((int)$current_user_data['id'], $mission_id);
    $hh_total     = count_mission_collectibles($mission_id);
    $hh_completed = ($hh_total > 0 && $hh_found >= $hh_total);
}
if ($is_hidden_hunt && !$is_logged_in) {
    $hh_total = count_mission_collectibles($mission_id);
}

// Collectibles sur cette page (pour le composant)
$hh_collectibles_mission = $mission_id;

// Helpers locaux
$type_icon  = function_exists('mission_type_icon')  ? mission_type_icon($mission['mission_type'])  : '📌';
$type_label = function_exists('mission_type_label') ? mission_type_label($mission['mission_type']) : $mission['mission_type'];

$status_labels = ['active' => 'En cours', 'draft' => 'Brouillon', 'closed' => 'Terminée', 'archived' => 'Archivée'];
$status_classes = ['active' => 'status-en-cours', 'draft' => 'status-a-venir', 'closed' => 'status-termine', 'archived' => 'status-termine'];

$validation_labels = ['auto' => 'Automatique', 'manual' => 'Validation équipe (24–48h)', 'hybrid' => 'Hybride'];

$is_active    = $mission['status'] === 'active';
$is_pending   = $mission['validation_mode'] === 'manual' || $mission['validation_mode'] === 'hybrid';
$xp_part      = (int)$mission['xp_participation'];
$xp_success   = (int)$mission['xp_success'];
$clan_pts     = (int)$mission['clan_points_participation'];
$end_date_fmt = !empty($mission['end_date']) ? date('j F Y', strtotime($mission['end_date'])) : null;

$page_styles = '<style>
/* ── MISSION DETAIL PAGE ── */
.mission-detail-wrap {
  padding-top: 68px;
  min-height: 100vh;
  background: var(--beige);
}
/* .mission-detail-hero — fond depuis zone85.css, padding réduit pour page détail */
.mission-detail-hero { padding: 80px 0 56px; }
.mission-detail-hero-inner { position: relative; z-index: 1; }
.mission-breadcrumb {
  font-size: .75rem;
  color: rgba(255,255,255,.45);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.mission-breadcrumb a {
  color: rgba(255,255,255,.45);
  text-decoration: none;
  transition: color .2s;
}
.mission-breadcrumb a:hover { color: rgba(255,255,255,.75); }
.mission-type-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(234,86,73,.18);
  border: 1px solid rgba(234,86,73,.35);
  color: #f07066;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: 5px 14px;
  border-radius: 4px;
  margin-bottom: 16px;
}
.mission-detail-title {
  font-size: clamp(1.8rem,4vw,2.8rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1px;
  line-height: 1.1;
  margin-bottom: 14px;
}
.mission-detail-desc {
  font-size: .97rem;
  color: rgba(255,255,255,.6);
  line-height: 1.75;
  max-width: 640px;
  margin-bottom: 24px;
}
.mission-meta-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.mission-meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 4px;
  font-size: .72rem;
  font-weight: 700;
}
.pill-xp     { background: rgba(234,86,73,.15); border:1px solid rgba(234,86,73,.3); color:#f07066; }
.pill-clan   { background: rgba(201,150,42,.15); border:1px solid rgba(201,150,42,.3); color:#d4a43a; }
.pill-status { background: rgba(42,157,92,.15);  border:1px solid rgba(42,157,92,.3);  color:#2a9d5c; }
.pill-date   { background: rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.55); }
.pill-pending{ background: rgba(14,165,233,.12); border:1px solid rgba(14,165,233,.25);color:#0ea5e9; }

/* ── MAIN LAYOUT ── */
.mission-detail-body {
  max-width: 900px;
  margin: 0 auto;
  padding: 40px 24px 80px;
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 28px;
  align-items: start;
}
.mission-detail-main {}
.mission-detail-aside {}

/* ── FLASH ── */
.flash-success {
  background: rgba(42,157,92,.12);
  border: 1.5px solid rgba(42,157,92,.3);
  border-radius: var(--radius);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  font-size: .9rem;
  font-weight: 700;
  color: #1a7a42;
}
.flash-error {
  background: rgba(234,86,73,.1);
  border: 1.5px solid rgba(234,86,73,.3);
  border-radius: var(--radius);
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
  font-size: .9rem;
  font-weight: 700;
  color: var(--primary);
}

/* ── CARDS ── */
.md-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 28px;
  margin-bottom: 20px;
}
.md-card-title {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.md-instructions {
  font-size: .9rem;
  color: var(--text-mid);
  line-height: 1.75;
}

/* ── FORM ── */
.participation-form {
  margin-top: 8px;
}
.btn-participate {
  width: 100%;
  padding: 15px 24px;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: var(--radius);
  font-size: 1rem;
  font-weight: 800;
  font-family: \'Inter\', sans-serif;
  cursor: pointer;
  transition: background .2s, transform .15s;
  letter-spacing: -.2px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-participate:hover { background: #c94038; transform: translateY(-2px); }
.btn-participate:active { transform: translateY(0); }
.btn-participate:disabled {
  background: var(--beige-dark);
  color: var(--text-muted);
  cursor: default;
  transform: none;
}
.participation-note {
  font-size: .75rem;
  color: var(--text-muted);
  text-align: center;
  margin-top: 10px;
  line-height: 1.5;
}

/* ── ALREADY PARTICIPATED ── */
.already-badge {
  width: 100%;
  padding: 15px 24px;
  background: rgba(42,157,92,.1);
  border: 2px solid rgba(42,157,92,.3);
  border-radius: var(--radius);
  font-size: .95rem;
  font-weight: 800;
  color: #1a7a42;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
/* Modal info XP mission */
.mission-info-modal{position:fixed;inset:0;z-index:99997;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(12,30,46,.6);backdrop-filter:blur(5px)}
.mission-info-modal.is-open{display:flex}
.mission-info-card{width:min(500px,96vw);background:#fff;border-radius:20px;box-shadow:0 28px 80px rgba(0,0,0,.28);overflow:hidden}
.mission-info-head{padding:20px 22px 14px;background:linear-gradient(135deg,var(--navy-dark,#0c1e2e),#1a3456);display:flex;align-items:flex-start;gap:12px}
.mission-info-head-icon{font-size:1.8rem;flex-shrink:0;margin-top:2px}
.mission-info-head h3{margin:0;font-size:1.05rem;font-weight:900;color:#fff;line-height:1.25}
.mission-info-head p{margin:5px 0 0;font-size:.8rem;color:rgba(255,255,255,.6);line-height:1.4}
.mission-info-head-close{margin-left:auto;flex-shrink:0;border:0;background:rgba(255,255,255,.12);color:#fff;width:32px;height:32px;border-radius:999px;font-size:1.2rem;cursor:pointer;font-weight:900}
.mission-info-head-close:hover{background:rgba(255,255,255,.22)}
.mission-info-body{padding:18px 20px;display:flex;flex-direction:column;gap:10px}
.mission-info-row{display:flex;align-items:flex-start;gap:12px;background:#f8f4ef;border-radius:12px;padding:12px 14px}
.mission-info-row-hl{background:#f0faf4;border:1.5px solid rgba(42,157,92,.2)}
.mission-info-row-icon{font-size:1.4rem;flex-shrink:0;margin-top:1px}
.mission-info-row strong{display:block;font-size:.88rem;font-weight:900;color:#0c1e2e;margin-bottom:3px}
.mission-info-row p{margin:0;font-size:.78rem;color:#4b6074;line-height:1.5}
.mission-info-xp{flex-shrink:0;margin-top:2px;padding:3px 9px;border-radius:999px;font-size:.7rem;font-weight:900}
.mission-info-xp-auto{background:rgba(42,157,92,.15);color:#1a7a42}
.mission-info-xp-pending{background:rgba(233,149,26,.18);color:#9a5800}
.mission-info-footer{padding:10px 20px 18px;display:flex;gap:10px}
.mission-info-btn-ok{flex:1;border:0;background:var(--primary,#ea5649);color:#fff;font-size:.88rem;font-weight:900;padding:12px;border-radius:10px;cursor:pointer;box-shadow:0 6px 18px rgba(234,86,73,.25);transition:opacity .15s}
.mission-info-btn-ok:hover{opacity:.9}
@media(max-width:480px){.mission-info-footer{flex-direction:column}}

/* ── CTA NON CONNECTÉ ── */
.login-cta-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 28px 24px;
  text-align: center;
}
.login-cta-icon {
  font-size: 2.4rem;
  display: block;
  margin-bottom: 14px;
}
.login-cta-title {
  font-size: 1rem;
  font-weight: 900;
  color: var(--navy-dark);
  margin-bottom: 8px;
  letter-spacing: -.3px;
}
.login-cta-sub {
  font-size: .82rem;
  color: var(--text-muted);
  margin-bottom: 20px;
  line-height: 1.55;
}

/* ── ASIDE INFO ── */
.mission-info-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 24px;
  margin-bottom: 16px;
}
.info-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--beige-dark);
  font-size: .85rem;
}
.info-row:first-child { padding-top: 0; }
.info-row:last-child  { border-bottom: none; padding-bottom: 0; }
.info-label { color: var(--text-muted); font-weight: 600; white-space: nowrap; }
.info-value { color: var(--text); font-weight: 700; text-align: right; }
.xp-highlight { color: var(--primary); font-weight: 900; font-size: .95rem; }
.clan-pts-highlight { color: #8a6020; font-weight: 900; }

/* ── PENDING NOTICE ── */
.pending-notice {
  background: rgba(14,165,233,.07);
  border: 1px solid rgba(14,165,233,.2);
  border-radius: var(--radius);
  padding: 14px 16px;
  font-size: .82rem;
  color: #0369a1;
  line-height: 1.6;
}

/* ── CLOSED NOTICE ── */
.closed-notice {
  background: var(--beige);
  border: 1px solid var(--beige-dark);
  border-radius: var(--radius);
  padding: 14px 16px;
  font-size: .88rem;
  color: var(--text-muted);
  text-align: center;
  font-weight: 600;
}

/* ── OPTION FORM ── */
.option-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
.option-label {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--beige);
  border-radius: var(--radius);
  border: 2px solid transparent;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  font-size: .88rem;
  font-weight: 600;
  color: var(--text);
}
.option-label:hover { border-color: var(--primary); background: rgba(234,86,73,.05); }
.option-label input[type="radio"] { accent-color: var(--primary); }
.option-label input[type="radio"]:checked + span { color: var(--primary); font-weight: 800; }
.option-label:has(input:checked) { border-color: var(--primary); background: rgba(234,86,73,.06); }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
  .mission-detail-body { grid-template-columns: 1fr; padding: 24px 16px 60px; }
  .mission-detail-aside { order: -1; }
  .mission-meta-pills { gap: 6px; }
}


/* ── ZONE85 V7.2 — Mission page polish ── */
.mission-detail-wrap{
  padding-top: 84px;
  background:
    radial-gradient(circle at 18% 8%, rgba(234,86,73,.10), transparent 30%),
    linear-gradient(180deg, #f8f4ef 0%, #f2ede7 100%);
}
.mission-detail-hero{
  padding: 76px 0 64px;
  box-shadow: inset 0 -1px 0 rgba(255,255,255,.08);
}
.mission-detail-hero::after{
  content:"";
  position:absolute;
  right:-90px;
  top:-120px;
  width:360px;
  height:360px;
  border-radius:999px;
  background:rgba(234,86,73,.16);
}
.mission-detail-title{
  max-width:860px;
  font-size:clamp(2.2rem,4.8vw,4rem);
  letter-spacing:-1.8px;
}
.mission-detail-desc{
  max-width:760px;
  font-size:1.05rem;
  color:rgba(255,255,255,.68);
}
.mission-meta-pill{
  border-radius:999px;
  padding:8px 15px;
  backdrop-filter: blur(8px);
}
.mission-detail-body{
  max-width:1120px;
  grid-template-columns:minmax(0, 720px) 340px;
  gap:34px;
  padding:54px 24px 90px;
}
.md-card,
.login-cta-card,
.mission-info-card{
  border:1px solid rgba(18,49,78,.08);
  box-shadow:0 18px 45px rgba(12,30,46,.08);
}
.md-card{
  padding:36px;
}
.login-cta-card{
  min-height:330px;
  display:flex;
  flex-direction:column;
  justify-content:center;
  padding:54px 52px;
  border-radius:24px;
  position:relative;
  overflow:hidden;
  background:
    radial-gradient(circle at 50% -10%, rgba(234,86,73,.12), transparent 34%),
    #fff;
}
.login-cta-card::before{
  content:"";
  position:absolute;
  inset:16px;
  border:1px solid rgba(18,49,78,.06);
  border-radius:20px;
  pointer-events:none;
}
.login-cta-icon{
  width:76px;
  height:76px;
  margin:0 auto 22px;
  display:grid;
  place-items:center;
  border-radius:24px;
  background:linear-gradient(135deg, rgba(234,86,73,.16), rgba(18,49,78,.08));
  font-size:2.6rem;
}
.login-cta-title{
  font-size:1.45rem;
  letter-spacing:-.6px;
  color:#0d1e2c;
}
.login-cta-sub{
  max-width:520px;
  margin:0 auto 26px;
  font-size:.96rem;
  line-height:1.7;
}
.login-cta-card .btn{
  min-height:54px;
  border-radius:10px;
  font-size:1rem;
  font-weight:900;
}
.mission-info-card{
  padding:28px;
  border-radius:22px;
}
.mission-detail-aside{
  position:sticky;
  top:104px;
}
.info-row{
  padding:13px 0;
  font-size:.92rem;
}
.info-value{
  max-width:170px;
  line-height:1.35;
}
.xp-highlight{font-size:1.08rem}
.mission-info-card:nth-child(2){
  background:linear-gradient(180deg,#fff,#fff7f3);
}
.flash-success,
.flash-error{
  border-radius:18px;
  padding:18px 22px;
}
.btn-participate{
  min-height:58px;
  border-radius:12px;
  font-size:1.05rem;
  box-shadow:0 12px 24px rgba(234,86,73,.22);
}
.already-badge{
  min-height:58px;
  border-radius:14px;
}
@media (max-width: 900px){
  .mission-detail-body{grid-template-columns:1fr;max-width:760px;padding:34px 18px 70px}
  .mission-detail-aside{position:static;order:0}
  .login-cta-card{padding:40px 24px}
}

</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';

// Charger les options si quiz/vote
$mission_options = [];
if (in_array($mission['mission_type'], ['quiz','vote']) && db_enabled()) {
    try {
        $pdo = db();
        if ($pdo) {
            $optStmt = $pdo->prepare("SELECT * FROM mission_options WHERE mission_id = :id ORDER BY sort_order ASC");
            $optStmt->execute([':id' => $mission_id]);
            $mission_options = $optStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log('[ZONE85] mission_options load : ' . $e->getMessage());
    }
}
?>

<div class="mission-detail-wrap">

  <!-- HERO ───────────────────────────────────────────── -->
  <section class="mission-detail-hero">
    <div class="container mission-detail-hero-inner">

      <div class="mission-breadcrumb">
        <a href="missions.php">Missions</a>
        <span>›</span>
        <span><?= e($type_label) ?></span>
      </div>

      <div class="mission-type-hero-badge">
        <?= $type_icon ?> <?= e($type_label) ?>
      </div>

      <h1 class="mission-detail-title"><?= e($mission['title']) ?></h1>

      <?php if (!empty($mission['description'])): ?>
      <p class="mission-detail-desc"><?= e($mission['description']) ?></p>
      <?php endif; ?>

      <div class="mission-meta-pills">
        <?php if ($xp_part > 0): ?>
        <span class="mission-meta-pill pill-xp">⚡ +<?= $xp_part ?> XP participation</span>
        <?php endif; ?>
        <?php if ($xp_success > 0): ?>
        <span class="mission-meta-pill pill-xp">🎯 +<?= $xp_success ?> XP réussite</span>
        <?php endif; ?>
        <?php if ($clan_pts > 0): ?>
        <span class="mission-meta-pill pill-clan">🛡️ +<?= $clan_pts ?> pts clan</span>
        <?php endif; ?>
        <?php if ($is_active): ?>
        <span class="mission-meta-pill pill-status">● En cours</span>
        <?php endif; ?>
        <?php if ($end_date_fmt): ?>
        <span class="mission-meta-pill pill-date">Jusqu'au <?= e($end_date_fmt) ?></span>
        <?php endif; ?>
        <?php if ($is_pending): ?>
        <span class="mission-meta-pill pill-pending">👀 Validation équipe</span>
        <?php endif; ?>
      </div>

    </div>
  </section>

  <!-- BODY ────────────────────────────────────────────── -->
  <div class="mission-detail-body">

    <!-- ── COLONNE PRINCIPALE ── -->
    <div class="mission-detail-main">

      <?php if ($flash_success): ?>
      <div class="flash-success">
        <span>✅</span>
        <div>
          <strong>Bravo !</strong> <?= e($flash_success) ?>
          <?php if (isset($_GET['xp']) && (int)$_GET['xp'] > 0): ?>
          — <strong>+<?= (int)$_GET['xp'] ?> XP</strong> crédités sur ton profil.
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($flash_error): ?>
      <div class="flash-error">
        <span>⚠️</span> <?= e($flash_error) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($mission['instructions'])): ?>
      <div class="md-card">
        <div class="md-card-title">📋 Consignes de participation</div>
        <p class="md-instructions"><?= nl2br(e($mission['instructions'])) ?></p>
      </div>
      <?php endif; ?>

      <!-- ── FORMULAIRE OU ÉTAT ── -->
      <?php if ($is_hidden_hunt && $is_active): ?>
      <!-- ── HIDDEN HUNT ─────────────────────────────────── -->
      <?php if (!$is_logged_in): ?>
      <div class="login-cta-card">
        <span class="login-cta-icon">🗝️</span>
        <div class="login-cta-title">Connecte-toi pour chasser !</div>
        <p class="login-cta-sub">Des objets sont cachés sur les pages du site. Trouve-les tous pour gagner des XP et contribuer à ton clan.</p>
        <div style="display:flex;flex-direction:column;gap:10px;max-width:280px;margin:0 auto">
          <a href="login.php" class="btn btn-primary" style="text-align:center">Se connecter →</a>
          <a href="inscription.php" class="btn btn-ghost" style="text-align:center">Rejoindre la Zone</a>
        </div>
        <?php if ($hh_total > 0): ?>
        <p style="margin-top:14px;font-size:.78rem;color:var(--text-muted)"><?= $hh_total ?> objet<?= $hh_total > 1 ? 's' : '' ?> cachés sur le site</p>
        <?php endif; ?>
      </div>

      <?php elseif ($hh_completed): ?>
      <div class="md-card" style="text-align:center">
        <div style="font-size:3rem;margin-bottom:12px">🏆</div>
        <div class="already-badge" style="margin-bottom:14px">🎉 Tu as trouvé tous les objets !</div>
        <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:16px">
          Chasse accomplie : <strong><?= $hh_found ?> / <?= $hh_total ?></strong> objets trouvés.
        </p>
        <a href="profil.php" style="color:var(--primary);font-weight:700;text-decoration:none;font-size:.88rem">Voir mes trophées →</a>
      </div>

      <?php else: ?>
      <div class="md-card">
        <div class="md-card-title">🗝️ Jeu de piste — ta progression</div>

        <?php if ($is_logged_in && $hh_total > 0): ?>
        <div style="margin-bottom:20px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-size:.85rem;font-weight:700;color:var(--text)">Objets trouvés</span>
            <span style="font-size:.95rem;font-weight:900;color:var(--primary)"><?= $hh_found ?> / <?= $hh_total ?></span>
          </div>
          <div style="height:10px;background:#f0ece7;border-radius:8px;overflow:hidden">
            <div style="height:100%;background:linear-gradient(90deg,#c94038,#ea5649);border-radius:8px;width:<?= $hh_total > 0 ? round($hh_found / $hh_total * 100) : 0 ?>%;transition:width .8s cubic-bezier(.22,1,.36,1)"></div>
          </div>
        </div>
        <?php endif; ?>

        <div style="background:rgba(234,86,73,.06);border:1px solid rgba(234,86,73,.15);border-radius:12px;padding:16px 18px;margin-bottom:18px">
          <p style="font-size:.88rem;color:var(--text-mid);line-height:1.65;margin:0">
            👁 Des objets sont <strong>cachés sur les pages du site</strong>. Explore, cherche bien, et clique dessus pour les collecter.
          </p>
        </div>

        <?php if ((int)$xp_success > 0): ?>
        <div style="background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.85rem;font-weight:700;color:#c94038;text-align:center">
          ⚡ <?= (int)$xp_success ?> XP attribués quand la chasse est terminée
        </div>
        <?php endif; ?>

        <?php if (!empty($mission['instructions'])): ?>
        <div style="font-size:.85rem;color:var(--text-mid);line-height:1.7;white-space:pre-line"><?= e($mission['instructions']) ?></div>
        <?php endif; ?>

        <a href="index.php" class="btn-participate" style="margin-top:18px;text-decoration:none;display:flex">
          🗺️ <?= $hh_found > 0 ? 'Continuer la chasse' : 'Commencer la chasse' ?>
        </a>
        <p class="participation-note">Visite chaque page du site — les objets peuvent être n'importe où.</p>
      </div>
      <?php endif; ?>

      <?php elseif (!$is_active): ?>
      <!-- Mission non active -->
      <div class="md-card">
        <div class="closed-notice">
          <?php
          $closed_msg = [
              'draft'    => '🔒 Cette mission n\'est pas encore ouverte.',
              'closed'   => '🏁 Cette mission est terminée.',
              'archived' => '📦 Cette mission est archivée.',
          ];
          echo e($closed_msg[$mission['status']] ?? 'Cette mission n\'est plus disponible.');
          ?>
          <br><a href="missions.php" style="color:var(--primary);font-weight:700;text-decoration:none;margin-top:10px;display:inline-block">← Voir toutes les missions</a>
        </div>
      </div>

      <?php elseif (!$is_logged_in): ?>
      <!-- Non connecté -->
      <div class="login-cta-card">
        <span class="login-cta-icon">🛡️</span>
        <div class="login-cta-title">Connecte-toi pour participer</div>
        <p class="login-cta-sub">Rejoins la Zone85, gagne des XP et fais progresser ton clan en participant à cette mission.</p>
        <div style="display:flex;flex-direction:column;gap:10px;max-width:280px;margin:0 auto">
          <a href="login.php" class="btn btn-primary" style="text-align:center">Se connecter →</a>
          <a href="inscription.php" class="btn btn-ghost" style="text-align:center">Rejoindre la Zone</a>
        </div>
        <p style="margin-top:16px;font-size:.75rem;color:var(--text-muted);font-style:italic">"Je progresse pour moi. Je fais gagner mon clan."</p>
      </div>

      <?php elseif ($already_participated): ?>
      <!-- Déjà participé -->
      <div class="md-card">
        <div class="already-badge">
          ✅ Tu as déjà participé à cette mission
        </div>
        <p style="text-align:center;font-size:.82rem;color:var(--text-muted);margin-top:14px">
          Tes XP et ta contribution au clan ont bien été enregistrés.
          <a href="profil.php" style="color:var(--primary);font-weight:700;text-decoration:none">Voir mon profil →</a>
        </p>
      </div>

      <?php elseif (!$is_hidden_hunt): ?>
      <!-- Formulaire participation -->
      <div class="md-card">
        <div class="md-card-title">🎯 Ta participation</div>

        <?php if ($is_pending): ?>
        <div class="pending-notice" style="margin-bottom:18px">
          👀 <strong>Validation équipe :</strong> ta participation sera examinée sous 24–48h. Tes XP de participation te seront attribués après validation.
        </div>
        <?php endif; ?>

        <form method="POST" class="participation-form" id="participate-form">
          <input type="hidden" name="action" value="participate">
          <?= csrf_field() ?>

          <?php if (!empty($mission_options) && in_array($mission['mission_type'], ['quiz','vote'])): ?>
          <!-- Options quiz/vote -->
          <div class="md-card-title" style="margin-bottom:12px">Choisis ta réponse :</div>
          <div class="option-list">
            <?php foreach ($mission_options as $opt): ?>
            <label class="option-label">
              <input type="radio" name="selected_option_id" value="<?= (int)$opt['id'] ?>" required>
              <span><?= e($opt['label']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <button type="submit" class="btn-participate" id="participate-btn">
            ⚡ Je participe à cette mission
          </button>
        </form>

        <p class="participation-note">
          <?php if ($xp_part > 0): ?>
          Tu gagneras <strong>+<?= $xp_part ?> XP</strong> immédiatement.<?= $clan_pts > 0 ? ' Ton clan recevra aussi <strong>+' . $clan_pts . ' pts</strong>.' : '' ?>
          <?php else: ?>
          Ta participation sera enregistrée et validée par l'équipe.
          <?php endif; ?>
        </p>
      </div>
      <?php endif; ?>

    </div><!-- /main -->

    <!-- ── COLONNE ASIDE ── -->
    <div class="mission-detail-aside">

      <!-- Fiche mission -->
      <div class="mission-info-card">
        <div class="md-card-title" style="margin-bottom:12px">📋 Fiche mission</div>

        <div class="info-row">
          <span class="info-label">Type</span>
          <span class="info-value"><?= $type_icon ?> <?= e($type_label) ?></span>
        </div>

        <div class="info-row">
          <span class="info-label">Statut</span>
          <span class="info-value">
            <span class="mission-status <?= e($status_classes[$mission['status']] ?? 'status-a-venir') ?>">
              <?= e($status_labels[$mission['status']] ?? $mission['status']) ?>
            </span>
          </span>
        </div>

        <div class="info-row">
          <span class="info-label">Validation</span>
          <span class="info-value"><?= e($validation_labels[$mission['validation_mode']] ?? $mission['validation_mode']) ?></span>
        </div>

        <?php if ($xp_part > 0): ?>
        <div class="info-row">
          <span class="info-label">XP participation</span>
          <span class="info-value xp-highlight">+<?= $xp_part ?> XP</span>
        </div>
        <?php endif; ?>

        <?php if ($xp_success > 0): ?>
        <div class="info-row">
          <span class="info-label">XP réussite</span>
          <span class="info-value xp-highlight">+<?= $xp_success ?> XP</span>
        </div>
        <?php endif; ?>

        <?php if ($clan_pts > 0): ?>
        <div class="info-row">
          <span class="info-label">Points clan</span>
          <span class="info-value clan-pts-highlight">+<?= $clan_pts ?> pts</span>
        </div>
        <?php endif; ?>

        <?php if ($mission['is_collective']): ?>
        <div class="info-row">
          <span class="info-label">Mode</span>
          <span class="info-value">🛡️ Collectif</span>
        </div>
        <?php endif; ?>

        <?php if ($end_date_fmt): ?>
        <div class="info-row">
          <span class="info-label">Fin</span>
          <span class="info-value"><?= e($end_date_fmt) ?></span>
        </div>
        <?php endif; ?>

      </div><!-- /mission-info-card -->

      <!-- XP à vie -->
      <div class="mission-info-card">
        <div class="md-card-title" style="margin-bottom:10px">⚡ XP à vie</div>
        <p style="font-size:.82rem;color:var(--text-muted);line-height:1.6;margin:0">
          Tes XP de participation sont permanents. Ils gardent la trace de ton parcours dans le QG.
        </p>
        <?php if ($clan_pts > 0): ?>
        <p style="font-size:.82rem;color:var(--text-muted);line-height:1.6;margin-top:10px;padding-top:10px;border-top:1px solid var(--beige-dark)">
          🛡️ Les points clan comptent pour le <strong>classement annuel des clans</strong>.
        </p>
        <?php endif; ?>
      </div>

      <a href="missions.php" style="display:block;text-align:center;font-size:.82rem;color:var(--text-muted);text-decoration:none;font-weight:600;padding:8px">
        ← Toutes les missions
      </a>

    </div><!-- /aside -->

  </div><!-- /body -->
</div>

<?php
render_hidden_collectibles('mission');

// Modal info participation (non-connecté ou déjà participé → pas affiché)
if ($is_logged_in && !$already_participated): ?>
<div id="missionInfoModal" class="mission-info-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="missionInfoTitle">
  <div class="mission-info-card">
    <div class="mission-info-head">
      <span class="mission-info-head-icon">&#x1F3AF;</span>
      <div>
        <h3 id="missionInfoTitle">Comment gagner des XP sur cette mission ?</h3>
        <p><?= e($mission['title']) ?></p>
      </div>
      <button class="mission-info-head-close" id="closeMissionInfo" aria-label="Fermer">&#xD7;</button>
    </div>
    <div class="mission-info-body">
      <?php if ($xp_part > 0): ?>
      <div class="mission-info-row <?= ($mission['validation_mode'] === 'auto') ? '' : 'mission-info-row-hl' ?>">
        <div class="mission-info-row-icon">&#x26A1;</div>
        <div>
          <strong>XP de participation</strong>
          <p><?php if ($mission['validation_mode'] === 'auto'): ?>
            Attribués <strong>automatiquement</strong> dès l'envoi de ta participation.
          <?php else: ?>
            Attribués <strong>après validation</strong> par l'équipe Zone85 (24–48&thinsp;h).
          <?php endif; ?></p>
        </div>
        <span class="mission-info-xp <?= ($mission['validation_mode'] === 'auto') ? 'mission-info-xp-auto' : 'mission-info-xp-pending' ?>">
          +<?= $xp_part ?>&thinsp;XP
        </span>
      </div>
      <?php endif; ?>
      <?php if ($xp_success > 0): ?>
      <div class="mission-info-row mission-info-row-hl">
        <div class="mission-info-row-icon">&#x1F3C6;</div>
        <div>
          <strong>Bonus réussite</strong>
          <p>Si ta réponse est retenue comme correcte, tu empoches un bonus supplémentaire.</p>
        </div>
        <span class="mission-info-xp mission-info-xp-auto">+<?= $xp_success ?>&thinsp;XP</span>
      </div>
      <?php endif; ?>
      <?php if ($clan_pts > 0): ?>
      <div class="mission-info-row">
        <div class="mission-info-row-icon">&#x1F6E1;</div>
        <div>
          <strong>Points clan</strong>
          <p>Ta participation rapporte aussi des points à ton clan pour le classement annuel.</p>
        </div>
        <span class="mission-info-xp mission-info-xp-auto">+<?= $clan_pts ?>&thinsp;pts</span>
      </div>
      <?php endif; ?>
    </div>
    <div class="mission-info-footer">
      <button id="closeMissionInfoBtn" class="mission-info-btn-ok" style="background:#4b6074">Fermer</button>
      <button id="goParticipateBtn" class="mission-info-btn-ok">Participer &#x2192;</button>
    </div>
  </div>
</div>
<?php endif; ?>
<?php

$page_scripts = '<script>
' . ($is_logged_in && !$already_participated ? '
(function(){
  var modal = document.getElementById("missionInfoModal");
  if (!modal) return;
  var key = "mission_info_seen_' . (int)$mission_id . '";
  if (!sessionStorage.getItem(key)) {
    setTimeout(function(){ modal.classList.add("is-open"); modal.setAttribute("aria-hidden","false"); }, 600);
  }
  function closeModal(){ modal.classList.remove("is-open"); modal.setAttribute("aria-hidden","true"); sessionStorage.setItem(key,"1"); }
  document.getElementById("closeMissionInfo") && document.getElementById("closeMissionInfo").addEventListener("click", closeModal);
  document.getElementById("closeMissionInfoBtn") && document.getElementById("closeMissionInfoBtn").addEventListener("click", closeModal);
  document.getElementById("goParticipateBtn") && document.getElementById("goParticipateBtn").addEventListener("click", function(){
    closeModal();
    var f = document.getElementById("participate-form");
    if (f) { f.scrollIntoView({behavior:"smooth",block:"center"}); }
  });
  modal.addEventListener("click", function(ev){ if(ev.target===modal) closeModal(); });
  document.addEventListener("keydown", function(ev){ if(ev.key==="Escape") closeModal(); });
})();
' : '') . '
document.getElementById("participate-form") && document.getElementById("participate-form").addEventListener("submit", function(e) {
  var btn = document.getElementById("participate-btn");
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Envoi en cours…";
  }
});
</script>';
require_once 'includes/footer.php';
?>
