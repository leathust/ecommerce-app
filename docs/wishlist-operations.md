# Wishlist — kiến trúc và hướng dẫn vận hành

## 1. Phạm vi hiện tại

Wishlist lưu sản phẩm yêu thích của **khách hàng active đã đăng nhập**. Tài khoản quản trị, tài khoản bị khóa và guest không được đọc hoặc thay đổi dữ liệu Wishlist. Database là nguồn dữ liệu chuẩn; JavaScript và `BroadcastChannel` chỉ tăng cường trải nghiệm sau khi server xác nhận mutation.

Chưa thuộc phạm vi hiện tại:

- Wishlist cho guest bằng cookie/localStorage.
- Merge Wishlist guest sau khi đăng nhập.
- Chia sẻ Wishlist công khai.
- Gửi email khi sản phẩm giảm giá hoặc có hàng trở lại.
- Đồng bộ Wishlist với dịch vụ bên ngoài.

## 2. Schema và vòng đời dữ liệu

Bảng `wishlists` được tạo bởi migration `2026_10_22_000070_create_wishlists_table.php`:

| Cột/ràng buộc | Ý nghĩa |
| --- | --- |
| `id` | Khóa chính kỹ thuật |
| `user_id` | Chủ sở hữu, FK tới `users.id` |
| `product_id` | Sản phẩm được lưu, FK tới `products.id` |
| `created_at` | Thời điểm lưu; dùng sắp xếp mới nhất |
| unique (`user_id`, `product_id`) | Một khách chỉ lưu một sản phẩm một lần |

Xóa user hoặc product sẽ cascade xóa row Wishlist tương ứng. Product/category bị chuyển inactive thì row vẫn được giữ nhưng không xuất hiện và không được tính vào badge published. Khi sản phẩm hoạt động lại, row có thể hiển thị lại.

Không cần migration mới cho module hiện tại. Không dùng `migrate:fresh`, `migrate:refresh`, `migrate:reset` hoặc `db:wipe` trên database cần giữ dữ liệu.

## 3. Route và ranh giới bảo mật

| Method | Route | Middleware chính | Mục đích |
| --- | --- | --- | --- |
| GET | `/wishlist` | `auth`, `customer` | Danh sách owner-scoped, 12 sản phẩm/trang |
| POST | `/wishlist/{storefrontProduct}` | `auth`, `customer`, throttle | Thêm published product theo slug |
| DELETE | `/wishlist/{product}` | `auth`, `customer`, throttle | Xóa item theo product ID và owner |

Mutation add/remove là idempotent. Mọi query đều lấy user từ session; không nhận `user_id` từ browser. Add chỉ resolve published product. Remove vẫn nhận product inactive để khách có thể dọn row cũ, nhưng câu lệnh xóa luôn scope theo `user_id + product_id`.

HTML form dùng CSRF và safe internal `return_to`. JSON trả trạng thái, published count và thông báo đã dịch; không trả thông tin tài khoản. AJAX không optimistic update: giao diện chỉ đổi sau response thành công. Khi tắt JavaScript, form POST/DELETE vẫn hoạt động.

## 4. Factory và dữ liệu demo

Factory test có thể dùng độc lập:

```php
$item = Wishlist::factory()->create();

$customer = User::factory()->create();
Wishlist::factory()->count(3)->for($customer)->create();

$product = Product::factory()->create();
Wishlist::factory()->for($customer)->for($product)->create();
```

`CategoryFactory`, `ProductFactory` và `WishlistFactory` chỉ là helper; chúng không được gọi bởi `DatabaseSeeder`.

Project cố ý **không có `WishlistSeeder` mặc định**: Wishlist là dữ liệu cá nhân, không phải dữ liệu cấu hình bắt buộc. Nếu cần demo tạm thời, dùng factory trong test hoặc tạo seeder demo riêng chỉ chạy bằng lệnh `--class` trong môi trường local; không thêm seeder đó vào `DatabaseSeeder` và không chạy trên production.

## 5. Deploy an toàn

```powershell
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate
php artisan optimize:clear
php artisan route:list --name=wishlist
php artisan test --filter=Wishlist
```

`migrate --pretend` giúp xem SQL pending trước khi áp dụng; kiểm tra output theo driver MySQL thực tế. Migration Wishlist cũ sẽ được Laravel bỏ qua khi đã ghi trong bảng `migrations`.

Sau deploy, kiểm tra bằng một customer test:

1. Đăng nhập storefront, thêm một sản phẩm active.
2. Xác nhận tim và badge đổi mà không reload.
3. Mở tab thứ hai, thao tác lại và kiểm tra state server-confirmed được đồng bộ.
4. Mở `/wishlist`, xóa sản phẩm và tải lại trang.
5. Thử guest, admin và customer inactive: không được truy cập dữ liệu.

## 6. Rollback

- Rollback code: đưa code về release trước và chạy `php artisan optimize:clear`. Không xóa row Wishlist.
- Không rollback migration Wishlist trên database đang vận hành vì `down()` sẽ drop toàn bộ bảng và mất dữ liệu.
- Nếu migration mới trong tương lai cần rollback, backup database và rollback đúng batch/file đã xác minh; không dùng lệnh reset toàn bộ.
- Trước thay đổi schema production, sao lưu `wishlists` cùng `users` và `products` để giữ khả năng đối chiếu FK.

## 7. Troubleshooting

| Hiện tượng | Kiểm tra |
| --- | --- |
| Guest nhận redirect hoặc JSON 401 | Đúng contract; đăng nhập customer trước |
| Admin nhận 403 | Đúng contract; admin không phải customer storefront |
| Customer bị đưa về login | Kiểm tra `users.status=active`, session/cookie và middleware `customer` |
| Nút tim đổi chậm hoặc báo lỗi | Network/console, response JSON, CSRF 419, throttle 429 và `storage/logs/laravel.log` |
| Badge không khớp row DB | Badge chỉ đếm product và category published; kiểm tra `products.status` và `categories.status` |
| Không thêm được sản phẩm | Add chỉ nhận slug published; product/category inactive trả 404 |
| Item trùng | Kiểm tra unique index `wishlists_user_id_product_id_unique`; không xóa index để chữa lỗi ứng dụng |
| Hai tab tạm lệch state | `BroadcastChannel` cần browser hỗ trợ; reload sẽ lấy lại state chuẩn từ server |
| Route không tồn tại | Chạy `php artisan route:list --name=wishlist` và `php artisan optimize:clear` |

Không đưa email, token, session cookie, nội dung `.env` hoặc dữ liệu Wishlist thật vào log/tài liệu hỗ trợ.
