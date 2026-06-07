<?php
// ============================================================
// admin/comments.php — Modération des commentaires Échos
// ============================================================
$admin_current    = 'comments';
$admin_page_title = 'Commentaires';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

define('SKIP_MAINTENANCE_CHECK', true);
require_admin();

$pdo   = db();
$flash = null;

// ── S'assurer que la colonne xp_awarded existe ────────────────
if ($pdo) {
    try {
        $pdo->exec("ALTER TABLE article_comments
            ADD COLUMN IF NOT EXISTS xp_awarded TINYINT(1) NOT NULL DEFAULT 0
            AFTER status");
    } catch (PDOException $e) {}
}

// ── Traitement des actions POST ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['comment_id'] ?? 0);

        // ── Toggle visible/hidden ─────────────────────────────
        if ($action === 'toggle_status' && $id > 0) {
            $row = $pdo->prepare("SELECT status FROM article_comments WHERE id=:id");
            $row->execute([':id' => $id]);
            $cur = $row->fetchColumn();
            $new = $cur === 'visible' ? 'hidden' : 'visible';
            $pdo->prepare("UPDATE article_comments SET status=:s WHERE id=:id")
                ->execute([':s' => $new, ':id' => $id]);
            $flash = ['type' => 'ok', 'msg' => $new === 'hidden' ? 'Commentaire masqué.' : 'Commentaire restauré.'];
        }

        // ── Suppression définitive ────────────────────────────
        elseif ($action === 'delete' && $id > 0) {
            // Retirer les XP si ils avaient été attribués
            $row = $pdo->prepare("SELECT user_id, xp_awarded FROM article_comments WHERE id=:id");
            $row->execute([':id' => $id]);
            $c = $row->fetch();
            if ($c && $c['xp_awarded']) {
                $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - 5) WHERE id=:id")
                    ->execute([':id' => $c['user_id']]);
            }
            $pdo->prepare("DELETE FROM article_comments WHERE id=:id")->execute([':id' => $id]);
            $flash = ['type' => 'ok', 'msg' => 'Commentaire supprimé' . ($c && $c['xp_awarded'] ? ' (−5 XP restitués).' : '.')];
        }

        // ── Attribuer +5 XP manuellement ─────────────────────
        elseif ($action === 'award_xp' && $id > 0) {
            $row = $pdo->prepare("SELECT user_id, xp_awarded FROM article_comments WHERE id=:id");
            $row->execute([':id' => $id]);
            $c = $row->fetch();
            if ($c && !$c['xp_awarded']) {
                $pdo->prepare("UPDATE users SET xp_total = xp_total + 5 WHERE id=:id")
                    ->execute([':id' => $c['user_id']]);
                $pdo->prepare("UPDATE article_comments SET xp_awarded=1 WHERE id=:id")
                    ->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => '+5 XP attribués à l\'auteur.'];
            } else {
                $flash = ['type' => 'warn', 'msg' => 'XP déjà attribués pour ce commentaire.'];
            }
        }

        // ── Retirer les XP ────────────────────────────────────
        elseif ($action === 'revoke_xp' && $id > 0) {
            $row = $pdo->prepare("SELECT user_id, xp_awarded FROM article_comments WHERE id=:id");
            $row->execute([':id' => $id]);
            $c = $row->fetch();
            if ($c && $c['xp_awarded']) {
                $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - 5) WHERE id=:id")
                    ->execute([':id' => $c['user_id']]);
                $pdo->prepare("UPDATE article_comments SET xp_awarded=0 WHERE id=:id")
                    ->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => '−5 XP retirés à l\'auteur.'];
            } else {
                $flash = ['type' => 'warn', 'msg' => 'Aucun XP à retirer pour ce commentaire.'];
            }
        }

        // ── Actions groupées ──────────────────────────────────
        elseif (in_array($action, ['bulk_hide', 'bulk_delete', 'bulk_award_xp']) && !empty($_POST['ids'])) {
            $ids = array_filter(array_map('intval', (array)$_POST['ids']));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                if ($action === 'bulk_hide') {
                    $pdo->prepare("UPDATE article_comments SET status='hidden' WHERE id IN ($ph)")
                        ->execute($ids);
                    $flash = ['type' => 'ok', 'msg' => count($ids) . ' commentaire(s) masqué(s).'];
                } elseif ($action === 'bulk_delete') {
                    // Retirer XP avant suppression
                    $rows = $pdo->prepare("SELECT user_id FROM article_comments WHERE id IN ($ph) AND xp_awarded=1");
                    $rows->execute($ids);
                    foreach ($rows->fetchAll() as $r) {
                        $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - 5) WHERE id=:id")
                            ->execute([':id' => $r['user_id']]);
                    }
                    $pdo->prepare("DELETE FROM article_comments WHERE id IN ($ph)")->execute($ids);
                    $flash = ['type' => 'ok', 'msg' => count($ids) . ' commentaire(s) supprimé(s).'];
                } elseif ($action === 'bulk_award_xp') {
                    $rows = $pdo->prepare("SELECT id, user_id FROM article_comments WHERE id IN ($ph) AND xp_awarded=0");
                    $rows->execute($ids);
                    $done = 0;
                    foreach ($rows->fetchAll() as $r) {
                        $pdo->prepare("UPDATE users SET xp_total = xp_total + 5 WHERE id=:id")
                            ->execute([':id' => $r['user_id']]);
                        $pdo->prepare("UPDATE article_comments SET xp_awarded=1 WHERE id=:id")
                            ->execute([':id' => $r['id']]);
                        $done++;
                    }
                    $flash = ['type' => 'ok', 'msg' => "+5 XP attribués à {$done} auteur(s)."];
                }
            }
        }
    }
}

