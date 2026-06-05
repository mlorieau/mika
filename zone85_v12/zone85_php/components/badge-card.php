<?php
// components/badge-card.php
// Carte d'un badge (vitrine des badges, profil utilisateur)
// Variables attendues : $badge (array — champs badge complets)

$rarity_labels = [
    'common'    => 'Commun',
    'uncommon'  => 'Peu commun',
    'rare'      => 'Rare',
    'epic'      => 'Épique',
    'legendary' => 'Légendaire',
];
$rarity_label = $rarity_labels[$badge['rarity']] ?? ucfirst($badge['rarity']);
$card_state    = !empty($badge['obtained']) ? 'obtained' : 'locked';
?>
<div class="badge-card <?= e($card_state) ?>">

    <div class="badge-icon"><?= e($badge['icon']) ?></div>

    <h4 class="badge-title"><?= e($badge['title']) ?></h4>

    <span class="badge-rarity badge-rarity--<?= e($badge['rarity']) ?>">
        <?= e($rarity_label) ?>
    </span>

    <p class="badge-desc"><?= e($badge['description']) ?></p>

    <?php if (empty($badge['obtained']) && isset($badge['progress'])): ?>
    <div class="badge-progress-wrap">
        <div class="badge-progress" style="width:<?= (int)$badge['progress'] ?>%"></div>
    </div>
    <div class="badge-progress-label"><?= (int)$badge['progress'] ?>%</div>
    <?php endif; ?>

</div>
