# Roadmap Trang chủ động Storefront

## 1. Mục tiêu

Chuyển `resources/views/frontend/home.blade.php` từ template hard-code sang trang chủ lấy dữ liệu thật từ catalog, hiển thị đúng locale `vi/en`, dùng URL Laravel hợp lệ và có fallback an toàn. Phase này ưu tiên tái sử dụng `products`, `product_images`, `categories`, `brands`, Settings và locale hiện có; không giả lập rating, sale, lượt bán hoặc cá nhân hóa khi chưa có dữ liệu nghiệp vụ.

## 2. Hiện trạng đã rà soát

- `HomeController::home()` chỉ trả view, chưa query dữ liệu.
- Hero gồm 1 card lớn và 5 card nhỏ; Trending gồm 5 card và 5 tab; Categories gồm 5 tile; Just for you gồm 4 card; Brand strip gồm 6 logo.
- Các link `product.html`, `shop.html`, `cart.html` là link template tĩnh, chưa phải named route Laravel.
- Schema hiện có đủ tên VI/EN, slug, mô tả, giá, tồn kho, `is_featured`, trạng thái, ảnh sản phẩm, danh mục và thương hiệu.
- Chưa có sale price, review/rating, lượt xem/lượt bán, banner/promotion động và newsletter subscriber.
- `/products` hiện chỉ là trang chi tiết mẫu, chưa có route model binding theo slug; shop/cart/wishlist vẫn là template tĩnh.

## 3. Contract dữ liệu trang chủ đã chốt

### 3.1 Quy tắc chung

- Chỉ render `products.status = true`, `categories.status = true`, `brands.status = true` trên storefront.
- Locale lấy từ `app()->getLocale()` sau middleware; trường EN rỗng fallback VI, không phát sinh query trong Blade.
- Giá luôn qua `MoneyFormatter`; không hard-code `$` hoặc tự format trong view.
- Ảnh ưu tiên `product_images.is_primary`, sau đó `sort_order`, `id`; category/brand dùng `image_src`; thiếu ảnh dùng placeholder local có alt text phù hợp.
- CTA dùng named route. Trước khi có thao tác cart thật, nút mua từ card dẫn đến chi tiết sản phẩm, không giả lập thêm giỏ hàng.
- Mỗi block thiếu dữ liệu phải giảm số card hoặc ẩn block; không tạo dữ liệu giả trong Blade.
- Query phải eager-load quan hệ, có giới hạn rõ ràng và được kiểm thử N+1/query count.

### 3.2 Hero Bento

- Kích thước: tối đa 6 sản phẩm, vị trí đầu là card lớn.
- Nguồn MVP: sản phẩm published có ảnh, ưu tiên `is_featured = true`, sau đó mới nhất.
- Eyebrow dùng tên category; tiêu đề/mô tả/ảnh/link lấy từ sản phẩm theo locale.
- Không đưa theme marketing vào bảng products. Theme vị trí giữ tại presentation config trong MVP; module banner quản trị động là bước nâng cấp riêng.
- Nếu không đủ 6 sản phẩm: render số lượng hiện có; nếu không có sản phẩm thì ẩn Hero và hiển thị empty state trang chủ gọn.

### 3.3 Trending Products và tab

- Kích thước ban đầu: 5 sản phẩm.
- “Trending” trong phase này được định nghĩa là published + ưu tiên featured + còn hàng + mới nhất; không gọi là bán chạy dựa trên dữ liệu giả.
- Tab lấy tối đa 5 category active có published product, sắp xếp `sort_order`, tên và id ổn định.
- Tab đầu render từ server; các tab khác có thể tải qua endpoint AJAX ở Task 9.6, luôn có fallback link shop theo category.
- Card chưa hiển thị rating/review hoặc sale badge khi schema chưa hỗ trợ.

### 3.4 Discount Banners

- Phase MVP giữ layout nhưng nội dung phải lấy từ cấu hình presentation/translation và link tới category/product thật.
- Không hiển thị phần trăm giảm hoặc giá gạch ngang nếu chưa có promotion hợp lệ.
- Task 9.7 mới bổ sung schema/CRUD banner-promotion có thời gian hiệu lực; không nhồi dữ liệu marketing vào products.

