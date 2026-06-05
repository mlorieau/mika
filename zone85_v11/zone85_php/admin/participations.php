<?php
// ============================================================
// admin/participations.php — Liste des participations
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current    = 'participations';
$admin_page_title = 'Participations';

$filter_status    = $_GET['status']     ?? 'pending'; // Par défaut : pending
$filter_type      = $_GET['type']       ?? '';
$filter_clan      = $_GET['clan']       ?? '';
$filter_mission   = (int)($_GET['mission_id'] ?? 0);

$pdo            = db();
$participations = [];
$nb_pending     = 0;

if ($pdo) {
    try {
        $nb_pending = (int)$pdo->query(
            "SELECT COUNT(*) FROM participations WHERE status = 'pending'")->fetchColumn();

        $where  = ['1=1'];
        $params = [];

        if ($filter_status) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filter_status;
        }
        if ($filter_type) {
            $where[] = 'm.mission_type = :type';
            $params[':type'] = $filter_type;
        }
        if ($filter_clan) {
            $where[] = 'c.slug = :clan';
            $params[':clan'] = $filter_clan;
        }
        if ($filter_mission > 0) {
            $where[] = 'p.mission_id = :mid';
            $params[':mid'] = $filter_mission;
        }

        $sql = "
            SELECT p.id, p.status, p.xp_awarded, p.clan_points_awarded,
                   p.created_at, p.validated_at,
                   u.pseudo, u.id AS user_id,
                   c.slug AS clan_slug, c.name AS clan_name,
                   m.title AS mission_title, m.mission_type, m.validation_mode
            FROM participations p
            JOIN users    u ON u.id = p.user_id
            JOIN missions m ON m.id = p.mission_id
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE p.status WHEN 'pending' THEN 0 ELSE 1 END,
                p.created_at DESC
            LIMIT 200
        ";
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $participations = $s->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/participations] ' . $e->getMessage());
    }
}

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">
      Participations
      <?php if ($nb_pending > 0): ?>
      <span style="background:#ea5649;color:#fff;padding:3px 10px;border-radius:99px;font-size:.7rem;margin-left:8px;vertical-align:middle"><?= $nb_pending ?> en attente</span>
      <?php endif; ?>
    </h1>
    <p class="adm-page-sub"><?= count($participations) ?> participation<?= count($participations) > 1 ? 's' : '' ?> trouvée<?= count($participations) > 1 ? 's' : '' ?>.</p>
  </div>
</div>

<!-- Filtres -->
<form method="GET" class="adm-filters">
  <select name="status" onchange="this.form.submit()">
    <option value="">Tous statuts</option>
    <option value="pending"        <?= $filter_status === 'pending'        ? 'selected' : '' ?>>En attente</option>
    <option value="validated"      <?= $filter_status === 'validated'      ? 'selected' : '' ?>>Validées</option>
    <option value="auto_validated" <?= $filter_status === 'auto_validated' ? 'selected' : '' ?>>Auto-validées</option>
    <option value="rejected"       <?= $filter_status === 'rejected'       ? 'selected' : '' ?>>Refusées</option>
  </select>
  <select name="type" onchange="this.form.submit()">
    <option value="">Tous types</option>
    <?php
    $types = ['seasonal_collective'=>'Grande Mission','quiz'=>'Quiz','vote'=>'Vote',
              'photo_challenge'=>'Photo','keto_kole_tche'=>'KTC','rando'=>'Rando',
              'weather_mission'=>'Météo','investigation'=>'Enquête',
              'hidden_hunt'=>'Chasse','premium_game'=>'Premium'];
    foreach ($types as $v => $l):
    ?>
    <option value="<?= $v ?>" <?= $filter_type === $v ? 'selected' : '' ?>><?= $l ?></option>
    <?php endforeach; ?>
  </select>
  <select name="clan" onchange="this.form.submit()">
    <option value="">Tous les clans</option>
    <option value="bocage"   <?= $filter_clan === 'bocage'   ? 'selected' : '' ?>>🌳 Bocage</option>
    <option value="littoral" <?= $filter_clan === 'littoral' ? 'selected' : '' ?>>⚓ Littoral</option>
    <option value="marais"   <?= $filter_clan === 'marais'   ? 'selected' : '' ?>>🌿 Marais</option>
  </select>
  <?php if ($filter_status !== 'pending' || $filter_type || $filter_clan || $filter_mission): ?>
  <a href="participations.php" class="btn-adm btn-adm-ghost btn-adm-sm">✕ Réinitialiser</a>
  <?php endif; ?>
  <!-- Raccourcis rapides -->
  <div style="margin-left:auto;display:flex;gap:6px">
    <a href="participations.php?status=pending" class="btn-adm <?= $filter_status === 'pending' && !$filter_type && !$filter_clan ? 'btn-adm-primary' : 'btn-adm-ghost' ?> btn-adm-sm">
      ⏳ Pending <?= $nb_pending > 0 ? "($nb_pending)" : '' ?>
    </a>
    <a href="participations.php?status=" class="btn-adm btn-adm-ghost btn-adm-sm">Toutes</a>
  </div>
</form>

<div class="adm-card" style="padding:0">
  <?php if (empty($participations)): ?>
  <div class="adm-empty">
    <div class="adm-empty-icon">👍</div>
    <p><?= $filter_status === 'pending' ? 'Aucune participation en attente. Bien joué !' : 'Aucune participation trouvée.' ?></p>
  </div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Membre</th>
          <th>Clan</th>
          <th>Mission</th>
          <th>Type</th>
          <th>Validation</th>
          <th>XP attrib.</th>
          <th>Pts clan</th>
          <th>Date</th>
          <th>Statut</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($participations as $p): ?>
        <tr <?= $p['status'] === 'pending' ? 'style="background:#fffbf0"' : '' ?>>
          <td style="color:#6b7f96;font-size:.75rem"><?= (int)$p['id'] ?></td>
          <td style="font-weight:700"><?= e($p['pseudo']) ?></td>
          <td>
            <?php
            $clan_icons = ['bocage'=>'🌳','littoral'=>'⚓','marais'=>'🌿'];
            echo ($clan_icons[$p['clan_slug']] ?? '') . ' ' . e($p['clan_name'] ?? $p['clan_slug'] ?? '—');
            ?>
          </td>
          <td style="max-width:180px">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;font-weight:600">
              <?= e($p['mission_title']) ?>
            </div>
          </td>
          <td><?= mission_type_icon($p['mission_type']) ?> <?= e(mission_type_label($p['mission_type'])) ?></td>
          <td><span class="adm-badge badge-<?= e($p['validation_mode']) ?>"><?= e($p['validation_mode']) ?></span></td>
          <td style="text-align:right;color:#ea5649;font-weight:800"><?= (int)$p['xp_awarded'] ?></td>
          <td style="text-align:right;color:#C9962A;font-weight:800"><?= (int)$p['clan_points_awarded'] ?></td>
          <td style="font-size:.75rem;color:#6b7f96;white-space:nowrap"><?= substr($p['created_at'], 0, 16) ?></td>
          <td><span class="adm-badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
          <td>
            <a href="participation-view.php?id=<?= (int)$p['id'] ?>"
               class="btn-adm <?= $p['status'] === 'pending' ? 'btn-adm-primary' : 'btn-adm-ghost' ?> btn-adm-sm">
              <?= $p['status'] === 'pending' ? 'Traiter' : 'Voir' ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '_admin-footer.php'; ?>
