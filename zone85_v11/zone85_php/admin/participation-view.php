<?php
// ============================================================
// admin/participation-view.php — Voir + Valider / Refuser
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current = 'participations';

$part_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($part_id <= 0) {
    header('Location: participations.php');
    exit;
}

$pdo     = db();
$part    = null;
$mission = null;
$member  = null;
$flash_ok  = null;
$flash_err = null;

// Charger la participation avec infos jointes
if ($pdo) {
    try {
        $s = $pdo->prepare("
            SELECT p.*,
                   u.pseudo, u.id AS user_id, u.xp_total,
                   c.slug AS clan_slug, c.name AS clan_name, c.id AS clan_id,
                   m.title AS mission_title, m.mission_type, m.validation_mode,
                   m.xp_participation, m.xp_success,
                   m.clan_points_participation, m.clan_points_success,
                   va.pseudo AS validator_pseudo
            FROM participations p
            JOIN users    u  ON u.id = p.user_id
            JOIN missions m  ON m.id = p.mission_id
            LEFT JOIN clans c   ON c.id  = u.clan_id
            LEFT JOIN users va  ON va.id = p.validated_by
            WHERE p.id = :id
            LIMIT 1
        ");
        $s->execute([':id' => $part_id]);
        $part = $s->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/part-view] ' . $e->getMessage());
    }
}

if (!$part) {
    header('Location: participations.php');
    exit;
}

$admin_page_title = 'Participation #' . $part_id;
$is_pending = $part['status'] === 'pending';

// ── Traitement POST (valider / refuser) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_err = 'Token CSRF invalide. Recharge la page.';
    } elseif (!$is_pending) {
        $flash_err = 'Action impossible : cette participation a déjà été traitée (' . $part['status'] . ').';
    } else {
        $action   = $_POST['action'] ?? '';
        $admin_id = (int)(current_user()['id'] ?? 0);

        if ($action === 'validate') {
            $result = admin_validate_participation($part_id, $admin_id);
            if ($result['ok']) {
                $flash_ok = 'Participation validée. ' .
                    ($result['xp_awarded'] > 0  ? '+' . $result['xp_awarded']  . ' XP attribués. ' : '') .
                    ($result['clan_pts']  > 0   ? '+' . $result['clan_pts']   . ' pts clan attribués.' : '');
                // Recharger
                $s = $pdo->prepare("SELECT p.*,u.pseudo,u.id AS user_id,u.xp_total,c.slug AS clan_slug,c.name AS clan_name,c.id AS clan_id,m.title AS mission_title,m.mission_type,m.validation_mode,m.xp_participation,m.xp_success,m.clan_points_participation,m.clan_points_success,va.pseudo AS validator_pseudo FROM participations p JOIN users u ON u.id=p.user_id JOIN missions m ON m.id=p.mission_id LEFT JOIN clans c ON c.id=u.clan_id LEFT JOIN users va ON va.id=p.validated_by WHERE p.id=:id LIMIT 1");
                $s->execute([':id' => $part_id]);
                $part = $s->fetch() ?: $part;
                $is_pending = false;
            } else {
                $flash_err = $result['error'] ?? 'Erreur lors de la validation.';
            }

        } elseif ($action === 'reject') {
            $result = admin_reject_participation($part_id, $admin_id);
            if ($result['ok']) {
                $flash_ok = 'Participation refusée.';
                $s = $pdo->prepare("SELECT p.*,u.pseudo,u.id AS user_id,u.xp_total,c.slug AS clan_slug,c.name AS clan_name,c.id AS clan_id,m.title AS mission_title,m.mission_type,m.validation_mode,m.xp_participation,m.xp_success,m.clan_points_participation,m.clan_points_success,va.pseudo AS validator_pseudo FROM participations p JOIN users u ON u.id=p.user_id JOIN missions m ON m.id=p.mission_id LEFT JOIN clans c ON c.id=u.clan_id LEFT JOIN users va ON va.id=p.validated_by WHERE p.id=:id LIMIT 1");
                $s->execute([':id' => $part_id]);
                $part = $s->fetch() ?: $part;
                $is_pending = false;
            } else {
                $flash_err = $result['error'] ?? 'Erreur lors du refus.';
            }
        } else {
            $flash_err = 'Action inconnue.';
        }
    }
}