### 3.5 Shop by Category

- Tối đa 5 category active có published product.
- Sắp xếp `sort_order`, tên locale, id; đếm `published_products_count`, không đếm sản phẩm inactive.
- Link đến `shop` với filter slug; ảnh từ `image_src`, thiếu ảnh dùng placeholder.

### 3.6 Just for You

- Kích thước: 4 sản phẩm published, còn hàng và không trùng Trending trong cùng response.
- Hiện chưa phải recommendation engine: fallback là featured còn lại, rồi sản phẩm mới nhất.
- Khi chưa có lịch sử hành vi storefront, UI dùng nhãn “Gợi ý cho bạn”, không tuyên bố cá nhân hóa sâu.

### 3.7 Brand Strip

- Tối đa 6 brand active có published product; sắp xếp theo tên locale và id.
- Ưu tiên logo; thiếu logo hiển thị tên; link shop theo brand slug.
- Không render brand không còn sản phẩm công khai.

### 3.8 Newsletter

- Form JavaScript giả hiện tại chưa được xem là chức năng hoàn chỉnh.
- Task 9.8 sẽ dùng bảng subscriber riêng, email unique, locale, consent/status, honeypot, throttle và progressive enhancement.
- Không hứa coupon/giảm 20% trước khi có nghiệp vụ coupon thật.

### 3.9 Ranh giới cache và bảo mật

- Có thể cache DTO/collection theo locale, không cache HTML chứa CSRF token.
- Cache phải invalid khi product/image/category/brand hoặc presentation source thay đổi.
- Product inactive và media thuộc product inactive phải tiếp tục trả 404 cho storefront.
- Nội dung database luôn escaped mặc định trong Blade; không render raw HTML marketing tùy ý.

## 4. Kiến trúc mục tiêu

```text
HomeController
    -> StorefrontHomeService
        -> Product/Category/Brand queries
        -> StorefrontProductPresenter / HomePageData
    -> frontend.home
        -> home partials
        -> shared product-card component
```

Controller chỉ điều phối. Service query và chọn tập dữ liệu. Presenter chuẩn hóa locale, ảnh, URL, giá và stock state. Blade chỉ render, không query database.

## 5. Kế hoạch task

### [x] Task 9.1 — Chốt contract dữ liệu trang chủ

**Dependency:** Catalog, Settings, Menu và locale storefront hiện có.

- [x] Kiểm kê đầy đủ các block và số lượng item của template.
- [x] Ánh xạ nguồn dữ liệu hiện có cho Hero, Trending, Categories, Suggestions và Brands.
- [x] Chốt published/featured/in-stock, locale, ảnh, giá, URL và fallback.
- [x] Xác định rõ dữ liệu chưa có: promotion, rating, analytics và newsletter.
- [x] Chốt ranh giới service/presenter/Blade, cache và security.

**Kết quả cần đạt:** mỗi block có contract truy vấn/render rõ ràng, không giả dữ liệu nghiệp vụ và đủ cơ sở để triển khai model scope/service.

**Kế hoạch test:** baseline Storefront Locale, Menu và Settings; dashboard syntax; rà named route/schema/model thực tế.

**Kết quả triển khai:** đã chốt contract tại các mục 2–4. Baseline storefront đạt 31 test/197 assertions; không thay đổi runtime, route, schema hoặc database.

### [x] Task 9.2 — Product scopes, localized presenter và ảnh chính

**Dependency:** Task 9.1.

- [x] Thêm scope published, in-stock, featured mà không phá admin query.
- [x] Thêm relation/selector ảnh chính có thứ tự fallback xác định.
- [x] Chuẩn hóa name/description theo locale với fallback VI.
- [x] Tạo presenter/DTO cho URL, ảnh, giá, stock và badge hợp lệ.
- [x] Kiểm thử scope, escaping, locale fallback và query count.

**Kết quả cần đạt:** có một contract card sản phẩm dùng chung, không query trong Blade.

**Kế hoạch test:** unit/feature cho published scope, primary image, vi/en fallback, inactive/empty image và MoneyFormatter.

