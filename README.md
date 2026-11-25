# Ecommerce App

> Hồ sơ customer storefront: xem contract, bảo mật, test và troubleshooting tại [`docs/profile-operations.md`](docs/profile-operations.md).

Ứng dụng thương mại điện tử xây dựng bằng Laravel 10, có giao diện khách hàng, đăng nhập quản trị, phân quyền theo role/permission và module liên hệ khách hàng. Hệ thống đang trong quá trình phát triển; nhiều trang thương mại điện tử hiện là giao diện mẫu.

## 1. Công nghệ và yêu cầu

- PHP 8.1 trở lên; môi trường phát triển đã sử dụng PHP 8.2.
- Laravel 10, Eloquent ORM, Blade.
- MySQL/MariaDB; có thể quản lý dữ liệu bằng phpMyAdmin.
- Apache 2.4/XAMPP với `mod_rewrite`, cho phép `.htaccess` qua `AllowOverride`.
- Composer.
- Bootstrap 5 và JavaScript thuần cho giao diện admin.
- PHPUnit 10; cần extension PDO SQLite để chạy test cách ly trong bộ nhớ.

Assets hiện được phục vụ trực tiếp từ `public/assets`, không cần chạy Vite để sử dụng các trang template hiện tại.

## 2. Chức năng hiện có

### Quản trị và phân quyền

- Đăng nhập bằng email/mật khẩu, ghi nhớ đăng nhập và đăng xuất bằng POST có CSRF.
- Chỉ tài khoản `active` có role quản trị được vào admin.
- Giới hạn đăng nhập sai: 5 lần trong 60 giây theo email và IP.
- Tạo lại session sau đăng nhập; hủy session khi logout hoặc khi tài khoản bị khóa.
- RBAC qua `users → user_roles → roles → role_permissions → permissions`.
- Middleware `auth`, `active`, `admin`, `permission` bảo vệ route.
- Laravel Gate và `@can` điều khiển quyền trên giao diện.
- `super_admin` được phép vượt qua kiểm tra permission.
- Menu desktop/mobile hiển thị theo quyền, header hiển thị người đăng nhập.

### Liên hệ khách hàng

- Khách gửi form liên hệ không cần đăng nhập.
- Lưu họ tên, email, điện thoại, chủ đề, mã đơn hàng và nội dung vào MySQL.
- Validate dữ liệu, CSRF, honeypot và giới hạn 5 lượt gửi/phút/IP.
- Admin xem danh sách, tìm kiếm, lọc theo trạng thái/chủ đề, phân trang 15 bản ghi.
- Cập nhật trạng thái `new`, `processing`, `resolved`, `closed`.
- Nút Trả lời mở modal nhập tiêu đề/nội dung và gửi email đến địa chỉ trong contact.
- Người gửi email lấy từ cấu hình `MAIL_*`; route reply giới hạn 10 request/phút.
- Nội dung khách hàng và nội dung email được escape khi render.

### Các phần đang ở mức giao diện

Một số trang storefront như theo dõi đơn hàng, đăng ký và quên mật khẩu vẫn chưa đại diện cho luồng thương mại điện tử hoàn chỉnh. Cart và Wishlist đã dùng backend thật, nhưng chưa có checkout, payment gateway, stock reservation, upload file liên hệ hoặc gửi thông báo tự động khi khách tạo contact.

### Wishlist storefront

- Customer active đã đăng nhập có thể thêm/xóa sản phẩm yêu thích và xem danh sách phân trang.
- Dữ liệu scope theo owner; guest, admin và customer inactive không được truy cập.
- Nút tim dùng AJAX server-confirmed, cập nhật badge và đồng bộ tab hỗ trợ `BroadcastChannel`; form CSRF vẫn hoạt động khi tắt JavaScript.
- Product/category inactive bị ẩn khỏi danh sách và badge nhưng row được giữ; xóa user/product sẽ cascade row Wishlist.
- Factory test không được gọi từ `DatabaseSeeder`; project không tự seed dữ liệu Wishlist cá nhân.

