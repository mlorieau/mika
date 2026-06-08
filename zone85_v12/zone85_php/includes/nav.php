<?php
// nav.php — Navigation Zone85 V13 Mega Menu
// Variable attendue : $current_page (string)

// Liens directs toujours visibles dans la barre
$_nav_direct = [
    ['slug' => 'index',      'label' => 'Accueil',    'file' => 'index.php'],
    ['slug' => 'concept',    'label' => 'Concept',    'file' => 'concept.php'],
    ['slug' => 'les-echos',  'label' => 'Les Échos',  'file' => 'les-echos.php'],
    ['slug' => 'randos',     'label' => 'Randos',     'file' => 'randos.php'],
    ['slug' => 'ktc',        'label' => 'Kétokole',   'file' => 'ktc.php'],
    ['slug' => 'missions',   'label' => 'Missions',   'file' => 'missions.php'],
    ['slug' => 'communaute', 'label' => 'Communauté', 'file' => 'communaute.php'],
];

// Contenu du mega menu (3 colonnes)
$_mega_sections = [
    [
        'label' => 'La Zone',
        'emoji' => '🗺️',
        'links' => [
            ['file' => 'index.php',      'label' => 'Accueil',         'desc' => 'La Vendée Joue',              'icon' => '🏠'],
            ['file' => 'hall.php',       'label' => 'Hall de la Zone', 'desc' => 'Galerie & saisons passées',   'icon' => '🏆'],
            ['file' => 'les-echos.php',  'label' => 'Les Échos',       'desc' => 'Le magazine de la Zone',      'icon' => '📰'],
            ['file' => 'trophees.php',   'label' => 'Trophées',        'desc' => 'Palmarès des clans',          'icon' => '🥇'],
        ],
    ],
    [
        'label' => 'Jouer',
        'emoji' => '⚡',
        'links' => [
            ['file' => 'missions.php',   'label' => 'Missions',        'desc' => 'Tous les défis actifs',       'icon' => '🎯'],
            ['file' => 'randos.php',     'label' => 'Randos',          'desc' => 'Aventures terrain & GPX',     'icon' => '🥾'],
            ['file' => 'ktc.php',        'label' => 'KTC',             'desc' => 'L\'objet mystère du mois',    'icon' => '🔍'],
            ['file' => 'evenements.php', 'label' => 'Événements',      'desc' => 'Flash events & bonus XP',    'icon' => '🎉'],
        ],
    ],
    [
        'label' => 'Communauté',
        'emoji' => '🛡️',
        'links' => [
            ['file' => 'profil.php',                        'label' => 'Mon Passeport',   'desc' => 'Identité, niveau & badges',   'icon' => '🪪'],
            ['file' => 'communaute.php',                    'label' => 'Fil de la Zone',  'desc' => 'Activité en temps réel',      'icon' => '🌍'],
            ['file' => 'clans.php',                         'label' => 'Les Clans',       'desc' => 'Bocage · Littoral · Marais',  'icon' => '⚔️'],
            ['file' => 'communaute.php?tab=classement',     'label' => 'Classement',      'desc' => 'Top 50 Zonautes',             'icon' => '📊'],
            ['file' => 'recompenses.php',                   'label' => 'XP & Récompenses',   'desc' => 'Niveaux, badges et points clan', 'icon' => '🏅'],
            ['file' => 'comment-ca-marche.php',             'label' => 'Comment ça marche ?', 'desc' => 'Comprendre Zone85 en 30 sec', 'icon' => '❓'],
            ['file' => 'aide.php',                          'label' => 'Centre d\'aide',      'desc' => 'FAQ & questions fréquentes',  'icon' => '💬'],
        ],
    ],
];

$_cp              = $current_page ?? '';
$_nav_user        = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['user'] ?? null) : null;
$_nav_initials    = '';
$_nav_notif_count = 0;
if ($_nav_user) {
    $_nav_initials = strtoupper(mb_substr($_nav_user['pseudo'], 0, 2));
    if (function_exists('count_unread_notifications')) {
        $_nav_notif_count = count_unread_notifications((int)$_nav_user['id']);
    }
}

