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
$pdo = db();

// ── Rubriques définitives ─────────────────────────────────────
$rubrique_options = [
    'les-invisibles' => 'Les Invisibles',
    'deux-minutes'   => "T'as deux minutes\xc2\xa0?",
    'les-ovnis'      => 'Les OVNIS de la Zone85',
    'actualite'      => 'Actualité',
    'chemins'        => 'Sur les Chemins',
    'evenements'     => 'Événements',
];

// ── Auto-migration : gallery + video_url ──────────────────────
if ($pdo) {
    try {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM articles")->fetchAll(), 'Field');
        if (!in_array('gallery', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN gallery JSON NULL AFTER cover_image");
        }
        if (!in_array('video_url', $cols)) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN video_url VARCHAR(500) NULL AFTER gallery");
        }
    } catch (PDOException $e) { /* table absente */ }
}

// ── Chargement article existant ────────────────────────────────
$id      = (int)($_GET['id'] ?? 0);
$article = null;
if ($id > 0 && $pdo) {
    try {
        $s = $pdo->prepare('SELECT * FROM articles WHERE id=:id LIMIT 1');
        $s->execute([':id' => $id]);
        $article = $s->fetch() ?: null;
    } catch (PDOException $e) {}
}
$is_edit          = $article !== null;
$admin_page_title = $is_edit ? 'Éditer — ' . $article['title'] : 'Nouvel article';

$flash      = '';
$flash_type = 'ok';
if (!empty($_GET['saved'])) {
    $flash = 'Article enregistré avec succès.';
}

// ── Saisons disponibles ───────────────────────────────────────
$seasons_list = [];
if ($pdo) {
    try {
        $seasons_list = $pdo->query("SELECT id, title, status FROM seasons ORDER BY id DESC")->fetchAll();
    } catch (PDOException $e) {}
}

// ── Gestion upload couverture ─────────────────────────────────
function handle_cover_upload(): ?string
{
    if (empty($_FILES['cover_file']['tmp_name'])) return null;
    if ($_FILES['cover_file']['error'] !== UPLOAD_ERR_OK) return null;

    $ext = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) return null;

    $mime = mime_content_type($_FILES['cover_file']['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) return null;

    if ($_FILES['cover_file']['size'] > UPLOAD_MAX_SIZE_MEDIA) return null;

    $dir = BASE_PATH . 'uploads/echos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $dir . $filename)) {
        return 'uploads/echos/' . $filename;
    }
    return null;
}

