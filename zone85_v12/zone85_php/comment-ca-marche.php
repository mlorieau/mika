<?php
$page_title       = 'Le QG des Zonautes — Comment ça marche ?';
$page_description = 'Le QG des Zonautes s\'installe doucement. Randos, récits, Échos, participations ponctuelles, clans… Tout comprendre en quelques lignes.';
$page_canonical   = 'https://www.zone85.fr/comment-ca-marche.php';
$page_robots      = 'index,follow';
$current_page     = 'comment-ca-marche';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/data.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$page_styles = '<style>
/* ── COMMENT ÇA MARCHE — styles spécifiques ── */

.ccm-hero{background:linear-gradient(160deg,#060e16 0%,#0c1e2e 40%,#12314e 100%);padding:120px 20px 64px;text-align:center;position:relative;overflow:hidden}
.ccm-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(ellipse 70% 50% at 50% 0%,rgba(234,86,73,.07) 0%,transparent 70%);pointer-events:none}
.ccm-hero-inner{position:relative;z-index:2;max-width:680px;margin:0 auto}
.ccm-eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(234,86,73,.12);border:1px solid rgba(234,86,73,.28);color:#f5a99f;padding:5px 16px;border-radius:4px;font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:22px}
.ccm-hero h1{font-size:clamp(2rem,5vw,3.4rem);font-weight:900;color:#fff;line-height:1.1;letter-spacing:-1.5px;margin-bottom:16px}
.ccm-hero h1 em{color:#ea5649;font-style:normal}
.ccm-hero-sub{font-size:clamp(.9rem,2vw,1.05rem);color:rgba(255,255,255,.55);line-height:1.7;max-width:480px;margin:0 auto}

/* Étapes */
.ccm-steps{background:var(--beige);padding:72px 20px}
.ccm-steps-inner{max-width:880px;margin:0 auto}
.ccm-steps-title{text-align:center;font-size:clamp(1.3rem,3vw,1.8rem);font-weight:900;color:var(--navy-dark);margin-bottom:48px}
.ccm-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.ccm-step{background:#fff;border-radius:14px;border:1.5px solid var(--beige-dark);padding:28px 24px;display:flex;flex-direction:column;gap:10px}
.ccm-step-num{width:36px;height:36px;border-radius:50%;background:#ea5649;color:#fff;font-size:.82rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ccm-step-header{display:flex;align-items:center;gap:12px}
.ccm-step-emoji{font-size:1.5rem;line-height:1}
.ccm-step-title{font-size:1rem;font-weight:800;color:var(--navy-dark);line-height:1.3}
.ccm-step-desc{font-size:.88rem;color:var(--text-muted);line-height:1.65;margin-top:4px}

/* CTA */
.ccm-cta{background:#fff;padding:56px 20px;text-align:center;border-top:1px solid var(--beige-dark)}
.ccm-cta-inner{max-width:480px;margin:0 auto}
.ccm-cta h2{font-size:1.5rem;font-weight:900;color:var(--navy-dark);margin-bottom:10px}
.ccm-cta-sub{font-size:.9rem;color:var(--text-muted);margin-bottom:28px;line-height:1.6}
.ccm-cta-link{display:inline-flex;align-items:center;gap:8px;background:#ea5649;color:#fff;padding:14px 32px;border-radius:6px;font-size:1rem;font-weight:800;text-decoration:none;transition:background .2s,transform .15s}
.ccm-cta-link:hover{background:#d44035;transform:translateY(-1px)}
.ccm-cta-secondary{display:block;margin-top:14px;font-size:.82rem;color:var(--text-muted);text-decoration:none}
.ccm-cta-secondary:hover{color:var(--navy-dark);text-decoration:underline}

/* FAQ accordéon */
.ccm-faq{background:var(--beige);padding:64px 20px;border-top:1px solid var(--beige-dark)}
.ccm-faq-inner{max-width:720px;margin:0 auto}
.ccm-faq-title{text-align:center;font-size:clamp(1.2rem,2.5vw,1.6rem);font-weight:900;color:var(--navy-dark);margin-bottom:10px}
.ccm-faq-intro{text-align:center;font-size:.9rem;color:var(--text-muted);margin-bottom:36px}
.ccm-faq details{background:#fff;border:1.5px solid var(--beige-dark);border-radius:10px;margin-bottom:10px;overflow:hidden}
.ccm-faq details+details{}
.ccm-faq summary{cursor:pointer;padding:16px 20px;font-size:.95rem;font-weight:700;color:var(--navy-dark);list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;user-select:none}
.ccm-faq summary::-webkit-details-marker{display:none}
.ccm-faq summary::after{content:"＋";font-size:1rem;font-weight:700;color:#ea5649;flex-shrink:0;transition:transform .2s}
.ccm-faq details[open] summary::after{content:"－"}
.ccm-faq-answer{padding:0 20px 18px;font-size:.88rem;color:var(--text-muted);line-height:1.7}

/* Responsive */
@media(max-width:680px){
  .ccm-grid{grid-template-columns:1fr}
  .ccm-hero{padding:100px 16px 48px}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO ──────────────────────────────────────────────────── -->
<section class="ccm-hero">
  <div class="ccm-hero-inner">
    <div class="ccm-eyebrow">🗺️ Le QG des Zonautes</div>
    <h1>Zone85, <em>comment ça marche&nbsp;?</em></h1>
    <p class="ccm-hero-sub">Pas besoin d'être là tous les jours. Zone85 fonctionne au rythme des saisons, des randos, des photos et des surprises qui apparaissent au fil de l'année.</p>
  </div>
</section>


<!-- 6 ÉTAPES ──────────────────────────────────────────────── -->
<section class="ccm-steps">
  <div class="ccm-steps-inner">
    <h2 class="ccm-steps-title">6 étapes légères, à votre rythme.</h2>

    <div class="ccm-grid">

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">1</div>
          <span class="ccm-step-emoji">🗺️</span>
          <div class="ccm-step-title">Je découvre les contenus Zone85</div>
        </div>
        <p class="ccm-step-desc">Randos vendéennes, Les Échos, Victor le livre mystère, Les Invisibles… Tout est accessible librement, sans compte.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">2</div>
          <span class="ccm-step-emoji">🙋</span>
          <div class="ccm-step-title">Je crée mon compte Zonaute</div>
        </div>
        <p class="ccm-step-desc">Inscription gratuite, aucune carte bleue. Je deviens membre de la communauté Zone85 et je reçois mon Passeport Vendéen.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">3</div>
          <span class="ccm-step-emoji">🛡️</span>
          <div class="ccm-step-title">Je choisis mon clan</div>
        </div>
        <p class="ccm-step-desc">Bocage, Littoral ou Marais : chaque clan représente un territoire vendéen. Je rejoins celui qui me ressemble.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">4</div>
          <span class="ccm-step-emoji">🎯</span>
          <div class="ccm-step-title">Je prends part aux participations</div>
        </div>
        <p class="ccm-step-desc">Missions photo, quiz, jeux de pistes, randos… Les participations se réveillent au fil des saisons. Je participe quand l'envie vient.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">5</div>
          <span class="ccm-step-emoji">📔</span>
          <div class="ccm-step-title">Mon passeport garde la trace</div>
        </div>
        <p class="ccm-step-desc">Chaque participation validée laisse une trace dans mon Passeport Vendéen : randos, photos, badges, points gagnés.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">6</div>
          <span class="ccm-step-emoji">🤝</span>
          <div class="ccm-step-title">Mon clan avance doucement</div>
        </div>
        <p class="ccm-step-desc">Chaque participation validée fait aussi avancer mon clan dans le classement annuel. Le podium se révèle en fin d'année.</p>
      </div>

    </div>
  </div>
</section>


<!-- CTA ───────────────────────────────────────────────────── -->
<section class="ccm-cta">
  <div class="ccm-cta-inner">
    <h2>Prêt à explorer la Zone&nbsp;?</h2>
    <p class="ccm-cta-sub">C'est gratuit, c'est vendéen, ça commence par une rando ou un article.</p>
    <?php if (is_logged_in()): ?>
      <a href="randos.php" class="ccm-cta-link">🗺️ Voir les randos →</a>
      <a href="missions.php" class="ccm-cta-secondary">Voir les participations →</a>
    <?php else: ?>
      <a href="randos.php" class="ccm-cta-link">🗺️ Découvrir les randos →</a>
      <a href="inscription.php" class="ccm-cta-secondary">Rejoindre les Zonautes →</a>
    <?php endif; ?>
  </div>
</section>


<!-- FAQ ───────────────────────────────────────────────────── -->
<section class="ccm-faq">
  <div class="ccm-faq-inner">
    <h2 class="ccm-faq-title">Questions fréquentes</h2>
    <p class="ccm-faq-intro">Tout ce qu'on nous demande souvent — en clair et sans jargon.</p>

    <details>
      <summary>Zone85.fr remplace-t-il Facebook ?</summary>
      <div class="ccm-faq-answer">Non. Zone85.fr et la page Facebook sont complémentaires. Facebook reste le moteur quotidien de la communauté. Zone85.fr est le camp de base durable : on y retrouve les randos, les récits, Les Échos et les participations.</div>
    </details>

    <details>
      <summary>Dois-je me connecter tous les jours ?</summary>
      <div class="ccm-faq-answer">Pas du tout. Zone85 fonctionne par éclats de participation : une photo de temps en temps, une rando, un quiz quand il apparaît. Aucune pression, aucune obligation.</div>
    </details>

    <details>
      <summary>Zone85 est-il gratuit ?</summary>
      <div class="ccm-faq-answer">Oui, totalement gratuit. Aucune carte bleue, aucun abonnement, aucune surprise.</div>
    </details>

    <details>
      <summary>À quoi servent les clans ?</summary>
      <div class="ccm-faq-answer">Les clans ajoutent une touche de jeu et de fierté locale. Bocage, Littoral ou Marais — chaque participation validée fait doucement avancer votre clan dans le classement annuel. En fin d'année, un podium révèle les résultats.</div>
    </details>

    <details>
      <summary>Comment fonctionne le classement annuel ?</summary>
      <div class="ccm-faq-answer">Le classement des clans se construit tranquillement toute l'année. Chaque participation validée — rando, photo, quiz — rapporte des points à votre clan. Le podium final est annoncé en fin d'année.</div>
    </details>

    <details>
      <summary>Est-ce qu'il y aura des missions toute l'année ?</summary>
      <div class="ccm-faq-answer">Non — et c'est voulu. Les participations se réveillent au fil des saisons : une mission photo l'été, un quiz à l'automne, un jeu de pistes en hiver… Suivez les annonces sur Facebook et dans Les Échos.</div>
    </details>

    <details>
      <summary>Comment valider une randonnée ?</summary>
      <div class="ccm-faq-answer">Téléchargez la trace GPX depuis la fiche de la rando, faites le parcours, envoyez une photo souvenir. Le tampon est ajouté instantanément à votre Passeport Vendéen.</div>
    </details>

    <details>
      <summary>Puis-je changer de clan ?</summary>
      <div class="ccm-faq-answer">Non. Une fois choisi à l'inscription, votre clan vous suit toute l'année. Réfléchissez bien — mais pas trop longtemps. 😄</div>
    </details>

    <details>
      <summary>Est-ce que je dois installer une application ?</summary>
      <div class="ccm-faq-answer">Non. Zone85 fonctionne dans votre navigateur. Vous pouvez l'ajouter à votre écran d'accueil comme une appli, sans passer par le store.</div>
    </details>

  </div>
</section>


<?php require_once 'includes/footer.php'; ?>
