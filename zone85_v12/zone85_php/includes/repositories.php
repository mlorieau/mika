<?php
// ============================================================
// ZONE 85 â€” Repositories (lecture donnÃ©es)
// Retourne null si DB non disponible â†’ fallback sur data.php
// ============================================================

if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
}

// â”€â”€ Helper interne â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne l'id et la start_date de la saison active (singleton).
 */
function _active_season_row(): ?array {
    static $_cache = false; // false = non encore tentÃ©
    if ($_cache !== false) return $_cache ?: null;
    $pdo = db();
    if (!$pdo) { $_cache = null; return null; }
    try {
        $row = $pdo->query(
            "SELECT id, COALESCE(start_date, '1970-01-01') AS start_date
             FROM seasons WHERE status = 'active' LIMIT 1"
        )->fetch();
        $_cache = $row ?: null;
        return $_cache;
    } catch (PDOException $e) {
        $_cache = null;
        return null;
    }
}

// â”€â”€ Clans â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les 3 clans indexÃ©s par slug, avec tous les champs attendus par clans.php / classement.php.
 * Champs : id, name, slug, mascot, hero_name, season_score, members_count, members,
 *          trophies, color, chip_class, chip_sm_class, text_class, label, description,
 *          race_width, podium_rank, podium_id, top_members.
 */
