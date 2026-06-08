<?php
// ============================================================
// ZONE85 V12.1 — Admin : Randonnee — CMS editorial
// Encodage : UTF-8 sans BOM
// ============================================================
$admin_current    = 'randos';
$admin_page_title = 'Randonn&eacute;e &mdash; &Eacute;diteur';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';

require_admin();

$pdo        = db();
$flash      = '';
$flash_type = 'ok';

// ── Chargement rando existante ──────────────────────────────
$id    = (int)($_GET['id'] ?? 0);
$rando = null;
if ($id > 0 && $pdo) {
    try {
        $s = $pdo->prepare('SELECT * FROM randos WHERE id=:id LIMIT 1');
        $s->execute([':id' => $id]);
        $rando = $s->fetch();
    } catch (PDOException $e) {}
}

$is_edit          = $rando !== null;
$admin_page_title = $is_edit
    ? 'Editer &mdash; ' . htmlspecialchars($rando['title'] ?? '', ENT_QUOTES, 'UTF-8')
    : 'Nouvelle randonn&eacute;e';

if (!empty($_GET['saved'])) {
    if (!empty($_GET['gpx_warn'])) {
        $flash      = 'Rando enregistrée. Avertissement GPX : ' . htmlspecialchars(urldecode($_GET['gpx_warn']), ENT_QUOTES, 'UTF-8');
        $flash_type = 'warn';
    } else {
        $flash      = 'Rando enregistrée avec succès.';
        $flash_type = 'ok';
    }
}

// ── Saisons disponibles ─────────────────────────────────────
$seasons_list = [];
if ($pdo) {
    try {
        $ss = $pdo->query('SELECT id, title, status FROM seasons ORDER BY id DESC');
        $seasons_list = $ss->fetchAll();
    } catch (PDOException $e) {}
}

// ── Blocs de contenu ────────────────────────────────────────
$blocks = [];
if ($is_edit && $pdo) {
    try {
        $sb = $pdo->prepare('SELECT * FROM rando_blocks WHERE rando_id=:rid ORDER BY sort_order ASC, id ASC');
        $sb->execute([':rid' => $rando['id']]);
        $blocks = $sb->fetchAll();
    } catch (PDOException $e) {}
}

// ── Options duree ───────────────────────────────────────────
$duration_options = [
    30  => '30 min',
    60  => '1h',
    90  => '1h30',
    120 => '2h',
    150 => '2h30',
    180 => '3h',
    270 => 'Demi-journ&eacute;e',
    480 => 'Journ&eacute;e',
];

// ── Types de blocs (spec V12.1) ─────────────────────────────
$block_types_allowed = ['text', 'image', 'gallery', 'conseil', 'info', 'quote', 'recit', 'treasure'];

