<?php
$admin_current    = 'events';
$admin_page_title = 'Événements Flash';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

// ── Actions POST ──────────────────────────────────────────────
$flash_message = null;
$flash_type    = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_message = 'Token CSRF invalide. Veuillez réessayer.';
        $flash_type    = 'err';
    } else {
        $action = $_POST['action'] ?? '';
        $pdo    = db();

        if ($action === 'create_flash' && $pdo) {
            try {
                $raw_title = trim($_POST['title'] ?? '');
                // Génération du slug ASCII
                $slug_base = strtolower(preg_replace(
                    '/[^a-z0-9]+/', '-',
                    iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($raw_title))
                ));
                $slug = trim($slug_base, '-') . '-' . time();

                $stmt = $pdo->prepare(
                    'INSERT INTO missions
                        (title, slug, mission_type, validation_mode, status, is_flash,
                         flash_start_at, flash_end_at, xp_multiplier, xp_participation,
                         cover_emoji, description, created_at)
                     VALUES
                        (:title, :slug, \'event_flash\', \'auto\', :status, 1,
                         :flash_start_at, :flash_end_at, :xp_multiplier, :xp_participation,
                         :cover_emoji, :description, NOW())'
                );
                $stmt->execute([
                    ':title'            => $raw_title,
                    ':slug'             => $slug,
                    ':status'           => in_array($_POST['status'] ?? '', ['active', 'draft']) ? $_POST['status'] : 'draft',
                    ':flash_start_at'   => !empty($_POST['flash_start_at']) ? $_POST['flash_start_at'] : null,
                    ':flash_end_at'     => !empty($_POST['flash_end_at'])   ? $_POST['flash_end_at']   : null,
                    ':xp_multiplier'    => in_array($_POST['xp_multiplier'] ?? '', ['1.0', '1.5', '2.0', '3.0']) ? $_POST['xp_multiplier'] : '1.0',
                    ':xp_participation' => (int)($_POST['xp_participation'] ?? 0),
                    ':cover_emoji'      => $_POST['cover_emoji'] ?? '&#9889;',
                    ':description'      => trim($_POST['description'] ?? ''),
                ]);
                $flash_message = 'Événement flash créé avec succès.';
            } catch (PDOException $e) {
                $flash_message = 'Erreur création : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $flash_type    = 'err';
            }

        } elseif ($action === 'toggle_status' && $pdo) {
            $id         = (int)($_POST['id'] ?? 0);
            $new_status = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'draft';
            try {
                $stmt = $pdo->prepare('UPDATE missions SET status = :status WHERE id = :id AND is_flash = 1');
                $stmt->execute([':status' => $new_status, ':id' => $id]);
                $flash_message = 'Statut mis à jour.';
            } catch (PDOException $e) {
                $flash_message = 'Erreur mise à jour du statut.';
                $flash_type    = 'err';
            }

        } elseif ($action === 'delete_flash' && $pdo) {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("UPDATE missions SET status = 'archived' WHERE id = :id AND is_flash = 1");
                $stmt->execute([':id' => $id]);
                $flash_message = 'Événement archivé.';
            } catch (PDOException $e) {
                $flash_message = 'Erreur archivage.';
                $flash_type    = 'err';
            }
        }
    }
}

// ── Lecture liste flash events ────────────────────────────────
$flash_events  = [];
$stats_active  = 0;
$stats_total   = 0;
$stats_running = 0;

$pdo = db();
if ($pdo) {
    try {
        $stmt = $pdo->query(
            'SELECT m.*,
                    (SELECT COUNT(*) FROM participations p WHERE p.mission_id = m.id) AS nb_participations
             FROM missions m
             WHERE m.is_flash = 1
             ORDER BY m.created_at DESC'
        );
        $flash_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($flash_events as $fe) {
            $stats_total++;
            if ($fe['status'] === 'active') {
                $stats_active++;
            }
            if ($fe['status'] === 'active'
                && (empty($fe['flash_end_at'])   || strtotime($fe['flash_end_at'])   > time())
                && (empty($fe['flash_start_at']) || strtotime($fe['flash_start_at']) <= time())) {
                $stats_running++;
            }
        }
    } catch (PDOException $e) {
        $flash_events = [];
    }
}

