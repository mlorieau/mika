/* ============================================
   CAP360 Projets — Objectifs & projets perso
   ============================================ */

'use strict';

CAP360.Projets = (function () {

  let _view = null;
  let _tab  = 'dashboard';

  const STATUS_LABELS = { active: 'En cours', planned: 'Planifié', done: 'Terminé', cancelled: 'Annulé', paused: 'En pause' };
  const STATUS_COLORS = { active: '#BF5AF2', planned: '#007AFF', done: '#30D158', cancelled: '#aeaeb2', paused: '#FF9500' };
  const CATEGORIES    = ['Financier', 'Personnel', 'Professionnel', 'Santé & Sport', 'Famille', 'Voyage', 'Formation', 'Autre'];

  function data() { return CAP360.Storage.get().projets; }

  /* ---- Stats for Cockpit ---- */

  function getStats() {
    const d     = data();
    const items = d.items || [];
    const active = items.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const late   = active.filter(p => p.dueDate && p.dueDate < new Date().toISOString().slice(0, 10));
    return { count: items.length, active: active.length, late: late.length };
  }

  /* ---- Tab switching ---- */

  function setTab(tab) {
    _tab = tab;
    _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    renderTabContent();
  }

  function renderTabContent() {
    const area = _view.querySelector('#projets-content');
    if (!area) return;
    if (_tab === 'dashboard') area.innerHTML = renderDashboard();
    else if (_tab === 'list') area.innerHTML = renderList();
  }

  /* ---- Dashboard ---- */

  function renderDashboard() {
    const d     = data();
    const stats = getStats();
    const items = d.items || [];
    const active = items.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const done   = items.filter(p => p.status === 'done');

    return `
      <div>
        <!-- KPIs -->
        <div class="kpi-grid">
          <div class="kpi-card"><div class="kpi-label">Objectifs actifs</div><div class="kpi-value">${stats.active}</div></div>
          <div class="kpi-card"><div class="kpi-label">En retard</div><div class="kpi-value ${stats.late>0?'neg':''}">${stats.late}</div></div>
          <div class="kpi-card"><div class="kpi-label">Terminés</div><div class="kpi-value pos">${done.length}</div></div>
          <div class="kpi-card"><div class="kpi-label">Total</div><div class="kpi-value">${stats.count}</div></div>
        </div>

        <!-- Active objectives -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Objectifs en cours</span>
            <button class="btn-primary btn-sm" onclick="P.openAdd()">+ Nouveau</button>
          </div>
          ${active.length === 0 ? '<div class="empty-state-sm">Aucun objectif en cours</div>' :
            active.map(p => renderProjectRow(p)).join('')}
        </div>

        ${done.length > 0 ? `
          <div class="card">
            <div class="card-header"><span class="card-title">Terminés</span></div>
            ${done.map(p => `
              <div class="tx-row" style="opacity:0.6">
                <span class="tx-icon">✅</span>
                <div class="tx-info">
                  <span class="tx-label">${p.name}</span>
                  <span class="tx-sub">${p.category || ''}</span>
                </div>
                <span class="badge" style="background:#30D15820;color:#30D158">Terminé</span>
              </div>
            `).join('')}
          </div>
        ` : ''}
      </div>
    `;
  }

  function renderProjectRow(p) {
    const today = new Date().toISOString().slice(0, 10);
    const isLate = p.dueDate && p.dueDate < today;
    const pct  = p.target > 0 ? Math.min(100, Math.round((p.current || 0) / p.target * 100)) : (p.progress || 0);
    const color = isLate ? '#FF3B30' : STATUS_COLORS[p.status] || '#BF5AF2';

    return `
      <div class="projets-item" onclick="P.openEdit('${p.id}')">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
          <div>
            <span style="font-weight:600">${p.name}</span>
            ${p.category ? `<span style="font-size:11px;color:var(--c-text-3);margin-left:8px">${p.category}</span>` : ''}
          </div>
          <span style="font-weight:700;color:${color}">${pct}%</span>
        </div>
        <div class="ck-metric-bar">
          <div class="ck-metric-fill" style="width:${pct}%;background:${color}"></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:12px;color:var(--c-text-2)">
          ${p.target ? `<span>${CAP360.Engine.currency(p.current||0)} / ${CAP360.Engine.currency(p.target)}</span>` : `<span>${p.description||''}</span>`}
          ${p.dueDate ? `<span ${isLate?'style="color:#FF3B30"':''}>${isLate ? '⚠️ ' : ''}Échéance: ${CAP360.Engine.dateShort(p.dueDate)}</span>` : ''}
        </div>
      </div>
    `;
  }

  /* ---- List ---- */

  function renderList() {
    const d = data();
    const items = (d.items || []).slice().sort((a, b) => {
      const ord = { active: 0, in_progress: 0, planned: 1, paused: 2, done: 3, cancelled: 4 };
      return (ord[a.status]||0) - (ord[b.status]||0);
    });

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="P.openAdd()">+ Nouvel objectif</button>
        </div>
        ${items.length === 0 ? `
          <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <div class="empty-title">Aucun objectif</div>
            <div class="empty-desc">Définissez vos objectifs de vie pour les suivre ici.</div>
            <button class="btn-primary" onclick="P.openAdd()">+ Créer un objectif</button>
          </div>
        ` : items.map(p => {
          const pct = p.target > 0 ? Math.min(100, Math.round((p.current||0)/p.target*100)) : (p.progress||0);
          return `
            <div class="card" style="margin-bottom:12px;cursor:pointer" onclick="P.openEdit('${p.id}')">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div>
                  <span style="font-weight:600">${p.name}</span>
                  <span class="badge" style="margin-left:8px;background:${STATUS_COLORS[p.status]||'#007AFF'}20;color:${STATUS_COLORS[p.status]||'#007AFF'}">${STATUS_LABELS[p.status]||p.status}</span>
                </div>
                <span style="font-size:18px;font-weight:700;color:${STATUS_COLORS[p.status]||'#BF5AF2'}">${pct}%</span>
              </div>
              ${p.target ? `
                <div class="ck-metric-bar" style="margin-bottom:6px">
                  <div class="ck-metric-fill" style="width:${pct}%;background:${STATUS_COLORS[p.status]||'#BF5AF2'}"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--c-text-2)">
                  <span>${CAP360.Engine.currency(p.current||0)}</span>
                  <span>${CAP360.Engine.currency(p.target)}</span>
                </div>
              ` : ''}
              ${p.description ? `<div style="font-size:12px;color:var(--c-text-3);margin-top:6px">${p.description}</div>` : ''}
              <div style="display:flex;gap:8px;margin-top:12px">
                <button class="btn-secondary btn-sm" onclick="event.stopPropagation();P.openEdit('${p.id}')">Modifier</button>
                <button class="btn-ghost btn-sm" onclick="event.stopPropagation();P.delete('${p.id}')">Supprimer</button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  /* ---- Modals ---- */

  function openAdd() {
    CAP360.UI.modal('Nouvel objectif', `
      <div class="form-group">
        <label class="form-label">Nom</label>
        <input type="text" id="pr-name" class="form-input" placeholder="Remplir le LEP, Voyage au Japon...">
      </div>
      <div class="form-group">
        <label class="form-label">Catégorie</label>
        <select id="pr-cat" class="form-select">
          ${CATEGORIES.map(c => `<option>${c}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select id="pr-status" class="form-select">
          <option value="active">En cours</option>
          <option value="planned">Planifié</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Montant actuel (€)</label>
          <input type="number" id="pr-current" class="form-input" placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">Objectif (€)</label>
          <input type="number" id="pr-target" class="form-input" placeholder="10 000">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Échéance</label>
        <input type="date" id="pr-due" class="form-input">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="pr-desc" class="form-input" rows="2"></textarea>
      </div>
    `, () => {
      const name    = document.getElementById('pr-name').value.trim();
      const cat     = document.getElementById('pr-cat').value;
      const status  = document.getElementById('pr-status').value;
      const current = parseFloat(document.getElementById('pr-current').value) || 0;
      const target  = parseFloat(document.getElementById('pr-target').value) || 0;
      const dueDate = document.getElementById('pr-due').value || null;
      const desc    = document.getElementById('pr-desc').value.trim();
      if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.addNested('projets', 'items', { name, category: cat, status, current, target, dueDate, description: desc, progress: 0 });
      CAP360.UI.toast('Objectif créé', 'success');
      renderTabContent();
    });
  }

  function openEdit(id) {
    const d = data();
    const p = (d.items || []).find(x => x.id === id);
    if (!p) return;
    CAP360.UI.modal('Modifier l\'objectif', `
      <div class="form-group">
        <label class="form-label">Nom</label>
        <input type="text" id="pr-name" class="form-input" value="${p.name}">
      </div>
      <div class="form-group">
        <label class="form-label">Catégorie</label>
        <select id="pr-cat" class="form-select">
          ${CATEGORIES.map(c => `<option ${c===p.category?'selected':''}>${c}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select id="pr-status" class="form-select">
          <option value="active" ${p.status==='active'?'selected':''}>En cours</option>
          <option value="planned" ${p.status==='planned'?'selected':''}>Planifié</option>
          <option value="paused" ${p.status==='paused'?'selected':''}>En pause</option>
          <option value="done" ${p.status==='done'?'selected':''}>Terminé</option>
          <option value="cancelled" ${p.status==='cancelled'?'selected':''}>Annulé</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Montant actuel (€)</label>
          <input type="number" id="pr-current" class="form-input" value="${p.current||0}">
        </div>
        <div class="form-group">
          <label class="form-label">Objectif (€)</label>
          <input type="number" id="pr-target" class="form-input" value="${p.target||0}">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Échéance</label>
        <input type="date" id="pr-due" class="form-input" value="${p.dueDate||''}">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="pr-desc" class="form-input" rows="2">${p.description||''}</textarea>
      </div>
    `, () => {
      const name    = document.getElementById('pr-name').value.trim();
      const cat     = document.getElementById('pr-cat').value;
      const status  = document.getElementById('pr-status').value;
      const current = parseFloat(document.getElementById('pr-current').value) || 0;
      const target  = parseFloat(document.getElementById('pr-target').value) || 0;
      const dueDate = document.getElementById('pr-due').value || null;
      const desc    = document.getElementById('pr-desc').value.trim();
      if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.updateNested('projets', 'items', id, { name, category: cat, status, current, target, dueDate, description: desc });
      CAP360.UI.toast('Objectif mis à jour', 'success');
      renderTabContent();
    });
  }

  function deleteItem(id) {
    if (!confirm('Supprimer cet objectif ?')) return;
    CAP360.Storage.removeNested('projets', 'items', id);
    renderTabContent();
  }

  /* ---- Mount ---- */

  function mount(container) {
    _view = container;
    _tab  = 'dashboard';

    _view.innerHTML = `
      <div class="module-header" style="--mod-accent:var(--c-projets)">
        <div class="module-header-inner">
          <h1 class="module-title">🎯 Projets & Objectifs</h1>
          <p class="module-subtitle">Suivez vos projets de vie</p>
        </div>
      </div>
      <div class="module-body">
        <div class="seg-control" style="margin-bottom:20px">
          <button class="seg-btn active" data-tab="dashboard" onclick="P.tab('dashboard')">Vue d'ensemble</button>
          <button class="seg-btn" data-tab="list" onclick="P.tab('list')">Tous les objectifs</button>
        </div>
        <div id="projets-content"></div>
      </div>
    `;

    renderTabContent();
  }

  /* ---- getHealth() — contrat Platform ---- */

  function getHealth() {
    const d     = data();
    const stats = getStats();
    const today = new Date().toISOString().slice(0, 10);
    const in60  = new Date(Date.now() + 60 * 86400000).toISOString().slice(0, 10);
    const items = d.items || [];
    const active = items.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const done   = items.filter(p => p.status === 'done');

    /* Score 0-100 */
    let score = 70;
    if (stats.late === 0 && stats.active > 0) score += 15;
    score -= stats.late * 15;
    if (done.length > 0) score += Math.min(15, done.length * 5);
    score = Math.max(0, Math.min(100, score));

    /* Alerts */
    const alerts = [];
    const late = active.filter(p => p.dueDate && p.dueDate < today);
    late.forEach(p => {
      const diff = Math.round((new Date() - new Date(p.dueDate)) / 86400000);
      alerts.push({ level: 'warning', message: 'Objectif "' + p.name + '" en retard de ' + diff + ' jour(s)', action: { label: 'Voir Projets', module: 'projets' } });
    });
    const soon = active.filter(p => p.dueDate && p.dueDate >= today && p.dueDate <= in60);
    soon.forEach(p => {
      const diff = Math.round((new Date(p.dueDate) - new Date()) / 86400000);
      alerts.push({ level: 'info', message: 'Échéance "' + p.name + '" dans ' + diff + ' jour(s)', action: { label: 'Voir Projets', module: 'projets' } });
    });

    /* Timeline */
    const timeline = active
      .filter(p => p.dueDate && p.dueDate >= today && p.dueDate <= in60)
      .map(p => {
        const pct = p.target > 0 ? Math.round((p.current || 0) / p.target * 100) : (p.progress || 0);
        return {
          date:   p.dueDate,
          type:   'milestone',
          label:  p.name + ' — ' + pct + '%',
          icon:   '🎯',
          color:  '#BF5AF2',
          amount: p.target || null,
        };
      });

    /* Story */
    const storyParts = [];
    if (stats.active > 0) {
      storyParts.push(stats.active + ' objectif(s) en cours.');
    }
    if (stats.late > 0) {
      storyParts.push(stats.late + ' en retard.');
    }
    if (done.length > 0) {
      storyParts.push(done.length + ' terminé(s).');
    }
    if (stats.active === 0 && done.length === 0) {
      storyParts.push('Aucun objectif défini.');
    }

    return {
      id:     'projets',
      label:  'Projets',
      icon:   '🎯',
      accent: '#BF5AF2',
      weight: 15,
      score,
      kpis: {
        count:  stats.count,
        active: stats.active,
        late:   stats.late,
        done:   done.length,
        items:  active.slice(0, 3),
      },
      alerts,
      timeline,
      story: storyParts.join(' ') || null,
    };
  }

  window.P = {
    tab:      t => { _tab = t; _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t)); renderTabContent(); },
    openAdd:  openAdd,
    openEdit: openEdit,
    delete:   deleteItem,
  };

  return { mount, getStats, getHealth };

}());
