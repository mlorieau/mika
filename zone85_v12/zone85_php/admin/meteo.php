<?php
// ============================================================
// ZONE85 — Admin : Météo — Gestion des posts météo
// ============================================================
$admin_current    = 'meteo';
$admin_page_title = 'Météo Zone85';

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

            if ($action === 'create') {
                $title     = trim($_POST['title'] ?? '');
                $body      = trim($_POST['body'] ?? '');
                $icon      = trim($_POST['weather_icon'] ?? '');
                $temp      = $_POST['temperature'] !== '' ? (int)$_POST['temperature'] : null;
                $condition = trim($_POST['weather_condition'] ?? '');
                $is_alert  = isset($_POST['is_alert']) ? 1 : 0;
                $is_event  = isset($_POST['is_event']) ? 1 : 0;
                $pub_at    = trim($_POST['published_at'] ?? '') ?: date('Y-m-d H:i:s');
                $exp_at    = trim($_POST['expires_at'] ?? '') ?: null;
                $mission_id = (int)($_POST['mission_id'] ?? 0) ?: null;

                if (!$title) {
                    $flash_msg = 'Le titre est obligatoire.';
                    $flash_type = 'err';
                } else {
                    $uid = (int)(current_user()['id'] ?? 0);
                    $pdo->prepare("
                        INSERT INTO weather_posts
                            (title, body, weather_icon, temperature, weather_condition,
                             is_alert, is_event, mission_id, published_at, expires_at, created_by)
                        VALUES
                            (:title, :body, :icon, :temp, :cond,
                             :alert, :event, :mid, :pub, :exp, :uid)
                    ")->execute([
                        ':title' => $title, ':body' => $body ?: null,
                        ':icon'  => $icon ?: null, ':temp' => $temp,
                        ':cond'  => $condition ?: null,
                        ':alert' => $is_alert, ':event' => $is_event,
                        ':mid'   => $mission_id, ':pub' => $pub_at,
                        ':exp'   => $exp_at, ':uid' => $uid,
                    ]);
                    $flash_msg = 'Post météo créé.';
                }

            } elseif ($action === 'toggle_alert' && $id > 0) {
                $cur = (int)($pdo->query("SELECT is_alert FROM weather_posts WHERE id={$id}")->fetchColumn());
                $pdo->prepare("UPDATE weather_posts SET is_alert = :v WHERE id = :id")
                    ->execute([':v' => $cur ? 0 : 1, ':id' => $id]);
                $flash_msg = $cur ? 'Alerte désactivée.' : 'Alerte activée.';

            } elseif ($action === 'delete' && $id > 0) {
                $pdo->prepare("DELETE FROM weather_posts WHERE id = :id")->execute([':id' => $id]);
                $flash_msg = 'Post météo supprimé.';
            }

        } catch (PDOException $e) {
            error_log('[admin/meteo] ' . $e->getMessage());
            $flash_msg  = 'Erreur base de données.';
            $flash_type = 'err';
        }
    }
    // PRG pour éviter resoumission
    header('Location: meteo.php' . ($flash_msg ? '?flash=' . urlencode($flash_msg) . '&ft=' . $flash_type : ''));
    exit;
}

// Flash depuis redirect
if (!$flash_msg && isset($_GET['flash'])) {
    $flash_msg  = $_GET['flash'];
    $flash_type = $_GET['ft'] ?? 'ok';
}

// ── Chargement données ────────────────────────────────────────
$posts       = [];
$missions_active = [];

