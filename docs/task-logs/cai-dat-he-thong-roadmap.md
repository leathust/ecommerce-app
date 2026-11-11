# Roadmap quản lý Cài đặt hệ thống

## 1. Mục tiêu

Xây dựng module **Cài đặt hệ thống** trong admin để Super Admin hoặc tài khoản có permission phù hợp có thể quản lý các thông tin vận hành, thương hiệu và mặc định nghiệp vụ mà không phải sửa source code.

Roadmap ưu tiên những cấu hình đang có nơi sử dụng rõ ràng trong website hiện tại. Cài đặt của chức năng chưa tồn tại chỉ được chuẩn bị ở mức thiết kế, không tạo field vô nghĩa. Khi phát sinh module mới, bổ sung key vào registry, giao diện và test tương ứng.

## 2. Hiện trạng đã rà soát

- Bảng `settings` đã tồn tại với `setting_key`, `setting_value`, `updated_by`, `updated_at`.
- Model `Setting` và quan hệ `updatedBy()` đã có nhưng chưa có typed accessor, cache hoặc service nghiệp vụ.
- Permission `settings.view` và `settings.update` đã được seed nhưng chưa có module quản trị tương ứng.
- Hệ thống đã có `AdminAuditService`, upload ảnh catalog, middleware trạng thái/admin/permission và layout admin có thể tái sử dụng.
- Các module liên quan trực tiếp gồm catalog, đơn hàng/in hóa đơn, contact/email reply, giao diện song ngữ và IAM.

## 3. Nguyên tắc kiến trúc

1. Bảng `settings` chỉ lưu giá trị; danh mục key hợp lệ, type, default, validation, group và mô tả nằm trong `config/system_settings.php`.
2. Không cho phép tạo key tự do từ request; controller chỉ nhận key có trong registry.
3. Không lưu password SMTP, API key, token, database credential hoặc secret thanh toán trong bảng `settings`; các giá trị này tiếp tục nằm trong `.env` hoặc secret manager.
4. Service trả về giá trị đã cast đúng type (`string`, `bool`, `int`, `decimal`, `url`, `email`, `image`) và fallback về default khi chưa có bản ghi.
5. Cập nhật theo từng nhóm trong transaction, ghi `updated_by`, audit before/after và xóa cache sau commit.
6. Cài đặt public chỉ được đưa ra frontend theo allowlist; tuyệt đối không trả toàn bộ bảng settings.
7. Ảnh thương hiệu dùng storage managed, validate MIME/kích thước và dọn file cũ sau khi transaction thành công.
8. Route admin phải qua `auth`, trạng thái active, admin middleware và `settings.view`/`settings.update`.

## 4. Danh mục cài đặt đề xuất giai đoạn đầu

### 4.1. Thông tin website và thương hiệu

| Key | Type | Giá trị mặc định | Mục đích |
|---|---|---|---|
| `site.name_vi` | string | Tên website hiện tại | Tên website tiếng Việt |
| `site.name_en` | string | Tên website hiện tại | Tên website tiếng Anh |
| `site.tagline_vi` | string | rỗng | Slogan tiếng Việt |
| `site.tagline_en` | string | rỗng | Slogan tiếng Anh |
| `site.logo` | image | rỗng | Logo header/admin/invoice khi phù hợp |
| `site.favicon` | image | rỗng | Favicon website |
| `site.default_locale` | enum | `vi` | Ngôn ngữ mặc định (`vi`, `en`) |
| `site.display_timezone` | enum | `Asia/Ho_Chi_Minh` | Múi giờ hiển thị; timezone nền Laravel vẫn do config/env quản lý |

### 4.2. Thông tin liên hệ và doanh nghiệp

| Key | Type | Giá trị mặc định | Mục đích |
|---|---|---|---|
| `contact.email` | email | rỗng | Email công khai trên website |
| `contact.phone` | string | rỗng | Hotline |
| `contact.address_vi` | textarea | rỗng | Địa chỉ tiếng Việt |
| `contact.address_en` | textarea | rỗng | Địa chỉ tiếng Anh |
| `contact.working_hours_vi` | string | rỗng | Giờ làm việc tiếng Việt |
| `contact.working_hours_en` | string | rỗng | Giờ làm việc tiếng Anh |
| `business.legal_name` | string | rỗng | Tên pháp lý trên hóa đơn |
| `business.tax_code` | string | rỗng | Mã số thuế |
| `business.invoice_footer_vi` | textarea | rỗng | Ghi chú cuối hóa đơn tiếng Việt |
| `business.invoice_footer_en` | textarea | rỗng | Ghi chú cuối hóa đơn tiếng Anh |

### 4.3. Bán hàng và đơn hàng

