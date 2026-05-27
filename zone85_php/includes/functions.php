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
