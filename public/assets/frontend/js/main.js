// Sprylo — light interaction (no framework)
(function () {
  // Mobile drawer
  const drawer = document.getElementById('drawer');
  const toggle = document.querySelector('.nav-toggle');
  const close  = document.querySelector('.drawer-close');

  function setDrawer(open) {
    if (!drawer || !toggle) return;
    drawer.classList.toggle('is-open', open);
    drawer.setAttribute('aria-hidden', String(!open));
    toggle.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
  }
  toggle && toggle.addEventListener('click', () => setDrawer(!drawer.classList.contains('is-open')));
  close  && close.addEventListener('click', () => setDrawer(false));
  drawer && drawer.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setDrawer(false)));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.querySelector('[data-language-menu]:not([hidden])')) return;
    if (e.key === 'Escape' && drawer && drawer.classList.contains('is-open')) setDrawer(false);
  });

  // Shared language switcher: desktop utility bar and mobile drawer.
  const languageSwitchers = Array.from(document.querySelectorAll('[data-language-switcher]'));
  const languageTrigger = (switcher) => switcher.querySelector('[data-language-trigger]');
  const languageMenu = (switcher) => switcher.querySelector('[data-language-menu]');
  const languageItems = (switcher) => Array.from(switcher.querySelectorAll('[role="menuitem"]:not([disabled])'));
  const setLanguageMenu = (switcher, open, focusMenu = false) => {
    const trigger = languageTrigger(switcher);
    const menu = languageMenu(switcher);
    if (!trigger || !menu) return;
    if (open) {
      languageSwitchers.forEach((other) => {
        if (other === switcher) return;
        const otherTrigger = languageTrigger(other);
        const otherMenu = languageMenu(other);
        if (otherTrigger && otherMenu) {
          otherTrigger.setAttribute('aria-expanded', 'false');
          otherMenu.hidden = true;
        }
      });
    }
    trigger.setAttribute('aria-expanded', String(open));
    menu.hidden = !open;
    if (open && focusMenu) languageItems(switcher)[0]?.focus();
  };

  languageSwitchers.forEach((switcher) => {
    const trigger = languageTrigger(switcher);
    const menu = languageMenu(switcher);
    if (!trigger || !menu) return;
    trigger.addEventListener('click', () => setLanguageMenu(switcher, menu.hidden));
    trigger.addEventListener('keydown', (event) => {
      if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
      event.preventDefault();
      setLanguageMenu(switcher, true, true);
      if (event.key === 'ArrowUp') languageItems(switcher).at(-1)?.focus();
    });
    menu.addEventListener('keydown', (event) => {
      const items = languageItems(switcher);
      const index = items.indexOf(document.activeElement);
      if (event.key === 'Escape') {
        event.preventDefault();
        setLanguageMenu(switcher, false);
        trigger.focus();
      } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) && items.length) {
        event.preventDefault();
        const nextIndex = event.key === 'Home' ? 0
          : event.key === 'End' ? items.length - 1
            : event.key === 'ArrowDown' ? (index + 1 + items.length) % items.length
              : (index - 1 + items.length) % items.length;
        items[nextIndex].focus();
      }
    });
  });

  document.addEventListener('click', (event) => {
    languageSwitchers.forEach((switcher) => {
      if (!switcher.contains(event.target)) setLanguageMenu(switcher, false);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const openSwitcher = languageSwitchers.find((switcher) => !languageMenu(switcher)?.hidden);
    if (!openSwitcher) return;
    event.preventDefault();
    setLanguageMenu(openSwitcher, false);
    languageTrigger(openSwitcher)?.focus();
  });

  // Recursive desktop dropdowns: click/touch, viewport direction and keyboard Escape.
  const directToggle = (item) => item?.querySelector(':scope > .main-nav__entry > [data-menu-toggle]');
  const closeMenuItem = (item) => {
    item.querySelectorAll('.main-nav__item.is-open').forEach((child) => {
      child.classList.remove('is-open', 'opens-left');
      directToggle(child)?.setAttribute('aria-expanded', 'false');
    });
    item.classList.remove('is-open', 'opens-left');
    directToggle(item)?.setAttribute('aria-expanded', 'false');
  };
  const positionSubmenu = (item) => {
    const submenu = item?.querySelector(':scope > [data-menu-submenu]');
    if (!submenu) return;
    item.classList.remove('opens-left');
    requestAnimationFrame(() => {
      if (submenu.getBoundingClientRect().right > window.innerWidth - 12) item.classList.add('opens-left');
    });
  };

  document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const item = button.closest('[data-menu-item]');
      const open = !item.classList.contains('is-open');
      item.parentElement.querySelectorAll(':scope > .main-nav__item.is-open').forEach((sibling) => {
        if (sibling !== item) closeMenuItem(sibling);
      });
      item.classList.remove('is-escape-closed');
      item.classList.toggle('is-open', open);
      button.setAttribute('aria-expanded', String(open));
      if (open) positionSubmenu(item); else closeMenuItem(item);
    });
  });

  document.querySelectorAll('[data-menu-item]').forEach((item) => {
    item.addEventListener('pointerenter', () => positionSubmenu(item));
    item.addEventListener('focusin', () => {
      item.classList.remove('is-escape-closed');
      positionSubmenu(item);
    });
    item.addEventListener('focusout', (event) => {
      if (!item.contains(event.relatedTarget)) item.classList.remove('is-escape-closed');
    });
  });

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-menu-item]')) return;
    document.querySelectorAll('.main-nav__item.is-open').forEach(closeMenuItem);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const activeSubmenu = document.activeElement?.closest('[data-menu-submenu]');
    const openItems = Array.from(document.querySelectorAll('.main-nav__item.is-open'));
    const item = activeSubmenu?.closest('[data-menu-item]') || openItems.at(-1);
    if (!item) return;
    event.preventDefault();
    closeMenuItem(item);
    item.classList.add('is-escape-closed');
    directToggle(item)?.focus();
  });

  // Category tabs (just visual switch)
  document.querySelectorAll('.tabs').forEach((tablist) => {
    tablist.querySelectorAll('.tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        tablist.querySelectorAll('.tab').forEach((t) => {
          t.classList.remove('is-active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
      });
    });
  });

  // Product gallery thumbnails
  const thumbs = document.querySelectorAll('.gallery-thumbs button');
  const mainImg = document.querySelector('.gallery-main img');
  thumbs.forEach((t) => {
    t.addEventListener('click', () => {
      thumbs.forEach((x) => x.classList.remove('is-active'));
      t.classList.add('is-active');
      const src = t.querySelector('img')?.src;
      if (src && mainImg) mainImg.src = src.replace(/w=\d+/, 'w=900');
    });
  });

  // Qty +/- buttons
  document.querySelectorAll('.qty').forEach((q) => {
    const input = q.querySelector('input');
    const minus = q.querySelector('[data-act="-"]');
    const plus  = q.querySelector('[data-act="+"]');
    minus && minus.addEventListener('click', () => { input.value = Math.max(1, parseInt(input.value || 1) - 1); });
    plus  && plus.addEventListener('click', () => { input.value = parseInt(input.value || 1) + 1; });
  });

  // Color swatches & option pills (visual switch)
  document.querySelectorAll('.color-swatches, .option-pills').forEach((group) => {
    group.querySelectorAll('button').forEach((b) => {
      b.addEventListener('click', () => {
        group.querySelectorAll('button').forEach((x) => x.classList.remove('is-active'));
        b.classList.add('is-active');
      });
    });
  });
})();
