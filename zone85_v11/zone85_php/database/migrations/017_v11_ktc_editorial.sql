-- ============================================================
-- Migration 017 — V11 : KTC éditorial (rencontre mensuelle)
-- Remplace la logique "banque de questions" par un mini-CMS
-- ============================================================

-- Épisode mensuel : 1 objet + 1 personne + 4 phases
CREATE TABLE IF NOT EXISTS `ktc_episodes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`            VARCHAR(255) NOT NULL,
  `title`           VARCHAR(255) NOT NULL,       -- "Le mystère de novembre 2025"
  `season_id`       INT UNSIGNED NULL,
  -- ── L'objet ──────────────────────────────────────────────
  `object_name`     VARCHAR(255) NULL,            -- révélé en semaine 4 seulement
  `object_hidden`   TINYINT(1) NOT NULL DEFAULT 1,
  `teaser_text`     TEXT NULL,                    -- semaine 1 : découverte
  `details_text`    TEXT NULL,                    -- semaine 2 : nouveaux détails
  `vote_question`   VARCHAR(500) NULL,            -- semaine 3 : "Selon vous, c'est..."
  `revelation_text` LONGTEXT NULL,                -- semaine 4 : histoire complète
  -- ── La personne (brocanteur, antiquaire, collectionneur) ──
  `person_name`     VARCHAR(150) NULL,
  `person_title`    VARCHAR(200) NULL,            -- "Brocanteur à Fontenay-le-Comte"
  `person_bio`      TEXT NULL,
  `person_photo`    VARCHAR(255) NULL,
  -- ── Calendrier des 4 phases ───────────────────────────────
  `date_week1`      DATE NULL,                    -- début semaine 1
  `date_week2`      DATE NULL,
  `date_week3`      DATE NULL,
  `date_revelation` DATE NULL,                    -- semaine 4
  -- ── Récompenses ──────────────────────────────────────────
  `badge_reward_id` INT UNSIGNED NULL,
  `xp_reward`       SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  -- ── Statut ────────────────────────────────────────────────
  `status`          ENUM('draft','week1','week2','week3','revealed','archived')
                    NOT NULL DEFAULT 'draft',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ktce_slug`   (`slug`),
  KEY `idx_ktce_status`       (`status`),
  KEY `idx_ktce_season`       (`season_id`),
  KEY `idx_ktce_revelation`   (`date_revelation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos de l'épisode, dévoilées par semaine
CREATE TABLE IF NOT EXISTS `ktc_episode_photos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `episode_id`  INT UNSIGNED NOT NULL,
  `file_path`   VARCHAR(255) NOT NULL,
  `caption`     VARCHAR(255) NULL,
  `reveal_week` TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- visible à partir de quelle semaine
  `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kep_episode` (`episode_id`),
  KEY `idx_kep_week`    (`episode_id`, `reveal_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Propositions des membres (semaine 1-2)
CREATE TABLE IF NOT EXISTS `ktc_propositions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `episode_id`   INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `proposition`  TEXT NOT NULL,
  `is_correct`   TINYINT(1) NOT NULL DEFAULT 0,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ktcp` (`user_id`, `episode_id`),
  KEY `idx_ktcp_episode` (`episode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votes semaine 3
CREATE TABLE IF NOT EXISTS `ktc_votes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `episode_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `vote_choice` VARCHAR(255) NOT NULL,
  `voted_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ktcv` (`user_id`, `episode_id`),
  KEY `idx_ktcv_episode` (`episode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note : la table ktc_questions (banque de questions) reste en base
-- mais n'est plus la source principale du module KTC.
-- Elle pourra être supprimée en V13 si non utilisée.
