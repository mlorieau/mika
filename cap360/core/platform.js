/* ============================================
   CAP360 Platform — Agrégateur central de données
   ============================================
   Les modules s'enregistrent ici et produisent des données
   via getHealth(). Le Cockpit lit uniquement Platform.
   ============================================ */

'use strict';

window.CAP360 = window.CAP360 || {};

CAP360.Platform = (function () {

  /* ---- Module registry ---- */

  const _registry = {};

  const WEIGHTS = {
    budget:    35,
    projets:   30,
    maison:    15,
    vehicules: 10,
    coffre:    10,
  };

  function register(name, module) {
    _registry[name] = module;
  }

  /* ---- Safe health getter ---- */

  function _health(name) {
    const mod = _registry[name];
    if (!mod || typeof mod.getHealth !== 'function') return null;
    try {
      return mod.getHealth();
    } catch (e) {
      console.error('[Platform] getHealth(' + name + ') failed:', e);
      return null;
    }
  }

  /* ---- Typed accessors (Cockpit API) ---- */

  function getFinancialHealth()  { return _health('budget'); }
  function getProjectHealth()    { return _health('projets'); }
  function getVehicleHealth()    { return _health('vehicules'); }
  function getDocumentsHealth()  { return _health('coffre'); }
  function getHomeHealth()       { return _health('maison'); }

  /* ---- getAll: iterate every registered module ---- */

  function getAll() {
    const out = {};
    for (const name of Object.keys(_registry)) {
      const h = _health(name);
      if (h) out[name] = h;
    }
    return out;
  }

  /* ---- Overall weighted score ---- */

  function getOverallScore() {
    const all = getAll();
    let totalWeight = 0;
    let weighted    = 0;
    for (const [name, h] of Object.entries(all)) {
      if (h && typeof h.score === 'number') {
        const w = WEIGHTS[name] || 5;
        weighted    += h.score * w;
        totalWeight += w;
      }
    }
    if (totalWeight === 0) return null;
    return Math.round(weighted / totalWeight);
  }

  /* ---- Unified alerts (sorted by severity) ---- */

  function getAllAlerts() {
    const all = getAll();
    const alerts = [];
    const LEVEL_ORDER = { danger: 0, warning: 1, info: 2, success: 3 };

    for (const [name, h] of Object.entries(all)) {
      if (!h || !Array.isArray(h.alerts)) continue;
      h.alerts.forEach(a => {
        alerts.push({
          level:   a.level   || 'info',
          message: a.message || '',
          date:    a.date    || null,
          action:  a.action  || null,
          module:  name,
        });
      });
    }

    return alerts.sort((a, b) =>
      (LEVEL_ORDER[a.level] ?? 9) - (LEVEL_ORDER[b.level] ?? 9)
    );
  }

  /* ---- Unified timeline ---- */

  function getTimeline(opts = {}) {
    const pastDays   = opts.past   ?? 14;
    const futureDays = opts.future ?? 45;
    const from = new Date(Date.now() - pastDays * 86400000).toISOString().slice(0, 10);
    const to   = new Date(Date.now() + futureDays * 86400000).toISOString().slice(0, 10);

    const all    = getAll();
    const events = [];

    for (const [name, h] of Object.entries(all)) {
      if (!h || !Array.isArray(h.timeline)) continue;
      h.timeline.forEach(e => {
        if (e.date >= from && e.date <= to) {
          events.push({
            date:   e.date,
            type:   e.type   || 'event',
            label:  e.label  || '',
            amount: e.amount ?? null,
            icon:   e.icon   || '📌',
            color:  e.color  || '#007AFF',
            module: name,
          });
        }
      });
    }

    return events.sort((a, b) => a.date.localeCompare(b.date));
  }

  /* ---- Auto-generated narrative story ---- */

  function getStory() {
    const overall = getOverallScore();
    const alerts  = getAllAlerts();
    const all     = getAll();
    const lines   = [];

    // Global intro from score
    if (overall === null) {
      lines.push('Aucune donnée disponible — commencez par renseigner vos modules.');
    } else if (overall >= 80) {
      lines.push('Cette semaine, tout est sous contrôle.');
    } else if (overall >= 65) {
      lines.push('Quelques points méritent votre attention.');
    } else if (overall >= 45) {
      lines.push('Plusieurs indicateurs nécessitent votre attention.');
    } else {
      lines.push('Situation globale à surveiller — agissez rapidement.');
    }

    // Collect per-module stories
    for (const [, h] of Object.entries(all)) {
      if (h && h.story) lines.push(h.story);
    }

    // Urgent alerts become story lines
    const dangers = alerts.filter(a => a.level === 'danger');
    dangers.slice(0, 2).forEach(a => {
      if (!lines.some(l => l.includes(a.message))) lines.push(a.message);
    });

    return lines;
  }

  /* ---- Public API ---- */

  return {
    register,
    getAll,
    getFinancialHealth,
    getProjectHealth,
    getVehicleHealth,
    getDocumentsHealth,
    getHomeHealth,
    getOverallScore,
    getAllAlerts,
    getTimeline,
    getStory,
  };

}());
