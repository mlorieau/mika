<?php
$page_title   = 'Bienvenue dans la Zone !';
$page_robots  = 'noindex,nofollow';
$current_page = '';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

// Accessible aux membres connectés uniquement (redirection post-inscription)
if (!is_logged_in()) {
    header('Location: inscription.php');
    exit;
}

$user         = current_user();
$pseudo       = $user['pseudo'] ?? 'Zonaute';
$has_clan     = !empty($user['clan_id']);
$has_avatar   = ($user['avatar_type'] ?? 'preset') !== 'preset' || !empty($user['avatar_key']);
$missions_done = 0;

$pdo = db();
if ($pdo) {
    try {
        $missions_done = (int)$pdo->prepare(
            "SELECT COUNT(*) FROM participations WHERE user_id=:uid AND status IN ('validated','auto_validated')"
        )->execute([':uid' => (int)$user['id']]) ? 0 : 0;
        $s = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id=:uid AND status IN ('validated','auto_validated')");
        $s->execute([':uid' => (int)$user['id']]);
        $missions_done = (int)$s->fetchColumn();
    } catch (PDOException $e) {}
}

$steps = [
    ['done' => true,       'icon' => '✅', 'title' => 'Compte créé',             'desc' => 'Tu es officiellement Zonaute. Bienvenue !'],
    ['done' => $has_clan,  'icon' => '🛡', 'title' => 'Rejoindre un clan',        'desc' => 'Rejoins Bocage, Littoral ou Marais pour contribuer à la Bataille des Clans.', 'link' => 'inscription.php', 'cta' => 'Choisir un clan'],
    ['done' => $has_avatar,'icon' => '🧭', 'title' => 'Personnaliser ton avatar',  'desc' => 'Donne un visage à ton profil — preset, généré ou photo.', 'link' => 'mon-compte.php', 'cta' => 'Modifier mon avatar'],
    ['done' => $missions_done > 0, 'icon' => '🎯', 'title' => 'Première mission', 'desc' => 'Lance-toi dans une mission pour gagner tes premiers XP.', 'link' => 'missions.php', 'cta' => 'Voir les missions'],
];

$done_count = count(array_filter($steps, fn($s) => $s['done']));

