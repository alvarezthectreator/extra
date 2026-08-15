(function () {
  const body = document.body;
  const overlay = document.querySelector('[data-site-preloader]');
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const minVisibleMs = prefersReduced ? 320 : 1600;

  let done = false;
  let resolveReady;
  const ready = new Promise((resolve) => {
    resolveReady = resolve;
  });

  function finish() {
    if (done) return;
    done = true;

    if (!overlay) {
      body.classList.remove('is-preloading');
      body.removeAttribute('aria-busy');
      document.dispatchEvent(new CustomEvent('extra-store:preloader-done'));
      resolveReady();
      return;
    }

    overlay.classList.add('is-exiting');

    const cleanup = () => {
      overlay.removeEventListener('transitionend', onTransitionEnd);
      overlay.remove();
      body.classList.remove('is-preloading');
      body.removeAttribute('aria-busy');
      document.dispatchEvent(new CustomEvent('extra-store:preloader-done'));
      resolveReady();
    };

    const onTransitionEnd = (event) => {
      if (event.target !== overlay) return;
      cleanup();
    };

    overlay.addEventListener('transitionend', onTransitionEnd, { once: true });
    window.setTimeout(cleanup, prefersReduced ? 120 : 700);
  }

  function whenReady(callback) {
    if (done) {
      callback();
      return;
    }

    ready.then(callback);
  }

  window.ExtraStorePreloader = {
    ready,
    whenReady,
  };

  body.classList.add('is-preloading');
  body.setAttribute('aria-busy', 'true');

  const pageReady = document.readyState === 'complete'
    ? Promise.resolve()
    : new Promise((resolve) => {
        window.addEventListener('load', resolve, { once: true });
      });

  Promise.all([
    pageReady,
    new Promise((resolve) => window.setTimeout(resolve, minVisibleMs)),
  ]).then(() => window.requestAnimationFrame(finish));
})();
