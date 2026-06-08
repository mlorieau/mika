<?php
// ============================================================
// ZONE85 V12 — KTC : Kéto Kolé Tché
// Rubrique éditoriale mensuelle — 4 phases
// DB : ktc_episodes, ktc_episode_photos, ktc_propositions, ktc_votes
// ============================================================
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ── Session ───────────────────────────────────────────────────
$is_logged = function_exists('is_logged_in') ? is_logged_in() : (!empty($_SESSION['user_id']));
$user_id   = $is_logged ? (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0) : 0;

// ── État initial ──────────────────────────────────────────────
$episode     = null;
$photos      = [];
$user_prop   = null;
$user_vote   = null;
$vote_counts = [];
$vote_total  = 0;
$past_eps    = [];
$winner_data = null;
$flash       = '';
$flash_type  = 'ok';
$current_week = 0;
$week_map = ['week1' => 1, 'week2' => 2, 'week3' => 3, 'revealed' => 4];

// ── Chargement DB ─────────────────────────────────────────────
try {
    $pdo = db();
    if ($pdo) {
        // Épisode actif : préférer en cours (week1/2/3) sur révélé
        $s = $pdo->query("
            SELECT * FROM ktc_episodes
            WHERE status IN ('week1','week2','week3','revealed')
            ORDER BY (status = 'revealed') ASC, id DESC
            LIMIT 1
        ");
        $episode = $s->fetch() ?: null;

        if ($episode) {
            $current_week = $week_map[$episode['status']] ?? 0;

            // Photos visibles dans cette phase
            $sp = $pdo->prepare("
                SELECT * FROM ktc_episode_photos
                WHERE episode_id = :eid AND reveal_week <= :wk
                ORDER BY sort_order ASC, id ASC
            ");
            $sp->execute([':eid' => $episode['id'], ':wk' => $current_week]);
            $photos = $sp->fetchAll();

            if ($user_id > 0) {
                $s2 = $pdo->prepare("SELECT * FROM ktc_propositions WHERE episode_id=:eid AND user_id=:uid LIMIT 1");
                $s2->execute([':eid' => $episode['id'], ':uid' => $user_id]);
                $user_prop = $s2->fetch() ?: null;

                $s3 = $pdo->prepare("SELECT * FROM ktc_votes WHERE episode_id=:eid AND user_id=:uid LIMIT 1");
                $s3->execute([':eid' => $episode['id'], ':uid' => $user_id]);
                $user_vote = $s3->fetch() ?: null;
            }

            // Résultats des votes (semaine 3+)
            if ($current_week >= 3) {
                $sv = $pdo->prepare("SELECT vote_choice, COUNT(*) AS cnt FROM ktc_votes WHERE episode_id=:eid GROUP BY vote_choice");
                $sv->execute([':eid' => $episode['id']]);
                while ($row = $sv->fetch()) {
                    $vote_counts[$row['vote_choice']] = (int)$row['cnt'];
                }
                $vote_total = array_sum($vote_counts);
            }
        }

        // Épisodes passés (révélés + archivés)
        $exclude = $episode ? ' AND id != ' . (int)$episode['id'] : '';
        $past_eps = $pdo->query("
            SELECT id, title, slug, date_revelation, object_name, object_hidden
            FROM ktc_episodes
            WHERE status IN ('revealed','archived') {$exclude}
            ORDER BY id DESC LIMIT 6
        ")->fetchAll();

        // Gagnant à la révélation
        // Nécessite migration 025_v13_ktc_winner.sql (colonnes winner_proposition_id + answer_confidence)
        $winner_data = null;
        if ($episode && $current_week >= 4 && !empty($episode['winner_proposition_id'])) {
            try {
                $sw = $pdo->prepare("
                    SELECT p.proposition, p.submitted_at, u.pseudo, u.avatar_type, u.avatar_file, u.avatar_config
                    FROM ktc_propositions p
                    JOIN users u ON u.id = p.user_id
                    WHERE p.id = :pid LIMIT 1
                ");
                $sw->execute([':pid' => (int)$episode['winner_proposition_id']]);
                $winner_data = $sw->fetch() ?: null;
            } catch (Exception $e) {}
        }
    }
} catch (Exception $e) {
    // Dégradé silencieux
}

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $episode) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = 'Session expirée. Rechargez la page.';
        $flash_type = 'err';
    } elseif (!$is_logged) {
        $flash = 'Connectez-vous pour participer.';
        $flash_type = 'err';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'submit_proposition' && in_array($current_week, [1, 2], true)) {
                $text = trim(safe_input($_POST['proposition'] ?? '', 500));
                if ($text === '') {
                    $flash = 'Votre proposition ne peut pas être vide.';
                    $flash_type = 'err';
                } elseif ($user_prop) {
                    $flash = 'Vous avez déjà soumis une proposition pour cet épisode.';
                    $flash_type = 'err';
                } else {
                    $pdo->prepare("INSERT INTO ktc_propositions (episode_id, user_id, proposition) VALUES (:eid,:uid,:prop)")
                        ->execute([':eid' => $episode['id'], ':uid' => $user_id, ':prop' => $text]);
                    $s2 = $pdo->prepare("SELECT * FROM ktc_propositions WHERE episode_id=:eid AND user_id=:uid LIMIT 1");
                    $s2->execute([':eid' => $episode['id'], ':uid' => $user_id]);
                    $user_prop = $s2->fetch() ?: null;
                    $flash = 'Votre proposition a été enregistrée !';
                }
            } elseif ($action === 'submit_vote' && $current_week === 3) {
                $choice = trim($_POST['vote_choice'] ?? '');
                $valid_choices = array_values(array_filter([
                    $episode['vote_choice_1'] ?? '', $episode['vote_choice_2'] ?? '',
                    $episode['vote_choice_3'] ?? '', $episode['vote_choice_4'] ?? '',
                ]));
                if (empty($choice) || !in_array($choice, $valid_choices, true)) {
                    $flash = 'Choisissez une proposition valide.';
                    $flash_type = 'err';
                } elseif ($user_vote) {
                    $flash = 'Vous avez déjà voté pour cet épisode.';
                    $flash_type = 'err';
                } else {
                    $pdo->prepare("INSERT INTO ktc_votes (episode_id, user_id, vote_choice) VALUES (:eid,:uid,:ch)")
                        ->execute([':eid' => $episode['id'], ':uid' => $user_id, ':ch' => $choice]);
                    $user_vote = ['vote_choice' => $choice];
                    $sv = $pdo->prepare("SELECT vote_choice, COUNT(*) AS cnt FROM ktc_votes WHERE episode_id=:eid GROUP BY vote_choice");
                    $sv->execute([':eid' => $episode['id']]);
                    $vote_counts = [];
                    while ($row = $sv->fetch()) {
                        $vote_counts[$row['vote_choice']] = (int)$row['cnt'];
                    }
                    $vote_total = array_sum($vote_counts);
                    $flash = 'Vote enregistré. Rendez-vous à la révélation !';
                }
            }
        } catch (Exception $e) {
            $flash = 'Une erreur est survenue. Réessayez.';
            $flash_type = 'err';
        }
    }
}

