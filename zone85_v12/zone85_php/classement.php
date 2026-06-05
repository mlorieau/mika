<?php
$page_title       = 'Classement — Zone85';
$page_description = 'Classement des Zonautes de la Zone85. Découvre le top 50 joueurs par XP saison, filtrable par clan.';
$page_canonical   = 'https://www.zone85.fr/classement.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'classement';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

$pdo         = db();
$is_logged   = is_logged_in();
$active_user = $is_logged ? current_user() : null;

// ── Filtre clan ──────────────────────────────────────────────
$clan_filter = trim($_GET['clan'] ?? 'all');
if (!in_array($clan_filter, ['all', 'bocage', 'littoral', 'marais'], true)) {
    $clan_filter = 'all';
}

// ── Saison active ────────────────────────────────────────────
$sr           = _active_season_row();
$season_start = $sr ? $sr['start_date'] : '1970-01-01';
$season_id    = $sr ? (int)$sr['id'] : 0;

// ── Top 50 joueurs ───────────────────────────────────────────
$leaderboard = [];
$total_count = 0;
$my_rank     = null;

if ($pdo) {
    try {
        $where_clan = ($clan_filter !== 'all') ? "AND c.slug = :clan_slug" : '';

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.pseudo,
                u.avatar_type,
                u.avatar_file,
                u.xp_total,
                COALESCE(u.level, 1)            AS level,
                COALESCE(c.slug, '')             AS clan_slug,
                COALESCE(c.name, '')             AS clan_name,
                COALESCE(c.color_primary, '#12314e') AS clan_color,
                (SELECT COALESCE(SUM(xl.xp_amount), 0)
                 FROM xp_logs xl
                 WHERE xl.user_id = u.id AND xl.created_at >= :season_start) AS xp_season,
                (SELECT COUNT(*)
                 FROM participations p
                 WHERE p.user_id = u.id
                   AND p.status IN ('validated','auto_validated')) AS missions_count
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.status = 'active' {$where_clan}
            ORDER BY xp_season DESC, u.xp_total DESC
            LIMIT 50
        ");
        $params = [':season_start' => $season_start];
        if ($clan_filter !== 'all') $params[':clan_slug'] = $clan_filter;
        $stmt->execute($params);
        $leaderboard = $stmt->fetchAll();

        // Rang côté PHP
        foreach ($leaderboard as $i => &$row) {
            $row['rank']         = $i + 1;
            $row['xp_season']    = (int)$row['xp_season'];
            $row['xp_total']     = (int)$row['xp_total'];
            $row['missions_count'] = (int)$row['missions_count'];
            $row['level']        = max(1, min(10, (int)$row['level']));
        }
        unset($row);

        // Rang de l'utilisateur connecté
        if ($is_logged) {
            $uid = (int)$active_user['id'];
            foreach ($leaderboard as $row) {
                if ((int)$row['id'] === $uid) { $my_rank = $row['rank']; break; }
            }
            // Si hors top 50, on calcule son rang réel
            if ($my_rank === null) {
                $r = $pdo->prepare("
                    SELECT COUNT(*) + 1 AS rank FROM users u
                    LEFT JOIN clans c ON c.id = u.clan_id
                    WHERE u.status = 'active'
                      AND (SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs
                           WHERE user_id = u.id AND created_at >= :s)
                        > (SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs
                           WHERE user_id = :uid AND created_at >= :s2)
                    " . ($clan_filter !== 'all' ? "AND c.slug = :clan_slug" : '') . "
                ");
                $p2 = [':s' => $season_start, ':s2' => $season_start, ':uid' => $uid];
                if ($clan_filter !== 'all') $p2[':clan_slug'] = $clan_filter;
                $r->execute($p2);
                $my_rank = (int)($r->fetchColumn() ?: 0);
            }
        }

        // Total joueurs pour le filtre actif
        $c2 = $pdo->prepare("
            SELECT COUNT(*) FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.status = 'active'
            " . ($clan_filter !== 'all' ? "AND c.slug = :clan_slug" : ''));
        $p3 = ($clan_filter !== 'all') ? [':clan_slug' => $clan_filter] : [];
        $c2->execute($p3);
        $total_count = (int)$c2->fetchColumn();

    } catch (PDOException $e) {
        error_log('[classement] ' . $e->getMessage());
    }
}

// ── Chip classes ─────────────────────────────────────────────
$chip_map = [
    'bocage'   => ['bocage-chip',   '🌳 Bocage'],
    'littoral' => ['littoral-chip', '⚓ Littoral'],
    'marais'   => ['marais-chip',   '🌿 Marais'],
];

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<style>
/* ── Hero ── */
.cl-hero{background:linear-gradient(135deg,#0c1e2e 0%,#163756 60%,#12314e 100%);padding:72px 0 48px;text-align:center;position:relative;overflow:hidden}
.cl-hero::before{content:'🏆';position:absolute;top:-20px;right:8%;font-size:12rem;opacity:.04;pointer-events:none}
.cl-hero-label{font-size:.68rem;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:var(--primary);display:block;margin-bottom:12px}
.cl-hero-title{font-size:clamp(1.9rem,4.5vw,3rem);font-weight:900;color:#fff;letter-spacing:-.03em;line-height:1.1;margin-bottom:16px}
.cl-hero-sub{font-size:.95rem;color:rgba(255,255,255,.6);max-width:460px;margin:0 auto 28px;line-height:1.6}
.cl-hero-stats{display:flex;justify-content:center;gap:36px;flex-wrap:wrap}
.cl-stat{text-align:center}
.cl-stat-num{font-size:1.9rem;font-weight:900;color:var(--primary);line-height:1}
.cl-stat-label{font-size:.7rem;font-weight:600;color:rgba(255,255,255,.5);letter-spacing:.08em;text-transform:uppercase;margin-top:4px}

/* ── Section ── */
.cl-section{padding:48px 0 64px;background:var(--beige)}
.cl-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:32px}
.cl-tab{padding:8px 18px;border-radius:20px;font-size:.82rem;font-weight:700;text-decoration:none;border:2px solid var(--beige-dark);color:var(--text-mid);transition:all .15s;background:#fff}
.cl-tab:hover{border-color:var(--primary);color:var(--primary)}
.cl-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}

/* ── Podium ── */
.cl-podium{display:flex;justify-content:center;align-items:flex-end;gap:12px;margin-bottom:40px}
.cl-podium-item{display:flex;flex-direction:column;align-items:center;gap:8px}
.cl-pod-avatar{border-radius:50%;object-fit:cover;border:3px solid #d4af37}
.cl-pod-avatar-wrap{position:relative;display:flex;justify-content:center;align-items:center}
.cl-pod-medal{position:absolute;bottom:-4px;right:-4px;font-size:1.1rem;line-height:1}
.cl-pod-1 .cl-pod-avatar{width:80px;height:80px;border-color:gold}
.cl-pod-2 .cl-pod-avatar{width:64px;height:64px;border-color:silver}
.cl-pod-3 .cl-pod-avatar{width:56px;height:56px;border-color:#cd7f32}
.cl-pod-1 .cl-pod-emoji{font-size:2.5rem}
.cl-pod-2 .cl-pod-emoji{font-size:2rem}
.cl-pod-3 .cl-pod-emoji{font-size:1.8rem}
.cl-pod-pseudo{font-size:.82rem;font-weight:800;color:var(--navy-dark);text-align:center;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cl-pod-xp{font-size:.72rem;font-weight:700;color:var(--primary)}
.cl-pod-plinth{border-radius:6px 6px 0 0;width:100%}
.cl-pod-1 .cl-pod-plinth{background:#ffd700;height:72px;min-width:80px}
.cl-pod-2 .cl-pod-plinth{background:#c0c0c0;height:52px;min-width:72px}
.cl-pod-3 .cl-pod-plinth{background:#cd7f32;height:38px;min-width:64px}
.cl-pod-rank{font-size:1rem;font-weight:900;color:#fff;text-align:center;padding-top:8px}

/* ── Tableau ── */
.cl-table-wrap{background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark);overflow:hidden;box-shadow:var(--shadow-sm)}
.cl-table{width:100%;border-collapse:collapse}
.cl-table th{padding:10px 16px;font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid var(--beige-dark);text-align:left;background:var(--beige-light)}
.cl-table th.num{text-align:right}
.cl-table td{padding:12px 16px;border-bottom:1px solid var(--beige-dark);vertical-align:middle}
.cl-table tr:last-child td{border-bottom:none}
.cl-table tr:hover td{background:rgba(248,244,239,.6)}
.cl-table tr.me td{background:rgba(234,86,73,.04);border-left:3px solid var(--primary)}
.cl-rank-num{font-size:.9rem;font-weight:900;color:var(--navy-dark);min-width:28px;display:inline-block;text-align:right}
.cl-medal{font-size:1.1rem;line-height:1}
.cl-avatar-cell{display:flex;align-items:center;gap:12px}
.cl-avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0}
.cl-avatar-emoji{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.cl-pseudo{font-size:.9rem;font-weight:700;color:var(--navy-dark)}
.cl-me-badge{display:inline-block;background:rgba(234,86,73,.1);color:var(--primary);border:1px solid rgba(234,86,73,.25);border-radius:10px;font-size:.6rem;font-weight:800;padding:1px 6px;margin-left:6px;vertical-align:middle;letter-spacing:.04em}
.cl-level{font-size:.75rem;font-weight:600;color:var(--text-muted)}
.cl-xp{font-size:.88rem;font-weight:800;color:var(--navy-dark);text-align:right}
.cl-xp-sub{font-size:.72rem;font-weight:500;color:var(--text-muted);text-align:right}
.cl-missions{font-size:.85rem;font-weight:700;color:var(--text-mid);text-align:right}
.cl-empty{text-align:center;padding:56px 24px;color:var(--text-muted)}
.cl-empty-icon{font-size:3rem;margin-bottom:12px}
.cl-empty-title{font-size:1.1rem;font-weight:700;color:var(--navy-dark);margin-bottom:8px}
.cl-my-rank{background:#fff;border-radius:var(--radius);border:2px solid var(--primary);padding:14px 20px;display:flex;align-items:center;gap:14px;margin-bottom:24px}
.cl-my-rank-label{font-size:.78rem;font-weight:600;color:var(--text-muted);flex:1}
.cl-my-rank-num{font-size:1.5rem;font-weight:900;color:var(--primary)}
.cl-total-label{font-size:.82rem;color:var(--text-muted);margin-bottom:16px}
@media(max-width:640px){
  .cl-table th.hide-sm,.cl-table td.hide-sm{display:none}
  .cl-podium{gap:6px}
  .cl-pod-1 .cl-pod-avatar{width:64px;height:64px}
  .cl-pod-2 .cl-pod-avatar{width:52px;height:52px}
  .cl-pod-3 .cl-pod-avatar{width:44px;height:44px}
}
</style>

<!-- ── HERO ── -->
<section class="cl-hero">
  <div class="container">
    <span class="cl-hero-label">Zone85</span>
    <h1 class="cl-hero-title">Classement 🏆</h1>
    <p class="cl-hero-sub">Les meilleurs Zonautes de la saison. Grimpe dans le classement en accumulant des XP.</p>
    <div class="cl-hero-stats">
      <div class="cl-stat">
        <div class="cl-stat-num"><?= $total_count ?></div>
        <div class="cl-stat-label">Zonautes</div>
      </div>
      <div class="cl-stat">
        <div class="cl-stat-num"><?= count($leaderboard) ?></div>
        <div class="cl-stat-label">Dans le top</div>
      </div>
      <?php if ($is_logged && $my_rank): ?>
      <div class="cl-stat">
        <div class="cl-stat-num">#<?= $my_rank ?></div>
        <div class="cl-stat-label">Ton rang</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ── CLASSEMENT ── -->
<section class="cl-section">
  <div class="container">

    <!-- Tabs filtre clan -->
    <div class="cl-tabs">
      <?php
      $tabs = [
        'all'      => '🌍 Tous les clans',
        'bocage'   => '🌳 Bocage',
        'littoral' => '⚓ Littoral',
        'marais'   => '🌿 Marais',
      ];
      foreach ($tabs as $slug => $label): ?>
        <a href="classement.php<?= $slug !== 'all' ? '?clan=' . $slug : '' ?>"
           class="cl-tab <?= $clan_filter === $slug ? 'active' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($is_logged && $my_rank && $my_rank > 50): ?>
    <!-- Mon rang si hors top 50 -->
    <div class="cl-my-rank">
      <span class="cl-my-rank-label">Ton rang actuel dans ce classement</span>
      <span class="cl-my-rank-num">#<?= $my_rank ?></span>
    </div>
    <?php endif; ?>

    <?php if (empty($leaderboard)): ?>
    <div class="cl-empty">
      <div class="cl-empty-icon">🔭</div>
      <p class="cl-empty-title">Aucun joueur dans ce classement</p>
      <p>Rejoins la Zone et commence à gagner des XP pour apparaître ici !</p>
    </div>

    <?php else: ?>

    <!-- Podium top 3 -->
    <?php
    $p1 = $leaderboard[0] ?? null;
    $p2 = $leaderboard[1] ?? null;
    $p3 = $leaderboard[2] ?? null;

    function cl_avatar_html(array $row, string $size_class = ''): string {
        if ($row['avatar_type'] === 'upload' && !empty($row['avatar_file'])) {
            $url = upload_url(ltrim($row['avatar_file'], '/'));
            return '<img src="' . e($url) . '" alt="' . e($row['pseudo']) . '" class="cl-pod-avatar ' . $size_class . '">';
        }
        return '<div class="cl-pod-emoji">🎮</div>';
    }
    ?>

    <?php if ($p1): ?>
    <div class="cl-podium">
      <!-- 2e place -->
      <?php if ($p2): ?>
      <div class="cl-podium-item cl-pod-2">
        <div class="cl-pod-avatar-wrap">
          <?php if ($p2['avatar_type'] === 'upload' && !empty($p2['avatar_file'])): ?>
            <img src="<?= e(upload_url(ltrim($p2['avatar_file'], '/'))) ?>" alt="<?= e($p2['pseudo']) ?>" class="cl-pod-avatar">
          <?php else: ?>
            <div class="cl-pod-emoji">🎮</div>
          <?php endif; ?>
          <span class="cl-pod-medal">🥈</span>
        </div>
        <div class="cl-pod-pseudo"><?= e($p2['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p2['xp_season'], 0, ',', ' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">2</div></div>
      </div>
      <?php endif; ?>

      <!-- 1re place -->
      <div class="cl-podium-item cl-pod-1">
        <div class="cl-pod-avatar-wrap">
          <?php if ($p1['avatar_type'] === 'upload' && !empty($p1['avatar_file'])): ?>
            <img src="<?= e(upload_url(ltrim($p1['avatar_file'], '/'))) ?>" alt="<?= e($p1['pseudo']) ?>" class="cl-pod-avatar">
          <?php else: ?>
            <div class="cl-pod-emoji">🎮</div>
          <?php endif; ?>
          <span class="cl-pod-medal">🥇</span>
        </div>
        <div class="cl-pod-pseudo"><?= e($p1['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p1['xp_season'], 0, ',', ' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">1</div></div>
      </div>

      <!-- 3e place -->
      <?php if ($p3): ?>
      <div class="cl-podium-item cl-pod-3">
        <div class="cl-pod-avatar-wrap">
          <?php if ($p3['avatar_type'] === 'upload' && !empty($p3['avatar_file'])): ?>
            <img src="<?= e(upload_url(ltrim($p3['avatar_file'], '/'))) ?>" alt="<?= e($p3['pseudo']) ?>" class="cl-pod-avatar">
          <?php else: ?>
            <div class="cl-pod-emoji">🎮</div>
          <?php endif; ?>
          <span class="cl-pod-medal">🥉</span>
        </div>
        <div class="cl-pod-pseudo"><?= e($p3['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p3['xp_season'], 0, ',', ' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">3</div></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tableau complet -->
    <p class="cl-total-label">
      Top <?= count($leaderboard) ?> sur <?= number_format($total_count, 0, ',', ' ') ?> joueur<?= $total_count > 1 ? 's' : '' ?> — classement par XP saison
    </p>
    <div class="cl-table-wrap">
      <table class="cl-table">
        <thead>
          <tr>
            <th style="width:48px">#</th>
            <th>Joueur</th>
            <th class="hide-sm">Clan</th>
            <th class="num hide-sm">Missions</th>
            <th class="num">XP Saison</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leaderboard as $row):
            $is_me  = $is_logged && (int)$row['id'] === (int)($active_user['id'] ?? 0);
            $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
            $chip   = $chip_map[$row['clan_slug']] ?? null;
            $level_name = get_level_name($row['level']);
          ?>
          <tr class="<?= $is_me ? 'me' : '' ?>">
            <td style="white-space:nowrap">
              <?php if (isset($medals[$row['rank']])): ?>
                <span class="cl-medal"><?= $medals[$row['rank']] ?></span>
              <?php else: ?>
                <span class="cl-rank-num"><?= $row['rank'] ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div class="cl-avatar-cell">
                <?php if ($row['avatar_type'] === 'upload' && !empty($row['avatar_file'])): ?>
                  <img src="<?= e(upload_url(ltrim($row['avatar_file'], '/'))) ?>"
                       alt="<?= e($row['pseudo']) ?>" class="cl-avatar">
                <?php else: ?>
                  <div class="cl-avatar-emoji">🎮</div>
                <?php endif; ?>
                <div>
                  <div class="cl-pseudo">
                    <?= e($row['pseudo']) ?>
                    <?php if ($is_me): ?><span class="cl-me-badge">toi</span><?php endif; ?>
                  </div>
                  <div class="cl-level">Niv. <?= $row['level'] ?> — <?= e($level_name) ?></div>
                </div>
              </div>
            </td>
            <td class="hide-sm">
              <?php if ($chip): ?>
                <span class="<?= $chip[0] ?>"><?= $chip[1] ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
            <td class="cl-missions hide-sm"><?= $row['missions_count'] ?></td>
            <td>
              <div class="cl-xp"><?= number_format($row['xp_season'], 0, ',', ' ') ?></div>
              <div class="cl-xp-sub"><?= number_format($row['xp_total'], 0, ',', ' ') ?> total</div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$is_logged): ?>
    <div style="text-align:center;margin-top:28px">
      <a href="inscription.php" style="display:inline-block;padding:12px 28px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;text-decoration:none;font-size:.92rem">
        Rejoindre la Zone →
      </a>
      <p style="margin-top:12px;font-size:.82rem;color:var(--text-muted)">Inscris-toi pour apparaître dans le classement.</p>
    </div>
    <?php endif; ?>

    <?php endif; // !empty($leaderboard) ?>

  </div>
</section>

<?php
render_hidden_collectibles('classement');
require_once 'includes/footer.php';
?>
