<?php
// components/contribution-card.php
// Carte d'une contribution photo (Hall des contributions)
// Variables attendues : $photo (array — champs hall_photos)
?>
<div class="photo-card">

    <div class="photo-area" style="background:<?= e($photo['gradient']) ?>">
        <div class="photo-overlay">
            <span class="photo-overlay-author"><?= e($photo['author']) ?></span>
            <span class="photo-overlay-likes">❤️ <?= (int)$photo['likes'] ?></span>
        </div>
    </div>

    <div class="photo-card-body">
        <div class="photo-card-top">
            <h4 class="photo-title"><?= e($photo['title']) ?></h4>
            <span class="photo-category"><?= e($photo['category']) ?></span>
        </div>
        <div class="photo-card-meta">
            <span class="photo-author">Par <?= e($photo['author']) ?></span>
            <span class="photo-likes">❤️ <?= (int)$photo['likes'] ?></span>
        </div>
    </div>

</div>
