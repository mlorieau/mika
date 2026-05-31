<?php
// ============================================================
// ZONE85 — Admin : KTC Editorial — Liste des episodes
// ============================================================
$admin_current    = 'ktc-episodes';
$admin_page_title = 'KTC Editorial — Episodes';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo   = db();
$flash = '';
$flash_type = 'ok';

// ── Traitement POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Formulaire rejete.';
        $flash_type = 'err';
    } else {
        $action     = $_POST['action'] ?? '';
        $episode_id = (int)($_POST['episode_id'] ?? 0);

        if ($action === 'advance_phase' && $episode_id > 0) {
            $phase_map = [
                'draft'    => 'week1',
                'week1'    => 'week2',
                'week2'    => 'week3',
                'week3'    => 'revealed',
                'revealed' => 'archived',
            ];
            try {
                $s = $pdo->prepare('SELECT status FROM ktc_episodes WHERE id = :id LIMIT 1');
                $s->execute([':id' => $episode_id]);
                $row = $s->fetch();
                if ($row && isset($phase_map[$row['status']])) {
                    $new_status = $phase_map[$row['status']];
                    $u = $pdo->prepare('UPDATE ktc_episodes SET status = :new_status WHERE id = :id');
                    $u->execute([':new_status' => $new_status, ':id' => $episode_id]);
                    $flash = 'Phase avancee avec succes.';
                    $flash_type = 'ok';
                } else {
                    $flash = 'Impossible d\'avancer la phase (statut actuel non modifiable).';
                    $flash_type = 'err';
                }
            } catch (PDOException $e) {
                $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $flash_type = 'err';
            }
        } elseif ($action === 'archive' && $episode_id > 0) {
            try {
                $u = $pdo->prepare('UPDATE ktc_episodes SET status = \'archived\' WHERE id = :id');
                $u->execute([':id' => $episode_id]);
                $flash = 'Episode archive.';
                $flash_type = 'ok';
            } catch (PDOException $e) {
                $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $flash_type = 'err';
            }
        }
    }
}

// ── Statistiques ───────────────────────────────────────────────
$stat_total    = 0;
$stat_actifs   = 0;
$stat_reveles  = 0;
$stat_brouillons = 0;

