-- ZONE85 — Schéma MySQL v1
-- Encodage : utf8mb4 | Moteur : InnoDB
-- Date : 2026-05-28
-- Usage : mysql -u zone85_user -p zone85 < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Créer la base si elle n'existe pas
CREATE DATABASE IF NOT EXISTS zone85
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE zone85;

-- ============================================================
-- 1. CLANS
-- ============================================================
CREATE TABLE clans (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(100) NOT NULL,
  slug             VARCHAR(100) NOT NULL,
  description      TEXT,
  mascot_image     VARCHAR(255),
  color_primary    VARCHAR(20) DEFAULT '#12314e',
  color_secondary  VARCHAR(20) DEFAULT '#163756',
  motto            VARCHAR(255),
  cry              VARCHAR(255),
  is_active        TINYINT(1) DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_clans_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. SEASONS (sans FK main_game_id — ajoutée après games)
-- ============================================================
CREATE TABLE seasons (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(200) NOT NULL,
  slug             VARCHAR(200) NOT NULL,
  description      TEXT,
  period_label     VARCHAR(100),
  start_date       DATE,
  end_date         DATE,
  status           ENUM('upcoming','active','archived') NOT NULL DEFAULT 'upcoming',
  theme_color      VARCHAR(20),
  main_game_id     INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seasons_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. GAMES
-- ============================================================
CREATE TABLE games (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id        INT UNSIGNED NULL,
  title            VARCHAR(200) NOT NULL,
  slug             VARCHAR(200) NOT NULL,
  description      TEXT,
  game_type        ENUM('seasonal_event','evergreen','hidden_hunt','premium_game','editorial_game') NOT NULL,
  status           ENUM('draft','active','archived','coming_soon') NOT NULL DEFAULT 'draft',
  is_paid          TINYINT(1) DEFAULT 0,
  is_collective    TINYINT(1) DEFAULT 0,
  cover_image      VARCHAR(255),
  rules            TEXT,
  start_date       DATE NULL,
  end_date         DATE NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_games_slug (slug),
  CONSTRAINT fk_games_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. FK CIRCULAIRE : seasons.main_game_id → games
-- ============================================================
ALTER TABLE seasons
  ADD CONSTRAINT fk_seasons_main_game
  FOREIGN KEY (main_game_id) REFERENCES games(id) ON DELETE SET NULL;

-- ============================================================
-- 5. USERS
-- ============================================================
CREATE TABLE users (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email                 VARCHAR(180) NOT NULL,
  password_hash         VARCHAR(255) NOT NULL DEFAULT '',
  pseudo                VARCHAR(50) NOT NULL,
  first_name            VARCHAR(100),
  last_name             VARCHAR(100),
  clan_id               INT UNSIGNED NULL,
  avatar_type           ENUM('preset','generated','upload') NOT NULL DEFAULT 'preset',
  avatar_config         JSON NULL,
  avatar_file           VARCHAR(255) NULL,
  bio                   TEXT NULL,
  xp_total              INT UNSIGNED NOT NULL DEFAULT 0,
  level                 TINYINT UNSIGNED NOT NULL DEFAULT 1,
  newsletter_optin      TINYINT(1) DEFAULT 0,
  accepted_cgu_at       DATETIME NULL,
  accepted_privacy_at   DATETIME NULL,
  role                  ENUM('member','moderator','admin') NOT NULL DEFAULT 'member',
  status                ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  last_login_at         DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_pseudo (pseudo),
  CONSTRAINT fk_users_clan FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. BADGES
-- ============================================================
CREATE TABLE badges (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(100) NOT NULL,
  slug             VARCHAR(100) NOT NULL,
  description      TEXT,
  icon             VARCHAR(20),
  rarity           ENUM('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common',
  condition_type   ENUM('manual','xp_threshold','mission_success','season','special') NOT NULL DEFAULT 'manual',
  condition_value  INT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_badges_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. MISSIONS
-- ============================================================
CREATE TABLE missions (
  id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id                    INT UNSIGNED NULL,
  season_id                  INT UNSIGNED NULL,
  title                      VARCHAR(200) NOT NULL,
  slug                       VARCHAR(200) NOT NULL,
  mission_type               ENUM('seasonal_collective','quiz','vote','photo_challenge','keto_kole_tche','rando','weather_mission','investigation','hidden_hunt','premium_game') NOT NULL,
  description                TEXT,
  instructions               TEXT,
  status                     ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
  is_collective              TINYINT(1) DEFAULT 0,
  validation_mode            ENUM('auto','manual','hybrid') NOT NULL DEFAULT 'auto',
  requires_answer            TINYINT(1) DEFAULT 0,
  requires_upload            TINYINT(1) DEFAULT 0,
  requires_vote              TINYINT(1) DEFAULT 0,
  requires_code              TINYINT(1) DEFAULT 0,
  xp_participation           SMALLINT UNSIGNED DEFAULT 0,
  xp_success                 SMALLINT UNSIGNED DEFAULT 0,
  clan_points_participation  SMALLINT UNSIGNED DEFAULT 0,
  clan_points_success        SMALLINT UNSIGNED DEFAULT 0,
  badge_reward_id            INT UNSIGNED NULL,
  start_date                 DATE NULL,
  end_date                   DATE NULL,
  display_in_hall            TINYINT(1) DEFAULT 0,
  created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_missions_slug (slug),
  CONSTRAINT fk_missions_game   FOREIGN KEY (game_id)         REFERENCES games(id)   ON DELETE SET NULL,
  CONSTRAINT fk_missions_season FOREIGN KEY (season_id)       REFERENCES seasons(id) ON DELETE SET NULL,
  CONSTRAINT fk_missions_badge  FOREIGN KEY (badge_reward_id) REFERENCES badges(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. MISSION_OPTIONS
-- ============================================================
CREATE TABLE mission_options (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id  INT UNSIGNED NOT NULL,
  label       VARCHAR(255) NOT NULL,
  value       VARCHAR(255),
  is_correct  TINYINT(1) DEFAULT 0,
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mopts_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. MEDIA
-- ============================================================
CREATE TABLE media (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255),
  mime_type     VARCHAR(50),
  file_size     INT UNSIGNED DEFAULT 0,
  width         SMALLINT UNSIGNED NULL,
  height        SMALLINT UNSIGNED NULL,
  media_type    ENUM('avatar','mission_photo','hall','system') NOT NULL DEFAULT 'system',
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  alt_text      VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_media_user        (user_id),
  KEY idx_media_type_status (media_type, status),
  CONSTRAINT fk_media_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. PARTICIPATIONS
-- ============================================================
CREATE TABLE participations (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NOT NULL,
  mission_id          INT UNSIGNED NOT NULL,
  game_id             INT UNSIGNED NULL,
  answer_text         TEXT NULL,
  selected_option_id  INT UNSIGNED NULL,
  uploaded_media_id   INT UNSIGNED NULL,
  comment             TEXT NULL,
  status              ENUM('pending','validated','rejected','auto_validated') NOT NULL DEFAULT 'pending',
  is_success          TINYINT(1) DEFAULT 0,
  xp_awarded          SMALLINT UNSIGNED DEFAULT 0,
  clan_points_awarded SMALLINT UNSIGNED DEFAULT 0,
  validated_by        INT UNSIGNED NULL,
  validated_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_participations_user_mission (user_id, mission_id),
  KEY idx_part_mission (mission_id),
  KEY idx_part_game    (game_id),
  CONSTRAINT fk_part_user      FOREIGN KEY (user_id)           REFERENCES users(id)           ON DELETE CASCADE,
  CONSTRAINT fk_part_mission   FOREIGN KEY (mission_id)        REFERENCES missions(id)        ON DELETE CASCADE,
  CONSTRAINT fk_part_game      FOREIGN KEY (game_id)           REFERENCES games(id)           ON DELETE SET NULL,
  CONSTRAINT fk_part_media     FOREIGN KEY (uploaded_media_id) REFERENCES media(id)           ON DELETE SET NULL,
  CONSTRAINT fk_part_option    FOREIGN KEY (selected_option_id) REFERENCES mission_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_part_validator FOREIGN KEY (validated_by)      REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. USER_PROGRESS
-- ============================================================
CREATE TABLE user_progress (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  game_id        INT UNSIGNED NULL,
  mission_id     INT UNSIGNED NULL,
  progress_key   VARCHAR(100) NOT NULL,
  progress_value INT UNSIGNED DEFAULT 0,
  progress_max   INT UNSIGNED DEFAULT 1,
  completed      TINYINT(1) DEFAULT 0,
  completed_at   DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_progress_user_game_key (user_id, game_id, progress_key),
  CONSTRAINT fk_progress_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_progress_game    FOREIGN KEY (game_id)    REFERENCES games(id)    ON DELETE SET NULL,
  CONSTRAINT fk_progress_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. USER_BADGES
-- ============================================================
CREATE TABLE user_badges (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  badge_id    INT UNSIGNED NOT NULL,
  source_type VARCHAR(50) NULL,
  source_id   INT UNSIGNED NULL,
  awarded_by  INT UNSIGNED NULL,
  awarded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_badges (user_id, badge_id),
  KEY idx_ub_badge (badge_id),
  CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)   REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id)  REFERENCES badges(id) ON DELETE CASCADE,
  CONSTRAINT fk_ub_admin FOREIGN KEY (awarded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. XP_LOGS
-- ============================================================
CREATE TABLE xp_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  source_type VARCHAR(50) NOT NULL,
  source_id   INT UNSIGNED NULL,
  xp_amount   SMALLINT NOT NULL,
  reason      VARCHAR(100),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_xp_user    (user_id),
  KEY idx_xp_created (created_at),
  CONSTRAINT fk_xp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. CLAN_SCORE_LOGS
-- ============================================================
CREATE TABLE clan_score_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clan_id     INT UNSIGNED NOT NULL,
  season_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  source_type VARCHAR(50) NOT NULL,
  source_id   INT UNSIGNED NULL,
  points      SMALLINT NOT NULL,
  reason      VARCHAR(100),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_csl_clan_season (clan_id, season_id),
  KEY idx_csl_created     (created_at),
  CONSTRAINT fk_csl_clan   FOREIGN KEY (clan_id)   REFERENCES clans(id)   ON DELETE CASCADE,
  CONSTRAINT fk_csl_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_csl_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. SEASON_TROPHIES
-- ============================================================
CREATE TABLE season_trophies (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id       INT UNSIGNED NOT NULL,
  winning_clan_id INT UNSIGNED NOT NULL,
  title           VARCHAR(200),
  description     TEXT,
  score_final     INT UNSIGNED DEFAULT 0,
  trophy_image    VARCHAR(255) NULL,
  awarded_at      DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trophy_season FOREIGN KEY (season_id)       REFERENCES seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_trophy_clan   FOREIGN KEY (winning_clan_id) REFERENCES clans(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. HALL_ITEMS
-- ============================================================
CREATE TABLE hall_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  slug         VARCHAR(200) NOT NULL,
  item_type    ENUM('photo','contribution','keto','rando','trophy','member','archive') NOT NULL DEFAULT 'photo',
  description  TEXT,
  user_id      INT UNSIGNED NULL,
  clan_id      INT UNSIGNED NULL,
  mission_id   INT UNSIGNED NULL,
  game_id      INT UNSIGNED NULL,
  media_id     INT UNSIGNED NULL,
  is_featured  TINYINT(1) DEFAULT 0,
  published_at DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hall_slug (slug),
  KEY idx_hall_type_pub (item_type, published_at),
  CONSTRAINT fk_hall_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_hall_clan    FOREIGN KEY (clan_id)    REFERENCES clans(id)    ON DELETE SET NULL,
  CONSTRAINT fk_hall_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE SET NULL,
  CONSTRAINT fk_hall_game    FOREIGN KEY (game_id)    REFERENCES games(id)    ON DELETE SET NULL,
  CONSTRAINT fk_hall_media   FOREIGN KEY (media_id)   REFERENCES media(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. COMMENTS
-- ============================================================
CREATE TABLE comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  mission_id INT UNSIGNED NULL,
  game_id    INT UNSIGNED NULL,
  parent_id  INT UNSIGNED NULL,
  content    TEXT NOT NULL,
  status     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_comments_mission (mission_id, status),
  CONSTRAINT fk_comments_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_comments_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE SET NULL,
  CONSTRAINT fk_comments_game    FOREIGN KEY (game_id)    REFERENCES games(id)    ON DELETE SET NULL,
  CONSTRAINT fk_comments_parent  FOREIGN KEY (parent_id)  REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. CONTACT_MESSAGES
-- ============================================================
CREATE TABLE contact_messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(180) NOT NULL,
  subject         VARCHAR(200),
  reason          VARCHAR(100),
  message         TEXT NOT NULL,
  status          ENUM('new','read','archived') NOT NULL DEFAULT 'new',
  ip_hash         VARCHAR(64) NULL,
  user_agent_hash VARCHAR(64) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. LEGAL_ACCEPTANCES
-- ============================================================
CREATE TABLE legal_acceptances (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  document_type    ENUM('cgu','privacy','cookies') NOT NULL,
  document_version VARCHAR(20) NOT NULL DEFAULT '1.0',
  accepted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash          VARCHAR(64) NULL,
  KEY idx_la_user (user_id),
  CONSTRAINT fk_la_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Rate limiting (anti brute-force login/inscription)
-- ============================================================
CREATE TABLE IF NOT EXISTS rate_limits (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  ip_hash      VARCHAR(64)       NOT NULL,
  endpoint     VARCHAR(60)       NOT NULL,
  attempts     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  window_start DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rl_ip_endpoint (ip_hash, endpoint),
  KEY idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
