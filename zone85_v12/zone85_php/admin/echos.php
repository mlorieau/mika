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

$pdo     = db();
$flash   = '';
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
            // Soft delete : repasser en draft
            $s = $pdo->prepare('UPDATE articles SET status=\'draft\' WHERE id=:id');
            $s->execute([':id' => $id]);
            $flash = 'Article repassé en brouillon.';
        } elseif ($action === 'toggle_status') {
            $new_status = $_POST['new_status'] ?? '';
            if (in_array($new_status, ['draft', 'published'], true)) {
                $published_at_sql = ($new_status === 'published')
                    ? ', published_at = COALESCE(published_at, NOW())'
                    : '';
                $s = $pdo->prepare(
                    'UPDATE articles SET status=:status' . $published_at_sql . ' WHERE id=:id'
                );
                $s->execute([':status' => $new_status, ':id' => $id]);
                $flash = $new_status === 'published' ? 'Article publié.' : 'Article dépublié (brouillon).';
            }
        }
    }
}

// ── Filtres ────────────────────────────────────────────────────
$filter_rubrique = $_GET['rubrique'] ?? '';
$filter_status   = $_GET['status']   ?? '';
$rubriques_valides = ['ovnis', 'deux-minutes', 'chez-nous', 'chemins', 'communaute', 'archives'];
$statuts_valides   = ['draft', 'published'];
if (!in_array($filter_rubrique, $rubriques_valides, true)) $filter_rubrique = '';
if (!in_array($filter_status, $statuts_valides, true))     $filter_status   = '';

// ── Chargement articles ────────────────────────────────────────
$articles = [];
if ($pdo) {
    try {
        $where  = [];
        $params = [];
        if ($filter_rubrique) { $where[] = 'rubrique = :r'; $params[':r'] = $filter_rubrique; }
        if ($filter_status)   { $where[] = 'status = :s';   $params[':s'] = $filter_status; }
        $sql = 'SELECT id, title, rubrique, status, author_name, published_at, created_at
                FROM articles'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();
    } catch (PDOException $e) {
        $flash = 'Erreur de base de données : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $flash_type = 'err';
    }
}

// ── Labels & couleurs rubriques ────────────────────────────────
$rubrique_labels = [
    'ovnis'        => 'OVNIS Zone85',
    'deux-minutes' => "T'as deux minutes\xc2\xa0?",
    'chez-nous'    => 'Chez nous on ne dit pas\xe2\x80\xa6',
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

// ── Ajout du lien Échos dans le topnav via admin_scripts ──────
$admin_scripts = '<script>
document.addEventListener("DOMContentLoaded", function () {
    var nav = document.querySelector(".adm-topnav");
    if (nav) {
        var link = document.createElement("a");
        link.href = "echos.php";
        link.textContent = "✏️ \xc9chos";
        if ("echos" === "echos") link.className = "active";
        nav.appendChild(link);
    }
});
</script>';

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">&#x270F;&#xFE0F; Les &Eacute;chos</h1>
    <p class="adm-page-sub">Gestion des articles du magazine communautaire.</p>
  </div>
  <div class="adm-page-actions">
    <a href="echo-edit.php" class="btn-adm btn-adm-primary">&#x2B; Nouvel article</a>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash_type === 'err' ? 'err' : 'ok' ?>">
  <span><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></span>
</div>
<?php endif; ?>

<!-- ── Filtres ─────────────────────────────────────────────── -->
<form method="get" class="adm-filters">
  <select name="rubrique">
    <option value="">Toutes les rubriques</option>
    <?php foreach ($rubrique_labels as $k => $label): ?>
      <option value="<?= $k ?>" <?= $filter_rubrique === $k ? 'selected' : '' ?>>
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">Tous les statuts</option>
    <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Publi&eacute;</option>
    <option value="draft"     <?= $filter_status === 'draft'     ? 'selected' : '' ?>>Brouillon</option>
  </select>
  <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm">Filtrer</button>
  <?php if ($filter_rubrique || $filter_status): ?>
    <a href="echos.php" class="btn-adm btn-adm-ghost btn-adm-sm">R&eacute;initialiser</a>
  <?php endif; ?>
</form>

<!-- ── Tableau ─────────────────────────────────────────────── -->
<div class="adm-card">
  <?php if (empty($articles)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">&#x1F4C4;</div>
      <p>Aucun article trouv&eacute;. <a href="echo-edit.php">Cr&eacute;er le premier &eacute;cho</a></p>
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
          <?php foreach ($articles as $art): ?>
            <?php
              $rub    = $art['rubrique'];
              $color  = $rubrique_colors[$rub] ?? '#6b7f96';
              $label  = $rubrique_labels[$rub]  ?? $rub;
              $is_pub = $art['status'] === 'published';
              $date_val = $is_pub && $art['published_at']
                            ? date('d/m/Y', strtotime($art['published_at']))
                            : date('d/m/Y', strtotime($art['created_at']));
            ?>
            <tr>
              <td>
                <span style="
                  display:inline-block;
                  padding:3px 10px;
                  border-radius:999px;
                  font-size:.68rem;
                  font-weight:800;
                  text-transform:uppercase;
                  letter-spacing:.06em;
                  color:#fff;
                  background:<?= $color ?>;
                ">
                  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td style="max-width:300px">
                <a href="echo-edit.php?id=<?= $art['id'] ?>" style="font-weight:700;color:#0c1e2e;text-decoration:none">
                  <?= htmlspecialchars($art['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td><?= htmlspecialchars($art['author_name'] ?? 'Équipe Zone85', ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <?php if ($is_pub): ?>
                  <span class="adm-badge badge-active">Publi&eacute;</span>
                <?php else: ?>
                  <span class="adm-badge badge-draft">Brouillon</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap"><?= $date_val ?></td>
              <td style="white-space:nowrap">
                <a href="echo-edit.php?id=<?= $art['id'] ?>" class="btn-adm btn-adm-ghost btn-adm-sm">
                  &#x270F; &Eacute;diter
                </a>
                <!-- Toggle publier/dépublier -->
                <form method="post" style="display:inline-block;margin-left:4px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action"     value="toggle_status">
                  <input type="hidden" name="id"         value="<?= $art['id'] ?>">
                  <input type="hidden" name="new_status" value="<?= $is_pub ? 'draft' : 'published' ?>">
                  <button type="submit" class="btn-adm btn-adm-sm <?= $is_pub ? 'btn-adm-ghost' : 'btn-adm-success' ?>">
                    <?= $is_pub ? '&#x23F8; D&eacute;pub.' : '&#x25B6; Publier' ?>
                  </button>
                </form>
                <!-- Soft delete (repasse en draft) -->
                <?php if ($is_pub): ?>
                  <form method="post" style="display:inline-block;margin-left:4px"
                        onsubmit="return confirm('Repasser cet article en brouillon ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_article">
                    <input type="hidden" name="id"     value="<?= $art['id'] ?>">
                    <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm">
                      &#x1F5D1;
                    </button>
                  </form>
                <?php endif; ?>
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