// Saison active pour la carte dans le mega menu
$_nav_season = null;
if (isset($active_season) && !empty($active_season['title'])) {
    $_nav_season = $active_season;
} elseif (function_exists('fetch_active_season')) {
    $_nav_season = fetch_active_season();
}
?>
<nav id="navbar">
  <div class="nav-inner">

    <!-- Gauche : logo -->
    <div class="nav-left">
      <a href="index.php" class="nav-logo" aria-label="ZONE85 — Accueil">
        <img src="<?= img('logo-header.png') ?>" alt="ZONE85 — L'Esprit Vendée">
      </a>
    </div>

    <!-- Centre : liens directs -->
    <div class="nav-center">
      <?php foreach ($_nav_direct as $_nd): ?>
        <a href="<?= $_nd['file'] ?>" class="nav-direct<?= ($_cp === $_nd['slug']) ? ' active' : '' ?>">
          <?= $_nd['label'] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Droite : classement, cloche, avatar, connexion -->
    <div class="nav-right">
      <?php if ($_nav_user): ?>
        <a href="notifications.php" class="nav-notif-bell" title="Notifications"
           style="position:relative;display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;text-decoration:none;font-size:1.1rem;color:var(--text-mid);transition:background .15s<?= ($_cp === 'notifications') ? ';background:rgba(234,86,73,.1)' : '' ?>">
          🔔
          <?php if ($_nav_notif_count > 0): ?>
          <span style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;background:var(--primary);color:#fff;border-radius:8px;font-size:.6rem;font-weight:900;display:flex;align-items:center;justify-content:center;padding:0 3px;line-height:1">
            <?= $_nav_notif_count > 9 ? '9+' : $_nav_notif_count ?>
          </span>
          <?php endif; ?>
        </a>
        <?php $_nav_avatar_url = avatar_url($_nav_user); ?>
        <div class="nav-avatar" title="<?= e($_nav_user['pseudo']) ?>" onclick="location.href='profil.php'" style="cursor:pointer<?= ($_nav_user['avatar_type'] === 'upload') ? ';padding:0;overflow:hidden' : '' ?>">
          <?php if (!empty($_nav_avatar_url)): ?>
            <img src="<?= e($_nav_avatar_url) ?>" alt="<?= e($_nav_user['pseudo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
          <?php else: ?>
            <?= e($_nav_user['avatar_key'] ?? $_nav_initials) ?>
          <?php endif; ?>
        </div>
        <?php if (($_nav_user['role'] ?? '') === 'admin'): ?>
        <a href="admin/index.php" style="font-size:.72rem;font-weight:800;color:var(--primary);text-decoration:none;padding:4px 10px;border:1.5px solid var(--primary);border-radius:5px;letter-spacing:.05em" title="Back-office admin">Admin</a>
        <?php endif; ?>
        <a href="logout.php" class="nav-btn" style="background:transparent;border:2px solid var(--beige-dark);color:var(--text-mid)" onclick="return confirm('Se déconnecter ?')">Déconnexion</a>
      <?php else: ?>
        <a href="login.php" style="font-size:.84rem;font-weight:700;color:var(--text-mid);text-decoration:none;white-space:nowrap">Connexion</a>
        <a href="inscription.php" class="nav-btn">Rejoindre</a>
      <?php endif; ?>
      <div class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Menu"><span></span><span></span><span></span></div>
    </div>

  </div>
</nav>


<!-- ── MEGA MENU PANEL ──────────────────────────────────────────── -->
<div class="mega-panel" id="mega-panel" role="dialog" aria-label="Menu de navigation">
  <div class="mega-panel-inner">

    <?php foreach ($_mega_sections as $_ms): ?>
    <div class="mega-col">
      <div class="mega-col-title">
        <span class="mega-col-emoji"><?= $_ms['emoji'] ?></span>
        <?= $_ms['label'] ?>
      </div>
      <?php foreach ($_ms['links'] as $_ml): ?>
      <a href="<?= $_ml['file'] ?>" class="mega-item">
        <span class="mega-item-icon"><?= $_ml['icon'] ?></span>
        <span>
          <span class="mega-item-label"><?= $_ml['label'] ?></span>
          <span class="mega-item-desc"><?= $_ml['desc'] ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- Carte saison (pleine largeur) -->
    <?php if ($_nav_season): ?>
    <div class="mega-season-row">
      <a href="missions.php" class="mega-season-card">
        <span class="mega-season-dot"></span>
        <span>
          <span class="mega-season-label">Saison en cours</span>
          <span class="mega-season-title"><?= e($_nav_season['title']) ?></span>
        </span>
        <?php if (!empty($_nav_season['main_mission'])): ?>
        <span class="mega-season-mission"><?= e(mb_substr($_nav_season['main_mission'], 0, 55)) ?></span>
        <?php endif; ?>
        <span class="mega-season-cta">Voir les missions →</span>
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
<!-- ── Overlay fermeture mega ── -->
<div class="mega-overlay" id="mega-overlay"></div>


