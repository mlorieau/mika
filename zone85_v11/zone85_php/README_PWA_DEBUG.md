# Zone85 V10.2 — Guide de débogage PWA

> Basé sur `assets/js/pwa-install.js` (V10.2) et `manifest.json`.  
> Date : 2026-05-29

---

## Critères d'installabilité Chrome

Chrome n'affiche le prompt d'installation (et ne déclenche `beforeinstallprompt`) que si **les 5 critères suivants sont tous satisfaits** :

1. **HTTPS obligatoire** — le site doit être servi en `https://`. `localhost` est l'unique exception autorisée en développement.
2. **manifest.json valide et lié** — la page doit contenir `<link rel="manifest" href="/manifest.json">`. Le manifest doit comporter au minimum `name`, `short_name`, `start_url`, `display` (standalone/fullscreen/minimal-ui).
3. **Service Worker enregistré** — un SW actif doit être enregistré sur le scope du site. Dans Zone85, `service-worker.js` est enregistré au chargement de la page via `pwa-install.js`.
4. **Icônes 192 px et 512 px présentes** — le manifest doit déclarer au minimum une icône `192x192` et une icône `512x512`. Dans Zone85, `assets/img/pwa/icon-192.png` et `assets/img/pwa/icon-512.png` doivent exister physiquement sur le serveur.
5. **`start_url` accessible** — l'URL déclarée dans `start_url` (ici `/`) doit retourner un code HTTP 200 même hors-ligne ou au premier accès.

---

## Pourquoi le bouton "Installer" ne fait rien

### Le problème : race condition entre `beforeinstallprompt` et `DOMContentLoaded`

`beforeinstallprompt` est déclenché par le navigateur de manière asynchrone, souvent **après** que `DOMContentLoaded` a déjà été émis. Si le code tentait d'afficher la bannière directement dans `DOMContentLoaded` sans attendre le prompt, `_deferredPrompt` était `null` au moment du rendu — et le bouton "Installer" n'était pas rendu dans le HTML. Cliquer dessus ne faisait donc rien.

### Le fix V10.2 : `_bannerScheduled` + timeout 3 s

```js
// Extrait de pwa-install.js V10.2
var _deferredPrompt  = null;
var _bannerScheduled = false;

window.addEventListener('beforeinstallprompt', function (e) {
  e.preventDefault();
  _deferredPrompt = e;
  if (_bannerScheduled) {         // DOMContentLoaded est déjà passé
    _bannerScheduled = false;
    showBanner();                 // on affiche maintenant que le prompt est prêt
  }
});

document.addEventListener('DOMContentLoaded', function () {
  if (!shouldShowBanner()) return;
  if (_deferredPrompt) {
    showBanner();                 // prompt déjà disponible (cas rare)
  } else {
    _bannerScheduled = true;      // marquer l'attente
    setTimeout(function () {
      if (_bannerScheduled && shouldShowBanner() && forceShow) {
        _bannerScheduled = false;
        showBanner();             // fallback si forceShow=1 et pas de prompt après 3s
      }
    }, 3000);
  }
});
```

Le drapeau `_bannerScheduled` agit comme rendez-vous entre les deux listeners : peu importe lequel arrive en premier, l'affichage ne se déclenche qu'une fois les deux conditions réunies.

### Quand le bouton "Installer" s'affiche

Le bouton `<button id="z85-pwa-install-btn">` est injecté dans le HTML de la bannière **uniquement si** `_deferredPrompt !== null` au moment de l'appel à `showBanner()`. Si Chrome n'a pas encore émis `beforeinstallprompt` (ou ne le fera pas du tout), la bannière s'affiche sans bouton natif.

### Quand le bouton ne s'affiche pas

- L'app est déjà installée (`display-mode: standalone` détecté).
- Le site n'est pas en HTTPS.
- Les icônes 192/512 px sont manquantes.
- L'utilisateur a déjà installé ou refusé l'installation sur ce navigateur.
- `z85_pwa_dismissed` est positionné à `1` dans localStorage (et moins de 7 jours se sont écoulés depuis le dismiss).
- Le compteur de visites `z85_visits` est inférieur à 2 et `?pwa_install=1` n'est pas dans l'URL.

---

## Test Android Chrome

