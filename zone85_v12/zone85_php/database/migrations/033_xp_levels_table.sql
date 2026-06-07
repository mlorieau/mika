-- ============================================================
-- Migration 033 — Table xp_levels (gestion BO des niveaux)
-- ============================================================

CREATE TABLE IF NOT EXISTS xp_levels (
    level        TINYINT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    xp_required  INT UNSIGNED NOT NULL DEFAULT 0,
    color        VARCHAR(20) NOT NULL DEFAULT '#6b7f96',
    emoji        VARCHAR(10) NOT NULL DEFAULT '⭐',
    PRIMARY KEY (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales (correspondant aux valeurs hardcodées actuelles)
INSERT IGNORE INTO xp_levels (level, name, xp_required, color, emoji) VALUES
(1,  'Novice',       0,     '#6b7f96', '🌱'),
(2,  'Explorateur',  50,    '#2a9d5c', '🧭'),
(3,  'Aventurier',   100,   '#12314e', '🏕️'),
(4,  'Expert',       250,   '#0c6291', '⚡'),
(5,  'Gardien',      500,   '#9b59b6', '🛡️'),
(6,  'Légende',      1000,  '#C9962A', '🌟'),
(7,  'Grand Pisteur',2500,  '#ea5649', '🗺️'),
(8,  'Vétéran',      5000,  '#8b1a1a', '🔥'),
(9,  'Ancêtre',      10000, '#1a1a2e', '💎'),
(10, 'Immortel',     20000, '#0c1e2e', '👑');
