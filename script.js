/* ═══════════════════════════════════════════════════════════════
   ZONE 85 — Comportements
   ═══════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  /* ─── Header sticky : ombre / fond au scroll ──────────────── */
  const header = document.getElementById('siteHeader');
  const onScroll = () => {
    if (window.scrollY > 8) header.classList.add('scrolled');
    else header.classList.remove('scrolled');
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ─── Menu mobile ─────────────────────────────────────────── */
  const burger = document.getElementById('burger');
  const mobileNav = document.getElementById('mobileNav');

  burger.addEventListener('click', () => {
    const isOpen = burger.classList.toggle('open');
    mobileNav.classList.toggle('open', isOpen);
    burger.setAttribute('aria-expanded', String(isOpen));
    mobileNav.setAttribute('aria-hidden', String(!isOpen));
  });

  mobileNav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      burger.classList.remove('open');
      mobileNav.classList.remove('open');
      burger.setAttribute('aria-expanded', 'false');
      mobileNav.setAttribute('aria-hidden', 'true');
    });
  });

  /* ─── Compteur Zonautes animé à l'arrivée ─────────────────── */
  const statNum = document.querySelector('.hero-stats .stat-num');
  if (statNum) {
    let animated = false;
    const animateCount = () => {
      if (animated) return;
      animated = true;
      const target = 22;
      let current = 0;
      const step = () => {
        current += 1;
        statNum.firstChild.textContent = current;
        if (current < target) requestAnimationFrame(step);
      };
      statNum.firstChild.textContent = '0';
      requestAnimationFrame(step);
    };

    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) animateCount(); });
    }, { threshold: 0.5 });
    io.observe(statNum);
  }

  /* ─── Apparition au scroll (reveal discret) ───────────────── */
  const reveals = document.querySelectorAll(
    '.card, .rando-card, .section-head, .hero-text, .hero-visual, .ticket'
  );
  reveals.forEach((el) => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(14px)';
    el.style.transition =
      'opacity .6s cubic-bezier(0.22,1,0.36,1), transform .6s cubic-bezier(0.22,1,0.36,1)';
  });

  const revealIO = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
        revealIO.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  reveals.forEach((el) => revealIO.observe(el));

})();
