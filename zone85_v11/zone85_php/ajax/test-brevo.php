<?php
// ajax/test-brevo.php — Test de connexion Brevo depuis le BO Settings
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/functions.php';
require_once '../includes/settings.php';
require_once '../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

// Admin uniquement
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès interdit.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF invalide.']);
    exit;
}

$to = filter_var(trim($_POST['test_email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$to) {
    echo json_encode(['ok' => false, 'error' => 'Adresse email invalide.']);
    exit;
}

$admin = current_user();
$result = send_email(
    $to,
    'Test Brevo — Zone85',
    'welcome',
    ['pseudo' => $admin['pseudo'] ?? 'Admin'],
    $admin['pseudo'] ?? 'Admin Zone85'
);

echo json_encode($result);
