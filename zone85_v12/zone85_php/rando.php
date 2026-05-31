<?php
// ============================================================
// ZONE85 — Fiche individuelle de randonnée (V12)
// ============================================================
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

// ── Paramètres d'entrée ────────────────────────────────────────
$slug = safe_input($_GET['slug'] ?? '', 200);
$id   = (int)($_GET['id'] ?? 0);

// ── Validation "J'ai réalisé cette rando" ─────────────────────
$rando_flash = '';
$rando_flash_type = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_rando') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $rando_flash = 'Session expirée. Rechargez la page.';
        $rando_flash_type = 'err';
    } elseif (!is_logged_in()) {
        $rando_flash = 'Connectez-vous pour tamponner votre Passeport Vendéen.';
        $rando_flash_type = 'err';
    }
}


// ── Chargement de la fiche ─────────────────────────────────────
$rando  = null;
$blocks = [];
$nb_completions = 0;
$rando_communes = [];
$recommended_seasons = [];

if (db_enabled()) {
    $pdo = db();
    if ($pdo) {
        try {
            if ($slug !== '') {
                $s = $pdo->prepare(
                    "SELECT * FROM randos WHERE slug = :s AND status = 'published' LIMIT 1"
                );
                $s->execute([':s' => $slug]);
            } else {
                $s = $pdo->prepare(
                    "SELECT * FROM randos WHERE id = :id AND status = 'published' LIMIT 1"
                );
                $s->execute([':id' => $id]);
            }
            $rando = $s->fetch();

            if ($rando) {
                // Blocs de contenu
                $bs = $pdo->prepare(
                    "SELECT * FROM rando_blocks WHERE rando_id = :rid ORDER BY sort_order ASC"
                );
                $bs->execute([':rid' => $rando['id']]);
                $blocks = $bs->fetchAll();

                // Nombre de membres ayant complété cette rando
                $cp = $pdo->prepare(
                    "SELECT COUNT(*) FROM rando_participations WHERE rando_id = :rid"
                );
                $cp->execute([':rid' => $rando['id']]);
                $nb_completions = (int)$cp->fetchColumn();

                // Communes traversées + saisons conseillées
                if (function_exists('normalize_communes_list')) {
                    $rando_communes = normalize_communes_list($rando['communes_json'] ?? '', $rando['commune'] ?? null);
                } else {
                    if (!empty($rando['communes_json'])) {
                        $decoded_communes = json_decode($rando['communes_json'], true);
                        if (is_array($decoded_communes)) {
                            $rando_communes = array_values(array_filter(array_map('trim', $decoded_communes)));
                        }
                    }
                    if (empty($rando_communes) && !empty($rando['commune'])) {
                        $rando_communes = [trim((string)$rando['commune'])];
                    }
                }
                if (!empty($rando['recommended_seasons'])) {
                    $decoded_reco = json_decode($rando['recommended_seasons'], true);
                    if (is_array($decoded_reco)) $recommended_seasons = $decoded_reco;
                }
            }
        } catch (PDOException $e) {
            // Erreur DB silencieuse — redirect si $rando reste null
        }
    }
}



// Traitement validation après chargement de la rando
$user_has_completed = false;
$user_rando_status  = null;
$latest_explorers = [];
$validated_returns = [];
if ($rando && db_enabled()) {
    $pdo = db();
    $current = function_exists('current_user') ? current_user() : null;
    $uid = !empty($current['id']) ? (int)$current['id'] : 0;

    $stamp_user_communes = function(int $uid, int $rid) use ($pdo, $rando) {
        $stamp_communes = function_exists('normalize_communes_list')
            ? normalize_communes_list($rando['communes_json'] ?? '', $rando['commune'] ?? null)
            : [];
        if (empty($stamp_communes) && !empty($rando['commune'])) $stamp_communes = [trim((string)$rando['commune'])];
        if (!empty($stamp_communes)) {
            $st = $pdo->prepare('INSERT IGNORE INTO rando_commune_stamps (user_id, commune, first_rando_id, stamped_at) VALUES (:uid,:commune,:rid,NOW())');
            foreach ($stamp_communes as $commune_name) {
                if ($commune_name !== '') $st->execute([':uid'=>$uid, ':commune'=>$commune_name, ':rid'=>$rid]);
            }
        }
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rando_flash && $uid > 0) {
        $action = $_POST['action'] ?? '';
        try {
            $chk = $pdo->prepare('SELECT * FROM rando_participations WHERE rando_id=:rid AND user_id=:uid LIMIT 1');
            $chk->execute([':rid'=>(int)$rando['id'], ':uid'=>$uid]);
            $existing_participation = $chk->fetch();

            if ($action === 'stamp_rando') {
                if ($existing_participation && in_array(($existing_participation['status'] ?? 'stamped'), ['stamped','pending','validated'], true)) {
                    $rando_flash = 'Cette randonnée est déjà tamponnée dans votre Passeport.';
                    $rando_flash_type = 'ok';
                } else {
                    if ($existing_participation) {
                        $up = $pdo->prepare('UPDATE rando_participations SET status="stamped", done_at=NOW() WHERE id=:id');
                        $up->execute([':id'=>(int)$existing_participation['id']]);
                    } else {
                        $ins = $pdo->prepare('INSERT INTO rando_participations (rando_id, user_id, status, done_at) VALUES (:rid,:uid,"stamped",NOW())');
                        $ins->execute([':rid'=>(int)$rando['id'], ':uid'=>$uid]);
                    }
                    $stamp_user_communes($uid, (int)$rando['id']);
                    $rando_flash = 'Randonnée ajoutée à votre Passeport. Les XP se débloquent avec une photo validée par l’équipe.';
                    $rando_flash_type = 'ok';
                }
            }

            if ($action === 'submit_rando_proof') {
                $photo_path = '';
                $proof_rating = (int)($_POST['proof_rating'] ?? 0);
                $proof_review = safe_input($_POST['proof_review'] ?? '', 900);
                if ($proof_rating < 1 || $proof_rating > 5) {
                    $rando_flash = 'Choisissez une note de 1 à 5 pour demander les XP.';
                    $rando_flash_type = 'err';
                } elseif ($proof_review === '') {
                    $rando_flash = 'Ajoutez un petit avis sur la randonnée.';
                    $rando_flash_type = 'err';
                } elseif (!empty($_FILES['proof_photo']['tmp_name'])) {
                    $up = upload_editorial_image($_FILES['proof_photo'], 'randos/proofs');
                    if ($up['ok']) $photo_path = $up['path'];
                    else {
                        $rando_flash = 'Photo : ' . e($up['error']);
                        $rando_flash_type = 'err';
                    }
                } else {
                    $rando_flash = 'Ajoutez une photo devant le Trésor du parcours pour demander les XP.';
                    $rando_flash_type = 'err';
                }
                if (!$rando_flash) {
                    if ($existing_participation) {
                        $up = $pdo->prepare('UPDATE rando_participations SET status="pending", photo_path=:photo, proof_rating=:rating, proof_review=:review, done_at=COALESCE(done_at,NOW()) WHERE id=:id');
                        $up->execute([':photo'=>$photo_path, ':rating'=>$proof_rating, ':review'=>$proof_review, ':id'=>(int)$existing_participation['id']]);
                    } else {
                        $ins = $pdo->prepare('INSERT INTO rando_participations (rando_id, user_id, status, photo_path, proof_rating, proof_review, done_at) VALUES (:rid,:uid,"pending",:photo,:rating,:review,NOW())');
                        $ins->execute([':rid'=>(int)$rando['id'], ':uid'=>$uid, ':photo'=>$photo_path, ':rating'=>$proof_rating, ':review'=>$proof_review]);
                    }
                    $stamp_user_communes($uid, (int)$rando['id']);
                    $rando_flash = 'Participation envoyée. L’équipe Zone85 validera les +25 XP après vérification de la photo devant le Trésor du parcours.';
                    $rando_flash_type = 'ok';
                }
            }
        } catch (Throwable $e) {
            $rando_flash = 'Impossible de traiter cette randonnée pour le moment.';
            $rando_flash_type = 'err';
        }
    }

    if ($uid > 0) {
        try {
            $chk = $pdo->prepare('SELECT status FROM rando_participations WHERE rando_id=:rid AND user_id=:uid LIMIT 1');
            $chk->execute([':rid'=>(int)$rando['id'], ':uid'=>$uid]);
            $row_status = $chk->fetch();
            if ($row_status) {
                $user_has_completed = in_array(($row_status['status'] ?? 'stamped'), ['stamped','pending','validated'], true);
                $user_rando_status  = $row_status['status'] ?? 'stamped';
            }
        } catch (Throwable $e) {}
    }

    try {
        $le = $pdo->prepare('SELECT u.pseudo, u.avatar_type, u.avatar_file, u.avatar_config, rp.done_at, rp.status FROM rando_participations rp JOIN users u ON u.id=rp.user_id WHERE rp.rando_id=:rid AND rp.status IN ("stamped","pending","validated") ORDER BY rp.done_at DESC LIMIT 5');
        $le->execute([':rid'=>(int)$rando['id']]);
        $latest_explorers = $le->fetchAll();
    } catch (Throwable $e) {}

    try {
        $cp = $pdo->prepare('SELECT COUNT(*) FROM rando_participations WHERE rando_id = :rid AND status IN ("stamped","pending","validated")');
        $cp->execute([':rid' => $rando['id']]);
        $nb_completions = (int)$cp->fetchColumn();
    } catch (Throwable $e) {}

    // Retours publics : uniquement les participations validées par l'admin
    try {
        $vr = $pdo->prepare('SELECT rp.photo_path, rp.proof_rating, rp.proof_review, rp.validated_at, rp.done_at, u.pseudo
            FROM rando_participations rp
            JOIN users u ON u.id = rp.user_id
            WHERE rp.rando_id = :rid
              AND rp.status = "validated"
              AND (rp.photo_path IS NOT NULL AND rp.photo_path <> ""
                   OR rp.proof_review IS NOT NULL AND rp.proof_review <> ""
                   OR rp.proof_rating IS NOT NULL)
            ORDER BY COALESCE(rp.validated_at, rp.done_at) DESC
            LIMIT 12');
        $vr->execute([':rid' => (int)$rando['id']]);
        $validated_returns = $vr->fetchAll();
    } catch (Throwable $e) {
        $validated_returns = [];
    }
}

// Redirect si non trouvée
if (!$rando) {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/randos.php');
    exit;
}

// ── État connexion ─────────────────────────────────────────────
$is_logged_in = function_exists('is_logged_in') ? is_logged_in() : (!empty($_SESSION['user_id']) || !empty($_SESSION['pseudo']));

// ── Méta SEO ──────────────────────────────────────────────────
$page_title       = $rando['title'] . ' — Rando Zone85';
$page_description = $rando['meta_description'] ?? $rando['summary'] ?? '';
$page_og_image    = $rando['cover_image'] ?? 'assets/img/ZONE852025.png';
$page_robots      = 'index,follow';
$page_canonical   = function_exists('absolute_url') ? absolute_url('rando.php?slug=' . urlencode($rando['slug'] ?? '')) : ('https://www.zone85.fr/rando.php?slug=' . urlencode($rando['slug'] ?? ''));
$current_page     = 'randos';

$page_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil',
         'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Randonnées',
         'item' => 'https://www.zone85.fr/randos.php'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $rando['title'],
         'item' => $page_canonical],
    ],
];

