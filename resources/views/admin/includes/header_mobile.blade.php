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
      </ul>
    </div>
  </nav>
</header>
