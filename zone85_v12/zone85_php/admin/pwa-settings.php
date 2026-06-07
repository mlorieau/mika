<?php
// ============================================================
// admin/pwa-settings.php — Gestion des icônes PWA
// ============================================================
$admin_current    = 'pwa-settings';
$admin_page_title = 'PWA — Icônes';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/settings.php';

define('SKIP_MAINTENANCE_CHECK', true);
require_admin();

$pdo     = db();
$flash   = null;
$pwa_dir = BASE_PATH . 'assets/img/pwa/';

$pwa_sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// ── Génération d'une icône PNG à partir d'une image source ────
function pwa_resize_icon(string $source, string $dest, int $size): bool|string {
    if (!extension_loaded('gd')) {
        return 'Extension GD non disponible.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($source);

    $src = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($source),
        'image/png'  => @imagecreatefrompng($source),
        'image/webp' => @imagecreatefromwebp($source),
        default      => false,
    };

    if (!$src) return 'Impossible de lire l\'image source.';

    $sw = imagesx($src);
    $sh = imagesy($src);

    // Recadrage carré centré si l'image n'est pas carrée
    if ($sw !== $sh) {
        $sq = min($sw, $sh);
        $ox = (int)(($sw - $sq) / 2);
        $oy = (int)(($sh - $sq) / 2);
        $tmp = imagecreatetruecolor($sq, $sq);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $t = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefilledrectangle($tmp, 0, 0, $sq - 1, $sq - 1, $t);
        imagecopy($tmp, $src, 0, 0, $ox, $oy, $sq, $sq);
        imagedestroy($src);
        $src = $tmp;
        $sw = $sh = $sq;
    }

    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $t = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $size - 1, $size - 1, $t);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $sw, $sh);

    $ok = imagepng($dst, $dest, 9);
    imagedestroy($src);
    imagedestroy($dst);

    return $ok ? true : 'Échec écriture ' . basename($dest) . '.';
}

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action = $_POST['action'] ?? '';

        // ── Action : upload + génération des icônes ─────────
        if ($action === 'upload_icon') {
            $f = $_FILES['pwa_source'] ?? null;
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $err_code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
                $err_msg  = match($err_code) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd.',
                    UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                    default => 'Erreur d\'envoi (code ' . $err_code . ').',
                };
                $flash = ['type' => 'err', 'msg' => $err_msg];
            } elseif ($f['size'] > 10 * 1024 * 1024) {
                $flash = ['type' => 'err', 'msg' => 'Fichier trop lourd (max 10 Mo).'];
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($f['tmp_name']);
                $ok_mimes = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($mime, $ok_mimes, true)) {
                    $flash = ['type' => 'err', 'msg' => 'Format non accepté. Utilisez PNG, JPG ou WEBP.'];
                } elseif (!@getimagesize($f['tmp_name'])) {
                    $flash = ['type' => 'err', 'msg' => 'Fichier image invalide ou corrompu.'];
                } else {
                    // Créer le dossier PWA si absent
                    if (!is_dir($pwa_dir)) {
                        mkdir($pwa_dir, 0755, true);
                    }
                    // Stocker la source
                    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $src_file = $pwa_dir . 'source-upload.' . ($ext_map[$mime] ?? 'png');

                    if (!move_uploaded_file($f['tmp_name'], $src_file)) {
                        $flash = ['type' => 'err', 'msg' => 'Impossible d\'enregistrer l\'image source.'];
                    } else {
                        // Générer toutes les tailles
                        $done = 0;
                        $errs = [];
                        foreach ($pwa_sizes as $sz) {
                            $dest   = $pwa_dir . "icon-{$sz}.png";
                            $result = pwa_resize_icon($src_file, $dest, $sz);
                            if ($result === true) {
                                $done++;
                            } else {
                                $errs[] = "icon-{$sz} : {$result}";
                            }
                        }
                        if (empty($errs)) {
                            $flash = ['type' => 'ok', 'msg' => "{$done} icônes PNG générées avec succès."];
                        } else {
                            $flash = ['type' => 'warn', 'msg' => "{$done}/" . count($pwa_sizes) . " icônes générées. Problèmes : " . implode(' | ', $errs)];
                        }
                    }
                }
            }
        }

        // ── Action : régénérer depuis source existante ──────
        elseif ($action === 'regen_icons') {
            // Trouver la source
            $src_file = null;
            foreach (['source-upload.png', 'source-upload.jpg', 'source-upload.webp'] as $candidate) {
                if (file_exists($pwa_dir . $candidate)) {
                    $src_file = $pwa_dir . $candidate;
                    break;
                }
            }
            if (!$src_file) {
                $flash = ['type' => 'err', 'msg' => 'Aucune image source trouvée. Uploadez d\'abord une icône.'];
            } else {
                $done = 0;
                $errs = [];
                foreach ($pwa_sizes as $sz) {
                    $dest   = $pwa_dir . "icon-{$sz}.png";
                    $result = pwa_resize_icon($src_file, $dest, $sz);
                    if ($result === true) $done++;
                    else $errs[] = "icon-{$sz}";
                }
                $flash = empty($errs)
                    ? ['type' => 'ok',  'msg' => "{$done} icônes régénérées."]
                    : ['type' => 'warn','msg' => "{$done}/" . count($pwa_sizes) . " générées. Échecs : " . implode(', ', $errs)];
            }
        }

        // ── Action : régénérer manifest.json ────────────────
        elseif ($action === 'regen_manifest') {
            $base_url    = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/');
            $pwa_name    = get_setting('pwa_app_name',   "ZONE85 — L'Esprit Vendée");
            $pwa_short   = get_setting('pwa_short_name', 'Zone85');
            $pwa_theme   = get_setting('pwa_theme_color', '#0c1e2e');
            $pwa_bg      = get_setting('pwa_bg_color',    '#f8f4ef');
            $pwa_display = get_setting('pwa_display',     'standalone');

            $manifest = [
                'name'             => $pwa_name,
                'short_name'       => $pwa_short,
                'description'      => "Terrain de jeu communautaire vendéen. Rejoins un clan, gagne des XP, fais vivre la Vendée autrement.",
                'start_url'        => $base_url . '/',
                'scope'            => $base_url . '/',
                'display'          => in_array($pwa_display, ['standalone','fullscreen','minimal-ui','browser'], true) ? $pwa_display : 'standalone',
                'orientation'      => 'portrait-primary',
                'background_color' => $pwa_bg,
                'theme_color'      => $pwa_theme,
                'lang'             => 'fr',
                'categories'       => ['games', 'social', 'entertainment'],
                'icons'            => array_map(fn($s) => [
                    'src'     => $base_url . "/assets/img/pwa/icon-{$s}.png",
                    'sizes'   => "{$s}x{$s}",
                    'type'    => 'image/png',
                    'purpose' => ($s >= 192) ? 'any maskable' : 'any',
                ], $pwa_sizes),
                'shortcuts' => [
                    ['name' => 'Mes missions', 'short_name' => 'Missions', 'description' => 'Voir les missions actives',
                     'url' => $base_url . '/missions.php',
                     'icons' => [['src' => $base_url . '/assets/img/pwa/shortcut-missions.png', 'sizes' => '96x96']]],
                    ['name' => 'Mon profil', 'short_name' => 'Profil', 'description' => 'Accéder à mon profil',
                     'url' => $base_url . '/profil.php',
                     'icons' => [['src' => $base_url . '/assets/img/pwa/shortcut-profil.png', 'sizes' => '96x96']]],
                    ['name' => 'Classement', 'short_name' => 'Classement', 'description' => 'Voir le classement',
                     'url' => $base_url . '/classement.php',
                     'icons' => [['src' => $base_url . '/assets/img/pwa/shortcut-classement.png', 'sizes' => '96x96']]],
                ],
            ];

            $dest = BASE_PATH . 'manifest.json';
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            if (file_put_contents($dest, $json) !== false) {
                $flash = ['type' => 'ok', 'msg' => 'manifest.json régénéré avec succès depuis les paramètres actuels.'];
            } else {
                $flash = ['type' => 'err', 'msg' => 'Impossible d\'écrire manifest.json — vérifiez les permissions du dossier.'];
            }
        }
    }
}

