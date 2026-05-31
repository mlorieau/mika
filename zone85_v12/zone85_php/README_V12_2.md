# ZONE85 — V12.2 Fix CMS Randos

Corrections post-test réel du CMS Randos V12.1.

---

## Ce qui a été corrigé

### 1. Image de couverture (front)
**Bug :** cover_image uploadée en BO mais invisible sur le front.
**Cause :** chemin relatif `uploads/randos/file.jpg` affiché sans `url()` → chemin cassé.
**Fix :** `e(url($r['cover_image']))` dans randos.php et rando.php.

### 2. Galerie (bloc gallery)
**Bug :** galerie invisible côté fiche rando.
**Cause :** cas `elseif ($btype === 'gallery')` absent du renderer.
**Fix :** Ajout du bloc galerie complet avec grille responsive CSS.

### 3. Blocs image (chemin cassé)
**Bug :** images de blocs non affichées.
**Cause :** `htmlspecialchars($c['src'])` sans `url()`.
**Fix :** `e(url($_img_src))`.

### 4. Bloc citation (mauvaise clé)
**Bug :** source de la citation absente.
**Cause :** front lisait `$c['author']`, admin sauvegardait `source`.
**Fix :** `$c['source'] ?? $c['author'] ?? ''` (compatibilité).

### 5. Bloc info / À savoir (mauvaise clé)
**Bug :** titre du bloc info absent.
**Cause :** front lisait `$c['title']`, admin sauvegardait `heading`.
**Fix :** `$c['heading'] ?? $c['title'] ?? ''` (compatibilité).

---

## Nouvelles fonctionnalités

### Introduction courte + Description longue
Deux nouveaux champs dans l'onglet "Informations" du BO :
- **Introduction courte** → accroche 2-4 lignes, typographie grande en haut de fiche
- **Description longue** → corps principal, affiché avant les blocs

Affichés automatiquement en haut de rando.php avant les blocs enrichis.

### GPX Upload
Remplace le simple champ URL.
- Upload direct `.gpx` depuis le BO
- Stocké dans `uploads/randos/gpx/`
- Lien téléchargement côté front (si fichier présent)
- Bouton "Supprimer" côté admin
- Champ URL externe conservé en option

### UX Upload médias
- Indicateur "X fichier(s) sélectionné(s)" sous chaque input file
- Bouton Sauvegarder désactivé + "Upload en cours..." pendant l'envoi
- Aperçu live de la photo de couverture avant soumission

---

## Migration SQL

```sql
-- Nouvelles colonnes sur la table randos
source database/migrations/024_v12_2_randos_fields.sql;
```

### Vérification
```sql
SHOW COLUMNS FROM randos LIKE 'intro_text';
SHOW COLUMNS FROM randos LIKE 'description';
SHOW COLUMNS FROM randos LIKE 'gpx_file';
```

---

## Checklist de test V12.2

### BO Admin

- [ ] Créer une rando, remplir "Introduction courte" → vérifier affichage front en haut de fiche
- [ ] Remplir "Description longue" → vérifier affichage front après introduction
- [ ] Uploader une image de couverture → vérifier aperçu live dans le BO
- [ ] Enregistrer → vérifier image visible sur la carte randos.php
- [ ] Vérifier image visible dans le hero de rando.php
- [ ] Uploader un fichier .gpx → vérifier indicateur "Tracé GPX disponible" + lien télécharger
- [ ] Essayer d'uploader un .pdf à la place du .gpx → vérifier message d'erreur
- [ ] Clic "Supprimer" GPX → vérifier disparition
- [ ] Sélectionner plusieurs photos pour un bloc Galerie → vérifier message "X fichiers sélectionnés"
- [ ] Sauvegarder → vérifier que le bouton dit "Upload en cours..."

### Blocs de contenu

- [ ] Créer bloc **Texte** avec titre et corps → vérifier rendu front
- [ ] Créer bloc **Image** → uploader une photo + légende → vérifier image visible front
- [ ] Créer bloc **Galerie** → uploader 3 photos → vérifier grille 3 colonnes front
- [ ] Créer bloc **Conseil Zone85** → vérifier encart rouge front
- [ ] Créer bloc **À savoir** → vérifier encart bleu avec titre front
- [ ] Créer bloc **Citation** → vérifier citation + source en italique front
- [ ] Réordonner les blocs (▲▼) → vérifier ordre côté front
- [ ] Supprimer un bloc → vérifier disparition front

### Front

- [ ] randos.php : image de couverture visible sur la carte
- [ ] randos.php : placeholder si pas d'image (pas d'image cassée)
- [ ] rando.php : ordre correct — hero → infos pratiques → intro → description → blocs → GPX → partage
- [ ] rando.php : GPX affiché uniquement si fichier présent
- [ ] Mobile : galerie passe en 2 colonnes sur 480px, 1 colonne sur 380px
- [ ] Aucun chemin cassé (vérifier console navigateur)

### Rando test complète

Créer la rando "La Marche des Genêts" avec :
- Image couverture uploadée
- Introduction : "Une boucle de 8 km au cœur du bocage vendéen..."
- Description : 2-3 paragraphes
- Bloc Citation
- Bloc Conseil Zone85
- Bloc À savoir
- Bloc Galerie (3 photos)
- Fichier GPX

Vérifier le rendu complet côté front.

---

## Architecture fichiers uploads

```
uploads/
└── randos/
    ├── .htaccess          ← bloque l'exécution PHP
    ├── abc123.jpg         ← images randos (cover + blocs image)
    ├── def456.jpg
    └── gpx/
        ├── trail1.gpx     ← fichiers GPX uploadés
        └── trail2.gpx
```
