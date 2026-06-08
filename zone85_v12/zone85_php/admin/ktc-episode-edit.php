<?php
// ============================================================
// ZONE85 — Admin : KTC Editorial — Editeur episode
// ============================================================
$admin_current    = 'ktc-episodes';
$admin_page_title = 'KTC — Editeur episode';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo   = db();
$flash = '';
$flash_type = 'ok';

// ── Chargement episode existant ───────────────────────────────
$id      = (int)($_GET['id'] ?? 0);
$episode = null;
if ($id > 0 && $pdo) {
    try {
        $s = $pdo->prepare('SELECT * FROM ktc_episodes WHERE id = :id LIMIT 1');
        $s->execute([':id' => $id]);
        $episode = $s->fetch();
    } catch (PDOException $e) {}
}

$is_edit = $episode !== null;
$admin_page_title = $is_edit
    ? 'KTC — ' . ($episode['title'] ?? 'Episode')
    : 'KTC — Nouvel episode';

// Flash "sauvegarde"
if (!empty($_GET['saved'])) {
    $flash = 'Episode enregistre avec succes.';
    $flash_type = 'ok';
}

// ── Saisons disponibles ───────────────────────────────────────
$seasons_list = [];
if ($pdo) {
    try {
        $s = $pdo->query("SELECT id, title, status FROM seasons WHERE status IN ('active','upcoming') ORDER BY id DESC");
        $seasons_list = $s->fetchAll();
    } catch (PDOException $e) {}
}

// ── Badges disponibles ────────────────────────────────────────
$badges_list = [];
if ($pdo) {
    try {
        $s = $pdo->query("SELECT id, name FROM badges ORDER BY name ASC");
        $badges_list = $s->fetchAll();
    } catch (PDOException $e) {}
}

// ── Photos de l'episode ───────────────────────────────────────
$photos = [];
if ($is_edit && $pdo) {
    try {
        $s = $pdo->prepare('SELECT * FROM ktc_episode_photos WHERE episode_id = :eid ORDER BY sort_order ASC, id ASC');
        $s->execute([':eid' => $episode['id']]);
        $photos = $s->fetchAll();
    } catch (PDOException $e) {}
}

