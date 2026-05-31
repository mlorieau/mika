-- ============================================================
-- ZONE85 V12.9 — Randos : avis + note sur demande XP
-- ============================================================

ALTER TABLE `rando_participations`
  ADD COLUMN IF NOT EXISTS `proof_rating` TINYINT UNSIGNED NULL AFTER `photo_path`,
  ADD COLUMN IF NOT EXISTS `proof_review` TEXT NULL AFTER `proof_rating`;

CREATE INDEX IF NOT EXISTS `idx_rp_rating` ON `rando_participations` (`proof_rating`);