// ── État des icônes ──────────────────────────────────────────
$icon_status = [];
foreach ($pwa_sizes as $sz) {
    $path = $pwa_dir . "icon-{$sz}.png";
    $icon_status[$sz] = file_exists($path) ? ['ok' => true, 'size' => filesize($path)] : ['ok' => false];
}
$icons_ok    = count(array_filter($icon_status, fn($s) => $s['ok']));
$icons_total = count($pwa_sizes);

// Source existante ?
$src_exists = false;
$src_label  = '';
foreach (['source-upload.png', 'source-upload.jpg', 'source-upload.webp'] as $c) {
    if (file_exists($pwa_dir . $c)) { $src_exists = true; $src_label = $c; break; }
}

// GD disponible ?
$gd_ok = extension_loaded('gd');

require_once '_admin-header.php';
?>

<style>
.pwa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media(max-width:800px){ .pwa-grid { grid-template-columns:1fr; } }

.pwa-icon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 12px;
  margin-top: 16px;
}
.pwa-icon-item {
  text-align: center;
  padding: 14px 8px;
  border-radius: 10px;
  border: 1.5px solid;
  font-size: .75rem;
}
.pwa-icon-ok   { border-color: rgba(42,157,92,.3); background: rgba(42,157,92,.06); color: #1a7a42; }
.pwa-icon-miss { border-color: rgba(234,86,73,.3);  background: rgba(234,86,73,.05); color: #c0392b; }
.pwa-icon-num  { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; }
.pwa-icon-lbl  { font-size: .67rem; opacity: .7; }

.pwa-src-preview { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8f4ef; border-radius: 8px; margin-bottom: 16px; }
.pwa-src-preview img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #d0cbc5; }

.pwa-score { display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 800; padding: 5px 14px; border-radius: 999px; }
.pwa-score-ok   { background: rgba(42,157,92,.1);  color: #1a7a42; }
.pwa-score-warn { background: rgba(201,150,42,.1); color: #8a6020; }
.pwa-score-err  { background: rgba(234,86,73,.1);  color: #c0392b; }

.pwa-upload-zone {
  border: 2px dashed #d0cbc5; border-radius: 12px; padding: 28px 20px;
  text-align: center; cursor: pointer; transition: border-color .2s;
}
.pwa-upload-zone:hover { border-color: #ea5649; }
.pwa-upload-zone input[type="file"] { display: none; }

.pwa-manifest-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0ece7; font-size: .84rem; }
.pwa-manifest-row:last-child { border-bottom: none; }
.pwa-manifest-key { font-weight: 700; color: #3d5166; width: 160px; flex-shrink: 0; }
.pwa-manifest-val { color: #0f1e2d; }

.adm-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 22px; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: .86rem; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; text-decoration: none; }
.adm-btn:hover { opacity: .88; }
.adm-btn:disabled { opacity: .45; cursor: not-allowed; }
.adm-btn-pri { background: #ea5649; color: #fff; }
.adm-btn-sec { background: transparent; color: #ea5649; border: 1.5px solid #ea5649; padding: 8px 18px; }
.adm-btn-sm  { padding: 7px 14px; font-size: .78rem; }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">📱 PWA — Icônes &amp; Manifest</h1>
    <p class="adm-page-sub">Gérez les icônes de l'application et le manifest PWA.</p>
  </div>
  <?php
  $score_cls = $icons_ok === $icons_total ? 'pwa-score-ok' : ($icons_ok >= $icons_total / 2 ? 'pwa-score-warn' : 'pwa-score-err');
  ?>
  <span class="pwa-score <?= $score_cls ?>"><?= $icons_ok ?>/<?= $icons_total ?> icônes</span>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
  <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (!$gd_ok): ?>
<div class="adm-flash adm-flash-warn">
  ⚠️ <strong>Extension GD non disponible</strong> — La génération automatique d'icônes est désactivée.
  Contactez votre hébergeur pour activer l'extension PHP GD.
</div>
<?php endif; ?>

<div class="pwa-grid">

  <!-- ── Colonne gauche : état des icônes ──────────────────── -->
  <div>
    <div class="adm-card">
      <p class="adm-card-title">État des icônes</p>
      <div class="pwa-icon-grid">
        <?php foreach ($icon_status as $sz => $st): ?>
        <div class="pwa-icon-item <?= $st['ok'] ? 'pwa-icon-ok' : 'pwa-icon-miss' ?>">
          <div class="pwa-icon-num"><?= $st['ok'] ? '✅' : '❌' ?></div>
          <div style="font-weight:700"><?= $sz ?>×<?= $sz ?></div>
          <div class="pwa-icon-lbl">
            <?php if ($st['ok']): ?>
            <?= round($st['size'] / 1024, 1) ?> Ko
            <?php else: ?>
            manquante
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($src_exists): ?>
      <div class="pwa-src-preview" style="margin-top:16px">
        <img src="<?= rtrim(defined('BASE_URL') ? BASE_URL : '/', '/') ?>/assets/img/pwa/<?= htmlspecialchars($src_label, ENT_QUOTES) ?>"
             alt="Icône source" onerror="this.style.display='none'">
        <div>
          <div style="font-size:.82rem;font-weight:700;color:#0f1e2d">Source actuelle</div>
          <div style="font-size:.72rem;color:#6b7f96"><?= htmlspecialchars($src_label, ENT_QUOTES) ?></div>
        </div>
        <?php if ($gd_ok): ?>
        <form method="POST" style="margin-left:auto">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="regen_icons">
          <button type="submit" class="adm-btn adm-btn-sm adm-btn-sec" style="font-size:.78rem">
            🔄 Régénérer
          </button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Manifest actuel -->
    <div class="adm-card">
      <p class="adm-card-title">Manifest actuel</p>
      <?php
      $mf_path = BASE_PATH . 'manifest.json';
      $mf      = file_exists($mf_path) ? @json_decode(file_get_contents($mf_path), true) : null;
      ?>
      <?php if ($mf): ?>
      <div>
        <?php
        $rows = [
          'name'             => $mf['name']             ?? '—',
          'short_name'       => $mf['short_name']       ?? '—',
          'start_url'        => $mf['start_url']        ?? '—',
          'display'          => $mf['display']          ?? '—',
          'theme_color'      => $mf['theme_color']      ?? '—',
          'background_color' => $mf['background_color'] ?? '—',
          'Icônes déclarées' => count($mf['icons'] ?? []) . ' entrées',
        ];
        foreach ($rows as $k => $v):
        ?>
        <div class="pwa-manifest-row">
          <span class="pwa-manifest-key"><?= htmlspecialchars($k, ENT_QUOTES) ?></span>
          <span class="pwa-manifest-val">
            <?php if (str_starts_with((string)$v, '#')): ?>
            <span style="display:inline-block;width:14px;height:14px;background:<?= htmlspecialchars($v, ENT_QUOTES) ?>;border-radius:3px;vertical-align:middle;margin-right:6px"></span>
            <?php endif; ?>
            <?= htmlspecialchars($v, ENT_QUOTES) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" style="margin-top:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="regen_manifest">
        <button type="submit" class="adm-btn adm-btn-sec" style="font-size:.82rem">
          🔄 Régénérer manifest.json depuis les paramètres
        </button>
      </form>
      <?php else: ?>
      <p style="font-size:.84rem;color:#c0392b">manifest.json introuvable ou invalide.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Colonne droite : upload ──────────────────────────── -->
  <div>
    <div class="adm-card">
      <p class="adm-card-title">Uploader une icône source</p>
      <p style="font-size:.82rem;color:#6b7f96;margin-bottom:18px;line-height:1.6">
        Uploadez une image carrée en PNG, JPG ou WEBP.<br>
        Recommandé : <strong>512×512 px minimum</strong>.<br>
        Si l'image n'est pas carrée, elle sera recadrée automatiquement au centre.
      </p>

      <?php if (!$gd_ok): ?>
      <div style="padding:14px;background:rgba(234,86,73,.06);border-radius:8px;font-size:.82rem;color:#c0392b;margin-bottom:16px">
        ⚠️ GD indisponible — la génération automatique ne fonctionnera pas.
      </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_icon">

        <label class="pwa-upload-zone" id="drop-zone">
          <input type="file" name="pwa_source" id="pwa_source" accept=".png,.jpg,.jpeg,.webp">
          <div id="drop-label">
            <div style="font-size:2rem;margin-bottom:8px">🖼️</div>
            <div style="font-weight:700;color:#0f1e2d;margin-bottom:4px">Choisir ou glisser une image</div>
            <div style="font-size:.74rem;color:#6b7f96">PNG, JPG, WEBP — max 10 Mo</div>
          </div>
          <div id="drop-preview" style="display:none">
            <img id="drop-img" src="" alt="" style="max-width:120px;max-height:120px;border-radius:10px;border:2px solid #ea5649">
            <div id="drop-info" style="margin-top:8px;font-size:.78rem;color:#3d5166;font-weight:600"></div>
          </div>
        </label>

        <div style="margin-top:16px">
          <button type="submit" class="adm-btn adm-btn-pri" <?= $gd_ok ? '' : 'disabled title="GD non disponible"' ?>>
            ⚡ Uploader et générer les 8 tailles
          </button>
        </div>
      </form>
    </div>

    <!-- Guide -->
    <div class="adm-card" style="background:rgba(14,165,233,.04);border:1px solid rgba(14,165,233,.15)">
      <p class="adm-card-title" style="color:#0369a1">📖 Guide PWA</p>
      <p style="font-size:.82rem;color:#0f1e2d;line-height:1.7;margin:0 0 10px">
        <strong>Icônes générées :</strong> icon-72 à icon-512 dans <code>assets/img/pwa/</code>.<br>
        <strong>Icônes spéciales :</strong> shortcut-*.png (96×96) à créer manuellement si besoin.<br>
        <strong>manifest.php :</strong> Utilisé automatiquement par le site — lit les paramètres en direct depuis la DB.<br>
        <strong>manifest.json :</strong> Version statique, régénérez-le si vous changez le nom ou les couleurs.
      </p>
      <div style="font-size:.76rem;color:#0369a1;background:rgba(14,165,233,.06);padding:10px 14px;border-radius:7px">
        Tailles requises pour les stores et navigateurs :<br>
        <strong>72, 96, 128, 144, 152</strong> (any) · <strong>192, 512</strong> (any maskable)
      </div>

      <p style="font-size:.82rem;color:#0f1e2d;line-height:1.6;margin-top:14px 0 0">
        <strong>Paramètres (nom, couleurs, display)</strong> → onglet
        <a href="settings.php?cat=pwa" style="color:#ea5649;font-weight:700">Paramètres → PWA</a>
      </p>
    </div>

    <!-- Vérification service worker -->
    <div class="adm-card">
      <p class="adm-card-title">Service Worker</p>
      <?php
      $sw_path = BASE_PATH . 'service-worker.js';
      $sw_ok   = file_exists($sw_path);
      if ($sw_ok) {
          $sw_content = file_get_contents($sw_path, false, null, 0, 200);
          preg_match('/SW_VERSION\s*=\s*[\'"]([^\'"]+)[\'"]/', $sw_content, $m);
          $sw_ver = $m[1] ?? '—';
      }
      ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <span class="pwa-score <?= $sw_ok ? 'pwa-score-ok' : 'pwa-score-err' ?>">
          <?= $sw_ok ? '✅ service-worker.js présent' : '❌ service-worker.js manquant' ?>
        </span>
        <?php if ($sw_ok && isset($sw_ver)): ?>
        <span class="pwa-score pwa-score-ok">v <?= htmlspecialchars($sw_ver, ENT_QUOTES) ?></span>
        <?php endif; ?>
        <span class="pwa-score <?= $gd_ok ? 'pwa-score-ok' : 'pwa-score-warn' ?>">
          PHP GD : <?= $gd_ok ? 'disponible' : 'non disponible' ?>
        </span>
      </div>
    </div>
  </div>
</div>

<script>
// Preview image avant upload
document.getElementById('pwa_source').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('drop-label').style.display = 'none';
    const prev = document.getElementById('drop-preview');
    prev.style.display = 'block';
    document.getElementById('drop-img').src = e.target.result;
    document.getElementById('drop-info').textContent =
      file.name + ' — ' + (file.size / 1024).toFixed(0) + ' Ko';
  };
  reader.readAsDataURL(file);
});

// Drag & drop sur la zone
const zone = document.getElementById('drop-zone');
['dragenter','dragover'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.style.borderColor='#ea5649'; }));
['dragleave','drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.style.borderColor=''; }));
zone.addEventListener('drop', ev => {
  const file = ev.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('pwa_source');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
  }
});
</script>

<?php require_once '_admin-footer.php'; ?>
