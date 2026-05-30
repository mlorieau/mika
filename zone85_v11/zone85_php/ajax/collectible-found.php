<?php
// ============================================================
// ajax/collectible-found.php — Endpoint AJAX : objet trouvé
// POST : collectible_id, csrf_token
// Réponse : JSON
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/repositories.php';

// Seules les requêtes POST sont acceptées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'reason'=>'method','message'=>'Méthode non autorisée.']);
    exit;
}

// Utilisateur connecté ?
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'ok'      => false,
        'reason'  => 'login_required',
        'message' => 'Connecte-toi pour valider ta trouvaille.',
    ]);
    exit;
}

// CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'reason'=>'csrf','message'=>'Token de sécurité invalide.']);
    exit;
}

$collectible_id = (int)($_POST['collectible_id'] ?? 0);
if ($collectible_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'reason'=>'invalid','message'=>'Paramètre manquant.']);
    exit;
}

$user    = current_user();
$user_id = (int)$user['id'];

$result = process_collectible_found($user_id, $collectible_id);

// Mettre à jour XP en session si succès + complétion
if ($result['ok'] && $result['completed'] && $result['xp_awarded'] > 0) {
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['xp_total'] = (int)$_SESSION['user']['xp_total'] + $result['xp_awarded'];
        if (function_exists('get_user_level_from_xp')) {
            $_SESSION['user']['level'] = get_user_level_from_xp($_SESSION['user']['xp_total']);
        }
    }
}

// URL du success_gif si défini
if (!empty($result['success_gif'])) {
    $result['success_gif_url'] = url($result['success_gif']);
}

// Ajouter le titre de l'objet si disponible
if ($result['ok'] && empty($result['object_title'])) {
    try {
        $pdo2 = db();
        if ($pdo2) {
            $st = $pdo2->prepare("SELECT title FROM mission_collectibles WHERE id = :id LIMIT 1");
            $st->execute([':id' => $collectible_id]);
            $row = $st->fetch();
            if ($row) $result['object_title'] = $row['title'];
        }
    } catch (Throwable $e) { /* silence */ }
}

// Nettoyer les champs internes avant envoi
unset($result['success_gif']);
unset($result['debug']);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
