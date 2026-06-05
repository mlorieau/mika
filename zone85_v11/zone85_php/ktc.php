<?php
// ============================================================
// ZONE85 — Kéto Kolé Tché V11.1
// Rubrique éditoriale mensuelle : 1 brocanteur + 1 objet mystère + 4 phases.
// Ne pas confondre avec la Quête saisonnière.
// ============================================================

$page_title       = 'Kéto Kolé Tché';
$page_description = 'Chaque mois, Zone85 part chez un brocanteur, antiquaire ou passionné local pour faire deviner un objet mystérieux vendéen.';
$page_canonical   = 'https://www.zone85.fr/ktc.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$current_page     = 'ktc';

require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$user = function_exists('current_user') ? current_user() : null;
$is_logged = $user !== null;
$user_id = $is_logged ? (int)$user['id'] : 0;
$pdo = db();

$episode = null;
$photos = [];
$propositions = [];
$user_proposition = null;
$user_vote = null;
$flash = null;
$table_missing = false;

function ktc_phase_label(string $status): string {
    return [
        'draft' => 'En préparation',
        'week1' => 'Semaine 1 — Découverte',
        'week2' => 'Semaine 2 — Nouveaux indices',
        'week3' => 'Semaine 3 — Votes',
        'revealed' => 'Révélation',
        'archived' => 'Archive',
    ][$status] ?? 'En préparation';
}
function ktc_reveal_week(string $status): int {
    return ['week1'=>1,'week2'=>2,'week3'=>3,'revealed'=>4,'archived'=>4][$status] ?? 1;
}

if ($pdo) {
    try {
        $st = $pdo->query("SELECT * FROM ktc_episodes WHERE status IN ('week1','week2','week3','revealed') ORDER BY FIELD(status,'week1','week2','week3','revealed'), COALESCE(date_week1, created_at) DESC LIMIT 1");
        $episode = $st->fetch() ?: null;

        if (!$episode) {
            $st = $pdo->query("SELECT * FROM ktc_episodes WHERE status='draft' ORDER BY created_at DESC LIMIT 1");
            $episode = $st->fetch() ?: null;
        }
    } catch (PDOException $e) {
        $table_missing = true;
    }
}

if ($pdo && $episode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_logged) {
        $flash = ['type'=>'err','msg'=>'Connecte-toi pour participer au KTC.'];
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type'=>'err','msg'=>'Session expirée. Recharge la page puis réessaie.'];
    } else {
        $action = $_POST['ktc_action'] ?? '';
        $eid = (int)$episode['id'];
        try {
            if ($action === 'propose' && in_array($episode['status'], ['week1','week2'], true)) {
                $prop = trim($_POST['proposition'] ?? '');
                if (mb_strlen($prop) < 3) {
                    $flash = ['type'=>'err','msg'=>'Ta proposition est un peu courte.'];
                } else {
                    $st = $pdo->prepare("INSERT INTO ktc_propositions (episode_id,user_id,proposition,submitted_at) VALUES (:eid,:uid,:p,NOW()) ON DUPLICATE KEY UPDATE proposition=VALUES(proposition), submitted_at=NOW()");
                    $st->execute([':eid'=>$eid, ':uid'=>$user_id, ':p'=>mb_substr($prop,0,1000)]);
                    $flash = ['type'=>'ok','msg'=>'Proposition enregistrée. La Zone enquête.'];
                }
            }
            if ($action === 'vote' && $episode['status'] === 'week3') {
                $choice = trim($_POST['vote_choice'] ?? '');
                if ($choice === '') {
                    $flash = ['type'=>'err','msg'=>'Choisis une hypothèse avant de voter.'];
                } else {
                    $st = $pdo->prepare("INSERT INTO ktc_votes (episode_id,user_id,vote_choice,voted_at) VALUES (:eid,:uid,:v,NOW()) ON DUPLICATE KEY UPDATE vote_choice=VALUES(vote_choice), voted_at=NOW()");
                    $st->execute([':eid'=>$eid, ':uid'=>$user_id, ':v'=>mb_substr($choice,0,255)]);
                    $flash = ['type'=>'ok','msg'=>'Vote enregistré. Révélation à venir.'];
                }
            }
        } catch (PDOException $e) {
            $flash = ['type'=>'err','msg'=>'Impossible d’enregistrer pour le moment.'];
        }
    }
}

