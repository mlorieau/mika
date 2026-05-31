# ZONE85 — V12 Moteur de contenu

> Guide complet de déploiement et de test de la version 12.  
> Dernière mise à jour : 2026-05-30

---

## Sommaire

1. [Migrations SQL](#1-migrations-sql)
2. [Admin KTC Editorial](#2-admin-ktc-editorial)
3. [Admin Randos](#3-admin-randos)
4. [Front Randos](#4-front-randos)
5. [Email de bienvenue Brevo](#5-email-de-bienvenue-brevo)
6. [Réorganisation BO](#6-réorganisation-bo)
7. [Médiathèque (fondation)](#7-médiathèque-fondation)
8. [Checklist de test V12](#8-checklist-de-test-v12)

---

## 1. Migrations SQL

Importer les migrations dans l'ordre strict suivant via phpMyAdmin ou la CLI MySQL.

### 021 — Randonnées CMS premium

**Fichier :** `database/migrations/021_v12_randos.sql`

**Tables créées :**

| Table | Description |
|---|---|
| `randos` | Fiche éditoriale complète d'une randonnée |
| `rando_blocks` | Blocs de contenu riches (text, image, quote, info, conseil, around, youtube, map) |
| `rando_participations` | Participations membres — structure préparée V12 |

**Vérification post-import :**
```sql
SHOW TABLES LIKE 'rand%';
-- Doit retourner : rando_blocks, rando_participations, randos

DESCRIBE randos;
-- Vérifier : slug (UNIQUE), status (ENUM draft/published/archived), secteur, difficulty, gpx_url

DESCRIBE rando_blocks;
-- Vérifier : type ENUM avec 9 valeurs, content LONGTEXT (JSON), sort_order
```

### 022 — KTC Editorial V12

**Fichier :** `database/migrations/022_v12_ktc.sql`

**Tables ou colonnes modifiées :** nouvelles colonnes sur `ktc_episodes` pour le cycle 3 semaines (week1_clue / week2_clue / week3_revealed), colonne `status` étendue.

**Vérification post-import :**
```sql
DESCRIBE ktc_episodes;
-- Vérifier colonnes : week1_clue, week2_clue, week3_clue, revealed_answer, status ENUM
```

### 023 — Médiathèque & Settings V12

**Fichier :** `database/migrations/023_v12_media.sql`

**Tables créées :** `media_library` pour la gestion centralisée des fichiers uploadés.  
**Nouvelles clés settings :** `brevo_welcome_template_id`, `brevo_api_key`, `media_path`.

**Vérification post-import :**
```sql
SHOW TABLES LIKE 'media%';
SELECT setting_key FROM settings WHERE setting_key LIKE 'brevo%';
```

---

## 2. Admin KTC Editorial

### Flux de création d'un épisode

Le KTC (Kéto Kolé Tché ?) suit un cycle de révélation progressive sur 3 semaines.

**États possibles :**

```
draft → week1 → week2 → week3 → revealed → archived
```

| Statut | Affichage front | Description |
|---|---|---|
| `draft` | Masqué | En cours d'écriture admin |
| `week1` | Indice 1 visible | Premier indice publié |
| `week2` | Indices 1+2 visibles | Deuxième indice ajouté |
| `week3` | Indices 1+2+3 visibles | Dernier indice avant révélation |
| `revealed` | Réponse visible | La solution est dévoilée |
| `archived` | Masqué | Hors saison, conservé en base |

**Exemple — KTC de novembre :**

1. Admin crée un épisode en `draft` avec titre "L'objet mystère du bocage".
2. Le lundi matin : passage en `week1` → publication de `week1_clue` ("On le trouve dans toutes les fermes vendéennes...").
3. Le lundi suivant : passage en `week2` → ajout de `week2_clue` ("Il est en bois et mesure moins d'un mètre...").
4. Le lundi d'après : passage en `week3` → ajout de `week3_clue` + activation des votes membres.
5. Le vendredi : passage en `revealed` → `revealed_answer` affiché + points attribués aux membres ayant trouvé.
6. Fin de saison : passage en `archived`.

**Interface admin :** `admin/ktc.php` — onglet "KTC Editorial".

---

## 3. Admin Randos

**Accès :** `admin/randos.php`

### Les 5 onglets

| Onglet | Fonction |
|---|---|
| **Liste** | Tableau de toutes les fiches (filtre statut/secteur/difficulté) |
| **Nouvelle rando** | Formulaire de création avec tous les champs |
| **Blocs** | Éditeur de blocs de contenu pour la fiche sélectionnée |
| **GPX** | Upload et gestion des traces GPS |
| **Communauté** | Vue des participations et avis membres (lecture seule V12) |

### Système de blocs

Chaque bloc est une ligne de la table `rando_blocks` avec un champ `content` JSON.

**Types de blocs et structure JSON :**

```json
// text
{ "heading": "Le départ depuis Aizenay", "body": "Le parking se trouve..." }

// image
{ "src": "uploads/randos/photo-bocage.jpg", "caption": "Vue depuis le sommet" }

// quote
{ "body": "Le bocage a ses secrets...", "author": "Équipe Zone85" }

// info
{ "title": "Bonne à savoir", "body": "Pas de réseau mobile sur 3 km." }

// conseil
{ "title": "Conseil Zone85", "body": "Partez tôt le matin en été." }

// around
{ "title": "À voir autour", "items": ["Château de Tiffauges", "Lac du Rochereau"] }

// youtube
{ "url": "https://www.youtube.com/watch?v=XXXXXXXXXXX", "title": "La rando en vidéo" }

// map
{}
```

### Exemple de fiche complète

**Rando "La Marche des Marais"**

- Secteur : Marais
- Difficulté : Moyen (2 étoiles)
- Distance : 12,4 km
- Durée : 3h30
- Commune : Maillezais
- Point de départ : Parking de l'Abbaye de Maillezais
- GPX : `uploads/gpx/marche-des-marais.gpx`

Blocs dans l'ordre :
1. `text` — introduction (200 mots)
2. `image` — photo de l'abbaye
3. `info` — "Terrain boueux après la pluie"
4. `text` — description du parcours (400 mots)
5. `conseil` — "Portez des bottes au printemps"
6. `quote` — citation d'un Zonaute
7. `around` — lieux à découvrir (5 entrées)
8. `youtube` — lien vers une vidéo du marais
9. `map` — placeholder carte interactive

---

## 4. Front Randos

### randos.php — Bibliothèque

**URL :** `https://www.zone85.fr/randos.php`

**Filtres disponibles via GET :**

| Paramètre | Valeurs acceptées |
|---|---|
| `secteur` | `bocage`, `littoral`, `marais`, `plaine` |
| `difficulte` | `facile`, `moyen`, `difficile`, `expert` |

**Exemples d'URL filtrées :**
```
/randos.php?secteur=bocage
/randos.php?difficulte=facile
/randos.php?secteur=littoral&difficulte=moyen
```

**Comportement :**
- Filtres combinables (AND en SQL)
- Actif mis en surbrillance dans la barre de filtres
- Si aucune rando publiée : empty state élégant avec lien vers missions.php
- Grille 3 colonnes → 2 colonnes → 1 colonne (responsive)

### rando.php — Fiche complète

**URL structure :** `/rando.php?slug=la-marche-des-marais`  
(ou `/rando.php?id=42` en fallback)

**Sections de la page :**

1. **Hero** — image de couverture en overlay + badges secteur/difficulté
2. **Breadcrumb** — Accueil > Randonnées > [Titre]
3. **Bandeau infos pratiques** — distance, durée, difficulté, commune, départ, bouton GPX
4. **Layout 2 colonnes** — blocs de contenu + aside sticky
5. **Section communauté** — placeholder "Photos bientôt disponibles"

**Aside sticky (colonne droite) :**
- Résumé infos pratiques
- Téléchargement GPX (si présent)
- Compteur membres ayant complété la rando
- Bouton partage Facebook
- Bouton "J'ai fait cette rando" (→ missions.php en V12, fonctionnel en V13)

**Redirect automatique :** si le slug ou l'ID ne correspond à aucune rando publiée, redirection vers `randos.php`.

---

## 5. Email de bienvenue Brevo

### Configuration requise

Dans le BO : **Admin > Système > Paramètres** (`admin/settings.php`)

| Clé setting | Description | Exemple |
|---|---|---|
| `brevo_api_key` | Clé API Brevo (v3) | `xkeysib-abc123...` |
| `brevo_welcome_template_id` | ID du template Brevo | `12` |
| `brevo_sender_email` | Email expéditeur | `bonjour@zone85.fr` |
| `brevo_sender_name` | Nom expéditeur | `ZONE85` |

### Déclenchement

L'email de bienvenue est déclenché automatiquement lors de la validation de l'inscription (`inscription.php` → confirmation email → `webhook_brevo_confirm.php`).

### Tester l'envoi

1. Aller dans `admin/settings.php` → section **Emails**
2. Renseigner les 4 clés ci-dessus
3. Cliquer "Envoyer un email de test" → renseigner une adresse de test
4. Vérifier la réception (vérifier les spams)
5. Si erreur : consulter les logs dans `logs/brevo_errors.log`

### Variables disponibles dans le template Brevo

```
{{ params.pseudo }}       → Pseudo du nouveau membre
{{ params.clan }}         → Clan choisi (bocage/littoral/marais)
{{ params.missions_url }} → URL vers missions.php
{{ params.profil_url }}   → URL vers profil.php
```

---

## 6. Réorganisation BO

Le menu admin V12 est restructuré en 3 sections distinctes.

### Structure du menu (ASCII)

```
╔══════════════════════════════════════════╗
║  ZONE85 — Back-office                    ║
╠══════════════════════════════════════════╣
║  ▼ CONTENUS                              ║
║    ├─ Randonnées       [randos.php]       ║
║    ├─ Les Échos        [articles.php]     ║
║    ├─ KTC Editorial    [ktc.php]          ║
║    └─ Médiathèque      [media.php]        ║
╠══════════════════════════════════════════╣
║  ▼ COMMUNAUTÉ                            ║
║    ├─ Membres          [users.php]        ║
║    ├─ Missions         [missions.php]     ║
║    ├─ Clans            [clans.php]        ║
║    ├─ Saisons          [seasons.php]      ║
║    └─ Hall de la Zone  [hall.php]         ║
╠══════════════════════════════════════════╣
║  ▼ SYSTÈME                               ║
║    ├─ Paramètres       [settings.php]     ║
║    ├─ Logs             [logs.php]         ║
║    ├─ Migrations       [migrations.php]   ║
║    └─ Maintenance      [maintenance.php]  ║
╚══════════════════════════════════════════╝
```

**Fichier à modifier :** `admin/includes/admin-nav.php`  
Ajouter les entrées Randonnées, KTC Editorial, Médiathèque dans la section CONTENUS.

---

## 7. Médiathèque (fondation)

La V12 pose les fondations de la médiathèque centralisée.  
Interface complète prévue en V13.

### Table `media_library`

```sql
CREATE TABLE IF NOT EXISTS `media_library` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`    VARCHAR(255) NOT NULL,
  `filepath`    VARCHAR(500) NOT NULL,
  `mime_type`   VARCHAR(100) NULL,
  `size_bytes`  INT UNSIGNED NULL,
  `alt_text`    VARCHAR(255) NULL,
  `entity_type` VARCHAR(80) NULL,   -- 'rando', 'article', 'ktc', etc.
  `entity_id`   INT UNSIGNED NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Dossiers uploads V12

```
uploads/
├── randos/       ← Images de couverture des randonnées
├── gpx/          ← Traces GPX téléchargeables
├── rando-blocks/ ← Images insérées dans les blocs
└── articles/     ← Images des articles Les Échos (existant)
```

**Permissions Plesk :** 755 sur les dossiers, 644 sur les fichiers.  
Uploader via Plesk File Manager → dossier `uploads/randos/` pour les covers.

---

## 8. Checklist de test V12

Cocher chaque point après vérification en pré-production.

### Migrations SQL

- [ ] Migration 021 importée sans erreur (table `randos`, `rando_blocks`, `rando_participations`)
- [ ] Migration 022 importée sans erreur (colonnes KTC)
- [ ] Migration 023 importée sans erreur (table `media_library`, clés settings Brevo)
- [ ] `SHOW TABLES` confirme la présence des 3 nouvelles tables

### Front — randos.php

- [ ] Page s'affiche sans erreur PHP (vérifier error_log)
- [ ] Hero visible avec dégradé vert-navy
- [ ] Barre de filtres affichée (2 rangées : secteur + difficulté)
- [ ] Filtre `?secteur=bocage` recharge la page et met le chip en surbrillance
- [ ] Filtre `?difficulte=facile` fonctionne
- [ ] Filtres combinés `?secteur=marais&difficulte=moyen` fonctionnent
- [ ] Empty state affiché si la table `randos` est vide (texte + lien missions.php)
- [ ] Grille passe en 2 colonnes en dessous de 960px (tester DevTools)
- [ ] Grille passe en 1 colonne en dessous de 600px

### Front — rando.php

- [ ] Accès par slug : `rando.php?slug=test-rando` fonctionne si la rando existe
- [ ] Redirect vers `randos.php` si slug inexistant ou rando non publiée
- [ ] Hero affiche image de couverture en overlay (si présente)
- [ ] Badges secteur et difficulté affichés dans le hero
- [ ] Breadcrumb visible et liens corrects (Accueil > Randonnées > Titre)
- [ ] Bandeau infos pratiques : distance, durée, difficulté, commune affichés
- [ ] Bouton GPX visible uniquement si `gpx_url` renseigné
- [ ] Blocs `text` rendus correctement avec `nl2br`
- [ ] Bloc `info` avec fond bleu clair et icône ℹ️
- [ ] Bloc `conseil` avec fond rouge clair et icône 🎯
- [ ] Bloc `youtube` : iframe 16:9 visible (tester avec un vrai ID YouTube)
- [ ] Bloc `map` : placeholder "Carte interactive — bientôt disponible"
- [ ] Aside sticky visible sur desktop avec infos résumées
- [ ] Compteur de complétions affiché (0 si table vide)
- [ ] Bouton partage Facebook pointe vers `sharer.php?u=URL_encodée`
- [ ] Si connecté : bouton "J'ai fait cette rando" pointe vers missions.php
- [ ] Si non connecté : bouton "Rejoindre la Zone" → inscription.php
- [ ] Aside passe en position statique en dessous de 960px
- [ ] Placeholder communauté affiché en bas de page

### Admin Randos

- [ ] Page `admin/randos.php` accessible (role admin requis)
- [ ] Liste des randos affichée (vide = tableau vide sans erreur)
- [ ] Création d'une rando de test avec statut `draft`
- [ ] Passage en `published` → rando visible sur randos.php
- [ ] Ajout d'un bloc `text` via l'onglet Blocs
- [ ] Ajout d'un bloc `info` avec titre et texte
- [ ] Ordre des blocs modifiable (sort_order)
- [ ] Upload GPX enregistré dans `uploads/gpx/`

### Admin KTC Editorial

- [ ] Création d'un épisode KTC en `draft`
- [ ] Passage en `week1` → indice 1 visible sur ktc.php
- [ ] Passage en `week2` → indice 2 ajouté
- [ ] Passage en `revealed` → réponse visible

### Email Brevo

- [ ] Clé API Brevo renseignée dans Settings
- [ ] Template ID renseigné
- [ ] Email de test envoyé depuis admin/settings.php
- [ ] Email reçu avec les variables `pseudo` et `clan` correctes

### SEO & performance

- [ ] `<title>` correct sur randos.php et rando.php
- [ ] `<meta description>` renseignée
- [ ] Canonical URL correcte sur les deux pages
- [ ] Schema JSON-LD BreadcrumbList valide (tester avec Rich Results Test)
- [ ] Images avec attribut `loading="lazy"`
- [ ] Aucun appel JS externe (pas de CDN jQuery, Bootstrap, etc.)
- [ ] Console navigateur : 0 erreur JS en mode non-connecté

### Sécurité & robustesse

- [ ] Paramètre `slug` filtré via `safe_input()` (pas d'injection SQL possible)
- [ ] Paramètre `secteur` validé par whitelist stricte
- [ ] Paramètre `difficulte` validé par whitelist stricte
- [ ] `PDOException` catchée silencieusement — page ne plante pas si DB inaccessible
- [ ] Redirect 302 vers randos.php si rando non trouvée (rando.php)
- [ ] Contenu des blocs `htmlspecialchars()` avant affichage (XSS impossible)