// ── Traitement POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Formulaire rejeté.';
        $flash_type = 'err';
    } else {
        $f_title     = safe_input($_POST['title']       ?? '', 255);
        $f_slug      = safe_input($_POST['slug']        ?? '', 255);
        $f_rubrique  = safe_input($_POST['rubrique']    ?? 'actualite', 50);
        $f_author    = safe_input($_POST['author_name'] ?? 'Équipe Zone85', 100);
        $f_excerpt   = safe_input($_POST['excerpt']     ?? '', 500);
        $f_body      = $_POST['body']        ?? '';  // HTML Quill, non échappé
        $f_cover     = safe_input($_POST['cover_image'] ?? '', 500);
        $f_gallery   = $_POST['gallery_json'] ?? '[]';
        $f_video_url = safe_input($_POST['video_url']   ?? '', 500);
        $f_status    = in_array($_POST['status'] ?? '', ['draft', 'published'], true)
                          ? $_POST['status'] : 'draft';
        $f_pub_at    = $_POST['published_at'] ?? '';
        $f_season_id = !empty($_POST['season_id']) ? (int)$_POST['season_id'] : null;

        // Cover upload (écrase l'URL si un fichier est fourni)
        $uploaded_cover = handle_cover_upload();
        if ($uploaded_cover !== null) $f_cover = $uploaded_cover;

        // Nettoyage slug
        $f_slug = strtolower($f_slug);
        $f_slug = preg_replace('/[^a-z0-9\-]/', '', $f_slug);
        $f_slug = trim($f_slug, '-');
        if (empty($f_slug)) $f_slug = 'article-' . time();

        if (!array_key_exists($f_rubrique, $rubrique_options)) $f_rubrique = 'actualite';

        // Valider JSON galerie
        $gallery_arr = json_decode($f_gallery, true);
        if (!is_array($gallery_arr)) $f_gallery = '[]';

        // Date de publication
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
                $gallery_val   = ($f_gallery !== '[]') ? $f_gallery : null;
                $video_val     = $f_video_url ?: null;

                if ($is_edit) {
                    $s = $pdo->prepare('
                        UPDATE articles SET
                            title=:title, slug=:slug, rubrique=:rub, author_name=:author,
                            excerpt=:excerpt, body=:body, cover_image=:cover,
                            gallery=:gallery, video_url=:video,
                            status=:status, published_at=:pub, season_id=:season
                        WHERE id=:id
                    ');
                    $s->execute([
                        ':title'   => $f_title,   ':slug'    => $f_slug,
                        ':rub'     => $f_rubrique, ':author'  => $f_author,
                        ':excerpt' => $f_excerpt ?: null, ':body' => $f_body ?: null,
                        ':cover'   => $f_cover ?: null,
                        ':gallery' => $gallery_val, ':video'  => $video_val,
                        ':status'  => $f_status,   ':pub'    => $published_at,
                        ':season'  => $f_season_id, ':id'    => $article['id'],
                    ]);
                    $saved_id = $article['id'];
                } else {
                    $s = $pdo->prepare('
                        INSERT INTO articles
                            (title, slug, rubrique, season_id, author_name, excerpt, body,
                             cover_image, gallery, video_url, status, published_at)
                        VALUES
                            (:title, :slug, :rub, :season, :author, :excerpt, :body,
                             :cover, :gallery, :video, :status, :pub)
                    ');
                    $s->execute([
                        ':title'   => $f_title,   ':slug'    => $f_slug,
                        ':rub'     => $f_rubrique, ':season'  => $f_season_id,
                        ':author'  => $f_author,
                        ':excerpt' => $f_excerpt ?: null, ':body' => $f_body ?: null,
                        ':cover'   => $f_cover ?: null,
                        ':gallery' => $gallery_val, ':video'  => $video_val,
                        ':status'  => $f_status,   ':pub'    => $published_at,
                    ]);
                    $saved_id = (int)$pdo->lastInsertId();
                }
                $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                header('Location: ' . $base . '/admin/echo-edit.php?id=' . $saved_id . '&saved=1');
                exit;
            } catch (PDOException $e) {
                $flash = 'Erreur de sauvegarde : ' . e($e->getMessage());
                $flash_type = 'err';
            }
        }
    }
}

// ── Valeurs formulaire ────────────────────────────────────────
$v_title     = $_POST['title']       ?? ($article['title']       ?? '');
$v_slug      = $_POST['slug']        ?? ($article['slug']        ?? '');
$v_rubrique  = $_POST['rubrique']    ?? ($article['rubrique']    ?? 'actualite');
$v_season_id = isset($_POST['season_id']) ? (int)$_POST['season_id']
             : (int)($article['season_id'] ?? 0);
$v_author    = $_POST['author_name'] ?? ($article['author_name'] ?? 'Équipe Zone85');
$v_excerpt   = $_POST['excerpt']     ?? ($article['excerpt']     ?? '');
$v_body      = $_POST['body']        ?? ($article['body']        ?? '');
$v_cover     = $_POST['cover_image'] ?? ($article['cover_image'] ?? '');
$v_gallery   = json_decode($article['gallery'] ?? '[]', true);
if (!is_array($v_gallery)) $v_gallery = [];
$v_video_url = $_POST['video_url']   ?? ($article['video_url']  ?? '');
$v_status    = $_POST['status']      ?? ($article['status']      ?? 'draft');
$v_pub_at    = '';
if (!empty($article['published_at'])) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $article['published_at']);
    if ($dt) $v_pub_at = $dt->format('Y-m-d\TH:i');
}
if (!empty($_POST['published_at'])) $v_pub_at = $_POST['published_at'];

