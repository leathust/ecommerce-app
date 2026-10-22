<!DOCTYPE html>
<html lang="vi">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Đăng nhập khu vực quản trị {{ config('app.name') }}.">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#4272d7">
    <title>Đăng nhập quản trị | {{ config('app.name') }}</title>

    <link href="{{ asset('assets/admin/css/font-face.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('assets/admin/vendor/fontawesome-7.3.1/css/all.min.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('assets/admin/vendor/bootstrap-5.3.8.min.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('assets/admin/vendor/css-hamburgers/hamburgers.min.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('assets/admin/css/theme.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('assets/admin/css/app.css') }}" rel="stylesheet" media="all">
  </head>
  <body class="app auth-page">
    <a class="visually-hidden-focusable skip-link" href="#auth-form">Đi đến biểu mẫu đăng nhập</a>

    <main class="login-wrap" id="auth-form">
      <div class="login-content">
        <a href="{{ route('home') }}" class="auth-brand" aria-label="Trang chủ {{ config('app.name') }}">
          <span class="logo-mark" aria-hidden="true">E</span>
          <span class="logo-text">{{ config('app.name') }}</span>
        </a>

        <h1 class="auth-title">Đăng nhập quản trị</h1>
        <p class="auth-subtitle">Nhập thông tin tài khoản quản trị để tiếp tục.</p>

        @if ($errors->has('auth'))
          <div class="alert alert-danger" role="alert">
            {{ $errors->first('auth') }}
          </div>
        @endif

        @if (session('status'))
          <div class="alert alert-success" role="status">
            {{ session('status') }}
          </div>
        @endif

        <form id="admin-login-form" class="login-form" action="{{ route('admin.login.submit') }}" method="POST">
          @csrf

          <div class="form-group">
            <label for="email">Địa chỉ email</label>
            <input
              id="email"
              class="au-input @error('email') is-invalid @enderror"
              type="email"
              name="email"
              value="{{ old('email') }}"
              placeholder="admin@example.com"
              autocomplete="username"
              maxlength="255"
              aria-describedby="email-error"
              autofocus
              required
            >
            @error('email')
              <div id="email-error" class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input
              id="password"
              class="au-input @error('password') is-invalid @enderror"
              type="password"
              name="password"
              placeholder="••••••••"
              autocomplete="current-password"
              aria-describedby="password-error"
              required
            >
            @error('password')
              <div id="password-error" class="invalid-feedback" role="alert">{{ $message }}</div>
            @enderror
          </div>

          <div class="login-checkbox">
            <label for="remember">
              <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
              Ghi nhớ đăng nhập
            </label>
          </div>

          <button id="login-submit" class="au-btn au-btn--green" type="submit">
            <span class="submit-label">Đăng nhập</span>
            <span class="submit-loading d-none" aria-hidden="true">
              <i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...
            </span>
          </button>
        </form>
      </div>
    </main>

    <script src="{{ asset('assets/admin/js/vanilla-utils.js') }}"></script>
    <script src="{{ asset('assets/admin/vendor/bootstrap-5.3.8.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/bootstrap5-init.js') }}"></script>
    <script src="{{ asset('assets/admin/js/main-vanilla.js') }}"></script>
    <script src="{{ asset('assets/admin/js/modern-plugins.js') }}"></script>
    <script>
      document.getElementById('admin-login-form').addEventListener('submit', function () {
        const button = document.getElementById('login-submit');
        button.disabled = true;
        button.querySelector('.submit-label').classList.add('d-none');
        button.querySelector('.submit-loading').classList.remove('d-none');
      });
    </script>
  </body>
</html>
