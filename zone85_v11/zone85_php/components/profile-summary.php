<?php
// components/profile-summary.php
// Carte de profil condensée (sidebar utilisateur connecté)
// Variables attendues : $user (array — mock_user), $clan (array — clan du joueur)

// Seuils de niveau (XP à vie)
$xp_thresholds = [0, 500, 1500, 3000, 5000, 8000, 12000, 17000, 23000, 30000];
$level         = $user['level'];
$xp_current    = $xp_thresholds[$level - 1] ?? 0;
$xp_next       = $xp_thresholds[$level]     ?? 30000;
$xp_in_level   = $user['xp_total'] - $xp_current;
$xp_needed     = max($xp_next - $xp_current, 1);
$xp_pct        = min(100, (int)round($xp_in_level / $xp_needed * 100));
?>
<div class="profile-summary">

    <div class="profile-summary-top">
        <div class="nav-avatar profile-avatar">
            <?= e($user['avatar']) ?>
        </div>
        <div class="profile-summary-id">
            <div class="profile-pseudo"><?= e($user['pseudo']) ?></div>
            <div class="profile-level">Niv.<?= (int)$level ?></div>
        </div>
    </div>

    <div class="profile-summary-clan">
        <span class="<?= e($clan['chip_class']) ?>"><?= e($clan['label']) ?></span>
    </div>

    <div class="profile-xp-bar-wrap">
        <div class="profile-xp-bar-track">
            <div class="profile-xp-bar-fill" style="width:<?= $xp_pct ?>%"></div>
        </div>
        <div class="profile-xp-bar-label">
            <?= e(format_xp($user['xp_total'])) ?> / <?= e(format_xp($xp_next)) ?>
        </div>
    </div>

    <div class="profile-summary-stats">
        <div class="profile-stat">
            <strong><?= e(format_xp($user['xp_total'])) ?></strong>
            <span>XP à vie</span>
        </div>
        <div class="profile-stat">
            <strong><?= e(format_xp($user['xp_this_season'])) ?></strong>
            <span>Saison</span>
        </div>
        <div class="profile-stat">
            <strong>#<?= (int)$user['rank_in_clan'] ?></strong>
            <span>Rang</span>
        </div>
    </div>

    <a href="profil.php" class="btn btn-outline btn-sm profile-summary-link">
        Mon profil →
    </a>

</div>
