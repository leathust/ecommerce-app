(() => {
  'use strict';

  const matrix = document.getElementById('permission-matrix');
  const selectAll = document.getElementById('permission-select-all');
  const count = document.getElementById('selected-permission-count');
  const search = document.getElementById('permission-search');
  const clearSearch = document.getElementById('permission-search-clear');
  const emptySearch = document.getElementById('permission-search-empty');

  if (!matrix || !selectAll || !count) return;

  const permissions = () => [...matrix.querySelectorAll('.permission-checkbox')];
  const refreshToggle = (toggle, checkboxes) => {
    const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
    toggle.checked = checkboxes.length > 0 && checked === checkboxes.length;
    toggle.indeterminate = checked > 0 && checked < checkboxes.length;
  };
  const refresh = () => {
    const all = permissions();
    matrix.querySelectorAll('.permission-module').forEach((module) => {
      const modulePermissions = [...module.querySelectorAll('.permission-checkbox')];
      refreshToggle(module.querySelector('.module-toggle'), modulePermissions);
      module.querySelector('.module-selected-count').textContent = modulePermissions
        .filter((checkbox) => checkbox.checked).length;
    });
    refreshToggle(selectAll, all);
    count.textContent = all.filter((checkbox) => checkbox.checked).length;
  };
  const applySearch = () => {
    const query = (search?.value || '').trim().toLocaleLowerCase('vi');
    let visibleModules = 0;

    matrix.querySelectorAll('.permission-module').forEach((module) => {
      let visibleOptions = 0;
      module.querySelectorAll('.permission-option-wrap').forEach((option) => {
        const matches = !query || option.dataset.permissionSearch.includes(query);
        option.hidden = !matches;
        if (matches) visibleOptions += 1;
      });

      module.hidden = visibleOptions === 0;
      module.querySelector('.permission-module-empty').hidden = visibleOptions > 0;
      if (visibleOptions > 0) visibleModules += 1;

      const collapse = module.querySelector('.accordion-collapse');
      if (query && visibleOptions > 0 && window.bootstrap?.Collapse) {
        window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
      }
    });

    if (clearSearch) clearSearch.hidden = !query;
    if (emptySearch) emptySearch.hidden = visibleModules > 0;
  };

  matrix.addEventListener('change', (event) => {
    if (event.target.matches('.module-toggle')) {
      event.target.closest('.permission-module').querySelectorAll('.permission-checkbox')
        .forEach((checkbox) => { checkbox.checked = event.target.checked; });
    }
    refresh();
  });
  selectAll.addEventListener('change', () => {
    permissions().forEach((checkbox) => { checkbox.checked = selectAll.checked; });
    refresh();
  });
  search?.addEventListener('input', applySearch);
  clearSearch?.addEventListener('click', () => {
    search.value = '';
    search.focus();
    applySearch();
  });

  refresh();
  applySearch();
})();
