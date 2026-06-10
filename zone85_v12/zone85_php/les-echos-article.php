<?php
// ============================================================
// ZONE85 — Article individuel des Échos
// ============================================================
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// ── Paramètres d'entrée ───────────────────────────────────────
$slug = safe_input($_GET['slug'] ?? '', 200);
$id   = (int)($_GET['id'] ?? 0);

// ── Chargement de l'article ───────────────────────────────────
$article  = null;
$pdo_main = db();
if ($pdo_main) {
    try {
        if ($slug !== '') {
            $s = $pdo_main->prepare("SELECT * FROM articles WHERE slug=:s AND status='published' LIMIT 1");
            $s->execute([':s' => $slug]);
        } else {
            $s = $pdo_main->prepare("SELECT * FROM articles WHERE id=:id AND status='published' LIMIT 1");
            $s->execute([':id' => $id]);
        }
        $article = $s->fetch() ?: null;
    } catch (PDOException $e) {}
}

if (!$article) {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/les-echos.php');
    exit;
}

// ── Rubrique ──────────────────────────────────────────────────
$rubrique_labels = [
    'les-invisibles' => 'Les Invisibles',
    'deux-minutes'   => "T'as deux minutes\xc2\xa0?",
    'les-ovnis'      => 'Les OVNIS de la Zone85',
    'actualite'      => 'Actualité',
    'chemins'        => 'Sur les Chemins',
    'evenements'     => 'Événements',
    // anciens slugs — rétrocompatibilité
    'ovnis' => 'Les OVNIS de la Zone85', 'chez-nous' => 'Les Invisibles',
    'communaute' => 'Actualité', 'archives' => 'Actualité',
];
$rubrique_gradients = [
    'les-invisibles' => 'linear-gradient(135deg,#ea5649 0%,#12314e 100%)',
    'deux-minutes'   => 'linear-gradient(135deg,#c9962a 0%,#12314e 100%)',
    'les-ovnis'      => 'linear-gradient(135deg,#1a7adc 0%,#0c1e2e 100%)',
    'actualite'      => 'linear-gradient(135deg,#2a9d5c 0%,#163756 100%)',
    'chemins'        => 'linear-gradient(135deg,#b8831a 0%,#12314e 100%)',
    'evenements'     => 'linear-gradient(135deg,#9b59b6 0%,#12314e 100%)',
    'ovnis' => 'linear-gradient(135deg,#1a7adc 0%,#0c1e2e 100%)',
    'chez-nous' => 'linear-gradient(135deg,#ea5649 0%,#12314e 100%)',
    'communaute' => 'linear-gradient(135deg,#2a9d5c 0%,#163756 100%)',
    'archives' => 'linear-gradient(135deg,#6b7f96 0%,#333 100%)',
];

$rub       = $article['rubrique'] ?? 'actualite';
$rub_label = $rubrique_labels[$rub] ?? $rub;
$rub_grad  = $rubrique_gradients[$rub] ?? 'linear-gradient(135deg,#12314e,#ea5649)';

// ── Articles connexes ─────────────────────────────────────────
$related = [];
if ($pdo_main) {
    try {
        $sr = $pdo_main->prepare(
            "SELECT id, title, slug, author_name, published_at
             FROM articles WHERE status='published' AND rubrique=:r AND id<>:id
             ORDER BY published_at DESC LIMIT 3"
        );
        $sr->execute([':r' => $rub, ':id' => $article['id']]);
        $related = $sr->fetchAll();
    } catch (PDOException $e) {}
}

// ── Date ─────────────────────────────────────────────────────
$date_fmt = '';
if (!empty($article['published_at'])) {
    $ts = strtotime($article['published_at']);
    if ($ts) {
        $mois = ['janvier','février','mars','avril','mai','juin',
                 'juillet','août','septembre','octobre','novembre','décembre'];
        $date_fmt = date('j',$ts).' '.$mois[(int)date('n',$ts)-1].' '.date('Y',$ts);
    }
}

// ── Temps de lecture estimé ───────────────────────────────────
$read_time = max(1, (int)round(str_word_count(strip_tags($article['body'] ?? '')) / 200));

// ── Auth ──────────────────────────────────────────────────────
$is_logged_in = !empty($_SESSION['user']['id']);
$user_id      = (int)($_SESSION['user']['id'] ?? 0);
$base_url     = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

// ── Galerie ───────────────────────────────────────────────────
$gallery_imgs = json_decode($article['gallery'] ?? '[]', true);
if (!is_array($gallery_imgs)) $gallery_imgs = [];
$gallery_count = count($gallery_imgs);

// Résoudre les URL d'images
$gallery_urls = array_map(function($img) use ($base_url) {
    return (strpos($img,'http') === 0) ? $img : $base_url.'/'.$img;
}, $gallery_imgs);

$gallery_js = json_encode($gallery_urls, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT);

// ── Vidéo YouTube ─────────────────────────────────────────────
$yt_id = '';
if (!empty($article['video_url'])) {
    preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_\-]{11})/', $article['video_url'], $yt_m);
    $yt_id = $yt_m[1] ?? '';
}

