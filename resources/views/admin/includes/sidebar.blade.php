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

        @can('categories.view')
          <li class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
            <a href="{{ route('admin.categories.index') }}"><i class="fa-solid fa-folder-tree"></i>Danh mục</a>
          </li>
        @endcan
        @can('brands.view')
          <li class="{{ request()->routeIs('admin.brands.*') ? 'active' : '' }}">
            <a href="{{ route('admin.brands.index') }}"><i class="fa-solid fa-tags"></i>Thương hiệu</a>
          </li>
        @endcan
        @can('products.view')
          <li class="{{ request()->routeIs('admin.products', 'admin.products.*') ? 'active' : '' }}">
            <a href="{{ route('admin.products') }}">
              <i class="fa-solid fa-box"></i>Sản phẩm
            </a>
          </li>
        @endcan

        @can('orders.view')
          <li class="{{ request()->routeIs('admin.orders', 'admin.orders.*') ? 'active' : '' }}">
            <a href="{{ route('admin.orders') }}">
              <i class="fa-solid fa-receipt"></i>Đơn hàng
            </a>
          </li>
        @endcan

        @can('payments.view')
          <li class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
            <a href="{{ route('admin.payments.reconciliation') }}">
              <i class="fa-solid fa-money-check-dollar"></i>Đối soát thanh toán
            </a>
          </li>
        @endcan

        @can('customers.view')
          <li class="{{ request()->routeIs('admin.customers', 'admin.customers.*') ? 'active' : '' }}">
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

        @canany(['users.view', 'roles.view', 'permissions.manage'])
          @php($iamMenuActive = request()->routeIs('admin.users.*', 'admin.roles.*', 'admin.access-logs.*'))
          <li class="{{ $iamMenuActive ? 'active' : '' }} has-sub">
            <a class="js-arrow {{ $iamMenuActive ? 'open' : '' }}" href="#" aria-expanded="{{ $iamMenuActive ? 'true' : 'false' }}">
              <i class="fa-solid fa-user-lock"></i>Quản lý phân quyền
            </a>
            <ul class="list-unstyled navbar__sub-list js-sub-list">
              @can('users.view')<li class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><a href="{{ route('admin.users.index') }}"><i class="fa-solid fa-user-shield"></i>Tài khoản &amp; quyền</a></li>@endcan
              @can('roles.view')<li class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"><a href="{{ route('admin.roles.index') }}"><i class="fa-solid fa-shield-halved"></i>Vai trò</a></li>@endcan
              @can('permissions.manage')<li class="{{ request()->routeIs('admin.access-logs.*') ? 'active' : '' }}"><a href="{{ route('admin.access-logs.index') }}"><i class="fa-solid fa-clock-rotate-left"></i>Nhật ký quản trị</a></li>@endcan
            </ul>
          </li>
        @endcanany

        @can('settings.view')
          <li class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <a href="{{ route('admin.settings.index') }}"><i class="fa-solid fa-gears"></i>Cài đặt hệ thống</a>
          </li>
        @endcan

        @can('menus.view')
          <li class="{{ request()->routeIs('admin.menus.*') ? 'active' : '' }}">
            <a href="{{ route('admin.menus.index') }}"><i class="fa-solid fa-diagram-project"></i>Menu website</a>
          </li>
        @endcan

        @can('banners.view')
          <li class="{{ request()->routeIs('admin.home-banners.*') ? 'active' : '' }}">
            <a href="{{ route('admin.home-banners.index') }}"><i class="fa-solid fa-images"></i>Banner trang chủ</a>
          </li>
        @endcan

        @can('dashboard.view')
          @php($templateMenuActive = request()->routeIs('admin.charts', 'admin.tables*', 'admin.data-tables', 'admin.forms.*', 'admin.calendar', 'admin.maps', 'admin.inbox', 'admin.kanban', 'admin.notifications', 'admin.documentation'))
          <li class="{{ $templateMenuActive ? 'active' : '' }} has-sub">
            <a class="js-arrow {{ $templateMenuActive ? 'open' : '' }}" href="#" aria-expanded="{{ $templateMenuActive ? 'true' : 'false' }}">
              <i class="fa-solid fa-layer-group"></i>Giao diện mẫu
            </a>
            <ul class="list-unstyled navbar__sub-list js-sub-list">
              <li class="{{ request()->routeIs('admin.charts') ? 'active' : '' }}"><a href="{{ route('admin.charts') }}">Biểu đồ</a></li>
              <li class="{{ request()->routeIs('admin.tables*', 'admin.data-tables') ? 'active' : '' }}"><a href="{{ route('admin.tables') }}">Bảng dữ liệu</a></li>
              <li class="{{ request()->routeIs('admin.forms.*') ? 'active' : '' }}"><a href="{{ route('admin.forms.form') }}">Biểu mẫu</a></li>
              <li class="{{ request()->routeIs('admin.calendar') ? 'active' : '' }}"><a href="{{ route('admin.calendar') }}">Lịch</a></li>
              <li class="{{ request()->routeIs('admin.notifications') ? 'active' : '' }}"><a href="{{ route('admin.notifications') }}">Thông báo</a></li>
              <li class="{{ request()->routeIs('admin.documentation') ? 'active' : '' }}"><a href="{{ route('admin.documentation') }}">Tài liệu</a></li>
            </ul>
          </li>
        @endcan
      </ul>
    </nav>
  </div>
</aside>
