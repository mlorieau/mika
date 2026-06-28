/* ============================================
   CAP360 Migration — Évolution du schéma
   ============================================ */

'use strict';

CAP360.Migration = (function () {

  const CURRENT_VERSION = 2;

  const migrations = {
    /* v1 → v2 : ajout modules secondaires et champs manquants */
    2: function (data) {
      if (!data.maison)     data.maison     = { projects: [], rooms: [] };
      if (!data.sante)      data.sante      = { metrics: [], appointments: [], activities: [], medications: [] };
      if (!data.projets)    data.projets    = { items: [], milestones: [] };
      if (!data.vehicules)  data.vehicules  = { items: [], maintenances: [], fuelLogs: [] };
      if (!data.coffre)     data.coffre     = { documents: [] };
      if (!data.envelopes)  data.envelopes  = [];
      if (!data.goals)      data.goals      = [];
      if (!data.recurring)  data.recurring  = [];
      if (!data.budgetSettings) data.budgetSettings = { currency: '€', monthlyIncome: 0, alertThreshold: 80 };

      /* S'assurer que toutes les transactions ont un champ status */
      if (Array.isArray(data.transactions)) {
        data.transactions.forEach(tx => {
          if (!tx.status) tx.status = 'cleared';
          if (!tx.tags)   tx.tags   = [];
        });
      }

      /* S'assurer que les catégories ont budgetMonthly */
      if (Array.isArray(data.categories)) {
        data.categories.forEach(cat => {
          if (cat.budgetMonthly === undefined) cat.budgetMonthly = null;
        });
      }

      return data;
    },
  };

  function run() {
    const data = CAP360.Storage.get();
    let v = data._version || 1;

    if (v === CURRENT_VERSION) return;

    console.log(`[Migration] Schema v${v} → v${CURRENT_VERSION}`);

    while (v < CURRENT_VERSION) {
      v++;
      if (migrations[v]) {
        try {
          migrations[v](data);
          console.log(`[Migration] v${v} OK`);
        } catch (e) {
          console.error(`[Migration] Erreur v${v}:`, e);
        }
      }
    }

    data._version = CURRENT_VERSION;
    CAP360.Storage.persist();
    console.log('[Migration] Terminé');
  }

  return { run, CURRENT_VERSION };

}());
