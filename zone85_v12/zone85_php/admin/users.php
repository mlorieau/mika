<?php
// ============================================================
// admin/users.php — Gestion Utilisateurs
// ============================================================
define('SKIP_MAINTENANCE_CHECK', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current    = 'users';
$admin_page_title = 'Utilisateurs';

$pdo = db();

// Flash depuis user-edit (après suppression)
$flash = null;
if (($_GET['flash'] ?? '') === 'deleted') {
    $flash = ['type' => 'ok', 'msg' => 'Compte supprimé définitivement.'];
}

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action  = $_POST['action'] ?? '';
        $target  = (int)($_POST['user_id'] ?? 0);
        $me      = (int)(current_user()['id'] ?? 0);

        if ($target && $target !== $me) {
            try {
                if ($action === 'set_role') {
                    $new_role = in_array($_POST['role'] ?? '', ['member','moderator','admin']) ? $_POST['role'] : 'member';
                    $pdo->prepare("UPDATE users SET role=:r, updated_at=NOW() WHERE id=:id")
                        ->execute([':r' => $new_role, ':id' => $target]);
                    $flash = ['type' => 'ok', 'msg' => 'Rôle mis à jour.'];
                } elseif ($action === 'suspend') {
                    $pdo->prepare("UPDATE users SET status='suspended', updated_at=NOW() WHERE id=:id AND status='active'")
                        ->execute([':id' => $target]);
                    $flash = ['type' => 'ok', 'msg' => 'Compte suspendu.'];
                } elseif ($action === 'reactivate') {
                    $pdo->prepare("UPDATE users SET status='active', updated_at=NOW() WHERE id=:id AND status='suspended'")
                        ->execute([':id' => $target]);
                    $flash = ['type' => 'ok', 'msg' => 'Compte réactivé.'];
                }
            } catch (PDOException $e) {
                error_log('[admin/users POST] ' . $e->getMessage());
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'action.'];
            }
        } else {
            $flash = ['type' => 'err', 'msg' => 'Action impossible sur cet utilisateur.'];
        }
    }
}

// ── Filtres ────────────────────────────────────────────────────
$f_clan       = $_GET['clan']       ?? '';
$f_role       = $_GET['role']       ?? '';
$f_status     = $_GET['status']     ?? 'active';
$f_newsletter = $_GET['newsletter'] ?? '';
$f_search     = trim($_GET['q']     ?? '');
$page         = max(1, (int)($_GET['p'] ?? 1));
$per_page     = 30;
$offset       = ($page - 1) * $per_page;

// ── Requête ────────────────────────────────────────────────────
$users        = [];
$total        = 0;
$clans_list   = [];

