-- ============================================================
-- Migration 013 — V11 : Randonnées Zone85
-- ============================================================

-- Données spécifiques aux missions de type rando
CREATE TABLE IF NOT EXISTS mission_rando_data (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mission_id      INT UNSIGNED NOT NULL,
    distance_km     DECIMAL(5,1) NULL,
    elevation_m     SMALLINT     NULL,
    difficulty      ENUM('facile','moyen','difficile','expert') NOT NULL DEFAULT 'facile',
    duration_min    SMALLINT UNSIGNED NULL,
    region          VARCHAR(64)  NULL,   -- 'bocage', 'littoral', 'marais', etc.
    start_point     VARCHAR(255) NULL,   -- lieu de départ
    gpx_url         VARCHAR(255) NULL,   -- lien vers le fichier GPX
    photo_required  TINYINT(1)   NOT NULL DEFAULT 0,
    badge_rando_id  INT UNSIGNED NULL,
    description_trail TEXT       NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mrd_mission (mission_id),
    INDEX idx_mrd_region (region),
    INDEX idx_mrd_diff   (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Validations rando spécifiques (photo optionnelle, localisation)
CREATE TABLE IF NOT EXISTS rando_completions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    mission_id  INT UNSIGNED NOT NULL,
    photo_path  VARCHAR(255) NULL,
    comment     VARCHAR(512) NULL,
    distance_km DECIMAL(5,1) NULL,    -- distance réelle parcourue
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rc (user_id, mission_id),
    INDEX idx_rc_mission (mission_id),
    INDEX idx_rc_user    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grande Mission Saisonnière (spécialisation)
ALTER TABLE missions
    ADD COLUMN IF NOT EXISTS is_grande_mission TINYINT(1)   NOT NULL DEFAULT 0 AFTER is_flash,
    ADD COLUMN IF NOT EXISTS grande_mission_season_id INT UNSIGNED NULL         AFTER is_grande_mission;

ALTER TABLE missions
    ADD INDEX IF NOT EXISTS idx_missions_grande (is_grande_mission, status),
    ADD INDEX IF NOT EXISTS idx_missions_season_grande (grande_mission_season_id);
