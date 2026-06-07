<?php
// ajax/echo-upload.php — Upload galerie Les Échos (admin uniquement)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin.php';

header('Content-Type: application/json');

function json_err(string $msg): never {
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (!is_admin()) {
    json_err('Non autorisé');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    json_err('Jeton CSRF invalide');
}

if (empty($_FILES['file']['tmp_name']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_err('Aucun fichier reçu');
}

$result = upload_editorial_image($_FILES['file'], 'echos');
echo json_encode($result);
