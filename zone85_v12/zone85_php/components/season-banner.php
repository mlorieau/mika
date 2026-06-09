<?php
// components/season-banner.php
// Bandeau compact de la saison active (sidebar, accueil)
// Variables attendues : $season (array — saison active depuis $seasons)
?>
<div class="season-banner">

    <div class="season-banner-eyebrow">En ce moment dans le QG</div>

    <h3 class="season-banner-title"><?= e($season['title']) ?></h3>

    <?php if (!empty($season['main_mission'])): ?>
    <div class="season-banner-mission">
        <span class="season-mission-label">Mission phare</span>
        <span class="season-mission-name"><?= e($season['main_mission']) ?></span>
    </div>
    <?php endif; ?>

    <div class="season-countdown">
        <div class="countdown-block">
            <span class="countdown-days">--</span>
            <span class="countdown-unit">j</span>
        </div>
        <div class="countdown-sep">:</div>
        <div class="countdown-block">
            <span class="countdown-hours">--</span>
            <span class="countdown-unit">h</span>
        </div>
        <div class="countdown-sep">:</div>
        <div class="countdown-block">
            <span class="countdown-mins">--</span>
            <span class="countdown-unit">min</span>
        </div>
    </div>

    <?php if (!empty($season['end_date'])): ?>
    <div class="season-end-date" data-end="<?= e($season['end_date']) ?>">
        Jusqu'au <?= e(date('j M Y', strtotime($season['end_date']))) ?>
    </div>
    <?php endif; ?>

    <a href="missions.php" class="btn btn-primary btn-sm season-banner-cta">
        Participer
    </a>

</div>
