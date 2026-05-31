<?php
// ============================================================
// ZONE85 — Admin : Randonnées — Liste et gestion
// ============================================================
$admin_current    = 'randos';
$admin_page_title = 'Randonnées';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo   = db();
$flash = '';
$flash_type = 'ok';

// ── Traitement actions POST (publier / dépublier / archiver) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Action rejetée.';
        $flash_type = 'err';
    } else {
        $action   = $_POST['action']   ?? '';
        $rando_id = (int)($_POST['rando_id'] ?? 0);

        if ($rando_id > 0 && in_array($action, ['publish', 'unpublish', 'archive'], true)) {
            try {
                if ($action === 'publish') {
                    $new_status = 'published';
                    $pub_at_sql = ', published_at = COALESCE(published_at, NOW())';
                } elseif ($action === 'unpublish') {
                    $new_status = 'draft';
                    $pub_at_sql = '';
                } else {
                    $new_status = 'archived';
                    $pub_at_sql = '';
                }
                $s = $pdo->prepare('UPDATE randos SET status=:st' . $pub_at_sql . ' WHERE id=:id');
                $s->execute([':st' => $new_status, ':id' => $rando_id]);
                $flash = 'Rando mise à jour (' . $new_status . ').';
                $flash_type = 'ok';
            } catch (PDOException $e) {
                $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $flash_type = 'err';
            }
        }
    }
}

// ── Filtres GET ────────────────────────────────────────────────
$f_secteur    = $_GET['secteur']    ?? '';
$f_difficulte = $_GET['difficulte'] ?? '';
$f_statut     = $_GET['statut']     ?? '';

$valid_secteurs    = ['bocage', 'littoral', 'marais', 'plaine'];
$valid_difficultes = ['facile', 'moyen', 'difficile', 'expert'];
$valid_statuts     = ['draft', 'published', 'archived'];

if (!in_array($f_secteur,    $valid_secteurs,    true)) $f_secteur    = '';
if (!in_array($f_difficulte, $valid_difficultes, true)) $f_difficulte = '';
if (!in_array($f_statut,     $valid_statuts,     true)) $f_statut     = '';

// ── Stats ──────────────────────────────────────────────────────
$stats = [
    'total'     => 0,
    'published' => 0,
    'draft'     => 0,
    'bocage'    => 0,
    'littoral'  => 0,
    'marais'    => 0,
    'plaine'    => 0,
];

if ($pdo) {
    try {
        $rs = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(status='published') AS cnt_pub,
                SUM(status='draft')     AS cnt_draft,
                SUM(secteur='bocage')   AS cnt_bocage,
                SUM(secteur='littoral') AS cnt_littoral,
                SUM(secteur='marais')   AS cnt_marais,
                SUM(secteur='plaine')   AS cnt_plaine
            FROM randos
        ");
        $row = $rs->fetch();
        if ($row) {
            $stats['total']     = (int)$row['total'];
            $stats['published'] = (int)$row['cnt_pub'];
            $stats['draft']     = (int)$row['cnt_draft'];
            $stats['bocage']    = (int)$row['cnt_bocage'];
            $stats['littoral']  = (int)$row['cnt_littoral'];
            $stats['marais']    = (int)$row['cnt_marais'];
            $stats['plaine']    = (int)$row['cnt_plaine'];
        }
    } catch (PDOException $e) { /* silencieux */ }
}

// ── Liste des randos (avec filtres) ───────────────────────────
$randos = [];
if ($pdo) {
    try {
        $where  = [];
        $params = [];
        if ($f_secteur !== '') {
            $where[] = 'r.secteur = :secteur';
            $params[':secteur'] = $f_secteur;
        }
        if ($f_difficulte !== '') {
            $where[] = 'r.difficulty = :diff';
            $params[':diff'] = $f_difficulte;
        }
        if ($f_statut !== '') {
            $where[] = 'r.status = :statut';
            $params[':statut'] = $f_statut;
        }
        $sql = 'SELECT r.id, r.title, r.slug, r.secteur, r.difficulty,
                       r.distance_km, r.status, r.published_at, r.commune,
                       r.created_at
                FROM randos r'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY r.created_at DESC';
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $randos = $s->fetchAll();
    } catch (PDOException $e) { /* silencieux */ }
}

