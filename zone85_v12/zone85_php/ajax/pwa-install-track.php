<?php
// ajax/pwa-install-track.php — Trace une installation PWA
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$event    = in_array($_POST['event'] ?? '', ['prompt_accepted','installed_event','manual']) ? ($_POST['event']) : 'unknown';
$platform = in_array($_POST['platform'] ?? '', ['android','ios','desktop']) ? ($_POST['platform']) : 'unknown';
$ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

$user    = current_user();
$user_id = $user ? (int)$user['id'] : null;

$pdo = db();
if ($pdo) {
    try {
        $pdo->prepare("
            INSERT INTO pwa_installs (user_id, platform, user_agent, installed_at)
            VALUES (:uid, :platform, :ua, NOW())
        ")->execute([':uid' => $user_id, ':platform' => $platform, ':ua' => $ua]);

        if ($user_id && $event === 'prompt_accepted') {
            $pdo->prepare("
                UPDATE users SET pwa_installed_at = NOW(),
                    pwa_install_count = pwa_install_count + 1
                WHERE id = :id
            ")->execute([':id' => $user_id]);
        }
    } catch (PDOException $e) {
        error_log('[ZONE85 pwa-install-track] ' . $e->getMessage());
    }
}

echo json_encode(['ok' => true]);
