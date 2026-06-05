-- ============================================================
-- Migration 020 -- V11.2 : Quiz contribue 1 pt clan
-- Auparavant : quiz = 0 pts clan = message "chaque action aide ton clan" faux
-- Nouveau : toute mission donne au minimum 1 pt clan a la participation
-- ============================================================

-- Corriger les missions quiz existantes (clan_points_participation = 0 -> 1)
UPDATE `missions`
SET `clan_points_participation` = 1
WHERE `mission_type` = 'quiz'
  AND `clan_points_participation` = 0
  AND `status` != 'archived';

-- Corriger les missions vote (0 pts -> reste 0, action trop passive)
-- Pas de changement volontaire pour vote

-- Verif :
-- SELECT id, title, mission_type, clan_points_participation, clan_points_success
-- FROM missions WHERE status = 'active' ORDER BY mission_type;
