-- ============================================================
-- Migration 024 -- V12.2 : Champs randos manquants
-- ============================================================

ALTER TABLE `randos`
  -- Introduction courte (accroche en haut de fiche)
  ADD COLUMN `intro_text`   TEXT NULL AFTER `summary`,
  -- Description longue (corps principal)
  ADD COLUMN `description`  LONGTEXT NULL AFTER `intro_text`,
  -- GPX uploadé (chemin relatif, ex: uploads/randos/gpx/trail.gpx)
  ADD COLUMN `gpx_file`     VARCHAR(255) NULL AFTER `gpx_url`;

-- Vérif :
-- SHOW COLUMNS FROM randos LIKE 'intro_text';
-- SHOW COLUMNS FROM randos LIKE 'gpx_file';
