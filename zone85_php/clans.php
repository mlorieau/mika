<?php
$page_title       = 'Les Clans';
$page_description = 'Bocage, Littoral, Marais — trois clans vendéens s\'affrontent chaque saison dans la Bataille des Clans. Découvre leur identité et rejoins le tien.';
$page_canonical   = 'https://www.zone85.fr/clans.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Les Clans','item'=>'https://www.zone85.fr/clans.php'],
    ],
];
$current_page = 'clans';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
$page_styles = '<style>/* No page-specific CSS for clans */</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Sort clans by podium_rank for the podium display
$podium_clans = $clans;
usort($podium_clans, fn($a, $b) => $a['podium_rank'] <=> $b['podium_rank']);

// Sort clans by season_score descending for the race
$race_clans = $clans;
usort($race_clans, fn($a, $b) => $b['season_score'] <=> $a['season_score']);

// Badge map for race ranks
$race_badges = ['gold-badge' => '🥇', 'silver-badge' => '🥈', 'bronze-badge' => '🥉'];
$rank_badge_classes = [1 => 'gold-badge', 2 => 'silver-badge', 3 => 'bronze-badge'];

// Podium display order: 2nd, 1st, 3rd
$podium_order = [];
foreach ($podium_clans as $c) {
    $podium_order[$c['podium_rank']] = $c;
}
$podium_display = [
    $podium_order[2] ?? null,
    $podium_order[1] ?? null,
    $podium_order[3] ?? null,
];
?>