function fetch_all_clans(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $sr       = _active_season_row();
        $seasonId = $sr ? (int)$sr['id'] : 0;

        $stmt = $pdo->prepare("
            SELECT
                c.id, c.name, c.slug, c.description,
                c.mascot_image, c.color_primary, c.motto,
                COALESCE(SUM(csl.points), 0)     AS season_score,
                COUNT(DISTINCT u.id)              AS members_count,
                (SELECT COUNT(*) FROM season_trophies st
                 WHERE st.winning_clan_id = c.id) AS trophies
            FROM clans c
            LEFT JOIN clan_score_logs csl
                ON csl.clan_id = c.id AND csl.season_id = :sid
            LEFT JOIN users u
                ON u.clan_id = c.id AND u.status = 'active'
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY season_score DESC
        ");
        $stmt->execute([':sid' => $seasonId]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;

        // Max score pour race_width relatif
        $maxScore = 0;
        foreach ($rows as $row) {
            if ((int)$row['season_score'] > $maxScore) $maxScore = (int)$row['season_score'];
        }

        $chip_map = [
            'bocage'   => ['bocage-chip',   'bocage-chip-sm',   'bocage-text',   'ðŸŒ³ Bocage'],
            'littoral' => ['littoral-chip', 'littoral-chip-sm', 'littoral-text', 'âš“ Littoral'],
            'marais'   => ['marais-chip',   'marais-chip-sm',   'marais-text',   'ðŸŒ¿ Marais'],
        ];

        $result = [];
        foreach ($rows as $i => $row) {
            $slug       = $row['slug'];
            $cm         = $chip_map[$slug] ?? ['', '', '', ''];
            $score      = (int)$row['season_score'];
            $raceWidth  = ($maxScore > 0) ? (int)round(($score / $maxScore) * 94) : 0;
            $podiumRank = $i + 1; // triÃ© par score DESC â†’ i=0 est le leader

            $result[$slug] = [
                'id'            => (int)$row['id'],
                'name'          => $row['name'],
                'slug'          => $slug,
                'mascot'        => $row['mascot_image'] ?? "mascotte-{$slug}.png",
                'hero_name'     => $row['motto'] ?? '',
                'season_score'  => $score,
                'members_count' => (int)$row['members_count'],
                'members'       => (int)$row['members_count'], // alias pour inscription.php
                'trophies'      => (int)$row['trophies'],
                'color'         => $row['color_primary'] ?? '#12314e',
                'chip_class'    => $cm[0],
                'chip_sm_class' => $cm[1],
                'text_class'    => $cm[2],
                'label'         => $cm[3],
                'description'   => $row['description'] ?? '',
                'race_width'    => max(0, min(100, $raceWidth)),
                'podium_rank'   => $podiumRank,
                'podium_id'     => 'p' . $podiumRank,
                'top_members'   => fetch_top_members_by_clan($slug, 6) ?? [],
            ];
        }
        return $result;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_all_clans : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Saisons â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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
        $sr       = _active_season_row();
        $seasonId = $sr ? (int)$sr['id'] : 0;
        $stmt = $pdo->prepare("
            SELECT c.slug AS clan_slug, c.name, COALESCE(SUM(csl.points), 0) AS score
            FROM clans c
            LEFT JOIN clan_score_logs csl
                ON csl.clan_id = c.id AND csl.season_id = :sid
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY score DESC
        ");
        $stmt->execute([':sid' => $seasonId]);
        return $stmt->fetchAll() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_current_season_scores : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Missions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les missions actives avec race_progress calculÃ©.
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

        // Calcul race_progress pour les missions collectives de saison
        $clanPct = ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
        $sr = _active_season_row();
        if ($sr) {
            $scoreStmt = $pdo->prepare("
                SELECT c.slug, COALESCE(SUM(csl.points), 0) AS pts
                FROM clans c
                LEFT JOIN clan_score_logs csl
                    ON csl.clan_id = c.id AND csl.season_id = :sid
                WHERE c.is_active = 1
                GROUP BY c.id
            ");
            $scoreStmt->execute([':sid' => (int)$sr['id']]);
            $rawPts  = [];
            $maxPts  = 0;
            foreach ($scoreStmt->fetchAll() as $sc) {
                $rawPts[$sc['slug']] = (int)$sc['pts'];
                if ((int)$sc['pts'] > $maxPts) $maxPts = (int)$sc['pts'];
            }
            if ($maxPts > 0) {
                foreach ($rawPts as $slug => $pts) {
                    $clanPct[$slug] = (int)round(($pts / $maxPts) * 100);
                }
            }
        }

        foreach ($rows as &$r) {
            foreach (['is_collective','requires_answer','requires_upload',
                      'requires_vote','requires_code','display_in_hall'] as $k) {
                $r[$k] = (bool)(int)$r[$k];
            }
            $r['race_progress'] = ($r['mission_type'] === 'seasonal_collective')
                ? $clanPct
                : ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_featured_missions : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les missions d'un type donnÃ©.
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
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            foreach (['is_collective','requires_answer','requires_upload',
                      'requires_vote','requires_code','display_in_hall'] as $k) {
                $r[$k] = (bool)(int)$r[$k];
            }
            $r['race_progress'] = ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_missions_by_type : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Badges â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les badges du catalogue.
 * Compatible avec $badges de data.php.
 */
function fetch_badges(?int $limit = null): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $sql = "SELECT * FROM badges
                ORDER BY FIELD(rarity,'legendary','epic','rare','uncommon','common'), title";
        if ($limit) $sql .= " LIMIT " . (int)$limit;
        $rows = $pdo->query($sql)->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            $r['obtained']     = false; // nÃ©cessite une session utilisateur
            $r['progress']     = 0;
            $r['xp_threshold'] = ($r['condition_type'] === 'xp_threshold')
                                 ? (int)$r['condition_value'] : null;
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_badges : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Membres â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne le top membres global (classement par XP Ã  vie).
 * Compatible avec $top_zonautes de data.php.
 * Le rang est calculÃ© cÃ´tÃ© PHP â€” compatible MySQL 8+ et MariaDB.
 */
function fetch_top_members(int $limit = 8): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $sr          = _active_season_row();
        $seasonStart = $sr ? $sr['start_date'] : '1970-01-01';

        $stmt = $pdo->prepare("
            SELECT
                u.pseudo,
                COALESCE(c.slug, '') AS clan,
                u.xp_total,
                (SELECT COALESCE(SUM(xp_amount), 0)
                 FROM xp_logs
                 WHERE user_id = u.id AND created_at >= :season_start) AS xp_season,
                (SELECT COUNT(*)
                 FROM participations
                 WHERE user_id = u.id
                   AND status IN ('validated','auto_validated')) AS missions
            FROM users u
            LEFT JOIN clans c ON c.id = u.clan_id
            WHERE u.status = 'active'
            ORDER BY u.xp_total DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':season_start', $seasonStart, PDO::PARAM_STR);
        $stmt->bindValue(':limit',        $limit,       PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as $i => &$r) {
            $r['rank']      = $i + 1;
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
 * Retourne le top membres d'un clan (par XP de saison).
 * Compatible avec $clans[$slug]['top_members'] de data.php.
 * Retourne [] (tableau vide) en cas d'Ã©chec pour ne pas casser les foreach.
 */
function fetch_top_members_by_clan(string $clanSlug, int $limit = 6): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $sr          = _active_season_row();
        $seasonStart = $sr ? $sr['start_date'] : '1970-01-01';

        $stmt = $pdo->prepare("
            SELECT
                u.pseudo,
                u.xp_total,
                (SELECT COALESCE(SUM(xp_amount), 0)
                 FROM xp_logs
                 WHERE user_id = u.id AND created_at >= :season_start) AS xp_season
            FROM users u
            JOIN clans c ON c.id = u.clan_id AND c.slug = :slug
            WHERE u.status = 'active'
            ORDER BY xp_season DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':season_start', $seasonStart, PDO::PARAM_STR);
        $stmt->bindValue(':slug',         $clanSlug,    PDO::PARAM_STR);
        $stmt->bindValue(':limit',        $limit,       PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['xp_total']  = (int)$r['xp_total'];
            $r['xp_season'] = (int)$r['xp_season'];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_top_members_by_clan : ' . $e->getMessage());
        return [];
    }
}

// â”€â”€ TrophÃ©es â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les trophÃ©es des saisons archivÃ©es.
 * Compatible avec $season_trophies de data.php.
 */
function fetch_trophies(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->query("
            SELECT
                s.title AS season,
                c.slug  AS winner_clan,
                c.name  AS winner_name,
                t.score_final AS contributions,
                t.description AS main_mission
            FROM season_trophies t
            JOIN seasons s ON s.id = t.season_id
            JOIN clans   c ON c.id = t.winning_clan_id
            ORDER BY t.awarded_at DESC
        ");
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            $r['medal']         = 'ðŸ¥‡';
            $r['main_mission']  = $r['main_mission'] ?? '';
            $r['contributions'] = (int)$r['contributions'];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_trophies : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Hall â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les top contributeurs du Hall.
 * Compatible avec $hall_contributors de data.php.
 */
function fetch_hall_contributors(int $limit = 5): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $sr          = _active_season_row();
        $seasonStart = $sr ? $sr['start_date'] : '1970-01-01';

        $stmt = $pdo->prepare("
            SELECT
                u.pseudo, c.slug AS clan_slug, c.name AS clan_name,
                (SELECT COALESCE(SUM(xp_amount), 0)
                 FROM xp_logs
                 WHERE user_id = u.id AND created_at >= :season_start) AS season_pts
            FROM users u
            JOIN clans c ON c.id = u.clan_id
            WHERE u.status = 'active'
            ORDER BY season_pts DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':season_start', $seasonStart, PDO::PARAM_STR);
        $stmt->bindValue(':limit',        $limit,       PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        $clan_labels = [
            'bocage'   => 'ðŸŒ³ Bocage',
            'littoral' => 'âš“ Littoral',
            'marais'   => 'ðŸŒ¿ Marais',
        ];
        foreach ($rows as &$r) {
            $r['season_pts'] = (int)$r['season_pts'];
            $r['clan_label'] = $clan_labels[$r['clan_slug']] ?? $r['clan_name'];
            $r['avatar']     = 'ðŸ§­';
            $r['type']       = 'Contribution';
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_hall_contributors : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les photos du Hall.
 * Compatible avec $hall_photos de data.php.
 */
function fetch_hall_photos(int $limit = 8): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT h.title, h.description, u.pseudo AS author,
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
            $r['category'] = 'Photo';
            $r['gradient'] = $gradients[$i % count($gradients)];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_hall_photos : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Profil utilisateur â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne le profil d'un utilisateur par son ID.
 * Compatible avec $mock_user de data.php.
 */
function fetch_user_profile(int $userId): ?array {
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
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $s2 = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = :id");
        $s2->execute([':id' => $userId]);
        $badges_count = (int)$s2->fetchColumn();

        $s3 = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id = :id AND status IN ('validated','auto_validated')");
        $s3->execute([':id' => $userId]);
        $missions_done = (int)$s3->fetchColumn();

        $sr          = _active_season_row();
        $seasonStart = $sr ? $sr['start_date'] : '1970-01-01';
        $seasonId    = $sr ? (int)$sr['id'] : 0;

        // XP personnels gagnés cette saison (pour référence interne)
        $s4 = $pdo->prepare("SELECT COALESCE(SUM(xp_amount), 0) FROM xp_logs WHERE user_id = :id AND created_at >= :start");
        $s4->execute([':id' => $userId, ':start' => $seasonStart]);
        $xp_season = (int)$s4->fetchColumn();

        // Points clan réellement apportés cette saison (source : clan_score_logs)
        // Distinct des XP personnels — c'est ce que le joueur a donné à son clan
        $clan_pts = 0;
        if ($seasonId > 0) {
            $s5 = $pdo->prepare("
                SELECT COALESCE(SUM(points), 0)
                FROM clan_score_logs
                WHERE user_id = :id AND season_id = :sid
            ");
            $s5->execute([':id' => $userId, ':sid' => $seasonId]);
            $clan_pts = (int)$s5->fetchColumn();
        }

        // Rang dans le clan cette saison
        $rank_in_clan = 0;
        if ($row['clan_id'] && $seasonId > 0) {
            $s6 = $pdo->prepare("
                SELECT COUNT(*) + 1 AS rank
                FROM (
                    SELECT user_id, COALESCE(SUM(points), 0) AS pts
                    FROM clan_score_logs
                    WHERE season_id = :sid AND clan_id = :cid
                    GROUP BY user_id
                    HAVING pts > :mypts
                ) sub
            ");
            $s6->execute([':sid' => $seasonId, ':cid' => (int)$row['clan_id'], ':mypts' => $clan_pts]);
            $rank_in_clan = (int)$s6->fetchColumn();
        }

        // Résolution de l'avatar (fix encodage emoji)
        $avatar_config = [];
        if (!empty($row['avatar_config'])) {
            $avatar_config = json_decode($row['avatar_config'], true) ?? [];
        }
        $avatar = $row['avatar_type'] === 'upload'
            ? ($row['avatar_file'] ?? '🧭')
            : ($avatar_config['emoji'] ?? '🧭');

        return [
            'id'                   => (int)$row['id'],
            'pseudo'               => $row['pseudo'],
            'prenom'               => $row['first_name'] ?? '',
            'nom'                  => $row['last_name'] ?? '',
            'email'                => $row['email'] ?? '',
            'clan_slug'            => $row['clan_slug'] ?? '',
            'level'                => (int)$row['level'],
            'xp_total'             => (int)$row['xp_total'],
            'xp_this_season'       => $xp_season,   // XP perso cette saison
            'clan_pts_contributed' => $clan_pts,     // Pts clan apportés cette saison
            'avatar'               => $avatar,
            'avatar_type'          => $row['avatar_type'] ?? 'preset',
            'bio'                  => $row['bio'] ?? '',
            'badges_count'         => $badges_count,
            'missions_done'        => $missions_done,
            'rank_in_clan'         => $rank_in_clan,
            'rank_total'           => 0,
            'joined'               => $row['created_at'] ? substr($row['created_at'], 0, 10) : '',
        ];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_profile : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Hall items â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les items du Hall (tous types confondus).
 */
function fetch_hall_items(int $limit = 12): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, u.pseudo AS author_pseudo, c.slug AS clan_slug
            FROM hall_items h
            LEFT JOIN users u ON u.id = h.user_id
            LEFT JOIN clans c ON c.id = h.clan_id
            WHERE h.published_at IS NOT NULL
            ORDER BY h.is_featured DESC, h.published_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: null;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_hall_items : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les items du Hall filtrÃ©s par type.
 * Types : 'photo', 'contribution', 'keto', 'rando', 'trophy', 'member', 'archive'
 */
function fetch_hall_items_by_type(string $type, int $limit = 12): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, u.pseudo AS author_pseudo, c.slug AS clan_slug
            FROM hall_items h
            LEFT JOIN users u ON u.id = h.user_id
            LEFT JOIN clans c ON c.id = h.clan_id
            WHERE h.item_type = :type AND h.published_at IS NOT NULL
            ORDER BY h.is_featured DESC, h.published_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':type',  $type,  PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_hall_items_by_type : ' . $e->getMessage());
        return [];
    }
}

/**
 * Retourne les items du Hall mis en avant (is_featured = 1).
 */
function fetch_featured_hall_items(int $limit = 6): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, u.pseudo AS author_pseudo, c.slug AS clan_slug
            FROM hall_items h
            LEFT JOIN users u ON u.id = h.user_id
            LEFT JOIN clans c ON c.id = h.clan_id
            WHERE h.is_featured = 1 AND h.published_at IS NOT NULL
            ORDER BY h.published_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_featured_hall_items : ' . $e->getMessage());
        return [];
    }
}

// â”€â”€ Utilisateur (dÃ©mo & enrichissement) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne un utilisateur de dÃ©monstration par ID (pour profil.php sans auth rÃ©elle).
 * DÃ©lÃ¨gue Ã  fetch_user_profile() â€” retourne null si indisponible.
 */
function fetch_demo_user(int $userId = 1): ?array {
    return fetch_user_profile($userId);
}

/**
 * Retourne les badges obtenus par un utilisateur.
 * Compatible avec $badges de data.php (champs : obtained=true, progress=100).
 * Retourne [] (jamais null) pour ne pas casser les foreach.
 */
function fetch_user_badges(int $userId): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, ub.awarded_at
            FROM user_badges ub
            JOIN badges b ON b.id = ub.badge_id
            WHERE ub.user_id = :id
            ORDER BY ub.awarded_at DESC
        ");
        $stmt->execute([':id' => $userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['obtained']     = true;
            $r['progress']     = 100;
            $r['xp_threshold'] = ($r['condition_type'] === 'xp_threshold') ? (int)$r['condition_value'] : null;
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_badges : ' . $e->getMessage());
        return [];
    }
}

/**
 * Retourne les participations d'un utilisateur, avec le titre de la mission.
 * Retourne [] (jamais null) pour ne pas casser les foreach.
 */
function fetch_user_participations(int $userId, int $limit = 20): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, m.title AS mission_title, m.mission_type, m.slug AS mission_slug
            FROM participations p
            JOIN missions m ON m.id = p.mission_id
            WHERE p.user_id = :id
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':id',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_participations : ' . $e->getMessage());
        return [];
    }
}

// â”€â”€ Missions par saison â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les missions liÃ©es Ã  une saison donnÃ©e.
 */
function fetch_missions_by_season(int $seasonId, string $status = 'active'): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM missions
            WHERE season_id = :sid AND status = :status
            ORDER BY mission_type = 'seasonal_collective' DESC, id ASC
        ");
        $stmt->execute([':sid' => $seasonId, ':status' => $status]);
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            foreach (['is_collective','requires_answer','requires_upload',
                      'requires_vote','requires_code','display_in_hall'] as $k) {
                $r[$k] = (bool)(int)$r[$k];
            }
            $r['race_progress'] = ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_missions_by_season : ' . $e->getMessage());
        return null;
    }
}

// â”€â”€ Historique XP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne les derniÃ¨res entrÃ©es XP d'un utilisateur.
 */
function fetch_user_xp_logs(int $userId, int $limit = 10): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT source_type, xp_amount, reason, created_at
            FROM xp_logs
            WHERE user_id = :id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':id',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_xp_logs : ' . $e->getMessage());
        return [];
    }
}



// ── Feed d'activité unifié ────────────────────────────────────────

/**
 * Retourne les N derniers événements d'activité d'un user, toutes sources confondues.
 */
function fetch_user_activity_feed(int $userId, int $limit = 10): array {
    $pdo = db();
    if (!$pdo) return [];
    $items = [];

    try {
        // Missions participées
        $s = $pdo->prepare("
            SELECT 'mission' AS feed_type, p.created_at AS feed_date,
                   m.title AS feed_title, m.mission_type AS feed_sub,
                   p.xp_awarded AS feed_xp, p.status AS feed_status
            FROM participations p
            JOIN missions m ON m.id = p.mission_id
            WHERE p.user_id = :id
            ORDER BY p.created_at DESC LIMIT 8
        ");
        $s->execute([':id' => $userId]);
        $items = array_merge($items, $s->fetchAll());

        // Randos (toutes, pas seulement validées)
        $s = $pdo->prepare("
            SELECT 'rando' AS feed_type, rp.done_at AS feed_date,
                   r.title AS feed_title, 'rando' AS feed_sub,
                   rp.xp_awarded AS feed_xp, rp.status AS feed_status
            FROM rando_participations rp
            JOIN randos r ON r.id = rp.rando_id
            WHERE rp.user_id = :id
            ORDER BY rp.done_at DESC LIMIT 5
        ");
        $s->execute([':id' => $userId]);
        $items = array_merge($items, $s->fetchAll());

        // Commentaires Échos ayant reçu des XP
        $s = $pdo->prepare("
            SELECT 'comment' AS feed_type, ac.created_at AS feed_date,
                   a.title AS feed_title, 'comment' AS feed_sub,
                   5 AS feed_xp, 'rewarded' AS feed_status
            FROM article_comments ac
            JOIN articles a ON a.id = ac.article_id
            WHERE ac.user_id = :id AND ac.xp_awarded = 1
            ORDER BY ac.created_at DESC LIMIT 5
        ");
        $s->execute([':id' => $userId]);
        $items = array_merge($items, $s->fetchAll());

        // XP divers (badges, admin, inscription…) — sources non couvertes ci-dessus
        $s = $pdo->prepare("
            SELECT 'xp_event' AS feed_type, xl.created_at AS feed_date,
                   COALESCE(xl.reason, xl.source_type) AS feed_title,
                   xl.source_type AS feed_sub,
                   xl.xp_amount AS feed_xp, NULL AS feed_status
            FROM xp_logs xl
            WHERE xl.user_id = :id
              AND xl.source_type NOT IN ('mission','article_comment','rando','rando_review')
            ORDER BY xl.created_at DESC LIMIT 5
        ");
        $s->execute([':id' => $userId]);
        $items = array_merge($items, $s->fetchAll());

    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_activity_feed : ' . $e->getMessage());
    }

    // Tri chronologique décroissant puis troncature
    usort($items, fn($a, $b) => strcmp($b['feed_date'] ?? '', $a['feed_date'] ?? ''));
    return array_slice($items, 0, $limit);
}

// â”€â”€ Moteur de participation V1 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Retourne une mission par son ID.
 */
function fetch_mission_by_id(int $id): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM missions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        foreach (['is_collective','requires_answer','requires_upload',
                  'requires_vote','requires_code','display_in_hall'] as $k) {
            $row[$k] = (bool)(int)($row[$k] ?? 0);
        }
        $row['race_progress'] = ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
        return $row;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_mission_by_id : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les missions actives (liste simple, sans calcul de scores).
 */
function fetch_active_missions(int $limit = 20): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM missions WHERE status = 'active'
            ORDER BY is_collective DESC, id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null;
        foreach ($rows as &$r) {
            foreach (['is_collective','requires_answer','requires_upload',
                      'requires_vote','requires_code','display_in_hall'] as $k) {
                $r[$k] = (bool)(int)($r[$k] ?? 0);
            }
            $r['race_progress'] = ['bocage' => 0, 'littoral' => 0, 'marais' => 0];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_active_missions : ' . $e->getMessage());
        return null;
    }
}

/**
 * Retourne les IDs des missions auxquelles l'utilisateur a participÃ©.
 * UtilisÃ© pour afficher l'Ã©tat "DÃ©jÃ  participÃ©" sur les listes.
 */
function fetch_user_participated_mission_ids(int $userId): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("SELECT mission_id FROM participations WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_participated_mission_ids : ' . $e->getMessage());
        return [];
    }
}

/**
 * VÃ©rifie si un utilisateur a dÃ©jÃ  participÃ© Ã  une mission.
 */
function has_user_participated(int $userId, int $missionId): bool {
    $pdo = db();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM participations
            WHERE user_id = :uid AND mission_id = :mid
        ");
        $stmt->execute([':uid' => $userId, ':mid' => $missionId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('[ZONE85] has_user_participated : ' . $e->getMessage());
        return false;
    }
}

/**
 * Attribue des XP Ã  un utilisateur (standalone, transaction propre).
 * Pour usage hors participation (admin, bonus ponctuel).
 */
function award_xp(int $userId, int $amount, string $sourceType, ?int $sourceId, string $reason): bool {
    $pdo = db();
    if (!$pdo) return false;
    try {
        $pdo->beginTransaction();
        $pdo->prepare("
            INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
            VALUES (:uid, :st, :sid, :amt, :reason)
        ")->execute([':uid' => $userId, ':st' => $sourceType, ':sid' => $sourceId,
                     ':amt' => $amount,  ':reason' => $reason]);
        $oldRow = $pdo->prepare("SELECT level FROM users WHERE id = :uid LIMIT 1");
        $oldRow->execute([':uid' => $userId]);
        $oldLevel = (int)($oldRow->fetchColumn() ?: 1);
        $pdo->prepare("UPDATE users SET xp_total = xp_total + :amt WHERE id = :uid")
            ->execute([':amt' => $amount, ':uid' => $userId]);
        $row = $pdo->prepare("SELECT xp_total FROM users WHERE id = :uid LIMIT 1");
        $row->execute([':uid' => $userId]);
        $newXp    = (int)$row->fetchColumn();
        $newLevel = function_exists('get_user_level_from_xp') ? get_user_level_from_xp($newXp) : 1;
        $pdo->prepare("UPDATE users SET level = :lvl WHERE id = :uid")
            ->execute([':lvl' => $newLevel, ':uid' => $userId]);
        $pdo->commit();
        // Level-up notification (non-blocking, after commit)
        if ($newLevel > $oldLevel && function_exists('push_notification')) {
            $lvlName = function_exists('get_level_name') ? get_level_name($newLevel) : 'Niveau ' . $newLevel;
            push_notification($userId, 'level_up',
                'Niveau ' . $newLevel . ' atteint — ' . $lvlName . ' !',
                ['link_url' => 'profil.php']
            );
        }
        // Check auto-badge conditions after XP change (non-blocking)
        if (function_exists('check_and_award_badges')) {
            check_and_award_badges($userId);
        }
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[ZONE85] award_xp : ' . $e->getMessage());
        return false;
    }
}

/**
 * Attribue des points Ã  un clan pour une saison (standalone, transaction propre).
 * Pour usage hors participation (admin, bonus ponctuel).
 */
function award_clan_points(int $clanId, int $seasonId, int $points, string $sourceType,
                           ?int $sourceId, string $reason, ?int $userId = null): bool {
    $pdo = db();
    if (!$pdo) return false;
    try {
        $pdo->beginTransaction();
        $pdo->prepare("
            INSERT INTO clan_score_logs
                (clan_id, season_id, user_id, source_type, source_id, points, reason)
            VALUES (:cid, :sid, :uid, :st, :src, :pts, :reason)
        ")->execute([':cid' => $clanId, ':sid' => $seasonId, ':uid' => $userId,
                     ':st'  => $sourceType, ':src' => $sourceId,
                     ':pts' => $points,     ':reason' => $reason]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[ZONE85] award_clan_points : ' . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre une participation complÃ¨te en une transaction atomique.
 *
 * Ã‰tapes : doublon check â†’ INSERT participations â†’ INSERT xp_logs â†’
 *          UPDATE users.xp_total + level â†’ INSERT clan_score_logs (si applicable).
 *
 * @return array{ok:bool, error:?string, xp_awarded:int, new_xp_total:int,
 *               participation_id:int, status:string, debug_error:?string}
 */
function create_participation(int $userId, int $missionId, array $data = []): array {
    $pdo = db();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'Base de donnÃ©es non disponible.',
                'xp_awarded' => 0, 'new_xp_total' => 0, 'participation_id' => 0, 'status' => ''];
    }

    try {
        // 1. Anti-doublon
        $dup = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id = :uid AND mission_id = :mid");
        $dup->execute([':uid' => $userId, ':mid' => $missionId]);
        if ((int)$dup->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Tu as dÃ©jÃ  participÃ© Ã  cette mission.',
                    'xp_awarded' => 0, 'new_xp_total' => 0, 'participation_id' => 0, 'status' => 'duplicate'];
        }

        // 2. Charger la mission active
        $ms = $pdo->prepare("SELECT * FROM missions WHERE id = :id AND status = 'active' LIMIT 1");
        $ms->execute([':id' => $missionId]);
        $mission = $ms->fetch();
        if (!$mission) {
            return ['ok' => false, 'error' => 'Mission introuvable ou inactive.',
                    'xp_awarded' => 0, 'new_xp_total' => 0, 'participation_id' => 0, 'status' => ''];
        }

        // 3. Charger l'utilisateur
        $us = $pdo->prepare("SELECT id, xp_total, level, clan_id FROM users WHERE id = :id AND status = 'active' LIMIT 1");
        $us->execute([':id' => $userId]);
        $user = $us->fetch();
        if (!$user) {
            return ['ok' => false, 'error' => 'Utilisateur introuvable.',
                    'xp_awarded' => 0, 'new_xp_total' => 0, 'participation_id' => 0, 'status' => ''];
        }

        // 4. Statut et XP selon validation_mode
        $shouldAuto  = in_array($mission['validation_mode'], ['auto', 'hybrid']);
        $partStatus  = $shouldAuto ? 'auto_validated' : 'pending';
        $xpToAward   = $shouldAuto ? (int)$mission['xp_participation'] : 0;
        if ($shouldAuto && $xpToAward <= 0) $xpToAward = 5; // fallback V1
        $clanPts     = $shouldAuto ? (int)$mission['clan_points_participation'] : 0;

        // â”€â”€ TRANSACTION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $pdo->beginTransaction();

        // 5. INSERT participation
        $ins = $pdo->prepare("
            INSERT INTO participations
                (user_id, mission_id, game_id, answer_text, selected_option_id,
                 status, is_success, xp_awarded, clan_points_awarded)
            VALUES
                (:uid, :mid, :gid, :answer, :opt, :status, :success, :xp, :cp)
        ");
        $ins->execute([
            ':uid'     => $userId,
            ':mid'     => $missionId,
            ':gid'     => $mission['game_id'] ?: null,
            ':answer'  => $data['answer_text']       ?? null,
            ':opt'     => $data['selected_option_id'] ?? null,
            ':status'  => $partStatus,
            ':success' => $shouldAuto ? 1 : 0,
            ':xp'      => $xpToAward,
            ':cp'      => $clanPts,
        ]);
        $participationId = (int)$pdo->lastInsertId();

        $newXpTotal = (int)$user['xp_total'];

        // 6. XP si auto-validÃ©
        if ($xpToAward > 0) {
            $pdo->prepare("
                INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
                VALUES (:uid, 'mission_participation', :sid, :amt, :reason)
            ")->execute([
                ':uid'    => $userId,
                ':sid'    => $participationId,
                ':amt'    => $xpToAward,
                ':reason' => 'Participation Ã  la mission : ' . mb_substr($mission['title'], 0, 80),
            ]);

            $pdo->prepare("UPDATE users SET xp_total = xp_total + :amt WHERE id = :uid")
                ->execute([':amt' => $xpToAward, ':uid' => $userId]);

            $newXpTotal += $xpToAward;

            if (function_exists('get_user_level_from_xp')) {
                $newLevel = get_user_level_from_xp($newXpTotal);
                $pdo->prepare("UPDATE users SET level = :lvl WHERE id = :uid")
                    ->execute([':lvl' => $newLevel, ':uid' => $userId]);
            }
        }

        // 7. Points clan si applicable
        if ($clanPts > 0 && !empty($user['clan_id'])) {
            $sr = _active_season_row();
            if ($sr) {
                $pdo->prepare("
                    INSERT INTO clan_score_logs
                        (clan_id, season_id, user_id, source_type, source_id, points, reason)
                    VALUES (:cid, :sid, :uid, 'mission_participation', :src, :pts, :reason)
                ")->execute([
                    ':cid'    => (int)$user['clan_id'],
                    ':sid'    => (int)$sr['id'],
                    ':uid'    => $userId,
                    ':src'    => $participationId,
                    ':pts'    => $clanPts,
                    ':reason' => 'Participation clan : ' . mb_substr($mission['title'], 0, 80),
                ]);
            }
        }

        $pdo->commit();

        return [
            'ok'               => true,
            'participation_id' => $participationId,
            'xp_awarded'       => $xpToAward,
            'new_xp_total'     => $newXpTotal,
            'status'           => $partStatus,
            'error'            => null,
            'debug_error'      => null,
        ];

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[ZONE85] create_participation : ' . $e->getMessage());
        $debug = (defined('APP_ENV') && APP_ENV === 'dev') ? $e->getMessage() : null;
        return [
            'ok'               => false,
            'error'            => 'Une erreur est survenue. Veuillez rÃ©essayer.',
            'debug_error'      => $debug,
            'xp_awarded'       => 0,
            'new_xp_total'     => 0,
            'participation_id' => 0,
            'status'           => '',
        ];
    }
}

// ============================================================
// ADMIN V8 â€” Validation / Refus participations manuelles
// ============================================================

/**
 * Valide une participation manuelle (pending â†’ validated).
 * Attribue xp_success et clan_points_success en transaction.
 * Idempotent : si dÃ©jÃ  traitÃ©e, retourne une erreur propre.
 *
 * @return array{ok:bool, error:?string, xp_awarded:int, clan_pts:int}
 */
function admin_validate_participation(int $participation_id, int $admin_user_id): array {
    $pdo = db();
    if (!$pdo) return ['ok' => false, 'error' => 'Base de donnÃ©es non disponible.', 'xp_awarded' => 0, 'clan_pts' => 0];

    try {
        // 1. Charger la participation + mission + user en une requÃªte
        $s = $pdo->prepare("
            SELECT p.*, m.xp_success, m.clan_points_success, m.title AS mission_title,
                   u.clan_id, u.xp_total, u.level, u.pseudo
            FROM participations p
            JOIN missions m ON m.id = p.mission_id
            JOIN users    u ON u.id = p.user_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $s->execute([':id' => $participation_id]);
        $row = $s->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'Participation introuvable.', 'xp_awarded' => 0, 'clan_pts' => 0];
        }
        if ($row['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Action impossible : participation dÃ©jÃ  traitÃ©e (' . $row['status'] . ').', 'xp_awarded' => 0, 'clan_pts' => 0];
        }

        $xp_success  = (int)$row['xp_success'];
        $clan_pts    = (int)$row['clan_points_success'];
        $user_id     = (int)$row['user_id'];
        $clan_id     = (int)$row['clan_id'];
        $new_xp      = (int)$row['xp_total'];

        $pdo->beginTransaction();

        // 2. Mettre Ã  jour la participation
        $pdo->prepare("
            UPDATE participations
            SET status       = 'validated',
                is_success   = 1,
                validated_by = :admin,
                validated_at = NOW(),
                xp_awarded   = xp_awarded + :xp,
                clan_points_awarded = clan_points_awarded + :cp,
                updated_at   = NOW()
            WHERE id = :id AND status = 'pending'
        ")->execute([':admin' => $admin_user_id, ':xp' => $xp_success, ':cp' => $clan_pts, ':id' => $participation_id]);

        // 3. XP rÃ©ussite si > 0
        if ($xp_success > 0) {
            $pdo->prepare("
                INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
                VALUES (:uid, 'mission_success', :src, :amt, :reason)
            ")->execute([
                ':uid'    => $user_id,
                ':src'    => $participation_id,
                ':amt'    => $xp_success,
                ':reason' => 'RÃ©ussite validÃ©e : ' . mb_substr($row['mission_title'], 0, 60),
            ]);

            $pdo->prepare("UPDATE users SET xp_total = xp_total + :amt WHERE id = :uid")
                ->execute([':amt' => $xp_success, ':uid' => $user_id]);

            $new_xp += $xp_success;

            if (function_exists('get_user_level_from_xp')) {
                $pdo->prepare("UPDATE users SET level = :lvl WHERE id = :uid")
                    ->execute([':lvl' => get_user_level_from_xp($new_xp), ':uid' => $user_id]);
            }
        }

        // 4. Points clan rÃ©ussite si > 0
        if ($clan_pts > 0 && $clan_id > 0) {
            $sr = _active_season_row();
            if ($sr) {
                $pdo->prepare("
                    INSERT INTO clan_score_logs
                        (clan_id, season_id, user_id, source_type, source_id, points, reason)
                    VALUES (:cid, :sid, :uid, 'mission_success', :src, :pts, :reason)
                ")->execute([
                    ':cid'    => $clan_id,
                    ':sid'    => (int)$sr['id'],
                    ':uid'    => $user_id,
                    ':src'    => $participation_id,
                    ':pts'    => $clan_pts,
                    ':reason' => 'RÃ©ussite clan validÃ©e : ' . mb_substr($row['mission_title'], 0, 60),
                ]);
            }
        }

        $pdo->commit();

        // Auto-badge check after participation validated (non-blocking)
        check_and_award_badges($user_id);

        // Notification + fil communautaire (hors transaction — ne bloque pas si table absente)
        if (function_exists('push_notification')) {
            $xp_str = $xp_success > 0 ? ' (+' . $xp_success . ' XP)' : '';
            push_notification($user_id, 'mission_validated',
                'Mission validée : ' . mb_substr($row['mission_title'], 0, 60) . $xp_str,
                ['link_url' => 'missions.php', 'mission_id' => (int)$row['mission_id']]
            );
        }
        if (function_exists('push_community_feed')) {
            push_community_feed('mission_complete', [
                'user_id'    => $user_id,
                'clan_id'    => $clan_id ?: null,
                'mission_id' => (int)$row['mission_id'],
                'title'      => ($row['pseudo'] ?? 'Zonaute') . ' a validé : ' . mb_substr($row['mission_title'], 0, 60),
                'icon_emoji' => '✅',
                'link_url'   => 'mission.php?id=' . (int)$row['mission_id'],
            ]);
        }

        return ['ok' => true, 'error' => null, 'xp_awarded' => $xp_success, 'clan_pts' => $clan_pts];

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[ZONE85] admin_validate_participation : ' . $e->getMessage());
        $debug = (defined('APP_ENV') && APP_ENV === 'dev') ? $e->getMessage() : null;
        return ['ok' => false, 'error' => 'Erreur lors de la validation.', 'debug' => $debug, 'xp_awarded' => 0, 'clan_pts' => 0];
    }
}

/**
 * Refuse une participation manuelle (pending â†’ rejected).
 * N'attribue aucun XP rÃ©ussite ni points clan.
 *
 * @return array{ok:bool, error:?string}
 */
function admin_reject_participation(int $participation_id, int $admin_user_id): array {
    $pdo = db();
    if (!$pdo) return ['ok' => false, 'error' => 'Base de donnÃ©es non disponible.'];

    try {
        $s = $pdo->prepare("SELECT status FROM participations WHERE id = :id LIMIT 1");
        $s->execute([':id' => $participation_id]);
        $row = $s->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'Participation introuvable.'];
        }
        if ($row['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Action impossible : participation dÃ©jÃ  traitÃ©e (' . $row['status'] . ').'];
        }

        $pdo->prepare("
            UPDATE participations
            SET status       = 'rejected',
                validated_by = :admin,
                validated_at = NOW(),
                updated_at   = NOW()
            WHERE id = :id AND status = 'pending'
        ")->execute([':admin' => $admin_user_id, ':id' => $participation_id]);

        return ['ok' => true, 'error' => null];

    } catch (Throwable $e) {
        error_log('[ZONE85] admin_reject_participation : ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors du refus.'];
    }
}

