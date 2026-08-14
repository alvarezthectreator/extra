(function () {
  const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(', ');

  function getFocusableElements(container) {
    if (!container) return [];
    return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter((element) => {
      const style = window.getComputedStyle(element);
      return style.display !== 'none' && style.visibility !== 'hidden';
    });
  }

  function focusElement(element) {
    if (!element || typeof element.focus !== 'function') return;
    element.focus({ preventScroll: true });
  }

  function canReceiveFocus(element) {
    if (!element || !(element instanceof HTMLElement)) return false;
    if (!document.contains(element)) return false;
    if (element.hasAttribute('disabled')) return false;
    const style = window.getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden';
  }

  function resolveTarget(target, container) {
    if (!target) return null;
    if (target instanceof HTMLElement) return target;
    if (typeof target === 'string') {
      return container?.querySelector(target) || document.querySelector(target);
    }
    return null;
  }

  function trapFocus(container, options = {}) {
    if (!container) return () => {};

    const {
      initialFocus = null,
      returnFocusTo = null,
      onEscape = null
    } = options;

    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const initialTarget = resolveTarget(initialFocus, container) || getFocusableElements(container)[0] || container;
    const restoreCandidate = resolveTarget(returnFocusTo, document);
    const fallbackTarget = canReceiveFocus(previouslyFocused) ? previouslyFocused : getFocusableElements(document.body)[0] || null;
    const restoreTarget = canReceiveFocus(restoreCandidate) ? restoreCandidate : fallbackTarget;
    const hadTabIndex = container.hasAttribute('tabindex');
    const previousTabIndex = container.getAttribute('tabindex');

    if (!hadTabIndex && container === initialTarget) {
      container.setAttribute('tabindex', '-1');
    }

    window.requestAnimationFrame(() => {
      focusElement(initialTarget);
    });

    function onKeyDown(event) {
      if (event.key === 'Escape') {
        if (typeof onEscape === 'function') {
          onEscape(event);
        }
        return;
      }

      if (event.key !== 'Tab') return;

      const focusable = getFocusableElements(container);
      if (!focusable.length) {
        event.preventDefault();
        focusElement(container);
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement;

      if (event.shiftKey && active === first) {
        event.preventDefault();
        focusElement(last);
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        focusElement(first);
      }
    }

    document.addEventListener('keydown', onKeyDown);

    return function cleanup() {
      document.removeEventListener('keydown', onKeyDown);

      if (!hadTabIndex) {
        container.removeAttribute('tabindex');
      } else if (previousTabIndex !== null) {
        container.setAttribute('tabindex', previousTabIndex);
      }

      window.requestAnimationFrame(() => {
        if (restoreTarget) {
          focusElement(restoreTarget);
        } else {
          focusElement(previouslyFocused);
        }
      });
    };
  }

  window.ExtraA11y = {
    getFocusableElements,
    trapFocus
  };
})();
