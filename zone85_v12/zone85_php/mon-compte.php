<?php
// ============================================================
// mon-compte.php — Page Mon Compte V10
// Infos perso · Avatar · Préférences · RGPD
// ============================================================
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

require_login('login.php');

$user_session = current_user();
$user_id      = (int)$user_session['id'];

// ── Chargement profil DB ──────────────────────────────────────
$pdo  = db();
$user = null;
$legal_rows = [];
$error  = '';
$success = '';

if ($pdo) {
    try {
        $s = $pdo->prepare("
            SELECT u.*, c.slug AS clan_slug, c.name AS clan_name
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.id = :id AND u.status = 'active' AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $s->execute([':id' => $user_id]);
        $user = $s->fetch();

        $s2 = $pdo->prepare("
            SELECT document_type, document_version, accepted_at
            FROM legal_acceptances WHERE user_id = :id ORDER BY accepted_at ASC
        ");
        $s2->execute([':id' => $user_id]);
        $legal_rows = $s2->fetchAll();
    } catch (PDOException $e) {
        error_log('[mon-compte] ' . $e->getMessage());
    }
}

if (!$user) {
    // Fallback session
    $user = [
        'id'             => $user_id,
        'pseudo'         => $user_session['pseudo'],
        'email'          => $user_session['email'],
        'avatar_type'    => $user_session['avatar_type'] ?? 'preset',
        'avatar_key'     => $user_session['avatar_key'] ?? '🧭',
        'avatar_config'  => null,
        'avatar_file'    => null,
        'newsletter_optin'=> 0,
        'notif_missions' => 1,
        'notif_saisons'  => 1,
        'notif_clan'     => 1,
        'notif_push'     => 0,
        'digest_hebdo'   => 0,
        'created_at'     => null,
        'clan_slug'      => $user_session['clan_slug'] ?? '',
        'clan_name'      => '',
        'bio'            => '',
    ];
}

// ── Traitement POST ───────────────────────────────────────────
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton de sécurité invalide. Rafraîchis la page.'];
    } else {
        $action = $_POST['action'];

        // ── Infos perso ─────────────────────────────────────
        if ($action === 'update_profile' && $pdo) {
            $new_pseudo = safe_input($_POST['pseudo'] ?? '', 30);
            $new_bio    = safe_input($_POST['bio'] ?? '', 500);

            if (mb_strlen($new_pseudo) < 3) {
                $flash = ['type' => 'err', 'msg' => 'Le pseudo doit faire au moins 3 caractères.'];
            } else {
                // Vérifier unicité pseudo (hors soi-même)
                $chk = $pdo->prepare("SELECT id FROM users WHERE pseudo=:p AND id != :id LIMIT 1");
                $chk->execute([':p' => $new_pseudo, ':id' => $user_id]);
                if ($chk->fetch()) {
                    $flash = ['type' => 'err', 'msg' => 'Ce pseudo est déjà utilisé.'];
                } else {
                    try {
                        $pdo->prepare("UPDATE users SET pseudo=:p, bio=:b, updated_at=NOW() WHERE id=:id")
                            ->execute([':p' => $new_pseudo, ':b' => $new_bio, ':id' => $user_id]);
                        // Mise à jour session
                        $_SESSION['user']['pseudo'] = $new_pseudo;
                        $user['pseudo'] = $new_pseudo;
                        $user['bio']    = $new_bio;
                        $flash = ['type' => 'ok', 'msg' => 'Profil mis à jour.'];
                    } catch (PDOException $e) {
                        error_log('[mon-compte update_profile] ' . $e->getMessage());
                        $flash = ['type' => 'err', 'msg' => 'Erreur lors de la mise à jour.'];
                    }
                }
            }
        }

        // ── Mot de passe ────────────────────────────────────
        if ($action === 'update_password' && $pdo) {
            $pwd_current = $_POST['password_current'] ?? '';
            $pwd_new     = $_POST['password_new'] ?? '';
            $pwd_confirm = $_POST['password_confirm'] ?? '';

            if (strlen($pwd_new) < 8) {
                $flash = ['type' => 'err', 'msg' => 'Le nouveau mot de passe doit faire au moins 8 caractères.'];
            } elseif ($pwd_new !== $pwd_confirm) {
                $flash = ['type' => 'err', 'msg' => 'Les mots de passe ne correspondent pas.'];
            } else {
                // Vérif mot de passe actuel
                $s = $pdo->prepare("SELECT password_hash FROM users WHERE id=:id LIMIT 1");
                $s->execute([':id' => $user_id]);
                $row = $s->fetch();
                if (!$row || !password_verify($pwd_current, $row['password_hash'])) {
                    $flash = ['type' => 'err', 'msg' => 'Mot de passe actuel incorrect.'];
                } else {
                    try {
                        $pdo->beginTransaction();
                        $hash = password_hash($pwd_new, PASSWORD_BCRYPT);
                        $pdo->prepare("UPDATE users SET password_hash=:h, updated_at=NOW() WHERE id=:id")
                            ->execute([':h' => $hash, ':id' => $user_id]);
                        $pdo->commit();
                        $flash = ['type' => 'ok', 'msg' => 'Mot de passe mis à jour.'];
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log('[mon-compte update_password] ' . $e->getMessage());
                        $flash = ['type' => 'err', 'msg' => 'Erreur lors de la mise à jour.'];
                    }
                }
            }
        }

        // ── Avatar emoji ────────────────────────────────────
        if ($action === 'update_avatar_emoji' && $pdo) {
            $emoji  = mb_substr(trim($_POST['avatar_emoji'] ?? '🧭'), 0, 8);
            $config = json_encode(['emoji' => $emoji]);
            try {
                $pdo->prepare("UPDATE users SET avatar_type='preset', avatar_config=:c, updated_at=NOW() WHERE id=:id")
                    ->execute([':c' => $config, ':id' => $user_id]);
                $_SESSION['user']['avatar_type'] = 'preset';
                $_SESSION['user']['avatar_key']  = $emoji;
                $user['avatar_type']   = 'preset';
                $user['avatar_config'] = $config;
                $flash = ['type' => 'ok', 'msg' => 'Avatar mis à jour.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de la mise à jour de l\'avatar.'];
            }
        }

        // ── Upload photo ────────────────────────────────────
        if ($action === 'upload_avatar' && $pdo) {
            if (!empty($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $result = upload_avatar($_FILES['avatar_file']);
                if ($result['ok']) {
                    // Supprimer l'ancienne photo si upload
                    if ($user['avatar_type'] === 'upload' && !empty($user['avatar_file'])) {
                        $old_path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__) . '/') . ltrim($user['avatar_file'], '/');
                        if (file_exists($old_path)) @unlink($old_path);
                    }
                    try {
                        $pdo->prepare("UPDATE users SET avatar_type='upload', avatar_file=:f, updated_at=NOW() WHERE id=:id")
                            ->execute([':f' => $result['path'], ':id' => $user_id]);
                        $_SESSION['user']['avatar_type'] = 'upload';
                        $_SESSION['user']['avatar_key']  = $result['path'];
                        $user['avatar_type'] = 'upload';
                        $user['avatar_file'] = $result['path'];
                        $flash = ['type' => 'ok', 'msg' => 'Photo de profil mise à jour.'];
                    } catch (PDOException $e) {
                        $flash = ['type' => 'err', 'msg' => 'Erreur base de données.'];
                    }
                } else {
                    $flash = ['type' => 'err', 'msg' => $result['error']];
                }
            } else {
                $flash = ['type' => 'err', 'msg' => 'Aucun fichier reçu ou erreur d\'envoi.'];
            }
        }

        // ── Supprimer photo → revenir avatar par défaut ────
        if ($action === 'remove_avatar' && $pdo) {
            if ($user['avatar_type'] === 'upload' && !empty($user['avatar_file'])) {
                $old = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__) . '/') . ltrim($user['avatar_file'], '/');
                if (file_exists($old)) @unlink($old);
            }
            try {
                $pdo->prepare("UPDATE users SET avatar_type='preset', avatar_file=NULL, avatar_config=:c, updated_at=NOW() WHERE id=:id")
                    ->execute([':c' => json_encode(['emoji' => '🧭']), ':id' => $user_id]);
                $_SESSION['user']['avatar_type'] = 'preset';
                $_SESSION['user']['avatar_key']  = '🧭';
                $user['avatar_type']   = 'preset';
                $user['avatar_file']   = null;
                $user['avatar_config'] = json_encode(['emoji' => '🧭']);
                $flash = ['type' => 'ok', 'msg' => 'Photo supprimée. Avatar par défaut restauré.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de la suppression.'];
            }
        }

        // ── Préférences ─────────────────────────────────────
        if ($action === 'update_prefs' && $pdo) {
            $prefs = [
                'newsletter_optin' => isset($_POST['newsletter_optin']) ? 1 : 0,
                'notif_missions'   => isset($_POST['notif_missions'])   ? 1 : 0,
                'notif_saisons'    => isset($_POST['notif_saisons'])    ? 1 : 0,
                'notif_clan'       => isset($_POST['notif_clan'])       ? 1 : 0,
                'notif_push'       => isset($_POST['notif_push'])       ? 1 : 0,
                'digest_hebdo'     => isset($_POST['digest_hebdo'])     ? 1 : 0,
            ];
            try {
                $pdo->prepare("
                    UPDATE users SET
                        newsletter_optin = :nl,
                        notif_missions   = :nm,
                        notif_saisons    = :ns,
                        notif_clan       = :nc,
                        notif_push       = :np,
                        digest_hebdo     = :dh,
                        updated_at       = NOW()
                    WHERE id = :id
                ")->execute([
                    ':nl' => $prefs['newsletter_optin'],
                    ':nm' => $prefs['notif_missions'],
                    ':ns' => $prefs['notif_saisons'],
                    ':nc' => $prefs['notif_clan'],
                    ':np' => $prefs['notif_push'],
                    ':dh' => $prefs['digest_hebdo'],
                    ':id' => $user_id,
                ]);
                foreach ($prefs as $k => $v) { $user[$k] = $v; }
                $flash = ['type' => 'ok', 'msg' => 'Préférences enregistrées.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'enregistrement.'];
            }
        }

        // ── Demande suppression compte ──────────────────────
        if ($action === 'request_delete' && $pdo) {
            try {
                $pdo->prepare("UPDATE users SET delete_requested_at=NOW(), status='pending_delete' WHERE id=:id AND delete_requested_at IS NULL")
                    ->execute([':id' => $user_id]);
                // Email de confirmation
                if (function_exists('queue_email')) {
                    queue_email($user['email'], 'delete_requested', ['pseudo' => $user['pseudo']], $user_id, $user['pseudo'] ?? null);
                }
                $flash = ['type' => 'warn', 'msg' => 'Demande de suppression enregistrée. Ton compte sera anonymisé sous 30 jours. Contacte-nous pour annuler.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de la demande.'];
            }
        }
    }
}

