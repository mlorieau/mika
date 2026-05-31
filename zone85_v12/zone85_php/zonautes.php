<?php
// ============================================================
// ZONE 85 — zonautes.php v11 Premium
// Classement joueurs avec Passeport Vendéen (overlay modal)
// UTF-8 sans BOM
// ============================================================
$page_title       = 'Les Zonautes';
$page_description = 'Découvrez les aventuriers de Zone85, leurs scores et leur clan. Cliquez sur un Zonaute pour ouvrir son Passeport Vendéen.';
$page_canonical   = 'https://www.zone85.fr/zonautes.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil',     'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Les Zonautes','item' => 'https://www.zone85.fr/zonautes.php'],
    ],
];
$current_page = 'zonautes';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/repositories.php';

// ── Paramètres ───────────────────────────────────────────────
$offset       = max(0, (int)($_GET['offset'] ?? 0));
$filter_clan  = in_array($_GET['clan'] ?? '', ['bocage', 'littoral', 'marais', ''])
                ? ($_GET['clan'] ?? '') : '';
$sort_mode    = ($_GET['sort'] ?? 'total') === 'season' ? 'season' : 'total';
$per_page     = 50;

// ── Chargement DB ───────────────────────────────────────────
$players     = [];
$total_count = 0;

$pdo = db();
if ($pdo) {
    try {
        $sr           = _active_season_row();
        $season_start = $sr ? $sr['start_date'] : '1970-01-01';

        $where_clan = $filter_clan ? 'AND c.slug = :clan_filter' : '';
        $order_by   = $sort_mode === 'season'
            ? 'xp_season DESC, u.xp_total DESC'
            : 'u.xp_total DESC';

        $sql = "
            SELECT
                u.id,
                u.pseudo,
                u.avatar_type,
                u.avatar_config,
                u.avatar_file,
                u.level,
                u.xp_total,
                c.slug  AS clan_slug,
                c.name  AS clan_name,
                u.created_at,
                (SELECT COALESCE(SUM(xp_amount),0)
                 FROM xp_logs xl
                 WHERE xl.user_id = u.id
                   AND xl.created_at >= :season_start) AS xp_season
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.status = 'active'
              AND u.deleted_at IS NULL
              $where_clan
            ORDER BY $order_by
            LIMIT :lim OFFSET :off
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':season_start', $season_start, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
        if ($filter_clan) {
            $stmt->bindValue(':clan_filter', $filter_clan, PDO::PARAM_STR);
        }
        $stmt->execute();
        $players = $stmt->fetchAll();

        // Comptage total pour "Voir plus"
        $cnt_sql = "
            SELECT COUNT(*) FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.status = 'active'
              AND u.deleted_at IS NULL
              $where_clan
        ";
        $cnt_stmt = $pdo->prepare($cnt_sql);
        if ($filter_clan) {
            $cnt_stmt->bindValue(':clan_filter', $filter_clan, PDO::PARAM_STR);
        }
        $cnt_stmt->execute();
        $total_count = (int)$cnt_stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log('[ZONE85] zonautes.php : ' . $e->getMessage());
    }
}

// ── Rang global (offset + index) ────────────────────────────
$rank_start = $offset + 1;

// ── Chip classes clan ────────────────────────────────────────
$chip_map = [
    'bocage'   => 'bocage-chip-sm',
    'littoral' => 'littoral-chip-sm',
    'marais'   => 'marais-chip-sm',
];

$page_styles = '<style>
/* ── ZONAUTES V11 ─────────────────────────────────────────── */