<!-- ===================== HERO PODIUM ===================== -->
<section class="clans-hero">
  <div class="clans-hero-inner">
    <div class="clans-hero-text">
      <p class="overline-label">Saison en cours · <?= e($active_season['title']) ?></p>
      <h1>La Bataille des Clans</h1>
      <p class="hero-sub">Trois clans. Un seul vainqueur de saison.<br>Le clan gagne la saison. Le joueur construit sa légende.</p>
      <a href="missions.php" class="btn btn-primary btn-lg">Participer à la mission</a>
    </div>

    <div class="podium-wrap">
      <?php foreach ($podium_display as $clan): if (!$clan) continue; ?>
      <?php
        $is_first = $clan['podium_rank'] === 1;
        $bar_class = 'p' . $clan['podium_rank'] . '-bar';
        $label_class = $clan['slug'] . '-label';
      ?>
      <!-- PODIUM <?= $clan['podium_rank'] ?> - <?= e($clan['name']) ?> -->
      <div class="podium-col" id="<?= e($clan['podium_id']) ?>">
        <?php if ($is_first): ?><div class="pod-crown">👑</div><?php endif; ?>
        <div class="pod-clan-label <?= e($label_class) ?>"><?= e(ucfirst($clan['slug'])) ?></div>
        <div class="pod-masc">
          <img src="<?= img($clan['mascot']) ?>" alt="Mascotte <?= e(ucfirst($clan['slug'])) ?>">
        </div>
        <div class="pod-bar <?= e($bar_class) ?>">
          <span class="pod-rank"><?= $clan['podium_rank'] ?></span>
        </div>
        <div class="pod-score"><?= format_score($clan['season_score']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== COURSE EN DIRECT ===================== -->
<section class="course-section reveal">
  <div class="container">
    <div class="section-header">
      <p class="overline-label">Score de saison · mis à jour en continu</p>
      <h2>Course en direct</h2>
    </div>

    <div class="race-track">
      <?php
        $race_rank = 1;
        foreach ($race_clans as $clan):
          $badge_class = $rank_badge_classes[$race_rank] ?? '';
          $badge_emoji = $race_badges[$badge_class] ?? '';
      ?>
      <div class="race-row">
        <div class="race-meta">
          <span class="race-clan-name <?= e($clan['text_class']) ?>"><?= e($clan['label']) ?></span>
          <span class="race-pts"><?= format_score($clan['season_score']) ?></span>
        </div>
        <div class="race-bar-bg">
          <div class="race-fill race-fill-<?= e($clan['slug']) ?>" data-width="<?= (int)$clan['race_width'] ?>"></div>
        </div>
        <span class="race-rank <?= e($badge_class) ?>"><?= $badge_emoji ?></span>
      </div>
      <?php $race_rank++; endforeach; ?>
    </div>

    <!-- Countdown -->
    <div class="course-footer">
      <p class="course-mission-label">Mission collective · <strong><?= e($active_season['main_mission']) ?></strong></p>
      <div class="countdown-inline">
        <div class="cd-unit">
          <span class="countdown-days cd-val">—</span>
          <span class="cd-lbl">jours</span>
        </div>
        <div class="cd-sep">:</div>
        <div class="cd-unit">
          <span class="countdown-hours cd-val">—</span>
          <span class="cd-lbl">heures</span>
        </div>
        <div class="cd-sep">:</div>
        <div class="cd-unit">
          <span class="countdown-mins cd-val">—</span>
          <span class="cd-lbl">min</span>
        </div>
      </div>
      <p class="course-end-label">Fin de saison : 31 août</p>
    </div>
  </div>
</section>

<!-- ===================== FICHES CLAN ===================== -->
<?php
// Define display order and layout variants for clan sheets
$clan_sheet_order = ['bocage', 'littoral', 'marais'];
$clan_reverse = ['littoral' => true];

foreach ($clan_sheet_order as $slug):
  $clan = $clans[$slug] ?? null;
  if (!$clan) continue;
  $is_reverse = !empty($clan_reverse[$slug]);
  $sheet_class = 'clan-sheet clan-sheet-' . $slug . ' reveal';
  $section_id = 'clan-' . $slug;
  $inner_class = 'clan-sheet-inner' . ($is_reverse ? ' reverse' : '');
?>

<!-- ===================== FICHE CLAN — <?= strtoupper($slug) ?> ===================== -->
<section class="<?= e($sheet_class) ?>" id="<?= e($section_id) ?>">
  <div class="container">
    <div class="<?= e($inner_class) ?>">
      <?php if ($is_reverse): ?>
      <div class="clan-masc-wrap">
        <img src="<?= img($clan['mascot']) ?>" alt="Mascotte <?= e(ucfirst($slug)) ?>" class="clan-masc-img">
        <div class="clan-quote <?= e($slug) ?>-quote"><?= e($clan['description']) ?></div>
      </div>
      <?php endif; ?>

      <div class="clan-sheet-info">
        <div class="clan-chip <?= e($clan['chip_class']) ?>"><?= e($clan['label']) ?></div>
        <h2 class="clan-sheet-title"><?= e($clan['hero_name']) ?></h2>
        <p class="clan-sheet-desc"><?= e($clan['description']) ?></p>
        <div class="clan-stats-row">
          <div class="clan-stat-box">
            <span class="clan-stat-val"><?= number_format($clan['season_score'], 0, ',', ' ') ?></span>
            <span class="clan-stat-lbl">Pts saison</span>
          </div>
          <div class="clan-stat-box">
            <span class="clan-stat-val"><?= (int)$clan['members_count'] ?></span>
            <span class="clan-stat-lbl">Membres</span>
          </div>
          <div class="clan-stat-box">
            <span class="clan-stat-val"><?= (int)$clan['trophies'] ?></span>
            <span class="clan-stat-lbl">Trophées</span>
          </div>
        </div>
        <div class="clan-top-members">
          <p class="top-members-title">Top membres · Saison en cours</p>
          <ol class="top-members-list">
            <?php foreach ($clan['top_members'] as $i => $member): ?>
            <li>
              <span class="tm-rank"><?= $i + 1 ?></span>
              <span class="tm-name"><?= e($member['pseudo']) ?></span>
              <span class="tm-xp"><?= format_xp($member['xp_season']) ?></span>
            </li>
            <?php endforeach; ?>
          </ol>
        </div>
        <a href="inscription.php" class="btn btn-primary">Rejoindre <?= e($clan['name']) ?></a>
      </div>

      <?php if (!$is_reverse): ?>
      <div class="clan-masc-wrap">
        <img src="<?= img($clan['mascot']) ?>" alt="Mascotte <?= e(ucfirst($slug)) ?>" class="clan-masc-img">
        <div class="clan-quote <?= e($slug) ?>-quote"><?= e($clan['description']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php endforeach; ?>

<!-- ===================== BIBLIOTHÈQUE DES TROPHÉES ===================== -->
<section class="trophees-section reveal">
  <div class="container">
    <div class="section-header">
      <p class="overline-label">Palmarès des saisons passées</p>
      <h2>Bibliothèque des trophées</h2>
      <p class="section-sub">Chaque saison couronnée entre dans l'histoire. Ces victoires sont définitives.</p>
    </div>

    <div class="trophy-grid">
      <?php foreach ($season_trophies as $trophy): ?>
      <div class="trophy-card reveal">
        <div class="trophy-header <?= e($trophy['winner_clan'] ?? '') ?>">
          <span class="trophy-season"><?= e($trophy['season']) ?></span>
          <span class="trophy-winner-badge"><?= e($trophy['medal']) ?> Vainqueur</span>
        </div>
        <div class="trophy-body">
          <div class="trophy-clan <?= e($trophy['winner_clan'] ?? '') ?>-chip-sm"><?= e($trophy['winner_name']) ?></div>
          <p class="trophy-desc"><?= e($trophy['description'] ?? '') ?></p>
          <div class="trophy-scores">
            <?php if (!empty($trophy['scores'])): ?>
            <?php foreach ($trophy['scores'] as $score_row): ?>
            <div class="tsc-row">
              <span class="tsc-clan"><?= e($score_row['clan']) ?></span>
              <span class="tsc-pts<?= !empty($score_row['gold']) ? ' gold' : '' ?>"><?= e($score_row['pts']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="cta-bloc reveal">
  <div class="cta-inner">
    <div>
      <h2>Choisissez votre camp</h2>
      <p>Bocage, Littoral ou Marais — quel territoire vous ressemble ?</p>
    </div>
    <div class="cta-actions">
      <a href="inscription.php" class="btn btn-white">Choisir mon clan</a>
      <a href="classement.php" class="btn btn-outline-white">Voir le classement</a>
    </div>
  </div>
</section>

<?php
$page_scripts = '<script>/* No page-specific JS for clans */</script>';
require_once 'includes/footer.php';
?>
