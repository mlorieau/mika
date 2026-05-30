<?php
// components/mission-card.php
// Carte d'une mission (liste des missions, hall, etc.)
// Variables attendues : $mission (array — champs mission complets)

$status_labels = [
    'active'   => ['label' => 'En cours',  'class' => 'status-active'],
    'upcoming' => ['label' => 'À venir',   'class' => 'status-upcoming'],
    'ended'    => ['label' => 'Terminé',   'class' => 'status-ended'],
];
$status_info = $status_labels[$mission['status']] ?? ['label' => 'En cours', 'class' => 'status-active'];
?>
<div class="mission-card reveal" data-type="<?= e($mission['mission_type']) ?>">

    <div class="mission-card-header">
        <span class="mission-type-badge">
            <?= mission_type_icon($mission['mission_type']) ?>
            <?= e(mission_type_label($mission['mission_type'])) ?>
        </span>

        <?php if (!empty($mission['is_collective'])): ?>
        <span class="mission-collective-badge">🛡️ Collectif</span>
        <?php endif; ?>

        <span class="mission-status <?= e($status_info['class']) ?>">
            <?= e($status_info['label']) ?>
        </span>
    </div>

    <h4 class="mission-card-title"><?= e($mission['title']) ?></h4>

    <p class="mission-card-desc"><?= e($mission['description']) ?></p>

    <div class="mission-card-footer">
        <div class="mission-rewards">
            <span class="mission-xp">
                +<?= e(format_xp($mission['xp_participation'])) ?>
            </span>

            <?php if (!empty($mission['clan_points_participation']) && $mission['clan_points_participation'] > 0): ?>
            <span class="mission-clan-pts">
                + <?= (int)$mission['clan_points_participation'] ?> pts clan
            </span>
            <?php endif; ?>
        </div>

        <a href="missions.php" class="btn btn-outline btn-sm">
            Voir la mission →
        </a>
    </div>

</div>
