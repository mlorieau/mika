<?php
// ============================================================
// admin/contact.php — Messages du formulaire de contact
// ============================================================
$admin_current    = 'contact';
$admin_page_title = 'Messages de contact';

define('SKIP_MAINTENANCE_CHECK', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

$pdo   = db();
$flash = null;

// ═══════════════════════════════════════════════════════════════
// TRAITEMENT DES ACTIONS POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        // For AJAX mark-as-read calls we still want to fail gracefully
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'err' => 'csrf']);
            exit;
        }
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['item_id'] ?? 0);

        if ($id > 0) {
            try {
                if ($action === 'read') {
                    $pdo->prepare("UPDATE contact_messages SET status='read' WHERE id=:id AND status='new'")
                        ->execute([':id' => $id]);
                    // AJAX response for inline mark-as-read
                    if (!empty($_POST['ajax'])) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => true]);
                        exit;
                    }
                    $flash = ['type' => 'ok', 'msg' => 'Message marqué comme lu.'];

                } elseif ($action === 'archive') {
                    $pdo->prepare("UPDATE contact_messages SET status='archived' WHERE id=:id")
                        ->execute([':id' => $id]);
                    $flash = ['type' => 'ok', 'msg' => 'Message archivé.'];

                } elseif ($action === 'delete') {
                    $pdo->prepare("DELETE FROM contact_messages WHERE id=:id")
                        ->execute([':id' => $id]);
                    $flash = ['type' => 'ok', 'msg' => 'Message supprimé définitivement.'];
                }

            } catch (PDOException $e) {
                error_log('[admin/contact POST] ' . $e->getMessage());
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'err' => 'db']);
                    exit;
                }
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'action.'];
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// STATS GLOBALES
// ═══════════════════════════════════════════════════════════════
$stats = ['new' => 0, 'read' => 0, 'archived' => 0, 'total' => 0];
if ($pdo) {
    try {
        $s = $pdo->query("
            SELECT
                SUM(status='new')      AS cnt_new,
                SUM(status='read')     AS cnt_read,
                SUM(status='archived') AS cnt_archived,
                COUNT(*)               AS cnt_total
            FROM contact_messages
        ")->fetch();
        if ($s) {
            $stats['new']      = (int)$s['cnt_new'];
            $stats['read']     = (int)$s['cnt_read'];
            $stats['archived'] = (int)$s['cnt_archived'];
            $stats['total']    = (int)$s['cnt_total'];
        }
    } catch (PDOException $e) {
        error_log('[admin/contact stats] ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// FILTRES & PAGINATION
// ═══════════════════════════════════════════════════════════════
$filter_status = in_array($_GET['status'] ?? '', ['new', 'read', 'archived'])
                 ? $_GET['status'] : 'all';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['p'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$rows        = [];
$total       = 0;
$total_pages = 1;

if ($pdo) {
    try {
        $where  = ['1=1'];
        $params = [];

        if ($filter_status !== 'all') {
            $where[] = 'status = :status';
            $params[':status'] = $filter_status;
        }
        if ($search !== '') {
            $where[] = '(name LIKE :q OR email LIKE :q OR subject LIKE :q OR message LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $where_sql = implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE {$where_sql}");
        $cnt->execute($params);
        $total       = (int)$cnt->fetchColumn();
        $total_pages = max(1, (int)ceil($total / $per_page));

        // Order: new first, then read, then archived; within group newest first
        $stmt = $pdo->prepare("
            SELECT id, name, email, subject, reason, message, status, created_at
            FROM contact_messages
            WHERE {$where_sql}
            ORDER BY
                CASE status WHEN 'new' THEN 0 WHEN 'read' THEN 1 ELSE 2 END ASC,
                created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[admin/contact] ' . $e->getMessage());
        $flash = ['type' => 'err', 'msg' => 'Erreur de base de données.'];
    }
}

// CSRF token string for JS (used in AJAX mark-as-read)
$csrf_token_str = function_exists('csrf_token') ? csrf_token() : '';

require_once '_admin-header.php';
?>

<style>
/* ── Stats ───────────────────────────────────────────────── */
.ct-stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.ct-stat  { flex:1 1 110px; background:#fff; border-radius:10px; padding:16px 18px;
            box-shadow:0 2px 8px rgba(12,30,46,.06); border:1px solid rgba(18,49,78,.07); text-align:center; }
.ct-stat-val  { font-size:1.7rem; font-weight:900; color:#0c1e2e; line-height:1; }
.ct-stat-lbl  { font-size:.68rem; font-weight:700; color:#6b7f96; text-transform:uppercase;
                letter-spacing:.08em; margin-top:4px; }
.ct-stat-alert .ct-stat-val { color:#c0392b; }

/* ── Filtres ─────────────────────────────────────────────── */
.ct-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
.ct-sel  { padding:8px 12px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; color:#0f1e2d; background:#fff; cursor:pointer; }
.ct-sel:focus { outline:none; border-color:#ea5649; }
.ct-srch { padding:8px 12px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; flex:1; min-width:180px; }
.ct-srch:focus { outline:none; border-color:#ea5649; }

/* ── Tableau ─────────────────────────────────────────────── */
.ct-table    { width:100%; border-collapse:collapse; }
.ct-table th { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
               color:#6b7f96; padding:10px 14px; background:#f8f4ef;
               border-bottom:1px solid #e8e4df; text-align:left; }
.ct-table td { padding:10px 14px; border-bottom:1px solid #f0ece7;
               vertical-align:middle; font-size:.83rem; }
.ct-row-data:hover td { background:#faf8f5; cursor:pointer; }
.ct-row-new  td { border-left:3px solid #ea5649; }

/* ── Badges ─────────────────────────────────────────────── */
.ct-badge { display:inline-flex; align-items:center; padding:2px 9px;
            border-radius:999px; font-size:.67rem; font-weight:700; white-space:nowrap; }
.ct-badge-new      { background:rgba(234,86,73,.12);  color:#c0392b; }
.ct-badge-read     { background:rgba(42,157,92,.1);   color:#1a7a42; }
.ct-badge-archived { background:rgba(107,127,150,.1); color:#4a6073; }

/* ── Boutons d'action ───────────────────────────────────── */
.ct-actions  { display:flex; gap:4px; align-items:center; flex-wrap:wrap; }
.ct-act-btn  { padding:4px 10px; border-radius:6px; font-family:'Inter',sans-serif; font-size:.72rem;
               font-weight:700; cursor:pointer; border:none; white-space:nowrap; transition:opacity .15s; }
.ct-act-btn:hover  { opacity:.82; }
.ct-btn-read     { background:rgba(42,157,92,.1);  color:#1a7a42; }
.ct-btn-archive  { background:rgba(107,127,150,.1); color:#4a6073; }
.ct-btn-del      { background:rgba(234,86,73,.1);   color:#c0392b; }

/* ── Expansion du message complet ──────────────────────── */
.ct-expand-row { background:#faf8f5; }
.ct-expand-row td { padding:0; border-bottom:1px solid #e8e4df; }
.ct-expand-inner { padding:16px 20px 18px; border-top:2px dashed #e8e4df; }
.ct-expand-meta  { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:12px;
                   font-size:.76rem; color:#6b7f96; }
.ct-expand-meta strong { color:#0c1e2e; font-weight:700; }
.ct-expand-body  { white-space:pre-wrap; background:#fff; border:1px solid #e8e4df;
                   border-radius:8px; padding:14px 16px; font-size:.83rem;
                   line-height:1.7; color:#0f1e2d; }

/* ── Pagination ──────────────────────────────────────────── */
.ct-pager { display:flex; gap:6px; justify-content:center; padding:16px; flex-wrap:wrap; }
.ct-pager a, .ct-pager span { padding:5px 12px; border-radius:7px; font-size:.79rem; font-weight:700;
                               text-decoration:none; border:1.5px solid #d0cbc5; color:#3d5166; }
.ct-pager a:hover  { border-color:#ea5649; color:#ea5649; }
.ct-pg-active { background:#ea5649 !important; color:#fff !important; border-color:#ea5649 !important; }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">✉️ Messages de contact</h1>
    <p class="adm-page-sub">Formulaire de contact — lecture et gestion des messages entrants.</p>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if ($stats['new'] > 0): ?>
<div class="adm-flash adm-flash-warn" style="margin-bottom:20px">
  ⚠️ <strong><?= $stats['new'] ?> message<?= $stats['new'] > 1 ? 's' : '' ?>
  non lu<?= $stats['new'] > 1 ? 's' : '' ?></strong> en attente de traitement.
  <?php if ($filter_status !== 'new'): ?>
  <a href="contact.php?status=new" style="color:#8a6020;font-weight:800;margin-left:8px">Voir les nouveaux →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="ct-stats">
  <div class="ct-stat <?= $stats['new'] > 0 ? 'ct-stat-alert' : '' ?>">
    <div class="ct-stat-val"><?= $stats['new'] ?></div>
    <div class="ct-stat-lbl">Nouveaux</div>
  </div>
  <div class="ct-stat">
    <div class="ct-stat-val" style="color:#1a7a42"><?= $stats['read'] ?></div>
    <div class="ct-stat-lbl">Lus</div>
  </div>
  <div class="ct-stat">
    <div class="ct-stat-val" style="color:#4a6073"><?= $stats['archived'] ?></div>
    <div class="ct-stat-lbl">Archivés</div>
  </div>
  <div class="ct-stat">
    <div class="ct-stat-val"><?= $stats['total'] ?></div>
    <div class="ct-stat-lbl">Total</div>
  </div>
</div>

<!-- Filtres -->
<form method="GET" action="contact.php">
  <div class="ct-filters">
    <select name="status" class="ct-sel" onchange="this.form.submit()">
      <option value="all"      <?= $filter_status === 'all'      ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="new"      <?= $filter_status === 'new'      ? 'selected' : '' ?>>🔴 Nouveaux</option>
      <option value="read"     <?= $filter_status === 'read'     ? 'selected' : '' ?>>✅ Lus</option>
      <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>📦 Archivés</option>
    </select>
    <input type="text" name="q" class="ct-srch"
           placeholder="Nom, email, sujet, message…"
           value="<?= e($search) ?>">
    <button type="submit"
            style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;
                   font-family:'Inter',sans-serif;font-weight:700;font-size:.82rem;cursor:pointer">
      Filtrer
    </button>
    <?php if ($filter_status !== 'all' || $search !== ''): ?>
    <a href="contact.php"
       style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">
      ✕ Réinitialiser
    </a>
    <?php endif; ?>
  </div>
</form>

<!-- Tableau -->
<div class="adm-card" style="padding:0;overflow:hidden">

  <?php if (empty($rows)): ?>
  <div class="adm-empty" style="padding:48px 24px">
    <div class="adm-empty-icon">✉️</div>
    <p>Aucun message avec ces filtres.</p>
    <?php if ($filter_status !== 'all' || $search !== ''): ?>
    <a href="contact.php" class="btn-adm btn-adm-ghost" style="margin-top:16px">Réinitialiser les filtres</a>
    <?php endif; ?>
  </div>

  <?php else: ?>

  <div class="adm-table-wrap">
    <table class="ct-table">
      <thead>
        <tr>
          <th>Expéditeur</th>
          <th>Sujet / Raison</th>
          <th>Aperçu du message</th>
          <th>Date</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $rid     = (int)$row['id'];
            $preview = e(mb_strimwidth(preg_replace('/\s+/', ' ', $row['message']), 0, 120, '…'));
            $badge_class = match($row['status']) {
                'new'      => 'ct-badge-new',
                'read'     => 'ct-badge-read',
                'archived' => 'ct-badge-archived',
                default    => '',
            };
            $badge_label = match($row['status']) {
                'new'      => '🔴 Nouveau',
                'read'     => '✅ Lu',
                'archived' => '📦 Archivé',
                default    => e($row['status']),
            };
        ?>
        <!-- Ligne de données -->
        <tr class="ct-row-data <?= $row['status'] === 'new' ? 'ct-row-new' : '' ?>"
            onclick="ctToggle(<?= $rid ?>)"
            title="Cliquer pour lire le message complet">
          <td>
            <div style="font-weight:700;color:#0c1e2e"><?= e($row['name']) ?></div>
            <div style="font-size:.74rem;color:#6b7f96"><?= e($row['email']) ?></div>
          </td>
          <td>
            <div style="font-weight:600;color:#0c1e2e">
              <?= e(mb_strimwidth($row['subject'], 0, 60, '…')) ?>
            </div>
            <?php if ($row['reason']): ?>
            <div style="font-size:.72rem;color:#9aadbc;margin-top:2px"><?= e($row['reason']) ?></div>
            <?php endif; ?>
          </td>
          <td style="max-width:280px;color:#4a6073"><?= $preview ?></td>
          <td style="white-space:nowrap;color:#6b7f96;font-size:.78rem">
            <?= date('d/m/Y', strtotime($row['created_at'])) ?><br>
            <span style="font-size:.69rem"><?= date('H:i', strtotime($row['created_at'])) ?></span>
          </td>
          <td>
            <span class="ct-badge <?= $badge_class ?>" id="badge-<?= $rid ?>"><?= $badge_label ?></span>
          </td>
          <td onclick="event.stopPropagation()">
            <div class="ct-actions" id="actions-<?= $rid ?>">
              <?php if ($row['status'] === 'new'): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="read">
                <input type="hidden" name="item_id" value="<?= $rid ?>">
                <button type="submit" class="ct-act-btn ct-btn-read">✅ Marquer lu</button>
              </form>
              <?php endif; ?>
              <?php if ($row['status'] !== 'archived'): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="archive">
                <input type="hidden" name="item_id" value="<?= $rid ?>">
                <button type="submit" class="ct-act-btn ct-btn-archive">📦 Archiver</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="margin:0"
                    onsubmit="return confirm('Supprimer définitivement ce message ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="item_id" value="<?= $rid ?>">
                <button type="submit" class="ct-act-btn ct-btn-del">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <!-- Ligne expansion (message complet), cachée par défaut -->
        <tr class="ct-expand-row" id="expand-<?= $rid ?>" style="display:none">
          <td colspan="6">
            <div class="ct-expand-inner">
              <div class="ct-expand-meta">
                <span><strong>De :</strong> <?= e($row['name']) ?> &lt;<?= e($row['email']) ?>&gt;</span>
                <span><strong>Sujet :</strong> <?= e($row['subject']) ?></span>
                <?php if ($row['reason']): ?>
                <span><strong>Raison :</strong> <?= e($row['reason']) ?></span>
                <?php endif; ?>
                <span><strong>Reçu le :</strong> <?= date('d/m/Y à H:i', strtotime($row['created_at'])) ?></span>
              </div>
              <div class="ct-expand-body"><?= e($row['message']) ?></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="ct-pager">
    <?php
    $qs_base = array_filter([
        'status' => $filter_status !== 'all' ? $filter_status : null,
        'q'      => $search !== '' ? $search : null,
    ], fn($v) => $v !== null);
    for ($i = 1; $i <= $total_pages; $i++):
        $qs = http_build_query(array_merge($qs_base, ['p' => $i]));
        if ($i === $page): ?>
    <span class="ct-pg-active"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $total_pages || abs($i - $page) <= 2): ?>
    <a href="contact.php?<?= $qs ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?>
    <span>…</span>
        <?php endif;
    endfor; ?>
  </div>
  <?php endif; ?>

  <div style="padding:10px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> message<?= $total > 1 ? 's' : '' ?> · Page <?= $page ?>/<?= max(1,$total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<script>
// CSRF token for AJAX requests — read from first available hidden field on page
var _ctCsrf = (function() {
    var t = document.querySelector('input[name="csrf_token"]');
    return t ? t.value : '';
})();
// Also store the fixed value from PHP for safety
var _ctCsrfPhp = <?= json_encode($csrf_token_str) ?>;
if (!_ctCsrf) _ctCsrf = _ctCsrfPhp;

function ctToggle(id) {
    var expandRow = document.getElementById('expand-' + id);
    if (!expandRow) return;
    var isHidden = expandRow.style.display === 'none' || expandRow.style.display === '';
    expandRow.style.display = isHidden ? 'table-row' : 'none';

    // Auto mark-as-read via AJAX on first expand
    if (isHidden) {
        var badge = document.getElementById('badge-' + id);
        if (badge && badge.classList.contains('ct-badge-new')) {
            var fd = new FormData();
            fd.append('action',     'read');
            fd.append('item_id',    id);
            fd.append('csrf_token', _ctCsrf);
            fd.append('ajax',       '1');
            fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.ok) return;
                    // Update badge
                    badge.className = 'ct-badge ct-badge-read';
                    badge.textContent = '✅ Lu';
                    // Remove border highlight
                    var dataRow = badge.closest('tr');
                    if (dataRow) dataRow.classList.remove('ct-row-new');
                    // Remove "Marquer lu" button from actions cell
                    var actionsDiv = document.getElementById('actions-' + id);
                    if (actionsDiv) {
                        var readBtn = actionsDiv.querySelector('.ct-btn-read');
                        if (readBtn) {
                            var frm = readBtn.closest('form');
                            if (frm) frm.remove();
                        }
                    }
                })
                .catch(function() {});
        }
    }
}
</script>

<?php require_once '_admin-footer.php'; ?>
