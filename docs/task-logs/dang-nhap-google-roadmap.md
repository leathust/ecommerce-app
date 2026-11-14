# Roadmap: Đăng nhập Google cho khách hàng storefront

## Mục tiêu

Cho khách mua hàng ở website bên ngoài (storefront) đăng nhập bằng tài khoản Google, không cần mật khẩu nội bộ, đồng thời giữ nguyên luồng đăng nhập email/password hiện hữu.

## Phạm vi đã chốt

- Chỉ hỗ trợ Google; nút đăng nhập chỉ xuất hiện trên trang login.
- Tài khoản khách định danh duy nhất theo email Google:
  - Email đã có trong `users` và là khách → đăng nhập luôn.
  - Chưa có → tạo tài khoản khách mới với `name`/`email` Google, `username` sinh tự động, `password = NULL`.
  - Email thuộc tài khoản admin → từ chối để tránh dùng storefront qua Google.
- Chỉ sửa cột `users.password` cho phép NULL; **không** thêm cột `google_id`, `google_token`, `google_refresh_token`, `avatar`.
- Ngoài phạm vi: đăng ký công khai, social provider khác, liên kết/đổi email nâng cao.

## Dependency

- `laravel/socialite` 5.30.1 (tương thích Laravel 10), kéo `firebase/php-jwt` v7.2.1 đã vá CVE-2025-45769.
- `doctrine/dbal` (dev) để `change()` chạy trên SQLite test.
- `StorefrontAuthController`, `CartIdentityService`, `SafeStorefrontRedirect` sẵn có.

## Task

### 13.1 Chốt contract và cấu hình Google OAuth
- [x] Cài Socialite 5.30.1 + php-jwt v7.
- [x] Thêm block `google` vào `config/services.php`.
- [x] Thêm `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` vào `.env.example`.
- [x] Xác nhận redirect URI `/auth/google/callback`.

### 13.2 Password nullable và GoogleCustomerService
- [x] Migration `2026_11_14_000020_allow_null_password_on_users_table.php`.
- [x] `GoogleCustomerService::findOrCreate` theo email, sinh username unique, chặn admin.
- [x] Set `email_verified_at`, `status = active`, `password = NULL`.
- [x] Bật native schema operations để SQLite giữ `dropColumn` native.

### 13.3 SocialAuthController, routes và merge giỏ
- [x] Route GET `login.google` / `login.google.callback` trong group `storefront.locale`.
- [x] Lưu `return_to` an toàn qua `SafeStorefrontRedirect`.
- [x] Callback: state Socialite, chặn admin/inactive, session regenerate, merge giỏ guest.
- [x] Xử lý lỗi OAuth → quay lại login kèm message.

### 13.4 Nút Google, i18n và quality gate
- [x] Nút "Đăng nhập bằng Google" chỉ trên trang login.
- [x] i18n vi/en (`google_button`, `google_failed`, `or`).
- [x] CSS `.btn--google`, `.auth-divider`.
- [x] `GoogleLoginTest` 8 tests.
- [x] Full regression.

### 13.5 Cài đặt Google OAuth trong admin
- [x] Nhóm Cài đặt `auth` với `google_login_enabled`, `google_client_id`, `google_client_secret`, `google_redirect_uri`.
- [x] Type `secret`: che khi hiển thị, không render ra HTML, redact khỏi audit.
- [x] Để trống secret = giữ giá trị cũ.
- [x] Seeder bổ sung key thiếu; `settings:check` nhất quán.

### 13.6 Bật/tắt đăng nhập Google theo settings
- [x] `GoogleOAuthService` đọc settings, fallback `GOOGLE_*` trong env.
- [x] `enabled()` yêu cầu công tắc bật + đủ client id/secret.
- [x] Route Google trả 404 và nút bị ẩn khi tắt/thiếu cấu hình.
- [x] Session regenerate + merge giỏ giữ nguyên.

## Quyết định

- ADR-81: Socialite stateful, chỉ khách storefront.
- ADR-82: định danh theo email, không thêm cột Google.
- ADR-83: bật native schema operations (`Builder::$alwaysUsesNativeSchemaOperationsIfPossible`).
- ADR-84: đăng nhập Google bật/tắt theo settings, yêu cầu đủ credential; env chỉ là fallback.
- ADR-85: credential Google OAuth lưu trong Settings như ngoại lệ có kiểm soát (secret che/redact, để trống = giữ cũ).

## Kế hoạch và kết quả test

- `php artisan test --filter=GoogleLogin`: 13 passed, 71 assertions (GoogleLoginTest 10 + GoogleLoginSettingsTest 3).
- `php artisan test --filter=SystemSetting`: 34 passed, 914 assertions.
- `php artisan test`: 453 passed, 4176 assertions.
- `php artisan settings:check`: 0 key thiếu/không thuộc registry/giá trị không hợp lệ.
- `php artisan route:list --path=auth/google`: 2 route GET đúng.
- `php artisan migrate`: `2026_11_14_000020` Ran; `SHOW COLUMNS users.password` → Null = YES.
- `node --check docs/assets/pm-board/pm-board.js`: pass sau khi cập nhật dashboard.

## Vận hành

1. Tạo OAuth Client (Web application) trên Google Cloud Console.
2. Thêm Authorized redirect URI: `https://<domain>/auth/google/callback` (và localhost khi dev).
3. Vào Admin → Cài đặt hệ thống → Đăng nhập Google: nhập Client ID, Client Secret, Redirect URI (để trống dùng mặc định) và bật công tắc.
4. (Tùy chọn) Có thể đặt `GOOGLE_*` trong `.env` làm fallback khi settings còn trống.
5. Chạy `php artisan migrate` nếu chưa chạy; chạy seeder settings nếu DB mới.

## Ghi chú

- Nếu Google OAuth lỗi, người dùng quay lại trang login với thông báo chung; form email/password vẫn hoạt động.
- Tài khoản Google không có mật khẩu nội bộ nên không đăng nhập được bằng form email/password cho tới khi đổi mật khẩu chủ động.
- Credential Google OAuth được lưu trong bảng `settings`; secret không hiển thị lại và bị redact khỏi audit, nhưng vẫn cần bảo vệ DB/backup và giới hạn quyền `settings.update`.
