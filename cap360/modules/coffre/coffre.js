/* ============================================
   CAP360 Coffre-fort — Documents importants
   ============================================ */

'use strict';

CAP360.Coffre = (function () {

  let _view    = null;
  let _filter  = 'all';
  let _search  = '';

  const CATEGORIES = [
    { id: 'assurance',  label: 'Assurances',     icon: '🛡️' },
    { id: 'bancaire',   label: 'Banque',          icon: '🏦' },
    { id: 'immobilier', label: 'Immobilier',      icon: '🏠' },
    { id: 'vehicule',   label: 'Véhicules',       icon: '🚗' },
    { id: 'sante',      label: 'Santé',           icon: '💊' },
    { id: 'admin',      label: 'Administration',  icon: '📋' },
    { id: 'fiscal',     label: 'Fiscal',          icon: '💰' },
    { id: 'contrat',    label: 'Contrats',        icon: '📑' },
    { id: 'autre',      label: 'Autre',           icon: '📄' },
  ];

  const DOC_ICONS = { assurance: '🛡️', bancaire: '🏦', immobilier: '🏠', vehicule: '🚗', sante: '💊', admin: '📋', fiscal: '💰', contrat: '📑', autre: '📄' };

  function data() { return CAP360.Storage.get().coffre; }

  /* ---- Stats for Cockpit ---- */

  function getStats() {
    const d    = data();
    const docs = d.documents || [];
    const today = new Date().toISOString().slice(0, 10);
    const in30  = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
    const expiring = docs.filter(doc => doc.expiryDate && doc.expiryDate >= today && doc.expiryDate <= in30);
    const expired  = docs.filter(doc => doc.expiryDate && doc.expiryDate < today);
    return { count: docs.length, expiringSoon: expiring.length, expired: expired.length };
  }

  /* ---- Render ---- */

  function filteredDocs() {
    const d = data();
    let docs = d.documents || [];
    if (_filter !== 'all') docs = docs.filter(doc => doc.category === _filter);
    if (_search) {
      const q = _search.toLowerCase();
      docs = docs.filter(doc => doc.name.toLowerCase().includes(q) || (doc.provider||'').toLowerCase().includes(q) || (doc.notes||'').toLowerCase().includes(q));
    }
    return docs.sort((a, b) => (a.expiryDate || '9999') > (b.expiryDate || '9999') ? 1 : -1);
  }

  function render() {
    const area = _view.querySelector('#coffre-list');
    if (!area) return;
    const docs  = filteredDocs();
    const today = new Date().toISOString().slice(0, 10);
    const in30  = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);

    area.innerHTML = docs.length === 0 ? `
      <div class="empty-state">
        <div class="empty-icon">🗄️</div>
        <div class="empty-title">Aucun document</div>
        <div class="empty-desc">Référencez vos documents importants : assurances, contrats, garanties...</div>
        <button class="btn-primary" onclick="CF.openAdd()">+ Ajouter un document</button>
      </div>
    ` : `
      <div class="coffre-grid">
        ${docs.map(doc => {
          const cat      = CATEGORIES.find(c => c.id === doc.category) || CATEGORIES[CATEGORIES.length - 1];
          const expired  = doc.expiryDate && doc.expiryDate < today;
          const expiring = doc.expiryDate && doc.expiryDate >= today && doc.expiryDate <= in30;
          let badgeCls = 'badge-info', badgeText = '';
          if (expired)  { badgeCls = 'badge-danger'; badgeText = 'Expiré'; }
          else if (expiring) { badgeCls = 'badge-warning'; badgeText = 'Expire bientôt'; }
          else if (doc.expiryDate) { badgeCls = 'badge-success'; badgeText = 'Valide'; }
          return `
            <div class="coffre-card" onclick="CF.openEdit('${doc.id}')">
              <div class="coffre-card-icon">${cat.icon}</div>
              <div class="coffre-card-body">
                <div class="coffre-card-name">${doc.name}</div>
                <div class="coffre-card-meta">
                  <span class="badge badge-ghost">${cat.label}</span>
                  ${doc.provider ? `<span class="coffre-card-provider">${doc.provider}</span>` : ''}
                </div>
                ${doc.expiryDate ? `
                  <div style="margin-top:8px;display:flex;align-items:center;gap:8px">
                    <span class="badge ${badgeCls}">${badgeText || CAP360.Engine.dateShort(doc.expiryDate)}</span>
                    <span style="font-size:11px;color:var(--c-text-3)">${CAP360.Engine.dateShort(doc.expiryDate)}</span>
                  </div>
                ` : ''}
                ${doc.notes ? `<div style="font-size:11px;color:var(--c-text-3);margin-top:6px;line-height:1.4">${doc.notes}</div>` : ''}
              </div>
              <div class="coffre-card-actions">
                ${doc.url ? `<a class="btn-ghost btn-sm" href="${doc.url}" target="_blank" onclick="event.stopPropagation()">🔗</a>` : ''}
                <button class="btn-ghost btn-sm" onclick="event.stopPropagation();CF.delete('${doc.id}')">✕</button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  /* ---- Modal ---- */

  function openAdd() {
    CAP360.UI.modal('Nouveau document', buildForm(), () => saveForm(null));
  }

  function openEdit(id) {
    const doc = (data().documents || []).find(x => x.id === id);
    if (!doc) return;
    CAP360.UI.modal('Modifier le document', buildForm(doc), () => saveForm(id));
  }

  function buildForm(doc) {
    return `
      <div class="form-group">
        <label class="form-label">Nom du document</label>
        <input type="text" id="cf-name" class="form-input" placeholder="Assurance habitation MMA..." value="${doc ? doc.name : ''}">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select id="cf-cat" class="form-select">
            ${CATEGORIES.map(c => `<option value="${c.id}" ${doc && doc.category===c.id?'selected':''}>${c.icon} ${c.label}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Organisme / Fournisseur</label>
          <input type="text" id="cf-provider" class="form-input" placeholder="AXA, Crédit Mutuel..." value="${doc ? (doc.provider||'') : ''}">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">N° de contrat / Référence</label>
          <input type="text" id="cf-ref" class="form-input" placeholder="12345678" value="${doc ? (doc.ref||'') : ''}">
        </div>
        <div class="form-group">
          <label class="form-label">Date d'expiration</label>
          <input type="date" id="cf-expiry" class="form-input" value="${doc ? (doc.expiryDate||'') : ''}">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Lien (URL ou chemin)</label>
        <input type="url" id="cf-url" class="form-input" placeholder="https://... ou chemin vers le fichier" value="${doc ? (doc.url||'') : ''}">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="cf-notes" class="form-input" rows="3" placeholder="Numéro de téléphone, adresse, informations importantes...">${doc ? (doc.notes||'') : ''}</textarea>
      </div>
    `;
  }

  function saveForm(id) {
    const name     = document.getElementById('cf-name').value.trim();
    const category = document.getElementById('cf-cat').value;
    const provider = document.getElementById('cf-provider').value.trim();
    const ref      = document.getElementById('cf-ref').value.trim();
    const expiry   = document.getElementById('cf-expiry').value || null;
    const url      = document.getElementById('cf-url').value.trim() || null;
    const notes    = document.getElementById('cf-notes').value.trim();

    if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }

    const item = { name, category, provider, ref, expiryDate: expiry, url, notes, icon: DOC_ICONS[category] || '📄' };
    if (id) {
      CAP360.Storage.updateNested('coffre', 'documents', id, item);
      CAP360.UI.toast('Document mis à jour', 'success');
    } else {
      CAP360.Storage.addNested('coffre', 'documents', item);
      CAP360.UI.toast('Document ajouté', 'success');
    }
    render();
  }

  function deleteDoc(id) {
    if (!confirm('Supprimer ce document ?')) return;
    CAP360.Storage.removeNested('coffre', 'documents', id);
    CAP360.UI.toast('Document supprimé');
    render();
  }

  /* ---- Mount ---- */

  function mount(container) {
    _view   = container;
    _filter = 'all';
    _search = '';

    const stats = getStats();

    _view.innerHTML = `
      <div class="module-header" style="--mod-accent:var(--c-coffre)">
        <div class="module-header-inner">
          <h1 class="module-title">📁 Coffre-fort</h1>
          <p class="module-subtitle">Vos documents importants en un seul endroit</p>
        </div>
        ${stats.expired > 0 || stats.expiringSoon > 0 ? `
          <div class="alert-card alert-${stats.expired?'danger':'warning'}" style="margin-top:12px">
            ${stats.expired ? `<strong>⚠️ ${stats.expired} document(s) expiré(s)</strong><br>` : ''}
            ${stats.expiringSoon ? `<span>⏰ ${stats.expiringSoon} document(s) expire(nt) dans 30 jours</span>` : ''}
          </div>
        ` : ''}
      </div>
      <div class="module-body">

        <!-- Toolbar -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
          <input type="text" class="form-input" placeholder="Rechercher..." style="max-width:240px"
            oninput="CF.search(this.value)" value="${_search}">
          <div class="seg-control">
            <button class="seg-btn active" data-cat="all" onclick="CF.filter('all')">Tous (${stats.count})</button>
            ${CATEGORIES.map(c => {
              const count = (data().documents||[]).filter(d => d.category === c.id).length;
              return count > 0 ? `<button class="seg-btn" data-cat="${c.id}" onclick="CF.filter('${c.id}')">${c.icon} ${c.label} (${count})</button>` : '';
            }).join('')}
          </div>
          <button class="btn-primary" onclick="CF.openAdd()" style="margin-left:auto">+ Ajouter un document</button>
        </div>

        <div id="coffre-list"></div>

      </div>
    `;

    render();
  }

  /* ---- getHealth() — contrat Platform ---- */

  function getHealth() {
    const d     = data();
    const stats = getStats();
    const docs  = d.documents || [];
    const today = new Date().toISOString().slice(0, 10);
    const in30  = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
    const in60  = new Date(Date.now() + 60 * 86400000).toISOString().slice(0, 10);

    /* Expired and expiring */
    const expired  = docs.filter(doc => doc.expiryDate && doc.expiryDate < today);
    const expiring = docs.filter(doc => doc.expiryDate && doc.expiryDate >= today && doc.expiryDate <= in30);

    /* Score 0-100 */
    let score = docs.length === 0 ? 70 : 100;
    score -= expired.length * 20;
    score -= expiring.length * 8;
    score = Math.max(0, Math.min(100, score));

    /* Alerts */
    const alerts = [];
    expired.forEach(doc => {
      alerts.push({ level: 'danger', message: '"' + doc.name + '" expiré' + (doc.expiryDate ? ' le ' + doc.expiryDate : ''), action: { label: 'Voir Coffre', module: 'coffre' } });
    });
    expiring.forEach(doc => {
      const diff = Math.round((new Date(doc.expiryDate) - new Date()) / 86400000);
      alerts.push({ level: 'warning', message: '"' + doc.name + '" expire dans ' + diff + ' jour(s)', action: { label: 'Voir Coffre', module: 'coffre' } });
    });

    /* Timeline */
    const timeline = docs
      .filter(doc => doc.expiryDate && doc.expiryDate >= today && doc.expiryDate <= in60)
      .map(doc => ({
        date:   doc.expiryDate,
        type:   'document',
        label:  'Expiration : ' + doc.name + (doc.provider ? ' (' + doc.provider + ')' : ''),
        icon:   doc.icon || '📄',
        color:  '#5AC8FA',
        amount: null,
      }));

    /* Story */
    const storyParts = [];
    storyParts.push(docs.length + ' document(s) dans le coffre.');
    if (expired.length > 0) storyParts.push(expired.length + ' expiré(s).');
    if (expiring.length > 0) storyParts.push(expiring.length + ' expire(nt) dans 30 jours.');

    return {
      id:     'coffre',
      label:  'Coffre-fort',
      icon:   '📁',
      accent: '#5AC8FA',
      weight: 10,
      score,
      kpis: {
        count:        docs.length,
        expiringSoon: stats.expiringSoon,
        expired:      stats.expired,
      },
      alerts,
      timeline,
      story: storyParts.join(' ') || null,
    };
  }

  window.CF = {
    openAdd:  openAdd,
    openEdit: openEdit,
    delete:   deleteDoc,
    filter:   (cat) => {
      _filter = cat;
      _view.querySelectorAll('.seg-btn[data-cat]').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
      render();
    },
    search:   (q) => { _search = q; render(); },
  };

  return { mount, getStats, getHealth };

}());