/* HERO */
.zonautes-hero{
  background:linear-gradient(160deg,#0a1826 0%,#0d1e2c 60%,#12314e 100%);
  padding:100px 0 56px;
  position:relative;overflow:hidden;
  text-align:center;
}
.zonautes-hero::before{
  content:"";position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.015\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/svg%3E");
  pointer-events:none;
}
.zonautes-hero-inner{position:relative;z-index:1;max-width:800px;margin:0 auto;padding:0 24px;}
.zonautes-hero .overline-label{
  font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:rgba(255,255,255,.45);margin-bottom:14px;
}
.zonautes-hero h1{
  font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#fff;
  letter-spacing:-1px;margin-bottom:14px;
}
.zonautes-hero .hero-sub{
  font-size:.95rem;color:rgba(255,255,255,.55);
  max-width:480px;margin:0 auto;line-height:1.6;
}

/* FILTRES */
.zonautes-filters{
  background:#fff;border-bottom:1px solid rgba(18,49,78,.08);
  position:sticky;top:68px;z-index:90;
}
.filters-inner{
  display:flex;align-items:center;gap:8px;
  max-width:1160px;margin:0 auto;padding:12px 24px;
  overflow-x:auto;scrollbar-width:none;
}
.filters-inner::-webkit-scrollbar{display:none;}
.filter-btn{
  padding:7px 18px;border-radius:20px;
  border:1px solid #e5e7eb;background:transparent;
  font-size:.82rem;font-weight:700;color:#6b7280;
  cursor:pointer;font-family:inherit;white-space:nowrap;
  transition:all .18s;
}
.filter-btn:hover{border-color:#12314e;color:#12314e;}
.filter-btn.active{background:#12314e;border-color:#12314e;color:#fff;}
.filter-btn.bocage-active{background:#2a9d5c;border-color:#2a9d5c;color:#fff;}
.filter-btn.littoral-active{background:#1a6fb8;border-color:#1a6fb8;color:#fff;}
.filter-btn.marais-active{background:#8b6340;border-color:#8b6340;color:#fff;}
.filter-sort-toggle{
  margin-left:auto;padding:7px 18px;border-radius:20px;
  border:1px dashed #d1d5db;background:transparent;
  font-size:.78rem;font-weight:700;color:#9ca3af;
  cursor:pointer;font-family:inherit;white-space:nowrap;
  transition:all .18s;
}
.filter-sort-toggle.active{border-style:solid;border-color:#f59e0b;color:#b45309;background:#fffbeb;}

/* GRILLE JOUEURS */
.zonautes-section{background:#f7f5f2;min-height:500px;padding:48px 0 80px;}
.zonautes-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
  gap:16px;
  max-width:1160px;margin:0 auto;padding:0 24px;
}
@media(max-width:600px){.zonautes-grid{grid-template-columns:repeat(2,1fr);gap:12px;}}

/* CARD JOUEUR */
.player-card{
  background:#fff;border-radius:14px;
  border:1px solid rgba(18,49,78,.07);
  padding:20px 16px;text-align:center;
  cursor:pointer;transition:all .2s;
  position:relative;overflow:hidden;
  box-shadow:0 1px 4px rgba(18,49,78,.05);
}
.player-card:hover{
  transform:translateY(-4px);
  box-shadow:0 10px 30px rgba(18,49,78,.12);
  border-color:rgba(18,49,78,.2);
}
.pc-rank-badge{
  position:absolute;top:10px;left:10px;
  width:24px;height:24px;border-radius:50%;
  background:#f3f4f6;
  display:flex;align-items:center;justify-content:center;
  font-size:.68rem;font-weight:900;color:#6b7280;
}
.pc-rank-badge.rank-1{background:#f6c90e;color:#7a5a00;}
.pc-rank-badge.rank-2{background:#c0c0c0;color:#444;}
.pc-rank-badge.rank-3{background:#cd7f32;color:#fff;}
.pc-avatar{
  width:64px;height:64px;border-radius:50%;
  margin:0 auto 10px;overflow:hidden;
  background:#f0ece7;
  display:flex;align-items:center;justify-content:center;
  font-size:1.8rem;
}
.pc-avatar img{width:100%;height:100%;object-fit:cover;}
.pc-pseudo{font-size:.92rem;font-weight:800;color:#1f2937;margin-bottom:6px;}
.pc-clan-chip{
  display:inline-block;padding:3px 10px;border-radius:10px;
  font-size:.68rem;font-weight:700;margin-bottom:8px;
}
.pc-level-badge{
  font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:4px;
}
.pc-xp{font-size:.88rem;font-weight:800;color:#0d1e2c;}
.pc-xp small{font-weight:600;font-size:.72rem;color:#9ca3af;}

/* PASSEPORT — OVERLAY MODAL */
.passport-overlay{
  position:fixed;inset:0;z-index:9000;
  background:rgba(6,16,26,.8);
  backdrop-filter:blur(6px);
  display:flex;align-items:center;justify-content:center;
  padding:20px;
  opacity:0;pointer-events:none;
  transition:opacity .25s;
}
.passport-overlay.open{opacity:1;pointer-events:all;}
.passport-modal{
  background:#fff;border-radius:20px;
  width:100%;max-width:480px;
  max-height:90vh;overflow-y:auto;
  box-shadow:0 30px 80px rgba(0,0,0,.5);
  transform:translateY(20px);
  transition:transform .3s cubic-bezier(.22,1,.36,1);
}
.passport-overlay.open .passport-modal{transform:translateY(0);}
.pp-header{
  background:linear-gradient(135deg,#0a1a2e,#163756);
  border-radius:20px 20px 0 0;
  padding:28px 28px 20px;
  display:flex;align-items:center;gap:18px;
  position:relative;
}
.pp-close{
  position:absolute;top:14px;right:16px;
  width:32px;height:32px;border-radius:50%;
  background:rgba(255,255,255,.12);border:none;cursor:pointer;
  color:#fff;font-size:1.1rem;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s;
}
.pp-close:hover{background:rgba(255,255,255,.25);}
.pp-logo{
  font-size:.6rem;font-weight:900;letter-spacing:.18em;
  color:rgba(255,255,255,.35);text-transform:uppercase;
  margin-bottom:4px;
}
.pp-id-label{
  font-size:.65rem;font-weight:700;letter-spacing:.14em;
  color:rgba(255,255,255,.4);text-transform:uppercase;margin-bottom:2px;
}
.pp-avatar{
  width:76px;height:76px;border-radius:50%;
  border:3px solid rgba(255,255,255,.2);overflow:hidden;
  background:rgba(255,255,255,.08);
  display:flex;align-items:center;justify-content:center;
  font-size:2.2rem;flex-shrink:0;
}
.pp-avatar img{width:100%;height:100%;object-fit:cover;}
.pp-info{flex:1;}
.pp-pseudo{font-size:1.2rem;font-weight:900;color:#fff;margin-bottom:4px;}
.pp-clan-line{font-size:.82rem;color:rgba(255,255,255,.55);}
.pp-body{padding:22px 28px 28px;}
.pp-member-since{
  font-size:.72rem;font-weight:700;letter-spacing:.1em;
  text-transform:uppercase;color:#9ca3af;margin-bottom:18px;
}
.pp-stats-grid{
  display:grid;grid-template-columns:repeat(2,1fr);gap:14px;
  margin-bottom:22px;
}
.pp-stat{
  background:#f7f5f2;border-radius:10px;padding:12px 14px;
}
.pp-stat-val{display:block;font-size:1.15rem;font-weight:900;color:#0d1e2c;}
.pp-stat-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-top:2px;}
.pp-level-line{
  display:flex;align-items:center;gap:10px;margin-bottom:18px;
}
.pp-level-badge{
  padding:5px 14px;border-radius:20px;
  background:#12314e;color:#fff;
  font-size:.82rem;font-weight:800;
}
.pp-level-name{font-size:.88rem;color:#374151;font-weight:600;}
.pp-badges-title{
  font-size:.68rem;font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:#9ca3af;
  margin-bottom:10px;border-top:1px solid #f3f4f6;padding-top:16px;
}
.pp-badges-row{display:flex;gap:8px;flex-wrap:wrap;}
.pp-badge-chip{
  padding:5px 12px;border-radius:10px;background:#f3f4f6;
  font-size:.8rem;font-weight:700;color:#374151;
}
.pp-badge-chip.rarity-legendary{background:#fef3c7;color:#92400e;}
.pp-badge-chip.rarity-epic{background:#f3e8ff;color:#6b21a8;}
.pp-badge-chip.rarity-rare{background:#dbeafe;color:#1e40af;}
.pp-badge-chip.rarity-uncommon{background:#d1fae5;color:#065f46;}
.pp-no-badges{font-size:.85rem;color:#9ca3af;font-style:italic;}

/* VOIR PLUS */
.voir-plus-wrap{
  max-width:1160px;margin:32px auto 0;padding:0 24px;
  text-align:center;
}

/* VIDE */
.zonautes-empty{
  text-align:center;padding:80px 24px;
  font-size:.95rem;color:#9ca3af;font-style:italic;
}

/* Spinner */
.pp-loading{text-align:center;padding:48px;color:#9ca3af;font-size:.9rem;}
.spinner{
  width:32px;height:32px;border-radius:50%;
  border:3px solid #e5e7eb;border-top-color:#12314e;
  animation:spin .7s linear infinite;display:inline-block;margin-bottom:10px;
}
@keyframes spin{to{transform:rotate(360deg);}}

/* Utilitaires clan chips (small) */
.bocage-chip-sm{background:#e8f5ee;color:#1a5c38;}
.littoral-chip-sm{background:#e8f0fb;color:#154f8b;}
.marais-chip-sm{background:#f5ede4;color:#6b4c2a;}

/* Responsive */
@media(max-width:520px){
  .pp-header{flex-direction:column;text-align:center;}
  .pp-close{top:10px;right:10px;}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';

// ── Construire l'URL pour les filtres ──────────────────────
function zonautes_url(array $overrides = []): string {
    $params = [
        'clan'   => $_GET['clan']   ?? '',
        'sort'   => $_GET['sort']   ?? 'total',
        'offset' => 0,
    ];
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 0));
    return 'zonautes.php' . ($qs ? '?' . $qs : '');
}
?>

<!-- ===================== HERO ===================== -->
<section class="zonautes-hero">
  <div class="zonautes-hero-inner">
    <p class="overline-label">Communauté Zone85</p>
    <h1>Les Zonautes</h1>
    <p class="hero-sub">Les aventuriers qui font vivre Zone85</p>
  </div>
</section>

<!-- ===================== FILTRES ===================== -->
<div class="zonautes-filters" role="navigation" aria-label="Filtres">
  <div class="filters-inner">
    <a href="<?= zonautes_url(['clan' => '']) ?>"
       class="filter-btn <?= $filter_clan === '' ? 'active' : '' ?>">
      Tous
    </a>
    <a href="<?= zonautes_url(['clan' => 'bocage']) ?>"
       class="filter-btn <?= $filter_clan === 'bocage' ? 'bocage-active' : '' ?>">
      &#127795; Bocage
    </a>
    <a href="<?= zonautes_url(['clan' => 'littoral']) ?>"
       class="filter-btn <?= $filter_clan === 'littoral' ? 'littoral-active' : '' ?>">
      &#9875; Littoral
    </a>
    <a href="<?= zonautes_url(['clan' => 'marais']) ?>"
       class="filter-btn <?= $filter_clan === 'marais' ? 'marais-active' : '' ?>">
      &#127807; Marais
    </a>
    <a href="<?= zonautes_url(['sort' => $sort_mode === 'season' ? 'total' : 'season']) ?>"
       class="filter-sort-toggle <?= $sort_mode === 'season' ? 'active' : '' ?>">
      <?= $sort_mode === 'season' ? '&#9733; Saison' : '&#9733; Total XP' ?>
    </a>
  </div>
</div>

<!-- ===================== GRILLE ===================== -->
<section class="zonautes-section">
  <?php if (empty($players)): ?>
  <p class="zonautes-empty">
    <span style="display:block;font-size:1.2rem;margin-bottom:12px">🗺️</span>
    <strong>Soyez parmi les premiers membres de l'aventure.</strong><br>
    <span style="font-size:.85rem;color:var(--text-muted)">Participez à une mission pour figurer dans ce classement.</span>
  </p>
  <?php else: ?>
  <div class="zonautes-grid" id="players-grid">
    <?php foreach ($players as $i => $p):
      $global_rank  = $rank_start + $i;
      $rank_cls     = match($global_rank) { 1 => ' rank-1', 2 => ' rank-2', 3 => ' rank-3', default => '' };
      $cs           = $p['clan_slug'] ?? '';
      $chip_cls     = $chip_map[$cs] ?? '';
      $clan_label   = $p['clan_name'] ?? '';
      $avatar_type  = $p['avatar_type'] ?? 'preset';
      $avatar_emoji = '&#128100;';
      if ($avatar_type === 'preset') {
          $cfg = $p['avatar_config'] ?? '';
          if ($cfg) {
              $cfg_arr = json_decode($cfg, true);
              if (!empty($cfg_arr['emoji'])) $avatar_emoji = htmlspecialchars($cfg_arr['emoji'], ENT_QUOTES, 'UTF-8');
          }
      }
      $level     = max(1, (int)($p['level'] ?? get_user_level_from_xp((int)$p['xp_total'])));
      $xp_show   = $sort_mode === 'season' ? (int)$p['xp_season'] : (int)$p['xp_total'];
      $xp_suffix = $sort_mode === 'season' ? ' XP saison' : ' XP';
    ?>
    <div
      class="player-card"
      data-user-id="<?= (int)$p['id'] ?>"
      onclick="openPassport(<?= (int)$p['id'] ?>)"
      tabindex="0"
      role="button"
      aria-label="Ouvrir le passeport de <?= e($p['pseudo']) ?>"
      onkeydown="if(event.key==='Enter'||event.key===' ')openPassport(<?= (int)$p['id'] ?>)"
    >
      <span class="pc-rank-badge<?= $rank_cls ?>"><?= $global_rank ?></span>
      <div class="pc-avatar">
        <?php if ($avatar_type === 'upload' && !empty($p['avatar_file'])): ?>
          <img src="<?= upload_url(e($p['avatar_file'])) ?>" alt="<?= e($p['pseudo']) ?>">
        <?php else: ?>
          <?= $avatar_emoji ?>
        <?php endif; ?>
      </div>
      <div class="pc-pseudo"><?= e($p['pseudo']) ?></div>
      <?php if ($clan_label): ?>
      <div class="pc-clan-chip <?= e($chip_cls) ?>"><?= e($clan_label) ?></div>
      <?php endif; ?>
      <div class="pc-level-badge">Niv. <?= $level ?></div>
      <div class="pc-xp">
        <?= number_format($xp_show, 0, ',', ' ') ?><small><?= e($xp_suffix) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (($offset + $per_page) < $total_count): ?>
  <div class="voir-plus-wrap">
    <a
      href="<?= zonautes_url(['offset' => $offset + $per_page]) ?>"
      class="btn btn-primary"
    >
      Voir plus &mdash; <?= max(0, $total_count - $offset - $per_page) ?> restants
    </a>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<!-- ===================== PASSEPORT VENDÉEN ===================== -->
<div
  class="passport-overlay"
  id="passport-overlay"
  role="dialog"
  aria-modal="true"
  aria-label="Passeport Vendéen"
  onclick="if(event.target===this)closePassport()"
>
  <div class="passport-modal" id="passport-modal">
    <div class="pp-loading" id="pp-loading">
      <span class="spinner"></span><br>
      Chargement du passeport...
    </div>
    <div id="pp-content" style="display:none;"></div>
  </div>
</div>

<?php
$page_scripts = '<script>
var _ppCache = {};

function openPassport(userId) {
  var overlay = document.getElementById("passport-overlay");
  var loading = document.getElementById("pp-loading");
  var content = document.getElementById("pp-content");
  overlay.classList.add("open");
  document.body.style.overflow = "hidden";

  if (_ppCache[userId]) {
    loading.style.display = "none";
    content.style.display = "block";
    content.innerHTML = _ppCache[userId];
    return;
  }

  loading.style.display = "block";
  content.style.display = "none";
  content.innerHTML = "";

  var xhr = new XMLHttpRequest();
  xhr.open("GET", "ajax/passport.php?user_id=" + encodeURIComponent(userId), true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
  xhr.onreadystatechange = function() {
    if (xhr.readyState !== 4) return;
    loading.style.display = "none";
    content.style.display = "block";
    if (xhr.status === 200) {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.ok) {
          var html = renderPassport(data);
          _ppCache[userId] = html;
          content.innerHTML = html;
        } else {
          content.innerHTML = "<div style=\"padding:32px;text-align:center;color:#ef4444;\">Passeport introuvable.</div>";
        }
      } catch(e) {
        content.innerHTML = "<div style=\"padding:32px;text-align:center;color:#ef4444;\">Erreur de chargement.</div>";
      }
    } else {
      content.innerHTML = "<div style=\"padding:32px;text-align:center;color:#ef4444;\">Erreur serveur.</div>";
    }
  };
  xhr.send();
}

function closePassport() {
  document.getElementById("passport-overlay").classList.remove("open");
  document.body.style.overflow = "";
}

document.addEventListener("keydown", function(e) {
  if (e.key === "Escape") closePassport();
});

function renderPassport(d) {
  var clanChip = d.clan_slug
    ? "<span class=\"pc-clan-chip " + d.clan_slug + "-chip-sm\">" + esc(d.clan_name) + "</span>"
    : "<span style=\"font-size:.82rem;color:rgba(255,255,255,.4);\">Sans clan</span>";

  var avatarHtml = "";
  if (d.avatar_type === "upload" && d.avatar_url) {
    avatarHtml = "<img src=\"" + esc(d.avatar_url) + "\" alt=\"" + esc(d.pseudo) + "\">";
  } else {
    avatarHtml = d.avatar_emoji || "&#128100;";
  }

  var badgesHtml = "";
  if (d.badges && d.badges.length > 0) {
    badgesHtml = "<div class=\"pp-badges-row\">";
    for (var i = 0; i < d.badges.length; i++) {
      var b = d.badges[i];
      badgesHtml += "<span class=\"pp-badge-chip rarity-" + esc(b.rarity) + "\">"
                  + esc(b.icon) + " " + esc(b.title) + "</span>";
    }
    badgesHtml += "</div>";
  } else {
    badgesHtml = "<p class=\"pp-no-badges\">Aucun badge encore &mdash; les aventures commencent.</p>";
  }

  return "<div class=\"pp-header\">"
    + "<button class=\"pp-close\" onclick=\"closePassport()\" aria-label=\"Fermer\">&times;</button>"
    + "<div class=\"pp-avatar\">" + avatarHtml + "</div>"
    + "<div class=\"pp-info\">"
    + "<div class=\"pp-logo\">Zone85</div>"
    + "<div class=\"pp-id-label\">Passeport Vend&#233;en</div>"
    + "<div class=\"pp-pseudo\">" + esc(d.pseudo) + "</div>"
    + "<div class=\"pp-clan-line\">" + clanChip + "</div>"
    + "</div>"
    + "</div>"
    + "<div class=\"pp-body\">"
    + "<div class=\"pp-level-line\">"
    + "<span class=\"pp-level-badge\">Niv. " + d.level + "</span>"
    + "<span class=\"pp-level-name\">" + esc(d.level_name) + "</span>"
    + "</div>"
    + "<p class=\"pp-member-since\">Membre depuis&nbsp;: " + esc(d.joined) + "</p>"
    + "<div class=\"pp-stats-grid\">"
    + "<div class=\"pp-stat\"><span class=\"pp-stat-val\">" + number(d.xp_total) + " <small style=\"font-size:.65rem;font-weight:600;color:#9ca3af;\">XP</small></span><span class=\"pp-stat-lbl\">XP Total</span></div>"
    + "<div class=\"pp-stat\"><span class=\"pp-stat-val\">" + d.badges_count + "</span><span class=\"pp-stat-lbl\">Badges</span></div>"
    + "<div class=\"pp-stat\"><span class=\"pp-stat-val\">" + d.participations + "</span><span class=\"pp-stat-lbl\">Participations</span></div>"
    + "<div class=\"pp-stat\"><span class=\"pp-stat-val\">" + d.collectibles + "</span><span class=\"pp-stat-lbl\">Objets trouv&#233;s</span></div>"
    + "</div>"
    + "<p class=\"pp-badges-title\">Derniers badges</p>"
    + badgesHtml
    + "</div>";
}

function esc(str) {
  if (!str) return "";
  return String(str)
    .replace(/&/g,"&amp;")
    .replace(/</g,"&lt;")
    .replace(/>/g,"&gt;")
    .replace(/"/g,"&quot;");
}

function number(n) {
  return parseInt(n||0).toLocaleString("fr-FR");
}
</script>';

render_hidden_collectibles('zonautes');
require_once 'includes/footer.php';
?>
