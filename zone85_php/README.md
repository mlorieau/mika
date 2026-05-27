# Zone 85 — Version PHP modulaire

Site communautaire de gamification vendéenne.  
Architecture PHP modulaire (sans framework), données mockées, prêt pour migration MySQL.

---

## Structure

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
│
├── includes/
│   ├── config.php          Constantes (chemins, SITE_NAME…)
│   ├── data.php            Données mockées (TODO: → MySQL)
│   ├── functions.php       Fonctions utilitaires
│   ├── header.php          <!DOCTYPE>…<body>
│   ├── nav.php             <nav>
│   └── footer.php          <footer>…</html>
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
    └── mysql-model-v1.md   Schéma MySQL cible
```

---

## Template de page

```php
<?php
$page_title       = '…';
$page_description = '…';          // optionnel
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

## Données disponibles (data.php)

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

## Prochaine étape

Voir `docs/mysql-model-v1.md` pour le schéma MySQL cible.  
Remplacer les tableaux de `data.php` par des requêtes PDO/MySQLi.
