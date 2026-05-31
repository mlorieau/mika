<?php
// ============================================================
// ZONE85 — Les Échos de la Zone (V11 — DB-connected)
// ============================================================
$page_title       = 'Les Échos de la Zone';
$page_description = 'Le magazine communautaire de Zone85 : histoires, curiosités et nouvelles du territoire vendéen. Ovnis, expressions locales, randos et vie de la Zone.';
$page_canonical   = 'https://www.zone85.fr/les-echos.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil',
         'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Les Échos de la Zone',
         'item' => 'https://www.zone85.fr/les-echos.php'],
    ],
];
$current_page = 'les-echos';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// ── État connexion ─────────────────────────────────────────────
$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['pseudo']);

// ── Rubriques ──────────────────────────────────────────────────
$rubriques_valides = ['ovnis', 'deux-minutes', 'chez-nous', 'chemins', 'communaute', 'archives'];
$rubrique_labels   = [
    'ovnis'        => 'OVNIS Zone85',
    'deux-minutes' => "T'as deux minutes\xc2\xa0?",
    'chez-nous'    => "Chez nous on ne dit pas\xe2\x80\xa6",
    'chemins'      => 'Sur les chemins',
    'communaute'   => 'Communaut\xc3\xa9',
    'archives'     => 'Archives',
];
$rubrique_colors   = [
    'ovnis'        => '#ea5649',
    'deux-minutes' => '#12314e',
    'chez-nous'    => '#2a9d5c',
    'chemins'      => '#b8831a',
    'communaute'   => '#9b59b6',
    'archives'     => '#6b7f96',
];
$rubrique_gradients = [
    'ovnis'        => 'linear-gradient(135deg, #ea5649 0%, #12314e 100%)',
    'deux-minutes' => 'linear-gradient(135deg, #C9962A 0%, #12314e 100%)',
    'chez-nous'    => 'linear-gradient(135deg, #2a9d5c 0%, #163756 100%)',
    'chemins'      => 'linear-gradient(135deg, #b8831a 0%, #12314e 100%)',
    'communaute'   => 'linear-gradient(135deg, #9b59b6 0%, #12314e 100%)',
    'archives'     => 'linear-gradient(135deg, #6b7f96 0%, #333 100%)',
];
$rubrique_emojis = [
    'ovnis'        => '&#x1F6F8;',
    'deux-minutes' => '&#x23F1;',
    'chez-nous'    => '&#x1F5E3;',
    'chemins'      => '&#x1F97E;',
    'communaute'   => '&#x1F465;',
    'archives'     => '&#x1F4DC;',
];

// ── Rubrique active ────────────────────────────────────────────
$rubrique_active = $_GET['rubrique'] ?? 'all';
if ($rubrique_active !== 'all' && !in_array($rubrique_active, $rubriques_valides, true)) {
    $rubrique_active = 'all';
}

