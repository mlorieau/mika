<?php
// ============================================================
// ZONE 85 — ajax/passport.php v11 Premium
// Retourne le Passeport Vendéen d'un joueur en JSON
// Pas d'authentification requise — anti-abus user_id integer
// UTF-8 sans BOM
// ============================================================

// ENDPOINT PUBLIC INTENTIONNEL — retourne uniquement des données publiques du joueur
// (pseudo, clan, niveau, XP, badges visibles, avatar). Aucune donnée sensible exposée.
// Protégé par rate-limiting (60 req/min/IP) et validation entier sur user_id.

// ── Headers ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ── Anti-abus : user_id doit être un entier valide ──────────
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
]);
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'user_id invalide'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Bootstrap minimal ────────────────────────────────────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/repositories.php';

$pdo = db();

// Rate limiting : 60 requêtes par IP par minute
if (!check_rate_limit('passport_api', 60, 60)) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode(['ok' => false, 'error' => 'Trop de requêtes. Veuillez patienter.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'DB indisponible'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ── Données joueur ───────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.pseudo,
            u.avatar_type,
            u.avatar_config,
            u.avatar_file,
            u.level,
            u.xp_total,
            u.created_at,
            c.slug AS clan_slug,
            c.name AS clan_name
        FROM users u
        LEFT JOIN clans c ON c.id = u.clan_id
        WHERE u.id = :uid
          AND u.status = 'active'
          AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':uid' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Joueur introuvable'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Niveau ───────────────────────────────────────────────
    $level      = max(1, (int)($user['level'] ?? get_user_level_from_xp((int)$user['xp_total'])));
    $level_name = get_level_name($level);

    // ── Badges récents (3 max) ───────────────────────────────
    $badges       = [];
    $badges_count = 0;
    try {
        $bstmt = $pdo->prepare("
            SELECT b.title, b.icon_emoji AS icon, b.rarity
            FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = :uid
            ORDER BY ub.awarded_at DESC
            LIMIT 3
        ");
        $bstmt->execute([':uid' => $user_id]);
        $badges = $bstmt->fetchAll(PDO::FETCH_ASSOC);

        $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = :uid");
        $cnt_stmt->execute([':uid' => $user_id]);
        $badges_count = (int)$cnt_stmt->fetchColumn();
    } catch (PDOException $e) {
        // Silencieux si la table n'existe pas encore
    }

    // ── Participations validées ──────────────────────────────
    $participations = 0;
    try {
        $pstmt = $pdo->prepare("
            SELECT COUNT(*) FROM participations
            WHERE user_id = :uid
              AND status IN ('validated','auto_validated')
        ");
        $pstmt->execute([':uid' => $user_id]);
        $participations = (int)$pstmt->fetchColumn();
    } catch (PDOException $e) {
        // Silencieux si la table n'existe pas encore
    }

    // ── Collectibles trouvés ────────────────────────────────
    $collectibles = 0;
    try {
        $cstmt = $pdo->prepare("SELECT COUNT(*) FROM user_collectibles WHERE user_id = :uid");
        $cstmt->execute([':uid' => $user_id]);
        $collectibles = (int)$cstmt->fetchColumn();
    } catch (PDOException $e) {
        try {
            $cstmt2 = $pdo->prepare("SELECT COUNT(*) FROM collectible_finds WHERE user_id = :uid");
            $cstmt2->execute([':uid' => $user_id]);
            $collectibles = (int)$cstmt2->fetchColumn();
        } catch (PDOException $e2) {
            // Silencieux
        }
    }

    // ── Avatar ───────────────────────────────────────────────
    $avatar_type  = $user['avatar_type'] ?? 'preset';
    $avatar_emoji = '&#128304;'; // boussole (entité HTML sûre)
    $avatar_url   = null;

    if ($avatar_type === 'upload' && !empty($user['avatar_file'])) {
        $base       = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $avatar_url = $base . '/' . ltrim($user['avatar_file'], '/');
    } else {
        $cfg = json_decode($user['avatar_config'] ?? '{}', true);
        if (!empty($cfg['emoji'])) {
            $avatar_emoji = $cfg['emoji'];
        }
    }

    // ── Date d'inscription ───────────────────────────────────
    $joined = 'Inconnue';
    if (!empty($user['created_at'])) {
        $ts = strtotime($user['created_at']);
        if ($ts) $joined = date('d/m/Y', $ts);
    }

    // ── Réponse JSON ─────────────────────────────────────────
    echo json_encode([
        'ok'             => true,
        'id'             => (int)$user['id'],
        'pseudo'         => (string)$user['pseudo'],
        'clan_name'      => (string)($user['clan_name'] ?? ''),
        'clan_slug'      => (string)($user['clan_slug'] ?? ''),
        'level'          => $level,
        'level_name'     => $level_name,
        'xp_total'       => (int)$user['xp_total'],
        'badges_count'   => $badges_count,
        'participations' => $participations,
        'collectibles'   => $collectibles,
        'joined'         => $joined,
        'avatar_type'    => $avatar_type,
        'avatar_emoji'   => $avatar_emoji,
        'avatar_url'     => $avatar_url,
        'badges'         => array_map(fn($b) => [
            'title'  => (string)($b['title']  ?? ''),
            'icon'   => (string)($b['icon']   ?? ''),
            'rarity' => (string)($b['rarity'] ?? 'common'),
        ], $badges),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[ZONE85] ajax/passport.php : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
}