// Propositions des membres (pour sélection du gagnant)
$all_propositions = [];
if ($is_edit && $pdo) {
    try {
        $sq = $pdo->prepare("
            SELECT p.id, p.proposition, p.is_correct, p.submitted_at, u.pseudo
            FROM ktc_propositions p
            JOIN users u ON u.id = p.user_id
            WHERE p.episode_id = :eid
            ORDER BY p.submitted_at ASC
        ");
        $sq->execute([':eid' => $episode['id']]);
        $all_propositions = $sq->fetchAll();
    } catch (PDOException $e) {}
}

// ── Traitement POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash = 'Jeton CSRF invalide. Formulaire rejete.';
        $flash_type = 'err';
    } else {
        $action = $_POST['action'] ?? 'save_episode';

        // ── Action : save_episode ──────────────────────────────
        if ($action === 'save_episode') {
            $f_title         = safe_input($_POST['title']          ?? '', 255);
            $f_slug          = safe_input($_POST['slug']           ?? '', 255);
            $f_season_id     = !empty($_POST['season_id'])    ? (int)$_POST['season_id']    : null;
            $f_badge_id      = !empty($_POST['badge_reward_id']) ? (int)$_POST['badge_reward_id'] : null;
            $f_xp            = max(0, (int)($_POST['xp_reward']    ?? 50));
            $f_status        = $_POST['status'] ?? 'draft';
            $valid_statuses  = ['draft','week1','week2','week3','revealed','archived'];
            if (!in_array($f_status, $valid_statuses, true)) $f_status = 'draft';

            $f_object_name   = safe_input($_POST['object_name']    ?? '', 255);
            $f_object_hidden = isset($_POST['object_hidden']) ? 1 : 0;
            $f_teaser_text   = $_POST['teaser_text']    ?? '';
            $f_details_text  = $_POST['details_text']   ?? '';
            $f_vote_question = safe_input($_POST['vote_question'] ?? '', 500);
            $f_vote_1        = safe_input($_POST['vote_choice_1'] ?? '', 255);
            $f_vote_2        = safe_input($_POST['vote_choice_2'] ?? '', 255);
            $f_vote_3        = safe_input($_POST['vote_choice_3'] ?? '', 255);
            $f_vote_4        = safe_input($_POST['vote_choice_4'] ?? '', 255);
            $f_revelation    = $_POST['revelation_text'] ?? '';
            $f_person_name   = safe_input($_POST['person_name']   ?? '', 150);
            $f_person_title  = safe_input($_POST['person_title']  ?? '', 200);
            $f_person_bio    = $_POST['person_bio']    ?? '';
            $f_person_photo  = safe_input($_POST['person_photo']  ?? '', 255);
            $f_date_week1    = $_POST['date_week1']    ?? '';
            $f_date_week2    = $_POST['date_week2']    ?? '';
            $f_date_week3    = $_POST['date_week3']    ?? '';
            $f_date_revel    = $_POST['date_revelation'] ?? '';

            // Nettoyer le slug
            $f_slug = strtolower($f_slug);
            $f_slug = preg_replace('/[^a-z0-9\-]/', '', $f_slug);
            $f_slug = trim($f_slug, '-');
            if (empty($f_slug)) {
                $f_slug = 'episode-' . time();
            }

            // Upload photo personne (facultatif)
            if (!empty($_FILES['person_photo_file']['tmp_name'])) {
                $up_person = upload_editorial_image($_FILES['person_photo_file'], 'ktc');
                if ($up_person['ok']) $f_person_photo = $up_person['path'];
            }
            // Winner + confidence
            $f_winner_prop_id = !empty($_POST['winner_proposition_id']) ? (int)$_POST['winner_proposition_id'] : null;
            $f_confidence     = in_array($_POST['answer_confidence'] ?? '', ['certain','probable','estimation'], true)
                                ? $_POST['answer_confidence'] : 'certain';

            // Dates — validation simple
            $clean_date = function(string $d): ?string {
                if (empty($d)) return null;
                $dt = DateTime::createFromFormat('Y-m-d', $d);
                return $dt ? $dt->format('Y-m-d') : null;
            };

            if (empty($f_title)) {
                $flash = 'Le titre est obligatoire.';
                $flash_type = 'err';
            } else {
                try {
                    if ($is_edit) {
                        $u = $pdo->prepare('
                            UPDATE ktc_episodes SET
                                slug            = :slug,
                                title           = :title,
                                season_id       = :season_id,
                                object_name     = :object_name,
                                object_hidden   = :object_hidden,
                                teaser_text     = :teaser_text,
                                details_text    = :details_text,
                                vote_question   = :vote_question,
                                vote_choice_1   = :vote_choice_1,
                                vote_choice_2   = :vote_choice_2,
                                vote_choice_3   = :vote_choice_3,
                                vote_choice_4   = :vote_choice_4,
                                revelation_text       = :revelation_text,
                                winner_proposition_id = :winner_proposition_id,
                                answer_confidence     = :answer_confidence,
                                person_name     = :person_name,
                                person_title    = :person_title,
                                person_bio      = :person_bio,
                                person_photo    = :person_photo,
                                date_week1      = :date_week1,
                                date_week2      = :date_week2,
                                date_week3      = :date_week3,
                                date_revelation = :date_revelation,
                                badge_reward_id = :badge_reward_id,
                                xp_reward       = :xp_reward,
                                status          = :status
                            WHERE id = :id
                        ');
                        $u->execute([
                            ':slug'            => $f_slug,
                            ':title'           => $f_title,
                            ':season_id'       => $f_season_id,
                            ':object_name'     => $f_object_name ?: null,
                            ':object_hidden'   => $f_object_hidden,
                            ':teaser_text'     => $f_teaser_text ?: null,
                            ':details_text'    => $f_details_text ?: null,
                            ':vote_question'   => $f_vote_question ?: null,
                            ':vote_choice_1'   => $f_vote_1 ?: null,
                            ':vote_choice_2'   => $f_vote_2 ?: null,
                            ':vote_choice_3'   => $f_vote_3 ?: null,
                            ':vote_choice_4'   => $f_vote_4 ?: null,
                            ':revelation_text'       => $f_revelation ?: null,
                            ':winner_proposition_id' => $f_winner_prop_id,
                            ':answer_confidence'     => $f_confidence,
                            ':person_name'     => $f_person_name ?: null,
                            ':person_title'    => $f_person_title ?: null,
                            ':person_bio'      => $f_person_bio ?: null,
                            ':person_photo'    => $f_person_photo ?: null,
                            ':date_week1'      => $clean_date($f_date_week1),
                            ':date_week2'      => $clean_date($f_date_week2),
                            ':date_week3'      => $clean_date($f_date_week3),
                            ':date_revelation' => $clean_date($f_date_revel),
                            ':badge_reward_id' => $f_badge_id,
                            ':xp_reward'       => $f_xp,
                            ':status'          => $f_status,
                            ':id'              => $episode['id'],
                        ]);
                        $saved_id = (int)$episode['id'];
                    } else {
                        $ins = $pdo->prepare('
                            INSERT INTO ktc_episodes
                                (slug, title, season_id, object_name, object_hidden,
                                 teaser_text, details_text, vote_question,
                                 vote_choice_1, vote_choice_2, vote_choice_3, vote_choice_4,
                                 revelation_text, winner_proposition_id, answer_confidence,
                                 person_name, person_title, person_bio, person_photo,
                                 date_week1, date_week2, date_week3, date_revelation,
                                 badge_reward_id, xp_reward, status)
                            VALUES
                                (:slug, :title, :season_id, :object_name, :object_hidden,
                                 :teaser_text, :details_text, :vote_question,
                                 :vote_choice_1, :vote_choice_2, :vote_choice_3, :vote_choice_4,
                                 :revelation_text, :winner_proposition_id, :answer_confidence,
                                 :person_name, :person_title, :person_bio, :person_photo,
                                 :date_week1, :date_week2, :date_week3, :date_revelation,
                                 :badge_reward_id, :xp_reward, :status)
                        ');
                        $ins->execute([
                            ':slug'                  => $f_slug,
                            ':title'                 => $f_title,
                            ':season_id'             => $f_season_id,
                            ':object_name'           => $f_object_name ?: null,
                            ':object_hidden'         => $f_object_hidden,
                            ':teaser_text'           => $f_teaser_text ?: null,
                            ':details_text'          => $f_details_text ?: null,
                            ':vote_question'         => $f_vote_question ?: null,
                            ':vote_choice_1'         => $f_vote_1 ?: null,
                            ':vote_choice_2'         => $f_vote_2 ?: null,
                            ':vote_choice_3'         => $f_vote_3 ?: null,
                            ':vote_choice_4'         => $f_vote_4 ?: null,
                            ':revelation_text'       => $f_revelation ?: null,
                            ':winner_proposition_id' => $f_winner_prop_id,
                            ':answer_confidence'     => $f_confidence,
                            ':person_name'           => $f_person_name ?: null,
                            ':person_title'          => $f_person_title ?: null,
                            ':person_bio'            => $f_person_bio ?: null,
                            ':person_photo'          => $f_person_photo ?: null,
                            ':date_week1'            => $clean_date($f_date_week1),
                            ':date_week2'            => $clean_date($f_date_week2),
                            ':date_week3'            => $clean_date($f_date_week3),
                            ':date_revelation'       => $clean_date($f_date_revel),
                            ':badge_reward_id'       => $f_badge_id,
                            ':xp_reward'             => $f_xp,
                            ':status'                => $f_status,
                        ]);
                        $saved_id = (int)$pdo->lastInsertId();
                    }
                    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                    header('Location: ' . $base . '/admin/ktc-episode-edit.php?id=' . $saved_id . '&saved=1');
                    exit;
                } catch (PDOException $e) {
                    $flash = 'Erreur lors de la sauvegarde : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            }

        // ── Action : add_photo ─────────────────────────────────
        } elseif ($action === 'add_photo' && $is_edit) {
            $ph_path    = '';
            $ph_caption = safe_input($_POST['photo_caption'] ?? '', 255);
            $ph_week    = max(1, min(4, (int)($_POST['photo_week'] ?? 1)));
            $ph_order   = max(0, (int)($_POST['photo_sort'] ?? 0));

            if (!empty($_FILES['photo_file']['tmp_name'])) {
                $up_ph = upload_editorial_image($_FILES['photo_file'], 'ktc');
                if (!$up_ph['ok']) {
                    $flash = 'Erreur upload : ' . ($up_ph['error'] ?? 'fichier invalide');
                    $flash_type = 'err';
                } else {
                    $ph_path = $up_ph['path'];
                }
            } elseif (!empty($_POST['photo_path'])) {
                $ph_path = safe_input($_POST['photo_path'], 255);
            }

            if ($flash_type !== 'err' && empty($ph_path)) {
                $flash = 'Sélectionnez une photo ou fournissez une URL.';
                $flash_type = 'err';
            }
            if ($flash_type !== 'err') {
                try {
                    $ins = $pdo->prepare('
                        INSERT INTO ktc_episode_photos (episode_id, file_path, caption, reveal_week, sort_order)
                        VALUES (:eid, :fp, :caption, :week, :sort)
                    ');
                    $ins->execute([
                        ':eid'     => $episode['id'],
                        ':fp'      => $ph_path,
                        ':caption' => $ph_caption ?: null,
                        ':week'    => $ph_week,
                        ':sort'    => $ph_order,
                    ]);
                    $flash = 'Photo ajoutée.';
                    $flash_type = 'ok';
                    $s = $pdo->prepare('SELECT * FROM ktc_episode_photos WHERE episode_id = :eid ORDER BY sort_order ASC, id ASC');
                    $s->execute([':eid' => $episode['id']]);
                    $photos = $s->fetchAll();
                } catch (PDOException $e) {
                    $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            }

        // ── Action : delete_photo ──────────────────────────────
        } elseif ($action === 'delete_photo' && $is_edit) {
            $photo_id = (int)($_POST['photo_id'] ?? 0);
            if ($photo_id > 0) {
                try {
                    $del = $pdo->prepare('DELETE FROM ktc_episode_photos WHERE id = :id AND episode_id = :eid');
                    $del->execute([':id' => $photo_id, ':eid' => $episode['id']]);
                    $flash = 'Photo supprimee.';
                    $flash_type = 'ok';
                    // Recharger les photos
                    $s = $pdo->prepare('SELECT * FROM ktc_episode_photos WHERE episode_id = :eid ORDER BY sort_order ASC, id ASC');
                    $s->execute([':eid' => $episode['id']]);
                    $photos = $s->fetchAll();
                } catch (PDOException $e) {
                    $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            }

        // ── Action : advance_phase ─────────────────────────────
        } elseif ($action === 'advance_phase' && $is_edit) {
            $phase_map = [
                'draft'    => 'week1',
                'week1'    => 'week2',
                'week2'    => 'week3',
                'week3'    => 'revealed',
                'revealed' => 'archived',
            ];
            $cur_status = $episode['status'];
            if (isset($phase_map[$cur_status])) {
                $new_status = $phase_map[$cur_status];
                try {
                    $u = $pdo->prepare('UPDATE ktc_episodes SET status = :s WHERE id = :id');
                    $u->execute([':s' => $new_status, ':id' => $episode['id']]);
                    $episode['status'] = $new_status;
                    $flash = 'Phase avancee : ' . htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8') . '.';
                    $flash_type = 'ok';
                } catch (PDOException $e) {
                    $flash = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            } else {
                $flash = 'Impossible d\'avancer la phase.';
                $flash_type = 'err';
            }
        }
    }
}

// ── Valeurs formulaire (POST en cas d'erreur, sinon episode) ──
$v = function(string $key, $default = '') use ($episode) {
    if (isset($_POST[$key])) return $_POST[$key];
    if ($episode && isset($episode[$key])) return $episode[$key];
    return $default;
};

$v_title          = $v('title');
$v_slug           = $v('slug');
$v_season_id      = (int)$v('season_id', 0);
$v_badge_id       = (int)$v('badge_reward_id', 0);
$v_xp             = (int)$v('xp_reward', 50);
$v_status         = $v('status', 'draft');
$v_object_name    = $v('object_name');
$v_object_hidden  = isset($_POST['object_hidden']) ? (bool)$_POST['object_hidden']
                  : (bool)($episode['object_hidden'] ?? 1);
$v_teaser         = $v('teaser_text');
$v_details        = $v('details_text');
$v_vote_q         = $v('vote_question');
$v_vote_1         = $v('vote_choice_1');
$v_vote_2         = $v('vote_choice_2');
$v_vote_3         = $v('vote_choice_3');
$v_vote_4         = $v('vote_choice_4');
$v_revelation     = $v('revelation_text');
$v_person_name    = $v('person_name');
$v_person_title   = $v('person_title');
$v_person_bio     = $v('person_bio');
$v_person_photo   = $v('person_photo');
$v_date_week1     = $v('date_week1');
$v_date_week2     = $v('date_week2');
$v_date_week3     = $v('date_week3');
$v_date_revel     = $v('date_revelation');
$v_winner_prop_id = (int)$v('winner_proposition_id', 0);
$v_confidence     = $v('answer_confidence', 'certain');

// Statuts episode pour le select
$status_options = [
    'draft'    => 'Brouillon',
    'week1'    => 'Semaine 1 — Decouverte',
    'week2'    => 'Semaine 2 — Indices',
    'week3'    => 'Semaine 3 — Votes',
    'revealed' => 'Revele',
    'archived' => 'Archive',
];

// ── JS slugify ─────────────────────────────────────────────────
$admin_scripts = <<<'JS'
<script>
function slugify(text) {
    var map = {
        'a':'àâäáã',
        'e':'èéêë',
        'i':'îïíì',
        'o':'ôöóò',
        'u':'ùûüú',
        'c':'ç','n':'ñ','y':'ýÿ'
    };
    var out = text.toLowerCase();
    for (var r in map) {
        var chars = map[r].split('');
        for (var i = 0; i < chars.length; i++) {
            out = out.split(chars[i]).join(r);
        }
    }
    out = out.replace('oe','œ').replace('ae','æ');
    out = out.replace(/[^a-z0-9\s\-]/g, '')
             .replace(/[\s]+/g, '-')
             .replace(/-+/g, '-')
             .replace(/^-|-$/g, '');
    return out;
}

var titleEl  = document.getElementById('f_title');
var slugEl   = document.getElementById('f_slug');
var slugLocked = slugEl && slugEl.value.length > 0;

if (titleEl && slugEl) {
    titleEl.addEventListener('input', function() {
        if (!slugLocked) slugEl.value = slugify(this.value);
    });
    slugEl.addEventListener('input', function() {
        slugLocked = this.value.length > 0;
    });
    slugEl.addEventListener('blur', function() {
        this.value = slugify(this.value);
    });
}
</script>
JS;

require_once __DIR__ . '/_admin-header.php';
?>

<!-- ── Breadcrumb ──────────────────────────────────────────── -->
<div style="margin-bottom:18px;font-size:.82rem;color:#6b7f96">
  <a href="ktc-episodes.php" style="color:#6b7f96;text-decoration:none">&larr; KTC Episodes</a>
  <span style="margin:0 8px">/</span>
  <?= $is_edit ? htmlspecialchars($episode['title'], ENT_QUOTES, 'UTF-8') : 'Nouvel episode' ?>
</div>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">
      <?= $is_edit ? '&#x270F; Editer l\'episode' : '&#x2B; Nouvel episode' ?>
    </h1>
    <?php if ($is_edit): ?>
      <p class="adm-page-sub">
        ID #<?= (int)$episode['id'] ?> &mdash; Statut :
        <strong><?= htmlspecialchars($status_options[$episode['status']] ?? $episode['status'], ENT_QUOTES, 'UTF-8') ?></strong>
      </p>
    <?php endif; ?>
  </div>
  <div class="adm-page-actions">
    <?php if ($is_edit): ?>
      <?php
      $phase_map_label = [
          'draft'    => 'Lancer sem. 1',
          'week1'    => 'Passer sem. 2',
          'week2'    => 'Passer sem. 3',
          'week3'    => 'Reveler',
          'revealed' => 'Archiver',
      ];
      if (isset($phase_map_label[$episode['status']])): ?>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Avancer la phase de cet episode ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="advance_phase">
          <button type="submit" class="btn-adm btn-adm-success">
            &#x25B6; <?= htmlspecialchars($phase_map_label[$episode['status']], ENT_QUOTES, 'UTF-8') ?>
          </button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
    <a href="ktc-episodes.php" class="btn-adm btn-adm-ghost">Retour &agrave; la liste</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="adm-flash adm-flash-<?= $flash_type ?>">
    <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!$pdo): ?>
  <div class="adm-flash adm-flash-err">
    Base de donn&eacute;es non disponible. Les modifications ne seront pas sauvegard&eacute;es.
  </div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- FORMULAIRE PRINCIPAL                                        -->
<!-- ═══════════════════════════════════════════════════════════ -->
<form method="post" action="ktc-episode-edit.php<?= $is_edit ? '?id=' . (int)$episode['id'] : '' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_episode">

  <!-- ── Section 1 : Informations generales ──────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">&#x2139; Section 1 &mdash; Informations generales</p>
    <div class="adm-form-grid">

      <!-- Titre -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_title">Titre <span>*</span></label>
        <input id="f_title" type="text" name="title" class="adm-input"
               maxlength="255" required
               placeholder="Ex : Le mystere de novembre 2025"
               value="<?= htmlspecialchars($v_title, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <!-- Slug -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_slug">Slug (URL)</label>
        <input id="f_slug" type="text" name="slug" class="adm-input"
               maxlength="255"
               placeholder="genere-depuis-le-titre"
               value="<?= htmlspecialchars($v_slug, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Genere automatiquement depuis le titre. Modifiable manuellement.</span>
      </div>

      <!-- Saison -->
      <div class="adm-field">
        <label class="adm-label" for="f_season">Saison associee</label>
        <select id="f_season" name="season_id" class="adm-select">
          <option value="">— Aucune saison —</option>
          <?php foreach ($seasons_list as $sea): ?>
            <option value="<?= (int)$sea['id'] ?>"
              <?= $v_season_id === (int)$sea['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($sea['title'], ENT_QUOTES, 'UTF-8') ?>
              <?= $sea['status'] === 'active' ? ' (EN COURS)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Badge recompense -->
      <div class="adm-field">
        <label class="adm-label" for="f_badge">Badge recompense</label>
        <select id="f_badge" name="badge_reward_id" class="adm-select">
          <option value="">— Aucun badge —</option>
          <?php foreach ($badges_list as $b): ?>
            <option value="<?= (int)$b['id'] ?>"
              <?= $v_badge_id === (int)$b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- XP -->
      <div class="adm-field">
        <label class="adm-label" for="f_xp">XP de recompense</label>
        <input id="f_xp" type="number" name="xp_reward" class="adm-input"
               min="0" max="9999" value="<?= (int)$v_xp ?>">
        <span class="adm-hint">Points XP attribues aux participants a la revelation.</span>
      </div>

      <!-- Statut -->
      <div class="adm-field">
        <label class="adm-label" for="f_status">Statut</label>
        <select id="f_status" name="status" class="adm-select">
          <?php foreach ($status_options as $sval => $slabel): ?>
            <option value="<?= $sval ?>" <?= $v_status === $sval ? 'selected' : '' ?>>
              <?= htmlspecialchars($slabel, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>
  </div>

  <!-- ── Section 2 : L'objet mystere ────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">&#x1F50D; Section 2 &mdash; L'objet mystere</p>
    <div class="adm-form-grid">

      <!-- Nom de l'objet -->
      <div class="adm-field">
        <label class="adm-label" for="f_object_name">Nom de l'objet</label>
        <input id="f_object_name" type="text" name="object_name" class="adm-input"
               maxlength="255"
               placeholder="Ex : Fer a repasser a braise XIXe"
               value="<?= htmlspecialchars($v_object_name, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Masque cote public jusqu'a la revelation (semaine 4).</span>
      </div>

      <!-- Object hidden -->
      <div class="adm-field" style="justify-content:flex-end;padding-bottom:4px">
        <label class="adm-label" style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="object_hidden" value="1"
                 <?= $v_object_hidden ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:#ea5649">
          Masquer le nom de l'objet (cote public)
        </label>
        <span class="adm-hint">Decocher uniquement apres revelation.</span>
      </div>

      <!-- Teaser semaine 1 -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_teaser">Texte semaine 1 &mdash; Decouverte</label>
        <textarea id="f_teaser" name="teaser_text" class="adm-textarea" rows="5"
                  placeholder="Premier texte mysterieux qui introduit l'objet sans le nommer..."
        ><?= htmlspecialchars($v_teaser, ENT_QUOTES, 'UTF-8') ?></textarea>
        <span class="adm-hint">Visible des la semaine 1. Doit piquer la curiosite sans reveler l'objet.</span>
      </div>

      <!-- Details semaine 2 -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_details">Texte semaine 2 &mdash; Indices supplementaires</label>
        <textarea id="f_details" name="details_text" class="adm-textarea" rows="5"
                  placeholder="Nouveaux indices, contexte historique, anecdotes..."
        ><?= htmlspecialchars($v_details, ENT_QUOTES, 'UTF-8') ?></textarea>
        <span class="adm-hint">Affiche en plus du texte semaine 1. Permet d'affiner les propositions.</span>
      </div>

    </div>
  </div>

  <!-- ── Section 3 : Vote semaine 3 ──────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">&#x1F5F3; Section 3 &mdash; Vote semaine 3</p>
    <div class="adm-form-grid">

      <!-- Question du vote -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_vote_q">Question du vote</label>
        <input id="f_vote_q" type="text" name="vote_question" class="adm-input"
               maxlength="500"
               placeholder="Ex : Selon vous, cet objet est..."
               value="<?= htmlspecialchars($v_vote_q, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <!-- 4 propositions -->
      <div class="adm-field">
        <label class="adm-label" for="f_vote_1">Proposition A</label>
        <input id="f_vote_1" type="text" name="vote_choice_1" class="adm-input"
               maxlength="255"
               placeholder="Premiere proposition..."
               value="<?= htmlspecialchars($v_vote_1, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_vote_2">Proposition B</label>
        <input id="f_vote_2" type="text" name="vote_choice_2" class="adm-input"
               maxlength="255"
               placeholder="Deuxieme proposition..."
               value="<?= htmlspecialchars($v_vote_2, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_vote_3">Proposition C</label>
        <input id="f_vote_3" type="text" name="vote_choice_3" class="adm-input"
               maxlength="255"
               placeholder="Troisieme proposition..."
               value="<?= htmlspecialchars($v_vote_3, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_vote_4">Proposition D</label>
        <input id="f_vote_4" type="text" name="vote_choice_4" class="adm-input"
               maxlength="255"
               placeholder="Quatrieme proposition..."
               value="<?= htmlspecialchars($v_vote_4, ENT_QUOTES, 'UTF-8') ?>">
      </div>

    </div>
    <div style="margin-top:12px;padding:12px;background:#fffbf0;border-radius:8px;border:1px solid #f3e8b0">
      <p style="margin:0;font-size:.78rem;color:#8a6020;font-weight:600">
        &#x26A0; La bonne reponse correspond a la proposition dont le texte correspond au nom de l'objet.
        Aucun marquage technique n'est necessaire ici &mdash; la correction est effectuee lors de la revelation.
      </p>
    </div>
  </div>

  <!-- ── Section 4 : Revelation ──────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">&#x2728; Section 4 &mdash; Revelation (semaine 4)</p>
    <div class="adm-form-grid">

      <!-- Rappel nom de l'objet -->
      <div class="adm-field adm-form-full"
           style="padding:14px;background:#f8f4ef;border-radius:8px">
        <span class="adm-label" style="display:block;margin-bottom:4px">Rappel &mdash; Nom de l'objet</span>
        <span style="font-size:.95rem;font-weight:700;color:#0c1e2e">
          <?= $v_object_name
              ? htmlspecialchars($v_object_name, ENT_QUOTES, 'UTF-8')
              : '<em style="color:#bbb">Non renseigne</em>' ?>
        </span>
        <span class="adm-hint" style="display:block;margin-top:4px">
          Ce nom sera revele aux membres lors du passage au statut "Revele".
          Pour le modifier, utilisez la section 2.
        </span>
      </div>

      <!-- Histoire complete -->
      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_revelation">Histoire complete de l'objet</label>
        <textarea id="f_revelation" name="revelation_text" class="adm-textarea"
                  rows="12" style="min-height:220px"
                  placeholder="L'histoire complete, le contexte historique, l'anecdote du brocanteur... Texte long bienvenu."
        ><?= htmlspecialchars($v_revelation, ENT_QUOTES, 'UTF-8') ?></textarea>
        <span class="adm-hint">Affiche uniquement quand le statut est "Revele" ou "Archive". HTML simple accepte.</span>
      </div>

      <!-- Niveau de confiance -->
      <div class="adm-field">
        <label class="adm-label" for="f_confidence">Niveau de confiance de la réponse</label>
        <select id="f_confidence" name="answer_confidence" class="adm-select">
          <option value="certain" <?= $v_confidence === 'certain' ? 'selected' : '' ?>>Réponse certaine — documentée</option>
          <option value="probable" <?= $v_confidence === 'probable' ? 'selected' : '' ?>>Probable — sources concordantes</option>
          <option value="estimation" <?= $v_confidence === 'estimation' ? 'selected' : '' ?>>Estimation — tradition orale / incertitude</option>
        </select>
        <span class="adm-hint">Affiché aux membres à la révélation pour être transparent sur la fiabilité de la réponse.</span>
      </div>

      <!-- Sélection du gagnant parmi les propositions -->
      <?php if (!empty($all_propositions)): ?>
      <div class="adm-field adm-form-full">
        <label class="adm-label">Meilleure proposition des Zonautes</label>
        <div style="display:flex;flex-direction:column;gap:8px;max-height:340px;overflow-y:auto;border:1px solid #e8e2db;border-radius:10px;padding:10px;background:#faf8f5">
          <label style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:#fff;border-radius:8px;cursor:pointer;border:2px solid <?= !$v_winner_prop_id ? '#ea5649' : 'transparent' ?>">
            <input type="radio" name="winner_proposition_id" value="0" <?= !$v_winner_prop_id ? 'checked' : '' ?> style="margin-top:3px;accent-color:#ea5649">
            <span style="font-size:.82rem;color:#6b7f96;font-style:italic">Aucun gagnant désigné</span>
          </label>
          <?php foreach ($all_propositions as $prop): ?>
          <label style="display:flex;align-items:flex-start;gap:10px;padding:10px;background:#fff;border-radius:8px;cursor:pointer;border:2px solid <?= (int)$prop['id'] === $v_winner_prop_id ? '#2a8c40' : 'transparent' ?>">
            <input type="radio" name="winner_proposition_id" value="<?= (int)$prop['id'] ?>" <?= (int)$prop['id'] === $v_winner_prop_id ? 'checked' : '' ?> style="margin-top:3px;accent-color:#2a8c40">
            <span style="flex:1;min-width:0">
              <span style="font-size:.72rem;font-weight:800;color:#9a6800;display:block;margin-bottom:2px"><?= htmlspecialchars($prop['pseudo'], ENT_QUOTES, 'UTF-8') ?></span>
              <span style="font-size:.85rem;color:#0c1e2e;line-height:1.4;display:block"><?= htmlspecialchars($prop['proposition'], ENT_QUOTES, 'UTF-8') ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <span class="adm-hint">Le Zonaute dont la proposition est sélectionnée recevra les XP de récompense.</span>
      </div>
      <?php else: ?>
      <div class="adm-form-full" style="padding:12px;background:#faf8f5;border-radius:8px;border:1px dashed #e8e2db">
        <p style="margin:0;font-size:.82rem;color:#9ca3af">Aucune proposition soumise par les membres pour cet épisode.</p>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- ── Section 5 : Le brocanteur ───────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">&#x1F9D4; Section 5 &mdash; Le brocanteur / passionne</p>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label" for="f_person_name">Nom</label>
        <input id="f_person_name" type="text" name="person_name" class="adm-input"
               maxlength="150"
               placeholder="Ex : Marcel Bertrand"
               value="<?= htmlspecialchars($v_person_name, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_person_title">Titre / Metier</label>
        <input id="f_person_title" type="text" name="person_title" class="adm-input"
               maxlength="200"
               placeholder="Ex : Brocanteur a Fontenay-le-Comte"
               value="<?= htmlspecialchars($v_person_title, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label" for="f_person_bio">Biographie courte</label>
        <textarea id="f_person_bio" name="person_bio" class="adm-textarea" rows="4"
                  placeholder="Qui est cette personne ? Son parcours, sa passion, son lien avec l'objet..."
        ><?= htmlspecialchars($v_person_bio, ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label">Photo de la personne</label>
        <input type="file" name="person_photo_file" accept="image/jpeg,image/png,image/webp"
               class="adm-input" style="padding:6px">
        <input type="hidden" name="person_photo" value="<?= htmlspecialchars($v_person_photo, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">JPEG / PNG / WebP — max 5 Mo. Laissez vide pour conserver la photo actuelle.</span>
      </div>

      <?php if ($v_person_photo): ?>
        <div class="adm-form-full" style="margin-top:-8px">
          <img src="<?= htmlspecialchars($v_person_photo, ENT_QUOTES, 'UTF-8') ?>"
               alt="Apercu photo"
               style="width:80px;height:80px;object-fit:cover;border-radius:50%;border:3px solid #e8e2db">
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- ── Section 6 : Calendrier ──────────────────────────────── -->
  <div class="adm-card">
    <p class="adm-card-title">&#x1F4C5; Section 6 &mdash; Calendrier</p>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label" for="f_date_w1">Debut semaine 1</label>
        <input id="f_date_w1" type="date" name="date_week1" class="adm-input"
               value="<?= htmlspecialchars($v_date_week1, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Debut de la phase Decouverte.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_date_w2">Debut semaine 2</label>
        <input id="f_date_w2" type="date" name="date_week2" class="adm-input"
               value="<?= htmlspecialchars($v_date_week2, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Debut de la phase Indices supplementaires.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_date_w3">Debut semaine 3</label>
        <input id="f_date_w3" type="date" name="date_week3" class="adm-input"
               value="<?= htmlspecialchars($v_date_week3, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Debut de la phase Vote.</span>
      </div>

      <div class="adm-field">
        <label class="adm-label" for="f_date_rev">Date de revelation</label>
        <input id="f_date_rev" type="date" name="date_revelation" class="adm-input"
               value="<?= htmlspecialchars($v_date_revel, ENT_QUOTES, 'UTF-8') ?>">
        <span class="adm-hint">Semaine 4 : revelation de l'identite de l'objet.</span>
      </div>

    </div>
  </div>

  <!-- ── Boutons de soumission ────────────────────────────────── -->
  <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-bottom:28px">
    <a href="ktc-episodes.php" class="btn-adm btn-adm-ghost">Annuler</a>
    <button type="submit" class="btn-adm btn-adm-primary">
      <?= $is_edit ? '&#x2713; Sauvegarder les modifications' : '&#x2B; Creer l\'episode' ?>
    </button>
  </div>

</form>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Section 7 : Photos (galerie) — hors formulaire principal   -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if ($is_edit): ?>
  <div class="adm-card">
    <p class="adm-card-title">&#x1F4F8; Section 7 &mdash; Photos de l'episode</p>

    <?php if (empty($photos)): ?>
      <div class="adm-empty" style="padding:32px 24px">
        <div class="adm-empty-icon">&#x1F4F7;</div>
        <p>Aucune photo pour cet episode. Ajoutez-en une ci-dessous.</p>
      </div>
    <?php else: ?>
      <div class="adm-table-wrap" style="margin-bottom:24px">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Apercu</th>
              <th>URL / Chemin</th>
              <th>Legende</th>
              <th>Visible sem.</th>
              <th>Ordre</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($photos as $ph): ?>
              <tr>
                <td style="width:64px">
                  <img src="<?= e(media_url($ph['file_path'])) ?>"
                       alt=""
                       style="width:52px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #e8e2db"
                       onerror="this.style.display='none'">
                </td>
                <td style="font-size:.78rem;max-width:240px;word-break:break-all">
                  <?= htmlspecialchars($ph['file_path'], ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td style="font-size:.82rem">
                  <?= $ph['caption'] ? htmlspecialchars($ph['caption'], ENT_QUOTES, 'UTF-8') : '<span style="color:#bbb">—</span>' ?>
                </td>
                <td>
                  <span class="adm-badge" style="background:rgba(14,165,233,.1);color:#0369a1">
                    Sem. <?= (int)$ph['reveal_week'] ?>
                  </span>
                </td>
                <td style="color:#6b7f96;font-size:.82rem"><?= (int)$ph['sort_order'] ?></td>
                <td>
                  <form method="post" action="ktc-episode-edit.php?id=<?= (int)$episode['id'] ?>"
                        style="display:inline"
                        onsubmit="return confirm('Supprimer cette photo ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"   value="delete_photo">
                    <input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>">
                    <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm">
                      &#x1F5D1; Supprimer
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Formulaire ajout photo -->
    <div style="background:#f8f4ef;border-radius:10px;padding:18px">
      <p style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7f96;margin:0 0 14px">
        + Ajouter une photo
      </p>
      <form method="post" action="ktc-episode-edit.php?id=<?= (int)$episode['id'] ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_photo">
        <div class="adm-form-grid">

          <div class="adm-field adm-form-full">
            <label class="adm-label" for="f_photo_file">Photo à uploader <span>*</span></label>
            <input id="f_photo_file" type="file" name="photo_file" accept="image/jpeg,image/png,image/webp"
                   class="adm-input" style="padding:6px">
            <span class="adm-hint">JPEG / PNG / WebP — max 5 Mo. Ou fournissez une URL ci-dessous.</span>
          </div>
          <div class="adm-field adm-form-full">
            <label class="adm-label" for="f_photo_path">URL alternative (optionnel si fichier uploadé)</label>
            <input id="f_photo_path" type="text" name="photo_path" class="adm-input"
                   maxlength="255"
                   placeholder="https://... ou /assets/img/ktc/...">
          </div>

          <div class="adm-field">
            <label class="adm-label" for="f_photo_caption">Legende</label>
            <input id="f_photo_caption" type="text" name="photo_caption" class="adm-input"
                   maxlength="255"
                   placeholder="Texte de la photo (optionnel)">
          </div>

          <div class="adm-field">
            <label class="adm-label" for="f_photo_week">Visible a partir de la semaine</label>
            <select id="f_photo_week" name="photo_week" class="adm-select">
              <option value="1">Semaine 1 — Decouverte</option>
              <option value="2">Semaine 2 — Indices</option>
              <option value="3">Semaine 3 — Votes</option>
              <option value="4">Semaine 4 — Revelation</option>
            </select>
          </div>

          <div class="adm-field">
            <label class="adm-label" for="f_photo_sort">Ordre d'affichage</label>
            <input id="f_photo_sort" type="number" name="photo_sort" class="adm-input"
                   min="0" max="255" value="0">
            <span class="adm-hint">0 = premier. Les plus petits chiffres apparaissent en premier.</span>
          </div>

        </div>
        <div style="margin-top:14px;display:flex;justify-content:flex-end">
          <button type="submit" class="btn-adm btn-adm-success">
            &#x2B; Ajouter la photo
          </button>
        </div>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="adm-card" style="opacity:.6">
    <p class="adm-card-title">&#x1F4F8; Section 7 &mdash; Photos de l'episode</p>
    <div class="adm-empty" style="padding:24px">
      <p>Enregistrez d'abord l'episode pour pouvoir ajouter des photos.</p>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
