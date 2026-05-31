<?php
$page_title       = 'Météo Zone85';
$page_description = 'La météo vendéenne selon Zone85. Alertes, événements météo, missions liées au temps.';
$page_canonical   = 'https://www.zone85.fr/meteo.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil',      'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Météo Zone85', 'item' => 'https://www.zone85.fr/meteo.php'],
    ],
];
$current_page = 'meteo';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';

// ── Données météo ─────────────────────────────────────────────
$weather_current  = null;
$weather_alerts   = [];
$weather_archive  = [];
$weather_missions = [];

try {
    $pdo = db();
    if ($pdo) {

        // 1. Post météo actuel
        $st = $pdo->prepare(
            'SELECT * FROM weather_posts
             WHERE (expires_at IS NULL OR expires_at > NOW())
             ORDER BY published_at DESC
             LIMIT 1'
        );
        $st->execute();
        $weather_current = $st->fetch() ?: null;

        // 2. Alertes actives
        $st2 = $pdo->prepare(
            'SELECT * FROM weather_posts
             WHERE is_alert = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY published_at DESC'
        );
        $st2->execute();
        $weather_alerts = $st2->fetchAll();

        // 3. Archives récentes (8 derniers)
        $st3 = $pdo->prepare(
            'SELECT * FROM weather_posts
             ORDER BY published_at DESC
             LIMIT 8'
        );
        $st3->execute();
        $weather_archive = $st3->fetchAll();

        // 4. Missions météo actives
        $st4 = $pdo->prepare(
            'SELECT id, title, cover_emoji, xp_participation
             FROM missions
             WHERE mission_type = \'weather_mission\'
               AND status = \'active\'
             LIMIT 3'
        );
        $st4->execute();
        $weather_missions = $st4->fetchAll();
    }
} catch (Exception $e) {
    // Dégradé silencieux
}

// Active season pour footer
try {
    $active_season = (db_enabled() && function_exists('fetch_active_season')) ? fetch_active_season() : null;
} catch (Exception $e) {
    $active_season = null;
}

// ── Styles ────────────────────────────────────────────────────
$page_styles = '<style>