// ── Articles statiques de secours ─────────────────────────────
$_articles_statiques = [
    [
        'id'          => 0,
        'slug'        => '',
        'rubrique'    => 'ovnis',
        'title'       => 'La mogette : objet de tous les mystères',
        'excerpt'     => "La mogette vendéenne n'est pas qu'un haricot. C'est un symbole, une identité, un jeu de piste que la Zone se propose de décrypter mission après mission.",
        'author_name' => 'Équipe Zone85',
        'published_at'=> null,
        'cover_image' => null,
    ],
    [
        'id'          => 0,
        'slug'        => '',
        'rubrique'    => 'deux-minutes',
        'title'       => "Kéto Kolé Tché, c'est quoi exactement ?",
        'excerpt'     => "En vendéen populaire, \"Kéto Kolé Tché\" signifie \"Qu'est-ce que c'est que ça ?\" — l'expression parfaite pour notre jeu de devinettes culturelles.",
        'author_name' => 'Équipe Zone85',
        'published_at'=> null,
        'cover_image' => null,
    ],
    [
        'id'          => 0,
        'slug'        => '',
        'rubrique'    => 'chemins',
        'title'       => 'Le bocage vendéen vu depuis les chemins creux',
        'excerpt'     => 'Entre les haies centenaires et les sentes oubliées, le bocage cache des histoires que même les cartes ne montrent pas.',
        'author_name' => 'Équipe Zone85',
        'published_at'=> null,
        'cover_image' => null,
    ],
    [
        'id'          => 0,
        'slug'        => '',
        'rubrique'    => 'chez-nous',
        'title'       => 'Chez nous on ne dit pas "dépêchons-nous"',
        'excerpt'     => '"On va pas se faire prier" — le vendéen a ses propres formules pour exprimer l\'urgence, le refus, la surprise.',
        'author_name' => 'Équipe Zone85',
        'published_at'=> null,
        'cover_image' => null,
    ],
    [
        'id'          => 0,
        'slug'        => '',
        'rubrique'    => 'communaute',
        'title'       => 'Les premiers clans prennent position',
        'excerpt'     => 'Bocage, Littoral, Marais : les trois territoires de la Zone se préparent. Qui seront les premiers à planter leur drapeau ?',
        'author_name' => 'Équipe Zone85',
        'published_at'=> null,
        'cover_image' => null,
    ],
    [
        'id'          => 0,
        'slug'        => '',
        'rubrique'    => 'archives',
        'title'       => 'Bilan de la saison zéro : les bases sont posées',
        'excerpt'     => "Avant le grand lancement, une saison de préparation. Retour sur les tests et les surprises qui ont façonné la Zone.",
        'author_name' => 'Équipe Zone85',
        'published_at'=> null,
        'cover_image' => null,
    ],
];

// ── Chargement depuis la base de données ──────────────────────
$articles_db = [];
if (db_enabled()) {
    $pdo = db();
    if ($pdo) {
        try {
            if ($rubrique_active !== 'all') {
                $stmt = $pdo->prepare(
                    "SELECT * FROM articles WHERE status='published' AND rubrique=:r ORDER BY published_at DESC"
                );
                $stmt->execute([':r' => $rubrique_active]);
            } else {
                $stmt = $pdo->query(
                    "SELECT * FROM articles WHERE status='published' ORDER BY published_at DESC"
                );
            }
            $articles_db = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Table absente ou erreur — le fallback statique prend le relais
        }
    }
}

// Fallback si DB vide
if (empty($articles_db)) {
    if ($rubrique_active !== 'all') {
        $articles_db = array_values(array_filter(
            $_articles_statiques,
            fn($a) => $a['rubrique'] === $rubrique_active
        ));
    } else {
        $articles_db = $_articles_statiques;
    }
}

// ── Styles page ────────────────────────────────────────────────
$page_styles = '<style>

/* ============================================================
   LES ÉCHOS DE LA ZONE — CSS V11
============================================================ */