Chi tiết deploy, rollback và troubleshooting: [docs/wishlist-operations.md](docs/wishlist-operations.md).

### Cart storefront

- Guest dùng cookie token bảo mật (database chỉ lưu SHA-256); customer active có một Cart theo `user_id` và Cart guest được merge khi đăng nhập.
- Add/update/remove/clear chạy AJAX server-confirmed, đồng bộ badge, totals và empty state; form CSRF vẫn hoạt động khi tắt JavaScript.
- Cart dùng giá/tồn kho catalog hiện tại, cap mỗi dòng theo `min(stock, 99)` và không giữ hoặc trừ tồn kho.
- Product/category inactive hoặc hết hàng không được tính vào totals nhưng dòng cũ vẫn có thể xóa.
- Factory test không được gọi từ `DatabaseSeeder`; project không tự seed Cart cá nhân hoặc chạy cleanup guest Cart mặc định.

Chi tiết token, merge, deploy, rollback, cleanup và troubleshooting: [docs/cart-operations.md](docs/cart-operations.md).

### Thanh toán SePay

- Checkout SePay dùng snapshot payment/order, QR và access token guest; webhook HMAC/API key, matching exact, Pusher realtime, Redis email queue và API reconciliation đều có idempotency.
- Sandbox/live tách profile bằng `SEPAY_MODE` và các nhóm `SEPAY_SANDBOX_*`/`SEPAY_LIVE_*`; không đưa credential vào source, log hoặc dashboard.
- `SEPAY_ENABLED=false` chỉ chặn checkout mới. Webhook, expiry và reconciliation cho attempt đang tồn tại vẫn được vận hành để recovery.
- Quy trình sandbox E2E, worker/scheduler, Cloudflare HTTPS, chuyển Live, rotation và rollback nằm tại [docs/sepay-payment-operations.md](docs/sepay-payment-operations.md).

## 3. Cấu trúc thư mục

```text
app/
  Http/Controllers/       Controller client và admin
  Http/Middleware/       Authentication, trạng thái, role, permission
  Http/Requests/         Validation đăng nhập và liên hệ
  Mail/                  ContactReplyMail
  Models/                Model và quan hệ Eloquent
  Providers/             Gate, rate limiter, cấu hình phân trang
config/
  admin.php              Thông tin seed super admin, giới hạn đăng nhập
  mail.php               Mail transport và người gửi
database/
  migrations/            Schema và các thay đổi bổ sung
  seeders/               Roles, permissions, super admin
docs/
  Ecommerce Diagram.sql  Schema tham khảo ban đầu
  Admin Template/        Template HTML admin gốc
public/assets/
  admin/                 CSS, JS, fonts, images admin
  frontend/              Assets giao diện khách hàng
resources/views/
  admin/                 Layout, dashboard, module liên hệ
  frontend/              Giao diện khách hàng
  emails/                Template email trả lời liên hệ
routes/web.php           Routes website
tests/Feature/           Test đăng nhập, phân quyền, schema và contact
```

## 4. Cài đặt lần đầu với XAMPP

Đặt project tại `C:\xampp\htdocs\ecommerce-app`, bật Apache và MySQL trong XAMPP. Mở terminal tại thư mục project:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Chỉ copy `.env.example` khi chưa có `.env`. Không tạo lại `APP_KEY` của hệ thống đang chạy vì có thể ảnh hưởng dữ liệu mã hóa và phiên đăng nhập.

Tạo database `ecommerce_app` trong phpMyAdmin, sau đó sửa `.env` theo MySQL thực tế:

```dotenv
APP_NAME="Ecommerce App"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/ecommerce-app

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=ecommerce_app
DB_USERNAME=root
DB_PASSWORD=

ADMIN_NAME="System Administrator"
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD="THAY_BANG_MAT_KHAU_MANH_CUA_BAN"
```

`3307` là cổng đã dùng trong môi trường phát triển này; đổi thành `3306` hoặc cổng đang cấu hình trong XAMPP nếu cần. `phpMyAdmin` là công cụ quản lý; ứng dụng kết nối trực tiếp MySQL qua các biến `DB_*`.

