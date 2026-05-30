<?php
// ============================================================
// ZONE 85 — Settings V10.1
// Lecture/écriture des paramètres depuis la table `settings`.
// Fallback sur les constantes config.php si DB indisponible.
// ============================================================

// Cache en mémoire (évite N+1 sur la même requête)
$_settings_cache = null;

/**
 * Charge tous les settings depuis la DB (une seule requête par requête HTTP).
 * Retourne un tableau associatif key => value.
 */
function _load_settings_cache(): array {
    global $_settings_cache;
    if ($_settings_cache !== null) return $_settings_cache;

    $pdo = db();
    if (!$pdo) {
        $_settings_cache = [];
        return $_settings_cache;
    }
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $_settings_cache = $rows ?: [];
    } catch (PDOException $e) {
        // Table peut ne pas encore exister
        $_settings_cache = [];
    }
    return $_settings_cache;
}

/**
 * Retourne la valeur d'un paramètre.
 * Priorité : DB → constante PHP → $default.
 *
 * Usage :
 *   get_setting('brevo_api_key')           → string|null
 *   get_setting('brevo_enabled', false)    → bool
 *   get_setting('ga4_measurement_id', '')  → string
 */
function get_setting(string $key, $default = null) {
    $cache = _load_settings_cache();

    if (array_key_exists($key, $cache) && $cache[$key] !== null && $cache[$key] !== '') {
        $val = $cache[$key];

        // Coerce booleans stored as '1'/'0'
        if ($default === true || $default === false || $default === null) {
            if (in_array($val, ['1','true','yes','on'],  true)) return true;
            if (in_array($val, ['0','false','no','off'], true)) return false;
        }
        return $val;
    }

    // Fallback : constantes config.php
    $constant_map = [
        'site_name'          => 'SITE_NAME',
        'site_email'         => 'SITE_EMAIL',
        'site_url'           => 'SITE_URL',
        'brevo_enabled'      => 'BREVO_ENABLED',
        'brevo_api_key'      => 'BREVO_API_KEY',
        'brevo_from_email'   => 'BREVO_FROM_EMAIL',
        'brevo_from_name'    => 'BREVO_FROM_NAME',
        'ga4_measurement_id' => 'GA4_MEASUREMENT_ID',
        'matomo_url'         => 'MATOMO_URL',
        'matomo_site_id'     => 'MATOMO_SITE_ID',
        'pwa_enabled'        => 'PWA_ENABLED',
        'pwa_app_name'       => 'PWA_APP_NAME',
        'pwa_short_name'     => 'PWA_SHORT_NAME',
        'pwa_theme_color'    => 'PWA_THEME_COLOR',
        'pwa_bg_color'       => 'PWA_BG_COLOR',
        'pwa_display'        => 'PWA_DISPLAY',
    ];

    if (isset($constant_map[$key]) && defined($constant_map[$key])) {
        return constant($constant_map[$key]);
    }

    return $default;
}

/**
 * Sauvegarde un paramètre en DB.
 * Crée la ligne si elle n'existe pas (INSERT … ON DUPLICATE KEY UPDATE).
 */
function set_setting(string $key, $value): bool {
    $pdo = db();
    if (!$pdo) return false;

    // Normalise les booleans
    if (is_bool($value)) $value = $value ? '1' : '0';

    try {
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()
        ")->execute([':k' => $key, ':v' => $value, ':v2' => $value]);

        // Invalider le cache
        global $_settings_cache;
        if ($_settings_cache !== null) {
            $_settings_cache[$key] = $value;
        }
        return true;
    } catch (PDOException $e) {
        error_log('[ZONE85 settings] set_setting : ' . $e->getMessage());
        return false;
    }
}

/**
 * Sauvegarde plusieurs paramètres en une transaction.
 */
function save_settings(array $data): bool {
    $pdo = db();
    if (!$pdo) return false;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()
        ");
        foreach ($data as $key => $value) {
            if (is_bool($value)) $value = $value ? '1' : '0';
            $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
        }
        $pdo->commit();

        // Invalider le cache
        global $_settings_cache;
        $_settings_cache = null;
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[ZONE85 settings] save_settings : ' . $e->getMessage());
        return false;
    }
}

/**
 * Retourne tous les paramètres d'une catégorie, avec leurs métadonnées.
 * Merge DB values dans les définitions.
 */
function get_settings_by_category(string $category): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, COALESCE(s.setting_value, '') AS current_value
            FROM settings s
            WHERE s.category = :cat
            ORDER BY s.sort_order ASC, s.id ASC
        ");
        $stmt->execute([':cat' => $category]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Retourne toutes les catégories disponibles.
 */
function get_settings_categories(): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $rows = $pdo->query("
            SELECT DISTINCT category FROM settings ORDER BY
            FIELD(category,'general','brevo','analytics','pwa','social','push') ASC, category ASC
        ")->fetchAll(PDO::FETCH_COLUMN);
        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}
