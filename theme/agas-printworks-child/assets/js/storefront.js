(() => {
  'use strict';
  const trigger = document.querySelector('[data-pw-nav-toggle]');
  const nav = document.getElementById('pw-mobile-nav');
  if (trigger && nav) {
    const close = () => {
      trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('aria-label', 'Open navigation');
      document.body.classList.remove('pw-nav-is-open');
    };
    trigger.addEventListener('click', () => {
      const open = trigger.getAttribute('aria-expanded') !== 'true';
      trigger.setAttribute('aria-expanded', String(open));
      trigger.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      document.body.classList.toggle('pw-nav-is-open', open);
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    document.addEventListener('click', e => { if (!trigger.contains(e.target) && !nav.contains(e.target)) close(); });
  }
})();
