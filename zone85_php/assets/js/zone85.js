/* =============================================
   ZONE 85 — JavaScript partagé
   zone85.js · version 1.0 statique
   Préparé pour conversion PHP + modules ES6
============================================= */

/* NAV SCROLL SHADOW */
window.addEventListener('scroll', () => {
  document.getElementById('navbar')?.classList.toggle('scrolled', window.scrollY > 10);
});

/* MOBILE MENU */
function toggleMenu() {
  document.getElementById('mobileMenu').classList.toggle('open');
}
document.addEventListener('click', e => {
  const mm = document.getElementById('mobileMenu');
  const hb = document.getElementById('hamburger');
  if (mm && hb && !mm.contains(e.target) && !hb.contains(e.target)) {
    mm.classList.remove('open');
  }
});

/* REVEAL ON SCROLL */
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

/* ANIMATE BARS (race, progress, XP) */
function animateBars() {
  document.querySelectorAll('.race-fill[data-width]').forEach(bar => {
    bar.style.width = bar.dataset.width + '%';
  });
  document.querySelectorAll('[data-prog]').forEach(bar => {
    bar.style.width = bar.dataset.prog + '%';
  });
  document.querySelectorAll('[data-xp-pct]').forEach(bar => {
    bar.style.width = bar.dataset.xpPct + '%';
  });
}
window.addEventListener('load', () => setTimeout(animateBars, 400));

/* PODIUM RISE ANIMATION */
function animatePodium() {
  const cols = document.querySelectorAll('.podium-col');
  if (!cols.length) return;
  const order = ['p2', 'p3', 'p1'];
  order.forEach((id, i) => {
    const el = document.getElementById(id);
    if (el) setTimeout(() => el.classList.add('risen'), i * 180);
  });
}
window.addEventListener('load', () => setTimeout(animatePodium, 600));

/* MASCOT POP (clan cards) */
const mascObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const img = e.target.querySelector('.clan-masc-wrap img');
      if (img) img.classList.add('popped');
      mascObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
document.querySelectorAll('.clan-card').forEach(c => mascObserver.observe(c));

/* COUNTDOWN — fin de la saison Camp d'Été Zone85 (31 août) */
function updateCountdown() {
  const endDate = new Date('2025-08-31T23:59:59');
  const now = new Date();
  const diff = endDate - now;
  if (diff <= 0) {
    document.querySelectorAll('.countdown-days').forEach(el => el.textContent = '0');
    document.querySelectorAll('.countdown-hours').forEach(el => el.textContent = '0');
    document.querySelectorAll('.countdown-mins').forEach(el => el.textContent = '0');
    return;
  }
  const days  = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const mins  = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  document.querySelectorAll('.countdown-days').forEach(el => el.textContent  = days);
  document.querySelectorAll('.countdown-hours').forEach(el => el.textContent = hours);
  document.querySelectorAll('.countdown-mins').forEach(el => el.textContent  = mins);
}
updateCountdown();
setInterval(updateCountdown, 60000);

/* GENERIC TAB SWITCHER
   Usage: onclick="switchTab('group-name', 'tab-id')"
   Tabs have [data-tab-group] + [data-tab-id]
   Panels have [data-panel-group] + [data-panel-id] */
function switchTab(group, id) {
  document.querySelectorAll(`[data-tab-group="${group}"]`).forEach(t => t.classList.remove('active'));
  document.querySelectorAll(`[data-panel-group="${group}"]`).forEach(p => p.style.display = 'none');
  const tab   = document.querySelector(`[data-tab-group="${group}"][data-tab-id="${id}"]`);
  const panel = document.querySelector(`[data-panel-group="${group}"][data-panel-id="${id}"]`);
  if (tab)   tab.classList.add('active');
  if (panel) panel.style.display = 'block';
}

/* MISSION FILTER
   Usage: onclick="filterMissions(this,'type-value')"
   Cards have [data-type] */
function filterMissions(btn, filter) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.mission-card').forEach(card => {
    const match = filter === 'all' || card.dataset.type === filter;
    card.style.display = match ? '' : 'none';
  });
}

/* FAQ ACCORDION */
function toggleFaq(el) {
  const item = el.closest('.faq-item');
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

/* CONFETTI (écran de bienvenue inscription) */
function spawnConfetti() {
  const emojis = ['🎉','⚡','🌊','🏆','🛡️','⚓','🌿','✨','🥐','❤️'];
  for (let i = 0; i < 28; i++) {
    const el = document.createElement('div');
    el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
    el.style.cssText = `position:fixed;top:-30px;left:${Math.random()*100}vw;font-size:${1.2+Math.random()*.8}rem;z-index:9999;pointer-events:none;animation:confettiFall ${1.5+Math.random()*2}s ease-in forwards`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  }
}
