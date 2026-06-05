-- ZONE85 — Migration 005 : Moteur Hidden Hunt V1
-- Date : 2026-05-28
-- Prérequis : schema.sql + migrations 001-004 appliquées

USE zone85;

-- ============================================================
-- Table : mission_collectibles
-- Objets cachés liés à une mission hidden_hunt
-- ============================================================
CREATE TABLE IF NOT EXISTS mission_collectibles (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id       INT UNSIGNED NOT NULL,
  collectible_key  VARCHAR(100) NOT NULL,     -- clé technique unique ex: mogette_home_hero
  title            VARCHAR(200) NOT NULL,      -- ex: Mogette du Réveil
  hint             TEXT NULL,                  -- indice optionnel
  page_slug        VARCHAR(50) NOT NULL DEFAULT 'index', -- index|clans|missions|hall|classement|profil
  page_url         VARCHAR(255) NULL,
  position_top     DECIMAL(6,2) DEFAULT 50.00, -- % depuis le haut (viewport)
  position_left    DECIMAL(6,2) DEFAULT 50.00, -- % depuis la gauche (viewport)
  object_image     VARCHAR(255) NULL,          -- chemin relatif image objet
  success_gif      VARCHAR(255) NULL,          -- chemin relatif GIF/image bravo
  success_title    VARCHAR(200) NULL DEFAULT 'Bravo !',
  success_message  TEXT NULL,
  sort_order       TINYINT UNSIGNED DEFAULT 0,
  is_active        TINYINT(1) DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_collectible_key (mission_id, collectible_key),
  KEY idx_coll_mission_page (mission_id, page_slug, is_active),
  CONSTRAINT fk_coll_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : user_collectibles
-- Objets trouvés par chaque joueur
-- ============================================================
CREATE TABLE IF NOT EXISTS user_collectibles (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  mission_id       INT UNSIGNED NOT NULL,
  collectible_id   INT UNSIGNED NOT NULL,
  found_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_collectible (user_id, collectible_id),  -- anti-doublon absolu
  KEY idx_uc_user_mission (user_id, mission_id),
  CONSTRAINT fk_uc_user       FOREIGN KEY (user_id)       REFERENCES users(id)               ON DELETE CASCADE,
  CONSTRAINT fk_uc_mission    FOREIGN KEY (mission_id)    REFERENCES missions(id)             ON DELETE CASCADE,
  CONSTRAINT fk_uc_collectible FOREIGN KEY (collectible_id) REFERENCES mission_collectibles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Dossier uploads/collectibles : créer via PHP (voir README)
-- ============================================================
-- Note sur user_progress :
--   La table user_progress existe déjà (schema.sql).
--   Les chasses hidden_hunt stockent la progression via user_collectibles.
--   user_progress peut être utilisé pour la progression globale par mission.
--   progress_key = 'hh_collected', mission_id = id de la mission hidden_hunt.
-- ============================================================
