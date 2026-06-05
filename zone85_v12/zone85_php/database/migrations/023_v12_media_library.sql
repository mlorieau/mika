-- ============================================================
-- Migration 023 -- V12 : Médiathèque centralisée (fondation légère)
-- Évite les uploads éparpillés dans chaque module
-- ============================================================

CREATE TABLE IF NOT EXISTS `media_files` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_path`   VARCHAR(255) NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `mime_type`   VARCHAR(50) NOT NULL DEFAULT 'image/jpeg',
  `file_size`   INT UNSIGNED NOT NULL DEFAULT 0,
  `width`       SMALLINT UNSIGNED NULL,
  `height`      SMALLINT UNSIGNED NULL,
  `alt_text`    VARCHAR(255) NULL,
  -- Provenance du fichier
  `source_type` ENUM('rando','ktc','article','mission','avatar','system')
                NOT NULL DEFAULT 'system',
  `source_id`   INT UNSIGNED NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mf_source` (`source_type`, `source_id`),
  KEY `idx_mf_type`   (`mime_type`),
  KEY `idx_mf_created`(`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Décision V12 : médiathèque légère (table + upload centralisé)
-- Pas d'UI complexe en V12 — les uploads se font via les formulaires existants
-- L'UI médiathèque (recherche, réutilisation) est planifiée en V13