1. Ouvrir Chrome sur Android et naviguer vers `https://www.zone85.fr`.
2. Après 2 visites (ou directement via `?pwa_install=1`), la bannière doit apparaître en bas.
3. Pour forcer l'affichage : ajouter `?pwa_install=1` à l'URL.
4. En DevTools (depuis un Mac/PC branché en USB + `chrome://inspect`) :
   - Aller dans **Application > Manifest** — vérifier que toutes les icônes se chargent sans erreur 404.
   - Aller dans **Application > Service Workers** — le SW doit être à l'état `activated and is running`.
5. Pour tester le prompt manuellement : dans la console, taper `Zone85PWA.hasPrompt()` — doit retourner `true`.
6. Lancer un audit **Lighthouse PWA** depuis l'onglet Lighthouse de DevTools — le score doit atteindre au minimum 100 % sur les critères d'installabilité.

---

## Test iOS Safari

iOS **ne supporte pas** l'événement `beforeinstallprompt`. L'installation est entièrement manuelle.

`pwa-install.js` détecte iOS Safari via :
```js
var isIOS    = /iphone|ipad|ipod/i.test(navigator.userAgent);
var isSafari = /safari/i.test(navigator.userAgent) && !/chrome/i.test(navigator.userAgent);
```

Si les deux conditions sont vraies, la bannière affiche des instructions textuelles :
> Appuie sur **▲ Partager** puis **Sur l'écran d'accueil**

