# Zone 85 — Guide SEO & IA-Ready v1

> Référence SEO et optimisation pour les moteurs de recherche et les IA génératives.  
> Dernière révision : 2026-05-28

---

## Philosophie

Zone 85 adopte une approche SEO centrée sur le contenu utile et la lisibilité, aussi bien pour les moteurs de recherche classiques que pour les IA génératives (ChatGPT, Perplexity, Google SGE, Mistral…).

**Principes directeurs :**

- **Contenu d'abord.** Le SEO est une conséquence d'un contenu de qualité, pas l'inverse.
- **Phrase directrice du site :** "Je progresse pour moi. Je fais gagner mon clan."
- **Pas de bourrage de mots-clés.** Chaque phrase doit avoir un sens humain avant d'être optimisée.
- **Lisibilité IA.** Les moteurs génératifs extraient des faits. Chaque page doit pouvoir être comprise hors contexte.
- **Structure cohérente.** Un H1 par page, des H2 descriptifs, des introductions en 2-3 phrases max.
- **Maillage interne logique.** Les liens internes guident à la fois les robots et les lecteurs humains.

---

## Variables SEO par page

| Page | `$page_title` | `$page_description` | `$canonical` | `$robots` | `og:image` | Schema principal |
|---|---|---|---|---|---|---|
| `index.php` | Zone 85 — La communauté vendéenne qui joue, explore et contribue | Rejoins Zone 85 : missions, clans, classements et Hall de la Zone. Une gamification ancrée en Vendée. | `https://www.zone85.fr/` | `index, follow` | `/assets/img/og-home.jpg` | `WebSite` |
| `concept.php` | Le Concept — Zone 85 | Comprends comment Zone 85 fonctionne : saisons, clans Bocage / Littoral / Marais, missions et récompenses. | `https://www.zone85.fr/concept.php` | `index, follow` | `/assets/img/og-concept.jpg` | `BreadcrumbList` |
| `clans.php` | Les 3 Clans — Zone 85 | Bocage, Littoral ou Marais : choisis ton clan, contribue aux missions et hisse-le en tête du classement. | `https://www.zone85.fr/clans.php` | `index, follow` | `/assets/img/og-clans.jpg` | `BreadcrumbList` |
| `missions.php` | Missions & Actions — Zone 85 | Explore les missions actives de Zone 85 : quiz, photos, randos, investigations. Chaque action rapporte des XP. | `https://www.zone85.fr/missions.php` | `index, follow` | `/assets/img/og-missions.jpg` | `BreadcrumbList` |
| `classement.php` | Classement — Zone 85 | Suis le score des clans et le top des Zonautes. Le classement est mis à jour en temps réel chaque saison. | `https://www.zone85.fr/classement.php` | `index, follow` | `/assets/img/og-classement.jpg` | `BreadcrumbList` |
| `hall.php` | Hall de la Zone — Zone 85 | Découvre les photos et contributions mises en avant par la communauté Zone 85. | `https://www.zone85.fr/hall.php` | `index, follow` | `/assets/img/og-hall.jpg` | `BreadcrumbList` |
| `contact.php` | Contact — Zone 85 | Une question sur Zone 85 ? Écris-nous via le formulaire de contact. | `https://www.zone85.fr/contact.php` | `index, follow` | `/assets/img/og-default.jpg` | `BreadcrumbList` |
| `mentions-legales.php` | Mentions légales — Zone 85 | Mentions légales du site Zone 85. | `https://www.zone85.fr/mentions-legales.php` | `noindex, nofollow` | — | `BreadcrumbList` |
| `confidentialite.php` | Politique de confidentialité — Zone 85 | Politique de confidentialité et traitement des données personnelles sur Zone 85. | `https://www.zone85.fr/confidentialite.php` | `noindex, nofollow` | — | `BreadcrumbList` |
| `cookies.php` | Politique de cookies — Zone 85 | Politique d'utilisation des cookies sur Zone 85. | `https://www.zone85.fr/cookies.php` | `noindex, nofollow` | — | `BreadcrumbList` |
| `cgu.php` | Conditions générales d'utilisation — Zone 85 | Conditions générales d'utilisation du site et de la communauté Zone 85. | `https://www.zone85.fr/cgu.php` | `index, nofollow` | — | `BreadcrumbList` |
| `profil.php` | Mon profil — Zone 85 | — | `https://www.zone85.fr/profil.php` | `noindex, nofollow` | — | — |
| `inscription.php` | Rejoindre Zone 85 | — | `https://www.zone85.fr/inscription.php` | `noindex, nofollow` | — | — |

---

## Structure header.php

Le fichier `includes/header.php` génère automatiquement l'ensemble des balises SEO à partir des variables définies en début de page.

### Balises générées

