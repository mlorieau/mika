/* ============================================
   CAP360 Router — Navigation SPA
   ============================================ */

'use strict';

CAP360.Router = (function () {

  const modules   = {};
  let   active    = null;
  let   container = null;

  function register(name, module) {
    modules[name] = module;
  }

  function boot() {
    container = document.getElementById('module-content');
    if (!container) { console.error('[Router] #module-content introuvable'); return; }

    window.addEventListener('hashchange', onHashChange);
    buildNav();

    const initial = (location.hash.slice(1) || 'cockpit').toLowerCase();
    navigate(initial, true);
  }

  function buildNav() {
    document.querySelectorAll('.nav-item[data-module]').forEach(item => {
      item.addEventListener('click', () => navigate(item.dataset.module));
    });
  }

  function navigate(name, instant) {
    if (!modules[name]) { console.warn('[Router] Module inconnu:', name); return; }

    const prev = active;

    // Update URL
    if (location.hash.slice(1) !== name) {
      history.pushState(null, '', '#' + name);
    }

    // Update sidebar active state
    document.querySelectorAll('.nav-item[data-module]').forEach(el => {
      el.classList.toggle('active', el.dataset.module === name);
    });

    // Transition out
    if (prev && !instant) {
      container.classList.add('page-exit');
      setTimeout(() => render(name), 120);
    } else {
      render(name);
    }

    active = name;
  }

  function render(name) {
    container.classList.remove('page-exit');
    container.innerHTML = '';

    const mod = modules[name];
    if (!mod) return;

    const view = document.createElement('div');
    view.className = 'module-view page-enter';
    container.appendChild(view);

    try {
      mod.mount(view);
    } catch (e) {
      console.error('[Router] Erreur mount module', name, e);
      view.innerHTML = `<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-title">Erreur de chargement</div><div class="empty-desc">${e.message}</div></div>`;
    }
  }

  function onHashChange() {
    const name = location.hash.slice(1).toLowerCase() || 'cockpit';
    if (name !== active) navigate(name);
  }

  function refresh() {
    if (active) render(active);
  }

  function getActive() { return active; }

  return { register, boot, navigate, refresh, getActive };

}());