// ── Données secteurs ───────────────────────────────────────────
$secteur_labels = [
    'bocage'   => 'Bocage',
    'littoral' => 'Littoral',
    'marais'   => 'Marais',
    'plaine'   => 'Plaine',
];
$secteur_gradients = [
    'bocage'   => 'linear-gradient(135deg, #1a3d1a 0%, #12314e 100%)',
    'littoral' => 'linear-gradient(135deg, #12314e 0%, #0c6291 100%)',
    'marais'   => 'linear-gradient(135deg, #8b6914 0%, #2a3d1e 100%)',
    'plaine'   => 'linear-gradient(135deg, #6b7f96 0%, #12314e 100%)',
];
$secteur_colors = [
    'bocage'   => '#2a9d5c',
    'littoral' => '#4da6d4',
    'marais'   => '#C9962A',
    'plaine'   => '#6b7f96',
];
$difficulte_labels = [
    'facile'    => 'Facile',
    'moyen'     => 'Moyen',
    'difficile' => 'Difficile',
    'expert'    => 'Expert',
];
$difficulte_stars = [
    'facile'    => 1,
    'moyen'     => 2,
    'difficile' => 3,
    'expert'    => 4,
];

$sec       = $rando['secteur']    ?? 'bocage';
$dif       = $rando['difficulty'] ?? 'facile';
$sec_label = $secteur_labels[$sec]    ?? ucfirst($sec);
$dif_label = $difficulte_labels[$dif] ?? ucfirst($dif);
$sec_grad  = $secteur_gradients[$sec] ?? 'linear-gradient(135deg,#1a3d1a,#12314e)';
$sec_color = $secteur_colors[$sec]    ?? '#2a9d5c';
$dif_stars = $difficulte_stars[$dif]  ?? 1;

// Premier Trésor du parcours : utilisé pour expliquer clairement la validation XP.
$first_treasure_title = 'le Trésor du parcours';
foreach ($blocks as $__block) {
    if (($__block['type'] ?? '') === 'treasure' && !empty($__block['content'])) {
        $__c = json_decode($__block['content'], true);
        if (is_array($__c) && !empty($__c['title'])) {
            $first_treasure_title = $__c['title'];
            break;
        }
    }
}

// ── Formatage distance / durée ─────────────────────────────────
$dist_str = $rando['distance_km']
    ? number_format((float)$rando['distance_km'], 1, ',', '') . ' km'
    : null;
$dur_str = null;
if ($rando['duration_min']) {
    $h = (int)floor($rando['duration_min'] / 60);
    $m = $rando['duration_min'] % 60;
    $dur_str = $h ? $h . 'h' . ($m ? sprintf('%02d', $m) : '') : $m . ' min';
}

// ── URL publique de la page (pour partage) ────────────────────
$share_url = function_exists('absolute_url') ? absolute_url('rando.php?slug=' . urlencode($rando['slug'] ?? '')) : $page_canonical;
$fb_share  = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($share_url);

// ── Styles page ────────────────────────────────────────────────
$page_styles = '<style>

/* ============================================================
   RANDO FICHE — CSS V12
============================================================ */

/* HERO */
.rando-hero {
  padding: 110px 0 64px;
  position: relative; overflow: hidden;
  background: ' . $sec_grad . ';
}
.rando-hero::before {
  content: \'\'; position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'80\' height=\'80\' viewBox=\'0 0 80 80\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Crect x=\'0\' y=\'0\' width=\'4\' height=\'4\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}
.rando-hero-cover {
  position: absolute; inset: 0;
  width: 100%; height: 100%; object-fit: cover;
  opacity: .28;
}
.rando-hero-inner { position: relative; z-index: 1; }
.rando-hero-badges {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-bottom: 20px;
}
.rando-hero-badge {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: .68rem; font-weight: 900; letter-spacing: .14em;
  text-transform: uppercase; color: #fff;
  background: rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.2);
  border-radius: 20px; padding: 5px 14px; backdrop-filter: blur(4px);
}
.rando-hero h1 {
  font-size: clamp(1.9rem, 4.5vw, 3.2rem); font-weight: 900;
  color: #fff; letter-spacing: -1px; line-height: 1.1;
  margin-bottom: 0; max-width: 820px;
}

