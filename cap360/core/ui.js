/* ============================================
   CAP360 UI — Utilitaires d'interface
   ============================================ */

'use strict';

CAP360.UI = (function () {

  let _currentOverlay = null;

  /* ---- Toast notifications ---- */

  function toast(message, type = 'default', duration = 3000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = `toast${type !== 'default' ? ' toast-' + type : ''}`;
    el.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '⚠' : '·'}</span> ${message}`;
    container.appendChild(el);

    setTimeout(() => {
      el.style.animation = 'toastOut 300ms ease forwards';
      setTimeout(() => el.remove(), 300);
    }, duration);
  }

  /* ---- Modal dialog ---- */

  function modal(title, bodyHTML, onConfirm, opts = {}) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal${opts.large ? ' modal-lg' : ''}${opts.xl ? ' modal-xl' : ''}">
        <div class="modal-header">
          <h2 class="modal-title">${title}</h2>
          <button class="modal-close" id="modal-close-btn" aria-label="Fermer">✕</button>
        </div>
        <div class="modal-body">
          ${bodyHTML}
        </div>
        <div class="modal-footer">
          ${opts.extra || ''}
          <button class="btn btn-ghost" id="modal-cancel-btn">${opts.cancelLabel || 'Annuler'}</button>
          <button class="btn btn-primary" id="modal-confirm-btn">${opts.confirmLabel || 'Enregistrer'}</button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);
    _currentOverlay = overlay;

    function close() {
      overlay.style.animation = 'overlayIn 200ms ease reverse forwards';
      setTimeout(() => { overlay.remove(); if (_currentOverlay === overlay) _currentOverlay = null; }, 200);
    }

    overlay.querySelector('#modal-close-btn').onclick   = close;
    overlay.querySelector('#modal-cancel-btn').onclick  = close;
    overlay.querySelector('#modal-confirm-btn').onclick = () => {
      const result = onConfirm();
      if (result !== false) close();
    };

    overlay.addEventListener('click', e => {
      if (e.target === overlay) close();
    });

    overlay.addEventListener('keydown', e => {
      if (e.key === 'Escape') close();
      if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
        const result = onConfirm();
        if (result !== false) close();
      }
    });

    // Focus first input
    setTimeout(() => {
      const first = overlay.querySelector('input, select, textarea');
      if (first) first.focus();
    }, 100);

    return { close };
  }

  /* ---- Confirm dialog ---- */

  function confirm(message, onConfirm) {
    return modal('Confirmation', `<p style="font-size:15px;line-height:1.5;color:var(--c-text-2)">${message}</p>`, onConfirm, {
      confirmLabel: 'Confirmer',
      cancelLabel: 'Annuler',
    });
  }

  function closeModal() {
    if (_currentOverlay) {
      _currentOverlay.style.animation = 'overlayIn 200ms ease reverse forwards';
      setTimeout(() => { if (_currentOverlay) { _currentOverlay.remove(); _currentOverlay = null; } }, 200);
    }
  }

  return { toast, modal, confirm, closeModal };

}());
