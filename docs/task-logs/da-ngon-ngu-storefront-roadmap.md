# Roadmap đa ngôn ngữ Việt–Anh cho storefront

## 1. Mục tiêu

Xây dựng cơ chế đổi ngôn ngữ động `vi/en` cho toàn bộ giao diện khách hàng, dùng chung với Menu động và System Settings hiện có. Người dùng chọn ngôn ngữ bằng dropdown giống mẫu tham khảo; lựa chọn được giữ trong session, áp dụng từ request kế tiếp và không làm mất trang đang xem, query string hoặc dữ liệu form hợp lệ.

Phạm vi phase này:

- Chỉ storefront; giao diện admin tiếp tục dùng tiếng Việt.
- Hai locale được phép: `vi`, `en`.
- Không đổi schema database và không nhân đôi route theo locale trong phase đầu.
- Dữ liệu động tiếp tục dùng cặp cột/key `_vi`, `_en`; chuỗi giao diện tĩnh dùng translation files.
- Không dịch nội dung do khách hàng nhập, snapshot đơn hàng hoặc dữ liệu không có bản dịch.

## 2. Hiện trạng đã rà soát

- `config/app.php` mặc định `vi`, nhưng chưa có middleware đọc locale từ session.
- `config/menus.php` đã khai báo `vi/en`; MenuService và StorefrontMenuPresenter đã cache/render theo locale.
- `SystemSettingPresenter` đã chọn `site.name`, tagline, SEO, địa chỉ và giờ làm việc theo locale.
- `site.default_locale` đang ở trạng thái `planned_consumer`; chưa chi phối request storefront.
- Route `GET /language` hiện gọi `HomeController::language()` và chỉ dự kiến trả view tĩnh; chưa có logic đổi locale.
- `lang/vi/frontend.php` và `lang/en/frontend.php` mới có 4 chuỗi; phần lớn layout/header/footer/page vẫn hard-code tiếng Anh.
- Header chưa có dropdown ngôn ngữ; layout chỉ có link `EN · USD`.
- Baseline liên quan storefront/Menu/Settings/Contact: `25 passed / 166 assertions`.

## 3. Contract đề xuất để duyệt

### 3.1 Nguồn locale và thứ tự ưu tiên

1. Locale hợp lệ trong session `storefront_locale`.
2. Setting `site.default_locale` nếu là `vi` hoặc `en`.
3. `config('app.locale', 'vi')` làm fallback cuối.

Middleware chỉ áp dụng cho nhóm `web`, đặt locale trước khi controller và view composer chạy. Không lấy locale trực tiếp từ query string ở mọi request để tránh input không kiểm soát và URL trùng SEO.

### 3.2 Endpoint đổi ngôn ngữ

- Đề xuất `POST /language` với CSRF, payload `locale=vi|en` và rate limit nhẹ.
- Lưu locale vào session rồi redirect về URL nội bộ trước đó.
- Chỉ chấp nhận relative/internal same-origin return URL; không redirect tới domain ngoài.
- Giữ `GET /language` cũ làm redirect tương thích về trang chủ hoặc loại bỏ sau khi kiểm tra không còn consumer.
- Không dùng `GET` để thay đổi session nhằm tránh crawler/prefetch vô tình đổi ngôn ngữ.

### 3.3 Phạm vi dịch

- Translation keys theo ngữ nghĩa, ví dụ `frontend.header.search_placeholder`, không dùng nguyên câu tiếng Anh làm key mới.
- Dịch shared chrome trước: utility bar, header, search, nav labels, mobile drawer, footer và accessibility labels.
- Sau đó dịch từng trang hiện có: home, shop, products, cart, contact và các trang help/track-order/account khi view tồn tại.
- Validation/flash message công khai phải có bản dịch phù hợp; admin/audit message không đổi trong phase này.

### 3.4 UI dropdown ngôn ngữ

- Component dùng chung đặt ở utility bar desktop; phiên bản mobile nằm trong drawer.
- Nút hiện flag + tên/viết tắt locale hiện tại + chevron.
- Menu có hai lựa chọn `Tiếng Việt` và `English`, flag rõ ràng, đánh dấu item hiện tại.
- Hỗ trợ click ngoài, `Escape`, focus, `aria-expanded`, `aria-current` và điều khiển bàn phím.
- Dùng icon/flag nội bộ hoặc inline SVG, không phụ thuộc CDN ảnh ngoài.
- Nếu JavaScript tắt, các lựa chọn vẫn submit bằng form chuẩn.

### 3.5 Cache và SEO

