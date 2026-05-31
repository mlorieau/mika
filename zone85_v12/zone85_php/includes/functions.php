<?php
// ============================================================
// ZONE 85 — Fonctions utilitaires
// ============================================================

/** Retourne le chemin complet d'un asset (relatif, compatible sous-dossier) */
function asset(string $path): string {
    return ASSETS_PATH . ltrim($path, '/');
}

/** Retourne le chemin d'une image */
function img(string $filename): string {
    return IMG_PATH . $filename;
}

/** Retourne l'URL d'une page .php */
function page_url(string $slug): string {
    return $slug . '.php';
}

/**
 * URL absolue vers une ressource du site (tient compte de BASE_URL).
 * url('profil.php')          → /test/zone85_php/profil.php
 * url('assets/css/zone85.css') → /test/zone85_php/assets/css/zone85.css
 */
function url(string $path = ''): string {
    $base = defined('BASE_URL') ? BASE_URL : '/';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * URL absolue vers un fichier uploadé.
 * upload_url('uploads/avatars/photo.png') → /test/zone85_php/uploads/avatars/photo.png
 */
function upload_url(string $path = ''): string {
    return url(ltrim($path, '/'));
}

/**
 * Nettoie un chemin média stocké en DB.
 * Accepte : uploads/x.jpg, /uploads/x.jpg, URL absolue.
 */
function normalize_media_path(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    $path = str_replace('\\', '/', $path);

    // Sécurité : si un chemin filesystem complet a été stocké par erreur,
    // on le ramène à partir de /uploads/.
    $pos = strpos($path, '/uploads/');
    if ($pos !== false) {
        $path = substr($path, $pos + 1);
    }

    $path = preg_replace('~^/+~', '', $path);

    // Si un vieux chemin contient déjà BASE_URL ou SITE_URL, on retire le préfixe public.
    if (defined('BASE_URL')) {
        $base = trim((string)BASE_URL, '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base) + 1);
        }
    }
    if (defined('SITE_URL') && SITE_URL) {
        $site = rtrim((string)SITE_URL, '/') . '/';
        if (str_starts_with($path, $site)) {
            $path = substr($path, strlen($site));
        }
    }
    return $path;
}

/**
 * URL sûre vers un média. Évite les chemins cassés et accepte les URLs externes.
 */
function media_url(?string $path): string {
    $path = normalize_media_path($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;

    // Robustesse V12.8 : certains anciens enregistrements peuvent contenir
    // seulement un nom de fichier. Dans ce cas, on le rattache aux uploads randos.
    if (!str_contains($path, '/') && preg_match('~\.(jpe?g|png|webp|gif)$~i', $path)) {
        $path = 'uploads/randos/' . $path;
    }
    if (!str_contains($path, '/') && preg_match('~\.gpx$~i', $path)) {
        $path = 'uploads/randos/gpx/' . $path;
    }

    return upload_url($path);
}

/**
 * URL absolue vers une page ou un asset du site, adaptée à l'environnement courant.
 * Utile pour Facebook/Open Graph sur staging (/test/zone85_php/) comme en production.
 */
function absolute_url(string $path = ''): string {
    if (preg_match('~^https?://~i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        return $scheme . '://' . $host . url($path);
    }
    $site = defined('SITE_URL') ? rtrim((string)SITE_URL, '/') : '';
    return $site . url($path);
}

/** URL absolue vers un média, adaptée à l'environnement courant. */
function media_absolute_url(?string $path): string {
    $path = normalize_media_path($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return absolute_url($path);
}

/** Normalise une liste de communes stockée en JSON, texte multiligne ou chaîne avec \n littéral. */
function normalize_communes_list($value, ?string $fallback = null): array {
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } else {
        $raw = trim((string)$value);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $raw = str_replace(['\r\n','\n','\r'], "
", $raw);
                $items = preg_split('/[
,;]+/', $raw) ?: [];
            }
        }
    }
    if (empty($items) && $fallback) $items = [$fallback];
    $out = [];
    foreach ($items as $item) {
        $name = trim(strip_tags((string)$item));
        if ($name !== '' && !in_array($name, $out, true)) $out[] = $name;
    }
    return $out;
}

/**
 * Upload d'un fichier GPX.
 */
function upload_gpx_file(array $file, string $subdir = 'randos/gpx'): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match($file['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd.',
            UPLOAD_ERR_PARTIAL => 'Envoi interrompu.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier reçu.',
            default => 'Erreur lors de l\'envoi.',
        };
        return ['ok' => false, 'error' => $msg];
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext !== 'gpx') {
        return ['ok' => false, 'error' => 'Seuls les fichiers .gpx sont acceptés.'];
    }
    $max_size = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max_size) {
        return ['ok' => false, 'error' => 'Fichier GPX trop lourd (max 5 Mo).'];
    }
    $upload_dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__) . '/') . 'uploads/' . trim($subdir, '/') . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(12)) . '.gpx';
    $dest = $upload_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Impossible d\'enregistrer le fichier GPX.'];
    }
    return ['ok' => true, 'path' => 'uploads/' . trim($subdir, '/') . '/' . $filename];
}


