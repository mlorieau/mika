<?php
$page_title       = 'Le Concept — Zone85';
$page_description = 'Zone85 : ce que c\'est vraiment, pourquoi créer un compte, ce que sont les clans, et comment l\'installer en un clic sur votre téléphone.';
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

/* ── Section 1 : Iceberg ── */
.cp-iceberg-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.cp-iceberg-text p{font-size:.97rem;color:var(--text-muted);line-height:1.8;margin-bottom:16px}
.cp-iceberg-text p strong{color:var(--text)}
.cp-iceberg-quote{background:linear-gradient(145deg,#0c1e2e,#12314e);border-radius:16px;padding:40px 36px;position:relative;overflow:hidden}
.cp-iceberg-quote::before{content:"";position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:rgba(234,86,73,.07);pointer-events:none}
.cp-iceberg-quote blockquote{font-size:1.15rem;font-weight:800;color:#fff;line-height:1.45;letter-spacing:-.3px;margin:0 0 20px;position:relative;z-index:1}
.cp-iceberg-quote blockquote::before{content:"\201C";font-size:3rem;color:rgba(234,86,73,.4);line-height:0;vertical-align:-1rem;margin-right:4px}
.cp-iceberg-caption{font-size:.78rem;color:rgba(255,255,255,.45);font-style:italic;position:relative;z-index:1}
.cp-iceberg-examples{margin-top:28px;display:flex;flex-direction:column;gap:10px}
.cp-iceberg-example{display:grid;grid-template-columns:1fr 24px 1fr;align-items:center;gap:8px;font-size:.83rem}
.cp-iceberg-example-social{color:var(--text-muted);background:var(--beige);border-radius:6px;padding:8px 12px;text-align:right}
.cp-iceberg-example-arrow{color:var(--primary);font-weight:900;text-align:center}
.cp-iceberg-example-zone{color:var(--text);background:rgba(234,86,73,.06);border:1px solid rgba(234,86,73,.15);border-radius:6px;padding:8px 12px}

/* ── Section 2 : Raisons cartes ── */
.cp-reasons{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.cp-reason{background:var(--white);border-radius:14px;padding:28px 24px;border:1.5px solid var(--beige-dark);border-top:4px solid var(--primary)}
.cp-reason-icon{font-size:2rem;margin-bottom:14px;display:block}
.cp-reason-title{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:8px}
.cp-reason-desc{font-size:.87rem;color:var(--text-muted);line-height:1.65}
.cp-free-note{margin-top:32px;text-align:center;font-size:.9rem;color:var(--text-muted);font-style:italic}
.cp-free-note strong{color:var(--text)}

/* ── Section 3 : Clans ── */
.cp-clan-intro{font-size:1.05rem;color:rgba(255,255,255,.72);line-height:1.85;max-width:780px;margin:0 auto 48px}
.cp-clan-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:40px}
.cp-clan-card{border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);padding:28px 22px;text-align:center;transition:transform .25s,box-shadow .25s}
.cp-clan-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.3)}
.cp-clan-mascot{width:80px;height:80px;object-fit:contain;margin:0 auto 14px;display:block}
.cp-clan-name{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.45);margin-bottom:4px}
.cp-clan-title{font-size:1rem;font-weight:900;color:#fff;margin-bottom:8px;line-height:1.2}
.cp-clan-tagline{font-size:.82rem;color:rgba(255,255,255,.5);line-height:1.5}
.cp-clan-cta{text-align:center}

/* ── Section 4 : PWA ── */
.cp-simple-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:40px;margin-bottom:32px}
.cp-step{text-align:center;position:relative}
.cp-step-num{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;font-size:.85rem;font-weight:900;margin:0 auto 14px}
.cp-step-title{font-size:.95rem;font-weight:800;color:var(--text);margin-bottom:6px}
.cp-step-desc{font-size:.83rem;color:var(--text-muted);line-height:1.6}
.cp-step-connector{position:absolute;top:22px;left:calc(50% + 22px);width:calc(100% - 44px);height:2px;background:var(--beige-dark)}
.cp-step:last-child .cp-step-connector{display:none}
.cp-fun-note{font-size:.88rem;color:var(--text-muted);font-style:italic;text-align:center;margin-top:8px}

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
.cp-pillar-header-missions{background:linear-gradient(135deg,#1a0010,#2e0a1a)}
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
  .cp-pillars{grid-template-columns:repeat(2,1fr)}
  .cp-clan-strip{grid-template-columns:1fr}
  .cp-iceberg-grid{grid-template-columns:1fr}
}
@media(max-width:768px){
  .cp-pillars,.cp-reasons,.cp-simple-steps{grid-template-columns:1fr}
  .cp-section,.cp-section-navy{padding:64px 0}
  .cp-step-connector{display:none}
  .cp-iceberg-grid{gap:28px}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ── HERO ─────────────────────────────────────────────────── -->
<section class="page-hero dark">
  <div class="container">
    <div class="page-hero-inner">
      <div class="page-eyebrow">Le Concept</div>
      <h1 class="page-h1">Zone85 — pourquoi, comment, pour qui.</h1>
      <p class="page-sub">Ce que c'est vraiment. Ce que ce n'est pas. Et pourquoi vous allez y rester.</p>
    </div>
  </div>
</section>


<!-- ── SECTION 1 : C'EST QUOI ZONE85 ? ────────────────────── -->
<section class="page-section-white" style="border-top:2px solid var(--beige-dark)">
  <div class="container">
    <div class="cp-head">
      <div class="cp-eyebrow">C'est quoi Zone85 ?</div>
      <h2 class="cp-title">Ce n'est pas un réseau social.</h2>
      <p class="cp-sub">Zone85 est un prolongement naturel de ce que vous vivez déjà sur Facebook ou Instagram — mais on entre dans la profondeur.</p>
    </div>
    <div class="cp-iceberg-grid">
      <div class="cp-iceberg-text">
        <p>Les réseaux sociaux <strong>montrent</strong> la Vendée. Zone85 la <strong>fait vivre</strong>. Ce n'est pas un concurrent — c'est un prolongement. La surface reste là où elle est. On ajoute ce qui est en dessous.</p>
        <p>Vous avez vu une photo de rando vendéenne sur Facebook ? Sur Zone85, vous la <strong>marchez</strong>. Vous avez partagé un article sur un château ? Sur Zone85, vous lisez <strong>l'histoire vraie</strong> derrière. Quelqu'un a posté un objet mystère ? Sur Zone85, vous jouez à le trouver.</p>
        <p>On ne remplace pas la place publique. On construit ce qu'il y a en dessous.</p>
        <div class="cp-iceberg-examples">
          <div class="cp-iceberg-example">
            <div class="cp-iceberg-example-social">Photo de rando sur Instagram</div>
            <div class="cp-iceberg-example-arrow">→</div>
            <div class="cp-iceberg-example-zone">Vous marchez le sentier, gagnez des XP</div>
          </div>
          <div class="cp-iceberg-example">
            <div class="cp-iceberg-example-social">Article partagé sur Facebook</div>
            <div class="cp-iceberg-example-arrow">→</div>
            <div class="cp-iceberg-example-zone">Vous creusez l'histoire vraie derrière</div>
          </div>
          <div class="cp-iceberg-example">
            <div class="cp-iceberg-example-social">Objet mystère posté en story</div>
            <div class="cp-iceberg-example-arrow">→</div>
            <div class="cp-iceberg-example-zone">Vous jouez au Kéto Kolé Tché</div>
          </div>
        </div>
      </div>
      <div class="cp-iceberg-quote">
        <blockquote>Les réseaux sociaux, c'est la pointe de l'iceberg. Zone85, c'est ce qu'il y a en dessous.</blockquote>
        <p class="cp-iceberg-caption">Zone85 n'est pas là pour remplacer Facebook ou Instagram. Il est là pour ajouter la profondeur que ces plateformes n'ont pas.</p>
      </div>
    </div>
  </div>
</section>


<!-- ── SECTION 2 : POURQUOI CRÉER UN COMPTE ? ─────────────── -->
<section class="page-section">
  <div class="container">
    <div class="cp-head">
      <div class="cp-eyebrow">Pourquoi créer un compte ?</div>
      <h2 class="cp-title">Ce que ça déverrouille.</h2>
      <p class="cp-sub">L'inscription prend 30 secondes. Et elle ouvre quatre choses que vous ne trouverez nulle part ailleurs.</p>
    </div>
    <div class="cp-reasons">
      <div class="cp-reason">
        <span class="cp-reason-icon">📖</span>
        <div class="cp-reason-title">VICTOR — le livre PDF de Zone85</div>
        <p class="cp-reason-desc">Une histoire vendéenne — personnages, territoire, mémoire. Téléchargeable dès l'inscription, offert à tous les membres. C'est votre première récompense pour avoir rejoint la Zone.</p>
      </div>
      <div class="cp-reason">
        <span class="cp-reason-icon">🎯</span>
        <div class="cp-reason-title">Les missions vendéennes</div>
        <p class="cp-reason-desc">Des défis locaux : quiz, randos, photos de terrain, Kéto Kolé Tché. Chaque mission validée rapporte des XP — votre progression personnelle, permanente, à vie.</p>
      </div>
      <div class="cp-reason">
        <span class="cp-reason-icon">⚔️</span>
        <div class="cp-reason-title">Un clan — une appartenance</div>
        <p class="cp-reason-desc">Bocage, Littoral ou Marais — une identité territoriale. Vous contribuez pour votre clan, vous faites avancer tout le monde. Pas une guerre, une émulation.</p>
      </div>
      <div class="cp-reason">
        <span class="cp-reason-icon">🎭</span>
        <div class="cp-reason-title">Les Invisibles — bientôt</div>
        <p class="cp-reason-desc">Le prochain jeu immersif de Zone85 s'ancre dans la Vendée réelle. Les membres seront les premiers prévenus — et les premiers à jouer.</p>
      </div>
    </div>
  </div>
</section>


<!-- ── SECTION 3 : LES CLANS — C'EST UNE GUERRE ? ─────────── -->
<section class="cp-section-navy">
  <div class="container">
    <div class="cp-head" style="text-align:center;margin-bottom:32px">
      <div class="cp-eyebrow">Les clans — c'est une guerre ?</div>
      <h2 class="cp-title">Non. C'est une appartenance.</h2>
    </div>
    <p class="cp-clan-intro">Bocage, Littoral, Marais — ce sont trois façons d'être vendéen. Pas trois armées. Quand vous choisissez un clan, vous choisissez une <strong style="color:#fff">identité territoriale</strong>, pas un camp. Vous pouvez admirer les deux autres clans — et quand même porter fièrement votre bocage, votre côte ou vos marais.<br><br>Le créateur de Zone85 lui-même aime "voir tout le monde avancer". La Bataille des Clans, c'est une <strong style="color:#fff">émulation collective</strong> — pas une guerre. Comme choisir sa région de cœur : on aime les trois, on appartient à l'une.</p>
    <div class="cp-clan-strip">
      <?php
      $clan_taglines = [
          'bocage'   => 'Les chemins creux, les haies centenaires. Tenaces, discrets, enracinés.',
          'littoral' => 'Le sel dans les veines, l\'horizon dans les yeux. Audacieux, curieux, ouverts.',
          'marais'   => 'La patience des eaux profondes. Observateurs, gardiens des secrets vendéens.',
      ];
      foreach ($clans as $slug => $clan):
      ?>
      <div class="cp-clan-card">
        <img
          src="<?= img($clan['mascot']) ?>"
          alt="Mascotte <?= e($clan['name']) ?>"
          class="cp-clan-mascot"
          loading="lazy"
        >
        <div class="cp-clan-name"><?= e($clan['label']) ?></div>
        <div class="cp-clan-title"><?= e($clan['name']) ?></div>
        <div class="cp-clan-tagline"><?= e($clan_taglines[$slug] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="cp-clan-cta">
      <a href="<?= page_url('inscription') ?>" class="btn btn-primary">Je choisis mon clan →</a>
    </div>
  </div>
</section>


<!-- ── SECTION 4 : AUSSI SIMPLE QUE FACEBOOK ─────────────── -->
<section class="page-section-white">
  <div class="container">
    <div class="cp-head">
      <div class="cp-eyebrow">Comment c'est simple d'accès ?</div>
      <h2 class="cp-title">L'icône sur votre téléphone.</h2>
      <p class="cp-sub">On entend parfois "oui mais c'est compliqué, faut taper l'adresse..." — non. Zone85 est une PWA : vous l'installez comme une app, sans passer par l'App Store.</p>
    </div>
    <div class="cp-simple-steps">
      <div class="cp-step">
        <div class="cp-step-num">1</div>
        <div class="cp-step-title">Inscrivez-vous (30s)</div>
        <p class="cp-step-desc">Un pseudo, un email, un mot de passe. Votre compte est créé, VICTOR est débloqué, votre clan vous attend.</p>
        <div class="cp-step-connector"></div>
      </div>
      <div class="cp-step">
        <div class="cp-step-num">2</div>
        <div class="cp-step-title">Installez l'icône</div>
        <p class="cp-step-desc">À l'inscription, installez l'icône Zone85 sur votre écran d'accueil — juste à côté de Facebook ou d'Instagram. Un bouton, c'est tout.</p>
        <div class="cp-step-connector"></div>
      </div>
      <div class="cp-step">
        <div class="cp-step-num">3</div>
        <div class="cp-step-title">Accédez en un clic</div>
        <p class="cp-step-desc">Un clic sur l'icône et vous êtes dans la Zone. Avec des notifications si vous le souhaitez, comme n'importe quelle app.</p>
        <div class="cp-step-connector"></div>
      </div>
    </div>
    <p class="cp-fun-note">Et si un jour les GAFAs décident de bannir la Vendée des réseaux sociaux — on aura toujours notre chez nous. 😏</p>
  </div>
</section>


<!-- ── SECTION 5 : CE QU'ON FAIT SUR ZONE85 ──────────────── -->
<section class="page-section">
  <div class="container">
    <div class="cp-head">
      <div class="cp-eyebrow">Ce qu'on fait sur Zone85</div>
      <h2 class="cp-title">Six façons de vivre la Vendée</h2>
      <p class="cp-sub">Le contenu est la raison d'être. Le jeu est l'ambiance. Voici ce que Zone85 produit, rassemble et fait vivre.</p>
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
          <p class="cp-pillar-desc">Un livre PDF offert à tous les membres de Zone85. Une histoire vendéenne — personnages, territoire, mémoire. Votre première récompense dès l'inscription.</p>
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

      <!-- Missions -->
      <div class="cp-pillar">
        <div class="cp-pillar-header cp-pillar-header-missions">
          <span class="cp-pillar-icon">🎯</span>
          <div class="cp-pillar-name">Communauté</div>
          <div class="cp-pillar-title">Les Missions</div>
        </div>
        <div class="cp-pillar-body">
          <p class="cp-pillar-desc">Des défis ponctuels et saisonniers : photo de terrain, quiz, enquête, défi météo. Chaque participation rapporte des XP et des points pour votre clan.</p>
          <a href="missions.php" class="cp-pillar-link">Voir les missions →</a>
        </div>
      </div>

    </div>
  </div>
</section>



<!-- ── SECTION HISTOIRE ──────────────────────────────────────── -->
<section class="page-section-white">
  <div class="container">
    <div class="cp-head" style="max-width:720px">
      <div class="cp-eyebrow">L'histoire</div>
      <h2 class="cp-title">Comment tout a commencé</h2>
      <p class="cp-sub">Dix ans de Vendée partagée, construite brique à brique par des gens qui la vivent vraiment.</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:0;max-width:680px">

      <div style="display:grid;grid-template-columns:80px 1fr;gap:0 28px;padding-bottom:40px;position:relative">
        <div style="text-align:right">
          <div style="font-size:1.2rem;font-weight:900;color:var(--primary);line-height:1">2015</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600;margin-top:4px">Le début</div>
        </div>
        <div style="border-left:2px solid var(--beige-dark);padding-left:28px;padding-bottom:8px">
          <div style="width:10px;height:10px;border-radius:50%;background:var(--primary);position:absolute;left:80px;transform:translate(-3px,6px)"></div>
          <h3 style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">Le premier projet</h3>
          <p style="font-size:.88rem;color:var(--text-muted);line-height:1.72">Tout commence avec une idée simple : parler de la Vendée comme on la vit, pas comme on la présente dans les brochures. Audrey et Mickaël posent les premières briques d'un site vivant, décalé, ancré dans le quotidien vendéen.</p>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:80px 1fr;gap:0 28px;padding-bottom:40px;position:relative">
        <div style="text-align:right">
          <div style="font-size:1.2rem;font-weight:900;color:var(--primary);line-height:1">2018</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600;margin-top:4px">La communauté</div>
        </div>
        <div style="border-left:2px solid var(--beige-dark);padding-left:28px;padding-bottom:8px">
          <div style="width:10px;height:10px;border-radius:50%;background:var(--primary);position:absolute;left:80px;transform:translate(-3px,6px)"></div>
          <h3 style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">La communauté prend forme</h3>
          <p style="font-size:.88rem;color:var(--text-muted);line-height:1.72">Les lecteurs deviennent acteurs. Les premiers Zonautes rejoignent la Zone, participent aux enquêtes, commentent les rubriques. La Bataille des Clans naît de ce besoin de jeu collectif : Bocage, Littoral ou Marais — chacun défend ses couleurs.</p>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:80px 1fr;gap:0 28px;padding-bottom:40px;position:relative">
        <div style="text-align:right">
          <div style="font-size:1.2rem;font-weight:900;color:var(--primary);line-height:1">2021</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600;margin-top:4px">Le terrain</div>
        </div>
        <div style="border-left:2px solid var(--beige-dark);padding-left:28px;padding-bottom:8px">
          <div style="width:10px;height:10px;border-radius:50%;background:var(--primary);position:absolute;left:80px;transform:translate(-3px,6px)"></div>
          <h3 style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">RandoZone &amp; les aventures du terrain</h3>
          <p style="font-size:.88rem;color:var(--text-muted);line-height:1.72">La Vendée se parcourt à pied. RandoZone naît pour raconter les sentiers, les chemins de halage, les panoramas côtiers et les forêts du bocage. Les Zonautes partagent leurs itinéraires, leurs photos, leurs coups de cœur.</p>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:80px 1fr;gap:0 28px;position:relative">
        <div style="text-align:right">
          <div style="font-size:1.2rem;font-weight:900;color:var(--primary);line-height:1">2024</div>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:600;margin-top:4px">L'équipe</div>
        </div>
        <div style="border-left:2px solid var(--beige-dark);padding-left:28px">
          <div style="width:10px;height:10px;border-radius:50%;background:var(--primary);position:absolute;left:80px;transform:translate(-3px,6px)"></div>
          <h3 style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">Jessica rejoint l'équipe</h3>
          <p style="font-size:.88rem;color:var(--text-muted);line-height:1.72">L'aventure s'agrandit. Jessica apporte son regard, sa plume et son énergie à la Zone. L'équipe à trois têtes se retrousse les manches pour la prochaine décennie.</p>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ── SECTION PHILOSOPHIE ───────────────────────────────────── -->
<section class="page-section" style="background:var(--beige)">
  <div class="container">
    <div class="cp-head" style="text-align:center">
      <div class="cp-eyebrow">Notre philosophie</div>
      <h2 class="cp-title">On voulait parler de la Vendée comme on la vit</h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;max-width:800px;margin:0 auto">

      <div style="background:#fff;border-radius:14px;padding:28px 24px;border:1.5px solid var(--beige-dark)">
        <div style="font-size:1.6rem;margin-bottom:12px">❤️</div>
        <div style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">Avec passion</div>
        <p style="font-size:.86rem;color:var(--text-muted);line-height:1.65">Zone85 n'est pas un média. C'est un projet de cœur, fait par des gens qui aiment vraiment leur département.</p>
      </div>

      <div style="background:#fff;border-radius:14px;padding:28px 24px;border:1.5px solid var(--beige-dark)">
        <div style="font-size:1.6rem;margin-bottom:12px">😄</div>
        <div style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">Avec humour</div>
        <p style="font-size:.86rem;color:var(--text-muted);line-height:1.65">La Vendée se prend au sérieux mais sait rire d'elle-même. On cultive cet équilibre : fierté et autodérision.</p>
      </div>

      <div style="background:#fff;border-radius:14px;padding:28px 24px;border:1.5px solid var(--beige-dark)">
        <div style="font-size:1.6rem;margin-bottom:12px">🛡️</div>
        <div style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">En communauté</div>
        <p style="font-size:.86rem;color:var(--text-muted);line-height:1.65">La Zone vit grâce aux Zonautes. Chaque profil, chaque participation, chaque échange nourrit le projet.</p>
      </div>

      <div style="background:#fff;border-radius:14px;padding:28px 24px;border:1.5px solid var(--beige-dark)">
        <div style="font-size:1.6rem;margin-bottom:12px">🎯</div>
        <div style="font-size:.97rem;font-weight:800;color:var(--text);margin-bottom:8px">Sans chichis</div>
        <p style="font-size:.86rem;color:var(--text-muted);line-height:1.65">Pas de jargon, pas de discours institutionnel. On parle vendéen, on pense local, on publie ce qu'on aime.</p>
      </div>

    </div>
  </div>
</section>


<!-- ── SECTION CODE DE LA ZONE ───────────────────────────────── -->
<section class="cp-section-navy">
  <div class="container">
    <div class="cp-head" style="text-align:center;margin-bottom:40px">
      <div class="cp-eyebrow">Le code de la Zone</div>
      <h2 class="cp-title">7 règles pour vivre la Zone</h2>
      <p class="cp-sub" style="margin:0 auto;text-align:center">Avant de rejoindre ton clan, prends deux minutes pour comprendre l'esprit maison : ici, on joue, on chambre, on partage, mais on garde toujours le cœur vendéen au bon endroit.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;max-width:860px;margin:0 auto">

      <?php
      $zone_rules = [
          ['num'=>1,'icon'=>'🥐','rule'=>'Arpenter la Zone85 sans brioche pur beurre tu ne devras.','desc'=>'On débarque doucement, on observe, on comprend les rubriques, les clans et les codes. La Zone85, ça se savoure comme une bonne gâche : pas en courant, pas en écrasant les autres.'],
          ['num'=>2,'icon'=>'☀️','rule'=>'Du soleil sur la plage, tes fesses tu protégeras.','desc'=>'On peut rire, taquiner, sortir les tongs et parler météo, mais on garde le bon sens vendéen : prudence, respect et pas de fanfaronnade inutile.'],
          ['num'=>3,'icon'=>'💨','rule'=>'Au Mont des Alouettes, la force du vent tu combattras.','desc'=>'Les défis ne se gagnent pas toujours du premier coup. Ici, on insiste, on cherche, on recommence. Un vrai Zonaute ne lâche pas au premier coup de vent.'],
          ['num'=>4,'icon'=>'🫘','rule'=>'D\'une mogette ingérée, le gaz tu maîtriseras.','desc'=>"L'humour est bienvenu, le bazar non. On accepte la rigolade, le patois, les blagues de comptoir, mais on évite de polluer les échanges."],
          ['num'=>5,'icon'=>'🥨','rule'=>'Galocher après le préfou, tu éviteras.','desc'=>'On garde une ambiance propre. Pas de lourdeur, pas de drague chelou, pas d\'insistance. La Zone85 doit rester un endroit où tout le monde se sent bien.'],
          ['num'=>6,'icon'=>'🌊','rule'=>'Les vagues de l\'Atlantique, tu affronteras.','desc'=>'Il y aura des énigmes, des jeux, des classements et des surprises. On participe avec panache, fair-play et un peu de sel dans les veines.'],
          ['num'=>7,'icon'=>'❤️','rule'=>'Dans ton cœur, Vendéen tu seras.','desc'=>"Que tu sois du Littoral, du Bocage, du Marais ou d'ailleurs, l'essentiel est là : aimer la Vendée, la faire vivre, la partager et respecter ceux qui la racontent avec toi."],
      ];
      foreach ($zone_rules as $r):
      ?>
      <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:12px;padding:20px 22px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
          <span style="font-size:1.1rem"><?= $r['icon'] ?></span>
          <span style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.35)">Leçon <?= $r['num'] ?></span>
        </div>
        <p style="font-size:.88rem;font-weight:700;color:#fff;line-height:1.45;margin-bottom:8px;font-style:italic">&ldquo;<?= e($r['rule']) ?>&rdquo;</p>
        <p style="font-size:.82rem;color:rgba(255,255,255,.5);line-height:1.65"><?= e($r['desc']) ?></p>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</section>

<!-- ── SECTION 6 : FAQ ─────────────────────────────────────── -->
<section class="cp-section-navy">
  <div class="container">
    <div class="cp-head" style="text-align:center;margin-bottom:36px">
      <div class="cp-eyebrow">Questions fréquentes</div>
      <h2 class="cp-title">Ce que tu te demandes peut-être</h2>
    </div>
    <div class="cp-faq-list">
      <?php
      $faqs = [
          ['q' => "Zone85, c'est quoi exactement ?",
           'a' => "Zone85, c'est la profondeur de la Vendée — le prolongement naturel des réseaux sociaux. Facebook et Instagram vous montrent la Vendée en surface. Zone85 vous fait y entrer : randos commentées, jeux culturels, livre PDF, missions collectives, clans. Ce n'est pas un réseau social, c'est ce qu'il y a en dessous."],
          ['q' => "C'est quoi VICTOR ?",
           'a' => "VICTOR est le livre PDF de Zone85 — une histoire vendéenne avec personnages, territoire et mémoire. Il est offert à tous les membres et déverrouillé dès l'inscription. C'est votre première récompense pour avoir rejoint la Zone."],
          ['q' => "Les clans, c'est obligatoire ?",
           'a' => "Non. Mais choisir un clan, c'est rejoindre une identité territoriale et contribuer collectivement à la Bataille des Clans. Bocage, Littoral ou Marais — choisissez votre appartenance. Vous pouvez admirer les deux autres et quand même porter fièrement le vôtre."],
          ['q' => "Est-ce que c'est une guerre entre clans ?",
           'a' => "Non. La Bataille des Clans est une émulation amicale entre trois identités vendéennes — pas une guerre. Choisir son clan, c'est comme choisir sa région de cœur : on peut aimer les trois, et se sentir profondément appartenir à l'une. Le créateur de Zone85 aime \"voir tout le monde avancer\"."],
          ['q' => "C'est quoi Les Invisibles ?",
           'a' => "Les Invisibles est le prochain jeu immersif de Zone85. Une aventure ancrée dans la Vendée réelle — des lieux, des indices, des personnages à découvrir sur le terrain. Les membres seront les premiers prévenus. Plus d'infos bientôt."],
          ['q' => "Faut-il payer pour participer ?",
           'a' => "L'inscription sur Zone85 est gratuite. L'accès aux Échos, aux randos, au KTC et aux missions de base est ouvert à tous les membres. Certains jeux à venir — comme Les Invisibles — seront en accès payant. Zone85 est une initiative pour faire vivre l'Esprit Vendée, et certains projets demandent un financement pour exister."],
          ['q' => "Faut-il habiter en Vendée ?",
           'a' => "Non. Zone85 accueille les Vendéens de souche, de cœur et d'adoption. Si la Vendée t'appelle — ou si tu y habites et que tu veux la vivre autrement — tu as ta place dans la Zone."],
          ['q' => "En quoi c'est différent de Facebook ?",
           'a' => "Facebook est la surface. Zone85 est la profondeur. Facebook vous montre une photo de rando — Zone85 vous fait la marcher. Facebook partage un article sur un château — Zone85 vous fait lire l'histoire vraie. Les deux se complètent. Zone85 n'est pas là pour remplacer, il est là pour ajouter."],
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


<!-- ── CTA FINAL ────────────────────────────────────────────── -->
<section style="background:linear-gradient(155deg,#0c1e2e,#12314e);padding:80px 0;position:relative;overflow:hidden">
  <div style="position:absolute;top:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(234,86,73,.06);pointer-events:none"></div>
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr auto;gap:48px;align-items:center;position:relative;z-index:1">
      <div>
        <h2 style="font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;color:#fff;margin-bottom:10px;letter-spacing:-.5px">Rejoins Zone85 — déverrouille VICTOR.</h2>
        <p style="color:rgba(255,255,255,.65);font-size:.93rem;line-height:1.65;max-width:500px">Vendéen. Communautaire. Une aventure à vivre.</p>
      </div>
      <div style="flex-shrink:0">
        <a href="<?= page_url('inscription') ?>" class="btn btn-primary">Créer mon compte →</a>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
