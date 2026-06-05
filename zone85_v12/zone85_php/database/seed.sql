-- ZONE85 — Données de test (seed) v1
-- ATTENTION : données fictives uniquement — emails @example.test
-- Usage : mysql -u zone85_user -p zone85 < seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

USE zone85;

-- ============================================================
-- CLANS (3)
-- ============================================================
INSERT INTO clans (id, name, slug, description, mascot_image, color_primary, color_secondary, motto, cry) VALUES
(1, 'Clan du Bocage',   'bocage',   'Ancrés dans les forêts et bocages vendéens. Discrets, déterminés.', 'mascotte-bocage.png',   '#2a9d5c', '#1a7a42', 'La forêt tient debout',     'Par les chênes et les genêts !'),
(2, 'Clan du Littoral', 'littoral', 'Enfants des côtes vendéennes. Curieux, audacieux.',                 'mascotte-littoral.png', '#12314e', '#163756', 'Le vent porte la légende',  'Vents et marées !'),
(3, 'Clan du Marais',   'marais',   'Gardiens des marais. Observateurs, patients, profonds.',             'mascotte-marais.png',   '#163756', '#0d1e2c', 'L\'eau coule, la mémoire reste', 'L\'eau coule, la mémoire reste !');

-- ============================================================
-- SAISONS (4) — main_game_id NULL pour l'instant (FK ajoutée après games)
-- ============================================================
INSERT INTO seasons (id, title, slug, description, period_label, start_date, end_date, status, theme_color) VALUES
(1, 'Saison du Réveil',          'saison-du-reveil',         'Première saison de la Zone — le réveil vendéen.',         'Mars à mai',          '2025-03-01', '2025-05-31', 'archived', '#2a9d5c'),
(2, 'Camp d\'Eté Zone85',        'camp-ete-zone85',          'La grande saison estivale — missions et défi collectif.',  'Juin à août',         '2025-06-01', '2025-08-31', 'active',   '#ea5649'),
(3, 'Saison des Chemins Creux',  'saison-des-chemins-creux', 'Automne vendéen — randos, enquêtes et mystères.',         'Septembre à novembre','2025-09-01', '2025-11-30', 'upcoming', '#C9962A'),
(4, 'Saison des Veillées',       'saison-des-veillees',      'Hiver vendéen — récits, patrimoines et veillées.',        'Décembre à février',  '2025-12-01', '2026-02-28', 'upcoming', '#12314e');

-- ============================================================
-- GAMES (3 + 1 premium)
-- ============================================================
INSERT INTO games (id, season_id, title, slug, description, game_type, status, is_paid, is_collective, start_date, end_date) VALUES
(1,  2,    'Camp d\'Eté Zone85',          'camp-ete-zone85',        'Le jeu collectif de l\'été vendéen. Tous clans confondus.',   'seasonal_event', 'active',      0, 1, '2025-06-01', '2025-08-31'),
(2,  NULL, 'Kéto Kolé Tché',              'keto-kole-tche',         'Objets mystères vendéens — devinez avant les autres.',        'evergreen',      'active',      0, 0, NULL, NULL),
(3,  NULL, 'Chasse aux Symboles Cachés',  'chasse-symboles-caches', 'Trouvez les symboles cachés sur le site et dans la Vendée.',  'hidden_hunt',    'coming_soon', 0, 0, NULL, NULL),
(99, NULL, 'Les Invisibles',              'les-invisibles',         'Jeu narratif premium — 10 chapitres, 1 gratuit.',             'premium_game',   'coming_soon', 1, 0, NULL, NULL);

-- Liaison saison active ↔ jeu principal
UPDATE seasons SET main_game_id = 1 WHERE id = 2;

