<?php
// components/clan-card.php
// Carte d'un clan pour la page d'accueil ou la liste des clans
// Variables attendues : $clan (array — champs clan complets)
?>
<div class="clan-card reveal" data-clan="<?= e($clan['slug']) ?>">

    <div class="clan-masc-wrap">
        <img src="<?= e(img($clan['mascot'])) ?>"
             alt="Mascotte <?= e($clan['name']) ?>"
             width="150"
             height="150"
             loading="lazy">
    </div>

    <span class="<?= e($clan['chip_class']) ?>"><?= e($clan['label']) ?></span>

    <h3><?= e($clan['name']) ?></h3>

    <div class="clan-stats-mini">
        <div class="clan-stat-mini">
            <strong><?= e(format_score($clan['season_score'])) ?></strong>
            <span>Score de saison</span>
        </div>
        <div class="clan-stat-mini">
            <strong><?= e((string)$clan['members_count']) ?></strong>
            <span>Membres</span>
        </div>
        <div class="clan-stat-mini">
            <strong><?= e((string)$clan['trophies']) ?></strong>
            <span>Trophées</span>
        </div>
    </div>

    <?php if (!empty($clan['top_members'])): ?>
    <div class="clan-top-mini">
        <?php foreach (array_slice($clan['top_members'], 0, 3) as $i => $member): ?>
        <div class="clan-top-mini-row">
            <span class="clan-top-mini-rank"><?= $i + 1 ?></span>
            <span class="clan-top-mini-pseudo"><?= e($member['pseudo']) ?></span>
            <span class="clan-top-mini-xp"><?= e(format_xp($member['xp_total'])) ?> à vie</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <a href="inscription.php" class="btn btn-primary btn-sm">
        Rejoindre ce clan →
    </a>

</div>
