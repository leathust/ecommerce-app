(function ($) {
    'use strict';
    var config = $('#product-bulk-config');
    if (!config.length) return;
    var modalElement = document.getElementById('bulk-delete-products-modal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    function selectedIds() { return $('.product-checkbox:checked').map(function () { return Number(this.value); }).get(); }
    function syncSelectionUi() {
        var all = $('.product-checkbox'), checked = all.filter(':checked'), selectAll = $('#select-all-products').get(0);
        $('#selected-product-count, #bulk-delete-modal-count').text(checked.length);
        $('#bulk-delete-open, #clear-product-selection').prop('disabled', checked.length === 0);
        $('#current-page-selection-note').toggleClass('d-none', checked.length === 0);
        if (selectAll) { selectAll.checked = all.length > 0 && checked.length === all.length; selectAll.indeterminate = checked.length > 0 && checked.length < all.length; selectAll.disabled = all.length === 0; }
    }
    function showMessage(type, message) { $('<div>', {class: 'alert alert-' + type, role: 'alert'}).text(message).appendTo($('#product-action-message').empty()); }

    $(document).on('change', '#select-all-products', function () { $('.product-checkbox').prop('checked', this.checked); syncSelectionUi(); })
      .on('change', '.product-checkbox', syncSelectionUi)
      .on('click', '#clear-product-selection', function () { $('.product-checkbox, #select-all-products').prop('checked', false); syncSelectionUi(); })
      .on('click', '#bulk-delete-open', function () { if (selectedIds().length) modal.show(); })
      .on('click', '#bulk-delete-confirm', function () {
          var ids = selectedIds(); if (!ids.length) { modal.hide(); return; }
          var button = $(this), params = new URLSearchParams(window.location.search);
          button.prop('disabled', true).find('.spinner-border').removeClass('d-none'); $(modalElement).find('[data-bs-dismiss="modal"]').prop('disabled', true);
          $.ajax({url: config.data('url'), method: 'POST', dataType: 'json', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': config.data('csrf')}, data: {ids: ids, q: params.get('q') || '', page: params.get('page') || 1}})
            .done(function (response) {
                $('#product-list-region').html(response.html); $('#product-total').text(new Intl.NumberFormat('en-US').format(response.total)); modal.hide();
                var details = []; if (response.blocked.length) details.push(response.blocked.length + ' sản phẩm bị bỏ qua vì đang được tham chiếu'); if (response.missing_ids.length) details.push(response.missing_ids.length + ' sản phẩm không còn tồn tại');
                showMessage(details.length ? 'warning' : 'success', response.message + (details.length ? ' ' + details.join('; ') + '.' : ''));
                params.set('page', response.page); window.history.replaceState({}, '', window.location.pathname + '?' + params.toString()); syncSelectionUi();
            }).fail(function (xhr) {
                var message = 'Không thể xóa sản phẩm. Vui lòng tải lại trang và thử lại.';
                if (xhr.status === 401 || xhr.status === 419) message = 'Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang và đăng nhập lại.';
                else if (xhr.status === 403) message = 'Bạn không có quyền xóa sản phẩm.';
                else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                showMessage('danger', message);
            }).always(function () { button.prop('disabled', false).find('.spinner-border').addClass('d-none'); $(modalElement).find('[data-bs-dismiss="modal"]').prop('disabled', false); });
      });
    syncSelectionUi();
})(jQuery);