// ── Helper : temps restant ────────────────────────────────────
function flash_time_remaining(?string $end_at): string {
    if (empty($end_at)) return '';
    $diff = strtotime($end_at) - time();
    if ($diff <= 0) {
        return '<span style="color:#c0392b;font-weight:700">Terminé</span>';
    }
    $h = (int)floor($diff / 3600);
    $m = (int)floor(($diff % 3600) / 60);
    if ($h >= 24) {
        return '<span style="color:#2a9d5c;font-weight:700">' . floor($h / 24) . 'j ' . ($h % 24) . 'h</span>';
    }
    return '<span style="color:#c9962a;font-weight:700">' . $h . 'h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . 'm</span>';
}

require_once '_admin-header.php';
?>

<!-- Page header -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">&#9889; Événements Flash</h1>
    <p class="adm-page-sub">Créez et gérez les flash events Zone85.</p>
  </div>
  <div class="adm-page-actions">
    <button class="btn-adm btn-adm-primary"
            onclick="document.getElementById('create-form').scrollIntoView({behavior:'smooth'})">
      + Nouvel événement flash
    </button>
  </div>
</div>

<?php if ($flash_message): ?>
<div class="adm-flash adm-flash-<?= $flash_type ?>">
  <?= $flash_type === 'ok' ? '&#9989;' : '&#10060;' ?>
  <?= htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="adm-stats">
  <div class="adm-stat">
    <div class="adm-stat-label">Total flash</div>
    <div class="adm-stat-value blue"><?= $stats_total ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Actifs</div>
    <div class="adm-stat-value green"><?= $stats_active ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">En cours</div>
    <div class="adm-stat-value coral"><?= $stats_running ?></div>
  </div>
</div>

