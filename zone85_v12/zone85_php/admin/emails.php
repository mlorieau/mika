<?php
// ============================================================
// admin/emails.php — File d'attente email & monitoring
// ============================================================
$admin_current    = 'emails';
$admin_page_title = 'File d\'emails';

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
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['item_id'] ?? 0);

        try {
            if ($action === 'retry' && $id > 0) {
                $pdo->prepare("
                    UPDATE email_queue
                    SET status='pending', attempts=0, last_error=NULL, scheduled_at=NOW()
                    WHERE id=:id AND status='failed'
                ")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Email remis en file d\'attente.'];

            } elseif ($action === 'delete' && $id > 0) {
                $pdo->prepare("DELETE FROM email_queue WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Email supprimé de la file.'];

            } elseif ($action === 'delete_all_failed') {
                $deleted = $pdo->exec("DELETE FROM email_queue WHERE status='failed'");
                $flash   = ['type' => 'ok', 'msg' => ($deleted ?: 0) . ' email(s) échoué(s) supprimé(s).'];

            } elseif ($action === 'retry_all_failed') {
                $retried = $pdo->exec("
                    UPDATE email_queue
                    SET status='pending', attempts=0, last_error=NULL, scheduled_at=NOW()
                    WHERE status='failed'
                ");
                $flash = ['type' => 'ok', 'msg' => ($retried ?: 0) . ' email(s) remis en file d\'attente.'];
            }

        } catch (PDOException $e) {
            error_log('[admin/emails POST] ' . $e->getMessage());
            $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'action.'];
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// STATS GLOBALES
// ═══════════════════════════════════════════════════════════════
$stats = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0];
if ($pdo) {
    try {
        $s = $pdo->query("
            SELECT
                SUM(status='pending') AS cnt_pending,
                SUM(status='sending') AS cnt_sending,
                SUM(status='sent')    AS cnt_sent,
                SUM(status='failed')  AS cnt_failed,
                COUNT(*)              AS cnt_total
            FROM email_queue
        ")->fetch();
        if ($s) {
            $stats['pending'] = (int)$s['cnt_pending'];
            $stats['sending'] = (int)$s['cnt_sending'];
            $stats['sent']    = (int)$s['cnt_sent'];
            $stats['failed']  = (int)$s['cnt_failed'];
            $stats['total']   = (int)$s['cnt_total'];
        }
    } catch (PDOException $e) {
        error_log('[admin/emails stats] ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// TEMPLATES (pour le filtre dropdown)
// ═══════════════════════════════════════════════════════════════
$templates = [];
if ($pdo) {
    try {
        $templates = $pdo->query("SELECT slug, description FROM email_templates ORDER BY slug ASC")
                         ->fetchAll();
    } catch (PDOException $e) {}
}

// ═══════════════════════════════════════════════════════════════
// FILTRES & PAGINATION
// ═══════════════════════════════════════════════════════════════
$filter_status   = in_array($_GET['status'] ?? '', ['pending', 'sending', 'sent', 'failed'])
                   ? $_GET['status'] : 'all';
$filter_template = trim($_GET['template'] ?? '');
$search          = trim($_GET['q'] ?? '');
$page            = max(1, (int)($_GET['p'] ?? 1));
$per_page        = 30;
$offset          = ($page - 1) * $per_page;

$rows        = [];
$total       = 0;
$total_pages = 1;

if ($pdo) {
    try {
        $where  = ['1=1'];
        $params = [];

        if ($filter_status !== 'all') {
            $where[] = 'eq.status = :status';
            $params[':status'] = $filter_status;
        }
        if ($filter_template !== '') {
            $where[] = 'eq.template_slug = :tpl';
            $params[':tpl'] = $filter_template;
        }
        if ($search !== '') {
            $where[] = '(eq.to_email LIKE :q OR eq.to_name LIKE :q OR eq.subject LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $where_sql = implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM email_queue eq WHERE {$where_sql}");
        $cnt->execute($params);
        $total       = (int)$cnt->fetchColumn();
        $total_pages = max(1, (int)ceil($total / $per_page));

        $stmt = $pdo->prepare("
            SELECT eq.id, eq.user_id, eq.to_email, eq.to_name, eq.template_slug,
                   eq.subject, eq.status, eq.attempts, eq.last_error,
                   eq.scheduled_at, eq.sent_at, eq.created_at
            FROM email_queue eq
            WHERE {$where_sql}
            ORDER BY
                CASE eq.status
                    WHEN 'failed'  THEN 0
                    WHEN 'pending' THEN 1
                    WHEN 'sending' THEN 2
                    ELSE 3
                END ASC,
                eq.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[admin/emails] ' . $e->getMessage());
        $flash = ['type' => 'err', 'msg' => 'Erreur de base de données.'];
    }
}

require_once '_admin-header.php';
?>

<style>
/* ── Stats ───────────────────────────────────────────────── */
.em-stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.em-stat  { flex:1 1 110px; background:#fff; border-radius:10px; padding:16px 18px;
            box-shadow:0 2px 8px rgba(12,30,46,.06); border:1px solid rgba(18,49,78,.07); text-align:center; }
.em-stat-val  { font-size:1.7rem; font-weight:900; color:#0c1e2e; line-height:1; }
.em-stat-lbl  { font-size:.68rem; font-weight:700; color:#6b7f96; text-transform:uppercase;
                letter-spacing:.08em; margin-top:4px; }
.em-stat-alert .em-stat-val { color:#c0392b; }

/* ── Filtres ─────────────────────────────────────────────── */
.em-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
.em-sel  { padding:8px 12px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; color:#0f1e2d; background:#fff; cursor:pointer; }
.em-sel:focus { outline:none; border-color:#ea5649; }
.em-srch { padding:8px 12px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; flex:1; min-width:180px; }
.em-srch:focus { outline:none; border-color:#ea5649; }

/* ── Tableau ─────────────────────────────────────────────── */
.em-table    { width:100%; border-collapse:collapse; }
.em-table th { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
               color:#6b7f96; padding:10px 14px; background:#f8f4ef;
               border-bottom:1px solid #e8e4df; text-align:left; }
.em-table td { padding:9px 14px; border-bottom:1px solid #f0ece7;
               vertical-align:middle; font-size:.83rem; }
.em-table tbody tr:hover td { background:#faf8f5; }
.em-row-failed td { border-left:3px solid #c0392b; }

/* ── Badges statut ───────────────────────────────────────── */
.em-badge { display:inline-flex; align-items:center; padding:2px 9px;
            border-radius:999px; font-size:.67rem; font-weight:700; white-space:nowrap; }
.em-badge-pending  { background:rgba(201,150,42,.12); color:#8a6020; }
.em-badge-sending  { background:rgba(14,165,233,.1);  color:#0369a1; }
.em-badge-sent     { background:rgba(42,157,92,.1);   color:#1a7a42; }
.em-badge-failed   { background:rgba(234,86,73,.12);  color:#c0392b; }

.em-badge-tpl { background:rgba(18,49,78,.08); color:#12314e; font-size:.65rem;
                padding:2px 8px; border-radius:999px; font-weight:700; }

/* ── Boutons d'action ───────────────────────────────────── */
.em-actions  { display:flex; gap:4px; align-items:center; flex-wrap:wrap; }
.em-act-btn  { padding:4px 10px; border-radius:6px; font-family:'Inter',sans-serif; font-size:.72rem;
               font-weight:700; cursor:pointer; border:none; white-space:nowrap; transition:opacity .15s; }
.em-act-btn:hover  { opacity:.82; }
.em-btn-retry { background:rgba(42,157,92,.1);  color:#1a7a42; }
.em-btn-del   { background:rgba(234,86,73,.1);  color:#c0392b; }

.em-last-err { font-size:.72rem; color:#c0392b; max-width:200px; white-space:nowrap;
               overflow:hidden; text-overflow:ellipsis; cursor:help; }

/* ── Pagination ──────────────────────────────────────────── */
.em-pager { display:flex; gap:6px; justify-content:center; padding:16px; flex-wrap:wrap; }
.em-pager a, .em-pager span { padding:5px 12px; border-radius:7px; font-size:.79rem; font-weight:700;
                               text-decoration:none; border:1.5px solid #d0cbc5; color:#3d5166; }
.em-pager a:hover   { border-color:#ea5649; color:#ea5649; }
.em-pg-active { background:#ea5649 !important; color:#fff !important; border-color:#ea5649 !important; }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">📬 File d'emails</h1>
    <p class="adm-page-sub">Suivi de la file d'attente — pending, envoyés, échecs.</p>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
  <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<?php if ($stats['failed'] > 0): ?>
<div class="adm-flash adm-flash-err" style="margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span>❌ <strong><?= $stats['failed'] ?> email<?= $stats['failed'] > 1 ? 's' : '' ?> échoué<?= $stats['failed'] > 1 ? 's' : '' ?></strong> dans la file.</span>
  <?php if ($filter_status !== 'failed'): ?>
  <a href="emails.php?status=failed" style="color:#c0392b;font-weight:800">Voir les échecs →</a>
  <?php endif; ?>
  <form method="POST" style="margin:0;margin-left:auto" onsubmit="return confirm('Relancer tous les emails échoués ?')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="retry_all_failed">
    <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm" style="color:#1a7a42;border-color:#1a7a42">
      ↩ Relancer tous
    </button>
  </form>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="em-stats">
  <div class="em-stat <?= ($stats['pending'] + $stats['sending']) > 0 ? '' : '' ?>">
    <div class="em-stat-val" style="color:#8a6020"><?= $stats['pending'] ?></div>
    <div class="em-stat-lbl">En attente</div>
  </div>
  <div class="em-stat">
    <div class="em-stat-val" style="color:#0369a1"><?= $stats['sending'] ?></div>
    <div class="em-stat-lbl">En cours</div>
  </div>
  <div class="em-stat">
    <div class="em-stat-val" style="color:#1a7a42"><?= $stats['sent'] ?></div>
    <div class="em-stat-lbl">Envoyés</div>
  </div>
  <div class="em-stat <?= $stats['failed'] > 0 ? 'em-stat-alert' : '' ?>">
    <div class="em-stat-val"><?= $stats['failed'] ?></div>
    <div class="em-stat-lbl">Échecs</div>
  </div>
  <div class="em-stat">
    <div class="em-stat-val"><?= $stats['total'] ?></div>
    <div class="em-stat-lbl">Total</div>
  </div>
</div>

<!-- Filtres -->
<form method="GET" action="emails.php">
  <div class="em-filters">
    <select name="status" class="em-sel" onchange="this.form.submit()">
      <option value="all"     <?= $filter_status === 'all'     ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
      <option value="sending" <?= $filter_status === 'sending' ? 'selected' : '' ?>>🔄 En cours</option>
      <option value="sent"    <?= $filter_status === 'sent'    ? 'selected' : '' ?>>✅ Envoyés</option>
      <option value="failed"  <?= $filter_status === 'failed'  ? 'selected' : '' ?>>❌ Échecs</option>
    </select>
    <?php if (!empty($templates)): ?>
    <select name="template" class="em-sel" onchange="this.form.submit()">
      <option value="">Tous les templates</option>
      <?php foreach ($templates as $tpl): ?>
      <option value="<?= e($tpl['slug']) ?>" <?= $filter_template === $tpl['slug'] ? 'selected' : '' ?>>
        <?= e($tpl['slug']) ?><?= $tpl['description'] ? ' — ' . e(mb_strimwidth($tpl['description'], 0, 40, '…')) : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="text" name="q" class="em-srch"
           placeholder="Email, nom, sujet…"
           value="<?= e($search) ?>">
    <button type="submit"
            style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;
                   font-family:'Inter',sans-serif;font-weight:700;font-size:.82rem;cursor:pointer">
      Filtrer
    </button>
    <?php if ($filter_status !== 'all' || $filter_template !== '' || $search !== ''): ?>
    <a href="emails.php"
       style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">
      ✕ Réinitialiser
    </a>
    <?php endif; ?>
  </div>
</form>

<!-- Bouton bulk : supprimer tous les échecs (hors tableau) -->
<?php if ($stats['failed'] > 0): ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <form method="POST" onsubmit="return confirm('Supprimer définitivement tous les <?= $stats['failed'] ?> emails échoués ?')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_all_failed">
    <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm" style="color:#c0392b;border-color:#c0392b">
      🗑 Supprimer tous les échecs (<?= $stats['failed'] ?>)
    </button>
  </form>
</div>
<?php endif; ?>

<!-- Tableau -->
<div class="adm-card" style="padding:0;overflow:hidden">

  <?php if (empty($rows)): ?>
  <div class="adm-empty" style="padding:48px 24px">
    <div class="adm-empty-icon">📬</div>
    <p>Aucun email avec ces filtres.</p>
    <?php if ($filter_status !== 'all' || $filter_template !== '' || $search !== ''): ?>
    <a href="emails.php" class="btn-adm btn-adm-ghost" style="margin-top:16px">Réinitialiser les filtres</a>
    <?php endif; ?>
  </div>

  <?php else: ?>

  <div class="adm-table-wrap">
    <table class="em-table">
      <thead>
        <tr>
          <th>Destinataire</th>
          <th>Template</th>
          <th>Sujet</th>
          <th>Statut</th>
          <th style="text-align:center">Tentatives</th>
          <th>Erreur</th>
          <th>Créé le</th>
          <th>Envoyé le</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $rid         = (int)$row['id'];
            $badge_class = match($row['status']) {
                'pending' => 'em-badge-pending',
                'sending' => 'em-badge-sending',
                'sent'    => 'em-badge-sent',
                'failed'  => 'em-badge-failed',
                default   => '',
            };
            $badge_label = match($row['status']) {
                'pending' => '⏳ Pending',
                'sending' => '🔄 En cours',
                'sent'    => '✅ Envoyé',
                'failed'  => '❌ Échec',
                default   => e($row['status']),
            };
        ?>
        <tr class="<?= $row['status'] === 'failed' ? 'em-row-failed' : '' ?>">
          <td>
            <div style="font-weight:600;color:#0c1e2e"><?= e($row['to_name'] ?: '—') ?></div>
            <div style="font-size:.74rem;color:#6b7f96"><?= e($row['to_email']) ?></div>
          </td>
          <td>
            <span class="em-badge-tpl"><?= e($row['template_slug'] ?: '—') ?></span>
          </td>
          <td style="max-width:220px;color:#0f1e2d">
            <?= e(mb_strimwidth($row['subject'], 0, 70, '…')) ?>
          </td>
          <td>
            <span class="em-badge <?= $badge_class ?>"><?= $badge_label ?></span>
          </td>
          <td style="text-align:center;font-weight:700;color:<?= (int)$row['attempts'] >= 3 ? '#c0392b' : '#0c1e2e' ?>">
            <?= (int)$row['attempts'] ?>
          </td>
          <td>
            <?php if ($row['last_error']): ?>
            <div class="em-last-err" title="<?= e($row['last_error']) ?>">
              <?= e(mb_strimwidth($row['last_error'], 0, 60, '…')) ?>
            </div>
            <?php else: ?>
            <span style="color:#9aadbc;font-size:.76rem">—</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap;color:#6b7f96;font-size:.76rem">
            <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
          </td>
          <td style="white-space:nowrap;color:#6b7f96;font-size:.76rem">
            <?= $row['sent_at'] ? date('d/m/Y H:i', strtotime($row['sent_at'])) : '—' ?>
          </td>
          <td>
            <div class="em-actions">
              <?php if ($row['status'] === 'failed'): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="retry">
                <input type="hidden" name="item_id" value="<?= $rid ?>">
                <button type="submit" class="em-act-btn em-btn-retry">↩ Relancer</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="margin:0"
                    onsubmit="return confirm('Supprimer cet email de la file ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="item_id" value="<?= $rid ?>">
                <button type="submit" class="em-act-btn em-btn-del">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="em-pager">
    <?php
    $qs_base = array_filter([
        'status'   => $filter_status !== 'all' ? $filter_status : null,
        'template' => $filter_template !== '' ? $filter_template : null,
        'q'        => $search !== '' ? $search : null,
    ], fn($v) => $v !== null);
    for ($i = 1; $i <= $total_pages; $i++):
        $qs = http_build_query(array_merge($qs_base, ['p' => $i]));
        if ($i === $page): ?>
    <span class="em-pg-active"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $total_pages || abs($i - $page) <= 2): ?>
    <a href="emails.php?<?= $qs ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?>
    <span>…</span>
        <?php endif;
    endfor; ?>
  </div>
  <?php endif; ?>

  <div style="padding:10px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> email<?= $total > 1 ? 's' : '' ?> · Page <?= $page ?>/<?= max(1, $total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once '_admin-footer.php'; ?>