// ============================================================
// HIDDEN HUNT V9 â€” Moteur collectibles
// ============================================================

/**
 * Retourne les collectibles actifs pour une page donnÃ©e et une mission active.
 * Si $user_id > 0 : exclut ceux dÃ©jÃ  trouvÃ©s par l'utilisateur.
 */
function fetch_active_collectibles_for_page(string $page_slug, int $user_id = 0): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        if ($user_id > 0) {
            $sql = "
                SELECT mc.*
                FROM mission_collectibles mc
                JOIN missions m ON m.id = mc.mission_id
                WHERE mc.page_slug = :slug
                  AND mc.is_active = 1
                  AND m.status = 'active'
                  AND m.mission_type = 'hidden_hunt'
                  AND mc.id NOT IN (
                      SELECT uc.collectible_id FROM user_collectibles uc
                      WHERE uc.user_id = :uid
                  )
                ORDER BY mc.sort_order ASC, mc.id ASC
            ";
            $s = $pdo->prepare($sql);
            $s->execute([':slug' => $page_slug, ':uid' => $user_id]);
        } else {
            $sql = "
                SELECT mc.*
                FROM mission_collectibles mc
                JOIN missions m ON m.id = mc.mission_id
                WHERE mc.page_slug = :slug
                  AND mc.is_active = 1
                  AND m.status = 'active'
                  AND m.mission_type = 'hidden_hunt'
                ORDER BY mc.sort_order ASC, mc.id ASC
            ";
            $s = $pdo->prepare($sql);
            $s->execute([':slug' => $page_slug]);
        }
        return $s->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_active_collectibles_for_page : ' . $e->getMessage());
        return [];
    }
}

