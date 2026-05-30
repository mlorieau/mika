<?php
// ============================================================
// ZONE85 — Admin : Les Échos — Créer / Éditer un article
// ============================================================
$admin_current    = 'echos';
$admin_page_title = 'Les Échos — Éditeur';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo   = db();
$flash = '';
$flash_type = 'ok';

// ── Chargement article existant ────────────────────────────────
$id      = (int)($_GET['id'] ?? 0);
$article = null;
if ($id > 0 && $pdo) {
    try {
        $s = $pdo->prepare('SELECT * FROM articles WHERE id=:id LIMIT 1');
        $s->execute([':id' => $id]);
        $article = $s->fetch();
    } catch (PDOException $e) { /* silencieux */ }
}

$admin_page_title = $article ? 'Éditer — ' . $article['title'] : 'Nouvel article';
$is_edit          = $article !== null;

// Flash "sauvegardé"
if (!empty($_GET['saved'])) {
    $flash = 'Article enregistré avec succès.';
    $flash_type = 'ok';
}

// ── Saisons disponibles ───────────────────────────────────────
$seasons_list = [];
if ($pdo) {
    try {
        $ss = $pdo->query("SELECT id, title, status FROM seasons ORDER BY id DESC");
        $seasons_list = $ss->fetchAll();
    } catch (PDOException $e) {}
}

// ── Rubriques ──────────────────────────────────────────────────
$rubrique_options = [
    'ovnis'        => 'OVNIS Zone85',
    'deux-minutes' => "T'as deux minutes\xc2\xa0?",
    'chez-nous'    => 'Chez nous on ne dit pas\xe2\x80\xa6',
    'chemins'      => 'Sur les chemins',
    'communaute'   => 'Communaut\xc3\xa9',
    'archives'     => 'Archives',
];

// ── Traitement POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Formulaire rejeté.';
        $flash_type = 'err';
    } else {
        $f_title       = safe_input($_POST['title']       ?? '', 255);
        $f_slug        = safe_input($_POST['slug']        ?? '', 255);
        $f_rubrique    = safe_input($_POST['rubrique']    ?? 'ovnis', 50);
        $f_author      = safe_input($_POST['author_name'] ?? 'Équipe Zone85', 100);
        $f_excerpt     = safe_input($_POST['excerpt']     ?? '', 500);
        $f_body        = $_POST['body'] ?? '';
        $f_cover       = safe_input($_POST['cover_image'] ?? '', 255);
        $f_status      = in_array($_POST['status'] ?? '', ['draft', 'published'], true)
                            ? $_POST['status'] : 'draft';
        $f_pub_at      = $_POST['published_at'] ?? '';
        $f_season_id   = !empty($_POST['season_id']) ? (int)$_POST['season_id'] : null;

        // Nettoyer le slug
        $f_slug = strtolower($f_slug);
        $f_slug = preg_replace('/[^a-z0-9\-]/', '', $f_slug);
        $f_slug = trim($f_slug, '-');
        if (empty($f_slug)) {
            $f_slug = 'article-' . time();
        }

        if (!in_array($f_rubrique, array_keys($rubrique_options), true)) {
            $f_rubrique = 'ovnis';
        }

        // published_at
        $published_at = null;
        if ($f_status === 'published') {
            if ($f_pub_at) {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $f_pub_at);
                $published_at = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            } else {
                $published_at = date('Y-m-d H:i:s');
            }
        }

        if (empty($f_title)) {
            $flash = 'Le titre est obligatoire.';
            $flash_type = 'err';
        } else {
            try {
                if ($is_edit) {
                    $s = $pdo->prepare('
                        UPDATE articles
                        SET title=:title, slug=:slug, rubrique=:rub, author_name=:author,
                            excerpt=:excerpt, body=:body, cover_image=:cover,
                            status=:status, published_at=:pub, season_id=:season
                        WHERE id=:id
                    ');
                    $s->execute([
                        ':title'   => $f_title,
                        ':slug'    => $f_slug,
                        ':rub'     => $f_rubrique,
                        ':author'  => $f_author,
                        ':excerpt' => $f_excerpt ?: null,
                        ':body'    => $f_body ?: null,
                        ':cover'   => $f_cover ?: null,
                        ':status'  => $f_status,
                        ':pub'     => $published_at,
                        ':season'  => $f_season_id,
                        ':id'      => $article['id'],
                    ]);
                    $saved_id = $article['id'];
                } else {
                    $s = $pdo->prepare('
                        INSERT INTO articles
                            (title, slug, rubrique, season_id, author_name, excerpt, body, cover_image, status, published_at)
                        VALUES
                            (:title, :slug, :rub, :season, :author, :excerpt, :body, :cover, :status, :pub)
                    ');
                    $s->execute([
                        ':title'   => $f_title,
                        ':slug'    => $f_slug,
                        ':rub'     => $f_rubrique,
                        ':season'  => $f_season_id,
                        ':author'  => $f_author,
                        ':excerpt' => $f_excerpt ?: null,
                        ':body'    => $f_body ?: null,
                        ':cover'   => $f_cover ?: null,
                        ':status'  => $f_status,
                        ':pub'     => $published_at,
                    ]);
                    $saved_id = (int)$pdo->lastInsertId();
                }
                $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                header('Location: ' . $base . '/admin/echo-edit.php?id=' . $saved_id . '&saved=1');
                exit;
            } catch (PDOException $e) {
                $flash = 'Erreur lors de la sauvegarde : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $flash_type = 'err';
            }
        }
    }
}

