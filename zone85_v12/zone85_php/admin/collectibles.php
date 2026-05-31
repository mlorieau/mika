<?php
// ============================================================
// admin/collectibles.php — Objets cachés d'une mission
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current = 'missions';
$mission_id    = isset($_GET['mission_id']) ? (int)$_GET['mission_id'] : 0;

if ($mission_id <= 0) {
    header('Location: missions.php');
    exit;
}

$pdo     = db();
$mission = null;
if ($pdo) {
    try {
        $s = $pdo->prepare("SELECT * FROM missions WHERE id=:id AND mission_type='hidden_hunt' LIMIT 1");
        $s->execute([':id'=>$mission_id]);
        $mission = $s->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/collectibles] ' . $e->getMessage());
    }
}

if (!$mission) {
    header('Location: missions.php?err=not_found');
    exit;
}

$collectibles = fetch_mission_collectibles_admin($mission_id);

// Flash depuis URL
$flash_ok  = null;
$flash_err = null;
if (isset($_GET['ok']))  $flash_ok  = match($_GET['ok'])  { 'created'=>'Objet ajouté.','updated'=>'Objet mis à jour.','deleted'=>'Objet supprimé.', default=>'' };
if (isset($_GET['err'])) $flash_err = match($_GET['err']) { 'not_found'=>'Objet introuvable.', default=>'' };

// Toggle actif/inactif
if (isset($_GET['toggle']) && $pdo) {
    if (verify_csrf_token($_GET['csrf'] ?? '')) {
        $tid = (int)$_GET['toggle'];
        $pdo->prepare("UPDATE mission_collectibles SET is_active=1-is_active WHERE id=:id AND mission_id=:mid")
            ->execute([':id'=>$tid, ':mid'=>$mission_id]);
        header('Location: collectibles.php?mission_id=' . $mission_id);
        exit;
    }
}

$admin_page_title = 'Objets cachés — ' . ($mission['title'] ?? '');
$page_slugs = ['index','clans','missions','hall','classement','profil'];

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Objets cachés</h1>
    <p class="adm-page-sub">
      Mission : <strong><?= e($mission['title']) ?></strong>
      — <?= count($collectibles) ?> objet<?= count($collectibles) > 1 ? 's' : '' ?> défini<?= count($collectibles) > 1 ? 's' : '' ?>
      — Statut :
      <span class="adm-badge badge-<?= e($mission['status']) ?>"><?= e($mission['status']) ?></span>
    </p>
  </div>
  <div class="adm-page-actions">
    <a href="mission-edit.php?id=<?= $mission_id ?>" class="btn-adm btn-adm-ghost">← Mission</a>
    <a href="collectible-edit.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-primary">+ Ajouter un objet</a>
  </div>
</div>

<?php if ($flash_ok): ?><div class="adm-flash adm-flash-ok">✅ <?= e($flash_ok) ?></div><?php endif; ?>
<?php if ($flash_err): ?><div class="adm-flash adm-flash-err">⚠️ <?= e($flash_err) ?></div><?php endif; ?>

<!-- Info positionnement -->
<div class="adm-flash adm-flash-info" style="margin-bottom:20px">
  <div>
    <strong>💡 Positionnement :</strong>
    Les valeurs <em>top</em> et <em>left</em> sont en % du viewport (écran).
    Ex : top=60 left=85 = objet dans le coin inférieur droit.
    Testez en naviguant sur la page publique correspondante.
  </div>
</div>

<?php if (empty($collectibles)): ?>
<div class="adm-card">
  <div class="adm-empty">
    <div class="adm-empty-icon">🗝️</div>
    <p>Aucun objet caché pour cette mission.<br>Créez le premier !</p>
    <a href="collectible-edit.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-primary" style="margin-top:16px">+ Ajouter un objet</a>
  </div>
