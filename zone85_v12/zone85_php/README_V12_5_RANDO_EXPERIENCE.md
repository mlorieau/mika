# Zone85 V12.5 — Expérience Rando

Objectif : différencier les fiches randos Zone85 d'une fiche classique d'office de tourisme.

Ajouts :
- Ambiance du parcours : Nature, Patrimoine, Famille, Photo.
- Nouveaux blocs éditoriaux : Récit Zone85 et Trésor du parcours.
- Bloc communautaire : Les Zonautes sont passés par ici.
- Bouton Passeport : J'ai réalisé cette randonnée (+25 XP, tampon rando_participations).
- Migration 025 pour les champs d'ambiance et nouveaux types de blocs.

À importer après les migrations V12/V12.3 :

```sql
source database/migrations/025_v12_5_rando_experience.sql;
```

Test rapide :
1. Créer ou modifier une rando.
2. Renseigner les notes d'ambiance.
3. Ajouter un bloc Récit Zone85.
4. Ajouter un bloc Trésor du parcours avec photo.
5. Publier et vérifier rando.php.
6. Se connecter et cliquer sur J'ai réalisé cette randonnée.
