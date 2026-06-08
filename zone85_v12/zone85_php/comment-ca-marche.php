<?php
$page_title       = 'Comment ça marche ? — Zone85';
$page_description = 'Comprends Zone85 en moins de 30 secondes : compte, clan, missions, XP, classement.';
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
    <div class="ccm-eyebrow">📖 Guide rapide</div>
    <h1>Zone85, <em>comment ça marche&nbsp;?</em></h1>
    <p class="ccm-hero-sub">En moins de 30 secondes, comprends l'essentiel.</p>
  </div>
</section>


<!-- 5 ÉTAPES ──────────────────────────────────────────────── -->
<section class="ccm-steps">
  <div class="ccm-steps-inner">
    <h2 class="ccm-steps-title">5 étapes, c'est tout.</h2>

    <div class="ccm-grid">

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">1</div>
          <span class="ccm-step-emoji">🙋</span>
          <div class="ccm-step-title">Je crée mon compte</div>
        </div>
        <p class="ccm-step-desc">Inscription gratuite, aucune carte bleue, aucune appli à télécharger.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">2</div>
          <span class="ccm-step-emoji">🛡️</span>
          <div class="ccm-step-title">Je choisis mon clan</div>
        </div>
        <p class="ccm-step-desc">Bocage, Littoral ou Marais : chaque clan représente un territoire vendéen. Tu joues pour lui.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">3</div>
          <span class="ccm-step-emoji">🎯</span>
          <div class="ccm-step-title">Je participe à des missions, des randos ou des défis</div>
        </div>
        <p class="ccm-step-desc">Des activités courtes ou longues, à faire seul ou en famille, en Vendée.</p>
      </div>

      <div class="ccm-step">
        <div class="ccm-step-header">
          <div class="ccm-step-num">4</div>
          <span class="ccm-step-emoji">⭐</span>
          <div class="ccm-step-title">Je gagne des XP</div>
        </div>
        <p class="ccm-step-desc">Chaque participation rapporte des points d'expérience. Plus tu participes, plus tu progresses.</p>
      </div>

      <div class="ccm-step" style="grid-column:1/-1">
        <div class="ccm-step-header">
          <div class="ccm-step-num">5</div>
          <span class="ccm-step-emoji">🤝</span>
          <div class="ccm-step-title">Je fais avancer mon clan</div>
        </div>
        <p class="ccm-step-desc">Tes XP contribuent aussi au classement de ton clan. Ensemble, vous montez.</p>
      </div>

    </div>
  </div>
</section>


<!-- CTA ───────────────────────────────────────────────────── -->
<section class="ccm-cta">
  <div class="ccm-cta-inner">
    <h2>Prêt à rejoindre la Zone&nbsp;?</h2>
    <p class="ccm-cta-sub">C'est gratuit, c'est vendéen, ça se passe maintenant.</p>
    <?php if (is_logged_in()): ?>
      <a href="missions.php" class="ccm-cta-link">🎯 Voir mes missions →</a>
    <?php else: ?>
      <a href="inscription.php" class="ccm-cta-link">Créer mon compte →</a>
    <?php endif; ?>
  </div>
</section>


<!-- FAQ ───────────────────────────────────────────────────── -->
<section class="ccm-faq">
  <div class="ccm-faq-inner">
    <h2 class="ccm-faq-title">Questions fréquentes</h2>
    <p class="ccm-faq-intro">Tout ce qu'on nous demande souvent — en clair et sans jargon.</p>

    <details>
      <summary>Zone85 est-il gratuit ?</summary>
      <div class="ccm-faq-answer">Oui, totalement gratuit. Aucune carte bleue, aucun abonnement, aucune surprise.</div>
    </details>

    <details>
      <summary>C'est quoi un clan ?</summary>
      <div class="ccm-faq-answer">Un groupe territorial. Bocage, Littoral ou Marais — tu rejoins l'un des trois à l'inscription et tu joues pour lui toute la saison.</div>
    </details>

    <details>
      <summary>Est-ce que je peux changer de clan ?</summary>
      <div class="ccm-faq-answer">Non. Une fois choisi, c'est pour la saison. Réfléchis bien — mais pas trop longtemps non plus. 😄</div>
    </details>

    <details>
      <summary>C'est quoi les XP ?</summary>
      <div class="ccm-faq-answer">Des points d'expérience qui mesurent ta participation. Plus tu t'impliques dans les missions, les randos et les défis, plus tu en gagnes.</div>
    </details>

    <details>
      <summary>Comment valider une rando ?</summary>
      <div class="ccm-faq-answer">Tu télécharges la trace GPX, tu fais la rando, tu postes une photo souvenir. Simple.</div>
    </details>

    <details>
      <summary>C'est quoi une trace GPX ?</summary>
      <div class="ccm-faq-answer">Un fichier de parcours que tu ouvres dans une application GPS comme Komoot ou GPX Viewer. Il te guide sur le chemin, comme une carte interactive.</div>
    </details>

    <details>
      <summary>Est-ce que je dois installer une application ?</summary>
      <div class="ccm-faq-answer">Non. Zone85 fonctionne dans ton navigateur. Tu peux l'ajouter à ton écran d'accueil comme une appli, sans passer par le store.</div>
    </details>

    <details>
      <summary>Est-ce que mes photos sont publiques ?</summary>
      <div class="ccm-faq-answer">Tes photos de participation sont visibles par la communauté Zone85.</div>
    </details>

    <details>
      <summary>Comment supprimer mon compte ?</summary>
      <div class="ccm-faq-answer">Via Mon Compte &gt; Supprimer mon compte, ou en écrivant à <a href="mailto:contact@zone85.fr" style="color:var(--primary)">contact@zone85.fr</a>.</div>
    </details>

  </div>
</section>


<?php require_once 'includes/footer.php'; ?>
