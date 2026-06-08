<?php

// ============================================================

// admin/seasons.php � Gestion des saisons

// ============================================================

require_once '../includes/config.php';

require_once '../includes/functions.php';

require_once '../includes/db.php';

require_once '../includes/auth.php';

require_once '../includes/admin.php';

require_once '../includes/repositories.php';

require_once '../includes/mailer.php';



require_admin();



$admin_current    = 'seasons';

$admin_page_title = 'Saisons';



$pdo    = db();

$flash  = null; // ['type' => 'ok'|'err'|'info', 'msg' => '...']



//  Traitement POST 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {



    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {

        $flash = ['type' => 'err', 'msg' => 'Token CSRF invalide. Action annul�e.'];

    } else {

        $action = $_POST['action'] ?? '';



        //  CREATE 

        if ($action === 'create') {

            $title            = safe_input($_POST['title']            ?? '', 120);

            $slug             = safe_input($_POST['slug']             ?? '', 120);

            $description      = safe_input($_POST['description']      ?? '', 500);

            $description_long = trim($_POST['description_long']       ?? '');

            $color_primary    = safe_input($_POST['color_primary']    ?? '#ea5649', 20);

            $color_secondary  = safe_input($_POST['color_secondary']  ?? '#0c1e2e', 20);

            $emoji            = safe_input($_POST['emoji']            ?? '', 10);

            $start_date       = $_POST['start_date'] ?? null;

            $end_date         = $_POST['end_date']   ?? null;

            $badge_id         = !empty($_POST['badge_id'])   ? (int)$_POST['badge_id']   : null;

            $mission_id       = !empty($_POST['mission_id']) ? (int)$_POST['mission_id'] : null;



            if (!$title || !$slug) {

                $flash = ['type' => 'err', 'msg' => 'Le titre et le slug sont obligatoires.'];

            } else {

                try {

                    $stmt = $pdo->prepare("

                        INSERT INTO seasons

                            (title, slug, description, description_long,

                             color_primary, color_secondary, emoji,

                             start_date, end_date, status,

                             badge_id, main_mission_id, created_at)

                        VALUES

                            (:title, :slug, :description, :description_long,

                             :color_primary, :color_secondary, :emoji,

                             :start_date, :end_date, 'upcoming',

                             :badge_id, :mission_id, NOW())

                    ");

                    $stmt->execute([

                        ':title'            => $title,

                        ':slug'             => $slug,

                        ':description'      => $description,

                        ':description_long' => $description_long,

                        ':color_primary'    => $color_primary,

                        ':color_secondary'  => $color_secondary,

                        ':emoji'            => $emoji,

                        ':start_date'       => $start_date ?: null,

                        ':end_date'         => $end_date   ?: null,

                        ':badge_id'         => $badge_id,

                        ':mission_id'       => $mission_id,

                    ]);

                    $flash = ['type' => 'ok', 'msg' => "Saison � {$title} � cr��e en brouillon."];

                } catch (PDOException $e) {

                    error_log('[ZONE85 admin/seasons create] ' . $e->getMessage());

                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de la cr�ation : ' . $e->getMessage()];

                }

            }

        }



        //  ACTIVATE 

        elseif ($action === 'activate') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id > 0) {

                try {

                    $pdo->beginTransaction();

                    // Fermer les �ventuelles saisons actives pr�c�dentes

                    $pdo->prepare("UPDATE seasons SET status='archived' WHERE status='active' AND id != :id")

                        ->execute([':id' => $id]);

                    // Activer la saison demand�e

                    $pdo->prepare("UPDATE seasons SET status='active' WHERE id = :id")

                        ->execute([':id' => $id]);

                    $pdo->commit();

                    $flash = ['type' => 'ok', 'msg' => "Saison #$id activ�e. Les autres saisons actives ont �t� ferm�es."];

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) $pdo->rollBack();

                    error_log('[ZONE85 admin/seasons activate] ' . $e->getMessage());

                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de l\'activation.'];

                }

            }

        }



        //  CLOSE 

        elseif ($action === 'close') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id > 0) {

                try {

                    $pdo->beginTransaction();



                    // D�terminer le clan gagnant (score le plus �lev� sur la saison)

                    $winnerStmt = $pdo->prepare("

                        SELECT clan_id, SUM(points) AS total

                        FROM clan_score_logs

                        WHERE season_id = :sid

                        GROUP BY clan_id

                        ORDER BY total DESC

                        LIMIT 1

                    ");

                    $winnerStmt->execute([':sid' => $id]);

                    $winner = $winnerStmt->fetch();

                    $winner_clan_id = $winner ? (int)$winner['clan_id'] : null;



                    // Fermer la saison

                    $pdo->prepare("

                        UPDATE seasons

                        SET status = 'archived',

                            closed_at = NOW(),

                            winner_clan_id = :winner_clan_id

                        WHERE id = :id

                    ")->execute([':winner_clan_id' => $winner_clan_id, ':id' => $id]);



                    // Ins�rer le troph�e pour le clan gagnant

                    if ($winner_clan_id) {

                        try {

                            $pdo->prepare("

                                INSERT INTO season_trophies (season_id, winning_clan_id, awarded_at)

                                VALUES (:sid, :cid, NOW())

                            ")->execute([':sid' => $id, ':cid' => $winner_clan_id]);

                        } catch (PDOException $e) {

                            // Table season_trophies peut ne pas encore exister

                            error_log('[ZONE85 admin/seasons close trophy] ' . $e->getMessage());

                        }

                    }



                    // Cr�er un fil d'actualit� (community_feed)

                    try {

                        $pdo->prepare("

                            INSERT INTO community_feed (feed_type, ref_id, created_at)

                            VALUES ('season_closed', :sid, NOW())

                        ")->execute([':sid' => $id]);

                    } catch (PDOException $e) {

                        // Table community_feed peut ne pas encore exister

                        error_log('[ZONE85 admin/seasons close feed] ' . $e->getMessage());

                    }



                    $pdo->commit();

                    $clanMsg = $winner_clan_id ? " Le clan #$winner_clan_id remporte le troph�e." : '';

                    $flash = ['type' => 'ok', 'msg' => "Saison #$id cl�tur�e.$clanMsg"];

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) $pdo->rollBack();

                    error_log('[ZONE85 admin/seasons close] ' . $e->getMessage());

                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de la cl�ture.'];

                }

            }

        }



        //  UPDATE 

        elseif ($action === 'update') {

            $id               = (int)($_POST['id'] ?? 0);

            $title            = safe_input($_POST['title']            ?? '', 120);

            $description      = safe_input($_POST['description']      ?? '', 500);

            $description_long = trim($_POST['description_long']       ?? '');

            $color_primary    = safe_input($_POST['color_primary']    ?? '#ea5649', 20);

            $color_secondary  = safe_input($_POST['color_secondary']  ?? '#0c1e2e', 20);

            $emoji            = safe_input($_POST['emoji']            ?? '', 10);

            $start_date       = $_POST['start_date'] ?? null;

            $end_date         = $_POST['end_date']   ?? null;

            $badge_id         = !empty($_POST['badge_id'])   ? (int)$_POST['badge_id']   : null;

            $mission_id       = !empty($_POST['mission_id']) ? (int)$_POST['mission_id'] : null;



            if ($id > 0 && $title) {

                try {

                    $pdo->prepare("

                        UPDATE seasons SET

                            title            = :title,

                            description      = :description,

                            description_long = :description_long,

                            color_primary    = :color_primary,

                            color_secondary  = :color_secondary,

                            emoji            = :emoji,

                            start_date       = :start_date,

                            end_date         = :end_date,

                            badge_id         = :badge_id,

                            main_mission_id  = :mission_id,

                            updated_at       = NOW()

                        WHERE id = :id

                    ")->execute([

                        ':title'            => $title,

                        ':description'      => $description,

                        ':description_long' => $description_long,

                        ':color_primary'    => $color_primary,

                        ':color_secondary'  => $color_secondary,

                        ':emoji'            => $emoji,

                        ':start_date'       => $start_date ?: null,

                        ':end_date'         => $end_date   ?: null,

                        ':badge_id'         => $badge_id,

                        ':mission_id'       => $mission_id,

                        ':id'               => $id,

                    ]);

                    $flash = ['type' => 'ok', 'msg' => "Saison #$id mise � jour."];

                } catch (PDOException $e) {

                    error_log('[ZONE85 admin/seasons update] ' . $e->getMessage());

                    $flash = ['type' => 'err', 'msg' => 'Erreur lors de la mise � jour.'];

                }

            }

        }

    }

}