$page_styles = '<style>
.bv-page{min-height:100vh;background:var(--beige);padding-top:80px;padding-bottom:72px}
.bv-wrap{max-width:580px;margin:0 auto;padding:0 20px}
.bv-hero{background:linear-gradient(135deg,#0c1e2e,#163756);border-radius:var(--radius-lg);padding:40px 36px;text-align:center;margin-bottom:32px;position:relative;overflow:hidden}
.bv-hero::before{content:"🎉";position:absolute;top:-10px;right:16px;font-size:8rem;opacity:.07;pointer-events:none}
.bv-hero-label{font-size:.68rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:var(--primary);display:block;margin-bottom:10px}
.bv-hero-title{font-size:1.9rem;font-weight:900;color:#fff;letter-spacing:-.03em;margin-bottom:10px;line-height:1.2}
.bv-hero-sub{font-size:.92rem;color:rgba(255,255,255,.65);line-height:1.6}
.bv-progress-wrap{background:rgba(255,255,255,.08);border-radius:20px;height:6px;margin-top:20px;overflow:hidden}
.bv-progress-bar{height:100%;background:var(--primary);border-radius:20px;transition:width .6s cubic-bezier(.4,0,.2,1)}
.bv-progress-label{font-size:.72rem;color:rgba(255,255,255,.5);margin-top:6px;font-weight:600}
.bv-steps{display:flex;flex-direction:column;gap:12px;margin-bottom:32px}
.bv-step{background:#fff;border-radius:var(--radius);border:1.5px solid var(--beige-dark);padding:18px 20px;display:flex;align-items:flex-start;gap:14px;transition:box-shadow .15s}
.bv-step:hover{box-shadow:var(--shadow-sm)}
.bv-step.done{opacity:.75}
.bv-step-icon{font-size:1.5rem;flex-shrink:0;line-height:1;width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--beige);flex-shrink:0}
.bv-step.done .bv-step-icon{background:rgba(42,157,92,.1)}
.bv-step-body{flex:1;min-width:0}
.bv-step-title{font-size:.93rem;font-weight:800;color:var(--navy-dark);margin-bottom:3px}
.bv-step.done .bv-step-title{text-decoration:line-through;color:var(--text-muted)}
.bv-step-desc{font-size:.82rem;color:var(--text-muted);line-height:1.5;margin-bottom:8px}
.bv-step-cta{display:inline-block;padding:7px 16px;background:var(--primary);color:#fff;border-radius:var(--radius);font-size:.8rem;font-weight:800;text-decoration:none;transition:all .15s}
.bv-step-cta:hover{background:var(--primary-dark);transform:translateY(-1px)}
.bv-step-done-check{font-size:.9rem;font-weight:700;color:#1a7a46;background:rgba(42,157,92,.1);border-radius:10px;padding:2px 10px;display:inline-block;margin-top:2px}
.bv-cta-section{background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark);padding:28px;text-align:center}
.bv-cta-title{font-size:1.1rem;font-weight:900;color:var(--navy-dark);margin-bottom:8px}
.bv-cta-sub{font-size:.88rem;color:var(--text-muted);margin-bottom:20px;line-height:1.5}
.bv-cta-primary{display:inline-block;padding:12px 28px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;text-decoration:none;transition:all .2s;margin:4px}
.bv-cta-primary:hover{background:var(--primary-dark);transform:translateY(-1px)}
.bv-cta-secondary{display:inline-block;padding:10px 20px;border:2px solid var(--beige-dark);color:var(--text-mid);border-radius:var(--radius);font-weight:700;font-size:.88rem;text-decoration:none;margin:4px}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="bv-page">
  <div class="bv-wrap">

    <!-- Hero -->
    <div class="bv-hero">
      <span class="bv-hero-label">Zone85</span>
      <h1 class="bv-hero-title">Bienvenue, <?= e($pseudo) ?> ! 🎉</h1>
      <p class="bv-hero-sub">Tu fais maintenant partie de la Zone. Quelques étapes pour bien démarrer :</p>
      <div class="bv-progress-wrap">
        <div class="bv-progress-bar" style="width:<?= (int)round($done_count / count($steps) * 100) ?>%"></div>
      </div>
      <div class="bv-progress-label"><?= $done_count ?>/<?= count($steps) ?> étapes complétées</div>
    </div>

    <!-- Étapes -->
    <div class="bv-steps">
      <?php foreach ($steps as $step): ?>
      <div class="bv-step <?= $step['done'] ? 'done' : '' ?>">
        <div class="bv-step-icon"><?= $step['icon'] ?></div>
        <div class="bv-step-body">
          <div class="bv-step-title"><?= $step['title'] ?></div>
          <div class="bv-step-desc"><?= $step['desc'] ?></div>
          <?php if ($step['done']): ?>
            <span class="bv-step-done-check">✓ Fait</span>
          <?php elseif (!empty($step['link'])): ?>
            <a href="<?= $step['link'] ?>" class="bv-step-cta"><?= $step['cta'] ?> →</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- CTA final -->
    <div class="bv-cta-section">
      <p class="bv-cta-title">Prêt à jouer ?</p>
      <p class="bv-cta-sub">La Zone t'attend. Explore les missions, les randos et les événements flash pour grimper dans le classement.</p>
      <a href="missions.php" class="bv-cta-primary">Voir les missions →</a>
      <a href="profil.php" class="bv-cta-secondary">Mon profil</a>
      <a href="classement.php" class="bv-cta-secondary">Classement</a>
    </div>

    <p style="text-align:center;margin-top:24px;font-size:.78rem;color:var(--text-muted);font-style:italic">
      "Je progresse pour moi. Je fais gagner mon clan."
    </p>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
