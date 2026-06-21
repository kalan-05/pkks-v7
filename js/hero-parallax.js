(() => {
  const scene = document.querySelector('[data-hero-parallax-scene]');
  const sheet = document.querySelector('[data-site-light-overlap]');
  const title = document.querySelector('.hero-section__title h1');
  const subtitle = document.querySelector('.text-hero-section h2');

  if (!scene || !sheet || !title || !subtitle) {
    return;
  }

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
  const easeOutCubic = (value) => 1 - Math.pow(1 - value, 3);

  let ticking = false;

  const prepareElement = (element) => {
    element.style.transformOrigin = 'center center';
    element.style.willChange = 'transform, opacity, filter';
    element.style.backfaceVisibility = 'hidden';
  };

  const resetElement = (element) => {
    element.style.removeProperty('transform');
    element.style.removeProperty('opacity');
    element.style.removeProperty('filter');
    element.style.removeProperty('transform-origin');
    element.style.removeProperty('will-change');
    element.style.removeProperty('backface-visibility');
  };

  const getProgress = () => {
    const sheetTop = sheet.getBoundingClientRect().top;
    const viewportHeight = window.innerHeight || 1;

    /*
      Start early, before the sheet cuts the title.
      End fast, so by scroll-50 the text is already clearly gone into depth.
    */
    const start = viewportHeight * 0.96;
    const end = viewportHeight * 0.54;

    return clamp((start - sheetTop) / Math.max(start - end, 1), 0, 1);
  };

  const applyDepth = () => {
    ticking = false;

    if (reduceMotion.matches) {
      resetElement(title);
      resetElement(subtitle);
      return;
    }

    prepareElement(title);
    prepareElement(subtitle);

    const progress = getProgress();
    const eased = easeOutCubic(progress);

    /*
      Deliberately aggressive.
      Previous subtle versions failed visually.
    */
    const titleScale = 1 - 0.82 * eased;
    const subtitleScale = 1 - 0.86 * eased;

    const titleY = -320 * eased;
    const subtitleY = -250 * eased;

    const titleOpacity = 1 - 0.94 * eased;
    const subtitleOpacity = 1 - 0.94 * eased;

    const titleBlur = 5.5 * eased;
    const subtitleBlur = 5.5 * eased;

    title.style.transform = `translate3d(0, ${titleY.toFixed(2)}px, 0) scale(${titleScale.toFixed(4)})`;
    title.style.opacity = titleOpacity.toFixed(3);
    title.style.filter = `blur(${titleBlur.toFixed(2)}px)`;

    subtitle.style.transform = `translate3d(0, ${subtitleY.toFixed(2)}px, 0) scale(${subtitleScale.toFixed(4)})`;
    subtitle.style.opacity = subtitleOpacity.toFixed(3);
    subtitle.style.filter = `blur(${subtitleBlur.toFixed(2)}px)`;

    scene.style.setProperty('--hero-text-depth-progress', progress.toFixed(4));
  };

  const requestTick = () => {
    if (ticking) {
      return;
    }

    ticking = true;
    window.requestAnimationFrame(applyDepth);
  };

  window.addEventListener('scroll', requestTick, { passive: true });
  window.addEventListener('resize', requestTick);
  reduceMotion.addEventListener?.('change', requestTick);

  applyDepth();
})();
