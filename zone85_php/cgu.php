<?php
$page_title = 'CGU — Règles de la Zone · Zone 85 · L\'Esprit Vendée';
$page_description = 'Conditions générales d\'utilisation et règles communautaires de Zone85.';
$current_page = 'legal';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
$page_styles = '<style>
.legal-hero{background:var(--navy-dark);padding:80px 0 56px}
.legal-hero h1{color:#fff;font-size:2rem;font-weight:900;margin:0}
.legal-hero p{color:rgba(255,255,255,.6);margin-top:12px;font-size:.95rem}
.legal-body{max-width:820px;margin:60px auto;padding:0 24px 80px}
.legal-section{margin-bottom:48px}
.legal-section h2{font-size:1.15rem;font-weight:800;color:var(--navy-dark);margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--beige-dark)}
.legal-section h3{font-size:.95rem;font-weight:700;color:var(--navy-dark);margin:18px 0 8px}
.legal-section p,.legal-section li{font-size:.9rem;color:#4a5568;line-height:1.8}
.legal-section ul{padding-left:20px;margin:10px 0}
.legal-section li{margin-bottom:5px}
.legal-note{background:var(--beige);border-radius:8px;padding:16px 20px;font-size:.82rem;color:#718096;margin-top:16px;font-style:italic;border:1px solid var(--beige-dark)}
.legal-warning{background:rgba(234,86,73,.06);border:1px solid rgba(234,86,73,.2);border-radius:8px;padding:16px 20px;font-size:.84rem;color:var(--primary);margin-bottom:40px;font-weight:600}

/* CGU intro card */
.cgu-intro-card{background:var(--navy-dark);border-radius:10px;padding:24px 28px;margin-bottom:40px}
.cgu-intro-card p{color:rgba(255,255,255,.85);font-size:.95rem;line-height:1.8;margin:0;font-weight:500}
.cgu-intro-aside{font-size:.78rem;color:rgba(255,255,255,.45);margin-top:12px;font-style:italic}

/* XP / points card */
.xp-card{background:var(--beige);border-left:4px solid var(--primary);border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:12px}
.xp-card-title{font-size:.88rem;font-weight:800;color:var(--navy-dark);margin-bottom:6px}
.xp-card p{margin:0;font-size:.86rem;color:#4a5568;line-height:1.7}

/* Signal link */
.signal-link{display:inline-block;margin-top:14px;font-size:.9rem;font-weight:700;color:var(--primary);text-decoration:none}
.signal-link:hover{text-decoration:underline}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <h1>Les Règles de la Zone</h1>
    <p>Conditions générales d'utilisation et règles communautaires de Zone85.</p>
  </div>
</section>

<!-- BODY -->
<main class="legal-body">

  <!-- Intro card -->
  <div class="cgu-intro-card">
    <p>Zone85 est un terrain de jeu vendéen. Pour que tout le monde s'y sente bien, quelques règles s'imposent. Elles sont simples, honnêtes et dans l'esprit du site.</p>
    <div class="cgu-intro-aside">Dernière mise à jour : [date à renseigner]</div>
  </div>

  <!-- Section 1 : Esprit général -->
  <div class="legal-section">
    <h2>1. Esprit général</h2>
    <p>Zone85 est un terrain de jeu vendéen. On peut chambrer, plaisanter, défendre son clan, mais on reste correct.</p>
    <p>L'esprit de Zone85 c'est : la Vendée qui joue, qui marche, qui enquête et qui se raconte. Ici, tout le monde a sa place — de souche, de cœur ou d'adoption.</p>
  </div>

  <!-- Section 2 : Compte membre -->
  <div class="legal-section">
    <h2>2. Compte membre</h2>
    <ul>
      <li>L'utilisateur est seul responsable de son compte et des actions effectuées depuis celui-ci.</li>
      <li>Il doit fournir une adresse email valide lors de l'inscription.</li>
      <li>Il ne doit pas usurper l'identité d'une autre personne.</li>
      <li>Il s'engage à ne pas partager ses identifiants de connexion.</li>
      <li>En cas de compromission du compte, l'utilisateur doit contacter Zone85 dès que possible.</li>
    </ul>
  </div>

  <!-- Section 3 : Avatar et photo de profil -->
  <div class="legal-section">
    <h2>3. Avatar et photo de profil</h2>
    <ul>
      <li>L'utilisateur peut choisir un avatar proposé par Zone85.</li>
      <li>Il peut également uploader sa propre photo de profil.</li>
      <li>En uploadant une photo, il certifie disposer des droits nécessaires sur cette image.</li>
      <li>Aucune photo offensante, illicite, violente ou contraire à l'esprit du site n'est autorisée.</li>
      <li>Zone85 se réserve le droit de supprimer toute photo non conforme sans préavis.</li>
    </ul>
  </div>

  <!-- Section 4 : Contributions -->
  <div class="legal-section">
    <h2>4. Contributions</h2>
    <p>Les contributions incluent : commentaires, photos, réponses aux jeux et quiz, avis sur randos, participations aux missions, hypothèses Kéto Kolé Tché.</p>
    <h3>Règle fondamentale</h3>
    <p>L'utilisateur s'engage à ne publier que des contenus dont il possède les droits ou qu'il est autorisé à partager. Toute publication de contenu appartenant à un tiers sans autorisation est interdite.</p>
  </div>

  <!-- Section 5 : Modération -->
  <div class="legal-section">
    <h2>5. Modération</h2>
    <p>Zone85 se réserve le droit de :</p>
    <ul>
      <li>Masquer ou supprimer tout contenu non conforme aux présentes règles.</li>
      <li>Modifier une contribution si nécessaire.</li>
      <li>Refuser la publication d'un contenu avant sa mise en ligne.</li>
      <li>Signaler des contenus illicites aux autorités compétentes.</li>
      <li>Désactiver temporairement ou définitivement un compte en cas d'abus.</li>
    </ul>
    <p>Les décisions de modération sont prises dans l'intérêt de la communauté et ne sont pas soumises à appel formel.</p>
  </div>

  <!-- Section 6 : XP, points et récompenses -->
  <div class="legal-section">
    <h2>6. XP, points et récompenses</h2>

    <div class="xp-card">
      <div class="xp-card-title">XP personnel</div>
      <p>Attribué à vie, récompense les actions individuelles : quiz, photos, votes, randos, contributions... L'XP personnel ne diminue jamais.</p>
    </div>

    <div class="xp-card">
      <div class="xp-card-title">Score de clan</div>
      <p>Saisonnier — repart à zéro à chaque nouvelle saison. Détermine le vainqueur de la Bataille des Clans.</p>
    </div>

    <div class="xp-card">
      <div class="xp-card-title">Badges</div>
      <p>Récompenses symboliques permanentes liées à l'XP personnel et aux actions réalisées sur le site.</p>
    </div>

    <div class="xp-card">
      <div class="xp-card-title">Trophées de clan</div>
      <p>Archivés pour chaque saison remportée par le clan vainqueur.</p>
    </div>

    <p style="margin-top:18px">Les points peuvent être ajustés en cas d'erreur technique ou d'abus constaté.</p>
    <p><strong>Les XP, badges, points de clan et trophées ont une valeur symbolique et ludique. Ils ne donnent pas droit à une récompense financière.</strong></p>
  </div>

  <!-- Section 7 : Règles de bonne conduite -->
  <div class="legal-section">
    <h2>7. Règles de bonne conduite</h2>
    <p>Pour garder la Zone agréable pour tous, sont strictement interdits :</p>
    <ul>
      <li>Insultes, propos haineux, racistes, sexistes ou discriminatoires.</li>
      <li>Harcèlement sous toutes ses formes.</li>
      <li>Spam, messages répétitifs ou hors sujet.</li>
      <li>Contenu illégal ou portant atteinte aux droits d'autrui.</li>
      <li>Triche, manipulation des scores ou des classements.</li>
      <li>Publication de données personnelles d'autres utilisateurs sans leur consentement.</li>
      <li>Usurpation d'identité.</li>
    </ul>
  </div>

  <!-- Section 8 : Signalement -->
  <div class="legal-section">
    <h2>8. Signalement</h2>
    <p>Si tu constates un comportement ou un contenu problématique, tu peux le signaler via notre formulaire de contact en choisissant le motif <strong>« Signalement de contenu »</strong>.</p>
    <a href="contact.php" class="signal-link">Accéder au formulaire de signalement →</a>
  </div>

  <!-- Section 9 : Évolution des règles -->
  <div class="legal-section">
    <h2>9. Évolution des règles</h2>
    <p>Zone85 se réserve le droit de modifier les présentes règles à tout moment. Les utilisateurs seront informés des changements importants. L'utilisation continue du site après modification vaut acceptation des nouvelles règles.</p>
    <p>Date de la dernière mise à jour : [à compléter]</p>
  </div>

</main>

<?php require_once 'includes/footer.php'; ?>
