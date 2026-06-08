# Roadmap Sprints Zone85 — V12 → V13

> Dernière mise à jour : 2026-06-08 — passe pré-bêta  
> Objectif général : rendre Zone85 plus clair, plus guidé, plus mobile, plus rassurant, plus simple à comprendre avant la bêta privée.

---

## Sprint 1 — Stabilisation finale V12 clean ✅

**Objectif :** Verrouiller la base avant bêta.  
**Statut :** Terminé

- [x] Correction encodage `repositories.php`, `admin/seasons.php` — fix cp1252→utf-8 round-trip (0 mojibake restant)
- [x] `check-db.php` supprimé du projet (token hardcodé + infos sensibles)
- [x] Manifest PWA : shortcuts désactivés temporairement (icônes PNG absentes, commentaire de réactivation en place)
- [x] `robots.txt` renforcé (`/ajax/`, `/tools/`, `/check-db.php`, `/login.php`, `/forgot-password.php`)
- [x] `upload_collectible_media()` : `getimagesize`, dimensions max 2000px, `.htaccess`
- [x] Formulaire contact branché (`send_email` + templates `contact_message` / `contact_ack`)
- [x] KTC : upload photos, gagnant, confiance, XP/clan distribués
- [x] Communauté : hub redesigné (6 onglets : passeport, fil, clans, classement, zonautes, récompenses)

**Livrable :** V12 clean stable

---

## Sprint 2 — Onboarding & compréhension ✅

**Objectif :** Rendre l'entrée dans Zone85 évidente.  
**Statut :** Terminé

- [x] Page `comment-ca-marche.php` (5 étapes, ton chaleureux, CTA selon connexion)
- [x] Amélioration `bienvenue.php` (stepper 4 étapes, CTA selon état : clan → mission → passeport)
- [x] Phrases identitaires par clan sur `clans.php` ("Tu es plutôt…")
- [x] Vocabulaire PWA remplacé côté public ("Ajouter sur mon écran d'accueil")
- [x] Page `aide.php` — FAQ 4 sections, 9 questions, ton rassurant
- [x] Liens nav : "Comment ça marche ?", "Centre d'aide", "XP & Récompenses"

**Livrable :** Parcours nouveau membre compréhensible en moins de 30 secondes

---

## Sprint 3 — Première mission & gamification douce ✅

**Objectif :** Faire vivre immédiatement le système XP/clan.  
**Statut :** Terminé

- [x] Migration `034_v13_mission_premier_pas.sql` : mission tutorielle "Premier pas dans la Zone" (20 XP, auto-validée)
- [x] Mini-dashboard membre connecté sur `index.php` (3 cartes : mission conseillée, clan, progression XP)
- [x] États vides intelligents : `missions.php`, `notifications.php`, `trophees.php`
- [x] Fix inscription : XP réel = 50 (était 10, promis 50)

**Livrable :** Un nouveau membre peut gagner ses premiers XP dès sa première session

---

## Sprint 4 — Randos user friendly ✅

**Objectif :** Rendre les randos utilisables par des non-techniciens.  
**Statut :** Terminé

- [x] Bloc "Comment utiliser cette rando ?" avec aide GPX dépliable (applications citées, formulations simples)
- [x] Badges de lisibilité sur `randos.php` et `rando.php` (distance, durée, difficulté, GPX fourni, famille)
- [x] Badges de lisibilité sur `missions.php` (difficulté, durée, type, localisation)
- [x] CTA sticky mobile "Télécharger la trace" / "Participer" sur fiche rando
- [x] Aide GPX : texte pédagogique, applications recommandées (Komoot, Organic Maps, OsmAnd…)

**Livrable :** Une fiche rando compréhensible même pour quelqu'un qui ne connaît pas le GPX

---

## Sprint 5 — Clans & communauté ✅

**Objectif :** Rendre le choix du clan plus émotionnel et plus clair.  
**Statut :** Terminé

- [x] Aide au choix du clan — section "Tu es plutôt…" avec 3 pills sur `clans.php`
- [x] Phrases identitaires sous chaque carte podium (Bocage, Littoral, Marais)
- [x] Bouton "✓ Mon clan" pour les membres connectés (au lieu de "Rejoindre ce clan")
- [x] Onglet Récompenses dans `communaute.php` (XP, niveaux, badges avec étiquettes couleur)
- [x] Page `recompenses.php` — référentiel public XP / niveaux / badges
- [x] Mise en avant classement saison : barre de bataille + activité récente dans onglet Clans
- [x] Fix badge_reward_id : attribution automatique pour tous les types de missions (pas seulement hidden_hunt)
- [x] Guide BO dans `admin/mission-edit.php` : 3 chemins d'attribution de badges expliqués

**Livrable :** Les clans deviennent une vraie identité communautaire

---

## Sprint 6 — Bêta privée ✅

