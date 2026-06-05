<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$token  = trim($_GET['token'] ?? '');
$status = 'error'; // 'ok' | 'already' | 'error'

if ($token && strlen($token) === 64) {
    $pdo = db();
    if ($pdo) {
        try {
            $s = $pdo->prepare("
                SELECT id, email_verified_at FROM users
                WHERE email_verify_token=:t AND deleted_at IS NULL
                LIMIT 1
            ");
            $s->execute([':t' => $token]);
            $user = $s->fetch();

            if ($user) {
                if ($user['email_verified_at']) {
                    $status = 'already';
                } else {
                    $pdo->prepare("UPDATE users SET email_verified_at=NOW(), email_verify_token=NULL WHERE id=:id")
                        ->execute([':id' => (int)$user['id']]);
                    $status = 'ok';
                    // Rafraîchir la session si connecté
                    if (is_logged_in() && (int)($_SESSION['user']['id'] ?? 0) === (int)$user['id']) {
                        $_SESSION['user']['email_verified'] = true;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('[verify-email] ' . $e->getMessage());
        }
    }
}

$page_title   = 'Confirmation de l\'adresse email';
$page_robots  = 'noindex,nofollow';
$current_page = '';
$page_styles  = '<style>
.ve-page{min-height:100vh;background:var(--beige);padding-top:120px;padding-bottom:72px;display:flex;align-items:flex-start;justify-content:center}
.ve-wrap{width:100%;max-width:480px;padding:0 20px;text-align:center}
.ve-icon{font-size:4rem;margin-bottom:20px}
.ve-title{font-size:1.7rem;font-weight:900;color:var(--navy-dark);margin-bottom:12px}
.ve-text{font-size:.95rem;color:var(--text-muted);line-height:1.6;margin-bottom:28px}
.ve-btn{display:inline-block;padding:13px 32px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;font-size:.95rem;text-decoration:none;transition:all .2s}
.ve-btn:hover{background:var(--primary-dark);transform:translateY(-1px)}
.ve-btn-secondary{display:inline-block;padding:11px 24px;border:2px solid var(--beige-dark);color:var(--text-mid);border-radius:var(--radius);font-weight:700;font-size:.88rem;text-decoration:none;margin-left:10px}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="ve-page">
  <div class="ve-wrap">
    <?php if ($status === 'ok'): ?>
      <div class="ve-icon">✅</div>
      <h1 class="ve-title">Adresse email confirmée !</h1>
      <p class="ve-text">Ton compte Zone85 est désormais vérifié. Tu peux maintenant participer à toutes les activités de la Zone.</p>
      <?php if (is_logged_in()): ?>
        <a href="profil.php" class="ve-btn">Voir mon profil →</a>
        <a href="missions.php" class="ve-btn-secondary">Voir les missions</a>
      <?php else: ?>
        <a href="login.php" class="ve-btn">Se connecter →</a>
      <?php endif; ?>

    <?php elseif ($status === 'already'): ?>
      <div class="ve-icon">👍</div>
      <h1 class="ve-title">Déjà confirmé</h1>
      <p class="ve-text">Cette adresse email est déjà vérifiée. Tu peux te connecter normalement.</p>
      <a href="login.php" class="ve-btn">Se connecter →</a>

    <?php else: ?>
      <div class="ve-icon">⚠️</div>
      <h1 class="ve-title">Lien invalide</h1>
      <p class="ve-text">Ce lien de confirmation est invalide ou a déjà été utilisé.<br>
        Si tu viens de t'inscrire, vérifie ton email ou contacte le support.</p>
      <a href="inscription.php" class="ve-btn">S'inscrire →</a>
      <a href="contact.php" class="ve-btn-secondary">Contacter</a>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
