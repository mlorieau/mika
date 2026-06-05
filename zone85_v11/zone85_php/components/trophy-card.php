<?php
// components/trophy-card.php
// Carte d'un trophée de saison (hall of fame / archives)
// Variables attendues : $trophy (array — champs season_trophies)

// Résoudre la chip_class du clan gagnant
$clan_chip_map = [
    'bocage'   => 'bocage-chip',
    'littoral' => 'littoral-chip',
    'marais'   => 'marais-chip',
];
$clan_label_map = [
    'bocage'   => '🌳 Bocage',
    'littoral' => '⚓ Littoral',
    'marais'   => '🌿 Marais',
];
$chip_class  = $clan_chip_map[$trophy['winner_clan']]  ?? 'littoral-chip';
$clan_label  = $clan_label_map[$trophy['winner_clan']] ?? e($trophy['winner_name']);
?>
<div class="trophy-card">

    <div class="trophy-card-header">
        <span class="trophy-medal"><?= e($trophy['medal']) ?></span>
        <div>
            <div class="trophy-season"><?= e($trophy['season']) ?></div>
            <span class="archived-note">Archivé</span>
        </div>
    </div>

    <div class="trophy-winner">
        <span class="<?= e($chip_class) ?>"><?= e($clan_label) ?></span>
        <span class="trophy-winner-name"><?= e($trophy['winner_name']) ?></span>
    </div>

    <div class="trophy-stats">
        <span class="trophy-contributions">
            <?= (int)$trophy['contributions'] ?> contributions
        </span>
    </div>

    <?php if (!empty($trophy['main_mission'])): ?>
    <div class="trophy-mission">
        <span class="trophy-mission-label">Mission phare</span>
        <span class="trophy-mission-title"><?= e($trophy['main_mission']) ?></span>
    </div>
    <?php endif; ?>

</div>
