<?php
// ============================================================
// admin/levels.php — Gestion des niveaux XP
// ============================================================
$admin_current    = 'levels';
$admin_page_title = 'Niveaux XP';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

$pdo   = db();
$flash = null;

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'err', 'msg' => 'Token CSRF invalide.'];
    } elseif (($_POST['action'] ?? '') === 'save_levels') {
        $levels_post = $_POST['levels'] ?? [];
        $errors = [];
        $prev_xp = -1;

        // Validate order before saving
        for ($lv = 1; $lv <= 10; $lv++) {
            $xp = (int)($levels_post[$lv]['xp_required'] ?? 0);
            if ($lv > 1 && $xp <= $prev_xp) {
                $errors[] = "Le niveau $lv doit avoir plus de XP que le niveau " . ($lv - 1) . ".";
            }
            $prev_xp = $xp;
        }

        if ($errors) {
            $flash = ['type' => 'err', 'msg' => implode('<br>', $errors)];
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO xp_levels (level, name, xp_required, color, emoji)
                     VALUES (:lv, :nm, :xp, :col, :em)
                     ON DUPLICATE KEY UPDATE name=VALUES(name), xp_required=VALUES(xp_required),
                                             color=VALUES(color), emoji=VALUES(emoji)"
                );
                for ($lv = 1; $lv <= 10; $lv++) {
                    $stmt->execute([
                        ':lv'  => $lv,
                        ':nm'  => trim($levels_post[$lv]['name']  ?? 'Niveau ' . $lv),
                        ':xp'  => (int)($levels_post[$lv]['xp_required'] ?? 0),
                        ':col' => trim($levels_post[$lv]['color'] ?? '#6b7f96'),
                        ':em'  => trim($levels_post[$lv]['emoji'] ?? '⭐'),
                    ]);
                }

                // Recalculer le niveau de tous les joueurs
                $pdo->exec("
                    UPDATE users u
                    SET u.level = (
                        SELECT COALESCE(MAX(xl.level), 1)
                        FROM xp_levels xl
                        WHERE xl.xp_required <= u.xp_total
                    )
                ");
                $flash = ['type' => 'ok', 'msg' => 'Niveaux mis à jour. Les niveaux de tous les joueurs ont été recalculés.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'err', 'msg' => 'Erreur DB : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')];
            }
        }
    }
}

// ── Chargement des niveaux ────────────────────────────────────
$xp_levels = [];
$table_exists = false;

if ($pdo) {
    try {
        $rows = $pdo->query("SELECT * FROM xp_levels ORDER BY level ASC")->fetchAll();
        if ($rows) {
            $table_exists = true;
            foreach ($rows as $r) {
                $xp_levels[(int)$r['level']] = $r;
            }
        }
    } catch (PDOException $e) {
        // Table absente
    }
}

// Valeurs par défaut si table vide ou absente
$defaults = [
    1  => ['name' => 'Novice',        'xp_required' => 0,     'color' => '#6b7f96', 'emoji' => '🌱'],
    2  => ['name' => 'Explorateur',   'xp_required' => 50,    'color' => '#2a9d5c', 'emoji' => '🧭'],
    3  => ['name' => 'Aventurier',    'xp_required' => 100,   'color' => '#12314e', 'emoji' => '🏕️'],
    4  => ['name' => 'Expert',        'xp_required' => 250,   'color' => '#0c6291', 'emoji' => '⚡'],
    5  => ['name' => 'Gardien',       'xp_required' => 500,   'color' => '#9b59b6', 'emoji' => '🛡️'],
    6  => ['name' => 'Légende',       'xp_required' => 1000,  'color' => '#C9962A', 'emoji' => '🌟'],
    7  => ['name' => 'Grand Pisteur', 'xp_required' => 2500,  'color' => '#ea5649', 'emoji' => '🗺️'],
    8  => ['name' => 'Vétéran',       'xp_required' => 5000,  'color' => '#8b1a1a', 'emoji' => '🔥'],
    9  => ['name' => 'Ancêtre',       'xp_required' => 10000, 'color' => '#1a1a2e', 'emoji' => '💎'],
    10 => ['name' => 'Immortel',      'xp_required' => 20000, 'color' => '#0c1e2e', 'emoji' => '👑'],
];
for ($i = 1; $i <= 10; $i++) {
    if (!isset($xp_levels[$i])) $xp_levels[$i] = $defaults[$i];
}

// Stats joueurs par niveau (si table présente)
$level_counts = [];
if ($pdo && $table_exists) {
    try {
        $rc = $pdo->query("SELECT level, COUNT(*) AS cnt FROM users GROUP BY level")->fetchAll();
        foreach ($rc as $r) $level_counts[(int)$r['level']] = (int)$r['cnt'];
    } catch (PDOException $e) {}
}
$total_users = array_sum($level_counts);

require_once '_admin-header.php';
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">🎖 Niveaux XP</h1>
        <p class="adm-page-sub">Définissez les seuils XP et les noms de chaque niveau joueur (1 à 10).<br>
        La sauvegarde recalcule automatiquement le niveau de tous les membres.</p>
    </div>
</div>

<?php if (!$table_exists): ?>
<div class="adm-alert adm-alert-warn" style="margin-bottom:20px">
    ⚠️ La table <code>xp_levels</code> n'existe pas encore en base. Passez d'abord la migration
    <code>033_xp_levels_table.sql</code> avant d'utiliser cette page.
