<?php
$page_title       = 'VICTOR — Le livre de la Zone85';
$page_description = 'VICTOR est le livre PDF de Zone85, réservé aux membres. Une histoire vendéenne en téléchargement libre pour ceux qui ont rejoint la Zone.';
$page_canonical   = 'https://www.zone85.fr/victor.php';
$page_robots      = 'noindex,follow';
$current_page     = '';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$is_logged  = is_logged_in();
$user       = $is_logged ? current_user() : null;
$pdo        = db();

// ── Table victor_downloads (CREATE IF NOT EXISTS) ─────────────
if ($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS victor_downloads (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip            VARCHAR(64) DEFAULT NULL,
            INDEX idx_user (user_id),
            INDEX idx_date (downloaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}
}

// ── Traitement du téléchargement ──────────────────────────────
$download_error = null;
if ($is_logged && isset($_GET['dl']) && $_GET['dl'] === '1') {
    $pdf_path = BASE_PATH . 'zone85_php/assets/pdf/victor.pdf';
    if (!file_exists($pdf_path)) {
        $download_error = 'Le fichier PDF n\'est pas encore disponible. Réessaie bientôt.';
    } else {
        // Log download
        if ($pdo) {
            try {
                $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
                $stmt = $pdo->prepare(
                    "INSERT INTO victor_downloads (user_id, ip) VALUES (:uid, :ip)"
                );
                $stmt->execute([':uid' => (int)$user['id'], ':ip' => $ip]);
            } catch (PDOException $e) {}
        }
        // Serve PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="VICTOR-Zone85.pdf"');
        header('Content-Length: ' . filesize($pdf_path));
        header('Cache-Control: private, no-cache');
        readfile($pdf_path);
        exit;
    }
}

// ── Stats d'affichage ─────────────────────────────────────────
$total_downloads = 0;
$my_downloads    = 0;
if ($pdo) {
    try {
        $total_downloads = (int)$pdo->query(
            "SELECT COUNT(*) FROM victor_downloads"
        )->fetchColumn();
        if ($is_logged) {
            $s = $pdo->prepare(
                "SELECT COUNT(*) FROM victor_downloads WHERE user_id = :uid"
            );
            $s->execute([':uid' => (int)$user['id']]);
            $my_downloads = (int)$s->fetchColumn();
        }
    } catch (PDOException $e) {}
}

$page_styles = '<style>
.vt-page{min-height:100vh;background:var(--beige);padding-top:80px;padding-bottom:72px}
.vt-wrap{max-width:700px;margin:0 auto;padding:0 20px}

/* Hero livre */
.vt-hero{background:linear-gradient(135deg,#1a1200,#2d2000);border-radius:var(--radius-lg);padding:48px 40px;margin-bottom:28px;position:relative;overflow:hidden;display:grid;grid-template-columns:1fr 120px;gap:28px;align-items:center}
.vt-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 100% 100%,rgba(212,175,55,.06),transparent 60%);pointer-events:none}
.vt-hero-tag{font-size:.62rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:#d4af37;margin-bottom:12px;display:block}
.vt-hero-title{font-size:2.6rem;font-weight:900;color:#fff;letter-spacing:-.04em;line-height:1;margin-bottom:8px}
.vt-hero-sub{font-size:.9rem;color:rgba(255,255,255,.5);line-height:1.65;margin-bottom:24px}
.vt-hero-visual{display:flex;align-items:center;justify-content:center;font-size:6rem;line-height:1;opacity:.85;position:relative;z-index:1}

/* Bouton téléchargement */
.vt-dl-btn{display:inline-flex;align-items:center;gap:10px;background:#d4af37;color:#1a1200;padding:14px 28px;border-radius:var(--radius);font-weight:800;font-size:.98rem;text-decoration:none;transition:background .2s,transform .15s}
.vt-dl-btn:hover{background:#c9a430;transform:translateY(-1px)}
.vt-dl-btn svg{width:18px;height:18px;flex-shrink:0}
.vt-dl-count{font-size:.72rem;color:rgba(255,255,255,.35);margin-top:10px;font-weight:600}

/* Gate (non connecté) */
.vt-gate{background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark);padding:32px 28px;text-align:center}
.vt-gate-icon{font-size:2.6rem;margin-bottom:14px}
.vt-gate-title{font-size:1.1rem;font-weight:900;color:var(--navy-dark);margin-bottom:8px}
.vt-gate-sub{font-size:.88rem;color:var(--text-muted);line-height:1.6;margin-bottom:22px}
.vt-gate-btn{display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#fff;padding:12px 26px;border-radius:var(--radius);font-weight:800;font-size:.92rem;text-decoration:none;transition:background .2s}
.vt-gate-btn:hover{background:var(--primary-dark)}
.vt-gate-login{display:block;margin-top:12px;font-size:.82rem;color:var(--text-muted)}
.vt-gate-login a{color:var(--primary);font-weight:700;text-decoration:none}

/* Stats */
.vt-stats{display:flex;gap:16px;margin-bottom:20px}
.vt-stat{background:#fff;border:1.5px solid var(--beige-dark);border-radius:var(--radius);padding:14px 20px;flex:1;text-align:center}
.vt-stat-num{font-size:1.6rem;font-weight:900;color:var(--navy-dark);line-height:1}
.vt-stat-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-top:4px}

/* Erreur */
.vt-error{background:rgba(234,86,73,.08);border:1.5px solid rgba(234,86,73,.25);border-radius:var(--radius);padding:14px 18px;font-size:.88rem;color:var(--primary);margin-bottom:16px}

/* Descr */
.vt-descr{background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark);padding:28px;margin-bottom:20px}
.vt-descr-title{font-size:.88rem;font-weight:800;color:var(--navy-dark);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em}
.vt-descr-text{font-size:.88rem;color:var(--text-muted);line-height:1.75}

@media(max-width:640px){
  .vt-hero{grid-template-columns:1fr;padding:32px 24px}
  .vt-hero-visual{display:none}
  .vt-stats{flex-direction:column}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="vt-page">
  <div class="vt-wrap">

    <!-- Erreur éventuelle -->
    <?php if ($download_error): ?>
    <div class="vt-error">⚠️ <?= e($download_error) ?></div>
    <?php endif; ?>

    <!-- Hero du livre -->
    <div class="vt-hero">
      <div style="position:relative;z-index:1">
        <span class="vt-hero-tag">Zone85 · Livre membre</span>
        <div class="vt-hero-title">VICTOR</div>
        <p class="vt-hero-sub">Une histoire vendéenne — personnages, territoire, mémoire. PDF gratuit, réservé aux membres de Zone85.</p>

        <?php if ($is_logged): ?>
          <a href="victor.php?dl=1" class="vt-dl-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Télécharger le PDF
          </a>
          <div class="vt-dl-count">
            <?= $total_downloads ?> téléchargement<?= $total_downloads > 1 ? 's' : '' ?> au total
            <?php if ($my_downloads > 0): ?>
              · tu l'as téléchargé <?= $my_downloads ?> fois
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.12);color:rgba(255,255,255,.4);padding:12px 22px;border-radius:var(--radius);font-size:.88rem;font-weight:700">
            🔒 Réservé aux membres
          </div>
        <?php endif; ?>
      </div>
      <div class="vt-hero-visual">📖</div>
    </div>

    <?php if ($is_logged): ?>
    <!-- Stats (membre) -->
    <div class="vt-stats">
      <div class="vt-stat">
        <div class="vt-stat-num"><?= $total_downloads ?></div>
        <div class="vt-stat-label">Téléchargements totaux</div>
      </div>
      <div class="vt-stat">
        <div class="vt-stat-num"><?= $my_downloads ?></div>
        <div class="vt-stat-label">Mes téléchargements</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Description -->
    <div class="vt-descr">
      <div class="vt-descr-title">À propos de VICTOR</div>
      <div class="vt-descr-text">
        VICTOR est l'œuvre littéraire de Zone85 — un livre PDF en téléchargement libre pour tous les membres. Une plongée dans l'âme secrète de la Vendée : ses personnages oubliés, ses territoires cachés, sa mémoire vivante.<br><br>
        Ce n'est pas un guide. Ce n'est pas un roman classique. C'est un récit vendéen, produit par et pour la communauté Zone85. Un avant-goût de ce que le projet peut créer quand le jeu devient récit.
      </div>
    </div>

    <!-- Gate (non connecté) -->
    <?php if (!$is_logged): ?>
    <div class="vt-gate">
      <div class="vt-gate-icon">🔒</div>
      <div class="vt-gate-title">Contenu réservé aux membres</div>
      <p class="vt-gate-sub">VICTOR est disponible gratuitement pour tous les membres de Zone85. Rejoins la Zone pour déverrouiller le téléchargement PDF.</p>
      <a href="inscription.php" class="vt-gate-btn">Rejoindre Zone85 — c'est gratuit →</a>
      <span class="vt-gate-login">Déjà membre ? <a href="login.php?redirect=victor.php">Se connecter</a></span>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
