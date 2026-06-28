/* ============================================
   CAP360 Charts — Wrappers Chart.js
   ============================================ */

'use strict';

CAP360.Charts = (function () {

  const instances = {};

  /* ---- Default chart options ---- */

  function baseFont() {
    return { family: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Inter', sans-serif", size: 12 };
  }

  function baseGrid() {
    return { color: 'rgba(60,60,67,0.07)', borderDash: [3, 3] };
  }

  function baseTick() {
    return { color: '#aeaeb2', font: baseFont() };
  }

  function destroy(id) {
    if (instances[id]) {
      instances[id].destroy();
      delete instances[id];
    }
  }

  function get(id) { return instances[id] || null; }

  /* ---- Treasury / Balance line chart ---- */

  function treasury(canvasId, data) {
    destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const labels  = data.map(d => CAP360.Engine.dateShort(d.date));
    const values  = data.map(d => d.balance);
    const isPos   = values[values.length - 1] >= 0;
    const color   = isPos ? '#34C759' : '#FF3B30';

    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, isPos ? 'rgba(52,199,89,0.2)' : 'rgba(255,59,48,0.2)');
    gradient.addColorStop(1, 'rgba(0,0,0,0)');

    instances[canvasId] = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          data:        values,
          borderColor: color,
          borderWidth: 2,
          fill:        true,
          backgroundColor: gradient,
          tension:     0.4,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBackgroundColor: color,
          pointHoverBorderColor: '#fff',
          pointHoverBorderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1c1c1e',
            titleColor: '#aeaeb2',
            bodyColor: '#ffffff',
            bodyFont: { size: 14, weight: '600' },
            padding: 12,
            cornerRadius: 10,
            displayColors: false,
            callbacks: {
              label: ctx => CAP360.Engine.currency(ctx.raw),
            },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { ...baseTick(), maxTicksLimit: 10, maxRotation: 0 },
            border: { display: false },
          },
          y: {
            grid: baseGrid(),
            ticks: { ...baseTick(), callback: v => CAP360.Engine.currencyShort(v) },
            border: { display: false },
          },
        },
      },
    });

    return instances[canvasId];
  }

  /* ---- Spending donut ---- */

  function donut(canvasId, categories) {
    destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const top = categories.slice(0, 8);
    const labels = top.map(c => c.name);
    const values = top.map(c => c.total);
    const colors = top.map(c => c.color);

    instances[canvasId] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data:             values,
          backgroundColor:  colors,
          borderColor:      '#ffffff',
          borderWidth:      3,
          hoverOffset:      6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        animation: { duration: 600, easing: 'easeOutQuart' },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1c1c1e',
            titleColor: '#aeaeb2',
            bodyColor: '#ffffff',
            bodyFont: { size: 13, weight: '600' },
            padding: 10,
            cornerRadius: 10,
            callbacks: {
              label: ctx => ` ${CAP360.Engine.currency(ctx.raw)}`,
            },
          },
        },
      },
    });

    return instances[canvasId];
  }

  /* ---- Monthly income vs expenses bar chart ---- */

  function monthlyBar(canvasId, data) {
    destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    instances[canvasId] = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(d => d.label),
        datasets: [
          {
            label:           'Revenus',
            data:            data.map(d => d.income),
            backgroundColor: 'rgba(52,199,89,0.7)',
            borderRadius:    6,
            borderSkipped:   false,
          },
          {
            label:           'Dépenses',
            data:            data.map(d => d.expenses),
            backgroundColor: 'rgba(255,59,48,0.65)',
            borderRadius:    6,
            borderSkipped:   false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: { font: baseFont(), color: '#636366', boxWidth: 10, boxHeight: 10, borderRadius: 5, useBorderRadius: true, padding: 16 },
          },
          tooltip: {
            backgroundColor: '#1c1c1e',
            titleColor: '#aeaeb2',
            bodyColor: '#ffffff',
            bodyFont: { size: 13 },
            padding: 10,
            cornerRadius: 10,
            callbacks: { label: ctx => ` ${ctx.dataset.label}: ${CAP360.Engine.currency(ctx.raw)}` },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: baseTick(),
            border: { display: false },
          },
          y: {
            grid: baseGrid(),
            ticks: { ...baseTick(), callback: v => CAP360.Engine.currencyShort(v) },
            border: { display: false },
          },
        },
      },
    });

    return instances[canvasId];
  }

  /* ---- Forecast line chart ---- */

  function forecast(canvasId, data) {
    destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    instances[canvasId] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(d => d.label),
        datasets: [{
          label:           'Solde prévisionnel',
          data:            data.map(d => d.balance),
          borderColor:     '#007AFF',
          borderWidth:     2.5,
          backgroundColor: 'rgba(0,122,255,0.08)',
          fill:            true,
          tension:         0.35,
          pointRadius:     4,
          pointBackgroundColor: '#007AFF',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600 },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1c1c1e',
            titleColor: '#aeaeb2',
            bodyColor: '#fff',
            bodyFont: { size: 13, weight: '600' },
            padding: 10,
            cornerRadius: 10,
            displayColors: false,
            callbacks: { label: ctx => CAP360.Engine.currency(ctx.raw) },
          },
        },
        scales: {
          x: { grid: { display: false }, ticks: baseTick(), border: { display: false } },
          y: {
            grid: baseGrid(),
            ticks: { ...baseTick(), callback: v => CAP360.Engine.currencyShort(v) },
            border: { display: false },
          },
        },
      },
    });

    return instances[canvasId];
  }

  /* ---- Goal progress ring ---- */

  function ring(canvasId, pct, color) {
    destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const c = color || '#007AFF';
    instances[canvasId] = new Chart(ctx, {
      type: 'doughnut',
      data: {
        datasets: [{
          data:            [pct, 100 - pct],
          backgroundColor: [c, 'rgba(0,0,0,0.06)'],
          borderWidth:     0,
          hoverOffset:     0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '82%',
        animation: { duration: 700, easing: 'easeOutQuart' },
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
      },
    });

    return instances[canvasId];
  }

  /* ---- Savings history area ---- */

  function savingsArea(canvasId, data) {
    destroy(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(0,122,255,0.18)');
    gradient.addColorStop(1, 'rgba(0,122,255,0)');

    instances[canvasId] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(d => d.label),
        datasets: [{
          data:            data.map(d => d.value),
          borderColor:     '#007AFF',
          borderWidth:     2,
          fill:            true,
          backgroundColor: gradient,
          tension:         0.4,
          pointRadius:     0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
          x: { display: false },
          y: { display: false },
        },
      },
    });

    return instances[canvasId];
  }

  return { destroy, get, treasury, donut, monthlyBar, forecast, ring, savingsArea };

}());
