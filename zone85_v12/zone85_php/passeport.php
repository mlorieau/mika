<?php
// ============================================================
// ZONE 85 — passeport.php v11
// Page publique : Passeport Vendéen partageable (Facebook / lien direct)
// UTF-8 sans BOM
// ============================================================

// ── Variables SEO & OG (définies avant header.php) ───────────
$page_robots    = 'index,follow';
$page_canonical = null; // défini après chargement du profil
$current_page   = 'passeport';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ── Paramètres GET ────────────────────────────────────────────
$pseudo_param = safe_input($_GET['pseudo'] ?? '', 50);
$id_param     = (int)($_GET['id'] ?? 0);

$profil = null;
$badges = [];
$stats  = ['participations' => 0, 'collectibles' => 0, 'badges_count' => 0, 'randos_count' => 0, 'rando_km' => 0, 'communes_count' => 0, 'bocage_randos' => 0, 'marais_randos' => 0, 'littoral_randos' => 0];

if (db_enabled()) {
    $pdo = db();
    if ($pdo) {
        try {
            if ($pseudo_param) {
                $s = $pdo->prepare("SELECT u.*, c.name AS clan_name, c.slug AS clan_slug FROM users u LEFT JOIN clans c ON c.id=u.clan_id WHERE u.pseudo=:p AND u.status='active' AND u.deleted_at IS NULL LIMIT 1");
                $s->execute([':p' => $pseudo_param]);
            } else {
                $s = $pdo->prepare("SELECT u.*, c.name AS clan_name, c.slug AS clan_slug FROM users u LEFT JOIN clans c ON c.id=u.clan_id WHERE u.id=:id AND u.status='active' AND u.deleted_at IS NULL LIMIT 1");
                $s->execute([':id' => $id_param]);
            }
            $profil = $s->fetch();

            if ($profil) {
                // Badges (max 6)
                $bs = $pdo->prepare("SELECT b.title, b.icon_emoji, b.rarity, b.color_primary FROM user_badges ub JOIN badges b ON b.id=ub.badge_id WHERE ub.user_id=:id ORDER BY ub.awarded_at DESC LIMIT 6");
                $bs->execute([':id' => $profil['id']]);
                $badges = $bs->fetchAll();

                // Participations validées
                $ps = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id=:id AND status IN ('validated','auto_validated')");
                $ps->execute([':id' => $profil['id']]);
                $stats['participations'] = (int)$ps->fetchColumn();

                // Badges count
                $bc = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id=:id");
                $bc->execute([':id' => $profil['id']]);
                $stats['badges_count'] = (int)$bc->fetchColumn();

                // Collectibles
                try {
                    $cc = $pdo->prepare("SELECT COUNT(*) FROM user_collectibles WHERE user_id=:id");
                    $cc->execute([':id' => $profil['id']]);
                    $stats['collectibles'] = (int)$cc->fetchColumn();
                } catch (PDOException $e2) {}

                // Passeport Rando : randos réalisées, kilomètres, communes tamponnées
                try {
                    $rs = $pdo->prepare("SELECT COUNT(*) AS nb, COALESCE(SUM(r.distance_km),0) AS km FROM rando_participations rp JOIN randos r ON r.id=rp.rando_id WHERE rp.user_id=:id AND rp.status IN ('stamped','pending','validated')");
                    $rs->execute([':id' => $profil['id']]);
                    $row = $rs->fetch() ?: [];
                    $stats['randos_count'] = (int)($row['nb'] ?? 0);
                    $stats['rando_km'] = (float)($row['km'] ?? 0);

                    $sec = $pdo->prepare("SELECT r.secteur, COUNT(*) AS nb FROM rando_participations rp JOIN randos r ON r.id=rp.rando_id WHERE rp.user_id=:id AND rp.status IN ('stamped','pending','validated') GROUP BY r.secteur");
                    $sec->execute([':id' => $profil['id']]);
                    foreach ($sec->fetchAll() as $sr) {
                        $key = ($sr['secteur'] ?? '') . '_randos';
                        if (array_key_exists($key, $stats)) $stats[$key] = (int)$sr['nb'];
                    }
                } catch (PDOException $e2) {}

                try {
                    $cs = $pdo->prepare("SELECT COUNT(*) FROM rando_commune_stamps WHERE user_id=:id");
                    $cs->execute([':id' => $profil['id']]);
                    $stats['communes_count'] = (int)$cs->fetchColumn();
                } catch (PDOException $e2) {}

                // Tampons individuels pour la grille visuelle
                $rando_stamps = [];
                try {
                    $rsp = $pdo->prepare("
                        SELECT rp.status, rp.done_at, rp.validated_at,
                               r.title, r.slug, r.secteur, r.distance_km, r.commune, r.cover_image
                        FROM rando_participations rp
                        JOIN randos r ON r.id = rp.rando_id
                        WHERE rp.user_id = :uid AND rp.status IN ('stamped','pending','validated')
                        ORDER BY COALESCE(rp.validated_at, rp.done_at) DESC
                        LIMIT 40
                    ");
                    $rsp->execute([':uid' => $profil['id']]);
                    $rando_stamps = $rsp->fetchAll();
                } catch (PDOException $e2) {}
            }
        } catch (PDOException $e) {
            // Silencieux — profil reste null
        }
    }
}

// ── 404 gracieux ──────────────────────────────────────────────
if (!$profil && ($pseudo_param || $id_param > 0)) {
    header('HTTP/1.1 404 Not Found');
}

// ── Données dérivées ──────────────────────────────────────────
$level      = $profil ? max(1, (int)($profil['level'] ?? get_user_level_from_xp((int)($profil['xp_total'] ?? 0)))) : 1;
$level_name = get_level_name($level);
$xp_total   = $profil ? (int)($profil['xp_total'] ?? 0) : 0;
$clan_slug  = $profil['clan_slug'] ?? '';
$clan_name  = $profil['clan_name'] ?? '';

// Seuils XP pour barre de progression
$_xp_thresholds = [0, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 20000];
$xp_cur_threshold = $_xp_thresholds[min($level - 1, 9)] ?? 0;
$xp_nxt_threshold = $_xp_thresholds[min($level, 9)] ?? 20000;
$xp_progress = ($xp_nxt_threshold > $xp_cur_threshold)
    ? min(100, (int)(($xp_total - $xp_cur_threshold) / ($xp_nxt_threshold - $xp_cur_threshold) * 100))
    : 100;

// Clan : couleur, emoji, label
$_clan_data = [
    'bocage'  => ['color' => '#3a7d44', 'bg' => '#e8f5e9', 'text' => '#1b5e20', 'emoji' => '🌳', 'label' => 'Clan du Bocage'],
    'littoral'=> ['color' => '#1565c0', 'bg' => '#e3f2fd', 'text' => '#0d47a1', 'emoji' => '⚓', 'label' => 'Clan du Littoral'],
    'marais'  => ['color' => '#558b2f', 'bg' => '#f1f8e9', 'text' => '#33691e', 'emoji' => '🌿', 'label' => 'Clan du Marais'],
];
$clan_info = $_clan_data[$clan_slug] ?? ['color' => '#ea5649', 'bg' => '#fde8e6', 'text' => '#b71c1c', 'emoji' => '⚡', 'label' => 'Zone85'];

// Avatar
$avatar_type  = $profil['avatar_type'] ?? 'preset';
$avatar_emoji = '🧭';
$avatar_img   = '';
if ($avatar_type === 'upload' && !empty($profil['avatar_file'])) {
    $avatar_img = url(ltrim($profil['avatar_file'], '/'));
} elseif (!empty($profil['avatar_config'])) {
    $cfg = json_decode($profil['avatar_config'], true);
    if (!empty($cfg['emoji'])) $avatar_emoji = $cfg['emoji'];
}

// Date inscription
$joined_display = '';
if (!empty($profil['created_at'])) {
    $ts = strtotime($profil['created_at']);
    if ($ts) $joined_display = date('d/m/Y', $ts);
}

// URL canonique
$_site_url = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
$_self_url = $_site_url . '/passeport.php';
if ($profil) {
    $_self_url .= '?pseudo=' . urlencode($profil['pseudo']);
}
$page_canonical = $_self_url;

// ── Meta OG ───────────────────────────────────────────────────
$page_title          = $profil ? e($profil['pseudo']) . ' — Passeport Zone85' : 'Passeport Zone85';
$page_og_title       = $profil ? $profil['pseudo'] . ' · ' . ($clan_name ?: 'Zone85') . ' · Zone85' : 'Passeport Zone85';
$page_og_description = $profil
    ? 'Niv. ' . $level . ' · ' . $stats['participations'] . ' missions · ' . $stats['badges_count'] . ' badges'
    : 'Découvrez les passeports des membres Zone85, le terrain de jeu communautaire vendéen.';
$page_description    = $page_og_description;

// Session visiteur
$is_guest = !is_logged_in();

// ── Styles inline spécifiques à la page ──────────────────────
$page_styles = <<<CSS
<style>
/* ── Passeport page ───────────────────────────────── */
.pp-page {
  min-height: 100vh;
  background: #0c1e2e;
  padding: 32px 16px 64px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Carte passeport */
.pp-card {
  width: 100%;
  max-width: 480px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0,0,0,.45), 0 4px 16px rgba(0,0,0,.25);
  overflow: hidden;
  margin-bottom: 24px;
}

/* En-tête de la carte */
.pp-card-header {
  background: #0c1e2e;
  padding: 18px 20px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.pp-card-brand {
  font-size: .65rem;
  font-weight: 900;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: #ea5649;
}
.pp-card-brand span {
  color: rgba(255,255,255,.35);
  font-weight: 500;
  letter-spacing: .06em;
  margin-left: 6px;
  font-size: .6rem;
}
.pp-clan-chip-header {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: .7rem;
  font-weight: 700;
}

/* Corps de la carte */
.pp-card-body {
  padding: 24px 20px 20px;
}

/* Avatar */
.pp-avatar-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
}
.pp-avatar {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  border: 3px solid var(--pp-clan-color, #ea5649);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.8rem;
  background: #f8f4ef;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(0,0,0,.12);
}
.pp-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Identité */
.pp-identity {
  text-align: center;
  margin-bottom: 16px;
}
.pp-pseudo {
  font-size: 1.55rem;
  font-weight: 900;
  color: #0c1e2e;
  letter-spacing: -.5px;
  margin: 0 0 4px;
  line-height: 1.15;
}
.pp-since {
  font-size: .75rem;
  color: #6b7f96;
  font-weight: 500;
}

/* Niveau + XP */
.pp-level-block {
  background: #f8f4ef;
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 16px;
}
.pp-level-label {
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: #6b7f96;
  margin-bottom: 6px;
}
.pp-level-line {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 8px;
}
.pp-level-num {
  font-size: 1.6rem;
  font-weight: 900;
  color: #0c1e2e;
  line-height: 1;
}
.pp-level-name {
  font-size: .88rem;
  font-weight: 700;
  color: #3d5166;
}
.pp-xp-bar-bg {
  height: 6px;
  background: #e0dbd4;
  border-radius: 99px;
  overflow: hidden;
}
.pp-xp-bar-fill {
  height: 100%;
  border-radius: 99px;
  background: #ea5649;
  transition: width .4s ease;
}
.pp-xp-label {
  font-size: .68rem;
  color: #8a9bb0;
  margin-top: 4px;
  text-align: right;
}

/* Clan chip grand format */
.pp-clan-big {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 800;
  margin-bottom: 16px;
}
.pp-clan-big .pp-clan-emoji { font-size: 1.4rem; }

/* Stats 3 colonnes */
.pp-stats {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 8px;
  margin-bottom: 16px;
}
.pp-stat {
  background: #f8f4ef;
  border-radius: 10px;
  padding: 10px 8px 8px;
  text-align: center;
}
.pp-stat-val {
  font-size: 1.3rem;
  font-weight: 900;
  color: #0c1e2e;
  line-height: 1;
}
.pp-stat-lbl {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #6b7f96;
  margin-top: 3px;
}

/* Badges section */
.pp-rando-panel { margin: 18px 28px 0; padding: 18px; border-radius: 18px; background: rgba(255,255,255,.72); border:1px solid rgba(12,30,46,.08); }
.pp-rando-title { font-size:.78rem; text-transform:uppercase; letter-spacing:.14em; font-weight:900; color:#ea5649; margin-bottom:12px; }
.pp-rando-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.pp-rando-mini { background:#fff; border-radius:14px; padding:12px 10px; text-align:center; box-shadow:0 4px 14px rgba(12,30,46,.05); }
.pp-rando-mini strong { display:block; font-size:1.15rem; color:#0c1e2e; }
.pp-rando-mini span { display:block; margin-top:2px; font-size:.68rem; color:#6b7f96; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
.pp-territory { display:flex;gap:8px;flex-wrap:wrap;margin-top:12px; }
.pp-territory span { border-radius:999px;background:#f8f4ef;border:1px solid #e7ded5;padding:6px 10px;font-size:.72rem;font-weight:800;color:#0c1e2e; }

.pp-badges-title {
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: #6b7f96;
  margin-bottom: 10px;
}
.pp-badges-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 4px;
}
.pp-badge-item {
  background: #f8f4ef;
  border-radius: 10px;
  padding: 8px 6px;
  text-align: center;
}
.pp-badge-emoji { font-size: 1.4rem; line-height: 1; }
.pp-badge-name {
  font-size: .6rem;
  font-weight: 700;
  color: #3d5166;
  margin-top: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pp-badge-rare {
  display: inline-block;
  font-size: .55rem;
  padding: 1px 5px;
  border-radius: 99px;
  font-weight: 700;
  margin-top: 2px;
}
.rare-legendary { background: #fff3cd; color: #856404; }
.rare-epic      { background: #e8d5f5; color: #6f42c1; }
.rare-rare      { background: #cce5ff; color: #004085; }
.rare-uncommon  { background: #d4edda; color: #155724; }
.rare-common    { background: #f0ece7; color: #6b7f96; }

/* Pied de carte */
.pp-card-foot {
  background: #f0ece7;
  padding: 10px 20px;
  text-align: center;
  font-size: .65rem;
  color: #8a9bb0;
  font-weight: 600;
  letter-spacing: .05em;
  border-top: 1px solid #e8e2db;
}

/* Boutons sous la carte */
.pp-actions {
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 32px;
}
.pp-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  border-radius: 12px;
  font-size: .9rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  border: none;
  font-family: inherit;
  transition: opacity .15s, transform .1s;
  line-height: 1;
}
.pp-btn:hover  { opacity: .88; transform: translateY(-1px); }
.pp-btn:active { transform: translateY(0); }
.pp-btn-fb   { background: #1877f2; color: #fff; }
.pp-btn-missions { background: #ea5649; color: #fff; }
.pp-btn-join { background: rgba(255,255,255,.1); color: rgba(255,255,255,.75); border: 1.5px solid rgba(255,255,255,.2); }
.pp-btn-join:hover { background: rgba(255,255,255,.16); color: #fff; }

/* CTA recrutement */
.pp-recruit {
  width: 100%;
  max-width: 480px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 28px 24px;
  text-align: center;
}
.pp-recruit-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 6px;
  line-height: 1.3;
}
.pp-recruit-sub {
  font-size: .82rem;
  color: rgba(255,255,255,.5);
  margin-bottom: 18px;
}

/* État 404 */
.pp-404 {
  width: 100%;
  max-width: 480px;
  text-align: center;
  padding: 48px 24px;
}
.pp-404-icon { font-size: 4rem; margin-bottom: 16px; }
.pp-404-title { font-size: 1.4rem; font-weight: 900; color: #fff; margin-bottom: 8px; }
.pp-404-sub   { font-size: .88rem; color: rgba(255,255,255,.5); margin-bottom: 24px; }

@media (min-width: 500px) {
  .pp-actions { flex-direction: row; flex-wrap: wrap; }
  .pp-btn { flex: 1; }
  .pp-btn-fb { flex: none; width: 100%; }
}

.pp-stamps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}
.pp-stamp-card{display:flex;flex-direction:column;background:#fff;border-radius:10px;padding:10px 8px 8px;text-decoration:none;position:relative;overflow:hidden;transition:transform .15s;border:1px solid rgba(0,0,0,.07)}
.pp-stamp-card:hover{transform:translateY(-2px)}
.pp-stamp-status{position:absolute;top:7px;right:7px;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#fff;font-weight:900}
.pp-stamp-title{font-size:.68rem;font-weight:800;color:#0c1e2e;line-height:1.3;margin-bottom:4px;padding-right:20px}
.pp-stamp-dist{font-size:.6rem;color:#6b7f96;font-weight:700}
.pp-stamp-date{font-size:.58rem;color:#aaa;margin-top:2px}
@media(max-width:380px){.pp-stamps-grid{grid-template-columns:repeat(2,1fr)}}
</style>
CSS;

require_once 'includes/header.php';
?>

<main>
<div class="pp-page">

<?php if (!$profil): ?>
<!-- ── État 404 ──────────────────────────────────────────── -->
<div class="pp-404">
  <div class="pp-404-icon">🗺️</div>
  <div class="pp-404-title">Passeport introuvable</div>
  <div class="pp-404-sub">Ce membre n'existe pas ou son compte n'est plus actif.</div>
  <a href="communaute.php?tab=classement" class="pp-btn pp-btn-missions" style="display:inline-flex;margin-bottom:10px">Voir le classement</a>
  <br>
  <a href="inscription.php" class="pp-btn pp-btn-join" style="display:inline-flex;margin-top:8px">Rejoindre la Zone →</a>
</div>

<?php else: ?>
<?php
  // XP formaté
  $_xp_fmt = number_format($xp_total, 0, ',', ' ') . ' XP';
  $_fb_url  = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($_self_url);
  $clan_color_css = '--pp-clan-color:' . $clan_info['color'];
?>

<!-- ── CARTE PASSEPORT ──────────────────────────────────── -->
<div class="pp-card" style="<?= $clan_color_css ?>">

  <!-- En-tête carte -->
  <div class="pp-card-header">
    <div class="pp-card-brand">ZONE85 <span>Passeport Vendéen</span></div>
    <?php if ($clan_slug): ?>
    <div class="pp-clan-chip-header" style="background:<?= e($clan_info['bg']) ?>;color:<?= e($clan_info['text']) ?>">
      <span><?= $clan_info['emoji'] ?></span>
      <span><?= e($clan_name) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Corps carte -->
  <div class="pp-card-body">

    <!-- Avatar -->
    <div class="pp-avatar-wrap">
      <div class="pp-avatar">
        <?php if ($avatar_img): ?>
          <img src="<?= e($avatar_img) ?>" alt="Avatar de <?= e($profil['pseudo']) ?>">
        <?php else: ?>
          <?= $avatar_emoji ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Identité -->
    <div class="pp-identity">
      <h1 class="pp-pseudo"><?= e($profil['pseudo']) ?></h1>
      <?php if ($joined_display): ?>
      <div class="pp-since">Membre depuis le <?= e($joined_display) ?></div>
      <?php endif; ?>
    </div>

    <!-- Niveau + barre XP -->
    <div class="pp-level-block">
      <div class="pp-level-label">Niveau</div>
      <div class="pp-level-line">
        <span class="pp-level-num"><?= $level ?></span>
        <span class="pp-level-name"><?= e($level_name) ?></span>
      </div>
      <div class="pp-xp-bar-bg">
        <div class="pp-xp-bar-fill" style="width:<?= $xp_progress ?>%;background:<?= e($clan_info['color']) ?>"></div>
      </div>
      <div class="pp-xp-label"><?= e($_xp_fmt) ?></div>
    </div>

    <!-- Clan chip grand format -->
    <?php if ($clan_slug): ?>
    <div class="pp-clan-big" style="background:<?= e($clan_info['bg']) ?>;color:<?= e($clan_info['text']) ?>">
      <span class="pp-clan-emoji"><?= $clan_info['emoji'] ?></span>
      <span><?= e($clan_info['label']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Stats 3 colonnes -->
    <div class="pp-stats">
      <div class="pp-stat">
        <div class="pp-stat-val"><?= number_format($stats['participations'], 0, ',', ' ') ?></div>
        <div class="pp-stat-lbl">Missions</div>
      </div>
      <div class="pp-stat">
        <div class="pp-stat-val"><?= $stats['badges_count'] ?></div>
        <div class="pp-stat-lbl">Badges</div>
      </div>
      <div class="pp-stat">
        <div class="pp-stat-val"><?= number_format($xp_total, 0, ',', ' ') ?></div>
        <div class="pp-stat-lbl">XP</div>
      </div>
    </div>

    <div class="pp-rando-panel">
      <div class="pp-rando-title">Passeport Rando</div>
      <div class="pp-rando-grid">
        <div class="pp-rando-mini"><strong><?= (int)$stats['randos_count'] ?></strong><span>Randos</span></div>
        <div class="pp-rando-mini"><strong><?= number_format((float)$stats['rando_km'], 1, ',', ' ') ?></strong><span>Km</span></div>
        <div class="pp-rando-mini"><strong><?= (int)$stats['communes_count'] ?></strong><span>Communes</span></div>
      </div>
      <div class="pp-territory">
        <span>Bocage : <?= (int)$stats['bocage_randos'] ?></span>
        <span>Marais : <?= (int)$stats['marais_randos'] ?></span>
        <span>Littoral : <?= (int)$stats['littoral_randos'] ?></span>
      </div>
      <?php if (!empty($rando_stamps)): ?>
      <div class="pp-stamps-grid">
        <?php foreach ($rando_stamps as $stamp):
          $sec_colors = ['bocage'=>'#2a9d5c','littoral'=>'#12314e','marais'=>'#8b6914','plaine'=>'#6b7f96'];
          $col = $sec_colors[$stamp['secteur']] ?? '#12314e';
          $status_map = ['validated'=>['🏆','#2a9d5c'],'pending'=>['⏳','#e9951a'],'stamped'=>['✓','#12314e']];
          [$sico, $scol] = $status_map[$stamp['status']] ?? ['✓','#12314e'];
          $date = !empty($stamp['validated_at']) ? substr($stamp['validated_at'],0,10) : substr($stamp['done_at'],0,10);
          $dist = $stamp['distance_km'] ? number_format((float)$stamp['distance_km'],1,',',' ').' km' : '';
        ?>
        <a href="rando.php?slug=<?= urlencode($stamp['slug']) ?>" class="pp-stamp-card" style="border-top:3px solid <?= $col ?>">
          <div class="pp-stamp-status" style="background:<?= $scol ?>"><?= $sico ?></div>
          <div class="pp-stamp-title"><?= htmlspecialchars(mb_substr($stamp['title'],0,28), ENT_QUOTES,'UTF-8') ?><?= mb_strlen($stamp['title'])>28?'…':'' ?></div>
          <?php if ($dist): ?><div class="pp-stamp-dist"><?= htmlspecialchars($dist,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
          <div class="pp-stamp-date"><?= htmlspecialchars($date,ENT_QUOTES,'UTF-8') ?></div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Badges -->
    <?php if (!empty($badges)): ?>
    <div class="pp-badges-title">Badges obtenus</div>
    <div class="pp-badges-grid">
      <?php foreach ($badges as $b):
        $rarity_class = 'rare-' . ($b['rarity'] ?? 'common');
        $rarity_label = match($b['rarity'] ?? 'common') {
            'legendary' => 'Légendaire',
            'epic'      => 'Épique',
            'rare'      => 'Rare',
            'uncommon'  => 'Peu commun',
            default     => 'Commun',
        };
      ?>
      <div class="pp-badge-item">
        <div class="pp-badge-emoji"><?= !empty($b['icon_emoji']) ? $b['icon_emoji'] : '🏅' ?></div>
        <div class="pp-badge-name"><?= e($b['title'] ?? '') ?></div>
        <span class="pp-badge-rare <?= $rarity_class ?>"><?= $rarity_label ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- .pp-card-body -->

  <!-- Pied de carte -->
  <div class="pp-card-foot">
    Passeport Zone85 &nbsp;·&nbsp; zone85.fr
  </div>

</div><!-- .pp-card -->

<!-- ── BOUTONS ACTIONS ───────────────────────────────────── -->
<div class="pp-actions">
  <a href="<?= e($_fb_url) ?>" target="_blank" rel="noopener noreferrer" class="pp-btn pp-btn-fb">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.791-4.697 4.534-4.697 1.313 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
    Partager sur Facebook
  </a>
  <a href="missions.php" class="pp-btn pp-btn-missions">Voir toutes les missions</a>
  <?php if ($is_guest): ?>
  <a href="inscription.php" class="pp-btn pp-btn-join">Rejoindre la Zone →</a>
  <?php endif; ?>
</div>

<!-- ── CTA RECRUTEMENT (invités seulement) ──────────────── -->
<?php if ($is_guest): ?>
<div class="pp-recruit">
  <div class="pp-recruit-title">Toi aussi, construis ta légende vendéenne.</div>
  <div class="pp-recruit-sub">Rejoins un clan, gagne de l'XP, explore la Vendée autrement.</div>
  <a href="inscription.php" class="pp-btn pp-btn-missions" style="display:inline-flex">Rejoindre gratuitement →</a>
</div>
<?php endif; ?>

<?php endif; // profil trouvé ?>

</div><!-- .pp-page -->
</main>

<?php require_once 'includes/footer.php'; ?>
