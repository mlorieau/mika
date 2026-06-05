-- ============================================================
-- Migration 021 -- V12 : Randonnées CMS premium
-- Table principale + blocs de contenu riches
-- ============================================================

-- Table rando (fiche éditoriale complète)
CREATE TABLE IF NOT EXISTS `randos` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mission_id`       INT UNSIGNED NULL,             -- lien optionnel vers missions
  `slug`             VARCHAR(255) NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `summary`          TEXT NULL,                     -- résumé court (liste + SEO)
  `cover_image`      VARCHAR(255) NULL,
  -- Localisation
  `secteur`          ENUM('bocage','littoral','marais','plaine') NOT NULL DEFAULT 'bocage',
  `commune`          VARCHAR(150) NULL,
  -- Parcours
  `distance_km`      DECIMAL(5,1) NULL,
  `duration_min`     INT UNSIGNED NULL,
  `difficulty`       ENUM('facile','moyen','difficile','expert') NOT NULL DEFAULT 'facile',
  `start_point`      VARCHAR(255) NULL,
  `gps_lat`          DECIMAL(10,7) NULL,
  `gps_lng`          DECIMAL(10,7) NULL,
  `parking`          TEXT NULL,
  `accessibility`    TEXT NULL,
  `gpx_url`          VARCHAR(255) NULL,
  -- SEO
  `meta_title`       VARCHAR(255) NULL,
  `meta_description` VARCHAR(500) NULL,
  -- Publication
  `status`           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at`     DATETIME NULL,
  `season_id`        INT UNSIGNED NULL,
  `created_by`       INT UNSIGNED NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rando_slug` (`slug`),
  KEY `idx_rando_status`    (`status`, `published_at`),
  KEY `idx_rando_secteur`   (`secteur`),
  KEY `idx_rando_season`    (`season_id`),
  KEY `idx_rando_difficulty`(`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocs de contenu (éditeur riche multi-types)
CREATE TABLE IF NOT EXISTS `rando_blocks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rando_id`   INT UNSIGNED NOT NULL,
  `type`       ENUM('text','image','gallery','quote','info','conseil','around','youtube','map')
               NOT NULL DEFAULT 'text',
  `content`    LONGTEXT NULL,    -- JSON : {heading, body, src, caption, url, ...}
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rb_rando` (`rando_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participation future (onglet Communauté — structure préparée)
CREATE TABLE IF NOT EXISTS `rando_participations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rando_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `comment`    TEXT NULL,
  `photo_path` VARCHAR(255) NULL,
  `rating`     TINYINT UNSIGNED NULL,   -- 1-5 étoiles (futur)
  `done_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rp` (`rando_id`, `user_id`),
  KEY `idx_rp_rando` (`rando_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
