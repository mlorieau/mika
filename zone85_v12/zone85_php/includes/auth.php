<?php
// ============================================================
// ZONE 85 — Authentification V1
// Fonctions de session, login, logout, inscription.
// ============================================================

// ── Session sécurisée ─────────────────────────────────────────

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $name = defined('SESSION_NAME') ? SESSION_NAME : 'zone85_session';
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Utilisateur courant + timeout de session ──────────────────
// SESSION_TIMEOUT (config.php) = 3600 s par défaut.
// last_activity est mis à jour à chaque requête authentifiée.

function current_user(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    if (!isset($_SESSION['user']))               return null;

    $timeout       = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    $last_activity = $_SESSION['last_activity'] ?? time();

    if (time() - $last_activity > $timeout) {
        // Session expirée — nettoyage silencieux
        $_SESSION = [];
        session_destroy();
        return null;
    }

    $_SESSION['last_activity'] = time();
    return $_SESSION['user'];
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

// ── Login / Logout ─────────────────────────────────────────────

function login_user(array $user): void {
    session_regenerate_id(true);
    // Décode l'emoji depuis avatar_config si présent
    $avatar_config = [];
    if (!empty($user['avatar_config'])) {
        $avatar_config = json_decode($user['avatar_config'], true) ?? [];
    }
    $avatar_key = $user['avatar_type'] === 'upload'
        ? ($user['avatar_file'] ?? '🧭')
        : ($avatar_config['emoji'] ?? '🧭');

    $_SESSION['user'] = [
        'id'         => (int)$user['id'],
        'pseudo'     => $user['pseudo'],
        'email'      => $user['email'],
        'clan_id'    => (int)($user['clan_id'] ?? 0),
        'clan_slug'  => $user['clan_slug'] ?? '',
        'level'      => (int)($user['level'] ?? 1),
        'xp_total'   => (int)($user['xp_total'] ?? 0),
        'avatar_type'=> $user['avatar_type'] ?? 'preset',
        'avatar_key' => $avatar_key,
        'role'       => $user['role'] ?? 'member',
    ];
    $_SESSION['last_activity'] = time();

    $pdo = db();
    if ($pdo) {
        try {
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")
                ->execute([':id' => (int)$user['id']]);
        } catch (PDOException $e) {
            error_log('[ZONE85] login_user : ' . $e->getMessage());
        }
    }
}

function logout_user(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 86400,
            $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']
        );
        session_destroy();
    }
}

// ── Finders ────────────────────────────────────────────────────

function find_user_by_email(string $email): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, c.slug AS clan_slug
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.email = :email AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] find_user_by_email : ' . $e->getMessage());
        return null;
    }
}

function find_user_by_id(int $id): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, c.slug AS clan_slug
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.id = :id AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] find_user_by_id : ' . $e->getMessage());
        return null;
    }
}

// ── Inscription ────────────────────────────────────────────────