- Menu cache tiếp tục tách theo locale như hiện tại.
- System Settings typed cache không nhân đôi; presenter chọn localized value sau khi lấy map.
- `<html lang>`, title, meta description và OG phải đổi đúng locale trong cùng request.
- Vì URL chưa có prefix locale, chưa thêm `hreflang`; đây là giới hạn được ghi rõ. Nếu SEO đa ngôn ngữ theo URL là yêu cầu sau này, mở phase `/vi/...` và `/en/...` riêng.

## 4. Tasks triển khai

### [x] Task 8.1 — Chốt contract và kiểm kê chuỗi storefront

**Dependency:** Không có.

- [x] Chốt session key, allowlist locale và thứ tự fallback.
- [x] Chốt POST endpoint, redirect-back guard và tương thích route `/language` cũ.
- [x] Kiểm kê toàn bộ Blade storefront, route/view đang thiếu và chuỗi hard-code.
- [x] Phân loại chuỗi tĩnh, dữ liệu động có `_vi/_en`, dữ liệu không được dịch.
- [x] Chốt UI desktop/mobile, accessibility và phạm vi SEO.

**Kết quả cần đạt:** contract đủ rõ để middleware, endpoint, translation và UI dùng cùng một quy tắc.

**Kế hoạch test:** baseline route/view, locale allowlist, inventory translation keys và xác nhận dependency Menu/Settings.

#### Kết quả chốt Task 8.1

**Contract request locale**

- Locale hỗ trợ cố định trong phase này là `['vi', 'en']`; mã locale được chuẩn hóa chữ thường trước khi đối chiếu allowlist.
- Session key duy nhất là `storefront_locale`.
- Thứ tự resolve là session hợp lệ → `site.default_locale` hợp lệ → `config('app.locale')` hợp lệ → `vi`.
- Locale storefront chỉ áp dụng cho nhóm route công khai của storefront. Route `admin.*`, account quản trị và các trang template admin không dùng session locale này.
- Middleware phải chạy sau `StartSession` và trước controller/View Composer để Menu và System Settings nhận cùng locale trong một request.

**Contract chuyển ngôn ngữ và redirect**

- Endpoint chính là `POST /language`, route name `language.update`, có CSRF và chỉ nhận `locale=vi|en`.
- Form có thể gửi `return_url` là URL tương đối hoặc absolute URL cùng origin. Backend chỉ redirect khi scheme là `http/https`, host và port khớp request hiện tại, đồng thời path không thuộc `/admin`; trường hợp không hợp lệ quay về `url()->previous()` nếu an toàn, cuối cùng fallback về route `home`.
- Query string nội bộ được giữ nguyên; fragment không được backend tin cậy hoặc xử lý vì fragment không được gửi trong HTTP request.
- `GET /language` cũ không thay đổi session và được chuyển thành redirect về `home` trong giai đoạn tương thích. Không giữ view `frontend.language` vì file này hiện không tồn tại.

**Kiểm kê route và view**

| Route storefront | View/controller hiện tại | Trạng thái | Hướng xử lý |
|---|---|---|---|
| `/` | `frontend.home` | Có view, 310 dòng, chưa dùng translation key | Dịch ở 8.5–8.6 |
| `/shop` | `frontend.shop` | Có view, 176 dòng, chưa dùng translation key | Dịch ở 8.6 |
| `/products` | `frontend.products` | Có view, 200 dòng, chưa dùng translation key | Dịch ở 8.6 |
| `/cart` | `frontend.cart` | Có view, 128 dòng, chưa dùng translation key | Dịch ở 8.6 |
| `/contact` | `frontend.contact` | Có view, 133 dòng, chưa dùng translation key | Dịch UI, validation và flash ở 8.6 |
| `/track-order` | `frontend.track-order` | Thiếu view | Không tạo placeholder trong 8.1; xử lý theo phạm vi 8.6 |
| `/help` | `frontend.help` | Thiếu view | Không tạo placeholder trong 8.1; xử lý theo phạm vi 8.6 |
| `/language` | `frontend.language` | Thiếu view, contract cũ không còn phù hợp | Đổi thành endpoint/redirect ở 8.3 |
| `/account` | `frontend.account` | Thiếu view | Xử lý theo phạm vi 8.6 |

Toàn bộ thư mục storefront hiện có 13 Blade với 1.184 dòng và chỉ 8 lượt gọi translation. Hai file `lang/vi/frontend.php`, `lang/en/frontend.php` mới có 4 key dạng nguyên câu; sẽ chuyển sang namespace ngữ nghĩa và giữ alias tạm thời nếu còn consumer.

**Ma trận nội dung**

