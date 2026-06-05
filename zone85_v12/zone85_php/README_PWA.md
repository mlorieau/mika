# ZONE85 — Guide PWA V10

---

## Architecture PWA

Le dispositif PWA de Zone85 repose sur trois fichiers principaux :

| Fichier | Rôle |
|---|---|
| `manifest.json` | Métadonnées de l'application (nom, icônes, couleurs, shortcuts, screenshots) |
| `service-worker.js` | Cache des assets statiques, stratégie réseau, page offline |
| `assets/js/pwa-install.js` | Enregistrement du SW, détection plateforme, bannière d'installation, tracking |

Le Service Worker est enregistré dans `pwa-install.js` au chargement de la page via `navigator.serviceWorker.register()`. La variable `window.ZONE85_BASE` doit être définie côté PHP (dans `header.php`) pour que le scope soit correct, notamment en cas d'installation dans un sous-dossier.

```js
// header.php (exemple)
<script>window.ZONE85_BASE = '<?= rtrim(BASE_URL, '/') ?>';</script>
```

---

## Contenu du manifest.json

```json
{
  "name": "ZONE85 — L'Esprit Vendée",
  "short_name": "Zone85",
  "display": "standalone",
  "orientation": "portrait-primary",
  "start_url": "/",
  "scope": "/",
  "background_color": "#f8f4ef",
  "theme_color": "#0c1e2e",
  "lang": "fr",
  "categories": ["games", "social", "entertainment"]
}
```

Les constantes PHP correspondantes dans `config.php` :

```php
define('PWA_APP_NAME',    'ZONE85');
define('PWA_SHORT_NAME',  'Zone85');
define('PWA_THEME_COLOR', '#0c1e2e');
define('PWA_BG_COLOR',    '#f8f4ef');
define('PWA_DISPLAY',     'standalone');
```

**Shortcuts déclarés :**
- `/missions.php` — Mes missions (icône `shortcut-missions.png`)
- `/profil.php` — Mon profil (icône `shortcut-profil.png`)
- `/classement.php` — Classement (icône `shortcut-classement.png`)

---

## Installation

### Android (Chrome / Edge / Samsung Internet)

