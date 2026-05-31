# Zone85 V12.10 — GPX + validation rando

## Objectif
Petite version de finition à partir de la V12.9 : ne pas toucher aux photos, galeries ou trésors, qui sont validés.

## Corrections

### 1. Trace GPX sur la fiche rando
- Parsing GPX renforcé côté front dans `rando.php`.
- Lecture des balises `trkpt`, `rtept` et `wpt`.
- Fallback regex si le parser XML classique ne trouve rien.
- Tracé affiché en corail Zone85 sur la carte Leaflet.

### 2. Texte de demande XP
- La pop-up de demande de +25 XP précise maintenant que la photo doit être prise devant le **Trésor du parcours**.
- Elle indique où arrive la demande : `Admin > Communauté > Validations Randos`.

## Où valider les participations ?
Dans le back-office :

`Admin > Communauté > Validations Randos`

Fichier : `admin/rando-validations.php`

## Test conseillé
1. Ouvrir une fiche rando avec GPX uploadé.
2. Vérifier que la carte s'affiche.
3. Vérifier que la trace du GPX apparaît sur la carte.
4. Cliquer sur la demande de +25 XP.
5. Vérifier le texte de la pop-up.
6. Envoyer une photo test.
7. Aller dans `Admin > Communauté > Validations Randos`.
8. Valider ou refuser la demande.

## Migration SQL
Aucune nouvelle migration SQL.
