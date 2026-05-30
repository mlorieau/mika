# Zone 85 — Checklist Sécurité v1

> Document de référence pour le suivi de la sécurité du projet.  
> À mettre à jour à chaque sprint et avant chaque mise en production.  
> Dernière révision : 2026-05-28

---

## Statut global

| Point de contrôle | Statut |
|---|---|
| Échappement HTML | Implémenté |
| Headers HTTP de sécurité | Implémenté |
| Sessions sécurisées | TODO |
| Authentification | TODO |
| CSRF | En cours |
| SQL / PDO | TODO |
| Upload de fichiers | TODO |
| Anti-triche gamification | En cours |
| Back-office | TODO |
| Données sensibles / secrets | Implémenté |
| Séparation dev / prod | En cours |

---

## 1. Échappement HTML

### Principe
Toute valeur affichée en HTML doit être échappée. La règle est simple : **jamais de `echo` direct sur une entrée utilisateur**.

### Implémentation actuelle

- [x] La fonction `e()` est disponible dans `includes/functions.php`
- [x] `e()` utilise `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`
- [x] Utilisée sur toutes les sorties dynamiques dans les pages et composants
- [x] Les attributs HTML (href, src, data-*) sont également échappés

### Règles à respecter

```php
// CORRECT
echo e($user['pseudo']);
<input value="<?= e($value) ?>">

// INCORRECT — ne jamais faire ça
echo $user['pseudo'];
echo $_GET['q'];
<input value="<?= $value ?>">
```

### Points de vigilance

- Les sorties dans les attributs `href` doivent aussi filtrer les protocoles (`javascript:`)
- Les blocs JSON injectés en PHP (`json_encode`) sont hors scope de `e()` mais doivent utiliser `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`
- Les blocs `<style>` ou `<script>` générés dynamiquement sont interdits sauf cas isolé et validé

---

## 2. Headers HTTP de sécurité

### Implémentation actuelle

La fonction `set_security_headers()` dans `includes/functions.php` envoie les headers suivants à chaque requête.

### X-Content-Type-Options

```
X-Content-Type-Options: nosniff
```

- [x] Implémenté
- Empêche le navigateur de "deviner" le type MIME d'une réponse
- Protège contre les attaques de type MIME-sniffing

### X-Frame-Options

```
X-Frame-Options: SAMEORIGIN
```

- [x] Implémenté
- Empêche l'intégration de la page dans un `<iframe>` externe
- Protège contre le clickjacking
- Note : remplaçable par `Content-Security-Policy: frame-ancestors 'self'` à terme

### Referrer-Policy

```
Referrer-Policy: strict-origin-when-cross-origin
```

- [x] Implémenté
- Envoie l'origine complète en interne, seulement l'origine (sans chemin) en cross-origin HTTPS
- N'envoie rien en cas de downgrade HTTP

### Permissions-Policy

```
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

- [x] Implémenté
- Désactive les APIs sensibles du navigateur non utilisées par le site
- À ajuster si des fonctionnalités de géolocalisation sont ajoutées

### Content-Security-Policy

#### Version actuelle (permissive)

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;
```

- [x] Implémenté (version relâchée pour faciliter le dev)
- `unsafe-inline` accepté temporairement pour les styles et scripts inline

#### Plan de renforcement (v2)

```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' 'nonce-{RANDOM}';
  style-src 'self' 'nonce-{RANDOM}';
  img-src 'self' data: https://www.zone85.fr;
  font-src 'self';
  connect-src 'self';
  frame-ancestors 'none';
  base-uri 'self';
  form-action 'self';
```

- [ ] TODO v2 : supprimer `unsafe-inline`, utiliser des nonces générés à chaque requête
- [ ] TODO v2 : tester avec l'extension CSP Evaluator (Google)
- [ ] TODO v2 : mode `report-only` avant activation complète

---

## 3. Sessions (futur)

