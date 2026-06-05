<?php
// AJAX : marquer notification(s) comme lues
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/repositories.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non connecté']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token invalide']);
    exit;
}

$uid      = (int)current_user()['id'];
$notif_id = isset($_POST['id']) ? (int)$_POST['id'] : null;

mark_notifications_read($uid, $notif_id);

echo json_encode([
    'ok'            => true,
    'unread_count'  => count_unread_notifications($uid),
]);
