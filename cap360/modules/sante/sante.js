/* ============================================
   CAP360 Santé — Suivi santé & sport
   ============================================ */

'use strict';

CAP360.Sante = (function () {

  let _view = null;
  let _tab  = 'dashboard';

  const METRIC_TYPES = [
    { id: 'steps',  label: 'Pas',          icon: '🦶', unit: 'pas',   target: 10000, color: '#30D158' },
    { id: 'sleep',  label: 'Sommeil',       icon: '😴', unit: 'h',     target: 8,     color: '#007AFF' },
    { id: 'weight', label: 'Poids',         icon: '⚖️', unit: 'kg',    target: null,  color: '#FF9500' },
    { id: 'cardio', label: 'Cardio',        icon: '🏃', unit: 'min',   target: 30,    color: '#FF3B30' },
    { id: 'water',  label: 'Eau',           icon: '💧', unit: 'L',     target: 2,     color: '#5AC8FA' },
    { id: 'sport',  label: 'Sport',         icon: '💪', unit: 'séances', target: 3,  color: '#BF5AF2' },
  ];

  const APPT_TYPES = ['Médecin généraliste', 'Dentiste', 'Ophtalmologue', 'Cardiologue', 'Kiné', 'Dermatologue', 'Autre'];

  function data() { return CAP360.Storage.get().sante; }

  /* ---- Stats for Cockpit ---- */

  function getStats() {
    const d = data();
    const metrics = d.metrics || [];
    const now7 = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);
    const recent = metrics.filter(m => m.date >= now7);

    function avg(type) {
      const vals = recent.filter(m => m.type === type);
      if (!vals.length) return 0;
      return vals.reduce((s, m) => s + m.value, 0) / vals.length;
    }

    const stepsAvg = Math.round(avg('steps'));
    const sleepAvg = Math.round(avg('sleep') * 10) / 10;
    const weights  = metrics.filter(m => m.type === 'weight').sort((a, b) => a.date > b.date ? 1 : -1);
    const weight   = weights.length ? weights[weights.length - 1].value : null;
    const today    = new Date().toISOString().slice(0, 10);
    const nextAppt = (d.appointments || []).filter(a => a.date >= today).sort((a, b) => a.date > b.date ? 1 : -1)[0] || null;

    let score = 5;
    if (stepsAvg > 8000)  score += 1.5; else if (stepsAvg > 5000) score += 0.8;
    if (sleepAvg >= 7 && sleepAvg <= 9) score += 1.5; else if (sleepAvg >= 6) score += 0.8;
    if (stepsAvg > 10000) score += 0.5;
    if (sleepAvg >= 7.5)  score += 0.5;
    score = Math.min(10, Math.round(score * 10) / 10);

    return { score, stepsAvg, sleepAvg, weight, nextAppt };
  }

  /* ---- Tabs ---- */

  function setTab(tab) {
    _tab = tab;
    _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    renderTabContent();
  }

  function renderTabContent() {
    const area = _view.querySelector('#sante-content');
    if (!area) return;
    if (_tab === 'dashboard')    area.innerHTML = renderDashboard();
    else if (_tab === 'metrics') area.innerHTML = renderMetrics();
    else if (_tab === 'appointments') area.innerHTML = renderAppointments();
    attachHandlers();
  }

  /* ---- Dashboard ---- */

  function renderDashboard() {
    const d = data();
    const stats = getStats();
    const today = new Date().toISOString().slice(0, 10);
    const todayMetrics = (d.metrics || []).filter(m => m.date === today);
    const scoreColor = stats.score >= 7 ? '#30D158' : stats.score >= 5 ? '#FF9500' : '#FF3B30';

    return `
      <div class="sante-dash">

        <!-- Score card -->
        <div class="card" style="text-align:center;padding:28px">
          <div style="font-size:13px;color:var(--c-text-2);margin-bottom:8px">Score Santé Global</div>
          <div style="font-size:52px;font-weight:700;color:${scoreColor};line-height:1">${stats.score}</div>
          <div style="font-size:16px;color:var(--c-text-3)">/10</div>
          <div style="margin-top:12px;font-size:13px;color:var(--c-text-2)">${stats.score>=8?'Excellent — continuez ainsi 💪':stats.score>=6?'Bien — quelques améliorations possibles':stats.score>=4?'À améliorer — fixez des objectifs':'Commencez par enregistrer vos données'}</div>
        </div>

        <!-- Quick add today -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Aujourd'hui</span>
            <button class="btn-primary btn-sm" onclick="S.openAddMetric()">+ Ajouter</button>
          </div>
          <div class="sante-today-grid">
            ${METRIC_TYPES.map(mt => {
              const todayVal = todayMetrics.find(m => m.type === mt.id);
              const pct = mt.target && todayVal ? Math.min(100, Math.round(todayVal.value / mt.target * 100)) : 0;
              return `
                <div class="sante-metric-card">
                  <div class="sante-metric-icon">${mt.icon}</div>
                  <div class="sante-metric-label">${mt.label}</div>
                  <div class="sante-metric-value">${todayVal ? todayVal.value + ' ' + mt.unit : '—'}</div>
                  ${mt.target ? `<div class="ck-metric-bar" style="margin-top:6px"><div class="ck-metric-fill" style="width:${pct}%;background:${mt.color}"></div></div><div style="font-size:11px;color:var(--c-text-3);margin-top:3px">Objectif: ${mt.target} ${mt.unit}</div>` : ''}
                </div>
              `;
            }).join('')}
          </div>
        </div>

        <!-- Weekly averages -->
        <div class="card">
          <div class="card-header"><span class="card-title">Moyennes 7 jours</span></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="kpi-card"><div class="kpi-label">Pas / jour</div><div class="kpi-value">${stats.stepsAvg.toLocaleString('fr-FR')}</div><div class="kpi-trend ${stats.stepsAvg>=10000?'pos':''}">Objectif 10 000</div></div>
            <div class="kpi-card"><div class="kpi-label">Sommeil / nuit</div><div class="kpi-value">${stats.sleepAvg} h</div><div class="kpi-trend ${stats.sleepAvg>=7?'pos':''}">${stats.sleepAvg>=7?'Objectif atteint':'Objectif 7-9 h'}</div></div>
            ${stats.weight ? `<div class="kpi-card"><div class="kpi-label">Poids actuel</div><div class="kpi-value">${stats.weight} kg</div></div>` : ''}
            ${stats.nextAppt ? `<div class="kpi-card"><div class="kpi-label">Prochain RDV</div><div class="kpi-value" style="font-size:16px">${stats.nextAppt.type}</div><div class="kpi-trend">${CAP360.Engine.dateShort(stats.nextAppt.date)}</div></div>` : ''}
          </div>
        </div>

      </div>
    `;
  }

  /* ---- Metrics list ---- */

  function renderMetrics() {
    const d = data();
    const metrics = (d.metrics || []).slice().sort((a, b) => b.date.localeCompare(a.date));

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="S.openAddMetric()">+ Nouvelle métrique</button>
        </div>
        ${metrics.length === 0 ? '<div class="empty-state"><div class="empty-icon">🦶</div><div class="empty-title">Aucune métrique</div><div class="empty-desc">Enregistrez vos premières données de santé.</div><button class="btn-primary" onclick="S.openAddMetric()">+ Ajouter une métrique</button></div>' : `
          <div class="card" style="overflow:auto">
            <table class="data-table">
              <thead><tr><th>Date</th><th>Type</th><th>Valeur</th><th></th></tr></thead>
              <tbody>
                ${metrics.slice(0, 100).map(m => {
                  const mt = METRIC_TYPES.find(t => t.id === m.type) || { icon: '📊', label: m.type, unit: '', color: '#007AFF' };
                  return `<tr>
                    <td>${CAP360.Engine.dateShort(m.date)}</td>
                    <td>${mt.icon} ${mt.label}</td>
                    <td><strong>${m.value}</strong> ${mt.unit}</td>
                    <td><button class="btn-ghost btn-sm" onclick="S.deleteMetric('${m.id}')">✕</button></td>
                  </tr>`;
                }).join('')}
              </tbody>
            </table>
          </div>
        `}
      </div>
    `;
  }

  /* ---- Appointments ---- */

  function renderAppointments() {
    const d = data();
    const today = new Date().toISOString().slice(0, 10);
    const upcoming = (d.appointments || []).filter(a => a.date >= today).sort((a, b) => a.date.localeCompare(b.date));
    const past     = (d.appointments || []).filter(a => a.date < today).sort((a, b) => b.date.localeCompare(a.date));

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="S.openAddAppt()">+ Nouveau RDV</button>
        </div>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><span class="card-title">À venir</span></div>
          ${upcoming.length === 0 ? '<div class="empty-state-sm">Aucun rendez-vous à venir</div>' : upcoming.map(a => `
            <div class="tx-row">
              <span class="tx-icon">🏥</span>
              <div class="tx-info">
                <span class="tx-label">${a.type}</span>
                <span class="tx-sub">${a.doctor ? a.doctor + ' · ' : ''}${a.location || ''}</span>
              </div>
              <div style="text-align:right">
                <span class="badge badge-info">${CAP360.Engine.dateShort(a.date)}</span>
              </div>
              <button class="btn-ghost btn-sm" onclick="S.deleteAppt('${a.id}')" style="margin-left:8px">✕</button>
            </div>
          `).join('')}
        </div>
        ${past.length > 0 ? `
          <div class="card">
            <div class="card-header"><span class="card-title">Passés</span></div>
            ${past.slice(0, 10).map(a => `
              <div class="tx-row" style="opacity:0.6">
                <span class="tx-icon">🏥</span>
                <div class="tx-info">
                  <span class="tx-label">${a.type}</span>
                  <span class="tx-sub">${a.doctor || ''}</span>
                </div>
                <span class="tx-amount">${CAP360.Engine.dateShort(a.date)}</span>
              </div>
            `).join('')}
          </div>
        ` : ''}
      </div>
    `;
  }

  /* ---- Modals ---- */

  function openAddMetric() {
    const today = new Date().toISOString().slice(0, 10);
    CAP360.UI.modal('Nouvelle métrique', `
      <div class="form-group">
        <label class="form-label">Date</label>
        <input type="date" id="m-date" class="form-input" value="${today}">
      </div>
      <div class="form-group">
        <label class="form-label">Type</label>
        <select id="m-type" class="form-select">
          ${METRIC_TYPES.map(mt => `<option value="${mt.id}">${mt.icon} ${mt.label} (${mt.unit})</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Valeur</label>
        <input type="number" id="m-value" class="form-input" placeholder="0" step="0.1">
      </div>
    `, () => {
      const date  = document.getElementById('m-date').value;
      const type  = document.getElementById('m-type').value;
      const value = parseFloat(document.getElementById('m-value').value);
      if (!date || isNaN(value)) { CAP360.UI.toast('Valeur requise', 'error'); return false; }
      CAP360.Storage.addNested('sante', 'metrics', { date, type, value });
      CAP360.UI.toast('Métrique ajoutée', 'success');
      renderTabContent();
    });
  }

  function openAddAppt() {
    const today = new Date().toISOString().slice(0, 10);
    CAP360.UI.modal('Nouveau rendez-vous', `
      <div class="form-group">
        <label class="form-label">Type</label>
        <select id="a-type" class="form-select">
          ${APPT_TYPES.map(t => `<option>${t}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Date</label>
        <input type="date" id="a-date" class="form-input" value="${today}">
      </div>
      <div class="form-group">
        <label class="form-label">Médecin / Praticien</label>
        <input type="text" id="a-doctor" class="form-input" placeholder="Dr Dupont">
      </div>
      <div class="form-group">
        <label class="form-label">Lieu</label>
        <input type="text" id="a-location" class="form-input" placeholder="Cabinet médical...">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="a-notes" class="form-input" rows="2"></textarea>
      </div>
    `, () => {
      const type     = document.getElementById('a-type').value;
      const date     = document.getElementById('a-date').value;
      const doctor   = document.getElementById('a-doctor').value.trim();
      const location = document.getElementById('a-location').value.trim();
      const notes    = document.getElementById('a-notes').value.trim();
      if (!date) { CAP360.UI.toast('Date requise', 'error'); return false; }
      CAP360.Storage.addNested('sante', 'appointments', { type, date, doctor, location, notes });
      CAP360.UI.toast('Rendez-vous ajouté', 'success');
      renderTabContent();
    });
  }

  function deleteMetric(id) {
    if (!confirm('Supprimer cette métrique ?')) return;
    CAP360.Storage.removeNested('sante', 'metrics', id);
    renderTabContent();
  }

  function deleteAppt(id) {
    if (!confirm('Supprimer ce rendez-vous ?')) return;
    CAP360.Storage.removeNested('sante', 'appointments', id);
    renderTabContent();
  }

  /* ---- Handlers ---- */

  function attachHandlers() { /* inline via window.S */ }

  /* ---- Mount ---- */

  function mount(container) {
    _view     = container;
    _tab      = 'dashboard';

    _view.innerHTML = `
      <div class="module-header" style="--mod-accent:var(--c-sante)">
        <div class="module-header-inner">
          <h1 class="module-title">❤️ Santé</h1>
          <p class="module-subtitle">Suivi de votre santé et bien-être</p>
        </div>
      </div>
      <div class="module-body">
        <div class="seg-control" style="margin-bottom:20px">
          <button class="seg-btn active" data-tab="dashboard" onclick="S.tab('dashboard')">Vue d'ensemble</button>
          <button class="seg-btn" data-tab="metrics" onclick="S.tab('metrics')">Métriques</button>
          <button class="seg-btn" data-tab="appointments" onclick="S.tab('appointments')">Rendez-vous</button>
        </div>
        <div id="sante-content"></div>
      </div>
    `;

    renderTabContent();
  }

  /* ---- Public API ---- */

  window.S = {
    tab:           t => { _tab = t; _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t)); renderTabContent(); },
    openAddMetric: openAddMetric,
    openAddAppt:   openAddAppt,
    deleteMetric:  deleteMetric,
    deleteAppt:    deleteAppt,
  };

  return { mount, getStats };

}());
