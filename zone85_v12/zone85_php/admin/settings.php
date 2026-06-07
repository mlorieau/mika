<?php
// ============================================================
// admin/settings.php — Paramètres & Intégrations Tierces V10.1
// ============================================================
$admin_current    = 'settings';
$admin_page_title = 'Paramètres';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/settings.php';

require_admin();

$pdo   = db();
$flash = null;

// ── Catégories et labels ──────────────────────────────────────
$cat_meta = [
    'general'   => ['label' => 'Général',          'icon' => '⚙️',  'desc' => 'Informations de base du site'],
    'brevo'     => ['label' => 'Brevo (Emails)',    'icon' => '📧',  'desc' => 'Emails transactionnels via Brevo API'],
    'analytics' => ['label' => 'Analytics',         'icon' => '📊',  'desc' => 'Google Analytics 4 et Matomo'],
    'pwa'       => ['label' => 'PWA',               'icon' => '📱',  'desc' => 'Progressive Web App — installation mobile'],
    'social'    => ['label' => 'Réseaux sociaux',   'icon' => '🌐',  'desc' => 'Liens vers les réseaux Zone85'],
    'push'      => ['label' => 'Notifications Push','icon' => '🔔',  'desc' => 'Notifications push Web (VAPID)'],
];

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } elseif (!$pdo) {
        $flash = ['type' => 'err', 'msg' => 'Base de données non disponible.'];
    } else {
        $saved_cat = $_POST['category'] ?? '';
        $data      = [];

        // Récupérer les définitions de la catégorie pour traiter correctement les types
        $cat_defs = get_settings_by_category($saved_cat);

        foreach ($cat_defs as $def) {
            $key   = $def['setting_key'];
            $type  = $def['setting_type'];

            if ($type === 'boolean') {
                $data[$key] = isset($_POST[$key]) ? '1' : '0';
            } elseif ($type === 'password') {
                // Ne sauvegarder que si renseigné (ne pas écraser avec "")
                $val = trim($_POST[$key] ?? '');
                if ($val !== '' && $val !== '••••••••') {
                    $data[$key] = $val;
                }
            } elseif ($type === 'number') {
                $data[$key] = (string)(int)($_POST[$key] ?? 0);
            } else {
                $data[$key] = trim($_POST[$key] ?? '');
            }
        }

        if (save_settings($data)) {
            $flash = ['type' => 'ok', 'msg' => 'Paramètres "' . ($cat_meta[$saved_cat]['label'] ?? $saved_cat) . '" enregistrés.'];
        } else {
            $flash = ['type' => 'err', 'msg' => 'Erreur lors de la sauvegarde.'];
        }
    }
}

// ── Onglet actif ──────────────────────────────────────────────
$active_cat = $_GET['cat'] ?? 'general';
if (!array_key_exists($active_cat, $cat_meta)) $active_cat = 'general';

// ── Charger les settings de toutes les catégories ────────────
$all_settings = [];
foreach (array_keys($cat_meta) as $cat) {
    $all_settings[$cat] = get_settings_by_category($cat);
}

require_once '_admin-header.php';
?>

