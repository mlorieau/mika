/* ============================================
   CAP360 Maison — Gestion du logement
   ============================================ */

'use strict';

CAP360.Maison = (function () {

  let _view = null;
  let _tab  = 'dashboard';

  const STATUS_LABELS = { active: 'En cours', in_progress: 'En cours', planned: 'Planifié', done: 'Terminé', paused: 'En pause' };
  const STATUS_COLORS = { active: '#FF9500', in_progress: '#FF9500', planned: '#007AFF', done: '#30D158', paused: '#aeaeb2' };
  const ROOM_TYPES    = ['Cuisine', 'Salon', 'Chambre principale', 'Chambre 2', 'Salle de bain', 'WC', 'Garage', 'Cave', 'Jardin', 'Bureau', 'Autre'];

  function data() { return CAP360.Storage.get().maison; }

  /* ---- Stats for Cockpit ---- */

  function getStats() {
    const d = data();
    const projects   = d.projects || [];
    const active     = projects.filter(p => p.status === 'active' || p.status === 'in_progress');
    const totalBudget = active.reduce((s, p) => s + (p.budget || 0), 0);
    const totalSpent  = active.reduce((s, p) => s + (p.spent || 0), 0);
    const pct = totalBudget > 0 ? Math.round(totalSpent / totalBudget * 100) : 0;
    return { projectCount: projects.length, activeCount: active.length, totalBudget, totalSpent, pct };
  }

  /* ---- Tab switching ---- */

  function setTab(tab) {
    _tab = tab;
    _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    renderTabContent();
  }

  function renderTabContent() {
    const area = _view.querySelector('#maison-content');
    if (!area) return;
    if (_tab === 'dashboard') area.innerHTML = renderDashboard();
    else if (_tab === 'projects') area.innerHTML = renderProjects();
    else if (_tab === 'rooms')    area.innerHTML = renderRooms();
    attachHandlers();
  }

  /* ---- Dashboard ---- */

  function renderDashboard() {
    const d    = data();
    const stats = getStats();
    const projects = d.projects || [];
    const active   = projects.filter(p => p.status === 'active' || p.status === 'in_progress');
    const done     = projects.filter(p => p.status === 'done');

    return `
      <div class="maison-dash">
        <!-- KPIs -->
        <div class="kpi-grid">
          <div class="kpi-card"><div class="kpi-label">Projets actifs</div><div class="kpi-value">${stats.activeCount}</div></div>
          <div class="kpi-card"><div class="kpi-label">Budget total</div><div class="kpi-value">${CAP360.Engine.currencyShort(stats.totalBudget)}</div></div>
          <div class="kpi-card"><div class="kpi-label">Dépensé</div><div class="kpi-value neg">${CAP360.Engine.currencyShort(stats.totalSpent)}</div></div>
          <div class="kpi-card"><div class="kpi-label">Terminés</div><div class="kpi-value pos">${done.length}</div></div>
        </div>

        <!-- Active projects -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Projets en cours</span>
            <button class="btn-primary btn-sm" onclick="M.openAddProject()">+ Nouveau</button>
          </div>
          ${active.length === 0 ? '<div class="empty-state-sm">Aucun projet en cours — <a href="#" onclick="M.openAddProject();return false">en créer un</a></div>' : active.map(p => renderProjectCard(p)).join('')}
        </div>

        <!-- All projects summary -->
        ${projects.length > 0 ? `
          <div class="card">
            <div class="card-header"><span class="card-title">Tous les projets</span></div>
            ${projects.map(p => `
              <div class="tx-row">
                <span class="tx-icon">🏗️</span>
                <div class="tx-info">
                  <span class="tx-label">${p.name}</span>
                  <span class="tx-sub">${p.room || ''}</span>
                </div>
                <span class="badge" style="background:${STATUS_COLORS[p.status]||'#007AFF'}20;color:${STATUS_COLORS[p.status]||'#007AFF'}">${STATUS_LABELS[p.status]||p.status}</span>
              </div>
            `).join('')}
          </div>
        ` : ''}
      </div>
    `;
  }

  function renderProjectCard(p) {
    const pct = p.budget > 0 ? Math.min(100, Math.round((p.spent || 0) / p.budget * 100)) : 0;
    const color = pct > 80 ? '#FF3B30' : pct > 60 ? '#FF9500' : '#30D158';
    return `
      <div class="maison-project-card" onclick="M.openEditProject('${p.id}')">
        <div class="maison-project-head">
          <span class="maison-project-name">${p.name}</span>
          <span class="maison-project-pct" style="color:${color}">${pct}%</span>
        </div>
        ${p.room ? `<div style="font-size:12px;color:var(--c-text-2);margin-bottom:8px">${p.room}</div>` : ''}
        <div class="ck-metric-bar">
          <div class="ck-metric-fill" style="width:${pct}%;background:${color}"></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:var(--c-text-2)">
          <span>Budget ${CAP360.Engine.currency(p.budget || 0)}</span>
          <span>Dépensé ${CAP360.Engine.currency(p.spent || 0)}</span>
        </div>
        ${p.description ? `<div style="font-size:12px;color:var(--c-text-3);margin-top:6px">${p.description}</div>` : ''}
      </div>
    `;
  }

  /* ---- Projects list ---- */

  function renderProjects() {
    const d  = data();
    const projects = (d.projects || []).slice().sort((a, b) => {
      const ord = { active: 0, in_progress: 0, planned: 1, paused: 2, done: 3 };
      return (ord[a.status]||0) - (ord[b.status]||0);
    });

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="M.openAddProject()">+ Nouveau projet</button>
        </div>
        ${projects.length === 0 ? `
          <div class="empty-state">
            <div class="empty-icon">🏗️</div>
            <div class="empty-title">Aucun projet</div>
            <div class="empty-desc">Créez votre premier projet de travaux ou d'amélioration.</div>
            <button class="btn-primary" onclick="M.openAddProject()">+ Créer un projet</button>
          </div>
        ` : `<div class="maison-projects-grid">${projects.map(p => `
          <div class="card maison-project-card" onclick="M.openEditProject('${p.id}')">
            <div class="maison-project-head">
              <span class="maison-project-name">${p.name}</span>
              <span class="badge" style="background:${STATUS_COLORS[p.status]||'#007AFF'}20;color:${STATUS_COLORS[p.status]||'#007AFF'}">${STATUS_LABELS[p.status]||p.status}</span>
            </div>
            ${p.room ? `<div style="font-size:12px;color:var(--c-text-2);margin:4px 0">${p.room}</div>` : ''}
            ${p.budget > 0 ? `
              <div class="ck-metric-bar" style="margin:10px 0">
                <div class="ck-metric-fill" style="width:${Math.min(100,Math.round((p.spent||0)/p.budget*100))}%;background:${STATUS_COLORS[p.status]||'#FF9500'}"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--c-text-2)">
                <span>${CAP360.Engine.currency(p.spent||0)} dépensé</span>
                <span>/${CAP360.Engine.currency(p.budget)}</span>
              </div>
            ` : ''}
            <div style="display:flex;gap:8px;margin-top:12px">
              <button class="btn-secondary btn-sm" onclick="event.stopPropagation();M.openEditProject('${p.id}')">Modifier</button>
              <button class="btn-ghost btn-sm" onclick="event.stopPropagation();M.deleteProject('${p.id}')">Supprimer</button>
            </div>
          </div>
        `).join('')}</div>`}
      </div>
    `;
  }

  /* ---- Rooms ---- */

  function renderRooms() {
    const d = data();
    const rooms = d.rooms || [];

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="M.openAddRoom()">+ Ajouter une pièce</button>
        </div>
        ${rooms.length === 0 ? `
          <div class="empty-state">
            <div class="empty-icon">🏡</div>
            <div class="empty-title">Aucune pièce</div>
            <div class="empty-desc">Décrivez les pièces de votre logement.</div>
            <button class="btn-primary" onclick="M.openAddRoom()">+ Ajouter une pièce</button>
          </div>
        ` : `
          <div class="maison-projects-grid">
            ${rooms.map(r => `
              <div class="card">
                <div style="font-size:24px;margin-bottom:8px">🏠</div>
                <div style="font-weight:600;margin-bottom:4px">${r.name}</div>
                ${r.area ? `<div style="font-size:12px;color:var(--c-text-2)">${r.area} m²</div>` : ''}
                ${r.notes ? `<div style="font-size:12px;color:var(--c-text-3);margin-top:6px">${r.notes}</div>` : ''}
                <div style="display:flex;gap:8px;margin-top:12px">
                  <button class="btn-ghost btn-sm" onclick="M.deleteRoom('${r.id}')">Supprimer</button>
                </div>
              </div>
            `).join('')}
          </div>
        `}
      </div>
    `;
  }

  /* ---- Modals ---- */

  function openAddProject() {
    CAP360.UI.modal('Nouveau projet', `
      <div class="form-group">
        <label class="form-label">Nom du projet</label>
        <input type="text" id="p-name" class="form-input" placeholder="Rénovation cuisine...">
      </div>
      <div class="form-group">
        <label class="form-label">Pièce concernée</label>
        <select id="p-room" class="form-select">
          <option value="">Sélectionner...</option>
          ${ROOM_TYPES.map(r => `<option>${r}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select id="p-status" class="form-select">
          <option value="planned">Planifié</option>
          <option value="active">En cours</option>
          <option value="paused">En pause</option>
          <option value="done">Terminé</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Budget prévu (€)</label>
          <input type="number" id="p-budget" class="form-input" placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">Dépensé (€)</label>
          <input type="number" id="p-spent" class="form-input" placeholder="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="p-desc" class="form-input" rows="2" placeholder="Détails du projet..."></textarea>
      </div>
    `, () => {
      const name   = document.getElementById('p-name').value.trim();
      const room   = document.getElementById('p-room').value;
      const status = document.getElementById('p-status').value;
      const budget = parseFloat(document.getElementById('p-budget').value) || 0;
      const spent  = parseFloat(document.getElementById('p-spent').value) || 0;
      const desc   = document.getElementById('p-desc').value.trim();
      if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.addNested('maison', 'projects', { name, room, status, budget, spent, description: desc });
      CAP360.UI.toast('Projet créé', 'success');
      renderTabContent();
    });
  }

  function openEditProject(id) {
    const d = data();
    const p = (d.projects || []).find(x => x.id === id);
    if (!p) return;
    CAP360.UI.modal('Modifier le projet', `
      <div class="form-group">
        <label class="form-label">Nom du projet</label>
        <input type="text" id="p-name" class="form-input" value="${p.name}">
      </div>
      <div class="form-group">
        <label class="form-label">Pièce</label>
        <select id="p-room" class="form-select">
          <option value="">Sélectionner...</option>
          ${ROOM_TYPES.map(r => `<option ${r === p.room ? 'selected' : ''}>${r}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select id="p-status" class="form-select">
          <option value="planned" ${p.status==='planned'?'selected':''}>Planifié</option>
          <option value="active" ${p.status==='active'?'selected':''}>En cours</option>
          <option value="in_progress" ${p.status==='in_progress'?'selected':''}>En cours</option>
          <option value="paused" ${p.status==='paused'?'selected':''}>En pause</option>
          <option value="done" ${p.status==='done'?'selected':''}>Terminé</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Budget (€)</label>
          <input type="number" id="p-budget" class="form-input" value="${p.budget || 0}">
        </div>
        <div class="form-group">
          <label class="form-label">Dépensé (€)</label>
          <input type="number" id="p-spent" class="form-input" value="${p.spent || 0}">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="p-desc" class="form-input" rows="2">${p.description || ''}</textarea>
      </div>
    `, () => {
      const name   = document.getElementById('p-name').value.trim();
      const room   = document.getElementById('p-room').value;
      const status = document.getElementById('p-status').value;
      const budget = parseFloat(document.getElementById('p-budget').value) || 0;
      const spent  = parseFloat(document.getElementById('p-spent').value) || 0;
      const desc   = document.getElementById('p-desc').value.trim();
      if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.updateNested('maison', 'projects', id, { name, room, status, budget, spent, description: desc });
      CAP360.UI.toast('Projet mis à jour', 'success');
      renderTabContent();
    });
  }

  function deleteProject(id) {
    if (!confirm('Supprimer ce projet ?')) return;
    CAP360.Storage.removeNested('maison', 'projects', id);
    CAP360.UI.toast('Projet supprimé');
    renderTabContent();
  }

  function openAddRoom() {
    CAP360.UI.modal('Ajouter une pièce', `
      <div class="form-group">
        <label class="form-label">Type / Nom</label>
        <select id="r-name" class="form-select">
          ${ROOM_TYPES.map(r => `<option>${r}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Surface (m²)</label>
        <input type="number" id="r-area" class="form-input" placeholder="20">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="r-notes" class="form-input" rows="2"></textarea>
      </div>
    `, () => {
      const name  = document.getElementById('r-name').value;
      const area  = parseFloat(document.getElementById('r-area').value) || null;
      const notes = document.getElementById('r-notes').value.trim();
      CAP360.Storage.addNested('maison', 'rooms', { name, area, notes });
      CAP360.UI.toast('Pièce ajoutée', 'success');
      renderTabContent();
    });
  }

  function deleteRoom(id) {
    if (!confirm('Supprimer cette pièce ?')) return;
    CAP360.Storage.removeNested('maison', 'rooms', id);
    renderTabContent();
  }

  function attachHandlers() { /* inline via window.M */ }

  /* ---- Mount ---- */

  function mount(container) {
    _view = container;
    _tab  = 'dashboard';

    _view.innerHTML = `
      <div class="module-header" style="--mod-accent:var(--c-maison)">
        <div class="module-header-inner">
          <h1 class="module-title">🏡 Maison</h1>
          <p class="module-subtitle">Gestion de votre logement et travaux</p>
        </div>
      </div>
      <div class="module-body">
        <div class="seg-control" style="margin-bottom:20px">
          <button class="seg-btn active" data-tab="dashboard" onclick="M.tab('dashboard')">Vue d'ensemble</button>
          <button class="seg-btn" data-tab="projects" onclick="M.tab('projects')">Projets</button>
          <button class="seg-btn" data-tab="rooms" onclick="M.tab('rooms')">Pièces</button>
        </div>
        <div id="maison-content"></div>
      </div>
    `;

    renderTabContent();
  }

  window.M = {
    tab:             t => { _tab = t; _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t)); renderTabContent(); },
    openAddProject:  openAddProject,
    openEditProject: openEditProject,
    deleteProject:   deleteProject,
    openAddRoom:     openAddRoom,
    deleteRoom:      deleteRoom,
  };

  return { mount, getStats };

}());
