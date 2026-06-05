<?php
// ============================================================
// ZONE85 — Admin : Médiathèque centralisée
// ============================================================
$admin_current    = 'media';
$admin_page_title = 'Médiathèque';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

$pdo        = db();
$flash_msg  = null;
$flash_type = 'ok';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_msg  = 'Token CSRF invalide. Veuillez réessayer.';
        $flash_type = 'err';
    } else {
        $action = trim($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);

        if ($pdo) try {

            // Upload d'un nouveau fichier
            if ($action === 'upload') {
                $file = $_FILES['media_file'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    $flash_msg  = 'Aucun fichier reçu ou erreur d\'envoi.';
                    $flash_type = 'err';
                } else {
                    $mime     = mime_content_type($file['tmp_name']);
                    $allowed  = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
                                 'video/mp4','video/webm','application/pdf'];
                    if (!in_array($mime, $allowed, true)) {
                        $flash_msg  = 'Type de fichier non autorisé (' . htmlspecialchars($mime, ENT_QUOTES) . ').';
                        $flash_type = 'err';
                    } elseif ($file['size'] > 20 * 1024 * 1024) {
                        $flash_msg  = 'Fichier trop lourd (max 20 Mo).';
                        $flash_type = 'err';
                    } else {
                        // Dimensions pour les images
                        $w = $h = null;
                        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
                            $img_size = @getimagesize($file['tmp_name']);
                            if ($img_size !== false) { $w = $img_size[0]; $h = $img_size[1]; }
                        }

                        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $safe_ext = preg_replace('/[^a-z0-9]/', '', $ext);
                        $subdir   = 'media';
                        $upload_dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 1) . '/')
                                    . 'uploads/' . $subdir . '/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                        $filename = bin2hex(random_bytes(12)) . ($safe_ext ? '.' . $safe_ext : '');
                        $dest     = $upload_dir . $filename;

                        if (!move_uploaded_file($file['tmp_name'], $dest)) {
                            $flash_msg  = 'Impossible d\'enregistrer le fichier.';
                            $flash_type = 'err';
                        } else {
                            $rel_path  = 'uploads/' . $subdir . '/' . $filename;
                            $alt_text  = trim($_POST['alt_text'] ?? '');
                            $src_type  = trim($_POST['source_type'] ?? '');
                            $src_id    = (int)($_POST['source_id'] ?? 0) ?: null;
                            $uid       = (int)(current_user()['id'] ?? 0);

                            $pdo->prepare("
                                INSERT INTO media_files
                                    (file_path, filename, mime_type, file_size,
                                     width, height, alt_text, source_type, source_id, uploaded_by)
                                VALUES
                                    (:path, :fname, :mime, :size,
                                     :w, :h, :alt, :stype, :sid, :uid)
                            ")->execute([
                                ':path'  => $rel_path,
                                ':fname' => $file['name'],
                                ':mime'  => $mime,
                                ':size'  => (int)$file['size'],
                                ':w'     => $w, ':h' => $h,
                                ':alt'   => $alt_text ?: null,
                                ':stype' => $src_type ?: null,
                                ':sid'   => $src_id,
                                ':uid'   => $uid,
                            ]);
                            $flash_msg = 'Fichier uploadé : ' . htmlspecialchars($file['name'], ENT_QUOTES);
                        }
                    }
                }

            } elseif ($action === 'update_alt' && $id > 0) {
                $alt = trim($_POST['alt_text'] ?? '');
                $pdo->prepare("UPDATE media_files SET alt_text = :alt WHERE id = :id")
                    ->execute([':alt' => $alt ?: null, ':id' => $id]);
                $flash_msg = 'Texte alternatif mis à jour.';

            } elseif ($action === 'delete' && $id > 0) {
                $row = $pdo->prepare("SELECT file_path FROM media_files WHERE id = :id");
                $row->execute([':id' => $id]);
                $mf = $row->fetch();
                if ($mf) {
                    $abs = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 1) . '/') . $mf['file_path'];
                    if (file_exists($abs)) @unlink($abs);
                    $pdo->prepare("DELETE FROM media_files WHERE id = :id")->execute([':id' => $id]);
                    $flash_msg = 'Fichier supprimé.';
                } else {
                    $flash_msg  = 'Fichier introuvable.';
                    $flash_type = 'err';
                }
            }

        } catch (PDOException $e) {
            error_log('[admin/media] ' . $e->getMessage());
            $flash_msg  = 'Erreur base de données.';
            $flash_type = 'err';
        }
    }
    header('Location: media.php' . ($flash_msg ? '?flash=' . urlencode($flash_msg) . '&ft=' . $flash_type : ''));
    exit;
}

