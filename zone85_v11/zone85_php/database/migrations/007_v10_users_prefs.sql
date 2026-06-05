-- ============================================================
-- Migration 007 — V10 : préférences utilisateur, RGPD, PWA
-- ============================================================

-- Suppression de compte (soft delete)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deleted_at        DATETIME     NULL DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS delete_requested_at DATETIME  NULL DEFAULT NULL AFTER deleted_at,

-- Vérification email
    ADD COLUMN IF NOT EXISTS email_verified_at  DATETIME   NULL DEFAULT NULL AFTER email,
    ADD COLUMN IF NOT EXISTS email_verify_token VARCHAR(64) NULL DEFAULT NULL AFTER email_verified_at,

-- Connexion
    ADD COLUMN IF NOT EXISTS last_login_at      DATETIME   NULL DEFAULT NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS login_count        INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at,

-- PWA
    ADD COLUMN IF NOT EXISTS pwa_installed_at   DATETIME   NULL DEFAULT NULL AFTER login_count,
    ADD COLUMN IF NOT EXISTS pwa_install_count  TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pwa_installed_at,

-- Préférences notifications/emails
    ADD COLUMN IF NOT EXISTS notif_missions     TINYINT(1) NOT NULL DEFAULT 1 AFTER newsletter_optin,
    ADD COLUMN IF NOT EXISTS notif_saisons      TINYINT(1) NOT NULL DEFAULT 1 AFTER notif_missions,
    ADD COLUMN IF NOT EXISTS notif_clan         TINYINT(1) NOT NULL DEFAULT 1 AFTER notif_saisons,
    ADD COLUMN IF NOT EXISTS notif_push         TINYINT(1) NOT NULL DEFAULT 0 AFTER notif_clan,
    ADD COLUMN IF NOT EXISTS digest_hebdo       TINYINT(1) NOT NULL DEFAULT 0 AFTER notif_push;

-- Index sur deleted_at pour filtres admin
ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_deleted (deleted_at),
    ADD INDEX IF NOT EXISTS idx_users_login (last_login_at);

-- find_user_by_email / find_user_by_id : exclure deleted
-- (les fonctions PHP font déjà WHERE status='active' — ajouter deleted_at IS NULL suffit)

-- Ajout colonne pwa_installs (stats globales via table dédiée)
CREATE TABLE IF NOT EXISTS pwa_installs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    platform   VARCHAR(32)  NOT NULL DEFAULT 'unknown',  -- android|ios|desktop
    user_agent VARCHAR(512) NULL,
    installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pwa_user (user_id),
    INDEX idx_pwa_date (installed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
