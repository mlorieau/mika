-- Migration 025 — V13 : KTC winner tracking + confidence level
ALTER TABLE `ktc_episodes`
  ADD COLUMN `winner_proposition_id` INT UNSIGNED NULL AFTER `revelation_text`,
  ADD COLUMN `answer_confidence` ENUM('certain','probable','estimation') NOT NULL DEFAULT 'certain' AFTER `winner_proposition_id`;
