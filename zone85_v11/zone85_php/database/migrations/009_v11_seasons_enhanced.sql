-- ============================================================
-- Migration 009 — V11 : Saisons enrichies + résultats
-- ============================================================

-- Enrichissement de la table seasons existante
ALTER TABLE seasons
    ADD COLUMN IF NOT EXISTS color_primary    VARCHAR(7)   NOT NULL DEFAULT '#0c1e2e' AFTER slug,
    ADD COLUMN IF NOT EXISTS color_secondary  VARCHAR(7)   NOT NULL DEFAULT '#ea5649' AFTER color_primary,
    ADD COLUMN IF NOT EXISTS emoji            VARCHAR(8)   NOT NULL DEFAULT '🏆'      AFTER color_secondary,
    ADD COLUMN IF NOT EXISTS description_long TEXT         NULL                       AFTER description,
    ADD COLUMN IF NOT EXISTS image_url        VARCHAR(255) NULL                       AFTER description_long,
    ADD COLUMN IF NOT EXISTS badge_reward_id  INT UNSIGNED NULL                       AFTER image_url,
    ADD COLUMN IF NOT EXISTS grande_mission_id INT UNSIGNED NULL                      AFTER badge_reward_id,
    ADD COLUMN IF NOT EXISTS start_date       DATE         NULL                       AFTER grande_mission_id,
    ADD COLUMN IF NOT EXISTS end_date         DATE         NULL                       AFTER start_date,
    ADD COLUMN IF NOT EXISTS closed_at        DATETIME     NULL                       AFTER end_date,
    ADD COLUMN IF NOT EXISTS winner_clan_id   INT UNSIGNED NULL                       AFTER closed_at;

-- Table des trophées de saison (résultats définitifs)
CREATE TABLE IF NOT EXISTS season_trophies (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season_id      INT UNSIGNED NOT NULL,
    winning_clan_id INT UNSIGNED NOT NULL,
    clan_score     INT UNSIGNED NOT NULL DEFAULT 0,
    runner_up_clan_id INT UNSIGNED NULL,
    runner_up_score   INT UNSIGNED NOT NULL DEFAULT 0,
    awarded_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes          TEXT         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_st_season (season_id),
    INDEX idx_st_clan (winning_clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table résultats de saison détaillés (un enregistrement par clan par saison)
CREATE TABLE IF NOT EXISTS season_clan_results (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season_id  INT UNSIGNED NOT NULL,
    clan_id    INT UNSIGNED NOT NULL,
    rank       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    final_score INT UNSIGNED NOT NULL DEFAULT 0,
    members_active SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    participations INT UNSIGNED NOT NULL DEFAULT 0,
    xp_total   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_scr (season_id, clan_id),
    INDEX idx_scr_season (season_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index sur seasons
ALTER TABLE seasons
    ADD INDEX IF NOT EXISTS idx_seasons_status (status),
    ADD INDEX IF NOT EXISTS idx_seasons_dates  (start_date, end_date);
