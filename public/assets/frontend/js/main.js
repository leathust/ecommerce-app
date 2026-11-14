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

  // Account forms: AJAX enhancement only. Native PATCH/PUT forms remain the no-JS contract.
  const profileForms = Array.from(document.querySelectorAll('[data-profile-form]'));
  const profileFeedback = document.querySelector('[data-profile-feedback]');
  const profileRequestState = new WeakMap();
  let profileRequestVersion = 0;
  const profileFieldAllowlist = new Set(['first_name', 'last_name', 'phone', 'current_password', 'password', 'password_confirmation']);

  const clearProfileErrors = (form) => {
    form.querySelectorAll('.field-error--ajax').forEach((error) => error.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
      input.removeAttribute('aria-invalid');
      const describedBy = (input.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter((id) => id && !id.endsWith('-ajax-error'));
      if (describedBy.length) input.setAttribute('aria-describedby', describedBy.join(' '));
      else input.removeAttribute('aria-describedby');
    });
  };

  const showProfileFeedback = (message, isError = false, shouldFocus = true) => {
    if (!profileFeedback || !message) return;
    profileFeedback.textContent = message;
    profileFeedback.hidden = false;
    profileFeedback.classList.toggle('form-alert--error', isError);
    profileFeedback.classList.toggle('form-alert--success', !isError);
    if (shouldFocus) profileFeedback.focus({ preventScroll: true });
  };

  const renderProfileErrors = (form, errors = {}) => {
    let firstInvalid = null;
    Object.entries(errors).forEach(([name, messages]) => {
      if (!profileFieldAllowlist.has(name)) return;
      const input = form.elements.namedItem(name);
      if (!(input instanceof HTMLElement)) return;
      const message = Array.isArray(messages) ? messages[0] : messages;
      if (!message) return;
      const id = `${input.id || `profile-${name}`}-ajax-error`;
      const error = document.createElement('small');
      error.id = id;
      error.className = 'field-error field-error--ajax';
      error.textContent = String(message);
      input.insertAdjacentElement('afterend', error);
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), id].filter(Boolean).join(' '));
      firstInvalid ||= input;
    });
    firstInvalid?.focus({ preventScroll: true });
    return firstInvalid;
  };

  const applyConfirmedProfile = (payload) => {
    const profile = payload?.profile;
    if (!profile || typeof profile.display_name !== 'string') return;
    document.querySelectorAll('[data-profile-display-name]').forEach((node) => { node.textContent = profile.display_name; });
    const avatar = document.querySelector('[data-profile-avatar]');
    if (avatar) avatar.textContent = Array.from(profile.display_name.trim())[0]?.toLocaleUpperCase() || '';
    const welcome = document.querySelector('[data-profile-welcome]');
    if (welcome) welcome.textContent = (welcome.dataset.welcomeTemplate || '__NAME__').replace('__NAME__', profile.display_name);
  };

  profileForms.forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (form.getAttribute('aria-busy') === 'true') return;

      const submit = form.querySelector('[data-profile-submit]');
      const originalLabel = submit?.textContent;
      const version = ++profileRequestVersion;
      const controller = new AbortController();
      profileRequestState.get(form)?.controller?.abort();
      profileRequestState.set(form, { version, controller });
      clearProfileErrors(form);
      if (profileFeedback) profileFeedback.hidden = true;
      form.setAttribute('aria-busy', 'true');
      form.classList.add('is-pending');
      if (submit) {
        submit.disabled = true;
        submit.textContent = form.dataset.pendingText || originalLabel;
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          signal: controller.signal,
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json().catch(() => ({}));
        if (profileRequestState.get(form)?.version !== version) return;

        if (!response.ok) {
          const hasFieldError = renderProfileErrors(form, payload.errors);
          const firstError = Object.values(payload.errors || {}).flat()[0];
          showProfileFeedback(firstError || payload.message || profileFeedback?.dataset.errorMessage, true, !hasFieldError);
          return;
        }

        if (form.dataset.profileForm === 'profile') applyConfirmedProfile(payload);
        if (form.dataset.profileForm === 'password') form.reset();
        showProfileFeedback(payload.message);
      } catch (error) {
        if (error.name !== 'AbortError' && profileRequestState.get(form)?.version === version) {
          showProfileFeedback(error.message || profileFeedback?.dataset.errorMessage, true);
        }
      } finally {
        if (profileRequestState.get(form)?.version === version) {
          form.setAttribute('aria-busy', 'false');
          form.classList.remove('is-pending');
          if (submit) {
            submit.disabled = false;
            submit.textContent = originalLabel;
          }
        }
      }
    });
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
  // Checkout form: keep the native POST, but lock the submit control while it is in flight.
  document.querySelectorAll('[data-checkout-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }

      form.dataset.submitting = 'true';
      form.setAttribute('aria-busy', 'true');
      form.classList.add('is-pending');

      const submit = form.querySelector('[data-checkout-submit]');
      if (submit) {
        submit.disabled = true;
        submit.textContent = form.dataset.pendingText || submit.textContent;
      }
    });
  });

  // Payment page: countdown and owner-scoped polling. The server response remains the source of truth.
  const paymentRoot = document.querySelector('[data-payment-status]');

  if (paymentRoot && paymentRoot.dataset.statusUrl) {
    const countdown = paymentRoot.querySelector('[data-payment-countdown]');
    const countdownWrap = paymentRoot.querySelector('[data-payment-countdown-wrap]');
    const statusBox = paymentRoot.querySelector('.payment-status');
    const statusText = paymentRoot.querySelector('[data-payment-status-text]');
    const statusDetail = paymentRoot.querySelector('[data-payment-status-detail]');
    const feedback = paymentRoot.querySelector('[data-payment-feedback]');

    let remaining = Math.max(0, Number(paymentRoot.dataset.remainingSeconds) || 0);
    let countdownTimer = null;
    let pollTimer = null;
    let polling = false;
    let stopped = false;
    let failures = 0;
    let expiryPolled = false;

    const pad = (value) => String(value).padStart(2, '0');

    const renderCountdown = () => {
      if (!countdown) return;
      countdown.textContent = `${pad(Math.floor(remaining / 60))}:${pad(remaining % 60)}`;
      countdownWrap?.classList.toggle('is-expired', remaining <= 0);
    };

    const announce = (message) => {
      if (!feedback) return;
      feedback.textContent = message;
      feedback.hidden = message === '';
    };

    const showError = (message) => {
      if (!statusBox) return;
      statusBox.classList.add('is-error');
      if (statusText) statusText.textContent = message;
      if (statusDetail) statusDetail.textContent = '';
    };

    const stopPolling = () => {
      stopped = true;
      window.clearTimeout(pollTimer);
      if (countdownTimer) window.clearInterval(countdownTimer);
    };

    const schedule = (delay) => {
      window.clearTimeout(pollTimer);
      if (stopped) return;
      pollTimer = window.setTimeout(poll, Math.max(1000, Number(delay) || 5000));
    };

    const poll = async () => {
      if (stopped || polling) return;
      polling = true;

      try {
        const response = await fetch(paymentRoot.dataset.statusUrl, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          cache: 'no-store',
        });

        if (response.status === 404 || response.status === 403) {
          stopPolling();
          showError(paymentRoot.dataset.lostText || '');
          return;
        }

        if (!response.ok) throw new Error('payment-status');
        const payload = await response.json();
        failures = 0;

        if (typeof payload.remaining_seconds === 'number') {
          remaining = Math.max(0, Number(payload.remaining_seconds) || 0);
          if (remaining > 0) expiryPolled = false;
          renderCountdown();
        }

        if (typeof payload.state === 'string' && payload.state !== paymentRoot.dataset.state) {
          window.location.reload();
          return;
        }

        schedule(payload.poll_after_ms);
      } catch (error) {
        failures += 1;
        if (failures >= 3) showError(paymentRoot.dataset.errorText || '');
        schedule(Math.min(30000, 5000 * Math.min(failures, 4)));
      } finally {
        polling = false;
      }
    };

    renderCountdown();
    schedule(paymentRoot.dataset.pollAfterMs);

    countdownTimer = window.setInterval(() => {
      if (stopped) return;
      if (remaining > 0) {
        remaining -= 1;
        renderCountdown();
      }
      if (remaining === 0 && !expiryPolled) {
        expiryPolled = true;
        poll();
      }
    }, 1000);

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden && !stopped) poll();
    });
  }

  // Copy-to-clipboard controls on the payment page.
  const copyFeedback = document.querySelector('[data-payment-feedback]');
  const copyFailedText = document.querySelector('[data-payment-status]')?.dataset.copyFailedText || '';

  const writeClipboard = async (value) => {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      await navigator.clipboard.writeText(value);
      return true;
    }

    const area = document.createElement('textarea');
    area.value = value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let copied = false;
    try {
      copied = document.execCommand('copy');
    } catch (error) {
      copied = false;
    }
    area.remove();
    return copied;
  };

  document.querySelectorAll('[data-copy-value]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.copyValue || '';
      const original = button.textContent;
      const copiedText = button.dataset.copiedText || original;

      try {
        const copied = await writeClipboard(value);
        if (!copied) throw new Error('copy');
        button.textContent = copiedText;
        if (copyFeedback) {
          copyFeedback.hidden = false;
          copyFeedback.textContent = copiedText;
        }
      } catch (error) {
        if (copyFeedback) {
          copyFeedback.hidden = false;
          copyFeedback.textContent = copyFailedText;
        }
      }

      window.setTimeout(() => {
        button.textContent = original;
        if (copyFeedback) {
          copyFeedback.hidden = true;
          copyFeedback.textContent = '';
        }
      }, 2000);
    });
  });
})();
