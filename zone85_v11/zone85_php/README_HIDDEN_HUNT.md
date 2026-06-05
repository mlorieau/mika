# ZONE85 — Hidden Hunt V9.1 / V11

---

## Architecture

Le système Hidden Hunt (jeux de piste) est composé de :

| Composant | Fichier | Rôle |
|---|---|---|
| Table objets | `mission_collectibles` | Définition de chaque objet caché (position, image, page) |
| Table trouvailles | `user_collectibles` | Historique de chaque objet trouvé par chaque utilisateur |
| Rendu HTML | `components/hidden-collectibles.php` | Injecte les objets sur la page publique (0 HTML si aucun objet) |
| Endpoint AJAX | `ajax/collectible-found.php` | Reçoit le clic, appelle `process_collectible_found()`, retourne JSON |
| Logique métier | `includes/repositories.php` — `process_collectible_found()` | Transaction atomique : enregistrement, complétion, XP, badge |
| JS client | `assets/js/hidden-hunt.js` | Overlay "Bravo !", toast, gestion progression (~6KB vanilla JS) |
| Admin liste | `admin/collectibles.php` | Liste les objets d'une mission |
| Admin édition | `admin/collectible-edit.php` | Créer/modifier un objet caché |

### Flow d'un clic

```
Utilisateur clique sur l'objet
  → hidden-hunt.js POST vers ajax/collectible-found.php
    → Vérifie session (401 si non connecté)
    → Vérifie CSRF token (403 si absent/invalide)
    → Appelle process_collectible_found(user_id, collectible_id)
      → Anti-doublon (déjà trouvé ? retourne reason:'already_found')
      → Transaction PDO :
          INSERT user_collectibles
          Compte found / total
          Si complétion :
            UPDATE participations → auto_validated
            INSERT xp_logs (hidden_hunt_completion)
            UPDATE users.xp_total
            INSERT clan_score_logs si clan actif
            INSERT user_badges si badge_reward_id
      → COMMIT
    → Retourne JSON {ok, found, total, completed, xp_awarded, badge_awarded}
  → JS affiche overlay Bravo ou toast "déjà trouvé"
```

---

## Créer une chasse

### Étape 1 — Créer la mission

1. Aller dans `/admin/mission-edit.php`
2. Remplir :
   - **Titre** : ex. "La Chasse du Bocage"
   - **Type** : `hidden_hunt`
   - **Validation** : `auto` (obligatoire pour Hidden Hunt — la validation est gérée par `process_collectible_found()`)
   - **XP participation** : `0` (l'XP est uniquement attribué à la complétion)
   - **XP réussite** : ex. `50` (crédité au dernier objet)
   - **Points clan réussite** : ex. `10`
   - **Badge de récompense** : sélectionner un badge optionnel (attribué à la complétion)
   - **Statut** : `active`
3. Cliquer **"Créer"**
4. Après sauvegarde : le bloc doré **"Gérer les objets cachés"** apparaît en bas de la page

### Étape 2 — Ajouter les objets

1. Cliquer sur **"Gérer les objets cachés"** → `/admin/collectibles.php?mission_id=X`
2. Cliquer **"+ Ajouter"** → `/admin/collectible-edit.php?mission_id=X`
3. Remplir pour chaque objet :
   - **Titre** : nom de l'objet (ex: "La Clé rouillée")
   - **Indice** (optionnel) : texte affiché dans l'overlay si configuré
   - **Page slug** : identifiant de la page où l'objet sera visible (ex: `index`, `missions`, `clans`, `classement`)
   - **Position desktop** : `position_top` en %, `position_left` en %
   - **Position mobile** : `position_top_mobile` / `position_left_mobile` en % (optionnel — fallback sur desktop si vide)
   - **Taille desktop** : en pixels (défaut 48px)
   - **Taille mobile** : en pixels (défaut 40px)
   - **Image** : upload PNG transparent (max 1MB — voir section PNG)
   - **GIF succès** (optionnel) : affiché dans l'overlay après trouvaille
   - **Titre succès** : texte du titre de l'overlay (défaut "Bravo !")
   - **Message succès** : texte du corps de l'overlay
4. Sauvegarder → vérifier l'apparition dans la liste

### Étape 3 — Vérifier côté public

- Aller sur la page correspondant au `page_slug` configuré
- L'objet doit flotter sur la page avec une légère animation
- Si aucun objet n'est configuré sur une page → **0 HTML injecté, 0 script chargé** (composant conditionnel)

---

## Positionnement des objets

Les positions sont exprimées en **pourcentages** par rapport au viewport/conteneur de la page :

```sql
-- Exemple : objet dans le coin supérieur droit
position_top  = 15.00   -- 15% depuis le haut
position_left = 82.00   -- 82% depuis la gauche
```

### Mobile fallback

Si `position_top_mobile` et `position_left_mobile` sont `NULL`, l'objet utilise la position desktop sur mobile. Sinon, le JS `hidden-hunt.js` applique les positions mobiles en dessous de 768px (`data-top-mobile` / `data-left-mobile` lus depuis les attributs HTML de l'élément `.hidden-collectible`).

La taille passe aussi automatiquement à `size_mobile` sur les écrans < 768px.

### Bonnes pratiques

- Éviter `position_top < 10%` (risque de recouvrir la navigation)
- Sur mobile, privilégier des positions entre 20% et 75% en top et 10% et 85% en left
- Tester sur plusieurs tailles d'écran avec DevTools (mode responsive)

---

## PNG transparents

Zone85 utilise des PNG à fond transparent pour les objets cachés, de façon à ne pas afficher de carré blanc autour des images.

### Configuration CSS requise

Le composant `components/hidden-collectibles.php` applique ces styles sur chaque élément `.hidden-collectible` :

