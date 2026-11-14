# Vận hành trang chủ động Storefront

## Phạm vi dữ liệu

- Hero và Discount Banner lấy từ `home_banners`; khi không có banner đang phát hành, trang chủ dùng catalog fallback.
- Trending, Category, Suggestion và Brand chỉ lấy product/category/brand đang hoạt động.
- Product card dùng ảnh chính có thứ tự xác định, giá qua `MoneyFormatter` và URL Laravel theo slug.
- Newsletter lưu email đã chuẩn hóa, locale, source, consent và trạng thái trong `newsletter_subscribers`.
- Giao diện hỗ trợ locale `vi/en`; trường tiếng Anh trống sẽ fallback về tiếng Việt.

## Kiểm tra vận hành

```powershell
php artisan migrate:status
php artisan route:list --path=home
php artisan route:list --path=newsletter
php artisan test --filter='StorefrontHomepageTest|StorefrontCatalogTest|AdminHomeBannerTest|NewsletterSubscriptionTest'
php artisan test
node --check public/assets/frontend/js/main.js
```

Không chạy `migrate:fresh` trên database đang sử dụng. Hai migration storefront mới phải ở trạng thái `Ran`:

- `2026_11_12_000010_create_home_banners_table`
- `2026_11_13_000010_create_newsletter_subscribers_table`

## Cache và cập nhật nội dung

- Product, ProductImage, Category, Brand và HomeBanner mutation sẽ invalid cache trang chủ qua observer hiện có.
- Banner được phát hành theo khoảng `starts_at <= now < ends_at` và phải dùng liên kết nội bộ an toàn.
- Nếu giao diện chưa phản ánh Blade mới, chạy `php artisan view:clear`; không cần xóa dữ liệu catalog.

## Newsletter

- Endpoint: `POST /newsletter/subscribe`.
- Form dùng CSRF, consent bắt buộc, honeypot và throttle 5 request/phút/IP.
- Email trùng không tạo bản ghi mới và luôn nhận cùng thông báo để không lộ membership.
- JavaScript chỉ tăng cường UX; POST/redirect vẫn hoạt động khi JavaScript tắt.
- Task hiện tại chưa gửi campaign, chưa có double opt-in, admin subscriber UI hoặc endpoint unsubscribe công khai. Model đã chuẩn bị token unsubscribe lưu hash một chiều cho phase email marketing sau.

## Baseline quality gate

Ngày 13/11/2026:

- Storefront targeted suites: 70 test, 445 assertions.
- Full regression: 307 test, 3.004 assertions.
- Visual QA đạt ở mobile khoảng 510px, tablet khoảng 930px và desktop khoảng 1.700px.
- Blade compile, JavaScript syntax, dashboard syntax, route audit, migration status và diff check đều đạt.