/**
 * URL de l'avatar d'un utilisateur pour un attribut src HTML.
 * - avatar_type = 'upload' : retourne l'URL absolue du fichier
 * - avatar_type = 'preset' : retourne '' (afficher l'emoji directement)
 * Accepte les deux structures : session (['avatar_key']) et profil (['avatar']).
 */
function avatar_url(array $user): string {
    $type = $user['avatar_type'] ?? 'preset';
    if ($type !== 'upload') {
        return '';
    }
    $file = $user['avatar_key'] ?? ($user['avatar'] ?? '');
    if (empty($file)) {
        return '';
    }
    return upload_url(ltrim($file, '/'));
}

/** Formate un nombre d'XP : 3400 → "3 400 XP" */
function format_xp(int $n): string {
    return number_format($n, 0, ',', ' ') . ' XP';
}

/** Formate un score de clan : 12840 → "12 840 pts" */
function format_score(int $n): string {
    return number_format($n, 0, ',', ' ') . ' pts';
}

/** Retourne la saison active depuis le tableau $seasons global */
function get_active_season(): ?array {
    global $seasons;
    foreach ($seasons as $s) {
        if ($s['status'] === 'active') return $s;
    }
    return null;
}

/** Retourne un clan par slug */
function get_clan_by_slug(string $slug): ?array {
    global $clans;
    return $clans[$slug] ?? null;
}

/** Filtre les missions par type */
function get_missions_by_type(string $type): array {
    global $missions;
    return array_values(array_filter($missions, fn($m) => $m['mission_type'] === $type));
}

/** Filtre les missions par saison */
function get_missions_by_season(int $season_id): array {
    global $missions;
    return array_values(array_filter($missions, fn($m) => $m['season_id'] === $season_id));
}

/** Retourne les top membres d'un clan */
function get_top_members_by_clan(string $clan_slug): array {
    global $clans;
    return $clans[$clan_slug]['top_members'] ?? [];
}

/**
 * Calcule le niveau à partir des XP à vie (V10.2).
 * Seuils : 0/50/100/250/500/1000/2500/5000/10000/20000
 * Retourne un entier entre 1 et 10.
 */
function get_user_level_from_xp(int $xp): int {
    $thresholds = [0, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 20000];
    $level = 1;
    foreach ($thresholds as $i => $t) {
        if ($xp >= $t) $level = $i + 1;
    }
    return min($level, 10);
}

/**
 * Retourne le nom du niveau pour un niveau donné.
 */
function get_level_name(int $level): string {
    $names = ['', 'Novice', 'Explorateur', 'Aventurier', 'Expert', 'Gardien',
              'Légende', 'Grand Pisteur', 'Vétéran', 'Ancêtre', 'Immortel'];
    return $names[$level] ?? 'Immortel';
}

/**
 * Retourne le seuil XP d'un niveau donné.
 */
function get_level_threshold(int $level): int {
    $t = [0, 0, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 20000];
    return $t[$level] ?? 20000;
}

