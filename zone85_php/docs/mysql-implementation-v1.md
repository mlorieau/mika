# Zone 85 — Implémentation MySQL v1

> Ce document explique comment installer et activer la base MySQL pour ZONE85.  
> Par défaut, le site fonctionne en mode données mockées (`DB_ENABLED = false`).

---

## 1. Prérequis

- **PHP** 8.0 ou supérieur (extension PDO + PDO_MySQL activée)
- **MySQL** 8.0+ ou **MariaDB** 10.5+
- Accès à un terminal avec le client `mysql`
- Les fichiers `database/schema.sql` et `database/seed.sql` présents dans le projet

---

## 2. Créer la base MySQL

Connectez-vous en tant que root et exécutez les commandes suivantes :

```sql
CREATE DATABASE IF NOT EXISTS zone85
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'zone85_user'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';

GRANT SELECT, INSERT, UPDATE, DELETE ON zone85.* TO 'zone85_user'@'localhost';

FLUSH PRIVILEGES;
```

> Remplacez `MOT_DE_PASSE_FORT` par un mot de passe sécurisé que vous stockerez
> dans une variable d'environnement (voir section Sécurité).

---

## 3. Importer le schéma

```bash
mysql -u root -p zone85 < database/schema.sql
```

Le schéma crée les tables suivantes :
`clans`, `seasons`, `games`, `missions`, `badges`, `users`, `user_badges`,
`xp_logs`, `clan_score_logs`, `season_trophies`, `hall_items`, `participations`.

---

## 4. Importer les données de test

```bash
mysql -u root -p zone85 < database/seed.sql
```

Le fichier seed peuple les tables avec :
- Les 3 clans (Bocage, Littoral, Marais)
- Une saison active de démonstration
- Des missions, badges et utilisateurs fictifs pour les tests

---

## 5. Activer la connexion

Dans `includes/config.php`, passez `DB_ENABLED` à `true` et renseignez les credentials :

```php
define('DB_ENABLED', true);
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'zone85');
define('DB_USER',    'zone85_user');
define('DB_PASS',    getenv('ZONE85_DB_PASS') ?: ''); // variable d'environnement recommandée
define('DB_CHARSET', 'utf8mb4');
```

> En développement local, vous pouvez mettre le mot de passe directement dans `DB_PASS`.  
> En production, utilisez une variable d'environnement (voir section Sécurité).

---

## 6. Mode fallback mock

Le site est conçu pour fonctionner **avec ou sans base de données**.

**Fonctionnement :**

1. `includes/db.php` tente la connexion PDO au démarrage.
2. Si `DB_ENABLED = false` → la fonction `db()` retourne `null` immédiatement.
3. Si la connexion échoue (mauvais credentials, serveur absent) → `db()` retourne également `null` après avoir loggé l'erreur discrètement.
4. Chaque fonction de `includes/repositories.php` commence par `if (!$pdo) return null;`.
5. Les pages PHP détectent le retour `null` et basculent sur les données de `includes/data.php`.

**Résultat :** le site reste entièrement fonctionnel en mode mockée, sans aucun message d'erreur visible pour l'utilisateur.

---

## 6.5 Compatibilité des repositories avec les données mockées

Les fonctions de `includes/repositories.php` sont conçues pour retourner exactement
les mêmes structures que les variables de `includes/data.php`. Cela permet une
migration progressive, page par page, sans réécrire le HTML.

### Règles de compatibilité appliquées

| Champ | Comportement repository | Fallback si absent |
|-------|------------------------|-------------------|
| `race_width` | calculé : `(score/max) × 94` | `0` |
| `podium_rank` | calculé par rang dans le résultat trié | `1` à `3` |
| `podium_id` | `'p1'`, `'p2'`, `'p3'` | `'p1'` |
| `top_members` | sous-requête par clan | `[]` (jamais null) |
| `trophies` | COUNT depuis `season_trophies` | `0` |
| `members` | alias de `members_count` | `0` |
| `race_progress` | scores de clan en % du max | `['bocage'=>0, …]` |
| `xp_season` | SUM depuis `xp_logs` depuis saison active | `0` |
| `xp_this_season` | idem (profil utilisateur) | `0` |