if (!$flash_msg && isset($_GET['flash'])) {
    $flash_msg  = $_GET['flash'];
    $flash_type = $_GET['ft'] ?? 'ok';
}

// ── Filtres ───────────────────────────────────────────────────
$filter_type  = $_GET['type'] ?? 'all';
$filter_page  = max(1, (int)($_GET['p'] ?? 1));
$per_page     = 24;
$offset       = ($filter_page - 1) * $per_page;

$allowed_types = ['all','image','video','pdf'];
if (!in_array($filter_type, $allowed_types, true)) $filter_type = 'all';

$where = match($filter_type) {
    'image' => "WHERE mime_type LIKE 'image/%'",
    'video' => "WHERE mime_type LIKE 'video/%'",
    'pdf'   => "WHERE mime_type = 'application/pdf'",
    default => '',
};

// ── Chargement données ────────────────────────────────────────
$files      = [];
$total_files = 0;
$total_size  = 0;

if ($pdo) try {
    $total_files = (int)$pdo->query("SELECT COUNT(*) FROM media_files {$where}")->fetchColumn();
    $total_size  = (int)$pdo->query("SELECT COALESCE(SUM(file_size),0) FROM media_files")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT mf.*, u.pseudo AS uploader
        FROM media_files mf
        LEFT JOIN users u ON u.id = mf.uploaded_by
        {$where}
        ORDER BY mf.created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ");
    $stmt->execute();
    $files = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/media] load : ' . $e->getMessage());
}

$total_pages = (int)ceil($total_files / $per_page);

function fmt_size(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
    if ($bytes >= 1024)    return round($bytes / 1024)    . ' Ko';
    return $bytes . ' o';
}

require_once '_admin-header.php';
?>

