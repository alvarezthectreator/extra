document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-nav-toggle]');
  const panel = document.querySelector('[data-nav-panel]');
  const overlay = document.querySelector('[data-nav-overlay]');
  const closeButtons = document.querySelectorAll('[data-nav-close]');
  const scrollLinks = document.querySelectorAll('[data-scroll-target]');
  const closeTarget = panel?.querySelector('[data-nav-close]') || toggle;
  let cleanupFocus = null;

  function openNav() {
    if (!toggle || !panel || !overlay) return;
    panel.classList.add('open');
    overlay.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('overflow-hidden');
    if (window.ExtraA11y) {
      cleanupFocus = window.ExtraA11y.trapFocus(panel, {
        initialFocus: closeTarget,
        returnFocusTo: toggle,
        onEscape: closeNav
      });
    }
  }

  function closeNav() {
    if (!toggle || !panel || !overlay) return;
    panel.classList.remove('open');
    overlay.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('overflow-hidden');
    if (cleanupFocus) {
      cleanupFocus();
      cleanupFocus = null;
    }
  }

  if (toggle && panel && overlay) {
    toggle.addEventListener('click', () => {
      const isOpen = panel.classList.contains('open');
      if (isOpen) {
        closeNav();
      } else {
        openNav();
      }
    });

    overlay.addEventListener('click', closeNav);
    closeButtons.forEach((button) => button.addEventListener('click', closeNav));

    panel.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', closeNav);
    });

    window.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeNav();
      }
    });
  }

  scrollLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      const targetId = link.dataset.scrollTarget;
      if (!targetId) return;

      const target = document.getElementById(targetId);
      if (!target) return;

      event.preventDefault();
      closeNav();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
});
