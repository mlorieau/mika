<?php
// ============================================================
// ZONE85 — Randonnées Zone85 (V12)
// Bibliothèque des randonnées vendéennes : bocage, littoral, marais
// ============================================================
$page_title       = 'Randonnées Zone85';
$page_description = 'Découvrez les randonnées vendéennes sélectionnées par Zone85 : bocage, littoral, marais. Fiches complètes, traces GPX et histoires de territoire.';
$page_canonical   = 'https://www.zone85.fr/randos.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type'    => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil',
         'item' => 'https://www.zone85.fr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Randonnées Zone85',
         'item' => 'https://www.zone85.fr/randos.php'],
    ],
];
$current_page = 'randos';

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// ── État connexion ─────────────────────────────────────────────
$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['pseudo']);
$current_uid  = $is_logged_in ? (int)($_SESSION['user_id'] ?? 0) : 0;

// ── Validation des filtres ─────────────────────────────────────
// V12.13 : filtres compacts multi-sélection.
// Compatibilité : accepte ?secteur=bocage et ?secteur[]=bocage.
$get_filter_values = static function(string $key, array $allowed): array {
    $raw = $_GET[$key] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' ? [$raw] : [];
    }
    $values = [];
    foreach ($raw as $value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value !== '' && in_array($value, $allowed, true) && !in_array($value, $values, true)) {
            $values[] = $value;
        }
    }
    return $values;
};

$secteur_filters    = $get_filter_values('secteur', ['bocage', 'littoral', 'marais', 'plaine']);
$difficulte_filters = $get_filter_values('difficulte', ['facile', 'moyen', 'difficile', 'expert']);
$distance_filters   = $get_filter_values('distance', ['moins5', '5-10', '10-15', '15plus']);
$duration_filters   = $get_filter_values('duree', ['moins1h', '1-2h', '2-3h', '3hplus']);

// Alias conservés pour les anciens blocs d'affichage éventuels.
$secteur_filter    = $secteur_filters[0] ?? '';
$difficulte_filter = $difficulte_filters[0] ?? '';
$distance_filter   = $distance_filters[0] ?? '';
$duration_filter   = $duration_filters[0] ?? '';

$has_filters = !empty($secteur_filters) || !empty($difficulte_filters) || !empty($distance_filters) || !empty($duration_filters);

// ── Données secteurs ───────────────────────────────────────────
$secteur_labels = [
    'bocage'   => 'Bocage',
    'littoral' => 'Littoral',
    'marais'   => 'Marais',
    'plaine'   => 'Plaine',
];
$secteur_colors = [
    'bocage'   => '#2a9d5c',
    'littoral' => '#12314e',
    'marais'   => '#8b6914',
    'plaine'   => '#6b7f96',
];
$secteur_gradients = [
    'bocage'   => 'linear-gradient(135deg, #2a9d5c 0%, #163756 100%)',
    'littoral' => 'linear-gradient(135deg, #12314e 0%, #0c6291 100%)',
    'marais'   => 'linear-gradient(135deg, #8b6914 0%, #2a3d1e 100%)',
    'plaine'   => 'linear-gradient(135deg, #6b7f96 0%, #12314e 100%)',
];
$secteur_emojis = [
    'bocage'   => '&#x1F333;',
    'littoral' => '&#x2693;',
    'marais'   => '&#x1F33F;',
    'plaine'   => '&#x1F343;',
];

// ── Données difficultés ────────────────────────────────────────
$difficulte_labels = [
    'facile'   => 'Facile',
    'moyen'    => 'Moyen',
    'difficile'=> 'Difficile',
    'expert'   => 'Expert',
];
$difficulte_stars = [
    'facile'   => 1,
    'moyen'    => 2,
    'difficile'=> 3,
    'expert'   => 4,
];
$difficulte_colors = [
    'facile'   => '#2a9d5c',
    'moyen'    => '#C9962A',
    'difficile'=> '#ea5649',
    'expert'   => '#7b2d8b',
];
$distance_labels = [
    'moins5' => '< 5 km',
    '5-10'   => '5 à 10 km',
    '10-15'  => '10 à 15 km',
    '15plus' => '+15 km',
];
$duration_labels = [
    'moins1h' => '< 1h',
    '1-2h'    => '1h à 2h',
    '2-3h'    => '2h à 3h',
    '3hplus'  => '+3h',
];

$active_filter_count = count($secteur_filters) + count($difficulte_filters) + count($distance_filters) + count($duration_filters);

$filter_group_label = static function(array $selected, array $labels, string $default): string {
    if (!$selected) return $default;
    if (count($selected) === 1) return $labels[$selected[0]] ?? $default;
    return count($selected) . ' filtres';
};

