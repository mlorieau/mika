<?php
// ============================================================
// admin/user-edit.php — Édition complète d'un profil membre
// ============================================================
$admin_current    = 'users';
$admin_page_title = 'Édition membre';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

define('SKIP_MAINTENANCE_CHECK', true);
require_admin();

$pdo   = db();
$flash = null;
$uid   = (int)($_GET['id'] ?? 0);
$me    = (int)(current_user()['id'] ?? 0);

if (!$uid || !$pdo) {
    header('Location: users.php');
    exit;
}

// Charger les clans pour le sélecteur
$clans_list = [];
try {
    $clans_list = $pdo->query("SELECT id, name FROM clans ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

// ═══════════════════════════════════════════════════════════════
// TRAITEMENT DES ACTIONS POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action = $_POST['action'] ?? '';

        // ── Infos de profil ────────────────────────────────────
        if ($action === 'save_profile') {
            $pseudo     = trim($_POST['pseudo'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name  = trim($_POST['last_name'] ?? '');
            $bio        = trim($_POST['bio'] ?? '');
            $clan_id    = (int)($_POST['clan_id'] ?? 0) ?: null;
            $role       = in_array($_POST['role'] ?? '', ['member','moderator','admin']) ? $_POST['role'] : 'member';
            $status     = in_array($_POST['status'] ?? '', ['active','suspended']) ? $_POST['status'] : 'active';

            if (empty($pseudo) || empty($email)) {
                $flash = ['type' => 'err', 'msg' => 'Pseudo et email obligatoires.'];
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $flash = ['type' => 'err', 'msg' => 'Email invalide.'];
            } elseif ($uid === $me && $role !== 'admin') {
                $flash = ['type' => 'err', 'msg' => 'Vous ne pouvez pas changer votre propre rôle.'];
            } else {
                try {
                    $pdo->prepare("UPDATE users SET
                        pseudo=:pseudo, email=:email, first_name=:fn, last_name=:ln,
                        bio=:bio, clan_id=:cid, role=:role, status=:status, updated_at=NOW()
                        WHERE id=:id")
                        ->execute([
                            ':pseudo' => $pseudo, ':email' => $email,
                            ':fn' => $first_name ?: null, ':ln' => $last_name ?: null,
                            ':bio' => $bio ?: null, ':cid' => $clan_id,
                            ':role' => $role, ':status' => $status, ':id' => $uid,
                        ]);
                    $flash = ['type' => 'ok', 'msg' => 'Profil mis à jour.'];
                } catch (PDOException $e) {
                    $flash = ['type' => 'err', 'msg' => str_contains($e->getMessage(), 'Duplicate') ? 'Pseudo ou email déjà utilisé.' : 'Erreur DB.'];
                }
            }
        }

        // ── Gamification XP / niveau ───────────────────────────
        elseif ($action === 'save_gamification') {
            $xp = max(0, (int)($_POST['xp_total'] ?? 0));
            $lvl = get_user_level_from_xp($xp);
            try {
                $pdo->prepare("UPDATE users SET xp_total=:xp, level=:lvl, updated_at=NOW() WHERE id=:id")
                    ->execute([':xp' => $xp, ':lvl' => $lvl, ':id' => $uid]);
                $flash = ['type' => 'ok', 'msg' => "XP mis à jour → {$xp} XP, Niveau {$lvl} (" . get_level_name($lvl) . ")."];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur DB.'];
            }
        }

        // ── Communications & préférences ───────────────────────
        elseif ($action === 'save_comms') {
            $fields = [
                'newsletter_optin' => (int)isset($_POST['newsletter_optin']),
                'notif_missions'   => (int)isset($_POST['notif_missions']),
                'notif_saisons'    => (int)isset($_POST['notif_saisons']),
                'notif_clan'       => (int)isset($_POST['notif_clan']),
                'notif_push'       => (int)isset($_POST['notif_push']),
                'digest_hebdo'     => (int)isset($_POST['digest_hebdo']),
            ];
            try {
                $pdo->prepare("UPDATE users SET
                    newsletter_optin=:nl, notif_missions=:nm, notif_saisons=:ns,
                    notif_clan=:nc, notif_push=:np, digest_hebdo=:dh, updated_at=NOW()
                    WHERE id=:id")
                    ->execute([
                        ':nl' => $fields['newsletter_optin'], ':nm' => $fields['notif_missions'],
                        ':ns' => $fields['notif_saisons'],    ':nc' => $fields['notif_clan'],
                        ':np' => $fields['notif_push'],       ':dh' => $fields['digest_hebdo'],
                        ':id' => $uid,
                    ]);
                $flash = ['type' => 'ok', 'msg' => 'Préférences communications enregistrées.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur DB.'];
            }
        }

        // ── Forcer vérification email ──────────────────────────
        elseif ($action === 'force_verify') {
            try {
                $pdo->prepare("UPDATE users SET email_verified_at=NOW(), email_verify_token=NULL, updated_at=NOW() WHERE id=:id")
                    ->execute([':id' => $uid]);
                $flash = ['type' => 'ok', 'msg' => 'Email marqué comme vérifié.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur DB.'];
            }
        }

        // ── Annuler demande de suppression ─────────────────────
        elseif ($action === 'cancel_delete_request') {
            try {
                $pdo->prepare("UPDATE users SET delete_requested_at=NULL, status='active', updated_at=NOW() WHERE id=:id")
                    ->execute([':id' => $uid]);
                $flash = ['type' => 'ok', 'msg' => 'Demande de suppression annulée, compte réactivé.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur DB.'];
            }
        }

        // ── Envoyer lien reset mot de passe ───────────────────
        elseif ($action === 'send_reset') {
            try {
                $row = $pdo->prepare("SELECT email FROM users WHERE id=:id");
                $row->execute([':id' => $uid]);
                $u = $row->fetch();
                if ($u) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("DELETE FROM password_resets WHERE user_id=:id")->execute([':id' => $uid]);
                    $pdo->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (:uid, :em, :tk, DATE_ADD(NOW(), INTERVAL 24 HOUR))")
                        ->execute([':uid' => $uid, ':em' => $u['email'], ':tk' => $token]);
                    $flash = ['type' => 'ok', 'msg' => 'Token de reset généré. Lien : ' . rtrim(BASE_URL, '/') . '/reset-password.php?token=' . $token];
                }
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur DB.'];
            }
        }

        // ── Anonymisation RGPD (suppression douce) ────────────
        elseif ($action === 'anonymize' && $uid !== $me) {
            $anon_email  = 'deleted_' . $uid . '_' . substr(md5(random_bytes(8)), 0, 6) . '@zone85.invalid';
            $anon_pseudo = 'utilisateur_' . $uid;
            try {
                $pdo->prepare("UPDATE users SET
                    email=:em, pseudo=:ps, first_name=NULL, last_name=NULL, bio=NULL,
                    password_hash='', avatar_config=NULL, avatar_file=NULL, avatar_type='preset',
                    newsletter_optin=0, notif_missions=0, notif_saisons=0, notif_clan=0, notif_push=0, digest_hebdo=0,
                    status='deleted', deleted_at=NOW(), delete_requested_at=NULL,
                    email_verified_at=NULL, email_verify_token=NULL, updated_at=NOW()
                    WHERE id=:id")
                    ->execute([':em' => $anon_email, ':ps' => $anon_pseudo, ':id' => $uid]);
                // Supprimer les tokens de reset
                $pdo->prepare("DELETE FROM password_resets WHERE user_id=:id")->execute([':id' => $uid]);
                $flash = ['type' => 'ok', 'msg' => 'Compte anonymisé (RGPD). Les données personnelles ont été effacées.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'anonymisation.'];
            }
        }

        // ── Attribuer un badge ─────────────────────────────────
        elseif ($action === 'award_badge') {
            $bid = (int)($_POST['badge_id'] ?? 0);
            if ($bid > 0) {
                try {
                    $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id, source_type, awarded_by, awarded_at)
                        VALUES (:uid, :bid, 'admin', :by, NOW())")
                        ->execute([':uid' => $uid, ':bid' => $bid, ':by' => $me]);
                    $flash = ['type' => 'ok', 'msg' => 'Badge attribué.'];
                } catch (PDOException $e) {
                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'attribution.'];
                }
            }
        }

        // ── Révoquer un badge ──────────────────────────────────
        elseif ($action === 'revoke_badge') {
            $bid = (int)($_POST['badge_id'] ?? 0);
            if ($bid > 0) {
                try {
                    $pdo->prepare("DELETE FROM user_badges WHERE user_id=:uid AND badge_id=:bid")
                        ->execute([':uid' => $uid, ':bid' => $bid]);
                    $flash = ['type' => 'ok', 'msg' => 'Badge révoqué.'];
                } catch (PDOException $e) {
                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de la révocation.'];
                }
            }
        }

        // ── Suppression définitive ────────────────────────────
        elseif ($action === 'hard_delete' && $uid !== $me) {
            try {
                // article_comments n'a pas de FK → supprimer manuellement
                $pdo->prepare("DELETE FROM article_comments WHERE user_id=:id")->execute([':id' => $uid]);
                // Le reste cascade via FK ON DELETE CASCADE
                $pdo->prepare("DELETE FROM users WHERE id=:id")->execute([':id' => $uid]);
                header('Location: users.php?flash=deleted');
                exit;
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur lors de la suppression : ' . htmlspecialchars($e->getMessage())];
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// CHARGEMENT DU PROFIL
// ═══════════════════════════════════════════════════════════════
$user = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, c.name AS clan_name
        FROM users u
        LEFT JOIN clans c ON c.id = u.clan_id
        WHERE u.id = :id AND u.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $uid]);
    $user = $stmt->fetch();
} catch (PDOException $e) {}

if (!$user) {
    header('Location: users.php');
    exit;
}

// Stats du membre
$stats = ['comments' => 0, 'randos' => 0, 'missions' => 0, 'badges' => 0];
try {
    $stat_queries = [
        'comments' => "SELECT COUNT(*) FROM article_comments WHERE user_id=:id",
        'randos'   => "SELECT COUNT(*) FROM rando_participations WHERE user_id=:id",
        'missions' => "SELECT COUNT(*) FROM participations WHERE user_id=:id",
        'badges'   => "SELECT COUNT(*) FROM user_badges WHERE user_id=:id",
    ];
    foreach ($stat_queries as $key => $sql) {
        $sq = $pdo->prepare($sql);
        $sq->execute([':id' => $uid]);
        $stats[$key] = (int)$sq->fetchColumn();
    }
} catch (PDOException $e) {}

// Badges du membre
$user_badges_list  = [];
$all_badges_list   = [];
$awarded_badge_ids = [];
try {
    $stmt_ub = $pdo->prepare("
        SELECT ub.badge_id, ub.awarded_at, ub.source_type,
               b.title, b.icon_emoji, b.rarity,
               adm.pseudo AS awarded_by_pseudo
        FROM user_badges ub
        JOIN badges b ON b.id = ub.badge_id
        LEFT JOIN users adm ON adm.id = ub.awarded_by
        WHERE ub.user_id = :id
        ORDER BY ub.awarded_at DESC
    ");
    $stmt_ub->execute([':id' => $uid]);
    $user_badges_list  = $stmt_ub->fetchAll();
    $awarded_badge_ids = array_column($user_badges_list, 'badge_id');
    $all_badges_list   = $pdo->query("SELECT id, title, icon_emoji, rarity FROM badges ORDER BY title")->fetchAll();
} catch (PDOException $e) {}

$level     = get_user_level_from_xp((int)$user['xp_total']);
$level_name = get_level_name($level);
$next_threshold = get_level_threshold($level + 1);
$current_threshold = get_level_threshold($level);
$xp_progress = $next_threshold > $current_threshold
    ? min(100, round((((int)$user['xp_total'] - $current_threshold) / ($next_threshold - $current_threshold)) * 100))
    : 100;

require_once '_admin-header.php';
?>

<style>
.ue-back { display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:700;
           color:#6b7f96;text-decoration:none;margin-bottom:18px; }
.ue-back:hover { color:#ea5649; }

.ue-hero { display:flex;gap:20px;align-items:center;margin-bottom:28px;
           background:#fff;border-radius:14px;padding:22px 24px;
           box-shadow:0 2px 10px rgba(12,30,46,.06);border:1px solid rgba(18,49,78,.07); }
.ue-avatar { width:64px;height:64px;border-radius:14px;background:#12314e;
             display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;overflow:hidden; }
.ue-avatar img { width:100%;height:100%;object-fit:cover; }
.ue-hero-name { font-size:1.2rem;font-weight:900;color:#0c1e2e; }
.ue-hero-email { font-size:.8rem;color:#6b7f96;margin-top:2px; }
.ue-hero-meta  { display:flex;gap:10px;flex-wrap:wrap;margin-top:8px; }
.ue-chip { display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;
           font-size:.7rem;font-weight:700; }

.ue-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
@media(max-width:760px){.ue-grid{grid-template-columns:1fr;}}

.ue-section { background:#fff;border-radius:14px;padding:22px 24px;
              box-shadow:0 2px 10px rgba(12,30,46,.06);border:1px solid rgba(18,49,78,.07); }
.ue-section-title { font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:.09em;
                    color:#6b7f96;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f0ece7; }

.ue-field { margin-bottom:14px; }
.ue-label { display:block;font-size:.74rem;font-weight:700;color:#3d5166;margin-bottom:5px; }
.ue-input { width:100%;padding:9px 12px;border:1.5px solid #d0cbc5;border-radius:8px;
            font-family:'Inter',sans-serif;font-size:.84rem;color:#0c1e2e;background:#fff;box-sizing:border-box; }
.ue-input:focus { outline:none;border-color:#ea5649; }
.ue-textarea { resize:vertical;min-height:80px; }
.ue-select { appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='7'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7f96' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
             background-repeat:no-repeat;background-position:right 10px center;padding-right:30px; }
.ue-hint { font-size:.71rem;color:#9aadbc;margin-top:4px; }

.ue-toggle-row { display:flex;align-items:center;justify-content:space-between;
                 padding:10px 0;border-bottom:1px solid #f8f4ef; }
.ue-toggle-row:last-child { border-bottom:none; }
.ue-toggle-label { font-size:.84rem;color:#0f1e2d; }
.ue-toggle-desc  { font-size:.72rem;color:#9aadbc;margin-top:2px; }
.ue-switch { position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0; }
.ue-switch input { opacity:0;width:0;height:0; }
.ue-slider { position:absolute;inset:0;background:#d0cbc5;border-radius:999px;cursor:pointer;transition:.2s; }
.ue-slider::before { content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;
                     background:#fff;border-radius:50%;transition:.2s; }
.ue-switch input:checked + .ue-slider { background:#ea5649; }
.ue-switch input:checked + .ue-slider::before { transform:translateX(18px); }

.ue-rgpd-row { display:flex;align-items:center;justify-content:space-between;
               padding:10px 0;border-bottom:1px solid #f8f4ef; }
.ue-rgpd-row:last-child { border-bottom:none; }
.ue-rgpd-key   { font-size:.8rem;font-weight:700;color:#3d5166; }
.ue-rgpd-val   { font-size:.8rem;color:#0f1e2d; }
.ue-rgpd-ok    { color:#1a7a42; }
.ue-rgpd-warn  { color:#c0392b; }

.ue-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px; }
@media(max-width:600px){.ue-stats{grid-template-columns:repeat(2,1fr);}}
.ue-stat { text-align:center;background:#f8f4ef;border-radius:10px;padding:12px 8px; }
.ue-stat-val { font-size:1.4rem;font-weight:900;color:#0c1e2e; }
.ue-stat-lbl { font-size:.65rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.07em;margin-top:2px; }

.ue-xp-bar { height:8px;background:#f0ece7;border-radius:999px;overflow:hidden;margin:8px 0; }
.ue-xp-fill { height:100%;background:linear-gradient(90deg,#ea5649,#f07066);border-radius:999px;transition:width .4s; }

.ue-danger { background:rgba(192,57,43,.04);border:1.5px solid rgba(192,57,43,.2);border-radius:14px;padding:22px 24px; }
.ue-danger-title { font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:.09em;
                   color:#c0392b;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid rgba(192,57,43,.15); }

.ue-btn { display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;
          font-family:'Inter',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;
          border:none;text-decoration:none;transition:opacity .15s; }
.ue-btn:hover { opacity:.85; }
.ue-btn-primary  { background:#ea5649;color:#fff; }
.ue-btn-ghost    { background:#f0ece7;color:#3d5166;border:1.5px solid #d0cbc5; }
.ue-btn-danger   { background:rgba(192,57,43,.12);color:#c0392b;border:1.5px solid rgba(192,57,43,.3); }
.ue-btn-warn     { background:rgba(201,150,42,.12);color:#8a6020;border:1.5px solid rgba(201,150,42,.3); }
.ue-btn-ok       { background:rgba(42,157,92,.12);color:#1a7a42;border:1.5px solid rgba(42,157,92,.3); }
.ue-btn-sm { padding:6px 12px;font-size:.74rem; }
</style>

<a href="users.php" class="ue-back">← Retour à la liste</a>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>"
     style="margin-bottom:20px;word-break:break-all">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
  <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- Alerte demande de suppression -->
<?php if (!empty($user['delete_requested_at'])): ?>
<div class="adm-flash adm-flash-err" style="margin-bottom:20px">
  ⚠️ <strong>Cet utilisateur a demandé la suppression de son compte</strong> le
  <?= date('d/m/Y à H:i', strtotime($user['delete_requested_at'])) ?>.
  Vous devez traiter cette demande dans la section RGPD ci-dessous.
</div>
<?php endif; ?>

<!-- Hero -->
<div class="ue-hero">
  <div class="ue-avatar">
    <?php
    $av = avatar_url($user);
    if ($av): ?><img src="<?= e($av) ?>" alt=""><?php
    else:
        $cfg   = json_decode($user['avatar_config'] ?? '{}', true) ?? [];
        $emoji = $cfg['emoji'] ?? '🧭';
        echo $emoji;
    endif; ?>
  </div>
  <div style="flex:1;min-width:0">
    <div class="ue-hero-name"><?= e($user['pseudo']) ?></div>
    <div class="ue-hero-email"><?= e($user['email']) ?></div>
    <div class="ue-hero-meta">
      <?php
      $role_colors = ['member'=>'#0369a1','moderator'=>'#6b2fa0','admin'=>'#c0392b'];
      $role_labels = ['member'=>'Membre','moderator'=>'Modérateur','admin'=>'Admin'];
      $rc = $role_colors[$user['role']] ?? '#6b7f96';
      ?>
      <span class="ue-chip" style="background:rgba(0,0,0,.06);color:<?= $rc ?>">
        <?= $role_labels[$user['role']] ?? $user['role'] ?>
      </span>
      <?php if ($user['status'] === 'suspended'): ?>
      <span class="ue-chip" style="background:rgba(192,57,43,.1);color:#c0392b">Suspendu</span>
      <?php endif; ?>
      <?php if ($user['clan_name']): ?>
      <span class="ue-chip" style="background:rgba(18,49,78,.07);color:#12314e"><?= e($user['clan_name']) ?></span>
      <?php endif; ?>
      <span class="ue-chip" style="background:rgba(234,86,73,.1);color:#ea5649">Niv. <?= $level ?> — <?= $level_name ?></span>
      <span class="ue-chip" style="background:rgba(201,150,42,.1);color:#8a6020">⭐ <?= number_format((int)$user['xp_total'], 0, ',', ' ') ?> XP</span>
    </div>
  </div>
  <div style="text-align:right;flex-shrink:0">
    <a href="<?= rtrim(BASE_URL,'/') ?>/profil.php?id=<?= $uid ?>" target="_blank"
       class="ue-btn ue-btn-ghost ue-btn-sm">👁 Voir profil</a>
  </div>
</div>

<!-- Stats activité -->
<div class="ue-stats">
  <div class="ue-stat"><div class="ue-stat-val"><?= $stats['missions'] ?></div><div class="ue-stat-lbl">Missions</div></div>
  <div class="ue-stat"><div class="ue-stat-val"><?= $stats['randos'] ?></div><div class="ue-stat-lbl">Randos</div></div>
  <div class="ue-stat"><div class="ue-stat-val"><?= $stats['comments'] ?></div><div class="ue-stat-lbl">Commentaires</div></div>
  <div class="ue-stat"><div class="ue-stat-val"><?= $stats['badges'] ?></div><div class="ue-stat-lbl">Badges</div></div>
</div>

<!-- ─────────────────────────────────────────────────────────── -->
<!-- Section 1 + 2 en grid -->
<!-- ─────────────────────────────────────────────────────────── -->
<div class="ue-grid" style="margin-bottom:20px">

  <!-- Identité & compte -->
  <div class="ue-section">
    <div class="ue-section-title">👤 Identité & compte</div>
    <form method="POST" action="user-edit.php?id=<?= $uid ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_profile">

      <div class="ue-field">
        <label class="ue-label">Pseudo *</label>
        <input type="text" name="pseudo" class="ue-input" value="<?= e($user['pseudo']) ?>" required maxlength="50">
      </div>
      <div class="ue-field">
        <label class="ue-label">Email *</label>
        <input type="email" name="email" class="ue-input" value="<?= e($user['email']) ?>" required maxlength="180">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="ue-field">
          <label class="ue-label">Prénom</label>
          <input type="text" name="first_name" class="ue-input" value="<?= e($user['first_name'] ?? '') ?>" maxlength="100">
        </div>
        <div class="ue-field">
          <label class="ue-label">Nom</label>
          <input type="text" name="last_name" class="ue-input" value="<?= e($user['last_name'] ?? '') ?>" maxlength="100">
        </div>
      </div>
      <div class="ue-field">
        <label class="ue-label">Bio</label>
        <textarea name="bio" class="ue-input ue-textarea" maxlength="500"><?= e($user['bio'] ?? '') ?></textarea>
      </div>
      <div class="ue-field">
        <label class="ue-label">Clan</label>
        <select name="clan_id" class="ue-input ue-select">
          <option value="">— Aucun clan —</option>
          <?php foreach ($clans_list as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= (int)$user['clan_id'] === (int)$cl['id'] ? 'selected' : '' ?>>
            <?= e($cl['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="ue-field">
          <label class="ue-label">Rôle</label>
          <select name="role" class="ue-input ue-select" <?= $uid === $me ? 'disabled' : '' ?>>
            <option value="member"    <?= $user['role']==='member'    ? 'selected':'' ?>>Membre</option>
            <option value="moderator" <?= $user['role']==='moderator' ? 'selected':'' ?>>Modérateur</option>
            <option value="admin"     <?= $user['role']==='admin'     ? 'selected':'' ?>>Admin</option>
          </select>
          <?php if ($uid === $me): ?>
          <div class="ue-hint">Vous ne pouvez pas modifier votre propre rôle.</div>
          <?php endif; ?>
        </div>
        <div class="ue-field">
          <label class="ue-label">Statut</label>
          <select name="status" class="ue-input ue-select" <?= $uid === $me ? 'disabled' : '' ?>>
            <option value="active"    <?= $user['status']==='active'    ? 'selected':'' ?>>Actif</option>
            <option value="suspended" <?= $user['status']==='suspended' ? 'selected':'' ?>>Suspendu</option>
          </select>
        </div>
      </div>
      <div style="margin-top:18px">
        <button type="submit" class="ue-btn ue-btn-primary">💾 Enregistrer le profil</button>
      </div>
    </form>
  </div>

  <!-- Gamification -->
  <div class="ue-section">
    <div class="ue-section-title">⭐ Gamification</div>
    <form method="POST" action="user-edit.php?id=<?= $uid ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_gamification">
      <div class="ue-field">
        <label class="ue-label">XP total</label>
        <input type="number" name="xp_total" class="ue-input" value="<?= (int)$user['xp_total'] ?>" min="0" max="999999">
        <div class="ue-hint">Le niveau sera recalculé automatiquement.</div>
      </div>
      <div style="margin:16px 0 8px">
        <div style="display:flex;justify-content:space-between;font-size:.78rem">
          <span style="font-weight:700;color:#0c1e2e">Niv. <?= $level ?> — <?= $level_name ?></span>
          <span style="color:#6b7f96"><?= number_format((int)$user['xp_total'],0,',',' ') ?> / <?= $next_threshold === 99999 ? '∞' : number_format($next_threshold,0,',',' ') ?> XP</span>
        </div>
        <div class="ue-xp-bar"><div class="ue-xp-fill" style="width:<?= $xp_progress ?>%"></div></div>
      </div>
      <div style="margin-top:18px">
        <button type="submit" class="ue-btn ue-btn-primary">💾 Mettre à jour XP</button>
      </div>
    </form>

    <div style="margin-top:24px;padding-top:20px;border-top:1px solid #f0ece7">
      <div class="ue-section-title" style="margin-top:0">🔐 Sécurité</div>
      <p style="font-size:.82rem;color:#6b7f96;margin-bottom:14px;line-height:1.6">
        Générer un lien de réinitialisation de mot de passe valable 24h. Copiez le lien et transmettez-le à l'utilisateur.
      </p>
      <form method="POST" action="user-edit.php?id=<?= $uid ?>" onsubmit="return confirm('Générer un lien de reset pour <?= e(addslashes($user['pseudo'])) ?> ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_reset">
        <button type="submit" class="ue-btn ue-btn-warn ue-btn-sm">🔑 Générer lien reset mot de passe</button>
      </form>
    </div>
  </div>

</div><!-- /grid -->

<!-- ─────────────────────────────────────────────────────────── -->
<!-- Section 3 + 4 en grid -->
<!-- ─────────────────────────────────────────────────────────── -->
<div class="ue-grid" style="margin-bottom:20px">

  <!-- Communications & Préférences -->
  <div class="ue-section">
    <div class="ue-section-title">📬 Communications & Préférences</div>
    <p style="font-size:.78rem;color:#9aadbc;margin-bottom:16px;line-height:1.6">
      Ces préférences sont normalement gérées par l'utilisateur depuis son profil. Toute modification admin doit être justifiée
      (ex : demande explicite de l'utilisateur, correction technique).
    </p>
    <form method="POST" action="user-edit.php?id=<?= $uid ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_comms">

      <?php
      $comms = [
          ['newsletter_optin', '📧 Newsletter Zone85', 'Emails marketing, actualités, nouveautés'],
          ['notif_missions',   '🎯 Notifications missions', 'Nouvelles missions, résultats de validation'],
          ['notif_saisons',    '🗓 Notifications saisons', 'Début/fin de saison, classements'],
          ['notif_clan',       '🛡 Notifications clan', 'Activité du clan, points'],
          ['notif_push',       '📱 Push notifications', 'Notifications push navigateur/PWA'],
          ['digest_hebdo',     '📰 Digest hebdomadaire', 'Récapitulatif de la semaine par email'],
      ];
      foreach ($comms as [$field, $label, $desc]):
      ?>
      <div class="ue-toggle-row">
        <div>
          <div class="ue-toggle-label"><?= $label ?></div>
          <div class="ue-toggle-desc"><?= $desc ?></div>
        </div>
        <label class="ue-switch">
          <input type="checkbox" name="<?= $field ?>" value="1" <?= $user[$field] ? 'checked' : '' ?>>
          <span class="ue-slider"></span>
        </label>
      </div>
      <?php endforeach; ?>

      <div style="margin-top:18px">
        <button type="submit" class="ue-btn ue-btn-primary">💾 Enregistrer préférences</button>
      </div>
    </form>
  </div>

  <!-- RGPD & Conformité -->
  <div class="ue-section">
    <div class="ue-section-title">⚖️ RGPD & Conformité</div>

    <div class="ue-rgpd-row">
      <span class="ue-rgpd-key">CGU acceptées</span>
      <span class="ue-rgpd-val <?= $user['accepted_cgu_at'] ? 'ue-rgpd-ok' : 'ue-rgpd-warn' ?>">
        <?= $user['accepted_cgu_at'] ? '✅ ' . date('d/m/Y H:i', strtotime($user['accepted_cgu_at'])) : '❌ Non acceptées' ?>
      </span>
    </div>
    <div class="ue-rgpd-row">
      <span class="ue-rgpd-key">Politique vie privée</span>
      <span class="ue-rgpd-val <?= $user['accepted_privacy_at'] ? 'ue-rgpd-ok' : 'ue-rgpd-warn' ?>">
        <?= $user['accepted_privacy_at'] ? '✅ ' . date('d/m/Y H:i', strtotime($user['accepted_privacy_at'])) : '❌ Non acceptée' ?>
      </span>
    </div>
    <div class="ue-rgpd-row">
      <span class="ue-rgpd-key">Email vérifié</span>
      <span class="ue-rgpd-val <?= $user['email_verified_at'] ? 'ue-rgpd-ok' : 'ue-rgpd-warn' ?>">
        <?= $user['email_verified_at'] ? '✅ ' . date('d/m/Y', strtotime($user['email_verified_at'])) : '⚠️ Non vérifié' ?>
      </span>
    </div>
    <div class="ue-rgpd-row">
      <span class="ue-rgpd-key">Inscription</span>
      <span class="ue-rgpd-val"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></span>
    </div>
    <div class="ue-rgpd-row">
      <span class="ue-rgpd-key">Dernière connexion</span>
      <span class="ue-rgpd-val"><?= $user['last_login_at'] ? date('d/m/Y H:i', strtotime($user['last_login_at'])) : '—' ?></span>
    </div>
    <div class="ue-rgpd-row">
      <span class="ue-rgpd-key">ID interne</span>
      <span class="ue-rgpd-val" style="font-family:monospace;font-size:.75rem">#<?= $uid ?></span>
    </div>
    <?php if ($user['delete_requested_at']): ?>
    <div class="ue-rgpd-row" style="background:rgba(192,57,43,.04);margin:0 -24px;padding:10px 24px">
      <span class="ue-rgpd-key ue-rgpd-warn">⚠️ Suppression demandée</span>
      <span class="ue-rgpd-val ue-rgpd-warn"><?= date('d/m/Y H:i', strtotime($user['delete_requested_at'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
      <?php if (!$user['email_verified_at']): ?>
      <form method="POST" action="user-edit.php?id=<?= $uid ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="force_verify">
        <button type="submit" class="ue-btn ue-btn-ok ue-btn-sm">✅ Forcer vérification email</button>
      </form>
      <?php endif; ?>

      <?php if ($user['delete_requested_at'] && $uid !== $me): ?>
      <form method="POST" action="user-edit.php?id=<?= $uid ?>" onsubmit="return confirm('Annuler la demande de suppression et réactiver le compte ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel_delete_request">
        <button type="submit" class="ue-btn ue-btn-warn ue-btn-sm">↩ Annuler la demande</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /grid -->

<!-- ─────────────────────────────────────────────────────────── -->
<!-- Section Badges -->
<!-- ─────────────────────────────────────────────────────────── -->
<div class="ue-section" style="margin-bottom:20px">
  <div class="ue-section-title">🏅 Badges (<?= count($user_badges_list) ?>)</div>

  <?php if (empty($user_badges_list)): ?>
  <p style="font-size:.83rem;color:#9aadbc;margin:0">Aucun badge débloqué.</p>
  <?php else: ?>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px">
    <?php foreach ($user_badges_list as $b):
      $rarity_colors = ['common'=>'#6b7f96','uncommon'=>'#1a7a42','rare'=>'#0369a1','epic'=>'#6b2fa0','legendary'=>'#c0392b'];
      $rc = $rarity_colors[$b['rarity'] ?? 'common'] ?? '#6b7f96';
    ?>
    <div style="display:flex;align-items:center;gap:8px;background:#f8f4ef;border-radius:10px;padding:9px 13px;
                border:1.5px solid #e8e4df;min-width:0">
      <span style="font-size:1.4rem"><?= $b['icon_emoji'] ?: '🏅' ?></span>
      <div style="min-width:0">
        <div style="font-size:.8rem;font-weight:800;color:#0c1e2e"><?= e($b['title']) ?></div>
        <div style="font-size:.67rem;color:<?= $rc ?>;font-weight:700;text-transform:uppercase;letter-spacing:.06em"><?= $b['rarity'] ?? 'common' ?></div>
        <div style="font-size:.67rem;color:#9aadbc"><?= date('d/m/Y', strtotime($b['awarded_at'])) ?>
          <?= $b['source_type'] === 'admin' ? ' · <em>Admin</em>' : '' ?></div>
      </div>
      <form method="POST" action="user-edit.php?id=<?= $uid ?>" style="margin:0;margin-left:auto"
            onsubmit="return confirm('Révoquer le badge « <?= e(addslashes($b['title'])) ?> » ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="revoke_badge">
        <input type="hidden" name="badge_id" value="<?= (int)$b['badge_id'] ?>">
        <button type="submit" class="ue-btn ue-btn-danger ue-btn-sm" title="Révoquer" style="padding:4px 8px;font-size:.7rem">✕</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Attribution manuelle -->
  <?php $available = array_filter($all_badges_list, fn($b) => !in_array($b['id'], $awarded_badge_ids)); ?>
  <?php if (!empty($available)): ?>
  <form method="POST" action="user-edit.php?id=<?= $uid ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:14px;border-top:1px solid #f0ece7">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="award_badge">
    <select name="badge_id" class="ue-input ue-select" style="flex:1;min-width:200px;max-width:360px">
      <option value="">— Choisir un badge à attribuer —</option>
      <?php foreach ($available as $b): ?>
      <option value="<?= $b['id'] ?>"><?= $b['icon_emoji'] ?: '🏅' ?> <?= e($b['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="ue-btn ue-btn-ok ue-btn-sm">🏅 Attribuer</button>
  </form>
  <?php else: ?>
  <p style="font-size:.78rem;color:#9aadbc;margin:14px 0 0;padding-top:14px;border-top:1px solid #f0ece7">
    Tous les badges disponibles ont été attribués.
  </p>
  <?php endif; ?>
</div>

<!-- ─────────────────────────────────────────────────────────── -->
<!-- Zone Danger -->
<!-- ─────────────────────────────────────────────────────────── -->
<?php if ($uid !== $me): ?>
<div class="ue-danger">
  <div class="ue-danger-title">⚠️ Zone Danger</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    @media(max-width:600px){style="grid-template-columns:1fr"}

    <!-- Anonymisation RGPD -->
    <div>
      <div style="font-weight:800;font-size:.85rem;color:#0c1e2e;margin-bottom:6px">Anonymisation RGPD</div>
      <p style="font-size:.8rem;color:#6b7f96;line-height:1.6;margin-bottom:14px">
        Efface toutes les données personnelles (email, pseudo, prénom, nom, bio, avatar) et désactive le compte.
        Les participations, XP et badges sont <strong>conservés</strong> sous forme anonyme.
        Action obligatoire pour répondre à une demande de suppression RGPD.
      </p>
      <form method="POST" action="user-edit.php?id=<?= $uid ?>"
            onsubmit="return confirm('ANONYMISATION RGPD\n\nToutes les données personnelles de « <?= e(addslashes($user['pseudo'])) ?> » seront effacées de façon irréversible.\n\nContinuer ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="anonymize">
        <button type="submit" class="ue-btn ue-btn-warn">🔒 Anonymiser (RGPD)</button>
      </form>
    </div>

    <!-- Suppression définitive -->
    <div>
      <div style="font-weight:800;font-size:.85rem;color:#c0392b;margin-bottom:6px">Suppression définitive</div>
      <p style="font-size:.8rem;color:#6b7f96;line-height:1.6;margin-bottom:14px">
        Supprime le compte et <strong>toutes les données associées</strong> : participations, XP, badges, commentaires, KTC, etc.
        Irréversible. Préférez l'anonymisation RGPD dans la plupart des cas.
      </p>
      <form method="POST" action="user-edit.php?id=<?= $uid ?>"
            onsubmit="return confirm('SUPPRESSION DÉFINITIVE\n\nLe compte « <?= e(addslashes($user['pseudo'])) ?> » et TOUTES ses données seront supprimés.\n\nCette action est IRRÉVERSIBLE.\n\nÉcrire « SUPPRIMER » puis confirmer.') && document.getElementById('confirm-delete-<?= $uid ?>').value === 'SUPPRIMER'">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="hard_delete">
        <div style="margin-bottom:10px">
          <input type="text" id="confirm-delete-<?= $uid ?>" placeholder="Tapez SUPPRIMER pour confirmer"
                 class="ue-input" style="font-size:.8rem" autocomplete="off">
        </div>
        <button type="submit" class="ue-btn ue-btn-danger">🗑 Supprimer définitivement</button>
      </form>
    </div>

  </div>
</div>
<?php endif; ?>

<?php require_once '_admin-footer.php'; ?>
