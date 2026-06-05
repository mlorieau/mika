<?php
$page_title       = 'Événements — Zone85';
$page_description = 'Événements flash et rendez-vous spéciaux de la Zone85. Participe aux bonus du moment et multiplie tes XP.';
$page_canonical   = 'https://www.zone85.fr/evenements.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'evenements';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

$pdo         = db();
$is_logged   = is_logged_in();
$active_user = $is_logged ? current_user() : null;

// ── Événements actifs (flash en cours) ──────────────────────
$events_active = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, title, slug, mission_type, description, cover_emoji,
                   xp_participation, xp_success, xp_multiplier,
                   flash_start_at, flash_end_at, badge_reward_id
            FROM missions
            WHERE is_flash = 1 AND status = 'active'
              AND (flash_end_at IS NULL OR flash_end_at > NOW())
            ORDER BY flash_start_at DESC
        ");
        $events_active = $stmt->fetchAll();
    } catch (PDOException $e) { error_log('[evenements] ' . $e->getMessage()); }
}

// ── Événements à venir ───────────────────────────────────────
$events_upcoming = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, title, slug, cover_emoji, xp_participation, xp_multiplier,
                   flash_start_at, flash_end_at
            FROM missions
            WHERE is_flash = 1 AND status IN ('draft','active')
              AND flash_start_at > NOW()
            ORDER BY flash_start_at ASC
            LIMIT 4
        ");
        $events_upcoming = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// ── Événements passés ────────────────────────────────────────
$events_past = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, title, slug, cover_emoji, xp_participation,
                   flash_start_at, flash_end_at
            FROM missions
            WHERE is_flash = 1 AND (status IN ('closed','archived') OR flash_end_at < NOW())
            ORDER BY flash_end_at DESC
            LIMIT 6
        ");
        $events_past = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// ── Participations de l'utilisateur ─────────────────────────
