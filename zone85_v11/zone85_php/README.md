# ZONE85 — Version PHP MySQL V9 (Hidden Hunt / Jeux de piste)

Site communautaire de gamification vendéenne.  
Architecture PHP modulaire, MySQL/PDO, back-office admin intégré.

---

## Lancer en local

```bash
cd zone85_php
php -S localhost:8080
# Ouvrir http://localhost:8080
```

---

## Structure

```
zone85_php/
├── index.php / missions.php / mission.php / profil.php …  Pages publiques
├── login.php / inscription.php / logout.php               Auth
├── admin/                                                  Back-office Admin V8
│   ├── index.php               Dashboard
│   ├── missions.php            Liste missions
│   ├── mission-edit.php        Créer / Éditer mission
│   ├── participations.php      Liste participations
│   ├── participation-view.php  Voir + Valider / Refuser
│   ├── _admin-header.php       Chrome admin
│   └── _admin-footer.php
├── includes/
│   ├── config.php              Constantes (DB, BASE_URL…)
│   ├── db.php                  Connexion PDO
│   ├── auth.php                Login / Inscription / Session
│   ├── admin.php               is_admin() / require_admin()
│   ├── repositories.php        Toutes les fonctions DB
│   └── functions.php           Helpers (csrf, url, e…)
├── database/
│   ├── schema.sql              Schéma complet
│   ├── seed.sql                Données initiales
│   └── migrations/
│       ├── 001_auth_v1.sql
│       ├── 002_auth_fix.sql
│       ├── 003_participation_v1.sql
│       ├── 004_admin_v1.sql    Indexes admin + note rôle
│       └── 005_hidden_hunt_v1.sql  Tables collectibles
├── admin/
│   ├── collectibles.php        Liste objets cachés d'une mission
│   └── collectible-edit.php    Créer / Éditer un objet caché
├── components/
│   └── hidden-collectibles.php Rendu conditionnel des objets (0 HTML si rien)
├── ajax/
│   └── collectible-found.php   Endpoint POST : valider trouvaille → JSON
├── assets/js/
│   └── hidden-hunt.js          Overlay bravo + toast (vanilla JS, ~6KB)
└── uploads/collectibles/       Images objets cachés + success GIFs
    └── .htaccess               Sécurité : interdit exécution PHP
```

---

## Installation base de données

```sql
-- 1. Importer le schéma complet
mysql -u zone85_user -p zone85 < database/schema.sql

-- 2. Données initiales (clans, saison, missions test)
mysql -u zone85_user -p zone85 < database/seed.sql

-- 3. Migrations V8 + V9 + V10
mysql -u zone85_user -p zone85 < database/migrations/004_admin_v1.sql
mysql -u zone85_user -p zone85 < database/migrations/005_hidden_hunt_v1.sql
mysql -u zone85_user -p zone85 < database/migrations/006_collectible_mobile_v1.sql
mysql -u zone85_user -p zone85 < database/migrations/007_v10_users_prefs.sql
mysql -u zone85_user -p zone85 < database/migrations/008_v10_email_queue.sql

-- 4. Migrations V11 (Saisons vivantes & Bataille des Clans)
mysql -u zone85_user -p zone85 < database/migrations/009_v11_seasons_enhanced.sql
mysql -u zone85_user -p zone85 < database/migrations/010_v11_events_flash.sql
mysql -u zone85_user -p zone85 < database/migrations/011_v11_badges_advanced.sql
mysql -u zone85_user -p zone85 < database/migrations/012_v11_community_feed.sql
mysql -u zone85_user -p zone85 < database/migrations/013_v11_rando.sql
```

**config.php** : adapter `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`.

---

## Créer un administrateur

Après inscription d'un compte normal :

```sql
UPDATE users SET role = 'admin' WHERE email = 'votre@email.fr';
```

Rôles disponibles : `member` | `moderator` | `admin`

---

## Accéder au back-office

`/admin/index.php` ou cliquer sur le bouton **Admin** dans la nav (visible uniquement pour les admins).

Un non-admin voit une page 403.