| Key | Type | Giá trị mặc định | Mục đích |
|---|---|---|---|
| `commerce.currency` | enum | `VND` | Đồng tiền hiển thị; giai đoạn đầu chỉ hỗ trợ VND |
| `commerce.currency_symbol` | string | `₫` | Ký hiệu tiền |
| `commerce.default_shipping_fee` | decimal | `0` | Phí giao hàng mặc định cho checkout tương lai |
| `commerce.free_shipping_threshold` | decimal | `0` | Ngưỡng miễn phí giao hàng; `0` là tắt |
| `commerce.minimum_order_amount` | decimal | `0` | Giá trị đơn tối thiểu; `0` là không giới hạn |
| `commerce.low_stock_threshold` | int | `5` | Ngưỡng cảnh báo tồn kho thấp |
| `payment.cod_enabled` | bool | `true` | Bật/tắt COD |
| `payment.bank_transfer_enabled` | bool | `true` | Bật/tắt chuyển khoản |
| `payment.card_enabled` | bool | `false` | Chỉ bật khi đã tích hợp gateway |
| `payment.e_wallet_enabled` | bool | `false` | Chỉ bật khi đã tích hợp gateway |
| `payment.bank_name` | string | rỗng | Tên ngân hàng công khai |
| `payment.bank_account_name` | string | rỗng | Tên chủ tài khoản công khai |
| `payment.bank_account_number` | string | rỗng | Số tài khoản nhận tiền; không phải credential |

Các key shipping/payment chỉ được nối vào checkout khi checkout được triển khai. Form phải ghi rõ key nào đang là cấu hình chuẩn bị, chưa tác động runtime hiện tại.

### 4.4. Email và contact

| Key | Type | Giá trị mặc định | Mục đích |
|---|---|---|---|
| `mail.from_name` | string | Tên website | Tên người gửi hiển thị |
| `mail.contact_recipient` | email | rỗng | Email nhận thông báo contact |
| `mail.contact_auto_reply_enabled` | bool | `false` | Bật thư xác nhận tự động sau khi có queue/retry |

`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, encryption và API token vẫn ở `.env`; không hiển thị hoặc lưu trong module settings.

### 4.5. SEO và mạng xã hội

| Key | Type | Giá trị mặc định | Mục đích |
|---|---|---|---|
| `seo.meta_title_vi` | string | rỗng | Meta title mặc định tiếng Việt |
| `seo.meta_title_en` | string | rỗng | Meta title mặc định tiếng Anh |
| `seo.meta_description_vi` | textarea | rỗng | Meta description tiếng Việt |
| `seo.meta_description_en` | textarea | rỗng | Meta description tiếng Anh |
| `seo.og_image` | image | rỗng | Ảnh chia sẻ mặc định |
| `seo.indexing_enabled` | bool | `false` | Chỉ bật index khi môi trường production sẵn sàng |
| `social.facebook_url` | url | rỗng | Facebook công khai |
| `social.instagram_url` | url | rỗng | Instagram công khai |
| `social.youtube_url` | url | rỗng | YouTube công khai |
| `social.tiktok_url` | url | rỗng | TikTok công khai |

## 5. Phạm vi chưa làm trong giai đoạn đầu

- Không chỉnh credential SMTP, database, session, cache, queue hoặc payment gateway từ giao diện.
- Không xây page-builder, chỉnh menu, FAQ hoặc nội dung CMS trong settings vì đã có bảng nghiệp vụ riêng.
- Không bật card/e-wallet nếu chưa có gateway và webhook xác thực.
- Không tự động áp shipping/payment vào đơn hàng cho đến khi có checkout thật.
- Không làm maintenance mode qua database ở giai đoạn đầu để tránh tự khóa toàn website khi cache/database lỗi.

## 6. Kế hoạch triển khai

### [x] Task 5.1 — Rà soát consumer và chốt registry settings

**Dependency:** IAM Task 4.3, 4.9 và schema settings hiện có.

- [x] Kiểm kê vị trí đang hard-code tên website, logo, liên hệ, invoice, currency và email hiển thị.
- [x] Chốt key, group, type, default, validation, public/private và consumer cho từng setting.
- [x] Đối chiếu song ngữ `vi/en`, timezone và định dạng tiền hiện tại.
- [x] Xác định cài đặt có hiệu lực ngay và cài đặt chỉ chuẩn bị cho checkout tương lai.
- [x] Chốt chiến lược upload logo/favicon/OG image và dọn file.
- [x] Chốt audit action: `settings.group_updated`.

**Kết quả cần đạt:** có inventory và registry contract rõ ràng, không còn key mơ hồ hoặc chưa có consumer mà bị quảng bá là đang hoạt động.

**Kế hoạch test:** rà soát tĩnh schema/routes/views; test registry không trùng key và mọi entry đủ metadata.

#### Kết quả rà soát Task 5.1

##### Inventory consumer hiện tại

| Khu vực | Hiện trạng | File/consumer chính | Hướng tích hợp |
|---|---|---|---|
| Brand storefront | `Sprylo`, chữ `S`, title và meta description đang hard-code | frontend layout/header/footer | `site.name_*`, `site.tagline_*`, `site.logo`, SEO defaults |
| Brand admin/auth | Dùng `config('app.name')` | sidebar, mobile header, login, contact reply subject | Settings fallback về `config('app.name')`; không thay đổi APP_NAME hạ tầng |
| Contact public | Email, phone, địa chỉ, giờ hỗ trợ đang là dữ liệu mẫu US | `frontend/contact.blade.php` | `contact.*` theo locale, ẩn block rỗng |
| Footer/social | Tên brand/copyright hard-code, social link là `#` | `frontend/includes/footer.blade.php` | `site.name_*`, `social.*`; chỉ render URL hợp lệ |
| Invoice | Chỉ có tên từ `config('app.name')`, chưa có thông tin người bán/tax/footer tùy chỉnh | `admin/orders/print.blade.php` | `business.*`, `contact.*`, `site.logo`, money formatter |
| Contact reply mail | Chữ ký dùng `config('mail.from.name')`; gửi đồng bộ | email view, `ContactReplyMail`, `AdminContactController` | `mail.from_name` là metadata; SMTP vẫn ở env |
| Contact submission | Chỉ lưu DB, chưa gửi thông báo nội bộ hoặc auto-reply | `ContactController` | Contact recipient/auto-reply là consumer kế tiếp, chưa quảng bá là đang chạy |
| Tiền tệ admin | `number_format(...).' ₫'` lặp lại | order index/show/print | Formatter chung đọc `commerce.currency*` |
| Tiền tệ storefront | Template mẫu còn dùng USD và giá tĩnh | home/shop/products/cart | Chỉ tích hợp khi storefront đọc Product/Cart thật |
| Locale | App và HTML là `en`, nội dung trộn Anh–Việt; route language chỉ render trang mẫu | config app, frontend layout, `HomeController::language` | Chưa có hiệu lực đầy đủ trước locale middleware/session |
| Timezone | Laravel dùng `Asia/Ho_Chi_Minh`, MySQL dùng `+07:00` | config app/database | Settings chỉ điều khiển timezone hiển thị, không đổi timezone hạ tầng giữa request |
| SEO | Title/description hard-code, chưa có canonical/OG defaults/index guard | `frontend/layout.blade.php` | SEO defaults và page-specific override |
| Checkout/payment | Checkout thật và gateway chưa tồn tại | roadmap/risk hiện tại | Shipping/payment settings là `prepared`, chưa tác động order hiện tại |