// ── Méta SEO ──────────────────────────────────────────────────
$phase_label_seo = [
    'week1'    => 'Semaine 1 — Découverte',
    'week2'    => 'Semaine 2 — Indices',
    'week3'    => 'Semaine 3 — Votes',
    'revealed' => 'Révélation !',
];
if ($episode) {
    $page_title       = 'KTC — ' . ($phase_label_seo[$episode['status']] ?? 'Kéto Kolé Tché');
    $page_description = strip_tags(substr($episode['teaser_text'] ?? 'Chaque mois, un objet mystérieux vendéen. Kéto Kolé Tché !', 0, 160));
    $page_og_image    = !empty($photos) ? $photos[0]['file_path'] : 'assets/img/ZONE852025.png';
} else {
    $page_title       = 'Kéto Kolé Tché — Zone85';
    $page_description = 'Chaque mois, un objet mystérieux vendéen et la rencontre avec un passionné local. Kéto Kolé Tché !';
    $page_og_image    = 'assets/img/ZONE852025.png';
}
$page_canonical = function_exists('absolute_url') ? absolute_url('ktc.php') : 'https://www.zone85.fr/ktc.php';
$page_robots    = 'index,follow';
$current_page   = 'ktc';

// ── Styles ────────────────────────────────────────────────────
$page_styles = '<style>
/* ============================================================
   KTC V12 — Mobile-first, éditorial
============================================================ */
/* KTC hero — fond noir spécifique (identité mystère), padding depuis zone85.css */
.ktc-hero {
  background:
    radial-gradient(circle at 80% 15%, rgba(50,10,10,.55), transparent 35%),
    radial-gradient(circle at 12% 88%, rgba(18,49,78,.6), transparent 38%),
    linear-gradient(160deg, #0a0a0a 0%, #150800 45%, #0d0508 100%);
}
.ktc-hero::before {
  content: "🥐";
  position: absolute; right: 5%; top: 50%;
  transform: translateY(-50%);
  font-size: 12rem; opacity: .05;
  pointer-events: none; line-height: 1; user-select: none;
}
.ktc-hero-inner { position: relative; z-index: 1; }
.ktc-phase-badge {
  display: inline-block;
  font-size: .68rem; font-weight: 900;
  letter-spacing: .14em; text-transform: uppercase;
  padding: 5px 14px; border-radius: 4px; margin-bottom: 16px;
}
.ktc-hero h1 {
  font-size: clamp(1.7rem, 4vw, 2.6rem); font-weight: 900;
  color: #fff; letter-spacing: -1px; line-height: 1.1; margin-bottom: 12px;
}
.ktc-hero-sub { font-size: .95rem; color: rgba(255,255,255,.48); max-width: 480px; line-height: 1.7; }
.ktc-hero-title-ep { font-size: .82rem; color: rgba(201,150,42,.75); font-weight: 700; margin-top: 8px; }

.ktc-section { background: #f5efe6; padding: 56px 0 96px; }

.ktc-layout { display: grid; grid-template-columns: 1fr; gap: 28px; }
@media (min-width: 900px) {
  .ktc-layout { grid-template-columns: 1fr 300px; gap: 36px; align-items: start; }
}

.ktc-flash {
  border-radius: 10px; padding: 12px 16px; margin-bottom: 20px;
  font-size: .88rem; font-weight: 600;
}
.ktc-flash-ok  { background: rgba(42,140,64,.1);  border: 1px solid rgba(42,140,64,.25);  color: #1a5c28; }
.ktc-flash-err { background: rgba(201,64,48,.08); border: 1px solid rgba(201,64,48,.22);  color: #8a1a10; }
/* Badge "déjà participé" KTC */
.ktc-done-badge{display:flex;align-items:center;gap:12px;background:rgba(42,140,64,.1);border:1.5px solid rgba(42,140,64,.25);border-radius:12px;padding:13px 16px;margin-bottom:14px}
.ktc-done-badge-icon{font-size:1.4rem;flex-shrink:0}
.ktc-done-badge-text{flex:1}
.ktc-done-badge-label{font-size:.78rem;font-weight:900;color:#1a5c28;text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px}
.ktc-done-badge-value{font-size:.88rem;color:#1a5c28;font-weight:700;line-height:1.4}

/* Photos */
.ktc-photos { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 28px; }
@media (min-width: 600px) { .ktc-photos { grid-template-columns: 1fr 1fr; } }
@media (min-width: 900px) { .ktc-photos { grid-template-columns: repeat(3, 1fr); } }
.ktc-photo { border-radius: 12px; overflow: hidden; aspect-ratio: 4/3; cursor: zoom-in; }
.ktc-photo img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.ktc-photo:hover img { transform: scale(1.04); }
.ktc-photo-caption { font-size: .72rem; color: #6b7f96; margin-top: 5px; text-align: center; }

/* Blocs texte */
.ktc-teaser-block {
  background: #fff; border-radius: 16px;
  border: 1px solid rgba(139,90,43,.1);
  padding: 28px; margin-bottom: 20px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.ktc-section-kicker {
  font-size: .66rem; font-weight: 800; letter-spacing: .14em;
  text-transform: uppercase; color: #8a4020; margin-bottom: 12px;
}
.ktc-teaser-text { font-size: 1rem; color: #0c1e2e; line-height: 1.75; white-space: pre-wrap; }

.ktc-details-block {
  background: linear-gradient(135deg, #fff8f0, #fdf5e8);
  border: 1px solid rgba(201,150,42,.22); border-radius: 14px;
  padding: 22px 24px; margin-bottom: 20px;
}
.ktc-details-block .ktc-section-kicker { color: #7a5010; }
.ktc-details-text { font-size: .93rem; color: #3d3020; line-height: 1.7; white-space: pre-wrap; }

/* Révélation */
.ktc-revelation-object {
  background: linear-gradient(135deg, #12314e, #1a4a24);
  border-radius: 16px; padding: 28px; margin-bottom: 20px; text-align: center;
}
.ktc-object-label {
  font-size: .7rem; font-weight: 800; letter-spacing: .16em;
  text-transform: uppercase; color: rgba(201,150,42,.85); margin-bottom: 10px;
}
.ktc-object-name {
  font-size: clamp(1.4rem, 3.5vw, 2.2rem); font-weight: 900; color: #fff; line-height: 1.2;
}
.ktc-revelation-text-block {
  background: #fff; border-radius: 14px; padding: 28px;
  border: 1px solid rgba(139,90,43,.1); margin-bottom: 20px;
}
.ktc-revelation-text { font-size: .97rem; color: #0c1e2e; line-height: 1.8; }

/* Formulaire proposition */
.ktc-prop-form {
  background: #fff; border-radius: 14px; padding: 24px;
  border: 1px solid rgba(18,49,78,.1);
  box-shadow: 0 2px 10px rgba(0,0,0,.04); margin-bottom: 20px;
}
.ktc-prop-form-title { font-size: .92rem; font-weight: 800; color: #0c1e2e; margin-bottom: 6px; }
.ktc-prop-form-hint { font-size: .78rem; color: #6b7f96; margin-bottom: 14px; line-height: 1.5; }
.ktc-prop-existing {
  background: rgba(42,140,64,.07); border: 1px solid rgba(42,140,64,.2);
  border-radius: 10px; padding: 14px 18px; font-size: .9rem;
  color: #1a5c28; font-weight: 600;
}
.ktc-prop-existing-label { color: #6b7f96; font-weight: 400; font-size: .76rem; display: block; margin-bottom: 5px; }
.ktc-prop-textarea {
  width: 100%; min-height: 80px; padding: 12px 14px;
  border: 1.5px solid rgba(18,49,78,.14); border-radius: 10px;
  font-family: inherit; font-size: .9rem; color: #0c1e2e;
  resize: vertical; box-sizing: border-box; line-height: 1.5; background: #f8f4ef;
}
.ktc-prop-textarea:focus { outline: none; border-color: #c9962a; background: #fff; }

/* Formulaire vote */
.ktc-vote-form {
  background: #fff; border-radius: 14px; padding: 24px;
  border: 1px solid rgba(18,49,78,.1);
  box-shadow: 0 2px 10px rgba(0,0,0,.04); margin-bottom: 20px;
}
.ktc-vote-question {
  font-size: 1rem; font-weight: 800; color: #0c1e2e; margin-bottom: 18px; line-height: 1.4;
}
.ktc-vote-options { display: grid; gap: 10px; margin-bottom: 18px; }
.ktc-vote-option {
  display: flex; align-items: center; gap: 12px;
  background: #f8f4ef; border: 2px solid rgba(139,90,43,.12);
  border-radius: 12px; padding: 14px 16px;
  cursor: pointer; font-size: .9rem; font-weight: 600; color: #0c1e2e;
  transition: border-color .18s, background .18s;
}
.ktc-vote-option:hover { border-color: rgba(201,150,42,.5); background: rgba(201,150,42,.07); }
.ktc-vote-option input[type="radio"] { accent-color: #c9962a; width: 18px; height: 18px; flex-shrink: 0; }
.ktc-vote-already {
  background: rgba(42,140,64,.07); border: 1px solid rgba(42,140,64,.22);
  border-radius: 10px; padding: 14px 18px; font-size: .88rem;
  color: #1a5c28; font-weight: 600;
}

/* Résultats vote */
.ktc-vote-results { margin-top: 20px; }
.ktc-vote-results-title {
  font-size: .66rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
  color: #6b7f96; margin-bottom: 14px; padding-bottom: 8px;
  border-bottom: 1px solid rgba(18,49,78,.1);
}
.ktc-vote-bar-item { margin-bottom: 12px; }
.ktc-vote-bar-label {
  display: flex; justify-content: space-between; align-items: center;
  font-size: .84rem; font-weight: 600; color: #0c1e2e; margin-bottom: 5px;
}
.ktc-vote-bar-pct { font-size: .74rem; color: #6b7f96; }
.ktc-vote-bar-track {
  height: 8px; background: rgba(18,49,78,.08); border-radius: 999px; overflow: hidden;
}
.ktc-vote-bar-fill {
  height: 100%; border-radius: 999px;
  background: linear-gradient(90deg, #c9962a, #ea5649);
}
.ktc-vote-bar-fill.winning { background: linear-gradient(90deg, #2a8c40, #1a6c2c); }

/* Carte brocanteur */
.ktc-person-card {
  background: #fff; border-radius: 16px; overflow: hidden;
  border: 1px solid rgba(139,90,43,.12);
  box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 16px;
}
.ktc-person-header {
  background: linear-gradient(135deg, #12314e, #1a3a1a);
  padding: 18px 20px 14px;
}
.ktc-person-name { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 2px; }
.ktc-person-subtitle { font-size: .78rem; color: rgba(201,150,42,.85); font-weight: 600; }
.ktc-person-body { padding: 16px 20px; }
.ktc-person-photo {
  width: 68px; height: 68px; border-radius: 50%; object-fit: cover;
  border: 3px solid #e8e2db; float: right; margin: 0 0 10px 12px;
}
.ktc-person-bio { font-size: .84rem; color: #3d5166; line-height: 1.6; }

/* Sidebar infos */
.ktc-sidebar-card {
  background: #fff; border-radius: 14px;
  border: 1px solid rgba(18,49,78,.1);
  padding: 18px 20px; margin-bottom: 16px;
}
.ktc-sidebar-title {
  font-size: .65rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
  color: #6b7f96; margin-bottom: 12px; padding-bottom: 8px;
  border-bottom: 1px solid rgba(18,49,78,.08);
}
.ktc-date-row {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 0; border-bottom: 1px solid rgba(18,49,78,.06);
  font-size: .82rem;
}
.ktc-date-row:last-child { border-bottom: none; padding-bottom: 0; }
.ktc-date-dot {
  width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
  background: rgba(18,49,78,.15);
}
.ktc-date-dot.active { background: #ea5649; box-shadow: 0 0 0 3px rgba(234,86,73,.2); }
.ktc-date-dot.done { background: #2a8c40; }
.ktc-date-label { font-weight: 600; color: #0c1e2e; flex: 1; }
.ktc-date-val { font-size: .74rem; color: #6b7f96; }

/* Épisodes passés */
.ktc-past-section { margin-top: 48px; }
.ktc-past-title {
  font-size: .68rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
  color: #1a2d3e; margin-bottom: 20px; padding-bottom: 10px;
  border-bottom: 1px solid rgba(18,49,78,.12);
}
.ktc-past-grid { display: grid; gap: 14px; }
@media (min-width: 600px) { .ktc-past-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 900px) { .ktc-past-grid { grid-template-columns: repeat(3, 1fr); } }
.ktc-past-card {
  background: #fff; border-radius: 12px;
  border: 1px solid rgba(139,90,43,.1);
  padding: 18px 16px; display: block; text-decoration: none;
  transition: transform .18s, box-shadow .18s;
}
.ktc-past-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(139,90,43,.12); }
.ktc-past-card-kicker { font-size: .64rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #9a6800; margin-bottom: 6px; }
.ktc-past-card-title { font-size: .88rem; font-weight: 800; color: #0c1e2e; line-height: 1.35; margin-bottom: 5px; }
.ktc-past-card-object { font-size: .8rem; color: #3d5166; }

/* Bouton submit */
.ktc-btn-submit {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: linear-gradient(135deg, #7a5010, #c9962a);
  color: #fff; font-size: .9rem; font-weight: 800;
  padding: 13px 22px; border-radius: 10px;
  border: none; cursor: pointer; font-family: inherit;
  transition: opacity .18s; width: 100%; margin-top: 14px;
}
.ktc-btn-submit:hover { opacity: .88; }

/* Login CTA */
.ktc-login-cta {
  background: rgba(18,49,78,.05); border: 1px solid rgba(18,49,78,.12);
  border-radius: 12px; padding: 14px 18px;
  font-size: .87rem; color: #4a5f73;
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.ktc-login-cta a { font-weight: 800; color: #0c1e2e; }

/* Empty state */
.ktc-empty {
  background: #fff; border-radius: 16px;
  border: 1px dashed rgba(139,90,43,.2);
  padding: 56px 24px; text-align: center; margin-bottom: 32px;
}
.ktc-empty-icon { font-size: 3rem; margin-bottom: 14px; }
.ktc-empty h3 { font-size: 1rem; font-weight: 800; color: #0c1e2e; margin-bottom: 6px; }
.ktc-empty p { font-size: .84rem; color: #6b7f96; }

@media (max-width: 599px) {
  .ktc-hero { padding: 80px 0 48px; }
  .ktc-hero::before { display: none; }
  .ktc-teaser-block, .ktc-prop-form, .ktc-vote-form { padding: 18px; }
}
</style>';

require 'includes/header.php';
require 'includes/nav.php';

// ── Helpers locaux ────────────────────────────────────────────
function _ktc_phase_badge(string $status): string {
    $d = [
        'week1'    => ['Semaine 1 — Découverte', '#1d4ed8', 'rgba(147,197,253,.2)'],
        'week2'    => ['Semaine 2 — Indices',    '#1e40af', 'rgba(96,165,250,.18)'],
        'week3'    => ['Semaine 3 — Votes',      '#c2410c', 'rgba(251,146,60,.18)'],
        'revealed' => ['✨ Révélation !',         '#1a7a42', 'rgba(42,157,92,.14)'],
    ];
    $b = $d[$status] ?? null;
    if (!$b) return '';
    return '<span class="ktc-phase-badge" style="background:' . $b[2] . ';color:' . $b[1] . '">'
         . htmlspecialchars($b[0], ENT_QUOTES, 'UTF-8') . '</span>';
}

function _ktc_fmt_date(?string $d): string {
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if (!$dt) return htmlspecialchars($d, ENT_QUOTES, 'UTF-8');
    return $dt->format('d/m/Y');
}
?>

<!-- ══════════════════════════════════════════════
     HERO
══════════════════════════════════════════════ -->
<section class="ktc-hero">
  <div class="container">
    <div class="ktc-hero-inner">
      <?php if ($episode): ?>
        <?= _ktc_phase_badge($episode['status']) ?>
        <h1>🥐 Kéto Kolé Tché !</h1>
        <p class="ktc-hero-sub"><?= e($episode['title']) ?></p>
        <?php if (!empty($episode['person_name'])): ?>
          <p class="ktc-hero-title-ep">
            Rencontre avec <?= e($episode['person_name']) ?>
            <?php if (!empty($episode['person_title'])): ?>— <?= e($episode['person_title']) ?><?php endif; ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <div class="ktc-phase-badge" style="background:rgba(220,30,30,.22);color:#e84040">CHAQUE MOIS</div>
        <h1>🥐 Kéto Kolé Tché !</h1>
        <p class="ktc-hero-sub">Chaque mois, un objet mystérieux vendéen et la rencontre avec un passionné local. C'est quoi cet objet ?</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════
     CONTENU
══════════════════════════════════════════════ -->
<section class="ktc-section">
  <div class="container">

    <?php if ($flash): ?>
      <div class="ktc-flash ktc-flash-<?= $flash_type === 'err' ? 'err' : 'ok' ?>">
        <?= e($flash) ?>
      </div>
    <?php endif; ?>

    <?php if ($episode): ?>
    <div class="ktc-layout">

      <!-- ── COLONNE PRINCIPALE ─────────────────────── -->
      <div>

        <?php // ── Semaine 1 : Découverte ── ?>
        <?php if (in_array($episode['status'], ['week1','week2','week3','revealed'], true) && !empty($episode['teaser_text'])): ?>
          <div class="ktc-teaser-block">
            <div class="ktc-section-kicker">Découverte</div>
            <div class="ktc-teaser-text"><?= e($episode['teaser_text']) ?></div>
          </div>
        <?php endif; ?>

        <?php // ── Semaine 2+ : Indices supplémentaires ── ?>
        <?php if ($current_week >= 2 && !empty($episode['details_text'])): ?>
          <div class="ktc-details-block">
            <div class="ktc-section-kicker">Indices supplémentaires</div>
            <div class="ktc-details-text"><?= e($episode['details_text']) ?></div>
          </div>
        <?php endif; ?>

        <?php // ── Révélation : objet + histoire ── ?>
        <?php if ($current_week >= 4): ?>
          <?php $show_name = !empty($episode['object_name']); ?>
          <?php if ($show_name): ?>
            <div class="ktc-revelation-object">
              <div class="ktc-object-label">L'objet mystérieux était…</div>
              <div class="ktc-object-name"><?= e($episode['object_name']) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($winner_data): ?>
          <?php
            $conf_labels = [
              'certain'    => ['Réponse certaine', '#1a5c28', 'rgba(42,140,64,.12)'],
              'probable'   => ['Réponse probable',  '#7a5010', 'rgba(201,150,42,.15)'],
              'estimation' => ['Estimation',         '#4a1d96', 'rgba(109,40,217,.1)'],
            ];
            [$conf_label, $conf_color, $conf_bg] = $conf_labels[$episode['answer_confidence'] ?? 'certain'] ?? $conf_labels['certain'];
          ?>
          <div style="background:#fff;border-radius:16px;border:2px solid #2a8c40;padding:22px 24px;margin-bottom:20px">
            <div style="font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#1a5c28;margin-bottom:12px">Meilleure réponse des Zonautes</div>
            <div style="display:flex;align-items:flex-start;gap:14px">
              <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:.82rem;flex-shrink:0">
                <?= strtoupper(mb_substr($winner_data['pseudo'], 0, 2)) ?>
              </div>
              <div style="flex:1">
                <div style="font-size:.78rem;font-weight:800;color:#9a6800;margin-bottom:4px"><?= e($winner_data['pseudo']) ?></div>
                <div style="font-size:.95rem;color:#0c1e2e;line-height:1.5;font-style:italic">"<?= e($winner_data['proposition']) ?>"</div>
              </div>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:800;background:<?= $conf_bg ?>;color:<?= $conf_color ?>"><?= $conf_label ?></span>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($episode['revelation_text'])): ?>
            <div class="ktc-revelation-text-block">
              <div class="ktc-section-kicker">L'histoire complète</div>
              <div class="ktc-revelation-text"><?= nl2br(e($episode['revelation_text'])) ?></div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php // ── Photos ── ?>
        <?php if (!empty($photos)): ?>
          <div class="ktc-photos">
            <?php foreach ($photos as $ph): ?>
              <div>
                <div class="ktc-photo" data-lightbox="1">
                  <img src="<?= e(media_url($ph['file_path'])) ?>"
                       alt="<?= e($ph['caption'] ?? 'Photo KTC') ?>"
                       onerror="this.closest('.ktc-photo').style.display='none'">
                </div>
                <?php if (!empty($ph['caption'])): ?>
                  <div class="ktc-photo-caption"><?= e($ph['caption']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php // ── Formulaire proposition (semaines 1 & 2) ── ?>
        <?php if (in_array($current_week, [1, 2], true)): ?>
          <div class="ktc-prop-form">
            <div class="ktc-prop-form-title">💬 Quelle est votre théorie ?</div>
            <div class="ktc-prop-form-hint">Selon vous, c'est quoi cet objet ? Proposez votre réponse — elle sera dévoilée à la révélation.</div>
            <?php if ($user_prop): ?>
              <div class="ktc-done-badge">
                <div class="ktc-done-badge-icon">&#x1F4AC;</div>
                <div class="ktc-done-badge-text">
                  <div class="ktc-done-badge-label">&#x2713; Proposition envoyée</div>
                  <div class="ktc-done-badge-value"><?= e($user_prop['proposition']) ?></div>
                </div>
              </div>
            <?php elseif ($is_logged): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_proposition">
                <textarea name="proposition" class="ktc-prop-textarea"
                          maxlength="500"
                          placeholder="Décrivez votre théorie sur cet objet mystérieux…" required></textarea>
                <button type="submit" class="ktc-btn-submit">&#x1F4AC; Soumettre ma proposition</button>
              </form>
            <?php else: ?>
              <div class="ktc-login-cta">
                🔒 <span><a href="login.php">Connectez-vous</a> pour soumettre votre théorie et participer au mystère.</span>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php // ── Formulaire vote (semaine 3) ── ?>
        <?php if ($current_week === 3): ?>
          <?php $vote_choices = array_filter([
              $episode['vote_choice_1'] ?? '', $episode['vote_choice_2'] ?? '',
              $episode['vote_choice_3'] ?? '', $episode['vote_choice_4'] ?? '',
          ]); ?>
          <?php if (!empty($vote_choices)): ?>
          <div class="ktc-vote-form">
            <div class="ktc-vote-question">
              <?= e($episode['vote_question'] ?: 'Selon vous, cet objet est…') ?>
            </div>
            <?php if ($user_vote): ?>
              <div class="ktc-done-badge">
                <div class="ktc-done-badge-icon">&#x1F5F3;&#xFE0F;</div>
                <div class="ktc-done-badge-text">
                  <div class="ktc-done-badge-label">&#x2713; Vous avez voté</div>
                  <div class="ktc-done-badge-value"><?= e($user_vote['vote_choice']) ?> — Rendez-vous à la révélation !</div>
                </div>
              </div>
            <?php elseif ($is_logged): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_vote">
                <div class="ktc-vote-options">
                  <?php foreach ($vote_choices as $ch): ?>
                    <label class="ktc-vote-option">
                      <input type="radio" name="vote_choice" value="<?= e($ch) ?>" required>
                      <?= e($ch) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="ktc-btn-submit">🗳️ Valider mon vote</button>
              </form>
            <?php else: ?>
              <div class="ktc-login-cta">
                🔒 <span><a href="login.php">Connectez-vous</a> pour voter et participer à la révélation.</span>
              </div>
            <?php endif; ?>

            <?php // Résultats provisoires si déjà voté ?>
            <?php if ($user_vote && $vote_total > 0): ?>
              <div class="ktc-vote-results">
                <div class="ktc-vote-results-title">Résultats provisoires — <?= $vote_total ?> vote<?= $vote_total > 1 ? 's' : '' ?></div>
                <?php foreach ($vote_choices as $ch): ?>
                  <?php $cnt = $vote_counts[$ch] ?? 0; $pct = $vote_total > 0 ? round($cnt / $vote_total * 100) : 0; ?>
                  <div class="ktc-vote-bar-item">
                    <div class="ktc-vote-bar-label">
                      <?= e($ch) ?><span class="ktc-vote-bar-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="ktc-vote-bar-track">
                      <div class="ktc-vote-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php // ── Résultats finaux à la révélation ── ?>
        <?php if ($current_week >= 4 && $vote_total > 0): ?>
          <?php $correct_answer = $episode['object_name'] ?? ''; ?>
          <div class="ktc-vote-form">
            <div class="ktc-vote-results-title" style="margin:0 0 16px">
              🗳️ Résultats du vote — <?= $vote_total ?> vote<?= $vote_total > 1 ? 's' : '' ?>
            </div>
            <?php
              $vote_choices_rev = array_filter([
                  $episode['vote_choice_1'] ?? '', $episode['vote_choice_2'] ?? '',
                  $episode['vote_choice_3'] ?? '', $episode['vote_choice_4'] ?? '',
              ]);
              arsort($vote_counts);
            ?>
            <?php foreach ($vote_choices_rev as $ch): ?>
              <?php $cnt = $vote_counts[$ch] ?? 0; $pct = $vote_total > 0 ? round($cnt / $vote_total * 100) : 0; ?>
              <?php $is_win = $correct_answer !== '' && strcasecmp(trim($ch), trim($correct_answer)) === 0; ?>
              <div class="ktc-vote-bar-item">
                <div class="ktc-vote-bar-label">
                  <?= e($ch) ?><?= $is_win ? ' ✓' : '' ?>
                  <span class="ktc-vote-bar-pct"><?= $pct ?>% (<?= $cnt ?>)</span>
                </div>
                <div class="ktc-vote-bar-track">
                  <div class="ktc-vote-bar-fill<?= $is_win ? ' winning' : '' ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div><!-- /main -->

      <!-- ── SIDEBAR ─────────────────────────────────── -->
      <aside>

        <?php // Carte brocanteur ?>
        <?php if (!empty($episode['person_name'])): ?>
          <div class="ktc-person-card">
            <div class="ktc-person-header">
              <div class="ktc-person-name"><?= e($episode['person_name']) ?></div>
              <?php if (!empty($episode['person_title'])): ?>
                <div class="ktc-person-subtitle"><?= e($episode['person_title']) ?></div>
              <?php endif; ?>
            </div>
            <div class="ktc-person-body">
              <?php if (!empty($episode['person_photo'])): ?>
                <img src="<?= e($episode['person_photo']) ?>" alt="<?= e($episode['person_name']) ?>"
                     class="ktc-person-photo" onerror="this.style.display='none'">
              <?php endif; ?>
              <?php if (!empty($episode['person_bio'])): ?>
                <div class="ktc-person-bio"><?= nl2br(e($episode['person_bio'])) ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php // Calendrier des phases ?>
        <?php if (!empty($episode['date_week1']) || !empty($episode['date_week2']) || !empty($episode['date_week3']) || !empty($episode['date_revelation'])): ?>
          <div class="ktc-sidebar-card">
            <div class="ktc-sidebar-title">Calendrier</div>
            <?php $phases = [
              ['label' => 'Sem. 1 — Découverte',       'date' => $episode['date_week1']    ?? '', 'week' => 1],
              ['label' => 'Sem. 2 — Indices',           'date' => $episode['date_week2']    ?? '', 'week' => 2],
              ['label' => 'Sem. 3 — Votes',             'date' => $episode['date_week3']    ?? '', 'week' => 3],
              ['label' => 'Sem. 4 — Révélation',        'date' => $episode['date_revelation'] ?? '', 'week' => 4],
            ]; ?>
            <?php foreach ($phases as $ph): ?>
              <?php
                $dot_class = '';
                if ($ph['week'] < $current_week)       $dot_class = 'done';
                elseif ($ph['week'] === $current_week) $dot_class = 'active';
              ?>
              <div class="ktc-date-row">
                <div class="ktc-date-dot <?= $dot_class ?>"></div>
                <div class="ktc-date-label"><?= e($ph['label']) ?></div>
                <div class="ktc-date-val"><?= _ktc_fmt_date($ph['date']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php // XP info ?>
        <?php if (!empty($episode['xp_reward'])): ?>
          <div class="ktc-sidebar-card" style="text-align:center">
            <div style="font-size:1.6rem;font-weight:900;color:#c9962a">+<?= (int)$episode['xp_reward'] ?> XP</div>
            <div style="font-size:.74rem;color:#6b7f96;margin-top:4px">à la révélation pour les participants</div>
          </div>
        <?php endif; ?>

      </aside>

    </div><!-- /ktc-layout -->

    <?php else: ?>
    <!-- Empty state -->
    <div class="ktc-empty">
      <div class="ktc-empty-icon">🥐</div>
      <h3>Aucun épisode en cours</h3>
      <p>Le prochain mystère vendéen arrive bientôt. Revenez dans la Zone !</p>
    </div>
    <?php endif; ?>

    <?php // ── Épisodes passés ── ?>
    <?php if (!empty($past_eps)): ?>
      <div class="ktc-past-section">
        <div class="ktc-past-title">Épisodes précédents</div>
        <div class="ktc-past-grid">
          <?php foreach ($past_eps as $ep): ?>
            <a href="ktc.php" class="ktc-past-card">
              <div class="ktc-past-card-kicker">
                <?= $ep['date_revelation'] ? _ktc_fmt_date($ep['date_revelation']) : 'Révélé' ?>
              </div>
              <div class="ktc-past-card-title"><?= e($ep['title']) ?></div>
              <?php if (!empty($ep['object_name']) && !(int)$ep['object_hidden']): ?>
                <div class="ktc-past-card-object">🔍 <?= e($ep['object_name']) ?></div>
              <?php else: ?>
                <div class="ktc-past-card-object" style="color:#bbb">Objet mystérieux révélé</div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php require 'includes/footer.php'; ?>