<!-- Liste flash events -->
<div class="adm-card" style="margin-bottom:32px">
  <p class="adm-card-title">Flash events</p>

  <?php if (empty($flash_events)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">&#9889;</div>
      <p>Aucun événement flash créé.<br>Utilisez le formulaire ci-dessous pour commencer.</p>
    </div>
  <?php else: ?>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Emoji</th>
            <th>Titre</th>
            <th>Statut</th>
            <th>Début</th>
            <th>Fin / Temps restant</th>
            <th>XP base</th>
            <th>Multiplicateur</th>
            <th>Participations</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($flash_events as $fe): ?>
            <?php
              $is_running = $fe['status'] === 'active'
                  && (empty($fe['flash_end_at'])   || strtotime($fe['flash_end_at'])   > time())
                  && (empty($fe['flash_start_at']) || strtotime($fe['flash_start_at']) <= time());
            ?>
            <tr>
              <td style="font-size:1.4rem"><?= e($fe['cover_emoji'] ?? '&#9889;') ?></td>
              <td>
                <strong><?= e($fe['title']) ?></strong>
                <?php if ($is_running): ?>
                  <span style="display:inline-block;margin-left:6px;background:rgba(234,86,73,.12);color:#ea5649;font-size:.62rem;font-weight:800;padding:2px 7px;border-radius:4px;letter-spacing:.07em;text-transform:uppercase">EN COURS</span>
                <?php endif; ?>
                <?php if (!empty($fe['description'])): ?>
                  <br><span style="font-size:.75rem;color:#6b7f96;font-weight:400"><?= e(mb_substr($fe['description'], 0, 60)) ?>&#8230;</span>
                <?php endif; ?>
              </td>
              <td><span class="adm-badge badge-<?= e($fe['status']) ?>"><?= e($fe['status']) ?></span></td>
              <td style="font-size:.82rem;color:#4a5f73">
                <?= !empty($fe['flash_start_at']) ? date('d/m/Y H:i', strtotime($fe['flash_start_at'])) : '&mdash;' ?>
              </td>
              <td style="font-size:.82rem">
                <?php if (!empty($fe['flash_end_at'])): ?>
                  <?= date('d/m/Y H:i', strtotime($fe['flash_end_at'])) ?>
                  <br><?= flash_time_remaining($fe['flash_end_at']) ?>
                <?php else: ?>
                  <span style="color:#6b7f96">Pas de limite</span>
                <?php endif; ?>
              </td>
              <td style="font-weight:700;color:#c9962a"><?= (int)($fe['xp_participation'] ?? 0) ?> XP</td>
              <td style="font-weight:800;font-size:.95rem">
                &#215;<?= !empty($fe['xp_multiplier']) ? number_format((float)$fe['xp_multiplier'], 1) : '1.0' ?>
              </td>
              <td style="font-weight:700"><?= (int)($fe['nb_participations'] ?? 0) ?></td>
              <td>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">

                  <?php if ($fe['status'] === 'active'): ?>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action"     value="toggle_status">
                      <input type="hidden" name="id"         value="<?= (int)$fe['id'] ?>">
                      <input type="hidden" name="new_status" value="draft">
                      <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm">&#9208; Désactiver</button>
                    </form>
                  <?php else: ?>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action"     value="toggle_status">
                      <input type="hidden" name="id"         value="<?= (int)$fe['id'] ?>">
                      <input type="hidden" name="new_status" value="active">
                      <button type="submit" class="btn-adm btn-adm-success btn-adm-sm">&#9654; Activer</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($fe['status'] !== 'archived'): ?>
                    <form method="post" style="display:inline"
                          onsubmit="return confirm('Archiver cet événement ?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_flash">
                      <input type="hidden" name="id"     value="<?= (int)$fe['id'] ?>">
                      <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm">&#128465;</button>
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

<!-- Formulaire création -->
<div class="adm-card" id="create-form">
  <p class="adm-card-title">Créer un nouvel événement flash</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_flash">
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label" for="fe_title">Titre <span>*</span></label>
        <input type="text" class="adm-input" id="fe_title" name="title"
               required placeholder="Ex : Chasse photo coucher de soleil" maxlength="120">
      </div>

      <div class="adm-field">
        <label class="adm-label" for="fe_emoji">Emoji</label>
        <select class="adm-select" id="fe_emoji" name="cover_emoji">
          <option value="&#9889;">&#9889; Éclair</option>
          <option value="&#127919;">&#127919; Cible</option>
          <option value="&#128248;">&#128248; Photo</option>
          <option value="&#127939;">&#127939; Sprint</option>
          <option value="&#129504;">&#129504; Défi cérébral</option>
          <option value="&#127754;">&#127754; Vague</option>
          <option value="&#127785;&#65039;">&#127785;&#65039; Orage</option>
          <option value="&#129418;">&#129418; Renard</option>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="fe_status">Statut initial</label>
        <select class="adm-select" id="fe_status" name="status">
          <option value="draft">Brouillon</option>
          <option value="active">Actif</option>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="fe_start">Début du flash</label>
        <input type="datetime-local" class="adm-input" id="fe_start" name="flash_start_at">
        <span class="adm-hint">Laisser vide pour démarrer immédiatement</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="fe_end">Fin du flash</label>
        <input type="datetime-local" class="adm-input" id="fe_end" name="flash_end_at">
        <span class="adm-hint">Laisser vide pour aucune limite</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="fe_xp">XP de participation</label>
        <input type="number" class="adm-input" id="fe_xp" name="xp_participation"
               value="50" min="0" max="9999" step="10">
      </div>

      <div class="adm-field">
        <label class="adm-label" for="fe_mult">Multiplicateur XP</label>
        <select class="adm-select" id="fe_mult" name="xp_multiplier">
          <option value="1.0">&#215;1.0 — Normal</option>
          <option value="1.5" selected>&#215;1.5 — Boosté</option>
          <option value="2.0">&#215;2.0 — Double</option>
          <option value="3.0">&#215;3.0 — Triple FLASH</option>
        </select>
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label" for="fe_desc">Description courte</label>
        <textarea class="adm-textarea" id="fe_desc" name="description" rows="3"
                  style="min-height:80px"
                  placeholder="Décrivez brièvement l'objectif de cet événement flash…"></textarea>
      </div>

    </div>
    <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
      <button type="submit" class="btn-adm btn-adm-primary">&#9889; Créer l'événement flash</button>
      <button type="reset"  class="btn-adm btn-adm-ghost">Réinitialiser</button>
    </div>
  </form>
</div>

<?php require_once '_admin-footer.php'; ?>
