<?php
// ============================================================
// admin/mission-edit.php — Créer / Éditer une mission
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin.php';
require_once '../includes/repositories.php';

require_admin();

$admin_current = 'missions';
$pdo           = db();
$mission_id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit       = $mission_id > 0;
$mission       = null;
$flash_ok      = null;
$flash_err     = null;

// Charger mission existante pour édition
if ($is_edit && $pdo) {
    try {
        $s = $pdo->prepare("SELECT * FROM missions WHERE id = :id LIMIT 1");
        $s->execute([':id' => $mission_id]);
        $mission = $s->fetch() ?: null;
        if (!$mission) {
            $flash_err = 'Mission introuvable.';
            $is_edit   = false;
            $mission_id = 0;
        }
    } catch (PDOException $e) {
        error_log('[ZONE85 admin/mission-edit] ' . $e->getMessage());
        $flash_err = 'Erreur lors du chargement de la mission.';
    }
}

$admin_page_title = $is_edit ? 'Éditer : ' . ($mission['title'] ?? '') : 'Nouvelle mission';

// ── Récupérer les saisons pour le select ─────────────────
$seasons = [];
if ($pdo) {
    try {
        $seasons = $pdo->query("SELECT id, title FROM seasons ORDER BY status = 'active' DESC, id DESC")->fetchAll();
    } catch (PDOException $e) { /* silence */ }
}

// Badges disponibles
$available_badges = [];
if ($pdo) {
    try {
        $available_badges = $pdo->query("SELECT id, title, icon FROM badges ORDER BY title ASC")->fetchAll();
    } catch (PDOException $e) { /* silence — table badges peut ne pas encore avoir de données */ }
}

