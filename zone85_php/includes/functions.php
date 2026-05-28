<?php
// ============================================================
// ZONE 85 — Fonctions utilitaires
// ============================================================

/** Retourne le chemin complet d'un asset */
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
 * Calcule le niveau à partir des XP à vie
 * Paliers : 0→Niv.1, 500→2, 1500→3, 3000→4, 5000→5,
 *           8000→6, 12000→7, 17000→8, 23000→9, 30000→10
 */
function get_user_level_from_xp(int $xp): int {
    $thresholds = [0, 500, 1500, 3000, 5000, 8000, 12000, 17000, 23000, 30000];
    $level = 1;
    foreach ($thresholds as $i => $t) {
        if ($xp >= $t) $level = $i + 1;
    }
    return min($level, 10);
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

/** Envoie les headers de sécurité HTTP */
function set_security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // CSP permissive (fonts Google, inline styles autorisés pour les pages actuelles)
    // TODO: renforcer en prod après audit complet des inline styles/scripts
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self';");
}
