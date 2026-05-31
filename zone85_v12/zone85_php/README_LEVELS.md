# Zone85 V10.2 — Système de niveaux

> Source : `includes/functions.php` — constantes `XP_LEVEL_THRESHOLDS`, `XP_LEVEL_NAMES` et fonction `get_user_level_from_xp()`.  
> Date : 2026-05-29

---

## Tableau des seuils

| Niveau | Nom | XP requis |
|--------|-----|-----------|
| 1 | Novice | 0 XP |
| 2 | Explorateur | 50 XP |
| 3 | Aventurier | 100 XP |
| 4 | Expert | 250 XP |
| 5 | Gardien | 500 XP |
| 6 | Légende | 1 000 XP |
| 7 | Grand Pisteur | 2 500 XP |
| 8 | Vétéran | 5 000 XP |
| 9 | Ancêtre | 10 000 XP |
| 10 | Immortel | 20 000 XP |

---

## Calcul automatique

### Où est calculé le niveau

Dans `includes/functions.php`, fonction `get_user_level_from_xp(int $xp): int`.

```php
// Extrait exact de functions.php
const XP_LEVEL_THRESHOLDS = [0, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 20000];

function get_user_level_from_xp(int $xp): int {
    $thresholds = [0, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 20000];
    $level = 1;
    foreach ($thresholds as $i => $t) {
        if ($xp >= $t) $level = $i + 1;
    }
    return min($level, 10);
}
```

La fonction parcourt tous les seuils et retourne le niveau correspondant à la valeur la plus haute atteinte. Le `min($level, 10)` plafonne à Immortel quel que soit le total XP.

Fonctions auxiliaires disponibles :
- `get_level_name(int $level): string` — retourne le nom du niveau (ex. `"Grand Pisteur"` pour le niveau 7)
- `get_level_threshold(int $level): int` — retourne l'XP requis pour atteindre ce niveau

### Quand est-il mis à jour

Le niveau est recalculé et persisté en base à chaque attribution d'XP, notamment lors :
- de la **participation à une mission** (vote, quiz, photo, rando, KTC, etc.)
- de la **complétion d'un Hidden Hunt** (XP déclenchée à la collecte du dernier objet)
- d'une **réponse KTC** (Ketokolé Tché — 7 questions par quiz, anti-doublon actif)
- de **l'inscription** (XP de bienvenue si configurée dans `XP_RATES`)
- de la **validation manuelle** d'une participation par un admin

Le champ `users.level` est mis à jour en même temps que `users.xp_total` dans la même transaction PDO pour garantir la cohérence.

---

## Recalcul des utilisateurs existants

Si des utilisateurs ont accumulé de l'XP avant la mise en place de V10.2 (ou si `users.level` est désynchronisé), exécuter ce SQL pour recalculer tous les niveaux en une passe :

```sql
UPDATE users SET level = CASE
  WHEN xp_total >= 20000 THEN 10
  WHEN xp_total >= 10000 THEN 9
  WHEN xp_total >= 5000  THEN 8
  WHEN xp_total >= 2500  THEN 7
  WHEN xp_total >= 1000  THEN 6
  WHEN xp_total >= 500   THEN 5
  WHEN xp_total >= 250   THEN 4
  WHEN xp_total >= 100   THEN 3
  WHEN xp_total >= 50    THEN 2
  ELSE 1
END;
```

**Vérification post-migration :**

```sql
-- Contrôler que level et xp_total sont cohérents
SELECT id, pseudo, xp_total, level,
  CASE
    WHEN xp_total >= 20000 THEN 10
    WHEN xp_total >= 10000 THEN 9
    WHEN xp_total >= 5000  THEN 8
    WHEN xp_total >= 2500  THEN 7
    WHEN xp_total >= 1000  THEN 6
    WHEN xp_total >= 500   THEN 5
    WHEN xp_total >= 250   THEN 4
    WHEN xp_total >= 100   THEN 3
    WHEN xp_total >= 50    THEN 2
    ELSE 1
  END AS expected_level
FROM users
WHERE level != (
  CASE
    WHEN xp_total >= 20000 THEN 10
    WHEN xp_total >= 10000 THEN 9
    WHEN xp_total >= 5000  THEN 8
    WHEN xp_total >= 2500  THEN 7
    WHEN xp_total >= 1000  THEN 6
    WHEN xp_total >= 500   THEN 5
    WHEN xp_total >= 250   THEN 4
    WHEN xp_total >= 100   THEN 3
    WHEN xp_total >= 50    THEN 2
    ELSE 1
  END
);
-- Doit retourner 0 ligne si tout est synchronisé.
```

---

## Affichage profil

Sur la page profil (`profil.php`), le niveau s'affiche avec :

### Barre de progression

La barre indique la progression entre le seuil du niveau actuel et celui du niveau suivant.

```
XP actuel : 180 XP  →  Niveau 3 (Aventurier, seuil 100 XP)
Prochain niveau : Expert à 250 XP
Progression : (180 - 100) / (250 - 100) = 80/150 = 53 %
```

Calcul PHP :
```php
$current_threshold = get_level_threshold($level);         // ex. 100
$next_threshold    = get_level_threshold($level + 1);     // ex. 250
$progress_pct      = ($level < 10)
    ? round(($xp - $current_threshold) / ($next_threshold - $current_threshold) * 100)
    : 100;
```

### Informations affichées

- **Badge de niveau** : numéro + nom (ex. "Niv. 3 — Aventurier")
- **Barre de progression** : visuelle de 0 à 100 % vers le niveau suivant
- **XP manquant** : "Il te manque X XP pour atteindre [Prochain niveau]"
- **Prochain niveau** : nom et seuil du niveau N+1
- **Au niveau 10 (Immortel)** : la barre est pleine à 100 %, le message "Prochain niveau" est remplacé par "Niveau maximum atteint"

### Formatage des XP

La fonction `format_xp(int $n): string` dans `functions.php` formate les valeurs avec espace insécable :  
`format_xp(2500)` → `"2 500 XP"`
