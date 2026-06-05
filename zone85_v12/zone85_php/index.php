<?php
$page_title       = 'Zone85 — La Vendée vécue, racontée et jouée';
$page_description = 'Les Échos de terrain, les Randos vendéennes, VICTOR le livre, Les Invisibles — dans un jeu communautaire pour la Vendée. Rejoins Zone85, c\'est gratuit.';
$page_canonical   = 'https://www.zone85.fr/index.php';
$page_robots      = 'index,follow';
$page_og_title    = 'Zone85 — La Vendée vécue, racontée et jouée';
$page_og_description = 'Les Échos, les Randos, VICTOR le livre, Les Invisibles — et un jeu communautaire qui tisse le tout. Rejoins la Zone.';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'ZONE85',
    'url'      => 'https://www.zone85.fr',
    'description' => 'Plateforme communautaire vendéenne : articles, randonnées, livre VICTOR, jeu Les Invisibles et Bataille des Clans.',
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => 'https://www.zone85.fr/missions.php?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
$current_page = 'index';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';

// ── Détection utilisateur ────────────────────────────────────
$_is_guest = !(session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user']));
$_me       = $_is_guest ? null : $_SESSION['user'];

// ── Données communes ─────────────────────────────────────────
if (db_enabled()) {
    $_clans_db = fetch_all_clans();
    if ($_clans_db !== null) $clans = $_clans_db;
    $_season_db = fetch_active_season();
    if ($_season_db !== null) $active_season = $_season_db;
}

// Tri clans par score
$clans_sorted = $clans;
uasort($clans_sorted, fn($a, $b) => $b['season_score'] <=> $a['season_score']);
$clans_sorted_arr = array_values($clans_sorted);

// ── Données dashboard (membres connectés) ────────────────────
$dash_missions = [];
$dash_feed     = [];
$flash_mission = null;

if (!$_is_guest && db_enabled()) {
    $_missions_db = fetch_featured_missions(3);
    if ($_missions_db !== null) $dash_missions = array_values($_missions_db);
    if (function_exists('fetch_community_feed')) {
        $dash_feed = fetch_community_feed(1, 6);
    }
    try {
        $pdo = db();
        if ($pdo) {
            $stmt = $pdo->query(
                "SELECT * FROM missions WHERE is_flash=1 AND status='active'
                 AND (flash_end_at IS NULL OR flash_end_at > NOW()) LIMIT 1"
            );
            $flash_mission = $stmt->fetch() ?: null;
        }
    } catch (Exception $e) {}
}

// Icônes types missions
$_mtype_icons = [
    'quiz'               => '🎯', 'photo'       => '📸',
    'rando'              => '🥾', 'ktc'          => '🔍',
    'keto_kole_tche'     => '🔍', 'vote'         => '🗳️',
    'seasonal_collective'=> '🛡️', 'flash'        => '⚡',
    'default'            => '🎮',
];

$page_styles = '<style>
/* ── INDEX — styles spécifiques ── */

/* Hero */
.idx-hero{min-height:100svh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:100px 20px 0;position:relative;overflow:hidden;background:linear-gradient(160deg,#060e16 0%,#0c1e2e 40%,#12314e 100%)}
.idx-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(234,86,73,.07) 0%,transparent 70%);pointer-events:none}
.idx-hero::after{content:"";position:absolute;bottom:0;left:0;right:0;height:100px;background:linear-gradient(to bottom,transparent,#0c1e2e);pointer-events:none}
.idx-hero-content{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;width:100%;max-width:840px;animation:heroIn .9s cubic-bezier(.22,.61,.36,1) both}
@keyframes heroIn{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
.idx-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(234,86,73,.12);border:1px solid rgba(234,86,73,.28);color:#f5a99f;padding:5px 16px;border-radius:4px;font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:24px}
.idx-eyebrow-dot{width:6px;height:6px;background:#ea5649;border-radius:50%;animation:blink 1.6s ease-in-out infinite}
.idx-h1{font-size:clamp(2.6rem,6.5vw,5rem);font-weight:900;color:#fff;text-align:center;line-height:1.08;letter-spacing:-2px;margin-bottom:18px}
.idx-h1 em{color:#ea5649;font-style:normal}
.idx-tagline{font-size:clamp(.9rem,2vw,1.04rem);color:rgba(255,255,255,.5);text-align:center;margin-bottom:40px;line-height:1.7;max-width:540px}
.idx-tagline strong{color:rgba(255,255,255,.8);font-weight:700}
.idx-ctas{display:flex;gap:14px;flex-wrap:wrap;justify-content:center;margin-bottom:56px}
.idx-btn-primary{display:inline-flex;align-items:center;gap:8px;background:#ea5649;color:#fff;padding:14px 30px;border-radius:6px;font-size:1rem;font-weight:800;text-decoration:none;transition:background .2s,transform .15s}
.idx-btn-primary:hover{background:#d44035;transform:translateY(-1px)}
.idx-btn-secondary{display:inline-flex;align-items:center;gap:8px;background:transparent;color:rgba(255,255,255,.7);border:1.5px solid rgba(255,255,255,.2);padding:14px 28px;border-radius:6px;font-size:1rem;font-weight:600;text-decoration:none;transition:border-color .2s,color .2s}
.idx-btn-secondary:hover{border-color:rgba(255,255,255,.5);color:#fff}
.idx-mascots{width:100%;max-width:860px;display:flex;align-items:flex-end;justify-content:center;gap:40px;position:relative;z-index:2;transform:translateY(12px)}
.idx-masc-wrap{display:flex;flex-direction:column;align-items:center;cursor:pointer;text-decoration:none}
.idx-masc-wrap img{height:180px;width:auto;object-fit:contain;filter:drop-shadow(0 12px 32px rgba(0,0,0,.55));transition:transform .3s}
.idx-masc-wrap:hover img{transform:translateY(-6px)}
.idx-masc-wrap.center img{height:230px}
.idx-masc-label{margin-top:10px;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.65);background:rgba(255,255,255,.08);padding:5px 16px;border-radius:20px;border:1px solid rgba(255,255,255,.12);white-space:nowrap}

/* Piliers de contenu */
.idx-pillars-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.idx-pillar-card{background:#fff;border-radius:14px;overflow:hidden;border:1.5px solid var(--beige-dark);transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column}
.idx-pillar-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.09)}
.idx-pillar-head{padding:22px 20px 14px;display:flex;align-items:center;gap:12px}
.idx-pillar-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.idx-pillar-label{font-size:.93rem;font-weight:800;color:var(--navy-dark);line-height:1.2}
.idx-pillar-sub{font-size:.7rem;color:var(--text-muted);font-weight:600;margin-top:2px}
.idx-pillar-body{padding:0 20px 20px;font-size:.83rem;color:var(--text-muted);line-height:1.65;flex:1}
.idx-pillar-link{display:inline-flex;align-items:center;gap:5px;margin-top:14px;font-size:.78rem;font-weight:700;color:var(--primary);text-decoration:none}
.idx-pillar-tag{display:inline-block;font-size:.58rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase;padding:2px 7px;border-radius:4px;margin-left:7px;vertical-align:middle}
.idx-pillar-tag-member{background:rgba(234,86,73,.1);color:var(--primary)}
.idx-pillar-tag-soon{background:rgba(123,71,178,.12);color:#7b47b2}

/* VICTOR block */
.idx-victor{background:linear-gradient(135deg,#1a1200,#2d2000);border-radius:16px;overflow:hidden;display:grid;grid-template-columns:1fr auto}
.idx-victor-content{padding:40px 36px}
.idx-victor-tag{font-size:.62rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:#d4af37;margin-bottom:14px;display:block}
.idx-victor-title{font-size:1.8rem;font-weight:900;color:#fff;margin-bottom:12px;line-height:1.2}
.idx-victor-desc{font-size:.9rem;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:24px}
.idx-victor-cta{display:inline-flex;align-items:center;gap:8px;background:#d4af37;color:#1a1200;padding:12px 24px;border-radius:7px;font-weight:800;font-size:.9rem;text-decoration:none;transition:background .2s}
.idx-victor-cta:hover{background:#c9a430}
.idx-victor-visual{background:rgba(212,175,55,.06);display:flex;align-items:center;justify-content:center;padding:36px 40px;border-left:1px solid rgba(212,175,55,.12);font-size:7rem;line-height:1}

/* Les Invisibles teaser */
.idx-invisibles{border-radius:14px;overflow:hidden;background:linear-gradient(135deg,#0d0520,#1a0a35);border:1px solid rgba(123,71,178,.2);padding:36px 32px;display:flex;align-items:center;gap:32px}
.idx-invisibles-icon{font-size:3.2rem;flex-shrink:0;line-height:1;filter:drop-shadow(0 0 16px rgba(168,125,224,.4))}
.idx-invisibles-body{flex:1}
.idx-invisibles-tag{font-size:.6rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:#a87de0;margin-bottom:8px;display:block}
.idx-invisibles-title{font-size:1.2rem;font-weight:900;color:#fff;margin-bottom:6px}
.idx-invisibles-desc{font-size:.86rem;color:rgba(255,255,255,.5);line-height:1.6}
.idx-invisibles-badge{background:rgba(168,125,224,.15);border:1px solid rgba(168,125,224,.3);color:#c9a7ef;border-radius:20px;padding:5px 14px;font-size:.72rem;font-weight:800;white-space:nowrap;flex-shrink:0}

/* Terrain de jeu — 3 étapes slim */
.idx-game-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.idx-game-step{background:rgba(255,255,255,.04);border-radius:12px;padding:24px 20px;border:1px solid rgba(255,255,255,.07)}
.idx-game-step-num{font-size:.6rem;font-weight:900;text-transform:uppercase;letter-spacing:.12em;color:var(--primary);margin-bottom:10px}
.idx-game-step-icon{font-size:1.5rem;margin-bottom:10px;display:block}
.idx-game-step-title{font-size:.92rem;font-weight:800;color:#fff;margin-bottom:6px}
.idx-game-step-desc{font-size:.79rem;color:rgba(255,255,255,.45);line-height:1.6}

/* Clan race */
.idx-clan-race{background:rgba(255,255,255,.04);border-radius:12px;padding:20px;border:1px solid rgba(255,255,255,.08)}
.idx-clan-race-row{display:grid;grid-template-columns:120px 1fr 64px;align-items:center;gap:10px}
.idx-clan-race-row+.idx-clan-race-row{margin-top:14px}
.idx-race-bar{background:rgba(255,255,255,.07);border-radius:4px;height:9px;overflow:hidden}
.idx-race-fill{height:100%;border-radius:4px;background:#ea5649;transition:width .8s cubic-bezier(.22,1,.36,1)}

/* Dashboard membre */
.idx-dash-hero{background:linear-gradient(155deg,#060e16 0%,#0c1e2e 60%,#0e2540 100%);padding:100px 0 48px;border-bottom:3px solid rgba(234,86,73,.35)}
.idx-dash-greeting{display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.idx-dash-avatar{width:56px;height:56px;border-radius:12px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.idx-dash-name{font-size:clamp(1.4rem,3vw,2rem);font-weight:900;color:#fff;line-height:1.1}
.idx-dash-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
.idx-dash-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;background:rgba(255,255,255,.1);color:rgba(255,255,255,.75)}
.idx-xp-row{margin-top:20px;max-width:400px}
.idx-xp-label{display:flex;justify-content:space-between;font-size:.72rem;color:rgba(255,255,255,.45);font-weight:600;margin-bottom:6px}
.idx-xp-track{background:rgba(255,255,255,.1);border-radius:20px;height:8px;overflow:hidden}
.idx-xp-fill{height:100%;background:linear-gradient(90deg,#ea5649,#f07066);border-radius:20px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.idx-mission-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.idx-mission-card{background:var(--white);border-radius:12px;padding:20px 18px;border:1.5px solid var(--beige-dark);display:flex;flex-direction:column;gap:10px;transition:transform .2s,box-shadow .2s;text-decoration:none}
.idx-mission-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.09)}
.idx-mission-icon{font-size:1.4rem;line-height:1}
.idx-mission-title{font-size:.92rem;font-weight:800;color:var(--text);line-height:1.35;flex:1}
.idx-mission-footer{display:flex;align-items:center;justify-content:space-between;margin-top:auto}
.idx-mission-xp{font-size:.78rem;font-weight:800;color:var(--primary)}
.idx-mission-badge{font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:4px;background:rgba(42,157,92,.1);color:#1a6b3a}
.idx-feed-item{display:flex;align-items:flex-start;gap:10px;padding:12px 0;border-bottom:1px solid var(--beige-dark)}
.idx-feed-item:last-child{border-bottom:none}
.idx-feed-icon{font-size:1.1rem;flex-shrink:0;line-height:1;margin-top:2px}
.idx-feed-text{font-size:.84rem;font-weight:600;color:var(--text);line-height:1.35}
.idx-feed-meta{font-size:.7rem;color:var(--text-muted);margin-top:2px}
.idx-flash-badge{display:inline-flex;align-items:center;gap:6px;background:#ea5649;color:#fff;font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;padding:4px 12px;border-radius:4px}
.idx-flash-dot{width:6px;height:6px;background:#fff;border-radius:50%;animation:blink .9s infinite}

/* Responsive */
@media(max-width:900px){
  .idx-hero{padding:96px 20px 0}
  .idx-mascots{gap:14px}
  .idx-masc-wrap img{height:130px!important}
  .idx-masc-wrap.center img{height:165px!important}
  .idx-pillars-grid,.idx-game-steps,.idx-mission-grid{grid-template-columns:1fr}
  .idx-victor{grid-template-columns:1fr}
  .idx-victor-visual{display:none}
  .idx-invisibles{flex-direction:column;text-align:center;gap:16px}
  .idx-dash-hero{padding:90px 0 40px}
}
@media(max-width:640px){
  .idx-h1{letter-spacing:-1px}
  .idx-ctas{flex-direction:column;align-items:stretch;max-width:280px;margin-left:auto;margin-right:auto}
  .idx-clan-race-row{grid-template-columns:100px 1fr 50px}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';

// ── Données post-nav ─────────────────────────────────────────
$_nav_me = isset($_nav_user) ? $_nav_user : $_me;
$_is_guest = empty($_nav_me);
?>

<?php if ($_is_guest): ?>
<!-- ================================================================
     VISITEUR — Contenu d'abord, jeu comme atmosphère
     Beat 1 : Hero  |  Beat 2 : Piliers  |  Beat 3 : VICTOR
     Beat 4 : Les Invisibles  |  Beat 5 : Le jeu  |  Beat 6 : CTA
================================================================ -->

<!-- BEAT 1 : HERO ─────────────────────────────────────────── -->
<section class="idx-hero">
  <div class="idx-hero-content">

    <div class="idx-eyebrow">
      <span class="idx-eyebrow-dot"></span>
      <?= !empty($active_season['title']) ? e($active_season['title']) . ' — Saison en cours' : 'Zone85 · L\'Esprit Vendée' ?>
    </div>

    <h1 class="idx-h1">La <em>Vendée</em><br>vécue. Racontée. Jouée.</h1>

    <p class="idx-tagline">
      Les Échos de terrain, les Randos vendéennes, VICTOR le livre —<br>
      <strong>dans un jeu communautaire qui tisse le tout.</strong>
    </p>

    <div class="idx-ctas">
      <a href="inscription.php" class="idx-btn-primary">Rejoindre la Zone →</a>
      <a href="concept.php"     class="idx-btn-secondary">Découvrir le concept</a>
    </div>

    <!-- Mascottes — atmosphère des 3 clans -->
    <div class="idx-mascots">
      <?php
      $masc_order = [
          'bocage'   => ['alt' => 'Bran le Bocager',    'center' => false, 'name' => 'Clan du Bocage'],
          'littoral' => ['alt' => 'Gabin Culsmouilles', 'center' => true,  'name' => 'Clan du Littoral'],
          'marais'   => ['alt' => 'Méric l\'Ancien',    'center' => false, 'name' => 'Clan du Marais'],
      ];
      foreach ($masc_order as $_slug => $_md):
          $_cl = $clans[$_slug] ?? [];
          $_mascot = $_cl['mascot'] ?? "mascotte-{$_slug}.png";
      ?>
      <a href="clans.php#<?= $_slug ?>" class="idx-masc-wrap<?= $_md['center'] ? ' center' : '' ?>" aria-label="<?= $_md['name'] ?>">
        <img src="<?= img($_mascot) ?>" alt="<?= e($_md['alt']) ?>" loading="eager">
        <span class="idx-masc-label"><?= $_md['name'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- BEAT 2 : PILIERS DE CONTENU ────────────────────────────── -->
<section class="page-section-white" style="border-top:3px solid rgba(234,86,73,.35)">
  <div class="container">
    <div class="page-section-head">
      <div class="page-section-eyebrow">La Zone</div>
      <h2 class="page-section-title">Ce qui t'attend</h2>
      <p class="page-section-sub">Zone85 c'est d'abord des contenus sur la Vendée — portés par une communauté qui joue ensemble.</p>
    </div>

    <div class="idx-pillars-grid">

      <!-- Les Échos -->
      <div class="idx-pillar-card">
        <div class="idx-pillar-head">
          <div class="idx-pillar-icon" style="background:rgba(15,40,70,.08)">📰</div>
          <div>
            <div class="idx-pillar-label">Les Échos</div>
            <div class="idx-pillar-sub">Articles · Portraits · Terrain</div>
          </div>
        </div>
        <div class="idx-pillar-body">
          Histoires vraies, portraits de Vendéens, actualités du territoire. Nos reporters de terrain racontent la Vendée qui ne fait pas les unes.
          <a href="les-echos.php" class="idx-pillar-link">Lire les Échos →</a>
        </div>
      </div>

      <!-- Les Randos -->
      <div class="idx-pillar-card">
        <div class="idx-pillar-head">
          <div class="idx-pillar-icon" style="background:rgba(30,80,50,.08)">🥾</div>
          <div>
            <div class="idx-pillar-label">Les Randos</div>
            <div class="idx-pillar-sub">Itinéraires · Découverte · Terrain</div>
          </div>
        </div>
        <div class="idx-pillar-body">
          Des circuits vendéens pour explorer bocage, littoral et marais à pied ou à vélo. Chaque rando complétée rapporte des XP à ton clan.
          <a href="randos.php" class="idx-pillar-link">Voir les randos →</a>
        </div>
      </div>

      <!-- KTC -->
      <div class="idx-pillar-card">
        <div class="idx-pillar-head">
          <div class="idx-pillar-icon" style="background:rgba(60,30,10,.08)">🥐</div>
          <div>
            <div class="idx-pillar-label">Kéto Kolé Tché</div>
            <div class="idx-pillar-sub">Objet mystère · Culture vendéenne</div>
          </div>
        </div>
        <div class="idx-pillar-body">
          Un objet. Des indices. Une histoire enfouie dans la mémoire vendéenne. Chaque semaine, un nouveau mystère à élucider ensemble.
          <a href="ktc.php" class="idx-pillar-link">Jouer →</a>
        </div>
      </div>

      <!-- VICTOR -->
      <div class="idx-pillar-card" style="border-color:rgba(212,175,55,.35)">
        <div class="idx-pillar-head">
          <div class="idx-pillar-icon" style="background:rgba(212,175,55,.12)">📖</div>
          <div>
            <div class="idx-pillar-label">
              VICTOR
              <span class="idx-pillar-tag idx-pillar-tag-member">Membres</span>
            </div>
            <div class="idx-pillar-sub">Le livre de la Zone</div>
          </div>
        </div>
        <div class="idx-pillar-body">
          VICTOR est le livre offert à tous les membres de Zone85. Une œuvre vendéenne, en téléchargement libre, réservée à la communauté.
          <a href="inscription.php" class="idx-pillar-link" style="color:#b8860b">Rejoindre pour recevoir VICTOR →</a>
        </div>
      </div>

      <!-- Les Invisibles -->
      <div class="idx-pillar-card" style="border-color:rgba(123,71,178,.3)">
        <div class="idx-pillar-head">
          <div class="idx-pillar-icon" style="background:rgba(123,71,178,.1)">🎭</div>
          <div>
            <div class="idx-pillar-label">
              Les Invisibles
              <span class="idx-pillar-tag idx-pillar-tag-soon">Bientôt</span>
            </div>
            <div class="idx-pillar-sub">Jeu immersif vendéen</div>
          </div>
        </div>
        <div class="idx-pillar-body">
          Un jeu grandeur nature ancré dans l'histoire secrète de la Vendée. Les Invisibles arrivent. Inscris-toi pour être parmi les premiers à jouer.
          <a href="concept.php#les-invisibles" class="idx-pillar-link" style="color:#9b6de0">En savoir plus →</a>
        </div>
      </div>

      <!-- Missions -->
      <div class="idx-pillar-card">
        <div class="idx-pillar-head">
          <div class="idx-pillar-icon" style="background:rgba(234,86,73,.08)">🎯</div>
          <div>
            <div class="idx-pillar-label">Les Missions</div>
            <div class="idx-pillar-sub">Quiz · Photos · Défis collectifs</div>
          </div>
        </div>
        <div class="idx-pillar-body">
          Quiz sur la Vendée, photos de terrain, défis collectifs saisonniers, événements flash. Chaque mission validée rapporte des XP pour toi et ton clan.
          <a href="missions.php" class="idx-pillar-link">Voir les missions →</a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- BEAT 3 : VICTOR — L'INCENTIVE D'INSCRIPTION ─────────── -->
<section class="page-section">
  <div class="container">
    <div class="idx-victor">
      <div class="idx-victor-content">
        <span class="idx-victor-tag">📖 Offert à l'inscription</span>
        <h2 class="idx-victor-title">VICTOR</h2>
        <p class="idx-victor-desc">
          VICTOR est l'œuvre littéraire de Zone85 — une plongée dans l'âme secrète de la Vendée. Réservée aux membres, elle est offerte gratuitement à chaque inscription. Pas de boutique. Pas d'achat. Juste la Zone.
        </p>
        <a href="inscription.php" class="idx-victor-cta">Rejoindre et recevoir VICTOR →</a>
      </div>
      <div class="idx-victor-visual">📖</div>
    </div>
  </div>
</section>


<!-- BEAT 4 : LES INVISIBLES ──────────────────────────────── -->
<section class="page-section-white">
  <div class="container">
    <div class="idx-invisibles">
      <span class="idx-invisibles-icon">🎭</span>
      <div class="idx-invisibles-body">
        <span class="idx-invisibles-tag">Jeu immersif · En développement</span>
        <div class="idx-invisibles-title">Les Invisibles</div>
        <p class="idx-invisibles-desc">
          Ils étaient là avant. Ils reviendront. Un jeu grandeur nature tissé dans l'histoire réelle de la Vendée — missions secrètes, lieux cachés, personnages enfouis. Rejoins la Zone pour être prévenu en premier.
        </p>
      </div>
      <span class="idx-invisibles-badge">Bientôt</span>
    </div>
  </div>
</section>


<!-- BEAT 5 : LE TERRAIN DE JEU ─────────────────────────────── -->
<section class="page-section-navy">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start">

      <!-- Explication du jeu -->
      <div>
        <div class="page-section-eyebrow">Le jeu</div>
        <h2 class="page-section-title" style="color:#fff;margin-bottom:14px">L'environnement qui tisse tout</h2>
        <p style="font-size:.9rem;color:rgba(255,255,255,.5);line-height:1.7;margin-bottom:28px">
          Zone85 n'est pas juste un site — c'est un terrain de jeu. Les contenus, les randos, les défis sont reliés par une mécanique communautaire : clans, XP, saisons. Tu joues en vivant la Vendée.
        </p>
        <div class="idx-game-steps">
          <div class="idx-game-step">
            <div class="idx-game-step-num">Étape 01</div>
            <span class="idx-game-step-icon">⚔️</span>
            <div class="idx-game-step-title">Choisis ton clan</div>
            <div class="idx-game-step-desc">Bocage, Littoral ou Marais. L'appartenance est pour toujours.</div>
          </div>
          <div class="idx-game-step">
            <div class="idx-game-step-num">Étape 02</div>
            <span class="idx-game-step-icon">🎯</span>
            <div class="idx-game-step-title">Pars en mission</div>
            <div class="idx-game-step-desc">Quiz, randos, photos, défis collectifs. Chaque action = XP pour toi et points pour ton clan.</div>
          </div>
          <div class="idx-game-step">
            <div class="idx-game-step-num">Étape 03</div>
            <span class="idx-game-step-icon">📊</span>
            <div class="idx-game-step-title">Laisse une trace</div>
            <div class="idx-game-step-desc">XP personnel permanent. Points de saison. Trophée final pour le clan victorieux.</div>
          </div>
        </div>
      </div>

      <!-- Course des clans en live -->
      <div>
        <div class="page-section-eyebrow">Score de saison</div>
        <div class="idx-clan-race" style="margin-bottom:16px">
          <?php
          $max_race = max(1, array_reduce($clans_sorted_arr, fn($c, $cl) => max($c, $cl['season_score']), 0));
          foreach ($clans_sorted_arr as $_ri => $_rc):
              $bar_w = $max_race > 0 ? round(($_rc['season_score'] / $max_race) * 90) : 0;
              $_rc_slug = $_rc['slug'] ?? '';
          ?>
          <div class="idx-clan-race-row">
            <div style="display:flex;align-items:center;gap:7px">
              <span style="font-size:1rem"><?= ['🥇','🥈','🥉'][$_ri] ?? '' ?></span>
              <div>
                <div style="font-size:.84rem;font-weight:700;color:#fff"><?= e($_rc['name'] ?? ucfirst($_rc_slug)) ?></div>
                <div style="font-size:.62rem;color:rgba(255,255,255,.35)"><?= (int)$_rc['members_count'] ?> membres</div>
              </div>
            </div>
            <div class="idx-race-bar"><div class="idx-race-fill" style="width:<?= $bar_w ?>%"></div></div>
            <div style="text-align:right;font-size:.84rem;font-weight:800;color:#fff">
              <?= number_format((int)$_rc['season_score'], 0, ',', '&#8201;') ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <a href="clans.php" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7);border-radius:7px;padding:9px 16px;font-size:.82rem;font-weight:700;text-decoration:none">
            Les 3 clans →
          </a>
          <a href="communaute.php?tab=classement" style="display:inline-flex;align-items:center;gap:6px;color:rgba(255,255,255,.4);font-size:.8rem;font-weight:700;text-decoration:none;padding:9px 0">
            🏆 Classement complet →
          </a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- BEAT 6 : CTA FINAL ─────────────────────────────────────── -->
<section class="page-cta-bloc">
  <div class="container">
    <?php if (!empty($active_season['title'])): ?>
    <div class="idx-eyebrow" style="margin-bottom:18px">
      <span class="idx-eyebrow-dot"></span>
      Saison en cours : <?= e($active_season['title']) ?>
    </div>
    <?php endif; ?>
    <h2 class="page-cta-title">Rejoins Zone85.</h2>
    <p class="page-cta-sub">Gratuit. Vendéen. Pour toujours.<br>Et tu reçois <strong style="color:#d4af37">VICTOR</strong> dès l'inscription.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a href="inscription.php" class="idx-btn-primary" style="font-size:1.05rem;padding:16px 36px">Créer mon compte →</a>
      <a href="concept.php"     class="idx-btn-secondary" style="font-size:1rem;padding:16px 28px">Le concept</a>
    </div>
  </div>
</section>


<?php else: ?>
<!-- ================================================================
     MEMBRE CONNECTÉ — Dashboard personnalisé
     Zone 1 : Greeting  |  Zone 2 : Missions  |  Zone 3 : Course  |  Zone 4 : Fil
================================================================ -->

<?php
// Données utilisateur pour le dashboard
$_me_xp    = (int)($_nav_me['xp_total'] ?? 0);
$_me_level = (int)($_nav_me['level']    ?? 1);
$_me_clan  = $_nav_me['clan']           ?? null;
$_me_pseudo= $_nav_me['pseudo']         ?? 'Zonaute';
$_clan_data= $_me_clan ? ($clans[$_me_clan] ?? null) : null;

// XP progress vers prochain niveau
function _idx_xp_to_level(int $lvl): int {
    return (int)(100 * ($lvl ** 1.55));
}
$_xp_prev = _idx_xp_to_level($_me_level);
$_xp_next = _idx_xp_to_level($_me_level + 1);
$_xp_pct  = $_xp_next > $_xp_prev
    ? min(100, round(($_me_xp - $_xp_prev) / ($_xp_next - $_xp_prev) * 100))
    : 100;

// Avatar
$_avatar_url = function_exists('avatar_url') ? avatar_url($_nav_me) : '';
?>

<!-- ZONE 1 : GREETING ──────────────────────────────────────── -->
<section class="idx-dash-hero">
  <div class="container">
    <div class="idx-dash-greeting">

      <!-- Avatar -->
      <div class="idx-dash-avatar"<?= ($_nav_me['avatar_type'] ?? '') === 'upload' ? ' style="padding:0"' : '' ?>>
        <?php if (!empty($_avatar_url)): ?>
          <img src="<?= e($_avatar_url) ?>" alt="<?= e($_me_pseudo) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= e($_nav_me['avatar_key'] ?? strtoupper(mb_substr($_me_pseudo, 0, 2))) ?>
        <?php endif; ?>
      </div>

      <div style="flex:1;min-width:0">
        <div class="idx-dash-name">Bienvenue, <?= e($_me_pseudo) ?> 👋</div>
        <div class="idx-dash-meta">
          <span class="idx-dash-badge">⚡ Niveau <?= $_me_level ?></span>
          <span class="idx-dash-badge"><?= number_format($_me_xp, 0, ',', '&#8201;') ?> XP</span>
          <?php if ($_clan_data): ?>
          <span class="idx-dash-badge">⚔️ <?= e($_clan_data['name'] ?? ucfirst($_me_clan)) ?></span>
          <?php endif; ?>
        </div>
        <div class="idx-xp-row">
          <div class="idx-xp-label">
            <span>XP vers Niv. <?= $_me_level + 1 ?></span>
            <span><?= $_xp_pct ?>%</span>
          </div>
          <div class="idx-xp-track">
            <div class="idx-xp-fill" style="width:<?= $_xp_pct ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Actions rapides -->
      <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
        <a href="profil.php"     class="idx-btn-primary" style="font-size:.84rem;padding:9px 18px">Mon Profil →</a>
        <a href="communaute.php?tab=classement" class="idx-btn-secondary" style="font-size:.84rem;padding:9px 18px">🏆 Classement</a>
      </div>

    </div>
  </div>
</section>


<!-- ZONE 2 : MISSIONS ACTIVES ──────────────────────────────── -->
<section class="page-section">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:12px">
      <div>
        <div class="page-section-eyebrow">Missions</div>
        <h2 class="page-section-title" style="margin-bottom:0">Défis disponibles</h2>
      </div>
      <?php if (!empty($flash_mission)): ?>
      <a href="missions.php?type=flash" style="text-decoration:none">
        <span class="idx-flash-badge"><span class="idx-flash-dot"></span> FLASH — <?= e(mb_substr($flash_mission['title'] ?? '', 0, 28)) ?></span>
      </a>
      <?php endif; ?>
      <a href="missions.php" style="font-size:.84rem;font-weight:700;color:var(--primary);text-decoration:none">Toutes les missions →</a>
    </div>

    <?php if (!empty($dash_missions)): ?>
    <div class="idx-mission-grid">
      <?php foreach ($dash_missions as $_dm):
          $_dt   = $_dm['mission_type'] ?? 'default';
          $_dico = $_mtype_icons[$_dt] ?? $_mtype_icons['default'];
      ?>
      <a href="mission.php?id=<?= (int)$_dm['id'] ?>" class="idx-mission-card">
        <span class="idx-mission-icon"><?= $_dico ?></span>
        <div class="idx-mission-title"><?= e($_dm['title'] ?? 'Mission') ?></div>
        <div class="idx-mission-footer">
          <span class="idx-mission-xp">+<?= (int)($_dm['xp_success'] ?? $_dm['xp_reward'] ?? 0) ?> XP</span>
          <span class="idx-mission-badge">Actif</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="page-empty">
      <div class="page-empty-icon">🎯</div>
      <div class="page-empty-text">Prochaines missions bientôt — surveille la Zone !</div>
    </div>
    <?php endif; ?>
  </div>
</section>


<!-- ZONE 3 : COURSE DES CLANS ──────────────────────────────── -->
<section class="page-section-navy">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start">

      <!-- Saison en cours -->
      <div>
        <div class="page-section-eyebrow">Saison en cours</div>
        <?php if (!empty($active_season['title'])): ?>
        <h2 class="page-section-title" style="margin-bottom:12px;color:#fff"><?= e($active_season['title']) ?></h2>
        <?php if (!empty($active_season['main_mission'])): ?>
        <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-left:3px solid #ea5649;border-radius:0 8px 8px 0;padding:14px 18px;margin-bottom:20px">
          <div style="font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:4px">Mission principale</div>
          <div style="font-size:.92rem;font-weight:700;color:#fff"><?= e($active_season['main_mission']) ?></div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <h2 class="page-section-title" style="color:#fff;margin-bottom:12px">Saison en cours</h2>
        <?php endif; ?>
        <a href="missions.php" style="display:inline-flex;align-items:center;gap:7px;color:rgba(255,255,255,.55);font-size:.88rem;font-weight:600;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.15);padding-bottom:2px">
          Voir toutes les missions →
        </a>
      </div>

      <!-- Course des clans -->
      <div>
        <div class="page-section-eyebrow">Score de saison</div>
        <div class="idx-clan-race">
          <?php
          $max_race = max(1, array_reduce($clans_sorted_arr, fn($c, $cl) => max($c, $cl['season_score']), 0));
          foreach ($clans_sorted_arr as $_ri => $_rc):
              $bar_w = $max_race > 0 ? round(($_rc['season_score'] / $max_race) * 90) : 0;
              $_rc_slug = $_rc['slug'] ?? '';
          ?>
          <div class="idx-clan-race-row">
            <div style="display:flex;align-items:center;gap:7px">
              <span style="font-size:1rem"><?= ['🥇','🥈','🥉'][$_ri] ?? '' ?></span>
              <div>
                <div style="font-size:.84rem;font-weight:700;color:#fff"><?= e($_rc['name'] ?? ucfirst($_rc_slug)) ?></div>
                <div style="font-size:.62rem;color:rgba(255,255,255,.35)"><?= (int)$_rc['members_count'] ?> membres</div>
              </div>
            </div>
            <div class="idx-race-bar"><div class="idx-race-fill" style="width:<?= $bar_w ?>%"></div></div>
            <div style="text-align:right;font-size:.84rem;font-weight:800;color:#fff">
              <?= number_format((int)$_rc['season_score'], 0, ',', '&#8201;') ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="communaute.php?tab=classement" style="display:inline-flex;align-items:center;gap:6px;margin-top:14px;color:rgba(255,255,255,.5);font-size:.8rem;font-weight:700;text-decoration:none">
          🏆 Classement complet des Zonautes →
        </a>
      </div>

    </div>
  </div>
</section>


<!-- ZONE 4 : FIL DE LA ZONE ────────────────────────────────── -->
<?php if (!empty($dash_feed)): ?>
<section class="page-section-white">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px">
      <div>
        <div class="page-section-eyebrow">Communauté</div>
        <h2 class="page-section-title" style="margin-bottom:0">Fil de la Zone</h2>
      </div>
      <a href="communaute.php" style="font-size:.84rem;font-weight:700;color:var(--primary);text-decoration:none">Tout voir →</a>
    </div>
    <?php
    $feed_icons = ['mission_new'=>'🎯','mission_complete'=>'✅','badge_unlock'=>'🏅',
                   'flash_start'=>'⚡','collectible_found'=>'🔍','rando_done'=>'🥾','ktc_win'=>'🥐'];
    foreach ($dash_feed as $_fi):
        $_fi_ico = $_fi['icon_emoji'] ?: ($feed_icons[$_fi['event_type']] ?? '📋');
    ?>
    <div class="idx-feed-item">
      <span class="idx-feed-icon"><?= e($_fi_ico) ?></span>
      <div style="flex:1;min-width:0">
        <div class="idx-feed-text"><?= e(mb_substr($_fi['title'], 0, 80)) ?></div>
        <div class="idx-feed-meta"><?= $_fi['pseudo'] ? e($_fi['pseudo']) . ' · ' : '' ?><?= function_exists('format_date') ? format_date($_fi['created_at'], 'short') : substr($_fi['created_at'], 0, 10) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php endif; // fin split guest/member ?>

<?php
if (function_exists('render_hidden_collectibles')) render_hidden_collectibles('index');
require_once 'includes/footer.php';
?>
