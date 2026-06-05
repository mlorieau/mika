-- ============================================================
-- Migration 006 — Collectible mobile positioning (V9.1)
-- ============================================================
ALTER TABLE mission_collectibles
    ADD COLUMN IF NOT EXISTS position_top_mobile  DECIMAL(6,2) NULL AFTER position_left,
    ADD COLUMN IF NOT EXISTS position_left_mobile DECIMAL(6,2) NULL AFTER position_top_mobile,
    ADD COLUMN IF NOT EXISTS size_desktop          TINYINT UNSIGNED NOT NULL DEFAULT 48 AFTER position_left_mobile,
    ADD COLUMN IF NOT EXISTS size_mobile           TINYINT UNSIGNED NOT NULL DEFAULT 40 AFTER size_desktop;
