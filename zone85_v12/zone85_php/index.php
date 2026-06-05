<?php
$page_title       = 'La Vendee Joue';
$page_description = 'Choisis ton clan, pars en mission, laisse une trace. ZONE85 — Le terrain de jeu vendeen. Bocage, Littoral ou Marais.';
$page_canonical   = 'https://www.zone85.fr/index.php';
$page_robots      = 'index,follow';
$page_og_title    = 'ZONE85 — La Vendee Joue';
$page_og_description = 'Choisis ton clan vendeen, pars en mission, gagne des XP pour ton clan. Bocage, Littoral ou Marais — lequel te ressemble ?';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'ZONE85',
    'url'      => 'https://www.zone85.fr',
    'description' => 'Terrain de jeu communautaire vendeen. Rejoins un clan, gagne des XP, fais vivre la Vendee autrement.',
    'potentialAction' => [
        '@type'        => 'SearchAction',
        'target'       => 'https://www.zone85.fr/missions.php?q={search_term_string}',
        'query-input'  => 'required name=search_term_string',
    ],
];
$current_page = 'index';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';

// ── Repository layer (MySQL si disponible, sinon fallback data.php) ──
require_once 'includes/db.php';
require_once 'includes/repositories.php';

$flash_mission = null;

if (db_enabled()) {
    $_clans_db = fetch_all_clans();
    if ($_clans_db !== null) $clans = $_clans_db;

    $_season_db = fetch_active_season();
    if ($_season_db !== null) $active_season = $_season_db;

    $_missions_db = fetch_featured_missions(3);
    if ($_missions_db !== null) $missions = $_missions_db;

    // Grande mission active (type seasonal_collective)
    if (!isset($grande_mission)) {
        try {
            $pdo = db();
            if ($pdo) {
                $stmt = $pdo->query(
                    "SELECT * FROM missions
                     WHERE mission_type = 'seasonal_collective'
                       AND status = 'active'
                     LIMIT 1"
                );
                $grande_mission = $stmt->fetch() ?: null;
            }
        } catch (Exception $e) {
            $grande_mission = null;
        }
    }

    // Bonus du moment (mission flash active)
    try {
        $pdo = db();
        if ($pdo) {
            $stmt = $pdo->query(
                "SELECT * FROM missions
                 WHERE is_flash = 1
                   AND status = 'active'
                   AND (flash_end_at IS NULL OR flash_end_at > NOW())
                 LIMIT 1"
            );
            $flash_mission = $stmt->fetch() ?: null;
        }
    } catch (Exception $e) {
        $flash_mission = null;
    }
}

// Feed communautaire (index widget)
$index_feed = [];
if (db_enabled() && function_exists('fetch_community_feed')) {
    $index_feed = fetch_community_feed(1, 5);
}

// Stats globales animées
$index_stats = ['members' => 0, 'missions' => 0, 'xp_total' => 0];
if (db_enabled()) {
    try {
        $pdo = db();
        if ($pdo) {
            $index_stats['members']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
            $index_stats['missions'] = (int)$pdo->query("SELECT COUNT(*) FROM participations WHERE status IN ('validated','auto_validated')")->fetchColumn();
            $index_stats['xp_total'] = (int)$pdo->query("SELECT COALESCE(SUM(xp_total),0) FROM users WHERE status='active'")->fetchColumn();
        }
    } catch (Exception $e) {}
}

// Podium : trier par score desc
$clans_sorted = $clans;
uasort($clans_sorted, fn($a, $b) => $b['season_score'] <=> $a['season_score']);
$clans_podium = array_values($clans_sorted);
$medals       = ['gold' => '🥇', 'silver' => '🥈', 'bronze' => '🥉'];
$medal_list   = array_values($medals);

// Missions recentes (max 3)
$recent_missions = [];
if (!empty($missions)) {
    $recent_missions = array_slice(is_array($missions) ? array_values($missions) : [], 0, 3);
}

// Type icons missions
$mission_type_icons = [
    'quiz'               => '🎯',
    'photo'              => '📸',
    'rando'              => '🥾',
    'ktc'                => '🔍',
    'keto_kole_tche'     => '🔍',
    'vote'               => '🗳️',
    'seasonal_collective'=> '🛡️',
    'flash'              => '⚡',
    'default'            => '🎮',
];

$page_styles = '<style>
/* ======================================
   INDEX.PHP — Zone85 V11 Premium
   Mobile-first, magazine, no ext JS
====================================== */

