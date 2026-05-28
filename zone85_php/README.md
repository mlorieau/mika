# ZONE85 — Version PHP modulaire v1

Site communautaire de gamification vendéenne.  
Architecture PHP modulaire (sans framework), données mockées, prêt pour migration MySQL.

---

## Lancer en local

```bash
# PHP built-in server
cd zone85_php
php -S localhost:8080
# Ouvrir http://localhost:8080
```

---

## Structure des fichiers

```
zone85_php/
├── index.php               Accueil
├── concept.php             Le Concept
├── clans.php               Les 3 Clans
├── missions.php            Missions & Actions
├── classement.php          Classement
├── hall.php                Hall de la Zone
├── profil.php              Profil utilisateur (mock)
├── inscription.php         Tunnel d'inscription
├── contact.php             Contact
├── mentions-legales.php
├── confidentialite.php
├── cookies.php
├── cgu.php
├── sitemap.php             Sitemap XML dynamique
├── robots.txt              Directives robots
│
├── database/
│   ├── schema.sql          Schéma MySQL complet (18 tables)
│   ├── seed.sql            Données de test
│   ├── reset.sql           Suppression des tables
│   └── README.md           Guide d'installation MySQL
│
├── includes/
│   ├── config.php          Constantes (chemins, SITE_NAME…)
│   ├── data.php            Données mockées (TODO: → MySQL)
│   ├── functions.php       Fonctions utilitaires (e(), csrf_token(), set_security_headers()…)
│   ├── header.php          <!DOCTYPE>…<body> + balises SEO automatiques
│   ├── nav.php             <nav>
│   ├── footer.php          <footer>…</html>
│   ├── db.php              Connexion PDO (fallback automatique si DB_ENABLED=false)
│   └── repositories.php    Fonctions de lecture des données (MySQL ou mock)
│
├── components/
│   ├── clan-card.php
│   ├── mission-card.php
│   ├── badge-card.php
│   ├── trophy-card.php
│   ├── score-card.php
│   ├── season-banner.php
│   ├── profile-summary.php
│   └── contribution-card.php
│
├── assets/
│   ├── css/zone85.css
│   ├── js/zone85.js
│   └── img/
│
└── docs/
    ├── mysql-model-v1.md       Schéma MySQL cible
    ├── security-checklist-v1.md  Checklist sécurité complète
    └── seo-ai-ready-v1.md      Guide SEO & IA-Ready
```

---

## Base de données

Par défaut, le site fonctionne sans base MySQL grâce aux données mockées de `includes/data.php`.

### Mode mock (défaut)
`DB_ENABLED = false` dans `includes/config.php` → aucune connexion requise.

### Activer MySQL

1. Créer la base et l'utilisateur :
   ```sql
   CREATE DATABASE zone85 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'zone85_user'@'localhost' IDENTIFIED BY 'mot_de_passe_fort';
   GRANT ALL PRIVILEGES ON zone85.* TO 'zone85_user'@'localhost';
   ```

2. Importer le schéma et les données de test :
   ```bash
   mysql -u zone85_user -p zone85 < database/schema.sql
   mysql -u zone85_user -p zone85 < database/seed.sql
   ```

3. Configurer dans `includes/config.php` :
   ```php
   define('DB_ENABLED', true);
   define('DB_USER',    'zone85_user');
   define('DB_PASS',    'mot_de_passe_fort');
   ```

> **Ne pas utiliser en production sans sécuriser les credentials.**  
> Voir `docs/mysql-implementation-v1.md` pour le guide complet.

---

## Template de page

```php
<?php
$page_title       = '…';
$page_description = '…';          // optionnel
$canonical        = 'https://www.zone85.fr/page.php';
$robots           = 'index, follow';  // ou 'noindex, nofollow'
$og_image         = '/assets/img/og-page.jpg';  // optionnel
$schema_json_ld   = '…';          // bloc JSON-LD, optionnel
$current_page     = '…';          // slug nav active
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
$page_styles = '<style>/* CSS spécifique */</style>';   // optionnel
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- contenu de la page -->

<?php
$page_scripts = '<script>/* JS spécifique */</script>'; // optionnel
require_once 'includes/footer.php';
?>
```

---

## Variables disponibles (data.php)

