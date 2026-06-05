<?php
$page_title       = 'Communauté — Zone85';
$page_description = 'Le fil de la Zone : toutes les actions de la communauté Zonautes en temps réel. Badges, missions, randos, événements.';
$page_canonical   = 'https://www.zone85.fr/communaute.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'communaute';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

$is_logged   = is_logged_in();
$active_user = $is_logged ? current_user() : null;

$page_num   = max(1, (int)($_GET['p'] ?? 1));
$per_page   = 30;
$feed_items = fetch_community_feed($page_num, $per_page);

// Total pour pagination
$total_items = 0;
$pdo = db();
if ($pdo) {
    try {
        $total_items = (int)$pdo->query("SELECT COUNT(*) FROM community_feed")->fetchColumn();
    } catch (PDOException $e) {}
}
$total_pages = max(1, (int)ceil($total_items / $per_page));

$chip_map = [
    'bocage'   => ['bocage-chip',   '🌳 Bocage'],
    'littoral' => ['littoral-chip', '⚓ Littoral'],
    'marais'   => ['marais-chip',   '🌿 Marais'],
];

$event_labels = [
    'mission_new'      => ['🎯', 'Nouvelle mission'],
    'mission_complete' => ['✅', 'Mission accomplie'],
    'badge_unlock'     => ['🏅', 'Badge débloqué'],
    'flash_start'      => ['⚡', 'Flash lancé'],
    'flash_end'        => ['🏁', 'Flash terminé'],
    'season_start'     => ['🗓', 'Saison lancée'],
    'season_end'       => ['🏆', 'Saison terminée'],
    'clan_lead'        => ['🛡', 'Changement de tête'],
    'trophy_awarded'   => ['🥇', 'Trophée attribué'],
    'collectible_found'=> ['🔍', 'Collectible trouvé'],
    'rando_done'       => ['🥾', 'Rando terminée'],
    'ktc_win'          => ['🥐', 'KTC — victoire'],
];

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<style>
/* ── Hero ── */
.cf-hero{background:linear-gradient(135deg,#0c1e2e 0%,#163756 60%,#12314e 100%);padding:72px 0 48px;text-align:center;position:relative;overflow:hidden}
.cf-hero::before{content:'📋';position:absolute;top:-20px;right:8%;font-size:12rem;opacity:.04;pointer-events:none}
.cf-hero-label{font-size:.68rem;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:var(--primary);display:block;margin-bottom:12px}
.cf-hero-title{font-size:clamp(1.9rem,4.5vw,3rem);font-weight:900;color:#fff;letter-spacing:-.03em;line-height:1.1;margin-bottom:14px}
.cf-hero-sub{font-size:.95rem;color:rgba(255,255,255,.6);max-width:460px;margin:0 auto;line-height:1.6}

/* ── Feed ── */
.cf-section{padding:48px 0 64px;background:var(--beige)}
.cf-feed{max-width:640px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
.cf-item{background:#fff;border-radius:var(--radius);border:1.5px solid var(--beige-dark);padding:16px 20px;display:flex;align-items:flex-start;gap:14px;transition:box-shadow .15s}
.cf-item:hover{box-shadow:var(--shadow-sm)}
.cf-item.pinned{border-color:var(--primary);background:#fffcfb}
.cf-icon{font-size:1.7rem;flex-shrink:0;line-height:1;margin-top:2px}
.cf-body{flex:1;min-width:0}
.cf-item-title{font-size:.93rem;font-weight:700;color:var(--navy-dark);margin-bottom:3px;line-height:1.4}
.cf-item-body{font-size:.83rem;color:var(--text-muted);line-height:1.5;margin-bottom:5px}
.cf-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.72rem;color:var(--text-muted)}
.cf-user{font-weight:700;color:var(--navy-dark)}
.cf-type-badge{display:inline-block;background:var(--beige);border:1px solid var(--beige-dark);border-radius:10px;padding:1px 7px;font-size:.67rem;font-weight:700;color:var(--text-mid)}
.cf-pinned-badge{display:inline-block;background:rgba(234,86,73,.1);color:var(--primary);border:1px solid rgba(234,86,73,.25);border-radius:10px;padding:1px 7px;font-size:.67rem;font-weight:800}
.cf-pagination{display:flex;gap:8px;justify-content:center;margin-top:32px;flex-wrap:wrap}
.cf-page-btn{padding:7px 14px;border-radius:6px;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid var(--beige-dark);color:var(--text-mid);background:#fff;transition:all .15s}
.cf-page-btn:hover{border-color:var(--primary);color:var(--primary)}
.cf-page-btn.active{background:var(--primary);border-color:var(--primary);color:#fff}
.cf-empty{text-align:center;padding:56px 24px;background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark)}
</style>

<!-- ── HERO ── -->
<section class="cf-hero">
  <div class="container">
    <span class="cf-hero-label">Zone85</span>
    <h1 class="cf-hero-title">Communauté 🌍</h1>
    <p class="cf-hero-sub">Le fil de la Zone — toutes les actions de la communauté en temps réel.</p>
  </div>
</section>

<!-- ── FEED ── -->
<section class="cf-section">
  <div class="container">
    <?php if (empty($feed_items)): ?>
    <div class="cf-empty">
      <div style="font-size:3rem;margin-bottom:12px">📭</div>
      <p style="font-weight:700;color:var(--navy-dark);margin-bottom:8px">Fil vide pour l'instant</p>
      <p style="font-size:.88rem;color:var(--text-muted)">Les actions de la communauté apparaîtront ici au fil du temps.</p>
    </div>
    <?php else: ?>
    <div class="cf-feed">
      <?php foreach ($feed_items as $item):
        [$evt_icon, $evt_label] = $event_labels[$item['event_type']] ?? ['📋', $item['event_type']];
        $chip = $chip_map[$item['clan_slug'] ?? ''] ?? null;
        $is_pinned = (int)$item['is_pinned'] === 1;
      ?>
      <div class="cf-item <?= $is_pinned ? 'pinned' : '' ?>">
        <div class="cf-icon"><?= $item['icon_emoji'] ? e($item['icon_emoji']) : $evt_icon ?></div>
        <div class="cf-body">
          <div class="cf-item-title"><?= e($item['title']) ?></div>
          <?php if ($item['body']): ?>
            <div class="cf-item-body"><?= e($item['body']) ?></div>
          <?php endif; ?>
          <div class="cf-meta">
            <?php if ($item['pseudo']): ?>
              <span class="cf-user"><?= e($item['pseudo']) ?></span>
            <?php endif; ?>
            <?php if ($chip): ?>
              <span class="<?= $chip[0] ?>"><?= $chip[1] ?></span>
            <?php endif; ?>
            <span class="cf-type-badge"><?= $evt_label ?></span>
            <?php if ($is_pinned): ?>
              <span class="cf-pinned-badge">📌 Épinglé</span>
            <?php endif; ?>
            <span>· <?= format_date($item['created_at'], 'long') ?></span>
            <?php if ($item['link_url']): ?>
              <a href="<?= e($item['link_url']) ?>" style="color:var(--primary);font-weight:700;text-decoration:none;font-size:.72rem">Voir →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="cf-pagination">
      <?php if ($page_num > 1): ?>
        <a href="communaute.php?p=<?= $page_num - 1 ?>" class="cf-page-btn">← Précédent</a>
      <?php endif; ?>
      <?php
      $start = max(1, $page_num - 2);
      $end   = min($total_pages, $page_num + 2);
      for ($pg = $start; $pg <= $end; $pg++):
      ?>
        <a href="communaute.php?p=<?= $pg ?>" class="cf-page-btn <?= $pg === $page_num ? 'active' : '' ?>"><?= $pg ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?>
        <a href="communaute.php?p=<?= $page_num + 1 ?>" class="cf-page-btn">Suivant →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<?php
render_hidden_collectibles('communaute');
require_once 'includes/footer.php';
?>
