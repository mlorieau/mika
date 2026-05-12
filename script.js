/* ============================================================
   ZONE85 — Script principal
   Intro → Carte animée → Hotspots → Panneaux de contenu
   ============================================================ */

'use strict';

/* ────────────────────────────────────────────
   BASE DE DONNÉES SIMULÉE
   (chaque objet deviendra une table PHP/MySQL)
   ──────────────────────────────────────────── */
const DB = {

  invisibles: {
    tag:   'Enquête · Zone85',
    title: 'Les Invisibles',
    html: `
      <p class="p-text">
        Tout a commencé un dimanche matin, à Vouvant.
        Une vieille table achetée presque par hasard — du bois sombre,
        des pieds tordus, une odeur de grenier et de mémoire.
      </p>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <p class="p-text">
        En la restaurant, un double fond. Discret. Presque invisible.
        Et à l'intérieur : <em>un carnet</em>.
      </p>
      <div class="carnet">
        <span class="carnet-date">— Note non datée, encre brune —</span>
        « Ceux qui gardent n'ont pas le droit de parler.<br>
        Ceux qui cherchent ne savent pas ce qu'ils cherchent.<br>
        L'Œuf attend. Il a attendu avant nous.<br>
        Il attendra après.<br><br>
        — Les Invisibles — »
      </div>
      <p class="p-text">
        Le carnet est rempli de notes, de croquis, de coordonnées.
        Des lieux vendéens. Des noms effacés. Un mot revient sans cesse :
        <em>Les Invisibles</em>. Une société. Ancienne. Discrète.
        Chargée de protéger quelque chose que le carnet appelle
        <em>l'Œuf de l'Oiseau du Temps</em>.
      </p>
      <p class="p-text">
        Un artefact lié au quartz. À la lumière. À la mémoire du territoire.
        Sa localisation reste inconnue. Ou presque.
      </p>
      <blockquote class="p-quote">
        Ceux qui cherchent la vérité ne sont pas un danger… mais un espoir.
      </blockquote>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <p class="p-text">
        L'enquête est ouverte. Les indices existent — ici, sur la carte,
        dans des brocantes, dans des archives. Il faut chercher.
        Il faut fouiller. Il faut revenir.
      </p>
      <a class="p-cta" href="#" onclick="return false;">
        Entrer dans l'enquête →
      </a>
    `
  },

  patois: {
    tag:   'Langue · Territoire',
    title: 'Chez nous, on ne dit pas…',
    html: `
      <p class="p-text">
        Le vendéen, c'est une langue à part. Pas vraiment du patois pur.
        Pas vraiment du français non plus. Un mélange. Une couleur.
        Le genre de mots qui sentent la mogette, les chemins creux
        et les dimanches où le ciel hésite à se tenir tranquille.
      </p>
      <div class="patois-grid">

        <div class="patois-card">
          <div class="patois-word">Pétaï de volàie</div>
          <div class="patois-trad">Flatteur des poules · vantard</div>
          <p class="patois-desc">
            Allez, aujourd'hui on parle de talent. Parce que <em>pétaï de volàie</em>,
            c'est pas juste faire du bruit avec son derrière. C'est une maîtrise.
            Régulière. Presque artistique. Le genre de performance qui arrive sans prévenir,
            souvent au pire moment, mais toujours avec une constance impressionnante. Respectons.
          </p>
        </div>

        <div class="patois-card">
          <div class="patois-word">O va peuter</div>
          <div class="patois-trad">Il va pleuvoir · temps menaçant</div>
          <p class="patois-desc">
            Chez nous, on ne dit pas "il va pleuvoir". On dit <em>o va peuter</em>.
            C'est plus précis. Ça inclut le vent, la boue sur les chemins et
            le fait que t'avais prévu de tondre la pelouse. Le ciel vendéen
            peute à sa façon : il prévient pas toujours.
          </p>
        </div>

        <div class="patois-card">
          <div class="patois-word">Mi-figue mi-mogette</div>
          <div class="patois-trad">Entre deux · ni très bien ni très mal</div>
          <p class="patois-desc">
            <em>Mi-figue mi-raisin</em>, c'est pour les Parisiens. Nous, on dit
            mi-figue mi-mogette. Parce que la mogette, c'est notre raisin.
            Le haricot blanc du dimanche, la fierté du bocage,
            l'honneur du plat qui réconcilie tout le monde autour de la table.
            Être mi-mogette, c'est être vendéen à moitié — et ça n'arrive presque jamais.
          </p>
        </div>

        <div class="patois-card">
          <div class="patois-word">Drôle</div>
          <div class="patois-trad">Enfant · gamin</div>
          <p class="patois-desc">
            "Regarde le drôle !" — Non, ça ne veut pas dire que l'enfant
            est comique (même si c'est souvent le cas). En vendéen, un drôle
            c'est un gosse. Un gamin. Celui qui court dans les rangs
            et qui va finir par tomber dans la mare. Tout le monde a été drôle.
            Certains le sont restés.
          </p>
        </div>

      </div>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <p class="p-text" style="font-style:italic;font-size:0.85rem;color:var(--text-dim)">
        Des expressions, vous en connaissez d'autres ?
        Les zonautes alimentent la rubrique. Envoyez-nous les vôtres.
      </p>
      <a class="p-cta" href="#" onclick="return false;">
        Proposer une expression →
      </a>
    `
  },

  meteo: {
    tag:   'Ciel · Vendée',
    title: 'Météo Zone85',
    html: `
      <p class="p-text">
        Ce n'est pas une météo technique. Ici, on ne parle pas
        de "perturbations atlantiques" ni de "dépression barométrique".
        On vous dit si <em>o va peuter</em>, si le soleil va sortir la tête
        ou si c'est une journée mogette-canapé.
      </p>
      <div class="meteo-bloc">
        <span class="meteo-emoji">🌦️</span>
        <div class="meteo-phrase">Mi-figue mi-mogette</div>
        <div class="meteo-detail">
          Le soleil hésite. Le nuage aussi.<br>
          On va dire que ça va aller, mais prenez quand même un lainage.
        </div>
      </div>
      <p class="p-text">
        <strong>La règle de base :</strong> en Vendée, la météo change d'avis
        plus vite qu'un voisin qui vous demande de l'aide pour déménager.
        Matin beau, midi peute, soir beau. C'est ça, le caractère vendéen.
      </p>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <p class="p-text" style="font-size:0.83rem;color:var(--text-dim)">
        En cas de vigilance orange ou rouge, les zonautes saluent
        ceux qui travaillent : pompiers, secours, agents de terrain.
        Zone85, c'est aussi ça.
      </p>
      <a class="p-cta" href="#" onclick="return false;">
        Voir les alertes →
      </a>
    `
  },

  balades: {
    tag:   'Territoire · Nature',
    title: 'Balades & coins à voir',
    html: `
      <p class="p-text">
        La Vendée, ça se parcourt. À pied, à vélo, en barque dans le marais.
        Les yeux ouverts, le téléphone dans la poche —
        pas pour Instagram, pour ne pas se perdre.
      </p>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <div class="zone-bloc">
        <p class="zone-bloc-title">🌿 Le bocage</p>
        <p class="p-text">
          Les chemins creux. Les haies infinies. L'ombre des chênes
          qui date d'avant vous et durera après. On y croise des curieux,
          des marcheurs, parfois une vache qui vous toise.
        </p>
      </div>
      <div class="zone-bloc">
        <p class="zone-bloc-title">🌊 Le littoral</p>
        <p class="p-text">
          Sables d'Olonne, Saint-Gilles, la Tranche, les Achards.
          Les plages vendéennes ne sont pas qu'estivales.
          En hiver, elles sont presque à vous tout seul. Presque.
        </p>
      </div>
      <div class="zone-bloc">
        <p class="zone-bloc-title">🌿 Le marais</p>
        <p class="p-text">
          Le marais breton au nord, le marais poitevin au sud.
          Deux univers. Deux silences différents.
          L'eau, les saules, les canards et le temps qui passe autrement.
        </p>
      </div>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <a class="p-cta" href="#" onclick="return false;">
        Explorer les balades →
      </a>
    `
  },

  memoire: {
    tag:   'Archives · Patrimoine',
    title: 'Mémoire vendéenne',
    html: `
      <p class="p-text">
        Il y a les histoires qu'on apprend à l'école.
        Et il y a celles qu'on entend à table, un dimanche,
        quand un vieux commence une phrase par
        <em>"mon père me disait…"</em>
      </p>
      <blockquote class="p-quote">
        "La Vendée, c'est pas juste une région. C'est une façon de se tenir."
      </blockquote>
      <p class="p-text">
        Zone85 collecte ces récits. Des familles. Des lieux.
        Des métiers disparus. Des événements que personne n'a photographiés
        mais que tout le monde dans le coin connaît de père en fils.
      </p>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <p class="p-text">
        Ce n'est pas un musée. C'est un carnet vivant.
        Les zonautes contribuent. Les archives s'ouvrent.
        Les histoires circulent à nouveau.
      </p>
      <p class="p-text" style="font-size:0.82rem;color:var(--text-dim);font-style:italic">
        Prochainement : archives photographiques, témoignages audio,
        cartes de lieux disparus.
      </p>
      <a class="p-cta" href="#" onclick="return false;">
        Partager un souvenir →
      </a>
    `
  },

  ovni: {
    tag:   'Mystère · Communauté',
    title: 'OVNI Zone85',
    html: `
      <p class="p-text">
        Parfois, on trouve des trucs. Des objets. Des machins.
        Des choses sans nom ni mode d'emploi.
        Et on se dit : <em>les zonautes, ils savent peut-être.</em>
      </p>
      <div class="carnet" style="transform:rotate(0.3deg)">
        <span class="carnet-date">— Signalement du 4 novembre —</span>
        « Trouvé au détour d'une brocante à Fontenay-le-Comte.<br>
        Métal. Creux. Environ un mètre de long.<br>
        Gravures sur l'un des côtés. Odeur de sel.<br><br>
        Quelqu'un sait ce que c'est ? »
      </div>
      <p class="p-text">
        La rubrique OVNI Zone85, c'est notre bureau des objets trouvés —
        pas les clés de voiture ou les parapluies.
        Les trucs vraiment étranges. Les questions sans réponse immédiate.
        Le mystère local, décomplexé.
      </p>
      <div class="quartz-sep"><div class="quartz-gem"></div></div>
      <p class="p-text">
        Vous avez trouvé quelque chose d'inexpliqué ?
        Une brocante vous a vendu quelque chose de louche ?
        Un champ cache un machin bizarre ?
      </p>
      <a class="p-cta" href="#" onclick="return false;">
        Soumettre un objet mystère →
      </a>
    `
  }
};

