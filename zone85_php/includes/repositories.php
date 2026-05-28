<?php
// ============================================================
// ZONE 85 — Repositories (lecture données)
// Retourne null si DB non disponible → fallback sur data.php
// ============================================================

if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
}

// ── Helper interne ────────────────────────────────────────────

/**
 * Retourne l'id et la start_date de la saison active (singleton).
 */
function _active_season_row(): ?array {
    static $_cache = false; // false = non encore tenté
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

// ── Clans ─────────────────────────────────────────────────────

/**
 * Retourne les 3 clans indexés par slug, avec tous les champs attendus par clans.php / classement.php.
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
            'bocage'   => ['bocage-chip',   'bocage-chip-sm',   'bocage-text',   '🌳 Bocage'],
            'littoral' => ['littoral-chip', 'littoral-chip-sm', 'littoral-text', '⚓ Littoral'],
            'marais'   => ['marais-chip',   'marais-chip-sm',   'marais-text',   '🌿 Marais'],
        ];

        $result = [];
        foreach ($rows as $i => $row) {
            $slug       = $row['slug'];
            $cm         = $chip_map[$slug] ?? ['', '', '', ''];
            $score      = (int)$row['season_score'];
            $raceWidth  = ($maxScore > 0) ? (int)round(($score / $maxScore) * 94) : 0;
            $podiumRank = $i + 1; // trié par score DESC → i=0 est le leader

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

// ── Saisons ───────────────────────────────────────────────────

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

// ── Missions ──────────────────────────────────────────────────

/**
 * Retourne les missions actives avec race_progress calculé.
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

// ── Badges ────────────────────────────────────────────────────

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
            $r['obtained']     = false; // nécessite une session utilisateur
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

// ── Membres ───────────────────────────────────────────────────

/**
 * Retourne le top membres global (classement par XP à vie).
 * Compatible avec $top_zonautes de data.php.
 * Le rang est calculé côté PHP — compatible MySQL 8+ et MariaDB.
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
 * Retourne [] (tableau vide) en cas d'échec pour ne pas casser les foreach.
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

// ── Trophées ──────────────────────────────────────────────────

/**
 * Retourne les trophées des saisons archivées.
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
            $r['medal']         = '🥇';
            $r['main_mission']  = $r['main_mission'] ?? '';
            $r['contributions'] = (int)$r['contributions'];
        }
        return $rows;
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_trophies : ' . $e->getMessage());
        return null;
    }
}

// ── Hall ──────────────────────────────────────────────────────

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
            'bocage'   => '🌳 Bocage',
            'littoral' => '⚓ Littoral',
            'marais'   => '🌿 Marais',
        ];
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

// ── Profil utilisateur ────────────────────────────────────────

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
        $s4 = $pdo->prepare("SELECT COALESCE(SUM(xp_amount), 0) FROM xp_logs WHERE user_id = :id AND created_at >= :start");
        $s4->execute([':id' => $userId, ':start' => $seasonStart]);
        $xp_season = (int)$s4->fetchColumn();

        return [
            'id'             => (int)$row['id'],
            'pseudo'         => $row['pseudo'],
            'prenom'         => $row['first_name'] ?? '',
            'nom'            => $row['last_name'] ?? '',
            'clan_slug'      => $row['clan_slug'] ?? '',
            'level'          => (int)$row['level'],
            'xp_total'       => (int)$row['xp_total'],
            'xp_this_season' => $xp_season,
            'avatar'         => '🧭',
            'bio'            => $row['bio'] ?? '',
            'badges_count'   => $badges_count,
            'missions_done'  => $missions_done,
            'rank_in_clan'   => 0, // calculé périodiquement via cron
            'rank_total'     => 0,
            'joined'         => $row['created_at'] ? substr($row['created_at'], 0, 10) : '',
        ];
    } catch (PDOException $e) {
        error_log('[ZONE85] fetch_user_profile : ' . $e->getMessage());
        return null;
    }
}
