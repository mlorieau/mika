# Zone85 V10.2 — État du projet avant bêta privée

> Audit honnête basé sur lecture du code source V10.2/V11.  
> Référence : `README_AUDIT_V10.md`, `includes/functions.php`, `assets/js/pwa-install.js`, `manifest.json`.  
> Date : 2026-05-29

---

## ✅ Fonctionnel et stable

- **Authentification complète** — inscription, connexion, déconnexion, session PHP sécurisée (httponly, SameSite=Lax), CSRF sur tous les formulaires, tokens à 64 caractères hex
- **Profil public** — XP total, niveau calculé dynamiquement, clan, avatar (preset emoji / upload), bio, historique XP paginé, badges obtenus
- **Mon Compte** — modification pseudo (unicité vérifiée), bio, mot de passe, avatar upload/emoji, préférences notifications, export JSON RGPD, demande de suppression de compte
- **Missions** — 9 types supportés (vote, quiz, photo, KTC, rando, hidden_hunt, investigation, event_flash, seasonal_collective), liste, détail, participation
- **Système XP** — attribution immédiate (auto) ou après validation admin (manuel), table `xp_logs` avec `source_type`, barème `XP_RATES` centralisé dans `config.php`
- **Système de niveaux V10.2** — 10 niveaux, seuils cohérents, `get_user_level_from_xp()` dans `functions.php`, noms et seuils en constantes
- **Scores de clan** — `clan_score_logs` par saison, remise à zéro automatique à chaque nouvelle saison
- **Hidden Hunt V9** — objets cachés sur pages, overlay Bravo, anti-doublon DB, progression N/total, XP à la complétion du set, badge optionnel, position mobile fallback
- **Back-office admin** — dashboard 9 KPIs, CRUD missions, validation/refus participations, gestion utilisateurs (rôles, suspension), clans, saisons, flash events, badges, collectibles
- **Saisons V11** — création, activation, clôture automatique, `winner_clan`, trophée, feed, tables `season_trophies` et `season_clan_results`
- **Badges V11** — 19 badges seeds, 5 `condition_type`, 5 raretés avec couleurs, attribution via `user_badges`
- **Ketokolé Tché (KTC)** — 7 questions seeds, quiz AJAX (`ajax/ktc-answer.php`), anti-doublon, XP, 6 catégories
- **Flash Events** — table `missions` avec `is_flash=1`, countdown JS côté public
- **Hall communautaire** — `community_feed` avec 12 `event_type`, pagination, textes relatifs
- **Météo Zone85** — `weather_posts` par zone (bocage/littoral/marais/vendée), alertes
- **Randonnées** — `mission_rando_data` (distance, dénivelé, difficulté, GPX), `rando_completions`, badge rando
- **Queue email** — `email_queue` + `email_templates` (9 templates), `queue_email()`, `process_email_queue()` prêt pour cron
- **Structure PWA** — `manifest.json` complet (8 icônes déclarées, 2 screenshots, 3 shortcuts), `pwa-install.js` V10.2 avec fix race condition, détection Android/iOS/Desktop, `pwa_installs` en DB
- **Analytics** — snippet GA4 conditionnel, stub Matomo, fallback `window.Zone85Analytics`
- **Passeport Zone85** — onglet profil avec saisons, trophées, collectibles, KTC, randos, badges groupés
- **RGPD** — `legal_acceptances`, export JSON, demande suppression (status `pending_delete`), anonymisation 30 j
- **Sécurité** — transactions PDO, contraintes `UNIQUE KEY` anti-doublon, headers HTTP de sécurité, `.htaccess` bloquant les uploads PHP, redirection ouverte bloquée dans `redirect()`

---

## ⚠️ Partiel ou à surveiller

