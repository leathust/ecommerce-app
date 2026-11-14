# Roadmap hoàn thiện Giỏ hàng Storefront

## 1. Mục tiêu

Xây dựng Cart thật cho website khách hàng trên Laravel 10: guest và customer có thể thêm sản phẩm từ Homepage/Shop/chi tiết, xem giỏ, thay đổi số lượng, xóa hoặc làm trống; đăng nhập sẽ hợp nhất giỏ guest an toàn; header hiển thị số lượng thật; giao diện hỗ trợ VI/EN, responsive, accessibility và AJAX progressive enhancement nhưng vẫn hoạt động khi tắt JavaScript.

Cart phải dùng bảng `carts` và `cart_items` hiện có, không dùng localStorage làm nguồn dữ liệu nghiệp vụ. Giá và tình trạng sản phẩm trong Cart là dữ liệu catalog hiện tại; snapshot chỉ được tạo khi checkout sinh Order ở phase sau.

## 2. Hiện trạng đã rà soát

- Migration `2026_10_22_000060_create_carts_tables.php` đã tạo `carts` và `cart_items`.
- `carts` có `user_id` nullable, `session_token` unique nullable và timestamps; chưa có ràng buộc đảm bảo một active cart cho mỗi customer.
- `cart_items` có `cart_id`, `product_id`, `quantity`, timestamps, unique `(cart_id, product_id)`; FK product dùng `RESTRICT`, vì vậy sản phẩm đang nằm trong Cart không thể bị xóa.
- Model `Cart`, `CartItem` và quan hệ `User::carts`, `Product::cartItems` đã tồn tại nhưng chưa có casts, typed relations, scopes hoặc factory.
- Route `GET /cart` đang trỏ tới `HomeController::cart()` và chỉ render template hard-code.
- `resources/views/frontend/cart.blade.php` đang chứa 3 sản phẩm, USD, promo, thuế, vận chuyển và payment badge giả.
- Header đang hiển thị badge Cart cố định `2`; product detail đang ghi “chức năng giỏ hàng đang được hoàn thiện”; shared product card chưa có add-to-cart.
- Chưa có mutation route/controller/request/service, guest cookie, merge khi đăng nhập, rate limit hoặc test Cart.
- Customer authentication, safe redirect, published product binding, presenter, MoneyFormatter, VI/EN và System Settings commerce đã có thể tái sử dụng.
- Baseline ngày 14/11/2026: schema/auth/catalog đạt 19 test/161 assertion; chỉ có một route Cart dạng GET; 23 migration đang `Ran`.

## 3. Contract nghiệp vụ đã chốt tại Task 11.1

### 3.1 Chủ sở hữu Cart

- Guest có Cart bằng opaque token 256-bit ngẫu nhiên lưu trong cookie `storefront_cart_token`; database chỉ lưu chuỗi SHA-256 dài 64 ký tự, không lưu raw token.
- Cookie tồn tại 30 ngày, `HttpOnly`, `SameSite=Lax`, chỉ `Secure` khi request HTTPS, dùng domain/path tương thích cấu hình session và được mã hóa bởi Laravel.
- Customer active dùng Cart gắn `user_id`; admin session không được nhận Cart customer.
- Mỗi guest token và mỗi customer chỉ có tối đa một Cart đang hoạt động. Cart customer có `user_id` và `session_token = null`; Cart guest có `user_id = null` và token hash khác null.
- Dữ liệu customer legacy có nhiều Cart sẽ được hợp nhất vào Cart cập nhật gần nhất trước khi thêm unique invariant cho `user_id`; item trùng được cộng nhưng chặn theo tồn kho và giới hạn mỗi dòng.
- Khi customer đăng nhập, Cart guest được merge vào Cart customer sau khi xác thực/regenerate session và trước redirect, trong transaction: cùng product thì cộng số lượng nhưng không vượt tồn kho/giới hạn; sau merge xóa Cart guest và thu hồi cookie token.
- Logout không xóa Cart customer. Nếu cùng browser trở về guest sau logout, không được tiếp tục thấy Cart riêng của customer.

### 3.2 Quy tắc sản phẩm và số lượng

