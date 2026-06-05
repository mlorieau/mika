-- ZONE85 — Migration 004 : Back-office Admin V1
-- Date : 2026-05-28
-- Prérequis : schema.sql déjà appliqué (contient users.role, participations.validated_by, etc.)
-- Ce fichier vérifie/ajoute uniquement ce qui pourrait manquer sur une base ancienne.

USE zone85;

-- ============================================================
-- Ajout du champ role sur users si absent (base pré-schéma)
-- Sur le schéma v1, ce champ existe déjà.
-- ============================================================
-- ALTER TABLE users
--   MODIFY COLUMN role ENUM('member','moderator','admin') NOT NULL DEFAULT 'member';
-- (décommentez si votre base est antérieure au schéma v1 complet)

-- ============================================================
-- Index utiles pour les requêtes admin (si absents)
-- ============================================================
ALTER TABLE participations
  ADD INDEX IF NOT EXISTS idx_part_status (status),
  ADD INDEX IF NOT EXISTS idx_part_created (created_at);

ALTER TABLE users
  ADD INDEX IF NOT EXISTS idx_users_role (role);

ALTER TABLE missions
  ADD INDEX IF NOT EXISTS idx_missions_status (status),
  ADD INDEX IF NOT EXISTS idx_missions_type   (mission_type);

-- ============================================================
-- Passer un utilisateur en admin (décommenter + adapter)
-- ============================================================
-- UPDATE users SET role = 'admin' WHERE email = 'votre@email.fr';

-- ============================================================
-- Vérification : les colonnes suivantes doivent exister
--   participations : status, validated_by, validated_at,
--                    xp_awarded, clan_points_awarded
--   users          : role ENUM('member','moderator','admin')
--   missions       : validation_mode, xp_participation,
--                    xp_success, clan_points_participation,
--                    clan_points_success, status
-- Elles font partie du schéma v1 — aucune ALTER requise.
-- ============================================================
