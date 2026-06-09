<?php
// components/score-card.php
// Ligne de classement des clans (leaderboard / bataille)
// Variables attendues : $clan (array), $rank (int), $medal (string emoji)
?>
<div class="score-card">

    <div class="score-card-header">
        <div class="score-medal-rank">
            <span class="score-medal"><?= e($medal) ?></span>
            <span class="score-rank">#<?= (int)$rank ?></span>
        </div>

        <div class="score-clan-info">
            <span class="score-clan-name"><?= e($clan['name']) ?></span>
            <span class="<?= e($clan['chip_class']) ?>"><?= e($clan['label']) ?></span>
        </div>

        <div class="score-clan-right">
            <div class="score-pts"><?= e(format_score($clan['season_score'])) ?></div>
            <div class="score-meta">Score annuel</div>
            <div class="score-members"><?= (int)$clan['members_count'] ?> membres</div>
        </div>
    </div>

    <div class="score-race-track">
        <div class="score-race-fill <?= e($clan['slug']) ?>-fill"
             style="width:<?= (int)$clan['race_width'] ?>%"></div>
    </div>

</div>
