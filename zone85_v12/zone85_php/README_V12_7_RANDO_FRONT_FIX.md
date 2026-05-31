# Zone85 V12.7 — Finition Front Randos

Correctifs inclus :

- Correction des erreurs 500 sur les images uploadées dans `uploads/randos/` via `.htaccess` compatible Apache 2.4/2.2.
- CSP mise à jour pour autoriser Leaflet (`unpkg.com`) et les tuiles OpenStreetMap.
- Carte des randonnées ajoutée sur `randos.php` avec les points GPS des fiches publiées.
- Fallback visuel si Leaflet est bloqué ou indisponible.
- Correction des communes traversées contenant `\n` affiché en clair.
- Partage Facebook corrigé avec URL absolue de la rando courante, adaptée au staging ou à la production.
- Open Graph image corrigée pour utiliser une URL absolue de l’image de couverture.
- GPX : les liens utilisent désormais `gpx_file` ou `gpx_url` selon ce qui existe.
- Validation rando revue :
  - Tampon Passeport simple = 0 XP.
  - Demande de +25 XP = photo obligatoire.
  - Validation admin via `admin/rando-validations.php`.
- Ajout migration `027_v12_7_rando_validation.sql`.

## SQL à importer

```sql
SOURCE database/migrations/027_v12_7_rando_validation.sql;
```

## Test conseillé

1. Importer la migration 027.
2. Créer ou modifier une rando avec : couverture, galerie, GPX, GPS, communes traversées.
3. Vérifier `randos.php` : carte + images cartes.
4. Vérifier `rando.php` : couverture + galerie/blocs + carte + trace GPX.
5. Cliquer sur “Tamponner mon Passeport” : pas d’XP, tampon OK.
6. Envoyer une photo de preuve : statut pending.
7. Aller dans `admin/rando-validations.php` et valider : +25 XP attribués.

