# Roadmap hoàn thiện Wishlist Storefront

## 1. Mục tiêu

Xây dựng Wishlist thật cho website khách hàng trên Laravel 10: khách hàng đăng nhập có thể thêm, xem và bỏ sản phẩm yêu thích từ trang chủ, Shop và chi tiết sản phẩm; header hiển thị số lượng chính xác; giao diện hỗ trợ VI/EN, responsive, accessibility và AJAX progressive enhancement nhưng vẫn hoạt động khi tắt JavaScript.

Wishlist phải dùng bảng `wishlists` hiện có, không lưu dữ liệu nghiệp vụ chính trong localStorage và không cho phép người dùng thao tác lên Wishlist của tài khoản khác.

## 2. Hiện trạng đã rà soát

- Migration `2026_10_22_000070_create_wishlists_table.php` đã có `id`, `user_id`, `product_id`, `created_at`, unique `(user_id, product_id)` và cascade FK.
- Model `Wishlist` đã tồn tại; `User` có `wishlistItems`, `wishlistProducts`; `Product` có `wishlistItems`.
- Header đã có icon Wishlist nhưng đang dùng `href="#"`, badge đang là dữ liệu giao diện mẫu.
- Chưa có route, controller, Form Request, service, trang danh sách hoặc feature test cho Wishlist.
- Product card dùng chung đã có ở Homepage/Shop; Product detail đã dùng binding chỉ resolve product published.
- Storefront chưa có customer authentication hoàn chỉnh. `/pages/login` và `/pages/register` mới là template; admin authentication không được tái sử dụng làm URL đăng nhập khách hàng.
- Middleware `auth`/`active` hiện được xây dựng quanh admin flow, cần chốt redirect và hành vi riêng cho storefront trước khi bảo vệ Wishlist.

## 3. Contract nghiệp vụ đã chốt tại Task 10.1

### 3.1 Chủ sở hữu và xác thực

- Wishlist chỉ thuộc về user đã đăng nhập và đang `active`.
- User hợp lệ phải thỏa đồng thời `isActive()` và `isCustomer()`; tài khoản có role `is_admin = true` không được dùng Wishlist dù đang có session trên web guard.
- Guest vẫn xem được catalog; khi bấm yêu thích sẽ được đưa tới đăng nhập khách hàng và quay lại URL an toàn sau đăng nhập.
- Không dùng admin login cho khách hàng, không cho admin session ngầm trở thành customer Wishlist nếu tài khoản không thuộc phạm vi khách hàng.
- Task 10.2 phải cung cấp middleware customer riêng và redirect storefront riêng; không tái sử dụng hành vi redirect `/admin` hiện có của `EnsureUserIsActive`.
- Không triển khai guest Wishlist/localStorage hoặc merge guest data trong phase này; có thể mở phase riêng sau khi có yêu cầu sản phẩm rõ ràng.

### 3.2 Quy tắc sản phẩm

- Chỉ thêm product đang published theo contract Storefront hiện có.
- Product bị inactive sau khi đã lưu không hiển thị trên Wishlist công khai nhưng bản ghi có thể được giữ để phục hồi khi product active lại.
- Product bị xóa sẽ cascade xóa wishlist item theo FK hiện có.
- Add là idempotent: bấm lặp không tạo duplicate; remove lặp không gây lỗi 500.

### 3.3 Route và response