// ── Helpers affichage ──────────────────────────────────────────
function rando_secteur_badge(string $secteur): string {
    $map = [
        'bocage'   => ['label' => 'Bocage',   'color' => '#2a9d5c', 'bg' => 'rgba(42,157,92,.12)'],
        'littoral' => ['label' => 'Littoral', 'color' => '#12314e', 'bg' => 'rgba(18,49,78,.12)'],
        'marais'   => ['label' => 'Marais',   'color' => '#8a6020', 'bg' => 'rgba(138,96,32,.12)'],
        'plaine'   => ['label' => 'Plaine',   'color' => '#6b7f96', 'bg' => 'rgba(107,127,150,.12)'],
    ];
    $c = $map[$secteur] ?? ['label' => ucfirst($secteur), 'color' => '#6b7f96', 'bg' => 'rgba(107,127,150,.12)'];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:.68rem;font-weight:700;'
         . 'text-transform:uppercase;letter-spacing:.06em;background:' . $c['bg'] . ';color:' . $c['color'] . '">'
         . htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') . '</span>';
}

function rando_difficulty_stars(string $diff): string {
    $levels = ['facile' => 1, 'moyen' => 2, 'difficile' => 3, 'expert' => 4];
    $n      = $levels[$diff] ?? 1;
    $stars  = str_repeat('★', $n) . str_repeat('☆', 4 - $n);
    $colors = ['facile' => '#2a9d5c', 'moyen' => '#C9962A', 'difficile' => '#ea5649', 'expert' => '#c0392b'];
    $col    = $colors[$diff] ?? '#6b7f96';
    return '<span style="color:' . $col . ';font-size:.9rem;letter-spacing:.05em" title="' . ucfirst($diff) . '">'
         . $stars . '</span>';
}

function rando_status_badge(string $status): string {
    $map = [
        'published' => ['label' => 'Publié',   'class' => 'badge-active'],
        'draft'     => ['label' => 'Brouillon', 'class' => 'badge-draft'],
        'archived'  => ['label' => 'Archivé',  'class' => 'badge-archived'],
    ];
    $c = $map[$status] ?? ['label' => $status, 'class' => 'badge-archived'];
    return '<span class="adm-badge ' . $c['class'] . '">' . $c['label'] . '</span>';
}

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">&#x1F97E; Randonnées</h1>
    <p class="adm-page-sub">Gestion du catalogue de randonnées Zone85</p>
  </div>
  <div class="adm-page-actions">
    <a href="rando-edit.php" class="btn-adm btn-adm-primary">&#x2B; Nouvelle rando</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= $flash_type ?>">
    <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!$pdo): ?>
  <div class="adm-flash adm-flash-err">
    Base de donn&eacute;es non disponible. Les donn&eacute;es ne peuvent pas &ecirc;tre charg&eacute;es.
  </div>
<?php endif; ?>

