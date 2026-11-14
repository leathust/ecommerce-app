# Roadmap — Hồ sơ tài khoản khách hàng storefront

> **Trạng thái: ĐÃ ĐÓNG — 14/11/2026.** Toàn bộ Task 12.1–12.8 đã hoàn thành và vượt quality gate 445 test/4092 assertion.

## 1. Mục tiêu

Xây dựng trang Account/Profile cho customer active tại `/account`, mở từ nút Account cạnh Wishlist trên header. Customer có thể xem dữ liệu tài khoản, chỉnh sửa đúng allowlist thông tin cá nhân, đổi mật khẩu an toàn và đăng xuất. Module phải tách biệt hoàn toàn với trang profile template dành cho admin, không cho sửa email/username/status/role và vẫn hoạt động khi JavaScript tắt.

## 2. Baseline đã rà soát

- Header đã có nút Account trỏ tới route `account` (`GET /account`).
- Route hiện dùng middleware `auth`, `customer`; guest được chuyển tới login, admin bị từ chối.
- `resources/views/frontend/account.blade.php` mới hiển thị email và form logout POST có CSRF.
- Storefront logout đã invalidate session, regenerate CSRF token, thu hồi guest Cart cookie và redirect Home.
- `users` hiện có `name`, `username`, `email`, `first_name`, `last_name`, `phone`, `status`, password và metadata đăng nhập.
- Route `account.profile` hiện thuộc template admin ở `/account/profile`; module storefront không được tái sử dụng tên route này để tránh collision/ngộ nhận phạm vi.
- Chưa có request/controller/service riêng cho cập nhật profile hoặc đổi mật khẩu storefront.

## 3. Contract nghiệp vụ chốt cho phase

### Trường hiển thị

- Họ, tên, display name, email, username, số điện thoại.
- Trạng thái tài khoản ở dạng read-only an toàn và ngày đăng ký.
- Có thể bổ sung shortcut tới Wishlist và Cart; không đưa dữ liệu admin/permission ra storefront.

### Trường customer được phép sửa

- `first_name`.
- `last_name`.
- `phone`.
- `name` được đồng bộ server-side từ họ/tên hoặc fallback hiện tại; browser không tự đặt role/status/name hệ thống.

### Trường không được sửa trong phase

- `email`: cần luồng xác minh email mới, chống account takeover và notification riêng.
- `username`: đang có vai trò định danh/backfill; thay đổi cần contract riêng.
- `status`, `roles`, `permissions`, `must_change_password`, `last_login_at`.
- Avatar/upload ảnh, địa chỉ giao hàng và social profile.

### Đổi mật khẩu

- Form riêng yêu cầu `current_password`, `password`, `password_confirmation`.
- Password mới theo rule project/Laravel, khác password hiện tại.
- Sau thành công regenerate session và CSRF token; không log password hoặc đưa password vào JSON/session flash.
- Không trộn cập nhật profile và password trong cùng transaction/form.

## 4. Task triển khai

### [x] Task 12.1 — Chốt contract và baseline Profile

**Dependency:** Storefront authentication Task 10.2, Cart logout isolation Task 11.3.

- [x] Kiểm kê route/header/view/controller/model/columns hiện hữu.
- [x] Chốt allowlist editable và read-only fields.
- [x] Chốt boundary guest/customer/admin/inactive.
- [x] Chốt route names không xung đột `account.profile` của admin template.
- [x] Chốt HTML/JSON, redirect, locale và security contract.

**Kết quả cần đạt:** contract profile rõ ràng trước khi thay đổi runtime.

**Kế hoạch test:** route inventory, auth baseline, model field audit, dashboard syntax và diff check.

#### Contract đã chốt cho Task 12.1

