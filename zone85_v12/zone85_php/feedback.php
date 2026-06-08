<?php
// ============================================================
// ZONE 85 — feedback.php
// Page de retour bêta privée — testeurs invités
// UTF-8 sans BOM
// ============================================================
$page_title       = 'Retour bêta — Zone85';
$page_description = 'Tu fais partie des premiers testeurs de Zone85. Dis-nous ce que tu as vécu, ce qui marche et ce qui coince.';
$page_canonical   = 'https://www.zone85.fr/feedback.php';
$page_robots      = 'noindex,nofollow';
$current_page     = 'feedback';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/mailer.php';

// ── Traitement POST ───────────────────────────────────────────
$fb_success = false;
$fb_error   = '';

if (is_post_request()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $fb_error = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $fb_pseudo  = safe_input($_POST['pseudo']  ?? '', 50);
        $fb_email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $fb_clan    = in_array($_POST['clan'] ?? '', ['bocage','littoral','marais','aucun'], true) ? $_POST['clan'] : 'aucun';
        $fb_device  = in_array($_POST['device'] ?? '', ['mobile','desktop','both'], true) ? $_POST['device'] : 'desktop';
        $fb_note    = (int)($_POST['note'] ?? 0);
        $fb_areas   = array_intersect(
            $_POST['areas'] ?? [],
            ['inscription','connexion','missions','randos','profil','clans','classement','ktc','pwa','autre']
        );
        $fb_problems = safe_input($_POST['problems'] ?? '', 2000);
        $fb_suggest  = safe_input($_POST['suggest'] ?? '', 2000);
        $fb_rgpd     = !empty($_POST['rgpd_consent']);

        if (!$fb_pseudo || !$fb_email || !$fb_rgpd || $fb_note < 1 || $fb_note > 5) {
            $fb_error = 'Merci de remplir tous les champs obligatoires (pseudo, email, note) et d\'accepter la politique de confidentialité.';
        } else {
            $note_label = ['', '1/5 — Difficile', '2/5 — Décevant', '3/5 — Correct', '4/5 — Bien', '5/5 — Excellent'][$fb_note];
            $areas_str  = implode(', ', $fb_areas) ?: 'Non précisé';

            $msg_body = "Pseudo : $fb_pseudo\n"
                      . "Email : " . ($fb_email ?: 'non fourni') . "\n"
                      . "Clan : $fb_clan\n"
                      . "Appareil : $fb_device\n"
                      . "Note globale : $note_label\n"
                      . "Zones testées : $areas_str\n\n"
                      . "Problèmes trouvés :\n" . ($fb_problems ?: '(aucun signalé)') . "\n\n"
                      . "Suggestions :\n" . ($fb_suggest ?: '(aucune)');

            // Sauvegarde en base
            $pdo = db();
            if ($pdo) {
                try {
                    $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
                    $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                    $pdo->prepare("
                        INSERT INTO contact_messages (name, email, subject, reason, message, ip_hash, user_agent_hash)
                        VALUES (:name, :email, :subject, :reason, :message, :ip, :ua)
                    ")->execute([
                        ':name'    => $fb_pseudo,
                        ':email'   => $fb_email ?: '',
                        ':subject' => '[Bêta] Note ' . $fb_note . '/5 — ' . $fb_pseudo,
                        ':reason'  => 'beta_feedback',
                        ':message' => $msg_body,
                        ':ip'      => $ip_hash,
                        ':ua'      => $ua_hash,
                    ]);
                } catch (PDOException $e) {
                    error_log('[ZONE85] feedback DB : ' . $e->getMessage());
                }
            }

            // Notification admin
            $admin_email = get_setting('site_email', 'contact@zone85.fr');
            send_email(
                $admin_email,
                '[Zone85 Bêta] Retour de ' . $fb_pseudo . ' — ' . $note_label,
                'contact_message',
                [
                    'sender_name'  => $fb_pseudo,
                    'sender_email' => $fb_email ?: 'non fourni',
                    'reason_label' => 'Retour bêta privée',
                    'subject'      => 'Note ' . $fb_note . '/5',
                    'message'      => $msg_body,
                ]
            );

            $fb_success = true;
        }
    }
}

