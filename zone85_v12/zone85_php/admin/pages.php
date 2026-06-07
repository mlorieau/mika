<?php
// ============================================================
// admin/pages.php — Liste des pages CMS Zone85
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_admin();

$admin_current    = 'pages';
$admin_page_title = 'Pages CMS';

$pdo   = db();
$pages = [];
$msg   = null;

// ── Handle DELETE ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    if ($pdo && $del_id > 0) {
        try {
            $pdo->prepare("DELETE FROM pages WHERE id = :id")->execute([':id' => $del_id]);
            $msg = ['type' => 'ok', 'text' => 'Page supprimée.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'err', 'text' => 'Erreur lors de la suppression.'];
        }
    }
}

// ── Fetch all pages ─────────────────────────────────────────
if ($pdo) {
    try {
        $stmt  = $pdo->query("SELECT id, slug, title, status, updated_at FROM pages ORDER BY updated_at DESC");
        $pages = $stmt->fetchAll();
    } catch (PDOException $e) {
        $msg = ['type' => 'warn', 'text' => 'Table `pages` introuvable. Exécutez la migration SQL d\'abord.'];
    }
}

// ── Derived stats ───────────────────────────────────────────
$total_pages = count($pages);
$published   = count(array_filter($pages, fn($p) => $p['status'] === 'published'));
$drafts      = $total_pages - $published;

require_once '_admin-header.php';
?>

<!-- ── Page header ────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Pages CMS</h1>
    <p class="adm-page-sub">Gérez les pages statiques du site — y compris <strong>index</strong> et <strong>concept</strong> dont les métadonnées sont éditables ici.</p>
  </div>
  <div class="adm-page-actions">
    <a href="page-edit.php" class="btn-adm btn-adm-primary">+ Nouvelle page</a>
  </div>
</div>

<!-- ── Flash message ──────────────────────────────────────── -->
<?php if ($msg): ?>
<div class="adm-flash adm-flash-<?= e($msg['type']) ?>">
  <?php if ($msg['type'] === 'ok'): ?>✓<?php elseif ($msg['type'] === 'err'): ?>✕<?php else: ?>⚠<?php endif; ?>
  <?= e($msg['text']) ?>
</div>
<?php endif; ?>

<!-- ── Stats row ──────────────────────────────────────────── -->
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat-label">Total pages</div>
    <div class="adm-stat-value blue"><?= $total_pages ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Publiées</div>
    <div class="adm-stat-value green"><?= $published ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Brouillons</div>
    <div class="adm-stat-value amber"><?= $drafts ?></div>
  </div>
</div>

<!-- ── Table ──────────────────────────────────────────────── -->
<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($pages)): ?>
  <div class="adm-empty">
    <div class="adm-empty-icon">📄</div>
    <p>Aucune page pour l'instant.</p>
    <a href="page-edit.php" class="btn-adm btn-adm-primary btn-adm-sm" style="margin-top:14px;display:inline-flex">
      + Créer la première page
    </a>
  </div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Slug</th>
          <th>Titre</th>
          <th>Statut</th>
          <th>Modifié le</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $p): ?>
        <tr>
          <td>
            <code style="font-size:.78rem;color:#6b7f96;background:#f8f4ef;padding:2px 7px;border-radius:4px">
              <?= e($p['slug']) ?>
            </code>
          </td>
          <td>
            <a href="page-edit.php?id=<?= (int)$p['id'] ?>"
               style="font-weight:700;color:#0c1e2e;text-decoration:none">
              <?= e($p['title'] ?: '(sans titre)') ?>
            </a>
          </td>
          <td>
            <?php if ($p['status'] === 'published'): ?>
              <span class="adm-badge badge-active">Publié</span>
            <?php else: ?>
              <span class="adm-badge badge-draft">Brouillon</span>
            <?php endif; ?>
          </td>
          <td style="color:#6b7f96;font-size:.82rem;white-space:nowrap">
            <?= e($p['updated_at'] ? date('d/m/Y H:i', strtotime($p['updated_at'])) : '—') ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
              <a href="page-edit.php?id=<?= (int)$p['id'] ?>"
                 class="btn-adm btn-adm-ghost btn-adm-sm">Modifier →</a>
              <?php
              $special = ['index' => 'index.php', 'concept' => 'concept.php'];
              $preview_url = isset($special[$p['slug']])
                  ? e(rtrim(BASE_URL, '/')) . '/' . $special[$p['slug']]
                  : e(rtrim(BASE_URL, '/')) . '/page.php?slug=' . urlencode($p['slug']);
              ?>
              <a href="<?= $preview_url ?>"
                 target="_blank" rel="noopener"
                 class="btn-adm btn-adm-ghost btn-adm-sm">Prévisualiser ↗</a>
              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Supprimer la page « <?= e(addslashes($p['title'] ?: $p['slug'])) ?> » ?\nCette action est irréversible.')">
                <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm">Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '_admin-footer.php'; ?>