> Non actif en v1 (pas d'authentification). À implémenter lors de la migration MySQL.

### Configuration PHP recommandée

```php
// À placer AVANT session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);      // HTTPS obligatoire
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);  // 1h

session_start();
```

### Checklist sessions

- [ ] `session_start()` avec les paramètres ci-dessus
- [ ] `session_regenerate_id(true)` appelé après chaque login réussi
- [ ] `session_regenerate_id(true)` appelé après chaque changement de privilège
- [ ] Session détruite à la déconnexion (`session_destroy()` + suppression cookie)
- [ ] Cookie de session avec `HttpOnly` activé
- [ ] Cookie de session avec `Secure` activé (HTTPS uniquement)
- [ ] Cookie de session avec `SameSite=Lax`
- [ ] Durée de session limitée (timeout inactivité côté serveur)
- [ ] Pas de session ID dans les URLs (`use_only_cookies = 1`)

---

## 4. Authentification (futur)

> Non actif en v1. À implémenter lors de la migration MySQL.

### Hachage des mots de passe

```php
// Création
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Vérification
if (password_verify($password, $hash)) { /* OK */ }

// Rehash si l'algo change
if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    // Mettre à jour en BDD
}
```

### Checklist authentification

- [ ] `password_hash()` avec `PASSWORD_BCRYPT` et cost >= 12
- [ ] `password_verify()` pour la comparaison (jamais de comparaison directe)
- [ ] Rehash automatique si les paramètres changent
- [ ] Limitation des tentatives de connexion (rate limiting)
  - Max 5 tentatives / 15 minutes par IP
  - Stocker les tentatives en BDD ou en cache
  - Bloquer temporairement l'IP (pas le compte, pour éviter le DoS)
- [ ] Message d'erreur générique ("Identifiants incorrects", jamais "Email inconnu")
- [ ] Réinitialisation de mot de passe par email
  - Token aléatoire : `bin2hex(random_bytes(32))`
  - Stocké hashé en BDD (`hash('sha256', $token)`)
  - Durée de validité : 1 heure maximum
  - Usage unique : supprimé après utilisation
  - Lien HTTPS avec token en paramètre GET
- [ ] Pas de "remember me" sans implémentation sécurisée (token long terme en BDD)

---

## 5. CSRF

### Principe
Tout formulaire POST doit inclure un token CSRF validé côté serveur.

### Implémentation

```php
// Génération (à placer dans functions.php)
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Dans le formulaire HTML
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

// Validation côté serveur (avant tout traitement)
function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF invalide.');
    }
}
```

### Checklist CSRF

- [x] Fonction `csrf_token()` disponible dans `functions.php`
- [ ] Token stocké en session
- [ ] Comparaison avec `hash_equals()` (protection timing attack)
- [ ] Token présent sur TOUS les formulaires POST
  - [ ] Formulaire contact
  - [ ] Formulaire inscription
  - [ ] Formulaire login (futur)
  - [ ] Formulaire profil (futur)
  - [ ] Actions back-office (futur)
- [ ] Token renouvelé après chaque soumission valide (optionnel mais recommandé)

---

## 6. SQL (futur MySQL)

> Non actif en v1 (données mockées). À implémenter lors de la migration MySQL.

### Règle absolue : PDO + requêtes préparées

```php
// CORRECT — requête préparée
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// CORRECT — avec IN() dynamique
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM missions WHERE id IN ($placeholders)");
$stmt->execute($ids);

// INCORRECT — ne jamais faire ça
$sql = "SELECT * FROM users WHERE email = '" . $email . "'";  // injection SQL
$sql = "SELECT * FROM users WHERE email = '$email'";          // idem
```

### Configuration PDO recommandée

```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=zone85;charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,  // important : désactive l'émulation
    ]
);
```

### Checklist SQL

- [ ] PDO utilisé exclusivement (pas de mysqli procedural)
- [ ] `PDO::ATTR_EMULATE_PREPARES => false` activé
- [ ] Toutes les requêtes utilisent des paramètres nommés (`:param`) ou positionnels (`?`)
- [ ] Aucune concaténation de variable utilisateur dans une requête SQL
- [ ] Noms de colonnes et de tables jamais issus directement de `$_GET`/`$_POST`
  - Si dynamique, valider contre une whitelist explicite
- [ ] Credentials MySQL dans `.env` ou config serveur, jamais dans le dépôt git
- [ ] Compte MySQL dédié à l'application avec droits minimaux (pas de `GRANT ALL`)

---

## 7. Upload de fichiers (futur)

> Non actif en v1. À implémenter pour le Hall de la Zone (photos de contributeurs).

### Checklist upload

**Validation côté serveur :**
- [ ] Taille maximale : 5 Mo (`$_FILES['file']['size'] <= 5 * 1024 * 1024`)
- [ ] Extensions autorisées : `jpg`, `jpeg`, `png`, `webp` — whitelist stricte
- [ ] Vérification du type MIME réel avec `finfo_file()` (pas `$_FILES['type']`, falsifiable)
- [ ] Vérification que le fichier est une vraie image avec `getimagesize()`
- [ ] Interdiction des fichiers SVG uploadés par les utilisateurs (vecteur XSS)
- [ ] Rejet de tout fichier avec extension double (ex: `photo.php.jpg`)

**Stockage sécurisé :**
- [ ] Renommage forcé du fichier : `uniqid('', true) . '_' . bin2hex(random_bytes(8)) . '.' . $ext`
- [ ] Jamais stocker le nom original comme nom de fichier final
- [ ] Stocker le nom original uniquement en BDD pour la modération (`media.original_name`)
- [ ] Stocker hors webroot si possible, ou dans un dossier protégé
- [ ] `.htaccess` dans le dossier uploads : `php_flag engine off` + `Options -Indexes`

**Traitement de l'image :**
- [ ] Redimensionner via GD ou Imagick (supprime aussi les métadonnées EXIF)
- [ ] Supprimer les données EXIF explicitement si pas de redimensionnement
- [ ] Vérifier les dimensions minimales/maximales
- [ ] Convertir en WebP si possible (performance + format moderne)

**Modération :**
- [ ] Toute photo uploadée est en statut `pending` par défaut
- [ ] Un modérateur valide avant affichage dans le Hall
- [ ] Photos rejetées conservées X jours pour audit, puis supprimées
- [ ] Logs de modération dans `media` (status + timestamps)

---

## 8. Anti-triche gamification

### Principe général
**Aucune valeur de score, XP ou points ne doit jamais venir du client.** Tout est calculé côté serveur.

### Architecture attendue

```
Client → POST /participer.php (mission_id) → Serveur vérifie → Serveur attribue XP
                              ↑
                    Jamais xp=50 dans le POST
```

### Checklist anti-triche

**Attribution des points :**
- [ ] XP attribués uniquement par le serveur, sur la base de `missions.xp_success` en BDD
- [ ] Points de clan calculés depuis `missions.clan_points_success`
- [ ] Jamais de paramètre `xp` ou `points` accepté depuis le formulaire
- [ ] Toute attribution logguée dans `xp_logs` et `clan_score_logs`

**Contrôle des participations :**
- [ ] Une participation par utilisateur par mission (UNIQUE KEY `user_id, mission_id`)
- [ ] Vérification du statut de la mission (active, non expirée)
- [ ] Vérification que l'utilisateur est actif (`is_active = 1`)
- [ ] Vérification que l'utilisateur appartient à un clan

**Détection des abus :**
- [ ] Rate limiting sur les actions sensibles (participation, vote, upload) : max N requêtes / minute / IP
- [ ] Détection des soumissions répétées rapides (timestamp delta < seuil)
- [ ] Logs d'IP pour les participations et votes
- [ ] Alerte si un utilisateur gagne trop d'XP trop vite (seuil configurable)

**Correction manuelle :**
- [ ] Interface back-office pour modifier manuellement xp_total et clan_scores
- [ ] Toute correction manuelle logguée avec raison + admin_id

**Votes :**
- [ ] Un vote par utilisateur par cible
- [ ] Validation que la cible (mission, media) est en statut `active` ou `approved`
- [ ] L'utilisateur ne peut pas voter pour lui-même

---

## 9. Back-office (futur)

> Non créé en v1. À développer avant la mise en production complète.

### Checklist back-office

**Accès :**
- [ ] URL non listée dans robots.txt (déjà `Disallow: /admin/`)
- [ ] Authentification distincte ou renforcée (2FA recommandé)
- [ ] Accès restreint par IP si possible (`.htaccess` ou config serveur)

**Gestion des rôles :**
- [ ] Rôle `admin` : accès complet
- [ ] Rôle `modérateur` : validation photos, commentaires, participations
- [ ] Rôle `éditeur` : gestion des missions, saisons, textes

**Sécurité des actions :**
- [ ] Token CSRF sur toutes les actions POST du back-office
- [ ] Confirmation (double validation) sur les actions destructives (suppression, bannissement)
- [ ] Toutes les actions sensibles logguées : qui, quoi, quand, ancienne valeur

**Logs :**
- [ ] Table `admin_logs` : `admin_id`, `action`, `target_type`, `target_id`, `old_value`, `new_value`, `created_at`
- [ ] Logs non modifiables par les modérateurs

---

## 10. Données sensibles

### Checklist secrets et configuration

- [x] Aucun credential dans le dépôt git
- [x] `.gitignore` couvre : `.env`, `*.log`, `uploads/`, `config.local.php`
- [ ] Variables de connexion MySQL dans `.env` (non versionné) ou variables d'environnement serveur
- [ ] `config.php` lit les valeurs depuis `$_ENV` ou `getenv()`, jamais en dur
- [ ] Clés d'API (email, paiement futur) dans `.env`
- [ ] `.env.example` versionné avec les clés mais sans valeurs réelles

```php
// config.php — lecture sécurisée
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'zone85');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

---

## 11. Séparation dev / prod

### Configuration

```php
// Détecter l'environnement
define('APP_ENV', getenv('APP_ENV') ?: 'prod');

// Affichage des erreurs
if (APP_ENV === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/log/zone85/php_errors.log');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
```

### Checklist environnements

- [ ] Variable `APP_ENV` définie sur le serveur de production (`prod`)
- [ ] `display_errors = Off` en prod
- [ ] `log_errors = On` en prod avec chemin de log sécurisé
- [ ] Logs PHP hors webroot
- [ ] Pages d'erreur personnalisées (404, 500) sans stack trace visible
- [x] Données mockées dans `data.php` clairement identifiées (TODO: → MySQL)
- [ ] Aucun `var_dump()` ou `print_r()` laissé en production
- [ ] Fichiers `.php` de debug (phpinfo, test.php) absents du serveur prod

---

## 12. Points d'action prioritaires avant lancement

Liste ordonnée des actions à compléter avant la mise en production publique.

1. **Activer et tester les headers CSP** en mode `report-only` puis passer en mode actif après validation
2. **Implémenter les sessions sécurisées** avec tous les flags cookie (`HttpOnly`, `Secure`, `SameSite=Lax`)
3. **Créer le système d'authentification** avec `password_hash()` et rate limiting sur le login
4. **Migrer vers MySQL + PDO** avec requêtes préparées sur toutes les interactions BDD
5. **Sécuriser le formulaire de contact** : CSRF, validation serveur, rate limiting, stockage en BDD (`contact_messages`)
6. **Implémenter la protection CSRF** sur tous les formulaires POST avec `hash_equals()`
7. **Configurer les variables d'environnement** sur le serveur (DB, clés API) et retirer tout credential de `config.php`
8. **Vérifier le `.gitignore`** : `.env`, `uploads/`, `*.log`, fichiers de config locaux
9. **Implémenter le module d'upload sécurisé** (si Hall activé) : finfo, getimagesize, renommage, GD resize, modération
10. **Auditer toutes les pages** avec un scanner (ex: OWASP ZAP, Nikto) avant ouverture au public

---

*Document maintenu par l'équipe Zone 85. Ne pas mettre de données réelles (credentials, tokens) dans ce fichier.*