| Loại nội dung | Nguồn chuẩn | Ví dụ | Quy tắc |
|---|---|---|---|
| Chuỗi UI tĩnh | `lang/{vi,en}/frontend.php` | nút, heading, breadcrumb, placeholder, `aria-label`, empty state | Bắt buộc dùng key ngữ nghĩa `frontend.*` |
| Dữ liệu động song ngữ | Model/Settings/Menu với cặp `_vi/_en` hoặc presenter hiện có | tên menu, site name, tagline, SEO, địa chỉ, giờ làm việc | Chọn theo locale request; thiếu EN fallback VI |
| Nhãn enum công khai | Translation catalog theo enum key ổn định | lý do liên hệ `order`, `warranty` | Không dùng label tiếng Việt trong model làm output storefront |
| Validation/flash storefront | Form Request/controller qua translation key | lỗi contact, thông báo gửi thành công | Dịch theo locale request; không ảnh hưởng message admin |
| Dữ liệu không dịch | Database/snapshot/input người dùng | SKU, mã đơn, email, điện thoại, giá, tên sản phẩm demo chưa có cột EN, nội dung liên hệ | Giữ nguyên và escape khi render |

Các điểm hard-code ưu tiên cao gồm utility/header/footer, search và accessibility label; các section/card của home; filter/sort shop; product detail/review; cart summary; toàn bộ contact form. `Contact::REASONS`, message trong `StoreContactRequest` và flash trong `ContactController` cũng là consumer cần chuyển sang translation key.

**Contract UI, accessibility và SEO**

- Desktop đặt language switcher trong utility bar; mobile đặt cùng component trong drawer, không nhân đôi logic locale.
- Trigger hiển thị cờ + tên/viết tắt locale hiện tại + chevron. Menu có `Tiếng Việt` và `English`, item hiện tại dùng `aria-current="true"` và không submit lại locale đang chọn.
- Component dùng button/form thật, hỗ trợ Tab, Enter/Space, Escape, click ngoài, focus trả về trigger, `aria-expanded`, `aria-controls`; khi không có JavaScript hai nút submit POST vẫn hoạt động.
- Cờ dùng asset nội bộ hoặc inline SVG có tính trang trí (`aria-hidden="true"`); tên ngôn ngữ là nhãn truy cập chính.
- `<html lang>`, title, description và Open Graph đổi theo locale trong cùng request. Canonical vẫn là URL hiện tại; phase session-locale không phát hành `hreflang` và không tạo URL `/vi`, `/en`.
- Currency độc lập với locale và tiếp tục lấy từ System Settings/MoneyFormatter; không ghép cố định `EN · USD`.

**Ranh giới triển khai đã chốt:** Task 8.1 chỉ hoàn thiện contract/inventory, không thay đổi runtime. Middleware bắt đầu ở 8.2, endpoint ở 8.3, UI ở 8.4 và nội dung dịch ở 8.5–8.6.

### [x] Task 8.2 — Locale resolver và middleware session

**Dependency:** Task 8.1.

- [x] Tạo service/resolver chọn locale theo session → setting → config.
- [x] Tạo middleware gọi `App::setLocale()` trước controller/view composer.
- [x] Đăng ký middleware đúng vị trí trong group `web` sau session start.
- [x] Đưa `site.default_locale` từ planned sang active consumer.
- [x] Không áp dụng session storefront vào hành vi ngôn ngữ admin nếu không được yêu cầu.

**Kết quả cần đạt:** mọi request storefront có locale ổn định trước khi Menu và Settings được compose.

**Kế hoạch test:** session hợp lệ, session sai, setting default, config fallback, request kế tiếp và isolation admin.

**Kết quả triển khai:** `StorefrontLocaleResolver` chuẩn hóa allowlist và resolve theo contract; middleware alias `storefront.locale` được gắn sau middleware `web` lên đúng nhóm route công khai. Middleware khôi phục locale trước đó trong `finally` để không rò locale sang admin/request kế tiếp trên long-running worker. `site.default_locale` đã chuyển sang `active_consumer`. Suite mục tiêu đạt 29 test/179 assertions; full regression đạt 254 test/2.683 assertions.

### [x] Task 8.3 — Endpoint đổi ngôn ngữ và redirect an toàn

**Dependency:** Task 8.2.

- [x] Tạo Form Request validate `locale` theo allowlist.
- [x] Tạo controller/action lưu session và redirect về trang nguồn.
- [x] Chặn open redirect, URL ngoài, scheme lạ và admin return URL nếu cần.
- [x] Giữ query string của trang nội bộ hợp lệ.
- [x] Áp dụng CSRF và throttle phù hợp; xử lý route GET cũ có chủ đích.

