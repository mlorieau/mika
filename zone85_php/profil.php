<?php
$page_title       = 'Mon Profil';
$page_description = 'Consulte ta progression, tes badges, ton XP à vie et ta contribution à la Bataille des Clans sur ZONE85.';
$page_canonical   = 'https://www.zone85.fr/profil.php';
$page_robots      = 'noindex,follow';
$page_og_image    = null;
$page_schema      = null;
$current_page = 'profil';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';

// Mock user data — à remplacer par la session réelle
$user = [
    'pseudo'          => 'Sophie M.',
    'first_name'      => 'Sophie',
    'initials'        => 'SM',
    'avatar'          => '🧭',
    'level'           => 7,
    'level_name'      => 'Grand Pisteur',
    'xp_current'      => 3400,
    'xp_next'         => 4000,
    'xp_pct'          => 85,
    'badges_count'    => 6,
    'participations'  => 14,
    'clan_slug'       => 'littoral',
    'clan_label'      => 'Clan du Littoral',
    'clan_chip_class' => 'littoral-chip-sm',
    'season_pts'      => 680,
    'clan_rank'       => 7,
    'clan_members'    => 438,
    'clan_score'      => 12840,
    'clan_place'      => 1,
];

// ── Repository layer — override $user depuis MySQL si disponible ──
// TODO auth membre : remplacer fetch_demo_user(1) par l'utilisateur connecté en session
require_once 'includes/db.php';
require_once 'includes/repositories.php';
if (db_enabled()) {
    $_demo = fetch_demo_user(1);
    if ($_demo) {
        // Calcul de la barre XP par niveau
        $_xp_levels = [0, 100, 300, 600, 1000, 1500, 2500, 4000, 6000, 9000, 13000, 18000];
        $_lvl        = max(1, min((int)$_demo['level'], count($_xp_levels) - 1));
        $_xp_floor   = $_xp_levels[$_lvl - 1] ?? 0;
        $_xp_ceil    = $_xp_levels[$_lvl]     ?? ($_xp_floor + 5000);
        $_xp_range   = max(1, $_xp_ceil - $_xp_floor);
        $_xp_pct     = min(100, (int)round(((int)$_demo['xp_total'] - $_xp_floor) / $_xp_range * 100));
        $_level_names = ['','Novice','Éclaireur','Pisteur','Ranger','Garde','Chasseur',
                         'Grand Pisteur','Vétéran','Légende','Ancêtre','Immortel'];
        $_chip_map   = ['bocage'=>'bocage-chip-sm','littoral'=>'littoral-chip-sm','marais'=>'marais-chip-sm'];
        $_clan_labels = ['bocage'=>'Clan du Bocage','littoral'=>'Clan du Littoral','marais'=>'Clan du Marais'];
        $_prenom     = $_demo['prenom'] ?: $_demo['pseudo'];
        $_nom        = $_demo['nom']    ?: '';
        $_initials   = strtoupper(mb_substr($_prenom, 0, 1) . mb_substr($_nom, 0, 1)) ?: '??';
        // Récupérer les données du clan depuis MySQL
        $_clan_data  = null;
        if ($_demo['clan_slug']) {
            $_all_clans = fetch_all_clans();
            $_clan_data = $_all_clans[$_demo['clan_slug']] ?? null;
        }
        $user = [
            'pseudo'          => $_demo['pseudo'],
            'first_name'      => $_prenom,
            'initials'        => $_initials,
            'avatar'          => $_demo['avatar'] ?? '🧭',
            'level'           => $_lvl,
            'level_name'      => $_level_names[$_lvl] ?? 'Zonaute',
            'xp_current'      => (int)$_demo['xp_total'],
            'xp_next'         => $_xp_ceil,
            'xp_pct'          => $_xp_pct,
            'badges_count'    => (int)$_demo['badges_count'],
            'participations'  => (int)$_demo['missions_done'],
            'clan_slug'       => $_demo['clan_slug'] ?? '',
            'clan_label'      => $_clan_labels[$_demo['clan_slug'] ?? ''] ?? 'Aucun clan',
            'clan_chip_class' => $_chip_map[$_demo['clan_slug'] ?? ''] ?? '',
            'season_pts'      => (int)$_demo['xp_this_season'],
            'clan_rank'       => (int)$_demo['rank_in_clan'],
            'clan_members'    => $_clan_data ? (int)$_clan_data['members_count'] : 0,
            'clan_score'      => $_clan_data ? (int)$_clan_data['season_score']  : 0,
            'clan_place'      => $_clan_data ? (int)$_clan_data['podium_rank']   : 0,
        ];
        // Badges réels de cet utilisateur
        $_user_badges = fetch_user_badges($_demo['id']);
        if (!empty($_user_badges)) $badges = $_user_badges;
    }
}

