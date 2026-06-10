<?php
$page_title   = 'Notifications — Zone85';
$page_robots  = 'noindex,nofollow';
$current_page = 'notifications';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=notifications.php');
    exit;
}

$user = current_user();
$uid  = (int)$user['id'];

// Mark all as read on page load
mark_notifications_read($uid);

// Fetch last 50
$notifs = fetch_user_notifications($uid, 50);

$page_styles = '<style>
.notif-page{min-height:60vh;background:var(--beige);padding-top:100px;padding-bottom:72px}
.notif-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.notif-title{font-size:1.6rem;font-weight:900;color:var(--navy-dark);letter-spacing:-.03em}
.notif-list{display:flex;flex-direction:column;gap:10px}
.notif-item{background:#fff;border-radius:var(--radius);border:1.5px solid var(--beige-dark);padding:16px 20px;display:flex;align-items:flex-start;gap:14px;transition:box-shadow .15s}
.notif-item:hover{box-shadow:var(--shadow-sm)}
.notif-item.unread{border-left:3px solid var(--primary);background:#fffdf9}
.notif-icon{font-size:1.6rem;flex-shrink:0;line-height:1;margin-top:2px}
.notif-body{flex:1;min-width:0}
.notif-item-title{font-size:.92rem;font-weight:700;color:var(--navy-dark);margin-bottom:3px}
.notif-item-body{font-size:.83rem;color:var(--text-muted);line-height:1.5;margin-bottom:4px}
.notif-meta{font-size:.72rem;color:var(--text-muted)}
.notif-link{font-size:.78rem;font-weight:700;color:var(--primary);text-decoration:none}
.notif-link:hover{text-decoration:underline}
.notif-empty{text-align:center;padding:56px 24px;background:#fff;border-radius:var(--radius-lg);border:1.5px solid var(--beige-dark)}
.notif-empty-icon{font-size:3rem;margin-bottom:12px}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';

$type_icons = [
    'badge_unlock'     => 'badge',
    'mission_validated'=> 'check-circle',
    'mission_new'      => 'missions',
    'flash_start'      => 'xp',
    'level_up'         => 'level-up',
    'clan_event'       => 'clans',
    'season_end'       => 'trophy',
    'system'           => 'announce',
];
$type_colors = [
    'badge_unlock'     => 'z85-icon--warning',
    'mission_validated'=> 'z85-icon--success',
    'mission_new'      => 'z85-icon--coral',
    'flash_start'      => 'z85-icon--warning',
    'level_up'         => 'z85-icon--coral',
    'clan_event'       => 'z85-icon--navy',
    'season_end'       => 'z85-icon--warning',
    'system'           => 'z85-icon--navy',
];
?>

<div class="notif-page">
  <div class="container" style="max-width:680px">

    <div class="notif-header">
      <h1 class="notif-title"><?= zone85_icon('notifications', 'z85-icon--md z85-icon--coral') ?> Notifications</h1>
      <span style="font-size:.82rem;color:var(--text-muted)"><?= count($notifs) ?> notification<?= count($notifs) > 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($notifs)): ?>
    <div class="notif-empty">
      <div class="notif-empty-icon"><?= zone85_icon('notifications', 'z85-icon--xl z85-icon--muted') ?></div>
      <p style="font-weight:700;color:var(--navy-dark);margin-bottom:8px">Aucune notification pour l'instant.</p>
      <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:20px">Tu seras averti ici lors de tes validations, badges débloqués et événements de la Zone.</p>
      <a href="missions.php" style="display:inline-block;background:var(--primary);color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.88rem">Voir les missions →</a>
    </div>
    <?php else: ?>
    <div class="notif-list">
      <?php foreach ($notifs as $n):
        $icon_name  = $type_icons[$n['type']] ?? 'announce';
        $icon_color = $type_colors[$n['type']] ?? 'z85-icon--navy';
        $was_unread = ($n['read_at'] === null);
      ?>
      <div class="notif-item">
        <div class="notif-icon"><?= zone85_icon($icon_name, 'z85-icon--md ' . $icon_color) ?></div>
        <div class="notif-body">
          <div class="notif-item-title"><?= e($n['title']) ?></div>
          <?php if ($n['body']): ?>
            <div class="notif-item-body"><?= e($n['body']) ?></div>
          <?php endif; ?>
          <div class="notif-meta">
            <?= format_date($n['created_at'], 'long') ?>
            <?php if ($n['link_url']): ?>
              · <a href="<?= e($n['link_url']) ?>" class="notif-link">Voir →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