**Kết quả triển khai:** `Product` có scope `published`, `inStock`, `featured` tách biệt query admin; published yêu cầu product và category cùng active. `primaryImage` ưu tiên cờ primary rồi `sort_order`, `id`. `StorefrontProductPresenter` tạo payload card không lazy-load, fallback EN → VI, dùng `MoneyFormatter`, chuẩn hóa tồn kho/badge, URL media an toàn và placeholder local. Test mục tiêu đạt 5 test/21 assertions; regression liên quan đạt 39 test/235 assertions; toàn dự án đạt 272 test/2.770 assertions; PHP/static checks đạt.

### [x] Task 9.3 — Home service, controller và cache dữ liệu

**Dependency:** Task 9.2.

- [x] Tạo `StorefrontHomeService` và cấu trúc `HomePageData`.
- [x] Query Hero, Trending, tab categories, Suggestions, Categories và Brands theo contract.
- [x] Loại trùng Trending/Suggestions và giới hạn item ổn định.
- [x] Eager-load, giới hạn query và cache DTO theo locale.
- [x] Truyền dữ liệu từ `HomeController` sang view; thêm invalidation phù hợp.

**Kết quả cần đạt:** trang chủ nhận đầy đủ dữ liệu thật qua một service có thể kiểm thử.

**Kế hoạch test:** content selection, empty state, duplicate guard, inactive exclusion, cache hit/invalidation và query bound.

**Kết quả triển khai:** `StorefrontHomeService` tạo một product pool published đã eager-load để cấp dữ liệu Hero, Trending và Suggestions; Suggestions không trùng Trending. Categories/Brands chỉ lấy bản ghi active có product published, có count, URL và locale fallback. `HomePageData` được cache riêng theo vi/en; sự kiện saved/deleted của Product, ProductImage, Category và Brand tự xóa cache. `HomeController` truyền DTO sang view. Test mục tiêu đạt 10 test/49 assertions, regression liên quan đạt 54 test/369 assertions và toàn dự án đạt 277 test/2.798 assertions.

### [x] Task 9.4 — Component hóa và render các block trang chủ

**Dependency:** Task 9.3 và route thật tối thiểu từ Task 9.5 nếu CTA cần chi tiết sản phẩm.

- [x] Tách Hero, Trending, Discounts, Categories, Suggestions, Brands và Newsletter thành partial.
- [x] Tạo product-card component dùng chung.
- [x] Thay link `.html` bằng named route.
- [x] Render đúng vi/en, MoneyFormatter, ảnh/empty state và responsive layout.
- [x] Không query database hoặc chứa logic nghiệp vụ trong Blade.

**Kết quả cần đạt:** toàn bộ block trang chủ render dữ liệu thật và giữ giao diện template.

**Kế hoạch test:** feature rendering vi/en, escaping, link, image, empty state và visual QA desktop/tablet/mobile.

**Kết quả triển khai:** trang chủ đã được tách thành 7 partial và một product-card dùng chung, render hoàn toàn từ `HomePageData` với nội dung VI/EN, giá/ảnh/stock/brand/category thật, named route, escaping mặc định và empty state. Discount MVP chỉ giới thiệu category thật, không giả phần trăm giảm; Newsletter chỉ hiển thị trạng thái sắp ra mắt, không còn form JavaScript giả. Cache DTO được nâng lên `storefront.home.v2` để tránh payload cũ thiếu trường khi triển khai. Test mục tiêu đạt 13 test/73 assertions; regression storefront đạt 44 test/270 assertions; toàn dự án đạt 280 test/2.822 assertions. Visual QA đạt ở desktop 1.707px và mobile khoảng 510px.

### [x] Task 9.5 — Shop filter và chi tiết sản phẩm thật

**Dependency:** Task 9.2.

- [x] Thêm route chi tiết theo product slug và route model binding an toàn.
- [x] Chỉ cho phép xem product published; inactive trả 404.
- [x] Shop hỗ trợ category, brand, search, sort và pagination đã validate.
- [x] Chi tiết sản phẩm và related products dùng dữ liệu thật.
- [x] CTA trang chủ/shop dùng named route; chưa giả add-to-cart.
- [x] Nâng sidebar về checkbox đa lựa chọn, khoảng giá và bố cục template gốc.
- [x] Lọc AJAX có loading/error state, URL history và fallback GET khi tắt JavaScript.

