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

  // Homepage trending tabs: AJAX enhancement over Shop fallback links.
  document.querySelectorAll('[data-trending-tabs]').forEach((root) => {
    const tablist = root.querySelector('[role="tablist"]');
    const panel = root.querySelector('[role="tabpanel"]');
    const results = root.querySelector('[data-trending-results]');
    const loading = root.querySelector('[data-trending-loading]');
    const error = root.querySelector('[data-trending-error]');
    const tabs = Array.from(tablist?.querySelectorAll('[role="tab"]') || []);
    if (!tablist || !panel || !results || tabs.length === 0) return;

    let requestController = null;
    const responseCache = new Map();

    const activate = (tab) => {
      tabs.forEach((item) => {
        const active = item === tab;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', String(active));
        item.tabIndex = active ? 0 : -1;
      });
      panel.setAttribute('aria-labelledby', tab.id);
    };

    const setLoading = (active) => {
      panel.classList.toggle('is-loading', active);
      panel.setAttribute('aria-busy', String(active));
      if (loading) loading.hidden = !active;
      tabs.forEach((tab) => tab.setAttribute('aria-disabled', String(active)));
    };

    const loadTab = async (tab) => {
      const url = tab.dataset.trendingUrl;
      if (!url || tab.getAttribute('aria-selected') === 'true') return;

      requestController?.abort();
      const controller = new AbortController();
      requestController = controller;
      const startedAt = performance.now();
      if (error) error.hidden = true;
      setLoading(true);

      try {
        let payload = responseCache.get(url);
        if (!payload) {
          const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
          });
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          payload = await response.json();
          responseCache.set(url, payload);
        }

        const remaining = Math.max(0, 220 - (performance.now() - startedAt));
        if (remaining) await new Promise((resolve) => window.setTimeout(resolve, remaining));
        if (controller.signal.aborted || requestController !== controller) return;

        results.innerHTML = payload.html;
        activate(tab);
      } catch (exception) {
        if (exception.name !== 'AbortError' && requestController === controller && error) {
          error.hidden = false;
        }
      } finally {
        if (requestController === controller) {
          requestController = null;
          setLoading(false);
        }
      }
    };

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', (event) => {
        event.preventDefault();
        loadTab(tab);
      });
      tab.addEventListener('keydown', (event) => {
        let targetIndex = null;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') targetIndex = (index + 1) % tabs.length;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') targetIndex = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') targetIndex = 0;
        if (event.key === 'End') targetIndex = tabs.length - 1;
        if (event.key === ' ') {
          event.preventDefault();
          loadTab(tab);
          return;
        }
        if (targetIndex === null) return;
        event.preventDefault();
        tabs[targetIndex].focus();
        loadTab(tabs[targetIndex]);
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

  // Shop catalog filters: AJAX enhancement with a complete GET fallback.
  const catalogRoot = document.querySelector('[data-catalog-shop]');
  if (catalogRoot) {
    const filterForm = catalogRoot.querySelector('#catalog-filters');
    const results = catalogRoot.querySelector('#catalog-results');
    const content = catalogRoot.querySelector('.catalog-content');
    const loading = catalogRoot.querySelector('[data-catalog-loading]');
    const error = catalogRoot.querySelector('[data-catalog-error]');
    const reset = catalogRoot.querySelector('[data-catalog-reset]');
    const range = catalogRoot.querySelector('[data-price-filter]');
    const rangeMin = range?.querySelector('[data-range-min]');
    const rangeMax = range?.querySelector('[data-range-max]');
    const rangeTrack = range?.querySelector('[data-range-track]');
    const minOutput = range?.querySelector('[data-min-output]');
    const maxOutput = range?.querySelector('[data-max-output]');
    let requestController = null;
    let searchTimer = null;

    const priceLabel = (value) => `${new Intl.NumberFormat(document.documentElement.lang === 'en' ? 'en-US' : 'vi-VN').format(Number(value))} ₫`;
    const updateRange = (changed) => {
      if (!rangeMin || !rangeMax || !rangeTrack) return;
      let minimum = Number(rangeMin.value);
      let maximum = Number(rangeMax.value);
      if (minimum > maximum) {
        if (changed === rangeMin) maximum = minimum;
        else minimum = maximum;
        rangeMin.value = String(minimum);
        rangeMax.value = String(maximum);
      }
      const lower = Number(rangeMin.min);
      const upper = Number(rangeMin.max);
      const span = Math.max(1, upper - lower);
      rangeTrack.style.setProperty('--range-start', `${((minimum - lower) / span) * 100}%`);
      rangeTrack.style.setProperty('--range-end', `${((maximum - lower) / span) * 100}%`);
      if (minOutput) minOutput.textContent = priceLabel(minimum);
      if (maxOutput) maxOutput.textContent = priceLabel(maximum);
    };

    const setLoading = (active) => {
      content?.classList.toggle('is-loading', active);
      results?.setAttribute('aria-busy', String(active));
      if (loading) {
        loading.hidden = !active;
        loading.setAttribute('aria-hidden', String(!active));
      }
      if (active && error) error.hidden = true;
    };

    const syncControls = () => {
      const sort = results?.querySelector('[data-catalog-sort]');
      const perPage = results?.querySelector('[data-catalog-per-page]');
      const hiddenSort = filterForm?.querySelector('[data-filter-sort]');
      const hiddenPerPage = filterForm?.querySelector('[data-filter-per-page]');
      if (sort && hiddenSort) hiddenSort.value = sort.value;
      if (perPage && hiddenPerPage) hiddenPerPage.value = perPage.value;
    };

    const formUrl = () => {
      syncControls();
      const params = new URLSearchParams(new FormData(filterForm));
      params.delete('page');
      return `${catalogRoot.dataset.shopUrl}?${params.toString()}`;
    };

    const loadCatalog = async (url, pushState = true) => {
      requestController?.abort();
      const controller = new AbortController();
      requestController = controller;
      const startedAt = performance.now();
      setLoading(true);
      try {
        const response = await fetch(url, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          signal: controller.signal,
        });
        if (!response.ok) throw new Error(`Catalog request failed: ${response.status}`);
        const payload = await response.json();
        const minimumLoadingTime = 260;
        const remaining = minimumLoadingTime - (performance.now() - startedAt);
        if (remaining > 0) await new Promise((resolve) => window.setTimeout(resolve, remaining));
        if (controller.signal.aborted) return;
        results.innerHTML = payload.html;
        syncControls();
        if (pushState) window.history.pushState({ catalog: true }, '', url);
      } catch (requestError) {
        if (requestError.name !== 'AbortError' && error) error.hidden = false;
      } finally {
        if (requestController === controller) setLoading(false);
      }
    };

    const syncFormFromUrl = (url) => {
      const params = new URL(url, window.location.origin).searchParams;
      filterForm.querySelector('[name="q"]').value = params.get('q') || '';
      filterForm.querySelectorAll('input[name="category[]"], input[name="brand[]"]').forEach((input) => {
        input.checked = params.getAll(input.name).includes(input.value);
      });
      const hiddenSort = filterForm.querySelector('[data-filter-sort]');
      const hiddenPerPage = filterForm.querySelector('[data-filter-per-page]');
      if (hiddenSort) hiddenSort.value = params.get('sort') || 'newest';
      if (hiddenPerPage) hiddenPerPage.value = params.get('per_page') || '12';
      if (rangeMin) rangeMin.value = params.get('min_price') || rangeMin.min;
      if (rangeMax) rangeMax.value = params.get('max_price') || rangeMax.max;
      updateRange();
    };

    filterForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      loadCatalog(formUrl());
    });
    filterForm?.addEventListener('change', (event) => {
      if (event.target.matches('input[type="checkbox"], input[type="range"]')) loadCatalog(formUrl());
    });
    filterForm?.querySelector('[name="q"]')?.addEventListener('input', () => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => loadCatalog(formUrl()), 450);
    });
    [rangeMin, rangeMax].forEach((input) => input?.addEventListener('input', () => updateRange(input)));

    results?.addEventListener('change', (event) => {
      if (!event.target.matches('[data-catalog-sort], [data-catalog-per-page]')) return;
      syncControls();
      loadCatalog(formUrl());
    });
    results?.addEventListener('click', (event) => {
      const link = event.target.closest('.pagination a');
      if (!link) return;
      event.preventDefault();
      loadCatalog(link.href);
    });
    reset?.addEventListener('click', (event) => {
      event.preventDefault();
      syncFormFromUrl(catalogRoot.dataset.shopUrl);
      loadCatalog(catalogRoot.dataset.shopUrl);
    });
    window.addEventListener('popstate', () => {
      syncFormFromUrl(window.location.href);
      loadCatalog(window.location.href, false);
    });
    updateRange();
  }

  // Wishlist: server-confirmed AJAX enhancement. Native forms remain the no-JS fallback.
  const wishlistFeedback = document.querySelector('[data-wishlist-feedback]');
  let wishlistFeedbackTimer = null;
  let wishlistChannel = null;

  const announceWishlist = (message, isError = false) => {
    if (!wishlistFeedback || !message) return;
    window.clearTimeout(wishlistFeedbackTimer);
    wishlistFeedback.textContent = message;
    wishlistFeedback.hidden = false;
    wishlistFeedback.classList.toggle('is-error', isError);
    wishlistFeedback.classList.add('is-visible');
    wishlistFeedbackTimer = window.setTimeout(() => {
      wishlistFeedback.classList.remove('is-visible');
      wishlistFeedback.hidden = true;
    }, 4200);
  };

  const updateWishlistBadge = (count) => {
    const link = document.querySelector('[data-wishlist-link]');
    if (!link) return;
    let badge = link.querySelector('[data-wishlist-count]');
    const safeCount = Math.max(0, Number(count) || 0);
    if (safeCount === 0) {
      badge?.remove();
      return;
    }
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'count';
      badge.dataset.wishlistCount = '';
      link.appendChild(badge);
    }
    badge.textContent = String(safeCount);
  };

  const updateWishlistControls = (productId, isWishlisted) => {
    document.querySelectorAll('[data-wishlist-form]').forEach((form) => {
      if (form.dataset.productId !== String(productId)) return;
      const button = form.querySelector('.wishlist-toggle');
      const icon = button?.querySelector('svg');
      const label = form.querySelector('[data-wishlist-label]');
      const accessibleLabel = isWishlisted ? form.dataset.removeLabel : form.dataset.addLabel;
      const visibleLabel = isWishlisted ? form.dataset.removeText : form.dataset.addText;
      let method = form.querySelector('input[name="_method"]');

      form.action = isWishlisted ? form.dataset.destroyUrl : form.dataset.storeUrl;
      if (isWishlisted && !method) {
        method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        form.appendChild(method);
      }
      if (method) {
        if (isWishlisted) method.value = 'DELETE';
        else method.remove();
      }
      button?.classList.toggle('is-active', isWishlisted);
      button?.setAttribute('aria-pressed', String(isWishlisted));
      button?.setAttribute('aria-label', accessibleLabel || '');
      button?.setAttribute('title', accessibleLabel || '');
      icon?.setAttribute('fill', isWishlisted ? 'currentColor' : 'none');
      if (label) label.textContent = label.classList.contains('sr-only') ? accessibleLabel : visibleLabel;
    });
  };

  const applyWishlistState = (payload) => {
    if (!payload || payload.product_id === undefined) return;
    updateWishlistControls(payload.product_id, Boolean(payload.is_wishlisted));
    updateWishlistBadge(payload.wishlist_count ?? payload.count);
  };

  if ('BroadcastChannel' in window) {
    wishlistChannel = new BroadcastChannel('storefront-wishlist');
    wishlistChannel.addEventListener('message', (event) => applyWishlistState(event.data));
  }

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-wishlist-form]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.pending === 'true') return;

    const button = form.querySelector('.wishlist-toggle');
    form.dataset.pending = 'true';
    form.classList.add('is-pending');
    form.setAttribute('aria-busy', 'true');
    if (button) button.disabled = true;

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const payload = await response.json().catch(() => ({}));
      if (response.status === 401) {
        window.location.assign(form.dataset.loginUrl);
        return;
      }
      if (!response.ok) {
        const firstError = Object.values(payload.errors || {}).flat()[0];
        throw new Error(firstError || payload.message || wishlistFeedback?.dataset.errorMessage);
      }

      const confirmedState = {
        product_id: form.dataset.productId,
        is_wishlisted: payload.is_wishlisted,
        wishlist_count: payload.wishlist_count ?? payload.count,
      };
      applyWishlistState(confirmedState);
      wishlistChannel?.postMessage(confirmedState);
      announceWishlist(payload.message);
    } catch (error) {
      announceWishlist(error.message || wishlistFeedback?.dataset.errorMessage, true);
    } finally {
      delete form.dataset.pending;
      form.classList.remove('is-pending');
      form.setAttribute('aria-busy', 'false');
      if (button) button.disabled = false;
    }
  });

  // Cart add: submit in the background and only apply state confirmed by the server.
  const cartFeedback = document.querySelector('[data-cart-feedback]');
  const cartRegion = document.querySelector('[data-cart-region]');
  let cartFeedbackTimer = null;
  let cartChannel = null;
  let cartRequestPending = false;
  let cartRequestVersion = 0;
  let cartAppliedVersion = 0;

  const announceCart = (message, isError = false) => {
    if (!cartFeedback || !message) return;
    window.clearTimeout(cartFeedbackTimer);
    cartFeedback.textContent = message;
    cartFeedback.hidden = false;
    cartFeedback.classList.toggle('is-error', isError);
    cartFeedback.classList.add('is-visible');
    cartFeedbackTimer = window.setTimeout(() => {
      cartFeedback.classList.remove('is-visible');
      cartFeedback.hidden = true;
    }, 4200);
  };

  const updateCartBadge = (count) => {
    const link = document.querySelector('[data-cart-link]');
    if (!link) return;
    let badge = link.querySelector('[data-cart-count]');
    const safeCount = Math.max(0, Number(count) || 0);
    if (safeCount === 0) {
      badge?.remove();
      return;
    }
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'count';
      badge.dataset.cartCount = '';
      link.appendChild(badge);
    }
    badge.textContent = String(safeCount);
  };

  const applyCartState = (payload, requestVersion = cartRequestVersion, focusProductId = null) => {
    if (!payload || payload.cart_count === undefined) return;
    if (requestVersion < cartAppliedVersion) return;
    cartAppliedVersion = requestVersion;
    updateCartBadge(payload.cart_count);
    if (cartRegion && typeof payload.cart_html === 'string') {
      cartRegion.innerHTML = payload.cart_html;
      const focusTarget = focusProductId
        ? cartRegion.querySelector(`[data-product-id="${focusProductId}"] input[name="quantity"]`)
        : cartRegion.querySelector('[data-cart-focus]');
      focusTarget?.focus({ preventScroll: true });
    }
  };

  const refreshCartRegion = async () => {
    if (!cartRegion?.dataset.cartUrl || cartRequestPending) return;
    const response = await fetch(cartRegion.dataset.cartUrl, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) return;
    const payload = await response.json();
    applyCartState(payload, ++cartRequestVersion);
  };

  if ('BroadcastChannel' in window) {
    cartChannel = new BroadcastChannel('storefront-cart');
    cartChannel.addEventListener('message', (event) => {
      applyCartState(event.data, ++cartRequestVersion);
      refreshCartRegion().catch(() => {});
    });
  }

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-cart-add-form]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.pending === 'true' || cartRequestPending) return;

    const button = form.querySelector('[data-cart-submit]');
    const originalMarkup = button?.innerHTML;
    const requestVersion = ++cartRequestVersion;
    cartRequestPending = true;
    form.dataset.pending = 'true';
    form.classList.add('is-pending');
    form.setAttribute('aria-busy', 'true');
    if (button) {
      button.disabled = true;
      button.textContent = form.dataset.pendingText || button.textContent;
    }

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const firstError = Object.values(payload.errors || {}).flat()[0];
        throw new Error(firstError || payload.message || cartFeedback?.dataset.errorMessage);
      }

      const confirmedState = { cart_count: payload.cart_count };
      applyCartState({ ...payload, ...confirmedState }, requestVersion, form.dataset.productId);
      cartChannel?.postMessage(confirmedState);
      announceCart(payload.message);
    } catch (error) {
      announceCart(error.message || cartFeedback?.dataset.errorMessage, true);
    } finally {
      delete form.dataset.pending;
      cartRequestPending = false;
      form.classList.remove('is-pending');
      form.setAttribute('aria-busy', 'false');
      if (button) {
        button.disabled = false;
        button.innerHTML = originalMarkup;
      }
    }
  });

  document.addEventListener('click', (event) => {
    const stepButton = event.target.closest('[data-cart-step]');
    if (!stepButton) return;
    const input = stepButton.closest('form')?.querySelector('input[name="quantity"]');
    if (!input) return;
    const min = Number(input.min || 1);
    const max = Number(input.max || 99);
    const next = Math.min(max, Math.max(min, Number(input.value || min) + Number(stepButton.dataset.cartStep || 0)));
    input.value = String(next);
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.focus();
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-cart-mutation-form]');
    if (!form) return;
    event.preventDefault();
    if (cartRequestPending) return;

    const requestVersion = ++cartRequestVersion;
    const productId = form.dataset.productId || null;
    const focusProductId = form.dataset.cartAction === 'update' ? productId : null;
    cartRequestPending = true;
    form.classList.add('is-pending');
    form.setAttribute('aria-busy', 'true');
    cartRegion?.setAttribute('aria-busy', 'true');
    document.querySelectorAll('[data-cart-mutation-submit]').forEach((button) => { button.disabled = true; });

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const firstError = Object.values(payload.errors || {}).flat()[0];
        throw new Error(firstError || payload.message || cartFeedback?.dataset.errorMessage);
      }

      applyCartState(payload, requestVersion, focusProductId);
      cartChannel?.postMessage({ cart_count: payload.cart_count });
      announceCart(payload.message);
    } catch (error) {
      announceCart(error.message || cartFeedback?.dataset.errorMessage, true);
    } finally {
      cartRequestPending = false;
      form.classList.remove('is-pending');
      form.setAttribute('aria-busy', 'false');
      cartRegion?.setAttribute('aria-busy', 'false');
      document.querySelectorAll('[data-cart-mutation-submit]').forEach((button) => { button.disabled = false; });
    }
  });

  // Newsletter: AJAX enhancement. The same form still works as a normal POST without JavaScript.
  document.querySelectorAll('[data-newsletter-form]').forEach((form) => {
    const feedback = form.querySelector('[data-newsletter-feedback]');
    const submit = form.querySelector('[data-newsletter-submit]');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (form.classList.contains('is-loading')) return;

      form.classList.add('is-loading');
      form.setAttribute('aria-busy', 'true');
      if (submit) submit.disabled = true;
      if (feedback) {
        feedback.textContent = '';
        feedback.classList.remove('is-error', 'is-success');
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          const firstError = Object.values(payload.errors || {}).flat()[0];
          throw new Error(firstError || form.dataset.errorMessage || 'Unable to subscribe.');
        }

        form.reset();
        if (feedback) {
          feedback.textContent = payload.message || '';
          feedback.classList.add('is-success');
        }
      } catch (error) {
        if (feedback) {
          feedback.textContent = error.message || form.dataset.errorMessage || 'Unable to subscribe.';
          feedback.classList.add('is-error');
        }
      } finally {
        form.classList.remove('is-loading');
        form.setAttribute('aria-busy', 'false');
        if (submit) submit.disabled = false;
      }
    });
  });
})();
