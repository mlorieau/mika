# V11.1 — Correctif KTC éditorial

Correctif appliqué :

- `admin/ktc.php` ne pointe plus vers l'ancien système de banque de questions.
- `admin/ktc.php` devient une vraie entrée BO pour les épisodes KTC éditoriaux.
- Ajout de `admin/ktc-episode-edit.php` pour créer/modifier un épisode KTC simple.
- `ktc.php` public ne lit plus `ktc_questions` : il lit maintenant `ktc_episodes`, `ktc_episode_photos`, `ktc_propositions` et `ktc_votes`.
- Le wording est corrigé : KTC = rendez-vous mensuel éditorial, pas quête saisonnière.
- La page publique explique : un objet, une rencontre, une enquête.
- Les 4 états sont gérés : découverte, indices, votes, révélation.

À importer avant test :

```sql
mysql -u zone85_user -p qg_ < database/migrations/017_v11_ktc_editorial.sql
```

À tester :

1. Aller sur `/admin/ktc.php` : plus d'erreur 500.
2. Créer un épisode KTC depuis `/admin/ktc-episode-edit.php`.
3. Passer l'épisode en `week1`.
4. Aller sur `/ktc.php`.
5. Poster une hypothèse avec un membre connecté.
6. Passer l'épisode en `week3` et tester le vote.
7. Passer l'épisode en `revealed` et vérifier la révélation.

Note produit :

- La Quête saisonnière est autre chose : aventure sur plusieurs mois.
- Le KTC est mensuel : une rencontre locale + un objet mystère + une révélation.
