# Zone85 — V12.3 ChatGPT Fix Randos

Objectif : stabiliser l'éditeur Rando et le rendu front après test réel.

## Corrections principales

- Suppression du champ "Résumé" côté BO : on garde uniquement Introduction + Description.
- Génération automatique d'un résumé technique depuis l'introduction ou la description pour les cartes/SEO.
- Amélioration des URLs médias avec `media_url()` et normalisation des chemins uploadés.
- Upload GPX via fichier `.gpx` privilégié ; lien externe masqué côté BO.
- Ajout d'un overlay de chargement média lors de l'upload de fichiers.
- Rendu front des images de couverture et galeries rendu plus robuste.
- Ajout d'une carte gratuite OpenStreetMap/Leaflet sur :
  - `randos.php` : carte des randos avec coordonnées GPS.
  - `rando.php` : carte de la fiche + affichage de la trace GPX si fichier disponible.
- Ajout d'un petit effet discret de sparkle au clic sur les pages randos.
- Syntaxe PHP vérifiée sur tous les fichiers.

## À tester

1. Créer une rando avec :
   - image couverture uploadée,
   - 3 images galerie,
   - fichier GPX uploadé,
   - latitude / longitude,
   - introduction,
   - description.
2. Vérifier :
   - la carte liste `randos.php`,
   - la fiche `rando.php`,
   - l'affichage de la galerie,
   - le bouton GPX,
   - le mobile.
3. Vider le cache navigateur si une ancienne image ou ancien CSS persiste.
