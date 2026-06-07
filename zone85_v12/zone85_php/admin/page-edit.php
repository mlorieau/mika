<?php
// ============================================================
// admin/page-edit.php — Éditeur de page CMS Zone85
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_admin();

$pdo     = db();
$page_id = (int)($_GET['id'] ?? 0);
$is_new  = ($page_id === 0);
$row     = null;
$msg     = null;

// ── Ensure table exists ─────────────────────────────────────
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            slug             VARCHAR(120) NOT NULL UNIQUE,
            title            VARCHAR(255) NOT NULL DEFAULT '',
            meta_title       VARCHAR(255) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            hero_title       VARCHAR(255) DEFAULT NULL,
            hero_subtitle    TEXT DEFAULT NULL,
            hero_image       VARCHAR(512) DEFAULT NULL,
            content_blocks   JSON DEFAULT NULL,
            status           ENUM('published','draft') NOT NULL DEFAULT 'draft',
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug   (slug),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {}
}

// ── Load existing page ──────────────────────────────────────
if (!$is_new && $pdo) {
    try {
        $s = $pdo->prepare("SELECT * FROM pages WHERE id = :id");
        $s->execute([':id' => $page_id]);
        $row = $s->fetch() ?: null;
        if (!$row) { header('Location: pages.php'); exit; }
    } catch (PDOException $e) {}
}

// ── Handle SAVE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $slug  = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $title = trim($_POST['title'] ?? '');

    // Validate blocks JSON from hidden field
    $blocks_raw     = trim($_POST['content_blocks'] ?? '[]');
    $blocks_decoded = json_decode($blocks_raw, true);
    $blocks_json    = json_encode(is_array($blocks_decoded) ? $blocks_decoded : [], JSON_UNESCAPED_UNICODE);

    $data = [
        'slug'             => $slug ?: 'page-' . time(),
        'title'            => $title ?: 'Sans titre',
        'meta_title'       => trim($_POST['meta_title'] ?? '') ?: null,
        'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        'hero_title'       => trim($_POST['hero_title'] ?? '') ?: null,
        'hero_subtitle'    => trim($_POST['hero_subtitle'] ?? '') ?: null,
        'hero_image'       => trim($_POST['hero_image'] ?? '') ?: null,
        'content_blocks'   => $blocks_json,
        'status'           => in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'draft',
    ];

    if ($pdo) {
        try {
            if ($is_new) {
                $sql = "INSERT INTO pages (slug, title, meta_title, meta_description, hero_title, hero_subtitle, hero_image, content_blocks, status)
                        VALUES (:slug, :title, :meta_title, :meta_description, :hero_title, :hero_subtitle, :hero_image, :content_blocks, :status)";
                $s = $pdo->prepare($sql);
                $s->execute(array_combine(array_map(fn($k) => ":$k", array_keys($data)), array_values($data)));
                $page_id = (int)$pdo->lastInsertId();
                $is_new  = false;
                header("Location: page-edit.php?id={$page_id}&saved=1");
                exit;
            } else {
                $sql = "UPDATE pages SET slug=:slug, title=:title, meta_title=:meta_title, meta_description=:meta_description,
                        hero_title=:hero_title, hero_subtitle=:hero_subtitle, hero_image=:hero_image,
                        content_blocks=:content_blocks, status=:status WHERE id=:id";
                $s = $pdo->prepare($sql);
                $data['id'] = $page_id;
                $s->execute(array_combine(array_map(fn($k) => ":$k", array_keys($data)), array_values($data)));
                header("Location: page-edit.php?id={$page_id}&saved=1");
                exit;
            }
        } catch (PDOException $e) {
            $msg = ['type' => 'err', 'text' => 'Erreur : ' . $e->getMessage()];
        }
    }
    // Reload row after failed save attempt
    if (!$is_new && $pdo) {
        try {
            $s = $pdo->prepare("SELECT * FROM pages WHERE id=:id");
            $s->execute([':id' => $page_id]);
            $row = $s->fetch() ?: $row;
        } catch (PDOException $e) {}
    }
}

if (isset($_GET['saved'])) {
    $msg = ['type' => 'ok', 'text' => 'Page enregistrée avec succès.'];
}

$admin_current    = 'pages';
$admin_page_title = $is_new ? 'Nouvelle page' : 'Modifier : ' . ($row['title'] ?? '');

// ── Current values for form population ──────────────────────
$f = [
    'slug'             => $row['slug']             ?? '',
    'title'            => $row['title']            ?? '',
    'meta_title'       => $row['meta_title']       ?? '',
    'meta_description' => $row['meta_description'] ?? '',
    'hero_title'       => $row['hero_title']       ?? '',
    'hero_subtitle'    => $row['hero_subtitle']    ?? '',
    'hero_image'       => $row['hero_image']       ?? '',
    'status'           => $row['status']           ?? 'draft',
    'content_blocks'   => $row['content_blocks']   ?? '[]',
];
$blocks_for_js = htmlspecialchars($f['content_blocks'], ENT_QUOTES, 'UTF-8');

