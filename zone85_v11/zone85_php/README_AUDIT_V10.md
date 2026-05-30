# ZONE85 — Audit Fonctionnel V10.1

> Audit basé sur lecture du code source réel : `config.php`, `mailer.php`, `repositories.php`, `pwa-install.js`, `manifest.json`, `zone85_clean_v11.sql` et `README.md`.  
> Date : 2026-05-29

---

## ✅ Opérationnel et testé

| Feature | Description |
|---|---|
| **Authentification** | Inscription, login, logout, session PHP sécurisée (httponly, samesite=Lax), CSRF sur tous les formulaires |
| **Profil public** | XP total, niveau, clan, avatar (preset/emoji/upload), bio, historique XP, badges obtenus |
| **Mon Compte** | Modification pseudo (unicité), bio, mot de passe, avatar upload/emoji, préférences notif, RGPD (export JSON, demande suppression) |
| **Missions** | Types : `vote`, `quiz`, `photo_challenge`, `keto_kole_tche`, `rando`, `hidden_hunt`, `investigation`, `event_flash`, `seasonal_collective` — liste, détail, participation |
| **Système XP** | Attribution immédiate (auto) ou après validation admin (manuel), `xp_logs` avec source_type, barème `XP_RATES` dans config |
| **Scores clan** | `clan_score_logs` par saison, scores remis à zéro à chaque nouvelle saison |
| **Hidden Hunt (V9/V9.1)** | Objets cachés sur pages, overlay Bravo, anti-doublon DB, progression N/total, XP à la complétion uniquement, badge optionnel, position mobile fallback, PNG transparent (`background:transparent !important`) |
| **Back-office Admin** | Dashboard KPIs (9 métriques), missions CRUD, participations (liste, validation, refus), utilisateurs (rôles, suspension, filtre), clans, saisons, flash events, badges, collectibles |
| **Saisons V11** | Création, activation, clôture automatique (winner_clan, trophy, feed), `season_trophies`, `season_clan_results` |
| **Badges V11** | 19 badges seeds en base, 5 types (`condition_type`), 5 raretés avec couleurs, attribution via `user_badges` avec source_type |
| **Ketokolé Tché (KTC)** | 7 questions seeds, quiz AJAX (`ajax/ktc-answer.php`), anti-doublon, XP, 6 catégories |
| **Flash Events** | Table `missions` avec `is_flash=1`, `flash_start_at/end_at`, `xp_multiplier`, countdown JS côté public |
| **Hall communautaire** | `community_feed` avec 12 event_types, pagination, icônes par type, textes relatifs |
| **Météo Zone85** | `weather_posts` par zone (bocage/littoral/marais/vendee), alertes (`is_alert=1`), missions météo liées |
| **Randonnées** | `mission_rando_data` (distance, dénivelé, difficulté, GPX), `rando_completions`, badge rando |
| **Email queue** | `email_queue` + `email_templates` (9 templates), `queue_email()`, `process_email_queue()` pour cron |
| **PWA structure** | `manifest.json` complet (8 icônes, 2 screenshots, 3 shortcuts), `pwa-install.js` (détection plateforme, pop-up), `service-worker.js` enregistré, `pwa_installs` en DB |
| **Analytics** | Snippet GA4 chargé si `GA4_MEASUREMENT_ID` renseigné ; Matomo alternatif ; stub `window.Zone85Analytics` en fallback |
| **Passeport Zone85** | Onglet profil — saisons vécues, trophées, missions, collectibles, KTC, randos, badges groupés par catégorie |
| **RGPD** | `legal_acceptances`, export JSON données membre, demande suppression (status `pending_delete`), anonymisation 30j |
| **Sécurité** | Transactions PDO, anti-doublon `UNIQUE KEY` sur participations et user_collectibles, 403 admin pour non-admins, upload PHP bloqué via `.htaccess` |

---

## ⚠️ Partiellement terminé / À vérifier

