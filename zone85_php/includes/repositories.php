<?php
// ============================================================
// ZONE 85 — Repositories (lecture données)
// Retourne null si DB non disponible → fallback sur data.php
// ============================================================

if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Retourne les 3 clans indexés par slug, avec scores de saison.
 * Compatible avec la structure $clans de data.php.
 */
function fetch_all_clans(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        // Requête : clans + score de saison active + nb membres
        $stmt = $pdo->prepare("
            SELECT
                c.id, c.name, c.slug, c.description, c.mascot_image,
                c.color_primary, c.color_secondary, c.motto, c.cry,
                COALESCE(SUM(csl.points), 0) AS season_score,
                COUNT(DISTINCT u.id) AS members_count
            FROM clans c
            LEFT JOIN clan_score_logs csl
                ON csl.clan_id = c.id
                AND csl.season_id = (SELECT id FROM seasons WHERE status = 'active' LIMIT 1)
            LEFT JOIN users u ON u.clan_id = c.id AND u.status = 'active'
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.id
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;

        // Mapper sur la même structure que data.php
        $chip_map = [
            'bocage'   => ['bocage-chip',   'bocage-chip-sm',   'bocage-text',   '🌳 Bocage'],
            'littoral' => ['littoral-chip', 'littoral-chip-sm', 'littoral-text', '⚓ Littoral'],
            'marais'   => ['marais-chip',   'marais-chip-sm',   'marais-text',   '🌿 Marais'],
        ];
        $result = [];
        foreach ($rows as $row) {
            $slug = $row['slug'];
            $cm = $chip_map[$slug] ?? ['', '', '', ''];
            $result[$slug] = [
                'id'            => (int)$row['id'],
                'name'          => $row['name'],
                'slug'          => $slug,
                'mascot'        => $row['mascot_image'] ?? "mascotte-{$slug}.png",
                'hero_name'     => $row['motto'] ?? '',
                'season_score'  => (int)$row['season_score'],
                'members_count' => (int)$row['members_count'],
                'trophies'      => 0, // calculé séparément si besoin
                'color'         => $row['color_primary'],
                'chip_class'    => $cm[0],
                'chip_sm_class' => $cm[1],
                'text_class'    => $cm[2],
                'label'         => $cm[3],
                'description'   => $row['description'] ?? '',
                'top_members'   => [], // chargé séparément
            ];
        }
        return $result;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_all_clans : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne la saison active ou null.
 * Compatible avec $active_season de data.php.
 */
function fetch_active_season(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, g.title AS main_mission_title
            FROM seasons s
            LEFT JOIN games g ON g.id = s.main_game_id
            WHERE s.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) return null;
        return [
            'id'              => (int)$row['id'],
            'title'           => $row['title'],
            'slug'            => $row['slug'],
            'period'          => $row['period_label'] ?? '',
            'status'          => $row['status'],
            'winner_clan'     => null,
            'winner_label'    => null,
            'contributions'   => null,
            'main_mission_id' => $row['main_game_id'] ? (int)$row['main_game_id'] : null,
            'main_mission'    => $row['main_mission_title'] ?? null,
            'end_date'        => $row['end_date'],
        ];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_active_season : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les scores des clans pour la saison active.
 * Retourne [['clan_slug'=>..., 'score'=>..., 'name'=>...], ...]
 */
function fetch_current_season_scores(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT c.slug AS clan_slug, c.name, COALESCE(SUM(csl.points), 0) AS score
            FROM clans c
            LEFT JOIN clan_score_logs csl
                ON csl.clan_id = c.id
                AND csl.season_id = (SELECT id FROM seasons WHERE status = 'active' LIMIT 1)
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY score DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_current_season_scores : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les missions actives (toutes ou limitées).
 * Compatible avec $missions de data.php.
 */
function fetch_featured_missions(int $limit = 9): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM missions
            WHERE status = 'active'
            ORDER BY display_in_hall DESC, id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        // Cast booleans
        foreach ($rows as &$r) {
            foreach (['is_collective', 'requires_answer', 'requires_upload', 'requires_vote', 'requires_code', 'display_in_hall'] as $k) {
                $r[$k] = (bool)$r[$k];
            }
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_featured_missions : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les missions d'un type donné.
 */
function fetch_missions_by_type(string $type): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM missions
            WHERE mission_type = :type AND status IN ('active','closed')
            ORDER BY id ASC
        ");
        $stmt->execute([':type' => $type]);
        return $stmt->fetchAll() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_missions_by_type : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les badges du catalogue.
 * Compatible avec $badges de data.php.
 */
function fetch_badges(?int $limit = null): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $sql = "SELECT * FROM badges ORDER BY FIELD(rarity,'legendary','epic','rare','uncommon','common'), title";
        if ($limit) $sql .= " LIMIT " . (int)$limit;
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            $r['obtained']     = false; // requiert une session utilisateur
            $r['progress']     = 0;
            $r['xp_threshold'] = $r['condition_type'] === 'xp_threshold' ? (int)$r['condition_value'] : null;
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_badges : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne le top membres global (classement par XP à vie).
 * Compatible avec $top_zonautes de data.php.
 */
function fetch_top_members(int $limit = 8): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT
                u.pseudo, c.slug AS clan, u.xp_total,
                COALESCE(SUM(CASE WHEN p.status IN ('validated','auto_validated') THEN 1 ELSE 0 END), 0) AS missions,
                COALESCE(SUM(xl.xp_amount), 0) AS xp_season,
                @rank := @rank + 1 AS `rank`
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            LEFT JOIN participations p ON p.user_id = u.id
            LEFT JOIN xp_logs xl ON xl.user_id = u.id
                AND xl.created_at >= (SELECT start_date FROM seasons WHERE status = 'active' LIMIT 1)
            JOIN (SELECT @rank := 0) r
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY u.xp_total DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            $r['rank']      = (int)$r['rank'];
            $r['xp_total']  = (int)$r['xp_total'];
            $r['xp_season'] = (int)$r['xp_season'];
            $r['missions']  = (int)$r['missions'];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_top_members : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne le top membres d'un clan donné (par XP de saison).
 * Compatible avec $clans[$slug]['top_members'] de data.php.
 */
function fetch_top_members_by_clan(string $clanSlug, int $limit = 6): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT
                u.pseudo,
                u.xp_total,
                COALESCE(SUM(xl.xp_amount), 0) AS xp_season
            FROM users u
            JOIN clans c ON c.id = u.clan_id AND c.slug = :slug
            LEFT JOIN xp_logs xl ON xl.user_id = u.id
                AND xl.created_at >= (SELECT COALESCE(start_date,'1970-01-01') FROM seasons WHERE status='active' LIMIT 1)
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY xp_season DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':slug', $clanSlug, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_top_members_by_clan : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les trophées de saisons archivées.
 * Compatible avec $season_trophies de data.php.
 */
function fetch_trophies(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->query("
            SELECT
                s.title AS season, c.slug AS winner_clan, c.name AS winner_name,
                t.score_final AS contributions, t.title AS trophy_title
            FROM season_trophies t
            JOIN seasons s ON s.id = t.season_id
            JOIN clans c ON c.id = t.winning_clan_id
            ORDER BY t.awarded_at DESC
        ");
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            $r['medal']         = '🥇';
            $r['main_mission']  = $r['trophy_title'] ?? '';
            $r['contributions'] = (int)$r['contributions'];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_trophies : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les top contributeurs du Hall.
 * Compatible avec $hall_contributors de data.php.
 */
function fetch_hall_contributors(int $limit = 5): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT
                u.pseudo, c.slug AS clan_slug, c.name AS clan_name,
                COALESCE(SUM(xl.xp_amount), 0) AS season_pts
            FROM users u
            JOIN clans c ON c.id = u.clan_id
            LEFT JOIN xp_logs xl ON xl.user_id = u.id
                AND xl.created_at >= (SELECT COALESCE(start_date,'1970-01-01') FROM seasons WHERE status='active' LIMIT 1)
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY season_pts DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        $clan_labels = ['bocage' => '🌳 Bocage', 'littoral' => '⚓ Littoral', 'marais' => '🌿 Marais'];
        foreach ($rows as &$r) {
            $r['season_pts'] = (int)$r['season_pts'];
            $r['clan_label'] = $clan_labels[$r['clan_slug']] ?? $r['clan_name'];
            $r['avatar']     = '🧭';
            $r['type']       = 'Contribution';
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_hall_contributors : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les items du Hall (photos en avant).
 * Compatible avec $hall_photos de data.php.
 */
function fetch_hall_photos(int $limit = 8): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT h.title, h.description, h.item_type AS category, u.pseudo AS author,
                   h.is_featured, h.published_at
            FROM hall_items h
            LEFT JOIN users u ON u.id = h.user_id
            WHERE h.item_type = 'photo' AND h.published_at IS NOT NULL
            ORDER BY h.is_featured DESC, h.published_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        $gradients = [
            'linear-gradient(135deg,#12314e,#2a9d5c)',
            'linear-gradient(135deg,#C9962A,#ea5649)',
            'linear-gradient(135deg,#2a9d5c,#163756)',
            'linear-gradient(135deg,#ea5649,#12314e)',
        ];
        foreach ($rows as $i => &$r) {
            $r['likes']    = 0;
            $r['gradient'] = $gradients[$i % count($gradients)];
            $r['category'] = ucfirst($r['category']);
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_hall_photos : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne le profil d'un utilisateur par son ID.
 * Compatible avec $mock_user de data.php.
 */
function fetch_user_profile(int $userId): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, c.slug AS clan_slug, c.name AS clan_name
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.id = :id AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        // Compter les badges
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = :id");
        $stmt2->execute([':id' => $userId]);
        $badges_count = (int)$stmt2->fetchColumn();
        // Compter les missions
        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id = :id AND status IN ('validated','auto_validated')");
        $stmt3->execute([':id' => $userId]);
        $missions_done = (int)$stmt3->fetchColumn();
        return [
            'id'             => (int)$row['id'],
            'pseudo'         => $row['pseudo'],
            'prenom'         => $row['first_name'] ?? '',
            'nom'            => $row['last_name'] ?? '',
            'clan_slug'      => $row['clan_slug'] ?? '',
            'level'          => (int)$row['level'],
            'xp_total'       => (int)$row['xp_total'],
            'xp_this_season' => 0, // calculé séparément
            'avatar'         => '🧭',
            'bio'            => $row['bio'] ?? '',
            'badges_count'   => $badges_count,
            'missions_done'  => $missions_done,
            'rank_in_clan'   => 0, // calculé périodiquement
            'rank_total'     => 0,
            'joined'         => $row['created_at'] ? substr($row['created_at'], 0, 10) : '',
        ];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_profile : ' . $e->getMessage());
        return null;
    }
}