<style>
/* ── SETTINGS PAGE ── */
.sett-layout { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: start; }
.sett-nav    { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(12,30,46,.06); border: 1px solid rgba(18,49,78,.07); overflow: hidden; position: sticky; top: 80px; }
.sett-nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 18px; font-size: .84rem; font-weight: 600;
  color: #5a7a96; cursor: pointer; border: none; background: none;
  width: 100%; text-align: left; font-family: 'Inter', sans-serif;
  border-left: 3px solid transparent; transition: all .15s;
  text-decoration: none;
}
.sett-nav-item:hover { background: #f8f4ef; color: #0c1e2e; }
.sett-nav-item.active { background: rgba(234,86,73,.06); color: #ea5649; border-left-color: #ea5649; font-weight: 800; }
.sett-nav-icon { font-size: 1.05rem; width: 22px; text-align: center; flex-shrink: 0; }
.sett-nav-sep { height: 1px; background: #f0ece7; margin: 4px 0; }

.sett-panel  { display: none; }
.sett-panel.active { display: block; }

/* Form fields */
.sett-section-title {
  font-size: .68rem; font-weight: 700; letter-spacing: .12em;
  text-transform: uppercase; color: #6b7f96; margin-bottom: 20px;
  padding-bottom: 10px; border-bottom: 1px solid #f0ece7;
  display: flex; align-items: center; gap: 8px;
}
.sett-field  { margin-bottom: 20px; }
.sett-label  { display: flex; align-items: center; gap: 8px; font-size: .78rem; font-weight: 700; color: #3d5166; margin-bottom: 6px; }
.sett-label .sett-sensitive { font-size: .62rem; background: rgba(234,86,73,.1); color: #ea5649; padding: 1px 7px; border-radius: 4px; font-weight: 700; }
.sett-label .sett-required  { color: #ea5649; margin-left: 2px; }
.sett-input, .sett-textarea, .sett-select {
  width: 100%; padding: 10px 14px; border-radius: 8px;
  border: 1.5px solid #d0cbc5; font-family: 'Inter', sans-serif;
  font-size: .88rem; color: #0f1e2d; background: #fff;
  transition: border-color .15s; box-sizing: border-box;
}
.sett-input:focus, .sett-textarea:focus, .sett-select:focus {
  outline: none; border-color: #ea5649; box-shadow: 0 0 0 3px rgba(234,86,73,.1);
}
.sett-textarea { resize: vertical; min-height: 80px; }
.sett-hint     { font-size: .72rem; color: #6b7f96; margin-top: 5px; line-height: 1.5; }
.sett-desc     { font-size: .82rem; color: #6b7f96; margin-bottom: 24px; padding: 14px 18px; background: #f8f4ef; border-radius: 8px; line-height: 1.6; }

/* Toggle switch */
.sett-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #f0ece7; }
.sett-toggle-row:last-of-type { border-bottom: none; }
.sett-toggle-info .sett-toggle-label { font-size: .88rem; font-weight: 700; color: #0f1e2d; margin-bottom: 2px; }
.sett-toggle-info .sett-toggle-hint  { font-size: .74rem; color: #6b7f96; }
.sett-toggle {
  position: relative; display: inline-block; width: 48px; height: 26px; flex-shrink: 0;
}
.sett-toggle input { opacity: 0; width: 0; height: 0; }
.sett-toggle-slider {
  position: absolute; cursor: pointer; inset: 0; border-radius: 999px;
  background: #d0cbc5; transition: .2s;
}
.sett-toggle-slider::before {
  content: ''; position: absolute; width: 20px; height: 20px;
  left: 3px; top: 3px; background: #fff; border-radius: 50%;
  transition: .2s; box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.sett-toggle input:checked + .sett-toggle-slider { background: #ea5649; }
.sett-toggle input:checked + .sett-toggle-slider::before { transform: translateX(22px); }

/* Status badge */
.sett-status { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
.sett-status-ok  { background: rgba(42,157,92,.1); color: #1a7a42; }
.sett-status-off { background: rgba(107,127,150,.1); color: #4a6073; }
.sett-status-warn{ background: rgba(201,150,42,.1); color: #8a6020; }

/* Color input */
input[type="color"].sett-input { padding: 4px 8px; height: 42px; cursor: pointer; }

/* Save btn row */
.sett-save-row { display: flex; align-items: center; gap: 14px; margin-top: 28px; padding-top: 20px; border-top: 1px solid #f0ece7; }
.sett-btn-save { background: #ea5649; color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: .88rem; font-weight: 800; cursor: pointer; transition: opacity .15s; }
.sett-btn-save:hover { opacity: .88; }

/* Test connection button */
.sett-btn-test { background: transparent; color: #ea5649; border: 1.5px solid #ea5649; padding: 10px 20px; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: .84rem; font-weight: 700; cursor: pointer; transition: all .15s; }
.sett-btn-test:hover { background: rgba(234,86,73,.06); }
#test-result { font-size: .82rem; font-weight: 600; }

@media(max-width:700px) {
  .sett-layout { grid-template-columns: 1fr; }
  .sett-nav { position: static; display: flex; flex-wrap: wrap; gap: 4px; padding: 8px; }
  .sett-nav-item { flex: 1 1 auto; border-left: none; border-bottom: 3px solid transparent; justify-content: center; padding: 8px 12px; border-radius: 6px; }
  .sett-nav-item.active { border-bottom-color: #ea5649; }
  .sett-nav-sep { display: none; }
}
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">⚙️ Paramètres</h1>
    <p class="adm-page-sub">Intégrations tierces, emails, analytics, PWA et réseaux sociaux.</p>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>">
  <?= $flash['type'] === 'ok' ? '✅' : '❌' ?> <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<div class="sett-layout">

  <!-- ── Navigation latérale ─────────────────────────────── -->
  <nav class="sett-nav" id="sett-nav">
    <?php foreach ($cat_meta as $cat_key => $cat_info): ?>
    <button class="sett-nav-item <?= $active_cat === $cat_key ? 'active' : '' ?>"
      onclick="switchSettCat('<?= $cat_key ?>')">
      <span class="sett-nav-icon"><?= $cat_info['icon'] ?></span>
      <?= htmlspecialchars($cat_info['label'], ENT_QUOTES, 'UTF-8') ?>
    </button>
    <?php if ($cat_key === 'general' || $cat_key === 'brevo'): ?>
    <div class="sett-nav-sep"></div>
    <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- ── Panneaux ────────────────────────────────────────── -->
  <div class="sett-panels">

    <?php foreach ($cat_meta as $cat_key => $cat_info): ?>
    <div class="sett-panel <?= $active_cat === $cat_key ? 'active' : '' ?>"
         id="sett-panel-<?= $cat_key ?>">

      <!-- Card principale -->
      <div class="adm-card">
        <div class="sett-section-title">
          <?= $cat_info['icon'] ?> <?= htmlspecialchars($cat_info['label'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="sett-desc"><?= htmlspecialchars($cat_info['desc'], ENT_QUOTES, 'UTF-8') ?></div>

        <form method="POST" action="settings.php?cat=<?= $cat_key ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="category" value="<?= $cat_key ?>">

          <?php
          $fields = $all_settings[$cat_key] ?? [];

          // Séparer boolean des autres
          $bool_fields  = array_filter($fields, fn($f) => $f['setting_type'] === 'boolean');
          $other_fields = array_filter($fields, fn($f) => $f['setting_type'] !== 'boolean');
          ?>

          <!-- Champs texte / input -->
          <?php if (!empty($other_fields)): ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:0 20px">
            <?php foreach ($other_fields as $field):
              $fkey   = $field['setting_key'];
              $ftype  = $field['setting_type'];
              $fval   = $field['setting_value'] ?? '';
              $flabel = $field['label'];
              $fdesc  = $field['description'] ?? '';
              $fph    = $field['placeholder'] ?? '';
              $fsens  = (bool)$field['is_sensitive'];
              $freq   = (bool)$field['is_required'];
            ?>
            <div class="sett-field">
              <label class="sett-label" for="sett_<?= $fkey ?>">
                <?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($freq): ?><span class="sett-required">*</span><?php endif; ?>
                <?php if ($fsens): ?><span class="sett-sensitive">Confidentiel</span><?php endif; ?>
              </label>

              <?php if ($ftype === 'textarea'): ?>
                <textarea id="sett_<?= $fkey ?>" name="<?= $fkey ?>"
                  class="sett-textarea"
                  placeholder="<?= htmlspecialchars($fph, ENT_QUOTES, 'UTF-8') ?>"
                ><?= htmlspecialchars($fval, ENT_QUOTES, 'UTF-8') ?></textarea>

              <?php elseif ($ftype === 'select'): ?>
                <select id="sett_<?= $fkey ?>" name="<?= $fkey ?>" class="sett-select">
                  <?php
                  $select_opts = [
                    'pwa_display' => ['standalone' => 'Standalone (plein écran)', 'fullscreen' => 'Fullscreen', 'minimal-ui' => 'Minimal UI', 'browser' => 'Navigateur'],
                  ];
                  $opts = $select_opts[$fkey] ?? [];
                  foreach ($opts as $oval => $olabel):
                  ?>
                  <option value="<?= $oval ?>" <?= $fval === $oval ? 'selected' : '' ?>><?= $olabel ?></option>
                  <?php endforeach; ?>
                </select>

              <?php elseif ($ftype === 'color'): ?>
                <div style="display:flex;align-items:center;gap:10px">
                  <input type="color" id="sett_<?= $fkey ?>" name="<?= $fkey ?>"
                    class="sett-input" value="<?= htmlspecialchars($fval ?: $fph ?: '#000000', ENT_QUOTES, 'UTF-8') ?>">
                  <code style="font-size:.82rem;color:#6b7f96" id="color-val-<?= $fkey ?>"><?= htmlspecialchars($fval ?: $fph ?: '#000000', ENT_QUOTES, 'UTF-8') ?></code>
                </div>

              <?php else: ?>
                <input
                  type="<?= $ftype === 'password' ? 'password' : ($ftype === 'email' ? 'email' : ($ftype === 'url' ? 'url' : ($ftype === 'number' ? 'number' : 'text'))) ?>"
                  id="sett_<?= $fkey ?>" name="<?= $fkey ?>"
                  class="sett-input"
                  value="<?= $fsens && !empty($fval) ? '••••••••' : htmlspecialchars($fval, ENT_QUOTES, 'UTF-8') ?>"
                  placeholder="<?= htmlspecialchars($fph, ENT_QUOTES, 'UTF-8') ?>"
                  <?= $ftype === 'password' ? 'autocomplete="new-password"' : '' ?>
                  <?= $ftype === 'number' ? 'min="0"' : '' ?>
                >
                <?php if ($fsens && !empty($fval)): ?>
                <div class="sett-hint" style="color:#C9962A">
                  ⚠️ Une valeur est déjà configurée. Saisissez une nouvelle valeur pour la remplacer, ou laissez ce champ vide pour conserver l'existante.
                </div>
                <?php endif; ?>
              <?php endif; ?>

              <?php if ($fdesc): ?>
              <div class="sett-hint"><?= htmlspecialchars($fdesc, ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Toggles boolean -->
          <?php if (!empty($bool_fields)): ?>
          <div style="margin-top:<?= !empty($other_fields) ? '24px' : '0' ?>">
            <div style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7f96;margin-bottom:12px">
              Activation
            </div>
            <?php foreach ($bool_fields as $field):
              $fkey  = $field['setting_key'];
              $fval  = $field['setting_value'];
              $fdesc = $field['description'] ?? '';
              $is_on = in_array($fval, ['1','true','yes','on'], true);
            ?>
            <div class="sett-toggle-row">
              <div class="sett-toggle-info">
                <div class="sett-toggle-label"><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($fdesc): ?><div class="sett-toggle-hint"><?= htmlspecialchars($fdesc, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
              </div>
              <label class="sett-toggle">
                <input type="checkbox" name="<?= $fkey ?>" <?= $is_on ? 'checked' : '' ?>>
                <span class="sett-toggle-slider"></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Bouton Tester pour Brevo -->
          <?php if ($cat_key === 'brevo'): ?>
          <div style="margin-top:20px;padding:16px;background:#f8f4ef;border-radius:10px">
            <div style="font-size:.78rem;font-weight:700;color:#3d5166;margin-bottom:10px">Tester la connexion Brevo</div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
              <input type="email" id="brevo-test-email" placeholder="votre@email.fr"
                style="padding:9px 14px;border-radius:7px;border:1.5px solid #d0cbc5;font-family:inherit;font-size:.84rem;min-width:220px">
              <button type="button" class="sett-btn-test" onclick="testBrevo()">Envoyer un email test</button>
              <span id="test-result"></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- Statut actuel pour Analytics -->
          <?php if ($cat_key === 'analytics'): ?>
          <?php
          $ga4_val = get_setting('ga4_measurement_id', '');
          $mat_url = get_setting('matomo_url', '');
          $gsc_tok = get_setting('google_search_console_verification', '');
          ?>
          <div style="margin-top:20px;padding:14px 18px;background:#f8f4ef;border-radius:10px;display:flex;gap:12px;flex-wrap:wrap">
            <span class="sett-status <?= !empty($ga4_val) ? 'sett-status-ok' : 'sett-status-off' ?>">
              📊 GA4 : <?= !empty($ga4_val) ? 'Configuré (' . htmlspecialchars(substr($ga4_val, 0, 10), ENT_QUOTES, 'UTF-8') . '…)' : 'Non configuré' ?>
            </span>
            <span class="sett-status <?= !empty($mat_url) ? 'sett-status-ok' : 'sett-status-off' ?>">
              📈 Matomo : <?= !empty($mat_url) ? 'Configuré' : 'Non configuré' ?>
            </span>
            <span class="sett-status <?= !empty($gsc_tok) ? 'sett-status-ok' : 'sett-status-off' ?>">
              🔍 Google Search Console : <?= !empty($gsc_tok) ? 'Vérifié' : 'Non configuré' ?>
            </span>
          </div>
          <?php endif; ?>

          <!-- Statut Brevo -->
          <?php if ($cat_key === 'brevo'): ?>
          <?php
          $brevo_key = get_setting('brevo_api_key', '');
          $brevo_on  = get_setting('brevo_enabled', false);
          ?>
          <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
            <span class="sett-status <?= !empty($brevo_key) ? 'sett-status-ok' : 'sett-status-warn' ?>">
              🔑 Clé API : <?= !empty($brevo_key) ? 'Configurée' : 'Manquante' ?>
            </span>
            <span class="sett-status <?= $brevo_on ? 'sett-status-ok' : 'sett-status-off' ?>">
              <?= $brevo_on ? '✅ Brevo actif' : '⏸ Brevo désactivé (fallback mail())' ?>
            </span>
          </div>
          <?php endif; ?>

          <div class="sett-save-row">
            <button type="submit" class="sett-btn-save">Enregistrer</button>
            <span style="font-size:.78rem;color:#6b7f96">Les modifications sont appliquées immédiatement.</span>
          </div>
        </form>
      </div>

      <!-- Card info/aide si disponible -->
      <?php if ($cat_key === 'brevo'): ?>
      <div class="adm-card" style="background:rgba(14,165,233,.04);border:1px solid rgba(14,165,233,.15)">
        <div class="adm-card-title" style="color:#0369a1">📖 Guide Brevo</div>
        <ol style="font-size:.84rem;color:#0f1e2d;line-height:1.9;margin:0;padding-left:20px">
          <li>Créer un compte sur <a href="https://brevo.com" target="_blank" style="color:#ea5649">brevo.com</a></li>
          <li>Menu <strong>SMTP &amp; API</strong> → <strong>API Keys</strong> → Créer une clé</li>
          <li>Copier la clé <code>xkeysib-...</code> dans le champ ci-dessus</li>
          <li>Activer Brevo avec le toggle</li>
          <li>Configurer les templates dans <strong>admin/email_templates</strong> (à venir)</li>
        </ol>
        <div style="margin-top:12px;font-size:.78rem;color:#0369a1;background:rgba(14,165,233,.08);padding:10px 14px;border-radius:7px">
          Sans clé API, les emails sont envoyés via <code>mail()</code> PHP natif (peut atterrir en spam).
        </div>
      </div>
      <?php endif; ?>

      <?php if ($cat_key === 'analytics'): ?>
      <div class="adm-card" style="background:rgba(42,157,92,.04);border:1px solid rgba(42,157,92,.15)">
        <div class="adm-card-title" style="color:#1a7a42">📖 Guide Analytics &amp; GSC</div>
        <p style="font-size:.84rem;color:#0f1e2d;line-height:1.7;margin:0 0 10px">
          <strong>GA4</strong> : Créer une propriété sur <a href="https://analytics.google.com" target="_blank" style="color:#ea5649">analytics.google.com</a>,
          copier l'ID de mesure (G-XXXXXXXXXX).<br>
          <strong>Matomo</strong> : Alternative self-hosted. Renseigner l'URL et l'ID du site.<br>
          <strong>Google Search Console</strong> : Dans GSC → Paramètres → Vérification → Balise HTML.
          Copier uniquement la valeur du <code>content="..."</code> (pas toute la balise).<br>
          <strong>Note</strong> : Un seul outil analytics actif à la fois. GA4 est prioritaire si configuré.
        </p>
        <div style="font-size:.78rem;color:#1a7a42;background:rgba(42,157,92,.08);padding:10px 14px;border-radius:7px;margin-bottom:10px">
          Les événements Zone85 trackés : signup, login, mission_start, mission_complete, collectible_found, badge_unlock, pwa_install.
        </div>
        <?php
        $gsc_site_url = rtrim(get_setting('site_url', defined('SITE_URL') ? SITE_URL : ''), '/');
        ?>
        <div style="font-size:.78rem;color:#0369a1;background:rgba(14,165,233,.06);padding:10px 14px;border-radius:7px">
          🗺️ Sitemap disponible à : <code><?= htmlspecialchars($gsc_site_url, ENT_QUOTES, 'UTF-8') ?>/sitemap.php</code>
          — À soumettre dans Google Search Console → Sitemaps.
        </div>
      </div>
      <?php endif; ?>

      <?php if ($cat_key === 'pwa'): ?>
      <div class="adm-card" style="background:rgba(201,150,42,.04);border:1px solid rgba(201,150,42,.15)">
        <div class="adm-card-title" style="color:#8a6020">📖 Guide PWA</div>
        <p style="font-size:.84rem;color:#0f1e2d;line-height:1.7;margin:0 0 10px">
          La PWA nécessite que les icônes PNG soient générées et placées dans <code>assets/img/pwa/</code>.<br>
          Utiliser <a href="https://maskable.app/editor" target="_blank" style="color:#ea5649">maskable.app</a> ou
          <a href="https://pwabuilder.com" target="_blank" style="color:#ea5649">pwabuilder.com</a> avec le SVG <code>assets/img/pwa/icon.svg</code>.
        </p>
        <div style="font-size:.78rem;font-weight:700;color:#8a6020">Icônes requises : 72, 96, 128, 144, 152, 192, 384, 512 px</div>
      </div>
      <?php endif; ?>

      <?php if ($cat_key === 'push'): ?>
      <div class="adm-card" style="background:rgba(107,127,150,.05);border:1px solid rgba(107,127,150,.15)">
        <div class="adm-card-title">📖 Guide Notifications Push (VAPID)</div>
        <p style="font-size:.84rem;color:#0f1e2d;line-height:1.7;margin:0 0 10px">
          Générer une paire de clés VAPID avec la commande :<br>
          <code style="background:#f0ece7;padding:4px 10px;border-radius:5px;font-size:.8rem">
            npx web-push generate-vapid-keys
          </code>
        </p>
        <div style="font-size:.78rem;color:#6b7f96;background:#f8f4ef;padding:10px 14px;border-radius:7px">
          ⚠️ Cette fonctionnalité est en préparation. Les clés sont sauvegardées mais pas encore utilisées côté serveur.
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /sett-panel -->
    <?php endforeach; ?>

  </div><!-- /sett-panels -->
</div><!-- /sett-layout -->

<script>
function switchSettCat(cat) {
  // Panneaux
  document.querySelectorAll('.sett-panel').forEach(p => p.classList.remove('active'));
  const panel = document.getElementById('sett-panel-' + cat);
  if (panel) panel.classList.add('active');

  // Nav
  document.querySelectorAll('.sett-nav-item').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.sett-nav-item').forEach(b => {
    if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + cat + "'")) {
      b.classList.add('active');
    }
  });

  // MAJ URL
  const url = new URL(window.location.href);
  url.searchParams.set('cat', cat);
  history.replaceState(null, '', url.toString());
}

// Live preview couleur
document.querySelectorAll('input[type="color"]').forEach(input => {
  const label = document.getElementById('color-val-' + input.name);
  if (label) {
    input.addEventListener('input', () => { label.textContent = input.value; });
  }
});

// Test Brevo
async function testBrevo() {
  const email = document.getElementById('brevo-test-email').value.trim();
  const result = document.getElementById('test-result');
  if (!email) { result.textContent = '⚠️ Saisissez un email.'; result.style.color = '#C9962A'; return; }
  result.textContent = 'Envoi en cours…'; result.style.color = '#6b7f96';

  try {
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('test_email', email);

    const resp = await fetch('ajax/test-brevo.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await resp.json();
    if (data.ok) {
      result.textContent = '✅ Email envoyé à ' + email;
      result.style.color = '#1a7a42';
    } else {
      result.textContent = '❌ ' + (data.error || 'Erreur inconnue');
      result.style.color = '#c0392b';
    }
  } catch(e) {
    result.textContent = '❌ Erreur réseau.';
    result.style.color = '#c0392b';
  }
}
</script>

<?php require_once '_admin-footer.php'; ?>
