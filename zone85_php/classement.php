<?php
$page_title = 'Classement — Zone85';
$current_page = 'classement';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
$page_styles = '<style>
/* HERO */
.classement-hero{background:linear-gradient(160deg,#0d1e2c 0%,#12314e 100%);padding:100px 0 56px;position:relative;overflow:hidden}
.classement-hero::before{content:\'\';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.02\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");pointer-events:none}
.classement-hero-inner{position:relative;z-index:1;display:flex;align-items:flex-end;justify-content:space-between;gap:32px;flex-wrap:wrap}
.classement-hero h1{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;color:#fff;letter-spacing:-1px;line-height:1.1;margin-bottom:8px}
.classement-hero p{font-size:.92rem;color:rgba(255,255,255,.5);max-width:440px}

/* TABS */
.classement-tabs-wrap{background:var(--navy-dark);border-bottom:1px solid rgba(255,255,255,.08);position:sticky;top:68px;z-index:100}
.classement-tabs{display:flex;gap:0;max-width:1200px;margin:0 auto;padding:0 24px;overflow-x:auto}
.classement-tab{padding:18px 28px;font-size:.88rem;font-weight:700;color:rgba(255,255,255,.45);cursor:pointer;border:none;background:none;font-family:\'Inter\',sans-serif;white-space:nowrap;border-bottom:3px solid transparent;transition:all .2s;position:relative;top:1px}
.classement-tab:hover{color:rgba(255,255,255,.8)}
.classement-tab.active{color:#fff;border-bottom-color:var(--primary)}

/* TAB PANELS */
.tab-panels{background:var(--navy-dark);min-height:600px;padding:56px 0 96px}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* CLAN SCORES PANEL */
.clans-score-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:48px}
.clan-score-card{border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-lg)}
.csc-header{padding:28px 28px 22px;display:flex;align-items:center;gap:16px}
.csc-header-bocage{background:linear-gradient(135deg,#0d2018,#1e3d2b)}
.csc-header-littoral{background:linear-gradient(135deg,#0a1a2e,#163756)}
.csc-header-marais{background:linear-gradient(135deg,#2b1a0a,#4a2e15)}
.csc-masc{width:60px;height:60px;border-radius:10px;overflow:hidden;background:rgba(255,255,255,.06);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.8rem}
.csc-info{flex:1}
.csc-clan-name{font-size:1rem;font-weight:900;color:#fff;margin-bottom:2px}
.csc-rank-line{font-size:.72rem;font-weight:700;color:rgba(255,255,255,.45);letter-spacing:.06em}
.csc-pts{font-size:1.5rem;font-weight:900;color:#fff;text-align:right;white-space:nowrap}
.csc-pts small{font-size:.65rem;color:rgba(255,255,255,.4);font-weight:600;display:block;margin-top:1px}
.csc-body{background:rgba(255,255,255,.03);padding:22px 28px;border-top:1px solid rgba(255,255,255,.06)}
.csc-bar-bg{background:rgba(255,255,255,.08);border-radius:6px;height:10px;overflow:hidden;margin-bottom:14px}
.csc-bar-fill{height:100%;border-radius:6px;width:0;transition:width 1.4s cubic-bezier(.22,1,.36,1)}
.csc-stats-row{display:flex;justify-content:space-between}
.csc-stat{text-align:center}
.csc-stat-val{display:block;font-size:.95rem;font-weight:800;color:rgba(255,255,255,.85)}
.csc-stat-lbl{display:block;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.3);margin-top:2px}

/* FEATURED WINNER */
.leading-clan-banner{background:rgba(234,86,73,.1);border:1px solid rgba(234,86,73,.25);border-radius:var(--radius-lg);padding:20px 28px;margin-bottom:32px;display:flex;align-items:center;gap:16px}
.lcb-icon{font-size:1.8rem}
.lcb-text{font-size:.92rem;color:rgba(255,255,255,.7);line-height:1.5}
.lcb-text strong{color:#fff}

/* TOP ZONAUTES TABLE */
.rank-section-title{font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.07)}
.podium-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:36px}
.podium-zonaute{border-radius:var(--radius-lg);overflow:hidden;text-align:center}
.pz-header{padding:24px 20px 18px;position:relative}
.pz-header-1{background:linear-gradient(135deg,#7a5c10,#c9962a)}
.pz-header-2{background:linear-gradient(135deg,#3a3a4a,#6a6a7a)}
.pz-header-3{background:linear-gradient(135deg,#5a3010,#8a5030)}
.pz-rank-icon{font-size:1.8rem;display:block;margin-bottom:8px}
.pz-name{font-size:1rem;font-weight:900;color:#fff;margin-bottom:4px}
.pz-xp{font-size:1.3rem;font-weight:900;color:rgba(255,255,255,.85)}
.pz-xp small{font-size:.6rem;font-weight:600;color:rgba(255,255,255,.5)}
.pz-body{background:rgba(255,255,255,.04);padding:14px 20px;border-top:1px solid rgba(255,255,255,.06)}
.pz-clan-tag{font-size:.72rem;font-weight:700}

/* PAR CLAN PANEL */
.par-clan-tabs{display:flex;gap:8px;margin-bottom:28px}
.par-clan-tab{padding:8px 20px;border-radius:var(--radius-sm);font-size:.82rem;font-weight:700;cursor:pointer;border:1px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.55);font-family:\'Inter\',sans-serif;transition:all .2s}
.par-clan-tab:hover{border-color:rgba(255,255,255,.4);color:#fff}
.par-clan-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}

/* ARCHIVES */
.archives-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.archive-card{border-radius:var(--radius-lg);overflow:hidden;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}
.archive-header{padding:24px 22px 18px;display:flex;flex-direction:column;gap:6px}
.archive-header-bocage{background:linear-gradient(135deg,#0d2018,#1e3d2b)}
.archive-header-littoral{background:linear-gradient(135deg,#0a1a2e,#163756)}
.archive-header-marais{background:linear-gradient(135deg,#2b1a0a,#4a2e15)}
.archive-season{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4)}
.archive-winner-name{font-size:1.1rem;font-weight:900;color:#fff}
.archive-trophy-icon{font-size:1.8rem;margin-bottom:2px}
.archive-body{padding:18px 22px}
.archive-scores{display:flex;flex-direction:column;gap:7px}
.archive-score-row{display:flex;justify-content:space-between;font-size:.82rem;color:rgba(255,255,255,.6)}
.archive-score-row strong{color:#fff;font-weight:800}
.archive-score-row.winner{color:rgba(255,255,255,.85)}
.archive-score-row.winner strong{color:var(--gold)}

@media(max-width:1024px){
  .clans-score-grid{grid-template-columns:1fr}
  .archives-grid{grid-template-columns:repeat(2,1fr)}
  .podium-row{grid-template-columns:1fr}
  .classement-tab{padding:16px 18px;font-size:.82rem}
}
@media(max-width:768px){
  .archives-grid{grid-template-columns:1fr}
  .clans-score-grid{grid-template-columns:1fr}
}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="classement-hero">
  <div class="container classement-hero-inner">
    <div>
      <p class="overline-label">Camp d'Été Zone85 · <?= e($active_season['title']) ?> en cours</p>
      <h1>Classement</h1>
      <p>Où en sont les clans ? Qui mène la danse ? Découvre ta position dans l'histoire de la Zone.</p>
    </div>
    <div class="live-badge">⚡ En direct</div>
  </div>
</section>

<!-- ===================== TABS ===================== -->
<div class="classement-tabs-wrap">
  <div class="classement-tabs">
    <button class="classement-tab active"
      data-tab-group="classement" data-tab-id="clans"
      onclick="switchTab('classement','clans')">🛡️ Bataille des Clans</button>
    <button class="classement-tab"
      data-tab-group="classement" data-tab-id="zonautes"
      onclick="switchTab('classement','zonautes')">⚡ Top Zonautes</button>
    <button class="classement-tab"
      data-tab-group="classement" data-tab-id="par-clan"
      onclick="switchTab('classement','par-clan')">👥 Par clan</button>
    <button class="classement-tab"
      data-tab-group="classement" data-tab-id="archives"
      onclick="switchTab('classement','archives')">🏆 Archives</button>
  </div>
</div>

<!-- ===================== PANELS ===================== -->
<div class="tab-panels">
  <div class="container">

    <!-- PANEL 1 — BATAILLE DES CLANS -->
    <div class="tab-panel active"
      data-panel-group="classement" data-panel-id="clans">

      <?php
        // Determine leading clan (highest season_score)
        $sorted_clans = $clans;
        usort($sorted_clans, function($a, $b) { return $b['season_score'] - $a['season_score']; });
        $leading_clan = $sorted_clans[0];
      ?>
      <div class="leading-clan-banner">
        <span class="lcb-icon">👑</span>
        <p class="lcb-text"><strong>Le Clan <?= e($leading_clan['name']) ?> mène la Bataille des Clans.</strong> Il lui reste <span class="countdown-days">—</span> jours pour consolider son avance. Rejoins ton clan pour changer le cours de la saison.</p>
      </div>

      <!-- Race bars -->
      <div style="background:rgba(255,255,255,.03);border-radius:var(--radius-lg);padding:32px;margin-bottom:32px;border:1px solid rgba(255,255,255,.07)">
        <p class="rank-section-title">Score de saison · Camp d'Été Zone85</p>
        <div style="display:flex;flex-direction:column;gap:20px">
          <?php
            $rank_medals = ['🥇', '🥈', '🥉'];
            $rank_badge_classes = ['gold-badge', 'silver-badge', 'bronze-badge'];
            foreach ($sorted_clans as $rank_idx => $clan):
          ?>
          <div class="race-row">
            <div class="race-meta">
              <span class="race-clan-name" style="color:rgba(255,255,255,.9)"><?= e($clan['mascot']) ?> <?= e($clan['name']) ?></span>
              <span class="race-pts" style="color:rgba(255,255,255,.45)"><?= format_score($clan['season_score']) ?> pts</span>
            </div>
            <div class="race-bar-bg">
              <div class="race-fill race-fill-<?= e($clan['slug']) ?>" data-width="<?= e($clan['race_width']) ?>"></div>
            </div>
            <span class="<?= e($rank_badge_classes[$rank_idx]) ?>"><?= e($rank_medals[$rank_idx]) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Clan score cards -->
      <div class="clans-score-grid">
        <?php
          $rank_labels = ['🥇 1ER · EN TÊTE', '🥈 2E · À ' . format_score($sorted_clans[0]['season_score'] - $sorted_clans[1]['season_score']) . ' PTS', '🥉 3E · À ' . format_score($sorted_clans[0]['season_score'] - $sorted_clans[2]['season_score']) . ' PTS'];
          $rank_gap_labels = ['Avance', 'Retard', 'Retard'];
          $rank_gap_values = [
            '+' . format_score($sorted_clans[0]['season_score'] - $sorted_clans[1]['season_score']),
            '-' . format_score($sorted_clans[0]['season_score'] - $sorted_clans[1]['season_score']),
            '-' . format_score($sorted_clans[0]['season_score'] - $sorted_clans[2]['season_score']),
          ];
          foreach ($sorted_clans as $rank_idx => $clan):
        ?>
        <div class="clan-score-card">
          <div class="csc-header csc-header-<?= e($clan['slug']) ?>">
            <div class="csc-masc"><?= e($clan['mascot']) ?></div>
            <div class="csc-info">
              <div class="csc-clan-name">Clan <?= e($clan['name']) ?></div>
              <div class="csc-rank-line"><?= e($rank_labels[$rank_idx]) ?></div>
            </div>
            <div class="csc-pts"><?= format_score($clan['season_score']) ?><small>pts saison</small></div>
          </div>
          <div class="csc-body">
            <div class="csc-bar-bg"><div class="csc-bar-fill race-fill-<?= e($clan['slug']) ?>" data-width="<?= e($clan['race_width']) ?>"></div></div>
            <div class="csc-stats-row">
              <div class="csc-stat"><span class="csc-stat-val"><?= e($clan['members_count']) ?></span><span class="csc-stat-lbl">Membres</span></div>
              <div class="csc-stat"><span class="csc-stat-val"><?= e($clan['trophies']) ?></span><span class="csc-stat-lbl">Trophées</span></div>
              <div class="csc-stat"><span class="csc-stat-val"><?= e($rank_gap_values[$rank_idx]) ?></span><span class="csc-stat-lbl"><?= e($rank_gap_labels[$rank_idx]) ?></span></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- PANEL 2 — TOP ZONAUTES -->
    <div class="tab-panel"
      data-panel-group="classement" data-panel-id="zonautes">

      <p class="rank-section-title">XP à vie · Classement personnel · Toutes saisons confondues</p>

      <!-- Podium top 3 -->
      <?php
        $podium_headers = ['pz-header-2', 'pz-header-1', 'pz-header-3'];
        $podium_icons   = ['🥈', '👑', '🥉'];
        $podium_order   = [1, 0, 2]; // display order: 2nd, 1st, 3rd
      ?>
      <div class="podium-row" style="grid-template-columns:repeat(3,1fr)">
        <?php foreach ($podium_order as $display_pos => $data_idx):
          $z = $top_zonautes[$data_idx];
        ?>
        <div class="podium-zonaute">
          <div class="pz-header <?= e($podium_headers[$display_pos]) ?>">
            <span class="pz-rank-icon"><?= e($podium_icons[$display_pos]) ?></span>
            <div class="pz-name"><?= e($z['pseudo']) ?></div>
            <div class="pz-xp"><?= format_xp($z['xp_total']) ?> <small>XP</small></div>
          </div>
          <div class="pz-body"><span class="pz-clan-tag <?= e($z['clan']) ?>-text"><?= e($clans[$z['clan']]['mascot'] ?? '') ?> <?= ucfirst(e($z['clan'])) ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Full table -->
      <div style="background:rgba(255,255,255,.03);border-radius:var(--radius-lg);overflow:hidden;border:1px solid rgba(255,255,255,.07)">
        <table class="rank-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Zonaute</th>
              <th>Clan</th>
              <th>XP à vie</th>
              <th>XP saison</th>
              <th>Missions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_zonautes as $z): ?>
            <tr>
              <td><?= e($z['rank']) ?></td>
              <td class="rank-name"><?= e($z['pseudo']) ?></td>
              <td><span class="rank-clan-chip chip-<?= e($z['clan']) ?>"><?= e($clans[$z['clan']]['mascot'] ?? '') ?> <?= ucfirst(e($z['clan'])) ?></span></td>
              <td class="rank-xp"><?= format_xp($z['xp_total']) ?></td>
              <td><?= format_xp($z['xp_season']) ?></td>
              <td><?= e($z['missions']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PANEL 3 — PAR CLAN -->
    <div class="tab-panel"
      data-panel-group="classement" data-panel-id="par-clan">

      <div class="par-clan-tabs" id="par-clan-tabs">
        <?php $_pci = 0; foreach ($clans as $clan): ?>
        <button class="par-clan-tab<?= $_pci === 0 ? ' active' : '' ?>"
          data-tab-group="par-clan" data-tab-id="<?= e($clan['slug']) ?>-tab"
          onclick="switchTab('par-clan','<?= e($clan['slug']) ?>-tab')"><?= e($clan['mascot']) ?> <?= e($clan['name']) ?></button>
        <?php $_pci++; endforeach; ?>
      </div>

      <?php $_pci = 0; foreach ($clans as $clan): ?>
      <div data-panel-group="par-clan" data-panel-id="<?= e($clan['slug']) ?>-tab" style="display:<?= $_pci === 0 ? 'block' : 'none' ?>">
        <p class="rank-section-title">Top Clan <?= e($clan['name']) ?> · XP saison en cours</p>
        <div style="background:rgba(255,255,255,.03);border-radius:var(--radius-lg);overflow:hidden;border:1px solid rgba(255,255,255,.07)">
          <table class="rank-table">
            <thead><tr><th>#</th><th>Zonaute</th><th>XP saison</th><th>XP à vie</th><th>Missions ce mois</th></tr></thead>
            <tbody>
              <?php foreach ($clan['top_members'] as $m_idx => $member): ?>
              <tr>
                <td><?= $m_idx + 1 ?></td>
                <td class="rank-name"><?= e($member['pseudo']) ?></td>
                <td class="rank-xp"><?= format_xp($member['xp_season']) ?></td>
                <td><?= format_xp($member['xp_total']) ?></td>
                <td><?= e($member['missions_month'] ?? '-') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php $_pci++; endforeach; ?>
    </div>

    <!-- PANEL 4 — ARCHIVES -->
    <div class="tab-panel"
      data-panel-group="classement" data-panel-id="archives">

      <p class="rank-section-title">Palmarès officiel · Saisons passées</p>

      <div class="archives-grid">
        <?php foreach ($season_trophies as $trophy): ?>
        <div class="archive-card">
          <div class="archive-header archive-header-<?= e($trophy['winner_clan']) ?>">
            <span class="archive-trophy-icon">🏆</span>
            <span class="archive-season"><?= e($trophy['season']) ?></span>
            <span class="archive-winner-name"><?= e($clans[$trophy['winner_clan']]['mascot'] ?? '') ?> Clan <?= ucfirst(e($trophy['winner_clan'])) ?></span>
          </div>
          <div class="archive-body">
            <div class="archive-scores">
              <div class="archive-score-row winner">
                <span><?= e($clans[$trophy['winner_clan']]['mascot'] ?? '') ?> <?= ucfirst(e($trophy['winner_clan'])) ?></span>
                <span><strong><?= e($trophy['winner_name']) ?></strong></span>
              </div>
            </div>
            <p style="font-size:.75rem;color:rgba(255,255,255,.35);margin-top:14px;line-height:1.5">Mission de saison : <?= e($trophy['main_mission']) ?> · <?= e($trophy['contributions']) ?> contributions</p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Global tally -->
      <div style="margin-top:36px;background:rgba(255,255,255,.03);border-radius:var(--radius-lg);padding:28px 32px;border:1px solid rgba(255,255,255,.07)">
        <p class="rank-section-title" style="margin-bottom:20px">Bilan historique · Trophées par clan</p>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;text-align:center">
          <?php foreach ($clans as $clan): ?>
          <div>
            <div style="font-size:2rem;margin-bottom:6px"><?= e($clan['mascot']) ?></div>
            <div style="font-size:1.3rem;font-weight:900;color:#fff"><?= e($clan['trophies']) ?></div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.35)"><?= $clan['trophies'] === 1 ? 'trophée' : 'trophées' ?> <?= e($clan['name']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ===================== CTA ===================== -->
<section class="cta-bloc reveal" style="max-width:1200px;margin:0 auto 72px;border-radius:var(--radius-lg)">
  <div class="cta-inner">
    <div>
      <h2>Tu veux changer ce classement ?</h2>
      <p>Chaque participation compte. Rejoins ton clan et fais basculer la Bataille.</p>
    </div>
    <div class="cta-actions">
      <a href="inscription.php" class="btn btn-white">Rejoindre la Zone →</a>
      <a href="missions.php" class="btn btn-outline-white">Voir les missions</a>
    </div>
  </div>
</section>

<?php
$page_scripts = '<script>
/* Init: open clans tab by default */
switchTab(\'classement\', \'clans\');
switchTab(\'par-clan\', \'littoral-tab\');
</script>';
require_once 'includes/footer.php';
?>