// ── Auto-migration tables ─────────────────────────────────────
if ($pdo_main) {
    try {
        $pdo_main->exec("CREATE TABLE IF NOT EXISTS article_reads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_read (article_id, user_id),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo_main->exec("CREATE TABLE IF NOT EXISTS article_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
            KEY idx_article (article_id, status, created_at),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {}
}

// ── Track lecture ─────────────────────────────────────────────
if ($is_logged_in && $user_id > 0 && $pdo_main) {
    try {
        $pdo_main->prepare(
            "INSERT INTO article_reads (article_id, user_id) VALUES (:a,:u)
             ON DUPLICATE KEY UPDATE read_at=NOW()"
        )->execute([':a' => $article['id'], ':u' => $user_id]);
    } catch (PDOException $e) {}
}

// ── Commentaire POST ──────────────────────────────────────────
$comment_flash      = '';
$comment_flash_type = 'ok';

if (isset($_GET['commented'])) {
    $xp_get = (int)($_GET['xp'] ?? 0);
    $comment_flash = 'Commentaire publié !' . ($xp_get > 0 ? ' +' . $xp_get . ' XP 🎉' : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in && $user_id > 0 && $pdo_main) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $comment_flash = 'Jeton invalide. Rechargez la page.';
        $comment_flash_type = 'err';
    } else {
        $body_raw = trim($_POST['comment_body'] ?? '');
        if (mb_strlen($body_raw) < 5) {
            $comment_flash = 'Le commentaire doit faire au moins 5 caractères.';
            $comment_flash_type = 'err';
        } elseif (mb_strlen($body_raw) > 1000) {
            $comment_flash = 'Le commentaire ne peut pas dépasser 1000 caractères.';
            $comment_flash_type = 'err';
        } else {
            try {
                $cnt_s = $pdo_main->prepare(
                    "SELECT COUNT(*) FROM article_comments WHERE article_id=:a AND user_id=:u"
                );
                $cnt_s->execute([':a' => $article['id'], ':u' => $user_id]);
                $existing = (int)$cnt_s->fetchColumn();

                $pdo_main->prepare(
                    "INSERT INTO article_comments (article_id, user_id, body) VALUES (:a,:u,:b)"
                )->execute([':a'=>$article['id'],':u'=>$user_id,':b'=>$body_raw]);
                $comment_id = (int)$pdo_main->lastInsertId();

                $xp_earned = 0;
                if ($existing === 0) {
                    // 5 XP pour le premier commentaire sur cet article
                    $pdo_main->prepare("UPDATE users SET xp_total = xp_total + 5 WHERE id=:id")
                        ->execute([':id' => $user_id]);
                    // Recalculer le niveau et enregistrer dans xp_logs
                    $xp_row = $pdo_main->prepare("SELECT xp_total FROM users WHERE id=:id LIMIT 1");
                    $xp_row->execute([':id' => $user_id]);
                    $new_xp = (int)$xp_row->fetchColumn();
                    if (function_exists('get_user_level_from_xp')) {
                        $pdo_main->prepare("UPDATE users SET level = :lvl WHERE id=:id")
                            ->execute([':lvl' => get_user_level_from_xp($new_xp), ':id' => $user_id]);
                    }
                    $pdo_main->prepare(
                        "INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
                         VALUES (:uid, 'comment', :src, 5, 'Commentaire sur Les Échos')"
                    )->execute([':uid' => $user_id, ':src' => $comment_id]);
                    $xp_earned = 5;
                }

                header('Location: '.$base_url.'/les-echos-article.php?slug='
                    .urlencode($article['slug']).'&commented=1&xp='.$xp_earned.'#commentaires');
                exit;
            } catch (PDOException $e) {
                $comment_flash      = 'Erreur lors de l\'envoi.';
                $comment_flash_type = 'err';
            }
        }
    }
}

// ── Chargement commentaires ───────────────────────────────────
$comments = [];
if ($pdo_main) {
    try {
        $cs = $pdo_main->prepare(
            "SELECT ac.id, ac.body, ac.created_at, u.pseudo, u.id AS author_id
             FROM article_comments ac
             JOIN users u ON u.id = ac.user_id
             WHERE ac.article_id=:a AND ac.status='visible'
             ORDER BY ac.created_at ASC LIMIT 100"
        );
        $cs->execute([':a' => $article['id']]);
        $comments = $cs->fetchAll();
    } catch (PDOException $e) {}
}
$comment_count = count($comments);

// ── URL partage ───────────────────────────────────────────────
$share_url   = $base_url . '/les-echos-article.php?slug=' . urlencode($article['slug'] ?? '');
$share_title = htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8');

// ── Style hero (cover image ou dégradé rubrique) ─────────────
if (!empty($article['cover_image'])) {
    $cover_esc  = htmlspecialchars($article['cover_image'], ENT_QUOTES, 'UTF-8');
    $hero_style = 'background-image:linear-gradient(to bottom,rgba(12,30,46,.5) 0%,rgba(12,30,46,.82) 100%),url(' . $cover_esc . ');background-size:cover;background-position:center top;';
} else {
    $hero_style = 'background:' . $rub_grad . ';';
}

// ── Blocs éditoriaux ─────────────────────────────────────────
$content_blocks = [];
if (!empty($article['content_blocks'])) {
    $decoded = json_decode($article['content_blocks'], true);
    if (is_array($decoded)) $content_blocks = $decoded;
}

// ── Méta SEO ─────────────────────────────────────────────────
$page_title       = $article['title'] . ' — Les Échos Zone85';
$page_description = $article['excerpt'] ?? '';
$page_og_image    = !empty($article['cover_image']) ? $article['cover_image'] : 'assets/img/ZONE852025.png';
$page_robots      = 'index,follow';
$page_canonical   = 'https://www.zone85.fr/les-echos-article.php?slug=' . urlencode($article['slug'] ?? '');
$current_page     = 'les-echos';

// ── Styles ────────────────────────────────────────────────────
$page_styles = '<style>
.article-hero { padding: 120px 0 72px; background-color: #0c1e2e; }
.article-hero-inner { position: relative; z-index: 1; }
.article-rubrique-badge {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: .68rem; font-weight: 900; letter-spacing: .14em;
  text-transform: uppercase; color: #fff;
  border-radius: 20px; padding: 5px 14px; margin-bottom: 20px;
}
.article-hero h1 {
  font-size: clamp(1.8rem,4.5vw,3rem); font-weight: 900;
  color: #fff; letter-spacing: -1px; line-height: 1.15;
  margin-bottom: 18px; max-width: 800px;
}
.article-hero-meta {
  display: flex; align-items: center; gap: 20px;
  flex-wrap: wrap; font-size: .85rem; color: rgba(255,255,255,.65); font-weight: 600;
}
.article-hero-meta span { display: flex; align-items: center; gap: 6px; }

.article-breadcrumb { background:#fff; border-bottom:1px solid rgba(0,0,0,.06); padding:12px 0; }
.article-breadcrumb ol {
  list-style:none; margin:0; padding:0;
  display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:.78rem;
}
.article-breadcrumb li { display:flex; align-items:center; gap:6px; }
.article-breadcrumb li:not(:last-child)::after { content:"›"; color:#aaa; }
.article-breadcrumb a { color:#6b7f96; text-decoration:none; font-weight:600; }
.article-breadcrumb a:hover { color:#ea5649; }
.article-breadcrumb .current { color:#1a1a1a; font-weight:700; }

.article-layout { background:#f7f4ef; padding:52px 0 72px; }
.article-layout-inner { display:grid; grid-template-columns:1fr 320px; gap:36px; align-items:start; }

.article-body-wrap {
  background:#fff; border-radius:16px;
  box-shadow:0 2px 12px rgba(0,0,0,.06);
  border:1px solid rgba(0,0,0,.06); overflow:hidden;
}
.article-cover { width:100%; max-height:420px; object-fit:cover; display:block; }
.article-body-inner { padding:36px 40px 40px; }
.article-body-content { font-size:1.02rem; line-height:1.85; color:#2a2a2a; max-width:680px; }
.article-body-content p  { margin:0 0 1.4em; }
.article-body-content h2 { font-size:1.35rem; font-weight:800; color:#0c1e2e; margin:1.8em 0 .7em; }
.article-body-content h3 { font-size:1.1rem; font-weight:700; color:#12314e; margin:1.5em 0 .6em; }
.article-body-content a  { color:#ea5649; font-weight:600; }
.article-body-content ul,
.article-body-content ol { margin:0 0 1.4em 1.4em; padding:0; }
.article-body-content li { margin-bottom:.4em; }
.article-body-content strong { font-weight:800; color:#1a1a1a; }
.article-body-content em { font-style:italic; }
.article-body-content blockquote {
  margin:1.6em 0; padding:16px 20px;
  border-left:3px solid #ea5649; background:rgba(234,86,73,.04);
  border-radius:0 8px 8px 0; font-style:italic; color:#444;
}

/* BLOCS ÉDITORIAUX */
.article-blocks { max-width:680px; }
.article-block { margin-bottom:2em; }
.article-block-text { font-size:1.02rem; line-height:1.85; color:#2a2a2a; }
.article-block-text p  { margin:0 0 1.4em; }
.article-block-text h2 { font-size:1.35rem; font-weight:800; color:#0c1e2e; margin:1.8em 0 .7em; }
.article-block-text h3 { font-size:1.1rem; font-weight:700; color:#12314e; margin:1.5em 0 .6em; }
.article-block-text a  { color:#ea5649; font-weight:600; }
.article-block-text ul, .article-block-text ol { margin:0 0 1.4em 1.4em; }
.article-block-text strong { font-weight:800; color:#1a1a1a; }
.article-block-heading { font-size:1.35rem; font-weight:800; color:#0c1e2e; letter-spacing:-.3px; line-height:1.25; }
.article-block-image figure { margin:0; }
.article-block-image img { width:100%; border-radius:10px; display:block; }
.article-block-image figcaption { font-size:.8rem; color:#6b7f96; margin-top:8px; font-style:italic; text-align:center; }
.article-block-gallery-grid { display:flex; flex-wrap:wrap; gap:8px; }
.article-block-gallery-grid img { height:180px; flex:1 1 200px; object-fit:cover; border-radius:8px; cursor:pointer; transition:opacity .2s; }
.article-block-gallery-grid img:hover { opacity:.88; }
.article-block-gallery figcaption { font-size:.8rem; color:#6b7f96; margin-top:8px; font-style:italic; text-align:center; }
.article-block-quote blockquote {
  margin:0; padding:20px 24px;
  border-left:4px solid #ea5649; background:rgba(234,86,73,.05);
  border-radius:0 10px 10px 0; font-style:italic; font-size:1.08rem; color:#333; line-height:1.7;
}
.article-block-quote cite { display:block; margin-top:10px; font-size:.82rem; font-weight:700; color:#ea5649; font-style:normal; }
.article-block-note {
  background:#f0f7ff; border:1.5px solid #c5d8f0; border-radius:10px;
  padding:18px 22px; font-size:.92rem; line-height:1.7; color:#1a3a5c;
}
.article-block-note-title { font-weight:800; font-size:.95rem; color:#0c1e2e; margin-bottom:6px; }

/* VIDÉO */
.article-video-wrap {
  position:relative; aspect-ratio:16/9;
  margin:28px 0; border-radius:12px; overflow:hidden;
  box-shadow:0 4px 20px rgba(0,0,0,.15);
}
.article-video-wrap iframe { width:100%; height:100%; border:none; }

/* SIDEBAR */
.article-sidebar { position: sticky; top: 24px; }
.article-sidebar-card {
  background:#fff; border-radius:14px;
  box-shadow:0 2px 10px rgba(0,0,0,.06);
  border:1px solid rgba(0,0,0,.06);
  overflow:hidden; margin-bottom:16px;
}
.article-sidebar-title {
  font-size:.65rem; font-weight:900; letter-spacing:.14em;
  text-transform:uppercase; color:#6b7f96; padding:14px 18px 0;
}
.article-back-btn {
  display:flex; align-items:center; gap:8px; padding:14px 18px;
  font-size:.85rem; font-weight:700; color:#0c1e2e;
  text-decoration:none; transition:color .15s;
}
.article-back-btn:hover { color:#ea5649; }

/* SHARE */
.article-share-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:14px; }
.share-btn {
  display:flex; align-items:center; justify-content:center; gap:6px;
  padding:9px 10px; border-radius:8px; font-size:.78rem; font-weight:700;
  text-decoration:none; cursor:pointer; border:none; transition:opacity .2s;
}
.share-btn:hover { opacity:.85; }
.share-fb  { background:#1877f2; color:#fff; }
.share-x   { background:#000; color:#fff; }
.share-wa  { background:#25d366; color:#fff; }
.share-copy { background:#f0ece7; color:#0c1e2e; grid-column:1/-1; }
.share-copy.copied { background:#2a9d5c; color:#fff; }

/* SIDEBAR GALERIE */
.sb-gallery-grid {
  display:grid; grid-template-columns:repeat(3,1fr); gap:4px;
  padding:10px 14px 14px;
}
.sb-gallery-thumb {
  aspect-ratio:1; border:none; background:none; padding:0; cursor:pointer;
  border-radius:6px; overflow:hidden; transition:opacity .2s;
}
.sb-gallery-thumb:hover { opacity:.82; }
.sb-gallery-thumb img { width:100%; height:100%; object-fit:cover; display:block; }

/* RELATED */
.article-related-item {
  display:block; padding:12px 18px; text-decoration:none; color:inherit;
  border-bottom:1px solid rgba(0,0,0,.06); transition:background .15s;
}
.article-related-item:last-child { border-bottom:none; }
.article-related-item:hover { background:#faf7f4; }
.article-related-item-title { font-size:.85rem; font-weight:700; color:#0c1e2e; line-height:1.35; margin-bottom:3px; }
.article-related-item-meta  { font-size:.7rem; color:#6b7f96; font-weight:600; }

/* COMMENTAIRES */
.article-comments { background:#f7f4ef; padding:52px 0 72px; }
.article-comments .container { max-width:760px; }
.ac-title { font-size:1.35rem; font-weight:900; color:#0c1e2e; letter-spacing:-.3px; margin-bottom:32px; }
.ac-flash {
  padding:12px 18px; border-radius:8px; font-size:.88rem; font-weight:600;
  margin-bottom:20px;
}
.ac-flash-ok  { background:#d4edda; color:#1a5c2a; }
.ac-flash-err { background:#f8d7da; color:#721c24; }

.ac-form { background:#fff; border-radius:14px; padding:24px; margin-bottom:32px;
           border:1px solid rgba(0,0,0,.06); box-shadow:0 2px 8px rgba(0,0,0,.05); }
.ac-form-label { font-size:.78rem; font-weight:700; color:#6b7f96; text-transform:uppercase;
                 letter-spacing:.08em; margin-bottom:10px; display:block; }
.ac-textarea {
  width:100%; min-height:110px; resize:vertical;
  border:1.5px solid #dde3ec; border-radius:8px; padding:12px 14px;
  font-size:.92rem; font-family:inherit; line-height:1.6;
  transition:border-color .2s; box-sizing:border-box;
}
.ac-textarea:focus { outline:none; border-color:#ea5649; }
.ac-form-footer { display:flex; align-items:center; justify-content:space-between; margin-top:12px; flex-wrap:wrap; gap:10px; }
.ac-counter { font-size:.74rem; color:#aaa; }
.ac-submit {
  background:#ea5649; color:#fff; border:none; border-radius:8px;
  padding:10px 22px; font-size:.88rem; font-weight:800; cursor:pointer;
  display:inline-flex; align-items:center; gap:6px; transition:opacity .2s;
}
.ac-submit:hover { opacity:.88; }
.ac-xp-badge {
  display:inline-flex; align-items:center; gap:4px;
  background:#fef3c7; color:#92400e;
  font-size:.72rem; font-weight:800; padding:4px 12px; border-radius:20px;
  border:1px solid #f6d860;
}
.ac-form-intro {
  font-size:.85rem; color:#6b7f96; line-height:1.6;
  margin-bottom:16px; padding:10px 14px;
  background:#f7f9fc; border-left:3px solid #C9962A; border-radius:0 8px 8px 0;
}
.ac-form-intro strong { color:#0c1e2e; }

.ac-login-prompt {
  background:#fff; border-radius:12px; padding:28px 24px;
  text-align:center; border:1.5px dashed #d6dde6; margin-bottom:32px;
}
.ac-login-prompt .ac-lp-xp {
  display:inline-flex; align-items:center; gap:6px;
  background:#fef3c7; color:#92400e; font-size:.8rem; font-weight:800;
  padding:5px 14px; border-radius:20px; border:1px solid #f6d860;
  margin-bottom:12px;
}
.ac-login-prompt p { font-size:.9rem; color:#6b7f96; margin-bottom:16px; }
.ac-login-link {
  display:inline-flex; align-items:center; gap:6px;
  background:#0c1e2e; color:#fff; padding:10px 20px;
  border-radius:8px; font-size:.85rem; font-weight:700;
  text-decoration:none; transition:opacity .2s;
}
.ac-login-link:hover { opacity:.85; }

.ac-list { display:flex; flex-direction:column; gap:16px; }
.ac-item {
  background:#fff; border-radius:12px; padding:20px 22px;
  border:1px solid rgba(0,0,0,.06); box-shadow:0 1px 4px rgba(0,0,0,.04);
}
.ac-item-meta { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.ac-pseudo {
  font-size:.85rem; font-weight:800; color:#0c1e2e;
  background:#f0ece7; padding:3px 10px; border-radius:20px;
}
.ac-date { font-size:.75rem; color:#aaa; font-weight:600; }
.ac-body { font-size:.93rem; line-height:1.75; color:#333; white-space:pre-wrap; word-break:break-word; }
.ac-empty { text-align:center; padding:32px; color:#aaa; font-size:.9rem; }

/* LIGHTBOX */
#article-lb {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.92);
  z-index:9999; align-items:center; justify-content:center;
  flex-direction:column;
}
#article-lb.open { display:flex; }
#lb-img { max-width:90vw; max-height:82vh; object-fit:contain; border-radius:8px; display:block; }
#lb-counter {
  color:rgba(255,255,255,.6); font-size:.82rem; font-weight:700;
  margin-top:12px; letter-spacing:.06em;
}
.lb-btn {
  position:fixed; top:50%; transform:translateY(-50%);
  background:rgba(255,255,255,.12); border:none; color:#fff;
  font-size:2rem; width:50px; height:50px; border-radius:50%;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  transition:background .2s; backdrop-filter:blur(4px);
}
.lb-btn:hover { background:rgba(255,255,255,.25); }
#lb-prev { left:16px; }
#lb-next { right:16px; }
#lb-close {
  position:fixed; top:16px; right:20px;
  background:rgba(255,255,255,.12); border:none; color:#fff;
  font-size:1.3rem; width:42px; height:42px; border-radius:50%;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  transition:background .2s; backdrop-filter:blur(4px);
}
#lb-close:hover { background:rgba(255,255,255,.25); }

/* RESPONSIVE */
@media (max-width:960px) {
  .article-layout-inner { grid-template-columns:1fr; }
  .article-body-inner { padding:24px; }
  .article-sidebar { order:-1; }
  .sb-gallery-grid { grid-template-columns:repeat(4,1fr); }
}
@media (max-width:600px) {
  .article-hero { padding:80px 0 44px; }
  .article-hero h1 { font-size:1.7rem; }
  .article-layout { padding:32px 0 40px; }
  .article-share-grid { grid-template-columns:1fr 1fr; }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ── HERO ─────────────────────────────────────────────────── -->
<section class="article-hero" style="<?= $hero_style ?>">
  <div class="container article-hero-inner">
    <span class="article-rubrique-badge" style="background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.2)">
      <?= e($rub_label) ?>
    </span>
    <h1><?= e($article['title']) ?></h1>
    <div class="article-hero-meta">
      <?php if (!empty($article['author_name'])): ?>
        <span>✏️ <?= e($article['author_name']) ?></span>
      <?php endif; ?>
      <?php if ($date_fmt): ?>
        <span>📅 <?= $date_fmt ?></span>
      <?php endif; ?>
      <span>⏱ <?= $read_time ?> min de lecture</span>
      <?php if ($comment_count > 0): ?>
        <span>💬 <?= $comment_count ?> commentaire<?= $comment_count > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ── BREADCRUMB ────────────────────────────────────────────── -->
<nav class="article-breadcrumb" aria-label="Fil d'Ariane">
  <div class="container">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="index.php" itemprop="item"><span itemprop="name">Accueil</span></a>
        <meta itemprop="position" content="1">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="les-echos.php" itemprop="item"><span itemprop="name">Les Échos</span></a>
        <meta itemprop="position" content="2">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="les-echos.php?rubrique=<?= urlencode($rub) ?>" itemprop="item">
          <span itemprop="name"><?= e($rub_label) ?></span>
        </a>
        <meta itemprop="position" content="3">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <span class="current" itemprop="name">
          <?= e(mb_strimwidth($article['title'], 0, 60, '…')) ?>
        </span>
        <meta itemprop="position" content="4">
      </li>
    </ol>
  </div>
</nav>

<!-- ── ARTICLE + SIDEBAR ─────────────────────────────────────── -->
<section class="article-layout">
  <div class="container">
    <div class="article-layout-inner">

      <!-- Corps principal -->
      <div class="article-body-wrap">
        <div class="article-body-inner">
          <!-- Contenu — l'accroche n'est pas répétée ici -->
          <?php if (!empty($content_blocks)): ?>
          <div class="article-blocks">
            <?php foreach ($content_blocks as $blk):
              $bt = $blk['type'] ?? '';
            ?>
            <?php if ($bt === 'text'): ?>
              <div class="article-block article-block-text"><?= $blk['html'] ?? '' ?></div>

            <?php elseif ($bt === 'heading'): ?>
              <h2 class="article-block article-block-heading"><?= e($blk['text'] ?? '') ?></h2>

            <?php elseif ($bt === 'image'): ?>
              <?php
                $bsrc = $blk['src'] ?? '';
                if ($bsrc !== '') {
                    $burl = (strpos($bsrc,'http') === 0) ? $bsrc : $base_url.'/'.$bsrc;
                    $bpos = htmlspecialchars($blk['position'] ?? 'center center', ENT_QUOTES, 'UTF-8');
                    $bcap = $blk['caption'] ?? '';
                ?>
              <div class="article-block article-block-image">
                <figure>
                  <img src="<?= htmlspecialchars($burl, ENT_QUOTES, 'UTF-8') ?>"
                       alt="<?= htmlspecialchars($bcap, ENT_QUOTES, 'UTF-8') ?>"
                       style="object-position:<?= $bpos ?>"
                       loading="lazy">
                  <?php if ($bcap !== ''): ?>
                    <figcaption><?= e($bcap) ?></figcaption>
                  <?php endif; ?>
                </figure>
              </div>
              <?php } ?>

            <?php elseif ($bt === 'gallery'): ?>
              <?php $gimgs = $blk['images'] ?? []; if (!empty($gimgs)): ?>
              <div class="article-block article-block-gallery">
                <figure>
                  <div class="article-block-gallery-grid">
                    <?php foreach ($gimgs as $gsrc):
                      $gurl = (strpos($gsrc,'http') === 0) ? $gsrc : $base_url.'/'.$gsrc;
                    ?>
                      <img src="<?= htmlspecialchars($gurl, ENT_QUOTES, 'UTF-8') ?>"
                           alt="" loading="lazy">
                    <?php endforeach; ?>
                  </div>
                  <?php if (!empty($blk['caption'])): ?>
                    <figcaption><?= e($blk['caption']) ?></figcaption>
                  <?php endif; ?>
                </figure>
              </div>
              <?php endif; ?>

            <?php elseif ($bt === 'quote'): ?>
              <div class="article-block article-block-quote">
                <blockquote>
                  <?= e($blk['text'] ?? '') ?>
                  <?php if (!empty($blk['author'])): ?>
                    <cite>— <?= e($blk['author']) ?></cite>
                  <?php endif; ?>
                </blockquote>
              </div>

            <?php elseif ($bt === 'note'): ?>
              <div class="article-block article-block-note">
                <?php if (!empty($blk['title'])): ?>
                  <div class="article-block-note-title"><?= e($blk['title']) ?></div>
                <?php endif; ?>
                <?= e($blk['text'] ?? '') ?>
              </div>

            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="article-body-content">
            <?php
              $body = $article['body'] ?? '';
              if ($body !== '' && strip_tags($body) === $body) {
                  echo nl2br(e($body));
              } else {
                  echo $body;
              }
            ?>
          </div>
          <?php endif; ?>

          <?php if ($yt_id): ?>
          <div class="article-video-wrap">
            <iframe src="https://www.youtube.com/embed/<?= e($yt_id) ?>"
                    allowfullscreen loading="lazy"
                    title="Vidéo <?= e($article['title']) ?>"></iframe>
          </div>
          <?php endif; ?>

          <!-- Pied d'article -->
          <div style="margin-top:40px;padding-top:24px;border-top:1px solid rgba(0,0,0,.06);
                      display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div style="font-size:.82rem;color:#6b7f96">
              <span style="font-weight:700;color:#0c1e2e"><?= e($article['author_name'] ?? 'Équipe Zone85') ?></span>
              <?php if ($date_fmt): ?> &mdash; <?= $date_fmt ?><?php endif; ?>
            </div>
            <a href="#commentaires" style="font-size:.82rem;font-weight:700;color:#ea5649;text-decoration:none">
              💬 <?php if ($comment_count > 0): ?>
                <?= $comment_count ?> commentaire<?= $comment_count > 1 ? 's' : '' ?> →
              <?php elseif ($is_logged_in): ?>
                Sois le premier à réagir →
              <?php else: ?>
                Laisser un commentaire →
              <?php endif; ?>
            </a>
          </div>
        </div>
      </div>

      <!-- SIDEBAR -->
      <aside class="article-sidebar">

        <!-- Retour aux Échos -->
        <div class="article-sidebar-card">
          <a href="les-echos.php" class="article-back-btn">← Retour aux Échos</a>
        </div>

        <!-- Partager -->
        <div class="article-sidebar-card">
          <p class="article-sidebar-title">Partager</p>
          <div class="article-share-grid">
            <a class="share-btn share-fb"
               href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($share_url) ?>"
               target="_blank" rel="noopener" aria-label="Partager sur Facebook">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
              Facebook
            </a>
            <a class="share-btn share-x"
               href="https://twitter.com/intent/tweet?url=<?= urlencode($share_url) ?>&text=<?= urlencode($article['title']) ?>"
               target="_blank" rel="noopener" aria-label="Partager sur X">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              X (Twitter)
            </a>
            <a class="share-btn share-wa"
               href="https://wa.me/?text=<?= urlencode($article['title'].' — '.$share_url) ?>"
               target="_blank" rel="noopener" aria-label="Partager sur WhatsApp">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.956 2C6.445 2 2 6.445 2 11.956c0 1.916.527 3.705 1.443 5.234L2 22l4.961-1.418c1.476.852 3.181 1.337 5.016 1.337 5.512 0 9.956-4.445 9.956-9.956C21.933 6.445 17.468 2 11.956 2z"/></svg>
              WhatsApp
            </a>
            <button id="share-copy-btn" class="share-btn share-copy"
                    onclick="copyArticleLink()" data-url="<?= e($share_url) ?>">
              📋 Copier le lien
            </button>
          </div>
        </div>

        <!-- Galerie -->
        <?php if ($gallery_count > 0): ?>
        <div class="article-sidebar-card">
          <p class="article-sidebar-title">Galerie — <?= $gallery_count ?> photo<?= $gallery_count > 1 ? 's' : '' ?></p>
          <div class="sb-gallery-grid">
            <?php foreach ($gallery_urls as $gi => $gurl): ?>
            <button type="button" class="sb-gallery-thumb"
                    onclick="openLightbox(<?= $gi ?>)"
                    aria-label="Photo <?= $gi+1 ?> sur <?= $gallery_count ?>">
              <img src="<?= e($gurl) ?>" loading="lazy" alt="Photo <?= $gi+1 ?>">
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Dans la même rubrique -->
        <?php if (!empty($related)): ?>
        <div class="article-sidebar-card">
          <p class="article-sidebar-title">Dans la même rubrique</p>
          <?php foreach ($related as $rel):
            $rel_url = !empty($rel['slug'])
                ? 'les-echos-article.php?slug='.urlencode($rel['slug'])
                : 'les-echos-article.php?id='.(int)$rel['id'];
            $rel_date = '';
            if (!empty($rel['published_at'])) {
                $ts2 = strtotime($rel['published_at']);
                if ($ts2) {
                    $mois2 = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sep.','oct.','nov.','déc.'];
                    $rel_date = date('j',$ts2).' '.$mois2[(int)date('n',$ts2)-1].' '.date('Y',$ts2);
                }
            }
          ?>
          <a href="<?= e($rel_url) ?>" class="article-related-item">
            <div class="article-related-item-title"><?= e($rel['title']) ?></div>
            <div class="article-related-item-meta">
              <?= e($rel['author_name'] ?? 'Équipe Zone85') ?>
              <?php if ($rel_date): ?>&nbsp;&middot; <?= $rel_date ?><?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </aside>
    </div>
  </div>
</section>

<!-- ── COMMENTAIRES ──────────────────────────────────────────── -->
<section class="article-comments" id="commentaires">
  <div class="container">

    <h2 class="ac-title">
      💬 Commentaires
      <?php if ($comment_count > 0): ?>
        <span style="font-size:.82rem;font-weight:600;color:#6b7f96;margin-left:8px">(<?= $comment_count ?>)</span>
      <?php endif; ?>
    </h2>

    <?php if ($comment_flash): ?>
    <div class="ac-flash ac-flash-<?= e($comment_flash_type) ?>">
      <?= e($comment_flash) ?>
    </div>
    <?php endif; ?>

    <?php if ($is_logged_in): ?>
    <!-- Formulaire commentaire -->
    <div class="ac-form">
      <p class="ac-form-intro">
        💬 Réagis à cet article — ton premier commentaire sur cet écho
        te rapporte <strong>+5 XP</strong> !
        Les points d'expérience font progresser ton rang dans la Zone85.
      </p>
      <label class="ac-form-label" for="comment_body">Ton commentaire</label>
      <form method="post" action="les-echos-article.php?slug=<?= urlencode($article['slug'] ?? '') ?>#commentaires">
        <?= csrf_field() ?>
        <textarea id="comment_body" name="comment_body" class="ac-textarea"
                  required minlength="5" maxlength="1000"
                  placeholder="Partage ton avis, une anecdote, une réaction…"></textarea>
        <div class="ac-form-footer">
          <span class="ac-counter" id="comment_counter">0 / 1000</span>
          <div style="display:flex;align-items:center;gap:12px">
            <span class="ac-xp-badge">&#x2B50; +5 XP premier commentaire</span>
            <button type="submit" class="ac-submit">Publier →</button>
          </div>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="ac-login-prompt">
      <div class="ac-lp-xp">&#x2B50; +5 XP pour commenter</div>
      <p>Connecte-toi pour laisser un commentaire et gagner des<br>
         <strong>points d'expérience</strong> qui font progresser ton rang dans la Zone85 !</p>
      <a href="login.php?redirect=<?= urlencode('les-echos-article.php?slug='.($article['slug']??'')) ?>#commentaires"
         class="ac-login-link">
        🔑 Se connecter
      </a>
    </div>
    <?php endif; ?>

    <!-- Liste des commentaires -->
    <?php if (empty($comments)): ?>
    <div class="ac-empty">
      Aucun commentaire pour l'instant.<br>Sois le premier à réagir !
    </div>
    <?php else: ?>
    <div class="ac-list">
      <?php foreach ($comments as $c):
        $c_ts = strtotime($c['created_at'] ?? '');
        $c_date = $c_ts ? date('d/m/Y à H:i', $c_ts) : '';
      ?>
      <div class="ac-item">
        <div class="ac-item-meta">
          <span class="ac-pseudo"><?= e($c['pseudo']) ?></span>
          <?php if ($c_date): ?>
          <span class="ac-date"><?= $c_date ?></span>
          <?php endif; ?>
        </div>
        <p class="ac-body"><?= nl2br(e($c['body'])) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- ── LIGHTBOX ──────────────────────────────────────────────── -->
<div id="article-lb" role="dialog" aria-label="Galerie photos" aria-modal="true">
  <button id="lb-close" aria-label="Fermer">✕</button>
  <button class="lb-btn" id="lb-prev" aria-label="Photo précédente">‹</button>
  <img id="lb-img" src="" alt="">
  <div id="lb-counter"></div>
  <button class="lb-btn" id="lb-next" aria-label="Photo suivante">›</button>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// ── Compteur textarea commentaire ─────────────────────────────
(function(){
  var ta  = document.getElementById('comment_body');
  var cnt = document.getElementById('comment_counter');
  if (!ta || !cnt) return;
  function update() { cnt.textContent = ta.value.length + ' / 1000'; }
  ta.addEventListener('input', update);
})();

// ── Copier le lien ────────────────────────────────────────────
function copyArticleLink() {
  var btn = document.getElementById('share-copy-btn');
  var url = btn ? btn.dataset.url : location.href;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(function() {
      btn.textContent = '✓ Lien copié !';
      btn.classList.add('copied');
      setTimeout(function(){ btn.textContent = '📋 Copier le lien'; btn.classList.remove('copied'); }, 2500);
    });
  } else {
    var el = document.createElement('input');
    el.value = url; document.body.appendChild(el);
    el.select(); document.execCommand('copy');
    document.body.removeChild(el);
    btn.textContent = '✓ Copié !'; btn.classList.add('copied');
    setTimeout(function(){ btn.textContent = '📋 Copier le lien'; btn.classList.remove('copied'); }, 2500);
  }
}

// ── Lightbox ──────────────────────────────────────────────────
(function(){
  var IMGS   = <?= $gallery_js ?>;
  var lb     = document.getElementById('article-lb');
  var lbImg  = document.getElementById('lb-img');
  var lbCnt  = document.getElementById('lb-counter');
  var lbPrev = document.getElementById('lb-prev');
  var lbNext = document.getElementById('lb-next');
  var lbClose= document.getElementById('lb-close');
  var cur    = 0;

  if (!lb || IMGS.length === 0) return;

  function show(i) {
    cur = ((i % IMGS.length) + IMGS.length) % IMGS.length;
    lbImg.src = IMGS[cur];
    lbCnt.textContent = (cur + 1) + ' / ' + IMGS.length;
    lbPrev.style.display = IMGS.length > 1 ? 'flex' : 'none';
    lbNext.style.display = IMGS.length > 1 ? 'flex' : 'none';
  }

  window.openLightbox = function(i) {
    show(i);
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    lbImg.focus();
  };

  function close() {
    lb.classList.remove('open');
    document.body.style.overflow = '';
  }

  lbClose.addEventListener('click', close);
  lbPrev.addEventListener('click', function(){ show(cur - 1); });
  lbNext.addEventListener('click', function(){ show(cur + 1); });

  lb.addEventListener('click', function(e){ if (e.target === lb) close(); });

  document.addEventListener('keydown', function(e) {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft')  show(cur - 1);
    if (e.key === 'ArrowRight') show(cur + 1);
  });
})();
</script>
