<?php
// ============================================================
// ZONE85 — Article individuel des Échos (V11)
// ============================================================
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// ── Paramètres d'entrée ────────────────────────────────────────
$slug = safe_input($_GET['slug'] ?? '', 200);
$id   = (int)($_GET['id'] ?? 0);

// ── Chargement de l'article ────────────────────────────────────
$article = null;
if (db_enabled()) {
    $pdo = db();
    if ($pdo) {
        try {
            if ($slug !== '') {
                $s = $pdo->prepare(
                    "SELECT * FROM articles WHERE slug=:s AND status='published' LIMIT 1"
                );
                $s->execute([':s' => $slug]);
            } else {
                $s = $pdo->prepare(
                    "SELECT * FROM articles WHERE id=:id AND status='published' LIMIT 1"
                );
                $s->execute([':id' => $id]);
            }
            $article = $s->fetch();
        } catch (PDOException $e) {
            // Table absente ou erreur
        }
    }
}

if (!$article) {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/les-echos.php');
    exit;
}

// ── Méta SEO ──────────────────────────────────────────────────
$page_title       = $article['title'] . ' — Les Échos Zone85';
$page_description = $article['excerpt'] ?? '';
$page_og_image    = !empty($article['cover_image'])
                        ? $article['cover_image']
                        : 'assets/img/ZONE852025.png';
$page_robots      = 'index,follow';
$page_canonical   = 'https://www.zone85.fr/les-echos-article.php?slug='
                    . urlencode($article['slug'] ?? '');
$current_page     = 'les-echos';

