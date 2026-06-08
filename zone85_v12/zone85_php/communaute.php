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
$_tab = in_array($_GET['tab'] ?? '', ['passeport', 'fil', 'clans', 'classement', 'zonautes'], true)
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

// ── URL helpers ──────────────────────────────────────────────
function comm_url(string $tab, array $extra = []): string {
    $p = array_filter(array_merge(['tab' => $tab], $extra), fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== 'all');
    $qs = http_build_query($p);
    return 'communaute.php' . ($qs ? '?'.$qs : '');
}

// ─────────────────────────────────────────────────────────────
$page_styles = '<style>

/* ── Header communauté ── */
.comm-hero{background:#fff;border-bottom:1.5px solid var(--beige-dark,#e8e0d4);padding:28px 0 0}
.comm-hero-eyebrow{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px}
.comm-hero-title{font-size:1.5rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin:0 0 4px;line-height:1.2}
.comm-hero-sub{font-size:.88rem;color:var(--text-muted);margin:0 0 4px}

/* ── Onglet nav (fond clair) ── */
.comm-tabs{display:flex;gap:0;margin-top:20px;border-bottom:2px solid rgba(18,49,78,.1);overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.comm-tabs::-webkit-scrollbar{display:none}
.comm-tab{display:inline-flex;align-items:center;gap:7px;padding:12px 22px;font-size:.9rem;font-weight:700;color:var(--text-muted,#6b7f96);text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;white-space:nowrap}
.comm-tab:hover{color:var(--navy-dark,#0c1e2e)}
.comm-tab.active{color:var(--primary,#ea5649);border-bottom-color:var(--primary,#ea5649)}
.comm-tab-count{font-size:.68rem;background:rgba(18,49,78,.08);color:var(--text-mid,#4a5568);padding:2px 7px;border-radius:10px;font-weight:700}
.comm-tab.active .comm-tab-count{background:rgba(234,86,73,.12);color:var(--primary,#ea5649)}

/* ── PASSEPORT page ── */
.pp-page-wrap{background:var(--beige);padding:40px 0 64px}
.pp-page-inner{max-width:860px;margin:0 auto;padding:0 24px;display:grid;grid-template-columns:340px 1fr;gap:28px;align-items:start}
@media(max-width:760px){.pp-page-inner{grid-template-columns:1fr}}
.pp-card{background:#fff;border-radius:20px;border:1.5px solid var(--beige-dark);overflow:hidden;box-shadow:0 4px 20px rgba(18,49,78,.08)}
.pp-card-header{background:linear-gradient(135deg,#0a1a2e,#163756);padding:28px 24px 20px;display:flex;align-items:center;gap:18px}
.pp-card-avatar{width:72px;height:72px;border-radius:50%;border:3px solid rgba(255,255,255,.2);overflow:hidden;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:900;color:#fff;flex-shrink:0}
.pp-card-avatar img{width:100%;height:100%;object-fit:cover}
.pp-card-info{flex:1;min-width:0}
.pp-card-zone-label{font-size:.6rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:3px}
.pp-card-pseudo{font-size:1.15rem;font-weight:900;color:#fff;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pp-card-clan{font-size:.8rem;color:rgba(255,255,255,.55)}
.pp-card-body{padding:20px 24px}
.pp-card-level{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.pp-card-level-badge{background:#ea5649;color:#fff;border-radius:20px;padding:5px 14px;font-size:.82rem;font-weight:800}
.pp-card-level-name{font-size:.88rem;color:var(--text-mid);font-weight:600}
.pp-xp-bar-wrap{margin-bottom:18px}
.pp-xp-bar-label{display:flex;justify-content:space-between;font-size:.72rem;font-weight:700;color:var(--text-muted);margin-bottom:5px}
.pp-xp-bar-track{height:8px;background:var(--beige-dark);border-radius:4px;overflow:hidden}
.pp-xp-bar-fill{height:100%;background:linear-gradient(90deg,#ea5649,#f0856d);border-radius:4px;transition:width .6s cubic-bezier(.22,1,.36,1)}
.pp-stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.pp-stat-box{background:var(--beige);border-radius:10px;padding:10px 12px;text-align:center}
.pp-stat-box-val{font-size:1.1rem;font-weight:900;color:var(--navy-dark)}
.pp-stat-box-lbl{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-top:2px}
.pp-badges-section{border-top:1px solid var(--beige-dark);padding-top:14px}
.pp-badges-kicker{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.pp-badges-list{display:flex;gap:6px;flex-wrap:wrap}
.pp-badge-pill{padding:4px 10px;border-radius:8px;background:var(--beige);font-size:.78rem;font-weight:700;color:var(--text-mid)}
.pp-badge-pill.rarity-legendary{background:#fef3c7;color:#92400e}
.pp-badge-pill.rarity-epic{background:#f3e8ff;color:#6b21a8}
.pp-badge-pill.rarity-rare{background:#dbeafe;color:#1e40af}
.pp-badge-pill.rarity-uncommon{background:#d1fae5;color:#065f46}
.pp-side-col{display:flex;flex-direction:column;gap:20px}
.pp-xp-log{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark);padding:20px 22px}
.pp-xp-log-title{font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px}
.pp-xp-log-item{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--beige-dark)}
.pp-xp-log-item:last-child{border-bottom:none;padding-bottom:0}
.pp-xp-log-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0}
.pp-xp-log-reason{flex:1;font-size:.82rem;color:var(--text-mid);font-weight:600}
.pp-xp-log-amount{font-size:.88rem;font-weight:900;color:var(--primary);white-space:nowrap}
.pp-xp-log-date{font-size:.68rem;color:var(--text-muted);white-space:nowrap}
.pp-howto{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark);padding:20px 22px}
.pp-howto-title{font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:12px}
.pp-howto-item{display:flex;align-items:flex-start;gap:10px;padding:7px 0;border-bottom:1px solid var(--beige-dark)}
.pp-howto-item:last-child{border-bottom:none}
.pp-howto-xp{min-width:56px;font-size:.82rem;font-weight:900;color:var(--primary)}
.pp-howto-label{font-size:.82rem;color:var(--text-mid)}
.pp-not-logged{background:#fff;border-radius:20px;border:1.5px solid var(--beige-dark);padding:48px 32px;text-align:center;max-width:480px;margin:40px auto}

/* ── CLANS ── */
.cn-wrap{background:var(--beige);padding:40px 0 64px}
.cn-grid{max-width:900px;margin:0 auto 36px;padding:0 24px;display:flex;flex-direction:column;gap:16px}
.cn-card{background:#fff;border-radius:18px;border:1.5px solid var(--beige-dark);padding:0;overflow:hidden;box-shadow:0 2px 12px rgba(18,49,78,.06);transition:box-shadow .15s}
.cn-card:hover{box-shadow:0 6px 24px rgba(18,49,78,.12)}
.cn-card-inner{display:flex;align-items:center;gap:0}
.cn-card-stripe{width:6px;min-height:80px;flex-shrink:0}
.cn-card-body{flex:1;padding:18px 22px;display:flex;align-items:center;gap:20px}
.cn-card-rank{font-size:1.8rem;font-weight:900;min-width:38px;text-align:center}
.cn-card-info{flex:1}
.cn-card-name{font-size:1.05rem;font-weight:900;color:var(--navy-dark);margin-bottom:3px}
.cn-card-members{font-size:.78rem;color:var(--text-muted)}
.cn-card-pts{text-align:right}
.cn-card-pts-val{font-size:1.4rem;font-weight:900}
.cn-card-pts-lbl{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted)}
.cn-section{max-width:900px;margin:0 auto;padding:0 24px}
.cn-section-title{font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px}
.cn-log{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark);padding:8px 0;margin-bottom:28px}
.cn-log-item{display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid var(--beige-dark)}
.cn-log-item:last-child{border-bottom:none}
.cn-log-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.cn-log-info{flex:1;min-width:0}
.cn-log-clan{font-size:.82rem;font-weight:800;color:var(--navy-dark)}
.cn-log-reason{font-size:.78rem;color:var(--text-muted);margin-top:1px}
.cn-log-pts{font-size:.88rem;font-weight:900;white-space:nowrap}
.cn-log-date{font-size:.68rem;color:var(--text-muted);white-space:nowrap}
.cn-howto{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark);padding:20px 24px}
.cn-howto-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-top:12px}
.cn-howto-card{background:var(--beige);border-radius:12px;padding:14px 16px}
.cn-howto-card-pts{font-size:1rem;font-weight:900;color:var(--primary);margin-bottom:4px}
.cn-howto-card-label{font-size:.8rem;font-weight:700;color:var(--navy-dark);margin-bottom:3px}
.cn-howto-card-desc{font-size:.75rem;color:var(--text-muted);line-height:1.4}

/* ── CLASSEMENT ZONAUTES ── */
.cl-wrap{padding:40px 0 64px;background:var(--beige)}
.cl-inner{max-width:900px;margin:0 auto;padding:0 24px}
.cl-howto{background:#fff;border-radius:16px;border:1.5px solid var(--beige-dark);padding:20px 24px;margin-top:28px}
.cl-howto-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin-top:12px}
.cl-howto-card{background:var(--beige);border-radius:10px;padding:12px 14px}
.cl-howto-card-xp{font-size:.95rem;font-weight:900;color:var(--primary);margin-bottom:3px}
.cl-howto-card-label{font-size:.8rem;font-weight:700;color:var(--navy-dark);margin-bottom:2px}
.cl-howto-card-desc{font-size:.72rem;color:var(--text-muted);line-height:1.4}

/* ── FIL ── */
.cf-wrap{padding:48px 0 64px;background:var(--beige)}
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
.cf-pinned-badge{background:rgba(234,86,73,.1);color:var(--primary);border:1px solid rgba(234,86,73,.25);border-radius:10px;padding:1px 7px;font-size:.67rem;font-weight:800}
.cf-pagination{display:flex;gap:8px;justify-content:center;margin-top:32px;flex-wrap:wrap}
.cf-page-btn{padding:7px 14px;border-radius:6px;font-size:.82rem;font-weight:700;text-decoration:none;border:1.5px solid var(--beige-dark);color:var(--text-mid);background:#fff;transition:all .15s}
.cf-page-btn:hover{border-color:var(--primary);color:var(--primary)}
.cf-page-btn.active{background:var(--primary);border-color:var(--primary);color:#fff}
.cf-empty{text-align:center;padding:56px 24px;background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark)}

/* ── CLASSEMENT ── */
.cl-wrap{padding:48px 0 64px;background:var(--beige)}
.cl-hero-stats{display:flex;gap:32px;flex-wrap:wrap;margin-top:10px}
.cl-stat-num{font-size:1.6rem;font-weight:900;color:var(--primary);line-height:1}
.cl-stat-label{font-size:.68rem;font-weight:600;color:var(--text-muted);letter-spacing:.08em;text-transform:uppercase;margin-top:3px}
.cl-clan-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}
.cl-clan-tab{padding:7px 18px;border-radius:20px;font-size:.82rem;font-weight:700;text-decoration:none;border:2px solid var(--beige-dark);color:var(--text-mid);transition:all .15s;background:#fff}
.cl-clan-tab:hover{border-color:var(--primary);color:var(--primary)}
.cl-clan-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.cl-podium{display:flex;justify-content:center;align-items:flex-end;gap:12px;margin-bottom:36px}
.cl-podium-item{display:flex;flex-direction:column;align-items:center;gap:8px}
.cl-pod-avatar{border-radius:50%;object-fit:cover;border:3px solid gold}
.cl-pod-avatar-wrap{position:relative;display:flex;justify-content:center;align-items:center}
.cl-pod-medal{position:absolute;bottom:-4px;right:-4px;font-size:1.1rem;line-height:1}
.cl-pod-1 .cl-pod-avatar{width:80px;height:80px;border-color:gold}
.cl-pod-2 .cl-pod-avatar{width:64px;height:64px;border-color:silver}
.cl-pod-3 .cl-pod-avatar{width:56px;height:56px;border-color:#cd7f32}
.cl-pod-emoji{font-size:2rem}
.cl-pod-1 .cl-pod-emoji{font-size:2.5rem}
.cl-pod-3 .cl-pod-emoji{font-size:1.8rem}
.cl-pod-pseudo{font-size:.82rem;font-weight:800;color:var(--navy-dark);text-align:center;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cl-pod-xp{font-size:.72rem;font-weight:700;color:var(--primary)}
.cl-pod-plinth{border-radius:6px 6px 0 0;width:100%}
.cl-pod-1 .cl-pod-plinth{background:#ffd700;height:72px;min-width:80px}
.cl-pod-2 .cl-pod-plinth{background:#c0c0c0;height:52px;min-width:72px}
.cl-pod-3 .cl-pod-plinth{background:#cd7f32;height:38px;min-width:64px}
.cl-pod-rank{font-size:1rem;font-weight:900;color:#fff;text-align:center;padding-top:8px}
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
.cl-my-rank{background:#fff;border-radius:var(--radius);border:2px solid var(--primary);padding:14px 20px;display:flex;align-items:center;gap:14px;margin-bottom:24px}
.cl-my-rank-label{font-size:.78rem;font-weight:600;color:var(--text-muted);flex:1}
.cl-my-rank-num{font-size:1.5rem;font-weight:900;color:var(--primary)}
.cl-total-label{font-size:.82rem;color:var(--text-muted);margin-bottom:16px}

/* ── ZONAUTES ── */
.zo-wrap{background:#f7f5f2;min-height:500px;padding:0 0 64px}
.zo-filters{background:#fff;border-bottom:1px solid rgba(18,49,78,.08);position:sticky;top:68px;z-index:90}
.zo-filters-inner{display:flex;align-items:center;gap:8px;max-width:1160px;margin:0 auto;padding:12px 24px;overflow-x:auto;scrollbar-width:none}
.zo-filters-inner::-webkit-scrollbar{display:none}
.zo-filter-btn{padding:7px 18px;border-radius:20px;border:1px solid #e5e7eb;background:transparent;font-size:.82rem;font-weight:700;color:#6b7280;cursor:pointer;font-family:inherit;white-space:nowrap;transition:all .18s;text-decoration:none}
.zo-filter-btn:hover{border-color:#12314e;color:#12314e}
.zo-filter-btn.active{background:#12314e;border-color:#12314e;color:#fff}
.zo-filter-btn.bocage-active{background:#2a9d5c;border-color:#2a9d5c;color:#fff}
.zo-filter-btn.littoral-active{background:#1a6fb8;border-color:#1a6fb8;color:#fff}
.zo-filter-btn.marais-active{background:#8b6340;border-color:#8b6340;color:#fff}
.zo-sort-toggle{margin-left:auto;padding:7px 18px;border-radius:20px;border:1px dashed #d1d5db;background:transparent;font-size:.78rem;font-weight:700;color:#9ca3af;cursor:pointer;font-family:inherit;white-space:nowrap;transition:all .18s;text-decoration:none}
.zo-sort-toggle.active{border-style:solid;border-color:#f59e0b;color:#b45309;background:#fffbeb}
.zo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;max-width:1160px;margin:32px auto 0;padding:0 24px}
@media(max-width:600px){.zo-grid{grid-template-columns:repeat(2,1fr);gap:12px}}
.player-card{background:#fff;border-radius:14px;border:1px solid rgba(18,49,78,.07);padding:20px 16px;text-align:center;cursor:pointer;transition:all .2s;position:relative;overflow:hidden;box-shadow:0 1px 4px rgba(18,49,78,.05)}
.player-card:hover{transform:translateY(-4px);box-shadow:0 10px 30px rgba(18,49,78,.12);border-color:rgba(18,49,78,.2)}
.pc-rank-badge{position:absolute;top:10px;left:10px;width:24px;height:24px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:900;color:#6b7280}
.pc-rank-badge.rank-1{background:#f6c90e;color:#7a5a00}
.pc-rank-badge.rank-2{background:#c0c0c0;color:#444}
.pc-rank-badge.rank-3{background:#cd7f32;color:#fff}
.pc-avatar{width:64px;height:64px;border-radius:50%;margin:0 auto 10px;overflow:hidden;background:#f0ece7;display:flex;align-items:center;justify-content:center;font-size:1.8rem}
.pc-avatar img{width:100%;height:100%;object-fit:cover}
.pc-pseudo{font-size:.92rem;font-weight:800;color:#1f2937;margin-bottom:6px}
.pc-clan-chip{display:inline-block;padding:3px 10px;border-radius:10px;font-size:.68rem;font-weight:700;margin-bottom:8px}
.pc-level-badge{font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:4px}
.pc-xp{font-size:.88rem;font-weight:800;color:#0d1e2c}
.pc-xp small{font-weight:600;font-size:.72rem;color:#9ca3af}
.zo-voir-plus{max-width:1160px;margin:28px auto 0;padding:0 24px;text-align:center}
.zo-empty{text-align:center;padding:64px 24px;font-size:.95rem;color:#9ca3af}
.bocage-chip-sm{background:#e8f5ee;color:#1a5c38}
.littoral-chip-sm{background:#e8f0fb;color:#154f8b}
.marais-chip-sm{background:#f5ede4;color:#6b4c2a}

/* ── Passeport modal ── */
.passport-overlay{position:fixed;inset:0;z-index:9000;background:rgba(6,16,26,.8);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity .25s}
.passport-overlay.open{opacity:1;pointer-events:all}
.passport-modal{background:#fff;border-radius:20px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 30px 80px rgba(0,0,0,.5);transform:translateY(20px);transition:transform .3s cubic-bezier(.22,1,.36,1)}
.passport-overlay.open .passport-modal{transform:translateY(0)}
.pp-header{background:linear-gradient(135deg,#0a1a2e,#163756);border-radius:20px 20px 0 0;padding:28px 28px 20px;display:flex;align-items:center;gap:18px;position:relative}
.pp-close{position:absolute;top:14px;right:16px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.12);border:none;cursor:pointer;color:#fff;font-size:1.1rem;display:flex;align-items:center;justify-content:center;transition:background .2s}
.pp-close:hover{background:rgba(255,255,255,.25)}
.pp-logo{font-size:.6rem;font-weight:900;letter-spacing:.18em;color:rgba(255,255,255,.35);text-transform:uppercase;margin-bottom:4px}
.pp-id-label{font-size:.65rem;font-weight:700;letter-spacing:.14em;color:rgba(255,255,255,.4);text-transform:uppercase;margin-bottom:2px}
.pp-avatar{width:76px;height:76px;border-radius:50%;border:3px solid rgba(255,255,255,.2);overflow:hidden;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;font-size:2.2rem;flex-shrink:0}
.pp-avatar img{width:100%;height:100%;object-fit:cover}
.pp-info{flex:1}
.pp-pseudo{font-size:1.2rem;font-weight:900;color:#fff;margin-bottom:4px}
.pp-clan-line{font-size:.82rem;color:rgba(255,255,255,.55)}
.pp-body{padding:22px 28px 28px}
.pp-member-since{font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ca3af;margin-bottom:18px}
.pp-stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:22px}
.pp-stat{background:#f7f5f2;border-radius:10px;padding:12px 14px}
.pp-stat-val{display:block;font-size:1.15rem;font-weight:900;color:#0d1e2c}
.pp-stat-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-top:2px}
.pp-level-line{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.pp-level-badge{padding:5px 14px;border-radius:20px;background:#12314e;color:#fff;font-size:.82rem;font-weight:800}
.pp-level-name{font-size:.88rem;color:#374151;font-weight:600}
.pp-badges-title{font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;margin-bottom:10px;border-top:1px solid #f3f4f6;padding-top:16px}
.pp-badges-row{display:flex;gap:8px;flex-wrap:wrap}
.pp-badge-chip{padding:5px 12px;border-radius:10px;background:#f3f4f6;font-size:.8rem;font-weight:700;color:#374151}
.pp-badge-chip.rarity-legendary{background:#fef3c7;color:#92400e}
.pp-badge-chip.rarity-epic{background:#f3e8ff;color:#6b21a8}
.pp-badge-chip.rarity-rare{background:#dbeafe;color:#1e40af}
.pp-badge-chip.rarity-uncommon{background:#d1fae5;color:#065f46}
.pp-no-badges{font-size:.85rem;color:#9ca3af;font-style:italic}
.pp-loading{text-align:center;padding:48px;color:#9ca3af;font-size:.9rem}
.spinner{width:32px;height:32px;border-radius:50%;border:3px solid #e5e7eb;border-top-color:#12314e;animation:spin .7s linear infinite;display:inline-block;margin-bottom:10px}
@keyframes spin{to{transform:rotate(360deg)}}

/* Responsive */
@media(max-width:640px){
  .cl-table th.hide-sm,.cl-table td.hide-sm{display:none}
  .cl-podium{gap:6px}
  .cl-pod-1 .cl-pod-avatar{width:64px;height:64px}
  .cl-pod-2 .cl-pod-avatar{width:52px;height:52px}
  .cl-pod-3 .cl-pod-avatar{width:44px;height:44px}
  .pp-header{flex-direction:column;text-align:center}
  .pp-close{top:10px;right:10px}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ── HEADER + ONGLETS ──────────────────────────────────── -->
<section class="comm-hero">
  <div class="container">
    <p class="comm-hero-eyebrow">Zone85 · Communauté</p>
    <?php if ($_tab === 'passeport'): ?>
      <h1 class="comm-hero-title"><?= $is_logged ? ('Passeport de ' . e($active_user['pseudo'])) : 'Mon Passeport' ?></h1>
      <p class="comm-hero-sub">Ton identité dans la Zone — niveau, badges, missions accomplies.</p>
    <?php elseif ($_tab === 'fil'): ?>
      <h1 class="comm-hero-title">Fil de la Zone</h1>
      <p class="comm-hero-sub">Ce qui se passe en ce moment dans Zone85.</p>
    <?php elseif ($_tab === 'clans'): ?>
      <h1 class="comm-hero-title">Classement des Clans</h1>
      <p class="comm-hero-sub">Bocage · Littoral · Marais — la course aux points de saison.</p>
    <?php else: ?>
      <h1 class="comm-hero-title">Classement des Zonautes</h1>
      <p class="comm-hero-sub">Les meilleurs Zonautes de la saison<?php if ($is_logged && $my_rank): ?> — tu es #<?= $my_rank ?><?php endif; ?>.</p>
    <?php endif; ?>

    <nav class="comm-tabs" aria-label="Onglets communauté">
      <a href="<?= comm_url('passeport') ?>" class="comm-tab <?= $_tab === 'passeport' ? 'active' : '' ?>">
        Mon Passeport
      </a>
      <a href="<?= comm_url('fil') ?>" class="comm-tab <?= $_tab === 'fil' ? 'active' : '' ?>">
        Le Fil<?php if ($total_items > 0 && $_tab === 'fil'): ?><span class="comm-tab-count"><?= number_format($total_items,0,',','&#8201;') ?></span><?php endif; ?>
      </a>
      <a href="<?= comm_url('clans') ?>" class="comm-tab <?= $_tab === 'clans' ? 'active' : '' ?>">
        Clans
      </a>
      <a href="<?= comm_url('classement') ?>" class="comm-tab <?= $_tab === 'classement' ? 'active' : '' ?>">
        Zonautes<?php if ($cl_total > 0): ?><span class="comm-tab-count"><?= $cl_total ?></span><?php endif; ?>
      </a>
    </nav>
  </div>
</section>


<?php if ($_tab === 'passeport'): ?>
<!-- ================================================================
     MON PASSEPORT
================================================================ -->
<section class="pp-page-wrap">
<?php if (!$is_logged): ?>
  <div class="pp-not-logged">
    <div style="font-size:2.5rem;margin-bottom:16px">🪪</div>
    <h2 style="font-size:1.1rem;font-weight:900;color:var(--navy-dark);margin-bottom:8px">Crée ton Passeport Vendéen</h2>
    <p style="font-size:.9rem;color:var(--text-muted);margin-bottom:24px">Rejoins Zone85 pour obtenir ton identité dans la Zone : niveau, badges, clan et historique d'aventures.</p>
    <a href="inscription.php" style="display:inline-block;padding:12px 28px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;text-decoration:none;font-size:.92rem">Rejoindre la Zone →</a>
    <p style="margin-top:12px;font-size:.82rem;color:var(--text-muted)">Déjà membre ? <a href="login.php" style="color:var(--primary);font-weight:700;text-decoration:none">Connexion</a></p>
  </div>
<?php else:
  $pp_level      = max(1, min(10, (int)($active_user['level'] ?? 1)));
  $pp_xp_total   = (int)($active_user['xp_total'] ?? 0);
  $pp_level_name = get_level_name($pp_level);
  $pp_xp_next    = ($pp_level < 10) ? ($pp_level * 500) : null;
  $pp_xp_pct     = $pp_xp_next ? min(100, round(($pp_xp_total % ($pp_level * 500)) / ($pp_level * 500) * 100)) : 100;
  $pp_avatar_url = avatar_url($active_user);
  $pp_initials   = strtoupper(mb_substr($active_user['pseudo'], 0, 2));
?>
  <div class="pp-page-inner">
    <!-- Colonne gauche : carte passeport -->
    <div class="pp-card">
      <div class="pp-card-header">
        <div class="pp-card-avatar">
          <?php if (!empty($pp_avatar_url)): ?>
            <img src="<?= e($pp_avatar_url) ?>" alt="<?= e($active_user['pseudo']) ?>">
          <?php else: ?>
            <?= e($active_user['avatar_key'] ?? $pp_initials) ?>
          <?php endif; ?>
        </div>
        <div class="pp-card-info">
          <div class="pp-card-zone-label">Zone85 · Passeport Vendéen</div>
          <div class="pp-card-pseudo"><?= e($active_user['pseudo']) ?></div>
          <div class="pp-card-clan">
            <?php
              $pp_clan_name = $active_user['clan_name'] ?? ($active_user['clan_slug'] ?? null);
              if (!$pp_clan_name && $pdo) {
                try {
                  $cs = $pdo->prepare("SELECT c.name, c.slug FROM clans c JOIN users u ON u.clan_id=c.id WHERE u.id=:uid");
                  $cs->execute([':uid' => (int)$active_user['id']]);
                  $cs_row = $cs->fetch();
                  if ($cs_row) { $pp_clan_name = $cs_row['name']; }
                } catch (PDOException $e) {}
              }
              echo $pp_clan_name ? e($pp_clan_name) : 'Sans clan';
            ?>
          </div>
        </div>
      </div>
      <div class="pp-card-body">
        <div class="pp-card-level">
          <span class="pp-card-level-badge">Niv. <?= $pp_level ?></span>
          <span class="pp-card-level-name"><?= e($pp_level_name) ?></span>
        </div>

        <div class="pp-xp-bar-wrap">
          <div class="pp-xp-bar-label">
            <span><?= number_format($pp_xp_total, 0, ',', ' ') ?> XP</span>
            <?php if ($pp_xp_next): ?><span>Prochain niv. <?= $pp_xp_next ?> XP</span><?php endif; ?>
          </div>
          <div class="pp-xp-bar-track">
            <div class="pp-xp-bar-fill" style="width:<?= $pp_xp_pct ?>%"></div>
          </div>
        </div>

        <div class="pp-stats-row">
          <div class="pp-stat-box">
            <div class="pp-stat-box-val"><?= $pp_missions_count ?></div>
            <div class="pp-stat-box-lbl">Missions</div>
          </div>
          <div class="pp-stat-box">
            <div class="pp-stat-box-val"><?= count($pp_badges) ?></div>
            <div class="pp-stat-box-lbl">Badges</div>
          </div>
          <div class="pp-stat-box">
            <div class="pp-stat-box-val"><?= $pp_collectibles ?></div>
            <div class="pp-stat-box-lbl">Objets</div>
          </div>
        </div>

        <?php if (!empty($pp_badges)): ?>
        <div class="pp-badges-section">
          <div class="pp-badges-kicker">Derniers badges obtenus</div>
          <div class="pp-badges-list">
            <?php foreach ($pp_badges as $b): ?>
              <span class="pp-badge-pill rarity-<?= e($b['rarity'] ?? 'common') ?>"><?= e($b['icon'] ?? '') ?> <?= e($b['title']) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="pp-badges-section">
          <div class="pp-badges-kicker">Badges</div>
          <p style="font-size:.83rem;color:var(--text-muted);font-style:italic">Aucun badge encore — les aventures commencent.</p>
        </div>
        <?php endif; ?>

        <div style="margin-top:16px;text-align:center">
          <a href="profil.php" style="display:inline-block;padding:9px 22px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;text-decoration:none;font-size:.85rem">Voir mon profil complet →</a>
        </div>
      </div>
    </div>

    <!-- Colonne droite -->
    <div class="pp-side-col">
      <!-- Historique XP -->
      <?php if (!empty($pp_recent_xp)): ?>
      <div class="pp-xp-log">
        <div class="pp-xp-log-title">Historique XP récent</div>
        <?php foreach ($pp_recent_xp as $xp_row): ?>
        <div class="pp-xp-log-item">
          <div class="pp-xp-log-dot"></div>
          <div class="pp-xp-log-reason"><?= e($xp_row['reason'] ?? 'Action') ?></div>
          <div class="pp-xp-log-amount">+<?= (int)$xp_row['xp_amount'] ?> XP</div>
          <div class="pp-xp-log-date"><?= format_date($xp_row['created_at'], 'short') ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Comment progresser -->
      <div class="pp-howto">
        <div class="pp-howto-title">Comment gagner des XP</div>
        <?php
        $xp_sources = [
          ['+100 XP', 'Valider une mission'],
          ['+50 XP',  'Terminer une randonnée'],
          ['+75 XP',  'Trouver un objet KTC'],
          ['+25 XP',  'Participer à un événement flash'],
          ['+30 XP',  'Débloquer un badge'],
          ['+10 XP',  'Trouver un collectible terrain'],
        ];
        foreach ($xp_sources as [$xp, $lbl]):
        ?>
        <div class="pp-howto-item">
          <span class="pp-howto-xp"><?= $xp ?></span>
          <span class="pp-howto-label"><?= $lbl ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>


<?php elseif ($_tab === 'fil'): ?>
<!-- ================================================================
     FIL DE LA ZONE
================================================================ -->
<section class="cf-wrap">
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
        $chip      = $chip_map[$item['clan_slug'] ?? ''] ?? null;
        $is_pinned = (int)$item['is_pinned'] === 1;
      ?>
      <div class="cf-item <?= $is_pinned ? 'pinned' : '' ?>">
        <div class="cf-icon"><?= $item['icon_emoji'] ? e($item['icon_emoji']) : $evt_icon ?></div>
        <div class="cf-body">
          <div class="cf-item-title"><?= e($item['title']) ?></div>
          <?php if ($item['body']): ?><div class="cf-item-body"><?= e($item['body']) ?></div><?php endif; ?>
          <div class="cf-meta">
            <?php if ($item['pseudo']): ?><span class="cf-user"><?= e($item['pseudo']) ?></span><?php endif; ?>
            <?php if ($chip): ?><span class="<?= $chip[0] ?>"><?= $chip[1] ?></span><?php endif; ?>
            <span class="cf-type-badge"><?= $evt_label ?></span>
            <?php if ($is_pinned): ?><span class="cf-pinned-badge">📌 Épinglé</span><?php endif; ?>
            <span>· <?= format_date($item['created_at'], 'long') ?></span>
            <?php if ($item['link_url']): ?>
              <a href="<?= e($item['link_url']) ?>" style="color:var(--primary);font-weight:700;text-decoration:none;font-size:.72rem">Voir →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="cf-pagination">
      <?php if ($page_num > 1): ?>
        <a href="<?= comm_url('fil', ['p' => $page_num - 1]) ?>" class="cf-page-btn">← Précédent</a>
      <?php endif; ?>
      <?php for ($pg = max(1, $page_num-2); $pg <= min($total_pages, $page_num+2); $pg++): ?>
        <a href="<?= comm_url('fil', ['p' => $pg]) ?>" class="cf-page-btn <?= $pg === $page_num ? 'active' : '' ?>"><?= $pg ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?>
        <a href="<?= comm_url('fil', ['p' => $page_num + 1]) ?>" class="cf-page-btn">Suivant →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>


<?php elseif ($_tab === 'clans'): ?>
<!-- ================================================================
     CLASSEMENT DES CLANS
================================================================ -->
<section class="cn-wrap">
  <?php
  $clan_colors_map = ['bocage'=>'#2a9d5c','littoral'=>'#1a6fb8','marais'=>'#8b6340'];
  $clan_medals     = ['🥇','🥈','🥉'];
  ?>
  <!-- Podium clans -->
  <div class="cn-grid">
    <?php if (empty($clan_rankings)): ?>
    <p style="text-align:center;padding:48px;color:var(--text-muted)">Aucune donnée de classement pour le moment.</p>
    <?php else: foreach ($clan_rankings as $cr_row):
      $c_bg    = $clan_colors_map[$cr_row['slug']] ?? ($cr_row['color_primary'] ?? '#12314e');
      $c_medal = $clan_medals[$cr_row['rank'] - 1] ?? ('#' . $cr_row['rank']);
    ?>
    <div class="cn-card">
      <div class="cn-card-inner">
        <div class="cn-card-stripe" style="background:<?= $c_bg ?>;min-height:88px"></div>
        <div class="cn-card-body">
          <div class="cn-card-rank"><?= $c_medal ?></div>
          <div class="cn-card-info">
            <div class="cn-card-name"><?= e($cr_row['emoji'] ?? '') ?> <?= e($cr_row['name']) ?></div>
            <div class="cn-card-members"><?= (int)$cr_row['active_members'] ?> membre<?= $cr_row['active_members'] > 1 ? 's' : '' ?> actif<?= $cr_row['active_members'] > 1 ? 's' : '' ?></div>
          </div>
          <div class="cn-card-pts">
            <div class="cn-card-pts-val" style="color:<?= $c_bg ?>"><?= number_format((int)$cr_row['season_points']) ?></div>
            <div class="cn-card-pts-lbl">pts saison</div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Historique récent des points clans -->
  <?php if (!empty($clan_recent_logs)): ?>
  <div class="cn-section">
    <div class="cn-section-title">Dernières actions — historique</div>
    <div class="cn-log">
      <?php foreach ($clan_recent_logs as $log_row):
        $log_color = $clan_colors_map[$log_row['clan_slug']] ?? '#12314e';
      ?>
      <div class="cn-log-item">
        <div class="cn-log-dot" style="background:<?= $log_color ?>"></div>
        <div class="cn-log-info">
          <div class="cn-log-clan" style="color:<?= $log_color ?>"><?= e($log_row['clan_name']) ?></div>
          <div class="cn-log-reason"><?= e($log_row['reason'] ?? '') ?><?= $log_row['pseudo'] ? ' · ' . e($log_row['pseudo']) : '' ?></div>
        </div>
        <div class="cn-log-pts" style="color:<?= $log_color ?>">+<?= (int)$log_row['points'] ?> pts</div>
        <div class="cn-log-date"><?= format_date($log_row['created_at'], 'short') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Comment faire progresser son clan -->
  <div class="cn-section">
    <div class="cn-howto">
      <div class="cn-section-title">Comment faire progresser ton clan</div>
      <div class="cn-howto-grid">
        <div class="cn-howto-card">
          <div class="cn-howto-card-pts">+50 pts</div>
          <div class="cn-howto-card-label">Valider une mission</div>
          <div class="cn-howto-card-desc">Chaque mission validée rapporte des points à ton clan pour la saison.</div>
        </div>
        <div class="cn-howto-card">
          <div class="cn-howto-card-pts">+30 pts</div>
          <div class="cn-howto-card-label">Terminer une randonnée</div>
          <div class="cn-howto-card-desc">Les randos GPX validées comptent pour le total du clan.</div>
        </div>
        <div class="cn-howto-card">
          <div class="cn-howto-card-pts">+40 pts</div>
          <div class="cn-howto-card-label">Gagner un KTC</div>
          <div class="cn-howto-card-desc">Identifier l'objet mystère du mois en premier rapporte gros.</div>
        </div>
        <div class="cn-howto-card">
          <div class="cn-howto-card-pts">+20 pts</div>
          <div class="cn-howto-card-label">Participer à un flash event</div>
          <div class="cn-howto-card-desc">Les événements éclair sont de belles opportunités pour booster le clan.</div>
        </div>
      </div>
      <p style="margin-top:14px;font-size:.78rem;color:var(--text-muted)">Le classement est remis à zéro à chaque nouvelle saison. Tout est possible !</p>
    </div>
  </div>
</section>


<?php elseif ($_tab === 'classement'): ?>
<!-- ================================================================
     CLASSEMENT DES ZONAUTES
================================================================ -->
<section class="cl-wrap">
  <div class="cl-inner">

    <?php if ($is_logged && $my_rank && $my_rank > 50): ?>
    <div class="cl-my-rank">
      <span class="cl-my-rank-label">Ton rang dans ce classement</span>
      <span class="cl-my-rank-num">#<?= $my_rank ?></span>
    </div>
    <?php endif; ?>

    <?php if (empty($leaderboard)): ?>
    <div class="cl-empty">
      <div class="cl-empty-icon">🔭</div>
      <p style="font-size:1.05rem;font-weight:700;color:var(--navy-dark);margin-bottom:8px">Aucun joueur dans ce classement</p>
      <p>Rejoins la Zone et commence à gagner des XP pour apparaître ici !</p>
    </div>

    <?php else: ?>
    <?php
    $p1 = $leaderboard[0] ?? null;
    $p2 = $leaderboard[1] ?? null;
    $p3 = $leaderboard[2] ?? null;
    ?>

    <!-- Podium -->
    <?php if ($p1):
    $pod_avatar = function(array $p): string {
        if ($p['avatar_type']==='upload' && !empty($p['avatar_file'])) {
            return '<img src="'.e(upload_url(ltrim($p['avatar_file'],'/'))).'" alt="'.e($p['pseudo']).'" class="cl-pod-avatar">';
        }
        return '<div class="cl-pod-avatar" style="background:linear-gradient(135deg,#163756,#0c1e2e);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:clamp(.7rem,2vw,.95rem)">'.strtoupper(mb_substr($p['pseudo'],0,2)).'</div>';
    };
    ?>
    <div class="cl-podium">
      <?php if ($p2): ?>
      <div class="cl-podium-item cl-pod-2">
        <div class="cl-pod-avatar-wrap"><?= $pod_avatar($p2) ?><span class="cl-pod-medal">🥈</span></div>
        <div class="cl-pod-pseudo"><?= e($p2['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p2['xp_season'],0,',',' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">2</div></div>
      </div>
      <?php endif; ?>
      <div class="cl-podium-item cl-pod-1">
        <div class="cl-pod-avatar-wrap"><?= $pod_avatar($p1) ?><span class="cl-pod-medal">🥇</span></div>
        <div class="cl-pod-pseudo"><?= e($p1['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p1['xp_season'],0,',',' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">1</div></div>
      </div>
      <?php if ($p3): ?>
      <div class="cl-podium-item cl-pod-3">
        <div class="cl-pod-avatar-wrap"><?= $pod_avatar($p3) ?><span class="cl-pod-medal">🥉</span></div>
        <div class="cl-pod-pseudo"><?= e($p3['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p3['xp_season'],0,',',' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">3</div></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="cl-total-label">Top <?= count($leaderboard) ?> sur <?= number_format($cl_total,0,',',' ') ?> joueur<?= $cl_total>1?'s':'' ?> — classement par XP saison</p>

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
            $is_me    = $is_logged && (int)$row['id']=== (int)($active_user['id']??0);
            $medals   = [1=>'🥇',2=>'🥈',3=>'🥉'];
            $chip     = $chip_map[$row['clan_slug']] ?? null;
            $lvl_name = get_level_name($row['level']);
          ?>
          <tr class="<?= $is_me ? 'me' : '' ?>">
            <td style="white-space:nowrap">
              <?php if (isset($medals[$row['rank']])): ?><span class="cl-medal"><?= $medals[$row['rank']] ?></span>
              <?php else: ?><span class="cl-rank-num"><?= $row['rank'] ?></span><?php endif; ?>
            </td>
            <td>
              <div class="cl-avatar-cell">
                <?php if ($row['avatar_type']==='upload' && !empty($row['avatar_file'])): ?>
                  <img src="<?= e(upload_url(ltrim($row['avatar_file'],'/'))) ?>" alt="<?= e($row['pseudo']) ?>" class="cl-avatar">
                <?php else: ?><div class="cl-avatar-emoji" style="font-size:.78rem;font-weight:900;color:#fff"><?= strtoupper(mb_substr($row['pseudo'],0,2)) ?></div><?php endif; ?>
                <div>
                  <div class="cl-pseudo"><?= e($row['pseudo']) ?><?php if ($is_me): ?><span class="cl-me-badge">toi</span><?php endif; ?></div>
                  <div class="cl-level">Niv. <?= $row['level'] ?> — <?= e($lvl_name) ?></div>
                </div>
              </div>
            </td>
            <td class="hide-sm">
              <?php if ($chip): ?><span class="<?= $chip[0] ?>"><?= $chip[1] ?></span>
              <?php else: ?><span style="color:var(--text-muted);font-size:.8rem">—</span><?php endif; ?>
            </td>
            <td class="cl-missions hide-sm"><?= $row['missions_count'] ?></td>
            <td>
              <div class="cl-xp"><?= number_format($row['xp_season'],0,',',' ') ?></div>
              <div class="cl-xp-sub"><?= number_format($row['xp_total'],0,',',' ') ?> total</div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$is_logged): ?>
    <div style="text-align:center;margin-top:28px">
      <a href="inscription.php" style="display:inline-block;padding:12px 28px;background:var(--primary);color:#fff;border-radius:var(--radius);font-weight:800;text-decoration:none;font-size:.92rem">Rejoindre la Zone →</a>
      <p style="margin-top:12px;font-size:.82rem;color:var(--text-muted)">Inscris-toi pour apparaître dans le classement.</p>
    </div>
    <?php endif; ?>

    <!-- Comment progresser -->
    <div class="cl-howto">
      <div class="cn-section-title">Comment progresser dans le classement</div>
      <div class="cl-howto-grid">
        <div class="cl-howto-card">
          <div class="cl-howto-card-xp">+100 XP</div>
          <div class="cl-howto-card-label">Valider une mission</div>
          <div class="cl-howto-card-desc">Le cœur de Zone85 — chaque mission validée est récompensée.</div>
        </div>
        <div class="cl-howto-card">
          <div class="cl-howto-card-xp">+50 XP</div>
          <div class="cl-howto-card-label">Terminer une randonnée</div>
          <div class="cl-howto-card-desc">Valide ton GPX ou ton passage sur le terrain pour gagner des XP.</div>
        </div>
        <div class="cl-howto-card">
          <div class="cl-howto-card-xp">+75 XP</div>
          <div class="cl-howto-card-label">Trouver un KTC</div>
          <div class="cl-howto-card-desc">Le premier à identifier l'objet mystère du mois rafle la mise.</div>
        </div>
        <div class="cl-howto-card">
          <div class="cl-howto-card-xp">+30 XP</div>
          <div class="cl-howto-card-label">Badge débloqué</div>
          <div class="cl-howto-card-desc">Chaque badge obtenu vient gonfler ton compteur XP.</div>
        </div>
      </div>
      <p style="margin-top:14px;font-size:.78rem;color:var(--text-muted)">Le classement est calculé sur les XP de la saison en cours — tout le monde repart de zéro à chaque nouvelle saison.</p>
    </div>

    <?php endif; // leaderboard ?>
  </div>
</section>


<?php endif; // tab ?>

<?php

render_hidden_collectibles('communaute');
require_once 'includes/footer.php';
?>
