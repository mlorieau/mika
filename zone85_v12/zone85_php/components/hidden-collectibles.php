<?php
// ============================================================
// components/hidden-collectibles.php  V9.1b
// Objets cachés : position:absolute → scrollent avec la page.
// 0 HTML / 0 script si aucun objet actif sur cette page.
// ============================================================

$_slug = $hc_page_slug ?? ($GLOBALS['_hidden_collectibles_page'] ?? null);
if (!$_slug) return;
if (!function_exists('fetch_active_collectibles_for_page')) return;

$_hc_user    = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['user'] ?? null) : null;
$_hc_user_id = $_hc_user ? (int)$_hc_user['id'] : 0;
$_hc_logged  = $_hc_user_id > 0;

$_hc_items = fetch_active_collectibles_for_page($_slug, $_hc_user_id);
if (empty($_hc_items)) return;

$_hc_base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$_hc_csrf = csrf_token();
?>

<!-- ── Hidden Collectibles — <?= e($_slug) ?> ── -->
<div id="hc-page-layer">
<?php foreach ($_hc_items as $_hc): ?>
<div class="hidden-collectible"
     data-id="<?= (int)$_hc['id'] ?>"
     data-logged="<?= $_hc_logged ? '1' : '0' ?>"
     data-login-url="<?= $_hc_base ?>/login.php"
     data-top="<?= number_format((float)$_hc['position_top'], 2) ?>"
     data-left="<?= number_format((float)$_hc['position_left'], 2) ?>"
     data-top-mobile="<?= number_format((float)($_hc['position_top_mobile'] ?? $_hc['position_top']), 2) ?>"
     data-left-mobile="<?= number_format((float)($_hc['position_left_mobile'] ?? $_hc['position_left']), 2) ?>"
     data-size="<?= (int)($_hc['size_desktop'] ?? 48) ?>"
     data-size-mobile="<?= (int)($_hc['size_mobile'] ?? 40) ?>"
     aria-label="Objet caché : <?= e($_hc['title']) ?>"
     title="?"
     role="button" tabindex="0">
  <?php if (!empty($_hc['object_image'])): ?>
  <img src="<?= e(url($_hc['object_image'])) ?>" alt="objet caché" draggable="false" loading="lazy">
  <?php else: ?>
  <span class="hc-default-icon">🎯</span>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<style>
/* ── Calque page (position absolue, suit le scroll) ── */
#hc-page-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  pointer-events: none;
  z-index: 800;
  overflow: visible;
  /* hauteur fixée par JS après rendu */
}
.hidden-collectible {
  position: absolute;
  cursor: pointer;
  pointer-events: all;
  z-index: 800;
  transform: translate(-50%, -50%);
  transition: transform .18s cubic-bezier(.34,1.56,.64,1);
  animation: hc-float 3.5s ease-in-out infinite;
  user-select: none;
  -webkit-user-drag: none;
  /* Garantit fond transparent (PNG) */
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  outline: none !important;
  padding: 0 !important;
}
.hidden-collectible img,
.hidden-collectible .hc-default-icon {
  width: 100%;
  height: 100%;
  object-fit: contain;
  pointer-events: none;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.28));
  background: transparent;
}
.hidden-collectible .hc-default-icon {
  font-size: 2rem;
  display: block;
  line-height: 1;
}
.hidden-collectible:hover {
  transform: translate(-50%, -50%) scale(1.18);
  z-index: 801;
}
.hidden-collectible:focus-visible {
  outline: 2px dashed #ea5649;
  outline-offset: 4px;
}
.hidden-collectible.hc-found {
  animation: hc-pop-out .4s ease forwards;
  pointer-events: none;
}
@keyframes hc-float {
  0%,100% { transform: translate(-50%,-50%) translateY(0); }
  50%      { transform: translate(-50%,-50%) translateY(-6px); }
}
@keyframes hc-pop-out {
  0%   { transform: translate(-50%,-50%) scale(1); opacity:1; }
  50%  { transform: translate(-50%,-50%) scale(1.5); opacity:.7; }
  100% { transform: translate(-50%,-50%) scale(0); opacity:0; }
}
</style>

<script>
(function() {
  'use strict';
  var AJAX_URL = '<?= $_hc_base ?>/ajax/collectible-found.php';
  var CSRF     = '<?= e($_hc_csrf) ?>';
  var layer    = document.getElementById('hc-page-layer');
  var mq       = window.matchMedia('(max-width: 768px)');

  // ── Positionnement absolu : top = % de la hauteur totale de la page
  //    left = % de la largeur du viewport (unité vw)
  function positionAll() {
    if (!layer) return;
    // body doit être positionné pour que position:absolute fonctionne
    var bodyStyle = window.getComputedStyle(document.body).position;
    if (bodyStyle === 'static') {
      document.body.style.position = 'relative';
    }
    var pageH = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight,
      document.body.offsetHeight,
      document.documentElement.offsetHeight
    );
    layer.style.height = pageH + 'px';

    var isMobile = mq.matches;
    document.querySelectorAll('.hidden-collectible').forEach(function(el) {
      var topPct  = parseFloat(isMobile ? (el.dataset.topMobile  || el.dataset.top)  : el.dataset.top);
      var leftPct = parseFloat(isMobile ? (el.dataset.leftMobile || el.dataset.left) : el.dataset.left);
      var sz      = parseInt(isMobile   ? (el.dataset.sizeMobile || 40) : (el.dataset.size || 48));
      el.style.top    = (pageH * topPct / 100).toFixed(0) + 'px';
      el.style.left   = leftPct + 'vw';
      el.style.width  = sz + 'px';
      el.style.height = sz + 'px';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { setTimeout(positionAll, 50); });
  } else {
    setTimeout(positionAll, 50);
  }
  window.addEventListener('load', positionAll);
  window.addEventListener('resize', function() { setTimeout(positionAll, 100); });
  mq.addEventListener('change', positionAll);

  // ── Gestionnaires de clic ─────────────────────────────────
  document.querySelectorAll('.hidden-collectible').forEach(function(el) {
    el.addEventListener('click', handleClick);
    el.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        handleClick.call(this, e);
      }
    });
  });

  function handleClick() {
    var el     = this;
    var id     = parseInt(el.dataset.id);
    var logged = el.dataset.logged === '1';

    if (!logged) {
      if (window.HiddenHunt) {
        HiddenHunt.showLoginOverlay(el.dataset.loginUrl || '/login.php');
      } else {
        if (confirm('Connecte-toi pour valider ta trouvaille.\nAller à la page de connexion ?')) {
          location.href = el.dataset.loginUrl || '/login.php';
        }
      }
      return;
    }

    el.style.pointerEvents = 'none';

    var fd = new FormData();
    fd.append('collectible_id', id);
    fd.append('csrf_token', CSRF);

    fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          el.classList.add('hc-found');
          if (window.HiddenHunt) HiddenHunt.showFoundOverlay(data);
        } else if (data.reason === 'already_found') {
          el.classList.add('hc-found');
          if (window.HiddenHunt) HiddenHunt.showAlreadyFoundToast();
        } else if (data.reason === 'login_required') {
          location.href = el.dataset.loginUrl || '/login.php';
        } else {
          el.style.pointerEvents = '';
          console.warn('[HiddenHunt] ' + (data.message || 'Erreur'));
        }
      })
      .catch(function(err) {
        el.style.pointerEvents = '';
        console.error('[HiddenHunt] Fetch error', err);
      });
  }
})();
</script>
<script src="<?= $_hc_base ?>/assets/js/hidden-hunt.js"></script>