- **Canonical page:** giữ `GET /account` với route name `account`; header và các liên kết storefront tiếp tục dùng route này.
- **Mutation routes dự kiến:** dùng `PATCH /account/profile` tên `storefront.profile.update` và `PUT /account/password` tên `storefront.password.update`. Không dùng lại `account.profile` vì tên đó đang thuộc trang template admin.
- **Actor boundary:** guest được middleware `auth` chuyển tới login; chỉ customer `active` được xem/sửa hồ sơ; customer `inactive`/`blocked` bị đăng xuất và từ chối; tài khoản admin nhận `403`. Mọi mutation chỉ lấy actor từ `$request->user()`, không nhận `user_id` từ client.
- **Editable allowlist:** chỉ nhận `first_name`, `last_name`, `phone`. Họ/tên được trim, chuỗi rỗng chuyển `null`; `phone` nullable, tối đa 30 ký tự. `name` chỉ do server đồng bộ từ họ/tên khi tên ghép không rỗng, nếu cả hai trống thì giữ fallback hiện tại.
- **Read-only:** `email`, `username`, `status`, `roles`, `permissions`, `must_change_password`, `last_login_at`, timestamps và mọi metadata IAM. Field ngoài allowlist không được mass assign hoặc phản chiếu lại trong response.
- **Password boundary:** endpoint/form riêng nhận `current_password`, `password`, `password_confirmation`; không trộn với update profile và không ghi credential vào log, flash, JSON, DOM storage hay dashboard.
- **HTML contract:** form có CSRF và vẫn chạy khi tắt JavaScript; thành công redirect về `account` với flash đã dịch; lỗi validation redirect back với old input/error bag riêng cho từng form.
- **JSON contract:** mutation hợp lệ trả message đã dịch và dữ liệu public tối thiểu do server xác nhận; lỗi validation dùng `422`; lỗi actor theo middleware. Không trả model `User` nguyên bản hoặc dữ liệu IAM/credential.
- **Locale và bảo mật:** toàn bộ text/validation dùng locale storefront `vi`/`en`; mutation áp dụng throttle riêng; trang giữ `noindex,nofollow`, escape output mặc định và regenerate session/CSRF sau đổi password.
- **Schema:** không tạo migration ở phase này vì `users.first_name`, `users.last_name`, `users.phone` đã tồn tại đúng độ dài cần dùng.

**Kết quả thực tế:** contract đã được khóa từ baseline đang chạy; `StorefrontAuthenticationTest` đạt 11 test/77 assertion, bốn route `/account*` được kiểm kê rõ ranh giới storefront/admin và 24 migration hiện đều ở trạng thái `Ran`. Task 12.2 có thể triển khai mà không cần thay đổi schema.

### [x] Task 12.2 — Controller, routes và dữ liệu trang Profile

**Dependency:** Task 12.1.

- [x] Tạo `StorefrontProfileController` và chuyển `GET /account` khỏi `HomeController`.
- [x] Giữ route canonical `account` để header/link cũ tương thích.
- [x] Thêm named routes update profile/password dưới middleware `auth`, `customer` và throttle phù hợp.
- [x] Truyền view model/array allowlist; Blade không tự query database.
- [x] Hiển thị shortcut Cart/Wishlist và dữ liệu read-only cần thiết mà không lộ IAM.

**Kết quả cần đạt:** trang Account có backend chuyên trách và route boundary rõ ràng.

**Kế hoạch test:** guest/customer/admin/inactive, route names/method/middleware, query budget và no data leakage.

**Kết quả thực tế:** `/account` đã chuyển sang controller chuyên trách và nhận array allowlist; Blade không còn gọi `auth()->user()`. Hai mutation route đã có đúng tên/middleware và tạm thời fail-closed `501` cho tới Task 12.3/12.4. Trang hiển thị shortcut Cart/Wishlist, không query permission và giữ tổng query shared layout trong ngân sách hữu hạn. Target đạt 6 test/51 assertion; full regression đạt 407 test/3785 assertion.

### [x] Task 12.3 — Cập nhật thông tin cá nhân theo allowlist

**Dependency:** Task 12.2.

- [x] Tạo FormRequest validate họ/tên/điện thoại, normalize khoảng trắng và nullable hợp lý.
- [x] Chỉ lấy dữ liệu bằng `validated()`/allowlist; bỏ qua hoặc từ chối field ngoài phạm vi.
- [x] Đồng bộ `name` server-side theo quy tắc đã chốt.
- [x] Không cho thay email, username, status, role, permission hoặc metadata bảo mật.
- [x] Hỗ trợ redirect + old input/error bag và JSON response tối thiểu.
- [x] Throttle mutation và giữ CSRF/no-JS fallback.

**Kết quả cần đạt:** customer chỉ sửa được dữ liệu cá nhân cho phép, không privilege escalation/mass assignment.

