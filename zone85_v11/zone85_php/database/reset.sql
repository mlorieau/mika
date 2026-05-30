-- ZONE85 — Reset base de données
-- ATTENTION : supprime toutes les données !
-- Usage : mysql -u zone85_user -p zone85 < reset.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE zone85;

DROP TABLE IF EXISTS legal_acceptances;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS hall_items;
DROP TABLE IF EXISTS season_trophies;
DROP TABLE IF EXISTS clan_score_logs;
DROP TABLE IF EXISTS xp_logs;
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS user_progress;
DROP TABLE IF EXISTS participations;
DROP TABLE IF EXISTS media;
DROP TABLE IF EXISTS mission_options;
DROP TABLE IF EXISTS missions;
DROP TABLE IF EXISTS badges;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS seasons;
DROP TABLE IF EXISTS clans;

SET FOREIGN_KEY_CHECKS = 1;