- Chỉ product published, category active và `quantity > 0` được thêm mới.
- Số lượng mỗi dòng là số nguyên tối thiểu 1, tối đa `min(tồn kho hiện tại, 99)`; không nhận số âm, float hoặc chuỗi tùy ý.
- Add lặp là idempotent theo `(cart_id, product_id)` và cộng quantity trong transaction; unique index là lớp bảo vệ cuối.
- Update quantity phải kiểm tra lại tồn kho hiện tại. Xóa dòng và clear Cart phải owner-scoped.
- Product/category inactive hoặc hết hàng sau khi đã thêm được hiển thị là “không còn khả dụng”, không tính vào checkout và chỉ cho xóa; không tự xóa âm thầm.
- Cart không giữ tồn kho. Tồn kho chỉ được xác nhận lại khi checkout/đặt hàng; thêm Cart không làm giảm `products.quantity`.

### 3.3 Giá và tổng tiền

- Giá dòng = giá catalog hiện tại × quantity, tính server-side bằng decimal; không tin giá/tổng từ browser.
- Cart hiển thị subtotal và item count thật bằng `MoneyFormatter`/currency setting hiện có.
- `commerce.default_shipping_fee`, `commerce.free_shipping_threshold` và `commerce.minimum_order_amount` chỉ hiển thị estimate/quy tắc khi được tích hợp rõ; không ghi vào Order tại Cart.
- Không hiển thị promo, thuế, payment method hoặc cam kết giao hàng giả khi chưa có engine tương ứng.
- Tổng cuối cùng có thể thay đổi tại checkout nếu giá, tồn kho hoặc shipping thay đổi; UI cần giải thích rõ.

### 3.4 Route và response dự kiến

| Method | Path | Tên route | Mục đích |
| --- | --- | --- | --- |
| GET | `/cart` | `cart` | Trang Cart hiện tại của guest/customer; giữ tương thích menu/Blade hiện hữu |
| POST | `/cart/items/{storefrontProduct}` | `cart.items.store` | Thêm published product |
| PATCH | `/cart/items/{product}` | `cart.items.update` | Cập nhật quantity owner-scoped |
| DELETE | `/cart/items/{product}` | `cart.items.destroy` | Xóa một dòng owner-scoped |
| DELETE | `/cart` | `cart.clear` | Làm trống Cart hiện tại |

- Tên route `cart` là contract canonical vì menu động, layout và header đang tham chiếu; khi thay controller không đổi tên route này.
- HTML fallback redirect về URL nội bộ an toàn với flash message; AJAX trả JSON tối thiểu gồm `cart_count`, `line_count`, `subtotal`, state/message và HTML fragment cần thiết.
- Mutation dùng CSRF, throttle riêng, không nhận `cart_id`, `user_id`, giá hoặc subtotal từ client.
- Guest/customer/admin/inactive có hành vi HTML/JSON rõ ràng; mọi mutation đều owner-scoped.

### 3.5 UI/UX và accessibility

- Header badge là tổng quantity của các dòng còn khả dụng, không phải số row; badge ẩn khi bằng 0. Dòng unavailable vẫn nằm trong trang Cart để người dùng chủ động xóa nhưng không tăng badge/subtotal.
- Shared add-to-cart control dùng trên Homepage, Shop, Trending, related card và Product detail; detail cho chọn quantity, card mặc định thêm 1.
- Cart page dùng dữ liệu presenter thật, có empty state, unavailable state, quantity stepper/input, remove và clear confirmation.
- AJAX có pending guard, abort/stale guard khi cần, error recovery, live feedback và đồng bộ header/line/subtotal; server vẫn là nguồn trạng thái chuẩn.
- Form POST/PATCH/DELETE vẫn hoạt động khi tắt JavaScript.
- `aria-label`, `aria-live`, focus-visible, target tối thiểu và reduced-motion phải được kiểm tra; toàn bộ chuỗi nằm trong catalog VI/EN.

### 3.6 Bảo mật, concurrency và hiệu năng

- Raw guest token chỉ tồn tại trong cookie; query database bằng hash và dùng `hash_equals`/lookup cố định phù hợp.
- Merge/add/update dùng transaction và lock các Cart/CartItem/Product liên quan để giảm lost update và oversell UX.
- Không route-bind `Cart` hoặc `CartItem` do client cung cấp; controller thao tác bằng actor/token + product.
- Throttle mutation theo customer ID hoặc guest token/IP; không đưa token vào log/JSON/HTML.
- Header context resolve một lần mỗi request; danh sách eager-load product, primary image, category, brand và có query budget chống N+1.
- Cookie/session/token phải được rotate hoặc thu hồi đúng lúc để tránh fixation và Cart customer leakage.

