-- ============================================================
-- ZONE85 V12.7 — Validation photo des randonnées
-- ============================================================

ALTER TABLE `rando_participations`
  ADD COLUMN IF NOT EXISTS `status` ENUM('stamped','pending','validated','rejected') NOT NULL DEFAULT 'stamped' AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `xp_awarded` TINYINT(1) NOT NULL DEFAULT 0 AFTER `rating`,
  ADD COLUMN IF NOT EXISTS `validated_at` DATETIME NULL AFTER `done_at`,
  ADD COLUMN IF NOT EXISTS `validated_by` INT UNSIGNED NULL AFTER `validated_at`,
  ADD COLUMN IF NOT EXISTS `admin_note` TEXT NULL AFTER `validated_by`;

UPDATE `rando_participations`
SET `status` = 'validated', `xp_awarded` = 1
WHERE (`status` IS NULL OR `status` = 'stamped')
  AND `photo_path` IS NOT NULL
  AND `photo_path` <> '';

CREATE INDEX IF NOT EXISTS `idx_rp_status` ON `rando_participations` (`status`);
