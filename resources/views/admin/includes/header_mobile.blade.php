<header class="header-mobile d-block d-lg-none">
  <div class="header-mobile__bar">
    <div class="container-fluid">
      <div class="header-mobile-inner">
        <a class="logo-link" href="{{ route('admin.dashboard') }}" aria-label="Trang quản trị">
          <span class="logo-mark" aria-hidden="true">E</span>
          <span class="logo-text">{{ config('app.name') }}</span>
        </a>
        <button class="hamburger hamburger--slider" type="button" aria-label="Mở menu">
          <span class="hamburger-box"><span class="hamburger-inner"></span></span>
        </button>
      </div>
    </div>
  </div>

  <nav class="navbar-mobile" aria-label="Điều hướng quản trị trên thiết bị di động">
    <div class="container-fluid">
      <ul class="navbar-mobile__list list-unstyled">
        @can('dashboard.view')
          <li><a href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-gauge-high"></i>Tổng quan</a></li>
        @endcan
        @can('categories.view')
          <li><a href="{{ route('admin.categories.index') }}"><i class="fa-solid fa-folder-tree"></i>Danh mục</a></li>
        @endcan
        @can('brands.view')
          <li><a href="{{ route('admin.brands.index') }}"><i class="fa-solid fa-tags"></i>Thương hiệu</a></li>
        @endcan
        @can('products.view')
          <li><a href="{{ route('admin.products') }}"><i class="fa-solid fa-box"></i>Sản phẩm</a></li>
        @endcan
        @can('orders.view')
          <li><a href="{{ route('admin.orders') }}"><i class="fa-solid fa-receipt"></i>Đơn hàng</a></li>
        @endcan
        @can('customers.view')
          <li><a href="{{ route('admin.customers') }}"><i class="fa-solid fa-users"></i>Khách hàng</a></li>
        @endcan
        @can('contacts.view')
          <li><a href="{{ route('admin.contacts.index') }}"><i class="fa-solid fa-envelope-open-text"></i>Liên hệ</a></li>
        @endcan
        @canany(['users.view', 'roles.view', 'permissions.manage'])
          @php($iamMobileMenuActive = request()->routeIs('admin.users.*', 'admin.roles.*', 'admin.access-logs.*'))
          <li class="{{ $iamMobileMenuActive ? 'active' : '' }} has-dropdown">
            <a class="js-arrow {{ $iamMobileMenuActive ? 'open' : '' }}" href="#" aria-expanded="{{ $iamMobileMenuActive ? 'true' : 'false' }}"><i class="fa-solid fa-user-lock"></i>Quản lý phân quyền</a>
            <ul class="navbar-mobile__dropdown list-unstyled js-sub-list">
              @can('users.view')<li class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><a href="{{ route('admin.users.index') }}"><i class="fa-solid fa-user-shield"></i>Tài khoản &amp; quyền</a></li>@endcan
              @can('roles.view')<li class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"><a href="{{ route('admin.roles.index') }}"><i class="fa-solid fa-shield-halved"></i>Vai trò</a></li>@endcan
              @can('permissions.manage')<li class="{{ request()->routeIs('admin.access-logs.*') ? 'active' : '' }}"><a href="{{ route('admin.access-logs.index') }}"><i class="fa-solid fa-clock-rotate-left"></i>Nhật ký quản trị</a></li>@endcan
            </ul>
          </li>
        @endcanany
        @can('settings.view')
          <li class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><a href="{{ route('admin.settings.index') }}"><i class="fa-solid fa-gears"></i>Cài đặt hệ thống</a></li>
        @endcan
        @can('menus.view')
          <li class="{{ request()->routeIs('admin.menus.*') ? 'active' : '' }}"><a href="{{ route('admin.menus.index') }}"><i class="fa-solid fa-diagram-project"></i>Menu website</a></li>
        @endcan
      </ul>
    </div>
  </nav>
</header>
