<?php
$page_title       = 'Missions';
$page_description = 'Participe à la Zone85 : photos de Vendée, Kétokole Tchè, pistes mystères, quiz flash. Des appels lancés aux Zonautes — à votre rythme, toute l\'année.';
$page_canonical   = 'https://www.zone85.fr/missions.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Missions','item'=>'https://www.zone85.fr/missions.php'],
    ],
];
$current_page = 'missions';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
// ── Repository layer ──────────────────────────────────────────
require_once 'includes/db.php';
require_once 'includes/repositories.php';
require_once 'includes/auth.php';
if (db_enabled()) {
    $_missions_db = fetch_featured_missions(9);
    if ($_missions_db !== null) $missions = $_missions_db;
    $_season_db = fetch_active_season();
    if ($_season_db !== null) $active_season = $_season_db;
    $_badges_db = fetch_badges();
    if ($_badges_db !== null) $badges = $_badges_db;
}

// Participations de l'utilisateur connecté (id → status)
$_participated_ids      = [];
$_user_mission_statuses = [];
if (db_enabled() && is_logged_in()) {
    $_nav_sess = current_user();
    if ($_nav_sess) {
        $_ms_pdo = db();
        if ($_ms_pdo) {
            try {
                $_ms_stmt = $_ms_pdo->prepare("SELECT mission_id, status FROM participations WHERE user_id = :id");
                $_ms_stmt->execute([':id' => (int)$_nav_sess['id']]);
                foreach ($_ms_stmt->fetchAll() as $_ms_row) {
                    $_participated_ids[] = (int)$_ms_row['mission_id'];
                    $_user_mission_statuses[(int)$_ms_row['mission_id']] = $_ms_row['status'];
                }
            } catch (PDOException $_ms_e) { /* silent */ }
        }
    }
}

// ── SECTION 0 : Grand Défi de la Saison ──────────────────────
$grand_defi = null;
try {
    if (db_enabled() && function_exists('fetch_grande_mission_active')) {
        $grand_defi = fetch_grande_mission_active();
    }
    // Fallback : requête directe si la fonction ne retourne rien
    if (!$grand_defi && db_enabled()) {
        $pdo = db();
        if ($pdo) {
            $gd_stmt = $pdo->prepare(
                "SELECT m.* FROM missions m
                 WHERE (m.is_grande_mission = 1 OR m.mission_type = 'seasonal_collective')
                   AND m.status = 'active'
                 LIMIT 1"
            );
            $gd_stmt->execute();
            $grand_defi = $gd_stmt->fetch() ?: null;
        }
    }
} catch (Exception $e) {
    $grand_defi = null;
}

// Compte à rebours Grand Défi
$gd_countdown_ms = 0;
if ($grand_defi && !empty($grand_defi['end_date'])) {
    $gd_end = strtotime($grand_defi['end_date']);
    if ($gd_end > time()) {
        $gd_countdown_ms = ($gd_end - time()) * 1000;
    }
}

// Race progress (depuis le grand défi ou depuis la grande mission de $missions)
$gd_race = ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
if ($grand_defi) {
    if (!empty($grand_defi['race_progress']) && is_array($grand_defi['race_progress'])) {
        $gd_race = $grand_defi['race_progress'];
    } else {
        // chercher dans $missions
        foreach ($missions as $m) {
            if ($m['mission_type'] === 'seasonal_collective' && $m['status'] === 'active') {
                $gd_race = $m['race_progress'] ?? $gd_race;
                break;
            }
        }
    }
}

// Participation au grand défi
$gd_already_participated = false;
if ($grand_defi && is_logged_in()) {
    $gd_already_participated = in_array((int)$grand_defi['id'], $_participated_ids);
}

// XP Grand Défi
$gd_xp = 0;
if ($grand_defi) {
    $gd_xp = ((int)($grand_defi['xp_participation'] ?? 0)) + ((int)($grand_defi['xp_success'] ?? 0));
}

// Progression fictive (nb participations / 100)
$gd_progress_pct = 0;
if ($grand_defi && !empty($grand_defi['participation_count'])) {
    $gd_progress_pct = min(100, (int)round(((int)$grand_defi['participation_count'] / 100) * 100));
}

// ── Missions hors grande mission collective ───────────────────
$missions_list = array_values(array_filter($missions, fn($m) =>
    $m['mission_type'] !== 'seasonal_collective'
));

// Map mission_type → CSS tag class (pour les badges et tags)
function mission_type_tag_class(string $type): string {
    return [
        'photo_challenge'     => 'capturer',
        'keto_kole_tche'      => 'chercher',
        'investigation'       => 'chercher',
        'quiz'                => 'quiz',
        'weather_mission'     => 'quiz',
        'vote'                => 'pistes',
        'hidden_hunt'         => 'pistes',
        'rando'               => 'rando',
        'seasonal_collective' => 'saison',
        'zone_wake'           => 'zone-wake',
    ][$type] ?? 'quiz';
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
        'active'   => 'Actif',
        'upcoming' => 'À venir',
        'archived' => 'Terminé',
        'closed'   => 'Terminé',
    ][$status] ?? $status;
}

