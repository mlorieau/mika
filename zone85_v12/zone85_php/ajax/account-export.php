<?php
// ajax/account-export.php — Export RGPD donnees utilisateur (JSON)
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

require_login('../login.php');
$user_session = current_user();
$user_id = (int)$user_session['id'];

$pdo = db();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['error' => 'Base de donnees indisponible.']);
    exit;
}

$data = [];

try {
    // Infos profil
    $s = $pdo->prepare("SELECT id,pseudo,email,first_name,last_name,bio,xp_total,level,created_at,clan_id FROM users WHERE id=:id LIMIT 1");
    $s->execute([':id' => $user_id]);
    $data['profil'] = $s->fetch(PDO::FETCH_ASSOC) ?: [];

    // XP logs
    $s = $pdo->prepare("SELECT source_type,xp_amount,reason,created_at FROM xp_logs WHERE user_id=:id ORDER BY created_at ASC");
    $s->execute([':id' => $user_id]);
    $data['xp_logs'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Participations
    $s = $pdo->prepare("SELECT p.status,p.xp_awarded,p.created_at,m.title AS mission FROM participations p JOIN missions m ON m.id=p.mission_id WHERE p.user_id=:id ORDER BY p.created_at ASC");
    $s->execute([':id' => $user_id]);
    $data['participations'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Badges
    $s = $pdo->prepare("SELECT b.title AS name,b.slug,ub.awarded_at FROM user_badges ub JOIN badges b ON b.id=ub.badge_id WHERE ub.user_id=:id ORDER BY ub.awarded_at ASC");
    $s->execute([':id' => $user_id]);
    $data['badges'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Objets trouves
    $s = $pdo->prepare("SELECT mc.title,uc.found_at FROM user_collectibles uc JOIN mission_collectibles mc ON mc.id=uc.collectible_id WHERE uc.user_id=:id ORDER BY uc.found_at ASC");
    $s->execute([':id' => $user_id]);
    $data['collectibles'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Acceptations legales
    $s = $pdo->prepare("SELECT document_type,document_version,accepted_at FROM legal_acceptances WHERE user_id=:id ORDER BY accepted_at ASC");
    $s->execute([':id' => $user_id]);
    $data['legal_acceptances'] = $s->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[account-export] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recuperation des donnees.']);
    exit;
}

$json = json_encode([
    'export_date' => date('Y-m-d H:i:s'),
    'user_id'     => $user_id,
    'data'        => $data,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$filename = 'zone85-data-' . $user_id . '-' . date('Ymd') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
header('Cache-Control: no-store');
echo $json;