| Variable | Type | Description |
|---|---|---|
| `$seasons` | array | 4 saisons (archived / active / upcoming) |
| `$active_season` | array\|null | Saison active calculée automatiquement |
| `$clans` | assoc array | 3 clans indexés par slug |
| `$missions` | array | Toutes les missions |
| `$games` | array | Types de jeux |
| `$badges` | array | Catalogue de badges |
| `$top_zonautes` | array | Classement global top 8 |
| `$season_trophies` | array | Palmarès des saisons passées |
| `$hall_contributors` | array | Top contributeurs du Hall |
| `$hall_photos` | array | Photos mises en avant |
| `$ktc_cases` | array | Dossiers Kéto Kolé Tché |
| `$randos` | array | Randos préférées |
| `$mock_user` | array | Profil fictif (→ session réelle) |

---

## SEO

### Variables SEO par page

Chaque page définit les variables suivantes avant d'inclure `header.php` :

| Variable | Rôle |
|---|---|
| `$page_title` | Balise `<title>` + og:title + twitter:title |
| `$page_description` | Balise `<meta name="description">` + og:description |
| `$canonical` | URL canonique absolue |
| `$robots` | `index, follow` ou `noindex, nofollow` |
| `$og_image` | Image Open Graph (chemin absolu ou relatif à la racine) |
| `$schema_json_ld` | Bloc JSON-LD (WebSite, BreadcrumbList, etc.) |

### Génération automatique

`header.php` génère automatiquement toutes les balises à partir de ces variables :
- `<title>`, `<meta description>`, `<link rel="canonical">`, `<meta robots>`
- Open Graph complet (`og:type`, `og:title`, `og:description`, `og:url`, `og:image`, `og:locale`)
- Twitter Card (`summary_large_image`)
- Bloc JSON-LD (`<script type="application/ld+json">`)

### Sitemap

- Fichier : `sitemap.php`
- URL publique : `https://www.zone85.fr/sitemap.php`
- Contenu : XML dynamique, 11 pages indexées, priorités et fréquences configurées
- Référencé dans `robots.txt`

### robots.txt

- Fichier texte en racine : `robots.txt`
- Exclut : `/profil.php`, `/inscription.php`, `/uploads/`, `/admin/`, `/back-office/`
- Autorise : tout le reste, y compris `/assets/`

Voir `docs/seo-ai-ready-v1.md` pour le guide complet.

---

## Sécurité

### Fonctions disponibles (functions.php)

| Fonction | Rôle |
|---|---|
| `e($val)` | Échappe une valeur pour l'affichage HTML (`htmlspecialchars` + `ENT_QUOTES` + `UTF-8`) |
| `csrf_token()` | Génère ou retourne le token CSRF de la session |
| `set_security_headers()` | Envoie les headers HTTP de sécurité (CSP, X-Frame-Options, etc.) |

### Règles fondamentales

- `e()` est utilisé sur **toutes** les sorties dynamiques — jamais de `echo` direct sur une entrée utilisateur
- `csrf_token()` est inclus dans chaque formulaire POST
- `set_security_headers()` est appelé dès le chargement de `config.php`

