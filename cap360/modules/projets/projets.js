/* ============================================
   CAP360 Projets V3 — Cœur du logiciel
   ============================================ */

'use strict';

CAP360.Projets = (function () {

  let _view = null;
  let _tab  = 'dashboard';

  const CATEGORIES = [
    { id: 'travaux',       label: 'Travaux',        icon: '🔨' },
    { id: 'vacances',      label: 'Vacances',        icon: '✈️' },
    { id: 'epargne',       label: 'Épargne',         icon: '💎' },
    { id: 'lep',           label: 'LEP / Livret',    icon: '🏦' },
    { id: 'voiture',       label: 'Voiture',         icon: '🚗' },
    { id: 'professionnel', label: 'Professionnel',   icon: '💼' },
    { id: 'immobilier',    label: 'Immobilier',      icon: '🏡' },
    { id: 'administratif', label: 'Administratif',   icon: '📋' },
    { id: 'autre',         label: 'Autre',           icon: '📌' },
  ];

  const PRIORITIES = [
    { id: 'high',   label: 'Haute',   color: '#D9796B' },
    { id: 'medium', label: 'Moyenne', color: '#C8A96A' },
    { id: 'low',    label: 'Basse',   color: '#8DB596' },
  ];

  const STATUSES = [
    { id: 'active',    label: 'En cours',  color: '#8DB596' },
    { id: 'planned',   label: 'Planifié',  color: '#9FCFC5' },
    { id: 'paused',    label: 'En pause',  color: '#C8A96A' },
    { id: 'done',      label: 'Terminé',   color: '#5A5C61' },
    { id: 'cancelled', label: 'Annulé',    color: '#2F363F' },
  ];

  function data()   { return CAP360.Storage.get().projets; }
  function items()  { return data().items || []; }

  function catById(id)    { return CATEGORIES.find(c => c.id === id) || CATEGORIES[CATEGORIES.length - 1]; }
  function prioById(id)   { return PRIORITIES.find(p => p.id === id) || PRIORITIES[1]; }
  function statusById(id) { return STATUSES.find(s => s.id === id)   || STATUSES[0]; }

  function fmt(v) {
    if (v === null || v === undefined) return '—';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v);
  }

  /* ---- Budget intelligence ---- */

  function computeFeasibility(project) {
    const planned  = project.budgetPlanned || 0;
    const spent    = project.budgetReal    || 0;
    const remaining = planned - spent;

    if (planned <= 0) return null;
    if (remaining <= 0) return { status: 'funded', label: 'Budget constitué', color: '#8DB596' };

    let financial = null;
    try { financial = CAP360.Platform.getFinancialHealth(); } catch (e) {}

    if (!financial || !financial.kpis) {
      return { status: 'unknown', label: 'Données budget manquantes', color: '#5A5C61' };
    }

    const balance       = financial.kpis.balance  || 0;
    const income        = financial.kpis.income   || 0;
    const expenses      = Math.abs(financial.kpis.expenses || 0);
    const monthlySavings = income - expenses;

    if (balance >= remaining) {
      return { status: 'now', label: 'Finançable immédiatement', color: '#8DB596', months: 0 };
    }

    if (monthlySavings < 50) {
      return { status: 'risk', label: 'Épargne mensuelle insuffisante', color: '#D9796B', months: null };
    }

    const needed     = Math.ceil((remaining - Math.max(0, balance)) / monthlySavings);
    const startDate  = new Date();
    startDate.setMonth(startDate.getMonth() + needed);
    const dateLabel  = startDate.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

    return {
      status: 'future',
      label:  'Démarrage possible en ' + dateLabel,
      color:  '#C8A96A',
      months: needed,
      date:   startDate.toISOString().slice(0, 7),
    };
  }

  function progressOf(p) {
    if (p.budgetPlanned > 0) return Math.min(100, Math.round((p.budgetReal || 0) / p.budgetPlanned * 100));
    if (p.target > 0)        return Math.min(100, Math.round((p.current    || 0) / p.target        * 100));
    return p.progress || 0;
  }

  /* ---- Getters ---- */

  function getStats() {
    const all    = items();
    const active = all.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const today  = new Date().toISOString().slice(0, 10);
    const late   = active.filter(p => p.dueDate && p.dueDate < today);
    const done   = all.filter(p => p.status === 'done');
    return { count: all.length, active: active.length, late: late.length, done: done.length };
  }

  /* ---- Tabs ---- */

  function setTab(tab) {
    _tab = tab;
    _view.querySelectorAll('.pj3-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    renderContent();
  }

  function renderContent() {
    const area = _view.querySelector('#pj3-content');
    if (!area) return;
    if (_tab === 'dashboard') area.innerHTML = renderDashboard();
    else                      area.innerHTML = renderAllProjects();
    bindChecklistEvents();
  }

  /* ---- Dashboard ---- */

  function renderDashboard() {
    const all    = items();
    const today  = new Date().toISOString().slice(0, 10);
    const active = all.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const late   = active.filter(p => p.dueDate && p.dueDate < today);
    const done   = all.filter(p => p.status === 'done');
    const stats  = getStats();

    if (all.length === 0) {
      return `
        <div class="empty-state">
          <div class="empty-icon">🎯</div>
          <div class="empty-title">Aucun projet</div>
          <div class="empty-desc">Créez votre premier projet de vie pour commencer à piloter vos ambitions.</div>
          <button class="btn btn-primary" onclick="P.openAdd()">+ Créer un projet</button>
        </div>
      `;
    }

    return `
      <!-- KPIs -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Projets actifs</div>
          <div class="kpi-value">${stats.active}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">En retard</div>
          <div class="kpi-value ${stats.late > 0 ? 'neg' : ''}">${stats.late}</div>
          ${stats.late > 0 ? '<div class="kpi-trend neg">Action requise</div>' : '<div class="kpi-trend pos">Dans les temps</div>'}
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Terminés</div>
          <div class="kpi-value pos">${stats.done}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Total</div>
          <div class="kpi-value">${stats.count}</div>
        </div>
      </div>

      ${late.length > 0 ? `
        <div class="alert-card alert-danger" style="margin-bottom:20px">
          ⚠️ <strong>${late.length} projet(s) en retard</strong> — nécessitent votre attention.
        </div>
      ` : ''}

      <!-- Projets actifs -->
      <div class="pj3-section-title">Projets en cours</div>
      <div class="pj3-grid">
        ${active.map(p => renderProjectCard(p)).join('')}
      </div>

      ${done.length > 0 ? `
        <div class="pj3-section-title" style="margin-top:28px">Terminés</div>
        <div class="pj3-grid">
          ${done.map(p => renderProjectCard(p)).join('')}
        </div>
      ` : ''}
    `;
  }

  function renderProjectCard(p) {
    const cat    = catById(p.category);
    const prio   = prioById(p.priority);
    const status = statusById(p.status);
    const pct    = progressOf(p);
    const today  = new Date().toISOString().slice(0, 10);
    const isLate = p.dueDate && p.dueDate < today && p.status !== 'done' && p.status !== 'cancelled';
    const feasibility = p.status !== 'done' && p.status !== 'cancelled' ? computeFeasibility(p) : null;
    const checklist   = p.checklist || [];
    const doneTasks   = checklist.filter(t => t.done).length;
    const isDone      = p.status === 'done';

    return `
      <div class="pj3-card ${isDone ? 'pj3-card-done' : ''}" onclick="P.openEdit('${p.id}')">
        <div class="pj3-card-head">
          <span class="pj3-cat-icon">${cat.icon}</span>
          <div class="pj3-card-meta">
            <span class="pj3-cat-label">${cat.label}</span>
            ${p.priority ? `<span class="pj3-prio" style="color:${prio.color}">${prio.label}</span>` : ''}
          </div>
          <span class="pj3-status-badge" style="background:${status.color}20;color:${status.color}">${status.label}</span>
        </div>

        <div class="pj3-card-name">${p.name}</div>
        ${p.description ? `<div class="pj3-card-desc">${p.description}</div>` : ''}

        ${(p.budgetPlanned > 0 || p.target > 0) ? `
          <div class="pj3-budget-row">
            <div class="pj3-budget-info">
              ${p.budgetPlanned > 0
                ? `<span>${fmt(p.budgetReal || 0)}</span><span style="color:var(--c-text-3)"> / ${fmt(p.budgetPlanned)}</span>`
                : `<span>${fmt(p.current || 0)}</span><span style="color:var(--c-text-3)"> / ${fmt(p.target)}</span>`
              }
            </div>
            <span class="pj3-pct" style="color:${isLate ? '#D9796B' : status.color}">${pct}%</span>
          </div>
          <div class="pj3-bar">
            <div class="pj3-bar-fill" style="width:${pct}%;background:${isLate ? '#D9796B' : status.color}"></div>
          </div>
        ` : ''}

        ${feasibility ? `
          <div class="pj3-feasibility" style="border-left-color:${feasibility.color}">
            <span style="color:${feasibility.color}">💡</span>
            <span>${feasibility.label}</span>
          </div>
        ` : ''}

        <div class="pj3-card-footer">
          ${p.dueDate ? `
            <span class="pj3-date ${isLate ? 'pj3-date-late' : ''}">
              ${isLate ? '⚠️ ' : '📅 '}${CAP360.Engine.dateShort(p.dueDate)}
            </span>
          ` : ''}
          ${checklist.length > 0 ? `
            <span class="pj3-tasks">${doneTasks}/${checklist.length} tâches</span>
          ` : ''}
        </div>
      </div>
    `;
  }

  /* ---- All projects ---- */

  function renderAllProjects() {
    const all = items().slice().sort((a, b) => {
      const order = { active: 0, planned: 1, paused: 2, done: 3, cancelled: 4 };
      return (order[a.status] || 0) - (order[b.status] || 0);
    });

    return `
      <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <button class="btn btn-primary" onclick="P.openAdd()">+ Nouveau projet</button>
      </div>
      ${all.length === 0 ? `
        <div class="empty-state">
          <div class="empty-icon">🎯</div>
          <div class="empty-title">Aucun projet</div>
          <div class="empty-desc">Ajoutez vos projets de vie ici.</div>
        </div>
      ` : `
        <div class="pj3-grid">
          ${all.map(p => renderProjectCard(p)).join('')}
        </div>
      `}
    `;
  }

  /* ---- Modals ---- */

  function buildForm(p) {
    const checklist = p ? (p.checklist || []) : [];
    return `
      <div class="form-group">
        <label class="form-label">Nom du projet</label>
        <input type="text" id="pj-name" class="form-input" placeholder="Rénover la salle de bain..." value="${p ? p.name : ''}">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Catégorie</label>
          <select id="pj-cat" class="form-select">
            ${CATEGORIES.map(c => `<option value="${c.id}" ${p && p.category === c.id ? 'selected' : ''}>${c.icon} ${c.label}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <select id="pj-prio" class="form-select">
            ${PRIORITIES.map(pr => `<option value="${pr.id}" ${p && p.priority === pr.id ? 'selected' : ''}>${pr.label}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Statut</label>
          <select id="pj-status" class="form-select">
            ${STATUSES.map(s => `<option value="${s.id}" ${p && p.status === s.id ? 'selected' : ''}>${s.label}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Échéance</label>
          <input type="date" id="pj-due" class="form-input" value="${p ? (p.dueDate || '') : ''}">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Budget prévu (€)</label>
          <input type="number" id="pj-budget-planned" class="form-input" placeholder="0" value="${p ? (p.budgetPlanned || '') : ''}">
        </div>
        <div class="form-group">
          <label class="form-label">Dépensé / Épargné (€)</label>
          <input type="number" id="pj-budget-real" class="form-input" placeholder="0" value="${p ? (p.budgetReal || '') : ''}">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="pj-desc" class="form-input" rows="2" placeholder="Description du projet...">${p ? (p.description || '') : ''}</textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="pj-notes" class="form-input" rows="2" placeholder="Contacts, liens, informations pratiques...">${p ? (p.notes || '') : ''}</textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Checklist</label>
        <div id="pj-checklist">
          ${checklist.map((t, i) => `
            <div class="pj3-checklist-row">
              <input type="checkbox" ${t.done ? 'checked' : ''} id="pjck-${i}">
              <input type="text" class="form-input" style="flex:1" value="${t.text}" id="pjct-${i}">
              <button class="btn-ghost btn-sm" onclick="PJ.removeTask(${i})" style="flex-shrink:0">✕</button>
            </div>
          `).join('')}
        </div>
        <button class="btn btn-secondary btn-sm" style="margin-top:8px" onclick="PJ.addTask()">+ Ajouter une tâche</button>
      </div>
    `;
  }

  function readForm(existingChecklist) {
    const checklist = (existingChecklist || []).map((t, i) => {
      const el = document.getElementById('pjct-' + i);
      const cb = document.getElementById('pjck-' + i);
      return { id: t.id, text: el ? el.value.trim() : t.text, done: cb ? cb.checked : t.done };
    }).filter(t => t.text);

    return {
      name:          (document.getElementById('pj-name').value        || '').trim(),
      category:       document.getElementById('pj-cat').value,
      priority:       document.getElementById('pj-prio').value,
      status:         document.getElementById('pj-status').value,
      dueDate:        document.getElementById('pj-due').value        || null,
      budgetPlanned:  parseFloat(document.getElementById('pj-budget-planned').value) || 0,
      budgetReal:     parseFloat(document.getElementById('pj-budget-real').value)    || 0,
      description:   (document.getElementById('pj-desc').value       || '').trim(),
      notes:         (document.getElementById('pj-notes').value       || '').trim(),
      checklist,
    };
  }

  function openAdd() {
    CAP360.UI.modal('Nouveau projet', buildForm(null), () => {
      const fields = readForm([]);
      if (!fields.name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.addNested('projets', 'items', { ...fields, progress: 0 });
      CAP360.UI.toast('Projet créé', 'success');
      renderContent();
    });
    bindModalEvents(null);
  }

  function openEdit(id) {
    const p = items().find(x => x.id === id);
    if (!p) return;
    CAP360.UI.modal('Modifier le projet', buildForm(p), () => {
      const fields = readForm(p.checklist || []);
      if (!fields.name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.updateNested('projets', 'items', id, fields);
      CAP360.UI.toast('Projet mis à jour', 'success');
      renderContent();
    }, {
      extra: `<button class="btn btn-danger btn-sm" style="margin-right:auto" onclick="P.delete('${id}');CAP360.UI.closeModal()">Supprimer</button>`,
    });
    bindModalEvents(p);
  }

  function deleteItem(id) {
    if (!confirm('Supprimer ce projet ?')) return;
    CAP360.Storage.removeNested('projets', 'items', id);
    CAP360.UI.toast('Projet supprimé');
    renderContent();
  }

  /* ---- Checklist helpers in modal ---- */

  let _modalChecklist = [];

  function bindModalEvents(existingProject) {
    _modalChecklist = existingProject ? [...(existingProject.checklist || [])] : [];
  }

  window.PJ = {
    addTask: function () {
      const container = document.getElementById('pj-checklist');
      if (!container) return;
      const i = container.children.length;
      const row = document.createElement('div');
      row.className = 'pj3-checklist-row';
      row.innerHTML = `
        <input type="checkbox" id="pjck-${i}">
        <input type="text" class="form-input" style="flex:1" id="pjct-${i}" placeholder="Nouvelle tâche...">
        <button class="btn-ghost btn-sm" onclick="PJ.removeTask(${i})" style="flex-shrink:0">✕</button>
      `;
      container.appendChild(row);
      row.querySelector('input[type=text]').focus();
    },
    removeTask: function (i) {
      const el = document.querySelector(`#pjck-${i}`)?.closest('.pj3-checklist-row');
      if (el) el.remove();
    },
  };

  function bindChecklistEvents() {}

  /* ---- Mount ---- */

  function mount(container) {
    _view = container;
    _tab  = 'dashboard';

    _view.innerHTML = `
      <div class="module-header">
        <div class="module-title-block">
          <h1>🎯 Projets de vie</h1>
          <p>Pilotez vos ambitions et suivez votre progression</p>
        </div>
        <div class="module-actions">
          <button class="btn btn-primary" onclick="P.openAdd()">+ Nouveau projet</button>
        </div>
      </div>

      <div class="seg-control" style="margin-bottom:24px">
        <button class="pj3-tab-btn seg-btn active" data-tab="dashboard" onclick="P.tab('dashboard')">Vue d'ensemble</button>
        <button class="pj3-tab-btn seg-btn" data-tab="all" onclick="P.tab('all')">Tous les projets</button>
      </div>

      <div id="pj3-content"></div>
    `;

    renderContent();
  }

  /* ---- getHealth() — contrat Platform ---- */

  function getHealth() {
    const all    = items();
    const stats  = getStats();
    const today  = new Date().toISOString().slice(0, 10);
    const in60   = new Date(Date.now() + 60 * 86400000).toISOString().slice(0, 10);
    const active = all.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const done   = all.filter(p => p.status === 'done');
    const late   = active.filter(p => p.dueDate && p.dueDate < today);

    /* Score 0-100 */
    let score = 70;
    if (late.length === 0 && active.length > 0) score += 15;
    score -= late.length * 15;
    if (done.length > 0) score += Math.min(15, done.length * 5);
    score = Math.max(0, Math.min(100, score));

    /* Alerts */
    const alerts = [];
    late.forEach(p => {
      const diff = Math.round((new Date() - new Date(p.dueDate)) / 86400000);
      alerts.push({ level: 'warning', message: '"' + p.name + '" en retard de ' + diff + ' jour(s)', action: { label: 'Voir Projets', module: 'projets' } });
    });
    active.filter(p => p.dueDate && p.dueDate >= today && p.dueDate <= in60).forEach(p => {
      const diff = Math.round((new Date(p.dueDate) - new Date()) / 86400000);
      alerts.push({ level: 'info', message: 'Échéance "' + p.name + '" dans ' + diff + ' jour(s)', action: { label: 'Voir Projets', module: 'projets' } });
    });

    /* Timeline */
    const timeline = active
      .filter(p => p.dueDate && p.dueDate >= today && p.dueDate <= in60)
      .map(p => ({
        date:   p.dueDate,
        type:   'milestone',
        label:  p.name,
        icon:   catById(p.category).icon,
        color:  statusById(p.status).color,
        amount: p.budgetPlanned || p.target || null,
      }));

    /* Story */
    const parts = [];
    if (active.length > 0) parts.push(active.length + ' projet(s) en cours.');
    if (late.length > 0)   parts.push(late.length + ' en retard.');
    if (done.length > 0)   parts.push(done.length + ' terminé(s).');
    if (all.length === 0)  parts.push('Aucun projet défini.');

    return {
      id:     'projets',
      label:  'Projets',
      icon:   '🎯',
      accent: '#8DB596',
      weight: 30,
      score,
      kpis: {
        count:  all.length,
        active: active.length,
        late:   late.length,
        done:   done.length,
        items:  active.slice(0, 3),
      },
      alerts,
      timeline,
      story: parts.join(' ') || null,
    };
  }

  window.P = {
    tab:      t => setTab(t),
    openAdd:  openAdd,
    openEdit: openEdit,
    delete:   deleteItem,
  };

  return { mount, getStats, getHealth };

}());
