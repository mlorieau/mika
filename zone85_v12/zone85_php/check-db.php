<?php
// ============================================================
// check-db.php — Diagnostic DB temporaire (SUPPRIMER APRÈS USAGE)
// Accès : https://www.zone85.fr/check-db.php
// ============================================================

// Sécurité minimale : token dans l'URL
$token = $_GET['t'] ?? '';
define('CHECK_TOKEN', 'z85diag2025');
if (!hash_equals(CHECK_TOKEN, $token)) {
    http_response_code(403);
    exit('403 Forbidden');
}

// Charge config sans démarrer la session ni les headers
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Zone85 — Diagnostic DB</title>
<style>
  body{font-family:monospace;max-width:760px;margin:40px auto;padding:20px;background:#f8f4ef}
  h1{color:#0c1e2e;font-size:1.2rem}
  .ok  {color:#1a7a42;font-weight:bold}
  .err {color:#c0392b;font-weight:bold}
  .row {padding:8px 0;border-bottom:1px solid #e0d8d0;display:flex;gap:16px}
  .k   {width:200px;color:#3d5166;flex-shrink:0}
  pre  {background:#0c1e2e;color:#f0e6d0;padding:14px;border-radius:8px;font-size:.8rem;overflow:auto}
  .warn{color:#8a6020;font-weight:bold}
</style>
</head>
<body>
<h1>🔍 Diagnostic Zone85 — DB &amp; Config</h1>
<p style="color:#c0392b;font-size:.8rem">⚠️ SUPPRIMER CE FICHIER après usage.</p>

<h2 style="font-size:1rem;color:#0c1e2e;margin-top:24px">Configuration</h2>
<?php
$rows = [
  'APP_ENV'  => APP_ENV,
  'BASE_URL' => BASE_URL,
  'SITE_URL' => SITE_URL,
  'DB_HOST'  => DB_HOST,
  'DB_PORT'  => (string)DB_PORT,
  'DB_NAME'  => DB_NAME ?: '<span class="err">VIDE</span>',
  'DB_USER'  => DB_USER ?: '<span class="err">VIDE</span>',
  'DB_PASS'  => DB_PASS !== '' ? '••••••' : '<span class="err">VIDE</span>',
  'DB_ENABLED' => DB_ENABLED ? 'true' : 'false',
];
foreach ($rows as $k => $v): ?>
<div class="row"><span class="k"><?= $k ?></span><span><?= $v ?></span></div>
<?php endforeach; ?>

<h2 style="font-size:1rem;color:#0c1e2e;margin-top:24px">Connexion PDO</h2>
<?php
$pdo = null;
$conn_error = '';
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '<div class="ok">✅ Connexion PDO réussie</div>';
} catch (PDOException $e) {
    $conn_error = $e->getMessage();
    echo '<div class="err">❌ Connexion échouée : ' . htmlspecialchars($conn_error) . '</div>';
}
?>

<?php if ($pdo): ?>
<h2 style="font-size:1rem;color:#0c1e2e;margin-top:24px">Tables existantes</h2>
<?php
$expected = ['users','clans','missions','participations','articles','pages','randos','settings','badges','seasons'];
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo '<pre>';
$missing = [];
foreach ($expected as $t) {
    $ok = in_array($t, $tables, true);
    if (!$ok) $missing[] = $t;
    echo ($ok ? '✅' : '❌') . ' ' . $t . "\n";
}
echo '</pre>';

if (!empty($missing)) {
    echo '<div class="err">❌ Tables manquantes : ' . implode(', ', $missing) . '</div>';
    echo '<p>→ Le schéma DB n\'a probablement pas été importé. Voir ci-dessous.</p>';
} else {
    echo '<div class="ok">✅ Toutes les tables principales sont présentes.</div>';
    // Count users
    $n = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo '<div style="margin-top:8px">👥 Membres en base : <strong>' . (int)$n . '</strong></div>';
    $np = $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
    echo '<div>📄 Pages CMS : <strong>' . (int)$np . '</strong></div>';
}
?>

<h2 style="font-size:1rem;color:#0c1e2e;margin-top:24px">User admin</h2>
<?php
try {
    $st = $pdo->query("SELECT id, pseudo, email, role, status FROM users WHERE role='admin' LIMIT 5");
    $admins = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($admins) {
        echo '<pre>';
        foreach ($admins as $a) {
            echo "id={$a['id']} pseudo={$a['pseudo']} email={$a['email']} role={$a['role']} status={$a['status']}\n";
        }
        echo '</pre>';
    } else {
        echo '<div class="warn">⚠️ Aucun utilisateur admin trouvé en base.</div>';
    }
} catch(Exception $e) {
    echo '<div class="err">Erreur lecture users : ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<?php endif; ?>

<?php if (!empty($missing) || !$pdo): ?>
<h2 style="font-size:1rem;color:#0c1e2e;margin-top:24px">🔧 Actions à faire</h2>
<pre>
1. Dans phpMyAdmin (ou votre panel hébergeur) :
   → Ouvrir votre base "<?= htmlspecialchars(DB_NAME) ?>"
   → Importer le fichier : database/schema.sql   (structure complète)
   → Puis les migrations dans : database/migrations/ (dans l'ordre 001 à 031)

2. OU importer un dump complet de votre base de dev.

3. Après import, rechargez cette page pour vérifier.
</pre>
<?php endif; ?>

<p style="margin-top:32px;font-size:.75rem;color:#999">
  ⚠️ SUPPRIMER ce fichier <code>check-db.php</code> immédiatement après diagnostic.
</p>
</body>
</html>