##### Contract bắt buộc cho mỗi entry registry

| Thuộc tính | Ý nghĩa |
|---|---|
| `key` | Key namespace duy nhất, tối đa 150 ký tự, khớp `settings.setting_key` |
| `group` | `site`, `contact`, `business`, `commerce`, `payment`, `mail`, `seo` hoặc `social` |
| `label` / `description` | Nhãn và giải thích tiếng Việt cho admin |
| `type` | `string`, `textarea`, `bool`, `int`, `decimal`, `enum`, `email`, `url` hoặc `image` |
| `default` | Fallback không nhạy cảm khi DB chưa có giá trị |
| `rules` | Laravel validation rules cố định từ code, không nhận từ request |
| `public` | Có được phép đưa vào public settings/view composer hay không |
| `runtime_status` | `active_consumer`, `planned_consumer` hoặc `infrastructure_only` |
| `consumer` | Danh sách class/view sử dụng cụ thể |
| `empty_behavior` | `fallback`, `hide` hoặc `disable` |

Task 5.2 phải test: key không trùng, đúng namespace/group/type, default vượt validation, public key không có tên nhạy cảm và mọi entry có consumer.

##### Phân loại hiệu lực đã chốt

- `active_consumer`: brand, logo/favicon, contact public, business/invoice, currency display, mail from name, SEO và social.
- `planned_consumer`: shipping fee/threshold/minimum order, low-stock threshold, payment/bank display, contact recipient và auto-reply.
- `infrastructure_only`: APP key/name dùng cho session/cache prefix, SMTP credential, database timezone, queue/cache/session driver; không đưa vào settings DB.
- `site.default_locale` chỉ active sau locale middleware/session; `site.display_timezone` chỉ dùng ở formatter, không gọi `date_default_timezone_set()` theo request.

##### Validation baseline

- String/textarea: trim và giới hạn độ dài; textarea không cho HTML trong giai đoạn đầu.
- Bool: normalize checkbox về boolean và lưu dạng canonical do Task 5.2 chốt.
- Decimal: không âm, tối đa `decimal(18,2)`; không dùng float khi tính tiền.
- Int: số nguyên không âm và có max riêng cho từng key.
- Enum: `Rule::in()` từ registry; currency đầu tiên chỉ `VND`, locale chỉ `vi/en`.
- Email tối đa 255; URL chỉ nhận `http/https`, từ chối `javascript:` và URL tương đối.
- Phone/bank/tax lưu string để giữ số 0 đầu, có pattern và giới hạn độ dài.