```html
<!-- Charset et viewport -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- SEO de base -->
<title>{$page_title}</title>
<meta name="description" content="{$page_description}">
<link rel="canonical" href="{$canonical}">
<meta name="robots" content="{$robots}">

<!-- Open Graph (partage réseaux sociaux) -->
<meta property="og:type" content="website">
<meta property="og:title" content="{$page_title}">
<meta property="og:description" content="{$page_description}">
<meta property="og:url" content="{$canonical}">
<meta property="og:image" content="{$og_image}">
<meta property="og:site_name" content="Zone 85">
<meta property="og:locale" content="fr_FR">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$page_title}">
<meta name="twitter:description" content="{$page_description}">
<meta name="twitter:image" content="{$og_image}">

<!-- JSON-LD Schema -->
{$schema_json_ld}
```

### Variables attendues en début de page

```php
$page_title       = 'Titre de la page — Zone 85';
$page_description = 'Description courte, 150-160 caractères max.';
$canonical        = 'https://www.zone85.fr/page.php';
$robots           = 'index, follow';      // ou 'noindex, nofollow'
$og_image         = '/assets/img/og-page.jpg';  // optionnel, fallback sur og-default.jpg
$schema_json_ld   = '...';               // bloc JSON-LD, généré page par page
```

---

## JSON-LD par page

### index.php — WebSite

```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "Zone 85",
  "url": "https://www.zone85.fr",
  "description": "Communauté vendéenne de gamification : missions, clans, classements.",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://www.zone85.fr/missions.php?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
```

### concept.php — BreadcrumbList

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://www.zone85.fr/"},
    {"@type": "ListItem", "position": 2, "name": "Le Concept", "item": "https://www.zone85.fr/concept.php"}
  ]
}
```

### clans.php — BreadcrumbList

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://www.zone85.fr/"},
    {"@type": "ListItem", "position": 2, "name": "Les Clans", "item": "https://www.zone85.fr/clans.php"}
  ]
}
```

### missions.php — BreadcrumbList

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://www.zone85.fr/"},
    {"@type": "ListItem", "position": 2, "name": "Missions", "item": "https://www.zone85.fr/missions.php"}
  ]
}
```

### classement.php — BreadcrumbList

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://www.zone85.fr/"},
    {"@type": "ListItem", "position": 2, "name": "Classement", "item": "https://www.zone85.fr/classement.php"}
  ]
}
```

### hall.php — BreadcrumbList + CreativeWork (futur)

```json
[
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
      {"@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://www.zone85.fr/"},
      {"@type": "ListItem", "position": 2, "name": "Hall de la Zone", "item": "https://www.zone85.fr/hall.php"}
    ]
  },
  {
    "@context": "https://schema.org",
    "@type": "ImageGallery",
    "name": "Hall de la Zone",
    "description": "Photos et contributions mises en avant par la communauté Zone 85.",
    "url": "https://www.zone85.fr/hall.php"
  }
]
```

> Note v2 : Ajouter un schema `CreativeWork` par photo approuvée, avec `author`, `dateCreated`, `contentUrl`.

### contact.php — BreadcrumbList

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://www.zone85.fr/"},
    {"@type": "ListItem", "position": 2, "name": "Contact", "item": "https://www.zone85.fr/contact.php"}
  ]
}
```

### Pages légales — BreadcrumbList

Même structure pour `mentions-legales.php`, `confidentialite.php`, `cookies.php`, `cgu.php` avec le nom de la page en position 2.

---

## Sitemap

### Emplacement

`https://www.zone85.fr/sitemap.php` — Généré dynamiquement en PHP.

### Pages indexées

| Page | Priorité | Fréquence |
|---|---|---|
| `/` | 1.0 | daily |
| `/concept.php` | 0.9 | monthly |
| `/clans.php` | 0.9 | weekly |
| `/missions.php` | 0.9 | daily |
| `/classement.php` | 0.8 | daily |
| `/hall.php` | 0.8 | weekly |
| `/contact.php` | 0.5 | yearly |
| `/cgu.php` | 0.4 | yearly |
| `/mentions-legales.php` | 0.3 | yearly |
| `/confidentialite.php` | 0.3 | yearly |
| `/cookies.php` | 0.3 | yearly |

### Pages exclues du sitemap

- `/profil.php` — page personnelle, non indexable
- `/inscription.php` — tunnel privé
- Toutes les pages futures sous `/admin/` et `/back-office/`

---

## robots.txt

### Règles actuelles

```
User-agent: *
Allow: /

Disallow: /profil.php
Disallow: /inscription.php
Disallow: /uploads/
Allow: /assets/
Disallow: /admin/
Disallow: /back-office/

Sitemap: https://www.zone85.fr/sitemap.php
```

### Extensions futures

- Interdire les pages de résultats de recherche internes si une recherche est ajoutée (`?q=`)
- Interdire les URLs avec paramètres de session ou tokens
- Permettre aux robots d'images (`Googlebot-Image`) l'accès aux photos du Hall après modération

