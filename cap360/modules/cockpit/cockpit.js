/* ============================================
   CAP360 Cockpit V3 — Vue pure 4 questions
   Toutes les données viennent de CAP360.Platform.
   ============================================ */

'use strict';

CAP360.Cockpit = (function () {

  let _view = null;

  /* ---- Helpers ---- */

  function fmt(v) {
    if (v === null || v === undefined) return '—';
    if (typeof v !== 'number') return String(v);
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v);
  }

  function pct(v) { return v !== null && v !== undefined ? Math.round(v) + '%' : '—'; }

  function scoreColor(s) {
    if (s === null || s === undefined) return 'var(--c-text-3)';
    if (s >= 75) return 'var(--c-positive)';
    if (s >= 50) return 'var(--c-warning)';
    return 'var(--c-negative)';
  }

  function scoreLabel(s) {
    if (s === null || s === undefined) return 'Aucune donnée';
    if (s >= 85) return 'Excellent';
    if (s >= 70) return 'Très bien';
    if (s >= 55) return 'Bien';
    if (s >= 40) return 'À améliorer';
    return 'Critique';
  }

  function alertIcon(level) {
    return { danger: '🔴', warning: '🟠', info: '🔵', success: '🟢' }[level] || '⚪';
  }

  function dateShort(d) {
    if (!d) return '';
    try { return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }); }
    catch (e) { return d; }
  }

  function greeting() {
    const h = new Date().getHours();
    if (h >= 5  && h < 18) return 'Bonjour';
    if (h >= 18 && h < 22) return 'Bonsoir';
    return 'Bonne nuit';
  }

  function userName() {
    try {
      const d = CAP360.Storage.get();
      return (d && d.budgetSettings && d.budgetSettings.userName) || 'Mickaël';
    } catch (e) { return 'Mickaël'; }
  }

  /* ================================================================
     4 QUESTIONS — cartes de synthèse
     ================================================================ */

  function renderQ1Projects(projets) {
    const k     = projets ? (projets.kpis || {}) : {};
    const score = projets ? projets.score : null;
    const color = scoreColor(score);

    const canStart = k.items ? k.items.filter(p => p.budgetPlanned > 0).length : 0;

    return `
      <div class="ck3-question" onclick="CAP360.Router.navigate('projets')">
        <div class="ck3-q-icon">🎯</div>
        <div class="ck3-q-title">Puis-je réaliser mes projets ?</div>
        <div class="ck3-q-value" style="color:${color}">${k.active !== undefined ? k.active : '—'}</div>
        <div class="ck3-q-sub">
          ${k.active !== undefined ? `${k.active} en cours` : 'Aucune donnée'}
          ${k.late > 0 ? ` · <span style="color:var(--c-negative)">${k.late} en retard</span>` : ''}
        </div>
      </div>
    `;
  }

  function renderQ2Risks(alerts) {
    const dangers  = alerts.filter(a => a.level === 'danger').length;
    const warnings = alerts.filter(a => a.level === 'warning').length;
    const total    = alerts.length;
    const color    = dangers > 0 ? 'var(--c-negative)' : warnings > 0 ? 'var(--c-warning)' : 'var(--c-positive)';

    return `
      <div class="ck3-question">
        <div class="ck3-q-icon">⚠️</div>
        <div class="ck3-q-title">Quels sont les risques ?</div>
        <div class="ck3-q-value" style="color:${color}">${total}</div>
        <div class="ck3-q-sub">
          ${total === 0 ? '🟢 Aucune alerte' : `${dangers > 0 ? `<span style="color:var(--c-negative)">${dangers} critique${dangers > 1 ? 's' : ''}</span>` : ''}${warnings > 0 ? ` · ${warnings} avertissement${warnings > 1 ? 's' : ''}` : ''}`}
        </div>
      </div>
    `;
  }

  function renderQ3Actions(alerts, timeline) {
    const today    = new Date().toISOString().slice(0, 10);
    const inWeek   = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
    const upcoming = timeline.filter(e => e.date >= today && e.date <= inWeek).slice(0, 3);
    const urgent   = alerts.filter(a => a.level === 'danger' || a.level === 'warning').slice(0, 2);
    const count    = upcoming.length + urgent.length;

    return `
      <div class="ck3-question">
        <div class="ck3-q-icon">📋</div>
        <div class="ck3-q-title">Que faire cette semaine ?</div>
        <div class="ck3-q-value">${count}</div>
        <div class="ck3-q-sub">
          ${upcoming.length > 0 ? `${upcoming.length} échéance${upcoming.length > 1 ? 's' : ''} cette semaine` : 'Rien d\'imminent'}
        </div>
      </div>
    `;
  }

  function renderQ4Trajectory(overall, financial) {
    const color = scoreColor(overall);
    const label = scoreLabel(overall);
    const sr    = financial ? (financial.kpis || {}).savingsRate : null;

    return `
      <div class="ck3-question">
        <div class="ck3-q-icon">📈</div>
        <div class="ck3-q-title">Ma trajectoire est-elle bonne ?</div>
        <div class="ck3-q-value" style="color:${color}">${overall !== null ? overall : '—'}<span style="font-size:14px;color:var(--c-text-3)">/100</span></div>
        <div class="ck3-q-sub">
          ${label}${sr !== null ? ` · Épargne ${Math.round(sr || 0)}%` : ''}
        </div>
      </div>
    `;
  }

  /* ================================================================
     SECTIONS PRINCIPALES
     ================================================================ */

  /* ---- Header ---- */

  function renderHeader(overall, story) {
    const todayStr = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    const color    = scoreColor(overall);
    const firstLine = story && story.length > 0 ? story[0] : '';

    return `
      <div class="ck3-header">
        <div>
          <h1 class="ck3-greeting">${greeting()}, ${userName()} 👋</h1>
          <p class="ck3-subline">${todayStr}${firstLine ? ' — ' + firstLine : ''}</p>
        </div>
        <div class="ck3-score-badge" style="border-color:${color}30;background:${color}08">
          <span class="ck3-score-num" style="color:${color}">${overall !== null ? overall : '—'}</span>
          <span class="ck3-score-label">Score global</span>
        </div>
      </div>
    `;
  }

  /* ---- Treasury chart ---- */

  function renderTreasury(financial) {
    if (!financial) {
      return `
        <div class="ck3-card">
          <div class="ck3-card-head"><span class="ck3-card-title">Trésorerie</span></div>
          <div class="ck3-empty">Importez vos transactions dans le module Budget.</div>
        </div>
      `;
    }
    const kpis  = financial.kpis || {};
    const bal   = kpis.balance;
    const inc   = kpis.income;
    const exp   = kpis.expenses;
    const sr    = kpis.savingsRate;

    return `
      <div class="ck3-card">
        <div class="ck3-card-head">
          <span class="ck3-card-title">Trésorerie</span>
          <button class="ck3-link" onclick="CAP360.Router.navigate('budget')">Voir Budget →</button>
        </div>
        <div style="position:relative;height:200px"><canvas id="ck3-treasury"></canvas></div>
        <div class="ck3-treasury-stats">
          <div class="ck3-tstat">
            <span class="ck3-tstat-l">Solde</span>
            <span class="ck3-tstat-v ${(bal || 0) >= 0 ? 'pos' : 'neg'}">${fmt(bal)}</span>
          </div>
          <div class="ck3-tstat">
            <span class="ck3-tstat-l">Revenus/mois</span>
            <span class="ck3-tstat-v pos">${fmt(inc)}</span>
          </div>
          <div class="ck3-tstat">
            <span class="ck3-tstat-l">Dépenses/mois</span>
            <span class="ck3-tstat-v neg">${fmt(exp)}</span>
          </div>
          <div class="ck3-tstat">
            <span class="ck3-tstat-l">Épargne</span>
            <span class="ck3-tstat-v ${(sr || 0) >= 0 ? 'pos' : 'neg'}">${pct(sr)}</span>
          </div>
        </div>
      </div>
    `;
  }

  /* ---- Active projects panel ---- */

  function renderProjectsPanel(projets) {
    if (!projets) {
      return `
        <div class="ck3-card">
          <div class="ck3-card-head"><span class="ck3-card-title">Projets</span></div>
          <div class="ck3-empty">Aucun projet défini.</div>
          <button class="ck3-link" onclick="CAP360.Router.navigate('projets')">Créer un projet →</button>
        </div>
      `;
    }

    const activeItems = (projets.kpis || {}).items || [];
    const color       = scoreColor(projets.score);

    return `
      <div class="ck3-card">
        <div class="ck3-card-head">
          <span class="ck3-card-title">Projets actifs</span>
          <span style="font-size:13px;font-weight:700;color:${color}">${projets.kpis.active || 0}</span>
        </div>
        ${activeItems.length === 0 ? `
          <div class="ck3-empty">Aucun projet en cours.</div>
        ` : activeItems.map(p => renderCkProjectRow(p)).join('')}
        <button class="ck3-link" onclick="CAP360.Router.navigate('projets')">Tous les projets →</button>
      </div>
    `;
  }

  function renderCkProjectRow(p) {
    const planned = p.budgetPlanned || p.target || 0;
    const real    = p.budgetReal    || p.current || 0;
    const pctVal  = planned > 0 ? Math.min(100, Math.round(real / planned * 100)) : (p.progress || 0);
    const today   = new Date().toISOString().slice(0, 10);
    const isLate  = p.dueDate && p.dueDate < today;

    return `
      <div class="ck3-proj-row" onclick="CAP360.Router.navigate('projets')">
        <div class="ck3-proj-name">${p.name}</div>
        <div class="ck3-proj-bar-wrap">
          <div class="ck3-proj-bar">
            <div class="ck3-proj-fill" style="width:${pctVal}%;background:${isLate ? 'var(--c-negative)' : 'var(--c-projets)'}"></div>
          </div>
          <span class="ck3-proj-pct">${pctVal}%</span>
        </div>
        ${p.dueDate ? `<span class="ck3-proj-date ${isLate ? 'neg' : ''}">${isLate ? '⚠️ ' : ''}${dateShort(p.dueDate)}</span>` : ''}
      </div>
    `;
  }

  /* ---- Spending donut ---- */

  function renderDonut(financial) {
    if (!financial || !(financial.kpis || {}).categoryBreakdown || !financial.kpis.categoryBreakdown.length) {
      return `
        <div class="ck3-card">
          <div class="ck3-card-head"><span class="ck3-card-title">Dépenses du mois</span></div>
          <div class="ck3-empty">Aucune dépense ce mois.</div>
        </div>
      `;
    }

    const cats  = financial.kpis.categoryBreakdown;
    const total = Math.abs(financial.kpis.expenses || 0);

    return `
      <div class="ck3-card">
        <div class="ck3-card-head">
          <span class="ck3-card-title">Dépenses du mois</span>
          <span class="neg" style="font-size:13px;font-weight:700">${fmt(total)}</span>
        </div>
        <div style="position:relative;height:150px"><canvas id="ck3-donut"></canvas></div>
        <div id="ck3-donut-legend" class="ck3-donut-legend"></div>
        <button class="ck3-link" onclick="CAP360.Router.navigate('budget')">Voir le détail →</button>
      </div>
    `;
  }

  /* ---- Unified alerts ---- */

  function renderAlertsPanel(alerts) {
    const visible = alerts.slice(0, 6);

    return `
      <div class="ck3-card">
        <div class="ck3-card-head">
          <span class="ck3-card-title">Alertes</span>
          ${alerts.length > 0 ? `<span class="badge badge-danger">${alerts.length}</span>` : ''}
        </div>
        ${visible.length === 0 ? `
          <div class="ck3-empty">🟢 Aucune alerte — tout est sous contrôle.</div>
        ` : visible.map(a => `
          <div class="ck3-alert-row">
            <span>${alertIcon(a.level)}</span>
            <span class="ck3-alert-msg">${a.message}</span>
            ${a.action ? `<button class="ck3-link" style="margin:0;white-space:nowrap" onclick="CAP360.Router.navigate('${a.action.module}')">${a.action.label}</button>` : ''}
          </div>
        `).join('')}
        ${alerts.length > 6 ? `<div class="ck3-empty" style="font-size:12px">+${alerts.length - 6} autre(s) alerte(s)</div>` : ''}
      </div>
    `;
  }

  /* ---- Unified timeline ---- */

  function renderTimeline(events) {
    const today  = new Date().toISOString().slice(0, 10);
    const past   = events.filter(e => e.date < today).reverse().slice(0, 2).reverse();
    const future = events.filter(e => e.date >= today).slice(0, 8);
    const all    = [...past, ...future];

    return `
      <div class="ck3-card">
        <div class="ck3-card-head">
          <span class="ck3-card-title">Timeline</span>
          ${future.length > 0 ? `<span class="badge badge-info">${future.length} à venir</span>` : ''}
        </div>
        ${all.length === 0 ? `
          <div class="ck3-empty">Aucun événement à venir. Ajoutez des données dans vos modules.</div>
        ` : `
          <div class="ck3-tl">
            ${all.map(e => {
              const isPast  = e.date < today;
              const isToday = e.date === today;
              return `
                <div class="ck3-tl-row ${isPast ? 'ck3-tl-past' : ''}" onclick="CAP360.Router.navigate('${e.module}')">
                  <div class="ck3-tl-dot" style="background:${isPast ? 'var(--c-border)' : e.color}"></div>
                  <div class="ck3-tl-date ${isToday ? 'ck3-tl-today' : ''}">${isToday ? "Auj." : dateShort(e.date)}</div>
                  <div class="ck3-tl-icon">${e.icon}</div>
                  <div class="ck3-tl-label">${e.label}</div>
                  ${e.amount !== null ? `<div class="ck3-tl-amt ${e.amount >= 0 ? 'pos' : 'neg'}">${fmt(e.amount)}</div>` : ''}
                </div>
              `;
            }).join('')}
          </div>
        `}
      </div>
    `;
  }

  /* ---- Charts ---- */

  function renderCharts(financial) {
    if (!financial) return;
    const kpis = financial.kpis || {};

    if (kpis.treasuryHistory && kpis.treasuryHistory.length > 0) {
      CAP360.Charts.treasury('ck3-treasury', kpis.treasuryHistory);
    }

    if (kpis.categoryBreakdown && kpis.categoryBreakdown.length > 0) {
      CAP360.Charts.donut('ck3-donut', kpis.categoryBreakdown);
      const legend = document.getElementById('ck3-donut-legend');
      if (legend) {
        const total = kpis.categoryBreakdown.reduce((s, c) => s + (c.total || c.spent || 0), 0);
        legend.innerHTML = kpis.categoryBreakdown.slice(0, 5).map(c => `
          <div class="ck3-legend-row">
            <span class="ck3-legend-dot" style="background:${c.color}"></span>
            <span class="ck3-legend-name">${c.name}</span>
            <span class="ck3-legend-pct">${total > 0 ? Math.round((c.total || c.spent || 0) / total * 100) : 0}%</span>
          </div>
        `).join('');
      }
    }
  }

  /* ================================================================
     MOUNT
     ================================================================ */

  function mount(container) {
    _view = container;

    const overall   = CAP360.Platform.getOverallScore();
    const financial = CAP360.Platform.getFinancialHealth();
    const projets   = CAP360.Platform.getProjectHealth();
    const alerts    = CAP360.Platform.getAllAlerts();
    const timeline  = CAP360.Platform.getTimeline({ past: 7, future: 60 });
    const story     = CAP360.Platform.getStory();

    _view.innerHTML = `
      <div class="ck3-wrap">

        ${renderHeader(overall, story)}

        <!-- 4 questions -->
        <div class="ck3-questions">
          ${renderQ1Projects(projets)}
          ${renderQ2Risks(alerts)}
          ${renderQ3Actions(alerts, timeline)}
          ${renderQ4Trajectory(overall, financial)}
        </div>

        <!-- Grille principale : trésorerie + projets -->
        <div class="ck3-main-grid">
          ${renderTreasury(financial)}
          ${renderProjectsPanel(projets)}
        </div>

        <!-- Grille basse : dépenses + timeline + alertes -->
        <div class="ck3-bottom-grid">
          ${renderDonut(financial)}
          ${renderTimeline(timeline)}
          ${renderAlertsPanel(alerts)}
        </div>

      </div>
    `;

    setTimeout(() => renderCharts(financial), 50);
  }

  return { mount };

}());
