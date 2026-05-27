<?php
$page_title = 'Cookies — Zone 85 · L\'Esprit Vendée';
$page_description = 'Comment Zone85 utilise les cookies et comment vous pouvez les gérer.';
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

/* Cookie type cards */
.cookie-card{background:var(--beige);border-left:4px solid var(--primary);border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:16px}
.cookie-card-title{font-size:.95rem;font-weight:800;color:var(--navy-dark);margin-bottom:8px}
.cookie-card p{margin:0;font-size:.88rem;color:#4a5568;line-height:1.7}
.cookie-card .cookie-tag{display:inline-block;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:10px;letter-spacing:.03em}
.cookie-tag--required{background:rgba(18,49,78,.12);color:var(--navy-dark)}
.cookie-tag--optional{background:rgba(234,86,73,.1);color:var(--primary)}

/* Consent placeholder */
.consent-placeholder{border:2px dashed var(--beige-dark);background:var(--beige);border-radius:8px;padding:20px 24px;text-align:center;color:#718096;font-size:.84rem;font-style:italic;margin:20px 0}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <h1>Politique cookies</h1>
    <p>Comment Zone85 utilise les cookies et comment vous pouvez les gérer.</p>
  </div>
</section>

<!-- BODY -->
<main class="legal-body">

  <div class="legal-note">
    Cette page sera finalisée selon les outils réellement installés sur le site.
  </div>

  <!-- Section 1 : Qu'est-ce qu'un cookie ? -->
  <div class="legal-section">
    <h2>Qu'est-ce qu'un cookie ?</h2>
    <p>Un cookie est un petit fichier texte déposé sur votre navigateur lors de la visite d'un site internet. Il permet au site de mémoriser certaines informations vous concernant : vos préférences d'affichage, votre session de connexion, ou encore votre façon de naviguer.</p>
    <p>Les cookies améliorent votre expérience utilisateur en évitant de ressaisir des informations à chaque visite, et aident les éditeurs à comprendre comment leur site est utilisé afin de l'améliorer en continu.</p>
    <p>Les cookies ne contiennent pas de programme exécutable et ne peuvent pas nuire à votre appareil.</p>
  </div>

  <!-- Section 2 : Les cookies utilisés par Zone85 -->
  <div class="legal-section">
    <h2>Les cookies utilisés par Zone85</h2>

    <div class="cookie-card">
      <div class="cookie-card-title">🔧 Cookies nécessaires</div>
      <p>Ces cookies sont indispensables au fonctionnement du site. Ils assurent des fonctions essentielles telles que la gestion de votre session, l'authentification à votre compte et la protection contre les attaques CSRF. Sans ces cookies, certaines parties du site ne peuvent pas fonctionner correctement.</p>
      <span class="cookie-tag cookie-tag--required">Obligatoires — aucun consentement requis</span>
    </div>

    <div class="cookie-card">
      <div class="cookie-card-title">📊 Cookies de mesure d'audience</div>
      <p>Ces cookies nous aident à comprendre comment les visiteurs utilisent le site : quelles pages sont consultées, combien de temps, depuis quels appareils. Les données collectées sont anonymisées et agrégées — elles ne permettent pas de vous identifier personnellement.</p>
      <p style="margin-top:10px"><em>Outil de mesure : à définir selon l'outil choisi — ex. Matomo, GA4, Plausible.</em></p>
      <span class="cookie-tag cookie-tag--optional">Soumis à consentement</span>
    </div>

    <div class="cookie-card">
      <div class="cookie-card-title">📺 Cookies de contenus intégrés</div>
      <p>Si Zone85 intègre des vidéos, des cartes interactives ou des contenus provenant de réseaux sociaux, les fournisseurs de ces services tiers peuvent déposer leurs propres cookies lors de la lecture ou du chargement de ces contenus.</p>
      <p style="margin-top:10px"><em>Contenus concernés : à définir selon les contenus embarqués sur le site.</em></p>
      <span class="cookie-tag cookie-tag--optional">Soumis à consentement</span>
    </div>

    <div class="cookie-card">
      <div class="cookie-card-title">⚙️ Cookies de préférence</div>
      <p>Ces cookies mémorisent vos choix d'affichage et vos préférences sur le site, comme le thème visuel ou d'autres options de personnalisation, afin de vous offrir une expérience cohérente à chacune de vos visites.</p>
      <span class="cookie-tag cookie-tag--optional">Soumis à consentement</span>
    </div>
  </div>

  <!-- Section 3 : Gestion du consentement -->
  <div class="legal-section">
    <h2>Gestion du consentement</h2>
    <p>Lors de la mise en ligne, un bandeau de gestion des cookies permettra d'accepter, refuser ou personnaliser les cookies non essentiels.</p>
    <p>Pour l'instant, seuls les cookies strictement nécessaires au fonctionnement du site sont utilisés.</p>

    <!-- ZONE CONSENTEMENT COOKIES — à intégrer lors de la mise en production -->
    <div class="consent-placeholder">
      [Emplacement réservé : bandeau de consentement cookies — à intégrer lors de la mise en production]
    </div>
  </div>

  <!-- Section 4 : Comment refuser ou supprimer les cookies -->
  <div class="legal-section">
    <h2>Comment refuser ou supprimer les cookies</h2>
    <p>Vous pouvez à tout moment gérer, désactiver ou supprimer les cookies directement depuis les paramètres de votre navigateur. La procédure varie selon le navigateur utilisé :</p>
    <ul>
      <li><strong>Google Chrome</strong> — Paramètres &gt; Confidentialité et sécurité &gt; Cookies et autres données des sites.</li>
      <li><strong>Mozilla Firefox</strong> — Paramètres &gt; Vie privée et sécurité &gt; Cookies et données de sites.</li>
      <li><strong>Apple Safari</strong> — Préférences &gt; Confidentialité &gt; Gérer les données de sites web.</li>
      <li><strong>Microsoft Edge</strong> — Paramètres &gt; Cookies et autorisations de site &gt; Cookies et données de site.</li>
    </ul>
    <p>Notez que la désactivation de certains cookies peut affecter le bon fonctionnement du site et limiter certaines fonctionnalités (connexion, préférences, etc.).</p>
    <p class="legal-note">Pour en savoir plus sur les cookies et vos droits, vous pouvez consulter le site de la CNIL : <strong>cnil.fr</strong>.</p>
  </div>

  <!-- Section 5 : Contact -->
  <div class="legal-section">
    <h2>Contact</h2>
    <p>Pour toute question relative aux cookies, contactez-nous via notre <a href="contact.php">formulaire de contact</a> ou à <a href="mailto:contact@zone85.fr">contact@zone85.fr</a>.</p>
  </div>

</main>

<?php require_once 'includes/footer.php'; ?>
