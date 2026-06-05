<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (is_logged_in()) { header('Location: profil.php'); exit; }

$error   = '';
$success = false;

if (is_post_request()) {
    if (!check_rate_limit('forgot_password', 5, 900)) {
        $error = 'Trop de tentatives. Veuillez patienter 15 minutes.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } else {
            $pdo  = db();
            $user = null;
            if ($pdo) {
                try {
                    $s = $pdo->prepare("SELECT id, pseudo FROM users WHERE email=:e AND status='active' AND deleted_at IS NULL LIMIT 1");
                    $s->execute([':e' => $email]);
                    $user = $s->fetch();
                } catch (PDOException $e) {
                    error_log('[forgot-password] ' . $e->getMessage());
                }
            }

            // Réponse identique que l'email existe ou non (sécurité)
            if ($pdo && $user) {
                $token     = bin2hex(random_bytes(32));
                $expires   = date('Y-m-d H:i:s', time() + 3600); // 1h
                try {
                    // Invalide les anciens tokens
                    $pdo->prepare("DELETE FROM password_resets WHERE user_id=:uid OR (email=:e AND expires_at < NOW())")
                        ->execute([':uid' => (int)$user['id'], ':e' => $email]);
                    $pdo->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (:uid,:e,:t,:exp)")
                        ->execute([':uid' => (int)$user['id'], ':e' => $email, ':t' => $token, ':exp' => $expires]);

                    $reset_url = (defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr')
                               . (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '')
                               . '/reset-password.php?token=' . urlencode($token);

                    require_once 'includes/mailer.php';
                    send_email($email, 'Réinitialisation de votre mot de passe', 'password_reset', [
                        'pseudo'    => $user['pseudo'],
                        'reset_url' => $reset_url,
                    ], $user['pseudo']);
                } catch (PDOException $e) {
                    error_log('[forgot-password] token insert : ' . $e->getMessage());
                }
            }
            $success = true; // toujours true pour ne pas révéler si l'email existe
        }
    }
}

$page_title    = 'Mot de passe oublié';
$page_robots   = 'noindex,nofollow';
$current_page  = '';
$page_styles = '<style>
.fp-page{min-height:100vh;background:var(--beige);padding-top:100px;padding-bottom:72px;display:flex;align-items:flex-start;justify-content:center}
.fp-wrap{width:100%;max-width:440px;padding:0 20px}
.fp-card{background:var(--white);border-radius:var(--radius-lg);padding:44px 36px;box-shadow:var(--shadow-md)}
@media(max-width:480px){.fp-card{padding:28px 20px}}
.fp-overline{font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--primary);margin-bottom:10px;display:block}
.fp-title{font-size:1.8rem;font-weight:900;color:var(--navy-dark);letter-spacing:-.5px;margin-bottom:6px}
.fp-sub{font-size:.9rem;color:var(--text-muted);margin-bottom:28px;line-height:1.5}
.fp-error{background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.3);border-radius:var(--radius);padding:12px 16px;font-size:.85rem;color:#c0392b;margin-bottom:16px;font-weight:600}
.fp-success{background:rgba(42,157,92,.08);border:1px solid rgba(42,157,92,.3);border-radius:var(--radius);padding:16px;font-size:.9rem;color:#1a7a46;line-height:1.6}
.fp-success strong{display:block;font-size:1rem;margin-bottom:6px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.78rem;font-weight:700;color:var(--text-mid);margin-bottom:6px}
.form-input{width:100%;padding:11px 14px;border:2px solid var(--beige-dark);border-radius:var(--radius);font-family:"Inter",sans-serif;font-size:.92rem;color:var(--text);background:var(--beige-light);transition:border-color .2s;outline:none;box-sizing:border-box}
.form-input:focus{border-color:var(--primary);background:#fff}
.fp-btn{width:100%;padding:13px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-family:"Inter",sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;margin-top:8px;transition:all .2s}
.fp-btn:hover{background:var(--primary-dark);transform:translateY(-1px)}
.fp-links{text-align:center;margin-top:20px;font-size:.84rem;color:var(--text-muted)}
.fp-links a{color:var(--primary);font-weight:700;text-decoration:underline}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="fp-page">
  <div class="fp-wrap">
    <div class="fp-card">
      <span class="fp-overline">Espace membre</span>
      <h1 class="fp-title">Mot de passe oublié</h1>

      <?php if ($success): ?>
        <div class="fp-success">
          <strong>Email envoyé !</strong>
          Si cette adresse est associée à un compte Zone85, vous recevrez
          un email avec un lien de réinitialisation valable <strong>1 heure</strong>.
          <br><br>Pensez à vérifier vos courriers indésirables.
        </div>
        <div class="fp-links" style="margin-top:24px">
          <a href="login.php">← Retour à la connexion</a>
        </div>
      <?php else: ?>
        <p class="fp-sub">Renseigne ton adresse email pour recevoir un lien de réinitialisation.</p>

        <?php if ($error): ?>
          <div class="fp-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot-password.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" class="form-input"
                   placeholder="ton@email.fr" autocomplete="email" required autofocus>
          </div>
          <button type="submit" class="fp-btn">Envoyer le lien →</button>
        </form>

        <div class="fp-links">
          <a href="login.php">← Retour à la connexion</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
