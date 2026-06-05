# ZONE85 — Base de données MySQL

Schéma complet du projet ZONE85 — site communautaire vendéen gamifié.  
Moteur : InnoDB | Encodage : utf8mb4_unicode_ci

---

## Prérequis

- MySQL 8.0+ ou MariaDB 10.5+
- Un client MySQL en ligne de commande ou un outil comme TablePlus / DBeaver

---

## Installation complète

### Étape 1 — Créer l'utilisateur et la base

```sql
-- En tant que root MySQL :
CREATE USER 'zone85_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe_fort';
CREATE DATABASE IF NOT EXISTS zone85
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON zone85.* TO 'zone85_user'@'localhost';
FLUSH PRIVILEGES;
```

### Étape 2 — Importer le schéma (tables)

```bash
mysql -u zone85_user -p zone85 < database/schema.sql
```

### Étape 3 — Importer les données de test

```bash
mysql -u zone85_user -p zone85 < database/seed.sql
```

> Ces données sont fictives (emails `@example.test`). Ne pas utiliser en production.

### Étape 4 — Activer la connexion dans config.php

Ouvrir `includes/config.php` et modifier le bloc base de données :

```php
define('DB_ENABLED', true);          // ← passer à true
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'zone85');
define('DB_USER',    'zone85_user');
define('DB_PASS',    'votre_mot_de_passe_fort');
define('DB_CHARSET', 'utf8mb4');
```

> En production, utiliser une variable d'environnement pour `DB_PASS`
> plutôt qu'une valeur en dur.

### Étape 5 — Lancer le diagnostic

Ouvrir dans un navigateur (serveur PHP local) :

```
http://localhost:8080/tools/db-check.php
```

Ce script vérifie automatiquement :
- la connexion PDO
- la présence des 10 tables principales
- les données importées (seed)
- le bon fonctionnement des 5 repositories clés

**Résultat attendu :** tous les indicateurs en vert ✓

> ⚠ Supprimer ou protéger `tools/db-check.php` avant toute mise en production.

### Étape 6 — Tester les pages publiques

Une fois le diagnostic vert, vérifier les pages qui utilisent MySQL :

```
http://localhost:8080/clans.php
http://localhost:8080/missions.php
http://localhost:8080/classement.php
http://localhost:8080/hall.php
```

Le site doit se comporter identiquement au mode mock — les mêmes données
apparaissent, maintenant lues depuis MySQL.

---

## Reinitialiser la base

Pour supprimer toutes les tables et repartir de zéro :

```bash
mysql -u zone85_user -p zone85 < database/reset.sql
mysql -u zone85_user -p zone85 < database/schema.sql
# Optionnel : recharger les seeds
mysql -u zone85_user -p zone85 < database/seed.sql
```

---

## Structure des fichiers

| Fichier       | Rôle                                              |
|---------------|---------------------------------------------------|
| `schema.sql`  | Création de toutes les tables (19 tables)         |
| `seed.sql`    | Données fictives de test                          |
| `reset.sql`   | Suppression de toutes les tables (ordre FK safe)  |
| `README.md`   | Ce fichier                                        |

---

## Tables créées (ordre FK)

1. `clans` — Les 3 clans (bocage, littoral, marais)
2. `seasons` — Saisons de jeu (archivées, active, à venir)
3. `games` — Jeux rattachés aux saisons ou evergreen
4. `users` — Membres du site
5. `badges` — Badges attribuables
6. `missions` — Missions individuelles et collectives
7. `mission_options` — Options de réponse pour les quiz/votes
8. `media` — Fichiers uploadés (photos, avatars…)
9. `participations` — Réponses des membres aux missions
10. `user_progress` — Progression dans les jeux (chasse, narratif…)
11. `user_badges` — Attribution des badges aux membres
12. `xp_logs` — Historique des gains XP (à vie, jamais remis à zéro)
13. `clan_score_logs` — Points de clan par saison (remis à 0 chaque saison)
14. `season_trophies` — Trophées de fin de saison
15. `hall_items` — Contenu mis en avant dans le Hall de la Zone
16. `comments` — Commentaires sur missions et jeux
17. `contact_messages` — Formulaire de contact
18. `legal_acceptances` — Acceptations CGU/vie privée/cookies

---

## Documentation complémentaire

Voir `docs/mysql-implementation-v1.md` pour les détails d'architecture,
les choix techniques et les règles métier associées au schéma.

---

## Avertissement

**Ne pas utiliser ce schéma en production sans :**
- Sécuriser les credentials (ne jamais committer `.env` avec les mots de passe)
- Supprimer les données de seed (`seed.sql` est réservé au développement)
- Activer SSL sur la connexion MySQL
- Restreindre les privilèges MySQL au strict nécessaire
