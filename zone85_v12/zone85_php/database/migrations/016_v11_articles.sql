-- ============================================================
-- Migration 016 -- V11 : Table articles (Les Echos)
-- ============================================================

CREATE TABLE IF NOT EXISTS `articles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(255) NOT NULL,
  `slug`         VARCHAR(255) NOT NULL,
  `rubrique`     ENUM('ovnis','deux-minutes','chez-nous','chemins','communaute','archives')
                 NOT NULL DEFAULT 'ovnis',
  `season_id`    INT UNSIGNED NULL,
  `excerpt`      TEXT NULL,
  `body`         LONGTEXT NULL,
  `cover_image`  VARCHAR(255) NULL,
  `author_name`  VARCHAR(100) NULL DEFAULT 'Equipe Zone85',
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_slug` (`slug`),
  KEY `idx_articles_status`    (`status`, `published_at`),
  KEY `idx_articles_rubrique`  (`rubrique`),
  KEY `idx_articles_season`    (`season_id`),
  KEY `idx_articles_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed : 3 articles de demonstration
-- Note : les apostrophes et accents sont echappes pour eviter les erreurs SQL
INSERT IGNORE INTO `articles`
  (`title`,`slug`,`rubrique`,`excerpt`,`body`,`author_name`,`status`,`published_at`)
VALUES
(
  'La mogette : l''objet de tous les mysteres',
  'la-mogette-objet-de-tous-les-mysteres',
  'ovnis',
  'La mogette vendeenne n''est pas qu''un haricot. C''est un symbole, une identite, un jeu de piste que la Zone se propose de decrypter mission apres mission.',
  'La mogette vendeenne n''est pas qu''un haricot blanc. Derriere ce legume discret se cache toute une philosophie du territoire. Cultivee depuis le XVIIe siecle dans les terres vendeennes, la mogette a traverse les guerres, les saisons et les modes alimentaires pour s''imposer comme l''embleme gustatif d''une region entiere.\n\nDans la Zone, elle est devenue bien autre chose : un symbole de jeu, de mystere, de complicite. Les mogettes cachees dans les jeux de piste du site ne sont pas la par hasard. Elles rappellent que Zone85 joue avec les codes de son territoire.\n\nLa prochaine fois que vous en trouvez une cachee dans une page du site, pensez a elle : petite, blanche, discrete... et impossible a ignorer.',
  'Equipe Zone85',
  'published',
  NOW()
),
(
  'Keto Kole Tche, c''est quoi exactement ?',
  'keto-kole-tche-cest-quoi-exactement',
  'deux-minutes',
  'En vendeen populaire, "Keto Kole Tche" signifie "Qu''est-ce que c''est que ca ?" -- l''expression parfaite pour notre jeu de devinettes culturelles.',
  'Vous avez sans doute remarque la rubrique KTC sur Zone85. Mais d''ou vient ce nom etrange ?\n\n"Keto Kole Tche" est une deformation phonetique de l''expression vendeenne populaire "Qu''est-ce que c''est que ca ?" prononcee tres vite, a la maniere dont on parle dans les marches de Montaigu ou de Fontenay.\n\nC''est exactement l''etat d''esprit de la rubrique : voir quelque chose, ne pas savoir ce que c''est, chercher, trouver. Un objet mystere par mois. Un brocanteur ou un passionné local. Une histoire vendéenne a decouvrir semaine apres semaine.\n\nSi vous n''avez pas encore explore la rubrique KTC, c''est le bon moment.',
  'Equipe Zone85',
  'published',
  NOW()
),
(
  'Le bocage vendeen vu depuis les chemins creux',
  'le-bocage-vendeen-vu-depuis-les-chemins-creux',
  'chemins',
  'Entre les haies centenaires et les sentes oubliees, le bocage cache des histoires que meme les cartes ne montrent pas.',
  'Il faut avoir marche dans un chemin creux vendeen pour comprendre ce que signifie appartenir a un territoire. Ces couloirs de terre et de racines, parfois si profonds qu''on en perd le ciel de vue, sont les arteres d''un bocage vivant.\n\nLe Clan du Bocage les connait bien. Ses membres arpentent ces chemins depuis des generations, y trouvant des champignons, des sources, des menhirs oublies et parfois -- si l''on en croit certains anciens -- des objets qui n''auraient pas du se trouver la.\n\nZone85 a decide d''explorer ces chemins via ses missions de rando. Rejoignez le Clan du Bocage pour participer aux prochaines sorties.',
  'Equipe Zone85',
  'published',
  NOW()
);