1. Naviguer sur `zone85.fr` dans Chrome Android
2. Chrome affiche automatiquement un bandeau "Ajouter à l'écran d'accueil" (événement `beforeinstallprompt`)
3. Si le bandeau n'apparaît pas : menu (⋮) → "Installer l'application" ou "Ajouter à l'écran d'accueil"
4. Confirmer l'installation → l'icône Zone85 apparaît dans le tiroir d'applications
5. L'app s'ouvre en mode `standalone` (sans barre d'adresse)

### iOS (Safari)

Safari ne supporte pas l'événement `beforeinstallprompt`. L'installation est toujours manuelle :

1. Ouvrir `zone85.fr` dans **Safari** (pas Chrome iOS)
2. Appuyer sur le bouton **Partager** (icône carré avec flèche vers le haut, en bas de l'écran)
3. Faire défiler et choisir **"Sur l'écran d'accueil"**
4. Modifier le nom si souhaité → **Ajouter**

La bannière Zone85 sur iOS affiche automatiquement les instructions : "Appuie sur **⬆ Partager** puis **Sur l'écran d'accueil**" car `isIOS && isSafari` est détecté dans `pwa-install.js`.

> **Important** : Sur iOS, la bannière s'affiche mais il n'y a pas de bouton "Installer" — l'utilisateur doit suivre les instructions manuelles.

### Desktop (Chrome / Edge)

1. Naviguer sur `zone85.fr` dans Chrome ou Edge
2. Une icône d'installation apparaît dans la barre d'adresse (icône "moniteur avec flèche")
3. Cliquer sur cette icône → "Installer"
4. L'application s'ouvre dans une fenêtre dédiée sans barre de navigation

---

## Test du pop-up d'installation

### Après inscription (comportement attendu)

Lors de la redirection après une inscription réussie, ajouter le paramètre `?pwa_install=1` à l'URL de destination :

```php
// Dans inscription.php, après création du compte :
header('Location: ' . BASE_URL . 'index.php?pwa_install=1');
```

Ce paramètre force l'affichage immédiat de la bannière sans attendre 2 visites, même si `_deferredPrompt` n'est pas encore disponible.

### Conditions de déclenchement normales

La bannière s'affiche si **toutes** ces conditions sont réunies :

1. L'application n'est pas déjà installée (`display-mode: standalone` → false)
2. Le pop-up n'a pas été fermé (`localStorage z85_pwa_dismissed !== '1'`)
3. Le compteur de visites `z85_visits >= 2` **ou** `?pwa_install=1` dans l'URL
4. Pour Android/Desktop : l'événement `beforeinstallprompt` a été capturé par Chrome
5. Pour iOS Safari : `isIOS && isSafari` → instructions manuelles affichées

### localStorage keys utilisées

| Clé | Valeur | Description |
|---|---|---|
| `z85_visits` | entier (ex: `3`) | Compteur de visites, incrémenté à chaque chargement de page |
| `z85_pwa_dismissed` | `'1'` | Positionné quand l'utilisateur ferme la bannière ou installe l'app |

Pour **réinitialiser le comportement** en test :
```js
// Dans la console du navigateur :
localStorage.removeItem('z85_pwa_dismissed');
localStorage.setItem('z85_visits', '0');
location.reload();
```

---

## Test Lighthouse

Lighthouse permet de valider le score PWA et de détecter les erreurs de configuration.

### Procédure pas à pas

1. Ouvrir Chrome et naviguer sur l'URL de prod (ou `localhost:8080` en local avec HTTPS via `ngrok` ou `mkcert`)
2. Ouvrir **DevTools** (F12) → onglet **Lighthouse**
3. Sélectionner la catégorie **"Progressive Web App"** (et optionnellement Performance, Accessibilité)
4. Choisir **Mobile** pour simuler les conditions réelles
5. Cliquer **"Analyser la page"** → attendre ~30 secondes
6. Lire le rapport — les points PWA sont dans la section "PWA"

### Points de contrôle Lighthouse PWA

- Manifest valide et détectable
- Service Worker enregistré avec fetch handler
- HTTPS obligatoire (ou localhost)
- Icônes 192px et 512px présentes et accessibles
- `start_url` répond avec un statut 200 même hors ligne
- `theme-color` meta défini dans le HTML

---

## Icônes PWA

### Tailles requises (déclarées dans manifest.json)

| Fichier | Taille | Usage |
|---|---|---|
| `assets/img/pwa/icon-72.png` | 72×72 | Favicon PWA legacy |
| `assets/img/pwa/icon-96.png` | 96×96 | Android launcher |
| `assets/img/pwa/icon-128.png` | 128×128 | Chrome Web Store |
| `assets/img/pwa/icon-144.png` | 144×144 | Windows tile |
| `assets/img/pwa/icon-152.png` | 152×152 | iOS touch icon |
| `assets/img/pwa/icon-192.png` | 192×192 | **Obligatoire** — Android splash screen (maskable) |
| `assets/img/pwa/icon-384.png` | 384×384 | Android haute densité |
| `assets/img/pwa/icon-512.png` | 512×512 | **Obligatoire** — Splash screen + install prompt (maskable) |
| `assets/img/pwa/screenshot-mobile.png` | 390×844 | Rich install UI mobile |
| `assets/img/pwa/screenshot-desktop.png` | 1280×720 | Rich install UI desktop |
| `assets/img/pwa/shortcut-missions.png` | 96×96 | Shortcut Missions |
| `assets/img/pwa/shortcut-profil.png` | 96×96 | Shortcut Profil |
| `assets/img/pwa/shortcut-classement.png` | 96×96 | Shortcut Classement |

### Génération des icônes

1. Préparer une image source carrée haute résolution (minimum 512×512px, idéalement SVG — `assets/img/pwa/icon.svg`)
2. Aller sur https://www.pwabuilder.com/imageGenerator
3. Uploader l'image source
4. Télécharger le pack → extraire dans `assets/img/pwa/`
5. Pour les icônes **maskable** (192 et 512) : vérifier que le contenu principal est dans les 80% centraux de l'image (safe zone) — utiliser https://maskable.app/editor

---

## Service Worker

### Stratégie de cache

Le `service-worker.js` doit implémenter au minimum :

- **Cache First** pour les assets statiques (CSS, JS, images, fonts) — servis depuis le cache, mis à jour en background
- **Network First** pour les pages HTML — tente le réseau, fallback cache
- **Exclusion des pages admin** : ne pas mettre en cache les URLs contenant `/admin/` (données sensibles et dynamiques)

### Page offline

En cas d'absence de réseau et d'absence de cache pour une page :
- Afficher `offline.php` (page dégradée informant l'utilisateur)
- Les assets CSS/JS doivent être précachés pour que `offline.php` soit rendu correctement

### Vérification DevTools

1. DevTools → Application → **Service Workers**
2. État doit indiquer "activated and running"
3. Onglet **Cache Storage** → vérifier les entrées précachées
4. Network → cocher "Offline" → recharger une page → vérifier que `offline.php` s'affiche
5. Network → vérifier que les assets CSS/JS ont "from ServiceWorker" dans la colonne Size

---

## Checklist de validation

- [ ] `manifest.json` accessible à l'URL `/manifest.json` (status 200)
- [ ] `manifest.json` valide JSON (aucune erreur dans DevTools → Application → Manifest)
- [ ] Service Worker enregistré (DevTools → Application → Service Workers : "activated and running")
- [ ] Icône 192px (`icon-192.png`) présente et accessible
- [ ] Icône 512px (`icon-512.png`) présente et accessible
- [ ] `theme_color` et `background_color` cohérents avec le design
- [ ] `start_url` = `/` répond en 200 (ou l'URL de base correcte)
- [ ] HTTPS actif en production (obligatoire pour SW et install prompt)
- [ ] `window.ZONE85_BASE` défini dans `header.php` avant `pwa-install.js`
- [ ] `localStorage z85_visits` s'incrémente à chaque visite (vérifier dans DevTools → Application → Local Storage)
- [ ] Bannière apparaît à la 2e visite sur Android/Desktop Chrome
- [ ] Bannière fermée → `z85_pwa_dismissed = '1'` dans localStorage
- [ ] `?pwa_install=1` → bannière affichée immédiatement
- [ ] iOS Safari → instructions "Partager → Sur l'écran d'accueil" affichées (pas de bouton "Installer")
- [ ] Après installation : `window.Zone85PWA.isInstalled()` retourne `true`
- [ ] `ajax/pwa-install-track.php` existe et insère dans `pwa_installs`
- [ ] Page offline affichée correctement en mode réseau coupé
- [ ] Les pages `/admin/` ne sont pas mises en cache par le SW
- [ ] Score Lighthouse PWA ≥ 80