// ── Helper : recharger les blocs ────────────────────────────
function reload_blocks(object $pdo, int $rando_id): array {
    try {
        $s = $pdo->prepare('SELECT * FROM rando_blocks WHERE rando_id=:rid ORDER BY sort_order ASC, id ASC');
        $s->execute([':rid' => $rando_id]);
        return $s->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ── Traitement POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $flash      = 'Jeton CSRF invalide. Formulaire rejet&eacute;.';
        $flash_type = 'err';
    } else {
        $action = $_POST['action'] ?? '';

        // ────────────────────────────────────────────────────
        // SAVE RANDO (formulaire principal)
        // ────────────────────────────────────────────────────
        if ($action === 'save_rando') {
            $f_title      = safe_input($_POST['title']        ?? '', 255);
            $f_slug       = safe_input($_POST['slug']         ?? '', 255);
            $f_summary    = ''; // V12.3 : résumé supprimé côté admin, généré depuis intro/description
            $f_secteur    = in_array($_POST['secteur'] ?? '', ['bocage','littoral','marais','plaine'], true)
                            ? $_POST['secteur'] : 'bocage';
            $f_commune    = safe_input($_POST['commune']      ?? '', 150);
            $f_communes_raw = trim((string)($_POST['communes_json'] ?? ''));
            $f_communes_list = [];
            if ($f_communes_raw !== '') {
                $parts = preg_split('/[,\n;]+/', $f_communes_raw);
                foreach ($parts as $part) {
                    $name = safe_input(trim($part), 120);
                    if ($name !== '' && !in_array($name, $f_communes_list, true)) {
                        $f_communes_list[] = $name;
                    }
                }
            }
            if (empty($f_communes_list) && $f_commune !== '') {
                $f_communes_list[] = $f_commune;
            }
            $f_communes_json = !empty($f_communes_list) ? json_encode($f_communes_list, JSON_UNESCAPED_UNICODE) : null;
            $f_why_text = $_POST['why_text'] ?? '';  // HTML Quill, non échappé
            $allowed_seasons = ['printemps','ete','automne','hiver'];
            $f_recommended = [];
            foreach (($_POST['recommended_seasons'] ?? []) as $season_key) {
                if (in_array($season_key, $allowed_seasons, true)) $f_recommended[] = $season_key;
            }
            $f_recommended_json = !empty($f_recommended) ? json_encode($f_recommended) : null;
            $f_season_id  = !empty($_POST['season_id'])  ? (int)$_POST['season_id']  : null;
            $f_status     = in_array($_POST['status'] ?? '', ['draft','published','archived'], true)
                            ? $_POST['status'] : 'draft';
            $f_pub_at     = $_POST['published_at'] ?? '';

            $f_distance   = is_numeric($_POST['distance_km'] ?? '') ? (float)$_POST['distance_km'] : null;
            $f_duration   = !empty($_POST['duration_min'])   ? (int)$_POST['duration_min'] : null;
            $f_difficulty = in_array($_POST['difficulty'] ?? '', ['facile','moyen','difficile','expert'], true)
                            ? $_POST['difficulty'] : 'facile';
            $f_nature      = isset($_POST['nature_score'])     && $_POST['nature_score']     !== '' ? max(0, min(5, (int)$_POST['nature_score']))     : null;
            $f_patrimoine  = isset($_POST['patrimoine_score']) && $_POST['patrimoine_score'] !== '' ? max(0, min(5, (int)$_POST['patrimoine_score'])) : null;
            $f_famille     = isset($_POST['famille_score'])    && $_POST['famille_score']    !== '' ? max(0, min(5, (int)$_POST['famille_score']))    : null;
            $f_photo       = isset($_POST['photo_score'])      && $_POST['photo_score']      !== '' ? max(0, min(5, (int)$_POST['photo_score']))      : null;
            $f_start      = safe_input($_POST['start_point'] ?? '', 255);
            $f_gps_lat    = is_numeric($_POST['gps_lat'] ?? '') ? (float)$_POST['gps_lat'] : null;
            $f_gps_lng    = is_numeric($_POST['gps_lng'] ?? '') ? (float)$_POST['gps_lng'] : null;
            $f_parking    = $_POST['parking']                 ?? '';
            $f_access     = $_POST['accessibility']           ?? '';
            $f_gpx        = safe_input($_POST['gpx_url']      ?? '', 255);

            $f_meta_title  = safe_input($_POST['meta_title']       ?? '', 255);
            $f_meta_desc   = safe_input($_POST['meta_description'] ?? '', 500);
            $f_intro       = $_POST['intro_text'] ?? '';  // HTML Quill, non échappé
            $f_description = $_POST['description'] ?? '';  // texte libre long
            // V12.3 : pas de champ résumé dans le BO. On génère un résumé technique pour les cartes/SEO.
            $f_summary = trim(strip_tags($f_intro ?: $f_description));
            if (function_exists('mb_substr')) {
                $f_summary = mb_substr($f_summary, 0, 300);
            } else {
                $f_summary = substr($f_summary, 0, 300);
            }

            // Upload GPX si fichier envoye
            $f_gpx_file = $rando['gpx_file'] ?? null;
            if (!empty($_FILES['gpx_file_upload']['tmp_name'])) {
                $up_gpx = function_exists('upload_gpx_file')
                    ? upload_gpx_file($_FILES['gpx_file_upload'], 'randos/gpx')
                    : ['ok' => false, 'error' => 'Helper upload_gpx_file manquant.'];
                if ($up_gpx['ok']) {
                    if (!empty($f_gpx_file) && defined('BASE_PATH')) {
                        $old_gpx = rtrim(BASE_PATH, '/') . '/' . ltrim($f_gpx_file, '/');
                        if (is_file($old_gpx)) @unlink($old_gpx);
                    }
                    $f_gpx_file = $up_gpx['path'];
                    $f_gpx = ''; // privilégier le fichier uploadé au lien externe
                } else {
                    // Avertissement non bloquant : l'erreur GPX ne bloque pas la sauvegarde
                    $gpx_warning = $up_gpx['error'];
                }
            }

            // Upload image de couverture
            // Préserver fermement la couverture existante : les uploads galerie/blocs ne doivent jamais la vider.
            $cover_path = null;
            if ($is_edit && !empty($rando['id'])) {
                try {
                    $cover_stmt = $pdo->prepare('SELECT cover_image FROM randos WHERE id=:id LIMIT 1');
                    $cover_stmt->execute([':id'=>(int)$rando['id']]);
                    $cover_path = $cover_stmt->fetchColumn() ?: null;
                } catch (Throwable $e) {
                    $cover_path = $rando['cover_image'] ?? null;
                }
            }
            if (!$is_edit) {
                $cover_path = null;
            }
            if (!empty($_FILES['cover_image_file']['tmp_name'])) {
                $upload = upload_editorial_image($_FILES['cover_image_file'], 'randos');
                if ($upload['ok']) {
                    // Supprimer l'ancienne si presente
                    if (!empty($cover_path) && defined('BASE_PATH')) {
                        $old = rtrim(BASE_PATH, '/') . '/' . ltrim($cover_path, '/');
                        if (is_file($old)) @unlink($old);
                    }
                    $cover_path = $upload['path'];
                } else {
                    $flash      = 'Upload cover : ' . htmlspecialchars($upload['error'], ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            }

            // Nettoyage slug
            $f_slug = strtolower($f_slug);
            $f_slug = preg_replace('/[^a-z0-9\-]/', '', $f_slug);
            $f_slug = trim($f_slug, '-');
            if (empty($f_slug)) $f_slug = 'rando-' . time();

            // Date publication
            $published_at = null;
            if ($f_status === 'published') {
                if ($f_pub_at) {
                    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $f_pub_at);
                    $published_at = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
                } else {
                    $published_at = date('Y-m-d H:i:s');
                }
            } elseif ($f_pub_at) {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $f_pub_at);
                $published_at = $dt ? $dt->format('Y-m-d H:i:s') : null;
            }

            if (empty($f_title)) {
                $flash      = 'Le titre est obligatoire.';
                $flash_type = 'err';
            } elseif (true) { // Sauvegarde independante des avertissements non-bloquants
                try {
                    if ($is_edit) {
                        $s = $pdo->prepare('
                            UPDATE randos SET
                                title=:title, slug=:slug, summary=:summary,
                                intro_text=:intro, description=:description,
                                cover_image=:cover, secteur=:secteur, commune=:commune, communes_json=:communes_json,
                                why_text=:why_text, recommended_seasons=:recommended_seasons,
                                season_id=:season, status=:status, published_at=:pub,
                                distance_km=:dist, duration_min=:dur, difficulty=:diff,
                                nature_score=:nature, patrimoine_score=:patrimoine, famille_score=:famille, photo_score=:photo,
                                start_point=:sp, gps_lat=:lat, gps_lng=:lng,
                                parking=:parking, accessibility=:access, gpx_url=:gpx,
                                gpx_file=:gpx_file,
                                meta_title=:mtitle, meta_description=:mdesc
                            WHERE id=:id
                        ');
                        $s->execute([
                            ':title'       => $f_title,
                            ':slug'        => $f_slug,
                            ':summary'     => $f_summary      ?: null,
                            ':intro'       => $f_intro        ?: null,
                            ':description' => $f_description  ?: null,
                            ':cover'       => $cover_path     ?: null,
                            ':secteur'     => $f_secteur,
                            ':commune'     => $f_commune       ?: null,
                            ':communes_json'=> $f_communes_json,
                            ':why_text'    => $f_why_text     ?: null,
                            ':recommended_seasons'=> $f_recommended_json,
                            ':season'      => $f_season_id,
                            ':status'      => $f_status,
                            ':pub'         => $published_at,
                            ':dist'        => $f_distance,
                            ':dur'         => $f_duration,
                            ':diff'        => $f_difficulty,
                            ':nature'      => $f_nature,
                            ':patrimoine'  => $f_patrimoine,
                            ':famille'     => $f_famille,
                            ':photo'       => $f_photo,
                            ':sp'          => $f_start         ?: null,
                            ':lat'         => $f_gps_lat,
                            ':lng'         => $f_gps_lng,
                            ':parking'     => $f_parking       ?: null,
                            ':access'      => $f_access        ?: null,
                            ':gpx'         => $f_gpx           ?: null,
                            ':gpx_file'    => $f_gpx_file      ?? ($rando['gpx_file'] ?? null),
                            ':mtitle'      => $f_meta_title    ?: null,
                            ':mdesc'       => $f_meta_desc     ?: null,
                            ':id'          => $rando['id'],
                        ]);
                        $saved_id = (int)$rando['id'];
                    } else {
                        $user = current_user();
                        $uid  = !empty($user['id']) ? (int)$user['id'] : null;
                        $s    = $pdo->prepare('
                            INSERT INTO randos
                                (title, slug, summary, intro_text, description,
                                 cover_image, secteur, commune, communes_json,
                                 why_text, recommended_seasons,
                                 season_id, status, published_at,
                                 distance_km, duration_min, difficulty,
                                 nature_score, patrimoine_score, famille_score, photo_score,
                                 start_point, gps_lat, gps_lng,
                                 parking, accessibility, gpx_url, gpx_file,
                                 meta_title, meta_description, created_by)
                            VALUES
                                (:title, :slug, :summary, :intro, :description,
                                 :cover, :secteur, :commune, :communes_json,
                                 :why_text, :recommended_seasons,
                                 :season, :status, :pub,
                                 :dist, :dur, :diff,
                                 :nature, :patrimoine, :famille, :photo,
                                 :sp, :lat, :lng,
                                 :parking, :access, :gpx, :gpx_file,
                                 :mtitle, :mdesc, :uid)
                        ');
                        $s->execute([
                            ':title'       => $f_title,
                            ':slug'        => $f_slug,
                            ':summary'     => $f_summary      ?: null,
                            ':intro'       => $f_intro        ?: null,
                            ':description' => $f_description  ?: null,
                            ':cover'       => $cover_path     ?: null,
                            ':secteur'     => $f_secteur,
                            ':commune'     => $f_commune       ?: null,
                            ':communes_json'=> $f_communes_json,
                            ':why_text'    => $f_why_text     ?: null,
                            ':recommended_seasons'=> $f_recommended_json,
                            ':season'      => $f_season_id,
                            ':status'      => $f_status,
                            ':pub'         => $published_at,
                            ':dist'        => $f_distance,
                            ':dur'         => $f_duration,
                            ':diff'        => $f_difficulty,
                            ':nature'      => $f_nature,
                            ':patrimoine'  => $f_patrimoine,
                            ':famille'     => $f_famille,
                            ':photo'       => $f_photo,
                            ':sp'          => $f_start         ?: null,
                            ':lat'         => $f_gps_lat,
                            ':lng'         => $f_gps_lng,
                            ':parking'     => $f_parking       ?: null,
                            ':access'      => $f_access        ?: null,
                            ':gpx'         => $f_gpx           ?: null,
                            ':gpx_file'    => $f_gpx_file      ?? null,
                            ':mtitle'      => $f_meta_title    ?: null,
                            ':mdesc'       => $f_meta_desc     ?: null,
                            ':uid'         => $uid,
                        ]);
                        $saved_id = (int)$pdo->lastInsertId();
                    }
                    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                    header('Location: ' . $base . '/admin/rando-edit.php?id=' . $saved_id . '&saved=1');
                    exit;
                } catch (PDOException $e) {
                    $flash      = 'Erreur sauvegarde : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            }

        // ────────────────────────────────────────────────────
        // REMOVE COVER
        // ────────────────────────────────────────────────────
        } elseif ($action === 'remove_cover' && $is_edit) {
            try {
                $old = $rando['cover_image'] ?? null;
                if ($old && defined('BASE_PATH')) {
                    $full = rtrim(BASE_PATH, '/') . '/' . ltrim($old, '/');
                    if (is_file($full)) @unlink($full);
                }
                $pdo->prepare('UPDATE randos SET cover_image=NULL WHERE id=:id')
                    ->execute([':id' => $rando['id']]);
                $rando['cover_image'] = null;
                $flash      = 'Image de couverture supprim&eacute;e.';
                $flash_type = 'ok';
            } catch (PDOException $e) {
                $flash      = 'Erreur suppression cover.';
                $flash_type = 'err';
            }

        // ────────────────────────────────────────────────────
        // REMOVE GPX
        // ────────────────────────────────────────────────────
        } elseif ($action === 'remove_gpx' && $is_edit) {
            try {
                $old_gpx = $rando['gpx_file'] ?? null;
                if ($old_gpx && defined('BASE_PATH')) {
                    $full_gpx = rtrim(BASE_PATH, '/') . '/' . ltrim($old_gpx, '/');
                    if (is_file($full_gpx)) @unlink($full_gpx);
                }
                $pdo->prepare('UPDATE randos SET gpx_file=NULL WHERE id=:id')
                    ->execute([':id' => $rando['id']]);
                $rando['gpx_file'] = null;
                $flash      = 'Fichier GPX supprim&eacute;.';
                $flash_type = 'ok';
            } catch (PDOException $e) {
                $flash      = 'Erreur suppression GPX.';
                $flash_type = 'err';
            }

        // ────────────────────────────────────────────────────
        // ADD BLOCK
        // ────────────────────────────────────────────────────
        } elseif ($action === 'add_block' && $is_edit) {
            $b_type = in_array($_POST['block_type'] ?? '', $block_types_allowed, true)
                      ? $_POST['block_type'] : 'text';

            $b_content = '{}';
            $upload_err = '';

            switch ($b_type) {
                case 'text':
                    $b_content = json_encode([
                        'heading' => safe_input($_POST['b_heading'] ?? '', 255),
                        'body'    => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'image':
                    $src = '';
                    if (!empty($_FILES['b_image_file']['tmp_name'])) {
                        $up = upload_editorial_image($_FILES['b_image_file'], 'randos');
                        if ($up['ok']) $src = $up['path'];
                        else           $upload_err = $up['error'];
                    }
                    if (empty($upload_err)) {
                        $b_content = json_encode([
                            'src'     => $src,
                            'caption' => safe_input($_POST['b_caption'] ?? '', 255),
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;

                case 'gallery':
                    // Traiter chaque fichier uploade
                    $images = [];
                    if (!empty($_FILES['b_gallery_files']['tmp_name'])) {
                        foreach ($_FILES['b_gallery_files']['tmp_name'] as $i => $tmp) {
                            if (empty($tmp)) continue;
                            $file_entry = [
                                'name'     => $_FILES['b_gallery_files']['name'][$i]     ?? '',
                                'type'     => $_FILES['b_gallery_files']['type'][$i]     ?? '',
                                'tmp_name' => $tmp,
                                'error'    => $_FILES['b_gallery_files']['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                                'size'     => $_FILES['b_gallery_files']['size'][$i]     ?? 0,
                            ];
                            $up = upload_editorial_image($file_entry, 'randos');
                            if ($up['ok']) $images[] = $up['path'];
                            else           $upload_err = $up['error'];
                        }
                    }
                    if (empty($upload_err)) {
                        $b_content = json_encode([
                            'images'  => $images,
                            'caption' => safe_input($_POST['b_caption'] ?? '', 255),
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;

                case 'conseil':
                    $b_content = json_encode([
                        'body' => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'info':
                    $b_content = json_encode([
                        'heading' => safe_input($_POST['b_heading'] ?? '', 255),
                        'body'    => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'quote':
                    $b_content = json_encode([
                        'text'   => $_POST['b_body']   ?? '',
                        'source' => safe_input($_POST['b_source'] ?? '', 255),
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'recit':
                    $b_content = json_encode([
                        'heading' => safe_input($_POST['b_heading'] ?? 'Le récit Zone85', 255),
                        'body'    => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'treasure':
                    $src = $existing_content['src'] ?? '';
                    if (!empty($_FILES['b_image_file']['tmp_name'])) {
                        $up = upload_editorial_image($_FILES['b_image_file'], 'randos');
                        if ($up['ok']) {
                            if (!empty($src) && defined('BASE_PATH')) {
                                $old_f = rtrim(BASE_PATH, '/') . '/' . ltrim($src, '/');
                                if (is_file($old_f)) @unlink($old_f);
                            }
                            $src = $up['path'];
                        } else {
                            $upload_err = $up['error'];
                        }
                    }
                    if (empty($upload_err)) {
                        $b_content = json_encode([
                            'title' => safe_input($_POST['b_heading'] ?? '', 255),
                            'body'  => $_POST['b_body'] ?? '',
                            'src'   => $src,
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;

                case 'recit':
                    $b_content = json_encode([
                        'heading' => safe_input($_POST['b_heading'] ?? 'Le récit Zone85', 255),
                        'body'    => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'treasure':
                    $src = '';
                    if (!empty($_FILES['b_image_file']['tmp_name'])) {
                        $up = upload_editorial_image($_FILES['b_image_file'], 'randos');
                        if ($up['ok']) $src = $up['path'];
                        else           $upload_err = $up['error'];
                    }
                    if (empty($upload_err)) {
                        $b_content = json_encode([
                            'title' => safe_input($_POST['b_heading'] ?? '', 255),
                            'body'  => $_POST['b_body'] ?? '',
                            'src'   => $src,
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;
            }

            if ($upload_err) {
                $flash      = 'Erreur upload : ' . htmlspecialchars($upload_err, ENT_QUOTES, 'UTF-8');
                $flash_type = 'err';
            } else {
                try {
                    $sm = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) AS mx FROM rando_blocks WHERE rando_id=:rid');
                    $sm->execute([':rid' => $rando['id']]);
                    $max_order = (int)($sm->fetch()['mx'] ?? 0);
                    $pdo->prepare('INSERT INTO rando_blocks (rando_id, type, content, sort_order) VALUES (:rid,:t,:c,:so)')
                        ->execute([':rid' => $rando['id'], ':t' => $b_type, ':c' => $b_content, ':so' => $max_order + 1]);
                    $flash      = 'Bloc ajout&eacute;.';
                    $flash_type = 'ok';
                    $blocks     = reload_blocks($pdo, $rando['id']);
                } catch (PDOException $e) {
                    $flash      = 'Erreur ajout bloc : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $flash_type = 'err';
                }
            }

        // ────────────────────────────────────────────────────
        // EDIT BLOCK
        // ────────────────────────────────────────────────────
        } elseif ($action === 'edit_block' && $is_edit) {
            $b_id   = (int)($_POST['block_id'] ?? 0);
            $b_type = $_POST['block_type'] ?? 'text';

            // Charger le bloc existant pour conserver les donnees non modifiees
            $existing_content = [];
            try {
                $se = $pdo->prepare('SELECT type, content FROM rando_blocks WHERE id=:id AND rando_id=:rid LIMIT 1');
                $se->execute([':id' => $b_id, ':rid' => $rando['id']]);
                $existing = $se->fetch();
                if ($existing) {
                    $b_type           = $existing['type'];
                    $existing_content = json_decode($existing['content'] ?? '{}', true) ?: [];
                }
            } catch (PDOException $e) {}

            $upload_err = '';
            $b_content  = '{}';

            switch ($b_type) {
                case 'text':
                    $b_content = json_encode([
                        'heading' => safe_input($_POST['b_heading'] ?? '', 255),
                        'body'    => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'image':
                    $src = $existing_content['src'] ?? '';
                    if (!empty($_FILES['b_image_file']['tmp_name'])) {
                        $up = upload_editorial_image($_FILES['b_image_file'], 'randos');
                        if ($up['ok']) {
                            // Supprimer l'ancienne image si presente
                            if (!empty($src) && defined('BASE_PATH')) {
                                $old_f = rtrim(BASE_PATH, '/') . '/' . ltrim($src, '/');
                                if (is_file($old_f)) @unlink($old_f);
                            }
                            $src = $up['path'];
                        } else {
                            $upload_err = $up['error'];
                        }
                    }
                    if (empty($upload_err)) {
                        $b_content = json_encode([
                            'src'     => $src,
                            'caption' => safe_input($_POST['b_caption'] ?? '', 255),
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;

                case 'gallery':
                    $images = $existing_content['images'] ?? [];

                    // Supprimer des images individuelles
                    $remove_idx = isset($_POST['gallery_remove']) ? array_map('intval', (array)$_POST['gallery_remove']) : [];
                    if ($remove_idx) {
                        foreach ($remove_idx as $ri) {
                            if (isset($images[$ri])) {
                                if (defined('BASE_PATH')) {
                                    $old_f = rtrim(BASE_PATH, '/') . '/' . ltrim($images[$ri], '/');
                                    if (is_file($old_f)) @unlink($old_f);
                                }
                                unset($images[$ri]);
                            }
                        }
                        $images = array_values($images);
                    }

                    // Ajouter de nouvelles images
                    if (!empty($_FILES['b_gallery_files']['tmp_name'])) {
                        foreach ($_FILES['b_gallery_files']['tmp_name'] as $i => $tmp) {
                            if (empty($tmp)) continue;
                            $file_entry = [
                                'name'     => $_FILES['b_gallery_files']['name'][$i]     ?? '',
                                'type'     => $_FILES['b_gallery_files']['type'][$i]     ?? '',
                                'tmp_name' => $tmp,
                                'error'    => $_FILES['b_gallery_files']['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                                'size'     => $_FILES['b_gallery_files']['size'][$i]     ?? 0,
                            ];
                            $up = upload_editorial_image($file_entry, 'randos');
                            if ($up['ok']) $images[] = $up['path'];
                            else           $upload_err = $up['error'];
                        }
                    }

                    if (empty($upload_err)) {
                        $b_content = json_encode([
                            'images'  => array_values($images),
                            'caption' => safe_input($_POST['b_caption'] ?? '', 255),
                        ], JSON_UNESCAPED_UNICODE);
                    }
                    break;

                case 'conseil':
                    $b_content = json_encode([
                        'body' => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'info':
                    $b_content = json_encode([
                        'heading' => safe_input($_POST['b_heading'] ?? '', 255),
                        'body'    => $_POST['b_body'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);
                    break;

                case 'quote':
                    $b_content = json_encode([
                        'text'   => $_POST['b_body']   ?? '',
                        'source' => safe_input($_POST['b_source'] ?? '', 255),
                    ], JSON_UNESCAPED_UNICODE);
                    break;
            }

            if ($upload_err) {
                $flash      = 'Erreur upload : ' . htmlspecialchars($upload_err, ENT_QUOTES, 'UTF-8');
                $flash_type = 'err';
            } elseif ($b_id > 0) {
                try {
                    $pdo->prepare('UPDATE rando_blocks SET content=:c WHERE id=:id AND rando_id=:rid')
                        ->execute([':c' => $b_content, ':id' => $b_id, ':rid' => $rando['id']]);
                    $flash      = 'Bloc mis &agrave; jour.';
                    $flash_type = 'ok';
                    $blocks     = reload_blocks($pdo, $rando['id']);
                } catch (PDOException $e) {
                    $flash      = 'Erreur modification bloc.';
                    $flash_type = 'err';
                }
            }

        // ────────────────────────────────────────────────────
        // DELETE BLOCK
        // ────────────────────────────────────────────────────
        } elseif ($action === 'delete_block' && $is_edit) {
            $b_id = (int)($_POST['block_id'] ?? 0);
            if ($b_id > 0) {
                try {
                    // Nettoyer fichiers images avant delete
                    $sd = $pdo->prepare('SELECT type, content FROM rando_blocks WHERE id=:id AND rando_id=:rid LIMIT 1');
                    $sd->execute([':id' => $b_id, ':rid' => $rando['id']]);
                    $del_bloc = $sd->fetch();
                    if ($del_bloc && defined('BASE_PATH')) {
                        $dc = json_decode($del_bloc['content'] ?? '{}', true) ?: [];
                        if ($del_bloc['type'] === 'image' && !empty($dc['src'])) {
                            $f = rtrim(BASE_PATH, '/') . '/' . ltrim($dc['src'], '/');
                            if (is_file($f)) @unlink($f);
                        } elseif ($del_bloc['type'] === 'gallery' && !empty($dc['images'])) {
                            foreach ($dc['images'] as $img_path) {
                                $f = rtrim(BASE_PATH, '/') . '/' . ltrim($img_path, '/');
                                if (is_file($f)) @unlink($f);
                            }
                        }
                    }
                    $pdo->prepare('DELETE FROM rando_blocks WHERE id=:id AND rando_id=:rid')
                        ->execute([':id' => $b_id, ':rid' => $rando['id']]);
                    $flash      = 'Bloc supprim&eacute;.';
                    $flash_type = 'ok';
                    $blocks     = reload_blocks($pdo, $rando['id']);
                } catch (PDOException $e) {
                    $flash      = 'Erreur suppression bloc.';
                    $flash_type = 'err';
                }
            }

        // ────────────────────────────────────────────────────
        // MOVE UP / MOVE DOWN
        // ────────────────────────────────────────────────────
        } elseif (in_array($action, ['move_up', 'move_down'], true) && $is_edit) {
            $b_id = (int)($_POST['block_id'] ?? 0);
            if ($b_id > 0) {
                try {
                    // Recuperer ordre actuel
                    $all = $pdo->prepare('SELECT id, sort_order FROM rando_blocks WHERE rando_id=:rid ORDER BY sort_order ASC, id ASC');
                    $all->execute([':rid' => $rando['id']]);
                    $rows = $all->fetchAll();
                    $idx  = -1;
                    foreach ($rows as $k => $r) {
                        if ((int)$r['id'] === $b_id) { $idx = $k; break; }
                    }
                    $swap_idx = ($action === 'move_up') ? $idx - 1 : $idx + 1;
                    if ($idx >= 0 && isset($rows[$swap_idx])) {
                        $so1 = $rows[$idx]['sort_order'];
                        $so2 = $rows[$swap_idx]['sort_order'];
                        // Si sort_order identiques, on utilise la position
                        if ($so1 === $so2) { $so1 = $idx; $so2 = $swap_idx; }
                        $upd = $pdo->prepare('UPDATE rando_blocks SET sort_order=:so WHERE id=:id AND rando_id=:rid');
                        $upd->execute([':so' => $so2, ':id' => $rows[$idx]['id'],     ':rid' => $rando['id']]);
                        $upd->execute([':so' => $so1, ':id' => $rows[$swap_idx]['id'], ':rid' => $rando['id']]);
                    }
                    $blocks = reload_blocks($pdo, $rando['id']);
                    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                    header('Location: ' . $base . '/admin/rando-edit.php?id=' . $rando['id'] . '&saved=1#blocs');
                    exit;
                } catch (PDOException $e) {
                    $flash      = 'Erreur r&eacute;ordonnancement.';
                    $flash_type = 'err';
                }
            }
        }
    }
}

// ── Valeurs formulaire (POST prioritaire, puis rando, puis defaut) ──
$v_title      = $_POST['title']          ?? ($rando['title']          ?? '');
$v_slug       = $_POST['slug']           ?? ($rando['slug']           ?? '');
$v_summary    = $_POST['summary']        ?? ($rando['summary']        ?? '');
$v_cover      = $rando['cover_image']    ?? '';
$v_secteur    = $_POST['secteur']        ?? ($rando['secteur']        ?? 'bocage');
$v_commune    = $_POST['commune']        ?? ($rando['commune']        ?? '');
$v_communes_raw = $_POST['communes_json'] ?? '';
if ($v_communes_raw === '' && !empty($rando['communes_json'])) {
    $decoded_communes = json_decode($rando['communes_json'], true);
    if (is_array($decoded_communes)) $v_communes_raw = implode('\n', $decoded_communes);
}
$v_why_text = $_POST['why_text'] ?? ($rando['why_text'] ?? '');
$v_recommended = $_POST['recommended_seasons'] ?? [];
if (empty($v_recommended) && !empty($rando['recommended_seasons'])) {
    $decoded_reco = json_decode($rando['recommended_seasons'], true);
    if (is_array($decoded_reco)) $v_recommended = $decoded_reco;
}
$v_season_id  = isset($_POST['season_id']) ? (int)$_POST['season_id'] : (int)($rando['season_id'] ?? 0);
$v_status     = $_POST['status']         ?? ($rando['status']         ?? 'draft');
$v_distance   = $_POST['distance_km']    ?? ($rando['distance_km']    ?? '');
$v_duration   = isset($_POST['duration_min']) ? (int)$_POST['duration_min'] : (int)($rando['duration_min'] ?? 0);
$v_difficulty = $_POST['difficulty']     ?? ($rando['difficulty']     ?? 'facile');
$v_nature     = $_POST['nature_score']     ?? ($rando['nature_score']     ?? '');
$v_patrimoine = $_POST['patrimoine_score'] ?? ($rando['patrimoine_score'] ?? '');
$v_famille    = $_POST['famille_score']    ?? ($rando['famille_score']    ?? '');
$v_photo      = $_POST['photo_score']      ?? ($rando['photo_score']      ?? '');
$v_start      = $_POST['start_point']    ?? ($rando['start_point']    ?? '');
$v_lat        = $_POST['gps_lat']        ?? ($rando['gps_lat']        ?? '');
$v_lng        = $_POST['gps_lng']        ?? ($rando['gps_lng']        ?? '');
$v_parking    = $_POST['parking']        ?? ($rando['parking']        ?? '');
$v_access     = $_POST['accessibility']  ?? ($rando['accessibility']  ?? '');
$v_gpx        = $_POST['gpx_url']        ?? ($rando['gpx_url']        ?? '');
$v_meta_title = $_POST['meta_title']     ?? ($rando['meta_title']     ?? '');
$v_meta_desc  = $_POST['meta_description'] ?? ($rando['meta_description'] ?? '');
$v_intro      = $_POST['intro_text']     ?? ($rando['intro_text']     ?? '');
$v_description= $_POST['description']    ?? ($rando['description']    ?? '');

// Date publication
$v_pub_at = '';
if (!empty($rando['published_at'])) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $rando['published_at']);
    if ($dt) $v_pub_at = $dt->format('Y-m-d\TH:i');
}
if (!empty($_POST['published_at'])) $v_pub_at = $_POST['published_at'];

// ── Helpers HTML blocs ──────────────────────────────────────
function block_type_icon(string $type): string {
    return match($type) {
        'text'    => '&#x1F4DD;',
        'image'   => '&#x1F4F7;',
        'gallery' => '&#x1F5BC;',
        'conseil' => '&#x1F3AF;',
        'info'    => 'ℹ️',
        'quote'   => '&#x1F4AC;',
        'recit'   => '&#x1F4DC;',
        'treasure'=> '&#x2B50;',
        default   => '&#x1F9F1;',
    };
}

function block_type_label(string $type): string {
    return match($type) {
        'text'    => 'Texte',
        'image'   => 'Image',
        'gallery' => 'Galerie',
        'conseil' => 'Conseil Zone85',
        'info'    => 'A savoir',
        'quote'   => 'Citation',
        'recit'   => 'Récit Zone85',
        'treasure'=> 'Trésor du parcours',
        default   => htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
    };
}

function block_preview(array $block): string {
    $d = json_decode($block['content'] ?? '{}', true) ?: [];
    if ($block['type'] === 'image') {
        return '[Image] ' . ($d['caption'] ?? '');
    }
    if ($block['type'] === 'gallery') {
        $n = count($d['images'] ?? []);
        return '[Galerie — ' . $n . ' photo' . ($n > 1 ? 's' : '') . '] ' . ($d['caption'] ?? '');
    }
    $text = $d['heading'] ?? $d['body'] ?? $d['text'] ?? '';
    $text = strip_tags((string)$text);
    return mb_strlen($text) > 90 ? mb_substr($text, 0, 90) . '...' : $text;
}

// ── JS inline ───────────────────────────────────────────────
$admin_scripts = <<<'JS'
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
// ── Slugify ────────────────────────────────────────────────
function slugify(text) {
    var map = {
        'a':['a','à','â','ä','á','ã'],
        'e':['e','è','é','ê','ë'],
        'i':['i','î','ï','í','ì'],
        'o':['o','ô','ö','ó','ò'],
        'u':['u','ù','û','ü','ú'],
        'c':['c','ç'],
        'n':['n','ñ'],
        'y':['y','ý','ÿ'],
        'oe':['œ'],
        'ae':['æ']
    };
    var s = text.toLowerCase();
    for (var rep in map) {
        map[rep].forEach(function(ch) {
            s = s.split(ch).join(rep);
        });
    }
    return s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

var titleEl   = document.getElementById('f_title');
var slugEl    = document.getElementById('f_slug');
var slugLocked = slugEl && slugEl.value.length > 0;

if (titleEl && slugEl) {
    titleEl.addEventListener('input', function() {
        if (!slugLocked) slugEl.value = slugify(this.value);
    });
    slugEl.addEventListener('input', function() { slugLocked = this.value.length > 0; });
    slugEl.addEventListener('blur',  function() { this.value = slugify(this.value || ''); });
}

// ── Compteur resume ────────────────────────────────────────
(function() {
    var el  = document.getElementById('f_summary');
    var cnt = document.getElementById('summary_count');
    if (!el || !cnt) return;
    function upd() {
        var n = el.value.length;
        cnt.textContent = n + '/300';
        cnt.style.color = n > 300 ? '#c0392b' : '#6b7f96';
    }
    el.addEventListener('input', upd);
    upd();
})();

// ── Compteurs SEO ──────────────────────────────────────────
['meta_title','meta_description'].forEach(function(name) {
    var el  = document.getElementById('f_' + name);
    var cnt = document.getElementById('cnt_' + name);
    var max = name === 'meta_title' ? 255 : 500;
    if (!el || !cnt) return;
    function upd() {
        var n = el.value.length;
        cnt.textContent = n + '/' + max;
        cnt.style.color = n > max ? '#c0392b' : '#6b7f96';
    }
    el.addEventListener('input', upd);
    upd();
});

// ── Onglets (formulaire principal) ─────────────────────────
(function() {
    var tabs   = document.querySelectorAll('.rando-tab-btn');
    var panels = document.querySelectorAll('.rando-tab-panel');

    function showTab(target) {
        tabs.forEach(function(t) {
            var on = t.dataset.tab === target;
            t.classList.toggle('rando-tab-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function(p) {
            p.style.display = p.dataset.tab === target ? '' : 'none';
        });
    }

    tabs.forEach(function(t) {
        t.addEventListener('click', function(e) {
            e.preventDefault();
            showTab(this.dataset.tab);
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('tab', this.dataset.tab);
                history.replaceState(null, '', u.toString());
            } catch(err) {}
        });
    });

    try {
        var p = new URLSearchParams(window.location.search);
        showTab(p.get('tab') || 'infos');
    } catch(err) {
        showTab('infos');
    }
})();

// ── Apercu cover (input file) ──────────────────────────────
(function() {
    var inp = document.getElementById('cover_file_input');
    var prv = document.getElementById('cover_new_preview');
    if (!inp || !prv) return;
    inp.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                prv.src = e.target.result;
                prv.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            prv.src = '';
            prv.style.display = 'none';
        }
    });
})();

// ── Sélecteur type de bloc (ajout) ────────────────────────
function selectBlockType(type) {
    document.querySelectorAll('.block-type-btn').forEach(function(btn) {
        btn.classList.toggle('block-type-btn-active', btn.dataset.btype === type);
    });
    document.querySelectorAll('.block-form-fields').forEach(function(el) {
        el.style.display = el.dataset.btype === type ? '' : 'none';
    });
    var inp = document.getElementById('add_block_type');
    if (inp) inp.value = type;
}

// ── Toggle formulaire edition bloc ────────────────────────
function toggleEditBlock(blockId) {
    var el = document.getElementById('edit-block-' + blockId);
    if (!el) return;
    var isOpen = el.style.display !== 'none';
    // Fermer tous les autres
    document.querySelectorAll('.edit-block-panel').forEach(function(p) {
        p.style.display = 'none';
    });
    if (!isOpen) {
        el.style.display = '';
        el.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }
}

// ── Confirmation suppression ───────────────────────────────
function confirmDelete(msg) {
    return confirm(msg || 'Supprimer ce bloc ?');
}

// Feedback upload médias désactivé ici : overlay unique en bas de page.

// Aperçu couverture : listener unique déjà présent plus haut.
</script>
JS;

require_once __DIR__ . '/_admin-header.php';
?>
<style>
/* ── Onglets ──────────────────────────────────────────────── */
.rando-tabs {
    display: flex; gap: 4px; flex-wrap: wrap;
    border-bottom: 2px solid #e8e2db;
    margin-bottom: 24px;
}
.rando-tab-btn {
    padding: 10px 18px; border-radius: 8px 8px 0 0;
    font-size: .82rem; font-weight: 700; cursor: pointer;
    border: none; background: transparent;
    color: #6b7f96; font-family: 'Inter', sans-serif;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px; transition: color .15s, border-color .15s;
}
.rando-tab-btn:hover { color: #0c1e2e; }
.rando-tab-active {
    color: #ea5649 !important;
    border-bottom-color: #ea5649 !important;
    background: rgba(234,86,73,.05);
}

/* ── Blocs cartes ─────────────────────────────────────────── */
.block-card {
    background: #fff; border: 1.5px solid var(--beige-dark, #e8e2db); border-radius: 10px;
    padding: 14px 18px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px;
}
.block-card-icon { font-size: 1.3rem; flex-shrink: 0; width: 32px; text-align: center; }
.block-card-info { flex: 1; min-width: 0; }
.block-card-type {
    font-size: .66rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .1em; color: var(--text-muted, #6b7f96); margin-bottom: 2px;
}
.block-card-preview {
    font-size: .84rem; color: var(--text-mid, #3d5166); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.block-card-actions { display: flex; gap: 6px; flex-shrink: 0; }
.block-card-actions button, .block-card-actions .btn-block-del {
    padding: 5px 9px; border-radius: 6px; border: 1px solid #d0cbc5;
    font-size: .72rem; font-weight: 700; cursor: pointer;
    background: #fff; color: #3d5166; font-family: 'Inter', sans-serif;
    transition: background .12s; line-height: 1;
    display: inline-flex; align-items: center;
}
.block-card-actions button:hover { background: #f0ece7; }
.btn-block-del { color: #c0392b !important; border-color: rgba(192,57,43,.25) !important; text-decoration: none; }
.btn-block-del:hover { background: rgba(192,57,43,.07) !important; }

/* ── Sélecteur type ───────────────────────────────────────── */
.block-type-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
    margin-bottom: 18px;
}
.block-type-btn {
    padding: 12px; border: 1.5px solid #e8e2db; border-radius: 8px;
    background: #f8f4ef; cursor: pointer; text-align: center; transition: all .15s;
    font-family: 'Inter', sans-serif; font-size: .82rem; font-weight: 700;
    color: #3d5166; line-height: 1.4;
}
.block-type-btn:hover, .block-type-btn-active {
    border-color: #ea5649; background: rgba(234,86,73,.06); color: #c0392b;
}

/* ── Panel edition bloc ───────────────────────────────────── */
.edit-block-panel {
    display: none; background: #faf7f4;
    border: 1.5px dashed #d0cbc5; border-radius: 10px;
    padding: 18px; margin-top: 8px; margin-bottom: 12px;
}

/* ── Galerie miniatures ───────────────────────────────────── */
.gallery-thumbs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
.gallery-thumb  { position: relative; }
.gallery-thumb img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; display: block; }
.gallery-thumb label {
    position: absolute; top: 3px; right: 3px;
    background: rgba(192,57,43,.85); color: #fff;
    width: 18px; height: 18px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; font-size: .6rem; cursor: pointer;
    font-weight: 900;
}
.gallery-thumb input[type=checkbox] { display: none; }

/* ── Cover preview ────────────────────────────────────────── */
.cover-preview-wrap img {
    margin-top: 10px; max-width: 360px; max-height: 200px;
    border-radius: 8px; object-fit: cover; display: block;
}

@media (max-width: 600px) {
    .rando-tab-btn { padding: 8px 12px; font-size: .75rem; }
    .block-card { flex-direction: column; align-items: flex-start; }
    .block-type-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ── Upload feedback ──────────────────────────────────────── */
.upload-status { font-size:.78rem; font-weight:600; padding:6px 10px;
  border-radius:6px; margin-top:6px; }
.upload-status-info { background:rgba(14,165,233,.08); color:#0369a1;
  border:1px solid rgba(14,165,233,.2); }
.upload-status-ok  { background:rgba(42,157,92,.08); color:#1a7a42;
  border:1px solid rgba(42,157,92,.2); }
.upload-status-err { background:rgba(234,86,73,.08); color:#c0392b;
  border:1px solid rgba(234,86,73,.2); }
</style>

<!-- ── Breadcrumb ─────────────────────────────────────────── -->
<div style="margin-bottom:18px;font-size:.82rem;color:#6b7f96">
    <a href="randos.php" style="color:#6b7f96;text-decoration:none">&larr; Randonn&eacute;es</a>
    <span style="margin:0 8px">/</span>
    <?= $is_edit ? htmlspecialchars($rando['title'] ?? '', ENT_QUOTES, 'UTF-8') : 'Nouvelle rando' ?>
</div>

<!-- ── Page header ───────────────────────────────────────── -->
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            <?= $is_edit ? '&#x270F;&#xFE0F; &Eacute;diter la rando' : '&#x2B; Nouvelle randonn&eacute;e' ?>
        </h1>
        <?php if ($is_edit): ?>
        <p class="adm-page-sub">
            ID&nbsp;#<?= (int)$rando['id'] ?> &mdash;
            Slug&nbsp;: <code style="font-size:.78rem"><?= htmlspecialchars($rando['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
        </p>
        <?php endif; ?>
    </div>
    <div class="adm-page-actions">
        <a href="randos.php" class="btn-adm btn-adm-ghost">Retour &agrave; la liste</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash_type ?>">
    <?= $flash ?>
</div>
<?php endif; ?>

<?php if (!$pdo): ?>
<div class="adm-flash adm-flash-err">Base de donn&eacute;es non disponible.</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════ -->
<!-- ONGLETS navigation                                        -->
<!-- ════════════════════════════════════════════════════════ -->
<nav class="rando-tabs" role="tablist">
    <button class="rando-tab-btn" data-tab="infos"    role="tab" aria-selected="true">
        &#x1F4CB; Informations
    </button>
    <button class="rando-tab-btn" data-tab="parcours" role="tab" aria-selected="false">
        &#x1F9ED; Parcours + SEO
    </button>
</nav>

<!-- ════════════════════════════════════════════════════════ -->
<!-- FORMULAIRE PRINCIPAL (multipart pour upload cover)       -->
<!-- ════════════════════════════════════════════════════════ -->
<form method="post" enctype="multipart/form-data"
      action="rando-edit.php<?= $is_edit ? '?id=' . (int)$rando['id'] : '' ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_rando">

    <!-- ══ ONGLET 1 : INFORMATIONS ══════════════════════════ -->
    <div class="rando-tab-panel" data-tab="infos">
        <div class="adm-card">
            <p class="adm-card-title">Informations g&eacute;n&eacute;rales</p>
            <div class="adm-form-grid">

                <!-- Titre -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_title">Titre <span>*</span></label>
                    <input id="f_title" type="text" name="title" class="adm-input"
                           maxlength="255" required placeholder="Titre de la randonn&eacute;e"
                           value="<?= htmlspecialchars($v_title, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Slug -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_slug">Slug (URL)</label>
                    <input id="f_slug" type="text" name="slug" class="adm-input"
                           maxlength="255" placeholder="genere-depuis-le-titre"
                           value="<?= htmlspecialchars($v_slug, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="adm-hint">G&eacute;n&eacute;r&eacute; depuis le titre. Modifiable manuellement.</span>
                </div>
<!-- Introduction courte -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_intro">Introduction courte</label>
                    <input type="hidden" id="f_intro" name="intro_text" value="">
                    <div id="quill_intro" style="min-height:100px;background:#fff;border-radius:6px"></div>
                    <div class="adm-hint">Accroche forte &mdash; 2 &agrave; 4 lignes. Affich&eacute;e en typographie plus grande.</div>
                </div>

                <!-- Description longue -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_description">Description longue</label>
                    <input type="hidden" id="f_description" name="description" value="">
                    <div id="quill_description" style="min-height:160px;background:#fff;border-radius:6px"></div>
                    <div class="adm-hint">Corps principal de la fiche. Affich&eacute; apr&egrave;s l'introduction.</div>
                </div>

                <!-- Pourquoi cette rando -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_why">Pourquoi faire cette rando ?</label>
                    <input type="hidden" id="f_why" name="why_text" value="">
                    <div id="quill_why" style="min-height:100px;background:#fff;border-radius:6px"></div>
                    <div class="adm-hint">Un court argument de s&eacute;duction. Affich&eacute; dans un bandeau sp&eacute;cial sur la fiche.</div>
                </div>

                <!-- Image de couverture -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label">Image de couverture</label>

                    <?php if (!empty($v_cover)): ?>
                    <!-- Image existante -->
                    <div class="cover-preview-wrap" style="margin-bottom:12px">
                        <img src="<?= htmlspecialchars(media_url($v_cover), ENT_QUOTES, 'UTF-8') ?>"
                             alt="Couverture actuelle">
                        <div style="margin-top:8px;display:flex;gap:10px;align-items:center">
                            <span style="font-size:.78rem;color:#6b7f96">Image actuelle</span>
                            <button type="submit" name="action" value="remove_cover" formnovalidate
                                    onclick="return confirm('Supprimer cette image de couverture ?')"
                                    class="btn-adm btn-adm-danger btn-adm-sm">
                                &#x1F5D1; Supprimer
                            </button>
                        </div>
                    </div>
                    <label class="adm-label" for="cover_file_input">Changer l'image</label>
                    <?php else: ?>
                    <label class="adm-label" for="cover_file_input">Choisir une image</label>
                    <?php endif; ?>

                    <input id="cover_file_input" type="file" name="cover_image_file"
                           accept="image/jpeg,image/png,image/webp" class="adm-input"
                           style="padding:6px">
                    <span class="adm-hint">JPG, PNG ou WebP &mdash; 5 Mo max</span>
                    <img id="cover_new_preview" src="" alt=""
                         style="display:none;margin-top:10px;max-width:360px;max-height:200px;border-radius:8px;object-fit:cover">
                </div>

                <!-- Secteur -->
                <div class="adm-field">
                    <label class="adm-label" for="f_secteur">Secteur <span>*</span></label>
                    <select id="f_secteur" name="secteur" class="adm-select">
                        <option value="bocage"   <?= $v_secteur === 'bocage'   ? 'selected' : '' ?>>Bocage</option>
                        <option value="littoral" <?= $v_secteur === 'littoral' ? 'selected' : '' ?>>Littoral</option>
                        <option value="marais"   <?= $v_secteur === 'marais'   ? 'selected' : '' ?>>Marais</option>
                        <option value="plaine"   <?= $v_secteur === 'plaine'   ? 'selected' : '' ?>>Plaine</option>
                    </select>
                </div>

                <!-- Commune -->
                <div class="adm-field">
                    <label class="adm-label" for="f_commune">Commune</label>
                    <input id="f_commune" type="text" name="commune" class="adm-input"
                           maxlength="150" placeholder="Ex : Saint-Gilles-Croix-de-Vie"
                           value="<?= htmlspecialchars($v_commune, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Communes traversees -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_communes_json">Communes travers&eacute;es</label>
                    <textarea id="f_communes_json" name="communes_json" rows="3" class="adm-textarea"
                        placeholder="Une commune par ligne ou s&eacute;par&eacute;es par des virgules. Ex : La Roche-sur-Yon&#10;Nesmy"><?= htmlspecialchars($v_communes_raw, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="adm-hint">La premi&egrave;re commune reste la commune principale. Les autres serviront aux tampons du Passeport.</div>
                </div>

                <!-- Saison -->
                <div class="adm-field">
                    <label class="adm-label" for="f_season">Saison associ&eacute;e</label>
                    <select id="f_season" name="season_id" class="adm-select">
                        <option value="">— Aucune saison —</option>
                        <?php foreach ($seasons_list as $sea): ?>
                        <option value="<?= (int)$sea['id'] ?>"
                            <?= $v_season_id === (int)$sea['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sea['title'], ENT_QUOTES, 'UTF-8') ?>
                            <?= $sea['status'] === 'active' ? ' ● EN COURS' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Statut -->
                <div class="adm-field">
                    <label class="adm-label" for="f_status">Statut</label>
                    <select id="f_status" name="status" class="adm-select">
                        <option value="draft"     <?= $v_status === 'draft'     ? 'selected' : '' ?>>Brouillon</option>
                        <option value="published" <?= $v_status === 'published' ? 'selected' : '' ?>>Publi&eacute;</option>
                        <option value="archived"  <?= $v_status === 'archived'  ? 'selected' : '' ?>>Archiv&eacute;</option>
                    </select>
                </div>

                <!-- Date publication -->
                <div class="adm-field">
                    <label class="adm-label" for="f_pub_at">Date de publication</label>
                    <input id="f_pub_at" type="datetime-local" name="published_at" class="adm-input"
                           value="<?= htmlspecialchars($v_pub_at, ENT_QUOTES, 'UTF-8') ?>">
                </div>

            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap">
            <a href="randos.php" class="btn-adm btn-adm-ghost">Annuler</a>
            <button type="submit" class="btn-adm btn-adm-primary">
                <?= $is_edit ? '&#x2713; Sauvegarder' : '&#x2B; Cr&eacute;er la rando' ?>
            </button>
        </div>
    </div><!-- /tab infos -->

    <!-- ══ ONGLET 2 : PARCOURS + SEO ════════════════════════ -->
    <div class="rando-tab-panel" data-tab="parcours" style="display:none">

        <div class="adm-card">
            <p class="adm-card-title">Caract&eacute;ristiques du parcours</p>
            <div class="adm-form-grid">

                <!-- Distance -->
                <div class="adm-field">
                    <label class="adm-label" for="f_dist">Distance (km)</label>
                    <input id="f_dist" type="number" name="distance_km" class="adm-input"
                           min="0" max="999" step="0.1" placeholder="Ex : 8.5"
                           value="<?= htmlspecialchars((string)$v_distance, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Duree -->
                <div class="adm-field">
                    <label class="adm-label" for="f_duration">Dur&eacute;e estim&eacute;e</label>
                    <select id="f_duration" name="duration_min" class="adm-select">
                        <option value="">— Non renseign&eacute;e —</option>
                        <?php foreach ($duration_options as $min => $label): ?>
                        <option value="<?= $min ?>" <?= $v_duration === $min ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Difficulte -->
                <div class="adm-field">
                    <label class="adm-label" for="f_difficulty">Difficult&eacute;</label>
                    <select id="f_difficulty" name="difficulty" class="adm-select">
                        <option value="facile"    <?= $v_difficulty === 'facile'    ? 'selected' : '' ?>>&#x2605; Facile</option>
                        <option value="moyen"     <?= $v_difficulty === 'moyen'     ? 'selected' : '' ?>>&#x2605;&#x2605; Moyen</option>
                        <option value="difficile" <?= $v_difficulty === 'difficile' ? 'selected' : '' ?>>&#x2605;&#x2605;&#x2605; Difficile</option>
                        <option value="expert"    <?= $v_difficulty === 'expert'    ? 'selected' : '' ?>>&#x2605;&#x2605;&#x2605;&#x2605; Expert</option>
                    </select>
                </div>



                <!-- Ambiance Zone85 -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label">Ambiance Zone85</label>
                    <div class="adm-hint" style="margin-bottom:10px">Ces notes ne sont pas des avis utilisateurs : elles racontent le profil de la randonnée.</div>
                    <div class="adm-form-grid" style="grid-template-columns:repeat(4,minmax(120px,1fr));gap:12px">
                        <div>
                            <label class="adm-label" style="font-size:.72rem">Nature /5</label>
                            <input type="number" name="nature_score" min="0" max="5" class="adm-input" value="<?= htmlspecialchars((string)$v_nature, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label class="adm-label" style="font-size:.72rem">Patrimoine /5</label>
                            <input type="number" name="patrimoine_score" min="0" max="5" class="adm-input" value="<?= htmlspecialchars((string)$v_patrimoine, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label class="adm-label" style="font-size:.72rem">Famille /5</label>
                            <input type="number" name="famille_score" min="0" max="5" class="adm-input" value="<?= htmlspecialchars((string)$v_famille, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label class="adm-label" style="font-size:.72rem">Photo /5</label>
                            <input type="number" name="photo_score" min="0" max="5" class="adm-input" value="<?= htmlspecialchars((string)$v_photo, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <!-- Saisons conseillees -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label">Saisons conseill&eacute;es</label>
                    <div class="adm-hint" style="margin-bottom:10px">Permet d'afficher la meilleure p&eacute;riode pour faire cette rando.</div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <?php foreach (['printemps'=>'Printemps','ete'=>'&Eacute;t&eacute;','automne'=>'Automne','hiver'=>'Hiver'] as $_sk=>$_sl): ?>
                            <label style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #e2d8cf;border-radius:999px;padding:8px 12px;font-size:.84rem;font-weight:700;color:#0c1e2e">
                                <input type="checkbox" name="recommended_seasons[]" value="<?= $_sk ?>" <?= in_array($_sk, (array)$v_recommended, true) ? 'checked' : '' ?>>
                                <?= $_sl ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Point de depart -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_start">Point de d&eacute;part</label>
                    <input id="f_start" type="text" name="start_point" class="adm-input"
                           maxlength="255" placeholder="Ex : Place de l'&eacute;glise, Saint-Hilaire-de-Riez"
                           value="<?= htmlspecialchars($v_start, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- GPS Lat -->
                <div class="adm-field">
                    <label class="adm-label" for="f_lat">Latitude GPS</label>
                    <input id="f_lat" type="number" name="gps_lat" class="adm-input"
                           min="-90" max="90" step="0.0001"
                           placeholder="Ex : 46.7234"
                           value="<?= htmlspecialchars((string)$v_lat, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- GPS Lng -->
                <div class="adm-field">
                    <label class="adm-label" for="f_lng">Longitude GPS</label>
                    <input id="f_lng" type="number" name="gps_lng" class="adm-input"
                           min="-180" max="180" step="0.0001"
                           placeholder="Ex : -1.8345"
                           value="<?= htmlspecialchars((string)$v_lng, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <!-- Stationnement -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_parking">Stationnement</label>
                    <textarea id="f_parking" name="parking" class="adm-textarea"
                              rows="3" placeholder="Ex : Parking gratuit bord de route D948..."><?= htmlspecialchars($v_parking, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Accessibilite -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_access">Accessibilit&eacute;</label>
                    <textarea id="f_access" name="accessibility" class="adm-textarea"
                              rows="3" placeholder="Ex : Praticable avec poussette jusqu'au km 2..."><?= htmlspecialchars($v_access, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Fichier GPX upload -->
                <div class="adm-field adm-form-full">
                    <label class="adm-label">Fichier GPX</label>

                    <?php if (!empty($rando['gpx_file'])): ?>
                    <div style="background:#f0faf4;border:1px solid #c8e6d0;border-radius:8px;padding:10px 14px;
                        display:flex;align-items:center;gap:10px;margin-bottom:8px">
                        <span style="font-size:1.1rem">&#x1F5FA;</span>
                        <span style="font-size:.84rem;font-weight:600;flex:1">Trac&eacute; GPX disponible</span>
                        <a href="<?= htmlspecialchars(media_url($rando['gpx_file']), ENT_QUOTES, 'UTF-8') ?>" download
                            style="font-size:.78rem;color:#1a7a42;font-weight:700;text-decoration:none">
                            &#x2B07; T&eacute;l&eacute;charger
                        </a>
                        <button type="submit" name="action" value="remove_gpx" formnovalidate
                            onclick="return confirm('Supprimer le fichier GPX ?')"
                            style="background:transparent;border:none;color:#c0392b;font-size:.78rem;font-weight:700;cursor:pointer">
                            &#x1F5D1; Supprimer
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="adm-hint" style="margin-bottom:8px">Aucun fichier GPX pour l'instant.</div>
                    <?php endif; ?>

                    <input type="file" name="gpx_file_upload" accept=".gpx" class="adm-input" style="padding:6px">
                    <div class="adm-hint">Fichier .gpx uniquement. Le trac&eacute; sera disponible en t&eacute;l&eacute;chargement sur la fiche.</div>
                    <input type="hidden" name="gpx_url" value="<?= htmlspecialchars($v_gpx, ENT_QUOTES, 'UTF-8') ?>">
</div>

            </div>
        </div>

        <!-- SEO -->
        <div class="adm-card">
            <p class="adm-card-title">R&eacute;f&eacute;rencement naturel (SEO)</p>
            <div class="adm-form-grid">

                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_meta_title">Meta title</label>
                    <input id="f_meta_title" type="text" name="meta_title" class="adm-input"
                           maxlength="255" placeholder="Titre SEO (60-70 caract&egrave;res recommand&eacute;s)"
                           value="<?= htmlspecialchars($v_meta_title, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="adm-hint" id="cnt_meta_title">0/255</span>
                </div>

                <div class="adm-field adm-form-full">
                    <label class="adm-label" for="f_meta_description">Meta description</label>
                    <textarea id="f_meta_description" name="meta_description" class="adm-textarea"
                              rows="4" maxlength="500"
                              placeholder="Description SEO (150-160 caract&egrave;res recommand&eacute;s)..."><?= htmlspecialchars($v_meta_desc, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <span class="adm-hint" id="cnt_meta_description">0/500</span>
                </div>

            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap">
            <a href="randos.php" class="btn-adm btn-adm-ghost">Annuler</a>
            <button type="submit" class="btn-adm btn-adm-primary">
                <?= $is_edit ? '&#x2713; Sauvegarder' : '&#x2B; Cr&eacute;er la rando' ?>
            </button>
        </div>

    </div><!-- /tab parcours -->

</form><!-- /formulaire principal -->

<!-- ════════════════════════════════════════════════════════ -->
<!-- EDITEUR DE BLOCS (hors formulaire principal)             -->
<!-- ════════════════════════════════════════════════════════ -->
<?php if ($is_edit): ?>
<div id="blocs" style="margin-top:32px">

    <div class="adm-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
            <p class="adm-card-title" style="margin:0">
                &#x1F9F1; Blocs de contenu
                <span style="font-size:.8rem;font-weight:500;color:#6b7f96;margin-left:8px">
                    (<?= count($blocks) ?> bloc<?= count($blocks) !== 1 ? 's' : '' ?>)
                </span>
            </p>
            <button type="button" onclick="toggleAddPanel()" class="btn-adm btn-adm-primary btn-adm-sm">
                &#x2B; Ajouter un bloc
            </button>
        </div>

        <!-- ── Formulaire d'ajout de bloc ─────────────────── -->
        <div id="add-block-panel" style="display:none;margin-bottom:20px">
            <form method="post" enctype="multipart/form-data"
                  action="rando-edit.php?id=<?= (int)$rando['id'] ?>#blocs">
                <?= csrf_field() ?>
                <input type="hidden" name="action"     value="add_block">
                <input type="hidden" name="block_type" id="add_block_type" value="text">

                <div style="background:#f8f4ef;border:1.5px dashed #d0cbc5;border-radius:10px;padding:18px">
                    <p class="adm-card-title" style="margin-bottom:14px">Choisir un type de bloc</p>

                    <!-- Sélecteur visuel -->
                    <div class="block-type-grid">
                        <button type="button" class="block-type-btn block-type-btn-active" data-btype="text"
                                onclick="selectBlockType('text')">
                            &#x1F4DD;<br>Texte
                        </button>
                        <button type="button" class="block-type-btn" data-btype="image"
                                onclick="selectBlockType('image')">
                            &#x1F4F7;<br>Image
                        </button>
                        <button type="button" class="block-type-btn" data-btype="gallery"
                                onclick="selectBlockType('gallery')">
                            &#x1F5BC;<br>Galerie
                        </button>
                        <button type="button" class="block-type-btn" data-btype="conseil"
                                onclick="selectBlockType('conseil')">
                            &#x1F3AF;<br>Conseil Zone85
                        </button>
                        <button type="button" class="block-type-btn" data-btype="info"
                                onclick="selectBlockType('info')">
                            &#x2139;<br>A savoir
                        </button>
                        <button type="button" class="block-type-btn" data-btype="quote"
                                onclick="selectBlockType('quote')">
                            &#x1F4AC;<br>Citation
                        </button>
                        <button type="button" class="block-type-btn" data-btype="recit"
                                onclick="selectBlockType('recit')">
                            &#x1F4DC;<br>Récit Zone85
                        </button>
                        <button type="button" class="block-type-btn" data-btype="treasure"
                                onclick="selectBlockType('treasure')">
                            &#x2B50;<br>Trésor
                        </button>
                    </div>

                    <!-- Champs selon type -->

                    <!-- text -->
                    <div class="block-form-fields adm-form-grid" data-btype="text">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Titre (optionnel)</label>
                            <input type="text" name="b_heading" class="adm-input" maxlength="255"
                                   placeholder="Titre du paragraphe">
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Contenu <span>*</span></label>
                            <textarea name="b_body" class="adm-textarea" rows="6"
                                      placeholder="R&eacute;digez le texte de ce bloc..."></textarea>
                        </div>
                    </div>

                    <!-- image -->
                    <div class="block-form-fields adm-form-grid" data-btype="image" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Photo <span>*</span></label>
                            <input type="file" name="b_image_file" class="adm-input"
                                   accept="image/jpeg,image/png,image/webp" style="padding:6px">
                            <span class="adm-hint">JPG, PNG ou WebP — 5 Mo max</span>
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">L&eacute;gende</label>
                            <input type="text" name="b_caption" class="adm-input" maxlength="255"
                                   placeholder="Description de l'image">
                        </div>
                    </div>

                    <!-- gallery -->
                    <div class="block-form-fields adm-form-grid" data-btype="gallery" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Photos <span>*</span></label>
                            <input type="file" name="b_gallery_files[]" class="adm-input" multiple
                                   accept="image/jpeg,image/png,image/webp" style="padding:6px">
                            <span class="adm-hint">S&eacute;lectionnez plusieurs photos (JPG, PNG, WebP — 5 Mo max chacune)</span>
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">L&eacute;gende de la galerie</label>
                            <input type="text" name="b_caption" class="adm-input" maxlength="255"
                                   placeholder="Description de la galerie">
                        </div>
                    </div>

                    <!-- conseil -->
                    <div class="block-form-fields adm-form-grid" data-btype="conseil" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Conseil <span>*</span></label>
                            <textarea name="b_body" class="adm-textarea" rows="4"
                                      placeholder="Pensez &agrave; emporter de l'eau..."></textarea>
                        </div>
                    </div>

                    <!-- info -->
                    <div class="block-form-fields adm-form-grid" data-btype="info" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Titre (optionnel)</label>
                            <input type="text" name="b_heading" class="adm-input" maxlength="255"
                                   placeholder="Ex : Bonne &agrave; savoir">
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Texte <span>*</span></label>
                            <textarea name="b_body" class="adm-textarea" rows="4"
                                      placeholder="Information importante pour les randonn&eacute;eurs..."></textarea>
                        </div>
                    </div>

                    <!-- quote -->
                    <div class="block-form-fields adm-form-grid" data-btype="quote" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Citation <span>*</span></label>
                            <textarea name="b_body" class="adm-textarea" rows="3"
                                      placeholder="Texte de la citation..."></textarea>
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Source</label>
                            <input type="text" name="b_source" class="adm-input" maxlength="255"
                                   placeholder="Jean Dupont, habitant de Fontenay">
                        </div>
                    </div>



                    <!-- recit -->
                    <div class="block-form-fields adm-form-grid" data-btype="recit" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Titre</label>
                            <input type="text" name="b_heading" class="adm-input" maxlength="255" value="Le récit Zone85">
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Récit <span>*</span></label>
                            <textarea name="b_body" class="adm-textarea" rows="6" placeholder="Racontez une anecdote, une légende, un détail insolite ou l'histoire du lieu..."></textarea>
                        </div>
                    </div>

                    <!-- treasure -->
                    <div class="block-form-fields adm-form-grid" data-btype="treasure" style="display:none">
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Titre du trésor <span>*</span></label>
                            <input type="text" name="b_heading" class="adm-input" maxlength="255" placeholder="Ex : Le vieux pont oublié">
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Description</label>
                            <textarea name="b_body" class="adm-textarea" rows="4" placeholder="Ce qu'il ne faut pas manquer sur le parcours..."></textarea>
                        </div>
                        <div class="adm-field adm-form-full">
                            <label class="adm-label">Photo</label>
                            <input type="file" name="b_image_file" class="adm-input" accept="image/jpeg,image/png,image/webp" style="padding:6px">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end">
                        <button type="button" onclick="toggleAddPanel()"
                                class="btn-adm btn-adm-ghost btn-adm-sm">Annuler</button>
                        <button type="submit" class="btn-adm btn-adm-success btn-adm-sm">
                            &#x2713; Ajouter ce bloc
                        </button>
                    </div>
                </div>
            </form>
        </div><!-- /add-block-panel -->

        <!-- ── Liste des blocs existants ──────────────────── -->
        <?php if (empty($blocks)): ?>
        <div class="adm-empty" style="padding:32px 20px">
            <div class="adm-empty-icon">&#x1F9F1;</div>
            <p>Aucun bloc de contenu. Cliquez sur &laquo;&nbsp;Ajouter un bloc&nbsp;&raquo; pour commencer.</p>
        </div>
        <?php else: ?>
        <?php foreach ($blocks as $bi => $bl):
            $bl_data = json_decode($bl['content'] ?? '{}', true) ?: [];
        ?>
        <div class="block-card">
            <div class="block-card-icon"><?= block_type_icon($bl['type']) ?></div>
            <div class="block-card-info">
                <div class="block-card-type"><?= block_type_label($bl['type']) ?></div>
                <div class="block-card-preview"><?= htmlspecialchars(block_preview($bl), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="block-card-actions">
                <!-- Monter -->
                <?php if ($bi > 0): ?>
                <form method="post" action="rando-edit.php?id=<?= (int)$rando['id'] ?>#blocs" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"   value="move_up">
                    <input type="hidden" name="block_id" value="<?= (int)$bl['id'] ?>">
                    <button type="submit" title="Monter">&#x2191;</button>
                </form>
                <?php endif; ?>
                <!-- Descendre -->
                <?php if ($bi < count($blocks) - 1): ?>
                <form method="post" action="rando-edit.php?id=<?= (int)$rando['id'] ?>#blocs" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"   value="move_down">
                    <input type="hidden" name="block_id" value="<?= (int)$bl['id'] ?>">
                    <button type="submit" title="Descendre">&#x2193;</button>
                </form>
                <?php endif; ?>
                <!-- Modifier -->
                <button type="button" onclick="toggleEditBlock(<?= (int)$bl['id'] ?>)">
                    &#x270F; Modifier
                </button>
                <!-- Supprimer -->
                <form method="post" action="rando-edit.php?id=<?= (int)$rando['id'] ?>#blocs" style="display:inline"
                      onsubmit="return confirmDelete('Supprimer ce bloc d\'auteur ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"   value="delete_block">
                    <input type="hidden" name="block_id" value="<?= (int)$bl['id'] ?>">
                    <button type="submit" class="btn-block-del" title="Supprimer">&#x1F5D1;</button>
                </form>
            </div>
        </div><!-- /block-card -->

        <!-- ── Formulaire edition inline ──────────────────── -->
        <div id="edit-block-<?= (int)$bl['id'] ?>" class="edit-block-panel">
            <form method="post" enctype="multipart/form-data"
                  action="rando-edit.php?id=<?= (int)$rando['id'] ?>#blocs">
                <?= csrf_field() ?>
                <input type="hidden" name="action"     value="edit_block">
                <input type="hidden" name="block_id"   value="<?= (int)$bl['id'] ?>">
                <input type="hidden" name="block_type"  value="<?= htmlspecialchars($bl['type'], ENT_QUOTES, 'UTF-8') ?>">

                <p class="adm-card-title" style="margin-bottom:14px">
                    Modifier &mdash; <?= block_type_icon($bl['type']) ?>
                    <?= block_type_label($bl['type']) ?>
                </p>

                <?php switch ($bl['type']):
                    case 'text': ?>
                <div class="adm-form-grid">
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Titre (optionnel)</label>
                        <input type="text" name="b_heading" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Contenu <span>*</span></label>
                        <textarea name="b_body" class="adm-textarea" rows="6"><?= htmlspecialchars($bl_data['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <?php break; case 'image': ?>
                <div class="adm-form-grid">
                    <?php if (!empty($bl_data['src'])): ?>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Image actuelle</label>
                        <img src="<?= htmlspecialchars(media_url($bl_data['src']), ENT_QUOTES, 'UTF-8') ?>"
                             alt="Image actuelle"
                             style="max-width:200px;max-height:140px;object-fit:cover;border-radius:6px;display:block">
                    </div>
                    <?php endif; ?>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label"><?= !empty($bl_data['src']) ? 'Changer l\'image' : 'Photo *' ?></label>
                        <input type="file" name="b_image_file" class="adm-input"
                               accept="image/jpeg,image/png,image/webp" style="padding:6px">
                        <span class="adm-hint">Laisser vide pour conserver l'image actuelle</span>
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">L&eacute;gende</label>
                        <input type="text" name="b_caption" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['caption'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <?php break; case 'gallery': ?>
                <div class="adm-form-grid">
                    <?php if (!empty($bl_data['images'])): ?>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Photos actuelles (cochez pour supprimer)</label>
                        <div class="gallery-thumbs">
                            <?php foreach ($bl_data['images'] as $gi => $img_path): ?>
                            <div class="gallery-thumb">
                                <img src="<?= htmlspecialchars(media_url($img_path), ENT_QUOTES, 'UTF-8') ?>"
                                     alt="Photo <?= $gi + 1 ?>">
                                <label title="Supprimer cette photo">
                                    <input type="checkbox" name="gallery_remove[]"
                                           value="<?= (int)$gi ?>">
                                    &#x2715;
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <span class="adm-hint">Cochez une vignette pour supprimer la photo</span>
                    </div>
                    <?php endif; ?>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Ajouter des photos</label>
                        <input type="file" name="b_gallery_files[]" class="adm-input" multiple
                               accept="image/jpeg,image/png,image/webp" style="padding:6px">
                        <span class="adm-hint">JPG, PNG ou WebP — 5 Mo max chacune</span>
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">L&eacute;gende de la galerie</label>
                        <input type="text" name="b_caption" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['caption'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <?php break; case 'conseil': ?>
                <div class="adm-form-grid">
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Conseil <span>*</span></label>
                        <textarea name="b_body" class="adm-textarea" rows="4"
                                  placeholder="Pensez &agrave; emporter de l'eau..."><?= htmlspecialchars($bl_data['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <?php break; case 'info': ?>
                <div class="adm-form-grid">
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Titre (optionnel)</label>
                        <input type="text" name="b_heading" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['heading'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Texte <span>*</span></label>
                        <textarea name="b_body" class="adm-textarea" rows="4"><?= htmlspecialchars($bl_data['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <?php break; case 'quote': ?>
                <div class="adm-form-grid">
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Citation <span>*</span></label>
                        <textarea name="b_body" class="adm-textarea" rows="3"><?= htmlspecialchars($bl_data['text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Source</label>
                        <input type="text" name="b_source" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['source'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <?php break; case 'recit': ?>
                <div class="adm-form-grid">
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Titre</label>
                        <input type="text" name="b_heading" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['heading'] ?? 'Le récit Zone85', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Récit <span>*</span></label>
                        <textarea name="b_body" class="adm-textarea" rows="6"><?= htmlspecialchars($bl_data['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <?php break; case 'treasure': ?>
                <div class="adm-form-grid">
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Titre du trésor</label>
                        <input type="text" name="b_heading" class="adm-input" maxlength="255"
                               value="<?= htmlspecialchars($bl_data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Description</label>
                        <textarea name="b_body" class="adm-textarea" rows="4"><?= htmlspecialchars($bl_data['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <?php if (!empty($bl_data['src'])): ?>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Photo actuelle</label>
                        <img src="<?= htmlspecialchars(media_url($bl_data['src']), ENT_QUOTES, 'UTF-8') ?>"
                             alt="Photo actuelle"
                             style="max-width:220px;max-height:150px;object-fit:cover;border-radius:8px;display:block">
                    </div>
                    <?php endif; ?>
                    <div class="adm-field adm-form-full">
                        <label class="adm-label">Changer la photo</label>
                        <input type="file" name="b_image_file" class="adm-input" accept="image/jpeg,image/png,image/webp" style="padding:6px">
                    </div>
                </div>

                <?php break; endswitch; ?>

                <div style="display:flex;gap:10px;margin-top:14px;justify-content:flex-end">
                    <button type="button" onclick="toggleEditBlock(<?= (int)$bl['id'] ?>)"
                            class="btn-adm btn-adm-ghost btn-adm-sm">Annuler</button>
                    <button type="submit" class="btn-adm btn-adm-primary btn-adm-sm">
                        &#x2713; Enregistrer
                    </button>
                </div>
            </form>
        </div><!-- /edit-block-panel -->

        <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /adm-card blocs -->
</div><!-- /#blocs -->
<?php else: ?>
<div class="adm-flash adm-flash-info" style="margin-top:24px">
    &#x1F4A1; Sauvegardez la rando avant d'ajouter des blocs de contenu.
</div>
<?php endif; ?>

<div id="uploadOverlay" class="zone85-upload-overlay" aria-hidden="true">
  <div class="zone85-upload-box">
    <div class="zone85-upload-title">Envoi des médias en cours...</div>
    <div class="zone85-upload-subtitle">Merci de patienter, Zone85 range les fichiers dans la besace.</div>
    <div class="zone85-upload-bar"><span></span></div>
  </div>
</div>

<style>
.zone85-upload-overlay{position:fixed;inset:0;background:rgba(12,30,46,.55);display:none;align-items:center;justify-content:center;z-index:99999;backdrop-filter:blur(3px)}
.zone85-upload-overlay.is-visible{display:flex}
.zone85-upload-box{width:min(420px,90vw);background:#fff;border-radius:18px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center}
.zone85-upload-title{font-weight:900;color:#0c1e2e;font-size:1.05rem;margin-bottom:6px}
.zone85-upload-subtitle{font-size:.85rem;color:#6b7f96;margin-bottom:18px;line-height:1.45}
.zone85-upload-bar{height:10px;border-radius:999px;background:#f0ece7;overflow:hidden}
.zone85-upload-bar span{display:block;height:100%;width:42%;border-radius:999px;background:linear-gradient(90deg,#ea5649,#2a9d5c);animation:zone85Upload 1.15s ease-in-out infinite}
@keyframes zone85Upload{0%{transform:translateX(-110%)}100%{transform:translateX(260%)}}
</style>

<script>
function toggleAddPanel() {
    var el = document.getElementById('add-block-panel');
    if (!el) return;
    el.style.display = el.style.display === 'none' || el.style.display === '' ? 'block' : 'none';
    if (el.style.display === 'block') {
        selectBlockType('text');
        el.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}
(function(){
  var overlay = document.getElementById('uploadOverlay');
  var forms = document.querySelectorAll('form[enctype="multipart/form-data"]');
  forms.forEach(function(form){
    form.addEventListener('submit', function(){
      var hasFile = false;
      form.querySelectorAll('input[type="file"]').forEach(function(input){
        if (input.files && input.files.length > 0) hasFile = true;
      });
      if (hasFile && overlay) {
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden','false');
        form.querySelectorAll('button[type="submit"]').forEach(function(btn){
          btn.disabled = true;
          btn.dataset.originalText = btn.innerHTML;
          btn.innerHTML = 'Envoi en cours...';
        });
      }
    });
  });
})();
</script>

<script>
(function() {
  var toolbarOptions = [
    ['bold', 'italic', 'underline'],
    [{ 'header': 2 }, { 'header': 3 }],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    ['clean']
  ];
  var _quills = {};
  function initQuill(editorId, hiddenId, initialHtml) {
    var q = new Quill('#' + editorId, { theme: 'snow', modules: { toolbar: toolbarOptions } });
    _quills[hiddenId] = q;
    q.on('text-change', function() {
      document.getElementById(hiddenId).value = q.root.innerHTML;
    });
    if (initialHtml) {
      q.clipboard.dangerouslyPasteHTML(initialHtml);
      // dangerouslyPasteHTML est async : forcer la sync après son exécution
      setTimeout(function() {
        document.getElementById(hiddenId).value = q.root.innerHTML;
      }, 80);
    }
    return q;
  }

  var introVal = <?= json_encode($v_intro) ?>;
  var descVal  = <?= json_encode($v_description) ?>;
  var whyVal   = <?= json_encode($v_why_text) ?>;

  initQuill('quill_intro',       'f_intro',       introVal);
  initQuill('quill_description', 'f_description', descVal);
  initQuill('quill_why',         'f_why',         whyVal);

  // Force sync de toutes les instances Quill juste avant soumission
  document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
      Object.keys(_quills).forEach(function(hiddenId) {
        var el = document.getElementById(hiddenId);
        if (el) el.value = _quills[hiddenId].root.innerHTML;
      });
    });
  });
})();
</script>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
