<?php
// ============================================================
// ZONE 85 — Connexion PDO
// Retourne une instance PDO ou null (fallback mock activé)
// ============================================================

function _zone85_db_connect(): ?PDO {
    if (!defined('DB_ENABLED') || !DB_ENABLED) {
        return null;
    }
    static $_pdo = null;
    static $_attempted = false;
    if ($_attempted) return $_pdo;
    $_attempted = true;
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT ?? 3306, DB_NAME, DB_CHARSET
        );
        $_pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $_pdo;
    } catch (PDOException $e) {
        // En dev, loguer ; en prod, ne rien exposer
        if (defined('APP_ENV') && APP_ENV === 'dev') {
            error_log('[ZONE85 DB] Connexion échouée : ' . $e->getMessage());
        } else {
            error_log('[ZONE85 DB] Connexion échouée.');
        }
        $_pdo = null;
        return null;
    }
}

function db(): ?PDO {
    return _zone85_db_connect();
}

function db_enabled(): bool {
    return _zone85_db_connect() !== null;
}

// ── Auto-chargement du module Settings ───────────────────────
// Dès que db.php est inclus, get_setting() devient disponible partout.
// Cela garantit que les paramètres configurés dans le BO Settings
// sont effectivement lus par le front (Brevo, GA4, PWA, maintenance...).
if (!function_exists('get_setting')) {
    $__settings_file = __DIR__ . '/settings.php';
    if (file_exists($__settings_file)) {
        require_once $__settings_file;
    }
    unset($__settings_file);
}
