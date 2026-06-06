-- Zone85 — Migration échos v3 : lectures + commentaires
-- CREATE TABLE IF NOT EXISTS → sécurisé, idempotent.
-- les-echos-article.php exécute ces CREATE automatiquement.

CREATE TABLE IF NOT EXISTS article_reads (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    read_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_read (article_id, user_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status     ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
    KEY idx_article (article_id, status, created_at),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