## 4. Kế hoạch triển khai

### [x] Task 11.1 — Chốt contract và baseline Cart

**Dependency:** Wishlist/Customer Auth/Catalog đã hoàn tất.

- [x] Kiểm kê schema, model, route, template, settings và test hiện có.
- [x] Chốt guest token hashing/cookie, customer ownership và admin isolation.
- [x] Chốt merge guest → customer, logout và nhiều tab/browser.
- [x] Chốt published/stock/quantity/price/unavailable contract.
- [x] Chốt route, HTML/JSON, CSRF, throttle, no-JS và out-of-scope checkout.
- [x] Ghi baseline test/query/route/migration thực tế.

**Kết quả cần đạt:** contract đủ rõ để không phải đổi ownership hoặc price semantics giữa chừng.

**Kế hoạch test:** schema/auth/catalog baseline, route inventory, migration status, dashboard/static checks.

**Kết quả thực tế ngày 14/11/2026:** đã khóa contract ownership, token, merge, quantity, price và route compatibility. Baseline `EcommerceSchemaTest`, `StorefrontAuthenticationTest`, `StorefrontCatalogTest` đạt 19 test/161 assertion; route inventory xác nhận hiện chỉ có `GET /cart` tên `cart`; `migrate:status` xác nhận 23 migration đều `Ran`. Runtime và database chưa bị thay đổi trong task contract này.

### [x] Task 11.2 — Củng cố schema, model và factory Cart

**Dependency:** Task 11.1.

- [x] Rà dữ liệu duplicate trước khi thêm unique owner invariant nếu cần.
- [x] Tạo additive migration/backfill an toàn, không phá Cart hiện hữu.
- [x] Chuẩn hóa casts, typed relations, scopes và helper trên `Cart`/`CartItem`.
- [x] Bổ sung `CartFactory`/`CartItemFactory` dùng ProductFactory hiện có.
- [x] Kiểm tra unique/FK/cascade/restrict và rollback không mất dữ liệu ngoài phạm vi.

**Kết quả cần đạt:** một Cart hợp lệ cho mỗi owner, item không trùng và factory ổn định.

**Kế hoạch test:** migration preservation, unique/FK, model relations/scopes, factory và MySQL migration status.

**Kết quả thực tế ngày 14/11/2026:** kiểm kê MySQL trước migration xác nhận 0 Cart/0 item, không có duplicate hoặc owner state sai. Migration additive hợp nhất duplicate customer Cart nếu có, giữ Cart cập nhật gần nhất, gộp item theo product, xóa token khỏi Cart customer rồi tạo unique `carts.user_id`; guest vẫn được phép có nhiều Cart nhờ `user_id` nullable. `CartSchemaTest` đạt 4 test/19 assertion; full regression đạt 355 test/3342 assertion; migration mới đã chạy thành công ở batch 8 và tổng cộng 24 migration đang `Ran`.

### [x] Task 11.3 — Cart identity và merge service

**Dependency:** Task 11.2.

- [x] Tạo resolver/context request-scoped cho guest/customer Cart.
- [x] Phát hành raw token cookie và chỉ lưu token hash.
- [x] Tạo/resolve Cart an toàn, chống duplicate do request đồng thời.
- [x] Merge guest vào customer khi login thành công trong transaction.
- [x] Rotate/forget token đúng lúc; logout không rò Cart customer cho guest.
- [x] Tích hợp mà không phá admin auth và safe intended redirect.

**Kết quả cần đạt:** ownership ổn định xuyên suốt guest → login → logout.

**Kế hoạch test:** cookie flags/token secrecy, guest isolation, customer isolation, merge cộng/trần quantity, login/logout/admin boundary và concurrency retry.

**Kết quả thực tế ngày 14/11/2026:** đã có `CartIdentityService` và `StorefrontCartContext` request-scoped. Guest chỉ được tạo Cart khi mutation yêu cầu, cookie raw 256-bit tồn tại 30 ngày với HttpOnly/SameSite/Secure phù hợp và database chỉ giữ SHA-256. Login customer merge guest Cart trong transaction sau session regeneration, khóa Cart/item/product, áp trần stock/99 rồi expire token; logout giữ Cart customer nhưng thu hồi mọi guest identity. `CartIdentityTest` đạt 6 test/43 assertion; Cart/Auth regression đạt 21 test/139 assertion; full regression đạt 361 test/3385 assertion.

