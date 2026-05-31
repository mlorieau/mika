<?php
// ============================================================
// admin/dashboard.php — Dashboard enrichi V10
// KPIs, graphiques, activité récente
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current    = 'dashboard';
$admin_page_title = 'Dashboard V10';

$pdo = db();

$kpis = [
    'members_total'    => 0, 'members_new_7d'   => 0,
    'members_active_30d'=> 0,'missions_active'  => 0,
    'participations_total' => 0, 'participations_pending' => 0,
    'xp_total'         => 0, 'xp_7d'            => 0,
    'collectibles_found' => 0, 'pwa_installs'   => 0,
    'badges_unlocked'  => 0, 'clan_pts_season'  => 0,
];

$chart_signups  = []; // inscriptions 14 derniers jours
$chart_xp       = []; // XP distribués 14 derniers jours
$last_users     = [];
$last_actions   = [];

if ($pdo) {
    try {
        $kpis['members_total']      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
        $kpis['members_new_7d']     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
        $kpis['members_active_30d'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_login_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
        $kpis['missions_active']    = (int)$pdo->query("SELECT COUNT(*) FROM missions WHERE status='active'")->fetchColumn();
        $kpis['participations_total']  = (int)$pdo->query("SELECT COUNT(*) FROM participations")->fetchColumn();
        $kpis['participations_pending']= (int)$pdo->query("SELECT COUNT(*) FROM participations WHERE status='pending'")->fetchColumn();
        $kpis['xp_total']   = (int)$pdo->query("SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs")->fetchColumn();
        $kpis['xp_7d']      = (int)$pdo->query("SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
        $kpis['badges_unlocked'] = (int)$pdo->query("SELECT COUNT(*) FROM user_badges")->fetchColumn();

        // Collectibles (table peut ne pas exister en version antérieure)
        try { $kpis['collectibles_found'] = (int)$pdo->query("SELECT COUNT(*) FROM user_collectibles")->fetchColumn(); } catch(PDOException $e){}
        try { $kpis['pwa_installs'] = (int)$pdo->query("SELECT COUNT(*) FROM pwa_installs")->fetchColumn(); } catch(PDOException $e){}

        $sr = _active_season_row();
        if ($sr) {
            $s = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM clan_score_logs WHERE season_id=:sid");
            $s->execute([':sid' => (int)$sr['id']]);
            $kpis['clan_pts_season'] = (int)$s->fetchColumn();
        }

        // Graphique inscriptions (14 jours)
        $s = $pdo->query("
            SELECT DATE(created_at) AS d, COUNT(*) AS n
            FROM users WHERE created_at >= NOW() - INTERVAL 14 DAY
            GROUP BY DATE(created_at) ORDER BY d ASC
        ");
        $raw_signups = $s->fetchAll(PDO::FETCH_KEY_PAIR);
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} day"));
            $chart_signups[$d] = $raw_signups[$d] ?? 0;
        }

        // Graphique XP (14 jours)
        $s = $pdo->query("
            SELECT DATE(created_at) AS d, COALESCE(SUM(xp_amount),0) AS n
            FROM xp_logs WHERE created_at >= NOW() - INTERVAL 14 DAY
            GROUP BY DATE(created_at) ORDER BY d ASC
        ");
        $raw_xp = $s->fetchAll(PDO::FETCH_KEY_PAIR);
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} day"));
            $chart_xp[$d] = $raw_xp[$d] ?? 0;
        }

        // Derniers inscrits
        $s = $pdo->query("
            SELECT id, pseudo, email, clan_id, level, xp_total, created_at,
                   avatar_type, avatar_config, avatar_file
            FROM users WHERE deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 8
        ");
        $last_users = $s->fetchAll();

        // Dernières actions XP
        $s = $pdo->query("
            SELECT xl.source_type, xl.xp_amount, xl.created_at, u.pseudo
            FROM xp_logs xl JOIN users u ON u.id = xl.user_id
            ORDER BY xl.created_at DESC LIMIT 10
        ");
        $last_actions = $s->fetchAll();

    } catch (PDOException $e) {
        error_log('[admin/dashboard] ' . $e->getMessage());
    }
}

$csrf = csrf_token();
require_once '_admin-header.php';
?>

<style>
.dash-kpi-grid  { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:28px; }
.dash-kpi       { background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 10px rgba(12,30,46,.06);
                  border:1px solid rgba(18,49,78,.07); }
.dash-kpi-ico   { font-size:1.4rem;margin-bottom:8px; }
.dash-kpi-val   { font-size:2rem;font-weight:900;color:#0c1e2e;letter-spacing:-1px;line-height:1;margin-bottom:4px; }
.dash-kpi-val.red   { color:#ea5649; }
.dash-kpi-val.green { color:#2a9d5c; }
.dash-kpi-val.blue  { color:#12314e; }
.dash-kpi-val.gold  { color:#b8831a; }
.dash-kpi-lbl   { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7f96; }
.dash-kpi-sub   { font-size:.72rem;color:#6b7f96;margin-top:4px; }
.dash-grid-2    { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px; }
/* Mini bar chart */
.mini-chart { display:flex;align-items:flex-end;gap:4px;height:64px;margin-top:12px; }
.mini-bar   { flex:1;border-radius:3px 3px 0 0;min-height:2px;transition:height .3s; }
.mini-bar:hover { opacity:.75; }
@media(max-width:700px){ .dash-grid-2{grid-template-columns:1fr;} .dash-kpi-grid{grid-template-columns:repeat(2,1fr);} }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">Dashboard</h1>
    <p class="adm-page-sub">Vue d'ensemble en temps réel · <?= date('d/m/Y H:i') ?></p>
  </div>
  <div class="adm-page-actions">
    <a href="mission-edit.php" class="btn-adm btn-adm-primary">+ Nouvelle mission</a>
    <a href="users.php" class="btn-adm btn-adm-ghost">👥 Utilisateurs</a>
  </div>
</div>

<!-- KPIs -->
<div class="dash-kpi-grid">
  <div class="dash-kpi">
    <div class="dash-kpi-ico">👥</div>
    <div class="dash-kpi-val blue"><?= number_format($kpis['members_total'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Membres totaux</div>
    <?php if ($kpis['members_new_7d']): ?>
    <div class="dash-kpi-sub">+<?= $kpis['members_new_7d'] ?> cette semaine</div>
    <?php endif; ?>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">🔥</div>
    <div class="dash-kpi-val green"><?= number_format($kpis['members_active_30d'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Actifs (30 jours)</div>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">🎯</div>
    <div class="dash-kpi-val red"><?= $kpis['missions_active'] ?></div>
    <div class="dash-kpi-lbl">Missions actives</div>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">📋</div>
    <div class="dash-kpi-val <?= $kpis['participations_pending'] > 0 ? 'gold' : '' ?>"><?= number_format($kpis['participations_total'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Participations</div>
    <?php if ($kpis['participations_pending']): ?>
    <div class="dash-kpi-sub" style="color:#C9962A">⚠️ <?= $kpis['participations_pending'] ?> en attente</div>
    <?php endif; ?>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">⚡</div>
    <div class="dash-kpi-val"><?= number_format($kpis['xp_total'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">XP distribués (total)</div>
    <div class="dash-kpi-sub">+<?= number_format($kpis['xp_7d'],0,',',' ') ?> cette semaine</div>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">🏅</div>
    <div class="dash-kpi-val"><?= number_format($kpis['badges_unlocked'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Badges débloqués</div>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">🗝️</div>
    <div class="dash-kpi-val"><?= number_format($kpis['collectibles_found'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Objets trouvés</div>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">📱</div>
    <div class="dash-kpi-val"><?= number_format($kpis['pwa_installs'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Installations PWA</div>
  </div>
  <div class="dash-kpi">
    <div class="dash-kpi-ico">🏆</div>
    <div class="dash-kpi-val gold"><?= number_format($kpis['clan_pts_season'],0,',',' ') ?></div>
    <div class="dash-kpi-lbl">Pts clan (saison)</div>
  </div>
</div>

<!-- Graphiques mini -->
<div class="dash-grid-2">
  <!-- Inscriptions 14j -->
  <div class="adm-card">
    <div class="adm-card-title">📈 Inscriptions (14 jours)</div>
    <?php $max_s = max(array_values($chart_signups) ?: [1]); ?>
    <div class="mini-chart">
      <?php foreach ($chart_signups as $d => $n):
        $h = $max_s > 0 ? max(4, round($n / $max_s * 60)) : 4;
        $is_today = ($d === date('Y-m-d'));
      ?>
      <div class="mini-bar"
           style="height:<?= $h ?>px;background:<?= $is_today ? '#ea5649' : '#2a9d5c' ?>;flex:1"
           title="<?= date('d/m', strtotime($d)) ?> : <?= $n ?> inscription<?= $n>1?'s':'' ?>"></div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.68rem;color:#6b7f96;margin-top:6px">
      <span><?= date('d/m', strtotime('-13 day')) ?></span>
      <span style="color:#ea5649;font-weight:700">Aujourd'hui : <?= $chart_signups[date('Y-m-d')] ?? 0 ?></span>
      <span><?= date('d/m') ?></span>
    </div>
  </div>

  <!-- XP 14j -->
  <div class="adm-card">
    <div class="adm-card-title">⚡ XP distribués (14 jours)</div>
    <?php $max_x = max(array_values($chart_xp) ?: [1]); ?>
    <div class="mini-chart">
      <?php foreach ($chart_xp as $d => $n):
        $h = $max_x > 0 ? max(4, round($n / $max_x * 60)) : 4;
        $is_today = ($d === date('Y-m-d'));
      ?>
      <div class="mini-bar"
           style="height:<?= $h ?>px;background:<?= $is_today ? '#ea5649' : '#12314e' ?>"
           title="<?= date('d/m', strtotime($d)) ?> : <?= number_format($n,0,',',' ') ?> XP"></div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:.68rem;color:#6b7f96;margin-top:6px">
      <span><?= date('d/m', strtotime('-13 day')) ?></span>
      <span style="color:#ea5649;font-weight:700">Aujourd'hui : <?= number_format($chart_xp[date('Y-m-d')] ?? 0,0,',',' ') ?> XP</span>
      <span><?= date('d/m') ?></span>
    </div>
  </div>
</div>

<!-- Actions rapides -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px">
  <a href="missions.php"       class="btn-adm btn-adm-ghost">📋 Missions</a>
  <a href="participations.php" class="btn-adm btn-adm-ghost">
    👀 Participations<?= $kpis['participations_pending'] > 0 ? ' <span style="background:#ea5649;color:#fff;padding:2px 7px;border-radius:99px;font-size:.68rem;margin-left:4px">' . $kpis['participations_pending'] . '</span>' : '' ?>
  </a>
  <a href="users.php"          class="btn-adm btn-adm-ghost">👥 Utilisateurs</a>
  <a href="mission-edit.php"   class="btn-adm btn-adm-primary">+ Mission</a>
</div>

<div class="dash-grid-2">

  <!-- Derniers inscrits -->
  <div class="adm-card">
    <div class="adm-card-title">🆕 Derniers inscrits</div>
    <?php if ($last_users): ?>
    <div>
      <?php foreach ($last_users as $lu):
        $lu_emoji = '🧭';
        if ($lu['avatar_type'] === 'preset') {
            $cfg = json_decode($lu['avatar_config'] ?? '{}', true) ?? [];
            $lu_emoji = $cfg['emoji'] ?? '🧭';
        }
        $lu_av = avatar_url($lu);
      ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0ece7">
        <div style="width:30px;height:30px;border-radius:7px;background:var(--primary);
          display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;overflow:hidden">
          <?php if ($lu_av): ?><img src="<?= e($lu_av) ?>" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?><?= e($lu_emoji) ?><?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:.82rem;font-weight:700;color:#0c1e2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= e($lu['pseudo']) ?>
          </div>
          <div style="font-size:.7rem;color:#6b7f96"><?= $lu['created_at'] ? date('d/m/Y', strtotime($lu['created_at'])) : '' ?></div>
        </div>
        <span style="font-size:.72rem;font-weight:700;color:#6b7f96">Niv.<?= (int)$lu['level'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:12px">
      <a href="users.php" style="font-size:.8rem;font-weight:700;color:#ea5649;text-decoration:none">Voir tous →</a>
    </div>
    <?php else: ?>
    <div class="adm-empty" style="padding:24px"><p>Aucun membre.</p></div>
    <?php endif; ?>
  </div>

  <!-- Dernières actions XP -->
  <div class="adm-card">
    <div class="adm-card-title">⚡ Dernières actions XP</div>
    <?php if ($last_actions): ?>
    <div>
      <?php
      $xp_icons = [
        'registration'          => '🎉',
        'mission_success'       => '✅',
        'hidden_hunt_completion'=> '🗝️',
        'mission_participation' => '📋',
        'badge'                 => '🏅',
      ];
      foreach ($last_actions as $la): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0ece7">
        <span style="font-size:1.1rem;flex-shrink:0"><?= $xp_icons[$la['source_type']] ?? '⚡' ?></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:.82rem;font-weight:700;color:#0c1e2e"><?= e($la['pseudo']) ?></div>
          <div style="font-size:.7rem;color:#6b7f96"><?= e($la['source_type']) ?></div>
        </div>
        <span style="font-size:.82rem;font-weight:800;color:#ea5649;white-space:nowrap">
          +<?= number_format((int)$la['xp_amount'],0,',',' ') ?> XP
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="adm-empty" style="padding:24px"><p>Aucune action XP.</p></div>
    <?php endif; ?>
  </div>

</div>

<?php require_once '_admin-footer.php'; ?>
