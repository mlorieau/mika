<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

// ── Traitement POST ───────────────────────────────────────────
$contact_success = false;
$contact_error   = '';

if (is_post_request()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $contact_error = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $c_name    = safe_input($_POST['name']    ?? '', 100);
        $c_email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $c_subject = safe_input($_POST['subject'] ?? '', 200);
        $c_reason  = safe_input($_POST['reason']  ?? '', 100);
        $c_message = safe_input($_POST['message'] ?? '', 2000);
        $c_rgpd    = !empty($_POST['rgpd_consent']);

        if (!$c_name || !$c_email || !$c_message || !$c_rgpd) {
            $contact_error = 'Veuillez remplir tous les champs obligatoires et accepter la politique de confidentialité.';
        } else {
            // 1. Sauvegarde en base
            $pdo = db();
            if ($pdo) {
                try {
                    $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
                    $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                    $pdo->prepare("
                        INSERT INTO contact_messages (name, email, subject, reason, message, ip_hash, user_agent_hash)
                        VALUES (:name, :email, :subject, :reason, :message, :ip, :ua)
                    ")->execute([
                        ':name'    => $c_name,
                        ':email'   => $c_email,
                        ':subject' => $c_subject ?: null,
                        ':reason'  => $c_reason  ?: null,
                        ':message' => $c_message,
                        ':ip'      => $ip_hash,
                        ':ua'      => $ua_hash,
                    ]);
                } catch (PDOException $e) {
                    error_log('[ZONE85] contact DB : ' . $e->getMessage());
                }
            }

            // 2. Notification à l'équipe Zone85
            $admin_email = get_setting('site_email', 'contact@zone85.fr');
            $reason_label = [
                'question'    => 'Question générale',
                'technique'   => 'Problème technique',
                'partenariat' => 'Partenariat',
                'signalement' => 'Signalement de contenu',
                'rgpd'        => 'Données personnelles / RGPD',
                'autre'       => 'Autre',
            ][$c_reason] ?? ($c_reason ?: 'Non précisé');

            send_email(
                $admin_email,
                '[Zone85 Contact] ' . ($c_subject ?: $reason_label) . ' — ' . $c_name,
                'contact_message',
                [
                    'sender_name'    => $c_name,
                    'sender_email'   => $c_email,
                    'reason_label'   => $reason_label,
                    'subject'        => $c_subject ?: '(sans sujet)',
                    'message'        => $c_message,
                ]
            );

            // 3. Accusé de réception à l'expéditeur
            send_email(
                $c_email,
                'Nous avons bien reçu votre message — Zone85',
                'contact_ack',
                [
                    'pseudo'  => $c_name,
                    'subject' => $c_subject ?: $reason_label,
                ]
            );

            $contact_success = true;
        }
    }
}

$page_title       = 'Contact';
$page_description = 'Contacte l\'équipe ZONE85 pour toute question sur la communauté vendéenne gamifiée, les missions, les clans ou les partenariats.';
$page_canonical   = 'https://www.zone85.fr/contact.php';
$page_robots      = 'index,follow';
$page_og_image    = null;
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Contact','item'=>'https://www.zone85.fr/contact.php'],
    ],
];
$current_page = 'legal';
require_once 'includes/data.php';
$page_styles = '<style>

/* ── HERO ── */
#hero {
  background: linear-gradient(160deg, #0d1e2c 0%, #12314e 60%, #163756 100%);
  padding: 140px 24px 80px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
#hero::before {
  content: \'\';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}
.hero-inner {
  position: relative;
  z-index: 2;
  animation: fadeUp .7s ease both;
}
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(234,86,73,.15);
  border: 1px solid rgba(234,86,73,.3);
  color: #f5a99f;
  padding: 6px 18px;
  border-radius: 4px;
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  margin-bottom: 24px;
}
.hero-eyebrow::before {
  content: \'\';
  width: 6px;
  height: 6px;
  background: var(--primary);
  border-radius: 50%;
  animation: blink 1.5s infinite;
}
.hero-title {
  font-size: clamp(2.2rem, 5vw, 3.6rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1.5px;
  line-height: 1.1;
  margin-bottom: 20px;
}
.hero-title em {
  color: var(--primary);
  font-style: normal;
}
.hero-sub {
  font-size: clamp(.95rem, 2vw, 1.1rem);
  color: rgba(255,255,255,.65);
  max-width: 540px;
  margin: 0 auto;
  line-height: 1.7;
}

/* ── MAIN SECTION ── */
#contact-section {
  background: var(--beige);
  padding: 72px 24px 96px;
}
.contact-layout {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 36px;
  align-items: start;
}

