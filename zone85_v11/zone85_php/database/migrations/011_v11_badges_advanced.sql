-- ============================================================
-- Migration 011 — V11 : Badges avancés (catégories + raretés)
-- ============================================================

-- Enrichissement badges
ALTER TABLE badges
    ADD COLUMN IF NOT EXISTS category   ENUM('exploration','clan','saison','meteo','rando','culture','invisible','general')
                                        NOT NULL DEFAULT 'general' AFTER slug,
    ADD COLUMN IF NOT EXISTS rarity     ENUM('commun','rare','epique','legendaire')
                                        NOT NULL DEFAULT 'commun' AFTER category,
    ADD COLUMN IF NOT EXISTS season_id  INT UNSIGNED NULL AFTER rarity,
    ADD COLUMN IF NOT EXISTS is_hidden  TINYINT(1)   NOT NULL DEFAULT 0 AFTER season_id,
    ADD COLUMN IF NOT EXISTS unlock_condition TEXT    NULL AFTER is_hidden,
    ADD COLUMN IF NOT EXISTS color_primary VARCHAR(7) NOT NULL DEFAULT '#ea5649' AFTER unlock_condition,
    ADD COLUMN IF NOT EXISTS icon_emoji VARCHAR(8)   NULL AFTER color_primary;

-- Badges supplémentaires V11 (exemples — à compléter via admin)
-- Note : la colonne d'affichage s'appelle "title" dans le schéma Zone85
INSERT IGNORE INTO badges (title, slug, description, category, rarity, icon_emoji, color_primary) VALUES
    ('Pionnier Zone85',      'pionnier-zone',      'Premier membre inscrit sur Zone85',            'general',    'legendaire', '🌟', '#ea5649'),
    ('Explorateur Bocage',   'explorateur-bocage', 'A participe a 3 missions Bocage',              'exploration', 'commun',    '🌳', '#2a9d5c'),
    ('Marin du Littoral',    'marin-littoral',     'A participe a 3 missions Littoral',            'exploration', 'commun',    '⚓', '#12314e'),
    ('Enfant du Marais',     'enfant-marais',      'A participe a 3 missions Marais',              'exploration', 'commun',    '🌿', '#b8831a'),
    ('Chasseur Objets',      'chasseur-objets',    'A trouve 5 objets caches',                     'exploration', 'rare',      '🗝️', '#ea5649'),
    ('Collecteur Legendaire','collecteur-leg',     'A trouve tous les objets une saison',          'saison',      'legendaire','💎', '#9b59b6'),
    ('Zonaute Meteo',        'zonaute-meteo',      'A participe a une mission meteo',              'meteo',       'commun',    '🌤️', '#12314e'),
    ('Randonneur Vendee',    'randonneur-vendee',  'A valide sa premiere rando Zone85',            'rando',       'commun',    '🥾', '#2a9d5c'),
    ('KTC Champion',         'ktc-champion',       'A repondu correctement a 10 KTC',              'culture',     'rare',      '🥐', '#b8831a'),
    ('Guerrier de Saison',   'guerrier-saison',    'A termine une grande mission saisonniere',     'saison',      'epique',    '⚔️', '#ea5649'),
    ('Legende du Clan',      'legende-clan',       'A ete dans le clan gagnant une saison',        'clan',        'legendaire','🏆', '#C9962A'),
    ('Flash Runner',         'flash-runner',       'A participe a 3 evenements flash',             'general',     'rare',      '⚡', '#ea5649');

-- Index optimisation
ALTER TABLE badges
    ADD INDEX IF NOT EXISTS idx_badges_cat   (category),
    ADD INDEX IF NOT EXISTS idx_badges_rar   (rarity),
    ADD INDEX IF NOT EXISTS idx_badges_season(season_id);