### Headers HTTP actifs

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy` (version permissive dev, à renforcer en prod)

Voir `docs/security-checklist-v1.md` pour la checklist complète et le plan v2.

---

## État actuel (v6.2 — Correctifs inscription, avatar, sous-dossier)

### Changements V6.2

| Correction | Détail |
|---|---|
| **Bug inscription front** | `register_user()` : paramètre `:uid` dupliqué dans xp_logs INSERT → PDO `HY093` → rollback silencieux. Corrigé avec `:src_id` distinct. |
| **Transaction atomique** | `register_user()` enveloppé dans `beginTransaction()` / `commit()` / `rollBack()`. user + xp_logs + legal_acceptances en tout-ou-rien. Badge hors transaction (non-bloquant). |
| **Double sélecteur fichier** | `<label for="photo-upload" onclick="...click()">` — le `onclick` était redondant avec `for`. Supprimé. |
| **Avatar upload invisible** | `.htaccess` : `php_flag engine off` causait une 500 sur FastCGI/FPM. Enveloppé dans `<IfModule mod_php.c>` etc. |
| **BASE_URL sous-dossier** | `config.php` : ajout de `define('BASE_URL', '/test/zone85_php/')`. Configurable selon le serveur. |
| **avatar_url($user)** | `functions.php` : nouvelle fonction centralisée. Gère les deux structures (session + profil). Utilisée dans `nav.php` et `profil.php`. |
| **url() / upload_url()** | `functions.php` : helpers d'URL absolues basés sur `BASE_URL`. |
| **Debug dev inscription** | En `APP_ENV=dev`, `debug_error` retourné dans JSON et loggé en `console.warn`. |
| **Debug dev profil** | Commentaire HTML `<!-- avatar-debug: ... -->` en dev uniquement. |
| **db-check enrichi** | `legal_acceptances` et `user_badges` ajoutés à la liste des tables vérifiées. |
| **Migration 002** | `database/migrations/002_auth_fix.sql` : CREATE TABLE IF NOT EXISTS pour les 3 tables auth + badge pionnier-zone. |

---

## État actuel (v6.1 — Auth V1 stabilisée)

| Composant | État |
|---|---|
| PHP modulaire | ✓ Opérationnel |
| MySQL + repositories | ✓ Actif (DB_ENABLED configurable) |
| Fallback data.php | ✓ Automatique si DB indisponible |
| Pages publiques | ✓ Alimentées par MySQL |
| **Inscription réelle** | ✓ Multi-étapes, validation, hash password, clan, avatar, CGU |
| **Connexion / Déconnexion** | ✓ Session PHP sécurisée, `login.php` / `logout.php` |
| **Session timeout** | ✓ Expiration auto après 1h d'inactivité (`SESSION_TIMEOUT`) |
| **Profil connecté** | ✓ Données réelles, badges réels, historique XP réel |
| **XP de bienvenue** | ✓ +50 XP à l'inscription, ligne dans `xp_logs` |
| **Badge bienvenue** | ✓ "Pionnier de la Zone" attribué si disponible |
| **legal_acceptances** | ✓ CGU + confidentialité enregistrées à l'inscription |
| **Avatar preset/upload** | ✓ Emoji au choix ou photo uploadée (jpg/png/webp, max 2 Mo) |
| **Nav connecté/déconnecté** | ✓ Menu adaptatif selon état de session |
| **CSRF unifié** | ✓ `csrf_field()` helper utilisé dans tous les formulaires POST |
| **Contact → DB** | ✓ Messages enregistrés dans `contact_messages` (CSRF + ip_hash) |
| **Protection outils dev** | ✓ `DEV_TOOLS_ALLOWED` + `APP_ENV` requis pour db-check.php |
| **Uploads sécurisés** | ✓ `.htaccess` Apache 2.4 (php_flag engine off, Require all denied) |
| Back-office | Non |
| Moteur de participation | Non |
| Paiement | Non |

---

## Authentification V1

### Inscription

L'inscription est un tunnel multi-étapes (6 étapes) :
1. Infos compte (prénom, nom, email, mot de passe)
2. ADN vendéen (cosmétique — stocké dans avatar_config)
3. Code de la Zone (acceptation des règles)
4. Choix du clan (bocage / littoral / marais)
5. Avatar emoji ou upload photo + pseudo + CGU obligatoires
6. Bienvenue — connexion automatique + redirection profil

À la création du compte :
- `password_hash()` avec `PASSWORD_BCRYPT`
- `+50 XP` crédités, ligne dans `xp_logs` (source_type = registration)
- Acceptations dans `legal_acceptances` (cgu_v1 + confidentialite_v1)
- Badge "Pionnier de la Zone" si disponible dans `user_badges`

### Connexion

```
GET/POST login.php
```

- Vérifie email + `password_verify()`
- Crée session PHP sécurisée (HTTPOnly, SameSite=Lax, Secure en HTTPS)
- Régénère l'ID de session à la connexion (`session_regenerate_id(true)`)
- Message générique "Identifiants incorrects" (ne révèle pas si l'email existe)

### Déconnexion

```
GET logout.php
```

- Vide `$_SESSION`, supprime le cookie, `session_destroy()`
- Redirige vers `index.php`

### Session

La session est démarrée automatiquement dans `includes/config.php` (avant tout output).

`$_SESSION['user']` contient :

```php
[
  'id'          => int,
  'pseudo'      => string,
  'email'       => string,
  'clan_id'     => int,
  'clan_slug'   => string,   // 'bocage' | 'littoral' | 'marais'
  'level'       => int,
  'xp_total'    => int,
  'avatar_type' => string,   // 'preset' | 'upload'
  'avatar_key'  => string,   // emoji ou chemin fichier
  'role'        => string,   // 'member' | 'moderator' | 'admin'
]
```

Fonctions disponibles dans `includes/auth.php` :

| Fonction | Rôle |
|---|---|
| `is_logged_in()` | Retourne true si une session utilisateur est active |
| `current_user()` | Retourne le tableau `$_SESSION['user']` ou null |
| `require_login($url)` | Redirige vers $url si non connecté |
| `login_user($user)` | Crée la session + met à jour last_login_at |
| `logout_user()` | Détruit la session proprement |
| `find_user_by_email($email)` | Cherche un utilisateur actif par email |
| `find_user_by_id($id)` | Cherche un utilisateur actif par ID |
| `register_user($data)` | Crée un compte, XP, legal, badge |
| `upload_avatar($file)` | Valide et stocke un avatar photo |

### Avatar

**Preset (emoji)** : `avatar_type = 'preset'`, emoji stocké dans `avatar_config` (JSON).

**Upload photo** :
- Types acceptés : jpg, jpeg, png, webp
- Taille max : 2 Mo
- MIME type vérifié côté serveur (extension seule insuffisante)
- Nom de fichier : 32 hex aléatoires + extension
- Stockage : `uploads/avatars/`
- Protection : `.htaccess` interdit l'exécution PHP dans ce dossier
- `avatar_type = 'upload'`, chemin dans `avatar_file`

> TODO : recadrage carré automatique (pas encore implémenté)

### Sécurité uploads

Le dossier `uploads/avatars/` est protégé par `.htaccess` :
- PHP et scripts interdits
- Seules les extensions image autorisées en lecture
- Options -Indexes (pas de listing)

---

## Sécurité — détails V6.1

### Fonctions disponibles (functions.php)

| Fonction | Rôle |
|---|---|
| `e($val)` | Échappe pour l'affichage HTML (ENT_QUOTES + UTF-8) |
| `csrf_token()` | Génère ou retourne le token CSRF de la session |
| `csrf_field()` | Retourne le champ `<input type="hidden">` CSRF prêt à insérer |
| `verify_csrf_token($token)` | Vérifie le token avec `hash_equals()` |
| `set_security_headers()` | Envoie les headers HTTP de sécurité |

### Session timeout

`current_user()` vérifie automatiquement `$_SESSION['last_activity']`.  
Si la dernière activité date de plus de `SESSION_TIMEOUT` secondes (défaut : 3600), la session est détruite et `null` est retourné.  
La valeur se met à jour à chaque appel réussi.

### Outils de diagnostic

`tools/db-check.php` est protégé par double condition :
- `APP_ENV === 'dev'` ET `DEV_TOOLS_ALLOWED === true` requis
- Sinon : HTTP 403, message générique
- Les deux constantes sont dans `includes/config.php`
- Ne jamais passer `DEV_TOOLS_ALLOWED = true` en production

---

## Checklist de test V6.2

### Inscription
- [ ] 1. Inscription avec avatar preset — front affiche bienvenue (étape 6)
- [ ] 2. Inscription avec upload photo — front affiche bienvenue (étape 6)
- [ ] 3. Upload photo : un clic = une seule ouverture de fenêtre
- [ ] 4. User créé en base (`SELECT * FROM users WHERE pseudo = '...'`)
- [ ] 5. xp_logs créé (`SELECT * FROM xp_logs WHERE user_id = X`)
- [ ] 6. legal_acceptances créé (`SELECT * FROM legal_acceptances WHERE user_id = X`)
- [ ] 7. Doublon email bloqué proprement (message côté front)
- [ ] 8. Doublon pseudo bloqué proprement
- [ ] 9. Erreur DB → pas d'utilisateur partiel (transaction rollback)
- [ ] 10. En APP_ENV=dev, debug_error visible en console navigateur si erreur

### Avatar
- [ ] 11. Profil affiche avatar preset (emoji)
- [ ] 12. Nav affiche avatar preset (emoji)
- [ ] 13. Profil affiche photo uploadée (pas d'image cassée)
- [ ] 14. Nav affiche photo uploadée
- [ ] 15. URL directe `/test/zone85_php/uploads/avatars/xxx.png` accessible (200 OK)
- [ ] 16. `/test/zone85_php/uploads/avatars/test.php` → 403 Forbidden
- [ ] 17. En APP_ENV=dev : commentaire `<!-- avatar-debug: ... -->` visible dans source de profil.php

### Connexion
- [ ] 18. Connexion OK → profil connecté
- [ ] 19. Déconnexion → nav redevient Connexion/Rejoindre
- [ ] 20. Mauvais mot de passe → "Identifiants incorrects."
- [ ] 21. Profil sans session → page invité

### Pages publiques (régression)
- [ ] 22. Accueil, Clans, Missions, Classement, Hall — chargement OK
- [ ] 23. Contact → submit → message succès, entrée dans contact_messages

---

## Checklist de test V6.1

### Pages publiques (régression)
- [ ] Accueil `index.php` — chargement, clans, stats
- [ ] Concept `concept.php` — sections, saison active
- [ ] Clans `clans.php` — les 3 clans, scores
- [ ] Missions `missions.php` — liste missions
- [ ] Classement `classement.php` — tableau classement
- [ ] Hall `hall.php` — photos, contributeurs
- [ ] Contact `contact.php` — formulaire s'affiche, submit → message de succès, entrée dans `contact_messages`

### Inscription
- [ ] Étapes 1→5 naviguent sans erreur
- [ ] Étape 5 : validation JS (champs vides, CGU non cochée)
- [ ] Submit étape 5 → créer un compte → redirect profil
- [ ] Vérifier en base : `users`, `xp_logs` (+50 XP), `legal_acceptances`, `user_badges`
- [ ] Avatar emoji → affiché dans nav et profil
- [ ] Avatar upload (jpg ≤ 2 Mo) → fichier dans `uploads/avatars/`, affiché dans nav et profil
- [ ] Email déjà pris → message d'erreur côté serveur
- [ ] Pseudo déjà pris → message d'erreur côté serveur

### Connexion / Déconnexion
- [ ] `login.php` avec bon email + password → session ouverte, redirect profil
- [ ] `login.php` avec mauvais password → "Identifiants incorrects." (message générique)
- [ ] Déconnexion → nav redevient Connexion/Rejoindre
- [ ] Accès `profil.php` sans session → page invité (boutons Se connecter/Rejoindre)

### Profil connecté
- [ ] Pseudo, clan, niveau, XP total affichés correctement
- [ ] Badges : badge "Pionnier de la Zone" visible si base renseignée ; sinon message vide
- [ ] Activité XP : ligne "+50 XP — Bienvenue dans la Zone" visible si base renseignée ; sinon message vide

### Session timeout
- [ ] Modifier `SESSION_TIMEOUT = 1` dans config.php, attendre 2s, recharger → déconnecté automatiquement
- [ ] Remettre `SESSION_TIMEOUT = 3600`

### Sécurité
- [ ] `tools/db-check.php` avec `DEV_TOOLS_ALLOWED = false` → HTTP 403
- [ ] `uploads/avatars/test.php` → HTTP 403 (exécution PHP bloquée)
- [ ] CSRF : soumettre contact.php sans token → rejet (modifier token manuellement pour tester)

---

## Ce qui n'est pas encore fait

- **Back-office** — pas de dashboard admin, pas de modération
- **Moteur de participation** — inscription aux missions non persistée
- **Attribution XP côté serveur** — pas encore déclenchée par les participations
- **Attribution points clan** — calcul automatique non implémenté
- **Badges avancés** — attribution conditionnelle non automatisée
- **Paiement** — accès jeux premium non géré
- **Les Invisibles** — fonctionnalité non développée
- **Sessions persistantes** — pas de "rester connecté" (token long terme)
- **Email transactionnel** — confirmation d'inscription non envoyée (SMTP non configuré)

---

## Prochaines étapes recommandées

1. Moteur de participation — enregistrer réponses, valider, déclencher XP en base
2. Attribution XP côté serveur — jamais côté client
3. Attribution points clan — mis à jour à chaque participation
4. Déclenchement badges — vérification à chaque action
5. Back-office minimal — modération participations, validation photos
6. Email transactionnel — confirmation d'inscription (SMTP)
