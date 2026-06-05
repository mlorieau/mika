<?php
// ============================================================
// admin/badges.php — Gestion des badges
// ============================================================
$admin_current    = 'badges';
$admin_page_title = 'Badges';

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';

require_admin();

$pdo    = db();
$flash  = null;          // ['type' => 'ok|err', 'msg' => '...']

// ── Constantes d'affichage ────────────────────────────────────
$rarity_colors = [
    'common'    => ['bg' => '#6b7f96', 'label' => 'Common'],
    'uncommon'  => ['bg' => '#2a9d5c', 'label' => 'Uncommon'],
    'rare'      => ['bg' => '#12314e', 'label' => 'Rare'],
    'epic'      => ['bg' => '#9b59b6', 'label' => 'Epic'],
    'legendary' => ['bg' => '#C9962A', 'label' => 'Legendary'],
];
$categories = ['exploration','clan','saison','meteo','rando','culture','invisible','general'];
$condition_types = [
    'manual'           => 'Manuel',
    'xp_threshold'     => 'Seuil XP',
    'mission_success'  => 'Mission réussie',
    'season'           => 'Saison',
    'special'          => 'Spécial',
];

// ── Helpers ───────────────────────────────────────────────────
function badge_slug(string $title): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($title));
    $s = strtolower(preg_replace('/[^a-z0-9]+/', '-', $s));
    $s = trim($s, '-');
    return $s . '-' . substr(md5($title), 0, 4);
}

