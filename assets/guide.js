/* Browsers with native grouped details and animated details-content need no click handler. */
(() => {
  const nativeMotion = CSS.supports('selector(details::details-content)') &&
    CSS.supports('transition-behavior', 'allow-discrete');
  if (nativeMotion && 'name' in document.createElement('details')) return;

  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');
  for (const guide of document.querySelectorAll('.ssbg')) {
    const items = Array.from(guide.querySelectorAll('.ssbg-item'));
    let requested = items.find(item => item.open);
    let running;

    // Finish closing the current disclosure before opening the requested one.
    // On a second click, measure its visible height before cancelling the animation.
    function transition() {
      const current = items.find(item => item.open);
      const item = current || requested;
      if (!item) return;
      const from = item.getBoundingClientRect().height;
      if (running) {
        running.cancel();
        running = null;
      }
      const opening = item === requested;
      item.open = opening;
      const to = item.getBoundingClientRect().height;
      const finish = () => {
        item.open = opening;
        running = null;
        if (!opening && requested) transition();
      };
      if (reduceMotion.matches || !item.animate || Math.abs(to - from) < 1) {
        finish();
        return;
      }
      item.open = true;
      const animation = item.animate([{ height: from + 'px' }, { height: to + 'px' }], {
        duration: opening ? 310 : 245,
        easing: 'cubic-bezier(.32, 0, .18, 1)'
      });
      running = animation;
      animation.onfinish = () => {
        if (running === animation) finish();
      };
    }

    for (const item of items) {
      item.querySelector('summary').addEventListener('click', event => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        requested = requested === item ? undefined : item;
        transition();
      });
    }
    function settle() {
      if (!running) return;
      running.cancel();
      running = null;
      for (const item of items) item.open = item === requested;
    }
    window.addEventListener('resize', settle, { passive: true });
    if (reduceMotion.addEventListener) reduceMotion.addEventListener('change', settle);
  }
})();
