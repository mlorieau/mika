<?php
// ============================================================
// ajax/ktc-answer.php — Valider une réponse KTC V11
// ============================================================
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'login_required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'reason' => 'csrf_invalid']);
    exit;
}

$user       = current_user();
$user_id    = (int)$user['id'];
$question_id = (int)($_POST['question_id'] ?? 0);
$given       = strtolower(trim($_POST['answer'] ?? ''));

if (!$question_id || !in_array($given, ['a','b','c','d'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid_params']);
    exit;
}

$pdo = db();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'reason' => 'db_unavailable']);
    exit;
}

try {
    // Récupérer la question
    $s = $pdo->prepare("SELECT * FROM ktc_questions WHERE id=:id AND is_active=1 LIMIT 1");
    $s->execute([':id' => $question_id]);
    $q = $s->fetch();

    if (!$q) {
        echo json_encode(['ok' => false, 'reason' => 'question_not_found']);
        exit;
    }

    // Anti-doublon
    $chk = $pdo->prepare("SELECT id, is_correct FROM ktc_answers WHERE user_id=:uid AND question_id=:qid LIMIT 1");
    $chk->execute([':uid' => $user_id, ':qid' => $question_id]);
    $existing = $chk->fetch();

    if ($existing) {
        echo json_encode([
            'ok'          => false,
            'reason'      => 'already_answered',
            'was_correct' => (bool)$existing['is_correct'],
            'correct'     => $q['correct'],
            'explanation' => $q['explanation'] ?? null,
        ]);
        exit;
    }

    $is_correct = ($given === $q['correct']);
    $xp_earned  = $is_correct ? (int)$q['xp_reward'] : 0;

    $pdo->beginTransaction();

    // Enregistrer la réponse
    $pdo->prepare("
        INSERT INTO ktc_answers (user_id, question_id, given_answer, is_correct, xp_earned, answered_at)
        VALUES (:uid, :qid, :ans, :ok, :xp, NOW())
    ")->execute([
        ':uid' => $user_id,
        ':qid' => $question_id,
        ':ans' => $given,
        ':ok'  => $is_correct ? 1 : 0,
        ':xp'  => $xp_earned,
    ]);

    // Attribuer les XP si bonne réponse
    if ($xp_earned > 0) {
        $pdo->prepare("UPDATE users SET xp_total = xp_total + :xp WHERE id = :id")
            ->execute([':xp' => $xp_earned, ':id' => $user_id]);

        $pdo->prepare("
            INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
            VALUES (:uid, 'ktc_correct', :qid, :xp, 'KTC correct')
        ")->execute([':uid' => $user_id, ':qid' => $question_id, ':xp' => $xp_earned]);

        // MAJ session
        $_SESSION['user']['xp_total'] = (int)($_SESSION['user']['xp_total'] ?? 0) + $xp_earned;
    }

    // Feed communautaire (non bloquant)
    try {
        $pdo->prepare("
            INSERT INTO community_feed (event_type, user_id, title, body, icon_emoji, link_url)
            VALUES ('ktc_win', :uid, :title, :body, '🥐', '/ktc.php')
        ")->execute([
            ':uid'   => $user_id,
            ':title' => ($user['pseudo'] ?? 'Un membre') . ' a résolu un KTC !',
            ':body'  => substr($q['question'], 0, 100),
        ]);
    } catch (PDOException $e) { /* feed table peut ne pas exister */ }

    // Badge KTC (tous les 10 bonnes réponses)
    try {
        $cnt_s = $pdo->prepare("SELECT COUNT(*) FROM ktc_answers WHERE user_id=:uid AND is_correct=1");
        $cnt_s->execute([':uid' => $user_id]);
        $cnt = (int)$cnt_s->fetchColumn();

        if ($cnt > 0 && $cnt % 10 === 0) {
            $badge_s = $pdo->prepare("SELECT id FROM badges WHERE slug='ktc-champion' LIMIT 1");
            $badge_s->execute();
            $badge = $badge_s->fetch();
            if ($badge) {
                $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id, source_type) VALUES (:uid,:bid,'ktc')")
                    ->execute([':uid' => $user_id, ':bid' => (int)$badge['id']]);
            }
        }
    } catch (PDOException $e) { /* non bloquant */ }

    $pdo->commit();

    // Calculer le score global KTC de l'utilisateur
    $stats_s = $pdo->prepare("
        SELECT COUNT(*) AS total, SUM(is_correct) AS correct, COALESCE(SUM(xp_earned),0) AS xp
        FROM ktc_answers WHERE user_id=:uid
    ");
    $stats_s->execute([':uid' => $user_id]);
    $stats = $stats_s->fetch();

    echo json_encode([
        'ok'          => true,
        'is_correct'  => $is_correct,
        'correct'     => $q['correct'],
        'explanation' => $q['explanation'] ?? null,
        'xp_earned'   => $xp_earned,
        'xp_total'    => (int)($_SESSION['user']['xp_total'] ?? 0),
        'stats'       => [
            'total'    => (int)$stats['total'],
            'correct'  => (int)$stats['correct'],
            'xp'       => (int)$stats['xp'],
        ],
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[ktc-answer] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'server_error']);
}