if ($pdo && $episode) {
    $eid = (int)$episode['id'];
    try {
        $week = ktc_reveal_week($episode['status']);
        $st = $pdo->prepare("SELECT * FROM ktc_episode_photos WHERE episode_id=:eid AND reveal_week <= :w ORDER BY sort_order ASC, id ASC");
        $st->execute([':eid'=>$eid, ':w'=>$week]);
        $photos = $st->fetchAll();

        $st = $pdo->prepare("SELECT p.*, u.pseudo FROM ktc_propositions p LEFT JOIN users u ON u.id=p.user_id WHERE p.episode_id=:eid ORDER BY p.submitted_at DESC LIMIT 20");
        $st->execute([':eid'=>$eid]);
        $propositions = $st->fetchAll();

        if ($is_logged) {
            $st = $pdo->prepare("SELECT * FROM ktc_propositions WHERE episode_id=:eid AND user_id=:uid LIMIT 1");
            $st->execute([':eid'=>$eid, ':uid'=>$user_id]);
            $user_proposition = $st->fetch() ?: null;
            $st = $pdo->prepare("SELECT * FROM ktc_votes WHERE episode_id=:eid AND user_id=:uid LIMIT 1");
            $st->execute([':eid'=>$eid, ':uid'=>$user_id]);
            $user_vote = $st->fetch() ?: null;
        }
    } catch (PDOException $e) {
        // Mode dégradé silencieux.
    }
}