// ── Traitement POST ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_err = 'Token de sécurité invalide. Recharge la page.';
    } else {
        // Récupérer les champs
        $title        = safe_input($_POST['title']        ?? '', 200);
        $slug_raw     = safe_input($_POST['slug']         ?? '', 200);
        $description  = safe_input($_POST['description']  ?? '', 2000);
        $instructions = safe_input($_POST['instructions'] ?? '', 5000);
        $mtype        = $_POST['mission_type']   ?? 'vote';
        $season_id    = (int)($_POST['season_id']   ?? 0) ?: null;
        $game_id      = (int)($_POST['game_id']     ?? 0) ?: null;
        $status       = $_POST['status']         ?? 'draft';
        $val_mode     = $_POST['validation_mode'] ?? 'auto';
        $is_collective= isset($_POST['is_collective']) ? 1 : 0;
        $xp_part      = max(0, (int)($_POST['xp_participation'] ?? 0));
        $xp_success   = max(0, (int)($_POST['xp_success']       ?? 0));
        $cp_part      = max(0, (int)($_POST['clan_points_participation'] ?? 0));
        $cp_success   = max(0, (int)($_POST['clan_points_success']       ?? 0));
        $start_date   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date     = !empty($_POST['end_date'])   ? $_POST['end_date']   : null;
        $display_hall = isset($_POST['display_in_hall']) ? 1 : 0;
        $badge_reward_id = !empty($_POST['badge_reward_id']) ? (int)$_POST['badge_reward_id'] : null;

        // Validation serveur
        $errors = [];
        if (!$title)    $errors[] = 'Le titre est obligatoire.';
        if (!$mtype)    $errors[] = 'Le type de mission est obligatoire.';
        if (!in_array($val_mode, ['auto','manual','hybrid'])) $errors[] = 'Mode de validation invalide.';
        if (!in_array($status,   ['draft','active','closed','archived'])) $errors[] = 'Statut invalide.';

        $valid_types = ['seasonal_collective','quiz','vote','photo_challenge','keto_kole_tche',
                        'rando','weather_mission','investigation','hidden_hunt','premium_game'];
        if (!in_array($mtype, $valid_types)) $errors[] = 'Type de mission invalide.';

        // Générer slug si vide
        if (empty($slug_raw)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $title)));
            $slug = trim($slug, '-');
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug_raw));
        }
        // Rendre le slug unique si création ou si slug changé
        if ($pdo && $slug) {
            try {
                $base_slug = $slug;
                $counter   = 1;
                while (true) {
                    $sq = $pdo->prepare("SELECT id FROM missions WHERE slug = :slug LIMIT 1");
                    $sq->execute([':slug' => $slug]);
                    $found = $sq->fetch();
                    if (!$found || ($is_edit && (int)$found['id'] === $mission_id)) break;
                    $slug = $base_slug . '-' . $counter++;
                }
            } catch (PDOException $e) { /* silence */ }
        }

        if (empty($errors) && $pdo) {
            try {
                if ($is_edit) {
                    $s = $pdo->prepare("
                        UPDATE missions SET
                            title = :title, slug = :slug,
                            description = :desc, instructions = :instr,
                            mission_type = :mtype, season_id = :sid,
                            game_id = :gid, status = :status,
                            validation_mode = :vmode,
                            is_collective = :coll,
                            xp_participation = :xpp, xp_success = :xps,
                            clan_points_participation = :cpp, clan_points_success = :cps,
                            start_date = :sdate, end_date = :edate,
                            display_in_hall = :hall,
                            badge_reward_id = :bid,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $s->execute([
                        ':title' => $title, ':slug' => $slug,
                        ':desc'  => $description ?: null, ':instr' => $instructions ?: null,
                        ':mtype' => $mtype, ':sid' => $season_id, ':gid' => $game_id,
                        ':status'=> $status, ':vmode' => $val_mode, ':coll' => $is_collective,
                        ':xpp'   => $xp_part, ':xps' => $xp_success,
                        ':cpp'   => $cp_part,  ':cps' => $cp_success,
                        ':sdate' => $start_date, ':edate' => $end_date,
                        ':hall'  => $display_hall, ':bid' => $badge_reward_id,
                        ':id' => $mission_id,
                    ]);
                    $flash_ok = 'Mission mise à jour.';
                    // Recharger
                    $s2 = $pdo->prepare("SELECT * FROM missions WHERE id = :id LIMIT 1");
                    $s2->execute([':id' => $mission_id]);
                    $mission = $s2->fetch() ?: $mission;
                } else {
                    $s = $pdo->prepare("
                        INSERT INTO missions
                            (title, slug, description, instructions, mission_type,
                             season_id, game_id, status, validation_mode, is_collective,
                             xp_participation, xp_success,
                             clan_points_participation, clan_points_success,
                             start_date, end_date, display_in_hall, badge_reward_id)
                        VALUES
                            (:title, :slug, :desc, :instr, :mtype,
                             :sid, :gid, :status, :vmode, :coll,
                             :xpp, :xps, :cpp, :cps,
                             :sdate, :edate, :hall, :bid)
                    ");
                    $s->execute([
                        ':title' => $title, ':slug' => $slug,
                        ':desc'  => $description ?: null, ':instr' => $instructions ?: null,
                        ':mtype' => $mtype, ':sid' => $season_id, ':gid' => $game_id,
                        ':status'=> $status, ':vmode' => $val_mode, ':coll' => $is_collective,
                        ':xpp'   => $xp_part, ':xps' => $xp_success,
                        ':cpp'   => $cp_part,  ':cps' => $cp_success,
                        ':sdate' => $start_date, ':edate' => $end_date,
                        ':hall'  => $display_hall, ':bid' => $badge_reward_id,
                    ]);
                    $new_id = (int)$pdo->lastInsertId();
                    $flash_ok   = 'Mission créée !';
                    $is_edit    = true;
                    $mission_id = $new_id;
                    $s2 = $pdo->prepare("SELECT * FROM missions WHERE id = :id LIMIT 1");
                    $s2->execute([':id' => $new_id]);
                    $mission = $s2->fetch() ?: null;
                    // Fil communautaire — nouvelle mission active
                    if ($status === 'active' && function_exists('push_community_feed')) {
                        push_community_feed('mission_new', [
                            'mission_id' => $new_id,
                            'title'      => 'Nouvelle mission : ' . mb_substr($title, 0, 60),
                            'icon_emoji' => '🎯',
                            'link_url'   => '../mission.php?id=' . $new_id,
                        ]);
                    }
                    // Redirect pour changer l'URL
                    header('Location: mission-edit.php?id=' . $new_id . '&ok=created');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('[ZONE85 admin/mission-edit] ' . $e->getMessage());
                $flash_err = 'Erreur base de données. Vérifiez les logs.';
            }
        } else {
            $flash_err = implode(' ', $errors);
        }
    }
}

// Flash depuis redirect
if (isset($_GET['ok']) && $_GET['ok'] === 'created') {
    $flash_ok = 'Mission créée avec succès !';
}

// Valeurs du formulaire (mission ou defaults)
$f = [
    'title'                    => $mission['title']                    ?? '',
    'slug'                     => $mission['slug']                     ?? '',
    'description'              => $mission['description']              ?? '',
    'instructions'             => $mission['instructions']             ?? '',
    'mission_type'             => $mission['mission_type']             ?? 'vote',
    'season_id'                => $mission['season_id']                ?? '',
    'game_id'                  => $mission['game_id']                  ?? '',
    'status'                   => $mission['status']                   ?? 'draft',
    'validation_mode'          => $mission['validation_mode']          ?? 'auto',
    'is_collective'            => (bool)($mission['is_collective']     ?? false),
    'xp_participation'         => $mission['xp_participation']         ?? 5,
    'xp_success'               => $mission['xp_success']               ?? 0,
    'clan_points_participation'=> $mission['clan_points_participation'] ?? 1,
    'clan_points_success'      => $mission['clan_points_success']       ?? 0,
    'start_date'               => $mission['start_date']               ?? '',
    'end_date'                 => $mission['end_date']                  ?? '',
    'display_in_hall'          => (bool)($mission['display_in_hall']   ?? false),
    'badge_reward_id'          => $mission['badge_reward_id'] ?? null,
];

require_once '_admin-header.php';
?>

<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title"><?= $is_edit ? 'Éditer la mission' : 'Nouvelle mission' ?></h1>
    <p class="adm-page-sub">
      <?php if ($is_edit && $mission): ?>
        #<?= $mission_id ?> —
        <a href="../mission.php?id=<?= $mission_id ?>" target="_blank" style="color:#ea5649;text-decoration:none;font-weight:700">Voir sur le site ↗</a>
      <?php else: ?>
        Créer une nouvelle mission Zone85.
      <?php endif; ?>
    </p>
  </div>
  <div class="adm-page-actions">
    <a href="missions.php" class="btn-adm btn-adm-ghost">← Retour</a>
    <?php if ($is_edit): ?>
    <a href="participations.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-ghost">Participations</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($flash_ok): ?>
<div class="adm-flash adm-flash-ok">✅ <?= e($flash_ok) ?></div>
<?php endif; ?>
<?php if ($flash_err): ?>
<div class="adm-flash adm-flash-err">⚠️ <?= e($flash_err) ?></div>
<?php endif; ?>

<form method="POST" autocomplete="off">
  <?= csrf_field() ?>

  <div class="adm-card">
    <div class="adm-card-title">Informations principales</div>
    <div class="adm-form-grid">

      <div class="adm-field adm-form-full">
        <label class="adm-label">Titre <span>*</span></label>
        <input type="text" name="title" class="adm-input"
               value="<?= e($f['title']) ?>" required maxlength="200"
               placeholder="Ex : Photo du coucher de soleil sur les marais">
      </div>

      <div class="adm-field">
        <label class="adm-label">Slug (URL)</label>
        <input type="text" name="slug" class="adm-input"
               value="<?= e($f['slug']) ?>" maxlength="200"
               placeholder="Généré automatiquement si vide">
        <span class="adm-hint">Lettres minuscules, tirets. Ex : photo-coucher-soleil</span>
      </div>

      <div class="adm-field">
        <label class="adm-label">Type de mission <span>*</span></label>
        <select name="mission_type" class="adm-select" required>
          <?php
          $types = [
            'seasonal_collective' => '🏆 Grande Mission de Saison',
            'quiz'                => '🧠 Quiz',
            'vote'                => '🗳️ Vote',
            'photo_challenge'     => '📸 Photo',
            'keto_kole_tche'      => '🥐 KTC',
            'rando'               => '🥾 Rando',
            'weather_mission'     => '🌤️ Météo',
            'investigation'       => '🔍 Enquête',
            'hidden_hunt'         => '🗝️ Chasse cachée',
            'premium_game'        => '⭐ Jeu Premium',
          ];
          foreach ($types as $v => $l):
          ?>
          <option value="<?= $v ?>" <?= $f['mission_type'] === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label">Mode de validation <span>*</span></label>
        <select name="validation_mode" class="adm-select" required>
          <option value="auto"   <?= $f['validation_mode'] === 'auto'   ? 'selected' : '' ?>>✅ Auto — XP immédiat</option>
          <option value="manual" <?= $f['validation_mode'] === 'manual' ? 'selected' : '' ?>>👀 Manuel — validation admin</option>
          <option value="hybrid" <?= $f['validation_mode'] === 'hybrid' ? 'selected' : '' ?>>🔄 Hybride</option>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label">Statut</label>
        <select name="status" class="adm-select">
          <option value="draft"    <?= $f['status'] === 'draft'    ? 'selected' : '' ?>>Brouillon</option>
          <option value="active"   <?= $f['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="closed"   <?= $f['status'] === 'closed'   ? 'selected' : '' ?>>Fermée</option>
          <option value="archived" <?= $f['status'] === 'archived' ? 'selected' : '' ?>>Archivée</option>
        </select>
      </div>

      <div class="adm-field">
        <label class="adm-label">Saison</label>
        <select name="season_id" class="adm-select">
          <option value="">— Aucune saison —</option>
          <?php foreach ($seasons as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)$f['season_id'] === (int)$s['id'] ? 'selected' : '' ?>>
            <?= e($s['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label">Description courte</label>
        <textarea name="description" class="adm-textarea" maxlength="2000"
                  placeholder="Résumé affiché sur la page mission…"><?= e($f['description']) ?></textarea>
      </div>

      <div class="adm-field adm-form-full">
        <label class="adm-label">Consignes de participation</label>
        <textarea name="instructions" class="adm-textarea" style="min-height:120px" maxlength="5000"
                  placeholder="Instructions détaillées pour les membres…"><?= e($f['instructions']) ?></textarea>
      </div>

    </div>
  </div>

  <!-- Récompenses -->
  <div class="adm-card">
    <div class="adm-card-title">XP & Points clan</div>
    <div style="font-size:.78rem;color:#6b7f96;margin-bottom:16px;line-height:1.6">
      <strong>XP à vie :</strong> permanent, jamais remis à zéro.
      <strong>Points clan :</strong> comptabilisés pour la saison active.
    </div>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label">XP participation <span>⚡</span></label>
        <input type="number" name="xp_participation" class="adm-input"
               value="<?= (int)$f['xp_participation'] ?>" min="0" max="500">
        <span class="adm-hint">Attribués immédiatement si mode auto/hybride</span>
      </div>

      <div class="adm-field">
        <label class="adm-label">XP réussite <span>🎯</span></label>
        <input type="number" name="xp_success" class="adm-input"
               value="<?= (int)$f['xp_success'] ?>" min="0" max="1000">
        <span class="adm-hint">Attribués uniquement après validation admin</span>
      </div>

      <div class="adm-field">
        <label class="adm-label">Points clan — participation 🛡️</label>
        <input type="number" name="clan_points_participation" class="adm-input"
               value="<?= (int)$f['clan_points_participation'] ?>" min="0" max="500">
        <span class="adm-hint">Attribués si auto/hybride</span>
      </div>

      <div class="adm-field">
        <label class="adm-label">Points clan — réussite 🛡️</label>
        <input type="number" name="clan_points_success" class="adm-input"
               value="<?= (int)$f['clan_points_success'] ?>" min="0" max="1000">
        <span class="adm-hint">Attribués après validation admin</span>
      </div>

    </div>
  </div>

  <!-- Dates & options -->
  <div class="adm-card">
    <div class="adm-card-title">Dates & Options</div>
    <div class="adm-form-grid">

      <div class="adm-field">
        <label class="adm-label">Date de début</label>
        <input type="date" name="start_date" class="adm-input"
               value="<?= e($f['start_date']) ?>">
      </div>

      <div class="adm-field">
        <label class="adm-label">Date de fin</label>
        <input type="date" name="end_date" class="adm-input"
               value="<?= e($f['end_date']) ?>">
      </div>

      <div class="adm-field" style="justify-content:center;padding-top:8px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="is_collective" <?= $f['is_collective'] ? 'checked' : '' ?>>
          <span class="adm-label" style="margin:0">Mission collective 🛡️</span>
        </label>
      </div>

      <div class="adm-field" style="justify-content:center;padding-top:8px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="display_in_hall" <?= $f['display_in_hall'] ? 'checked' : '' ?>>
          <span class="adm-label" style="margin:0">Afficher dans le Hall 🏆</span>
        </label>
      </div>

    </div>
  </div>

  <!-- Badge de récompense -->
  <div class="adm-card">
    <div class="adm-card-title">🏅 Badge de récompense (optionnel)</div>

    <details style="margin-bottom:16px;background:#f8f9fa;border-radius:8px;padding:0">
      <summary style="cursor:pointer;padding:10px 14px;font-size:.82rem;font-weight:700;color:#1e40af;list-style:none">
        ℹ️ Comment fonctionne l'attribution des badges ? (cliquer pour lire)
      </summary>
      <div style="padding:0 14px 14px;font-size:.8rem;color:#374151;line-height:1.7">
        <p style="margin-bottom:10px"><strong>3 façons d'attribuer un badge à un membre :</strong></p>
        <ol style="margin:0 0 12px 16px;padding:0">
          <li style="margin-bottom:6px">
            <strong>⚡ Automatique via condition</strong> — Badges avec <em>condition_type = xp_threshold / mission_success / rando_validated / registration / season</em>.
            Attribués automatiquement par le système dès que la condition est remplie. Rien à faire en tant qu'admin.
          </li>
          <li style="margin-bottom:6px">
            <strong>🎯 Récompense de mission</strong> — Ce champ ci-dessous.
            Si tu sélectionnes un badge ici, il sera attribué automatiquement quand un admin valide la participation
            (ou immédiatement si la mission est en mode <em>auto</em>). Attribution unique par membre.
          </li>
          <li style="margin-bottom:6px">
            <strong>👤 Attribution manuelle</strong> — Badges avec <em>condition_type = manual</em> (ex : "Oeil de Faucon").
            Jamais attribués automatiquement. L'admin les attribue à la main depuis
            <a href="users.php" style="color:#1e40af">Utilisateurs → Modifier → section Badges</a>.
            Parfait pour les "coups de coeur" ou récompenses exceptionnelles.
          </li>
        </ol>
        <p style="color:#6b7280;font-size:.75rem">💡 Un badge avec <em>condition_type = manual</em> sélectionné ici ne sera PAS attribué automatiquement — il faut le passer en <em>mission_reward</em> ou le donner à la main.</p>
      </div>
    </details>

    <div class="adm-field" style="max-width:360px">
      <label class="adm-label">Badge attribué à la complétion (mode auto &amp; validation admin)</label>
      <select name="badge_reward_id" class="adm-select">
        <option value="">— Aucun badge —</option>
        <?php foreach ($available_badges as $b): ?>
        <option value="<?= (int)$b['id'] ?>" <?= (int)($f['badge_reward_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
          <?= e($b['icon_emoji'] ?? $b['icon'] ?? '🏅') ?> <?= e($b['title']) ?>
          <?php if (($b['condition_type'] ?? '') === 'manual'): ?>(⚠️ manuel — ne s'attribue pas automatiquement)<?php endif; ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($available_badges)): ?>
      <span class="adm-hint">Aucun badge disponible. Créez des badges dans <a href="badges.php">Badges</a>.</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <button type="submit" class="btn-adm btn-adm-primary" style="min-width:200px">
      <?= $is_edit ? '💾 Enregistrer les modifications' : '✨ Créer la mission' ?>
    </button>
    <a href="missions.php" class="btn-adm btn-adm-ghost">Annuler</a>
  </div>

</form>

<?php if ($is_edit && ($mission['mission_type'] ?? '') === 'hidden_hunt'): ?>
<div class="adm-card" style="margin-top:24px;border:2px solid rgba(201,150,42,.35);background:linear-gradient(135deg,#fffdf5,#fff9ec)">
  <div class="adm-card-title" style="color:#8a6020">🗝️ Objets cachés — Gestion du jeu de piste</div>
  <p style="font-size:.85rem;color:#6b5020;line-height:1.65;margin-bottom:20px">
    Gérez les objets à placer sur les pages du site. Chaque objet peut avoir sa propre position, image et message de succès.
  </p>
  <?php
  // Compter les collectibles
  $_coll_count = 0;
  try {
    $sc = $pdo->prepare("SELECT COUNT(*) FROM mission_collectibles WHERE mission_id = :id");
    $sc->execute([':id' => $mission_id]);
    $_coll_count = (int)$sc->fetchColumn();
  } catch (PDOException $e) { /* silence */ }
  ?>
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <a href="collectibles.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-primary" style="background:#c9962a;border-color:#c9962a">
      🗝️ Gérer les objets cachés
      <?php if ($_coll_count > 0): ?>
      <span style="margin-left:8px;background:rgba(255,255,255,.25);padding:1px 8px;border-radius:999px;font-size:.78rem"><?= $_coll_count ?></span>
      <?php endif; ?>
    </a>
    <a href="collectible-edit.php?mission_id=<?= $mission_id ?>" class="btn-adm btn-adm-ghost">
      + Ajouter un objet
    </a>
    <span style="font-size:.8rem;color:#8a6020;font-weight:600">
      <?= $_coll_count ?> objet<?= $_coll_count > 1 ? 's' : '' ?> configuré<?= $_coll_count > 1 ? 's' : '' ?>
    </span>
  </div>
  <p style="font-size:.75rem;color:#8a6020;margin-top:14px;padding-top:12px;border-top:1px solid rgba(201,150,42,.2)">
    💡 Mode de validation recommandé : <strong>Auto</strong> — la participation est enregistrée automatiquement quand le membre trouve tous les objets.
  </p>
</div>
<?php endif; ?>

<?php require_once '_admin-footer.php'; ?>
