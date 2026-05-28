<?php
$page_title       = 'Accueil';
$page_description = 'Rejoins un clan vendéen, gagne des XP, participe à des missions et fais entrer ton clan dans la légende. ZONE85 — Le terrain de jeu vendéen.';
$page_canonical   = 'https://www.zone85.fr/index.php';
$page_robots      = 'index,follow';
$page_og_title    = 'ZONE85 — Le terrain de jeu vendéen';
$page_og_description = 'Rejoins un clan vendéen, gagne des XP et fais entrer ton clan dans la légende. Bocage, Littoral ou Marais — quel territoire te ressemble ?';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'ZONE85',
    'url' => 'https://www.zone85.fr',
    'description' => "Terrain de jeu communautaire vendéen. Rejoins un clan, gagne des XP, fais vivre la Vendée autrement.",
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => 'https://www.zone85.fr/missions.php?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
$current_page = 'index';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';

// Trier les clans par score décroissant pour la course de saison
$clans_race = $clans;
uasort($clans_race, fn($a, $b) => $b['season_score'] <=> $a['season_score']);
$clan_leader = array_values($clans_race)[0]; // Clan en tête

// Médailles pour le podium
$medals = ['🥇', '🥈', '🥉'];

$page_styles = '<style>
/* ── HERO ── */
#hero{min-height:100vh;background:linear-gradient(160deg,#0d1e2c 0%,#12314e 45%,#1a3a55 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:120px 24px 0;position:relative;overflow:hidden}
#hero::before{content:\'\';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");pointer-events:none}
.hero-content{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;width:100%;animation:fadeUp .8s ease both}
.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(234,86,73,.15);border:1px solid rgba(234,86,73,.3);color:#f5a99f;padding:6px 18px;border-radius:4px;font-size:.78rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin-bottom:28px}
.hero-eyebrow::before{content:\'\';width:6px;height:6px;background:var(--primary);border-radius:50%;animation:blink 1.5s infinite}
.hero-h1{font-size:clamp(2.6rem,6vw,5rem);font-weight:900;color:#fff;text-align:center;line-height:1.1;letter-spacing:-1.5px;margin-bottom:24px}
.hero-h1 em{color:var(--primary);font-style:normal}
.hero-sub{font-size:clamp(1rem,2vw,1.15rem);color:rgba(255,255,255,.65);text-align:center;max-width:560px;margin-bottom:44px;line-height:1.7}
.hero-ctas{display:flex;gap:16px;flex-wrap:wrap;justify-content:center;margin-bottom:80px}
.hero-mascots{width:100%;max-width:900px;display:flex;align-items:flex-end;justify-content:center;gap:40px}
.hero-masc-wrap{display:flex;flex-direction:column;align-items:center;cursor:pointer}
.hero-masc-label{margin-top:10px;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.55);background:rgba(255,255,255,.07);padding:4px 14px;border-radius:20px;border:1px solid rgba(255,255,255,.12)}

/* ── STATS BAR ── */
#stats-bar{background:var(--navy-dark);padding:28px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr)}
.stat-item{text-align:center;padding:0 24px;position:relative}
.stat-item:not(:last-child)::after{content:\'\';position:absolute;right:0;top:50%;transform:translateY(-50%);height:36px;width:1px;background:rgba(255,255,255,.1)}
.stat-num{font-size:1.9rem;font-weight:900;color:#fff;letter-spacing:-1px;line-height:1}
.stat-num span{color:var(--primary)}
.stat-label{font-size:.72rem;font-weight:600;color:var(--text-muted);letter-spacing:.08em;text-transform:uppercase;margin-top:4px}

/* ── MODES ── */
#modes{background:var(--beige);padding:100px 0 80px}
.modes-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:48px}
.mode-card{background:var(--white);border-radius:var(--radius-lg);padding:36px 28px;box-shadow:var(--shadow-sm);border-top:4px solid transparent}
.mode-card.orange{border-top-color:var(--primary)}
.mode-card.navy{border-top-color:var(--navy-dark)}
.mode-icon{font-size:2.4rem;margin-bottom:16px}
.mode-title{font-size:1.3rem;font-weight:800;color:var(--text);margin-bottom:8px}
.mode-desc{font-size:.9rem;color:var(--text-mid);line-height:1.65;margin-bottom:20px}
.mode-points{display:flex;flex-direction:column;gap:8px}
.mode-point{display:flex;align-items:flex-start;gap:10px;font-size:.85rem;color:var(--text-mid)}
.mode-point::before{content:\'✓\';font-weight:900;flex-shrink:0;margin-top:1px}
.mode-card.orange .mode-point::before{color:var(--primary)}
.mode-card.navy .mode-point::before{color:var(--navy-dark)}
.mode-tagline{background:var(--beige-dark);border-radius:var(--radius);padding:20px 28px;text-align:center;font-size:1.15rem;font-weight:800;color:var(--navy-dark);margin-top:28px}
.mode-tagline em{color:var(--primary);font-style:normal}

/* ── CLANS HOME ── */
#clans-home{background:var(--beige-light);padding:100px 0 80px}
.clans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
.clan-card{background:var(--white);border-radius:var(--radius-lg);overflow:visible;box-shadow:var(--shadow-sm);transition:transform .25s,box-shadow .25s}
.clan-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}
.clan-header{border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:32px 24px 0;min-height:180px;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;position:relative;overflow:visible}
.clan-header-bocage{background:linear-gradient(160deg,#0d2018 0%,#1e3d2b 100%)}
.clan-header-littoral{background:linear-gradient(160deg,#0a1a2e 0%,#12314e 100%)}
.clan-header-marais{background:linear-gradient(160deg,#2b1a0a 0%,#4a2e15 100%)}
.clan-masc-wrap{position:relative;z-index:2;margin-bottom:-16px}
.clan-masc-wrap img{height:150px;width:auto;filter:drop-shadow(0 8px 24px rgba(0,0,0,.5));opacity:0}
.clan-masc-wrap img.popped{animation:mascPop .7s ease forwards}
.clan-name-pill{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#fff;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:5px 16px;border-radius:20px;margin-bottom:18px;position:relative;z-index:1}
.clan-body{padding:28px 20px 20px}
.clan-title{font-size:1.2rem;font-weight:800;color:var(--text);margin-bottom:2px}
.clan-mascot-name{font-size:.85rem;font-weight:700;color:var(--primary);margin-bottom:4px}
.clan-cri{font-style:italic;font-size:.82rem;color:var(--text-mid);padding:8px 12px;border-left:3px solid var(--primary);background:var(--beige);border-radius:0 4px 4px 0;margin-bottom:14px}
.clan-stats{display:flex;background:var(--beige);border-radius:var(--radius);overflow:hidden;margin-bottom:16px}
.clan-stat{flex:1;text-align:center;padding:10px 6px;position:relative}
.clan-stat:not(:last-child)::after{content:\'\';position:absolute;right:0;top:50%;transform:translateY(-50%);height:24px;width:1px;background:var(--beige-dark)}
.clan-stat-val{font-size:1rem;font-weight:800;color:var(--text);line-height:1}
.clan-stat-lbl{font-size:.6rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-top:3px;font-weight:600}
.btn-clan{width:100%;display:flex;align-items:center;justify-content:center;gap:6px;background:var(--primary);color:#fff;border:none;padding:12px;border-radius:var(--radius-sm);font-size:.88rem;font-weight:700;cursor:pointer;font-family:\'Inter\',sans-serif;transition:background .2s;text-decoration:none}
.btn-clan:hover{background:var(--primary-dark)}

/* ── SAISON ── */
#saison{background:linear-gradient(160deg,#0c1e2e,#12314e);padding:100px 0}
.saison-bloc{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
.saison-leader{display:flex;align-items:center;gap:12px;padding:12px 18px;background:rgba(255,255,255,.06);border-radius:var(--radius);border:1px solid rgba(255,255,255,.1);margin:16px 0}
.saison-leader-lbl{font-size:.68rem;color:rgba(255,255,255,.45);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px}
.saison-leader-name{font-size:.95rem;font-weight:800;color:#fff}
.saison-countdown{display:flex;gap:12px;margin-top:24px;margin-bottom:28px}
.countdown-box{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius);padding:12px 16px;text-align:center;min-width:70px}
.countdown-num{font-size:1.6rem;font-weight:900;color:#fff;line-height:1}
.countdown-lbl{font-size:.62rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-top:4px;font-weight:600}
.saison-race{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:var(--radius-lg);padding:24px}
.saison-race-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);margin-bottom:18px}

/* ── ACTIONS ── */
#actions{background:var(--beige);padding:100px 0 80px}
.actions-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:48px}
.action-card{background:var(--white);border-radius:var(--radius-lg);padding:24px;box-shadow:var(--shadow-sm);transition:transform .2s,box-shadow .2s}
.action-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
.action-icon{width:48px;height:48px;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:14px}
.action-title{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:6px}
.action-desc{font-size:.85rem;color:var(--text-mid);line-height:1.5;margin-bottom:12px}
.action-xp{font-size:.78rem;font-weight:800;color:var(--primary)}

/* ── HALL APERÇU ── */
#hall-apercu{background:var(--beige-light);padding:80px 0}
.hall-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:48px}
.hall-card{background:var(--white);border-radius:var(--radius-lg);padding:20px 24px;box-shadow:var(--shadow-sm)}
.hall-card-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--text-muted);margin-bottom:14px}
.hall-contrib-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--beige-dark)}
.hall-contrib-item:last-child{border-bottom:none}
.hall-contrib-avatar{width:36px;height:36px;background:var(--beige-dark);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.hall-contrib-name{font-weight:700;color:var(--text);font-size:.88rem}
.hall-contrib-action{font-size:.75rem;color:var(--text-muted)}
.hall-contrib-xp{font-size:.78rem;font-weight:800;color:var(--primary);margin-left:auto;white-space:nowrap}
.badge-showcase{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.badge-item{background:var(--beige);border-radius:var(--radius);padding:12px;text-align:center}
.badge-item-icon{font-size:1.5rem;margin-bottom:4px}
.badge-item-name{font-size:.62rem;font-weight:700;color:var(--text-muted);line-height:1.3}

/* ── CTA FINAL ── */
#cta-final{background:var(--primary);padding:80px 0;position:relative;overflow:hidden}
#cta-final::before{content:\'\';position:absolute;top:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.06)}
#cta-final::after{content:\'\';position:absolute;bottom:-80px;left:20%;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.04)}
.cta-inner{position:relative;z-index:1;text-align:center}
.cta-mascots{display:flex;justify-content:center;align-items:flex-end;gap:20px;margin-bottom:36px}
.cta-title{font-size:clamp(1.8rem,4vw,3rem);font-weight:900;color:#fff;letter-spacing:-1px;margin-bottom:16px}
.cta-sub{font-size:1.05rem;color:rgba(255,255,255,.8);max-width:520px;margin:0 auto 32px;line-height:1.7}
.cta-chips{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-bottom:36px}
.cta-chip{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;font-size:.8rem;font-weight:700;padding:7px 18px;border-radius:20px}

@media(max-width:768px){
  .modes-grid,.clans-grid,.saison-bloc,.actions-grid,.hall-grid{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .hero-mascots{gap:16px}
  .hero-masc-wrap img{height:120px!important}
  .hero-masc-wrap:nth-child(2) img{height:160px!important}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ============ HERO ============ -->
<section id="hero">
  <div class="hero-content">
    <div class="hero-eyebrow">Camp d'Été Zone85 · Saison en cours</div>
    <h1 class="hero-h1">Le terrain de jeu<br><em>vendéen</em> qui te fait revenir</h1>
    <p class="hero-sub">Rejoins ton clan, explore la Vendée, gagne des XP. Fais entrer ton clan dans la légende.</p>
    <div class="hero-ctas">
      <a href="<?= page_url('inscription') ?>" class="btn btn-primary" style="font-size:1rem;padding:14px 32px">Rejoindre la Zone →</a>
      <a href="<?= page_url('concept') ?>" class="btn btn-outline" style="font-size:1rem;padding:14px 32px">Comprendre le concept</a>
    </div>
    <div class="hero-mascots">
      <div class="hero-masc-wrap" onclick="location.href='<?= page_url('clans') ?>#clan-bocage'">
        <img src="<?= img('mascotte-bocage.png') ?>" alt="Bran le Bocager" class="masc-bob" style="height:175px">
        <span class="hero-masc-label">Clan du Bocage</span>
      </div>
      <div class="hero-masc-wrap" onclick="location.href='<?= page_url('clans') ?>#clan-littoral'">
        <img src="<?= img('mascotte-littoral.png') ?>" alt="Gabin Culsmouillés" class="masc-bob" style="height:240px;animation-delay:.2s">
        <span class="hero-masc-label">Clan du Littoral</span>
      </div>
      <div class="hero-masc-wrap" onclick="location.href='<?= page_url('clans') ?>#clan-marais'">
        <img src="<?= img('mascotte-marais.png') ?>" alt="Méric l'Ancien" class="masc-bob" style="height:175px;animation-delay:.4s">
        <span class="hero-masc-label">Clan du Marais</span>
      </div>
    </div>
  </div>
</section>

<!-- ============ STATS BAR ============ -->
<section id="stats-bar">
  <div class="container">
    <div class="stats-grid">
      <?php
      // TODO: remplacer par SELECT COUNT(*) FROM users (total membres inscrits)
      $total_members = array_sum(array_column($clans, 'members_count'));
      ?>
      <div class="stat-item">
        <div class="stat-num"><?= number_format(floor($total_members / 1000), 0, ',', ' ') ?> <span><?= str_pad($total_members % 1000, 3, '0', STR_PAD_LEFT) ?></span></div>
        <div class="stat-label">Aventuriers</div>
      </div>
      <div class="stat-item"><div class="stat-num"><span><?= count($clans) ?></span></div><div class="stat-label">Clans actifs</div></div>
      <div class="stat-item"><div class="stat-num"><span>4</span></div><div class="stat-label">Saisons par an</div></div>
      <div class="stat-item">
        <div class="stat-num" style="font-size:1rem;line-height:1.3">Camp<br><span style="font-size:1.4rem">d'Été</span></div>
        <div class="stat-label">Saison en cours</div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 2 MODES ============ -->
<section id="modes">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">Comment ça marche</span>
      <h2 class="section-title">Zone 85 fonctionne en 2 modes</h2>
      <p class="section-sub">Un terrain de jeu à deux niveaux : tu joues pour toi, et tu joues pour ton clan. Les deux se nourrissent.</p>
    </div>
    <div class="modes-grid">
      <div class="mode-card orange reveal">
        <div class="mode-icon">⚡</div>
        <div class="mode-title">Mode personnel</div>
        <p class="mode-desc">Tu progresses à ton rythme. Chaque participation enrichit ton profil — à vie. L'XP ne revient jamais à zéro.</p>
        <div class="mode-points">
          <div class="mode-point">Gagne des XP à chaque participation</div>
          <div class="mode-point">Monte de niveau, débloque des badges</div>
          <div class="mode-point">Ton XP est permanent — à vie</div>
          <div class="mode-point">Quiz, photos, randos, votes, Kéto Kolé Tché…</div>
        </div>
      </div>
      <div class="mode-card navy reveal" style="transition-delay:.1s">
        <div class="mode-icon">🛡️</div>
        <div class="mode-title">Mode collectif</div>
        <p class="mode-desc">Ton clan participe à une grande mission par saison. Les clans s'affrontent. Le vainqueur emporte un trophée archivé pour toujours.</p>
        <div class="mode-points">
          <div class="mode-point">1 grande mission collective par saison</div>
          <div class="mode-point">Les 3 clans s'affrontent dans la Bataille des Clans</div>
          <div class="mode-point">Le score de clan repart à zéro chaque saison</div>
          <div class="mode-point">Le trophée du clan gagnant est archivé définitivement</div>
        </div>
      </div>
    </div>
    <div class="mode-tagline reveal" style="transition-delay:.2s">
      <em>Je progresse pour moi.</em> Je fais gagner mon clan.
    </div>
  </div>
</section>

<!-- ============ LES 3 CLANS ============ -->
<section id="clans-home">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">Les 3 clans</span>
      <h2 class="section-title">Choisissez votre camp</h2>
      <p class="section-sub">Trois clans, trois identités, un même territoire. À vous de trouver votre place dans l'Esprit Vendée.</p>
    </div>
    <div class="clans-grid">

      <?php
      $delay = 0;
      foreach ($clans as $slug => $clan):
        $trophy_label = $clan['trophies'] === 1 ? 'Trophée' : 'Trophées';
        $delay_style  = $delay > 0 ? " style=\"transition-delay:{$delay}s\"" : '';
      ?>
      <div class="clan-card reveal"<?= $delay_style ?>>
        <div class="clan-header clan-header-<?= e($slug) ?>">
          <div class="clan-masc-wrap"><img src="<?= img($clan['mascot']) ?>" alt="<?= e($clan['hero_name']) ?>" class="masc-bob"></div>
          <div class="clan-name-pill"><?= e($clan['name']) ?></div>
        </div>
        <div class="clan-body">
          <div class="clan-title"><?= e($clan['name']) ?></div>
          <div class="clan-mascot-name"><?= e($clan['hero_name']) ?></div>
          <?php if (!empty($clan['description'])): ?>
          <div class="clan-cri"><?= e(truncate_text($clan['description'], 60)) ?>…</div>
          <?php endif; ?>
          <div class="clan-stats">
            <div class="clan-stat">
              <div class="clan-stat-val"><?= $clan['members_count'] ?></div>
              <div class="clan-stat-lbl">Membres</div>
            </div>
            <div class="clan-stat">
              <div class="clan-stat-val"><?= number_format($clan['season_score'], 0, ',', ' ') ?></div>
              <div class="clan-stat-lbl">Score de saison</div>
            </div>
            <div class="clan-stat">
              <div class="clan-stat-val"><?= $clan['trophies'] ?></div>
              <div class="clan-stat-lbl"><?= $trophy_label ?></div>
            </div>
          </div>
          <a href="<?= page_url('clans') ?>#clan-<?= e($slug) ?>" class="btn-clan">Découvrir le clan →</a>
        </div>
      </div>
      <?php
        $delay = round($delay + 0.1, 1);
      endforeach;
      ?>

    </div>
    <p style="text-align:center;margin-top:28px;font-size:.82rem;color:var(--text-muted)">Les pts de saison sont remis à zéro chaque saison. L'XP personnel est à vie.</p>
  </div>
</section>

<!-- ============ SAISON EN COURS ============ -->
<section id="saison">
  <div class="container">
    <div class="saison-bloc">

      <div class="reveal">
        <span class="eyebrow-tag-white">Saison en cours</span>
        <h2 class="section-title" style="color:#fff;margin-bottom:12px"><?= e($active_season['title']) ?></h2>
        <p style="color:rgba(255,255,255,.6);font-size:.95rem;line-height:1.7;margin-bottom:4px"><?= e(ucfirst($active_season['period'])) ?> · Soleil, littoral, vacances. La grande mission de l'été bat son plein.</p>

        <div class="saison-leader">
          <img src="<?= img($clan_leader['mascot']) ?>" alt="<?= e($clan_leader['hero_name']) ?>" style="width:40px;height:40px;object-fit:contain">
          <div>
            <div class="saison-leader-lbl">Clan en tête — <?= e($active_season['title']) ?></div>
            <div class="saison-leader-name"><?= e($clan_leader['name']) ?> · <?= format_score($clan_leader['season_score']) ?></div>
          </div>
          <span style="margin-left:auto;font-size:1.3rem">🥇</span>
        </div>

        <div style="background:rgba(255,255,255,.06);border-radius:var(--radius);padding:16px 20px;border:1px solid rgba(255,255,255,.1);margin-bottom:20px">
          <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4);margin-bottom:6px">Grande mission de saison</div>
          <div style="font-size:1.05rem;font-weight:800;color:#fff"><?= e($active_season['main_mission']) ?></div>
          <div style="font-size:.82rem;color:rgba(255,255,255,.55);margin-top:4px">Participez, documentez, partagez — chaque action compte pour ton clan.</div>
        </div>

        <!-- Countdown — zone85.js détecte automatiquement .countdown-days / .countdown-hours / .countdown-mins -->
        <!-- data-end est utilisé par zone85.js pour calculer le compte à rebours -->
        <div class="saison-countdown" data-end="<?= e($active_season['end_date']) ?>">
          <div class="countdown-box"><div class="countdown-num countdown-days">--</div><div class="countdown-lbl">Jours</div></div>
          <div class="countdown-box"><div class="countdown-num countdown-hours">--</div><div class="countdown-lbl">Heures</div></div>
          <div class="countdown-box"><div class="countdown-num countdown-mins">--</div><div class="countdown-lbl">Min</div></div>
        </div>

        <a href="<?= page_url('missions') ?>" class="btn btn-primary">Participer à la mission →</a>
        <a href="<?= page_url('clans') ?>" class="btn btn-outline" style="margin-left:12px">Voir la course</a>
      </div>

      <div class="saison-race reveal" style="transition-delay:.15s">
        <div class="saison-race-title">Score de saison — <?= e($active_season['title']) ?></div>

        <?php
        $rank = 0;
        foreach ($clans_race as $slug => $clan):
          $is_last = ($rank === count($clans_race) - 1);
          $mb_style = $is_last ? '' : 'margin-bottom:16px';
        ?>
        <div style="display:grid;grid-template-columns:140px 1fr 80px;align-items:center;gap:12px;<?= $mb_style ?>">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:36px;height:36px;border-radius:6px;overflow:hidden;background:rgba(255,255,255,.06);flex-shrink:0">
              <img src="<?= img($clan['mascot']) ?>" alt="<?= e($clan['hero_name']) ?>" style="width:100%;height:100%;object-fit:contain">
            </div>
            <div>
              <div style="font-size:.9rem;margin-bottom:2px"><?= $medals[$rank] ?></div>
              <div style="font-size:.82rem;font-weight:700;color:#fff"><?= e(ucfirst($slug)) ?></div>
              <div style="font-size:.68rem;color:rgba(255,255,255,.4)"><?= $clan['members_count'] ?> membres</div>
            </div>
          </div>
          <div style="background:rgba(255,255,255,.1);border-radius:6px;height:12px;overflow:hidden">
            <div class="race-fill race-fill-<?= e($slug) ?>" data-width="<?= $clan['race_width'] ?>" style="height:100%"></div>
          </div>
          <div style="text-align:right">
            <div style="font-size:.88rem;font-weight:800;color:#fff"><?= number_format($clan['season_score'], 0, ',', ' ') ?></div>
            <div style="font-size:.65rem;color:rgba(255,255,255,.35)">pts</div>
          </div>
        </div>
        <?php $rank++; endforeach; ?>

        <div style="border-top:1px solid rgba(255,255,255,.1);margin-top:18px;padding-top:14px;font-size:.72rem;color:rgba(255,255,255,.35)">
          Score saisonnier uniquement — remis à zéro à la prochaine saison
        </div>
        <div style="margin-top:16px">
          <a href="<?= page_url('classement') ?>" class="btn btn-outline" style="font-size:.85rem;padding:10px 20px;width:100%;justify-content:center">Voir le classement complet →</a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ============ ACTIONS DU QUOTIDIEN ============ -->
<section id="actions">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">Actions personnelles</span>
      <h2 class="section-title">Participer, c'est simple</h2>
      <p class="section-sub">Des petites actions toute l'année pour gagner des XP, monter de niveau, et contribuer à la saison de ton clan.</p>
    </div>
    <div class="actions-grid">
      <div class="action-card reveal">
        <div class="action-icon" style="background:rgba(234,86,73,.1)">🎯</div>
        <div class="action-title">Quiz vendéen</div>
        <p class="action-desc">Teste tes connaissances sur la Vendée. Histoire, nature, folklore, spécialités. Un nouveau quiz régulièrement.</p>
        <div class="action-xp">+20 à +60 XP</div>
      </div>
      <div class="action-card reveal" style="transition-delay:.05s">
        <div class="action-icon" style="background:rgba(201,150,42,.1)">📸</div>
        <div class="action-title">Défi photo</div>
        <p class="action-desc">Capture la Vendée telle que tu la vis. Paysages, artisans, couchers de soleil, moments de vie.</p>
        <div class="action-xp">+25 à +80 XP</div>
      </div>
      <div class="action-card reveal" style="transition-delay:.1s">
        <div class="action-icon" style="background:rgba(42,157,92,.1)">🥾</div>
        <div class="action-title">Avis de rando</div>
        <p class="action-desc">Tu as marché ? Raconte. Un avis sur un sentier et tu aides toute la communauté à explorer la Vendée à pied.</p>
        <div class="action-xp">+30 à +50 XP</div>
      </div>
      <div class="action-card reveal" style="transition-delay:.15s">
        <div class="action-icon" style="background:rgba(18,49,78,.1)">🔍</div>
        <div class="action-title">Kéto Kolé Tché</div>
        <p class="action-desc">Objet mystère, lieu inconnu, expression locale. Tu identifies, tu proposes, tu contribues à la mémoire collective vendéenne.</p>
        <div class="action-xp">+40 à +100 XP</div>
      </div>
      <div class="action-card reveal" style="transition-delay:.2s">
        <div class="action-icon" style="background:rgba(234,86,73,.08)">🌦️</div>
        <div class="action-title">Météo-mission</div>
        <p class="action-desc">La météo comme prétexte à l'aventure. Chaque condition météo débloque une mini-mission unique.</p>
        <div class="action-xp">+15 à +40 XP</div>
      </div>
      <div class="action-card reveal" style="transition-delay:.25s">
        <div class="action-icon" style="background:rgba(201,150,42,.08)">🗳️</div>
        <div class="action-title">Vote &amp; commentaire</div>
        <p class="action-desc">Ton avis compte. Vote sur les meilleures contributions, commente, réagis. La communauté, c'est aussi ça.</p>
        <div class="action-xp">+5 à +15 XP</div>
      </div>
    </div>
    <div style="text-align:center;margin-top:40px">
      <a href="<?= page_url('missions') ?>" class="btn btn-primary">Voir toutes les missions →</a>
    </div>
  </div>
</section>

<!-- ============ HALL APERÇU ============ -->
<section id="hall-apercu">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">Hall de la Zone</span>
      <h2 class="section-title">La vie de la communauté</h2>
      <p class="section-sub">Dernières contributions, badges récents, trophée précédent — la mémoire vivante de la Zone.</p>
    </div>
    <div class="hall-grid">
      <div class="hall-card reveal">
        <div class="hall-card-title">Dernières contributions</div>
        <?php
        // TODO: remplacer par SELECT * FROM participations ORDER BY created_at DESC LIMIT 4
        $recent_contributions = [
          ['avatar' => '🌊', 'name' => 'Sophie M.',     'action' => 'A répondu au quiz Marais Poitevin',             'xp' => '+45 XP'],
          ['avatar' => '📸', 'name' => 'Pierre V.',     'action' => 'Défi photo — coucher de soleil aux Sables',     'xp' => '+60 XP'],
          ['avatar' => '🔍', 'name' => 'Marie-Ange L.', 'action' => 'A identifié un objet Kéto Kolé Tché',           'xp' => '+80 XP'],
          ['avatar' => '🥾', 'name' => 'Thomas D.',     'action' => 'Avis sur le sentier côtier de Noirmoutier',    'xp' => '+35 XP'],
        ];
        foreach ($recent_contributions as $contrib):
        ?>
        <div class="hall-contrib-item">
          <div class="hall-contrib-avatar"><?= $contrib['avatar'] ?></div>
          <div>
            <div class="hall-contrib-name"><?= e($contrib['name']) ?></div>
            <div class="hall-contrib-action"><?= e($contrib['action']) ?></div>
          </div>
          <div class="hall-contrib-xp"><?= e($contrib['xp']) ?></div>
        </div>
        <?php endforeach; ?>
        <div style="text-align:center;margin-top:16px">
          <a href="<?= page_url('hall') ?>" class="btn btn-ghost" style="font-size:.82rem;padding:8px 18px">Voir le Hall complet →</a>
        </div>
      </div>

      <div>
        <div class="hall-card reveal" style="transition-delay:.1s;margin-bottom:20px">
          <div class="hall-card-title">Badges récents</div>
          <div class="badge-showcase">
            <?php
            // TODO: remplacer par SELECT * FROM user_badges ORDER BY obtained_at DESC LIMIT 4
            $recent_badges = [
              ['icon' => '🌊', 'name' => 'Marin d\'eau douce'],
              ['icon' => '📸', 'name' => 'L\'Œil du Littoral'],
              ['icon' => '🏹', 'name' => 'Chasseur de primes'],
              ['icon' => '⚡', 'name' => 'Streak 7 jours'],
            ];
            foreach ($recent_badges as $badge):
            ?>
            <div class="badge-item">
              <div class="badge-item-icon"><?= $badge['icon'] ?></div>
              <div class="badge-item-name"><?= e($badge['name']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php
        // Trophée de la saison précédente archivée
        $prev_trophy = $season_trophies[0] ?? null;
        if ($prev_trophy):
          $trophy_clan = $clans[$prev_trophy['winner_clan']] ?? null;
        ?>
        <div class="hall-card reveal" style="transition-delay:.15s">
          <div class="hall-card-title">Trophée précédent — <?= e($prev_trophy['season']) ?></div>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
            <div style="width:52px;height:52px;background:linear-gradient(135deg,#0a1a2e,#163756);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">🏆</div>
            <div>
              <div style="font-size:1rem;font-weight:800;color:var(--text)"><?= e($prev_trophy['winner_name']) ?></div>
              <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px"><?= e($prev_trophy['season']) ?> · <?= $prev_trophy['contributions'] ?> contributions</div>
              <?php if ($trophy_clan): ?>
              <div style="font-size:.72rem;color:var(--primary);font-weight:700;margin-top:4px"><?= e($trophy_clan['hero_name']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <a href="<?= page_url('clans') ?>#trophees" class="btn btn-ghost" style="font-size:.78rem;padding:7px 16px">Voir les archives →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ CTA FINAL ============ -->
<section id="cta-final">
  <div class="container">
    <div class="cta-inner">
      <div class="cta-mascots">
        <img src="<?= img('mascotte-bocage.png') ?>" alt="" style="height:90px;filter:drop-shadow(0 6px 18px rgba(0,0,0,.3))">
        <img src="<?= img('mascotte-littoral.png') ?>" alt="" style="height:120px;filter:drop-shadow(0 6px 18px rgba(0,0,0,.3))">
        <img src="<?= img('mascotte-marais.png') ?>" alt="" style="height:90px;filter:drop-shadow(0 6px 18px rgba(0,0,0,.3))">
      </div>
      <h2 class="cta-title">Ta légende commence maintenant</h2>
      <?php
      // TODO: remplacer le total aventuriers par SELECT COUNT(*) FROM users
      $total_members = array_sum(array_column($clans, 'members_count'));
      ?>
      <p class="cta-sub">Rejoins <?= number_format($total_members, 0, ',', ' ') ?> aventuriers vendéens. Choisis ton clan, participe, progresse. L'Esprit Vendée t'attend.</p>
      <div class="cta-chips">
        <span class="cta-chip">50 XP offerts à l'inscription</span>
        <span class="cta-chip"><?= count($clans) ?> clans au choix</span>
        <span class="cta-chip">Gratuit</span>
        <span class="cta-chip"><?= e($active_season['title']) ?> en cours</span>
      </div>
      <a href="<?= page_url('inscription') ?>" class="btn-white">Rejoindre la Zone 85 →</a>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
