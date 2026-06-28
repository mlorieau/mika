/* ============================================
   CAP360 Cockpit — Vue pure, zéro calcul
   Toutes les données viennent de CAP360.Platform.
   ============================================ */

'use strict';

CAP360.Cockpit = (function () {

  let _view = null;

  /* ---- Utilitaires de rendu ---- */

  function fmt(v, decimals) {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'number') {
      return new Intl.NumberFormat('fr-FR', {
        style: 'currency', currency: 'EUR',
        maximumFractionDigits: decimals ?? 0,
      }).format(v);
    }
    return String(v);
  }

  function scoreColor(score) {
    if (score === null || score === undefined) return '#aeaeb2';
    if (score >= 75) return '#30D158';
    if (score >= 50) return '#FF9500';
    return '#FF3B30';
  }

  function scoreLabel(score) {
    if (score === null || score === undefined) return 'Aucune donnée';
    if (score >= 85) return 'Excellent';
    if (score >= 70) return 'Très bien';
    if (score >= 55) return 'Bien';
    if (score >= 40) return 'À améliorer';
    return 'Critique';
  }

  function alertIcon(level) {
    return { danger: '🔴', warning: '🟠', info: '🔵', success: '🟢' }[level] || '⚪';
  }

  function alertBadge(level) {
    const map = { danger: 'badge-danger', warning: 'badge-warning', info: 'badge-info', success: 'badge-success' };
    return map[level] || 'badge-info';
  }

  function typeIcon(type) {
    const map = {
      transaction:  null,      // uses event icon
      appointment:  '🏥',
      deadline:     '📅',
      milestone:    '🏁',
      document:     '📄',
      maintenance:  '🔧',
      event:        '📌',
    };
    return map[type] || '📌';
  }

  function dateShort(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }); }
    catch (e) { return d; }
  }

  function greeting() {
    const h = new Date().getHours();
    if (h >= 5  && h < 12) return 'Bonjour';
    if (h >= 12 && h < 18) return 'Bonjour';
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
     RENDU — chaque fonction lit uniquement Platform
     ================================================================ */

  /* ---- Score global ---- */

  function renderScoreRing(score) {
    const color = scoreColor(score);
    const label = scoreLabel(score);
    const pct   = score !== null ? score : 0;
    const r = 44;
    const circ = 2 * Math.PI * r;
    const dash = (pct / 100) * circ;

    return `
      <div class="ck-score-ring-wrap">
        <svg width="110" height="110" viewBox="0 0 110 110">
          <circle cx="55" cy="55" r="${r}" fill="none" stroke="rgba(0,0,0,0.06)" stroke-width="8"/>
          <circle cx="55" cy="55" r="${r}" fill="none"
            stroke="${color}" stroke-width="8"
            stroke-dasharray="${dash} ${circ}"
            stroke-dashoffset="${circ / 4}"
            stroke-linecap="round"
            style="transition:stroke-dasharray 0.8s ease"/>
        </svg>
        <div class="ck-score-ring-center">
          <span class="ck-score-ring-value" style="color:${color}">${score !== null ? score : '—'}</span>
          <span class="ck-score-ring-label">/100</span>
        </div>
      </div>
      <div class="ck-score-sublabel" style="color:${color}">${label}</div>
    `;
  }

  /* ---- Module tiles (génériques) ---- */

  function renderTile(name, health) {
    const score = health.score;
    const color = scoreColor(score);

    return `
      <div class="ck-tile" style="--tile-accent:${health.accent}" onclick="CAP360.Router.navigate('${name}')">
        <div class="ck-tile-head">
          <span class="ck-tile-icon">${health.icon}</span>
          <span class="ck-tile-label" style="color:${health.accent}">${health.label}</span>
        </div>
        <div class="ck-tile-score">
          <span style="font-size:28px;font-weight:700;letter-spacing:-1px;color:${color}">${score !== null ? score : '—'}</span>
          <span style="font-size:12px;color:var(--c-text-3)">/100</span>
        </div>
        <div class="ck-metric-bar" style="margin:8px 0 6px">
          <div class="ck-metric-fill" style="width:${score || 0}%;background:${color}"></div>
        </div>
        <div class="ck-tile-story">${health.story ? health.story.split('.')[0] + '.' : scoreLabel(score)}</div>
      </div>
    `;
  }

  /* ---- Header ---- */

  function renderHeader(overall, story) {
    const todayStr = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const color    = scoreColor(overall);

    return `
      <div class="ck-header">
        <div class="ck-header-left">
          <h1 class="ck-greeting">${greeting()} ${userName()} 👋</h1>
          <p class="ck-quote" id="ck-story-line">${story[0] || ''}</p>
        </div>
        <div class="ck-header-right">
          <div class="ck-overall-badge" style="border-color:${color}20;background:${color}10">
            <span style="font-size:22px;font-weight:700;color:${color}">${overall !== null ? overall : '—'}</span>
            <span style="font-size:11px;color:var(--c-text-3);margin-top:2px">Score global<br>/100</span>
          </div>
          <div>
            <div class="ck-sync"><span class="ck-dot"></span> En local</div>
            <div class="ck-date">${todayStr}</div>
          </div>
        </div>
      </div>
    `;
  }

  /* ---- Story lines ---- */

  function renderStory(lines) {
    if (!lines || lines.length <= 1) return '';
    return `
      <div class="ck-story-bar">
        <span class="ck-story-icon">💬</span>
        <div class="ck-story-lines">
          ${lines.slice(1).map(l => `<span class="ck-story-line">${l}</span>`).join('')}
        </div>
      </div>
    `;
  }

  /* ---- Financial charts ---- */

  function renderFinancialCard(financial) {
    if (!financial) {
      return `
        <div class="ck-card ck-card-treasury">
          <div class="ck-card-head"><span class="ck-card-title">Trésorerie</span></div>
          <div class="ck-empty-sm">Aucune donnée budgétaire — importez vos transactions.</div>
        </div>
      `;
    }

    const kpis  = financial.kpis || {};
    const bal   = kpis.balance;
    const inc   = kpis.income;
    const exp   = kpis.expenses;
    const sr    = kpis.savingsRate;
    const color = bal >= 0 ? 'pos' : 'neg';

    return `
      <div class="ck-card ck-card-treasury">
        <div class="ck-card-head">
          <span class="ck-card-title">Trésorerie</span>
          <div class="ck-legend">
            <span class="ck-legend-item"><span class="ck-legend-dot" style="background:#34C759"></span> Réel</span>
            <span class="ck-legend-item"><span class="ck-legend-dot" style="background:#FF9500"></span> Prévisionnel</span>
          </div>
        </div>
        <div class="ck-chart-wrap" style="height:220px"><canvas id="ck-treasury"></canvas></div>
        <div class="ck-treasury-stats">
          <div class="ck-tstat">
            <span class="ck-tstat-label">Solde actuel</span>
            <span class="ck-tstat-val ${color}">${fmt(bal)}</span>
          </div>
          <div class="ck-tstat">
            <span class="ck-tstat-label">Revenus / mois</span>
            <span class="ck-tstat-val pos">${fmt(inc)}</span>
          </div>
          <div class="ck-tstat">
            <span class="ck-tstat-label">Dépenses / mois</span>
            <span class="ck-tstat-val neg">${fmt(exp)}</span>
          </div>
          <div class="ck-tstat">
            <span class="ck-tstat-label">Taux d'épargne</span>
            <span class="ck-tstat-val ${(sr || 0) >= 0 ? 'pos' : 'neg'}">${sr !== undefined ? Math.round(sr) + '%' : '—'}</span>
          </div>
        </div>
      </div>
    `;
  }

  function renderSpendingCard(financial) {
    if (!financial || !(financial.kpis || {}).categoryBreakdown || !financial.kpis.categoryBreakdown.length) {
      return `
        <div class="ck-card ck-card-donut">
          <div class="ck-card-head"><span class="ck-card-title">Dépenses du mois</span></div>
          <div class="ck-empty-sm">Aucune dépense ce mois.</div>
        </div>
      `;
    }

    const cats  = financial.kpis.categoryBreakdown;
    const total = Math.abs(financial.kpis.expenses || 0);

    return `
      <div class="ck-card ck-card-donut">
        <div class="ck-card-head">
          <span class="ck-card-title">Dépenses du mois</span>
          <span class="ck-card-total neg">${fmt(total)}</span>
        </div>
        <div class="ck-donut-layout">
          <div class="ck-chart-wrap" style="height:160px;flex:0 0 160px"><canvas id="ck-donut"></canvas></div>
          <div id="ck-donut-legend" class="ck-donut-legend"></div>
        </div>
        <button class="ck-widget-link" onclick="CAP360.Router.navigate('budget')">Voir le détail →</button>
      </div>
    `;
  }

  /* ---- Unified alerts ---- */

  function renderAlertsCard(alerts) {
    const visible = alerts.slice(0, 8);
    if (visible.length === 0) {
      return `
        <div class="ck-card">
          <div class="ck-card-head"><span class="ck-card-title">Alertes</span></div>
          <div class="ck-empty-sm">🟢 Aucune alerte — tout est sous contrôle.</div>
        </div>
      `;
    }

    return `
      <div class="ck-card">
        <div class="ck-card-head">
          <span class="ck-card-title">Alertes</span>
          <span class="badge badge-danger">${alerts.length}</span>
        </div>
        ${visible.map(a => `
          <div class="ck-alert-row">
            <span class="ck-alert-icon">${alertIcon(a.level)}</span>
            <span class="ck-alert-msg">${a.message}</span>
            ${a.action ? `<button class="ck-widget-link" style="margin:0;flex-shrink:0" onclick="CAP360.Router.navigate('${a.action.module}')">${a.action.label}</button>` : ''}
          </div>
        `).join('')}
        ${alerts.length > 8 ? `<div class="ck-empty-sm">+${alerts.length - 8} alerte(s) supplémentaire(s)</div>` : ''}
      </div>
    `;
  }

  /* ---- Unified timeline ---- */

  function renderTimeline(events) {
    const today   = new Date().toISOString().slice(0, 10);
    const past    = events.filter(e => e.date < today).reverse().slice(0, 3);
    const future  = events.filter(e => e.date >= today).slice(0, 10);
    const all     = [...past.reverse(), ...future];

    if (all.length === 0) {
      return `
        <div class="ck-card">
          <div class="ck-card-head"><span class="ck-card-title">Timeline</span></div>
          <div class="ck-empty-sm">Aucun événement à venir. Ajoutez des données dans vos modules.</div>
        </div>
      `;
    }

    return `
      <div class="ck-card">
        <div class="ck-card-head">
          <span class="ck-card-title">Timeline</span>
          <span class="badge badge-info">${future.length} à venir</span>
        </div>
        <div class="ck-tl-list">
          ${all.map(e => {
            const isPast = e.date < today;
            const isToday = e.date === today;
            return `
              <div class="ck-tl-row${isPast ? ' ck-tl-past' : ''}${isToday ? ' ck-tl-today' : ''}" onclick="CAP360.Router.navigate('${e.module}')">
                <div class="ck-tl-dot" style="background:${isPast ? 'var(--c-border-med)' : e.color}"></div>
                <div class="ck-tl-date ${isToday ? 'ck-tl-date-today' : ''}">${isToday ? "Aujourd'hui" : dateShort(e.date)}</div>
                <div class="ck-tl-icon">${e.icon || typeIcon(e.type)}</div>
                <div class="ck-tl-label">${e.label}</div>
                ${e.amount !== null ? `<div class="ck-tl-amount ${e.amount >= 0 ? 'pos' : 'neg'}">${e.amount >= 0 ? '+' : ''}${fmt(e.amount)}</div>` : ''}
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `;
  }

  /* ---- Module mini-widgets (génériques) ---- */

  function renderModuleWidget(name, health) {
    const kpis  = health.kpis || {};
    const score = health.score;
    const color = scoreColor(score);

    return `
      <div class="ck-card ck-widget" onclick="CAP360.Router.navigate('${name}')" style="cursor:pointer">
        <div class="ck-widget-header">
          <span class="ck-widget-icon">${health.icon}</span>
          <span class="ck-widget-title">${health.label}</span>
          <span style="font-size:14px;font-weight:700;color:${color}">${score !== null ? score : '—'}<span style="font-size:10px;color:var(--c-text-3)">/100</span></span>
        </div>
        <div class="ck-metric-bar" style="margin-bottom:10px">
          <div class="ck-metric-fill" style="width:${score || 0}%;background:${color}"></div>
        </div>
        <div class="ck-widget-body">
          ${health.story ? `<div style="font-size:12px;color:var(--c-text-2);line-height:1.5">${health.story}</div>` : ''}
          ${renderKpiRows(kpis, name)}
        </div>
        <button class="ck-widget-link">Voir ${health.label} →</button>
      </div>
    `;
  }

  function renderKpiRows(kpis, name) {
    // Financial KPIs
    if (name === 'budget') {
      return [
        kpis.income    ? kpiRow('Revenus/mois', fmt(kpis.income)) : '',
        kpis.expenses  ? kpiRow('Dépenses/mois', fmt(kpis.expenses)) : '',
        kpis.debtRatio ? kpiRow('Endettement', kpis.debtRatio + '%') : '',
      ].filter(Boolean).join('');
    }
    // Health KPIs
    if (name === 'sante') {
      return [
        kpis.stepsAvg ? kpiRow('Pas/jour', (kpis.stepsAvg || 0).toLocaleString('fr-FR')) : '',
        kpis.sleepAvg ? kpiRow('Sommeil', kpis.sleepAvg + ' h') : '',
        kpis.weight   ? kpiRow('Poids', kpis.weight + ' kg') : '',
      ].filter(Boolean).join('');
    }
    // Home KPIs
    if (name === 'maison') {
      return [
        kpis.activeCount !== undefined ? kpiRow('Projets actifs', kpis.activeCount) : '',
        kpis.totalBudget ? kpiRow('Budget total', fmt(kpis.totalBudget)) : '',
        kpis.pct !== undefined ? kpiRow('Consommé', kpis.pct + '%') : '',
      ].filter(Boolean).join('');
    }
    // Project KPIs
    if (name === 'projets') {
      return [
        kpis.active !== undefined ? kpiRow('En cours', kpis.active) : '',
        kpis.late   !== undefined ? kpiRow('En retard', kpis.late, kpis.late > 0 ? 'neg' : '') : '',
        kpis.done   !== undefined ? kpiRow('Terminés', kpis.done, 'pos') : '',
      ].filter(Boolean).join('');
    }
    // Vehicle KPIs
    if (name === 'vehicules') {
      return [
        kpis.count  !== undefined ? kpiRow('Véhicules', kpis.count) : '',
        kpis.allOk  !== undefined ? kpiRow('Statut', kpis.allOk ? '✅ OK' : '⚠️ Alerte', kpis.allOk ? 'pos' : 'neg') : '',
        kpis.monthCosts ? kpiRow('Coûts ce mois', fmt(kpis.monthCosts)) : '',
      ].filter(Boolean).join('');
    }
    // Documents KPIs
    if (name === 'coffre') {
      return [
        kpis.count        !== undefined ? kpiRow('Documents', kpis.count) : '',
        kpis.expired      ? kpiRow('Expirés', kpis.expired, 'neg') : '',
        kpis.expiringSoon ? kpiRow('Expirent bientôt', kpis.expiringSoon, 'neg') : '',
      ].filter(Boolean).join('');
    }
    return '';
  }

  function kpiRow(label, value, cls) {
    return `
      <div class="ck-kpi-row">
        <span class="ck-kpi-label">${label}</span>
        <span class="ck-kpi-value ${cls || ''}">${value}</span>
      </div>
    `;
  }

  /* ================================================================
     CHARTS — rendus après DOM
     ================================================================ */

  function renderCharts(financial) {
    if (!financial) return;
    const kpis = financial.kpis || {};

    // Treasury chart
    if (kpis.treasuryHistory && kpis.treasuryHistory.length > 0) {
      CAP360.Charts.treasury('ck-treasury', kpis.treasuryHistory);
    }

    // Spending donut
    if (kpis.categoryBreakdown && kpis.categoryBreakdown.length > 0) {
      CAP360.Charts.donut('ck-donut', kpis.categoryBreakdown);
      const legend = document.getElementById('ck-donut-legend');
      if (legend) {
        const total = kpis.categoryBreakdown.reduce((s, c) => s + (c.total || c.spent || 0), 0);
        legend.innerHTML = kpis.categoryBreakdown.slice(0, 6).map(c => `
          <div class="ck-legend-cat">
            <span class="ck-legend-swatch" style="background:${c.color}"></span>
            <span class="ck-legend-name">${c.name}</span>
            <span class="ck-legend-pct">${total > 0 ? Math.round((c.total || c.spent || 0) / total * 100) : 0}%</span>
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

    // Read all data from Platform — zero calcul ici
    const overall   = CAP360.Platform.getOverallScore();
    const financial = CAP360.Platform.getFinancialHealth();
    const alerts    = CAP360.Platform.getAllAlerts();
    const timeline  = CAP360.Platform.getTimeline({ past: 14, future: 45 });
    const story     = CAP360.Platform.getStory();
    const all       = CAP360.Platform.getAll();

    // Tiles : tous les modules enregistrés sauf budget (affiché via les charts)
    const tileModules = Object.entries(all);

    _view.innerHTML = `
      <div class="ck-wrap">

        ${renderHeader(overall, story)}

        ${renderStory(story)}

        <!-- Module tiles (un par module) -->
        <div class="ck-tiles">
          ${tileModules.map(([name, health]) => renderTile(name, health)).join('')}
        </div>

        <!-- Grille principale -->
        <div class="ck-main-grid">

          <!-- Trésorerie -->
          ${renderFinancialCard(financial)}

          <!-- Colonne droite -->
          <div class="ck-right-col">
            ${renderSpendingCard(financial)}
            ${renderAlertsCard(alerts)}
          </div>

        </div>

        <!-- Timeline unifiée + widgets modules -->
        <div class="ck-bottom-grid">

          ${renderTimeline(timeline)}

          <!-- Mini-widgets génériques -->
          <div class="ck-widget-col">
            ${tileModules.filter(([name]) => name !== 'budget').map(([name, health]) => renderModuleWidget(name, health)).join('')}
          </div>

        </div>

      </div>
    `;

    // Charts après DOM
    setTimeout(() => renderCharts(financial), 50);
  }

  return { mount };

}());
