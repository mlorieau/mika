<?php
// nav.php — Navigation partagée
// Variable attendue : $current_page (string) — slug de la page active
$_nav_links = [
    ['slug' => 'index',       'label' => 'Accueil',        'file' => 'index.php'],
    ['slug' => 'concept',     'label' => 'Le Concept',     'file' => 'concept.php'],
    ['slug' => 'clans',       'label' => 'Les Clans',      'file' => 'clans.php'],
    ['slug' => 'missions',    'label' => 'Missions',       'file' => 'missions.php'],
    ['slug' => 'classement',  'label' => 'Classement',     'file' => 'classement.php'],
    ['slug' => 'hall',        'label' => 'Hall de la Zone','file' => 'hall.php'],
];
$_cp       = $current_page ?? '';
$_nav_user = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['user'] ?? null) : null;

// Initiales pour l'avatar nav
$_nav_initials = '';
if ($_nav_user) {
    $_nav_initials = strtoupper(mb_substr($_nav_user['pseudo'], 0, 2));
}
?>
<nav id="navbar">
  <div class="nav-inner">
    <div class="nav-left">
      <?php foreach ($_nav_links as $_nl): ?>
        <a href="<?= $_nl['file'] ?>"<?= ($_cp === $_nl['slug']) ? ' class="active"' : '' ?>><?= $_nl['label'] ?></a>
      <?php endforeach; ?>
    </div>
    <a href="index.php" class="nav-logo">ZONE<span>85</span><span class="nav-logo-sub">L'Esprit Vendée</span></a>
    <div class="nav-right">
      <?php if ($_nav_user): ?>
        <div class="nav-avatar" title="<?= e($_nav_user['pseudo']) ?>" onclick="location.href='profil.php'" style="cursor:pointer">
          <?php if ($_nav_user['avatar_type'] === 'upload' && !empty($_nav_user['avatar_key'])): ?>
            <img src="<?= e($_nav_user['avatar_key']) ?>" alt="<?= e($_nav_user['pseudo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
          <?php else: ?>
            <?= mb_substr(e($_nav_user['avatar_key']), 0, 2) === strtoupper(mb_substr(e($_nav_user['avatar_key']), 0, 2)) ? e($_nav_initials) : e($_nav_user['avatar_key']) ?>
          <?php endif; ?>
        </div>
        <a href="profil.php" style="font-size:.84rem;font-weight:700;color:var(--text);text-decoration:none;white-space:nowrap;display:none" class="nav-profil-link">Mon Profil</a>
        <a href="logout.php" class="nav-btn" style="background:transparent;border:2px solid var(--beige-dark);color:var(--text-mid)" onclick="return confirm('Se déconnecter ?')">Déconnexion</a>
      <?php else: ?>
        <a href="login.php" style="font-size:.84rem;font-weight:700;color:var(--text-mid);text-decoration:none;white-space:nowrap">Connexion</a>
        <a href="inscription.php" class="nav-btn">Rejoindre</a>
      <?php endif; ?>
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
    <a href="logout.php" style="color:var(--text-muted);border-bottom:none" onclick="return confirm('Se déconnecter ?')">Déconnexion</a>
  <?php else: ?>
    <a href="profil.php">Mon Profil</a>
    <a href="login.php">Connexion</a>
    <a href="inscription.php" style="color:var(--primary);font-weight:800;border-bottom:none">Rejoindre la Zone →</a>
  <?php endif; ?>
</div>