/**
 * Retourne le nombre d'objets trouvÃ©s par un user pour une mission.
 */
function count_user_collectibles(int $user_id, int $mission_id): int {
    $pdo = db();
    if (!$pdo) return 0;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM user_collectibles WHERE user_id=:uid AND mission_id=:mid");
        $s->execute([':uid' => $user_id, ':mid' => $mission_id]);
        return (int)$s->fetchColumn();
    } catch (PDOException $e) {
        error_log('[ZONE85] count_user_collectibles : ' . $e->getMessage());
        return 0;
    }
}

/**
 * Retourne le nombre total d'objets actifs d'une mission hidden_hunt.
 */
function count_mission_collectibles(int $mission_id): int {
    $pdo = db();
    if (!$pdo) return 0;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM mission_collectibles WHERE mission_id=:mid AND is_active=1");
        $s->execute([':mid' => $mission_id]);
        return (int)$s->fetchColumn();
    } catch (PDOException $e) {
        error_log('[ZONE85] count_mission_collectibles : ' . $e->getMessage());
        return 0;
    }
}

/**
 * Retourne les missions hidden_hunt en cours ou terminÃ©es pour un user (profil).
 * Retourne [] si aucune.
 */
function fetch_user_hidden_hunts(int $user_id): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        // Missions hidden_hunt actives ou que l'user a commencÃ©
        $s = $pdo->prepare("
            SELECT DISTINCT
                m.id, m.title, m.slug, m.status,
                m.xp_success, m.badge_reward_id,
                (SELECT COUNT(*) FROM mission_collectibles mc WHERE mc.mission_id = m.id AND mc.is_active = 1) AS total,
                (SELECT COUNT(*) FROM user_collectibles uc WHERE uc.user_id = :uid AND uc.mission_id = m.id) AS found,
                p.status AS part_status, p.xp_awarded AS part_xp
            FROM missions m
            LEFT JOIN participations p ON p.mission_id = m.id AND p.user_id = :uid2
            WHERE m.mission_type = 'hidden_hunt'
              AND m.status IN ('active','closed')
              AND (
                  m.status = 'active'
                  OR EXISTS (SELECT 1 FROM user_collectibles uc2 WHERE uc2.user_id = :uid3 AND uc2.mission_id = m.id)
              )
            ORDER BY m.status = 'active' DESC, m.id DESC
        ");
        $s->execute([':uid' => $user_id, ':uid2' => $user_id, ':uid3' => $user_id]);
        $rows = $s->fetchAll();
        foreach ($rows as &$r) {
            $r['total']     = (int)$r['total'];
            $r['found']     = (int)$r['found'];
            $r['completed'] = $r['found'] > 0 && $r['found'] >= $r['total'];
            $r['pct']       = ($r['total'] > 0) ? (int)round($r['found'] / $r['total'] * 100) : 0;
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_hidden_hunts : ' . $e->getMessage());
        return [];
    }
}

