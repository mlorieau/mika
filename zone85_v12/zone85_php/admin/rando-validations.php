<?php
// ============================================================
// ZONE85 V12.7 — Admin validations photos randos
// ============================================================
$admin_current = 'rando-validations';
$admin_page_title = 'Validations Randos';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
require_admin();

$pdo = db();
$flash = '';
$flash_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = 'Jeton CSRF invalide.';
        $flash_type = 'err';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        try {
            $s = $pdo->prepare('SELECT rp.*, r.title AS rando_title, u.pseudo, u.id AS uid FROM rando_participations rp JOIN randos r ON r.id=rp.rando_id JOIN users u ON u.id=rp.user_id WHERE rp.id=:id LIMIT 1');
            $s->execute([':id'=>$id]);
            $row = $s->fetch();
            if (!$row) throw new RuntimeException('Participation introuvable.');

            if ($action === 'validate') {
                $pdo->beginTransaction();
                if ((int)($row['xp_awarded'] ?? 0) === 0 && function_exists('award_xp')) {
                    award_xp((int)$row['uid'], 25, 'rando', (int)$row['rando_id'], 'Randonnée validée : ' . ($row['rando_title'] ?? ''));
                }
                $up = $pdo->prepare('UPDATE rando_participations SET status="validated", xp_awarded=1, validated_at=NOW(), validated_by=:admin WHERE id=:id');
                $admin_id = (int)((current_user()['id'] ?? 0));
                $up->execute([':admin'=>$admin_id ?: null, ':id'=>$id]);
                // Vérification badge "Chasseur de Randos" (5 randos validées)
                $cnt_s = $pdo->prepare("SELECT COUNT(*) FROM rando_participations WHERE user_id=:uid AND status='validated'");
                $cnt_s->execute([':uid' => (int)$row['uid']]);
                if ((int)$cnt_s->fetchColumn() >= 5) {
                    $b = $pdo->prepare("SELECT id FROM badges WHERE slug='chasseur-randos' LIMIT 1");
                    $b->execute();
                    $badge = $b->fetch();
                    if ($badge) {
                        $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id, source_type) VALUES (:uid,:bid,'rando')")
                            ->execute([':uid' => (int)$row['uid'], ':bid' => (int)$badge['id']]);
                    }
                }
                $pdo->commit();
                $flash = 'Rando validée : +25 XP attribués.';
            } elseif ($action === 'reject') {
                $note = safe_input($_POST['admin_note'] ?? '', 500);
                $up = $pdo->prepare('UPDATE rando_participations SET status="rejected", admin_note=:note WHERE id=:id');
                $up->execute([':note'=>$note ?: null, ':id'=>$id]);
                $flash = 'Demande refusée.';
            }
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $flash_type = 'err';
        }
    }
}

$status = in_array($_GET['status'] ?? 'pending', ['pending','validated','rejected','stamped','all'], true) ? ($_GET['status'] ?? 'pending') : 'pending';
$rows = [];
if ($pdo) {
    try {
        $where = $status === 'all' ? '1=1' : 'rp.status=:status';
        $sql = "SELECT rp.*, r.title AS rando_title, r.slug, u.pseudo
                FROM rando_participations rp
                JOIN randos r ON r.id=rp.rando_id
                JOIN users u ON u.id=rp.user_id
                WHERE $where
                ORDER BY rp.done_at DESC
                LIMIT 200";
        $s = $pdo->prepare($sql);
        $params = $status === 'all' ? [] : [':status'=>$status];
        $s->execute($params);
        $rows = $s->fetchAll();
    } catch (PDOException $e) {}
}

require_once __DIR__ . '/_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Validations Randos</h1>
    <p class="adm-page-sub">Photos envoyées par les Zonautes pour débloquer les XP randonnée.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= $flash_type === 'err' ? 'err' : 'ok' ?>"><?= $flash ?></div>
<?php endif; ?>

<div class="adm-card">
  <div class="adm-filters">
    <?php foreach (['pending'=>'En attente','validated'=>'Validées','rejected'=>'Refusées','stamped'=>'Tampons simples','all'=>'Toutes'] as $k=>$label): ?>
      <a class="btn-adm <?= $status===$k?'btn-adm-primary':'btn-adm-ghost' ?> btn-adm-sm" href="rando-validations.php?status=<?= $k ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($rows)): ?>
    <div class="adm-empty" style="padding:34px 20px">
      <div class="adm-empty-icon">🥾</div>
      <p>Aucune validation rando dans cette vue.</p>
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Zonaute</th>
            <th>Rando</th>
            <th>Statut</th>
            <th>Photo</th>
            <th>Avis</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['pseudo'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td><a href="../rando.php?slug=<?= urlencode($r['slug'] ?? '') ?>" target="_blank"><?= htmlspecialchars($r['rando_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></a></td>
            <td><span class="adm-badge badge-<?= htmlspecialchars($r['status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
            <td>
              <?php if (!empty($r['photo_path'])): ?>
                <a href="<?= htmlspecialchars(media_url($r['photo_path']), ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                  <img src="<?= htmlspecialchars(media_url($r['photo_path']), ENT_QUOTES, 'UTF-8') ?>" alt="preuve" style="width:70px;height:52px;object-fit:cover;border-radius:8px">
                </a>
              <?php else: ?>
                <span style="color:#6b7f96">—</span>
              <?php endif; ?>
            </td>
            <td style="max-width:260px">
              <?php if (!empty($r['proof_rating'])): ?>
                <div style="font-weight:800;color:#c9962a"><?= str_repeat('★', (int)$r['proof_rating']) ?><?= str_repeat('☆', 5-(int)$r['proof_rating']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['proof_review'])): ?>
                <div style="font-size:.82rem;color:#31475d;line-height:1.35;margin-top:4px"><?= nl2br(htmlspecialchars($r['proof_review'], ENT_QUOTES, 'UTF-8')) ?></div>
              <?php else: ?>
                <span style="color:#6b7f96">—</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['done_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if (($r['status'] ?? '') === 'pending'): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="validate">
                  <button class="btn-adm btn-adm-success btn-adm-sm" type="submit">Valider +25 XP</button>
                </form>
                <form method="post" style="display:inline;margin-left:6px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn-adm btn-adm-danger btn-adm-sm" type="submit">Refuser</button>
                </form>
              <?php else: ?>
                <span style="color:#6b7f96">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
