# Roadmap Sprints Zone85 — V12 → V13

## Sprint 1 — Stabilisation finale V12 clean ✅
**Objectif :** Verrouiller la base avant bêta.
**Statut :** Terminé
- [x] Correction encodage repositories.php, trophees.php, admin/seasons.php
- [x] Vérification PWA (event aliases, manifest)
- [x] robots.txt renforcé (/ajax/, /tools/, /check-db.php, /login.php, /forgot-password.php)
- [x] upload_collectible_media() : getimagesize, dimensions max 2000px, .htaccess
- [x] Formulaire contact branché (send_email + templates contact_message / contact_ack)
- [x] KTC : upload photos, gagnant, confiance, XP/clan distribués
- [x] Communauté : hub 4 onglets redesigné
- [ ] Génération icônes PWA 192/512px
- [ ] Vérification .env prod complète
- [ ] Sauvegarde zip + dump SQL base
**Livrable :** V12 clean stable

---

## Sprint 2 — Onboarding & compréhension 🔄
**Objectif :** Rendre l'entrée dans Zone85 évidente.
**Statut :** En cours
- [x] Page "Comment ça marche ?" (comment-ca-marche.php + FAQ intégrée)
- [x] Amélioration bienvenue.php (stepper 4 étapes, CTA selon état)
- [x] Phrases identitaires par clan (clans.php)
- [x] Vocabulaire PWA remplacé ("Ajouter sur mon écran d'accueil")
- [ ] Lien "Comment ça marche ?" visible depuis l'accueil + nav
- [ ] Wording simplifié sur inscription.php (vérifier ton actuel)
- [ ] Email de bienvenue : vérifier contenu + lien mission conseillée
**Livrable :** Parcours nouveau membre compréhensible en moins de 30 secondes

---

## Sprint 3 — Première mission & gamification douce
**Objectif :** Faire vivre immédiatement le système XP/clan.
- [x] Migration 034 : mission "Premier pas dans la Zone" (20 XP, easy, location:home)
- [x] États vides intelligents (trophees, notifications, missions)
- [ ] Affichage mission conseillée sur bienvenue.php / index.php (requête DB)
- [ ] Mini-dashboard membre connecté sur index.php (3 cartes : mission, clan, niveau)
- [ ] Progression XP plus visible sur profil.php (barre animée vers prochain niveau)
- [ ] Notification automatique après première participation
**Livrable :** Un nouveau membre peut gagner ses premiers XP dès sa première session

---

## Sprint 4 — Randos user friendly
**Objectif :** Rendre les randos utilisables par des non-techniciens.
- [x] Bloc "Comment utiliser cette rando ?" avec aide GPX dépliable
- [ ] Badges de lisibilité sur fiches rando (distance, durée, difficulté, GPX fourni, famille)
- [ ] CTA sticky mobile "Télécharger la trace" / "Participer" sur fiche rando
- [ ] Test filtres randos mobile (vérifier UX scroll + tap)
- [ ] Test upload photo participation mobile (taille, compression)
- [ ] Indication claire quand une rando n'a pas de GPX
**Livrable :** Une fiche rando compréhensible même pour quelqu'un qui ne connaît pas le GPX

---

## Sprint 5 — Clans & communauté
**Objectif :** Rendre le choix du clan plus émotionnel et plus clair.
- [ ] Libellé nav "Communauté" → clarifier (option : "Clans & Classements" ou garder)
- [ ] Meilleure mise en avant classement saison sur communaute.php
- [ ] États vides trophées / saisons (déjà fait en Sprint 3)
- [ ] Page clan individuelle plus riche (top membres, historique saisons)
- [ ] Notification quand le clan change de rang
**Livrable :** Les clans deviennent une vraie identité communautaire

---

## Sprint 6 — Bêta privée
**Objectif :** Tester avec 5 à 10 personnes réelles.
- [ ] Checklist testeur (document à créer)
- [ ] Formulaire ou page feedback simple (aide.php ou page dédiée)
- [ ] Tests inscription complète (mobile + desktop)
- [ ] Tests randos : GPX + photo souvenir
- [ ] Tests missions : participation + validation admin
- [ ] Tests PWA : installation + notification
- [ ] Tests KTC : vote + révélation
- [ ] Corrections UX légères post-bêta
**Livrable :** Retour bêta privée + liste corrections V13

---

## Points d'attention techniques (toutes versions)
- `.env` prod : ne jamais committer, vérifier avant chaque déploiement
- `check-db.php` : supprimer du serveur prod
- `uploads/` : vérifier permissions (755 dossiers, 644 fichiers)
- PHP error_log : activer en dev, désactiver en prod
- Brevo : configurer BREVO_API_KEY en prod pour les emails transactionnels
- Sitemap : régénérer après ajout de nouvelles pages publiques