/**
 * Enregistre qu'un utilisateur a trouvÃ© un collectible.
 * GÃ¨re la progression et la complÃ©tion de mission en transaction atomique.
 *
 * @return array{ok:bool, reason:string, message:string, found:int, total:int,
 *               completed:bool, xp_awarded:int, badge_awarded:?string}
 */
function process_collectible_found(int $user_id, int $collectible_id): array {
    $pdo = db();
    $empty = ['ok'=>false,'reason'=>'error','message'=>'Erreur serveur.',
              'found'=>0,'total'=>0,'completed'=>false,'xp_awarded'=>0,'badge_awarded'=>null];
    if (!$pdo) return $empty;

    try {
        // 1. Charger le collectible + mission + user
        $s = $pdo->prepare("
            SELECT mc.*, m.status AS mission_status, m.validation_mode,
                   m.xp_success, m.clan_points_success, m.badge_reward_id, m.title AS mission_title,
                   u.clan_id, u.xp_total, u.level
            FROM mission_collectibles mc
            JOIN missions m ON m.id = mc.mission_id
            JOIN users    u ON u.id = :uid
            WHERE mc.id = :cid AND mc.is_active = 1
            LIMIT 1
        ");
        $s->execute([':uid' => $user_id, ':cid' => $collectible_id]);
        $coll = $s->fetch();

        if (!$coll) {
            return array_merge($empty, ['reason'=>'not_found','message'=>'Objet introuvable ou inactif.']);
        }
        if ($coll['mission_status'] !== 'active') {
            return array_merge($empty, ['reason'=>'mission_inactive','message'=>'Cette chasse n\'est plus active.']);
        }

        $mission_id = (int)$coll['mission_id'];

        // 2. Anti-doublon avant transaction
        $dup = $pdo->prepare("SELECT id FROM user_collectibles WHERE user_id=:uid AND collectible_id=:cid LIMIT 1");
        $dup->execute([':uid'=>$user_id, ':cid'=>$collectible_id]);
        if ($dup->fetch()) {
            $found = count_user_collectibles($user_id, $mission_id);
            $total = count_mission_collectibles($mission_id);
            return ['ok'=>false,'reason'=>'already_found',
                    'message'=>'Tu avais dÃ©jÃ  trouvÃ© cet objet.',
                    'found'=>$found,'total'=>$total,'completed'=>($found>=$total),'xp_awarded'=>0,'badge_awarded'=>null];
        }

        // 3. Compter total actifs
        $total = count_mission_collectibles($mission_id);

        $pdo->beginTransaction();

        // 4. Enregistrer la trouvaille
        $pdo->prepare("INSERT INTO user_collectibles (user_id, mission_id, collectible_id) VALUES (:uid,:mid,:cid)")
            ->execute([':uid'=>$user_id, ':mid'=>$mission_id, ':cid'=>$collectible_id]);

        // 5. Compter le nombre trouvÃ© maintenant
        $s2 = $pdo->prepare("SELECT COUNT(*) FROM user_collectibles WHERE user_id=:uid AND mission_id=:mid");
        $s2->execute([':uid'=>$user_id, ':mid'=>$mission_id]);
        $found = (int)$s2->fetchColumn();

        $completed   = ($found >= $total && $total > 0);
        $xp_awarded  = 0;
        $badge_name  = null;
        $new_xp      = (int)$coll['xp_total'];

        // 6. Assurer l'existence d'une participation (INSERT IGNORE = no-op si elle existe dÃ©jÃ )
        $pdo->prepare("
            INSERT IGNORE INTO participations
                (user_id, mission_id, status, is_success, xp_awarded, clan_points_awarded)
            VALUES (:uid, :mid, 'pending', 0, 0, 0)
        ")->execute([':uid'=>$user_id, ':mid'=>$mission_id]);

        // 7. Si complÃ©tion : valider + XP + pts clan + badge
        if ($completed) {
            $xp_success = (int)$coll['xp_success'];
            $clan_pts   = (int)$coll['clan_points_success'];
            $clan_id    = (int)$coll['clan_id'];

            // VÃ©rifier si la participation Ã©tait dÃ©jÃ  validÃ©e (anti-doublon XP)
            $chk = $pdo->prepare("SELECT status FROM participations WHERE user_id=:uid AND mission_id=:mid LIMIT 1");
            $chk->execute([':uid'=>$user_id, ':mid'=>$mission_id]);
            $chk_row = $chk->fetch();
            $already_validated = ($chk_row && in_array($chk_row['status'], ['validated','auto_validated']));

            // Valider la participation â€” sans filtre sur status (robuste dans tous les cas)
            $pdo->prepare("
                UPDATE participations
                SET status='auto_validated', is_success=1,
                    xp_awarded = :xp,
                    clan_points_awarded = :cp,
                    validated_at = COALESCE(validated_at, NOW()),
                    updated_at = NOW()
                WHERE user_id=:uid AND mission_id=:mid
            ")->execute([':xp'=>$xp_success, ':cp'=>$clan_pts, ':uid'=>$user_id, ':mid'=>$mission_id]);

            // XP rÃ©ussite â€” uniquement si pas dÃ©jÃ  attribuÃ©s
            if ($xp_success > 0 && !$already_validated) {
                $part_s = $pdo->prepare("SELECT id FROM participations WHERE user_id=:uid AND mission_id=:mid LIMIT 1");
                $part_s->execute([':uid'=>$user_id, ':mid'=>$mission_id]);
                $part_row = $part_s->fetch();
                $part_src = $part_row ? (int)$part_row['id'] : null;

                $pdo->prepare("
                    INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason)
                    VALUES (:uid, 'hidden_hunt_completion', :src, :amt, :reason)
                ")->execute([
                    ':uid'    => $user_id,
                    ':src'    => $part_src,
                    ':amt'    => $xp_success,
                    ':reason' => 'Chasse complÃ©tÃ©e : ' . mb_substr($coll['mission_title'], 0, 60),
                ]);

                $pdo->prepare("UPDATE users SET xp_total=xp_total+:amt WHERE id=:uid")
                    ->execute([':amt'=>$xp_success, ':uid'=>$user_id]);

                $new_xp += $xp_success;

                if (function_exists('get_user_level_from_xp')) {
                    $pdo->prepare("UPDATE users SET level=:lvl WHERE id=:uid")
                        ->execute([':lvl'=>get_user_level_from_xp($new_xp), ':uid'=>$user_id]);
                }

                $xp_awarded = $xp_success;
            }

            // Points clan â€” uniquement si pas dÃ©jÃ  attribuÃ©s
            if ($clan_pts > 0 && $clan_id > 0 && !$already_validated) {
                $sr = _active_season_row();
                if ($sr) {
                    $pdo->prepare("
                        INSERT INTO clan_score_logs
                            (clan_id,season_id,user_id,source_type,source_id,points,reason)
                        VALUES (:cid,:sid,:uid,'hidden_hunt_completion',:src,:pts,:reason)
                    ")->execute([
                        ':cid'=>$clan_id, ':sid'=>(int)$sr['id'], ':uid'=>$user_id,
                        ':src'=>null, ':pts'=>$clan_pts,
                        ':reason'=>'Chasse clan : ' . mb_substr($coll['mission_title'], 0, 60),
                    ]);
                }
            }

            // Badge si dÃ©fini
            if (!empty($coll['badge_reward_id'])) {
                try {
                    $bs = $pdo->prepare("SELECT title FROM badges WHERE id=:bid LIMIT 1");
                    $bs->execute([':bid'=>(int)$coll['badge_reward_id']]);
                    $badge = $bs->fetch();
                    if ($badge) {
                        $pdo->prepare("
                            INSERT IGNORE INTO user_badges (user_id, badge_id, source_type, source_id)
                            VALUES (:uid, :bid, 'mission_success', :src)
                        ")->execute([':uid'=>$user_id, ':bid'=>(int)$coll['badge_reward_id'], ':src'=>$mission_id]);
                        $badge_name = $badge['title'];
                    }
                } catch (PDOException $e2) {
                    error_log('[ZONE85] badge award hidden_hunt : ' . $e2->getMessage());
                }
            }
        }

        $pdo->commit();

        $msg = $completed
            ? 'Bravo ! Tu as complÃ©tÃ© la chasse !'
            : ($coll['success_message'] ?: ('TrouvÃ© ! ' . $coll['title']));

        return [
            'ok'            => true,
            'reason'        => 'found',
            'message'       => $msg,
            'title'         => $coll['success_title'] ?: 'Bravo !',
            'success_gif'   => $coll['success_gif']   ?: null,
            'collectible_title' => $coll['title'],
            'found'         => $found,
            'total'         => $total,
            'completed'     => $completed,
            'xp_awarded'    => $xp_awarded,
            'badge_awarded' => $badge_name,
        ];

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[ZONE85] process_collectible_found : ' . $e->getMessage());
        $debug = (defined('APP_ENV') && APP_ENV === 'dev') ? $e->getMessage() : null;
        return array_merge($empty, ['debug' => $debug]);
    }
}

/**
 * Retourne les collectibles d'une mission hidden_hunt (admin).
 */
function fetch_mission_collectibles_admin(int $mission_id): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $s = $pdo->prepare("
            SELECT mc.*,
                   (SELECT COUNT(*) FROM user_collectibles uc WHERE uc.collectible_id = mc.id) AS found_count
            FROM mission_collectibles mc
            WHERE mc.mission_id = :mid
            ORDER BY mc.sort_order ASC, mc.id ASC
        ");
        $s->execute([':mid' => $mission_id]);
        return $s->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_mission_collectibles_admin : ' . $e->getMessage());
        return [];
    }
}

