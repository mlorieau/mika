<?php
// nav.php — Navigation partagee Zone85 V11
// Variable attendue : $current_page (string) — slug de la page active

$_nav_links = [
    ['slug' => 'index',     'label' => 'Accueil',        'file' => 'index.php'],
    ['slug' => 'missions',  'label' => 'Missions',        'file' => 'missions.php'],
    ['slug' => 'randos',    'label' => 'Randos',          'file' => 'randos.php'],
    ['slug' => 'ktc',       'label' => 'KTC',             'file' => 'ktc.php'],
    ['slug' => 'clans',     'label' => 'Clans',           'file' => 'clans.php'],
    ['slug' => 'zonautes',  'label' => 'Zonautes',        'file' => 'zonautes.php'],
    ['slug' => 'les-echos', 'label' => 'Les Echos',       'file' => 'les-echos.php'],
    ['slug' => 'hall',      'label' => 'Hall de la Zone', 'file' => 'hall.php'],
    ['slug' => 'communaute','label' => 'Communauté',      'file' => 'communaute.php'],
];

$_cp       = $current_page ?? '';
$_nav_user = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['user'] ?? null) : null;

// Initiales pour l'avatar nav
$_nav_initials = '';
$_nav_notif_count = 0;
if ($_nav_user) {
    $_nav_initials = strtoupper(mb_substr($_nav_user['pseudo'], 0, 2));
    if (function_exists('count_unread_notifications')) {
        $_nav_notif_count = count_unread_notifications((int)$_nav_user['id']);
    }
}
?>
<nav id="navbar">
  <div class="nav-inner">
    <div class="nav-left">
      <?php foreach ($_nav_links as $_nl): ?>
        <a href="<?= $_nl['file'] ?>"<?= ($_cp === $_nl['slug']) ? ' class="active"' : '' ?>><?= $_nl['label'] ?></a>
      <?php endforeach; ?>
    </div>
    <a href="index.php" class="nav-logo" aria-label="ZONE85 — Accueil"><img src="<?= img('logo-header.png') ?>" alt="ZONE85 — L'Esprit Vendee"></a>
    <div class="nav-right">
      <?php if ($_nav_user): ?>
        <!-- Cloche notifications -->
        <a href="notifications.php" class="nav-notif-bell" title="Notifications" style="position:relative;display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;text-decoration:none;font-size:1.1rem;color:var(--text-mid);transition:background .15s<?= ($_cp === 'notifications') ? ';background:rgba(234,86,73,.1)' : '' ?>">
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
        <a href="logout.php" class="nav-btn" style="background:transparent;border:2px solid var(--beige-dark);color:var(--text-mid)" onclick="return confirm('Se deconnecter ?')">Deconnexion</a>
      <?php else: ?>
        <a href="login.php" style="font-size:.84rem;font-weight:700;color:var(--text-mid);text-decoration:none;white-space:nowrap">Connexion</a>
        <a href="inscription.php" class="nav-btn">Rejoindre</a>
      <?php endif; ?>
      <button class="dark-mode-toggle" id="dark-toggle" onclick="toggleDarkMode()" title="Changer le thème"
              aria-label="Basculer mode sombre"></button>
      <div class="hamburger" id="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
    </div>
  </div>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <?php foreach ($_nav_links as $_nl): ?>
    <a href="<?= $_nl['file'] ?>"><?= $_nl['label'] ?></a>
  <?php endforeach; ?>
  <?php if ($_nav_user): ?>
    <a href="profil.php">Mon Profil — <?= e($_nav_user['pseudo']) ?></a>
    <a href="mon-compte.php">&#9881;&#65039; Mon Compte</a>
    <a href="logout.php" style="color:var(--text-muted)" onclick="return confirm('Se deconnecter ?')">Deconnexion</a>
  <?php else: ?>
    <a href="profil.php">Mon Profil</a>
    <a href="login.php">Connexion</a>
    <a href="inscription.php" style="color:var(--primary);font-weight:800">Rejoindre la Zone &#8594;</a>
  <?php endif; ?>
  <div style="border-top:1px solid rgba(255,255,255,.08);margin-top:8px;padding-top:8px">
    <a href="trophees.php" style="font-size:.8rem;color:rgba(255,255,255,.45);font-weight:600;border-bottom:none">&#127942; Trophees</a>
  </div>
</div>

<!-- Toast container -->
<div id="z85-toast" role="status" aria-live="polite"></div>

<script>
/* ── Dark mode ── */
(function(){
  var html = document.documentElement;
  var btn  = document.getElementById('dark-toggle');
  function applyIcon(){
    var dark = html.getAttribute('data-theme') === 'dark';
    if(btn) btn.textContent = dark ? '☀️' : '🌙';
  }
  applyIcon();
  window.toggleDarkMode = function(){
    var isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('z85-theme', isDark ? 'light' : 'dark');
    applyIcon();
  };
})();

/* ── Navbar scroll shadow ── */
(function(){
  var nb = document.getElementById('navbar');
  if(!nb) return;
  window.addEventListener('scroll', function(){
    nb.classList.toggle('scrolled', window.scrollY > 16);
  }, {passive:true});
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
  el.className = (type === 'success' ? 'show success' : type === 'error' ? 'show error' : 'show');
  clearTimeout(el._t);
  el._t = setTimeout(function(){ el.className = ''; }, duration || 3000);
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
  // Re-run after potential dynamic content
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.reveal:not(.visible)').forEach(function(el){ obs.observe(el); });
  });
})();

/* ── Counter animation ── */
window.z85AnimateCounter = function(el, from, to, duration){
  if(!el) return;
  var start = performance.now();
  function step(now){
    var pct = Math.min(1,(now-start)/duration);
    var ease = 1-Math.pow(1-pct,3);
    el.textContent = Math.round(from + (to-from)*ease).toLocaleString('fr-FR');
    if(pct < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
};
</script>
