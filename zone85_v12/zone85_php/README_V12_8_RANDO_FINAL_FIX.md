# Zone85 V12.8 — Finition Randos front

Corrections incluses :

- `.htaccess` des uploads/randos simplifié pour éviter les erreurs 500 sur certaines configurations Apache/Plesk.
- `media_url()` rendu plus tolérant aux anciens chemins stockés en base.
- Leaflet chargé sans attribut `integrity` pour éviter les blocages SRI/CDN.
- CSP ajustée pour les tuiles OpenStreetMap et les icônes Leaflet.
- `invalidateSize()` ajouté après initialisation des cartes.
- Lightbox simple sur les photos de couverture, galerie et trésors du parcours.
- Suppression du double overlay d’upload dans `admin/rando-edit.php`.

Aucune migration SQL requise.

À tester :

1. Recharger une fiche rando.
2. Vérifier image couverture.
3. Vérifier galerie et trésor du parcours.
4. Cliquer sur les photos : elles doivent s’ouvrir en plein écran.
5. Vérifier la carte sur `randos.php` et `rando.php`.
6. Vérifier la trace GPX si un fichier GPX est uploadé.
7. Vérifier qu’un seul overlay d’upload apparaît côté BO.