// Clan icon helper
$clan_icons = ['bocage'=>'🌳','littoral'=>'⚓','marais'=>'🌿'];

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Participation #<?= $part_id ?></h1>
    <p class="adm-page-sub">
      <span class="adm-badge badge-<?= e($part['status']) ?>"><?= e($part['status']) ?></span>
      — <?= e($part['pseudo']) ?> — <?= e($part['mission_title']) ?>
    </p>
  </div>
  <div class="adm-page-actions">
    <a href="participations.php" class="btn-adm btn-adm-ghost">← Retour</a>
    <a href="participations.php?status=pending" class="btn-adm btn-adm-ghost">En attente</a>
  </div>
</div>

<?php if ($flash_ok): ?>
<div class="adm-flash adm-flash-ok">✅ <?= e($flash_ok) ?></div>
<?php endif; ?>
<?php if ($flash_err): ?>
<div class="adm-flash adm-flash-err">⚠️ <?= e($flash_err) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

  <!-- Colonne principale -->
  <div>

    <!-- Détails membre + mission -->
    <div class="adm-card">
      <div class="adm-card-title">Détails de la participation</div>

      <div class="adm-detail-row">
        <span class="adm-detail-label">Membre</span>
        <span class="adm-detail-value"><?= e($part['pseudo']) ?></span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Clan</span>
        <span class="adm-detail-value">
          <?= ($clan_icons[$part['clan_slug']] ?? '') ?> <?= e($part['clan_name'] ?? $part['clan_slug'] ?? '—') ?>
        </span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Mission</span>
        <span class="adm-detail-value">
          <a href="../mission.php?id=<?= (int)$part['mission_id'] ?>" target="_blank"
             style="color:#ea5649;text-decoration:none;font-weight:700">
            <?= e($part['mission_title']) ?> ↗
          </a>
        </span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Type de mission</span>
        <span class="adm-detail-value">
          <?= mission_type_icon($part['mission_type']) ?> <?= e(mission_type_label($part['mission_type'])) ?>
        </span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Mode validation</span>
        <span class="adm-detail-value">
          <span class="adm-badge badge-<?= e($part['validation_mode']) ?>"><?= e($part['validation_mode']) ?></span>
        </span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Date de participation</span>
        <span class="adm-detail-value"><?= e(substr($part['created_at'], 0, 16)) ?></span>
      </div>
      <?php if ($part['validated_at']): ?>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Traité le</span>
        <span class="adm-detail-value"><?= e(substr($part['validated_at'], 0, 16)) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($part['validator_pseudo']): ?>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Traité par</span>
        <span class="adm-detail-value"><?= e($part['validator_pseudo']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Réponse / Commentaire -->
    <?php if (!empty($part['answer_text'])): ?>
    <div class="adm-card">
      <div class="adm-card-title">💬 Réponse du membre</div>
      <div style="background:#f8f4ef;border-radius:10px;padding:16px 18px;font-size:.9rem;color:#3d5166;line-height:1.7;white-space:pre-wrap"><?= e($part['answer_text']) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($part['comment'])): ?>
    <div class="adm-card">
      <div class="adm-card-title">📝 Commentaire</div>
      <div style="background:#f8f4ef;border-radius:10px;padding:16px 18px;font-size:.9rem;color:#3d5166;line-height:1.7"><?= e($part['comment']) ?></div>
    </div>
    <?php endif; ?>

    <!-- XP déjà attribués -->
    <div class="adm-card">
      <div class="adm-card-title">Récompenses enregistrées</div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">XP attribués</span>
        <span class="adm-detail-value" style="color:#ea5649;font-size:1.1rem">+<?= (int)$part['xp_awarded'] ?> XP</span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">XP du membre (total actuel)</span>
        <span class="adm-detail-value"><?= number_format((int)$part['xp_total'], 0, ',', ' ') ?> XP</span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Points clan attribués</span>
        <span class="adm-detail-value" style="color:#C9962A;font-size:1.1rem">+<?= (int)$part['clan_points_awarded'] ?> pts</span>
      </div>
    </div>

  </div><!-- /col principale -->

  <!-- Colonne action -->
  <div>

    <!-- Fiche récompenses potentielles -->
    <div class="adm-card">
      <div class="adm-card-title">Récompenses à attribuer</div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">XP réussite</span>
        <span class="adm-detail-value" style="color:#2a9d5c;font-size:1.05rem;font-weight:900">
          +<?= (int)$part['xp_success'] ?> XP
        </span>
      </div>
      <div class="adm-detail-row">
        <span class="adm-detail-label">Pts clan réussite</span>
        <span class="adm-detail-value" style="color:#C9962A;font-size:1.05rem;font-weight:900">
          +<?= (int)$part['clan_points_success'] ?> pts
        </span>
      </div>
      <?php if ((int)$part['xp_success'] === 0 && (int)$part['clan_points_success'] === 0): ?>
      <p style="font-size:.75rem;color:#6b7f96;margin:10px 0 0;line-height:1.5">
        Cette mission ne prévoit pas de XP réussite supplémentaires.
      </p>
      <?php endif; ?>
    </div>

    <!-- Statut actuel -->
    <div class="adm-card">
      <div class="adm-card-title">Statut actuel</div>
      <div style="text-align:center;padding:12px 0">
        <span class="adm-badge badge-<?= e($part['status']) ?>"
              style="font-size:.85rem;padding:8px 18px">
          <?= e($part['status']) ?>
        </span>
      </div>
    </div>

    <!-- Actions -->
    <?php if ($is_pending): ?>
    <div class="adm-card" style="border:2px solid rgba(234,86,73,.2);background:linear-gradient(180deg,#fff,#fff8f7)">
      <div class="adm-card-title">Actions admin</div>
      <p style="font-size:.8rem;color:#6b7f96;margin-bottom:18px;line-height:1.6">
        <strong>Valider</strong> : attribue les XP réussite et points clan.<br>
        <strong>Refuser</strong> : marque la participation comme refusée, sans XP supplémentaires.
      </p>
      <form method="POST" id="action-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="action-field" value="">
        <div style="display:flex;flex-direction:column;gap:10px">
          <button type="button" class="btn-adm btn-adm-success"
                  onclick="submitAction('validate', 'Valider cette participation ?')"
                  style="min-height:48px;font-size:1rem">
            ✅ Valider
            <?php if ((int)$part['xp_success'] > 0): ?>
            <span style="opacity:.75;font-size:.82rem">+<?= (int)$part['xp_success'] ?> XP</span>
            <?php endif; ?>
          </button>
          <button type="button" class="btn-adm btn-adm-danger"
                  onclick="submitAction('reject', 'Refuser cette participation ?')">
            ✗ Refuser
          </button>
        </div>
      </form>
    </div>
    <?php elseif ($part['status'] === 'validated' || $part['status'] === 'auto_validated'): ?>
    <div class="adm-card" style="border:1.5px solid rgba(42,157,92,.25)">
      <div style="text-align:center;padding:8px 0">
        <div style="font-size:2rem;margin-bottom:8px">✅</div>
        <div style="font-weight:800;color:#1a7a42;margin-bottom:4px">Déjà validée</div>
        <div style="font-size:.78rem;color:#6b7f96">
          Les XP et points clan ont été attribués.<br>
          Aucune action supplémentaire possible.
        </div>
      </div>
    </div>
    <?php elseif ($part['status'] === 'rejected'): ?>
    <div class="adm-card" style="border:1.5px solid rgba(234,86,73,.25)">
      <div style="text-align:center;padding:8px 0">
        <div style="font-size:2rem;margin-bottom:8px">✗</div>
        <div style="font-weight:800;color:#c0392b;margin-bottom:4px">Participation refusée</div>
        <div style="font-size:.78rem;color:#6b7f96">
          Aucun XP réussite ni points clan attribués.
        </div>
      </div>
    </div>
    <?php endif; ?>

    <a href="participations.php?status=pending" class="btn-adm btn-adm-ghost" style="width:100%;text-align:center;margin-top:8px">
      ← Retour aux pending
    </a>

  </div><!-- /col action -->

</div><!-- /grid -->

<?php
$admin_scripts = '<script>
function submitAction(action, confirmMsg) {
  if (!confirm(confirmMsg)) return;
  document.getElementById("action-field").value = action;
  document.getElementById("action-form").submit();
}
</script>';
require_once '_admin-footer.php';
?>
