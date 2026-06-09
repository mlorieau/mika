<?php
// ============================================================
// ZONE85 — communaute.php — Hub communautaire V13
// Onglets : Fil · Classement · Zonautes
// ============================================================
$page_title       = 'Communauté — Zone85';
$page_description = 'Le cœur vivant de Zone85 : classement des Zonautes, fil d\'activité, passeports vendéens.';
$page_canonical   = 'https://www.zone85.fr/communaute.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'communaute';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

// ── Onglet actif ─────────────────────────────────────────────
$_tab = in_array($_GET['tab'] ?? '', ['passeport', 'fil', 'clans', 'classement', 'zonautes', 'recompenses'], true)
    ? $_GET['tab'] : 'fil';
if ($_tab === 'zonautes') $_tab = 'classement'; // backward compat

$is_logged   = is_logged_in();
$active_user = $is_logged ? current_user() : null;
$pdo         = db();

// ── Maps communs ─────────────────────────────────────────────
$chip_map = [
    'bocage'   => ['bocage-chip',   '🌳 Bocage'],
    'littoral' => ['littoral-chip', '⚓ Littoral'],
    'marais'   => ['marais-chip',   '🌿 Marais'],
];
$event_labels = [
    'mission_new'       => ['🎯', 'Nouvelle mission'],
    'mission_complete'  => ['✅', 'Mission accomplie'],
    'badge_unlock'      => ['🏅', 'Badge débloqué'],
    'flash_start'       => ['⚡', 'Flash lancé'],
    'flash_end'         => ['🏁', 'Flash terminé'],
    'season_start'      => ['🗓', 'Saison lancée'],
    'season_end'        => ['🏆', 'Saison terminée'],
    'clan_lead'         => ['🛡', 'Changement de tête'],
    'trophy_awarded'    => ['🥇', 'Trophée attribué'],
    'collectible_found' => ['🔍', 'Collectible trouvé'],
    'rando_done'        => ['🥾', 'Rando terminée'],
    'ktc_win'           => ['🥐', 'KTC — victoire'],
];

// ── Données spécifiques par onglet ───────────────────────────

// ── PASSEPORT ────────────────────────────────────────────────
$pp_badges         = [];
$pp_missions_count = 0;
$pp_collectibles   = 0;
$pp_recent_xp      = [];

if ($_tab === 'passeport' && $is_logged && $pdo) {
    $pp_uid = (int)$active_user['id'];
    try {
        $s = $pdo->prepare("SELECT b.icon, b.title, b.rarity FROM user_badges ub JOIN badges b ON b.id=ub.badge_id WHERE ub.user_id=:uid ORDER BY ub.unlocked_at DESC LIMIT 6");
        $s->execute([':uid' => $pp_uid]);
        $pp_badges = $s->fetchAll();
    } catch (PDOException $e) {}
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id=:uid AND status IN ('validated','auto_validated')");
        $s->execute([':uid' => $pp_uid]);
        $pp_missions_count = (int)$s->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM user_collectibles WHERE user_id=:uid");
        $s->execute([':uid' => $pp_uid]);
        $pp_collectibles = (int)$s->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $s = $pdo->prepare("SELECT reason, xp_amount, created_at FROM xp_logs WHERE user_id=:uid ORDER BY created_at DESC LIMIT 10");
        $s->execute([':uid' => $pp_uid]);
        $pp_recent_xp = $s->fetchAll();
    } catch (PDOException $e) {}
}

// ── FIL ──────────────────────────────────────────────────────
$feed_items  = [];
$total_items = 0;
$total_pages = 1;
$page_num    = 1;

if ($_tab === 'fil') {
    $page_num   = max(1, (int)($_GET['p'] ?? 1));
    $per_feed   = 30;
    $feed_items = fetch_community_feed($page_num, $per_feed);
    if ($pdo) {
        try { $total_items = (int)$pdo->query("SELECT COUNT(*) FROM community_feed")->fetchColumn(); }
        catch (PDOException $e) {}
    }
    $total_pages = max(1, (int)ceil($total_items / $per_feed));
}

// ── CLANS (onglet dédié) ─────────────────────────────────────
$clan_rankings      = [];
$clan_recent_logs   = [];