require_once '_admin-header.php';
?>

<!-- ── Block editor CSS ─────────────────────────────────────── -->
<style>
.be-toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.be-add-btn { position:relative; display:inline-block; }
.be-add-btn select { padding:9px 14px; border-radius:8px; border:1.5px solid #d0cbc5; font-family:'Inter',sans-serif; font-size:.82rem; background:#fff; cursor:pointer; color:#0f1e2d; }
.be-add-btn select:focus { outline:none; border-color:#ea5649; }
.be-block { background:#fff; border-radius:10px; border:1.5px solid #e0ddd5; margin-bottom:12px; overflow:hidden; }
.be-block-header { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#f8f4ef; border-bottom:1px solid #e8e2db; }
.be-block-label { font-size:.8rem; font-weight:700; color:#0c1e2e; }
.be-block-controls { display:flex; gap:4px; }
.be-block-body { padding:16px; }
.be-btn { padding:5px 10px; border-radius:6px; border:1px solid #d0cbc5; background:#fff; cursor:pointer; font-size:.8rem; color:#0c1e2e; transition:background .12s; }
.be-btn:hover { background:#f0ece7; }
.be-btn:disabled { opacity:.3; cursor:default; }
.be-btn-del { border-color:#fcc; color:#c0392b; }
.be-btn-del:hover { background:#fee; }
.be-empty { text-align:center; padding:32px; color:#6b7f96; font-size:.88rem; background:#faf7f4; border-radius:8px; border:2px dashed #e0ddd5; }
/* Two-column editor layout */
.pe-layout { display:grid; grid-template-columns:2fr 1fr; gap:20px; align-items:start; }
@media (max-width:900px) { .pe-layout { grid-template-columns:1fr; } }
/* Sticky save bar */
.pe-save-bar {
  position:fixed; bottom:0; left:224px; right:0;
  background:#0c1e2e; border-top:2px solid rgba(234,86,73,.5);
  padding:12px 28px; display:flex; align-items:center; gap:12px;
  z-index:150; box-shadow:0 -4px 24px rgba(0,0,0,.18);
}
@media (max-width:900px) { .pe-save-bar { left:0; } }
.pe-save-bar-hint { font-size:.76rem; color:rgba(255,255,255,.4); font-weight:600; margin-left:auto; }
</style>

<!-- ── Page header ────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title"><?= e($admin_page_title) ?></h1>
    <?php if (!$is_new): ?>
    <p class="adm-page-sub">
      Slug :
      <code style="font-size:.78rem;background:#f0ece7;padding:1px 6px;border-radius:4px"><?= e($f['slug']) ?></code>
    </p>
    <?php endif; ?>
  </div>
  <div class="adm-page-actions">
    <a href="pages.php" class="btn-adm btn-adm-ghost">← Retour</a>
    <?php if (!$is_new): ?>
    <a href="<?= e(rtrim(BASE_URL, '/')) ?>/page.php?slug=<?= urlencode($f['slug']) ?>"
       target="_blank" rel="noopener"
       class="btn-adm btn-adm-ghost">Prévisualiser ↗</a>
    <?php endif; ?>
    <button type="submit" form="page-form" name="save" value="1" class="btn-adm btn-adm-success">
      ✓ Enregistrer
    </button>
  </div>
</div>

<!-- ── Flash message ──────────────────────────────────────── -->
<?php if ($msg): ?>
<div class="adm-flash adm-flash-<?= e($msg['type']) ?>" style="margin-bottom:20px">
  <?php if ($msg['type'] === 'ok'): ?>✓<?php elseif ($msg['type'] === 'err'): ?>✕<?php else: ?>⚠<?php endif; ?>
  <?= e($msg['text']) ?>
</div>
<?php endif; ?>

<!-- ── Main form ──────────────────────────────────────────── -->
<form id="page-form" method="POST" onsubmit="BlockEditor.sync(); return true;">
  <input type="hidden" name="save" value="1">

  <div class="pe-layout">

    <!-- ── LEFT COLUMN ───────────────────────────────────── -->
    <div>

      <!-- Card 1 — Informations générales -->
      <div class="adm-card">
        <p class="adm-card-title">Informations générales</p>

        <div class="adm-field" style="margin-bottom:16px">
          <label class="adm-label">Titre de la page <span>*</span></label>
          <input type="text" name="title" class="adm-input" required
                 value="<?= e($f['title']) ?>"
                 placeholder="Ma super page">
        </div>

        <div class="adm-field" style="margin-bottom:16px">
          <label class="adm-label">Slug URL</label>
          <input type="text" name="slug" class="adm-input"
                 pattern="[a-z0-9-]+" placeholder="mon-slug"
                 value="<?= e($f['slug']) ?>">
          <span class="adm-hint">URL : <?= e(rtrim(BASE_URL, '/')) ?>/page.php?slug=mon-slug</span>
        </div>

        <div class="adm-field">
          <label class="adm-label">Statut</label>
          <select name="status" class="adm-select">
            <option value="draft"     <?= $f['status'] === 'draft'      ? 'selected' : '' ?>>Brouillon</option>
            <option value="published" <?= $f['status'] === 'published'  ? 'selected' : '' ?>>Publié</option>
          </select>
        </div>
      </div>

      <!-- Card 2 — SEO -->
      <div class="adm-card">
        <p class="adm-card-title">SEO</p>

        <div class="adm-field" style="margin-bottom:16px">
          <label class="adm-label">Meta titre</label>
          <input type="text" name="meta_title" class="adm-input"
                 value="<?= e($f['meta_title']) ?>"
                 placeholder="Titre pour les moteurs de recherche">
          <span class="adm-hint">Si vide, utilise le titre de la page.</span>
        </div>

        <div class="adm-field">
          <label class="adm-label">Meta description</label>
          <textarea name="meta_description" class="adm-textarea" rows="2" maxlength="160"
                    placeholder="Description courte (160 caractères max)"
                    id="meta-desc-field"
                    oninput="document.getElementById('meta-desc-count').textContent = this.value.length"><?= e($f['meta_description']) ?></textarea>
          <span class="adm-hint"><span id="meta-desc-count"><?= mb_strlen($f['meta_description']) ?></span> / 160 caractères</span>
        </div>
      </div>

      <!-- Card 3 — Hero -->
      <div class="adm-card">
        <p class="adm-card-title">Hero (en-tête de page)</p>

        <div class="adm-field" style="margin-bottom:16px">
          <label class="adm-label">Titre hero</label>
          <input type="text" name="hero_title" class="adm-input"
                 value="<?= e($f['hero_title']) ?>"
                 placeholder="Titre affiché en grand dans le hero">
        </div>

        <div class="adm-field" style="margin-bottom:16px">
          <label class="adm-label">Sous-titre hero</label>
          <textarea name="hero_subtitle" class="adm-textarea" rows="2"
                    placeholder="Texte secondaire sous le titre"><?= e($f['hero_subtitle']) ?></textarea>
        </div>

        <div class="adm-field">
          <label class="adm-label">Image hero (URL)</label>
          <input type="text" name="hero_image" class="adm-input"
                 value="<?= e($f['hero_image']) ?>"
                 placeholder="https://...">
          <span class="adm-hint">Laisser vide pour un hero sans image (fond dégradé uniquement).</span>
        </div>
      </div>

      <!-- Card 4 — Blocs de contenu -->
      <div class="adm-card">
        <p class="adm-card-title">Blocs de contenu</p>

        <!-- Hidden field holding serialized JSON -->
        <textarea id="blocks-json" name="content_blocks"
                  style="display:none"><?= $blocks_for_js ?></textarea>

        <!-- Toolbar -->
        <div class="be-toolbar">
          <span style="font-size:.8rem;font-weight:700;color:#6b7f96">Ajouter un bloc :</span>
          <div class="be-add-btn">
            <select onchange="if(this.value){BlockEditor.add(this.value);this.value='';}">
              <option value="">— Choisir un type —</option>
              <option value="text">📝 Texte</option>
              <option value="image">🖼 Image</option>
              <option value="button">🔘 Bouton</option>
              <option value="info_card">🃏 Cartes info</option>
              <option value="faq">❓ FAQ</option>
              <option value="quote">💬 Citation</option>
              <option value="divider">─ Séparateur</option>
            </select>
          </div>
        </div>

        <!-- Blocks render target -->
        <div id="blocks-container"></div>
      </div>

    </div><!-- /.left -->

    <!-- ── RIGHT COLUMN ──────────────────────────────────── -->
    <div>

      <!-- Card — Raccourcis -->
      <div class="adm-card" style="position:sticky;top:70px">
        <p class="adm-card-title">Raccourcis</p>

        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
          <button type="submit" form="page-form" name="save" value="1"
                  class="btn-adm btn-adm-success" style="justify-content:center">
            ✓ Enregistrer
          </button>
          <button type="submit" form="page-form" name="save" value="1"
                  onclick="document.querySelector('[name=status]').value='draft';"
                  class="btn-adm btn-adm-ghost" style="justify-content:center">
            Enregistrer comme brouillon
          </button>
          <?php if (!$is_new): ?>
          <a href="<?= e(rtrim(BASE_URL, '/')) ?>/page.php?slug=<?= urlencode($f['slug']) ?>"
             target="_blank" rel="noopener"
             class="btn-adm btn-adm-ghost" style="justify-content:center">
            Prévisualiser →
          </a>
          <?php endif; ?>
        </div>

        <?php if (!$is_new && $row): ?>
        <div style="font-size:.75rem;color:#6b7f96;border-top:1px solid #f0ece7;padding-top:14px;display:flex;flex-direction:column;gap:6px">
          <div style="display:flex;justify-content:space-between">
            <span style="font-weight:600">Créée le</span>
            <span><?= e($row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '—') ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="font-weight:600">Modifiée le</span>
            <span><?= e($row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '—') ?></span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="font-weight:600">ID</span>
            <span>#<?= (int)$row['id'] ?></span>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Card — Types de blocs disponibles -->
      <div class="adm-card">
        <p class="adm-card-title">Types de blocs disponibles</p>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:.8rem">
          <div><strong>📝 Texte</strong> — Paragraphes de contenu</div>
          <div><strong>🖼 Image</strong> — Photo avec légende</div>
          <div><strong>🔘 Bouton</strong> — CTA cliquable</div>
          <div><strong>🃏 Cartes info</strong> — Grille d'icônes/texte</div>
          <div><strong>❓ FAQ</strong> — Accordéon questions/réponses</div>
          <div><strong>💬 Citation</strong> — Bloc de citation</div>
          <div><strong>─ Séparateur</strong> — Ligne de séparation</div>
        </div>
      </div>

      <!-- Card — Analyse SEO -->
      <div class="adm-card">
        <p class="adm-card-title" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <span>🔍 Analyse SEO</span>
          <span id="pe-seo-score" style="font-size:.8rem;font-weight:900;padding:4px 12px;border-radius:20px;background:#e8e2db;color:#6b7f96">—</span>
        </p>
        <div id="pe-seo-checklist" style="display:flex;flex-direction:column;gap:7px">
          <p style="font-size:.8rem;color:#6b7f96;font-style:italic;margin:0">Remplir les champs pour lancer l'analyse…</p>
        </div>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f0ece7">
          <button type="button" id="pe-ps-btn" onclick="peRunPageSpeed()"
                  style="width:100%;padding:7px 14px;border-radius:7px;border:1.5px solid #d0cbc5;background:#fff;cursor:pointer;font-size:.78rem;font-weight:700;color:#0c1e2e;transition:background .12s">
            ⚡ Tester PageSpeed
          </button>
          <div id="pe-ps-result" style="margin-top:10px"></div>
        </div>
        <div style="margin-top:10px">
          <a href="seo.php" style="font-size:.74rem;color:#6b7f96;font-weight:600">Vue d'ensemble SEO →</a>
        </div>
      </div>

    </div><!-- /.right -->

  </div><!-- /.pe-layout -->

</form>

<!-- ── Sticky save bar ────────────────────────────────────── -->
<div class="pe-save-bar">
  <button type="submit" form="page-form" name="save" value="1"
          class="btn-adm btn-adm-success">
    ✓ Enregistrer
  </button>
  <button type="submit" form="page-form" name="save" value="1"
          onclick="document.querySelector('[name=status]').value='draft';"
          class="btn-adm btn-adm-ghost" style="color:#fff;border-color:rgba(255,255,255,.2)">
    Brouillon
  </button>
  <?php if (!$is_new): ?>
  <span class="pe-save-bar-hint">
    <?= e($f['title'] ?: 'Sans titre') ?>
    &nbsp;·&nbsp;
    <span class="adm-badge <?= $f['status'] === 'published' ? 'badge-active' : 'badge-draft' ?>">
      <?= $f['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
    </span>
  </span>
  <?php endif; ?>
</div>

<!-- bottom padding so content isn't hidden behind sticky bar -->
<div style="height:64px"></div>

<!-- ── Block editor JS ────────────────────────────────────── -->
<script>
const BlockEditor = (function () {
    let blocks = [];
    const container = document.getElementById('blocks-container');
    const jsonField = document.getElementById('blocks-json');

    const BLOCK_TYPES = {
        text:      { label: '📝 Texte',       icon: '📝', defaults: { content: '', size: 'normal' } },
        image:     { label: '🖼 Image',        icon: '🖼', defaults: { url: '', alt: '', caption: '' } },
        button:    { label: '🔘 Bouton',       icon: '🔘', defaults: { label: 'En savoir plus', url: '#', style: 'primary', align: 'left' } },
        info_card: { label: '🃏 Cartes info',  icon: '🃏', defaults: { cards: [{ icon: '⭐', title: 'Titre', body: 'Description' }] } },
        faq:       { label: '❓ FAQ',           icon: '❓', defaults: { items: [{ q: 'Question ?', a: 'Réponse.' }] } },
        quote:     { label: '💬 Citation',     icon: '💬', defaults: { text: '', author: '' } },
        divider:   { label: '─ Séparateur',   icon: '─',  defaults: { style: 'thin' } },
    };

    function sync() {
        jsonField.value = JSON.stringify(blocks);
    }

    function add(type) {
        if (!BLOCK_TYPES[type]) return;
        blocks.push({ type, ...JSON.parse(JSON.stringify(BLOCK_TYPES[type].defaults)) });
        render();
        sync();
        // Scroll to newly added block
        const lastBlock = container.lastElementChild;
        if (lastBlock) lastBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function remove(i) {
        if (!confirm('Supprimer ce bloc ?')) return;
        blocks.splice(i, 1);
        render();
        sync();
    }

    function move(i, dir) {
        const j = i + dir;
        if (j < 0 || j >= blocks.length) return;
        [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
        render();
        sync();
    }

    function readBlock(i) {
        const el = container.querySelector('[data-block-index="' + i + '"]');
        if (!el) return;
        const type = blocks[i].type;
        if (type === 'text') {
            blocks[i].content = el.querySelector('[name="content"]').value;
            blocks[i].size    = el.querySelector('[name="size"]').value;
        } else if (type === 'image') {
            blocks[i].url     = el.querySelector('[name="url"]').value;
            blocks[i].alt     = el.querySelector('[name="alt"]').value;
            blocks[i].caption = el.querySelector('[name="caption"]').value;
        } else if (type === 'button') {
            blocks[i].label = el.querySelector('[name="label"]').value;
            blocks[i].url   = el.querySelector('[name="url"]').value;
            blocks[i].style = el.querySelector('[name="style"]').value;
            blocks[i].align = el.querySelector('[name="align"]').value;
        } else if (type === 'info_card') {
            blocks[i].cards = Array.from(el.querySelectorAll('.block-card-item')).map(c => ({
                icon:  c.querySelector('[name="icon"]').value,
                title: c.querySelector('[name="title"]').value,
                body:  c.querySelector('[name="body"]').value,
            }));
        } else if (type === 'faq') {
            blocks[i].items = Array.from(el.querySelectorAll('.block-faq-item')).map(f => ({
                q: f.querySelector('[name="q"]').value,
                a: f.querySelector('[name="a"]').value,
            }));
        } else if (type === 'quote') {
            blocks[i].text   = el.querySelector('[name="text"]').value;
            blocks[i].author = el.querySelector('[name="author"]').value;
        } else if (type === 'divider') {
            blocks[i].style = el.querySelector('[name="style"]').value;
        }
        sync();
    }

    function addSubItem(blockIndex, subType) {
        readBlock(blockIndex);
        if (subType === 'card') blocks[blockIndex].cards.push({ icon: '⭐', title: '', body: '' });
        if (subType === 'faq')  blocks[blockIndex].items.push({ q: '', a: '' });
        render();
        sync();
    }

    function removeSubItem(blockIndex, subType, subIndex) {
        readBlock(blockIndex);
        if (subType === 'card') blocks[blockIndex].cards.splice(subIndex, 1);
        if (subType === 'faq')  blocks[blockIndex].items.splice(subIndex, 1);
        render();
        sync();
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function blockFormHTML(block, i) {
        const type = block.type;
        const info = BLOCK_TYPES[type];
        let fields = '';

        if (type === 'text') {
            fields = `
                <div class="adm-field">
                    <label class="adm-label">Contenu</label>
                    <textarea name="content" class="adm-textarea" rows="4" oninput="BlockEditor.readBlock(${i})">${esc(block.content)}</textarea>
                </div>
                <div class="adm-field" style="margin-top:10px">
                    <label class="adm-label">Taille</label>
                    <select name="size" class="adm-select" onchange="BlockEditor.readBlock(${i})">
                        <option value="normal"${block.size === 'normal' ? ' selected' : ''}>Normal</option>
                        <option value="large"${block.size === 'large' ? ' selected' : ''}>Grand</option>
                    </select>
                </div>`;
        } else if (type === 'image') {
            fields = `
                <div class="adm-field">
                    <label class="adm-label">URL de l'image</label>
                    <input name="url" class="adm-input" value="${esc(block.url)}" placeholder="https://..." oninput="BlockEditor.readBlock(${i})">
                </div>
                <div class="adm-field" style="margin-top:10px">
                    <label class="adm-label">Texte alternatif</label>
                    <input name="alt" class="adm-input" value="${esc(block.alt)}" oninput="BlockEditor.readBlock(${i})">
                </div>
                <div class="adm-field" style="margin-top:10px">
                    <label class="adm-label">Légende (optionnel)</label>
                    <input name="caption" class="adm-input" value="${esc(block.caption || '')}" oninput="BlockEditor.readBlock(${i})">
                </div>`;
        } else if (type === 'button') {
            fields = `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div class="adm-field">
                        <label class="adm-label">Texte du bouton</label>
                        <input name="label" class="adm-input" value="${esc(block.label)}" oninput="BlockEditor.readBlock(${i})">
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">URL</label>
                        <input name="url" class="adm-input" value="${esc(block.url)}" oninput="BlockEditor.readBlock(${i})">
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">Style</label>
                        <select name="style" class="adm-select" onchange="BlockEditor.readBlock(${i})">
                            ${['primary', 'secondary', 'ghost', 'white'].map(s => `<option value="${s}"${block.style === s ? ' selected' : ''}>${s}</option>`).join('')}
                        </select>
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">Alignement</label>
                        <select name="align" class="adm-select" onchange="BlockEditor.readBlock(${i})">
                            <option value="left"${block.align === 'left' ? ' selected' : ''}>Gauche</option>
                            <option value="center"${block.align === 'center' ? ' selected' : ''}>Centré</option>
                        </select>
                    </div>
                </div>`;
        } else if (type === 'info_card') {
            const cards = (block.cards || []).map((c, ci) => `
                <div class="block-card-item" style="display:grid;grid-template-columns:60px 1fr 1fr auto;gap:8px;align-items:start;padding:10px;background:#f8f4ef;border-radius:8px;margin-bottom:8px">
                    <div class="adm-field">
                        <label class="adm-label">Icône</label>
                        <input name="icon" class="adm-input" value="${esc(c.icon)}" oninput="BlockEditor.readBlock(${i})">
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">Titre</label>
                        <input name="title" class="adm-input" value="${esc(c.title)}" oninput="BlockEditor.readBlock(${i})">
                    </div>
                    <div class="adm-field">
                        <label class="adm-label">Description</label>
                        <input name="body" class="adm-input" value="${esc(c.body)}" oninput="BlockEditor.readBlock(${i})">
                    </div>
                    <button type="button" onclick="BlockEditor.removeSubItem(${i},'card',${ci})"
                            style="margin-top:18px;background:#fee;border:1px solid #fcc;border-radius:6px;padding:6px 10px;cursor:pointer;color:#c0392b;font-size:.8rem">✕</button>
                </div>`).join('');
            fields = `${cards}<button type="button" class="btn-adm btn-adm-ghost btn-adm-sm" onclick="BlockEditor.addSubItem(${i},'card')">+ Ajouter une carte</button>`;
        } else if (type === 'faq') {
            const items = (block.items || []).map((item, fi) => `
                <div class="block-faq-item" style="padding:10px;background:#f8f4ef;border-radius:8px;margin-bottom:8px">
                    <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:start">
                        <div class="adm-field">
                            <label class="adm-label">Question</label>
                            <input name="q" class="adm-input" value="${esc(item.q)}" oninput="BlockEditor.readBlock(${i})">
                        </div>
                        <button type="button" onclick="BlockEditor.removeSubItem(${i},'faq',${fi})"
                                style="margin-top:18px;background:#fee;border:1px solid #fcc;border-radius:6px;padding:6px 10px;cursor:pointer;color:#c0392b;font-size:.8rem">✕</button>
                    </div>
                    <div class="adm-field" style="margin-top:8px">
                        <label class="adm-label">Réponse</label>
                        <textarea name="a" class="adm-textarea" rows="2" oninput="BlockEditor.readBlock(${i})">${esc(item.a)}</textarea>
                    </div>
                </div>`).join('');
            fields = `${items}<button type="button" class="btn-adm btn-adm-ghost btn-adm-sm" onclick="BlockEditor.addSubItem(${i},'faq')">+ Ajouter une question</button>`;
        } else if (type === 'quote') {
            fields = `
                <div class="adm-field">
                    <label class="adm-label">Citation</label>
                    <textarea name="text" class="adm-textarea" rows="3" oninput="BlockEditor.readBlock(${i})">${esc(block.text)}</textarea>
                </div>
                <div class="adm-field" style="margin-top:10px">
                    <label class="adm-label">Auteur (optionnel)</label>
                    <input name="author" class="adm-input" value="${esc(block.author || '')}" oninput="BlockEditor.readBlock(${i})">
                </div>`;
        } else if (type === 'divider') {
            fields = `
                <div class="adm-field">
                    <label class="adm-label">Style</label>
                    <select name="style" class="adm-select" onchange="BlockEditor.readBlock(${i})">
                        <option value="thin"${block.style === 'thin' ? ' selected' : ''}>Fin</option>
                        <option value="gradient"${block.style === 'gradient' ? ' selected' : ''}>Dégradé</option>
                    </select>
                </div>`;
        }

        return `
            <div class="be-block" data-block-index="${i}">
                <div class="be-block-header">
                    <div class="be-block-label">${info.icon} ${info.label}</div>
                    <div class="be-block-controls">
                        <button type="button" class="be-btn be-btn-move" onclick="BlockEditor.move(${i},-1)" title="Monter"${i === 0 ? ' disabled' : ''}>↑</button>
                        <button type="button" class="be-btn be-btn-move" onclick="BlockEditor.move(${i},1)" title="Descendre"${i === blocks.length - 1 ? ' disabled' : ''}>↓</button>
                        <button type="button" class="be-btn be-btn-del" onclick="BlockEditor.remove(${i})" title="Supprimer">✕</button>
                    </div>
                </div>
                <div class="be-block-body">${fields}</div>
            </div>`;
    }

    function render() {
        container.innerHTML = blocks.length === 0
            ? '<div class="be-empty">Aucun bloc. Ajoutez-en un avec le menu ci-dessus.</div>'
            : blocks.map((b, i) => blockFormHTML(b, i)).join('');
    }

    function init(initialJson) {
        try {
            blocks = JSON.parse(initialJson || '[]');
            if (!Array.isArray(blocks)) blocks = [];
        } catch (e) {
            blocks = [];
        }
        render();
    }

    return { init, add, remove, move, readBlock, addSubItem, removeSubItem, sync };
})();

// Initialise with current page data
BlockEditor.init('<?= $blocks_for_js ?>');
</script>

<!-- ── SEO Live Panel JS ──────────────────────────────────── -->
<script>
(function(){
    var _peSeoTimer = null;

    function _peSeoItem(status, label, detail) {
        var c = {green:'#1a7a42', orange:'#8a6020', red:'#c0392b'};
        var b = {green:'rgba(42,157,92,.1)', orange:'rgba(201,150,42,.09)', red:'rgba(234,86,73,.08)'};
        var ico = {green:'✓', orange:'⚠', red:'✕'};
        return '<div style="display:flex;align-items:flex-start;gap:9px;padding:7px 10px;border-radius:7px;background:'+b[status]+'">'
             + '<span style="width:17px;height:17px;border-radius:50%;background:'+c[status]+';color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:900;flex-shrink:0;margin-top:1px">'+ico[status]+'</span>'
             + '<div><div style="font-size:.82rem;font-weight:700;color:#0f1e2d">'+label+'</div>'
             + '<div style="font-size:.74rem;color:#6b7f96;margin-top:1px">'+detail+'</div></div></div>';
    }

    function peSEO() {
        var metaTitle = (document.querySelector('[name="meta_title"]') || {value:''}).value;
        var pageTitle = (document.querySelector('[name="title"]')      || {value:''}).value;
        var metaDesc  = (document.querySelector('[name="meta_description"]') || {value:''}).value;
        var slug      = (document.querySelector('[name="slug"]')       || {value:''}).value;
        var heroImg   = (document.querySelector('[name="hero_image"]') || {value:''}).value;

        var effectiveTitle = metaTitle || pageTitle;
        var items   = [];
        var statuses = [];

        // 1. Titre SEO
        var tl = effectiveTitle.length;
        var ts = tl >= 50 && tl <= 60 ? 'green' : (tl >= 30 && tl <= 70 ? 'orange' : 'red');
        items.push(_peSeoItem(ts, 'Titre SEO', tl + ' caractères (idéal 50–60)' + (metaTitle ? ' — meta_title utilisé' : ' — titre page utilisé')));
        statuses.push(ts);

        // 2. Meta description
        var dl = metaDesc.length;
        var ds = dl >= 120 && dl <= 158 ? 'green' : (dl >= 60 && dl <= 200 ? 'orange' : 'red');
        items.push(_peSeoItem(ds, 'Meta description', dl + ' caractères (idéal 120–158)'));
        statuses.push(ds);

        // 3. Slug
        var ss = (slug.length > 0 && slug.length <= 60) ? 'green' : (slug.length > 60 ? 'orange' : 'red');
        items.push(_peSeoItem(ss, 'URL Slug', slug.length > 0 ? '"'+slug+'" ('+slug.length+' chars)' : 'Slug manquant'));
        statuses.push(ss);

        // 4. Image hero (og:image)
        var hs = heroImg.length > 0 ? 'green' : 'orange';
        items.push(_peSeoItem(hs, 'Image hero (og:image)', heroImg.length > 0 ? 'Présente' : 'Absente — recommandé pour les partages'));
        statuses.push(hs);

        // Score
        var pts = {green:2, orange:1, red:0};
        var total = statuses.reduce(function(s,st){ return s + pts[st]; }, 0);
        var pct   = Math.round(total / (statuses.length * 2) * 100);
        var scoreColor = pct >= 80 ? '#1a7a42' : (pct >= 50 ? '#8a6020' : '#c0392b');
        var scoreBg    = pct >= 80 ? 'rgba(42,157,92,.12)' : (pct >= 50 ? 'rgba(201,150,42,.12)' : 'rgba(234,86,73,.1)');

        var badge = document.getElementById('pe-seo-score');
        if (badge) { badge.textContent = pct+'%'; badge.style.background = scoreBg; badge.style.color = scoreColor; }
        var cl = document.getElementById('pe-seo-checklist');
        if (cl) cl.innerHTML = items.join('');
    }

    function debounceSEO(){ clearTimeout(_peSeoTimer); _peSeoTimer = setTimeout(peSEO, 400); }

    document.addEventListener('DOMContentLoaded', function(){
        peSEO();
        ['meta_title','title','meta_description','slug','hero_image'].forEach(function(n){
            var el = document.querySelector('[name="'+n+'"]');
            if(el) el.addEventListener('input', debounceSEO);
        });
    });

    window.peRunPageSpeed = function() {
        var btn = document.getElementById('pe-ps-btn');
        var res = document.getElementById('pe-ps-result');
        var slug = (document.querySelector('[name="slug"]') || {value:''}).value;
        if (!slug) { alert('Renseigne d\'abord le slug de la page.'); return; }

        var pageUrl = window.location.protocol + '//' + window.location.host + '/page.php?slug=' + encodeURIComponent(slug);
        btn.textContent = '⏳ Analyse…';
        btn.disabled = true;
        res.innerHTML = '<p style="font-size:.78rem;color:#6b7f96;margin:0">Interrogation de Google PageSpeed Insights…</p>';

        fetch('ajax/pagespeed.php?url=' + encodeURIComponent(pageUrl))
            .then(function(r){ return r.json(); })
            .then(function(d){
                btn.textContent = '⚡ Tester PageSpeed';
                btn.disabled = false;
                if (d.error) { res.innerHTML = '<p style="font-size:.78rem;color:#c0392b;margin:0">Erreur : '+d.error+'</p>'; return; }
                res.innerHTML = _peRenderPS(d);
            })
            .catch(function(){
                btn.textContent = '⚡ Tester PageSpeed';
                btn.disabled = false;
                res.innerHTML = '<p style="font-size:.78rem;color:#c0392b;margin:0">Erreur réseau.</p>';
            });
    };

    function _peRenderPS(d) {
        function sc(s){ return s>=90?'#1a7a42':s>=50?'#8a6020':'#c0392b'; }
        function sb(s){ return s>=90?'rgba(42,157,92,.1)':s>=50?'rgba(201,150,42,.1)':'rgba(234,86,73,.08)'; }
        var m = Math.round((d.mobile||0)*100), dsk = Math.round((d.desktop||0)*100);
        var h = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">'
              + '<div style="background:'+sb(m)+';border-radius:8px;padding:10px;text-align:center"><div style="font-size:1.4rem;font-weight:900;color:'+sc(m)+'">'+m+'</div><div style="font-size:.68rem;font-weight:700;color:#6b7f96;margin-top:2px">📱 Mobile</div></div>'
              + '<div style="background:'+sb(dsk)+';border-radius:8px;padding:10px;text-align:center"><div style="font-size:1.4rem;font-weight:900;color:'+sc(dsk)+'">'+dsk+'</div><div style="font-size:.68rem;font-weight:700;color:#6b7f96;margin-top:2px">🖥 Desktop</div></div>'
              + '</div>';
        if (d.cwv && Object.keys(d.cwv).length > 0) {
            var labels = {lcp:'LCP',cls:'CLS',fcp:'FCP',ttfb:'TTFB',inp:'INP'};
            h += '<div style="font-size:.72rem;font-weight:700;color:#6b7f96;margin-bottom:5px">Core Web Vitals</div><div style="display:flex;flex-direction:column;gap:3px">';
            Object.keys(d.cwv).forEach(function(k){
                h += '<div style="display:flex;justify-content:space-between;font-size:.75rem;padding:4px 8px;background:#f8f4ef;border-radius:5px"><span style="color:#3d5166;font-weight:600">'+(labels[k]||k)+'</span><span style="font-weight:700">'+d.cwv[k]+'</span></div>';
            });
            h += '</div>';
        }
        return h;
    }
})();
</script>

<?php require_once '_admin-footer.php'; ?>
