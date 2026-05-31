<?php
$page_title       = 'Trophéothèque — Zone85';
$page_description = 'Découvre l\'histoire des saisons Zone85 et les clans qui ont remporté les trophées. La Trophéothèque, mémoire de la Bataille des Clans.';
$page_canonical   = 'https://www.zone85.fr/trophees.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'trophees';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// ── Chargement des trophées ───────────────────────────────────
$trophies    = [];
$past_seasons = [];

if (db_enabled()) {
    $pdo = db();

    // Trophées décernés
    try {
        $stmt = $pdo->prepare("
            SELECT s.title, s.emoji, s.color_primary, s.start_date, s.end_date,
                   c.name AS winner_name, c.slug AS winner_slug,
                   st.awarded_at
            FROM season_trophies st
            JOIN seasons s ON s.id = st.season_id
            JOIN clans c   ON c.id = st.winning_clan_id
            ORDER BY st.awarded_at DESC
        ");
        $stmt->execute();
        $trophies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table absente ou erreur silencieuse
        $trophies = [];
    }

    // Saisons passées / actives
    try {
        $stmt2 = $pdo->prepare("
            SELECT id, title, emoji, color_primary, start_date, end_date, status
            FROM seasons
            WHERE status IN ('archived', 'active')
            ORDER BY id DESC
        ");
        $stmt2->execute();
        $past_seasons = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $past_seasons = [];
    }
}

// ── Styles ───────────────────────────────────────────────────
$page_styles = '<style>
/* HERO */
.trophees-hero {
  background:
    radial-gradient(circle at 80% 20%, rgba(201,150,42,.22), transparent 30%),
    radial-gradient(circle at 15% 10%, rgba(255,255,255,.06), transparent 22%),
    linear-gradient(160deg, #071622 0%, #12314e 58%, #1a3a20 100%);
  padding: 120px 0 72px;
  position: relative;
  overflow: hidden;
}
.trophees-hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}
.trophees-hero::after {
  content: "";
  position: absolute;
  right: -60px;
  bottom: -120px;
  width: 320px;
  height: 320px;
  border-radius: 999px;
  background: rgba(201,150,42,.15);
  pointer-events: none;
}
.trophees-hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 32px;
  flex-wrap: wrap;
}
.trophees-hero h1 {
  font-size: clamp(1.9rem, 4.5vw, 3rem);
  font-weight: 900;
  color: #fff;
  letter-spacing: -1px;
  line-height: 1.1;
  margin-bottom: 12px;
}
.trophees-hero p {
  font-size: .95rem;
  color: rgba(255,255,255,.55);
  max-width: 460px;
}
.hero-trophy-icon {
  font-size: 6rem;
  opacity: .18;
  line-height: 1;
  user-select: none;
  flex-shrink: 0;
}

/* SECTION */
.trophees-section {
  background: #f8f4ef;
  padding: 64px 0 96px;
}
.section-title {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 28px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(18,49,78,.1);
}

/* GRILLE TROPHÉES */
.trophees-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 24px;
  margin-bottom: 64px;
}
.trophy-card {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0,0,0,.10);
  background: #fff;
  transition: transform .22s ease, box-shadow .22s ease;
}
.trophy-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 36px rgba(0,0,0,.16);
}
.trophy-card-header {
  padding: 32px 24px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
  position: relative;
}
.trophy-emoji {
  font-size: 3.2rem;
  line-height: 1;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,.25));
}
.trophy-icon-big {
  font-size: 1.6rem;
  margin-top: 4px;
}
.trophy-season-name {
  font-size: 1rem;
  font-weight: 900;
  color: #fff;
  margin-top: 4px;
  text-shadow: 0 1px 4px rgba(0,0,0,.3);
}
.trophy-dates {
  font-size: .72rem;
  font-weight: 600;
  color: rgba(255,255,255,.55);
  letter-spacing: .04em;
}
.trophy-card-body {
  padding: 18px 24px 22px;
  border-top: 1px solid rgba(18,49,78,.07);
}
.trophy-winner-label {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--text-muted);
  margin-bottom: 8px;
}
.trophy-winner-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 999px;
  font-size: .84rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 12px;
}
.trophy-awarded {
  font-size: .72rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* EMPTY STATE */
.empty-state {
  text-align: center;
  padding: 64px 24px;
  background: #fff;
  border-radius: 16px;
  border: 1px dashed rgba(18,49,78,.15);
  margin-bottom: 64px;
}
.empty-state-icon {
  font-size: 3.5rem;
  margin-bottom: 16px;
}
.empty-state h3 {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--navy-dark);
  margin-bottom: 8px;
}
.empty-state p {
  font-size: .88rem;
  color: var(--text-muted);
  max-width: 380px;
  margin: 0 auto;
}

