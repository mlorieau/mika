-- ============================================================
-- Migration 022 -- V12 : Ajout colonnes vote (semaine 3) sur ktc_episodes
-- Les 4 propositions de vote gérées par l'admin
-- ============================================================

ALTER TABLE `ktc_episodes`
  ADD COLUMN `vote_choice_1` VARCHAR(255) NULL AFTER `vote_question`,
  ADD COLUMN `vote_choice_2` VARCHAR(255) NULL AFTER `vote_choice_1`,
  ADD COLUMN `vote_choice_3` VARCHAR(255) NULL AFTER `vote_choice_2`,
  ADD COLUMN `vote_choice_4` VARCHAR(255) NULL AFTER `vote_choice_3`;

-- Vérif :
-- SHOW COLUMNS FROM ktc_episodes LIKE 'vote_choice%';
