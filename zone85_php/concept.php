<?php
$page_title       = 'Le Concept';
$page_description = 'Comprends comment fonctionne ZONE85 : deux modes de jeu, trois clans, quatre saisons. Je progresse pour moi. Je fais gagner mon clan.';
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
/* ── PAGE HERO BEIGE ── */
.page-hero{background:var(--beige-light);padding:140px 0 80px;border-bottom:1px solid var(--beige-dark)}
.page-hero-inner{max-width:740px;margin:0 auto;padding:0 24px}
.page-hero h1{font-size:clamp(2.2rem,5vw,3.6rem);font-weight:900;color:var(--navy-dark);letter-spacing:-1.5px;line-height:1.1;margin-bottom:16px}
.page-hero h1 em{color:var(--primary);font-style:normal}
.page-hero p{font-size:1.05rem;color:var(--text-mid);line-height:1.75;max-width:600px}

/* ── PHRASE CENTRALE ── */
#phrase{background:var(--navy-dark);padding:64px 0}
.phrase-inner{max-width:800px;margin:0 auto;padding:0 24px;text-align:center}
.phrase-text{font-size:clamp(1.4rem,3vw,2rem);font-weight:900;color:#fff;line-height:1.3;letter-spacing:-.5px;margin-bottom:12px}
.phrase-text em{color:var(--primary);font-style:normal}
.phrase-sub{font-size:.95rem;color:rgba(255,255,255,.55);line-height:1.7}

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
.reset-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:36px}
.reset-card{border-radius:var(--radius);padding:20px}
.reset-card.ok{background:rgba(42,157,92,.08);border:1px solid rgba(42,157,92,.2)}
.reset-card.zero{background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.2)}
.reset-card-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px}
.reset-card.ok .reset-card-title{color:#2a9d5c}
.reset-card.zero .reset-card-title{color:var(--primary)}
.reset-item{font-size:.85rem;color:var(--text-mid);padding:6px 0;border-bottom:1px solid var(--beige-dark);display:flex;align-items:center;gap:8px}
.reset-item:last-child{border-bottom:none}
.reset-card.ok .reset-item::before{content:\'✓\';color:#2a9d5c;font-weight:700}
.reset-card.zero .reset-item::before{content:\'↺\';color:var(--primary);font-weight:700}

/* ── SAISONS TIMELINE ── */
#saisons{background:var(--navy-dark);padding:100px 0}
.season-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:48px}
.season-card{border-radius:var(--radius-lg);padding:28px 20px;text-align:center;border:1px solid rgba(255,255,255,.08)}
.season-card.active{background:rgba(234,86,73,.12);border-color:rgba(234,86,73,.3)}
.season-card:not(.active){background:rgba(255,255,255,.04)}
.season-emoji{font-size:2rem;margin-bottom:12px;display:block}
.season-badge{display:inline-block;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;padding:3px 10px;border-radius:3px;margin-bottom:10px}
.season-card.active .season-badge{background:var(--primary);color:#fff}
.season-card:not(.active) .season-badge{background:rgba(255,255,255,.1);color:rgba(255,255,255,.5)}
.season-name{font-size:1rem;font-weight:800;color:#fff;margin-bottom:4px;line-height:1.2}
.season-period{font-size:.72rem;color:rgba(255,255,255,.45);font-weight:600;margin-bottom:10px}
.season-theme{font-size:.8rem;color:rgba(255,255,255,.6);line-height:1.5;margin-bottom:12px}
.season-mission{font-size:.75rem;font-weight:700;color:var(--primary);background:rgba(234,86,73,.1);padding:6px 12px;border-radius:4px;line-height:1.4}
.season-legend{text-align:center;margin-top:32px;font-size:.82rem;color:rgba(255,255,255,.4)}
.season-legend strong{color:rgba(255,255,255,.7)}

/* ── XP ACTIONS ── */
#xp-actions{background:var(--beige);padding:100px 0 80px}
.xp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:48px}
.xp-card{background:var(--white);border-radius:var(--radius);padding:20px 16px;box-shadow:var(--shadow-sm);text-align:center}
.xp-icon{font-size:1.6rem;margin-bottom:8px}
.xp-action-name{font-size:.88rem;font-weight:800;color:var(--text);margin-bottom:4px}
.xp-range{font-size:.82rem;font-weight:800;color:var(--primary)}
.xp-note{font-size:.7rem;color:var(--text-muted);margin-top:4px;line-height:1.4}

/* ── CODE DE LA ZONE ── */
#code{background:var(--beige-light);padding:100px 0 80px}
.code-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:48px}
.code-card{background:var(--white);border-radius:var(--radius-lg);padding:24px 18px;box-shadow:var(--shadow-sm);border-bottom:3px solid var(--primary)}
.code-num{font-size:.72rem;font-weight:800;color:var(--primary);letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px}
.code-rule{font-size:.92rem;font-weight:800;color:var(--navy-dark);margin-bottom:6px;line-height:1.3}
.code-detail{font-size:.78rem;color:var(--text-mid);line-height:1.5}

/* ── FAQ ── */
#faq{background:var(--navy-dark);padding:100px 0 80px}
.faq-list{max-width:820px;margin:40px auto 0;display:flex;flex-direction:column;gap:10px}
.faq-item{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius)}
.faq-q{padding:18px 22px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:12px}
.faq-q-text{font-size:.95rem;font-weight:700;color:#fff}
.faq-chevron{color:rgba(255,255,255,.4);font-size:1.1rem;transition:transform .25s;flex-shrink:0}
.faq-item.open .faq-chevron{transform:rotate(180deg)}
.faq-a{display:none;padding:0 22px 18px;font-size:.88rem;color:rgba(255,255,255,.6);line-height:1.7}
.faq-item.open .faq-a{display:block}

@media(max-width:1024px){.season-grid{grid-template-columns:repeat(2,1fr)}.xp-grid{grid-template-columns:repeat(2,1fr)}.code-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.modes-grid,.reset-row{grid-template-columns:1fr}.season-grid,.xp-grid,.code-grid{grid-template-columns:1fr}}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- PAGE HERO -->
<section class="page-hero">
  <div class="page-hero-inner">
    <span class="eyebrow-tag">Le Concept</span>
    <h1>Zone 85, <em>c'est quoi</em><br>exactement ?</h1>
    <p>Un terrain de jeu vendéen, branché sur la communauté, où chaque interaction devient une petite aventure. On ne crée pas un réseau social. On crée un jeu collectif ancré dans le territoire.</p>
  </div>
</section>

<!-- PHRASE CENTRALE -->
<section id="phrase">
  <div class="phrase-inner">
    <div class="phrase-text">"La Vendée qui joue, qui enquête, qui marche,<br><em>qui se raconte et qui se chambre gentiment.</em>"</div>
    <p class="phrase-sub">Zone 85, c'est la Vendée en version participative. Chaque membre progresse à son rythme, tout en faisant grandir son clan dans une compétition saisonnière amicale.</p>
  </div>
</section>

<!-- LES 2 MODES -->
<section id="modes">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">Les 2 dimensions</span>
      <h2 class="section-title">Un jeu à deux niveaux</h2>
      <p class="section-sub">Zone 85 fonctionne sur deux modes complémentaires. Les deux se nourrissent, mais ils fonctionnent indépendamment.</p>
    </div>
    <div class="modes-grid">
      <div class="mode-card orange reveal">
        <div class="mode-icon">⚡</div>
        <div class="mode-title">Mode personnel</div>
        <p class="mode-desc">Ton profil, ta légende. L'XP que tu accumules est à toi pour toujours. Aucune saison ne peut te l'enlever.</p>
        <div class="mode-points">
          <div class="mode-point">Quiz, photos, randos, votes, Kéto Kolé Tché</div>
          <div class="mode-point">Chaque action rapporte des XP personnels</div>
          <div class="mode-point">Tu montes de niveau et débloque des badges</div>
          <div class="mode-point">Ton XP est <strong>permanent</strong> — à vie, jamais remis à zéro</div>
        </div>
      </div>
      <div class="mode-card navy reveal" style="transition-delay:.1s">
        <div class="mode-icon">🛡️</div>
        <div class="mode-title">Mode collectif</div>
        <p class="mode-desc">La Bataille des Clans. 4 saisons par an, 1 grande mission collective par saison. Les clans s'affrontent. Le vainqueur entre dans la légende.</p>
        <div class="mode-points">
          <div class="mode-point">4 saisons par an — 1 grande mission par saison</div>
          <div class="mode-point">Les 3 clans s'affrontent dans la Bataille des Clans</div>
          <div class="mode-point">Le score de clan repart à zéro chaque saison</div>
          <div class="mode-point">Le clan gagnant reçoit un trophée archivé pour toujours</div>
        </div>
      </div>
    </div>

    <div class="reset-row reveal" style="transition-delay:.2s">
      <div class="reset-card ok">
        <div class="reset-card-title">Ce qui ne revient jamais à zéro</div>
        <div class="reset-item">Ton XP personnel</div>
        <div class="reset-item">Ton niveau</div>
        <div class="reset-item">Tes badges</div>
        <div class="reset-item">Ton historique de participations</div>
        <div class="reset-item">Les trophées archivés des clans</div>
      </div>
      <div class="reset-card zero">
        <div class="reset-card-title">Ce qui repart à zéro chaque saison</div>
        <div class="reset-item">Le score saisonnier de chaque clan</div>
        <div class="reset-item">Le classement collectif de la Bataille</div>
        <div class="reset-item">La grande mission collective</div>
      </div>
    </div>

    <div style="text-align:center;margin-top:36px;padding:20px 28px;background:var(--beige-dark);border-radius:var(--radius);font-size:1.1rem;font-weight:800;color:var(--navy-dark)" class="reveal">
      Le clan gagne la saison. <span style="color:var(--primary)">Le joueur construit sa légende.</span>
    </div>
  </div>
</section>

<!-- LES 4 SAISONS -->
<section id="saisons">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag-white">Le calendrier</span>
      <h2 class="section-title" style="color:#fff">Les 4 saisons de la Zone</h2>
      <p class="section-sub" style="color:rgba(255,255,255,.6)">Moins de missions collectives, mais plus d'impact. 4 saisons par an. 1 grande mission par saison. 1 trophée archivé.</p>
    </div>
    <div class="season-grid">
      <?php
      $season_emojis  = ['🌱', '☀️', '🍂', '🕯️'];
      $season_themes  = [
          'Retour dehors. Nature, villages, chemins, biodiversité vendéenne.',
          'Soleil, littoral, vacances, humour, météo fun et esprit estival.',
          'Bocage, brume, patrimoine, villages, mémoire et chemins cachés.',
          'Objets anciens, souvenirs, expressions, récits et enquêtes de l\'hiver.',
      ];
      $season_missions_labels = [
          'La Grande Remise en Route',
          'Le Grand Défi de l\'Été',
          'La Traversée des Clans',
          'Le Grand Kéto Kolé Tché',
      ];
      $season_delays = [0, .1, .2, .3];

      foreach ($seasons as $i => $season):
        $is_active   = ($season['status'] === 'active');
        $badge_label = $is_active ? 'En cours' : 'Saison ' . ($i + 1);
        $delay_style = $season_delays[$i] > 0 ? ' style="transition-delay:' . $season_delays[$i] . 's"' : '';
        $mission_label = $season['main_mission'] ?? $season_missions_labels[$i];
      ?>
      <div class="season-card<?= $is_active ? ' active' : '' ?> reveal"<?= $delay_style ?>>
        <span class="season-emoji"><?= $season_emojis[$i] ?></span>
        <div class="season-badge"><?= e($badge_label) ?></div>
        <div class="season-name"><?= e($season['title']) ?></div>
        <div class="season-period"><?= e(ucfirst($season['period'])) ?></div>
        <div class="season-theme"><?= e($season_themes[$i]) ?></div>
        <div class="season-mission"><?= e($mission_label) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="season-legend reveal" style="transition-delay:.4s">
      <strong>4 saisons · 4 grandes missions collectives · 4 trophées par an</strong><br>
      Des actions personnelles disponibles toute l'année.
    </div>
  </div>
</section>

<!-- COMMENT GAGNER DES XP -->
<section id="xp-actions">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">XP Personnel</span>
      <h2 class="section-title">Comment gagner des XP ?</h2>
      <p class="section-sub">Des actions simples, variées, accessibles à tout le monde. Pas besoin d'être un expert — juste de participer à ton rythme.</p>
    </div>
    <div class="xp-grid">
      <div class="xp-card reveal">
        <div class="xp-icon">🎯</div>
        <div class="xp-action-name">Quiz</div>
        <div class="xp-range">+20 à +60 XP</div>
        <div class="xp-note">Histoire, nature, folklore, spécialités vendéennes</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.05s">
        <div class="xp-icon">📸</div>
        <div class="xp-action-name">Défi photo</div>
        <div class="xp-range">+25 à +80 XP</div>
        <div class="xp-note">Capturez la Vendée telle que vous la vivez</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.1s">
        <div class="xp-icon">🥾</div>
        <div class="xp-action-name">Avis de rando</div>
        <div class="xp-range">+30 à +50 XP</div>
        <div class="xp-note">Sentiers, GR, chemins — partagez votre expérience</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.15s">
        <div class="xp-icon">🔍</div>
        <div class="xp-action-name">Kéto Kolé Tché</div>
        <div class="xp-range">+40 à +100 XP</div>
        <div class="xp-note">Identifiez objets, lieux et expressions vendéennes mystères</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.2s">
        <div class="xp-icon">🌦️</div>
        <div class="xp-action-name">Météo-mission</div>
        <div class="xp-range">+15 à +40 XP</div>
        <div class="xp-note">La météo ouvre des mini-missions spéciales</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.25s">
        <div class="xp-icon">🗳️</div>
        <div class="xp-action-name">Vote</div>
        <div class="xp-range">+5 à +15 XP</div>
        <div class="xp-note">Votez pour les meilleures contributions de la semaine</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.3s">
        <div class="xp-icon">💬</div>
        <div class="xp-action-name">Commentaire</div>
        <div class="xp-range">+5 à +15 XP</div>
        <div class="xp-note">Réagissez, encouragez, débattez — avec le sourire</div>
      </div>
      <div class="xp-card reveal" style="transition-delay:.35s">
        <div class="xp-icon">🛡️</div>
        <div class="xp-action-name">Mission de saison</div>
        <div class="xp-range">+50 à +200 XP</div>
        <div class="xp-note">La grande mission collective rapporte aussi de l'XP perso</div>
      </div>
    </div>
    <p style="text-align:center;margin-top:24px;font-size:.82rem;color:var(--text-muted)" class="reveal">L'XP personnel ne revient jamais à zéro. Chaque participation compte pour toujours.</p>
  </div>
</section>

<!-- CODE DE LA ZONE -->
<section id="code">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag">Code de la Zone</span>
      <h2 class="section-title">Les 5 règles de l'Esprit Vendée</h2>
      <p class="section-sub">Entrer en Zone85, tu pourras… mais d'abord, quelques vérités vendéennes tu accepteras.</p>
    </div>
    <div class="code-grid">
      <div class="code-card reveal">
        <div class="code-num">Règle 01</div>
        <div class="code-rule">Tu participes avec bonne humeur</div>
        <div class="code-detail">La Zone, c'est une ambiance. Pas un concours de sérieux. La brioche se mange avec le sourire.</div>
      </div>
      <div class="code-card reveal" style="transition-delay:.05s">
        <div class="code-num">Règle 02</div>
        <div class="code-rule">Tu chambres sans être lourd</div>
        <div class="code-detail">Rivaliser entre clans, oui. Se respecter, toujours. Le terrain de jeu reste convivial.</div>
      </div>
      <div class="code-card reveal" style="transition-delay:.1s">
        <div class="code-num">Règle 03</div>
        <div class="code-rule">Tu respectes les lieux</div>
        <div class="code-detail">Chaque mission te fait découvrir la Vendée. Ce patrimoine, c'est aussi le nôtre à tous.</div>
      </div>
      <div class="code-card reveal" style="transition-delay:.15s">
        <div class="code-num">Règle 04</div>
        <div class="code-rule">Tu ne triches pas pour une mogette</div>
        <div class="code-detail">Même pour gagner des trophées. Le jeu n'a de valeur que si tout le monde joue vraiment.</div>
      </div>
      <div class="code-card reveal" style="transition-delay:.2s;grid-column:span 2">
        <div class="code-num">Règle 05</div>
        <div class="code-rule">Tu joues pour toi et pour ton clan</div>
        <div class="code-detail">Ta progression personnelle et la victoire de ton clan sont les deux faces d'une même pièce. Les deux comptent. Toujours.</div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section id="faq">
  <div class="container">
    <div class="section-head reveal">
      <span class="eyebrow-tag-white">Questions fréquentes</span>
      <h2 class="section-title" style="color:#fff">Ce que tu te demandes peut-être</h2>
    </div>
    <div class="faq-list">
      <?php
      $faqs = [
          [
              'q' => 'Est-ce que mon XP peut disparaître ?',
              'a' => 'Non, jamais. Ton XP personnel est à vie. Chaque participation enrichit ton profil de façon permanente. Seul le score saisonnier du clan repart à zéro entre chaque saison.',
          ],
          [
              'q' => 'Puis-je changer de clan ?',
              'a' => 'Le changement de clan est possible, mais réfléchi. Tu peux demander un transfert entre deux saisons. Ton XP perso te suit, mais tu repars à zéro dans la Bataille des Clans de la nouvelle saison.',
          ],
          [
              'q' => 'Combien de missions collectives par saison ?',
              'a' => 'Une seule grande mission collective par saison. Cette règle est centrale : moins de missions collectives, mais plus d\'impact. Les actions personnelles (quiz, photos, randos…) sont disponibles toute l\'année.',
          ],
          [
              'q' => 'Zone 85 est-il gratuit ?',
              'a' => 'Oui, entièrement. Rejoindre la Zone, participer, gagner des XP, accéder au Hall — tout est gratuit. Zone 85 est une initiative communautaire pour faire vivre l\'Esprit Vendée.',
          ],
          [
              'q' => 'Que se passe-t-il quand une saison se termine ?',
              'a' => 'Le clan en tête remporte un trophée archivé dans la Bibliothèque des Trophées. Les scores de saison des clans sont remis à zéro. La nouvelle saison commence avec une nouvelle grande mission collective. Vos XP personnels, eux, restent intacts.',
          ],
          [
              'q' => 'Faut-il habiter en Vendée pour participer ?',
              'a' => 'Non. Zone 85 accueille les Vendéens de souche, de cœur, et d\'adoption. Si la Vendée t\'appelle, tu as ta place dans la Zone. Chacun a sa porte d\'entrée dans l\'Esprit Vendée.',
          ],
      ];
      foreach ($faqs as $faq):
      ?>
      <div class="faq-item">
        <div class="faq-q" onclick="toggleFaq(this)">
          <span class="faq-q-text"><?= e($faq['q']) ?></span>
          <span class="faq-chevron">▾</span>
        </div>
        <div class="faq-a"><?= e($faq['a']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--primary);padding:80px 0;position:relative;overflow:hidden">
  <div style="position:absolute;top:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.06)"></div>
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr auto;gap:48px;align-items:center;position:relative;z-index:1">
      <div>
        <h2 style="font-size:clamp(1.6rem,3vw,2.4rem);font-weight:900;color:#fff;margin-bottom:12px;letter-spacing:-.5px">Prêt.e à rejoindre la Zone ?</h2>
        <p style="color:rgba(255,255,255,.8);font-size:.95rem;line-height:1.65;max-width:480px">En 6 étapes, tu choisis ton clan, tu acceptes le Code de la Zone, et tu reçois tes 50 XP de bienvenue. Ta légende commence maintenant.</p>
      </div>
      <div style="flex-shrink:0">
        <a href="<?= page_url('inscription') ?>" class="btn-white">Rejoindre la Zone →</a>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
