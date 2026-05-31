<?php
// ============================================================
// ZONE 85 — clans.php v11 Premium
// Refonte complète : podium, histoires, comment aider, historique
// UTF-8 sans BOM
// ============================================================
$page_title       = 'La Bataille des Clans';
$page_description = 'Bocage, Littoral, Marais — trois clans vendéens s\'affrontent chaque saison dans la Bataille des Clans. Découvre leur identité et rejoins le tien.';
$page_canonical   = 'https://www.zone85.fr/clans.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Les Clans', 'item' => 'https://www.zone85.fr/clans.php'],
    ],
];
$current_page = 'clans';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';

if (db_enabled()) {
    $_clans_db = fetch_all_clans();
    if ($_clans_db !== null) $clans = $_clans_db;
    $_season_db = fetch_active_season();
    if ($_season_db !== null) $active_season = $_season_db;
    $_trophies_db = fetch_trophies();
}

// ── Tri podium : 2e, 1er, 3e (affichage) ────────────────────
$podium_clans = array_values($clans);
usort($podium_clans, fn($a, $b) => $a['podium_rank'] <=> $b['podium_rank']);
$by_rank = [];
foreach ($podium_clans as $c) {
    $by_rank[$c['podium_rank']] = $c;
}
$podium_display = [
    $by_rank[2] ?? null,
    $by_rank[1] ?? null,
    $by_rank[3] ?? null,
];

// ── Score max pour barres de progression ────────────────────
$max_score = 0;
foreach ($clans as $c) {
    if ((int)$c['season_score'] > $max_score) $max_score = (int)$c['season_score'];
}

// ── Couleurs par clan ────────────────────────────────────────
$clan_colors = [
    'bocage'   => ['bg' => '#0d2018', 'bg2' => '#1e3d2b', 'bar' => '#2a9d5c', 'medal' => '&#127947;'],
    'littoral' => ['bg' => '#0a1a2e', 'bg2' => '#163756', 'bar' => '#1a6fb8', 'medal' => '&#9875;'],
    'marais'   => ['bg' => '#2b1a0a', 'bg2' => '#4a2e15', 'bar' => '#8b6340', 'medal' => '&#127807;'],
];

// ── Mots-clés par clan ───────────────────────────────────────
$clan_keywords = [
    'bocage'   => ['Tenacité', 'Forêt', 'Discrétion'],
    'littoral' => ['Audace', 'Horizon', 'Sel'],
    'marais'   => ['Patience', 'Profondeur', 'Secrets'],
];

// ── Cri de guerre par clan ───────────────────────────────────
$clan_cries = [
    'bocage'   => '"Par les haies et les chemins creux, le Bocage avance !"',
    'littoral' => '"La mer nous appelle, les vagues nous portent !"',
    'marais'   => '"Dans le silence des eaux, la force attend."',
];

// ── Médailles ────────────────────────────────────────────────
$rank_medals = [1 => '&#127945;', 2 => '&#129356;', 3 => '&#129357;'];

// ── Saisons archivées ────────────────────────────────────────
$archived_seasons = array_filter($seasons, fn($s) => $s['status'] === 'archived');

$page_styles = '<style>
/* ── CLANS V11 — CSS complet ──────────────────────────────── */

