// ============================================================
// Zone85 — PWA Install Modal V10.2
// Modal centré, overlay sombre/flou, animation fade-up
// Déclenché après inscription ou après N visites
// ============================================================
(function () {
  'use strict';

  var BASE  = (window.ZONE85_BASE || '').replace(/\/$/, '');
  var TRACK = BASE + '/ajax/pwa-install-track.php';

  // ── 1. Service Worker ────────────────────────────────────
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(BASE + '/service-worker.js', { scope: BASE + '/' })
        .then(function (reg) { console.debug('[SW] Registered:', reg.scope); })
        .catch(function (err) { console.warn('[SW] Failed:', err); });
    });
  }

  // ── 2. Plateforme ─────────────────────────────────────────
  var ua       = navigator.userAgent || '';
  var isIOS    = /iphone|ipad|ipod/i.test(ua);
  var isSafari = /safari/i.test(ua) && !/chrome/i.test(ua) && !/crios/i.test(ua);
  var platform = isIOS ? 'ios' : (/android/i.test(ua) ? 'android' : 'desktop');

  // ── 3. Helpers localStorage ───────────────────────────────
  function isInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
        || navigator.standalone === true
        || document.referrer.startsWith('android-app://');
  }
  function getVisits()  { return parseInt(localStorage.getItem('z85_visits') || '0', 10); }
  function incVisits()  { var n = getVisits() + 1; localStorage.setItem('z85_visits', n); return n; }
  function isDismissed() {
    var ts = parseInt(localStorage.getItem('z85_pwa_ts') || '0', 10);
    return ts > 0 && (Date.now() - ts) < 7 * 86400 * 1000;
  }
  function setDismissed() { localStorage.setItem('z85_pwa_ts', Date.now()); }

  // ── 4. beforeinstallprompt ────────────────────────────────
  var _prompt      = null;
  var _waitingShow = false;

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    _prompt = e;
    if (_waitingShow) { _waitingShow = false; showModal(); }
  });

  window.addEventListener('appinstalled', function () {
    setDismissed();
    trackInstall('installed');
    hideModal();
  });

  // ── 5. Décision affichage ─────────────────────────────────
  var visits    = incVisits();
  var THRESHOLD = 2;
  var force     = new URLSearchParams(location.search).get('pwa_install') === '1';

  function canShow() {
    return !isInstalled() && !isDismissed() && (force || visits >= THRESHOLD);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!canShow()) return;

    if (isIOS && isSafari) {
      // iOS : pas de beforeinstallprompt → afficher directement
      setTimeout(showModal, 1200);
    } else if (_prompt) {
      setTimeout(showModal, 800);
    } else {
      // Attendre le prompt jusqu'à 4s (surtout utile après inscription)
      _waitingShow = true;
      setTimeout(function () {
        if (_waitingShow) {
          _waitingShow = false;
          if (force && canShow()) showModal(); // afficher même sans prompt natif
        }
      }, 4000);
    }
  });

  // ── 6. Modal HTML ─────────────────────────────────────────
  var _modalEl = null;

  function showModal() {
    if (_modalEl || document.getElementById('z85-pwa-modal')) return;

    // CSS injecté une seule fois
    if (!document.getElementById('z85-pwa-css')) {
      var style = document.createElement('style');
      style.id  = 'z85-pwa-css';
      style.textContent = [
        '@keyframes z85FadeUp{from{opacity:0;transform:translateY(32px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}',
        '@keyframes z85FadeIn{from{opacity:0}to{opacity:1}}',
        '#z85-pwa-overlay{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;',
          'background:rgba(8,18,30,.75);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);',
          'animation:z85FadeIn .3s ease both}',
        '#z85-pwa-card{background:#fff;border-radius:22px;width:100%;max-width:420px;overflow:hidden;',
          'box-shadow:0 32px 80px rgba(0,0,0,.45);animation:z85FadeUp .4s cubic-bezier(.22,1,.36,1) .05s both}',
        '#z85-pwa-hero{background:linear-gradient(135deg,#0c1e2e 0%,#1a3a5c 100%);',
          'padding:32px 28px 24px;display:flex;align-items:center;gap:18px}',
        '#z85-pwa-icon{width:64px;height:64px;border-radius:15px;overflow:hidden;flex-shrink:0;',
          'background:#ea5649;display:flex;align-items:center;justify-content:center;',
          'font-size:1.8rem;font-weight:900;color:#fff;border:3px solid rgba(255,255,255,.15)}',
        '#z85-pwa-icon img{width:100%;height:100%;object-fit:cover}',
        '#z85-pwa-hero-text .t1{font-size:1.15rem;font-weight:900;color:#fff;letter-spacing:-.3px;margin-bottom:4px}',
        '#z85-pwa-hero-text .t2{font-size:.78rem;color:rgba(255,255,255,.55);line-height:1.5}',
        '#z85-pwa-body{padding:24px 28px 28px}',
        '#z85-pwa-body p{font-size:.92rem;color:#2d4057;line-height:1.65;margin:0 0 20px}',
        '#z85-pwa-body p strong{color:#0c1e2e}',
        '.z85-pwa-ios-steps{display:flex;flex-direction:column;gap:10px;margin-bottom:20px;',
          'background:#f8f4ef;border-radius:12px;padding:16px 18px}',
        '.z85-pwa-ios-step{display:flex;align-items:center;gap:12px;font-size:.84rem;color:#2d4057;font-weight:500}',
        '.z85-pwa-ios-step .ico{font-size:1.3rem;width:32px;text-align:center;flex-shrink:0}',
        '.z85-pwa-ios-step .step-num{background:#ea5649;color:#fff;border-radius:50%;',
          'width:20px;height:20px;display:flex;align-items:center;justify-content:center;',
          'font-size:.65rem;font-weight:800;flex-shrink:0}',
        '#z85-pwa-install-btn{width:100%;padding:14px 24px;background:#ea5649;color:#fff;border:none;',
          'border-radius:11px;font-family:inherit;font-size:.95rem;font-weight:800;cursor:pointer;',
          'letter-spacing:-.2px;transition:opacity .15s,transform .1s;margin-bottom:10px;display:block}',
        '#z85-pwa-install-btn:hover{opacity:.9;transform:translateY(-1px)}',
        '#z85-pwa-install-btn:active{transform:translateY(0)}',
        '#z85-pwa-later{width:100%;background:none;border:none;color:#8a9bb0;font-family:inherit;',
          'font-size:.84rem;font-weight:600;cursor:pointer;padding:8px;transition:color .15s}',
        '#z85-pwa-later:hover{color:#2d4057}',
        '@media(max-width:480px){#z85-pwa-card{border-radius:18px 18px 0 0;margin:auto 0 0}',
          '#z85-pwa-overlay{align-items:flex-end;padding:0}}',
      ].join('');
      document.head.appendChild(style);
    }

    // Construire le modal
    var div = document.createElement('div');
    div.id  = 'z85-pwa-overlay';

    var iconSrc = BASE + '/assets/img/pwa/icon-144.png';

    var iosBlock = isIOS && isSafari ? [
      '<div class="z85-pwa-ios-steps">',
        '<div class="z85-pwa-ios-step">',
          '<span class="step-num">1</span>',
          '<span>Appuie sur <strong>⬆ Partager</strong> en bas de Safari</span>',
        '</div>',
        '<div class="z85-pwa-ios-step">',
          '<span class="step-num">2</span>',
          '<span>Choisis <strong>Sur l\'écran d\'accueil</strong></span>',
        '</div>',
        '<div class="z85-pwa-ios-step">',
          '<span class="step-num">3</span>',
          '<span>Appuie sur <strong>Ajouter</strong> — c\'est fait !</span>',
        '</div>',
      '</div>',
    ].join('') : '';

    var installBtn = !isIOS ? [
      '<button id="z85-pwa-install-btn">',
        _prompt ? '📲 Installer Zone85' : '📲 Ajouter à l\'écran d\'accueil',
      '</button>',
    ].join('') : '<button id="z85-pwa-install-btn">C\'est parti !</button>';

    div.innerHTML = [
      '<div id="z85-pwa-card" role="dialog" aria-labelledby="z85-pwa-title" aria-modal="true">',
        '<div id="z85-pwa-hero">',
          '<div id="z85-pwa-icon">',
            '<img src="' + iconSrc + '" alt="Zone85" onerror="this.parentNode.innerHTML=\'Z85\'">',
          '</div>',
          '<div id="z85-pwa-hero-text">',
            '<div class="t1" id="z85-pwa-title">Installer Zone85</div>',
            '<div class="t2">Application gratuite · Aucun store requis</div>',
          '</div>',
        '</div>',
        '<div id="z85-pwa-body">',
          '<p>',
            'Installez <strong>Zone85</strong> en mode application sur votre smartphone ou PC ',
            'pour nous retrouver en un clic — sans passer par un navigateur.',
          '</p>',
          iosBlock,
          installBtn,
          '<button id="z85-pwa-later">Plus tard</button>',
        '</div>',
      '</div>',
    ].join('');

    document.body.appendChild(div);
    _modalEl = div;

    // Fermer sur clic overlay
    div.addEventListener('click', function (e) {
      if (e.target === div) dismissModal();
    });

    // Bouton Installer
    var btn = document.getElementById('z85-pwa-install-btn');
    if (btn) {
      btn.addEventListener('click', function () {
        if (_prompt) {
          var p = _prompt;
          _prompt = null;
          p.prompt();
          p.userChoice.then(function (r) {
            if (r.outcome === 'accepted') {
              setDismissed();
              trackInstall('accepted');
              hideModal();
            } else {
              _prompt = p; // remettre si refusé
              dismissModal();
            }
          });
        } else {
          // iOS ou desktop sans prompt : fermer proprement
          setDismissed();
          hideModal();
        }
      });
    }

    // Bouton Plus tard
    var later = document.getElementById('z85-pwa-later');
    if (later) later.addEventListener('click', dismissModal);

    // Fermer avec Escape
    document.addEventListener('keydown', onKeydown);
  }

  function dismissModal() {
    setDismissed();
    hideModal();
  }

  function hideModal() {
    document.removeEventListener('keydown', onKeydown);
    if (!_modalEl) return;
    // Fade out
    _modalEl.style.animation = 'z85FadeIn .2s ease reverse both';
    var card = _modalEl.querySelector('#z85-pwa-card');
    if (card) card.style.animation = 'z85FadeUp .2s ease reverse both';
    setTimeout(function () {
      if (_modalEl) { _modalEl.remove(); _modalEl = null; }
    }, 220);
  }

  function onKeydown(e) {
    if (e.key === 'Escape') dismissModal();
  }

  // ── 7. Tracking ───────────────────────────────────────────
  function trackInstall(evt) {
    try {
      var fd = new FormData();
      fd.append('event', evt);
      fd.append('platform', platform);
      fetch(TRACK, { method:'POST', body:fd, credentials:'same-origin' }).catch(function(){});
    } catch(e) {}
  }

  // ── 8. API publique ───────────────────────────────────────
  window.Zone85PWA = {
    platform:    platform,
    isInstalled: isInstalled,
    showModal:   showModal,
    hideModal:   hideModal,
    hasPrompt:   function () { return !!_prompt; },
  };

})();