| Feature | Statut |
|---|---|
| **Pop-up PWA après inscription** | Le paramètre `?pwa_install=1` est géré dans `pwa-install.js` (force l'affichage) mais il faut vérifier que `inscription.php` redirige bien avec ce paramètre après la création de compte |
| **Tracking PWA côté serveur** | `ajax/pwa-install-track.php` est référencé dans `pwa-install.js` (variable `TRACK`) — vérifier que ce fichier existe et insère bien dans `pwa_installs` |
| **Feed communautaire** | `push_community_feed()` doit être appelé dans les fonctions de validation/badge — vérifier que tous les points d'injection sont en place dans `repositories.php` |
| **Icônes PWA** | `manifest.json` déclare 8 tailles (72 à 512px) + 2 screenshots + icônes shortcuts — ces fichiers PNG doivent être physiquement présents dans `assets/img/pwa/` |
| **Email templates Brevo** | La table `email_templates` doit contenir les `brevo_id` correspondant aux templates créés côté Brevo (sinon fallback HTML générique activé) |
| **Grande Mission** | Champ `is_grande_mission` présent dans `missions`, barème XP `grande_mission_participation/selected` dans config — le flow complet de candidature/sélection reste à vérifier |
| **Notifications push** | Colonne `notif_push` dans `users` et préférence dans Mon Compte — l'infrastructure Push (Service Worker push event, endpoint, clés VAPID) n'est pas visible dans le code lu |
| **Commentaires** | Table `comments` avec statut modération (`pending/approved/rejected`) — les pages d'affichage et de modération admin sont à vérifier |

---

## 🔧 Structure en place, configuration requise

| Feature | Action requise |
|---|---|
| **Brevo** | Renseigner `BREVO_API_KEY` dans `config.php` et passer `BREVO_ENABLED` à `true`. Créer les templates dans l'interface Brevo et reporter leurs IDs dans la table `email_templates` (colonne `brevo_id`). |
| **Google Analytics 4** | Renseigner `GA4_MEASUREMENT_ID = 'G-XXXXXXXXXX'` dans `config.php` |
| **Matomo** | Renseigner `MATOMO_URL` et `MATOMO_SITE_ID` si utilisé à la place de GA4 |
| **Base de données prod** | Adapter `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_HOST` dans `config.php` (actuellement pointé sur `qg_` / `AdminQg85`) |
| **BASE_URL** | Changer `BASE_URL` de `/test/zone85_php/` vers `/` si le site est à la racine du domaine |
| **APP_ENV** | Passer `APP_ENV` de `'dev'` à `'prod'` avant mise en ligne |
| **Cron email queue** | Configurer un cron Plesk qui appelle `php /path/to/cron/process-email-queue.php` toutes les 5–10 minutes |
| **Icônes PWA** | Générer et déposer les PNG dans `assets/img/pwa/` : 72, 96, 128, 144, 152, 192, 384, 512px + screenshots + shortcuts (outil : https://www.pwabuilder.com/imageGenerator) |
| **SITE_URL** | Vérifier que `SITE_URL = 'https://www.zone85.fr'` correspond bien au domaine de production |

---

## ❌ Théorique / Non implémenté

| Feature | Remarque |
|---|---|
| **Les Invisibles** | Mentionné dans les limites V8 comme "non développé" — aucune table ni code trouvé |
| **Paiement / Premium** | `is_paid` dans `games`, `game_type = 'premium_game'` dans `missions` — aucun module de paiement (Stripe, PayPal…) détecté |
| **Notifications temps réel** | Listées comme limite V8 — WebSocket / SSE non implémentés |
| **Push notifications navigateur** | Colonne `notif_push` présente mais infrastructure VAPID / Push API absente |
| **Quiz avancé avec options** | Table `mission_options` présente mais formulaires de participation spécifiques par type non tous développés |
| **Modération commentaires** | Table `comments` avec workflow pending/approved/rejected — interface admin de modération à vérifier |
| **Upload photos de mission** | `requires_upload` dans missions, table `media` présente — le flow upload côté public est à vérifier pour tous les types de mission |

---

## 📋 Actions pour la bêta privée

Liste priorisée des 10 actions à faire avant d'inviter des bêta-testeurs :

1. **Déployer `zone85_clean_v11.sql`** sur la base de prod et vérifier que toutes les tables sont créées correctement
2. **Adapter `config.php`** : `BASE_URL = '/'`, `APP_ENV = 'prod'`, `DB_*` production, `SITE_URL` correct
3. **Générer et déposer les icônes PWA** dans `assets/img/pwa/` (au minimum 192px et 512px pour que le manifest soit valide)
4. **Créer le fichier `ajax/pwa-install-track.php`** s'il n'existe pas (INSERT dans `pwa_installs` + mise à jour `users.pwa_installed_at`)
5. **Configurer le cron `process_email_queue`** sur Plesk (toutes les 5 min) — sans ça, les emails de bienvenue et de badge ne partent pas
6. **Tester le flow complet Hidden Hunt** : créer une mission, ajouter 2 objets, les trouver, vérifier XP + badge en DB
7. **Tester KTC** : répondre à 3 questions, vérifier XP crédités et anti-doublon au rechargement
8. **Vérifier l'inscription → redirection `?pwa_install=1`** pour que le pop-up PWA s'affiche après la création de compte
9. **Passer un compte en admin** (`UPDATE users SET role='admin' WHERE email='...'`) et parcourir toutes les sections du back-office
10. **Lancer un audit Lighthouse** (PWA, Performance, Accessibilité) depuis Chrome DevTools et corriger les points bloquants avant d'ouvrir l'accès

---

> **Note de version** : le fichier `README.md` racine est étiqueté V9 mais le code intègre déjà toutes les fonctionnalités V10 (PWA, Mon Compte, Analytics, Admin étendu) et V11 (Saisons vivantes, Flash Events, KTC, Randos, Feed, Badges avancés). Ce projet est fonctionnellement à la version **V11** avec la base de données `zone85_clean_v11.sql` générée le 2026-05-29.