### Compatibilité MySQL / MariaDB

- Aucune variable utilisateur `@rank` — le rang est calculé côté PHP (compatible MySQL 8+ et MariaDB 10.5+).
- `FIELD()` est supporté sur les deux moteurs pour trier les raretés de badges.
- `SET FOREIGN_KEY_CHECKS = 0` dans `reset.sql` remplace le `ALTER TABLE DROP FOREIGN KEY` non portable.

### Champs présents dans data.php mais non encore chargés depuis MySQL

Ces champs nécessitent une session utilisateur authentifiée — ils resteront mockés jusqu'à l'implémentation de l'auth :

- `obtained` et `progress` sur les badges (requiert `user_badges` pour l'utilisateur connecté)
- `rank_in_clan` et `rank_total` sur le profil (calculés périodiquement via cron)
- `avatar` (emoji ou photo — lié à `avatar_type` et `avatar_file`)

---

## 7. Pages connectées à MySQL (repositories prêts)

| Page | Fonctions repository utilisées |
|------|-------------------------------|
| `clans.php` | `fetch_all_clans()`, `fetch_top_members_by_clan()` |
| `missions.php` | `fetch_featured_missions()`, `fetch_missions_by_type()`, `fetch_active_season()` |
| `classement.php` | `fetch_top_members()`, `fetch_current_season_scores()`, `fetch_trophies()` |
| `hall.php` | `fetch_hall_contributors()`, `fetch_hall_photos()` |
| `profil.php` | `fetch_user_profile()`, `fetch_badges()` (mock actif — auth non implémentée) |

Pour chaque page, le pattern d'intégration est :

```php
require_once __DIR__ . '/includes/repositories.php';
$clans = fetch_all_clans() ?? $clans; // fallback sur data.php si null
```

---

## 8. Ce qui reste en mode mock

Les fonctionnalités suivantes ne sont **pas encore connectées à MySQL** et continuent
d'utiliser des données statiques ou ne font aucun traitement persistant :

- **Formulaires** : inscription, contact, signalement — les soumissions ne sont pas enregistrées
- **Upload de photos** : la gestion des fichiers n'est pas implémentée (`UPLOAD_PATH` est défini mais inactif)
- **Paiement** : aucune table ni logique de paiement n'est présente dans v1
- **Authentification** : pas de session utilisateur persistante — `$mock_user` dans data.php est statique
- **Administration** : pas de back-office, pas de gestion de contenu via interface

---

## 9. Prochaines étapes

| Priorité | Fonctionnalité | Fichiers concernés |
|----------|---------------|-------------------|
| 1 | Authentification (register/login/logout) | `auth.php`, `session.php` |
| 2 | Sessions utilisateur persistantes | `includes/session.php`, pages profil |
| 3 | Upload de photos avec validation | `includes/upload.php`, `missions/photo.php` |
| 4 | Back-office modération | `admin/` (nouveau répertoire) |
| 5 | Paiement (accès jeux payants) | `includes/payment.php` |
| 6 | Calcul automatique des classements | Cron job ou triggers MySQL |

---

## 10. Sécurité en production

**Ne jamais committer les credentials MySQL dans le dépôt git.**

### Option recommandée : variable d'environnement

Définissez la variable dans la configuration serveur (Apache, Nginx, PHP-FPM) :

```bash
# Exemple dans /etc/environment ou dans la config du vhost
export ZONE85_DB_PASS="votre_mot_de_passe_fort"
```

Puis dans `config.php` :

```php
define('DB_PASS', getenv('ZONE85_DB_PASS') ?: '');
```

### Fichier .env (alternative)

Si vous utilisez un fichier `.env`, assurez-vous qu'il est exclu du dépôt :

```bash
# .gitignore
.env
.env.local
```

### Règles générales

- Utilisez un utilisateur MySQL dédié (`zone85_user`) avec uniquement les droits nécessaires (SELECT, INSERT, UPDATE, DELETE — pas de DROP, pas de CREATE).
- Activez SSL/TLS pour les connexions MySQL en production.
- Consultez `docs/security-checklist-v1.md` pour la liste complète des points de sécurité à valider avant la mise en ligne.