- `GET /wishlist`, tên `wishlist.index`: danh sách của user hiện tại, phân trang và eager-load ảnh/category/brand.
- `POST /wishlist/{storefrontProduct}`, tên `wishlist.store`: thêm sản phẩm qua published binding hiện có.
- `DELETE /wishlist/{product}`, tên `wishlist.destroy`: dùng product binding thường để có thể bỏ item đã inactive, nhưng mutation bắt buộc xóa theo cặp `user_id + product_id`.
- Cả ba route nằm trong nhóm `storefront.locale`, `auth`, `active` và customer middleware; mutation có throttle riêng. Thứ tự middleware phải giữ session/locale trước controller và không làm thay đổi nhóm admin.
- HTML fallback redirect với flash message; AJAX trả JSON tối thiểu gồm trạng thái, count và HTML/label cần thiết, không trả dữ liệu user nhạy cảm.
- JSON mutation dùng contract ổn định: `is_wishlisted`, `wishlist_count`, `message`; có thể bổ sung HTML fragment trình bày nhưng không biến fragment thành nguồn trạng thái.
- Add mới có thể trả `201`, add lặp trả `200`; remove tồn tại hoặc remove lặp đều trả thành công idempotent. Guest JSON trả `401`, admin/inactive trả `403`, product không hợp lệ trả `404` hoặc `422` nhất quán theo binding/validation.
- Mutation có CSRF, throttle hợp lý và không nhận `user_id` từ client.

### 3.4 UI/UX

- Icon header trỏ tới named route Wishlist và badge lấy count thật; guest không hiển thị count giả.
- Nút heart dùng chung trên product card Homepage/Shop và trang detail; trạng thái active đồng bộ theo user.
- Trang Wishlist tái sử dụng presenter/card contract, hỗ trợ empty state, pagination và link quay lại Shop.
- JavaScript có loading/disabled, success/error feedback, cập nhật badge và aria-pressed; form POST/DELETE vẫn là fallback chuẩn khi JavaScript tắt.
- Tất cả chuỗi hiển thị và accessibility label nằm trong `lang/vi/frontend.php` và `lang/en/frontend.php`.

### 3.5 Bảo mật và hiệu năng

- Query luôn scope theo `auth()->id()`; không route-bind Wishlist record của user khác.
- Controller không nhận `Wishlist $wishlist` từ URL. Service nhận actor và product, sau đó thao tác bằng khóa tự nhiên `(user_id, product_id)`.
- Không cho client gửi owner ID; product inactive/unknown trả 404 hoặc validation phù hợp.
- Header count không tạo query lặp qua nhiều partial; dùng một nguồn share/composer có cache request-level hoặc eager-load phù hợp.
- Danh sách và card không phát sinh N+1; query count được kiểm thử.
- Unique database là lớp bảo vệ cuối cho add đồng thời; service xử lý duplicate race an toàn.

### 3.6 Redirect và return URL

- Login customer chỉ nhận intended URL nội bộ cùng scheme/host/port, không nhận URL `/admin`, protocol-relative hoặc scheme lạ.
- URL mặc định sau đăng nhập là `wishlist.index` khi thao tác bắt đầu từ Wishlist; nếu không có intended URL hợp lệ thì về `home`.
- Request HTML của guest được redirect tới customer login; request JSON không redirect HTML mà trả `401` để JavaScript quyết định mở CTA/login.
- Không đưa email, user ID hoặc nội dung Wishlist vào query string return URL.

## 4. Baseline kỹ thuật Task 10.1

Baseline được xác nhận ngày 13/11/2026:

- Bảng `wishlists` đã chạy migration, có unique `(user_id, product_id)` và hai FK cascade; schema hiện tại đủ cho MVP, chưa cần migration mới.
- `Wishlist::UPDATED_AT = null`; quan hệ `Wishlist -> User/Product`, `User -> wishlistItems/wishlistProducts` và `Product -> wishlistItems` đã tồn tại.
- `Product::published()` yêu cầu product và category cùng active; đây là điều kiện canonical cho thao tác thêm.
- Web guard đang dùng chung cho admin/customer; `User::isCustomer()` được xác định bằng việc không có role `is_admin`.
- Chưa có customer login backend, customer middleware, route/controller/service/test Wishlist.
- Header còn `href="#"` và badge `3`; product card chưa có heart control. Các giá trị mẫu này chỉ được thay trong Task 10.5 sau khi backend hoàn tất.
- Route inventory không có route chứa `wishlist`.
- Baseline `EcommerceSchemaTest`, `StorefrontCatalogTest`, `StorefrontHomepageTest`: 25 test, 199 assertion đều đạt.

## 5. Kiến trúc mục tiêu

