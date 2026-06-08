<?php
$page_title       = 'Récompenses & XP — Zone85';
$page_description = 'Tous les points XP à gagner, les niveaux à atteindre et les badges à débloquer sur Zone85.';
$page_canonical   = 'https://www.zone85.fr/recompenses.php';
$page_robots      = 'index,follow';
$current_page     = '';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/repositories.php';

$is_logged = is_logged_in();
$me        = $is_logged ? current_user() : null;
$my_xp     = $me ? (int)$me['xp_total'] : 0;
$my_level  = $me ? (int)$me['level']    : 0;

// Badges depuis la DB
$badges_db = fetch_badges();

// Badges du membre connecté
$my_badges = [];
if ($is_logged && $me) {
    $ub = fetch_user_badges((int)$me['id']);
    foreach ($ub as $b) $my_badges[$b['id']] = true;
}

// Niveaux (seuils + noms)
$levels = [];
for ($l = 1; $l <= 10; $l++) {
    $levels[] = [
        'level'     => $l,
        'name'      => get_level_name($l),
        'threshold' => get_level_threshold($l),
    ];
}

// Regrouper les badges par rareté
$rarity_order = ['legendary', 'epic', 'rare', 'uncommon', 'common'];
$rarity_labels = [
    'legendary' => ['label' => 'Légendaire', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'epic'      => ['label' => 'Épique',     'color' => '#7c3aed', 'bg' => '#ede9fe'],
    'rare'      => ['label' => 'Rare',       'color' => '#2563eb', 'bg' => '#dbeafe'],
    'uncommon'  => ['label' => 'Peu commun', 'color' => '#059669', 'bg' => '#d1fae5'],
    'common'    => ['label' => 'Commun',     'color' => '#6b7280', 'bg' => '#f3f4f6'],
];
$badges_by_rarity = [];
foreach ($rarity_order as $r) $badges_by_rarity[$r] = [];
if ($badges_db) {
    foreach ($badges_db as $b) {
        $r = $b['rarity'] ?? 'common';
        if (!isset($badges_by_rarity[$r])) $r = 'common';
        $badges_by_rarity[$r][] = $b;
    }
}

$page_styles = '<style>
/* ── RECOMPENSES PAGE ───────────────────────────────────────── */
.rw-page{min-height:100vh;background:var(--beige,#f8f4ef);padding-bottom:72px}
.rw-hero{background:linear-gradient(135deg,#0c1e2e,#163756);padding:108px 0 48px;text-align:center;position:relative;overflow:hidden}
.rw-hero::before{content:"";position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;pointer-events:none}
.rw-hero-inner{position:relative;z-index:1;max-width:740px;margin:0 auto;padding:0 24px}
.rw-hero-label{font-size:.68rem;font-weight:900;letter-spacing:.2em;text-transform:uppercase;color:var(--primary,#ea5649);display:block;margin-bottom:12px}
.rw-hero-title{font-size:2.2rem;font-weight:900;color:#fff;letter-spacing:-.04em;margin-bottom:12px;line-height:1.15}
.rw-hero-sub{font-size:.95rem;color:rgba(255,255,255,.6);line-height:1.6}
.rw-hero-me{display:inline-flex;align-items:center;gap:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:40px;padding:8px 20px 8px 8px;margin-top:20px}
.rw-hero-me-av{width:36px;height:36px;border-radius:50%;background:var(--primary,#ea5649);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:900;color:#fff;overflow:hidden;flex-shrink:0}
.rw-hero-me-av img{width:100%;height:100%;object-fit:cover}
.rw-hero-me-info{text-align:left}
.rw-hero-me-name{font-size:.82rem;font-weight:800;color:#fff}
.rw-hero-me-xp{font-size:.72rem;color:rgba(255,255,255,.55)}

.rw-wrap{max-width:900px;margin:0 auto;padding:0 20px}
.rw-section{margin-top:48px}
.rw-section-title{font-size:1.1rem;font-weight:900;color:var(--navy-dark,#0c1e2e);margin-bottom:6px;letter-spacing:-.02em}
.rw-section-sub{font-size:.82rem;color:var(--text-muted,#6b7f96);margin-bottom:20px;line-height:1.5}

/* ── Tableau XP ── */
.rw-xp-table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)}
.rw-xp-table th{font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted,#6b7f96);padding:12px 16px;text-align:left;border-bottom:1px solid var(--beige-dark,#e8e0d4);background:var(--beige,#f8f4ef)}
.rw-xp-table td{padding:13px 16px;font-size:.88rem;color:var(--text-mid,#3d5166);border-bottom:1px solid var(--beige-dark,#e8e0d4)}
.rw-xp-table tr:last-child td{border-bottom:none}
.rw-xp-table td:last-child{font-weight:800;color:var(--primary,#ea5649);white-space:nowrap}
.rw-xp-badge{display:inline-block;background:rgba(234,86,73,.1);color:var(--primary,#ea5649);border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:800}
.rw-freq{font-size:.72rem;background:var(--beige,#f8f4ef);color:var(--text-muted,#6b7f96);border-radius:20px;padding:2px 8px;display:inline-block;font-weight:600}

/* ── Grille niveaux ── */
.rw-levels-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}
.rw-level-card{background:#fff;border-radius:12px;padding:14px 16px;border:2px solid var(--beige-dark,#e8e0d4);transition:box-shadow .15s}
.rw-level-card.current{border-color:var(--primary,#ea5649);box-shadow:0 0 0 3px rgba(234,86,73,.12)}
.rw-level-card.reached{border-color:rgba(234,86,73,.3);background:rgba(234,86,73,.03)}
.rw-level-num{font-size:.65rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted,#6b7f96);margin-bottom:4px}
.rw-level-name{font-size:.9rem;font-weight:800;color:var(--navy-dark,#0c1e2e);margin-bottom:4px}
.rw-level-xp{font-size:.78rem;color:var(--text-muted,#6b7f96);font-weight:600}
.rw-level-xp strong{color:var(--primary,#ea5649)}
.rw-level-badge-current{font-size:.62rem;font-weight:800;background:var(--primary,#ea5649);color:#fff;border-radius:20px;padding:2px 8px;display:inline-block;margin-top:6px}

/* ── Clan points ── */
.rw-clan-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.rw-clan-card{background:#fff;border-radius:12px;padding:16px;border:1.5px solid var(--beige-dark,#e8e0d4);display:flex;align-items:center;gap:12px}
.rw-clan-ico{font-size:1.5rem;flex-shrink:0;width:40px;text-align:center}
.rw-clan-label{flex:1;font-size:.85rem;color:var(--text-mid,#3d5166);font-weight:600;line-height:1.3}
.rw-clan-pts{font-size:1rem;font-weight:900;color:#0c1e2e;white-space:nowrap}

/* ── Badges ── */
.rw-rarity-section{margin-bottom:32px}
.rw-rarity-title{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.rw-rarity-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.rw-rarity-label{font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.rw-badges-grid{display:flex;flex-wrap:wrap;gap:10px}
.rw-badge-chip{display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--beige-dark,#e8e0d4);border-radius:10px;padding:8px 12px;min-width:0;transition:box-shadow .15s}
.rw-badge-chip:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}
.rw-badge-chip.obtained{border-color:var(--primary,#ea5649);background:rgba(234,86,73,.04)}
.rw-badge-chip.locked{opacity:.55}
.rw-badge-emoji{font-size:1.3rem;flex-shrink:0;line-height:1}
.rw-badge-info{min-width:0}
.rw-badge-name{font-size:.8rem;font-weight:700;color:var(--navy-dark,#0c1e2e);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px}
.rw-badge-cond{font-size:.68rem;color:var(--text-muted,#6b7f96)}
.rw-badge-check{font-size:.7rem;color:var(--primary,#ea5649);font-weight:800;margin-left:2px;flex-shrink:0}

/* ── Note ── */
.rw-note{background:#fff;border-radius:12px;border-left:4px solid var(--primary,#ea5649);padding:16px 20px;font-size:.85rem;color:var(--text-mid,#3d5166);line-height:1.6;margin-top:48px}
.rw-note strong{color:var(--navy-dark,#0c1e2e)}

@media(max-width:600px){
  .rw-hero-title{font-size:1.6rem}
  .rw-xp-table{font-size:.82rem}
  .rw-xp-table th,.rw-xp-table td{padding:10px 12px}
  .rw-levels-grid{grid-template-columns:repeat(2,1fr)}
}
</style>';

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<div class="rw-page">

  <!-- ════ HERO ════ -->
  <div class="rw-hero">
    <div class="rw-hero-inner">
      <span class="rw-hero-label">Zone85 · Progression</span>
      <h1 class="rw-hero-title">Récompenses &amp; XP</h1>
      <p class="rw-hero-sub">Le guide complet et à jour de tous les points XP à gagner,<br>les niveaux à franchir et les badges à débloquer.</p>
      <?php if ($is_logged && $me): ?>
        <?php
          $av_url = ($me['avatar_type'] === 'upload') ? avatar_url($me) : '';
          $initials = strtoupper(mb_substr($me['pseudo'], 0, 2));
        ?>
        <div class="rw-hero-me">
          <div class="rw-hero-me-av">
            <?php if ($av_url): ?><img src="<?= e($av_url) ?>" alt=""><?php else: ?><?= e($initials) ?><?php endif; ?>
          </div>
          <div class="rw-hero-me-info">
            <div class="rw-hero-me-name"><?= e($me['pseudo']) ?> · Niv.&nbsp;<?= $my_level ?> <?= e(get_level_name($my_level)) ?></div>
            <div class="rw-hero-me-xp"><?= number_format($my_xp) ?> XP · <?= count($my_badges) ?> badge<?= count($my_badges) > 1 ? 's' : '' ?> débloqué<?= count($my_badges) > 1 ? 's' : '' ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="rw-wrap">

    <!-- ════ SECTION XP ════ -->
    <div class="rw-section">
      <h2 class="rw-section-title">🎯 Comment gagner des XP</h2>
      <p class="rw-section-sub">Chaque action compte. Les XP s'accumulent sur ton profil et font progresser ton niveau. Ils contribuent aussi aux points de ton clan dans la Bataille des Clans.</p>

      <table class="rw-xp-table">
        <thead>
          <tr>
            <th>Action</th>
            <th>Gain XP</th>
            <th>Fréquence</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>🎉 Inscription sur Zone85</td>
            <td><span class="rw-xp-badge">+50 XP</span></td>
            <td><span class="rw-freq">Une fois</span></td>
          </tr>
          <tr>
            <td>🎯 Valider une mission</td>
            <td><span class="rw-xp-badge">+20 à +150 XP</span></td>
            <td><span class="rw-freq">Par mission</span></td>
          </tr>
          <tr>
            <td>🥾 Terminer une randonnée</td>
            <td><span class="rw-xp-badge">+25 XP</span></td>
            <td><span class="rw-freq">Par rando</span></td>
          </tr>
          <tr>
            <td>🔍 Proposer pour un KTC</td>
            <td><span class="rw-xp-badge">+10 XP</span></td>
            <td><span class="rw-freq">Par épisode</span></td>
          </tr>
          <tr>
            <td>🗳 Voter pour un KTC</td>
            <td><span class="rw-xp-badge">+5 XP</span></td>
            <td><span class="rw-freq">Par épisode</span></td>
          </tr>
          <tr>
            <td>🏆 Gagner le KTC du mois</td>
            <td><span class="rw-xp-badge">+50 à +200 XP</span></td>
            <td><span class="rw-freq">Par mois</span></td>
          </tr>
          <tr>
            <td>✍️ Commenter Les Échos</td>
            <td><span class="rw-xp-badge">+5 XP</span></td>
            <td><span class="rw-freq">Par article</span></td>
          </tr>
          <tr>
            <td>🏅 Débloquer un badge</td>
            <td><span class="rw-xp-badge">Variable</span></td>
            <td><span class="rw-freq">Selon badge</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ════ SECTION CLAN POINTS ════ -->
    <div class="rw-section">
      <h2 class="rw-section-title">🛡 Points de clan</h2>
      <p class="rw-section-sub">En plus de tes XP personnels, chaque action contribue aux points de ton clan dans la Bataille des Clans. Ces points sont saison­niers et remis à zéro à chaque nouvelle saison.</p>

      <div class="rw-clan-grid">
        <div class="rw-clan-card">
          <div class="rw-clan-ico">🎯</div>
          <div class="rw-clan-label">Valider une mission</div>
          <div class="rw-clan-pts">+50 pts</div>
        </div>
        <div class="rw-clan-card">
          <div class="rw-clan-ico">🥾</div>
          <div class="rw-clan-label">Terminer une randonnée</div>
          <div class="rw-clan-pts">+30 pts</div>
        </div>
        <div class="rw-clan-card">
          <div class="rw-clan-ico">🏆</div>
          <div class="rw-clan-label">Gagner un KTC</div>
          <div class="rw-clan-pts">+40 pts</div>
        </div>
        <div class="rw-clan-card">
          <div class="rw-clan-ico">⚡</div>
          <div class="rw-clan-label">Participer à un flash event</div>
          <div class="rw-clan-pts">+20 pts</div>
        </div>
      </div>
    </div>

    <!-- ════ SECTION NIVEAUX ════ -->
    <div class="rw-section">
      <h2 class="rw-section-title">⬆️ Les niveaux</h2>
      <p class="rw-section-sub">Ta progression est mesurée par ton niveau, de Novice à Immortel. Chaque seuil débloque une reconnaissance dans la communauté.</p>

      <div class="rw-levels-grid">
        <?php foreach ($levels as $lv): ?>
          <?php
            $is_current = $is_logged && $my_level === $lv['level'];
            $is_reached = $is_logged && $my_xp >= $lv['threshold'];
            $cls = $is_current ? 'current' : ($is_reached ? 'reached' : '');
          ?>
          <div class="rw-level-card <?= $cls ?>">
            <div class="rw-level-num">Niveau <?= $lv['level'] ?></div>
            <div class="rw-level-name"><?= e($lv['name']) ?></div>
            <div class="rw-level-xp">
              <?php if ($lv['threshold'] === 0): ?>
                <strong>Dès l'inscription</strong>
              <?php else: ?>
                À partir de <strong><?= number_format($lv['threshold']) ?> XP</strong>
              <?php endif; ?>
            </div>
            <?php if ($is_current): ?><span class="rw-level-badge-current">Ton niveau</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ════ SECTION BADGES ════ -->
    <div class="rw-section">
      <h2 class="rw-section-title">🏅 Les badges</h2>
      <p class="rw-section-sub">Les badges récompensent les accomplissements remarquables. Ils sont permanents et visibles sur ton profil.<?= $is_logged ? ' <strong>' . count($my_badges) . ' débloqué' . (count($my_badges) > 1 ? 's' : '') . '</strong> sur ' . ($badges_db ? count($badges_db) : '?') . ' disponibles.' : '' ?></p>

      <?php if ($badges_db): ?>
        <?php foreach ($rarity_order as $rarity): ?>
          <?php if (empty($badges_by_rarity[$rarity])) continue; ?>
          <?php $rl = $rarity_labels[$rarity]; ?>
          <div class="rw-rarity-section">
            <div class="rw-rarity-title">
              <span class="rw-rarity-dot" style="background:<?= e($rl['color']) ?>"></span>
              <span class="rw-rarity-label" style="color:<?= e($rl['color']) ?>"><?= e($rl['label']) ?></span>
            </div>
            <div class="rw-badges-grid">
              <?php foreach ($badges_by_rarity[$rarity] as $b): ?>
                <?php
                  $obtained = isset($my_badges[$b['id']]);
                  $chip_cls = $obtained ? 'obtained' : 'locked';
                  $cond_text = '';
                  if ($b['condition_type'] === 'xp_threshold') {
                      $cond_text = number_format((int)$b['condition_value']) . ' XP atteints';
                  } elseif ($b['condition_type'] === 'missions_count') {
                      $cond_text = (int)$b['condition_value'] . ' mission' . ((int)$b['condition_value'] > 1 ? 's' : '') . ' validée' . ((int)$b['condition_value'] > 1 ? 's' : '');
                  } elseif ($b['condition_type'] === 'randos_count') {
                      $cond_text = (int)$b['condition_value'] . ' rando' . ((int)$b['condition_value'] > 1 ? 's' : '') . ' terminée' . ((int)$b['condition_value'] > 1 ? 's' : '');
                  } elseif ($b['condition_type'] === 'registration') {
                      $cond_text = 'Inscription';
                  } elseif (!empty($b['condition_type'])) {
                      $cond_text = $b['condition_type'];
                  }
                ?>
                <div class="rw-badge-chip <?= $chip_cls ?>">
                  <span class="rw-badge-emoji"><?= e($b['icon_emoji'] ?? '🏅') ?></span>
                  <div class="rw-badge-info">
                    <div class="rw-badge-name"><?= e($b['title']) ?></div>
                    <?php if ($cond_text): ?><div class="rw-badge-cond"><?= e($cond_text) ?></div><?php endif; ?>
                  </div>
                  <?php if ($obtained): ?><span class="rw-badge-check">✓</span><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align:center;padding:48px 24px;color:var(--text-muted)">
          <div style="font-size:3rem;margin-bottom:12px">🏅</div>
          <p style="font-weight:700;color:var(--navy-dark);margin-bottom:8px">Badges bientôt disponibles</p>
          <p style="font-size:.88rem">Les badges arrivent avec les prochaines saisons.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- ════ NOTE ════ -->
    <div class="rw-note">
      <strong>À noter :</strong> Les valeurs XP des missions varient selon la difficulté et le type. Les XP sont définitivement acquis et ne disparaissent pas. Les points de clan sont remis à zéro à chaque nouvelle saison. Certains badges ne peuvent être débloqués qu'une seule fois.
    </div>

    <!-- ════ CTA ════ -->
    <div style="text-align:center;margin-top:40px;padding-bottom:8px">
      <?php if ($is_logged): ?>
        <a href="missions.php" style="display:inline-block;background:var(--primary,#ea5649);color:#fff;padding:14px 32px;border-radius:10px;font-weight:800;font-size:.95rem;text-decoration:none;margin-right:12px;margin-bottom:10px">Voir les missions →</a>
        <a href="communaute.php" style="display:inline-block;border:2px solid var(--navy-dark,#0c1e2e);color:var(--navy-dark,#0c1e2e);padding:14px 32px;border-radius:10px;font-weight:800;font-size:.95rem;text-decoration:none;margin-bottom:10px">Mon passeport</a>
      <?php else: ?>
        <a href="inscription.php" style="display:inline-block;background:var(--primary,#ea5649);color:#fff;padding:14px 32px;border-radius:10px;font-weight:800;font-size:.95rem;text-decoration:none;margin-right:12px;margin-bottom:10px">Rejoindre Zone85 →</a>
        <a href="login.php" style="display:inline-block;border:2px solid var(--navy-dark,#0c1e2e);color:var(--navy-dark,#0c1e2e);padding:14px 32px;border-radius:10px;font-weight:800;font-size:.95rem;text-decoration:none;margin-bottom:10px">Connexion</a>
      <?php endif; ?>
    </div>

  </div><!-- /.rw-wrap -->
</div><!-- /.rw-page -->

<?php require_once 'includes/footer.php'; ?>
