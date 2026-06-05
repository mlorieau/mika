<?php
// ============================================================
// admin/collectible-edit.php — Créer / Éditer un objet caché
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current = 'missions';
$pdo           = db();
$coll_id       = isset($_GET['id'])         ? (int)$_GET['id']         : 0;
$mission_id    = isset($_GET['mission_id']) ? (int)$_GET['mission_id'] : 0;
$is_edit       = $coll_id > 0;
$coll          = null;
$mission       = null;
$flash_ok      = null;
$flash_err     = null;

// Charger la mission
if ($pdo) {
    // Si édition, récupérer depuis collectible
    if ($is_edit) {
        $s = $pdo->prepare("SELECT mc.*, m.title AS mission_title FROM mission_collectibles mc JOIN missions m ON m.id=mc.mission_id WHERE mc.id=:id LIMIT 1");
        $s->execute([':id'=>$coll_id]);
        $coll = $s->fetch() ?: null;
        if ($coll) $mission_id = (int)$coll['mission_id'];
    }
    if ($mission_id > 0) {
        $s = $pdo->prepare("SELECT * FROM missions WHERE id=:id AND mission_type='hidden_hunt' LIMIT 1");
        $s->execute([':id'=>$mission_id]);
        $mission = $s->fetch() ?: null;
    }
}

if (!$mission) {
    header('Location: missions.php');
    exit;
}

$admin_page_title = $is_edit ? 'Éditer objet' : 'Nouvel objet';

$page_slugs = ['index','clans','missions','hall','classement','profil'];

// ── POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_err = 'Token CSRF invalide.';
    } else {
        $title   = safe_input($_POST['title']   ?? '', 200);
        $key_raw = safe_input($_POST['collectible_key'] ?? '', 100);
        $hint    = safe_input($_POST['hint']    ?? '', 500);
        $page    = in_array($_POST['page_slug'] ?? '', $page_slugs) ? ($_POST['page_slug']) : 'index';
        $top     = min(99, max(0, (float)($_POST['position_top']  ?? 50)));
        $left    = min(99, max(0, (float)($_POST['position_left'] ?? 50)));
        $top_mobile  = isset($_POST['position_top_mobile'])  && $_POST['position_top_mobile'] !== '' ? min(99, max(0, (float)$_POST['position_top_mobile'])) : null;
        $left_mobile = isset($_POST['position_left_mobile']) && $_POST['position_left_mobile'] !== '' ? min(99, max(0, (float)$_POST['position_left_mobile'])) : null;
        $size_desktop = max(24, min(120, (int)($_POST['size_desktop'] ?? 48)));
        $size_mobile  = max(20, min(80,  (int)($_POST['size_mobile']  ?? 40)));
        $stitle  = safe_input($_POST['success_title']   ?? '', 200);
        $smsg    = safe_input($_POST['success_message'] ?? '', 500);
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $order   = max(0, (int)($_POST['sort_order'] ?? 0));

        if (!$title) {
            $flash_err = 'Le titre est obligatoire.';
        } else {
            // Générer la clé si vide
            if (empty($key_raw)) {
                $base = strtolower(preg_replace('/[^a-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $title . '_' . $page)));
                $key_raw = trim($base, '_');
            }
            $key = strtolower(preg_replace('/[^a-z0-9_]/', '_', $key_raw));

            // Upload objet
            $obj_path = $coll['object_image'] ?? null;
            if (!empty($_FILES['object_image']['name'])) {
                $up = upload_collectible_media($_FILES['object_image'], 'object');
                if ($up['ok']) {
                    $obj_path = $up['path'];
                } else {
                    $flash_err = 'Image objet : ' . $up['error'];
                }
            }
            // Upload GIF succès
            $gif_path = $coll['success_gif'] ?? null;
            if (!empty($_FILES['success_gif']['name'])) {
                $up2 = upload_collectible_media($_FILES['success_gif'], 'success');
                if ($up2['ok']) {
                    $gif_path = $up2['path'];
                } else {
                    $flash_err = ($flash_err ? $flash_err . ' ' : '') . 'GIF succès : ' . $up2['error'];
                }
            }

            if (!$flash_err) {
                try {
                    if ($is_edit) {
                        $pdo->prepare("
                            UPDATE mission_collectibles SET
                                title=:title, collectible_key=:key, hint=:hint,
                                page_slug=:page, position_top=:top, position_left=:left,
                                position_top_mobile=:topm, position_left_mobile=:leftm,
                                size_desktop=:szd, size_mobile=:szm,
                                object_image=:obj, success_gif=:gif,
                                success_title=:stitle, success_message=:smsg,
                                sort_order=:order, is_active=:active,
                                updated_at=NOW()
                            WHERE id=:id AND mission_id=:mid
                        ")->execute([
                            ':title'=>$title, ':key'=>$key, ':hint'=>$hint ?: null,
                            ':page'=>$page, ':top'=>$top, ':left'=>$left,
                            ':topm'=>$top_mobile, ':leftm'=>$left_mobile,
                            ':szd'=>$size_desktop, ':szm'=>$size_mobile,
                            ':obj'=>$obj_path, ':gif'=>$gif_path,
                            ':stitle'=>$stitle ?: 'Bravo !', ':smsg'=>$smsg ?: null,
                            ':order'=>$order, ':active'=>$active,
                            ':id'=>$coll_id, ':mid'=>$mission_id,
                        ]);
                        $flash_ok = 'Objet mis à jour.';
                        $s = $pdo->prepare("SELECT mc.*,m.title AS mission_title FROM mission_collectibles mc JOIN missions m ON m.id=mc.mission_id WHERE mc.id=:id LIMIT 1");
                        $s->execute([':id'=>$coll_id]);
                        $coll = $s->fetch() ?: $coll;
                    } else {
                        $pdo->prepare("
                            INSERT INTO mission_collectibles
                                (mission_id,collectible_key,title,hint,
                                 page_slug,position_top,position_left,
                                 position_top_mobile,position_left_mobile,
                                 size_desktop,size_mobile,
                                 object_image,success_gif,
                                 success_title,success_message,sort_order,is_active)
                            VALUES
                                (:mid,:key,:title,:hint,
                                 :page,:top,:left,
                                 :topm,:leftm,:szd,:szm,
                                 :obj,:gif,:stitle,:smsg,:order,:active)
                        ")->execute([
                            ':mid'=>$mission_id, ':key'=>$key, ':title'=>$title, ':hint'=>$hint ?: null,
                            ':page'=>$page, ':top'=>$top, ':left'=>$left,
                            ':topm'=>$top_mobile, ':leftm'=>$left_mobile,
                            ':szd'=>$size_desktop, ':szm'=>$size_mobile,
                            ':obj'=>$obj_path, ':gif'=>$gif_path,
                            ':stitle'=>$stitle ?: 'Bravo !', ':smsg'=>$smsg ?: null,
                            ':order'=>$order, ':active'=>$active,
                        ]);
                        header('Location: collectibles.php?mission_id=' . $mission_id . '&ok=created');
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log('[ZONE85 admin/collectible-edit] ' . $e->getMessage());
                    $flash_err = 'Erreur base de données. Vérifiez les logs.';
                }
            }
        }
    }
}

