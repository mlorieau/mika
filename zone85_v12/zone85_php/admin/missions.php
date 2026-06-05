<?php
// ============================================================
// admin/missions.php — Liste des missions
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current    = 'missions';
$admin_page_title = 'Missions';

// Filtres
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';

$pdo      = db();
$missions = [];

if ($pdo) {
    try {
        $where  = ['1=1'];
        $params = [];
        if ($filter_status) {
            $where[] = 'm.status = :status';
            $params[':status'] = $filter_status;
        }
        if ($filter_type) {
            $where[] = 'm.mission_type = :type';
            $params[':type'] = $filter_type;
        }
        $sql = "
            SELECT m.*,
                   (SELECT COUNT(*) FROM participations p WHERE p.mission_id = m.id) AS nb_participations
            FROM missions m
            WHERE " . implode(' AND ', $where) . "
            ORDER BY m.created_at DESC
            LIMIT 200
        ";
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $missions = $s->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/missions] ' . $e->getMessage());
    }
}

$status_options = ['', 'draft', 'active', 'closed', 'archived'];
$type_options   = [
    '' => 'Tous types',
    'seasonal_collective' => 'Grande Mission',
    'quiz'                => 'Quiz',
    'vote'                => 'Vote',
    'photo_challenge'     => 'Photo',
    'keto_kole_tche'      => 'KTC',
    'rando'               => 'Rando',
    'weather_mission'     => 'Météo',
    'investigation'       => 'Enquête',
    'hidden_hunt'         => 'Chasse cachée',
    'premium_game'        => 'Jeu Premium',
];

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Missions</h1>
    <p class="adm-page-sub"><?= count($missions) ?> mission<?= count($missions) > 1 ? 's' : '' ?> trouvée<?= count($missions) > 1 ? 's' : '' ?>.</p>
  </div>
  <div class="adm-page-actions">
    <a href="mission-edit.php" class="btn-adm btn-adm-primary">+ Nouvelle mission</a>
  </div>
</div>

<!-- Filtres -->
<form method="GET" class="adm-filters" style="margin-bottom:18px">
  <select name="status" onchange="this.form.submit()">
    <option value="">Tous statuts</option>
    <?php foreach (['draft'=>'Brouillon','active'=>'Active','closed'=>'Fermée','archived'=>'Archivée'] as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filter_status === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" onchange="this.form.submit()">
    <?php foreach ($type_options as $v => $l): ?>
    <option value="<?= $v ?>" <?= $filter_type === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($filter_status || $filter_type): ?>
  <a href="missions.php" class="btn-adm btn-adm-ghost btn-adm-sm">✕ Effacer filtres</a>
  <?php endif; ?>
</form>

<div class="adm-card" style="padding:0">
  <?php if (empty($missions)): ?>
  <div class="adm-empty">
    <div class="adm-empty-icon">📋</div>
    <p>Aucune mission trouvée.</p>
    <a href="mission-edit.php" class="btn-adm btn-adm-primary" style="margin-top:16px">Créer la première mission</a>
  </div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Titre</th>
          <th>Type</th>
          <th>Validation</th>
          <th>XP part.</th>
          <th>XP réussite</th>
          <th>Pts clan</th>
          <th>Participations</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($missions as $m): ?>
        <tr>
          <td style="color:#6b7f96;font-size:.75rem"><?= (int)$m['id'] ?></td>
          <td style="font-weight:700;max-width:220px">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px">
              <?= e($m['title']) ?>
            </div>
          </td>
          <td><?= mission_type_icon($m['mission_type']) ?> <?= e(mission_type_label($m['mission_type'])) ?></td>
          <td><span class="adm-badge badge-<?= e($m['validation_mode']) ?>"><?= e($m['validation_mode']) ?></span></td>
          <td style="text-align:right;color:#ea5649;font-weight:800"><?= (int)$m['xp_participation'] ?></td>
          <td style="text-align:right;color:#2a9d5c;font-weight:800"><?= (int)$m['xp_success'] ?></td>
          <td style="text-align:right;color:#C9962A;font-weight:800"><?= (int)$m['clan_points_success'] ?></td>
          <td style="text-align:right;font-weight:700"><?= (int)$m['nb_participations'] ?></td>
          <td><span class="adm-badge badge-<?= e($m['status']) ?>"><?= e($m['status']) ?></span></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:nowrap">
              <a href="mission-edit.php?id=<?= (int)$m['id'] ?>" class="btn-adm btn-adm-ghost btn-adm-sm">Éditer</a>
              <a href="../mission.php?id=<?= (int)$m['id'] ?>" class="btn-adm btn-adm-ghost btn-adm-sm" target="_blank" title="Voir sur le site">↗</a>
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
