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
$_tab = in_array($_GET['tab'] ?? '', ['classement', 'zonautes', 'fil'], true)
    ? $_GET['tab'] : 'fil';

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

    // Classement des clans (saison active)
    $clan_rankings = [];
    if ($pdo) {
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
            $sid = $sr ? (int)$sr['id'] : 0;
            $cr->execute([':season_id' => $sid, ':season_id2' => $sid]);
            $clan_rankings = $cr->fetchAll();
            foreach ($clan_rankings as $i => &$cr_row) { $cr_row['rank'] = $i + 1; }
            unset($cr_row);
        } catch (PDOException $e) {}
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

    <?php if ($_tab === 'classement'): ?>
      <h1 class="comm-hero-title">Classement</h1>
      <p class="comm-hero-sub">Les meilleurs Zonautes de la saison en cours.</p>
      <div class="cl-hero-stats">
        <div><div class="cl-stat-num"><?= $cl_total ?></div><div class="cl-stat-label">Zonautes</div></div>
        <?php if ($is_logged && $my_rank): ?>
        <div><div class="cl-stat-num">#<?= $my_rank ?></div><div class="cl-stat-label">Ton rang</div></div>
        <?php endif; ?>
      </div>
    <?php elseif ($_tab === 'zonautes'): ?>
      <h1 class="comm-hero-title">Les Zonautes</h1>
      <p class="comm-hero-sub">Tous les membres de Zone85 — clique sur un passeport pour en savoir plus.</p>
    <?php else: ?>
      <h1 class="comm-hero-title">Fil de la Zone</h1>
      <p class="comm-hero-sub">Ce qui se passe en ce moment dans Zone85.</p>
    <?php endif; ?>

    <nav class="comm-tabs" aria-label="Onglets communauté">
      <a href="<?= comm_url('fil') ?>" class="comm-tab <?= $_tab === 'fil' ? 'active' : '' ?>">
        🌍 Fil<?php if ($total_items > 0 && $_tab === 'fil'): ?><span class="comm-tab-count"><?= number_format($total_items,0,',','&#8201;') ?></span><?php endif; ?>
      </a>
      <a href="<?= comm_url('classement') ?>" class="comm-tab <?= $_tab === 'classement' ? 'active' : '' ?>">
        🏆 Classement
      </a>
      <a href="<?= comm_url('zonautes') ?>" class="comm-tab <?= $_tab === 'zonautes' ? 'active' : '' ?>">
        👥 Zonautes<?php if ($zo_total > 0 && $_tab === 'zonautes'): ?><span class="comm-tab-count"><?= $zo_total ?></span><?php endif; ?>
      </a>
    </nav>
  </div>
</section>


<?php if ($_tab === 'fil'): ?>
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


<?php elseif ($_tab === 'classement'): ?>
<!-- ================================================================
     CLASSEMENT
