<?php
// ============================================================
// ZONE85 — Admin KTC éditorial V11.1
// 1 KTC / mois : rencontre + objet mystère + 4 phases.
// Remplace l'ancien écran "banque de questions".
// ============================================================

$admin_current    = 'ktc';
$admin_page_title = 'KTC — Épisodes éditoriaux';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo = db();
$episodes = [];
$stats = ['total' => 0, 'draft' => 0, 'active' => 0, 'revealed' => 0];
$err = '';

if ($pdo) {
    try {
        $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM ktc_episodes")->fetchColumn();
        $stats['draft'] = (int)$pdo->query("SELECT COUNT(*) FROM ktc_episodes WHERE status='draft'")->fetchColumn();
        $stats['active'] = (int)$pdo->query("SELECT COUNT(*) FROM ktc_episodes WHERE status IN ('week1','week2','week3')")->fetchColumn();
        $stats['revealed'] = (int)$pdo->query("SELECT COUNT(*) FROM ktc_episodes WHERE status='revealed'")->fetchColumn();
        $episodes = $pdo->query("SELECT * FROM ktc_episodes ORDER BY COALESCE(date_week1, created_at) DESC, id DESC")->fetchAll();
    } catch (PDOException $e) {
        $err = "Les tables KTC éditoriales ne semblent pas encore importées. Importer database/migrations/017_v11_ktc_editorial.sql.";
    }
}

function ktc_status_label(string $status): string {
    return [
        'draft' => 'Brouillon',
        'week1' => 'Semaine 1 — Découverte',
        'week2' => 'Semaine 2 — Indices',
        'week3' => 'Semaine 3 — Votes',
        'revealed' => 'Révélation',
        'archived' => 'Archivé',
    ][$status] ?? $status;
}

require_once __DIR__ . '/_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Kéto Kolé Tché</h1>
    <p class="adm-page-sub">1 épisode mensuel : un brocanteur, un objet mystère, une histoire locale. Ce n'est plus une banque de questions.</p>
  </div>
  <div class="adm-page-actions">
    <a class="btn-adm btn-adm-primary" href="ktc-episode-edit.php">+ Créer un épisode</a>
    <a class="btn-adm btn-adm-ghost" href="../ktc.php" target="_blank">Voir la page publique</a>
  </div>
</div>

<?php if ($err): ?>
  <div class="adm-flash adm-flash-err">⚠️ <?= e($err) ?></div>
<?php endif; ?>

<section class="adm-stats">
  <div class="adm-stat"><div class="adm-stat-label">Épisodes</div><div class="adm-stat-value coral"><?= (int)$stats['total'] ?></div></div>
  <div class="adm-stat"><div class="adm-stat-label">Brouillons</div><div class="adm-stat-value amber"><?= (int)$stats['draft'] ?></div></div>
  <div class="adm-stat"><div class="adm-stat-label">En cours</div><div class="adm-stat-value blue"><?= (int)$stats['active'] ?></div></div>
  <div class="adm-stat"><div class="adm-stat-label">Révélés</div><div class="adm-stat-value green"><?= (int)$stats['revealed'] ?></div></div>
</section>

<div class="adm-card">
  <h2 class="adm-card-title">Logique éditoriale validée</h2>
  <div class="adm-form-grid">
    <div class="adm-field"><strong>1. Rencontre</strong><span class="adm-hint">On présente le brocanteur, antiquaire ou collectionneur.</span></div>
    <div class="adm-field"><strong>2. Objet</strong><span class="adm-hint">On montre un vrai objet à deviner, pas une question de quiz.</span></div>
    <div class="adm-field"><strong>3. Participation</strong><span class="adm-hint">Les Zonautes proposent, comparent, votent.</span></div>
    <div class="adm-field"><strong>4. Révélation</strong><span class="adm-hint">On raconte l'histoire de l'objet et de la personne rencontrée.</span></div>
  </div>
</div>

<div class="adm-card">
  <h2 class="adm-card-title">Épisodes KTC</h2>
  <?php if (empty($episodes)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">🥐</div>
      <p>Aucun épisode KTC pour le moment. Crée le premier mystère du mois.</p>
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Personne</th>
            <th>Objet</th>
            <th>Statut</th>
            <th>Révélation</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($episodes as $ep): ?>
          <tr>
            <td><strong><?= e($ep['title'] ?? '') ?></strong><br><span class="adm-hint"><?= e($ep['slug'] ?? '') ?></span></td>
            <td><?= e(trim(($ep['person_name'] ?? '') . ' ' . ($ep['person_title'] ? '— '.$ep['person_title'] : ''))) ?: '<span class="adm-hint">Non renseigné</span>' ?></td>
            <td><?= !empty($ep['object_name']) && empty($ep['object_hidden']) ? e($ep['object_name']) : '<span class="adm-hint">Masqué jusqu’à révélation</span>' ?></td>
            <td><span class="adm-badge badge-<?= e($ep['status'] ?? 'draft') ?>"><?= e(ktc_status_label($ep['status'] ?? 'draft')) ?></span></td>
            <td><?= e($ep['date_revelation'] ?? '') ?: '<span class="adm-hint">À définir</span>' ?></td>
            <td><a class="btn-adm btn-adm-sm btn-adm-ghost" href="ktc-episode-edit.php?id=<?= (int)$ep['id'] ?>">Modifier</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