$csrf_val        = e(csrf_token());
$v_gallery_json  = json_encode($v_gallery, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$v_body_js       = json_encode($v_body);
$base_url_js     = json_encode(defined('BASE_URL') ? rtrim(BASE_URL, '/') : '');

$admin_scripts = <<<SCRIPTS
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
// ── Slugify ────────────────────────────────────────────────────
function slugify(t){
    var m={'à':'a','â':'a','ä':'a','á':'a','ã':'a','è':'e','é':'e','ê':'e','ë':'e',
           'î':'i','ï':'i','ì':'i','ô':'o','ö':'o','ò':'o','ù':'u','û':'u','ü':'u',
           'ç':'c','ñ':'n','œ':'oe','æ':'ae'};
    return t.toLowerCase()
        .replace(/[àâäáãèéêëîïìôöòùûüçñœæ]/g,function(c){return m[c]||c;})
        .replace(/[^a-z0-9\s\-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
}
var slugLocked = document.getElementById('f_slug').value.length > 0;
document.getElementById('f_title').addEventListener('input',function(){
    if(!slugLocked) document.getElementById('f_slug').value=slugify(this.value);
});
document.getElementById('f_slug').addEventListener('input',function(){
    slugLocked = this.value.length > 0;
});
document.getElementById('f_slug').addEventListener('blur',function(){
    this.value = slugify(this.value);
});

// ── Compteur extrait ───────────────────────────────────────────
var excerptEl = document.getElementById('f_excerpt');
var cntEl     = document.getElementById('excerpt_count');
function updateCount(){
    var l = excerptEl.value.length;
    cntEl.textContent = l+'/150';
    cntEl.style.color = l > 150 ? '#c0392b' : '#6b7f96';
}
excerptEl.addEventListener('input', updateCount);
updateCount();

// ── Aperçu couverture ──────────────────────────────────────────
document.getElementById('cover_file_input').addEventListener('change', function(){
    if(this.files[0]){
        var r = new FileReader();
        r.onload = function(e){
            var p = document.getElementById('cover_preview');
            p.src = e.target.result;
            p.style.display = 'block';
        };
        r.readAsDataURL(this.files[0]);
    }
});

// ── Galerie ────────────────────────────────────────────────────
var gallery = {$v_gallery_json};
var baseUrl = {$base_url_js};

function renderGallery(){
    var c = document.getElementById('gallery_grid');
    if(gallery.length === 0){
        c.innerHTML = '<p style="color:#aaa;font-size:.82rem;padding:8px 0">Aucune photo dans la galerie.</p>';
    } else {
        c.innerHTML = gallery.map(function(p,i){
            var src = p.startsWith('http') ? p : baseUrl+'/'+p;
            return '<div style="position:relative;display:inline-block;margin:4px">'
                +'<img src="'+src+'" style="width:110px;height:80px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,.08)" loading="lazy">'
                +'<button type="button" onclick="removeGallery('+i+')" '
                +'style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;'
                +'background:#c0392b;color:#fff;border:none;cursor:pointer;font-size:.7rem;line-height:1;display:flex;align-items:center;justify-content:center">'
                +'✕</button></div>';
        }).join('');
    }
    document.getElementById('gallery_json').value = JSON.stringify(gallery);
}

function removeGallery(i){
    if(confirm('Retirer cette photo de la galerie ?')){
        gallery.splice(i,1);
        renderGallery();
    }
}

document.getElementById('gallery_file_input').addEventListener('change',function(){
    Array.from(this.files).forEach(uploadGalleryFile);
    this.value = '';
});

function uploadGalleryFile(file){
    var fd = new FormData();
    fd.append('file', file);
    fd.append('csrf_token', '{$csrf_val}');
    var btn = document.getElementById('gallery_upload_btn');
    if(btn) btn.textContent = '⏳ Upload…';
    fetch(baseUrl+'/ajax/echo-upload.php', {method:'POST', body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.ok){
                gallery.push(d.path);
                renderGallery();
            } else {
                alert('Erreur upload : '+(d.error||'inconnue'));
            }
            if(btn) btn.textContent = '📷 Ajouter des photos';
        })
        .catch(function(){
            alert('Erreur réseau lors de l\'upload.');
            if(btn) btn.textContent = '📷 Ajouter des photos';
        });
}

renderGallery();

// ── Aperçu YouTube ─────────────────────────────────────────────
var ytInput = document.getElementById('f_video_url');
var ytPreview = document.getElementById('yt_preview');
var ytIframe  = document.getElementById('yt_iframe');

function updateYT(){
    var url = ytInput.value;
    var m   = url.match(/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_\-]{11})/);
    if(m){
        ytIframe.src = 'https://www.youtube.com/embed/'+m[1];
        ytPreview.style.display = 'block';
    } else {
        ytPreview.style.display = 'none';
        ytIframe.src = '';
    }
}
ytInput.addEventListener('input', updateYT);
if(ytInput.value) updateYT();

// ── Quill éditeur ──────────────────────────────────────────────
var quill = new Quill('#quill_editor', {
    modules: {
        toolbar: [
            ['bold','italic','underline','strike'],
            [{header:[2,3,false]}],
            ['blockquote'],
            [{list:'ordered'},{list:'bullet'}],
            ['link'],
            ['clean']
        ]
    },
    theme: 'snow'
});

