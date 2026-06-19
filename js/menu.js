document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.header');
  const burger = document.getElementById('burger');
  const menu = document.getElementById('menu');

  if (!header || !burger || !menu) {
    return;
  }

  const navLinks = menu.querySelectorAll('.menu__link');

  const closeMenu = () => {
    if (!header.classList.contains('open')) {
      return;
    }
    header.classList.remove('open');
    burger.setAttribute('aria-expanded', 'false');
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

  burger.setAttribute('aria-expanded', 'false');

  burger.addEventListener('click', () => {
    const isOpen = header.classList.toggle('open');
    burger.setAttribute('aria-expanded', String(isOpen));
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
      if (focusedLink) {
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
      burger.focus();
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
});
