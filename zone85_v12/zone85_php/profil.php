<?php
$page_title       = 'Mon Profil';
$page_description = 'Consulte ta progression, tes badges, ton XP à vie et ta contribution à la Bataille des Clans sur ZONE85.';
$page_canonical   = 'https://www.zone85.fr/profil.php';
$page_robots      = 'noindex,follow';
$page_og_image    = null;
$page_schema      = null;
$current_page = 'profil';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';
require_once 'includes/auth.php';

// Seuils V10.2 — synchronises avec get_user_level_from_xp()
// Load from xp_levels table, fallback to hardcoded defaults
$_xp_levels = [0, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 20000];
$_level_names = ['','Novice','Explorateur','Aventurier','Expert','Gardien','Légende','Grand Pisteur','Vétéran','Ancêtre','Immortel'];
$_level_colors = ['','#6b7f96','#2a9d5c','#12314e','#0c6291','#9b59b6','#C9962A','#ea5649','#8b1a1a','#1a1a2e','#0c1e2e'];
$_level_emojis = ['','🌱','🧭','🏕️','⚡','🛡️','🌟','🗺️','🔥','💎','👑'];
try {
    $_lv_pdo = db();
    if ($_lv_pdo) {
        $_lv_rows = $_lv_pdo->query("SELECT level, name, xp_required, color, emoji FROM xp_levels ORDER BY level ASC")->fetchAll();
        if (count($_lv_rows) >= 10) {
            $_xp_levels = [0];
            foreach ($_lv_rows as $_lv_r) {
                $_xp_levels[(int)$_lv_r['level']] = (int)$_lv_r['xp_required'];
                $_level_names[(int)$_lv_r['level']] = $_lv_r['name'];
                $_level_colors[(int)$_lv_r['level']] = $_lv_r['color'];
                $_level_emojis[(int)$_lv_r['level']] = $_lv_r['emoji'];
            }
            // rebuild indexed array for xp_levels (0-indexed for existing code compatibility)
            $_xp_levels = array_values(array_map(fn($r) => (int)$r['xp_required'], $_lv_rows));
        }
    }
} catch (Throwable $_lv_e) { /* fallback stays */ }
$_chip_map    = ['bocage'=>'bocage-chip-sm','littoral'=>'littoral-chip-sm','marais'=>'marais-chip-sm'];
$_clan_labels = ['bocage'=>'Clan du Bocage','littoral'=>'Clan du Littoral','marais'=>'Clan du Marais'];

$is_guest = !is_logged_in();
$user     = [];
$_compte_flash = null; // flash message onglet Compte

// ── Traitement POST onglet Compte ─────────────────────────────
if (!$is_guest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compte_action'])) {
    require_login('login.php');
    $_cu  = current_user();
    $_cid = (int)$_cu['id'];
    $_cpdo = db();
    $_action = $_POST['compte_action'];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_compte_flash = ['type'=>'err','msg'=>'Token de sécurité invalide. Rechargez la page.'];
    } elseif ($_cpdo) {

        if ($_action === 'update_profile') {
            $_pseudo = safe_input($_POST['pseudo'] ?? '', 30);
            $_bio    = safe_input($_POST['bio'] ?? '', 500);
            if (mb_strlen($_pseudo) < 3) {
                $_compte_flash = ['type'=>'err','msg'=>'Le pseudo doit faire au moins 3 caractères.'];
            } else {
                $_chk = $_cpdo->prepare("SELECT id FROM users WHERE pseudo=:p AND id!=:id LIMIT 1");
                $_chk->execute([':p'=>$_pseudo,':id'=>$_cid]);
                if ($_chk->fetch()) {
                    $_compte_flash = ['type'=>'err','msg'=>'Ce pseudo est déjà utilisé.'];
                } else {
                    $_cpdo->prepare("UPDATE users SET pseudo=:p,bio=:b,updated_at=NOW() WHERE id=:id")
                          ->execute([':p'=>$_pseudo,':b'=>$_bio,':id'=>$_cid]);
                    $_SESSION['user']['pseudo'] = $_pseudo;
                    $_compte_flash = ['type'=>'ok','msg'=>'Profil mis à jour.'];
                }
            }
        }

        elseif ($_action === 'update_password') {
            $_pwd_cur  = $_POST['password_current']  ?? '';
            $_pwd_new  = $_POST['password_new']       ?? '';
            $_pwd_conf = $_POST['password_confirm']   ?? '';
            if (strlen($_pwd_new) < 8) {
                $_compte_flash = ['type'=>'err','msg'=>'8 caractères minimum.'];
            } elseif ($_pwd_new !== $_pwd_conf) {
                $_compte_flash = ['type'=>'err','msg'=>'Les mots de passe ne correspondent pas.'];
            } else {
                $_hs = $_cpdo->prepare("SELECT password_hash FROM users WHERE id=:id LIMIT 1");
                $_hs->execute([':id'=>$_cid]);
                $_hr = $_hs->fetch();
                if (!$_hr || !password_verify($_pwd_cur, $_hr['password_hash'])) {
                    $_compte_flash = ['type'=>'err','msg'=>'Mot de passe actuel incorrect.'];
                } else {
                    $_cpdo->prepare("UPDATE users SET password_hash=:h,updated_at=NOW() WHERE id=:id")
                          ->execute([':h'=>password_hash($_pwd_new, PASSWORD_BCRYPT),':id'=>$_cid]);
                    $_compte_flash = ['type'=>'ok','msg'=>'Mot de passe mis à jour.'];
                }
            }
        }

        elseif ($_action === 'upload_avatar') {
            if (!empty($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $_res = upload_avatar($_FILES['avatar_file']);
                if ($_res['ok']) {
                    $_cpdo->prepare("UPDATE users SET avatar_type='upload',avatar_file=:f,updated_at=NOW() WHERE id=:id")
                          ->execute([':f'=>$_res['path'],':id'=>$_cid]);
                    $_SESSION['user']['avatar_type'] = 'upload';
                    $_SESSION['user']['avatar_key']  = $_res['path'];
                    $_compte_flash = ['type'=>'ok','msg'=>'Photo mise à jour.'];
                } else { $_compte_flash = ['type'=>'err','msg'=>$_res['error']]; }
            } else { $_compte_flash = ['type'=>'err','msg'=>'Aucun fichier reçu.']; }
        }

        elseif ($_action === 'update_avatar_emoji') {
            $_emoji = mb_substr(trim($_POST['avatar_emoji'] ?? '🧭'), 0, 8);
            $_cpdo->prepare("UPDATE users SET avatar_type='preset',avatar_config=:c,updated_at=NOW() WHERE id=:id")
                  ->execute([':c'=>json_encode(['emoji'=>$_emoji]),':id'=>$_cid]);
            $_SESSION['user']['avatar_type'] = 'preset';
            $_SESSION['user']['avatar_key']  = $_emoji;
            $_compte_flash = ['type'=>'ok','msg'=>'Avatar mis à jour.'];
        }

        elseif ($_action === 'remove_avatar') {
            $_cpdo->prepare("UPDATE users SET avatar_type='preset',avatar_file=NULL,avatar_config=:c,updated_at=NOW() WHERE id=:id")
                  ->execute([':c'=>json_encode(['emoji'=>'🧭']),':id'=>$_cid]);
            $_SESSION['user']['avatar_type'] = 'preset';
            $_SESSION['user']['avatar_key']  = '🧭';
            $_compte_flash = ['type'=>'ok','msg'=>'Photo supprimée.'];
        }

        elseif ($_action === 'update_prefs') {
            $_cpdo->prepare("UPDATE users SET newsletter_optin=:nl,notif_missions=:nm,notif_saisons=:ns,notif_clan=:nc,notif_push=:np,digest_hebdo=:dh,updated_at=NOW() WHERE id=:id")
                  ->execute([
                    ':nl'=>isset($_POST['newsletter_optin'])?1:0,
                    ':nm'=>isset($_POST['notif_missions'])?1:0,
                    ':ns'=>isset($_POST['notif_saisons'])?1:0,
                    ':nc'=>isset($_POST['notif_clan'])?1:0,
                    ':np'=>isset($_POST['notif_push'])?1:0,
                    ':dh'=>isset($_POST['digest_hebdo'])?1:0,
                    ':id'=>$_cid,
                  ]);
            $_compte_flash = ['type'=>'ok','msg'=>'Préférences enregistrées.'];
        }
    }
    // Rediriger sur tab=compte pour éviter re-POST
    header('Location: profil.php?tab=compte' . (!empty($_compte_flash) ? '&compte_msg=' . urlencode($_compte_flash['msg']) . '&compte_type=' . $_compte_flash['type'] : ''));
    exit;
}

// Récupérer flash message après redirect POST compte
if (!$is_guest && isset($_GET['compte_msg'])) {
    $_compte_flash = ['type' => $_GET['compte_type'] ?? 'ok', 'msg' => urldecode($_GET['compte_msg'])];
}

