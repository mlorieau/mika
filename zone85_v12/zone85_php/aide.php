<?php
// ============================================================
// ZONE85 — Centre d'aide (FAQ)
// ============================================================
$page_title       = 'Centre d\'aide — Zone85';
$page_description = 'Toutes les réponses à vos questions sur Zone85 : compte, XP, missions, randos, clans et communauté.';
$page_canonical   = 'https://www.zone85.fr/aide.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'aide';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page_styles = '<style>
/* ── AIDE.PHP — Centre d\'aide Zone85 ── */

.aide-hero {
  background: linear-gradient(160deg, #060e16 0%, #0c1e2e 45%, #12314e 100%);
  padding: 120px 20px 64px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.aide-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(42,157,92,.08) 0%, transparent 70%);
  pointer-events: none;
}
.aide-hero-inner {
  position: relative;
  z-index: 2;
  max-width: 680px;
  margin: 0 auto;
}
.aide-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(42,157,92,.12);
  border: 1px solid rgba(42,157,92,.3);
  color: #7ed9a8;
  padding: 5px 16px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  margin-bottom: 22px;
}
.aide-hero h1 {
  font-size: clamp(2rem, 5vw, 3.2rem);
  font-weight: 900;
  color: #fff;
  line-height: 1.1;
  letter-spacing: -1.5px;
  margin-bottom: 16px;
}
.aide-hero h1 em { color: #2a9d5c; font-style: normal; }
.aide-hero-sub {
  font-size: clamp(.9rem, 2vw, 1.05rem);
  color: rgba(255,255,255,.55);
  line-height: 1.7;
  max-width: 500px;
  margin: 0 auto;
}

/* SECTIONS FAQ */
.aide-sections {
  background: var(--beige, #f7f4ef);
  padding: 64px 20px 80px;
}
.aide-sections-inner {
  max-width: 760px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 48px;
}

.aide-section-title {
  font-size: .72rem;
  font-weight: 900;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: #2a9d5c;
  margin-bottom: 6px;
  display: block;
}
.aide-section-heading {
  font-size: clamp(1.2rem, 2.5vw, 1.55rem);
  font-weight: 900;
  color: #0c1e2e;
  letter-spacing: -.3px;
  margin-bottom: 20px;
}

/* ACCORDÉONS */
.aide-accordion {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.aide-accordion details {
  background: #fff;
  border: 1.5px solid rgba(12,30,46,.09);
  border-radius: 12px;
  overflow: hidden;
  transition: box-shadow .2s;
}
.aide-accordion details:hover {
  box-shadow: 0 4px 18px rgba(12,30,46,.08);
}
.aide-accordion details[open] {
  border-color: rgba(42,157,92,.3);
  box-shadow: 0 6px 24px rgba(12,30,46,.07);
}
.aide-accordion summary {
  cursor: pointer;
  padding: 16px 20px;
  font-size: .96rem;
  font-weight: 700;
  color: #0c1e2e;
  list-style: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  user-select: none;
}
.aide-accordion summary::-webkit-details-marker { display: none; }
.aide-accordion summary::after {
  content: "+";
  font-size: 1.15rem;
  font-weight: 900;
  color: #2a9d5c;
  flex-shrink: 0;
  transition: transform .2s;
  line-height: 1;
}
.aide-accordion details[open] summary::after {
  content: "−";
}
.aide-accordion-answer {
  padding: 0 20px 18px;
  font-size: .9rem;
  color: #4a5f73;
  line-height: 1.75;
  border-top: 1px solid rgba(12,30,46,.06);
  padding-top: 14px;
}
.aide-accordion-answer p { margin: 0 0 .8em; }
.aide-accordion-answer p:last-child { margin-bottom: 0; }
.aide-accordion-answer strong { color: #0c1e2e; }
.aide-accordion-answer a {
  color: #2a9d5c;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* CTA */
.aide-cta {
  background: linear-gradient(160deg, #0c1e2e 0%, #12314e 100%);
  padding: 64px 20px;
  text-align: center;
}
.aide-cta-inner {
  max-width: 500px;
  margin: 0 auto;
}
.aide-cta-icon { font-size: 2.4rem; display: block; margin-bottom: 16px; }
.aide-cta h2 {
  font-size: clamp(1.3rem, 3vw, 1.85rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -.5px;
  margin-bottom: 10px;
}
.aide-cta-sub {
  font-size: .95rem;
  color: rgba(255,255,255,.55);
  line-height: 1.7;
  margin-bottom: 28px;
}
.aide-cta-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #2a9d5c;
  color: #fff;
  font-size: .92rem;
  font-weight: 800;
  padding: 14px 32px;
  border-radius: 10px;
  text-decoration: none;
  transition: opacity .2s, transform .15s;
}
.aide-cta-btn:hover { opacity: .88; transform: translateY(-2px); }

@media (max-width: 600px) {
  .aide-hero { padding: 80px 16px 48px; }
  .aide-sections { padding: 44px 16px 56px; }
  .aide-accordion summary { font-size: .88rem; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="aide-hero">
  <div class="aide-hero-inner">
    <div class="aide-eyebrow">&#x2753; Centre d&rsquo;aide</div>
    <h1>Toutes vos questions<br><em>sur Zone85</em></h1>
    <p class="aide-hero-sub">QG, Facebook, clans, randos, participations&hellip; Toutes les r&eacute;ponses en quelques lignes.</p>
  </div>
</section>

<!-- ===================== SECTIONS FAQ ===================== -->
<section class="aide-sections">
  <div class="aide-sections-inner">

    <!-- ── 1. Compte & Inscription ── -->
    <div>
      <span class="aide-section-title">&#x1F464; Section 1</span>
      <h2 class="aide-section-heading">Compte &amp; Inscription</h2>
      <div class="aide-accordion">

        <details>
          <summary>Comment cr&eacute;er un compte Zone85&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Rendez-vous sur <a href="inscription.php">la page d&rsquo;inscription</a> et renseignez votre pseudo, votre e-mail et un mot de passe. L&rsquo;inscription est <strong>gratuite et ouverte &agrave; tous</strong>.</p>
            <p>Un e-mail de confirmation vous sera envoy&eacute; pour activer votre compte. V&eacute;rifiez vos spams si vous ne le recevez pas dans les 5 minutes.</p>
          </div>
        </details>

        <details>
          <summary>Peut-on changer de clan apr&egrave;s l&rsquo;inscription&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Le clan est choisi &agrave; l&rsquo;inscription et repr&eacute;sente votre territoire en Vend&eacute;e (Bocage, Littoral, Marais). <strong>Le changement de clan n&rsquo;est pas autoris&eacute; en cours de saison</strong> afin de garantir l&rsquo;&eacute;quilibre des comp&eacute;titions.</p>
            <p>Si vous estimez avoir commis une erreur lors du choix, contactez l&rsquo;&eacute;quipe Zone85 via la page <a href="contact.php">Contact</a> en d&eacute;but de saison.</p>
          </div>
        </details>

        <details>
          <summary>Comment changer mon mot de passe&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Si vous &ecirc;tes connect&eacute;, rendez-vous dans <a href="mon-compte.php">Mon Compte</a> &rarr; section &laquo;&nbsp;S&eacute;curit&eacute;&nbsp;&raquo; pour modifier votre mot de passe.</p>
            <p>Si vous avez oubli&eacute; votre mot de passe, utilisez le lien <a href="forgot-password.php">&laquo;&nbsp;Mot de passe oubli&eacute;&nbsp;&raquo;</a> sur la page de connexion.</p>
          </div>
        </details>

        <details>
          <summary>Mon compte est-il visible par les autres membres&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Votre pseudo, votre clan et votre niveau sont visibles dans le classement et le fil de la Zone. Votre e-mail et vos informations priv&eacute;es restent <strong>strictement confidentiels</strong> et ne sont jamais affich&eacute;s publiquement.</p>
          </div>
        </details>

      </div>
    </div>

    <!-- ── 2. XP & Niveaux ── -->
    <div>
      <span class="aide-section-title">&#x26A1; Section 2</span>
      <h2 class="aide-section-heading">XP &amp; Niveaux</h2>
      <div class="aide-accordion">

        <details>
          <summary>Comment gagner des XP sur Zone85&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Les XP (points d&rsquo;exp&eacute;rience) se gagnent principalement en :</p>
            <p>
              &#x2022; <strong>Validant des missions</strong> (&agrave; partir de 10 XP par mission)<br>
              &#x2022; <strong>Tampon nant des randonnées</strong> et en faisant valider votre photo (+25 XP)<br>
              &#x2022; <strong>Participant &agrave; des &eacute;v&eacute;nements flash</strong> (bonus XP temporaires)<br>
              &#x2022; <strong>R&eacute;pondant au KTC</strong> (Kétokole, mission d'identification collective)
            </p>
            <p>Consultez la page <a href="recompenses.php">XP &amp; R&eacute;compenses</a> pour le d&eacute;tail complet.</p>
          </div>
        </details>

        <details>
          <summary>Mes XP disparaissent-ils &agrave; la fin d&rsquo;une saison&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Non. <strong>Vos XP personnels sont permanents</strong> et s&rsquo;accumulent tout au long de votre aventure Zone85, quelle que soit la saison. Ils d&eacute;finissent votre niveau global de Zonaute.</p>
            <p>Les points de clan contribuent au classement annuel des clans. Le bilan est fait en fin d&rsquo;ann&eacute;e.</p>
          </div>
        </details>

        <details>
          <summary>Qu&rsquo;est-ce qui change quand je monte de niveau&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Chaque niveau d&eacute;bloqu&eacute; vous apporte :</p>
            <p>
              &#x2022; Un <strong>nouveau titre</strong> visible sur votre Passeport Vendéen<br>
              &#x2022; L&rsquo;acc&egrave;s &agrave; des <strong>badges exclusifs</strong><br>
              &#x2022; Une place am&eacute;lior&eacute;e dans le <strong>classement g&eacute;n&eacute;ral</strong>
            </p>
            <p>Les paliers de niveau sont disponibles sur la page <a href="recompenses.php">R&eacute;compenses</a>.</p>
          </div>
        </details>

        <details>
          <summary>Les XP de mon clan comptent-ils aussi pour mon niveau personnel&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Oui. Les XP que vous gagnez en accomplissant des missions et des actions contribuent &agrave; la fois &agrave; votre score personnel ET &agrave; votre clan. <strong>Vous faites progresser les deux en m&ecirc;me temps.</strong></p>
          </div>
        </details>

      </div>
    </div>

    <!-- ── 3. Missions & Randos ── -->
    <div>
      <span class="aide-section-title">&#x1F3AF; Section 3</span>
      <h2 class="aide-section-heading">Missions &amp; Randos</h2>
      <div class="aide-accordion">

        <details>
          <summary>Comment valider une mission&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Ouvrez la fiche de la mission depuis la page <a href="missions.php">Missions</a>, remplissez le formulaire de validation (photo, commentaire, pr&eacute;sence sur place selon le type de mission) et envoyez votre participation.</p>
            <p>Un membre de l&rsquo;&eacute;quipe Zone85 examine votre soumission et valide (ou non) les XP associ&eacute;s. La validation prend en g&eacute;n&eacute;ral <strong>moins de 48 heures</strong>.</p>
          </div>
        </details>

        <details>
          <summary>Comment t&eacute;l&eacute;charger un fichier GPX pour une randonnée&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Sur la fiche de chaque randonnée disposant d&rsquo;une trace GPS, un bouton <strong>&laquo;&nbsp;T&eacute;l&eacute;charger GPX&nbsp;&raquo;</strong> est disponible dans la barre d&rsquo;informations et dans le panneau lat&eacute;ral.</p>
            <p>Le fichier .gpx peut ensuite &ecirc;tre import&eacute; dans la grande majorit&eacute; des applications GPS et sport&nbsp;: <strong>Komoot, Strava, Wikiloc, AllTrails, OsmAnd, Organic Maps, Garmin Connect, GPX Viewer</strong>&hellip; Liste non exhaustive &mdash; la plupart des applis GPS acceptent ce format.</p>
          </div>
        </details>

        <details>
          <summary>La validation de ma rando est-elle imm&eacute;diate&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Le tampon de votre Passeport est <strong>instantan&eacute;</strong> d&egrave;s que vous cliquez sur &laquo;&nbsp;Tamponner mon Passeport&nbsp;&raquo;.</p>
            <p>Les <strong>+25 XP suppl&eacute;mentaires</strong> (pour photo envoy&eacute;e) ne sont d&eacute;bloqu&eacute;s qu&rsquo;apr&egrave;s examen de votre photo par l&rsquo;&eacute;quipe. Ce processus prend en g&eacute;n&eacute;ral moins de 48 heures.</p>
          </div>
        </details>

        <details>
          <summary>Puis-je faire une randonnée plusieurs fois&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Vous pouvez refaire autant de randonn&eacute;es que vous le souhaitez, mais <strong>les XP et le tampon ne sont attribu&eacute;s qu&rsquo;une seule fois par parcours</strong>. Chaque fiche de randonnée est li&eacute;e &agrave; une mission unique dans votre Passeport Vend&eacute;en.</p>
          </div>
        </details>

        <details>
          <summary>C&rsquo;est quoi une mission saisonni&egrave;re&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Une mission saisonni&egrave;re est une participation qui s&rsquo;ouvre &agrave; un moment pr&eacute;cis de l&rsquo;ann&eacute;e et reste disponible plusieurs semaines. Elle n&rsquo;est pas permanente&nbsp;: une fois la p&eacute;riode pass&eacute;e, elle se ferme.</p>
            <p>Exemples&nbsp;: une mission photo estivale ouverte jusqu&rsquo;en septembre, un quiz automnal, un jeu de pistes hivernal. <strong>Il n&rsquo;est pas n&eacute;cessaire de les faire toutes</strong> &mdash; participez &agrave; ce qui vous inspire, &agrave; votre rythme.</p>
          </div>
        </details>

        <details>
          <summary>C&rsquo;est quoi un R&eacute;veil de Zone&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Un R&eacute;veil de Zone est une petite mission flash, li&eacute;e &agrave; un article, une rando, un portrait ou une actualit&eacute; vend&eacute;enne. Elle appara&icirc;t pour quelques jours seulement.</p>
            <p>Exemples&nbsp;: un article pr&eacute;sente un artisan local &mdash; une question flash est associ&eacute;e. Un lieu est mis en avant &mdash; un tirage au sort est ouvert aux Zonautes. Ces &eacute;clats sont optionnels, rapides et fun.</p>
          </div>
        </details>

        <details>
          <summary>C&rsquo;est quoi le K&eacute;tokole Tch&egrave;&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>K&eacute;tokole Tch&egrave; (en vend&eacute;en&nbsp;: <em>&laquo;&nbsp;Qu&rsquo;est-ce que c&rsquo;est donc &agrave;&nbsp;&raquo;</em>) est une famille de missions de recherche collective. Une photo est publi&eacute;e &mdash; un objet, un lieu, un d&eacute;tail vendéen &mdash; et les Zonautes proposent leurs pistes, leurs souvenirs, leurs explications.</p>
            <p>L&rsquo;&eacute;quipe Zone85 s&eacute;lectionne les meilleures contributions et attribue XP et coups de c&oelig;ur. Le but n&rsquo;est pas de gagner mais de <strong>r&eacute;veiller la m&eacute;moire locale ensemble</strong>.</p>
          </div>
        </details>

        <details>
          <summary>Comment participer &agrave; une mission photo&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Depuis la fiche de la mission, envoyez votre photo avec le lieu et une courte description. Cochez la case d&rsquo;autorisation d&rsquo;affichage si vous acceptez que votre photo soit publi&eacute;e sur Zone85.fr.</p>
            <p>L&rsquo;&eacute;quipe examine les contributions et peut s&eacute;lectionner des <strong>coups de c&oelig;ur</strong> mis en avant sur le site ou partag&eacute;s sur Facebook (avec votre accord).</p>
          </div>
        </details>

        <details>
          <summary>Est-ce que mes photos peuvent &ecirc;tre publi&eacute;es sur Facebook&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Uniquement si vous l&rsquo;acceptez explicitement lors de l&rsquo;envoi. Une case &laquo;&nbsp;J&rsquo;accepte que ma photo soit relay&eacute;e sur la page Facebook de Zone85&nbsp;&raquo; est pr&eacute;sente dans le formulaire.</p>
            <p>Sans cette autorisation, votre photo reste visible uniquement sur Zone85.fr, dans la galerie de la mission.</p>
          </div>
        </details>

        <details>
          <summary>Est-ce que les r&eacute;compenses sont toujours des lots mat&eacute;riels&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Non. Les r&eacute;compenses sur Zone85 sont avant tout&nbsp;: des XP permanents, des badges de participation, des coups de c&oelig;ur de l&rsquo;&eacute;quipe, des mises en avant sur le site ou Facebook, et des trophées symboliques pour les clans.</p>
            <p>Des petits lots ponctuels peuvent appara&icirc;tre lors de missions sp&eacute;ciales, mais ce n&rsquo;est pas syst&eacute;matique. <strong>La vraie r&eacute;compense, c&rsquo;est la trace laiss&eacute;e dans votre Passeport Vend&eacute;en.</strong></p>
          </div>
        </details>

      </div>
    </div>

    <!-- ── 4. Clans & Communauté ── -->
    <div>
      <span class="aide-section-title">&#x1F6E1; Section 4</span>
      <h2 class="aide-section-heading">Clans &amp; Communaut&eacute;</h2>
      <div class="aide-accordion">

        <details>
          <summary>C&rsquo;est quoi le classement des clans&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Les trois clans &mdash; <strong>Bocage, Littoral, Marais</strong> &mdash; accumulent des points tout au long de l&rsquo;ann&eacute;e gr&acirc;ce aux participations de leurs membres (randos, photos, quiz, missions&hellip;).</p>
            <p>Le classement est <strong>annuel</strong>. Un podium final est r&eacute;v&eacute;l&eacute; en fin d&rsquo;ann&eacute;e. Les clans n&rsquo;ont pas besoin de se prendre trop au s&eacute;rieux : ils ajoutent une touche de jeu et de fiert&eacute; locale.</p>
          </div>
        </details>

        <details>
          <summary>Comment rejoindre un clan&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Le clan est choisi <strong>lors de l&rsquo;inscription</strong> en fonction de votre territoire. Il repr&eacute;sente votre zone g&eacute;ographique en Vend&eacute;e. Vous ne pouvez pas le changer une fois la saison commenc&eacute;e.</p>
          </div>
        </details>

        <details>
          <summary>Zone85.fr remplace-t-il la page Facebook&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Non. Zone85.fr et la page Facebook sont compl&eacute;mentaires. <strong>Facebook reste le moteur quotidien de la communaut&eacute;</strong> pour les annonces, les &eacute;changes et les nouvelles fraîches.</p>
            <p>Zone85.fr est le camp de base durable : on y retrouve les randos, les r&eacute;cits, Les &Eacute;chos, Victor et les participations propos&eacute;es par la communaut&eacute;.</p>
          </div>
        </details>

        <details>
          <summary>Dois-je me connecter tous les jours&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Non. Zone85 fonctionne par &eacute;clats de participation : une photo de temps en temps, une rando, un quiz quand il appara&icirc;t. <strong>Aucune pression, aucune obligation quotidienne.</strong></p>
            <p>Les participations se r&eacute;veillent au fil des saisons. Suivez les annonces sur Facebook pour ne rien rater.</p>
          </div>
        </details>

        <details>
          <summary>Peut-on voir les activit&eacute;s des autres Zonautes&nbsp;?</summary>
          <div class="aide-accordion-answer">
            <p>Oui, le <a href="communaute.php">Fil de la Zone</a> affiche les derni&egrave;res actions de la communaut&eacute; en temps r&eacute;el&nbsp;: missions valid&eacute;es, randonn&eacute;es tamponn&eacute;es, montées de niveau.</p>
            <p>Le <a href="communaute.php?tab=classement">classement g&eacute;n&eacute;ral</a> permet aussi de voir le top 50 des Zonautes les plus actifs.</p>
          </div>
        </details>

      </div>
    </div>

  </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="aide-cta">
  <div class="aide-cta-inner">
    <span class="aide-cta-icon">&#x1F4AC;</span>
    <h2>Une question sans r&eacute;ponse&nbsp;?</h2>
    <p class="aide-cta-sub">
      Notre &eacute;quipe est disponible pour vous aider. Envoyez-nous un message
      et nous r&eacute;pondrons dans les plus brefs d&eacute;lais.
    </p>
    <a href="contact.php" class="aide-cta-btn">&#x2709; Contactez-nous</a>
  </div>
</section>

<!-- ===================== BETA CTA ===================== -->
<section style="background:var(--beige,#f8f4ef);padding:48px 24px;text-align:center;border-top:1px solid var(--beige-dark,#e8e0d4)">
  <div style="max-width:560px;margin:0 auto">
    <p style="font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted,#6b7f96);margin-bottom:12px">B&ecirc;ta priv&eacute;e</p>
    <h2 style="font-size:1.4rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:10px">Tu testes Zone85&nbsp;en avant-premi&egrave;re&nbsp;?</h2>
    <p style="font-size:.9rem;color:var(--text-muted,#6b7f96);line-height:1.6;margin-bottom:24px">
      Ton retour est pr&eacute;cieux. Dis-nous ce qui marche, ce qui coince, et ce que tu am&eacute;liorerais.
      Quelques minutes suffisent.
    </p>
    <a href="feedback.php" style="display:inline-block;background:var(--primary,#ea5649);color:#fff;padding:13px 32px;border-radius:10px;text-decoration:none;font-weight:800;font-size:.92rem;transition:opacity .2s" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      &#x1F4DD; Donner mon avis sur la b&ecirc;ta
    </a>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