```text
Storefront customer session
    -> WishlistController
        -> StoreWishlistRequest / mutation validation
        -> WishlistService
            -> Wishlist + Product published scopes
    -> Wishlist page / shared product cards
    -> Header wishlist count
    -> AJAX progressive enhancement
```

Controller chỉ điều phối. Service chịu trách nhiệm owner scope, idempotency và transaction. Blade chỉ render payload đã chuẩn hóa; không query database.

## 6. Kế hoạch task

### [x] Task 10.1 — Chốt contract và baseline Wishlist

**Dependency:** Catalog published contract, locale storefront, schema users/products/wishlists hiện có.

- [x] Xác nhận schema/index/FK và quan hệ model hiện tại.
- [x] Chốt authenticated-only, ownership, published/inactive và idempotency.
- [x] Chốt route/response/no-JS/AJAX contract.
- [x] Chốt guest behavior, redirect safety và phạm vi không dùng localStorage.
- [x] Ghi baseline route, migration và test liên quan.

**Kết quả cần đạt:** contract đủ rõ để triển khai mà không suy đoán owner, guest merge hoặc trạng thái product.

**Kế hoạch test:** migration status; EcommerceSchema, Storefront Catalog/Homepage baseline; route inventory; dashboard syntax.

### [x] Task 10.2 — Hoàn thiện xác thực khách hàng tối thiểu cho Wishlist

**Dependency:** Task 10.1.

- [x] Tách login/logout storefront khỏi admin authentication.
- [x] Form Request validate credential và rate limit đăng nhập.
- [x] Chỉ cho customer active dùng Wishlist; blocked/inactive bị từ chối an toàn.
- [x] Redirect intended chỉ cho URL same-origin, không vào `/admin`.
- [x] Kết nối trang login khách hàng với backend và dịch VI/EN.

**Kết quả cần đạt:** khách hàng có session hợp lệ để truy cập Wishlist, không phá admin guard hiện có.

**Kế hoạch test:** valid/invalid/blocked/admin account, session regeneration, logout, throttle, open redirect, admin isolation và CSRF.

**Kết quả triển khai:** `/login`, `/account` và `/logout` đã hoạt động độc lập với `/admin`; web guard được dùng chung nhưng mọi route customer bắt buộc đi qua alias `customer`. `SafeStorefrontRedirect` chỉ chấp nhận relative URL an toàn hoặc absolute URL trùng scheme/host/port và loại `/admin`. Suite mục tiêu đạt 11 test/77 assertion; full regression đạt 318 test/3.093 assertion.

### [x] Task 10.3 — Wishlist service, controller và route mutation

**Dependency:** Task 10.2.

- [x] Chuẩn hóa model types/relations và owner scopes nếu cần.
- [x] Tạo `WishlistService` add/remove/count idempotent và race-safe.
- [x] Tạo controller, request và named route có auth/customer/active/throttle.
- [x] Chỉ thêm product published; không nhận `user_id` từ request.
- [x] Trả HTML redirect và JSON response nhất quán, không lộ dữ liệu tài khoản.

**Kết quả cần đạt:** backend Wishlist an toàn, nguyên tử và có thể dùng từ mọi card.

**Kế hoạch test:** ownership, add/remove/idempotent, duplicate race, inactive/unknown, CSRF, throttle, guest và permission boundary.

**Kết quả triển khai:** `wishlist.store` và `wishlist.destroy` đã dùng chung `WishlistService`; add trả `201/200` theo bản ghi mới/có sẵn, remove lặp vẫn thành công, count luôn owner-scoped. Mutation có `auth + customer + throttle:wishlist-mutations`, HTML safe redirect và JSON tối thiểu không lộ user. Suite mục tiêu đạt 10 test/113 assertion; full regression đạt 328 test/3.206 assertion.

### [x] Task 10.4 — Trang danh sách Wishlist

**Dependency:** Task 10.3.