- **Pop-up PWA post-inscription** — la logique `?pwa_install=1` existe dans `pwa-install.js` mais il faut vérifier que `inscription.php` redirige bien avec ce paramètre. Risque : le pop-up ne s'affiche jamais après inscription si la redirection est absente. *(Risque : moyen)*
- **Tracking PWA serveur** — `ajax/pwa-install-track.php` est référencé dans `pwa-install.js` (variable `TRACK`) mais son existence n'est pas confirmée. Risque : erreurs 404 silencieuses, perte de données d'installation. *(Risque : faible — silencieux)*
- **Feed communautaire** — `push_community_feed()` doit être appelé à tous les points d'injection pertinents (validation participation, attribution badge…) — vérifier la couverture dans `repositories.php`. *(Risque : moyen — des événements peuvent manquer dans le feed)*
- **Icônes PWA physiques** — `manifest.json` déclare 8 tailles, mais les PNG doivent être physiquement déposés dans `assets/img/pwa/`. S'ils sont absents, Chrome refuse le prompt d'installation. *(Risque : bloquant pour l'installabilité)*
- **Grande Mission** — champ `is_grande_mission` présent, barème XP `grande_mission_participation/selected` dans config — le flow complet candidature/sélection reste à tester end-to-end. *(Risque : élevé si c'est une feature centrale de la bêta)*
- **Commentaires** — table `comments` avec workflow `pending/approved/rejected` — les pages d'affichage public et l'interface de modération admin sont à vérifier. *(Risque : moyen)*
- **Notifications push** — colonne `notif_push` dans `users` et préférence dans Mon Compte — l'infrastructure VAPID/Push API n'est pas visible dans le code. *(Risque : faible — la préférence est sauvegardée mais ne fait rien)*

---

## ❌ Non terminé ou théorique

- **Les Invisibles** — mentionné dans les limites V8 comme feature planifiée, aucune table ni code trouvé dans la codebase actuelle
- **Paiement / Premium** — `is_paid` dans `games`, `game_type = 'premium_game'` dans missions — aucun module de paiement (Stripe, PayPal, autre) n'est détecté. Les jeux premium sont déclarés mais non monétisables.
- **Notifications temps réel** — WebSocket et SSE non implémentés. Les notifications sont uniquement par email (via queue) ou par re-chargement de page.
- **Push notifications navigateur** — la colonne `notif_push` existe, la préférence est enregistrable, mais l'infrastructure complète (clés VAPID, endpoint push, `push` event dans le Service Worker) est absente.
- **Quiz avancé multi-options** — table `mission_options` présente mais les formulaires de participation spécifiques pour tous les types de mission ne sont pas tous développés.
- **Upload photos de mission côté public** — `requires_upload` dans missions, table `media` présente — le flow complet d'upload depuis la page de participation est à vérifier pour chaque type de mission.

---

## 🚀 Plan bêta privée

### Avant d'inviter les premiers bêta-testeurs

1. **Déployer `zone85_clean_v11.sql`** sur la base de production et vérifier que toutes les tables sont créées sans erreur.
2. **Adapter `config.php`** : `BASE_URL = '/'`, `APP_ENV = 'prod'`, `DB_*` production, `SITE_URL = 'https://www.zone85.fr'`.
3. **Générer et déposer les icônes PWA** dans `assets/img/pwa/` — au minimum `icon-192.png` et `icon-512.png` pour que Chrome accepte le manifest. Outil : [pwabuilder.com/imageGenerator](https://www.pwabuilder.com/imageGenerator).
4. **Créer `ajax/pwa-install-track.php`** s'il n'existe pas — INSERT dans `pwa_installs` + mise à jour de `users.pwa_installed_at`.
5. **Configurer le cron `process-email-queue.php`** sur Plesk (toutes les 5 minutes) — sans ça, aucun email ne part (bienvenue, badge, notification).
6. **Tester le flow complet Hidden Hunt** : créer une mission avec 2 objets, les trouver, vérifier XP + badge dans `user_badges` et `xp_logs`.
7. **Tester KTC** : répondre à 3 questions, vérifier XP crédité et anti-doublon fonctionnel au rechargement.
8. **Vérifier que `inscription.php` redirige vers `?pwa_install=1`** après création de compte — test sur mobile Chrome.
9. **Passer un compte en rôle admin** (`UPDATE users SET role='admin' WHERE email='...'`) et parcourir les 9 sections du back-office.
10. **Lancer un audit Lighthouse PWA** depuis Chrome DevTools et corriger tous les points bloquants avant d'ouvrir l'accès bêta.

### À faire pendant la bêta

- Monitorer `email_queue` quotidiennement (`SELECT status, COUNT(*) FROM email_queue GROUP BY status`) — les `failed` doivent être nuls.
- Vérifier `xp_logs` après chaque session de test pour s'assurer que les XP s'accumulent correctement et sans doublon.
- Tester sur au moins 3 appareils réels : Android Chrome, iPhone Safari, Desktop Chrome.
- Collecter les retours sur la lisibilité de la barre de progression des niveaux et les icônes de missions.
- Vérifier que le feed communautaire se peuple à chaque action significative (participation validée, badge obtenu, inscription).
- Activer Brevo si disponible et tester les 9 templates email en conditions réelles.

### À ne pas faire avant la bêta (dette technique)

- Ne pas implémenter les notifications push navigateur (VAPID) — trop complexe, pas de valeur perçue en phase bêta.
- Ne pas construire le module de paiement — les jeux premium restent en mode "coming soon".
- Ne pas migrer vers WebSocket pour le temps réel — la charge ne le justifie pas à ce stade.
- Ne pas renforcer la CSP (actuellement `unsafe-inline` autorisé) — audit complet requis avant de restreindre.
- Ne pas résoudre la dette du `csrf_token()` qui retourne un placeholder si la session n'est pas démarrée — laisser le TODO en place.

---

## 📊 Scorecard pré-bêta

| Axe | Note | Commentaire |
|-----|------|-------------|
| Inscription | 8/10 | Fonctionnelle, CSRF, unicité pseudo. Manque : vérification que `?pwa_install=1` est bien passé à la redirection. |
| Connexion | 9/10 | Sécurisée, session correcte, headers OK. Manque : rate-limiting sur les tentatives. |
| Profil | 8/10 | XP, niveau, clan, avatar, bio, historique. Barre de progression à vérifier visuellement sur mobile. |
| Missions | 7/10 | 9 types déclarés, liste et participation OK. Flow upload photo et Grande Mission non validés end-to-end. |
| Badges | 7/10 | 19 seeds en base, 5 raretés. Attribution automatique à vérifier pour chaque `condition_type`. |
| Hidden Hunt | 8/10 | Anti-doublon DB, XP à la complétion, badge optionnel. Position mobile à tester sur petits écrans. |
| Emailing | 4/10 | Queue en place, 9 templates, cron prêt. Brevo non configuré = aucun email réel envoyé. Critique pour bienvenue et badges. |
| PWA | 6/10 | Manifest complet, fix race condition V10.2, détection plateforme. Bloquant : les 8 PNG d'icônes ne sont pas déposés sur le serveur. Sans `icon-192.png` et `icon-512.png`, Chrome ne propose pas l'installation. |
| Admin | 8/10 | Dashboard KPIs, CRUD complet, gestion rôles. Modération commentaires à vérifier. |
| Mobile | 7/10 | CSS responsive présent, bannière PWA iOS/Android. Tests réels à effectuer sur 3 appareils minimum avant ouverture bêta. |
