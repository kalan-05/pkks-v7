(() => {
  const root = document.documentElement;
  const heroOverlapMinWidth = 1124;
  const heroOverlapTriggerId = 'pkks-hero-overlap-pin';
  const profile = {
    mode: 'gsap-3',
    smooth: 2.4,
    normalizeScroll: true,
    effects: false
  };
  const state = {
    active: false,
    instance: null,
    reason: 'not-started',
    mode: profile.mode,
    smooth: profile.smooth,
    normalizeScroll: profile.normalizeScroll,
    effects: profile.effects,
    env: null,
    error: '',
    heroOverlap: {
      active: false,
      reason: 'not-started',
      triggerFound: false,
      aboutTargetFound: false,
      triggerSelector: '[data-hero-parallax-scene]',
      aboutSelector: '[data-site-light-overlap]',
      pinnedElement: '[data-hero-sticky]',
      pinSpacing: false,
      start: '',
      end: '',
      scrollTriggerId: '',
      scrollTriggerIds: [],
      desktopEligible: false
    }
  };

  window.PKKSScrollSmoother = state;

  const isAdminPath = () => {
    return /^\/admin(?:\/|$)/.test(window.location.pathname);
  };

  const readEnvironment = () => {
    return {
      admin: isAdminPath(),
      width: window.innerWidth || root.clientWidth || 0
    };
  };

  const syncRootState = () => {
    root.dataset.pkksScrollMode = state.mode;
    root.dataset.pkksSmoother = state.active ? 'active' : 'inactive';
    root.dataset.pkksSmootherReason = state.reason;
    root.dataset.pkksSmootherSmooth = String(state.smooth);
    root.dataset.pkksHeroOverlap = state.heroOverlap.active ? 'active' : 'inactive';
    root.dataset.pkksHeroOverlapReason = state.heroOverlap.reason;
  };

  const setInactive = (reason) => {
    state.active = false;
    state.instance = null;
    state.reason = reason;
    state.env = readEnvironment();
    syncRootState();
  };

  const getScrollTriggers = () => {
    if (!window.ScrollTrigger || typeof window.ScrollTrigger.getAll !== 'function') {
      return [];
    }

    return window.ScrollTrigger.getAll();
  };

  const getScrollTriggerIds = () => {
    return getScrollTriggers()
      .map((trigger) => trigger && trigger.vars && trigger.vars.id ? trigger.vars.id : '')
      .filter(Boolean);
  };

  const getHeroOverlapTrigger = () => {
    if (!window.ScrollTrigger || typeof window.ScrollTrigger.getById !== 'function') {
      return null;
    }

    return window.ScrollTrigger.getById(heroOverlapTriggerId) || null;
  };

  const getSmoother = () => {
    if (state.active && state.instance) {
      return state.instance;
    }

    if (window.ScrollSmoother && typeof window.ScrollSmoother.get === 'function') {
      return window.ScrollSmoother.get();
    }

    return null;
  };

  const getScrollTop = () => {
    const smoother = getSmoother();

    if (smoother && typeof smoother.scrollTop === 'function') {
      return Math.max(Math.round(smoother.scrollTop() || 0), 0);
    }

    return Math.max(Math.round(window.scrollY || root.scrollTop || 0), 0);
  };

  const isMenuOpen = () => {
    const header = document.getElementById('header');
    return Boolean((header && header.classList.contains('open')) || document.body.classList.contains('is-menu-open'));
  };

  const describeElement = (element) => {
    if (!element || element.nodeType !== 1) {
      return '';
    }

    if (element.matches('[data-hero-sticky]')) {
      return '[data-hero-sticky]';
    }

    if (element.id) {
      return `#${element.id}`;
    }

    const tagName = element.tagName ? element.tagName.toLowerCase() : 'element';
    const firstClass = element.classList && element.classList.length ? `.${element.classList[0]}` : '';

    return `${tagName}${firstClass}`;
  };

  const setHeroOverlapState = (nextState) => {
    Object.assign(state.heroOverlap, nextState);
    syncRootState();
  };

  const syncHeroOverlapRuntimeState = (fallbackReason = state.heroOverlap.reason) => {
    const trigger = getHeroOverlapTrigger();
    const scrollTriggerIds = getScrollTriggerIds();

    if (!trigger) {
      setHeroOverlapState({
        active: false,
        reason: fallbackReason || 'not-created',
        scrollTriggerId: '',
        scrollTriggerIds
      });
      return null;
    }

    setHeroOverlapState({
      active: trigger.enabled !== false,
      reason: trigger.enabled === false ? 'disabled' : 'active',
      pinnedElement: describeElement(trigger.pin) || state.heroOverlap.pinnedElement,
      pinSpacing: Boolean(trigger.vars && trigger.vars.pinSpacing),
      start: String(Math.round(trigger.start || 0)),
      end: String(Math.round(trigger.end || 0)),
      scrollTriggerId: heroOverlapTriggerId,
      scrollTriggerIds
    });

    return trigger;
  };

  const initHeroOverlapScene = () => {
    const scene = document.querySelector('[data-hero-parallax-scene]');
    const hero = document.querySelector('[data-hero-sticky]');
    const about = document.querySelector('[data-site-light-overlap]') || document.querySelector('[data-about-overlap]');
    const env = readEnvironment();
    // Production gate: desktop pin depends only on viewport width.
    const desktopEligible = env.width >= heroOverlapMinWidth;

    setHeroOverlapState({
      active: false,
      reason: 'checking',
      triggerFound: Boolean(scene && hero),
      aboutTargetFound: Boolean(about),
      pinnedElement: describeElement(hero) || '[data-hero-sticky]',
      scrollTriggerIds: getScrollTriggerIds(),
      desktopEligible
    });

    if (!scene || !hero) {
      setHeroOverlapState({ reason: 'missing-hero-scene' });
      return null;
    }

    if (!about) {
      setHeroOverlapState({ reason: 'missing-about-target' });
      return null;
    }

    if (!desktopEligible) {
      setHeroOverlapState({ reason: 'below-desktop-width' });
      return null;
    }

    if (!window.ScrollTrigger || typeof window.ScrollTrigger.create !== 'function') {
      setHeroOverlapState({ reason: 'missing-scrolltrigger' });
      return null;
    }

    let heroTrigger = null;
    const getHeroOverlapDuration = () => {
      const currentScrollTop = getScrollTop();
      const sceneTop = scene.getBoundingClientRect().top + currentScrollTop;
      const aboutTop = about.getBoundingClientRect().top + currentScrollTop;
      const measuredDuration = aboutTop - sceneTop;
      const viewportHeight = window.innerHeight || root.clientHeight || 1;

      return Math.max(Math.round(measuredDuration), Math.round(viewportHeight * 0.75), 1);
    };

    try {
      heroTrigger = window.ScrollTrigger.create({
        id: heroOverlapTriggerId,
        trigger: scene,
        start: 'top top',
        end: () => `+=${getHeroOverlapDuration()}`,
        pin: hero,
        pinSpacing: false,
        anticipatePin: 1,
        invalidateOnRefresh: true,
        refreshPriority: 20,
        onRefresh: () => syncHeroOverlapRuntimeState('active')
      });
    } catch (error) {
      state.error = error instanceof Error ? error.message : String(error);
      setHeroOverlapState({ reason: 'create-failed' });
      return null;
    }

    syncHeroOverlapRuntimeState('active');

    return heroTrigger;
  };

  const refreshScrollTriggers = () => {
    if (!window.ScrollTrigger || typeof window.ScrollTrigger.refresh !== 'function') {
      return;
    }

    window.ScrollTrigger.refresh();
    syncHeroOverlapRuntimeState();
  };

  const getProductionInactiveReason = (env) => {
    if (env.width < heroOverlapMinWidth) {
      return 'below-desktop-width';
    }

    return '';
  };

  state.env = readEnvironment();
  syncRootState();

  if (isAdminPath()) {
    setInactive('admin');
    return;
  }

  const productionInactiveReason = getProductionInactiveReason(state.env);

  if (productionInactiveReason) {
    setInactive(productionInactiveReason);
    return;
  }

  if (isMenuOpen()) {
    setInactive('menu-open');
    return;
  }

  if (!window.gsap || !window.ScrollTrigger || !window.ScrollSmoother) {
    setInactive('missing-gsap');
    return;
  }

  const wrapper = document.getElementById('smooth-wrapper');
  const content = document.getElementById('smooth-content');

  if (!wrapper) {
    setInactive('missing-wrapper');
    return;
  }

  if (!content) {
    setInactive('missing-content');
    return;
  }

  window.gsap.registerPlugin(window.ScrollTrigger, window.ScrollSmoother);

  let smoother = null;

  try {
    smoother = window.ScrollSmoother.create({
      wrapper: '#smooth-wrapper',
      content: '#smooth-content',
      smooth: profile.smooth,
      effects: profile.effects,
      normalizeScroll: profile.normalizeScroll,
      ignoreMobileResize: true
    });
  } catch (error) {
    state.error = error instanceof Error ? error.message : String(error);
    setInactive('create-failed');
    return;
  }

  state.active = true;
  state.instance = smoother;
  state.reason = 'active';
  state.env = readEnvironment();
  syncRootState();

  const heroOverlapTrigger = initHeroOverlapScene();

  if (heroOverlapTrigger) {
    refreshScrollTriggers();
    window.requestAnimationFrame(refreshScrollTriggers);
    window.addEventListener('load', refreshScrollTriggers, { once: true });
  }

  const getAnchorScrollY = (target) => {
    const header = document.getElementById('header');
    const headerOffset = header ? header.offsetHeight + 16 : 0;

    return Math.max((smoother.scrollTop() || 0) + target.getBoundingClientRect().top - headerOffset, 0);
  };

  document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;

    if (!link || !link.hash || link.hash === '#') {
      return;
    }

    const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));

    if (!target) {
      return;
    }

    event.preventDefault();
    smoother.scrollTo(getAnchorScrollY(target), true);
    window.history.pushState(null, '', link.hash);
  });

  if (window.location.hash) {
    const initialTarget = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));

    if (initialTarget) {
      window.requestAnimationFrame(() => {
        smoother.scrollTo(getAnchorScrollY(initialTarget), false);
      });
    }
  }

  document.dispatchEvent(new CustomEvent('pkks:smoother-ready', {
    detail: {
      mode: state.mode,
      smooth: profile.smooth,
      normalizeScroll: profile.normalizeScroll,
      effects: profile.effects,
      heroOverlapActive: Boolean(syncHeroOverlapRuntimeState() && state.heroOverlap.active)
    }
  }));
})();