Áp dụng cấu hình, tạo bảng và dữ liệu khởi tạo:

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan optimize:clear
```

Đăng nhập bằng **email** và mật khẩu bạn đặt trong `ADMIN_EMAIL`, `ADMIN_PASSWORD`. Không có mật khẩu mặc định được ghi trong tài liệu này.

## 5. URL và cấu hình Apache

| Mục đích | URL local |
| --- | --- |
| Trang chủ | http://localhost/ecommerce-app/ |
| Gửi liên hệ | http://localhost/ecommerce-app/contact |
| Đăng nhập admin | http://localhost/ecommerce-app/admin |
| Dashboard | http://localhost/ecommerce-app/admin/dashboard |
| Danh sách liên hệ | http://localhost/ecommerce-app/admin/contacts |

Project hiện dùng `.htaccess` tại thư mục gốc để rewrite vào `public`, giúp URL không chứa `/public`. Assets vẫn nằm trong `public/assets` và được gọi bằng `asset('assets/...')`.

- `.htaccess` gốc rewrite `/storage/*` vào đúng Junction `public/storage`, sau đó chặn truy cập các thư mục source và file nhạy cảm, tắt directory listing và thêm security headers. Rule public storage phải đứng trước rule chặn thư mục `storage`.
- `public/.htaccess` chuyển request trực tiếp có `/public` về URL sạch.
- `public/assets/.htaccess` chặn thực thi script phía server trong assets.
- `public/index.php` có xử lý base path cho cách triển khai dưới thư mục con.
- Nếu đổi tên thư mục `ecommerce-app`, cần cập nhật `APP_URL` và rule redirect `/ecommerce-app/` trong `public/.htaccess`.

Khi triển khai domain thật, nên cấu hình DocumentRoot vào `public`, rà lại các rule dành cho đường dẫn local, sử dụng HTTPS và `APP_DEBUG=false`.

## 6. Database và migration

Schema nghiệp vụ gồm users, roles, permissions, categories, brands, products, product_images, carts, cart_items, wishlists, orders, order_items, contacts, faqs, menus và settings; có thêm các bảng phục vụ Laravel.

Bảng `users` giữ các cột chuẩn Laravel như `password`, `name`, `remember_token`, `email_verified_at`. Trường `password` tương ứng mật khẩu hash trong sơ đồ SQL; ứng dụng không dùng cột `password_hash` riêng. Migration bổ sung username, họ tên, điện thoại, trạng thái và cấp username `user-{id}` cho bản ghi cũ.

Bảng `contacts` đã được bổ sung `order_code` bằng migration riêng. Với database đang có dữ liệu, chỉ chạy:

```powershell
php artisan migrate:status
php artisan migrate
```

Laravel bỏ qua migration đã chạy và chỉ áp dụng migration mới. Không import lại file SQL sơ đồ lên database đã được migrations quản lý. Không dùng `migrate:fresh`, `migrate:refresh` hoặc `migrate:reset` nếu cần giữ dữ liệu.

## 7. Seeder và phân quyền

`DatabaseSeeder` gọi các seeder RBAC, super admin, cài đặt hệ thống và dữ liệu mẫu nghiệp vụ. Trên database đang vận hành, nên gọi đúng seeder cần thiết thay vì chạy lại toàn bộ dữ liệu mẫu.

```powershell
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=SystemSettingSeeder
php artisan db:seed --class=MenuSeeder
```

`SystemSettingSeeder` chỉ chèn key registry còn thiếu bằng `insertOrIgnore`, không cập nhật hoặc xóa giá trị admin đã chỉnh. Giá trị mặc định lấy từ allowlist `config/system_settings.php`; seeder không quét hay sao chép credential từ `.env`.

### Vận hành Menu động

Trên database hiện hữu, dùng quy trình không phá hủy sau:

```powershell
php artisan migrate:status
php artisan menus:check
php artisan migrate
php artisan db:seed --class=MenuSeeder
php artisan optimize:clear
php artisan menus:check
```

`MenuSeeder` đọc cây mặc định đệ quy từ `config/menus.php` và xử lý độc lập từng location `header`/`footer`. Cây mặc định có thể sâu tối đa `menus.max_depth` (hiện là 4); nếu config vượt giới hạn, toàn bộ transaction bị rollback thay vì seed một phần. Seeder chỉ backfill location hoàn toàn trống; nếu location đã có dù chỉ một item — kể cả cây 2 cấp cũ hoặc node cấp 3–4 — toàn bộ location đó được giữ nguyên. Vì vậy chạy lại không tạo trùng và không ghi đè nhãn, URL, trạng thái hay thứ tự do admin đã chỉnh. Khóa tự nhiên nội bộ gồm location, parent, loại link, đích link và cặp nhãn mặc định; hai nhãn khác nhau vẫn có thể cùng trỏ tới một route.

`php artisan menus:check` là kiểm tra chỉ đọc cho toàn bộ adjacency list. Lệnh trả exit code `1` nếu phát hiện cycle, parent không tồn tại, parent khác location hoặc depth vượt giới hạn; lệnh không tự sửa, di chuyển hay xóa menu. Hãy chạy trước deploy để phát hiện dữ liệu legacy không hợp lệ và chạy lại sau migrate/seed/cache clear. Snapshot/backup database vẫn là bước bắt buộc trước khi deploy production.

`MenuSeeder` được gọi trong `DatabaseSeeder` vì đây là dữ liệu điều hướng bắt buộc, không phải dữ liệu demo. Tuy nhiên `DatabaseSeeder` còn gọi customer/product/order demo, nên trên production hoặc database đang vận hành hãy chạy riêng `--class=MenuSeeder` như trên, không chạy toàn bộ `php artisan db:seed`.

Fallback hard-code mặc định đã tắt. Nếu deploy gặp sự cố và cả location cần dùng đang trống, có thể tạm đặt `MENU_FALLBACK_ENABLED=true`, chạy `php artisan config:clear`, sau đó backfill/khôi phục dữ liệu. Khi menu database đã hiển thị đúng, đặt lại `false` và chạy `php artisan optimize:clear`. Không xóa bảng hoặc dùng `migrate:fresh` để khôi phục menu.

#### Rollback code Menu 4 cấp

- Không rollback migration và không xóa các hàng cấp 3–4: nâng cấp 4 cấp không thêm schema mới, dữ liệu vẫn dùng `menus.parent_id` hiện hữu.
- Sao lưu database, đưa code về release cần rollback rồi chạy `php artisan optimize:clear`. Không chạy `MenuSeeder` với mục đích “hạ cấp” vì seeder không sửa location đã có dữ liệu.
- Release cũ chỉ hiểu 2 cấp có thể không hiển thị node sâu đầy đủ. Giữ nguyên dữ liệu, dùng maintenance window hoặc bật fallback khẩn cấp nếu location trống, sau đó triển khai lại release hỗ trợ 4 cấp; không kéo node sâu lên root hoặc xóa chúng để chữa cháy.
- Sau khi triển khai lại code 4 cấp, chạy `php artisan menus:check` và kiểm tra desktop/mobile/footer. Nếu lệnh báo lỗi, sửa cấu trúc qua admin theo từng branch và chạy lại đến khi exit code bằng `0`.

### Vận hành cài đặt hệ thống

Quy trình an toàn cho database hiện hữu:

```powershell
php artisan migrate:status
php artisan settings:check
php artisan db:seed --class=SystemSettingSeeder
php artisan settings:check
php artisan storage:link
php artisan optimize:clear
```

`settings:check` mặc định chỉ đọc và trả exit code khác `0` nếu có key thiếu/lạ, giá trị sai rule, ảnh đang tham chiếu nhưng bị mất hoặc file trong thư mục managed không còn được tham chiếu. Có thể dùng `php artisan settings:check --fix-missing` để chỉ bổ sung defaults còn thiếu. Lệnh không tự sửa giá trị sai, không xóa key lạ và không xóa ảnh mồ côi; cần kiểm tra từng mục trước khi xử lý thủ công.

Không cần migration bổ sung cho settings: `setting_key` đã có unique index phục vụ lookup và chống trùng; `updated_by` cùng `updated_at` đủ cho metadata cập nhật. Khi rollback một bản phát hành, sao lưu database/storage trước, đưa code về phiên bản tương thích rồi chạy lại `settings:check`; không dùng `migrate:fresh`. Nếu registry cũ chưa biết key mới, key đó chỉ được báo là “không thuộc registry” và vẫn được giữ nguyên.

Ảnh logo/favicon/OG nằm trên disk `public` dưới `system-settings/*`; symlink/Junction `public/storage` phải tồn tại để trình duyệt truy cập. Với cách bỏ `/public` hiện tại, `.htaccess` gốc ánh xạ URL sạch `/storage/*` sang `public/storage/*` trước khi áp dụng rule chặn private `storage`. Sau khi đổi registry/config hoặc gặp cache cũ, dùng `php artisan optimize:clear`; thao tác lưu trong admin và seeder đều tự xóa cache typed settings.

`RolePermissionSeeder` tạo/cập nhật 5 role và 29 permission. Seeder đồng bộ lại quyền của các role mẫu bằng `sync`, vì vậy chạy lại sẽ thay đổi các chỉnh sửa quyền thủ công trên những role này theo ma trận trong seeder.

`AdminUserSeeder` dùng `ADMIN_*` qua `config/admin.php`, tìm user theo email rồi tạo hoặc cập nhật. Chạy lại sẽ đặt lại mật khẩu, username và trạng thái active của user tương ứng theo cấu hình hiện tại. Seeder yêu cầu `ADMIN_PASSWORD` có giá trị và role `super_admin` đã tồn tại.

| Role | Quyền khởi tạo |
| --- | --- |
| `super_admin` | Toàn quyền, bao gồm permission mới |
| `admin` | Toàn bộ permission được khai báo trong seeder |
| `catalog_manager` | Dashboard, sản phẩm, danh mục, thương hiệu |
| `order_manager` | Dashboard, xem/cập nhật/hủy đơn, xem khách hàng |
| `customer_support` | Dashboard, xem đơn/khách hàng, xem/cập nhật liên hệ |

Các quyền module hiện dùng:

| Module/hành động | Permission |
| --- | --- |
| Dashboard, trang template | `dashboard.view` |
| Xem sản phẩm | `products.view` |
| Xem đơn hàng | `orders.view` |
| Xem khách hàng | `customers.view` |
| Xem liên hệ | `contacts.view` |
| Cập nhật trạng thái/trả lời contact | `contacts.update` |

Ví dụ sử dụng:

```php
$user->isActive();
$user->isAdmin();
$user->hasRole('customer_support');
$user->hasPermission('contacts.update');
User::role('order_manager')->get();
User::permission('orders.view')->get();
```

```blade
@can('contacts.update')
    <!-- Thao tác dành cho người có quyền -->
@endcan
```

`permission:a|b` chấp nhận một trong các quyền. Muốn bắt buộc cả hai, gắn hai middleware permission riêng. Các helper quyền kiểm tra role/permission; trạng thái hoạt động và khả năng vào admin được bảo vệ thêm bằng middleware `active`, `admin`.

## 8. Gửi email trả lời contact

Sửa `.env` bằng thông tin SMTP của nhà cung cấp:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=support@your-domain.com
MAIL_PASSWORD="YOUR_SMTP_PASSWORD"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=support@your-domain.com
MAIL_FROM_NAME="Customer Support"
```

Sau khi sửa:

```powershell
php artisan config:clear
```

Trong admin, mở Liên hệ → Trả lời → nhập tiêu đề/nội dung → Gửi email. Người nhận lấy từ bản ghi contact, không lấy từ email do trình duyệt gửi lên. Người gửi lấy từ `MAIL_FROM_*`.

Email hiện gửi đồng bộ qua `Mail::send`, chưa cần queue worker. Phản hồi chưa được lưu thành lịch sử trong database và không tự đổi trạng thái contact. SMTP chấp nhận thư không đồng nghĩa chắc chắn thư vào Inbox; kiểm tra Spam khi cần.

`.env.example` dùng Mailpit (`mailpit:1025`) để bắt email local. Host `mailpit` cần dịch vụ/DNS tương ứng; khi chạy Mailpit trực tiếp trên Windows thường cần dùng `127.0.0.1` và đúng cổng của dịch vụ. Mailpit không gửi thư tới hộp thư thật. Có thể dùng `MAIL_MAILER=log` để ghi email thử vào log, tránh gửi ra ngoài.

## 9. Routes quan trọng

| Method | Path | Tên route |
| --- | --- | --- |
| GET | `/contact` | `contact` |
| POST | `/contact` | `contact.store` |
| GET | `/admin` | `admin.login` |
| POST | `/admin/login` | `admin.login.submit` |
| POST | `/admin/logout` | `admin.logout` |
| GET | `/admin/dashboard` | `admin.dashboard` |
| GET | `/admin/contacts` | `admin.contacts.index` |
| PATCH | `/admin/contacts/{contact}/status` | `admin.contacts.status.update` |
| POST | `/admin/contacts/{contact}/reply` | `admin.contacts.reply` |

Các path trong bảng là path Laravel; khi dùng XAMPP hiện tại, thêm tiền tố `/ecommerce-app`. Xem đầy đủ bằng `php artisan route:list`.

## 10. Kiểm thử

```powershell
php artisan test
php artisan test --filter=ContactFeatureTest
```

`phpunit.xml` ép `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Tests dùng database trong bộ nhớ và mail giả/array, không dùng MySQL của website. Giữ cấu hình cách ly này khi sử dụng `RefreshDatabase`; không đổi test sang database chứa dữ liệu thật. Nếu từng cache config, chạy `php artisan config:clear` trước khi test.

Các nhóm test hiện có kiểm tra schema, đăng nhập, trạng thái tài khoản, role/permission, routes, gửi contact, lọc/phân trang, cập nhật trạng thái và gửi reply bằng mail giả.

Kiểm tra thủ công:

1. Gửi contact với email của bạn từ `/contact`.
2. Đăng nhập `/admin` bằng tài khoản đã seed.
3. Mở Liên hệ, tìm bản ghi và kiểm tra bộ lọc/phân trang.
4. Đổi trạng thái, tải lại để xác nhận đã lưu.
5. Mở Trả lời, gửi nội dung và kiểm tra Inbox/Spam hoặc Mailpit.
6. Thử role không có `contacts.view`: menu phải ẩn, truy cập trực tiếp nhận 403.

## 11. Xử lý lỗi thường gặp

| Hiện tượng | Cách kiểm tra |
| --- | --- |
| Không kết nối MySQL | Bật MySQL; kiểm tra `DB_HOST`, `DB_PORT`, tên database và tài khoản |
| Không đăng nhập được | Kiểm tra user tồn tại, mật khẩu hash, status active và role quản trị; chỉ seed lại admin khi muốn cập nhật tài khoản theo `ADMIN_*` |
| HTTP 403 trong admin | Kiểm tra role, permission và trạng thái tài khoản |
| HTTP 419 | Tải lại trang lấy CSRF/session mới; kiểm tra cookie và sử dụng nhất quán hostname |
| CSS/JS 404 hoặc route 404 | Kiểm tra Apache rewrite, `.htaccess`, `APP_URL`, và đường dẫn thư mục project |
| Modal bị backdrop che | Modal cần nằm ngoài container nội dung; layout hiện dùng `@stack('modals')` trước các script |
| SMTP lỗi | Kiểm tra host/port/TLS, tài khoản SMTP, người gửi và log; không chia sẻ mật khẩu khi gửi log |
| Thay `.env` nhưng chưa có tác dụng | Chạy `php artisan config:clear` |
| Blade cũ vẫn hiển thị | Chạy `php artisan view:clear`, sau đó Ctrl + F5 |

Log ứng dụng thường ở `storage/logs/laravel.log` khi dùng cấu hình logging mặc định. Không commit `.env`, mật khẩu SMTP, APP_KEY hoặc dữ liệu khách hàng vào Git.
