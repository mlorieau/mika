<?php
// ============================================================
// admin/index.php — Dashboard Admin Zone85 V8
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

// V10 : redirection vers le nouveau dashboard enrichi
header('Location: dashboard.php', true, 301);
exit;

$admin_current    = 'dashboard';
$admin_page_title = 'Dashboard';

// Statistiques
$pdo = db();
$stats = [
    'missions_active'   => 0,
    'participations_pending' => 0,
    'members'           => 0,
    'xp_total'          => 0,
    'clan_pts_season'   => 0,
];
$last_mission        = null;
$last_participations = [];

if ($pdo) {
    try {
        $stats['missions_active'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM missions WHERE status = 'active'")->fetchColumn();

        $stats['participations_pending'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM participations WHERE status = 'pending'")->fetchColumn();

        $stats['members'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

        $stats['xp_total'] = (int)$pdo->query(
            "SELECT COALESCE(SUM(xp_amount), 0) FROM xp_logs")->fetchColumn();

        $sr = _active_season_row();
        if ($sr) {
            $stats['clan_pts_season'] = (int)$pdo->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM clan_score_logs WHERE season_id = :sid"
            )->execute([':sid' => (int)$sr['id']]) ? 0 : 0;
            $s = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM clan_score_logs WHERE season_id = :sid");
            $s->execute([':sid' => (int)$sr['id']]);
            $stats['clan_pts_season'] = (int)$s->fetchColumn();
        }

        $s = $pdo->query("SELECT id, title, status, mission_type FROM missions ORDER BY created_at DESC LIMIT 1");
        $last_mission = $s->fetch() ?: null;

        $s = $pdo->query("
            SELECT p.id, p.status, p.created_at,
                   u.pseudo, m.title AS mission_title
            FROM participations p
            JOIN users u    ON u.id = p.user_id
            JOIN missions m ON m.id = p.mission_id
            ORDER BY p.created_at DESC
            LIMIT 8
        ");
        $last_participations = $s->fetchAll();

    } catch (PDOException $e) {
        error_log('[ZONE85 admin/index] ' . $e->getMessage());
    }
}

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Dashboard</h1>
    <p class="adm-page-sub">Vue d'ensemble du moteur de jeu Zone85.</p>
  </div>
  <div class="adm-page-actions">
    <a href="mission-edit.php" class="btn-adm btn-adm-primary">+ Nouvelle mission</a>
  </div>
</div>

<!-- Stats -->
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat-label">Missions actives</div>
    <div class="adm-stat-value coral"><?= $stats['missions_active'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Participations en attente</div>
    <div class="adm-stat-value <?= $stats['participations_pending'] > 0 ? 'amber' : 'green' ?>"><?= $stats['participations_pending'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Membres actifs</div>
    <div class="adm-stat-value blue"><?= $stats['members'] ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">XP distribués (total)</div>
    <div class="adm-stat-value"><?= number_format($stats['xp_total'], 0, ',', ' ') ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Pts clan (saison)</div>
    <div class="adm-stat-value"><?= number_format($stats['clan_pts_season'], 0, ',', ' ') ?></div>
  </div>
</div>

<!-- Quick actions -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px">
  <a href="missions.php"       class="btn-adm btn-adm-ghost">📋 Gérer les missions</a>
  <a href="participations.php" class="btn-adm btn-adm-ghost">👀 Voir les participations<?= $stats['participations_pending'] > 0 ? ' <span style="background:#ea5649;color:#fff;padding:2px 7px;border-radius:99px;font-size:.7rem;margin-left:6px">' . $stats['participations_pending'] . '</span>' : '' ?></a>
  <a href="mission-edit.php"   class="btn-adm btn-adm-primary">+ Créer une mission</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- Dernière mission -->
  <div class="adm-card">
    <div class="adm-card-title">Dernière mission créée</div>
    <?php if ($last_mission): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div>
        <div style="font-weight:800;font-size:.95rem;color:#0c1e2e;margin-bottom:6px">
          <?= htmlspecialchars($last_mission['title'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div style="display:flex;gap:8px">
          <span class="adm-badge badge-<?= e($last_mission['status']) ?>"><?= e($last_mission['status']) ?></span>
          <span style="font-size:.75rem;color:#6b7f96;font-weight:600"><?= mission_type_icon($last_mission['mission_type']) ?> <?= e(mission_type_label($last_mission['mission_type'])) ?></span>
        </div>
      </div>
      <a href="mission-edit.php?id=<?= (int)$last_mission['id'] ?>" class="btn-adm btn-adm-ghost btn-adm-sm">Éditer</a>
    </div>
    <?php else: ?>
    <div class="adm-empty" style="padding:24px"><p>Aucune mission créée.</p></div>
    <?php endif; ?>
  </div>

  <!-- Dernières participations -->
  <div class="adm-card">
    <div class="adm-card-title">Dernières participations</div>
    <?php if ($last_participations): ?>
    <div style="display:flex;flex-direction:column;gap:0">
      <?php foreach ($last_participations as $lp): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid #f0ece7">
        <div style="min-width:0">
          <div style="font-size:.82rem;font-weight:700;color:#0c1e2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= e($lp['pseudo']) ?> — <?= e(mb_substr($lp['mission_title'], 0, 30)) ?>
          </div>
          <div style="font-size:.72rem;color:#6b7f96"><?= substr($lp['created_at'], 0, 16) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <span class="adm-badge badge-<?= e($lp['status']) ?>"><?= e($lp['status']) ?></span>
          <?php if ($lp['status'] === 'pending'): ?>
          <a href="participation-view.php?id=<?= (int)$lp['id'] ?>" class="btn-adm btn-adm-primary btn-adm-sm">Traiter</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:14px">
      <a href="participations.php" style="font-size:.8rem;font-weight:700;color:#ea5649;text-decoration:none">Voir toutes →</a>
    </div>
    <?php else: ?>
    <div class="adm-empty" style="padding:24px"><p>Aucune participation enregistrée.</p></div>
    <?php endif; ?>
  </div>

</div>

<?php require_once '_admin-footer.php'; ?>
