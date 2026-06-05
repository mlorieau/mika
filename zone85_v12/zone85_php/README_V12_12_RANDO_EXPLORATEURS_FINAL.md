# Zone85 V12.12 — Randos finalisées : Explorateurs Zone85

Base : V12.11 Randos UX + retours participants.

## Objectif
Finaliser la brique Randonnées V1 avec l'affichage public des participations validées.

## Ajout principal
Sur `rando.php`, en bas de chaque fiche randonnée, ajout du bloc :

**Les explorateurs Zone85 — Ils sont passés par ici**

Le bloc affiche uniquement les participations validées par l'administration :
- pseudo du Zonaute ;
- photo envoyée ;
- note sur 5 ;
- avis ;
- date de validation.

Les photos utilisent la lightbox existante.

## Règles
- Les participations en attente ne sont pas affichées.
- Les participations refusées ne sont pas affichées.
- Aucune migration SQL nécessaire.
- Aucun changement back-office.

## Test rapide
1. Créer une participation rando avec photo + note + avis.
2. Valider la participation dans le back-office.
3. Vérifier que les XP sont attribués.
4. Retourner sur la fiche rando.
5. Vérifier l'apparition du bloc Explorateurs Zone85 en bas de page.