function unique_badge_slug(PDO $pdo, string $base_slug): string {
    $slug = $base_slug;
    $i    = 0;
    do {
        $st = $pdo->prepare('SELECT id FROM badges WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        $exists = (bool)$st->fetchColumn();
        if ($exists) {
            $i++;
            $slug = $base_slug . '-' . $i;
        }
    } while ($exists);
    return $slug;
}

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action']     ?? '';

    if (!verify_csrf_token($token)) {
        $flash = ['type' => 'err', 'msg' => 'Token CSRF invalide. Veuillez réessayer.'];
    } else {
        // ── CREATE ────────────────────────────────────────────
        if ($action === 'create') {
            $title           = trim($_POST['title']           ?? '');
            $description     = trim($_POST['description']     ?? '');
            $icon_emoji      = trim($_POST['icon_emoji']      ?? '');
            $category        = $_POST['category']        ?? 'general';
            $rarity          = $_POST['rarity']          ?? 'common';
            $color_primary   = $_POST['color_primary']   ?? '#ea5649';
            $condition_type  = $_POST['condition_type']  ?? 'manual';
            $condition_value = isset($_POST['condition_value']) && $_POST['condition_value'] !== ''
                               ? (int)$_POST['condition_value'] : null;

            if ($title === '') {
                $flash = ['type' => 'err', 'msg' => 'Le titre est requis.'];
            } else {
                try {
                    $slug = unique_badge_slug($pdo, badge_slug($title));
                    $st = $pdo->prepare(
                        'INSERT INTO badges
                         (title, slug, category, description, icon_emoji, rarity, color_primary, condition_type, condition_value)
                         VALUES (:t, :sl, :cat, :d, :ie, :r, :cp, :ct, :cv)'
                    );
                    $st->execute([
                        ':t'   => $title,
                        ':sl'  => $slug,
                        ':cat' => $category,
                        ':d'   => $description !== '' ? $description : null,
                        ':ie'  => $icon_emoji  !== '' ? $icon_emoji  : null,
                        ':r'   => $rarity,
                        ':cp'  => $color_primary,
                        ':ct'  => $condition_type,
                        ':cv'  => $condition_value,
                    ]);
                    $flash = ['type' => 'ok', 'msg' => 'Badge « ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' » créé avec le slug <code>' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</code>.'];
                } catch (PDOException $e) {
                    error_log('[ZONE85 admin/badges create] ' . $e->getMessage());
                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de la création : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')];
                }
            }
        }

        // ── UPDATE ────────────────────────────────────────────
        elseif ($action === 'update') {
            $id              = (int)($_POST['id'] ?? 0);
            $title           = trim($_POST['title']           ?? '');
            $description     = trim($_POST['description']     ?? '');
            $icon_emoji      = trim($_POST['icon_emoji']      ?? '');
            $rarity          = $_POST['rarity']         ?? 'common';
            $color_primary   = $_POST['color_primary']  ?? '#ea5649';
            $condition_type  = $_POST['condition_type'] ?? 'manual';
            $condition_value = isset($_POST['condition_value']) && $_POST['condition_value'] !== ''
                               ? (int)$_POST['condition_value'] : null;

            if ($id <= 0 || $title === '') {
                $flash = ['type' => 'err', 'msg' => 'Données invalides pour la mise à jour.'];
            } else {
                try {
                    $st = $pdo->prepare(
                        'UPDATE badges
                         SET title=:t, description=:d, icon_emoji=:ie, rarity=:r,
                             color_primary=:cp, condition_type=:ct, condition_value=:cv,
                             updated_at=NOW()
                         WHERE id=:id'
                    );
                    $st->execute([
                        ':t'   => $title,
                        ':d'   => $description !== '' ? $description : null,
                        ':ie'  => $icon_emoji  !== '' ? $icon_emoji  : null,
                        ':r'   => $rarity,
                        ':cp'  => $color_primary,
                        ':ct'  => $condition_type,
                        ':cv'  => $condition_value,
                        ':id'  => $id,
                    ]);
                    $flash = ['type' => 'ok', 'msg' => 'Badge #' . $id . ' mis à jour.'];
                } catch (PDOException $e) {
                    error_log('[ZONE85 admin/badges update] ' . $e->getMessage());
                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de la mise à jour : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')];
                }
            }
        }

        // ── DELETE (soft-hide) ────────────────────────────────
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $flash = ['type' => 'err', 'msg' => 'ID invalide.'];
            } else {
                try {
                    // Vérifie que is_hidden existe dans la table
                    $col_check = $pdo->query("SHOW COLUMNS FROM badges LIKE 'is_hidden'")->fetch();
                    if ($col_check) {
                        $st = $pdo->prepare('UPDATE badges SET is_hidden=1 WHERE id=:id');
                        $st->execute([':id' => $id]);
                        $flash = ['type' => 'ok', 'msg' => 'Badge #' . $id . ' masqué (soft-hide).'];
                    } else {
                        $flash = ['type' => 'err', 'msg' => 'Colonne is_hidden absente — suppression non effectuée.'];
                    }
                } catch (PDOException $e) {
                    error_log('[ZONE85 admin/badges delete] ' . $e->getMessage());
                    $flash = ['type' => 'err', 'msg' => 'Erreur lors du masquage : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')];
                }
            }
        }
    }
    // Redirect PRG pour éviter double-POST
    if ($flash && $flash['type'] === 'ok') {
        $_SESSION['adm_flash'] = $flash;
        header('Location: badges.php');
        exit;
    }
}

// Récupération flash depuis session (après redirect)
if (empty($flash) && !empty($_SESSION['adm_flash'])) {
    $flash = $_SESSION['adm_flash'];
    unset($_SESSION['adm_flash']);
}

// ── Lecture : liste des badges ────────────────────────────────
$badges = [];
if ($pdo) {
    try {
        $badges = $pdo->query(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM user_badges ub WHERE ub.badge_id = b.id) AS member_count
             FROM badges b
             WHERE b.is_hidden = 0
             ORDER BY b.category ASC, b.rarity ASC, b.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/badges list] ' . $e->getMessage());
        // Si user_badges n'existe pas ou autre erreur, fallback sans sous-requête
        try {
            $badges = $pdo->query(
                'SELECT b.*, 0 AS member_count
                 FROM badges b
                 WHERE b.is_hidden = 0
                 ORDER BY b.category ASC, b.rarity ASC, b.id ASC'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            error_log('[ZONE85 admin/badges list fallback] ' . $e2->getMessage());
        }
    }
}

