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

## Prochaines étapes

1. **Migration MySQL** — Voir `docs/mysql-model-v1.md` pour le schéma complet
2. **Authentification réelle** — Sessions sécurisées, `password_hash()`, rate limiting
3. **Back-office admin/modérateur** — Gestion missions, saisons, validation photos
4. **Upload photo sécurisé** — finfo, getimagesize, renommage, GD resize, modération
5. **Gamification réelle** — XP côté serveur, points clan, badges, anti-triche

---

## Ce qui n'est PAS encore actif

| Fonctionnalité | État | Référence |
|---|---|---|
| Base MySQL | Optionnel — `DB_ENABLED=false` par défaut, data.php en fallback | `docs/mysql-implementation-v1.md` |
| Authentification | Non actif — `$mock_user` fictif | `docs/security-checklist-v1.md` §4 |
| Upload réel | Non actif | `docs/security-checklist-v1.md` §7 |
| Paiement | Non actif | — |
| Back-office | Non actif | `docs/security-checklist-v1.md` §9 |
| Gamification réelle | Non actif — XP mockés | `docs/mysql-model-v1.md` |