function register_user(array $data): array {
    $pdo = db();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'Base de données non disponible.'];
    }

    // Vérification côté serveur de la confirmation du mot de passe
    if (isset($data['password_confirm']) && $data['password'] !== $data['password_confirm']) {
        return ['ok' => false, 'field' => 'password', 'error' => 'Les mots de passe ne correspondent pas.'];
    }

    try {
        // Email unique (avant transaction pour éviter un lock inutile)
        $s = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $s->execute([':e' => $data['email']]);
        if ($s->fetch()) {
            return ['ok' => false, 'field' => 'email', 'error' => 'Cette adresse email est déjà utilisée.'];
        }

        // Pseudo unique
        $s = $pdo->prepare("SELECT id FROM users WHERE pseudo = :p LIMIT 1");
        $s->execute([':p' => $data['pseudo']]);
        if ($s->fetch()) {
            return ['ok' => false, 'field' => 'pseudo', 'error' => 'Ce pseudo est déjà pris.'];
        }

        $pdo->beginTransaction();

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO users
                (email, password_hash, pseudo, first_name, last_name, clan_id,
                 avatar_type, avatar_config, avatar_file, bio,
                 xp_total, level, newsletter_optin,
                 accepted_cgu_at, accepted_privacy_at, status, role)
            VALUES
                (:email, :hash, :pseudo, :fname, :lname, :clan_id,
                 :avatar_type, :avatar_config, :avatar_file, :bio,
                 50, 1, :newsletter,
                 NOW(), NOW(), 'active', 'member')
        ");
        $stmt->execute([
            ':email'        => $data['email'],
            ':hash'         => $hash,
            ':pseudo'       => $data['pseudo'],
            ':fname'        => $data['first_name'] ?? '',
            ':lname'        => $data['last_name']  ?? '',
            ':clan_id'      => (int)$data['clan_id'],
            ':avatar_type'  => $data['avatar_type'],
            ':avatar_config'=> $data['avatar_config'] ?? null,
            ':avatar_file'  => $data['avatar_file']   ?? null,
            ':bio'          => $data['bio']            ?? null,
            ':newsletter'   => $data['newsletter'] ? 1 : 0,
        ]);
        $user_id = (int)$pdo->lastInsertId();

        // XP de bienvenue
        $pdo->prepare("
            INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
            VALUES (:uid, 'registration', :src_id, 50, 'Bienvenue dans la Zone')
        ")->execute([':uid' => $user_id, ':src_id' => $user_id]);

        // Acceptations légales
        $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        foreach (['cgu' => 'cgu_v1', 'privacy' => 'confidentialite_v1'] as $type => $version) {
            $pdo->prepare("
                INSERT INTO legal_acceptances (user_id, document_type, document_version, ip_hash)
                VALUES (:uid, :type, :version, :ip)
            ")->execute([
                ':uid'     => $user_id,
                ':type'    => $type,
                ':version' => $version,
                ':ip'      => $ip_hash,
            ]);
        }

        $pdo->commit();

        // Badge de bienvenue — non-bloquant, hors transaction
        try {
            $s = $pdo->prepare("SELECT id FROM badges WHERE slug = 'pionnier-zone' LIMIT 1");
            $s->execute();
            $badge = $s->fetch();
            if ($badge) {
                $pdo->prepare("
                    INSERT IGNORE INTO user_badges (user_id, badge_id, source_type)
                    VALUES (:uid, :bid, 'registration')
                ")->execute([':uid' => $user_id, ':bid' => (int)$badge['id']]);
            }
        } catch (PDOException $e) {
            error_log('[ZONE85] register_user badge : ' . $e->getMessage());
        }

        // Email de bienvenue Brevo — non-bloquant, hors transaction
        try {
            if (function_exists('queue_email')) {
                // Récupérer le clan pour personnaliser l'email
                $clan_names = [1 => 'Bocage', 2 => 'Littoral', 3 => 'Marais'];
                $clan_label = $clan_names[(int)$data['clan_id']] ?? 'Zone85';
                queue_email(
                    $data['email'],
                    'welcome',
                    [
                        'pseudo'     => $data['pseudo'],
                        'clan_label' => $clan_label,
                        'clan_id'    => (int)$data['clan_id'],
                    ],
                    $user_id,
                    $data['pseudo'] ?? null
                );
            }
        } catch (Throwable $e) {
            error_log('[ZONE85] register_user email : ' . $e->getMessage());
        }

        return ['ok' => true, 'user_id' => $user_id];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[ZONE85] register_user : ' . $e->getMessage());
        $debug = (defined('APP_ENV') && APP_ENV === 'dev') ? $e->getMessage() : null;
        return [
            'ok'          => false,
            'error'       => 'Une erreur est survenue. Veuillez réessayer.',
            'debug_error' => $debug,
        ];
    }
}

// ── Upload avatar ──────────────────────────────────────────────

function upload_avatar(array $file): array {
    $max_size      = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 2 * 1024 * 1024;
    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd (max 2 Mo).',
            UPLOAD_ERR_PARTIAL  => 'Envoi interrompu.',
            default             => 'Erreur lors de l\'envoi du fichier.',
        };
        return ['ok' => false, 'error' => $msg];
    }

    if ($file['size'] > $max_size) {
        return ['ok' => false, 'error' => 'Fichier trop lourd (max 2 Mo).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, $allowed_types)) {
        return ['ok' => false, 'error' => 'Type non autorisé. Utilisez jpg, png ou webp.'];
    }

    // Vérification dimensions — évite les images DoS (ex: 50 000×50 000 px)
    $img_size = @getimagesize($file['tmp_name']);
    if ($img_size === false) {
        return ['ok' => false, 'error' => 'Fichier image illisible.'];
    }
    if ($img_size[0] > 4000 || $img_size[1] > 4000) {
        return ['ok' => false, 'error' => 'Image trop grande (max 4000×4000 px).'];
    }

    $upload_dir = defined('BASE_PATH')
        ? BASE_PATH . 'uploads/avatars/'
        : dirname(__DIR__) . '/uploads/avatars/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext      = $allowed_types[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Impossible d\'enregistrer la photo.'];
    }

    return ['ok' => true, 'path' => 'uploads/avatars/' . $filename];
}
