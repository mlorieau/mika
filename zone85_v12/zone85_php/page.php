<?php
// ============================================================
// ZONE85 — Rendu dynamique de page CMS
// Lit un enregistrement de la table `pages` et le rend
// en utilisant les composants du design system.
// ============================================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/admin.php';

// ── Slug ──────────────────────────────────────────────────────
$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: index.php');
    exit;
}

// ── Fetch DB ──────────────────────────────────────────────────
$row = null;
$pdo = db();
if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('[ZONE85 page.php] ' . $e->getMessage());
    }
}

// ── 404 ───────────────────────────────────────────────────────
if (!$row) {
    http_response_code(404);
    $page_title       = 'Page introuvable — Zone85';
    $page_description = '';
    $page_robots      = 'noindex,nofollow';
    $current_page     = '';
    $page_styles      = '<link rel="stylesheet" href="' . CSS_PATH . 'zone85-design-system.css">';
    require_once 'includes/header.php';
    require_once 'includes/nav.php';
    ?>
<div class="ds-page">
  <section class="ds-section ds-section-beige">
    <div class="ds-container" style="text-align:center;padding-top:80px;padding-bottom:80px;">
      <p class="ds-eyebrow">Erreur 404</p>
      <h1 class="ds-h1">Page introuvable</h1>
      <p class="ds-body" style="max-width:480px;margin:0 auto 32px;">
        La page que vous cherchez n'existe pas ou a été déplacée.
      </p>
      <a href="index.php" class="ds-btn ds-btn-primary">Retour à l'accueil →</a>
    </div>
  </section>
</div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// ── Brouillon : accès restreint ────────────────────────────────
if (($row['status'] ?? '') === 'draft') {
    $preview_allowed = is_admin() || (isset($_GET['preview']) && is_admin());
    if (!$preview_allowed) {
        http_response_code(403);
        $page_title       = 'Page non disponible — Zone85';
        $page_description = '';
        $page_robots      = 'noindex,nofollow';
        $current_page     = '';
        $page_styles      = '<link rel="stylesheet" href="' . CSS_PATH . 'zone85-design-system.css">';
        require_once 'includes/header.php';
        require_once 'includes/nav.php';
        ?>
<div class="ds-page">
  <section class="ds-section ds-section-beige">
    <div class="ds-container" style="text-align:center;padding-top:80px;padding-bottom:80px;">
      <p class="ds-eyebrow">Brouillon</p>
      <h1 class="ds-h1">Page non disponible</h1>
      <p class="ds-body" style="max-width:480px;margin:0 auto 32px;">
        Cette page est en cours de rédaction et n'est pas encore accessible au public.
      </p>
      <a href="index.php" class="ds-btn ds-btn-primary">Retour à l'accueil →</a>
    </div>
  </section>
</div>
        <?php
        require_once 'includes/footer.php';
        exit;
    }
}

// ── Variables SEO ─────────────────────────────────────────────
$page_title       = ($row['meta_title'] ?? '') !== '' ? $row['meta_title'] : $row['title'];
$page_description = $row['meta_description'] ?? '';
$page_robots      = ($row['status'] ?? '') === 'published' ? 'index,follow' : 'noindex,nofollow';
$page_canonical   = 'https://www.zone85.fr/page.php?slug=' . urlencode($slug);
$current_page     = '';

$page_styles = '<link rel="stylesheet" href="' . CSS_PATH . 'zone85-design-system.css">';

// ── Décodage des blocs ────────────────────────────────────────
$blocks = json_decode($row['content_blocks'] ?? '[]', true) ?: [];

// ── Fonction render_block ─────────────────────────────────────
function render_block(array $block): string
{
    $type = $block['type'] ?? '';

    switch ($type) {

        // ── Texte ──────────────────────────────────────────────
        case 'text': {
            $size    = ($block['size'] ?? 'normal') === 'large' ? ' ds-text-large' : '';
            $content = trim($block['content'] ?? '');
            $paras   = preg_split('/\n\n+/', $content);
            $html    = '<div class="ds-block ds-block-text' . $size . '">';
            foreach ($paras as $para) {
                $para = trim($para);
                if ($para !== '') {
                    $html .= '<p>' . e($para) . '</p>';
                }
            }
            $html .= '</div>';
            return $html;
        }

        // ── Image ──────────────────────────────────────────────
        case 'image': {
            $url     = $block['url']     ?? '';
            $alt     = $block['alt']     ?? '';
            $caption = trim($block['caption'] ?? '');
            $html    = '<div class="ds-block ds-block-image">'
                     . '<figure>'
                     . '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy">';
            if ($caption !== '') {
                $html .= '<figcaption>' . e($caption) . '</figcaption>';
            }
            $html .= '</figure></div>';
            return $html;
        }

        // ── Bouton ─────────────────────────────────────────────
        case 'button': {
            $label = $block['label'] ?? '';
            $url   = $block['url']   ?? '#';
            $style = $block['style'] ?? 'primary';
            $align = ($block['align'] ?? 'left') === 'center' ? ' ds-align-center' : '';
            return '<div class="ds-block ds-block-buttons' . $align . '">'
                 . '<a href="' . e($url) . '" class="ds-btn ds-btn-' . e($style) . '">' . e($label) . ' →</a>'
                 . '</div>';
        }

        // ── Cartes info ────────────────────────────────────────
        case 'info_card': {
            $cards = $block['cards'] ?? [];
            $html  = '<div class="ds-block ds-block-cards"><div class="ds-cards-grid">';
            foreach ($cards as $card) {
                $html .= '<div class="ds-info-card">'
                       . '<span class="ds-info-card-icon">' . e($card['icon']  ?? '') . '</span>'
                       . '<div class="ds-info-card-title">'  . e($card['title'] ?? '') . '</div>'
                       . '<p class="ds-info-card-body">'     . e($card['body']  ?? '') . '</p>'
                       . '</div>';
            }
            $html .= '</div></div>';
            return $html;
        }

        // ── FAQ ────────────────────────────────────────────────
        case 'faq': {
            $items = $block['items'] ?? [];
            $html  = '<div class="ds-block ds-block-faq">';
            foreach ($items as $item) {
                $html .= '<div class="ds-faq-item">'
                       . '<div class="ds-faq-q" onclick="this.closest(\'.ds-faq-item\').classList.toggle(\'open\')">'
                       . '<span class="ds-faq-q-text">' . e($item['q'] ?? '') . '</span>'
                       . '<span class="ds-faq-chevron">▾</span>'
                       . '</div>'
                       . '<div class="ds-faq-a">' . e($item['a'] ?? '') . '</div>'
                       . '</div>';
            }
            $html .= '</div>';
            return $html;
        }

        // ── Citation ───────────────────────────────────────────
        case 'quote': {
            $text   = $block['text']   ?? '';
            $author = trim($block['author'] ?? '');
            $html   = '<div class="ds-block ds-block-quote">'
                    . '<p class="ds-quote-text">' . e($text) . '</p>';
            if ($author !== '') {
                $html .= '<cite class="ds-quote-author">' . e($author) . '</cite>';
            }
            $html .= '</div>';
            return $html;
        }

        // ── Séparateur ─────────────────────────────────────────
        case 'divider': {
            $style = ($block['style'] ?? 'thin') === 'gradient' ? 'gradient' : 'thin';
            return '<div class="ds-block ds-block-divider">'
                 . '<hr class="ds-divider-' . $style . '">'
                 . '</div>';
        }

        default:
            return '<!-- bloc inconnu : ' . e($type) . ' -->';
    }
}

// ── En-tête ───────────────────────────────────────────────────
require_once 'includes/header.php';
require_once 'includes/nav.php';

// ── Hero (optionnel) ──────────────────────────────────────────
$hero_title    = $row['hero_title']    ?? '';
$hero_subtitle = $row['hero_subtitle'] ?? '';

if ($hero_title !== '') {
    ?>
<section class="ds-hero">
  <div class="ds-container ds-hero-inner">
    <h1 class="ds-hero-title"><?= e($hero_title) ?></h1>
    <?php if ($hero_subtitle !== ''): ?>
    <p class="ds-hero-sub"><?= e($hero_subtitle) ?></p>
    <?php endif; ?>
  </div>
</section>
    <?php
}
?>

<div class="ds-page">
  <section class="ds-section ds-section-beige">
    <div class="ds-container">

      <?php if ($hero_title === ''): ?>
      <h1 class="ds-h1"><?= e($row['title'] ?? '') ?></h1>
      <?php endif; ?>

      <?php if (empty($blocks)): ?>
      <p class="ds-body" style="text-align:center;padding:40px 0;color:var(--ds-text-muted)">Cette page est en cours de rédaction.</p>
      <?php else: ?>
        <?php foreach ($blocks as $block): ?>
          <?= render_block($block) ?>
        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php
require_once 'includes/footer.php';