/* SAISONS PASSÉES */
.seasons-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.season-row {
  display: flex;
  align-items: center;
  gap: 16px;
  background: #fff;
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 1px 8px rgba(0,0,0,.06);
  border: 1px solid rgba(18,49,78,.06);
  text-decoration: none;
  color: inherit;
  transition: box-shadow .18s;
}
.season-row:hover {
  box-shadow: 0 4px 18px rgba(0,0,0,.12);
}
.season-row-emoji {
  font-size: 1.6rem;
  flex-shrink: 0;
  width: 44px;
  text-align: center;
}
.season-row-info {
  flex: 1;
}
.season-row-title {
  font-size: .92rem;
  font-weight: 800;
  color: var(--navy-dark);
  margin-bottom: 3px;
}
.season-row-dates {
  font-size: .72rem;
  color: var(--text-muted);
  font-weight: 500;
}
.season-status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: .65rem;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
}
.badge-active {
  background: rgba(46,160,67,.15);
  color: #1a7a30;
}
.badge-closed {
  background: rgba(18,49,78,.08);
  color: var(--text-muted);
}

@media (max-width: 768px) {
  .trophees-grid { grid-template-columns: 1fr; }
  .hero-trophy-icon { display: none; }
  .trophees-hero { padding: 100px 0 56px; }
}
</style>';

// ── Helpers locaux ────────────────────────────────────────────
function _fmt_date_fr(string $date): string {
    $ts = strtotime($date);
    if (!$ts) return '';
    return date('d/m/Y', $ts);
}

function _season_gradient(string $color): string {
    // Dérive un gradient sombre à partir de la couleur primaire
    return 'linear-gradient(135deg, ' . $color . 'cc 0%, ' . $color . ' 100%)';
}

function _clan_chip_color(string $slug): string {
    $map = [
        'bocage'   => '#1e6b3f',
        'littoral' => '#1a4a7a',
        'marais'   => '#7a4a1a',
    ];
    return $map[$slug] ?? '#333';
}

require_once 'includes/repositories.php';

// Active season pour footer
try {
    $active_season = (db_enabled() && function_exists('fetch_active_season')) ? fetch_active_season() : null;
} catch (Exception $e) {
    $active_season = null;
}

require 'includes/header.php';
require 'includes/nav.php';
?>

<!-- HERO -->
<section class="trophees-hero">
  <div class="container">
    <div class="trophees-hero-inner">
      <div>
        <h1>ðŸ† Trophéothèque Zone85</h1>
        <p>L'histoire des saisons — chaque trophée, un clan vainqueur, une époque gravée dans la mémoire de la Zone.</p>
      </div>
      <div class="hero-trophy-icon" aria-hidden="true">ðŸ†</div>
    </div>
  </div>
</section>

<!-- CONTENU -->
<section class="trophees-section">
  <div class="container">

    <!-- TROPHÉES -->
    <div class="section-title">Palmarès des saisons</div>

    <?php if (!empty($trophies)): ?>
    <div class="trophees-grid">
      <?php foreach ($trophies as $t):
        $bg_color = !empty($t['color_primary']) ? $t['color_primary'] : '#12314e';
        $chip_color = _clan_chip_color($t['winner_slug'] ?? '');
      ?>
      <div class="trophy-card">
        <div class="trophy-card-header" style="background: <?= _season_gradient($bg_color) ?>">
          <div class="trophy-emoji"><?= e($t['emoji'] ?? 'ðŸ†') ?></div>
          <div class="trophy-icon-big">ðŸ†</div>
          <div class="trophy-season-name"><?= e($t['title']) ?></div>
          <?php if (!empty($t['start_date']) && !empty($t['end_date'])): ?>
          <div class="trophy-dates">
            <?= _fmt_date_fr($t['start_date']) ?> → <?= _fmt_date_fr($t['end_date']) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="trophy-card-body">
          <div class="trophy-winner-label">Clan vainqueur</div>
          <div>
            <span class="trophy-winner-chip" style="background: <?= htmlspecialchars($chip_color, ENT_QUOTES) ?>">
              🥇 <?= e($t['winner_name']) ?>
            </span>
          </div>
          <?php if (!empty($t['awarded_at'])): ?>
          <div class="trophy-awarded">Décerné le <?= _fmt_date_fr($t['awarded_at']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">👑</div>
      <h3>Les premiers trophées seront bientôt remportés.</h3>
      <p>La saison est en cours. Aidez votre clan à prendre la tête — le clan vainqueur recevra son trophée à la clôture de la saison.</p>
    </div>
    <?php endif; ?>

    <!-- SAISONS PASSÉES -->
    <?php if (!empty($past_seasons)): ?>
    <div class="section-title">Saisons passées &amp; en cours</div>
    <div class="seasons-list">
      <?php foreach ($past_seasons as $s): ?>
      <div class="season-row">
        <div class="season-row-emoji"><?= e($s['emoji'] ?? '📅') ?></div>
        <div class="season-row-info">
          <div class="season-row-title"><?= e($s['title']) ?></div>
          <?php if (!empty($s['start_date'])): ?>
          <div class="season-row-dates">
            <?= _fmt_date_fr($s['start_date']) ?>
            <?= !empty($s['end_date']) ? ' → ' . _fmt_date_fr($s['end_date']) : '' ?>
          </div>
          <?php endif; ?>
        </div>
        <span class="season-status-badge <?= $s['status'] === 'active' ? 'badge-active' : 'badge-closed' ?>">
          <?= $s['status'] === 'active' ? 'â— En cours' : 'Terminée' ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php require 'includes/footer.php'; ?>




