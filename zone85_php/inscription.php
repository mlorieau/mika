<?php
// ── Handler AJAX inscription ──────────────────────────────────
// Traité avant tout output HTML.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'register') {
    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
    require_once 'includes/auth.php';
    header('Content-Type: application/json; charset=UTF-8');

    // Vérification CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'Token de sécurité invalide. Rechargez la page.']);
        exit;
    }

    // Validation des champs
    $errors = [];
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $pseudo     = safe_input($_POST['pseudo'] ?? '', 50);
    $password   = $_POST['password'] ?? '';
    $clan_slug  = safe_input($_POST['clan'] ?? '', 20);
    $clan_id    = match($clan_slug) { 'bocage' => 1, 'littoral' => 2, 'marais' => 3, default => 0 };
    $avatar_type = in_array($_POST['avatar_type'] ?? '', ['preset', 'upload']) ? $_POST['avatar_type'] : 'preset';
    $avatar_key  = safe_input($_POST['avatar_key'] ?? '🧭', 16);
    $first_name  = safe_input($_POST['first_name'] ?? '', 100);
    $last_name   = safe_input($_POST['last_name']  ?? '', 100);
    $bio         = safe_input($_POST['bio']        ?? '', 120);
    $newsletter  = !empty($_POST['newsletter']);

    $password_confirm = $_POST['password_confirm'] ?? '';

    if (!$email)                    $errors['email']    = 'Adresse email invalide.';
    if (mb_strlen($pseudo) < 3)     $errors['pseudo']   = 'Le pseudo doit contenir au moins 3 caractères.';
    if (strlen($password) < 8)      $errors['password'] = 'Mot de passe trop court (minimum 8 caractères).';
    if ($password_confirm && $password !== $password_confirm) $errors['password'] = 'Les mots de passe ne correspondent pas.';
    if ($clan_id === 0)             $errors['clan']     = 'Clan invalide.';
    if (empty($_POST['accept_cgu']) || empty($_POST['accept_privacy'])) {
        $errors['legal'] = 'Veuillez accepter les CGU et la politique de confidentialité.';
    }

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }

    // Upload avatar si fourni
    $avatar_file_path = null;
    if ($avatar_type === 'upload' && !empty($_FILES['avatar_photo']['tmp_name'])) {
        $upload = upload_avatar($_FILES['avatar_photo']);
        if (!$upload['ok']) {
            echo json_encode(['ok' => false, 'errors' => ['avatar' => $upload['error']]]);
            exit;
        }
        $avatar_file_path = $upload['path'];
    } else {
        $avatar_type = 'preset';
    }

    // Création du compte
    $result = register_user([
        'email'            => $email,
        'pseudo'           => $pseudo,
        'password'         => $password,
        'password_confirm' => $password_confirm,
        'clan_id'       => $clan_id,
        'avatar_type'   => $avatar_type,
        'avatar_config' => json_encode(['emoji' => $avatar_key]),
        'avatar_file'   => $avatar_file_path,
        'first_name'    => $first_name,
        'last_name'     => $last_name,
        'bio'           => $bio,
        'newsletter'    => $newsletter,
    ]);

    if (!$result['ok']) {
        echo json_encode($result);
        exit;
    }

    // Auto-connexion après inscription
    $new_user = find_user_by_id($result['user_id']);
    if ($new_user) {
        login_user($new_user);
    }

    echo json_encode(['ok' => true, 'user_id' => $result['user_id']]);
    exit;
}

// ── Rediriger si déjà connecté ────────────────────────────────
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (is_logged_in()) {
    header('Location: profil.php');
    exit;
}

$page_title       = 'Rejoindre la Zone';
$page_description = 'Inscris-toi sur ZONE85, choisis ton clan vendéen et commence à gagner des XP. Inscription gratuite en moins de 2 minutes.';
$page_canonical   = 'https://www.zone85.fr/inscription.php';
$page_robots      = 'noindex,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = null;
$current_page = 'inscription';
require_once 'includes/data.php';
$page_styles = '<style>
/* ── PAGE LAYOUT ── */
.insc-page{min-height:100vh;background:var(--beige);padding-top:100px;padding-bottom:72px}
.insc-wrap{max-width:680px;margin:0 auto;padding:0 20px}

