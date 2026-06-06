-- Zone85 — Migration échos v2
-- Nouvelles rubriques définitives + galerie + vidéo YouTube
-- Exécuter une seule fois. echo-edit.php gère les colonnes gallery/video_url automatiquement.

-- ── 1. Nouvelles colonnes ────────────────────────────────────────
ALTER TABLE articles
    ADD COLUMN gallery   JSON         NULL AFTER cover_image,
    ADD COLUMN video_url VARCHAR(500) NULL AFTER gallery;

-- ── 2. Transition des rubriques ───────────────────────────────────
-- Anciens slugs → nouveaux slugs
UPDATE articles SET rubrique = 'les-ovnis'      WHERE rubrique = 'ovnis';
UPDATE articles SET rubrique = 'les-invisibles' WHERE rubrique = 'chez-nous';
UPDATE articles SET rubrique = 'actualite'      WHERE rubrique IN ('communaute', 'archives');

-- ── 3. Nouveau ENUM ────────────────────────────────────────────────
ALTER TABLE articles
    MODIFY COLUMN rubrique
    ENUM('les-invisibles','deux-minutes','les-ovnis','actualite','chemins','evenements')
    NOT NULL DEFAULT 'actualite';
