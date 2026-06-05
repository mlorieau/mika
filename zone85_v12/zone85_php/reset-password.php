<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (is_logged_in()) { header('Location: profil.php'); exit; }

$token    = trim($_GET['token'] ?? '');
$error    = '';
$success  = false;
$valid    = false;
$token_row = null;

// Validation du token (GET et POST)
if ($token) {
    $pdo = db();
    if ($pdo) {
        try {
            $s = $pdo->prepare("
                SELECT pr.*, u.pseudo FROM password_resets pr
                JOIN users u ON u.id = pr.user_id
                WHERE pr.token=:t AND pr.used_at IS NULL AND pr.expires_at > NOW()
                LIMIT 1
            ");
            $s->execute([':t' => $token]);
            $token_row = $s->fetch();
            $valid = (bool)$token_row;
        } catch (PDOException $e) {
            error_log('[reset-password] ' . $e->getMessage());
            $error = 'Erreur serveur. Veuillez réessayer.';
        }
    } else {
        $error = 'Base de données indisponible.';
    }
} else {
    $error = 'Lien invalide ou incomplet.';
}

if (is_post_request() && $valid) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Rechargez la page.';
        $valid = false;
    } else {
        $pwd_new     = $_POST['password_new'] ?? '';
        $pwd_confirm = $_POST['password_confirm'] ?? '';

        if (strlen($pwd_new) < 8) {
            $error = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($pwd_new !== $pwd_confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $hash = password_hash($pwd_new, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash=:h, updated_at=NOW() WHERE id=:id")
                    ->execute([':h' => $hash, ':id' => (int)$token_row['user_id']]);
                $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE token=:t")
                    ->execute([':t' => $token]);
                // Invalide toutes les sessions actives (sécurité)
                $pdo->prepare("DELETE FROM rate_limits WHERE endpoint='login'")
                    ->execute(); // réinitialise le rate limit pour cet utilisateur
                $pdo->commit();
                $success = true;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[reset-password] update : ' . $e->getMessage());
                $error = 'Erreur lors de la mise à jour. Veuillez réessayer.';
            }
        }
    }
}

$page_title   = 'Réinitialisation du mot de passe';
$page_robots  = 'noindex,nofollow';
$current_page = '';
$page_styles = '<style>
.rp-page{min-height:100vh;background:var(--beige);padding-top:100px;padding-bottom:72px;display:flex;align-items:flex-start;justify-content:center}
.rp-wrap{width:100%;max-width:440px;padding:0 20px}
.rp-card{background:var(--white);border-radius:var(--radius-lg);padding:44px 36px;box-shadow:var(--shadow-md)}
@media(max-width:480px){.rp-card{padding:28px 20px}}
.rp-overline{font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--primary);margin-bottom:10px;display:block}
.rp-title{font-size:1.8rem;font-weight:900;color:var(--navy-dark);letter-spacing:-.5px;margin-bottom:6px}
.rp-sub{font-size:.9rem;color:var(--text-muted);margin-bottom:28px;line-height:1.5}
.rp-error{background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.3);border-radius:var(--radius);padding:12px 16px;font-size:.85rem;color:#c0392b;margin-bottom:16px;font-weight:600}
.rp-success{background:rgba(42,157,92,.08);border:1px solid rgba(42,157,92,.3);border-radius:var(--radius);padding:16px;font-size:.9rem;color:#1a7a46;line-height:1.6}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.78rem;font-weight:700;color:var(--text-mid);margin-bottom:6px}
.form-input{width:100%;padding:11px 14px;border:2px solid var(--beige-dark);border-radius:var(--radius);font-family:"Inter",sans-serif;font-size:.92rem;color:var(--text);background:var(--beige-light);transition:border-color .2s;outline:none;box-sizing:border-box}
.form-input:focus{border-color:var(--primary);background:#fff}
.pw-wrap{position:relative}
.pw-wrap .form-input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1rem;padding:4px;color:var(--text-muted)}
.rp-btn{width:100%;padding:13px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-family:"Inter",sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;margin-top:8px;transition:all .2s}
.rp-btn:hover{background:var(--primary-dark);transform:translateY(-1px)}
.rp-links{text-align:center;margin-top:20px;font-size:.84rem;color:var(--text-muted)}
.rp-links a{color:var(--primary);font-weight:700;text-decoration:underline}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="rp-page">
  <div class="rp-wrap">
    <div class="rp-card">
      <span class="rp-overline">Espace membre</span>
      <h1 class="rp-title">Nouveau mot de passe</h1>

      <?php if ($success): ?>
        <div class="rp-success">
          <strong>✓ Mot de passe mis à jour !</strong>
          Tu peux maintenant te connecter avec ton nouveau mot de passe.
        </div>
        <div class="rp-links" style="margin-top:24px">
          <a href="login.php" style="display:inline-block;padding:12px 28px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;text-decoration:none">Se connecter →</a>
        </div>

      <?php elseif (!$valid && !$success): ?>
        <div class="rp-error">
          <?= $error ?: 'Ce lien est invalide ou a expiré (durée de validité : 1h).' ?>
        </div>
        <div class="rp-links">
          <a href="forgot-password.php">Demander un nouveau lien →</a>
        </div>

      <?php else: ?>
        <p class="rp-sub">Bonjour <?= e($token_row['pseudo'] ?? '') ?> — choisis un nouveau mot de passe.</p>

        <?php if ($error): ?>
          <div class="rp-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="reset-password.php?token=<?= urlencode($token) ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

          <div class="form-group">
            <label for="password_new">Nouveau mot de passe</label>
            <div class="pw-wrap">
              <input type="password" id="password_new" name="password_new" class="form-input"
                     placeholder="8 caractères minimum" autocomplete="new-password" required minlength="8">
              <button class="pw-toggle" type="button" onclick="togglePw('password_new',this)" tabindex="-1">👁</button>
            </div>
          </div>

          <div class="form-group">
            <label for="password_confirm">Confirmer le mot de passe</label>
            <div class="pw-wrap">
              <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                     placeholder="Répète ton mot de passe" autocomplete="new-password" required minlength="8">
              <button class="pw-toggle" type="button" onclick="togglePw('password_confirm',this)" tabindex="-1">👁</button>
            </div>
          </div>

          <button type="submit" class="rp-btn">Enregistrer le mot de passe →</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function togglePw(id, btn) {
  var inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
</script>

<?php require_once 'includes/footer.php'; ?>