/**
 * Inclut un composant PHP avec des données
 * @param string $component  Nom du fichier sans extension (ex: 'clan-card')
 * @param array  $data       Variables injectées dans le composant
 */
function render_component(string $component, array $data = []): void {
    $file = __DIR__ . '/../components/' . $component . '.php';
    if (!file_exists($file)) {
        echo "<!-- Component not found: {$component} -->";
        return;
    }
    extract($data, EXTR_SKIP);
    include $file;
}

/** Tronque une chaîne en préservant les caractères multibyte */
function truncate_text(string $str, int $len): string {
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return mb_strlen($str) > $len ? mb_substr($str, 0, $len) : $str;
    }
    return strlen($str) > $len ? substr($str, 0, $len) : $str;
}

/** Échappe et affiche une chaîne HTML */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/** Retourne le label d'un type de mission */
function mission_type_label(string $type): string {
    return [
        'seasonal_collective' => 'Grande Mission',
        'quiz'                => 'Quiz',
        'vote'                => 'Vote',
        'photo_challenge'     => 'Photo',
        'keto_kole_tche'      => 'KTC',
        'rando'               => 'Rando',
        'weather_mission'     => 'Météo',
        'investigation'       => 'Enquête',
        'hidden_hunt'         => 'Chasse cachée',
        'premium_game'        => 'Jeu Premium',
    ][$type] ?? ucfirst($type);
}

/** Retourne l'emoji d'un type de mission */
function mission_type_icon(string $type): string {
    return [
        'seasonal_collective' => '🏆',
        'quiz'                => '🧠',
        'vote'                => '🗳️',
        'photo_challenge'     => '📸',
        'keto_kole_tche'      => '🥐',
        'rando'               => '🥾',
        'weather_mission'     => '🌤️',
        'investigation'       => '🔍',
        'hidden_hunt'         => '🗝️',
        'premium_game'        => '⭐',
    ][$type] ?? '📌';
}

// ============================================================
// HELPERS SEO
// ============================================================

/** Génère le <title> final : "Page — Zone 85 · L'Esprit Vendée" */
function seo_title(string $title): string {
    return htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . " — Zone 85 · L'Esprit Vendée";
}

/** Tronque une meta description à 160 chars */
function seo_description(string $desc): string {
    if (function_exists('mb_strlen') && mb_strlen($desc) > 160) {
        return mb_substr($desc, 0, 157) . '…';
    }
    return strlen($desc) > 160 ? substr($desc, 0, 157) . '…' : $desc;
}

/** Retourne l'URL canonique complète */
function canonical_url(string $path = ''): string {
    $base = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
    return $base . '/' . ltrim($path, '/');
}

/** Affiche un bloc <script type="application/ld+json"> */
function json_ld(array $schema): void {
    echo '<script type="application/ld+json">' . "\n";
    echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n</script>\n";
}

/** Génère le schema BreadcrumbList */
function generate_breadcrumb_schema(array $items): array {
    $elements = [];
    foreach ($items as $pos => $item) {
        $elements[] = [
            '@type'    => 'ListItem',
            'position' => $pos + 1,
            'name'     => $item['name'],
            'item'     => $item['url'] ?? '',
        ];
    }
    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}

