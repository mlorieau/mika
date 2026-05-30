/**
 * ZONE85 — Hidden Hunt V9
 * Overlay bravo + toast + gestion UX collectibles
 */
(function(global) {
  'use strict';

  // ── Overlay HTML injecté une fois ──────────────────────
  var _overlayEl = null;
  var _toastEl   = null;

  function _buildOverlay() {
    if (_overlayEl) return;
    var el = document.createElement('div');
    el.id = 'hh-overlay';
    el.innerHTML = [
      '<div id="hh-overlay-bg"></div>',
      '<div id="hh-overlay-box">',
      '  <button id="hh-overlay-close" aria-label="Fermer">✕</button>',
      '  <div id="hh-ov-img-wrap"><img id="hh-ov-img" src="" alt="" hidden></div>',
      '  <div id="hh-ov-icon">🎉</div>',
      '  <h2 id="hh-ov-title">Bravo !</h2>',
      '  <p id="hh-ov-msg"></p>',
      '  <div id="hh-ov-progress">',
      '    <div id="hh-ov-prog-bar-wrap"><div id="hh-ov-prog-bar"></div></div>',
      '    <div id="hh-ov-prog-text"></div>',
      '  </div>',
      '  <div id="hh-ov-xp" hidden></div>',
      '  <div id="hh-ov-badge" hidden></div>',
      '  <div id="hh-ov-actions">',
      '    <button id="hh-ov-continue">Continuer la chasse</button>',
      '    <a id="hh-ov-profil" href="profil.php" hidden>Voir mon profil →</a>',
      '  </div>',
      '</div>',
    ].join('');
    document.body.appendChild(el);
    _overlayEl = el;

    el.querySelector('#hh-overlay-close').addEventListener('click', closeOverlay);
    el.querySelector('#hh-overlay-bg').addEventListener('click', closeOverlay);
    el.querySelector('#hh-ov-continue').addEventListener('click', closeOverlay);

    // Styles
    var css = [
      '#hh-overlay { position:fixed; inset:0; z-index:9000; display:none; align-items:center; justify-content:center; }',
      '#hh-overlay.visible { display:flex; }',
      '#hh-overlay-bg { position:absolute; inset:0; background:rgba(10,20,35,.75); backdrop-filter:blur(4px); animation:hh-fade-in .25s ease; }',
      '#hh-overlay-box {',
      '  position:relative; z-index:1;',
      '  background:#fff; border-radius:24px;',
      '  box-shadow:0 40px 80px rgba(0,0,0,.35);',
      '  padding:40px 36px 32px;',
      '  max-width:440px; width:calc(100% - 32px);',
      '  text-align:center;',
      '  animation:hh-slide-up .35s cubic-bezier(.34,1.56,.64,1);',
      '}',
      '#hh-overlay-close {',
      '  position:absolute; top:14px; right:16px;',
      '  background:none; border:none; font-size:1.1rem; cursor:pointer;',
      '  color:#6b7f96; width:30px; height:30px; border-radius:50%;',
      '  display:flex; align-items:center; justify-content:center;',
      '  transition:background .15s;',
      '}',
      '#hh-overlay-close:hover { background:#f0ece7; color:#0c1e2e; }',
      '#hh-ov-img-wrap { min-height:0; }',
      '#hh-ov-img { max-width:200px; max-height:140px; border-radius:12px; object-fit:contain; margin:0 auto 12px; display:block; }',
      '#hh-ov-icon { font-size:2.8rem; line-height:1; margin-bottom:12px; }',
      '#hh-ov-title { font-size:1.5rem; font-weight:900; color:#0c1e2e; margin:0 0 10px; letter-spacing:-.5px; }',
      '#hh-ov-subtitle { font-size:1rem; font-weight:700; color:#3d5166; margin:0 0 8px; }',
      '#hh-ov-msg { font-size:.92rem; color:#3d5166; line-height:1.65; margin:0 0 20px; }',
      '#hh-ov-prog-bar-wrap { height:10px; background:#f0ece7; border-radius:8px; overflow:hidden; margin-bottom:8px; }',
      '#hh-ov-prog-bar { height:100%; background:linear-gradient(90deg,#c94038,#ea5649); border-radius:8px; width:0; transition:width .8s cubic-bezier(.22,1,.36,1); }',
      '#hh-ov-prog-text { font-size:.8rem; font-weight:700; color:#6b7f96; }',
      '#hh-ov-xp { margin:14px 0 4px; background:rgba(234,86,73,.1); border:1px solid rgba(234,86,73,.25); border-radius:10px; padding:10px 16px; font-size:.95rem; font-weight:900; color:#ea5649; }',
      '#hh-ov-badge { margin:10px 0 4px; background:rgba(42,157,92,.1); border:1px solid rgba(42,157,92,.25); border-radius:10px; padding:10px 16px; font-size:.88rem; font-weight:800; color:#1a7a42; }',
      '#hh-ov-actions { display:flex; flex-direction:column; gap:10px; margin-top:22px; }',
      '#hh-ov-continue { background:#ea5649; color:#fff; border:none; border-radius:10px; padding:13px 28px; font-family:inherit; font-size:.95rem; font-weight:800; cursor:pointer; transition:background .15s; }',
      '#hh-ov-continue:hover { background:#c94038; }',
      '#hh-ov-profil { color:#ea5649; font-weight:700; font-size:.88rem; text-decoration:none; }',
      '#hh-ov-profil:hover { text-decoration:underline; }',
      '@keyframes hh-fade-in { from{opacity:0} to{opacity:1} }',
      '@keyframes hh-slide-up { from{opacity:0;transform:translateY(32px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }',
    ].join('\n');
    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);
  }

  function _buildToast() {
    if (_toastEl) return;
    var el = document.createElement('div');
    el.id = 'hh-toast';
    el.textContent = 'Tu avais déjà trouvé cet objet !';
    document.body.appendChild(el);
    _toastEl = el;
    var css = [
      '#hh-toast {',
      '  position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px);',
      '  background:#0c1e2e; color:#fff; padding:12px 24px; border-radius:10px;',
      '  font-size:.88rem; font-weight:700; z-index:9100;',
      '  opacity:0; transition:opacity .25s, transform .25s;',
      '  pointer-events:none; white-space:nowrap;',
      '}',
      '#hh-toast.visible { opacity:1; transform:translateX(-50%) translateY(0); }',
    ].join('\n');
    var s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
  }

  // ── API publique ──────────────────────────────────────

  function showFoundOverlay(data) {
    _buildOverlay();
    var box  = _overlayEl;
    var img  = box.querySelector('#hh-ov-img');
    var icon = box.querySelector('#hh-ov-icon');
    var h2   = box.querySelector('#hh-ov-title');
    var msg  = box.querySelector('#hh-ov-msg');
    var bar  = box.querySelector('#hh-ov-prog-bar');
    var ptxt = box.querySelector('#hh-ov-prog-text');
    var xpEl = box.querySelector('#hh-ov-xp');
    var bdgEl= box.querySelector('#hh-ov-badge');
    var cont = box.querySelector('#hh-ov-continue');
    var profil = box.querySelector('#hh-ov-profil');

    // Image succès
    if (data.success_gif_url) {
      img.src = data.success_gif_url;
      img.hidden = false;
      icon.style.display = 'none';
    } else {
      img.hidden = true;
      icon.style.display = '';
      icon.textContent = data.completed ? '🏆' : '🎯';
    }

    h2.textContent  = data.title || (data.completed ? 'Mission accomplie !' : 'Bravo !');

    // Sous-titre objet
    var subtitleEl = box.querySelector('#hh-ov-subtitle');
    if (!subtitleEl) {
      subtitleEl = document.createElement('p');
      subtitleEl.id = 'hh-ov-subtitle';
      msg.parentNode.insertBefore(subtitleEl, msg);
    }
    subtitleEl.textContent = data.object_title ? data.object_title : '';
    subtitleEl.style.display = data.object_title ? '' : 'none';

    msg.textContent = data.message || '';

    // Progression
    var pct = (data.total > 0) ? Math.round(data.found / data.total * 100) : 0;
    ptxt.textContent = data.found + ' / ' + data.total + ' trouvé' + (data.found > 1 ? 's' : '');
    bar.style.width = '0%';
    setTimeout(function() { bar.style.width = pct + '%'; }, 80);

    // XP
    if (data.completed && data.xp_awarded > 0) {
      xpEl.textContent = '⚡ +' + data.xp_awarded + ' XP gagnés !';
      xpEl.hidden = false;
    } else {
      xpEl.hidden = true;
    }

    // Badge
    if (data.badge_awarded) {
      bdgEl.textContent = '🏅 Badge débloqué : ' + data.badge_awarded;
      bdgEl.hidden = false;
    } else {
      bdgEl.hidden = true;
    }

    // Boutons
    if (data.completed) {
      cont.textContent = 'Voir mon profil';
      profil.hidden = false;
    } else {
      cont.textContent = 'Continuer la chasse';
      profil.hidden = true;
    }

    _overlayEl.classList.add('visible');
    document.body.style.overflow = 'hidden';
  }

  function showAlreadyFoundToast() {
    _buildToast();
    _toastEl.classList.add('visible');
    setTimeout(function() { _toastEl.classList.remove('visible'); }, 2400);
  }

  function showLoginOverlay(loginUrl) {
    _buildOverlay();
    var h2  = _overlayEl.querySelector('#hh-ov-title');
    var msg = _overlayEl.querySelector('#hh-ov-msg');
    var img = _overlayEl.querySelector('#hh-ov-img');
    var icon= _overlayEl.querySelector('#hh-ov-icon');
    var bar = _overlayEl.querySelector('#hh-ov-progress');
    var cont= _overlayEl.querySelector('#hh-ov-continue');
    var profil = _overlayEl.querySelector('#hh-ov-profil');

    img.hidden = true;
    icon.style.display = '';
    icon.textContent = '🔐';
    h2.textContent  = 'Connecte-toi !';
    msg.textContent = 'Tu as l\'œil ! Rejoins la Zone85 pour valider ta trouvaille et gagner des XP.';
    bar.style.display = 'none';
    cont.textContent = 'Se connecter →';
    profil.hidden = true;

    var cleanup = function() {
      cont.removeEventListener('click', goLogin);
      bar.style.display = '';
    };
    var goLogin = function() { location.href = loginUrl || '/login.php'; };
    cont.addEventListener('click', goLogin);
    _overlayEl.addEventListener('hh-close', cleanup, {once:true});

    _overlayEl.classList.add('visible');
  }

  function closeOverlay() {
    if (!_overlayEl) return;
    _overlayEl.classList.remove('visible');
    document.body.style.overflow = '';
    _overlayEl.dispatchEvent(new CustomEvent('hh-close'));
  }

  // Fermer sur Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeOverlay();
  });

  // Export
  global.HiddenHunt = {
    showFoundOverlay:     showFoundOverlay,
    showAlreadyFoundToast:showAlreadyFoundToast,
    showLoginOverlay:     showLoginOverlay,
    closeOverlay:         closeOverlay,
  };

})(window);