**Kết quả cần đạt:** mọi CTA catalog từ trang chủ có đích Laravel hợp lệ.

**Kế hoạch test:** slug 200/404, filters, pagination, locale, related products và route list.

**Kết quả triển khai:** route `products.show` dùng slug binding riêng cho storefront và chỉ resolve product published; route `/products` cũ redirect an toàn về Shop. `StorefrontCatalogRequest` validate search, nhiều category/brand, khoảng giá, sort, per-page và page; `StorefrontCatalogService` thực hiện query eager-load, OR trong từng nhóm category/brand, AND giữa các nhóm, locale search/name sort, pagination và related products. Shop/detail render dữ liệu thật, dùng product-card chung và không giả rating/review/cart. Sidebar dùng checkbox có số lượng, dual range giá, AJAX progressive enhancement với debounce, hủy stale request, loading tối thiểu 260ms, error state, đồng bộ URL/history và fallback GET khi tắt JavaScript. Test mục tiêu Catalog đạt 7 test/61 assertions; toàn dự án đạt 287 test/2.884 assertions; route, Blade, JavaScript, diff check và visual QA mobile khoảng 510px đạt.

### [x] Task 9.6 — Trending tabs bằng AJAX progressive enhancement

**Dependency:** Task 9.3–9.5.

- [x] Endpoint read-only validate category active/published.
- [x] Trả partial card dùng chung, không nhân đôi markup.
- [x] Loading, error, empty state và chống stale response.
- [x] Keyboard/ARIA tab state và touch responsive.
- [x] Không có JavaScript vẫn điều hướng shop filter được.

**Kết quả cần đạt:** đổi category nhanh, accessible và không tải dư toàn bộ catalog.

**Kế hoạch test:** endpoint validation, inactive category, HTML partial, query count, JS syntax và no-JS fallback.

**Kết quả triển khai:** endpoint `home.trending` chỉ nhận category active có product published, trả tối đa 5 product còn hàng theo thứ tự featured/mới nhất qua partial dùng lại `product-card`. Tab đầu render đúng category từ server; các tab còn lại dùng AJAX có cache phía trình duyệt, loading tối thiểu 220ms, AbortController chống stale response, error/empty state và không đổi URL trang. ARIA tablist/tab/tabpanel, phím mũi tên/Home/End/Space, focus style và thanh tab cuộn ngang hỗ trợ keyboard/touch; link tab vẫn dẫn tới Shop filter khi JavaScript tắt. Cache Home nâng namespace `storefront.home.v4` để loại payload tab cũ. Test mục tiêu đạt 17 test/104 assertions; toàn dự án đạt 291 test/2.915 assertions; route, PHP, Blade, JavaScript, diff check và visual QA desktop đạt.

### [x] Task 9.7 — Banner và promotion động

**Dependency:** Task 9.1; có thể triển khai sau MVP trang chủ.

- [x] Chốt schema banner/promotion tách khỏi products.
- [x] Hỗ trợ nội dung VI/EN, theme, link allowlist, vị trí, thứ tự và thời gian hiệu lực.
- [x] CRUD admin theo RBAC/audit hiện có.
- [x] Render Hero/Discount từ dữ liệu published với fallback an toàn.
- [x] Cache invalidation và vận hành/backfill.

**Kết quả cần đạt:** marketing có thể quản trị mà không sửa Blade hoặc làm sai giá sản phẩm.

**Kế hoạch test:** permission, schedule boundary, safe link, locale fallback, audit/cache và empty state.

