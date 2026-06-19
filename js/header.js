const headerEl = document.getElementById('header');

if (headerEl) {
  let lastKnownScroll = 0;
  let ticking = false;

  const updateHeaderState = () => {
    const isMini = lastKnownScroll > 100;
    headerEl.classList.toggle('header_mini', isMini);
    ticking = false;
  };

  window.addEventListener('scroll', () => {
    lastKnownScroll = window.scrollY;

    if (!ticking) {
      ticking = true;
      // Исправлено: переносим работу в requestAnimationFrame вместо каждого события scroll
      requestAnimationFrame(updateHeaderState);
    }
  }, {passive: true});
}
