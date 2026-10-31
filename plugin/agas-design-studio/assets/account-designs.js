/* AGAS My Account: accessible full-size saved artwork preview. No changes to design data. */
(() => {
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('agas-account-designs');
    if (!root) return;
    const dialog = document.getElementById('agas-account-preview-dialog');
    const image = document.getElementById('agas-account-modal-image');
    const label = document.getElementById('agas-account-modal-title');
    if (!dialog || !image || !label) return;
    let lastTrigger = null;
    root.addEventListener('click', event => {
      const trigger = event.target.closest('[data-agas-preview]');
      if (!trigger || !root.contains(trigger)) return;
      const src = trigger.getAttribute('data-agas-preview');
      if (!src) return;
      lastTrigger = trigger;
      label.textContent = trigger.getAttribute('data-agas-title') || 'Design preview';
      image.src = src;
      image.alt = `Front artwork preview: ${label.textContent}`;
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else window.open(src, '_blank', 'noopener');
    });
    const close = dialog.querySelector('.agas-account-modal-close');
    if (close) close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
      image.removeAttribute('src');
      if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    });
  });
})();