</div>
<?php else: ?>
<div class="adm-card" style="padding:0">
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Objet</th>
          <th>Page</th>
          <th>Position T / L</th>
          <th>Image</th>
          <th>GIF succès</th>
          <th>Trouvé par</th>
          <th>Actif</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($collectibles as $c): ?>
        <tr <?= !$c['is_active'] ? 'style="opacity:.5"' : '' ?>>
          <td style="color:#6b7f96;font-size:.75rem"><?= (int)$c['id'] ?></td>
          <td>
            <div style="font-weight:700;color:#0c1e2e"><?= e($c['title']) ?></div>
            <div style="font-size:.72rem;color:#6b7f96;font-family:monospace"><?= e($c['collectible_key']) ?></div>
            <?php if ($c['hint']): ?>
            <div style="font-size:.72rem;color:#6b7f96;font-style:italic;margin-top:2px">💡 <?= e(mb_substr($c['hint'], 0, 50)) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span style="background:#f0ece7;padding:3px 10px;border-radius:5px;font-size:.78rem;font-weight:700;font-family:monospace"><?= e($c['page_slug']) ?></span>
          </td>
          <td style="font-family:monospace;font-size:.82rem;color:#3d5166">
            top <?= number_format((float)$c['position_top'], 1) ?>%<br>
            left <?= number_format((float)$c['position_left'], 1) ?>%
          </td>
          <td>
            <?php if ($c['object_image']): ?>
            <img src="<?= e(url($c['object_image'])) ?>" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:6px;background:#f0ece7;padding:4px">
            <?php else: ?>
            <span style="color:#6b7f96;font-size:.75rem">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['success_gif']): ?>
            <img src="<?= e(url($c['success_gif'])) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px">
            <?php else: ?>
            <span style="color:#6b7f96;font-size:.75rem">—</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-weight:700;color:<?= (int)$c['found_count'] > 0 ? '#2a9d5c' : '#6b7f96' ?>">
            <?= (int)$c['found_count'] ?>
          </td>
          <td>
            <?php if ($c['is_active']): ?>
            <span class="adm-badge badge-active">Actif</span>
            <?php else: ?>
            <span class="adm-badge badge-archived">Inactif</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="collectible-edit.php?id=<?= (int)$c['id'] ?>&mission_id=<?= $mission_id ?>"
                 class="btn-adm btn-adm-ghost btn-adm-sm">Éditer</a>
              <a href="collectibles.php?mission_id=<?= $mission_id ?>&toggle=<?= (int)$c['id'] ?>&csrf=<?= csrf_token() ?>"
                 class="btn-adm btn-adm-ghost btn-adm-sm"
                 onclick="return confirm('<?= $c['is_active'] ? 'Désactiver' : 'Activer' ?> cet objet ?')"
                 title="<?= $c['is_active'] ? 'Désactiver' : 'Activer' ?>">
                <?= $c['is_active'] ? '⏸' : '▶' ?>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Aperçu pages -->
<div class="adm-card" style="margin-top:8px">
  <div class="adm-card-title">Répartition par page</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php
    $by_page = [];
    foreach ($collectibles as $c) {
      $by_page[$c['page_slug']] = ($by_page[$c['page_slug']] ?? 0) + 1;
    }
    foreach ($page_slugs as $ps):
      $n = $by_page[$ps] ?? 0;
    ?>
    <div style="background:<?= $n > 0 ? 'rgba(42,157,92,.1)' : '#f8f4ef' ?>;border:1.5px solid <?= $n > 0 ? 'rgba(42,157,92,.2)' : '#e8e2db' ?>;border-radius:10px;padding:12px 18px;text-align:center;min-width:90px">
      <div style="font-size:1.2rem;margin-bottom:4px">
        <?= ['index'=>'🏠','clans'=>'🛡️','missions'=>'🗺️','hall'=>'🏆','classement'=>'📊','profil'=>'👤'][$ps] ?? '📌' ?>
      </div>
      <div style="font-size:.75rem;font-weight:700;font-family:monospace;color:#3d5166"><?= $ps ?></div>
      <div style="font-size:.88rem;font-weight:900;color:<?= $n > 0 ? '#2a9d5c' : '#6b7f96' ?>"><?= $n ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once '_admin-footer.php'; ?>