$page_styles = '<style>
/* ── PAGE LAYOUT ── */
.profil-page { padding-top: 68px; min-height: 100vh; background: var(--beige); }
.profil-layout { max-width: 1200px; margin: 0 auto; padding: 40px 24px 80px; display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: start; }

/* ── SIDEBAR ── */
.profil-sidebar { background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 28px 24px; position: sticky; top: 88px; }
.sidebar-avatar { width: 72px; height: 72px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 14px; animation: pulse 2.4s ease-in-out infinite; }
.sidebar-name { font-size: 1.1rem; font-weight: 900; color: var(--text); letter-spacing: -.3px; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.level-badge { display: inline-block; background: var(--primary); color: #fff; font-size: .65rem; font-weight: 800; padding: 3px 9px; border-radius: 4px; letter-spacing: .06em; flex-shrink: 0; }
.sidebar-separator { border: none; border-top: 1px solid var(--beige-dark); margin: 20px 0; }
.sidebar-section-label { font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.xp-bar-wrap { margin-bottom: 16px; }
.xp-bar-meta { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
.xp-bar-label { font-size: .82rem; font-weight: 700; color: var(--text); }
.xp-bar-pct { font-size: .72rem; font-weight: 700; color: var(--primary); }
.xp-bar-track { height: 10px; background: var(--beige-dark); border-radius: 6px; overflow: hidden; margin-bottom: 5px; }
.xp-bar-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--primary-dark), var(--primary-light)); width: 0; transition: width 1.2s cubic-bezier(.22,1,.36,1); }
.xp-bar-sub { font-size: .7rem; color: var(--text-muted); }
.sidebar-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 4px; }
.sidebar-stat { background: var(--beige); border-radius: var(--radius-sm); padding: 10px 8px; text-align: center; }
.sidebar-stat-val { display: block; font-size: .95rem; font-weight: 900; color: var(--text); line-height: 1.1; }
.sidebar-stat-lbl { display: block; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-top: 3px; }
.clan-contribution-val { font-size: 1.6rem; font-weight: 900; color: var(--primary); letter-spacing: -.5px; line-height: 1.1; margin-bottom: 4px; }
.clan-contribution-sub { font-size: .78rem; color: var(--text-mid); margin-bottom: 10px; }
.clan-rank-line { font-size: .82rem; font-weight: 700; color: var(--text); background: var(--beige); border-radius: var(--radius-sm); padding: 8px 12px; display: flex; align-items: center; gap: 6px; }
.clan-rank-line span { color: var(--primary); font-weight: 900; }
.sidebar-nav { display: flex; flex-direction: column; gap: 2px; }
.sidebar-nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius-sm); font-size: .88rem; font-weight: 600; color: var(--text-mid); cursor: pointer; transition: all .18s; border: none; background: none; font-family: \'Inter\', sans-serif; width: 100%; text-align: left; }
.sidebar-nav-link:hover { background: var(--beige); color: var(--text); }
.sidebar-nav-link.active { background: rgba(234,86,73,.08); color: var(--primary); font-weight: 800; }
.sidebar-nav-icon { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }

/* ── MAIN CONTENT ── */
.profil-content { min-width: 0; }