</div>
<?php endif; ?>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] ?>" style="margin-bottom:20px">
    <?= $flash['msg'] ?>
</div>
<?php endif; ?>

<!-- Statistiques rapides -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:28px">
    <?php for ($lv = 1; $lv <= 10; $lv++):
        $cnt = $level_counts[$lv] ?? 0;
        $pct = $total_users > 0 ? round($cnt / $total_users * 100) : 0;
        $d   = $xp_levels[$lv];
    ?>
    <div style="background:#fff;border-radius:12px;padding:14px;border:1px solid rgba(0,0,0,.07);text-align:center">
        <div style="font-size:1.6rem;line-height:1;margin-bottom:6px"><?= e($d['emoji']) ?></div>
        <div style="font-size:.72rem;font-weight:900;color:#6b7f96;text-transform:uppercase;letter-spacing:.06em">Niv. <?= $lv ?></div>
        <div style="font-size:.85rem;font-weight:800;color:#0c1e2e;margin:3px 0"><?= e($d['name']) ?></div>
        <div style="font-size:1.4rem;font-weight:900;color:<?= e($d['color']) ?>"><?= $cnt ?></div>
        <div style="font-size:.68rem;color:#aaa">joueur<?= $cnt > 1 ? 's' : '' ?> · <?= $pct ?>%</div>
    </div>
    <?php endfor; ?>
</div>

<!-- Formulaire d'édition -->
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_levels">

    <div class="adm-card" style="padding:0;overflow:hidden;margin-bottom:20px">
        <div style="padding:20px 24px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between">
            <strong style="font-size:1rem;font-weight:800;color:#0c1e2e">Paramètres des niveaux</strong>
            <button type="submit" class="btn-adm btn-adm-primary" <?= !$table_exists ? 'disabled' : '' ?>>
                💾 Sauvegarder tous les niveaux
            </button>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th style="width:60px">Niv.</th>
                        <th style="width:60px">Emoji</th>
                        <th>Nom du niveau</th>
                        <th style="width:160px">XP requis</th>
                        <th style="width:120px">Couleur badge</th>
                        <th style="width:110px">Joueurs actuels</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($lv = 1; $lv <= 10; $lv++):
                        $d   = $xp_levels[$lv];
                        $cnt = $level_counts[$lv] ?? 0;
                    ?>
                    <tr>
                        <td>
                            <span style="display:inline-flex;align-items:center;justify-content:center;
                                         width:32px;height:32px;border-radius:999px;
                                         background:<?= e($d['color']) ?>;color:#fff;
                                         font-size:.78rem;font-weight:900"><?= $lv ?></span>
                        </td>
                        <td>
                            <input type="text" name="levels[<?= $lv ?>][emoji]"
                                   value="<?= htmlspecialchars($d['emoji'], ENT_QUOTES, 'UTF-8') ?>"
                                   style="width:54px;text-align:center;font-size:1.2rem;
                                          border:1px solid #e0d8d0;border-radius:8px;padding:6px;background:#fafafa"
                                   maxlength="10">
                        </td>
                        <td>
                            <input type="text" name="levels[<?= $lv ?>][name]"
                                   value="<?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>"
                                   class="adm-input" required maxlength="100"
                                   placeholder="Nom du niveau <?= $lv ?>">
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <input type="number" name="levels[<?= $lv ?>][xp_required]"
                                       value="<?= (int)$d['xp_required'] ?>"
                                       class="adm-input" required min="0" max="999999"
                                       <?= $lv === 1 ? 'readonly style="background:#f0ece7;color:#aaa"' : '' ?>>
                                <span style="font-size:.7rem;color:#999;white-space:nowrap">XP</span>
                            </div>
                        </td>
                        <td>
                            <input type="color" name="levels[<?= $lv ?>][color]"
                                   value="<?= htmlspecialchars($d['color'], ENT_QUOTES, 'UTF-8') ?>"
                                   style="width:80px;height:36px;padding:4px;border:1px solid #e0d8d0;border-radius:8px;cursor:pointer">
                        </td>
                        <td style="text-align:center">
                            <strong style="font-size:1.15rem;color:#0c1e2e"><?= $cnt ?></strong>
                            <span style="font-size:.72rem;color:#aaa;display:block">joueur<?= $cnt > 1 ? 's' : '' ?></span>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end">
        <button type="submit" class="btn-adm btn-adm-primary" <?= !$table_exists ? 'disabled' : '' ?>>
            💾 Sauvegarder et recalculer les niveaux joueurs
        </button>
    </div>
</form>

<div class="adm-card" style="margin-top:24px;padding:20px 24px">
    <h3 style="font-size:.92rem;font-weight:800;color:#0c1e2e;margin:0 0 12px">ℹ️ Notes</h3>
    <ul style="font-size:.82rem;color:#4b6074;line-height:1.8;margin:0;padding-left:18px">
        <li>Le niveau 1 démarre toujours à <strong>0 XP</strong> — ce champ n'est pas modifiable.</li>
        <li>Chaque niveau doit avoir un seuil <strong>strictement supérieur</strong> au niveau précédent.</li>
        <li>La sauvegarde <strong>recalcule immédiatement</strong> le niveau de tous les membres.</li>
        <li>Les noms et couleurs sont affichés sur les profils joueurs et le Passeport Vendéen.</li>
        <li>L'emoji est affiché dans la sidebar du profil et les badges niveau.</li>
    </ul>
</div>

<?php require_once '_admin-footer.php'; ?>