if ($pdo) {
    try {
        $st = $pdo->query("
            SELECT
                COUNT(*)                                                          AS total,
                SUM(status IN ('week1','week2','week3'))                          AS actifs,
                SUM(status = 'revealed')                                          AS reveles,
                SUM(status = 'draft')                                             AS brouillons
            FROM ktc_episodes
        ");
        $stats = $st->fetch();
        if ($stats) {
            $stat_total      = (int)$stats['total'];
            $stat_actifs     = (int)$stats['actifs'];
            $stat_reveles    = (int)$stats['reveles'];
            $stat_brouillons = (int)$stats['brouillons'];
        }
    } catch (PDOException $e) {}
}

// ── Liste des episodes ─────────────────────────────────────────
$episodes = [];
if ($pdo) {
    try {
        $s = $pdo->query("
            SELECT id, title, slug, status, person_name, date_revelation
            FROM ktc_episodes
            ORDER BY id DESC
        ");
        $episodes = $s->fetchAll();
    } catch (PDOException $e) {}
}

// ── Labels et couleurs des statuts ────────────────────────────
function ktc_status_badge(string $status): string {
    $badges = [
        'draft'    => ['label' => 'Brouillon',             'style' => 'background:rgba(107,127,150,.12);color:#4a5f73'],
        'week1'    => ['label' => 'Semaine 1 — Decouverte','style' => 'background:rgba(147,197,253,.25);color:#1d4ed8'],
        'week2'    => ['label' => 'Semaine 2 — Indices',   'style' => 'background:rgba(96,165,250,.2);color:#1e40af'],
        'week3'    => ['label' => 'Semaine 3 — Votes',     'style' => 'background:rgba(251,146,60,.2);color:#c2410c'],
        'revealed' => ['label' => 'Revele',                'style' => 'background:rgba(42,157,92,.12);color:#1a7a42'],
        'archived' => ['label' => 'Archive',               'style' => 'background:rgba(55,65,81,.1);color:#374151'],
    ];
    $b = $badges[$status] ?? ['label' => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'), 'style' => ''];
    return '<span class="adm-badge" style="' . $b['style'] . '">' . htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8') . '</span>';
}

function ktc_can_advance(string $status): bool {
    return in_array($status, ['draft', 'week1', 'week2', 'week3', 'revealed'], true);
}

function ktc_advance_label(string $status): string {
    $map = [
        'draft'    => 'Lancer sem. 1',
        'week1'    => 'Passer sem. 2',
        'week2'    => 'Passer sem. 3',
        'week3'    => 'Reveler',
        'revealed' => 'Archiver',
    ];
    return $map[$status] ?? '';
}

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">&#x1F950; KTC &mdash; Episodes</h1>
    <p class="adm-page-sub">Gestion des episodes mensuels &laquo;&nbsp;Kiffe ta chine&nbsp;&raquo;</p>
  </div>
  <div class="adm-page-actions">
    <a href="ktc-episode-edit.php" class="btn-adm btn-adm-primary">&#x2B; Nouvel episode</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= $flash_type ?>">
    <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!$pdo): ?>
  <div class="adm-flash adm-flash-err">
    Base de donn&eacute;es non disponible.
  </div>
<?php endif; ?>

<!-- ── Statistiques ────────────────────────────────────────── -->
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat-label">Total</div>
    <div class="adm-stat-value"><?= $stat_total ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Actifs (sem. 1&ndash;3)</div>
    <div class="adm-stat-value blue"><?= $stat_actifs ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Reveles</div>
    <div class="adm-stat-value green"><?= $stat_reveles ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Brouillons</div>
    <div class="adm-stat-value amber"><?= $stat_brouillons ?></div>
  </div>
</div>

<!-- ── Tableau ──────────────────────────────────────────────── -->
<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($episodes)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">&#x1F950;</div>
      <p>Aucun episode pour l'instant.<br>
        <a href="ktc-episode-edit.php" style="color:#ea5649;font-weight:700">Cr&eacute;er le premier episode</a>
      </p>
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Titre</th>
            <th>Statut</th>
            <th>Brocanteur</th>
            <th>Date r&eacute;v&eacute;lation</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($episodes as $ep): ?>
            <tr>
              <td style="color:#6b7f96;font-size:.78rem"><?= (int)$ep['id'] ?></td>
              <td>
                <a href="ktc-episode-edit.php?id=<?= (int)$ep['id'] ?>"
                   style="font-weight:700;color:#0c1e2e;text-decoration:none">
                  <?= htmlspecialchars($ep['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div style="font-size:.72rem;color:#6b7f96;margin-top:2px">
                  <?= htmlspecialchars($ep['slug'], ENT_QUOTES, 'UTF-8') ?>
                </div>
              </td>
              <td><?= ktc_status_badge($ep['status']) ?></td>
              <td style="color:#3d5166">
                <?= $ep['person_name'] ? htmlspecialchars($ep['person_name'], ENT_QUOTES, 'UTF-8') : '<span style="color:#bbb">—</span>' ?>
              </td>
              <td style="font-size:.82rem;color:#3d5166">
                <?= $ep['date_revelation'] ? htmlspecialchars($ep['date_revelation'], ENT_QUOTES, 'UTF-8') : '<span style="color:#bbb">—</span>' ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                  <!-- Editer -->
                  <a href="ktc-episode-edit.php?id=<?= (int)$ep['id'] ?>"
                     class="btn-adm btn-adm-ghost btn-adm-sm">&#x270F; Editer</a>

                  <!-- Avancer la phase -->
                  <?php if (ktc_can_advance($ep['status'])): ?>
                    <form method="post" style="display:inline"
                          onsubmit="return confirm('Avancer la phase de cet episode ?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action"     value="advance_phase">
                      <input type="hidden" name="episode_id" value="<?= (int)$ep['id'] ?>">
                      <button type="submit" class="btn-adm btn-adm-primary btn-adm-sm">
                        &#x25B6; <?= htmlspecialchars(ktc_advance_label($ep['status']), ENT_QUOTES, 'UTF-8') ?>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Archiver (si pas deja archive) -->
                  <?php if ($ep['status'] !== 'archived'): ?>
                    <form method="post" style="display:inline"
                          onsubmit="return confirm('Archiver cet episode ?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action"     value="archive">
                      <input type="hidden" name="episode_id" value="<?= (int)$ep['id'] ?>">
                      <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm"
                              style="color:#6b7f96">
                        &#x1F4E6; Archiver
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
