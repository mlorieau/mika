<?php
$page_title       = 'Le Concept — Zone85';
$page_description = 'Zone85 : randos, articles, livres, jeux — et un terrain de jeu communautaire pour tout relier. La Vendée vécue, racontée et jouée par ceux qui l\'habitent.';
$page_canonical   = 'https://www.zone85.fr/concept.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Le Concept','item'=>'https://www.zone85.fr/concept.php'],
    ],
];
$current_page = 'concept';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';

$page_styles = '<style>

/* ── Sections communes ── */
.cp-section{padding:88px 0}
.cp-section-beige{background:var(--beige)}
.cp-section-white{background:var(--white)}
.cp-section-navy{background:linear-gradient(155deg,#060e16,#0c1e2e);padding:88px 0}

/* ── Section header ── */
.cp-head{margin-bottom:52px}
.cp-eyebrow{font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--primary);margin-bottom:10px}
.cp-title{font-size:clamp(1.8rem,3.5vw,2.6rem);font-weight:900;color:var(--text);letter-spacing:-.5px;line-height:1.15;margin-bottom:12px}
.cp-sub{font-size:.96rem;color:var(--text-muted);max-width:560px;line-height:1.75}
.cp-section-navy .cp-title{color:#fff}
.cp-section-navy .cp-sub{color:rgba(255,255,255,.5)}
.cp-section-navy .cp-eyebrow{color:#f5a99f}

/* ── Piliers de contenu ── */
.cp-pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.cp-pillar{border-radius:14px;overflow:hidden;border:1.5px solid var(--beige-dark);background:var(--white);transition:transform .25s,box-shadow .25s}
.cp-pillar:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.09)}
.cp-pillar-header{padding:28px 22px 20px;position:relative}
.cp-pillar-header-echos{background:linear-gradient(135deg,#1a1a2e,#12314e)}
.cp-pillar-header-randos{background:linear-gradient(135deg,#1a3d1a,#12314e)}
.cp-pillar-header-ktc{background:linear-gradient(135deg,#2b1a0a,#12314e)}
.cp-pillar-header-victor{background:linear-gradient(135deg,#1a1500,#3a2a00)}
.cp-pillar-header-invisibles{background:linear-gradient(135deg,#0e0514,#1a0a2e)}
.cp-pillar-icon{font-size:2.2rem;line-height:1;margin-bottom:12px;display:block}
.cp-pillar-name{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.5);margin-bottom:6px}
.cp-pillar-title{font-size:1rem;font-weight:900;color:#fff;line-height:1.2}
.cp-pillar-tag{position:absolute;top:14px;right:14px;font-size:.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;padding:3px 8px;border-radius:3px}
.cp-pillar-tag-members{background:rgba(201,150,42,.2);color:#d4a43a;border:1px solid rgba(201,150,42,.3)}
.cp-pillar-tag-soon{background:rgba(139,59,140,.2);color:#c471c4;border:1px solid rgba(139,59,140,.3)}
.cp-pillar-body{padding:18px 22px 22px}
.cp-pillar-desc{font-size:.85rem;color:var(--text-muted);line-height:1.65;margin-bottom:16px}
.cp-pillar-link{display:inline-flex;align-items:center;gap:6px;font-size:.82rem;font-weight:700;color:var(--primary);text-decoration:none;border-bottom:1px solid transparent;transition:border-color .15s}
.cp-pillar-link:hover{border-bottom-color:var(--primary)}
.cp-pillar-link-muted{color:var(--text-muted);pointer-events:none}

/* ── Victor highlight ── */
.cp-victor{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center;padding:48px;background:linear-gradient(135deg,#1a1500,#3a2800);border-radius:16px;border:1px solid rgba(201,150,42,.2)}
.cp-victor-text .cp-eyebrow{color:#d4a43a}
.cp-victor-text h3{font-size:clamp(1.4rem,2.5vw,2rem);font-weight:900;color:#fff;letter-spacing:-.5px;margin-bottom:10px}
.cp-victor-text p{font-size:.9rem;color:rgba(255,255,255,.55);line-height:1.7;margin-bottom:24px}
.cp-victor-cta{display:inline-flex;align-items:center;gap:8px;background:rgba(201,150,42,.15);border:1.5px solid rgba(201,150,42,.4);color:#d4a43a;padding:12px 22px;border-radius:7px;font-size:.9rem;font-weight:800;text-decoration:none;transition:background .2s}
.cp-victor-cta:hover{background:rgba(201,150,42,.25)}
.cp-victor-cta-locked{color:rgba(255,255,255,.3);border-color:rgba(255,255,255,.1);pointer-events:none;cursor:default}
.cp-victor-visual{display:flex;align-items:center;justify-content:center;font-size:6rem;text-align:center;opacity:.8}

/* ── Les Invisibles teaser ── */
.cp-invisibles{border-radius:16px;overflow:hidden;border:1px solid rgba(139,59,140,.2);background:linear-gradient(135deg,#0e0514,#1a0a2e);padding:40px;text-align:center;position:relative}
.cp-invisibles::before{content:"";position:absolute;inset:0;background:radial-gradient(ellipse 60% 40% at 50% 0%,rgba(180,80,180,.08),transparent 70%);pointer-events:none}
.cp-invisibles-eyebrow{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.15em;color:rgba(180,80,180,.8);margin-bottom:14px}
.cp-invisibles h3{font-size:clamp(1.4rem,2.5vw,2rem);font-weight:900;color:#fff;letter-spacing:-.5px;margin-bottom:10px}
.cp-invisibles p{font-size:.9rem;color:rgba(255,255,255,.45);max-width:440px;margin:0 auto 24px;line-height:1.7}
.cp-invisibles-tag{display:inline-block;padding:6px 18px;border-radius:20px;background:rgba(180,80,180,.12);border:1px solid rgba(180,80,180,.25);color:rgba(180,80,180,.8);font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}

/* ── 2 modes jeu ── */
.cp-modes{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.cp-mode{background:var(--white);border-radius:14px;padding:32px 26px;border:1.5px solid var(--beige-dark);border-top:4px solid transparent}
.cp-mode.orange{border-top-color:var(--primary)}
.cp-mode.navy{border-top-color:var(--navy-dark)}
.cp-mode-icon{font-size:2rem;margin-bottom:14px}
.cp-mode-title{font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:8px}
.cp-mode-desc{font-size:.87rem;color:var(--text-muted);line-height:1.65;margin-bottom:18px}
.cp-mode-points{display:flex;flex-direction:column;gap:7px}
.cp-mode-point{font-size:.83rem;color:var(--text-muted);display:flex;align-items:flex-start;gap:8px}
.cp-mode-point::before{content:"✓";flex-shrink:0;font-weight:800;margin-top:1px}
.cp-mode.orange .cp-mode-point::before{color:var(--primary)}
.cp-mode.navy .cp-mode-point::before{color:var(--navy-dark)}
.cp-reset{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:28px}
.cp-reset-card{border-radius:var(--radius);padding:18px 20px}
.cp-reset-ok{background:rgba(42,157,92,.07);border:1px solid rgba(42,157,92,.18)}
.cp-reset-zero{background:rgba(234,86,73,.07);border:1px solid rgba(234,86,73,.18)}
.cp-reset-label{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px}
.cp-reset-ok .cp-reset-label{color:#2a9d5c}
.cp-reset-zero .cp-reset-label{color:var(--primary)}
.cp-reset-item{font-size:.83rem;color:var(--text-mid);padding:5px 0;border-bottom:1px solid var(--beige-dark);display:flex;align-items:center;gap:8px}
.cp-reset-item:last-child{border-bottom:none}
.cp-reset-ok .cp-reset-item::before{content:"✓";color:#2a9d5c;font-weight:700}
.cp-reset-zero .cp-reset-item::before{content:"↺";color:var(--primary);font-weight:700}

/* ── Saisons ── */
.cp-season-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.cp-season{border-radius:12px;padding:24px 18px;text-align:center;border:1px solid rgba(255,255,255,.08)}
.cp-season.active{background:rgba(234,86,73,.1);border-color:rgba(234,86,73,.3)}
.cp-season:not(.active){background:rgba(255,255,255,.04)}
.cp-season-emoji{font-size:1.8rem;margin-bottom:10px;display:block}
.cp-season-badge{display:inline-block;font-size:.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;padding:3px 9px;border-radius:3px;margin-bottom:8px}
.cp-season.active .cp-season-badge{background:var(--primary);color:#fff}
.cp-season:not(.active) .cp-season-badge{background:rgba(255,255,255,.1);color:rgba(255,255,255,.4)}
.cp-season-name{font-size:.95rem;font-weight:800;color:#fff;margin-bottom:3px;line-height:1.2}
.cp-season-period{font-size:.68rem;color:rgba(255,255,255,.4);font-weight:600;margin-bottom:8px}
.cp-season-theme{font-size:.78rem;color:rgba(255,255,255,.55);line-height:1.5;margin-bottom:10px}
.cp-season-mission{font-size:.72rem;font-weight:700;color:var(--primary);background:rgba(234,86,73,.1);padding:5px 10px;border-radius:4px;line-height:1.4}

/* ── Code de la Zone ── */
.cp-code-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.cp-code-card{background:rgba(255,255,255,.04);border-radius:12px;padding:22px 18px;border:1px solid rgba(255,255,255,.08);border-bottom:3px solid var(--primary)}
.cp-code-num{font-size:.6rem;font-weight:800;color:var(--primary);letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px}
.cp-code-rule{font-size:.9rem;font-weight:800;color:#fff;margin-bottom:6px;line-height:1.3}
.cp-code-detail{font-size:.78rem;color:rgba(255,255,255,.5);line-height:1.5}

/* ── FAQ ── */
.cp-faq-list{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:8px}
.cp-faq-item{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:var(--radius)}
.cp-faq-q{padding:16px 22px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:12px}
.cp-faq-q-text{font-size:.92rem;font-weight:700;color:#fff}
.cp-faq-chevron{color:rgba(255,255,255,.35);font-size:1rem;transition:transform .22s;flex-shrink:0}
.cp-faq-item.open .cp-faq-chevron{transform:rotate(180deg)}
.cp-faq-a{display:none;padding:0 22px 16px;font-size:.86rem;color:rgba(255,255,255,.55);line-height:1.72}
.cp-faq-item.open .cp-faq-a{display:block}

@media(max-width:1024px){
  .cp-season-grid{grid-template-columns:repeat(2,1fr)}
  .cp-code-grid{grid-template-columns:repeat(2,1fr)}
  .cp-pillars{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
  .cp-pillars,.cp-modes,.cp-reset,.cp-victor{grid-template-columns:1fr}
  .cp-season-grid,.cp-code-grid{grid-template-columns:1fr}
  .cp-victor{padding:28px 24px}
  .cp-section,.cp-section-navy{padding:64px 0}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ── HERO ─────────────────────────────────────────────────── -->
<section class="page-hero">
  <div class="container">
    <div class="page-hero-inner">
      <div class="page-eyebrow">Le Concept</div>
      <h1 class="page-h1">La Vendée vécue,<br>racontée et <em>jouée</em>.</h1>
      <p class="page-sub">Zone85 n'est pas un site de jeu avec des articles sur le côté. C'est une revue vivante sur la Vendée — ses chemins, ses histoires, ses objets, ses gens — avec un terrain de jeu communautaire pour relier tout ça.</p>
    </div>
  </div>
</section>


<!-- ── LE CONTENU ─────────────────────────────────────────────── -->
<section class="cp-section cp-section-white">
  <div class="container">
    <div class="cp-head">
      <div class="cp-eyebrow">Ce que vous trouverez ici</div>
      <h2 class="cp-title">Cinq façons de vivre la Vendée</h2>
      <p class="cp-sub">Le jeu est l'ambiance. Le contenu est la raison d'être. Voici ce que Zone85 produit, rassemble et fait vivre.</p>
    </div>
    <div class="cp-pillars">

      <!-- Les Échos -->
      <div class="cp-pillar">
        <div class="cp-pillar-header cp-pillar-header-echos">
          <span class="cp-pillar-icon">📰</span>
          <div class="cp-pillar-name">Éditorial</div>
          <div class="cp-pillar-title">Les Échos de la Zone</div>
        </div>
        <div class="cp-pillar-body">
          <p class="cp-pillar-desc">Le magazine communautaire. Histoires, curiosités, récits du territoire vendéen — courts, humains, locaux. Pas de commentaires, juste de la lecture.</p>
          <a href="les-echos.php" class="cp-pillar-link">Lire les Échos →</a>
        </div>
      </div>

      <!-- Randos -->
      <div class="cp-pillar">
        <div class="cp-pillar-header cp-pillar-header-randos">
          <span class="cp-pillar-icon">🥾</span>
          <div class="cp-pillar-name">Terrain</div>
          <div class="cp-pillar-title">Les Randos</div>
        </div>
        <div class="cp-pillar-body">
          <p class="cp-pillar-desc">Des sentiers commentés et vécus par la communauté. GR, circuits, chemins oubliés — chaque Zonaute qui marche laisse son avis, gagne des XP, enrichit le guide.</p>
          <a href="randos.php" class="cp-pillar-link">Explorer les randos →</a>
        </div>
      </div>

      <!-- KTC -->
      <div class="cp-pillar">
        <div class="cp-pillar-header cp-pillar-header-ktc">
          <span class="cp-pillar-icon">🔍</span>
          <div class="cp-pillar-name">Jeu culturel</div>
          <div class="cp-pillar-title">Kéto Kolé Tché</div>
        </div>
        <div class="cp-pillar-body">
          <p class="cp-pillar-desc">"Qu'est-ce que c'est que ça ?" en vendéen populaire. Objets mystères, lieux à identifier, expressions à deviner. Court, fun, local — et souvent plus difficile qu'il n'y paraît.</p>
          <a href="ktc.php" class="cp-pillar-link">Jouer au KTC →</a>
        </div>
      </div>

      <!-- VICTOR -->
      <div class="cp-pillar">
        <div class="cp-pillar-header cp-pillar-header-victor">
          <span class="cp-pillar-icon">📖</span>
          <div class="cp-pillar-name">Livre membre</div>
          <div class="cp-pillar-title">VICTOR</div>
          <span class="cp-pillar-tag cp-pillar-tag-members">Membres</span>
        </div>
        <div class="cp-pillar-body">
          <p class="cp-pillar-desc">Un livre téléchargeable réservé aux membres de Zone85. Une histoire vendéenne — personnages, territoire, mémoire. Votre première récompense pour avoir rejoint la Zone.</p>
          <a href="victor.php" class="cp-pillar-link">Accéder à VICTOR →</a>
        </div>
      </div>

      <!-- Les Invisibles -->
      <div class="cp-pillar">
        <div class="cp-pillar-header cp-pillar-header-invisibles">
          <span class="cp-pillar-icon">🎭</span>
          <div class="cp-pillar-name">Prochain jeu</div>
          <div class="cp-pillar-title">Les Invisibles</div>
          <span class="cp-pillar-tag cp-pillar-tag-soon">Bientôt</span>
        </div>
        <div class="cp-pillar-body">
          <p class="cp-pillar-desc">Le prochain jeu immersif de Zone85. Une aventure qui sortira du terrain numérique pour entrer dans la Vendée réelle. Plus d'informations prochainement.</p>
          <span class="cp-pillar-link cp-pillar-link-muted">En préparation…</span>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ── VICTOR ─────────────────────────────────────────────────── -->
<section class="cp-section cp-section-beige">
  <div class="container">
    <div class="cp-victor">
      <div class="cp-victor-text">
        <div class="cp-eyebrow" style="color:#d4a43a">Réservé aux membres</div>
        <h3>VICTOR, le livre de la Zone</h3>
        <p>Un PDF réservé aux membres de Zone85. L'inscription déverrouille le téléchargement — chaque accès est comptabilisé pour suivre l'audience. Un avant-goût de ce que Zone85 produit quand le jeu devient récit.</p>
        <a href="victor.php" class="cp-victor-cta">Accéder à VICTOR →</a>
      </div>
      <div class="cp-victor-visual">📖</div>
    </div>
  </div>
</section>


<!-- ── LES INVISIBLES ──────────────────────────────────────────── -->
<section class="cp-section cp-section-white">
  <div class="container">
    <div class="cp-invisibles">
      <div class="cp-invisibles-eyebrow">Prochain projet</div>
      <h3>Les Invisibles</h3>
      <p>Zone85 prépare un jeu immersif ancré dans la Vendée réelle. Des personnages, des lieux, des indices — une enquête qui se joue sur le terrain. Les détails arrivent bientôt pour les membres.</p>
      <span class="cp-invisibles-tag">En préparation</span>
    </div>
  </div>
</section>


<!-- ── LE TERRAIN DE JEU ───────────────────────────────────────── -->
<section class="cp-section cp-section-beige">
  <div class="container">
    <div class="cp-head">
      <div class="cp-eyebrow">Le terrain de jeu</div>
      <h2 class="cp-title">L'environnement qui relie tout</h2>
      <p class="cp-sub">Le jeu n'est pas le site. C'est ce qui rend le contenu engageant — une façon de participer, de progresser et de contribuer à la communauté.</p>
    </div>
    <div class="cp-modes">
      <div class="cp-mode orange">
        <div class="cp-mode-icon">⚡</div>
        <div class="cp-mode-title">Ta progression personnelle</div>
        <p class="cp-mode-desc">Chaque contribution — avis de rando, réponse au KTC, article lu et commenté — rapporte de l'XP. Ton profil grandit à vie, jamais remis à zéro.</p>
        <div class="cp-mode-points">
          <div class="cp-mode-point">Randos, quiz, photos, KTC, votes</div>
          <div class="cp-mode-point">Tu montes de niveau, tu débloque des badges</div>
          <div class="cp-mode-point">Ton XP est permanent — à vie</div>
          <div class="cp-mode-point">Accès à des contenus réservés (VICTOR, Les Invisibles)</div>
        </div>
      </div>
      <div class="cp-mode navy">
        <div class="cp-mode-icon">🛡️</div>
        <div class="cp-mode-title">La Bataille des Clans</div>
        <p class="cp-mode-desc">3 clans vendéens s'affrontent en saison. Bocage, Littoral, Marais. 4 saisons par an, 1 grande mission collective, 1 trophée archivé pour l'éternité.</p>
        <div class="cp-mode-points">
          <div class="cp-mode-point">4 saisons · 1 grande mission par saison</div>
          <div class="cp-mode-point">Score de clan repart à zéro chaque saison</div>
          <div class="cp-mode-point">Le clan vainqueur reçoit un trophée archivé</div>
          <div class="cp-mode-point">Ta contribution compte pour toi et pour ton clan</div>
        </div>
      </div>
    </div>
    <div class="cp-reset">
      <div class="cp-reset-card cp-reset-ok">
        <div class="cp-reset-label">Ce qui ne revient jamais à zéro</div>
        <div class="cp-reset-item">Ton XP personnel</div>
        <div class="cp-reset-item">Ton niveau et tes badges</div>
        <div class="cp-reset-item">Ton accès à VICTOR</div>
        <div class="cp-reset-item">Les trophées archivés des clans</div>
      </div>
      <div class="cp-reset-card cp-reset-zero">
        <div class="cp-reset-label">Ce qui repart à zéro chaque saison</div>
        <div class="cp-reset-item">Le score de chaque clan</div>
        <div class="cp-reset-item">Le classement collectif de saison</div>
        <div class="cp-reset-item">La grande mission collective</div>
      </div>
    </div>
  </div>
</section>


<!-- ── LES 4 SAISONS ───────────────────────────────────────────── -->
<section class="cp-section-navy">
  <div class="container">
    <div class="cp-head" style="margin-bottom:40px">
      <div class="cp-eyebrow">Le rythme</div>
      <h2 class="cp-title">4 saisons, 4 ambiances</h2>
      <p class="cp-sub">Chaque saison porte un thème vendéen. Les missions collectives, les Échos, les randos mises en avant — tout suit ce fil.</p>
    </div>
    <div class="cp-season-grid">
      <?php
      $season_emojis = ['🌱','☀️','🍂','🕯️'];
      $season_themes = [
          'Nature, villages, chemins, biodiversité vendéenne.',
          'Littoral, vacances, humour, esprit estival.',
          'Bocage, brume, patrimoine, mémoire et chemins cachés.',
          'Objets anciens, expressions, récits et enquêtes hivernales.',
      ];
      $season_missions_labels = ['La Grande Remise en Route','Le Grand Défi de l\'Été','La Traversée des Clans','Le Grand Kéto Kolé Tché'];
      foreach ($seasons as $i => $season):
          $is_active = ($season['status'] === 'active');
          $mission_label = $season['main_mission'] ?? ($season_missions_labels[$i] ?? '');
      ?>
      <div class="cp-season<?= $is_active ? ' active' : '' ?>">
        <span class="cp-season-emoji"><?= $season_emojis[$i] ?? '📅' ?></span>
        <div class="cp-season-badge"><?= $is_active ? 'En cours' : 'Saison '.($i+1) ?></div>
        <div class="cp-season-name"><?= e($season['title']) ?></div>
        <div class="cp-season-period"><?= e(ucfirst($season['period'] ?? '')) ?></div>
        <div class="cp-season-theme"><?= e($season_themes[$i] ?? '') ?></div>
        <?php if ($mission_label): ?><div class="cp-season-mission"><?= e($mission_label) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ── CODE DE LA ZONE ─────────────────────────────────────────── -->
<section class="cp-section-navy" style="border-top:1px solid rgba(255,255,255,.06)">
  <div class="container">
    <div class="cp-head" style="margin-bottom:40px">
      <div class="cp-eyebrow">Code de la Zone</div>
      <h2 class="cp-title">5 règles de l'Esprit Vendée</h2>
      <p class="cp-sub">Entrer en Zone85, tu pourras… mais d'abord quelques vérités vendéennes tu accepteras.</p>
    </div>
    <div class="cp-code-grid">
      <div class="cp-code-card">
        <div class="cp-code-num">Règle 01</div>
        <div class="cp-code-rule">Tu participes avec bonne humeur</div>
        <div class="cp-code-detail">La Zone, c'est une ambiance. Pas un concours de sérieux. La brioche se mange avec le sourire.</div>
      </div>
      <div class="cp-code-card">
        <div class="cp-code-num">Règle 02</div>
        <div class="cp-code-rule">Tu chambres sans être lourd</div>
        <div class="cp-code-detail">Rivaliser entre clans, oui. Se respecter, toujours. Le terrain de jeu reste convivial.</div>
      </div>
      <div class="cp-code-card">
        <div class="cp-code-num">Règle 03</div>
        <div class="cp-code-rule">Tu respectes les lieux</div>
        <div class="cp-code-detail">Chaque mission, chaque rando te fait découvrir la Vendée réelle. Ce patrimoine est à nous tous.</div>
      </div>
      <div class="cp-code-card">
        <div class="cp-code-num">Règle 04</div>
        <div class="cp-code-rule">Tu ne triches pas pour une mogette</div>
        <div class="cp-code-detail">Même pour un trophée. Le jeu n'a de valeur que si tout le monde joue vraiment.</div>
      </div>
      <div class="cp-code-card" style="grid-column:span 2">
        <div class="cp-code-num">Règle 05</div>
        <div class="cp-code-rule">Tu joues pour toi et pour ton clan</div>
        <div class="cp-code-detail">Ta progression et la victoire de ton clan sont les deux faces d'une même pièce. Les deux comptent.</div>
      </div>
    </div>
  </div>
</section>


<!-- ── FAQ ─────────────────────────────────────────────────────── -->
<section class="cp-section-navy" style="border-top:1px solid rgba(255,255,255,.06)">
  <div class="container">
    <div class="cp-head" style="margin-bottom:36px">
      <div class="cp-eyebrow">Questions fréquentes</div>
      <h2 class="cp-title">Ce que tu te demandes peut-être</h2>
    </div>
    <div class="cp-faq-list">
      <?php
      $faqs = [
          ['q'=>"C'est quoi VICTOR ?",
           'a'=>"VICTOR est un livre PDF réservé aux membres de Zone85. Une histoire vendéenne — personnages, territoire, mémoire. L'inscription déverrouille le téléchargement. C'est aussi un avant-goût de ce que Zone85 produit au-delà du jeu."],
          ['q'=>"C'est quoi Les Invisibles ?",
           'a'=>"Les Invisibles est le prochain jeu immersif de Zone85. Une enquête ancrée dans la Vendée réelle — des lieux, des indices, des personnages à découvrir sur le terrain. Plus d'infos bientôt pour les membres."],
          ['q'=>"C'est quoi Les Échos de la Zone ?",
           'a'=>"Les Échos sont le magazine communautaire de Zone85 : histoires, curiosités et nouvelles du territoire vendéen. Des articles courts, humains et locaux. La lecture comme moteur, pas la réaction."],
          ['q'=>"C'est quoi le Kéto Kolé Tché ?",
           'a'=>"'Kéto Kolé Tché' signifie 'Qu'est-ce que c'est que ça ?' en vendéen populaire. Un jeu de devinettes culturelles : objets mystères, lieux à identifier, expressions vendéennes. Court, fun, local."],
          ['q'=>"Mon XP peut-il disparaître ?",
           'a'=>"Non, jamais. Ton XP personnel est à vie. Chaque participation enrichit ton profil de façon permanente. Seul le score saisonnier du clan repart à zéro entre chaque saison."],
          ['q'=>"Puis-je changer de clan ?",
           'a'=>"Le changement est possible mais réfléchi. Tu peux demander un transfert entre deux saisons. Ton XP perso te suit, mais tu repars à zéro dans la Bataille des Clans de la nouvelle saison."],
          ['q'=>"Zone85 est-il gratuit ?",
           'a'=>"Oui, entièrement. Rejoindre la Zone, participer, accéder à VICTOR, lire les Échos, faire des randos — tout est gratuit. Zone85 est une initiative communautaire pour faire vivre l'Esprit Vendée."],
          ['q'=>"Faut-il habiter en Vendée ?",
           'a'=>"Non. Zone85 accueille les Vendéens de souche, de cœur et d'adoption. Si la Vendée t'appelle, tu as ta place dans la Zone."],
          ['q'=>"En quoi c'est différent de la page Facebook ?",
           'a'=>"Facebook est la place du village : discussions, partages, réactions. Zone85 est le terrain de jeu : missions, randos, Échos, VICTOR, clans. Les deux se complètent — Zone85 rend l'expérience vendéenne interactive et durable."],
      ];
      foreach ($faqs as $faq): ?>
      <div class="cp-faq-item">
        <div class="cp-faq-q" onclick="this.closest('.cp-faq-item').classList.toggle('open')">
          <span class="cp-faq-q-text"><?= e($faq['q']) ?></span>
          <span class="cp-faq-chevron">▾</span>
        </div>
        <div class="cp-faq-a"><?= e($faq['a']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ── CTA ─────────────────────────────────────────────────────── -->
<section style="background:var(--primary);padding:80px 0;position:relative;overflow:hidden">
  <div style="position:absolute;top:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none"></div>
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr auto;gap:48px;align-items:center;position:relative;z-index:1">
      <div>
        <h2 style="font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;color:#fff;margin-bottom:10px;letter-spacing:-.5px">Rejoins Zone85 — et déverrouille VICTOR.</h2>
        <p style="color:rgba(255,255,255,.78);font-size:.93rem;line-height:1.65;max-width:500px">Choisis ton clan, commence à contribuer, télécharge VICTOR en PDF. La Vendée t'attend — avec ses chemins, ses histoires et ses mystères.</p>
      </div>
      <div style="flex-shrink:0">
        <a href="<?= page_url('inscription') ?>" class="btn-white">Rejoindre la Zone →</a>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
