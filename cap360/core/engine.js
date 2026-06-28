/* ============================================
   CAP360 Engine — Calculs & Logique métier
   ============================================ */

'use strict';

CAP360.Engine = (function () {

  /* ---- Formatters ---- */

  function currency(amount, symbol) {
    const s = symbol || CAP360.Storage.get().budgetSettings?.currency || '€';
    const abs = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';
    return `${sign}${abs.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${s}`;
  }

  function currencyShort(amount) {
    const abs = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';
    if (abs >= 1000000) return `${sign}${(abs/1000000).toFixed(1)}M €`;
    if (abs >= 1000)    return `${sign}${(abs/1000).toFixed(1)}k €`;
    return `${sign}${abs.toFixed(0)} €`;
  }

  function dateStr(date) {
    const d = date instanceof Date ? date : new Date(date);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function dateShort(date) {
    const d = date instanceof Date ? date : new Date(date);
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
  }

  function monthLabel(date) {
    const d = date instanceof Date ? date : new Date(date);
    return d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
  }

  function dayName(date) {
    const d = date instanceof Date ? date : new Date(date);
    return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  /* ---- Date helpers ---- */

  function startOfMonth(date) {
    const d = date ? new Date(date) : new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1);
  }

  function endOfMonth(date) {
    const d = date ? new Date(date) : new Date();
    return new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59);
  }

  function addMonths(date, n) {
    const d = new Date(date);
    d.setMonth(d.getMonth() + n);
    return d;
  }

  function addDays(date, n) {
    const d = new Date(date);
    d.setDate(d.getDate() + n);
    return d;
  }

  function isSameMonth(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth();
  }

  function isSameDay(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  }

  function daysBetween(a, b) {
    return Math.round((b - a) / (1000 * 60 * 60 * 24));
  }

  /* ---- Account calculations ---- */

  function getAccountBalance(accountId) {
    const d = CAP360.Storage.get();
    const account = d.accounts.find(a => a.id === accountId);
    if (!account) return 0;
    const initial = account.initialBalance || 0;
    const txSum = d.transactions
      .filter(tx => tx.accountId === accountId && tx.status !== 'cancelled')
      .reduce((sum, tx) => sum + (tx.amount || 0), 0);
    return initial + txSum;
  }

  function getTotalBalance() {
    const d = CAP360.Storage.get();
    return d.accounts.reduce((sum, a) => sum + getAccountBalance(a.id), 0);
  }

  /* ---- Period transactions ---- */

  function getTransactionsForPeriod(start, end, accountId) {
    const d = CAP360.Storage.get();
    return d.transactions.filter(tx => {
      const txDate = new Date(tx.date);
      const inPeriod = txDate >= start && txDate <= end;
      const inAccount = !accountId || tx.accountId === accountId;
      return inPeriod && inAccount && tx.status !== 'cancelled';
    });
  }

  function getPeriodSummary(start, end, accountId) {
    const txs = getTransactionsForPeriod(start, end, accountId);
    const income   = txs.filter(tx => tx.amount > 0).reduce((s, tx) => s + tx.amount, 0);
    const expenses = txs.filter(tx => tx.amount < 0).reduce((s, tx) => s + tx.amount, 0);
    return { income, expenses, balance: income + expenses, count: txs.length };
  }

  function getCurrentMonthSummary() {
    return getPeriodSummary(startOfMonth(), endOfMonth());
  }

  /* ---- Category breakdown ---- */

  function getCategoryBreakdown(start, end) {
    const d   = CAP360.Storage.get();
    const txs = getTransactionsForPeriod(start, end);
    const map = {};

    txs.filter(tx => tx.amount < 0).forEach(tx => {
      const catId = tx.categoryId || 'autre';
      if (!map[catId]) {
        const cat = d.categories.find(c => c.id === catId) || { id: catId, name: 'Autre', color: '#D3D3D3', icon: '📎' };
        map[catId] = { ...cat, total: 0, count: 0 };
      }
      map[catId].total += Math.abs(tx.amount);
      map[catId].count++;
    });

    return Object.values(map).sort((a, b) => b.total - a.total);
  }

  /* ---- Budget vs actual ---- */

  function getBudgetVsActual(start, end) {
    const d    = CAP360.Storage.get();
    const breakdown = getCategoryBreakdown(start, end);

    return breakdown.map(cat => {
      const budget = cat.budgetMonthly || 0;
      const spent  = cat.total;
      const pct    = budget > 0 ? Math.min(Math.round((spent / budget) * 100), 999) : null;
      return { ...cat, budget, spent, pct, over: budget > 0 && spent > budget };
    });
  }

  /* ---- Recurring transactions ---- */

  function getNextOccurrence(recurring, fromDate) {
    const from = fromDate || new Date();
    const last = recurring.lastDate ? new Date(recurring.lastDate) : new Date(recurring.startDate);
    let next = new Date(last);

    switch (recurring.frequency) {
      case 'weekly':    next.setDate(next.getDate() + 7); break;
      case 'biweekly':  next.setDate(next.getDate() + 14); break;
      case 'monthly':   next.setMonth(next.getMonth() + 1); break;
      case 'quarterly': next.setMonth(next.getMonth() + 3); break;
      case 'yearly':    next.setFullYear(next.getFullYear() + 1); break;
      default: return null;
    }

    return next;
  }

  function getUpcomingTransactions(days) {
    const d      = CAP360.Storage.get();
    const from   = new Date();
    const to     = addDays(from, days || 30);
    const result = [];

    d.recurring.filter(r => r.active !== false).forEach(r => {
      let next = new Date(r.nextDate || getNextOccurrence(r));
      while (next && next <= to) {
        if (next >= from) {
          result.push({
            date:       new Date(next),
            amount:     r.amount,
            description: r.description,
            categoryId: r.categoryId,
            recurring:  true,
            recurringId: r.id,
          });
        }
        const tmp = new Date(next);
        switch (r.frequency) {
          case 'weekly':    tmp.setDate(tmp.getDate() + 7); break;
          case 'biweekly':  tmp.setDate(tmp.getDate() + 14); break;
          case 'monthly':   tmp.setMonth(tmp.getMonth() + 1); break;
          case 'quarterly': tmp.setMonth(tmp.getMonth() + 3); break;
          case 'yearly':    tmp.setFullYear(tmp.getFullYear() + 1); break;
          default: next = null; continue;
        }
        next = tmp;
      }
    });

    return result.sort((a, b) => a.date - b.date);
  }

  /* ---- Forecast (6 months) ---- */

  function generateForecast(months) {
    const n   = months || 6;
    const d   = CAP360.Storage.get();
    const now = new Date();
    const res = [];

    let runningBalance = getTotalBalance();

    for (let i = 0; i < n; i++) {
      const start = startOfMonth(addMonths(now, i));
      const end   = endOfMonth(start);

      const recurring = d.recurring
        .filter(r => r.active !== false)
        .reduce((sum, r) => {
          const occ = occurrencesInPeriod(r, start, end);
          return sum + (r.amount * occ);
        }, 0);

      const income   = d.budgetSettings.monthlyIncome || 0;
      const monthNet = income + recurring;
      runningBalance += monthNet;

      res.push({
        label:   start.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }),
        start,
        end,
        income,
        recurring,
        net:     monthNet,
        balance: runningBalance,
      });
    }

    return res;
  }

  function occurrencesInPeriod(recurring, start, end) {
    const ms = {
      weekly:    7 * 86400000,
      biweekly:  14 * 86400000,
      monthly:   null,
      quarterly: null,
      yearly:    null,
    };
    const period = end - start;
    if (ms[recurring.frequency]) {
      return Math.max(1, Math.round(period / ms[recurring.frequency]));
    }
    if (recurring.frequency === 'monthly') return 1;
    if (recurring.frequency === 'quarterly') return Math.round(period / (90 * 86400000));
    if (recurring.frequency === 'yearly') return Math.round(period / (365 * 86400000));
    return 0;
  }

  /* ---- Goals progress ---- */

  function getGoalProgress(goal) {
    const current = goal.current || 0;
    const target  = goal.target  || 1;
    const pct     = Math.min(Math.round((current / target) * 100), 100);
    const remaining = target - current;
    const deadline  = goal.deadline ? new Date(goal.deadline) : null;
    const daysLeft  = deadline ? daysBetween(new Date(), deadline) : null;
    const monthly   = (daysLeft && daysLeft > 0) ? (remaining / (daysLeft / 30)) : null;
    return { pct, remaining, daysLeft, monthly, done: pct >= 100 };
  }

  /* ---- Envelopes ---- */

  function getEnvelopeUsage(envelope, start, end) {
    const s   = start || startOfMonth();
    const e   = end   || endOfMonth();
    const txs = getTransactionsForPeriod(s, e);
    const spent = txs
      .filter(tx => tx.amount < 0 && tx.categoryId === envelope.categoryId)
      .reduce((sum, tx) => sum + Math.abs(tx.amount), 0);
    const pct = envelope.amount > 0 ? Math.min(Math.round((spent / envelope.amount) * 100), 999) : 0;
    return { spent, remaining: envelope.amount - spent, pct, over: spent > envelope.amount };
  }

  /* ---- Alerts ---- */

  function generateAlerts() {
    const d       = CAP360.Storage.get();
    const now     = new Date();
    const alerts  = [];
    const { start, end } = { start: startOfMonth(), end: endOfMonth() };
    const budgetActual = getBudgetVsActual(start, end);
    const threshold = d.budgetSettings.alertThreshold || 80;

    budgetActual.forEach(cat => {
      if (cat.over) {
        alerts.push({ type: 'danger', icon: '⚠️', title: `Dépassement ${cat.name}`, desc: `${currency(cat.spent)} dépensé vs budget ${currency(cat.budget)}` });
      } else if (cat.pct >= threshold) {
        alerts.push({ type: 'warning', icon: '🔶', title: `Budget ${cat.name} à ${cat.pct}%`, desc: `Il reste ${currency(cat.budget - cat.spent)} pour ce mois` });
      }
    });

    const upcoming = getUpcomingTransactions(7);
    upcoming.filter(tx => tx.amount < 0).forEach(tx => {
      alerts.push({ type: 'info', icon: '📅', title: tx.description, desc: `Prélèvement de ${currency(Math.abs(tx.amount))} le ${dateShort(tx.date)}` });
    });

    d.goals.forEach(goal => {
      const prog = getGoalProgress(goal);
      if (prog.daysLeft !== null && prog.daysLeft < 30 && !prog.done) {
        alerts.push({ type: 'warning', icon: '🎯', title: `Objectif "${goal.name}" bientôt`, desc: `${prog.daysLeft} jours restants, ${currency(prog.remaining)} à épargner` });
      }
    });

    return alerts;
  }

  /* ---- OFX Parser ---- */

  function parseOFX(content) {
    const transactions = [];
    const clean = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    const trnRegex = /<STMTTRN>([\s\S]*?)<\/STMTTRN>/gi;
    let match;

    while ((match = trnRegex.exec(clean)) !== null) {
      const block = match[1];

      const get = (tag) => {
        const m = block.match(new RegExp(`<${tag}>([^<\n]+)`, 'i'));
        return m ? m[1].trim() : null;
      };

      const dateRaw = get('DTPOSTED') || get('DTUSER');
      const date    = parseOFXDate(dateRaw);
      const amountStr = get('TRNAMT') || '0';
      const amount    = parseFloat(amountStr.replace(',', '.'));
      const name      = get('NAME') || get('MEMO') || 'Transaction';
      const memo      = get('MEMO') || '';
      const fitid     = get('FITID') || CAP360.Storage.generateId();
      const type      = get('TRNTYPE') || 'OTHER';

      if (!isNaN(amount)) {
        transactions.push({ date, amount, description: name, memo, fitid, ofxType: type });
      }
    }

    if (transactions.length === 0) {
      const legacyMatch = clean.match(/DTPOSTED[\s\S]*?TRNAMT[\s\S]*?NAME/g);
      if (legacyMatch) {
        const dtReg  = /DTPOSTED:([^\n]+)/g;
        const amReg  = /TRNAMT:([^\n]+)/g;
        const nmReg  = /NAME:([^\n]+)/g;
        const memReg = /MEMO:([^\n]+)/g;
        const amReg2 = /TRNAMT:([^\n]+)/g;
        let dt, am, nm;
        while ((dt = dtReg.exec(clean)) && (am = amReg2.exec(clean)) && (nm = nmReg.exec(clean))) {
          const amount = parseFloat((am[1] || '0').trim().replace(',', '.'));
          if (!isNaN(amount)) {
            transactions.push({
              date:        parseOFXDate(dt[1].trim()),
              amount,
              description: nm[1].trim(),
              memo:        '',
              fitid:       CAP360.Storage.generateId(),
              ofxType:     'OTHER',
            });
          }
        }
      }
    }

    return transactions;
  }

  function parseOFXDate(str) {
    if (!str) return new Date();
    const s = str.replace(/\[.*$/, '').trim();
    const y = parseInt(s.substring(0, 4));
    const m = parseInt(s.substring(4, 6)) - 1;
    const d = parseInt(s.substring(6, 8));
    const h = parseInt(s.substring(8, 10) || 0);
    const mn= parseInt(s.substring(10, 12) || 0);
    if (isNaN(y) || isNaN(m) || isNaN(d)) return new Date();
    return new Date(y, m, d, h, mn);
  }

  /* ---- CSV Parser ---- */

  function parseCSV(content, mapping) {
    const lines = content.trim().split('\n');
    const headers = lines[0].split(mapping.delimiter || ';').map(h => h.trim().replace(/"/g, ''));
    const transactions = [];

    for (let i = 1; i < lines.length; i++) {
      const line = lines[i];
      if (!line.trim()) continue;
      const cols = line.split(mapping.delimiter || ';').map(c => c.trim().replace(/"/g, ''));
      const obj  = {};
      headers.forEach((h, idx) => { obj[h] = cols[idx] || ''; });

      const dateRaw = obj[mapping.dateCol];
      const amountRaw = obj[mapping.amountCol] || '0';
      const desc = obj[mapping.descCol] || 'Transaction';

      const amount = parseFloat(amountRaw.replace(/\s/g, '').replace(',', '.'));
      let date;
      try {
        const parts = dateRaw.split(/[\/\-.]/);
        if (parts.length === 3) {
          if (parts[0].length === 4) date = new Date(parseInt(parts[0]), parseInt(parts[1])-1, parseInt(parts[2]));
          else                       date = new Date(parseInt(parts[2]), parseInt(parts[1])-1, parseInt(parts[0]));
        } else {
          date = new Date(dateRaw);
        }
      } catch(e) { date = new Date(); }

      if (!isNaN(amount)) {
        transactions.push({ date, amount, description: desc, fitid: CAP360.Storage.generateId() });
      }
    }

    return transactions;
  }

  /* ---- Daily balance history (for chart) ---- */

  function getDailyBalanceHistory(days) {
    const d     = CAP360.Storage.get();
    const n     = days || 90;
    const today = new Date();
    const from  = addDays(today, -n);
    const txs   = d.transactions
      .filter(tx => new Date(tx.date) >= from && tx.status !== 'cancelled')
      .sort((a, b) => new Date(a.date) - new Date(b.date));

    const initialBalance = getTotalBalance() - txs.reduce((s, tx) => s + tx.amount, 0);
    const result = [];
    let balance  = initialBalance;
    const cursor = new Date(from);

    while (cursor <= today) {
      const dayTxs = txs.filter(tx => isSameDay(new Date(tx.date), cursor));
      dayTxs.forEach(tx => { balance += tx.amount; });
      result.push({ date: new Date(cursor), balance });
      cursor.setDate(cursor.getDate() + 1);
    }

    return result;
  }

  /* ---- Monthly balance history ---- */

  function getMonthlyHistory(months) {
    const n   = months || 12;
    const now = new Date();
    const res = [];

    for (let i = n - 1; i >= 0; i--) {
      const start = startOfMonth(addMonths(now, -i));
      const end   = endOfMonth(start);
      const sum   = getPeriodSummary(start, end);
      res.push({
        label:    start.toLocaleDateString('fr-FR', { month: 'short' }),
        income:   sum.income,
        expenses: Math.abs(sum.expenses),
        net:      sum.balance,
      });
    }

    return res;
  }

  /* ---- Savings rate ---- */

  function getSavingsRate() {
    const d    = CAP360.Storage.get();
    const now  = new Date();
    const start = startOfMonth(addMonths(now, -2));
    const end   = endOfMonth();
    const sum   = getPeriodSummary(start, end);
    if (sum.income <= 0) return 0;
    const saved = Math.max(0, sum.balance);
    return Math.round((saved / sum.income) * 100);
  }

  /* ---- Auto-suggest category ---- */

  const CATEGORY_RULES = [
    { keywords: ['leclerc','carrefour','auchan','lidl','aldi','monoprix','casino','supermarche','super u','intermarche','courses'], cat: 'alimentation' },
    { keywords: ['sncf','ratp','bus','metro','velo','covoiturage','blablacar','uber','taxi','essence','total','bp','shell','station'], cat: 'transport' },
    { keywords: ['loyer','electricite','edf','eau','gaz','internet','sfr','orange','free','bouygues'], cat: 'logement' },
    { keywords: ['pharmacie','medecin','docteur','hopital','mutuelle','dentiste','opticien'], cat: 'sante' },
    { keywords: ['cinema','theatre','spotify','netflix','deezer','amazon prime','jeux','livre'], cat: 'loisirs' },
    { keywords: ['restaurant','mcdonald','pizzeria','brasserie','burger','sushi','kebab'], cat: 'restaurant' },
    { keywords: ['zara','h&m','primark','kiabi','vetements','chaussures'], cat: 'vetements' },
    { keywords: ['abonnement','mensuel','adhesion','cotisation'], cat: 'abonnements' },
    { keywords: ['salaire','virement employeur','paie'], cat: 'salaire' },
  ];

  function suggestCategory(description) {
    const lower = (description || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    for (const rule of CATEGORY_RULES) {
      if (rule.keywords.some(kw => lower.includes(kw))) return rule.cat;
    }
    return 'autre';
  }

  /* ---- Export ---- */

  return {
    currency,
    currencyShort,
    dateStr,
    dateShort,
    monthLabel,
    dayName,
    startOfMonth,
    endOfMonth,
    addMonths,
    addDays,
    isSameMonth,
    isSameDay,
    daysBetween,
    getAccountBalance,
    getTotalBalance,
    getTransactionsForPeriod,
    getPeriodSummary,
    getCurrentMonthSummary,
    getCategoryBreakdown,
    getBudgetVsActual,
    getUpcomingTransactions,
    generateForecast,
    getGoalProgress,
    getEnvelopeUsage,
    generateAlerts,
    parseOFX,
    parseCSV,
    getDailyBalanceHistory,
    getMonthlyHistory,
    getSavingsRate,
    suggestCategory,
  };

}());