**Kết quả cần đạt:** đổi ngôn ngữ an toàn, không mất ngữ cảnh trang và không tạo lỗ hổng redirect.

**Kế hoạch test:** vi/en thành công, locale lạ 422/redirect lỗi, external return URL, query string, CSRF và session persistence.

**Kết quả triển khai:** `POST /language` lưu locale hợp lệ vào session, giữ URL/query nội bộ và throttle 20 request/phút. Redirect guard chỉ nhận relative URL an toàn hoặc absolute URL đúng scheme/host/port hiện tại, chặn credentials, protocol-relative, control character, backslash, scheme lạ, domain ngoài và đường dẫn admin kể cả dạng URL-encoded. Referer chỉ được dùng sau cùng một kiểm tra; fallback cuối là trang chủ. `GET /language` cũ chỉ redirect trang chủ và không đổi session. Suite locale đạt 16 test/57 assertions; regression storefront đạt 40 test/221 assertions; toàn dự án đạt 265 test/2.725 assertions.

### [x] Task 8.4 — Dropdown ngôn ngữ responsive theo mẫu

**Dependency:** Task 8.3.

- [x] Tạo Blade component/partial dùng chung cho desktop và mobile.
- [x] Hiển thị flag, nhãn locale hiện tại, chevron và trạng thái active.
- [x] Styling đồng bộ template, không vỡ utility bar trên tablet/mobile.
- [x] JavaScript hỗ trợ toggle, click ngoài, Escape, focus và `aria-expanded`.
- [x] Form fallback hoạt động khi JavaScript tắt.

**Kết quả cần đạt:** dropdown trực quan giống mẫu tham khảo và dùng được bằng chuột, cảm ứng, bàn phím.

**Kế hoạch test:** markup/ARIA, hai locale, active item, desktop/mobile, no-JS submit và visual QA các breakpoint.

**Kết quả triển khai:** đã tạo partial selector dùng chung với cờ Việt Nam/Anh dạng SVG nội tuyến, trạng thái hiện tại, form POST có CSRF và fallback `<noscript>`. Desktop đặt trong utility bar, mobile đặt trong drawer; JavaScript hỗ trợ click/touch, click ngoài, Escape, focus, phím mũi tên/Home/End và đồng bộ `aria-expanded`. Visual QA xác nhận dropdown không bị che bởi sticky header, hiển thị cân đối ở desktop 1.707 px và mobile 510 px. Suite mục tiêu đạt 42 test/245 assertions; toàn dự án đạt 267 test/2.749 assertions; JavaScript syntax đạt.

### [ ] Task 8.5 — Chuẩn hóa translation catalog và shared layout

**Dependency:** Task 8.1–8.4.

- [ ] Chuyển translation keys hiện tại sang namespace ngữ nghĩa, giữ alias nếu cần tương thích.
- [ ] Dịch utility bar, header, search, primary nav labels, drawer, footer và accessibility text.
- [ ] Loại hard-code `EN · USD`; currency hiển thị từ Settings, language từ locale resolver.
- [ ] Đảm bảo Menu động và branding/SEO đổi đồng bộ với locale.
- [ ] Kiểm tra escaping và không đưa HTML tùy ý vào translation.

**Kết quả cần đạt:** toàn bộ chrome storefront đổi ngôn ngữ nhất quán trong một request.

**Kế hoạch test:** snapshot chuỗi vi/en, `<html lang>`, title/meta/OG, Menu labels, header/footer và fallback thiếu bản dịch.

### [ ] Task 8.6 — Dịch nội dung trang và form công khai

**Dependency:** Task 8.5.

- [ ] Dịch home, shop, products, cart và contact.
- [ ] Rà/tạo view help, track-order, account đang được route tham chiếu nhưng chưa có file nếu đúng phạm vi nghiệp vụ.
- [ ] Dịch label, placeholder, button, empty state, validation và flash message công khai.
- [ ] Giữ nguyên dữ liệu người dùng, mã đơn, SKU, email, số điện thoại và snapshot nghiệp vụ.
- [ ] Chuẩn hóa pluralization/format số nếu có nội dung đếm.

**Kết quả cần đạt:** không còn chuỗi giao diện chính bị trộn Việt–Anh trên các trang storefront đang hoạt động.

**Kế hoạch test:** mỗi route ở vi/en, form contact hợp lệ/không hợp lệ, escaping, fallback và view-not-found regression.

### [ ] Task 8.7 — Vận hành, cache và tài liệu

**Dependency:** Task 8.2–8.6.