/**
 * Helper d'upload pour objets cachÃ©s et GIFs de succÃ¨s.
 * $type = 'object' | 'success'
 */
function upload_collectible_media(array $file, string $type = 'object'): array {
    $max_size = ($type === 'success') ? 5 * 1024 * 1024 : 1 * 1024 * 1024;
    $allowed  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd.',
            UPLOAD_ERR_PARTIAL => 'Envoi interrompu.',
            default            => 'Erreur lors de l\'envoi.',
        };
        return ['ok'=>false, 'error'=>$msg];
    }
    if ($file['size'] > $max_size) {
        return ['ok'=>false, 'error'=>'Fichier trop lourd (max ' . ($max_size/1024/1024) . ' Mo).'];
    }

    // Pour les GIFs, vÃ©rifier par extension + mime
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, $allowed)) {
        return ['ok'=>false, 'error'=>'Type non autorisÃ©. Utilisez jpg, png, webp ou gif.'];
    }

    $upload_dir = defined('BASE_PATH') ? BASE_PATH . 'uploads/collectibles/' : dirname(__DIR__) . '/uploads/collectibles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext      = $allowed[$mime];
    $prefix   = ($type === 'success') ? 'success_' : 'obj_';
    $filename = $prefix . bin2hex(random_bytes(12)) . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok'=>false, 'error'=>'Impossible d\'enregistrer le fichier.'];
    }

    return ['ok'=>true, 'path'=>'uploads/collectibles/' . $filename];
}