/* ── INFO BANNER ── */
.info-banner {
  background: rgba(234,86,73,.08);
  border: 1px solid rgba(234,86,73,.25);
  border-left: 4px solid var(--primary);
  border-radius: var(--radius);
  padding: 14px 18px;
  font-size: .88rem;
  color: var(--text-mid);
  line-height: 1.6;
  margin-bottom: 28px;
}
.info-banner strong {
  color: var(--text);
}
.info-banner a {
  color: var(--primary);
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* ── FORM CARD ── */
.form-card {
  background: #fff;
  border-radius: var(--radius-lg);
  padding: 40px 36px;
  box-shadow: var(--shadow-md);
  border-top: 4px solid var(--primary);
}
.form-card-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--navy-dark);
  margin-bottom: 28px;
  letter-spacing: -.3px;
}
.form-group {
  margin-bottom: 20px;
}
.form-group label {
  display: block;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--navy-dark);
  margin-bottom: 7px;
}
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  font-family: \'Inter\', sans-serif;
  font-size: .95rem;
  color: var(--text);
  background: var(--beige-light);
  border: 2px solid var(--beige-dark);
  border-radius: var(--radius-sm);
  padding: 11px 14px;
  outline: none;
  transition: border-color .2s ease, box-shadow .2s ease;
  appearance: none;
  -webkit-appearance: none;
}
.form-group select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'8\' viewBox=\'0 0 12 8\'%3E%3Cpath d=\'M1 1l5 5 5-5\' stroke=\'%237a8a94\' stroke-width=\'1.8\' fill=\'none\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  padding-right: 40px;
  cursor: pointer;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(234,86,73,.12);
  background: #fff;
}
.form-group textarea {
  resize: vertical;
  min-height: 130px;
  line-height: 1.6;
}

/* ── FORM ROW ── */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

/* ── CHECKBOX ── */
.form-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 24px;
  cursor: pointer;
}
.form-check input[type="checkbox"] {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  accent-color: var(--primary);
  margin-top: 2px;
  cursor: pointer;
}
.form-check-label {
  font-size: .88rem;
  color: var(--text-mid);
  line-height: 1.5;
}
.form-check-label a {
  color: var(--primary);
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* ── SUBMIT BUTTON ── */
.btn-submit {
  display: block;
  width: 100%;
  background: var(--primary);
  color: #fff;
  border: none;
  border-radius: var(--radius-sm);
  padding: 14px 24px;
  font-family: \'Inter\', sans-serif;
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: .02em;
  cursor: pointer;
  transition: background .2s ease, transform .15s ease;
  text-align: center;
}
.btn-submit:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}
.btn-submit:active {
  transform: translateY(0);
}

/* ── RGPD NOTE ── */
.rgpd-note {
  margin-top: 16px;
  font-size: .78rem;
  color: var(--text-muted);
  line-height: 1.6;
  text-align: center;
}

/* ── INFO CARDS (RIGHT COLUMN) ── */
.info-cards {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.info-card {
  background: #fff;
  border-radius: var(--radius-lg);
  padding: 24px 24px 20px;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--beige-dark);
}
.info-card.signalement {
  background: rgba(234,86,73,.05);
  border: 1px solid rgba(234,86,73,.2);
}
.info-card-icon {
  font-size: 1.6rem;
  margin-bottom: 10px;
  line-height: 1;
}
.info-card-title {
  font-size: 1rem;
  font-weight: 800;
  color: var(--navy-dark);
  margin-bottom: 8px;
  letter-spacing: -.2px;
}
.info-card-email {
  display: inline-block;
  font-size: .95rem;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 10px;
  letter-spacing: -.2px;
}
.info-card p {
  font-size: .875rem;
  color: var(--text-mid);
  line-height: 1.65;
  margin: 0;
}
.info-card-hint {
  display: inline-block;
  margin-top: 12px;
  font-size: .75rem;
  font-weight: 700;
  color: var(--primary);
  background: rgba(234,86,73,.1);
  border-radius: 4px;
  padding: 4px 10px;
  letter-spacing: .05em;
  text-transform: uppercase;
}

/* ── RESPONSIVE ── */
@media (max-width: 820px) {
  .contact-layout {
    grid-template-columns: 1fr;
  }
  .form-card {
    padding: 28px 20px;
  }
  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
}
@media (max-width: 480px) {
  #hero {
    padding: 120px 16px 60px;
  }
  .form-card {
    padding: 24px 16px;
  }
}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- HERO -->
<section id="hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">Contact</div>
    <h1 class="hero-title">Contacter la <em>Zone</em></h1>
    <p class="hero-sub">Une question, une idée, un signalement ou une vieille mogette à déclarer&nbsp;? Écris-nous.</p>
  </div>
</section>