<!-- ── Stats ──────────────────────────────────────────────── -->
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat-label">Total</div>
    <div class="adm-stat-value"><?= $stats['total'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Publi&eacute;es</div>
    <div class="adm-stat-value green"><?= $stats['published'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Brouillons</div>
    <div class="adm-stat-value amber"><?= $stats['draft'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Bocage</div>
    <div class="adm-stat-value" style="color:#2a9d5c"><?= $stats['bocage'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Littoral</div>
    <div class="adm-stat-value blue"><?= $stats['littoral'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Marais</div>
    <div class="adm-stat-value" style="color:#8a6020"><?= $stats['marais'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Plaine</div>
    <div class="adm-stat-value" style="color:#6b7f96"><?= $stats['plaine'] ?></div>
  </div>
</div>

<!-- ── Filtres ────────────────────────────────────────────── -->
<form method="get" action="randos.php" class="adm-filters">
  <select name="secteur">
    <option value="">Tous les secteurs</option>
    <option value="bocage"   <?= $f_secteur === 'bocage'   ? 'selected' : '' ?>>Bocage</option>
    <option value="littoral" <?= $f_secteur === 'littoral' ? 'selected' : '' ?>>Littoral</option>
    <option value="marais"   <?= $f_secteur === 'marais'   ? 'selected' : '' ?>>Marais</option>
    <option value="plaine"   <?= $f_secteur === 'plaine'   ? 'selected' : '' ?>>Plaine</option>
  </select>
  <select name="difficulte">
    <option value="">Toutes les difficult&eacute;s</option>
    <option value="facile"    <?= $f_difficulte === 'facile'    ? 'selected' : '' ?>>&#x2605; Facile</option>
    <option value="moyen"     <?= $f_difficulte === 'moyen'     ? 'selected' : '' ?>>&#x2605;&#x2605; Moyen</option>
    <option value="difficile" <?= $f_difficulte === 'difficile' ? 'selected' : '' ?>>&#x2605;&#x2605;&#x2605; Difficile</option>
    <option value="expert"    <?= $f_difficulte === 'expert'    ? 'selected' : '' ?>>&#x2605;&#x2605;&#x2605;&#x2605; Expert</option>
  </select>
  <select name="statut">
    <option value="">Tous les statuts</option>
    <option value="published" <?= $f_statut === 'published' ? 'selected' : '' ?>>Publi&eacute;</option>
    <option value="draft"     <?= $f_statut === 'draft'     ? 'selected' : '' ?>>Brouillon</option>
    <option value="archived"  <?= $f_statut === 'archived'  ? 'selected' : '' ?>>Archiv&eacute;</option>
  </select>
  <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm">Filtrer</button>
  <?php if ($f_secteur !== '' || $f_difficulte !== '' || $f_statut !== ''): ?>
    <a href="randos.php" class="btn-adm btn-adm-ghost btn-adm-sm">&#x2715; R&eacute;initialiser</a>
  <?php endif; ?>
</form>

<!-- ── Tableau ────────────────────────────────────────────── -->
<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($randos)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">&#x1F97E;</div>
      <p>Aucune randonn&eacute;e trouv&eacute;e.<br>
        <a href="rando-edit.php" style="color:#ea5649;font-weight:700">Cr&eacute;er la premi&egrave;re rando</a>
      </p>
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Secteur</th>
            <th>Difficult&eacute;</th>
            <th>Distance</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($randos as $r): ?>
            <tr>
              <td>
                <div style="font-weight:700;color:#0c1e2e">
                  <?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if (!empty($r['commune'])): ?>
                  <div style="font-size:.72rem;color:#6b7f96;margin-top:2px">
                    &#x1F4CD; <?= htmlspecialchars($r['commune'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= rando_secteur_badge($r['secteur']) ?></td>
              <td><?= rando_difficulty_stars($r['difficulty']) ?></td>
              <td style="white-space:nowrap">
                <?php if ($r['distance_km'] !== null): ?>
                  <span style="font-weight:700"><?= number_format((float)$r['distance_km'], 1, ',', '') ?></span>
                  <span style="font-size:.75rem;color:#6b7f96"> km</span>
                <?php else: ?>
                  <span style="color:#6b7f96">—</span>
                <?php endif; ?>
              </td>
              <td><?= rando_status_badge($r['status']) ?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                  <a href="rando-edit.php?id=<?= (int)$r['id'] ?>"
                     class="btn-adm btn-adm-ghost btn-adm-sm">
                    &#x270F;&#xFE0F; &Eacute;diter
                  </a>

                  <form method="post" action="randos.php" style="display:inline"
                        onsubmit="return confirm('Confirmer cette action ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="rando_id" value="<?= (int)$r['id'] ?>">
                    <?php if ($r['status'] !== 'published'): ?>
                      <input type="hidden" name="action" value="publish">
                      <button type="submit" class="btn-adm btn-adm-success btn-adm-sm">
                        &#x2713; Publier
                      </button>
                    <?php else: ?>
                      <input type="hidden" name="action" value="unpublish">
                      <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm">
                        &#x23F8; D&eacute;publier
                      </button>
                    <?php endif; ?>
                  </form>

                  <?php if ($r['status'] !== 'archived'): ?>
                    <form method="post" action="randos.php" style="display:inline"
                          onsubmit="return confirm('Archiver cette rando ?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="rando_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="action" value="archive">
                      <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm"
                              style="color:#6b7f96">
                        &#x1F4E6; Archiver
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:12px 20px;font-size:.75rem;color:#6b7f96;border-top:1px solid #f0ece7">
      <?= count($randos) ?> rando<?= count($randos) > 1 ? 's' : '' ?> affich&eacute;<?= count($randos) > 1 ? 'es' : 'e' ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