// ── Filtres ───────────────────────────────────────────────────
$filter_status  = $_GET['status']  ?? 'all';
$filter_xp      = $_GET['xp']      ?? 'all';
$filter_article = (int)($_GET['article_id'] ?? 0);
$search         = trim($_GET['q'] ?? '');
$page           = max(1, (int)($_GET['p'] ?? 1));
$per_page       = 30;
$offset         = ($page - 1) * $per_page;

// ── Requête principale ────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filter_status === 'visible') { $where[] = "ac.status='visible'"; }
if ($filter_status === 'hidden')  { $where[] = "ac.status='hidden'"; }
if ($filter_xp === 'awarded')     { $where[] = "ac.xp_awarded=1"; }
if ($filter_xp === 'missing')     { $where[] = "ac.xp_awarded=0"; }
if ($filter_article > 0)          { $where[] = "ac.article_id=:art"; $params[':art'] = $filter_article; }
if ($search !== '')                { $where[] = "(ac.body LIKE :q OR u.pseudo LIKE :q)"; $params[':q'] = '%' . $search . '%'; }

$where_sql = implode(' AND ', $where);

$total = 0;
$comments = [];
$articles_list = [];

if ($pdo) {
    try {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM article_comments ac
            LEFT JOIN users u ON u.id = ac.user_id WHERE {$where_sql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ac.id, ac.body, ac.created_at, ac.status, ac.xp_awarded,
                   ac.article_id, ac.user_id,
                   u.pseudo, u.xp_total AS user_xp,
                   a.title AS article_title, a.slug AS article_slug
            FROM article_comments ac
            LEFT JOIN users u ON u.id = ac.user_id
            LEFT JOIN articles a ON a.id = ac.article_id
            WHERE {$where_sql}
            ORDER BY ac.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $comments = $stmt->fetchAll();

        // Liste articles pour le filtre
        $articles_list = $pdo->query("
            SELECT a.id, a.title FROM articles a
            INNER JOIN article_comments ac ON ac.article_id = a.id
            GROUP BY a.id ORDER BY a.title ASC
        ")->fetchAll();

    } catch (PDOException $e) {
        $flash = ['type' => 'err', 'msg' => 'Erreur DB : ' . htmlspecialchars($e->getMessage())];
    }
}

// ── Stats rapides ─────────────────────────────────────────────
$stats = ['total' => 0, 'visible' => 0, 'hidden' => 0, 'no_xp' => 0];
if ($pdo) {
    try {
        $s = $pdo->query("SELECT
            COUNT(*) AS total,
            SUM(status='visible') AS visible,
            SUM(status='hidden') AS hidden,
            SUM(xp_awarded=0) AS no_xp
            FROM article_comments")->fetch();
        $stats = $s ?: $stats;
    } catch (PDOException $e) {}
}

$total_pages = (int)ceil($total / $per_page);

require_once '_admin-header.php';
?>

<style>
.cm-stats { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; }
.cm-stat  { flex: 1 1 120px; background: #fff; border-radius: 10px; padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(12,30,46,.06); border: 1px solid rgba(18,49,78,.07); text-align:center; }
.cm-stat-val  { font-size: 1.8rem; font-weight: 900; color: #0c1e2e; line-height: 1; }
.cm-stat-lbl  { font-size: .7rem; font-weight: 700; color: #6b7f96; text-transform: uppercase; letter-spacing: .08em; margin-top: 4px; }
.cm-stat-warn .cm-stat-val { color: #c0392b; }

.cm-filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
.cm-filter-select { padding: 8px 12px; border: 1.5px solid #d0cbc5; border-radius: 7px;
                    font-family: 'Inter', sans-serif; font-size: .82rem; color: #0f1e2d; background: #fff; }
.cm-filter-select:focus { outline: none; border-color: #ea5649; }
.cm-search { padding: 8px 12px; border: 1.5px solid #d0cbc5; border-radius: 7px;
             font-family: 'Inter', sans-serif; font-size: .82rem; min-width: 220px; flex: 1; }
.cm-search:focus { outline: none; border-color: #ea5649; }

.cm-table { width: 100%; border-collapse: collapse; }
.cm-table th { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
               color: #6b7f96; padding: 10px 14px; background: #f8f4ef; border-bottom: 1px solid #e8e4df; text-align: left; }
.cm-table td { padding: 12px 14px; border-bottom: 1px solid #f0ece7; vertical-align: top; font-size: .84rem; }
.cm-table tr:hover td { background: #faf8f5; }
.cm-table tr.cm-hidden td { opacity: .55; }

.cm-body    { max-width: 340px; color: #0f1e2d; line-height: 1.5; }
.cm-article { font-size: .74rem; color: #ea5649; font-weight: 700; text-decoration: none; }
.cm-article:hover { text-decoration: underline; }
.cm-author  { font-weight: 700; color: #0c1e2e; font-size: .82rem; }
.cm-date    { font-size: .72rem; color: #6b7f96; white-space: nowrap; }

.cm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px;
            border-radius: 999px; font-size: .68rem; font-weight: 700; white-space: nowrap; }
.cm-badge-visible { background: rgba(42,157,92,.1);  color: #1a7a42; }
.cm-badge-hidden  { background: rgba(107,127,150,.1); color: #4a6073; }
.cm-badge-xp      { background: rgba(234,86,73,.1);  color: #ea5649; }
.cm-badge-noxp    { background: rgba(201,150,42,.1); color: #8a6020; }

.cm-actions { display: flex; flex-direction: column; gap: 5px; min-width: 140px; }
.cm-btn { padding: 5px 12px; border-radius: 6px; font-family: 'Inter', sans-serif; font-size: .74rem;
          font-weight: 700; cursor: pointer; border: none; text-align: center; white-space: nowrap;
          transition: opacity .15s; }
.cm-btn:hover { opacity: .82; }
.cm-btn-hide  { background: #f0ece7; color: #3d5166; }
.cm-btn-show  { background: rgba(42,157,92,.1); color: #1a7a42; }
.cm-btn-del   { background: rgba(234,86,73,.1); color: #c0392b; }
.cm-btn-xp    { background: rgba(234,86,73,.15); color: #ea5649; }
.cm-btn-noxp  { background: rgba(201,150,42,.1); color: #8a6020; }

.cm-bulk { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
           padding: 12px 14px; background: #f8f4ef; border-radius: 8px; margin-bottom: 16px; }
.cm-bulk-btn { padding: 7px 16px; border-radius: 7px; font-family: 'Inter', sans-serif;
               font-size: .78rem; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.cm-bulk-btn:hover { opacity: .85; }
.cm-bulk-hide   { background: #e8e4df; color: #3d5166; }
.cm-bulk-del    { background: rgba(234,86,73,.15); color: #c0392b; }
.cm-bulk-xp     { background: rgba(234,86,73,.1); color: #ea5649; }

.cm-pager { display: flex; gap: 6px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
.cm-pager a, .cm-pager span { padding: 6px 13px; border-radius: 7px; font-size: .8rem; font-weight: 700;
                               text-decoration: none; border: 1.5px solid #d0cbc5; color: #3d5166; }
.cm-pager a:hover { border-color: #ea5649; color: #ea5649; }
.cm-pager .active { background: #ea5649; color: #fff; border-color: #ea5649; }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">💬 Modération des commentaires</h1>
    <p class="adm-page-sub">Gérez les commentaires des Échos. Attribuez ou retirez les XP manuellement.</p>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
  <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="cm-stats">
  <div class="cm-stat">
    <div class="cm-stat-val"><?= (int)$stats['total'] ?></div>
    <div class="cm-stat-lbl">Total</div>
  </div>
  <div class="cm-stat">
    <div class="cm-stat-val" style="color:#1a7a42"><?= (int)$stats['visible'] ?></div>
    <div class="cm-stat-lbl">Visibles</div>
  </div>
  <div class="cm-stat">
    <div class="cm-stat-val" style="color:#4a6073"><?= (int)$stats['hidden'] ?></div>
    <div class="cm-stat-lbl">Masqués</div>
  </div>
  <div class="cm-stat <?= (int)$stats['no_xp'] > 0 ? 'cm-stat-warn' : '' ?>">
    <div class="cm-stat-val"><?= (int)$stats['no_xp'] ?></div>
    <div class="cm-stat-lbl">Sans XP</div>
  </div>
</div>

<?php if ((int)$stats['no_xp'] > 0): ?>
<div class="adm-flash adm-flash-warn" style="margin-bottom:20px">
  ⚠️ <strong><?= (int)$stats['no_xp'] ?> commentaire(s)</strong> sans XP attribués.
  <a href="?xp=missing" style="color:#8a6020;font-weight:800;margin-left:8px">Voir et corriger →</a>
</div>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" action="">
  <div class="cm-filters">
    <select name="status" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"     <?= $filter_status === 'all'     ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="visible" <?= $filter_status === 'visible' ? 'selected' : '' ?>>✅ Visibles</option>
      <option value="hidden"  <?= $filter_status === 'hidden'  ? 'selected' : '' ?>>👁 Masqués</option>
    </select>

    <select name="xp" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"     <?= $filter_xp === 'all'     ? 'selected' : '' ?>>Tous (XP)</option>
      <option value="awarded" <?= $filter_xp === 'awarded' ? 'selected' : '' ?>>⭐ XP attribués</option>
      <option value="missing" <?= $filter_xp === 'missing' ? 'selected' : '' ?>>⚠️ Sans XP</option>
    </select>

    <?php if (!empty($articles_list)): ?>
    <select name="article_id" class="cm-filter-select" onchange="this.form.submit()">
      <option value="">Tous les articles</option>
      <?php foreach ($articles_list as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $filter_article === (int)$a['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars(mb_strimwidth($a['title'], 0, 50, '…'), ENT_QUOTES, 'UTF-8') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <input type="text" name="q" class="cm-search" placeholder="Rechercher auteur, texte…"
           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;font-family:inherit;font-weight:700;font-size:.82rem;cursor:pointer">Filtrer</button>
    <?php if ($filter_status !== 'all' || $filter_xp !== 'all' || $filter_article || $search): ?>
    <a href="?" style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">✕ Réinitialiser</a>
    <?php endif; ?>
  </div>
</form>

<!-- Tableau avec actions groupées -->
<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($comments)): ?>
  <div style="padding:48px;text-align:center;color:#6b7f96">
    Aucun commentaire trouvé avec ces filtres.
  </div>
  <?php else: ?>

  <form method="POST" id="bulk-form">
    <?= csrf_field() ?>

    <!-- Bulk actions bar -->
    <div class="cm-bulk" id="bulk-bar" style="display:none">
      <span id="bulk-count" style="font-size:.82rem;font-weight:700;color:#3d5166"></span>
      <button type="button" class="cm-bulk-btn cm-bulk-xp"
              onclick="bulkAction('bulk_award_xp')">⭐ Attribuer XP</button>
      <button type="button" class="cm-bulk-btn cm-bulk-hide"
              onclick="bulkAction('bulk_hide')">👁 Masquer</button>
      <button type="button" class="cm-bulk-btn cm-bulk-del"
              onclick="if(confirm('Supprimer ces commentaires définitivement ?')) bulkAction('bulk_delete')">
        🗑 Supprimer
      </button>
    </div>

    <table class="cm-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="check-all" title="Tout sélectionner"
                     onchange="toggleAll(this.checked)"></th>
          <th>Article / Auteur</th>
          <th>Commentaire</th>
          <th>Date</th>
          <th>Statut / XP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($comments as $c): ?>
        <tr class="<?= $c['status'] === 'hidden' ? 'cm-hidden' : '' ?>">
          <td><input type="checkbox" name="ids[]" value="<?= $c['id'] ?>"
                     class="bulk-check" onchange="updateBulk()"></td>
          <td>
            <div class="cm-author"><?= htmlspecialchars($c['pseudo'] ?? '?', ENT_QUOTES, 'UTF-8') ?></div>
            <div style="margin-top:4px">
              <a href="<?= rtrim(BASE_URL, '/') ?>/les-echos-article.php?slug=<?= urlencode($c['article_slug'] ?? '') ?>"
                 target="_blank" class="cm-article" title="Voir l'article">
                <?= htmlspecialchars(mb_strimwidth($c['article_title'] ?? '—', 0, 38, '…'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
            <div style="font-size:.7rem;color:#9aadbc;margin-top:2px">XP user : <?= (int)($c['user_xp'] ?? 0) ?></div>
          </td>
          <td>
            <div class="cm-body">
              <?= htmlspecialchars(mb_strimwidth($c['body'], 0, 200, '…'), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </td>
          <td class="cm-date"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
          <td>
            <span class="cm-badge cm-badge-<?= $c['status'] ?>">
              <?= $c['status'] === 'visible' ? '✅ Visible' : '👁 Masqué' ?>
            </span>
            <br style="margin:4px 0">
            <span class="cm-badge <?= $c['xp_awarded'] ? 'cm-badge-xp' : 'cm-badge-noxp' ?>">
              <?= $c['xp_awarded'] ? '⭐ +5 XP' : '○ Sans XP' ?>
            </span>
          </td>
          <td>
            <div class="cm-actions">
              <!-- Toggle visible/hidden -->
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn <?= $c['status'] === 'visible' ? 'cm-btn-hide' : 'cm-btn-show' ?>">
                  <?= $c['status'] === 'visible' ? '👁 Masquer' : '✅ Restaurer' ?>
                </button>
              </form>

              <!-- XP -->
              <?php if (!$c['xp_awarded']): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="award_xp">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-xp" title="Attribuer +5 XP à l'auteur">
                  ⭐ +5 XP
                </button>
              </form>
              <?php else: ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke_xp">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-noxp" title="Retirer les 5 XP à l'auteur">
                  ↩ −5 XP
                </button>
              </form>
              <?php endif; ?>

              <!-- Supprimer -->
              <form method="POST" style="margin:0"
                    onsubmit="return confirm('Supprimer ce commentaire définitivement ?<?= $c['xp_awarded'] ? ' Les XP seront retirés.' : '' ?>')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-del">🗑 Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="cm-pager" style="padding:20px">
    <?php
    $qs = http_build_query(array_filter([
        'status' => $filter_status !== 'all' ? $filter_status : null,
        'xp'     => $filter_xp !== 'all' ? $filter_xp : null,
        'article_id' => $filter_article ?: null,
        'q'      => $search ?: null,
    ]));
    $qs = $qs ? '&' . $qs : '';
    for ($i = 1; $i <= $total_pages; $i++):
    ?>
    <?php if ($i === $page): ?>
      <span class="active"><?= $i ?></span>
    <?php elseif ($i === 1 || $i === $total_pages || abs($i - $page) <= 2): ?>
      <a href="?p=<?= $i . $qs ?>"><?= $i ?></a>
    <?php elseif (abs($i - $page) === 3): ?>
      <span>…</span>
    <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <div style="padding:14px 18px;font-size:.74rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> commentaire<?= $total > 1 ? 's' : '' ?> au total
    <?= $total > 0 ? '· Page ' . $page . '/' . $total_pages : '' ?>
  </div>

  <?php endif; ?>
</div>

<!-- Guide process modération -->
<div class="adm-card" style="margin-top:20px;background:rgba(14,165,233,.04);border:1px solid rgba(14,165,233,.15)">
  <p class="adm-card-title" style="color:#0369a1">📋 Process de modération</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;font-size:.82rem;color:#0f1e2d;line-height:1.7">
    <div>
      <strong>👁 Masquer</strong><br>
      Commentaire invisible publiquement mais conservé en base. Réversible.
    </div>
    <div>
      <strong>🗑 Supprimer</strong><br>
      Suppression définitive. Les XP sont automatiquement retirés si ils avaient été attribués.
    </div>
    <div>
      <strong>⭐ +5 XP</strong><br>
      À utiliser pour rattraper les commentaires publiés avant le correctif du bug XP (juillet 2026).
    </div>
    <div>
      <strong>↩ −5 XP</strong><br>
      Retirer les XP d'un commentaire jugé abusif ou en doublon, sans supprimer le commentaire.
    </div>
    <div>
      <strong>⚠️ Filtre "Sans XP"</strong><br>
      Affiche tous les commentaires sans XP. Utile pour le rattrapage groupé via la sélection.
    </div>
    <div>
      <strong>☑ Sélection multiple</strong><br>
      Cochez plusieurs lignes pour masquer, supprimer ou attribuer les XP en une seule action.
    </div>
  </div>
</div>

<script>
function toggleAll(checked) {
  document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = checked);
  updateBulk();
}

function updateBulk() {
  const checked = document.querySelectorAll('.bulk-check:checked').length;
  const bar = document.getElementById('bulk-bar');
  document.getElementById('bulk-count').textContent = checked + ' sélectionné(s)';
  bar.style.display = checked > 0 ? 'flex' : 'none';
  document.getElementById('check-all').indeterminate =
    checked > 0 && checked < document.querySelectorAll('.bulk-check').length;
}

function bulkAction(action) {
  const form = document.getElementById('bulk-form');
  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = 'action';
  hidden.value = action;
  form.appendChild(hidden);
  form.submit();
}
</script>

<?php require_once '_admin-footer.php'; ?>
