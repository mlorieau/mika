/* ============================================
   CAP360 Paramètres — Configuration
   ============================================ */

'use strict';

CAP360.Parametres = (function () {

  let _view = null;

  function mount(container) {
    _view = container;
    render();
  }

  function render() {
    const d    = CAP360.Storage.get();
    const name = (d.budgetSettings && d.budgetSettings.userName) || '';

    _view.innerHTML = `
      <div class="module-header">
        <div class="module-title-block">
          <h1>⚙️ Paramètres</h1>
          <p>Configuration de votre espace personnel</p>
        </div>
      </div>

      <div style="max-width:600px;display:flex;flex-direction:column;gap:16px">

        <div class="card">
          <div class="card-header"><span class="card-title">Profil</span></div>
          <div class="form-group">
            <label class="form-label">Votre prénom</label>
            <input type="text" id="pm-name" class="form-input" value="${name}" placeholder="Mickaël">
          </div>
          <div style="margin-top:16px">
            <button class="btn btn-primary" onclick="PM.save()">Enregistrer</button>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Données</span></div>
          <p style="font-size:13px;color:var(--c-text-2);margin-bottom:16px;line-height:1.5">
            Toutes vos données sont stockées localement sur votre appareil. Aucune information n'est envoyée sur internet.
          </p>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-secondary" onclick="PM.exportData()">📥 Exporter</button>
            <button class="btn btn-secondary" onclick="PM.importData()">📤 Importer</button>
            <button class="btn btn-danger" onclick="PM.reset()">⚠️ Réinitialiser</button>
          </div>
          <input type="file" id="pm-import-file" accept=".json" style="display:none" onchange="PM.doImport(this)">
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Stockage</span></div>
          <div id="pm-storage-info" style="font-size:13px;color:var(--c-text-2);line-height:1.8"></div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">À propos</span></div>
          <div style="font-size:14px;color:var(--c-text-2);line-height:1.7">
            <strong style="color:var(--c-text-1)">CAP360 V3</strong><br>
            Cockpit de vie personnel<br>
            <span style="color:var(--c-text-3);font-size:13px">Données 100 % locales — aucune connexion requise.</span>
          </div>
        </div>

      </div>
    `;

    renderStorageInfo();
  }

  function renderStorageInfo() {
    const el = document.getElementById('pm-storage-info');
    if (!el) return;
    try {
      const d = CAP360.Storage.get();
      const projets   = (d.projets && d.projets.items    || []).length;
      const vehicules = (d.vehicules && d.vehicules.items || []).length;
      const coffre    = (d.coffre && d.coffre.documents   || []).length;
      const maison    = (d.maison && d.maison.projects    || []).length;

      const raw = JSON.stringify(d);
      const kb  = (new Blob([raw]).size / 1024).toFixed(1);

      el.innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <span style="color:var(--c-text-3)">Projets</span><span>${projets}</span>
          <span style="color:var(--c-text-3)">Véhicules</span><span>${vehicules}</span>
          <span style="color:var(--c-text-3)">Documents coffre</span><span>${coffre}</span>
          <span style="color:var(--c-text-3)">Travaux maison</span><span>${maison}</span>
          <span style="color:var(--c-text-3)">Taille données</span><span>${kb} Ko</span>
        </div>
      `;
    } catch (e) {
      el.textContent = 'Impossible de lire les informations de stockage.';
    }
  }

  window.PM = {
    save: function () {
      const name = (document.getElementById('pm-name').value || '').trim() || 'Mickaël';
      const d = CAP360.Storage.get();
      if (!d.budgetSettings) d.budgetSettings = {};
      d.budgetSettings.userName = name;
      CAP360.Storage.persist(d);
      const el = document.getElementById('sidebar-name');
      const av = document.getElementById('sidebar-avatar');
      if (el) el.textContent = name;
      if (av) av.textContent = name.charAt(0).toUpperCase();
      CAP360.UI.toast('Paramètres enregistrés', 'success');
    },
    exportData: function () {
      const d    = CAP360.Storage.get();
      const json = JSON.stringify(d, null, 2);
      const blob = new Blob([json], { type: 'application/json' });
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href     = url;
      a.download = 'cap360-backup-' + new Date().toISOString().slice(0, 10) + '.json';
      a.click();
      URL.revokeObjectURL(url);
      CAP360.UI.toast('Données exportées', 'success');
    },
    importData: function () {
      document.getElementById('pm-import-file').click();
    },
    doImport: function (input) {
      const file = input.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function (e) {
        try {
          const data = JSON.parse(e.target.result);
          CAP360.Storage.persist(data);
          CAP360.UI.toast('Données importées — rechargement...', 'success');
          setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
          CAP360.UI.toast('Fichier invalide', 'error');
        }
      };
      reader.readAsText(file);
    },
    reset: function () {
      if (!confirm('⚠️ Réinitialiser TOUTES les données CAP360 ?\n\nCette action est irréversible.\nVos données budget (cap360_v35) seront également supprimées.')) return;
      localStorage.removeItem('cap360_data');
      localStorage.removeItem('cap360_meta');
      localStorage.removeItem('cap360_v35');
      CAP360.UI.toast('Données réinitialisées — rechargement...', 'warning');
      setTimeout(() => window.location.reload(), 1500);
    },
  };

  return { mount };

}());