//  Chargement des saisons 

$seasons = [];

if ($pdo) {

    try {

        $seasons = $pdo->query("

            SELECT s.*,

                   COALESCE((

                       SELECT SUM(csl.points)

                       FROM clan_score_logs csl

                       WHERE csl.season_id = s.id

                   ), 0) AS total_season_score

            FROM seasons s

            ORDER BY s.id DESC

        ")->fetchAll();

    } catch (PDOException $e) {

        error_log('[ZONE85 admin/seasons list] ' . $e->getMessage());

    }

}



//  Badges disponibles 

$badges = [];

if ($pdo) {

    try {

        $badges = $pdo->query("SELECT id, name, emoji FROM badges ORDER BY name ASC")->fetchAll();

    } catch (PDOException $e) {

        error_log('[ZONE85 admin/seasons badges] ' . $e->getMessage());

    }

}



//  Missions �ligibles (grande mission / chasse cach�e) 

$missions_eligible = [];

if ($pdo) {

    try {

        $missions_eligible = $pdo->query("

            SELECT id, title, mission_type

            FROM missions

            WHERE mission_type IN ('seasonal_collective', 'hidden_hunt')

            ORDER BY title ASC

        ")->fetchAll();

    } catch (PDOException $e) {

        error_log('[ZONE85 admin/seasons missions] ' . $e->getMessage());

    }

}



//  Vue 

require_once '_admin-header.php';



// Ajouter Saisons dans la nav � patch inline topnav via JS

$admin_scripts = <<<'JS'

<script>

(function(){

    // Injecte le lien Saisons dans la topnav s'il n'existe pas

    var nav = document.querySelector('.adm-topnav');

    if (!nav) return;

    var existing = nav.querySelector('a[href="seasons.php"]');

    if (!existing) {

        var a = document.createElement('a');

        a.href = 'seasons.php';

        a.textContent = 'Saisons';

        if (window.location.pathname.endsWith('seasons.php')) a.className = 'active';

        nav.appendChild(a);

    }



    // Auto-slug depuis le titre

    var titleInput = document.getElementById('new_title');

    var slugInput  = document.getElementById('new_slug');

    if (titleInput && slugInput) {

        titleInput.addEventListener('input', function() {

            slugInput.value = titleInput.value

                .toLowerCase()

                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')

                .replace(/[^a-z0-9]+/g, '-')

                .replace(/^-+|-+$/g, '');

        });

    }



    // Toggles modales d'�dition

    document.querySelectorAll('.btn-edit-season').forEach(function(btn) {

        btn.addEventListener('click', function() {

            var id = this.dataset.id;

            var panel = document.getElementById('edit-panel-' + id);

            if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';

        });

    });



    // Toggle formulaire de cr�ation

    var btnCreate = document.getElementById('btn-show-create');

    var panelCreate = document.getElementById('panel-create');

    if (btnCreate && panelCreate) {

        btnCreate.addEventListener('click', function() {

            panelCreate.style.display = panelCreate.style.display === 'none' ? 'block' : 'none';

        });

    }

})();

</script>

JS;

?>



<!-- Page header -->

<div class="adm-page-header">

  <div>

    <h1 class="adm-page-title"> Saisons</h1>

    <p class="adm-page-sub">Cr�er, activer et cl�turer les saisons de jeu Zone85.</p>

  </div>

  <div class="adm-page-actions">

    <button id="btn-show-create" class="btn-adm btn-adm-primary">+ Cr�er une saison</button>

  </div>

</div>



<?php if ($flash): ?>

<div class="adm-flash adm-flash-<?= $flash['type'] === 'ok' ? 'ok' : ($flash['type'] === 'info' ? 'info' : 'err') ?>">

  <?= $flash['type'] === 'ok' ? '' : ($flash['type'] === 'info' ? '' : '�') ?>

  <span><?= e($flash['msg']) ?></span>

</div>

<?php endif; ?>



<?php if (!$pdo): ?>

<div class="adm-flash adm-flash-err"> <span>Base de donn�es indisponible.</span></div>

<?php endif; ?>



<!--  Formulaire de cr�ation  -->

<div id="panel-create" class="adm-card" style="display:none;margin-bottom:24px">

  <p class="adm-card-title">Nouvelle saison</p>

  <form method="post" action="seasons.php">

    <?= csrf_field() ?>

    <input type="hidden" name="action" value="create">

    <div class="adm-form-grid">



      <div class="adm-field">

        <label class="adm-label" for="new_title">Titre <span>*</span></label>

        <input id="new_title" name="title" type="text" class="adm-input" required maxlength="120" placeholder="Saison des Chemins Creux">

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_slug">Slug <span>*</span></label>

        <input id="new_slug" name="slug" type="text" class="adm-input" required maxlength="120" placeholder="saison-des-chemins-creux">

        <span class="adm-hint">G�n�r� automatiquement depuis le titre, �ditable.</span>

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_emoji">Emoji</label>

        <input id="new_emoji" name="emoji" type="text" class="adm-input" maxlength="10" placeholder="">

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_color_primary">Couleur primaire</label>

        <input id="new_color_primary" name="color_primary" type="color" class="adm-input" value="#ea5649" style="height:42px;padding:4px 8px">

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_color_secondary">Couleur secondaire</label>

        <input id="new_color_secondary" name="color_secondary" type="color" class="adm-input" value="#0c1e2e" style="height:42px;padding:4px 8px">

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_start_date">Date de d�but</label>

        <input id="new_start_date" name="start_date" type="date" class="adm-input">

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_end_date">Date de fin</label>

        <input id="new_end_date" name="end_date" type="date" class="adm-input">

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_badge_id">Badge de saison</label>

        <select id="new_badge_id" name="badge_id" class="adm-select">

          <option value="">� Aucun badge �</option>

          <?php foreach ($badges as $b): ?>

            <option value="<?= (int)$b['id'] ?>"><?= e($b['emoji'] ?? '') ?> <?= e($b['name']) ?></option>

          <?php endforeach; ?>

        </select>

      </div>



      <div class="adm-field">

        <label class="adm-label" for="new_mission_id">Grande Mission / Chasse cach�e</label>

        <select id="new_mission_id" name="mission_id" class="adm-select">

          <option value="">� Aucune mission principale �</option>

          <?php foreach ($missions_eligible as $m): ?>

            <option value="<?= (int)$m['id'] ?>">[<?= e(mission_type_label($m['mission_type'])) ?>] <?= e($m['title']) ?></option>

          <?php endforeach; ?>

        </select>

      </div>



      <div class="adm-field adm-form-full">

        <label class="adm-label" for="new_description">Description courte</label>

        <input id="new_description" name="description" type="text" class="adm-input" maxlength="500" placeholder="R�sum� en une phrase�">

      </div>



      <div class="adm-field adm-form-full">

        <label class="adm-label" for="new_description_long">Description longue</label>

        <textarea id="new_description_long" name="description_long" class="adm-textarea" rows="5" placeholder="Texte de pr�sentation complet de la saison�"></textarea>

      </div>



    </div>

    <div style="margin-top:20px;display:flex;gap:10px">

      <button type="submit" class="btn-adm btn-adm-primary">Cr�er la saison</button>

      <button type="button" class="btn-adm btn-adm-ghost" onclick="document.getElementById('panel-create').style.display='none'">Annuler</button>

    </div>

  </form>

</div>



<!--  Liste des saisons  -->

<div class="adm-card">

  <p class="adm-card-title">Toutes les saisons (<?= count($seasons) ?>)</p>



  <?php if (empty($seasons)): ?>

    <div class="adm-empty">

      <div class="adm-empty-icon"></div>

      <p>Aucune saison pour le moment. Cr�ez la premi�re !</p>

    </div>

  <?php else: ?>

    <div class="adm-table-wrap">

      <table class="adm-table">

        <thead>

          <tr>

            <th>Emoji</th>

            <th>Titre</th>

            <th>Slug</th>

            <th>D�but</th>

            <th>Fin</th>

            <th>Statut</th>

            <th>Score total</th>

            <th>Actions</th>

          </tr>

        </thead>

        <tbody>

          <?php foreach ($seasons as $s): ?>

          <tr>

            <td style="font-size:1.4rem;text-align:center"><?= e($s['emoji'] ?? '') ?></td>

            <td style="font-weight:700"><?= e($s['title']) ?></td>

            <td><code style="font-size:.75rem;color:#6b7f96"><?= e($s['slug']) ?></code></td>

            <td><?= $s['start_date'] ? htmlspecialchars(date('d/m/Y', strtotime($s['start_date'])), ENT_QUOTES, 'UTF-8') : '<span style="color:#aaa">�</span>' ?></td>

            <td><?= $s['end_date']   ? htmlspecialchars(date('d/m/Y', strtotime($s['end_date'])),   ENT_QUOTES, 'UTF-8') : '<span style="color:#aaa">�</span>' ?></td>

            <td>

              <span class="adm-badge badge-<?= e($s['status']) ?>">

                <?= e($s['status']) ?>

              </span>

            </td>

            <td style="font-weight:700"><?= format_score((int)($s['total_season_score'] ?? 0)) ?></td>

            <td>

              <div style="display:flex;gap:6px;flex-wrap:wrap">

                <!-- �diter -->

                <button class="btn-adm btn-adm-ghost btn-adm-sm btn-edit-season" data-id="<?= (int)$s['id'] ?>"> �diter</button>



                <?php if ($s['status'] === 'upcoming'): ?>

                  <!-- Activer -->

                  <form method="post" action="seasons.php" style="display:inline" onsubmit="return confirm('Activer cette saison ? Les autres saisons actives seront ferm�es.')">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="activate">

                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                    <button type="submit" class="btn-adm btn-adm-success btn-adm-sm"> Activer</button>

                  </form>

                <?php endif; ?>



                <?php if ($s['status'] === 'active'): ?>

                  <!-- Cl�turer -->

                  <form method="post" action="seasons.php" style="display:inline" onsubmit="return confirm('Cl�turer d�finitivement cette saison ? Cette action est irr�versible.')">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="close">

                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                    <button type="submit" class="btn-adm btn-adm-danger btn-adm-sm"> Cl�turer</button>

                  </form>

                <?php endif; ?>

              </div>



              <!-- Panneau d'�dition inline -->

              <div id="edit-panel-<?= (int)$s['id'] ?>" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid #f0ece7">

                <form method="post" action="seasons.php">

                  <?= csrf_field() ?>

                  <input type="hidden" name="action" value="update">

                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                  <div class="adm-form-grid">



                    <div class="adm-field">

                      <label class="adm-label">Titre <span>*</span></label>

                      <input name="title" type="text" class="adm-input" required maxlength="120" value="<?= e($s['title']) ?>">

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Emoji</label>

                      <input name="emoji" type="text" class="adm-input" maxlength="10" value="<?= e($s['emoji'] ?? '') ?>">

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Couleur primaire</label>

                      <input name="color_primary" type="color" class="adm-input" value="<?= e($s['color_primary'] ?? '#ea5649') ?>" style="height:42px;padding:4px 8px">

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Couleur secondaire</label>

                      <input name="color_secondary" type="color" class="adm-input" value="<?= e($s['color_secondary'] ?? '#0c1e2e') ?>" style="height:42px;padding:4px 8px">

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Date de d�but</label>

                      <input name="start_date" type="date" class="adm-input" value="<?= e($s['start_date'] ?? '') ?>">

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Date de fin</label>

                      <input name="end_date" type="date" class="adm-input" value="<?= e($s['end_date'] ?? '') ?>">

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Badge de saison</label>

                      <select name="badge_id" class="adm-select">

                        <option value="">� Aucun badge �</option>

                        <?php foreach ($badges as $b): ?>

                          <option value="<?= (int)$b['id'] ?>" <?= (int)($s['badge_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>

                            <?= e($b['emoji'] ?? '') ?> <?= e($b['name']) ?>

                          </option>

                        <?php endforeach; ?>

                      </select>

                    </div>



                    <div class="adm-field">

                      <label class="adm-label">Grande Mission / Chasse cach�e</label>

                      <select name="mission_id" class="adm-select">

                        <option value="">� Aucune �</option>

                        <?php foreach ($missions_eligible as $m): ?>

                          <option value="<?= (int)$m['id'] ?>" <?= (int)($s['main_mission_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>

                            [<?= e(mission_type_label($m['mission_type'])) ?>] <?= e($m['title']) ?>

                          </option>

                        <?php endforeach; ?>

                      </select>

                    </div>



                    <div class="adm-field adm-form-full">

                      <label class="adm-label">Description courte</label>

                      <input name="description" type="text" class="adm-input" maxlength="500" value="<?= e($s['description'] ?? '') ?>">

                    </div>



                    <div class="adm-field adm-form-full">

                      <label class="adm-label">Description longue</label>

                      <textarea name="description_long" class="adm-textarea" rows="4"><?= e($s['description_long'] ?? '') ?></textarea>

                    </div>



                  </div>

                  <div style="margin-top:16px;display:flex;gap:10px">

                    <button type="submit" class="btn-adm btn-adm-primary btn-adm-sm">Enregistrer</button>

                    <button type="button" class="btn-adm btn-adm-ghost btn-adm-sm" onclick="document.getElementById('edit-panel-<?= (int)$s['id'] ?>').style.display='none'">Annuler</button>

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



<?php require_once '_admin-footer.php'; ?>







