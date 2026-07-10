# SILLAGE

> Le temps n'efface pas tout.

Site de la maison de sélection SILLAGE — Astro + TypeScript, contenu géré en
fichiers Markdown, sans base de données.

## Démarrer en local

```bash
npm install
npm run dev
```

Le site est disponible sur `http://localhost:4321`.

```bash
npm run build     # génère le site statique dans dist/
npm run preview   # prévisualise le build de production
```

## Gérer les objets (sans coder)

Chaque objet de la sélection est **un seul fichier** dans
`src/content/objets/`. Pour ajouter un objet, copiez un fichier existant
(ex. `chandelier-bronze.md`), renommez-le, et modifiez son contenu.

Un fichier ressemble à ceci :

```markdown
---
titre: Chandelier en bronze doré
accroche: Une flamme de plus, et la pièce entière change de lumière.
statut: disponible          # disponible | reservee | archivee
epoque: XIXe siècle
materiaux: Bronze doré
dimensions: Hauteur 28 cm, base Ø 11 cm
etat: Belle patine d'origine.
prix: 410
devise: EUR
images:
  - src: /images/objets/chandelier-bronze/01-hero.jpg
    alt: Chandelier en bronze doré
misEnAvant: true            # apparaît en avant sur la page d'accueil
ordre: 2                    # plus petit = affiché en premier
dateEntree: 2026-04-02
---

Le texte que vous écrivez ici, sous les deux lignes `---`, est le récit de
la pièce — ce qu'elle évoque. Vous pouvez écrire plusieurs paragraphes,
séparés par une ligne vide.
```

### Marquer un objet comme vendu

Changez `statut: disponible` en `statut: archivee` et ajoutez une ligne
`dateVente: 2026-07-10`. L'objet disparaît automatiquement de la page
d'accueil et apparaît dans **Les Archives**.

### Ajouter des photos

1. Créez un dossier dans `public/images/objets/nom-de-l-objet/`.
2. Déposez-y vos photos (`01-hero.jpg`, `02-detail.jpg`, etc.). La première
   photo listée dans `images:` est la photo qui « fait rêver » ; les
   suivantes montrent l'objet réel, avec ses défauts et sa patine.
3. Listez-les dans le fichier de l'objet :

```yaml
images:
  - src: /images/objets/nom-de-l-objet/01-hero.jpg
    alt: Description courte de la première photo
  - src: /images/objets/nom-de-l-objet/02-detail.jpg
    alt: Description courte de la deuxième photo
```

Le champ `alt` est important : il décrit l'image pour les personnes
malvoyantes et pour les moteurs de recherche. Décrivez simplement ce que
l'on voit.

### Supprimer un objet

Supprimez simplement son fichier `.md`. Pensez à conserver ses photos si
vous voulez les réutiliser ailleurs, sinon supprimez aussi son dossier
d'images.

## Le logo

Le logo se trouve dans `public/images/logo/sillage-logo.png` (fond
transparent). Il est utilisé automatiquement dans l'en-tête, le pied de
page et la page d'accueil. Pour le remplacer, déposez un nouveau fichier
PNG transparent au même endroit, sous le même nom.

Le favicon (`sillage-favicon.png`) et l'image de partage sur les réseaux
sociaux (`sillage-og.jpg`) sont générés à partir du même logo — à
régénérer manuellement si le logo change.

## Les autres pages

- **La Maison** (`src/pages/la-maison.astro`) — texte de la philosophie de
  la marque. Modifiable directement dans le fichier (le texte est en clair
  au milieu du code).
- **Contact** (`src/pages/contact.astro`) — formulaire de contact. Voir
  ci-dessous pour le faire fonctionner selon l'hébergeur choisi.

## Déploiement

### Netlify (recommandé pour le formulaire de contact)

Le fichier `netlify.toml` est déjà configuré. Connectez simplement le
dépôt à Netlify : le build (`npm run build`) et la publication (`dist/`)
sont automatiques. Le formulaire de contact utilise **Netlify Forms**
(détection automatique, aucune configuration serveur requise) — les
messages arrivent dans l'onglet « Forms » du tableau de bord Netlify.

### Vercel

Vercel détecte automatiquement Astro (aucune configuration nécessaire).
En revanche, Netlify Forms ne fonctionne pas sur Vercel : il faudra
remplacer l'action du formulaire dans `src/pages/contact.astro` par un
service tiers (Formspree, par exemple) ou une fonction serverless.

## Préparer l'arrivée de Stripe (V2)

L'architecture est prête à accueillir un paiement en ligne sans tout
refaire :

- Chaque objet a déjà un champ `stripePriceId` (vide en V1) dans son
  fichier Markdown, prêt à recevoir l'identifiant du prix Stripe
  correspondant.
- Le bouton d'action sur la page d'un objet disponible
  (`src/pages/objets/[slug].astro`) pointe aujourd'hui vers le formulaire
  de contact ; il suffira de le remplacer par un appel à Stripe Checkout
  quand ce sera activé.
- Aucune base de données n'est nécessaire pour ce changement : Stripe peut
  être appelé directement depuis une fonction serverless (Netlify
  Functions ou Vercel Functions) au moment opportun.

## Stack technique

- [Astro](https://astro.build) + TypeScript
- Contenu en collections Markdown (`src/content/objets/`)
- CSS natif (pas de framework CSS) — variables de design dans
  `src/styles/global.css`
- Police de titres : Fraunces (via `@fontsource-variable/fraunces`)
- Aucune base de données, aucune authentification
