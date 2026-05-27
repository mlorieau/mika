<?php
$page_title = 'Missions & Actions';
$page_description = 'Toutes les missions et actions de la Zone 85 — Quiz, photos, randos, Kéto Kolé Tché, enquêtes. Je progresse pour moi. Je fais gagner mon clan.';
$current_page = 'missions';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';

// La grande mission collective de la saison active
$grande_mission = null;
foreach ($missions as $m) {
    if ($m['mission_type'] === 'seasonal_collective' && $m['status'] === 'active') {
        $grande_mission = $m;
        break;
    }
}

// Actions personnelles du moment (actives, hors grande mission collective)
$actions_perso = array_values(array_filter($missions, fn($m) =>
    $m['status'] === 'active' && $m['mission_type'] !== 'seasonal_collective'
));

// Toutes les missions hors grande mission collective
$missions_list = array_values(array_filter($missions, fn($m) =>
    $m['mission_type'] !== 'seasonal_collective'
));

// Map mission_type → CSS tag class (pour les badges et tags)
function mission_type_tag_class(string $type): string {
    return [
        'photo_challenge' => 'photo',
        'quiz'            => 'quiz',
        'rando'           => 'rando',
        'keto_kole_tche'  => 'ktc',
        'weather_mission' => 'meteo',
        'investigation'   => 'enquete',
        'vote'            => 'rapide',
    ][$type] ?? 'rapide';
}

// Map mission status → CSS class + label
function mission_status_class(string $status): string {
    return [
        'active'   => 'status-en-cours',
        'upcoming' => 'status-a-venir',
        'archived' => 'status-termine',
        'closed'   => 'status-termine',
    ][$status] ?? 'status-a-venir';
}

function mission_status_label(string $status): string {
    return [
        'active'   => 'En cours',
        'upcoming' => 'À venir',
        'archived' => 'Terminé',
        'closed'   => 'Terminé',
    ][$status] ?? $status;
}

// Map mission data-filter value from mission_type
function mission_filter_type(string $type): string {
    return [
        'photo_challenge' => 'photo',
        'quiz'            => 'quiz',
        'rando'           => 'rando',
        'keto_kole_tche'  => 'ktc',
        'weather_mission' => 'meteo',
        'investigation'   => 'enquete',
        'vote'            => 'rapide',
    ][$type] ?? 'rapide';
}

$page_styles = '<style>

/* ============================================================
   MISSIONS PAGE — Internal Styles
============================================================ */