// ── Données de rubrique ────────────────────────────────────────
$rubrique_labels = [
    'ovnis'        => 'OVNIS Zone85',
    'deux-minutes' => "T'as deux minutes\xc2\xa0?",
    'chez-nous'    => "Chez nous on ne dit pas\xe2\x80\xa6",
    'chemins'      => 'Sur les chemins',
    'communaute'   => 'Communaut\xc3\xa9',
    'archives'     => 'Archives',
];
$rubrique_colors = [
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

$rub        = $article['rubrique'] ?? 'ovnis';
$rub_label  = $rubrique_labels[$rub]     ?? $rub;
$rub_color  = $rubrique_colors[$rub]     ?? '#ea5649';
$rub_grad   = $rubrique_gradients[$rub]  ?? 'linear-gradient(135deg,#12314e,#ea5649)';

// ── Articles connexes (même rubrique) ─────────────────────────
$related = [];
if (db_enabled()) {
    $pdo = db();
    if ($pdo) {
        try {
            $sr = $pdo->prepare(
                "SELECT id, title, slug, excerpt, author_name, published_at, cover_image
                 FROM articles
                 WHERE status='published' AND rubrique=:r AND id <> :id
                 ORDER BY published_at DESC
                 LIMIT 3"
            );
            $sr->execute([':r' => $rub, ':id' => $article['id']]);
            $related = $sr->fetchAll();
        } catch (PDOException $e) { /* silencieux */ }
    }
}

// ── Format de la date de publication ──────────────────────────
$date_fmt = '';
if (!empty($article['published_at'])) {
    $ts = strtotime($article['published_at']);
    if ($ts) {
        $mois = ['janvier','février','mars','avril','mai','juin',
                 'juillet','août','septembre','octobre','novembre','décembre'];
        $date_fmt = date('j', $ts) . ' ' . $mois[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
    }
}

// ── État connexion ─────────────────────────────────────────────
$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['pseudo']);

// ── Styles page ────────────────────────────────────────────────
$page_styles = '<style>

/* ============================================================
   ARTICLE INDIVIDUEL — CSS
============================================================ */

/* HERO ARTICLE — position depuis zone85.css, fond via inline style rubrique */
.article-hero { padding: 120px 0 72px; }
.article-hero-inner { position: relative; z-index: 1; }
.article-rubrique-badge {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: .68rem; font-weight: 900; letter-spacing: .14em;
  text-transform: uppercase; color: #fff;
  border-radius: 20px; padding: 5px 14px; margin-bottom: 20px;
}
.article-hero h1 {
  font-size: clamp(1.8rem, 4.5vw, 3rem); font-weight: 900;
  color: #fff; letter-spacing: -1px; line-height: 1.15;
  margin-bottom: 18px; max-width: 800px;
}
.article-hero-meta {
  display: flex; align-items: center; gap: 20px;
  flex-wrap: wrap; font-size: .85rem; color: rgba(255,255,255,.65);
  font-weight: 600;
}
.article-hero-meta span { display: flex; align-items: center; gap: 6px; }

/* BREADCRUMB */
.article-breadcrumb {
  background: #fff; border-bottom: 1px solid rgba(0,0,0,.06);
  padding: 12px 0;
}
.article-breadcrumb ol {
  list-style: none; margin: 0; padding: 0;
  display: flex; align-items: center; gap: 6px;
  flex-wrap: wrap; font-size: .78rem;
}
.article-breadcrumb li { display: flex; align-items: center; gap: 6px; }
.article-breadcrumb li:not(:last-child)::after {
  content: "›"; color: #aaa; font-size: .8rem;
}
.article-breadcrumb a { color: #6b7f96; text-decoration: none; font-weight: 600; }
.article-breadcrumb a:hover { color: #ea5649; }
.article-breadcrumb .current { color: #1a1a1a; font-weight: 700; }

/* LAYOUT PRINCIPAL */
.article-layout {
  background: var(--beige-light, #f7f4ef);
  padding: 52px 0 72px;
}
.article-layout-inner {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 40px;
  align-items: start;
}

/* CORPS ARTICLE */
.article-body-wrap {
  background: #fff; border-radius: 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  overflow: hidden;
}
.article-cover {
  width: 100%; max-height: 420px;
  object-fit: cover; display: block;
}
.article-body-inner { padding: 36px 40px 40px; }
.article-body-content {
  font-size: 1.02rem; line-height: 1.85;
  color: #2a2a2a; max-width: 680px;
}
.article-body-content p  { margin: 0 0 1.4em; }
.article-body-content h2 {
  font-size: 1.35rem; font-weight: 800; color: #0c1e2e;
  margin: 1.8em 0 .7em; letter-spacing: -.3px;
}
.article-body-content h3 {
  font-size: 1.1rem; font-weight: 700; color: #12314e;
  margin: 1.5em 0 .6em;
}
.article-body-content a { color: #ea5649; font-weight: 600; }
.article-body-content a:hover { opacity: .8; }
.article-body-content ul,
.article-body-content ol {
  margin: 0 0 1.4em 1.4em; padding: 0;
}
.article-body-content li { margin-bottom: .4em; }
.article-body-content strong { font-weight: 800; color: #1a1a1a; }
.article-body-content em { font-style: italic; }
.article-body-content blockquote {
  margin: 1.6em 0; padding: 16px 20px;
  border-left: 3px solid #ea5649;
  background: rgba(234,86,73,.04);
  border-radius: 0 8px 8px 0;
  font-style: italic; color: #444;
}

/* SIDEBAR */
.article-sidebar {}
.article-sidebar-card {
  background: #fff; border-radius: 14px;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  overflow: hidden; margin-bottom: 20px;
}
.article-sidebar-title {
  font-size: .68rem; font-weight: 900; letter-spacing: .14em;
  text-transform: uppercase; color: #6b7f96;
  padding: 16px 20px 0;
}
.article-related-item {
  display: block; padding: 14px 20px;
  text-decoration: none; color: inherit;
  border-bottom: 1px solid rgba(0,0,0,.06);
  transition: background .15s;
}
.article-related-item:last-child { border-bottom: none; }
.article-related-item:hover { background: #faf7f4; }
.article-related-item-title {
  font-size: .88rem; font-weight: 700;
  color: #0c1e2e; line-height: 1.35;
  margin-bottom: 4px;
}
.article-related-item-meta {
  font-size: .72rem; color: #6b7f96; font-weight: 600;
}

/* CTA bas de page */
.article-cta {
  background: linear-gradient(135deg, #0c1e2e 0%, #12314e 100%);
  border-radius: 16px; padding: 36px; margin-top: 8px;
  text-align: center;
}
.article-cta-title {
  font-size: 1.15rem; font-weight: 900; color: #fff;
  margin-bottom: 10px; letter-spacing: -.3px;
}
.article-cta-sub {
  font-size: .85rem; color: rgba(255,255,255,.6);
  line-height: 1.65; margin-bottom: 20px;
}
.article-cta-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: #ea5649; color: #fff;
  font-size: .85rem; font-weight: 800;
  padding: 12px 24px; border-radius: 10px;
  text-decoration: none; transition: opacity .2s;
}
.article-cta-btn:hover { opacity: .88; }

/* RESPONSIVE */
@media (max-width: 960px) {
  .article-layout-inner {
    grid-template-columns: 1fr;
  }
  .article-body-inner { padding: 24px; }
}
@media (max-width: 600px) {
  .article-hero { padding: 80px 0 44px; }
  .article-hero h1 { font-size: 1.7rem; }
  .article-layout { padding: 32px 0 52px; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ARTICLE ===================== -->
<section class="article-hero" style="background: <?= $rub_grad ?>">
  <div class="container article-hero-inner">
    <span class="article-rubrique-badge"
          style="background: rgba(0,0,0,.3); border:1px solid rgba(255,255,255,.2)">
      <?= htmlspecialchars($rub_label, ENT_QUOTES, 'UTF-8') ?>
    </span>
    <h1><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="article-hero-meta">
      <?php if (!empty($article['author_name'])): ?>
        <span>&#x270F;&#xFE0F; <?= htmlspecialchars($article['author_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <?php if ($date_fmt): ?>
        <span>&#x1F4C5; <?= $date_fmt ?></span>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== BREADCRUMB ===================== -->
<nav class="article-breadcrumb" aria-label="Fil d'Ariane">
  <div class="container">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="index.php" itemprop="item"><span itemprop="name">Accueil</span></a>
        <meta itemprop="position" content="1">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="les-echos.php" itemprop="item"><span itemprop="name">Les &Eacute;chos</span></a>
        <meta itemprop="position" content="2">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="les-echos.php?rubrique=<?= urlencode($rub) ?>" itemprop="item">
          <span itemprop="name"><?= htmlspecialchars($rub_label, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <meta itemprop="position" content="3">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <span class="current" itemprop="name">
          <?= htmlspecialchars(mb_strimwidth($article['title'], 0, 60, '…'), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <meta itemprop="position" content="4">
      </li>
    </ol>
  </div>
</nav>

<!-- ===================== CONTENU ===================== -->
<section class="article-layout">
  <div class="container">
    <div class="article-layout-inner">

      <!-- Corps principal -->
      <div class="article-body-wrap">
        <?php if (!empty($article['cover_image'])): ?>
          <img
            class="article-cover"
            src="<?= htmlspecialchars($article['cover_image'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>"
            loading="lazy">
        <?php endif; ?>

        <div class="article-body-inner">
          <?php if (!empty($article['excerpt'])): ?>
            <p style="
              font-size:1.1rem;font-weight:600;color:#444;line-height:1.7;
              padding-bottom:24px;margin-bottom:24px;
              border-bottom:2px solid rgba(0,0,0,.06);
            ">
              <?= htmlspecialchars($article['excerpt'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          <?php endif; ?>

          <div class="article-body-content">
            <?php
              $body = $article['body'] ?? '';
              // Si le body ne contient pas de balises HTML, on applique nl2br
              if ($body !== '' && strip_tags($body) === $body) {
                  echo nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
              } else {
                  // Body HTML stocké en base — on fait confiance à l'admin
                  echo $body;
              }
            ?>
          </div>

          <!-- Pied d'article -->
          <div style="
            margin-top:40px;padding-top:24px;
            border-top:1px solid rgba(0,0,0,.06);
            display:flex;align-items:center;justify-content:space-between;
            flex-wrap:wrap;gap:12px;
          ">
            <div style="font-size:.82rem;color:#6b7f96">
              <span style="font-weight:700;color:#0c1e2e">
                <?= htmlspecialchars($article['author_name'] ?? 'Équipe Zone85', ENT_QUOTES, 'UTF-8') ?>
              </span>
              <?php if ($date_fmt): ?>
                &mdash; <?= $date_fmt ?>
              <?php endif; ?>
            </div>
            <a href="les-echos.php?rubrique=<?= urlencode($rub) ?>"
               style="font-size:.82rem;font-weight:700;color:#ea5649;text-decoration:none">
              &larr; Retour &agrave; <?= htmlspecialchars($rub_label, ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <aside class="article-sidebar">

        <?php if (!empty($related)): ?>
          <div class="article-sidebar-card">
            <p class="article-sidebar-title">Dans la m&ecirc;me rubrique</p>
            <?php foreach ($related as $rel): ?>
              <?php
                $rel_url = !empty($rel['slug'])
                    ? 'les-echos-article.php?slug=' . urlencode($rel['slug'])
                    : 'les-echos-article.php?id=' . (int)($rel['id'] ?? 0);
                $rel_date = '';
                if (!empty($rel['published_at'])) {
                    $ts2 = strtotime($rel['published_at']);
                    if ($ts2) {
                        $mois = ['jan.','fév.','mars','avr.','mai','juin',
                                 'juil.','août','sep.','oct.','nov.','déc.'];
                        $rel_date = date('j', $ts2) . ' ' . $mois[(int)date('n', $ts2)-1] . ' ' . date('Y', $ts2);
                    }
                }
              ?>
              <a href="<?= htmlspecialchars($rel_url, ENT_QUOTES, 'UTF-8') ?>"
                 class="article-related-item">
                <div class="article-related-item-title">
                  <?= htmlspecialchars($rel['title'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="article-related-item-meta">
                  <?= htmlspecialchars($rel['author_name'] ?? 'Équipe Zone85', ENT_QUOTES, 'UTF-8') ?>
                  <?php if ($rel_date): ?>&nbsp;&middot; <?= $rel_date ?><?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- CTA si non connecté -->
        <?php if (!$is_logged_in): ?>
          <div class="article-cta">
            <p class="article-cta-title">Rejoins la Zone</p>
            <p class="article-cta-sub">
              Inscription gratuite. Valide des missions, gagne des points, contribue aux &Eacute;chos.
            </p>
            <a href="inscription.php" class="article-cta-btn">
              &#x1F331; Je rejoins
            </a>
          </div>
        <?php else: ?>
          <div class="article-cta">
            <p class="article-cta-title">Explore les missions</p>
            <p class="article-cta-sub">
              Pars en mission sur le territoire vend&eacute;en et ram&egrave;ne des histoires pour les &Eacute;chos.
            </p>
            <a href="missions.php" class="article-cta-btn">
              &#x1F3AF; Voir les missions
            </a>
          </div>
        <?php endif; ?>

      </aside>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