if (!$is_guest) {
    $_session     = current_user();
    $_db_profile  = fetch_user_profile((int)$_session['id']);

    if ($_db_profile) {
        $_prenom   = $_db_profile['prenom'] ?: $_db_profile['pseudo'];
        $_nom      = $_db_profile['nom']    ?: '';
        $_initials = strtoupper(mb_substr($_prenom, 0, 1) . mb_substr($_nom, 0, 1)) ?: '??';
        $_xp       = (int)$_db_profile['xp_total'];
        $_lvl      = max(1, min((int)$_db_profile['level'], count($_xp_levels) - 1));
        $_xp_floor = $_xp_levels[$_lvl - 1] ?? 0;
        $_xp_ceil  = $_xp_levels[$_lvl]     ?? ($_xp_floor + 5000);
        $_xp_range = max(1, $_xp_ceil - $_xp_floor);
        $_xp_pct   = min(100, (int)round(($_xp - $_xp_floor) / $_xp_range * 100));

        $_clan_data = null;
        if (!empty($_db_profile['clan_slug'])) {
            $_all_clans = fetch_all_clans();
            $_clan_data = $_all_clans[$_db_profile['clan_slug']] ?? null;
        }

        // Avatar : emoji ou chemin fichier upload
        $_avatar     = $_db_profile['avatar'] ?? '🧭';
        $_avatar_type = $_db_profile['avatar_type'] ?? 'preset';

        $user = [
            'id'              => (int)$_db_profile['id'],
            'pseudo'          => $_db_profile['pseudo'],
            'first_name'      => $_prenom,
            'initials'        => $_initials,
            'avatar'          => $_avatar,
            'avatar_type'     => $_avatar_type,
            'level'           => $_lvl,
            'level_name'      => $_level_names[$_lvl] ?? 'Zonaute',
            'xp_current'      => $_xp,
            'xp_next'         => $_xp_ceil,
            'xp_pct'          => $_xp_pct,
            'badges_count'    => (int)$_db_profile['badges_count'],
            'participations'  => (int)$_db_profile['missions_done'],
            'clan_slug'       => $_db_profile['clan_slug'] ?? '',
            'clan_label'      => $_clan_labels[$_db_profile['clan_slug'] ?? ''] ?? 'Aucun clan',
            'clan_chip_class' => $_chip_map[$_db_profile['clan_slug'] ?? ''] ?? '',
            // Axe 2 sidebar : points clan réellement apportés cette saison
            // DISTINCT des XP personnels (xp_this_season)
            'season_pts'      => (int)($_db_profile['clan_pts_contributed'] ?? 0),
            'clan_rank'       => (int)$_db_profile['rank_in_clan'],
            'clan_members'    => $_clan_data ? (int)$_clan_data['members_count'] : 0,
            'clan_score'      => $_clan_data ? (int)$_clan_data['season_score']  : 0,
            'clan_place'      => $_clan_data ? (int)$_clan_data['podium_rank']   : 0,
            'bio'             => $_db_profile['bio'] ?? '',
            'joined'          => $_db_profile['joined'] ?? '',
        ];

        // XP saison (depuis le début de la saison active)
        $_xp_season = function_exists('fetch_user_xp_season')
            ? fetch_user_xp_season((int)$_db_profile['id'])
            : 0;

        $_user_badges       = fetch_user_badges((int)$_db_profile['id']);
        if (!empty($_user_badges)) $badges = $_user_badges;
        $_xp_history          = fetch_user_xp_logs((int)$_db_profile['id'], 30);
        $_user_participations = fetch_user_participations((int)$_db_profile['id'], 5);
        $_activity_feed       = function_exists('fetch_user_activity_feed') ? fetch_user_activity_feed((int)$_db_profile['id'], 10) : [];
        $_user_hidden_hunts = function_exists('fetch_user_hidden_hunts') ? fetch_user_hidden_hunts((int)$_db_profile['id']) : [];
        // V11 — Passeport (chargé en lazy dans le panneau passeport)
    } else {
        // DB non disponible — construire depuis session
        $_prenom   = $_session['pseudo'];
        $_initials = strtoupper(mb_substr($_session['pseudo'], 0, 2));
        $_lvl      = max(1, min((int)$_session['level'], count($_xp_levels) - 1));
        $_xp       = (int)$_session['xp_total'];
        $_xp_floor = $_xp_levels[$_lvl - 1] ?? 0;
        $_xp_ceil  = $_xp_levels[$_lvl]     ?? ($_xp_floor + 5000);
        $_xp_range = max(1, $_xp_ceil - $_xp_floor);
        $_xp_pct   = min(100, (int)round(($_xp - $_xp_floor) / $_xp_range * 100));

        $user = [
            'id'              => (int)$_session['id'],
            'pseudo'          => $_session['pseudo'],
            'first_name'      => $_prenom,
            'initials'        => $_initials,
            'avatar'          => $_session['avatar_key'] ?? '🧭',
            'avatar_type'     => $_session['avatar_type'] ?? 'preset',
            'level'           => $_lvl,
            'level_name'      => $_level_names[$_lvl] ?? 'Zonaute',
            'xp_current'      => $_xp,
            'xp_next'         => $_xp_ceil,
            'xp_pct'          => $_xp_pct,
            'badges_count'    => 0,
            'participations'  => 0,
            'clan_slug'       => $_session['clan_slug'] ?? '',
            'clan_label'      => $_clan_labels[$_session['clan_slug'] ?? ''] ?? 'Aucun clan',
            'clan_chip_class' => $_chip_map[$_session['clan_slug'] ?? ''] ?? '',
            'season_pts'      => 0,
            'clan_rank'       => 0,
            'clan_members'    => 0,
            'clan_score'      => 0,
            'clan_place'      => 0,
            'bio'             => '',
            'joined'          => '',
        ];
    }
}

$page_styles = '<style>
/* ── PAGE LAYOUT ── */
.profil-page { padding-top: 68px; min-height: 100vh; background: var(--beige); }
.profil-layout { max-width: 1280px; margin: 0 auto; padding: 40px 24px 80px; display: grid; grid-template-columns: 320px 1fr; gap: 28px; align-items: start; }

/* ── SIDEBAR ── */
.profil-sidebar { background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; position: sticky; top: 88px; }