### [x] Task 11.4 — Cart mutation service, request và routes

**Dependency:** Task 11.3.

- [x] Tạo `CartService` cho add/update/remove/clear owner-scoped.
- [x] Tạo Form Request validation quantity/return URL và authorization boundary.
- [x] Thêm named routes/controller, CSRF và limiter riêng.
- [x] Kiểm tra published/category/stock ở add và stock hiện tại ở update.
- [x] Đảm bảo add lặp, remove lặp và clear lặp có response nhất quán.
- [x] Trả HTML redirect/flash và JSON contract không lộ owner/token.

**Kết quả cần đạt:** mutation nguyên tử, không sửa Cart khác và không tin dữ liệu tiền từ client.

**Kế hoạch test:** ownership, validation, published/inactive/out-of-stock, quantity bounds, idempotency, race/unique, CSRF/throttle và HTML/JSON parity.

**Kết quả thực tế ngày 14/11/2026:** đã triển khai `CartService`, ba Form Request và `CartController` cho add/update/remove/clear. Add dùng published slug binding; update/remove dùng product ID nhưng luôn thao tác qua Cart owner hiện tại để có thể xóa dòng inactive mà không chạm Cart khác. Quantity được kiểm tra số nguyên 1–99 và tồn kho hiện tại trong transaction; subtotal/count chỉ tính dòng còn khả dụng. Bốn mutation route dùng web CSRF, locale và limiter riêng 60/phút theo customer hoặc guest token hash/IP; HTML dùng safe redirect/flash, JSON không trả owner/token. `CartMutationTest` đạt 11 test/161 assertion; Cart/Auth/Catalog regression đạt 39 test/361 assertion; full regression đạt 372 test/3546 assertion.

### [x] Task 11.5 — Trang Cart động và tổng tiền server-side

**Dependency:** Task 11.4.

- [x] Thay toàn bộ dữ liệu hard-code bằng Cart DTO/presenter.
- [x] Eager-load media/category/brand và định dạng VI/EN/tiền tệ.
- [x] Hiển thị quantity, unit price, line subtotal, subtotal và item count thật.
- [x] Hiển thị empty state và unavailable/out-of-stock state có thể xóa.
- [x] Tích hợp estimate shipping/minimum order từ settings mà không giả tax/promo.
- [x] Giữ POST/PATCH/DELETE fallback và responsive layout.

**Kết quả cần đạt:** `/cart` phản ánh đúng database/current catalog và không còn nội dung template giả.

**Kế hoạch test:** empty/populated/unavailable, locale/media/price, totals, settings fallback, query budget, XSS và no-JS forms.

**Kết quả thực tế ngày 14/11/2026:** `GET /cart` đã chuyển sang `CartController@index` và dùng `CartPageData` query-free ở Blade. Trang hiển thị product/ảnh/category/giá/số lượng/line subtotal thật, format VI/EN và eager-load hữu hạn; dòng inactive/hết hàng vẫn hiện để xóa nhưng bị loại khỏi count/subtotal. Commerce settings chỉ được trình bày dưới dạng phí giao hàng/tổng ước tính, ngưỡng miễn phí và mức đơn tối thiểu; promo, thuế và payment giả đã bị loại bỏ, checkout được ghi rõ chưa khả dụng. Empty state, PATCH/DELETE/clear forms CSRF và responsive CSS đã hoàn thiện. `CartPageTest` đạt 8 test/52 assertion; Cart/Catalog/Settings regression đạt 43 test/403 assertion; full regression đạt 380 test/3598 assertion; Blade cache thành công.

### [x] Task 11.6 — Tích hợp add-to-cart vào storefront

**Dependency:** Task 11.4–11.5.

- [x] Thay header badge cố định bằng count thật qua request-scoped context.
- [x] Thêm shared add control vào Homepage/Shop/Trending/related cards.
- [x] Thay `cart_pending` ở Product detail bằng quantity form thật.
- [x] Disable/label đúng cho product inactive hoặc hết hàng.
- [x] Giữ link/menu route Cart tương thích và không tăng query theo số card.
- [x] Cập nhật Wishlist/Card integration để hai control không chồng lấn.

**Kết quả cần đạt:** mọi điểm vào Cart dùng chung backend/state và header count chính xác.

