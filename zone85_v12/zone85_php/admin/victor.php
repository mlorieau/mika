<?php
// admin/victor.php — Statistiques des téléchargements de VICTOR
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

$admin_current    = 'victor';
$admin_page_title = 'VICTOR — Téléchargements';

$pdo = db();

// ── KPIs ──────────────────────────────────────────────────────
$total       = 0;
$today       = 0;
$this_week   = 0;
$this_month  = 0;
$unique_users = 0;

// ── Par jour (30 derniers jours) ──────────────────────────────
$daily = [];

// ── Top téléchargeurs ─────────────────────────────────────────
$top_users = [];

// ── Téléchargements récents ───────────────────────────────────
$recent = [];

$table_exists = false;

if ($pdo) {
    try {
        // Vérifier existence de la table
        $pdo->query("SELECT 1 FROM victor_downloads LIMIT 1");
        $table_exists = true;

        // KPIs
        $total = (int)$pdo->query("SELECT COUNT(*) FROM victor_downloads")->fetchColumn();
        $today = (int)$pdo->query("SELECT COUNT(*) FROM victor_downloads WHERE DATE(downloaded_at) = CURDATE()")->fetchColumn();
        $this_week = (int)$pdo->query("SELECT COUNT(*) FROM victor_downloads WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $this_month = (int)$pdo->query("SELECT COUNT(*) FROM victor_downloads WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $unique_users = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM victor_downloads")->fetchColumn();

        // Par jour — 30 derniers jours
        $stmt = $pdo->query("
            SELECT DATE(downloaded_at) AS jour, COUNT(*) AS nb
            FROM victor_downloads
            WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(downloaded_at)
            ORDER BY jour DESC
        ");
        $daily = $stmt->fetchAll();

        // Top 10 téléchargeurs
        $stmt = $pdo->query("
            SELECT u.pseudo, u.id AS user_id,
                   COUNT(vd.id) AS nb,
                   MAX(vd.downloaded_at) AS last_dl
            FROM victor_downloads vd
            JOIN users u ON u.id = vd.user_id
            GROUP BY vd.user_id
            ORDER BY nb DESC
            LIMIT 10
        ");
        $top_users = $stmt->fetchAll();

        // 20 derniers téléchargements
        $stmt = $pdo->query("
            SELECT vd.downloaded_at, vd.ip, u.pseudo, u.id AS user_id
            FROM victor_downloads vd
            LEFT JOIN users u ON u.id = vd.user_id
            ORDER BY vd.downloaded_at DESC
            LIMIT 20
        ");
        $recent = $stmt->fetchAll();

    } catch (PDOException $e) {
        // Table absente — affichage état vide
    }
}

// Max pour la barre de graphe
$max_daily = $daily ? max(array_column($daily, 'nb')) : 1;

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">📖 VICTOR — Téléchargements</h1>
    <p class="adm-page-sub">Suivi des accès au PDF VICTOR par les membres.</p>
  </div>
  <div class="adm-page-actions">
    <a href="../victor.php" class="btn-adm btn-adm-ghost btn-adm-sm" target="_blank">Voir la page publique →</a>
  </div>
</div>

<?php if (!$table_exists): ?>
<div class="adm-flash adm-flash-warn">
  ⚠️ La table <code>victor_downloads</code> n'existe pas encore. Elle sera créée automatiquement lors du premier accès à la page <strong>victor.php</strong> par un membre connecté.
</div>
<?php elseif ($total === 0): ?>
<div class="adm-flash adm-flash-info">
  ℹ️ Aucun téléchargement enregistré pour l'instant. Le compteur démarrera dès que le premier membre téléchargera VICTOR.
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat-label">Total téléchargements</div>
    <div class="adm-stat-value coral"><?= $total ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Membres uniques</div>
    <div class="adm-stat-value blue"><?= $unique_users ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Aujourd'hui</div>
    <div class="adm-stat-value green"><?= $today ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">7 derniers jours</div>
    <div class="adm-stat-value"><?= $this_week ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">30 derniers jours</div>
    <div class="adm-stat-value amber"><?= $this_month ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

  <!-- Graphe par jour -->
  <div class="adm-card">
    <div class="adm-card-title">Téléchargements par jour — 30 derniers jours</div>
    <?php if (empty($daily)): ?>
    <div class="adm-empty" style="padding:28px">
      <p>Aucune donnée</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach ($daily as $row):
        $pct = $max_daily > 0 ? round($row['nb'] / $max_daily * 100) : 0;
      ?>
      <div style="display:grid;grid-template-columns:90px 1fr 30px;align-items:center;gap:8px;font-size:.76rem">
        <span style="color:#6b7f96;font-weight:600"><?= date('d/m', strtotime($row['jour'])) ?></span>
        <div style="background:#f0ece7;border-radius:4px;height:8px;overflow:hidden">
          <div style="width:<?= $pct ?>%;height:100%;background:#ea5649;border-radius:4px"></div>
        </div>
        <span style="font-weight:700;color:#0c1e2e;text-align:right"><?= $row['nb'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Top téléchargeurs -->
  <div class="adm-card">
    <div class="adm-card-title">Top téléchargeurs</div>
    <?php if (empty($top_users)): ?>
    <div class="adm-empty" style="padding:28px">
      <p>Aucune donnée</p>
    </div>
    <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Membre</th>
            <th>Téléchargements</th>
            <th>Dernier</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_users as $i => $u): ?>
          <tr>
            <td style="color:#6b7f96;font-weight:700"><?= $i + 1 ?></td>
            <td>
              <a href="users.php?id=<?= (int)$u['user_id'] ?>" style="font-weight:700;color:#0c1e2e;text-decoration:none">
                <?= e($u['pseudo']) ?>
              </a>
            </td>
            <td><strong><?= (int)$u['nb'] ?></strong></td>
            <td style="color:#6b7f96;font-size:.78rem"><?= date('d/m/Y H:i', strtotime($u['last_dl'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Téléchargements récents -->
<div class="adm-card">
  <div class="adm-card-title">20 téléchargements les plus récents</div>
  <?php if (empty($recent)): ?>
  <div class="adm-empty">
    <div class="adm-empty-icon">📖</div>
    <p>Aucun téléchargement enregistré pour l'instant.</p>
  </div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Date / heure</th>
          <th>Membre</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td style="color:#6b7f96;font-size:.8rem;white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($r['downloaded_at'])) ?></td>
          <td>
            <?php if ($r['pseudo']): ?>
            <a href="users.php?id=<?= (int)$r['user_id'] ?>" style="font-weight:700;color:#0c1e2e;text-decoration:none">
              <?= e($r['pseudo']) ?>
            </a>
            <?php else: ?>
            <span style="color:#6b7f96">—</span>
            <?php endif; ?>
          </td>
          <td style="color:#6b7f96;font-size:.78rem;font-family:monospace"><?= e($r['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '_admin-footer.php'; ?>
