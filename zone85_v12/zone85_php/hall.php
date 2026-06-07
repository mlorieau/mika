<?php
$page_title       = 'Hall de la Zone';
$page_description = 'Le Hall de la Zone conserve la memoire vivante de la communaute vendeenne : top contributeurs, galerie des moments, archives de saisons et KTC resolus.';
$page_canonical   = 'https://www.zone85.fr/hall.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil',          'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Hall de la Zone',  'item' => 'https://www.zone85.fr/hall.php'],
    ],
];
$current_page = 'hall';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';

// ── Donnees DB ────────────────────────────────────────────────
$hall_contributors = [];
$hall_photos       = [];
$active_season     = null;
$hall_trophies     = [];
$flash_active      = null;
$ktc_count         = 0;
$ktc_last3         = [];

if (db_enabled()) {

    // Contributeurs
    $_contributors_db = fetch_hall_contributors(8);
    if ($_contributors_db !== null) $hall_contributors = $_contributors_db;

    // Photos / galerie
    $_photos_db = fetch_hall_photos(6);
    if ($_photos_db !== null) $hall_photos = $_photos_db;

    // Saison active
    $_season_db = fetch_active_season();
    if ($_season_db !== null) $active_season = $_season_db;

    // Trophees saisons closes
    if (function_exists('fetch_trophies')) {
        $_trophies_db = fetch_trophies();
        if ($_trophies_db !== null) $hall_trophies = $_trophies_db;
    }

    // Flash en cours (1 seul)
    try {
        $pdo = db();
        $stmt = $pdo->query("
            SELECT * FROM missions
            WHERE is_flash = 1 AND status = 'active'
              AND (flash_end_at IS NULL OR flash_end_at > NOW())
            ORDER BY flash_start_at DESC
            LIMIT 1
        ");
        $flash_active = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $flash_active = null;
    }

    // KTC : épisodes révélés (nouveau modèle éditorial)
    try {
        $pdo = db();
        $ktc_count = (int)$pdo->query(
            "SELECT COUNT(*) FROM ktc_episodes WHERE status IN ('revealed','archived')"
        )->fetchColumn();
    } catch (PDOException $e) {
        $ktc_count = 0;
    }

    // KTC : 3 derniers épisodes révélés
    try {
        $pdo = db();
        $stmt = $pdo->query("
            SELECT title, id, date_revelation
            FROM ktc_episodes
            WHERE status IN ('revealed','archived')
            ORDER BY id DESC
            LIMIT 3
        ");
        $ktc_last3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ktc_last3 = [];
    }
}

// ── Gradients galerie ────────────────────────────────────────
$gallery_gradients = [
    'linear-gradient(135deg, #12314e 0%, #2a9d5c 100%)',
    'linear-gradient(135deg, #C9962A 0%, #ea5649 100%)',
    'linear-gradient(135deg, #2a9d5c 0%, #163756 100%)',
    'linear-gradient(135deg, #ea5649 0%, #12314e 100%)',
    'linear-gradient(135deg, #163756 0%, #C9962A 100%)',
    'linear-gradient(135deg, #12314e 0%, #ea5649 100%)',
];

$gallery_icons = ['&#x1F3DE;', '&#x1F9ED;', '&#x1F33F;', '&#x26F0;', '&#x1F30A;', '&#x1F332;'];

// ── Clan labels ──────────────────────────────────────────────
$clan_labels = [
    'bocage'   => '&#x1F333; Bocage',
    'littoral' => '&#x2693; Littoral',
    'marais'   => '&#x1F33F; Marais',
];

$page_styles = '<style>

/* ============================================================
   HALL DE LA ZONE V11 — Page CSS
============================================================ */

/* HERO MUSEE — fond/padding depuis zone85.css (.hall-hero) */
.hall-hero::after {
  content: \'\';
  position: absolute;
  bottom: -80px;
  right: -80px;
  width: 400px;
  height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(201,150,42,.07) 0%, transparent 70%);
  pointer-events: none;
}
.hall-hero-inner { position: relative; z-index: 1; }
.hall-hero-icon {
  font-size: 3.6rem;
  margin-bottom: 16px;
  display: block;
  filter: drop-shadow(0 4px 20px rgba(201,150,42,.3));
  line-height: 1;
}
.hall-hero-overline {
  display: block;
  font-size: .68rem;
  font-weight: 900;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: rgba(201,150,42,.9);
  margin-bottom: 12px;
}
.hall-hero h1 {
  font-size: clamp(2.4rem, 6vw, 3.8rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -2px;
  line-height: 1.0;
  margin-bottom: 14px;
}
.hall-hero .hero-sub {
  font-size: 1rem;
  color: rgba(255,255,255,.58);
  max-width: 520px;
  line-height: 1.75;
}

/* ============================================================
   SHARED SECTION LAYOUT
============================================================ */
.hall-section { padding: 80px 0; }
.hall-section-light  { background: var(--beige-light, #f7f4ef); }
.hall-section-white  { background: var(--white, #fff); }
.hall-section-navy   { background: linear-gradient(160deg, #0c1e2e 0%, #12314e 100%); }
.hall-section-dark   { background: #0a1825; }

.hall-section-eyebrow {
  display: block;
  font-size: .67rem;
  font-weight: 900;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: var(--primary, #ea5649);
  margin-bottom: 8px;
}
.hall-section-eyebrow.gold { color: #C9962A; }
.hall-section-eyebrow.green { color: #2a9d5c; }

.hall-section-title {
  font-size: clamp(1.5rem, 2.8vw, 2rem);
  font-weight: 900;
  color: var(--text, #1a1a1a);
  letter-spacing: -.5px;
  line-height: 1.2;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.hall-section-title.light { color: #fff; }
.hall-section-title .sec-icon { font-size: 1.5rem; flex-shrink: 0; }
.hall-section-sub {
  font-size: .92rem;
  color: var(--text-mid, #555);
  line-height: 1.7;
  max-width: 560px;
  margin-bottom: 40px;
}
.hall-section-sub.light { color: rgba(255,255,255,.52); }

/* ============================================================
   FLASH BANNER
============================================================ */
.hall-flash-banner {
  background: linear-gradient(90deg, #1a0800 0%, #2e0f00 50%, #1a0800 100%);
  border-top: 2px solid rgba(234,86,73,.35);
  border-bottom: 2px solid rgba(234,86,73,.35);
  padding: 0;
}
.hall-flash-inner {
  display: flex;
  align-items: center;
  gap: 18px;
  padding: 12px 0;
  flex-wrap: wrap;
}
.hall-flash-pulse {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #ea5649;
  flex-shrink: 0;
  box-shadow: 0 0 0 0 rgba(234,86,73,.6);
  animation: hall-pulse 1.8s infinite;
}
@keyframes hall-pulse {
  0%   { box-shadow: 0 0 0 0 rgba(234,86,73,.6); }
  70%  { box-shadow: 0 0 0 8px rgba(234,86,73,0); }
  100% { box-shadow: 0 0 0 0 rgba(234,86,73,0); }
}
.hall-flash-label {
  font-size: .7rem;
  font-weight: 900;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: #ea5649;
  flex-shrink: 0;
}
.hall-flash-content {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  flex-wrap: wrap;
}
.hall-flash-emoji { font-size: 1.3rem; }
.hall-flash-title {
  font-size: .9rem;
  font-weight: 800;
  color: #fff;
}
.hall-flash-timer {
  font-size: .75rem;
  color: rgba(255,255,255,.5);
  font-weight: 600;
}
.hall-flash-xp {
  font-size: .68rem;
  font-weight: 900;
  background: rgba(201,150,42,.25);
  color: #C9962A;
  padding: 3px 10px;
  border-radius: 20px;
}
.hall-flash-link {
  font-size: .78rem;
  font-weight: 800;
  color: #ea5649;
  text-decoration: none;
  border: 1px solid rgba(234,86,73,.4);
  padding: 5px 14px;
  border-radius: 8px;
  transition: background .2s;
  flex-shrink: 0;
}
.hall-flash-link:hover { background: rgba(234,86,73,.12); }

/* ============================================================
   CONTRIBUTEURS
============================================================ */
.contributors-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
}
.contributor-card {
  background: var(--white, #fff);
  border-radius: 16px;
  padding: 26px 18px 22px;
  text-align: center;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  transition: transform .25s, box-shadow .25s;
  position: relative;
  overflow: hidden;
}
.contributor-card::after {
  content: \'\';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--primary, #ea5649);
  transform: scaleX(0);
  transition: transform .25s;
  transform-origin: left;
}
.contributor-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,.1); }
.contributor-card:hover::after { transform: scaleX(1); }
.contributor-rank {
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: .7rem;
  font-weight: 900;
  color: var(--text-muted, #888);
}
.contributor-avatar { font-size: 2.4rem; margin-bottom: 12px; display: block; }
.contributor-pseudo { font-size: .9rem; font-weight: 800; color: var(--text, #1a1a1a); margin-bottom: 6px; }
.contributor-clan {
  display: inline-block;
  font-size: .64rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
  background: rgba(18,49,78,.08);
  color: #12314e;
  padding: 3px 10px;
  border-radius: 20px;
  margin-bottom: 10px;
}
.contributor-pts { font-size: 1.1rem; font-weight: 900; color: var(--primary, #ea5649); }
.contributor-pts small { font-size: .65rem; font-weight: 600; color: var(--text-muted, #888); }

.contributors-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 52px 24px;
  color: var(--text-muted, #888);
  font-size: .9rem;
  line-height: 1.7;
  border: 2px dashed rgba(0,0,0,.08);
  border-radius: 14px;
}
.contributors-empty-icon { font-size: 2.8rem; margin-bottom: 14px; display: block; }

/* ============================================================
   GALERIE
============================================================ */
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}
.gallery-card {
  border-radius: 14px;
  overflow: hidden;
  position: relative;
  aspect-ratio: 4 / 3;
  box-shadow: 0 2px 12px rgba(0,0,0,.1);
  transition: transform .25s, box-shadow .25s;
}
.gallery-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,.18); }
.gallery-card-bg {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.8rem;
}
.gallery-card-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(0deg, rgba(0,0,0,.72) 0%, transparent 55%);
}
.gallery-card-body {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px 16px 14px;
}
.gallery-card-title {
  font-size: .88rem;
  font-weight: 800;
  color: #fff;
  line-height: 1.3;
  margin-bottom: 4px;
}
.gallery-card-author {
  font-size: .72rem;
  color: rgba(255,255,255,.55);
  font-weight: 600;
}
.gallery-card-featured {
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: .62rem;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
  background: rgba(201,150,42,.9);
  color: #fff;
  padding: 3px 10px;
  border-radius: 20px;
}

.gallery-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 52px 24px;
  color: rgba(255,255,255,.4);
  font-size: .9rem;
  line-height: 1.7;
  border: 2px dashed rgba(255,255,255,.1);
  border-radius: 14px;
}
.gallery-empty-icon { font-size: 2.8rem; margin-bottom: 14px; display: block; }

/* ============================================================
   ARCHIVES TIMELINE
============================================================ */
.archives-timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
  border-left: 2px solid rgba(255,255,255,.1);
  margin-left: 12px;
  padding-left: 24px;
}
.archive-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid rgba(255,255,255,.06);
  position: relative;
}
.archive-row::before {
  content: \'\';
  position: absolute;
  left: -30px;
  top: 22px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #C9962A;
  border: 2px solid #0c1e2e;
  box-shadow: 0 0 0 3px rgba(201,150,42,.3);
}
.archive-row:last-child { border-bottom: none; }
.archive-saison {
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.5);
  width: 90px;
  flex-shrink: 0;
  padding-top: 2px;
}
.archive-winner {
  font-size: .9rem;
  font-weight: 800;
  color: #fff;
}
.archive-winner span { color: #C9962A; }
.archive-pts {
  font-size: .75rem;
  font-weight: 700;
  color: rgba(255,255,255,.4);
  margin-top: 2px;
}

.archives-empty {
  text-align: center;
  padding: 40px 24px;
  color: rgba(255,255,255,.35);
  font-size: .88rem;
  line-height: 1.7;
  border: 2px dashed rgba(255,255,255,.08);
  border-radius: 14px;
}

/* ============================================================
   KTC BLOCK
============================================================ */
.ktc-block {
  background: rgba(201,150,42,.06);
  border: 1px solid rgba(201,150,42,.2);
  border-radius: 16px;
  padding: 32px 32px 28px;
  max-width: 680px;
}
.ktc-count-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.ktc-count-number {
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  font-weight: 900;
  color: #C9962A;
  line-height: 1;
}
.ktc-count-label {
  font-size: 1rem;
  font-weight: 700;
  color: rgba(255,255,255,.7);
  line-height: 1.3;
}
.ktc-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ktc-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 8px;
}
.ktc-item-emoji { font-size: 1.1rem; flex-shrink: 0; }
.ktc-item-title {
  font-size: .85rem;
  font-weight: 600;
  color: rgba(255,255,255,.75);
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  flex: 1;
}
.ktc-item-badge {
  font-size: .62rem;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
  background: rgba(42,157,92,.2);
  color: #2a9d5c;
  padding: 2px 8px;
  border-radius: 20px;
  flex-shrink: 0;
}

.ktc-empty {
  font-size: .88rem;
  color: rgba(255,255,255,.35);
  line-height: 1.7;
  padding: 20px 0 0;
}

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1024px) {
  .contributors-grid { grid-template-columns: repeat(3, 1fr); }
  .gallery-grid      { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .hall-section      { padding: 60px 0; }
  .contributors-grid { grid-template-columns: repeat(2, 1fr); }
  .gallery-grid      { grid-template-columns: repeat(2, 1fr); }
  .hall-flash-inner  { gap: 10px; }
  .ktc-block         { padding: 24px 20px; }
}
@media (max-width: 480px) {
  .contributors-grid { grid-template-columns: 1fr 1fr; }
  .gallery-grid      { grid-template-columns: 1fr 1fr; }
  .hall-hero         { padding: 80px 0 52px; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO MUSEE ===================== -->
<section class="hall-hero">
  <div class="container hall-hero-inner">
    <span class="hall-hero-icon">&#x1F3DB;</span>
    <span class="hall-hero-overline">LA MEMOIRE DE LA ZONE</span>
    <h1>HALL DE LA ZONE</h1>
    <p class="hero-sub">La memoire de la communaute vendeenne. Contributeurs, galerie des moments, archives de saisons et enigmes resolues.</p>
  </div>
</section>

<?php if ($flash_active): ?>
<!-- ===================== FLASH EN COURS ===================== -->
<?php
  $fl_emoji  = e($flash_active['cover_emoji'] ?? '&#x26A1;');
  $fl_title  = e($flash_active['title'] ?? '');
  $fl_remain = '';
  if (!empty($flash_active['flash_end_at'])) {
      $diff = strtotime($flash_active['flash_end_at']) - time();
      if ($diff > 0) {
          $h = floor($diff / 3600);
          $m = floor(($diff % 3600) / 60);
          $fl_remain = $h > 0 ? "Encore {$h}h{$m}min" : "Encore {$m}min";
      }
  }
?>
<div class="hall-flash-banner">
  <div class="container hall-flash-inner">
    <span class="hall-flash-pulse"></span>
    <span class="hall-flash-label">&#x26A1; Flash en cours</span>
    <div class="hall-flash-content">
      <span class="hall-flash-emoji"><?= $fl_emoji ?></span>
      <span class="hall-flash-title"><?= $fl_title ?></span>
      <?php if ($fl_remain): ?>
        <span class="hall-flash-timer"><?= $fl_remain ?></span>
      <?php endif; ?>
      <?php if (!empty($flash_active['xp_multiplier']) && (float)$flash_active['xp_multiplier'] > 1): ?>
        <span class="hall-flash-xp">&#xD7;<?= number_format((float)$flash_active['xp_multiplier'], 1) ?> XP</span>
      <?php endif; ?>
    </div>
    <a href="missions.php?id=<?= (int)$flash_active['id'] ?>" class="hall-flash-link">Participer &#x2192;</a>
  </div>
</div>
<?php endif; ?>

<!-- ===================== ILS ONT MARQUE LA ZONE ===================== -->
<section class="hall-section hall-section-light">
  <div class="container">
    <span class="hall-section-eyebrow">&#x1F31F; Saison en cours</span>
    <h2 class="hall-section-title">
      <span class="sec-icon">&#x1F3C6;</span> Ils ont marque la Zone
    </h2>
    <p class="hall-section-sub">Les membres les plus actifs de la communaute cette saison.</p>

    <div class="contributors-grid">
      <?php if (!empty($hall_contributors)): ?>
        <?php
        $rank_icons = ['&#x1F947;', '&#x1F948;', '&#x1F949;'];
        foreach ($hall_contributors as $i => $c):
          $pseudo    = e($c['pseudo'] ?? 'Aventurier');
          $pts       = number_format((int)($c['season_pts'] ?? 0));
          $clan_slug = $c['clan_slug'] ?? '';
          $clan_html = $clan_labels[$clan_slug] ?? e($c['clan_name'] ?? '');
          $avatar    = $c['avatar'] ?? '&#x1F9ED;';
          $rank_icon = $rank_icons[$i] ?? '#' . ($i + 1);
        ?>
          <div class="contributor-card">
            <span class="contributor-rank"><?= $rank_icon ?></span>
            <span class="contributor-avatar"><?= $avatar ?></span>
            <div class="contributor-pseudo"><?= $pseudo ?></div>
            <?php if ($clan_html): ?>
              <span class="contributor-clan"><?= $clan_html ?></span>
            <?php endif; ?>
            <div class="contributor-pts"><?= $pts ?> <small>pts saison</small></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="contributors-empty">
          <span class="contributors-empty-icon">&#x1F331;</span>
          <strong style="display:block;font-size:.95rem;margin-bottom:6px;color:var(--navy-dark)">Les premiers explorateurs arrivent bientôt.</strong>
          Chaque participation enrichit ce Hall. Rejoignez une mission pour apparaître ici.
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== GALERIE DES MOMENTS ===================== -->
<section class="hall-section hall-section-navy">
  <div class="container">
    <span class="hall-section-eyebrow gold">&#x1F5BC; Galerie</span>
    <h2 class="hall-section-title light">
      <span class="sec-icon">&#x1F4F8;</span> Galerie des moments
    </h2>
    <p class="hall-section-sub light">Les contributions et explorations mémorables de la communauté.</p>

    <div class="gallery-grid">
      <?php if (!empty($hall_photos)): ?>
        <?php foreach ($hall_photos as $idx => $photo):
          $title     = e($photo['title']  ?? 'Moment de Zone');
          $author    = e($photo['author'] ?? '');
          $featured  = !empty($photo['is_featured']);
          $gradient  = $photo['gradient'] ?? $gallery_gradients[$idx % count($gallery_gradients)];
          $icon      = $gallery_icons[$idx % count($gallery_icons)];
        ?>
          <div class="gallery-card">
            <div class="gallery-card-bg" style="background: <?= $gradient ?>">
              <span><?= $icon ?></span>
            </div>
            <div class="gallery-card-overlay"></div>
            <?php if ($featured): ?>
              <span class="gallery-card-featured">&#x2665; Coup de coeur</span>
            <?php endif; ?>
            <div class="gallery-card-body">
              <div class="gallery-card-title"><?= $title ?></div>
              <?php if ($author): ?>
                <div class="gallery-card-author">par <?= $author ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="gallery-empty">
          <span class="gallery-empty-icon">&#x1F5BC;</span>
          La galerie se remplit avec vos participations.
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== ARCHIVES SAISONS ===================== -->
<section class="hall-section hall-section-dark">
  <div class="container">
    <span class="hall-section-eyebrow gold">&#x1F4DC; Memoire</span>
    <h2 class="hall-section-title light">
      <span class="sec-icon">&#x1F3FA;</span> Archives des saisons
    </h2>
    <p class="hall-section-sub light">Les clans vainqueurs des saisons passees.</p>

    <?php if (!empty($hall_trophies)): ?>
      <div class="archives-timeline">
        <?php foreach ($hall_trophies as $trophy): ?>
          <div class="archive-row">
            <span class="archive-saison"><?= e($trophy['season_name'] ?? $trophy['title'] ?? 'Saison') ?></span>
            <div>
              <div class="archive-winner">
                Clan <span><?= e($trophy['winner_clan'] ?? $trophy['clan_name'] ?? 'Inconnu') ?></span>
                &#x1F3C6;
              </div>
              <?php if (!empty($trophy['winner_score'])): ?>
                <div class="archive-pts"><?= number_format((int)$trophy['winner_score']) ?> pts</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="archives-empty">
        Les archives apparaitront a la fin de la premiere saison.
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===================== KTC RESOLUS ===================== -->
<section class="hall-section hall-section-navy">
  <div class="container">
    <span class="hall-section-eyebrow" style="color:rgba(201,150,42,.9)">&#x1F950; Enigmes</span>
    <h2 class="hall-section-title light">
      <span class="sec-icon">&#x1F9E9;</span> Keto Kole Tche resolus
    </h2>
    <p class="hall-section-sub light">Les enigmes vendeennes que la communaute a su dechiffrer.</p>

    <div class="ktc-block">
      <div class="ktc-count-row">
        <span class="ktc-count-number"><?= number_format($ktc_count) ?></span>
        <span class="ktc-count-label">mystere<?= $ktc_count > 1 ? 's' : '' ?><br>resolu<?= $ktc_count > 1 ? 's' : '' ?></span>
      </div>

      <?php if (!empty($ktc_last3)): ?>
        <div class="ktc-list">
          <?php foreach ($ktc_last3 as $ktc): ?>
            <div class="ktc-item">
              <span class="ktc-item-emoji">&#x1F950;</span>
              <span class="ktc-item-title"><?= e(mb_strimwidth($ktc['title'] ?? '', 0, 60, '...')) ?></span>
              <span class="ktc-item-badge">&#x2713; Resolu</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif ($ktc_count === 0): ?>
        <p class="ktc-empty">Aucun mystere encore revele &mdash; le premier sera legendaire.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
