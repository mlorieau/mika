-- Migration 030 — V13 : password_resets + notifications
-- Date : 2026-06-05

-- ── Table password_resets ──────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  email      VARCHAR(180) NOT NULL,
  token      VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pr_token (token),
  KEY idx_pr_user  (user_id),
  KEY idx_pr_email (email),
  KEY idx_pr_exp   (expires_at),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table notifications ────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(50) NOT NULL,
  title      VARCHAR(200) NOT NULL,
  body       TEXT NULL,
  icon_emoji VARCHAR(20) NULL,
  link_url   VARCHAR(500) NULL,
  read_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user_unread (user_id, read_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Colonnes users (si migration 007 n'a pas été jouée) ───
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema=DATABASE() AND table_name='users' AND column_name='email_verified_at');
SET @sql = IF(@x=0,
  'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER accepted_privacy_at',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema=DATABASE() AND table_name='users' AND column_name='email_verify_token');
SET @sql = IF(@x=0,
  'ALTER TABLE users ADD COLUMN email_verify_token VARCHAR(64) NULL AFTER email_verified_at',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE table_schema=DATABASE() AND table_name='users' AND column_name='deleted_at');
SET @sql = IF(@x=0,
  'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Templates email ────────────────────────────────────────
INSERT IGNORE INTO email_templates (slug, subject, description) VALUES
  ('email_verification', 'Confirmez votre adresse email — Zone85', 'Confirmation email lors de l\'inscription'),
  ('password_reset',     'Réinitialisation de votre mot de passe',  'Lien de réinitialisation mot de passe'),
  ('mission_validated',  'Ta participation a été validée',          'Notification validation participation');
