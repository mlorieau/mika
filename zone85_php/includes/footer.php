<?php
// footer.php — Footer partagé + fermeture de la page
// Variable optionnelle : $page_scripts (string HTML) — JS spécifique à la page
?>

<!-- ============ FOOTER ============ -->
<footer id="footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-col">
        <div class="footer-brand-logo">ZONE<span>85</span></div>
        <div class="footer-brand-tagline">L'Esprit Vendée</div>
        <p class="footer-brand-desc"><?= SITE_TAGLINE ?></p>
        <div class="footer-clan-chips">
          <span class="footer-clan-chip">Bocage</span>
          <span class="footer-clan-chip">Littoral</span>
          <span class="footer-clan-chip">Marais</span>
        </div>
      </div>
      <div class="footer-col">
        <div class="footer-col-title">Le site</div>
        <ul>
          <li><a href="index.php">Accueil</a></li>
          <li><a href="concept.php">Le Concept</a></li>
          <li><a href="clans.php">Les Clans</a></li>
          <li><a href="missions.php">Missions</a></li>
          <li><a href="classement.php">Classement</a></li>
          <li><a href="hall.php">Hall de la Zone</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <div class="footer-col-title">Mon compte</div>
        <ul>
          <li><a href="inscription.php">Rejoindre</a></li>
          <li><a href="profil.php">Connexion</a></li>
          <li><a href="profil.php">Profil</a></li>
        </ul>
        <div style="margin-top:20px">
          <div class="footer-col-title">Saison en cours</div>
          <?php if ($active_season): ?>
            <div style="font-size:.88rem;color:rgba(255,255,255,.65);font-weight:600"><?= e($active_season['title']) ?></div>
            <div style="font-size:.75rem;color:var(--primary);font-weight:700;margin-top:4px"><?= e($active_season['main_mission'] ?? '') ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="footer-col">
        <div class="footer-col-title">Légal</div>
        <ul>
          <li><a href="contact.php">Contact</a></li>
          <li><a href="mentions-legales.php">Mentions légales</a></li>
          <li><a href="confidentialite.php">Confidentialité</a></li>
          <li><a href="cookies.php">Cookies</a></li>
          <li><a href="cgu.php">CGU / Règles de la Zone</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="footer-copy">© 2025 Zone 85 — L'Esprit Vendée. Tous droits réservés.</div>
      <div class="footer-legal">
        <a href="mentions-legales.php">Mentions légales</a>
        <a href="cgu.php">CGU</a>
        <a href="confidentialite.php">Confidentialité</a>
        <a href="cookies.php">Cookies</a>
        <a href="contact.php">Contact</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= JS_PATH ?>zone85.js"></script>
<?php if (!empty($page_scripts)) echo $page_scripts . "\n"; ?>
</body>
</html>
