<?php
// ============================================================
// admin/seo.php — Vue d'ensemble SEO : articles + pages CMS
// ============================================================
$admin_current    = 'seo';
$admin_page_title = 'SEO — Vue d\'ensemble';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin.php';
require_admin();

$pdo = db();

// ── Fetch articles ────────────────────────────────────────────
$articles = [];
if ($pdo) {
    try {
        $articles = $pdo->query(
            "SELECT id, title, slug, excerpt, cover_image, body, status, rubrique
             FROM articles ORDER BY status ASC, created_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {}
}

// ── Fetch pages CMS ───────────────────────────────────────────
$pages = [];
if ($pdo) {
    try {
        $pages = $pdo->query(
            "SELECT id, title, slug, meta_title, meta_description, hero_image, status
             FROM pages ORDER BY status ASC, created_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {}
}

// ── SEO scoring helper ────────────────────────────────────────

/**
 * Returns ['status'=>'green|orange|red', 'detail'=>'…']
 */
function seo_check_title(string $title): array
{
    $l = mb_strlen($title);
    $s = ($l >= 50 && $l <= 60) ? 'green' : (($l >= 30 && $l <= 70) ? 'orange' : 'red');
    return ['status' => $s, 'detail' => $l . ' chars'];
}

function seo_check_desc(string $desc): array
{
    $l = mb_strlen($desc);
    $s = ($l >= 120 && $l <= 158) ? 'green' : (($l >= 60 && $l <= 200) ? 'orange' : 'red');
    return ['status' => $s, 'detail' => $l . ' chars'];
}

function seo_check_slug(string $slug): array
{
    if ($slug === '') return ['status' => 'red', 'detail' => 'Manquant'];
    $l = strlen($slug);
    $s = $l <= 60 ? 'green' : 'orange';
    return ['status' => $s, 'detail' => $l . ' chars'];
}

function seo_check_image(string $img): array
{
    return $img !== ''
        ? ['status' => 'green', 'detail' => 'Présente']
        : ['status' => 'orange', 'detail' => 'Absente'];
}

function seo_check_h2(string $body): array
{
    $count = preg_match_all('/<h2[^>]*>/i', $body);
    $words = str_word_count(strip_tags($body));
    if ($count >= 2) return ['status' => 'green', 'detail' => $count . ' H2'];
    if ($count === 1) return ['status' => 'orange', 'detail' => '1 H2'];
    $s = ($words > 300) ? 'red' : 'orange';
    return ['status' => $s, 'detail' => 'Aucun H2'];
}

function seo_check_alts(string $body): array
{
    preg_match_all('/<img[^>]*>/i', $body, $imgs);
    $total = count($imgs[0]);
    if ($total === 0) return ['status' => 'green', 'detail' => 'Pas d\'image'];
    $withAlt = 0;
    foreach ($imgs[0] as $img) {
        if (preg_match('/alt=["\'][^"\']+["\']/', $img)) $withAlt++;
    }
    if ($withAlt === $total) return ['status' => 'green', 'detail' => $withAlt . '/' . $total . ' ✓'];
    if ($withAlt > 0)        return ['status' => 'orange', 'detail' => $withAlt . '/' . $total . ' alt'];
    return ['status' => 'red', 'detail' => '0/' . $total . ' alt'];
}

/**
 * Compute score (0–100) from criteria array.
 * Each criterion contributes: green=2, orange=1, red=0 out of 2.
 */
function seo_score(array $criteria): int
{
    $pts = ['green' => 2, 'orange' => 1, 'red' => 0];
    $total = 0;
    foreach ($criteria as $c) {
        $total += $pts[$c['status']] ?? 0;
    }
    return (int) round($total / (count($criteria) * 2) * 100);
}

function score_badge(int $pct): string
{
    if ($pct >= 80) return '<span style="background:rgba(42,157,92,.12);color:#1a7a42;font-size:.72rem;font-weight:900;padding:3px 10px;border-radius:20px">' . $pct . '%</span>';
    if ($pct >= 50) return '<span style="background:rgba(201,150,42,.12);color:#8a6020;font-size:.72rem;font-weight:900;padding:3px 10px;border-radius:20px">' . $pct . '%</span>';
    return '<span style="background:rgba(234,86,73,.1);color:#c0392b;font-size:.72rem;font-weight:900;padding:3px 10px;border-radius:20px">' . $pct . '%</span>';
}

function dot(array $c): string
{
    $colors = ['green' => '#2a9d5c', 'orange' => '#C9962A', 'red' => '#ea5649'];
    $color  = $colors[$c['status']] ?? '#ccc';
    return '<span title="' . htmlspecialchars($c['detail'], ENT_QUOTES) . '"
                 style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . $color . ';cursor:help"></span>';
}

// ── Compute rows ──────────────────────────────────────────────

$article_rows = [];
foreach ($articles as $a) {
    $effectiveTitle = $a['title'] ?? '';
    $desc  = $a['excerpt']     ?? '';
    $slug  = $a['slug']        ?? '';
    $img   = $a['cover_image'] ?? '';
    $body  = $a['body']        ?? '';

    $criteria = [
        'Titre'       => seo_check_title($effectiveTitle),
        'Description' => seo_check_desc($desc),
        'Slug'        => seo_check_slug($slug),
        'Image'       => seo_check_image($img),
        'H2'          => seo_check_h2($body),
        'Alt imgs'    => seo_check_alts($body),
    ];
    $article_rows[] = [
        'id'       => $a['id'],
        'type'     => 'article',
        'title'    => $effectiveTitle,
        'slug'     => $slug,
        'status'   => $a['status'],
        'rubrique' => $a['rubrique'] ?? '',
        'score'    => seo_score($criteria),
        'criteria' => $criteria,
        'edit_url' => 'echo-edit.php?id=' . (int)$a['id'],
    ];
}

$page_rows = [];
foreach ($pages as $p) {
    $effectiveTitle = $p['meta_title'] ?: ($p['title'] ?? '');
    $desc  = $p['meta_description'] ?? '';
    $slug  = $p['slug']             ?? '';
    $img   = $p['hero_image']       ?? '';

    $criteria = [
        'Titre'       => seo_check_title($effectiveTitle),
        'Description' => seo_check_desc($desc),
        'Slug'        => seo_check_slug($slug),
        'Image'       => seo_check_image($img),
    ];
    $page_rows[] = [
        'id'       => $p['id'],
        'type'     => 'page',
        'title'    => $p['title'] ?? '',
        'slug'     => $slug,
        'status'   => $p['status'],
        'rubrique' => '',
        'score'    => seo_score($criteria),
        'criteria' => $criteria,
        'edit_url' => 'page-edit.php?id=' . (int)$p['id'],
    ];
}

// Stats globales
$all_rows = array_merge($article_rows, $page_rows);
$count_total = count($all_rows);
$count_green = count(array_filter($all_rows, fn($r) => $r['score'] >= 80));
$count_warn  = count(array_filter($all_rows, fn($r) => $r['score'] >= 50 && $r['score'] < 80));
$count_red   = count(array_filter($all_rows, fn($r) => $r['score'] < 50));
$avg_score   = $count_total > 0 ? (int)round(array_sum(array_column($all_rows, 'score')) / $count_total) : 0;

require_once __DIR__ . '/_admin-header.php';
?>

<style>
.seo-legend{display:flex;align-items:center;gap:6px;font-size:.72rem;font-weight:700;color:#6b7f96}
.seo-legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
</style>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="adm-page-header">
  <div>
    <h1 class="adm-page-title">🔍 Vue d'ensemble SEO</h1>
    <p class="adm-page-sub">Analyse automatique de tous les articles et pages du site — titre, description, slug, image, structure H2, alts.</p>
  </div>
  <div class="adm-page-actions">
    <a href="echos.php" class="btn-adm btn-adm-ghost">📰 Articles</a>
    <a href="pages.php" class="btn-adm btn-adm-ghost">📄 Pages</a>
  </div>
</div>

<!-- ── Statistiques globales ───────────────────────────────── -->
<div class="adm-stats" style="margin-bottom:28px">
  <div class="adm-stat">
    <div class="adm-stat-label">Score moyen</div>
    <div class="adm-stat-value <?= $avg_score >= 80 ? 'green' : ($avg_score >= 50 ? 'amber' : 'coral') ?>"><?= $avg_score ?>%</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">✅ Score ≥ 80%</div>
    <div class="adm-stat-value green"><?= $count_green ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">⚠️ Score 50–79%</div>
    <div class="adm-stat-value amber"><?= $count_warn ?></div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-label">❌ Score < 50%</div>
    <div class="adm-stat-value coral"><?= $count_red ?></div>
  </div>
</div>

<!-- ── Légende ────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;gap:16px;margin-bottom:14px;flex-wrap:wrap">
  <div class="seo-legend"><span class="seo-legend-dot" style="background:#2a9d5c"></span>Optimal</div>
  <div class="seo-legend"><span class="seo-legend-dot" style="background:#C9962A"></span>À améliorer</div>
  <div class="seo-legend"><span class="seo-legend-dot" style="background:#ea5649"></span>Problème</div>
  <div style="margin-left:auto;font-size:.72rem;color:#6b7f96">
    Les colonnes de points : <strong>Titre · Description · Slug · Image · H2 · Alts</strong> (articles) |
    <strong>Titre · Description · Slug · Image</strong> (pages)
  </div>
</div>

<!-- ── Articles ──────────────────────────────────────────── -->
<div class="adm-card" style="margin-bottom:20px">
  <p class="adm-card-title">Articles — Les Échos (<?= count($article_rows) ?>)</p>
  <?php if (empty($article_rows)): ?>
    <div class="adm-empty"><p>Aucun article.</p></div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Score</th>
          <th>Critères</th>
          <th>Titre</th>
          <th>Rubrique</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($article_rows as $row): ?>
        <tr>
          <td><?= score_badge($row['score']) ?></td>
          <td style="white-space:nowrap">
            <?php foreach ($row['criteria'] as $k => $c): ?>
              <?= dot($c) ?>
            <?php endforeach; ?>
          </td>
          <td>
            <span style="font-size:.84rem;font-weight:700;color:#0f1e2d"><?= e(mb_substr($row['title'], 0, 60)) ?><?= mb_strlen($row['title']) > 60 ? '…' : '' ?></span>
            <?php if ($row['slug']): ?>
              <span style="display:block;font-size:.72rem;color:#6b7f96;margin-top:2px">/<?= e($row['slug']) ?></span>
            <?php endif; ?>
          </td>
          <td><span style="font-size:.76rem;color:#6b7f96"><?= e($row['rubrique']) ?></span></td>
          <td>
            <span class="adm-badge <?= $row['status'] === 'published' ? 'badge-active' : 'badge-draft' ?>">
              <?= $row['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
            </span>
          </td>
          <td>
            <a href="<?= e($row['edit_url']) ?>" class="btn-adm btn-adm-ghost btn-adm-sm">Éditer</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Pages CMS ──────────────────────────────────────────── -->
<div class="adm-card">
  <p class="adm-card-title">Pages CMS (<?= count($page_rows) ?>)</p>
  <?php if (empty($page_rows)): ?>
    <div class="adm-empty">
      <div class="adm-empty-icon">📄</div>
      <p>Aucune page CMS. Les pages <strong>index.php</strong> et <strong>concept.php</strong> seront ajoutées automatiquement après le premier enregistrement de leurs métadonnées.</p>
    </div>
  <?php else: ?>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Score</th>
          <th>Critères</th>
          <th>Titre</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($page_rows as $row): ?>
        <tr>
          <td><?= score_badge($row['score']) ?></td>
          <td style="white-space:nowrap">
            <?php foreach ($row['criteria'] as $k => $c): ?>
              <?= dot($c) ?>
            <?php endforeach; ?>
          </td>
          <td>
            <span style="font-size:.84rem;font-weight:700;color:#0f1e2d"><?= e(mb_substr($row['title'], 0, 60)) ?></span>
            <?php if ($row['slug']): ?>
              <span style="display:block;font-size:.72rem;color:#6b7f96;margin-top:2px">/<?= e($row['slug']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <span class="adm-badge <?= $row['status'] === 'published' ? 'badge-active' : 'badge-draft' ?>">
              <?= $row['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
            </span>
          </td>
          <td>
            <a href="<?= e($row['edit_url']) ?>" class="btn-adm btn-adm-ghost btn-adm-sm">Éditer</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── PageSpeed tester global ─────────────────────────────── -->
<div class="adm-card">
  <p class="adm-card-title">⚡ Tester une URL sur PageSpeed</p>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="url" id="ps-global-url" class="adm-input" style="flex:1;min-width:260px"
           placeholder="https://www.zone85.fr/les-echos.php"
           value="<?= e(defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '') ?>">
    <button type="button" class="btn-adm btn-adm-primary" onclick="globalPageSpeed()">⚡ Analyser</button>
  </div>
  <div id="ps-global-result" style="margin-top:14px"></div>
</div>

<script>
function globalPageSpeed() {
    var url = document.getElementById('ps-global-url').value.trim();
    var res = document.getElementById('ps-global-result');
    if (!url || !url.startsWith('http')) { alert('Renseigne une URL complète (https://…)'); return; }
    res.innerHTML = '<p style="font-size:.82rem;color:#6b7f96">Analyse en cours…</p>';
    fetch('ajax/pagespeed.php?url=' + encodeURIComponent(url))
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.error) { res.innerHTML = '<p style="color:#c0392b;font-size:.82rem">Erreur : ' + d.error + '</p>'; return; }
            function sc(s){ return s>=90?'#1a7a42':s>=50?'#8a6020':'#c0392b'; }
            function sb(s){ return s>=90?'rgba(42,157,92,.1)':s>=50?'rgba(201,150,42,.1)':'rgba(234,86,73,.08)'; }
            var m = Math.round((d.mobile||0)*100), dsk = Math.round((d.desktop||0)*100);
            var h = '<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">'
                  + '<div style="background:'+sb(m)+';border-radius:10px;padding:16px 24px;text-align:center"><div style="font-size:2rem;font-weight:900;color:'+sc(m)+'">'+m+'</div><div style="font-size:.76rem;font-weight:700;color:#6b7f96">📱 Mobile</div></div>'
                  + '<div style="background:'+sb(dsk)+';border-radius:10px;padding:16px 24px;text-align:center"><div style="font-size:2rem;font-weight:900;color:'+sc(dsk)+'">'+dsk+'</div><div style="font-size:.76rem;font-weight:700;color:#6b7f96">🖥 Desktop</div></div>';
            if (d.cwv && Object.keys(d.cwv).length > 0) {
                var labels = {lcp:'LCP',cls:'CLS',fcp:'FCP',ttfb:'TTFB',inp:'INP'};
                h += '<div style="flex:1;min-width:200px"><div style="font-size:.76rem;font-weight:700;color:#6b7f96;margin-bottom:8px">Core Web Vitals (mobile)</div><div style="display:flex;flex-direction:column;gap:4px">';
                Object.keys(d.cwv).forEach(function(k){
                    h += '<div style="display:flex;justify-content:space-between;font-size:.8rem;padding:5px 10px;background:#f8f4ef;border-radius:6px"><span style="font-weight:600;color:#3d5166">'+(labels[k]||k)+'</span><span style="font-weight:700">'+d.cwv[k]+'</span></div>';
                });
                h += '</div></div>';
            }
            h += '</div>';
            res.innerHTML = h;
        })
        .catch(function(){ res.innerHTML = '<p style="color:#c0392b;font-size:.82rem">Erreur réseau.</p>'; });
}
</script>

<?php require_once __DIR__ . '/_admin-footer.php'; ?>