---

## Checklist de test V8

### 1. Passer un user en admin
```sql
UPDATE users SET role = 'admin' WHERE email = 'ton@email.fr';
```

### 2. Se connecter et accéder au back-office
- Se connecter sur `/login.php`
- Vérifier que le bouton **Admin** apparaît dans la nav
- Accéder à `/admin/index.php`
- Vérifier le dashboard (stats, dernière mission, dernières participations)

### 3. Créer une mission AUTO
- `/admin/mission-edit.php`
- Remplir : titre, type = Vote, validation = Auto, XP participation = 5, pts clan = 1
- Statut = Active → Créer
- Vérifier apparition dans `/admin/missions.php`

### 4. Participer côté public (mission AUTO)
- Se connecter avec un compte membre
- Aller sur `/missions.php` → cliquer sur la mission
- Participer → vérifier message "Participation enregistrée ! Tes XP ont été crédités."
- Aller sur `/profil.php` → vérifier +5 XP dans l'historique

### 5. Créer une mission MANUELLE
- `/admin/mission-edit.php`
- Validation = Manuel, XP participation = 0, XP réussite = 30, pts clan réussite = 5
- Statut = Active → Créer

### 6. Participer côté public (mission MANUELLE)
- Participer → vérifier message "Participation envoyée. L'équipe Zone85 va la vérifier sous 24–48h."
- Sur `/profil.php` → la participation apparaît en statut **pending**, XP = 0

### 7. Valider la participation (admin)
- `/admin/participations.php` → filtre : En attente
- Cliquer sur "Traiter"
- `/admin/participation-view.php?id=X` → bouton "Valider"
- Confirmer → flash "Participation validée. +30 XP attribués."

### 8. Vérifier XP success
- Sur `/profil.php` du membre → xp_total augmenté de 30
- xp_logs contient une ligne `source_type = 'mission_success'`

### 9. Vérifier profil
- Statut participation = validated
- Historique XP cohérent

### 10. Refuser une participation test
- Créer une 2e participation manuelle (avec un autre compte ou recréer)
- `/admin/participation-view.php` → "Refuser"
- Vérifier : statut = rejected, aucun XP supplémentaire dans xp_logs

### 11. Vérifier l'anti-doublon
- Tenter de valider deux fois la même participation → message "déjà traitée"
- Aucun XP dupliqué dans xp_logs

### 12. Vérifier clan_score_logs
```sql
SELECT * FROM clan_score_logs ORDER BY created_at DESC LIMIT 10;
```

### 13. Vérifier xp_logs
```sql
SELECT * FROM xp_logs ORDER BY created_at DESC LIMIT 10;
```

### 14. Vérifier qu'un membre standard ne voit pas le back-office
- Se connecter avec un compte role = member
- Accéder à `/admin/index.php` → page 403

---

## Checklist de test V9 — Hidden Hunt

### 1. Installer la migration
```sql
mysql -u zone85_user -p zone85 < database/migrations/005_hidden_hunt_v1.sql
```

### 2. Créer une mission Hidden Hunt
- `/admin/mission-edit.php`
- Type = 🗝️ Chasse cachée, Validation = Auto
- XP participation = 0, XP réussite = 50, pts clan réussite = 10
- Statut = Active → Créer
- Après sauvegarde → bloc doré "Gérer les objets cachés" apparaît en bas

### 3. Ajouter des objets cachés
- Cliquer sur "Gérer les objets cachés" → `/admin/collectibles.php?mission_id=X`
- Cliquer "+ Ajouter" → `/admin/collectible-edit.php?mission_id=X`
- Remplir : titre, page_slug = `index`, position top = 25%, left = 80%
- Ajouter une image légère (max 1MB)
- Sauvegarder → l'objet apparaît dans la liste
- Ajouter 2–3 objets sur des pages différentes (index, missions, clans…)

### 4. Vérifier affichage côté public
- Aller sur `/index.php` → l'objet flotte sur la page (animation)
- **Aucun objet sur `/clans.php`** si aucun n'est configuré sur cette page → 0 HTML, 0 script chargé

