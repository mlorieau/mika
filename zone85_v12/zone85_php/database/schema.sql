-- ============================================================
-- ZONE85 — Schéma MySQL complet V13
-- Encodage : utf8mb4 | Moteur : InnoDB
-- Date : 2026-06-05
-- Consolide toutes les migrations 001 → 030
-- Usage : mysql -u zone85_user -p zone85 < database/schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS zone85
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE zone85;

-- ============================================================
-- 1. CLANS
-- ============================================================
CREATE TABLE IF NOT EXISTS clans (
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
-- 2. SEASONS (sans FK circulaire → games, ajoutée après)
-- ============================================================
CREATE TABLE IF NOT EXISTS seasons (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title              VARCHAR(200) NOT NULL,
  slug               VARCHAR(200) NOT NULL,
  description        TEXT,
  description_long   TEXT NULL,
  period_label       VARCHAR(100),
  start_date         DATE,
  end_date           DATE,
  closed_at          DATETIME NULL,
  status             ENUM('upcoming','active','archived') NOT NULL DEFAULT 'upcoming',
  theme_color        VARCHAR(20),
  color_primary      VARCHAR(20) NULL,
  color_secondary    VARCHAR(20) NULL,
  emoji              VARCHAR(20) NULL,
  image_url          VARCHAR(255) NULL,
  badge_reward_id    INT UNSIGNED NULL,
  grande_mission_id  INT UNSIGNED NULL,
  winner_clan_id     INT UNSIGNED NULL,
  main_game_id       INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seasons_slug (slug),
  KEY idx_seasons_status (status),
  KEY idx_seasons_dates  (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. GAMES
-- ============================================================
CREATE TABLE IF NOT EXISTS games (
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

-- 4. FK circulaire seasons → games
ALTER TABLE seasons
  ADD CONSTRAINT fk_seasons_main_game
  FOREIGN KEY (main_game_id) REFERENCES games(id) ON DELETE SET NULL;

-- ============================================================
-- 5. USERS (inclut colonnes migrations 007)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
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
  notif_missions        TINYINT(1) DEFAULT 1,
  notif_saisons         TINYINT(1) DEFAULT 1,
  notif_clan            TINYINT(1) DEFAULT 1,
  notif_push            TINYINT(1) DEFAULT 0,
  digest_hebdo          TINYINT(1) DEFAULT 0,
  accepted_cgu_at       DATETIME NULL,
  accepted_privacy_at   DATETIME NULL,
  email_verified_at     DATETIME NULL,
  email_verify_token    VARCHAR(64) NULL,
  role                  ENUM('member','moderator','admin') NOT NULL DEFAULT 'member',
  status                ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  last_login_at         DATETIME NULL,
  login_count           INT UNSIGNED DEFAULT 0,
  pwa_installed_at      DATETIME NULL,
  pwa_install_count     TINYINT UNSIGNED DEFAULT 0,
  delete_requested_at   DATETIME NULL,
  deleted_at            DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email  (email),
  UNIQUE KEY uq_users_pseudo (pseudo),
  KEY idx_users_status  (status),
  KEY idx_users_role    (role),
  KEY idx_users_deleted (deleted_at),
  KEY idx_users_login   (last_login_at),
  CONSTRAINT fk_users_clan FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. BADGES (inclut colonnes migration 011)
-- ============================================================
CREATE TABLE IF NOT EXISTS badges (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(100) NOT NULL,
  slug             VARCHAR(100) NOT NULL,
  description      TEXT,
  icon             VARCHAR(20),
  icon_emoji       VARCHAR(20) NULL,
  category         VARCHAR(50) NULL,
  rarity           ENUM('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common',
  is_hidden        TINYINT(1) DEFAULT 0,
  condition_type   ENUM('manual','xp_threshold','mission_success','season','special') NOT NULL DEFAULT 'manual',
  condition_value  INT NULL,
  unlock_condition VARCHAR(255) NULL,
  color_primary    VARCHAR(20) NULL,
  season_id        INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_badges_slug (slug),
  KEY idx_badges_cat    (category),
  KEY idx_badges_rar    (rarity),
  KEY idx_badges_season (season_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. MISSIONS (inclut colonnes migrations 010, 013)
-- ============================================================
CREATE TABLE IF NOT EXISTS missions (
  id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id                    INT UNSIGNED NULL,
  season_id                  INT UNSIGNED NULL,
  title                      VARCHAR(200) NOT NULL,
  slug                       VARCHAR(200) NOT NULL,
  mission_type               ENUM('seasonal_collective','quiz','vote','photo_challenge','keto_kole_tche',
                                  'rando','weather_mission','investigation','hidden_hunt','premium_game',
                                  'event_flash') NOT NULL,
  description                TEXT,
  instructions               TEXT,
  cover_image                VARCHAR(255) NULL,
  cover_emoji                VARCHAR(20) NULL,
  status                     ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
  is_collective              TINYINT(1) DEFAULT 0,
  is_flash                   TINYINT(1) DEFAULT 0,
  is_grande_mission          TINYINT(1) DEFAULT 0,
  grande_mission_season_id   INT UNSIGNED NULL,
  validation_mode            ENUM('auto','manual','hybrid') NOT NULL DEFAULT 'auto',
  requires_answer            TINYINT(1) DEFAULT 0,
  requires_upload            TINYINT(1) DEFAULT 0,
  requires_vote              TINYINT(1) DEFAULT 0,
  requires_code              TINYINT(1) DEFAULT 0,
  xp_participation           SMALLINT UNSIGNED DEFAULT 0,
  xp_success                 SMALLINT UNSIGNED DEFAULT 0,
  xp_multiplier              DECIMAL(3,1) DEFAULT 1.0,
  clan_points_participation  SMALLINT UNSIGNED DEFAULT 0,
  clan_points_success        SMALLINT UNSIGNED DEFAULT 0,
  badge_reward_id            INT UNSIGNED NULL,
  flash_badge_id             INT UNSIGNED NULL,
  start_date                 DATE NULL,
  end_date                   DATE NULL,
  flash_start_at             DATETIME NULL,
  flash_end_at               DATETIME NULL,
  display_in_hall            TINYINT(1) DEFAULT 0,
  created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_missions_slug (slug),
  KEY idx_missions_status        (status),
  KEY idx_missions_type          (mission_type),
  KEY idx_missions_flash         (is_flash, flash_start_at, flash_end_at),
  KEY idx_missions_grande        (is_grande_mission),
  KEY idx_missions_season_grande (grande_mission_season_id),
  CONSTRAINT fk_missions_game        FOREIGN KEY (game_id)               REFERENCES games(id)    ON DELETE SET NULL,
  CONSTRAINT fk_missions_season      FOREIGN KEY (season_id)             REFERENCES seasons(id)  ON DELETE SET NULL,
  CONSTRAINT fk_missions_badge       FOREIGN KEY (badge_reward_id)       REFERENCES badges(id)   ON DELETE SET NULL,
  CONSTRAINT fk_missions_flash_badge FOREIGN KEY (flash_badge_id)        REFERENCES badges(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. MISSION_OPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS mission_options (
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
CREATE TABLE IF NOT EXISTS media (
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
CREATE TABLE IF NOT EXISTS participations (
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
  KEY idx_part_status  (status),
  KEY idx_part_created (created_at),
  CONSTRAINT fk_part_user      FOREIGN KEY (user_id)            REFERENCES users(id)           ON DELETE CASCADE,
  CONSTRAINT fk_part_mission   FOREIGN KEY (mission_id)         REFERENCES missions(id)        ON DELETE CASCADE,
  CONSTRAINT fk_part_game      FOREIGN KEY (game_id)            REFERENCES games(id)           ON DELETE SET NULL,
  CONSTRAINT fk_part_media     FOREIGN KEY (uploaded_media_id)  REFERENCES media(id)           ON DELETE SET NULL,
  CONSTRAINT fk_part_option    FOREIGN KEY (selected_option_id) REFERENCES mission_options(id) ON DELETE SET NULL,
  CONSTRAINT fk_part_validator FOREIGN KEY (validated_by)       REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. USER_PROGRESS
-- ============================================================
CREATE TABLE IF NOT EXISTS user_progress (
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
CREATE TABLE IF NOT EXISTS user_badges (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  badge_id    INT UNSIGNED NOT NULL,
  source_type VARCHAR(50) NULL,
  source_id   INT UNSIGNED NULL,
  awarded_by  INT UNSIGNED NULL,
  awarded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_badges (user_id, badge_id),
  KEY idx_ub_badge (badge_id),
  CONSTRAINT fk_ub_user  FOREIGN KEY (user_id)    REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge FOREIGN KEY (badge_id)   REFERENCES badges(id) ON DELETE CASCADE,
  CONSTRAINT fk_ub_admin FOREIGN KEY (awarded_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. XP_LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS xp_logs (
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
CREATE TABLE IF NOT EXISTS clan_score_logs (
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
-- 15. SEASON_TROPHIES (inclut colonnes migration 009)
-- ============================================================
CREATE TABLE IF NOT EXISTS season_trophies (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id        INT UNSIGNED NOT NULL,
  winning_clan_id  INT UNSIGNED NOT NULL,
  runner_up_clan_id INT UNSIGNED NULL,
  title            VARCHAR(200),
  description      TEXT,
  score_final      INT UNSIGNED DEFAULT 0,
  clan_score       INT UNSIGNED DEFAULT 0,
  runner_up_score  INT UNSIGNED DEFAULT 0,
  trophy_image     VARCHAR(255) NULL,
  awarded_at       DATETIME NULL,
  notes            TEXT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_st_clan (winning_clan_id),
  CONSTRAINT fk_trophy_season    FOREIGN KEY (season_id)        REFERENCES seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_trophy_clan      FOREIGN KEY (winning_clan_id)  REFERENCES clans(id)   ON DELETE CASCADE,
  CONSTRAINT fk_trophy_runner_up FOREIGN KEY (runner_up_clan_id) REFERENCES clans(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. SEASON_CLAN_RESULTS (migration 009)
-- ============================================================
CREATE TABLE IF NOT EXISTS season_clan_results (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id          INT UNSIGNED NOT NULL,
  clan_id            INT UNSIGNED NOT NULL,
  rank               TINYINT UNSIGNED DEFAULT 0,
  final_score        INT UNSIGNED DEFAULT 0,
  members_active     SMALLINT UNSIGNED DEFAULT 0,
  participations     INT UNSIGNED DEFAULT 0,
  xp_total           INT UNSIGNED DEFAULT 0,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_scr_season (season_id, clan_id),
  CONSTRAINT fk_scr_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_scr_clan   FOREIGN KEY (clan_id)   REFERENCES clans(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. HALL_ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS hall_items (
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
-- 18. COMMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS comments (
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
-- 19. CONTACT_MESSAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
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
-- 20. LEGAL_ACCEPTANCES
-- ============================================================
CREATE TABLE IF NOT EXISTS legal_acceptances (
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
-- 21. MISSION_COLLECTIBLES (migrations 005 + 006)
-- ============================================================
CREATE TABLE IF NOT EXISTS mission_collectibles (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id           INT UNSIGNED NOT NULL,
  collectible_key      VARCHAR(100) NOT NULL,
  title                VARCHAR(200) NOT NULL,
  hint                 TEXT NULL,
  page_slug            VARCHAR(100) NOT NULL,
  page_url             VARCHAR(255) NULL,
  position_top         DECIMAL(6,3) DEFAULT 50.0,
  position_left        DECIMAL(6,3) DEFAULT 50.0,
  position_top_mobile  DECIMAL(6,3) NULL,
  position_left_mobile DECIMAL(6,3) NULL,
  size_desktop         TINYINT UNSIGNED DEFAULT 40,
  size_mobile          TINYINT UNSIGNED DEFAULT 32,
  object_image         VARCHAR(255) NULL,
  success_gif          VARCHAR(255) NULL,
  success_title        VARCHAR(200) NULL,
  success_message      TEXT NULL,
  sort_order           TINYINT UNSIGNED DEFAULT 0,
  is_active            TINYINT(1) DEFAULT 1,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_coll_mission_page (mission_id, page_slug),
  CONSTRAINT fk_coll_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 22. USER_COLLECTIBLES (migration 005)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_collectibles (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  mission_id     INT UNSIGNED NULL,
  collectible_id INT UNSIGNED NOT NULL,
  found_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_uc_user_mission (user_id, mission_id),
  CONSTRAINT fk_uc_user       FOREIGN KEY (user_id)        REFERENCES users(id)             ON DELETE CASCADE,
  CONSTRAINT fk_uc_mission    FOREIGN KEY (mission_id)     REFERENCES missions(id)          ON DELETE SET NULL,
  CONSTRAINT fk_uc_collectible FOREIGN KEY (collectible_id) REFERENCES mission_collectibles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. PWA_INSTALLS (migration 007)
-- ============================================================
CREATE TABLE IF NOT EXISTS pwa_installs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NULL,
  platform     VARCHAR(50) NULL,
  user_agent   VARCHAR(500) NULL,
  installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pwa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 24. EMAIL_QUEUE + EMAIL_TEMPLATES (migration 008)
-- ============================================================
CREATE TABLE IF NOT EXISTS email_queue (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NULL,
  to_email     VARCHAR(180) NOT NULL,
  to_name      VARCHAR(100) NULL,
  template_slug VARCHAR(100) NOT NULL,
  subject      VARCHAR(300) NOT NULL,
  variables    JSON NULL,
  status       ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts     TINYINT UNSIGNED DEFAULT 0,
  last_error   TEXT NULL,
  scheduled_at DATETIME NULL,
  sent_at      DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_eq_status  (status),
  KEY idx_eq_user    (user_id),
  KEY idx_eq_created (created_at),
  CONSTRAINT fk_eq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(100) NOT NULL,
  brevo_id    INT NULL,
  subject     VARCHAR(300) NOT NULL,
  description VARCHAR(500) NULL,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tpl_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 25. FLASH_PARTICIPATIONS (migration 010)
-- ============================================================
CREATE TABLE IF NOT EXISTS flash_participations (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  mission_id    INT UNSIGNED NOT NULL,
  xp_multiplier DECIMAL(3,1) DEFAULT 1.0,
  xp_base       SMALLINT UNSIGNED DEFAULT 0,
  xp_bonus      SMALLINT UNSIGNED DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fp_mission (mission_id, user_id),
  CONSTRAINT fk_fp_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_fp_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 26. COMMUNITY_FEED + WEATHER_POSTS (migration 012)
-- ============================================================
CREATE TABLE IF NOT EXISTS community_feed (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL,
  user_id    INT UNSIGNED NULL,
  clan_id    INT UNSIGNED NULL,
  mission_id INT UNSIGNED NULL,
  badge_id   INT UNSIGNED NULL,
  season_id  INT UNSIGNED NULL,
  title      VARCHAR(300) NOT NULL,
  body       TEXT NULL,
  icon_emoji VARCHAR(20) NULL,
  link_url   VARCHAR(500) NULL,
  is_pinned  TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cf_type    (event_type),
  KEY idx_cf_created (created_at),
  KEY idx_cf_user    (user_id),
  KEY idx_cf_pinned  (is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weather_posts (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone              VARCHAR(100) DEFAULT 'vendee',
  title             VARCHAR(200) NOT NULL,
  body              TEXT NULL,
  weather_icon      VARCHAR(20) NULL,
  temperature       TINYINT NULL,
  weather_condition VARCHAR(100) NULL,
  is_alert          TINYINT(1) DEFAULT 0,
  is_event          TINYINT(1) DEFAULT 0,
  mission_id        INT UNSIGNED NULL,
  published_at      DATETIME NULL,
  expires_at        DATETIME NULL,
  created_by        INT UNSIGNED NULL,
  KEY idx_wp_zone  (zone),
  KEY idx_wp_alert (is_alert, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 27. KTC LEGACY (migration 012 — conservées pour compatibilité)
-- ============================================================
CREATE TABLE IF NOT EXISTS ktc_questions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category    VARCHAR(100) NULL,
  question    TEXT NOT NULL,
  answer_a    VARCHAR(255) NOT NULL,
  answer_b    VARCHAR(255) NOT NULL,
  answer_c    VARCHAR(255) NOT NULL,
  answer_d    VARCHAR(255) NOT NULL,
  correct     CHAR(1) NOT NULL,
  explanation TEXT NULL,
  difficulty  ENUM('easy','medium','hard') DEFAULT 'medium',
  xp_reward   SMALLINT UNSIGNED DEFAULT 5,
  is_active   TINYINT(1) DEFAULT 1,
  season_id   INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ktc_cat  (category),
  KEY idx_ktc_diff (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ktc_answers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  given_answer CHAR(1) NOT NULL,
  is_correct  TINYINT(1) DEFAULT 0,
  xp_earned   SMALLINT UNSIGNED DEFAULT 0,
  answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ktca_user (user_id),
  KEY idx_ktca_q    (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 28. MISSION_RANDO_DATA + RANDO_COMPLETIONS (migration 013)
-- ============================================================
CREATE TABLE IF NOT EXISTS mission_rando_data (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id       INT UNSIGNED NOT NULL,
  distance_km      DECIMAL(6,2) NULL,
  elevation_m      SMALLINT UNSIGNED NULL,
  difficulty       ENUM('facile','moyen','difficile') DEFAULT 'moyen',
  duration_min     SMALLINT UNSIGNED NULL,
  region           VARCHAR(100) NULL,
  start_point      VARCHAR(255) NULL,
  gpx_url          VARCHAR(255) NULL,
  photo_required   TINYINT(1) DEFAULT 0,
  badge_rando_id   INT UNSIGNED NULL,
  description_trail TEXT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mrd_region (region),
  KEY idx_mrd_diff   (difficulty),
  CONSTRAINT fk_mrd_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rando_completions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  mission_id   INT UNSIGNED NOT NULL,
  photo_path   VARCHAR(255) NULL,
  comment      TEXT NULL,
  distance_km  DECIMAL(6,2) NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rc_mission (mission_id),
  KEY idx_rc_user    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 29. SETTINGS (migration 014)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  setting_type  ENUM('string','boolean','integer','json','secret') NOT NULL DEFAULT 'string',
  category      VARCHAR(50) DEFAULT 'general',
  label         VARCHAR(200) NULL,
  description   TEXT NULL,
  placeholder   VARCHAR(300) NULL,
  is_sensitive  TINYINT(1) DEFAULT 0,
  is_required   TINYINT(1) DEFAULT 0,
  sort_order    SMALLINT UNSIGNED DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 30. ARTICLES (migrations 016 + 018)
-- ============================================================
CREATE TABLE IF NOT EXISTS articles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(300) NOT NULL,
  slug         VARCHAR(300) NOT NULL,
  rubrique     ENUM('ovnis','deux-minutes','chez-nous','chemins','communaute','archives') NOT NULL DEFAULT 'communaute',
  season_id    INT UNSIGNED NULL,
  excerpt      TEXT NULL,
  body         MEDIUMTEXT NULL,
  cover_image  VARCHAR(255) NULL,
  author_name  VARCHAR(100) NULL,
  status       ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_articles_slug (slug),
  KEY idx_articles_status   (status),
  KEY idx_articles_rubrique (rubrique),
  KEY idx_articles_season   (season_id),
  KEY idx_articles_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 31. KTC ÉDITORIAL (migrations 017 + 022)
-- ============================================================
CREATE TABLE IF NOT EXISTS ktc_episodes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(200) NOT NULL,
  title            VARCHAR(300) NOT NULL,
  season_id        INT UNSIGNED NULL,
  object_name      VARCHAR(200) NOT NULL,
  object_hidden    VARCHAR(200) NULL,
  teaser_text      TEXT NULL,
  details_text     TEXT NULL,
  vote_question    VARCHAR(300) NULL,
  vote_choice_1    VARCHAR(200) NULL,
  vote_choice_2    VARCHAR(200) NULL,
  vote_choice_3    VARCHAR(200) NULL,
  vote_choice_4    VARCHAR(200) NULL,
  revelation_text  TEXT NULL,
  person_name      VARCHAR(200) NULL,
  person_title     VARCHAR(200) NULL,
  person_bio       TEXT NULL,
  person_photo     VARCHAR(255) NULL,
  date_week1       DATE NULL,
  date_week2       DATE NULL,
  date_week3       DATE NULL,
  date_revelation  DATE NULL,
  badge_reward_id  INT UNSIGNED NULL,
  xp_reward        SMALLINT UNSIGNED DEFAULT 10,
  status           ENUM('draft','week1','week2','week3','revealed','archived') NOT NULL DEFAULT 'draft',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ktce_slug (slug),
  KEY idx_ktce_status     (status),
  KEY idx_ktce_season     (season_id),
  KEY idx_ktce_revelation (date_revelation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ktc_episode_photos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  episode_id  INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  caption     VARCHAR(300) NULL,
  reveal_week TINYINT UNSIGNED DEFAULT 1,
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_kep_episode (episode_id),
  KEY idx_kep_week    (reveal_week),
  CONSTRAINT fk_kep_episode FOREIGN KEY (episode_id) REFERENCES ktc_episodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ktc_propositions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  episode_id   INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  proposition  TEXT NOT NULL,
  is_correct   TINYINT(1) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ktcp_user_ep (episode_id, user_id),
  KEY idx_ktcp_episode (episode_id),
  CONSTRAINT fk_ktcp_ep   FOREIGN KEY (episode_id) REFERENCES ktc_episodes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ktcp_user FOREIGN KEY (user_id)    REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ktc_votes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  episode_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  vote_choice VARCHAR(200) NOT NULL,
  voted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ktcv_user_ep (episode_id, user_id),
  KEY idx_ktcv_episode (episode_id),
  CONSTRAINT fk_ktcv_ep   FOREIGN KEY (episode_id) REFERENCES ktc_episodes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ktcv_user FOREIGN KEY (user_id)    REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 32. RANDOS (migrations 021 + 024 + 025 + 026)
-- ============================================================
CREATE TABLE IF NOT EXISTS randos (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id           INT UNSIGNED NULL,
  slug                 VARCHAR(200) NOT NULL,
  title                VARCHAR(300) NOT NULL,
  summary              VARCHAR(500) NULL,
  intro_text           TEXT NULL,
  description          MEDIUMTEXT NULL,
  why_text             TEXT NULL,
  cover_image          VARCHAR(255) NULL,
  secteur              ENUM('bocage','littoral','marais','centre') NOT NULL DEFAULT 'bocage',
  commune              VARCHAR(100) NULL,
  communes_json        JSON NULL,
  distance_km          DECIMAL(6,2) NULL,
  duration_min         SMALLINT UNSIGNED NULL,
  difficulty           ENUM('facile','moyen','difficile') NOT NULL DEFAULT 'moyen',
  start_point          VARCHAR(255) NULL,
  gps_lat              DECIMAL(10,7) NULL,
  gps_lng              DECIMAL(10,7) NULL,
  parking              TEXT NULL,
  accessibility        TEXT NULL,
  gpx_url              VARCHAR(255) NULL,
  gpx_file             VARCHAR(255) NULL,
  nature_score         TINYINT UNSIGNED DEFAULT 0,
  patrimoine_score     TINYINT UNSIGNED DEFAULT 0,
  famille_score        TINYINT UNSIGNED DEFAULT 0,
  photo_score          TINYINT UNSIGNED DEFAULT 0,
  recommended_seasons  VARCHAR(100) NULL,
  meta_title           VARCHAR(200) NULL,
  meta_description     VARCHAR(300) NULL,
  status               ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at         DATETIME NULL,
  season_id            INT UNSIGNED NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rando_slug (slug),
  KEY idx_rando_status     (status),
  KEY idx_rando_secteur    (secteur),
  KEY idx_rando_season     (season_id),
  KEY idx_rando_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rando_blocks (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rando_id   INT UNSIGNED NOT NULL,
  type       ENUM('text','image','tip','warning','gallery','gpx','quote','recit','treasure') NOT NULL DEFAULT 'text',
  content    MEDIUMTEXT NULL,
  sort_order TINYINT UNSIGNED DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rb_rando (rando_id),
  CONSTRAINT fk_rb_rando FOREIGN KEY (rando_id) REFERENCES randos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rando_participations (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rando_id     INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  comment      TEXT NULL,
  photo_path   VARCHAR(255) NULL,
  rating       TINYINT UNSIGNED NULL,
  proof_rating TINYINT UNSIGNED NULL,
  proof_review TEXT NULL,
  status       ENUM('stamped','pending_proof','validated','rejected') NOT NULL DEFAULT 'stamped',
  xp_awarded   SMALLINT UNSIGNED DEFAULT 0,
  validated_at DATETIME NULL,
  validated_by INT UNSIGNED NULL,
  admin_note   TEXT NULL,
  done_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rp_rando_user (rando_id, user_id),
  KEY idx_rp_rando  (rando_id),
  KEY idx_rp_user   (user_id),
  KEY idx_rp_status (status),
  KEY idx_rp_rating (proof_rating),
  CONSTRAINT fk_rp_rando     FOREIGN KEY (rando_id)     REFERENCES randos(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_user      FOREIGN KEY (user_id)      REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_rp_validator FOREIGN KEY (validated_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 33. MEDIA_FILES (migration 023)
-- ============================================================
CREATE TABLE IF NOT EXISTS media_files (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_path   VARCHAR(255) NOT NULL,
  filename    VARCHAR(255) NULL,
  mime_type   VARCHAR(50) NULL,
  file_size   INT UNSIGNED DEFAULT 0,
  width       SMALLINT UNSIGNED NULL,
  height      SMALLINT UNSIGNED NULL,
  alt_text    VARCHAR(255) NULL,
  source_type VARCHAR(50) NULL,
  source_id   INT UNSIGNED NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mf_source  (source_type, source_id),
  KEY idx_mf_type    (mime_type),
  KEY idx_mf_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 34. RANDO_COMMUNE_STAMPS (migration 026)
-- ============================================================
CREATE TABLE IF NOT EXISTS rando_commune_stamps (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  commune       VARCHAR(100) NOT NULL,
  first_rando_id INT UNSIGNED NULL,
  stamped_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rcs_user_commune (user_id, commune),
  KEY idx_rcs_commune (commune),
  KEY idx_rcs_user    (user_id),
  CONSTRAINT fk_rcs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 35. RATE_LIMITS (migration 029)
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

-- ============================================================
-- 36. PASSWORD_RESETS (V13 — nouveau)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  email      VARCHAR(180) NOT NULL,
  token      VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pr_token (token),
  KEY idx_pr_user  (user_id),
  KEY idx_pr_email (email),
  KEY idx_pr_exp   (expires_at),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 37. NOTIFICATIONS (V13 — nouveau, pour Bloc 3)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(50) NOT NULL,
  title      VARCHAR(200) NOT NULL,
  body       TEXT NULL,
  icon_emoji VARCHAR(20) NULL,
  link_url   VARCHAR(500) NULL,
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user_unread (user_id, read_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES DE RÉFÉRENCE
-- ============================================================

-- Badge de bienvenue (accordé à l'inscription)
INSERT IGNORE INTO badges (title, slug, description, icon, icon_emoji, category, rarity, condition_type)
VALUES ('Pionnier de la Zone', 'pionnier-zone',
        'Accordé à chaque membre lors de son inscription.', '🧭', '🧭', 'inscription', 'common', 'manual');

-- Templates email de base
INSERT IGNORE INTO email_templates (slug, subject, description) VALUES
  ('welcome',             'Bienvenue dans la Zone85 !',            'Email de bienvenue après inscription'),
  ('email_verification',  'Confirmez votre adresse email — Zone85','Confirmation email lors de l\'inscription'),
  ('password_reset',      'Réinitialisation de votre mot de passe', 'Lien de réinitialisation mot de passe'),
  ('badge_unlock',        'Nouveau badge débloqué !',              'Notification badge'),
  ('mission_new',         'Nouvelle mission disponible !',         'Alerte nouvelle mission'),
  ('mission_validated',   'Ta participation a été validée',        'Notification validation participation'),
  ('delete_requested',    'Demande de suppression de compte',      'Confirmation suppression compte');

SET FOREIGN_KEY_CHECKS = 1;