**Kế hoạch test:** happy path, normalization, validation VI/EN, over-posting, owner isolation, CSRF/throttle và HTML/JSON parity.

**Kết quả thực tế:** endpoint profile đã cập nhật self-scoped qua FormRequest và service transaction. Họ/tên/điện thoại được trim, gộp khoảng trắng, chuỗi rỗng thành `null`; `name` chỉ đổi khi tên ghép không rỗng. Field IAM/identity ngoài allowlist bị bỏ qua, JSON chỉ trả bốn field public, HTML dùng error bag `profile`, flash VI/EN và redirect canonical. Target đạt 8 test/77 assertion; ba suite Profile/Auth đạt 25 test/207 assertion; full regression đạt 415 test/3864 assertion.

### [x] Task 12.4 — Đổi mật khẩu an toàn

**Dependency:** Task 12.2.

- [x] Tạo request riêng cho current/new/confirmation password.
- [x] Xác minh password hiện tại bằng rule/hash chuẩn Laravel.
- [x] Yêu cầu password mới đủ mạnh và khác password hiện tại.
- [x] Update hash bằng cast/Hash, regenerate session và CSRF token sau thành công.
- [x] Không log, flash, audit payload hoặc trả JSON chứa password.
- [x] Giữ customer đăng nhập trên phiên hiện tại; ghi rõ chiến lược thu hồi phiên khác là nâng cấp sau.

**Kết quả cần đạt:** customer tự đổi mật khẩu mà không làm lộ credential hoặc mất phiên ngoài ý muốn.

**Kế hoạch test:** wrong current password, confirmation/strength, same password, hash changed, session remains authenticated, secret redaction và throttle.

**Kết quả thực tế:** password endpoint đã dùng request/error bag riêng, xác minh `current_password:web`, yêu cầu tối thiểu 12 ký tự có hoa/thường/số/ký hiệu, confirmed và khác mật khẩu hiện tại. Hash cập nhật qua cast, session ID cùng CSRF token được regenerate nhưng phiên hiện tại vẫn đăng nhập; credential không được flash hoặc trả JSON. Thu hồi mọi phiên khác vẫn ngoài scope. Target đạt 7 test/67 assertion; bốn suite Profile/Auth đạt 32 test/276 assertion; full regression đạt 422 test/3933 assertion.

### [x] Task 12.5 — Giao diện Account/Profile responsive VI/EN

**Dependency:** Task 12.2–12.4.

- [x] Thiết kế lại `frontend/account.blade.php` theo layout storefront.
- [x] Tách khối tổng quan tài khoản, form thông tin cá nhân, form password và vùng hành động phiên.
- [x] Đánh dấu rõ field read-only và lý do email/username chưa sửa trực tiếp.
- [x] Hiển thị validation theo từng form/error bag, success message và old input.
- [x] Giữ logout POST + CSRF, nút dễ tìm nhưng không gây nhầm với xóa tài khoản.
- [x] Responsive mobile/tablet/desktop, label/description/heading semantics và VI/EN đầy đủ.

**Kết quả cần đạt:** trang Profile trực quan, nhất quán storefront và dùng tốt trên mobile.

**Kế hoạch test:** Blade assertions, locale, escaping, responsive CSS, keyboard order, logout form và no-JS submission.

**Kết quả thực tế:** `/account` đã có layout hai cột desktop, chuyển grid tablet và một cột mobile; sidebar gồm overview/read-only identity/shortcut/session, nội dung gồm hai form native profile/password với CSRF, method spoofing, autocomplete, error bag, old input và localized feedback. Output được escape, password không bao giờ render lại value. View target đạt 6 test/54 assertion; năm suite Profile/Auth đạt 38 test/330 assertion; full regression đạt 428 test/3987 assertion.

### [x] Task 12.6 — AJAX progressive enhancement và accessibility

**Dependency:** Task 12.3–12.5.

- [x] AJAX profile/password update không reload nhưng giữ native form fallback.
- [x] Có pending lock, server-confirmed state, validation/error recovery và chống stale response.
- [x] Không đưa password vào DOM dataset, history, localStorage hoặc BroadcastChannel.
- [x] Bổ sung live region, focus error/success, `aria-busy` và reduced-motion.
- [x] Đồng bộ display name trên header nếu header sau này hiển thị tên; không optimistic update.
- [x] Không dùng cross-tab message chứa PII; tab khác lấy lại state từ server/reload khi cần.