/* Avatar hero — pleine largeur, centré */
.sidebar-avatar-hero { width: 100%; height: 150px; background: linear-gradient(135deg, var(--navy-dark), #1e3a5f); display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; flex-shrink: 0; }
.sidebar-avatar-hero::after { content: \'\'; position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,.25)); }
.sidebar-avatar-hero img { width: 100%; height: 100%; object-fit: cover; }
.sidebar-avatar-emoji { font-size: 4.5rem; line-height: 1; position: relative; z-index: 1; }

/* Identité */
.sidebar-identity { padding: 16px 20px 14px; border-bottom: 1px solid var(--beige-dark); }
.sidebar-name { font-size: 1.1rem; font-weight: 900; color: var(--text); letter-spacing: -.3px; display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
.level-badge { display: inline-block; background: var(--primary); color: #fff; font-size: .62rem; font-weight: 800; padding: 3px 9px; border-radius: 4px; letter-spacing: .06em; flex-shrink: 0; }

/* Axes — blocs cliquables */
.sidebar-axe { padding: 16px 20px; border-bottom: 1px solid var(--beige-dark); cursor: pointer; transition: background .15s; text-decoration: none; display: block; }
.sidebar-axe:hover { background: rgba(234,86,73,.04); }
.sidebar-axe-label { font-size: .6rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px; display: flex; align-items: center; gap: 5px; }
.sidebar-axe-val { font-size: 1.55rem; font-weight: 900; letter-spacing: -.5px; line-height: 1.1; margin-bottom: 2px; }
.sidebar-axe-val-xp { color: var(--primary); }
.sidebar-axe-val-clan { color: #b8831a; }
.sidebar-axe-val-total { color: var(--navy-dark); }
.sidebar-axe-sub { font-size: .74rem; color: var(--text-mid); margin-bottom: 8px; line-height: 1.4; }
.sidebar-axe-bar-track { height: 6px; background: var(--beige-dark); border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
.sidebar-axe-bar-fill { height: 100%; border-radius: 4px; width: 0; transition: width 1.2s cubic-bezier(.22,1,.36,1); }
.fill-xp   { background: linear-gradient(90deg, #c94038, #ea5649); }
.fill-clan { background: linear-gradient(90deg, #b8831a, #e0a82a); }
.fill-total{ background: linear-gradient(90deg, #163756, #1e4f7a); }
.sidebar-axe-hint { font-size: .72rem; font-weight: 700; color: var(--primary); }
.sidebar-axe-clan .sidebar-axe-hint { color: #b8831a; }
.sidebar-axe-total .sidebar-axe-hint { color: var(--navy-mid); }

/* Stats compactes */
.sidebar-stats-mini { display: flex; gap: 0; border-bottom: 1px solid var(--beige-dark); }
.sidebar-stat-mini { flex: 1; padding: 10px 8px; text-align: center; border-right: 1px solid var(--beige-dark); }
.sidebar-stat-mini:last-child { border-right: none; }
.sidebar-stat-val { display: block; font-size: .92rem; font-weight: 900; color: var(--text); line-height: 1.1; }
.sidebar-stat-lbl { display: block; font-size: .56rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-top: 2px; }

/* Nav */
.sidebar-separator { border: none; border-top: 1px solid var(--beige-dark); margin: 0; }
.sidebar-nav { display: flex; flex-direction: column; gap: 0; padding: 8px 12px 12px; }
.sidebar-nav-link { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: var(--radius-sm); font-size: .86rem; font-weight: 600; color: var(--text-mid); cursor: pointer; transition: all .18s; border: none; background: none; font-family: \'Inter\', sans-serif; width: 100%; text-align: left; }
.sidebar-nav-link:hover { background: var(--beige); color: var(--text); }
.sidebar-nav-link.active { background: rgba(234,86,73,.08); color: var(--primary); font-weight: 800; }
.sidebar-nav-icon { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }

/* compat anciens sélecteurs */
.sidebar-section-label { display: none; }
.xp-bar-wrap,.sidebar-stats-row,.clan-contribution-val,.clan-contribution-sub,.clan-rank-line { display: none; }

/* ── MAIN CONTENT ── */
.profil-content { min-width: 0; }

/* ── TAB PANEL SHARED ── */
.profil-panel { animation: fadeUp .3s ease both; }
.profil-card { background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 28px; margin-bottom: 20px; }
.profil-card-title { font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

/* ── TAB 1 : APERÇU ── */
.welcome-card { background: linear-gradient(135deg, var(--navy-dark), var(--navy-mid)); border-radius: var(--radius-lg); padding: 28px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; position: relative; overflow: hidden; }
.welcome-card::after { content: \'\'; position: absolute; top: -40px; right: -40px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,.04); }
.welcome-avatar { width: 56px; height: 56px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; position: relative; z-index: 1; }
.welcome-text { position: relative; z-index: 1; }
.welcome-text h2 { font-size: 1.2rem; font-weight: 900; color: #fff; letter-spacing: -.3px; margin-bottom: 4px; }
.welcome-text p { font-size: .82rem; color: rgba(255,255,255,.6); line-height: 1.5; }
.welcome-season { margin-left: auto; flex-shrink: 0; position: relative; z-index: 1; text-align: right; }
.welcome-season-label { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.35); margin-bottom: 4px; }
.welcome-season-name { font-size: .88rem; font-weight: 800; color: rgba(255,255,255,.85); }

.mission-item { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--beige-dark); }
.mission-item:last-child { border-bottom: none; padding-bottom: 0; }
.mission-item:first-child { padding-top: 0; }
.mission-icon { font-size: 1.3rem; width: 36px; height: 36px; background: var(--beige); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mission-info { flex: 1; min-width: 0; }
.mission-name { font-size: .9rem; font-weight: 700; color: var(--text); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mission-sub { font-size: .72rem; color: var(--text-muted); margin-bottom: 6px; }
.mission-prog-track { height: 5px; background: var(--beige-dark); border-radius: 4px; overflow: hidden; }
.mission-prog-fill { height: 100%; border-radius: 4px; background: var(--primary); width: 0; transition: width 1.2s cubic-bezier(.22,1,.36,1); }
.mission-xp { font-size: .8rem; font-weight: 800; color: var(--primary); white-space: nowrap; flex-shrink: 0; }

.feed-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--beige-dark); }
.feed-item:last-child { border-bottom: none; padding-bottom: 0; }
.feed-item:first-child { padding-top: 0; }
.feed-icon { font-size: 1.1rem; width: 32px; height: 32px; background: var(--beige); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
.feed-info { flex: 1; }
.feed-title { font-size: .88rem; font-weight: 700; color: var(--text); margin-bottom: 2px; }
.feed-meta { font-size: .72rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
.feed-xp { display: inline-block; background: rgba(234,86,73,.1); color: var(--primary); font-size: .68rem; font-weight: 800; padding: 1px 7px; border-radius: 3px; }

/* ── TAB 2 : PROGRESSION ── */
.roadmap-wrap { position: relative; padding: 16px 0 32px; overflow-x: auto; }
.roadmap-line { display: flex; align-items: center; gap: 0; min-width: 640px; }
.roadmap-node { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
.roadmap-node::before { content: \'\'; position: absolute; top: 20px; right: -50%; width: 100%; height: 3px; background: var(--beige-dark); z-index: 0; }
.roadmap-node:last-child::before { display: none; }
.roadmap-node.done::before { background: var(--primary); }
.roadmap-dot { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .9rem; font-weight: 900; border: 3px solid var(--beige-dark); background: var(--white); color: var(--text-muted); position: relative; z-index: 1; flex-shrink: 0; transition: all .3s; }
.roadmap-node.done .roadmap-dot { background: var(--primary); border-color: var(--primary); color: #fff; font-size: .8rem; }
.roadmap-node.current .roadmap-dot { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 0 5px rgba(234,86,73,.2); font-size: .75rem; }
.roadmap-label { margin-top: 10px; text-align: center; font-size: .72rem; font-weight: 700; color: var(--text-muted); line-height: 1.3; }
.roadmap-node.done .roadmap-label { color: var(--text-mid); }
.roadmap-node.current .roadmap-label { color: var(--primary); font-weight: 900; }
.roadmap-xp-req { font-size: .65rem; font-weight: 600; color: var(--text-muted); margin-top: 3px; }
.roadmap-node.current .roadmap-xp-req { color: rgba(234,86,73,.6); }
.next-level-banner { background: rgba(234,86,73,.07); border: 1px solid rgba(234,86,73,.18); border-radius: var(--radius); padding: 14px 18px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: .88rem; font-weight: 600; color: var(--text); }
.next-level-banner strong { color: var(--primary); }
.xp-table { width: 100%; border-collapse: collapse; }
.xp-table th { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--text-muted); padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--beige-dark); background: var(--beige); }
.xp-table th:first-child { border-radius: var(--radius-sm) 0 0 0; }
.xp-table th:last-child  { border-radius: 0 var(--radius-sm) 0 0; }
.xp-table td { padding: 10px 12px; font-size: .85rem; color: var(--text-mid); border-bottom: 1px solid var(--beige-dark); vertical-align: middle; }
.xp-table tr:last-child td { border-bottom: none; }
.xp-table tr:nth-child(even) td { background: rgba(245,241,237,.5); }
.xp-amount { font-weight: 800; color: var(--primary); white-space: nowrap; }

/* ── TAB 3 : BADGES ── */
.badges-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.badge-card { background: var(--beige); border-radius: var(--radius); padding: 18px 14px; text-align: center; border: 1px solid var(--beige-dark); transition: transform .2s, box-shadow .2s; }
.badge-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); }
.badge-emoji { font-size: 1.8rem; display: block; margin-bottom: 8px; }
.badge-name { font-size: .82rem; font-weight: 800; color: var(--text); margin-bottom: 4px; line-height: 1.3; }
.badge-date { font-size: .65rem; color: var(--text-muted); margin-bottom: 5px; }
.badge-xp { display: inline-block; background: rgba(234,86,73,.1); color: var(--primary); font-size: .65rem; font-weight: 800; padding: 2px 8px; border-radius: 3px; }
.badge-card.locked { opacity: .55; background: var(--beige-dark); border-color: transparent; cursor: default; }
.badge-card.locked:hover { transform: none; box-shadow: none; }
.badge-card.locked .badge-name { color: var(--text-muted); }
.badge-lock { font-size: .65rem; color: var(--text-muted); margin-bottom: 4px; }
.badge-prog { font-size: .65rem; color: var(--text-muted); margin-top: 4px; }
.badge-prog-track { height: 4px; background: rgba(0,0,0,.1); border-radius: 3px; overflow: hidden; margin-top: 5px; }
.badge-prog-fill { height: 100%; background: var(--text-muted); border-radius: 3px; }

/* ── TAB 4 : ACTIVITÉ ── */
.activity-chart { display: flex; align-items: flex-end; gap: 16px; height: 120px; padding: 0 8px; margin-bottom: 8px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.chart-bar-wrap { width: 100%; display: flex; align-items: flex-end; justify-content: center; flex: 1; }
.chart-bar { width: 64%; border-radius: 4px 4px 0 0; background: var(--primary); height: 0; transition: height 1s cubic-bezier(.22,1,.36,1); min-height: 4px; opacity: .85; }
.chart-bar.highlight { opacity: 1; background: var(--primary-dark); }
.chart-label { font-size: .68rem; font-weight: 700; color: var(--text-muted); text-align: center; margin-top: 6px; white-space: nowrap; }
.chart-val { font-size: .75rem; font-weight: 800; color: var(--primary); text-align: center; margin-bottom: 4px; }
.activity-axis { display: flex; gap: 16px; padding: 0 8px; border-top: 1px solid var(--beige-dark); margin-bottom: 28px; }
.activity-axis-col { flex: 1; text-align: center; padding-top: 6px; font-size: .68rem; color: var(--text-muted); font-weight: 600; }
.activity-filter { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
.activity-filter-btn { padding: 6px 14px; border-radius: var(--radius-sm); font-size: .78rem; font-weight: 700; cursor: pointer; border: 2px solid var(--beige-dark); background: var(--white); color: var(--text-mid); font-family: \'Inter\', sans-serif; transition: all .18s; }
.activity-filter-btn:hover { border-color: var(--primary); color: var(--primary); }
.activity-filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

/* ── CTA BAS ── */
.profil-cta { background: var(--primary); border-radius: var(--radius-lg); padding: 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; position: relative; overflow: hidden; }
.profil-cta::before { content: \'\'; position: absolute; top: -30px; right: -30px; width: 140px; height: 140px; border-radius: 50%; background: rgba(255,255,255,.06); }
.profil-cta-text { position: relative; z-index: 1; }
.profil-cta-label { font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgba(255,255,255,.55); margin-bottom: 4px; }
.profil-cta-title { font-size: 1.05rem; font-weight: 900; color: #fff; margin-bottom: 4px; letter-spacing: -.3px; }
.profil-cta-score { font-size: .82rem; color: rgba(255,255,255,.75); font-weight: 600; }
.profil-cta-score strong { color: #fff; font-weight: 900; }
.profil-cta-action { position: relative; z-index: 1; flex-shrink: 0; }
.btn-cta-white { background: #fff; color: var(--primary); border: none; padding: 12px 22px; border-radius: var(--radius-sm); font-weight: 800; font-size: .88rem; font-family: \'Inter\', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; text-decoration: none; white-space: nowrap; }
.btn-cta-white:hover { background: var(--beige-light); transform: translateY(-1px); }

/* ── RESPONSIVE ── */
@media(max-width: 900px) {
  .profil-layout { grid-template-columns: 1fr; padding: 24px 16px 60px; gap: 20px; }
  .profil-sidebar { position: static; }
  .sidebar-name { font-size: .95rem; }
  .sidebar-axe-val { font-size: 1.25rem; }
  .sidebar-avatar-hero { height: 120px; }
  .sidebar-nav { flex-direction: row; flex-wrap: wrap; gap: 4px; padding: 6px 8px 8px; }
  .sidebar-nav-link { flex: 1 1 auto; min-width: 90px; justify-content: center; font-size: .76rem; padding: 7px 8px; }
  .sidebar-nav-icon { display: none; }
  .badges-grid { grid-template-columns: repeat(2, 1fr); }
  .welcome-season { display: none; }
  .profil-cta { flex-direction: column; text-align: center; }
  /* Tab Compte mobile */
  .mc-row { grid-template-columns: 1fr !important; }
}
@media(max-width: 480px) {
  .profil-layout { padding: 16px 12px 60px; }
  .badges-grid { grid-template-columns: repeat(2, 1fr); }
  .profil-card { padding: 18px 16px; }
  .sidebar-stats-mini { flex-direction: row; }
  .sidebar-stat-mini { flex: 1; padding: 8px 4px; }
  .sidebar-axe { padding: 12px 16px; }
  .sidebar-axe-val { font-size: 1.1rem; }
  /* Roadmap horizontal scroll sur mobile */
  .roadmap-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .roadmap-line { min-width: 560px; }
}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<?php if ($is_guest): ?>
<!-- ── ÉTAT NON CONNECTÉ ─────────────────────────────────── -->
<div class="profil-page" style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 68px)">
  <div style="max-width:480px;width:100%;padding:0 20px;text-align:center">
    <div style="width:80px;height:80px;background:var(--navy-dark);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 24px">🛡️</div>
    <h1 style="font-size:clamp(1.6rem,4vw,2.2rem);font-weight:900;color:var(--navy-dark);letter-spacing:-.5px;margin-bottom:10px">Ton profil t'attend</h1>
    <p style="font-size:.95rem;color:var(--text-muted);margin-bottom:32px;line-height:1.7">
      Connecte-toi pour voir ta progression, tes badges et ta contribution à la Bataille des Clans.
    </p>
    <div style="display:flex;flex-direction:column;gap:12px;max-width:300px;margin:0 auto">
      <a href="login.php" class="btn btn-primary btn-lg" style="text-align:center">Se connecter →</a>
      <a href="inscription.php" class="btn btn-ghost" style="text-align:center">Rejoindre la Zone</a>
    </div>
    <p style="margin-top:28px;font-size:.78rem;color:var(--text-muted);font-style:italic">"Je progresse pour moi. Je fais gagner mon clan."</p>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
<?php exit; ?>
<?php endif; ?>

<?php if (defined('APP_ENV') && APP_ENV === 'dev' && !$is_guest): ?>
<!-- avatar-debug: type=<?= e($user['avatar_type'] ?? '') ?> file=<?= e($user['avatar'] ?? '') ?> url=<?= e($_profil_avatar_url ?? '') ?> -->
<?php endif; ?>

<div class="profil-page">
  <div class="profil-layout">

    <!-- ── SIDEBAR ── -->
    <aside class="profil-sidebar">

      <?php $_profil_avatar_url = avatar_url($user); ?>

      <!-- Avatar hero pleine largeur -->
      <div class="sidebar-avatar-hero">
        <?php if (!empty($_profil_avatar_url)): ?>
          <img src="<?= e($_profil_avatar_url) ?>" alt="<?= e($user['pseudo']) ?>">
        <?php else: ?>
          <div class="sidebar-avatar-emoji"><?= e($user['avatar']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Identité -->
      <div class="sidebar-identity">
        <div class="sidebar-name">
          <?= e($user['pseudo']) ?>
          <span class="level-badge">Niv. <?= e($user['level']) ?></span>
        </div>
        <span class="<?= e($user['clan_chip_class']) ?>"><?= e($user['clan_label']) ?></span>
      </div>

      <!-- Axe 1 : Mes XP personnels -->
      <div class="sidebar-axe" onclick="switchTab('profil','progression')" role="button" tabindex="0" title="Voir ma progression">
        <div class="sidebar-axe-label">⚡ Mes XP personnels</div>
        <div class="sidebar-axe-val sidebar-axe-val-xp"><?= format_xp($user['xp_current']) ?></div>
        <div class="sidebar-axe-sub"><?= e($user['level_name']) ?> · niveau <?= e($user['level']) ?></div>
        <div class="sidebar-axe-bar-track">
          <div class="sidebar-axe-bar-fill fill-xp" data-fill="<?= e($user['xp_pct']) ?>" style="width:<?= e($user['xp_pct']) ?>%"></div>
        </div>
        <div class="sidebar-axe-hint">Voir ma progression →</div>
      </div>

      <!-- Axe 2 : Ma contribution au clan -->
      <div class="sidebar-axe sidebar-axe-clan" onclick="switchTab('profil','activite')" role="button" tabindex="0" title="Voir mon activité">
        <div class="sidebar-axe-label">🛡️ Ma contribution au clan <span class="live-badge" style="margin-left:4px">LIVE</span></div>
        <?php $_clan_contrib = (int)$user['season_pts']; ?>
        <?php if ($_clan_contrib > 0): ?>
        <div class="sidebar-axe-val sidebar-axe-val-clan">+<?= number_format($_clan_contrib, 0, ',', ' ') ?> pts</div>
        <div class="sidebar-axe-sub">points apportés au <?= e($user['clan_label']) ?><?php if (!empty($active_season['title'])): ?> — <?= e($active_season['title']) ?><?php endif; ?></div>
        <?php else: ?>
        <div class="sidebar-axe-val sidebar-axe-val-clan" style="font-size:1.1rem;color:var(--text-muted)">0 pt</div>
        <div class="sidebar-axe-sub">Participez à une mission pour contribuer au <?= e($user['clan_label']) ?></div>
        <?php endif; ?>
        <?php if ((int)$user['clan_rank'] > 0 && (int)$user['clan_members'] > 0): ?>
        <div class="sidebar-axe-bar-track">
          <?php $_axe2_pct = min(100, round((1 - ($user['clan_rank'] - 1) / max(1, $user['clan_members'])) * 100)); ?>
          <div class="sidebar-axe-bar-fill fill-clan" style="width:<?= $_axe2_pct ?>%"></div>
        </div>
        <div class="sidebar-axe-hint">🏅 <?= (int)$user['clan_rank'] ?>e sur <?= (int)$user['clan_members'] ?> membres →</div>
        <?php else: ?>
        <div class="sidebar-axe-hint">Voir mon activité →</div>
        <?php endif; ?>
      </div>

      <!-- Axe 3 : Score total du clan -->
      <?php $_clan_total = (int)($user['clan_score'] ?? 0); ?>
      <div class="sidebar-axe sidebar-axe-total" onclick="location.href='communaute.php?tab=classement'" role="button" tabindex="0" title="Voir le classement">
        <div class="sidebar-axe-label">🏆 Score de mon clan</div>
        <div class="sidebar-axe-val sidebar-axe-val-total"><?= number_format($_clan_total, 0, ',', ' ') ?> pts</div>
        <div class="sidebar-axe-sub"><?= e($user['clan_label']) ?> — saison en cours</div>
        <?php if ($user['clan_place'] > 0): ?>
        <div class="sidebar-axe-hint">📊 <?= (int)$user['clan_place'] ?>e au classement →</div>
        <?php else: ?>
        <div class="sidebar-axe-hint">Voir le classement →</div>
        <?php endif; ?>
      </div>

      <!-- Stats compactes -->
      <div class="sidebar-stats-mini">
        <div class="sidebar-stat-mini">
          <span class="sidebar-stat-val"><?= number_format((int)$user['xp_current'], 0, ',', ' ') ?></span>
          <span class="sidebar-stat-lbl">XP à vie</span>
        </div>
        <div class="sidebar-stat-mini">
          <span class="sidebar-stat-val"><?= e($user['badges_count']) ?></span>
          <span class="sidebar-stat-lbl">Badges</span>
        </div>
        <div class="sidebar-stat-mini">
          <span class="sidebar-stat-val"><?= e($user['participations']) ?></span>
          <span class="sidebar-stat-lbl">Participations</span>
        </div>
      </div>

      <!-- Nav tabs -->
      <nav class="sidebar-nav">
        <button class="sidebar-nav-link active" data-tab-group="profil" data-tab-id="apercu" onclick="switchTab('profil','apercu')">
          <span class="sidebar-nav-icon">👁</span> Aperçu
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="progression" onclick="switchTab('profil','progression')">
          <span class="sidebar-nav-icon">📈</span> Progression
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="badges" onclick="switchTab('profil','badges')">
          <span class="sidebar-nav-icon">🏅</span> Badges
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="activite" onclick="switchTab('profil','activite')">
          <span class="sidebar-nav-icon">📋</span> Activité
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="passeport" onclick="switchTab('profil','passeport')">
          <span class="sidebar-nav-icon">🗺️</span> Passeport
        </button>
        <button class="sidebar-nav-link" data-tab-group="profil" data-tab-id="compte"
          onclick="switchTab('profil','compte')"
          style="margin-top:4px;border-top:1px solid var(--beige-dark);padding-top:10px">
          <span class="sidebar-nav-icon">⚙️</span> Mon Compte
        </button>
      </nav>

    </aside>

    <!-- ── CONTENU PRINCIPAL ── -->
    <main class="profil-content">

      <!-- ══════════════════════════════
           TAB 1 — APERÇU
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="apercu" class="profil-panel">

        <?php
        // ── Onboarding : affiché uniquement à la 1ère connexion ──
        $_show_onboarding = !$is_guest
            && isset($_SESSION['user']['id'])
            && empty($_SESSION['onboarding_done'])
            && (int)($user['participations'] ?? 0) === 0;
        if ($_show_onboarding): $_SESSION['onboarding_done'] = true; ?>
        <div style="background:linear-gradient(135deg,#0c1e2e,#1e3a5c);border-radius:var(--radius-lg);
          padding:28px;margin-bottom:20px;position:relative;overflow:hidden">
          <div style="position:absolute;top:-20px;right:-20px;width:120px;height:120px;
            border-radius:50%;background:rgba(234,86,73,.08)"></div>
          <div style="font-size:.68rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase;
            color:rgba(234,86,73,.9);margin-bottom:12px">Bienvenue dans la Zone !</div>
          <div style="font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:18px;
            letter-spacing:-.2px">Par où commencer&nbsp;?</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;
            margin-bottom:20px">
            <?php
            $onboarding_steps = [
              ['🎯','Découvre les missions','Participe à une mission et gagne tes premiers XP.',   'missions.php'],
              ['🛡️','Rejoins la course des clans','Ton clan a besoin de toi pour grimper au classement.','clans.php'],
              ['🥐','Essaie le KTC','Un mystère vendéen t\'attend. Sauras-tu le résoudre ?',       'ktc.php'],
            ];
            foreach ($onboarding_steps as [$icon,$titre,$desc,$lien]):
            ?>
            <a href="<?= $lien ?>" style="background:rgba(255,255,255,.07);border-radius:10px;
              padding:14px;text-decoration:none;border:1px solid rgba(255,255,255,.1);
              transition:background .15s;display:block"
              onmouseover="this.style.background='rgba(255,255,255,.12)'"
              onmouseout="this.style.background='rgba(255,255,255,.07)'">
              <div style="font-size:1.4rem;margin-bottom:8px"><?= $icon ?></div>
              <div style="font-size:.84rem;font-weight:800;color:#fff;margin-bottom:4px"><?= $titre ?></div>
              <div style="font-size:.74rem;color:rgba(255,255,255,.5);line-height:1.5"><?= $desc ?></div>
            </a>
            <?php endforeach; ?>
          </div>
          <a href="concept.php" style="font-size:.78rem;font-weight:700;color:rgba(255,255,255,.4);
            text-decoration:none">En savoir plus sur Zone85 →</a>
        </div>
        <?php endif; ?>

        <div class="welcome-card">
          <div class="welcome-avatar" style="<?= ($user['avatar_type'] === 'upload') ? 'padding:0;overflow:hidden' : '' ?>">
            <?php if (!empty($_profil_avatar_url)): ?>
              <img src="<?= e($_profil_avatar_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <?= e($user['avatar']) ?>
            <?php endif; ?>
          </div>
          <div class="welcome-text">
            <h2>Bonjour <?= e($user['first_name']) ?> !</h2>
            <p>Je progresse pour moi. Je fais gagner mon clan.</p>
          </div>
          <div class="welcome-season">
            <div class="welcome-season-label">Saison en cours</div>
            <div class="welcome-season-name">☀️ <?= e($active_season['title']) ?></div>
          </div>
        </div>

        <!-- Feed activité unifié -->
        <div class="profil-card">
          <div class="profil-card-title">⚡ Activité récente</div>

          <?php
          $_feed_icons = [
              // feed_type → icône par défaut
              'rando'   => '🥾',
              'comment' => '💬',
              // mission_type icons (feed_sub pour les missions)
              'seasonal_collective' => '🏆', 'quiz' => '🧠', 'vote' => '🗳️',
              'photo_challenge' => '📸', 'keto_kole_tche' => '🥐',
              'weather_mission' => '🌤️', 'investigation' => '🔍',
              'hidden_hunt' => '🗝️', 'premium_game' => '⭐',
              // source_type icons (feed_sub pour les xp_events)
              'registration' => '🎉', 'badge' => '🏅', 'admin' => '✨',
              'article_comment' => '💬', 'rando_review' => '🥾',
              'mission_participation' => '⚡', 'mission_success' => '✅',
              'ktc_correct' => '🔍', 'hidden_hunt_completion' => '🗝️',
          ];
          $_feed_status = [
              'auto_validated' => ['Validé',     '#1a7a42'],
              'validated'      => ['Validé',     '#1a7a42'],
              'pending'        => ['En attente', '#0369a1'],
              'rejected'       => ['Refusé',     '#c0392b'],
              'stamped'        => ['Stampé',     '#6b7f96'],
              'pending_proof'  => ['Preuve en attente', '#8a6020'],
              'rewarded'       => ['Récompensé', '#1a7a42'],
          ];
          if (!empty($_activity_feed)):
              foreach ($_activity_feed as $_ev):
                  $_ev_type  = $_ev['feed_type'];
                  $_ev_sub   = $_ev['feed_sub'] ?? '';
                  $_ev_icon  = $_feed_icons[$_ev_sub] ?? $_feed_icons[$_ev_type] ?? '⚡';
                  $_ev_title = e(mb_strimwidth($_ev['feed_title'] ?? '—', 0, 60, '…'));
                  $_ev_xp    = (int)($_ev['feed_xp'] ?? 0);
                  $_ev_date  = $_ev['feed_date'] ? date('d/m/Y', strtotime($_ev['feed_date'])) : '';
                  $_ev_st    = $_feed_status[$_ev['feed_status'] ?? ''] ?? null;
          ?>
          <div class="feed-item">
            <div class="feed-icon"><?= $_ev_icon ?></div>
            <div class="feed-info">
              <div class="feed-title"><?= $_ev_title ?></div>
              <div class="feed-meta">
                <?php if ($_ev_st): ?>
                <span style="color:<?= $_ev_st[1] ?>;font-weight:700;font-size:.72rem"><?= $_ev_st[0] ?></span>
                <?php endif; ?>
                <?php if ($_ev_xp !== 0): ?>
                <span class="feed-xp"><?= $_ev_xp > 0 ? '+' : '' ?><?= $_ev_xp ?> XP</span>
                <?php endif; ?>
                <?= $_ev_date ? ' · ' . e($_ev_date) : '' ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="text-align:center;padding:24px 16px;color:var(--text-muted)">
            <div style="font-size:1.8rem;margin-bottom:8px">⚡</div>
            <div style="font-size:.88rem;font-weight:600">Tes premières activités apparaîtront ici.</div>
            <div style="font-size:.78rem;margin-top:6px">
              <a href="missions.php" style="color:var(--primary);font-weight:700;text-decoration:none">Voir les missions disponibles →</a>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($_user_hidden_hunts)): ?>
        <div class="profil-card">
          <div class="profil-card-title">🗝️ Mes jeux de piste</div>
          <?php foreach ($_user_hidden_hunts as $_hh):
            $_hh_pct  = ($_hh['total'] > 0) ? min(100, round($_hh['found'] / $_hh['total'] * 100)) : 0;
            $_hh_done = (int)$_hh['found'] >= (int)$_hh['total'] && (int)$_hh['total'] > 0;
          ?>
          <div style="display:flex;align-items:center;gap:14px;padding:16px 0;border-bottom:1px solid var(--beige-dark)">
            <!-- Icône mission -->
            <div style="width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,#0c1e2e,#12314e);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">🗝️</div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
                <a href="mission.php?id=<?= (int)$_hh['id'] ?>" style="font-size:.9rem;font-weight:800;color:var(--navy-dark);text-decoration:none;line-height:1.3">
                  <?= e($_hh['title']) ?>
                </a>
                <?php if ($_hh_done): ?>
                <span style="font-size:.68rem;font-weight:800;color:#1a7a42;background:rgba(42,157,92,.12);padding:2px 8px;border-radius:999px;white-space:nowrap">✅ Terminée</span>
                <?php elseif (($_hh['status'] ?? '') === 'active'): ?>
                <span style="font-size:.68rem;font-weight:800;color:#0369a1;background:rgba(14,165,233,.1);padding:2px 8px;border-radius:999px;white-space:nowrap">🔍 En cours</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <div style="flex:1;height:6px;background:#f0ece7;border-radius:8px;overflow:hidden">
                  <div class="mission-prog-fill" data-prog="<?= $_hh_pct ?>" style="height:100%;background:<?= $_hh_done ? 'linear-gradient(90deg,#2a9d5c,#22c55e)' : 'linear-gradient(90deg,#c94038,#ea5649)' ?>;border-radius:8px;width:0;transition:width .8s cubic-bezier(.22,1,.36,1)"></div>
                </div>
                <span style="font-size:.78rem;font-weight:900;color:<?= $_hh_done ? '#1a7a42' : 'var(--primary)' ?>;white-space:nowrap"><?= (int)$_hh['found'] ?> / <?= (int)$_hh['total'] ?></span>
              </div>
              <?php if (!empty($_hh['part_xp']) && (int)$_hh['part_xp'] > 0): ?>
              <div style="font-size:.72rem;color:var(--primary);font-weight:700">⚡ +<?= (int)$_hh['part_xp'] ?> XP gagnés</div>
              <?php endif; ?>
            </div>
            <?php if (!$_hh_done && ($_hh['status'] ?? '') === 'active'): ?>
            <a href="<?= url('mission.php?id=' . (int)$_hh['id']) ?>" style="font-size:.72rem;font-weight:800;color:#fff;background:var(--primary);padding:6px 12px;border-radius:8px;text-decoration:none;white-space:nowrap;flex-shrink:0">Continuer →</a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if (count($_user_hidden_hunts) > 0): ?>
          <div style="margin-top:14px;text-align:right">
            <a href="missions.php" style="font-size:.78rem;color:var(--primary);font-weight:700;text-decoration:none">Voir toutes les missions →</a>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="profil-cta">
          <div class="profil-cta-text">
            <div class="profil-cta-label">Saison <?= e($active_season['title']) ?></div>
            <div class="profil-cta-title">Tu joues pour le <?= e($user['clan_label']) ?></div>
            <div class="profil-cta-score">Score clan : <strong><?= format_score($user['clan_score']) ?> pts</strong> — Place <strong>n°<?= e($user['clan_place']) ?></strong> 🥇</div>
          </div>
          <div class="profil-cta-action">
            <a href="clans.php" class="btn-cta-white">Voir la course des clans →</a>
          </div>
        </div>

      </div><!-- /panel apercu -->


      <!-- ══════════════════════════════
           TAB 2 — PROGRESSION
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="progression" class="profil-panel" style="display:none">

        <!-- Level hero card -->
        <?php
        $_hero_lv    = (int)$user['level'];
        $_hero_color = $_level_colors[$_hero_lv] ?? '#6b7f96';
        $_hero_emoji = $_level_emojis[$_hero_lv] ?? '⭐';
        $_hero_name  = $_level_names[$_hero_lv] ?? 'Niveau ' . $_hero_lv;
        $_hero_xp    = (int)$user['xp_current'];
        $_hero_floor = $_xp_levels[$_hero_lv - 1] ?? 0;
        $_hero_ceil  = $_xp_levels[$_hero_lv]     ?? ($_hero_floor + 5000);
        $_hero_range = max(1, $_hero_ceil - $_hero_floor);
        $_hero_pct   = min(100, (int)round(($_hero_xp - $_hero_floor) / $_hero_range * 100));
        $_hero_left  = max(0, $_hero_ceil - $_hero_xp);
        $_next_emoji = $_level_emojis[$_hero_lv + 1] ?? '';
        $_next_name  = $_level_names[$_hero_lv + 1]  ?? '';
        ?>
        <div style="background:<?= e($_hero_color) ?>;border-radius:var(--radius-lg);padding:28px;margin-bottom:20px;position:relative;overflow:hidden">
          <div style="position:absolute;top:-30px;right:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.07)"></div>
          <div style="position:absolute;bottom:-20px;left:60px;width:100px;height:100px;border-radius:50%;background:rgba(0,0,0,.08)"></div>
          <div style="position:relative;z-index:1">
            <div style="font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:10px">Mon niveau actuel</div>
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap">
              <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:2.4rem;flex-shrink:0;border:3px solid rgba(255,255,255,.25)"><?= e($_hero_emoji) ?></div>
              <div>
                <div style="font-size:.75rem;font-weight:800;color:rgba(255,255,255,.6);letter-spacing:.05em;text-transform:uppercase">Niveau <?= $_hero_lv ?></div>
                <div style="font-size:1.8rem;font-weight:900;color:#fff;letter-spacing:-.5px;line-height:1"><?= e($_hero_name) ?></div>
                <div style="font-size:.82rem;color:rgba(255,255,255,.6);margin-top:4px;font-weight:600"><?= number_format($_hero_xp, 0, ',', ' ') ?> XP à vie</div>
              </div>
            </div>
            <!-- Progress bar to next level -->
            <?php if ($_hero_lv < 10): ?>
            <div style="margin-bottom:10px">
              <div style="background:rgba(0,0,0,.25);border-radius:20px;height:12px;overflow:hidden;margin-bottom:8px">
                <div style="width:<?= $_hero_pct ?>%;height:100%;background:rgba(255,255,255,.5);border-radius:20px;transition:width .8s cubic-bezier(.22,1,.36,1)"></div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.7)">
                <span><?= $_hero_pct ?>% vers <?= e($_next_emoji) ?> <?= e($_next_name) ?></span>
                <span><?= number_format($_hero_left, 0, ',', ' ') ?> XP manquants</span>
              </div>
            </div>
            <?php else: ?>
            <div style="font-size:.9rem;font-weight:800;color:rgba(255,255,255,.9)">👑 Niveau maximum atteint !</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Progression saisonnière -->
        <?php if (isset($_xp_season)): ?>
        <?php
        $_szn_xp    = $_xp_season;
        $_szn_lvl   = get_user_level_from_xp($_szn_xp);
        $_szn_floor = $_xp_levels[$_szn_lvl - 1] ?? 0;
        $_szn_ceil  = $_xp_levels[$_szn_lvl]     ?? ($_szn_floor + 5000);
        $_szn_range = max(1, $_szn_ceil - $_szn_floor);
        $_szn_pct   = min(100, (int)round(($_szn_xp - $_szn_floor) / $_szn_range * 100));
        $_szn_next  = get_level_name($_szn_lvl + 1);
        ?>
        <div class="profil-card" style="margin-bottom:20px">
          <div class="profil-card-title">🗓 Progression cette saison</div>
          <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px">
            <span style="font-size:1.6rem;font-weight:900;color:var(--navy-dark)"><?= number_format($_szn_xp, 0, ',', ' ') ?> <span style="font-size:.85rem;font-weight:600;color:var(--text-muted)">XP saison</span></span>
            <a href="communaute.php?tab=classement" style="font-size:.78rem;font-weight:700;color:var(--primary);text-decoration:none">Voir classement →</a>
          </div>
          <!-- Barre XP saison vers prochain niveau -->
          <div style="background:var(--beige-dark);border-radius:20px;height:10px;overflow:hidden;margin-bottom:6px">
            <div style="width:<?= $_szn_pct ?>%;height:100%;background:linear-gradient(90deg,var(--primary),#f07066);border-radius:20px;transition:width .6s ease"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted);font-weight:600">
            <span><?= number_format($_szn_floor, 0, ',', ' ') ?> XP</span>
            <?php if ($_szn_lvl < 10): ?>
            <span>Prochain niveau : <strong style="color:var(--primary)"><?= e($_szn_next) ?></strong> à <?= number_format($_szn_ceil, 0, ',', ' ') ?> XP</span>
            <?php else: ?>
            <span style="color:#d4af37;font-weight:800">🏆 Niveau max atteint !</span>
            <?php endif; ?>
          </div>
          <!-- Mini stat XP total vs saison -->
          <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:100px;background:var(--beige-light);border-radius:8px;padding:10px 14px;text-align:center">
              <div style="font-size:1.1rem;font-weight:900;color:var(--navy-dark)"><?= number_format((int)$user['xp_current'], 0, ',', ' ') ?></div>
              <div style="font-size:.68rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">XP total à vie</div>
            </div>
            <div style="flex:1;min-width:100px;background:rgba(234,86,73,.06);border-radius:8px;padding:10px 14px;text-align:center;border:1px solid rgba(234,86,73,.15)">
              <div style="font-size:1.1rem;font-weight:900;color:var(--primary)"><?= number_format($_szn_xp, 0, ',', ' ') ?></div>
              <div style="font-size:.68rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">XP cette saison</div>
            </div>
            <div style="flex:1;min-width:100px;background:var(--beige-light);border-radius:8px;padding:10px 14px;text-align:center">
              <div style="font-size:1.1rem;font-weight:900;color:var(--navy-dark)"><?= (int)$user['participations'] ?></div>
              <div style="font-size:.68rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Missions validées</div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="profil-card">
          <div class="profil-card-title">🛤️ Roadmap des niveaux</div>

          <div class="roadmap-wrap">
            <div class="roadmap-line">
            <?php
            // Afficher les 6 premiers niveaux significatifs de la roadmap
            $_roadmap_indices = [1, 2, 3, 4, 5, 6, 7, 8];
            $_user_xp  = (int)$user['xp_current'];
            $_user_lvl = (int)$user['level'];
            foreach ($_roadmap_indices as $_ri):
                $_rm_xp   = $_xp_levels[$_ri - 1] ?? 0;
                $_rm_name = $_level_names[$_ri] ?? 'Niveau ' . $_ri;
                $_rm_done    = $_user_xp >= ($_xp_levels[$_ri] ?? 999999);
                $_rm_current = ($_ri === $_user_lvl);
            ?>
              <div class="roadmap-node <?= $_rm_done ? 'done' : ($_rm_current ? 'current' : '') ?>">
                <div class="roadmap-dot"><?= $_rm_done ? '✓' : ($_rm_current ? $_ri : '—') ?></div>
                <div class="roadmap-label"><?= e($_rm_name) ?><div class="roadmap-xp-req"><?= number_format($_rm_xp, 0, ',', ' ') ?> XP</div></div>
              </div>
            <?php endforeach; ?>
            </div>
          </div>

          <?php
          $_xp_manquants = max(0, (int)$user['xp_next'] - (int)$user['xp_current']);
          $_next_name    = $_level_names[$_user_lvl + 1] ?? 'Niveau max';
          ?>
          <?php if ($_user_lvl < 8): ?>
          <div class="next-level-banner">
            🎯 Prochain niveau : <strong>&nbsp;<?= e($_next_name) ?>&nbsp;</strong>
            — encore <strong><?= number_format($_xp_manquants, 0, ',', ' ') ?> XP</strong> à gagner
          </div>
          <?php else: ?>
          <div class="next-level-banner" style="background:linear-gradient(135deg,rgba(201,150,42,.12),rgba(201,150,42,.06));border-color:rgba(201,150,42,.3);color:#8a6020">
            🏆 Niveau maximum atteint — Tu es une <strong>Légende de la Zone</strong> !
          </div>
          <?php endif; ?>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">💡 Comment gagner des XP</div>
          <table class="xp-table">
            <thead>
              <tr>
                <th>Action</th>
                <th>XP gagnés</th>
                <th>Action</th>
                <th>XP gagnés</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Quiz réussi</td>
                <td class="xp-amount">+20 – 60</td>
                <td>Kéto Kolé Tché</td>
                <td class="xp-amount">+40 – 100</td>
              </tr>
              <tr>
                <td>Défi photo</td>
                <td class="xp-amount">+25 – 80</td>
                <td>Météo-mission</td>
                <td class="xp-amount">+15 – 40</td>
              </tr>
              <tr>
                <td>Avis rando</td>
                <td class="xp-amount">+30 – 50</td>
                <td>Vote &amp; commentaire</td>
                <td class="xp-amount">+5 – 15</td>
              </tr>
              <tr>
                <td>Mission saison</td>
                <td class="xp-amount">+100 – 300</td>
                <td>Identification parfaite</td>
                <td class="xp-amount">+80 – 150</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div><!-- /panel progression -->


      <!-- ══════════════════════════════
           TAB 3 — BADGES
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="badges" class="profil-panel" style="display:none">

        <div class="profil-card">
          <div class="profil-card-title">🏅 Badges obtenus <span style="color:var(--primary);font-size:.9em"><?= e($user['badges_count']) ?></span></div>
          <?php if (!empty($badges)):
          $_rarity_fr = ['common'=>'Commun','uncommon'=>'Peu commun','rare'=>'Rare','epic'=>'Épique','legendary'=>'Légendaire'];
          $_rarity_colors_badge = ['common'=>'#6b7f96','uncommon'=>'#2a9d5c','rare'=>'#12314e','epic'=>'#9b59b6','legendary'=>'#C9962A'];
          ?>
          <div class="badges-grid">
            <?php foreach ($badges as $_b): ?>
            <div class="badge-card">
              <span class="badge-emoji"><?= e($_b['icon'] ?? '🏅') ?></span>
              <div class="badge-name"><?= e($_b['title'] ?? '') ?></div>
              <?php if (!empty($_b['awarded_at'])): ?>
              <div class="badge-date">Obtenu le <?= e(date('d/m/Y', strtotime($_b['awarded_at']))) ?></div>
              <?php endif; ?>
              <span class="badge-xp" style="font-size:.72rem;font-weight:700;color:<?= e($_rarity_colors_badge[$_b['rarity'] ?? 'common'] ?? '#6b7f96') ?>;background:<?= e($_rarity_colors_badge[$_b['rarity'] ?? 'common'] ?? '#6b7f96') ?>18;padding:2px 8px;border-radius:3px"><?= e($_rarity_fr[$_b['rarity'] ?? ''] ?? ucfirst($_b['rarity'] ?? '')) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div style="text-align:center;padding:32px 20px;color:var(--text-muted)">
            <div style="font-size:2rem;margin-bottom:8px">🏅</div>
            <div style="font-size:.88rem;font-weight:600">Aucun badge encore obtenu.</div>
            <div style="font-size:.78rem;margin-top:4px">Participe aux missions pour débloquer tes premiers badges.</div>
          </div>
          <?php endif; ?>
        </div>

        <?php
        // Fetch all non-hidden, non-special badges for "locked" display
        $_all_badges_for_lock = [];
        $_locked_badges = [];
        $_lock_pdo = db();
        if ($_lock_pdo) {
            try {
                $_lock_stmt = $_lock_pdo->prepare("
                    SELECT b.id, b.title, b.icon, b.rarity, b.condition_type, b.condition_value, b.description
                    FROM badges b
                    WHERE b.is_hidden = 0 AND b.is_special = 0
                    ORDER BY b.rarity DESC, b.condition_value ASC
                ");
                $_lock_stmt->execute();
                $_all_badges_raw = $_lock_stmt->fetchAll();
                $_earned_ids = array_column($badges ?? [], 'id');
                // User stats for progress
                $_u_xp      = (int)$user['xp_current'];
                $_u_missions = (int)$user['participations'];
                // Count validated randos
                $_u_randos = 0;
                try {
                    $_rc = $_lock_pdo->prepare("SELECT COUNT(*) FROM rando_participations WHERE user_id=:id AND status='validated'");
                    $_rc->execute([':id' => $user['id']]);
                    $_u_randos = (int)$_rc->fetchColumn();
                } catch (Throwable $_re) {}

                foreach ($_all_badges_raw as $_ab) {
                    if (in_array($_ab['id'], $_earned_ids)) continue;
                    // Compute progress
                    $_ab_prog = 0;
                    $_ab_val  = (int)($_ab['condition_value'] ?? 0);
                    if ($_ab_val > 0) {
                        if ($_ab['condition_type'] === 'xp_threshold')    $_ab_prog = min(100, round($_u_xp / $_ab_val * 100));
                        if ($_ab['condition_type'] === 'mission_success') $_ab_prog = min(100, round($_u_missions / $_ab_val * 100));
                        if ($_ab['condition_type'] === 'rando_validated') $_ab_prog = min(100, round($_u_randos / $_ab_val * 100));
                    }
                    $_ab['_prog'] = (int)$_ab_prog;
                    $_ab['_val']  = $_ab_val;
                    $_ab['_user_val'] = match($_ab['condition_type']) {
                        'xp_threshold'    => $_u_xp,
                        'mission_success' => $_u_missions,
                        'rando_validated' => $_u_randos,
                        default => 0,
                    };
                    $_locked_badges[] = $_ab;
                }
            } catch (Throwable $_le) {}
        }
        $_rarity_colors_badge = $_rarity_colors_badge ?? ['common'=>'#6b7f96','uncommon'=>'#2a9d5c','rare'=>'#12314e','epic'=>'#9b59b6','legendary'=>'#C9962A'];
        $_rarity_fr = $_rarity_fr ?? ['common'=>'Commun','uncommon'=>'Peu commun','rare'=>'Rare','epic'=>'Épique','legendary'=>'Légendaire'];
        $_cond_labels = ['xp_threshold'=>'XP requis','mission_success'=>'Missions validées','rando_validated'=>'Randos validées','season'=>'Participation saison'];
        ?>
        <?php if (!empty($_locked_badges)): ?>
        <div class="profil-card">
          <div class="profil-card-title">🔒 Badges à débloquer <span style="font-size:.82em;color:var(--text-muted)"><?= count($_locked_badges) ?></span></div>
          <div class="badges-grid">
            <?php foreach (array_slice($_locked_badges, 0, 12) as $_lb):
              $_lb_color = $_rarity_colors_badge[$_lb['rarity'] ?? 'common'] ?? '#6b7f96';
            ?>
            <div class="badge-card locked" style="border-top:3px solid <?= e($_lb_color) ?>20;position:relative">
              <span class="badge-emoji"><?= e($_lb['icon'] ?? '🔒') ?></span>
              <div class="badge-name"><?= e($_lb['title']) ?></div>
              <div class="badge-lock" style="color:<?= e($_lb_color) ?>;font-size:.7rem;font-weight:700;margin:3px 0"><?= e($_rarity_fr[$_lb['rarity'] ?? ''] ?? ucfirst($_lb['rarity'] ?? '')) ?></div>
              <?php if ($_lb['_val'] > 0 && in_array($_lb['condition_type'], ['xp_threshold','mission_success','rando_validated'])): ?>
              <div class="badge-prog"><?= number_format($_lb['_user_val'], 0, ',', ' ') ?> / <?= number_format($_lb['_val'], 0, ',', ' ') ?> <?= e($_cond_labels[$_lb['condition_type']] ?? '') ?></div>
              <div class="badge-prog-track"><div class="badge-prog-fill" style="width:<?= $_lb['_prog'] ?>%;background:<?= e($_lb_color) ?>"></div></div>
              <?php elseif ($_lb['condition_type'] === 'season'): ?>
              <div class="badge-prog">Participer à une saison</div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /panel badges -->


      <!-- ══════════════════════════════
           TAB 4 — ACTIVITÉ
      ══════════════════════════════ -->
      <div data-panel-group="profil" data-panel-id="activite" class="profil-panel" style="display:none">

        <div class="profil-card">
          <div class="profil-card-title">📊 Activité — 4 dernières semaines</div>

          <div class="activity-chart" id="activityChart">
            <div class="chart-col">
              <div class="chart-val" id="val-s4">3</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar" id="bar-s4" data-height="33"></div>
              </div>
            </div>
            <div class="chart-col">
              <div class="chart-val" id="val-s3">7</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar" id="bar-s3" data-height="78"></div>
              </div>
            </div>
            <div class="chart-col">
              <div class="chart-val" id="val-s2">5</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar" id="bar-s2" data-height="56"></div>
              </div>
            </div>
            <div class="chart-col">
              <div class="chart-val" id="val-s1">9</div>
              <div class="chart-bar-wrap">
                <div class="chart-bar highlight" id="bar-s1" data-height="100"></div>
              </div>
            </div>
          </div>
          <div class="activity-axis">
            <div class="activity-axis-col">S-4</div>
            <div class="activity-axis-col">S-3</div>
            <div class="activity-axis-col">S-2</div>
            <div class="activity-axis-col">Cette sem.</div>
          </div>
        </div>

        <div class="profil-card">
          <div class="profil-card-title">⚡ Historique XP récent</div>

          <div id="activityFeed">

            <?php
            $_xp_icons = [
                'registration'           => '🎉',
                'mission_success'        => '✅',
                'quiz_success'           => '🎯',
                'photo_coup_de_coeur'    => '📸',
                'vote'                   => '🗳️',
                'rando_review'           => '🥾',
                'ktc_correct'            => '🔍',
                'investigation_solved'   => '🕵️',
                'hidden_hunt_completion' => '🗝️',
            ];
            if (!empty($_xp_history)):
                foreach ($_xp_history as $_xlog):
                    $_icon   = $_xp_icons[$_xlog['source_type']] ?? '⚡';
                    $_label  = e($_xlog['reason'] ?: ucfirst(str_replace('_', ' ', $_xlog['source_type'])));
                    $_amount = (int)$_xlog['xp_amount'];
                    $_sign   = $_amount >= 0 ? '+' : '';
                    $_date   = $_xlog['created_at'] ? date('d/m/Y', strtotime($_xlog['created_at'])) : '';
            ?>
            <div class="feed-item">
              <div class="feed-icon"><?= $_icon ?></div>
              <div class="feed-info">
                <div class="feed-title"><?= $_label ?></div>
                <div class="feed-meta"><span class="feed-xp"><?= $_sign . $_amount ?> XP</span><?= $_date ? ' · ' . e($_date) : '' ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align:center;padding:28px 20px;color:var(--text-muted)">
              <div style="font-size:1.8rem;margin-bottom:8px">⚡</div>
              <div style="font-size:.88rem;font-weight:600">Tes premières actions apparaîtront ici.</div>
              <div style="font-size:.78rem;margin-top:4px">Participe à des missions pour gagner de l'XP.</div>
            </div>
            <?php endif; ?>

          </div><!-- /activityFeed -->
        </div>

      </div><!-- /panel activite -->

      <!-- ══ TAB PASSEPORT ════════════════════════════════════ -->
      <?php
      $_passport = function_exists('fetch_user_passport') ? fetch_user_passport((int)$user['id']) : [];
      $_rarity_colors = ['commun'=>'#6b7f96','rare'=>'#12314e','epique'=>'#9b59b6','legendaire'=>'#C9962A'];
      $_cat_emojis = ['exploration'=>'🧭','clan'=>'🛡️','saison'=>'⚔️','meteo'=>'🌤️','rando'=>'🥾','culture'=>'🥐','invisible'=>'👁','general'=>'🏅'];
      ?>
      <div class="profil-panel" data-panel-group="profil" data-panel-id="passeport" style="display:none">

        <!-- Passeport card -->
        <div class="profil-card" style="background:linear-gradient(135deg,var(--navy-dark),#1e3a5f);color:#fff;margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
            <div style="width:64px;height:64px;border-radius:14px;overflow:hidden;background:var(--primary);
              display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;
              border:3px solid rgba(255,255,255,.2)">
              <?php $_pass_av = avatar_url($user); ?>
              <?php if (!empty($_pass_av)): ?>
                <img src="<?= e($_pass_av) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?><?= e($user['avatar']) ?><?php endif; ?>
            </div>
            <div>
              <div style="font-size:1.3rem;font-weight:900;letter-spacing:-.3px;margin-bottom:4px"><?= e($user['pseudo']) ?></div>
              <div style="font-size:.8rem;color:rgba(255,255,255,.6)"><?= e($user['clan_label']) ?> · <?= e($user['level_name']) ?> · Niveau <?= (int)$user['level'] ?></div>
            </div>
            <div style="margin-left:auto;text-align:right">
              <div style="font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:4px">Passeport Zone85</div>
              <div style="font-size:1.6rem;font-weight:900;color:var(--primary)"><?= format_xp($user['xp_current']) ?></div>
            </div>
          </div>
        </div>

        <!-- Stats passeport -->
        <div class="profil-card">
          <div class="profil-card-title">🗺️ Ma légende</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:14px">
            <?php
            $passport_stats = [
              ['🗓️', 'Saisons vécues',  $_passport['seasons_lived'] ?? 0, ''],
              ['🏆', 'Trophées clan',    $_passport['clan_trophies'] ?? 0, ''],
              ['🎯', 'Missions validées',$_passport['missions_total'] ?? 0,''],
              ['🗝️', 'Objets trouvés',  $_passport['collectibles'] ?? 0,  ''],
              ['🥐', 'KTC réussis',      $_passport['ktc_correct'] ?? 0,   '/' . ($_passport['ktc_total'] ?? 0)],
              ['🥾', 'Randos validées',  $_passport['randos'] ?? 0,        ''],
            ];
            foreach ($passport_stats as [$icon, $label, $val, $suffix]):
            ?>
            <div style="background:var(--beige);border-radius:10px;padding:14px;text-align:center">
              <div style="font-size:1.5rem;margin-bottom:6px"><?= $icon ?></div>
              <div style="font-size:1.3rem;font-weight:900;color:var(--navy-dark)"><?= $val ?><span style="font-size:.7rem;color:var(--text-muted)"><?= $suffix ?></span></div>
              <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:3px"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Saisons vécues -->
        <?php if (!empty($_passport['seasons_list'])): ?>
        <div class="profil-card">
          <div class="profil-card-title">📅 Saisons vécues</div>
          <div style="display:flex;flex-direction:column;gap:0">
            <?php foreach ($_passport['seasons_list'] as $_ps): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--beige-dark)">
              <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;
                background:<?= e($_ps['color_primary'] ?? '#0c1e2e') ?>20;flex-shrink:0">
                <?= e($_ps['emoji'] ?? '🏆') ?>
              </div>
              <div style="flex:1;font-size:.88rem;font-weight:700;color:var(--text)"><?= e($_ps['title']) ?></div>
              <?php if (!empty($_ps['start_date'])): ?>
              <div style="font-size:.72rem;color:var(--text-muted)"><?= date('Y', strtotime($_ps['start_date'])) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Badges par catégorie -->
        <?php if (!empty($_passport['badges_by_cat'])): ?>
        <div class="profil-card">
          <div class="profil-card-title">🏅 Collection badges</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($_passport['badges_by_cat'] as $_cat => $_cnt): ?>
            <div style="display:flex;align-items:center;gap:6px;background:var(--beige);
              padding:7px 14px;border-radius:999px;font-size:.82rem;font-weight:700;color:var(--text)">
              <?= $_cat_emojis[$_cat] ?? '🏅' ?> <?= $_cnt ?> <?= ucfirst($_cat) ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /panel passeport -->

      <!-- ══ TAB MON COMPTE ════════════════════════════════════ -->
      <?php
      // Charger les données prefs depuis DB pour le tab Compte
      $_prefs_user = null;
      $_pdo_compte = db(); // $pdo n'est pas dans le scope de profil.php
      if ($_pdo_compte && !$is_guest) {
          try {
              $_ps = $_pdo_compte->prepare("SELECT newsletter_optin,notif_missions,notif_saisons,notif_clan,notif_push,digest_hebdo,bio,email,created_at,delete_requested_at FROM users WHERE id=:id LIMIT 1");
              $_ps->execute([':id'=>$user['id']]);
              $_prefs_user = $_ps->fetch();
          } catch (PDOException $e) {}
      }
      $_prefs = $_prefs_user ?: [];
      $_tab_compte = ($_GET['tab'] ?? '') === 'compte';
      $_av_emoji_list = ['🧭','⚔️','🛡️','🦊','🐺','🦅','🐗','🌿','🏄','🎯','🔥','⚡','🌊','🗝️','🏹','🧲','🦁','🐻','🦋','🌙','☀️','🌲','🗺️','🎭','🎪','🏔️','🌾','🦜','🐬','🦎','🍄','🎸'];
      $_cur_emoji = '🧭';
      if ($user['avatar_type'] === 'preset') {
          $_cfg = json_decode($user['avatar'] ?? '{}', true) ?? [];
          $_cur_emoji = $_cfg['emoji'] ?? $user['avatar'] ?? '🧭';
      }
      ?>
      <div class="profil-panel" data-panel-group="profil" data-panel-id="compte" style="display:none">

        <?php if (!empty($_compte_flash)): ?>
        <div class="profil-card" style="padding:14px 20px;margin-bottom:14px;
          background:<?= $_compte_flash['type']==='ok'?'rgba(42,157,92,.08)':'rgba(234,86,73,.07)' ?>;
          border:1px solid <?= $_compte_flash['type']==='ok'?'rgba(42,157,92,.3)':'rgba(234,86,73,.25)' ?>;
          color:<?= $_compte_flash['type']==='ok'?'#1a7a42':'#c0392b' ?>;font-size:.88rem;font-weight:600">
          <?= $_compte_flash['type']==='ok'?'✅':'❌' ?> <?= e($_compte_flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Infos personnelles -->
        <div class="profil-card">
          <div class="profil-card-title">👤 Informations personnelles</div>
          <form method="POST" action="profil.php?tab=compte">
            <?= csrf_field() ?>
            <input type="hidden" name="compte_action" value="update_profile">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
              <div>
                <label style="display:block;font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:6px">Pseudo <span style="color:var(--primary)">*</span></label>
                <input type="text" name="pseudo" value="<?= e($user['pseudo']) ?>" maxlength="30" required
                  style="width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid var(--beige-dark);font-family:inherit;font-size:.88rem;box-sizing:border-box">
              </div>
              <div>
                <label style="display:block;font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:6px">Email</label>
                <input type="email" value="<?= e($_prefs['email'] ?? $_session['email'] ?? '') ?>" disabled
                  style="width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid var(--beige-dark);background:var(--beige);font-family:inherit;font-size:.88rem;box-sizing:border-box;cursor:not-allowed">
              </div>
            </div>
            <div style="margin-bottom:16px">
              <label style="display:block;font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:6px">Bio</label>
              <textarea name="bio" maxlength="500" rows="3"
                style="width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid var(--beige-dark);font-family:inherit;font-size:.88rem;box-sizing:border-box;resize:vertical"
                placeholder="Présente-toi..."><?= e($_prefs['bio'] ?? $user['bio'] ?? '') ?></textarea>
            </div>
            <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:11px 24px;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:800;cursor:pointer">
              Enregistrer
            </button>
          </form>
        </div>

        <!-- Mot de passe -->
        <div class="profil-card">
          <div class="profil-card-title">🔑 Changer le mot de passe</div>
          <form method="POST" action="profil.php?tab=compte">
            <?= csrf_field() ?>
            <input type="hidden" name="compte_action" value="update_password">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px">
              <div>
                <label style="display:block;font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:6px">Mot de passe actuel</label>
                <input type="password" name="password_current" required
                  style="width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid var(--beige-dark);font-family:inherit;font-size:.88rem;box-sizing:border-box">
              </div>
              <div>
                <label style="display:block;font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:6px">Nouveau (8 min.)</label>
                <input type="password" name="password_new" minlength="8" required
                  style="width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid var(--beige-dark);font-family:inherit;font-size:.88rem;box-sizing:border-box">
              </div>
              <div>
                <label style="display:block;font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:6px">Confirmer</label>
                <input type="password" name="password_confirm" minlength="8" required
                  style="width:100%;padding:10px 14px;border-radius:8px;border:1.5px solid var(--beige-dark);font-family:inherit;font-size:.88rem;box-sizing:border-box">
              </div>
            </div>
            <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:11px 24px;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:800;cursor:pointer">
              Modifier le mot de passe
            </button>
          </form>
        </div>

        <!-- Avatar -->
        <div class="profil-card">
          <div class="profil-card-title">🎨 Mon avatar</div>
          <?php $_av_url = avatar_url($user); ?>
          <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px;flex-wrap:wrap">
            <div style="width:64px;height:64px;border-radius:12px;overflow:hidden;background:var(--primary);
              display:flex;align-items:center;justify-content:center;font-size:2rem;border:3px solid var(--beige-dark)">
              <?php if (!empty($_av_url)): ?>
                <img src="<?= e($_av_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?><?= e($_cur_emoji) ?><?php endif; ?>
            </div>
            <?php if ($user['avatar_type'] === 'upload'): ?>
            <form method="POST" action="profil.php?tab=compte">
              <?= csrf_field() ?><input type="hidden" name="compte_action" value="remove_avatar">
              <button type="submit" onclick="return confirm('Supprimer la photo ?')"
                style="background:transparent;color:#c0392b;border:1.5px solid #c0392b;padding:7px 14px;
                border-radius:8px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer">
                🗑️ Supprimer la photo
              </button>
            </form>
            <?php endif; ?>
          </div>
          <form method="POST" action="profil.php?tab=compte" enctype="multipart/form-data"
            style="margin-bottom:20px;padding:16px;background:var(--beige);border-radius:10px">
            <?= csrf_field() ?><input type="hidden" name="compte_action" value="upload_avatar">
            <div style="font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:8px">📷 Uploader une photo</div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp" required style="font-size:.82rem">
              <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:9px 18px;
                border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer">Mettre à jour</button>
            </div>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:6px">JPG, PNG, WebP · Max 2 Mo</div>
          </form>
          <form method="POST" action="profil.php?tab=compte" id="emoji-form-profil">
            <?= csrf_field() ?><input type="hidden" name="compte_action" value="update_avatar_emoji">
            <input type="hidden" name="avatar_emoji" id="profil_selected_emoji" value="<?= e($_cur_emoji) ?>">
            <div style="font-size:.76rem;font-weight:700;color:#3d5166;margin-bottom:8px">🎭 Choisir un emoji</div>
            <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:6px;margin-bottom:12px">
              <?php foreach ($_av_emoji_list as $_em): ?>
              <button type="button" onclick="selectProfilEmoji(this,'<?= e($_em) ?>')"
                style="aspect-ratio:1;border-radius:8px;border:2px solid var(--beige-dark);background:var(--beige);
                font-size:1.4rem;cursor:pointer;display:flex;align-items:center;justify-content:center;
                <?= $_em===$_cur_emoji?'border-color:var(--primary);background:rgba(234,86,73,.08)':'' ?>"
                data-emoji="<?= e($_em) ?>"><?= e($_em) ?></button>
              <?php endforeach; ?>
            </div>
            <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:9px 20px;
              border-radius:8px;font-family:inherit;font-size:.84rem;font-weight:800;cursor:pointer">Choisir cet emoji</button>
          </form>
        </div>

        <!-- Préférences -->
        <div class="profil-card">
          <div class="profil-card-title">🔔 Préférences</div>
          <form method="POST" action="profil.php?tab=compte">
            <?= csrf_field() ?><input type="hidden" name="compte_action" value="update_prefs">
            <?php
            $toggles = [
              ['newsletter_optin','Newsletter Zone85',  'Actualités et nouvelles fonctionnalités'],
              ['notif_missions',  'Emails nouvelles missions','À chaque nouvelle mission'],
              ['notif_saisons',   'Emails saisons',     'Début/fin de saison'],
              ['notif_clan',      'Notifications clan', 'Classement et activité clan'],
              ['notif_push',      'Notifications push', 'Si Zone85 est installée'],
              ['digest_hebdo',    'Résumé hebdomadaire','Un email par semaine'],
            ];
            foreach ($toggles as [$tname,$tlabel,$tsub]):
              $tchecked = !empty($_prefs[$tname]);
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--beige-dark)">
              <div>
                <div style="font-size:.88rem;font-weight:600;color:var(--text)"><?= $tlabel ?></div>
                <div style="font-size:.74rem;color:var(--text-muted)"><?= $tsub ?></div>
              </div>
              <label class="pref-toggle-label" style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;cursor:pointer">
                <input type="checkbox" name="<?= $tname ?>"<?= $tchecked?' checked':'' ?>
                  class="pref-toggle-input" style="position:absolute;opacity:0;width:0;height:0"
                  onchange="var t=this.parentNode.querySelector('.pref-slider');var k=this.parentNode.querySelector('.pref-knob');t.style.background=this.checked?'var(--primary)':'var(--beige-dark)';k.style.transform=this.checked?'translateX(20px)':'';">
                <span class="pref-slider" style="position:absolute;cursor:pointer;inset:0;border-radius:999px;
                  background:<?= $tchecked?'var(--primary)':'var(--beige-dark)' ?>;transition:.2s">
                  <span class="pref-knob" style="position:absolute;width:18px;height:18px;left:3px;top:3px;
                    background:#fff;border-radius:50%;transition:.2s;transform:<?= $tchecked?'translateX(20px)':'' ?>;
                    box-shadow:0 1px 3px rgba(0,0,0,.2)"></span>
                </span>
              </label>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:16px">
              <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:11px 24px;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:800;cursor:pointer">
                Enregistrer
              </button>
            </div>
          </form>
        </div>

        <!-- RGPD -->
        <div class="profil-card">
          <div class="profil-card-title">🔒 Confidentialité</div>
          <?php
          $_legal_rows = [];
          if ($_pdo_compte && !$is_guest) {
              try {
                  $_ls2 = $_pdo_compte->prepare("SELECT document_type,document_version,accepted_at FROM legal_acceptances WHERE user_id=:id ORDER BY accepted_at ASC");
                  $_ls2->execute([':id'=>$user['id']]);
                  $_legal_rows = $_ls2->fetchAll();
              } catch (PDOException $e) {}
          }
          $consent_labels = ['cgu' => 'CGU acceptées', 'privacy' => 'Confidentialité'];
          $found_consents = array_column($_legal_rows, null, 'document_type');
          ?>
          <?php foreach ($consent_labels as $ctype => $clabel): $crow = $found_consents[$ctype] ?? null; ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--beige-dark)">
            <div style="font-size:.88rem;font-weight:600;color:var(--text)"><?= $clabel ?></div>
            <span style="font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:999px;
              background:<?= $crow ? 'rgba(42,157,92,.1)' : 'rgba(201,150,42,.1)' ?>;
              color:<?= $crow ? '#1a7a42' : '#8a6020' ?>">
              <?= $crow ? '✅ ' . date('d/m/Y', strtotime($crow['accepted_at'])) : '⚠️ Non enregistré' ?>
            </span>
          </div>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;padding:10px 0">
            <div style="font-size:.88rem;font-weight:600;color:var(--text)">Newsletter</div>
            <span style="font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:999px;
              background:<?= !empty($_prefs['newsletter_optin']) ? 'rgba(42,157,92,.1)' : 'rgba(107,127,150,.1)' ?>;
              color:<?= !empty($_prefs['newsletter_optin']) ? '#1a7a42' : '#4a6073' ?>">
              <?= !empty($_prefs['newsletter_optin']) ? '✅ Abonné' : '○ Non abonné' ?>
            </span>
          </div>
          <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
            <a href="ajax/account-export.php" style="display:inline-flex;align-items:center;gap:6px;
              padding:10px 18px;background:var(--beige);color:var(--text);border:1.5px solid var(--beige-dark);
              border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:700;text-decoration:none">
              📥 Exporter mes données
            </a>
            <?php if (empty($_prefs['delete_requested_at'])): ?>
            <form method="POST" action="profil.php?tab=compte" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="compte_action" value="request_delete">
              <button type="submit" onclick="return confirm('Confirmer la demande de suppression ?')"
                style="background:transparent;color:#c0392b;border:1.5px solid #c0392b;
                padding:10px 18px;border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer">
                🗑️ Demander la suppression
              </button>
            </form>
            <?php else: ?>
            <div style="padding:10px 14px;background:rgba(201,150,42,.1);border:1px solid rgba(201,150,42,.3);
              border-radius:8px;font-size:.82rem;font-weight:600;color:#8a6020">
              ⏳ Suppression demandée — annulable en nous contactant
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /panel compte -->

    </main>
  </div><!-- /profil-layout -->
</div><!-- /profil-page -->

<?php
$page_scripts = '<script>
const _origSwitchTab = switchTab;
window.switchTab = function(group, id) {
  _origSwitchTab(group, id);
  if (group === \'profil\') {
    document.querySelectorAll(\'.sidebar-nav-link\').forEach(btn => {
      btn.classList.toggle(\'active\', btn.dataset.tabId === id);
    });
    if (id === \'activite\') animateActivityChart();
  }
};

function selectProfilEmoji(btn, emoji) {
  document.querySelectorAll(\'[data-emoji]\').forEach(b => {
    b.style.borderColor = \'\'; b.style.background = \'\';
  });
  btn.style.borderColor = \'var(--primary)\';
  btn.style.background  = \'rgba(234,86,73,.08)\';
  document.getElementById(\'profil_selected_emoji\').value = emoji;
}

// Ouvrir tab=compte si paramètre GET présent
if (new URLSearchParams(window.location.search).get(\'tab\') === \'compte\') {
  switchTab(\'profil\', \'compte\');
}

function animateActivityChart() {
  const maxH = 90;
  document.querySelectorAll(\'.chart-bar[data-height]\').forEach(bar => {
    const pct = parseInt(bar.dataset.height, 10) / 100;
    bar.style.height = Math.round(maxH * pct) + \'px\';
  });
}

function filterActivity(btn, type) {
  document.querySelectorAll(\'.activity-filter-btn\').forEach(b => b.classList.remove(\'active\'));
  btn.classList.add(\'active\');
  document.querySelectorAll(\'#activityFeed .feed-item\').forEach(item => {
    const match = type === \'all\' || item.dataset.activityType === type;
    item.style.display = match ? \'\' : \'none\';
  });
}

window.addEventListener(\'load\', () => {
  setTimeout(() => {
    document.querySelectorAll(\'.mission-prog-fill[data-prog]\').forEach(bar => {
      bar.style.width = bar.dataset.prog + \'%\';
    });
  }, 500);
});
</script>';
require_once 'includes/footer.php';
?>
