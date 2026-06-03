-- Migration 029 — Index de performance + table rate_limits
-- Date : 2026-06-03

-- ── Index sur colonnes status (requêtes fréquentes) ──────────
-- missions
SET @x = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'missions' AND index_name = 'idx_missions_status');
SET @sql = IF(@x = 0, 'ALTER TABLE missions ADD INDEX idx_missions_status (status)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- users
SET @x = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_users_status');
SET @sql = IF(@x = 0, 'ALTER TABLE users ADD INDEX idx_users_status (status)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- participations
SET @x = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'participations' AND index_name = 'idx_part_status');
SET @sql = IF(@x = 0, 'ALTER TABLE participations ADD INDEX idx_part_status (status)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- rando_participations
SET @x = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE table_schema = DATABASE() AND table_name = 'rando_participations' AND index_name = 'idx_rp_status');
SET @sql = IF(@x = 0, 'ALTER TABLE rando_participations ADD INDEX idx_rp_status (status)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Table rate_limits (anti brute-force) ─────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
  id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  ip_hash      VARCHAR(64)      NOT NULL,
  endpoint     VARCHAR(60)      NOT NULL,
  attempts     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  window_start DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rl_ip_endpoint (ip_hash, endpoint),
  KEY idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