**Kế hoạch test:** header/card/detail/trending, stock state, guest/customer, shared partial, query count và route compatibility.

**Kết quả thực tế ngày 14/11/2026:** badge Cart ở header đã dùng count server-side theo Cart guest/customer và chỉ tính số lượng khả dụng với trần tồn kho/99; context tự nhận diện actor/token mới giữa các request và không tăng query theo số card. Shared `cart-add` component đã được dùng cho Homepage, Shop, Trending AJAX, related/suggestion cards và Product detail; card gửi quantity 1, detail cho chọn quantity theo tồn kho, sản phẩm hết hàng chỉ hiện control disabled. Form POST + CSRF + `return_to` nội bộ tiếp tục hoạt động khi tắt JavaScript; Wishlist và Cart có vùng action riêng, responsive. `CartStorefrontIntegrationTest` đạt 7 test/44 assertion; regression Cart/Catalog/Home/Wishlist đạt 67 test/530 assertion; full regression đạt 387 test/3643 assertion; 5 route Cart, Blade cache, PHP syntax, dashboard syntax và diff check đều đạt.

### [x] Task 11.7 — AJAX, VI/EN và accessibility Cart

**Dependency:** Task 11.5–11.6.

- [x] AJAX add/update/remove/clear có pending, stale guard và error recovery.
- [x] Đồng bộ header badge, line subtotal, subtotal và empty state từ response server.
- [x] Không optimistic sai khi server từ chối do tồn kho/giá/trạng thái thay đổi.
- [x] Bổ sung live region, focus management, keyboard stepper và reduced-motion.
- [x] Dịch toàn bộ Cart UI, validation và flash sang VI/EN.
- [x] Đồng bộ nhiều tab bằng sự kiện server-confirmed; không dùng localStorage làm Cart chính.

**Kết quả cần đạt:** Cart nhanh, accessible và vẫn đúng khi JavaScript tắt hoặc request lỗi.

**Kế hoạch test:** JS syntax, JSON/HTML parity, loading/error/stale, badge/totals, locale, ARIA/keyboard, reduced-motion, multi-tab và no-JS.

**Kết quả thực tế ngày 14/11/2026:** add/update/remove/clear đều chạy AJAX không tải lại trang, có khóa request lặp, stale-version guard, validation/network recovery và không optimistic update. Mỗi response mutation mang `cart_html` do server render từ catalog/settings mới nhất; Cart region được thay nguyên tử nên quantity, line subtotal, subtotal, shipping estimate, badge và empty state luôn đồng bộ. `GET /cart` hỗ trợ JSON reconciliation cho tab khác; `BroadcastChannel` chỉ phát count đã được server xác nhận và không dùng localStorage làm nguồn nghiệp vụ. Live region VI/EN, focus sau mutation, nút tăng/giảm keyboard-friendly, reduced-motion và native POST/PATCH/DELETE fallback đã hoàn tất. `CartAjaxAccessibilityTest` đạt 8 test/65 assertion; targeted Cart/Catalog/Home/Wishlist đạt 72 test/585 assertion; full regression đạt 395 test/3709 assertion; 5 route Cart, Blade cache, PHP/JS/dashboard syntax và diff check đều đạt.

### [x] Task 11.8 — Dữ liệu test và hướng dẫn vận hành Cart

**Dependency:** Task 11.2–11.7.

- [x] Hoàn thiện factory/helper guest/customer/unavailable Cart.
- [x] Không thêm Cart cá nhân vào production `DatabaseSeeder`; demo seeder chỉ khi có yêu cầu rõ ràng.
- [x] Tài liệu hóa cookie/token, schema, routes, merge, cascade/restrict và cleanup.
- [x] Viết hướng dẫn deploy, migration/backfill, rollback và troubleshooting.
- [x] Ghi rõ stock reservation, promo, checkout/payment và abandoned-cart job ngoài scope.

**Kết quả cần đạt:** có dữ liệu test ổn định và runbook không làm mất Cart production.

**Kế hoạch test:** factory/helper, migration status, docs commands, token redaction và static checks.

