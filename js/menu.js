document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.header');
  const burger = document.getElementById('burger');
  const menu = document.getElementById('menu');

  if (!header || !burger || !menu) {
    return;
  }

  const navLinks = menu.querySelectorAll('.menu__link');
  const tabletMenuMedia = window.matchMedia('(max-width: 1123px)');
  const body = document.body;
  const root = document.documentElement;
  let lockedScrollY = 0;
  let bodyLocked = false;

  const setMenuState = (isOpen) => {
    burger.setAttribute('aria-expanded', String(isOpen));
    burger.setAttribute('aria-label', isOpen ? 'Закрыть меню' : 'Открыть меню');
    menu.setAttribute('aria-hidden', String(!isOpen));
  };

  const lockBodyScroll = () => {
    if (bodyLocked) {
      return;
    }

    lockedScrollY = window.scrollY || root.scrollTop || 0;
    const scrollbarWidth = Math.max(0, window.innerWidth - root.clientWidth);

    body.classList.add('is-menu-open');
    body.style.position = 'fixed';
    body.style.top = `-${lockedScrollY}px`;
    body.style.left = '0';
    body.style.right = '0';
    body.style.width = '100%';
    body.style.overflow = 'hidden';
    if (scrollbarWidth > 0) {
      body.style.paddingRight = `${scrollbarWidth}px`;
    }

    bodyLocked = true;
  };

  const unlockBodyScroll = () => {
    if (!bodyLocked) {
      return;
    }

    const restoreScrollY = lockedScrollY;
    body.classList.remove('is-menu-open');
    body.style.position = '';
    body.style.top = '';
    body.style.left = '';
    body.style.right = '';
    body.style.width = '';
    body.style.overflow = '';
    body.style.paddingRight = '';
    bodyLocked = false;

    window.scrollTo(0, restoreScrollY);
  };

  const openMenu = () => {
    if (header.classList.contains('open')) {
      return;
    }

    header.classList.remove('is-header-hidden');
    header.classList.add('open');
    setMenuState(true);
    lockBodyScroll();
    burger.focus({ preventScroll: true });
  };

  const closeMenu = ({ restoreFocus = true } = {}) => {
    if (!header.classList.contains('open')) {
      return;
    }
    header.classList.remove('open');
    setMenuState(false);
    unlockBodyScroll();

    if (restoreFocus) {
      burger.focus({ preventScroll: true });
    }
  };

  const setCurrentLink = (current) => {
    navLinks.forEach((link) => {
      link.classList.remove('active');
      link.removeAttribute('aria-current');
    });
    if (current) {
      current.classList.add('active');
      current.setAttribute('aria-current', 'page');
    }
  };

  // Инициализируем aria-current на дефолтной активной ссылке (если задано классом)
  const defaultActive = menu.querySelector('.menu__link.active');
  if (defaultActive) {
    defaultActive.setAttribute('aria-current', 'page');
  }

  setMenuState(false);

  burger.addEventListener('click', () => {
    if (header.classList.contains('open')) {
      closeMenu();
      return;
    }

    openMenu();
  });

  // Закрытие при отмене жеста (некоторые тач-устройства)
  burger.addEventListener('pointercancel', () => {
    closeMenu();
  });

  // Клик вне области header закрывает меню
  document.addEventListener('click', (event) => {
    if (!header.classList.contains('open')) {
      return;
    }
    if (header.contains(event.target)) {
      return;
    }
    closeMenu();
  });

  // Клик по пункту меню — закрыть меню и отметить активный пункт
  menu.addEventListener('click', (event) => {
    const link = event.target instanceof HTMLElement ? event.target.closest('.menu__link') : null;
    if (link) {
      if (link.classList.contains('menu__phone-link')) {
        closeMenu({ restoreFocus: false });
        return;
      }

      setCurrentLink(link);
      closeMenu();
    }
  });

  // Выбор пункта клавишей Enter (когда фокус на ссылке)
  menu.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      const focusedLink = document.activeElement instanceof HTMLElement
          ? document.activeElement.closest('.menu__link')
          : null;
      if (focusedLink && !focusedLink.classList.contains('menu__phone-link')) {
        setCurrentLink(focusedLink);
      }
    }
  });

  // Потеря фокуса всей области меню — закрываем (если фокус ушёл за пределы header)
  menu.addEventListener('focusout', (event) => {
    if (!header.classList.contains('open')) {
      return;
    }
    const related = event.relatedTarget;
    if (related instanceof Node && header.contains(related)) {
      return;
    }
    closeMenu();
  });

  // Закрытие по Escape
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });

  // Синхронизация активного пункта при изменении хэша (переход по якорям)
  window.addEventListener('hashchange', () => {
    const activeByHash = typeof window.location.hash === 'string'
        ? menu.querySelector(`.menu__link[href="${window.location.hash}"]`)
        : null;
    if (activeByHash) {
      setCurrentLink(activeByHash);
    }
  });

  const closeOnDesktop = () => {
    if (!tabletMenuMedia.matches) {
      closeMenu({ restoreFocus: false });
    }
  };

  if (typeof tabletMenuMedia.addEventListener === 'function') {
    tabletMenuMedia.addEventListener('change', closeOnDesktop);
  } else if (typeof tabletMenuMedia.addListener === 'function') {
    tabletMenuMedia.addListener(closeOnDesktop);
  }
});