/* BREADCRUMB */
.rando-breadcrumb {
  background: #fff; border-bottom: 1px solid rgba(0,0,0,.06);
  padding: 12px 0;
}
.rando-breadcrumb ol {
  list-style: none; margin: 0; padding: 0;
  display: flex; align-items: center; gap: 6px;
  flex-wrap: wrap; font-size: .78rem;
}
.rando-breadcrumb li { display: flex; align-items: center; gap: 6px; }
.rando-breadcrumb li:not(:last-child)::after {
  content: "›"; color: #aaa; font-size: .8rem;
}
.rando-breadcrumb a { color: #6b7f96; text-decoration: none; font-weight: 600; }
.rando-breadcrumb a:hover { color: #2a9d5c; }
.rando-breadcrumb .current { color: #1a1a1a; font-weight: 700; }

/* INFOS PRATIQUES BANDEAU */
.rando-infos-band {
  background: #fff;
  border-bottom: 2px solid rgba(0,0,0,.06);
  padding: 20px 0;
}
.rando-infos-inner {
  display: flex; align-items: center;
  gap: 0; flex-wrap: wrap;
}
.rando-info-item {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 24px; flex: 1 1 auto; min-width: 100px;
  border-right: 1px solid rgba(0,0,0,.07);
  text-align: center;
}
.rando-info-item:last-of-type { border-right: none; }
.rando-info-item-icon { font-size: 1.3rem; margin-bottom: 4px; }
.rando-info-item-value {
  font-size: .92rem; font-weight: 800; color: #0c1e2e;
  white-space: nowrap;
}
.rando-info-item-label {
  font-size: .64rem; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: #999; margin-top: 2px;
}
.rando-stars-band { display: inline-flex; gap: 1px; }
.rando-star-band { font-size: .85rem; }
.rando-star-band.on  { color: #C9962A; }
.rando-star-band.off { color: #ddd; }
.rando-gpx-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: #2a9d5c; color: #fff;
  font-size: .8rem; font-weight: 800; padding: 10px 20px;
  border-radius: 8px; text-decoration: none;
  transition: opacity .2s; white-space: nowrap;
}
.rando-gpx-btn:hover { opacity: .88; }

/* LAYOUT PRINCIPAL */
.rando-layout {
  background: var(--beige-light, #f7f4ef);
  padding: 48px 0 72px;
}
.rando-layout-inner {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 36px;
  align-items: start;
}

/* CORPS PRINCIPAL */
.rando-main-wrap {
  background: #fff; border-radius: 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  overflow: hidden;
}
.rando-cover-img {
  width: 100%; max-height: 420px;
  object-fit: cover; display: block;
}

/* BLOCS */
.rando-block-text {
  padding: 32px 36px;
  font-size: 1rem; line-height: 1.85; color: #2a2a2a;
}
.rando-block-text p   { margin: 0 0 1.3em; }
.rando-block-text h2  {
  font-size: 1.3rem; font-weight: 800; color: #0c1e2e;
  margin: 1.6em 0 .6em; letter-spacing: -.3px;
}
.rando-block-text h3 {
  font-size: 1.05rem; font-weight: 700; color: #12314e;
  margin: 1.4em 0 .5em;
}
.rando-block-sep {
  height: 1px; background: rgba(0,0,0,.06);
  margin: 0;
}
.rando-block-image {
  padding: 0 36px 28px;
}
.rando-block-image figure {
  margin: 0; border-radius: 10px; overflow: hidden;
}
.rando-block-image img {
  width: 100%; height: auto; display: block;
  object-fit: cover; max-height: 480px;
}
.rando-block-image figcaption {
  font-size: .78rem; color: #888; font-style: italic;
  padding: 8px 4px 0; text-align: center;
}
/* Galerie */
.rando-block-gallery { padding: 24px 36px; }
.rando-gallery-caption { font-size:.82rem;color:#6b7f96;margin-bottom:12px;font-style:italic; }
.rando-gallery-grid {
  display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;
}
.rando-gallery-item { border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:#f0ece7; }
.rando-gallery-item img { width:100%;height:100%;object-fit:cover;cursor:pointer;transition:transform .2s; }
.rando-gallery-item img:hover { transform:scale(1.04); }
/* Placeholder couverture */
.rando-cover-placeholder {
  width:100%;height:280px;background:linear-gradient(135deg,#2a9d5c,#12314e);
  display:flex;align-items:center;justify-content:center;font-size:4rem;
}
/* Intro + Description */
.rando-intro { padding:28px 36px 0;font-size:1.05rem;font-weight:600;color:#0c1e2e;line-height:1.75; }
.rando-description { padding:16px 36px 0;font-size:.95rem;color:#3d5166;line-height:1.8; }
.rando-why { margin:26px 36px 0;padding:18px 20px;border-radius:16px;background:linear-gradient(135deg,#fff7ed,#fff);border:1px solid rgba(234,86,73,.18);box-shadow:0 8px 22px rgba(12,30,46,.06); }
.rando-why-kicker { font-size:.72rem;text-transform:uppercase;letter-spacing:.14em;font-weight:900;color:#ea5649;margin-bottom:6px; }
.rando-why-text { font-size:1rem;line-height:1.7;font-weight:650;color:#0c1e2e; }
.rando-season-pills,.rando-commune-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.rando-season-pill,.rando-commune-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:#fff;border:1px solid #e7ded5;color:#0c1e2e;padding:7px 11px;font-size:.78rem;font-weight:800}
.rando-passport-note{font-size:.78rem;color:#6b7f96;margin-top:8px;line-height:1.5}


.rando-block-quote {
  padding: 24px 36px;
}
.rando-block-quote blockquote {
  margin: 0; padding: 18px 22px;
  border-left: 3px solid #2a9d5c;
  background: rgba(42,157,92,.04);
  border-radius: 0 10px 10px 0;
  font-size: 1.05rem; font-style: italic;
  color: #333; line-height: 1.7;
}
.rando-block-info {
  padding: 24px 36px;
}
.rando-block-info-inner {
  background: #e8f4fd; border-radius: 10px;
  padding: 18px 22px;
  display: flex; gap: 14px; align-items: flex-start;
}
.rando-block-info-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
.rando-block-info-body {}
.rando-block-info-title {
  font-size: .82rem; font-weight: 800; color: #12314e;
  margin-bottom: 4px;
}
.rando-block-info-text {
  font-size: .88rem; color: #2a4a6e; line-height: 1.65;
}
.rando-block-conseil {
  padding: 24px 36px;
}
.rando-block-conseil-inner {
  background: #fdf0ee; border-radius: 10px;
  padding: 18px 22px;
  display: flex; gap: 14px; align-items: flex-start;
  border-left: 3px solid #ea5649;
}
.rando-block-conseil-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
.rando-block-conseil-text {
  font-size: .88rem; color: #7a1e14; line-height: 1.65;
}
.rando-block-around {
  padding: 24px 36px;
}
.rando-block-around-title {
  font-size: .78rem; font-weight: 900; letter-spacing: .1em;
  text-transform: uppercase; color: #6b7f96;
  margin-bottom: 12px;
}
.rando-block-around ul {
  list-style: none; margin: 0; padding: 0;
}
.rando-block-around ul li {
  padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,.06);
  font-size: .9rem; color: #333; display: flex; gap: 8px; align-items: flex-start;
}
.rando-block-around ul li::before {
  content: "📍"; font-size: .8rem; flex-shrink: 0; margin-top: 1px;
}
.rando-block-around ul li:last-child { border-bottom: none; }
.rando-block-youtube {
  padding: 24px 36px;
}
.rando-video-wrap {
  position: relative; padding-top: 56.25%;
  border-radius: 10px; overflow: hidden; background: #000;
}
.rando-video-wrap iframe {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  border: none;
}
.rando-block-map {
  padding: 24px 36px;
}
.rando-map-placeholder {
  background: #f0f4f8; border-radius: 10px;
  padding: 36px 24px; text-align: center;
  border: 2px dashed rgba(0,0,0,.1);
}
.rando-map-placeholder-icon { font-size: 2rem; margin-bottom: 10px; display: block; }
.rando-map-placeholder-text {
  font-size: .88rem; font-weight: 600; color: #6b7f96;
}

/* SECTION COMMUNAUTÉ */
.rando-community-section {
  background: var(--beige-light, #f7f4ef);
  padding: 0 0 48px;
}
.rando-community-placeholder {
  background: rgba(255,255,255,.7);
  border-radius: 14px;
  border: 1.5px dashed rgba(42,157,92,.3);
  padding: 40px 28px; text-align: center;
  backdrop-filter: blur(4px);
}
.rando-community-icon { font-size: 2.2rem; margin-bottom: 12px; display: block; }
.rando-community-title {
  font-size: 1.05rem; font-weight: 800; color: #0c1e2e;
  margin-bottom: 8px;
}
.rando-community-sub {
  font-size: .88rem; color: #6b7f96; line-height: 1.65;
}

/* ASIDE / SIDEBAR */
.rando-aside {}
.rando-aside-card {
  background: #fff; border-radius: 14px;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  padding: 22px; margin-bottom: 20px;
  position: sticky; top: 80px;
}
.rando-aside-title {
  font-size: .68rem; font-weight: 900; letter-spacing: .14em;
  text-transform: uppercase; color: #6b7f96;
  margin-bottom: 16px; display: block;
}
.rando-aside-info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,.05);
  font-size: .84rem;
}
.rando-aside-info-row:last-of-type { border-bottom: none; }
.rando-aside-info-key {
  font-weight: 700; color: #6b7f96; font-size: .78rem;
}
.rando-aside-info-val {
  font-weight: 800; color: #0c1e2e;
}
.rando-aside-gpx {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; background: #2a9d5c; color: #fff;
  font-size: .82rem; font-weight: 800; padding: 11px 18px;
  border-radius: 9px; text-decoration: none;
  margin-top: 16px; transition: opacity .2s;
}
.rando-aside-gpx:hover { opacity: .88; }
.rando-aside-completions {
  text-align: center; padding: 12px 0 4px;
  font-size: .84rem; color: #6b7f96;
}
.rando-aside-completions strong {
  font-size: 1.4rem; font-weight: 900; color: #0c1e2e;
  display: block; margin-bottom: 2px;
}
.rando-aside-share {
  margin-top: 16px;
}
.rando-fb-btn {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; background: #1877f2; color: #fff;
  font-size: .8rem; font-weight: 800; padding: 10px 16px;
  border-radius: 8px; text-decoration: none;
  transition: opacity .2s;
}
.rando-fb-btn:hover { opacity: .88; }


/* Expérience Rando Zone85 */
.rando-zone85-section{padding:26px 36px;border-top:1px solid rgba(0,0,0,.06)}
.rando-section-kicker{font-size:.68rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#2a9d5c;margin-bottom:10px}
.rando-ambiance-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:10px}
.rando-ambiance-card{background:#f8f4ef;border:1px solid rgba(12,30,46,.08);border-radius:12px;padding:12px;text-align:center}
.rando-ambiance-label{font-size:.72rem;font-weight:800;color:#0c1e2e;margin-bottom:6px}
.rando-ambiance-stars{font-size:.82rem;letter-spacing:1px;color:#C9962A}
.rando-block-recit{padding:26px 36px;background:linear-gradient(135deg,rgba(201,150,42,.08),rgba(42,157,92,.05));border-top:1px solid rgba(0,0,0,.05);border-bottom:1px solid rgba(0,0,0,.05)}
.rando-block-recit h2{font-size:1.25rem;margin:0 0 10px;color:#0c1e2e;font-weight:900}
.rando-block-recit p{font-size:1rem;line-height:1.85;color:#30465c;margin:0}
.rando-block-treasure{padding:24px 36px}
.rando-treasure-card{display:grid;grid-template-columns:170px 1fr;gap:18px;align-items:stretch;background:#fff8ed;border:1px solid rgba(201,150,42,.28);border-radius:16px;overflow:hidden;box-shadow:0 6px 18px rgba(201,150,42,.08)}
.rando-treasure-img{background:#f0ece7;min-height:140px}
.rando-treasure-img img{width:100%;height:100%;object-fit:cover;display:block}
.rando-treasure-body{padding:18px}
.rando-treasure-body h3{margin:0 0 8px;font-size:1.05rem;color:#0c1e2e;font-weight:900}
.rando-treasure-body p{margin:0;font-size:.92rem;line-height:1.7;color:#4b6074}
.rando-passport-btn{width:100%;border:0;background:#ea5649;color:white;border-radius:12px;padding:13px 16px;font-weight:900;cursor:pointer;box-shadow:0 8px 22px rgba(234,86,73,.22);transition:transform .15s,opacity .15s}
.rando-passport-btn:hover{transform:translateY(-1px)}
.rando-passport-btn.done{background:#2a9d5c;cursor:default}
.rando-flash{margin:0 0 20px;padding:14px 18px;border-radius:12px;font-size:.9rem;font-weight:700}
.rando-flash-ok{background:rgba(42,157,92,.1);color:#1a7a42;border:1px solid rgba(42,157,92,.22)}
.rando-flash-err{background:rgba(234,86,73,.1);color:#b83a2f;border:1px solid rgba(234,86,73,.22)}
.rando-explorers{margin-top:18px;padding-top:16px;border-top:1px solid rgba(0,0,0,.06)}
.rando-explorer-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.rando-explorer-pill{background:#f8f4ef;border-radius:999px;padding:7px 11px;font-size:.78rem;font-weight:800;color:#0c1e2e}
@media(max-width:700px){.rando-ambiance-grid{grid-template-columns:repeat(2,1fr)}.rando-treasure-card{grid-template-columns:1fr}.rando-treasure-img{min-height:190px}.rando-zone85-section{padding:22px 24px}}

.rando-aside-mission-btn {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; background: #0c1e2e; color: #fff;
  font-size: .82rem; font-weight: 800; padding: 11px 18px;
  border-radius: 9px; text-decoration: none;
  margin-top: 10px; transition: opacity .2s;
  opacity: .7;
}
.rando-aside-mission-btn:hover { opacity: 1; }
.rando-aside-mission-note {
  font-size: .7rem; color: #aaa; text-align: center;
  margin-top: 6px; line-height: 1.5;
}

/* RESPONSIVE */
@media (max-width: 960px) {
  .rando-layout-inner { grid-template-columns: 1fr; }
  .rando-block-text   { padding: 24px; }
  .rando-block-image,
  .rando-block-quote,
  .rando-block-info,
  .rando-block-conseil,
  .rando-block-around,
  .rando-block-youtube,
  .rando-block-map    { padding-left: 24px; padding-right: 24px; }
  .rando-aside-card   { position: static; }
}
@media (max-width: 600px) {
  .rando-hero         { padding: 80px 0 48px; }
  .rando-hero h1      { font-size: 1.75rem; }
  .rando-infos-inner  { gap: 4px; }
  .rando-info-item    { padding: 8px 12px; min-width: 80px; }
  .rando-layout       { padding: 28px 0 52px; }
}

.rando-map-block{padding:24px 36px 4px}
#zone85RandoMap{height:360px;width:100%;border-radius:14px;overflow:hidden;background:#f4efe8;border:1px solid rgba(0,0,0,.06)}

.rando-validation-box{margin-top:16px}
.rando-proof-form{margin-top:14px;background:#fff7f5;border:1px solid rgba(234,86,73,.18);border-radius:14px;padding:14px}
.rando-proof-title{font-size:.76rem;font-weight:900;color:#ea5649;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.rando-proof-note{font-size:.78rem;line-height:1.55;color:#6b7f96;margin:0 0 10px}
.rando-proof-input{width:100%;font-size:.78rem;margin-bottom:10px}
.rando-proof-btn{width:100%;border:0;border-radius:10px;background:#0c1e2e;color:#fff;font-weight:900;padding:11px 12px;cursor:pointer}
.rando-proof-btn:hover{opacity:.92}
.rando-proof-modal{position:fixed;inset:0;z-index:99998;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(12,30,46,.56);backdrop-filter:blur(4px)}
.rando-proof-modal.is-open{display:flex}
.rando-proof-card{width:min(560px,94vw);background:#fff;border-radius:22px;box-shadow:0 28px 90px rgba(0,0,0,.24);overflow:hidden}
.rando-proof-head{padding:22px 24px 14px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.rando-proof-head h3{margin:0;color:#0c1e2e;font-size:1.28rem;line-height:1.2;font-weight:900}
.rando-proof-head p{margin:7px 0 0;color:#6b7f96;font-size:.9rem;line-height:1.45}
.rando-proof-close{border:0;background:#f3eee9;color:#0c1e2e;width:38px;height:38px;border-radius:999px;font-size:1.4rem;cursor:pointer;font-weight:900}
.rando-proof-body{padding:20px 24px 24px}
.rando-proof-field{margin-bottom:16px}
.rando-proof-field label{display:block;font-size:.74rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:#6b7f96;margin-bottom:7px}
.rando-proof-stars{display:flex;gap:8px;flex-wrap:wrap}
.rando-proof-stars label{margin:0;text-transform:none;letter-spacing:0;font-size:1rem;cursor:pointer;background:#f7f3ef;border:1px solid rgba(0,0,0,.08);border-radius:999px;padding:8px 11px;color:#c9962a}
.rando-proof-stars input{display:none}
.rando-proof-stars input:checked + span{font-weight:900;color:#ea5649}
.rando-proof-textarea{width:100%;min-height:96px;border:1px solid rgba(0,0,0,.14);border-radius:14px;padding:13px 14px;font:inherit;resize:vertical;color:#0c1e2e}
.rando-proof-file{width:100%;background:#f7f3ef;border:1px dashed rgba(12,30,46,.24);border-radius:14px;padding:13px;font-size:.88rem}
.rando-proof-submit{width:100%;border:0;border-radius:14px;background:#ea5649;color:#fff;font-weight:900;padding:14px 16px;cursor:pointer;box-shadow:0 10px 26px rgba(234,86,73,.22)}
.rando-proof-hint{font-size:.8rem;color:#6b7f96;line-height:1.45;margin-top:8px}
.leaflet-fallback{display:flex;align-items:center;justify-content:center;color:#6b7f96;font-size:.9rem;height:100%;text-align:center;padding:20px}

.rando-click-spark{position:fixed;pointer-events:none;z-index:9999;font-size:14px;animation:randoSpark .75s ease-out forwards}
@keyframes randoSpark{0%{transform:translate(-50%,-50%) scale(.6);opacity:1}100%{transform:translate(-50%,-80px) scale(1.25);opacity:0}}


/* Retours validés des explorateurs */
.rando-returns-section{margin-top:32px;background:#fff;border:1px solid rgba(0,0,0,.07);border-radius:18px;box-shadow:0 8px 30px rgba(12,30,46,.06);overflow:hidden}
.rando-returns-head{padding:26px 30px;border-bottom:1px solid rgba(0,0,0,.06);background:linear-gradient(135deg,rgba(42,157,92,.08),rgba(201,150,42,.06))}
.rando-returns-kicker{font-size:.68rem;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#2a9d5c;margin-bottom:6px}
.rando-returns-title{margin:0;font-size:1.28rem;font-weight:900;color:#0c1e2e}
.rando-returns-sub{margin:6px 0 0;color:#6b7f96;font-size:.92rem;line-height:1.55}
.rando-returns-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;padding:24px 30px 30px}
.rando-return-card{background:#fbf8f3;border:1px solid #eadfd3;border-radius:16px;overflow:hidden;display:grid;grid-template-columns:140px 1fr;min-height:150px}
.rando-return-photo{background:#eee7de;min-height:150px;display:flex;align-items:center;justify-content:center;color:#8fa0ad;font-weight:800;font-size:.78rem;text-transform:uppercase;letter-spacing:.08em}
.rando-return-photo img{width:100%;height:100%;object-fit:cover;display:block;cursor:zoom-in}
.rando-return-body{padding:18px}
.rando-return-meta{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
.rando-return-pseudo{font-weight:900;color:#0c1e2e}
.rando-return-stars{font-size:.86rem;color:#c9962a;white-space:nowrap}
.rando-return-text{font-size:.94rem;line-height:1.65;color:#31475d;margin:0}
.rando-return-date{margin-top:12px;font-size:.75rem;color:#8fa0ad;font-weight:700}
@media(max-width:760px){.rando-returns-grid{grid-template-columns:1fr;padding:18px}.rando-return-card{grid-template-columns:1fr}.rando-return-photo{height:210px}.rando-returns-head{padding:22px}}

/* Lightbox photos randos */
.z85-lightbox{position:fixed;inset:0;background:rgba(12,30,46,.88);display:none;align-items:center;justify-content:center;z-index:99999;padding:24px}
.z85-lightbox.is-visible{display:flex}
.z85-lightbox img{max-width:min(1100px,94vw);max-height:88vh;border-radius:14px;box-shadow:0 24px 80px rgba(0,0,0,.38);object-fit:contain;background:#fff}
.z85-lightbox-close{position:fixed;top:18px;right:22px;background:#fff;border:0;border-radius:999px;width:44px;height:44px;font-size:26px;line-height:44px;cursor:pointer;color:#0c1e2e;box-shadow:0 12px 30px rgba(0,0,0,.25)}
.rando-main-wrap img[data-lightbox], .rando-treasure-card img[data-lightbox], .rando-gallery-item img[data-lightbox]{cursor:zoom-in}

</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="rando-hero">
  <?php if (!empty($rando['cover_image'])): ?>
    <img class="rando-hero-cover" data-lightbox="1"
         src="<?= e(media_url($rando['cover_image'])) ?>"
         alt="<?= htmlspecialchars($rando['title'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.style.display='none';">
  <?php endif; ?>
  <div class="container rando-hero-inner">
    <div class="rando-hero-badges">
      <span class="rando-hero-badge">
        <?= htmlspecialchars($sec_label, ENT_QUOTES, 'UTF-8') ?>
      </span>
      <span class="rando-hero-badge" style="border-color:rgba(201,150,42,.4)">
        <?= htmlspecialchars($dif_label, ENT_QUOTES, 'UTF-8') ?>
        &nbsp;<?php for ($i = 1; $i <= $dif_stars; $i++) echo '&#x2605;'; ?>
      </span>
    </div>
    <h1><?= htmlspecialchars($rando['title'], ENT_QUOTES, 'UTF-8') ?></h1>
  </div>
</section>

<!-- ===================== BREADCRUMB ===================== -->
<nav class="rando-breadcrumb" aria-label="Fil d'Ariane">
  <div class="container">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="index.php" itemprop="item"><span itemprop="name">Accueil</span></a>
        <meta itemprop="position" content="1">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a href="randos.php" itemprop="item"><span itemprop="name">Randonn&eacute;es</span></a>
        <meta itemprop="position" content="2">
      </li>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <span class="current" itemprop="name">
          <?= htmlspecialchars(mb_strimwidth($rando['title'], 0, 55, '…'), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <meta itemprop="position" content="3">
      </li>
    </ol>
  </div>
</nav>

<!-- ===================== INFOS PRATIQUES BANDEAU ===================== -->
<div class="rando-infos-band">
  <div class="container">
    <div class="rando-infos-inner">
      <?php if ($dist_str): ?>
        <div class="rando-info-item">
          <span class="rando-info-item-icon">&#x1F4CD;</span>
          <span class="rando-info-item-value"><?= htmlspecialchars($dist_str, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="rando-info-item-label">Distance</span>
        </div>
      <?php endif; ?>
      <?php if ($dur_str): ?>
        <div class="rando-info-item">
          <span class="rando-info-item-icon">&#x23F1;</span>
          <span class="rando-info-item-value"><?= htmlspecialchars($dur_str, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="rando-info-item-label">Dur&eacute;e</span>
        </div>
      <?php endif; ?>
      <div class="rando-info-item">
        <span class="rando-info-item-icon">&#x1F3C6;</span>
        <span class="rando-info-item-value">
          <span class="rando-stars-band">
            <?php for ($i = 1; $i <= 4; $i++): ?>
              <span class="rando-star-band <?= $i <= $dif_stars ? 'on' : 'off' ?>">&#x2605;</span>
            <?php endfor; ?>
          </span>
        </span>
        <span class="rando-info-item-label"><?= htmlspecialchars($dif_label, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php if (!empty($rando['commune'])): ?>
        <div class="rando-info-item">
          <span class="rando-info-item-icon">&#x1F3D8;</span>
          <span class="rando-info-item-value"><?= htmlspecialchars($rando['commune'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="rando-info-item-label">Commune</span>
        </div>
      <?php endif; ?>
      <?php if (!empty($rando['start_point'])): ?>
        <div class="rando-info-item">
          <span class="rando-info-item-icon">&#x1F697;</span>
          <span class="rando-info-item-value" style="font-size:.78rem;max-width:140px;white-space:normal;text-align:center">
            <?= htmlspecialchars($rando['start_point'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <span class="rando-info-item-label">D&eacute;part</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===================== LAYOUT PRINCIPAL ===================== -->
<section class="rando-layout">
  <div class="container">
    <div class="rando-layout-inner">

      <?php if (!empty($rando_flash)): ?>
        <div style="grid-column:1/-1">
          <div class="rando-flash rando-flash-<?= $rando_flash_type === 'err' ? 'err' : 'ok' ?>">
            <?= e($rando_flash) ?>
          </div>
        </div>
      <?php endif; ?>


      <!-- Corps principal -->
      <div>
        <div class="rando-main-wrap">

          <?php if (!empty($rando['cover_image'])): ?>
            <img class="rando-cover-img" data-lightbox="1"
                 src="<?= e(media_url($rando['cover_image'])) ?>"
                 alt="<?= htmlspecialchars($rando['title'], ENT_QUOTES, 'UTF-8') ?>"
                 loading="lazy" onerror="this.style.display='none';">
          <?php endif; ?>


          <?php // Intro courte (accroche) — priorité sur summary si intro_text défini ?>
          <?php $_intro = $rando['intro_text'] ?? ''; ?>
          <?php if ($_intro): ?>
            <div class="rando-intro"><?= nl2br(e($_intro)) ?></div>
          <?php endif; ?>

          <?php // Description longue ?>
          <?php if (!empty($rando['description'])): ?>
            <div class="rando-description"><?= nl2br(e($rando['description'])) ?></div>
          <?php endif; ?>

          <?php if (!empty($rando['why_text'])): ?>
            <div class="rando-why">
              <div class="rando-why-kicker">Pourquoi cette rando ?</div>
              <div class="rando-why-text"><?= nl2br(e($rando['why_text'])) ?></div>
            </div>
          <?php endif; ?>

          <?php if (!empty($recommended_seasons)): ?>
            <?php $_season_labels = ['printemps'=>'🌱 Printemps','ete'=>'☀️ Été','automne'=>'🍂 Automne','hiver'=>'❄️ Hiver']; ?>
            <div style="padding:18px 36px 0">
              <div class="rando-section-kicker" style="margin-bottom:6px">Meilleure période</div>
              <div class="rando-season-pills">
                <?php foreach ($recommended_seasons as $_s): if (!isset($_season_labels[$_s])) continue; ?>
                  <span class="rando-season-pill"><?= e($_season_labels[$_s]) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($rando_communes)): ?>
            <div style="padding:18px 36px 0">
              <div class="rando-section-kicker" style="margin-bottom:6px">Communes traversées</div>
              <div class="rando-commune-pills">
                <?php foreach ($rando_communes as $_commune): ?>
                  <span class="rando-commune-pill">🏛 <?= e($_commune) ?></span>
                <?php endforeach; ?>
              </div>
              <div class="rando-passport-note">En validant cette randonnée, ces communes peuvent être tamponnées dans votre Passeport Vendéen.</div>
            </div>
          <?php endif; ?>


          <?php
            $ambiance = [
              ['Nature',      '🌿', $rando['nature_score'] ?? null],
              ['Patrimoine',  '🏛', $rando['patrimoine_score'] ?? null],
              ['Famille',     '👨‍👩‍👧‍👦', $rando['famille_score'] ?? null],
              ['Photo',       '📸', $rando['photo_score'] ?? null],
            ];
            $ambiance_has = false;
            foreach ($ambiance as $a) { if ($a[2] !== null && $a[2] !== '') $ambiance_has = true; }
          ?>
          <?php if ($ambiance_has): ?>
            <div class="rando-zone85-section">
              <div class="rando-section-kicker">Ambiance du parcours</div>
              <div class="rando-ambiance-grid">
                <?php foreach ($ambiance as [$label,$icon,$score]): ?>
                  <?php if ($score !== null && $score !== ''): $score=(int)$score; ?>
                    <div class="rando-ambiance-card">
                      <div class="rando-ambiance-label"><?= $icon ?> <?= e($label) ?></div>
                      <div class="rando-ambiance-stars"><?= str_repeat('★', max(0,min(5,$score))) ?><?= str_repeat('☆', 5-max(0,min(5,$score))) ?></div>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($rando['gps_lat']) && !empty($rando['gps_lng'])): ?>
            <div class="rando-map-block">
              <div id="zone85RandoMap"
                   data-lat="<?= e((string)$rando['gps_lat']) ?>"
                   data-lng="<?= e((string)$rando['gps_lng']) ?>"
                   data-title="<?= e($rando['title']) ?>"
                   data-gpx="<?= e(media_url($rando['gpx_file'] ?? $rando['gpx_url'] ?? '')) ?>"></div>
            </div>
          <?php endif; ?>

<?php if (empty($blocks) && empty($_intro) && empty($rando['description'])): ?>
            <div class="rando-block-text" style="color:#6b7f96;font-style:italic;padding:28px 36px">
              <p>La fiche de cette randonnée est en cours de rédaction.</p>
            </div>
          <?php elseif (!empty($blocks)): ?>

            <?php foreach ($blocks as $block): ?>
              <?php
                // Décode le JSON de contenu
                $c = [];
                if (!empty($block['content'])) {
                    $decoded = json_decode($block['content'], true);
                    if (is_array($decoded)) $c = $decoded;
                }
                $btype = $block['type'] ?? 'text';
              ?>

              <?php if ($btype === 'text'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-text">
                  <?php if (!empty($c['heading'])): ?>
                    <h2><?= htmlspecialchars($c['heading'], ENT_QUOTES, 'UTF-8') ?></h2>
                  <?php endif; ?>
                  <?= nl2br(htmlspecialchars($c['body'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                </div>

              <?php elseif ($btype === 'image'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-image">
                  <?php $_img_src = $c['src'] ?? ''; ?>
                  <?php if ($_img_src): ?>
                  <figure>
                    <img src="<?= e(media_url($_img_src)) ?>"
                         alt="<?= e($c['caption'] ?? '') ?>"
                         loading="lazy" data-lightbox="1" onerror="this.style.display='none';">
                    <?php if (!empty($c['caption'])): ?>
                      <figcaption><?= e($c['caption']) ?></figcaption>
                    <?php endif; ?>
                  </figure>
                  <?php endif; ?>
                </div>

              <?php elseif ($btype === 'gallery'): ?>
                <?php $_gimgs = $c['images'] ?? []; ?>
                <?php if (!empty($_gimgs)): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-gallery">
                  <?php if (!empty($c['caption'])): ?>
                  <div class="rando-gallery-caption"><?= e($c['caption']) ?></div>
                  <?php endif; ?>
                  <div class="rando-gallery-grid">
                    <?php foreach ($_gimgs as $_gi): ?>
                    <div class="rando-gallery-item">
                      <img src="<?= e(media_url($_gi)) ?>" alt="" loading="lazy" data-lightbox="1" onerror="this.style.display='none';">
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>

              <?php elseif ($btype === 'quote'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-quote">
                  <blockquote>
                    <?= nl2br(e($c['text'] ?? $c['body'] ?? '')) ?>
                    <?php $_qsrc = $c['source'] ?? $c['author'] ?? ''; ?>
                    <?php if ($_qsrc): ?>
                      <footer style="font-size:.8rem;margin-top:8px;font-style:normal;font-weight:700;color:#2a9d5c">
                        — <?= e($_qsrc) ?>
                      </footer>
                    <?php endif; ?>
                  </blockquote>
                </div>

              <?php elseif ($btype === 'info'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-info">
                  <div class="rando-block-info-inner">
                    <span class="rando-block-info-icon">&#x2139;&#xFE0F;</span>
                    <div class="rando-block-info-body">
                      <?php $_ihead = $c['heading'] ?? $c['title'] ?? ''; ?>
                      <?php if ($_ihead): ?>
                        <div class="rando-block-info-title"><?= e($_ihead) ?></div>
                      <?php endif; ?>
                      <div class="rando-block-info-text">
                        <?= nl2br(e($c['body'] ?? $c['text'] ?? '')) ?>
                      </div>
                    </div>
                  </div>
                </div>

              <?php elseif ($btype === 'conseil'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-conseil">
                  <div class="rando-block-conseil-inner">
                    <span class="rando-block-conseil-icon">&#x1F3AF;</span>
                    <div class="rando-block-conseil-text">
                      <?php if (!empty($c['title'])): ?>
                        <strong style="display:block;margin-bottom:4px;font-weight:800;color:#7a1e14">
                          <?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                      <?php endif; ?>
                      <?= nl2br(htmlspecialchars($c['body'] ?? $c['text'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                  </div>
                </div>

              <?php elseif ($btype === 'around'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-around">
                  <p class="rando-block-around-title">
                    <?= htmlspecialchars($c['title'] ?? 'À voir autour', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                  <?php
                    $items = $c['items'] ?? [];
                    // Accepte aussi un body texte multiligne
                    if (empty($items) && !empty($c['body'])) {
                        $items = array_filter(array_map('trim', explode("\n", $c['body'])));
                    }
                  ?>
                  <?php if (!empty($items)): ?>
                    <ul>
                      <?php foreach ($items as $it): ?>
                        <li><?= htmlspecialchars($it, ENT_QUOTES, 'UTF-8') ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>

              <?php elseif ($btype === 'youtube'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-youtube">
                  <?php
                    $yt_url = $c['url'] ?? $c['src'] ?? '';
                    // Extrait l'ID YouTube
                    $yt_id  = '';
                    if (preg_match('/(?:youtu\.be\/|v=)([A-Za-z0-9_\-]{11})/', $yt_url, $m)) {
                        $yt_id = $m[1];
                    }
                  ?>
                  <?php if ($yt_id): ?>
                    <div class="rando-video-wrap">
                      <iframe
                        src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($yt_id, ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($c['title'] ?? 'Vidéo rando Zone85', ENT_QUOTES, 'UTF-8') ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        loading="lazy" data-lightbox="1" onerror="this.style.display='none';">
                      </iframe>
                    </div>
                  <?php else: ?>
                    <p style="font-size:.84rem;color:#aaa;text-align:center;padding:16px 0">[Vidéo non disponible]</p>
                  <?php endif; ?>
                </div>


              <?php elseif ($btype === 'recit'): ?>
                <div class="rando-block-recit">
                  <div class="rando-section-kicker">Récit Zone85</div>
                  <h2><?= e($c['heading'] ?? 'Le récit Zone85') ?></h2>
                  <p><?= nl2br(e($c['body'] ?? $c['text'] ?? '')) ?></p>
                </div>

              <?php elseif ($btype === 'treasure'): ?>
                <div class="rando-block-treasure">
                  <div class="rando-treasure-card">
                    <div class="rando-treasure-img">
                      <?php if (!empty($c['src'])): ?>
                        <img src="<?= e(media_url($c['src'])) ?>" alt="<?= e($c['title'] ?? 'Trésor du parcours') ?>" loading="lazy" data-lightbox="1" onerror="this.style.display='none';">
                      <?php endif; ?>
                    </div>
                    <div class="rando-treasure-body">
                      <div class="rando-section-kicker">Trésor du parcours</div>
                      <h3><?= e($c['title'] ?? 'À ne pas manquer') ?></h3>
                      <p><?= nl2br(e($c['body'] ?? $c['text'] ?? '')) ?></p>
                    </div>
                  </div>
                </div>

              <?php elseif ($btype === 'map'): ?>
                <div class="rando-block-sep"></div>
                <div class="rando-block-map">
                  <div class="rando-map-placeholder">
                    <span class="rando-map-placeholder-icon">&#x1F5FA;</span>
                    <p class="rando-map-placeholder-text">
                      Carte interactive &mdash; bient&ocirc;t disponible
                    </p>
                  </div>
                </div>

              <?php endif; ?>

            <?php endforeach; ?>

          <?php endif; // blocs ?>
          <?php // ferme le elseif(!empty($blocks)) — note: si intro uniquement, on ne fait rien de plus ?>

          <!-- Pied de fiche -->
          <div style="padding:20px 36px 28px;border-top:1px solid rgba(0,0,0,.06);margin-top:8px;
                      display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <a href="randos.php?secteur=<?= urlencode($sec) ?>"
               style="font-size:.82rem;font-weight:700;color:#2a9d5c;text-decoration:none">
              &larr; Retour aux randonn&eacute;es <?= htmlspecialchars($sec_label, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a href="<?= htmlspecialchars($fb_share, ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener noreferrer"
               style="font-size:.78rem;font-weight:700;color:#1877f2;text-decoration:none">
              &#x1F4F1; Partager sur Facebook
            </a>
          </div>
        </div>

        <!-- Section communauté -->
        <div style="margin-top:32px">
          <div class="rando-community-placeholder" style="text-align:left">
            <span class="rando-community-icon">🥾</span>
            <p class="rando-community-title">Les Zonautes sont passés par ici</p>
            <p class="rando-community-sub">
              <strong><?= (int)$nb_completions ?></strong> Zonaute<?= $nb_completions > 1 ? 's' : '' ?> <?= $nb_completions > 1 ? 'ont' : 'a' ?> déjà tamponné cette randonnée.
            </p>
            <?php if (!empty($latest_explorers)): ?>
              <div class="rando-explorer-list">
                <?php foreach ($latest_explorers as $ex): ?>
                  <span class="rando-explorer-pill"><?= e($ex['pseudo'] ?? 'Zonaute') ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="rando-community-sub" style="margin-top:10px">Soyez parmi les premiers à laisser votre trace sur ce parcours.</p>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($validated_returns)): ?>
        <section class="rando-returns-section" aria-label="Retours des participants">
          <div class="rando-returns-head">
            <div class="rando-returns-kicker">Les explorateurs Zone85</div>
            <h2 class="rando-returns-title">Ils sont passés par ici</h2>
            <p class="rando-returns-sub">Photos, avis et notes des Zonautes dont la participation a été validée par l'équipe Zone85.</p>
          </div>
          <div class="rando-returns-grid">
            <?php foreach ($validated_returns as $ret): ?>
              <?php $rating = max(0, min(5, (int)($ret['proof_rating'] ?? 0))); ?>
              <article class="rando-return-card">
                <div class="rando-return-photo">
                  <?php if (!empty($ret['photo_path'])): ?>
                    <img src="<?= e(media_url($ret['photo_path'])) ?>" alt="Photo de participation" loading="lazy" data-lightbox="1" onerror="this.style.display='none';this.parentElement.textContent='Photo';">
                  <?php else: ?>
                    Photo
                  <?php endif; ?>
                </div>
                <div class="rando-return-body">
                  <div class="rando-return-meta">
                    <span class="rando-return-pseudo"><?= e($ret['pseudo'] ?? 'Zonaute') ?></span>
                    <?php if ($rating): ?>
                      <span class="rando-return-stars"><?= str_repeat('★', $rating) ?><?= str_repeat('☆', 5-$rating) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($ret['proof_review'])): ?>
                    <p class="rando-return-text">“<?= nl2br(e($ret['proof_review'])) ?>”</p>
                  <?php endif; ?>
                  <div class="rando-return-date">
                    Validé<?= ($ret['validated_at'] ?? '') ? ' le '.e(date('d/m/Y', strtotime($ret['validated_at']))) : '' ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>
      </div>

      <!-- Aside sticky -->
      <aside class="rando-aside">
        <div class="rando-aside-card">
          <span class="rando-aside-title">Infos pratiques</span>

          <?php if ($dist_str): ?>
            <div class="rando-aside-info-row">
              <span class="rando-aside-info-key">&#x1F4CD; Distance</span>
              <span class="rando-aside-info-val"><?= htmlspecialchars($dist_str, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <?php if ($dur_str): ?>
            <div class="rando-aside-info-row">
              <span class="rando-aside-info-key">&#x23F1; Dur&eacute;e</span>
              <span class="rando-aside-info-val"><?= htmlspecialchars($dur_str, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <div class="rando-aside-info-row">
            <span class="rando-aside-info-key">&#x1F3C6; Difficult&eacute;</span>
            <span class="rando-aside-info-val"><?= htmlspecialchars($dif_label, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="rando-aside-info-row">
            <span class="rando-aside-info-key">&#x1F5FA; Secteur</span>
            <span class="rando-aside-info-val" style="color:<?= htmlspecialchars($sec_color, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($sec_label, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <?php if (!empty($rando['commune'])): ?>
            <div class="rando-aside-info-row">
              <span class="rando-aside-info-key">&#x1F3D8; Commune</span>
              <span class="rando-aside-info-val"><?= htmlspecialchars($rando['commune'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <?php if (!empty($rando['start_point'])): ?>
            <div class="rando-aside-info-row" style="align-items:flex-start">
              <span class="rando-aside-info-key">&#x1F697; D&eacute;part</span>
              <span class="rando-aside-info-val" style="font-size:.76rem;text-align:right;max-width:160px">
                <?= htmlspecialchars($rando['start_point'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
          <?php endif; ?>

          <?php $_aside_gpx = $rando['gpx_file'] ?? $rando['gpx_url'] ?? ''; ?>
          <?php if (!empty($_aside_gpx)): ?>
            <a href="<?= e(media_url($_aside_gpx)) ?>"
               class="rando-aside-gpx" download>
              &#x1F4E5; T&eacute;l&eacute;charger GPX
            </a>
          <?php endif; ?>

          <!-- Completions -->
          <div class="rando-aside-completions">
            <strong><?= $nb_completions ?></strong>
            membre<?= $nb_completions > 1 ? 's' : '' ?> <?= $nb_completions > 1 ? 'ont' : 'a' ?> fait cette rando
          </div>



          <div class="rando-validation-box">
            <?php if (!$user_has_completed && $is_logged_in): ?>
              <p style="font-size:.74rem;color:#6b7f96;line-height:1.5;margin-bottom:12px">
                🥾 Tamponnez votre Passeport gratuitement.<br>
                📸 Ajoutez une photo devant le Trésor pour débloquer +25 XP.
              </p>
            <?php endif; ?>
            <?php if ($user_has_completed): ?>
              <?php if ($user_rando_status === 'validated'): ?>
                <button type="button" class="rando-passport-btn done">✓ Rando validée · +25 XP</button>
              <?php elseif ($user_rando_status === 'pending'): ?>
                <button type="button" class="rando-passport-btn done">⏳ Photo envoyée · validation en attente</button>
              <?php else: ?>
                <button type="button" class="rando-passport-btn done">✓ Tamponnée dans mon Passeport</button>
              <?php endif; ?>
            <?php elseif ($is_logged_in): ?>
              <form method="post" style="margin-top:16px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="stamp_rando">
                <button type="submit" class="rando-passport-btn">🥾 Tamponner mon Passeport</button>
              </form>
            <?php else: ?>
              <a href="login.php" class="rando-passport-btn" style="display:block;text-align:center;text-decoration:none">Se connecter pour tamponner</a>
            <?php endif; ?>

            <?php if ($is_logged_in && $user_rando_status !== 'validated'): ?>
              <div class="rando-proof-form">
                <div class="rando-proof-title">Débloquer les +25 XP</div>
                <p class="rando-proof-note">Pour les points, envoyez une note, un petit avis et une photo prise devant <strong><?= e($first_treasure_title) ?></strong>. L'équipe Zone85 validera ensuite.</p>
                <button type="button" class="rando-proof-btn" id="openProofModal">Demander les +25 XP</button>
              </div>
            <?php endif; ?>
          </div>

          <!-- Partage FB -->
          <div class="rando-aside-share">
            <a href="<?= htmlspecialchars($fb_share, ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener noreferrer"
               class="rando-fb-btn">
              &#x1F310; Partager sur Facebook
            </a>
          </div>

        </div>
      </aside>

    </div>
  </div>
</section>

<?php if ($is_logged_in && $user_rando_status !== 'validated'): ?>
<div class="rando-proof-modal" id="proofModal" aria-hidden="true">
  <form method="post" enctype="multipart/form-data" class="rando-proof-card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="submit_rando_proof">
    <div class="rando-proof-head">
      <div>
        <h3>Valider ma rando</h3>
        <p>Déposez votre avis et une photo prise devant <strong><?= e($first_treasure_title) ?></strong>. Après validation, vous gagnez +25 XP.</p>
      </div>
      <button class="rando-proof-close" type="button" id="closeProofModal">×</button>
    </div>
    <div class="rando-proof-body">
      <div class="rando-proof-field">
        <label>Ma note</label>
        <div class="rando-proof-stars">
          <?php for ($i=1; $i<=5; $i++): ?>
            <label><input type="radio" name="proof_rating" value="<?= $i ?>" <?= $i===5?'checked':'' ?>><span><?= str_repeat('★', $i) ?></span></label>
          <?php endfor; ?>
        </div>
      </div>
      <div class="rando-proof-field">
        <label>Mon avis</label>
        <textarea name="proof_review" class="rando-proof-textarea" maxlength="900" required placeholder="Votre ressenti sur la balade, le parcours, le point remarquable..."></textarea>
      </div>
      <div class="rando-proof-field">
        <label>Photo devant le Trésor du parcours</label>
        <input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp" class="rando-proof-file" required>
        <div class="rando-proof-hint">La photo doit montrer votre passage devant le Trésor du parcours. Elle arrivera dans le back-office : Admin > Communauté > Validations Randos.</div>
      </div>
      <button class="rando-proof-submit" type="submit">Envoyer ma participation</button>
    </div>
  </form>
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var el = document.getElementById('zone85RandoMap');
  if (el && !window.L) { el.innerHTML = '<div class="leaflet-fallback">Carte temporairement indisponible. Vérifiez la connexion ou la politique de sécurité.</div>'; }
  if (el && window.L) {
    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
    if (isNaN(lat) || isNaN(lng)) { el.innerHTML = '<div class="leaflet-fallback">Carte indisponible : coordonnées manquantes.</div>'; return; }
    var map = L.map(el, {scrollWheelZoom:false}).setView([lat,lng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    setTimeout(function(){ map.invalidateSize(); }, 250);
    L.marker([lat,lng]).addTo(map).bindPopup('<strong>'+String(el.dataset.title || 'Rando').replace(/</g,'&lt;')+'</strong>');
    var gpx = (el.dataset.gpx || '').trim();
    if (gpx) {
      fetch(gpx, {cache:'no-store', credentials:'same-origin'}).then(function(res){
        if (!res.ok) throw new Error('GPX HTTP '+res.status);
        return res.text();
      }).then(function(xmlText){
        var pts = [];

        // 1) Parsing DOM classique : GPX avec balises <trkpt>, <rtept> ou <wpt>.
        try {
          var xml = new DOMParser().parseFromString(xmlText, 'application/xml');
          var parseErr = xml.getElementsByTagName('parsererror');
          if (!parseErr.length) {
            ['trkpt','rtept','wpt'].forEach(function(tag){
              if (pts.length) return;
              var nodes = Array.prototype.slice.call(xml.getElementsByTagName(tag));
              pts = nodes.map(function(pt){
                return [parseFloat(pt.getAttribute('lat')), parseFloat(pt.getAttribute('lon'))];
              }).filter(function(p){ return !isNaN(p[0]) && !isNaN(p[1]); });
            });
          }
        } catch(e) {}

        // 2) Fallback regex : utile si le GPX a un namespace, un format atypique ou si le DOM bloque.
        if (!pts.length) {
          var re = /<(?:[a-zA-Z0-9_\-]+:)?(?:trkpt|rtept|wpt)\b[^>]*?lat=["']([^"']+)["'][^>]*?lon=["']([^"']+)["'][^>]*?>/g;
          var m;
          while ((m = re.exec(xmlText)) !== null) {
            var lat2 = parseFloat(m[1]);
            var lon2 = parseFloat(m[2]);
            if (!isNaN(lat2) && !isNaN(lon2)) pts.push([lat2, lon2]);
          }
        }

        if (pts.length >= 2) {
          var line = L.polyline(pts, {
            color: '#ef5248',
            weight: 5,
            opacity: .95,
            lineJoin: 'round'
          }).addTo(map);
          map.fitBounds(line.getBounds(), {padding:[30,30], maxZoom:15});
          setTimeout(function(){ map.invalidateSize(); }, 350);
        } else if (pts.length === 1) {
          L.circleMarker(pts[0], {radius:7, color:'#ef5248', fillOpacity:.9}).addTo(map);
        } else {
          console.warn('ZONE85 GPX : aucun point trouvé dans le fichier', gpx);
        }
      }).catch(function(err){
        console.warn('ZONE85 GPX non affiché :', err);
      });
    }
  }
  document.addEventListener('click', function(e){
    if (!e.target.closest('a,button,.leaflet-container')) return;
    var s = document.createElement('span');
    s.className = 'rando-click-spark';
    s.textContent = '✦';
    s.style.left = e.clientX + 'px';
    s.style.top = e.clientY + 'px';
    document.body.appendChild(s);
    setTimeout(function(){ s.remove(); }, 800);
  }, true);

  // Lightbox simple pour les photos de randonnée
  var lb = document.createElement('div');
  lb.className = 'z85-lightbox';
  lb.innerHTML = '<button class="z85-lightbox-close" type="button" aria-label="Fermer">×</button><img alt="">';
  document.body.appendChild(lb);
  var lbImg = lb.querySelector('img');
  function closeLb(){ lb.classList.remove('is-visible'); lbImg.removeAttribute('src'); }
  lb.addEventListener('click', function(ev){ if (ev.target === lb || ev.target.classList.contains('z85-lightbox-close')) closeLb(); });
  document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') closeLb(); });


  // Modal participation rando (+25 XP)
  var proofModal = document.getElementById('proofModal');
  var openProof = document.getElementById('openProofModal');
  var closeProof = document.getElementById('closeProofModal');
  function openProofModal(){ if (proofModal) { proofModal.classList.add('is-open'); proofModal.setAttribute('aria-hidden','false'); } }
  function closeProofModal(){ if (proofModal) { proofModal.classList.remove('is-open'); proofModal.setAttribute('aria-hidden','true'); } }
  if (openProof) openProof.addEventListener('click', openProofModal);
  if (closeProof) closeProof.addEventListener('click', closeProofModal);
  if (proofModal) proofModal.addEventListener('click', function(ev){ if (ev.target === proofModal) closeProofModal(); });

  document.addEventListener('click', function(ev){
    var img = ev.target.closest('img[data-lightbox]');
    if (!img || !img.src) return;
    ev.preventDefault();
    lbImg.src = img.src;
    lb.classList.add('is-visible');
  });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
