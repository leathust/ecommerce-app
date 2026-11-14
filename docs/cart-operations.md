# Cart — kiến trúc và hướng dẫn vận hành

## 1. Phạm vi và nguồn dữ liệu chuẩn

Cart hỗ trợ hai loại owner:

- Guest được nhận diện bằng raw token 256-bit trong cookie `storefront_cart_token`; database chỉ lưu SHA-256 của token.
- Customer active dùng `carts.user_id`; Cart customer không giữ `session_token`.

Database và catalog hiện tại là nguồn chuẩn. JavaScript, badge và `BroadcastChannel` chỉ phản ánh kết quả server đã xác nhận. Cart không giữ tồn kho và không đóng băng giá: mỗi lần đọc hoặc ghi đều kiểm tra lại product/category, tồn kho và giá hiện hành.

Chưa thuộc phạm vi hiện tại:

- Giữ chỗ hoặc trừ tồn kho khi thêm Cart.
- Coupon/promo, thuế, gift card hoặc loyalty point.
- Checkout, địa chỉ giao hàng, tạo Order, payment gateway và webhook.
- Phí vận chuyển theo địa chỉ/provider hoặc ETA.
- Abandoned-cart email, analytics, job cleanup tự động và admin xem Cart khách hàng.

## 2. Schema, ownership và vòng đời

| Thành phần | Contract |
| --- | --- |
| `carts.user_id` | Nullable FK; unique khi có giá trị nên mỗi customer có tối đa một Cart |
| `carts.session_token` | Nullable unique; guest lưu SHA-256, customer bắt buộc `NULL` |
| `cart_items.cart_id` | FK cascade: xóa Cart sẽ xóa các dòng của Cart |
| `cart_items.product_id` | FK restrict: không hard-delete product đang được Cart tham chiếu |
| unique (`cart_id`, `product_id`) | Một product chỉ có một dòng trong mỗi Cart |
| `quantity` | Từ 1 đến `min(products.quantity, 99)` khi mutation |

Product/category inactive hoặc hết hàng vẫn có thể xuất hiện để người dùng xóa, nhưng không được tính vào badge, subtotal hoặc shipping estimate. Xóa user cascade Cart customer và các item. Login customer sẽ merge guest Cart vào Cart customer trong transaction, cộng quantity rồi cap theo stock/99; sau đó guest Cart bị xóa và cookie bị thu hồi. Logout giữ Cart customer nhưng expire guest identity để phiên guest mới không đọc được dữ liệu customer.

Migration `2026_11_14_000010_enforce_unique_customer_carts.php` là migration additive: nếu dữ liệu cũ có nhiều Cart/customer, nó giữ Cart cập nhật gần nhất, gộp item trùng, cap quantity, giữ item hết hàng để dọn và thêm unique invariant. Không chạy `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` trên database cần giữ dữ liệu.

## 3. Cookie và bảo mật

- Raw token có 43 ký tự base64url, entropy 256-bit, thời hạn 30 ngày.
- Cookie là HttpOnly, SameSite=Lax và Secure khi HTTPS; path/domain theo session config.
- Không lưu raw token trong database, source, log, dashboard, tài liệu hỗ trợ hoặc analytics.
- Không nhận `cart_id`, `user_id`, price, subtotal hoặc owner từ browser.
- Mutation dùng CSRF, route binding, owner scope và limiter riêng cho customer/guest.
- JSON chỉ trả state nghiệp vụ cần cho UI; không trả token hash hoặc thông tin owner.

Nếu token nghi bị lộ, expire cookie ở client và xóa đúng guest Cart đã đối chiếu bằng hash. Không log raw token để điều tra.

## 4. Routes và response

| Method | Route | Mục đích |
| --- | --- | --- |
| GET | `/cart` | Trang Cart; `Accept: application/json` trả Cart region để reconcile |
| POST | `/cart/items/{storefrontProduct}` | Thêm published product theo slug |
| PATCH | `/cart/items/{product}` | Cập nhật quantity theo Cart owner |
| DELETE | `/cart/items/{product}` | Xóa dòng, kể cả product vừa inactive |
| DELETE | `/cart` | Làm trống Cart owner hiện tại |