##### Public/private boundary

- Public allowlist: brand, contact công khai, business trên invoice, currency display, SEO và social; chỉ lấy qua `publicValues()`.
- Private: `mail.contact_recipient`, feature state chưa công bố và metadata vận hành.
- Thông tin ngân hàng chỉ public ở checkout/instruction khi phương thức tương ứng bật, không đưa vào global payload mặc định.
- Không tạo key cho SMTP password, API token, APP_KEY, database credential, session/cache/queue connection hoặc gateway secret.

##### Chiến lược upload đã chốt

- Disk `public`; prefix managed `system-settings/{logo|favicon|og-image}/`.
- Logo PNG/JPEG/WebP tối đa 2 MB; favicon PNG/ICO tối đa 512 KB; OG image PNG/JPEG/WebP tối đa 3 MB.
- Không nhận SVG ở giai đoạn đầu để tránh active-content/XSS và yêu cầu sanitizer riêng.
- Lưu file mới trước, cập nhật settings + audit trong transaction; transaction lỗi thì xóa file mới.
- Chỉ xóa file cũ sau commit và khi path thuộc prefix managed; không xóa URL ngoài/path module khác.
- Dùng flag `remove_*` riêng, không suy luận xóa từ upload rỗng.

##### Audit contract đã chốt

- Action `settings.group_updated`: một event cho một lần lưu group.
- Subject `Setting::class`, `subject_id = null`; Task 5.2 mở rộng `AdminAuditService` bằng API system event thay vì gắn vào một row ngẫu nhiên.
- Before/after gồm `group` và `values`, chỉ chứa key thực sự đổi và được sắp xếp ổn định.
- Image audit chỉ lưu relative path; sanitizer tiếp tục redact key nhạy cảm.
- `safeForDisplay()` bổ sung root allowlist `group`, `values` và test chống lộ secret/XSS.

##### Quyết định schema

- Bảng hiện tại đủ cho giai đoạn đầu: unique key, text value, updater và timestamp.
- Không thêm `type/group/is_public` vào DB vì đây là metadata code-managed; lưu hai nguồn gây nguy cơ lệch.
- Task 5.2 chuẩn hóa model timestamps vì bảng chỉ có `updated_at`; Task 5.7 chỉ tạo migration nếu đo đạc chứng minh cần.
- Cache toàn bộ typed map; không gọi `Setting::where()` rải rác trong Blade.

### [x] Task 5.2 — Registry, service, typed value và cache

**Dependency:** Task 5.1.

- [x] Tạo `config/system_settings.php` chứa metadata theo group.
- [x] Hoàn thiện model `Setting` với constant/cast cần thiết, không thêm business logic nặng.
- [x] Tạo `SystemSettingService` cho `get`, `getMany`, `publicValues`, `updateGroup` và fallback default.
- [x] Cast và normalize an toàn cho bool/int/decimal/string/url/email/image.
- [x] Cache theo version hoặc một cache key tập trung; invalidation sau transaction thành công.
- [x] Upsert theo allowlist, ghi `updated_by` và audit before/after.
- [x] Xử lý dữ liệu DB thiếu/không hợp lệ bằng fallback default; lỗi transaction không làm bẩn cache.

**Kết quả cần đạt:** mọi consumer đọc settings qua một API thống nhất, typed, có cache và audit.

**Kế hoạch test:** unit/feature test default, cast, unknown key, transaction rollback, cache invalidation và audit sanitize.

#### Kết quả triển khai Task 5.2

- Registry hiện có 8 group và toàn bộ key giai đoạn đầu với type/default/rules/public/runtime status/consumer/empty behavior.
- `SystemSettingService` cung cấp `definitions`, `groups`, `definitionsForGroup`, `all`, `get`, `getMany`, `publicValues`, `updateGroup` và `forgetCache`.
- Giá trị được cache thành một typed map; DB chỉ được đọc một lần cho mỗi vòng đời cache và chỉ clear sau transaction cập nhật thành công.
- Boolean lưu canonical `1/0`, integer trả về `int`, decimal chuẩn hóa thành chuỗi hai chữ số thập phân để tránh dùng float trong nghiệp vụ tiền.
- Unknown key/group, cross-group payload, invalid value và actor thiếu `settings.update` đều bị từ chối ở service.
- Cập nhật group lock row, upsert, ghi updater và một audit `settings.group_updated` trong cùng transaction; audit lỗi sẽ rollback DB và giữ cache cũ.
- `AdminAuditService` có `recordSystemEvent()` cho audit không gắn row, đồng thời allowlist hiển thị thêm `group` và `values` nhưng vẫn sanitize đệ quy.
- Test mục tiêu `SystemSettingServiceTest`: `5 passed (546 assertions)`.
- Regression audit: `AdminAuditLogTest` `7 passed (30 assertions)`.
- Toàn bộ regression suite: `172 passed (1900 assertions)`.
- Không có migration mới trong Task 5.2; schema settings hiện tại vẫn đủ dùng.