**Étapes manuelles pour l'utilisateur :**
1. Ouvrir Safari (pas Chrome ni Firefox sur iOS — ils ne supportent pas l'installation PWA).
2. Naviguer vers `https://www.zone85.fr`.
3. Appuyer sur l'icône **Partager** (carré avec flèche vers le haut) dans la barre de navigation Safari.
4. Faire défiler la feuille d'actions vers le bas et sélectionner **Sur l'écran d'accueil** ("Add to Home Screen").
5. Confirmer le nom "Zone85" puis appuyer sur **Ajouter**.

**Note :** Sur iOS, `display: standalone` fonctionne correctement une fois installé. La barre Safari disparaît et l'app s'ouvre en plein écran.

---

## Test Desktop Chrome

1. Naviguer vers `https://www.zone85.fr` dans Chrome desktop.
2. Si le site est installable, une icône d'installation apparaît dans la barre d'adresse (ordinateur avec flèche).
3. En DevTools :
   - **Application > Manifest** : vérifier le manifest chargé, les icônes, `start_url`, `display`.
   - **Application > Service Workers** : statut `activated and is running`, script `service-worker.js`.
   - Cliquer sur **"Install Zone85"** dans le panneau Manifest pour déclencher le prompt de test.
4. Pour forcer la bannière Zone85 : ajouter `?pwa_install=1` à l'URL — cela met `forceShow = true` dans `pwa-install.js` et contourne le seuil de visites.
5. Vérifier dans la console : `Zone85PWA.platform` doit retourner `"desktop"`.

---

## Vérifier le Service Worker

Dans Chrome DevTools, onglet **Application > Service Workers** :

| Indicateur | Valeur attendue |
|---|---|
| Source | `service-worker.js` |
| Status | `activated and is running` |
| Clients | L'URL de la page courante |
| Update on reload | Cocher pour forcer la mise à jour en dev |

**Commandes console utiles :**

```js
// Lister tous les SW enregistrés
navigator.serviceWorker.getRegistrations().then(console.log);

// Scope attendu
// ServiceWorkerRegistration { scope: "https://www.zone85.fr/" }
```

Si le SW est en état `waiting` : cliquer sur **skipWaiting** dans DevTools ou fermer tous les onglets Zone85 et rouvrir.

Si le SW est absent : vérifier que `service-worker.js` est bien présent à la racine du projet et que le serveur le sert avec le `Content-Type: application/javascript` correct.

---

## Icônes manquantes

Le manifest Zone85 déclare **8 PNG** qui doivent tous exister dans `assets/img/pwa/` :

| Fichier | Taille | Purpose |
|---|---|---|
| `icon-72.png` | 72×72 px | any |
| `icon-96.png` | 96×96 px | any |
| `icon-128.png` | 128×128 px | any |
| `icon-144.png` | 144×144 px | any |
| `icon-152.png` | 152×152 px | any |
| `icon-192.png` | 192×192 px | any maskable ⚠️ requis Chrome |
| `icon-384.png` | 384×384 px | any |
| `icon-512.png` | 512×512 px | any maskable ⚠️ requis Chrome |

Plus 2 screenshots et 3 icônes de shortcuts :
- `assets/img/pwa/screenshot-mobile.png` (390×844)
- `assets/img/pwa/screenshot-desktop.png` (1280×720)
- `assets/img/pwa/shortcut-missions.png` (96×96)
- `assets/img/pwa/shortcut-profil.png` (96×96)
- `assets/img/pwa/shortcut-classement.png` (96×96)

**Outils de génération :**
- [PWA Builder Image Generator](https://www.pwabuilder.com/imageGenerator) — génère toutes les tailles depuis une image source.
- [Maskable.app](https://maskable.app/editor) — vérifier et créer des icônes maskable (zone sûre = 80 % du centre).

**Vérification rapide :** dans Chrome DevTools > Application > Manifest, une icône manquante apparaît avec une croix rouge. Toute icône 404 peut empêcher l'affichage du prompt d'installation.

---

## localStorage debug

Zone85 utilise 3 clés localStorage pour contrôler l'affichage de la bannière PWA :

| Clé | Type | Rôle |
|---|---|---|
| `z85_visits` | entier (string) | Compteur de visites. La bannière s'affiche à partir de la **2e visite** (`SHOW_AFTER = 2`). |
| `z85_pwa_dismissed` | `"1"` ou absent | Ancienne clé — le prompt a été fermé ou l'installation acceptée. |
| `z85_pwa_dismissed_ts` | timestamp ms (string) | Date du dernier dismiss. Si absent, `z85_pwa_dismissed` seul fait foi. Si présent, la bannière réapparaît automatiquement après **7 jours**. |

**Commandes console pour déboguer :**

```js
// Voir l'état complet
console.table({
  visits:       localStorage.getItem('z85_visits'),
  dismissed:    localStorage.getItem('z85_pwa_dismissed'),
  dismissed_ts: localStorage.getItem('z85_pwa_dismissed_ts'),
    dismissed_date: new Date(parseInt(localStorage.getItem('z85_pwa_dismissed_ts') || '0')).toLocaleString()
});

// Réinitialiser pour retester
localStorage.removeItem('z85_visits');
localStorage.removeItem('z85_pwa_dismissed');
localStorage.removeItem('z85_pwa_dismissed_ts');

// Forcer l'affichage sans modifier le localStorage
// Ajouter ?pwa_install=1 à l'URL (forceShow bypass le compteur de visites)
```

**API publique disponible en console :**
```js
Zone85PWA.platform     // "android" | "ios" | "desktop"
Zone85PWA.isInstalled() // true si standalone
Zone85PWA.hasPrompt()   // true si beforeinstallprompt capturé
Zone85PWA.showBanner()  // force l'affichage de la bannière
Zone85PWA.hideBanner()  // masque la bannière
```

---

## Checklist finale

- [ ] Le site est servi en HTTPS (certificat SSL valide)
- [ ] `<link rel="manifest" href="/manifest.json">` présent dans le `<head>` de toutes les pages
- [ ] `manifest.json` retourne HTTP 200 avec `Content-Type: application/manifest+json`
- [ ] `service-worker.js` existe à la racine et retourne HTTP 200
- [ ] Le Service Worker est à l'état `activated and is running` dans DevTools
- [ ] `icon-192.png` existe dans `assets/img/pwa/` (192×192 px)
- [ ] `icon-512.png` existe dans `assets/img/pwa/` (512×512 px)
- [ ] Les 6 autres icônes PNG existent (72, 96, 128, 144, 152, 384 px)
- [ ] `start_url: "/"` retourne HTTP 200
- [ ] `BASE_URL` est correctement défini dans la config PHP et exposé en JS via `window.ZONE85_BASE`
- [ ] `?pwa_install=1` déclenche bien la bannière après inscription
- [ ] Sur iOS Safari, les instructions manuelles s'affichent (pas de bouton "Installer")
- [ ] Sur Android Chrome, le bouton "Installer" apparaît dans la bannière (si `_deferredPrompt !== null`)
- [ ] `ajax/pwa-install-track.php` existe et insère dans la table `pwa_installs`
- [ ] Audit Lighthouse PWA ≥ 100 % sur les critères d'installabilité