// ── Stats par catégorie ───────────────────────────────────────
$stats_cat = array_fill_keys($categories, 0);
$stats_rar = array_fill_keys(array_keys($rarity_colors), 0);
foreach ($badges as $b) {
    $cat = $b['category'] ?? 'general';
    $rar = $b['rarity']   ?? 'common';
    if (isset($stats_cat[$cat])) $stats_cat[$cat]++;
    if (isset($stats_rar[$rar])) $stats_rar[$rar]++;
}
$total_badges = count($badges);

// ── Rendu HTML ────────────────────────────────────────────────
require '_admin-header.php';
?>

<style>
/* ── Badges page spécifique ── */
.badge-rarity {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: .7rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .05em; color: #fff;
}
.rarity-common    { background: #6b7f96; }
.rarity-uncommon  { background: #2a9d5c; }
.rarity-rare      { background: #12314e; }
.rarity-epic      { background: #9b59b6; }
.rarity-legendary { background: #C9962A; }

.badge-emoji-cell { font-size: 1.6rem; line-height: 1; }
.badge-color-dot  { display: inline-block; width: 14px; height: 14px; border-radius: 50%; vertical-align: middle; border: 1.5px solid rgba(0,0,0,.12); margin-right: 6px; }

/* Panel création */
.adm-create-panel {
    display: none;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(12,30,46,.09);
    border: 1px solid rgba(234,86,73,.2);
    padding: 24px;
    margin-bottom: 24px;
    animation: slideDown .2s ease;
}
.adm-create-panel.open { display: block; }
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Edit row inline */
.adm-edit-row { display: none; background: #fdfaf7; }
.adm-edit-row.open { display: table-row; }
.adm-edit-inner { padding: 16px; }

/* Stats rarity strip */
.rar-strip { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.rar-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 999px;
    font-size: .75rem; font-weight: 700; color: #fff;
}

/* condition_value row toggle */
.cond-val-row { display: none; }
.cond-val-row.visible { display: flex; }
</style>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">🏅 Badges</h1>
        <p class="adm-page-sub"><?= $total_badges ?> badge<?= $total_badges !== 1 ? 's' : '' ?> actifs</p>
    </div>
    <div class="adm-page-actions">
        <button class="btn-adm btn-adm-primary" onclick="toggleCreatePanel()">+ Nouveau badge</button>
    </div>
</div>

<?php if ($flash): ?>
<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>">
    <?= $flash['type'] === 'ok' ? '✓' : '✕' ?>
    <span><?= $flash['msg'] ?></span>
</div>
<?php endif; ?>

<!-- ── Stats ──────────────────────────────────────────────────── -->
<div class="adm-card" style="margin-bottom:20px">
    <p class="adm-card-title">Répartition par catégorie</p>
    <div class="adm-stats" style="margin-bottom:0">
        <?php foreach ($stats_cat as $cat => $cnt): if ($cnt === 0) continue; ?>
        <div class="adm-stat">
            <div class="adm-stat-label"><?= htmlspecialchars(ucfirst($cat), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="adm-stat-value"><?= $cnt ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:16px">
        <p class="adm-card-title" style="margin-bottom:8px">Répartition par rareté</p>
        <div class="rar-strip">
            <?php foreach ($rarity_colors as $rar => $meta): ?>
            <span class="rar-pill" style="background:<?= htmlspecialchars($meta['bg'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                <strong><?= $stats_rar[$rar] ?? 0 ?></strong>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Panneau création ───────────────────────────────────────── -->
<div class="adm-create-panel" id="createPanel">
    <p class="adm-card-title" style="margin-bottom:16px">Créer un badge</p>
    <form method="post" action="badges.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="adm-form-grid">
            <div class="adm-field">
                <label class="adm-label" for="c_title">Titre <span>*</span></label>
                <input class="adm-input" type="text" id="c_title" name="title" required maxlength="100"
                       placeholder="Ex : Marcheur des crêtes">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="c_icon_emoji">Emoji / Icône</label>
                <input class="adm-input" type="text" id="c_icon_emoji" name="icon_emoji" maxlength="8"
                       placeholder="🏅" style="font-size:1.3rem">
                <span class="adm-hint">Max 8 caractères (emoji ou code)</span>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="c_category">Catégorie <span>*</span></label>
                <select class="adm-select" id="c_category" name="category" required>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="c_rarity">Rareté <span>*</span></label>
                <select class="adm-select" id="c_rarity" name="rarity" required>
                    <?php foreach ($rarity_colors as $r => $meta): ?>
                    <option value="<?= $r ?>"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="c_color">Couleur primaire</label>
                <input class="adm-input" type="color" id="c_color" name="color_primary" value="#ea5649" style="height:44px;padding:6px 10px">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="c_ctype">Type de condition</label>
                <select class="adm-select" id="c_ctype" name="condition_type"
                        onchange="toggleCondVal('c_cval_row', this.value)">
                    <?php foreach ($condition_types as $k => $v): ?>
                    <option value="<?= $k ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="adm-field cond-val-row" id="c_cval_row">
                <label class="adm-label" for="c_cval">Valeur de condition</label>
                <input class="adm-input" type="number" id="c_cval" name="condition_value" min="0" placeholder="Ex : 500">
            </div>
            <div class="adm-field adm-form-full">
                <label class="adm-label" for="c_desc">Description</label>
                <textarea class="adm-textarea" id="c_desc" name="description" rows="3"
                          placeholder="Description du badge..."></textarea>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px">
            <button type="submit" class="btn-adm btn-adm-primary">Créer le badge</button>
            <button type="button" class="btn-adm btn-adm-ghost" onclick="toggleCreatePanel()">Annuler</button>
        </div>
    </form>
</div>

<!-- ── Tableau principal ──────────────────────────────────────── -->
<div class="adm-card" style="padding:0;overflow:hidden">
    <?php if (empty($badges)): ?>
    <div class="adm-empty">
        <div class="adm-empty-icon">🏅</div>
        <p>Aucun badge trouvé. Créez-en un ci-dessus.</p>
    </div>
    <?php else: ?>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width:52px">Icône</th>
                    <th>Titre</th>
                    <th>Catégorie</th>
                    <th>Rareté</th>
                    <th>Condition</th>
                    <th style="text-align:right">Membres</th>
                    <th style="text-align:right;width:160px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($badges as $b):
                $bid       = (int)$b['id'];
                $rar       = $b['rarity'] ?? 'common';
                $rar_meta  = $rarity_colors[$rar] ?? ['bg' => '#6b7f96', 'label' => ucfirst($rar)];
                $ctype     = $b['condition_type'] ?? 'manual';
                $cval      = $b['condition_value'];
                $ctype_lbl = $condition_types[$ctype] ?? $ctype;
                if ($cval !== null) $ctype_lbl .= ' (' . (int)$cval . ')';
            ?>
            <tr id="row-<?= $bid ?>">
                <td class="badge-emoji-cell" style="text-align:center">
                    <?php if (!empty($b['icon_emoji'])): ?>
                        <?= htmlspecialchars($b['icon_emoji'], ENT_QUOTES, 'UTF-8') ?>
                    <?php else: ?>
                        <span style="font-size:.8rem;color:#bbb">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:700"><?= htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:.72rem;color:#6b7f96;font-family:monospace"><?= htmlspecialchars($b['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($b['color_primary'])): ?>
                    <span class="badge-color-dot" style="background:<?= htmlspecialchars($b['color_primary'], ENT_QUOTES, 'UTF-8') ?>"></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="adm-badge badge-draft"><?= htmlspecialchars(ucfirst($b['category'] ?? 'general'), ENT_QUOTES, 'UTF-8') ?></span>
                </td>
                <td>
                    <span class="badge-rarity rarity-<?= htmlspecialchars($rar, ENT_QUOTES, 'UTF-8') ?>"
                          style="background:<?= htmlspecialchars($rar_meta['bg'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($rar_meta['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td style="font-size:.8rem;color:#4a5f73"><?= htmlspecialchars($ctype_lbl, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="text-align:right;font-weight:700"><?= (int)($b['member_count'] ?? 0) ?></td>
                <td style="text-align:right">
                    <button class="btn-adm btn-adm-ghost btn-adm-sm"
                            onclick="toggleEditRow(<?= $bid ?>)">Modifier</button>
                    <form method="post" action="badges.php" style="display:inline"
                          onsubmit="return confirm('Masquer ce badge ?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $bid ?>">
                        <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm">Masquer</button>
                    </form>
                </td>
            </tr>
            <!-- Ligne d'édition inline -->
            <tr class="adm-edit-row" id="edit-row-<?= $bid ?>">
                <td colspan="7">
                    <div class="adm-edit-inner">
                        <p class="adm-card-title" style="margin-bottom:12px">Modifier le badge #<?= $bid ?></p>
                        <form method="post" action="badges.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= $bid ?>">
                            <div class="adm-form-grid">
                                <div class="adm-field">
                                    <label class="adm-label">Titre <span>*</span></label>
                                    <input class="adm-input" type="text" name="title" required maxlength="100"
                                           value="<?= htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="adm-field">
                                    <label class="adm-label">Emoji / Icône</label>
                                    <input class="adm-input" type="text" name="icon_emoji" maxlength="8"
                                           style="font-size:1.3rem"
                                           value="<?= htmlspecialchars($b['icon_emoji'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="adm-field">
                                    <label class="adm-label">Rareté</label>
                                    <select class="adm-select" name="rarity">
                                        <?php foreach ($rarity_colors as $r => $rmeta): ?>
                                        <option value="<?= $r ?>" <?= $rar === $r ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rmeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="adm-field">
                                    <label class="adm-label">Couleur primaire</label>
                                    <input class="adm-input" type="color" name="color_primary"
                                           style="height:44px;padding:6px 10px"
                                           value="<?= htmlspecialchars($b['color_primary'] ?? '#ea5649', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="adm-field">
                                    <label class="adm-label">Type de condition</label>
                                    <select class="adm-select" name="condition_type"
                                            id="e_ctype_<?= $bid ?>"
                                            onchange="toggleCondVal('e_cval_row_<?= $bid ?>', this.value)">
                                        <?php foreach ($condition_types as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= $ctype === $k ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="adm-field cond-val-row<?= !in_array($ctype, ['manual','special']) ? ' visible' : '' ?>"
                                     id="e_cval_row_<?= $bid ?>">
                                    <label class="adm-label">Valeur de condition</label>
                                    <input class="adm-input" type="number" name="condition_value" min="0"
                                           value="<?= $cval !== null ? (int)$cval : '' ?>">
                                </div>
                                <div class="adm-field adm-form-full">
                                    <label class="adm-label">Description</label>
                                    <textarea class="adm-textarea" name="description" rows="3"><?= htmlspecialchars($b['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>
                            <div style="display:flex;gap:10px;margin-top:14px">
                                <button type="submit" class="btn-adm btn-adm-success">Enregistrer</button>
                                <button type="button" class="btn-adm btn-adm-ghost"
                                        onclick="toggleEditRow(<?= $bid ?>)">Annuler</button>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$admin_scripts = <<<'JS'
<script>
function toggleCreatePanel() {
    var p = document.getElementById('createPanel');
    p.classList.toggle('open');
    if (p.classList.contains('open')) {
        p.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}

function toggleEditRow(id) {
    var row = document.getElementById('edit-row-' + id);
    if (!row) return;
    // Ferme toutes les autres lignes d'édition
    document.querySelectorAll('.adm-edit-row.open').forEach(function(r) {
        if (r.id !== 'edit-row-' + id) r.classList.remove('open');
    });
    row.classList.toggle('open');
    if (row.classList.contains('open')) {
        row.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}

function toggleCondVal(rowId, condType) {
    var row = document.getElementById(rowId);
    if (!row) return;
    if (condType === 'manual' || condType === 'special') {
        row.classList.remove('visible');
        var inp = row.querySelector('input');
        if (inp) inp.value = '';
    } else {
        row.classList.add('visible');
    }
}
</script>
JS;

require '_admin-footer.php';