**Kết quả triển khai:** bảng `home_banners` tách hoàn toàn khỏi Product, quản lý hai placement `hero`/`discount`, nội dung VI/EN, theme allowlist, thứ tự, trạng thái và khoảng hiệu lực `[starts_at, ends_at)`. Liên kết chỉ nhận Shop/filter hoặc Product nội bộ; URL ngoài và JavaScript URL bị từ chối. Admin có danh sách/filter/CRUD, nhóm permission `banners.*`, sidebar, audit nguyên tử và seeder permission không ghi đè role đã tùy chỉnh. Storefront lấy tối đa 6 Hero và 2 Discount đang published, locale fallback an toàn; nếu chưa có dữ liệu thì giữ catalog fallback hiện tại. Mutation HomeBanner invalid cache và cache Home nâng namespace `v5`; triển khai không cần backfill hay nội dung marketing giả. Migration đã chạy batch 6, target liên quan đạt 39 test/292 assertions, toàn dự án đạt 297 test/2.953 assertions; route, PHP, Blade, JavaScript, dashboard và diff checks đạt.

### [x] Task 9.8 — Newsletter subscription

**Dependency:** Task 9.4.

- [x] Migration/model subscriber riêng, email unique và status/consent timestamps.
- [x] Form Request, controller, POST route, honeypot và throttle.
- [x] AJAX progressive enhancement và response không lộ dữ liệu đăng ký.
- [x] Lưu locale/source; không hứa coupon khi chưa có coupon engine.
- [x] Chuẩn bị unsubscribe an toàn.

**Kết quả cần đạt:** form newsletter lưu đăng ký thật, chống spam cơ bản và có thể vận hành.

**Kế hoạch test:** valid/duplicate/invalid, CSRF, throttle, honeypot, locale, AJAX/fallback và migration status.

**Kết quả triển khai:** đã tạo bảng `newsletter_subscribers` độc lập với email chuẩn hóa/unique, locale, source, trạng thái, thời điểm consent/unsubscribe và token unsubscribe lưu hash một chiều. Endpoint POST thuộc middleware storefront locale, CSRF web và rate limiter 5 request/phút/IP; honeypot và consent được validate. Đăng ký trùng có tính idempotent và luôn trả cùng thông báo chung để không lộ email đã tồn tại. Form trang chủ hoạt động bằng POST/redirect khi không có JavaScript và được tăng cường AJAX với loading, lỗi 422, ARIA live feedback; nội dung không hứa coupon. Migration đã chạy batch 7. Newsletter đạt 9 test/43 assertions, Homepage đạt 17 test/105 assertions và toàn dự án đạt 307 test/3.004 assertions.

### [x] Task 9.9 — Quality gate trang chủ động

**Dependency:** Task 9.2–9.8 theo phạm vi phase được chốt.

- [x] Kiểm thử published/inactive, locale, images, price và named route.
- [x] Kiểm thử block selection, empty state, cache/invalidation và N+1.
- [x] Kiểm thử promotion/newsletter nếu đã đưa vào phase release.
- [x] Visual QA desktop/tablet/mobile, keyboard và no-JS.
- [x] Chạy full regression và cập nhật tài liệu vận hành.

**Kết quả cần đạt:** trang chủ dùng dữ liệu thật, an toàn, responsive và không phá module hiện có.

**Kế hoạch test:** targeted storefront suites, route list, migration status khi có migration, static checks và full `php artisan test`.

**Kết quả triển khai:** quality gate đã xác nhận published/inactive boundary, locale VI/EN và fallback, media/price/named route, block selection/empty state, cache invalidation cùng query budget; Banner schedule/security và Newsletter valid/duplicate/invalid/CSRF/throttle/honeypot/AJAX-fallback đều đạt. Visual QA đạt trên mobile khoảng 510px, tablet khoảng 930px và desktop khoảng 1.700px; accessibility tree nhận đúng region, email field, submit button và consent checkbox, còn no-JS được xác nhận bằng POST/redirect feature test. Bảy suite storefront đạt 70 test/445 assertions; toàn dự án đạt 307 test/3.004 assertions. Toàn bộ 23 migration ở trạng thái Ran; route audit, Blade compile, JavaScript/dashboard syntax và diff check đều đạt. Hướng dẫn vận hành được ghi tại `docs/storefront-home.md`.

## 6. Thứ tự triển khai

```text
9.1 -> 9.2 -> 9.3 -> 9.5 -> 9.4 -> 9.6
                         \-> 9.7 -> 9.8 -> 9.9
```

Task 9.7–9.8 có thể tách release sau MVP, nhưng không được giả lập promotion/newsletter là đã hoạt động trước khi schema và backend tương ứng hoàn thành.