$f = [
    'title'                => $coll['title']            ?? '',
    'collectible_key'      => $coll['collectible_key']  ?? '',
    'hint'                 => $coll['hint']             ?? '',
    'page_slug'            => $coll['page_slug']        ?? 'index',
    'position_top'         => $coll['position_top']     ?? 50,
    'position_left'        => $coll['position_left']    ?? 50,
    'position_top_mobile'  => $coll['position_top_mobile']  ?? null,
    'position_left_mobile' => $coll['position_left_mobile'] ?? null,
    'size_desktop'         => $coll['size_desktop']     ?? 48,
    'size_mobile'          => $coll['size_mobile']      ?? 40,
    'object_image'         => $coll['object_image']     ?? null,
    'success_gif'          => $coll['success_gif']      ?? null,
    'success_title'        => $coll['success_title']    ?? 'Bravo !',
    'success_message'      => $coll['success_message']  ?? '',
    'sort_order'           => $coll['sort_order']       ?? 0,
    'is_active'            => $coll['is_active']        ?? 1,
];

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title"><?= $is_edit ? 'Éditer l\'objet caché' : 'Nouvel objet caché' ?></h1>
    <p class="adm-page-sub">Mission : <strong><?= e($mission['title']) ?></strong></p>
  </div>
  <div class="adm-page-actions">
    <a href="collectibles.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-ghost">← Retour aux objets</a>
  </div>
</div>