/* ── TAB PANEL SHARED ── */
.profil-panel { animation: fadeUp .3s ease both; }
.profil-card { background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 28px; margin-bottom: 20px; }
.profil-card-title { font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

/* ── TAB 1 : APERÇU ── */
.welcome-card { background: linear-gradient(135deg, var(--navy-dark), var(--navy-mid)); border-radius: var(--radius-lg); padding: 28px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; position: relative; overflow: hidden; }
.welcome-card::after { content: \'\'; position: absolute; top: -40px; right: -40px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.04); }
.welcome-avatar { width: 56px; height: 56px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; position: relative; z-index: 1; }
.welcome-text { position: relative; z-index: 1; }
.welcome-text h2 { font-size: 1.2rem; font-weight: 900; color: #fff; letter-spacing: -.3px; margin-bottom: 4px; }
.welcome-text p { font-size: .82rem; color: rgba(255,255,255,.6); line-height: 1.5; }
.welcome-season { margin-left: auto; flex-shrink: 0; position: relative; z-index: 1; text-align: right; }
.welcome-season-label { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.35); margin-bottom: 4px; }
.welcome-season-name { font-size: .88rem; font-weight: 800; color: rgba(255,255,255,.85); }

.mission-item { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--beige-dark); }
.mission-item:last-child { border-bottom: none; padding-bottom: 0; }
.mission-item:first-child { padding-top: 0; }
.mission-icon { font-size: 1.3rem; width: 36px; height: 36px; background: var(--beige); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mission-info { flex: 1; min-width: 0; }
.mission-name { font-size: .9rem; font-weight: 700; color: var(--text); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mission-sub { font-size: .72rem; color: var(--text-muted); margin-bottom: 6px; }
.mission-prog-track { height: 5px; background: var(--beige-dark); border-radius: 4px; overflow: hidden; }
.mission-prog-fill { height: 100%; border-radius: 4px; background: var(--primary); width: 0; transition: width 1.2s cubic-bezier(.22,1,.36,1); }
.mission-xp { font-size: .8rem; font-weight: 800; color: var(--primary); white-space: nowrap; flex-shrink: 0; }

.feed-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--beige-dark); }
.feed-item:last-child { border-bottom: none; padding-bottom: 0; }
.feed-item:first-child { padding-top: 0; }
.feed-icon { font-size: 1.1rem; width: 32px; height: 32px; background: var(--beige); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
.feed-info { flex: 1; }
.feed-title { font-size: .88rem; font-weight: 700; color: var(--text); margin-bottom: 2px; }
.feed-meta { font-size: .72rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
.feed-xp { display: inline-block; background: rgba(234,86,73,.1); color: var(--primary); font-size: .68rem; font-weight: 800; padding: 1px 7px; border-radius: 3px; }

/* ── TAB 2 : PROGRESSION ── */
.roadmap-wrap { position: relative; padding: 16px 0 32px; overflow-x: auto; }
.roadmap-line { display: flex; align-items: center; gap: 0; min-width: 640px; }
.roadmap-node { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
.roadmap-node::before { content: \'\'; position: absolute; top: 20px; right: -50%; width: 100%; height: 3px; background: var(--beige-dark); z-index: 0; }
.roadmap-node:last-child::before { display: none; }
.roadmap-node.done::before { background: var(--primary); }
.roadmap-dot { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 900; border: 3px solid var(--beige-dark); background: var(--white); color: var(--text-muted); position: relative; z-index: 1; flex-shrink: 0; transition: all .3s; }
.roadmap-node.done .roadmap-dot { background: var(--primary); border-color: var(--primary); color: #fff; font-size: .8rem; }
.roadmap-node.current .roadmap-dot { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 0 5px rgba(234,86,73,.2); font-size: .75rem; }
.roadmap-label { margin-top: 10px; text-align: center; font-size: .72rem; font-weight: 700; color: var(--text-muted); line-height: 1.3; }
.roadmap-node.done .roadmap-label { color: var(--text-mid); }
.roadmap-node.current .roadmap-label { color: var(--primary); font-weight: 900; }
.roadmap-xp-req { font-size: .65rem; font-weight: 600; color: var(--text-muted); margin-top: 3px; }
.roadmap-node.current .roadmap-xp-req { color: rgba(234,86,73,.6); }
.next-level-banner { background: rgba(234,86,73,.07); border: 1px solid rgba(234,86,73,.18); border-radius: var(--radius); padding: 14px 18px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: .88rem; font-weight: 600; color: var(--text); }
.next-level-banner strong { color: var(--primary); }
.xp-table { width: 100%; border-collapse: collapse; }
.xp-table th { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--text-muted); padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--beige-dark); background: var(--beige); }
.xp-table th:first-child { border-radius: var(--radius-sm) 0 0 0; }
.xp-table th:last-child  { border-radius: 0 var(--radius-sm) 0 0; }
.xp-table td { padding: 10px 12px; font-size: .85rem; color: var(--text-mid); border-bottom: 1px solid var(--beige-dark); vertical-align: middle; }
.xp-table tr:last-child td { border-bottom: none; }
.xp-table tr:nth-child(even) td { background: rgba(245,241,237,.5); }
.xp-amount { font-weight: 800; color: var(--primary); white-space: nowrap; }

/* ── TAB 3 : BADGES ── */
.badges-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.badge-card { background: var(--beige); border-radius: var(--radius); padding: 18px 14px; text-align: center; border: 1px solid var(--beige-dark); transition: transform .2s, box-shadow .2s; }
.badge-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); }
.badge-emoji { font-size: 1.8rem; display: block; margin-bottom: 8px; }
.badge-name { font-size: .82rem; font-weight: 800; color: var(--text); margin-bottom: 4px; line-height: 1.3; }
.badge-date { font-size: .65rem; color: var(--text-muted); margin-bottom: 5px; }
.badge-xp { display: inline-block; background: rgba(234,86,73,.1); color: var(--primary); font-size: .65rem; font-weight: 800; padding: 2px 8px; border-radius: 3px; }
.badge-card.locked { opacity: .55; background: var(--beige-dark); border-color: transparent; cursor: default; }
.badge-card.locked:hover { transform: none; box-shadow: none; }
.badge-card.locked .badge-name { color: var(--text-muted); }
.badge-lock { font-size: .65rem; color: var(--text-muted); margin-bottom: 4px; }
.badge-prog { font-size: .65rem; color: var(--text-muted); margin-top: 4px; }
.badge-prog-track { height: 4px; background: rgba(0,0,0,.1); border-radius: 3px; overflow: hidden; margin-top: 5px; }
.badge-prog-fill { height: 100%; background: var(--text-muted); border-radius: 3px; }

/* ── TAB 4 : ACTIVITÉ ── */
.activity-chart { display: flex; align-items: flex-end; gap: 16px; height: 120px; padding: 0 8px; margin-bottom: 8px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.chart-bar-wrap { width: 100%; display: flex; align-items: flex-end; justify-content: center; flex: 1; }
.chart-bar { width: 64%; border-radius: 4px 4px 0 0; background: var(--primary); height: 0; transition: height 1s cubic-bezier(.22,1,.36,1); min-height: 4px; opacity: .85; }
.chart-bar.highlight { opacity: 1; background: var(--primary-dark); }
.chart-label { font-size: .68rem; font-weight: 700; color: var(--text-muted); text-align: center; margin-top: 6px; white-space: nowrap; }
.chart-val { font-size: .75rem; font-weight: 800; color: var(--primary); text-align: center; margin-bottom: 4px; }
.activity-axis { display: flex; gap: 16px; padding: 0 8px; border-top: 1px solid var(--beige-dark); margin-bottom: 28px; }
.activity-axis-col { flex: 1; text-align: center; padding-top: 6px; font-size: .68rem; color: var(--text-muted); font-weight: 600; }
.activity-filter { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
.activity-filter-btn { padding: 6px 14px; border-radius: var(--radius-sm); font-size: .78rem; font-weight: 700; cursor: pointer; border: 2px solid var(--beige-dark); background: var(--white); color: var(--text-mid); font-family: \'Inter\', sans-serif; transition: all .18s; }
.activity-filter-btn:hover { border-color: var(--primary); color: var(--primary); }
.activity-filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

/* ── CTA BAS ── */
.profil-cta { background: var(--primary); border-radius: var(--radius-lg); padding: 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; position: relative; overflow: hidden; }
.profil-cta::before { content: \'\'; position: absolute; top: -30px; right: -30px; width: 140px; height: 140px; border-radius: 50%; background: rgba(255,255,255,.06); }
.profil-cta-text { position: relative; z-index: 1; }
.profil-cta-label { font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.55); margin-bottom: 4px; }
.profil-cta-title { font-size: 1.05rem; font-weight: 900; color: #fff; margin-bottom: 4px; letter-spacing: -.3px; }
.profil-cta-score { font-size: .82rem; color: rgba(255,255,255,.75); font-weight: 600; }
.profil-cta-score strong { color: #fff; font-weight: 900; }
.profil-cta-action { position: relative; z-index: 1; flex-shrink: 0; }
.btn-cta-white { background: #fff; color: var(--primary); border: none; padding: 12px 22px; border-radius: var(--radius-sm); font-weight: 800; font-size: .88rem; font-family: \'Inter\', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; text-decoration: none; white-space: nowrap; }
.btn-cta-white:hover { background: var(--beige-light); transform: translateY(-1px); }

/* ── RESPONSIVE ── */
@media(max-width: 900px) {
  .profil-layout { grid-template-columns: 1fr; padding: 24px 16px 60px; gap: 20px; }
  .profil-sidebar { position: static; }
  .sidebar-nav { flex-direction: row; flex-wrap: wrap; gap: 4px; }
  .sidebar-nav-link { flex: 1 1 auto; min-width: 110px; justify-content: center; font-size: .8rem; padding: 8px 10px; }
  .badges-grid { grid-template-columns: repeat(2, 1fr); }
  .welcome-season { display: none; }
  .profil-cta { flex-direction: column; text-align: center; }
}
@media(max-width: 480px) {
  .badges-grid { grid-template-columns: repeat(2, 1fr); }
  .sidebar-stats-row { grid-template-columns: repeat(3, 1fr); }
}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="profil-page">
  <div class="profil-layout">

    <!-- ── SIDEBAR ── -->
    <aside class="profil-sidebar">

      <div class="sidebar-avatar"><?= e($user['avatar']) ?></div>
      <div class="sidebar-name"><?= e($user['pseudo']) ?> <span class="level-badge">Niv. <?= e($user['level']) ?></span></div>
      <span class="<?= e($user['clan_chip_class']) ?>"><?= e($user['clan_label']) ?></span>

      <hr class="sidebar-separator">
      <div class="sidebar-section-label">Ma progression personnelle</div>

      <div class="xp-bar-wrap">
        <div class="xp-bar-meta">
          <span class="xp-bar-label"><?= e($user['level_name']) ?></span>
          <span class="xp-bar-pct"><?= e($user['xp_pct']) ?> %</span>
        </div>
        <div class="xp-bar-track">
          <div class="xp-bar-fill" data-xp-pct="<?= e($user['xp_pct']) ?>"></div>
        </div>
        <div class="xp-bar-sub"><?= format_xp($user['xp_current']) ?> / <?= format_xp($user['xp_next']) ?> XP</div>
      </div>

      <div class="sidebar-stats-row">
        <div class="sidebar-stat">
          <span class="sidebar-stat-val"><?= format_xp($user['xp_current']) ?></span>
          <span class="sidebar-stat-lbl">XP à vie</span>
        </div>
        <div class="sidebar-stat">
          <span class="sidebar-stat-val"><?= e($user['badges_count']) ?></span>
          <span class="sidebar-stat-lbl">Badges</span>
        </div>
        <div class="sidebar-stat">
          <span class="sidebar-stat-val"><?= e($user['participations']) ?></span>
          <span class="sidebar-stat-lbl">Participations</span>
        </div>
      </div>

      <hr class="sidebar-separator">
      <div class="sidebar-section-label">
        Ma contribution à la saison
        <span class="live-badge">LIVE</span>
      </div>

      <div class="clan-contribution-val">+<?= format_score($user['season_pts']) ?> pts</div>
      <div class="clan-contribution-sub">apportés au <?= e($user['clan_label']) ?> — <?= e($active_season['title']) ?></div>
      <div class="clan-rank-line">🏅 <span><?= e($user['clan_rank']) ?>e</span> sur <?= format_score($user['clan_members']) ?> membres du clan</div>

      <hr class="sidebar-separator">
      <nav class="sidebar-nav">
        <button class="sidebar-nav-link active" data-tab-group="profil" data-tab-id="apercu" onclick="switchTab('profil','apercu')">
          <span class="sidebar-nav-icon">👁</span> Aperçu
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="progression" onclick="switchTab('profil','progression')">
          <span class="sidebar-nav-icon">📈</span> Progression
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="badges" onclick="switchTab('profil','badges')">
          <span class="sidebar-nav-icon">🏅</span> Badges
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="activite" onclick="switchTab('profil','activite')">
          <span class="sidebar-nav-icon">📋</span> Activité
        </button>
      </nav>

    </aside>

    <!-- ── CONTENU PRINCIPAL ── -->
    <main class="profil-content">

      <!-- ══════════════════════════════
           TAB 1 — APERÇU
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="apercu" class="profil-panel">

        <div class="welcome-card">
          <div class="welcome-avatar"><?= e($user['avatar']) ?></div>
          <div class="welcome-text">
            <h2>Bonjour <?= e($user['first_name']) ?> !</h2>
            <p>Je progresse pour moi. Je fais gagner mon clan.</p>
          </div>
          <div class="welcome-season">
            <div class="welcome-season-label">Saison en cours</div>
            <div class="welcome-season-name">☀️ <?= e($active_season['title']) ?></div>
          </div>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">🗺️ Mes missions en cours</div>

          <div class="mission-item">
            <div class="mission-icon">🗺️</div>
            <div class="mission-info">
              <div class="mission-name"><?= e($active_season['main_mission']) ?></div>
              <div class="mission-sub">Contribution au clan · Mission saison</div>
              <div class="mission-prog-track">
                <div class="mission-prog-fill" data-prog="82"></div>
              </div>
            </div>
            <div class="mission-xp">82 %</div>
          </div>

          <div class="mission-item">
            <div class="mission-icon">🧠</div>
            <div class="mission-info">
              <div class="mission-name">Quiz Marais Poitevin</div>
              <div class="mission-sub">+45 XP personnel</div>
              <div class="mission-prog-track">
                <div class="mission-prog-fill" data-prog="0" style="background:var(--beige-dark)"></div>
              </div>
            </div>
            <div class="mission-xp" style="color:var(--text-muted)">Non commencé</div>
          </div>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">⚡ Dernières actions</div>

          <div class="feed-item">
            <div class="feed-icon">📸</div>
            <div class="feed-info">
              <div class="feed-title">Défi photo Noirmoutier</div>
              <div class="feed-meta"><span class="feed-xp">+60 XP</span> · il y a 2 jours</div>
            </div>
          </div>

          <div class="feed-item">
            <div class="feed-icon">🎯</div>
            <div class="feed-info">
              <div class="feed-title">Quiz Vendée parfait — 10/10</div>
              <div class="feed-meta"><span class="feed-xp">+60 XP</span> · badge 🏹 débloqué · il y a 3 jours</div>
            </div>
          </div>

          <div class="feed-item">
            <div class="feed-icon">🔍</div>
            <div class="feed-info">
              <div class="feed-title">Kéto Kolé Tché identifié</div>
              <div class="feed-meta"><span class="feed-xp">+80 XP</span> · il y a 5 jours</div>
            </div>
          </div>

          <div class="feed-item">
            <div class="feed-icon">🌦️</div>
            <div class="feed-info">
              <div class="feed-title">Météo-mission : grande marée</div>
              <div class="feed-meta"><span class="feed-xp">+30 XP</span> · il y a 1 semaine</div>
            </div>
          </div>

          <div class="feed-item">
            <div class="feed-icon">🗳️</div>
            <div class="feed-info">
              <div class="feed-title">3 votes communauté</div>
              <div class="feed-meta"><span class="feed-xp">+15 XP</span> · il y a 1 semaine</div>
            </div>
          </div>
        </div>

        <div class="profil-cta">
          <div class="profil-cta-text">
            <div class="profil-cta-label">Saison <?= e($active_season['title']) ?></div>
            <div class="profil-cta-title">Tu joues pour le <?= e($user['clan_label']) ?></div>
            <div class="profil-cta-score">Score clan : <strong><?= format_score($user['clan_score']) ?> pts</strong> — Place <strong>n°<?= e($user['clan_place']) ?></strong> 🥇</div>
          </div>
          <div class="profil-cta-action">
            <a href="clans.php" class="btn-cta-white">Voir la course des clans →</a>
          </div>
        </div>

      </div><!-- /panel apercu -->


      <!-- ══════════════════════════════
           TAB 2 — PROGRESSION
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="progression" class="profil-panel" style="display:none">

        <div class="profil-card">
          <div class="profil-card-title">🛤️ Roadmap des niveaux</div>

          <div class="roadmap-wrap">
            <div class="roadmap-line">

              <div class="roadmap-node done">
                <div class="roadmap-dot">✓</div>
                <div class="roadmap-label">Néophyte<div class="roadmap-xp-req">0 XP</div></div>
              </div>

              <div class="roadmap-node done">
                <div class="roadmap-dot">✓</div>
                <div class="roadmap-label">Explorateur<div class="roadmap-xp-req">500 XP</div></div>
              </div>

              <div class="roadmap-node done">
                <div class="roadmap-dot">✓</div>
                <div class="roadmap-label">Pisteur<div class="roadmap-xp-req">1 500 XP</div></div>
              </div>

              <div class="roadmap-node current">
                <div class="roadmap-dot" style="font-size:.65rem;letter-spacing:-.5px;">Grand<br>Pisteur</div>
                <div class="roadmap-label">Grand Pisteur<div class="roadmap-xp-req">3 000 XP ← ici</div></div>
              </div>

              <div class="roadmap-node">
                <div class="roadmap-dot" style="font-size:.75rem">→</div>
                <div class="roadmap-label">Mémoire Vivante<div class="roadmap-xp-req">6 000 XP</div></div>
              </div>

              <div class="roadmap-node">
                <div class="roadmap-dot" style="font-size:.75rem">🏆</div>
                <div class="roadmap-label">Gardien Légendaire<div class="roadmap-xp-req">12 000 XP</div></div>
              </div>

            </div>
          </div>

          <div class="next-level-banner">
            🎯 Prochain niveau : <strong>&nbsp;Mémoire Vivante&nbsp;</strong> — encore <strong><?= format_xp($user['xp_next'] - $user['xp_current']) ?> XP</strong> à gagner
          </div>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">💡 Comment gagner des XP</div>
          <table class="xp-table">
            <thead>
              <tr>
                <th>Action</th>
                <th>XP gagnés</th>
                <th>Action</th>
                <th>XP gagnés</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Quiz réussi</td>
                <td class="xp-amount">+20 – 60</td>
                <td>Kéto Kolé Tché</td>
                <td class="xp-amount">+40 – 100</td>
              </tr>
              <tr>
                <td>Défi photo</td>
                <td class="xp-amount">+25 – 80</td>
                <td>Météo-mission</td>
                <td class="xp-amount">+15 – 40</td>
              </tr>
              <tr>
                <td>Avis rando</td>
                <td class="xp-amount">+30 – 50</td>
                <td>Vote &amp; commentaire</td>
                <td class="xp-amount">+5 – 15</td>
              </tr>
              <tr>
                <td>Mission saison</td>
                <td class="xp-amount">+100 – 300</td>
                <td>Identification parfaite</td>
                <td class="xp-amount">+80 – 150</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div><!-- /panel progression -->


      <!-- ══════════════════════════════
           TAB 3 — BADGES
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="badges" class="profil-panel" style="display:none">

        <div class="profil-card">
          <div class="profil-card-title">🏅 Badges obtenus <span style="color:var(--primary);font-size:.9em"><?= e($user['badges_count']) ?></span></div>
          <div class="badges-grid">

            <div class="badge-card">
              <span class="badge-emoji">🌊</span>
              <div class="badge-name">Marin d'eau douce</div>
              <div class="badge-date">Obtenu le 12 juin 2025</div>
              <span class="badge-xp">+50 XP</span>
            </div>

            <div class="badge-card">
              <span class="badge-emoji">📸</span>
              <div class="badge-name">L'Œil du Littoral</div>
              <div class="badge-date">Obtenu le 28 juin 2025</div>
              <span class="badge-xp">+75 XP</span>
            </div>

            <div class="badge-card">
              <span class="badge-emoji">🏹</span>
              <div class="badge-name">Chasseur de primes</div>
              <div class="badge-date">Obtenu il y a 3 jours</div>
              <span class="badge-xp">+60 XP</span>
            </div>

            <div class="badge-card">
              <span class="badge-emoji">⚡</span>
              <div class="badge-name">Streak 7 jours</div>
              <div class="badge-date">Obtenu le 15 mai 2025</div>
              <span class="badge-xp">+40 XP</span>
            </div>

            <div class="badge-card">
              <span class="badge-emoji">🥐</span>
              <div class="badge-name">Connaisseuse de brioche</div>
              <div class="badge-date">Obtenu le 3 mars 2025</div>
              <span class="badge-xp">+30 XP</span>
            </div>

            <div class="badge-card">
              <span class="badge-emoji">🗺️</span>
              <div class="badge-name">Exploratrice du bocage</div>
              <div class="badge-date">Obtenu le 8 avril 2025</div>
              <span class="badge-xp">+45 XP</span>
            </div>

          </div>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">🔒 Badges à débloquer</div>
          <div class="badges-grid">

            <div class="badge-card locked">
              <span class="badge-emoji">🏆</span>
              <div class="badge-name">Champion de saison</div>
              <div class="badge-lock">🔒 Gagner 1 trophée</div>
              <div class="badge-prog">0 / 1 trophée</div>
              <div class="badge-prog-track"><div class="badge-prog-fill" style="width:0%"></div></div>
            </div>

            <div class="badge-card locked">
              <span class="badge-emoji">⚔️</span>
              <div class="badge-name">Gardien Légendaire</div>
              <div class="badge-lock">🔒 Atteindre le niveau max</div>
              <div class="badge-prog">Niv. <?= e($user['level']) ?> / 12</div>
              <div class="badge-prog-track"><div class="badge-prog-fill" style="width:58%"></div></div>
            </div>

            <div class="badge-card locked">
              <span class="badge-emoji">🌿</span>
              <div class="badge-name">Sage des canaux</div>
              <div class="badge-lock">🔒 20 missions Kéto</div>
              <div class="badge-prog">2 / 20 missions</div>
              <div class="badge-prog-track"><div class="badge-prog-fill" style="width:10%"></div></div>
            </div>

            <div class="badge-card locked">
              <span class="badge-emoji">🦅</span>
              <div class="badge-name">Aigle du bocage</div>
              <div class="badge-lock">🔒 100 contributions</div>
              <div class="badge-prog"><?= e($user['participations']) ?> / 100 contributions</div>
              <div class="badge-prog-track"><div class="badge-prog-fill" style="width:<?= e($user['participations']) ?>%"></div></div>
            </div>

          </div>
        </div>

      </div><!-- /panel badges -->


      <!-- ══════════════════════════════
           TAB 4 — ACTIVITÉ
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="activite" class="profil-panel" style="display:none">

        <div class="profil-card">
          <div class="profil-card-title">📊 Activité — 4 dernières semaines</div>

          <div class="activity-chart" id="activityChart">
            <div class="chart-col">
              <div class="chart-val" id="val-s4">3</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar" id="bar-s4" data-height="33"></div>
              </div>
            </div>
            <div class="chart-col">
              <div class="chart-val" id="val-s3">7</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar" id="bar-s3" data-height="78"></div>
              </div>
            </div>
            <div class="chart-col">
              <div class="chart-val" id="val-s2">5</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar" id="bar-s2" data-height="56"></div>
              </div>
            </div>
            <div class="chart-col">
              <div class="chart-val" id="val-s1">9</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar highlight" id="bar-s1" data-height="100"></div>
              </div>
            </div>
          </div>
          <div class="activity-axis">
            <div class="activity-axis-col">S-4</div>
            <div class="activity-axis-col">S-3</div>
            <div class="activity-axis-col">S-2</div>
            <div class="activity-axis-col">Cette sem.</div>
          </div>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">📋 Historique des actions</div>

          <div class="activity-filter">
            <button class="activity-filter-btn active" onclick="filterActivity(this,'all')">Tout</button>
            <button class="activity-filter-btn" onclick="filterActivity(this,'mission')">Missions</button>
            <button class="activity-filter-btn" onclick="filterActivity(this,'keto')">Kéto Kolé Tché</button>
            <button class="activity-filter-btn" onclick="filterActivity(this,'badge')">Badges</button>
            <button class="activity-filter-btn" onclick="filterActivity(this,'quiz')">Quiz</button>
          </div>

          <div id="activityFeed">

            <div class="feed-item" data-activity-type="mission">
              <div class="feed-icon">🗺️</div>
              <div class="feed-info">
                <div class="feed-title">Le Grand Défi de l'Été — avancé à 82 %</div>
                <div class="feed-meta"><span class="feed-xp">Mission</span> · il y a 1 jour</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="mission">
              <div class="feed-icon">📸</div>
              <div class="feed-info">
                <div class="feed-title">Défi photo Noirmoutier</div>
                <div class="feed-meta"><span class="feed-xp">+60 XP</span> · Mission · il y a 2 jours</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="badge">
              <div class="feed-icon">🏹</div>
              <div class="feed-info">
                <div class="feed-title">Badge débloqué — Chasseur de primes</div>
                <div class="feed-meta"><span class="feed-xp">+60 XP</span> · Badge · il y a 3 jours</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="quiz">
              <div class="feed-icon">🎯</div>
              <div class="feed-info">
                <div class="feed-title">Quiz Vendée — score parfait 10/10</div>
                <div class="feed-meta"><span class="feed-xp">+60 XP</span> · Quiz · il y a 3 jours</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="keto">
              <div class="feed-icon">🔍</div>
              <div class="feed-info">
                <div class="feed-title">Kéto Kolé Tché — moulin de Rairé identifié</div>
                <div class="feed-meta"><span class="feed-xp">+80 XP</span> · Kéto · il y a 5 jours</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="mission">
              <div class="feed-icon">🌦️</div>
              <div class="feed-info">
                <div class="feed-title">Météo-mission : grande marée de vive-eau</div>
                <div class="feed-meta"><span class="feed-xp">+30 XP</span> · Mission · il y a 1 semaine</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="mission">
              <div class="feed-icon">🗳️</div>
              <div class="feed-info">
                <div class="feed-title">3 votes communauté validés</div>
                <div class="feed-meta"><span class="feed-xp">+15 XP</span> · Participation · il y a 1 semaine</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="keto">
              <div class="feed-icon">🔍</div>
              <div class="feed-info">
                <div class="feed-title">Kéto Kolé Tché — château du Puy du Fou</div>
                <div class="feed-meta"><span class="feed-xp">+90 XP</span> · Kéto · il y a 10 jours</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="quiz">
              <div class="feed-icon">🧠</div>
              <div class="feed-info">
                <div class="feed-title">Quiz Marais Poitevin — 8/10</div>
                <div class="feed-meta"><span class="feed-xp">+40 XP</span> · Quiz · il y a 12 jours</div>
              </div>
            </div>

            <div class="feed-item" data-activity-type="badge">
              <div class="feed-icon">⚡</div>
              <div class="feed-info">
                <div class="feed-title">Badge débloqué — Streak 7 jours</div>
                <div class="feed-meta"><span class="feed-xp">+40 XP</span> · Badge · il y a 2 semaines</div>
              </div>
            </div>

          </div><!-- /activityFeed -->
        </div>

      </div><!-- /panel activite -->

    </main>
  </div><!-- /profil-layout -->
</div><!-- /profil-page -->


<?php
$page_scripts = '<script>
const _origSwitchTab = switchTab;
window.switchTab = function(group, id) {
  _origSwitchTab(group, id);
  if (group === \'profil\') {
    document.querySelectorAll(\'.sidebar-nav-link\').forEach(btn => {
      btn.classList.toggle(\'active\', btn.dataset.tabId === id);
    });
    if (id === \'activite\') animateActivityChart();
  }
};

function animateActivityChart() {
  const maxH = 90;
  document.querySelectorAll(\'.chart-bar[data-height]\').forEach(bar => {
    const pct = parseInt(bar.dataset.height, 10) / 100;
    bar.style.height = Math.round(maxH * pct) + \'px\';
  });
}

function filterActivity(btn, type) {
  document.querySelectorAll(\'.activity-filter-btn\').forEach(b => b.classList.remove(\'active\'));
  btn.classList.add(\'active\');
  document.querySelectorAll(\'#activityFeed .feed-item\').forEach(item => {
    const match = type === \'all\' || item.dataset.activityType === type;
    item.style.display = match ? \'\' : \'none\';
  });
}

window.addEventListener(\'load\', () => {
  setTimeout(() => {
    document.querySelectorAll(\'.mission-prog-fill[data-prog]\').forEach(bar => {
      bar.style.width = bar.dataset.prog + \'%\';
    });
  }, 500);
});
</script>';
require_once 'includes/footer.php';
?>
