-- ============================================================
-- Migration 010 — V11 : Événements Flash
-- ============================================================

-- Ajout colonnes flash sur missions existante
ALTER TABLE missions
    ADD COLUMN IF NOT EXISTS flash_start_at  DATETIME     NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS flash_end_at    DATETIME     NULL AFTER flash_start_at,
    ADD COLUMN IF NOT EXISTS xp_multiplier   DECIMAL(3,1) NOT NULL DEFAULT 1.0 AFTER flash_end_at,
    ADD COLUMN IF NOT EXISTS is_flash        TINYINT(1)   NOT NULL DEFAULT 0   AFTER xp_multiplier,
    ADD COLUMN IF NOT EXISTS flash_badge_id  INT UNSIGNED NULL                 AFTER is_flash,
    ADD COLUMN IF NOT EXISTS cover_emoji     VARCHAR(8)   NOT NULL DEFAULT '🎯' AFTER flash_badge_id;

-- Index pour récupérer rapidement les flash actifs
ALTER TABLE missions
    ADD INDEX IF NOT EXISTS idx_missions_flash (is_flash, flash_end_at, status);

-- Journalisation des participations flash (pour double XP, etc.)
CREATE TABLE IF NOT EXISTS flash_participations (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    mission_id     INT UNSIGNED NOT NULL,
    xp_multiplier  DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    xp_base        INT UNSIGNED NOT NULL DEFAULT 0,
    xp_bonus       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fp (user_id, mission_id),
    INDEX idx_fp_mission (mission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed : exemples de flash events (désactivés par défaut)
-- INSERT INTO missions (title, mission_type, validation_type, status, is_flash, xp_multiplier, cover_emoji, ...)
-- À créer depuis l'admin avec type = event_flash
