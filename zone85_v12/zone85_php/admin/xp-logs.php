<?php
// ============================================================
// admin/xp-logs.php — Journal d'audit XP (lecture seule)
// ============================================================
$admin_current    = 'xp-logs';
$admin_page_title = 'Journal XP';

define('SKIP_MAINTENANCE_CHECK', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

$pdo = db();

// ═══════════════════════════════════════════════════════════════
// STATS GLOBALES
// ═══════════════════════════════════════════════════════════════
$stats = [
    'total_xp'       => 0,
    'xp_7d'          => 0,
    'xp_30d'         => 0,
    'distinct_users' => 0,
    'total_logs'     => 0,
];
if ($pdo) {
    try {
        $s = $pdo->query("
            SELECT
                SUM(xp_amount)                                             AS total_xp,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         THEN xp_amount ELSE 0 END)                       AS xp_7d,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                         THEN xp_amount ELSE 0 END)                       AS xp_30d,
                COUNT(DISTINCT user_id)                                    AS distinct_users,
                COUNT(*)                                                   AS total_logs
            FROM xp_logs
        ")->fetch();
        if ($s) {
            $stats['total_xp']       = (int)$s['total_xp'];
            $stats['xp_7d']          = (int)$s['xp_7d'];
            $stats['xp_30d']         = (int)$s['xp_30d'];
            $stats['distinct_users'] = (int)$s['distinct_users'];
            $stats['total_logs']     = (int)$s['total_logs'];
        }
    } catch (PDOException $e) {
        error_log('[admin/xp-logs stats] ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════════════════════
// SOURCE TYPES (pour le filtre dropdown)
// ═══════════════════════════════════════════════════════════════
$source_types = [];
if ($pdo) {
    try {
        $source_types = $pdo->query("
            SELECT DISTINCT source_type FROM xp_logs
            WHERE source_type IS NOT NULL AND source_type != ''
            ORDER BY source_type ASC
        ")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}

// ═══════════════════════════════════════════════════════════════
// FILTRES & PAGINATION
// ═══════════════════════════════════════════════════════════════
$search      = trim($_GET['q']           ?? '');
$filter_type = trim($_GET['source_type'] ?? '');
$date_from   = trim($_GET['date_from']   ?? '');
$date_to     = trim($_GET['date_to']     ?? '');
$page        = max(1, (int)($_GET['p']   ?? 1));
$per_page    = 50;
$offset      = ($page - 1) * $per_page;

// Validate dates
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
if ($date_to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = '';

$rows        = [];
$total       = 0;
$total_pages = 1;

if ($pdo) {
    try {
        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = 'u.pseudo LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }
        if ($filter_type !== '') {
            $where[] = 'xl.source_type = :stype';
            $params[':stype'] = $filter_type;
        }
        if ($date_from !== '') {
            $where[] = 'xl.created_at >= :dfrom';
            $params[':dfrom'] = $date_from . ' 00:00:00';
        }
        if ($date_to !== '') {
            $where[] = 'xl.created_at <= :dto';
            $params[':dto'] = $date_to . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where);

        $cnt = $pdo->prepare("
            SELECT COUNT(*)
            FROM xp_logs xl
            LEFT JOIN users u ON u.id = xl.user_id
            WHERE {$where_sql}
        ");
        $cnt->execute($params);
        $total       = (int)$cnt->fetchColumn();
        $total_pages = max(1, (int)ceil($total / $per_page));

        $stmt = $pdo->prepare("
            SELECT
                xl.id, xl.user_id, xl.source_type, xl.source_id,
                xl.xp_amount, xl.reason, xl.created_at,
                u.pseudo
            FROM xp_logs xl
            LEFT JOIN users u ON u.id = xl.user_id
            WHERE {$where_sql}
            ORDER BY xl.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[admin/xp-logs] ' . $e->getMessage());
    }
}

// Palette de couleurs par source_type (cohérente, générée par hash)
function xp_type_color(string $type): array {
    $palette = [
        ['bg' => 'rgba(234,86,73,.1)',   'fg' => '#c0392b'],
        ['bg' => 'rgba(42,157,92,.1)',   'fg' => '#1a7a42'],
        ['bg' => 'rgba(14,165,233,.1)',  'fg' => '#0369a1'],
        ['bg' => 'rgba(155,89,182,.1)',  'fg' => '#7d3c98'],
        ['bg' => 'rgba(201,150,42,.1)',  'fg' => '#8a6020'],
        ['bg' => 'rgba(18,49,78,.08)',   'fg' => '#12314e'],
        ['bg' => 'rgba(22,160,133,.1)',  'fg' => '#0e8073'],
        ['bg' => 'rgba(230,126,34,.1)',  'fg' => '#b7770d'],
    ];
    $idx = abs(crc32($type)) % count($palette);
    return $palette[$idx];
}

require_once '_admin-header.php';
?>

<style>
/* ── Stats ───────────────────────────────────────────────── */
.xp-stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.xp-stat  { flex:1 1 130px; background:#fff; border-radius:10px; padding:16px 18px;
            box-shadow:0 2px 8px rgba(12,30,46,.06); border:1px solid rgba(18,49,78,.07); text-align:center; }
.xp-stat-val  { font-size:1.6rem; font-weight:900; color:#0c1e2e; line-height:1; }
.xp-stat-lbl  { font-size:.68rem; font-weight:700; color:#6b7f96; text-transform:uppercase;
                letter-spacing:.08em; margin-top:4px; }

/* ── Filtres ─────────────────────────────────────────────── */
.xp-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
.xp-sel  { padding:8px 12px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; color:#0f1e2d; background:#fff; cursor:pointer; }
.xp-sel:focus { outline:none; border-color:#ea5649; }
.xp-srch { padding:8px 12px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; min-width:140px; }
.xp-srch:focus { outline:none; border-color:#ea5649; }
.xp-date { padding:8px 10px; border:1.5px solid #d0cbc5; border-radius:7px;
           font-family:'Inter',sans-serif; font-size:.82rem; color:#0f1e2d; background:#fff; }
.xp-date:focus { outline:none; border-color:#ea5649; }

/* ── Tableau ─────────────────────────────────────────────── */
.xp-table    { width:100%; border-collapse:collapse; }
.xp-table th { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
               color:#6b7f96; padding:10px 14px; background:#f8f4ef;
               border-bottom:1px solid #e8e4df; text-align:left; }
.xp-table td { padding:9px 14px; border-bottom:1px solid #f0ece7;
               vertical-align:middle; font-size:.83rem; }
.xp-table tbody tr:hover td { background:#faf8f5; }

/* ── Badges source_type ──────────────────────────────────── */
.xp-type-badge { display:inline-flex; align-items:center; padding:2px 9px;
                 border-radius:999px; font-size:.67rem; font-weight:700; white-space:nowrap; }

/* ── Montant XP ──────────────────────────────────────────── */
.xp-amount-pos { font-weight:800; color:#1a7a42; }
.xp-amount-neg { font-weight:800; color:#c0392b; }
.xp-amount-zero { font-weight:700; color:#6b7f96; }

/* ── User link ───────────────────────────────────────────── */
.xp-user-link { font-weight:700; color:#0c1e2e; text-decoration:none; font-size:.83rem; }
.xp-user-link:hover { color:#ea5649; text-decoration:underline; }

/* ── Pagination ──────────────────────────────────────────── */
.xp-pager { display:flex; gap:6px; justify-content:center; padding:16px; flex-wrap:wrap; }
.xp-pager a, .xp-pager span { padding:5px 12px; border-radius:7px; font-size:.79rem; font-weight:700;
                               text-decoration:none; border:1.5px solid #d0cbc5; color:#3d5166; }
.xp-pager a:hover { border-color:#ea5649; color:#ea5649; }
.xp-pg-active { background:#ea5649 !important; color:#fff !important; border-color:#ea5649 !important; }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">⭐ Journal XP</h1>
    <p class="adm-page-sub">Audit de la distribution XP — lecture seule, toutes sources confondues.</p>
  </div>
</div>

<!-- Stats -->
<div class="xp-stats">
  <div class="xp-stat">
    <div class="xp-stat-val" style="color:#ea5649">
      <?= number_format($stats['total_xp'], 0, ',', '\u{202F}') ?> XP
    </div>
    <div class="xp-stat-lbl">Total distribué</div>
  </div>
  <div class="xp-stat">
    <div class="xp-stat-val" style="color:#0369a1">
      <?= number_format($stats['xp_7d'], 0, ',', '\u{202F}') ?> XP
    </div>
    <div class="xp-stat-lbl">7 derniers jours</div>
  </div>
  <div class="xp-stat">
    <div class="xp-stat-val" style="color:#8a6020">
      <?= number_format($stats['xp_30d'], 0, ',', '\u{202F}') ?> XP
    </div>
    <div class="xp-stat-lbl">30 derniers jours</div>
  </div>
  <div class="xp-stat">
    <div class="xp-stat-val"><?= number_format($stats['distinct_users'], 0, ',', '\u{202F}') ?></div>
    <div class="xp-stat-lbl">Utilisateurs récompensés</div>
  </div>
  <div class="xp-stat">
    <div class="xp-stat-val"><?= number_format($stats['total_logs'], 0, ',', '\u{202F}') ?></div>
    <div class="xp-stat-lbl">Entrées journal</div>
  </div>
</div>

<!-- Filtres -->
<form method="GET" action="xp-logs.php">
  <div class="xp-filters">
    <input type="text" name="q" class="xp-srch"
           placeholder="Pseudo…"
           value="<?= e($search) ?>">
    <?php if (!empty($source_types)): ?>
    <select name="source_type" class="xp-sel" onchange="this.form.submit()">
      <option value="">Toutes les sources</option>
      <?php foreach ($source_types as $stype): ?>
      <option value="<?= e($stype) ?>" <?= $filter_type === $stype ? 'selected' : '' ?>>
        <?= e($stype) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <label style="font-size:.78rem;color:#6b7f96;display:flex;align-items:center;gap:6px;white-space:nowrap">
      Du
      <input type="date" name="date_from" class="xp-date" value="<?= e($date_from) ?>">
    </label>
    <label style="font-size:.78rem;color:#6b7f96;display:flex;align-items:center;gap:6px;white-space:nowrap">
      Au
      <input type="date" name="date_to" class="xp-date" value="<?= e($date_to) ?>">
    </label>
    <button type="submit"
            style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;
                   font-family:'Inter',sans-serif;font-weight:700;font-size:.82rem;cursor:pointer">
      Filtrer
    </button>
    <?php if ($search !== '' || $filter_type !== '' || $date_from !== '' || $date_to !== ''): ?>
    <a href="xp-logs.php"
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
    <div class="adm-empty-icon">⭐</div>
    <p>Aucune entrée XP avec ces filtres.</p>
    <?php if ($search !== '' || $filter_type !== '' || $date_from !== '' || $date_to !== ''): ?>
    <a href="xp-logs.php" class="btn-adm btn-adm-ghost" style="margin-top:16px">Réinitialiser les filtres</a>
    <?php endif; ?>
  </div>

  <?php else: ?>

  <div class="adm-table-wrap">
    <table class="xp-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Utilisateur</th>
          <th>Source</th>
          <th>Raison</th>
          <th style="text-align:right">XP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $xp     = (int)$row['xp_amount'];
            $color  = xp_type_color($row['source_type'] ?? '');
            $pseudo = $row['pseudo'] ?? ('user#' . $row['user_id']);
        ?>
        <tr>
          <td style="white-space:nowrap;color:#6b7f96;font-size:.78rem">
            <?= date('d/m/Y', strtotime($row['created_at'])) ?><br>
            <span style="font-size:.69rem"><?= date('H:i', strtotime($row['created_at'])) ?></span>
          </td>
          <td>
            <?php if ($row['user_id']): ?>
            <a href="user-edit.php?id=<?= (int)$row['user_id'] ?>" class="xp-user-link">
              <?= e($pseudo) ?>
            </a>
            <div style="font-size:.71rem;color:#9aadbc">ID <?= (int)$row['user_id'] ?></div>
            <?php else: ?>
            <span style="color:#9aadbc;font-size:.82rem">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['source_type']): ?>
            <span class="xp-type-badge"
                  style="background:<?= $color['bg'] ?>;color:<?= $color['fg'] ?>">
              <?= e($row['source_type']) ?>
            </span>
            <?php if ($row['source_id']): ?>
            <div style="font-size:.69rem;color:#9aadbc;margin-top:2px">ID <?= (int)$row['source_id'] ?></div>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:#9aadbc;font-size:.76rem">—</span>
            <?php endif; ?>
          </td>
          <td style="max-width:260px;color:#0f1e2d">
            <?= $row['reason'] ? e(mb_strimwidth($row['reason'], 0, 80, '…')) : '<span style="color:#9aadbc">—</span>' ?>
          </td>
          <td style="text-align:right;white-space:nowrap">
            <?php if ($xp > 0): ?>
            <span class="xp-amount-pos">+<?= $xp ?></span>
            <?php elseif ($xp < 0): ?>
            <span class="xp-amount-neg"><?= $xp ?></span>
            <?php else: ?>
            <span class="xp-amount-zero">0</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="xp-pager">
    <?php
    $qs_base = array_filter([
        'q'           => $search !== '' ? $search : null,
        'source_type' => $filter_type !== '' ? $filter_type : null,
        'date_from'   => $date_from !== '' ? $date_from : null,
        'date_to'     => $date_to !== '' ? $date_to : null,
    ], fn($v) => $v !== null);
    for ($i = 1; $i <= $total_pages; $i++):
        $qs = http_build_query(array_merge($qs_base, ['p' => $i]));
        if ($i === $page): ?>
    <span class="xp-pg-active"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $total_pages || abs($i - $page) <= 2): ?>
    <a href="xp-logs.php?<?= $qs ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?>
    <span>…</span>
        <?php endif;
    endfor; ?>
  </div>
  <?php endif; ?>

  <div style="padding:10px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= number_format($total, 0, ',', '\u{202F}') ?> entrée<?= $total > 1 ? 's' : '' ?> · Page <?= $page ?>/<?= max(1, $total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once '_admin-footer.php'; ?>