/* ────────────────────────────────────────────
   RACCOURCIS DOM
   ──────────────────────────────────────────── */
const $ = id => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);

/* ────────────────────────────────────────────
   INIT AU CHARGEMENT
   ──────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  startParticles();
  startIntro();
  bindHotspots();
  bindPanel();
  bindMobileNav();
  bindKeyboard();
});

/* ────────────────────────────────────────────
   PARTICULES DE FOND
   ──────────────────────────────────────────── */
function startParticles() {
  const canvas = $('particles-canvas');
  const ctx = canvas.getContext('2d');

  let W, H, pts;

  function setup() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
    const n = Math.min(55, Math.floor(W / 22));
    pts = Array.from({ length: n }, () => ({
      x:  Math.random() * W,
      y:  Math.random() * H,
      vx: (Math.random() - 0.5) * 0.28,
      vy: (Math.random() - 0.5) * 0.18,
      r:  Math.random() * 1.3 + 0.3,
      base: Math.random() * 0.35 + 0.04,
      phase: Math.random() * Math.PI * 2
    }));
  }

  setup();
  window.addEventListener('resize', setup);

  let t = 0;
  (function loop() {
    ctx.clearRect(0, 0, W, H);
    t += 0.007;

    pts.forEach(p => {
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0) p.x = W;
      if (p.x > W) p.x = 0;
      if (p.y < 0) p.y = H;
      if (p.y > H) p.y = 0;

      const alpha = p.base * (0.5 + 0.5 * Math.sin(t * 1.3 + p.phase));
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(184,220,240,${alpha})`;
      ctx.fill();
    });

    requestAnimationFrame(loop);
  })();
}

/* ────────────────────────────────────────────
   SÉQUENCE D'INTRO
   ──────────────────────────────────────────── */
function startIntro() {
  const intro = $('intro');
  const app   = $('app');

  setTimeout(() => {
    intro.classList.add('fade-out');
    app.classList.remove('hidden');
    app.classList.add('visible');

    // Lancer l'animation de carte après la transition
    setTimeout(animateMap, 700);
  }, 2300);
}

/* ────────────────────────────────────────────
   ANIMATION DE LA CARTE VENDÉE
   ──────────────────────────────────────────── */
function animateMap() {
  const path  = $('vendee-path');
  const fill  = $('vendee-fill');
  const dot   = $('trace-dot');
  const glow  = $('trace-glow');
  const hotspotsEl = $$('.hotspot');

  if (!path) return;

  const len = path.getTotalLength();

  // Prépare le tracé progressif (stroke-dashoffset)
  path.style.strokeDasharray  = len;
  path.style.strokeDashoffset = len;

  // Affiche le point mobile
  dot.style.opacity  = '1';
  glow.style.opacity = '0.55';

  const DURATION = 2800; // ms pour tracer le contour
  let t0 = null;

  function step(ts) {
    if (!t0) t0 = ts;
    const elapsed = ts - t0;
    const raw = Math.min(elapsed / DURATION, 1);
    // Ease-out cubic
    const ease = 1 - Math.pow(1 - raw, 3);

    // Tracé progressif
    path.style.strokeDashoffset = len * (1 - ease);

    // Déplacement du point mobile
    const pt = path.getPointAtLength(len * ease);
    dot.setAttribute('cx', pt.x);
    dot.setAttribute('cy', pt.y);
    glow.setAttribute('cx', pt.x);
    glow.setAttribute('cy', pt.y);

    if (raw < 1) {
      requestAnimationFrame(step);
    } else {
      // Tracé terminé
      dot.style.transition  = 'opacity 0.6s ease';
      glow.style.transition = 'opacity 0.6s ease';
      dot.style.opacity  = '0';
      glow.style.opacity = '0';

      // Fait apparaître le remplissage
      fill.style.transition = 'opacity 1s ease';
      fill.setAttribute('opacity', '0.85');

      // Fait apparaître les hotspots un par un
      setTimeout(() => {
        hotspotsEl.forEach((h, i) => {
          setTimeout(() => h.classList.add('visible'), i * 160);
        });
      }, 400);
    }
  }

  requestAnimationFrame(step);
}

/* ────────────────────────────────────────────
   HOTSPOTS
   ──────────────────────────────────────────── */
function bindHotspots() {
  $$('.hotspot').forEach(el => {
    el.addEventListener('click', () => openPanel(el.dataset.section));
  });
}

/* ────────────────────────────────────────────
   PANNEAU LATÉRAL
   ──────────────────────────────────────────── */
function bindPanel() {
  $('panel-backdrop').addEventListener('click', closePanel);
  $('panel-close').addEventListener('click', closePanel);

  // Boutons de nav header
  $$('.nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      openPanel(btn.dataset.section);
      closeMobileNav();
    });
  });
}

function openPanel(section) {
  const data = DB[section];
  if (!data) return;

  const panel = $('content-panel');
  const inner = $('panel-inner');

  inner.innerHTML = `
    <div class="p-tag">${data.tag}</div>
    <h2 class="p-title">${data.title}</h2>
    <div class="p-divider"></div>
    ${data.html}
  `;

  $('panel-backdrop').classList.add('active');
  panel.classList.add('open');
  panel.scrollTop = 0;
}

function closePanel() {
  $('content-panel').classList.remove('open');
  $('panel-backdrop').classList.remove('active');
}

/* ────────────────────────────────────────────
   NAVIGATION MOBILE
   ──────────────────────────────────────────── */
function bindMobileNav() {
  const toggle  = $('menu-toggle');
  const nav     = $('mobile-nav');
  const closeBtn= $('mobile-nav-close');

  toggle.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('open');
    toggle.classList.toggle('open', isOpen);
  });

  closeBtn.addEventListener('click', closeMobileNav);
}

function closeMobileNav() {
  $('mobile-nav').classList.remove('open');
  $('menu-toggle').classList.remove('open');
}

/* ────────────────────────────────────────────
   RACCOURCIS CLAVIER
   ──────────────────────────────────────────── */
function bindKeyboard() {
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closePanel();
      closeMobileNav();
    }
  });
}

/* ────────────────────────────────────────────
   NOTE ARCHITECTURE PHP / MYSQL (future version)
   ──────────────────────────────────────────

   L'objet DB ci-dessus simule les futures tables :

   TABLE zones        → id, slug, name, tag, title, color
   TABLE hotspots     → id, zone_id, pos_left, pos_top, label, tooltip
   TABLE articles     → id, zone_id, title, body, created_at, published
   TABLE expressions  → id, word, translation, description, created_at
   TABLE lieux        → id, name, type(bocage|marais|cote), description, lat, lng
   TABLE enigmes      → id, titre, indice, latitude, longitude, statut
   TABLE contributions→ id, type, content, media_url, status, created_at
   TABLE medias       → id, ref_id, ref_type, path, alt
   TABLE admin_users  → id, email, password_hash, role, last_login

   En PHP : chaque section du panneau devient une vue partielle incluse via
   include "views/panel_{$section}.php" après requête PDO.
   Les hotspots sont générés dynamiquement depuis la table hotspots.
   ──────────────────────────────────────────── */
