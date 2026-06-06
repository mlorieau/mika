<?php
// ============================================================
// ZONE85 — Design System Showcase
// Documentation visuelle de tous les composants disponibles.
// Accès réservé aux admins.
// ============================================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/admin.php';

if (!is_admin()) {
    header('Location: login.php');
    exit;
}

$page_title   = 'Design System — Zone85';
$page_robots  = 'noindex,nofollow';
$current_page = '';

$page_styles = '<link rel="stylesheet" href="' . CSS_PATH . 'zone85-design-system.css">
<style>
.dsg-wrap { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; padding-top: 70px; }
.dsg-sidebar { position: sticky; top: 70px; height: calc(100vh - 70px); overflow-y: auto; background: #fff; border-right: 1.5px solid var(--ds-beige-dark); padding: 24px 0; }
.dsg-sidebar-title { font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .14em; color: var(--ds-text-muted); padding: 0 20px 8px; }
.dsg-sidebar a { display: block; padding: 7px 20px; font-size: .82rem; font-weight: 600; color: var(--ds-text-muted); text-decoration: none; transition: color .15s, background .15s; border-left: 2px solid transparent; }
.dsg-sidebar a:hover { color: var(--ds-primary); background: var(--ds-beige); }
.dsg-sidebar a.active { color: var(--ds-primary); border-left-color: var(--ds-primary); background: rgba(234,86,73,.05); }
.dsg-content { padding: 40px 48px 80px; max-width: 900px; }
.dsg-section { margin-bottom: 64px; }
.dsg-section-title { font-size: 1.4rem; font-weight: 900; color: var(--ds-navy); letter-spacing: -.03em; margin-bottom: 8px; padding-bottom: 12px; border-bottom: 2px solid var(--ds-beige-dark); }
.dsg-section-sub { font-size: .88rem; color: var(--ds-text-muted); margin-bottom: 28px; }
.dsg-example { background: var(--ds-beige); border-radius: var(--ds-radius-lg); padding: 28px; margin-bottom: 16px; border: 1.5px solid var(--ds-beige-dark); }
.dsg-example-dark { background: linear-gradient(135deg, #060e16, #0c1e2e); }
.dsg-label { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--ds-text-muted); margin-bottom: 10px; display: block; }
.dsg-code { background: #f4f4f4; border-radius: 6px; padding: 10px 14px; font-size: .78rem; font-family: monospace; color: #333; margin-top: 10px; overflow-x: auto; white-space: pre; }
/* Color swatches */
.dsg-swatches { display: flex; flex-wrap: wrap; gap: 12px; }
.dsg-swatch { width: 80px; text-align: center; }
.dsg-swatch-color { width: 80px; height: 56px; border-radius: var(--ds-radius); border: 1.5px solid var(--ds-beige-dark); margin-bottom: 6px; }
.dsg-swatch-name { font-size: .65rem; font-weight: 700; color: var(--ds-text-muted); }
/* Typography scale */
.dsg-type-scale { display: flex; flex-direction: column; gap: 12px; }
.dsg-type-row { display: flex; align-items: baseline; gap: 16px; }
.dsg-type-size { font-size: .65rem; font-weight: 700; color: var(--ds-text-muted); width: 52px; flex-shrink: 0; }
/* Section swatches */
.dsg-section-swatches { display: flex; gap: 16px; flex-wrap: wrap; }
.dsg-section-swatch { flex: 1; min-width: 180px; border-radius: var(--ds-radius-lg); padding: 24px 20px; font-size: .82rem; font-weight: 700; }
@media(max-width:900px) { .dsg-wrap { grid-template-columns: 1fr; } .dsg-sidebar { display: none; } .dsg-content { padding: 24px 20px 60px; } }
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="dsg-wrap">

  <!-- ── Sidebar ────────────────────────────────────────────── -->
  <aside class="dsg-sidebar">
    <div class="dsg-sidebar-title">Design System</div>
    <a href="#tokens">Tokens</a>
    <a href="#hero">Hero</a>
    <a href="#buttons">Boutons</a>
    <a href="#badges">Badges</a>
    <a href="#cards">Cartes</a>
    <div class="dsg-sidebar-title" style="margin-top:12px;">Blocs CMS</div>
    <a href="#blocks-text">Bloc Texte</a>
    <a href="#blocks-image">Bloc Image</a>
    <a href="#blocks-button">Bloc Bouton</a>
    <a href="#blocks-cards">Bloc Cartes Info</a>
    <a href="#blocks-faq">Bloc FAQ</a>
    <a href="#blocks-quote">Bloc Citation</a>
    <a href="#blocks-divider">Séparateurs</a>
    <div class="dsg-sidebar-title" style="margin-top:12px;">Mise en page</div>
    <a href="#sections">Sections</a>
  </aside>

  <!-- ── Content ────────────────────────────────────────────── -->
  <main class="dsg-content ds-page">

    <!-- ═══════════════════════════════════════════════════════
         TOKENS
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="tokens">
      <div class="dsg-section-title">Tokens de design</div>
      <p class="dsg-section-sub">Variables CSS fondamentales du design system. Préfixe <code>--ds-</code>.</p>

      <span class="dsg-label">Couleurs</span>
      <div class="dsg-example">
        <div class="dsg-swatches">
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-primary)"></div>
            <div class="dsg-swatch-name">primary<br>#ea5649</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-primary-dark)"></div>
            <div class="dsg-swatch-name">primary-dark<br>#c0392b</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-navy)"></div>
            <div class="dsg-swatch-name">navy<br>#0c1e2e</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-navy-mid)"></div>
            <div class="dsg-swatch-name">navy-mid<br>#163756</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-beige)"></div>
            <div class="dsg-swatch-name">beige<br>#f0ece7</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-beige-dark)"></div>
            <div class="dsg-swatch-name">beige-dark<br>#e0ddd5</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-text)"></div>
            <div class="dsg-swatch-name">text<br>#0f1e2d</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-text-muted)"></div>
            <div class="dsg-swatch-name">text-muted<br>#6b7f96</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-gold)"></div>
            <div class="dsg-swatch-name">gold<br>#d4af37</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-purple)"></div>
            <div class="dsg-swatch-name">purple<br>#7b47b2</div>
          </div>
          <div class="dsg-swatch">
            <div class="dsg-swatch-color" style="background:var(--ds-green)"></div>
            <div class="dsg-swatch-name">green<br>#2a9d5c</div>
          </div>
        </div>
      </div>
      <div class="dsg-code">--ds-primary: #ea5649;  --ds-navy: #0c1e2e;  --ds-beige: #f0ece7;
--ds-gold: #d4af37;  --ds-purple: #7b47b2;  --ds-green: #2a9d5c;</div>

      <span class="dsg-label" style="margin-top:24px;">Échelle typographique</span>
      <div class="dsg-example">
        <div class="dsg-type-scale">
          <div class="dsg-type-row"><span class="dsg-type-size">ds-h1</span><span class="ds-h1" style="margin:0">Titre H1</span></div>
          <div class="dsg-type-row"><span class="dsg-type-size">ds-h2</span><span class="ds-h2" style="margin:0">Titre H2</span></div>
          <div class="dsg-type-row"><span class="dsg-type-size">ds-h3</span><span class="ds-h3" style="margin:0">Titre H3</span></div>
          <div class="dsg-type-row"><span class="dsg-type-size">ds-lead</span><span class="ds-lead" style="margin:0">Texte lead — accroche principale de section</span></div>
          <div class="dsg-type-row"><span class="dsg-type-size">ds-body</span><span class="ds-body">Corps de texte secondaire, muted</span></div>
          <div class="dsg-type-row"><span class="dsg-type-size">ds-eyebrow</span><span class="ds-eyebrow" style="margin:0">Eyebrow label</span></div>
        </div>
      </div>
      <div class="dsg-code">&lt;h1 class="ds-h1"&gt;…&lt;/h1&gt;
&lt;h2 class="ds-h2"&gt;…&lt;/h2&gt;
&lt;p class="ds-lead"&gt;…&lt;/p&gt;
&lt;p class="ds-body"&gt;…&lt;/p&gt;
&lt;span class="ds-eyebrow"&gt;…&lt;/span&gt;</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         HERO
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="hero">
      <div class="dsg-section-title">Hero</div>
      <p class="dsg-section-sub">Section d'en-tête plein-écran sur fond navy dégradé. Classe <code>.ds-hero</code>.</p>

      <span class="dsg-label">Exemple</span>
      <div class="dsg-example" style="padding:0;overflow:hidden;border-radius:var(--ds-radius-lg);">
        <section class="ds-hero" style="padding:60px 0 40px;border-radius:var(--ds-radius-lg);">
          <div class="ds-container ds-hero-inner">
            <span class="ds-hero-eyebrow">Zone85 · Vendée</span>
            <h1 class="ds-hero-title">La Vendée qui joue<br>et qui <em>s'aventure</em></h1>
            <p class="ds-hero-sub">Rejoins un clan, accomplis des missions, fais vivre ta région autrement.</p>
            <div class="ds-hero-actions">
              <a href="#" class="ds-btn ds-btn-primary">Rejoindre →</a>
              <a href="#" class="ds-btn ds-btn-ghost">En savoir plus</a>
            </div>
          </div>
        </section>
      </div>
      <div class="dsg-code">&lt;section class="ds-hero"&gt;
  &lt;div class="ds-container ds-hero-inner"&gt;
    &lt;span class="ds-hero-eyebrow"&gt;Accroche&lt;/span&gt;
    &lt;h1 class="ds-hero-title"&gt;Titre &lt;em&gt;coloré&lt;/em&gt;&lt;/h1&gt;
    &lt;p class="ds-hero-sub"&gt;Sous-titre…&lt;/p&gt;
    &lt;div class="ds-hero-actions"&gt;
      &lt;a class="ds-btn ds-btn-primary"&gt;…&lt;/a&gt;
    &lt;/div&gt;
  &lt;/div&gt;
&lt;/section&gt;</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BOUTONS
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="buttons">
      <div class="dsg-section-title">Boutons</div>
      <p class="dsg-section-sub">Toutes les variantes de boutons. Classe de base <code>.ds-btn</code>.</p>

      <span class="dsg-label">Variantes principales</span>
      <div class="dsg-example" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <a href="#" class="ds-btn ds-btn-primary">Primary →</a>
        <a href="#" class="ds-btn ds-btn-secondary">Secondary →</a>
      </div>
      <div class="dsg-code">ds-btn ds-btn-primary
ds-btn ds-btn-secondary</div>

      <span class="dsg-label" style="margin-top:20px;">Sur fond sombre (ghost &amp; white)</span>
      <div class="dsg-example dsg-example-dark" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <a href="#" class="ds-btn ds-btn-ghost">Ghost →</a>
        <a href="#" class="ds-btn ds-btn-white">White →</a>
      </div>
      <div class="dsg-code">ds-btn ds-btn-ghost   (fond sombre)
ds-btn ds-btn-white   (fond sombre)</div>

      <span class="dsg-label" style="margin-top:20px;">Tailles</span>
      <div class="dsg-example" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        <a href="#" class="ds-btn ds-btn-primary ds-btn-sm">Small →</a>
        <a href="#" class="ds-btn ds-btn-primary">Normal →</a>
        <a href="#" class="ds-btn ds-btn-primary ds-btn-lg">Large →</a>
      </div>
      <div class="dsg-code">ds-btn ds-btn-primary ds-btn-sm
ds-btn ds-btn-primary
ds-btn ds-btn-primary ds-btn-lg</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BADGES
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="badges">
      <div class="dsg-section-title">Badges</div>
      <p class="dsg-section-sub">Étiquettes inline pour statuts, catégories, tags. Classe de base <code>.ds-badge</code>.</p>

      <div class="dsg-example" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <span class="ds-badge ds-badge-primary">Primary</span>
        <span class="ds-badge ds-badge-muted">Muted</span>
        <span class="ds-badge ds-badge-success">Success</span>
        <span class="ds-badge ds-badge-navy">Navy</span>
        <span class="ds-badge ds-badge-soon">Bientôt</span>
      </div>
      <div class="dsg-code">ds-badge ds-badge-primary
ds-badge ds-badge-muted
ds-badge ds-badge-success
ds-badge ds-badge-navy
ds-badge ds-badge-soon</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         CARTES
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="cards">
      <div class="dsg-section-title">Cartes</div>
      <p class="dsg-section-sub">Carte générique avec hover et info-card pour les blocs CMS.</p>

      <span class="dsg-label">Carte générique — <code>.ds-card</code></span>
      <div class="dsg-example">
        <div class="ds-grid ds-grid-2">
          <div class="ds-card">
            <span class="ds-eyebrow">Catégorie</span>
            <h3 class="ds-h3">Titre de la carte</h3>
            <p class="ds-body">Contenu secondaire de la carte. Peut contenir du texte descriptif court.</p>
          </div>
          <div class="ds-card">
            <span class="ds-eyebrow">Catégorie</span>
            <h3 class="ds-h3">Autre carte</h3>
            <p class="ds-body">Deuxième exemple de carte dans une grille à deux colonnes.</p>
          </div>
        </div>
      </div>
      <div class="dsg-code">&lt;div class="ds-card"&gt;
  &lt;span class="ds-eyebrow"&gt;…&lt;/span&gt;
  &lt;h3 class="ds-h3"&gt;…&lt;/h3&gt;
  &lt;p class="ds-body"&gt;…&lt;/p&gt;
&lt;/div&gt;</div>

      <span class="dsg-label" style="margin-top:24px;">Info-card — <code>.ds-info-card</code></span>
      <div class="dsg-example">
        <div class="ds-grid ds-grid-3">
          <div class="ds-info-card">
            <span class="ds-info-card-icon">🗺️</span>
            <div class="ds-info-card-title">Explorez la Vendée</div>
            <p class="ds-info-card-body">Partez à la découverte de nouveaux lieux à chaque mission.</p>
          </div>
          <div class="ds-info-card">
            <span class="ds-info-card-icon">🏆</span>
            <div class="ds-info-card-title">Gagnez des XP</div>
            <p class="ds-info-card-body">Chaque action rapporte des points d'expérience et des récompenses.</p>
          </div>
          <div class="ds-info-card">
            <span class="ds-info-card-icon">👥</span>
            <div class="ds-info-card-title">Rejoignez un clan</div>
            <p class="ds-info-card-body">Bocage, Littoral ou Marais — choisissez votre camp et progressez ensemble.</p>
          </div>
        </div>
      </div>
      <div class="dsg-code">&lt;div class="ds-info-card"&gt;
  &lt;span class="ds-info-card-icon"&gt;🗺️&lt;/span&gt;
  &lt;div class="ds-info-card-title"&gt;…&lt;/div&gt;
  &lt;p class="ds-info-card-body"&gt;…&lt;/p&gt;
&lt;/div&gt;</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BLOC TEXTE
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-text">
      <div class="dsg-section-title">Bloc Texte</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>text</code>. Variante <code>large</code> pour le corps principal.</p>

      <span class="dsg-label">Normal</span>
      <div class="dsg-example">
        <div class="ds-block ds-block-text">
          <p>Zone85 est un terrain de jeu communautaire ancré en Vendée. Chaque membre rejoins un clan territorial et accumule de l'expérience en participant à des missions variées.</p>
          <p>Les missions couvrent quiz, photos, randos, enquêtes et bien plus encore — toujours en lien avec le territoire vendéen.</p>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-text</div>

      <span class="dsg-label" style="margin-top:20px;">Large</span>
      <div class="dsg-example">
        <div class="ds-block ds-block-text ds-text-large">
          <p>Un texte plus grand pour accrocher le lecteur dès l'entrée de page. Idéal pour les introductions ou les accroches de section importantes.</p>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-text ds-text-large</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BLOC IMAGE
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-image">
      <div class="dsg-section-title">Bloc Image</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>image</code>. Supporte une légende optionnelle.</p>

      <div class="dsg-example">
        <div class="ds-block ds-block-image">
          <figure>
            <img src="https://placehold.co/860x360/f0ece7/6b7f96?text=Image+Bloc" alt="Image d'exemple" loading="lazy">
            <figcaption>Légende optionnelle — description de l'image pour l'accessibilité.</figcaption>
          </figure>
        </div>
      </div>
      <div class="dsg-code">&lt;div class="ds-block ds-block-image"&gt;
  &lt;figure&gt;
    &lt;img src="…" alt="…" loading="lazy"&gt;
    &lt;figcaption&gt;Légende…&lt;/figcaption&gt;
  &lt;/figure&gt;
&lt;/div&gt;</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BLOC BOUTON
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-button">
      <div class="dsg-section-title">Bloc Bouton</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>button</code>. Alignement gauche ou centré.</p>

      <span class="dsg-label">Alignement gauche (défaut)</span>
      <div class="dsg-example">
        <div class="ds-block ds-block-buttons">
          <a href="#" class="ds-btn ds-btn-primary">Découvrir les missions →</a>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-buttons</div>

      <span class="dsg-label" style="margin-top:20px;">Centré</span>
      <div class="dsg-example">
        <div class="ds-block ds-block-buttons ds-align-center">
          <a href="#" class="ds-btn ds-btn-secondary">Voir tous les clans →</a>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-buttons ds-align-center</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BLOC CARTES INFO
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-cards">
      <div class="dsg-section-title">Bloc Cartes Info</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>info_card</code>. Grille auto-responsive.</p>

      <div class="dsg-example">
        <div class="ds-block ds-block-cards">
          <div class="ds-cards-grid">
            <div class="ds-info-card">
              <span class="ds-info-card-icon">🥾</span>
              <div class="ds-info-card-title">Randonnées</div>
              <p class="ds-info-card-body">Explorez les sentiers vendéens et gagnez des points à chaque parcours validé.</p>
            </div>
            <div class="ds-info-card">
              <span class="ds-info-card-icon">🔍</span>
              <div class="ds-info-card-title">Enquêtes</div>
              <p class="ds-info-card-body">Résolvez des mystères locaux et débloquez des récompenses exclusives.</p>
            </div>
            <div class="ds-info-card">
              <span class="ds-info-card-icon">📸</span>
              <div class="ds-info-card-title">Défis photo</div>
              <p class="ds-info-card-body">Capturez la beauté du territoire et partagez vos clichés avec la communauté.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-cards
  └── ds-cards-grid
        └── ds-info-card (× n)</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BLOC FAQ
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-faq">
      <div class="dsg-section-title">Bloc FAQ</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>faq</code>. Accordéon fonctionnel via toggle de classe <code>open</code>.</p>

      <div class="dsg-example">
        <div class="ds-block ds-block-faq">
          <div class="ds-faq-item open">
            <div class="ds-faq-q" onclick="this.closest('.ds-faq-item').classList.toggle('open')">
              <span class="ds-faq-q-text">Comment rejoindre Zone85 ?</span>
              <span class="ds-faq-chevron">▾</span>
            </div>
            <div class="ds-faq-a">
              Créez un compte gratuitement depuis la page d'inscription, choisissez votre clan territorial et commencez à participer aux missions dès votre première connexion.
            </div>
          </div>
          <div class="ds-faq-item">
            <div class="ds-faq-q" onclick="this.closest('.ds-faq-item').classList.toggle('open')">
              <span class="ds-faq-q-text">Les missions sont-elles disponibles toute l'année ?</span>
              <span class="ds-faq-chevron">▾</span>
            </div>
            <div class="ds-faq-a">
              Oui ! De nouvelles missions apparaissent régulièrement. Certaines sont liées à des saisons spéciales avec des récompenses limitées.
            </div>
          </div>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-faq
  └── ds-faq-item [.open pour déplié]
        ├── ds-faq-q (onclick toggle)
        │     ├── ds-faq-q-text
        │     └── ds-faq-chevron (▾)
        └── ds-faq-a</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         BLOC CITATION
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-quote">
      <div class="dsg-section-title">Bloc Citation</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>quote</code>. Bordure primary à gauche, fond beige.</p>

      <div class="dsg-example">
        <div class="ds-block ds-block-quote">
          <p class="ds-quote-text">La Vendée ne se visite pas, elle se vit. Zone85 m'a redonné envie d'explorer mon propre département.</p>
          <cite class="ds-quote-author">Marie L., Bocage</cite>
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-quote
  ├── ds-quote-text   (::before/::after guillemets auto)
  └── ds-quote-author (optionnel)</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         SÉPARATEURS
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="blocks-divider">
      <div class="dsg-section-title">Séparateurs</div>
      <p class="dsg-section-sub">Bloc CMS de type <code>divider</code>. Styles <code>thin</code> et <code>gradient</code>.</p>

      <span class="dsg-label">Thin</span>
      <div class="dsg-example">
        <div class="ds-block ds-block-divider">
          <hr class="ds-divider-thin">
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-divider
  └── hr.ds-divider-thin</div>

      <span class="dsg-label" style="margin-top:20px;">Gradient</span>
      <div class="dsg-example">
        <div class="ds-block ds-block-divider">
          <hr class="ds-divider-gradient">
        </div>
      </div>
      <div class="dsg-code">ds-block ds-block-divider
  └── hr.ds-divider-gradient</div>
    </section>

    <!-- ═══════════════════════════════════════════════════════
         SECTIONS
    ════════════════════════════════════════════════════════ -->
    <section class="dsg-section" id="sections">
      <div class="dsg-section-title">Sections</div>
      <p class="dsg-section-sub">Wrappers de section avec fond et espacement. Toujours combinés avec <code>.ds-container</code> à l'intérieur.</p>

      <div class="dsg-section-swatches">
        <div class="dsg-section-swatch" style="background:var(--ds-white);border:1.5px solid var(--ds-beige-dark);color:var(--ds-text);">
          <code>ds-section</code><br>
          <small style="font-weight:400;color:var(--ds-text-muted)">Fond blanc · padding 80px</small>
        </div>
        <div class="dsg-section-swatch" style="background:var(--ds-beige);border:1.5px solid var(--ds-beige-dark);color:var(--ds-text);">
          <code>ds-section ds-section-beige</code><br>
          <small style="font-weight:400;color:var(--ds-text-muted)">Fond beige · padding 80px</small>
        </div>
        <div class="dsg-section-swatch" style="background:linear-gradient(155deg,#060e16,#0c1e2e);color:#fff;">
          <code style="color:rgba(255,255,255,.7)">ds-section ds-section-navy</code><br>
          <small style="font-weight:400;color:rgba(255,255,255,.5)">Fond navy dégradé · padding 80px</small>
        </div>
      </div>
      <div class="dsg-code">&lt;section class="ds-section"&gt;
  &lt;div class="ds-container"&gt;…&lt;/div&gt;
&lt;/section&gt;

&lt;section class="ds-section ds-section-beige"&gt;…&lt;/section&gt;
&lt;section class="ds-section ds-section-navy"&gt;…&lt;/section&gt;</div>
    </section>

  </main>
</div>

<script>
// ── Scroll-spy sidebar ────────────────────────────────────────
(function () {
  var links = document.querySelectorAll('.dsg-sidebar a');
  var sections = [];

  links.forEach(function (link) {
    var href = link.getAttribute('href');
    if (href && href.startsWith('#')) {
      var target = document.querySelector(href);
      if (target) {
        sections.push({ el: target, link: link });
      }
    }
  });

  if (!sections.length || !('IntersectionObserver' in window)) return;

  var activeLink = null;

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        var found = sections.find(function (s) { return s.el === entry.target; });
        if (found) {
          if (activeLink) activeLink.classList.remove('active');
          found.link.classList.add('active');
          activeLink = found.link;
        }
      }
    });
  }, {
    rootMargin: '-20% 0px -70% 0px',
    threshold: 0,
  });

  sections.forEach(function (s) { observer.observe(s.el); });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
