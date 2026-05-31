-- ============================================================
-- Migration 008 — V10 : file d'attente emails (Brevo-ready)
-- ============================================================

CREATE TABLE IF NOT EXISTS email_queue (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NULL,                      -- destinataire (null = hors membres)
    to_email      VARCHAR(255) NOT NULL,
    to_name       VARCHAR(120) NULL,
    template_slug VARCHAR(80)  NOT NULL,                  -- 'welcome','badge_unlock','mission_new'…
    subject       VARCHAR(255) NOT NULL,
    variables     JSON         NULL,                      -- données pour template Brevo
    status        ENUM('pending','sending','sent','failed','skipped')
                              NOT NULL DEFAULT 'pending',
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error    TEXT         NULL,
    scheduled_at  DATETIME     NULL,                      -- NULL = envoi immédiat
    sent_at       DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_eq_status  (status, scheduled_at),
    INDEX idx_eq_user    (user_id),
    INDEX idx_eq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Templates Brevo : référentiel des slugs
CREATE TABLE IF NOT EXISTS email_templates (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(80)  NOT NULL,
    brevo_id     INT UNSIGNED NULL,                       -- ID template Brevo (null tant que non configuré)
    subject      VARCHAR(255) NOT NULL,
    description  VARCHAR(512) NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tpl_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO email_templates (slug, subject, description) VALUES
    ('welcome',           'Bienvenue dans la Zone !',                  'Email de bienvenue après inscription'),
    ('email_verify',      'Vérifie ton adresse email — Zone85',       'Vérification email'),
    ('password_reset',    'Réinitialisation de ton mot de passe',      'Mot de passe oublié'),
    ('badge_unlock',      '🏅 Tu as débloqué un badge !',             'Notification badge gagné'),
    ('mission_new',       '🎯 Nouvelle mission disponible !',         'Annonce nouvelle mission'),
    ('mission_complete',  '✅ Mission accomplie !',                    'Confirmation completion mission'),
    ('season_start',      '🚀 Nouvelle saison Zone85 !',              'Début de saison'),
    ('digest_hebdo',      '📋 Tes infos Zone85 de la semaine',        'Résumé hebdomadaire'),
    ('delete_requested',  'Demande de suppression reçue',             'Confirmation demande suppression compte');