### 5. Cliquer sur un objet (non connecté)
- Overlay "Connecte-toi !" apparaît
- Bouton "Se connecter →" redirige vers login.php

### 6. Cliquer sur un objet (connecté)
- Overlay "Bravo !" s'affiche avec progression (N / total)
- L'objet disparaît de la page
- XP crédités uniquement si c'est le **dernier** objet (complétion)

### 7. Vérifier anti-doublon
- Recharger la page → l'objet ne réapparaît PAS (déjà trouvé côté DB)
- Si on clique une 2e fois via DevTools → toast "Tu avais déjà trouvé cet objet !"

### 8. Vérifier complétion mission
- Trouver tous les objets → overlay 🏆 "Mission accomplie !"
- Session XP mise à jour → profil.php affiche +50 XP
- xp_logs : `source_type = 'hidden_hunt_completion'`
- participations : ligne auto créée avec statut `auto_validated`

### 9. Vérifier profil.php
- Section "🗝️ Mes jeux de piste" visible si au moins une chasse commencée
- Barre de progression pour chaque mission, XP gagnés si terminé

### 10. Vérifier mission.php avec Hidden Hunt
- `/mission.php?id=X` avec type = hidden_hunt
- Affiche la progression + bouton "Commencer/Continuer la chasse"
- Pas de formulaire de participation classique

### 11. Désactiver un objet (admin)
- `/admin/collectibles.php?mission_id=X` → cliquer "Désactiver"
- L'objet ne s'affiche plus sur le site (is_active = 0)

### 12. Vérifier performance
- Ouvrir une page sans objets configurés → 0 requête hidden hunt, 0 script chargé
- Ouvrir une page avec objets → UNE seule requête (index sur mission_id, page_slug, is_active)
- Le JS `hidden-hunt.js` n'est chargé QUE si des objets sont présents sur la page

### 13. Vérifier sécurité uploads
- Tenter d'uploader un fichier `.php` → rejeté côté PHP
- `.htaccess` dans `uploads/collectibles/` bloque l'exécution

### 14. Vérifier sécurité AJAX
- POST sur `ajax/collectible-found.php` sans session → 401 JSON
- POST sans CSRF token → 403 JSON
- POST avec collectible_id invalide → 400 JSON
- Toutes les réponses sont JSON propre, sans HTML

---

## Checklist de test V9.1

### 1. PNG transparent
- Placer un PNG à fond transparent sur une page et vérifier l'absence de carré blanc/gris autour de l'objet
- Vérifier que `background: transparent !important` et `box-shadow: none !important` sont actifs
- Inspecter l'élément `.hidden-collectible` dans DevTools : aucun fond, aucune bordure

### 2. Position mobile
- Réduire la fenêtre sous 768px et vérifier que l'objet se repositionne selon `data-top-mobile` / `data-left-mobile`
- Si aucune position mobile configurée, vérifier qu'il utilise la position desktop en fallback
- Vérifier que la taille passe à `size_mobile` px sur mobile

### 3. XP complétion
- Trouver 1 objet sur 3 → vérifier 0 XP crédités en session
- Trouver le dernier objet → vérifier que les XP sont crédités une seule fois
- Sur `profil.php`, vérifier que `xp_logs` contient une ligne `hidden_hunt_completion`

### 4. Badge mission
- Créer une mission hidden_hunt avec un `badge_reward_id` configuré
- Compléter la chasse → vérifier que le badge apparaît dans l'overlay bravo
- Sur `profil.php` → vérifier que le badge est listé dans les badges obtenus

### 5. Sidebar profil
- Vérifier que la contribution affiche "+210 pts" et non "+210 pts pts"
- Vérifier que la ligne de rang (`🏅 Xe sur N membres`) n'apparaît que si clan_rank > 0 et clan_members > 0
- Vérifier le fallback si clan non configuré : la ligne est absente