/* HERO */
.echos-hero {
  background: linear-gradient(160deg, #0c1e2e 0%, #12314e 55%, #163756 100%);
  padding: 110px 0 72px;
  position: relative;
  overflow: hidden;
}
.echos-hero::before {
  content: \'\';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'80\' height=\'80\' viewBox=\'0 0 80 80\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Crect x=\'0\' y=\'0\' width=\'4\' height=\'4\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}
.echos-hero::after {
  content: \'\';
  position: absolute;
  top: -120px; left: -120px;
  width: 500px; height: 500px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(201,150,42,.06) 0%, transparent 70%);
  pointer-events: none;
}
.echos-hero-inner { position: relative; z-index: 1; }
.echos-hero-badge {
  display: inline-block;
  font-size: .68rem; font-weight: 900; letter-spacing: .18em;
  text-transform: uppercase; color: #C9962A;
  border: 1px solid rgba(201,150,42,.4); border-radius: 20px;
  padding: 5px 16px; margin-bottom: 20px;
  background: rgba(201,150,42,.08);
}
.echos-hero h1 {
  font-size: clamp(2.4rem, 6vw, 4rem);
  font-weight: 900; color: #fff; letter-spacing: -2px;
  line-height: 1.0; margin-bottom: 18px;
}
.echos-hero h1 span { color: #C9962A; }
.echos-hero .hero-sub {
  font-size: 1.05rem; color: rgba(255,255,255,.6);
  max-width: 540px; line-height: 1.75;
}

/* TABS RUBRIQUES */
.echos-tabs-bar {
  background: #0c1e2e;
  border-bottom: 1px solid rgba(255,255,255,.08);
  position: sticky; top: 0; z-index: 100;
}
.echos-tabs-inner {
  display: flex; gap: 0;
  overflow-x: auto; -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.echos-tabs-inner::-webkit-scrollbar { display: none; }
.echos-tab {
  display: inline-block; padding: 16px 20px;
  font-size: .78rem; font-weight: 800;
  letter-spacing: .06em; text-transform: uppercase;
  color: rgba(255,255,255,.45); text-decoration: none;
  white-space: nowrap; border-bottom: 2px solid transparent;
  transition: color .2s, border-color .2s; flex-shrink: 0;
}
.echos-tab:hover { color: rgba(255,255,255,.8); }
.echos-tab.active { color: #C9962A; border-bottom-color: #C9962A; }

/* SECTION ARTICLES */
.echos-section { background: var(--beige-light, #f7f4ef); padding: 72px 0 80px; }
.echos-section-header { margin-bottom: 48px; }
.echos-section-label {
  font-size: .7rem; font-weight: 900; letter-spacing: .18em;
  text-transform: uppercase; color: var(--primary, #ea5649);
  margin-bottom: 8px; display: block;
}
.echos-section-title {
  font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 900;
  color: var(--text, #1a1a1a); letter-spacing: -.5px; line-height: 1.2;
}
.echos-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
}

/* CARD */
.echos-card {
  background: #fff; border-radius: 16px; overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  transition: transform .25s, box-shadow .25s;
  display: flex; flex-direction: column;
  text-decoration: none; color: inherit;
}
.echos-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
.echos-card-visual {
  height: 180px; display: flex; align-items: center;
  justify-content: center; font-size: 3rem;
  position: relative; flex-shrink: 0;
  overflow: hidden;
}
.echos-card-visual img {
  width: 100%; height: 100%; object-fit: cover;
  position: absolute; inset: 0;
}
.echos-card-visual .echos-card-emoji {
  position: relative; z-index: 1;
}
.echos-card-rubrique-badge {
  position: absolute; top: 14px; left: 14px; z-index: 2;
  font-size: .64rem; font-weight: 900;
  letter-spacing: .1em; text-transform: uppercase;
  color: #fff; background: rgba(0,0,0,.4);
  border-radius: 20px; padding: 4px 12px; backdrop-filter: blur(4px);
}
.echos-card-body {
  padding: 22px 22px 24px; flex: 1;
  display: flex; flex-direction: column;
}
.echos-card-title {
  font-size: 1.08rem; font-weight: 800; color: var(--text, #1a1a1a);
  line-height: 1.35; margin-bottom: 10px; letter-spacing: -.2px;
}
.echos-card-intro {
  font-size: .87rem; color: var(--text-mid, #555);
  line-height: 1.65; flex: 1; margin-bottom: 18px;
}
.echos-card-footer {
  display: flex; align-items: center; justify-content: space-between;
}
.echos-card-meta {
  font-size: .72rem; font-weight: 600; color: var(--text-muted, #888);
}
.echos-card-cta {
  font-size: .78rem; font-weight: 800;
  color: var(--primary, #ea5649); letter-spacing: .02em;
}

/* EMPTY STATE */
.echos-empty {
  grid-column: 1/-1; text-align: center;
  padding: 64px 24px; color: var(--text-muted, #888);
}
.echos-empty-icon { font-size: 3rem; margin-bottom: 16px; }
.echos-empty-text { font-size: .95rem; line-height: 1.7; }

/* CTA REJOINDRE */
.echos-cta-section {
  background: linear-gradient(160deg, #0c1e2e 0%, #12314e 100%);
  padding: 72px 0;
}
.echos-cta-inner { max-width: 640px; margin: 0 auto; text-align: center; }
.echos-cta-icon { font-size: 2.8rem; margin-bottom: 18px; display: block; }
.echos-cta-title {
  font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 900;
  color: #fff; letter-spacing: -.5px; margin-bottom: 12px;
}
.echos-cta-sub {
  font-size: .95rem; color: rgba(255,255,255,.6);
  line-height: 1.7; margin-bottom: 32px;
}
.echos-cta-btns {
  display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
}
.echos-btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--primary, #ea5649); color: #fff;
  font-size: .88rem; font-weight: 800; padding: 13px 28px;
  border-radius: 10px; text-decoration: none;
  transition: opacity .2s, transform .2s; letter-spacing: .02em;
}
.echos-btn-primary:hover { opacity: .88; transform: translateY(-2px); }
.echos-btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  background: transparent; color: rgba(255,255,255,.8);
  font-size: .88rem; font-weight: 700; padding: 13px 28px;
  border-radius: 10px; text-decoration: none;
  border: 1.5px solid rgba(255,255,255,.2);
  transition: border-color .2s, color .2s;
}
.echos-btn-outline:hover { border-color: rgba(255,255,255,.5); color: #fff; }

/* NOTE ÉDITORIALE */
.echos-note-section {
  background: var(--white, #fff); padding: 52px 0;
  border-top: 1px solid rgba(0,0,0,.06);
}
.echos-note-inner { max-width: 680px; margin: 0 auto; text-align: center; }
.echos-note-icon { font-size: 1.6rem; margin-bottom: 12px; display: block; }
.echos-note-text { font-size: .9rem; color: var(--text-mid, #555); line-height: 1.75; }
.echos-note-text a { color: var(--primary, #ea5649); font-weight: 700; text-decoration: none; }
.echos-note-text a:hover { text-decoration: underline; }

/* RESPONSIVE */
@media (max-width: 900px) {
  .echos-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .echos-hero { padding: 80px 0 52px; }
  .echos-grid { grid-template-columns: 1fr; }
  .echos-tab  { padding: 14px; font-size: .72rem; }
  .echos-cta-section { padding: 52px 0; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="echos-hero">
  <div class="container echos-hero-inner">
    <span class="echos-hero-badge">MAGAZINE COMMUNAUTAIRE</span>
    <h1>LES <span>&Eacute;CHOS</span><br>DE LA ZONE</h1>
    <p class="hero-sub">Histoires, curiosit&eacute;s et nouvelles du territoire vend&eacute;en.</p>
  </div>
</section>

<!-- ===================== TABS RUBRIQUES ===================== -->
<nav class="echos-tabs-bar" aria-label="Rubriques du magazine">
  <div class="container echos-tabs-inner">
    <a href="les-echos.php"
       class="echos-tab <?= $rubrique_active === 'all' ? 'active' : '' ?>">
      Tous
    </a>
    <?php foreach ($rubrique_labels as $slug => $label): ?>
      <a href="les-echos.php?rubrique=<?= $slug ?>"
         class="echos-tab <?= $rubrique_active === $slug ? 'active' : '' ?>">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- ===================== SECTION ARTICLES ===================== -->
<section class="echos-section">
  <div class="container">
    <div class="echos-section-header">
      <span class="echos-section-label">
        <?= $rubrique_active === 'all' ? 'Tous les articles' : 'Rubrique' ?>
      </span>
      <h2 class="echos-section-title">
        <?php
          if ($rubrique_active === 'all') {
              echo 'Les &Eacute;chos de la Zone';
          } else {
              echo htmlspecialchars($rubrique_labels[$rubrique_active] ?? 'Les Échos', ENT_QUOTES, 'UTF-8');
          }
        ?>
      </h2>
    </div>

    <div class="echos-grid">
      <?php if (empty($articles_db)): ?>
        <div class="echos-empty">
          <div class="echos-empty-icon">&#x1F4F0;</div>
          <p class="echos-empty-text">
            Le premier &eacute;cho de la Zone arrive bient&ocirc;t.<br>
            Rejoins la communaut&eacute; pour ne rien rater.
          </p>
          <?php if (!$is_logged_in): ?>
            <a href="inscription.php" class="echos-btn-primary" style="margin-top:20px;display:inline-flex">
              &#x1F331; Rejoindre la Zone
            </a>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <?php foreach ($articles_db as $art): ?>
          <?php
            $rub    = $art['rubrique'] ?? 'ovnis';
            $color  = $rubrique_colors[$rub]    ?? '#ea5649';
            $grad   = $rubrique_gradients[$rub]  ?? 'linear-gradient(135deg,#12314e,#ea5649)';
            $emoji  = $rubrique_emojis[$rub]     ?? '&#x1F4C4;';
            $label  = $rubrique_labels[$rub]     ?? $rub;
            $excerpt = $art['excerpt'] ?? '';
            if (mb_strlen($excerpt) > 120) {
                $excerpt = mb_substr($excerpt, 0, 117) . '…';
            }
            $date_str = '';
            if (!empty($art['published_at'])) {
                $ts = strtotime($art['published_at']);
                if ($ts) {
                    $mois = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sep.','oct.','nov.','déc.'];
                    $date_str = date('j', $ts) . ' ' . $mois[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
                }
            }
            $article_url = '';
            if (!empty($art['slug'])) {
                $article_url = 'les-echos-article.php?slug=' . urlencode($art['slug']);
            } elseif (!empty($art['id'])) {
                $article_url = 'les-echos-article.php?id=' . (int)$art['id'];
            }
          ?>
          <article class="echos-card" <?= $article_url ? '' : '' ?>>
            <div class="echos-card-visual" style="background: <?= $grad ?>">
              <?php if (!empty($art['cover_image'])): ?>
                <img src="<?= htmlspecialchars($art['cover_image'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($art['title'], ENT_QUOTES, 'UTF-8') ?>"
                     loading="lazy">
              <?php else: ?>
                <span class="echos-card-emoji"><?= $emoji ?></span>
              <?php endif; ?>
              <span class="echos-card-rubrique-badge">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <div class="echos-card-body">
              <h3 class="echos-card-title">
                <?= htmlspecialchars($art['title'], ENT_QUOTES, 'UTF-8') ?>
              </h3>
              <?php if ($excerpt): ?>
                <p class="echos-card-intro">
                  <?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php endif; ?>
              <div class="echos-card-footer">
                <span class="echos-card-meta">
                  <?= htmlspecialchars($art['author_name'] ?? 'Équipe Zone85', ENT_QUOTES, 'UTF-8') ?>
                  <?php if ($date_str): ?> &middot; <?= $date_str ?><?php endif; ?>
                </span>
                <?php if ($article_url): ?>
                  <a href="<?= htmlspecialchars($article_url, ENT_QUOTES, 'UTF-8') ?>"
                     class="echos-card-cta">
                    Lire la suite &#x2192;
                  </a>
                <?php else: ?>
                  <span class="echos-card-cta" style="opacity:.4">Bient&ocirc;t &#x2192;</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== CTA REJOINDRE ===================== -->
<section class="echos-cta-section">
  <div class="container echos-cta-inner">
    <span class="echos-cta-icon">&#x1F30E;</span>
    <h2 class="echos-cta-title">
      <?= $is_logged_in ? 'Contribue aux &Eacute;chos de la Zone' : 'Rejoins la Zone pour contribuer' ?>
    </h2>
    <p class="echos-cta-sub">
      <?php if ($is_logged_in): ?>
        Les &Eacute;chos de la Zone se nourrissent des aventures et d&eacute;couvertes de la communaut&eacute;.
        Pars en mission et ram&egrave;ne des histoires.
      <?php else: ?>
        Inscris-toi gratuitement pour faire partie de la communaut&eacute; vend&eacute;enne,
        valider des missions et contribuer au magazine de la Zone.
      <?php endif; ?>
    </p>
    <div class="echos-cta-btns">
      <?php if ($is_logged_in): ?>
        <a href="missions.php" class="echos-btn-primary">&#x1F3AF; Voir les missions</a>
        <a href="hall.php"     class="echos-btn-outline">&#x1F3DB; Hall de la Zone</a>
      <?php else: ?>
        <a href="inscription.php" class="echos-btn-primary">&#x1F331; Rejoindre la Zone</a>
        <a href="concept.php"     class="echos-btn-outline">D&eacute;couvrir le concept</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== NOTE ÉDITORIALE ===================== -->
<section class="echos-note-section">
  <div class="container echos-note-inner">
    <span class="echos-note-icon">&#x270F;&#xFE0F;</span>
    <p class="echos-note-text">
      Les &Eacute;chos de la Zone sont &eacute;crits par l&rsquo;&eacute;quipe Zone85 et les membres de la communaut&eacute;.<br>
      Envie de contribuer&nbsp;? <a href="contact.php">Contact &#x2192;</a>
    </p>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