// Valeurs du formulaire (POST en cas d'erreur, sinon article chargé)
$v_title     = $_POST['title']       ?? ($article['title']       ?? '');
$v_slug      = $_POST['slug']        ?? ($article['slug']        ?? '');
$v_rubrique  = $_POST['rubrique']    ?? ($article['rubrique']    ?? 'ovnis');
$v_season_id = isset($_POST['season_id']) ? (int)$_POST['season_id']
             : (int)($article['season_id'] ?? 0);
$v_author    = $_POST['author_name'] ?? ($article['author_name'] ?? 'Équipe Zone85');
$v_excerpt   = $_POST['excerpt']     ?? ($article['excerpt']     ?? '');
$v_body      = $_POST['body']        ?? ($article['body']        ?? '');
$v_cover     = $_POST['cover_image'] ?? ($article['cover_image'] ?? '');
$v_status    = $_POST['status']      ?? ($article['status']      ?? 'draft');
$v_pub_at   = '';
if (!empty($article['published_at'])) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $article['published_at']);
    if ($dt) $v_pub_at = $dt->format('Y-m-d\TH:i');
}
if (!empty($_POST['published_at'])) $v_pub_at = $_POST['published_at'];

$admin_scripts = <<<'JS'
<script>
// ── Slugify ────────────────────────────────────────────────────
function slugify(text) {
    var map = {
        'à':'a','â':'a','ä':'a','á':'a','ã':'a',
        'è':'e','é':'e','ê':'e','ë':'e',
        'î':'i','ï':'i','í':'i','ì':'i',
        'ô':'o','ö':'o','ó':'o','ò':'o',
        'ù':'u','û':'u','ü':'u','ú':'u',
        'ç':'c','ñ':'n','ý':'y','ÿ':'y',
        'œ':'oe','æ':'ae'
    };
    return text.toLowerCase()
        .replace(/[àâäáãèéêëîïíìôöóòùûüúçñýÿœæ]/g, function(c){ return map[c]||c; })
        .replace(/[^a-z0-9\s\-]/g, '')
        .replace(/[\s]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

var titleEl   = document.getElementById('f_title');
var slugEl    = document.getElementById('f_slug');
var excerptEl = document.getElementById('f_excerpt');
var cntEl     = document.getElementById('excerpt_count');
var slugLocked = slugEl && slugEl.value.length > 0;

if (titleEl && slugEl) {
    titleEl.addEventListener('input', function() {
        if (!slugLocked) {
            slugEl.value = slugify(this.value);
        }
    });
    slugEl.addEventListener('input', function() {
        slugLocked = this.value.length > 0;
    });
    // Nettoyer le slug à la perte de focus
    slugEl.addEventListener('blur', function() {
        this.value = slugify(this.value);
    });
}

// Compteur de caractères pour l'extrait
if (excerptEl && cntEl) {
    function updateCount() {
        var len = excerptEl.value.length;
        cntEl.textContent = len + '/150';
        cntEl.style.color = len > 150 ? '#c0392b' : '#6b7f96';
    }
    excerptEl.addEventListener('input', updateCount);
    updateCount();
}
</script>
JS;

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Breadcrumb ──────────────────────────────────────────── -->
<div style="margin-bottom:18px;font-size:.82rem;color:#6b7f96">
  <a href="echos.php" style="color:#6b7f96;text-decoration:none">&larr; Les &Eacute;chos</a>
  <span style="margin:0 8px">/</span>
  <?= $is_edit ? htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') : 'Nouvel article' ?>
</div>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">
      <?= $is_edit ? '&#x270F;&#xFE0F; &Eacute;diter l\'article' : '&#x2B; Nouvel article' ?>
    </h1>
    <?php if ($is_edit): ?>
      <p class="adm-page-sub">
        ID #<?= $article['id'] ?> &mdash;
        <a href="<?= defined('BASE_URL') ? rtrim(BASE_URL,'/') : '' ?>/les-echos-article.php?slug=<?= htmlspecialchars($article['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" style="color:#7ab8e8">
          Voir l'article &#x2197;
        </a>
      </p>
    <?php endif; ?>
  </div>
  <div class="adm-page-actions">
    <a href="echos.php" class="btn-adm btn-adm-ghost">Retour &agrave; la liste</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= $flash_type ?>">
    <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!$pdo): ?>
  <div class="adm-flash adm-flash-err">
    Base de donn&eacute;es non disponible. Les modifications ne seront pas sauvegard&eacute;es.
  </div>
<?php endif; ?>

<!-- ── Formulaire ──────────────────────────────────────────── -->
<form method="post" action="echo-edit.php<?= $is_edit ? '?id='.$article['id'] : '' ?>">
  <?= csrf_field() ?>

  <div class="adm-card">
    <p class="adm-card-title">Informations principales</p>
    <div class="adm-form-grid">

      <!-- Titre -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_title">Titre <span>*</span></label>
        <input
          id="f_title"
          type="text"
          name="title"
          class="adm-input"
          maxlength="255"
          required
          placeholder="Titre de l'article"
          value="<?= htmlspecialchars($v_title, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <!-- Slug -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_slug">Slug (URL)</label>
        <input
          id="f_slug"
          type="text"
          name="slug"
          class="adm-input"
          maxlength="255"
          placeholder="genere-automatiquement-depuis-le-titre"
          value="<?= htmlspecialchars($v_slug, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Généré automatiquement depuis le titre. Modifiable manuellement.</span>
      </div>

      <!-- Rubrique -->
      <div class="adm-field">
        <label class="adm-label" for="f_rubrique">Rubrique <span>*</span></label>
        <select id="f_rubrique" name="rubrique" class="adm-select">
          <?php foreach ($rubrique_options as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $v_rubrique === $k ? 'selected' : '' ?>>
              <?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Saison associée -->
      <div class="adm-field">
        <label class="adm-label" for="f_season">Saison associée</label>
        <select id="f_season" name="season_id" class="adm-select">
          <option value="">— Aucune saison —</option>
          <?php foreach ($seasons_list as $sea): ?>
            <option value="<?= (int)$sea['id'] ?>"
              <?= $v_season_id === (int)$sea['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($sea['title'], ENT_QUOTES, 'UTF-8') ?>
              <?php if ($sea['status'] === 'active'): ?>
                <span> ● EN COURS</span>
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="adm-hint">Rattache cet écho à une saison pour l'afficher dans le bilan saisonnier.</div>
      </div>

      <!-- Auteur -->
      <div class="adm-field">
        <label class="adm-label" for="f_author">Auteur</label>
        <input
          id="f_author"
          type="text"
          name="author_name"
          class="adm-input"
          maxlength="100"
          value="<?= htmlspecialchars($v_author, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <!-- Image de couverture -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_cover">Image de couverture (URL)</label>
        <input
          id="f_cover"
          type="url"
          name="cover_image"
          class="adm-input"
          placeholder="https://... ou chemin relatif assets/img/..."
          value="<?= htmlspecialchars($v_cover, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Laissez vide pour afficher un dégradé de couleur selon la rubrique.</span>
      </div>

    </div>
  </div>

  <!-- Extrait -->
  <div class="adm-card">
    <p class="adm-card-title">Chapeau / Extrait</p>
    <div class="adm-field">
      <label class="adm-label" for="f_excerpt">Extrait (150 caractères max)</label>
      <textarea
        id="f_excerpt"
        name="excerpt"
        class="adm-textarea"
        rows="3"
        maxlength="150"
        placeholder="Résumé accrocheur de l'article (affiché dans la liste)…"
      ><?= htmlspecialchars($v_excerpt, ENT_QUOTES, 'UTF-8') ?></textarea>
      <span class="adm-hint" id="excerpt_count">0/150</span>
    </div>
  </div>

  <!-- Corps de l'article -->
  <div class="adm-card">
    <p class="adm-card-title">Corps de l'article</p>
    <div class="adm-field">
      <label class="adm-label" for="f_body">Contenu</label>
      <textarea
        id="f_body"
        name="body"
        class="adm-textarea"
        rows="18"
        style="min-height:300px;font-family:monospace;font-size:.85rem"
        placeholder="Contenu de l'article. Vous pouvez utiliser du HTML simple (<p>, <strong>, <em>, <ul>, <li>, <a href=...>)."
      ><?= htmlspecialchars($v_body, ENT_QUOTES, 'UTF-8') ?></textarea>
      <span class="adm-hint">HTML simple accepté. Pas d'éditeur WYSIWYG pour garder le contrôle.</span>
    </div>
  </div>

  <!-- Publication -->
  <div class="adm-card">
    <p class="adm-card-title">Publication</p>
    <div class="adm-form-grid">
      <div class="adm-field">
        <label class="adm-label" for="f_status">Statut</label>
        <select id="f_status" name="status" class="adm-select">
          <option value="draft"     <?= $v_status === 'draft'     ? 'selected' : '' ?>>Brouillon</option>
          <option value="published" <?= $v_status === 'published' ? 'selected' : '' ?>>Publié</option>
        </select>
      </div>
      <div class="adm-field">
        <label class="adm-label" for="f_pub_at">Date de publication</label>
        <input
          id="f_pub_at"
          type="datetime-local"
          name="published_at"
          class="adm-input"
          value="<?= htmlspecialchars($v_pub_at, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Laissez vide pour utiliser la date actuelle lors de la publication.</span>
      </div>
    </div>
  </div>

  <!-- Boutons de soumission -->
  <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-top:4px">
    <a href="echos.php" class="btn-adm btn-adm-ghost">Annuler</a>
    <button type="submit" name="status_override" value="draft" class="btn-adm btn-adm-ghost">
      &#x1F4BE; Enregistrer en brouillon
    </button>
    <button type="submit" class="btn-adm btn-adm-primary">
      <?= $is_edit ? '&#x2713; Sauvegarder' : '&#x2B; Créer l\'article' ?>
    </button>
  </div>

</form>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
