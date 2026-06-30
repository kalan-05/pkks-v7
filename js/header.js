(() => {
  const header = document.getElementById('header');
  const root = document.documentElement;
  const body = document.body;

  if (!header) {
    return;
  }

  const SHOW_AT_TOP = 80;
  const HIDE_AFTER = 220;
  const MIN_DELTA = 20;
  const HIDE_DISTANCE = 120;
  const SHOW_DISTANCE = 72;
  const STATE_CHANGE_COOLDOWN = 320;

  let lastScrollY = 0;
  let direction = 0;
  let directionStartY = 0;
  let lastStateChangeAt = 0;
  let ticking = false;

  const getSmoother = () => {
    const bridge = window.PKKSScrollSmoother;

    if (bridge && bridge.active && bridge.instance) {
      return bridge.instance;
    }

    if (window.ScrollSmoother && typeof window.ScrollSmoother.get === 'function') {
      return window.ScrollSmoother.get();
    }

    return null;
  };

  const getScrollY = () => {
    const smoother = getSmoother();

    if (smoother && typeof smoother.scrollTop === 'function') {
      return Math.max(smoother.scrollTop() || 0, 0);
    }

    return Math.max(window.scrollY || root.scrollTop || 0, 0);
  };

  const isMenuOpen = () => {
    return header.classList.contains('open') || body.classList.contains('is-menu-open');
  };

  const emitState = (state, scrollY) => {
    document.dispatchEvent(new CustomEvent('pkks:header-motion', {
      detail: {
        state,
        scrollY,
        timestamp: Math.round(window.performance.now())
      }
    }));
  };

  const showHeader = (scrollY = getScrollY()) => {
    if (!header.classList.contains('is-header-hidden')) {
      return;
    }

    header.classList.remove('is-header-hidden');
    lastStateChangeAt = window.performance.now();
    emitState('shown', scrollY);
  };

  const hideHeader = (scrollY = getScrollY()) => {
    if (header.classList.contains('is-header-hidden')) {
      return;
    }

    header.classList.add('is-header-hidden');
    lastStateChangeAt = window.performance.now();
    emitState('hidden', scrollY);
  };

  const resetDirection = (scrollY) => {
    direction = 0;
    directionStartY = scrollY;
  };

  const updateHeader = () => {
    const currentScrollY = getScrollY();
    const delta = currentScrollY - lastScrollY;
    const absoluteDelta = Math.abs(delta);

    if (currentScrollY <= SHOW_AT_TOP || isMenuOpen()) {
      showHeader(currentScrollY);
      resetDirection(currentScrollY);
      lastScrollY = currentScrollY;
      ticking = false;
      return;
    }

    if (absoluteDelta < MIN_DELTA) {
      ticking = false;
      return;
    }

    const nextDirection = delta > 0 ? 1 : -1;
    if (nextDirection !== direction) {
      direction = nextDirection;
      directionStartY = lastScrollY;
    }

    const directionDistance = Math.abs(currentScrollY - directionStartY);
    const now = window.performance.now();
    const canChangeState = now - lastStateChangeAt >= STATE_CHANGE_COOLDOWN;

    if (nextDirection > 0 && currentScrollY >= HIDE_AFTER && directionDistance >= HIDE_DISTANCE && canChangeState) {
      hideHeader(currentScrollY);
      directionStartY = currentScrollY;
    } else if (nextDirection < 0 && directionDistance >= SHOW_DISTANCE && canChangeState) {
      showHeader(currentScrollY);
      directionStartY = currentScrollY;
    }

    lastScrollY = currentScrollY;
    ticking = false;
  };

  const requestUpdate = () => {
    if (ticking) {
      return;
    }

    ticking = true;
    window.requestAnimationFrame(updateHeader);
  };

  lastScrollY = getScrollY();
  directionStartY = lastScrollY;

  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate, { passive: true });
  document.addEventListener('pkks:smoother-ready', requestUpdate);

  header.addEventListener('focusin', () => showHeader());

  if (lastScrollY <= SHOW_AT_TOP || isMenuOpen()) {
    showHeader(lastScrollY);
  }
})();