// ============================================================
// V11 â€” Fonctions communautaires
// ============================================================

/**
 * InsÃ¨re un Ã©vÃ©nement dans le feed communautaire.
 * Non-bloquant : catch silencieux si la table n'existe pas encore.
 */
function push_community_feed(string $event_type, array $data = []): void {
    $pdo = db();
    if (!$pdo) return;
    $allowed = ['mission_new','mission_complete','badge_unlock','flash_start','flash_end',
                'season_start','season_end','clan_lead','trophy_awarded',
                'collectible_found','rando_done','ktc_win'];
    if (!in_array($event_type, $allowed)) return;
    try {
        $pdo->prepare("
            INSERT INTO community_feed
                (event_type, user_id, clan_id, mission_id, badge_id, season_id,
                 title, body, icon_emoji, link_url, is_pinned)
            VALUES
                (:et, :uid, :cid, :mid, :bid, :sid,
                 :title, :body, :icon, :link, :pinned)
        ")->execute([
            ':et'     => $event_type,
            ':uid'    => $data['user_id']    ?? null,
            ':cid'    => $data['clan_id']    ?? null,
            ':mid'    => $data['mission_id'] ?? null,
            ':bid'    => $data['badge_id']   ?? null,
            ':sid'    => $data['season_id']  ?? null,
            ':title'  => substr($data['title']  ?? '', 0, 255),
            ':body'   => substr($data['body']   ?? '', 0, 512),
            ':icon'   => $data['icon_emoji'] ?? 'ðŸ“‹',
            ':link'   => $data['link_url']   ?? null,
            ':pinned' => $data['is_pinned']  ?? 0,
        ]);
    } catch (PDOException $e) { /* table peut ne pas exister */ }
}

/**
 * Retourne le feed communautaire paginÃ©.
 */