// Charger le contenu existant
var bodyContent = {$v_body_js};
if(bodyContent) quill.clipboard.dangerouslyPasteHTML(bodyContent);

// Synchroniser avant soumission
document.getElementById('echo_form').addEventListener('submit',function(){
    document.getElementById('f_body').value = quill.root.innerHTML;
});

// Style Quill dans l'interface admin
var qlEl = document.querySelector('.ql-container');
if(qlEl){ qlEl.style.minHeight = '300px'; qlEl.style.fontSize = '1rem'; qlEl.style.fontFamily = 'Inter, sans-serif'; }
</script>
SCRIPTS;

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Breadcrumb ──────────────────────────────────────────── -->
<div style="margin-bottom:18px;font-size:.82rem;color:#6b7f96">
  <a href="echos.php" style="color:#6b7f96;text-decoration:none">← Les Échos</a>
  <span style="margin:0 8px">/</span>
  <?= $is_edit ? e($article['title']) : 'Nouvel article' ?>
</div>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">
      <?= $is_edit ? '✏️ Éditer l\'article' : '+ Nouvel article' ?>
    </h1>
    <?php if ($is_edit): ?>
      <p class="adm-page-sub">
        ID #<?= (int)$article['id'] ?> &mdash;
        <a href="<?= defined('BASE_URL') ? rtrim(BASE_URL, '/') : '' ?>/les-echos-article.php?slug=<?= urlencode($article['slug'] ?? '') ?>"
           target="_blank" style="color:#7ab8e8">Voir l'article ↗</a>
      </p>
    <?php endif; ?>
  </div>
  <div class="adm-page-actions">
    <a href="echos.php" class="btn-adm btn-adm-ghost">← Retour à la liste</a>
    <button form="echo_form" type="submit" class="btn-adm btn-adm-primary">
      <?= $is_edit ? '✓ Sauvegarder' : '+ Créer l\'article' ?>
    </button>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= e($flash_type) ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<!-- ── Formulaire ──────────────────────────────────────────── -->