<?php if ($flash_ok): ?><div class="adm-flash adm-flash-ok">✅ <?= e($flash_ok) ?></div><?php endif; ?>
<?php if ($flash_err): ?><div class="adm-flash adm-flash-err">⚠️ <?= e($flash_err) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" autocomplete="off">
  <?= csrf_field() ?>

  <div class="adm-card">
    <div class="adm-card-title">Identité de l'objet</div>
    <div class="adm-form-grid">

      <div class="adm-field adm-form-full">
        <label class="adm-label">Titre <span>*</span></label>
        <input type="text" name="title" class="adm-input" value="<?= e($f['title']) ?>"
               required maxlength="200" placeholder="Ex : Mogette du Réveil">
      </div>

      <div class="adm-field">
        <label class="adm-label">Clé technique</label>
        <input type="text" name="collectible_key" class="adm-input" value="<?= e($f['collectible_key']) ?>"
               maxlength="100" placeholder="Généré automatiquement" style="font-family:monospace">
        <span class="adm-hint">Lettres, chiffres, underscores. Ex : mogette_home_hero</span>
      </div>

      <div class="adm-field">
        <label class="adm-label">Ordre d'affichage</label>
        <input type="number" name="sort_order" class="adm-input" value="<?= (int)$f['sort_order'] ?>" min="0" max="99">
        <span class="adm-hint">0 = premier. Pour ordonner les indices.</span>
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label">Indice (optionnel)</label>
        <input type="text" name="hint" class="adm-input" value="<?= e($f['hint']) ?>"
               maxlength="500" placeholder="Ex : Regarde dans le hero de la page d'accueil…">
        <span class="adm-hint">Visible dans mission.php si les indices sont affichés.</span>
      </div>

    </div>
  </div>

  <div class="adm-card">
    <div class="adm-card-title">Placement sur le site</div>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label">Page <span>*</span></label>
        <select name="page_slug" class="adm-select">
          <?php foreach (['index'=>'🏠 Accueil','clans'=>'🛡️ Les Clans','missions'=>'🗺️ Missions','hall'=>'🏆 Hall','classement'=>'📊 Classement','profil'=>'👤 Profil'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $f['page_slug'] === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <span class="adm-hint">Page sur laquelle l'objet apparaîtra.</span>
      </div>

      <div class="adm-field" style="grid-column:1/-1">
        <div style="background:#f8f4ef;border-radius:12px;padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div>
            <label class="adm-label">Position Top (%)</label>
            <input type="number" name="position_top" class="adm-input"
                   value="<?= number_format((float)$f['position_top'], 1) ?>"
                   min="1" max="95" step="0.5" id="inp_top">
            <span class="adm-hint">% depuis le haut du viewport (1–95)</span>
          </div>
          <div>
            <label class="adm-label">Position Left (%)</label>
            <input type="number" name="position_left" class="adm-input"
                   value="<?= number_format((float)$f['position_left'], 1) ?>"
                   min="1" max="95" step="0.5" id="inp_left">
            <span class="adm-hint">% depuis la gauche du viewport (1–95)</span>
          </div>
        </div>
        <!-- Aperçu visuel position -->
        <div style="margin-top:12px;position:relative;width:100%;aspect-ratio:16/6;background:linear-gradient(135deg,#0c1e2e,#12314e);border-radius:12px;overflow:hidden;border:2px solid #d0cbc5" id="preview-box">
          <div style="position:absolute;width:28px;height:28px;background:#ea5649;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;transition:top .2s,left .2s;display:flex;align-items:center;justify-content:center;font-size:1rem" id="preview-dot">🎯</div>
          <div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);font-size:.7rem;color:rgba(255,255,255,.4);font-weight:600;letter-spacing:.05em">Aperçu position viewport</div>
        </div>
      </div>

    </div>
  </div>

  <div class="adm-card" style="margin-top:20px;border:1px dashed rgba(14,165,233,.3);background:rgba(14,165,233,.04)">
    <div class="adm-card-title" style="color:#0369a1">📱 Position mobile (optionnel)</div>
    <p style="font-size:.78rem;color:#0369a1;margin-bottom:16px">
      Si non renseigné, la position desktop est utilisée sur mobile. Recommandé : éviter les bords et les coins.
    </p>
    <div class="adm-form-grid">
      <div class="adm-field">
        <label class="adm-label">Top mobile (%)</label>
        <input type="number" name="position_top_mobile" class="adm-input"
               value="<?= e($f['position_top_mobile'] ?? '') ?>" min="0" max="100" step="0.5" placeholder="Laisser vide = même que desktop">
      </div>
      <div class="adm-field">
        <label class="adm-label">Left mobile (%)</label>
        <input type="number" name="position_left_mobile" class="adm-input"
               value="<?= e($f['position_left_mobile'] ?? '') ?>" min="0" max="100" step="0.5" placeholder="Laisser vide = même que desktop">
      </div>
      <div class="adm-field">
        <label class="adm-label">Taille desktop (px)</label>
        <input type="number" name="size_desktop" class="adm-input"
               value="<?= (int)($f['size_desktop'] ?? 48) ?>" min="24" max="120" step="4">
        <span class="adm-hint">Par défaut : 48px</span>
      </div>
      <div class="adm-field">
        <label class="adm-label">Taille mobile (px)</label>
        <input type="number" name="size_mobile" class="adm-input"
               value="<?= (int)($f['size_mobile'] ?? 40) ?>" min="20" max="80" step="4">
        <span class="adm-hint">Par défaut : 40px</span>
      </div>
    </div>
  </div>

  <div class="adm-card">
    <div class="adm-card-title">Image de l'objet & GIF de succès</div>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label">Image de l'objet caché</label>
        <?php if ($f['object_image']): ?>
        <div style="margin-bottom:10px">
          <img src="<?= e(url($f['object_image'])) ?>" alt="" style="width:60px;height:60px;object-fit:contain;background:#f8f4ef;border-radius:8px;padding:6px;border:1px solid #d0cbc5">
          <span style="font-size:.72rem;color:#6b7f96;margin-left:8px">Image actuelle</span>
        </div>
        <?php endif; ?>
        <input type="file" name="object_image" class="adm-input" accept=".jpg,.jpeg,.png,.webp,.gif">
        <span class="adm-hint">jpg, png, webp, gif — max 1 Mo. Fond transparent recommandé (PNG).</span>
      </div>

      <div class="adm-field">
        <label class="adm-label">Image/GIF de succès (bravo)</label>
        <?php if ($f['success_gif']): ?>
        <div style="margin-bottom:10px">
          <img src="<?= e(url($f['success_gif'])) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #d0cbc5">
          <span style="font-size:.72rem;color:#6b7f96;margin-left:8px">GIF actuel</span>
        </div>
        <?php endif; ?>
        <input type="file" name="success_gif" class="adm-input" accept=".jpg,.jpeg,.png,.webp,.gif">
        <span class="adm-hint">jpg, png, webp, gif — max 5 Mo. Affiché dans l'overlay bravo.</span>
      </div>

    </div>
  </div>

  <div class="adm-card">
    <div class="adm-card-title">Message de réussite</div>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label">Titre de l'overlay</label>
        <input type="text" name="success_title" class="adm-input"
               value="<?= e($f['success_title']) ?>" maxlength="200" placeholder="Bravo !">
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label">Message</label>
        <textarea name="success_message" class="adm-textarea" maxlength="500"
                  placeholder="Ex : Tu as trouvé la mogette du réveil ! Plus que 9 à dénicher…"><?= e($f['success_message']) ?></textarea>
      </div>

    </div>
  </div>

  <div class="adm-card">
    <div class="adm-card-title">Options</div>
    <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
      <input type="checkbox" name="is_active" <?= $f['is_active'] ? 'checked' : '' ?>>
      <span class="adm-label" style="margin:0">Objet actif (visible sur le site)</span>
    </label>
  </div>

  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <button type="submit" class="btn-adm btn-adm-primary" style="min-width:200px">
      <?= $is_edit ? '💾 Enregistrer' : '✨ Créer l\'objet' ?>
    </button>
    <a href="collectibles.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-ghost">Annuler</a>
  </div>

</form>

<?php
$admin_scripts = '<script>
(function() {
  var box = document.getElementById("preview-box");
  var dot = document.getElementById("preview-dot");
  var inpTop  = document.getElementById("inp_top");
  var inpLeft = document.getElementById("inp_left");
  function update() {
    if (!box || !dot) return;
    var t = parseFloat(inpTop.value)  || 50;
    var l = parseFloat(inpLeft.value) || 50;
    dot.style.top  = t + "%";
    dot.style.left = l + "%";
  }
  if (inpTop)  inpTop.addEventListener("input",  update);
  if (inpLeft) inpLeft.addEventListener("input",  update);
  update();
})();
</script>';
require_once '_admin-footer.php'; ?>