$page_styles = '<style>
.ktc-hero{background:#071521;padding:116px 0 82px;color:#fff;position:relative;overflow:hidden}.ktc-hero:before{content:"";position:absolute;inset:auto -10% -45% 45%;height:360px;background:radial-gradient(circle,rgba(234,86,73,.28),transparent 62%)}.ktc-eyebrow{font-size:.72rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:#c9962a;margin-bottom:14px}.ktc-title{font-size:clamp(2.1rem,5vw,4rem);line-height:1.02;font-weight:950;letter-spacing:-.07em;margin:0 0 18px}.ktc-lead{max-width:660px;color:rgba(255,255,255,.68);line-height:1.75;font-weight:600}.ktc-wrap{background:#f5f1ed;padding:54px 0 92px}.ktc-grid{display:grid;grid-template-columns:minmax(0,1.35fr) 380px;gap:28px}.ktc-card{background:#fff;border-radius:22px;box-shadow:0 10px 35px rgba(12,30,46,.08);border:1px solid rgba(18,49,78,.08);overflow:hidden}.ktc-card-pad{padding:30px}.ktc-phase{display:inline-flex;padding:8px 14px;border-radius:999px;background:rgba(234,86,73,.1);color:#ea5649;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em;margin-bottom:18px}.ktc-h2{font-size:1.8rem;line-height:1.15;margin:0 0 12px;color:#0c1e2e;font-weight:950;letter-spacing:-.04em}.ktc-text{color:#53677d;line-height:1.8;font-size:.95rem}.ktc-person{display:flex;gap:16px;align-items:center;margin:22px 0;padding:18px;border-radius:18px;background:#f7f2ec}.ktc-avatar{width:68px;height:68px;border-radius:18px;background:#12314e;object-fit:cover}.ktc-avatar-fallback{width:68px;height:68px;border-radius:18px;background:#12314e;display:flex;align-items:center;justify-content:center;font-size:2rem}.ktc-person strong{display:block;color:#0c1e2e}.ktc-person span{font-size:.86rem;color:#6b7f96}.ktc-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:22px}.ktc-gallery img{width:100%;height:180px;object-fit:cover;border-radius:16px;background:#eee}.ktc-form{margin-top:24px;padding:22px;border-radius:18px;background:#f9f5f0;border:1px solid #eadfd4}.ktc-form textarea,.ktc-form input[type=text]{width:100%;border:1.5px solid #d8cec4;border-radius:12px;padding:14px;font-family:inherit;font-size:.94rem}.ktc-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;background:#ea5649;color:#fff;padding:13px 22px;font-weight:900;text-decoration:none;cursor:pointer;margin-top:12px}.ktc-btn.secondary{background:#12314e}.ktc-side-title{font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#8a98a8;margin-bottom:16px}.ktc-timeline{display:grid;gap:10px}.ktc-step{padding:14px;border-radius:14px;background:#f7f2ec;border:1px solid #eadfd4;color:#53677d;font-size:.86rem}.ktc-step.active{background:#fff1ef;border-color:#ea5649;color:#0c1e2e;font-weight:800}.ktc-prop{padding:13px 0;border-bottom:1px solid #eee}.ktc-prop:last-child{border-bottom:0}.ktc-prop b{color:#0c1e2e}.ktc-prop small{color:#8a98a8}.ktc-alert{padding:14px 18px;border-radius:14px;margin-bottom:20px;font-weight:800}.ktc-alert.ok{background:#eaf8ef;color:#1f7a45}.ktc-alert.err{background:#fff0ee;color:#c0392b}.ktc-empty{text-align:center;padding:70px 26px;color:#6b7f96}.ktc-empty .big{font-size:4rem;margin-bottom:16px}@media(max-width:900px){.ktc-grid{grid-template-columns:1fr}.ktc-hero{padding:90px 0 58px}.ktc-card-pad{padding:22px}.ktc-gallery img{height:150px}}
</style>';

require_once 'includes/header.php';
?>

<section class="ktc-hero">
  <div class="container">
    <div class="ktc-eyebrow">Kéto Kolé Tché — le rendez-vous du mois</div>
    <h1 class="ktc-title">Un objet. Une rencontre. Une enquête.</h1>
    <p class="ktc-lead">Chaque mois, Zone85 pousse la porte d’un brocanteur, d’un antiquaire ou d’un passionné local. Un objet sort de l’ombre. À vous de deviner son histoire.</p>
  </div>
</section>

<section class="ktc-wrap">
  <div class="container">
    <?php if ($flash): ?><div class="ktc-alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

    <?php if ($table_missing): ?>
      <div class="ktc-card ktc-empty"><div class="big">🥐</div><h2>Le KTC éditorial est prêt côté interface.</h2><p>Il reste à importer la migration <strong>017_v11_ktc_editorial.sql</strong> pour activer les épisodes.</p></div>
    <?php elseif (!$episode): ?>
      <div class="ktc-card ktc-empty"><div class="big">🥐</div><h2>Le prochain Kéto Kolé Tché se prépare.</h2><p>Un objet, une rencontre et quelques indices arrivent bientôt dans la Zone.</p></div>
    <?php else: ?>
      <div class="ktc-grid">
        <article class="ktc-card">
          <div class="ktc-card-pad">
            <span class="ktc-phase"><?= e(ktc_phase_label($episode['status'])) ?></span>
            <h2 class="ktc-h2"><?= e($episode['title']) ?></h2>
            <p class="ktc-text"><?= nl2br(e($episode['teaser_text'] ?: 'Un objet étrange vient d’apparaître dans la Zone. Saurez-vous retrouver son usage et son histoire ?')) ?></p>

            <?php if (!empty($episode['person_name']) || !empty($episode['person_title'])): ?>
              <div class="ktc-person">
                <?php if (!empty($episode['person_photo'])): ?><img class="ktc-avatar" src="<?= e(url($episode['person_photo'])) ?>" alt="">
                <?php else: ?><div class="ktc-avatar-fallback">👤</div><?php endif; ?>
                <div><strong><?= e($episode['person_name'] ?: 'Rencontre locale') ?></strong><span><?= e($episode['person_title'] ?: 'Brocanteur, collectionneur ou passionné vendéen') ?></span><?php if (!empty($episode['person_bio'])): ?><p class="ktc-text" style="margin:8px 0 0"><?= e($episode['person_bio']) ?></p><?php endif; ?></div>
              </div>
            <?php endif; ?>

            <?php if (in_array($episode['status'], ['week2','week3','revealed','archived'], true) && !empty($episode['details_text'])): ?>
              <h3>Les nouveaux indices</h3><p class="ktc-text"><?= nl2br(e($episode['details_text'])) ?></p>
            <?php endif; ?>
            <?php if (in_array($episode['status'], ['revealed','archived'], true)): ?>
              <h3>La révélation</h3><p class="ktc-text"><?= nl2br(e($episode['revelation_text'] ?: 'La révélation arrive bientôt.')) ?></p>
              <?php if (empty($episode['object_hidden']) && !empty($episode['object_name'])): ?><p><strong>Objet :</strong> <?= e($episode['object_name']) ?></p><?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($photos)): ?><div class="ktc-gallery"><?php foreach ($photos as $ph): ?><figure><img src="<?= e(url($ph['file_path'])) ?>" alt="<?= e($ph['caption'] ?? '') ?>"><?php if (!empty($ph['caption'])): ?><figcaption class="ktc-text" style="font-size:.8rem;margin-top:6px"><?= e($ph['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div><?php endif; ?>

            <?php if (in_array($episode['status'], ['week1','week2'], true)): ?>
              <div class="ktc-form">
                <h3>Votre hypothèse</h3>
                <?php if ($is_logged): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="ktc_action" value="propose"><textarea name="proposition" rows="4" placeholder="À votre avis, à quoi servait cet objet ?"><?= e($user_proposition['proposition'] ?? '') ?></textarea><button class="ktc-btn" type="submit">Envoyer mon hypothèse</button></form>
                <?php else: ?><p class="ktc-text">Connectez-vous pour proposer votre hypothèse.</p><a class="ktc-btn" href="login.php">Se connecter</a><?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($episode['status'] === 'week3'): ?>
              <div class="ktc-form">
                <h3><?= e($episode['vote_question'] ?: 'Quelle hypothèse vous semble la plus crédible ?') ?></h3>
                <?php if ($is_logged): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="ktc_action" value="vote">
                    <?php if (!empty($propositions)): ?><?php foreach ($propositions as $p): ?><label style="display:block;margin:10px 0"><input type="radio" name="vote_choice" value="<?= e($p['proposition']) ?>" <?= (($user_vote['vote_choice'] ?? '') === $p['proposition'] ? 'checked' : '') ?>> <?= e($p['proposition']) ?></label><?php endforeach; ?><?php else: ?><input type="text" name="vote_choice" placeholder="Votre hypothèse préférée"><?php endif; ?>
                    <button class="ktc-btn" type="submit">Voter</button>
                  </form>
                <?php else: ?><p class="ktc-text">Connectez-vous pour voter.</p><a class="ktc-btn" href="login.php">Se connecter</a><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>

        <aside>
          <div class="ktc-card ktc-card-pad">
            <div class="ktc-side-title">Les 4 temps du KTC</div>
            <div class="ktc-timeline">
              <?php foreach (['week1'=>'Découverte','week2'=>'Indices','week3'=>'Votes','revealed'=>'Révélation'] as $k=>$v): ?><div class="ktc-step <?= $episode['status']===$k?'active':'' ?>"><?= e($v) ?></div><?php endforeach; ?>
            </div>
          </div>
          <div class="ktc-card ktc-card-pad" style="margin-top:18px">
            <div class="ktc-side-title">Propositions récentes</div>
            <?php if (empty($propositions)): ?><p class="ktc-text">Aucune hypothèse pour le moment.</p><?php else: ?><?php foreach ($propositions as $p): ?><div class="ktc-prop"><b><?= e($p['pseudo'] ?: 'Zonaute') ?></b><br><small><?= e($p['submitted_at']) ?></small><p><?= e($p['proposition']) ?></p></div><?php endforeach; ?><?php endif; ?>
          </div>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