### [x] Task 5.3 — Route, request, controller và giao diện admin

**Dependency:** Task 5.2.

- [x] Tạo route danh sách với `settings.view` và route cập nhật với `settings.update`.
- [x] Tạo Form Request validate động theo registry nhưng thông báo lỗi cụ thể bằng tiếng Việt.
- [x] Tạo controller chỉ điều phối service, không tự thao tác key tùy ý.
- [x] Xây trang admin theo tab/section: Website, Liên hệ, Doanh nghiệp, Bán hàng, Thanh toán, Email, SEO và Social.
- [x] Hiển thị mô tả, giá trị mặc định, trạng thái “đang áp dụng” hoặc “chuẩn bị”.
- [x] Bổ sung menu desktop/mobile theo `settings.view` và active đúng route.
- [x] Chỉ hiện nút lưu khi actor có `settings.update`.

**Kết quả cần đạt:** giao diện rõ ràng, responsive, phân quyền đúng và không cho gửi key ngoài registry.

**Kế hoạch test:** route middleware, view/update permission, validation, tab active, forbidden update và mass-assignment payload.

#### Kết quả triển khai Task 5.3

- Thêm `GET /admin/settings` dùng `settings.view` và `PUT /admin/settings/{group}` dùng `settings.update`, bên trong group middleware auth/active/admin hiện có.
- `UpdateSystemSettingRequest` normalize checkbox thiếu thành false, validate từng field theo registry và chặn unknown/cross-group key.
- Controller chỉ chọn group, cung cấp definitions/typed values và gọi `SystemSettingService::updateGroup()`.
- Giao diện có 8 tab theo registry, render input phù hợp string/textarea/bool/int/decimal/enum/email/url, badge `Đang áp dụng`/`Chuẩn bị` và cảnh báo consumer chưa tồn tại.
- Image settings chỉ hiển thị trạng thái chờ Task 5.4; không cho nhập path thủ công để tránh vượt upload guard.
- Tài khoản chỉ có `settings.view` xem được form disabled nhưng không thấy nút lưu; tài khoản không có quyền không thấy menu và nhận 403.
- Menu desktop/mobile có mục `Cài đặt hệ thống`, active theo `admin.settings.*`.
- Audit model hiển thị label cho action/subject settings thay vì raw class/action.
- `php artisan route:list --name=admin.settings`: xác nhận 2 route đúng method/name.
- Test mục tiêu `AdminSystemSettingTest`: `6 passed (42 assertions)`.
- Toàn bộ regression suite: `178 passed (1942 assertions)`.
- Không có migration mới trong Task 5.3.

### [x] Task 5.4 — Upload tài sản thương hiệu

**Dependency:** Task 5.3.

- [x] Upload/thay/xóa logo, favicon và OG image.
- [x] Validate MIME, extension, dung lượng và kích thước ảnh theo từng loại.
- [x] Dùng disk/path riêng cho system settings.
- [x] Không xóa file cũ trước khi database update thành công.
- [x] Không xóa URL ngoài hoặc file không thuộc thư mục managed.
- [x] Hiển thị preview và trạng thái ảnh mặc định.

**Kết quả cần đạt:** quản lý tài sản thương hiệu an toàn, không tạo file rác hoặc xóa nhầm.

**Kế hoạch test:** fake storage cho upload/replace/remove, invalid file, rollback, unauthorized và external URL guard.

#### Kết quả triển khai Task 5.4

- Registry khai báo upload metadata riêng cho `site.logo`, `site.favicon`, `seo.og_image`: directory, rules và accept list.
- Logo nhận PNG/JPEG/WebP tối đa 2 MB; favicon nhận PNG/ICO tối đa 512 KB; OG image nhận PNG/JPEG/WebP tối đa 3 MB và tối thiểu 200×200.
- SVG bị từ chối; PNG favicon được kiểm tra tối đa 512×512, ICO giữ MIME/dung lượng guard vì PHP không đọc dimension ICO ổn định trên mọi môi trường.
- `SystemSettingAssetService` lưu file mới, gọi transactional settings update, xóa file mới khi DB/audit lỗi và chỉ xóa file cũ managed sau khi lưu thành công.
- Managed path guard yêu cầu đúng prefix, cấm traversal/backslash; URL ngoài và path module khác không bị xóa.
- Form settings dùng multipart, có preview, path hiện tại, input thay thế và checkbox xóa riêng; không thể upload và xóa cùng key trong một request.
- Request validate asset theo đúng group, từ chối cross-group, MIME không hợp lệ, quá dung lượng và actor thiếu permission không thể chạm storage.
- Test fixture dùng PNG nhúng sẵn thay vì GD; môi trường hiện tại không bật GD nhưng runtime validation/upload không phụ thuộc GD.
- Test mục tiêu `SystemSettingUploadTest`: `6 passed (41 assertions)`.
- Toàn bộ settings suites: `17 passed (642 assertions)`.
- Toàn bộ regression suite: `184 passed (1996 assertions)`.
- Không có migration mới trong Task 5.4; khi dùng local public disk cần bảo đảm `public/storage` link tồn tại.