// ── Données affichage ─────────────────────────────────────────
$avatar_url    = avatar_url($user);
$avatar_emoji  = '🧭';
if ($user['avatar_type'] === 'preset') {
    $cfg = json_decode($user['avatar_config'] ?? '{}', true) ?? [];
    $avatar_emoji = $cfg['emoji'] ?? ($user['avatar_key'] ?? '🧭');
}

$_tab = $_GET['tab'] ?? 'profil';
$_tabs = ['profil', 'avatar', 'prefs', 'rgpd'];
if (!in_array($_tab, $_tabs)) $_tab = 'profil';

$csrf = csrf_token();

$page_title    = 'Mon Compte — Zone85';
$current_page  = 'compte';

$page_styles = '<style>
.mc-page { padding-top: 68px; min-height: 100vh; background: var(--beige); }
.mc-layout { max-width: 900px; margin: 0 auto; padding: 40px 24px 80px; }
.mc-hero {
  background: linear-gradient(135deg, var(--navy-dark), #1e3a5f);
  border-radius: var(--radius-lg); padding: 28px 28px 24px;
  display: flex; align-items: center; gap: 20px; margin-bottom: 28px;
  position: relative; overflow: hidden;
}
.mc-hero::after { content:""; position:absolute; top:-30px; right:-30px; width:120px; height:120px;
  border-radius:50%; background:rgba(255,255,255,.04); }
.mc-hero-avatar {
  width: 72px; height: 72px; border-radius: 14px; overflow: hidden;
  background: var(--primary); display: flex; align-items: center;
  justify-content: center; font-size: 2rem; flex-shrink: 0;
  border: 3px solid rgba(255,255,255,.15); position: relative; z-index:1;
}
.mc-hero-avatar img { width:100%; height:100%; object-fit:cover; }
.mc-hero-info { position:relative; z-index:1; }
.mc-hero-name { font-size: 1.4rem; font-weight: 900; color: #fff; letter-spacing:-.4px; margin-bottom:4px; }
.mc-hero-sub  { font-size: .82rem; color: rgba(255,255,255,.55); }
.mc-hero-actions { margin-left: auto; position:relative;z-index:1; }

/* Tabs nav */
.mc-tabs { display:flex; gap:4px; margin-bottom:24px; background:var(--white);
  border-radius: var(--radius-lg); padding:6px; box-shadow:var(--shadow-sm); }
.mc-tab { flex:1; padding:9px 12px; border:none; background:none; border-radius:8px;
  font-family:inherit; font-size:.82rem; font-weight:600; color:var(--text-mid);
  cursor:pointer; transition:all .18s; text-align:center; white-space:nowrap; }
.mc-tab.active { background:var(--primary); color:#fff; font-weight:800; }
.mc-tab:hover:not(.active) { background:var(--beige); color:var(--text); }

/* Cards */
.mc-card { background:var(--white); border-radius:var(--radius-lg);
  box-shadow:var(--shadow-sm); padding:28px; margin-bottom:20px; }
.mc-card-title { font-size:.7rem; font-weight:700; letter-spacing:.12em;
  text-transform:uppercase; color:var(--text-muted); margin-bottom:20px;
  display:flex; align-items:center; gap:8px; }
.mc-panel { display:none; }
.mc-panel.active { display:block; animation:fadeUp .25s ease both; }

/* Form */
.mc-field { margin-bottom:18px; }
.mc-label { display:block; font-size:.76rem; font-weight:700; color:#3d5166;
  margin-bottom:6px; }
.mc-input, .mc-textarea, .mc-select {
  width:100%; padding:10px 14px; border-radius:8px;
  border:1.5px solid var(--beige-dark); font-family:inherit;
  font-size:.88rem; color:var(--text); background:var(--white);
  transition:border-color .15s; box-sizing:border-box;
}
.mc-input:focus, .mc-textarea:focus, .mc-select:focus {
  outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(234,86,73,.1);
}
.mc-textarea { resize:vertical; min-height:80px; }
.mc-hint { font-size:.72rem; color:var(--text-muted); margin-top:4px; }
.mc-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.mc-btn-submit {
  display:inline-flex; align-items:center; gap:6px;
  padding:11px 24px; background:var(--primary); color:#fff;
  border:none; border-radius:8px; font-family:inherit;
  font-size:.88rem; font-weight:800; cursor:pointer; transition:opacity .15s;
}
.mc-btn-submit:hover { opacity:.88; }
.mc-btn-ghost {
  display:inline-flex; align-items:center; gap:6px;
  padding:10px 20px; background:var(--white); color:var(--text);
  border:1.5px solid var(--beige-dark); border-radius:8px; font-family:inherit;
  font-size:.85rem; font-weight:700; cursor:pointer; transition:all .15s;
  text-decoration:none;
}
.mc-btn-ghost:hover { border-color:var(--primary); color:var(--primary); }
.mc-btn-danger {
  display:inline-flex; align-items:center; gap:6px;
  padding:10px 20px; background:transparent; color:#c0392b;
  border:1.5px solid #c0392b; border-radius:8px; font-family:inherit;
  font-size:.85rem; font-weight:700; cursor:pointer; transition:all .15s;
}
.mc-btn-danger:hover { background:#c0392b; color:#fff; }

/* Flash */
.mc-flash { border-radius:10px; padding:14px 18px; margin-bottom:20px;
  font-size:.88rem; font-weight:600; display:flex; align-items:center; gap:10px; }
.mc-flash-ok   { background:rgba(42,157,92,.1); border:1px solid rgba(42,157,92,.25); color:#1a7a42; }
.mc-flash-err  { background:rgba(234,86,73,.08); border:1px solid rgba(234,86,73,.25); color:#c0392b; }
.mc-flash-warn { background:rgba(201,150,42,.1); border:1px solid rgba(201,150,42,.3); color:#8a6020; }

/* Avatar grid */
.avatar-emoji-grid { display:grid; grid-template-columns:repeat(8,1fr); gap:8px; margin:12px 0; }
.avatar-emoji-btn {
  aspect-ratio:1; border-radius:10px; border:2px solid var(--beige-dark);
  background:var(--beige); font-size:1.5rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:all .15s; line-height:1;
}
.avatar-emoji-btn:hover, .avatar-emoji-btn.selected {
  border-color:var(--primary); background:rgba(234,86,73,.08);
  transform:scale(1.08);
}

/* Toggle switch */
.mc-toggle-row { display:flex; align-items:center; justify-content:space-between;
  padding:12px 0; border-bottom:1px solid var(--beige-dark); }
.mc-toggle-row:last-child { border-bottom:none; }
.mc-toggle-label { font-size:.88rem; font-weight:600; color:var(--text); }
.mc-toggle-sub { font-size:.74rem; color:var(--text-muted); margin-top:2px; }
.mc-toggle { position:relative; display:inline-block; width:46px; height:26px; flex-shrink:0; }
.mc-toggle input { opacity:0; width:0; height:0; }
.mc-toggle-slider {
  position:absolute; cursor:pointer; inset:0;
  background:var(--beige-dark); border-radius:999px; transition:.2s;
}
.mc-toggle-slider::before {
  content:""; position:absolute; width:20px; height:20px; left:3px; top:3px;
  background:#fff; border-radius:50%; transition:.2s;
  box-shadow:0 1px 4px rgba(0,0,0,.2);
}
.mc-toggle input:checked + .mc-toggle-slider { background:var(--primary); }
.mc-toggle input:checked + .mc-toggle-slider::before { transform:translateX(20px); }

/* RGPD */
.rgpd-data-row { display:flex; align-items:center; justify-content:space-between;
  padding:11px 0; border-bottom:1px solid var(--beige-dark); font-size:.88rem; }
.rgpd-data-row:last-child { border-bottom:none; }
.rgpd-label { color:var(--text-muted); font-weight:600; min-width:180px; }
.rgpd-val { color:var(--text); font-weight:700; text-align:right; }
.rgpd-ok  { color:#2a9d5c; }
.danger-zone { border:1.5px solid rgba(192,57,43,.3); border-radius:12px; padding:20px 24px;
  background:rgba(192,57,43,.03); }

@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
@media(max-width:600px) {
  .mc-row { grid-template-columns:1fr; }
  .mc-tabs { flex-wrap:wrap; }
  .mc-tab { flex:0 0 calc(50% - 4px); font-size:.78rem; }
  .avatar-emoji-grid { grid-template-columns:repeat(6,1fr); }
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="mc-page">
<div class="mc-layout">

  <!-- Hero -->
  <div class="mc-hero">
    <div class="mc-hero-avatar">
      <?php if (!empty($avatar_url)): ?>
        <img src="<?= e($avatar_url) ?>" alt="<?= e($user['pseudo']) ?>">
      <?php else: ?>
        <?= e($avatar_emoji) ?>
      <?php endif; ?>
    </div>
    <div class="mc-hero-info">
      <div class="mc-hero-name"><?= e($user['pseudo']) ?></div>
      <div class="mc-hero-sub"><?= e($user['email']) ?> · <?= e($user['clan_name'] ?: 'Aucun clan') ?></div>
    </div>
    <div class="mc-hero-actions">
      <a href="profil.php" class="mc-btn-ghost" style="font-size:.78rem">← Mon profil</a>
    </div>
  </div>

  <?php if ($flash): ?>
  <div class="mc-flash mc-flash-<?= e($flash['type']) ?>">
    <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
    <?= e($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="mc-tabs">
    <button class="mc-tab <?= $_tab === 'profil' ? 'active' : '' ?>"
      onclick="switchMcTab('profil')">👤 Informations</button>
    <button class="mc-tab <?= $_tab === 'avatar' ? 'active' : '' ?>"
      onclick="switchMcTab('avatar')">🎨 Avatar</button>
    <button class="mc-tab <?= $_tab === 'prefs' ? 'active' : '' ?>"
      onclick="switchMcTab('prefs')">🔔 Préférences</button>
    <button class="mc-tab <?= $_tab === 'rgpd' ? 'active' : '' ?>"
      onclick="switchMcTab('rgpd')">🔒 Confidentialité</button>
  </div>

  <!-- ══ TAB 1 : INFORMATIONS ══════════════════════════════════ -->
  <div class="mc-panel <?= $_tab === 'profil' ? 'active' : '' ?>" id="mc-panel-profil">

    <!-- Pseudo + Bio -->
    <div class="mc-card">
      <div class="mc-card-title">👤 Informations personnelles</div>
      <form method="POST" action="mon-compte.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="mc-row">
          <div class="mc-field">
            <label class="mc-label" for="mc_pseudo">Pseudo <span style="color:var(--primary)">*</span></label>
            <input class="mc-input" type="text" id="mc_pseudo" name="pseudo"
              value="<?= e($user['pseudo']) ?>" maxlength="30" required autocomplete="username">
            <div class="mc-hint">3 à 30 caractères, visible par tous.</div>
          </div>
          <div class="mc-field">
            <label class="mc-label">Email</label>
            <input class="mc-input" type="email" value="<?= e($user['email']) ?>" disabled
              style="background:var(--beige);cursor:not-allowed">
            <div class="mc-hint">L'email ne peut pas être modifié pour l'instant.</div>
          </div>
        </div>
        <div class="mc-field">
          <label class="mc-label" for="mc_bio">Bio (optionnel)</label>
          <textarea class="mc-textarea" id="mc_bio" name="bio" maxlength="500"
            placeholder="Présente-toi en quelques mots…"><?= e($user['bio'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="mc-btn-submit">Enregistrer</button>
      </form>
    </div>

    <!-- Mot de passe -->
    <div class="mc-card">
      <div class="mc-card-title">🔑 Changer le mot de passe</div>
      <form method="POST" action="mon-compte.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_password">
        <div class="mc-field">
          <label class="mc-label" for="mc_pwd_current">Mot de passe actuel</label>
          <input class="mc-input" type="password" id="mc_pwd_current" name="password_current"
            required autocomplete="current-password">
        </div>
        <div class="mc-row">
          <div class="mc-field">
            <label class="mc-label" for="mc_pwd_new">Nouveau mot de passe</label>
            <input class="mc-input" type="password" id="mc_pwd_new" name="password_new"
              required minlength="8" autocomplete="new-password">
            <div class="mc-hint">8 caractères minimum.</div>
          </div>
          <div class="mc-field">
            <label class="mc-label" for="mc_pwd_confirm">Confirmer</label>
            <input class="mc-input" type="password" id="mc_pwd_confirm" name="password_confirm"
              required minlength="8" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="mc-btn-submit">Modifier le mot de passe</button>
      </form>
    </div>

  </div><!-- /panel profil -->

  <!-- ══ TAB 2 : AVATAR ════════════════════════════════════════ -->
  <div class="mc-panel <?= $_tab === 'avatar' ? 'active' : '' ?>" id="mc-panel-avatar">

    <!-- Avatar actuel -->
    <div class="mc-card">
      <div class="mc-card-title">🖼️ Avatar actuel</div>
      <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px">
        <div style="width:80px;height:80px;border-radius:14px;overflow:hidden;background:var(--primary);
          display:flex;align-items:center;justify-content:center;font-size:2.5rem;
          border:3px solid var(--beige-dark)">
          <?php if (!empty($avatar_url)): ?>
            <img src="<?= e($avatar_url) ?>" alt="<?= e($user['pseudo']) ?>" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <?= e($avatar_emoji) ?>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-size:.88rem;font-weight:700;color:var(--text);margin-bottom:6px">
            <?= $user['avatar_type'] === 'upload' ? '📷 Photo de profil' : '🎭 Emoji' ?>
          </div>
          <?php if ($user['avatar_type'] === 'upload'): ?>
          <form method="POST" action="mon-compte.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="mc-btn-danger" onclick="return confirm('Supprimer la photo et revenir à un avatar ?')"
              style="padding:7px 14px;font-size:.78rem">
              🗑️ Supprimer la photo
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Uploader une photo -->
    <div class="mc-card">
      <div class="mc-card-title">📷 Uploader une photo</div>
      <form method="POST" action="mon-compte.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_avatar">
        <div class="mc-field">
          <label class="mc-label" for="mc_avatar_file">Choisir une photo</label>
          <input class="mc-input" type="file" id="mc_avatar_file" name="avatar_file"
            accept="image/jpeg,image/png,image/webp" required>
          <div class="mc-hint">JPG, PNG ou WebP · Max 2 Mo · Format carré recommandé</div>
        </div>
        <button type="submit" class="mc-btn-submit">Mettre à jour la photo</button>
      </form>
    </div>

    <!-- Choisir un emoji -->
    <div class="mc-card">
      <div class="mc-card-title">🎭 Choisir un emoji</div>
      <form method="POST" action="mon-compte.php" id="emoji-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_avatar_emoji">
        <input type="hidden" name="avatar_emoji" id="selected_emoji" value="<?= e($avatar_emoji) ?>">
        <div class="mc-hint" style="margin-bottom:12px">Clique sur un emoji pour le sélectionner.</div>
        <?php
        $emoji_list = ['🧭','⚔️','🛡️','🦊','🐺','🦅','🐗','🌿','🏄','🎯','🔥','⚡','🌊','🗝️','🏹','🧲',
                       '🦁','🐻','🦋','🌙','☀️','🌲','🗺️','🎭','🎪','🏔️','🌾','🦜','🐬','🦎','🍄','🎸'];
        ?>
        <div class="avatar-emoji-grid" id="emoji-grid">
          <?php foreach ($emoji_list as $em): ?>
          <button type="button" class="avatar-emoji-btn <?= ($em === $avatar_emoji) ? 'selected' : '' ?>"
            data-emoji="<?= e($em) ?>" onclick="selectEmoji(this, '<?= e($em) ?>')"><?= e($em) ?></button>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;display:flex;align-items:center;gap:12px">
          <div id="emoji-preview" style="width:48px;height:48px;border-radius:10px;background:var(--primary);
            display:flex;align-items:center;justify-content:center;font-size:1.8rem">
            <?= e($avatar_emoji) ?>
          </div>
          <button type="submit" class="mc-btn-submit">Choisir cet emoji</button>
        </div>
      </form>
    </div>

  </div><!-- /panel avatar -->

  <!-- ══ TAB 3 : PRÉFÉRENCES ═══════════════════════════════════ -->
  <div class="mc-panel <?= $_tab === 'prefs' ? 'active' : '' ?>" id="mc-panel-prefs">
    <div class="mc-card">
      <div class="mc-card-title">🔔 Préférences de communication</div>
      <form method="POST" action="mon-compte.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_prefs">

        <?php
        $toggle_rows = [
          ['name' => 'newsletter_optin', 'label' => 'Newsletter Zone85',
           'sub'  => 'Actualités, nouvelles fonctionnalités, événements spéciaux.'],
          ['name' => 'notif_missions',   'label' => 'Emails nouvelles missions',
           'sub'  => 'Reçois un email à chaque nouvelle mission disponible.'],
          ['name' => 'notif_saisons',    'label' => 'Emails saisons',
           'sub'  => 'Début de saison, classements finaux, annonces clans.'],
          ['name' => 'notif_clan',       'label' => 'Notifications clan',
           'sub'  => 'Évolutions du classement, messages du clan.'],
          ['name' => 'notif_push',       'label' => 'Notifications push (PWA)',
           'sub'  => 'Notifications sur ton appareil si Zone85 est installée.'],
          ['name' => 'digest_hebdo',     'label' => 'Résumé hebdomadaire',
           'sub'  => 'Un email chaque semaine avec tes XP, missions et classement.'],
        ];
        foreach ($toggle_rows as $tr):
            $checked = !empty($user[$tr['name']]);
        ?>
        <div class="mc-toggle-row">
          <div>
            <div class="mc-toggle-label"><?= $tr['label'] ?></div>
            <div class="mc-toggle-sub"><?= $tr['sub'] ?></div>
          </div>
          <label class="mc-toggle">
            <input type="checkbox" name="<?= $tr['name'] ?>"<?= $checked ? ' checked' : '' ?>>
            <span class="mc-toggle-slider"></span>
          </label>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:20px">
          <button type="submit" class="mc-btn-submit">Enregistrer les préférences</button>
        </div>
      </form>
    </div>
  </div><!-- /panel prefs -->

  <!-- ══ TAB 4 : CONFIDENTIALITÉ / RGPD ═══════════════════════ -->
  <div class="mc-panel <?= $_tab === 'rgpd' ? 'active' : '' ?>" id="mc-panel-rgpd">

    <!-- Données personnelles -->
    <div class="mc-card">
      <div class="mc-card-title">📋 Mes données</div>

      <div class="rgpd-data-row">
        <span class="rgpd-label">Date d'inscription</span>
        <span class="rgpd-val"><?= $user['created_at'] ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?></span>
      </div>
      <div class="rgpd-data-row">
        <span class="rgpd-label">Email</span>
        <span class="rgpd-val"><?= e($user['email']) ?></span>
      </div>
      <div class="rgpd-data-row">
        <span class="rgpd-label">Pseudo</span>
        <span class="rgpd-val"><?= e($user['pseudo']) ?></span>
      </div>
      <div class="rgpd-data-row">
        <span class="rgpd-label">Clan</span>
        <span class="rgpd-val"><?= e($user['clan_name'] ?: 'Aucun') ?></span>
      </div>
      <?php foreach ($legal_rows as $lr): ?>
      <div class="rgpd-data-row">
        <span class="rgpd-label">
          <?= $lr['document_type'] === 'cgu' ? '✅ CGU acceptées' : '✅ Politique de confidentialité' ?>
          <span style="font-size:.72rem;display:block;color:var(--text-muted)"><?= e($lr['document_version']) ?></span>
        </span>
        <span class="rgpd-val rgpd-ok"><?= $lr['accepted_at'] ? date('d/m/Y', strtotime($lr['accepted_at'])) : '✓' ?></span>
      </div>
      <?php endforeach; ?>
      <div class="rgpd-data-row">
        <span class="rgpd-label">Newsletter</span>
        <span class="rgpd-val <?= !empty($user['newsletter_optin']) ? 'rgpd-ok' : '' ?>">
          <?= !empty($user['newsletter_optin']) ? 'Oui — consentie' : 'Non' ?>
        </span>
      </div>

      <div style="margin-top:20px">
        <a href="ajax/account-export.php" class="mc-btn-ghost" style="display:inline-flex">
          📥 Télécharger mes données (JSON)
        </a>
        <div class="mc-hint" style="margin-top:8px">
          Tes données personnelles, XP, participations et badges au format JSON.
        </div>
      </div>
    </div>

    <!-- Zone danger -->
    <div class="mc-card">
      <div class="mc-card-title" style="color:#c0392b">⚠️ Zone de danger</div>
      <div class="danger-zone">
        <div style="font-size:.88rem;font-weight:700;color:var(--text);margin-bottom:8px">
          Demander la suppression de mon compte
        </div>
        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:16px;line-height:1.6">
          Une demande de suppression entraîne l'<strong>anonymisation de ton compte sous 30 jours</strong>
          (soft delete). Tes participations, badges et scores sont conservés sous une identité anonyme
          pour préserver l'intégrité des classements.<br>
          <strong>Cette action peut être annulée en nous contactant avant le délai de 30 jours.</strong>
        </div>
        <?php if (!empty($user['delete_requested_at'])): ?>
        <div style="background:rgba(201,150,42,.1);border:1px solid rgba(201,150,42,.3);
          border-radius:8px;padding:12px 16px;font-size:.84rem;font-weight:600;color:#8a6020">
          ⏳ Demande de suppression enregistrée le <?= date('d/m/Y', strtotime($user['delete_requested_at'])) ?>.
          Contacte-nous pour annuler.
        </div>
        <?php else: ?>
        <form method="POST" action="mon-compte.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="request_delete">
          <button type="submit" class="mc-btn-danger"
            onclick="return confirm('Confirmer la demande de suppression de ton compte ? Cette action peut être annulée sous 30 jours.')">
            🗑️ Demander la suppression de mon compte
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /panel rgpd -->

</div>
</div>

<script>
function switchMcTab(tab) {
  document.querySelectorAll('.mc-tab').forEach(function(b) { b.classList.remove('active'); });
  document.querySelectorAll('.mc-panel').forEach(function(p) { p.classList.remove('active'); });
  var tabBtn = document.querySelector('.mc-tab[onclick*="' + tab + '"]');
  var panel  = document.getElementById('mc-panel-' + tab);
  if (tabBtn)  tabBtn.classList.add('active');
  if (panel)   panel.classList.add('active');
  // MAJ URL sans reload
  var url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  history.replaceState(null, '', url.toString());
}

function selectEmoji(btn, emoji) {
  document.querySelectorAll('.avatar-emoji-btn').forEach(function(b) { b.classList.remove('selected'); });
  btn.classList.add('selected');
  document.getElementById('selected_emoji').value = emoji;
  document.getElementById('emoji-preview').textContent = emoji;
}
</script>

<?php require_once 'includes/footer.php'; ?>