<form id="echo_form" method="post"
      action="echo-edit.php<?= $is_edit ? '?id=' . (int)$article['id'] : '' ?>"
      enctype="multipart/form-data">
  <?= csrf_field() ?>

  <!-- ── Informations principales ──────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Informations principales</p>
    <div class="adm-form-grid">

      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_title">Titre <span>*</span></label>
        <input id="f_title" type="text" name="title" class="adm-input"
               maxlength="255" required placeholder="Titre de l'article"
               value="<?= e($v_title) ?>">
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_slug">Slug (URL)</label>
        <input id="f_slug" type="text" name="slug" class="adm-input"
               maxlength="255" placeholder="généré-automatiquement"
               value="<?= e($v_slug) ?>">
        <span class="adm-hint">Généré depuis le titre. Modifiable manuellement.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_rubrique">Rubrique <span>*</span></label>
        <select id="f_rubrique" name="rubrique" class="adm-select">
          <?php foreach ($rubrique_options as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= $v_rubrique === $k ? 'selected' : '' ?>>
              <?= e($lbl) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_season">Saison associée</label>
        <select id="f_season" name="season_id" class="adm-select">
          <option value="">— Aucune saison —</option>
          <?php foreach ($seasons_list as $sea): ?>
            <option value="<?= (int)$sea['id'] ?>"
              <?= $v_season_id === (int)$sea['id'] ? 'selected' : '' ?>>
              <?= e($sea['title']) ?>
              <?= $sea['status'] === 'active' ? ' ● EN COURS' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_author">Auteur</label>
        <input id="f_author" type="text" name="author_name" class="adm-input"
               maxlength="100" value="<?= e($v_author) ?>">
      </div>

    </div>
  </div>

  <!-- ── Image de couverture ───────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Image de couverture</p>

    <?php if ($v_cover): ?>
    <div style="margin-bottom:14px">
      <img src="<?= e($v_cover) ?>" id="cover_preview" loading="lazy"
           style="max-width:100%;max-height:280px;border-radius:10px;object-fit:cover;display:block;border:1px solid rgba(0,0,0,.08)">
    </div>
    <?php else: ?>
    <img src="" id="cover_preview" style="display:none;max-width:100%;max-height:280px;border-radius:10px;object-fit:cover;margin-bottom:14px;border:1px solid rgba(0,0,0,.08)">
    <?php endif; ?>

    <div class="adm-form-grid">
      <div class="adm-field">
        <label class="adm-label">Uploader un nouveau fichier</label>
        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border:1.5px dashed #b8c9db;border-radius:8px;font-size:.82rem;font-weight:600;color:#4a6177">
          📁 Choisir une image
          <input type="file" id="cover_file_input" name="cover_file"
                 accept=".jpg,.jpeg,.png,.webp" style="display:none">
        </label>
        <span class="adm-hint">JPG, PNG, WebP — max <?= round(UPLOAD_MAX_SIZE_MEDIA / 1048576) ?> Mo. Écrase l'URL ci-dessous.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_cover">Ou URL externe</label>
        <input id="f_cover" type="text" name="cover_image" class="adm-input"
               placeholder="https://... ou assets/img/..."
               value="<?= e($v_cover) ?>">
        <span class="adm-hint">Laissez vide pour afficher le dégradé de la rubrique.</span>
      </div>
    </div>
  </div>

  <!-- ── Chapeau / Extrait ─────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Chapeau / Extrait</p>
    <div class="adm-field">
      <label class="adm-label" for="f_excerpt">Accroche (150 caractères max)</label>
      <textarea id="f_excerpt" name="excerpt" class="adm-textarea" rows="3"
                maxlength="150"
                placeholder="Résumé accrocheur affiché dans la liste des articles…"><?= e($v_excerpt) ?></textarea>
      <span class="adm-hint" id="excerpt_count">0/150</span>
    </div>
  </div>

  <!-- ── Corps de l'article ───────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Corps de l'article</p>
    <!-- Textarea cachée — synchronisée par Quill avant soumission -->
    <textarea id="f_body" name="body" style="display:none"><?= e($v_body) ?></textarea>
    <!-- Barre d'outils Quill + éditeur -->
    <div style="border:1px solid #d6dde6;border-radius:8px;overflow:hidden;background:#fff">
      <div id="quill_editor" style="min-height:300px;font-size:1rem"></div>
    </div>
    <span class="adm-hint" style="margin-top:6px;display:block">
      Barre d'outils : <strong>Gras</strong>, <em>Italique</em>, Souligné, Barré, H2/H3, Blockquote, Listes, Lien, Effacer la mise en forme.
    </span>
  </div>

  <!-- ── Galerie photo ─────────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Galerie photo</p>
    <input type="hidden" name="gallery_json" id="gallery_json" value="<?= e($v_gallery_json) ?>">
    <div id="gallery_grid" style="margin-bottom:14px"></div>
    <label id="gallery_upload_btn"
           style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border:1.5px dashed #b8c9db;border-radius:8px;font-size:.82rem;font-weight:600;color:#4a6177">
      📷 Ajouter des photos
      <input type="file" id="gallery_file_input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none">
    </label>
    <span class="adm-hint" style="display:block;margin-top:6px">
      Plusieurs fichiers acceptés. JPG, PNG, WebP — max <?= round(UPLOAD_MAX_SIZE_MEDIA / 1048576) ?> Mo par image.
    </span>
  </div>

  <!-- ── Vidéo YouTube ─────────────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">Vidéo YouTube (optionnel)</p>
    <div class="adm-field">
      <label class="adm-label" for="f_video_url">URL YouTube</label>
      <input id="f_video_url" type="url" name="video_url" class="adm-input"
             placeholder="https://www.youtube.com/watch?v=..."
             value="<?= e($v_video_url) ?>">
      <span class="adm-hint">Coller l'URL complète de la vidéo YouTube. L'embed apparaît dans l'article après le corps du texte.</span>
    </div>
    <div id="yt_preview" style="margin-top:14px;display:none">
      <iframe id="yt_iframe" src="" frameborder="0" allowfullscreen loading="lazy"
              style="width:100%;aspect-ratio:16/9;border-radius:8px;display:block"></iframe>
    </div>
  </div>

  <!-- ── Publication ───────────────────────────────────────── -->
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
        <input id="f_pub_at" type="datetime-local" name="published_at" class="adm-input"
               value="<?= e($v_pub_at) ?>">
        <span class="adm-hint">Vide = date actuelle à la publication.</span>
      </div>
    </div>
  </div>

  <!-- ── Actions ───────────────────────────────────────────── -->
  <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-top:4px;margin-bottom:32px">
    <a href="echos.php" class="btn-adm btn-adm-ghost">Annuler</a>
    <button type="submit" class="btn-adm btn-adm-primary">
      <?= $is_edit ? '✓ Sauvegarder' : '+ Créer l\'article' ?>
    </button>
  </div>

</form>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