/* HERO */
.clans-hero{
  background:linear-gradient(160deg,#06101a 0%,#0d1e2c 60%,#12314e 100%);
  padding:110px 0 70px;
  position:relative;
  overflow:hidden;
  text-align:center;
}
.clans-hero::before{
  content:"";
  position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width=\'80\' height=\'80\' viewBox=\'0 0 80 80\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.018\'%3E%3Cpath d=\'M0 0h80v80H0z\'/%3E%3C/g%3E%3C/svg%3E");
  pointer-events:none;
}
.clans-hero-inner{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:0 24px;}
.clans-hero .overline-label{
  font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:rgba(255,255,255,.5);margin-bottom:14px;
}
.clans-hero h1{
  font-size:clamp(2rem,5vw,3.2rem);font-weight:900;color:#fff;
  letter-spacing:-1.5px;line-height:1.05;margin-bottom:16px;
}
.clans-hero .hero-sub{
  font-size:clamp(.95rem,2vw,1.1rem);color:rgba(255,255,255,.6);
  max-width:560px;margin:0 auto 48px;line-height:1.6;
}

/* PODIUM SECTION */
.podium-section{
  background:#0d1e2c;
  padding:60px 0 80px;
}
.podium-grid{
  display:flex;
  align-items:flex-end;
  justify-content:center;
  gap:20px;
  max-width:960px;
  margin:0 auto;
  padding:0 20px;
}
.podium-card{
  border-radius:16px;
  overflow:hidden;
  flex:1;
  max-width:280px;
  box-shadow:0 20px 60px rgba(0,0,0,.5);
  transition:transform .25s,box-shadow .25s;
}
.podium-card:hover{transform:translateY(-6px);box-shadow:0 30px 80px rgba(0,0,0,.6);}
.podium-card-first{
  flex:1.35;
  max-width:360px;
  order:2;
}
.podium-card:nth-child(1){order:1;}
.podium-card:nth-child(3){order:3;}
.pc-header{padding:28px 22px 20px;text-align:center;position:relative;}
.pc-medal{font-size:2rem;margin-bottom:8px;display:block;line-height:1;}
.pc-masc{
  width:90px;height:90px;border-radius:12px;overflow:hidden;
  background:rgba(255,255,255,.08);margin:0 auto 12px;
  display:flex;align-items:center;justify-content:center;
}
.podium-card-first .pc-masc{width:120px;height:120px;border-radius:16px;}
.pc-masc img{width:100%;height:100%;object-fit:cover;}
.pc-clan-name{
  font-size:.95rem;font-weight:900;color:#fff;letter-spacing:.03em;
  margin-bottom:4px;
}
.podium-card-first .pc-clan-name{font-size:1.15rem;}
.pc-body{background:#fff;padding:18px 20px 22px;}
.pc-score-row{
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:12px;
}
.pc-score{font-size:1.2rem;font-weight:900;color:#0d1e2c;}
.podium-card-first .pc-score{font-size:1.5rem;}
.pc-members{font-size:.78rem;color:#6b7280;font-weight:600;}
.pc-bar-bg{background:#f0ece7;border-radius:6px;height:8px;overflow:hidden;margin-bottom:14px;}
.pc-bar-fill{height:100%;border-radius:5px;transition:width 1.4s cubic-bezier(.22,1,.36,1);}
.pc-btn{
  display:block;text-align:center;
  padding:10px 14px;border-radius:8px;
  font-size:.8rem;font-weight:800;letter-spacing:.04em;
  text-decoration:none;transition:opacity .2s;
  color:#fff;
}
.pc-btn:hover{opacity:.85;}
.bocage-btn{background:#2a9d5c;}
.littoral-btn{background:#1a6fb8;}
.marais-btn{background:#8b6340;}

@media(max-width:700px){
  .podium-grid{flex-direction:column;align-items:center;}
  .podium-card,.podium-card-first{max-width:100%;width:100%;order:unset !important;}
}

/* HISTOIRES DES CLANS */
.clans-stories{background:#f7f5f2;padding:80px 0;}
.stories-tabs{
  display:flex;gap:4px;justify-content:center;
  margin-bottom:40px;
  background:#fff;border-radius:40px;
  padding:6px;
  max-width:440px;
  margin-left:auto;margin-right:auto;
  box-shadow:0 2px 10px rgba(18,49,78,.08);
}
.story-tab-btn{
  flex:1;padding:10px 22px;border-radius:34px;
  border:none;background:transparent;cursor:pointer;
  font-size:.85rem;font-weight:700;color:#6b7280;
  font-family:inherit;transition:all .2s;white-space:nowrap;
}
.story-tab-btn.active{background:#12314e;color:#fff;}
.story-tab-btn:hover:not(.active){color:#12314e;}
.story-panels .story-panel{display:none;}
.story-panels .story-panel.active{display:block;}
.story-inner{
  display:grid;grid-template-columns:1fr 2fr;
  gap:48px;align-items:start;
  max-width:900px;margin:0 auto;padding:0 24px;
}
@media(max-width:760px){
  .story-inner{grid-template-columns:1fr;gap:28px;}
  .story-masc-col{text-align:center;}
}
.story-masc-img{
  width:100%;max-width:240px;border-radius:16px;
  display:block;
}
.story-hero-name{
  font-size:clamp(1.4rem,3vw,2rem);font-weight:900;
  color:#0d1e2c;margin-bottom:8px;
}
.story-cry{
  font-style:italic;font-size:.95rem;
  color:#5a7a6a;border-left:3px solid #2a9d5c;
  padding-left:14px;margin-bottom:20px;line-height:1.5;
}
.story-panel[data-clan="littoral"] .story-cry{border-color:#1a6fb8;color:#24587a;}
.story-panel[data-clan="marais"] .story-cry{border-color:#8b6340;color:#6b4c2a;}
.story-desc{
  font-size:.95rem;color:#374151;line-height:1.7;margin-bottom:24px;
}
.story-keywords{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px;}
.story-kw{
  padding:5px 14px;border-radius:20px;font-size:.78rem;
  font-weight:700;letter-spacing:.04em;
}
.bocage-kw{background:#e8f5ee;color:#1a5c38;}
.littoral-kw{background:#e8f0fb;color:#154f8b;}
.marais-kw{background:#f5ede4;color:#6b4c2a;}
.story-top5-title{
  font-size:.68rem;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:#9ca3af;
  margin-bottom:12px;border-bottom:1px solid #e5e7eb;padding-bottom:8px;
}
.story-top5-list{list-style:none;padding:0;margin:0;}
.story-top5-list li{
  display:flex;align-items:center;gap:10px;
  padding:8px 0;border-bottom:1px solid #f3f4f6;
  font-size:.88rem;
}
.story-top5-list li:last-child{border-bottom:none;}
.st-rank{
  width:22px;height:22px;border-radius:50%;
  background:#f3f4f6;display:flex;align-items:center;justify-content:center;
  font-size:.72rem;font-weight:800;color:#6b7280;flex-shrink:0;
}
.st-name{flex:1;font-weight:700;color:#1f2937;}
.st-xp{font-size:.8rem;font-weight:800;color:#9ca3af;}
.story-empty{font-size:.88rem;color:#9ca3af;font-style:italic;padding:12px 0;}

/* COMMENT AIDER SON CLAN */
.clan-help{background:#fff;padding:80px 0;}
.clan-help-grid{
  display:grid;grid-template-columns:repeat(3,1fr);
  gap:28px;max-width:960px;margin:0 auto;padding:0 24px;
}
@media(max-width:760px){.clan-help-grid{grid-template-columns:1fr;}}
.clan-help-card{
  border-radius:16px;border:1px solid #e5e7eb;
  padding:32px 28px;text-align:center;
  transition:box-shadow .2s,transform .2s;
}
.clan-help-card:hover{
  box-shadow:0 8px 32px rgba(18,49,78,.1);
  transform:translateY(-4px);
}
.chc-icon{font-size:2.4rem;display:block;margin-bottom:14px;}
.chc-title{font-size:1rem;font-weight:900;color:#0d1e2c;margin-bottom:8px;}
.chc-desc{font-size:.88rem;color:#6b7280;line-height:1.6;margin-bottom:14px;}
.chc-badge{
  display:inline-block;padding:5px 14px;border-radius:20px;
  font-size:.78rem;font-weight:800;background:#fef3c7;color:#92400e;
}

/* HISTORIQUE */
.seasons-history{background:#f7f5f2;padding:80px 0;}
.seasons-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
  gap:20px;max-width:960px;margin:0 auto;padding:0 24px;
}
.season-hist-card{
  border-radius:14px;overflow:hidden;background:#fff;
  border:1px solid rgba(18,49,78,.08);
  box-shadow:0 2px 10px rgba(18,49,78,.05);
}
.shc-header{padding:22px 20px 16px;}
.shc-header-bocage{background:linear-gradient(135deg,#0d2018,#1e3d2b);}
.shc-header-littoral{background:linear-gradient(135deg,#0a1a2e,#163756);}
.shc-header-marais{background:linear-gradient(135deg,#2b1a0a,#4a2e15);}
.shc-header-none{background:linear-gradient(135deg,#1a2436,#2a3650);}
.shc-season-label{
  font-size:.62rem;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:6px;
}
.shc-winner{font-size:1rem;font-weight:900;color:#fff;margin-bottom:2px;}
.shc-winner-badge{
  display:inline-block;padding:3px 10px;border-radius:10px;
  font-size:.72rem;font-weight:700;margin-top:6px;
}
.shc-badge-bocage{background:rgba(42,157,92,.25);color:#6ddba0;}
.shc-badge-littoral{background:rgba(26,111,184,.25);color:#7ab8f0;}
.shc-badge-marais{background:rgba(139,99,64,.25);color:#d4a87a;}
.shc-badge-none{background:rgba(255,255,255,.1);color:rgba(255,255,255,.5);}
.shc-body{padding:16px 20px;}
.shc-period{font-size:.78rem;color:#9ca3af;margin-bottom:6px;}
.shc-mission{font-size:.85rem;color:#374151;font-weight:600;}

/* SECTION HEADERS */
.section-header{text-align:center;margin-bottom:48px;padding:0 24px;}
.section-header h2{
  font-size:clamp(1.5rem,3.5vw,2.2rem);font-weight:900;
  color:#0d1e2c;margin-bottom:10px;
}
.section-header p,.section-header .section-sub{
  font-size:.95rem;color:#6b7280;max-width:520px;
  margin:0 auto;line-height:1.6;
}
.section-header .overline-label{
  font-size:.65rem;font-weight:700;letter-spacing:.14em;
  text-transform:uppercase;color:#9ca3af;margin-bottom:12px;
}

/* CTA */
.clans-cta{
  background:linear-gradient(135deg,#0d1e2c 0%,#12314e 100%);
  padding:72px 0;text-align:center;
}
.clans-cta h2{
  font-size:clamp(1.5rem,3vw,2.2rem);font-weight:900;color:#fff;
  margin-bottom:10px;
}
.clans-cta p{font-size:.95rem;color:rgba(255,255,255,.55);margin-bottom:32px;}
.clans-cta .cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}

/* Utilitaires */
.container{max-width:1160px;margin:0 auto;padding:0 24px;}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="clans-hero">
  <div class="clans-hero-inner">
    <p class="overline-label">
      <?php if ($active_season): ?>
        Saison en cours &middot; <?= e($active_season['title']) ?>
      <?php else: ?>
        Zone85 &mdash; Vendée
      <?php endif; ?>
    </p>
    <h1>La Bataille&nbsp;des&nbsp;Clans</h1>
    <p class="hero-sub">Trois clans. Une seule Vendée.<br>Qui mènera la danse cette saison&nbsp;?</p>
  </div>
</section>

<!-- ===================== PODIUM ===================== -->
<section class="podium-section">
  <div class="section-header" style="color:#fff;">
    <p class="overline-label" style="color:rgba(255,255,255,.45);">Classement actuel</p>
    <h2 style="color:#fff;">Le Podium</h2>
  </div>
  <div class="podium-grid">
    <?php foreach ($podium_display as $clan): if (!$clan) continue;
      $rank  = (int)$clan['podium_rank'];
      $slug  = $clan['slug'];
      $col   = $clan_colors[$slug] ?? ['bg' => '#0d1e2c', 'bg2' => '#12314e', 'bar' => '#888', 'medal' => ''];
      $medal = $rank_medals[$rank] ?? '';
      $is_first = $rank === 1;
      $bar_pct = ($max_score > 0) ? (int)round(((int)$clan['season_score'] / $max_score) * 100) : 0;
      $card_class = 'podium-card' . ($is_first ? ' podium-card-first' : '');
    ?>
    <div class="<?= e($card_class) ?>">
      <div class="pc-header" style="background:linear-gradient(135deg,<?= e($col['bg']) ?>,<?= e($col['bg2']) ?>);">
        <span class="pc-medal"><?= $medal ?></span>
        <div class="pc-masc">
          <img src="<?= img(e($clan['mascot'])) ?>" alt="Mascotte <?= e(ucfirst($slug)) ?>">
        </div>
        <div class="pc-clan-name"><?= e($clan['name']) ?></div>
      </div>
      <div class="pc-body">
        <div class="pc-score-row">
          <span class="pc-score"><?= format_score((int)$clan['season_score']) ?></span>
          <span class="pc-members"><?= (int)$clan['members_count'] ?> membres</span>
        </div>
        <div class="pc-bar-bg">
          <div class="pc-bar-fill" style="width:<?= $bar_pct ?>%;background:<?= e($col['bar']) ?>;"></div>
        </div>
        <a href="inscription.php?clan=<?= e($slug) ?>" class="pc-btn <?= e($slug) ?>-btn">
          Rejoindre ce clan
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===================== HISTOIRES DES CLANS ===================== -->
<section class="clans-stories">
  <div class="container">
    <div class="section-header">
      <p class="overline-label">Identité &amp; Légende</p>
      <h2>Histoires des Clans</h2>
      <p class="section-sub">Chaque clan a sa personnalité, ses héros et sa façon d'habiter la Vendée.</p>
    </div>

    <div class="stories-tabs" role="tablist">
      <?php $first_tab = true; foreach (['bocage', 'littoral', 'marais'] as $slug):
        $c = $clans[$slug] ?? null;
        if (!$c) continue;
        $active_cls = $first_tab ? ' active' : '';
        $first_tab = false;
      ?>
      <button
        class="story-tab-btn<?= $active_cls ?>"
        role="tab"
        onclick="switchClanTab('<?= e($slug) ?>')"
        id="tab-<?= e($slug) ?>"
        aria-controls="panel-<?= e($slug) ?>"
        aria-selected="<?= $active_cls ? 'true' : 'false' ?>"
      ><?= e($c['label']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="story-panels">
      <?php $first_panel = true; foreach (['bocage', 'littoral', 'marais'] as $slug):
        $c = $clans[$slug] ?? null;
        if (!$c) continue;
        $panel_active = $first_panel ? ' active' : '';
        $first_panel  = false;
        $cry   = $clan_cries[$slug]    ?? '';
        $kws   = $clan_keywords[$slug] ?? [];
        $kw_cls = $slug . '-kw';
      ?>
      <div
        class="story-panel<?= $panel_active ?>"
        id="panel-<?= e($slug) ?>"
        data-clan="<?= e($slug) ?>"
        role="tabpanel"
        aria-labelledby="tab-<?= e($slug) ?>"
      >
        <div class="story-inner">
          <!-- Mascotte -->
          <div class="story-masc-col">
            <img
              src="<?= img(e($c['mascot'])) ?>"
              alt="Mascotte <?= e(ucfirst($slug)) ?>"
              class="story-masc-img"
            >
          </div>
          <!-- Infos -->
          <div class="story-info-col">
            <h3 class="story-hero-name"><?= e($c['hero_name']) ?></h3>
            <p class="story-cry"><?= e($cry) ?></p>
            <p class="story-desc"><?= e($c['description']) ?></p>
            <div class="story-keywords">
              <?php foreach ($kws as $kw): ?>
              <span class="story-kw <?= e($kw_cls) ?>"><?= e($kw) ?></span>
              <?php endforeach; ?>
            </div>
            <!-- Top 5 membres -->
            <p class="story-top5-title">Top membres &middot; Saison en cours</p>
            <?php if (!empty($c['top_members'])): ?>
            <ol class="story-top5-list">
              <?php foreach (array_slice($c['top_members'], 0, 5) as $i => $member): ?>
              <li>
                <span class="st-rank"><?= $i + 1 ?></span>
                <span class="st-name"><?= e($member['pseudo']) ?></span>
                <span class="st-xp"><?= format_xp((int)$member['xp_season']) ?></span>
              </li>
              <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <p class="story-empty">Rejoins le clan pour figurer ici.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== COMMENT AIDER SON CLAN ===================== -->
<section class="clan-help">
  <div class="container">
    <div class="section-header">
      <p class="overline-label">Contribuer</p>
      <h2>Comment aider son clan&nbsp;?</h2>
      <p class="section-sub">Chaque action compte. Chaque point rapproche la victoire.</p>
    </div>
    <div class="clan-help-grid">
      <div class="clan-help-card">
        <span class="chc-icon">&#127919;</span>
        <div class="chc-title">Participer aux missions</div>
        <p class="chc-desc">Inscris-toi et prends part aux missions collectives ou solo. Ta simple participation rapporte des points à ton clan.</p>
        <span class="chc-badge">+pts participation</span>
      </div>
      <div class="clan-help-card">
        <span class="chc-icon">&#9989;</span>
        <div class="chc-title">Réussir les missions</div>
        <p class="chc-desc">Valide une réponse, dépose une photo, vote — chaque mission réussie multiplie ta contribution au score de saison.</p>
        <span class="chc-badge">+pts réussite</span>
      </div>
      <div class="clan-help-card">
        <span class="chc-icon">&#128273;</span>
        <div class="chc-title">Compléter les chasses</div>
        <p class="chc-desc">Les chasses cachées sont des bonus discrets disséminés dans Zone85. Les trouver, c'est offrir un boost direct à ton clan.</p>
        <span class="chc-badge">+pts complétion</span>
      </div>
    </div>
  </div>
</section>

<!-- ===================== HISTORIQUE DES SAISONS ===================== -->
<section class="seasons-history">
  <div class="container">
    <div class="section-header">
      <p class="overline-label">Palmarès</p>
      <h2>Historique des saisons</h2>
      <p class="section-sub">Les victoires passées appartiennent à l'histoire de Zone85.</p>
    </div>
    <div class="seasons-grid">
      <?php foreach ($archived_seasons as $s):
        $wc    = $s['winner_clan'] ?? '';
        $hdr   = $wc ? 'shc-header-' . $wc : 'shc-header-none';
        $badge = $wc ? 'shc-badge-' . $wc  : 'shc-badge-none';
        $winner_label = $s['winner_label'] ?? ($wc ? ucfirst($wc) : 'En cours');
      ?>
      <div class="season-hist-card">
        <div class="shc-header <?= e($hdr) ?>">
          <p class="shc-season-label"><?= e($s['title']) ?></p>
          <div class="shc-winner">&#127945; <?= e($winner_label) ?></div>
          <span class="shc-winner-badge <?= e($badge) ?>">Vainqueur</span>
        </div>
        <div class="shc-body">
          <p class="shc-period"><?= e($s['period'] ?? '') ?></p>
          <?php if (!empty($s['main_mission'])): ?>
          <p class="shc-mission"><?= e($s['main_mission']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Saison active -->
      <?php if ($active_season): ?>
      <div class="season-hist-card">
        <div class="shc-header shc-header-none">
          <p class="shc-season-label"><?= e($active_season['title']) ?></p>
          <div class="shc-winner" style="font-size:.9rem;">&#8987; En cours</div>
          <span class="shc-winner-badge shc-badge-none">Saison active</span>
        </div>
        <div class="shc-body">
          <p class="shc-period"><?= e($active_season['period'] ?? '') ?></p>
          <?php if (!empty($active_season['main_mission'])): ?>
          <p class="shc-mission"><?= e($active_season['main_mission']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="clans-cta">
  <div class="container">
    <h2>Choisissez votre camp</h2>
    <p>Bocage, Littoral ou Marais &mdash; quel territoire vous ressemble&nbsp;?</p>
    <div class="cta-btns">
      <a href="inscription.php" class="btn btn-primary btn-lg">Choisir mon clan</a>
      <a href="zonautes.php" class="btn btn-outline-white">Voir les Zonautes</a>
    </div>
  </div>
</section>

<?php
$page_scripts = '<script>
function switchClanTab(slug) {
  document.querySelectorAll(".story-tab-btn").forEach(function(btn) {
    var active = btn.id === "tab-" + slug;
    btn.classList.toggle("active", active);
    btn.setAttribute("aria-selected", active ? "true" : "false");
  });
  document.querySelectorAll(".story-panel").forEach(function(panel) {
    panel.classList.toggle("active", panel.id === "panel-" + slug);
  });
}
// Animer les barres de progression au chargement
document.addEventListener("DOMContentLoaded", function() {
  document.querySelectorAll(".pc-bar-fill").forEach(function(el) {
    el.style.width = (el.style.width || "0%");
  });
});
</script>';

render_hidden_collectibles('clans');
require_once 'includes/footer.php';
?>