/* ── STEP DOTS ── */
.step-nav{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:40px}
.step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;border:2px solid var(--beige-dark);background:var(--beige-dark);color:var(--text-muted);transition:all .3s ease;flex-shrink:0;cursor:default}
.step-dot.active{background:var(--navy-dark);border-color:var(--navy-dark);color:#fff;box-shadow:0 0 0 4px rgba(18,49,78,.15)}
.step-dot.done{background:var(--primary);border-color:var(--primary);color:#fff}
.step-line{flex:1;height:2px;background:var(--beige-dark);transition:background .3s ease;max-width:48px}
.step-line.done{background:var(--primary)}

/* ── STEP PANEL ── */
.step-panel{display:none;animation:fadeUp .35s ease}
.step-panel.active{display:block}

/* ── STEP CARD ── */
.step-card{background:var(--white);border-radius:var(--radius-lg);padding:40px 36px;box-shadow:var(--shadow-md)}
@media(max-width:520px){.step-card{padding:28px 20px}}

/* ── FORM ELEMENTS ── */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.78rem;font-weight:700;color:var(--text-mid);margin-bottom:6px;letter-spacing:.03em}
.form-input{width:100%;padding:11px 14px;border:2px solid var(--beige-dark);border-radius:var(--radius);font-family:\'Inter\',sans-serif;font-size:.92rem;color:var(--text);background:var(--beige-light);transition:border-color .2s,box-shadow .2s;outline:none}
.form-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(234,86,73,.1);background:#fff}
.form-input.error{border-color:#e74c3c;box-shadow:0 0 0 3px rgba(231,76,60,.1)}
.form-input.valid{border-color:var(--green)}
.form-error{font-size:.75rem;color:#e74c3c;margin-top:4px;display:none}
.form-error.show{display:block}

/* PW TOGGLE */
.pw-wrap{position:relative}
.pw-wrap .form-input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1.1rem;padding:4px;color:var(--text-muted);line-height:1}
.pw-toggle:hover{color:var(--text)}

/* STEP NAV BUTTONS */
.step-actions{display:flex;align-items:center;justify-content:space-between;margin-top:28px;gap:12px}
.step-actions.single{justify-content:flex-end}

/* ── IDENTITY CARDS (étape 2) ── */
.identity-cards{display:flex;flex-direction:column;gap:12px;margin:20px 0 24px}
.identity-card{display:flex;align-items:center;gap:16px;padding:16px 18px;border-radius:var(--radius);border:2px solid var(--beige-dark);background:var(--beige-light);cursor:pointer;transition:all .2s}
.identity-card:hover{border-color:var(--primary);background:rgba(234,86,73,.03)}
.identity-card.selected{border-color:var(--primary);background:rgba(234,86,73,.05)}
.identity-icon{font-size:1.8rem;flex-shrink:0;width:44px;text-align:center}
.identity-info{flex:1}
.identity-name{font-weight:800;font-size:.95rem;color:var(--text);margin-bottom:2px}
.identity-desc{font-size:.82rem;color:var(--text-muted);line-height:1.5}
.identity-check{width:20px;height:20px;border-radius:50%;border:2px solid var(--beige-dark);flex-shrink:0;transition:all .2s;position:relative}
.identity-card.selected .identity-check{background:var(--primary);border-color:var(--primary)}
.identity-card.selected .identity-check::after{content:\'✓\';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.65rem;font-weight:900}

/* ── LEÇONS (étape 3) ── */
.lecons-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px}
.lecon-img{width:100%;border-radius:8px;display:block}
.lecon-full{grid-column:1/-1}
.lecon-last{grid-column:1/-1;max-width:50%;justify-self:center}
.accept-row{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-radius:var(--radius);border:2px solid var(--beige-dark);background:var(--beige-light);cursor:pointer;transition:border-color .2s;margin-bottom:20px}
.accept-row.checked{border-color:var(--primary);background:rgba(234,86,73,.04)}
.accept-cb{width:18px;height:18px;border:2px solid var(--beige-dark);border-radius:4px;flex-shrink:0;margin-top:2px;position:relative;transition:all .2s}
.accept-row.checked .accept-cb{background:var(--primary);border-color:var(--primary)}
.accept-row.checked .accept-cb::after{content:\'✓\';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;font-weight:900}
.accept-label{font-size:.85rem;color:var(--text-mid);line-height:1.55;user-select:none}
.accept-label strong{color:var(--text)}

/* CODE INTRO CARD */
.code-intro{background:var(--navy-dark);border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;font-size:.88rem;color:rgba(255,255,255,.8);line-height:1.6}
.code-intro em{color:var(--primary);font-style:normal;font-weight:700}

/* ── CLAN OPTIONS (étape 4) ── */
.clan-options{display:flex;flex-direction:column;gap:12px;margin:20px 0 24px}
.clan-option{display:flex;align-items:center;gap:16px;padding:16px 18px;border-radius:var(--radius);border:2px solid var(--beige-dark);background:var(--beige-light);cursor:pointer;transition:all .2s}
.clan-option:hover{border-color:var(--primary);background:rgba(234,86,73,.03)}
.clan-option.selected{border-color:var(--primary);background:rgba(234,86,73,.05)}
.clan-thumb{width:64px;height:64px;border-radius:var(--radius);overflow:hidden;flex-shrink:0;background:var(--beige-dark);display:flex;align-items:center;justify-content:center}
.clan-thumb img{width:100%;height:100%;object-fit:contain}
.clan-info{flex:1;min-width:0}
.clan-nom{font-weight:900;font-size:1rem;color:var(--text);margin-bottom:1px}
.clan-mascotte{font-size:.78rem;font-weight:700;color:var(--primary);margin-bottom:3px}
.clan-cri{font-size:.78rem;font-style:italic;color:var(--text-muted);margin-bottom:6px}
.clan-stats-mini{display:flex;gap:12px;flex-wrap:wrap}
.clan-stat-mini{font-size:.72rem;color:var(--text-muted);font-weight:600}
.clan-stat-mini strong{color:var(--text);font-weight:800}
.clan-radio{width:20px;height:20px;border-radius:50%;border:2px solid var(--beige-dark);flex-shrink:0;transition:all .2s;position:relative}
.clan-option.selected .clan-radio{background:var(--primary);border-color:var(--primary)}
.clan-option.selected .clan-radio::after{content:\'✓\';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.65rem;font-weight:900}
.clan-pts-note{font-size:.75rem;color:var(--text-muted);text-align:center;margin-top:-12px;margin-bottom:16px}

/* ── AVATARS (étape 5) ── */
.avatar-tabs{display:flex;gap:0;margin-bottom:16px;border-radius:var(--radius);overflow:hidden;border:2px solid var(--beige-dark)}
.avatar-tab-btn{flex:1;padding:10px 12px;background:var(--beige-light);border:none;font-family:\'Inter\',sans-serif;font-size:.82rem;font-weight:700;color:var(--text-muted);cursor:pointer;transition:all .2s;text-align:center}
.avatar-tab-btn.active{background:var(--navy-dark);color:#fff}
.avatar-panel{display:none}.avatar-panel.active{display:block}
.avatar-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:16px}
.avatar-btn{aspect-ratio:1;border-radius:var(--radius);background:var(--beige-light);border:2px solid var(--beige-dark);font-size:1.5rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;user-select:none}
.avatar-btn:hover{border-color:var(--primary);transform:scale(1.07)}
.avatar-btn.selected{border-color:var(--primary);background:rgba(234,86,73,.08);transform:scale(1.07)}
.upload-zone{border:2px dashed var(--beige-dark);border-radius:var(--radius-lg);padding:32px 20px;text-align:center;background:var(--beige-light);cursor:pointer;transition:border-color .2s}
.upload-zone:hover{border-color:var(--primary)}
.upload-zone input[type="file"]{display:none}
.upload-preview{width:80px;height:80px;border-radius:50%;margin:0 auto 12px;overflow:hidden;background:var(--beige-dark);display:none}
.upload-preview img{width:100%;height:100%;object-fit:cover}
.upload-icon{font-size:2rem;margin-bottom:8px;display:block}
.upload-text{font-size:.84rem;color:var(--text-muted);line-height:1.5}
.upload-legal{font-size:.72rem;color:var(--text-muted);margin-top:10px;line-height:1.6;font-style:italic}
.check-group{display:flex;align-items:flex-start;gap:10px;padding:12px 0;border-top:1px solid var(--beige-dark)}
.check-group input[type="checkbox"]{width:18px;height:18px;flex-shrink:0;margin-top:2px;accent-color:var(--primary);cursor:pointer}
.check-group label{font-size:.84rem;color:var(--text-mid);line-height:1.55;cursor:pointer}
.check-group label a{color:var(--primary);text-decoration:underline}
.check-group.required label::before{content:\'* \';color:var(--primary);font-weight:700}
.char-count{font-size:.72rem;color:var(--text-muted);text-align:right;margin-top:4px}

/* ── ÉTAPE 6 BIENVENUE ── */
.welcome-hero{background:linear-gradient(160deg,#0d1e2c 0%,#12314e 100%);border-radius:var(--radius-lg);padding:40px 32px;text-align:center;margin-bottom:20px;position:relative;overflow:hidden}
.welcome-hero::before{content:\'\';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none}
.welcome-hero::after{content:\'\';position:absolute;bottom:-50px;left:20%;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.03);pointer-events:none}
.welcome-avatar{width:72px;height:72px;background:var(--primary);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;font-size:2.2rem;margin:0 auto 16px;position:relative;z-index:1;box-shadow:0 4px 20px rgba(234,86,73,.4)}
.welcome-hero h2{font-size:clamp(1.5rem,4vw,2.2rem);font-weight:900;color:#fff;margin-bottom:6px;position:relative;z-index:1;letter-spacing:-.5px}
.welcome-hero p{font-size:.92rem;color:rgba(255,255,255,.65);margin-bottom:16px;position:relative;z-index:1}
.xp-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(234,86,73,.2);border:1px solid rgba(234,86,73,.35);color:var(--primary);font-size:.82rem;font-weight:800;padding:6px 16px;border-radius:20px;position:relative;z-index:1}
.recap-card{background:var(--white);border-radius:var(--radius-lg);padding:28px 28px;box-shadow:var(--shadow-sm);margin-bottom:24px}
.recap-title{font-size:.7rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);margin-bottom:16px}
.recap-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--beige-dark);font-size:.9rem;color:var(--text)}
.recap-row:last-child{border-bottom:none}
.recap-icon{font-size:1.1rem;width:24px;text-align:center;flex-shrink:0}
.recap-label{color:var(--text-muted);font-size:.78rem;font-weight:600;min-width:90px}
.recap-val{font-weight:700;flex:1}
.recap-clan-img{width:40px;height:40px;border-radius:6px;object-fit:contain;margin-left:8px;flex-shrink:0}
.recap-xp{font-weight:800;color:var(--primary)}

/* ── STEP HEADER ── */
.step-overline{font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--primary);margin-bottom:8px;display:block}
.step-title{font-size:clamp(1.3rem,3vw,1.8rem);font-weight:900;color:var(--navy-dark);letter-spacing:-.5px;margin-bottom:6px;line-height:1.2}
.step-sub{font-size:.9rem;color:var(--text-muted);margin-bottom:24px;line-height:1.6}

/* LOGIN LINK */
.login-link{text-align:center;margin-top:20px;font-size:.84rem;color:var(--text-muted)}
.login-link a{color:var(--primary);font-weight:700;text-decoration:underline}

/* DISABLED BTN */
.btn-primary:disabled,.btn[disabled]{opacity:.45;cursor:not-allowed;pointer-events:none}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- PROGRESS BAR -->
<div style="position:fixed;top:71px;left:0;right:0;height:3px;background:var(--beige-dark);z-index:99">
  <div id="progress-fill" style="height:100%;background:var(--primary);transition:width .5s ease;width:16.6%"></div>
</div>

<!-- PAGE -->
<div class="insc-page">
  <div class="insc-wrap">

    <!-- CSRF -->
    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <!-- STEP DOTS -->
    <div class="step-nav" id="step-dots">
      <div class="step-dot active" id="step-dot-1">1</div>
      <div class="step-line" id="step-line-1"></div>
      <div class="step-dot" id="step-dot-2">2</div>
      <div class="step-line" id="step-line-2"></div>
      <div class="step-dot" id="step-dot-3">3</div>
      <div class="step-line" id="step-line-3"></div>
      <div class="step-dot" id="step-dot-4">4</div>
      <div class="step-line" id="step-line-4"></div>
      <div class="step-dot" id="step-dot-5">5</div>
      <div class="step-line" id="step-line-5"></div>
      <div class="step-dot" id="step-dot-6">6</div>
    </div>

    <!-- ══════════════════════════════════════
         ÉTAPE 1 — TES INFOS
    ══════════════════════════════════════ -->
    <div class="step-panel active" id="panel-1">
      <div class="step-card">
        <span class="step-overline">Étape 1 — Tes informations</span>
        <h1 class="step-title">Crée ton compte Zone 85</h1>
        <p class="step-sub">Rapide, gratuit, et ta légende commence ici.</p>

        <div class="form-row">
          <div class="form-group" style="margin-bottom:0">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" class="form-input" placeholder="Marie" autocomplete="given-name">
            <div class="form-error" id="err-prenom">Ce champ est requis.</div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label for="nom">Nom</label>
            <input type="text" id="nom" class="form-input" placeholder="Mercier" autocomplete="family-name">
            <div class="form-error" id="err-nom">Ce champ est requis.</div>
          </div>
        </div>

        <div class="form-group" style="margin-top:16px">
          <label for="email">Adresse email</label>
          <input type="email" id="email" class="form-input" placeholder="marie@vendee.fr" autocomplete="email">
          <div class="form-error" id="err-email">Adresse email invalide.</div>
        </div>

        <div class="form-group">
          <label for="pw">Mot de passe</label>
          <div class="pw-wrap">
            <input type="password" id="pw" class="form-input" placeholder="Min. 8 caractères" autocomplete="new-password">
            <button class="pw-toggle" type="button" onclick="togglePw('pw',this)" tabindex="-1" aria-label="Afficher le mot de passe">👁</button>
          </div>
          <div class="form-error" id="err-pw">Minimum 8 caractères requis.</div>
        </div>

        <div class="form-group">
          <label for="pw2">Confirmer le mot de passe</label>
          <div class="pw-wrap">
            <input type="password" id="pw2" class="form-input" placeholder="Répète ton mot de passe" autocomplete="new-password">
            <button class="pw-toggle" type="button" onclick="togglePw('pw2',this)" tabindex="-1" aria-label="Afficher le mot de passe">👁</button>
          </div>
          <div class="form-error" id="err-pw2">Les mots de passe ne correspondent pas.</div>
        </div>

        <div class="step-actions single">
          <button class="btn btn-primary" onclick="validateStep1()">Continuer →</button>
        </div>
      </div>
      <p class="login-link">Déjà un compte ? <a href="profil.php">Se connecter</a></p>
    </div>

    <!-- ══════════════════════════════════════
         ÉTAPE 2 — TON ADN VENDÉEN
    ══════════════════════════════════════ -->
    <div class="step-panel" id="panel-2">
      <div class="step-card">
        <span class="step-overline">Étape 2 — Ton lien avec la Vendée</span>
        <h2 class="step-title">Tu es vendéen·ne…</h2>

        <div class="identity-cards">
          <div class="identity-card" onclick="selectIdentity('souche',this)">
            <div class="identity-icon">🌱</div>
            <div class="identity-info">
              <div class="identity-name">De souche</div>
              <div class="identity-desc">Né·e en terre vendéenne. Le 85 coule dans tes veines.</div>
            </div>
            <div class="identity-check"></div>
          </div>
          <div class="identity-card" onclick="selectIdentity('coeur',this)">
            <div class="identity-icon">❤️</div>
            <div class="identity-info">
              <div class="identity-name">De cœur</div>
              <div class="identity-desc">La Vendée t'a adopté·e. Tu l'as choisie et tu ne pars plus.</div>
            </div>
            <div class="identity-check"></div>
          </div>
          <div class="identity-card" onclick="selectIdentity('adoption',this)">
            <div class="identity-icon">🌍</div>
            <div class="identity-info">
              <div class="identity-name">D'adoption</div>
              <div class="identity-desc">Tu découvres la Vendée. Tu tombes déjà sous le charme.</div>
            </div>
            <div class="identity-check"></div>
          </div>
        </div>

        <div class="step-actions">
          <button class="btn btn-ghost" onclick="goStep(1)">← Retour</button>
          <button class="btn btn-primary" id="btn-step2" disabled onclick="goStep(3)">Continuer →</button>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         ÉTAPE 3 — LE CODE DE LA ZONE
    ══════════════════════════════════════ -->
    <div class="step-panel" id="panel-3">
      <div class="step-card">
        <span class="step-overline">Étape 3 — Le Code de la Zone</span>
        <h2 class="step-title">7 leçons vendéennes</h2>

        <div class="code-intro">
          Entrer en Zone85 tu pourras… <em>mais avant,</em> 7 leçons tu apprendras…
        </div>

        <div class="lecons-grid">
          <img src="<?= img('intro.png') ?>" alt="Intro" class="lecon-img lecon-full">
          <img src="<?= img('lecon-1.png') ?>" alt="Leçon 1" class="lecon-img">
          <img src="<?= img('lecon-2.png') ?>" alt="Leçon 2" class="lecon-img">
          <img src="<?= img('lecon-3.png') ?>" alt="Leçon 3" class="lecon-img">
          <img src="<?= img('lecon-4.png') ?>" alt="Leçon 4" class="lecon-img">
          <img src="<?= img('lecon-5.png') ?>" alt="Leçon 5" class="lecon-img">
          <img src="<?= img('lecon-6.png') ?>" alt="Leçon 6" class="lecon-img">
          <img src="<?= img('lecon-7.png') ?>" alt="Leçon 7" class="lecon-img lecon-last">
        </div>

        <div class="accept-row" id="accept-row" onclick="toggleAccept()">
          <div class="accept-cb" id="accept-cb"></div>
          <div class="accept-label"><strong>J'ai lu et j'accepte le Code de la Zone 85.</strong> Je jure sur la brioche pur beurre.</div>
        </div>

        <div class="step-actions">
          <button class="btn btn-ghost" onclick="goStep(2)">← Retour</button>
          <button class="btn btn-primary" id="btn-step3" disabled onclick="goStep(4)">Continuer →</button>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         ÉTAPE 4 — TON CLAN
    ══════════════════════════════════════ -->
    <div class="step-panel" id="panel-4">
      <div class="step-card">
        <span class="step-overline">Étape 4 — Choisis ton clan</span>
        <h2 class="step-title">Une seule question : qui es-tu ?</h2>
        <p class="step-sub">Trois clans. Trois identités. Ta contribution fait gagner le tien.</p>

        <div class="clan-options">
          <!-- BOCAGE -->
          <div class="clan-option" onclick="selectClan('bocage',this)">
            <div class="clan-thumb">
              <img src="<?= img('mascotte-bocage.png') ?>" alt="Mascotte Bocage">
            </div>
            <div class="clan-info">
              <div class="clan-nom">Clan Bocage</div>
              <div class="clan-mascotte">Bran le Bocager</div>
              <div class="clan-cri">"Par les chênes et les genêts !"</div>
              <div class="clan-stats-mini">
                <?php $bocage = get_clan_by_slug('bocage'); ?>
                <span class="clan-stat-mini"><strong><?= e($bocage['members'] ?? '392') ?></strong> membres</span>
                <span class="clan-stat-mini"><strong><?= format_score($bocage['season_score'] ?? 10210) ?></strong> pts</span>
                <span class="clan-stat-mini"><strong><?= e($bocage['trophies'] ?? '0') ?></strong> trophée</span>
              </div>
            </div>
            <div class="clan-radio"></div>
          </div>

          <!-- LITTORAL -->
          <div class="clan-option" onclick="selectClan('littoral',this)">
            <div class="clan-thumb">
              <img src="<?= img('mascotte-littoral.png') ?>" alt="Mascotte Littoral">
            </div>
            <div class="clan-info">
              <div class="clan-nom">Clan Littoral</div>
              <div class="clan-mascotte">Gabin Culsmouillés</div>
              <div class="clan-cri">"Vents et marées !"</div>
              <div class="clan-stats-mini">
                <?php $littoral = get_clan_by_slug('littoral'); ?>
                <span class="clan-stat-mini"><strong><?= e($littoral['members'] ?? '438') ?></strong> membres</span>
                <span class="clan-stat-mini"><strong><?= format_score($littoral['season_score'] ?? 12840) ?></strong> pts</span>
                <span class="clan-stat-mini"><strong><?= e($littoral['trophies'] ?? '2') ?></strong> trophées</span>
              </div>
            </div>
            <div class="clan-radio"></div>
          </div>

          <!-- MARAIS -->
          <div class="clan-option" onclick="selectClan('marais',this)">
            <div class="clan-thumb">
              <img src="<?= img('mascotte-marais.png') ?>" alt="Mascotte Marais">
            </div>
            <div class="clan-info">
              <div class="clan-nom">Clan Marais</div>
              <div class="clan-mascotte">Méric l'Ancien</div>
              <div class="clan-cri">"L'eau coule, la mémoire reste !"</div>
              <div class="clan-stats-mini">
                <?php $marais = get_clan_by_slug('marais'); ?>
                <span class="clan-stat-mini"><strong><?= e($marais['members'] ?? '410') ?></strong> membres</span>
                <span class="clan-stat-mini"><strong><?= format_score($marais['season_score'] ?? 11560) ?></strong> pts</span>
                <span class="clan-stat-mini"><strong><?= e($marais['trophies'] ?? '1') ?></strong> trophée</span>
              </div>
            </div>
            <div class="clan-radio"></div>
          </div>
        </div>

        <p class="clan-pts-note">* Les pts de saison repartent à zéro chaque saison.</p>

        <div class="step-actions">
          <button class="btn btn-ghost" onclick="goStep(3)">← Retour</button>
          <button class="btn btn-primary" id="btn-step4" disabled onclick="goStep(5)">Continuer →</button>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         ÉTAPE 5 — TON IDENTITÉ
    ══════════════════════════════════════ -->
    <div class="step-panel" id="panel-5">
      <div class="step-card">
        <span class="step-overline">Étape 5 — Ton identité dans la Zone</span>
        <h2 class="step-title">Comment tu seras connu·e</h2>

        <div class="form-group" style="margin-bottom:20px">
          <label>Ton avatar</label>
          <div class="avatar-tabs">
            <button class="avatar-tab-btn active" onclick="switchAvatarTab('emoji',this)" type="button">Choisir un avatar</button>
            <button class="avatar-tab-btn" onclick="switchAvatarTab('upload',this)" type="button">Uploader ma photo</button>
          </div>

          <!-- Panel : avatars emoji -->
          <div class="avatar-panel active" id="avatar-panel-emoji">
            <div class="avatar-grid" id="avatar-grid">
              <div class="avatar-btn selected" onclick="selectAvatar(this)" data-emoji="🧭">🧭</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="⚓">⚓</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🗺️">🗺️</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🏹">🏹</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🌊">🌊</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🌿">🌿</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="⚔️">⚔️</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🦅">🦅</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🔥">🔥</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🥐">🥐</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🌙">🌙</div>
              <div class="avatar-btn" onclick="selectAvatar(this)" data-emoji="🐚">🐚</div>
            </div>
          </div>

          <!-- Panel : upload photo -->
          <div class="avatar-panel" id="avatar-panel-upload">
            <label class="upload-zone" for="photo-upload" onclick="document.getElementById('photo-upload').click()">
              <input type="file" id="photo-upload" accept="image/*" onchange="handlePhotoUpload(this)">
              <div class="upload-preview" id="upload-preview"><img id="upload-preview-img" src="" alt="Aperçu"></div>
              <span class="upload-icon" id="upload-icon">📷</span>
              <div class="upload-text" id="upload-text">
                <strong>Clique pour choisir une photo</strong><br>
                Choisis une photo carrée ou bien cadrée. Elle sera affichée sur ton profil.
              </div>
            </label>
            <p class="upload-legal">En uploadant une photo, je confirme disposer des droits nécessaires et j'accepte qu'elle soit utilisée comme photo de profil sur Zone85.</p>
          </div>
        </div>

        <div class="form-group">
          <label for="pseudo">Ton pseudo <span style="font-weight:400;color:var(--text-muted)">(max 24 car.)</span></label>
          <input type="text" id="pseudo" class="form-input" placeholder="ex: BrancheVendée85" maxlength="24" autocomplete="nickname" oninput="updateCharCount('pseudo','pseudo-count',24)">
          <div class="char-count"><span id="pseudo-count">0</span>/24</div>
          <div class="form-error" id="err-pseudo">Le pseudo doit contenir au moins 3 caractères.</div>
        </div>

        <div class="form-group">
          <label for="bio">Ta phrase de présentation <span style="font-weight:400;color:var(--text-muted)">(optionnel, max 120 car.)</span></label>
          <textarea id="bio" class="form-input" rows="3" placeholder="Ex: Marcheur du bocage, amoureux des chemins creux et des citrouilles…" maxlength="120" style="resize:vertical;min-height:80px" oninput="updateCharCount('bio','bio-count',120)"></textarea>
          <div class="char-count"><span id="bio-count">0</span>/120</div>
        </div>

        <!-- Checkboxes obligatoires -->
        <div style="margin-top:8px">
          <div class="check-group required">
            <input type="checkbox" id="check-cgu" required>
            <label for="check-cgu">J'accepte les <a href="cgu.php" target="_blank">CGU et les règles de la Zone</a>.</label>
          </div>
          <div class="check-group required">
            <input type="checkbox" id="check-rgpd" required>
            <label for="check-rgpd">J'ai lu la <a href="confidentialite.php" target="_blank">politique de confidentialité</a>.</label>
          </div>
          <div class="check-group">
            <input type="checkbox" id="check-newsletter">
            <label for="check-newsletter">J'accepte de recevoir les actualités de Zone85 par email. <span style="font-weight:400;color:var(--text-muted)">(facultatif)</span></label>
          </div>
          <div class="form-error" id="err-checks" style="margin-top:4px">Veuillez accepter les CGU et la politique de confidentialité pour continuer.</div>
        </div>

        <div id="err-server" style="display:none;margin-top:12px;padding:11px 14px;background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.3);border-radius:var(--radius);font-size:.85rem;color:#c0392b;font-weight:600"></div>

        <div class="step-actions">
          <button class="btn btn-ghost" onclick="goStep(4)">← Retour</button>
          <button class="btn btn-primary" id="btn-step5-submit" onclick="validateStep5()">Finaliser mon inscription →</button>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         ÉTAPE 6 — BIENVENUE !
    ══════════════════════════════════════ -->
    <div class="step-panel" id="panel-6">

      <div class="welcome-hero">
        <div class="welcome-avatar" id="welcome-avatar">🧭</div>
        <h2 id="welcome-title">Bienvenue !</h2>
        <p id="welcome-sub">Ta légende commence. L'Esprit Vendée t'attend.</p>
        <div class="xp-badge">⚡ 50 XP de départ offerts</div>
      </div>

      <div class="recap-card">
        <div class="recap-title">Ton profil Zone 85</div>
        <div class="recap-row">
          <span class="recap-icon">👤</span>
          <span class="recap-label">Nom complet</span>
          <span class="recap-val" id="recap-nom">—</span>
        </div>
        <div class="recap-row">
          <span class="recap-icon">📍</span>
          <span class="recap-label">ADN vendéen</span>
          <span class="recap-val" id="recap-adn">—</span>
        </div>
        <div class="recap-row">
          <span class="recap-icon">🛡️</span>
          <span class="recap-label">Clan</span>
          <span class="recap-val" id="recap-clan">—</span>
          <img id="recap-clan-img" src="" alt="" class="recap-clan-img" style="display:none">
        </div>
        <div class="recap-row">
          <span class="recap-icon">🧭</span>
          <span class="recap-label">Pseudo</span>
          <span class="recap-val" id="recap-pseudo">—</span>
        </div>
        <div class="recap-row">
          <span class="recap-icon">⚡</span>
          <span class="recap-label">Bienvenue</span>
          <span class="recap-val recap-xp">+50 XP offerts</span>
        </div>
      </div>

      <div style="text-align:center">
        <a href="profil.php" class="btn btn-primary btn-lg" id="btn-go-profil">Accéder à mon profil →</a>
      </div>

    </div>
    <!-- end panels -->

  </div><!-- /insc-wrap -->
</div><!-- /insc-page -->

<?php
$page_scripts = '<script>
/* ──────────────────────────────────────────
   ZONE 85 — Inscription JS
────────────────────────────────────────── */
const formData = {
  prenom: \'\', nom: \'\', email: \'\', pw: \'\',
  identity: \'\', clan: \'\', avatar: \'🧭\', pseudo: \'\', bio: \'\'
};
let step = 1;
const TOTAL = 6;

/* ADN labels */
const adnLabels = { souche: \'De souche 🌱\', coeur: \'De cœur ❤️\', adoption: "D\'adoption 🌍" };
const clanLabels = { bocage: \'Clan Bocage\', littoral: \'Clan Littoral\', marais: \'Clan Marais\' };
const clanImages = {
  bocage:  \'' . img('mascotte-bocage.png') . '\',
  littoral:\'' . img('mascotte-littoral.png') . '\',
  marais:  \'' . img('mascotte-marais.png') . '\'
};

/* ── NAVIGATION ── */
function goStep(n) {
  document.getElementById(\'panel-\' + step).classList.remove(\'active\');
  step = n;
  document.getElementById(\'panel-\' + step).classList.add(\'active\');
  updateStepUI();
  window.scrollTo({ top: 0, behavior: \'smooth\' });
}

function updateStepUI() {
  const pct = (step / TOTAL) * 100;
  document.getElementById(\'progress-fill\').style.width = pct + \'%\';

  for (let i = 1; i <= TOTAL; i++) {
    const dot = document.getElementById(\'step-dot-\' + i);
    dot.classList.remove(\'active\', \'done\');
    if (i < step) {
      dot.classList.add(\'done\');
      dot.textContent = \'✓\';
    } else if (i === step) {
      dot.classList.add(\'active\');
      dot.textContent = i;
    } else {
      dot.textContent = i;
    }
  }
  for (let i = 1; i <= TOTAL - 1; i++) {
    const line = document.getElementById(\'step-line-\' + i);
    line.classList.toggle(\'done\', i < step);
  }
}

/* ── ÉTAPE 1 — VALIDATION ── */
function validateStep1() {
  let ok = true;

  const prenom = document.getElementById(\'prenom\').value.trim();
  const nom    = document.getElementById(\'nom\').value.trim();
  const email  = document.getElementById(\'email\').value.trim();
  const pw     = document.getElementById(\'pw\').value;
  const pw2    = document.getElementById(\'pw2\').value;

  setError(\'prenom\', \'err-prenom\', !prenom);
  setError(\'nom\',    \'err-nom\',    !nom);

  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  setError(\'email\', \'err-email\', !emailOk);

  const pwOk = pw.length >= 8;
  setError(\'pw\', \'err-pw\', !pwOk);

  const pw2Ok = pw === pw2 && pw2.length > 0;
  setError(\'pw2\', \'err-pw2\', !pw2Ok);

  if (!prenom || !nom || !emailOk || !pwOk || !pw2Ok) return;

  formData.prenom = prenom;
  formData.nom    = nom;
  formData.email  = email;
  formData.pw     = pw;
  goStep(2);
}

function setError(inputId, errId, show) {
  const input = document.getElementById(inputId);
  const err   = document.getElementById(errId);
  if (show) {
    input.classList.add(\'error\');
    input.classList.remove(\'valid\');
    err.classList.add(\'show\');
  } else {
    input.classList.remove(\'error\');
    input.classList.add(\'valid\');
    err.classList.remove(\'show\');
  }
}

/* PW TOGGLE */
function togglePw(fieldId, btn) {
  const input = document.getElementById(fieldId);
  if (input.type === \'password\') { input.type = \'text\'; btn.textContent = \'🙈\'; }
  else                           { input.type = \'password\'; btn.textContent = \'👁\'; }
}

/* ── ÉTAPE 2 — ADN ── */
function selectIdentity(val, el) {
  document.querySelectorAll(\'.identity-card\').forEach(c => c.classList.remove(\'selected\'));
  el.classList.add(\'selected\');
  formData.identity = val;
  document.getElementById(\'btn-step2\').disabled = false;
}

/* ── ÉTAPE 3 — ACCEPT ── */
let accepted = false;
function toggleAccept() {
  accepted = !accepted;
  document.getElementById(\'accept-row\').classList.toggle(\'checked\', accepted);
  document.getElementById(\'btn-step3\').disabled = !accepted;
}

/* ── ÉTAPE 4 — CLAN ── */
function selectClan(val, el) {
  document.querySelectorAll(\'.clan-option\').forEach(c => c.classList.remove(\'selected\'));
  el.classList.add(\'selected\');
  formData.clan = val;
  document.getElementById(\'btn-step4\').disabled = false;
}

/* ── ÉTAPE 5 — AVATAR + PSEUDO ── */
function switchAvatarTab(tab, btn) {
  document.querySelectorAll(\'.avatar-tab-btn\').forEach(b => b.classList.remove(\'active\'));
  btn.classList.add(\'active\');
  document.getElementById(\'avatar-panel-emoji\').classList.toggle(\'active\', tab === \'emoji\');
  document.getElementById(\'avatar-panel-upload\').classList.toggle(\'active\', tab === \'upload\');
  if (tab === \'emoji\') formData.avatarType = \'emoji\';
  else formData.avatarType = \'upload\';
}

function handlePhotoUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById(\'upload-preview\');
    const img = document.getElementById(\'upload-preview-img\');
    img.src = e.target.result;
    preview.style.display = \'block\';
    document.getElementById(\'upload-icon\').style.display = \'none\';
    document.getElementById(\'upload-text\').innerHTML = \'<strong>\' + file.name + \'</strong><br>Photo chargée — tu peux en choisir une autre.\';
    formData.avatarType = \'upload\';
    formData.photoSrc = e.target.result;
  };
  reader.readAsDataURL(file);
}

function selectAvatar(el) {
  document.querySelectorAll(\'.avatar-btn\').forEach(b => b.classList.remove(\'selected\'));
  el.classList.add(\'selected\');
  formData.avatar = el.dataset.emoji;
  formData.avatarType = \'emoji\';
}

function updateCharCount(inputId, countId, max) {
  const val = document.getElementById(inputId).value;
  document.getElementById(countId).textContent = val.length;
}

async function validateStep5() {
  const pseudo = document.getElementById(\'pseudo\').value.trim();
  if (pseudo.length < 3) {
    setError(\'pseudo\', \'err-pseudo\', true);
    document.getElementById(\'pseudo\').focus();
    return;
  }
  setError(\'pseudo\', \'err-pseudo\', false);

  const checkCgu  = document.getElementById(\'check-cgu\').checked;
  const checkRgpd = document.getElementById(\'check-rgpd\').checked;
  const errChecks = document.getElementById(\'err-checks\');
  if (!checkCgu || !checkRgpd) {
    errChecks.classList.add(\'show\');
    errChecks.scrollIntoView({ behavior: \'smooth\', block: \'center\' });
    return;
  }
  errChecks.classList.remove(\'show\');

  formData.pseudo     = pseudo;
  formData.bio        = document.getElementById(\'bio\').value.trim();
  formData.newsletter = document.getElementById(\'check-newsletter\').checked;

  const btn    = document.getElementById(\'btn-step5-submit\');
  const errDiv = document.getElementById(\'err-server\');
  btn.disabled    = true;
  btn.textContent = \'Envoi en cours…\';
  errDiv.style.display = \'none\';

  const fd = new FormData();
  fd.append(\'csrf_token\',    document.getElementById(\'csrf_token\').value);
  fd.append(\'email\',         formData.email);
  fd.append(\'password\',         formData.pw);
  fd.append(\'password_confirm\', formData.pw);
  fd.append(\'first_name\',    formData.prenom);
  fd.append(\'last_name\',     formData.nom);
  fd.append(\'pseudo\',        formData.pseudo);
  fd.append(\'bio\',           formData.bio);
  fd.append(\'clan\',          formData.clan);
  fd.append(\'avatar_type\',   formData.avatarType === \'upload\' ? \'upload\' : \'preset\');
  fd.append(\'avatar_key\',    formData.avatar || \'🧭\');
  fd.append(\'accept_cgu\',    \'1\');
  fd.append(\'accept_privacy\',\'1\');
  if (formData.newsletter) fd.append(\'newsletter\', \'1\');
  if (formData.avatarType === \'upload\') {
    const fileInput = document.getElementById(\'photo-upload\');
    if (fileInput && fileInput.files[0]) fd.append(\'avatar_photo\', fileInput.files[0]);
  }

  try {
    const resp = await fetch(\'inscription.php?action=register\', { method: \'POST\', body: fd });
    const json = await resp.json();
    if (json.ok) {
      buildWelcome();
      goStep(6);
      setTimeout(spawnConfetti, 400);
    } else {
      const msg = json.error || (json.errors ? Object.values(json.errors).join(\' \') : \'Erreur inattendue.\');
      errDiv.textContent    = msg;
      errDiv.style.display  = \'block\';
      btn.disabled          = false;
      btn.textContent       = \'Finaliser mon inscription →\';
      errDiv.scrollIntoView({ behavior: \'smooth\', block: \'center\' });
    }
  } catch(e) {
    errDiv.textContent   = \'Erreur de connexion. Vérifiez votre réseau et réessayez.\';
    errDiv.style.display = \'block\';
    btn.disabled         = false;
    btn.textContent      = \'Finaliser mon inscription →\';
  }
}

/* ── ÉTAPE 6 — BUILD WELCOME ── */
function buildWelcome() {
  document.getElementById(\'welcome-avatar\').textContent = formData.avatar;
  document.getElementById(\'welcome-title\').textContent  = \'Bienvenue \' + formData.prenom + \' !\';
  document.getElementById(\'recap-nom\').textContent   = formData.prenom + \' \' + formData.nom;
  document.getElementById(\'recap-adn\').textContent   = adnLabels[formData.identity] || \'—\';
  document.getElementById(\'recap-clan\').textContent  = clanLabels[formData.clan]    || \'—\';
  document.getElementById(\'recap-pseudo\').textContent = formData.pseudo;

  const img = document.getElementById(\'recap-clan-img\');
  if (formData.clan && clanImages[formData.clan]) {
    img.src = clanImages[formData.clan];
    img.alt = clanLabels[formData.clan];
    img.style.display = \'block\';
  }
}
</script>';
require_once 'includes/footer.php';
?>