- [ ] Cập nhật README và tài liệu kiến trúc về locale request cycle.
- [ ] Ghi cách thêm locale/translation key mới và kiểm tra key thiếu.
- [ ] Ghi quy trình `optimize:clear`, config cache và rollback không mất session/data.
- [ ] Xác nhận không cần migration hoặc seeder mới.
- [ ] Rà ảnh flag/assets nội bộ và CSP/path khi deploy dưới subfolder.

**Kết quả cần đạt:** có hướng dẫn mở rộng và vận hành song ngữ an toàn.

**Kế hoạch test:** config cache/clear, session sau deploy, asset URL dưới `/ecommerce-app`, route list và migration status.

### [ ] Task 8.8 — Quality gate đa ngôn ngữ storefront

**Dependency:** Task 8.1–8.7.

- [ ] Bao phủ middleware, endpoint, session, redirect security và CSRF.
- [ ] Bao phủ Menu, Settings, SEO và toàn bộ page chrome ở cả vi/en.
- [ ] Kiểm tra translation key thiếu, XSS, URL an toàn và accessibility dropdown.
- [ ] Kiểm tra query/cache không tăng theo mỗi partial hoặc node Menu.
- [ ] Chạy module suites, route list, migration status và full regression.
- [ ] Đồng bộ roadmap, PM board, activities, decisions, risks và Test Center.

**Kết quả cần đạt:** storefront đổi Việt–Anh ổn định, không phá admin, Menu động, Settings, Contact hoặc URL local bỏ `/public`.

**Kế hoạch test:** suite locale mới, StorefrontMenuTest, SystemSettingConsumerTest, ContactFeatureTest và `php artisan test`.

## 5. Dependency

```text
8.1 Contract + inventory
 ├─ 8.2 Locale resolver + middleware
 │   └─ 8.3 Switch endpoint + safe redirect
 │       └─ 8.4 Responsive language dropdown
 └────────── 8.5 Translation catalog + shared layout
              └─ 8.6 Page/form translations
                  └─ 8.7 Operations docs
                      └─ 8.8 Quality gate
```

## 6. File dự kiến chính

- `app/Http/Middleware/SetStorefrontLocale.php`
- `app/Support/StorefrontLocaleResolver.php`
- `app/Http/Controllers/StorefrontLocaleController.php`
- `app/Http/Requests/UpdateStorefrontLocaleRequest.php`
- `app/Http/Kernel.php`
- `routes/web.php`
- `config/app.php`
- `config/menus.php`
- `config/system_settings.php`
- `lang/vi/frontend.php`
- `lang/en/frontend.php`
- `resources/views/frontend/layout.blade.php`
- `resources/views/frontend/includes/header.blade.php`
- `resources/views/frontend/includes/footer.blade.php`
- `resources/views/frontend/includes/language-switcher.blade.php`
- `resources/views/frontend/*.blade.php`
- `public/assets/frontend/css/styles.css`
- `public/assets/frontend/js/main.js`
- `tests/Feature/StorefrontLocaleTest.php`
- `tests/Feature/StorefrontMenuTest.php`
- `tests/Feature/SystemSettingConsumerTest.php`
- `tests/Feature/ContactFeatureTest.php`

## 7. Rủi ro và giới hạn

- Session locale không tạo URL riêng cho từng ngôn ngữ, nên chưa tối ưu SEO quốc tế/hreflang.
- Dịch toàn bộ nội dung demo trong các page dài dễ bỏ sót; cần inventory và test key/route thay vì thay chuỗi rời rạc.
- Middleware chạy sai thứ tự sẽ khiến Menu/Settings dùng locale cũ trong cùng request.
- Redirect-back không kiểm tra origin có thể tạo open redirect.
- Font, chiều dài chữ và dropdown có thể vỡ layout ở mobile dù backend đúng.
- Translation file không phải nơi lưu nội dung admin-editable; nội dung động tiếp tục đi qua Settings/Menu/database.

## 8. Tiêu chí hoàn tất phase

- Người dùng đổi `vi/en` từ desktop và mobile mà không rời ngữ cảnh trang.
- Lựa chọn tồn tại trong session và áp dụng ở request tiếp theo.
- Menu, branding, SEO, shared chrome và các trang storefront chính đổi đồng bộ.
- Locale lạ và return URL ngoài bị chặn.
- Không còn route storefront trỏ tới view không tồn tại trong phạm vi đã chốt.
- Không có migration dữ liệu; cache/query contract hiện tại được giữ.
- Targeted suites và full regression đều đạt; roadmap và PM board đồng bộ.
