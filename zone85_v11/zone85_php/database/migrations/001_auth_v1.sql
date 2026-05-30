-- ZONE85 — Migration 001 : Auth V1
-- Vérification de compatibilité du schéma avec l'authentification membre.
-- Aucune modification de table requise : toutes les colonnes nécessaires
-- existent déjà dans schema.sql v1.
--
-- Colonnes utilisées par l'auth V1 :
--   users         : email, password_hash, pseudo, first_name, last_name, clan_id,
--                   avatar_type, avatar_config, avatar_file, bio, xp_total, level,
--                   newsletter_optin, status, role, last_login_at, created_at
--   xp_logs       : user_id, source_type, source_id, xp_amount, reason, created_at
--   legal_acceptances : user_id, document_type, document_version, accepted_at, ip_hash
--   user_badges   : user_id, badge_id, source_type
--
-- À importer uniquement si vous avez un schéma antérieur à la version 1 complète.
-- Usage : mysql -u zone85_user -p zone85 < database/migrations/001_auth_v1.sql

SET NAMES utf8mb4;

-- Aucune altération nécessaire.
-- Ce fichier sert de point de référence de version.
SELECT 'Migration 001_auth_v1 : schéma déjà à jour.' AS status;
