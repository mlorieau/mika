<?php
// ============================================================
// ZONE85 — Admin : Les Échos — Liste des articles
// ============================================================
$admin_current    = 'echos';
$admin_page_title = 'Les Échos — Articles';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo        = db();
$flash      = '';
$flash_type = 'ok';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action']     ?? '';
    $id     = (int)($_POST['id']   ?? 0);

    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Action annulée.';
        $flash_type = 'err';
    } elseif ($pdo && $id > 0) {
        if ($action === 'delete_article') {
            // Suppression définitive
            try {
                $pdo->prepare('DELETE FROM articles WHERE id=:id')->execute([':id' => $id]);
                $flash = 'Article supprimé définitivement.';
            } catch (PDOException $e) {
                $flash = 'Erreur lors de la suppression.';
                $flash_type = 'err';
            }
        } elseif ($action === 'toggle_status') {
            $new_status = $_POST['new_status'] ?? '';
            if (in_array($new_status, ['draft', 'published'], true)) {
                $pub_sql = ($new_status === 'published')
                    ? ', published_at = COALESCE(published_at, NOW())' : '';
                try {
                    $pdo->prepare('UPDATE articles SET status=:s' . $pub_sql . ' WHERE id=:id')
                        ->execute([':s' => $new_status, ':id' => $id]);
                    $flash = $new_status === 'published'
                        ? 'Article publié.' : 'Article repassé en brouillon.';
                } catch (PDOException $e) {
                    $flash = 'Erreur lors de la mise à jour.';
                    $flash_type = 'err';
                }
            }
        }
    }
}

// ── Rubriques définitives ──────────────────────────────────────
$rubrique_labels = [
    'les-invisibles' => 'Les Invisibles',
    'deux-minutes'   => "T'as deux minutes\xc2\xa0?",
    'les-ovnis'      => 'Les OVNIS de la Zone85',
    'actualite'      => 'Actualité',
    'chemins'        => 'Sur les Chemins',
    'evenements'     => 'Événements',
];
$rubrique_colors = [
    'les-invisibles' => '#ea5649',
    'deux-minutes'   => '#12314e',
    'les-ovnis'      => '#1a7adc',
    'actualite'      => '#2a9d5c',
    'chemins'        => '#b8831a',
    'evenements'     => '#9b59b6',
];

// ── Filtres ────────────────────────────────────────────────────
$filter_rubrique = $_GET['rubrique'] ?? '';
$filter_status   = $_GET['status']   ?? '';
if (!array_key_exists($filter_rubrique, $rubrique_labels)) $filter_rubrique = '';
if (!in_array($filter_status, ['draft', 'published'], true)) $filter_status  = '';

// ── Chargement articles ────────────────────────────────────────
$articles = [];
if ($pdo) {
    try {
        $where  = [];
        $params = [];
        if ($filter_rubrique) { $where[] = 'rubrique = :r'; $params[':r'] = $filter_rubrique; }
        if ($filter_status)   { $where[] = 'status = :s';   $params[':s'] = $filter_status;   }
        $sql  = 'SELECT id, title, rubrique, status, author_name, published_at, created_at FROM articles';
        $sql .= $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();
    } catch (PDOException $e) {
        $flash = 'Erreur de base de données : ' . e($e->getMessage());
        $flash_type = 'err';
    }
}

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">✏️ Les Échos</h1>
    <p class="adm-page-sub">Magazine communautaire — gestion des articles.</p>
  </div>
  <div class="adm-page-actions">
    <a href="echo-edit.php" class="btn-adm btn-adm-primary">+ Nouvel article</a>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= e($flash_type) ?>">
  <?= e($flash) ?>
</div>
<?php endif; ?>

<!-- ── Filtres ─────────────────────────────────────────────── -->
<form method="get" class="adm-filters">
  <select name="rubrique">
    <option value="">Toutes les rubriques</option>
    <?php foreach ($rubrique_labels as $k => $label): ?>
      <option value="<?= e($k) ?>" <?= $filter_rubrique === $k ? 'selected' : '' ?>>
        <?= e($label) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">Tous les statuts</option>
    <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Publié</option>
    <option value="draft"     <?= $filter_status === 'draft'     ? 'selected' : '' ?>>Brouillon</option>
  </select>
  <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm">Filtrer</button>
  <?php if ($filter_rubrique || $filter_status): ?>
    <a href="echos.php" class="btn-adm btn-adm-ghost btn-adm-sm">Réinitialiser</a>
  <?php endif; ?>
</form>

<!-- ── Tableau ─────────────────────────────────────────────── -->
<div class="adm-card">
  <?php if (empty($articles)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">📄</div>
      <p>Aucun article trouvé. <a href="echo-edit.php">Créer le premier écho</a></p>
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Rubrique</th>
            <th>Titre</th>
            <th>Auteur</th>
            <th>Statut</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($articles as $art):
            $rub    = $art['rubrique'];
            $color  = $rubrique_colors[$rub]  ?? '#6b7f96';
            $label  = $rubrique_labels[$rub]  ?? $rub;
            $is_pub = $art['status'] === 'published';
            $date_val = $is_pub && $art['published_at']
                ? date('d/m/Y', strtotime($art['published_at']))
                : date('d/m/Y', strtotime($art['created_at']));
          ?>
          <tr>
            <td>
              <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#fff;background:<?= e($color) ?>">
                <?= e($label) ?>
              </span>
            </td>
            <td style="max-width:300px">
              <a href="echo-edit.php?id=<?= (int)$art['id'] ?>" style="font-weight:700;color:#0c1e2e;text-decoration:none">
                <?= e($art['title']) ?>
              </a>
            </td>
            <td><?= e($art['author_name'] ?? 'Équipe Zone85') ?></td>
            <td>
              <?php if ($is_pub): ?>
                <span class="adm-badge badge-active">Publié</span>
              <?php else: ?>
                <span class="adm-badge badge-draft">Brouillon</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;color:#6b7f96;font-size:.82rem"><?= e($date_val) ?></td>
            <td style="white-space:nowrap">
              <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <a href="echo-edit.php?id=<?= (int)$art['id'] ?>" class="btn-adm btn-adm-ghost btn-adm-sm">
                  ✏ Éditer
                </a>
                <!-- Toggle publier/dépublier -->
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action"     value="toggle_status">
                  <input type="hidden" name="id"         value="<?= (int)$art['id'] ?>">
                  <input type="hidden" name="new_status" value="<?= $is_pub ? 'draft' : 'published' ?>">
                  <button type="submit" class="btn-adm btn-adm-sm <?= $is_pub ? 'btn-adm-ghost' : 'btn-adm-success' ?>">
                    <?= $is_pub ? '⏸ Dépub.' : '▶ Publier' ?>
                  </button>
                </form>
                <!-- Suppression définitive -->
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Supprimer définitivement « <?= e(addslashes($art['title'])) ?> » ?\nCette action est irréversible.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_article">
                  <input type="hidden" name="id"     value="<?= (int)$art['id'] ?>">
                  <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm">🗑 Supprimer</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:.76rem;color:#6b7f96;margin:14px 0 0;text-align:right">
      <?= count($articles) ?> article<?= count($articles) > 1 ? 's' : '' ?>
    </p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