<!-- CONTACT SECTION -->
<section id="contact-section">
  <div class="contact-layout">

    <!-- LEFT: FORM -->
    <div>
      <div class="form-card reveal">
        <div class="form-card-title">Envoyer un message</div>

        <?php if ($contact_success): ?>
          <div style="background:rgba(42,157,92,.1);border:1px solid rgba(42,157,92,.3);border-radius:8px;padding:18px 20px;margin-bottom:20px;color:#2a9d5c;font-weight:700;font-size:.9rem;line-height:1.6">
            ✓ Message envoyé. Nous vous répondrons dans les meilleurs délais à <strong><?= e($c_email) ?></strong>.
          </div>
        <?php elseif ($contact_error): ?>
          <div style="background:rgba(234,86,73,.08);border:1px solid rgba(234,86,73,.3);border-radius:8px;padding:14px 18px;margin-bottom:16px;color:#c0392b;font-weight:600;font-size:.85rem">
            <?= e($contact_error) ?>
          </div>
        <?php endif; ?>

        <?php if (!$contact_success): ?>
        <form action="" method="post" novalidate>

          <div class="form-row">
            <div class="form-group">
              <label for="f_name">Nom ou pseudo <span style="color:var(--primary)">*</span></label>
              <input type="text" id="f_name" name="name" placeholder="Ex. : VendéenDu85" autocomplete="name"
                     value="<?= isset($c_name) ? e($c_name) : '' ?>" required>
            </div>
            <div class="form-group">
              <label for="f_email">Email <span style="color:var(--primary)">*</span></label>
              <input type="email" id="f_email" name="email" placeholder="ton@email.fr" autocomplete="email"
                     value="<?= isset($c_email) && $c_email ? e($c_email) : (isset($_POST['email']) ? e(trim($_POST['email'])) : '') ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="f_subject">Sujet</label>
            <input type="text" id="f_subject" name="subject" placeholder="Résumez votre demande en quelques mots"
                   value="<?= isset($c_subject) ? e($c_subject) : '' ?>">
          </div>

          <div class="form-group">
            <label for="f_reason">Motif de contact</label>
            <select id="f_reason" name="reason">
              <?php
              $sel = isset($c_reason) ? $c_reason : '';
              $opts = [''=>'Sélectionner un motif…','question'=>'Question générale','technique'=>'Problème technique','partenariat'=>'Partenariat','signalement'=>'Signalement de contenu','rgpd'=>'Données personnelles / RGPD','autre'=>'Autre'];
              foreach ($opts as $val => $lbl):
              ?>
              <option value="<?= e($val) ?>"<?= $val==='' ? ' disabled' : '' ?><?= $val==='' && !$sel ? ' selected' : ($val===$sel ? ' selected' : '') ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="f_message">Message <span style="color:var(--primary)">*</span></label>
            <textarea id="f_message" name="message" rows="5" placeholder="Décrivez votre demande en détail…" required><?= isset($c_message) ? e($c_message) : '' ?></textarea>
          </div>

          <label class="form-check">
            <input type="checkbox" name="rgpd_consent" required>
            <span class="form-check-label">J'ai lu la <a href="confidentialite.php">politique de confidentialité</a>.</span>
          </label>

          <?= csrf_field() ?>
          <button type="submit" class="btn-submit">Envoyer le message</button>
        </form>
        <?php endif; ?>

          <p class="rgpd-note">
            Les informations transmises via ce formulaire sont utilisées uniquement pour répondre à votre demande. Vous pouvez exercer vos droits d'accès, de rectification ou de suppression en nous contactant.
          </p>

      </div>
    </div>

    <!-- RIGHT: INFO CARDS -->
    <div class="info-cards">

      <!-- Card 1: Contact direct -->
      <div class="info-card reveal">
        <div class="info-card-icon">✉️</div>
        <div class="info-card-title">Contact direct</div>
        <div class="info-card-email">contact@zone85.fr</div>
        <p>Pour toute demande liée à vos données personnelles, utilisez ce formulaire ou écrivez-nous à cette adresse.</p>
      </div>

      <!-- Card 2: Signalement -->
      <div class="info-card signalement reveal">
        <div class="info-card-icon">🚨</div>
        <div class="info-card-title">Signaler un contenu</div>
        <p>Une photo, un commentaire ou une contribution te semble inapproprié&nbsp;? Signale-le-nous. La Zone doit rester un terrain de jeu sympa, pas un champ de bataille de mauvaise foi.</p>
        <div class="info-card-hint">Motif : Signalement de contenu</div>
      </div>

      <!-- Card 3: Données personnelles -->
      <div class="info-card reveal">
        <div class="info-card-icon">🔒</div>
        <div class="info-card-title">Vos données personnelles</div>
        <p>Pour exercer vos droits (accès, rectification, suppression), utilisez le formulaire en sélectionnant le motif <strong>Données personnelles&nbsp;/ RGPD</strong>.</p>
      </div>

    </div>

  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
