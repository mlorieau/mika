-- ============================================================
-- Migration 031 — V13.1 : Google Search Console verification
-- ============================================================

INSERT IGNORE INTO `settings` (`setting_key`,`setting_type`,`category`,`label`,`description`,`placeholder`,`is_sensitive`,`sort_order`) VALUES
('google_search_console_verification', 'text', 'analytics',
 'Code vérification Google Search Console',
 'Token de vérification fourni par Google Search Console (balise meta google-site-verification). Injecté automatiquement dans le <head> de toutes les pages.',
 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
 0, 20);