<style>
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px}
.media-card{background:#fff;border-radius:10px;border:1.5px solid #e2ddd8;overflow:hidden;transition:box-shadow .15s}
.media-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08)}
.media-thumb{width:100%;height:130px;object-fit:cover;background:#f0ece7;display:block}
.media-thumb-placeholder{width:100%;height:130px;background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-size:2.5rem}
.media-info{padding:10px 12px}
.media-name{font-size:.75rem;font-weight:700;color:#0c1e2e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:2px}
.media-meta{font-size:.68rem;color:#6b7f96}
.media-actions{display:flex;gap:6px;padding:0 12px 10px;flex-wrap:wrap}
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">🗂️ Médiathèque</h1>
    <p class="adm-page-sub">Tous les fichiers uploadés dans Zone85 — images, vidéos, PDFs.</p>
  </div>
  <div class="adm-page-actions">
    <button class="btn-adm btn-adm-primary"
            onclick="document.getElementById('upload-form').scrollIntoView({behavior:'smooth'})">
      ↑ Uploader un fichier
    </button>
  </div>
</div>

<?php if ($flash_msg): ?>
<div class="adm-flash adm-flash-<?= $flash_type ?>">
  <?= $flash_type === 'ok' ? '&#9989;' : '&#10060;' ?>
  <?= htmlspecialchars($flash_msg, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- KPIs -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
  <div style="flex:1;min-width:130px;background:#fff;border-radius:10px;padding:14px 18px;border:1.5px solid #e2ddd8">
    <div style="font-size:1.6rem;font-weight:900;color:#0c1e2e"><?= $total_files ?></div>
    <div style="font-size:.72rem;font-weight:600;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Fichiers</div>
  </div>
  <div style="flex:1;min-width:130px;background:#fff;border-radius:10px;padding:14px 18px;border:1.5px solid #e2ddd8">
    <div style="font-size:1.6rem;font-weight:900;color:#0c1e2e"><?= fmt_size($total_size) ?></div>
    <div style="font-size:.72rem;font-weight:600;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Espace utilisé</div>
  </div>
</div>

<!-- Filtres type -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach (['all'=>'Tous','image'=>'🖼 Images','video'=>'🎬 Vidéos','pdf'=>'📄 PDFs'] as $k=>$lbl): ?>
    <a href="media.php?type=<?= $k ?>"
       style="padding:6px 16px;border-radius:20px;font-size:.8rem;font-weight:700;text-decoration:none;border:2px solid <?= $filter_type===$k ? 'var(--primary,#ea5649)' : '#e2ddd8' ?>;color:<?= $filter_type===$k ? '#fff' : '#6b7f96' ?>;background:<?= $filter_type===$k ? 'var(--primary,#ea5649)' : '#fff' ?>">
      <?= $lbl ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Grille médias -->
<?php if (empty($files)): ?>
<div style="text-align:center;padding:48px;background:#fff;border-radius:10px;border:1.5px solid #e2ddd8;color:#6b7f96;margin-bottom:28px">
  <div style="font-size:3rem;margin-bottom:12px">📂</div>
  <p style="font-weight:700;color:#0c1e2e;margin-bottom:6px">Aucun fichier</p>
  <p>Uploadez votre premier média ci-dessous.</p>
</div>
<?php else: ?>
<div class="media-grid">
  <?php
  $base_url = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
  foreach ($files as $mf):
    $is_img   = str_starts_with($mf['mime_type'] ?? '', 'image/');
    $is_video = str_starts_with($mf['mime_type'] ?? '', 'video/');
    $is_pdf   = ($mf['mime_type'] ?? '') === 'application/pdf';
    $file_url = $base_url . '/' . ltrim($mf['file_path'], '/');
    $icon     = $is_img ? '🖼' : ($is_video ? '🎬' : ($is_pdf ? '📄' : '📎'));
  ?>
  <div class="media-card">
    <?php if ($is_img): ?>
      <a href="<?= htmlspecialchars($file_url, ENT_QUOTES) ?>" target="_blank">
        <img src="<?= htmlspecialchars($file_url, ENT_QUOTES) ?>"
             alt="<?= htmlspecialchars($mf['alt_text'] ?? $mf['filename'] ?? '', ENT_QUOTES) ?>"
             class="media-thumb" loading="lazy">
      </a>
    <?php else: ?>
      <a href="<?= htmlspecialchars($file_url, ENT_QUOTES) ?>" target="_blank" style="text-decoration:none">
        <div class="media-thumb-placeholder"><?= $icon ?></div>
      </a>
    <?php endif; ?>

    <div class="media-info">
      <div class="media-name" title="<?= htmlspecialchars($mf['filename'] ?? '', ENT_QUOTES) ?>">
        <?= htmlspecialchars($mf['filename'] ?? $mf['file_path'], ENT_QUOTES) ?>
      </div>
      <div class="media-meta">
        <?= fmt_size((int)$mf['file_size']) ?>
        <?php if ($mf['width'] && $mf['height']): ?>
          · <?= (int)$mf['width'] ?>×<?= (int)$mf['height'] ?>
        <?php endif; ?>
        · <?= date('d/m/y', strtotime($mf['created_at'])) ?>
      </div>
    </div>

    <!-- Édition alt text inline -->
    <form method="POST" class="media-actions" style="flex-direction:column;gap:6px">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_alt">
      <input type="hidden" name="id" value="<?= (int)$mf['id'] ?>">
      <input type="text" name="alt_text"
             value="<?= htmlspecialchars($mf['alt_text'] ?? '', ENT_QUOTES) ?>"
             placeholder="Texte alternatif…"
             style="width:100%;padding:4px 8px;font-size:.72rem;border:1px solid #e2ddd8;border-radius:4px;font-family:inherit">
      <div style="display:flex;gap:6px;justify-content:space-between">
        <button type="submit" class="btn-adm btn-adm-sm" style="font-size:.7rem">Sauver alt</button>
        <!-- Copier URL -->
        <button type="button" class="btn-adm btn-adm-sm" style="font-size:.7rem"
                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($file_url, ENT_QUOTES) ?>');this.textContent='✓ Copié'">
          📋 URL
        </button>
      </div>
    </form>

    <!-- Supprimer -->
    <form method="POST" class="media-actions" style="padding-top:0">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$mf['id'] ?>">
      <button type="submit" class="btn-adm btn-adm-sm btn-adm-danger"
              style="width:100%;font-size:.7rem"
              onclick="return confirm('Supprimer ce fichier définitivement ?')">
        ✕ Supprimer
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-bottom:28px;flex-wrap:wrap">
  <?php for ($pg = 1; $pg <= $total_pages; $pg++): ?>
    <a href="media.php?type=<?= $filter_type ?>&p=<?= $pg ?>"
       style="padding:6px 12px;border-radius:6px;font-size:.8rem;font-weight:700;text-decoration:none;border:1.5px solid <?= $pg===$filter_page ? 'var(--primary,#ea5649)' : '#e2ddd8' ?>;color:<?= $pg===$filter_page ? '#fff' : '#6b7f96' ?>;background:<?= $pg===$filter_page ? 'var(--primary,#ea5649)' : '#fff' ?>">
      <?= $pg ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; // !empty($files) ?>

<!-- Formulaire d'upload -->
<div id="upload-form"
     style="background:#fff;border-radius:12px;border:1.5px solid #e2ddd8;padding:28px 28px 24px">
  <h2 style="font-size:1rem;font-weight:800;color:#0c1e2e;margin:0 0 20px">
    ↑ Uploader un fichier
  </h2>
  <p style="font-size:.82rem;color:#6b7f96;margin-bottom:20px">
    Formats acceptés : JPEG, PNG, GIF, WebP, SVG, MP4, WebM, PDF — Max 20 Mo.
  </p>

  <form method="POST" action="media.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div style="grid-column:1/-1">
        <label class="adm-label">Fichier <span style="color:#ea5649">*</span></label>
        <input type="file" name="media_file" required
               accept="image/*,video/mp4,video/webm,application/pdf"
               style="width:100%;padding:8px 12px;border:2px dashed #c8bfb4;border-radius:8px;font-size:.88rem;background:#f8f4ef;cursor:pointer">
      </div>
      <div>
        <label class="adm-label">Texte alternatif (SEO / accessibilité)</label>
        <input type="text" name="alt_text" class="adm-input" placeholder="Description courte du fichier" maxlength="255">
      </div>
      <div>
        <label class="adm-label">Contexte (source_type optionnel)</label>
        <select name="source_type" class="adm-input">
          <option value="">— Aucun —</option>
          <option value="article">Article Les Échos</option>
          <option value="mission">Mission</option>
          <option value="rando">Rando</option>
          <option value="clan">Clan</option>
          <option value="misc">Divers</option>
        </select>
      </div>
    </div>

    <div style="margin-top:20px">
      <button type="submit" class="btn-adm btn-adm-primary">Uploader →</button>
    </div>
  </form>
</div>

<?php require_once '_admin-footer.php'; ?>
