(function () {
  'use strict';

  const feedback = document.getElementById('menu-ajax-feedback');
  const moveModalElement = document.getElementById('menu-move-modal');
  const moveForm = moveModalElement?.querySelector('[data-menu-move-form]');
  const moveCatalogElement = document.getElementById('menu-move-catalog');
  const moveCatalog = moveCatalogElement ? JSON.parse(moveCatalogElement.textContent || '[]') : [];
  const maxDepth = Number(moveCatalogElement?.dataset.maxDepth || 4);
  let moveSubject = null;

  function notify(message, success) {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = `alert ${success ? 'alert-success' : 'alert-danger'}`;
    feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function submit(form, data) {
    const response = await fetch(form.action, {
      method: 'POST',
      body: data,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Không thể cập nhật menu. Vui lòng thử lại.');
    return payload;
  }

  function scopeIds(row) {
    return (row.dataset.scopeIds || '').split(',').filter(Boolean).map(Number);
  }

  function reorderedIds(row, direction) {
    const ids = scopeIds(row);
    const current = ids.indexOf(Number(row.dataset.menuId));
    const target = current + (direction === 'up' ? -1 : 1);
    if (current < 0 || target < 0 || target >= ids.length) return null;
    [ids[current], ids[target]] = [ids[target], ids[current]];
    return { ids, targetId: ids[current] };
  }

  function branchBlock(row) {
    const rows = [row];
    const level = Number(row.dataset.menuLevel);
    let next = row.nextElementSibling;
    while (next?.matches('[data-menu-row]') && Number(next.dataset.menuLevel) > level) {
      rows.push(next);
      next = next.nextElementSibling;
    }
    return rows;
  }

  function moveVisibleRows(row, targetRow, direction) {
    if (!targetRow) return false;
    const rows = branchBlock(row);
    const targetRows = branchBlock(targetRow);
    const fragment = document.createDocumentFragment();
    rows.forEach(item => fragment.appendChild(item));
    if (direction === 'up') targetRows[0].before(fragment);
    else targetRows[targetRows.length - 1].after(fragment);
    return true;
  }

  function refreshAncestorWarnings() {
    const activeAtLevel = [];
    document.querySelectorAll('[data-menu-row]').forEach(row => {
      const level = Number(row.dataset.menuLevel);
      activeAtLevel.length = level;
      const ancestorsActive = activeAtLevel.every(Boolean);
      row.querySelector('[data-parent-hidden-warning]')?.classList.toggle('d-none', ancestorsActive);
      activeAtLevel[level] = row.dataset.menuStatus === '1';
    });
  }

  function refreshScopeControls(ids, row) {
    document.querySelectorAll(`[data-menu-row][data-menu-location="${CSS.escape(row.dataset.menuLocation)}"][data-menu-parent="${CSS.escape(row.dataset.menuParent)}"]`).forEach(item => {
      item.dataset.scopeIds = ids.join(',');
      const index = ids.indexOf(Number(item.dataset.menuId));
      item.querySelectorAll('[data-menu-reorder-form]').forEach(form => {
        const direction = form.dataset.direction;
        const target = index + (direction === 'up' ? -1 : 1);
        form.querySelector('button').disabled = target < 0 || target >= ids.length;
      });
    });
  }

  function syncMoveOptions() {
    if (!moveForm || !moveSubject) return;
    const location = moveForm.elements.location.value;
    const parent = moveForm.elements.parent_id;
    const selected = String(parent.value || moveSubject.parentId || '');
    parent.replaceChildren(new Option('Không có — mục gốc', ''));

    moveCatalog.forEach(candidate => {
      const isInvalid = candidate.id === moveSubject.id
        || candidate.ancestors.includes(moveSubject.id)
        || candidate.depth + moveSubject.subtreeHeight > maxDepth
        || candidate.location !== location;
      if (isInvalid) return;
      const indent = '— '.repeat(Math.max(0, candidate.depth - 1));
      const option = new Option(`${indent}${candidate.breadcrumb}`, String(candidate.id));
      option.dataset.depth = String(candidate.depth);
      parent.appendChild(option);
    });

    if (Array.from(parent.options).some(option => option.value === selected)) parent.value = selected;
    const parentDepth = Number(parent.selectedOptions[0]?.dataset.depth || 0);
    moveForm.querySelector('[data-menu-move-depth]').textContent = `Nhánh sẽ bắt đầu ở cấp ${parentDepth + 1} và sâu nhất ở cấp ${parentDepth + moveSubject.subtreeHeight}/${maxDepth}.`;
  }

  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-menu-move-trigger]');
    if (!trigger || !moveForm || !window.bootstrap?.Modal) return;
    event.preventDefault();
    moveSubject = {
      id: Number(trigger.dataset.menuId),
      parentId: trigger.dataset.menuParent,
      location: trigger.dataset.menuLocation,
      subtreeHeight: Number(trigger.dataset.menuSubtreeHeight)
    };
    moveForm.action = trigger.dataset.menuMoveUrl;
    moveForm.elements.source_parent_id.value = trigger.dataset.menuParent;
    moveForm.elements.source_location.value = trigger.dataset.menuLocation;
    moveForm.elements.location.value = trigger.dataset.menuLocation;
    Array.from(moveForm.elements.location.options).forEach(option => {
      option.disabled = moveSubject.subtreeHeight > 1 && option.value !== moveSubject.location;
    });
    moveForm.querySelector('[data-menu-move-name]').textContent = trigger.dataset.menuName;
    moveForm.querySelector('[data-menu-move-page]').href = trigger.href;
    moveForm.querySelector('[data-menu-move-warning]').classList.add('d-none');
    syncMoveOptions();
    window.bootstrap.Modal.getOrCreateInstance(moveModalElement).show();
  });

  moveForm?.elements.location.addEventListener('change', syncMoveOptions);
  moveForm?.elements.parent_id.addEventListener('change', syncMoveOptions);

  document.addEventListener('submit', async function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.matches('[data-menu-move-form]')) {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const warning = form.querySelector('[data-menu-move-warning]');
      button.disabled = true;
      warning.classList.add('d-none');
      try {
        const payload = await submit(form, new FormData(form));
        window.bootstrap?.Modal.getInstance(moveModalElement)?.hide();
        notify(payload.message + ' Đang tải lại cây menu...', true);
        window.setTimeout(() => window.location.assign(`${window.location.pathname}?location=${encodeURIComponent(payload.menu.location)}`), 500);
      } catch (error) {
        warning.textContent = error.message;
        warning.classList.remove('d-none');
        button.disabled = false;
      }
      return;
    }

    if (form.matches('[data-menu-status-form]')) {
      event.preventDefault();
      const button = form.querySelector('[data-menu-status-button]');
      const row = form.closest('[data-menu-row]');
      button.disabled = true;
      try {
        const payload = await submit(form, new FormData(form));
        const active = Boolean(payload.menu.status);
        const badge = row.querySelector('[data-menu-status-badge]');
        const input = form.querySelector('input[name="status"]');
        badge.textContent = active ? 'Hiển thị' : 'Đang ẩn';
        badge.classList.toggle('text-bg-success', active);
        badge.classList.toggle('text-bg-secondary', !active);
        row.dataset.menuStatus = active ? '1' : '0';
        input.value = active ? '0' : '1';
        button.classList.toggle('btn-outline-secondary', active);
        button.classList.toggle('btn-outline-success', !active);
        button.querySelector('i').className = `fa-solid ${active ? 'fa-eye-slash' : 'fa-eye'} me-1`;
        button.querySelector('span').textContent = active ? 'Ẩn' : 'Hiện';
        refreshAncestorWarnings();
        notify(payload.message, true);
      } catch (error) {
        notify(error.message, false);
      } finally {
        button.disabled = false;
      }
      return;
    }

    if (form.matches('[data-menu-reorder-form]')) {
      event.preventDefault();
      const row = form.closest('[data-menu-row]');
      const order = reorderedIds(row, form.dataset.direction);
      if (!order) return;
      const data = new FormData(form);
      data.delete('ordered_ids[]');
      order.ids.forEach(id => data.append('ordered_ids[]', String(id)));
      const button = form.querySelector('button');
      button.disabled = true;
      try {
        const payload = await submit(form, data);
        const targetRow = document.querySelector(`[data-menu-row][data-menu-id="${order.targetId}"]`);
        const moved = moveVisibleRows(row, targetRow, form.dataset.direction);
        payload.items.forEach(item => {
          const itemRow = document.querySelector(`[data-menu-row][data-menu-id="${item.id}"]`);
          if (itemRow) itemRow.querySelector('[data-menu-sort-order]').textContent = item.sort_order;
        });
        refreshScopeControls(order.ids, row);
        notify(payload.message + (moved ? '' : ' Trang sẽ được tải lại để hiển thị vị trí mới.'), true);
        if (!moved) window.setTimeout(() => window.location.reload(), 500);
      } catch (error) {
        notify(error.message, false);
        button.disabled = false;
      }
    }
  });

  refreshAncestorWarnings();
})();
