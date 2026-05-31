-- ============================================================
-- Migration 026 -- V12.6 : Couche émotionnelle Randos
-- Pourquoi cette rando, saisons conseillées, multi-communes,
-- tampons communes Passeport
-- ============================================================

ALTER TABLE `randos`
  ADD COLUMN `why_text` TEXT NULL AFTER `description`,
  ADD COLUMN `communes_json` JSON NULL AFTER `commune`,
  ADD COLUMN `recommended_seasons` JSON NULL AFTER `photo_score`;

CREATE TABLE IF NOT EXISTS `rando_commune_stamps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `commune` VARCHAR(150) NOT NULL,
  `first_rando_id` INT UNSIGNED NULL,
  `stamped_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_commune` (`user_id`, `commune`),
  KEY `idx_commune` (`commune`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