**Objectif :** Tester avec 5 à 10 personnes réelles et recueillir des retours structurés.  
**Statut :** Terminé

- [x] Page `feedback.php` — formulaire de retour bêta avec checklist interactive (10 points)
- [x] Checklist : inscription, connexion, missions, randos, profil, clans, classement, KTC, app mobile, PWA
- [x] Formulaire : pseudo, email, clan, appareil, note/5 (étoiles), zones testées, problèmes, suggestions
- [x] Sauvegarde en base (`contact_messages` avec `reason = 'beta_feedback'`) + notification email admin
- [x] Lien "Donner mon avis bêta" depuis `aide.php`
- [x] Ce fichier `docs/ROADMAP_SPRINTS_V12_V13.md`

**Livrable :** Retour bêta privée + liste corrections V13

---

## Bilan des fichiers — V12 → V13

### Fichiers créés

| Fichier | Description |
|---|---|
| `comment-ca-marche.php` | Page pédagogique "Zone85 en 30 secondes" |
| `aide.php` | Centre d'aide FAQ — 4 sections, 9 questions |
| `recompenses.php` | Référentiel public XP / niveaux / badges |
| `feedback.php` | Formulaire de retour bêta privée avec checklist |
| `database/migrations/025_v13_ktc_winner.sql` | Colonnes KTC winner + confidence |
| `database/migrations/034_v13_mission_premier_pas.sql` | Mission tutorielle "Premier pas dans la Zone" |
| `docs/ROADMAP_SPRINTS_V12_V13.md` | Ce document |

### Fichiers modifiés

| Fichier | Modification principale |
|---|---|
| `includes/auth.php` | XP inscription 10 → 50 |
| `includes/repositories.php` | Fix `badge_reward_id` toutes missions + encodage UTF-8 |
| `includes/nav.php` | Liens Récompenses, Aide, Comment ça marche ? + mobile |
| `index.php` | Mini-dashboard membre connecté (3 cartes) |
| `missions.php` | Badges de lisibilité (difficulté, durée, type) |
| `randos.php` | Badges de lisibilité (distance, durée, difficulté, GPX, famille) |
| `rando.php` | Bloc aide GPX dépliable + CTA mobile sticky |
| `clans.php` | "Mon clan", aide au choix, phrases identitaires |
| `communaute.php` | Onglet Récompenses + fix header padding |
| `aide.php` | CTA bêta vers feedback.php |
| `admin/mission-edit.php` | Guide BO — 3 chemins d'attribution de badges |
| `bienvenue.php` | Parcours onboarding contextuel (stepper 4 étapes) |
| `admin/seasons.php` | Fix encodage UTF-8 |
| `trophees.php` | Fix encodage UTF-8 + empty state |

---

## Checklist bêta pour les testeurs

### Inscription & connexion
- [ ] Créer un compte depuis zéro
- [ ] Vérifier l'email de bienvenue (reçu ? contenu clair ?)
- [ ] Comprendre les 50 XP offerts à l'inscription
- [ ] Choisir un clan

### Missions & gamification
- [ ] Faire la mission "Premier pas dans la Zone"
- [ ] Vérifier l'attribution des XP après validation
- [ ] Vérifier que le niveau monte si seuil atteint
- [ ] Vérifier qu'un badge éventuellement lié à la mission est attribué

### Randos
- [ ] Lire une fiche rando (badges de lisibilité visibles ?)
- [ ] Télécharger un fichier GPX
- [ ] Comprendre le bloc "Comment utiliser cette rando ?"
- [ ] Test sur mobile (bouton sticky visible ?)

### Profil & communauté
- [ ] Voir son passeport et ses badges
- [ ] Consulter le classement saison
- [ ] Voir la page Récompenses (XP, niveaux, badges)

### PWA
- [ ] Ajouter Zone85 sur l'écran d'accueil (Android ou iOS)
- [ ] Vérifier que l'icône s'affiche correctement
- [ ] Ouvrir l'app depuis l'écran d'accueil

---

## Points d'attention techniques (déploiement prod)

- `.env` prod : ne jamais committer, vérifier BREVO_API_KEY, DB_*, SITE_URL
- `check-db.php` : ✅ supprimé du projet — ne pas recréer en prod
- `uploads/` : vérifier permissions (755 dossiers, 644 fichiers)
- PHP `error_log` : activer en dev, désactiver ou rediriger en prod
- Sitemap : régénérer après ajout pages publiques (recompenses.php, aide.php, feedback.php, comment-ca-marche.php)
- Open Graph : vérifier les previews Facebook pour les nouvelles pages
- PWA icônes : générer les PNG depuis `assets/img/pwa/icon.svg` (72, 96, 128, 144, 152, 192, 384, 512px + shortcuts 96px)
  puis décommenter la section `shortcuts` dans `manifest.php`