**Kết quả thực tế ngày 14/11/2026:** `CartFactory` đã có helper guest token hash, customer owner, nhiều item và unavailable item; `CartItemFactory` có quantity/out-of-stock/inactive state. Test chứng minh raw token không được persist, quan hệ owner hợp lệ, Cart delete cascade item, product có Cart history bị restrict và `DatabaseSeeder` không gọi/không tồn tại `CartSeeder`. Runbook `docs/cart-operations.md` mô tả schema, cookie, login merge/logout, routes, AJAX/no-JS, deploy/backfill, rollback, retention/cleanup bảo thủ, troubleshooting, token redaction và toàn bộ phần ngoài scope. README và tài liệu kiến trúc đã liên kết module Cart. `CartFactoryTest` đạt 6 test/25 assertion; toàn bộ Cart đạt 50 test/410 assertion; full regression đạt 401 test/3734 assertion; 24 migration đều Ran và PHP/route/dashboard/docs/diff checks đạt.

### [ ] Task 11.9 — Quality gate Cart

**Dependency:** Task 11.1–11.8.

- [ ] Regression guest/customer/admin ownership, login merge và logout isolation.
- [ ] Regression published/stock/quantity/price/settings/locale/media.
- [ ] Regression AJAX/no-JS, CSRF, throttle, idempotency, stale response và badge/totals.
- [ ] Regression query budget, unique/concurrency, token secrecy và XSS.
- [ ] Visual QA desktop/tablet/mobile, keyboard, screen reader semantics và reduced-motion.
- [ ] Chạy full regression, route/migration/Blade/JS/dashboard/diff checks.

**Kết quả cần đạt:** Cart an toàn, nhất quán, responsive và không phá Wishlist/Catalog/Auth/Admin.

**Kế hoạch test:** targeted Cart/Auth/Catalog/Wishlist suites, full `php artisan test`, route list, migration status, Blade/JS/dashboard syntax, diff check và visual QA.

## 5. Thứ tự triển khai

```text
11.1 -> 11.2 -> 11.3 -> 11.4 -> 11.5 -> 11.6 -> 11.7 -> 11.8 -> 11.9
```

Đường găng là ownership/schema → identity/merge → mutation → UI/integration. Không nên làm giao diện AJAX trước khi contract guest/customer và merge được khóa.

## 6. Ngoài phạm vi phase

- Checkout tạo Order và địa chỉ giao hàng.
- Giữ/trừ tồn kho khi thêm Cart; reservation timeout.
- Promo/coupon, thuế, gift card, loyalty point.
- Payment gateway, COD/bank transfer workflow và webhook.
- Shipping provider/rate theo địa chỉ hoặc thời gian giao dự kiến.
- Abandoned-cart email/job, analytics và admin xem Cart khách hàng.
- Product variant/options vì schema hiện chưa có variant SKU.
- Chuyển hàng loạt Wishlist → Cart; có thể làm sau khi Cart ổn định.

## 7. Rủi ro cần theo dõi

| Rủi ro | Mức | Kiểm soát |
| --- | --- | --- |
| Guest token bị đoán/rò | Cao | Token entropy cao, DB lưu hash, cookie HttpOnly/SameSite/Secure và không log token |
| Hai request tạo hai Cart customer | Cao | Unique invariant, transaction/lock và retry duplicate |
| Merge làm vượt tồn kho | Cao | Lock product/item, cộng có trần và trả thông báo điều chỉnh |
| Giá/tồn kho đổi khi Cart đang mở | Trung bình | Tính lại từ catalog mỗi request, đánh dấu unavailable và xác nhận lại tại checkout |
| Cart bị hiểu nhầm là giữ hàng | Trung bình | Không trừ kho, hiển thị thông báo và test contract rõ ràng |
| Badge gây query lặp | Trung bình | Context request-scoped và query-count test |
| Template tiếp tục hiển thị promo/tax giả | Trung bình | Xóa hard-code; chỉ render dữ liệu có backend/settings thật |
| Rollback migration làm mất Cart | Cao | Additive migration, backup, migration preservation test và runbook rollback |

## 8. Tiêu chí hoàn thành phase

- 9/9 task có checklist, test thực tế và log dashboard đồng bộ.
- Guest/customer Cart hoạt động owner-scoped; merge/login/logout không rò dữ liệu.
- Add/update/remove/clear và count/totals đúng khi bật hoặc tắt JavaScript.
- Không còn sản phẩm, USD, promo, thuế, shipping/payment giả trong Cart template.
- Published/stock/price/quantity được server kiểm tra; không trừ tồn kho ở Cart.
- VI/EN, responsive, keyboard/ARIA/reduced-motion và query budget đạt.
- Migration/backfill an toàn trên database hiện hữu; full regression xanh.
