<?php
$page_title       = 'Politique de confidentialité';
$page_description = 'Politique de confidentialité de ZONE85 — comment vos données personnelles sont collectées, utilisées et protégées.';
$page_canonical   = 'https://www.zone85.fr/confidentialite.php';
$page_robots      = 'index,follow';
$page_og_image    = null;
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Confidentialité','item'=>'https://www.zone85.fr/confidentialite.php'],
    ],
];
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
.data-tag{display:inline-block;background:rgba(18,49,78,.07);border:1px solid rgba(18,49,78,.12);color:var(--navy-dark);font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;margin:2px}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <h1>Politique de confidentialité</h1>
    <p>Comment Zone85 collecte, utilise et protège vos données personnelles.</p>
  </div>
</section>

<!-- BODY -->
<main class="legal-body">

  <!-- Warning banner -->
  <div class="legal-warning">
    ⚠️ Ce document est un modèle à faire valider juridiquement avant mise en production officielle.
  </div>

  <!-- Section 1 : Qui sommes-nous ? -->
  <div class="legal-section">
    <h2>Qui sommes-nous ?</h2>
    <p>Zone85 — L'Esprit Vendée est un média communautaire gamifié dédié à la Vendée. Le responsable du traitement des données est [Nom de la structure éditrice], joignable à <a href="mailto:contact@zone85.fr">contact@zone85.fr</a>.</p>
  </div>

  <!-- Section 2 : Données collectées -->
  <div class="legal-section">
    <h2>Données collectées</h2>
    <p>Selon votre utilisation de Zone85, nous pouvons collecter les données suivantes :</p>

    <h3>Données de compte</h3>
    <ul>
      <li><span class="data-tag">Email</span></li>
      <li><span class="data-tag">Pseudo</span></li>
      <li><span class="data-tag">Prénom / Nom</span> si renseigné</li>
      <li><span class="data-tag">Mot de passe</span> stocké chiffré</li>
      <li><span class="data-tag">Clan choisi</span></li>
      <li><span class="data-tag">Avatar</span> ou <span class="data-tag">Photo de profil</span></li>
    </ul>

    <h3>Données de participation</h3>
    <ul>
      <li><span class="data-tag">XP personnel</span></li>
      <li><span class="data-tag">Niveau</span></li>
      <li><span class="data-tag">Badges obtenus</span></li>
      <li><span class="data-tag">Missions réalisées</span></li>
      <li><span class="data-tag">Contributions</span> : commentaires, photos, votes, avis randos, hypothèses Kéto Kolé Tché, participations météo-mission</li>
    </ul>

    <h3>Données de contact</h3>
    <ul>
      <li><span class="data-tag">Informations transmises</span> via le formulaire de contact</li>
    </ul>

    <h3>Données techniques</h3>
    <ul>
      <li><span class="data-tag">Logs de connexion</span> éventuels</li>
      <li><span class="data-tag">Cookies</span> nécessaires au fonctionnement du site (voir <a href="cookies.php">politique cookies</a>)</li>
    </ul>
  </div>

  <!-- Section 3 : Pourquoi nous collectons ces données -->
  <div class="legal-section">
    <h2>Pourquoi nous collectons ces données</h2>
    <ul>
      <li>Création et gestion de votre compte membre</li>
      <li>Affichage de votre profil et de vos statistiques</li>
      <li>Attribution des XP, niveaux et badges</li>
      <li>Participation aux missions et à la Bataille des Clans</li>
      <li>Gestion des classements</li>
      <li>Modération des contributions</li>
      <li>Réponse à vos demandes de contact</li>
      <li>Sécurité et intégrité du site</li>
      <li>Statistiques de fréquentation anonymisées (si cookies de mesure activés)</li>
    </ul>
  </div>

  <!-- Section 4 : Photos, avatars et contributions -->
  <div class="legal-section">
    <h2>Photos, avatars et contributions</h2>
    <p>Lors de l'inscription ou depuis son profil, l'utilisateur peut choisir un avatar proposé par Zone85 ou uploader sa propre photo. En uploadant une photo, l'utilisateur garantit disposer des droits nécessaires et accepte qu'elle soit utilisée comme image de profil sur le site. Il pourra la modifier ou la supprimer depuis son espace membre lorsque cette fonctionnalité sera disponible.</p>
  </div>

  <!-- Section 5 : Contributions publiques -->
  <div class="legal-section">
    <h2>Contributions publiques</h2>
    <p>Certaines participations, photos, commentaires ou contributions peuvent être affichés publiquement sur le site, notamment dans les missions, les classements, le Hall de la Zone ou les archives. Les contributions mises en avant pourront également être relayées sur les réseaux sociaux de Zone85, dans le cadre de l'animation communautaire.</p>
  </div>

  <!-- Section 6 : Durée de conservation -->
  <div class="legal-section">
    <h2>Durée de conservation</h2>
    <p>Vos données sont conservées pendant la durée de vie de votre compte. En cas de suppression de compte, vos données personnelles sont supprimées dans un délai raisonnable, à l'exception des contributions publiques qui peuvent rester archivées de façon anonymisée.</p>
  </div>

  <!-- Section 7 : Vos droits -->
  <div class="legal-section">
    <h2>Vos droits</h2>
    <p>Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez des droits suivants :</p>
    <ul>
      <li>Droit d'accès à vos données personnelles</li>
      <li>Droit de rectification en cas d'erreur</li>
      <li>Droit à la suppression (droit à l'oubli)</li>
      <li>Droit d'opposition au traitement</li>
      <li>Droit de retrait de votre consentement à la newsletter à tout moment</li>
    </ul>
    <p>Pour exercer ces droits, contactez-nous via le <a href="contact.php">formulaire de contact</a> ou à <a href="mailto:contact@zone85.fr">contact@zone85.fr</a>.</p>
  </div>

  <!-- Section 8 : Contact et réclamation -->
  <div class="legal-section">
    <h2>Contact et réclamation</h2>
    <p>Pour toute question relative à la protection de vos données, vous pouvez nous contacter à <a href="mailto:contact@zone85.fr">contact@zone85.fr</a> ou via notre <a href="contact.php">formulaire de contact</a>. Vous disposez également du droit de déposer une réclamation auprès de la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a>).</p>
  </div>

</main>

<?php require_once 'includes/footer.php'; ?>