================================================================ -->
<section class="cl-wrap">
  <div class="container">

    <!-- Filtre clan -->
    <div class="cl-clan-tabs">
      <?php foreach (['all'=>'🌍 Tous','bocage'=>'🌳 Bocage','littoral'=>'⚓ Littoral','marais'=>'🌿 Marais'] as $slug => $lbl): ?>
      <a href="<?= comm_url('classement', ['clan' => $slug === 'all' ? '' : $slug]) ?>"
         class="cl-clan-tab <?= $clan_filter === $slug ? 'active' : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

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
    <?php if ($p1): ?>
    <div class="cl-podium">
      <!-- 2e -->
      <?php if ($p2): ?>
      <div class="cl-podium-item cl-pod-2">
        <div class="cl-pod-avatar-wrap">
          <?php if ($p2['avatar_type']==='upload' && !empty($p2['avatar_file'])): ?>
            <img src="<?= e(upload_url(ltrim($p2['avatar_file'],'/'))) ?>" alt="<?= e($p2['pseudo']) ?>" class="cl-pod-avatar">
          <?php else: ?><div class="cl-pod-emoji">🎮</div><?php endif; ?>
          <span class="cl-pod-medal">🥈</span>
        </div>
        <div class="cl-pod-pseudo"><?= e($p2['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p2['xp_season'],0,',',' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">2</div></div>
      </div>
      <?php endif; ?>

      <!-- 1er -->
      <div class="cl-podium-item cl-pod-1">
        <div class="cl-pod-avatar-wrap">
          <?php if ($p1['avatar_type']==='upload' && !empty($p1['avatar_file'])): ?>
            <img src="<?= e(upload_url(ltrim($p1['avatar_file'],'/'))) ?>" alt="<?= e($p1['pseudo']) ?>" class="cl-pod-avatar">
          <?php else: ?><div class="cl-pod-emoji">🎮</div><?php endif; ?>
          <span class="cl-pod-medal">🥇</span>
        </div>
        <div class="cl-pod-pseudo"><?= e($p1['pseudo']) ?></div>
        <div class="cl-pod-xp"><?= number_format($p1['xp_season'],0,',',' ') ?> XP</div>
        <div class="cl-pod-plinth"><div class="cl-pod-rank">1</div></div>
      </div>

      <!-- 3e -->
      <?php if ($p3): ?>
      <div class="cl-podium-item cl-pod-3">
        <div class="cl-pod-avatar-wrap">
          <?php if ($p3['avatar_type']==='upload' && !empty($p3['avatar_file'])): ?>
            <img src="<?= e(upload_url(ltrim($p3['avatar_file'],'/'))) ?>" alt="<?= e($p3['pseudo']) ?>" class="cl-pod-avatar">
          <?php else: ?><div class="cl-pod-emoji">🎮</div><?php endif; ?>
          <span class="cl-pod-medal">🥉</span>
        </div>
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
                <?php else: ?><div class="cl-avatar-emoji">🎮</div><?php endif; ?>
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

    <?php if (!empty($clan_rankings)): ?>
    <h3 style="font-size:1rem;font-weight:900;color:#0c1e2e;margin:32px 0 14px">🛡 Classement des clans — Saison en cours</h3>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($clan_rankings as $cr_row):
        $clan_colors = ['bocage'=>'#2a9d5c','littoral'=>'#12314e','marais'=>'#8b6914','plaine'=>'#6b7f96'];
        $bg = $clan_colors[$cr_row['slug']] ?? ($cr_row['color_primary'] ?? '#12314e');
        $medal = ['🥇','🥈','🥉'][$cr_row['rank']-1] ?? ('#' . $cr_row['rank']);
      ?>
      <div style="background:#fff;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:14px;border:1.5px solid rgba(0,0,0,.07)">
        <span style="font-size:1.4rem;min-width:32px"><?= $medal ?></span>
        <span style="display:inline-block;width:12px;height:36px;border-radius:4px;background:<?= $bg ?>;flex-shrink:0"></span>
        <div style="flex:1">
          <div style="font-size:.95rem;font-weight:900;color:#0c1e2e"><?= e($cr_row['emoji'] ?? '') ?> <?= e($cr_row['name']) ?></div>
          <div style="font-size:.72rem;color:#6b7f96"><?= (int)$cr_row['active_members'] ?> membre<?= $cr_row['active_members']>1?'s':'' ?> actif<?= $cr_row['active_members']>1?'s':'' ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:1.3rem;font-weight:900;color:<?= $bg ?>"><?= number_format((int)$cr_row['season_points']) ?></div>
          <div style="font-size:.68rem;color:#aaa">pts saison</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; // leaderboard ?>
  </div>
</section>


<?php else: // zonautes ?>
<!-- ================================================================
     ZONAUTES
================================================================ -->

<!-- Barre de filtres collante -->
<div class="zo-filters" role="navigation" aria-label="Filtres Zonautes">
  <div class="zo-filters-inner">
    <a href="<?= comm_url('zonautes') ?>"
       class="zo-filter-btn <?= $filter_clan==='' ? 'active' : '' ?>">Tous</a>
    <a href="<?= comm_url('zonautes',['clan'=>'bocage']) ?>"
       class="zo-filter-btn <?= $filter_clan==='bocage' ? 'bocage-active' : '' ?>">🌳 Bocage</a>
    <a href="<?= comm_url('zonautes',['clan'=>'littoral']) ?>"
       class="zo-filter-btn <?= $filter_clan==='littoral' ? 'littoral-active' : '' ?>">⚓ Littoral</a>
    <a href="<?= comm_url('zonautes',['clan'=>'marais']) ?>"
       class="zo-filter-btn <?= $filter_clan==='marais' ? 'marais-active' : '' ?>">🌿 Marais</a>
    <a href="<?= comm_url('zonautes', array_merge($filter_clan?['clan'=>$filter_clan]:[], ['sort'=>$sort_mode==='season'?'total':'season'])) ?>"
       class="zo-sort-toggle <?= $sort_mode==='season' ? 'active' : '' ?>">
      <?= $sort_mode==='season' ? '★ Saison' : '★ Total XP' ?>
    </a>
  </div>
</div>

<!-- Grille joueurs -->
<section class="zo-wrap">
  <?php if (empty($players)): ?>
  <p class="zo-empty">
    <span style="display:block;font-size:1.2rem;margin-bottom:12px">🗺️</span>
    <strong>Soyez parmi les premiers membres de l'aventure.</strong>
  </p>
  <?php else: ?>

  <div class="zo-grid" id="players-grid">
    <?php
    $rank_start = $zo_offset + 1;
    foreach ($players as $i => $p):
      $global_rank  = $rank_start + $i;
      $rank_cls     = match($global_rank) {1=>' rank-1',2=>' rank-2',3=>' rank-3',default=>''};
      $cs           = $p['clan_slug'] ?? '';
      $chip_cls     = ['bocage'=>'bocage-chip-sm','littoral'=>'littoral-chip-sm','marais'=>'marais-chip-sm'][$cs] ?? '';
      $avatar_type  = $p['avatar_type'] ?? 'preset';
      $avatar_emoji = '&#128100;';
      if ($avatar_type==='preset') {
          $cfg = $p['avatar_config'] ?? '';
          if ($cfg) { $cfg_arr = json_decode($cfg,true); if (!empty($cfg_arr['emoji'])) $avatar_emoji = htmlspecialchars($cfg_arr['emoji'],ENT_QUOTES,'UTF-8'); }
      }
      $level   = max(1,(int)($p['level'] ?? get_user_level_from_xp((int)$p['xp_total'])));
      $xp_show = $sort_mode==='season' ? (int)$p['xp_season'] : (int)$p['xp_total'];
      $xp_suf  = $sort_mode==='season' ? ' XP saison' : ' XP';
    ?>
    <div class="player-card" data-user-id="<?= (int)$p['id'] ?>"
         onclick="openPassport(<?= (int)$p['id'] ?>)" tabindex="0" role="button"
         aria-label="Ouvrir le passeport de <?= e($p['pseudo']) ?>"
         onkeydown="if(event.key==='Enter'||event.key===' ')openPassport(<?= (int)$p['id'] ?>)">
      <span class="pc-rank-badge<?= $rank_cls ?>"><?= $global_rank ?></span>
      <div class="pc-avatar">
        <?php if ($avatar_type==='upload' && !empty($p['avatar_file'])): ?>
          <img src="<?= upload_url(e($p['avatar_file'])) ?>" alt="<?= e($p['pseudo']) ?>">
        <?php else: ?><?= $avatar_emoji ?><?php endif; ?>
      </div>
      <div class="pc-pseudo"><?= e($p['pseudo']) ?></div>
      <?php if ($p['clan_name']): ?><div class="pc-clan-chip <?= e($chip_cls) ?>"><?= e($p['clan_name']) ?></div><?php endif; ?>
      <div class="pc-level-badge">Niv. <?= $level ?></div>
      <div class="pc-xp"><?= number_format($xp_show,0,',',' ') ?><small><?= e($xp_suf) ?></small></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (($zo_offset + 50) < $zo_total): ?>
  <div class="zo-voir-plus">
    <a href="<?= comm_url('zonautes', array_merge(['offset'=>$zo_offset+50], $filter_clan?['clan'=>$filter_clan]:[], $sort_mode==='season'?['sort'=>'season']:[])) ?>"
       class="btn btn-primary">
      Voir plus — <?= max(0,$zo_total-$zo_offset-50) ?> restants
    </a>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</section>

<!-- Passeport modal -->
<div class="passport-overlay" id="passport-overlay" role="dialog" aria-modal="true" aria-label="Passeport Vendéen" onclick="if(event.target===this)closePassport()">
  <div class="passport-modal" id="passport-modal">
    <div class="pp-loading" id="pp-loading"><span class="spinner"></span><br>Chargement du passeport...</div>
    <div id="pp-content" style="display:none"></div>
  </div>
</div>

<?php endif; // tab zonautes ?>

<?php
// Scripts (Passeport — uniquement pour l'onglet zonautes)
if ($_tab === 'zonautes') {
$page_scripts = '<script>
var _ppCache={};
function openPassport(userId){
  var overlay=document.getElementById("passport-overlay"),loading=document.getElementById("pp-loading"),content=document.getElementById("pp-content");
  overlay.classList.add("open");document.body.style.overflow="hidden";
  if(_ppCache[userId]){loading.style.display="none";content.style.display="block";content.innerHTML=_ppCache[userId];return;}
  loading.style.display="block";content.style.display="none";content.innerHTML="";
  var xhr=new XMLHttpRequest();
  xhr.open("GET","ajax/passport.php?user_id="+encodeURIComponent(userId),true);
  xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");
  xhr.onreadystatechange=function(){
    if(xhr.readyState!==4)return;
    loading.style.display="none";content.style.display="block";
    if(xhr.status===200){try{var d=JSON.parse(xhr.responseText);if(d.ok){var h=renderPassport(d);_ppCache[userId]=h;content.innerHTML=h;}else{content.innerHTML="<div style=\"padding:32px;text-align:center;color:#ef4444;\">Passeport introuvable.</div>";}}catch(e){content.innerHTML="<div style=\"padding:32px;text-align:center;color:#ef4444;\">Erreur.</div>";}}
    else{content.innerHTML="<div style=\"padding:32px;text-align:center;color:#ef4444;\">Erreur serveur.</div>";}
  };xhr.send();
}
function closePassport(){document.getElementById("passport-overlay").classList.remove("open");document.body.style.overflow="";}
document.addEventListener("keydown",function(e){if(e.key==="Escape")closePassport();});
function renderPassport(d){
  var clanChip=d.clan_slug?"<span class=\"pc-clan-chip "+d.clan_slug+"-chip-sm\">"+esc(d.clan_name)+"</span>":"<span style=\"font-size:.82rem;color:rgba(255,255,255,.4);\">Sans clan</span>";
  var avatarHtml=d.avatar_type==="upload"&&d.avatar_url?"<img src=\""+esc(d.avatar_url)+"\" alt=\""+esc(d.pseudo)+"\">":d.avatar_emoji||"&#128100;";
  var badgesHtml="";
  if(d.badges&&d.badges.length>0){badgesHtml="<div class=\"pp-badges-row\">";for(var i=0;i<d.badges.length;i++){var b=d.badges[i];badgesHtml+="<span class=\"pp-badge-chip rarity-"+esc(b.rarity)+"\">"+esc(b.icon)+" "+esc(b.title)+"</span>";}badgesHtml+="</div>";}
  else{badgesHtml="<p class=\"pp-no-badges\">Aucun badge encore &mdash; les aventures commencent.</p>";}
  return "<div class=\"pp-header\"><button class=\"pp-close\" onclick=\"closePassport()\" aria-label=\"Fermer\">&times;</button><div class=\"pp-avatar\">"+avatarHtml+"</div><div class=\"pp-info\"><div class=\"pp-logo\">Zone85</div><div class=\"pp-id-label\">Passeport Vend&#233;en</div><div class=\"pp-pseudo\">"+esc(d.pseudo)+"</div><div class=\"pp-clan-line\">"+clanChip+"</div></div></div><div class=\"pp-body\"><div class=\"pp-level-line\"><span class=\"pp-level-badge\">Niv. "+d.level+"</span><span class=\"pp-level-name\">"+esc(d.level_name)+"</span></div><p class=\"pp-member-since\">Membre depuis&nbsp;: "+esc(d.joined)+"</p><div class=\"pp-stats-grid\"><div class=\"pp-stat\"><span class=\"pp-stat-val\">"+number(d.xp_total)+" <small style=\"font-size:.65rem;font-weight:600;color:#9ca3af;\">XP</small></span><span class=\"pp-stat-lbl\">XP Total</span></div><div class=\"pp-stat\"><span class=\"pp-stat-val\">"+d.badges_count+"</span><span class=\"pp-stat-lbl\">Badges</span></div><div class=\"pp-stat\"><span class=\"pp-stat-val\">"+d.participations+"</span><span class=\"pp-stat-lbl\">Participations</span></div><div class=\"pp-stat\"><span class=\"pp-stat-val\">"+d.collectibles+"</span><span class=\"pp-stat-lbl\">Objets trouv&#233;s</span></div></div><p class=\"pp-badges-title\">Derniers badges</p>"+badgesHtml+"</div>";
}
function esc(str){if(!str)return"";return String(str).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}
function number(n){return parseInt(n||0).toLocaleString("fr-FR");}
</script>';
}

render_hidden_collectibles('communaute');
require_once 'includes/footer.php';
?>