$my_participations = [];
if ($is_logged && $pdo && !empty($events_active)) {
    try {
        $ids  = implode(',', array_map(fn($e) => (int)$e['id'], $events_active));
        $rows = $pdo->prepare("SELECT mission_id FROM participations WHERE user_id=:uid AND mission_id IN ({$ids})");
        $rows->execute([':uid' => (int)$active_user['id']]);
        foreach ($rows->fetchAll() as $r) $my_participations[$r['mission_id']] = true;
    } catch (PDOException $e) {}
}

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<style>
/* .ev-hero — fond/padding depuis zone85.css */
.ev-hero{text-align:center}
.ev-hero-label{font-size:.68rem;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:var(--primary);display:block;margin-bottom:12px}
.ev-hero-title{font-size:clamp(2rem,5vw,3.2rem);font-weight:900;color:#fff;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px}
.ev-hero-sub{font-size:1rem;color:rgba(255,255,255,.6);max-width:480px;margin:0 auto 32px;line-height:1.6}
.ev-hero-stats{display:flex;justify-content:center;gap:32px;flex-wrap:wrap}
.ev-stat{text-align:center}
.ev-stat-num{font-size:2rem;font-weight:900;color:var(--primary);line-height:1}
.ev-stat-label{font-size:.72rem;font-weight:600;color:rgba(255,255,255,.5);letter-spacing:.08em;text-transform:uppercase;margin-top:4px}

/* ── Sections ── */
.ev-section{padding:56px 0}
.ev-section-title{font-size:1.4rem;font-weight:900;color:var(--navy-dark);margin-bottom:8px}
.ev-section-sub{font-size:.9rem;color:var(--text-muted);margin-bottom:32px}

/* ── Card événement ── */
.ev-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}
.ev-card{background:#fff;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm);border:1.5px solid var(--beige-dark);transition:transform .2s,box-shadow .2s;position:relative}
.ev-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md)}
.ev-card.active{border-color:var(--primary)}
.ev-card-header{padding:28px 24px 20px;background:linear-gradient(135deg,#0c1e2e,#163756)}
.ev-card-emoji{font-size:2.8rem;display:block;margin-bottom:12px;line-height:1}
.ev-card-title{font-size:1.1rem;font-weight:900;color:#fff;margin-bottom:6px;line-height:1.3}
.ev-card-body{padding:20px 24px}
.ev-card-desc{font-size:.88rem;color:var(--text-muted);line-height:1.6;margin-bottom:16px}
.ev-xp-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(234,86,73,.08);color:var(--primary);border:1px solid rgba(234,86,73,.25);border-radius:20px;padding:4px 12px;font-size:.78rem;font-weight:800}
.ev-multiplier{display:inline-flex;align-items:center;gap:6px;background:rgba(212,175,55,.1);color:#b8860b;border:1px solid rgba(212,175,55,.3);border-radius:20px;padding:4px 12px;font-size:.78rem;font-weight:800;margin-left:8px}
.ev-card-footer{padding:0 24px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.ev-countdown{font-size:.78rem;color:var(--text-muted);font-weight:600}
.ev-countdown span{color:var(--primary);font-weight:900}
.ev-btn{padding:10px 20px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-weight:800;font-size:.85rem;text-decoration:none;cursor:pointer;transition:all .2s;display:inline-block}
.ev-btn:hover{background:var(--primary-dark)}
.ev-btn-done{padding:10px 20px;background:rgba(42,157,92,.1);color:#1a7a46;border:1.5px solid rgba(42,157,92,.3);border-radius:var(--radius);font-weight:700;font-size:.85rem;pointer-events:none;display:inline-block}
.ev-flash-badge{position:absolute;top:16px;right:16px;background:var(--primary);color:#fff;font-size:.64rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase;padding:4px 10px;border-radius:20px}

/* ── Upcoming / Past ── */
.ev-mini-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.ev-mini-card{background:#fff;border-radius:var(--radius);padding:16px 20px;border:1.5px solid var(--beige-dark);display:flex;align-items:center;gap:14px}
.ev-mini-emoji{font-size:1.8rem;flex-shrink:0}
.ev-mini-title{font-size:.9rem;font-weight:700;color:var(--navy-dark);margin-bottom:4px}
.ev-mini-date{font-size:.75rem;color:var(--text-muted)}
.ev-empty{text-align:center;padding:48px 24px;color:var(--text-muted)}
.ev-empty-icon{font-size:3rem;margin-bottom:12px}
.ev-empty-title{font-size:1.1rem;font-weight:700;color:var(--navy-dark);margin-bottom:8px}
.ev-empty-text{font-size:.9rem;line-height:1.5}
.ev-past-card{opacity:.65}
.ev-past-card:hover{opacity:1}
@media(max-width:640px){.ev-grid{grid-template-columns:1fr}.ev-hero-stats{gap:20px}}
</style>

<!-- ── HERO ── -->
<section class="ev-hero">
  <div class="container">
    <span class="ev-hero-label">Zone85</span>
    <h1 class="ev-hero-title">Événements ⚡</h1>
    <p class="ev-hero-sub">Les moments forts de la Zone. Participe aux événements flash et multiplie tes XP.</p>
    <div class="ev-hero-stats">
      <div class="ev-stat">
        <div class="ev-stat-num"><?= count($events_active) ?></div>
        <div class="ev-stat-label">En cours</div>
      </div>
      <div class="ev-stat">
        <div class="ev-stat-num"><?= count($events_upcoming) ?></div>
        <div class="ev-stat-label">À venir</div>
      </div>
      <div class="ev-stat">
        <div class="ev-stat-num"><?= count($events_past) ?></div>
        <div class="ev-stat-label">Passés</div>
      </div>
    </div>
  </div>
</section>

<!-- ── ÉVÉNEMENTS ACTIFS ── -->
<section class="ev-section" style="background:var(--beige)">
  <div class="container">
    <h2 class="ev-section-title">En ce moment</h2>
    <p class="ev-section-sub">Ces événements sont actifs maintenant — ne rate pas la fenêtre !</p>

    <?php if (empty($events_active)): ?>
    <div class="ev-empty">
      <div class="ev-empty-icon">🔭</div>
      <p class="ev-empty-title">Aucun événement en cours</p>
      <p class="ev-empty-text">Les prochains événements flash arrivent bientôt.<br>Surveille cette page et les annonces du clan.</p>
    </div>
    <?php else: ?>
    <div class="ev-grid">
      <?php foreach ($events_active as $ev):
        $end_ts     = $ev['flash_end_at'] ? strtotime($ev['flash_end_at']) : 0;
        $has_done   = isset($my_participations[$ev['id']]);
        $xp_total   = (int)$ev['xp_participation'] + (int)$ev['xp_success'];
        $multiplier = (float)($ev['xp_multiplier'] ?? 1.0);
      ?>
      <div class="ev-card active">
        <span class="ev-flash-badge">⚡ Flash</span>
        <div class="ev-card-header">
          <span class="ev-card-emoji"><?= e($ev['cover_emoji'] ?: '⚡') ?></span>
          <div class="ev-card-title"><?= e($ev['title']) ?></div>
        </div>
        <div class="ev-card-body">
          <p class="ev-card-desc"><?= e(mb_substr($ev['description'] ?? '', 0, 160)) ?></p>
          <div>
            <?php if ($xp_total): ?>
              <span class="ev-xp-badge">+<?= $xp_total ?> XP</span>
            <?php endif; ?>
            <?php if ($multiplier > 1): ?>
              <span class="ev-multiplier">×<?= number_format($multiplier, 1) ?> multiplicateur</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="ev-card-footer">
          <?php if ($end_ts): ?>
            <span class="ev-countdown">Fin dans <span class="cd-time" data-end="<?= $end_ts ?>">…</span></span>
          <?php else: ?>
            <span class="ev-countdown">Durée illimitée</span>
          <?php endif; ?>

          <?php if ($has_done): ?>
            <span class="ev-btn-done">✓ Participé</span>
          <?php elseif ($is_logged): ?>
            <a href="missions.php" class="ev-btn">Participer →</a>
          <?php else: ?>
            <a href="login.php?redirect=evenements.php" class="ev-btn">Se connecter →</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── À VENIR ── -->
<?php if (!empty($events_upcoming)): ?>
<section class="ev-section">
  <div class="container">
    <h2 class="ev-section-title">À venir</h2>
    <p class="ev-section-sub">Ces événements arrivent prochainement — note les dates !</p>
    <div class="ev-mini-grid">
      <?php foreach ($events_upcoming as $ev): ?>
      <div class="ev-mini-card">
        <span class="ev-mini-emoji"><?= e($ev['cover_emoji'] ?: '📅') ?></span>
        <div>
          <div class="ev-mini-title"><?= e($ev['title']) ?></div>
          <div class="ev-mini-date">
            <?php if ($ev['flash_start_at']): ?>
              <?= format_date($ev['flash_start_at'], 'long') ?>
            <?php else: ?>
              Bientôt
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── ÉVÉNEMENTS PASSÉS ── -->
<?php if (!empty($events_past)): ?>
<section class="ev-section" style="background:var(--beige)">
  <div class="container">
    <h2 class="ev-section-title">Événements passés</h2>
    <p class="ev-section-sub">L'histoire de la Zone — les défis que la communauté a relevés.</p>
    <div class="ev-mini-grid">
      <?php foreach ($events_past as $ev): ?>
      <div class="ev-mini-card ev-past-card">
        <span class="ev-mini-emoji" style="opacity:.5"><?= e($ev['cover_emoji'] ?: '📁') ?></span>
        <div>
          <div class="ev-mini-title" style="color:var(--text-muted)"><?= e($ev['title']) ?></div>
          <div class="ev-mini-date">
            <?= $ev['flash_end_at'] ? format_date($ev['flash_end_at'], 'long') : 'Terminé' ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
(function () {
  function pad(n) { return n < 10 ? '0' + n : n; }
  function tick() {
    document.querySelectorAll('.cd-time').forEach(function(el) {
      var end = parseInt(el.dataset.end, 10) * 1000;
      var diff = end - Date.now();
      if (diff <= 0) { el.textContent = 'terminé'; return; }
      var h = Math.floor(diff / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      el.textContent = h > 0 ? h + 'h ' + pad(m) + 'min' : pad(m) + 'min ' + pad(s) + 's';
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

<?php
render_hidden_collectibles('evenements');
require_once 'includes/footer.php';
?>
