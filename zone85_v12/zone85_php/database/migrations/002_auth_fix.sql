-- ZONE85 — Migration 002 : vérification et correction tables Auth V1
-- À exécuter si schema.sql a été importé avant la version V6.1
-- Idempotente : utilise IF NOT EXISTS / IGNORE

SET NAMES utf8mb4;

-- ── xp_logs : vérifier que source_id est nullable (INT UNSIGNED NULL)
-- La colonne source_id doit accepter NULL et les valeurs non nulles.
-- Si la table existe avec source_id NOT NULL, cette ligne ne fait rien (déjà correct si schema.sql v1 importé).
-- Aucune modification destructive.

-- ── legal_acceptances : s'assurer que la table existe
CREATE TABLE IF NOT EXISTS legal_acceptances (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  document_type    ENUM('cgu','privacy','cookies') NOT NULL,
  document_version VARCHAR(20) NOT NULL DEFAULT '1.0',
  accepted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash          VARCHAR(64) NULL,
  KEY idx_la_user (user_id),
  CONSTRAINT fk_la_user_002 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user_badges : s'assurer que la table existe
CREATE TABLE IF NOT EXISTS user_badges (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  badge_id    INT UNSIGNED NOT NULL,
  source_type VARCHAR(50) NULL,
  source_id   INT UNSIGNED NULL,
  awarded_by  INT UNSIGNED NULL,
  awarded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_badges_002 (user_id, badge_id),
  KEY idx_ub_badge_002 (badge_id),
  CONSTRAINT fk_ub_user_002  FOREIGN KEY (user_id)   REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ub_badge_002 FOREIGN KEY (badge_id)  REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── xp_logs : s'assurer que la table existe
CREATE TABLE IF NOT EXISTS xp_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  source_type VARCHAR(50) NOT NULL,
  source_id   INT UNSIGNED NULL,
  xp_amount   SMALLINT NOT NULL,
  reason      VARCHAR(100),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_xp_user_002    (user_id),
  KEY idx_xp_created_002 (created_at),
  CONSTRAINT fk_xp_user_002 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Badge de bienvenue : s'assurer qu'il existe
INSERT IGNORE INTO badges (title, slug, description, icon, rarity, condition_type)
VALUES ('Pionnier de la Zone', 'pionnier-zone', 'Accordé à chaque membre lors de son inscription.', '🧭', 'common', 'manual');