if ($pdo) {
    try {
        // Clans pour le filtre
        $clans_list = $pdo->query("SELECT id, name, slug FROM clans ORDER BY name")->fetchAll();

        // Construction WHERE
        $where  = ['u.deleted_at IS NULL'];
        $params = [];

        if ($f_status && in_array($f_status, ['active','suspended','pending_delete'])) {
            $where[] = 'u.status = :status';
            $params[':status'] = $f_status;
        }
        if ($f_clan) {
            $where[] = 'c.slug = :clan';
            $params[':clan'] = $f_clan;
        }
        if ($f_role && in_array($f_role, ['member','moderator','admin'])) {
            $where[] = 'u.role = :role';
            $params[':role'] = $f_role;
        }
        if ($f_newsletter !== '') {
            $where[] = 'u.newsletter_optin = :nl';
            $params[':nl'] = (int)$f_newsletter;
        }
        if ($f_search !== '') {
            $where[] = '(u.pseudo LIKE :q OR u.email LIKE :q OR u.first_name LIKE :q)';
            $params[':q'] = '%' . $f_search . '%';
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total
        $cnt = $pdo->prepare("
            SELECT COUNT(*) FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            {$where_sql}
        ");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        // Liste
        $stmt = $pdo->prepare("
            SELECT u.id, u.pseudo, u.email, u.first_name, u.last_name,
                   u.role, u.status, u.xp_total, u.level,
                   u.newsletter_optin, u.avatar_type, u.avatar_config, u.avatar_file,
                   u.created_at, u.last_login_at, u.pwa_installed_at,
                   u.delete_requested_at, u.email_verified_at, u.accepted_cgu_at,
                   c.name AS clan_name, c.slug AS clan_slug
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            {$where_sql}
            ORDER BY u.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[admin/users] ' . $e->getMessage());
    }
}

$total_pages = $total ? (int)ceil($total / $per_page) : 1;
$csrf = csrf_token();
require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">👥 Membres</h1>
    <p class="adm-page-sub"><?= number_format($total, 0, ',', ' ') ?> membre<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?></p>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?> <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" action="users.php" class="adm-filters" id="filter-form">
  <input type="text" name="q" value="<?= e($f_search) ?>" placeholder="Pseudo ou email…" style="min-width:180px">

  <select name="clan" onchange="this.form.submit()">
    <option value="">Tous les clans</option>
    <?php foreach ($clans_list as $cl): ?>
    <option value="<?= e($cl['slug']) ?>" <?= $f_clan === $cl['slug'] ? 'selected' : '' ?>><?= e($cl['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="role" onchange="this.form.submit()">
    <option value="">Tous les rôles</option>
    <option value="member"    <?= $f_role === 'member'    ? 'selected' : '' ?>>Membre</option>
    <option value="moderator" <?= $f_role === 'moderator' ? 'selected' : '' ?>>Modérateur</option>
    <option value="admin"     <?= $f_role === 'admin'     ? 'selected' : '' ?>>Admin</option>
  </select>

  <select name="status" onchange="this.form.submit()">
    <option value="active"       <?= $f_status === 'active'        ? 'selected' : '' ?>>Actifs</option>
    <option value="suspended"    <?= $f_status === 'suspended'      ? 'selected' : '' ?>>Suspendus</option>
    <option value="pending_delete" <?= $f_status === 'pending_delete' ? 'selected' : '' ?>>Suppression demandée</option>
    <option value=""             <?= $f_status === ''               ? 'selected' : '' ?>>Tous statuts</option>
  </select>

  <select name="newsletter" onchange="this.form.submit()">
    <option value="">Newsletter : tous</option>
    <option value="1" <?= $f_newsletter === '1' ? 'selected' : '' ?>>Inscrits newsletter</option>
    <option value="0" <?= $f_newsletter === '0' ? 'selected' : '' ?>>Non inscrits</option>
  </select>

  <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm">🔍 Filtrer</button>
  <a href="users.php" class="btn-adm btn-adm-ghost btn-adm-sm">Réinitialiser</a>
</form>

<!-- Tableau -->
<?php if ($users): ?>
<div class="adm-card" style="padding:0;overflow:hidden">
<div class="adm-table-wrap">
<table class="adm-table">
<thead>
<tr>
  <th>Membre</th>
  <th>Clan</th>
  <th>Rôle</th>
  <th>Niv.</th>
  <th>XP</th>
  <th>Inscription</th>
  <th>Dernière co.</th>
  <th style="text-align:center" title="Newsletter · RGPD · PWA">📬 RGPD</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $u):
    $u_avatar_url = avatar_url($u);
    $u_emoji = '🧭';
    if ($u['avatar_type'] === 'preset') {
        $cfg = json_decode($u['avatar_config'] ?? '{}', true) ?? [];
        $u_emoji = $cfg['emoji'] ?? '🧭';
    }
?>
<tr>
  <!-- Avatar + Pseudo + Email -->
  <td>
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:34px;height:34px;border-radius:8px;overflow:hidden;background:var(--primary);
        display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0">
        <?php if (!empty($u_avatar_url)): ?>
          <img src="<?= e($u_avatar_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= e($u_emoji) ?>
        <?php endif; ?>
      </div>
      <div>
        <div style="font-weight:800;font-size:.84rem;color:#0c1e2e"><?= e($u['pseudo']) ?></div>
        <div style="font-size:.72rem;color:#6b7f96"><?= e($u['email']) ?></div>
        <?php if (!empty($u['delete_requested_at'])): ?>
        <span style="font-size:.64rem;font-weight:700;color:#c0392b;background:rgba(192,57,43,.1);
          padding:1px 6px;border-radius:4px">Suppression demandée</span>
        <?php endif; ?>
      </div>
    </div>
  </td>

  <!-- Clan -->
  <td>
    <?php if ($u['clan_slug']): ?>
    <span class="adm-badge" style="background:rgba(18,49,78,.08);color:#12314e">
      <?= e($u['clan_name'] ?? $u['clan_slug']) ?>
    </span>
    <?php else: ?><span style="color:#6b7f96;font-size:.78rem">—</span><?php endif; ?>
  </td>

  <!-- Rôle -->
  <td>
    <span class="adm-badge badge-<?= e($u['role']) ?>">
      <?= ['member'=>'Membre','moderator'=>'Modérateur','admin'=>'Admin'][$u['role']] ?? e($u['role']) ?>
    </span>
  </td>

  <!-- Niveau -->
  <td style="font-weight:800;color:#0c1e2e"><?= (int)$u['level'] ?></td>

  <!-- XP -->
  <td style="font-size:.84rem;font-weight:700"><?= number_format((int)$u['xp_total'], 0, ',', ' ') ?></td>

  <!-- Inscription -->
  <td style="font-size:.76rem;color:#6b7f96;white-space:nowrap">
    <?= $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
  </td>

  <!-- Dernière connexion -->
  <td style="font-size:.76rem;color:#6b7f96;white-space:nowrap">
    <?= $u['last_login_at'] ? date('d/m/Y', strtotime($u['last_login_at'])) : '—' ?>
  </td>

  <!-- Newsletter · RGPD · PWA -->
  <td style="text-align:center;white-space:nowrap">
    <span title="Newsletter <?= $u['newsletter_optin'] ? 'inscrit' : 'non inscrit' ?>" style="font-size:1.05rem;opacity:<?= $u['newsletter_optin'] ? '1' : '.25' ?>">📧</span>
    <span title="CGU <?= $u['accepted_cgu_at'] ? 'acceptées' : 'non acceptées' ?>" style="font-size:1.05rem;opacity:<?= $u['accepted_cgu_at'] ? '1' : '.25' ?>">⚖️</span>
    <span title="Email <?= $u['email_verified_at'] ? 'vérifié' : 'non vérifié' ?>" style="font-size:1.05rem;opacity:<?= $u['email_verified_at'] ? '1' : '.25' ?>">✉️</span>
    <?php if (!empty($u['pwa_installed_at'])): ?>
    <span title="PWA installée" style="font-size:1.05rem">📱</span>
    <?php endif; ?>
    <?php if (!empty($u['delete_requested_at'])): ?>
    <br><span style="font-size:.64rem;font-weight:800;color:#c0392b;background:rgba(192,57,43,.1);padding:1px 5px;border-radius:4px">🗑 À supprimer</span>
    <?php endif; ?>
  </td>

  <!-- Actions -->
  <td>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <a href="user-edit.php?id=<?= (int)$u['id'] ?>"
         class="btn-adm btn-adm-ghost btn-adm-sm" title="Modifier le profil">✏️ Modifier</a>

      <a href="../profil.php?id=<?= (int)$u['id'] ?>" target="_blank"
         class="btn-adm btn-adm-ghost btn-adm-sm" title="Voir profil public">👁</a>

      <?php if ($u['status'] === 'active' && (int)$u['id'] !== (int)(current_user()['id'] ?? 0)): ?>
      <form method="POST" action="users.php" style="display:inline" onsubmit="return confirm('Suspendre <?= e(addslashes($u['pseudo'])) ?> ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="suspend">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <button type="submit" class="btn-adm btn-adm-ghost btn-adm-sm" style="color:#c0392b;border-color:#c0392b"
          title="Suspendre">⛔</button>
      </form>
      <?php elseif ($u['status'] === 'suspended'): ?>
      <form method="POST" action="users.php" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reactivate">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <button type="submit" class="btn-adm btn-adm-success btn-adm-sm" title="Réactiver">✅</button>
      </form>
      <?php endif; ?>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div><!-- /table-wrap -->
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= min($total_pages, 15); $i++):
    $q_args = http_build_query(array_merge($_GET, ['p' => $i]));
  ?>
  <a href="users.php?<?= $q_args ?>" class="btn-adm btn-adm-ghost btn-adm-sm"
    <?= $i === $page ? 'style="background:#ea5649;color:#fff;border-color:#ea5649"' : '' ?>><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($total_pages > 15): ?>
  <span style="font-size:.78rem;color:#6b7f96;align-self:center">… <?= $total_pages ?> pages</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="adm-card">
  <div class="adm-empty">
    <div class="adm-empty-icon">👥</div>
    <p>Aucun utilisateur trouvé avec ces filtres.</p>
    <a href="users.php" class="btn-adm btn-adm-ghost" style="margin-top:16px">Réinitialiser les filtres</a>
  </div>
</div>
<?php endif; ?>

<!-- Modal changement de rôle -->
<div id="role-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;
  align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;max-width:380px;width:90%;
    box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="font-size:1rem;font-weight:900;color:#0c1e2e;margin-bottom:4px">Changer le rôle</div>
    <div id="role-modal-user" style="font-size:.82rem;color:#6b7f96;margin-bottom:20px"></div>
    <form method="POST" action="users.php" id="role-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_role">
      <input type="hidden" name="user_id" id="role-modal-uid">
      <div style="margin-bottom:16px">
        <select name="role" id="role-modal-select" class="adm-select">
          <option value="member">Membre</option>
          <option value="moderator">Modérateur</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn-adm btn-adm-primary">Confirmer</button>
        <button type="button" class="btn-adm btn-adm-ghost" onclick="closeRoleModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRoleModal(uid, pseudo, role) {
  document.getElementById('role-modal-uid').value = uid;
  document.getElementById('role-modal-user').textContent = pseudo + ' (ID ' + uid + ')';
  document.getElementById('role-modal-select').value = role;
  var m = document.getElementById('role-modal');
  m.style.display = 'flex';
}
function closeRoleModal() {
  document.getElementById('role-modal').style.display = 'none';
}
document.getElementById('role-modal').addEventListener('click', function(e) {
  if (e.target === this) closeRoleModal();
});
</script>

<?php require_once '_admin-footer.php'; ?>