if ($pdo) try {
    $posts = $pdo->query("
        SELECT wp.*, u.pseudo AS author
        FROM weather_posts wp
        LEFT JOIN users u ON u.id = wp.created_by
        ORDER BY wp.published_at DESC
        LIMIT 50
    ")->fetchAll();

    $missions_active = $pdo->query("
        SELECT id, title FROM missions
        WHERE status = 'active' AND mission_type = 'weather_mission'
        ORDER BY title ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/meteo] load : ' . $e->getMessage());
}

// Statistiques rapides
$nb_alerts  = count(array_filter($posts, fn($p) => (int)$p['is_alert'] === 1));
$nb_active  = count(array_filter($posts, fn($p) => !$p['expires_at'] || strtotime($p['expires_at']) > time()));

$ICONS = ['☀️','🌤','⛅','🌥','☁️','🌦','🌧','⛈','🌩','🌨','❄️','🌬','💨','🌫','🌊','🌈'];

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">🌦️ Météo Zone85</h1>
    <p class="adm-page-sub">Gestion des posts météo, alertes et bulletins vendéens.</p>
  </div>
  <div class="adm-page-actions">
    <button class="btn-adm btn-adm-primary"
            onclick="document.getElementById('create-form').scrollIntoView({behavior:'smooth'})">
      + Nouveau post météo
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
<div class="adm-kpi-row" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:28px">
  <div class="adm-kpi-card" style="flex:1;min-width:140px;background:#fff;border-radius:10px;padding:16px 20px;border:1.5px solid #e2ddd8">
    <div style="font-size:1.8rem;font-weight:900;color:#0c1e2e"><?= count($posts) ?></div>
    <div style="font-size:.75rem;font-weight:600;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Posts total</div>
  </div>
  <div class="adm-kpi-card" style="flex:1;min-width:140px;background:#fff;border-radius:10px;padding:16px 20px;border:1.5px solid #e2ddd8">
    <div style="font-size:1.8rem;font-weight:900;color:#ea5649"><?= $nb_alerts ?></div>
    <div style="font-size:.75rem;font-weight:600;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Alertes actives</div>
  </div>
  <div class="adm-kpi-card" style="flex:1;min-width:140px;background:#fff;border-radius:10px;padding:16px 20px;border:1.5px solid #e2ddd8">
    <div style="font-size:1.8rem;font-weight:900;color:#2a9d5c"><?= $nb_active ?></div>
    <div style="font-size:.75rem;font-weight:600;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">En cours / non expirés</div>
  </div>
</div>

<!-- Liste des posts -->
<div class="adm-section" style="margin-bottom:36px">
  <h2 class="adm-section-title" style="font-size:1rem;font-weight:800;color:#0c1e2e;margin-bottom:16px">Derniers posts (50)</h2>

  <?php if (empty($posts)): ?>
  <div style="text-align:center;padding:40px;color:#6b7f96;background:#fff;border-radius:10px;border:1.5px solid #e2ddd8">
    Aucun post météo pour l'instant. Créez le premier ci-dessous.
  </div>
  <?php else: ?>
  <div style="background:#fff;border-radius:10px;border:1.5px solid #e2ddd8;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem">
      <thead>
        <tr style="background:#f8f4ef;border-bottom:2px solid #e2ddd8">
          <th style="padding:10px 16px;text-align:left;font-size:.72rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Titre</th>
          <th style="padding:10px 16px;text-align:center;font-size:.72rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Icône / °C</th>
          <th style="padding:10px 16px;text-align:center;font-size:.72rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Alerte</th>
          <th style="padding:10px 16px;text-align:left;font-size:.72rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Publié le</th>
          <th style="padding:10px 16px;text-align:left;font-size:.72rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Expiration</th>
          <th style="padding:10px 16px;text-align:right;font-size:.72rem;font-weight:700;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $post):
          $expired  = $post['expires_at'] && strtotime($post['expires_at']) <= time();
          $is_alert = (int)$post['is_alert'] === 1;
        ?>
        <tr style="border-bottom:1px solid #f0ece7<?= $expired ? ';opacity:.6' : '' ?>">
          <td style="padding:12px 16px">
            <div style="font-weight:700;color:#0c1e2e"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($post['body']): ?>
              <div style="font-size:.78rem;color:#6b7f96;margin-top:2px;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;text-align:center">
            <?= $post['weather_icon'] ? '<span style="font-size:1.4rem">' . htmlspecialchars($post['weather_icon'], ENT_QUOTES, 'UTF-8') . '</span>' : '—' ?>
            <?php if ($post['temperature'] !== null): ?>
              <div style="font-size:.75rem;font-weight:700;color:#0c1e2e"><?= (int)$post['temperature'] ?>°C</div>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;text-align:center">
            <?php if ($is_alert): ?>
              <span style="background:#fef0ef;color:#c0392b;border:1px solid rgba(192,57,43,.3);border-radius:12px;font-size:.7rem;font-weight:800;padding:2px 9px">🚨 ALERTE</span>
            <?php else: ?>
              <span style="color:#b0b8c8;font-size:.8rem">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;font-size:.8rem;color:#6b7f96">
            <?= $post['published_at'] ? date('d/m/Y H:i', strtotime($post['published_at'])) : '—' ?>
          </td>
          <td style="padding:12px 16px;font-size:.8rem;color:<?= $expired ? '#c0392b' : '#6b7f96' ?>">
            <?php if ($post['expires_at']): ?>
              <?= date('d/m/Y H:i', strtotime($post['expires_at'])) ?>
              <?= $expired ? ' <span style="font-weight:700">(expiré)</span>' : '' ?>
            <?php else: ?>
              <span style="color:#2a9d5c;font-weight:600">Permanent</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px 16px;text-align:right;white-space:nowrap">
            <!-- Toggle alerte -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_alert">
              <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
              <button type="submit" class="btn-adm btn-adm-sm" title="<?= $is_alert ? 'Désactiver alerte' : 'Activer alerte' ?>"
                      style="background:<?= $is_alert ? '#fef0ef' : '' ?>">
                <?= $is_alert ? '🔕' : '🚨' ?>
              </button>
            </form>
            <!-- Supprimer -->
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('Supprimer ce post météo ?')">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
              <button type="submit" class="btn-adm btn-adm-sm btn-adm-danger">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Formulaire de création -->
<div id="create-form" class="adm-section"
     style="background:#fff;border-radius:12px;border:1.5px solid #e2ddd8;padding:28px 28px 24px">
  <h2 class="adm-section-title" style="font-size:1rem;font-weight:800;color:#0c1e2e;margin-bottom:20px">
    + Nouveau post météo
  </h2>

  <form method="POST" action="meteo.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <!-- Titre -->
      <div style="grid-column:1/-1">
        <label class="adm-label">Titre <span style="color:#ea5649">*</span></label>
        <input type="text" name="title" class="adm-input" placeholder="Ex : Beau temps sur la Vendée" required maxlength="200">
      </div>

      <!-- Corps -->
      <div style="grid-column:1/-1">
        <label class="adm-label">Corps / description</label>
        <textarea name="body" class="adm-input" rows="3"
                  placeholder="Détails du bulletin météo..."></textarea>
      </div>

      <!-- Icône emoji -->
      <div>
        <label class="adm-label">Icône météo</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input type="text" name="weather_icon" id="icon-input" class="adm-input"
                 style="flex:1;max-width:80px" placeholder="☀️" maxlength="10">
          <div style="display:flex;flex-wrap:wrap;gap:4px;max-width:280px">
            <?php foreach ($ICONS as $ico): ?>
              <button type="button"
                      onclick="document.getElementById('icon-input').value='<?= $ico ?>'"
                      style="background:none;border:1px solid #e2ddd8;border-radius:4px;padding:2px 6px;cursor:pointer;font-size:1.1rem"
                      title="<?= $ico ?>"><?= $ico ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Température -->
      <div>
        <label class="adm-label">Température (°C)</label>
        <input type="number" name="temperature" class="adm-input" placeholder="Ex : 22" min="-20" max="50">
      </div>

      <!-- Condition -->
      <div>
        <label class="adm-label">Condition météo (texte)</label>
        <input type="text" name="weather_condition" class="adm-input" placeholder="Ex : Ensoleillé avec quelques nuages" maxlength="100">
      </div>

      <!-- Mission liée -->
      <div>
        <label class="adm-label">Mission liée (optionnel)</label>
        <select name="mission_id" class="adm-input">
          <option value="">— Aucune —</option>
          <?php foreach ($missions_active as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Date publication -->
      <div>
        <label class="adm-label">Date de publication</label>
        <input type="datetime-local" name="published_at" class="adm-input"
               value="<?= date('Y-m-d\TH:i') ?>">
      </div>

      <!-- Date expiration -->
      <div>
        <label class="adm-label">Expiration (laisser vide = permanent)</label>
        <input type="datetime-local" name="expires_at" class="adm-input">
      </div>

      <!-- Flags -->
      <div style="grid-column:1/-1;display:flex;gap:24px;align-items:center;padding:12px 0 4px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.88rem;font-weight:600;color:#0c1e2e">
          <input type="checkbox" name="is_alert" value="1">
          🚨 C'est une alerte météo
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.88rem;font-weight:600;color:#0c1e2e">
          <input type="checkbox" name="is_event" value="1">
          📅 C'est un événement météo
        </label>
      </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:12px;align-items:center">
      <button type="submit" class="btn-adm btn-adm-primary">Publier le post météo</button>
      <button type="reset" class="btn-adm">Effacer</button>
    </div>
  </form>
</div>

<?php require_once '_admin-footer.php'; ?>
