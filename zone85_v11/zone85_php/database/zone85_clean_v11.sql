-- ============================================================
-- ZONE85 — Base de données propre V11
-- Généré le : 2026-05-29
-- Base cible : qg_
--
-- ✅ Toutes les tables V8 → V11 (schéma réel + extensions V11)
-- ✅ 1 seul utilisateur : Mickaël Lorieau (admin)
-- ✅ Données de référence : clans, saisons, badges, missions, KTC
-- ✅ Tous les compteurs à 0
-- ✅ Aucun membre démo, aucune donnée de test
--
-- IMPORT : phpMyAdmin → sélectionner base qg_ → Importer → ce fichier
-- Connexion : lorieau.mickael@gmail.com / aaaaaaaa (admin)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- SUPPRESSION DES TABLES (ordre FK inverse)
-- ============================================================
DROP TABLE IF EXISTS `user_collectibles`;
DROP TABLE IF EXISTS `user_badges`;
DROP TABLE IF EXISTS `user_progress`;
DROP TABLE IF EXISTS `xp_logs`;
DROP TABLE IF EXISTS `participations`;
DROP TABLE IF EXISTS `clan_score_logs`;
DROP TABLE IF EXISTS `rando_completions`;
DROP TABLE IF EXISTS `flash_participations`;
DROP TABLE IF EXISTS `ktc_answers`;
DROP TABLE IF EXISTS `mission_options`;
DROP TABLE IF EXISTS `mission_rando_data`;
DROP TABLE IF EXISTS `mission_collectibles`;
DROP TABLE IF EXISTS `pwa_installs`;
DROP TABLE IF EXISTS `community_feed`;
DROP TABLE IF EXISTS `weather_posts`;
DROP TABLE IF EXISTS `legal_acceptances`;
DROP TABLE IF EXISTS `email_queue`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `hall_items`;
DROP TABLE IF EXISTS `media`;
DROP TABLE IF EXISTS `season_clan_results`;
DROP TABLE IF EXISTS `season_trophies`;
DROP TABLE IF EXISTS `missions`;
DROP TABLE IF EXISTS `games`;
DROP TABLE IF EXISTS `seasons`;
DROP TABLE IF EXISTS `ktc_questions`;
DROP TABLE IF EXISTS `email_templates`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `badges`;
DROP TABLE IF EXISTS `clans`;

-- ============================================================
-- CRÉATION DES TABLES
-- ============================================================