---

## Structure IA-ready

Les moteurs génératifs (ChatGPT, Perplexity, Google SGE) extraient du contenu de manière contextuelle. Zone 85 applique les règles suivantes pour maximiser la lisibilité des IA.

### Règles de contenu

**H1 unique par page**
- Un seul `<h1>` par page, descriptif et contenant les mots-clés principaux
- Différent du `<title>` mais complémentaire

**Introduction en 2-3 phrases**
- Chaque page commence par un résumé clair de son contenu
- Compréhensible sans avoir lu les autres pages (contexte autonome)
- Idéalement : une phrase de définition, une phrase de bénéfice, une phrase d'action

**H2 descriptifs**
- Chaque `<h2>` doit décrire précisément le contenu de la section
- Éviter les titres vagues ("En savoir plus", "Découvrez")
- Préférer des titres factuels ("Comment fonctionnent les saisons Zone 85 ?")

**Contenu compréhensible hors contexte**
- Éviter les pronoms sans antécédent clair ("il", "ça", "le truc")
- Nommer explicitement les entités (Bocage, Littoral, Marais, Zone 85)
- Données concrètes préférées aux généralités

**Listes et tableaux**
- Les listes à puces sont bien extraites par les IA
- Les tableaux avec en-têtes clairs sont optimaux pour les données comparatives
- Éviter les tableaux purement visuels sans données sémantiques

---

## Maillage interne recommandé

| Page source | Liens vers | Texte d'ancre recommandé |
|---|---|---|
| `index.php` | `concept.php` | "Découvrir le concept" |
| `index.php` | `clans.php` | "Choisir son clan" |
| `index.php` | `missions.php` | "Voir les missions actives" |
| `index.php` | `classement.php` | "Voir le classement" |
| `concept.php` | `clans.php` | "Les 3 clans vendéens" |
| `concept.php` | `missions.php` | "Toutes les missions" |
| `concept.php` | `inscription.php` | "Rejoindre Zone 85" |
| `clans.php` | `classement.php` | "Classement des clans" |
| `clans.php` | `missions.php` | "Missions en cours" |
| `missions.php` | `clans.php` | "Voir les clans" |
| `missions.php` | `hall.php` | "Hall de la Zone" |
| `classement.php` | `clans.php` | "Détail des clans" |
| `hall.php` | `missions.php` | "Participer aux missions" |
| `hall.php` | `clans.php` | "Rejoindre un clan" |
| `contact.php` | `cgu.php` | "Conditions générales" |
| `footer (toutes)` | `mentions-legales.php` | "Mentions légales" |
| `footer (toutes)` | `confidentialite.php` | "Politique de confidentialité" |
| `footer (toutes)` | `cookies.php` | "Politique de cookies" |
| `footer (toutes)` | `cgu.php` | "CGU" |

---

## Points à améliorer en v2

### Structured Data avancés

- [ ] **Schema `Article`** pour les entrées du Hall de la Zone (chaque photo approuvée avec auteur et date)
- [ ] **Schema `Event`** pour les missions de saison (date de début, date de fin, lieu si applicable)
- [ ] **Schema `FAQPage`** si une section FAQ devient visible publiquement
- [ ] **Schema `Person`** pour les profils publics (si les profils deviennent consultables)
- [ ] **Schema `Organization`** pour Zone 85 en tant qu'entité (logo, réseaux sociaux, contact)
- [ ] **Balises `hreflang`** si une version multilingue est envisagée (fr / en minimum)

### Gestion SEO éditoriale

- [ ] Champs SEO éditables dans le back-office (title, description, og:image par page)
- [ ] Prévisualisation du snippet Google lors de l'édition
- [ ] Génération de sitemap dynamique depuis MySQL (fréquences adaptées au contenu réel)
- [ ] Alertes automatiques si une page stratégique passe en `noindex`

### Performance

- [ ] Score Core Web Vitals cible : LCP < 2.5s, FID < 100ms, CLS < 0.1
- [ ] Images en format WebP avec attributs `width` et `height` pour éviter le CLS
- [ ] Lazy loading sur les images du Hall (`loading="lazy"`)
- [ ] Minification CSS/JS en production
- [ ] Cache HTTP sur les assets statiques (Cache-Control: max-age=31536000)

### Contenu

- [ ] Pages de clans avec contenu éditorial riche (histoire du territoire, missions passées)
- [ ] Pages de missions archivées consultables (bon pour le SEO long terme)
- [ ] Glossaire Zone 85 (XP, clan, Zonaute, saison…) — très bien indexé par les IA
- [ ] Blog ou journal de bord de la communauté

---

*Document maintenu par l'équipe Zone 85. Référence : https://schema.org, https://developers.google.com/search/docs*
