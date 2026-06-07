<?php
// ============================================================
// admin/comments.php — Modération des commentaires (site-wide)
// Sources : Les Échos · Randos · Missions · KTC
// ============================================================
$admin_current    = 'comments';
$admin_page_title = 'Commentaires';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

define('SKIP_MAINTENANCE_CHECK', true);
require_admin();

$pdo   = db();
$flash = null;
$tab   = in_array($_GET['tab'] ?? '', ['echos', 'randos', 'missions', 'ktc'])
         ? $_GET['tab'] : 'echos';

// ── S'assurer que la colonne xp_awarded existe (article_comments) ─
if ($pdo) {
    try {
        $pdo->exec("ALTER TABLE article_comments
            ADD COLUMN IF NOT EXISTS xp_awarded TINYINT(1) NOT NULL DEFAULT 0
            AFTER status");
    } catch (PDOException $e) {}
}

// ═══════════════════════════════════════════════════════════════
// TRAITEMENT DES ACTIONS POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $source = $_POST['source'] ?? 'echos';
        $id     = (int)($_POST['item_id'] ?? 0);

        // ─────────────────────────────────────────────────────
        // SOURCE : LES ÉCHOS (article_comments)
        // ─────────────────────────────────────────────────────
        if ($source === 'echos' && $id > 0) {

            if ($action === 'toggle_status') {
                $row = $pdo->prepare("SELECT status FROM article_comments WHERE id=:id");
                $row->execute([':id' => $id]);
                $cur = $row->fetchColumn();
                $new = $cur === 'visible' ? 'hidden' : 'visible';
                $pdo->prepare("UPDATE article_comments SET status=:s WHERE id=:id")
                    ->execute([':s' => $new, ':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => $new === 'hidden' ? 'Commentaire masqué.' : 'Commentaire restauré.'];
            }

            elseif ($action === 'delete') {
                $row = $pdo->prepare("SELECT user_id, xp_awarded FROM article_comments WHERE id=:id");
                $row->execute([':id' => $id]);
                $c = $row->fetch();
                if ($c && $c['xp_awarded']) {
                    $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - 5) WHERE id=:id")
                        ->execute([':id' => $c['user_id']]);
                }
                $pdo->prepare("DELETE FROM article_comments WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Commentaire supprimé' . ($c && $c['xp_awarded'] ? ' (−5 XP restitués).' : '.')];
            }

            elseif ($action === 'award_xp') {
                $row = $pdo->prepare("SELECT user_id, xp_awarded FROM article_comments WHERE id=:id");
                $row->execute([':id' => $id]);
                $c = $row->fetch();
                if ($c && !$c['xp_awarded']) {
                    $pdo->prepare("UPDATE users SET xp_total = xp_total + 5 WHERE id=:id")
                        ->execute([':id' => $c['user_id']]);
                    $pdo->prepare("UPDATE article_comments SET xp_awarded=1 WHERE id=:id")
                        ->execute([':id' => $id]);
                    $pdo->prepare("INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason) VALUES (:uid,'article_comment',:sid,5,'Commentaire Les Échos')")
                        ->execute([':uid' => $c['user_id'], ':sid' => $id]);
                    $flash = ['type' => 'ok', 'msg' => '+5 XP attribués à l\'auteur.'];
                } else {
                    $flash = ['type' => 'warn', 'msg' => 'XP déjà attribués pour ce commentaire.'];
                }
            }

            elseif ($action === 'revoke_xp') {
                $row = $pdo->prepare("SELECT user_id, xp_awarded FROM article_comments WHERE id=:id");
                $row->execute([':id' => $id]);
                $c = $row->fetch();
                if ($c && $c['xp_awarded']) {
                    $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - 5) WHERE id=:id")
                        ->execute([':id' => $c['user_id']]);
                    $pdo->prepare("UPDATE article_comments SET xp_awarded=0 WHERE id=:id")
                        ->execute([':id' => $id]);
                    $pdo->prepare("INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason) VALUES (:uid,'article_comment',:sid,-5,'XP commentaire retiré (admin)')")
                        ->execute([':uid' => $c['user_id'], ':sid' => $id]);
                    $flash = ['type' => 'ok', 'msg' => '−5 XP retirés à l\'auteur.'];
                } else {
                    $flash = ['type' => 'warn', 'msg' => 'Aucun XP à retirer pour ce commentaire.'];
                }
            }

            // Actions groupées Les Échos
            elseif (in_array($action, ['bulk_hide', 'bulk_delete', 'bulk_award_xp']) && !empty($_POST['ids'])) {
                $ids = array_filter(array_map('intval', (array)$_POST['ids']));
                if (!empty($ids)) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    if ($action === 'bulk_hide') {
                        $pdo->prepare("UPDATE article_comments SET status='hidden' WHERE id IN ($ph)")->execute($ids);
                        $flash = ['type' => 'ok', 'msg' => count($ids) . ' commentaire(s) masqué(s).'];
                    } elseif ($action === 'bulk_delete') {
                        $rows = $pdo->prepare("SELECT id, user_id FROM article_comments WHERE id IN ($ph) AND xp_awarded=1");
                        $rows->execute($ids);
                        foreach ($rows->fetchAll() as $r) {
                            $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - 5) WHERE id=:id")
                                ->execute([':id' => $r['user_id']]);
                            $pdo->prepare("INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason) VALUES (:uid,'article_comment',:sid,-5,'XP commentaire retiré (suppression admin)')")
                                ->execute([':uid' => $r['user_id'], ':sid' => $r['id']]);
                        }
                        $pdo->prepare("DELETE FROM article_comments WHERE id IN ($ph)")->execute($ids);
                        $flash = ['type' => 'ok', 'msg' => count($ids) . ' commentaire(s) supprimé(s).'];
                    } elseif ($action === 'bulk_award_xp') {
                        $rows = $pdo->prepare("SELECT id, user_id FROM article_comments WHERE id IN ($ph) AND xp_awarded=0");
                        $rows->execute($ids);
                        $done = 0;
                        foreach ($rows->fetchAll() as $r) {
                            $pdo->prepare("UPDATE users SET xp_total = xp_total + 5 WHERE id=:id")
                                ->execute([':id' => $r['user_id']]);
                            $pdo->prepare("UPDATE article_comments SET xp_awarded=1 WHERE id=:id")
                                ->execute([':id' => $r['id']]);
                            $pdo->prepare("INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason) VALUES (:uid,'article_comment',:sid,5,'Commentaire Les Échos')")
                                ->execute([':uid' => $r['user_id'], ':sid' => $r['id']]);
                            $done++;
                        }
                        $flash = ['type' => 'ok', 'msg' => "+5 XP attribués à {$done} auteur(s)."];
                    }
                }
            }
        }

        // ─────────────────────────────────────────────────────
        // SOURCE : RANDOS (rando_participations)
        // ─────────────────────────────────────────────────────
        elseif ($source === 'rando' && $id > 0) {

            if ($action === 'clear_comment') {
                $pdo->prepare("UPDATE rando_participations SET comment=NULL WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Commentaire rando effacé.'];
            }

            elseif ($action === 'clear_review') {
                $pdo->prepare("UPDATE rando_participations SET proof_review=NULL WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Avis rando effacé.'];
            }

            elseif ($action === 'delete') {
                $row = $pdo->prepare("SELECT user_id, xp_awarded FROM rando_participations WHERE id=:id");
                $row->execute([':id' => $id]);
                $c = $row->fetch();
                if ($c && $c['xp_awarded'] > 0) {
                    $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - :xp) WHERE id=:id")
                        ->execute([':xp' => $c['xp_awarded'], ':id' => $c['user_id']]);
                }
                $pdo->prepare("DELETE FROM rando_participations WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Participation rando supprimée' . ($c && $c['xp_awarded'] > 0 ? ' (XP restitués).' : '.')];
            }

            // Bulk randos
            elseif (in_array($action, ['bulk_clear_comment', 'bulk_delete']) && !empty($_POST['ids'])) {
                $ids = array_filter(array_map('intval', (array)$_POST['ids']));
                if (!empty($ids)) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    if ($action === 'bulk_clear_comment') {
                        $pdo->prepare("UPDATE rando_participations SET comment=NULL WHERE id IN ($ph)")->execute($ids);
                        $flash = ['type' => 'ok', 'msg' => count($ids) . ' commentaire(s) effacé(s).'];
                    } elseif ($action === 'bulk_delete') {
                        $rows = $pdo->prepare("SELECT user_id, xp_awarded FROM rando_participations WHERE id IN ($ph) AND xp_awarded > 0");
                        $rows->execute($ids);
                        foreach ($rows->fetchAll() as $r) {
                            $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - :xp) WHERE id=:id")
                                ->execute([':xp' => $r['xp_awarded'], ':id' => $r['user_id']]);
                        }
                        $pdo->prepare("DELETE FROM rando_participations WHERE id IN ($ph)")->execute($ids);
                        $flash = ['type' => 'ok', 'msg' => count($ids) . ' participation(s) supprimée(s).'];
                    }
                }
            }
        }

        // ─────────────────────────────────────────────────────
        // SOURCE : MISSIONS (participations)
        // ─────────────────────────────────────────────────────
        elseif ($source === 'mission' && $id > 0) {

            if ($action === 'clear_comment') {
                $pdo->prepare("UPDATE participations SET comment=NULL WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Commentaire mission effacé.'];
            }

            elseif ($action === 'clear_answer') {
                $pdo->prepare("UPDATE participations SET answer_text=NULL WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Réponse mission effacée.'];
            }

            elseif ($action === 'delete') {
                $row = $pdo->prepare("SELECT user_id, xp_awarded FROM participations WHERE id=:id");
                $row->execute([':id' => $id]);
                $c = $row->fetch();
                if ($c && $c['xp_awarded'] > 0) {
                    $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - :xp) WHERE id=:id")
                        ->execute([':xp' => $c['xp_awarded'], ':id' => $c['user_id']]);
                }
                $pdo->prepare("DELETE FROM participations WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Participation mission supprimée' . ($c && $c['xp_awarded'] > 0 ? ' (XP restitués).' : '.')];
            }

            elseif ($action === 'bulk_delete' && !empty($_POST['ids'])) {
                $ids = array_filter(array_map('intval', (array)$_POST['ids']));
                if (!empty($ids)) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $rows = $pdo->prepare("SELECT user_id, xp_awarded FROM participations WHERE id IN ($ph) AND xp_awarded > 0");
                    $rows->execute($ids);
                    foreach ($rows->fetchAll() as $r) {
                        $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - :xp) WHERE id=:id")
                            ->execute([':xp' => $r['xp_awarded'], ':id' => $r['user_id']]);
                    }
                    $pdo->prepare("DELETE FROM participations WHERE id IN ($ph)")->execute($ids);
                    $flash = ['type' => 'ok', 'msg' => count($ids) . ' participation(s) supprimée(s).'];
                }
            }
        }

        // ─────────────────────────────────────────────────────
        // SOURCE : KTC (ktc_propositions)
        // ─────────────────────────────────────────────────────
        elseif ($source === 'ktc' && $id > 0) {

            if ($action === 'mark_correct') {
                $pdo->prepare("UPDATE ktc_propositions SET is_correct=1 WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Proposition marquée correcte.'];
            }

            elseif ($action === 'mark_incorrect') {
                $pdo->prepare("UPDATE ktc_propositions SET is_correct=0 WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Proposition marquée incorrecte.'];
            }

            elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM ktc_propositions WHERE id=:id")->execute([':id' => $id]);
                $flash = ['type' => 'ok', 'msg' => 'Proposition KTC supprimée.'];
            }

            elseif ($action === 'bulk_delete' && !empty($_POST['ids'])) {
                $ids = array_filter(array_map('intval', (array)$_POST['ids']));
                if (!empty($ids)) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("DELETE FROM ktc_propositions WHERE id IN ($ph)")->execute($ids);
                    $flash = ['type' => 'ok', 'msg' => count($ids) . ' proposition(s) supprimée(s).'];
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// DONNÉES SELON L'ONGLET ACTIF
// ═══════════════════════════════════════════════════════════════
$page     = max(1, (int)($_GET['p'] ?? 1));
$per_page = 30;
$offset   = ($page - 1) * $per_page;
$search   = trim($_GET['q'] ?? '');
$total    = 0;
$rows     = [];

// ── Stats globales (pour les badges d'onglet) ─────────────────
$tab_counts = ['echos' => 0, 'randos' => 0, 'missions' => 0, 'ktc' => 0];
if ($pdo) {
    try {
        $tab_counts['echos']    = (int)$pdo->query("SELECT COUNT(*) FROM article_comments")->fetchColumn();
        $tab_counts['randos']   = (int)$pdo->query("SELECT COUNT(*) FROM rando_participations WHERE comment IS NOT NULL AND comment != ''")->fetchColumn();
        $tab_counts['missions'] = (int)$pdo->query("SELECT COUNT(*) FROM participations WHERE (comment IS NOT NULL AND comment != '') OR (answer_text IS NOT NULL AND answer_text != '')")->fetchColumn();
        $tab_counts['ktc']      = (int)$pdo->query("SELECT COUNT(*) FROM ktc_propositions")->fetchColumn();
    } catch (PDOException $e) {}
}

// ── Les Échos ─────────────────────────────────────────────────
$filter_status  = $_GET['status']     ?? 'all';
$filter_xp      = $_GET['xp']         ?? 'all';
$filter_article = (int)($_GET['article_id'] ?? 0);
$stats_echos    = ['total' => 0, 'visible' => 0, 'hidden' => 0, 'no_xp' => 0];
$articles_list  = [];

if ($tab === 'echos' && $pdo) {
    try {
        $where  = ['1=1'];
        $params = [];
        if ($filter_status === 'visible') { $where[] = "ac.status='visible'"; }
        if ($filter_status === 'hidden')  { $where[] = "ac.status='hidden'"; }
        if ($filter_xp === 'awarded')     { $where[] = "ac.xp_awarded=1"; }
        if ($filter_xp === 'missing')     { $where[] = "ac.xp_awarded=0"; }
        if ($filter_article > 0)          { $where[] = "ac.article_id=:art"; $params[':art'] = $filter_article; }
        if ($search !== '')               { $where[] = "(ac.body LIKE :q OR u.pseudo LIKE :q)"; $params[':q'] = '%' . $search . '%'; }
        $where_sql = implode(' AND ', $where);

        $total = (int)$pdo->prepare("SELECT COUNT(*) FROM article_comments ac LEFT JOIN users u ON u.id=ac.user_id WHERE {$where_sql}")
                           ->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM article_comments ac LEFT JOIN users u ON u.id=ac.user_id WHERE {$where_sql}")->fetchColumn() : 0;

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM article_comments ac LEFT JOIN users u ON u.id=ac.user_id WHERE {$where_sql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ac.id, ac.body, ac.created_at, ac.status, ac.xp_awarded,
                   ac.article_id, ac.user_id,
                   u.pseudo, u.xp_total AS user_xp,
                   a.title AS ref_title, a.slug AS ref_slug
            FROM article_comments ac
            LEFT JOIN users u ON u.id = ac.user_id
            LEFT JOIN articles a ON a.id = ac.article_id
            WHERE {$where_sql}
            ORDER BY ac.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $s = $pdo->query("SELECT COUNT(*) AS total, SUM(status='visible') AS visible,
            SUM(status='hidden') AS hidden, SUM(xp_awarded=0) AS no_xp
            FROM article_comments")->fetch();
        $stats_echos = $s ?: $stats_echos;

        $articles_list = $pdo->query("
            SELECT a.id, a.title FROM articles a
            INNER JOIN article_comments ac ON ac.article_id = a.id
            GROUP BY a.id ORDER BY a.title ASC
        ")->fetchAll();

    } catch (PDOException $e) {
        $flash = ['type' => 'err', 'msg' => 'Erreur DB : ' . htmlspecialchars($e->getMessage())];
    }
}

// ── Randos ────────────────────────────────────────────────────
$filter_rando_status = $_GET['rstatus'] ?? 'all';
$randos_list = [];

if ($tab === 'randos' && $pdo) {
    try {
        $where  = ["(rp.comment IS NOT NULL AND rp.comment != '') OR (rp.proof_review IS NOT NULL AND rp.proof_review != '')"];
        $params = [];
        if ($filter_rando_status !== 'all') { $where[] = "rp.status=:rs"; $params[':rs'] = $filter_rando_status; }
        if ($search !== '') { $where[] = "(rp.comment LIKE :q OR rp.proof_review LIKE :q OR u.pseudo LIKE :q OR r.title LIKE :q)"; $params[':q'] = '%' . $search . '%'; }
        $where_sql = implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM rando_participations rp LEFT JOIN users u ON u.id=rp.user_id LEFT JOIN randos r ON r.id=rp.rando_id WHERE {$where_sql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT rp.id, rp.comment, rp.proof_review, rp.rating, rp.status, rp.xp_awarded, rp.done_at,
                   rp.user_id, rp.rando_id,
                   u.pseudo, u.xp_total AS user_xp,
                   r.title AS ref_title, r.slug AS ref_slug
            FROM rando_participations rp
            LEFT JOIN users u ON u.id = rp.user_id
            LEFT JOIN randos r ON r.id = rp.rando_id
            WHERE {$where_sql}
            ORDER BY rp.done_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        $flash = ['type' => 'err', 'msg' => 'Erreur DB : ' . htmlspecialchars($e->getMessage())];
    }
}

// ── Missions ──────────────────────────────────────────────────
$filter_mission_status = $_GET['mstatus'] ?? 'all';

if ($tab === 'missions' && $pdo) {
    try {
        $where  = ["((p.comment IS NOT NULL AND p.comment != '') OR (p.answer_text IS NOT NULL AND p.answer_text != ''))"];
        $params = [];
        if ($filter_mission_status !== 'all') { $where[] = "p.status=:ms"; $params[':ms'] = $filter_mission_status; }
        if ($search !== '') { $where[] = "(p.answer_text LIKE :q OR p.comment LIKE :q OR u.pseudo LIKE :q OR m.title LIKE :q)"; $params[':q'] = '%' . $search . '%'; }
        $where_sql = implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM participations p LEFT JOIN users u ON u.id=p.user_id LEFT JOIN missions m ON m.id=p.mission_id WHERE {$where_sql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT p.id, p.answer_text, p.comment, p.status, p.xp_awarded, p.created_at,
                   p.user_id, p.mission_id,
                   u.pseudo, u.xp_total AS user_xp,
                   m.title AS ref_title, m.slug AS ref_slug
            FROM participations p
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN missions m ON m.id = p.mission_id
            WHERE {$where_sql}
            ORDER BY p.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        $flash = ['type' => 'err', 'msg' => 'Erreur DB : ' . htmlspecialchars($e->getMessage())];
    }
}

// ── KTC ───────────────────────────────────────────────────────
$filter_ktc_correct = $_GET['correct'] ?? 'all';

if ($tab === 'ktc' && $pdo) {
    try {
        $where  = ['1=1'];
        $params = [];
        if ($filter_ktc_correct === 'yes')  { $where[] = "kp.is_correct=1"; }
        if ($filter_ktc_correct === 'no')   { $where[] = "kp.is_correct=0"; }
        if ($filter_ktc_correct === 'none') { $where[] = "kp.is_correct IS NULL"; }
        if ($search !== '') { $where[] = "(kp.proposition LIKE :q OR u.pseudo LIKE :q OR e.title LIKE :q)"; $params[':q'] = '%' . $search . '%'; }
        $where_sql = implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM ktc_propositions kp LEFT JOIN users u ON u.id=kp.user_id LEFT JOIN ktc_episodes e ON e.id=kp.episode_id WHERE {$where_sql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT kp.id, kp.proposition, kp.is_correct, kp.submitted_at,
                   kp.user_id, kp.episode_id,
                   u.pseudo, u.xp_total AS user_xp,
                   e.title AS ref_title, e.slug AS ref_slug
            FROM ktc_propositions kp
            LEFT JOIN users u ON u.id = kp.user_id
            LEFT JOIN ktc_episodes e ON e.id = kp.episode_id
            WHERE {$where_sql}
            ORDER BY kp.submitted_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        $flash = ['type' => 'err', 'msg' => 'Erreur DB : ' . htmlspecialchars($e->getMessage())];
    }
}

$total_pages = (int)ceil($total / $per_page);

require_once '_admin-header.php';
?>

<style>
/* ── Onglets ──────────────────────────────────────────────── */
.cm-tabs { display: flex; gap: 0; border-bottom: 2px solid #e8e4df; margin-bottom: 24px; flex-wrap: wrap; }
.cm-tab  { padding: 10px 22px; font-size: .83rem; font-weight: 700; color: #6b7f96;
           text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -2px;
           transition: color .15s, border-color .15s; white-space: nowrap; display: flex; align-items: center; gap: 7px; }
.cm-tab:hover   { color: #ea5649; }
.cm-tab.active  { color: #ea5649; border-bottom-color: #ea5649; }
.cm-tab-badge   { background: #ea5649; color: #fff; border-radius: 999px; font-size: .65rem;
                  font-weight: 800; padding: 1px 7px; line-height: 1.5; }

/* ── Stats ───────────────────────────────────────────────── */
.cm-stats { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; }
.cm-stat  { flex: 1 1 110px; background: #fff; border-radius: 10px; padding: 16px 18px;
            box-shadow: 0 2px 8px rgba(12,30,46,.06); border: 1px solid rgba(18,49,78,.07); text-align:center; }
.cm-stat-val  { font-size: 1.7rem; font-weight: 900; color: #0c1e2e; line-height: 1; }
.cm-stat-lbl  { font-size: .68rem; font-weight: 700; color: #6b7f96; text-transform: uppercase; letter-spacing: .08em; margin-top: 4px; }
.cm-stat-warn .cm-stat-val { color: #c0392b; }

/* ── Filtres ─────────────────────────────────────────────── */
.cm-filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
.cm-filter-select { padding: 8px 12px; border: 1.5px solid #d0cbc5; border-radius: 7px;
                    font-family: 'Inter', sans-serif; font-size: .82rem; color: #0f1e2d; background: #fff; }
.cm-filter-select:focus { outline: none; border-color: #ea5649; }
.cm-search { padding: 8px 12px; border: 1.5px solid #d0cbc5; border-radius: 7px;
             font-family: 'Inter', sans-serif; font-size: .82rem; min-width: 200px; flex: 1; }
.cm-search:focus { outline: none; border-color: #ea5649; }

/* ── Tableau ─────────────────────────────────────────────── */
.cm-table { width: 100%; border-collapse: collapse; }
.cm-table th { font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
               color: #6b7f96; padding: 10px 14px; background: #f8f4ef; border-bottom: 1px solid #e8e4df; text-align: left; }
.cm-table td { padding: 11px 14px; border-bottom: 1px solid #f0ece7; vertical-align: top; font-size: .83rem; }
.cm-table tr:hover td { background: #faf8f5; }
.cm-table tr.cm-row-hidden td { opacity: .55; }

.cm-text-cell { max-width: 300px; color: #0f1e2d; line-height: 1.5; }
.cm-text-label { font-size: .65rem; font-weight: 700; color: #9aadbc; text-transform: uppercase; margin-top: 6px; }
.cm-ref-link { font-size: .73rem; color: #ea5649; font-weight: 700; text-decoration: none; }
.cm-ref-link:hover { text-decoration: underline; }
.cm-author  { font-weight: 700; color: #0c1e2e; font-size: .82rem; }
.cm-date    { font-size: .71rem; color: #6b7f96; white-space: nowrap; }

/* ── Badges ─────────────────────────────────────────────── */
.cm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px;
            border-radius: 999px; font-size: .67rem; font-weight: 700; white-space: nowrap; }
.cm-badge-visible   { background: rgba(42,157,92,.1);  color: #1a7a42; }
.cm-badge-hidden    { background: rgba(107,127,150,.1); color: #4a6073; }
.cm-badge-xp        { background: rgba(234,86,73,.1);  color: #ea5649; }
.cm-badge-noxp      { background: rgba(201,150,42,.1); color: #8a6020; }
.cm-badge-correct   { background: rgba(42,157,92,.1);  color: #1a7a42; }
.cm-badge-incorrect { background: rgba(234,86,73,.1);  color: #c0392b; }
.cm-badge-pending   { background: rgba(14,165,233,.1); color: #0369a1; }

/* ── Actions ─────────────────────────────────────────────── */
.cm-actions { display: flex; flex-direction: column; gap: 4px; min-width: 130px; }
.cm-btn { padding: 4px 11px; border-radius: 6px; font-family: 'Inter', sans-serif; font-size: .73rem;
          font-weight: 700; cursor: pointer; border: none; text-align: center; white-space: nowrap;
          transition: opacity .15s; line-height: 1.4; }
.cm-btn:hover { opacity: .82; }
.cm-btn-hide  { background: #f0ece7; color: #3d5166; }
.cm-btn-show  { background: rgba(42,157,92,.1); color: #1a7a42; }
.cm-btn-del   { background: rgba(234,86,73,.1); color: #c0392b; }
.cm-btn-xp    { background: rgba(234,86,73,.15); color: #ea5649; }
.cm-btn-noxp  { background: rgba(201,150,42,.1); color: #8a6020; }
.cm-btn-clear { background: rgba(14,165,233,.1); color: #0369a1; }
.cm-btn-ok    { background: rgba(42,157,92,.15); color: #1a7a42; }
.cm-btn-warn  { background: rgba(234,86,73,.1); color: #c0392b; }

/* ── Bulk bar ────────────────────────────────────────────── */
.cm-bulk { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
           padding: 10px 14px; background: #f8f4ef; border-radius: 8px; margin-bottom: 14px; }
.cm-bulk-btn { padding: 6px 14px; border-radius: 7px; font-family: 'Inter', sans-serif;
               font-size: .77rem; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.cm-bulk-btn:hover { opacity: .85; }
.cm-bulk-hide   { background: #e8e4df; color: #3d5166; }
.cm-bulk-del    { background: rgba(234,86,73,.15); color: #c0392b; }
.cm-bulk-xp     { background: rgba(234,86,73,.1); color: #ea5649; }
.cm-bulk-clear  { background: rgba(14,165,233,.1); color: #0369a1; }

/* ── Pagination ──────────────────────────────────────────── */
.cm-pager { display: flex; gap: 6px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
.cm-pager a, .cm-pager span { padding: 5px 12px; border-radius: 7px; font-size: .79rem; font-weight: 700;
                               text-decoration: none; border: 1.5px solid #d0cbc5; color: #3d5166; }
.cm-pager a:hover { border-color: #ea5649; color: #ea5649; }
.cm-pager .active { background: #ea5649; color: #fff; border-color: #ea5649; }
</style>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">💬 Modération des commentaires</h1>
    <p class="adm-page-sub">Tous les contenus textuels générés par les utilisateurs — Les Échos, Randos, Missions, KTC.</p>
  </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'warn' ? 'warn' : 'err') ?>">
  <?= $flash['type'] === 'ok' ? '✅' : ($flash['type'] === 'warn' ? '⚠️' : '❌') ?>
  <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- Navigation par onglets -->
<div class="cm-tabs">
  <?php
  $tabs_def = [
      'echos'    => ['Les Échos', '📰'],
      'randos'   => ['Randos',    '🥾'],
      'missions' => ['Missions',  '🎯'],
      'ktc'      => ['KTC',       '🔍'],
  ];
  foreach ($tabs_def as $t_key => [$t_label, $t_icon]): ?>
  <a href="?tab=<?= $t_key ?>" class="cm-tab <?= $tab === $t_key ? 'active' : '' ?>">
    <?= $t_icon ?> <?= $t_label ?>
    <?php if ($tab_counts[$t_key] > 0): ?>
    <span class="cm-tab-badge"><?= $tab_counts[$t_key] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php
// ═══════════════════════════════════════════════════════════════
// VUE : LES ÉCHOS
// ═══════════════════════════════════════════════════════════════
if ($tab === 'echos'):
?>

<!-- Stats Les Échos -->
<div class="cm-stats">
  <div class="cm-stat">
    <div class="cm-stat-val"><?= (int)$stats_echos['total'] ?></div>
    <div class="cm-stat-lbl">Total</div>
  </div>
  <div class="cm-stat">
    <div class="cm-stat-val" style="color:#1a7a42"><?= (int)$stats_echos['visible'] ?></div>
    <div class="cm-stat-lbl">Visibles</div>
  </div>
  <div class="cm-stat">
    <div class="cm-stat-val" style="color:#4a6073"><?= (int)$stats_echos['hidden'] ?></div>
    <div class="cm-stat-lbl">Masqués</div>
  </div>
  <div class="cm-stat <?= (int)$stats_echos['no_xp'] > 0 ? 'cm-stat-warn' : '' ?>">
    <div class="cm-stat-val"><?= (int)$stats_echos['no_xp'] ?></div>
    <div class="cm-stat-lbl">Sans XP</div>
  </div>
</div>

<?php if ((int)$stats_echos['no_xp'] > 0): ?>
<div class="adm-flash adm-flash-warn" style="margin-bottom:20px">
  ⚠️ <strong><?= (int)$stats_echos['no_xp'] ?> commentaire(s)</strong> sans XP attribués.
  <a href="?tab=echos&xp=missing" style="color:#8a6020;font-weight:800;margin-left:8px">Voir et corriger →</a>
</div>
<?php endif; ?>

<!-- Filtres Les Échos -->
<form method="GET" action="">
  <input type="hidden" name="tab" value="echos">
  <div class="cm-filters">
    <select name="status" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"     <?= $filter_status === 'all'     ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="visible" <?= $filter_status === 'visible' ? 'selected' : '' ?>>✅ Visibles</option>
      <option value="hidden"  <?= $filter_status === 'hidden'  ? 'selected' : '' ?>>👁 Masqués</option>
    </select>
    <select name="xp" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"     <?= $filter_xp === 'all'     ? 'selected' : '' ?>>Tous (XP)</option>
      <option value="awarded" <?= $filter_xp === 'awarded' ? 'selected' : '' ?>>⭐ XP attribués</option>
      <option value="missing" <?= $filter_xp === 'missing' ? 'selected' : '' ?>>⚠️ Sans XP</option>
    </select>
    <?php if (!empty($articles_list)): ?>
    <select name="article_id" class="cm-filter-select" onchange="this.form.submit()">
      <option value="">Tous les articles</option>
      <?php foreach ($articles_list as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $filter_article === (int)$a['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars(mb_strimwidth($a['title'], 0, 45, '…'), ENT_QUOTES, 'UTF-8') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="text" name="q" class="cm-search" placeholder="Auteur, texte…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;font-family:inherit;font-weight:700;font-size:.82rem;cursor:pointer">Filtrer</button>
    <?php if ($filter_status !== 'all' || $filter_xp !== 'all' || $filter_article || $search): ?>
    <a href="?tab=echos" style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">✕ Reset</a>
    <?php endif; ?>
  </div>
</form>

<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($rows)): ?>
  <div style="padding:40px;text-align:center;color:#6b7f96">Aucun commentaire avec ces filtres.</div>
  <?php else: ?>

  <form method="POST" id="bulk-form-echos">
    <?= csrf_field() ?>
    <input type="hidden" name="source" value="echos">
    <div class="cm-bulk" id="bulk-bar-echos" style="display:none">
      <span id="bulk-count-echos" style="font-size:.82rem;font-weight:700;color:#3d5166"></span>
      <button type="button" class="cm-bulk-btn cm-bulk-xp"   onclick="bulkAction('bulk-form-echos','bulk_award_xp')">⭐ Attribuer XP</button>
      <button type="button" class="cm-bulk-btn cm-bulk-hide" onclick="bulkAction('bulk-form-echos','bulk_hide')">👁 Masquer</button>
      <button type="button" class="cm-bulk-btn cm-bulk-del"  onclick="if(confirm('Supprimer ces commentaires ?')) bulkAction('bulk-form-echos','bulk_delete')">🗑 Supprimer</button>
    </div>

    <table class="cm-table">
      <thead>
        <tr>
          <th><input type="checkbox" onchange="toggleAll('echos',this.checked)"></th>
          <th>Article / Auteur</th>
          <th>Commentaire</th>
          <th>Date</th>
          <th>Statut / XP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $c): ?>
        <tr class="<?= $c['status'] === 'hidden' ? 'cm-row-hidden' : '' ?>">
          <td><input type="checkbox" name="ids[]" value="<?= $c['id'] ?>" class="bulk-check-echos" onchange="updateBulk('echos')"></td>
          <td>
            <div class="cm-author"><?= htmlspecialchars($c['pseudo'] ?? '?', ENT_QUOTES, 'UTF-8') ?></div>
            <div style="margin-top:3px">
              <a href="<?= rtrim(BASE_URL, '/') ?>/les-echos-article.php?slug=<?= urlencode($c['ref_slug'] ?? '') ?>"
                 target="_blank" class="cm-ref-link">
                <?= htmlspecialchars(mb_strimwidth($c['ref_title'] ?? '—', 0, 36, '…'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
            <div style="font-size:.69rem;color:#9aadbc;margin-top:2px">XP : <?= (int)($c['user_xp'] ?? 0) ?></div>
          </td>
          <td><div class="cm-text-cell"><?= htmlspecialchars(mb_strimwidth($c['body'], 0, 200, '…'), ENT_QUOTES, 'UTF-8') ?></div></td>
          <td class="cm-date"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
          <td>
            <span class="cm-badge cm-badge-<?= $c['status'] ?>"><?= $c['status'] === 'visible' ? '✅ Visible' : '👁 Masqué' ?></span>
            <br style="margin:4px 0">
            <span class="cm-badge <?= $c['xp_awarded'] ? 'cm-badge-xp' : 'cm-badge-noxp' ?>">
              <?= $c['xp_awarded'] ? '⭐ +5 XP' : '○ Sans XP' ?>
            </span>
          </td>
          <td>
            <div class="cm-actions">
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="echos">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn <?= $c['status'] === 'visible' ? 'cm-btn-hide' : 'cm-btn-show' ?>">
                  <?= $c['status'] === 'visible' ? '👁 Masquer' : '✅ Restaurer' ?>
                </button>
              </form>
              <?php if (!$c['xp_awarded']): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="echos">
                <input type="hidden" name="action" value="award_xp">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-xp">⭐ +5 XP</button>
              </form>
              <?php else: ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="echos">
                <input type="hidden" name="action" value="revoke_xp">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-noxp">↩ −5 XP</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Supprimer ce commentaire ?<?= $c['xp_awarded'] ? ' Les XP seront retirés.' : '' ?>')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="echos">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-del">🗑 Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <?php if ($total_pages > 1): ?>
  <?php echo cm_pager($page, $total_pages, ['tab' => 'echos', 'status' => $filter_status !== 'all' ? $filter_status : null, 'xp' => $filter_xp !== 'all' ? $filter_xp : null, 'article_id' => $filter_article ?: null, 'q' => $search ?: null]); ?>
  <?php endif; ?>

  <div style="padding:12px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> commentaire<?= $total > 1 ? 's' : '' ?> · Page <?= $page ?>/<?= max(1,$total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<?php
// ═══════════════════════════════════════════════════════════════
// VUE : RANDOS
// ═══════════════════════════════════════════════════════════════
elseif ($tab === 'randos'):
?>

<div style="margin-bottom:14px;padding:12px 16px;background:rgba(14,165,233,.05);border:1px solid rgba(14,165,233,.2);border-radius:8px;font-size:.82rem;color:#0369a1">
  📋 Seules les participations avec un commentaire ou un avis sont affichées. Pour la validation des preuves, utilisez
  <a href="rando-validations.php" style="font-weight:800;color:#0369a1">Randos → Validations</a>.
</div>

<!-- Filtres Randos -->
<form method="GET" action="">
  <input type="hidden" name="tab" value="randos">
  <div class="cm-filters">
    <select name="rstatus" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"           <?= $filter_rando_status === 'all'           ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="stamped"       <?= $filter_rando_status === 'stamped'       ? 'selected' : '' ?>>🗺 Stampé</option>
      <option value="pending_proof" <?= $filter_rando_status === 'pending_proof' ? 'selected' : '' ?>>⏳ Preuve en attente</option>
      <option value="validated"     <?= $filter_rando_status === 'validated'     ? 'selected' : '' ?>>✅ Validé</option>
      <option value="rejected"      <?= $filter_rando_status === 'rejected'      ? 'selected' : '' ?>>❌ Rejeté</option>
    </select>
    <input type="text" name="q" class="cm-search" placeholder="Auteur, rando, texte…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;font-family:inherit;font-weight:700;font-size:.82rem;cursor:pointer">Filtrer</button>
    <?php if ($filter_rando_status !== 'all' || $search): ?>
    <a href="?tab=randos" style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">✕ Reset</a>
    <?php endif; ?>
  </div>
</form>

<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($rows)): ?>
  <div style="padding:40px;text-align:center;color:#6b7f96">Aucune participation avec commentaire.</div>
  <?php else: ?>

  <form method="POST" id="bulk-form-randos">
    <?= csrf_field() ?>
    <input type="hidden" name="source" value="rando">
    <div class="cm-bulk" id="bulk-bar-randos" style="display:none">
      <span id="bulk-count-randos" style="font-size:.82rem;font-weight:700;color:#3d5166"></span>
      <button type="button" class="cm-bulk-btn cm-bulk-clear" onclick="bulkAction('bulk-form-randos','bulk_clear_comment')">🧹 Effacer commentaires</button>
      <button type="button" class="cm-bulk-btn cm-bulk-del"   onclick="if(confirm('Supprimer ces participations ?')) bulkAction('bulk-form-randos','bulk_delete')">🗑 Supprimer</button>
    </div>

    <table class="cm-table">
      <thead>
        <tr>
          <th><input type="checkbox" onchange="toggleAll('randos',this.checked)"></th>
          <th>Rando / Auteur</th>
          <th>Commentaire · Avis</th>
          <th>Date</th>
          <th>Statut / XP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $c): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $c['id'] ?>" class="bulk-check-randos" onchange="updateBulk('randos')"></td>
          <td>
            <div class="cm-author"><?= htmlspecialchars($c['pseudo'] ?? '?', ENT_QUOTES, 'UTF-8') ?></div>
            <div style="margin-top:3px">
              <a href="<?= rtrim(BASE_URL, '/') ?>/rando.php?slug=<?= urlencode($c['ref_slug'] ?? '') ?>"
                 target="_blank" class="cm-ref-link">
                <?= htmlspecialchars(mb_strimwidth($c['ref_title'] ?? '—', 0, 36, '…'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
            <div style="font-size:.69rem;color:#9aadbc;margin-top:2px">XP : <?= (int)($c['user_xp'] ?? 0) ?></div>
          </td>
          <td>
            <?php if ($c['comment']): ?>
            <div class="cm-text-label">Commentaire</div>
            <div class="cm-text-cell"><?= htmlspecialchars(mb_strimwidth($c['comment'], 0, 180, '…'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($c['proof_review']): ?>
            <div class="cm-text-label" style="margin-top:8px">Avis / preuve</div>
            <div class="cm-text-cell"><?= htmlspecialchars(mb_strimwidth($c['proof_review'], 0, 180, '…'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </td>
          <td class="cm-date"><?= date('d/m/Y', strtotime($c['done_at'])) ?></td>
          <td>
            <?php
            $status_labels = ['stamped'=>'🗺 Stampé','pending_proof'=>'⏳ Preuve','validated'=>'✅ Validé','rejected'=>'❌ Rejeté'];
            $status_colors = ['stamped'=>'#0369a1','pending_proof'=>'#8a6020','validated'=>'#1a7a42','rejected'=>'#c0392b'];
            $sl = $status_labels[$c['status']] ?? $c['status'];
            $sc = $status_colors[$c['status']] ?? '#6b7f96';
            ?>
            <span class="cm-badge" style="background:rgba(0,0,0,.05);color:<?= $sc ?>"><?= $sl ?></span>
            <br style="margin:4px 0">
            <?php if ($c['xp_awarded'] > 0): ?>
            <span class="cm-badge cm-badge-xp">⭐ <?= (int)$c['xp_awarded'] ?> XP</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="cm-actions">
              <?php if ($c['comment']): ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Effacer ce commentaire ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="rando">
                <input type="hidden" name="action" value="clear_comment">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-clear">🧹 Vider cmmt</button>
              </form>
              <?php endif; ?>
              <?php if ($c['proof_review']): ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Effacer cet avis ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="rando">
                <input type="hidden" name="action" value="clear_review">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-clear">🧹 Vider avis</button>
              </form>
              <?php endif; ?>
              <a href="rando-validations.php?rando_id=<?= $c['rando_id'] ?>" class="cm-btn cm-btn-hide" style="text-decoration:none;display:block">⚙ Validation</a>
              <form method="POST" style="margin:0" onsubmit="return confirm('Supprimer cette participation (XP restitués si attribués) ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="rando">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-del">🗑 Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <?php if ($total_pages > 1): ?>
  <?php echo cm_pager($page, $total_pages, ['tab' => 'randos', 'rstatus' => $filter_rando_status !== 'all' ? $filter_rando_status : null, 'q' => $search ?: null]); ?>
  <?php endif; ?>

  <div style="padding:12px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> participation<?= $total > 1 ? 's' : '' ?> avec texte · Page <?= $page ?>/<?= max(1,$total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<?php
// ═══════════════════════════════════════════════════════════════
// VUE : MISSIONS
// ═══════════════════════════════════════════════════════════════
elseif ($tab === 'missions'):
?>

<div style="margin-bottom:14px;padding:12px 16px;background:rgba(14,165,233,.05);border:1px solid rgba(14,165,233,.2);border-radius:8px;font-size:.82rem;color:#0369a1">
  📋 Participations ayant une réponse texte ou un commentaire. Pour la validation des missions, utilisez le panneau Missions.
</div>

<!-- Filtres Missions -->
<form method="GET" action="">
  <input type="hidden" name="tab" value="missions">
  <div class="cm-filters">
    <select name="mstatus" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"            <?= $filter_mission_status === 'all'            ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="pending"        <?= $filter_mission_status === 'pending'        ? 'selected' : '' ?>>⏳ En attente</option>
      <option value="validated"      <?= $filter_mission_status === 'validated'      ? 'selected' : '' ?>>✅ Validé</option>
      <option value="auto_validated" <?= $filter_mission_status === 'auto_validated' ? 'selected' : '' ?>>🤖 Auto-validé</option>
      <option value="rejected"       <?= $filter_mission_status === 'rejected'       ? 'selected' : '' ?>>❌ Rejeté</option>
    </select>
    <input type="text" name="q" class="cm-search" placeholder="Auteur, mission, texte…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;font-family:inherit;font-weight:700;font-size:.82rem;cursor:pointer">Filtrer</button>
    <?php if ($filter_mission_status !== 'all' || $search): ?>
    <a href="?tab=missions" style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">✕ Reset</a>
    <?php endif; ?>
  </div>
</form>

<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($rows)): ?>
  <div style="padding:40px;text-align:center;color:#6b7f96">Aucune participation avec texte.</div>
  <?php else: ?>

  <form method="POST" id="bulk-form-missions">
    <?= csrf_field() ?>
    <input type="hidden" name="source" value="mission">
    <div class="cm-bulk" id="bulk-bar-missions" style="display:none">
      <span id="bulk-count-missions" style="font-size:.82rem;font-weight:700;color:#3d5166"></span>
      <button type="button" class="cm-bulk-btn cm-bulk-del" onclick="if(confirm('Supprimer ces participations ?')) bulkAction('bulk-form-missions','bulk_delete')">🗑 Supprimer</button>
    </div>

    <table class="cm-table">
      <thead>
        <tr>
          <th><input type="checkbox" onchange="toggleAll('missions',this.checked)"></th>
          <th>Mission / Auteur</th>
          <th>Réponse · Commentaire</th>
          <th>Date</th>
          <th>Statut / XP</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $c): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $c['id'] ?>" class="bulk-check-missions" onchange="updateBulk('missions')"></td>
          <td>
            <div class="cm-author"><?= htmlspecialchars($c['pseudo'] ?? '?', ENT_QUOTES, 'UTF-8') ?></div>
            <div style="margin-top:3px">
              <span class="cm-ref-link" style="color:#3d5166">
                <?= htmlspecialchars(mb_strimwidth($c['ref_title'] ?? '—', 0, 36, '…'), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <div style="font-size:.69rem;color:#9aadbc;margin-top:2px">XP : <?= (int)($c['user_xp'] ?? 0) ?></div>
          </td>
          <td>
            <?php if ($c['answer_text']): ?>
            <div class="cm-text-label">Réponse</div>
            <div class="cm-text-cell"><?= htmlspecialchars(mb_strimwidth($c['answer_text'], 0, 180, '…'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($c['comment']): ?>
            <div class="cm-text-label" style="margin-top:6px">Commentaire</div>
            <div class="cm-text-cell"><?= htmlspecialchars(mb_strimwidth($c['comment'], 0, 180, '…'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </td>
          <td class="cm-date"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
          <td>
            <?php
            $ms_labels = ['pending'=>'⏳ En attente','validated'=>'✅ Validé','auto_validated'=>'🤖 Auto','rejected'=>'❌ Rejeté'];
            $ms_colors = ['pending'=>'#8a6020','validated'=>'#1a7a42','auto_validated'=>'#0369a1','rejected'=>'#c0392b'];
            $ml = $ms_labels[$c['status']] ?? $c['status'];
            $mc = $ms_colors[$c['status']] ?? '#6b7f96';
            ?>
            <span class="cm-badge" style="background:rgba(0,0,0,.05);color:<?= $mc ?>"><?= $ml ?></span>
            <?php if ($c['xp_awarded'] > 0): ?>
            <br style="margin:4px 0">
            <span class="cm-badge cm-badge-xp">⭐ <?= (int)$c['xp_awarded'] ?> XP</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="cm-actions">
              <?php if ($c['answer_text']): ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Effacer la réponse texte ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="mission">
                <input type="hidden" name="action" value="clear_answer">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-clear">🧹 Vider réponse</button>
              </form>
              <?php endif; ?>
              <?php if ($c['comment']): ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Effacer le commentaire ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="mission">
                <input type="hidden" name="action" value="clear_comment">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-clear">🧹 Vider cmmt</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Supprimer cette participation (XP restitués si attribués) ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="mission">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-del">🗑 Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <?php if ($total_pages > 1): ?>
  <?php echo cm_pager($page, $total_pages, ['tab' => 'missions', 'mstatus' => $filter_mission_status !== 'all' ? $filter_mission_status : null, 'q' => $search ?: null]); ?>
  <?php endif; ?>

  <div style="padding:12px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> participation<?= $total > 1 ? 's' : '' ?> avec texte · Page <?= $page ?>/<?= max(1,$total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<?php
// ═══════════════════════════════════════════════════════════════
// VUE : KTC
// ═══════════════════════════════════════════════════════════════
elseif ($tab === 'ktc'):
?>

<!-- Filtres KTC -->
<form method="GET" action="">
  <input type="hidden" name="tab" value="ktc">
  <div class="cm-filters">
    <select name="correct" class="cm-filter-select" onchange="this.form.submit()">
      <option value="all"  <?= $filter_ktc_correct === 'all'  ? 'selected' : '' ?>>Toutes les props.</option>
      <option value="yes"  <?= $filter_ktc_correct === 'yes'  ? 'selected' : '' ?>>✅ Correctes</option>
      <option value="no"   <?= $filter_ktc_correct === 'no'   ? 'selected' : '' ?>>❌ Incorrectes</option>
      <option value="none" <?= $filter_ktc_correct === 'none' ? 'selected' : '' ?>>○ Non évaluées</option>
    </select>
    <input type="text" name="q" class="cm-search" placeholder="Auteur, épisode, proposition…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" style="padding:8px 16px;background:#ea5649;color:#fff;border:none;border-radius:7px;font-family:inherit;font-weight:700;font-size:.82rem;cursor:pointer">Filtrer</button>
    <?php if ($filter_ktc_correct !== 'all' || $search): ?>
    <a href="?tab=ktc" style="padding:8px 14px;color:#6b7f96;font-size:.78rem;font-weight:700;text-decoration:none">✕ Reset</a>
    <?php endif; ?>
  </div>
</form>

<div class="adm-card" style="padding:0;overflow:hidden">
  <?php if (empty($rows)): ?>
  <div style="padding:40px;text-align:center;color:#6b7f96">Aucune proposition KTC trouvée.</div>
  <?php else: ?>

  <form method="POST" id="bulk-form-ktc">
    <?= csrf_field() ?>
    <input type="hidden" name="source" value="ktc">
    <div class="cm-bulk" id="bulk-bar-ktc" style="display:none">
      <span id="bulk-count-ktc" style="font-size:.82rem;font-weight:700;color:#3d5166"></span>
      <button type="button" class="cm-bulk-btn cm-bulk-del" onclick="if(confirm('Supprimer ces propositions ?')) bulkAction('bulk-form-ktc','bulk_delete')">🗑 Supprimer</button>
    </div>

    <table class="cm-table">
      <thead>
        <tr>
          <th><input type="checkbox" onchange="toggleAll('ktc',this.checked)"></th>
          <th>Épisode / Auteur</th>
          <th>Proposition</th>
          <th>Date</th>
          <th>Résultat</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $c): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= $c['id'] ?>" class="bulk-check-ktc" onchange="updateBulk('ktc')"></td>
          <td>
            <div class="cm-author"><?= htmlspecialchars($c['pseudo'] ?? '?', ENT_QUOTES, 'UTF-8') ?></div>
            <div style="margin-top:3px;font-size:.73rem;color:#3d5166;font-weight:700">
              <?= htmlspecialchars(mb_strimwidth($c['ref_title'] ?? '—', 0, 36, '…'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="font-size:.69rem;color:#9aadbc;margin-top:2px">XP : <?= (int)($c['user_xp'] ?? 0) ?></div>
          </td>
          <td><div class="cm-text-cell"><?= htmlspecialchars(mb_strimwidth($c['proposition'], 0, 200, '…'), ENT_QUOTES, 'UTF-8') ?></div></td>
          <td class="cm-date"><?= date('d/m/Y', strtotime($c['submitted_at'])) ?></td>
          <td>
            <?php if ($c['is_correct'] === null): ?>
            <span class="cm-badge cm-badge-pending">○ Non évalué</span>
            <?php elseif ($c['is_correct']): ?>
            <span class="cm-badge cm-badge-correct">✅ Correct</span>
            <?php else: ?>
            <span class="cm-badge cm-badge-incorrect">❌ Incorrect</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="cm-actions">
              <?php if (!$c['is_correct']): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="ktc">
                <input type="hidden" name="action" value="mark_correct">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-ok">✅ Correct</button>
              </form>
              <?php endif; ?>
              <?php if ($c['is_correct'] !== 0): ?>
              <form method="POST" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="ktc">
                <input type="hidden" name="action" value="mark_incorrect">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-warn">❌ Incorrect</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="margin:0" onsubmit="return confirm('Supprimer cette proposition ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="ktc">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= $c['id'] ?>">
                <button type="submit" class="cm-btn cm-btn-del">🗑 Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <?php if ($total_pages > 1): ?>
  <?php echo cm_pager($page, $total_pages, ['tab' => 'ktc', 'correct' => $filter_ktc_correct !== 'all' ? $filter_ktc_correct : null, 'q' => $search ?: null]); ?>
  <?php endif; ?>

  <div style="padding:12px 18px;font-size:.73rem;color:#9aadbc;border-top:1px solid #f0ece7">
    <?= $total ?> proposition<?= $total > 1 ? 's' : '' ?> · Page <?= $page ?>/<?= max(1,$total_pages) ?>
  </div>
  <?php endif; ?>
</div>

<?php endif; // fin switch tabs ?>

<!-- Guide process -->
<div class="adm-card" style="margin-top:20px;background:rgba(14,165,233,.04);border:1px solid rgba(14,165,233,.15)">
  <p class="adm-card-title" style="color:#0369a1">📋 Process de modération</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;font-size:.81rem;color:#0f1e2d;line-height:1.7">
    <div><strong>👁 Masquer (Échos)</strong><br>Invisible en public, conservé en base. Réversible.</div>
    <div><strong>🧹 Vider texte (Randos/Missions)</strong><br>Efface le contenu offensant sans supprimer la participation ni les XP.</div>
    <div><strong>🗑 Supprimer</strong><br>Suppression définitive. Les XP sont automatiquement restitués si attribués.</div>
    <div><strong>⭐ XP Échos</strong><br>Attribuer/retirer les +5 XP liés à un commentaire. Utile pour corriger les bugs passés.</div>
    <div><strong>✅/❌ KTC</strong><br>Marquer une proposition comme correcte ou incorrecte après révélation de l'objet.</div>
    <div><strong>☑ Sélection multiple</strong><br>Cochez plusieurs lignes pour appliquer une action en bloc.</div>
  </div>
</div>

<?php
// ── Helper pagination ─────────────────────────────────────────
function cm_pager(int $page, int $total_pages, array $qs_params): string {
    $qs = http_build_query(array_filter($qs_params, fn($v) => $v !== null && $v !== ''));
    $qs = $qs ? '&' . $qs : '';
    $html = '<div class="cm-pager" style="padding:16px">';
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i === $page) {
            $html .= "<span class=\"active\">{$i}</span>";
        } elseif ($i === 1 || $i === $total_pages || abs($i - $page) <= 2) {
            $html .= "<a href=\"?p={$i}{$qs}\">{$i}</a>";
        } elseif (abs($i - $page) === 3) {
            $html .= "<span>…</span>";
        }
    }
    $html .= '</div>';
    return $html;
}
?>

<script>
function toggleAll(group, checked) {
  document.querySelectorAll('.bulk-check-' + group).forEach(cb => cb.checked = checked);
  updateBulk(group);
}

function updateBulk(group) {
  const checked = document.querySelectorAll('.bulk-check-' + group + ':checked').length;
  const bar = document.getElementById('bulk-bar-' + group);
  const countEl = document.getElementById('bulk-count-' + group);
  if (countEl) countEl.textContent = checked + ' sélectionné(s)';
  if (bar) bar.style.display = checked > 0 ? 'flex' : 'none';
}

function bulkAction(formId, action) {
  const form = document.getElementById(formId);
  if (!form) return;
  // remove any previously injected action field
  form.querySelectorAll('input[data-bulk-action]').forEach(el => el.remove());
  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = 'action';
  hidden.value = action;
  hidden.dataset.bulkAction = '1';
  form.appendChild(hidden);
  form.submit();
}
</script>

<?php require_once '_admin-footer.php'; ?>
