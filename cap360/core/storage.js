/* ============================================
   CAP360 Storage — Couche de persistance
   ============================================ */

'use strict';

window.CAP360 = window.CAP360 || {};

CAP360.Storage = (function () {

  const PREFIX  = 'cap360_';
  const KEY_DATA = PREFIX + 'data';
  const KEY_META = PREFIX + 'meta';

  const DEFAULT_DATA = {
    _version: 2,
    _createdAt: null,
    _updatedAt: null,

    // --- Budget ---
    accounts: [],
    transactions: [],
    categories: [
      { id: 'alimentation',   name: 'Alimentation',   icon: '🛒', color: '#FF6B6B', parent: null, budgetMonthly: 500 },
      { id: 'transport',      name: 'Transport',       icon: '🚌', color: '#4ECDC4', parent: null, budgetMonthly: 150 },
      { id: 'logement',       name: 'Logement',        icon: '🏠', color: '#45B7D1', parent: null, budgetMonthly: 1200 },
      { id: 'sante',          name: 'Santé',           icon: '💊', color: '#96CEB4', parent: null, budgetMonthly: 100 },
      { id: 'loisirs',        name: 'Loisirs',         icon: '🎉', color: '#FFEAA7', parent: null, budgetMonthly: 200 },
      { id: 'restaurant',     name: 'Restaurants',     icon: '🍽️', color: '#DDA0DD', parent: null, budgetMonthly: 150 },
      { id: 'vetements',      name: 'Vêtements',       icon: '👕', color: '#98D8C8', parent: null, budgetMonthly: 100 },
      { id: 'abonnements',    name: 'Abonnements',     icon: '📱', color: '#B8B8FF', parent: null, budgetMonthly: 80 },
      { id: 'education',      name: 'Éducation',       icon: '📚', color: '#FFB347', parent: null, budgetMonthly: 50 },
      { id: 'epargne',        name: 'Épargne',         icon: '🏦', color: '#87CEEB', parent: null, budgetMonthly: 400 },
      { id: 'salaire',        name: 'Salaire',         icon: '💼', color: '#32CD32', parent: null, budgetMonthly: null },
      { id: 'autre_revenu',   name: 'Autres revenus',  icon: '💰', color: '#20B2AA', parent: null, budgetMonthly: null },
      { id: 'autre',          name: 'Autre',           icon: '📎', color: '#D3D3D3', parent: null, budgetMonthly: null },
    ],
    envelopes: [],
    goals: [],
    recurring: [],
    budgetSettings: {
      currency: '€',
      monthlyIncome: 0,
      alertThreshold: 80,
    },

    // --- Maison ---
    maison: {
      projects: [],
      rooms: [],
    },

    // --- Santé ---
    sante: {
      metrics: [],
      appointments: [],
      activities: [],
      medications: [],
    },

    // --- Projets ---
    projets: {
      items: [],
      milestones: [],
    },

    // --- Véhicules ---
    vehicules: {
      items: [],
      maintenances: [],
      fuelLogs: [],
    },

    // --- Coffre-fort ---
    coffre: {
      documents: [],
    },
  };

  function now() { return new Date().toISOString(); }

  function load() {
    try {
      const raw = localStorage.getItem(KEY_DATA);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      console.error('[Storage] Erreur lecture:', e);
      return null;
    }
  }

  function save(data) {
    try {
      data._updatedAt = now();
      localStorage.setItem(KEY_DATA, JSON.stringify(data));
      return true;
    } catch (e) {
      console.error('[Storage] Erreur écriture:', e);
      if (e.name === 'QuotaExceededError') {
        CAP360.UI && CAP360.UI.toast('Stockage plein — supprimez des anciennes données', 'error');
      }
      return false;
    }
  }

  function init() {
    let data = load();
    if (!data) {
      data = JSON.parse(JSON.stringify(DEFAULT_DATA));
      data._createdAt = now();
      data._updatedAt = now();
      save(data);
    }
    CAP360._data = data;
    return data;
  }

  function get() {
    return CAP360._data;
  }

  function persist() {
    return save(CAP360._data);
  }

  /* --- Backups --- */

  function backup() {
    const d = get();
    const b = JSON.stringify(d, null, 2);
    const blob = new Blob([b], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `cap360-backup-${new Date().toISOString().slice(0,10)}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }

  function restore(jsonString) {
    try {
      const parsed = JSON.parse(jsonString);
      if (!parsed._version) throw new Error('Format invalide');
      CAP360._data = parsed;
      persist();
      return true;
    } catch (e) {
      console.error('[Storage] Restauration échouée:', e);
      return false;
    }
  }

  /* --- Generic CRUD helpers --- */

  function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  }

  function addItem(collection, item) {
    const d = get();
    if (!Array.isArray(d[collection])) throw new Error('Collection introuvable: ' + collection);
    item.id = item.id || generateId();
    item.createdAt = now();
    d[collection].push(item);
    persist();
    return item;
  }

  function updateItem(collection, id, patch) {
    const d = get();
    const arr = d[collection];
    if (!Array.isArray(arr)) throw new Error('Collection introuvable: ' + collection);
    const idx = arr.findIndex(x => x.id === id);
    if (idx === -1) throw new Error('Item introuvable: ' + id);
    arr[idx] = { ...arr[idx], ...patch, updatedAt: now() };
    persist();
    return arr[idx];
  }

  function removeItem(collection, id) {
    const d = get();
    const arr = d[collection];
    if (!Array.isArray(arr)) throw new Error('Collection introuvable: ' + collection);
    const idx = arr.findIndex(x => x.id === id);
    if (idx === -1) return false;
    arr.splice(idx, 1);
    persist();
    return true;
  }

  function getItem(collection, id) {
    const d = get();
    const arr = d[collection];
    if (!Array.isArray(arr)) return null;
    return arr.find(x => x.id === id) || null;
  }

  /* --- Nested helpers (maison, sante, etc.) --- */

  function addNested(module, collection, item) {
    const d = get();
    if (!d[module]) d[module] = {};
    if (!Array.isArray(d[module][collection])) d[module][collection] = [];
    item.id = item.id || generateId();
    item.createdAt = now();
    d[module][collection].push(item);
    persist();
    return item;
  }

  function updateNested(module, collection, id, patch) {
    const d = get();
    const arr = d[module][collection];
    const idx = arr.findIndex(x => x.id === id);
    if (idx === -1) throw new Error('Item introuvable');
    arr[idx] = { ...arr[idx], ...patch, updatedAt: now() };
    persist();
    return arr[idx];
  }

  function removeNested(module, collection, id) {
    const d = get();
    const arr = d[module][collection];
    const idx = arr.findIndex(x => x.id === id);
    if (idx === -1) return false;
    arr.splice(idx, 1);
    persist();
    return true;
  }

  /* --- Export API --- */

  return {
    init,
    get,
    persist,
    backup,
    restore,
    generateId,
    addItem,
    updateItem,
    removeItem,
    getItem,
    addNested,
    updateNested,
    removeNested,
    DEFAULT_DATA,
  };

}());
