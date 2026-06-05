# Zone85 V12.11 — Randos UX filtres + retours participants

## Objectif
Finalisation UX de la page Randos et valorisation des participations validées.

## Modifications

### randos.php
- Refonte visuelle des filtres sous forme de carte centrale plus lisible.
- Ajout filtres Distance : < 5 km, 5 à 10 km, 10 à 15 km, +15 km.
- Ajout filtres Durée : < 1h, 1h à 2h, 2h à 3h, +3h.
- Conservation des filtres combinés secteur + difficulté + distance + durée.
- Résumé du nombre de randonnées trouvées.

### rando.php
- Ajout d’un bloc final “Retours validés”.
- Affiche les participations validées par l’admin avec :
  - photo envoyée ;
  - pseudo ;
  - note ;
  - avis ;
  - date de validation.
- Les photos de participation s’ouvrent dans la lightbox existante.

## SQL
Aucune migration nécessaire.
Les champs utilisés existent déjà dans `rando_participations` :
- photo_path
- proof_rating
- proof_review
- status
- validated_at

## Tests conseillés
1. Ouvrir `randos.php`.
2. Tester les filtres secteur, difficulté, distance et durée seuls puis combinés.
3. Ouvrir une fiche rando avec une participation validée.
4. Vérifier que le bloc “Retours validés” apparaît.
5. Cliquer sur une photo de retour : ouverture en lightbox.