### [x] Task 5.5 — Tích hợp branding, liên hệ và invoice

**Dependency:** Task 5.3 và 5.4.

- [x] Dùng tên website, logo và favicon từ settings với fallback hiện tại.
- [x] Dùng thông tin liên hệ trong các vị trí client đang hiển thị tương ứng.
- [x] Dùng business legal name, tax code, address, phone và invoice footer trên bản in hóa đơn.
- [x] Dùng currency/symbol qua formatter chung, không nối chuỗi rải rác trong Blade.
- [x] Giữ dữ liệu snapshot của đơn hàng; settings chỉ cung cấp thông tin người bán tại thời điểm render.
- [x] Kiểm tra locale `vi/en` và escape output.

**Kết quả cần đạt:** thay đổi cấu hình có tác động rõ ràng tới branding, contact và invoice mà vẫn có fallback an toàn.

**Kế hoạch test:** feature test consumer, fallback khi thiếu setting, locale, XSS escaping và query/cache behavior.

#### Kết quả triển khai Task 5.5

- `SystemSettingPresenter` tập trung dữ liệu branding/contact/invoice, hỗ trợ locale `vi/en`, fallback ngôn ngữ còn lại và fallback `config('app.name')` khi DB chưa có giá trị.
- Storefront layout/header/footer dùng tên website, ký tự đại diện, tagline, logo và favicon từ public settings; asset chỉ render khi path thuộc vùng managed.
- Trang contact chỉ hiển thị email, điện thoại, địa chỉ và giờ hỗ trợ khi có giá trị; output Blade được escape và số điện thoại chỉ được lọc cho `tel:`.
- Hóa đơn lấy legal name, mã số thuế, địa chỉ, điện thoại, email, logo và footer người bán từ settings; `locale` được truyền vào document, trong khi người nhận và item tiếp tục dùng snapshot của đơn hàng.
- `MoneyFormatter` đọc currency/symbol từ typed settings và được dùng thống nhất ở danh sách/chi tiết/in hóa đơn cùng thống kê/lịch sử mua hàng của khách hàng; không còn nối `₫` thủ công cho giá trị tiền trong admin Blade.
- View composer chia sẻ branding và formatter cho storefront; typed settings cache bảo đảm các partial lồng nhau không query lặp database.
- Test bao phủ branding/contact/assets, fallback VI/EN, seller invoice, currency symbol, order snapshot, XSS escaping, cache query và trạng thái settings rỗng.
- `SystemSettingConsumerTest`: 6 test, 47 assertions đạt.
- Toàn bộ settings suites: 23 test, 689 assertions đạt.
- Regression customer/order/consumer: 30 test, 170 assertions đạt.
- Full regression: 200 test, 2138 assertions đạt.
- Không có migration mới trong Task 5.5.

### [x] Task 5.6 — Tích hợp email, SEO và social allowlist

**Dependency:** Task 5.3; email auto-reply phụ thuộc quyết định queue/retry.

- [x] Dùng `mail.from_name` và `mail.contact_recipient` ở lớp ứng dụng phù hợp, không ghi đè SMTP credential.
- [x] Chỉ bật auto-reply khi cơ chế queue/retry được xác nhận; nếu chưa có thì giữ disabled và ghi technical debt.
- [x] Tạo SEO defaults cho layout client, cho phép trang cụ thể override.
- [x] Render canonical/meta/OG/indexing theo allowlist và môi trường.
- [x] Render social links hợp lệ, ẩn link rỗng.
- [x] Không expose private settings qua view composer/API.

**Kết quả cần đạt:** email display metadata, SEO và social dùng cấu hình tập trung mà không làm lộ secret.

**Kế hoạch test:** mail fake, meta fallback/override, robots index guard, URL validation và public allowlist.

#### Kết quả triển khai Task 5.6

