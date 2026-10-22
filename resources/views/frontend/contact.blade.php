@extends('frontend.layout')

@section('content')
  <main id="main">
    <section class="page-head">
      <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">Trang chủ</a> <span class="sep">›</span> <span>Liên hệ</span></div>
        <h1>Liên hệ với chúng tôi</h1>
        <p>Đội ngũ hỗ trợ sẽ tiếp nhận và phản hồi yêu cầu của bạn trong thời gian sớm nhất.</p>
      </div>
    </section>

    <section>
      <div class="container">
        <div class="contact-grid">
          <div class="contact-info">
            <div class="info-block">
              <div class="ic">✉</div>
              <div><div class="label">EMAIL</div><div class="value"><a href="mailto:support@sprylo.example">support@sprylo.example</a></div></div>
            </div>
            <div class="info-block">
              <div class="ic">☎</div>
              <div><div class="label">ĐIỆN THOẠI · 24/7</div><div class="value"><a href="tel:+18005551234">+1 (800) 555-1234</a></div></div>
            </div>
            <div class="info-block">
              <div class="ic">⌖</div>
              <div><div class="label">ĐỊA CHỈ</div><div class="value">214 Market Street<br>San Francisco, CA 94103</div></div>
            </div>
            <div class="info-block">
              <div class="ic">◷</div>
              <div><div class="label">GIỜ HỖ TRỢ</div><div class="value">Thứ Hai – Thứ Sáu · 06:00 – 22:00<br>Thứ Bảy – Chủ Nhật · 08:00 – 18:00</div></div>
            </div>
          </div>

          <form id="contact-form" class="contact-form" action="{{ route('contact.store') }}" method="POST">
            @csrf

            <h2 style="font-size: var(--text-xl); margin-bottom: var(--s2)">Gửi yêu cầu hỗ trợ</h2>
            <p style="color: var(--fg-soft); font-size: var(--text-sm); margin-bottom: var(--s5)">Vui lòng cung cấp đủ thông tin để chúng tôi có thể hỗ trợ bạn nhanh chóng.</p>

            @if (session('success'))
              <div role="status" style="padding:14px 16px;margin-bottom:var(--s5);border:1px solid #86efac;background:#f0fdf4;color:#166534;border-radius:var(--r)">
                {{ session('success') }}
              </div>
            @endif

            @if ($errors->any())
              <div role="alert" style="padding:14px 16px;margin-bottom:var(--s5);border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:var(--r)">
                Vui lòng kiểm tra lại các trường được đánh dấu bên dưới.
              </div>
            @endif

            <div class="field-row">
              <div class="field">
                <label for="c-first">Tên</label>
                <input id="c-first" type="text" name="first_name" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required>
                @error('first_name')<small style="color:#b91c1c">{{ $message }}</small>@enderror
              </div>
              <div class="field">
                <label for="c-last">Họ</label>
                <input id="c-last" type="text" name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required>
                @error('last_name')<small style="color:#b91c1c">{{ $message }}</small>@enderror
              </div>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="c-email">Email</label>
                <input id="c-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                @error('email')<small style="color:#b91c1c">{{ $message }}</small>@enderror
              </div>
              <div class="field">
                <label for="c-phone">Điện thoại (không bắt buộc)</label>
                <input id="c-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel">
                @error('phone')<small style="color:#b91c1c">{{ $message }}</small>@enderror
              </div>
            </div>

            <div class="field">
              <label for="c-topic">Nội dung cần hỗ trợ</label>
              <select id="c-topic" name="reason" required>
                <option value="">Chọn nội dung</option>
                @foreach ($reasons as $value => $label)
                  <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                @endforeach
              </select>
              @error('reason')<small style="color:#b91c1c">{{ $message }}</small>@enderror
            </div>

            <div class="field">
              <label for="c-order">Mã đơn hàng (nếu có)</label>
              <input id="c-order" type="text" name="order_code" value="{{ old('order_code') }}" maxlength="50" placeholder="Ví dụ: SPR-204418">
              @error('order_code')<small style="color:#b91c1c">{{ $message }}</small>@enderror
            </div>

            <div class="field">
              <label for="c-msg">Nội dung liên hệ</label>
              <textarea id="c-msg" name="message" maxlength="5000" required>{{ old('message') }}</textarea>
              @error('message')<small style="color:#b91c1c">{{ $message }}</small>@enderror
            </div>

            <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
              <label for="website">Website</label>
              <input id="website" type="text" name="website" value="" tabindex="-1" autocomplete="off">
            </div>

            <button id="contact-submit" class="btn btn--indigo btn--block" type="submit" style="padding:16px;font-size:var(--text-base);margin-top:var(--s2)">Gửi liên hệ →</button>
            <p style="font-family:var(--ff-mono);font-size:11px;color:var(--fg-mute);margin-top:var(--s4);text-align:center;line-height:1.6">Thông tin của bạn chỉ được sử dụng để xử lý yêu cầu hỗ trợ.</p>
          </form>
        </div>
      </div>
    </section>
  </main>

  <script>
    document.getElementById('contact-form').addEventListener('submit', function () {
      const button = document.getElementById('contact-submit');
      button.disabled = true;
      button.textContent = 'Đang gửi...';
    });
  </script>
@endsection
