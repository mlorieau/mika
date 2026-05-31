# Zone85 V12.13 — Randos : filtres compacts

## Objectif
Remplacer le grand bloc de filtres Randos par une barre compacte avec menus déroulants multi-sélection.

## Modifié
- `randos.php`

## Changements
- Filtres sur une seule ligne : Secteur, Difficulté, Distance, Durée.
- Menus déroulants avec cases à cocher.
- Multi-sélection possible dans chaque filtre.
- Affichage des filtres actifs sous forme de tags.
- Bouton `Effacer tout` si au moins un filtre est actif.
- Logique SQL adaptée aux filtres multiples.
- Responsive mobile : menus accessibles en panneau bas.

## À tester
1. Sélectionner plusieurs secteurs.
2. Sélectionner plusieurs difficultés.
3. Combiner distance + durée.
4. Effacer un groupe de filtres.
5. Effacer tous les filtres.
6. Vérifier mobile.

Aucune migration SQL nécessaire.