### 6. Classement fond clair
- Ouvrir `/classement.php` → les panels sous les tabs doivent être clairs (fond #f8f4ef)
- Vérifier que le hero reste sombre (navy) et les tabs aussi
- Vérifier le responsive mobile : la grille clans passe en 1 colonne

### 7. Profil — Mes jeux de piste
- Commencer une chasse → la section "Mes jeux de piste" apparaît avec barre de progression réelle
- Vérifier que le bouton "Continuer →" apparaît pour les missions actives non terminées
- Terminer une chasse → badge vert "Terminée" + barre verte + XP gagnés affichés

### 8. Historique XP — Activité
- Sur `/profil.php` TAB Activité → seules les lignes réelles de xp_logs s'affichent
- Vérifier que `hidden_hunt_completion` affiche l'icône 🗝️
- Si aucune action → message "Tes premières actions apparaîtront ici."

---

## Checklist de test V11 — Saisons Vivantes & Bataille des Clans

### A. Migrations V11
```sql
-- Vérifier les nouvelles tables/colonnes
SHOW TABLES LIKE 'season_trophies';
SHOW TABLES LIKE 'season_clan_results';
SHOW TABLES LIKE 'community_feed';
SHOW TABLES LIKE 'weather_posts';
SHOW TABLES LIKE 'ktc_questions';
SHOW TABLES LIKE 'ktc_answers';
SHOW TABLES LIKE 'mission_rando_data';
SHOW TABLES LIKE 'rando_completions';
SHOW COLUMNS FROM seasons LIKE 'color_primary';
SHOW COLUMNS FROM missions LIKE 'is_flash';
SHOW COLUMNS FROM missions LIKE 'is_grande_mission';
SHOW COLUMNS FROM badges LIKE 'category';
SHOW COLUMNS FROM badges LIKE 'rarity';
SELECT COUNT(*) FROM ktc_questions;  -- doit retourner 7
SELECT COUNT(*) FROM badges;          -- doit inclure les 12 nouveaux badges V11
```

### B. Admin Saisons (`/admin/seasons.php`)
1. Accéder → liste des saisons existantes
2. Créer une saison : titre, slug auto-généré, couleurs, emoji, dates → "Créer"
3. Activer la saison → les autres passent à "closed", la nouvelle à "active"
4. Clôturer une saison active → winner_clan calculé automatiquement, trophy inséré, feed mis à jour
5. Modifier une saison draft → champs éditables

### C. Admin Clans (`/admin/clans.php`)
1. 3 cards clans affichées avec couleurs distinctes
2. Stats : membres, score saison, participations, XP, trophées
3. Lien "Voir tous les membres" → users.php filtré par clan

### D. Admin Flash Events (`/admin/events.php`)
1. Créer un flash event : emoji, titre, dates début/fin, multiplicateur ×2
2. Activer → apparaît sur evenements.php
3. Le multiplicateur est visible dans le tableau
4. Archiver → disparaît des flash actifs

### E. Page Événements Flash (`/evenements.php`)
1. Flash actif : card avec countdown JS (jours/h/min/s décompte en temps réel)
2. Badge "● LIVE" avec animation pulse
3. Aucun flash : message "Guette la Zone..."
4. Flash passés en liste compacte

### F. Trophéothèque (`/trophees.php`)
1. Liste des trophées si au moins une saison clôturée
2. Chaque trophée : couleur saison, chip clan gagnant, dates
3. Liste saisons actives/fermées en bas
4. Aucun trophée : message informatif

### G. Ketokolé Tché (`/ktc.php`)
1. Question aléatoire affichée avec 4 options
2. Clic sur une réponse (connecté) → POST AJAX vers `ajax/ktc-answer.php`
3. Bonne réponse : animation verte, explication, XP crédités
4. Mauvaise réponse : rouge, explication, bonne réponse révélée
5. Anti-doublon : recharger → question déjà répondue signalée
6. Non connecté : boutons grisés + message connexion
7. Section stats (taux réussite, XP KTC) si connecté et répondu
8. 6 catégories affichées avec comptage

### H. Hall de la Zone vivant (`/hall.php`)
1. Bandeau flash events si actifs
2. Barre score clans en temps réel
3. Feed communautaire depuis `community_feed` (ou participations en fallback)
4. Textes relatifs (il y a Xh, hier, il y a Xj)
5. Icônes par type d'événement
6. Pagination ?page=2 fonctionne

### I. Météo Zone85 (`/meteo.php`)
1. Si aucune donnée : section concept explicative
2. Si `weather_posts` contient des entrées : post actuel affiché
3. Alertes en rouge si `is_alert=1`
4. Section missions météo actives

### J. Passeport Zone85 (TAB dans profil.php)
1. Onglet "🗺️ Passeport" visible dans la sidebar nav du profil
2. Stats : saisons vécues, trophées, missions, collectibles, KTC, randos
3. Saisons vécues listées (si participations dans missions avec season_id)
4. Badges groupés par catégorie

### K. Badges V11
1. Vérifier l'affichage des badges avec catégorie et rareté dans profil
2. Badge "ktc-champion" attribué après 10 bonnes réponses KTC
3. Couleurs raretés : commun=#6b7f96, rare=#12314e, épique=#9b59b6, légendaire=#C9962A

### L. Feed communautaire
1. `push_community_feed()` appelé lors d'un badge gagné, mission validée, etc.
2. Vérifier que la table `community_feed` se remplit avec les actions utilisateurs

---

## Checklist de test V10

### A. PWA

#### 1. Manifest & installation
- Ouvrir DevTools → Application → Manifest : vérifier icônes, nom, couleurs
- Sur Chrome Android : bouton "Installer l'application" apparaît dans le menu
- Sur Safari iOS : bouton Partager → "Sur l'écran d'accueil" disponible
- Sur Chrome Desktop : icône installation dans la barre d'adresse
- Après installation : `window.matchMedia('(display-mode: standalone)').matches` = true

#### 2. Service Worker
- DevTools → Application → Service Workers : état "activated and running"
- Charger une page, couper le réseau, recharger → page offline.php affichée
- Assets CSS/JS servis depuis le cache (DevTools Network → From ServiceWorker)
- Les pages admin ne sont PAS mises en cache (filtre `/admin/` actif)

#### 3. Pop-up installation
- Première visite : pas de pop-up
- Deuxième visite : pop-up "Installer Zone85" en bas de l'écran
- Après `?pwa_install=1` : pop-up forcé (après inscription)
- Fermer le pop-up → ne réapparaît plus (localStorage `z85_pwa_dismissed=1`)
- Installer l'app → pop-up disparaît, event `appinstalled` tracé en DB
- Sur iOS/Safari : instructions "⬆ Partager → Sur l'écran d'accueil" affichées

#### 4. Icônes PWA
- Placer les PNGs dans `assets/img/pwa/` (voir README.txt dans ce dossier)
- Utiliser https://maskable.app/editor ou https://www.pwabuilder.com/imageGenerator
- Source SVG : `assets/img/pwa/icon.svg`

---

### B. Mon Compte (`/mon-compte.php`)

#### 1. Informations
- Modifier pseudo → vérifier unicité + session mise à jour
- Pseudo trop court (< 3 chars) → message d'erreur
- Email en lecture seule → ne peut pas être modifié
- Bio modifiable → enregistrée en DB

#### 2. Mot de passe
- Mauvais mot de passe actuel → erreur "Mot de passe actuel incorrect"
- Nouveau MDP < 8 chars → erreur
- MDP et confirmation différents → erreur
- Changement valide → flash vert "Mot de passe mis à jour"

#### 3. Avatar (TAB Avatar)
- Upload photo JPG/PNG/WebP → avatar mis à jour, ancienne photo supprimée
- Upload fichier PHP → rejeté
- Sélectionner emoji → grille émojis, clic → `selected_emoji` mis à jour
- Supprimer photo → retour emoji par défaut 🧭

#### 4. Préférences (TAB Préférences)
- Cocher/décocher les toggles → enregistrement DB (newsletter_optin, notif_*, digest_hebdo)
- Vérifier que les états sont rechargés correctement à la réouverture de la page

#### 5. RGPD (TAB Confidentialité)
- Date inscription affichée
- Acceptations CGU et confidentialité listées
- "Télécharger mes données" → fichier JSON téléchargé avec profil, XP, participations, badges
- "Demander la suppression" → confirmation, status=pending_delete, email en queue
- Après demande : bouton remplacé par message "Demande enregistrée le X"

---

### C. Admin Utilisateurs (`/admin/users.php`)

- Liste triée par date d'inscription DESC
- Filtres : clan, rôle, statut, newsletter, recherche texte
- Pagination 30 par page
- Changement de rôle → modal de confirmation → mis à jour en DB
- Suspension compte → icône ⛔ → statut `suspended`
- Réactivation → bouton ✅ → statut `active`
- Impossible de se suspendre soi-même (vérification ID)
- Icône 📱 si PWA installée

---

### D. Dashboard Admin (`/admin/dashboard.php`)

- 9 KPIs affichés : membres, actifs 30j, missions, participations, XP, badges, objets trouvés, PWA, pts clan
- 2 mini graphiques barres (inscriptions 14j / XP 14j)
- Derniers inscrits avec avatar
- Dernières actions XP avec icônes par type
- `/admin/index.php` redirige vers `/admin/dashboard.php`

---

### E. Brevo (structure)

- `includes/mailer.php` chargé
- `queue_email()` : ajoute en `email_queue`
- `send_email()` : envoie via Brevo si `BREVO_ENABLED=true` + clé API, sinon mail()
- `process_email_queue()` : à appeler via cron Plesk
- Table `email_templates` : 9 templates prêts (slugs configurés)
- Activer : `config.php` → `BREVO_API_KEY`, `BREVO_ENABLED=true`

---

### F. Analytics

- **GA4** : renseigner `GA4_MEASUREMENT_ID` dans `config.php` → snippet chargé sur toutes les pages
- **Matomo** : renseigner `MATOMO_URL` + `MATOMO_SITE_ID` → snippet alternatif
- **Stub** : si aucun configuré → `window.Zone85Analytics` disponible pour `track()` manuel
- Événements côté JS à déclencher : `Zone85Analytics.track('signup')`, `('login')`, etc.

---

### G. Migrations V10

```sql
-- Vérifier les nouvelles colonnes
SHOW COLUMNS FROM users LIKE 'deleted_at';
SHOW COLUMNS FROM users LIKE 'notif_missions';
SHOW COLUMNS FROM users LIKE 'pwa_installed_at';
DESC email_queue;
DESC email_templates;
DESC pwa_installs;
```

---

## Vocabulaire Zone85

| Terme | Définition |
|---|---|
| **XP à vie** | Progression personnelle permanente du membre (jamais remis à zéro) |
| **Score de saison** | Points du clan, remis à zéro à chaque saison |
| **xp_participation** | XP attribués immédiatement à la participation (mode auto) |
| **xp_success** | XP attribués uniquement après validation admin (mode manual) |
| **clan_points_participation** | Points clan immédiats |
| **clan_points_success** | Points clan attribués après validation admin |

> "Je progresse pour moi. Je fais gagner mon clan."  
> "Le clan gagne la saison. Le joueur construit sa légende."

---

## Limites V8

- Pas de gestion utilisateurs complète (pas de suspension, suppression dans l'admin)
- Pas d'upload photo de mission
- Pas de formulaires spécifiques par type de mission (KTC, quiz avancé…)
- Pas de notifications temps réel
- Pas de paiement
- Les Invisibles : non développé
- Un refresh de page suffit pour voir les changements post-validation

---

## Sécurité

- Toutes les pages admin : session active + role = admin obligatoires
- CSRF sur tous les formulaires (POST)
- Pas d'affichage d'erreurs SQL en production (`APP_ENV = prod`)
- Transactions PDO sur toutes les opérations critiques
- Anti-doublon sur participation (UNIQUE KEY user_id + mission_id)
- Anti-doublon sur validation (vérification status = pending avant toute action)
