# ZONE85 — Système de badges V11

---

## Structure DB

### Table `badges`

| Colonne | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Identifiant auto |
| `title` | VARCHAR(100) | Nom affiché du badge |
| `slug` | VARCHAR(100) UNIQUE | Identifiant textuel unique (ex: `pionnier-zone`) |
| `category` | ENUM | Catégorie du badge (voir ci-dessous) |
| `description` | TEXT | Description de la condition d'obtention |
| `icon` | VARCHAR(20) | Emoji ou nom d'icône |
| `icon_emoji` | VARCHAR(8) | Emoji alternatif pour affichage inline |
| `rarity` | ENUM | Rareté du badge (voir ci-dessous) |
| `condition_type` | ENUM | Mécanisme d'attribution automatique |
| `condition_value` | INT | Valeur seuil (ex: 10 000 XP, 5 missions) |
| `color_primary` | VARCHAR(7) | Couleur HEX du badge (fond ou accent) |
| `season_id` | INT UNSIGNED | Badge lié à une saison spécifique (nullable) |
| `is_hidden` | TINYINT(1) | Si 1 : badge secret, non affiché avant obtention |
| `unlock_condition` | TEXT | Description longue de la condition (pour l'UI) |

### Table `user_badges`

| Colonne | Description |
|---|---|
| `user_id` | FK vers `users.id` |
| `badge_id` | FK vers `badges.id` |
| `source_type` | Origine : `manual`, `mission_success`, `hidden_hunt`, `ktc_correct`… |
| `source_id` | ID de la mission/participation/question source |
| `awarded_by` | ID admin si attribution manuelle |
| `awarded_at` | Timestamp d'attribution |

Contrainte UNIQUE sur `(user_id, badge_id)` — un badge ne peut être obtenu qu'une fois.

---

## Types de badges (condition_type)

| Type | Mécanisme | Exemple |
|---|---|---|
| `manual` | Attribution admin depuis `admin/badges.php` | "Oeil de Faucon" (photo coup de coeur) |
| `xp_threshold` | Attribution automatique quand `users.xp_total >= condition_value` | "Légende de la Zone" (10 000 XP) |
| `mission_success` | Attribution automatique au Nème succès de mission du bon type | "Quiz Addict" (10 quiz), "Chasseur de Randos" (5 randos) |
| `season` | Attribution après N saisons consécutives actif | "Fidèle du Littoral" (3 saisons) |
| `special` | Attribution programmatique dans le code (cas complexes) | "Chasseur Objets", "KTC Champion", "Flash Runner" |

---

## Raretés

| Rareté | Couleur | Signification |
|---|---|---|
| `common` | `#6b7f96` (gris-bleu) | Accessible à tous, conditions simples |
| `uncommon` | `#b8831a` (ocre) | Demande un effort modéré |
| `rare` | `#12314e` (navy) | Engagement sérieux requis |
| `epic` | `#9b59b6` (violet) | Réalisation remarquable |
| `legendary` | `#C9962A` (or) | Sommet de la progression Zone85 |

---

## Attribution automatique

L'attribution automatique des badges est déclenchée dans `includes/repositories.php` lors des événements suivants :

- **Complétion Hidden Hunt** (`process_collectible_found()`) : si la mission a un `badge_reward_id`, le badge est attribué en même temps que les XP, via `INSERT IGNORE INTO user_badges`. Le nom du badge est retourné dans la réponse JSON pour l'overlay "Bravo !".

- **Validation de participation** (missions manuelles/auto) : la fonction de validation dans repositories.php peut attribuer le badge lié à `missions.badge_reward_id`.

- **KTC bonne réponse** (`ajax/ktc-answer.php`) : peut déclencher l'attribution du badge `ktc-champion` (condition_type `special`) après 10 bonnes réponses.

- **Seuil XP** : à vérifier dans le code — doit être vérifié dans la fonction qui met à jour `users.xp_total` (comparer avec `badges` où `condition_type = 'xp_threshold'`).

- **Feed communautaire** : `push_community_feed()` avec `event_type = 'badge_unlock'` est appelé lors d'un badge gagné pour l'inscrire dans `community_feed`.

---

## Associer un badge à une mission

Pour qu'un badge soit automatiquement attribué à la complétion d'une mission :

1. Aller dans **Admin → Missions** → cliquer "Modifier" sur la mission souhaitée
2. Dans `/admin/mission-edit.php`, localiser le champ **Badge de récompense** (`badge_reward_id`)
3. Sélectionner le badge souhaité dans la liste déroulante
4. Enregistrer la mission

Le badge sera attribué lors de :
- La **validation admin** d'une participation (missions manuelles)
- La **complétion automatique** d'une Hidden Hunt (dernier objet trouvé)
- La **validation auto** d'une mission en mode automatique

---

## Créer un badge via admin

1. Aller dans **Admin → Badges** (`/admin/badges.php`)
2. Cliquer **"+ Nouveau badge"**
3. Remplir les champs :
   - **Titre** : nom affiché (ex: "Explorateur des Marais")
   - **Slug** : généré automatiquement ou saisir manuellement (unique, ex: `explorateur-marais`)
   - **Catégorie** : choisir parmi `exploration`, `clan`, `saison`, `meteo`, `rando`, `culture`, `invisible`, `general`
   - **Description** : condition lisible par l'utilisateur
   - **Icône** : emoji (ex: 🌿) ou nom d'icône
   - **Rareté** : `common` / `uncommon` / `rare` / `epic` / `legendary`
   - **Type de condition** : `manual`, `xp_threshold`, `mission_success`, `season`, `special`
   - **Valeur de condition** : nombre seuil si applicable (laisser vide pour `manual`)
   - **Couleur** : code HEX (utilisé comme couleur d'accent du badge)
4. Cliquer **"Créer le badge"**

Pour **attribuer manuellement** un badge à un membre :
1. Admin → Utilisateurs → cliquer sur le profil du membre
2. Section "Badges" → "Attribuer un badge" → choisir le badge → confirmer

---

## Badges actuels (19 badges — seeds V11)

| ID | Titre | Slug | Rareté | Condition |
|---|---|---|---|---|
| 1 | Pionnier de la Zone | `pionnier-zone` | rare | `special` — inscrit parmi les 500 premiers |
| 2 | Quiz Addict | `quiz-addict` | common | `mission_success` — 10 quiz complétés |
| 3 | Chasseur de Randos | `chasseur-randos` | common | `mission_success` — 5 randos validées |
| 4 | Oeil de Faucon | `oeil-faucon` | epic | `manual` — photo coup de coeur |
| 5 | Enquêteur du Bocage | `enqueteur-bocage` | uncommon | `mission_success` — 1er KTC résolu |
| 6 | Fidèle du Littoral | `fidele-littoral` | rare | `season` — 3 saisons consécutives actif |
| 7 | Météo-guerrier | `meteo-guerrier` | common | `mission_success` — 10 météo-missions |
| 8 | Légende de la Zone | `legende-zone` | legendary | `xp_threshold` — 10 000 XP à vie |
| 9 | Explorateur Bocage | `explorateur-bocage` | common | `mission_success` — 3 missions Bocage |
| 10 | Marin du Littoral | `marin-littoral` | common | `mission_success` — 3 missions Littoral |
| 11 | Enfant du Marais | `enfant-marais` | common | `mission_success` — 3 missions Marais |
| 12 | Chasseur Objets | `chasseur-objets` | rare | `special` — 5 objets cachés trouvés |
| 13 | Collecteur Légendaire | `collecteur-leg` | legendary | `special` — tous objets d'une saison |
| 14 | Zonaute Météo | `zonaute-meteo` | common | `mission_success` — 1ère mission météo |
| 15 | Randonneur Vendée | `randonneur-vendee` | common | `mission_success` — 1ère rando Zone85 |
| 16 | KTC Champion | `ktc-champion` | rare | `special` — 10 bonnes réponses KTC |
| 17 | Guerrier de Saison | `guerrier-saison` | epic | `special` — grande mission saisonnière |
| 18 | Légende du Clan | `legende-clan` | legendary | `special` — clan gagnant d'une saison |
| 19 | Flash Runner | `flash-runner` | rare | `special` — 3 événements flash |

---

## Checklist de test

- [ ] Créer un badge `manual` depuis Admin → Badges → vérifier apparition dans la liste
- [ ] Attribuer manuellement le badge à un utilisateur → vérifier dans `user_badges` en DB
- [ ] Vérifier que le badge apparaît dans le profil public de l'utilisateur
- [ ] Créer une mission Hidden Hunt avec `badge_reward_id` configuré
- [ ] Compléter la chasse → vérifier que le badge apparaît dans l'overlay "Bravo !"
- [ ] Vérifier dans `user_badges` : `source_type = 'mission_success'`, `source_id = mission_id`
- [ ] Recharger la page profil → badge visible dans la section Badges
- [ ] Tenter d'attribuer le même badge deux fois → `INSERT IGNORE` doit empêcher le doublon (vérifier en DB)
- [ ] Vérifier les couleurs de rareté dans l'UI : common=gris-bleu, uncommon=ocre, rare=navy, epic=violet, legendary=or
- [ ] Badges groupés par catégorie dans l'onglet Passeport du profil
- [ ] `community_feed` contient une entrée `event_type = 'badge_unlock'` après attribution
- [ ] Badge `legende-zone` (seuil 10 000 XP) : créditer 10 000 XP en DB et vérifier attribution automatique