- `SystemMailMetadata` đọc riêng `mail.from_name` và `mail.contact_recipient` từ private typed settings; không đưa hai key này vào storefront composer.
- Email reply dùng tên người gửi từ settings nhưng địa chỉ From, mailer, SMTP host/user/password tiếp tục lấy từ `config/mail.php`/`.env`.
- Khi khách gửi contact và recipient hợp lệ, hệ thống gửi một email thông báo nội bộ; lỗi transport được report nhưng không rollback hoặc làm mất contact đã lưu.
- `mail.contact_auto_reply_enabled` tiếp tục là planned consumer, bị khóa tắt trên UI và validation `declined`; không gửi auto-reply cho khách trước khi có queue/retry.
- Layout storefront có SEO title/description mặc định theo locale, canonical, robots, Open Graph title/description/url/image và cơ chế section override cho từng trang.
- Canonical/OG override chỉ nhận URL tuyệt đối `http/https`; output được escape. Trang Contact đã dùng title/description riêng làm ví dụ override.
- Robots luôn `noindex,nofollow` ngoài production; chỉ render `index,follow` khi đồng thời ở production và `seo.indexing_enabled = true`.
- OG image chỉ lấy từ managed asset; URL tương đối được chuyển thành URL tuyệt đối.
- Footer chỉ render Facebook/Instagram/YouTube/TikTok có URL `http/https` hợp lệ, ẩn link rỗng/sai scheme và thêm `target="_blank" rel="noopener noreferrer"`.
- `SystemSettingConsumerTest`: 7 test; `ContactFeatureTest`: 11 test.
- Settings/contact test mục tiêu: 29 test, 715 assertions đạt.
- Full regression: 202 test, 2162 assertions đạt.
- Không có migration mới trong Task 5.6.
- Technical debt: notification contact và reply email vẫn gửi đồng bộ; cần chuyển sang queue với retry/backoff trước production. Auto-reply tiếp tục bị khóa.

### [x] Task 5.7 — Seeder mặc định, migration/backfill và vận hành

**Dependency:** Task 5.2–5.6.

- [x] Quyết định có cần migration bổ sung index/metadata hay giữ schema hiện tại sau khi đo query thực tế.
- [x] Tạo seeder giá trị mặc định tối thiểu, idempotent và không ghi đè giá trị admin đã chỉnh.
- [x] Backfill từ giá trị hard-code/config hiện có mà không đọc hoặc sao chép secret `.env`.
- [x] Thêm command kiểm tra key lạ, key thiếu và file ảnh managed bị mồ côi nếu cần.
- [x] Viết hướng dẫn cache clear, storage link và rollback vận hành.
- [x] Cập nhật README/tài liệu kiến trúc settings.

**Kết quả cần đạt:** triển khai lên database hiện có an toàn, chạy lặp lại không mất tùy chỉnh.

**Kế hoạch test:** seeder idempotency, migration status, command dry-run và database hiện hữu.

#### Kết quả triển khai Task 5.7

- Giữ nguyên schema: `setting_key` đã có unique index, lookup theo key không cần index bổ sung; `updated_by` và `updated_at` đã đủ metadata vận hành.
- `SystemSettingSeeder` dùng registry allowlist và `insertOrIgnore`, chỉ chèn key thiếu, giữ nguyên mọi giá trị admin đã chỉnh và xóa typed cache sau khi chạy.
- `settings:check` mặc định read-only; phát hiện key thiếu/lạ, giá trị sai rule, ảnh tham chiếu bị mất và ảnh managed mồ côi. `--fix-missing` chỉ gọi seeder, không tự sửa/xóa dữ liệu khác.
- README và tài liệu kiến trúc đã bổ sung migrate status, seed/backfill, storage link, cache clear và rollback an toàn.
- Dry-run trên MySQL hiện hữu hoạt động, báo 37 key thiếu và không ghi dữ liệu. Toàn bộ 21 migration hiện ở trạng thái `Ran`; không tạo migration mới.
- `SystemSettingOperationsTest`: 5 passed (21 assertions).
- Toàn bộ settings suites: 29 passed (729 assertions).
- Full regression: 207 passed (2183 assertions).
- Seeder chưa được chạy trên MySQL hiện hữu; quản trị viên chủ động chạy sau khi xem kết quả dry-run.

### [x] Task 5.8 — Kiểm thử tích hợp và quality gate

**Dependency:** Task 5.1–5.7.

- [x] Bao phủ permission matrix `settings.view/update` cho Super Admin, Admin và role chỉ xem.
- [x] Kiểm tra update nhiều field nguyên tử và rollback khi audit/upload lỗi.
- [x] Kiểm tra cache invalidation và request kế tiếp nhận giá trị mới.
- [x] Kiểm tra không có secret trong response, audit, dashboard hoặc test output.
- [x] Kiểm tra không N+1 và không query settings lặp lại trên một request.
- [x] Chạy test mục tiêu, route list, migration status nếu có migration, `node --check` và full regression.
- [x] Đồng bộ roadmap, PM Board, Test Center, activity, decision và risk thực tế.

**Kết quả cần đạt:** module settings đạt quality gate và sẵn sàng mở rộng key theo registry.

**Kế hoạch test:** `AdminSystemSettingTest`, `SystemSettingServiceTest`, upload/integration test và toàn bộ `php artisan test`.

#### Kết quả triển khai Task 5.8

