<?php
$page_title = 'Contact — Zone 85 · L\'Esprit Vendée';
$page_description = 'Une question, une idée, un signalement ? Contactez l\'équipe Zone 85.';
$current_page = 'legal';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
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
      <!-- Info banner -->
      <div class="info-banner">
        <strong>Formulaire en cours de connexion.</strong> En attendant, vous pouvez nous écrire directement à <a href="mailto:contact@zone85.fr">contact@zone85.fr</a>.
      </div>

      <div class="form-card reveal">
        <div class="form-card-title">Envoyer un message</div>

        <form action="" method="post" novalidate>

          <div class="form-row">
            <div class="form-group">
              <label for="nom">Nom ou pseudo</label>
              <input type="text" id="nom" name="nom" placeholder="Ex. : VendéenDu85" autocomplete="name">
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" placeholder="ton@email.fr" autocomplete="email">
            </div>
          </div>

          <div class="form-group">
            <label for="sujet">Sujet</label>
            <input type="text" id="sujet" name="sujet" placeholder="Résumez votre demande en quelques mots">
          </div>

          <div class="form-group">
            <label for="motif">Motif de contact</label>
            <select id="motif" name="motif">
              <option value="" disabled selected>Sélectionner un motif…</option>
              <option value="question">Question générale</option>
              <option value="technique">Problème technique</option>
              <option value="partenariat">Partenariat</option>
              <option value="signalement">Signalement de contenu</option>
              <option value="rgpd">Données personnelles / RGPD</option>
              <option value="autre">Autre</option>
            </select>
          </div>

          <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" placeholder="Décrivez votre demande en détail…"></textarea>
          </div>

          <label class="form-check">
            <input type="checkbox" name="rgpd_consent" required>
            <span class="form-check-label">J'ai lu la <a href="confidentialite.php">politique de confidentialité</a>.</span>
          </label>

          <button type="submit" class="btn-submit">Envoyer le message</button>

          <p class="rgpd-note">
            Les informations transmises via ce formulaire sont utilisées uniquement pour répondre à votre demande. Vous pouvez exercer vos droits d'accès, de rectification ou de suppression en nous contactant.
          </p>

        </form>
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
