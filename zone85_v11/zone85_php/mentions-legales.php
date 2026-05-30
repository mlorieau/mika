<?php
$page_title       = 'Mentions légales';
$page_description = 'Mentions légales du site ZONE85 — terrain de jeu communautaire vendéen.';
$page_canonical   = 'https://www.zone85.fr/mentions-legales.php';
$page_robots      = 'index,follow';
$page_og_image    = null;
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Mentions légales','item'=>'https://www.zone85.fr/mentions-legales.php'],
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
.placeholder-field{background:rgba(234,86,73,.07);border-left:3px solid var(--primary);padding:8px 14px;color:#c73d31;font-style:italic;font-size:.84rem;margin:6px 0;border-radius:0 4px 4px 0;display:block}
.legal-note{background:var(--beige);border-radius:8px;padding:16px 20px;font-size:.82rem;color:#718096;margin-top:16px;font-style:italic;border:1px solid var(--beige-dark)}
.legal-warning{background:rgba(234,86,73,.06);border:1px solid rgba(234,86,73,.2);border-radius:8px;padding:16px 20px;font-size:.84rem;color:var(--primary);margin-bottom:40px;font-weight:600}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <h1>Mentions légales</h1>
    <p>Informations légales relatives au site Zone 85 — L'Esprit Vendée.</p>
  </div>
</section>

<!-- BODY -->
<main class="legal-body">

  <!-- Warning banner -->
  <div class="legal-warning">
    ⚠️ Informations à compléter avant mise en ligne officielle. Les champs en surbrillance sont des placeholders.
  </div>

  <!-- Section 1 : Éditeur du site -->
  <div class="legal-section">
    <h2>Éditeur du site</h2>
    <ul>
      <li><strong>Dénomination ou raison sociale :</strong><span class="placeholder-field">[Nom de la structure éditrice]</span></li>
      <li><strong>Forme juridique :</strong><span class="placeholder-field">[Forme juridique]</span></li>
      <li><strong>Adresse :</strong><span class="placeholder-field">[Adresse complète]</span></li>
      <li><strong>SIRET :</strong><span class="placeholder-field">[Numéro SIRET]</span></li>
      <li><strong>Email :</strong><span class="placeholder-field">[Email de contact]</span></li>
      <li><strong>Site web :</strong> zone85.fr</li>
    </ul>
    <p class="legal-note">Renseignez la dénomination sociale officielle, la forme juridique (association loi 1901, SAS, auto-entrepreneur…), l'adresse du siège social, le numéro SIRET délivré par l'INSEE ainsi que l'email public de contact.</p>
  </div>

  <!-- Section 2 : Responsable de publication -->
  <div class="legal-section">
    <h2>Responsable de publication</h2>
    <span class="placeholder-field">[Prénom Nom, Responsable de publication]</span>
    <ul>
      <li><strong>Qualité :</strong><span class="placeholder-field">[Titre ou fonction]</span></li>
    </ul>
    <p class="legal-note">Le responsable de publication est la personne physique ou morale qui décide des contenus publiés sur le site. Indiquez son nom complet ainsi que sa qualité (ex. : Directeur de la publication, Président de l'association, etc.).</p>
  </div>

  <!-- Section 3 : Hébergeur -->
  <div class="legal-section">
    <h2>Hébergeur</h2>
    <ul>
      <li><strong>Hébergeur :</strong><span class="placeholder-field">[Nom de l'hébergeur]</span></li>
      <li><strong>Adresse :</strong><span class="placeholder-field">[Adresse de l'hébergeur]</span></li>
      <li><strong>Contact hébergeur :</strong><span class="placeholder-field">[Contact hébergeur]</span></li>
    </ul>
    <p class="legal-note">Indiquez le nom de la société qui héberge le site (ex. : OVHcloud, Infomaniak, Vercel, Netlify…), son adresse postale complète et un moyen de contact (email ou numéro de téléphone). Ces informations sont obligatoires en vertu de la loi pour la confiance dans l'économie numérique (LCEN).</p>
  </div>

  <!-- Section 4 : Propriété intellectuelle -->
  <div class="legal-section">
    <h2>Propriété intellectuelle</h2>
    <p>L'ensemble des contenus présents sur le site Zone85 (textes, images, logos, éléments graphiques, mascottes, etc.) sont la propriété de <span class="placeholder-field">[Nom de la structure éditrice]</span> ou de leurs auteurs respectifs, et sont protégés par les lois françaises et internationales relatives à la propriété intellectuelle. Toute reproduction, représentation, modification, publication ou adaptation de tout ou partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite sans autorisation écrite préalable.</p>
  </div>

  <!-- Section 5 : Responsabilité -->
  <div class="legal-section">
    <h2>Responsabilité</h2>
    <p>Zone85 s'efforce de fournir des informations aussi précises que possible. Toutefois, il ne pourra être tenu responsable des omissions, inexactitudes ou lacunes dans la mise à jour des informations. Zone85 décline toute responsabilité pour tout dommage résultant de l'utilisation frauduleuse de ces informations ou d'une indisponibilité du service. Les liens hypertextes présents sur le site peuvent renvoyer vers d'autres sites internet, sur lesquels Zone85 n'a aucun contrôle.</p>
  </div>

  <!-- Section 6 : Signalement de contenu -->
  <div class="legal-section">
    <h2>Signalement de contenu</h2>
    <p>Si vous constatez un contenu illicite, inapproprié ou portant atteinte à vos droits sur ce site, vous pouvez le signaler via notre page <a href="contact.php">formulaire de contact</a> ou par email à <a href="mailto:contact@zone85.fr">contact@zone85.fr</a>.</p>
  </div>

  <!-- Section 7 : Contact -->
  <div class="legal-section">
    <h2>Contact</h2>
    <p><a href="contact.php">Contacter Zone85</a></p>
    <p><a href="mailto:contact@zone85.fr">contact@zone85.fr</a></p>
  </div>

</main>

<?php require_once 'includes/footer.php'; ?>
