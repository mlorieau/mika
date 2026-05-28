<?php
$page_title       = 'Hall de la Zone';
$page_description = 'Le Hall de la Zone conserve les meilleures contributions, photos, KTC résolus, randos préférées et trophées de saison de la communauté vendéenne ZONE85.';
$page_canonical   = 'https://www.zone85.fr/hall.php';
$page_robots      = 'index,follow';
$page_og_image    = 'assets/img/ZONE852025.png';
$page_schema      = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'Accueil','item'=>'https://www.zone85.fr/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Hall de la Zone','item'=>'https://www.zone85.fr/hall.php'],
    ],
];
$current_page = 'hall';
require_once 'includes/config.php';
require_once 'includes/data.php';
require_once 'includes/functions.php';
$page_styles = '<style>

/* ============================================================
   HALL DE LA ZONE — Page CSS
============================================================ */

/* HERO */
.hall-hero {
  background: linear-gradient(160deg, #0c1e2e 0%, #12314e 55%, #163756 100%);
  padding: 120px 0 80px;
  position: relative;
  overflow: hidden;
}
.hall-hero::before {
  content: \'\';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.025\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}
.hall-hero::after {
  content: \'\';
  position: absolute;
  bottom: -80px;
  right: -80px;
  width: 400px;
  height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(201,150,42,.08) 0%, transparent 70%);
  pointer-events: none;
}
.hall-hero-inner { position: relative; z-index: 1; }
.hall-hero-icon { font-size: 3rem; margin-bottom: 18px; display: block; filter: drop-shadow(0 4px 16px rgba(201,150,42,.3)); }
.hall-hero h1 { font-size: clamp(2.2rem, 5.5vw, 3.4rem); font-weight: 900; color: #fff; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 16px; }
.hall-hero .hero-sub { font-size: 1.05rem; color: rgba(255,255,255,.62); max-width: 560px; line-height: 1.75; margin-bottom: 36px; }
.hall-filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.hall-pill { padding: 7px 18px; border-radius: 24px; font-size: .8rem; font-weight: 700; cursor: pointer; border: 2px solid rgba(255,255,255,.2); background: transparent; color: rgba(255,255,255,.65); font-family: \'Inter\', sans-serif; transition: all .2s; }
.hall-pill:hover { border-color: rgba(255,255,255,.5); color: #fff; }
.hall-pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }

/* SECTION SHARED */
.hall-section { padding: 84px 0; }
.hall-section-light { background: var(--beige-light); }
.hall-section-white { background: var(--white); }
.hall-section-beige { background: var(--beige); }
.hall-section-navy { background: linear-gradient(160deg, #0c1e2e 0%, #12314e 100%); }
.hall-section-title { display: flex; align-items: center; gap: 12px; font-size: clamp(1.5rem, 2.8vw, 2rem); font-weight: 900; color: var(--text); letter-spacing: -.5px; margin-bottom: 8px; }
.hall-section-title.light { color: #fff; }
.hall-section-title-icon { font-size: 1.6rem; flex-shrink: 0; }
.hall-section-sub { font-size: .95rem; color: var(--text-mid); line-height: 1.7; margin-bottom: 44px; max-width: 560px; }
.hall-section-sub.light { color: rgba(255,255,255,.55); }

/* ============================================================
   SECTION 1 — UNE DE LA ZONE (Featured)
============================================================ */
.une-section { background: var(--beige-light); }
.une-card { background: var(--navy-dark); border-radius: 16px; overflow: hidden; box-shadow: 0 8px 40px rgba(0,0,0,.18); display: grid; grid-template-columns: 60% 40%; }
.une-visual { background: linear-gradient(135deg, #0c1e2e 0%, #12314e 35%, #1d5a7a 65%, #0e4060 100%); min-height: 380px; position: relative; overflow: hidden; display: flex; align-items: flex-end; }
.une-visual::before { content: \'\'; position: absolute; inset: 0; background: linear-gradient(45deg, rgba(201,150,42,.12) 0%, transparent 50%, rgba(234,86,73,.08) 100%); }
.une-visual-texture { position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width=\'80\' height=\'80\' viewBox=\'0 0 80 80\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M0 0h40v40H0V0zm40 40h40v40H40V40z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); pointer-events: none; }
.une-visual-label { position: absolute; top: 20px; left: 20px; z-index: 2; }
.une-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--gold); color: #fff; font-size: .72rem; font-weight: 800; padding: 6px 14px; border-radius: 5px; letter-spacing: .08em; text-transform: uppercase; }
.une-visual-placeholder { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 6rem; opacity: .1; }
.une-content { padding: 44px 40px; display: flex; flex-direction: column; justify-content: center; gap: 20px; }
.une-content .une-badge-inline { align-self: flex-start; }
.une-content-title { font-size: 1.4rem; font-weight: 900; color: #fff; line-height: 1.25; letter-spacing: -.4px; }
.une-content-desc { font-size: .9rem; color: rgba(255,255,255,.6); line-height: 1.75; }
.une-content-author { font-size: .78rem; color: rgba(255,255,255,.4); font-weight: 600; display: flex; align-items: center; gap: 8px; }
.une-content-author::before { content: \'\'; display: inline-block; width: 20px; height: 1px; background: rgba(255,255,255,.2); }
.une-cta { align-self: flex-start; background: var(--primary); color: #fff; padding: 11px 22px; border-radius: 6px; font-size: .88rem; font-weight: 700; font-family: \'Inter\', sans-serif; border: none; cursor: pointer; text-decoration: none; transition: background .2s, transform .15s; display: inline-block; }
.une-cta:hover { background: var(--primary-dark); transform: translateY(-1px); }

/* ============================================================
   SECTION 2 — ILS ONT MARQUÉ LA ZONE
============================================================ */
.contributors-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
.contributor-card { background: var(--white); border-radius: 14px; padding: 24px 18px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--beige-dark); transition: all .25s; position: relative; overflow: hidden; }
.contributor-card::before { content: \'\'; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--primary); transform: scaleX(0); transition: transform .25s; }
.contributor-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.contributor-card:hover::before { transform: scaleX(1); }
.contributor-avatar { font-size: 2.2rem; margin-bottom: 12px; display: block; }
.contributor-pseudo { font-size: .9rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.contributor-xp { font-size: 1.05rem; font-weight: 900; color: var(--primary); margin: 8px 0 4px; }
.contributor-xp small { font-size: .65rem; font-weight: 600; color: var(--text-muted); }
.contributor-type { display: inline-block; background: rgba(234,86,73,.08); color: var(--primary); font-size: .65rem; font-weight: 800; padding: 3px 10px; border-radius: 20px; letter-spacing: .06em; text-transform: uppercase; margin-top: 6px; }

/* ============================================================
   SECTION 3 — PHOTOS QUI ONT FAIT PARLER
============================================================ */
.photos-section { background: var(--beige-light); }
.photo-filter-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 36px; }
.photo-pill { padding: 7px 16px; border-radius: 20px; font-size: .8rem; font-weight: 700; cursor: pointer; border: 2px solid var(--beige-dark); background: var(--white); color: var(--text-mid); font-family: \'Inter\', sans-serif; transition: all .2s; }
.photo-pill:hover { border-color: var(--primary); color: var(--primary); }
.photo-pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 36px; }
.photo-card { border-radius: 12px; overflow: hidden; background: var(--white); box-shadow: var(--shadow-sm); transition: all .25s; cursor: pointer; }
.photo-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.photo-thumb { aspect-ratio: 1; position: relative; overflow: hidden; }
.photo-thumb-bg { width: 100%; height: 100%; transition: transform .3s; }
.photo-card:hover .photo-thumb-bg { transform: scale(1.04); }
.photo-thumb-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.65) 0%, transparent 50%); opacity: 0; transition: opacity .25s; display: flex; align-items: flex-end; padding: 14px; }
.photo-card:hover .photo-thumb-overlay { opacity: 1; }
.photo-overlay-info { display: flex; align-items: center; justify-content: space-between; width: 100%; }
.photo-overlay-pseudo { font-size: .78rem; font-weight: 700; color: #fff; }
.photo-overlay-likes { font-size: .78rem; font-weight: 700; color: rgba(255,255,255,.85); display: flex; align-items: center; gap: 4px; }
.photo-meta { padding: 12px 14px; }
.photo-caption { font-size: .82rem; font-weight: 700; color: var(--text); margin-bottom: 6px; line-height: 1.3; }
.photo-meta-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.photo-author { font-size: .72rem; color: var(--text-muted); font-weight: 600; }
.photo-cat-badge { font-size: .62rem; font-weight: 800; letter-spacing: .07em; padding: 2px 8px; border-radius: 20px; background: rgba(18,49,78,.08); color: var(--navy-dark); text-transform: uppercase; }
.photo-date { font-size: .65rem; color: var(--text-muted); }
.photos-contribute { text-align: center; padding-top: 8px; }
.photos-contribute a { font-size: .88rem; font-weight: 700; color: var(--primary); text-decoration: none; transition: color .2s; }
.photos-contribute a:hover { color: var(--primary-dark); }

/* Photo gradient backgrounds */
.ph-navy-teal { background: linear-gradient(135deg, #12314e 0%, #1a6b7a 100%); }
.ph-beige-amber { background: linear-gradient(135deg, #d4b483 0%, #a07020 100%); }
.ph-green-teal { background: linear-gradient(135deg, #1e5c30 0%, #1a6b5a 100%); }
.ph-navy-primary { background: linear-gradient(135deg, #0c1e2e 0%, #ea5649 100%); }
.ph-olive-green { background: linear-gradient(135deg, #4a5c1a 0%, #2a7a40 100%); }
.ph-amber-orange { background: linear-gradient(135deg, #c9962a 0%, #c04f20 100%); }
.ph-navy-blue { background: linear-gradient(135deg, #12314e 0%, #1d5ca0 100%); }
.ph-teal-navy { background: linear-gradient(135deg, #1a8a7a 0%, #0c1e2e 100%); }

/* ============================================================
   SECTION 4 — KTC RÉSOLUS
============================================================ */
.ktc-section { background: linear-gradient(160deg, #0c1e2e 0%, #12314e 100%); }
.ktc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 36px; }
.ktc-card { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); border-radius: 14px; overflow: hidden; transition: all .25s; }
.ktc-card:hover { background: rgba(255,255,255,.09); transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,.3); }
.ktc-card-photo { height: 160px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.ktc-photo-bg { width: 100%; height: 100%; }
.ktc-photo-icon { position: absolute; font-size: 3.5rem; opacity: .18; }
.ktc-solved-badge { position: absolute; top: 14px; right: 14px; background: #1a7a40; color: #fff; font-size: .65rem; font-weight: 800; padding: 4px 11px; border-radius: 4px; letter-spacing: .08em; text-transform: uppercase; border: 1px solid rgba(255,255,255,.15); }
.ktc-card-body { padding: 20px 22px; }
.ktc-card-title { font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 12px; line-height: 1.3; }
.ktc-card-meta { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.ktc-meta-row { font-size: .78rem; color: rgba(255,255,255,.5); display: flex; align-items: center; gap: 8px; }
.ktc-meta-row strong { color: rgba(255,255,255,.8); font-weight: 700; }
.ktc-xp-badge { display: inline-flex; align-items: center; gap: 4px; background: rgba(201,150,42,.2); border: 1px solid rgba(201,150,42,.35); color: var(--gold); font-size: .75rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.ktc-card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 14px; border-top: 1px solid rgba(255,255,255,.08); }
.ktc-season { font-size: .68rem; color: rgba(255,255,255,.35); font-weight: 600; letter-spacing: .04em; }
.ktc-voir-link { font-size: .8rem; font-weight: 700; color: var(--primary); text-decoration: none; transition: color .2s; }
.ktc-voir-link:hover { color: var(--primary-light); }
.ktc-in-progress { text-align: center; margin-top: 4px; }
.ktc-in-progress a { font-size: .88rem; font-weight: 700; color: rgba(255,255,255,.6); text-decoration: none; transition: color .2s; }
.ktc-in-progress a:hover { color: #fff; }
.ktc-photo-1 { background: linear-gradient(135deg, #2b1a0a 0%, #6a3a10 100%); }
.ktc-photo-2 { background: linear-gradient(135deg, #0d2018 0%, #1e5c30 100%); }
.ktc-photo-3 { background: linear-gradient(135deg, #1a0a2a 0%, #4a2060 100%); }

/* ============================================================
   SECTION 5 — RANDOS PRÉFÉRÉES
============================================================ */
.randos-section { background: var(--beige); }
.rando-cards-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.rando-hall-card { background: var(--white); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--beige-dark); transition: all .25s; }
.rando-hall-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
.rando-photo { height: 160px; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.rando-photo-icon { font-size: 3.5rem; opacity: .18; position: absolute; }
.rando-difficulty { position: absolute; top: 14px; left: 14px; font-size: .65rem; font-weight: 800; padding: 4px 11px; border-radius: 4px; letter-spacing: .07em; text-transform: uppercase; }
.diff-facile { background: #d4f0e0; color: #1a6630; }
.diff-moyen { background: #fde8c0; color: #8a4f10; }
.diff-difficile { background: #fdd0cc; color: #8a1a10; }
.rando-rating { position: absolute; top: 14px; right: 14px; background: rgba(0,0,0,.55); color: #fff; font-size: .72rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 3px; }
.rando-hall-body { padding: 20px 22px; }
.rando-hall-title { font-size: .98rem; font-weight: 800; color: var(--text); margin-bottom: 10px; line-height: 1.3; }
.rando-hall-location { font-size: .78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 14px; display: flex; align-items: center; gap: 4px; }
.rando-hall-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 16px; }
.rando-stat { background: var(--beige); border-radius: 7px; padding: 8px 12px; font-size: .75rem; color: var(--text-mid); }
.rando-stat strong { display: block; font-size: .68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 2px; }
.rando-hall-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 14px; border-top: 1px solid var(--beige-dark); }
.rando-reviews { font-size: .75rem; color: var(--text-muted); font-weight: 600; }
.rando-voir-btn { font-size: .8rem; font-weight: 700; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 4px; transition: color .2s; }
.rando-voir-btn:hover { color: var(--primary-dark); }
.rando-bg-1 { background: linear-gradient(135deg, #1a6b5a 0%, #12314e 100%); }
.rando-bg-2 { background: linear-gradient(135deg, #0d2018 0%, #2a5a28 100%); }
.rando-bg-3 { background: linear-gradient(135deg, #12314e 0%, #1a6b7a 100%); }

/* ============================================================
   SECTION 6 — TROPHÉES SUR L\'ÉTAGÈRE
============================================================ */
.trophees-section { background: var(--beige); }
.trophy-shelf-wrap { position: relative; padding-bottom: 24px; }
.trophy-shelf-line { position: absolute; bottom: 0; left: -5%; right: -5%; height: 14px; background: linear-gradient(90deg, var(--beige-dark) 0%, #c8b99a 50%, var(--beige-dark) 100%); border-radius: 4px; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
.trophy-shelf-line::after { content: \'\'; position: absolute; bottom: -8px; left: 0; right: 0; height: 8px; background: linear-gradient(180deg, rgba(0,0,0,.08) 0%, transparent 100%); border-radius: 0 0 4px 4px; }
.trophy-shelf-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; align-items: end; padding-bottom: 28px; }
.trophy-shelf-card { background: var(--white); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-md); transition: transform .25s, box-shadow .25s; text-align: center; }
.trophy-shelf-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
.trophy-shelf-top { padding: 32px 24px 24px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.trophy-shelf-top.littoral-bg { background: linear-gradient(160deg, #0c1e2e 0%, #163756 100%); }
.trophy-shelf-top.marais-bg { background: linear-gradient(160deg, #2b1a0a 0%, #5a3e18 100%); }
.trophy-shelf-top.bocage-bg { background: linear-gradient(160deg, #0d2018 0%, #1e3d2b 100%); }
.trophy-shelf-medal { font-size: 2.8rem; filter: drop-shadow(0 4px 12px rgba(0,0,0,.2)); }
.trophy-shelf-season { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.5); }
.trophy-shelf-clan { font-size: 1.1rem; font-weight: 900; color: #fff; }
.trophy-shelf-trophees { font-size: .78rem; font-weight: 700; color: rgba(255,255,255,.5); }
.trophy-shelf-body { padding: 16px 20px; background: var(--white); }
.trophy-shelf-note { font-size: .82rem; color: var(--text-mid); line-height: 1.5; }
.trophy-current-note { text-align: center; margin-top: 44px; font-size: .88rem; color: var(--text-muted); font-weight: 600; }
.trophy-current-note strong { color: var(--text); }
.trophy-current-note .season-label { display: inline-block; background: var(--primary); color: #fff; font-size: .72rem; font-weight: 800; padding: 3px 10px; border-radius: 4px; margin: 0 4px; letter-spacing: .06em; }

/* ============================================================
   SECTION 7 — ARCHIVES
============================================================ */
.archives-section { background: var(--white); }
.archives-list { display: flex; flex-direction: column; gap: 0; border: 1px solid var(--beige-dark); border-radius: 12px; overflow: hidden; }
.archive-item { border-bottom: 1px solid var(--beige-dark); }
.archive-item:last-child { border-bottom: none; }
.archive-trigger { width: 100%; background: none; border: none; cursor: pointer; font-family: \'Inter\', sans-serif; padding: 0; text-align: left; display: block; }
.archive-trigger-inner { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; transition: background .2s; }
.archive-trigger:hover .archive-trigger-inner { background: var(--beige-light); }
.archive-trigger[aria-expanded="true"] .archive-trigger-inner { background: var(--beige-light); }
.archive-trigger-left { display: flex; align-items: center; gap: 14px; }
.archive-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
.archive-title { font-size: .95rem; font-weight: 800; color: var(--text); }
.archive-period { font-size: .78rem; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
.archive-chevron { font-size: .8rem; color: var(--text-muted); transition: transform .3s; flex-shrink: 0; }
.archive-trigger[aria-expanded="true"] .archive-chevron { transform: rotate(180deg); }
.archive-panel { display: none; padding: 0 24px 22px 48px; animation: fadeUp .25s ease; }
.archive-panel.open { display: block; }
.archive-detail { display: flex; gap: 24px; flex-wrap: wrap; }
.archive-stat { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--text-mid); }
.archive-stat-icon { font-size: .9rem; }
.archive-stat strong { font-weight: 700; color: var(--text); }
.archive-in-progress { font-size: .82rem; font-style: italic; color: var(--text-muted); }

/* ============================================================
   SECTION CTA FINAL
============================================================ */
.hall-cta-section { background: var(--navy-dark); padding: 96px 0; text-align: center; position: relative; overflow: hidden; }
.hall-cta-section::before { content: \'\'; position: absolute; top: -100px; left: 50%; transform: translateX(-50%); width: 600px; height: 300px; border-radius: 50%; background: radial-gradient(circle, rgba(234,86,73,.1) 0%, transparent 70%); pointer-events: none; }
.hall-cta-inner { position: relative; z-index: 1; max-width: 600px; margin: 0 auto; }
.hall-cta-title { font-size: clamp(1.6rem, 3.5vw, 2.4rem); font-weight: 900; color: #fff; letter-spacing: -.8px; line-height: 1.15; margin-bottom: 16px; }
.hall-cta-sub { font-size: .98rem; color: rgba(255,255,255,.6); line-height: 1.75; margin-bottom: 36px; }
.hall-cta-actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1024px) {
  .contributors-row { grid-template-columns: repeat(3, 1fr); }
  .photo-grid { grid-template-columns: repeat(2, 1fr); }
  .ktc-grid { grid-template-columns: repeat(2, 1fr); }
  .rando-cards-grid { grid-template-columns: repeat(2, 1fr); }
  .trophy-shelf-grid { grid-template-columns: repeat(3, 1fr); }
  .une-card { grid-template-columns: 1fr; }
  .une-visual { min-height: 260px; }
}
@media (max-width: 768px) {
  .hall-section { padding: 60px 0; }
  .contributors-row { grid-template-columns: repeat(2, 1fr); }
  .photo-grid { grid-template-columns: repeat(2, 1fr); }
  .ktc-grid { grid-template-columns: 1fr; }
  .rando-cards-grid { grid-template-columns: 1fr; }
  .trophy-shelf-grid { grid-template-columns: 1fr; }
  .archive-detail { flex-direction: column; gap: 10px; }
  .hall-cta-actions { flex-direction: column; align-items: center; }
}
@media (max-width: 480px) {
  .contributors-row { grid-template-columns: 1fr 1fr; }
  .photo-grid { grid-template-columns: 1fr; }
}
</style>';
require_once 'includes/header.php';
require_once 'includes/nav.php';
?>

<!-- ===================== HERO ===================== -->
<section class="hall-hero">
  <div class="container hall-hero-inner">
    <span class="hall-hero-icon">🏛️</span>
    <p class="overline-label" style="color:rgba(234,86,73,.85)">La mémoire de la Zone</p>
    <h1>Hall de la Zone</h1>
    <p class="hero-sub">Ici reposent les meilleures contributions, les mystères résolus, les randos préférées et les légendes de saison.</p>
    <div class="hall-filter-pills">
      <button class="hall-pill active">Tout</button>
      <button class="hall-pill">Photos</button>
      <button class="hall-pill">KTC</button>
      <button class="hall-pill">Randos</button>
      <button class="hall-pill">Enquêtes</button>
      <button class="hall-pill">Trophées</button>
    </div>
  </div>
</section>


<!-- ===================== SECTION 1 — UNE DE LA ZONE ===================== -->
<section class="hall-section une-section">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label">Mise en avant éditoriale</p>
      <h2 class="hall-section-title" style="font-size:clamp(1.5rem,2.8vw,2rem)">
        <span class="hall-section-title-icon">📰</span>
        Une de la Zone
      </h2>
      <p class="hall-section-sub">Le temps fort de la communauté ce mois-ci.</p>
    </div>

    <div class="une-card reveal">
      <div class="une-visual">
        <div class="une-visual-texture"></div>
        <div class="une-visual-placeholder">🌊</div>
        <div class="une-visual-label">
          <span class="une-badge">★ Une de la Zone</span>
        </div>
      </div>
      <div class="une-content">
        <div class="une-content-title">La Vendée de l'intérieur : regards croisés sur un été vendéen</div>
        <p class="une-content-desc">Ce mois-ci, 47 Zonautes ont capturé leur coin de Vendée. Voici les images qui nous ont coupé le souffle — paysages oubliés, faune sauvage, patrimoine bocager : la Vendée vue de l'intérieur.</p>
        <div class="une-content-author">
          Collecte de la communauté · Camp d'Été Zone85
        </div>
        <a href="missions.php" class="une-cta">Voir toute la galerie →</a>
      </div>
    </div>
  </div>
</section>


<!-- ===================== SECTION 2 — ILS ONT MARQUÉ LA ZONE ===================== -->
<section class="hall-section hall-section-white">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label">Saison en cours</p>
      <h2 class="hall-section-title">
        <span class="hall-section-title-icon">🌟</span>
        Ils ont marqué la Zone
      </h2>
      <p class="hall-section-sub">Les membres qui ont contribué le plus cette saison.</p>
    </div>

    <div class="contributors-row">
      <?php
      $delay = 0;
      foreach ($hall_contributors as $i => $member):
        $delay_style = $delay > 0 ? " style=\"transition-delay:{$delay}s\"" : '';
      ?>
      <div class="contributor-card reveal"<?= $delay_style ?>>
        <span class="contributor-avatar"><?= e($member['avatar']) ?></span>
        <div class="contributor-pseudo"><?= e($member['pseudo']) ?></div>
        <span class="<?= e($member['clan_slug']) ?>-chip-sm"><?= e($member['clan_label']) ?></span>
        <div class="contributor-xp">+<?= format_score($member['season_pts']) ?> pts <small>cette saison</small></div>
        <span class="contributor-type"><?= e($member['type']) ?></span>
      </div>
      <?php
        $delay = round($delay + 0.06, 2);
      endforeach;
      ?>
    </div>
  </div>
</section>


<!-- ===================== SECTION 3 — PHOTOS QUI ONT FAIT PARLER ===================== -->
<section class="hall-section photos-section">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label">Galerie communautaire</p>
      <h2 class="hall-section-title">
        <span class="hall-section-title-icon">📸</span>
        Les photos qui ont fait parler
      </h2>
      <p class="hall-section-sub">Les meilleures photos soumises par la communauté.</p>
    </div>

    <div class="photo-filter-row reveal">
      <button class="photo-pill active">Tout</button>
      <button class="photo-pill">Paysage</button>
      <button class="photo-pill">Patrimoine</button>
      <button class="photo-pill">Faune</button>
      <button class="photo-pill">KTC</button>
      <button class="photo-pill">Rando</button>
    </div>

    <div class="photo-grid reveal">

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-navy-teal" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🌅</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@MarcelBocat</span>
              <span class="photo-overlay-likes">❤️ 47</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Coucher sur la Baie de l'Aiguillon</div>
          <div class="photo-meta-row">
            <span class="photo-author">@MarcelBocat</span>
            <span class="photo-cat-badge">Paysage</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Juin 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-beige-amber" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🏛️</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@ÉlodieMJ</span>
              <span class="photo-overlay-likes">❤️ 38</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Le moulin de Sallertaine</div>
          <div class="photo-meta-row">
            <span class="photo-author">@ÉlodieMJ</span>
            <span class="photo-cat-badge">Patrimoine</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Mai 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-green-teal" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🦢</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@SophieVM</span>
              <span class="photo-overlay-likes">❤️ 29</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Héron cendré dans le marais</div>
          <div class="photo-meta-row">
            <span class="photo-author">@SophieVM</span>
            <span class="photo-cat-badge">Faune</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Avril 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-navy-primary" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🥐</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@GabinCM</span>
              <span class="photo-overlay-likes">❤️ 52</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Objet KTC #12 — révélé !</div>
          <div class="photo-meta-row">
            <span class="photo-author">@GabinCM</span>
            <span class="photo-cat-badge">KTC</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Juin 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-olive-green" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🌲</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@ThomasBV</span>
              <span class="photo-overlay-likes">❤️ 33</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">La forêt de Mervent en été</div>
          <div class="photo-meta-row">
            <span class="photo-author">@ThomasBV</span>
            <span class="photo-cat-badge">Rando</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Juillet 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-amber-orange" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🛞</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@MarcelBocat</span>
              <span class="photo-overlay-likes">❤️ 41</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Vieille roue de chariot bocager</div>
          <div class="photo-meta-row">
            <span class="photo-author">@MarcelBocat</span>
            <span class="photo-cat-badge">Patrimoine</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Mai 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-navy-blue" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🌇</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@PaulineLC</span>
              <span class="photo-overlay-likes">❤️ 27</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Coucher soleil Puy du Fou</div>
          <div class="photo-meta-row">
            <span class="photo-author">@PaulineLC</span>
            <span class="photo-cat-badge">Paysage</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Juillet 2025</div>
        </div>
      </div>

      <div class="photo-card">
        <div class="photo-thumb">
          <div class="photo-thumb-bg ph-teal-navy" style="height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.2">🌿</div>
          <div class="photo-thumb-overlay">
            <div class="photo-overlay-info">
              <span class="photo-overlay-pseudo">@AntoineDB</span>
              <span class="photo-overlay-likes">❤️ 19</span>
            </div>
          </div>
        </div>
        <div class="photo-meta">
          <div class="photo-caption">Tourbière du bocage</div>
          <div class="photo-meta-row">
            <span class="photo-author">@AntoineDB</span>
            <span class="photo-cat-badge">Faune</span>
          </div>
          <div class="photo-date" style="margin-top:4px">Juin 2025</div>
        </div>
      </div>

    </div>

    <div class="photos-contribute reveal">
      <a href="missions.php">→ Contribuer une photo</a>
    </div>
  </div>
</section>


<!-- ===================== SECTION 4 — KTC RÉSOLUS ===================== -->
<section class="hall-section ktc-section">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label" style="color:rgba(234,86,73,.8)">Objets identifiés</p>
      <h2 class="hall-section-title light">
        <span class="hall-section-title-icon">🥐</span>
        Les objets enfin identifiés — Kéto Kolé Tché
      </h2>
      <p class="hall-section-sub light">Les mystères résolus par la communauté. Bravo aux meilleurs enquêteurs.</p>
    </div>

    <div class="ktc-grid">

      <div class="ktc-card reveal">
        <div class="ktc-card-photo ktc-photo-1">
          <span class="ktc-photo-icon">🌾</span>
          <span class="ktc-solved-badge">RÉSOLU</span>
        </div>
        <div class="ktc-card-body">
          <div class="ktc-card-title">Le tranchet du marais</div>
          <div class="ktc-card-meta">
            <div class="ktc-meta-row">Identifié par <strong>@GabinCM</strong></div>
            <div class="ktc-meta-row">23 hypothèses proposées</div>
            <div><span class="ktc-xp-badge">+30 XP</span></div>
          </div>
          <div class="ktc-card-footer">
            <span class="ktc-season">Saison des Chemins Creux</span>
            <a href="missions.php" class="ktc-voir-link">Voir le dossier →</a>
          </div>
        </div>
      </div>

      <div class="ktc-card reveal" style="transition-delay:.08s">
        <div class="ktc-card-photo ktc-photo-2">
          <span class="ktc-photo-icon">🌿</span>
          <span class="ktc-solved-badge">RÉSOLU</span>
        </div>
        <div class="ktc-card-body">
          <div class="ktc-card-title">La serfouette bocagère</div>
          <div class="ktc-card-meta">
            <div class="ktc-meta-row">Identifié par <strong>@MarcelBocat</strong></div>
            <div class="ktc-meta-row">31 hypothèses proposées</div>
            <div><span class="ktc-xp-badge">+30 XP</span></div>
          </div>
          <div class="ktc-card-footer">
            <span class="ktc-season">Saison du Réveil</span>
            <a href="missions.php" class="ktc-voir-link">Voir le dossier →</a>
          </div>
        </div>
      </div>

      <div class="ktc-card reveal" style="transition-delay:.16s">
        <div class="ktc-card-photo ktc-photo-3">
          <span class="ktc-photo-icon">🍶</span>
          <span class="ktc-solved-badge">RÉSOLU</span>
        </div>
        <div class="ktc-card-body">
          <div class="ktc-card-title">La baratte à beurre vendéenne</div>
          <div class="ktc-card-meta">
            <div class="ktc-meta-row">Identifié par <strong>@ÉlodieMJ</strong></div>
            <div class="ktc-meta-row">18 hypothèses proposées</div>
            <div><span class="ktc-xp-badge">+30 XP</span></div>
          </div>
          <div class="ktc-card-footer">
            <span class="ktc-season">Camp d'Été Zone85</span>
            <a href="missions.php" class="ktc-voir-link">Voir le dossier →</a>
          </div>
        </div>
      </div>

    </div>

    <div class="ktc-in-progress reveal">
      <a href="missions.php">KTC en cours : objet mystère #14 →</a>
    </div>
  </div>
</section>


<!-- ===================== SECTION 5 — RANDOS PRÉFÉRÉES ===================== -->
<section class="hall-section randos-section">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label">Validées par la communauté</p>
      <h2 class="hall-section-title">
        <span class="hall-section-title-icon">🥾</span>
        Les randos préférées des Zonautes
      </h2>
      <p class="hall-section-sub">Les meilleures fiches rando commentées par la communauté.</p>
    </div>

    <div class="rando-cards-grid">

      <div class="rando-hall-card reveal">
        <div class="rando-photo rando-bg-1">
          <span class="rando-photo-icon">🌊</span>
          <span class="rando-difficulty diff-facile">Facile</span>
          <span class="rando-rating">⭐ 4.8</span>
        </div>
        <div class="rando-hall-body">
          <div class="rando-hall-title">La Marche des Marais</div>
          <div class="rando-hall-location">📍 La Tranche-sur-Mer</div>
          <div class="rando-hall-stats">
            <div class="rando-stat"><strong>Distance</strong>12 km</div>
            <div class="rando-stat"><strong>Durée</strong>3h30</div>
            <div class="rando-stat"><strong>Dénivelé</strong>+30 m</div>
            <div class="rando-stat"><strong>Avis</strong>28 avis</div>
          </div>
          <div class="rando-hall-footer">
            <span class="rando-reviews">28 commentaires</span>
            <a href="missions.php" class="rando-voir-btn">Voir la rando →</a>
          </div>
        </div>
      </div>

      <div class="rando-hall-card reveal" style="transition-delay:.08s">
        <div class="rando-photo rando-bg-2">
          <span class="rando-photo-icon">🌲</span>
          <span class="rando-difficulty diff-moyen">Moyen</span>
          <span class="rando-rating">⭐ 4.6</span>
        </div>
        <div class="rando-hall-body">
          <div class="rando-hall-title">Les Chemins Creux du Bocage</div>
          <div class="rando-hall-location">📍 Pouzauges</div>
          <div class="rando-hall-stats">
            <div class="rando-stat"><strong>Distance</strong>18 km</div>
            <div class="rando-stat"><strong>Durée</strong>5h</div>
            <div class="rando-stat"><strong>Dénivelé</strong>+320 m</div>
            <div class="rando-stat"><strong>Avis</strong>19 avis</div>
          </div>
          <div class="rando-hall-footer">
            <span class="rando-reviews">19 commentaires</span>
            <a href="missions.php" class="rando-voir-btn">Voir la rando →</a>
          </div>
        </div>
      </div>

      <div class="rando-hall-card reveal" style="transition-delay:.16s">
        <div class="rando-photo rando-bg-3">
          <span class="rando-photo-icon">⛵</span>
          <span class="rando-difficulty diff-facile">Facile</span>
          <span class="rando-rating">⭐ 4.9</span>
        </div>
        <div class="rando-hall-body">
          <div class="rando-hall-title">Tour de l'Île de Noirmoutier</div>
          <div class="rando-hall-location">📍 Noirmoutier</div>
          <div class="rando-hall-stats">
            <div class="rando-stat"><strong>Distance</strong>25 km</div>
            <div class="rando-stat"><strong>Durée</strong>6h30</div>
            <div class="rando-stat"><strong>Dénivelé</strong>+15 m</div>
            <div class="rando-stat"><strong>Avis</strong>34 avis</div>
          </div>
          <div class="rando-hall-footer">
            <span class="rando-reviews">34 commentaires</span>
            <a href="missions.php" class="rando-voir-btn">Voir la rando →</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ===================== SECTION 6 — TROPHÉES SUR L'ÉTAGÈRE ===================== -->
<section class="hall-section trophees-section">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label">Palmarès des saisons</p>
      <h2 class="hall-section-title">
        <span class="hall-section-title-icon">🏆</span>
        Les trophées sur l'étagère
      </h2>
      <p class="hall-section-sub">Les clans qui ont remporté les saisons précédentes.</p>
    </div>

    <div class="trophy-shelf-wrap reveal">
      <div class="trophy-shelf-grid">
        <?php foreach ($season_trophies as $trophy):
          $_t_clan    = $clans[$trophy['winner_clan']] ?? null;
          $_t_count   = $_t_clan ? (int)$_t_clan['trophies'] : 0;
          $_t_label   = $_t_count . ' trophée' . ($_t_count > 1 ? 's' : '');
        ?>
        <div class="trophy-shelf-card">
          <div class="trophy-shelf-top <?= e($trophy['winner_clan']) ?>-bg">
            <span class="trophy-shelf-medal"><?= e($trophy['medal']) ?></span>
            <span class="trophy-shelf-season"><?= e($trophy['season']) ?></span>
            <div class="trophy-shelf-clan"><?= e($trophy['winner_name']) ?></div>
            <div class="trophy-shelf-trophees"><?= e($_t_label) ?></div>
          </div>
          <div class="trophy-shelf-body">
            <p class="trophy-shelf-note"><?= e($trophy['main_mission'] ?? '') ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="trophy-shelf-line"></div>
    </div>

    <div class="trophy-current-note reveal">
      Saison en cours : <span class="season-label"><?= e($active_season['title']) ?></span> · <strong>Résultats le <?= e($active_season['end_date']) ?></strong>
    </div>
  </div>
</section>


<!-- ===================== SECTION 7 — ARCHIVES ===================== -->
<section class="hall-section archives-section">
  <div class="container">
    <div class="section-header reveal">
      <p class="overline-label">L'histoire de Zone85</p>
      <h2 class="hall-section-title">
        <span class="hall-section-title-icon">📚</span>
        Archives
      </h2>
      <p class="hall-section-sub">L'histoire de Zone85, saison par saison.</p>
    </div>

    <div class="archives-list reveal">
      <?php foreach ($season_trophies as $season_arc):
        $_arc_color = $clans[$season_arc['winner_clan']]['color'] ?? 'var(--primary)';
      ?>
      <div class="archive-item">
        <button class="archive-trigger" aria-expanded="false" onclick="toggleArchive(this)">
          <div class="archive-trigger-inner">
            <div class="archive-trigger-left">
              <span class="archive-dot" style="background:<?= e($_arc_color) ?>"></span>
              <div>
                <div class="archive-title"><?= e($season_arc['season']) ?></div>
                <div class="archive-period"><?= e($season_arc['main_mission'] ?? '') ?></div>
              </div>
            </div>
            <span class="archive-chevron">▼</span>
          </div>
        </button>
        <div class="archive-panel">
          <?php if (!empty($season_arc['winner_name'])): ?>
          <div class="archive-detail">
            <span class="archive-stat"><span class="archive-stat-icon">🏆</span> <span>Vainqueur : <strong><?= e($season_arc['winner_name']) ?></strong></span></span>
            <span class="archive-stat"><span class="archive-stat-icon">📊</span> <span><strong><?= e((string)($season_arc['contributions'] ?? '')) ?></strong> contributions</span></span>
            <span class="archive-stat"><span class="archive-stat-icon">🗺️</span> <span>Grande mission : <strong><?= e($season_arc['main_mission'] ?? '') ?></strong></span></span>
          </div>
          <?php else: ?>
          <p class="archive-in-progress">En cours d'archivage…</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ===================== CTA FINAL ===================== -->
<section class="hall-cta-section">
  <div class="container">
    <div class="hall-cta-inner reveal">
      <h2 class="hall-cta-title">Ta contribution ici demain ?</h2>
      <p class="hall-cta-sub">Participe aux missions, envoie tes photos, résous les KTC, donne ton avis sur les randos. Les meilleures contributions rejoignent le Hall.</p>
      <div class="hall-cta-actions">
        <a href="missions.php" class="btn btn-primary btn-lg">Voir les missions →</a>
        <a href="inscription.php" class="btn btn-outline btn-lg">Rejoindre Zone85 →</a>
      </div>
    </div>
  </div>
</section>


<?php
$page_scripts = '<script>
  function toggleArchive(btn) {
    var expanded = btn.getAttribute(\'aria-expanded\') === \'true\';
    btn.setAttribute(\'aria-expanded\', !expanded);
    var panel = btn.parentElement.querySelector(\'.archive-panel\');
    if (panel) panel.classList.toggle(\'open\', !expanded);
  }

  document.querySelectorAll(\'.hall-pill\').forEach(function(pill) {
    pill.addEventListener(\'click\', function() {
      document.querySelectorAll(\'.hall-pill\').forEach(function(p) { p.classList.remove(\'active\'); });
      pill.classList.add(\'active\');
    });
  });

  document.querySelectorAll(\'.photo-pill\').forEach(function(pill) {
    pill.addEventListener(\'click\', function() {
      document.querySelectorAll(\'.photo-pill\').forEach(function(p) { p.classList.remove(\'active\'); });
      pill.classList.add(\'active\');
    });
  });
</script>';
require_once 'includes/footer.php';
?>
