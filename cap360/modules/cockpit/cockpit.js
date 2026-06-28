/* ============================================
   CAP360 Cockpit — Dashboard de vie
   ============================================ */

'use strict';

CAP360.Cockpit = (function () {

  let _view = null;

  const GREETINGS = {
    morning:   'Bonjour',
    afternoon: 'Bonjour',
    evening:   'Bonsoir',
    night:     'Bonne nuit',
  };

  const QUOTES = [
    '"Ce que vous mesurez s\'améliore." — Peter Drucker',
    '"La liberté financière s\'achète avec de la discipline." — Robert Kiyosaki',
    '"Investissez dans vous-même, c\'est le meilleur investissement." — Warren Buffett',
    '"Prendre soin de sa santé est la meilleure économie." — Samuel Johnson',
    '"Un projet bien planifié est à moitié accompli." — Anonyme',
  ];

  function greeting() {
    const h = new Date().getHours();
    if (h >= 5  && h < 12) return GREETINGS.morning;
    if (h >= 12 && h < 18) return GREETINGS.afternoon;
    if (h >= 18 && h < 22) return GREETINGS.evening;
    return GREETINGS.night;
  }

  function userName() {
    const d = CAP360.Storage.get();
    return (d && d.budgetSettings && d.budgetSettings.userName) || 'Mickaël';
  }

  function randomQuote() {
    return QUOTES[new Date().getDate() % QUOTES.length];
  }

  /* ---- Stats from modules ---- */

  function getBudgetStats() {
    if (CAP360.Budget && typeof CAP360.Budget.getStats === 'function') {
      return CAP360.Budget.getStats();
    }
    return { balance: 0, score: 0, income: 0, expenses: 0, lowBalance: false, savingsRate: 0, totalSavings: 0, txCount: 0 };
  }

  function getSanteStats() {
    if (CAP360.Sante && typeof CAP360.Sante.getStats === 'function') {
      return CAP360.Sante.getStats();
    }
    const d = CAP360.Storage.get().sante;
    const metrics = d.metrics || [];
    const latest  = metrics.slice(-7);
    const stepsAvg  = latest.filter(m => m.type === 'steps').reduce((s, m) => s + m.value, 0) / (latest.filter(m => m.type === 'steps').length || 1);
    const sleepAvg  = latest.filter(m => m.type === 'sleep').reduce((s, m) => s + m.value, 0) / (latest.filter(m => m.type === 'sleep').length || 1);
    const weights   = metrics.filter(m => m.type === 'weight').sort((a, b) => a.date > b.date ? 1 : -1);
    const weight    = weights.length ? weights[weights.length - 1].value : null;
    const appointments = (d.appointments || []).filter(a => a.date >= new Date().toISOString().slice(0, 10));
    const score = computeSanteScore(stepsAvg, sleepAvg);
    return { score, stepsAvg: Math.round(stepsAvg), sleepAvg: sleepAvg.toFixed(1), weight, nextAppt: appointments[0] || null };
  }

  function computeSanteScore(steps, sleep) {
    let s = 5;
    if (steps > 8000)  s += 1.5;
    else if (steps > 5000) s += 0.8;
    if (sleep >= 7 && sleep <= 9) s += 1.5;
    else if (sleep >= 6) s += 0.8;
    if (steps > 10000) s += 0.5;
    if (sleep >= 7.5)  s += 0.5;
    return Math.min(10, Math.round(s * 10) / 10);
  }

  function getMaisonStats() {
    if (CAP360.Maison && typeof CAP360.Maison.getStats === 'function') {
      return CAP360.Maison.getStats();
    }
    const d = CAP360.Storage.get().maison;
    const projects  = d.projects || [];
    const active    = projects.filter(p => p.status === 'active' || p.status === 'in_progress');
    const totalBudget = active.reduce((s, p) => s + (p.budget || 0), 0);
    const totalSpent  = active.reduce((s, p) => s + (p.spent || 0), 0);
    const pct = totalBudget > 0 ? Math.round(totalSpent / totalBudget * 100) : 0;
    return { projectCount: projects.length, activeCount: active.length, totalBudget, totalSpent, pct };
  }

  function getProjetsStats() {
    if (CAP360.Projets && typeof CAP360.Projets.getStats === 'function') {
      return CAP360.Projets.getStats();
    }
    const d  = CAP360.Storage.get().projets;
    const items  = d.items || [];
    const active = items.filter(p => p.status !== 'done' && p.status !== 'cancelled');
    const late   = active.filter(p => p.dueDate && p.dueDate < new Date().toISOString().slice(0, 10));
    return { count: items.length, active: active.length, late: late.length };
  }

  function getVehiculesStats() {
    if (CAP360.Vehicules && typeof CAP360.Vehicules.getStats === 'function') {
      return CAP360.Vehicules.getStats();
    }
    const d = CAP360.Storage.get().vehicules;
    const items  = d.items || [];
    const today  = new Date().toISOString().slice(0, 10);
    const alerts = [];
    items.forEach(v => {
      if (v.controleTechniqueDate && v.controleTechniqueDate < today) alerts.push('CT expiré: ' + v.name);
      if (v.assuranceDate && v.assuranceDate < today) alerts.push('Assurance expirée: ' + v.name);
    });
    return { count: items.length, alerts, allOk: alerts.length === 0 };
  }

  function getCoffreStats() {
    if (CAP360.Coffre && typeof CAP360.Coffre.getStats === 'function') {
      return CAP360.Coffre.getStats();
    }
    const d     = CAP360.Storage.get().coffre;
    const docs  = d.documents || [];
    const today = new Date().toISOString().slice(0, 10);
    const in30  = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
    const expiring = docs.filter(doc => doc.expiryDate && doc.expiryDate >= today && doc.expiryDate <= in30);
    return { count: docs.length, expiringSoon: expiring.length };
  }

  /* ---- Upcoming operations (from Budget) ---- */

  function getUpcoming() {
    if (!CAP360.Budget || typeof CAP360.Budget.getUpcoming !== 'function') {
      return getUpcomingFromStorage();
    }
    return CAP360.Budget.getUpcoming();
  }

  function getUpcomingFromStorage() {
    try {
      const raw = localStorage.getItem('cap360_v35');
      if (!raw) return [];
      const s = JSON.parse(raw);
      const today = new Date();
      const in30  = new Date(Date.now() + 30 * 86400000);
      const items = [];
      (s.models || []).forEach(m => {
        let d = new Date(m.nextDate || m.startDate);
        if (!d || isNaN(d)) return;
        let count = 0;
        while (d <= in30 && count < 5) {
          if (d >= today) {
            items.push({
              date:   d.toISOString().slice(0, 10),
              label:  m.label,
              amount: m.amount,
              icon:   m.amount > 0 ? '💰' : getCategoryIcon(m.category),
            });
          }
          const next = new Date(d);
          if (m.freq === 'monthly')  next.setMonth(next.getMonth() + 1);
          else if (m.freq === 'weekly') next.setDate(next.getDate() + 7);
          else if (m.freq === 'annual') next.setFullYear(next.getFullYear() + 1);
          else break;
          d = next;
          count++;
        }
      });
      return items.sort((a, b) => a.date.localeCompare(b.date)).slice(0, 8);
    } catch (e) {
      return [];
    }
  }

  function getCategoryIcon(cat) {
    const map = { Logement: '🏠', Alimentation: '🛒', Transport: '🚌', Assurances: '🛡️', Abonnements: '📱', Santé: '💊', Loisirs: '🎉' };
    return map[cat] || '📋';
  }

  /* ---- Smart decisions / alerts ---- */

  function getDecisions() {
    const decisions = [];
    const bs = getBudgetStats();
    if (bs.debtRatio > 33) decisions.push({ icon: '⚠️', text: `Votre taux d'endettement est de ${Math.round(bs.debtRatio)}% — au-dessus du seuil recommandé de 33%.`, action: 'Voir Budget →' });
    if (bs.balance < 500 && bs.balance >= 0) decisions.push({ icon: '💡', text: `Votre solde est faible (${CAP360.Engine.currency(bs.balance)}). Prévoyez des dépenses avec précaution.`, action: 'Voir Trésorerie →' });
    const vs = getVehiculesStats();
    vs.alerts.forEach(a => decisions.push({ icon: '🚗', text: a, action: 'Voir Véhicules →' }));
    const cs = getCoffreStats();
    if (cs.expiringSoon > 0) decisions.push({ icon: '📄', text: `${cs.expiringSoon} document(s) expirent dans les 30 prochains jours.`, action: 'Voir Coffre →' });
    const ps = getProjetsStats();
    if (ps.late > 0) decisions.push({ icon: '🎯', text: `${ps.late} projet(s) en retard sur leur échéance.`, action: 'Voir Projets →' });
    if (decisions.length === 0) decisions.push({ icon: '✅', text: 'Tout est sous contrôle. Bonne journée !', action: null });
    return decisions.slice(0, 3);
  }

  /* ---- Treasury data ---- */

  function getTreasuryData() {
    try {
      if (CAP360.Engine && typeof CAP360.Engine.getDailyBalanceHistory === 'function') {
        return CAP360.Engine.getDailyBalanceHistory(60);
      }
    } catch (e) { /* ignore */ }
    return [];
  }

  function getSpendingCategories() {
    try {
      if (CAP360.Engine && typeof CAP360.Engine.getCategoryBreakdown === 'function') {
        const now = new Date();
        return CAP360.Engine.getCategoryBreakdown(
          new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10),
          new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
        );
      }
    } catch (e) { /* ignore */ }
    return [];
  }

  /* ---- HTML Building ---- */

  function buildScoreStars(score) {
    const filled = Math.round(score);
    return `<span class="ck-score">${score}<span class="ck-score-denom">/10</span></span>`;
  }

  function tile(id, icon, label, mainHtml, subHtml, sparkData, accentColor, clickModule) {
    const sparkId = `ck-spark-${id}`;
    return `
      <div class="ck-tile" data-accent="${accentColor}" onclick="CAP360.Router.navigate('${clickModule}')" style="--tile-accent:${accentColor}">
        <div class="ck-tile-head">
          <span class="ck-tile-icon">${icon}</span>
          <span class="ck-tile-label" style="color:${accentColor}">${label}</span>
        </div>
        <div class="ck-tile-main">${mainHtml}</div>
        ${subHtml ? `<div class="ck-tile-sub">${subHtml}</div>` : ''}
        <canvas class="ck-sparkline" id="${sparkId}" height="32"></canvas>
      </div>
    `;
  }

  function buildTiles(bs, ss, ms, ps, vs, cs) {
    const budgetMain = `${buildScoreStars(bs.score || 0)}`;
    const budgetSub  = bs.score >= 7 ? '<span class="ck-ok">Très bonne maîtrise</span>' : bs.score >= 5 ? '<span class="ck-warn">À surveiller</span>' : '<span class="ck-bad">En difficulté</span>';

    const santeSub = ss.score >= 7 ? '<span class="ck-ok">En progression</span>' : ss.score >= 5 ? '<span class="ck-warn">À améliorer</span>' : '<span class="ck-bad">Attention</span>';

    const maisonMain = `<span class="ck-big">${ms.pct || 0}%</span>`;
    const maisonSub  = `<span class="ck-muted">${ms.activeCount} projet(s) en cours</span>`;

    const projetsMain = `<span class="ck-big">${ps.active}</span>`;
    const projetsSub  = ps.late > 0 ? `<span class="ck-bad">${ps.late} en retard</span>` : `<span class="ck-ok">${ps.active} en cours</span>`;

    const vehicMain = `<span class="ck-big ck-status-ok">${vs.allOk ? 'Tout est OK' : vs.alerts.length + ' alerte(s)'}</span>`;
    const vehicSub  = vs.allOk ? '<span class="ck-ok">Aucune alerte</span>' : `<span class="ck-bad">${vs.alerts[0]}</span>`;

    const coffreMain = `<span class="ck-big">${cs.count}</span>`;
    const coffreSub  = cs.expiringSoon > 0 ? `<span class="ck-warn">${cs.expiringSoon} doc(s) expir. bientôt</span>` : '<span class="ck-ok">Tout à jour</span>';

    return `
      <div class="ck-tiles">
        ${tile('budget',    '💰', 'Budget',    budgetMain,  budgetSub,  [], '#007AFF', 'budget')}
        ${tile('sante',     '❤️', 'Santé',     buildScoreStars(ss.score || 0), santeSub, [], '#30D158', 'sante')}
        ${tile('maison',    '🏡', 'Maison',    maisonMain,  maisonSub,  [], '#FF9500', 'maison')}
        ${tile('projets',   '🎯', 'Projets',   projetsMain, projetsSub, [], '#BF5AF2', 'projets')}
        ${tile('vehicules', '🚗', 'Véhicules', vehicMain,   vehicSub,   [], '#FF3B30', 'vehicules')}
        ${tile('coffre',    '📄', 'Coffre',    coffreMain,  coffreSub,  [], '#5AC8FA', 'coffre')}
      </div>
    `;
  }

  function buildUpcoming(items) {
    if (!items.length) return '<div class="ck-empty-sm">Aucune opération prévue</div>';
    return items.slice(0, 6).map(item => {
      const amt = item.amount;
      const cls = amt >= 0 ? 'pos' : 'neg';
      return `
        <div class="ck-upcoming-row">
          <span class="ck-upcoming-icon">${item.icon || '📋'}</span>
          <div class="ck-upcoming-info">
            <span class="ck-upcoming-label">${item.label}</span>
            <span class="ck-upcoming-date">${CAP360.Engine.dateShort(item.date)}</span>
          </div>
          <span class="ck-upcoming-amt ${cls}">${amt >= 0 ? '+' : ''}${CAP360.Engine.currency(amt)}</span>
        </div>
      `;
    }).join('');
  }

  function buildDecisions(decisions) {
    return decisions.map(d => `
      <div class="ck-decision">
        <span class="ck-decision-icon">${d.icon}</span>
        <span class="ck-decision-text">${d.text}</span>
        ${d.action ? `<button class="ck-decision-btn" onclick="CAP360.Router.navigate('budget')">${d.action}</button>` : ''}
      </div>
    `).join('');
  }

  function buildSanteWidget(ss) {
    const d = CAP360.Storage.get().sante;
    const metrics = d.metrics || [];
    const last = metrics.slice(-5);
    return `
      <div class="ck-widget-header">
        <span class="ck-widget-icon">❤️</span>
        <span class="ck-widget-title">Sport & Santé</span>
        <span class="ck-widget-period">Cette semaine</span>
      </div>
      <div class="ck-widget-body">
        <div class="ck-metric-row">
          <span class="ck-metric-icon">🦶</span>
          <div class="ck-metric-info"><span class="ck-metric-label">Pas quotidiens (moy.)</span><div class="ck-metric-bar"><div class="ck-metric-fill" style="width:${Math.min(100,ss.stepsAvg/100)}%;background:#30D158"></div></div></div>
          <span class="ck-metric-val">${ss.stepsAvg || '—'}</span>
        </div>
        <div class="ck-metric-row">
          <span class="ck-metric-icon">😴</span>
          <div class="ck-metric-info"><span class="ck-metric-label">Sommeil (moy.)</span><div class="ck-metric-bar"><div class="ck-metric-fill" style="width:${Math.min(100,(ss.sleepAvg||0)/10*100)}%;background:#007AFF"></div></div></div>
          <span class="ck-metric-val">${ss.sleepAvg || '—'} h</span>
        </div>
        ${ss.weight ? `<div class="ck-metric-row"><span class="ck-metric-icon">⚖️</span><div class="ck-metric-info"><span class="ck-metric-label">Poids</span></div><span class="ck-metric-val">${ss.weight} kg</span></div>` : ''}
        ${last.length === 0 ? '<div class="ck-empty-sm">Commencez à enregistrer vos métriques →</div>' : ''}
      </div>
      <button class="ck-widget-link" onclick="CAP360.Router.navigate('sante')">Voir le suivi complet →</button>
    `;
  }

  function buildMaisonWidget(ms) {
    const d    = CAP360.Storage.get().maison;
    const proj = (d.projects || []).filter(p => p.status === 'active' || p.status === 'in_progress').slice(0, 2);
    return `
      <div class="ck-widget-header">
        <span class="ck-widget-icon">🏡</span>
        <span class="ck-widget-title">Maison</span>
        <span class="ck-widget-period">${ms.activeCount} projet(s)</span>
      </div>
      <div class="ck-widget-body">
        ${proj.length > 0 ? proj.map(p => {
          const pct = p.budget > 0 ? Math.round((p.spent || 0) / p.budget * 100) : 0;
          return `
            <div class="ck-proj-card">
              <div class="ck-proj-head">
                <span class="ck-proj-name">${p.name}</span>
                <span class="ck-proj-pct" style="color:${pct>80?'#FF3B30':pct>50?'#FF9500':'#30D158'}">${pct}%</span>
              </div>
              <div class="ck-metric-bar"><div class="ck-metric-fill" style="width:${pct}%;background:${pct>80?'#FF3B30':pct>50?'#FF9500':'#30D158'}"></div></div>
              <div class="ck-proj-budget">Budget ${CAP360.Engine.currency(p.budget || 0)} · Dépensé ${CAP360.Engine.currency(p.spent || 0)}</div>
            </div>
          `;
        }).join('') : '<div class="ck-empty-sm">Aucun projet en cours</div>'}
      </div>
      <button class="ck-widget-link" onclick="CAP360.Router.navigate('maison')">Voir tous les projets →</button>
    `;
  }

  function buildProjetsWidget(ps) {
    const d    = CAP360.Storage.get().projets;
    const items = (d.items || []).filter(p => p.status !== 'done' && p.status !== 'cancelled').slice(0, 3);
    return `
      <div class="ck-widget-header">
        <span class="ck-widget-icon">🎯</span>
        <span class="ck-widget-title">Objectifs</span>
        <span class="ck-widget-period">${ps.active} actif(s)</span>
      </div>
      <div class="ck-widget-body">
        ${items.length > 0 ? items.map(p => {
          const pct = p.target > 0 ? Math.round((p.current || 0) / p.target * 100) : 0;
          return `
            <div class="ck-proj-card">
              <div class="ck-proj-head">
                <span class="ck-proj-name">${p.name}</span>
                <span class="ck-proj-pct" style="color:#BF5AF2">${pct}%</span>
              </div>
              <div class="ck-metric-bar"><div class="ck-metric-fill" style="width:${pct}%;background:#BF5AF2"></div></div>
              ${p.target ? `<div class="ck-proj-budget">${CAP360.Engine.currency(p.current||0)} / ${CAP360.Engine.currency(p.target)}</div>` : `<div class="ck-proj-budget">${p.description||''}</div>`}
            </div>
          `;
        }).join('') : '<div class="ck-empty-sm">Aucun objectif en cours</div>'}
      </div>
      <button class="ck-widget-link" onclick="CAP360.Router.navigate('projets')">Voir tous les objectifs →</button>
    `;
  }

  function buildCoffreWidget(cs) {
    const d    = CAP360.Storage.get().coffre;
    const docs = (d.documents || []).sort((a, b) => (a.expiryDate || '9999') > (b.expiryDate || '9999') ? 1 : -1).slice(0, 4);
    return `
      <div class="ck-widget-header">
        <span class="ck-widget-icon">📁</span>
        <span class="ck-widget-title">Coffre-fort</span>
        <span class="ck-widget-period">${cs.count} doc(s)</span>
      </div>
      <div class="ck-widget-body">
        ${docs.length > 0 ? docs.map(doc => `
          <div class="ck-doc-row">
            <span class="ck-doc-icon">${doc.icon || '📄'}</span>
            <div class="ck-doc-info">
              <span class="ck-doc-name">${doc.name}</span>
              <span class="ck-doc-meta">${doc.provider || doc.category || ''}${doc.expiryDate ? ' · ' + CAP360.Engine.dateShort(doc.expiryDate) : ''}</span>
            </div>
          </div>
        `).join('') : '<div class="ck-empty-sm">Aucun document enregistré</div>'}
      </div>
      <button class="ck-widget-link" onclick="CAP360.Router.navigate('coffre')">Ouvrir le coffre-fort →</button>
    `;
  }

  function buildHTML(bs, ss, ms, ps, vs, cs) {
    const upcoming  = getUpcoming();
    const decisions = getDecisions();
    const todayStr  = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const totalExp  = bs.expenses || 0;

    return `
      <div class="ck-wrap">

        <!-- Header -->
        <div class="ck-header">
          <div class="ck-header-left">
            <h1 class="ck-greeting">${greeting()} ${userName()} 👋</h1>
            <p class="ck-quote">${randomQuote()}</p>
          </div>
          <div class="ck-header-right">
            <span class="ck-sync"><span class="ck-dot"></span> En local</span>
            <span class="ck-date">${todayStr}</span>
          </div>
        </div>

        <!-- Module Tiles -->
        ${buildTiles(bs, ss, ms, ps, vs, cs)}

        <!-- Main Grid -->
        <div class="ck-main-grid">

          <!-- Treasury chart -->
          <div class="ck-card ck-card-treasury">
            <div class="ck-card-head">
              <span class="ck-card-title">Trésorerie</span>
              <div class="ck-legend">
                <span class="ck-legend-item"><span class="ck-legend-dot" style="background:#34C759"></span> Réel</span>
                <span class="ck-legend-item"><span class="ck-legend-dot ck-legend-dash" style="background:#FF9500"></span> Prévisionnel</span>
              </div>
            </div>
            <div class="ck-chart-wrap" style="height:220px"><canvas id="ck-treasury"></canvas></div>
            <div class="ck-treasury-stats">
              <div class="ck-tstat">
                <span class="ck-tstat-label">Solde actuel</span>
                <span class="ck-tstat-val ${(bs.balance||0) >= 0 ? 'pos' : 'neg'}">${CAP360.Engine.currency(bs.balance||0)}</span>
              </div>
              <div class="ck-tstat">
                <span class="ck-tstat-label">Revenus ce mois</span>
                <span class="ck-tstat-val pos">+${CAP360.Engine.currency(bs.income||0)}</span>
              </div>
              <div class="ck-tstat">
                <span class="ck-tstat-label">Dépenses ce mois</span>
                <span class="ck-tstat-val neg">-${CAP360.Engine.currency(Math.abs(bs.expenses||0))}</span>
              </div>
              <div class="ck-tstat">
                <span class="ck-tstat-label">Taux d'épargne</span>
                <span class="ck-tstat-val ${(bs.savingsRate||0)>=0?'pos':'neg'}">${Math.round(bs.savingsRate||0)}%</span>
              </div>
            </div>
          </div>

          <!-- Right column -->
          <div class="ck-right-col">

            <!-- Spending donut -->
            <div class="ck-card ck-card-donut">
              <div class="ck-card-head">
                <span class="ck-card-title">Dépenses du mois</span>
                <span class="ck-card-total neg">-${CAP360.Engine.currency(Math.abs(totalExp))}</span>
              </div>
              <div class="ck-donut-layout">
                <div class="ck-chart-wrap" style="height:160px;flex:0 0 160px"><canvas id="ck-donut"></canvas></div>
                <div id="ck-donut-legend" class="ck-donut-legend"></div>
              </div>
              <button class="ck-widget-link" onclick="CAP360.Router.navigate('budget')">Voir le détail →</button>
            </div>

            <!-- Upcoming operations -->
            <div class="ck-card ck-card-upcoming">
              <div class="ck-card-head">
                <span class="ck-card-title">Prochaines opérations</span>
              </div>
              <div class="ck-upcoming-list">${buildUpcoming(upcoming)}</div>
              <button class="ck-widget-link" onclick="CAP360.Router.navigate('budget')">Voir tout le calendrier →</button>
            </div>

          </div>
        </div>

        <!-- Widget Grid -->
        <div class="ck-widget-grid">
          <div class="ck-card ck-widget">${buildSanteWidget(ss)}</div>
          <div class="ck-card ck-widget">${buildMaisonWidget(ms)}</div>
          <div class="ck-card ck-widget">${buildProjetsWidget(ps)}</div>
          <div class="ck-card ck-widget">${buildCoffreWidget(cs)}</div>
        </div>

        <!-- Decisions bar -->
        <div class="ck-decisions-bar">
          <div class="ck-decisions-title">💡 Décisions qui auront le plus d'impact</div>
          <div class="ck-decisions-list">${buildDecisions(decisions)}</div>
          <button class="ck-decisions-all btn-secondary" onclick="CAP360.Router.navigate('budget')">Voir toutes les alertes →</button>
        </div>

      </div>
    `;
  }

  /* ---- Charts ---- */

  function renderCharts(bs) {
    // Treasury chart
    const treasuryData = getTreasuryData();
    if (treasuryData.length > 0) {
      CAP360.Charts.treasury('ck-treasury', treasuryData);
    } else {
      const canvas = document.getElementById('ck-treasury');
      if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#aeaeb2';
        ctx.font = '13px system-ui';
        ctx.textAlign = 'center';
        ctx.fillText('Importez des transactions pour voir le graphique', canvas.width / 2, canvas.height / 2);
      }
    }

    // Spending donut
    const cats = getSpendingCategories();
    if (cats.length > 0) {
      CAP360.Charts.donut('ck-donut', cats);
      renderDonutLegend(cats);
    }
  }

  function renderDonutLegend(cats) {
    const el = document.getElementById('ck-donut-legend');
    if (!el) return;
    const total = cats.reduce((s, c) => s + c.total, 0);
    el.innerHTML = cats.slice(0, 6).map(c => `
      <div class="ck-legend-cat">
        <span class="ck-legend-swatch" style="background:${c.color}"></span>
        <span class="ck-legend-name">${c.name}</span>
        <span class="ck-legend-pct">${total > 0 ? Math.round(c.total / total * 100) : 0}%</span>
      </div>
    `).join('');
  }

  /* ---- Mount ---- */

  function mount(container) {
    _view = container;

    const bs = getBudgetStats();
    const ss = getSanteStats();
    const ms = getMaisonStats();
    const ps = getProjetsStats();
    const vs = getVehiculesStats();
    const cs = getCoffreStats();

    _view.innerHTML = buildHTML(bs, ss, ms, ps, vs, cs);

    // Render charts after DOM is ready
    setTimeout(() => renderCharts(bs), 50);
  }

  /* ---- Public ---- */

  return { mount };

}());