CREATE TABLE `clans` (
  `id`              int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            varchar(100) NOT NULL,
  `slug`            varchar(100) NOT NULL,
  `description`     text DEFAULT NULL,
  `mascot_image`    varchar(255) DEFAULT NULL,
  `color_primary`   varchar(20) DEFAULT '#12314e',
  `color_secondary` varchar(20) DEFAULT '#163756',
  `motto`           varchar(255) DEFAULT NULL,
  `cry`             varchar(255) DEFAULT NULL,
  `is_active`       tinyint(1) DEFAULT 1,
  `created_at`      datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`      datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=4;

CREATE TABLE `badges` (
  `id`               int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`            varchar(100) NOT NULL,
  `slug`             varchar(100) NOT NULL,
  `category`         enum('exploration','clan','saison','meteo','rando','culture','invisible','general') NOT NULL DEFAULT 'general',
  `description`      text DEFAULT NULL,
  `icon`             varchar(20) DEFAULT NULL,
  `rarity`           enum('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common',
  `season_id`        int(10) UNSIGNED DEFAULT NULL,
  `is_hidden`        tinyint(1) NOT NULL DEFAULT 0,
  `unlock_condition` text DEFAULT NULL,
  `color_primary`    varchar(7) NOT NULL DEFAULT '#ea5649',
  `icon_emoji`       varchar(8) DEFAULT NULL,
  `condition_type`   enum('manual','xp_threshold','mission_success','season','special') NOT NULL DEFAULT 'manual',
  `condition_value`  int(11) DEFAULT NULL,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_badges_slug` (`slug`),
  KEY `idx_badges_cat` (`category`),
  KEY `idx_badges_rar` (`rarity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=20;

CREATE TABLE `seasons` (
  `id`                 int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`              varchar(200) NOT NULL,
  `slug`               varchar(200) NOT NULL,
  `color_primary`      varchar(7) NOT NULL DEFAULT '#0c1e2e',
  `color_secondary`    varchar(7) NOT NULL DEFAULT '#ea5649',
  `emoji`              varchar(8) NOT NULL DEFAULT '?',
  `description`        text DEFAULT NULL,
  `description_long`   text DEFAULT NULL,
  `image_url`          varchar(255) DEFAULT NULL,
  `badge_reward_id`    int(10) UNSIGNED DEFAULT NULL,
  `grande_mission_id`  int(10) UNSIGNED DEFAULT NULL,
  `period_label`       varchar(100) DEFAULT NULL,
  `start_date`         date DEFAULT NULL,
  `end_date`           date DEFAULT NULL,
  `closed_at`          datetime DEFAULT NULL,
  `winner_clan_id`     int(10) UNSIGNED DEFAULT NULL,
  `status`             enum('upcoming','active','archived') NOT NULL DEFAULT 'upcoming',
  `theme_color`        varchar(20) DEFAULT NULL,
  `main_game_id`       int(10) UNSIGNED DEFAULT NULL,
  `created_at`         datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`         datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seasons_slug` (`slug`),
  KEY `idx_seasons_status` (`status`),
  KEY `idx_seasons_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=5;

CREATE TABLE `games` (
  `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `season_id`    int(10) UNSIGNED DEFAULT NULL,
  `title`        varchar(200) NOT NULL,
  `slug`         varchar(200) NOT NULL,
  `description`  text DEFAULT NULL,
  `game_type`    enum('seasonal_event','evergreen','hidden_hunt','premium_game','editorial_game') NOT NULL,
  `status`       enum('draft','active','archived','coming_soon') NOT NULL DEFAULT 'draft',
  `is_paid`      tinyint(1) DEFAULT 0,
  `is_collective` tinyint(1) DEFAULT 0,
  `cover_image`  varchar(255) DEFAULT NULL,
  `rules`        text DEFAULT NULL,
  `start_date`   date DEFAULT NULL,
  `end_date`     date DEFAULT NULL,
  `created_at`   datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`   datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_games_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=100;

CREATE TABLE `email_templates` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        varchar(80) NOT NULL,
  `brevo_id`    int(10) UNSIGNED DEFAULT NULL,
  `subject`     varchar(255) NOT NULL,
  `description` varchar(512) DEFAULT NULL,
  `is_active`   tinyint(1) NOT NULL DEFAULT 1,
  `created_at`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpl_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10;

CREATE TABLE `ktc_questions` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`    enum('expression','quiz','devinette','histoire','nature','gastronomie') NOT NULL DEFAULT 'quiz',
  `question`    text NOT NULL,
  `answer_a`    varchar(255) NOT NULL,
  `answer_b`    varchar(255) NOT NULL,
  `answer_c`    varchar(255) DEFAULT NULL,
  `answer_d`    varchar(255) DEFAULT NULL,
  `correct`     enum('a','b','c','d') NOT NULL DEFAULT 'a',
  `explanation` text DEFAULT NULL,
  `difficulty`  tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `xp_reward`   tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `is_active`   tinyint(1) NOT NULL DEFAULT 1,
  `season_id`   int(10) UNSIGNED DEFAULT NULL,
  `created_at`  datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ktc_cat` (`category`,`is_active`),
  KEY `idx_ktc_diff` (`difficulty`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=8;

CREATE TABLE `users` (
  `id`                   int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`                varchar(180) NOT NULL,
  `email_verified_at`    datetime DEFAULT NULL,
  `email_verify_token`   varchar(64) DEFAULT NULL,
  `password_hash`        varchar(255) NOT NULL DEFAULT '',
  `pseudo`               varchar(50) NOT NULL,
  `first_name`           varchar(100) DEFAULT NULL,
  `last_name`            varchar(100) DEFAULT NULL,
  `clan_id`              int(10) UNSIGNED DEFAULT NULL,
  `avatar_type`          enum('preset','generated','upload') NOT NULL DEFAULT 'preset',
  `avatar_config`        longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`avatar_config`)),
  `avatar_file`          varchar(255) DEFAULT NULL,
  `bio`                  text DEFAULT NULL,
  `xp_total`             int(10) UNSIGNED NOT NULL DEFAULT 0,
  `level`                tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `newsletter_optin`     tinyint(1) DEFAULT 0,
  `notif_missions`       tinyint(1) NOT NULL DEFAULT 1,
  `notif_saisons`        tinyint(1) NOT NULL DEFAULT 1,
  `notif_clan`           tinyint(1) NOT NULL DEFAULT 1,
  `notif_push`           tinyint(1) NOT NULL DEFAULT 0,
  `digest_hebdo`         tinyint(1) NOT NULL DEFAULT 0,
  `accepted_cgu_at`      datetime DEFAULT NULL,
  `accepted_privacy_at`  datetime DEFAULT NULL,
  `role`                 enum('member','moderator','admin') NOT NULL DEFAULT 'member',
  `status`               enum('active','suspended','deleted') NOT NULL DEFAULT 'active',
  `deleted_at`           datetime DEFAULT NULL,
  `delete_requested_at`  datetime DEFAULT NULL,
  `last_login_at`        datetime DEFAULT NULL,
  `login_count`          int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pwa_installed_at`     datetime DEFAULT NULL,
  `pwa_install_count`    tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at`           datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`           datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_pseudo` (`pseudo`),
  KEY `fk_users_clan` (`clan_id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_deleted` (`deleted_at`),
  KEY `idx_users_login` (`last_login_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=2;

CREATE TABLE `missions` (
  `id`                       int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `game_id`                  int(10) UNSIGNED DEFAULT NULL,
  `season_id`                int(10) UNSIGNED DEFAULT NULL,
  `title`                    varchar(200) NOT NULL,
  `slug`                     varchar(200) NOT NULL,
  `mission_type`             enum('seasonal_collective','quiz','vote','photo_challenge','keto_kole_tche','rando','weather_mission','investigation','hidden_hunt','premium_game','event_flash') NOT NULL,
  `description`              text DEFAULT NULL,
  `instructions`             text DEFAULT NULL,
  `status`                   enum('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
  `is_collective`            tinyint(1) DEFAULT 0,
  `validation_mode`          enum('auto','manual','hybrid') NOT NULL DEFAULT 'auto',
  `requires_answer`          tinyint(1) DEFAULT 0,
  `requires_upload`          tinyint(1) DEFAULT 0,
  `requires_vote`            tinyint(1) DEFAULT 0,
  `requires_code`            tinyint(1) DEFAULT 0,
  `xp_participation`         smallint(5) UNSIGNED DEFAULT 0,
  `xp_success`               smallint(5) UNSIGNED DEFAULT 0,
  `clan_points_participation` smallint(5) UNSIGNED DEFAULT 0,
  `clan_points_success`      smallint(5) UNSIGNED DEFAULT 0,
  `badge_reward_id`          int(10) UNSIGNED DEFAULT NULL,
  `start_date`               date DEFAULT NULL,
  `end_date`                 date DEFAULT NULL,
  `display_in_hall`          tinyint(1) DEFAULT 0,
  `flash_start_at`           datetime DEFAULT NULL,
  `flash_end_at`             datetime DEFAULT NULL,
  `xp_multiplier`            decimal(3,1) NOT NULL DEFAULT 1.0,
  `is_flash`                 tinyint(1) NOT NULL DEFAULT 0,
  `is_grande_mission`        tinyint(1) NOT NULL DEFAULT 0,
  `grande_mission_season_id` int(10) UNSIGNED DEFAULT NULL,
  `flash_badge_id`           int(10) UNSIGNED DEFAULT NULL,
  `cover_emoji`              varchar(8) NOT NULL DEFAULT '?',
  `created_at`               datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`               datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_missions_slug` (`slug`),
  KEY `idx_missions_status` (`status`),
  KEY `idx_missions_type` (`mission_type`),
  KEY `idx_missions_flash` (`is_flash`,`flash_end_at`,`status`),
  KEY `idx_missions_grande` (`is_grande_mission`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10;

CREATE TABLE `mission_options` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `mission_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL, `value` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0, `sort_order` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `fk_mopts_mission` (`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mission_collectibles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `mission_id` int(10) UNSIGNED NOT NULL,
  `collectible_key` varchar(100) NOT NULL, `title` varchar(200) NOT NULL,
  `hint` text DEFAULT NULL, `page_slug` varchar(50) NOT NULL DEFAULT 'index',
  `page_url` varchar(255) DEFAULT NULL,
  `position_top` decimal(6,2) DEFAULT 50.00, `position_left` decimal(6,2) DEFAULT 50.00,
  `position_top_mobile` decimal(6,2) DEFAULT NULL, `position_left_mobile` decimal(6,2) DEFAULT NULL,
  `size_desktop` tinyint(3) UNSIGNED NOT NULL DEFAULT 48, `size_mobile` tinyint(3) UNSIGNED NOT NULL DEFAULT 40,
  `object_image` varchar(255) DEFAULT NULL, `success_gif` varchar(255) DEFAULT NULL,
  `success_title` varchar(200) DEFAULT 'Bravo !', `success_message` text DEFAULT NULL,
  `sort_order` tinyint(3) UNSIGNED DEFAULT 0, `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_collectible_key` (`mission_id`,`collectible_key`),
  KEY `idx_coll_mission_page` (`mission_id`,`page_slug`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mission_rando_data` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `mission_id` int(10) UNSIGNED NOT NULL,
  `distance_km` decimal(5,1) DEFAULT NULL, `elevation_m` smallint(6) DEFAULT NULL,
  `difficulty` enum('facile','moyen','difficile','expert') NOT NULL DEFAULT 'facile',
  `duration_min` smallint(5) UNSIGNED DEFAULT NULL, `region` varchar(64) DEFAULT NULL,
  `start_point` varchar(255) DEFAULT NULL, `gpx_url` varchar(255) DEFAULT NULL,
  `photo_required` tinyint(1) NOT NULL DEFAULT 0, `badge_rando_id` int(10) UNSIGNED DEFAULT NULL,
  `description_trail` text DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_mrd_mission` (`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `mission_id` int(10) UNSIGNED NOT NULL, `game_id` int(10) UNSIGNED DEFAULT NULL,
  `answer_text` text DEFAULT NULL, `selected_option_id` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_media_id` int(10) UNSIGNED DEFAULT NULL, `comment` text DEFAULT NULL,
  `status` enum('pending','validated','rejected','auto_validated') NOT NULL DEFAULT 'pending',
  `is_success` tinyint(1) DEFAULT 0, `xp_awarded` smallint(5) UNSIGNED DEFAULT 0,
  `clan_points_awarded` smallint(5) UNSIGNED DEFAULT 0, `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_participations_user_mission` (`user_id`,`mission_id`),
  KEY `idx_part_mission` (`mission_id`), KEY `idx_part_status` (`status`),
  KEY `idx_part_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clan_score_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `clan_id` int(10) UNSIGNED NOT NULL,
  `season_id` int(10) UNSIGNED NOT NULL, `user_id` int(10) UNSIGNED DEFAULT NULL,
  `source_type` varchar(50) NOT NULL, `source_id` int(10) UNSIGNED DEFAULT NULL,
  `points` smallint(6) NOT NULL, `reason` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_csl_clan_season` (`clan_id`,`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `xp_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `source_type` varchar(50) NOT NULL, `source_id` int(10) UNSIGNED DEFAULT NULL,
  `xp_amount` smallint(6) NOT NULL, `reason` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_xp_user` (`user_id`), KEY `idx_xp_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=2;

CREATE TABLE `user_badges` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `badge_id` int(10) UNSIGNED NOT NULL, `source_type` varchar(50) DEFAULT NULL,
  `source_id` int(10) UNSIGNED DEFAULT NULL, `awarded_by` int(10) UNSIGNED DEFAULT NULL,
  `awarded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_badges` (`user_id`,`badge_id`), KEY `idx_ub_badge` (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=2;

CREATE TABLE `user_collectibles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `mission_id` int(10) UNSIGNED NOT NULL, `collectible_id` int(10) UNSIGNED NOT NULL,
  `found_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_collectible` (`user_id`,`collectible_id`),
  KEY `idx_uc_user_mission` (`user_id`,`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_progress` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED DEFAULT NULL, `mission_id` int(10) UNSIGNED DEFAULT NULL,
  `progress_key` varchar(100) NOT NULL, `progress_value` int(10) UNSIGNED DEFAULT 0,
  `progress_max` int(10) UNSIGNED DEFAULT 1, `completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_progress_user_game_key` (`user_id`,`game_id`,`progress_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legal_acceptances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('cgu','privacy','cookies') NOT NULL,
  `document_version` varchar(20) NOT NULL DEFAULT '1.0',
  `accepted_at` datetime NOT NULL DEFAULT current_timestamp(), `ip_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`), KEY `idx_la_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=3;

CREATE TABLE `season_trophies` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `season_id` int(10) UNSIGNED NOT NULL,
  `winning_clan_id` int(10) UNSIGNED NOT NULL, `title` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL, `score_final` int(10) UNSIGNED DEFAULT 0,
  `trophy_image` varchar(255) DEFAULT NULL, `awarded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `fk_trophy_season` (`season_id`), KEY `fk_trophy_clan` (`winning_clan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=2;

CREATE TABLE `season_clan_results` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `season_id` int(10) UNSIGNED NOT NULL,
  `clan_id` int(10) UNSIGNED NOT NULL, `rank` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `final_score` int(10) UNSIGNED NOT NULL DEFAULT 0, `members_active` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `participations` int(10) UNSIGNED NOT NULL DEFAULT 0, `xp_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_scr` (`season_id`,`clan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hall_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `item_type` enum('photo','contribution','keto','rando','trophy','member','archive') NOT NULL DEFAULT 'photo',
  `description` text DEFAULT NULL, `user_id` int(10) UNSIGNED DEFAULT NULL,
  `clan_id` int(10) UNSIGNED DEFAULT NULL, `mission_id` int(10) UNSIGNED DEFAULT NULL,
  `game_id` int(10) UNSIGNED DEFAULT NULL, `media_id` int(10) UNSIGNED DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0, `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_hall_slug` (`slug`),
  KEY `idx_hall_type_pub` (`item_type`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=2;

CREATE TABLE `media` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED DEFAULT NULL,
  `file_path` varchar(255) NOT NULL, `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL, `file_size` int(10) UNSIGNED DEFAULT 0,
  `width` smallint(5) UNSIGNED DEFAULT NULL, `height` smallint(5) UNSIGNED DEFAULT NULL,
  `media_type` enum('avatar','mission_photo','hall','system') NOT NULL DEFAULT 'system',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `alt_text` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `comments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `mission_id` int(10) UNSIGNED DEFAULT NULL, `game_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL, `content` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `name` varchar(100) NOT NULL,
  `email` varchar(180) NOT NULL, `subject` varchar(200) DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL, `message` text NOT NULL,
  `status` enum('new','read','archived') NOT NULL DEFAULT 'new',
  `ip_hash` varchar(64) DEFAULT NULL, `user_agent_hash` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_queue` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED DEFAULT NULL,
  `to_email` varchar(255) NOT NULL, `to_name` varchar(120) DEFAULT NULL,
  `template_slug` varchar(80) NOT NULL, `subject` varchar(255) NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `status` enum('pending','sending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0, `last_error` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL, `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_eq_status` (`status`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `community_feed` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` enum('mission_new','mission_complete','badge_unlock','flash_start','flash_end','season_start','season_end','clan_lead','trophy_awarded','collectible_found','rando_done','ktc_win') NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL, `clan_id` int(10) UNSIGNED DEFAULT NULL,
  `mission_id` int(10) UNSIGNED DEFAULT NULL, `badge_id` int(10) UNSIGNED DEFAULT NULL,
  `season_id` int(10) UNSIGNED DEFAULT NULL, `title` varchar(255) NOT NULL,
  `body` varchar(512) DEFAULT NULL, `icon_emoji` varchar(8) NOT NULL DEFAULT '?',
  `link_url` varchar(255) DEFAULT NULL, `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_cf_type` (`event_type`), KEY `idx_cf_created` (`created_at`),
  KEY `idx_cf_pinned` (`is_pinned`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `weather_posts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `zone` enum('bocage','littoral','marais','vendee') NOT NULL DEFAULT 'vendee',
  `title` varchar(255) NOT NULL, `body` text DEFAULT NULL,
  `weather_icon` varchar(8) NOT NULL DEFAULT '?', `temperature` tinyint(4) DEFAULT NULL,
  `weather_condition` varchar(64) DEFAULT NULL,
  `is_alert` tinyint(1) NOT NULL DEFAULT 0, `is_event` tinyint(1) NOT NULL DEFAULT 0,
  `mission_id` int(10) UNSIGNED DEFAULT NULL,
  `published_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL, `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_wp_zone` (`zone`,`published_at`),
  KEY `idx_wp_alert` (`is_alert`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pwa_installs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED DEFAULT NULL,
  `platform` varchar(32) NOT NULL DEFAULT 'unknown', `user_agent` varchar(512) DEFAULT NULL,
  `installed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_pwa_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `flash_participations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `mission_id` int(10) UNSIGNED NOT NULL, `xp_multiplier` decimal(3,1) NOT NULL DEFAULT 1.0,
  `xp_base` int(10) UNSIGNED NOT NULL DEFAULT 0, `xp_bonus` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_fp` (`user_id`,`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rando_completions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `mission_id` int(10) UNSIGNED NOT NULL, `photo_path` varchar(255) DEFAULT NULL,
  `comment` varchar(512) DEFAULT NULL, `distance_km` decimal(5,1) DEFAULT NULL,
  `completed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_rc` (`user_id`,`mission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ktc_answers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `given_answer` enum('a','b','c','d') NOT NULL, `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `xp_earned` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `answered_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_ktc_answer` (`user_id`,`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES DE RÉFÉRENCE
-- ============================================================

INSERT INTO `clans` (`id`,`name`,`slug`,`description`,`mascot_image`,`color_primary`,`color_secondary`,`motto`,`cry`,`is_active`) VALUES
(1,'Clan du Bocage','bocage','Ancrés dans les forêts et bocages vendéens. Discrets, déterminés.','mascotte-bocage.png','#2a9d5c','#1a7a42','La forêt tient debout','Par les chênes et les genêts !',1),
(2,'Clan du Littoral','littoral','Enfants des côtes vendéennes. Curieux, audacieux.','mascotte-littoral.png','#12314e','#163756','Le vent porte la légende','Vents et marées !',1),
(3,'Clan du Marais','marais','Gardiens des marais. Observateurs, patients, profonds.','mascotte-marais.png','#163756','#0d1e2c','L\'eau coule, la mémoire reste','L\'eau coule, la mémoire reste !',1);

INSERT INTO `seasons` (`id`,`title`,`slug`,`color_primary`,`color_secondary`,`emoji`,`description`,`period_label`,`start_date`,`end_date`,`status`,`theme_color`,`main_game_id`) VALUES
(1,'Saison du Réveil','saison-du-reveil','#0c1e2e','#ea5649','🌱','Première saison de la Zone — le réveil vendéen.','Mars à mai','2025-03-01','2025-05-31','archived','#2a9d5c',NULL),
(2,'Camp d\'Eté Zone85','camp-ete-zone85','#0c1e2e','#ea5649','☀️','La grande saison estivale — missions et défi collectif.','Juin à août','2025-06-01','2025-08-31','active','#ea5649',1),
(3,'Saison des Chemins Creux','saison-des-chemins-creux','#0c1e2e','#C9962A','🍂','Automne vendéen — randos, enquêtes et mystères.','Septembre à novembre','2025-09-01','2025-11-30','upcoming','#C9962A',NULL),
(4,'Saison des Veillées','saison-des-veillees','#0c1e2e','#12314e','❄️','Hiver vendéen — récits, patrimoines et veillées.','Décembre à février','2025-12-01','2026-02-28','upcoming','#12314e',NULL);

INSERT INTO `badges` (`id`,`title`,`slug`,`category`,`description`,`icon`,`rarity`,`color_primary`,`condition_type`,`condition_value`) VALUES
(1,'Pionnier de la Zone','pionnier-zone','general','Inscrit parmi les 500 premiers membres','🌱','rare','#ea5649','special',NULL),
(2,'Quiz Addict','quiz-addict','general','10 quiz complétés','🧠','common','#ea5649','mission_success',10),
(3,'Chasseur de Randos','chasseur-randos','rando','5 randos validées','🥾','common','#2a9d5c','mission_success',5),
(4,'Oeil de Faucon','oeil-faucon','general','Photo coup de coeur de l\'équipe','🦅','epic','#ea5649','manual',NULL),
(5,'Enqueteur du Bocage','enqueteur-bocage','culture','Premier KTC résolu','🔍','uncommon','#b8831a','mission_success',1),
(6,'Fidele du Littoral','fidele-littoral','clan','3 saisons consécutives actif','⚓','rare','#12314e','season',3),
(7,'Meteo-guerrier','meteo-guerrier','meteo','10 météo-missions complétées','🌤','common','#ea5649','mission_success',10),
(8,'Legende de la Zone','legende-zone','general','10 000 XP à vie atteints','🏆','legendary','#C9962A','xp_threshold',10000),
(9,'Explorateur Bocage','explorateur-bocage','exploration','A participé à 3 missions Bocage','🌳','common','#2a9d5c','mission_success',3),
(10,'Marin du Littoral','marin-littoral','exploration','A participé à 3 missions Littoral','⚓','common','#12314e','mission_success',3),
(11,'Enfant du Marais','enfant-marais','exploration','A participé à 3 missions Marais','🌿','common','#b8831a','mission_success',3),
(12,'Chasseur Objets','chasseur-objets','exploration','A trouvé 5 objets cachés','🗝️','rare','#ea5649','special',NULL),
(13,'Collecteur Legendaire','collecteur-leg','saison','A trouvé tous les objets d\'une saison','💎','legendary','#9b59b6','special',NULL),
(14,'Zonaute Meteo','zonaute-meteo','meteo','A participé à une mission météo','🌤️','common','#12314e','mission_success',1),
(15,'Randonneur Vendee','randonneur-vendee','rando','A validé sa première rando Zone85','🥾','common','#2a9d5c','mission_success',1),
(16,'KTC Champion','ktc-champion','culture','A répondu correctement à 10 KTC','🥐','rare','#b8831a','special',NULL),
(17,'Guerrier de Saison','guerrier-saison','saison','A terminé une grande mission saisonnière','⚔️','epic','#ea5649','special',NULL),
(18,'Legende du Clan','legende-clan','clan','A été dans le clan gagnant d\'une saison','🏆','legendary','#C9962A','special',NULL),
(19,'Flash Runner','flash-runner','general','A participé à 3 événements flash','⚡','rare','#ea5649','special',NULL);

INSERT INTO `games` (`id`,`season_id`,`title`,`slug`,`description`,`game_type`,`status`,`is_paid`,`is_collective`) VALUES
(1,2,'Camp d\'Ete Zone85','camp-ete-zone85','Le jeu collectif de l\'été vendéen.','seasonal_event','active',0,1),
(2,NULL,'Keto Kole Tche','keto-kole-tche','Objets mystères vendéens.','evergreen','active',0,0),
(3,NULL,'Chasse aux Symboles Caches','chasse-symboles-caches','Trouvez les symboles cachés sur le site.','hidden_hunt','coming_soon',0,0),
(99,NULL,'Les Invisibles','les-invisibles','Jeu narratif premium — 10 chapitres.','premium_game','coming_soon',1,0);

INSERT INTO `email_templates` (`id`,`slug`,`subject`,`description`,`is_active`) VALUES
(1,'welcome','Bienvenue dans la Zone !','Email de bienvenue après inscription',1),
(2,'email_verify','Vérifie ton adresse email — Zone85','Vérification email',1),
(3,'password_reset','Réinitialisation de ton mot de passe','Mot de passe oublié',1),
(4,'badge_unlock','🏅 Tu as débloqué un badge !','Notification badge gagné',1),
(5,'mission_new','🎯 Nouvelle mission disponible !','Annonce nouvelle mission',1),
(6,'mission_complete','✅ Mission accomplie !','Confirmation completion mission',1),
(7,'season_start','🚀 Nouvelle saison Zone85 !','Début de saison',1),
(8,'digest_hebdo','📋 Tes infos Zone85 de la semaine','Résumé hebdomadaire',1),
(9,'delete_requested','Demande de suppression reçue','Confirmation demande suppression compte',1);

-- Missions de contenu (sans missions test 10-14)
INSERT INTO `missions` (`id`,`game_id`,`season_id`,`title`,`slug`,`mission_type`,`description`,`status`,`is_collective`,`validation_mode`,`requires_answer`,`requires_upload`,`requires_vote`,`requires_code`,`xp_participation`,`xp_success`,`clan_points_participation`,`clan_points_success`,`badge_reward_id`,`start_date`,`end_date`,`display_in_hall`,`xp_multiplier`,`is_flash`,`is_grande_mission`,`cover_emoji`) VALUES
(1,1,2,'Le Grand Defi de l\'Ete','grand-defi-ete','seasonal_collective','La grande mission qui fait avancer la Bataille des Clans.','active',1,'hybrid',0,0,0,0,20,100,5,50,NULL,'2025-06-01','2025-08-31',1,1.0,0,0,'🏆'),
(2,NULL,NULL,'Quiz du moment','quiz-du-moment','quiz','5 questions sur la Vendée mystérieuse.','active',0,'auto',1,0,0,0,5,10,0,2,NULL,NULL,NULL,0,1.0,0,0,'🧠'),
(3,NULL,NULL,'Defi photo — Ete Vendee','defi-photo-ete-vendee','photo_challenge','Capture un coucher de soleil vendéen.','active',0,'manual',0,1,0,0,10,50,1,5,NULL,NULL,'2025-08-31',1,1.0,0,0,'📸'),
(4,2,NULL,'Keto Kole Tche 14','ktc-14','keto_kole_tche','Objet mystère vendéen — 3 indices disponibles.','active',0,'manual',1,0,0,0,10,50,2,10,NULL,NULL,NULL,1,1.0,0,0,'🥐'),
(5,NULL,NULL,'Meteo-mission Canicule','meteo-canicule','weather_mission','Canicule en Vendée : partage ta technique survivaliste estivale.','active',0,'auto',1,0,0,0,15,15,1,1,NULL,NULL,NULL,0,1.0,0,0,'🌤️'),
(6,NULL,NULL,'Avis rando La Marche des Marais','rando-marche-des-marais','rando','La Tranche-sur-Mer · 12 km · 3h30. Donne ton avis.','active',0,'auto',1,0,0,0,15,25,2,5,NULL,NULL,NULL,0,1.0,0,0,'🥾'),
(7,NULL,NULL,'Vote de la semaine','vote-semaine','vote','Quelle est la meilleure photo vendéenne de la semaine ?','active',0,'auto',0,0,1,0,2,2,0,0,NULL,NULL,NULL,0,1.0,0,0,'🗳️'),
(8,NULL,NULL,'Enquete Village','enquete-village-personnage','investigation','Portrait mystère d\'un personnage de l\'histoire vendéenne.','active',0,'manual',1,0,0,0,10,80,2,15,NULL,NULL,NULL,1,1.0,0,0,'🔍'),
(9,NULL,NULL,'Quiz Marais Poitevin','quiz-marais-poitevin','quiz','Faune et flore du Marais Poitevin — 5 questions.','archived',0,'auto',1,0,0,0,5,10,0,2,NULL,'2025-09-01',NULL,0,1.0,0,0,'🧠');

INSERT INTO `ktc_questions` (`id`,`category`,`question`,`answer_a`,`answer_b`,`answer_c`,`answer_d`,`correct`,`explanation`,`difficulty`,`xp_reward`,`is_active`) VALUES
(1,'expression','Que signifie "Mogette" en Vendee ?','Un marecage','Un haricot blanc','Une tempete','Un canard','b','La mogette est le haricot blanc emblematique de la Vendee.',1,5,1),
(2,'expression','Que veut dire etre chouan ?','Etre courageux','Etre vendeen rebelle','Etre fatigue','Etre en retard','b','Les Chouans etaient les insurges vendeens contre la Revolution.',2,8,1),
(3,'quiz','Quelle est la prefecture de la Vendee ?','Les Sables-d\'Olonne','Fontenay-le-Comte','La Roche-sur-Yon','Lucon','c','La Roche-sur-Yon est la prefecture, fondee sous Napoleon.',1,5,1),
(4,'nature','Quel est l\'arbre emblematique du bocage vendeen ?','Le chene','Le charme','Le frene','Le noyer','a','Le chene pedoncule est l\'arbre roi du bocage vendeen.',1,5,1),
(5,'gastronomie','Quelle specialite vendéenne est liee aux Sables ?','La brioche','Le jambon','La bouillie','Le prefou','d','Le prefou est un pain a l\'ail et au beurre, specialite vendeenne.',1,5,1),
(6,'histoire','Qui etait Charette de la Contrie ?','Un navigateur','Un chef chouan','Un eveque','Un marchand','b','Charette fut l\'un des principaux chefs militaires de la Vendee.',2,8,1),
(7,'devinette','Je couvre les marais, on me recolte a maree basse en Vendee. Qui suis-je ?','La salicorne','La moule de bouchot','L\'anguille','Le mulet','b','La moule de bouchot, elevee sur des pieux dans les marais vendeens.',2,8,1);

INSERT INTO `season_trophies` (`id`,`season_id`,`winning_clan_id`,`title`,`description`,`score_final`,`awarded_at`) VALUES
(1,1,2,'Trophee Saison du Reveil 2025','Le Clan du Littoral s\'impose des la premiere saison.',12840,'2025-05-31 23:59:59');

INSERT INTO `hall_items` (`id`,`title`,`slug`,`item_type`,`description`,`clan_id`,`is_featured`,`published_at`) VALUES
(1,'Trophee Saison du Reveil 2025','trophee-saison-reveil','trophy','Victoire du Clan du Littoral',2,1,'2025-05-31 23:59:59');

-- ============================================================
-- UTILISATEUR ADMIN : Mickaël Lorieau
-- Email : lorieau.mickael@gmail.com | Mot de passe : aaaaaaaa
-- ============================================================
INSERT INTO `users` (`id`,`email`,`password_hash`,`pseudo`,`first_name`,`last_name`,`clan_id`,`avatar_type`,`avatar_config`,`xp_total`,`level`,`newsletter_optin`,`notif_missions`,`notif_saisons`,`notif_clan`,`notif_push`,`digest_hebdo`,`accepted_cgu_at`,`accepted_privacy_at`,`role`,`status`,`login_count`,`created_at`) VALUES
(1,'lorieau.mickael@gmail.com','$2b$10$sljAJRrkNxVzh43oqZ32Je1BrD/edtkAvv9b1BLnXIohMPnnRNX1S','Micka','Mickaël','LORIEAU',1,'preset','{"emoji":"🧭"}',50,1,1,1,1,1,0,0,NOW(),NOW(),'admin','active',0,NOW());

INSERT INTO `legal_acceptances` (`id`,`user_id`,`document_type`,`document_version`,`accepted_at`) VALUES
(1,1,'cgu','cgu_v1',NOW()),
(2,1,'privacy','confidentialite_v1',NOW());

INSERT INTO `user_badges` (`id`,`user_id`,`badge_id`,`source_type`,`awarded_at`) VALUES
(1,1,1,'registration',NOW());

INSERT INTO `xp_logs` (`id`,`user_id`,`source_type`,`source_id`,`xp_amount`,`reason`) VALUES
(1,1,'registration',1,50,'Bienvenue dans la Zone');

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIN — Zone85 V11 propre
-- ============================================================