// ── Chargement depuis la base ──────────────────────────────────
$randos = [];
if (db_enabled()) {
    $pdo = db();
    if ($pdo) {
        try {
            $where  = ["r.status = 'published'"];
            $params = [];
            if (!empty($secteur_filters)) {
                $placeholders = [];
                foreach ($secteur_filters as $i => $value) {
                    $key = ':sec' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $value;
                }
                $where[] = 'r.secteur IN (' . implode(',', $placeholders) . ')';
            }
            if (!empty($difficulte_filters)) {
                $placeholders = [];
                foreach ($difficulte_filters as $i => $value) {
                    $key = ':dif' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $value;
                }
                $where[] = 'r.difficulty IN (' . implode(',', $placeholders) . ')';
            }
            if (!empty($distance_filters)) {
                $distance_where = [];
                foreach ($distance_filters as $value) {
                    if ($value === 'moins5') {
                        $distance_where[] = '(r.distance_km IS NOT NULL AND r.distance_km <= 5)';
                    } elseif ($value === '5-10') {
                        $distance_where[] = '(r.distance_km IS NOT NULL AND r.distance_km > 5 AND r.distance_km <= 10)';
                    } elseif ($value === '10-15') {
                        $distance_where[] = '(r.distance_km IS NOT NULL AND r.distance_km > 10 AND r.distance_km <= 15)';
                    } elseif ($value === '15plus') {
                        $distance_where[] = '(r.distance_km IS NOT NULL AND r.distance_km > 15)';
                    }
                }
                if ($distance_where) {
                    $where[] = '(' . implode(' OR ', $distance_where) . ')';
                }
            }
            if (!empty($duration_filters)) {
                $duration_where = [];
                foreach ($duration_filters as $value) {
                    if ($value === 'moins1h') {
                        $duration_where[] = '(r.duration_min IS NOT NULL AND r.duration_min <= 60)';
                    } elseif ($value === '1-2h') {
                        $duration_where[] = '(r.duration_min IS NOT NULL AND r.duration_min > 60 AND r.duration_min <= 120)';
                    } elseif ($value === '2-3h') {
                        $duration_where[] = '(r.duration_min IS NOT NULL AND r.duration_min > 120 AND r.duration_min <= 180)';
                    } elseif ($value === '3hplus') {
                        $duration_where[] = '(r.duration_min IS NOT NULL AND r.duration_min > 180)';
                    }
                }
                if ($duration_where) {
                    $where[] = '(' . implode(' OR ', $duration_where) . ')';
                }
            }

            $user_rp_subq = $current_uid > 0
                ? ", (SELECT status FROM rando_participations WHERE rando_id = r.id AND user_id = {$current_uid} LIMIT 1) AS user_rp_status"
                : ", NULL AS user_rp_status";
            $sql = "SELECT r.*,
                    (SELECT COUNT(*) FROM rando_participations rp WHERE rp.rando_id = r.id) AS nb_completions
                    {$user_rp_subq}
                    FROM randos r
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY r.published_at DESC, r.id DESC";

            $s = $pdo->prepare($sql);
            $s->execute($params);
            $randos = $s->fetchAll();
        } catch (PDOException $e) {
            // Table absente ou erreur — grille vide avec empty state
        }
    }
}

// ── Styles page ────────────────────────────────────────────────
$page_styles = '<style>

/* ============================================================
   RANDOS — CSS V12
============================================================ */