HTML form POST/PATCH/DELETE + CSRF là fallback chuẩn. AJAX không optimistic update: mutation thành công trả `cart_html`, `cart_count`, `line_count`, `subtotal` và message; frontend chỉ thay Cart region sau response thành công. Lỗi validation/network giữ nguyên UI. Tab khác nhận count đã xác nhận qua `BroadcastChannel`, sau đó gọi GET JSON để reconcile toàn vùng.

## 5. Factory và dữ liệu test

```php
$guest = Cart::factory()->guest()->create();
$knownGuest = Cart::factory()->guestWithToken(str_repeat('a', 43))->create();

$customer = User::factory()->create();
$cart = Cart::factory()->forCustomer($customer)->withItems(3)->create();
$cartWithUnavailable = Cart::factory()->withUnavailableItem(2)->create();

$item = CartItem::factory()->quantity(4)->create();
$outOfStock = CartItem::factory()->unavailable()->create();
$inactive = CartItem::factory()->inactive()->create();
```

Các factory chỉ phục vụ automated test hoặc demo local có chủ đích. Project cố ý không có `CartSeeder` và `DatabaseSeeder` không tạo Cart cá nhân. Nếu cần demo, tạo seeder riêng, chạy bằng `--class` chỉ trong local và không thêm vào luồng seed production.

## 6. Deploy và backfill an toàn

Trước deploy, backup các bảng `carts`, `cart_items`, `users`, `products`, `categories` và bảng `migrations`. Sau đó:

```powershell
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=cart
php artisan test --filter=Cart
```

`migrate --pretend` chỉ để xem SQL pending; cần đọc output theo MySQL thực tế. Migration đã có trong bảng `migrations` sẽ không chạy lại. Sau deploy, kiểm tra:

1. Guest thêm hai product, reload và xác nhận Cart còn nguyên.
2. Đăng nhập customer có Cart sẵn, xác nhận merge và trần stock/99.
3. Add/update/remove/clear không reload; badge, subtotal và empty state đồng bộ.
4. Mở hai tab, mutation ở tab một rồi kiểm tra tab hai reconcile.
5. Tắt JavaScript và thử native forms.
6. Thử product hết hàng/inactive: không thêm được nhưng dòng cũ vẫn xóa được.

## 7. Rollback và cleanup

- Rollback code: deploy lại release tương thích và chạy `php artisan optimize:clear`; không xóa Cart.
- Không rollback migration unique Cart trên production chỉ để quay code, vì `down()` bỏ invariant và có thể cho duplicate phát sinh.
- Nếu buộc rollback schema, backup trước, dừng ghi Cart, rollback đúng migration/batch đã xác minh rồi kiểm tra duplicate trước khi mở traffic.
- Không cleanup Cart customer tự động. Guest Cart chỉ nên cleanup khi có retention policy đã được duyệt, preview đúng tập `user_id IS NULL`, backup và triển khai bằng command/job có dry-run, giới hạn batch, metrics và audit. Project hiện chưa có cleanup job.

## 8. Troubleshooting

| Hiện tượng | Kiểm tra |
| --- | --- |
| Guest mất Cart | Host/path cookie, HTTPS/Secure, SameSite, cookie 43 ký tự và hash DB |
| Customer có hai Cart | Unique index `carts.user_id`, migration status và dữ liệu import ngoài ứng dụng |
| Login không merge | Customer active, cookie còn hợp lệ, transaction/log DB và stale token đã bị expire chưa |
| Badge khác subtotal | Product/category status, stock, item quantity và Cart context; cả hai chỉ tính dòng khả dụng |
| AJAX không cập nhật | Console/network, JSON response, CSRF 419, validation 422, throttle 429, asset/browser cache |
| Native form redirect sai | `return_to` phải là URL nội bộ an toàn; kiểm tra `APP_URL` và proxy headers |
| Không xóa được product | FK restrict do `cart_items`; chuyển inactive và để owner/cleanup xử lý Cart trước |
| Hai tab tạm lệch | Browser có hỗ trợ `BroadcastChannel`; reload/GET Cart luôn lấy state database chuẩn |
| Quantity bị từ chối | Giới hạn là `min(stock hiện tại, 99)` và add lặp cộng vào quantity đang có |
| Giá thay đổi trong Cart | Đây là contract hiện tại: Cart dùng giá catalog mới nhất; Order tương lai phải snapshot khi checkout |

Không đưa cookie, token/hash, session ID, dữ liệu customer thật hoặc nội dung `.env` vào ticket, log hay ảnh chụp hỗ trợ.
