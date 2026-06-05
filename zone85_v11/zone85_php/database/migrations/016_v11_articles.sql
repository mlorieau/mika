-- ============================================================
-- Migration 016 — V11 : Table articles (Les Échos)
-- ============================================================

CREATE TABLE IF NOT EXISTS `articles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(255) NOT NULL,
  `slug`         VARCHAR(255) NOT NULL,
  `rubrique`     ENUM('ovnis','deux-minutes','chez-nous','chemins','communaute','archives')
                 NOT NULL DEFAULT 'ovnis',
  `excerpt`      TEXT NULL,
  `body`         LONGTEXT NULL,
  `cover_image`  VARCHAR(255) NULL,
  `author_name`  VARCHAR(100) NULL DEFAULT 'Équipe Zone85',
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_slug` (`slug`),
  KEY `idx_articles_status`    (`status`, `published_at`),
  KEY `idx_articles_rubrique`  (`rubrique`),
  KEY `idx_articles_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed : 3 articles de démonstration (status=published)
INSERT IGNORE INTO `articles`
  (`title`,`slug`,`rubrique`,`excerpt`,`body`,`author_name`,`status`,`published_at`)
VALUES
(
  'La mogette : objet de tous les mystères',
  'la-mogette-objet-de-tous-les-mysteres',
  'ovnis',
  'La mogette vendéenne n\'est pas qu\'un haricot. C\'est un symbole, une identité, un jeu de piste que la Zone se propose de décrypter mission après mission.',
  '<p>La mogette vendéenne n\'est pas qu\'un haricot blanc. Derrière ce légume discret se cache toute une philosophie du territoire. Cultivée depuis le XVIIe siècle dans les terres vendéennes, la mogette a traversé les guerres, les saisons et les modes alimentaires pour s\'imposer comme l\'emblème gustatif d\'une région entière.</p><p>Dans la Zone, elle est devenue bien autre chose : un symbole de jeu, de mystère, de complicité. Les mogettes cachées dans les jeux de piste du site ne sont pas là par hasard. Elles rappellent que Zone85 joue avec les codes de son territoire.</p><p>La prochaine fois que vous en trouvez une cachée dans une page du site, pensez à elle : petite, blanche, discrète... et impossible à ignorer.</p>',
  'Équipe Zone85',
  'published',
  NOW()
),
(
  'Kéto Kolé Tché, c\'est quoi exactement ?',
  'keto-kole-tche-cest-quoi-exactement',
  'deux-minutes',
  'En vendéen populaire, "Kéto Kolé Tché" signifie littéralement "Qu\'est-ce que c\'est que ça ?" — l\'expression parfaite pour notre jeu de devinettes culturelles.',
  '<p>Vous avez sans doute remarqué la section KTC sur Zone85. Mais d\'où vient ce nom étrange ? "Kéto Kolé Tché" est une déformation phonétique de l\'expression vendéenne populaire "Qu\'est-ce que c\'est que ça ?" prononcée très vite, à la manière dont on parle dans les marchés de Montaigu ou de Fontenay.</p><p>C\'est exactement l\'état d\'esprit du jeu : voir quelque chose, ne pas savoir ce que c\'est, chercher, trouver. Un objet mystère par semaine. Des indices. Une réponse collective.</p><p>Si vous n\'avez pas encore essayé, rendez-vous dans la section KTC. Bonne chance.</p>',
  'Équipe Zone85',
  'published',
  NOW()
),
(
  'Le bocage vendéen vu depuis les chemins creux',
  'le-bocage-vendeen-vu-depuis-les-chemins-creux',
  'chemins',
  'Entre les haies centenaires et les sentes oubliées, le bocage cache des histoires que même les cartes ne montrent pas.',
  '<p>Il faut avoir marché dans un chemin creux vendéen pour comprendre ce que signifie "appartenir à un territoire". Ces couloirs de terre et de racines, parfois si profonds qu\'on en perd le ciel de vue, sont les artères d\'un bocage vivant.</p><p>Le Clan du Bocage les connaît bien. Ses membres arpentent ces chemins depuis des générations, y trouvant des champignons, des sources, des menhirs oubliés et parfois — si l\'on en croit certains anciens — des objets qui n\'auraient pas dû se trouver là.</p><p>Zone85 a décidé d\'explorer ces chemins via ses missions de rando. Rejoignez le Clan du Bocage pour participer aux prochaines sorties.</p>',
  'Équipe Zone85',
  'published',
  NOW()
);
