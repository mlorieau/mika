# Zone85 V12.6 — Couche émotionnelle Randos

Cette version enrichit les fiches randos sans ajouter de réseau social lourd.

## Ajouts principaux

### Back-office rando
- Champ **Pourquoi faire cette rando ?**
- Champ **Communes traversées** : une par ligne ou séparées par des virgules
- Cases **Saisons conseillées** : printemps, été, automne, hiver

### Front fiche rando
- Bandeau **Pourquoi cette rando ?**
- Affichage des saisons conseillées
- Affichage des communes traversées
- Message de tampon Passeport : les communes traversées sont tamponnées à la validation

### Passeport Vendéen
- Bloc **Passeport Rando**
- Nombre de randos réalisées
- Kilomètres cumulés
- Communes découvertes
- Répartition par territoire : Bocage / Marais / Littoral

### Tampons de communes
Une rando peut traverser plusieurs communes. Lorsqu'un Zonaute valide la rando, chaque commune est ajoutée une seule fois dans `rando_commune_stamps`.

Exemple :
- Vallée de l'Yon
- Communes traversées : La Roche-sur-Yon, Nesmy
- Le Passeport gagne 2 communes si elles n'étaient pas déjà tamponnées.

## SQL à importer

```sql
source database/migrations/026_v12_6_rando_emotion.sql;
```

## Test conseillé

1. Importer la migration 026.
2. Créer une rando avec deux communes traversées.
3. Cocher deux saisons conseillées.
4. Ajouter un texte dans “Pourquoi faire cette rando ?”.
5. Publier la rando.
6. Vérifier `rando.php` : bandeau, saisons, communes.
7. Valider la rando en front.
8. Ouvrir le Passeport : randos, kilomètres, communes doivent progresser.

## Note produit

Les avis, notes utilisateurs et commentaires publics restent hors périmètre. L'objectif est de différencier une fiche Zone85 par le récit, la découverte et le Passeport, pas de recréer TripAdvisor.