-- ============================================================
-- BADGES (8)
-- ============================================================
INSERT INTO badges (id, title, slug, description, icon, rarity, condition_type, condition_value) VALUES
(1, 'Pionnier de la Zone',  'pionnier-zone',    'Inscrit parmi les 500 premiers membres',    '🌱', 'rare',      'special',         NULL),
(2, 'Quiz Addict',          'quiz-addict',      '10 quiz complétés',                         '🧠', 'common',    'mission_success', 10),
(3, 'Chasseur de Randos',   'chasseur-randos',  '5 avis de rando postés',                    '🥾', 'common',    'mission_success', 5),
(4, 'Oeil de Faucon',       'oeil-faucon',      'Photo coup de coeur de l\'équipe',          '🦅', 'epic',      'manual',          NULL),
(5, 'Enquêteur du Bocage',  'enqueteur-bocage', 'Premier Kéto Kolé Tché résolu',             '🔍', 'uncommon',  'mission_success', 1),
(6, 'Fidèle du Littoral',   'fidele-littoral',  '3 saisons consécutives actif',              '⚓', 'rare',      'season',          3),
(7, 'Météo-guerrier',       'meteo-guerrier',   '10 météo-missions complétées',              '🌤', 'common',    'mission_success', 10),
(8, 'Légende de la Zone',   'legende-zone',     '10 000 XP à vie atteints',                 '🏆', 'legendary', 'xp_threshold',    10000);

-- ============================================================
-- MISSIONS (9)
-- ============================================================
INSERT INTO missions (id, game_id, season_id, title, slug, mission_type, description, status, is_collective, validation_mode, requires_answer, requires_upload, requires_vote, xp_participation, xp_success, clan_points_participation, clan_points_success, start_date, end_date, display_in_hall) VALUES
(1, 1,    2,    'Le Grand Défi de l\'Eté',                    'grand-defi-ete',             'seasonal_collective', 'La grande mission qui fait avancer la Bataille des Clans.',               'active',   1, 'hybrid', 0, 0, 0, 20, 100, 5,  50, '2025-06-01', '2025-08-31', 1),
(2, NULL, NULL, 'Quiz du moment',                              'quiz-du-moment',             'quiz',                '5 questions sur la Vendée mystérieuse.',                                  'active',   0, 'auto',   1, 0, 0,  5,  10, 0,   2, NULL,         NULL,         0),
(3, NULL, NULL, 'Défi photo — Eté Vendée',                    'defi-photo-ete-vendee',      'photo_challenge',     'Capture un coucher de soleil vendéen.',                                   'active',   0, 'manual', 0, 1, 0, 10,  50, 1,   5, NULL,         '2025-08-31', 1),
(4, 2,    NULL, 'Kéto Kolé Tché #14',                         'ktc-14',                     'keto_kole_tche',      'Objet mystère vendéen — 3 indices disponibles.',                          'active',   0, 'manual', 1, 0, 0, 10,  50, 2,  10, NULL,         NULL,         1),
(5, NULL, NULL, 'Météo-mission — Canicule',                   'meteo-canicule',             'weather_mission',     'Canicule en Vendée : partage ta technique survivaliste estivale.',        'active',   0, 'auto',   1, 0, 0, 15,  15, 1,   1, NULL,         NULL,         0),
(6, NULL, NULL, 'Avis rando — La Marche des Marais',          'rando-marche-des-marais',    'rando',               'La Tranche-sur-Mer · 12 km · 3h30. Donne ton avis.',                     'active',   0, 'auto',   1, 0, 0, 15,  25, 2,   5, NULL,         NULL,         0),
(7, NULL, NULL, 'Vote de la semaine',                         'vote-semaine',               'vote',                'Quelle est la meilleure photo vendéenne de la semaine ?',                 'active',   0, 'auto',   0, 0, 1,  2,   2, 0,   0, NULL,         NULL,         0),
(8, NULL, NULL, 'Enquête Village — Qui est ce personnage ?',  'enquete-village-personnage', 'investigation',       'Portrait mystère d\'un personnage de l\'histoire vendéenne.',             'active',   0, 'manual', 1, 0, 0, 10,  80, 2,  15, NULL,         NULL,         1),
(9, NULL, NULL, 'Quiz Marais Poitevin',                       'quiz-marais-poitevin',       'quiz',                'Faune et flore du Marais Poitevin — 5 questions.',                       'archived', 0, 'auto',   1, 0, 0,  5,  10, 0,   2, '2025-09-01', NULL,         0);

