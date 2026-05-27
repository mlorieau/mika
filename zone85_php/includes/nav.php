<?php
// nav.php — Navigation partagée
// Variable attendue : $current_page (string) — slug de la page active
// Ex : 'index', 'concept', 'clans', 'missions', 'classement', 'hall', 'profil', 'inscription'
$_nav_links = [
    ['slug' => 'index',       'label' => 'Accueil',       'file' => 'index.php'],
    ['slug' => 'concept',     'label' => 'Le Concept',    'file' => 'concept.php'],
    ['slug' => 'clans',       'label' => 'Les Clans',     'file' => 'clans.php'],
    ['slug' => 'missions',    'label' => 'Missions',      'file' => 'missions.php'],
    ['slug' => 'classement',  'label' => 'Classement',    'file' => 'classement.php'],
    ['slug' => 'hall',        'label' => 'Hall de la Zone','file' => 'hall.php'],
];
$_cp = $current_page ?? '';
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
      <div class="nav-avatar" title="Mon profil" onclick="location.href='profil.php'">SM</div>
      <a href="inscription.php" class="nav-btn">Rejoindre</a>
      <div class="hamburger" id="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></div>
    </div>
  </div>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <?php foreach ($_nav_links as $_nl): ?>
    <a href="<?= $_nl['file'] ?>"><?= $_nl['label'] ?></a>
  <?php endforeach; ?>
  <a href="profil.php">Mon Profil</a>
  <a href="inscription.php" style="color:var(--primary);font-weight:800;border-bottom:none">Rejoindre la Zone →</a>
</div>
