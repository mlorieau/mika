-- ============================================================
-- Migration 015 — V10.2 : Recalcul niveaux utilisateurs
-- Nouveaux seuils : 0/50/100/250/500/1000/2500/5000/10000/20000
-- ============================================================

-- Recalculer le niveau de tous les membres actifs
UPDATE `users` SET `level` = CASE
    WHEN `xp_total` >= 20000 THEN 10
    WHEN `xp_total` >= 10000 THEN 9
    WHEN `xp_total` >= 5000  THEN 8
    WHEN `xp_total` >= 2500  THEN 7
    WHEN `xp_total` >= 1000  THEN 6
    WHEN `xp_total` >= 500   THEN 5
    WHEN `xp_total` >= 250   THEN 4
    WHEN `xp_total` >= 100   THEN 3
    WHEN `xp_total` >= 50    THEN 2
    ELSE 1
END
WHERE `status` = 'active' AND `deleted_at` IS NULL;

-- Vérification après migration
-- SELECT level, COUNT(*) AS nb_users FROM users GROUP BY level ORDER BY level;
