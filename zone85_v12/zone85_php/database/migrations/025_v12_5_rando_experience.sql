-- ============================================================
-- Migration 025 -- V12.5 : Expérience Rando Zone85
-- Ambiances + nouveaux blocs éditoriaux + compatibilité rando
-- ============================================================

ALTER TABLE `randos`
  ADD COLUMN `nature_score`      TINYINT UNSIGNED NULL AFTER `difficulty`,
  ADD COLUMN `patrimoine_score`  TINYINT UNSIGNED NULL AFTER `nature_score`,
  ADD COLUMN `famille_score`     TINYINT UNSIGNED NULL AFTER `patrimoine_score`,
  ADD COLUMN `photo_score`       TINYINT UNSIGNED NULL AFTER `famille_score`;

ALTER TABLE `rando_blocks`
  MODIFY COLUMN `type` ENUM('text','image','gallery','quote','info','conseil','around','youtube','map','recit','treasure')
  NOT NULL DEFAULT 'text';

-- Participation rando : la table existe déjà en V12.
-- Elle sert à tamponner le Passeport Rando et à afficher les Zonautes passés par ici.