/** Génère le schema WebSite */
function generate_website_schema(): array {
    $url = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
    return [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => 'ZONE85',
        'url'      => $url,
        'description' => 'Terrain de jeu communautaire vendéen. Rejoins un clan, gagne des XP, fais vivre la Vendée autrement.',
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => $url . '/missions.php?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

/** Génère le schema Organization */
function generate_organization_schema(): array {
    $url = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
    return [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => 'ZONE85',
        'url'      => $url,
        'logo'     => $url . '/assets/img/ZONE852025.png',
        'contactPoint' => [
            '@type'             => 'ContactPoint',
            'email'             => 'contact@zone85.fr',
            'contactType'       => 'customer service',
            'availableLanguage' => 'French',
        ],
        'areaServed' => 'Vendée, Pays de la Loire, France',
    ];
}

/** Génère un schema CreativeWork générique */
function generate_creativework_schema(array $data): array {
    return array_merge([
        '@context' => 'https://schema.org',
        '@type'    => 'CreativeWork',
    ], $data);
}

// ============================================================
// HELPERS SÉCURITÉ
// ============================================================

/** Génère ou retourne le token CSRF de session */
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Session non démarrée — retourne un placeholder pour les maquettes statiques
        return 'csrf_placeholder_start_session_first';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Génère un champ hidden CSRF prêt à insérer dans un formulaire HTML */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Vérifie le token CSRF (retourne false si invalide) */
function verify_csrf_token(string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Vérifie si la requête est en POST */
function is_post_request(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Redirection sécurisée (bloque les redirections ouvertes) */
function redirect(string $url): void {
    $base = defined('SITE_URL') ? SITE_URL : 'https://www.zone85.fr';
    // N'autorise que les redirections relatives ou vers le même domaine
    if (!str_starts_with($url, '/') && !str_starts_with($url, $base)) {
        $url = '/';
    }
    header('Location: ' . $url, true, 302);
    exit;
}

/** Nettoie une entrée utilisateur basique */
function safe_input(string $value, int $max_length = 500): string {
    return mb_substr(trim(strip_tags($value)), 0, $max_length);
}

/**
 * Affiche les objets cachés pour une page donnée (Hidden Hunt V9).
 * À appeler en bas de chaque page publique avant le footer.
 * Ne produit aucune sortie si la DB est indisponible ou aucun objet actif.
 */
function render_hidden_collectibles(string $page_slug): void {
    if (!function_exists('fetch_active_collectibles_for_page')) return;
    $GLOBALS['_hidden_collectibles_page'] = $page_slug;
    $file = __DIR__ . '/../components/hidden-collectibles.php';
    if (file_exists($file)) include $file;
    $GLOBALS['_hidden_collectibles_page'] = null;
}

/** Envoie les headers de sécurité HTTP */
function set_security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // CSP permissive (fonts Google, inline styles autorisés pour les pages actuelles)
    // TODO: renforcer en prod après audit complet des inline styles/scripts
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://unpkg.com; img-src 'self' data: blob: https://unpkg.com https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://www.zone85.fr https://zone85.fr; connect-src 'self' https://unpkg.com https://*.tile.openstreetmap.org https://tile.openstreetmap.org;");
}

/**
 * Exécute un callback repository et retourne le résultat ou le fallback si null/exception.
 * Usage : $clans = safe_fetch(fn() => fetch_all_clans(), $clans);
 */
function safe_fetch(callable $callback, $fallback = []) {
    try {
        $result = $callback();
        return ($result !== null) ? $result : $fallback;
    } catch (Throwable $e) {
        if (defined('APP_ENV') && APP_ENV === 'dev') {
            error_log('[ZONE85 safe_fetch] ' . $e->getMessage());
        }
        return $fallback;
    }
}


/**
 * Upload une image pour les randos ou le CMS editorial.
 * Retourne ['ok'=>bool, 'path'=>string, 'error'=>string]
 */
function upload_editorial_image(array $file, string $subdir = 'randos'): array {
    $max_size      = 5 * 1024 * 1024; // 5 Mo
    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd (max 5 Mo).',
            UPLOAD_ERR_PARTIAL => 'Envoi interrompu.',
            default => 'Erreur lors de l\'envoi.',
        };
        return ['ok' => false, 'error' => $msg];
    }

    if ($file['size'] > $max_size) {
        return ['ok' => false, 'error' => 'Fichier trop lourd (max 5 Mo).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, $allowed_types)) {
        return ['ok' => false, 'error' => 'Type non autorise. Utilisez jpg, png ou webp.'];
    }

    $upload_dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__) . '/') . 'uploads/' . $subdir . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext      = $allowed_types[$mime];
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Impossible d\'enregistrer le fichier.'];
    }

    return ['ok' => true, 'path' => normalize_media_path('uploads/' . $subdir . '/' . $filename)];
}




