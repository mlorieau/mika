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

if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_err('Aucun fichier ou erreur d\'envoi (code ' . ($_FILES['file']['error'] ?? '?') . ')');
}

$file = $_FILES['file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
    json_err('Format non autorisé (JPG, PNG, WebP uniquement)');
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
    json_err('Type MIME invalide : ' . $mime);
}

if ($file['size'] > UPLOAD_MAX_SIZE_MEDIA) {
    json_err('Fichier trop volumineux (max ' . round(UPLOAD_MAX_SIZE_MEDIA / 1048576) . ' Mo)');
}

$dir = BASE_PATH . 'uploads/echos/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    json_err('Échec de l\'enregistrement du fichier');
}

echo json_encode(['ok' => true, 'path' => 'uploads/echos/' . $filename]);