```css
background: transparent !important;
box-shadow: none !important;
border: none !important;
```

### Créer un PNG transparent

1. Utiliser GIMP, Photoshop, ou Canva
2. Créer un fond transparent (couche alpha)
3. Dessiner ou coller l'objet sur ce fond
4. Exporter en **PNG-24** (avec transparence) — ne jamais exporter en JPEG (pas de canal alpha)
5. Taille recommandée : 96×96px à 200×200px (l'affichage est redimensionné via CSS)

### Vérification

- Dans DevTools, inspecter l'élément `.hidden-collectible` → aucun `background-color` visible, aucune `box-shadow`
- L'objet doit s'intégrer visuellement dans le contexte de la page sans encadré

---

## Attribution XP à la complétion

Les XP ne sont attribués qu'une seule fois, au moment où l'utilisateur trouve le **dernier objet** de la chasse.

### Flow détaillé dans `process_collectible_found()` (repositories.php L.1365+)

1. Récupération du collectible + mission + user en jointure
2. Anti-doublon : si déjà trouvé → retour immédiat sans XP
3. `INSERT INTO user_collectibles` (enregistre la trouvaille)
4. `SELECT COUNT(*)` → calcul du nombre total trouvé par l'utilisateur sur cette mission
5. Si `found >= total AND total > 0` → **complétion détectée**
6. `INSERT IGNORE INTO participations` (crée la participation si elle n'existait pas)
7. `UPDATE participations SET status='auto_validated'` (valide la participation)
8. Vérification anti-doublon XP : si participation déjà validée, on ne recrédite pas les XP
9. `INSERT INTO xp_logs` avec `source_type = 'hidden_hunt_completion'`
10. `UPDATE users SET xp_total = xp_total + xp_success`
11. Mise à jour du niveau (`level`) si la fonction `get_user_level_from_xp()` existe
12. `INSERT INTO clan_score_logs` si le user a un clan et une saison est active
13. Attribution du badge si `badge_reward_id` défini sur la mission
14. `COMMIT`

### Points clés

- Les XP sont ceux du champ `xp_success` de la mission (pas `xp_participation`)
- Le `source_type` dans `xp_logs` est `'hidden_hunt_completion'` (visible dans l'historique du profil avec l'icône 🗝️)
- La transaction est atomique : si une étape échoue, tout est rollback

---

## Badge de récompense

Le badge est optionnel et configuré via le champ `badge_reward_id` sur la mission.

### Attribution

- Déclenchée uniquement à la **complétion** (dernier objet trouvé)
- `INSERT IGNORE INTO user_badges` → anti-doublon garanti
- Le nom du badge est retourné dans la réponse JSON → affiché dans l'overlay "Mission accomplie !"

### Configuration

Dans `/admin/mission-edit.php`, champ **"Badge de récompense"** → sélectionner un badge existant depuis la liste.

Pour créer le badge avant d'en avoir besoin : voir `README_BADGES.md`.

---

## Progression dans le profil

### Section "Mes jeux de piste"

- Visible dans `/profil.php` si l'utilisateur a au moins une chasse commencée (au moins 1 objet trouvé)
- Alimente la fonction `fetch_user_hidden_hunts($user_id)` dans `repositories.php`
- Affiche pour chaque chasse :
  - Titre de la mission
  - Barre de progression (N objets trouvés / M total)
  - Statut : **En cours** (barre orange) ou **Terminée** (barre verte + badge vert "Terminée")
  - XP gagnés si terminée
  - Bouton "Commencer →" ou "Continuer →" si mission active non terminée

### Onglet Passeport

- Dans l'onglet `🗺️ Passeport` du profil → stat "Collectibles trouvés" : total d'objets trouvés toutes chasses confondues

---

## Checklist de validation

- [ ] Objet visible sur la page configurée (page_slug correspond à la page ouverte)
- [ ] Objet animé (légère flottaison CSS)
- [ ] Clic sans connexion → overlay ou redirection vers login (401 JSON côté AJAX)
- [ ] Clic connecté → overlay "Bravo !" avec progression N/total
- [ ] L'objet disparaît de la page après clic (retiré du DOM par hidden-hunt.js)
- [ ] Anti-doublon : recharger la page → l'objet ne réapparaît pas (déjà trouvé en DB)
- [ ] Anti-doublon : POST direct sur ajax/collectible-found.php avec un `collectible_id` déjà trouvé → `reason: 'already_found'`
- [ ] XP crédités à 0 si ce n'est pas le dernier objet
- [ ] XP crédités uniquement au dernier objet (vérifier dans `xp_logs` et sur profil.php)
- [ ] `xp_logs.source_type = 'hidden_hunt_completion'` présent après complétion
- [ ] Badge attribué si `badge_reward_id` configuré (vérifier `user_badges` en DB et overlay)
- [ ] Position mobile correcte (tester en dessous de 768px dans DevTools)
- [ ] PNG sans fond blanc (vérifier avec un fond de page coloré)
- [ ] `background: transparent` et `box-shadow: none` actifs sur `.hidden-collectible` (DevTools)
- [ ] Page sans objet configuré → 0 HTML hidden-hunt injecté, `hidden-hunt.js` non chargé
- [ ] CSRF token manquant → 403 JSON
- [ ] Session absente → 401 JSON
- [ ] `collectible_id` invalide ou `is_active = 0` → 400 JSON avec message approprié
- [ ] Mission inactive → `reason: 'mission_inactive'`
- [ ] Section "Mes jeux de piste" visible dans profil.php après 1er objet trouvé
- [ ] Barre de progression mise à jour en temps réel (après rechargement)
- [ ] Bouton "Terminée" vert après complétion de toute la chasse
- [ ] `community_feed` contient `event_type = 'collectible_found'` après trouvaille