- [x] Tạo trang `/wishlist` dùng layout storefront và named route.
- [x] Query published product có eager-load media/category/brand và phân trang.
- [x] Tái sử dụng presenter/product-card, hiển thị giá/stock/ảnh/locale đúng contract.
- [x] Empty state và CTA quay lại Shop.
- [x] Bỏ item bằng form chuẩn, giữ trang/phân trang hợp lệ sau mutation.

**Kết quả cần đạt:** user chỉ nhìn thấy Wishlist của mình, responsive và không N+1.

**Kế hoạch test:** list isolation, pagination, inactive hidden, empty state, locale/media/price và query budget.

**Kết quả triển khai:** `/wishlist` hiển thị tối đa 12 published product/trang theo thứ tự lưu mới nhất, dùng presenter/card chung và eager-load media/category/brand. Item inactive bị ẩn nhưng row vẫn được giữ; empty state, VI/EN, remove form CSRF/no-JS và tự lùi về trang hợp lệ đã hoàn tất. Suite mục tiêu đạt 9 test/52 assertion; regression Wishlist/Auth/Catalog đạt 37 test/303 assertion; full regression đạt 337 test/3.258 assertion.

### [x] Task 10.5 — Tích hợp Wishlist vào header, card và product detail

**Dependency:** Task 10.3–10.4.

- [x] Icon header dùng route thật và count thật, không badge mẫu.
- [x] Nút heart dùng chung cho Homepage, Shop, Trending AJAX và Product detail.
- [x] Server-render đúng active state cho user; guest có CTA đăng nhập rõ ràng.
- [x] Không làm tăng query theo số lượng card.
- [x] Giữ HTML form/no-JS fallback cho add/remove.

**Kết quả cần đạt:** mọi điểm vào catalog có hành vi Wishlist nhất quán và badge chính xác.

**Kế hoạch test:** header/card/detail, active state, count, guest CTA, shared partial, trending AJAX và query count.

**Kết quả triển khai:** header đã dùng `wishlist.index` và published count thật; heart control dùng một partial chung trên Homepage, Shop, Trending AJAX, related card và Product detail. `StorefrontWishlistContext` request-scoped tải ID đúng một lần, không tăng query theo số card; guest nhận login CTA, customer nhận POST/DELETE form có CSRF/no-JS và active state server-render. Suite tích hợp đạt 7 test/33 assertion; regression Wishlist/Storefront đạt 50 test/374 assertion; full regression đạt 344 test/3.291 assertion.

### [x] Task 10.6 — AJAX, đa ngôn ngữ và accessibility Wishlist

**Dependency:** Task 10.5.

- [x] AJAX add/remove có pending guard, error recovery và cập nhật badge.
- [x] `aria-pressed`, focus-visible, live feedback và label đúng trạng thái.
- [x] Dịch toàn bộ Wishlist UI/validation/flash sang VI/EN.
- [x] Đồng bộ response sau nhiều tab/request và không optimistic sai khi server từ chối.
- [x] Visual QA desktop/tablet/mobile và reduced-motion.

**Kết quả cần đạt:** UX nhanh nhưng server vẫn là nguồn dữ liệu chuẩn; keyboard/screen reader dùng được.

**Kế hoạch test:** JSON/HTML parity, JS syntax, locale, keyboard/ARIA, loading/error, mobile và no-JS.

**Kết quả triển khai:** form Wishlist được tăng cường bằng AJAX nhưng vẫn giữ POST/DELETE + CSRF khi tắt JavaScript; pending guard chặn gửi lặp, lỗi không làm đổi trạng thái giả và response thành công cập nhật mọi control cùng badge. Trạng thái server-confirmed được đồng bộ giữa tab bằng `BroadcastChannel`, không dùng localStorage. Live region, `aria-pressed`, focus-visible, nhãn VI/EN, responsive toast và reduced-motion đã hoàn thiện. Suite AJAX/accessibility đạt 4 test/23 assertion; integration đạt 7 test/34 assertion; mutation đạt 10 test/113 assertion; full regression đạt 348 test/3.315 assertion.

### [x] Task 10.7 — Seeder/factory và hướng dẫn vận hành Wishlist