if ($_tab === 'clans' && $pdo) {
    $sr_clans = _active_season_row();
    $sid_clans = $sr_clans ? (int)$sr_clans['id'] : 0;
    try {
        $cr = $pdo->prepare("
            SELECT c.id, c.name, c.slug, c.color_primary, c.emoji,
                   COALESCE(SUM(csl.points), 0) AS season_points,
                   COUNT(DISTINCT csl.user_id) AS active_members
            FROM clans c
            LEFT JOIN clan_score_logs csl ON csl.clan_id = c.id
                AND (:season_id = 0 OR csl.season_id = :season_id2)
            GROUP BY c.id
            ORDER BY season_points DESC
        ");
        $cr->execute([':season_id' => $sid_clans, ':season_id2' => $sid_clans]);
        $clan_rankings = $cr->fetchAll();
        foreach ($clan_rankings as $i => &$cr_row) { $cr_row['rank'] = $i + 1; }
        unset($cr_row);
    } catch (PDOException $e) {}
    try {
        $lr = $pdo->prepare("
            SELECT csl.points, csl.reason, csl.created_at, u.pseudo, c.name AS clan_name, c.slug AS clan_slug, c.color_primary
            FROM clan_score_logs csl
            JOIN clans c ON c.id = csl.clan_id
            LEFT JOIN users u ON u.id = csl.user_id
            ORDER BY csl.created_at DESC LIMIT 15
        ");
        $lr->execute();
        $clan_recent_logs = $lr->fetchAll();
    } catch (PDOException $e) {}
}

// ── CLASSEMENT ───────────────────────────────────────────────
$leaderboard = [];
$cl_total    = 0;
$my_rank     = null;
$sr          = null;
$season_start= '1970-01-01';
$clan_filter = 'all';

if ($_tab === 'classement') {
    $clan_filter = trim($_GET['clan'] ?? 'all');
    if (!in_array($clan_filter, ['all','bocage','littoral','marais'], true)) $clan_filter = 'all';

    $sr           = _active_season_row();
    $season_start = $sr ? $sr['start_date'] : '1970-01-01';

    if ($pdo) {
        try {
            $where_clan = $clan_filter !== 'all' ? "AND c.slug = :clan_slug" : '';
            $stmt = $pdo->prepare("
                SELECT u.id, u.pseudo, u.avatar_type, u.avatar_file,
                       u.xp_total, COALESCE(u.level,1) AS level,
                       COALESCE(c.slug,'') AS clan_slug,
                       COALESCE(c.name,'') AS clan_name,
                       COALESCE(c.color_primary,'#12314e') AS clan_color,
                       (SELECT COALESCE(SUM(xl.xp_amount),0)
                        FROM xp_logs xl
                        WHERE xl.user_id=u.id AND xl.created_at>=:season_start) AS xp_season,
                       (SELECT COUNT(*) FROM participations p
                        WHERE p.user_id=u.id
                          AND p.status IN ('validated','auto_validated')) AS missions_count
                FROM users u
                LEFT JOIN clans c ON c.id=u.clan_id
                WHERE u.status='active' {$where_clan}
                ORDER BY xp_season DESC, u.xp_total DESC
                LIMIT 50
            ");
            $params = [':season_start' => $season_start];
            if ($clan_filter !== 'all') $params[':clan_slug'] = $clan_filter;
            $stmt->execute($params);
            $leaderboard = $stmt->fetchAll();

            foreach ($leaderboard as $i => &$row) {
                $row['rank']           = $i + 1;
                $row['xp_season']      = (int)$row['xp_season'];
                $row['xp_total']       = (int)$row['xp_total'];
                $row['missions_count'] = (int)$row['missions_count'];
                $row['level']          = max(1, min(10, (int)$row['level']));
            }
            unset($row);

            if ($is_logged) {
                $uid = (int)$active_user['id'];
                foreach ($leaderboard as $row) {
                    if ((int)$row['id'] === $uid) { $my_rank = $row['rank']; break; }
                }
                if ($my_rank === null) {
                    $r = $pdo->prepare("
                        SELECT COUNT(*)+1 AS rank FROM users u
                        LEFT JOIN clans c ON c.id=u.clan_id
                        WHERE u.status='active'
                          AND (SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE user_id=u.id AND created_at>=:s)
                              > (SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE user_id=:uid AND created_at>=:s2)
                        " . ($clan_filter !== 'all' ? "AND c.slug=:clan_slug" : ''));
                    $p2 = [':s'=>$season_start,':s2'=>$season_start,':uid'=>$uid];
                    if ($clan_filter !== 'all') $p2[':clan_slug'] = $clan_filter;
                    $r->execute($p2);
                    $my_rank = (int)($r->fetchColumn() ?: 0);
                }
            }

            $c2 = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN clans c ON c.id=u.clan_id WHERE u.status='active'" . ($clan_filter !== 'all' ? " AND c.slug=:clan_slug" : ''));
            $c2->execute($clan_filter !== 'all' ? [':clan_slug'=>$clan_filter] : []);
            $cl_total = (int)$c2->fetchColumn();

        } catch (PDOException $e) {
            error_log('[communaute classement] '.$e->getMessage());
        }
    }

}

// ── ZONAUTES ─────────────────────────────────────────────────
$players      = [];
$zo_total     = 0;
$zo_offset    = 0;
$filter_clan  = '';
$sort_mode    = 'total';

if ($_tab === 'zonautes') {
    $zo_offset   = max(0, (int)($_GET['offset'] ?? 0));
    $filter_clan = in_array($_GET['clan'] ?? '', ['bocage','littoral','marais',''], true)
                   ? ($_GET['clan'] ?? '') : '';
    $sort_mode   = ($_GET['sort'] ?? 'total') === 'season' ? 'season' : 'total';
    $per_zo      = 50;

    if ($pdo) {
        try {
            $sr_zo        = _active_season_row();
            $sz_start     = $sr_zo ? $sr_zo['start_date'] : '1970-01-01';
            $where_clan   = $filter_clan ? 'AND c.slug=:clan_filter' : '';
            $order_by     = $sort_mode === 'season' ? 'xp_season DESC, u.xp_total DESC' : 'u.xp_total DESC';

            $stmt = $pdo->prepare("
                SELECT u.id, u.pseudo, u.avatar_type, u.avatar_config, u.avatar_file,
                       u.level, u.xp_total, c.slug AS clan_slug, c.name AS clan_name, u.created_at,
                       (SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs xl
                        WHERE xl.user_id=u.id AND xl.created_at>=:season_start) AS xp_season
                FROM users u
                LEFT JOIN clans c ON c.id=u.clan_id
                WHERE u.status='active' AND u.deleted_at IS NULL {$where_clan}
                ORDER BY {$order_by}
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':season_start', $sz_start);
            $stmt->bindValue(':lim', $per_zo, PDO::PARAM_INT);
            $stmt->bindValue(':off', $zo_offset, PDO::PARAM_INT);
            if ($filter_clan) $stmt->bindValue(':clan_filter', $filter_clan);
            $stmt->execute();
            $players = $stmt->fetchAll();

            $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN clans c ON c.id=u.clan_id WHERE u.status='active' AND u.deleted_at IS NULL {$where_clan}");
            if ($filter_clan) $cnt_stmt->bindValue(':clan_filter', $filter_clan);
            $cnt_stmt->execute();
            $zo_total = (int)$cnt_stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log('[communaute zonautes] '.$e->getMessage());
        }
    }
}

// ── RÉCOMPENSES (data lazy-loaded si tab actif) ──────────────
$rw_badges_db = null;
$rw_my_badges = [];
$rw_levels    = [];
if ($_tab === 'recompenses') {
    $rw_badges_db = fetch_badges();
    if ($is_logged && $active_user) {
        $ub = fetch_user_badges((int)$active_user['id']);
        foreach ($ub as $b) $rw_my_badges[$b['id']] = true;
    }
    for ($l = 1; $l <= 10; $l++) {
        $rw_levels[] = ['level' => $l, 'name' => get_level_name($l), 'threshold' => get_level_threshold($l)];
    }
}

// ── URL helpers ──────────────────────────────────────────────
function comm_url(string $tab, array $extra = []): string {
    $p = array_filter(array_merge(['tab' => $tab], $extra), fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== 'all');
    $qs = http_build_query($p);
    return 'communaute.php' . ($qs ? '?'.$qs : '');
}

// ── STATS GLOBALES HERO ──────────────────────────────────────
$community_stats = ['total' => 0, 'today' => 0, 'week_active' => 0];
if ($pdo) {
    try {
        $community_stats['total']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
        $community_stats['today']       = (int)$pdo->query("SELECT COUNT(*) FROM community_feed WHERE created_at >= CURDATE()")->fetchColumn();
        $community_stats['week_active'] = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM xp_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    } catch (PDOException $e) {}
}

// ── STATS PERSO HERO (utilisateur connecté) ──────────────────
$hero_rank    = null;
$hero_xp_week = 0;
if ($is_logged && $pdo) {
    $_uid = (int)$active_user['id'];
    try {
        $hr = $pdo->prepare("SELECT COUNT(*)+1 FROM users u WHERE u.status='active' AND (SELECT COALESCE(SUM(xl.xp_amount),0) FROM xp_logs xl WHERE xl.user_id=u.id) > :mx");
        $hr->execute([':mx' => (int)($active_user['xp_total'] ?? 0)]);
        $hero_rank = (int)$hr->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $hw = $pdo->prepare("SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE user_id=:uid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $hw->execute([':uid' => $_uid]);
        $hero_xp_week = (int)$hw->fetchColumn();
    } catch (PDOException $e) {}
}

// ── HERO STRIP : 3 derniers items du fil ─────────────────────
$hero_feed = [];
if ($pdo) {
    try {
        $hf = $pdo->query("SELECT cf.title, cf.event_type, cf.icon_emoji, u.pseudo, cf.created_at FROM community_feed cf LEFT JOIN users u ON u.id=cf.user_id ORDER BY cf.created_at DESC LIMIT 3");
        $hero_feed = $hf->fetchAll();
    } catch (PDOException $e) {}
}

// ── EXTRA PASSEPORT ──────────────────────────────────────────
$pp_xp_season   = 0;
$pp_xp_week     = 0;
$pp_rando_count = 0;
$pp_ktc_count   = 0;
$pp_next_badge  = null;
if ($_tab === 'passeport' && $is_logged && $pdo) {
    $pp_uid = (int)$active_user['id'];
    try {
        $sr2 = _active_season_row();
        $ss  = $sr2 ? $sr2['start_date'] : '1970-01-01';
        $q = $pdo->prepare("SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE user_id=:uid AND created_at>=:ss");
        $q->execute([':uid' => $pp_uid, ':ss' => $ss]);
        $pp_xp_season = (int)$q->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $q2 = $pdo->prepare("SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE user_id=:uid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $q2->execute([':uid' => $pp_uid]);
        $pp_xp_week = (int)$q2->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $q3 = $pdo->prepare("SELECT COUNT(*) FROM rando_submissions WHERE user_id=:uid AND status='validated'");
        $q3->execute([':uid' => $pp_uid]);
        $pp_rando_count = (int)$q3->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $q4 = $pdo->prepare("SELECT COUNT(*) FROM ktc_propositions WHERE user_id=:uid");
        $q4->execute([':uid' => $pp_uid]);
        $pp_ktc_count = (int)$q4->fetchColumn();
    } catch (PDOException $e) {}
    try {
        $q5 = $pdo->prepare("SELECT b.icon, b.title FROM badges b WHERE b.id NOT IN (SELECT badge_id FROM user_badges WHERE user_id=:uid) ORDER BY b.id ASC LIMIT 1");
        $q5->execute([':uid' => $pp_uid]);
        $pp_next_badge = $q5->fetch() ?: null;
    } catch (PDOException $e) {}
}

// ─────────────────────────────────────────────────────────────
$page_styles = '<style>
/* ── Reset spécifique comm ── */
*,*::before,*::after{box-sizing:border-box}

/* ── HERO ── */
.comm-hero{background:linear-gradient(135deg,#0c1e2e 0%,#163756 100%);padding:108px 0 0;position:relative;overflow:hidden}
.comm-hero::before{content:"";position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;pointer-events:none}
.hero-inner{max-width:960px;margin:0 auto;padding:0 24px}
.hero-user{display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:28px}
.hero-user-avatar{width:72px;height:72px;border-radius:50%;border:3px solid rgba(255,255,255,.25);overflow:hidden;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:900;color:#fff;flex-shrink:0}
.hero-user-avatar img{width:100%;height:100%;object-fit:cover}
.hero-user-info{flex:1;min-width:0}
.hero-user-greeting{font-size:.65rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:3px}
.hero-user-pseudo{font-size:1.4rem;font-weight:900;color:#fff;margin-bottom:6px;line-height:1.1}
.hero-user-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.hero-level-badge{background:#ea5649;color:#fff;border-radius:20px;padding:3px 12px;font-size:.72rem;font-weight:800}
.hero-clan-chip{padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;background:rgba(255,255,255,.12);color:rgba(255,255,255,.8)}
.hero-user-xpbar{margin-top:12px;flex-basis:100%}
.hero-xpbar-meta{display:flex;justify-content:space-between;font-size:.68rem;color:rgba(255,255,255,.45);margin-bottom:5px}
.hero-xpbar-track{height:6px;background:rgba(255,255,255,.12);border-radius:3px;overflow:hidden}
.hero-xpbar-fill{height:100%;background:linear-gradient(90deg,#ea5649,#f0856d);border-radius:3px;width:0;transition:width 1s cubic-bezier(.22,1,.36,1)}
.hero-user-stats{display:flex;gap:20px;margin-top:10px;flex-wrap:wrap}
.hero-stat{text-align:left}
.hero-stat-val{font-size:1.1rem;font-weight:900;line-height:1}
.hero-stat-val.green{color:#4ade80}
.hero-stat-val.gold{color:#fbbf24}
.hero-stat-lbl{font-size:.62rem;font-weight:600;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-top:2px}
.hero-anon{padding-bottom:28px;text-align:left}
.hero-anon-title{font-size:1.6rem;font-weight:900;color:#fff;margin-bottom:8px;line-height:1.2}
.hero-anon-sub{font-size:.9rem;color:rgba(255,255,255,.55);margin-bottom:20px}
.hero-anon-stats{display:flex;gap:28px;margin-bottom:24px;flex-wrap:wrap}
.hero-anon-stat-val{font-size:1.5rem;font-weight:900;color:#fff;line-height:1}
.hero-anon-stat-lbl{font-size:.62rem;font-weight:600;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-top:2px}
.hero-cta{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#ea5649;color:#fff;border-radius:50px;font-weight:800;text-decoration:none;font-size:.92rem;transition:background .15s}
.hero-cta:hover{background:#d94a3d}
.hero-strip{display:flex;gap:1px;background:rgba(255,255,255,.06);border-top:1px solid rgba(255,255,255,.08);margin-top:8px;overflow:hidden}
.hero-strip-item{flex:1;padding:10px 16px;display:flex;align-items:center;gap:10px;min-width:0;transition:background .15s}
.hero-strip-item:hover{background:rgba(255,255,255,.05)}
.hero-strip-icon{font-size:1.1rem;flex-shrink:0}
.hero-strip-text{min-width:0}
.hero-strip-title{font-size:.75rem;font-weight:700;color:rgba(255,255,255,.8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hero-strip-meta{font-size:.63rem;color:rgba(255,255,255,.35);margin-top:1px}
@media(max-width:640px){.hero-strip-item:nth-child(n+3){display:none}}

/* ── Onglets ── */
.comm-tabs-wrap{background:#fff;border-bottom:1.5px solid var(--beige-dark,#e8e0d4);position:sticky;top:60px;z-index:100;box-shadow:0 2px 8px rgba(18,49,78,.06)}
.comm-tabs{display:flex;gap:0;max-width:960px;margin:0 auto;padding:0 24px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.comm-tabs::-webkit-scrollbar{display:none}
.comm-tab{display:inline-flex;align-items:center;gap:7px;padding:14px 20px;font-size:.88rem;font-weight:700;color:var(--text-muted,#6b7f96);text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-1.5px;transition:color .15s,border-color .15s;white-space:nowrap}
.comm-tab:hover{color:var(--navy-dark,#0c1e2e)}
.comm-tab.active{color:var(--primary,#ea5649);border-bottom-color:var(--primary,#ea5649)}
.comm-tab-count{font-size:.65rem;background:rgba(18,49,78,.08);color:var(--text-mid,#3d5166);padding:2px 7px;border-radius:10px;font-weight:700}
.comm-tab.active .comm-tab-count{background:rgba(234,86,73,.12);color:var(--primary,#ea5649)}

/* ── Wrapper général ── */
.comm-content{background:var(--beige,#f8f4ef);min-height:60vh}

/* ── PASSEPORT ── */
.pp-layout{max-width:960px;margin:0 auto;padding:36px 24px 64px;display:grid;grid-template-columns:1fr 360px;gap:28px;align-items:start}
@media(max-width:800px){.pp-layout{grid-template-columns:1fr}}
.pp-card{background:#fff;border-radius:20px;border:1.5px solid var(--beige-dark,#e8e0d4);overflow:hidden;box-shadow:0 4px 20px rgba(18,49,78,.07)}
.pp-level-card{}
.pp-card-hd{background:linear-gradient(135deg,#0a1a2e,#163756);padding:28px 24px 22px;display:flex;align-items:center;gap:18px}
.pp-hd-avatar{width:72px;height:72px;border-radius:50%;border:3px solid rgba(255,255,255,.2);overflow:hidden;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:900;color:#fff;flex-shrink:0}
.pp-hd-avatar img{width:100%;height:100%;object-fit:cover}
.pp-hd-info{flex:1;min-width:0}
.pp-hd-kicker{font-size:.58rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-bottom:4px}
.pp-hd-pseudo{font-size:1.15rem;font-weight:900;color:#fff;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pp-hd-sub{font-size:.78rem;color:rgba(255,255,255,.5)}
.pp-card-bd{padding:22px 24px}
.pp-level-row{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.pp-level-badge{background:#ea5649;color:#fff;border-radius:20px;padding:5px 14px;font-size:.82rem;font-weight:800}
.pp-level-name{font-size:.88rem;color:var(--text-mid,#3d5166);font-weight:600}
.pp-xp-bar-wrap{margin-bottom:18px}
.pp-xp-bar-labels{display:flex;justify-content:space-between;font-size:.7rem;font-weight:700;color:var(--text-muted,#6b7f96);margin-bottom:5px}
.pp-xp-bar-track{height:8px;background:var(--beige-dark,#e8e0d4);border-radius:4px;overflow:hidden}
.pp-xp-bar-fill{height:100%;background:linear-gradient(90deg,#ea5649,#f0856d);border-radius:4px;width:0;transition:width 1s cubic-bezier(.22,1,.36,1)}
.pp-stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px}
.pp-stat{background:var(--beige,#f8f4ef);border-radius:12px;padding:14px 16px}
.pp-stat-val{display:block;font-size:1.2rem;font-weight:900;color:var(--navy-dark,#0c1e2e)}
.pp-stat-lbl{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted,#6b7f96);margin-top:2px}
.pp-badges-card{margin-top:0}
.pp-badges-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.pp-badge-item{display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:10px;background:var(--beige,#f8f4ef);font-size:.8rem;font-weight:700;color:var(--text-mid,#3d5166)}
.pp-badge-item.rarity-legendary{background:#fef3c7;color:#92400e}
.pp-badge-item.rarity-epic{background:#f3e8ff;color:#6b21a8}
.pp-badge-item.rarity-rare{background:#dbeafe;color:#1e40af}
.pp-badge-item.rarity-uncommon{background:#d1fae5;color:#065f46}
.pp-next-badge{display:flex;align-items:center;gap:8px;margin-top:12px;padding:10px 14px;background:linear-gradient(90deg,rgba(234,86,73,.06),transparent);border-left:3px solid #ea5649;border-radius:0 8px 8px 0;font-size:.8rem;color:var(--text-mid,#3d5166)}
.pp-next-badge strong{color:var(--primary,#ea5649)}
.pp-section-title{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted,#6b7f96);margin-bottom:12px}
.pp-right-col{display:flex;flex-direction:column;gap:20px}
.pp-week-card{background:linear-gradient(135deg,#0a1a2e,#163756);border-radius:20px;padding:24px;text-align:center;color:#fff}
.pp-week-xp{font-size:2.8rem;font-weight:900;color:#4ade80;line-height:1;margin-bottom:4px}
.pp-week-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.45);margin-bottom:12px}
.pp-season-xp{font-size:1rem;font-weight:700;color:rgba(255,255,255,.55)}
.pp-xp-log{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark,#e8e0d4);padding:20px 22px}
.pp-xp-log-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--beige-dark,#e8e0d4)}
.pp-xp-log-item:last-child{border-bottom:none;padding-bottom:0}
.pp-xp-log-dot{width:8px;height:8px;border-radius:50%;background:var(--primary,#ea5649);flex-shrink:0}
.pp-xp-log-reason{flex:1;font-size:.82rem;color:var(--text-mid,#3d5166);font-weight:600}
.pp-xp-log-amount{font-size:.85rem;font-weight:900;color:var(--primary,#ea5649);white-space:nowrap}
.pp-xp-log-date{font-size:.65rem;color:var(--text-muted,#6b7f96);white-space:nowrap}
.pp-howto{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark,#e8e0d4);padding:20px 22px}
.pp-howto-item{display:flex;align-items:flex-start;gap:10px;padding:7px 0;border-bottom:1px solid var(--beige-dark,#e8e0d4)}
.pp-howto-item:last-child{border-bottom:none}
.pp-howto-xp{min-width:60px;font-size:.82rem;font-weight:900;color:var(--primary,#ea5649)}
.pp-howto-label{font-size:.82rem;color:var(--text-mid,#3d5166)}
.pp-not-logged{max-width:460px;margin:60px auto;background:#fff;border-radius:20px;border:1.5px solid var(--beige-dark,#e8e0d4);padding:48px 32px;text-align:center}

/* ── LE FIL ── */
.cf-wrap{max-width:720px;margin:0 auto;padding:36px 24px 64px}
.cf-item{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark,#e8e0d4);padding:16px 20px;display:flex;align-items:flex-start;gap:14px;margin-bottom:12px;transition:box-shadow .15s}
.cf-item:hover{box-shadow:0 4px 20px rgba(18,49,78,.08)}
.cf-item.pinned{border-color:var(--primary,#ea5649);background:#fffcfb}
.cf-avatar{width:48px;height:48px;border-radius:50%;flex-shrink:0;overflow:hidden;background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:900;color:#fff}
.cf-avatar img{width:100%;height:100%;object-fit:cover}
.cf-body{flex:1;min-width:0}
.cf-body-top{display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap}
.cf-pseudo{font-size:.88rem;font-weight:800;color:var(--navy-dark,#0c1e2e)}
.cf-action{font-size:.85rem;color:var(--text-muted,#6b7f96)}
.cf-xp-badge{background:rgba(74,222,128,.15);color:#166534;border-radius:10px;padding:2px 8px;font-size:.72rem;font-weight:800}
.cf-body-text{font-size:.82rem;color:var(--text-muted,#6b7f96);line-height:1.5;margin-bottom:5px}
.cf-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.7rem;color:var(--text-muted,#6b7f96)}
.cf-time{color:var(--text-muted,#6b7f96)}
.cf-clan-chip{display:inline-block;padding:2px 8px;border-radius:8px;font-size:.67rem;font-weight:700}
.cf-type-badge{background:var(--beige,#f8f4ef);color:var(--text-mid,#3d5166);border-radius:8px;padding:2px 8px;font-size:.67rem;font-weight:700}
.cf-evt-icon{font-size:1.4rem;flex-shrink:0;margin-top:2px;line-height:1}
.cf-pagination{display:flex;gap:8px;justify-content:center;margin-top:32px;flex-wrap:wrap}
.cf-page-btn{padding:8px 16px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid var(--beige-dark,#e8e0d4);color:var(--text-mid,#3d5166);background:#fff;transition:all .15s}
.cf-page-btn:hover,.cf-page-btn.active{background:var(--primary,#ea5649);border-color:var(--primary,#ea5649);color:#fff}
.cf-empty{text-align:center;padding:64px 24px;background:#fff;border-radius:20px;border:1.5px solid var(--beige-dark,#e8e0d4)}

/* ── CLANS ── */
.cn-wrap{padding:0 0 64px}
.cn-battle{background:linear-gradient(135deg,#0a1a2e,#163756);padding:40px 0}
.cn-battle-inner{max-width:960px;margin:0 auto;padding:0 24px}
.cn-battle-title{font-size:1.3rem;font-weight:900;color:#fff;margin-bottom:4px}
.cn-battle-sub{font-size:.82rem;color:rgba(255,255,255,.45);margin-bottom:28px}
.cn-battle-bar{height:36px;border-radius:8px;overflow:hidden;display:flex;gap:2px;margin-bottom:16px}
.cn-battle-seg{flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:#fff;transition:flex .6s cubic-bezier(.22,1,.36,1)}
.cn-battle-labels{display:flex;gap:2px}
.cn-battle-lbl{flex-shrink:0;font-size:.7rem;font-weight:700;text-align:center;color:rgba(255,255,255,.6)}
.cn-content{max-width:960px;margin:0 auto;padding:36px 24px 0}
.cn-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:40px}
@media(max-width:700px){.cn-cards{grid-template-columns:1fr}}
.cn-card{background:#fff;border-radius:18px;border:1.5px solid var(--beige-dark,#e8e0d4);overflow:hidden;box-shadow:0 2px 12px rgba(18,49,78,.06);transition:transform .15s,box-shadow .15s}
.cn-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(18,49,78,.12)}
.cn-card-hd{padding:20px 20px 16px;text-align:center;color:#fff}
.cn-card-emoji{font-size:2rem;margin-bottom:4px}
.cn-card-name{font-size:1.05rem;font-weight:900}
.cn-card-rank-medal{font-size:1.4rem;margin-bottom:6px;display:block}
.cn-card-bd{padding:16px 20px 20px}
.cn-card-pts{font-size:2rem;font-weight:900;text-align:center;line-height:1;margin-bottom:2px}
.cn-card-pts-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--text-muted,#6b7f96);text-align:center;margin-bottom:14px}
.cn-card-members{font-size:.8rem;color:var(--text-muted,#6b7f96);text-align:center;margin-bottom:12px}
.cn-card-bar-track{height:6px;background:var(--beige-dark,#e8e0d4);border-radius:3px;overflow:hidden;margin-bottom:14px}
.cn-card-bar-fill{height:100%;border-radius:3px;transition:width 1s cubic-bezier(.22,1,.36,1)}
.cn-card-link{display:block;text-align:center;padding:9px 0;background:var(--beige,#f8f4ef);border-radius:10px;font-size:.82rem;font-weight:700;color:var(--navy-dark,#0c1e2e);text-decoration:none;transition:background .15s}
.cn-card-link:hover{background:var(--beige-dark,#e8e0d4)}
.cn-section-title{font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted,#6b7f96);margin-bottom:14px}
.cn-activity{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark,#e8e0d4);margin-bottom:32px;overflow:hidden}
.cn-log-item{display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid var(--beige-dark,#e8e0d4)}
.cn-log-item:last-child{border-bottom:none}
.cn-log-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.cn-log-info{flex:1;min-width:0}
.cn-log-clan{font-size:.82rem;font-weight:800}
.cn-log-reason{font-size:.75rem;color:var(--text-muted,#6b7f96);margin-top:1px}
.cn-log-pts{font-size:.88rem;font-weight:900;white-space:nowrap}
.cn-log-date{font-size:.65rem;color:var(--text-muted,#6b7f96);white-space:nowrap}
.cn-howto{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark,#e8e0d4);padding:24px}
.cn-howto-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-top:12px}
.cn-howto-card{background:var(--beige,#f8f4ef);border-radius:12px;padding:14px 16px}
.cn-howto-pts{font-size:1rem;font-weight:900;color:var(--primary,#ea5649);margin-bottom:4px}
.cn-howto-label{font-size:.82rem;font-weight:700;color:var(--navy-dark,#0c1e2e);margin-bottom:3px}
.cn-howto-desc{font-size:.75rem;color:var(--text-muted,#6b7f96);line-height:1.4}

/* ── ZONAUTES (classement) ── */
.zo-wrap{padding:36px 0 64px}
.zo-inner{max-width:960px;margin:0 auto;padding:0 24px}
.zo-podium{display:flex;justify-content:center;align-items:flex-end;gap:16px;margin-bottom:40px;flex-wrap:wrap}
.zo-pod-card{display:flex;flex-direction:column;align-items:center;gap:8px;min-width:0}
.zo-pod-card.rank-1{order:2}
.zo-pod-card.rank-2{order:1}
.zo-pod-card.rank-3{order:3}
.zo-pod-avatar-wrap{position:relative;display:flex;align-items:center;justify-content:center}
.zo-pod-avatar{border-radius:50%;object-fit:cover;border:3px solid gold;display:block}
.zo-pod-avatar.rank-2-av{width:68px;height:68px;border-color:silver}
.zo-pod-avatar.rank-3-av{width:60px;height:60px;border-color:#cd7f32}
.zo-pod-avatar.rank-1-av{width:84px;height:84px;border-color:#ffd700}
.zo-pod-avatar-init{border-radius:50%;background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff}
.zo-pod-medal{position:absolute;bottom:-4px;right:-4px;font-size:1.2rem;line-height:1}
.zo-pod-crown{font-size:1.8rem;margin-bottom:-4px}
.zo-pod-pseudo{font-size:.85rem;font-weight:800;color:var(--navy-dark,#0c1e2e);text-align:center;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.zo-pod-clan{font-size:.68rem;font-weight:700;color:var(--text-muted,#6b7f96)}
.zo-pod-xp{font-size:.78rem;font-weight:800;color:var(--primary,#ea5649)}
.zo-pod-plinth{border-radius:8px 8px 0 0;text-align:center;padding-top:10px;font-size:.85rem;font-weight:900;color:#fff}
.zo-pod-card.rank-1 .zo-pod-plinth{background:#ffd700;height:76px;min-width:84px;color:#7a5a00}
.zo-pod-card.rank-2 .zo-pod-plinth{background:#c0c0c0;height:56px;min-width:72px;color:#444}
.zo-pod-card.rank-3 .zo-pod-plinth{background:#cd7f32;height:44px;min-width:64px;color:#fff}
.zo-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;align-items:center}
.zo-filter-pill{padding:7px 18px;border-radius:20px;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid var(--beige-dark,#e8e0d4);color:var(--text-mid,#3d5166);background:#fff;transition:all .15s;cursor:pointer}
.zo-filter-pill:hover{border-color:var(--navy-dark,#0c1e2e);color:var(--navy-dark,#0c1e2e)}
.zo-filter-pill.active{background:var(--navy-dark,#0c1e2e);border-color:var(--navy-dark,#0c1e2e);color:#fff}
.zo-filter-pill.bocage.active{background:#2a9d5c;border-color:#2a9d5c}
.zo-filter-pill.littoral.active{background:#1a6fb8;border-color:#1a6fb8}
.zo-filter-pill.marais.active{background:#8b6340;border-color:#8b6340}
.zo-table-wrap{background:#fff;border-radius:18px;border:1.5px solid var(--beige-dark,#e8e0d4);overflow:hidden;box-shadow:0 2px 12px rgba(18,49,78,.06);margin-bottom:28px}
.zo-table{width:100%;border-collapse:collapse}
.zo-table th{padding:10px 16px;font-size:.7rem;font-weight:700;color:var(--text-muted,#6b7f96);text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid var(--beige-dark,#e8e0d4);text-align:left;background:var(--beige,#f8f4ef)}
.zo-table th.num{text-align:right}
.zo-table td{padding:12px 16px;border-bottom:1px solid var(--beige-dark,#e8e0d4);vertical-align:middle}
.zo-table tr:last-child td{border-bottom:none}
.zo-table tr:hover td{background:rgba(248,244,239,.5)}
.zo-table tr.me td{background:rgba(234,86,73,.04);border-left:3px solid var(--primary,#ea5649)}
.zo-rank-num{font-size:.9rem;font-weight:900;color:var(--navy-dark,#0c1e2e)}
.zo-avatar-cell{display:flex;align-items:center;gap:12px}
.zo-avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0}
.zo-avatar-init{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:900;color:#fff;flex-shrink:0}
.zo-pseudo{font-size:.9rem;font-weight:700;color:var(--navy-dark,#0c1e2e)}
.zo-me-badge{display:inline-block;background:rgba(234,86,73,.1);color:var(--primary,#ea5649);border:1px solid rgba(234,86,73,.25);border-radius:8px;font-size:.6rem;font-weight:800;padding:1px 6px;margin-left:6px;vertical-align:middle}
.zo-level{font-size:.72rem;font-weight:600;color:var(--text-muted,#6b7f96)}
.zo-clan-chip{display:inline-block;padding:3px 8px;border-radius:8px;font-size:.68rem;font-weight:700}
.zo-xp{font-size:.88rem;font-weight:800;color:var(--navy-dark,#0c1e2e);text-align:right}
.zo-xp-sub{font-size:.7rem;font-weight:500;color:var(--text-muted,#6b7f96);text-align:right}
.zo-missions{font-size:.85rem;font-weight:700;color:var(--text-mid,#3d5166);text-align:right}
.zo-my-rank{background:#fff;border-radius:16px;border:2px solid var(--primary,#ea5649);padding:16px 22px;display:flex;align-items:center;gap:14px;margin-bottom:28px}
.zo-my-rank-label{font-size:.82rem;font-weight:600;color:var(--text-muted,#6b7f96);flex:1}
.zo-my-rank-num{font-size:1.6rem;font-weight:900;color:var(--primary,#ea5649)}
.zo-empty{text-align:center;padding:56px 24px;color:var(--text-muted,#6b7f96)}

/* ── Clan chips ── */
.bocage-chip{display:inline-block;padding:3px 9px;border-radius:8px;font-size:.72rem;font-weight:700;background:#e8f5ee;color:#1a5c38}
.littoral-chip{display:inline-block;padding:3px 9px;border-radius:8px;font-size:.72rem;font-weight:700;background:#e8f0fb;color:#154f8b}
.marais-chip{display:inline-block;padding:3px 9px;border-radius:8px;font-size:.72rem;font-weight:700;background:#f5ede4;color:#6b4c2a}

/* ── Responsive ── */
@media(max-width:640px){
  .zo-table th.hide-sm,.zo-table td.hide-sm{display:none}
  .pp-layout{padding:20px 16px 48px}
  .cn-content{padding:24px 16px 0}
  .cf-wrap{padding:24px 16px 48px}
}
</style>';


require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ════════════════════════════════════════════════════════
     HERO — fond navy, commun à tous les onglets
════════════════════════════════════════════════════════ -->
<section class="comm-hero">
  <div class="hero-inner">
    <?php if ($is_logged):
      $h_level    = max(1, min(10, (int)($active_user['level'] ?? 1)));
      $h_xp       = (int)($active_user['xp_total'] ?? 0);
      $h_xp_next  = get_level_threshold($h_level + 1);
      $h_xp_cur   = get_level_threshold($h_level);
      $h_xp_range = max(1, $h_xp_next - $h_xp_cur);
      $h_xp_pct   = min(100, round(($h_xp - $h_xp_cur) / $h_xp_range * 100));
      $h_av_url   = ($active_user['avatar_type'] === 'upload') ? avatar_url($active_user) : '';
      $h_initials = strtoupper(mb_substr($active_user['pseudo'], 0, 2));
      $h_clan_slug = $active_user['clan_slug'] ?? ($active_user['clan_id'] ? '' : '');
      $h_clan_chip = $chip_map[$h_clan_slug] ?? null;
    ?>
    <div class="hero-user">
      <div class="hero-user-avatar">
        <?php if ($h_av_url): ?><img src="<?= e($h_av_url) ?>" alt="<?= e($active_user['pseudo']) ?>"><?php else: ?><?= e($h_initials) ?><?php endif; ?>
      </div>
      <div class="hero-user-info">
        <div class="hero-user-greeting">Bonjour Zonaute</div>
        <div class="hero-user-pseudo"><?= e($active_user['pseudo']) ?></div>
        <div class="hero-user-meta">
          <span class="hero-level-badge">Niv. <?= $h_level ?> — <?= e(get_level_name($h_level)) ?></span>
          <?php if ($h_clan_chip): ?><span class="hero-clan-chip"><?= $h_clan_chip[1] ?></span><?php endif; ?>
        </div>
        <div class="hero-user-xpbar">
          <div class="hero-xpbar-meta">
            <span><?= number_format($h_xp, 0, ',', ' ') ?> XP</span>
            <?php if ($h_level < 10): ?><span>Prochain niveau : <?= number_format($h_xp_next, 0, ',', ' ') ?> XP</span><?php else: ?><span>Niveau maximum !</span><?php endif; ?>
          </div>
          <div class="hero-xpbar-track">
            <div class="hero-xpbar-fill xp-bar-fill" data-pct="<?= $h_xp_pct ?>" style="width:0"></div>
          </div>
        </div>
        <div class="hero-user-stats">
          <?php if ($hero_xp_week > 0): ?>
          <div class="hero-stat">
            <div class="hero-stat-val green"><?= $hero_xp_week > 0 ? '+' . number_format($hero_xp_week, 0, ',', ' ') : '0' ?> XP</div>
            <div class="hero-stat-lbl">cette semaine</div>
          </div>
          <?php endif; ?>
          <?php if ($hero_rank): ?>
          <div class="hero-stat">
            <div class="hero-stat-val gold">#<?= $hero_rank ?></div>
            <div class="hero-stat-lbl">ton rang</div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="hero-anon">
      <h1 class="hero-anon-title">La communauté Zone85</h1>
      <p class="hero-anon-sub">Le hub vivant des Zonautes vendéens — missions, clans, classements.</p>
      <div class="hero-anon-stats">
        <div>
          <div class="hero-anon-stat-val js-counter" data-val="<?= $community_stats['total'] ?>"><?= $community_stats['total'] ?></div>
          <div class="hero-anon-stat-lbl">Zonautes</div>
        </div>
        <div>
          <div class="hero-anon-stat-val js-counter" data-val="<?= $community_stats['week_active'] ?>"><?= $community_stats['week_active'] ?></div>
          <div class="hero-anon-stat-lbl">actifs cette semaine</div>
        </div>
        <div>
          <div class="hero-anon-stat-val js-counter" data-val="<?= $community_stats['today'] ?>"><?= $community_stats['today'] ?></div>
          <div class="hero-anon-stat-lbl">actions aujourd'hui</div>
        </div>
      </div>
      <a href="inscription.php" class="hero-cta">Rejoindre la Zone →</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($hero_feed)): ?>
    <div class="hero-strip">
      <?php foreach ($hero_feed as $hf_item):
        [$hf_icon] = $event_labels[$hf_item['event_type']] ?? ['📋'];
      ?>
      <div class="hero-strip-item">
        <div class="hero-strip-icon"><?= $hf_item['icon_emoji'] ? e($hf_item['icon_emoji']) : $hf_icon ?></div>
        <div class="hero-strip-text">
          <div class="hero-strip-title"><?= e($hf_item['title'] ?? '') ?></div>
          <div class="hero-strip-meta"><?= $hf_item['pseudo'] ? e($hf_item['pseudo']) . ' · ' : '' ?><span class="js-reltime" data-ts="<?= e($hf_item['created_at'] ?? '') ?>"></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ════════════════════════════════════════════════════════
     ONGLETS sticky
════════════════════════════════════════════════════════ -->
<div class="comm-tabs-wrap">
  <nav class="comm-tabs" aria-label="Onglets communauté">
    <a href="<?= comm_url('passeport') ?>" class="comm-tab <?= $_tab === 'passeport' ? 'active' : '' ?>">Mon Passeport</a>
    <a href="<?= comm_url('fil') ?>" class="comm-tab <?= $_tab === 'fil' ? 'active' : '' ?>">
      Le Fil<?php if ($total_items > 0): ?><span class="comm-tab-count"><?= number_format($total_items, 0, ',', ' ') ?></span><?php endif; ?>
    </a>
    <a href="<?= comm_url('clans') ?>" class="comm-tab <?= $_tab === 'clans' ? 'active' : '' ?>">Clans</a>
    <a href="<?= comm_url('classement') ?>" class="comm-tab <?= $_tab === 'classement' ? 'active' : '' ?>">
      Zonautes<?php if ($cl_total > 0): ?><span class="comm-tab-count"><?= $cl_total ?></span><?php endif; ?>
    </a>
    <a href="<?= comm_url('recompenses') ?>" class="comm-tab <?= $_tab === 'recompenses' ? 'active' : '' ?>">🏅 Trophées &amp; Badges</a>
  </nav>
</div>

<div class="comm-content">


<?php if ($_tab === 'passeport'): ?>
<!-- ════════════════════════════════════════════════════════
     MON PASSEPORT
════════════════════════════════════════════════════════ -->
<?php if (!$is_logged): ?>
<div class="pp-not-logged">
  <div style="font-size:2.5rem;margin-bottom:16px">🪪</div>
  <h2 style="font-size:1.1rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:8px">Crée ton Passeport Vendéen</h2>
  <p style="font-size:.9rem;color:var(--text-muted,#6b7f96);margin-bottom:24px">Rejoins Zone85 pour obtenir ton identité dans la Zone : niveau, badges, clan et historique d'aventures.</p>
  <a href="inscription.php" style="display:inline-block;padding:12px 28px;background:var(--primary,#ea5649);color:#fff;border-radius:50px;font-weight:800;text-decoration:none;font-size:.92rem">Rejoindre la Zone →</a>
  <p style="margin-top:12px;font-size:.82rem;color:var(--text-muted,#6b7f96)">Déjà membre ? <a href="login.php" style="color:var(--primary,#ea5649);font-weight:700;text-decoration:none">Connexion</a></p>
</div>
<?php else:
  $pp_level      = max(1, min(10, (int)($active_user['level'] ?? 1)));
  $pp_xp_total   = (int)($active_user['xp_total'] ?? 0);
  $pp_level_name = get_level_name($pp_level);
  $pp_xp_th_cur  = get_level_threshold($pp_level);
  $pp_xp_th_next = get_level_threshold($pp_level + 1);
  $pp_xp_range   = max(1, $pp_xp_th_next - $pp_xp_th_cur);
  $pp_xp_pct     = ($pp_level < 10) ? min(100, round(($pp_xp_total - $pp_xp_th_cur) / $pp_xp_range * 100)) : 100;
  $pp_avatar_url = ($active_user['avatar_type'] === 'upload') ? avatar_url($active_user) : '';
  $pp_initials   = strtoupper(mb_substr($active_user['pseudo'], 0, 2));
  $pp_clan_name  = '';
  if ($pdo) {
    try {
      $cs = $pdo->prepare("SELECT c.name FROM clans c JOIN users u ON u.clan_id=c.id WHERE u.id=:uid LIMIT 1");
      $cs->execute([':uid' => (int)$active_user['id']]);
      $pp_clan_name = (string)($cs->fetchColumn() ?: '');
    } catch (PDOException $e) {}
  }
?>
<div class="pp-layout">
  <!-- Colonne gauche -->
  <div>
    <!-- Carte niveau / identité -->
    <div class="pp-card pp-level-card" style="margin-bottom:20px">
      <div class="pp-card-hd">
        <div class="pp-hd-avatar">
          <?php if ($pp_avatar_url): ?><img src="<?= e($pp_avatar_url) ?>" alt="<?= e($active_user['pseudo']) ?>"><?php else: ?><?= e($pp_initials) ?><?php endif; ?>
        </div>
        <div class="pp-hd-info">
          <div class="pp-hd-kicker">Zone85 · Passeport Vendéen</div>
          <div class="pp-hd-pseudo"><?= e($active_user['pseudo']) ?></div>
          <div class="pp-hd-sub"><?= $pp_clan_name ? e($pp_clan_name) : 'Sans clan' ?></div>
        </div>
      </div>
      <div class="pp-card-bd">
        <div class="pp-level-row">
          <span class="pp-level-badge">Niv. <?= $pp_level ?></span>
          <span class="pp-level-name"><?= e($pp_level_name) ?></span>
        </div>
        <div class="pp-xp-bar-wrap">
          <div class="pp-xp-bar-labels">
            <span><?= number_format($pp_xp_total, 0, ',', ' ') ?> XP</span>
            <?php if ($pp_level < 10): ?><span>Prochain niveau : <?= number_format($pp_xp_th_next, 0, ',', ' ') ?> XP (<?= number_format(max(0, $pp_xp_th_next - $pp_xp_total), 0, ',', ' ') ?> restants)</span><?php else: ?><span>Niveau maximum</span><?php endif; ?>
          </div>
          <div class="pp-xp-bar-track">
            <div class="pp-xp-bar-fill xp-bar-fill" data-pct="<?= $pp_xp_pct ?>" style="width:0"></div>
          </div>
        </div>
        <div style="text-align:right;margin-top:14px">
          <a href="profil.php" style="display:inline-block;padding:9px 22px;background:var(--primary,#ea5649);color:#fff;border-radius:50px;font-weight:800;text-decoration:none;font-size:.85rem">Mon profil complet →</a>
        </div>
      </div>
    </div>

    <!-- Carte stats -->
    <div class="pp-card" style="margin-bottom:20px">
      <div class="pp-card-bd">
        <div class="pp-section-title">Vos statistiques</div>
        <div class="pp-stats-grid">
          <div class="pp-stat">
            <span class="pp-stat-val"><?= $pp_missions_count ?></span>
            <span class="pp-stat-lbl">Missions validées</span>
          </div>
          <div class="pp-stat">
            <span class="pp-stat-val"><?= $pp_rando_count ?></span>
            <span class="pp-stat-lbl">Randos terminées</span>
          </div>
          <div class="pp-stat">
            <span class="pp-stat-val"><?= $pp_ktc_count ?></span>
            <span class="pp-stat-lbl">Propositions KTC</span>
          </div>
          <div class="pp-stat">
            <span class="pp-stat-val"><?= $pp_collectibles ?></span>
            <span class="pp-stat-lbl">Collectibles trouvés</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Carte badges -->
    <div class="pp-card pp-badges-card">
      <div class="pp-card-bd">
        <div class="pp-section-title">Vos badges</div>
        <?php if (!empty($pp_badges)): ?>
        <div class="pp-badges-grid">
          <?php foreach ($pp_badges as $b): ?>
          <span class="pp-badge-item rarity-<?= e($b['rarity'] ?? 'common') ?>"><?= e($b['icon'] ?? '') ?> <?= e($b['title']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="font-size:.85rem;color:var(--text-muted,#6b7f96);font-style:italic;margin:0">Aucun badge encore — les aventures commencent.</p>
        <?php endif; ?>
        <?php if ($pp_next_badge): ?>
        <div class="pp-next-badge">
          <span>Prochain badge à débloquer :</span>
          <strong><?= e($pp_next_badge['icon'] ?? '') ?> <?= e($pp_next_badge['title']) ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Colonne droite -->
  <div class="pp-right-col">
    <!-- XP cette semaine -->
    <div class="pp-week-card">
      <div class="pp-week-xp">+<?= number_format($pp_xp_week, 0, ',', ' ') ?></div>
      <div class="pp-week-lbl">XP cette semaine</div>
      <div class="pp-season-xp"><?= number_format($pp_xp_season, 0, ',', ' ') ?> XP dans le QG</div>
    </div>

    <!-- Historique XP -->
    <?php if (!empty($pp_recent_xp)): ?>
    <div class="pp-xp-log">
      <div class="pp-section-title">Historique XP récent</div>
      <?php foreach ($pp_recent_xp as $xp_row): ?>
      <div class="pp-xp-log-item">
        <div class="pp-xp-log-dot"></div>
        <div class="pp-xp-log-reason"><?= e($xp_row['reason'] ?? 'Action') ?></div>
        <div class="pp-xp-log-amount">+<?= (int)$xp_row['xp_amount'] ?> XP</div>
        <div class="pp-xp-log-date js-reltime" data-ts="<?= e($xp_row['created_at'] ?? '') ?>"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Comment gagner des XP -->
    <div class="pp-howto">
      <div class="pp-section-title">Comment gagner des XP</div>
      <?php foreach ([
        ['+50–150 XP', 'Valider une mission'],
        ['+25 XP',     'Terminer une randonnée'],
        ['+5 XP',      'Voter pour un KTC'],
        ['+10 XP',     'Proposer un KTC'],
        ['+50+ XP',    'Être retenu par le QG'],
        ['Variable',   'Débloquer un badge'],
      ] as [$xp_val, $xp_lbl]): ?>
      <div class="pp-howto-item">
        <span class="pp-howto-xp"><?= $xp_val ?></span>
        <span class="pp-howto-label"><?= $xp_lbl ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>


<?php elseif ($_tab === 'fil'): ?>
<!-- ════════════════════════════════════════════════════════
     LE FIL DE LA ZONE
════════════════════════════════════════════════════════ -->
<div class="cf-wrap">
  <?php if (empty($feed_items)): ?>
  <div class="cf-empty">
    <div style="font-size:3rem;margin-bottom:12px">📭</div>
    <p style="font-weight:700;color:var(--navy-dark,#0c1e2e);margin-bottom:8px">Fil vide pour l'instant</p>
    <p style="font-size:.88rem;color:var(--text-muted,#6b7f96)">Les actions de la communauté apparaîtront ici.</p>
  </div>
  <?php else: ?>
  <?php foreach ($feed_items as $item):
    [$evt_icon, $evt_label] = $event_labels[$item['event_type']] ?? ['📋', $item['event_type']];
    $chip      = $chip_map[$item['clan_slug'] ?? ''] ?? null;
    $is_pinned = (int)($item['is_pinned'] ?? 0) === 1;
    $cf_initials = $item['pseudo'] ? strtoupper(mb_substr($item['pseudo'], 0, 2)) : '?';
  ?>
  <div class="cf-item <?= $is_pinned ? 'pinned' : '' ?>">
    <div class="cf-avatar"><?= e($cf_initials) ?></div>
    <div class="cf-body">
      <div class="cf-body-top">
        <?php if ($item['pseudo']): ?><span class="cf-pseudo"><?= e($item['pseudo']) ?></span><?php endif; ?>
        <span class="cf-evt-icon"><?= $item['icon_emoji'] ? e($item['icon_emoji']) : $evt_icon ?></span>
        <span class="cf-action"><?= e($item['title']) ?></span>
        <?php if (!empty($item['xp_amount']) && (int)$item['xp_amount'] > 0): ?><span class="cf-xp-badge">+<?= (int)$item['xp_amount'] ?> XP</span><?php endif; ?>
      </div>
      <?php if (!empty($item['body'])): ?><div class="cf-body-text"><?= e($item['body']) ?></div><?php endif; ?>
      <div class="cf-meta">
        <?php if ($chip): ?><span class="cf-clan-chip <?= $chip[0] ?>"><?= $chip[1] ?></span><?php endif; ?>
        <span class="cf-type-badge"><?= e($evt_label) ?></span>
        <?php if ($is_pinned): ?><span class="cf-type-badge" style="background:rgba(234,86,73,.1);color:var(--primary,#ea5649)">📌 Épinglé</span><?php endif; ?>
        <span class="cf-time js-reltime" data-ts="<?= e($item['created_at'] ?? '') ?>"></span>
        <?php if (!empty($item['link_url'])): ?>
        <a href="<?= e($item['link_url']) ?>" style="color:var(--primary,#ea5649);font-weight:700;text-decoration:none;font-size:.72rem">Voir →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if ($total_pages > 1): ?>
  <div class="cf-pagination">
    <?php if ($page_num > 1): ?>
      <a href="<?= comm_url('fil', ['p' => $page_num - 1]) ?>" class="cf-page-btn">← Précédent</a>
    <?php endif; ?>
    <?php for ($pg = max(1, $page_num - 2); $pg <= min($total_pages, $page_num + 2); $pg++): ?>
      <a href="<?= comm_url('fil', ['p' => $pg]) ?>" class="cf-page-btn <?= $pg === $page_num ? 'active' : '' ?>"><?= $pg ?></a>
    <?php endfor; ?>
    <?php if ($page_num < $total_pages): ?>
      <a href="<?= comm_url('fil', ['p' => $page_num + 1]) ?>" class="cf-page-btn">Suivant →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>


<?php elseif ($_tab === 'clans'): ?>
<!-- ════════════════════════════════════════════════════════
     CLANS — Classement annuel des clans
════════════════════════════════════════════════════════ -->
<?php
$clan_colors_map = ['bocage' => '#2a9d5c', 'littoral' => '#1a6fb8', 'marais' => '#8b6340'];
$clan_medals     = ['🥇', '🥈', '🥉'];
$cn_total_pts    = array_sum(array_column($clan_rankings, 'season_points'));
?>
<div class="cn-wrap">
  <!-- Barre classement clans -->
  <div class="cn-battle">
    <div class="cn-battle-inner">
      <div class="cn-battle-title">Le Classement des Clans</div>
      <div class="cn-battle-sub">Classement annuel — chaque participation fait avancer votre clan</div>
      <?php if (!empty($clan_rankings) && $cn_total_pts > 0): ?>
      <div class="cn-battle-bar">
        <?php foreach ($clan_rankings as $cr_seg):
          $c_col  = $clan_colors_map[$cr_seg['slug']] ?? ($cr_seg['color_primary'] ?? '#12314e');
          $c_pct  = round((int)$cr_seg['season_points'] / $cn_total_pts * 100);
        ?>
        <div class="cn-battle-seg" style="flex:<?= $c_pct ?>;background:<?= $c_col ?>"><?= $c_pct ?>%</div>
        <?php endforeach; ?>
      </div>
      <div class="cn-battle-labels" style="display:flex;gap:2px">
        <?php foreach ($clan_rankings as $cr_seg):
          $c_col  = $clan_colors_map[$cr_seg['slug']] ?? ($cr_seg['color_primary'] ?? '#12314e');
          $c_pct  = round((int)$cr_seg['season_points'] / $cn_total_pts * 100);
        ?>
        <div class="cn-battle-lbl" style="flex:<?= $c_pct ?>;color:<?= $c_col ?>"><?= e($cr_seg['emoji'] ?? '') ?> <?= number_format((int)$cr_seg['season_points']) ?> pts</div>
        <?php endforeach; ?>
      </div>
      <?php elseif (!empty($clan_rankings)): ?>
      <p style="color:rgba(255,255,255,.4);font-size:.85rem">Aucun point marqué encore — le QG vient d'ouvrir !</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="cn-content">
    <!-- Cartes des 3 clans -->
    <?php if (!empty($clan_rankings)): ?>
    <div class="cn-cards">
      <?php foreach ($clan_rankings as $cr_row):
        $c_bg    = $clan_colors_map[$cr_row['slug']] ?? ($cr_row['color_primary'] ?? '#12314e');
        $c_medal = $clan_medals[($cr_row['rank'] ?? 99) - 1] ?? '';
        $c_max   = $cn_total_pts > 0 ? max(1, (int)$clan_rankings[0]['season_points']) : 1;
        $c_pbar  = min(100, round((int)$cr_row['season_points'] / $c_max * 100));
      ?>
      <div class="cn-card">
        <div class="cn-card-hd" style="background:<?= $c_bg ?>">
          <span class="cn-card-rank-medal"><?= $c_medal ?></span>
          <div class="cn-card-emoji"><?= e($cr_row['emoji'] ?? '') ?></div>
          <div class="cn-card-name"><?= e($cr_row['name']) ?></div>
        </div>
        <div class="cn-card-bd">
          <div class="cn-card-pts" style="color:<?= $c_bg ?>"><?= number_format((int)$cr_row['season_points']) ?></div>
          <div class="cn-card-pts-lbl">points annuels</div>
          <div class="cn-card-members"><?= (int)$cr_row['active_members'] ?> membre<?= (int)$cr_row['active_members'] > 1 ? 's' : '' ?> actif<?= (int)$cr_row['active_members'] > 1 ? 's' : '' ?></div>
          <div class="cn-card-bar-track">
            <div class="cn-card-bar-fill xp-bar-fill" data-pct="<?= $c_pbar ?>" style="background:<?= $c_bg ?>;width:0"></div>
          </div>
          <a href="clan.php?slug=<?= e($cr_row['slug']) ?>" class="cn-card-link">Voir le clan →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;padding:40px;color:var(--text-muted,#6b7f96)">Aucune donnée de classement pour le moment.</p>
    <?php endif; ?>

    <!-- Activité récente des clans -->
    <?php if (!empty($clan_recent_logs)): ?>
    <div class="cn-section-title">Activité récente des clans</div>
    <div class="cn-activity">
      <?php foreach ($clan_recent_logs as $log_row):
        $log_color = $clan_colors_map[$log_row['clan_slug'] ?? ''] ?? '#12314e';
      ?>
      <div class="cn-log-item">
        <div class="cn-log-dot" style="background:<?= $log_color ?>"></div>
        <div class="cn-log-info">
          <div class="cn-log-clan" style="color:<?= $log_color ?>"><?= e($log_row['clan_name']) ?></div>
          <div class="cn-log-reason"><?= e($log_row['reason'] ?? '') ?><?= $log_row['pseudo'] ? ' · ' . e($log_row['pseudo']) : '' ?></div>
        </div>
        <div class="cn-log-pts" style="color:<?= $log_color ?>">+<?= (int)$log_row['points'] ?> pts</div>
        <div class="cn-log-date js-reltime" data-ts="<?= e($log_row['created_at'] ?? '') ?>"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Comment contribuer -->
    <div class="cn-section-title">Comment faire progresser ton clan</div>
    <div class="cn-howto">
      <div class="cn-howto-grid">
        <?php foreach ([
          ['+50 pts', 'Valider une mission',        'Chaque participation validée rapporte des points à ton clan pour l\'année.'],
          ['+30 pts', 'Terminer une randonnée',      'Les randos GPX validées comptent pour le total du clan.'],
          ['+40 pts', 'Participer à un KTC',           'Réponse retenue par le QG lors d\'un épisode Kétokole Tchè.'],
          ['+20 pts', 'Participer à un flash event', 'Les événements éclair boostent le clan.'],
        ] as [$pts, $lbl, $desc]): ?>
        <div class="cn-howto-card">
          <div class="cn-howto-pts"><?= $pts ?></div>
          <div class="cn-howto-label"><?= $lbl ?></div>
          <div class="cn-howto-desc"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:14px;font-size:.78rem;color:var(--text-muted,#6b7f96)">Le classement est annuel. Chaque participation validée compte — le podium final est révélé en fin d'année.</p>
    </div>
  </div>
</div>


<?php elseif ($_tab === 'classement'): ?>
<!-- ════════════════════════════════════════════════════════
     ZONAUTES — classement annuel
════════════════════════════════════════════════════════ -->
<div class="zo-wrap">
  <div class="zo-inner">

    <?php if ($is_logged && $my_rank && $my_rank > 50): ?>
    <div class="zo-my-rank">
      <span class="zo-my-rank-label">Votre position dans le classement global</span>
      <span class="zo-my-rank-num">#<?= $my_rank ?> sur <?= number_format($cl_total, 0, ',', ' ') ?></span>
    </div>
    <?php endif; ?>

    <!-- Filtres clan -->
    <div class="zo-filters">
      <a href="<?= comm_url('classement', ['clan' => 'all']) ?>" class="zo-filter-pill <?= $clan_filter === 'all' ? 'active' : '' ?>">Tous</a>
      <a href="<?= comm_url('classement', ['clan' => 'bocage']) ?>" class="zo-filter-pill bocage <?= $clan_filter === 'bocage' ? 'active' : '' ?>">🌳 Bocage</a>
      <a href="<?= comm_url('classement', ['clan' => 'littoral']) ?>" class="zo-filter-pill littoral <?= $clan_filter === 'littoral' ? 'active' : '' ?>">⚓ Littoral</a>
      <a href="<?= comm_url('classement', ['clan' => 'marais']) ?>" class="zo-filter-pill marais <?= $clan_filter === 'marais' ? 'active' : '' ?>">🌿 Marais</a>
    </div>

    <?php if (empty($leaderboard)): ?>
    <div class="zo-empty">
      <div style="font-size:3rem;margin-bottom:12px">🔭</div>
      <p style="font-size:1rem;font-weight:700;color:var(--navy-dark,#0c1e2e);margin-bottom:8px">Aucun Zonaute dans ce classement</p>
      <p>Rejoins la Zone et commence à gagner des XP pour apparaître ici !</p>
    </div>
    <?php else:
      $p1 = $leaderboard[0] ?? null;
      $p2 = $leaderboard[1] ?? null;
      $p3 = $leaderboard[2] ?? null;
      $pod_av = function(array $p, string $size_class, string $medal): string {
          $init = strtoupper(mb_substr($p['pseudo'], 0, 2));
          if ($p['avatar_type'] === 'upload' && !empty($p['avatar_file'])) {
              $src = htmlspecialchars(upload_url(ltrim($p['avatar_file'], '/')), ENT_QUOTES, 'UTF-8');
              $alt = htmlspecialchars($p['pseudo'], ENT_QUOTES, 'UTF-8');
              return "<img src=\"{$src}\" alt=\"{$alt}\" class=\"zo-pod-avatar {$size_class}\">";
          }
          return "<div class=\"zo-pod-avatar-init zo-pod-avatar {$size_class}\" style=\"font-size:.9rem\">{$init}</div>";
      };
    ?>

    <!-- Podium top 3 -->
    <?php if ($p1): ?>
    <div class="zo-podium">
      <?php if ($p2):
        $p2_chip = $chip_map[$p2['clan_slug'] ?? ''] ?? null;
      ?>
      <div class="zo-pod-card rank-2">
        <div class="zo-pod-avatar-wrap">
          <?= $pod_av($p2, 'rank-2-av', '🥈') ?>
          <span class="zo-pod-medal">🥈</span>
        </div>
        <div class="zo-pod-pseudo"><?= e($p2['pseudo']) ?></div>
        <?php if ($p2_chip): ?><div class="zo-pod-clan"><?= $p2_chip[1] ?></div><?php endif; ?>
        <div class="zo-pod-xp"><?= number_format($p2['xp_season'], 0, ',', ' ') ?> XP</div>
        <div class="zo-pod-plinth">2</div>
      </div>
      <?php endif; ?>

      <?php
        $p1_chip = $chip_map[$p1['clan_slug'] ?? ''] ?? null;
      ?>
      <div class="zo-pod-card rank-1">
        <div class="zo-pod-crown">👑</div>
        <div class="zo-pod-avatar-wrap">
          <?= $pod_av($p1, 'rank-1-av', '🥇') ?>
          <span class="zo-pod-medal">🥇</span>
        </div>
        <div class="zo-pod-pseudo"><?= e($p1['pseudo']) ?></div>
        <?php if ($p1_chip): ?><div class="zo-pod-clan"><?= $p1_chip[1] ?></div><?php endif; ?>
        <div class="zo-pod-xp"><?= number_format($p1['xp_season'], 0, ',', ' ') ?> XP</div>
        <div class="zo-pod-plinth">1</div>
      </div>

      <?php if ($p3):
        $p3_chip = $chip_map[$p3['clan_slug'] ?? ''] ?? null;
      ?>
      <div class="zo-pod-card rank-3">
        <div class="zo-pod-avatar-wrap">
          <?= $pod_av($p3, 'rank-3-av', '🥉') ?>
          <span class="zo-pod-medal">🥉</span>
        </div>
        <div class="zo-pod-pseudo"><?= e($p3['pseudo']) ?></div>
        <?php if ($p3_chip): ?><div class="zo-pod-clan"><?= $p3_chip[1] ?></div><?php endif; ?>
        <div class="zo-pod-xp"><?= number_format($p3['xp_season'], 0, ',', ' ') ?> XP</div>
        <div class="zo-pod-plinth">3</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <p style="font-size:.82rem;color:var(--text-muted,#6b7f96);margin-bottom:20px">Top <?= count($leaderboard) ?> sur <?= number_format($cl_total, 0, ',', ' ') ?> Zonaute<?= $cl_total > 1 ? 's' : '' ?> — classement annuel par XP</p>

    <!-- Table rang 4–50 -->
    <?php $table_rows = array_slice($leaderboard, 3); ?>
    <?php if (!empty($table_rows)): ?>
    <div class="zo-table-wrap">
      <table class="zo-table">
        <thead>
          <tr>
            <th style="width:48px">#</th>
            <th>Zonaute</th>
            <th class="hide-sm">Clan</th>
            <th class="hide-sm">Niveau</th>
            <th class="num hide-sm">Missions</th>
            <th class="num">XP annuel</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($table_rows as $row):
            $is_me    = $is_logged && (int)$row['id'] === (int)($active_user['id'] ?? 0);
            $tr_chip  = $chip_map[$row['clan_slug'] ?? ''] ?? null;
            $lvl_name = get_level_name($row['level']);
          ?>
          <tr class="<?= $is_me ? 'me' : '' ?>">
            <td><span class="zo-rank-num"><?= $row['rank'] ?></span></td>
            <td>
              <div class="zo-avatar-cell">
                <?php if ($row['avatar_type'] === 'upload' && !empty($row['avatar_file'])): ?>
                <img src="<?= e(upload_url(ltrim($row['avatar_file'], '/'))) ?>" alt="<?= e($row['pseudo']) ?>" class="zo-avatar">
                <?php else: ?>
                <div class="zo-avatar-init"><?= strtoupper(mb_substr($row['pseudo'], 0, 2)) ?></div>
                <?php endif; ?>
                <div>
                  <div class="zo-pseudo"><?= e($row['pseudo']) ?><?php if ($is_me): ?><span class="zo-me-badge">toi</span><?php endif; ?></div>
                </div>
              </div>
            </td>
            <td class="hide-sm"><?php if ($tr_chip): ?><span class="zo-clan-chip <?= $tr_chip[0] ?>"><?= $tr_chip[1] ?></span><?php else: ?><span style="color:var(--text-muted,#6b7f96);font-size:.8rem">—</span><?php endif; ?></td>
            <td class="hide-sm"><span class="zo-level">Niv. <?= $row['level'] ?> — <?= e($lvl_name) ?></span></td>
            <td class="num hide-sm" style="font-size:.85rem;font-weight:700;color:var(--text-mid,#3d5166)"><?= $row['missions_count'] ?></td>
            <td>
              <div class="zo-xp"><?= number_format($row['xp_season'], 0, ',', ' ') ?></div>
              <div class="zo-xp-sub"><?= number_format($row['xp_total'], 0, ',', ' ') ?> total</div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if (!$is_logged): ?>
    <div style="text-align:center;margin-top:28px">
      <a href="inscription.php" style="display:inline-block;padding:12px 28px;background:var(--primary,#ea5649);color:#fff;border-radius:50px;font-weight:800;text-decoration:none;font-size:.92rem">Rejoindre la Zone →</a>
      <p style="margin-top:12px;font-size:.82rem;color:var(--text-muted,#6b7f96)">Inscris-toi pour apparaître dans le classement.</p>
    </div>
    <?php endif; ?>

    <?php endif; // leaderboard ?>
  </div>
</div>

<?php elseif ($_tab === 'recompenses'): ?>
<div class="comm-inner" style="max-width:860px;margin:0 auto;padding:36px 24px 64px">

  <?php
  $rw_xp_rows = [
    ['🎉', 'Inscription sur Zone85',           '+50 XP',         'Une fois'],
    ['🎯', 'Valider une mission',               '+20 à +150 XP',  'Par mission'],
    ['🥾', 'Terminer une randonnée',            '+25 XP',         'Par rando'],
    ['🔍', 'Proposer pour un KTC',              '+10 XP',         'Par épisode'],
    ['🗳', 'Voter pour un KTC',                 '+5 XP',          'Par épisode'],
    ['🏆', 'Être retenu par le QG',              '+50 à +200 XP',  'Par épisode KTC'],
    ['✍️', 'Commenter Les Échos',               '+5 XP',          'Par article'],
    ['🏅', 'Débloquer un badge',                'Variable',        'Selon badge'],
  ];
  $rw_clan_rows = [
    ['🎯', 'Valider une mission',           '+50 pts'],
    ['🥾', 'Terminer une randonnée',        '+30 pts'],
    ['🏆', 'Participer à un KTC',            '+40 pts'],
    ['⚡', 'Participer à un flash event',  '+20 pts'],
  ];
  $rw_rarity_labels = [
    'legendary' => ['Légendaire', '#f59e0b'],
    'epic'      => ['Épique',     '#7c3aed'],
    'rare'      => ['Rare',       '#2563eb'],
    'uncommon'  => ['Peu commun', '#059669'],
    'common'    => ['Commun',     '#6b7280'],
  ];
  $rw_rarity_order = ['legendary','epic','rare','uncommon','common'];
  $rw_badges_grouped = [];
  foreach ($rw_rarity_order as $r) $rw_badges_grouped[$r] = [];
  if ($rw_badges_db) {
    foreach ($rw_badges_db as $b) {
      $r = $b['rarity'] ?? 'common';
      if (!isset($rw_badges_grouped[$r])) $r = 'common';
      $rw_badges_grouped[$r][] = $b;
    }
  }
  $rw_my_xp  = $is_logged ? (int)$active_user['xp_total'] : 0;
  $rw_my_lvl = $is_logged ? (int)$active_user['level']    : 0;
  ?>

  <!-- XP -->
  <div style="margin-bottom:36px">
    <h2 style="font-size:1.05rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:4px">🎯 Comment gagner des XP</h2>
    <p style="font-size:.82rem;color:var(--text-muted,#6b7f96);margin-bottom:16px;line-height:1.5">Chaque action compte et s'accumule définitivement sur ton profil.</p>
    <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06)">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:var(--beige,#f8f4ef)">
            <th style="font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted,#6b7f96);padding:10px 14px;text-align:left;border-bottom:1px solid var(--beige-dark,#e8e0d4)">Action</th>
            <th style="font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted,#6b7f96);padding:10px 14px;text-align:left;border-bottom:1px solid var(--beige-dark,#e8e0d4)">XP</th>
            <th style="font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted,#6b7f96);padding:10px 14px;text-align:left;border-bottom:1px solid var(--beige-dark,#e8e0d4)">Fréquence</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rw_xp_rows as [$ico, $label, $xp, $freq]): ?>
          <tr>
            <td style="padding:11px 14px;font-size:.85rem;color:var(--text-mid,#3d5166);border-bottom:1px solid var(--beige-dark,#e8e0d4)"><?= $ico ?> <?= e($label) ?></td>
            <td style="padding:11px 14px;border-bottom:1px solid var(--beige-dark,#e8e0d4)"><span style="background:rgba(234,86,73,.1);color:var(--primary,#ea5649);border-radius:20px;padding:2px 9px;font-size:.75rem;font-weight:800"><?= e($xp) ?></span></td>
            <td style="padding:11px 14px;border-bottom:1px solid var(--beige-dark,#e8e0d4)"><span style="background:var(--beige,#f8f4ef);color:var(--text-muted,#6b7f96);border-radius:20px;padding:2px 8px;font-size:.7rem;font-weight:600"><?= e($freq) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Clan points + Niveaux côte à côte -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:36px">
    <!-- Clan points -->
    <div>
      <h2 style="font-size:1.05rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:4px">🛡 Points de clan</h2>
      <p style="font-size:.78rem;color:var(--text-muted,#6b7f96);margin-bottom:12px">Classement annuel — chaque participation compte</p>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($rw_clan_rows as [$ico, $label, $pts]): ?>
        <div style="background:#fff;border-radius:10px;padding:12px 14px;border:1.5px solid var(--beige-dark,#e8e0d4);display:flex;align-items:center;gap:10px">
          <span style="font-size:1.2rem"><?= $ico ?></span>
          <span style="flex:1;font-size:.82rem;color:var(--text-mid,#3d5166);font-weight:600"><?= e($label) ?></span>
          <strong style="font-size:.95rem;color:var(--navy-dark,#0c1e2e)"><?= e($pts) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Niveaux -->
    <div>
      <h2 style="font-size:1.05rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:4px">⬆️ Les niveaux</h2>
      <p style="font-size:.78rem;color:var(--text-muted,#6b7f96);margin-bottom:12px">De Novice à Immortel</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
        <?php foreach ($rw_levels as $lv): ?>
          <?php $is_cur = $is_logged && $rw_my_lvl === $lv['level']; ?>
          <div style="background:#fff;border-radius:8px;padding:8px 10px;border:<?= $is_cur ? '2px solid var(--primary,#ea5649)' : '1.5px solid var(--beige-dark,#e8e0d4)' ?>;<?= $is_cur ? 'background:rgba(234,86,73,.03)' : '' ?>">
            <div style="font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted,#6b7f96)">Niv. <?= $lv['level'] ?></div>
            <div style="font-size:.8rem;font-weight:800;color:var(--navy-dark,#0c1e2e)"><?= e($lv['name']) ?></div>
            <div style="font-size:.68rem;color:var(--text-muted,#6b7f96)"><?= $lv['threshold'] === 0 ? 'Dès l\'inscription' : number_format($lv['threshold']).' XP' ?></div>
            <?php if ($is_cur): ?><span style="font-size:.58rem;font-weight:800;background:var(--primary,#ea5649);color:#fff;border-radius:20px;padding:1px 6px;display:inline-block;margin-top:2px">Ton niveau</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Badges -->
  <div>
    <h2 style="font-size:1.05rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:4px">🏅 Les badges</h2>
    <p style="font-size:.82rem;color:var(--text-muted,#6b7f96);margin-bottom:16px">
      Récompenses permanentes, visibles sur ton profil.
      <?php if ($is_logged): ?><strong><?= count($rw_my_badges) ?> débloqué<?= count($rw_my_badges) > 1 ? 's' : '' ?></strong> sur <?= $rw_badges_db ? count($rw_badges_db) : '?' ?>.<?php endif; ?>
    </p>

    <?php if ($rw_badges_db): ?>
      <?php foreach ($rw_rarity_order as $rar): ?>
        <?php if (empty($rw_badges_grouped[$rar])) continue; ?>
        <?php [$rl, $rc] = $rw_rarity_labels[$rar]; ?>
        <div style="margin-bottom:20px">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= e($rc) ?>;flex-shrink:0;display:inline-block"></span>
            <span style="font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:<?= e($rc) ?>"><?= e($rl) ?></span>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($rw_badges_grouped[$rar] as $b): ?>
              <?php $got = isset($rw_my_badges[$b['id']]); ?>
              <?php
                $ct2 = $b['condition_type'] ?? '';
                $cv2 = (int)($b['condition_value'] ?? 0);
                [$how2_type, $how2_label, $how2_text] = match($ct2) {
                  'xp_threshold'    => ['auto',   '⚡', 'Atteindre '.number_format($cv2).' XP'],
                  'mission_success' => ['auto',   '⚡', $cv2.' mission'.($cv2>1?'s':'').' validée'.($cv2>1?'s':'')],
                  'missions_count'  => ['auto',   '⚡', $cv2.' mission'.($cv2>1?'s':'').' validée'.($cv2>1?'s':'')],
                  'rando_validated' => ['auto',   '⚡', $cv2.' rando'.($cv2>1?'s':'').' terminée'.($cv2>1?'s':'')],
                  'randos_count'    => ['auto',   '⚡', $cv2.' rando'.($cv2>1?'s':'').' terminée'.($cv2>1?'s':'')],
                  'registration'    => ['auto',   '⚡', 'À l\'inscription'],
                  'season'          => ['auto',   '⚡', 'Participer à un temps fort du QG'],
                  'mission_reward'  => ['reward', '🎯', 'Récompense de mission'],
                  'manual'          => ['manual', '👤', 'Attribué par l\'équipe'],
                  'special'         => ['special','✨', 'Condition spéciale'],
                  default           => ['auto',   '⚡', $ct2 ?: '—'],
                };
                $how2_fg = match($how2_type) {
                  'auto'    => '#166534',
                  'manual'  => '#854d0e',
                  'reward'  => '#1e40af',
                  'special' => '#6b21a8',
                  default   => '#475569',
                };
              ?>
              <div style="display:flex;flex-direction:column;gap:4px;background:#fff;border:<?= $got ? '1.5px solid var(--primary,#ea5649)' : '1.5px solid var(--beige-dark,#e8e0d4)' ?>;border-radius:9px;padding:8px 11px;opacity:<?= $got ? '1' : '.55' ?>;min-width:160px">
                <div style="display:flex;align-items:center;gap:7px">
                  <span style="font-size:1.2rem;line-height:1"><?= e($b['icon_emoji'] ?? '🏅') ?></span>
                  <div style="font-size:.78rem;font-weight:700;color:var(--navy-dark,#0c1e2e);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($b['title']) ?></div>
                  <?php if ($got): ?><span style="font-size:.65rem;color:var(--primary,#ea5649);font-weight:800;flex-shrink:0">✓</span><?php endif; ?>
                </div>
                <span style="font-size:.62rem;font-weight:700;color:<?= e($how2_fg) ?>"><?= e($how2_label) ?> <?= e($how2_text) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align:center;padding:32px;color:var(--text-muted,#6b7f96);font-size:.88rem">Les badges arrivent bientôt.</p>
    <?php endif; ?>

    <p style="font-size:.78rem;color:var(--text-muted,#6b7f96);margin-top:20px;line-height:1.6">
      XP acquis définitivement · Points clan dans le classement annuel · Certains badges non cumulables.
    </p>
  </div>

</div><!-- /.comm-inner recompenses -->

<?php endif; // tab ?>
</div><!-- /.comm-content -->

<?php
$page_scripts = '<script>
(function(){
  // ── Temps relatif ────────────────────────────────────────
  function relTime(dateStr) {
    if (!dateStr) return "";
    var d = new Date(dateStr.replace(" ", "T"));
    if (isNaN(d)) return "";
    var diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60)  return "à l\'instant";
    if (diff < 3600) return Math.floor(diff/60) + " min";
    if (diff < 86400) return Math.floor(diff/3600) + " h";
    if (diff < 604800) return Math.floor(diff/86400) + " j";
    return d.toLocaleDateString("fr-FR", {day:"numeric", month:"short"});
  }
  document.querySelectorAll(".js-reltime[data-ts]").forEach(function(el){
    el.textContent = relTime(el.dataset.ts);
  });

  // ── Animation barres XP ──────────────────────────────────
  function animBar(el) {
    var target = parseFloat(el.dataset.pct) || 0;
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var prog = Math.min((ts - start) / 900, 1);
      var ease = 1 - Math.pow(1 - prog, 3);
      el.style.width = (ease * target) + "%";
      if (prog < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  document.querySelectorAll(".xp-bar-fill[data-pct]").forEach(animBar);

  // ── Compteurs animés ─────────────────────────────────────
  function animCounter(el) {
    var target = parseInt(el.dataset.val, 10) || 0;
    if (target === 0) { el.textContent = "0"; return; }
    var start = null;
    var duration = 1200;
    function step(ts) {
      if (!start) start = ts;
      var prog = Math.min((ts - start) / duration, 1);
      var ease = 1 - Math.pow(1 - prog, 3);
      el.textContent = Math.round(ease * target).toLocaleString("fr-FR");
      if (prog < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  document.querySelectorAll(".js-counter[data-val]").forEach(animCounter);
})();
</script>';

render_hidden_collectibles('communaute');
require_once 'includes/footer.php';
?>