- Bổ sung `SystemSettingQualityGateTest` cho ma trận Super Admin/Admin/view-only/không quyền, cache qua request kế tiếp, giới hạn tối đa một query bảng settings trên trang admin và credential boundary.
- Update nhiều field tạo đúng một audit; test service/upload hiện có tiếp tục xác nhận rollback database, cache và file khi audit thất bại.
- Credential hạ tầng giả lập không xuất hiện trong registry, response admin/storefront, audit payload hoặc PM Board; test không đọc `.env`.
- Hai route `admin.settings.index/update` được xác nhận tồn tại; middleware permission tiếp tục được bao phủ bởi `AdminSystemSettingTest`.
- Quality gate mới: 4 passed (117 assertions).
- Toàn bộ settings suites: 33 passed (846 assertions).
- Full regression: 211 passed (2300 assertions).
- `node --check` đạt; 21 migration trên MySQL đều `Ran`; không có migration mới.
- Risk hiện hữu không đổi: email contact còn gửi đồng bộ và demo seeders không được chạy trên production.

## 7. Thứ tự dependency

```text
5.1 → 5.2 → 5.3 → 5.4
              ├────→ 5.5
              ├────→ 5.6
              └────→ 5.7
                       ↓
                     5.8
```

## 8. Tiêu chí bổ sung setting sau này

Mỗi setting mới phải có đầy đủ:

1. Key theo namespace, không đổi nghĩa của key cũ.
2. Group, type, default, validation và mô tả trong registry.
3. Phân loại public/private và xác định consumer cụ thể.
4. UI phù hợp nếu admin cần chỉnh.
5. Audit và cache invalidation nếu có update.
6. Test default, validation, authorization và consumer.
7. Backfill/seeder không ghi đè dữ liệu đã tùy chỉnh.
8. Không chứa secret; nếu là secret phải dùng `.env`/secret manager hoặc thiết kế encrypted vault riêng.

## 9. Tiêu chí hoàn thành roadmap

- Route settings được bảo vệ đúng authentication, trạng thái và permission.
- Không thể đọc/cập nhật key ngoài registry.
- Giá trị typed, validate đúng và có fallback.
- Update nguyên tử, có actor/audit và cache được làm mới.
- Branding/contact/invoice/SEO có consumer thực tế và không phát sinh query lặp.
- Upload asset an toàn, không mất file khi transaction lỗi.
- Không có credential hoặc secret bị chuyển từ `.env` vào database.
- Seeder/migration chạy trên dữ liệu hiện có mà không ghi đè tùy chỉnh.
- Test mục tiêu và full regression đạt; roadmap và PM Board đồng bộ.

## 10. Nhật ký cập nhật

- 11/11/2026: Hoàn tất Task 5.8 và phase System Settings; quality gate bao phủ permission, atomicity/rollback, cache, secret boundary và query count. Full regression 211 tests/2300 assertions.
- 11/11/2026: Hoàn tất Task 5.6; email metadata/private recipient, SEO/OG/robots guard và social allowlist đã tích hợp. Full regression 202 tests/2162 assertions; email đồng bộ được ghi technical debt.
- 09/11/2026: Hoàn tất Task 5.5; branding/contact/invoice/currency đã dùng settings tập trung, bổ sung locale/fallback/XSS tests. Full regression 200 tests/2138 assertions.

| Thời gian | Task | Trạng thái | Thay đổi chính | Kiểm thử |
|---|---|---|---|---|
| 05/11/2026 | Lập roadmap | Hoàn thành | Rà soát schema/settings permission và tạo kế hoạch 8 task cho module Cài đặt hệ thống | Rà soát tĩnh, chưa thay đổi code runtime |
| 05/11/2026 | 5.1 | Hoàn thành | Kiểm kê consumer/hard-code; chốt registry contract, active/planned boundary, validation, upload, audit và quyết định giữ schema hiện tại | Rà soát tĩnh bằng `rg`; kiểm tra JavaScript dashboard và task ID |
| 05/11/2026 | 5.2 | Hoàn thành | Tạo registry 8 group, typed/cached service, transactional update, system-event audit và rollback safeguards | Settings service: 5 test/546 assertions; audit: 7 test/30 assertions; full regression: 172 test/1900 assertions |
| 05/11/2026 | 5.3 | Hoàn thành | Thêm route/request/controller, giao diện 8 tab, menu desktop/mobile và authorization settings.view/update | AdminSystemSettingTest: 6 test/42 assertions; full regression: 178 test/1942 assertions |
| 05/11/2026 | 5.4 | Hoàn thành | Thêm upload/preview/replace/remove logo, favicon, OG image với managed-path và rollback guard | SystemSettingUploadTest: 6 test/41 assertions; settings suites: 17 test/642 assertions; full regression: 184 test/1996 assertions |
| 11/11/2026 | 5.8 | Hoàn thành | Bổ sung quality gate permission, multi-field/cache, credential boundary và query count; chốt phase Settings | Quality gate: 4 test/117 assertions; settings suites: 33 test/846 assertions; full regression: 211 test/2300 assertions |

