-- Zone85 — Migration : table pages (mini-CMS)
-- Exécuter une seule fois en production.
-- page-edit.php exécute aussi CREATE TABLE IF NOT EXISTS au démarrage.

CREATE TABLE IF NOT EXISTS pages (
    id               INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    slug             VARCHAR(160) NOT NULL,
    title            VARCHAR(255) NOT NULL DEFAULT '',
    meta_title       VARCHAR(255) NOT NULL DEFAULT '',
    meta_description VARCHAR(320) NOT NULL DEFAULT '',
    hero_title       VARCHAR(255) NOT NULL DEFAULT '',
    hero_subtitle    VARCHAR(400) NOT NULL DEFAULT '',
    hero_image       VARCHAR(400) NOT NULL DEFAULT '',
    content_blocks   JSON                  DEFAULT NULL,
    status           ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
