<?php
// ============================================================
// ZONE85 — Admin édition épisode KTC V11.1
// Éditeur simple pour lancer le CMS éditorial KTC.
// ============================================================

$admin_current    = 'ktc';
$admin_page_title = 'Épisode KTC';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;
$episode = [
    'slug' => '', 'title' => '', 'object_name' => '', 'object_hidden' => 1,
    'teaser_text' => '', 'details_text' => '', 'vote_question' => '', 'revelation_text' => '',
    'person_name' => '', 'person_title' => '', 'person_bio' => '', 'person_photo' => '',
    'date_week1' => '', 'date_week2' => '', 'date_week3' => '', 'date_revelation' => '',
    'xp_reward' => 50, 'badge_reward_id' => null, 'status' => 'draft'
];

function z85_slugify_ktc(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower((string)$s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'episode-ktc';
}

if ($pdo && $id > 0) {
    try {
        $st = $pdo->prepare("SELECT * FROM ktc_episodes WHERE id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if ($row) $episode = array_merge($episode, $row);
    } catch (PDOException $e) {
        $flash = ['type' => 'err', 'msg' => 'Impossible de charger l’épisode. Migration 017 importée ?'];
    }
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Token CSRF invalide.'];
    } else {
        $data = [];
        foreach (array_keys($episode) as $k) {
            if ($k === 'id' || $k === 'created_at' || $k === 'updated_at') continue;
            $data[$k] = trim($_POST[$k] ?? '');
        }
        $data['title'] = safe_input($data['title'] ?? '', 255);
        $data['slug'] = safe_input($data['slug'] ?: z85_slugify_ktc($data['title']), 255);
        $data['object_hidden'] = isset($_POST['object_hidden']) ? 1 : 0;
        $data['xp_reward'] = max(0, min(500, (int)($data['xp_reward'] ?? 50)));
        $data['badge_reward_id'] = !empty($_POST['badge_reward_id']) ? (int)$_POST['badge_reward_id'] : null;
        $valid_status = ['draft','week1','week2','week3','revealed','archived'];
        if (!in_array($data['status'], $valid_status, true)) $data['status'] = 'draft';

        foreach (['date_week1','date_week2','date_week3','date_revelation'] as $d) {
            $data[$d] = $data[$d] ?: null;
        }

        if ($data['title'] === '') {
            $flash = ['type' => 'err', 'msg' => 'Le titre est obligatoire.'];
        } else {
            try {
                if ($id > 0) {
                    $sql = "UPDATE ktc_episodes SET
                        slug=:slug,title=:title,object_name=:object_name,object_hidden=:object_hidden,
                        teaser_text=:teaser_text,details_text=:details_text,vote_question=:vote_question,revelation_text=:revelation_text,
                        person_name=:person_name,person_title=:person_title,person_bio=:person_bio,person_photo=:person_photo,
                        date_week1=:date_week1,date_week2=:date_week2,date_week3=:date_week3,date_revelation=:date_revelation,
                        badge_reward_id=:badge_reward_id,xp_reward=:xp_reward,status=:status
                        WHERE id=:id";
                    $data['id'] = $id;
                    $pdo->prepare($sql)->execute($data);
                } else {
                    $sql = "INSERT INTO ktc_episodes
                        (slug,title,object_name,object_hidden,teaser_text,details_text,vote_question,revelation_text,
                         person_name,person_title,person_bio,person_photo,date_week1,date_week2,date_week3,date_revelation,
                         badge_reward_id,xp_reward,status,created_at)
                        VALUES
                        (:slug,:title,:object_name,:object_hidden,:teaser_text,:details_text,:vote_question,:revelation_text,
                         :person_name,:person_title,:person_bio,:person_photo,:date_week1,:date_week2,:date_week3,:date_revelation,
                         :badge_reward_id,:xp_reward,:status,NOW())";
                    $pdo->prepare($sql)->execute($data);
                    $id = (int)$pdo->lastInsertId();
                    header('Location: ktc-episode-edit.php?id=' . $id . '&saved=1');
                    exit;
                }
                header('Location: ktc-episode-edit.php?id=' . $id . '&saved=1');
                exit;
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur SQL : ' . e($e->getMessage())];
            }
        }
        $episode = array_merge($episode, $data);
    }
}

if (isset($_GET['saved'])) $flash = ['type' => 'ok', 'msg' => 'Épisode KTC enregistré.'];

require_once __DIR__ . '/_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title"><?= $id ? 'Modifier l’épisode KTC' : 'Créer un épisode KTC' ?></h1>
    <p class="adm-page-sub">Un épisode = une rencontre locale + un objet mystère + une révélation en fin de mois.</p>
  </div>
  <div class="adm-page-actions">
    <a class="btn-adm btn-adm-ghost" href="ktc.php">← Retour KTC</a>
  </div>
</div>

<?php if ($flash): ?><div class="adm-flash adm-flash-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

<form method="post" class="adm-card">
  <?= csrf_field() ?>
  <h2 class="adm-card-title">Informations générales</h2>
  <div class="adm-form-grid">
    <div class="adm-field adm-form-full"><label class="adm-label">Titre <span>*</span></label><input class="adm-input" name="title" value="<?= e($episode['title']) ?>" required></div>
    <div class="adm-field"><label class="adm-label">Slug</label><input class="adm-input" name="slug" value="<?= e($episode['slug']) ?>" placeholder="auto si vide"></div>
    <div class="adm-field"><label class="adm-label">Statut</label><select class="adm-select" name="status">
      <?php foreach (['draft'=>'Brouillon','week1'=>'Semaine 1 — Découverte','week2'=>'Semaine 2 — Indices','week3'=>'Semaine 3 — Votes','revealed'=>'Révélation','archived'=>'Archivé'] as $k=>$v): ?>
        <option value="<?= e($k) ?>" <?= ($episode['status']===$k?'selected':'') ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select></div>
  </div>

  <h2 class="adm-card-title" style="margin-top:28px">La personne rencontrée</h2>
  <div class="adm-form-grid">
    <div class="adm-field"><label class="adm-label">Nom</label><input class="adm-input" name="person_name" value="<?= e($episode['person_name']) ?>"></div>
    <div class="adm-field"><label class="adm-label">Titre / lieu</label><input class="adm-input" name="person_title" value="<?= e($episode['person_title']) ?>" placeholder="Brocanteur à... "></div>
    <div class="adm-field adm-form-full"><label class="adm-label">Bio courte</label><textarea class="adm-textarea" name="person_bio"><?= e($episode['person_bio']) ?></textarea></div>
    <div class="adm-field adm-form-full"><label class="adm-label">Photo personne (chemin fichier)</label><input class="adm-input" name="person_photo" value="<?= e($episode['person_photo']) ?>" placeholder="uploads/ktc/photo.jpg"></div>
  </div>

  <h2 class="adm-card-title" style="margin-top:28px">L'objet mystère</h2>
  <div class="adm-form-grid">
    <div class="adm-field"><label class="adm-label">Nom de l’objet</label><input class="adm-input" name="object_name" value="<?= e($episode['object_name']) ?>"></div>
    <div class="adm-field"><label class="adm-label">Récompense XP</label><input class="adm-input" type="number" name="xp_reward" value="<?= (int)$episode['xp_reward'] ?>"></div>
    <div class="adm-field adm-form-full"><label><input type="checkbox" name="object_hidden" value="1" <?= !empty($episode['object_hidden']) ? 'checked' : '' ?>> Masquer le nom de l’objet jusqu’à la révélation</label></div>
    <div class="adm-field adm-form-full"><label class="adm-label">Semaine 1 — Découverte</label><textarea class="adm-textarea" name="teaser_text"><?= e($episode['teaser_text']) ?></textarea></div>
    <div class="adm-field adm-form-full"><label class="adm-label">Semaine 2 — Indices / détails</label><textarea class="adm-textarea" name="details_text"><?= e($episode['details_text']) ?></textarea></div>
    <div class="adm-field adm-form-full"><label class="adm-label">Semaine 3 — Question de vote</label><input class="adm-input" name="vote_question" value="<?= e($episode['vote_question']) ?>" placeholder="Selon vous, cet objet servait à quoi ?"></div>
    <div class="adm-field adm-form-full"><label class="adm-label">Révélation finale</label><textarea class="adm-textarea" name="revelation_text" style="min-height:180px"><?= e($episode['revelation_text']) ?></textarea></div>
  </div>

  <h2 class="adm-card-title" style="margin-top:28px">Calendrier</h2>
  <div class="adm-form-grid">
    <div class="adm-field"><label class="adm-label">Semaine 1</label><input class="adm-input" type="date" name="date_week1" value="<?= e($episode['date_week1']) ?>"></div>
    <div class="adm-field"><label class="adm-label">Semaine 2</label><input class="adm-input" type="date" name="date_week2" value="<?= e($episode['date_week2']) ?>"></div>
    <div class="adm-field"><label class="adm-label">Semaine 3</label><input class="adm-input" type="date" name="date_week3" value="<?= e($episode['date_week3']) ?>"></div>
    <div class="adm-field"><label class="adm-label">Révélation</label><input class="adm-input" type="date" name="date_revelation" value="<?= e($episode['date_revelation']) ?>"></div>
  </div>

  <div style="margin-top:28px"><button class="btn-adm btn-adm-primary" type="submit">Enregistrer l’épisode</button></div>
</form>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