// Map mission data-filter value from mission_type (4 familles V13)
function mission_filter_type(string $type): string {
    return [
        'photo_challenge'     => 'capturer',
        'keto_kole_tche'      => 'chercher',
        'investigation'       => 'chercher',
        'quiz'                => 'quiz',
        'weather_mission'     => 'quiz',
        'vote'                => 'pistes',
        'hidden_hunt'         => 'pistes',
        'rando'               => 'rando',
        'zone_wake'           => 'capturer',
    ][$type] ?? 'quiz';
}

$page_styles = '<style>

/* ============================================================
   MISSIONS PAGE V11 — Internal Styles
   Mobile-first
============================================================ */

/* HERO — fond/padding depuis zone85.css (.missions-hero) */
.missions-hero-inner { position: relative; z-index: 1; }
.mh-split { display: grid; grid-template-columns: 1fr 360px; gap: 56px; align-items: center; position: relative; z-index: 1; }
@media(max-width:900px){ .mh-split { grid-template-columns: 1fr; } .mh-visual { display: none; } }
.mh-visual-card {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 20px;
  padding: 28px 26px;
  backdrop-filter: blur(8px);
}
.mh-visual-card-title {
  font-size: .62rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .14em; color: rgba(255,255,255,.4); margin-bottom: 16px;
}
.mh-stat { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.07); }
.mh-stat:last-child { border-bottom: none; padding-bottom: 0; }
.mh-stat-icon { font-size: 1.1rem; width: 28px; text-align: center; flex-shrink: 0; }
.mh-stat-label { font-size: .78rem; color: rgba(255,255,255,.45); font-weight: 600; }
.mh-stat-val { font-size: .92rem; font-weight: 900; color: #fff; margin-top: 1px; }
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
  font-size: clamp(2rem, 5vw, 3.4rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1.5px;
  line-height: 1.05;
  margin-bottom: 16px;
}
.missions-hero .hero-phrase {
  font-size: 1rem;
  color: rgba(255,255,255,.55);
  font-style: italic;
  margin-bottom: 32px;
  line-height: 1.6;
}
.hero-mode-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.mode-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 40px;
  font-size: .8rem;
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
   SECTION 0 — GRAND DÉFI DE LA SAISON
============================================================ */
#grand-defi {
  background: linear-gradient(160deg, #0a1520 0%, #102030 60%, #0d1e2c 100%);
  padding: 72px 0 80px;
  position: relative;
  overflow: hidden;
}
#grand-defi::before {
  content: "🏆";
  position: absolute;
  right: 4%;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14rem;
  opacity: .04;
  pointer-events: none;
  line-height: 1;
  user-select: none;
}
.gd-inner { position: relative; z-index: 1; }

.gd-badge-top {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(201,150,42,.18);
  border: 1px solid rgba(201,150,42,.38);
  color: #d4a43a;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
  padding: 6px 16px;
  border-radius: 999px;
  margin-bottom: 24px;
}
.gd-badge-dot {
  width: 7px;
  height: 7px;
  background: #d4a43a;
  border-radius: 50%;
  animation: blink 1.5s infinite;
}

.gd-title {
  font-size: clamp(2rem, 4.5vw, 3.2rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1.5px;
  line-height: 1.08;
  margin-bottom: 16px;
}
.gd-desc {
  font-size: .96rem;
  color: rgba(255,255,255,.58);
  line-height: 1.7;
  margin-bottom: 28px;
  max-width: 560px;
}

.gd-meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 28px;
}
.gd-meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: .74rem;
  font-weight: 700;
  padding: 5px 13px;
  border-radius: 6px;
  letter-spacing: .04em;
}
.gd-pill-xp {
  background: rgba(234,86,73,.14);
  border: 1px solid rgba(234,86,73,.24);
  color: #f07066;
}
.gd-pill-clan {
  background: rgba(201,150,42,.12);
  border: 1px solid rgba(201,150,42,.24);
  color: #d4a43a;
}
.gd-pill-date {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.5);
}

/* Barre de progression Grand Défi */
.gd-progress-wrap {
  margin-bottom: 36px;
  max-width: 520px;
}
.gd-progress-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: .74rem;
  font-weight: 700;
  color: rgba(255,255,255,.45);
  margin-bottom: 8px;
}
.gd-progress-label span { color: #d4a43a; }
.gd-progress-bar {
  width: 100%;
  height: 10px;
  background: rgba(255,255,255,.1);
  border-radius: 5px;
  overflow: hidden;
}
.gd-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #c9962a, #f0c048);
  border-radius: 5px;
  width: 0;
  transition: width 1.5s cubic-bezier(.22,1,.36,1);
}