/* HERO */
.meteo-hero {
  background: linear-gradient(160deg, #0a2a4e 0%, #1565a0 50%, #1e88d4 100%);
  padding: 110px 0 80px;
  position: relative;
  overflow: hidden;
}
.meteo-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'80\' height=\'80\' viewBox=\'0 0 80 80\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cellipse cx=\'20\' cy=\'20\' rx=\'18\' ry=\'10\' fill=\'%23ffffff\' fill-opacity=\'0.025\'/%3E%3Cellipse cx=\'60\' cy=\'55\' rx=\'22\' ry=\'12\' fill=\'%23ffffff\' fill-opacity=\'0.02\'/%3E%3C/svg%3E");
  pointer-events: none;
}
.meteo-hero::after {
  content: "";
  position: absolute;
  bottom: -60px;
  right: -60px;
  width: 350px;
  height: 350px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 70%);
  pointer-events: none;
}
.meteo-hero-inner   { position: relative; z-index: 1; }
.meteo-hero-icon    { font-size: 3.5rem; margin-bottom: 16px; display: block; filter: drop-shadow(0 4px 20px rgba(255,255,255,.25)); }
.meteo-hero h1      { font-size: clamp(2.2rem, 5.5vw, 3.2rem); font-weight: 900; color: #fff; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 14px; }
.meteo-hero .hero-sub { font-size: 1.05rem; color: rgba(255,255,255,.7); max-width: 520px; line-height: 1.75; }

/* SECTIONS */
.meteo-section       { padding: 72px 0; }
.meteo-section-sky   { background: linear-gradient(160deg, #e8f4fd 0%, #d0e8f8 100%); }
.meteo-section-white { background: #fff; }
.meteo-section-light { background: #f4f8fc; }
.meteo-section-navy  { background: linear-gradient(160deg, #0a2a4e 0%, #1565a0 100%); }

.meteo-section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: clamp(1.4rem, 2.6vw, 1.9rem);
  font-weight: 900;
  color: #0a2a4e;
  letter-spacing: -.5px;
  margin-bottom: 8px;
}
.meteo-section-title.light { color: #fff; }
.meteo-section-title-icon  { font-size: 1.5rem; flex-shrink: 0; }
.meteo-section-sub         { font-size: .92rem; color: #4a6a8a; line-height: 1.7; margin-bottom: 40px; max-width: 520px; }
.meteo-section-sub.light   { color: rgba(255,255,255,.6); }

/* ALERTES */
.meteo-alert-bar { background: #c0392b; padding: 0; }
.meteo-alert-bar-inner   { display: flex; align-items: stretch; gap: 0; }
.meteo-alert-icon-wrap   { background: #a93226; padding: 14px 20px; display: flex; align-items: center; font-size: 1.3rem; flex-shrink: 0; }
.meteo-alert-content     { padding: 12px 18px; flex: 1; }
.meteo-alert-badge       { display: inline-block; background: rgba(255,255,255,.2); color: #fff; font-size: .65rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; padding: 2px 9px; border-radius: 4px; margin-bottom: 4px; }
.meteo-alert-title       { font-size: .92rem; font-weight: 800; color: #fff; margin: 0; }
.meteo-alert-desc        { font-size: .8rem; color: rgba(255,255,255,.8); margin: 2px 0 0; }

/* CURRENT WEATHER CARD */
.meteo-current-card { background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(21,101,160,.15); overflow: hidden; }
.meteo-card-top {
  background: linear-gradient(135deg, #1565a0 0%, #1e88d4 100%);
  padding: 40px 44px;
  display: flex;
  align-items: center;
  gap: 28px;
  flex-wrap: wrap;
}
.meteo-main-emoji     { font-size: 5rem; filter: drop-shadow(0 4px 16px rgba(0,0,0,.2)); flex-shrink: 0; }
.meteo-main-info      { flex: 1; min-width: 200px; }
.meteo-main-condition { font-size: 1.8rem; font-weight: 900; color: #fff; letter-spacing: -.5px; line-height: 1.2; margin-bottom: 6px; }
.meteo-main-location  { font-size: .9rem; color: rgba(255,255,255,.7); font-weight: 600; margin-bottom: 10px; }
.meteo-main-date      { font-size: .78rem; color: rgba(255,255,255,.55); font-weight: 600; }
.meteo-card-body      { padding: 28px 44px; }
.meteo-body-text      { font-size: .95rem; color: #2a4a6a; line-height: 1.75; margin-bottom: 20px; }
.meteo-card-tags      { display: flex; gap: 8px; flex-wrap: wrap; }
.meteo-tag            { display: inline-flex; align-items: center; gap: 5px; background: #e8f4fd; color: #1565a0; font-size: .75rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; }
.meteo-card-author    { font-size: .78rem; color: #6a8aaa; font-weight: 600; padding-top: 16px; border-top: 1px solid #e8f0f8; margin-top: 16px; }

/* INFO CARD (pas de données) */
.meteo-info-card  { background: linear-gradient(135deg, #e8f4fd 0%, #d0e8f8 100%); border: 1.5px solid rgba(21,101,160,.15); border-radius: 16px; padding: 40px 36px; text-align: center; }
.meteo-info-icon  { font-size: 3.5rem; margin-bottom: 16px; }
.meteo-info-title { font-size: 1.3rem; font-weight: 900; color: #0a2a4e; margin-bottom: 10px; }
.meteo-info-text  { font-size: .92rem; color: #4a6a8a; line-height: 1.75; max-width: 480px; margin: 0 auto; }

/* MISSIONS MÉTÉO */
.meteo-missions-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.meteo-mission-card  { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); border-radius: 12px; padding: 20px; text-decoration: none; transition: background .2s, transform .2s; }
.meteo-mission-card:hover { background: rgba(255,255,255,.15); transform: translateY(-3px); }
.meteo-mission-emoji { font-size: 2rem; margin-bottom: 10px; display: block; }
.meteo-mission-title { font-size: .9rem; font-weight: 800; color: #fff; margin-bottom: 8px; line-height: 1.3; }
.meteo-mission-xp    { font-size: .75rem; font-weight: 700; color: rgba(255,255,255,.6); }

/* ARCHIVES */
.meteo-archives-list  { display: flex; flex-direction: column; gap: 0; border: 1.5px solid #d8e8f4; border-radius: 12px; overflow: hidden; }
.meteo-archive-item   { display: flex; align-items: center; gap: 14px; padding: 14px 20px; border-bottom: 1px solid #e8f0f8; transition: background .15s; }
.meteo-archive-item:last-child { border-bottom: none; }
.meteo-archive-item:hover { background: #f4f8fc; }
.meteo-archive-emoji  { font-size: 1.4rem; flex-shrink: 0; width: 36px; text-align: center; }
.meteo-archive-info   { flex: 1; min-width: 0; }
.meteo-archive-title  { font-size: .88rem; font-weight: 800; color: #0a2a4e; line-height: 1.3; }
.meteo-archive-date   { font-size: .72rem; color: #6a8aaa; font-weight: 600; margin-top: 2px; }
.meteo-archive-alert  { display: inline-block; background: rgba(192,57,43,.1); color: #c0392b; font-size: .62rem; font-weight: 800; padding: 2px 7px; border-radius: 4px; letter-spacing: .07em; text-transform: uppercase; }

/* CONCEPT */
.meteo-concept-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.meteo-concept-card { background: #fff; border-radius: 14px; padding: 28px 24px; text-align: center; box-shadow: 0 4px 18px rgba(21,101,160,.08); border: 1px solid rgba(21,101,160,.1); }
.meteo-concept-icon  { font-size: 2.4rem; margin-bottom: 14px; display: block; }
.meteo-concept-title { font-size: .95rem; font-weight: 800; color: #0a2a4e; margin-bottom: 8px; }
.meteo-concept-text  { font-size: .82rem; color: #4a6a8a; line-height: 1.65; }

/* RESPONSIVE */
@media (max-width: 1024px) {
  .meteo-missions-grid { grid-template-columns: repeat(2, 1fr); }
  .meteo-concept-grid  { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .meteo-section       { padding: 52px 0; }
  .meteo-missions-grid { grid-template-columns: 1fr; }
  .meteo-concept-grid  { grid-template-columns: 1fr; }
  .meteo-card-top      { padding: 28px 24px; }
  .meteo-card-body     { padding: 22px 24px; }
  .meteo-main-emoji    { font-size: 3.5rem; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ======================== HERO ======================== -->
<section class="meteo-hero">
  <div class="container meteo-hero-inner">
    <span class="meteo-hero-icon">🌤️</span>
    <p class="overline-label" style="color:rgba(255,255,255,.6)">La Zone85 &amp; la nature</p>
    <h1>Météo Zone85</h1>
    <p class="hero-sub">La Vendée, ses humeurs, son terrain de jeu — bulletins communautaires, alertes terrain et missions gameplay météo.</p>
  </div>
</section>

<?php if (!empty($weather_alerts)): ?>
<!-- ======================== ALERTES ======================== -->
<?php foreach ($weather_alerts as $alert): ?>
<div class="meteo-alert-bar">
  <div class="container meteo-alert-bar-inner">
    <div class="meteo-alert-icon-wrap">⚠️</div>
    <div class="meteo-alert-content">
      <span class="meteo-alert-badge">⚠️ ALERTE</span>
      <p class="meteo-alert-title"><?= e($alert['title'] ?? 'Alerte météo active') ?></p>
      <?php if (!empty($alert['description'])): ?>
      <p class="meteo-alert-desc"><?= e(mb_substr($alert['description'], 0, 120)) ?>…</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ======================== AUJOURD'HUI ======================== -->
<section class="meteo-section meteo-section-sky">
  <div class="container">
    <h2 class="meteo-section-title">
      <span class="meteo-section-title-icon">🌡️</span> Aujourd'hui
    </h2>
    <p class="meteo-section-sub">Le dernier bulletin météo publié par la communauté.</p>

    <?php if ($weather_current): ?>
    <div class="meteo-current-card">
      <div class="meteo-card-top">
        <span class="meteo-main-emoji"><?= e($weather_current['weather_emoji'] ?? $weather_current['icon_emoji'] ?? '🌤️') ?></span>
        <div class="meteo-main-info">
          <div class="meteo-main-condition"><?= e($weather_current['title'] ?? 'Bulletin météo') ?></div>
          <div class="meteo-main-location">📍 <?= e($weather_current['location'] ?? 'Vendée') ?></div>
          <?php if (!empty($weather_current['published_at'])): ?>
          <div class="meteo-main-date">Publié le <?= date('d/m/Y à H\hi', strtotime($weather_current['published_at'])) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="meteo-card-body">
        <?php if (!empty($weather_current['description']) || !empty($weather_current['content'])): ?>
        <p class="meteo-body-text"><?= nl2br(e($weather_current['description'] ?? $weather_current['content'])) ?></p>
        <?php endif; ?>
        <?php
          $tags = [];
          if (!empty($weather_current['temperature']))       $tags[] = '🌡️ ' . e($weather_current['temperature']) . '°C';
          if (!empty($weather_current['wind']))              $tags[] = '💨 ' . e($weather_current['wind']);
          if (!empty($weather_current['rain']))              $tags[] = '🌧️ ' . e($weather_current['rain']);
          if (!empty($weather_current['weather_condition'])) $tags[] = e($weather_current['weather_condition']);
        ?>
        <?php if (!empty($tags)): ?>
        <div class="meteo-card-tags">
          <?php foreach ($tags as $tag): ?>
          <span class="meteo-tag"><?= $tag ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($weather_current['author']) || !empty($weather_current['pseudo'])): ?>
        <div class="meteo-card-author">
          Publié par <strong><?= e($weather_current['author'] ?? $weather_current['pseudo'] ?? '') ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <div class="meteo-info-card">
      <div class="meteo-info-icon">🌥️</div>
      <h3 class="meteo-info-title">Aucune publication météo aujourd'hui</h3>
      <p class="meteo-info-text">
        Aucune publication météo aujourd'hui — la Vendée se réveille doucement.
        La météo Zone85 est un bulletin communautaire ; revenez bientôt pour découvrir les conditions de terrain et les événements gameplay associés.
      </p>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($weather_missions)): ?>
<!-- ======================== MISSIONS MÉTÉO ======================== -->
<section class="meteo-section meteo-section-navy">
  <div class="container">
    <h2 class="meteo-section-title light">
      <span class="meteo-section-title-icon">⛅</span> Missions météo actives
    </h2>
    <p class="meteo-section-sub light">Des missions spéciales déclenchées par les conditions météo actuelles en Vendée.</p>
    <div class="meteo-missions-grid">
      <?php foreach ($weather_missions as $mission): ?>
      <a href="missions.php?id=<?= (int)$mission['id'] ?>" class="meteo-mission-card">
        <span class="meteo-mission-emoji"><?= e($mission['cover_emoji'] ?? '⛅') ?></span>
        <div class="meteo-mission-title"><?= e($mission['title']) ?></div>
        <?php if (!empty($mission['xp_participation'])): ?>
        <div class="meteo-mission-xp">+<?= (int)$mission['xp_participation'] ?> XP de participation</div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ======================== CONCEPT ======================== -->
<section class="meteo-section meteo-section-light">
  <div class="container">
    <h2 class="meteo-section-title">
      <span class="meteo-section-title-icon">💡</span> La météo dans le gameplay
    </h2>
    <p class="meteo-section-sub">Ici, la météo ne sert pas qu'à décider si tu prends un parapluie.</p>
    <div class="meteo-concept-grid">
      <div class="meteo-concept-card">
        <span class="meteo-concept-icon">📡</span>
        <h3 class="meteo-concept-title">Bulletins communautaires</h3>
        <p class="meteo-concept-text">L'équipe Zone85 publie des conditions météo adaptées au terrain vendéen : bocage, littoral, marais. Des infos utiles pour tes sorties.</p>
      </div>
      <div class="meteo-concept-card">
        <span class="meteo-concept-icon">⚡</span>
        <h3 class="meteo-concept-title">Missions météo</h3>
        <p class="meteo-concept-text">Certaines conditions déclenchent des missions spéciales : "Photo sous la pluie", "Rando dans le brouillard", "Coucher de soleil littoral".</p>
      </div>
      <div class="meteo-concept-card">
        <span class="meteo-concept-icon">⚠️</span>
        <h3 class="meteo-concept-title">Alertes terrain</h3>
        <p class="meteo-concept-text">En cas de conditions dangereuses ou d'événements météo exceptionnels, des alertes sont publiées pour informer la communauté Zone85.</p>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($weather_archive)): ?>
<!-- ======================== ARCHIVES ======================== -->
<section class="meteo-section meteo-section-white">
  <div class="container">
    <h2 class="meteo-section-title">
      <span class="meteo-section-title-icon">📁</span> Archives météo
    </h2>
    <p class="meteo-section-sub">Les 8 derniers bulletins publiés.</p>
    <div class="meteo-archives-list">
      <?php foreach ($weather_archive as $post): ?>
      <div class="meteo-archive-item">
        <div class="meteo-archive-emoji"><?= e($post['weather_emoji'] ?? $post['icon_emoji'] ?? '🌤️') ?></div>
        <div class="meteo-archive-info">
          <div class="meteo-archive-title"><?= e($post['title'] ?? 'Bulletin météo') ?></div>
          <?php if (!empty($post['published_at'])): ?>
          <div class="meteo-archive-date"><?= date('d/m/Y', strtotime($post['published_at'])) ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($post['is_alert'])): ?>
        <span class="meteo-archive-alert">⚠️ Alerte</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
