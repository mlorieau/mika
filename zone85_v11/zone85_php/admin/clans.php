<?php
// ============================================================
// admin/clans.php — Statistiques des clans
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current    = 'clans';
$admin_page_title = 'Clans';

$pdo = db();

// ── Définition des clans (ordre, couleurs) ────────────────────
$clan_meta = [
    'bocage'   => ['label' => 'Bocage',   'emoji' => '🌳', 'color' => '#2a7d4f', 'bg' => 'rgba(42,125,79,.08)',  'border' => 'rgba(42,125,79,.25)'],
    'littoral' => ['label' => 'Littoral', 'emoji' => '🌊', 'color' => '#1565c0', 'bg' => 'rgba(21,101,192,.08)', 'border' => 'rgba(21,101,192,.25)'],
    'marais'   => ['label' => 'Marais',   'emoji' => '🦢', 'color' => '#6d4c41', 'bg' => 'rgba(109,76,65,.08)',  'border' => 'rgba(109,76,65,.25)'],
];

// ── Chargement des données pour chaque clan ───────────────────
$clans_data = [];

if ($pdo) {

    // Récupérer les ids de clans
    $clan_rows = [];
    try {
        $clan_rows = $pdo->query("SELECT id, name, slug, color_primary FROM clans ORDER BY slug ASC")->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/clans clan_rows] ' . $e->getMessage());
    }

    // Saison active
    $active_season_id = null;
    try {
        $row = $pdo->query("SELECT id FROM seasons WHERE status = 'active' LIMIT 1")->fetch();
        $active_season_id = $row ? (int)$row['id'] : null;
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/clans active_season] ' . $e->getMessage());
    }

    foreach ($clan_rows as $clan) {
        $slug = $clan['slug'];
        $cid  = (int)$clan['id'];
        $meta = $clan_meta[$slug] ?? ['label' => $clan['name'], 'emoji' => '🏳️', 'color' => '#6b7f96', 'bg' => 'rgba(107,127,150,.08)', 'border' => 'rgba(107,127,150,.25)'];

        $entry = [
            'id'              => $cid,
            'slug'            => $slug,
            'name'            => $clan['name'],
            'color'           => $clan['color_primary'] ?: $meta['color'],
            'meta'            => $meta,
            'members_total'   => 0,
            'season_score'    => 0,
            'all_time_score'  => 0,
            'total_parts'     => 0,
            'total_xp'        => 0,
            'last_logs'       => [],
            'recent_members'  => [],
            'trophies'        => 0,
        ];

        // Membres actifs
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE clan_id = :cid AND status = 'active'");
            $stmt->execute([':cid' => $cid]);
            $entry['members_total'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[ZONE85 admin/clans members] ' . $e->getMessage());
        }

        // Score saison courante
        if ($active_season_id) {
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM clan_score_logs WHERE clan_id = :cid AND season_id = :sid");
                $stmt->execute([':cid' => $cid, ':sid' => $active_season_id]);
                $entry['season_score'] = (int)$stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log('[ZONE85 admin/clans season_score] ' . $e->getMessage());
            }
        }

        // Score toutes saisons
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM clan_score_logs WHERE clan_id = :cid");
            $stmt->execute([':cid' => $cid]);
            $entry['all_time_score'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[ZONE85 admin/clans all_time] ' . $e->getMessage());
        }

        // Participations totales des membres du clan
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM participations p
                INNER JOIN users u ON u.id = p.user_id
                WHERE u.clan_id = :cid
            ");
            $stmt->execute([':cid' => $cid]);
            $entry['total_parts'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[ZONE85 admin/clans total_parts] ' . $e->getMessage());
        }

        // XP total des membres
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(xp_total),0) FROM users WHERE clan_id = :cid AND status = 'active'");
            $stmt->execute([':cid' => $cid]);
            $entry['total_xp'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[ZONE85 admin/clans total_xp] ' . $e->getMessage());
        }

        // 5 dernières entrées clan_score_logs
        try {
            $stmt = $pdo->prepare("
                SELECT csl.points, csl.reason, csl.created_at,
                       s.title AS season_title
                FROM clan_score_logs csl
                LEFT JOIN seasons s ON s.id = csl.season_id
                WHERE csl.clan_id = :cid
                ORDER BY csl.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([':cid' => $cid]);
            $entry['last_logs'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[ZONE85 admin/clans last_logs] ' . $e->getMessage());
        }

        // 5 derniers membres inscrits
        try {
            $stmt = $pdo->prepare("
                SELECT id, pseudo, xp_total, created_at
                FROM users
                WHERE clan_id = :cid
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([':cid' => $cid]);
            $entry['recent_members'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[ZONE85 admin/clans recent_members] ' . $e->getMessage());
        }

        // Trophées
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM season_trophies WHERE winning_clan_id = :cid");
            $stmt->execute([':cid' => $cid]);
            $entry['trophies'] = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            // Table peut ne pas exister encore — catch silencieux
            error_log('[ZONE85 admin/clans trophies] ' . $e->getMessage());
        }

        $clans_data[$slug] = $entry;
    }
}

// Trier par ordre canonique bocage / littoral / marais
$clans_ordered = [];
foreach (array_keys($clan_meta) as $slug) {
    if (isset($clans_data[$slug])) {
        $clans_ordered[] = $clans_data[$slug];
    }
}
// Ajouter les clans éventuellement non couverts par $clan_meta
foreach ($clans_data as $slug => $c) {
    if (!isset($clan_meta[$slug])) $clans_ordered[] = $c;
}

// ── Vue ───────────────────────────────────────────────────────
require_once '_admin-header.php';

$admin_scripts = <<<'JS'
<script>
(function(){
    var nav = document.querySelector('.adm-topnav');
    if (!nav) return;
    var existing = nav.querySelector('a[href="clans.php"]');
    if (!existing) {
        var a = document.createElement('a');
        a.href = 'clans.php';
        a.textContent = 'Clans';
        if (window.location.pathname.endsWith('clans.php')) a.className = 'active';
        nav.appendChild(a);
    }
})();
</script>
JS;
?>

<style>
  .clans-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 32px;
  }
  @media (max-width: 960px) {
    .clans-grid { grid-template-columns: 1fr; }
  }
  .clan-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(12,30,46,.07);
    border: 1px solid rgba(18,49,78,.07);
    overflow: hidden;
  }
  .clan-card-header {
    padding: 20px 20px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .clan-card-emoji {
    font-size: 2rem;
    line-height: 1;
  }
  .clan-card-name {
    font-size: 1.1rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -.3px;
  }
  .clan-card-body {
    padding: 16px 20px 20px;
  }
  .clan-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0ece7;
    font-size: .84rem;
  }
  .clan-stat-row:last-child { border-bottom: none; }
  .clan-stat-label { color: #6b7f96; font-weight: 600; }
  .clan-stat-val   { font-weight: 800; color: #0c1e2e; }
  .clan-section-title {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #6b7f96;
    margin: 16px 0 8px;
  }
  .clan-member-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: .8rem;
    border-bottom: 1px solid #f8f4ef;
  }
  .clan-member-row:last-child { border-bottom: none; }
  .clan-member-pseudo { font-weight: 700; color: #0c1e2e; }
  .clan-member-xp { color: #6b7f96; font-size: .75rem; }
  .clan-log-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 5px 0;
    font-size: .78rem;
    border-bottom: 1px solid #f8f4ef;
  }
  .clan-log-row:last-child { border-bottom: none; }
  .clan-log-reason { color: #0c1e2e; font-weight: 600; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .clan-log-pts { font-weight: 800; white-space: nowrap; }
  .clan-log-pts.pos { color: #2a9d5c; }
  .clan-log-pts.neg { color: #c0392b; }
  .clan-trophy-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .78rem; font-weight: 700;
    background: rgba(201,150,42,.12); color: #8a6020;
    padding: 3px 10px; border-radius: 999px;
  }
</style>

<!-- Page header -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">🏳️ Clans</h1>
    <p class="adm-page-sub">Statistiques et activité des trois clans Zone85.</p>
  </div>
</div>

<?php if (!$pdo): ?>
<div class="adm-flash adm-flash-err">⚠️ <span>Base de données indisponible. Les données ne peuvent pas être chargées.</span></div>
<?php endif; ?>

<?php if (empty($clans_ordered)): ?>
  <div class="adm-empty">
    <div class="adm-empty-icon">🏳️</div>
    <p>Aucun clan trouvé en base de données.</p>
  </div>
<?php else: ?>

<!-- Stats globales rapides -->
<?php
  $total_members = array_sum(array_column($clans_ordered, 'members_total'));
  $total_parts   = array_sum(array_column($clans_ordered, 'total_parts'));
  $total_xp      = array_sum(array_column($clans_ordered, 'total_xp'));
?>
<div class="adm-stats" style="margin-bottom:28px">
  <div class="adm-stat">
    <div class="adm-stat-label">Membres actifs</div>
    <div class="adm-stat-value coral"><?= number_format($total_members, 0, ',', ' ') ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Participations totales</div>
    <div class="adm-stat-value blue"><?= number_format($total_parts, 0, ',', ' ') ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">XP total communauté</div>
    <div class="adm-stat-value green"><?= number_format($total_xp, 0, ',', ' ') ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">Clans actifs</div>
    <div class="adm-stat-value amber"><?= count($clans_ordered) ?></div>
  </div>
</div>

<!-- Grille 3 clans -->
<div class="clans-grid">
  <?php foreach ($clans_ordered as $c): ?>
  <?php
    $meta       = $c['meta'];
    $hdr_color  = $c['color'] ?: $meta['color'];
    $slug       = $c['slug'];
  ?>
  <div class="clan-card">
    <!-- En-tête colorée -->
    <div class="clan-card-header" style="background:<?= e($hdr_color) ?>">
      <span class="clan-card-emoji"><?= e($meta['emoji']) ?></span>
      <div>
        <div class="clan-card-name"><?= e($c['name']) ?></div>
        <?php if ($c['trophies'] > 0): ?>
          <span class="clan-trophy-badge" style="margin-top:4px;display:inline-flex">
            🏆 <?= $c['trophies'] ?> trophée<?= $c['trophies'] > 1 ? 's' : '' ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="clan-card-body">

      <!-- Stats chiffrées -->
      <div class="clan-stat-row">
        <span class="clan-stat-label">👥 Membres actifs</span>
        <span class="clan-stat-val"><?= number_format($c['members_total'], 0, ',', ' ') ?></span>
      </div>
      <div class="clan-stat-row">
        <span class="clan-stat-label">⚡ Score saison en cours</span>
        <span class="clan-stat-val"><?= format_score($c['season_score']) ?></span>
      </div>
      <div class="clan-stat-row">
        <span class="clan-stat-label">📊 Score toutes saisons</span>
        <span class="clan-stat-val"><?= format_score($c['all_time_score']) ?></span>
      </div>
      <div class="clan-stat-row">
        <span class="clan-stat-label">🎯 Participations membres</span>
        <span class="clan-stat-val"><?= number_format($c['total_parts'], 0, ',', ' ') ?></span>
      </div>
      <div class="clan-stat-row">
        <span class="clan-stat-label">✨ XP total membres</span>
        <span class="clan-stat-val"><?= format_xp($c['total_xp']) ?></span>
      </div>

      <!-- Dernières actions score -->
      <?php if (!empty($c['last_logs'])): ?>
      <div class="clan-section-title">Dernières actions</div>
      <?php foreach ($c['last_logs'] as $log): ?>
        <div class="clan-log-row">
          <span class="clan-log-reason"><?= e($log['reason'] ?? '—') ?></span>
          <span class="clan-log-pts <?= (int)$log['points'] >= 0 ? 'pos' : 'neg' ?>">
            <?= (int)$log['points'] >= 0 ? '+' : '' ?><?= number_format((int)$log['points'], 0, ',', ' ') ?> pts
          </span>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <!-- Membres récents -->
      <?php if (!empty($c['recent_members'])): ?>
      <div class="clan-section-title">Derniers membres inscrits</div>
      <?php foreach ($c['recent_members'] as $mem): ?>
        <div class="clan-member-row">
          <span class="clan-member-pseudo"><?= e($mem['pseudo']) ?></span>
          <span class="clan-member-xp"><?= format_xp((int)($mem['xp_total'] ?? 0)) ?></span>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <!-- Action admin : voir les membres -->
      <div style="margin-top:16px;padding-top:12px;border-top:1px solid #f0ece7">
        <a href="users.php?clan=<?= e($slug) ?>" class="btn-adm btn-adm-ghost btn-adm-sm" style="width:100%;justify-content:center">
          👥 Voir tous les membres
        </a>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require_once '_admin-footer.php'; ?>