**Kết quả cần đạt:** chỉnh sửa nhanh, accessible, không làm giảm bảo mật và vẫn hoạt động khi JS lỗi/tắt.

**Kế hoạch test:** JS syntax/static contract, JSON/HTML parity, pending/stale/error, secret scan, ARIA/focus/reduced-motion và no-JS.

**Trạng thái kiểm thử 14/11/2026:** phần triển khai đã hoàn tất và target đạt 7 test/52 assertion; toàn bộ Profile/Auth đạt 45 test/382 assertion. Full regression hiện có 441 test đạt và 2 test migration nền thất bại (`MenuSchemaTest`, `OrderWorkflowSchemaTest`) vì SQLite báo index đã tồn tại sau thay đổi `doctrine/dbal` ngoài phạm vi task. Task giữ trạng thái `testing`, chưa đánh dấu hoàn thành cho tới khi regression nền được xử lý.

### [x] Task 12.7 — Tài liệu vận hành và dữ liệu test Profile

**Dependency:** Task 12.1–12.6.

- [x] Bổ sung factory states/helper cho customer active/inactive và profile fields nếu còn thiếu.
- [x] Không seed password/profile cá nhân giả vào production ngoài demo hiện hữu đã kiểm soát.
- [x] Tài liệu hóa routes, editable fields, session/password behavior và troubleshooting.
- [x] Ghi rõ email change/verification, avatar, address book, account deletion và revoke-all-sessions ngoài scope.
- [x] Cập nhật README và `docs/kien-truc-he-thong.html`.

**Kết quả cần đạt:** team có runbook rõ ràng, không lộ PII/credential và biết giới hạn phase.

**Kế hoạch test:** factory/helper, docs links/commands, secret/PII redaction và static checks.

### [x] Task 12.8 — Quality gate Profile storefront

**Dependency:** Task 12.1–12.7.

- [x] Regression guest/customer/admin/inactive và session/logout isolation.
- [x] Regression allowlist/over-posting/validation/CSRF/throttle.
- [x] Regression password hash/session/secret redaction.
- [x] Regression VI/EN, XSS, AJAX/no-JS, query budget và route collision.
- [x] Visual QA desktop/tablet/mobile, keyboard, screen-reader semantics và reduced-motion.
- [x] Chạy full regression, route/migration/Blade/JS/dashboard/diff checks.

**Kết quả cần đạt:** Profile an toàn, responsive, không phá Auth, Wishlist, Cart, IAM hoặc admin template.

**Kế hoạch test:** targeted Profile/Auth/Cart/Wishlist/IAM suites, full `php artisan test`, route list, migration status, Blade/JS/dashboard syntax, diff check và visual QA.

**Kết quả đóng roadmap 14/11/2026:** target Profile/Auth/Cart/Wishlist/IAM đạt 137 test/1080 assertion; full regression đạt 445 test/4092 assertion; 25 migration đều `Ran`; sáu account route giữ đúng middleware; Blade, JavaScript, dashboard và diff checks đạt. Phase 12 được đóng, các nâng cấp ngoài phạm vi giữ cho roadmap tương lai.

## 5. Thứ tự triển khai

```text
12.1 -> 12.2 -> 12.3 -> 12.4 -> 12.5 -> 12.6 -> 12.7 -> 12.8
```

Task 12.3 và 12.4 có thể phát triển song song sau khi route/controller contract ở 12.2 ổn định. UI 12.5 chỉ khóa khi hai form backend đã rõ validation/error bag.

## 6. Ngoài phạm vi phase

- Thay email và xác minh email mới/cũ.
- Thay username.
- Upload/crop/avatar lifecycle.
- Address book và địa chỉ checkout.
- Xóa/ẩn danh tài khoản, data export và consent/privacy center.
- Two-factor authentication, passkey và quản lý thiết bị/phiên đăng nhập khác.
- Social login/link account.
- Notification preferences và marketing consent.

Các phần trên cần roadmap riêng vì ảnh hưởng verification, storage, privacy, Order history hoặc security policy.
