-- ============================================================
-- Migration 032 — Traçabilité XP commentaires Échos
-- ============================================================

ALTER TABLE article_comments
    ADD COLUMN IF NOT EXISTS xp_awarded TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 si les +5 XP ont été attribués pour ce commentaire'
    AFTER status;

-- Marquer les commentaires existants dont l'auteur a déjà
-- au moins un commentaire antérieur comme "premier commentaire sans XP"
-- (valeur 0 par défaut = indéterminé, l'admin gère manuellement)
