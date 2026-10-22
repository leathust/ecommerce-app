<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <meta name="generator" content="CoolAdmin 3.4.0"/>
    <meta name="description" content="Modern Bootstrap 5 admin dashboard with Chart.js widgets, responsive tables, and clean typography."/>
    <title>Dashboard | CoolAdmin Bootstrap 5 Admin Dashboard</title>
    <meta property="og:type" content="website"/>
    <meta property="og:title" content="Dashboard | CoolAdmin Bootstrap 5 Admin Dashboard"/>
    <meta property="og:description" content="Modern Bootstrap 5 admin dashboard with Chart.js widgets, responsive tables, and clean typography."/>
    <meta property="og:image" content="screenshots/cooladmin-bootstrap-dashboard-2.png"/>
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="Dashboard | CoolAdmin Bootstrap 5 Admin Dashboard"/>
    <meta name="twitter:description" content="Modern Bootstrap 5 admin dashboard with Chart.js widgets, responsive tables, and clean typography."/>
    <meta name="theme-color" content="#4272d7"/>
    <link href="{{ asset('assets/admin/css/font-face.css') }}" rel="stylesheet" media="all"/>
    <link rel="preconnect" href="https://rsms.me/"/>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css"/>
    <link href="{{ asset('assets/admin/vendor/fontawesome-7.3.1/css/all.min.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('assets/admin/vendor/bootstrap-5.3.8.min.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('assets/admin/vendor/css-hamburgers/hamburgers.min.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('assets/admin/css/theme.css') }}" rel="stylesheet" media="all"/>
    <link href="{{ asset('assets/admin/css/app.css') }}" rel="stylesheet" media="all"/>
    @yield('styles')
  </head>
  <body class="app"><a class="visually-hidden-focusable skip-link" href="#main-content">Skip to main content</a>
    <div class="page-wrapper">
      @include('admin.includes.header_mobile')
      @include('admin.includes.sidebar')
      <div class="page-container">
        @include('admin.includes.header_desktop')
        <main class="main-content" id="main-content">
          <div class="section__content section__content--p30">
            <div class="container-fluid">
                @yield('content')
                @include('admin.includes.footer')
            </div>
          </div>
        </main>
      </div>
    </div>
    @stack('modals')
    <script src="{{ asset('assets/admin/js/vanilla-utils.js') }}"></script>
    <script src="{{ asset('assets/admin/vendor/bootstrap-5.3.8.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/vendor/chartjs/chart.umd.js-4.5.1.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/bootstrap5-init.js') }}"></script>
    <script src="{{ asset('assets/admin/js/main-vanilla.js') }}"></script>
    <script src="{{ asset('assets/admin/js/modern-plugins.js') }}"></script>
    @yield('scripts')
  </body>
</html>
