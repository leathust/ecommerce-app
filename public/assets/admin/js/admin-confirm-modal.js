(function () {
  'use strict';

  const modalElement = document.getElementById('admin-confirm-action-modal');
  if (!modalElement || typeof bootstrap === 'undefined') return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const titleElement = document.getElementById('admin-confirm-action-title');
  const messageElement = document.getElementById('admin-confirm-action-message');
  const confirmButton = document.getElementById('admin-confirm-action-submit');
  const iconElement = document.getElementById('admin-confirm-action-icon');
  const confirmedForms = new WeakSet();
  let pendingForm = null;
  let pendingSubmitter = null;

  function applyTone(tone) {
    const danger = tone !== 'warning';
    confirmButton.classList.toggle('btn-danger', danger);
    confirmButton.classList.toggle('btn-warning', !danger);
    iconElement.classList.toggle('bg-danger-subtle', danger);
    iconElement.classList.toggle('text-danger', danger);
    iconElement.classList.toggle('bg-warning-subtle', !danger);
    iconElement.classList.toggle('text-warning-emphasis', !danger);
  }

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-message]')) return;

    if (confirmedForms.has(form)) {
      confirmedForms.delete(form);
      return;
    }

    event.preventDefault();
    pendingForm = form;
    pendingSubmitter = event.submitter || null;
    titleElement.textContent = form.dataset.confirmTitle || 'Xác nhận thao tác';
    messageElement.textContent = form.dataset.confirmMessage;
    confirmButton.textContent = form.dataset.confirmButton || 'Xác nhận';
    applyTone(form.dataset.confirmTone || 'danger');
    modal.show();
  });

  confirmButton.addEventListener('click', function () {
    if (!pendingForm) return;

    const form = pendingForm;
    const submitter = pendingSubmitter;
    confirmButton.disabled = true;
    confirmedForms.add(form);
    modal.hide();

    if (typeof form.requestSubmit === 'function' && submitter) form.requestSubmit(submitter);
    else if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else HTMLFormElement.prototype.submit.call(form);
  });

  modalElement.addEventListener('hidden.bs.modal', function () {
    pendingForm = null;
    pendingSubmitter = null;
    confirmButton.disabled = false;
  });
})();