/* HERO */
.missions-hero {
  background: linear-gradient(160deg, #0d1e2c 0%, #12314e 55%, #163756 100%);
  padding: 130px 0 80px;
  position: relative;
  overflow: hidden;
}
.missions-hero::before {
  content: \'\';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}
.missions-hero-inner { position: relative; z-index: 1; }
.missions-hero .hero-eyebrow {
  display: inline-block;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: rgba(234,86,73,.85);
  margin-bottom: 18px;
}
.missions-hero h1 {
  font-size: clamp(2.2rem, 5vw, 3.4rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1.5px;
  line-height: 1.05;
  margin-bottom: 16px;
}
.missions-hero .hero-phrase {
  font-size: 1.05rem;
  color: rgba(255,255,255,.55);
  font-style: italic;
  margin-bottom: 32px;
  line-height: 1.6;
}
.hero-mode-pills {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.mode-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  border-radius: 40px;
  font-size: .82rem;
  font-weight: 800;
  letter-spacing: .02em;
  border: 1.5px solid transparent;
}
.mode-pill-perso {
  background: rgba(234,86,73,.18);
  border-color: rgba(234,86,73,.4);
  color: #f07066;
}
.mode-pill-collectif {
  background: rgba(201,150,42,.14);
  border-color: rgba(201,150,42,.35);
  color: #d4a43a;
}

/* ============================================================
   BLOC 1 — GRANDE MISSION DE SAISON
============================================================ */
#grande-mission {
  background: var(--beige);
  padding: 80px 0;
}
.gm-section-eyebrow {
  text-align: center;
  margin-bottom: 36px;
}
.gm-section-eyebrow .overline-label { color: var(--primary); }
.gm-section-eyebrow h2 {
  font-size: clamp(1.5rem, 2.8vw, 2rem);
  font-weight: 900;
  letter-spacing: -.5px;
  color: var(--text);
  margin-bottom: 8px;
}
.gm-card {
  background: linear-gradient(135deg, #0c1e2e 0%, #163756 100%);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-lg);
  border: 1px solid rgba(201,150,42,.25);
}
.gm-inner {
  display: grid;
  grid-template-columns: 1fr 380px;
}
.gm-content { padding: 52px 52px 52px 52px; }
.gm-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(201,150,42,.15);
  border: 1px solid rgba(201,150,42,.3);
  color: #d4a43a;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: 5px 14px;
  border-radius: 4px;
  margin-bottom: 20px;
}
.gm-tag-dot {
  width: 6px;
  height: 6px;
  background: var(--gold);
  border-radius: 50%;
  animation: blink 1.5s infinite;
}
.gm-title {
  font-size: clamp(1.7rem, 3vw, 2.4rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -.5px;
  margin-bottom: 16px;
  line-height: 1.1;
}
.gm-desc {
  font-size: .93rem;
  color: rgba(255,255,255,.6);
  line-height: 1.75;
  margin-bottom: 32px;
  max-width: 480px;
}
.gm-badge-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 32px;
}
.gm-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: .7rem;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 4px;
  letter-spacing: .04em;
}
.gm-badge-collectif {
  background: rgba(234,86,73,.15);
  border: 1px solid rgba(234,86,73,.25);
  color: #f07066;
}
.gm-badge-grande {
  background: rgba(201,150,42,.12);
  border: 1px solid rgba(201,150,42,.25);
  color: #d4a43a;
}
.gm-badge-date {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.55);
}
.gm-info-box {
  background: rgba(201,150,42,.1);
  border: 1px solid rgba(201,150,42,.2);
  border-radius: var(--radius);
  padding: 14px 18px;
  font-size: .82rem;
  color: rgba(255,255,255,.6);
  line-height: 1.65;
  margin-bottom: 32px;
}
.gm-info-box strong { color: #d4a43a; }
.gm-ctas { display: flex; gap: 12px; flex-wrap: wrap; }

/* Grande Mission Side */
.gm-side {
  background: rgba(0,0,0,.22);
  padding: 52px 36px;
  border-left: 1px solid rgba(255,255,255,.07);
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 28px;
}
.gm-side-title {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.4);
  margin-bottom: 16px;
}
.gm-clan-race { width: 100%; }
.gm-clan-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.gm-clan-row:last-child { margin-bottom: 0; }
.gm-clan-lbl {
  font-size: .75rem;
  font-weight: 700;
  color: rgba(255,255,255,.55);
  width: 68px;
  flex-shrink: 0;
}
.gm-clan-bar {
  flex: 1;
  background: rgba(255,255,255,.09);
  border-radius: 5px;
  height: 9px;
  overflow: hidden;
}
.gm-clan-fill {
  height: 100%;
  border-radius: 5px;
  width: 0;
  transition: width 1.4s cubic-bezier(.22,1,.36,1);
}
.gm-fill-bocage  { background: linear-gradient(90deg,#1e5c30,#2d8a49); }
.gm-fill-littoral { background: linear-gradient(90deg,#1d4f7a,#2e78c0); }
.gm-fill-marais   { background: linear-gradient(90deg,var(--primary),#f07066); }
.gm-clan-pct {
  font-size: .72rem;
  font-weight: 800;
  color: rgba(255,255,255,.6);
  width: 36px;
  text-align: right;
  flex-shrink: 0;
}

/* ============================================================
   BLOC 2 — ACTIONS PERSONNELLES DU MOMENT
============================================================ */
#actions-perso {
  background: var(--beige-light);
  padding: 80px 0;
  border-bottom: 1px solid var(--beige-dark);
}
.actions-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-top: 40px;
}
.action-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  padding: 28px 24px;
  box-shadow: var(--shadow-sm);
  border: 2px solid transparent;
  transition: all .25s;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.action-card:hover {
  border-color: var(--primary);
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
}
.action-icon {
  font-size: 2.2rem;
  margin-bottom: 12px;
  display: block;
}
.action-type-badge {
  display: inline-block;
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: 3px 10px;
  border-radius: 4px;
  margin-bottom: 12px;
  align-self: flex-start;
}
.badge-quiz     { background: rgba(234,86,73,.1); color: var(--primary); }
.badge-photo    { background: rgba(99,102,241,.12); color: #4f46e5; }
.badge-meteo    { background: rgba(14,165,233,.12); color: #0369a1; }
.badge-rando    { background: rgba(30,92,48,.12); color: #1e5c30; }
.badge-ktc      { background: rgba(201,150,42,.15); color: #8a6020; }
.badge-rapide   { background: rgba(234,86,73,.08); color: var(--text-mid); }
.badge-enquete  { background: rgba(168,85,247,.1); color: #7c3aed; }
.badge-saison   { background: rgba(234,86,73,.15); color: var(--primary); border: 1px solid rgba(234,86,73,.2); }
.action-title {
  font-size: .97rem;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 8px;
  line-height: 1.3;
}
.action-desc {
  font-size: .8rem;
  color: var(--text-mid);
  line-height: 1.55;
  flex: 1;
  margin-bottom: 18px;
}
.action-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.action-xp {
  font-size: .85rem;
  font-weight: 800;
  color: var(--primary);
}
.action-btn {
  display: inline-flex;
  align-items: center;
  padding: 7px 16px;
  background: var(--navy-dark);
  color: #fff;
  border-radius: var(--radius-sm);
  font-size: .78rem;
  font-weight: 700;
  text-decoration: none;
  transition: background .2s;
  white-space: nowrap;
}
.action-btn:hover { background: var(--primary); }
.actions-note {
  margin-top: 28px;
  text-align: center;
  font-size: .8rem;
  color: var(--text-muted);
  font-style: italic;
}

/* ============================================================
   BLOC 3 — TOUS LES FORMATS
============================================================ */
#mission-list {
  background: var(--beige);
  padding: 80px 0;
}
.ml-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}
.ml-header-text .overline-label { margin-bottom: 8px; }
.ml-header-text h2 {
  font-size: clamp(1.4rem, 2.5vw, 1.9rem);
  font-weight: 900;
  letter-spacing: -.5px;
  color: var(--text);
  margin-bottom: 6px;
}
.ml-header-text p {
  font-size: .85rem;
  color: var(--text-muted);
  line-height: 1.55;
}
.missions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-top: 8px;
}
.mission-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  transition: all .25s;
  border: 2px solid transparent;
}
.mission-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
}
.mission-card.featured { border-color: var(--gold); }
.mission-card-header {
  padding: 22px 22px 16px;
  border-bottom: 1px solid var(--beige-dark);
}
.mission-card.featured .mission-card-header {
  background: rgba(201,150,42,.05);
}
.mission-type-tag {
  display: inline-block;
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: 3px 10px;
  border-radius: 4px;
  margin-bottom: 10px;
}
.tag-photo    { background: rgba(99,102,241,.12); color: #4f46e5; }
.tag-quiz     { background: rgba(234,86,73,.1); color: var(--primary); }
.tag-rando    { background: rgba(30,92,48,.12); color: #1e5c30; }
.tag-ktc      { background: rgba(201,150,42,.15); color: #8a6020; }
.tag-meteo    { background: rgba(14,165,233,.12); color: #0369a1; }
.tag-enquete  { background: rgba(168,85,247,.1); color: #7c3aed; }
.tag-saison   { background: rgba(201,150,42,.15); color: #8a6020; border: 1px solid rgba(201,150,42,.25); }
.tag-rapide   { background: rgba(234,86,73,.08); color: var(--text-mid); }
.mission-title {
  font-size: .97rem;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 6px;
  line-height: 1.3;
}
.mission-desc {
  font-size: .8rem;
  color: var(--text-mid);
  line-height: 1.55;
}
.mission-card-footer {
  padding: 13px 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}
.mission-xp {
  font-size: .84rem;
  font-weight: 800;
  color: var(--primary);
}
.mission-clan-note {
  font-size: .72rem;
  font-weight: 700;
  color: var(--gold);
  background: rgba(201,150,42,.1);
  padding: 2px 8px;
  border-radius: 4px;
}
.mission-status {
  font-size: .7rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 4px;
  letter-spacing: .04em;
}
.status-en-cours  { background: rgba(42,157,92,.12); color: #1a7a42; }
.status-a-venir   { background: rgba(14,165,233,.1); color: #0369a1; }
.status-termine   { background: rgba(122,138,148,.12); color: var(--text-muted); }
.mission-cta-link {
  font-size: .78rem;
  font-weight: 700;
  color: var(--navy-dark);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.mission-card[style*="display:none"] { display: none !important; }

/* ============================================================
   BLOC 4 — COMMENT CA FONCTIONNE
============================================================ */
#comment-ca-marche {
  background: var(--navy-dark);
  padding: 80px 0;
}
#comment-ca-marche .overline-label { color: rgba(234,86,73,.8); }
#comment-ca-marche h2 {
  font-size: clamp(1.5rem, 2.8vw, 2rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -.5px;
  margin-bottom: 10px;
}
#comment-ca-marche .section-sub {
  color: rgba(255,255,255,.5);
  max-width: 520px;
}
.how-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-top: 44px;
}
.how-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: var(--radius-lg);
  padding: 28px 24px;
  transition: background .25s;
}
.how-card:hover { background: rgba(255,255,255,.08); }
.how-icon {
  font-size: 1.8rem;
  margin-bottom: 14px;
  display: block;
}
.how-title {
  font-size: .9rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 10px;
  line-height: 1.3;
}
.how-desc {
  font-size: .8rem;
  color: rgba(255,255,255,.5);
  line-height: 1.65;
}

/* ============================================================
   TYPES APPENDIX
============================================================ */
#types-participation {
  background: var(--beige-light);
  padding: 72px 0;
  border-top: 1px solid var(--beige-dark);
}
#types-participation .overline-label { color: var(--primary); }
#types-participation h2 {
  font-size: clamp(1.3rem, 2.5vw, 1.7rem);
  font-weight: 900;
  color: var(--text);
  letter-spacing: -.5px;
  margin-bottom: 36px;
}
.types-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.type-row {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: var(--white);
  border-radius: var(--radius);
  padding: 16px 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--beige-dark);
  transition: box-shadow .2s;
}
.type-row:hover { box-shadow: var(--shadow-md); }
.type-row-icon { font-size: 1.3rem; flex-shrink: 0; margin-top: 2px; }
.type-row-body {}
.type-row-name {
  font-size: .85rem;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 4px;
}
.type-row-desc {
  font-size: .76rem;
  color: var(--text-muted);
  line-height: 1.55;
}

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1100px) {
  .how-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 1024px) {
  .gm-inner { grid-template-columns: 1fr; }
  .gm-side {
    border-left: none;
    border-top: 1px solid rgba(255,255,255,.07);
    padding: 36px;
  }
  .actions-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .actions-grid { grid-template-columns: 1fr; }
  .missions-grid { grid-template-columns: 1fr; }
  .types-list { grid-template-columns: 1fr; }
  .how-grid { grid-template-columns: 1fr 1fr; }
  .gm-content { padding: 32px 24px; }
  .ml-header { flex-direction: column; }
  .hero-mode-pills { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 520px) {
  .how-grid { grid-template-columns: 1fr; }
  .filter-tabs { gap: 6px; }
  .filter-tab { font-size: .75rem; padding: 7px 12px; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="missions-hero">
  <div class="container missions-hero-inner">
    <?php if ($active_season): ?>
    <span class="hero-eyebrow"><?= e($active_season['title']) ?> · Saison en cours</span>
    <?php endif; ?>
    <h1>Missions &amp; Actions</h1>
    <p class="hero-phrase">Je progresse pour moi. Je fais gagner mon clan.</p>
    <div class="hero-mode-pills">
      <span class="mode-pill mode-pill-perso">⚡ Mode Personnel — XP à vie</span>
      <span class="mode-pill mode-pill-collectif">🛡️ Mode Collectif — Score de saison</span>
    </div>
  </div>
</section>

<!-- BLOC 1 — GRANDE MISSION DE SAISON -->
<section id="grande-mission">
  <div class="container">

    <div class="gm-section-eyebrow reveal">
      <p class="overline-label">🏆 Mission Collective · Saison en cours</p>
      <h2>La Grande Mission de la Saison</h2>
    </div>

    <?php if ($grande_mission): ?>
    <div class="gm-card reveal">
      <div class="gm-inner">

        <div class="gm-content">
          <div class="gm-tag">
            <span class="gm-tag-dot"></span>
            🏆 Mission Collective · Saison en cours
          </div>
          <h2 class="gm-title"><?= e($grande_mission['title']) ?></h2>
          <p class="gm-desc"><?= e($grande_mission['description']) ?></p>

          <div class="gm-badge-row">
            <span class="gm-badge gm-badge-collectif">🏆 Collectif</span>
            <span class="gm-badge gm-badge-grande">🗺️ Grande Mission</span>
            <?php if (!empty($grande_mission['end_date'])): ?>
            <span class="gm-badge gm-badge-date">Jusqu'au <?= e(date('j F', strtotime($grande_mission['end_date']))) ?></span>
            <?php endif; ?>
          </div>

          <div class="gm-info-box">
            <strong>Info :</strong> Cette mission fait avancer la Bataille des Clans. Chaque contribution compte double pour ton clan. Le clan avec le plus de points remporte le trophée de la saison.
          </div>

          <div class="gm-ctas">
            <a href="<?= page_url('inscription') ?>" class="btn btn-primary">Participer →</a>
            <a href="#mission-list" class="btn btn-outline">Voir toutes les missions</a>
          </div>
        </div>

        <div class="gm-side">
          <div>
            <div class="gm-side-title">Course des clans · Saison en cours</div>
            <div class="gm-clan-race">
              <?php
              $race_progress = $grande_mission['race_progress'] ?? [];
              $clan_display  = [
                  'bocage'   => ['label' => '🌳 Bocage',  'fill_class' => 'gm-fill-bocage'],
                  'littoral' => ['label' => '⚓ Littoral', 'fill_class' => 'gm-fill-littoral'],
                  'marais'   => ['label' => '🌿 Marais',  'fill_class' => 'gm-fill-marais'],
              ];
              foreach ($clan_display as $slug => $display):
                $pct = $race_progress[$slug] ?? 0;
              ?>
              <div class="gm-clan-row">
                <span class="gm-clan-lbl"><?= e($display['label']) ?></span>
                <div class="gm-clan-bar">
                  <div class="gm-clan-fill <?= e($display['fill_class']) ?>" data-width="<?= (int)$pct ?>"></div>
                </div>
                <span class="gm-clan-pct"><?= (int)$pct ?>%</span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- BLOC 2 — ACTIONS PERSONNELLES DU MOMENT -->
<section id="actions-perso">
  <div class="container">

    <div class="section-header reveal">
      <p class="overline-label">Mode Personnel · XP à vie</p>
      <h2>Actions personnelles du moment</h2>
      <p class="section-sub">Ces actions font progresser ton profil et peuvent aussi donner un coup de pouce à ton clan.</p>
    </div>

    <div class="actions-grid">
      <?php
      $action_icons = [
          'quiz'            => '🧠',
          'photo_challenge' => '📸',
          'weather_mission' => '🌤️',
          'rando'           => '🥾',
          'keto_kole_tche'  => '🥐',
          'vote'            => '🗳️',
          'investigation'   => '🔍',
      ];
      $action_btn_labels = [
          'quiz'            => 'Répondre',
          'photo_challenge' => 'Envoyer ma photo',
          'weather_mission' => 'Participer',
          'rando'           => 'Donner mon avis',
          'keto_kole_tche'  => 'Proposer une hypothèse',
          'vote'            => 'Voter',
          'investigation'   => 'Enquêter',
      ];
      foreach ($actions_perso as $i => $action):
        $tag_class  = mission_type_tag_class($action['mission_type']);
        $icon       = $action_icons[$action['mission_type']] ?? '📌';
        $xp         = $action['xp_participation'] + $action['xp_success'];
        $btn_label  = $action_btn_labels[$action['mission_type']] ?? 'Participer';
        $delay      = $i > 0 ? ' style="transition-delay:' . round($i * 0.05, 2) . 's"' : '';
      ?>
      <div class="action-card reveal"<?= $delay ?>>
        <span class="action-icon"><?= $icon ?></span>
        <span class="action-type-badge badge-<?= e($tag_class) ?>"><?= e(mission_type_label($action['mission_type'])) ?></span>
        <div class="action-title"><?= e($action['title']) ?></div>
        <p class="action-desc"><?= e($action['description']) ?></p>
        <div class="action-footer">
          <span class="action-xp">+<?= (int)$action['xp_participation'] ?> XP</span>
          <a href="<?= page_url('inscription') ?>" class="action-btn"><?= e($btn_label) ?></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <p class="actions-note reveal">Ces actions nourrissent ton XP personnel (permanent) et peuvent donner un léger bonus à ton clan selon la saison.</p>

  </div>
</section>

<!-- BLOC 3 — TOUS LES FORMATS -->
<section id="mission-list">
  <div class="container">

    <div class="ml-header reveal">
      <div class="ml-header-text">
        <p class="overline-label">Tous les formats de participation</p>
        <h2>Tous les formats de participation</h2>
        <p>Filtrez par type d'action et trouvez ce qui vous inspire.</p>
      </div>
    </div>

    <div class="filter-tabs reveal" id="mission-filters">
      <button class="filter-tab active" data-filter="all" onclick="filterMissions(this,'all')">Toutes</button>
      <button class="filter-tab" data-filter="photo" onclick="filterMissions(this,'photo')">📸 Photo</button>
      <button class="filter-tab" data-filter="quiz" onclick="filterMissions(this,'quiz')">🧠 Quiz</button>
      <button class="filter-tab" data-filter="rando" onclick="filterMissions(this,'rando')">🥾 Rando</button>
      <button class="filter-tab" data-filter="ktc" onclick="filterMissions(this,'ktc')">🥐 KTC</button>
      <button class="filter-tab" data-filter="meteo" onclick="filterMissions(this,'meteo')">🌤️ Météo</button>
      <button class="filter-tab" data-filter="enquete" onclick="filterMissions(this,'enquete')">🔍 Enquête</button>
    </div>

    <div class="missions-grid">
      <?php
      $mission_icons = [
          'quiz'            => '🧠',
          'photo_challenge' => '📸',
          'rando'           => '🥾',
          'keto_kole_tche'  => '🥐',
          'weather_mission' => '🌤️',
          'investigation'   => '🔍',
          'vote'            => '🗳️',
      ];
      $cta_labels = [
          'quiz'            => 'Jouer',
          'photo_challenge' => 'Envoyer',
          'rando'           => 'Participer',
          'keto_kole_tche'  => 'Proposer',
          'weather_mission' => null,
          'investigation'   => 'Enquêter',
          'vote'            => 'Voter',
      ];
      foreach ($missions_list as $i => $mission):
        $tag_class     = mission_type_tag_class($mission['mission_type']);
        $filter_type   = mission_filter_type($mission['mission_type']);
        $icon          = $mission_icons[$mission['mission_type']] ?? '📌';
        $status_class  = mission_status_class($mission['status']);
        $status_label  = mission_status_label($mission['status']);
        $cta_label     = $cta_labels[$mission['mission_type']] ?? null;
        $xp_display    = '+' . (int)$mission['xp_participation'] . ' XP';
        $delay         = $i > 0 ? ' style="transition-delay:' . round($i * 0.04, 2) . 's"' : '';
      ?>
      <div class="mission-card reveal" data-type="<?= e($filter_type) ?>"<?= $delay ?>>
        <div class="mission-card-header">
          <span class="mission-type-tag tag-<?= e($tag_class) ?>"><?= $icon ?> <?= e(mission_type_label($mission['mission_type'])) ?></span>
          <div class="mission-title"><?= e($mission['title']) ?></div>
          <p class="mission-desc"><?= e($mission['description']) ?></p>
        </div>
        <div class="mission-card-footer">
          <span class="mission-xp"><?= e($xp_display) ?></span>
          <span class="mission-status <?= e($status_class) ?>"><?= e($status_label) ?></span>
          <?php if ($mission['status'] === 'active' && $cta_label): ?>
          <a href="<?= page_url('inscription') ?>" class="mission-cta-link"><?= e($cta_label) ?></a>
          <?php elseif ($mission['status'] === 'archived' && $mission['display_in_hall']): ?>
          <a href="<?= page_url('hall') ?>" class="mission-cta-link">Voir le Hall</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- BLOC 4 — COMMENT CA FONCTIONNE -->
<section id="comment-ca-marche">
  <div class="container">

    <div class="section-header reveal">
      <p class="overline-label">Règles de la Zone</p>
      <h2>Comment fonctionnent les participations ?</h2>
      <p class="section-sub">Tout ce que tu dois savoir avant de te lancer dans les missions.</p>
    </div>

    <div class="how-grid">
      <div class="how-card reveal">
        <span class="how-icon">⚡</span>
        <div class="how-title">Actions automatiques</div>
        <p class="how-desc">Certaines participations sont validées automatiquement : quiz, votes, commentaires. Tes XP sont crédités immédiatement.</p>
      </div>
      <div class="how-card reveal" style="transition-delay:.06s">
        <span class="how-icon">👀</span>
        <div class="how-title">Validation équipe</div>
        <p class="how-desc">Les photos, contributions créatives et hypothèses Kéto Kolé Tché sont examinées par l'équipe Zone85 avant validation. Délai : 24–48h.</p>
      </div>
      <div class="how-card reveal" style="transition-delay:.12s">
        <span class="how-icon">🛡️</span>
        <div class="how-title">Modération</div>
        <p class="how-desc">Les photos et commentaires peuvent être modérés. Tout contenu ne respectant pas les règles de la Zone peut être refusé ou supprimé.</p>
      </div>
      <div class="how-card reveal" style="transition-delay:.18s">
        <span class="how-icon">⚖️</span>
        <div class="how-title">Équité et fair-play</div>
        <p class="how-desc">Les points peuvent être ajustés en cas d'erreur technique ou d'abus. Les XP, badges et points ont une valeur symbolique et ludique — pas financière.</p>
      </div>
    </div>

  </div>
</section>

<!-- TYPES DE PARTICIPATION -->
<section id="types-participation">
  <div class="container">

    <div class="reveal" style="margin-bottom:36px">
      <p class="overline-label">Référence</p>
      <h2>Les 8 types d'actions de la Zone</h2>
    </div>

    <div class="types-list">
      <?php
      $type_rows = [
          ['icon' => '🏆', 'name' => 'Grande mission',    'desc' => 'Collective, une par saison, points clan + XP personnels. La mission qui décide du gagnant de la saison.'],
          ['icon' => '🧠', 'name' => 'Quiz',              'desc' => '3 à 5 questions sur la Vendée. Validation automatique. XP personnel crédité immédiatement.'],
          ['icon' => '🥐', 'name' => 'Kéto Kolé Tché',   'desc' => 'Objet mystère vendéen. Propose ton hypothèse, validation équipe. Les solutions sont archivées dans le Hall.'],
          ['icon' => '🥾', 'name' => 'Rando',             'desc' => 'Fiche de randonnée + actions (avis, photo, j\'ai fait). XP personnel. Contributions archivables dans le Hall.'],
          ['icon' => '🌤️', 'name' => 'Météo-mission',   'desc' => 'Mission éditoriale déclenchée par la météo. Rapide à réaliser. XP personnel uniquement.'],
          ['icon' => '📸', 'name' => 'Défi photo',        'desc' => 'Upload une photo, modérée par l\'équipe, puis soumise au vote. XP personnel. Les meilleures entrent au Hall.'],
          ['icon' => '🗳️', 'name' => 'Vote',             'desc' => 'Rapide, XP faible, validation automatique. Idéal pour participer facilement sans s\'engager longtemps.'],
          ['icon' => '🔍', 'name' => 'Enquête',           'desc' => 'Indices, hypothèses collectives, résolution en communauté. Les enquêtes résolues sont archivées dans le Hall.'],
      ];
      foreach ($type_rows as $i => $row):
        $delay = $i > 0 ? ' style="transition-delay:' . round($i * 0.04, 2) . 's"' : '';
      ?>
      <div class="type-row reveal"<?= $delay ?>>
        <span class="type-row-icon"><?= $row['icon'] ?></span>
        <div class="type-row-body">
          <div class="type-row-name"><?= e($row['name']) ?></div>
          <p class="type-row-desc"><?= e($row['desc']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-bloc reveal" style="margin:0 24px 72px;max-width:1200px;margin-left:auto;margin-right:auto;border-radius:var(--radius-lg);margin-bottom:72px">
  <div class="cta-inner">
    <div>
      <h2>Prêt à gagner ton premier XP ?</h2>
      <p>Crée ton compte en 2 minutes et rejoins le clan qui te ressemble.</p>
    </div>
    <div class="cta-actions">
      <a href="<?= page_url('inscription') ?>" class="btn btn-white">Rejoindre la Zone →</a>
      <a href="<?= page_url('classement') ?>" class="btn btn-outline-white">Voir le classement</a>
    </div>
  </div>
</section>

<?php
$page_scripts = '<script>
  /* Animate clan progress bars on scroll */
  (function() {
    function animateBars() {
      document.querySelectorAll(\'.gm-clan-fill[data-width]\').forEach(function(el) {
        var rect = el.getBoundingClientRect();
        if (rect.top < window.innerHeight - 60 && !el.dataset.animated) {
          el.dataset.animated = \'1\';
          el.style.width = el.dataset.width + \'%\';
        }
      });
    }
    window.addEventListener(\'scroll\', animateBars, { passive: true });
    animateBars();
  })();
</script>';
require_once 'includes/footer.php';
?>