function fetch_community_feed(int $page = 1, int $per_page = 30): array {
    $pdo = db();
    if (!$pdo) return [];
    $offset = ($page - 1) * $per_page;
    try {
        $stmt = $pdo->prepare("
            SELECT cf.*,
                   u.pseudo, u.avatar_type, u.avatar_config, u.avatar_file,
                   c.name AS clan_name, c.slug AS clan_slug,
                   m.title AS mission_title,
                   b.title AS badge_name, b.icon_emoji AS badge_emoji
            FROM community_feed cf
            LEFT JOIN users    u ON u.id = cf.user_id
            LEFT JOIN clans    c ON c.id = cf.clan_id
            LEFT JOIN missions m ON m.id = cf.mission_id
            LEFT JOIN badges   b ON b.id = cf.badge_id
            ORDER BY cf.is_pinned DESC, cf.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Retourne les flash events actifs.
 */
function fetch_active_flash_events(int $limit = 5): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM missions
            WHERE is_flash = 1 AND status = 'active'
              AND (flash_end_at IS NULL OR flash_end_at > NOW())
            ORDER BY flash_end_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Retourne la grande mission de la saison active.
 */
function fetch_grande_mission_active(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    $sr = _active_season_row();
    if (!$sr) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT m.* FROM missions m
            WHERE m.is_grande_mission = 1
              AND m.grande_mission_season_id = :sid
              AND m.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':sid' => (int)$sr['id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        // Fallback : premiÃ¨re mission collective de la saison
        try {
            $stmt2 = $pdo->prepare("
                SELECT * FROM missions
                WHERE season_id = :sid AND mission_type = 'seasonal_collective' AND status = 'active'
                ORDER BY id ASC LIMIT 1
            ");
            $stmt2->execute([':sid' => (int)$sr['id']]);
            return $stmt2->fetch() ?: null;
        } catch (PDOException $e2) {
            return null;
        }
    }
}

/**
 * Retourne le passeport Zone85 d'un utilisateur (profil enrichi V11).
 * Calcule : saisons vÃ©cues, trophÃ©es, missions terminÃ©es, badges par catÃ©gorie.
 */
function fetch_user_passport(int $user_id): array {
    $pdo = db();
    $passport = [
        'seasons_lived'   => 0,
        'trophies'        => 0,
        'missions_total'  => 0,
        'flash_events'    => 0,
        'ktc_correct'     => 0,
        'ktc_total'       => 0,
        'randos'          => 0,
        'collectibles'    => 0,
        'badges_by_cat'   => [],
        'seasons_list'    => [],
        'clan_trophies'   => 0,
    ];
    if (!$pdo) return $passport;
    try {
        // Saisons vÃ©cues (a participÃ© Ã  au moins 1 mission dans la saison)
        $s = $pdo->prepare("
            SELECT COUNT(DISTINCT m.season_id) FROM participations p
            JOIN missions m ON m.id = p.mission_id
            WHERE p.user_id = :uid AND m.season_id IS NOT NULL
        ");
        $s->execute([':uid' => $user_id]);
        $passport['seasons_lived'] = (int)$s->fetchColumn();

        // Missions validÃ©es totales
        $s = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id=:uid AND status IN ('validated','auto_validated')");
        $s->execute([':uid' => $user_id]);
        $passport['missions_total'] = (int)$s->fetchColumn();

        // Collectibles
        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM user_collectibles WHERE user_id=:uid");
            $s->execute([':uid' => $user_id]);
            $passport['collectibles'] = (int)$s->fetchColumn();
        } catch (PDOException $e) {}

        // KTC : épisodes auxquels l'utilisateur a participé (proposition ou vote)
        try {
            $s = $pdo->prepare("
                SELECT COUNT(DISTINCT episode_id) FROM (
                    SELECT episode_id FROM ktc_propositions WHERE user_id=:uid
                    UNION ALL
                    SELECT episode_id FROM ktc_votes WHERE user_id=:uid2
                ) ktc_part
            ");
            $s->execute([':uid' => $user_id, ':uid2' => $user_id]);
            $passport['ktc_total']   = (int)$s->fetchColumn();
            $passport['ktc_correct'] = 0;
        } catch (PDOException $e) {}

        // Randos validées admin — depuis rando_participations (source V12)
        // Cohérent avec passeport.php qui utilise la même table
        try {
            $s = $pdo->prepare("
                SELECT COUNT(*) FROM rando_participations
                WHERE user_id = :uid
                  AND status IN ('stamped', 'validated')
            ");
            $s->execute([':uid' => $user_id]);
            $passport['randos'] = (int)$s->fetchColumn();
        } catch (PDOException $e) {
            // Fallback participations si table absente
            try {
                $s2 = $pdo->prepare("
                    SELECT COUNT(*) FROM participations p
                    JOIN missions m ON m.id = p.mission_id
                    WHERE p.user_id = :uid
                      AND m.mission_type = 'rando'
                      AND p.status IN ('validated','auto_validated')
                ");
                $s2->execute([':uid' => $user_id]);
                $passport['randos'] = (int)$s2->fetchColumn();
            } catch (PDOException $e2) {}
        }

        // Badges par catÃ©gorie
        try {
            $s = $pdo->prepare("
                SELECT b.category, COUNT(*) AS cnt
                FROM user_badges ub JOIN badges b ON b.id=ub.badge_id
                WHERE ub.user_id=:uid
                GROUP BY b.category
            ");
            $s->execute([':uid' => $user_id]);
            $cats = $s->fetchAll(PDO::FETCH_KEY_PAIR);
            $passport['badges_by_cat'] = $cats;
        } catch (PDOException $e) {}

        // Saisons vÃ©cues (liste)
        try {
            $s = $pdo->prepare("
                SELECT DISTINCT s.title, s.emoji, s.color_primary, s.start_date
                FROM participations p
                JOIN missions m ON m.id=p.mission_id
                JOIN seasons s ON s.id=m.season_id
                WHERE p.user_id=:uid
                ORDER BY s.start_date ASC
            ");
            $s->execute([':uid' => $user_id]);
            $passport['seasons_list'] = $s->fetchAll();
        } catch (PDOException $e) {}

        // TrophÃ©es de clan (clan gagnant d'une saison)
        try {
            $s = $pdo->prepare("
                SELECT COUNT(*) FROM season_trophies st
                JOIN users u ON u.clan_id=st.winning_clan_id
                WHERE u.id=:uid
            ");
            $s->execute([':uid' => $user_id]);
            $passport['clan_trophies'] = (int)$s->fetchColumn();
        } catch (PDOException $e) {}

    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_passport : ' . $e->getMessage());
    }
    return $passport;
}

/**
 * Retourne les saisons avec leurs stats.
 */
function fetch_all_seasons(): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("
            SELECT s.*,
                   c.name AS winner_name, c.slug AS winner_slug
            FROM seasons s
            LEFT JOIN clans c ON c.id = s.winner_clan_id
            ORDER BY s.id DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_all_seasons : ' . $e->getMessage());
        return [];
    }
}

/**
 * Retourne les posts mÃ©tÃ©o actifs.
 */
function fetch_weather_current(): ?array {
    $pdo = db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->query("
            SELECT * FROM weather_posts
            WHERE (expires_at IS NULL OR expires_at > NOW())
            ORDER BY published_at DESC LIMIT 1
        ");
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Retourne les alertes mÃ©tÃ©o actives.
 */
function fetch_weather_alerts(): array {
    $pdo = db();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("
            SELECT * FROM weather_posts
            WHERE is_alert=1 AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY published_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// ── Notifications ─────────────────────────────────────────────

/**
 * Crée une notification in-app pour un utilisateur.
 * Non-bloquant : silencieux si table inexistante.
 */
function push_notification(int $user_id, string $type, string $title, array $opts = []): void {
    $pdo = db();
    if (!$pdo || $user_id <= 0) return;
    $allowed_types = ['badge_unlock','mission_validated','mission_new','flash_start',
                      'level_up','clan_event','season_end','system'];
    if (!in_array($type, $allowed_types, true)) return;
    try {
        $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body, icon_emoji, link_url)
            VALUES (:uid, :type, :title, :body, :icon, :link)
        ")->execute([
            ':uid'   => $user_id,
            ':type'  => $type,
            ':title' => mb_substr($title, 0, 200),
            ':body'  => $opts['body']       ?? null,
            ':icon'  => $opts['icon_emoji'] ?? null,
            ':link'  => $opts['link_url']   ?? null,
        ]);
    } catch (PDOException $e) { /* silencieux */ }
}

/**
 * Nombre de notifications non lues d'un utilisateur.
 */
function count_unread_notifications(int $user_id): int {
    $pdo = db();
    if (!$pdo) return 0;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND read_at IS NULL");
        $s->execute([':uid' => $user_id]);
        return (int)$s->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Retourne les notifications d'un utilisateur (récentes en premier).
 */
function fetch_user_notifications(int $user_id, int $limit = 30, bool $unread_only = false): array {
    $pdo = db();
    if (!$pdo) return [];
    $where_unread = $unread_only ? 'AND read_at IS NULL' : '';
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = :uid {$where_unread}
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Marque les notifications comme lues.
 * $notif_id = null → marque toutes les notifications de l'utilisateur.
 */
function mark_notifications_read(int $user_id, ?int $notif_id = null): void {
    $pdo = db();
    if (!$pdo) return;
    try {
        if ($notif_id) {
            $pdo->prepare("UPDATE notifications SET read_at=NOW() WHERE id=:id AND user_id=:uid AND read_at IS NULL")
                ->execute([':id' => $notif_id, ':uid' => $user_id]);
        } else {
            $pdo->prepare("UPDATE notifications SET read_at=NOW() WHERE user_id=:uid AND read_at IS NULL")
                ->execute([':uid' => $user_id]);
        }
    } catch (PDOException $e) { /* silencieux */ }
}

/**
 * Retourne les XP gagnés depuis le début de la saison active.
 */
function fetch_user_xp_season(int $user_id): int {
    $pdo = db();
    if (!$pdo) return 0;
    try {
        $sr    = _active_season_row();
        $start = $sr ? $sr['start_date'] : '1970-01-01';
        $stmt  = $pdo->prepare("SELECT COALESCE(SUM(xp_amount),0) FROM xp_logs WHERE user_id=:uid AND created_at >= :s");
        $stmt->execute([':uid' => $user_id, ':s' => $start]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ── Auto-badge attribution engine ──────────────────────────────

/**
 * Vérifie et attribue automatiquement les badges débloqués par un utilisateur.
 * Appelée après chaque gain de XP ou validation de participation.
 * Entièrement silencieuse sur erreur — ne doit jamais bloquer la page appelante.
 *
 * @param  int   $userId
 * @return array Tableau des lignes de badges nouvellement attribués
 */
function check_and_award_badges(int $userId): array {
    if ($userId <= 0) return [];
    $pdo = db();
    if (!$pdo) return [];

    try {
        // -- 1. Charger tous les badges auto-éligibles (non-manual, non-special, non-hidden)
        $stmtBadges = $pdo->prepare(
            "SELECT * FROM badges
              WHERE condition_type NOT IN ('manual','special')
                AND is_hidden = 0"
        );
        $stmtBadges->execute();
        $badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);
        if (empty($badges)) return [];

        // -- 2. Badges déjà obtenus par l'utilisateur
        $stmtEarned = $pdo->prepare(
            "SELECT badge_id FROM user_badges WHERE user_id = :uid"
        );
        $stmtEarned->execute([':uid' => $userId]);
        $earnedIds = array_flip($stmtEarned->fetchAll(PDO::FETCH_COLUMN));

        // -- 3. Données utilisateur nécessaires aux vérifications
        $stmtUser = $pdo->prepare(
            "SELECT xp_total FROM users WHERE id = :uid LIMIT 1"
        );
        $stmtUser->execute([':uid' => $userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$userRow) return [];
        $userXp = (int)$userRow['xp_total'];

        // Saison active (peut être null)
        $activeSeason = _active_season_row();

        // -- 4. Évaluer chaque badge non encore obtenu
        $newlyAwarded = [];

        foreach ($badges as $badge) {
            $badgeId   = (int)$badge['id'];
            $condType  = $badge['condition_type'];
            $condValue = (int)$badge['condition_value'];

            // Ignorer les badges déjà obtenus
            if (isset($earnedIds[$badgeId])) continue;

            $unlocked = false;

            switch ($condType) {
                case 'xp_threshold':
                    $unlocked = ($userXp >= $condValue);
                    break;

                case 'mission_success':
                    $stmtP = $pdo->prepare(
                        "SELECT COUNT(*) FROM participations
                          WHERE user_id = :uid
                            AND status IN ('validated','auto_validated')"
                    );
                    $stmtP->execute([':uid' => $userId]);
                    $unlocked = ((int)$stmtP->fetchColumn() >= $condValue);
                    break;

                case 'rando_validated':
                    $stmtR = $pdo->prepare(
                        "SELECT COUNT(*) FROM rando_participations
                          WHERE user_id = :uid
                            AND status = 'validated'"
                    );
                    $stmtR->execute([':uid' => $userId]);
                    $unlocked = ((int)$stmtR->fetchColumn() >= $condValue);
                    break;

                case 'season':
                    if ($activeSeason) {
                        $stmtS = $pdo->prepare(
                            "SELECT COUNT(*) FROM participations
                              WHERE user_id = :uid
                                AND status IN ('validated','auto_validated')
                                AND season_id = :sid"
                        );
                        $stmtS->execute([':uid' => $userId, ':sid' => (int)$activeSeason['id']]);
                        $unlocked = ((int)$stmtS->fetchColumn() >= 1);
                    }
                    break;

                default:
                    // Type non géré → on ignore silencieusement
                    break;
            }

            if (!$unlocked) continue;

            // -- 5. Attribuer le badge (INSERT IGNORE pour éviter les doublons)
            $stmtIns = $pdo->prepare(
                "INSERT IGNORE INTO user_badges
                    (user_id, badge_id, source_type, awarded_by, awarded_at)
                 VALUES (:uid, :bid, 'auto', NULL, NOW())"
            );
            $stmtIns->execute([':uid' => $userId, ':bid' => $badgeId]);

            // Ne notifier que si une ligne a vraiment été insérée
            if ($stmtIns->rowCount() > 0) {
                $newlyAwarded[] = $badge;

                // -- 6. Notification in-app (non-bloquante)
                if (function_exists('push_notification')) {
                    push_notification(
                        $userId,
                        'badge_unlock',
                        'Badge débloqué : ' . $badge['title'],
                        [
                            'link_url'   => 'profil.php',
                            'icon_emoji' => $badge['icon_emoji'] ?? '🏅',
                        ]
                    );
                }
            }
        }

        return $newlyAwarded;

    } catch (Throwable $e) {
        // Entièrement silencieux — ne doit jamais interrompre la page appelante
        error_log('[ZONE85] check_and_award_badges : ' . $e->getMessage());
        return [];
    }
}
