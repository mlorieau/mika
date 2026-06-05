-- ============================================================
-- Migration 014 — V10.1 : Table settings (intégrations tierces)
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         NULL,
  `setting_type`  ENUM('text','password','boolean','select','number','textarea','url','email','color')
                              NOT NULL DEFAULT 'text',
  `category`      VARCHAR(50)  NOT NULL DEFAULT 'general',
  `label`         VARCHAR(200) NOT NULL,
  `description`   VARCHAR(500) NULL,
  `placeholder`   VARCHAR(255) NULL,
  `is_sensitive`  TINYINT(1)   NOT NULL DEFAULT 0,  -- masqué dans l'UI si 1
  `is_required`   TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`),
  KEY `idx_settings_cat` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Paramètres généraux ───────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('site_name',        'text',    'general', 'Nom du site',        'Nom affiché dans les emails et métadonnées.',       'ZONE85',           0, 1),
('site_tagline',     'text',    'general', 'Slogan',             'Slogan du site.',                                   'La Vendée qui joue...', 0, 2),
('site_email',       'email',   'general', 'Email de contact',   'Adresse email publique du site.',                   'contact@zone85.fr', 0, 3),
('site_url',         'url',     'general', 'URL du site',        'URL de production sans slash final.',               'https://www.zone85.fr', 0, 4),
('maintenance_mode', 'boolean', 'general', 'Mode maintenance',   'Affiche une page de maintenance aux visiteurs.',    NULL,               0, 5),
('beta_mode',        'boolean', 'general', 'Mode bêta privée',   'Restreint l\'accès aux membres invités uniquement.', NULL,              0, 6);

-- ── Brevo (emailing transactionnel) ──────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('brevo_enabled',    'boolean',  'brevo', 'Activer Brevo',       'Active l\'envoi d\'emails via Brevo API.',           NULL,              0, 1),
('brevo_api_key',    'password', 'brevo', 'Clé API Brevo',       'Clé API Brevo (xkeysib-...). Visible uniquement à la saisie.', 'xkeysib-...', 1, 2),
('brevo_from_email', 'email',    'brevo', 'Expéditeur (email)',  'Adresse email de l\'expéditeur.',                   'noreply@zone85.fr', 0, 3),
('brevo_from_name',  'text',     'brevo', 'Expéditeur (nom)',    'Nom affiché comme expéditeur.',                     'ZONE85',           0, 4),
('brevo_smtp_host',  'text',     'brevo', 'Serveur SMTP',        'Optionnel : serveur SMTP Brevo (smtp-relay.brevo.com).', 'smtp-relay.brevo.com', 0, 5),
('brevo_smtp_port',  'number',   'brevo', 'Port SMTP',           'Port SMTP (587 recommandé).',                       '587',              0, 6),
('brevo_smtp_login', 'email',    'brevo', 'Login SMTP',          'Votre adresse email Brevo pour le SMTP.',           NULL,               0, 7),
('brevo_smtp_pass',  'password', 'brevo', 'Mot de passe SMTP',   'Clé API comme mot de passe SMTP.',                  NULL,               1, 8);

-- ── Analytics GA4 ────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('ga4_enabled',      'boolean', 'analytics', 'Activer Google Analytics 4', 'Active le snippet GA4 sur toutes les pages.', NULL,             0, 1),
('ga4_measurement_id','text',   'analytics', 'ID de mesure GA4',  'Format : G-XXXXXXXXXX.',                             'G-XXXXXXXXXX',    0, 2),
('ga4_debug_mode',   'boolean', 'analytics', 'Mode debug GA4',    'Active debug_mode dans GA4 (dev uniquement).',       NULL,              0, 3);

-- ── Analytics Matomo ─────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('matomo_enabled',   'boolean', 'analytics', 'Activer Matomo',    'Alternative open-source à GA4.',                    NULL,              0, 10),
('matomo_url',       'url',     'analytics', 'URL Matomo',        'URL de votre instance Matomo.',                     'https://matomo.votredomaine.fr/', 0, 11),
('matomo_site_id',   'number',  'analytics', 'ID site Matomo',    'Identifiant du site dans Matomo.',                  '1',               0, 12);

-- ── PWA ──────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('pwa_enabled',      'boolean', 'pwa', 'Activer la PWA',          'Active le manifest, SW et le bouton d\'installation.', NULL,            0, 1),
('pwa_app_name',     'text',    'pwa', 'Nom de l\'app',           'Nom complet affiché lors de l\'installation.',      'ZONE85',          0, 2),
('pwa_short_name',   'text',    'pwa', 'Nom court',               'Nom sous l\'icône sur l\'écran d\'accueil (max 12 chars).', 'Zone85',   0, 3),
('pwa_theme_color',  'color',   'pwa', 'Couleur du thème',        'Couleur de la barre de statut mobile.',             '#0c1e2e',         0, 4),
('pwa_bg_color',     'color',   'pwa', 'Couleur de fond',         'Couleur du fond au démarrage (splash screen).',     '#f8f4ef',         0, 5),
('pwa_display',      'select',  'pwa', 'Mode d\'affichage',       'standalone = plein écran sans navigateur.',         'standalone',      0, 6),
('pwa_install_delay','number',  'pwa', 'Délai avant pop-up (visites)', 'Nombre de visites avant d\'afficher le pop-up.', '2',              0, 7);

-- ── Réseaux sociaux ──────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('social_instagram',  'url',   'social', 'Instagram',            'Lien vers le compte Instagram Zone85.',             'https://instagram.com/zone85_fr', 0, 1),
('social_facebook',   'url',   'social', 'Facebook',             'Lien vers la page Facebook Zone85.',                'https://facebook.com/zone85',     0, 2),
('social_youtube',    'url',   'social', 'YouTube',              'Lien vers la chaîne YouTube.',                      NULL,                              0, 3),
('social_twitter',    'url',   'social', 'X / Twitter',          'Lien vers le compte X (Twitter).',                  NULL,                              0, 4);

-- ── Notifications Push (VAPID — future) ──────────────────────
INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('vapid_public_key',  'text',     'push', 'Clé publique VAPID',  'Clé publique pour les notifications push Web.',      NULL,             0, 1),
('vapid_private_key', 'password', 'push', 'Clé privée VAPID',   'Clé privée VAPID (ne jamais partager).',             NULL,             1, 2),
('vapid_subject',     'email',    'push', 'Sujet VAPID (email)', 'Email ou URL de contact pour les notifications.',    'mailto:contact@zone85.fr', 0, 3),
('push_enabled',      'boolean',  'push', 'Activer Push',        'Active les notifications push Web (VAPID requis).',  NULL,             0, 4);