/* HERO — fond/padding depuis zone85.css (.randos-hero) */
.randos-hero::after {
  content: \'\';
  position: absolute;
  top: -80px; right: -80px;
  width: 400px; height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(42,157,92,.08) 0%, transparent 70%);
  pointer-events: none;
}
.randos-hero-inner { position: relative; z-index: 1; }
.rh-split { display: grid; grid-template-columns: 1fr 320px; gap: 56px; align-items: center; }
@media(max-width:900px){ .rh-split { grid-template-columns: 1fr; } .rh-visual { display: none; } }
.randos-hero-badge {
  display: inline-block;
  font-size: .68rem; font-weight: 900; letter-spacing: .18em;
  text-transform: uppercase; color: #2a9d5c;
  border: 1px solid rgba(42,157,92,.4); border-radius: 20px;
  padding: 5px 16px; margin-bottom: 20px;
  background: rgba(42,157,92,.08);
}
.randos-hero h1 {
  font-size: clamp(2.4rem, 6vw, 4rem);
  font-weight: 900; color: #fff; letter-spacing: -2px;
  line-height: 1.0; margin-bottom: 18px;
}
.randos-hero h1 span { color: #2a9d5c; }
.randos-hero .hero-sub {
  font-size: 1.05rem; color: rgba(255,255,255,.6);
  line-height: 1.75;
}
.rh-stat-card {
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  border-radius: 18px; padding: 22px 20px; display: flex; flex-direction: column; gap: 12px;
}
.rh-stat-item { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.07); }
.rh-stat-item:last-child { border-bottom: none; padding-bottom: 0; }
.rh-stat-num { font-size: 1.7rem; font-weight: 900; color: #2a9d5c; min-width: 44px; text-align: right; }
.rh-stat-txt { font-size: .8rem; color: rgba(255,255,255,.55); line-height: 1.3; }

/* FILTRES — V12.10 redesign */
.randos-filters-bar {
  background: #fff;
  border-bottom: 2px solid #f0ece7;
  padding: 20px 0;
  position: sticky; top: 68px; z-index: 90;
  box-shadow: 0 4px 16px rgba(0,0,0,.06);
}
.randos-filters-inner {
  display: flex; flex-direction: column; gap: 14px;
}
.randos-filter-row {
  display: flex; align-items: center; gap: 8px;
  overflow-x: auto; -webkit-overflow-scrolling: touch;
  scrollbar-width: none; padding-bottom: 2px;
}
.randos-filter-row::-webkit-scrollbar { display: none; }
.randos-filter-label {
  font-size: .62rem; font-weight: 900; letter-spacing: .14em;
  text-transform: uppercase; color: #6b7f96;
  white-space: nowrap; flex-shrink: 0; min-width: 70px;
}
/* Pills secteur — grandes et colorées */
.randos-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 18px; border-radius: 999px;
  font-size: .82rem; font-weight: 700;
  text-decoration: none; white-space: nowrap; flex-shrink: 0;
  border: 2px solid #e8e3dd; background: #f8f4ef;
  color: #4a5f73; transition: all .18s;
}
.randos-chip:hover {
  border-color: #2a9d5c; color: #2a9d5c;
  background: rgba(42,157,92,.06);
}
/* Actif secteur */
.randos-chip.active-bocage   { background:#2a9d5c; border-color:#2a9d5c; color:#fff; }
.randos-chip.active-littoral { background:#12314e; border-color:#12314e; color:#fff; }
.randos-chip.active-marais   { background:#8b6914; border-color:#8b6914; color:#fff; }
.randos-chip.active-plaine   { background:#6b7f96; border-color:#6b7f96; color:#fff; }
.randos-chip.active          { background:#0c1e2e; border-color:#0c1e2e; color:#fff; }
/* Actif difficulté */
.randos-chip.active-dif-facile    { background:#2a9d5c; border-color:#2a9d5c; color:#fff; }
.randos-chip.active-dif-moyen     { background:#C9962A; border-color:#C9962A; color:#fff; }
.randos-chip.active-dif-difficile { background:#ea5649; border-color:#ea5649; color:#fff; }
.randos-chip.active-dif-expert    { background:#7b2d8b; border-color:#7b2d8b; color:#fff; }
/* Reset link */
.randos-filter-reset {
  font-size:.76rem; font-weight:700; color:#ea5649;
  text-decoration:none; white-space:nowrap; flex-shrink:0;
  margin-left:auto; padding: 8px 0;
}
.randos-filter-reset:hover { text-decoration:underline; }


/* FILTRES — V12.11 expérience plus claire */
.randos-filter-card{
  max-width: 1080px;
  margin: 0 auto;
  background:#fff;
  border:1px solid rgba(12,30,46,.08);
  border-radius: 22px;
  box-shadow:0 18px 45px rgba(12,30,46,.08);
  padding:18px;
}
.randos-filter-top{
  display:flex;align-items:center;justify-content:space-between;gap:18px;
  padding:0 4px 12px;border-bottom:1px solid #eee8df;margin-bottom:14px;
}
.randos-filter-title{font-size:1rem;font-weight:900;color:#0c1e2e;letter-spacing:-.02em;margin:0}
.randos-filter-help{font-size:.82rem;color:#6b7f96;margin-top:3px}
.randos-filter-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 22px}
.randos-filter-row{overflow:visible;flex-wrap:wrap;gap:8px;padding:0}
.randos-filter-label{min-width:100%;margin-bottom:2px;color:#2a9d5c;}
.randos-chip{box-shadow:0 1px 0 rgba(12,30,46,.04)}
.randos-filter-summary{font-size:.82rem;color:#4a5f73;font-weight:700;white-space:nowrap}
.randos-filter-reset{margin-left:0;background:#fff3f1;border:1px solid #ffd4ce;color:#ea5649;border-radius:999px;padding:9px 14px;text-decoration:none}
@media(max-width:760px){.randos-filter-groups{grid-template-columns:1fr}.randos-filter-top{align-items:flex-start;flex-direction:column}.randos-filter-summary{white-space:normal}.randos-filters-bar{top:60px}}

/* SECTION GRILLE */
.randos-section { background: var(--beige-light, #f7f4ef); padding: 72px 0 80px; }
.randos-section-header { margin-bottom: 48px; }
.randos-section-label {
  font-size: .7rem; font-weight: 900; letter-spacing: .18em;
  text-transform: uppercase; color: #2a9d5c;
  margin-bottom: 8px; display: block;
}
.randos-section-title {
  font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 900;
  color: var(--text, #1a1a1a); letter-spacing: -.5px; line-height: 1.2;
}
.randos-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

/* CARD RANDO */
.rando-card {
  background: #fff; border-radius: 16px; overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  border: 1px solid rgba(0,0,0,.06);
  transition: transform .25s, box-shadow .25s;
  display: flex; flex-direction: column;
  text-decoration: none; color: inherit;
}
.rando-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
.rando-card-visual {
  height: 190px; display: flex; align-items: center;
  justify-content: center; font-size: 3.2rem;
  position: relative; flex-shrink: 0; overflow: hidden;
}
.rando-card-visual img {
  width: 100%; height: 100%; object-fit: cover;
  position: absolute; inset: 0;
}
.rando-card-emoji { position: relative; z-index: 1; }
.rando-card-secteur-badge {
  position: absolute; top: 12px; left: 12px; z-index: 2;
  font-size: .66rem; font-weight: 900;
  letter-spacing: .08em; text-transform: uppercase;
  color: #fff; border-radius: 999px; padding: 5px 13px;
  backdrop-filter: blur(4px); box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
.rando-card-secteur-badge.badge-bocage   { background: rgba(42,157,92,.85); }
.rando-card-secteur-badge.badge-littoral { background: rgba(18,49,78,.85); }
.rando-card-secteur-badge.badge-marais   { background: rgba(139,105,20,.85); }
.rando-card-secteur-badge.badge-plaine   { background: rgba(107,127,150,.85); }
/* Icône GPX sur la card */
.rando-card-gpx-badge {
  position: absolute; top: 12px; right: 12px; z-index: 2;
  background: rgba(0,0,0,.45); color: #fff; border-radius: 999px;
  padding: 4px 10px; font-size: .66rem; font-weight: 700;
  backdrop-filter: blur(4px); display: flex; align-items: center; gap: 4px;
}
.rando-card-body {
  padding: 22px 22px 24px; flex: 1;
  display: flex; flex-direction: column;
}
.rando-card-title {
  font-size: 1.05rem; font-weight: 800; color: var(--text, #1a1a1a);
  line-height: 1.35; margin-bottom: 8px; letter-spacing: -.2px;
}
.rando-card-summary {
  font-size: .86rem; color: var(--text-mid, #555);
  line-height: 1.65; flex: 1; margin-bottom: 14px;
}
.rando-card-infos {
  display: flex; align-items: center; gap: 12px;
  flex-wrap: wrap; margin-bottom: 14px;
}
.rando-card-info {
  font-size: .75rem; font-weight: 700; color: #6b7f96;
  display: flex; align-items: center; gap: 4px;
}
.rando-card-stars {
  display: inline-flex; gap: 1px;
}
.rando-card-star { font-size: .7rem; }
.rando-card-star.on  { color: #C9962A; }
.rando-card-star.off { color: #ccc; }
.rando-card-footer {
  display: flex; align-items: center; justify-content: space-between;
  border-top: 1px solid rgba(0,0,0,.06); padding-top: 14px;
}
.rando-card-commune {
  font-size: .72rem; font-weight: 600; color: #999;
}
.rando-card-cta {
  font-size: .78rem; font-weight: 800;
  color: #2a9d5c; letter-spacing: .02em;
  text-decoration: none;
}
.rando-card-cta:hover { color: #1d7a47; }

/* BADGE PARTICIPATION UTILISATEUR */
.rando-card-done-badge {
  position: absolute; bottom: 10px; right: 10px;
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .7rem; font-weight: 900; color: #fff;
  padding: 4px 10px; border-radius: 999px;
  letter-spacing: .02em;
  box-shadow: 0 2px 8px rgba(0,0,0,.22);
}
.rando-card-done-stamped   { background: rgba(18,49,78,.82); }
.rando-card-done-pending   { background: rgba(233,149,26,.9); }
.rando-card-done-validated { background: rgba(42,157,92,.92); }

/* EMPTY STATE */
.randos-empty {
  grid-column: 1/-1; text-align: center;
  padding: 72px 24px;
}
.randos-empty-icon { font-size: 3.2rem; margin-bottom: 16px; }
.randos-empty-title {
  font-size: 1.1rem; font-weight: 800; color: #0c1e2e;
  margin-bottom: 10px;
}
.randos-empty-text {
  font-size: .92rem; color: var(--text-mid, #555);
  line-height: 1.7; margin-bottom: 24px;
}
.randos-empty-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: #2a9d5c; color: #fff;
  font-size: .88rem; font-weight: 800; padding: 12px 26px;
  border-radius: 10px; text-decoration: none;
  transition: opacity .2s, transform .2s;
}
.randos-empty-btn:hover { opacity: .88; transform: translateY(-2px); }

/* CTA BAS */
.randos-cta-section {
  background: linear-gradient(160deg, #0c1e2e 0%, #12314e 100%);
  padding: 64px 0;
}
.randos-cta-inner { max-width: 640px; margin: 0 auto; text-align: center; }
.randos-cta-icon  { font-size: 2.6rem; margin-bottom: 16px; display: block; }
.randos-cta-title {
  font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 900;
  color: #fff; letter-spacing: -.5px; margin-bottom: 12px;
}
.randos-cta-sub {
  font-size: .95rem; color: rgba(255,255,255,.6);
  line-height: 1.7; margin-bottom: 32px;
}
.randos-cta-btns {
  display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
}
.randos-btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  background: #2a9d5c; color: #fff;
  font-size: .88rem; font-weight: 800; padding: 13px 28px;
  border-radius: 10px; text-decoration: none;
  transition: opacity .2s, transform .2s;
}
.randos-btn-primary:hover { opacity: .88; transform: translateY(-2px); }
.randos-btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  background: transparent; color: rgba(255,255,255,.8);
  font-size: .88rem; font-weight: 700; padding: 13px 28px;
  border-radius: 10px; text-decoration: none;
  border: 1.5px solid rgba(255,255,255,.2);
  transition: border-color .2s, color .2s;
}
.randos-btn-outline:hover { border-color: rgba(255,255,255,.5); color: #fff; }

/* BADGES LISIBILITÉ */
.rando-badge {
  display: inline-flex; align-items: center; gap: 4px;
  background: #f7f4ef; border: 1px solid rgba(12,30,46,.1);
  color: #4a5f73; font-size: .72rem; font-weight: 700;
  border-radius: 20px; padding: 2px 8px;
  white-space: nowrap;
}
.rando-badge-diff-facile   { background: rgba(42,157,92,.1);  color: #1a7a42; border-color: rgba(42,157,92,.25); }
.rando-badge-diff-moyen    { background: rgba(201,150,42,.1); color: #8a6020; border-color: rgba(201,150,42,.25); }
.rando-badge-diff-difficile{ background: rgba(234,86,73,.1);  color: #b83a2f; border-color: rgba(234,86,73,.25); }
.rando-badge-gpx  { background: rgba(12,99,195,.1); color: #0c63c3; border-color: rgba(12,99,195,.2); }
.rando-badge-fam  { background: rgba(42,157,92,.08); color: #1a7a42; border-color: rgba(42,157,92,.2); }
.rando-badges-row {
  display: flex; flex-wrap: wrap; gap: 5px;
  margin-bottom: 12px;
}

/* RESPONSIVE */
@media (max-width: 960px) {
  .randos-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .randos-hero { padding: 80px 0 52px; }
  .randos-grid { grid-template-columns: 1fr; }
  .randos-chip { padding: 10px 12px; font-size: .7rem; }
  .randos-cta-section { padding: 48px 0; }
}

.randos-map-section{background:#fff;padding:34px 0 18px;border-bottom:1px solid rgba(0,0,0,.06)}
.randos-map-card{border-radius:18px;overflow:hidden;box-shadow:0 12px 30px rgba(12,30,46,.08);border:1px solid rgba(12,30,46,.08);background:#fff}
#zone85RandosMap{height:360px;width:100%;background:#f4efe8;position:relative;z-index:1}
.randos-map-section,.randos-map-card{position:relative;z-index:1}
.leaflet-fallback{display:flex;align-items:center;justify-content:center;color:#6b7f96;font-size:.9rem;height:100%;text-align:center;padding:20px}
.randos-map-empty{padding:28px;color:#6b7f96;font-size:.95rem}
.rando-click-spark{position:fixed;pointer-events:none;z-index:9999;font-size:14px;animation:randoSpark .75s ease-out forwards}
@keyframes randoSpark{0%{transform:translate(-50%,-50%) scale(.6);opacity:1}100%{transform:translate(-50%,-80px) scale(1.25);opacity:0}}

/* FILTRES COMPACTS — V12.13 */
.randos-filter-shell{
  background:#fff;
  border-bottom:1px solid #eee8df;
  padding:18px 0;
  position:sticky;
  top:68px;
  z-index:3000;
  box-shadow:0 8px 22px rgba(12,30,46,.06);
}
.randos-filter-compact{
  max-width:1120px;
  margin:0 auto;
  background:#fff;
  border:1px solid rgba(12,30,46,.08);
  border-radius:22px;
  padding:16px 18px;
  box-shadow:0 14px 35px rgba(12,30,46,.06);
}
.randos-filter-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:18px;
  margin-bottom:12px;
}
.randos-filter-head h2{
  margin:0 0 4px;
  font-size:1rem;
  line-height:1.2;
  color:#0c1e2e;
  font-weight:900;
  letter-spacing:-.02em;
}
.randos-filter-head p{
  margin:0;
  color:#6b7f96;
  font-size:.84rem;
}
.randos-filter-count{
  color:#4a5f73;
  font-size:.82rem;
  white-space:nowrap;
  padding-top:3px;
}
.randos-filter-count strong{color:#0c1e2e;font-size:1.05rem}
.randos-filter-line{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
}
.z85-filter-menu{position:relative;display:inline-flex}
.z85-filter-trigger{
  min-height:44px;
  display:inline-flex;
  align-items:center;
  gap:8px;
  border:2px solid #e8e3dd;
  background:#f8f4ef;
  color:#263849;
  border-radius:999px;
  padding:0 14px;
  font-weight:800;
  cursor:pointer;
  box-shadow:0 1px 0 rgba(12,30,46,.04);
  transition:.16s ease;
}
.z85-filter-trigger:hover,
.z85-filter-trigger.has-selection{
  border-color:#0c1e2e;
  background:#0c1e2e;
  color:#fff;
}
.z85-filter-trigger em{
  font-style:normal;
  font-size:.78rem;
  opacity:.85;
  font-weight:700;
}
.z85-filter-caret{font-size:.75rem;opacity:.65}
.z85-filter-panel{
  display:none;
  position:absolute;
  left:0;
  top:calc(100% + 10px);
  min-width:260px;
  background:#fff;
  border:1px solid #e8e3dd;
  border-radius:18px;
  box-shadow:0 22px 55px rgba(12,30,46,.18);
  padding:12px;
  z-index:5000;
}
.z85-filter-menu.open .z85-filter-panel{display:block}
.z85-filter-panel-title{
  font-size:.7rem;
  text-transform:uppercase;
  letter-spacing:.14em;
  font-weight:900;
  color:#2a9d5c;
  margin:4px 6px 8px;
}
.z85-filter-option{
  display:flex;
  align-items:center;
  gap:10px;
  padding:9px 8px;
  border-radius:12px;
  color:#263849;
  font-weight:750;
  cursor:pointer;
}
.z85-filter-option:hover{background:#f8f4ef}
.z85-filter-option input{width:17px;height:17px;accent-color:#2a9d5c}
.z85-filter-panel-actions{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  border-top:1px solid #eee8df;
  margin-top:8px;
  padding-top:10px;
}
.z85-filter-clear,
.z85-filter-apply{
  border:0;
  border-radius:999px;
  padding:9px 13px;
  font-weight:850;
  cursor:pointer;
}
.z85-filter-clear{background:#f8f4ef;color:#6b7f96}
.z85-filter-apply{background:#ea5649;color:#fff}
.z85-filter-reset-all{
  min-height:44px;
  display:inline-flex;
  align-items:center;
  border:1px solid #ffd4ce;
  background:#fff3f1;
  color:#ea5649;
  border-radius:999px;
  padding:0 14px;
  font-size:.82rem;
  font-weight:850;
  text-decoration:none;
}
.randos-active-filters{
  display:flex;
  flex-wrap:wrap;
  gap:7px;
  margin-top:12px;
}
.randos-active-filters span{
  display:inline-flex;
  align-items:center;
  gap:5px;
  background:rgba(42,157,92,.08);
  border:1px solid rgba(42,157,92,.18);
  color:#2a9d5c;
  border-radius:999px;
  padding:7px 10px;
  font-size:.76rem;
  font-weight:850;
}
@media(max-width:760px){
  .randos-filter-shell{top:60px;padding:12px 0}
  .randos-filter-compact{border-radius:18px;padding:14px}
  .randos-filter-head{flex-direction:column;gap:8px}
  .randos-filter-line{display:flex;overflow-x:auto;flex-wrap:nowrap;padding-bottom:4px;-webkit-overflow-scrolling:touch}
  .z85-filter-menu{position:static;flex-shrink:0}
  .z85-filter-panel{position:fixed;left:14px;right:14px;top:auto;bottom:18px;min-width:0;max-height:70vh;overflow:auto;z-index:6000}
  .z85-filter-trigger{white-space:nowrap}
}

</style>';


// Données carte publique (OpenStreetMap/Leaflet)
$randos_map_points = [];
foreach ($randos as $_r) {
    if (!empty($_r['gps_lat']) && !empty($_r['gps_lng'])) {
        $randos_map_points[] = [
            'id' => (int)$_r['id'],
            'title' => $_r['title'] ?? '',
            'lat' => (float)$_r['gps_lat'],
            'lng' => (float)$_r['gps_lng'],
            'secteur' => $_r['secteur'] ?? '',
            'url' => url('rando.php?slug=' . urlencode($_r['slug'] ?? '')),
        ];
    }
}

require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="randos-hero">
  <div class="container randos-hero-inner">
    <div class="rh-split">

      <!-- Colonne texte -->
      <div>
        <span class="randos-hero-badge">🥾 RandoZone</span>
        <h1>RANDONN&Eacute;ES<br><span>ZONE85</span></h1>
        <p class="hero-sub">La Vendée à pied. Du bocage au littoral, des chemins commentés par la communauté.</p>
      </div>

      <!-- Colonne visuelle : stats randos -->
      <div class="rh-visual">
        <?php
        $rh_total   = count($randos ?? []);
        $rh_diffs   = ['Facile'=>0,'Moyen'=>0,'Difficile'=>0];
        foreach (($randos ?? []) as $r) {
            $d = $r['difficulty'] ?? '';
            if (isset($rh_diffs[$d])) $rh_diffs[$d]++;
        }
        $rh_regions = count(array_unique(array_filter(array_column($randos ?? [], 'region'))));
        ?>
        <div class="rh-stat-card">
          <div style="font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.35);margin-bottom:4px">Sur les sentiers</div>
          <div class="rh-stat-item">
            <div class="rh-stat-num"><?= $rh_total ?: '?' ?></div>
            <div class="rh-stat-txt">Randonnée<?= $rh_total>1?'s':'' ?> répertoriée<?= $rh_total>1?'s':'' ?></div>
          </div>
          <?php if ($rh_diffs['Facile'] > 0): ?>
          <div class="rh-stat-item">
            <div class="rh-stat-num" style="font-size:1.1rem;color:#2a9d5c">🟢 <?= $rh_diffs['Facile'] ?></div>
            <div class="rh-stat-txt">Facile<?= $rh_diffs['Facile']>1?'s':'' ?> — idéal pour commencer</div>
          </div>
          <?php endif; ?>
          <?php if ($rh_diffs['Moyen'] > 0): ?>
          <div class="rh-stat-item">
            <div class="rh-stat-num" style="font-size:1.1rem;color:#C9962A">🟡 <?= $rh_diffs['Moyen'] ?></div>
            <div class="rh-stat-txt">Moyen<?= $rh_diffs['Moyen']>1?'s':'' ?> — pour les habitués</div>
          </div>
          <?php endif; ?>
          <?php if ($rh_diffs['Difficile'] > 0): ?>
          <div class="rh-stat-item">
            <div class="rh-stat-num" style="font-size:1.1rem;color:#ea5649">🔴 <?= $rh_diffs['Difficile'] ?></div>
            <div class="rh-stat-txt">Difficile<?= $rh_diffs['Difficile']>1?'s':'' ?> — pour les courageux</div>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ===================== FILTRES ===================== -->
<nav class="randos-filter-shell" aria-label="Filtres des randonnées">
  <div class="container">
    <form id="randosFilterForm" class="randos-filter-compact" method="get" action="randos.php">
      <div class="randos-filter-head">
        <div>
          <h2>Trouver la bonne sortie</h2>
          <p>Filtres rapides, combinables, sans prendre toute la page.</p>
        </div>
        <div class="randos-filter-count">
          <strong><?= count($randos) ?></strong>
          rando<?= count($randos)>1?'s':'' ?> trouvée<?= count($randos)>1?'s':'' ?>
        </div>
      </div>

      <div class="randos-filter-line">
        <?php
          $filter_groups = [
            'secteur' => [
              'title' => 'Secteur',
              'icon' => '&#x1F5FA;',
              'selected' => $secteur_filters,
              'labels' => [
                'bocage' => '&#x1F333; Bocage',
                'littoral' => '&#x2693; Littoral',
                'marais' => '&#x1F33F; Marais',
                'plaine' => '&#x1F343; Plaine',
              ],
              'plain_labels' => $secteur_labels,
            ],
            'difficulte' => [
              'title' => 'Difficulté',
              'icon' => '&#x2B50;',
              'selected' => $difficulte_filters,
              'labels' => [
                'facile' => '&#x2B50; Facile',
                'moyen' => '&#x2B50;&#x2B50; Moyen',
                'difficile' => '&#x2B50;&#x2B50;&#x2B50; Difficile',
                'expert' => '&#x2B50;&#x2B50;&#x2B50;&#x2B50; Expert',
              ],
              'plain_labels' => $difficulte_labels,
            ],
            'distance' => [
              'title' => 'Distance',
              'icon' => '&#x1F97E;',
              'selected' => $distance_filters,
              'labels' => [
                'moins5' => '< 5 km',
                '5-10' => '5 à 10 km',
                '10-15' => '10 à 15 km',
                '15plus' => '+15 km',
              ],
              'plain_labels' => $distance_labels,
            ],
            'duree' => [
              'title' => 'Durée',
              'icon' => '&#x23F1;',
              'selected' => $duration_filters,
              'labels' => [
                'moins1h' => '< 1h',
                '1-2h' => '1h à 2h',
                '2-3h' => '2h à 3h',
                '3hplus' => '+3h',
              ],
              'plain_labels' => $duration_labels,
            ],
          ];
        ?>
        <?php foreach ($filter_groups as $name => $group): ?>
          <?php $selected = $group['selected']; ?>
          <div class="z85-filter-menu" data-filter-menu>
            <button type="button" class="z85-filter-trigger <?= $selected ? 'has-selection' : '' ?>" data-filter-trigger aria-expanded="false">
              <span><?= $group['icon'] ?></span>
              <strong><?= e($group['title']) ?></strong>
              <?php if ($selected): ?>
                <em><?= e($filter_group_label($selected, $group['plain_labels'], $group['title'])) ?></em>
              <?php endif; ?>
              <span class="z85-filter-caret">▾</span>
            </button>
            <div class="z85-filter-panel" data-filter-panel>
              <div class="z85-filter-panel-title"><?= e($group['title']) ?></div>
              <?php foreach ($group['labels'] as $value => $label): ?>
                <label class="z85-filter-option">
                  <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($value) ?>" <?= in_array($value, $selected, true) ? 'checked' : '' ?>>
                  <span><?= $label ?></span>
                </label>
              <?php endforeach; ?>
              <div class="z85-filter-panel-actions">
                <button type="button" class="z85-filter-clear" data-clear-filter="<?= e($name) ?>">Effacer</button>
                <button type="submit" class="z85-filter-apply">Appliquer</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if ($has_filters): ?>
          <a href="randos.php" class="z85-filter-reset-all">Effacer tout</a>
        <?php endif; ?>
      </div>

      <?php if ($has_filters): ?>
        <div class="randos-active-filters" aria-label="Filtres actifs">
          <?php foreach ($secteur_filters as $value): ?>
            <span><?= $secteur_emojis[$value] ?? '' ?> <?= e($secteur_labels[$value] ?? $value) ?></span>
          <?php endforeach; ?>
          <?php foreach ($difficulte_filters as $value): ?>
            <span>★ <?= e($difficulte_labels[$value] ?? $value) ?></span>
          <?php endforeach; ?>
          <?php foreach ($distance_filters as $value): ?>
            <span>🥾 <?= e($distance_labels[$value] ?? $value) ?></span>
          <?php endforeach; ?>
          <?php foreach ($duration_filters as $value): ?>
            <span>⏱ <?= e($duration_labels[$value] ?? $value) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>
</nav>

<!-- ===================== CARTE DES RANDOS ===================== -->
<?php if (!empty($randos_map_points)): ?>
<section class="randos-map-section">
  <div class="container">
    <div class="randos-map-card">
      <div id="zone85RandosMap"
           data-points='<?= htmlspecialchars(json_encode($randos_map_points, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'>
        <div class="randos-map-empty">Chargement de la carte des randonnées...</div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===================== GRILLE DES RANDOS ===================== -->
<section class="randos-section">
  <div class="container">
    <div class="randos-section-header">
      <span class="randos-section-label">
        <?php
          $label_parts = [];
          if ($secteur_filter) $label_parts[] = htmlspecialchars($secteur_labels[$secteur_filter], ENT_QUOTES, 'UTF-8');
          if ($difficulte_filter) $label_parts[] = htmlspecialchars($difficulte_labels[$difficulte_filter], ENT_QUOTES, 'UTF-8');
          echo $label_parts ? implode(' · ', $label_parts) : 'Toutes les randonn&eacute;es';
        ?>
      </span>
      <h2 class="randos-section-title">
        <?php if ($secteur_filter): ?>
          Randonn&eacute;es — <?= htmlspecialchars($secteur_labels[$secteur_filter], ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
          Randonn&eacute;es Zone85
        <?php endif; ?>
      </h2>
    </div>

    <div class="randos-grid">
      <?php if (empty($randos)): ?>
        <div class="randos-empty">
          <div class="randos-empty-icon">&#x1F97E;</div>
          <?php if ($has_filters): ?>
            <p class="randos-empty-title">Aucune randonn&eacute;e ne correspond &agrave; vos filtres.</p>
            <p class="randos-empty-text">Essayez de modifier ou r&eacute;initialiser vos crit&egrave;res de recherche.</p>
            <a href="randos.php" class="randos-empty-btn">&#x21BA; R&eacute;initialiser les filtres</a>
          <?php else: ?>
            <p class="randos-empty-title">Les premi&egrave;res randonn&eacute;es Zone85 arrivent bient&ocirc;t.</p>
            <p class="randos-empty-text">
              En attendant, d&eacute;couvrez les missions de randonn&eacute;e disponibles<br>
              et explorez la Vend&eacute;e &agrave; votre rythme.
            </p>
            <a href="missions.php" class="randos-empty-btn">&#x1F3AF; Voir les missions</a>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <?php foreach ($randos as $r): ?>
          <?php
            $sec      = $r['secteur'] ?? 'bocage';
            $grad     = $secteur_gradients[$sec] ?? 'linear-gradient(135deg,#2a9d5c,#12314e)';
            $emoji    = $secteur_emojis[$sec]    ?? '&#x1F97E;';
            $badge    = $secteur_labels[$sec]     ?? $sec;
            $dif      = $r['difficulty'] ?? 'facile';
            $stars    = $difficulte_stars[$dif]   ?? 1;
            $summary  = $r['summary'] ?? '';
            if (mb_strlen($summary) > 80) {
                $summary = mb_substr($summary, 0, 77) . '…';
            }
            // Formatage distance
            $dist_str = $r['distance_km'] ? number_format((float)$r['distance_km'], 1, ',', '') . ' km' : '';
            // Formatage durée
            $dur_str  = '';
            if ($r['duration_min']) {
                $h = (int)floor($r['duration_min'] / 60);
                $m = $r['duration_min'] % 60;
                $dur_str = $h ? $h . 'h' . ($m ? sprintf('%02d', $m) : '') : $m . ' min';
            }
            $rando_url = 'rando.php?slug=' . urlencode($r['slug']);
          ?>
          <article class="rando-card">
            <a href="<?= htmlspecialchars($rando_url, ENT_QUOTES, 'UTF-8') ?>"
               class="rando-card-visual" style="background: <?= $grad ?>"
               aria-label="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>">
              <?php if (!empty($r['cover_image'])): ?>
                <img src="<?= e(media_url($r['cover_image'])) ?>"
                     alt="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>"
                     loading="lazy" onerror="this.style.display='none';this.closest('.rando-card-visual')?.classList.add('rando-card-visual-fallback');">
              <?php else: ?>
                <span class="rando-card-emoji"><?= $emoji ?></span>
              <?php endif; ?>
              <span class="rando-card-secteur-badge badge-<?= htmlspecialchars($sec, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?>
              </span>
              <?php if (!empty($r['gpx_file']) || !empty($r['gpx_url'])): ?>
              <span class="rando-card-gpx-badge">&#x1F5FA; GPX</span>
              <?php endif; ?>
              <?php
                $urp = $r['user_rp_status'] ?? null;
                if ($urp === 'validated'): ?>
                  <span class="rando-card-done-badge rando-card-done-validated">&#x1F3C6; +25 XP</span>
                <?php elseif ($urp === 'pending'): ?>
                  <span class="rando-card-done-badge rando-card-done-pending">&#x23F3; En validation</span>
                <?php elseif ($urp): ?>
                  <span class="rando-card-done-badge rando-card-done-stamped">&#x2713; Tamponnée</span>
                <?php endif; ?>
            </a>
            <div class="rando-card-body">
              <h3 class="rando-card-title">
                <a href="<?= htmlspecialchars($rando_url, ENT_QUOTES, 'UTF-8') ?>"
                   style="text-decoration:none;color:inherit">
                  <?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h3>
              <?php if ($summary): ?>
                <p class="rando-card-summary">
                  <?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?>
                </p>
              <?php endif; ?>

              <!-- Badges lisibilité -->
              <div class="rando-badges-row">
                <?php if ($dist_str): ?>
                  <span class="rando-badge">&#x1F4CD; <?= htmlspecialchars($dist_str, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($dur_str): ?>
                  <span class="rando-badge">&#x23F1; ~<?= htmlspecialchars($dur_str, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php
                  $diff_badge_class = 'rando-badge-diff-' . ($dif === 'difficile' ? 'difficile' : ($dif === 'moyen' ? 'moyen' : 'facile'));
                  $diff_badge_labels = ['facile' => 'Facile', 'moyen' => 'Moyen', 'difficile' => 'Difficile'];
                ?>
                <span class="rando-badge <?= $diff_badge_class ?>">
                  <?= htmlspecialchars($diff_badge_labels[$dif] ?? ucfirst($dif), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if (!empty($r['gpx_file']) || !empty($r['gpx_url'])): ?>
                  <span class="rando-badge rando-badge-gpx">&#x1F4CD; GPX</span>
                <?php endif; ?>
                <?php if (!empty($r['famille_score']) && (int)$r['famille_score'] >= 3): ?>
                  <span class="rando-badge rando-badge-fam">&#x1F46A; Famille</span>
                <?php endif; ?>
              </div>

              <div class="rando-card-infos">
                <?php if ($dist_str): ?>
                  <span class="rando-card-info">&#x1F4CD; <?= htmlspecialchars($dist_str, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($dur_str): ?>
                  <span class="rando-card-info">&#x23F1; <?= htmlspecialchars($dur_str, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="rando-card-info">
                  <span class="rando-card-stars" title="<?= htmlspecialchars($difficulte_labels[$dif] ?? $dif, ENT_QUOTES, 'UTF-8') ?>">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                      <span class="rando-card-star <?= $i <= $stars ? 'on' : 'off' ?>">&#x2605;</span>
                    <?php endfor; ?>
                  </span>
                </span>
              </div>

              <div class="rando-card-footer">
                <span class="rando-card-commune">
                  <?php if (!empty($r['commune'])): ?>
                    &#x1F4CD; <?= htmlspecialchars($r['commune'], ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </span>
                <a href="<?= htmlspecialchars($rando_url, ENT_QUOTES, 'UTF-8') ?>"
                   class="rando-card-cta">
                  Voir la fiche &#x2192;
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="randos-cta-section">
  <div class="container randos-cta-inner">
    <span class="randos-cta-icon">&#x1F97E;</span>
    <h2 class="randos-cta-title">
      <?= $is_logged_in ? 'Valide tes randonn&eacute;es en mission' : 'Rejoins la Zone pour valider tes randon&eacute;es' ?>
    </h2>
    <p class="randos-cta-sub">
      <?php if ($is_logged_in): ?>
        Chaque fiche de randonn&eacute;e est li&eacute;e &agrave; une mission Zone85.
        Pars en mission et rapporte des points pour ton clan.
      <?php else: ?>
        Inscription gratuite. Explore la Vend&eacute;e, valide des missions de randonn&eacute;e
        et contribue &agrave; la communaut&eacute; Zone85.
      <?php endif; ?>
    </p>
    <div class="randos-cta-btns">
      <?php if ($is_logged_in): ?>
        <a href="missions.php"    class="randos-btn-primary">&#x1F3AF; Voir les missions</a>
        <a href="communaute.php" class="randos-btn-outline">&#x1F465; La communauté</a>
      <?php else: ?>
        <a href="inscription.php" class="randos-btn-primary">&#x1F331; Rejoindre la Zone</a>
        <a href="concept.php"     class="randos-btn-outline">D&eacute;couvrir le concept</a>
      <?php endif; ?>
    </div>
  </div>
</section>


<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var el = document.getElementById('zone85RandosMap');
  if (el && !window.L) { el.innerHTML = '<div class="leaflet-fallback">Carte temporairement indisponible. Vérifiez la connexion ou la politique de sécurité.</div>'; }
  if (el && window.L) {
    var points = [];
    try { points = JSON.parse(el.dataset.points || '[]'); } catch(e) {}
    if (points.length) {
      var map = L.map(el, {scrollWheelZoom:false});
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);
      setTimeout(function(){ map.invalidateSize(); }, 250);
      var bounds = [];
      points.forEach(function(p){
        var m = L.marker([p.lat, p.lng]).addTo(map);
        m.bindPopup('<strong>'+String(p.title).replace(/</g,'&lt;')+'</strong><br><a href="'+p.url+'">Voir la rando</a>');
        bounds.push([p.lat, p.lng]);
      });
      map.fitBounds(bounds, {padding:[30,30], maxZoom:11});
    }
  }
  // Dropdowns de filtres Randos — V12.13
  document.querySelectorAll('[data-filter-trigger]').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      var menu = btn.closest('[data-filter-menu]');
      var isOpen = menu.classList.contains('open');
      document.querySelectorAll('[data-filter-menu].open').forEach(function(m){ m.classList.remove('open'); });
      menu.classList.toggle('open', !isOpen);
      btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    });
  });
  document.querySelectorAll('[data-filter-panel]').forEach(function(panel){
    panel.addEventListener('click', function(e){ e.stopPropagation(); });
  });
  document.querySelectorAll('[data-clear-filter]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var name = btn.getAttribute('data-clear-filter') + '[]';
      document.querySelectorAll('input[name="'+name+'"]').forEach(function(cb){ cb.checked = false; });
      document.getElementById('randosFilterForm').submit();
    });
  });
  document.addEventListener('click', function(){
    document.querySelectorAll('[data-filter-menu].open').forEach(function(m){ m.classList.remove('open'); });
  });

  document.addEventListener('click', function(e){
    if (!e.target.closest('a,button,.leaflet-container')) return;
    var s = document.createElement('span');
    s.className = 'rando-click-spark';
    s.textContent = '✦';
    s.style.left = e.clientX + 'px';
    s.style.top = e.clientY + 'px';
    document.body.appendChild(s);
    setTimeout(function(){ s.remove(); }, 800);
  }, true);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
