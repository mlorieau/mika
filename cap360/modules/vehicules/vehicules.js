/* ============================================
   CAP360 Véhicules — Gestion des véhicules
   ============================================ */

'use strict';

CAP360.Vehicules = (function () {

  let _view     = null;
  let _tab      = 'dashboard';
  let _selectedId = null;

  const FUEL_TYPES   = ['Essence', 'Diesel', 'Électrique', 'Hybride', 'GPL', 'Autre'];
  const MAINT_TYPES  = ['Vidange', 'Pneus', 'Freins', 'Révision', 'Contrôle technique', 'Courroie', 'Batterie', 'Carburant', 'Assurance', 'Autre'];

  function data() { return CAP360.Storage.get().vehicules; }

  /* ---- Stats for Cockpit ---- */

  function getStats() {
    const d     = data();
    const items = d.items || [];
    const today = new Date().toISOString().slice(0, 10);
    const alerts = [];
    items.forEach(v => {
      if (v.controleTechniqueDate && v.controleTechniqueDate < today) alerts.push('CT expiré: ' + v.name);
      if (v.assuranceDate && v.assuranceDate < today) alerts.push('Assurance expirée: ' + v.name);
      const ct30 = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
      if (v.controleTechniqueDate && v.controleTechniqueDate >= today && v.controleTechniqueDate <= ct30) {
        alerts.push('CT bientôt: ' + v.name + ' le ' + CAP360.Engine.dateShort(v.controleTechniqueDate));
      }
    });
    return { count: items.length, alerts, allOk: alerts.length === 0 };
  }

  /* ---- Tab switching ---- */

  function setTab(tab) {
    _tab = tab;
    _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    renderTabContent();
  }

  function renderTabContent() {
    const area = _view.querySelector('#vehicules-content');
    if (!area) return;
    if (_tab === 'dashboard')    area.innerHTML = renderDashboard();
    else if (_tab === 'maintenance') area.innerHTML = renderMaintenance();
    else if (_tab === 'fuel')    area.innerHTML = renderFuel();
  }

  /* ---- Dashboard ---- */

  function renderDashboard() {
    const d     = data();
    const stats = getStats();
    const items = d.items || [];
    const today = new Date().toISOString().slice(0, 10);

    return `
      <div>
        <!-- Alerts -->
        ${stats.alerts.length > 0 ? `
          <div class="alert-card alert-danger" style="margin-bottom:16px">
            <strong>⚠️ Alertes véhicules</strong>
            ${stats.alerts.map(a => `<div>${a}</div>`).join('')}
          </div>
        ` : ''}

        <!-- KPIs -->
        <div class="kpi-grid">
          <div class="kpi-card"><div class="kpi-label">Véhicules</div><div class="kpi-value">${stats.count}</div></div>
          <div class="kpi-card"><div class="kpi-label">Alertes</div><div class="kpi-value ${stats.alerts.length>0?'neg':''}">${stats.alerts.length}</div></div>
          <div class="kpi-card"><div class="kpi-label">Coûts ce mois</div><div class="kpi-value neg">${CAP360.Engine.currencyShort(getMonthCosts())}</div></div>
          <div class="kpi-card"><div class="kpi-label">Statut</div><div class="kpi-value ${stats.allOk?'pos':'neg'}">${stats.allOk?'✓ OK':'⚠️'}</div></div>
        </div>

        <!-- Vehicles -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Mes véhicules</span>
            <button class="btn-primary btn-sm" onclick="V.openAddVehicle()">+ Ajouter</button>
          </div>
          ${items.length === 0 ? `
            <div class="empty-state-sm">Aucun véhicule — <a href="#" onclick="V.openAddVehicle();return false">en ajouter un</a></div>
          ` : items.map(v => {
            const ct   = v.controleTechniqueDate;
            const ins  = v.assuranceDate;
            const ctOk = !ct || ct > today;
            const insOk = !ins || ins > today;
            return `
              <div class="tx-row" style="padding:16px;cursor:pointer" onclick="V.openEditVehicle('${v.id}')">
                <span class="tx-icon" style="font-size:28px">${v.type === 'moto' ? '🏍️' : '🚗'}</span>
                <div class="tx-info">
                  <span class="tx-label" style="font-size:15px;font-weight:600">${v.name}</span>
                  <span class="tx-sub">${[v.brand, v.model, v.year].filter(Boolean).join(' · ')}${v.plate ? ' · ' + v.plate : ''}</span>
                  <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap">
                    ${ct ? `<span class="badge ${ctOk?'badge-success':'badge-danger'}">CT: ${CAP360.Engine.dateShort(ct)}</span>` : ''}
                    ${ins ? `<span class="badge ${insOk?'badge-success':'badge-danger'}">Assurance: ${CAP360.Engine.dateShort(ins)}</span>` : ''}
                    ${v.mileage ? `<span class="badge badge-info">${v.mileage.toLocaleString('fr-FR')} km</span>` : ''}
                  </div>
                </div>
                <div style="display:flex;gap:8px">
                  <button class="btn-secondary btn-sm" onclick="event.stopPropagation();V.selectVehicle('${v.id}')">Détails</button>
                  <button class="btn-ghost btn-sm" onclick="event.stopPropagation();V.deleteVehicle('${v.id}')">✕</button>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `;
  }

  function getMonthCosts() {
    const d = data();
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    const end   = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    const maint = (d.maintenances || []).filter(m => m.date >= start && m.date <= end).reduce((s, m) => s + (m.cost || 0), 0);
    const fuel  = (d.fuelLogs || []).filter(f => f.date >= start && f.date <= end).reduce((s, f) => s + (f.cost || 0), 0);
    return maint + fuel;
  }

  /* ---- Maintenance ---- */

  function renderMaintenance() {
    const d   = data();
    const items = d.items || [];
    const maint = (d.maintenances || []).slice().sort((a, b) => b.date.localeCompare(a.date));

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="V.openAddMaintenance()">+ Ajouter une opération</button>
        </div>
        ${maint.length === 0 ? `
          <div class="empty-state">
            <div class="empty-icon">🔧</div>
            <div class="empty-title">Aucune opération</div>
            <div class="empty-desc">Enregistrez vos vidanges, révisions et autres maintenances.</div>
            <button class="btn-primary" onclick="V.openAddMaintenance()">+ Ajouter</button>
          </div>
        ` : `
          <div class="card">
            <table class="data-table">
              <thead><tr><th>Date</th><th>Véhicule</th><th>Type</th><th>Km</th><th>Coût</th><th>Notes</th><th></th></tr></thead>
              <tbody>
                ${maint.slice(0, 50).map(m => {
                  const v = items.find(x => x.id === m.vehicleId);
                  return `<tr>
                    <td>${CAP360.Engine.dateShort(m.date)}</td>
                    <td>${v ? v.name : '—'}</td>
                    <td>${m.type}</td>
                    <td>${m.mileage ? m.mileage.toLocaleString('fr-FR') + ' km' : '—'}</td>
                    <td class="neg">${m.cost ? CAP360.Engine.currency(m.cost) : '—'}</td>
                    <td style="max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${m.notes || ''}</td>
                    <td><button class="btn-ghost btn-sm" onclick="V.deleteMaintenance('${m.id}')">✕</button></td>
                  </tr>`;
                }).join('')}
              </tbody>
            </table>
          </div>
        `}
      </div>
    `;
  }

  /* ---- Fuel ---- */

  function renderFuel() {
    const d   = data();
    const items = d.items || [];
    const logs = (d.fuelLogs || []).slice().sort((a, b) => b.date.localeCompare(a.date));
    const totalL   = logs.reduce((s, f) => s + (f.liters || 0), 0);
    const totalCost = logs.reduce((s, f) => s + (f.cost || 0), 0);
    const avgPrice = totalL > 0 ? totalCost / totalL : 0;

    return `
      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
          <button class="btn-primary" onclick="V.openAddFuel()">+ Plein de carburant</button>
        </div>
        <div class="kpi-grid" style="margin-bottom:16px">
          <div class="kpi-card"><div class="kpi-label">Total carburant</div><div class="kpi-value">${Math.round(totalL)} L</div></div>
          <div class="kpi-card"><div class="kpi-label">Total dépensé</div><div class="kpi-value neg">${CAP360.Engine.currency(totalCost)}</div></div>
          <div class="kpi-card"><div class="kpi-label">Prix moy. / L</div><div class="kpi-value">${avgPrice.toFixed(2)} €</div></div>
        </div>
        ${logs.length === 0 ? `
          <div class="empty-state">
            <div class="empty-icon">⛽</div>
            <div class="empty-title">Aucun plein</div>
            <div class="empty-desc">Enregistrez vos pleins de carburant pour suivre votre consommation.</div>
          </div>
        ` : `
          <div class="card">
            <table class="data-table">
              <thead><tr><th>Date</th><th>Véhicule</th><th>Litres</th><th>Prix/L</th><th>Total</th><th>Km</th><th></th></tr></thead>
              <tbody>
                ${logs.slice(0, 50).map(f => {
                  const v = items.find(x => x.id === f.vehicleId);
                  const ppl = f.liters ? (f.cost / f.liters).toFixed(3) : '—';
                  return `<tr>
                    <td>${CAP360.Engine.dateShort(f.date)}</td>
                    <td>${v ? v.name : '—'}</td>
                    <td>${f.liters ? f.liters + ' L' : '—'}</td>
                    <td>${ppl} €</td>
                    <td class="neg">${CAP360.Engine.currency(f.cost)}</td>
                    <td>${f.mileage ? f.mileage.toLocaleString('fr-FR') + ' km' : '—'}</td>
                    <td><button class="btn-ghost btn-sm" onclick="V.deleteFuel('${f.id}')">✕</button></td>
                  </tr>`;
                }).join('')}
              </tbody>
            </table>
          </div>
        `}
      </div>
    `;
  }

  /* ---- Modals ---- */

  function openAddVehicle() {
    CAP360.UI.modal('Nouveau véhicule', `
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nom / Surnom</label>
          <input type="text" id="v-name" class="form-input" placeholder="Ma 206...">
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select id="v-type" class="form-select">
            <option value="car">Voiture</option>
            <option value="moto">Moto</option>
            <option value="velo">Vélo</option>
            <option value="other">Autre</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Marque</label>
          <input type="text" id="v-brand" class="form-input" placeholder="Peugeot">
        </div>
        <div class="form-group">
          <label class="form-label">Modèle</label>
          <input type="text" id="v-model" class="form-input" placeholder="206">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Année</label>
          <input type="number" id="v-year" class="form-input" placeholder="2019">
        </div>
        <div class="form-group">
          <label class="form-label">Immatriculation</label>
          <input type="text" id="v-plate" class="form-input" placeholder="AB-123-CD">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Carburant</label>
          <select id="v-fuel" class="form-select">
            ${FUEL_TYPES.map(f => `<option>${f}</option>`).join('')}
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Kilométrage actuel</label>
          <input type="number" id="v-km" class="form-input" placeholder="75000">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date CT (contrôle technique)</label>
          <input type="date" id="v-ct" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Date assurance</label>
          <input type="date" id="v-ins" class="form-input">
        </div>
      </div>
    `, () => {
      const name   = document.getElementById('v-name').value.trim();
      const type   = document.getElementById('v-type').value;
      const brand  = document.getElementById('v-brand').value.trim();
      const model  = document.getElementById('v-model').value.trim();
      const year   = parseInt(document.getElementById('v-year').value) || null;
      const plate  = document.getElementById('v-plate').value.trim().toUpperCase();
      const fuel   = document.getElementById('v-fuel').value;
      const km     = parseInt(document.getElementById('v-km').value) || null;
      const ct     = document.getElementById('v-ct').value || null;
      const ins    = document.getElementById('v-ins').value || null;
      if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.addNested('vehicules', 'items', { name, type, brand, model, year, plate, fuel, mileage: km, controleTechniqueDate: ct, assuranceDate: ins });
      CAP360.UI.toast('Véhicule ajouté', 'success');
      renderTabContent();
    });
  }

  function openEditVehicle(id) {
    const d = data();
    const v = (d.items || []).find(x => x.id === id);
    if (!v) return;
    CAP360.UI.modal('Modifier le véhicule', `
      <div class="form-group">
        <label class="form-label">Nom / Surnom</label>
        <input type="text" id="v-name" class="form-input" value="${v.name}">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Marque</label>
          <input type="text" id="v-brand" class="form-input" value="${v.brand||''}">
        </div>
        <div class="form-group">
          <label class="form-label">Modèle</label>
          <input type="text" id="v-model" class="form-input" value="${v.model||''}">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Kilométrage</label>
          <input type="number" id="v-km" class="form-input" value="${v.mileage||''}">
        </div>
        <div class="form-group">
          <label class="form-label">Immatriculation</label>
          <input type="text" id="v-plate" class="form-input" value="${v.plate||''}">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date CT</label>
          <input type="date" id="v-ct" class="form-input" value="${v.controleTechniqueDate||''}">
        </div>
        <div class="form-group">
          <label class="form-label">Date assurance</label>
          <input type="date" id="v-ins" class="form-input" value="${v.assuranceDate||''}">
        </div>
      </div>
    `, () => {
      const name  = document.getElementById('v-name').value.trim();
      const brand = document.getElementById('v-brand').value.trim();
      const model = document.getElementById('v-model').value.trim();
      const km    = parseInt(document.getElementById('v-km').value) || null;
      const plate = document.getElementById('v-plate').value.trim().toUpperCase();
      const ct    = document.getElementById('v-ct').value || null;
      const ins   = document.getElementById('v-ins').value || null;
      if (!name) { CAP360.UI.toast('Nom requis', 'error'); return false; }
      CAP360.Storage.updateNested('vehicules', 'items', id, { name, brand, model, mileage: km, plate, controleTechniqueDate: ct, assuranceDate: ins });
      CAP360.UI.toast('Véhicule mis à jour', 'success');
      renderTabContent();
    });
  }

  function deleteVehicle(id) {
    if (!confirm('Supprimer ce véhicule et tout son historique ?')) return;
    CAP360.Storage.removeNested('vehicules', 'items', id);
    const d = CAP360.Storage.get();
    d.vehicules.maintenances = (d.vehicules.maintenances || []).filter(m => m.vehicleId !== id);
    d.vehicules.fuelLogs     = (d.vehicules.fuelLogs || []).filter(f => f.vehicleId !== id);
    CAP360.Storage.persist();
    CAP360.UI.toast('Véhicule supprimé');
    renderTabContent();
  }

  function openAddMaintenance() {
    const d = data();
    const vehicles = d.items || [];
    const today = new Date().toISOString().slice(0, 10);
    CAP360.UI.modal('Nouvelle opération', `
      <div class="form-group">
        <label class="form-label">Véhicule</label>
        <select id="mt-v" class="form-select">
          ${vehicles.map(v => `<option value="${v.id}">${v.name}</option>`).join('')}
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date</label>
          <input type="date" id="mt-date" class="form-input" value="${today}">
        </div>
        <div class="form-group">
          <label class="form-label">Type</label>
          <select id="mt-type" class="form-select">
            ${MAINT_TYPES.map(t => `<option>${t}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Kilométrage</label>
          <input type="number" id="mt-km" class="form-input" placeholder="75000">
        </div>
        <div class="form-group">
          <label class="form-label">Coût (€)</label>
          <input type="number" id="mt-cost" class="form-input" placeholder="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea id="mt-notes" class="form-input" rows="2"></textarea>
      </div>
    `, () => {
      const vid   = document.getElementById('mt-v').value;
      const date  = document.getElementById('mt-date').value;
      const type  = document.getElementById('mt-type').value;
      const km    = parseInt(document.getElementById('mt-km').value) || null;
      const cost  = parseFloat(document.getElementById('mt-cost').value) || 0;
      const notes = document.getElementById('mt-notes').value.trim();
      if (!date) { CAP360.UI.toast('Date requise', 'error'); return false; }
      CAP360.Storage.addNested('vehicules', 'maintenances', { vehicleId: vid, date, type, mileage: km, cost, notes });
      if (km) CAP360.Storage.updateNested('vehicules', 'items', vid, { mileage: km });
      CAP360.UI.toast('Opération enregistrée', 'success');
      renderTabContent();
    });
  }

  function deleteMaintenance(id) {
    CAP360.Storage.removeNested('vehicules', 'maintenances', id);
    renderTabContent();
  }

  function openAddFuel() {
    const d = data();
    const vehicles = d.items || [];
    const today = new Date().toISOString().slice(0, 10);
    CAP360.UI.modal('Plein de carburant', `
      <div class="form-group">
        <label class="form-label">Véhicule</label>
        <select id="fl-v" class="form-select">
          ${vehicles.map(v => `<option value="${v.id}">${v.name}</option>`).join('')}
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date</label>
          <input type="date" id="fl-date" class="form-input" value="${today}">
        </div>
        <div class="form-group">
          <label class="form-label">Kilométrage</label>
          <input type="number" id="fl-km" class="form-input" placeholder="75000">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Litres</label>
          <input type="number" id="fl-liters" class="form-input" placeholder="40" step="0.1">
        </div>
        <div class="form-group">
          <label class="form-label">Coût total (€)</label>
          <input type="number" id="fl-cost" class="form-input" placeholder="0" step="0.01">
        </div>
      </div>
    `, () => {
      const vid    = document.getElementById('fl-v').value;
      const date   = document.getElementById('fl-date').value;
      const km     = parseInt(document.getElementById('fl-km').value) || null;
      const liters = parseFloat(document.getElementById('fl-liters').value) || 0;
      const cost   = parseFloat(document.getElementById('fl-cost').value) || 0;
      if (!date) { CAP360.UI.toast('Date requise', 'error'); return false; }
      CAP360.Storage.addNested('vehicules', 'fuelLogs', { vehicleId: vid, date, mileage: km, liters, cost });
      if (km) CAP360.Storage.updateNested('vehicules', 'items', vid, { mileage: km });
      CAP360.UI.toast('Plein enregistré', 'success');
      renderTabContent();
    });
  }

  function deleteFuel(id) {
    CAP360.Storage.removeNested('vehicules', 'fuelLogs', id);
    renderTabContent();
  }

  function selectVehicle(id) {
    _selectedId = id;
    _tab = 'maintenance';
    setTab('maintenance');
  }

  /* ---- Mount ---- */

  function mount(container) {
    _view = container;
    _tab  = 'dashboard';

    _view.innerHTML = `
      <div class="module-header" style="--mod-accent:var(--c-vehicules)">
        <div class="module-header-inner">
          <h1 class="module-title">🚗 Véhicules</h1>
          <p class="module-subtitle">Gestion et suivi de vos véhicules</p>
        </div>
      </div>
      <div class="module-body">
        <div class="seg-control" style="margin-bottom:20px">
          <button class="seg-btn active" data-tab="dashboard" onclick="V.tab('dashboard')">Véhicules</button>
          <button class="seg-btn" data-tab="maintenance" onclick="V.tab('maintenance')">Maintenance</button>
          <button class="seg-btn" data-tab="fuel" onclick="V.tab('fuel')">Carburant</button>
        </div>
        <div id="vehicules-content"></div>
      </div>
    `;

    renderTabContent();
  }

  /* ---- getHealth() — contrat Platform ---- */

  function getHealth() {
    const d      = data();
    const stats  = getStats();
    const today  = new Date().toISOString().slice(0, 10);
    const in30   = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
    const in60   = new Date(Date.now() + 60 * 86400000).toISOString().slice(0, 10);
    const items  = d.items || [];

    /* Score 0-100 */
    let score = stats.count === 0 ? 75 : 100;
    items.forEach(v => {
      if (v.controleTechniqueDate && v.controleTechniqueDate < today) score -= 25;
      else if (v.controleTechniqueDate && v.controleTechniqueDate <= in30) score -= 10;
      if (v.assuranceDate && v.assuranceDate < today) score -= 25;
      else if (v.assuranceDate && v.assuranceDate <= in30) score -= 10;
    });
    score = Math.max(0, Math.min(100, score));

    /* Alerts */
    const alerts = [];
    items.forEach(v => {
      if (v.controleTechniqueDate) {
        if (v.controleTechniqueDate < today) {
          alerts.push({ level: 'danger', message: 'CT expiré pour ' + v.name + ' depuis le ' + v.controleTechniqueDate, action: { label: 'Voir Véhicules', module: 'vehicules' } });
        } else if (v.controleTechniqueDate <= in30) {
          const diff = Math.round((new Date(v.controleTechniqueDate) - new Date()) / 86400000);
          alerts.push({ level: 'warning', message: 'CT ' + v.name + ' dans ' + diff + ' jour(s) (' + v.controleTechniqueDate + ')', action: { label: 'Voir Véhicules', module: 'vehicules' } });
        }
      }
      if (v.assuranceDate) {
        if (v.assuranceDate < today) {
          alerts.push({ level: 'danger', message: 'Assurance expirée pour ' + v.name, action: { label: 'Voir Véhicules', module: 'vehicules' } });
        } else if (v.assuranceDate <= in30) {
          const diff = Math.round((new Date(v.assuranceDate) - new Date()) / 86400000);
          alerts.push({ level: 'warning', message: 'Renouvellement assurance ' + v.name + ' dans ' + diff + ' jour(s)', action: { label: 'Voir Véhicules', module: 'vehicules' } });
        }
      }
    });

    /* Timeline */
    const timeline = [];
    items.forEach(v => {
      if (v.controleTechniqueDate && v.controleTechniqueDate >= today && v.controleTechniqueDate <= in60) {
        timeline.push({ date: v.controleTechniqueDate, type: 'deadline', label: 'Contrôle technique — ' + v.name, icon: '🔧', color: '#FF3B30', amount: null });
      }
      if (v.assuranceDate && v.assuranceDate >= today && v.assuranceDate <= in60) {
        timeline.push({ date: v.assuranceDate, type: 'deadline', label: 'Renouvellement assurance — ' + v.name, icon: '🛡️', color: '#FF9500', amount: null });
      }
    });

    /* Story */
    const storyParts = [];
    if (items.length === 0) {
      storyParts.push('Aucun véhicule enregistré.');
    } else if (stats.allOk) {
      storyParts.push(items.length + ' véhicule(s) — tout est à jour.');
    } else {
      storyParts.push(stats.alerts.length + ' alerte(s) véhicule à traiter.');
    }

    return {
      id:     'vehicules',
      label:  'Véhicules',
      icon:   '🚗',
      accent: '#FF3B30',
      weight: 10,
      score,
      kpis: {
        count:   items.length,
        allOk:   stats.allOk,
        alerts:  stats.alerts,
        items:   items,
        monthCosts: getMonthCosts(),
      },
      alerts,
      timeline,
      story: storyParts.join(' ') || null,
    };
  }

  window.V = {
    tab:              t => { _tab = t; _view.querySelectorAll('.seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === t)); renderTabContent(); },
    openAddVehicle:   openAddVehicle,
    openEditVehicle:  openEditVehicle,
    deleteVehicle:    deleteVehicle,
    selectVehicle:    selectVehicle,
    openAddMaintenance: openAddMaintenance,
    deleteMaintenance:  deleteMaintenance,
    openAddFuel:      openAddFuel,
    deleteFuel:       deleteFuel,
  };

  return { mount, getStats, getHealth };

}());