<!-- ── MENU MOBILE ─────────────────────────────────────────────── -->
<div class="mobile-menu" id="mobileMenu">

  <div class="mmenu-section">
    <div class="mmenu-section-label">Zone85</div>
    <a href="index.php">Accueil</a>
    <a href="concept.php">Concept</a>
    <a href="les-echos.php">Les Échos</a>
    <a href="randos.php">Randos</a>
    <a href="ktc.php">Kétokole</a>
    <a href="missions.php">Missions</a>
    <a href="communaute.php">Communauté</a>
    <a href="comment-ca-marche.php">Comment ça marche ?</a>
    <a href="recompenses.php">🏅 XP &amp; Récompenses</a>
    <a href="aide.php">❓ Aide</a>
    <a href="feedback.php">📝 Donner mon avis (bêta)</a>
  </div>

  <div class="mmenu-section">
    <div class="mmenu-section-label">Communauté</div>
    <a href="communaute.php?tab=passeport">🪪 Mon Passeport</a>
    <a href="communaute.php">🌍 Le Fil</a>
    <a href="communaute.php?tab=clans">🛡 Classement Clans</a>
    <a href="communaute.php?tab=classement">📊 Classement Zonautes</a>
  </div>

  <div class="mmenu-section">
    <div class="mmenu-section-label">Découvrir</div>
    <a href="hall.php">Hall de la Zone</a>
    <a href="trophees.php">Trophées</a>
    <a href="clans.php">Les Clans</a>
    <a href="evenements.php">Événements</a>
  </div>

  <div class="mmenu-section" style="border-top:1px solid rgba(0,0,0,.08)">
    <?php if ($_nav_user): ?>
      <a href="profil.php">🪪 Mon Passeport — <?= e($_nav_user['pseudo']) ?></a>
      <a href="mon-compte.php">⚙️ Mon Compte</a>
      <a href="notifications.php">🔔 Notifications<?= $_nav_notif_count > 0 ? ' (' . $_nav_notif_count . ')' : '' ?></a>
      <a href="logout.php" style="color:var(--text-muted)" onclick="return confirm('Se déconnecter ?')">Déconnexion</a>
    <?php else: ?>
      <a href="login.php">Connexion</a>
      <a href="inscription.php" style="color:var(--primary);font-weight:800">Rejoindre la Zone →</a>
    <?php endif; ?>
  </div>

</div>


<!-- Toast container -->
<div id="z85-toast" role="status" aria-live="polite"></div>


<script>
/* ── Navbar scroll shadow ── */
(function(){
  var nb = document.getElementById('navbar');
  if(!nb) return;
  window.addEventListener('scroll', function(){
    nb.classList.toggle('scrolled', window.scrollY > 16);
  }, {passive:true});
})();

/* ── Mega Menu ── */
(function(){
  var trigger  = document.getElementById('mega-trigger');
  var panel    = document.getElementById('mega-panel');
  var overlay  = document.getElementById('mega-overlay');
  if(!trigger || !panel) return;
  var leaveTimer;

  function openMega(){
    clearTimeout(leaveTimer);
    trigger.classList.add('active');
    trigger.setAttribute('aria-expanded','true');
    panel.classList.add('open');
    if(overlay) overlay.classList.add('active');
  }
  function closeMega(){
    clearTimeout(leaveTimer);
    trigger.classList.remove('active');
    trigger.setAttribute('aria-expanded','false');
    panel.classList.remove('open');
    if(overlay) overlay.classList.remove('active');
  }

  // Desktop hover (ignorer sur petits écrans)
  function isDesktop(){ return window.innerWidth > 900; }

  trigger.addEventListener('mouseenter', function(){ if(isDesktop()) openMega(); });
  trigger.addEventListener('mouseleave', function(){ if(isDesktop()) leaveTimer = setTimeout(closeMega, 200); });
  panel.addEventListener('mouseenter',   function(){ if(isDesktop()) clearTimeout(leaveTimer); });
  panel.addEventListener('mouseleave',   function(){ if(isDesktop()) leaveTimer = setTimeout(closeMega, 180); });

  // Click (toggle, pour mobile/clavier)
  trigger.addEventListener('click', function(e){
    e.stopPropagation();
    panel.classList.contains('open') ? closeMega() : openMega();
  });

  // Fermer sur overlay ou Escape
  if(overlay) overlay.addEventListener('click', closeMega);
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeMega(); });
  document.addEventListener('click', function(e){
    if(!trigger.contains(e.target) && !panel.contains(e.target)) closeMega();
  });
})();

/* ── Mobile menu ── */
function toggleMenu(){
  var m = document.getElementById('mobileMenu');
  var h = document.getElementById('hamburger');
  if(!m) return;
  var open = m.classList.toggle('open');
  if(h) h.classList.toggle('active', open);
}

/* ── Toast global ── */
window.z85Toast = function(msg, type, duration){
  var el = document.getElementById('z85-toast');
  if(!el) return;
  el.textContent = msg;
  el.className = (type==='success'?'show success':type==='error'?'show error':'show');
  clearTimeout(el._t);
  el._t = setTimeout(function(){ el.className=''; }, duration||3000);
};

/* ── Scroll-to-reveal ── */
(function(){
  if(!window.IntersectionObserver) return;
  var obs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting){ e.target.classList.add('visible'); obs.unobserve(e.target); }
    });
  },{threshold:.12});
  document.querySelectorAll('.reveal').forEach(function(el){ obs.observe(el); });
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.reveal:not(.visible)').forEach(function(el){ obs.observe(el); });
  });
})();

/* ── Counter animation ── */
window.z85AnimateCounter = function(el, from, to, duration){
  if(!el) return;
  var start = performance.now();
  function step(now){
    var pct  = Math.min(1,(now-start)/duration);
    var ease = 1-Math.pow(1-pct,3);
    el.textContent = Math.round(from+(to-from)*ease).toLocaleString('fr-FR');
    if(pct < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
};
</script>
