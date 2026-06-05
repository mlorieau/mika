-- ZONE85 — Migration 003 : Moteur de participation V1
-- Date : 2026-05-28
-- Vérifications : schéma déjà complet (participations, xp_logs, clan_score_logs OK)
-- Ce fichier ajoute uniquement la mission de test si elle n'existe pas.

USE zone85;

-- ============================================================
-- Mission de test V1 — "Premier pas dans la Zone"
-- Simple, auto-validée, type vote, active.
-- Permet de valider la boucle participation → XP → profil.
-- ============================================================
INSERT IGNORE INTO missions
    (title, slug, mission_type, description, instructions,
     status, is_collective, validation_mode,
     requires_answer, requires_upload, requires_vote, requires_code,
     xp_participation, xp_success,
     clan_points_participation, clan_points_success,
     display_in_hall)
VALUES
    ('Premier pas dans la Zone',
     'premier-pas-zone',
     'vote',
     'Valide ta première participation et gagne tes premiers XP. Bienvenue dans la Zone !',
     'Clique sur "Je participe" pour valider ton premier pas dans la Zone85. Tes XP sont crédités immédiatement.',
     'active', 0, 'auto',
     0, 0, 1, 0,
     5, 0,
     1, 0,
     0);

-- ============================================================
-- Note : les tables participations, xp_logs et clan_score_logs
-- existent déjà depuis schema.sql. Aucune modification de
-- structure requise pour la V1.
-- ============================================================
-- Les colonnes nécessaires sont déjà présentes :
--   participations : user_id, mission_id, game_id, answer_text,
--                    selected_option_id, status (auto_validated/pending),
--                    is_success, xp_awarded, clan_points_awarded
--   xp_logs : user_id, source_type, source_id, xp_amount, reason
--   clan_score_logs : clan_id, season_id, user_id, source_type,
--                     source_id, points, reason
-- ============================================================