// Pré-remplir depuis session si connecté
$_logged_user = is_logged_in() ? current_user() : null;

// ── Styles ───────────────────────────────────────────────────
$page_styles = '<style>
.fb-hero{
  background:linear-gradient(135deg,#0c1e2e 0%,#12314e 60%,#0d2018 100%);
  padding:108px 0 56px;text-align:center;
}
.fb-hero h1{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;color:#fff;letter-spacing:-1px;margin-bottom:12px}
.fb-hero p{font-size:1rem;color:rgba(255,255,255,.6);max-width:520px;margin:0 auto;line-height:1.6}
.fb-beta-chip{
  display:inline-block;padding:5px 16px;border-radius:20px;
  background:rgba(234,86,73,.2);border:1.5px solid rgba(234,86,73,.4);
  color:#ea5649;font-size:.72rem;font-weight:800;letter-spacing:.1em;
  text-transform:uppercase;margin-bottom:18px;
}

.fb-page{background:var(--beige,#f8f4ef);padding:56px 0 96px}
.fb-grid{display:grid;grid-template-columns:1fr 2fr;gap:40px;max-width:960px;margin:0 auto;padding:0 24px}
@media(max-width:760px){.fb-grid{grid-template-columns:1fr;gap:24px}}

/* Sidebar checklist */
.fb-checklist-card{
  background:#fff;border-radius:16px;border:1px solid var(--beige-dark,#e8e0d4);
  padding:28px 24px;position:sticky;top:90px;height:fit-content;
}
.fb-checklist-title{
  font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
  color:var(--text-muted,#6b7f96);margin-bottom:16px;border-bottom:1px solid var(--beige-dark,#e8e0d4);padding-bottom:10px;
}
.fb-checklist{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
.fb-checklist li{display:flex;align-items:flex-start;gap:10px;font-size:.84rem;line-height:1.4;color:#374151}
.fb-check{
  width:18px;height:18px;border-radius:4px;border:2px solid var(--beige-dark,#e8e0d4);
  flex-shrink:0;margin-top:1px;cursor:pointer;appearance:none;
  background:#fff;transition:all .15s;
}
.fb-check:checked{background:var(--primary,#ea5649);border-color:var(--primary,#ea5649);}
.fb-check:checked + span{text-decoration:line-through;color:var(--text-muted,#6b7f96)}
.fb-progress-bar{
  height:6px;border-radius:4px;background:var(--beige-dark,#e8e0d4);
  margin-top:16px;overflow:hidden;
}
.fb-progress-fill{height:100%;background:var(--primary,#ea5649);border-radius:4px;transition:width .3s ease;width:0}
.fb-progress-label{font-size:.72rem;color:var(--text-muted,#6b7f96);margin-top:8px;text-align:right}

/* Formulaire */
.fb-form-card{background:#fff;border-radius:16px;border:1px solid var(--beige-dark,#e8e0d4);padding:36px}
@media(max-width:600px){.fb-form-card{padding:24px 18px}}
.fb-section-title{
  font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
  color:var(--text-muted,#6b7f96);margin-bottom:16px;margin-top:28px;
  border-bottom:1px solid var(--beige-dark,#e8e0d4);padding-bottom:10px;
}
.fb-section-title:first-child{margin-top:0}
.fb-field{margin-bottom:18px}
.fb-label{display:block;font-size:.82rem;font-weight:700;color:var(--navy-dark,#0c1e2e);margin-bottom:6px}
.fb-label span{color:var(--primary,#ea5649)}
.fb-input,.fb-select,.fb-textarea{
  width:100%;padding:11px 14px;border-radius:8px;
  border:1.5px solid var(--beige-dark,#e8e0d4);
  font-family:inherit;font-size:.9rem;color:var(--navy-dark,#0c1e2e);
  background:#fff;transition:border-color .15s;
  box-sizing:border-box;
}
.fb-input:focus,.fb-select:focus,.fb-textarea:focus{
  outline:none;border-color:var(--primary,#ea5649);
}
.fb-textarea{min-height:100px;resize:vertical;line-height:1.5}
.fb-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:480px){.fb-row{grid-template-columns:1fr}}

/* Note étoiles */
.star-group{display:flex;gap:8px;margin-top:6px}
.star-group input[type=radio]{display:none}
.star-group label{
  font-size:2rem;cursor:pointer;opacity:.35;transition:opacity .15s,transform .15s;
  user-select:none;
}
.star-group label:hover,.star-group label:hover~label{opacity:1}
.star-group input:checked~label{opacity:.35}
.star-group input:checked+label,.star-group input:checked+label~label{opacity:1}
.star-group:hover label{opacity:.5}
.star-group:hover label:hover,.star-group:hover label:hover~label{opacity:1}

/* Zones testées */
.fb-areas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin-top:8px}
.fb-area-chip{
  display:flex;align-items:center;gap:6px;padding:8px 12px;border-radius:8px;
  border:1.5px solid var(--beige-dark,#e8e0d4);cursor:pointer;font-size:.82rem;
  font-weight:600;color:#374151;background:#fff;transition:all .15s;
}
.fb-area-chip input{display:none}
.fb-area-chip.selected{background:rgba(234,86,73,.06);border-color:var(--primary,#ea5649);color:var(--primary,#ea5649);font-weight:700}

/* Device radio */
.fb-device-group{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
.fb-device-btn{
  padding:9px 18px;border-radius:8px;border:1.5px solid var(--beige-dark,#e8e0d4);
  cursor:pointer;font-size:.84rem;font-weight:600;color:#374151;background:#fff;
  transition:all .15s;
}
.fb-device-btn input{display:none}
.fb-device-btn.selected{background:rgba(234,86,73,.08);border-color:var(--primary,#ea5649);color:var(--primary,#ea5649);font-weight:700}

/* RGPD */
.fb-rgpd{display:flex;align-items:flex-start;gap:10px;font-size:.8rem;color:var(--text-muted,#6b7f96);line-height:1.5;margin-top:20px}
.fb-rgpd input[type=checkbox]{width:16px;height:16px;flex-shrink:0;margin-top:2px;cursor:pointer;accent-color:var(--primary,#ea5649)}
.fb-rgpd a{color:var(--primary,#ea5649)}

/* Alertes */
.fb-alert{padding:14px 18px;border-radius:10px;font-size:.88rem;margin-bottom:20px;font-weight:600}
.fb-alert-err{background:rgba(220,38,38,.08);border:1.5px solid rgba(220,38,38,.25);color:#b91c1c}
.fb-alert-ok{background:rgba(34,197,94,.08);border:1.5px solid rgba(34,197,94,.25);color:#166534}

/* Success state */
.fb-success{text-align:center;padding:56px 32px}
.fb-success-icon{font-size:4rem;margin-bottom:16px;line-height:1}
.fb-success h2{font-size:1.5rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:10px}
.fb-success p{font-size:.9rem;color:var(--text-muted,#6b7f96);line-height:1.6;max-width:420px;margin:0 auto 24px}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section class="fb-hero">
  <div class="container">
    <div class="fb-beta-chip">Bêta privée Zone85</div>
    <h1>Ton retour compte</h1>
    <p>Tu fais partie des premiers Zonautes. Dis-nous ce que tu as vécu, ce qui marche et ce qui coince — chaque retour améliore la Zone.</p>
  </div>
</section>

<!-- CONTENU -->
<section class="fb-page">
  <div class="fb-grid">

    <!-- SIDEBAR CHECKLIST -->
    <aside>
      <div class="fb-checklist-card">
        <div class="fb-checklist-title">Checklist à tester</div>
        <ul class="fb-checklist" id="fb-checklist">
          <?php foreach ([
            ['inscription', "Créer un compte"],
            ['clan',        "Choisir un clan"],
            ['mission',     "Faire une mission"],
            ['passeport',   "Voir mon passeport"],
            ['badges',      "Vérifier mes badges"],
            ['rando',       "Lire une fiche rando"],
            ['gpx',         "Télécharger un GPX"],
            ['classement',  "Consulter le classement"],
            ['mobile',      "Tout ça sur mobile"],
            ['pwa',         "Ajouter à l'écran d'accueil"],
          ] as [$id, $label]): ?>
          <li>
            <input type="checkbox" class="fb-check" id="chk-<?= $id ?>" onchange="updateProgress()">
            <span><?= $label ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="fb-progress-bar"><div class="fb-progress-fill" id="fb-progress"></div></div>
        <div class="fb-progress-label" id="fb-progress-label">0 / 10 testés</div>
      </div>
    </aside>

    <!-- FORMULAIRE -->
    <div>
      <?php if ($fb_success): ?>
      <div class="fb-form-card">
        <div class="fb-success">
          <div class="fb-success-icon">🙏</div>
          <h2>Merci pour ton retour !</h2>
          <p>Ton témoignage a été envoyé à l'équipe Zone85. Tu contribues directement à améliorer l'expérience pour tous les Zonautes.</p>
          <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
        </div>
      </div>
      <?php else: ?>
      <div class="fb-form-card">

        <?php if ($fb_error): ?>
        <div class="fb-alert fb-alert-err"><?= e($fb_error) ?></div>
        <?php endif; ?>

        <form method="post" action="feedback.php">
          <?= csrf_field() ?>

          <!-- Identité -->
          <div class="fb-section-title">Qui tu es</div>

          <div class="fb-row">
            <div class="fb-field">
              <label class="fb-label" for="fb-pseudo">Ton pseudo Zone85 <span>*</span></label>
              <input type="text" name="pseudo" id="fb-pseudo" class="fb-input"
                     value="<?= e($_logged_user['pseudo'] ?? $_POST['pseudo'] ?? '') ?>"
                     placeholder="ex : ZonauteVendéen" maxlength="50" required>
            </div>
            <div class="fb-field">
              <label class="fb-label" for="fb-email">Email (pour qu'on puisse te répondre) <span>*</span></label>
              <input type="email" name="email" id="fb-email" class="fb-input"
                     value="<?= e($_POST['email'] ?? '') ?>"
                     placeholder="ton@email.fr" required>
            </div>
          </div>

          <div class="fb-row">
            <div class="fb-field">
              <label class="fb-label" for="fb-clan">Ton clan</label>
              <select name="clan" id="fb-clan" class="fb-select">
                <option value="aucun">— Je n'ai pas encore de clan</option>
                <?php foreach (['bocage' => '🌳 Bocage', 'littoral' => '⚓ Littoral', 'marais' => '🌿 Marais'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= (($_POST['clan'] ?? ($_logged_user['clan_slug'] ?? '')) === $v) ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fb-field">
              <label class="fb-label">Appareil utilisé</label>
              <div class="fb-device-group" id="device-group">
                <?php foreach (['mobile' => '📱 Mobile', 'desktop' => '💻 Desktop', 'both' => '📱+💻 Les deux'] as $v => $l): ?>
                <label class="fb-device-btn <?= (($_POST['device'] ?? 'desktop') === $v) ? 'selected' : '' ?>">
                  <input type="radio" name="device" value="<?= $v ?>" <?= (($_POST['device'] ?? 'desktop') === $v) ? 'checked' : '' ?> onchange="updateDevice(this)">
                  <?= $l ?>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Note globale -->
          <div class="fb-section-title">Ta note globale</div>
          <div class="fb-field">
            <label class="fb-label">Ton expérience sur Zone85 <span>*</span></label>
            <div class="star-group" id="star-group">
              <?php for ($i = 5; $i >= 1; $i--): ?>
              <input type="radio" name="note" id="star-<?= $i ?>" value="<?= $i ?>" <?= (isset($_POST['note']) && (int)$_POST['note'] === $i) ? 'checked' : '' ?>>
              <label for="star-<?= $i ?>" title="<?= $i ?>/5">⭐</label>
              <?php endfor; ?>
            </div>
            <p style="font-size:.78rem;color:var(--text-muted);margin-top:8px">1 étoile = difficile · 5 étoiles = excellent</p>
          </div>

          <!-- Zones testées -->
          <div class="fb-section-title">Ce que tu as testé</div>
          <div class="fb-field">
            <label class="fb-label">Zones testées (sélectionne tout ce que tu as essayé)</label>
            <div class="fb-areas-grid" id="areas-grid">
              <?php foreach ([
                'inscription' => '🚀 Inscription',
                'connexion'   => '🔑 Connexion',
                'missions'    => '🎯 Missions',
                'randos'      => '🥾 Randos',
                'profil'      => '🪪 Profil/Passeport',
                'clans'       => '⚔️ Clans',
                'classement'  => '📊 Classement',
                'ktc'         => '🔍 Kétokole',
                'pwa'         => '📲 App mobile',
                'autre'       => '…Autre',
              ] as $v => $l): ?>
              <label class="fb-area-chip <?= in_array($v, $_POST['areas'] ?? []) ? 'selected' : '' ?>">
                <input type="checkbox" name="areas[]" value="<?= $v ?>" <?= in_array($v, $_POST['areas'] ?? []) ? 'checked' : '' ?> onchange="updateAreaStyle(this)">
                <?= $l ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Problèmes -->
          <div class="fb-section-title">Problèmes &amp; suggestions</div>
          <div class="fb-field">
            <label class="fb-label" for="fb-problems">Problèmes rencontrés</label>
            <textarea name="problems" id="fb-problems" class="fb-textarea"
                      placeholder="Décris ce qui n'a pas marché (page, action, message d'erreur…)"><?= e($_POST['problems'] ?? '') ?></textarea>
          </div>
          <div class="fb-field">
            <label class="fb-label" for="fb-suggest">Suggestions &amp; idées</label>
            <textarea name="suggest" id="fb-suggest" class="fb-textarea"
                      placeholder="Ce que tu améliorerais, ce qui manque, ce que tu as aimé…"><?= e($_POST['suggest'] ?? '') ?></textarea>
          </div>

          <!-- RGPD -->
          <label class="fb-rgpd">
            <input type="checkbox" name="rgpd_consent" value="1" <?= !empty($_POST['rgpd_consent']) ? 'checked' : '' ?> required>
            <span>J'accepte que mon retour soit utilisé par l'équipe Zone85 pour améliorer la plateforme. Mes données ne seront pas partagées avec des tiers. <a href="confidentialite.php" target="_blank">Politique de confidentialité</a></span>
          </label>

          <div style="margin-top:28px">
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%">Envoyer mon retour →</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php
$page_scripts = '<script>
function updateProgress() {
  var checks = document.querySelectorAll(".fb-check");
  var checked = document.querySelectorAll(".fb-check:checked").length;
  var total   = checks.length;
  var pct     = total > 0 ? Math.round(checked / total * 100) : 0;
  var fill    = document.getElementById("fb-progress");
  var label   = document.getElementById("fb-progress-label");
  if (fill)  fill.style.width  = pct + "%";
  if (label) label.textContent = checked + " / " + total + " testés";
}

function updateDevice(radio) {
  document.querySelectorAll(".fb-device-btn").forEach(function(btn) {
    var inp = btn.querySelector("input[type=radio]");
    btn.classList.toggle("selected", inp && inp.checked);
  });
}

function updateAreaStyle(checkbox) {
  checkbox.closest(".fb-area-chip").classList.toggle("selected", checkbox.checked);
}

// Init
updateProgress();
</script>';

require_once 'includes/footer.php';
?>
