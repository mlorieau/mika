<?php
// ── Traitement POST ──────────────────────────────────────────
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Rediriger si déjà connecté
if (is_logged_in()) {
    header('Location: profil.php');
    exit;
}

$error   = '';
$prefill = '';

if (is_post_request()) {
    // Rate limiting : 10 tentatives par IP sur 10 minutes
    if (!check_rate_limit('login', 10, 600)) {
        $error = 'Trop de tentatives. Veuillez patienter quelques minutes.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $user = find_user_by_email($email);
            if ($user && password_verify($password, $user['password_hash'])) {
                reset_rate_limit('login');
                login_user($user);
                $redirect = $_GET['redirect'] ?? 'profil.php';
                // Sécurité : on n'autorise que les redirections relatives
                if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+\.php$/', $redirect)) {
                    $redirect = 'profil.php';
                }
                header('Location: ' . $redirect);
                exit;
            } else {
                $error   = 'Identifiants incorrects.';
                $prefill = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

// ── Rendu HTML ────────────────────────────────────────────────
$page_title       = 'Connexion';
$page_description = 'Connecte-toi à Zone85 pour accéder à ton profil et participer aux missions vendéennes.';
$page_canonical   = 'https://www.zone85.fr/login.php';
$page_robots      = 'noindex,follow';
$current_page     = '';
$page_styles = '<style>
.login-page{min-height:100vh;background:var(--beige);padding-top:100px;padding-bottom:72px;display:flex;align-items:flex-start;justify-content:center}
.login-wrap{width:100%;max-width:440px;padding:0 20px}
.login-card{background:var(--white);border-radius:var(--radius-lg);padding:44px 36px;box-shadow:var(--shadow-md)}
@media(max-width:480px){.login-card{padding:28px 20px}}
.login-logo{font-size:1.3rem;font-weight:900;color:var(--navy-dark);letter-spacing:-.5px;margin-bottom:6px}
.login-logo span{color:var(--primary)}
.login-overline{font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--primary);margin-bottom:10px;display:block}
.login-title{font-size:1.8rem;font-weight:900;color:var(--navy-dark);letter-spacing:-.5px;margin-bottom:6px;line-height:1.2}
.login-sub{font-size:.9rem;color:var(--text-muted);margin-bottom:28px;line-height:1.5}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.78rem;font-weight:700;color:var(--text-mid);margin-bottom:6px;letter-spacing:.03em}
.form-input{width:100%;padding:11px 14px;border:2px solid var(--beige-dark);border-radius:var(--radius);font-family:"Inter",sans-serif;font-size:.92rem;color:var(--text);background:var(--beige-light);transition:border-color .2s,box-shadow .2s;outline:none;box-sizing:border-box}
.form-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(234,86,73,.1);background:#fff}
.pw-wrap{position:relative}
.pw-wrap .form-input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1rem;padding:4px;color:var(--text-muted)}
.pw-toggle:hover{color:var(--text)}
.login-error{background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.3);border-radius:var(--radius);padding:12px 16px;font-size:.85rem;color:#c0392b;margin-bottom:16px;font-weight:600}
.login-btn{width:100%;padding:13px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-family:"Inter",sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;transition:all .2s;margin-top:8px}
.login-btn:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 4px 12px rgba(234,86,73,.35)}
.login-sep{display:flex;align-items:center;gap:12px;margin:20px 0;color:var(--text-muted);font-size:.78rem}
.login-sep::before,.login-sep::after{content:"";flex:1;height:1px;background:var(--beige-dark)}
.login-links{display:flex;flex-direction:column;gap:8px;text-align:center;margin-top:16px;font-size:.84rem;color:var(--text-muted)}
.login-links a{color:var(--primary);font-weight:700;text-decoration:underline}
.login-tagline{text-align:center;margin-top:24px;font-size:.78rem;color:var(--text-muted);font-style:italic;line-height:1.5}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="login-page">
  <div class="login-wrap">
    <div class="login-card">

      <span class="login-overline">Espace membre</span>
      <h1 class="login-title">Connexion</h1>
      <p class="login-sub">Retrouve ta légende. L'Esprit Vendée t'attend.</p>

      <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . htmlspecialchars(urlencode($_GET['redirect']), ENT_QUOTES, 'UTF-8') : '' ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" class="form-input"
                 value="<?= $prefill ?>"
                 placeholder="ton@email.fr" autocomplete="email" required>
        </div>

        <div class="form-group">
          <label for="password">Mot de passe</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" class="form-input"
                   placeholder="Ton mot de passe" autocomplete="current-password" required>
            <button class="pw-toggle" type="button" onclick="togglePw()" tabindex="-1" aria-label="Afficher le mot de passe">👁</button>
          </div>
        </div>

        <button type="submit" class="login-btn">Se connecter →</button>
      </form>

      <div class="login-sep">ou</div>

      <div class="login-links">
        <span>Pas encore de compte ? <a href="inscription.php">Rejoindre la Zone →</a></span>
      </div>

    </div>
    <p class="login-tagline">"Je progresse pour moi. Je fais gagner mon clan."</p>
  </div>
</div>

<script>
function togglePw() {
  const input = document.getElementById('password');
  const btn   = document.querySelector('.pw-toggle');
  if (input.type === 'password') { input.type = 'text'; btn.textContent = '🙈'; }
  else { input.type = 'password'; btn.textContent = '👁'; }
}
document.getElementById('email').focus();
</script>

<?php require_once 'includes/footer.php'; ?>
