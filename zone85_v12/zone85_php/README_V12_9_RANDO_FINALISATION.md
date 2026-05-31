# Zone85 V12.9 — Finalisation Randos

## SQL à importer

```sql
SOURCE database/migrations/028_v12_9_rando_participation_review.sql;
```

## Corrections incluses

- La photo de couverture est préservée explicitement lors des sauvegardes de galerie/blocs.
- La trace GPX est mieux parsée côté front : `trkpt` et `rtept` sont supportés.
- Le bouton +25 XP ouvre désormais un pop-up front propre.
- La demande de points demande :
  - une note sur 5 ;
  - un avis ;
  - une photo devant le Trésor du parcours.
- L’admin des validations affiche la note et l’avis en plus de la photo.

## Test rapide

1. Créer ou éditer une rando.
2. Ajouter une couverture.
3. Ajouter une galerie.
4. Vérifier que la couverture reste présente après sauvegarde galerie/bloc.
5. Uploader un GPX.
6. Vérifier que le tracé apparaît sur la carte.
7. Côté front, cliquer sur “Demander les +25 XP”.
8. Déposer note + avis + photo.
9. Vérifier la demande dans `admin/rando-validations.php`.
10. Valider et vérifier les +25 XP.