-- ============================================================
-- UTILISATEURS FICTIFS (5) — emails @example.test uniquement
-- ============================================================
INSERT INTO users (id, email, password_hash, pseudo, first_name, last_name, clan_id, avatar_type, bio, xp_total, level, role, status, created_at) VALUES
(1, 'brume@example.test',  '$2y$12$placeholder_hash_do_not_use', 'BrumeDuMarais', 'Léa',    'M.', 3,    'preset', 'Gardienne des marais vendéens.',                    22140, 12, 'member', 'active', '2024-03-01 10:00:00'),
(2, 'dune@example.test',   '$2y$12$placeholder_hash_do_not_use', 'DuneRider85',   'Tom',    'D.', 2,    'preset', 'Surfeur des dunes et explorateur du littoral.',     18430, 11, 'member', 'active', '2024-03-05 09:30:00'),
(3, 'foret@example.test',  '$2y$12$placeholder_hash_do_not_use', 'ForetRunner',   'Marc',   'B.', 1,    'preset', 'Coureur des chemins bocagers.',                     15820, 10, 'member', 'active', '2024-03-10 14:00:00'),
(4, 'sophie@example.test', '$2y$12$placeholder_hash_do_not_use', 'SophieVM',      'Sophie', 'V.', 2,    'preset', 'Exploratrice littorale et amateure de KTC.',        3400,  7,  'member', 'active', '2024-06-15 11:00:00'),
(5, 'admin@example.test',  '$2y$12$placeholder_hash_do_not_use', 'AdminZone85',   'Admin',  'Z.', NULL, 'preset', NULL,                                                0,     1,  'admin',  'active', '2024-01-01 00:00:00');

-- ============================================================
-- XP LOGS
-- ============================================================
INSERT INTO xp_logs (user_id, source_type, source_id, xp_amount, reason, created_at) VALUES
(1, 'participation', 1,    20,  'seasonal_collective_participation', '2025-06-05 10:00:00'),
(1, 'participation', 1,    100, 'seasonal_collective_success',       '2025-06-10 14:00:00'),
(2, 'participation', 2,    5,   'quiz_participation',                '2025-06-08 09:00:00'),
(2, 'participation', 2,    10,  'quiz_success',                      '2025-06-08 09:05:00'),
(3, 'participation', 4,    10,  'ktc_participation',                 '2025-06-12 16:00:00'),
(4, 'registration',  NULL, 50,  'welcome_bonus',                     '2024-06-15 11:00:00');

-- ============================================================
-- CLAN SCORE LOGS
-- ============================================================
INSERT INTO clan_score_logs (clan_id, season_id, user_id, source_type, source_id, points, reason, created_at) VALUES
(3, 2, 1, 'participation', 1, 50, 'seasonal_collective_success', '2025-06-10 14:00:00'),
(2, 2, 2, 'participation', 2, 2,  'quiz_success',                '2025-06-08 09:05:00'),
(1, 2, 3, 'participation', 4, 10, 'ktc_participation',           '2025-06-12 16:00:00');

-- ============================================================
-- TROPHÉE ARCHIVÉ — Saison 1 (Réveil)
-- ============================================================
INSERT INTO season_trophies (season_id, winning_clan_id, title, description, score_final, awarded_at) VALUES
(1, 2, 'Trophée de la Saison du Réveil 2025', 'Le Clan du Littoral s\'impose dès la première saison.', 12840, '2025-05-31 23:59:59');

-- ============================================================
-- USER BADGES
-- ============================================================
INSERT INTO user_badges (user_id, badge_id, source_type, awarded_at) VALUES
(1, 1, 'registration', '2024-03-01 10:00:00'),
(2, 1, 'registration', '2024-03-05 09:30:00'),
(3, 1, 'registration', '2024-03-10 14:00:00'),
(4, 1, 'registration', '2024-06-15 11:00:00');

-- ============================================================
-- HALL ITEMS
-- ============================================================
INSERT INTO hall_items (title, slug, item_type, description, user_id, clan_id, is_featured, published_at) VALUES
('Coucher sur la Baie de l\'Aiguillon', 'photo-baie-aiguillon',  'photo',  'Photo coup de coeur de MarcelBocat',   3,    1, 1, '2025-06-15 12:00:00'),
('KTC #12 — La baratte à beurre',       'ktc-12-baratte-beurre', 'keto',   'Objet résolu par SophieVM',            4,    2, 1, '2025-06-10 10:00:00'),
('Trophée Saison du Réveil 2025',       'trophee-saison-reveil', 'trophy', 'Victoire du Clan du Littoral',         NULL, 2, 1, '2025-05-31 23:59:59');

SET FOREIGN_KEY_CHECKS = 1;
