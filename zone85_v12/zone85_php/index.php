<?php
$current_page = 'index';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';

// ── Métadonnées depuis la DB (Sprint 3) ──────────────────────
$_idx_defaults = [
    'title'          => 'Zone85 — La Vendée vécue, racontée et jouée',
    'meta_desc'      => 'Les Échos de terrain, les Randos vendéennes, VICTOR le livre, Les Invisibles — dans un jeu communautaire pour la Vendée. Rejoins les Zonautes.',
    'og_title'       => 'Zone85 — La Vendée vécue, racontée et jouée',
    'og_desc'        => 'Les Échos, les Randos, VICTOR le livre, Les Invisibles — et un jeu communautaire qui tisse le tout. Rejoins la Zone.',
    'og_image'       => 'assets/img/ZONE852025.png',
    'canonical'      => 'https://www.zone85.fr/',
    'robots'         => 'index,follow',
];
if (db_enabled()) {
    try {
        $_pdo_idx = db();
        $_pdo_idx->exec("CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL DEFAULT '', meta_title VARCHAR(255) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL, hero_title VARCHAR(255) DEFAULT NULL,
            hero_subtitle TEXT DEFAULT NULL, hero_image VARCHAR(512) DEFAULT NULL,
            content_blocks JSON DEFAULT NULL, status ENUM('published','draft') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug(slug), INDEX idx_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed default entry if missing
        $_pdo_idx->prepare("INSERT IGNORE INTO pages (slug, title, meta_title, meta_description, hero_image, status)
            VALUES ('index', :t, :mt, :md, :img, 'published')")
            ->execute([':t'=>$_idx_defaults['title'],':mt'=>$_idx_defaults['og_title'],
                       ':md'=>$_idx_defaults['meta_desc'],':img'=>$_idx_defaults['og_image']]);

        $_meta_row = $_pdo_idx->prepare("SELECT * FROM pages WHERE slug='index' LIMIT 1");
        $_meta_row->execute();
        $_meta_row = $_meta_row->fetch();
        if ($_meta_row) {
            if (!empty($_meta_row['meta_title']))       $_idx_defaults['title']    = $_meta_row['meta_title'];
            if (!empty($_meta_row['meta_description'])) $_idx_defaults['meta_desc']= $_meta_row['meta_description'];
            if (!empty($_meta_row['meta_title']))       $_idx_defaults['og_title'] = $_meta_row['meta_title'];
            if (!empty($_meta_row['meta_description'])) $_idx_defaults['og_desc']  = $_meta_row['meta_description'];
            if (!empty($_meta_row['hero_image']))       $_idx_defaults['og_image'] = $_meta_row['hero_image'];
        }
    } catch (PDOException $e) {}
}

$page_title          = $_idx_defaults['title'];
$page_description    = $_idx_defaults['meta_desc'];
$page_canonical      = $_idx_defaults['canonical'];
$page_robots         = $_idx_defaults['robots'];
$page_og_title       = $_idx_defaults['og_title'];
$page_og_description = $_idx_defaults['og_desc'];
$page_og_image       = $_idx_defaults['og_image'];
$page_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'ZONE85',
    'url'      => 'https://www.zone85.fr',
    'description' => $page_description,
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => 'https://www.zone85.fr/missions.php?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];

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
.idx-tagline{font-size:clamp(.9rem,2vw,1.04rem);color:rgba(255,255,255,.5);text-align:center;margin-bottom:40px;line-height:1.7;max-width:560px}
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

/* VICTOR block */
.idx-victor{background:linear-gradient(135deg,#0c1e2e,#12314e);border-radius:16px;overflow:hidden;display:grid;grid-template-columns:1fr auto;border:1px solid rgba(255,255,255,.08)}
.idx-victor-content{padding:40px 36px}
.idx-victor-tag{font-size:.62rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:#f5a99f;margin-bottom:14px;display:block}
.idx-victor-title{font-size:1.8rem;font-weight:900;color:#fff;margin-bottom:12px;line-height:1.2}
.idx-victor-desc{font-size:.9rem;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:24px}
.idx-victor-cta{display:inline-flex;align-items:center;gap:8px;background:#ea5649;color:#fff;padding:12px 24px;border-radius:7px;font-weight:800;font-size:.9rem;text-decoration:none;transition:opacity .2s}
.idx-victor-cta:hover{opacity:.88}
.idx-victor-visual{background:rgba(234,86,73,.06);display:flex;align-items:center;justify-content:center;padding:36px 40px;border-left:1px solid rgba(234,86,73,.12);font-size:7rem;line-height:1}

/* Clan race */
.idx-clan-race{background:rgba(255,255,255,.04);border-radius:12px;padding:20px;border:1px solid rgba(255,255,255,.08)}
.idx-clan-race-row{display:grid;grid-template-columns:120px 1fr 64px;align-items:center;gap:10px}
.idx-clan-race-row+.idx-clan-race-row{margin-top:14px}
.idx-race-bar{background:rgba(255,255,255,.07);border-radius:4px;height:9px;overflow:hidden}
.idx-race-fill{height:100%;border-radius:4px;background:#ea5649;transition:width .8s cubic-bezier(.22,1,.36,1)}

/* Iceberg comparison */
.idx-iceberg-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px}
.idx-iceberg-col{border-radius:12px;padding:28px 24px}
.idx-iceberg-col-social{background:#f4f4f0;border:1.5px solid #e0ddd5}
.idx-iceberg-col-zone{background:#fff;border:2px solid var(--primary)}
.idx-iceberg-col-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.idx-iceberg-col-social .idx-iceberg-col-title{color:var(--text-muted)}
.idx-iceberg-col-zone .idx-iceberg-col-title{color:var(--primary)}
.idx-iceberg-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px}
.idx-iceberg-list li{font-size:.86rem;line-height:1.5;padding-left:20px;position:relative}
.idx-iceberg-list li::before{content:"—";position:absolute;left:0;color:currentColor;opacity:.4}
.idx-iceberg-col-social .idx-iceberg-list{color:var(--text-muted)}
.idx-iceberg-col-zone .idx-iceberg-list{color:var(--text)}
.idx-iceberg-col-zone .idx-iceberg-list li::before{content:"✓";color:var(--primary);opacity:1;font-weight:900}

/* Vraiment grid */
.idx-vraiment-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.idx-vraiment-card{background:#fff;border-radius:12px;padding:24px 22px;border:1.5px solid var(--beige-dark)}
.idx-vraiment-icon{font-size:1.8rem;margin-bottom:12px;display:block;line-height:1}
.idx-vraiment-title{font-size:1rem;font-weight:800;color:var(--navy-dark);margin-bottom:8px}
.idx-vraiment-desc{font-size:.84rem;color:var(--text-muted);line-height:1.65;margin-bottom:12px}
.idx-vraiment-link{font-size:.78rem;font-weight:700;color:var(--primary);text-decoration:none}

/* Simple / PWA */
.idx-simple-steps{display:flex;gap:0;margin-top:28px}
.idx-simple-step{flex:1;display:flex;align-items:flex-start;gap:12px;padding:0 20px 0 0}
.idx-simple-step+.idx-simple-step{border-left:1px solid rgba(255,255,255,.08);padding-left:20px}
.idx-simple-step-num{width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;font-size:.82rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.idx-simple-step-title{font-size:.88rem;font-weight:700;color:#fff;line-height:1.3}
.idx-simple-step-desc{font-size:.76rem;color:rgba(255,255,255,.45);margin-top:3px}
.idx-gafa-note{margin-top:28px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:14px 18px;font-size:.82rem;color:rgba(255,255,255,.4);line-height:1.6;font-style:italic}
.idx-gafa-note strong{color:rgba(255,255,255,.65);font-style:normal}

/* Connaisseurs */
.idx-connaisseurs-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start}

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

/* Barre membre */
.idx-member-bar{background:#fff;border-bottom:2px solid rgba(234,86,73,.2);padding:9px 0}
.idx-member-bar-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.idx-member-bar-avatar{width:30px;height:30px;border-radius:7px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:#fff;overflow:hidden;flex-shrink:0}
.idx-member-bar-name{font-size:.84rem;font-weight:700;color:#0c1e2e}
.idx-member-bar-stats{font-size:.74rem;color:#6b7f96;font-weight:600}
.idx-member-bar a{font-size:.78rem;font-weight:700;color:var(--primary);text-decoration:none;white-space:nowrap}
.idx-member-bar a:hover{text-decoration:underline}

/* Responsive */
@media(max-width:900px){
  .idx-hero{padding:96px 20px 0}
  .idx-mascots{gap:14px}
  .idx-masc-wrap img{height:130px!important}
  .idx-masc-wrap.center img{height:165px!important}
  .idx-mission-grid{grid-template-columns:1fr}
  .idx-victor{grid-template-columns:1fr}
  .idx-victor-visual{display:none}
  .idx-dash-hero{padding:90px 0 40px}
  .idx-iceberg-grid,.idx-vraiment-grid,.idx-connaisseurs-grid{grid-template-columns:1fr}
  .idx-simple-steps{flex-direction:column;gap:16px}
  .idx-simple-step+.idx-simple-step{border-left:none;padding-left:0;border-top:1px solid rgba(255,255,255,.08);padding-top:16px}
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
$_nav_me   = isset($_nav_user) ? $_nav_user : $_me;
$_is_guest = empty($_nav_me);

// Données membre (disponibles pour toute la page)
$_me_pseudo  = $_nav_me['pseudo']   ?? 'Zonaute';
$_me_xp      = (int)($_nav_me['xp_total'] ?? 0);
$_me_level   = (int)($_nav_me['level']    ?? 1);
$_me_clan    = $_nav_me['clan']     ?? null;
$_clan_data  = $_me_clan ? ($clans[$_me_clan] ?? null) : null;
$_avatar_url = !$_is_guest && function_exists('avatar_url') ? avatar_url($_nav_me) : '';
if (!function_exists('_idx_xp_to_level')) {
    function _idx_xp_to_level(int $lvl): int { return (int)(100 * ($lvl ** 1.55)); }
}
$_xp_prev = _idx_xp_to_level($_me_level);
$_xp_next = _idx_xp_to_level($_me_level + 1);
$_xp_pct  = $_xp_next > $_xp_prev
    ? min(100, round(($_me_xp - $_xp_prev) / ($_xp_next - $_xp_prev) * 100))
    : 100;
?>

<?php if (!$_is_guest): ?>
<!-- Barre de bienvenue membre ─────────────────────────────── -->
<div class="idx-member-bar">
  <div class="container idx-member-bar-inner">
    <div style="display:flex;align-items:center;gap:10px">
      <div class="idx-member-bar-avatar"<?= ($_nav_me['avatar_type'] ?? '') === 'upload' ? ' style="padding:0"' : '' ?>>
        <?php if (!empty($_avatar_url)): ?>
          <img src="<?= e($_avatar_url) ?>" alt="<?= e($_me_pseudo) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= e($_nav_me['avatar_key'] ?? strtoupper(mb_substr($_me_pseudo, 0, 2))) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="idx-member-bar-name">Bienvenue, <?= e($_me_pseudo) ?> 👋</div>
        <div class="idx-member-bar-stats">⚡ Niv.&nbsp;<?= $_me_level ?> &nbsp;·&nbsp; <?= number_format($_me_xp, 0, ',', '&#8201;') ?>&nbsp;XP</div>
      </div>
    </div>
    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <a href="missions.php">🎯 Mes missions</a>
      <a href="profil.php">Mon profil →</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- BEAT 1 : HERO ─────────────────────────────────────────── -->
<section class="idx-hero">
  <div class="idx-hero-content">

    <div class="idx-eyebrow">
      <span class="idx-eyebrow-dot"></span>
      <?= !empty($active_season['title']) ? e($active_season['title']) : 'Zone85 · L\'Esprit Vendée' ?>
    </div>

    <h1 class="idx-h1">La <em>Vendée</em>.<br>Pas en surface.</h1>

    <p class="idx-tagline">
      Les réseaux sociaux vous montrent la Vendée. Zone85 vous la fait vivre — les chemins, les histoires, les objets, les gens.
    </p>

    <div class="idx-ctas">
      <?php if ($_is_guest): ?>
        <a href="inscription.php" class="idx-btn-primary">Rejoindre la Zone →</a>
        <a href="concept.php"     class="idx-btn-secondary">Comprendre le concept</a>
      <?php else: ?>
        <a href="missions.php" class="idx-btn-primary">🎯 Voir mes missions →</a>
        <a href="les-echos.php" class="idx-btn-secondary">📰 Les Échos</a>
      <?php endif; ?>
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


<!-- BEAT 2 : L'ICEBERG ─────────────────────────────────────── -->
<section class="page-section-white" style="border-top:3px solid var(--primary)">
  <div class="container">
    <div class="page-section-head">
      <div class="page-section-eyebrow">La différence</div>
      <h2 class="page-section-title">La pointe de l'iceberg. Et ce qu'il y a dessous.</h2>
      <p class="page-section-sub">Facebook et Instagram, c'est formidable — et on adore ça. Mais c'est la surface. Zone85, c'est la profondeur : les chemins qu'on marche vraiment, les histoires qu'on lit vraiment, les objets qu'on identifie, les gens qu'on rencontre.</p>
    </div>

    <div class="idx-iceberg-grid">

      <!-- Colonne réseaux sociaux -->
      <div class="idx-iceberg-col idx-iceberg-col-social">
        <div class="idx-iceberg-col-title">
          <span>📱</span>
          Sur les réseaux
        </div>
        <ul class="idx-iceberg-list">
          <li>Vous likez une photo de rando vendéenne</li>
          <li>Vous partagez un article sur un château</li>
          <li>Vous découvrez un plat typique en scrollant</li>
          <li>Vous passez à autre chose en 30 secondes</li>
        </ul>
      </div>

      <!-- Colonne Zone85 -->
      <div class="idx-iceberg-col idx-iceberg-col-zone">
        <div class="idx-iceberg-col-title">
          <span>🎯</span>
          Sur Zone85
        </div>
        <ul class="idx-iceberg-list">
          <li>Vous marchez ce chemin — et gagnez des XP</li>
          <li>Vous lisez l'histoire vraie dans Les Échos</li>
          <li>Vous identifiez l'objet dans le KTC</li>
          <li>Vous contribuez à votre clan, vous laissez une trace</li>
        </ul>
      </div>

    </div>
  </div>
</section>


<!-- BEAT 3 : VRAIMENT ──────────────────────────────────────── -->
<section class="page-section" style="background:var(--beige)">
  <div class="container">
    <div class="page-section-head">
      <div class="page-section-eyebrow">Ce qu'on fait</div>
      <h2 class="page-section-title">Rentrer vraiment dans la Vendée</h2>
      <p class="page-section-sub">Pas du contenu à consommer. Des expériences à vivre.</p>
    </div>

    <div class="idx-vraiment-grid">

      <div class="idx-vraiment-card">
        <span class="idx-vraiment-icon">🥾</span>
        <div class="idx-vraiment-title">Marcher vraiment</div>
        <p class="idx-vraiment-desc">Les randos Zone85 sur les chemins du bocage, du littoral et du marais. Vous marchez, vous validez, vous progressez.</p>
        <a href="randos.php" class="idx-vraiment-link">Voir les randos →</a>
      </div>

      <div class="idx-vraiment-card">
        <span class="idx-vraiment-icon">📰</span>
        <div class="idx-vraiment-title">Lire vraiment</div>
        <p class="idx-vraiment-desc">Les Échos : des histoires vraies écrites par ceux qui habitent le territoire vendéen. Courts, humains, locaux.</p>
        <a href="les-echos.php" class="idx-vraiment-link">Lire les Échos →</a>
      </div>

      <div class="idx-vraiment-card">
        <span class="idx-vraiment-icon">🔍</span>
        <div class="idx-vraiment-title">Découvrir vraiment</div>
        <p class="idx-vraiment-desc">Le KTC — Kéto Kolé Tché : un objet mystère par semaine. Une histoire cachée dans la mémoire vendéenne.</p>
        <a href="ktc.php" class="idx-vraiment-link">Jouer →</a>
      </div>

      <div class="idx-vraiment-card">
        <span class="idx-vraiment-icon">⚔️</span>
        <div class="idx-vraiment-title">Contribuer vraiment</div>
        <p class="idx-vraiment-desc">Chaque participation fait avancer votre score et celui de votre clan. Vous ne consommez pas — vous construisez.</p>
        <a href="missions.php" class="idx-vraiment-link">Voir les missions →</a>
      </div>

    </div>
  </div>
</section>


<!-- BEAT 4 : SIMPLE / PWA ──────────────────────────────────── -->
<section class="page-section-navy">
  <div class="container">
    <div class="page-section-head">
      <div class="page-section-eyebrow">Aussi simple que</div>
      <h2 class="page-section-title" style="color:#fff">Une icône sur votre téléphone.</h2>
      <p class="page-section-sub" style="color:rgba(255,255,255,.5)">On entend souvent "oui mais c'est chiant de taper une adresse dans la barre..." — non. À l'inscription, vous pouvez installer l'icône Zone85 sur votre téléphone, juste à côté de Facebook ou d'Instagram. Un simple clic. Et si vous voulez, des notifications comme sur n'importe quelle app.</p>
    </div>

    <div class="idx-simple-steps">

      <div class="idx-simple-step">
        <div class="idx-simple-step-num">1</div>
        <div>
          <div class="idx-simple-step-title">Vous vous inscrivez</div>
          <div class="idx-simple-step-desc">30 secondes</div>
        </div>
      </div>

      <div class="idx-simple-step">
        <div class="idx-simple-step-num">2</div>
        <div>
          <div class="idx-simple-step-title">Vous installez l'icône sur votre écran</div>
          <div class="idx-simple-step-desc">Depuis le navigateur, en un geste</div>
        </div>
      </div>

      <div class="idx-simple-step">
        <div class="idx-simple-step-num">3</div>
        <div>
          <div class="idx-simple-step-title">Vous accédez en un clic, comme Facebook</div>
          <div class="idx-simple-step-desc">Plus besoin de taper l'adresse</div>
        </div>
      </div>

    </div>

    <div class="idx-gafa-note">
      <strong>Et si un jour les GAFAs décident de bannir la Vendée des réseaux sociaux</strong> — on aura toujours notre chez nous. 😏
    </div>
  </div>
</section>


<!-- BEAT 5 : VICTOR ────────────────────────────────────────── -->
<section class="page-section" style="background:#fff">
  <div class="container">
    <div class="idx-victor">
      <div class="idx-victor-content">
        <span class="idx-victor-tag">📖 Réservé aux membres · PDF inclus</span>
        <h2 class="idx-victor-title">VICTOR</h2>
        <p class="idx-victor-desc">
          VICTOR est l'œuvre littéraire de Zone85 — une plongée dans l'âme secrète de la Vendée. Réservé aux membres, le PDF est disponible en téléchargement dès ton inscription. Pas de boutique. Pas d'achat. Juste la Zone.
        </p>
        <a href="victor.php" class="idx-victor-cta">
          <?= $_is_guest ? 'Rejoindre et accéder à VICTOR →' : '📖 Accéder à VICTOR →' ?>
        </a>
      </div>
      <div class="idx-victor-visual">📖</div>
    </div>
  </div>
</section>







<?php
if (function_exists('render_hidden_collectibles')) render_hidden_collectibles('index');
require_once 'includes/footer.php';
?>
