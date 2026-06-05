-- ============================================================
-- Migration 018 — V11 : articles.season_id
-- Rattache les Échos à une saison
-- ============================================================

ALTER TABLE `articles`
  ADD COLUMN `season_id` INT UNSIGNED NULL AFTER `rubrique`,
  ADD KEY `idx_articles_season` (`season_id`);
