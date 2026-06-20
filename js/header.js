(() => {
  const header = document.getElementById('header');

  if (!header) {
    return;
  }

  const HIDE_AFTER = 120;
  const MIN_DELTA = 8;

  let lastScrollY = window.scrollY || 0;
  let ticking = false;

  const showHeader = () => {
    header.classList.remove('is-header-hidden');
  };

  const hideHeader = () => {
    header.classList.add('is-header-hidden');
  };

  const isMenuOpen = () => {
    return header.classList.contains('open');
  };

  const updateHeader = () => {
    const currentScrollY = window.scrollY || 0;
    const delta = currentScrollY - lastScrollY;

    if (currentScrollY <= HIDE_AFTER || isMenuOpen()) {
      showHeader();
    } else if (delta > MIN_DELTA) {
      hideHeader();
    } else if (delta < -MIN_DELTA) {
      showHeader();
    }

    lastScrollY = Math.max(currentScrollY, 0);
    ticking = false;
  };

  window.addEventListener(
    'scroll',
    () => {
      if (!ticking) {
        window.requestAnimationFrame(updateHeader);
        ticking = true;
      }
    },
    { passive: true }
  );

  header.addEventListener('focusin', showHeader);

  showHeader();
})();
