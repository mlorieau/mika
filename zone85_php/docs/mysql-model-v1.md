# Zone 85 — Modèle MySQL v1

> Ce document décrit le schéma de base de données cible.  
> Les données mockées dans `includes/data.php` correspondent à ce modèle.

---

## Tables principales

### `users`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| pseudo | VARCHAR(50) UNIQUE | |
| email | VARCHAR(180) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt |
| clan_slug | ENUM('bocage','littoral','marais') | |
| level | TINYINT | calculé depuis xp_total |
| xp_total | INT DEFAULT 0 | XP à vie, jamais remis à 0 |
| avatar | VARCHAR(10) | emoji ou path |
| bio | TEXT | |
| joined_at | DATETIME | |
| is_active | TINYINT(1) DEFAULT 1 | |

### `seasons`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| title | VARCHAR(100) | |
| slug | VARCHAR(100) UNIQUE | |
| period | VARCHAR(80) | ex. "Juin à août" |
| start_date | DATE | |
| end_date | DATE | |
| status | ENUM('upcoming','active','archived') | |
| winner_clan | ENUM('bocage','littoral','marais') NULL | rempli à la fin |
| main_mission_id | INT NULL FK missions.id | |

### `missions`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| season_id | INT NULL FK seasons.id | NULL = mission permanente |
| game_id | INT NULL FK games.id | |
| title | VARCHAR(200) | |
| slug | VARCHAR(200) UNIQUE | |
| mission_type | ENUM('seasonal_collective','quiz','vote','photo_challenge','keto_kole_tche','rando','weather_mission','investigation','hidden_hunt','premium_game') | |
| description | TEXT | |
| status | ENUM('upcoming','active','archived') | |
| is_collective | TINYINT(1) DEFAULT 0 | |
| validation_mode | ENUM('auto','manual','hybrid') | |
| requires_answer | TINYINT(1) DEFAULT 0 | |
| requires_upload | TINYINT(1) DEFAULT 0 | |
| requires_vote | TINYINT(1) DEFAULT 0 | |
| xp_participation | SMALLINT DEFAULT 0 | |
| xp_success | SMALLINT DEFAULT 0 | |
| clan_points_participation | SMALLINT DEFAULT 0 | |
| clan_points_success | SMALLINT DEFAULT 0 | |
| badge_reward_id | INT NULL FK badges.id | |
| start_date | DATE NULL | |
| end_date | DATE NULL | |
| display_in_hall | TINYINT(1) DEFAULT 0 | |

### `participations`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| user_id | INT FK users.id | |
| mission_id | INT FK missions.id | |
| clan_slug | ENUM('bocage','littoral','marais') | snapshot au moment de la participation |
| status | ENUM('pending','validated','rejected') | |
| answer | TEXT NULL | |
| upload_path | VARCHAR(255) NULL | |
| xp_earned | SMALLINT DEFAULT 0 | |
| clan_points_earned | SMALLINT DEFAULT 0 | |
| created_at | DATETIME | |
| validated_at | DATETIME NULL | |

### `clan_scores`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| clan_slug | ENUM('bocage','littoral','marais') | |
| season_id | INT FK seasons.id | |
| score | INT DEFAULT 0 | remis à 0 chaque saison |
| UNIQUE KEY | (clan_slug, season_id) | |

### `badges`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| title | VARCHAR(100) | |
| icon | VARCHAR(10) | emoji |
| description | TEXT | |
| rarity | ENUM('common','uncommon','rare','epic','legendary') | |
| xp_threshold | INT NULL | si débloqué par XP |

### `user_badges`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| user_id | INT FK users.id | |
| badge_id | INT FK badges.id | |
| obtained_at | DATETIME | |
| UNIQUE KEY | (user_id, badge_id) | |

### `games`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| title | VARCHAR(200) | |
| slug | VARCHAR(200) UNIQUE | |
| game_type | ENUM('seasonal_collective','keto_kole_tche','hidden_hunt','premium_game') | |
| is_paid | TINYINT(1) DEFAULT 0 | |
| season_id | INT NULL FK seasons.id | |
| status | ENUM('upcoming','active','archived','coming_soon') | |

---

## Requêtes clés à remplacer (TODO)

```sql
-- Saison active
SELECT * FROM seasons WHERE status = 'active' LIMIT 1;

-- Score des clans pour la saison active
SELECT cs.clan_slug, cs.score
FROM clan_scores cs
JOIN seasons s ON s.id = cs.season_id
WHERE s.status = 'active';

-- Top membres d'un clan (saison en cours)
SELECT u.pseudo, u.xp_total,
       SUM(p.clan_points_earned) AS xp_season
FROM users u
JOIN participations p ON p.user_id = u.id
JOIN seasons s ON s.status = 'active'
WHERE u.clan_slug = :clan_slug
  AND p.status = 'validated'
GROUP BY u.id
ORDER BY xp_season DESC
LIMIT 6;

-- Top zonautes global (XP à vie)
SELECT pseudo, clan_slug, xp_total,
       RANK() OVER (ORDER BY xp_total DESC) AS rank
FROM users
WHERE is_active = 1
LIMIT 10;
```