/* ── HERO ── */
#hero{
  min-height:100svh;
  background:linear-gradient(160deg,#060e16 0%,#0c1e2e 40%,#12314e 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:100px 20px 0;position:relative;overflow:hidden
}
#hero::before{
  content:"";position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(234,86,73,.07) 0%,transparent 70%);
  pointer-events:none
}
#hero::after{
  content:"";position:absolute;
  bottom:0;left:0;right:0;height:120px;
  background:linear-gradient(to bottom,transparent,#0c1e2e);
  pointer-events:none
}
.hero-content{
  position:relative;z-index:2;
  display:flex;flex-direction:column;align-items:center;
  width:100%;max-width:820px;
  animation:heroIn .9s cubic-bezier(.22,.61,.36,1) both
}
@keyframes heroIn{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(234,86,73,.12);border:1px solid rgba(234,86,73,.28);
  color:#f5a99f;padding:5px 16px;border-radius:4px;
  font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  margin-bottom:26px
}
.hero-eyebrow-dot{
  width:6px;height:6px;background:#ea5649;border-radius:50%;
  animation:blink 1.6s ease-in-out infinite
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.hero-h1{
  font-size:clamp(3rem,7vw,5.6rem);font-weight:900;color:#fff;
  text-align:center;line-height:1.04;letter-spacing:-2px;
  margin-bottom:28px
}
.hero-h1 em{color:#ea5649;font-style:normal}
.hero-taglines{
  display:flex;flex-direction:column;align-items:center;gap:0;
  margin-bottom:42px
}
.hero-tagline-line{
  font-size:clamp(.95rem,2vw,1.12rem);color:rgba(255,255,255,.55);
  font-weight:500;letter-spacing:.01em;text-align:center;
  padding:4px 0;
  position:relative
}
.hero-tagline-line+.hero-tagline-line{
  border-top:1px solid rgba(255,255,255,.07);margin-top:2px;padding-top:10px
}
.hero-tagline-line strong{color:rgba(255,255,255,.85);font-weight:700}
.hero-ctas{
  display:flex;gap:14px;flex-wrap:wrap;justify-content:center;
  margin-bottom:52px
}
.hero-btn-primary{
  display:inline-flex;align-items:center;gap:8px;
  background:#ea5649;color:#fff;
  padding:14px 30px;border-radius:6px;
  font-size:1rem;font-weight:800;text-decoration:none;
  letter-spacing:.01em;transition:background .2s,transform .15s
}
.hero-btn-primary:hover{background:#d44035;transform:translateY(-1px)}
.hero-btn-secondary{
  display:inline-flex;align-items:center;gap:8px;
  background:transparent;color:rgba(255,255,255,.75);
  border:1.5px solid rgba(255,255,255,.2);
  padding:14px 28px;border-radius:6px;
  font-size:1rem;font-weight:600;text-decoration:none;
  transition:border-color .2s,color .2s
}
.hero-btn-secondary:hover{border-color:rgba(255,255,255,.5);color:#fff}
.hero-mascots{
  width:100%;max-width:840px;
  display:flex;align-items:flex-end;justify-content:center;gap:40px;
  position:relative;z-index:2;transform:translateY(8px)
}
.hero-masc-wrap{
  display:flex;flex-direction:column;align-items:center;
  cursor:pointer;text-decoration:none
}
.hero-masc-wrap img{
  height:190px;width:auto;object-fit:contain;
  filter:drop-shadow(0 12px 32px rgba(0,0,0,.55));
  transition:transform .3s
}
.hero-masc-wrap:hover img{transform:translateY(-6px)}
.hero-masc-wrap.center img{height:240px}
.hero-masc-label{
  margin-top:10px;
  font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
  color:rgba(255,255,255,.7);
  background:rgba(255,255,255,.08);
  padding:5px 16px;border-radius:20px;
  border:1px solid rgba(255,255,255,.13);white-space:nowrap
}

/* ── SECTION SAISON ── */
#s-saison{
  background:linear-gradient(160deg,#0c1e2e 0%,#12314e 100%);
  padding:80px 0
}
.saison-wrapper{
  display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start
}
.saison-eyebrow{
  font-size:.68rem;font-weight:800;text-transform:uppercase;
  letter-spacing:.13em;color:#ea5649;margin-bottom:12px
}
.saison-title{
  font-size:clamp(1.5rem,3vw,2rem);font-weight:900;color:#fff;
  line-height:1.15;margin-bottom:16px
}
.saison-grande-mission{
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.1);
  border-left:3px solid #ea5649;
  border-radius:0 8px 8px 0;
  padding:16px 20px;margin-bottom:20px
}
.saison-grande-mission-label{
  font-size:.62rem;font-weight:800;text-transform:uppercase;
  letter-spacing:.1em;color:rgba(255,255,255,.35);margin-bottom:5px
}
.saison-grande-mission-title{
  font-size:1rem;font-weight:800;color:#fff;line-height:1.35
}
.saison-grande-mission-sub{
  font-size:.78rem;color:rgba(255,255,255,.45);margin-top:4px;line-height:1.5
}
.saison-bataille{
  display:inline-flex;align-items:center;gap:10px;
  background:rgba(234,86,73,.1);border:1px solid rgba(234,86,73,.25);
  color:#f5a99f;padding:10px 18px;border-radius:6px;
  font-size:.84rem;font-weight:700;margin-top:4px
}
.saison-right{
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:12px;padding:24px
}
.saison-right-label{
  font-size:.64rem;font-weight:800;text-transform:uppercase;
  letter-spacing:.1em;color:rgba(255,255,255,.35);margin-bottom:16px
}

/* ── PODIUM ── */
#s-podium{background:#f4f0eb;padding:88px 0 72px}
.podium-section-head{text-align:center;margin-bottom:56px}
.podium-eyebrow{
  display:inline-block;
  font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
  color:#ea5649;margin-bottom:12px
}
.podium-title{
  font-size:clamp(1.6rem,3vw,2.4rem);font-weight:900;color:#0c1e2e;
  letter-spacing:-.5px;margin-bottom:8px
}
.podium-sub{font-size:.92rem;color:#6b7280;max-width:480px;margin:0 auto}
.podium-grid{
  display:grid;
  grid-template-columns:1fr 1.15fr 1fr;
  gap:20px;align-items:end
}
.podium-card{
  background:#fff;border-radius:14px;overflow:hidden;
  box-shadow:0 2px 12px rgba(0,0,0,.06);
  transition:transform .25s,box-shadow .25s;
  position:relative
}
.podium-card:hover{transform:translateY(-5px);box-shadow:0 12px 32px rgba(0,0,0,.11)}
.podium-card.first{
  box-shadow:0 6px 28px rgba(234,86,73,.18),0 2px 8px rgba(0,0,0,.06)
}
.podium-card.first .podium-header{padding-bottom:32px}
.podium-medal{
  position:absolute;top:14px;right:14px;
  font-size:1.4rem;line-height:1
}
.podium-header{
  padding:28px 20px 20px;
  display:flex;flex-direction:column;align-items:center
}
.podium-header-bocage{background:linear-gradient(160deg,#0d2018,#1e3d2b)}
.podium-header-littoral{background:linear-gradient(160deg,#0a1a2e,#12314e)}
.podium-header-marais{background:linear-gradient(160deg,#2b1a0a,#4a2e15)}
.podium-masc{
  height:110px;width:auto;object-fit:contain;
  filter:drop-shadow(0 6px 18px rgba(0,0,0,.45));
  margin-bottom:10px
}
.podium-card.first .podium-masc{height:140px}
.podium-clan-name{
  font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
  color:rgba(255,255,255,.75);
  background:rgba(255,255,255,.12);
  padding:4px 14px;border-radius:20px;border:1px solid rgba(255,255,255,.18)
}
.podium-body{padding:20px 18px 22px}
.podium-name{font-size:1.05rem;font-weight:800;color:#0c1e2e;margin-bottom:2px}
.podium-hero{font-size:.78rem;color:#ea5649;font-weight:700;margin-bottom:12px}
.podium-stats{
  display:flex;background:#f4f0eb;border-radius:8px;overflow:hidden;margin-bottom:14px
}
.podium-stat{flex:1;text-align:center;padding:9px 4px;position:relative}
.podium-stat:not(:last-child)::after{
  content:"";position:absolute;right:0;top:50%;transform:translateY(-50%);
  height:20px;width:1px;background:#e2ddd6
}
.podium-stat-val{font-size:.92rem;font-weight:800;color:#0c1e2e;line-height:1}
.podium-stat-lbl{font-size:.58rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-top:3px;font-weight:600}
.podium-btn{
  display:flex;align-items:center;justify-content:center;gap:5px;
  background:#0c1e2e;color:#fff;
  padding:10px;border-radius:7px;
  font-size:.82rem;font-weight:700;text-decoration:none;
  transition:background .2s
}
.podium-btn:hover{background:#ea5649}
.podium-cta{text-align:center;margin-top:36px}

/* ── BONUS DU MOMENT ── */
#s-bonus{padding:72px 0;background:#fff}
.bonus-inner{max-width:780px;margin:0 auto;text-align:center}
.bonus-section-label{
  font-size:.7rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
  color:#ea5649;margin-bottom:14px
}
.bonus-active{
  background:linear-gradient(135deg,#1a0a05,#3b1208);
  border:1.5px solid rgba(234,86,73,.4);
  border-radius:16px;padding:36px 32px;
  position:relative;overflow:hidden
}
.bonus-active::before{
  content:"";position:absolute;top:-40px;right:-40px;
  width:180px;height:180px;border-radius:50%;
  background:rgba(234,86,73,.08)
}
.bonus-live-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:#ea5649;color:#fff;
  font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
  padding:4px 12px;border-radius:4px;margin-bottom:18px
}
.bonus-live-dot{
  width:6px;height:6px;background:#fff;border-radius:50%;
  animation:blink .9s infinite
}
.bonus-multiplier{
  font-size:clamp(2.4rem,5vw,3.6rem);font-weight:950;
  color:#fff;letter-spacing:-2px;line-height:1;margin-bottom:6px
}
.bonus-multiplier span{color:#f5a285}
.bonus-active-title{
  font-size:1.05rem;font-weight:700;color:rgba(255,255,255,.75);
  margin-bottom:24px;line-height:1.45
}
.bonus-countdown{
  display:inline-flex;gap:10px;justify-content:center;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.1);
  border-radius:10px;padding:16px 20px
}
.bcnt-box{text-align:center;min-width:56px}
.bcnt-num{
  font-size:1.5rem;font-weight:900;color:#fff;line-height:1;
  font-variant-numeric:tabular-nums
}
.bcnt-lbl{
  font-size:.58rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:rgba(255,255,255,.38);margin-top:4px
}
.bonus-none{
  background:#f9f8f7;border:1.5px dashed #d1cdc7;
  border-radius:12px;padding:32px 24px;color:#9ca3af
}
.bonus-none-icon{font-size:2rem;margin-bottom:10px}
.bonus-none-text{font-size:.92rem;font-weight:600;color:#9ca3af;line-height:1.55}

/* ── MISSIONS RECENTES ── */
#s-missions{background:#f4f0eb;padding:80px 0}
.missions-section-head{text-align:center;margin-bottom:44px}
.missions-eyebrow{
  font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
  color:#ea5649;margin-bottom:10px
}
.missions-section-title{
  font-size:clamp(1.5rem,3vw,2.2rem);font-weight:900;color:#0c1e2e;letter-spacing:-.4px
}
.missions-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.mission-card{
  background:#fff;border-radius:12px;padding:22px 20px;
  box-shadow:0 1px 6px rgba(0,0,0,.05);
  transition:transform .2s,box-shadow .2s;
  display:flex;flex-direction:column;gap:10px
}
.mission-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.09)}
.mission-type-row{display:flex;align-items:center;gap:8px}
.mission-type-icon{font-size:1.3rem;line-height:1}
.mission-type-label{
  font-size:.64rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
  color:#9ca3af
}
.mission-title{font-size:.98rem;font-weight:800;color:#0c1e2e;line-height:1.35}
.mission-bottom{display:flex;align-items:center;justify-content:space-between;margin-top:auto}
.mission-xp{font-size:.78rem;font-weight:800;color:#ea5649}
.mission-status-badge{
  font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  padding:3px 9px;border-radius:4px
}
.mission-status-active{background:rgba(42,157,92,.1);color:#1a6b3a}
.mission-status-upcoming{background:rgba(234,86,73,.08);color:#b83028}
.missions-cta{text-align:center;margin-top:36px}

/* ── COMMENT CA MARCHE ── */
#s-concept{
  background:linear-gradient(160deg,#0c1e2e,#12314e);
  padding:80px 0
}
.concept-inner{max-width:760px;margin:0 auto;text-align:center}
.concept-eyebrow{
  font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
  color:#ea5649;margin-bottom:14px
}
.concept-title{
  font-size:clamp(1.5rem,3vw,2.2rem);font-weight:900;color:#fff;
  letter-spacing:-.4px;margin-bottom:10px
}
.concept-sub{
  font-size:.92rem;color:rgba(255,255,255,.5);
  max-width:520px;margin:0 auto 44px;line-height:1.7
}
.concept-steps{
  display:grid;grid-template-columns:repeat(3,1fr);gap:8px;
  margin-bottom:36px
}
.concept-step{
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:12px;padding:24px 16px
}
.concept-step-num{
  font-size:.64rem;font-weight:800;letter-spacing:.1em;
  text-transform:uppercase;color:rgba(255,255,255,.25);margin-bottom:12px
}
.concept-step-icon{font-size:2rem;margin-bottom:10px}
.concept-step-title{
  font-size:.92rem;font-weight:800;color:#fff;margin-bottom:6px
}
.concept-step-desc{font-size:.8rem;color:rgba(255,255,255,.45);line-height:1.55}
.concept-link{
  display:inline-flex;align-items:center;gap:7px;
  color:rgba(255,255,255,.55);
  font-size:.88rem;font-weight:600;text-decoration:none;
  border-bottom:1px solid rgba(255,255,255,.15);
  padding-bottom:2px;transition:color .2s
}
.concept-link:hover{color:#fff}

/* ── SHARED UTILS ── */
.container{max-width:1100px;margin:0 auto;padding:0 20px}
.section-link-btn{
  display:inline-flex;align-items:center;gap:8px;
  background:#0c1e2e;color:#fff;
  padding:12px 26px;border-radius:7px;
  font-size:.9rem;font-weight:700;text-decoration:none;
  transition:background .2s
}
.section-link-btn:hover{background:#ea5649}
.section-link-btn.outline{
  background:transparent;border:1.5px solid rgba(255,255,255,.2);color:rgba(255,255,255,.8)
}
.section-link-btn.outline:hover{border-color:#ea5649;color:#fff;background:rgba(234,86,73,.08)}

/* ── RESPONSIVE ── */
@media(max-width:900px){
  .saison-wrapper{grid-template-columns:1fr}
  .podium-grid{grid-template-columns:1fr}
  .podium-card.first{order:-1}
  .missions-grid{grid-template-columns:1fr}
  .concept-steps{grid-template-columns:1fr}
}
@media(max-width:640px){
  #hero{padding-top:88px}
  .hero-mascots{gap:12px}
  .hero-masc-wrap img{height:130px!important}
  .hero-masc-wrap.center img{height:160px!important}
  .hero-h1{letter-spacing:-1px}
  .bonus-countdown{gap:6px;padding:12px 14px}
  .bcnt-num{font-size:1.2rem}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ============================================================
     SECTION 1 — HERO : LA VENDEE JOUE
     ============================================================ -->
<section id="hero">
  <div class="hero-content">

    <div class="hero-eyebrow">
      <span class="hero-eyebrow-dot"></span>
      <?php if (!empty($active_season['title'])): ?>
        <?= e($active_season['title']) ?> &mdash; Saison en cours
      <?php else: ?>
        Zone85 &mdash; L&rsquo;Esprit Vendee
      <?php endif; ?>
    </div>

    <h1 class="hero-h1">LA <em>VENDEE</em> JOUE</h1>

    <div class="hero-taglines">
      <div class="hero-tagline-line"><strong>Choisis ton clan.</strong></div>
      <div class="hero-tagline-line"><strong>Pars en mission.</strong></div>
      <div class="hero-tagline-line"><strong>Laisse une trace.</strong></div>
    </div>

    <div class="hero-ctas">
      <a href="inscription.php" class="hero-btn-primary">Rejoindre la Zone &#8594;</a>
      <a href="missions.php" class="hero-btn-secondary">Voir les missions</a>
    </div>

    <!-- Mascottes cliquables -->
    <div class="hero-mascots">
      <?php
      $masc_data = [
          'bocage'  => ['alt' => 'Bran le Bocager',    'center' => false],
          'littoral'=> ['alt' => 'Gabin Culsmouilles', 'center' => true],
          'marais'  => ['alt' => 'Meric l\'Ancien',    'center' => false],
      ];
      $clan_names = [
          'bocage'  => 'Clan du Bocage',
          'littoral'=> 'Clan du Littoral',
          'marais'  => 'Clan du Marais',
      ];
      foreach ($masc_data as $_slug => $_md):
          $_clan    = $clans[$_slug] ?? [];
          $_mascot  = $_clan['mascot'] ?? "mascotte-{$_slug}.png";
          $_center  = $_md['center'] ? ' center' : '';
      ?>
      <a href="clans.php#<?= $_slug ?>" class="hero-masc-wrap<?= $_center ?>" aria-label="<?= $clan_names[$_slug] ?>">
        <img src="<?= img($_mascot) ?>" alt="<?= e($_md['alt']) ?>" loading="lazy">
        <span class="hero-masc-label"><?= e($clan_names[$_slug]) ?></span>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- ============================================================
     SECTION 2 — SAISON EN COURS
     ============================================================ -->
<section id="s-saison">
  <div class="container">
    <div class="saison-wrapper">

      <!-- Colonne gauche : info saison -->
      <div>
        <div class="saison-eyebrow">Saison en cours</div>
        <?php if (!empty($active_season['title'])): ?>
          <h2 class="saison-title"><?= e($active_season['title']) ?></h2>
          <?php if (!empty($active_season['period'])): ?>
            <p style="font-size:.82rem;color:rgba(255,255,255,.38);margin-bottom:20px;text-transform:capitalize"><?= e($active_season['period']) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <h2 class="saison-title">Saison en cours</h2>
        <?php endif; ?>

        <!-- Grande mission -->
        <div class="saison-grande-mission">
          <div class="saison-grande-mission-label">Le Grand Defi du moment</div>
          <?php
          $gm_title = null;
          if (!empty($grande_mission['title'])) {
              $gm_title = $grande_mission['title'];
          } elseif (!empty($active_season['main_mission'])) {
              $gm_title = $active_season['main_mission'];
          }
          ?>
          <?php if ($gm_title): ?>
            <div class="saison-grande-mission-title"><?= e($gm_title) ?></div>
            <div class="saison-grande-mission-sub">Participez, documentez, partagez &mdash; chaque action compte pour ton clan.</div>
          <?php else: ?>
            <div class="saison-grande-mission-title">La grande mission de saison arrive bientot.</div>
            <div class="saison-grande-mission-sub">Reste connecte pour ne pas rater le depart.</div>
          <?php endif; ?>
        </div>

        <!-- Bandeau Bataille des Clans -->
        <div class="saison-bataille">
          <span>&#128737;&#65039;</span>
          <span>Rejoins la Bataille des Clans &mdash; ton clan a besoin de toi</span>
        </div>
      </div>

      <!-- Colonne droite : classement rapide -->
      <div class="saison-right">
        <div class="saison-right-label">Score de saison</div>
        <?php
        $rank_s = 0;
        $max_s  = max(1, array_reduce($clans_sorted, fn($c, $cl) => max($c, $cl['season_score']), 0));
        foreach ($clans_sorted as $_sl => $_cl):
            $bar_w = ($max_s > 0) ? round(($_cl['season_score'] / $max_s) * 92) : 0;
            $is_last_s = ($rank_s === count($clans_sorted) - 1);
        ?>
        <div style="display:grid;grid-template-columns:130px 1fr 72px;align-items:center;gap:10px;<?= $is_last_s ? '' : 'margin-bottom:16px' ?>">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:32px;height:32px;border-radius:6px;overflow:hidden;background:rgba(255,255,255,.06);flex-shrink:0">
              <img src="<?= img($_cl['mascot'] ?? "mascotte-{$_sl}.png") ?>" alt="<?= e($_cl['hero_name'] ?? '') ?>" style="width:100%;height:100%;object-fit:contain">
            </div>
            <div>
              <div style="font-size:.62rem;color:rgba(255,255,255,.35);line-height:1"><?= ['🥇','🥈','🥉'][$rank_s] ?? '' ?></div>
              <div style="font-size:.82rem;font-weight:700;color:#fff"><?= e(ucfirst($_sl)) ?></div>
              <div style="font-size:.62rem;color:rgba(255,255,255,.35)"><?= (int)$_cl['members_count'] ?> membres</div>
            </div>
          </div>
          <div style="background:rgba(255,255,255,.08);border-radius:4px;height:10px;overflow:hidden">
            <div style="width:<?= $bar_w ?>%;height:100%;background:#ea5649;border-radius:4px;transition:width .6s ease"></div>
          </div>
          <div style="text-align:right">
            <div style="font-size:.86rem;font-weight:800;color:#fff"><?= number_format((int)$_cl['season_score'], 0, ',', '&#8201;') ?></div>
            <div style="font-size:.6rem;color:rgba(255,255,255,.3)">pts</div>
          </div>
        </div>
        <?php $rank_s++; endforeach; ?>
        <div style="border-top:1px solid rgba(255,255,255,.07);margin-top:16px;padding-top:12px;font-size:.7rem;color:rgba(255,255,255,.25)">Score saisonnier — remis a zero a la prochaine saison</div>
      </div>

    </div>
  </div>
</section>


<!-- ============================================================
     SECTION 3 — PODIUM CLANS
     ============================================================ -->
<section id="s-podium">
  <div class="container">

    <div class="podium-section-head">
      <div class="podium-eyebrow">Les 3 clans</div>
      <h2 class="podium-title">La course de saison</h2>
      <p class="podium-sub">Trois identites, un meme territoire. Rejoins le clan qui te ressemble.</p>
    </div>

    <div class="podium-grid">
      <?php
      $podium_order = [1, 0, 2]; // visuel : 2e a gauche, 1er au centre, 3e a droite
      foreach ($podium_order as $_pi):
          $_pc   = $clans_podium[$_pi] ?? null;
          if (!$_pc) continue;
          $_pslug = $_pc['slug'] ?? 'bocage';
          $_is_first = ($_pi === 0);
          $_medal_icons = ['🥇','🥈','🥉'];
      ?>
      <div class="podium-card<?= $_is_first ? ' first' : '' ?>">
        <div class="podium-medal"><?= $_medal_icons[$_pi] ?></div>
        <div class="podium-header podium-header-<?= e($_pslug) ?>">
          <img class="podium-masc" src="<?= img($_pc['mascot'] ?? "mascotte-{$_pslug}.png") ?>" alt="<?= e($_pc['hero_name'] ?? '') ?>" loading="lazy">
          <div class="podium-clan-name"><?= e($_pc['name'] ?? ucfirst($_pslug)) ?></div>
        </div>
        <div class="podium-body">
          <div class="podium-name"><?= e($_pc['name'] ?? ucfirst($_pslug)) ?></div>
          <div class="podium-hero"><?= e($_pc['hero_name'] ?? '') ?></div>
          <div class="podium-stats">
            <div class="podium-stat">
              <div class="podium-stat-val"><?= (int)$_pc['season_score'] > 0 ? number_format((int)$_pc['season_score'], 0, ',', '&#8201;') : '&mdash;' ?></div>
              <div class="podium-stat-lbl">Score</div>
            </div>
            <div class="podium-stat">
              <div class="podium-stat-val"><?= (int)$_pc['members_count'] > 0 ? (int)$_pc['members_count'] : '&mdash;' ?></div>
              <div class="podium-stat-lbl">Membres</div>
            </div>
            <div class="podium-stat">
              <div class="podium-stat-val"><?= (int)($_pc['trophies'] ?? 0) ?></div>
              <div class="podium-stat-lbl">Trophees</div>
            </div>
          </div>
          <a href="clans.php#<?= e($_pslug) ?>" class="podium-btn">Rejoindre &#8594;</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="podium-cta">
      <a href="clans.php" class="section-link-btn">Voir le detail des clans &#8594;</a>
      <p style="margin-top:12px;font-size:.78rem;color:#9ca3af">Les pts de saison sont remis a zero chaque saison &mdash; l&rsquo;XP personnel est a vie.</p>
    </div>

  </div>
</section>


<!-- ============================================================
     SECTION 4 — BONUS DU MOMENT
     ============================================================ -->
<section id="s-bonus">
  <div class="container">
    <div class="bonus-inner">

      <div class="bonus-section-label">Bonus du moment</div>

      <?php if (!empty($flash_mission)): ?>

        <div class="bonus-active">
          <div class="bonus-live-badge">
            <span class="bonus-live-dot"></span>
            LIVE
          </div>
          <div class="bonus-multiplier">
            &#9889;&#65039; <span><?= e($flash_mission['title'] ?? 'Bonus Flash') ?></span>
          </div>
          <?php if (!empty($flash_mission['xp_reward'])): ?>
            <div class="bonus-active-title">+<?= (int)$flash_mission['xp_reward'] ?> XP &mdash; <?= e($flash_mission['description'] ?? '') ?></div>
          <?php elseif (!empty($flash_mission['description'])): ?>
            <div class="bonus-active-title"><?= e($flash_mission['description']) ?></div>
          <?php endif; ?>
          <?php if (!empty($flash_mission['flash_end_at'])): ?>
            <div class="bonus-countdown"
                 id="bonus-countdown"
                 data-end="<?= e($flash_mission['flash_end_at']) ?>">
              <div class="bcnt-box"><div class="bcnt-num" id="bcnt-h">--</div><div class="bcnt-lbl">Heures</div></div>
              <div class="bcnt-box"><div class="bcnt-num" id="bcnt-m">--</div><div class="bcnt-lbl">Min</div></div>
              <div class="bcnt-box"><div class="bcnt-num" id="bcnt-s">--</div><div class="bcnt-lbl">Sec</div></div>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>

        <div class="bonus-none">
          <div class="bonus-none-icon">&#128268;</div>
          <div class="bonus-none-text">Pas de bonus en cours &mdash; surveille la Zone !</div>
        </div>

      <?php endif; ?>

    </div>
  </div>
</section>


<!-- ============================================================
     SECTION 5 — DERNIERES MISSIONS
     ============================================================ -->
<section id="s-missions">
  <div class="container">

    <div class="missions-section-head">
      <div class="missions-eyebrow">Missions</div>
      <h2 class="missions-section-title">Dernieres missions disponibles</h2>
    </div>

    <?php if (!empty($recent_missions)): ?>
    <div class="missions-grid">
      <?php foreach ($recent_missions as $_m): ?>
        <?php
        $_mtype = $_m['mission_type'] ?? $_m['game_type'] ?? 'default';
        $_micon = $mission_type_icons[$_mtype] ?? $mission_type_icons['default'];
        $_mxp   = !empty($_m['xp_reward']) ? '+' . (int)$_m['xp_reward'] . ' XP' : 'XP a gagner';
        $_mst   = ($_m['status'] ?? 'active') === 'active' ? 'active' : 'upcoming';
        $_mst_label = $_mst === 'active' ? 'Actif' : 'A venir';
        ?>
        <div class="mission-card">
          <div class="mission-type-row">
            <span class="mission-type-icon"><?= $_micon ?></span>
            <span class="mission-type-label"><?= e(str_replace(['_','-'], ' ', $_mtype)) ?></span>
          </div>
          <div class="mission-title"><?= e($_m['title'] ?? 'Mission') ?></div>
          <div class="mission-bottom">
            <span class="mission-xp"><?= $_mxp ?></span>
            <span class="mission-status-badge mission-status-<?= $_mst ?>"><?= $_mst_label ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:32px;color:#9ca3af;font-size:.9rem">
      Les missions de la saison arrivent bientot.
    </div>
    <?php endif; ?>

    <div class="missions-cta">
      <a href="missions.php" class="section-link-btn">Toutes les missions &#8594;</a>
    </div>

  </div>
</section>


<!-- ============================================================
     SECTION — STATS ANIMÉES + FIL COMMUNAUTÉ
     ============================================================ -->
<section style="padding:64px 0;background:var(--beige)">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start" class="reveal">

      <!-- Stats compteurs -->
      <div>
        <div class="overline-label">La Zone en chiffres</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px">
          <?php
          $stat_defs = [
            ['val' => $index_stats['members'],  'label' => 'Zonautes',         'icon' => '👥'],
            ['val' => $index_stats['missions'],  'label' => 'Missions validées', 'icon' => '🎯'],
            ['val' => $index_stats['xp_total'],  'label' => 'XP distribués',    'icon' => '⚡'],
          ];
          foreach ($stat_defs as $i => $st): ?>
          <div style="background:var(--white);border-radius:var(--radius-lg);padding:20px 16px;text-align:center;border:1.5px solid var(--beige-dark)">
            <div style="font-size:1.5rem;margin-bottom:6px"><?= $st['icon'] ?></div>
            <div class="counter-num reveal reveal-delay-<?= $i+1 ?>"
                 data-target="<?= $st['val'] ?>"
                 style="font-size:1.5rem;font-weight:900;color:var(--navy-dark);line-height:1">
              <?= number_format($st['val'], 0, ',', ' ') ?>
            </div>
            <div style="font-size:.7rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-top:4px">
              <?= $st['label'] ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Fil communauté widget -->
      <div class="reveal reveal-delay-2">
        <div class="cf-widget">
          <div class="cf-widget-header">
            <span class="cf-widget-title">🌍 Fil de la Zone</span>
            <a href="communaute.php" class="cf-widget-link">Tout voir →</a>
          </div>
          <?php if (empty($index_feed)): ?>
          <div style="padding:24px 20px;text-align:center;font-size:.85rem;color:var(--text-muted)">
            Le fil est vide pour l'instant. Revenez bientôt !
          </div>
          <?php else:
            $feed_icons = ['mission_new'=>'🎯','mission_complete'=>'✅','badge_unlock'=>'🏅',
                           'flash_start'=>'⚡','collectible_found'=>'🔍','rando_done'=>'🥾','ktc_win'=>'🥐'];
            foreach ($index_feed as $fi):
              $fi_icon = $fi['icon_emoji'] ?: ($feed_icons[$fi['event_type']] ?? '📋');
          ?>
          <div class="cf-widget-item">
            <span class="cf-widget-icon"><?= e($fi_icon) ?></span>
            <div class="cf-widget-body">
              <div class="cf-widget-text"><?= e(mb_substr($fi['title'], 0, 60)) ?></div>
              <div class="cf-widget-meta">
                <?= $fi['pseudo'] ? e($fi['pseudo']) . ' · ' : '' ?><?= format_date($fi['created_at'], 'short') ?>
              </div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<script>
// Anime les compteurs quand visibles
(function(){
  if(!window.IntersectionObserver) return;
  var obs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(!e.isIntersecting) return;
      var el = e.target;
      var target = parseInt(el.dataset.target, 10);
      if(!isNaN(target)) window.z85AnimateCounter(el, 0, target, 1200);
      obs.unobserve(el);
    });
  },{threshold:.3});
  document.querySelectorAll('.counter-num[data-target]').forEach(function(el){ obs.observe(el); });
})();
</script>

<!-- ============================================================
     SECTION 6 — COMMENT CA MARCHE
     ============================================================ -->
<section id="s-concept">
  <div class="container">
    <div class="concept-inner">

      <div class="concept-eyebrow">Le concept</div>
      <h2 class="concept-title">Comment ca marche</h2>
      <p class="concept-sub">Un terrain de jeu vendeen. Trois clans. Des missions toute l&rsquo;annee. Simple.</p>

      <div class="concept-steps">
        <div class="concept-step">
          <div class="concept-step-num">Etape 01</div>
          <div class="concept-step-icon">&#128737;&#65039;</div>
          <div class="concept-step-title">Choisis ton clan</div>
          <div class="concept-step-desc">Bocage, Littoral ou Marais &mdash; trois identites vendees, trois façons de vivre la Zone. Lequel te ressemble ?</div>
        </div>
        <div class="concept-step">
          <div class="concept-step-num">Etape 02</div>
          <div class="concept-step-icon">&#127919;</div>
          <div class="concept-step-title">Pars en mission</div>
          <div class="concept-step-desc">Quiz, photos, randos, objets mysteres, defis saisonniers. Chaque action compte, chaque trace reste.</div>
        </div>
        <div class="concept-step">
          <div class="concept-step-num">Etape 03</div>
          <div class="concept-step-icon">&#9889;</div>
          <div class="concept-step-title">Gagne des XP pour ton clan</div>
          <div class="concept-step-desc">Ton XP personnel est permanent, a vie. Les pts de saison de ton clan s&rsquo;accumulent &mdash; le vainqueur emporte un trophee archive.</div>
        </div>
      </div>

      <a href="concept.php" class="concept-link">En savoir plus sur le concept &#8594;</a>

    </div>
  </div>
</section>

<?php
// Countdown JS vanilla pour le bonus flash
if (!empty($flash_mission['flash_end_at'])):
?>
<script>
(function(){
  var end = new Date("<?= addslashes(e($flash_mission['flash_end_at'])) ?>").getTime();
  function pad(n){return n<10?'0'+n:n}
  function tick(){
    var now = Date.now(), diff = end - now;
    if(diff <= 0){
      ['bcnt-h','bcnt-m','bcnt-s'].forEach(function(id){
        var el = document.getElementById(id);
        if(el) el.textContent = '00';
      });
      return;
    }
    var h = Math.floor(diff/3600000);
    var m = Math.floor((diff%3600000)/60000);
    var s = Math.floor((diff%60000)/1000);
    var eh = document.getElementById('bcnt-h');
    var em = document.getElementById('bcnt-m');
    var es = document.getElementById('bcnt-s');
    if(eh) eh.textContent = pad(h);
    if(em) em.textContent = pad(m);
    if(es) es.textContent = pad(s);
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php endif; ?>

<?php
if (function_exists('render_hidden_collectibles')) {
    render_hidden_collectibles('index');
}
require_once 'includes/footer.php';
?>
