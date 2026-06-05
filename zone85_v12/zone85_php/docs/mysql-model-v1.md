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

### `user_progress`
| Colonne | Type | Notes |
|---|---|---|
| user_id | INT FK users.id | |
| level | TINYINT | calculé |
| xp_total | INT | dupliqué pour perf |
| xp_this_season | INT | remis à 0 chaque saison |
| missions_done | INT | total |
| rank_total | INT | calculé périodiquement |
| rank_in_clan | INT | calculé périodiquement |
| updated_at | DATETIME | |

### `xp_logs`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| user_id | INT FK users.id | |
| participation_id | INT NULL FK participations.id | |
| xp_delta | SMALLINT | positif ou négatif |
| reason | VARCHAR(100) | ex: 'quiz_success', 'photo_approved' |
| created_at | DATETIME | |

### `clan_score_logs`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| clan_slug | ENUM('bocage','littoral','marais') | |
| season_id | INT FK seasons.id | |
| delta | SMALLINT | |
| reason | VARCHAR(100) | |
| user_id | INT NULL FK users.id | |
| created_at | DATETIME | |

### `media`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| user_id | INT FK users.id | |
| mission_id | INT NULL FK missions.id | |
| filename | VARCHAR(255) | nom final (jamais le nom original) |
| original_name | VARCHAR(255) | stocké pour modération seulement |
| mime_type | VARCHAR(50) | |
| size_bytes | INT | |
| width | SMALLINT NULL | |
| height | SMALLINT NULL | |
| status | ENUM('pending','approved','rejected') | |
| display_in_hall | TINYINT(1) DEFAULT 0 | |
| created_at | DATETIME | |

### `comments`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| user_id | INT FK users.id | |
| parent_type | VARCHAR(50) | 'mission', 'media', 'rando' |
| parent_id | INT | |
| body | TEXT | |
| status | ENUM('pending','approved','rejected') | |
| created_at | DATETIME | |

### `contact_messages`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| name | VARCHAR(100) | |
| email | VARCHAR(180) | |
| subject | VARCHAR(200) | |
| body | TEXT | |
| ip_address | VARCHAR(45) | |
| status | ENUM('new','read','replied','spam') DEFAULT 'new' | |
| created_at | DATETIME | |

### `season_trophies`
| Colonne | Type | Notes |
|---|---|---|
| id | INT PK AUTO | |
| season_id | INT FK seasons.id | |
| winner_clan_slug | ENUM('bocage','littoral','marais') | |
| medal | VARCHAR(5) | ex: '🥇' |
| contributions | INT | |
| main_mission | VARCHAR(200) | |
| archived_at | DATETIME | |

---

## Moteur de jeu : Game → Mission → Participation → Reward → Progression

### Diagramme des relations

```
games (type de jeu)
  └── missions (liées à une saison et/ou un game)
        └── participations (un utilisateur tente une mission)
              ├── xp_logs (XP accordés à l'utilisateur)
              ├── clan_score_logs (points accordés au clan)
              └── media (upload lié à la participation, si required)

users
  ├── participations (historique de toutes les tentatives)
  ├── user_badges (badges obtenus)
  └── user_progress (snapshot de progression, mis à jour périodiquement)

seasons
  ├── missions (missions rattachées à la saison)
  ├── clan_scores (score courant des clans pour cette saison)
  └── season_trophies (résultat final archivé)
```

### Flux complet d'une participation

```
1. L'utilisateur soumet une participation (POST /participer.php)
   → Vérifications serveur :
     - mission en statut 'active' et non expirée
     - utilisateur actif (is_active = 1) et dans un clan
     - pas de participation existante (UNIQUE KEY user_id + mission_id)
     - CSRF valide

2. La participation est créée en BDD avec statut 'pending' ou 'validated'
   selon validation_mode de la mission :
     - 'auto'   → validée immédiatement
     - 'manual' → en attente d'un modérateur
     - 'hybrid' → auto si réponse correcte, sinon manual

3. Si validée (immédiatement ou par modérateur) :
   a. xp_earned = missions.xp_success (ou xp_participation si partiel)
   b. clan_points_earned = missions.clan_points_success
   c. INSERT dans xp_logs (user_id, participation_id, xp_delta, reason)
   d. INSERT dans clan_score_logs (clan_slug, season_id, delta, reason, user_id)
   e. UPDATE users SET xp_total = xp_total + xp_earned
   f. UPDATE clan_scores SET score = score + clan_points_earned
   g. UPDATE user_progress (xp_total, xp_this_season, missions_done)
   h. Si badge_reward_id → INSERT dans user_badges (si pas déjà obtenu)

4. Les rangs (rank_total, rank_in_clan) sont recalculés périodiquement
   via une tâche CRON ou au chargement du classement.
```

### Règles métier importantes

- **XP à vie** : `users.xp_total` n'est jamais remis à zéro. Il détermine le niveau global.
- **XP de saison** : `user_progress.xp_this_season` est remis à zéro au début de chaque saison.
- **Points de clan** : `clan_scores.score` est remis à zéro chaque saison. Le clan gagnant est déterminé à `seasons.end_date`.
- **Immutabilité des logs** : `xp_logs` et `clan_score_logs` ne sont jamais mis à jour, seulement insérés. Toute correction passe par un delta négatif avec reason='correction_admin'.

---

## Les Invisibles (futur jeu premium)

Zone 85 prévoit un jeu narratif premium intitulé **Les Invisibles**, intégré à la table `games` existante.

### Structure dans la table `games`

```sql
-- Exemple d'entrée pour Les Invisibles
INSERT INTO games (title, slug, game_type, is_paid, season_id, status)
VALUES ('Les Invisibles', 'les-invisibles', 'premium_game', 1, NULL, 'coming_soon');
```

### Champs additionnels prévus (migration future)

| Colonne | Type | Notes |
|---|---|---|
| chapters | TINYINT | Nombre total de chapitres (ex: 10) |
| free_chapters | TINYINT | Chapitres gratuits (ex: 1) |
| price_per_chapter | DECIMAL(5,2) NULL | Prix unitaire si achat à l'unité |
| price_full | DECIMAL(5,2) NULL | Prix accès complet |

### Fonctionnement

- **Chapitre 1 gratuit** : accessible à tout Zonaute inscrit
- **Chapitres 2 à 10 payants** : débloqués après paiement (Stripe ou équivalent)
- **Progression sauvegardée** : dans `user_progress` avec un champ JSON `game_data` (futur)
- **Impact clan minimal** : 5 points de clan par chapitre complété (vs 20-50 pour les missions collectives)
- **Badges spéciaux** : série de badges dédiés ("Invisible Niveau 1" → "Maître des Invisibles")
- **Missions liées** : chaque chapitre peut contenir des missions de type `premium_game` dans la table `missions`

### Intégration dans le flux existant

```
games (Les Invisibles, game_type='premium_game', is_paid=1)
  └── missions (chapter_1, chapter_2, …, chapter_10)
        └── participations (accès vérifié avant soumission)
              ├── xp_logs (XP réduits vs missions collectives)
              └── clan_score_logs (impact minimal)
```

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