/* CTA Grand Défi */
.gd-ctas {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

/* Compte à rebours */
.gd-countdown {
  margin-top: 36px;
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
}
.gd-countdown-label {
  font-size: .7rem;
  font-weight: 700;
  color: rgba(255,255,255,.4);
  text-transform: uppercase;
  letter-spacing: .1em;
  margin-right: 4px;
}
.gd-cd-unit {
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px;
  padding: 8px 14px;
  text-align: center;
  min-width: 56px;
}
.gd-cd-num {
  display: block;
  font-size: 1.4rem;
  font-weight: 900;
  color: #fff;
  line-height: 1;
}
.gd-cd-lbl {
  font-size: .62rem;
  font-weight: 700;
  color: rgba(255,255,255,.4);
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-top: 3px;
  display: block;
}

/* Race des clans dans le Grand Défi */
.gd-clan-section {
  margin-top: 48px;
  padding-top: 36px;
  border-top: 1px solid rgba(255,255,255,.07);
}
.gd-clan-section-title {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(255,255,255,.4);
  margin-bottom: 20px;
}
.gd-clan-rows { max-width: 480px; }
.gd-clan-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}
.gd-clan-row:last-child { margin-bottom: 0; }
.gd-clan-lbl {
  font-size: .78rem;
  font-weight: 700;
  color: rgba(255,255,255,.55);
  width: 72px;
  flex-shrink: 0;
}
.gd-clan-bar {
  flex: 1;
  background: rgba(255,255,255,.09);
  border-radius: 5px;
  height: 10px;
  overflow: hidden;
}
.gd-clan-fill {
  height: 100%;
  border-radius: 5px;
  width: 0;
  transition: width 1.4s cubic-bezier(.22,1,.36,1);
}
.gd-fill-bocage   { background: linear-gradient(90deg,#1e5c30,#2d8a49); }
.gd-fill-littoral { background: linear-gradient(90deg,#1d4f7a,#2e78c0); }
.gd-fill-marais   { background: linear-gradient(90deg,var(--primary),#f07066); }
.gd-clan-pct {
  font-size: .74rem;
  font-weight: 800;
  color: rgba(255,255,255,.6);
  width: 38px;
  text-align: right;
  flex-shrink: 0;
}

/* ============================================================
   SECTION 1 — FILTRES PAR TYPE
============================================================ */
#mission-filters-section {
  background: var(--beige);
  padding: 64px 0 0;
}
.filter-header {
  margin-bottom: 28px;
}
.filter-header h2 {
  font-size: clamp(1.4rem, 2.5vw, 1.9rem);
  font-weight: 900;
  letter-spacing: -.5px;
  color: var(--text);
  margin-bottom: 6px;
}
.filter-header p {
  font-size: .85rem;
  color: var(--text-muted);
}
.missions-families-grid {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 24px;
  margin-bottom: 4px;
}
.missions-family {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: #fff;
  border: 1.5px solid var(--beige-dark, #e8e0d4);
  border-radius: 12px;
  padding: 14px 18px;
  min-width: 128px;
  text-align: center;
  flex: 1 1 128px;
  max-width: 180px;
}
.missions-family .mf-icon { font-size: 1.5rem; line-height: 1; }
.missions-family strong   { font-size: .78rem; font-weight: 800; color: var(--navy-dark); line-height: 1.3; }
.missions-family span     { font-size: .7rem; color: var(--text-muted); line-height: 1.4; }

/* Tabs filtres améliorés */
.filter-tabs-v2 {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  padding-bottom: 0;
}
.filter-tab-v2 {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 10px;
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
  border: 1.5px solid rgba(18,49,78,.14);
  background: var(--white);
  color: var(--text-mid);
  transition: all .18s;
  white-space: nowrap;
}
.filter-tab-v2:hover {
  border-color: var(--navy-dark);
  color: var(--navy-dark);
  background: rgba(18,49,78,.04);
}
.filter-tab-v2.active {
  background: var(--navy-dark);
  color: #fff;
  border-color: var(--navy-dark);
}
.filter-tab-v2 .tab-icon { font-size: 1rem; }
.filter-tab-v2 .tab-count {
  font-size: .65rem;
  background: rgba(255,255,255,.25);
  padding: 1px 6px;
  border-radius: 999px;
  font-weight: 800;
}
.filter-tab-v2:not(.active) .tab-count {
  background: rgba(18,49,78,.1);
  color: var(--text-muted);
}

/* ============================================================
   SECTION 2 — GRILLE MISSIONS
============================================================ */
#mission-list {
  background: var(--beige);
  padding: 28px 0 80px;
}
.missions-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
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
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
}
.mission-card.featured { border-color: var(--gold); }
.mission-card-header {
  padding: 20px 20px 14px;
  border-bottom: 1px solid var(--beige-dark);
}
.mission-card.featured .mission-card-header {
  background: rgba(201,150,42,.05);
}
.mission-card-header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.mission-type-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 5px;
}
.tag-capturer  { background: rgba(99,102,241,.12);  color: #4f46e5; }
.tag-chercher  { background: rgba(201,150,42,.15);  color: #8a6020; }
.tag-pistes    { background: rgba(168,85,247,.1);   color: #7c3aed; }
.tag-quiz      { background: rgba(234,86,73,.1);    color: var(--primary); }
.tag-rando     { background: rgba(30,92,48,.12);    color: #1e5c30; }
.tag-saison    { background: rgba(201,150,42,.15);  color: #8a6020; border: 1px solid rgba(201,150,42,.25); }
.tag-zone-wake { background: rgba(234,86,73,.12);   color: #c04d42; border: 1px solid rgba(234,86,73,.2); }
/* legacy aliases kept for possible existing data */
.tag-photo    { background: rgba(99,102,241,.12);  color: #4f46e5; }
.tag-ktc      { background: rgba(201,150,42,.15);  color: #8a6020; }
.tag-enquete  { background: rgba(168,85,247,.1);   color: #7c3aed; }
.tag-vote     { background: rgba(234,86,73,.08);   color: var(--text-mid); }
.tag-rapide   { background: rgba(234,86,73,.08);   color: var(--text-mid); }
.status-zone-wake  { background: rgba(234,86,73,.1);   color: #c04d42; }
.status-coup-coeur { background: rgba(201,150,42,.12);  color: #8a6020; }

.mission-already-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: .65rem;
  font-weight: 800;
  background: rgba(42,157,92,.12);
  color: #1a7a42;
  padding: 4px 10px;
  border-radius: 5px;
  letter-spacing: .04em;
}
.mission-badge-validated  { background: rgba(42,157,92,.18); color: #1a7a42; }
.mission-badge-auto       { background: rgba(42,157,92,.12); color: #1a7a42; }
.mission-badge-pending    { background: rgba(233,149,26,.15); color: #9a5800; }
.mission-badge-rejected   { background: rgba(107,127,150,.12); color: #4b6074; }
.mission-title {
  font-size: 1rem;
  font-weight: 900;
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
  padding: 12px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}
.mission-xp {
  font-size: .88rem;
  font-weight: 900;
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
.status-en-cours { background: rgba(42,157,92,.12);   color: #1a7a42; }
.status-a-venir  { background: rgba(14,165,233,.1);   color: #0369a1; }
.status-termine  { background: rgba(122,138,148,.12); color: var(--text-muted); }
.mission-cta-link {
  font-size: .8rem;
  font-weight: 800;
  color: var(--navy-dark);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.mission-card[style*="display:none"] { display: none !important; }

/* ============================================================
   SECTION 3 — MYSTÈRE DE LA SAISON (KTC TEASER)
============================================================ */
#mystere-saison {
  background: #0a1520;
  padding: 0 0 80px;
}
.ktc-teaser {
  background: linear-gradient(135deg, #0d1e2c 0%, #1a2d3e 100%);
  border: 1px solid rgba(201,150,42,.2);
  border-radius: var(--radius-lg);
  padding: 40px 36px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}
.ktc-teaser-body {}
.ktc-teaser-eyebrow {
  font-size: .68rem;
  font-weight: 800;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(201,150,42,.7);
  margin-bottom: 10px;
  display: block;
}
.ktc-teaser-title {
  font-size: clamp(1.3rem, 3vw, 1.9rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -.5px;
  margin-bottom: 12px;
}
.ktc-teaser-text {
  font-size: .88rem;
  color: rgba(255,255,255,.5);
  line-height: 1.65;
  max-width: 480px;
}
.ktc-teaser-cta { flex-shrink: 0; }

/* ============================================================
   BLOC — COMMENT CA FONCTIONNE
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
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 44px;
}
.how-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: var(--radius-lg);
  padding: 26px 22px;
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
  grid-template-columns: 1fr;
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
   BADGES DE LISIBILITÉ
============================================================ */
.readability-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}
.rd-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: .68rem;
  font-weight: 700;
  color: #4a5568;
  background: #f5f2ec;
  border: 1px solid #e2ddd5;
  border-radius: 999px;
  padding: 2px 9px;
  white-space: nowrap;
  line-height: 1.6;
}
.rd-badge-diff-facile  { background: rgba(42,157,92,.1);  border-color: rgba(42,157,92,.2);  color: #1a6b3a; }
.rd-badge-diff-moyen   { background: rgba(234,149,26,.1); border-color: rgba(234,149,26,.2); color: #8a5000; }
.rd-badge-diff-difficile{ background: rgba(234,86,73,.1); border-color: rgba(234,86,73,.2);  color: #9a2020; }

/* ============================================================
   RESPONSIVE — tablette & desktop
============================================================ */
@media (min-width: 600px) {
  .missions-grid { grid-template-columns: repeat(2, 1fr); }
  .types-list { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 900px) {
  .how-grid { grid-template-columns: repeat(4, 1fr); }
  .missions-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1100px) {
  .missions-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 599px) {
  .filter-tabs-v2 { gap: 6px; }
  .filter-tab-v2 { font-size: .76rem; padding: 8px 12px; }
  .ktc-teaser { padding: 28px 20px; }
  .gd-title { letter-spacing: -1px; }
  #grand-defi::before { display: none; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="missions-hero">
  <div class="container missions-hero-inner">
    <div class="mh-split">

      <!-- Colonne texte -->
      <div class="mh-text">
        <?php if (!empty($active_season)): ?>
        <span class="hero-eyebrow">Participer à la Zone<?= !empty($active_season['title']) ? ' · ' . e($active_season['title']) : '' ?></span>
        <?php endif; ?>
        <h1>Participer &agrave; la Zone</h1>
        <p class="hero-phrase">Des appels lanc&eacute;s aux Zonautes, au fil des saisons &mdash; &agrave; votre rythme.</p>
        <div class="hero-mode-pills">
          <span class="mode-pill mode-pill-perso">⚡ XP permanents &mdash; jamais perdus</span>
          <span class="mode-pill mode-pill-collectif">🛡️ Points de clan &mdash; classement annuel</span>
        </div>
      </div>

      <!-- Colonne visuelle : carte saison -->
      <div class="mh-visual">
        <div class="mh-visual-card">
          <div class="mh-visual-card-title">🗓️ Participations ouvertes</div>
          <?php
          $mh_mission_count = count($missions_list ?? []);
          $mh_xp_total = array_sum(array_map(fn($m)=>(int)($m['xp_participation']??0)+(int)($m['xp_success']??0), $missions_list ?? []));
          $mh_days = 0;
          if (!empty($active_season['end_date'])) {
              $mh_days = max(0, (int)ceil((strtotime($active_season['end_date']) - time()) / 86400));
          }
          $mh_season_label = $active_season['title'] ?? 'En ce moment dans le QG';
          ?>
          <div class="mh-stat">
            <div class="mh-stat-icon">🎯</div>
            <div>
              <div class="mh-stat-label">Participations disponibles</div>
              <div class="mh-stat-val"><?= $mh_mission_count ?> mission<?= $mh_mission_count > 1 ? 's' : '' ?></div>
            </div>
          </div>
          <?php if ($mh_xp_total > 0): ?>
          <div class="mh-stat">
            <div class="mh-stat-icon">⚡</div>
            <div>
              <div class="mh-stat-label">XP max disponibles</div>
              <div class="mh-stat-val">+<?= number_format($mh_xp_total) ?> XP</div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($grand_defi): ?>
          <div class="mh-stat">
            <div class="mh-stat-icon">🏆</div>
            <div>
              <div class="mh-stat-label">Grand Défi en cours</div>
              <div class="mh-stat-val" style="font-size:.82rem;line-height:1.3"><?= e(mb_substr($grand_defi['title'],0,40)) ?><?= mb_strlen($grand_defi['title'])>40?'…':'' ?></div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($mh_days > 0): ?>
          <div class="mh-stat">
            <div class="mh-stat-icon">⏳</div>
            <div>
              <div class="mh-stat-label">Fin de saison dans</div>
              <div class="mh-stat-val"><?= $mh_days ?> jour<?= $mh_days>1?'s':'' ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- SECTION 0 — GRAND DÉFI DE LA SAISON -->
<section id="grand-defi"<?= $grand_defi ? '' : ' style="display:none"' ?>>
  <div class="container gd-inner">

    <div class="gd-badge-top reveal">
      <span class="gd-badge-dot"></span>
      🏆 Grand Défi de la Saison
    </div>

    <?php if ($grand_defi): ?>

    <h2 class="gd-title reveal"><?= e($grand_defi['title']) ?></h2>
    <p class="gd-desc reveal">
      <?= e(mb_substr($grand_defi['description'] ?? '', 0, 100)) ?>…
    </p>

    <div class="gd-meta-row reveal">
      <?php if ($gd_xp > 0): ?>
      <span class="gd-meta-pill gd-pill-xp">⚡ +<?= (int)$gd_xp ?> XP</span>
      <?php endif; ?>
      <span class="gd-meta-pill gd-pill-clan">🛡️ Points clan</span>
      <?php if (!empty($grand_defi['end_date'])): ?>
      <span class="gd-meta-pill gd-pill-date">Jusqu'au <?= e(date('j F Y', strtotime($grand_defi['end_date']))) ?></span>
      <?php endif; ?>
    </div>

    <!-- Barre de progression -->
    <?php if (!empty($grand_defi['participation_count'])): ?>
    <div class="gd-progress-wrap reveal">
      <div class="gd-progress-label">
        <span>Participations</span>
        <span><?= (int)$grand_defi['participation_count'] ?> / 100</span>
      </div>
      <div class="gd-progress-bar">
        <div class="gd-progress-fill" data-width="<?= $gd_progress_pct ?>"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- CTA -->
    <div class="gd-ctas reveal">
      <?php if ($gd_already_participated): ?>
      <span class="btn" style="background:rgba(42,157,92,.15);border:2px solid rgba(42,157,92,.3);color:#2a9d5c;cursor:default">✅ Déjà participé</span>
      <?php else: ?>
      <a href="mission.php?id=<?= (int)$grand_defi['id'] ?>" class="btn btn-primary">Rejoindre le défi →</a>
      <?php endif; ?>
      <a href="#mission-list" class="btn btn-outline" style="border-color:rgba(255,255,255,.25);color:rgba(255,255,255,.7)">Toutes les participations ouvertes</a>
    </div>

    <!-- Compte à rebours -->
    <?php if ($gd_countdown_ms > 0): ?>
    <div class="gd-countdown reveal" id="gd-countdown">
      <span class="gd-countdown-label">Se termine dans</span>
      <div class="gd-cd-unit"><span class="gd-cd-num" id="gd-cd-days">--</span><span class="gd-cd-lbl">jours</span></div>
      <div class="gd-cd-unit"><span class="gd-cd-num" id="gd-cd-hours">--</span><span class="gd-cd-lbl">heures</span></div>
      <div class="gd-cd-unit"><span class="gd-cd-num" id="gd-cd-min">--</span><span class="gd-cd-lbl">min</span></div>
      <div class="gd-cd-unit"><span class="gd-cd-num" id="gd-cd-sec">--</span><span class="gd-cd-lbl">sec</span></div>
    </div>
    <?php endif; ?>

    <!-- Course des clans -->
    <div class="gd-clan-section reveal">
      <div class="gd-clan-section-title">Course des clans · classement annuel</div>
      <div class="gd-clan-rows">
        <?php
        $clan_display = [
            'bocage'   => ['label' => '🌳 Bocage',   'fill_class' => 'gd-fill-bocage'],
            'littoral' => ['label' => '⚓ Littoral',  'fill_class' => 'gd-fill-littoral'],
            'marais'   => ['label' => '🌿 Marais',   'fill_class' => 'gd-fill-marais'],
        ];
        foreach ($clan_display as $slug => $display):
            $pct = $gd_race[$slug] ?? 0;
        ?>
        <div class="gd-clan-row">
          <span class="gd-clan-lbl"><?= e($display['label']) ?></span>
          <div class="gd-clan-bar">
            <div class="gd-clan-fill <?= e($display['fill_class']) ?>" data-width="<?= (int)$pct ?>"></div>
          </div>
          <span class="gd-clan-pct"><?= (int)$pct ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php endif; ?>
  </div>
</section>

<!-- SECTION 1 — FILTRES PAR TYPE -->
<section id="mission-filters-section">
  <div class="container">
    <div class="filter-header reveal">
      <p class="overline-label">Participer &agrave; la Zone</p>
      <h2>Des appels aux Zonautes</h2>
      <p>Partager une photo, identifier un objet, suivre une piste ou r&eacute;pondre &agrave; un quiz &mdash; au fil des saisons, &agrave; votre rythme.</p>

      <div class="missions-families-grid">
        <div class="missions-family">
          <span class="mf-icon">📸</span>
          <strong>Capturer la Vend&eacute;e</strong>
          <span>Photos, souvenirs, paysages</span>
        </div>
        <div class="missions-family">
          <span class="mf-icon">🔍</span>
          <strong>Chercher &amp; identifier</strong>
          <span>K&eacute;tokole Tch&egrave;, enqu&ecirc;tes collectives</span>
        </div>
        <div class="missions-family">
          <span class="mf-icon">🗝️</span>
          <strong>Suivre les pistes</strong>
          <span>Objets myst&egrave;res, chasses aux indices</span>
        </div>
        <div class="missions-family">
          <span class="mf-icon">🧠</span>
          <strong>Quiz &amp; d&eacute;fis flash</strong>
          <span>Questions rapides, animation Facebook</span>
        </div>
      </div>
    </div>

    <div class="filter-tabs-v2 reveal" id="mission-filters">
      <button class="filter-tab-v2 active" data-filter="all" onclick="filterMissions(this,'all')">
        <span class="tab-icon">🗺️</span> Tout voir
      </button>
      <button class="filter-tab-v2" data-filter="capturer" onclick="filterMissions(this,'capturer')">
        <span class="tab-icon">📸</span> Capturer
      </button>
      <button class="filter-tab-v2" data-filter="chercher" onclick="filterMissions(this,'chercher')">
        <span class="tab-icon">🔍</span> Chercher
      </button>
      <button class="filter-tab-v2" data-filter="pistes" onclick="filterMissions(this,'pistes')">
        <span class="tab-icon">🗝️</span> Pistes
      </button>
      <button class="filter-tab-v2" data-filter="quiz" onclick="filterMissions(this,'quiz')">
        <span class="tab-icon">🧠</span> Quiz &amp; Flash
      </button>
      <button class="filter-tab-v2" data-filter="rando" onclick="filterMissions(this,'rando')">
        <span class="tab-icon">🥾</span> Rando
      </button>
    </div>
  </div>
</section>

<!-- SECTION 2 — GRILLE MISSIONS -->
<section id="mission-list">
  <div class="container">

    <div class="missions-grid" id="missions-grid">
      <?php
      $mission_icons = [
          'quiz'            => '🧠',
          'photo_challenge' => '📸',
          'rando'           => '🥾',
          'keto_kole_tche'  => '🔍',
          'weather_mission' => '🌤️',
          'investigation'   => '🔍',
          'vote'            => '🗳️',
          'zone_wake'       => '⚡',
          'hidden_hunt'     => '🗝️',
      ];
      $cta_labels = [
          'quiz'            => 'Jouer',
          'photo_challenge' => 'Envoyer',
          'rando'           => 'Participer',
          'keto_kole_tche'  => 'Identifier',
          'weather_mission' => 'Participer',
          'investigation'   => 'Enquêter',
          'vote'            => 'Voter',
          'zone_wake'       => 'Participer',
          'hidden_hunt'     => 'Suivre la piste',
      ];
      foreach ($missions_list as $i => $mission):
          $tag_class    = mission_type_tag_class($mission['mission_type']);
          $filter_type  = mission_filter_type($mission['mission_type']);
          $icon         = $mission_icons[$mission['mission_type']] ?? '📌';
          $status_class = mission_status_class($mission['status']);
          $status_label = mission_status_label($mission['status']);
          $cta_label    = $cta_labels[$mission['mission_type']] ?? 'Voir';
          $xp_display      = '+' . (int)$mission['xp_participation'] . ' XP';
          $participated    = in_array((int)$mission['id'], $_participated_ids);
          $part_status     = $_user_mission_statuses[(int)$mission['id']] ?? null;
          $delay           = $i > 0 ? ' style="transition-delay:' . round($i * 0.04, 2) . 's"' : '';
      ?>
      <div class="mission-card reveal" data-type="<?= e($filter_type) ?>"<?= $delay ?>>
        <div class="mission-card-header">
          <div class="mission-card-header-top">
            <span class="mission-type-tag tag-<?= e($tag_class) ?>"><?= $icon ?> <?= e(mission_type_label($mission['mission_type'])) ?></span>
            <?php if ($participated): ?>
              <?php if ($part_status === 'validated' || $part_status === 'auto_validated'): ?>
                <span class="mission-already-badge mission-badge-validated">&#x2713; +<?= (int)$mission['xp_participation'] ?>&thinsp;XP</span>
              <?php elseif ($part_status === 'rejected'): ?>
                <span class="mission-already-badge mission-badge-rejected">&#x2715; Non retenue</span>
              <?php elseif ($part_status === 'pending'): ?>
                <span class="mission-already-badge mission-badge-pending">&#x23F3; En attente</span>
              <?php else: ?>
                <span class="mission-already-badge mission-badge-auto">&#x2713; Participé</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="mission-title"><?= e($mission['title']) ?></div>
          <p class="mission-desc"><?= e($mission['description']) ?></p>
          <?php
          // ── Badges de lisibilité ──────────────────────────────
          // Difficulté déduite depuis les champs réels
          $_rd_diff = null;
          if (isset($mission['difficulty'])) {
              $_rd_diff = strtolower((string)$mission['difficulty']);
          } elseif (isset($mission['xp_success'])) {
              $_rd_xps = (int)$mission['xp_success'];
              if ($_rd_xps <= 15)      $_rd_diff = 'facile';
              elseif ($_rd_xps <= 50)  $_rd_diff = 'moyen';
              else                     $_rd_diff = 'difficile';
          }
          // Durée déduite depuis duration_minutes si présent, sinon depuis mission_type
          $_rd_dur = null;
          if (isset($mission['duration_minutes']) && $mission['duration_minutes'] > 0) {
              $_rd_dur = '~' . (int)$mission['duration_minutes'] . 'min';
          } else {
              $_rd_dur_map = [
                  'quiz'            => '~5min',
                  'vote'            => '~2min',
                  'weather_mission' => '~5min',
                  'photo_challenge' => '~15min',
                  'keto_kole_tche'  => '~10min',
                  'rando'           => '~3h',
                  'investigation'   => '~20min',
              ];
              $_rd_dur = $_rd_dur_map[$mission['mission_type']] ?? null;
          }
          // Icône lieu déduite depuis location_type si présent, sinon mission_type
          $_rd_loc = null;
          if (isset($mission['location_type'])) {
              $_rd_loc_map = ['home' => '🏠', 'outdoor' => '🌿', 'photo' => '📸', 'family' => '👨‍👩‍👧'];
              $_rd_loc = $_rd_loc_map[strtolower((string)$mission['location_type'])] ?? null;
          } else {
              $_rd_loc_map2 = [
                  'rando'           => '🌿',
                  'photo_challenge' => '📸',
                  'weather_mission' => '🏠',
                  'investigation'   => '🏠',
              ];
              $_rd_loc = $_rd_loc_map2[$mission['mission_type']] ?? null;
          }
          $has_badges = $_rd_diff || $_rd_dur || $_rd_loc;
          ?>
          <?php if ($has_badges): ?>
          <div class="readability-badges">
            <?php if ($_rd_diff): ?>
              <?php $_rd_diff_label = ['facile'=>'Facile','moyen'=>'Moyen','difficile'=>'Difficile'][$_rd_diff] ?? $_rd_diff; ?>
              <span class="rd-badge rd-badge-diff-<?= e($_rd_diff) ?>"><?= e($_rd_diff_label) ?></span>
            <?php endif; ?>
            <?php if ($_rd_dur): ?>
              <span class="rd-badge">⏱ <?= e($_rd_dur) ?></span>
            <?php endif; ?>
            <?php if ($_rd_loc): ?>
              <span class="rd-badge"><?= $_rd_loc ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="mission-card-footer">
          <span class="mission-xp"><?= e($xp_display) ?></span>
          <?php if (!$participated): ?>
          <span class="mission-status <?= e($status_class) ?>"><?= e($status_label) ?></span>
          <?php endif; ?>
          <?php if ($mission['status'] === 'active' && !$participated): ?>
          <a href="mission.php?id=<?= (int)$mission['id'] ?>" class="mission-cta-link"><?= e($cta_label) ?></a>
          <?php elseif ($mission['status'] === 'archived' && !empty($mission['display_in_hall'])): ?>
          <span class="mission-status status-coup-coeur">&#x2665; Coup de c&oelig;ur</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($missions_list)): ?>
    <div style="text-align:center;padding:64px 24px;color:var(--text-muted)">
      <div style="font-size:3rem;margin-bottom:16px">🗺️</div>
      <p style="font-size:1.05rem;font-weight:700;color:var(--navy-dark);margin-bottom:8px">Les missions arrivent bientôt. En attendant, explore les randos.</p>
      <p style="font-size:.9rem;margin-bottom:24px">Reviens dans la Zone — les premières aventures se préparent.</p>
      <a href="randos.php" style="display:inline-block;background:var(--primary);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.88rem">Explorer les randos →</a>
    </div>
    <?php endif; ?>
    <!-- Message affiché par JS si les filtres ne donnent aucun résultat -->
    <div id="missions-filter-empty" style="display:none;text-align:center;padding:64px 24px;color:var(--text-muted)">
      <div style="font-size:2.5rem;margin-bottom:16px">🔍</div>
      <p style="font-size:1.05rem;font-weight:700;color:var(--navy-dark);margin-bottom:8px">Aucune mission dans cette catégorie.</p>
      <p style="font-size:.9rem;margin-bottom:20px">Essaie un autre filtre ou reviens sur "Toutes".</p>
      <button onclick="filterMissions(document.querySelector('[data-filter=all]'),'all')"
              style="padding:10px 24px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-weight:700;cursor:pointer">
        Voir toutes les missions
      </button>
    </div>

  </div>
</section>

<!-- SECTION 3 — LE MYSTÈRE DE LA SAISON -->
<section id="mystere-saison">
  <div class="container" style="padding-top:64px">
    <div class="ktc-teaser reveal">
      <div class="ktc-teaser-body">
        <span class="ktc-teaser-eyebrow">Chercher &amp; identifier · K&eacute;tokole Tch&egrave;</span>
        <div class="ktc-teaser-title">🔍 K&eacute;TOKOLE TCH&Egrave; ?</div>
        <p class="ktc-teaser-text">Un objet, un lieu, un d&eacute;tail vend&eacute;en &mdash; K&eacute;tokole Tch&egrave;&nbsp;? Chaque &eacute;pisode lance un appel collectif&nbsp;: proposez une piste, identifiez le myst&egrave;re, r&eacute;veillez un souvenir.</p>
      </div>
      <div class="ktc-teaser-cta">
        <a href="ktc.php" class="btn btn-primary">Jouer au KTC →</a>
      </div>
    </div>
  </div>
</section>

<!-- BLOC — COMMENT CA FONCTIONNE -->
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

<!-- ESPRIT DES MISSIONS -->
<section id="esprit-missions">
  <div class="container">
    <div class="reveal" style="max-width:720px;margin:0 auto 16px;text-align:center">
      <p class="overline-label">L'esprit Zone85</p>
      <p style="font-size:1.08rem;color:var(--navy-dark);line-height:1.7">Sur Zone85, les missions ne sont pas des devoirs à faire tous les jours. Ce sont des appels lancés aux Zonautes : partager une photo, identifier un objet, suivre une piste, répondre à un quiz ou réveiller un souvenir.</p>
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
(function() {
  /* ── Filtre missions ── */
  window.filterMissions = function(btn, type) {
    document.querySelectorAll(".filter-tab-v2").forEach(function(b) { b.classList.remove("active"); });
    btn.classList.add("active");
    var visible = 0;
    document.querySelectorAll("#missions-grid .mission-card").forEach(function(card) {
      if (type === "all" || card.dataset.type === type) {
        card.style.display = "";
        visible++;
      } else {
        card.style.display = "none";
      }
    });
    var emptyEl = document.getElementById("missions-filter-empty");
    if (emptyEl) emptyEl.style.display = (visible === 0 && type !== "all") ? "" : "none";
  };

  /* ── Anime les barres de progression ── */
  function animateBars() {
    document.querySelectorAll("[data-width]").forEach(function(el) {
      var rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight - 60 && !el.dataset.animated) {
        el.dataset.animated = "1";
        el.style.width = el.dataset.width + "%";
      }
    });
  }
  window.addEventListener("scroll", animateBars, { passive: true });
  animateBars();

  /* ── Compte à rebours Grand Défi ── */
  var cdMs = ' . (int)$gd_countdown_ms . ';
  if (cdMs > 0) {
    var end = Date.now() + cdMs;
    function updateCountdown() {
      var diff = Math.max(0, end - Date.now());
      var d = Math.floor(diff / 86400000);
      var h = Math.floor((diff % 86400000) / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      var el = function(id) { return document.getElementById(id); };
      if (el("gd-cd-days"))  el("gd-cd-days").textContent  = d;
      if (el("gd-cd-hours")) el("gd-cd-hours").textContent = h < 10 ? "0" + h : h;
      if (el("gd-cd-min"))   el("gd-cd-min").textContent   = m < 10 ? "0" + m : m;
      if (el("gd-cd-sec"))   el("gd-cd-sec").textContent   = s < 10 ? "0" + s : s;
      if (diff > 0) setTimeout(updateCountdown, 1000);
    }
    updateCountdown();
  }
})();
</script>';
render_hidden_collectibles('missions');
require_once 'includes/footer.php';
?>
