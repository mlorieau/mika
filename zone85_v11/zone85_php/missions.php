<?php
$page_title       = 'Missions';
$page_description = 'Explore les missions vendéennes : quiz, défis photo, Kéto Kolé Tché, randos, météo-missions, enquêtes. Participe, gagne des XP, fais progresser ton clan.';
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

// IDs des missions déjà faites par l'utilisateur connecté
$_participated_ids = [];
if (db_enabled() && is_logged_in()) {
    $_nav_sess = current_user();
    if ($_nav_sess) {
        $_participated_ids = fetch_user_participated_mission_ids((int)$_nav_sess['id']);
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
        'active'   => 'Actif',
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
        'vote'            => 'vote',
    ][$type] ?? 'vote';
}

$page_styles = '<style>

/* ============================================================
   MISSIONS PAGE V11 — Internal Styles
   Mobile-first
============================================================ */

/* HERO */
.missions-hero {
  background: linear-gradient(160deg, #0d1e2c 0%, #12314e 55%, #163756 100%);
  padding: 110px 0 64px;
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
.tag-photo    { background: rgba(99,102,241,.12);  color: #4f46e5; }
.tag-quiz     { background: rgba(234,86,73,.1);    color: var(--primary); }
.tag-rando    { background: rgba(30,92,48,.12);    color: #1e5c30; }
.tag-ktc      { background: rgba(201,150,42,.15);  color: #8a6020; }
.tag-meteo    { background: rgba(14,165,233,.12);  color: #0369a1; }
.tag-enquete  { background: rgba(168,85,247,.1);   color: #7c3aed; }
.tag-saison   { background: rgba(201,150,42,.15);  color: #8a6020; border: 1px solid rgba(201,150,42,.25); }
.tag-vote     { background: rgba(234,86,73,.08);   color: var(--text-mid); }
.tag-rapide   { background: rgba(234,86,73,.08);   color: var(--text-mid); }

.mission-already-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: .65rem;
  font-weight: 800;
  background: rgba(42,157,92,.1);
  color: #1a7a42;
  padding: 4px 10px;
  border-radius: 5px;
  letter-spacing: .04em;
}
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
    <?php if (!empty($active_season)): ?>
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
      <a href="#mission-list" class="btn btn-outline" style="border-color:rgba(255,255,255,.25);color:rgba(255,255,255,.7)">Toutes les missions</a>
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
      <div class="gd-clan-section-title">Course des clans · Saison en cours</div>
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
      <p class="overline-label">Tous les formats de participation</p>
      <h2>Explore les missions</h2>
      <p>Filtre par catégorie et trouve ce qui t'inspire.</p>
    </div>

    <div class="filter-tabs-v2 reveal" id="mission-filters">
      <button class="filter-tab-v2 active" data-filter="all" onclick="filterMissions(this,'all')">
        <span class="tab-icon">🗺️</span> Tout voir
      </button>
      <button class="filter-tab-v2" data-filter="quiz" onclick="filterMissions(this,'quiz')">
        <span class="tab-icon">🧠</span> Quiz
      </button>
      <button class="filter-tab-v2" data-filter="photo" onclick="filterMissions(this,'photo')">
        <span class="tab-icon">📸</span> Défi Photo
      </button>
      <button class="filter-tab-v2" data-filter="rando" onclick="filterMissions(this,'rando')">
        <span class="tab-icon">🥾</span> Rando
      </button>
      <button class="filter-tab-v2" data-filter="meteo" onclick="filterMissions(this,'meteo')">
        <span class="tab-icon">🌤️</span> Météo
      </button>
      <button class="filter-tab-v2" data-filter="enquete" onclick="filterMissions(this,'enquete')">
        <span class="tab-icon">🔍</span> Enquête
      </button>
      <button class="filter-tab-v2" data-filter="vote" onclick="filterMissions(this,'vote')">
        <span class="tab-icon">🗳️</span> Vote
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
          'weather_mission' => 'Participer',
          'investigation'   => 'Enquêter',
          'vote'            => 'Voter',
      ];
      foreach ($missions_list as $i => $mission):
          $tag_class    = mission_type_tag_class($mission['mission_type']);
          $filter_type  = mission_filter_type($mission['mission_type']);
          $icon         = $mission_icons[$mission['mission_type']] ?? '📌';
          $status_class = mission_status_class($mission['status']);
          $status_label = mission_status_label($mission['status']);
          $cta_label    = $cta_labels[$mission['mission_type']] ?? 'Voir';
          $xp_display   = '+' . (int)$mission['xp_participation'] . ' XP';
          $participated = in_array((int)$mission['id'], $_participated_ids);
          $delay        = $i > 0 ? ' style="transition-delay:' . round($i * 0.04, 2) . 's"' : '';
      ?>
      <div class="mission-card reveal" data-type="<?= e($filter_type) ?>"<?= $delay ?>>
        <div class="mission-card-header">
          <div class="mission-card-header-top">
            <span class="mission-type-tag tag-<?= e($tag_class) ?>"><?= $icon ?> <?= e(mission_type_label($mission['mission_type'])) ?></span>
            <?php if ($participated): ?>
            <span class="mission-already-badge">✓ Déjà participé</span>
            <?php endif; ?>
          </div>
          <div class="mission-title"><?= e($mission['title']) ?></div>
          <p class="mission-desc"><?= e($mission['description']) ?></p>
        </div>
        <div class="mission-card-footer">
          <span class="mission-xp"><?= e($xp_display) ?></span>
          <?php if (!$participated): ?>
          <span class="mission-status <?= e($status_class) ?>"><?= e($status_label) ?></span>
          <?php endif; ?>
          <?php if ($mission['status'] === 'active' && !$participated): ?>
          <a href="mission.php?id=<?= (int)$mission['id'] ?>" class="mission-cta-link"><?= e($cta_label) ?></a>
          <?php elseif ($mission['status'] === 'archived' && !empty($mission['display_in_hall'])): ?>
          <a href="<?= page_url('hall') ?>" class="mission-cta-link">Voir le Hall</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($missions_list)): ?>
    <div style="text-align:center;padding:48px 0;color:var(--text-muted)">
      <div style="font-size:2.5rem;margin-bottom:12px">🗺️</div>
      <p>Les missions arrivent bientôt. Reviens dans la Zone !</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- SECTION 3 — LE MYSTÈRE DE LA SAISON -->
<section id="mystere-saison">
  <div class="container" style="padding-top:64px">
    <div class="ktc-teaser reveal">
      <div class="ktc-teaser-body">
        <span class="ktc-teaser-eyebrow">Le Mystère de la Saison</span>
        <div class="ktc-teaser-title">🥐 LE MYSTÈRE DE LA SAISON</div>
        <p class="ktc-teaser-text">Chaque saison, un objet étrange apparaît. À vous de découvrir son origine.</p>
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
(function() {
  /* ── Filtre missions ── */
  window.filterMissions = function(btn, type) {
    document.querySelectorAll(".filter-tab-v2").forEach(function(b) { b.classList.remove("active"); });
    btn.classList.add("active");
    document.querySelectorAll("#missions-grid .mission-card").forEach(function(card) {
      if (type === "all" || card.dataset.type === type) {
        card.style.display = "";
      } else {
        card.style.display = "none";
      }
    });
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
