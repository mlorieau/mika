/* ============================================
   CAP360 Budget — Module complet
   Lit/écrit cap360_v35 (rétrocompatible)
   ============================================ */

'use strict';

CAP360.Budget = (function () {

  /* ---- Seed data (first-run) ---- */
  const SEED_CATEGORIES = {"Revenus":["Salaire","Santé","Revente","Entrées diverses"],"Dépenses fixes":["Crédit immobilier","Crédit véhicule","Crédit personnel","Crédit consommation","Télécom","Énergie","Banque","Assurances","Famille","Voiture"],"Dépenses variables":["Courses","Mobilité","Shopping","Loisirs","Santé","Maison","Restaurant","Bien-être","Espèces","Services","Famille"],"Transferts":["Enveloppes","Famille","Interne","Courses"],"Investissements":["Assurance-vie","LEP","Épargne long terme"]};
  const SEED_RULES = [{"pattern":"VIR VERS COMPTE EPARG","family":"Transferts","category":"Enveloppes","post":"Charges annuelles","type":"transfert","confidence":100,"envelope":"Charges annuelles"},{"pattern":"VIR EPARGNE","family":"Transferts","category":"Enveloppes","post":"Épargne libre","type":"transfert","confidence":95,"envelope":"Épargne libre"},{"pattern":"VIR PREV MAISON VACANCES","family":"Transferts","category":"Enveloppes","post":"Vacances","type":"transfert","confidence":100,"envelope":"Vacances"},{"pattern":"PLAN ASSUR VIE","family":"Investissements","category":"Assurance-vie","post":"Versement programmé","type":"investissement","confidence":95,"envelope":"Assurance-vie"},{"pattern":"ASSURANCE VIE","family":"Investissements","category":"Assurance-vie","post":"Versement programmé","type":"investissement","confidence":95,"envelope":"Assurance-vie"},{"pattern":"CPAM","family":"Revenus","category":"Santé","post":"Remboursement CPAM","type":"revenu","confidence":100,"envelope":""},{"pattern":"BALOO","family":"Revenus","category":"Santé","post":"Remboursement mutuelle","type":"revenu","confidence":100,"envelope":""},{"pattern":"VINTED","family":"Revenus","category":"Revente","post":"Vinted","type":"revenu","confidence":90,"envelope":""},{"pattern":"LBC FRANCE","family":"Revenus","category":"Revente","post":"Leboncoin","type":"revenu","confidence":95,"envelope":""},{"pattern":"SUPER U","family":"Dépenses variables","category":"Courses","post":"Alimentaire","type":"depense","confidence":100,"envelope":""},{"pattern":"LECLERC","family":"Dépenses variables","category":"Courses","post":"Alimentaire","type":"depense","confidence":95,"envelope":""},{"pattern":"CREDIPAR","family":"Dépenses fixes","category":"Crédit véhicule","post":"Véhicule","type":"depense","confidence":100,"envelope":""},{"pattern":"YOUNITED","family":"Dépenses fixes","category":"Crédit personnel","post":"Younited Credit","type":"depense","confidence":100,"envelope":""},{"pattern":"CONSUMER FINANCE","family":"Dépenses fixes","category":"Crédit consommation","post":"Crédit consommation","type":"depense","confidence":95,"envelope":""},{"pattern":"ONEY BANQUE","family":"Dépenses fixes","category":"Crédit consommation","post":"Oney","type":"depense","confidence":90,"envelope":""},{"pattern":"BOUYGUES","family":"Dépenses fixes","category":"Télécom","post":"Mobile / internet","type":"depense","confidence":100,"envelope":""},{"pattern":"EDF","family":"Dépenses fixes","category":"Énergie","post":"Électricité","type":"depense","confidence":100,"envelope":""},{"pattern":"ECH PRET","family":"Dépenses fixes","category":"Crédit immobilier","post":"Prêt immobilier","type":"depense","confidence":90,"envelope":""},{"pattern":"HABITATION","family":"Dépenses fixes","category":"Assurances","post":"Habitation","type":"depense","confidence":95,"envelope":""},{"pattern":"AUTOMOBILE","family":"Dépenses fixes","category":"Assurances","post":"Assurance auto","type":"depense","confidence":85,"envelope":""},{"pattern":"RETRAIT DAB","family":"Dépenses variables","category":"Espèces","post":"Retrait espèces","type":"depense","confidence":90,"envelope":""},{"pattern":"PHARMACIE","family":"Dépenses variables","category":"Santé","post":"Pharmacie","type":"depense","confidence":90,"envelope":""},{"pattern":"PHARM","family":"Dépenses variables","category":"Santé","post":"Pharmacie","type":"depense","confidence":85,"envelope":""},{"pattern":"AMAZON","family":"Dépenses variables","category":"Shopping","post":"Achats internet","type":"depense","confidence":80,"envelope":""},{"pattern":"ZALANDO","family":"Dépenses variables","category":"Shopping","post":"Vêtements","type":"depense","confidence":95,"envelope":""},{"pattern":"RESTAURANT","family":"Dépenses variables","category":"Restaurant","post":"Restaurant","type":"depense","confidence":85,"envelope":""},{"pattern":"SALAIRE","family":"Revenus","category":"Salaire","post":"Salaire","type":"revenu","confidence":85,"envelope":""}];

  const KEY = 'cap360_v35';
  let _state = null;
  let _activeTab = 'dashboard';
  let _editTxId = null;
  let _editModelId = null;
  let _editEnvId = null;
  let _editAccountId = null;
  let _charts = {};

  /* ---- State management ---- */

  function loadState() {
    try {
      const raw = localStorage.getItem(KEY);
      if (raw) {
        _state = JSON.parse(raw);
        if (!_state.budgets)    _state.budgets = {};
        if (!_state.rules)      _state.rules = SEED_RULES;
        if (!_state.categories) _state.categories = SEED_CATEGORIES;
        if (!_state.accounts)   _state.accounts = [];
        if (!_state.envelopes)  _state.envelopes = _defaultEnvelopes();
        if (!_state.models)     _state.models = [];
        if (!_state.planned)    _state.planned = [];
        return _state;
      }
    } catch(e) { console.error('Budget state load error', e); }

    _state = _defaultState();
    saveState();
    return _state;
  }

  function saveState() {
    localStorage.setItem(KEY, JSON.stringify(_state));
  }

  function _defaultState() {
    return {
      settings: { initialBalance: 0, initialDate: new Date().toISOString().slice(0,10), accountName: 'Compte courant', safeFloor: 800, projectionStart: '' },
      transactions: [],
      planned: [],
      models: [],
      rules: SEED_RULES,
      categories: SEED_CATEGORIES,
      envelopes: _defaultEnvelopes(),
      accounts: [],
      budgets: {},
    };
  }

  function _defaultEnvelopes() {
    return [
      { id: _uid(), name: 'Charges annuelles', type: 'charges', target: 2520, currentManual: 0, monthly: 210, targetDate: '', desc: 'Taxe foncière, eau, assurances...' },
      { id: _uid(), name: 'Vacances',           type: 'projet',  target: 3000, currentManual: 0, monthly: 100, targetDate: '', desc: 'Budget vacances' },
      { id: _uid(), name: 'Épargne libre',       type: 'epargne', target: 5000, currentManual: 0, monthly: 0,   targetDate: '', desc: 'Sécurité / imprévus' },
    ];
  }

  function _uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2,7); }

  /* ---- Helpers ---- */

  function eur(v) { return (Number(v) || 0).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); }
  function fmtDate(d) { return d ? d.split('-').reverse().join('/') : '—'; }
  function fmtMonth(ym) { return ym ? new Date(ym + '-01').toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }) : ''; }
  function lastDayOfMonth(ym) { const [y,m] = ym.split('-').map(Number); const d = new Date(y,m,0); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
  function families() { return Object.keys(_state.categories); }
  function envelopeNames() { return ['', ..._state.envelopes.map(e => e.name)]; }
  function signForFamily(fam) { return fam === 'Revenus' ? 1 : -1; }
  function signedAmount(fam, amount) { return Math.abs(Number(amount || 0)) * signForFamily(fam); }

  /* ---- Month keys ---- */

  function monthKeys() {
    const now = new Date();
    let txDates = _state.transactions.map(t => t.date).filter(Boolean).sort();
    let start = txDates.length ? txDates[0].slice(0,7) : (_state.settings.initialDate || now.toISOString().slice(0,7));
    const endD = new Date(now.getFullYear(), now.getMonth() + 19, 1);
    const endYM = `${endD.getFullYear()}-${String(endD.getMonth()+1).padStart(2,'0')}`;
    const keys = [];
    let [sy, sm] = start.split('-').map(Number);
    const [ey, em] = endYM.split('-').map(Number);
    while (sy < ey || (sy === ey && sm <= em)) {
      keys.push(`${sy}-${String(sm).padStart(2,'0')}`);
      sm++; if (sm > 12) { sm = 1; sy++; }
    }
    return keys;
  }

  /* ---- OFX import ---- */

  function parseOfx(text) {
    return text.split(/<STMTTRN>/i).slice(1).map(b => {
      const get = tag => ((b.match(new RegExp('<'+tag+'>([^\\r\\n<]+)','i')) || [])[1] || '').trim();
      const dateRaw = get('DTPOSTED').slice(0,8).replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
      const amount = parseFloat(get('TRNAMT').replace(',', '.')) || 0;
      const label  = [get('NAME'), get('MEMO')].filter(Boolean).join(' ') || 'Transaction';
      const id     = get('FITID') || _uid();
      return { id, date: dateRaw, amount, label, family: '', category: '', envelope: '', source: 'OFX', note: '' };
    });
  }

  function applyRules(tx) {
    const label = (tx.label || '').toUpperCase();
    let best = null, bestConf = 0;
    for (const rule of _state.rules) {
      if (rule.confidence > bestConf && label.includes(rule.pattern.toUpperCase())) {
        best = rule; bestConf = rule.confidence;
      }
    }
    if (!best) return tx;
    return { ...tx, family: best.family, category: best.category, envelope: best.envelope || '', post: best.post || tx.label };
  }

  /* ---- Balance ---- */

  function projectionStart() {
    const cfg = _state.settings.projectionStart || '';
    const txMax = _state.transactions.map(t => t.date).filter(Boolean).sort().at(-1) || '';
    return (cfg > txMax ? cfg : txMax) || new Date().toISOString().slice(0,10);
  }

  function balanceReal(dateMax) {
    const max = dateMax || '9999-12-31';
    return Number(_state.settings.initialBalance || 0)
      + _state.transactions.filter(t => (t.date || '') <= max).reduce((s, t) => s + Number(t.amount || 0), 0);
  }

  function modelOccurrences() {
    const out = [];
    _state.models.forEach(m => {
      const days = (m.days || '5').split(',').map(x => parseInt(x.trim())).filter(Boolean);
      const start = new Date(m.start || projectionStart());
      const end   = new Date(m.end || new Date(new Date().getFullYear() + 1, 11, 31).toISOString().slice(0,10));
      for (let y = start.getFullYear(); y <= end.getFullYear(); y++) {
        for (let mo = 0; mo < 12; mo++) {
          const month = `${y}-${String(mo+1).padStart(2,'0')}`;
          const ov = (m.overrides || {})[month] || {};
          if (ov.enabled === false) continue;
          const base = new Date(y, mo, 1);
          if (base < new Date(start.getFullYear(), start.getMonth(), 1) || base > new Date(end.getFullYear(), end.getMonth(), 1)) continue;
          if (m.freq === 'yearly' && mo !== start.getMonth()) continue;
          const total = signedAmount(m.family, ov.amount !== undefined ? ov.amount : m.amount);
          const slice = total / days.length;
          days.forEach(day => {
            const last = new Date(y, mo+1, 0).getDate();
            const date = `${month}-${String(Math.min(day, last)).padStart(2,'0')}`;
            if (date >= (m.start || '2000-01-01') && date <= (m.end || '2099-12-31')) {
              out.push({ id: 'gen_'+m.id+'_'+date+'_'+day, modelId: m.id, date, label: m.label, amount: slice, family: m.family, category: m.category, envelope: m.envelope || '', source: 'modèle' });
            }
          });
        }
      }
    });
    return out;
  }

  function allPlans() { return [..._state.planned, ...modelOccurrences()].sort((a,b) => a.date.localeCompare(b.date)); }
  function futurePlans() { const cut = projectionStart(); return allPlans().filter(p => (p.date || '') > cut); }
  function balanceProjected(dateMax) {
    const max = dateMax || '9999-12-31';
    const cut = projectionStart();
    if (max <= cut) return balanceReal(max);
    return balanceReal(cut) + allPlans().filter(p => p.date > cut && p.date <= max).reduce((s,p) => s + Number(p.amount || 0), 0);
  }

  /* ---- Metrics ---- */

  function monthlyIncome() {
    const byM = {};
    _state.transactions.filter(t => t.amount > 0 && t.family === 'Revenus').forEach(t => {
      const m = t.date.slice(0,7); byM[m] = (byM[m] || 0) + t.amount;
    });
    const vals = Object.values(byM);
    return vals.length ? vals.reduce((a,b) => a+b, 0) / vals.length : 0;
  }

  function monthlyExpenses() {
    const byM = {};
    _state.transactions.filter(t => t.amount < 0).forEach(t => {
      const m = t.date.slice(0,7); byM[m] = (byM[m] || 0) + Math.abs(t.amount);
    });
    const vals = Object.values(byM);
    return vals.length ? vals.reduce((a,b) => a+b, 0) / vals.length : 0;
  }

  function monthlyCreditPayments() {
    return _state.models.filter(m => m.family === 'Dépenses fixes' && (m.category||'').toLowerCase().includes('crédit')).reduce((s,m) => s + Math.abs(Number(m.amount||0)), 0);
  }

  function monthlyTransfersToSavings() {
    const byM = {};
    _state.transactions.filter(t => t.amount < 0 && ['Transferts','Investissements'].includes(t.family)).forEach(t => {
      const m = t.date.slice(0,7); byM[m] = (byM[m] || 0) + Math.abs(t.amount);
    });
    const vals = Object.values(byM);
    return vals.length ? vals.reduce((a,b) => a+b, 0) / vals.length : 0;
  }

  function debtRatio() { const inc = monthlyIncome(); return inc > 0 ? Math.round(monthlyCreditPayments() / inc * 100) : 0; }
  function savingsRate() { const inc = monthlyIncome(); return inc > 0 ? Math.round(monthlyTransfersToSavings() / inc * 100) : 0; }

  function totalSavings() { return _state.envelopes.reduce((s,e) => s + envelopeComputed(e.name), 0); }

  function envelopeComputed(name) {
    let cur = Number(_state.envelopes.find(e => e.name === name)?.currentManual || 0);
    _state.transactions.filter(t => t.envelope === name).forEach(t => { cur += -Number(t.amount || 0); });
    _state.planned.filter(t => t.envelope === name && (!t.date || t.date <= projectionStart())).forEach(t => { cur += -Number(t.amount || 0); });
    return cur;
  }

  function monthsRemaining(d) { if (!d) return 1; const n = new Date(), t = new Date(d); return Math.max(1, (t.getFullYear()-n.getFullYear())*12+t.getMonth()-n.getMonth()); }
  function idealMonthly(e) { const cur = envelopeComputed(e.name), rem = Math.max(0, e.target-cur), months = monthsRemaining(e.targetDate); return rem/months; }

  function lowFuture() { const arr = monthKeys().map(m => balanceProjected(lastDayOfMonth(m))); return arr.length ? Math.min(...arr) : 0; }
  function findLowMonth() { const low = lowFuture(); return monthKeys().find(m => Math.abs(balanceProjected(lastDayOfMonth(m)) - low) < 0.01) || null; }

  function healthScore() {
    const bal = balanceReal(), low = lowFuture(), floor = Number(_state.settings.safeFloor || 800);
    const savings = totalSavings(), exp = monthlyExpenses();
    const b = bal < 0 ? 0 : bal < floor ? 10 : bal < floor*2 ? 20 : 30;
    const l = low < 0 ? 0 : low < floor ? 10 : low < floor*1.5 ? 22 : 35;
    const s = Math.min(25, Math.round((exp > 0 ? savings/exp : 0) * 8));
    const mm = Math.min(10, _state.models.length);
    return Math.max(0, Math.min(100, b+l+s+mm));
  }

  /* ---- Chart helpers ---- */

  function destroyChart(id) { if (_charts[id]) { _charts[id].destroy(); delete _charts[id]; } }

  /* ---- Expose global handlers for inline onclick ---- */

  function _expose() {
    window.B = {
      goTab,
      handleOfxFile, exportCsv,
      openTxModal, saveTx, deleteTx,
      openModelModal, saveModel, deleteModel, deleteModelById, syncModelMonthAmounts,
      openEnvelopeModal, saveEnvelope,
      openAccountModal, saveAccount, deleteAccount, resetSavingsBalances,
      setBudget,
      updateSettings, saveBackup, restoreBackup, resetData,
      addFamily, deleteFamily, addCat, deleteCat,
      populateCategoryB,
      closeBModal,
    };
  }

  function closeBModal(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }

  /* ---- Mount ---- */

  function mount(container) {
    loadState();
    _expose();
    container.innerHTML = _templateShell();
    goTab(_activeTab);
  }

  /* ---- Tab navigation ---- */

  function goTab(id) {
    _activeTab = id;
    const nav = document.querySelectorAll('.budget-sub-tab');
    nav.forEach(b => b.classList.toggle('active', b.dataset.tab === id));
    const pane = document.getElementById('budget-pane');
    if (!pane) return;
    switch(id) {
      case 'dashboard':    renderDashboard(pane); break;
      case 'transactions': renderTransactions(pane); break;
      case 'forecasts':    renderForecasts(pane); break;
      case 'assets':       renderAssets(pane); break;
      case 'calendar':     renderCalendar(pane); break;
      case 'settings':     renderSettings(pane); break;
    }
  }

  /* ---- Shell template ---- */

  function _templateShell() {
    return `
      <div class="budget-module">
        <div class="module-header">
          <div class="module-title-block">
            <h1>💰 Budget</h1>
            <p>Puis-je continuer comme ça ?</p>
          </div>
          <div class="module-actions">
            <label class="btn btn-primary btn-sm" style="cursor:pointer">
              📂 Importer OFX
              <input type="file" accept=".ofx,.txt,.qfx" onchange="B.handleOfxFile(event)" style="display:none">
            </label>
            <button class="btn btn-secondary btn-sm" onclick="B.exportCsv()">⬇ CSV</button>
          </div>
        </div>

        <div class="sub-nav">
          <button class="sub-nav-tab budget-sub-tab active" data-tab="dashboard"    onclick="B.goTab('dashboard')">Tableau de bord</button>
          <button class="sub-nav-tab budget-sub-tab" data-tab="transactions" onclick="B.goTab('transactions')">Transactions</button>
          <button class="sub-nav-tab budget-sub-tab" data-tab="forecasts"   onclick="B.goTab('forecasts')">Prévisions</button>
          <button class="sub-nav-tab budget-sub-tab" data-tab="assets"      onclick="B.goTab('assets')">Comptes & Enveloppes</button>
          <button class="sub-nav-tab budget-sub-tab" data-tab="calendar"    onclick="B.goTab('calendar')">Calendrier</button>
          <button class="sub-nav-tab budget-sub-tab" data-tab="settings"    onclick="B.goTab('settings')">Paramètres</button>
        </div>

        <div id="budget-pane"></div>

        ${_modalsTemplate()}
      </div>
    `;
  }

  /* ---- Dashboard ---- */

  function renderDashboard(pane) {
    const bal  = balanceReal();
    const low  = lowFuture();
    const lowM = findLowMonth();
    const score = healthScore();
    const floor = Number(_state.settings.safeFloor || 800);
    const freedom = Math.round(bal / Math.max(1, monthlyExpenses() / 30));
    const debt = debtRatio(), srate = savingsRate();
    const now = new Date();
    const cm = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const cmIncome   = _state.transactions.filter(t => t.date.startsWith(cm) && t.amount > 0).reduce((s,t) => s+t.amount, 0);
    const cmExpenses = _state.transactions.filter(t => t.date.startsWith(cm) && t.amount < 0).reduce((s,t) => s+Math.abs(t.amount), 0);
    const scoreColor = score >= 70 ? 'var(--c-positive)' : score >= 40 ? 'var(--c-warning)' : 'var(--c-negative)';
    const scoreEmoji = score >= 70 ? '💚' : score >= 40 ? '🟡' : '🔴';

    const months6 = []; for (let i=5;i>=0;i--){const d=new Date(now.getFullYear(),now.getMonth()-i,1);months6.push(`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`);}
    const revs = months6.map(m => _state.transactions.filter(t => t.date.startsWith(m) && t.amount > 0).reduce((s,t) => s+t.amount, 0));
    const deps = months6.map(m => _state.transactions.filter(t => t.date.startsWith(m) && t.amount < 0).reduce((s,t) => s+Math.abs(t.amount), 0));
    const monthLabels = months6.map(m => new Date(m+'-01').toLocaleDateString('fr-FR',{month:'short'}));

    const byCat = {};
    _state.transactions.filter(t => t.date.startsWith(cm) && t.amount < 0).forEach(t => {
      const k = t.category || t.family || '—'; byCat[k] = (byCat[k] || 0) + Math.abs(t.amount);
    });
    const topCats = Object.entries(byCat).sort((a,b) => b[1]-a[1]).slice(0,8);

    const alerts = [];
    if (bal < floor) alerts.push({ type:'danger', icon:'⚠️', title:'Solde sous le plancher de sécurité', desc:`Solde actuel ${eur(bal)} — plancher ${eur(floor)}` });
    if (low < 0) alerts.push({ type:'danger', icon:'📉', title:`Point bas négatif prévu`, desc:`${eur(low)} en ${lowM ? fmtMonth(lowM) : 'prochains mois'}` });
    if (debt > 35) alerts.push({ type:'warning', icon:'🔴', title:`Taux d'endettement élevé : ${debt}%`, desc:'Recommandé : < 33% des revenus' });
    if (_state.transactions.length === 0) alerts.push({ type:'info', icon:'📂', title:'Aucune transaction importée', desc:'Importez un fichier OFX depuis votre banque pour commencer' });

    pane.innerHTML = `
      <div class="grid-4 mb-lg">
        <div class="kpi-card">
          <div class="kpi-label">Solde réel</div>
          <div class="kpi-value" style="color:${bal<0?'var(--c-negative)':'var(--c-text-1)'}">${eur(bal)}</div>
          <div class="kpi-change ${bal>=0?'pos':'neg'}">${bal>=0?'▲ Positif':'▼ Négatif'}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Point bas prévu</div>
          <div class="kpi-value" style="color:${low<floor?'var(--c-negative)':'var(--c-positive)'}">${eur(low)}</div>
          <div class="text-sm text-2 mt-sm">${lowM ? fmtMonth(lowM) : '—'}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Autonomie financière</div>
          <div class="kpi-value">${freedom} jours</div>
          <div class="text-sm text-2 mt-sm">au rythme actuel</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Score santé ${scoreEmoji}</div>
          <div class="kpi-value" style="color:${scoreColor}">${score}<span style="font-size:20px;font-weight:500">/100</span></div>
          <div class="text-sm text-2 mt-sm">Taux d'endett. ${debt}% · Épargne ${srate}%</div>
        </div>
      </div>

      <div class="grid-3 mb-lg">
        <div class="kpi-card">
          <div class="kpi-label">Ce mois — Revenus</div>
          <div class="kpi-value" style="font-size:22px;color:var(--c-positive)">${eur(cmIncome)}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Ce mois — Dépenses</div>
          <div class="kpi-value" style="font-size:22px;color:var(--c-negative)">${eur(cmExpenses)}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Ce mois — Balance</div>
          <div class="kpi-value" style="font-size:22px;color:${cmIncome-cmExpenses>=0?'var(--c-positive)':'var(--c-negative)'}">${eur(cmIncome-cmExpenses)}</div>
        </div>
      </div>

      <div class="grid-2 mb-lg">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Revenus vs Dépenses — 6 mois</div>
          </div>
          <div class="chart-wrap" style="height:220px">
            <canvas id="bgt-revdep-chart"></canvas>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Répartition ce mois</div>
          </div>
          ${topCats.length ? `
            <div class="chart-wrap" style="height:160px;margin-bottom:16px">
              <canvas id="bgt-donut-chart"></canvas>
            </div>
            <div>
              ${topCats.map(([cat, amt]) => `
                <div class="stat-row">
                  <div class="stat-row-label">${cat}</div>
                  <div class="stat-row-value" style="color:var(--c-negative)">${eur(amt)}</div>
                </div>
              `).join('')}
            </div>
          ` : '<div class="empty-state" style="padding:40px"><div class="empty-desc">Aucune dépense ce mois.</div></div>'}
        </div>
      </div>

      <div class="card mb-lg">
        <div class="card-header"><div class="card-title">Budget mensuel par famille</div></div>
        <div id="budgetRows"></div>
      </div>

      ${alerts.length ? `
        <div class="card">
          <div class="card-header"><div class="card-title">Alertes</div></div>
          ${alerts.map(a => `
            <div class="alert-card ${a.type}">
              <div class="alert-icon">${a.icon}</div>
              <div class="alert-text">
                <div class="alert-title">${a.title}</div>
                <div class="alert-desc">${a.desc}</div>
              </div>
            </div>
          `).join('')}
        </div>
      ` : ''}
    `;

    renderBudgetRows();
    _drawRevDepChart(monthLabels, revs, deps);
    if (topCats.length) _drawDonutChart(topCats);
  }

  function renderBudgetRows() {
    const now = new Date();
    const cm = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const el = document.getElementById('budgetRows');
    if (!el) return;
    const spendFamilies = ['Dépenses fixes','Dépenses variables'];
    const allFams = [...new Set([...Object.keys(_state.budgets), ...spendFamilies.filter(f => _state.categories[f] || _state.transactions.some(t => t.family===f))])];

    el.innerHTML = allFams.map(fam => {
      const spent  = _state.transactions.filter(t => t.date.startsWith(cm) && t.family===fam && t.amount<0).reduce((s,t) => s+Math.abs(t.amount), 0);
      const budget = Number(_state.budgets[fam] || 0);
      const pct    = budget > 0 ? Math.min(150, Math.round(spent/budget*100)) : 0;
      const pctDisplay = Math.min(100, pct);
      const color  = pct > 100 ? 'var(--c-negative)' : pct > 85 ? 'var(--c-warning)' : 'var(--c-positive)';
      const delta  = budget > 0 ? spent - budget : 0;
      return `
        <div style="display:grid;grid-template-columns:180px 130px 1fr 110px 110px;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--c-border)">
          <div style="font-size:14px;font-weight:500">${fam}</div>
          <div><input type="number" value="${budget||''}" placeholder="Budget €/mois" class="form-input" style="height:32px;font-size:13px;padding:4px 10px" onchange="B.setBudget(${JSON.stringify(fam)},this.value)"></div>
          <div class="progress-bar"><div class="progress-fill" style="width:${pctDisplay}%;background:${color}"></div></div>
          <div style="font-size:13px;font-weight:600;text-align:right">${eur(spent)}</div>
          <div style="font-size:13px;font-weight:600;text-align:right;color:${delta>0?'var(--c-negative)':'var(--c-positive)'}">${budget>0?(delta>0?'▲ '+eur(delta):'▼ '+eur(Math.abs(delta))):'—'}</div>
        </div>
      `;
    }).join('') || '<div class="text-sm text-2" style="padding:16px">Aucune famille de dépenses détectée. Importez des transactions OFX d\'abord.</div>';
  }

  function setBudget(fam, val) { _state.budgets[fam] = parseFloat(val) || 0; saveState(); CAP360.UI && CAP360.UI.toast('Budget enregistré'); }

  function _drawRevDepChart(labels, revs, deps) {
    destroyChart('bgt-revdep-chart');
    const ctx = document.getElementById('bgt-revdep-chart'); if (!ctx) return;
    _charts['bgt-revdep-chart'] = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: [
        { label: 'Revenus',  data: revs, backgroundColor: 'rgba(52,199,89,0.7)', borderRadius: 6, borderSkipped: false },
        { label: 'Dépenses', data: deps, backgroundColor: 'rgba(255,59,48,0.65)', borderRadius: 6, borderSkipped: false },
      ]},
      options: { responsive:true, maintainAspectRatio:false, animation:{duration:400}, plugins:{legend:{position:'top',align:'end',labels:{boxWidth:10,boxHeight:10,font:{size:12},color:'#636366',useBorderRadius:true,borderRadius:5}}}, scales:{x:{grid:{display:false},ticks:{color:'#aeaeb2',maxRotation:0},border:{display:false}},y:{grid:{color:'rgba(60,60,67,0.07)',borderDash:[3,3]},ticks:{color:'#aeaeb2',callback:v=>v===0?'0':v>=1000?Math.round(v/1000)+'k':v},border:{display:false}}} },
    });
  }

  function _drawDonutChart(topCats) {
    destroyChart('bgt-donut-chart');
    const ctx = document.getElementById('bgt-donut-chart'); if (!ctx) return;
    const COLORS = ['#007AFF','#34C759','#FF9500','#FF3B30','#BF5AF2','#5AC8FA','#FF2D55','#FFD60A'];
    _charts['bgt-donut-chart'] = new Chart(ctx, {
      type: 'doughnut',
      data: { labels: topCats.map(c=>c[0]), datasets: [{ data: topCats.map(c=>c[1]), backgroundColor: COLORS, borderColor:'#fff', borderWidth:3, hoverOffset:6 }] },
      options: { responsive:true, maintainAspectRatio:false, cutout:'70%', animation:{duration:400}, plugins:{legend:{display:false},tooltip:{backgroundColor:'#1c1c1e',titleColor:'#aeaeb2',bodyColor:'#fff',bodyFont:{size:12},padding:8,cornerRadius:8,callbacks:{label:c=>` ${eur(c.raw)}`}}} },
    });
  }

  /* ---- Transactions ---- */

  function handleOfxFile(e) {
    const f = e.target.files[0]; if (!f) return;
    const r = new FileReader();
    r.onload = () => {
      const ops = parseOfx(r.result);
      const existing = new Set(_state.transactions.map(t => t.id));
      let added = 0;
      ops.forEach(op => { if (!existing.has(op.id)) { _state.transactions.push(applyRules(op)); added++; } });
      _state.transactions.sort((a,b) => (a.date||'').localeCompare(b.date||''));
      if (_state.transactions.length) _state.settings.projectionStart = _state.transactions.map(t=>t.date).filter(Boolean).sort().at(-1) || '';
      saveState();
      goTab(_activeTab);
      CAP360.UI && CAP360.UI.toast(`${added} transaction(s) importée(s)`, 'success');
    };
    r.readAsText(f);
  }

  function exportCsv() {
    const rows = [['Date','Libellé','Famille','Catégorie','Montant','Note']];
    _state.transactions.slice().reverse().forEach(t => rows.push([t.date, `"${(t.label||'').replace(/"/g,'""')}"`, t.family||'', t.category||'', t.amount, t.note||'']));
    const csv = rows.map(r => r.join(';')).join('\n');
    const a = document.createElement('a'); a.href = 'data:text/csv;charset=utf-8,﻿'+encodeURIComponent(csv); a.download = 'cap360-transactions-'+new Date().toISOString().slice(0,10)+'.csv'; a.click();
    CAP360.UI && CAP360.UI.toast('Export CSV téléchargé', 'success');
  }

  function renderTransactions(pane) {
    if (!pane) pane = document.getElementById('budget-pane');
    const search = document.getElementById('tx-search-inp')?.value?.toLowerCase() || '';
    const famFilter = document.getElementById('tx-fam-filter')?.value || '';
    const fams = [...new Set(_state.transactions.map(t => t.family).filter(Boolean))].sort();

    let rows = _state.transactions.slice().reverse();
    if (search) rows = rows.filter(t => (t.label||'').toLowerCase().includes(search) || (t.family||'').toLowerCase().includes(search) || (t.post||'').toLowerCase().includes(search));
    if (famFilter) rows = rows.filter(t => t.family === famFilter);
    rows = rows.slice(0, 500);

    pane.innerHTML = `
      <div class="flex items-center gap-md mb-lg" style="flex-wrap:wrap">
        <div class="search-bar" style="flex:1;min-width:240px">
          <span class="search-icon">🔍</span>
          <input type="text" id="tx-search-inp" placeholder="Rechercher..." oninput="B.goTab('transactions')" value="${search}">
        </div>
        <select class="form-select" id="tx-fam-filter" style="width:200px" onchange="B.goTab('transactions')">
          <option value="">Toutes les familles</option>
          ${fams.map(f => `<option value="${f}"${f===famFilter?' selected':''}>${f}</option>`).join('')}
        </select>
        <div class="text-sm text-2">${rows.length} transaction(s)</div>
      </div>

      <div class="card" style="padding:0">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Libellé bancaire</th>
              <th>Famille</th>
              <th>Catégorie</th>
              <th style="text-align:right">Montant</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(t => `
              <tr onclick="B.openTxModal('${t.id}')" style="cursor:pointer">
                <td style="white-space:nowrap;color:var(--c-text-2)">${fmtDate(t.date)}</td>
                <td style="max-width:300px" class="truncate" title="${t.label||''}">${t.post||t.label||'—'}</td>
                <td>${t.family ? `<span class="badge badge-${_famColor(t.family)}">${t.family}</span>` : '<span class="text-3">—</span>'}</td>
                <td class="text-sm text-2">${t.category||'—'}</td>
                <td style="text-align:right" class="${t.amount>=0?'amount-positive':'amount-negative'} fw-600">${eur(t.amount)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        ${rows.length===0 ? '<div class="empty-state"><div class="empty-icon">📥</div><div class="empty-title">Aucune transaction</div><div class="empty-desc">Importez un fichier OFX depuis votre espace bancaire.</div></div>' : ''}
      </div>
    `;
  }

  function _famColor(fam) {
    if (fam === 'Revenus') return 'green';
    if (fam === 'Dépenses fixes') return 'red';
    if (fam === 'Dépenses variables') return 'orange';
    if (fam === 'Transferts') return 'blue';
    if (fam === 'Investissements') return 'purple';
    return 'gray';
  }

  function openTxModal(id) {
    _editTxId = id;
    const tx = _state.transactions.find(t => t.id === id); if (!tx) return;
    _fillSelect('txFamily', families(), tx.family || '');
    _populateCategory('txFamily', 'txCategory', tx.category || '');
    _fillSelect('txEnvelope', envelopeNames(), tx.envelope || '');
    _setVal('txDate', tx.date || '');
    _setVal('txAmount', tx.amount || 0);
    _setVal('txLabel', tx.label || '');
    _setVal('txNote', tx.note || '');
    document.getElementById('bgt-tx-modal').style.display = 'flex';
  }

  function saveTx() {
    const tx = _state.transactions.find(t => t.id === _editTxId); if (!tx) return;
    tx.date = _getVal('txDate');
    tx.amount = parseFloat(_getVal('txAmount') || 0);
    tx.label = _getVal('txLabel');
    tx.family = _getVal('txFamily');
    tx.category = _getVal('txCategory');
    tx.envelope = _getVal('txEnvelope');
    tx.note = _getVal('txNote');
    tx.post = tx.label;
    _state.transactions.sort((a,b) => (a.date||'').localeCompare(b.date||''));
    closeBModal('bgt-tx-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Transaction mise à jour', 'success');
  }

  function deleteTx() {
    if (!confirm('Supprimer cette transaction ?')) return;
    _state.transactions = _state.transactions.filter(t => t.id !== _editTxId);
    closeBModal('bgt-tx-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Transaction supprimée');
  }

  /* ---- Forecasts (Modèles récurrents) ---- */

  function renderForecasts(pane) {
    if (!pane) pane = document.getElementById('budget-pane');
    const order = ['Revenus','Dépenses fixes','Dépenses variables','Transferts','Investissements'];

    const projData = [];
    const mKeys = monthKeys().slice(0, 12);
    mKeys.forEach(m => {
      projData.push({ label: new Date(m+'-01').toLocaleDateString('fr-FR',{month:'short',year:'2-digit'}), balance: balanceProjected(lastDayOfMonth(m)) });
    });

    let modelsHtml = '';
    order.forEach(sec => {
      const arr = _state.models.filter(m => m.family === sec); if (!arr.length) return;
      modelsHtml += `
        <div class="card mb-lg">
          <div class="card-header"><div class="card-title">${sec}</div></div>
          <table class="data-table">
            <thead><tr><th>Libellé</th><th>Montant</th><th>Jours</th><th>Période</th><th>Catégorie</th><th></th></tr></thead>
            <tbody>
              ${arr.map(m => `
                <tr>
                  <td><b>${m.label}</b>${m.auto ? ' <span class="badge badge-blue" style="font-size:10px">OFX</span>' : ''}</td>
                  <td class="${signedAmount(m.family,m.amount)>=0?'amount-positive':'amount-negative'} fw-600">${eur(signedAmount(m.family,m.amount))}</td>
                  <td class="text-sm text-2">${m.days}</td>
                  <td class="text-sm text-2">${m.start||'?'} → ${m.end||'?'}</td>
                  <td class="text-sm text-2">${m.category||'—'}</td>
                  <td style="white-space:nowrap">
                    <button class="btn btn-secondary btn-sm" onclick="B.openModelModal('${m.id}')">Modifier</button>
                    <button class="btn btn-danger btn-sm" onclick="B.deleteModelById('${m.id}')">Suppr.</button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    });

    pane.innerHTML = `
      <div class="module-header" style="margin-bottom:var(--sp-lg)">
        <div></div>
        <button class="btn btn-primary btn-sm" onclick="B.openModelModal()">+ Modèle récurrent</button>
      </div>

      <div class="card mb-lg">
        <div class="card-header"><div class="card-title">Solde prévisionnel — 12 mois</div></div>
        <div class="chart-wrap" style="height:240px"><canvas id="bgt-forecast-chart"></canvas></div>
      </div>

      ${modelsHtml || '<div class="card"><div class="empty-state"><div class="empty-icon">📋</div><div class="empty-title">Aucun modèle récurrent</div><div class="empty-desc">Créez des modèles pour les charges fixes, abonnements, salaires…</div></div></div>'}
    `;

    _drawForecastChart(projData);
  }

  function _drawForecastChart(data) {
    destroyChart('bgt-forecast-chart');
    const ctx = document.getElementById('bgt-forecast-chart'); if (!ctx) return;
    const values = data.map(d => d.balance);
    const floor  = Number(_state.settings.safeFloor || 800);
    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 240);
    gradient.addColorStop(0, 'rgba(0,122,255,0.15)');
    gradient.addColorStop(1, 'rgba(0,122,255,0)');

    _charts['bgt-forecast-chart'] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(d => d.label),
        datasets: [
          { label: 'Solde projeté', data: values, borderColor: '#007AFF', borderWidth: 2.5, backgroundColor: gradient, fill: true, tension: 0.35, pointRadius: 4, pointBackgroundColor: '#007AFF', pointBorderColor: '#fff', pointBorderWidth: 2 },
          { label: 'Plancher', data: Array(data.length).fill(floor), borderColor: 'rgba(255,59,48,0.5)', borderWidth: 1.5, borderDash: [6,3], pointRadius: 0, fill: false },
        ],
      },
      options: { responsive:true, maintainAspectRatio:false, animation:{duration:400}, interaction:{intersect:false,mode:'index'}, plugins:{legend:{display:true,position:'top',align:'end',labels:{boxWidth:10,boxHeight:10,font:{size:12},color:'#636366',useBorderRadius:true,borderRadius:5}},tooltip:{backgroundColor:'#1c1c1e',titleColor:'#aeaeb2',bodyColor:'#fff',padding:10,cornerRadius:10,callbacks:{label:c=>` ${c.dataset.label}: ${eur(c.raw)}`}}}, scales:{x:{grid:{display:false},ticks:{color:'#aeaeb2'},border:{display:false}},y:{grid:{color:'rgba(60,60,67,0.07)',borderDash:[3,3]},ticks:{color:'#aeaeb2',callback:v=>v>=1000?Math.round(v/1000)+'k €':v+'  €'},border:{display:false}}} },
    });
  }

  function openModelModal(id) {
    _editModelId = id || null;
    const m = id ? _state.models.find(x => x.id === id) : { id:null, label:'', amount:0, start: new Date().toISOString().slice(0,10), end: new Date(new Date().getFullYear()+1,11,31).toISOString().slice(0,10), days:'5', freq:'monthly', family:'Dépenses fixes', category:'', envelope:'', overrides:{} };

    _fillSelect('mFamily', families(), m.family || families()[0]);
    _populateCategory('mFamily', 'mCategory', m.category || '');
    _fillSelect('mEnvelope', envelopeNames(), m.envelope || '');

    _setVal('mLabel', m.label || '');
    _setVal('mAmount', Math.abs(m.amount || 0));
    _setVal('mStart', m.start || '');
    _setVal('mEnd', m.end || '');
    _setVal('mDays', m.days || '5');
    _setVal('mFreq', m.freq || 'monthly');

    const delBtn = document.getElementById('deleteModelBtn');
    if (delBtn) delBtn.style.display = id ? 'inline-flex' : 'none';

    _renderModelMonths(m);
    document.getElementById('bgt-model-modal').style.display = 'flex';
  }

  function _renderModelMonths(m) {
    const el = document.getElementById('modelMonths'); if (!el) return;
    let html = '';
    monthKeys().forEach(mon => {
      if (mon < (m.start||'').slice(0,7) || mon > (m.end||'').slice(0,7)) return;
      const ov = (m.overrides || {})[mon] || {};
      const enabled = ov.enabled !== false;
      const amount  = ov.amount !== undefined ? ov.amount : Math.abs(m.amount || 0);
      const custom  = ov.amount !== undefined && String(ov.amount) !== String(Math.abs(m.amount || 0));
      html += `<div style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid var(--c-border)">
        <label style="display:flex;align-items:center;gap:6px;min-width:110px;font-size:12px">
          <input type="checkbox" data-mon="${mon}" class="moEnabled" ${enabled?'checked':''}> ${mon}
        </label>
        <input type="number" step="0.01" data-mon="${mon}" class="moAmount" value="${amount}" data-custom="${custom?'1':'0'}" oninput="this.dataset.custom='1'" style="width:90px;padding:4px 8px;border-radius:6px;border:1.5px solid var(--c-border);font-size:12px">
      </div>`;
    });
    el.innerHTML = html;
  }

  function syncModelMonthAmounts() {
    const v = document.getElementById('mAmount')?.value;
    document.querySelectorAll('.moAmount').forEach(inp => { if (inp.dataset.custom !== '1') inp.value = v; });
  }

  function saveModel() {
    const baseAmount = String(Math.abs(parseFloat(document.getElementById('mAmount')?.value || 0)));
    const ov = {};
    document.querySelectorAll('.moEnabled').forEach(ch => {
      const mon = ch.dataset.mon;
      const inp = document.querySelector(`.moAmount[data-mon="${mon}"]`);
      if (!inp) return;
      const amt = String(parseFloat(inp.value || 0));
      if (!ch.checked || (inp.dataset.custom === '1' && amt !== baseAmount)) {
        ov[mon] = { enabled: ch.checked, amount: parseFloat(inp.value || 0) };
      }
    });
    const obj = {
      id:       _editModelId || _uid(),
      label:    _getVal('mLabel'),
      amount:   Math.abs(parseFloat(_getVal('mAmount') || 0)),
      start:    _getVal('mStart'),
      end:      _getVal('mEnd'),
      days:     _getVal('mDays') || '5',
      freq:     _getVal('mFreq') || 'monthly',
      family:   _getVal('mFamily'),
      category: _getVal('mCategory'),
      envelope: _getVal('mEnvelope'),
      auto:     false,
      detectedCount: _editModelId ? (_state.models.find(x => x.id===_editModelId)?.detectedCount||0) : 0,
      detectedMonths: _editModelId ? (_state.models.find(x => x.id===_editModelId)?.detectedMonths||0) : 0,
      overrides: ov,
    };
    if (_editModelId) {
      const idx = _state.models.findIndex(x => x.id === _editModelId);
      if (idx >= 0) _state.models[idx] = obj; else _state.models.push(obj);
    } else { _state.models.push(obj); }
    closeBModal('bgt-model-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Modèle enregistré', 'success');
  }

  function deleteModel() {
    if (!_editModelId) return;
    if (!confirm('Supprimer ce modèle récurrent ?')) return;
    _state.models = _state.models.filter(m => m.id !== _editModelId);
    closeBModal('bgt-model-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Modèle supprimé');
  }

  function deleteModelById(id) {
    if (!confirm('Supprimer ce modèle récurrent ?')) return;
    _state.models = _state.models.filter(m => m.id !== id);
    saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Modèle supprimé');
  }

  /* ---- Assets (Comptes & Enveloppes) ---- */

  function renderAssets(pane) {
    if (!pane) pane = document.getElementById('budget-pane');
    const current = { id:'current', name: _state.settings.accountName||'Compte courant', type:'courant', balance: balanceReal() };
    const allAccounts = [current, ...(_state.accounts||[])];
    const savings = totalSavings();

    const mKeys = monthKeys().slice(0, 18);
    const savingsLabels = mKeys.map(m => new Date(m+'-01').toLocaleDateString('fr-FR',{month:'short',year:'2-digit'}));
    const accountColors = ['linear-gradient(135deg,#007AFF,#5856D6)','linear-gradient(135deg,#34C759,#30D158)','linear-gradient(135deg,#FF9500,#FF6000)','linear-gradient(135deg,#FF3B30,#FF2D55)','linear-gradient(135deg,#BF5AF2,#9969FF)','linear-gradient(135deg,#5AC8FA,#007AFF)'];

    pane.innerHTML = `
      <div class="module-header" style="margin-bottom:var(--sp-lg)">
        <div></div>
        <div class="module-actions">
          <button class="btn btn-secondary btn-sm" onclick="B.openAccountModal()">+ Compte / Placement</button>
          <button class="btn btn-secondary btn-sm" onclick="B.openEnvelopeModal()">+ Enveloppe</button>
          <button class="btn btn-ghost btn-sm" onclick="B.resetSavingsBalances()">Remettre à 0</button>
        </div>
      </div>

      <div class="grid-${Math.min(3, allAccounts.length)} mb-lg">
        ${allAccounts.map((a, i) => `
          <div class="account-card" style="background:${accountColors[i%accountColors.length]}" onclick="B.openAccountModal('${a.id}')">
            <div>
              <div class="account-type">${a.type}</div>
              <div class="account-name">${a.name}</div>
            </div>
            <div class="account-balance">${eur(a.balance)}</div>
            ${a.target ? `<div class="account-number">Objectif : ${eur(a.target)}</div>` : ''}
          </div>
        `).join('')}
        <div class="account-card" style="background:linear-gradient(135deg,#636366,#48484A)">
          <div>
            <div class="account-type">enveloppes</div>
            <div class="account-name">Total épargne</div>
          </div>
          <div class="account-balance">${eur(savings)}</div>
          <div class="account-number">${_state.envelopes.length} enveloppe(s)</div>
        </div>
      </div>

      <div class="card mb-lg">
        <div class="card-header"><div class="card-title">Projection épargne — 18 mois</div></div>
        <div class="chart-wrap" style="height:200px"><canvas id="bgt-savings-chart"></canvas></div>
      </div>

      <div class="grid-3">
        ${_state.envelopes.map(e => {
          const cur = envelopeComputed(e.name);
          const pct = e.target > 0 ? Math.min(100, Math.round(cur/e.target*100)) : 0;
          const monthsLeft = monthsRemaining(e.targetDate);
          const ideal = idealMonthly(e);
          return `
            <div class="envelope-card">
              <div class="envelope-header">
                <div>
                  <div class="envelope-name">${e.name}</div>
                  <div class="text-xs text-2">${e.type}</div>
                </div>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="B.openEnvelopeModal('${e.id}')">✏️</button>
              </div>
              <div class="envelope-amounts"><strong>${eur(cur)}</strong> / ${eur(e.target)}</div>
              <div class="progress-bar mt-sm">
                <div class="progress-fill" style="width:${pct}%;background:${pct>=100?'var(--c-positive)':pct>75?'var(--c-warning)':'var(--c-budget)'}"></div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:12px;color:var(--c-text-2)">
                <span>${pct}% atteint</span>
                <span>${monthsLeft} mois restants</span>
                <span>Idéal : ${eur(ideal)}/mois</span>
              </div>
              ${e.desc ? `<div class="text-xs text-3" style="margin-top:6px">${e.desc}</div>` : ''}
            </div>
          `;
        }).join('')}
      </div>
    `;

    _drawSavingsChart(savingsLabels);
  }

  function _drawSavingsChart(labels) {
    destroyChart('bgt-savings-chart');
    const ctx = document.getElementById('bgt-savings-chart'); if (!ctx) return;
    const COLORS = ['#007AFF','#34C759','#FF9500','#BF5AF2'];
    const datasets = _state.envelopes.slice(0,4).map((e, i) => ({
      label: e.name,
      data: labels.map((_,j) => envelopeComputed(e.name) + Number(e.monthly||0) * (j+1)),
      borderColor: COLORS[i % COLORS.length],
      backgroundColor: 'transparent',
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 0,
    }));
    _charts['bgt-savings-chart'] = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: { responsive:true, maintainAspectRatio:false, animation:{duration:400}, interaction:{intersect:false,mode:'index'}, plugins:{legend:{position:'bottom',labels:{boxWidth:10,boxHeight:10,font:{size:11},color:'#636366',useBorderRadius:true,borderRadius:5}},tooltip:{backgroundColor:'#1c1c1e',titleColor:'#aeaeb2',bodyColor:'#fff',padding:10,cornerRadius:10,callbacks:{label:c=>` ${c.dataset.label}: ${eur(c.raw)}`}}}, scales:{x:{grid:{display:false},ticks:{color:'#aeaeb2',maxTicksLimit:9},border:{display:false}},y:{grid:{color:'rgba(60,60,67,0.07)',borderDash:[3,3]},ticks:{color:'#aeaeb2',callback:v=>v>=1000?Math.round(v/1000)+'k':v},border:{display:false}}} },
    });
  }

  function openEnvelopeModal(id) {
    _editEnvId = id || null;
    const e = id ? _state.envelopes.find(x => x.id === id) : { name:'', type:'projet', target:0, currentManual:0, monthly:0, targetDate:'', desc:'' };
    _setVal('eName', e.name); _setVal('eType', e.type); _setVal('eTarget', e.target||0);
    _setVal('eManual', e.currentManual||0); _setVal('eMonthly', e.monthly||0);
    _setVal('eTargetDate', e.targetDate||''); _setVal('eDesc', e.desc||'');
    document.getElementById('bgt-env-modal').style.display = 'flex';
  }

  function saveEnvelope() {
    const obj = { id: _editEnvId||_uid(), name:_getVal('eName'), type:_getVal('eType'), target:parseFloat(_getVal('eTarget')||0), currentManual:parseFloat(_getVal('eManual')||0), monthly:parseFloat(_getVal('eMonthly')||0), targetDate:_getVal('eTargetDate'), desc:_getVal('eDesc') };
    if (_editEnvId) { const idx = _state.envelopes.findIndex(x => x.id===_editEnvId); if (idx>=0) _state.envelopes[idx]=obj; } else { _state.envelopes.push(obj); }
    closeBModal('bgt-env-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Enveloppe enregistrée', 'success');
  }

  function openAccountModal(id) {
    _editAccountId = id || null;
    const delBtn = document.getElementById('deleteAccountBtn');
    if (delBtn) delBtn.style.display = (id && id !== 'current') ? 'inline-flex' : 'none';
    const balInput = document.getElementById('aBalance');
    let a;
    if (id === 'current') { a = { name: _state.settings.accountName||'Compte courant', type:'courant', balance: balanceReal(), target:0, targetDate:'', note:'' }; if(balInput) balInput.disabled=true; }
    else if (id) { a = (_state.accounts||[]).find(x => x.id===id); if(balInput) balInput.disabled=false; }
    else { a = { name:'', type:'epargne', balance:0, target:0, targetDate:'', note:'' }; if(balInput) balInput.disabled=false; }
    _setVal('aName', a?.name||''); _setVal('aType', a?.type||'epargne');
    _setVal('aBalance', a?.balance||0); _setVal('aTarget', a?.target||0);
    _setVal('aTargetDate', a?.targetDate||''); _setVal('aNote', a?.note||'');
    document.getElementById('bgt-account-modal').style.display = 'flex';
  }

  function saveAccount() {
    if (_editAccountId === 'current') { _state.settings.accountName = _getVal('aName')||'Compte courant'; closeBModal('bgt-account-modal'); saveState(); goTab(_activeTab); return; }
    const obj = { id:_editAccountId||_uid(), name:_getVal('aName'), type:_getVal('aType'), balance:parseFloat(_getVal('aBalance')||0), target:parseFloat(_getVal('aTarget')||0), targetDate:_getVal('aTargetDate'), note:_getVal('aNote') };
    _state.accounts = _state.accounts || [];
    if (_editAccountId) { const idx=_state.accounts.findIndex(x=>x.id===_editAccountId); if(idx>=0)_state.accounts[idx]=obj; else _state.accounts.push(obj); } else { _state.accounts.push(obj); }
    closeBModal('bgt-account-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Compte enregistré', 'success');
  }

  function deleteAccount() {
    if (!_editAccountId || _editAccountId === 'current') return;
    if (!confirm('Supprimer ce compte / placement ?')) return;
    _state.accounts = (_state.accounts||[]).filter(x => x.id !== _editAccountId);
    closeBModal('bgt-account-modal'); saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Compte supprimé');
  }

  function resetSavingsBalances() {
    if (!confirm('Remettre à zéro les soldes manuels des enveloppes et placements ?')) return;
    _state.envelopes.forEach(e => { e.currentManual = 0; });
    (_state.accounts||[]).forEach(a => { if (['epargne','investissement'].includes(a.type)) a.balance = 0; });
    saveState(); goTab(_activeTab);
    CAP360.UI && CAP360.UI.toast('Soldes remis à zéro');
  }

  /* ---- Calendar ---- */

  function renderCalendar(pane) {
    if (!pane) pane = document.getElementById('budget-pane');
    const now = new Date();
    const defMonth = _state.transactions.length ? _state.transactions.map(t=>t.date).filter(Boolean).sort().at(-1)?.slice(0,7) : now.toISOString().slice(0,7);

    pane.innerHTML = `
      <div class="flex items-center gap-md mb-lg">
        <div class="period-nav">
          <button class="period-btn" onclick="B._calPrev()">‹</button>
          <input type="month" id="calendarMonth" class="form-input" style="width:160px" value="${defMonth}" onchange="B._calRefresh()">
          <button class="period-btn" onclick="B._calNext()">›</button>
        </div>
        <div id="cal-kpis" class="flex gap-md flex-1"></div>
      </div>
      <div class="card mb-lg">
        <div class="chart-wrap" style="height:200px"><canvas id="bgt-cal-chart"></canvas></div>
      </div>
      <div id="calendarTimeline"></div>
    `;

    window.B._calRefresh = _calRefresh;
    window.B._calPrev = () => { const el=document.getElementById('calendarMonth'); if(!el)return; const d=new Date(el.value+'-01'); d.setMonth(d.getMonth()-1); el.value=d.toISOString().slice(0,7); _calRefresh(); };
    window.B._calNext = () => { const el=document.getElementById('calendarMonth'); if(!el)return; const d=new Date(el.value+'-01'); d.setMonth(d.getMonth()+1); el.value=d.toISOString().slice(0,7); _calRefresh(); };
    _calRefresh();
  }

  function _calRefresh() {
    const calM = document.getElementById('calendarMonth'); if(!calM) return;
    const m = calM.value; const y = Number(m.slice(0,4)), mo = Number(m.slice(5,7));
    const last = new Date(y, mo, 0);
    const prevDate = new Date(y, mo-1, 0); const prevStr = `${prevDate.getFullYear()}-${String(prevDate.getMonth()+1).padStart(2,'0')}-${String(prevDate.getDate()).padStart(2,'0')}`;
    const lastStr  = `${last.getFullYear()}-${String(last.getMonth()+1).padStart(2,'0')}-${String(last.getDate()).padStart(2,'0')}`;
    const openBal = balanceProjected(prevStr);
    const closeBal = balanceProjected(lastStr);

    document.getElementById('cal-kpis').innerHTML = `
      <div class="kpi-card" style="padding:14px 20px"><div class="kpi-label">Solde ouverture</div><div class="kpi-value" style="font-size:18px">${eur(openBal)}</div></div>
      <div class="kpi-card" style="padding:14px 20px"><div class="kpi-label">Solde fermeture</div><div class="kpi-value" style="font-size:18px;color:${closeBal<0?'var(--c-negative)':'var(--c-text-1)'}">${eur(closeBal)}</div></div>
    `;

    const labels = [], vals = [];
    for (let d=1; d<=last.getDate(); d++) {
      const ds = `${m}-${String(d).padStart(2,'0')}`;
      labels.push(String(d)); vals.push(balanceProjected(ds));
    }

    destroyChart('bgt-cal-chart');
    const ctx = document.getElementById('bgt-cal-chart'); if (ctx) {
      const gradient = ctx.getContext('2d').createLinearGradient(0,0,0,200);
      gradient.addColorStop(0, 'rgba(0,122,255,0.15)');
      gradient.addColorStop(1, 'rgba(0,122,255,0)');
      _charts['bgt-cal-chart'] = new Chart(ctx, {
        type:'line',
        data:{labels,datasets:[{label:'Solde cumulé',data:vals,borderColor:'#007AFF',backgroundColor:gradient,fill:true,tension:0.35,borderWidth:2,pointRadius:0,pointHoverRadius:4}]},
        options:{responsive:true,maintainAspectRatio:false,animation:{duration:300},interaction:{intersect:false,mode:'index'},plugins:{legend:{display:false},tooltip:{backgroundColor:'#1c1c1e',titleColor:'#aeaeb2',bodyColor:'#fff',padding:10,cornerRadius:10,displayColors:false,callbacks:{title:ctx=>`${ctx[0].label} ${fmtMonth(m)}`,label:ctx=>eur(ctx.raw)}}},scales:{x:{grid:{display:false},ticks:{color:'#aeaeb2',maxTicksLimit:16},border:{display:false}},y:{grid:{color:'rgba(60,60,67,0.07)',borderDash:[3,3]},ticks:{color:'#aeaeb2',callback:v=>v>=1000?Math.round(v/1000)+'k':v},border:{display:false}}}}
      });
    }

    const ev = [
      ..._state.transactions.filter(t => t.date.slice(0,7)===m).map(t => ({...t, kind:'réel'})),
      ...futurePlans().filter(p => p.date.slice(0,7)===m).map(p => ({...p, kind:'prévu'})),
    ].sort((a,b) => a.date.localeCompare(b.date));

    const byDay = {}; ev.forEach(e => { const day=e.date.slice(8); (byDay[day]||(byDay[day]=[])).push(e); });
    document.getElementById('calendarTimeline').innerHTML = Object.keys(byDay).sort().map(day => `
      <div class="mb-md">
        <div class="text-sm fw-600 text-2" style="margin-bottom:8px">📅 ${fmtDate(`${m}-${day}`)}</div>
        <div class="card" style="padding:0">
          ${byDay[day].map(e => `
            <div class="tx-row" style="padding:10px 16px">
              <div class="tx-info">
                <div class="tx-name">${e.post||e.label||'—'}</div>
                <div class="tx-meta"><span class="badge ${e.kind==='réel'?'badge-green':'badge-blue'}" style="font-size:10px">${e.kind}</span> ${e.family||''}</div>
              </div>
              <div class="tx-amount ${e.amount>=0?'positive':'negative'}">${eur(e.amount)}</div>
            </div>
          `).join('')}
        </div>
      </div>
    `).join('') || '<div class="empty-state" style="padding:40px"><div class="empty-desc">Aucun événement ce mois.</div></div>';
  }

  /* ---- Settings ---- */

  function renderSettings(pane) {
    if (!pane) pane = document.getElementById('budget-pane');
    pane.innerHTML = `
      <div class="grid-2">
        <div>
          <div class="card mb-lg">
            <div class="card-header"><div class="card-title">Paramètres généraux</div></div>
            <div class="form-group mb-md">
              <label class="form-label">Solde initial (€)</label>
              <input id="initialBalance" type="number" class="form-input" value="${_state.settings.initialBalance||0}">
            </div>
            <div class="form-group mb-md">
              <label class="form-label">Date du solde initial</label>
              <input id="initialDate" type="date" class="form-input" value="${_state.settings.initialDate||''}">
            </div>
            <div class="form-group mb-md">
              <label class="form-label">Nom du compte principal</label>
              <input id="accountName" type="text" class="form-input" value="${_state.settings.accountName||'Compte courant'}">
            </div>
            <div class="form-group mb-md">
              <label class="form-label">Plancher de sécurité (€)</label>
              <input id="safeFloor" type="number" class="form-input" value="${_state.settings.safeFloor||800}">
            </div>
            <div class="form-group mb-lg">
              <label class="form-label">Début des projections</label>
              <input id="projectionStart" type="date" class="form-input" value="${_state.settings.projectionStart||''}">
              <div class="text-xs text-2" style="margin-top:4px">Calculé automatiquement depuis la dernière transaction OFX</div>
            </div>
            <button class="btn btn-primary" onclick="B.updateSettings()">Enregistrer les paramètres</button>
          </div>

          <div class="card">
            <div class="card-header"><div class="card-title">Données</div></div>
            <div class="flex gap-sm" style="flex-wrap:wrap">
              <button class="btn btn-secondary btn-sm" onclick="B.saveBackup()">💾 Sauvegarder</button>
              <label class="btn btn-secondary btn-sm" style="cursor:pointer">
                📂 Restaurer <input type="file" accept=".json" onchange="B.restoreBackup(event)" style="display:none">
              </label>
              <button class="btn btn-danger btn-sm" onclick="B.resetData()">⚠️ Tout réinitialiser</button>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-title">Familles & catégories</div>
            <div class="flex gap-sm">
              <input id="newFamName" type="text" class="form-input" style="height:32px;font-size:13px" placeholder="Nouvelle famille...">
              <button class="btn btn-primary btn-sm" onclick="B.addFamily()">+ Ajouter</button>
            </div>
          </div>
          <div id="catManager"></div>
        </div>
      </div>
    `;
    renderCatManager();
  }

  function updateSettings() {
    _state.settings.initialBalance  = parseFloat(_getVal('initialBalance') || 0);
    _state.settings.initialDate     = _getVal('initialDate');
    _state.settings.accountName     = _getVal('accountName');
    _state.settings.safeFloor       = parseFloat(_getVal('safeFloor') || 800);
    _state.settings.projectionStart = _getVal('projectionStart');
    saveState();
    CAP360.UI && CAP360.UI.toast('Paramètres enregistrés', 'success');
  }

  function saveBackup() {
    const blob = new Blob([JSON.stringify(_state,null,2)], {type:'application/json'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'cap360-budget-'+new Date().toISOString().slice(0,10)+'.json'; a.click();
    CAP360.UI && CAP360.UI.toast('Sauvegarde téléchargée', 'success');
  }

  function restoreBackup(e) {
    const f = e.target.files[0]; if(!f) return;
    const r = new FileReader();
    r.onload = () => {
      try {
        const parsed = JSON.parse(r.result);
        _state = { ..._state, ...parsed };
        if (!_state.budgets) _state.budgets = {};
        saveState(); goTab(_activeTab);
        CAP360.UI && CAP360.UI.toast('Sauvegarde restaurée', 'success');
      } catch(err) { CAP360.UI && CAP360.UI.toast('Fichier invalide', 'error'); }
    };
    r.readAsText(f);
  }

  function resetData() {
    if (!confirm('Tout effacer et réinitialiser CAP360 Budget ? Cette action est irréversible.')) return;
    localStorage.removeItem(KEY); location.reload();
  }

  /* ---- Category management ---- */

  function renderCatManager() {
    const el = document.getElementById('catManager'); if (!el) return;
    el.innerHTML = Object.entries(_state.categories).map(([fam, cats]) => `
      <div style="margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--c-border)">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <b style="font-size:14px">${fam}</b>
          <button class="btn btn-danger btn-sm" onclick="B.deleteFamily(${JSON.stringify(fam)})" style="padding:2px 8px;font-size:11px">× Famille</button>
        </div>
        <div class="tags-wrap">
          ${cats.map(c => `<span class="tag">${c}<button onclick="B.deleteCat(${JSON.stringify(fam)},${JSON.stringify(c)})" style="margin-left:4px;opacity:0.6;font-size:12px">×</button></span>`).join('')}
          <button class="tag" style="border:1.5px dashed var(--c-border)" onclick="B.addCat(${JSON.stringify(fam)})">+ Catégorie</button>
        </div>
      </div>
    `).join('');
  }

  function addFamily() {
    const name = document.getElementById('newFamName')?.value.trim();
    if (!name || _state.categories[name]) return;
    _state.categories[name] = []; saveState(); renderCatManager();
    if (document.getElementById('newFamName')) document.getElementById('newFamName').value = '';
    CAP360.UI && CAP360.UI.toast('Famille ajoutée');
  }

  function deleteFamily(fam) {
    if (!confirm(`Supprimer la famille "${fam}" ?`)) return;
    delete _state.categories[fam]; saveState(); renderCatManager();
    CAP360.UI && CAP360.UI.toast('Famille supprimée');
  }

  function addCat(fam) {
    const name = prompt(`Nouvelle catégorie dans "${fam}" :`);
    if (!name) return;
    if (!_state.categories[fam]) _state.categories[fam] = [];
    if (!_state.categories[fam].includes(name)) _state.categories[fam].push(name);
    saveState(); renderCatManager();
    CAP360.UI && CAP360.UI.toast('Catégorie ajoutée');
  }

  function deleteCat(fam, cat) {
    if (!_state.categories[fam]) return;
    _state.categories[fam] = _state.categories[fam].filter(c => c !== cat);
    saveState(); renderCatManager();
    CAP360.UI && CAP360.UI.toast('Catégorie supprimée');
  }

  /* ---- DOM helpers ---- */

  function _fillSelect(id, vals, value) {
    const el = document.getElementById(id); if (!el) return;
    el.innerHTML = vals.map(v => `<option value="${v}">${v||'—'}</option>`).join('');
    el.value = value || '';
  }

  function _populateCategory(famId, catId, val) {
    const famEl = document.getElementById(famId);
    const fam   = famEl ? famEl.value : '';
    _fillSelect(catId, _state.categories[fam] || [], val);
  }

  function populateCategoryB(famId, catId) {
    _populateCategory(famId, catId, document.getElementById(catId)?.value || '');
  }

  function _setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v ?? ''; }
  function _getVal(id)    { const el = document.getElementById(id); return el ? el.value : ''; }

  /* ---- Modals template ---- */

  function _modalsTemplate() {
    return `
      <!-- Transaction modal -->
      <div id="bgt-tx-modal" style="display:none" class="modal-overlay" onclick="if(event.target===this)B.closeBModal('bgt-tx-modal')">
        <div class="modal">
          <div class="modal-header">
            <div class="modal-title">Modifier la transaction</div>
            <button class="modal-close" onclick="B.closeBModal('bgt-tx-modal')">×</button>
          </div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Date</label><input id="txDate" type="date" class="form-input"></div>
            <div class="form-group"><label class="form-label">Montant (€)</label><input id="txAmount" type="number" step="0.01" class="form-input"></div>
          </div>
          <div class="form-group mb-md"><label class="form-label">Libellé</label><input id="txLabel" type="text" class="form-input"></div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Famille</label><select id="txFamily" class="form-select" onchange="B.populateCategoryB('txFamily','txCategory')"></select></div>
            <div class="form-group"><label class="form-label">Catégorie</label><select id="txCategory" class="form-select"></select></div>
          </div>
          <div class="form-group mb-md"><label class="form-label">Enveloppe</label><select id="txEnvelope" class="form-select"></select></div>
          <div class="form-group mb-lg"><label class="form-label">Note</label><input id="txNote" type="text" class="form-input" placeholder="Note optionnelle..."></div>
          <div class="modal-footer">
            <button class="btn btn-danger" onclick="B.deleteTx()">Supprimer</button>
            <button class="btn btn-secondary" onclick="B.closeBModal('bgt-tx-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="B.saveTx()">Enregistrer</button>
          </div>
        </div>
      </div>

      <!-- Model modal -->
      <div id="bgt-model-modal" style="display:none" class="modal-overlay" onclick="if(event.target===this)B.closeBModal('bgt-model-modal')">
        <div class="modal modal-lg">
          <div class="modal-header">
            <div class="modal-title">Modèle récurrent</div>
            <button class="modal-close" onclick="B.closeBModal('bgt-model-modal')">×</button>
          </div>
          <div class="form-group mb-md"><label class="form-label">Libellé</label><input id="mLabel" type="text" class="form-input" placeholder="Loyer, Salaire, Abonnement..."></div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Montant (€, positif)</label><input id="mAmount" type="number" step="0.01" class="form-input" oninput="B.syncModelMonthAmounts()"></div>
            <div class="form-group"><label class="form-label">Fréquence</label><select id="mFreq" class="form-select"><option value="monthly">Mensuel</option><option value="weekly">Hebdomadaire</option><option value="biweekly">Bimensuel</option><option value="quarterly">Trimestriel</option><option value="yearly">Annuel</option></select></div>
          </div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Famille</label><select id="mFamily" class="form-select" onchange="B.populateCategoryB('mFamily','mCategory')"></select></div>
            <div class="form-group"><label class="form-label">Catégorie</label><select id="mCategory" class="form-select"></select></div>
          </div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Début</label><input id="mStart" type="date" class="form-input"></div>
            <div class="form-group"><label class="form-label">Fin</label><input id="mEnd" type="date" class="form-input"></div>
          </div>
          <div class="form-row mb-lg">
            <div class="form-group"><label class="form-label">Jours du mois (ex: 5,15,25)</label><input id="mDays" type="text" class="form-input" placeholder="5"></div>
            <div class="form-group"><label class="form-label">Enveloppe</label><select id="mEnvelope" class="form-select"></select></div>
          </div>
          <div class="card-header"><div class="card-title">Surcharges par mois</div></div>
          <div id="modelMonths" style="max-height:200px;overflow-y:auto;padding:8px 0"></div>
          <div class="modal-footer">
            <button id="deleteModelBtn" class="btn btn-danger" onclick="B.deleteModel()" style="display:none">Supprimer</button>
            <button class="btn btn-secondary" onclick="B.closeBModal('bgt-model-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="B.saveModel()">Enregistrer</button>
          </div>
        </div>
      </div>

      <!-- Envelope modal -->
      <div id="bgt-env-modal" style="display:none" class="modal-overlay" onclick="if(event.target===this)B.closeBModal('bgt-env-modal')">
        <div class="modal">
          <div class="modal-header">
            <div class="modal-title">Enveloppe / Objectif</div>
            <button class="modal-close" onclick="B.closeBModal('bgt-env-modal')">×</button>
          </div>
          <div class="form-group mb-md"><label class="form-label">Nom</label><input id="eName" type="text" class="form-input"></div>
          <div class="form-group mb-md"><label class="form-label">Type</label><select id="eType" class="form-select"><option value="projet">Projet</option><option value="epargne">Épargne</option><option value="charges">Charges</option><option value="investissement">Investissement</option></select></div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Objectif (€)</label><input id="eTarget" type="number" class="form-input"></div>
            <div class="form-group"><label class="form-label">Solde manuel actuel (€)</label><input id="eManual" type="number" class="form-input"></div>
          </div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Versement mensuel (€)</label><input id="eMonthly" type="number" class="form-input"></div>
            <div class="form-group"><label class="form-label">Date cible</label><input id="eTargetDate" type="date" class="form-input"></div>
          </div>
          <div class="form-group mb-lg"><label class="form-label">Description</label><input id="eDesc" type="text" class="form-input" placeholder="Vacances d'été, travaux, retraite..."></div>
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="B.closeBModal('bgt-env-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="B.saveEnvelope()">Enregistrer</button>
          </div>
        </div>
      </div>

      <!-- Account modal -->
      <div id="bgt-account-modal" style="display:none" class="modal-overlay" onclick="if(event.target===this)B.closeBModal('bgt-account-modal')">
        <div class="modal">
          <div class="modal-header">
            <div class="modal-title">Compte / Placement</div>
            <button class="modal-close" onclick="B.closeBModal('bgt-account-modal')">×</button>
          </div>
          <div class="form-group mb-md"><label class="form-label">Nom</label><input id="aName" type="text" class="form-input"></div>
          <div class="form-group mb-md"><label class="form-label">Type</label><select id="aType" class="form-select"><option value="courant">Courant</option><option value="epargne">Épargne</option><option value="investissement">Investissement</option><option value="credit">Crédit</option></select></div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Solde actuel (€)</label><input id="aBalance" type="number" class="form-input"></div>
            <div class="form-group"><label class="form-label">Objectif (€)</label><input id="aTarget" type="number" class="form-input"></div>
          </div>
          <div class="form-row mb-md">
            <div class="form-group"><label class="form-label">Date cible</label><input id="aTargetDate" type="date" class="form-input"></div>
            <div class="form-group"><label class="form-label">Note</label><input id="aNote" type="text" class="form-input"></div>
          </div>
          <div class="modal-footer">
            <button id="deleteAccountBtn" class="btn btn-danger" onclick="B.deleteAccount()" style="display:none">Supprimer</button>
            <button class="btn btn-secondary" onclick="B.closeBModal('bgt-account-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="B.saveAccount()">Enregistrer</button>
          </div>
        </div>
      </div>
    `;
  }

  /* ---- Public budget data accessor (legacy) ---- */

  function getStats() {
    loadState();
    return {
      balance:      balanceReal(),
      lowBalance:   lowFuture(),
      score:        healthScore(),
      income:       monthlyIncome(),
      expenses:     monthlyExpenses(),
      debtRatio:    debtRatio(),
      savingsRate:  savingsRate(),
      totalSavings: totalSavings(),
      txCount:      _state.transactions.length,
    };
  }

  /* ---- getHealth() — contrat Platform ---- */

  function getHealth() {
    loadState();

    const bal     = balanceReal();
    const low     = lowFuture();
    const lowM    = findLowMonth();
    const score   = healthScore();
    const inc     = monthlyIncome();
    const exp     = monthlyExpenses();
    const dr      = debtRatio();
    const sr      = savingsRate();
    const sav     = totalSavings();
    const today   = new Date().toISOString().slice(0, 10);
    const in30    = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
    const in60    = new Date(Date.now() + 60 * 86400000).toISOString().slice(0, 10);

    /* Treasury history (60 jours) */
    let treasuryHistory = [];
    try {
      if (CAP360.Engine && CAP360.Engine.getDailyBalanceHistory) {
        treasuryHistory = CAP360.Engine.getDailyBalanceHistory(60);
      }
    } catch (e) { /* ignore */ }

    /* Category breakdown (mois courant) */
    let categoryBreakdown = [];
    try {
      if (CAP360.Engine && CAP360.Engine.getCategoryBreakdown) {
        const startM = today.slice(0, 7) + '-01';
        const endM   = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10);
        categoryBreakdown = CAP360.Engine.getCategoryBreakdown(startM, endM);
      }
    } catch (e) { /* ignore */ }

    /* Upcoming transactions (timeline) */
    const upcoming = futurePlans()
      .filter(p => p.date >= today && p.date <= in60)
      .sort((a, b) => a.date.localeCompare(b.date))
      .slice(0, 20);

    /* Alerts */
    const alerts = [];
    if (bal < 0) {
      alerts.push({ level: 'danger', message: 'Solde négatif : ' + fmtCurrency(bal), action: { label: 'Voir Budget', module: 'budget' } });
    } else if (bal < 500) {
      alerts.push({ level: 'warning', message: 'Solde faible : ' + fmtCurrency(bal), action: { label: 'Voir Budget', module: 'budget' } });
    }
    if (low < 0) {
      alerts.push({ level: 'danger', message: 'Solde prévisionnel négatif : ' + fmtCurrency(low) + (lowM ? ' en ' + lowM : ''), action: { label: 'Voir Prévisions', module: 'budget' } });
    }
    if (dr > 33) {
      alerts.push({ level: 'warning', message: 'Taux d\'endettement élevé : ' + dr + '%', action: { label: 'Voir Budget', module: 'budget' } });
    }
    if (sr < 5 && inc > 0) {
      alerts.push({ level: 'info', message: 'Taux d\'épargne faible : ' + sr + '%', action: { label: 'Voir Budget', module: 'budget' } });
    }

    /* Timeline events */
    const timeline = upcoming.map(p => ({
      date:   p.date,
      type:   'transaction',
      label:  p.label,
      amount: p.amount,
      icon:   p.amount >= 0 ? '💰' : getCategoryIcon(p.category || p.family),
      color:  p.amount >= 0 ? '#30D158' : '#FF3B30',
    }));

    /* Story */
    const storyParts = [];
    storyParts.push('Solde ' + fmtCurrency(bal) + '.');
    if (lowM && low < bal) {
      storyParts.push('Point bas prévu en ' + lowM + ' (' + fmtCurrency(low) + ').');
    }
    if (sr > 0) storyParts.push('Épargne ' + sr + '% du revenu.');
    if (dr > 20) storyParts.push('Endettement ' + dr + '%.');

    return {
      id:      'budget',
      label:   'Budget',
      icon:    '💰',
      accent:  '#007AFF',
      weight:  30,
      score,
      kpis: {
        balance: bal,
        lowBalance: low,
        income: inc,
        expenses: exp,
        debtRatio: dr,
        savingsRate: sr,
        totalSavings: sav,
        txCount: _state.transactions.length,
        treasuryHistory,
        categoryBreakdown,
      },
      alerts,
      timeline,
      story: storyParts.join(' '),
    };
  }

  function fmtCurrency(v) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v);
  }

  function getCategoryIcon(cat) {
    const map = { Logement: '🏠', Alimentation: '🛒', Transport: '🚌', Assurances: '🛡️', Abonnements: '📱', Santé: '💊', Loisirs: '🎉', Revenus: '💰' };
    return map[cat] || '📋';
  }

  return { mount, getStats, getHealth };

}());
