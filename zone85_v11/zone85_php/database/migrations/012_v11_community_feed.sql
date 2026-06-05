-- ============================================================
-- Migration 012 — V11 : Feed communautaire + météo + KTC
-- ============================================================

-- Feed communautaire (Hall vivant)
CREATE TABLE IF NOT EXISTS community_feed (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type  ENUM(
        'mission_new',       -- nouvelle mission publiée
        'mission_complete',  -- mission complétée (auto)
        'badge_unlock',      -- badge gagné
        'flash_start',       -- événement flash lancé
        'flash_end',         -- événement flash terminé
        'season_start',      -- nouvelle saison
        'season_end',        -- saison clôturée
        'clan_lead',         -- un clan passe premier
        'trophy_awarded',    -- trophée décerné
        'collectible_found', -- objet caché trouvé
        'rando_done',        -- rando validée
        'ktc_win'            -- KTC résolu
    ) NOT NULL,
    user_id     INT UNSIGNED NULL,
    clan_id     INT UNSIGNED NULL,
    mission_id  INT UNSIGNED NULL,
    badge_id    INT UNSIGNED NULL,
    season_id   INT UNSIGNED NULL,
    title       VARCHAR(255) NOT NULL,
    body        VARCHAR(512) NULL,
    icon_emoji  VARCHAR(8)   NOT NULL DEFAULT '📋',
    link_url    VARCHAR(255) NULL,
    is_pinned   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cf_type    (event_type),
    INDEX idx_cf_created (created_at),
    INDEX idx_cf_user    (user_id),
    INDEX idx_cf_pinned  (is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Météo Zone85
CREATE TABLE IF NOT EXISTS weather_posts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone         ENUM('bocage','littoral','marais','vendee') NOT NULL DEFAULT 'vendee',
    title        VARCHAR(255) NOT NULL,
    body         TEXT         NULL,
    weather_icon      VARCHAR(8)   NOT NULL DEFAULT '🌤️',
    temperature       TINYINT      NULL,              -- °C (optionnel)
    weather_condition VARCHAR(64)  NULL,              -- 'ensoleille', 'pluie', 'orage', etc.
    is_alert     TINYINT(1)   NOT NULL DEFAULT 0,
    is_event     TINYINT(1)   NOT NULL DEFAULT 0,   -- lié à un événement météo gameplay
    mission_id   INT UNSIGNED NULL,
    published_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME     NULL,
    created_by   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    INDEX idx_wp_zone    (zone, published_at),
    INDEX idx_wp_alert   (is_alert, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KTC Questions (Ketokolé Tché — mini-jeux culturels vendéens)
CREATE TABLE IF NOT EXISTS ktc_questions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category     ENUM('expression','quiz','devinette','histoire','nature','gastronomie') NOT NULL DEFAULT 'quiz',
    question     TEXT         NOT NULL,
    answer_a     VARCHAR(255) NOT NULL,
    answer_b     VARCHAR(255) NOT NULL,
    answer_c     VARCHAR(255) NULL,
    answer_d     VARCHAR(255) NULL,
    correct      ENUM('a','b','c','d') NOT NULL DEFAULT 'a',
    explanation  TEXT         NULL,
    difficulty   TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 1=facile, 2=moyen, 3=difficile
    xp_reward    TINYINT UNSIGNED NOT NULL DEFAULT 5,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    season_id    INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_ktc_cat  (category, is_active),
    INDEX idx_ktc_diff (difficulty, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réponses KTC des utilisateurs
CREATE TABLE IF NOT EXISTS ktc_answers (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    question_id  INT UNSIGNED NOT NULL,
    given_answer ENUM('a','b','c','d') NOT NULL,
    is_correct   TINYINT(1)   NOT NULL DEFAULT 0,
    xp_earned    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    answered_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ktc_answer (user_id, question_id),
    INDEX idx_ktca_user (user_id),
    INDEX idx_ktca_q    (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed KTC : quelques questions vendéennes d'exemple
INSERT IGNORE INTO ktc_questions (category, question, answer_a, answer_b, answer_c, answer_d, correct, explanation, difficulty, xp_reward) VALUES
    ('expression', 'Que signifie "Mogette" en Vendée ?', 'Un marécage', 'Un haricot blanc', 'Une tempête', 'Un canard', 'b', 'La mogette est le haricot blanc emblématique de la Vendée, souvent accompagnée de jambon.', 1, 5),
    ('expression', 'Que veut dire "être chouan" ?', 'Être courageux', 'Être vendéen rebelle', 'Être fatigué', 'Être en retard', 'b', 'Les Chouans étaient les insurgés vendéens et bretons contre la Révolution.', 2, 8),
    ('quiz', 'Quelle est la préfecture de la Vendée ?', 'Les Sables-d\'Olonne', 'Fontenay-le-Comte', 'La Roche-sur-Yon', 'Luçon', 'c', 'La Roche-sur-Yon est la préfecture de la Vendée, fondée sous Napoléon.', 1, 5),
    ('nature', 'Quel est l\'arbre emblématique du bocage vendéen ?', 'Le chêne', 'Le charme', 'Le frêne', 'Le noyer', 'a', 'Le chêne pédonculé est l\'arbre roi du bocage vendéen.', 1, 5),
    ('gastronomie', 'Quelle spécialité est liée aux Sables-d\'Olonne ?', 'La brioche vendéenne', 'Le jambon de Vendée', 'La bouillie de sarrasin', 'Le préfou', 'd', 'Le préfou est un pain à l\'ail et au beurre, spécialité vendéenne incontournable.', 1, 5),
    ('histoire', 'Qui était Charette de la Contrie ?', 'Un navigateur vendéen', 'Un chef chouan de Vendée', 'Un évêque', 'Un marchand de sel', 'b', 'François de Charette de la Contrie fut l\'un des principaux chefs militaires de la Vendée militaire.', 2, 8),
    ('devinette', 'Je couvre les marais, je nourris les étangs, en Vendée on me ramasse à marée basse. Qui suis-je ?', 'La salicorne', 'La moule de bouchot', 'L\'anguille', 'Le mulet', 'b', 'La moule de bouchot des Pays de la Loire, élevée sur des pieux en bois dans les marais vendéens.', 2, 8);
