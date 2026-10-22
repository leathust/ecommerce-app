<aside class="menu-sidebar" id="main-sidebar">
  <div class="logo">
    <a class="logo-link" href="{{ route('admin.dashboard') }}" aria-label="Trang quản trị">
      <span class="logo-mark" aria-hidden="true">E</span>
      <span class="logo-text">{{ config('app.name') }}</span>
    </a>
    <button class="sidebar-close js-sidebar-toggle" type="button" aria-label="Đóng menu">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

  <div class="menu-sidebar__content js-scrollbar1">
    <nav class="navbar-sidebar" aria-label="Điều hướng quản trị">
      <ul class="list-unstyled navbar__list">
        @can('dashboard.view')
          <li class="{{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}">
            <a href="{{ route('admin.dashboard') }}">
              <i class="fa-solid fa-gauge-high"></i>Tổng quan
            </a>
          </li>
        @endcan

        @can('products.view')
          <li class="{{ request()->routeIs('admin.products') ? 'active' : '' }}">
            <a href="{{ route('admin.products') }}">
              <i class="fa-solid fa-box"></i>Sản phẩm
            </a>
          </li>
        @endcan

        @can('orders.view')
          <li class="{{ request()->routeIs('admin.orders') ? 'active' : '' }}">
            <a href="{{ route('admin.orders') }}">
              <i class="fa-solid fa-receipt"></i>Đơn hàng
            </a>
          </li>
        @endcan

        @can('customers.view')
          <li class="{{ request()->routeIs('admin.customers') ? 'active' : '' }}">
            <a href="{{ route('admin.customers') }}">
              <i class="fa-solid fa-users"></i>Khách hàng
            </a>
          </li>
        @endcan

        @can('contacts.view')
          <li class="{{ request()->routeIs('admin.contacts.*') ? 'active' : '' }}">
            <a href="{{ route('admin.contacts.index') }}">
              <i class="fa-solid fa-envelope-open-text"></i>Liên hệ
            </a>
          </li>
        @endcan

        @can('dashboard.view')
          <li class="{{ request()->routeIs('admin.charts', 'admin.tables*', 'admin.data-tables', 'admin.forms.*', 'admin.calendar', 'admin.maps', 'admin.inbox', 'admin.kanban', 'admin.notifications', 'admin.documentation') ? 'active' : '' }} has-sub">
            <a class="js-arrow" href="#">
              <i class="fa-solid fa-layer-group"></i>Giao diện mẫu
            </a>
            <ul class="list-unstyled navbar__sub-list js-sub-list">
              <li><a href="{{ route('admin.charts') }}">Biểu đồ</a></li>
              <li><a href="{{ route('admin.tables') }}">Bảng dữ liệu</a></li>
              <li><a href="{{ route('admin.forms.form') }}">Biểu mẫu</a></li>
              <li><a href="{{ route('admin.calendar') }}">Lịch</a></li>
              <li><a href="{{ route('admin.notifications') }}">Thông báo</a></li>
              <li><a href="{{ route('admin.documentation') }}">Tài liệu</a></li>
            </ul>
          </li>
        @endcan
      </ul>
    </nav>
  </div>
</aside>