**Dependency:** Task 10.3–10.6.

- [x] Bổ sung factory/helper dữ liệu test không phụ thuộc production seeder.
- [x] Đã đánh giá demo seeder: không tạo mặc định vì Wishlist là dữ liệu cá nhân; factory chỉ dùng có chủ đích trong test/local.
- [x] Tài liệu hóa route, auth boundary, schema, xóa product/user và troubleshooting.
- [x] Ghi rõ guest Wishlist/merge và email notification chưa thuộc scope.
- [x] Kiểm tra migration hiện có, không tạo migration mới vì schema đã đủ.

**Kết quả cần đạt:** module có dữ liệu kiểm thử ổn định và hướng dẫn vận hành/rollback rõ ràng.

**Kế hoạch test:** factory/seeder idempotency nếu có, migration status, docs command và static checks.

**Kết quả triển khai:** đã bổ sung `CategoryFactory`, `ProductFactory`, `WishlistFactory` cùng quan hệ factory chuẩn để tạo dữ liệu owner/product độc lập; không nối dữ liệu demo Wishlist vào `DatabaseSeeder`. Tài liệu vận hành mới mô tả route/middleware, unique/FK cascade, published boundary, deploy, rollback không mất dữ liệu, troubleshooting và các phần guest merge/email notification ngoài scope. Migration Wishlist hiện ở trạng thái `Ran`, không cần migration mới. Factory suite đạt 3 test/8 assertion; toàn bộ Wishlist đạt 33 test/230 assertion; full regression đạt 351 test/3.323 assertion.

### [x] Task 10.8 — Quality gate Wishlist

**Dependency:** Task 10.1–10.7.

- [x] Regression authentication/admin isolation và ownership security.
- [x] Regression published/inactive, locale, image, price, pagination và N+1.
- [x] Regression AJAX/no-JS, CSRF, throttle, duplicate/idempotency và badge count.
- [x] Visual QA desktop/tablet/mobile, keyboard và accessibility.
- [x] Chạy full regression, route/migration/static checks và đồng bộ tài liệu/dashboard.

**Kết quả cần đạt:** Wishlist hoạt động đúng cho customer, an toàn owner scope, responsive và không phá Catalog/Admin.

**Kế hoạch test:** targeted Wishlist/Auth/Storefront suites, full `php artisan test`, route list, migration status, Blade/JS/dashboard syntax và diff check.

**Kết quả triển khai:** quality gate cuối phase đạt toàn bộ. Tám suite Authentication/Catalog/Homepage/Wishlist đạt 68 test/483 assertion; full regression đạt 351 test/3.323 assertion. Ba route Wishlist đúng controller/method, 23 migration đều `Ran`, Blade cache, JavaScript frontend/dashboard và diff check hợp lệ. Visual QA trực tiếp đạt ở desktop 1.700px, tablet iPad Pro 1.032px và mobile 510px: header, filter, product grid, guest heart CTA, pagination/footer không tràn ngang; keyboard/ARIA được xác nhận bằng accessibility tree và automated markup tests. Phase Wishlist hoàn tất 8/8 task.

## 7. Thứ tự triển khai

```text
10.1 -> 10.2 -> 10.3 -> 10.4 -> 10.5 -> 10.6 -> 10.7 -> 10.8
```

Task 10.2 là dependency bắt buộc vì repository chưa có customer authentication hoàn chỉnh. Nếu authentication được tách thành roadmap riêng trước khi bắt đầu, Task 10.2 sẽ chuyển thành bước tích hợp/kiểm chứng thay vì tạo lại cùng chức năng.

## 8. Ngoài phạm vi phase

- Guest Wishlist lưu localStorage và merge sau đăng nhập.
- Chia sẻ Wishlist công khai.
- Nhiều danh sách yêu thích cho một user.
- Email nhắc giá giảm hoặc sản phẩm có hàng lại.
- Di chuyển hàng loạt Wishlist sang Cart trước khi Cart/Checkout thật hoàn tất.
