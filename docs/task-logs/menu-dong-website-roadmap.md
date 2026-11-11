# Roadmap quản lý Menu động website

## 1. Mục tiêu

- Cho phép quản trị viên quản lý menu hiển thị trên website khách hàng mà không sửa Blade.
- Hỗ trợ menu đa ngôn ngữ Việt/Anh, nhiều vị trí, cấu trúc cha–con và thứ tự hiển thị.
- Chỉ xuất bản mục đang hoạt động, có liên kết hợp lệ và nằm trong độ sâu giao diện hỗ trợ.
- Dùng chung một nguồn dữ liệu cho menu desktop và mobile để tránh lệch nội dung.
- Bảo vệ thao tác quản trị bằng RBAC, validation, transaction và audit log hiện có.
- Thiết kế đủ mở để bổ sung footer menu hoặc vị trí mới sau này mà không đổi kiến trúc lõi.

## 2. Hiện trạng đã rà soát

- Migration `2026_10_22_000110_create_menus_table.php` đã chạy trên database hiện tại.
- Bảng `menus` đã có: `parent_id`, `name_vi`, `name_en`, `url`, `location`, `sort_order`, `status`; chưa có timestamps, kiểu liên kết, route name hay hành vi mở tab.
- Model `App\Models\Menu` đã có quan hệ `parent()` và `children()`, nhưng chưa có scope cây đang xuất bản, thứ tự ổn định hoặc giới hạn độ sâu.
- Điều hướng chính và mobile drawer tại `resources/views/frontend/layout.blade.php` đang hard-code riêng biệt.
- Chưa có controller/request/service/view admin, route quản trị, permission `menus.*`, seeder hay feature test cho menu.
- Dự án đã có sẵn middleware `auth`, `active`, `admin`, `permission:*`, `AdminAuditService`, modal xác nhận dùng chung và quy ước giao diện admin.

## 3. Phạm vi nghiệp vụ giai đoạn đầu

### 3.1. Vị trí menu

- `header`: điều hướng chính trên desktop và mobile drawer.
- `footer`: chuẩn bị dữ liệu và renderer để footer có thể dùng cùng module.
- Danh sách vị trí phải được khai báo tập trung trong config, không nhập chuỗi tùy ý từ request.

### 3.2. Kiểu liên kết

- `route`: chọn route storefront từ allowlist và sinh URL bằng `route()`.
- `url`: URL tương đối nội bộ hoặc URL tuyệt đối `http/https` đã validate.
- Không cho nhập `javascript:`, `data:`, route admin hoặc scheme không an toàn.
- Cho phép chọn mở cùng tab hoặc tab mới; tab mới phải render `rel="noopener noreferrer"`.

### 3.3. Cấu trúc cây

- Một menu item có thể là mục gốc hoặc con của item cùng `location`.
- Giai đoạn đầu hỗ trợ tối đa 2 cấp vì giao diện storefront hiện tại chưa có mega-menu đa tầng.
- Không được chọn chính nó, hậu duệ của nó hoặc item khác location làm cha.
- Xóa item cha chỉ được thực hiện khi không còn item con; không tự động xóa cả nhánh.
- Thứ tự được xác định bởi `sort_order`, sau đó `id` để kết quả ổn định.

### 3.4. Xuất bản và fallback

- Chỉ item `status = true` và có liên kết hợp lệ mới xuất hiện ngoài storefront.
- Item con không xuất hiện nếu item cha bị tắt.
- Nhãn theo locale hiện tại; nếu bản dịch tương ứng rỗng thì fallback sang nhãn còn lại.
- Khi chưa có dữ liệu menu được xuất bản, tạm dùng fallback điều hướng hiện tại trong thời gian backfill; fallback được bỏ sau khi seeder/backfill được xác nhận.

## 4. Nguyên tắc kiến trúc đã đề xuất

1. `config/menus.php` giữ location, route allowlist, target và giới hạn độ sâu; database chỉ giữ dữ liệu menu.
2. `MenuService` chịu trách nhiệm dựng cây published, resolve URL và cache; Blade không tự query database.
3. CRUD, đổi trạng thái và sắp xếp chạy qua service/transaction, sau commit mới xóa cache.
4. Mọi thay đổi ghi một audit event với before/after phù hợp; không ghi dữ liệu request thừa.
5. Desktop và mobile dùng chung collection/partial; khác nhau chỉ ở markup trình bày cần thiết.
6. Không cho phép quản trị viên trỏ menu tới route admin dù họ có quyền admin.
7. Không dùng drag-and-drop làm điều kiện duy nhất: form vẫn có `parent_id` và `sort_order` để thao tác được bằng bàn phím.

## 5. Kế hoạch triển khai

### [x] Task 6.1 — Rà soát và chốt contract Menu động

**Dependency:** schema `menus`, route storefront và IAM hiện có.

- [x] Kiểm kê toàn bộ điều hướng hard-code ở header, mobile drawer và footer.
- [x] Chốt location, route allowlist, kiểu URL, target, locale fallback và giới hạn độ sâu.
- [x] Chốt quy tắc xóa, chuyển cha, đổi location, trạng thái cha–con và sắp xếp.
- [x] Chốt permission matrix `menus.view/create/update/delete` cho các role mặc định.
- [x] Chốt audit action và chiến lược cache invalidation.

**Kết quả cần đạt:** contract nghiệp vụ đủ rõ để schema, CRUD và storefront không diễn giải khác nhau.

**Kế hoạch test:** rà soát tĩnh schema/routes/views; kiểm tra task ID/dashboard và ghi lại quyết định kiến trúc.

#### Kết quả Task 6.1

##### 1. Inventory điều hướng storefront

| Khu vực | Hiện trạng | Quyết định |
|---|---|---|
| `.main-nav` trong `frontend/layout.blade.php` | 5 liên kết hard-code: Home, Shop, Products, Cart, Contact | Thay bằng cây `header` đã publish |
| `#drawer` mobile | Lặp lại 5 liên kết desktop và thêm nút View cart | Dùng cùng cây `header`; nút View cart tiếp tục là CTA riêng |
| Footer | 4 cột liên kết hard-code: Shop, Support, Popular categories, Company | Root `footer` là tiêu đề cột, con là liên kết |
| Utility bar | Track order, Help, Language | Giữ ngoài menu giai đoạn đầu vì là tiện ích hệ thống |
| Header icons | Account, Wishlist, Cart | Giữ ngoài menu vì có badge/trạng thái nghiệp vụ riêng |
| Category/CTA | All Categories và thông điệp khuyến mãi | Giữ ngoài menu; sau này thuộc catalog/settings |
| Social links | Twitter, LinkedIn, Instagram, YouTube | Không đưa vào menu; thuộc social settings Task 5.6 |

Các liên kết `*.html`, `#` demo nằm trong nội dung trang sản phẩm/home không thuộc navigation toàn cục và không nằm trong phạm vi module này.

##### 2. Contract location và độ sâu

- Location hợp lệ giai đoạn đầu: `header`, `footer`; khai báo tại `config/menus.php`.
- `header`: root là mục điều hướng cấp 1, children là dropdown cấp 2.
- `footer`: root là tiêu đề cột, children là liên kết trong cột.
- Độ sâu tối đa là `2` (root tính là cấp 1); không nhận cháu cấp 3.
- `parent_id` chỉ được trỏ tới item cùng location và chỉ tới root.
- Thứ tự ổn định: `sort_order ASC`, sau đó `id ASC`.
- `sort_order` là số nguyên không âm; service chuẩn hóa lại thành bước `10` sau reorder để dễ chèn thủ công.

##### 3. Contract kiểu liên kết

Ba `link_type` hợp lệ:

| Kiểu | Dữ liệu bắt buộc | Quy tắc |
|---|---|---|
| `route` | `route_name` | Route phải nằm trong allowlist, không có parameter và không thuộc admin |
| `url` | `url` | Chỉ nhận đường dẫn tương đối bắt đầu bằng `/` hoặc URL tuyệt đối `http/https` |
| `none` | Không có URL/route | Dùng làm container; chỉ render khi có ít nhất một child được publish |

Allowlist route giai đoạn đầu:

```text
home, shop, products, cart, contact, track-order, help, account
```

- Không nhận `language` vì đây là action chuyển ngôn ngữ, không phải trang nội dung ổn định.
- Không nhận media route, route template `pages.*`, `elements.*`, route lỗi, route auth admin hoặc route có parameter.
- Chặn scheme `javascript:`, `data:`, protocol-relative `//...`, control character và URL nội bộ bắt đầu bằng `/admin`.
- `target` chỉ nhận `_self` hoặc `_blank`; `none` luôn `_self`.
- `_blank` bắt buộc render thêm `rel="noopener noreferrer"`.
- Phase 1 không hỗ trợ route parameters/query builder; custom query string hợp lệ có thể dùng `url`.

##### 4. Contract nhãn, locale và active state

- `name_vi` và `name_en` tiếp tục bắt buộc để phù hợp schema hiện tại.
- Locale hỗ trợ: `vi`, `en`; locale ngoài danh sách fallback về `vi`.
- Nếu dữ liệu legacy thiếu nhãn hiện tại: fallback nhãn còn lại, cuối cùng không render nếu cả hai rỗng.
- Route link active khi tên route hiện tại bằng `route_name`.
- URL nội bộ active khi path đã normalize bằng request path; URL ngoài site không active.
- Container/root active nếu có một descendant active.

##### 5. Tree invariant và mutation

- Chặn chọn chính item hoặc hậu duệ làm cha; kiểm tra lại trong transaction với bản ghi được lock.
- Chuyển parent chỉ hợp lệ khi location giống nhau và không vượt depth 2.
- Đổi location chỉ cho item root không có child; item đang có child phải di chuyển/xử lý child trước.
- Xóa item có child bị từ chối; không cascade cả nhánh và không tự động đưa child lên root.
- Tắt root không sửa trạng thái child trong database nhưng toàn bộ child bị loại khỏi published tree.
- Bật child dưới parent đang tắt được lưu nhưng chưa publish; UI phải cảnh báo rõ.
- Reorder chỉ áp dụng cho tập sibling cùng `location` và cùng `parent_id`, cập nhật nguyên tử.
- Payload reorder phải chứa đúng tập ID trong scope đang sắp xếp; không được kéo item ngoài scope vào ngầm.

##### 6. Published tree contract

Một item chỉ được render khi:

1. `status = true`;
2. nằm trong location hợp lệ;
3. root hợp lệ hoặc có parent root đang active cùng location;
4. `route/url` resolve hợp lệ, hoặc `none` có ít nhất một child published;
5. không vượt quá độ sâu 2.

Storefront không tự query trong Blade. `MenuService` trả DTO/array đã resolve sẵn `label`, `href`, `target`, `rel`, `active`, `children` để desktop/mobile/footer chỉ render.

##### 7. Permission matrix

| Role mặc định | view | create | update/reorder/toggle | delete |
|---|:---:|:---:|:---:|:---:|
| `super_admin` | ✓ | ✓ | ✓ | ✓ |
| `admin` | ✓ | ✓ | ✓ | ✓ |
| `catalog_manager` | ✓ | ✓ | ✓ | ✓ |
| `order_manager` |  |  |  |  |
| `customer_support` |  |  |  |  |

- Permission catalog: `menus.view`, `menus.create`, `menus.update`, `menus.delete`.
- Reorder và toggle dùng `menus.update`; không tạo permission vụn riêng.
- Seeder chỉ sync permission khi role vừa tạo theo quy tắc hiện hữu; Task 6.3 phải bổ sung cơ chế backfill permission mới mà không ghi đè ma trận role đã tùy chỉnh.

##### 8. Audit contract

Các action:

```text
menu.created
menu.updated
menu.deleted
menu.status_updated
menu.reordered
```

- Create/update/delete/status ghi subject là `Menu` và snapshot allowlist: id, parent_id, location, label, link_type, route_name/url, target, sort_order, status.
- Reorder ghi một system event gồm location, parent_id, danh sách `{id, before, after}`; không tạo một audit row cho mỗi item.
- Audit nằm trong cùng transaction với mutation; audit thất bại phải rollback mutation.
- Task 6.3 bổ sung root key `menu`, `items`, `location`, `parent_id` vào display allowlist và alias `menu` cho bộ lọc audit.

##### 9. Cache contract

- Cache key: `menus.published.v1.{location}.{locale}`.
- Cache dùng `rememberForever` giống settings vì mutation tập trung qua service.
- Sau transaction thành công mới `forget` toàn bộ tổ hợp location `header/footer` và locale `vi/en`.
- Không xóa cache nếu transaction/audit rollback.
- Admin preview/form không đọc published cache; dùng query quản trị để thấy cả item inactive.
- Khi cache trống hoặc database chưa có published menu, renderer dùng fallback hard-code trong giai đoạn chuyển đổi; Task 6.7 loại fallback sau khi seed/backfill được xác nhận.

##### 10. Quyết định schema chuyển sang Task 6.2

Giữ bảng hiện tại và tạo migration additive:

- `link_type varchar(20)` mặc định `url` để tương thích cột `url` legacy;
- `route_name varchar(100) nullable`;
- `target varchar(20)` mặc định `_self`;
- `created_at`, `updated_at` nullable để backfill không phá dữ liệu cũ;
- composite index phục vụ published tree theo `location`, `parent_id`, `status`, `sort_order`.

Không dùng database enum để có thể bổ sung kiểu liên kết mà không phải sửa enum MySQL. Invariant chéo cột và cây được kiểm soát bằng Form Request + `MenuService`, đồng thời có test hồi quy.

### [x] Task 6.2 — Bổ sung schema, model và cấu hình Menu

**Dependency:** Task 6.1.

- [x] Tạo migration additive cho `link_type`, `route_name`, `target`, timestamps và index cần thiết.
- [x] Backfill bản ghi `url` cũ an toàn, không xóa hay ghi đè dữ liệu hiện hữu.
- [x] Bổ sung casts, scopes, ordered children và helper nhãn/URL trong model hoặc service phù hợp.
- [x] Tạo `config/menus.php` chứa location, route allowlist và giới hạn độ sâu.
- [x] Rà soát foreign key/index và khả năng rollback migration.

**Kết quả cần đạt:** schema biểu diễn được menu an toàn, có thể truy vấn cây ổn định và tương thích dữ liệu cũ.

**Kế hoạch test:** migration status, schema test, model relation/scope test, backfill và rollback trên database test cô lập; không dùng `migrate:fresh` trên database hiện tại.

#### Kết quả Task 6.2

- Tạo migration `2026_11_09_000010_extend_menus_table_for_dynamic_navigation.php` theo hướng additive; không sửa migration gốc đã chạy.
- Bổ sung `link_type`, `route_name`, `target`, timestamps nullable và composite index `menus_published_tree_index`.
- Backfill item legacy không có URL thành container `none`; item có URL giữ nguyên URL và dùng kiểu `url` mặc định.
- `down()` chỉ gỡ index/cột mới, giữ nguyên bảng, foreign key tự tham chiếu và dữ liệu cột cũ.
- Tạo `config/menus.php` làm nguồn duy nhất cho location, link type, target, 8 route allowlist, locale, depth, sort step, URL guard và cache prefix.
- Model `Menu` đã bật timestamps, bổ sung fillable/default/casts, scope `forLocation/roots/active/ordered`, children có thứ tự ổn định, helper `localizedName()` và `isContainer()`.
- `MenuSchemaTest`: 3 test, 39 assertions đạt; bao phủ config/schema/index, model/scopes/relations/locale và migration `down → up` giữ/backfill dữ liệu.
- Schema regression: 4 test, 62 assertions đạt.
- Full regression: 191 test, 2074 assertions đạt.
- MySQL hiện tại: migration gốc `menus` và migration mở rộng `2026_11_09_000010` đều đã `Ran` (migration mở rộng ở batch 5).

### [x] Task 6.3 — Permission, service cây và audit

**Dependency:** Task 6.2 và IAM/Audit hiện có.

- [x] Thêm permission `menus.view`, `menus.create`, `menus.update`, `menus.delete` idempotent.
- [x] Gán quyền mặc định cho `super_admin`, `admin` và `catalog_manager` theo ma trận đã chốt.
- [x] Xây dựng `MenuService` cho create/update/delete/toggle/reorder và published tree.
- [x] Chặn cycle, sai location, vượt depth, route ngoài allowlist và URL nguy hiểm.
- [x] Ghi audit trong transaction và invalid cache sau commit.

**Kết quả cần đạt:** toàn bộ quy tắc cây và bảo mật tập trung, controller không chứa nghiệp vụ lặp lại.

**Kế hoạch test:** unit/feature test cycle, depth, parent/location, URL/route, transaction rollback, audit và cache invalidation.

#### Kết quả Task 6.3

- Permission catalog có thêm `menus.view/create/update/delete` và nhóm “Menu website” trong ma trận IAM.
- `super_admin`, `admin`, `catalog_manager` nhận permission menu theo baseline; seeder chỉ backfill role legacy chưa tùy chỉnh, không ghi đè ma trận system role đã chỉnh tay và không detach permission ngoài baseline.
- Tạo `MenuService` với API create/update/status/delete/reorder/published và cache invalidation tập trung.
- Mutation kiểm tra permission ở service, khóa bản ghi/sibling cần thiết, ghi audit trong cùng transaction và chỉ xóa cache sau commit thành công.
- Chặn route ngoài allowlist/route có parameter, scheme nguy hiểm, protocol-relative URL, credential URL, `/admin` kể cả dạng encode/dot-segment và URL nội bộ không an toàn.
- Chặn parent khác location, self-parent, cấp ba, đổi location của root đang có child, đưa root có child xuống cấp hai, xóa item có child và reorder sai sibling scope.
- Published tree chỉ lấy root/child active, giới hạn hai cấp, resolve locale/URL/target/rel, tính active state sau lớp cache để không cache trạng thái theo request.
- Audit có action/label/subject/filter/safe display cho Menu; reorder ghi một system event thay vì một dòng cho mỗi item.
- `MenuServiceTest`: 7 test, 48 assertions đạt.
- Regression liên quan Menu/IAM/Audit: 30 test, 212 assertions đạt.
- Full regression: 198 test, 2122 assertions đạt.

### [x] Task 6.4 — Danh sách và CRUD Menu trong admin

**Dependency:** Task 6.3.

- [x] Thêm route admin với `auth`, `active`, `admin` và permission riêng từng thao tác.
- [x] Tạo Form Request cho index/store/update, normalize checkbox và payload.
- [x] Tạo danh sách dạng cây có filter location, trạng thái, tìm kiếm và phân trang phù hợp.
- [x] Tạo form thêm/sửa với preview liên kết, chọn cha hợp lệ, location, target và trạng thái.
- [x] Thêm menu điều hướng admin theo `menus.view` và modal Bootstrap xác nhận xóa.
- [x] Hiển thị lỗi nghiệp vụ rõ ràng khi item có con hoặc thao tác tạo cycle.

**Kết quả cần đạt:** quản trị viên thao tác đầy đủ bằng giao diện responsive, đúng permission và không mass-assign trường ngoài phạm vi.

**Kế hoạch test:** route middleware, CRUD happy path, validation, 403, 404, parent options, delete guard, filter và pagination query preservation.

#### Kết quả Task 6.4

- Thêm 6 route CRUD dưới nhóm admin hiện hữu; mỗi action dùng permission `menus.view/create/update/delete` tương ứng, ngoài hàng rào `auth`, `active`, `admin` của group cha.
- Tạo request index/store/update với allowlist field, normalize checkbox/null và kiểm tra parent cùng location, chỉ là root, không self-parent.
- Danh sách phân trang theo root để không tách cha–con, eager load child và hỗ trợ tìm tên/route/URL, lọc location/trạng thái, giữ query khi phân trang.
- Form responsive hỗ trợ nhãn VI/EN, location, root parent hợp lệ, link type route/url/none, target, trạng thái và preview liên kết; menu có con bị khóa thao tác đổi location/cấp.
- Navigation desktop/mobile chỉ hiện theo `menus.view`; xóa dùng modal Bootstrap chung và service trả lỗi rõ ràng nếu còn child.
- `AdminMenuTest`: 7 passed (68 assertions).
- Regression liên quan Menu: 18 passed (160 assertions).
- Full regression: 218 passed (2368 assertions); `node --check` và PHP lint đạt; route list xác nhận đủ 6 route.
- Không có migration mới trong Task 6.4; migration Menu hiện tại tiếp tục đủ cho CRUD.

### [x] Task 6.5 — Sắp xếp và đổi trạng thái menu

**Dependency:** Task 6.4.

- [x] Cung cấp thao tác sắp xếp trong từng parent/location; ưu tiên AJAX nhưng vẫn có fallback hợp lệ.
- [x] Validate toàn bộ payload ID, quyền sở hữu location và thứ tự không trùng/không thiếu item ngoài phạm vi.
- [x] Khóa các hàng liên quan khi reorder để hạn chế race condition.
- [x] Cho phép bật/tắt nhanh và phản ánh quy tắc cha–con trên giao diện.
- [x] Cập nhật DOM, thông báo lỗi và CSRF handling nhất quán với admin hiện tại.

**Kết quả cần đạt:** thứ tự và trạng thái cập nhật nhanh, nguyên tử, không làm hỏng cấu trúc cây.

**Kế hoạch test:** reorder hợp lệ/sai phạm vi, concurrent-safe transaction, toggle, unauthorized AJAX, cache và audit.

#### Kết quả Task 6.5

- Thêm endpoint PATCH cho trạng thái và reorder, cùng Form Request riêng kiểm tra `menus.update`, location, parent và danh sách ID không trùng.
- Giao diện có nút Ẩn/Hiện và Lên/Xuống; JavaScript gửi AJAX với CSRF, cập nhật badge/thứ tự/DOM, trạng thái nút và thông báo trực tiếp.
- Mỗi thao tác vẫn là form PATCH đầy đủ để fallback bằng redirect + flash message khi JavaScript không hoạt động.
- Reorder luôn gửi toàn bộ sibling scope kể cả khi danh sách root đang phân trang; backend khóa scope, từ chối ID thiếu/thừa/sai parent/location và chuẩn hóa `sort_order` theo bước cấu hình.
- Khi root bị ẩn, child hiển thị cảnh báo “Cha đang ẩn”; published cache được xóa sau status/reorder thành công.
- `AdminMenuTest`: 9 passed (95 assertions).
- Regression liên quan Menu: 20 passed (187 assertions).
- Full regression: 220 passed (2395 assertions); 8 route admin Menu, PHP lint và JavaScript `node --check` đều đạt.
- Không có migration mới trong Task 6.5.

### [x] Task 6.6 — Tích hợp Menu động vào storefront

**Dependency:** Task 6.3; giao diện quản trị có thể triển khai song song sau contract.

- [x] Chia sẻ cây published qua view composer hoặc provider phù hợp, không query lặp ở partial.
- [x] Thay menu hard-code desktop và mobile bằng cùng nguồn dữ liệu.
- [x] Render active state cho route/URL hiện tại và dropdown có accessibility cơ bản.
- [x] Hỗ trợ locale fallback, target an toàn và item con.
- [x] Tích hợp vị trí footer nếu template đã có vùng phù hợp; không phá vỡ nội dung footer hiện tại.
- [x] Cache cây theo location/locale và xóa cache khi admin thay đổi.

**Kết quả cần đạt:** thay đổi menu từ admin phản ánh nhất quán lên desktop/mobile mà không sửa Blade thủ công.

**Kế hoạch test:** storefront feature test cho location, locale, active state, parent disabled, target security, query count/cache và responsive markup.

#### Kết quả Task 6.6

- `AppServiceProvider` resolve `header` và `footer` đúng một lần tại layout; desktop/mobile dùng chung cây header, footer dùng location riêng.
- Ba partial storefront render cây hai cấp, locale fallback, active state, `aria-current`, dropdown bàn phím/touch cơ bản và mobile `<details>`.
- Link `_blank` luôn kèm `noopener noreferrer`; item legacy có URL không an toàn bị loại khỏi published tree.
- Giữ fallback chuyển tiếp từ config khi database chưa có menu để storefront không mất điều hướng trước Task 6.7; footer branding/social hiện tại được giữ nguyên.
- Cache tiếp tục phân tách theo location/locale và active state được tính sau cache theo request; test xác nhận request kế tiếp không query lại bảng `menus`.
- `StorefrontMenuTest`: 5 passed (33 assertions).
- Regression liên quan Menu: 25 passed (220 assertions).
- Full regression: 225 passed (2428 assertions); PHP lint, frontend JavaScript và dashboard `node --check` đều đạt.
- Không có migration hoặc route mới trong Task 6.6.

### [x] Task 6.7 — Seeder, backfill và hướng dẫn vận hành

**Dependency:** Task 6.2–6.6.

- [x] Tạo seeder idempotent từ menu hard-code hiện tại, không ghi đè menu đã tùy chỉnh.
- [x] Xác định khóa tự nhiên an toàn cho dữ liệu mặc định và tránh tạo bản ghi trùng khi chạy lại.
- [x] Đăng ký seeder theo cách không tự động đẩy dữ liệu demo lên production ngoài ý muốn.
- [x] Viết hướng dẫn migrate, seed, cache clear và khôi phục fallback.
- [x] Cập nhật README/tài liệu kiến trúc nếu luồng runtime thay đổi.

**Kết quả cần đạt:** database hiện có nâng cấp an toàn và đội vận hành biết cách khởi tạo menu mặc định.

**Kế hoạch test:** seeder chạy hai lần không trùng/không ghi đè, backfill database hiện hữu và kiểm tra route đích tồn tại.

#### Kết quả Task 6.7

- `MenuSeeder` dùng defaults tập trung trong `config/menus.php`, chạy transaction và chỉ backfill location hoàn toàn trống; location đã có bất kỳ item nào được giữ nguyên toàn bộ.
- Khóa tự nhiên gồm location, parent, link type, route/URL và cặp nhãn mặc định; các nhãn khác nhau có thể cùng trỏ một route mà không bị gộp.
- Seeder được đăng ký trong `DatabaseSeeder` như dữ liệu điều hướng bắt buộc; tài liệu yêu cầu production/database hiện hữu chạy riêng `--class=MenuSeeder` để không kéo theo customer/product/order demo.
- Fallback khẩn cấp dùng cùng defaults, mặc định tắt qua `MENU_FALLBACK_ENABLED=false`; tài liệu có quy trình bật tạm, clear config và tắt lại sau khôi phục.
- README và tài liệu kiến trúc đã bổ sung migrate, seed, cache clear, rollback/fallback và nguyên tắc không dùng `migrate:fresh`.
- `MenuSeederTest`: 4 passed (39 assertions); bao phủ chạy hai lần, không ghi đè location tùy chỉnh, route allowlist và storefront khi fallback tắt.
- Regression liên quan Menu: 29 passed (259 assertions).
- Full regression: 229 passed (2467 assertions); PHP lint và dashboard JavaScript đều đạt.
- MySQL hiện tại có đủ 21 migration `Ran`; chạy `MenuSeeder` hai lần đều giữ nguyên `header=5`, `footer=16`.
- Không có migration mới trong Task 6.7.

### [x] Task 6.8 — Kiểm thử tích hợp và quality gate Menu

**Dependency:** Task 6.1–6.7.

- [x] Bao phủ permission matrix và việc thu hồi quyền có hiệu lực ngay.
- [x] Bao phủ CRUD, tree invariant, reorder, audit, cache và storefront renderer.
- [x] Kiểm tra XSS/URL scheme, route admin leakage, target security và mass assignment.
- [x] Kiểm tra giao diện desktop/mobile, bàn phím và empty/fallback state.
- [x] Chạy route list, migration status, test module và full regression.
- [x] Đồng bộ roadmap, PM board, activity, test center và migration dashboard theo kết quả thực tế.

**Kết quả cần đạt:** module Menu động sẵn sàng dùng trên database hiện hữu, không phá giao diện hoặc quyền admin.

**Kế hoạch test:** `AdminMenuTest`, `MenuServiceTest`, `StorefrontMenuTest`, security cases và toàn bộ `php artisan test`.

#### Kết quả Task 6.8

- Thêm `MenuIntegrationQualityGateTest` bao phủ ma trận role mặc định và thu hồi `menus.view` có hiệu lực ngay trên cùng session, cả middleware lẫn sidebar.
- Xác nhận create/update/toggle từ admin invalid cache và phản ánh ngay ra storefront, đồng thời tạo đúng chuỗi audit event.
- Xác nhận fillable không nhận `id`, timestamps hay field lạ; route ngoài allowlist, URL `/admin/*` và `javascript:` đều bị từ chối.
- Xác nhận empty state khi fallback tắt, desktop/mobile/footer marker, dropdown button `aria-expanded`, mobile `<details>/<summary>`, active state, `_blank` security, Escape handler và CSS `:focus-within`.
- Sáu suite Menu chính xác: 32 passed (303 assertions), gồm service, schema, admin CRUD/reorder, seeder, storefront và integration quality gate.
- `route:list -v`: 8 route admin Menu; tất cả đi qua `auth`, `active`, `admin` và permission cụ thể `menus.view/create/update/delete`.
- `migrate:status`: 21 migration đều `Ran`; migration Menu mở rộng ở batch 5. Không có migration mới trong Task 6.8.
- Full regression: 233 passed (2516 assertions).
- PHP lint, frontend JavaScript, dashboard JavaScript, task-ID, HTML-ID và `git diff --check` đều đạt.
- Phase Dynamic Storefront Menus hoàn tất 8/8 task; giới hạn đã biết vẫn là cây tối đa hai cấp, chưa phải mega-menu.

## 6. Thứ tự dependency

```text
6.1 Contract
  └─ 6.2 Schema + model + config
       └─ 6.3 Permission + service + audit
            ├─ 6.4 Admin CRUD
            │    └─ 6.5 Reorder + toggle
            └─ 6.6 Storefront integration
                 └─ 6.7 Seeder + operations
                      └─ 6.8 Integration quality gate
```

## 7. File dự kiến chính

- `config/menus.php`
- `database/migrations/*_extend_menus_table.php`
- `database/seeders/MenuSeeder.php`
- `database/seeders/RolePermissionSeeder.php`
- `app/Models/Menu.php`
- `app/Services/MenuService.php`
- `app/Http/Controllers/AdminMenuController.php`
- `app/Http/Requests/*MenuRequest.php`
- `app/Providers/AppServiceProvider.php` hoặc view composer chuyên biệt
- `resources/views/admin/menus/*`
- `resources/views/frontend/includes/*menu*.blade.php`
- `resources/views/frontend/layout.blade.php`
- `resources/views/admin/includes/sidebar.blade.php`
- `routes/web.php`
- `tests/Feature/AdminMenuTest.php`
- `tests/Unit/MenuServiceTest.php`
- `tests/Feature/StorefrontMenuTest.php`

## 8. Rủi ro và giới hạn

- Template storefront chưa có dropdown đa cấp hoàn chỉnh; giai đoạn đầu giới hạn 2 cấp.
- Route allowlist phải được cập nhật khi website có trang khách hàng mới, nếu không route đó sẽ không xuất hiện trong form.
- Dữ liệu menu hiện hữu nếu có URL tùy ý cần được audit trước khi bật renderer động.
- Kéo–thả có thể khó dùng trên mobile/accessibility; phải giữ input thứ tự/cha làm phương án thay thế.
- Cần tránh query menu cho từng partial và tránh cache vĩnh viễn khiến cập nhật admin không phản ánh.

## 9. Tiêu chí hoàn thành roadmap

- Tất cả Task 6.1–6.8 và checklist bắt buộc được đánh dấu hoàn thành sau khi test đạt.
- Menu desktop và mobile dùng cùng dữ liệu; không còn danh sách điều hướng chính hard-code.
- Không tạo được cycle, vượt depth, liên kết nguy hiểm hoặc liên kết tới admin.
- Mọi mutation được phân quyền, audit, transaction và invalid cache đúng.
- Seeder/backfill chạy lặp lại an toàn trên database hiện có.
- Roadmap, `pm-board.js`, Test Center và migration status phản ánh đúng kết quả thực tế.

## 10. Nhật ký cập nhật

- 11/11/2026: Hoàn tất Task 6.5; thêm AJAX/fallback cho status và reorder, DOM feedback, full-scope validation, audit/cache tests. Full regression 220 tests/2395 assertions.
- 11/11/2026: Hoàn tất Task 6.4; thêm CRUD admin, tree list/root pagination, form preview, navigation, modal delete guard và 7 feature tests. Full regression 218 tests/2368 assertions.
- 09/11/2026: Hoàn tất Task 6.3; thêm permission, MenuService, tree/link guards, transaction/audit/cache và 7 service tests. Full regression 198 tests/2122 assertions; xác nhận migration menu batch 5 đã Ran.
- 09/11/2026: Hoàn tất Task 6.2; thêm config, migration additive, model contract và 3 schema tests. Full regression 191 tests/2074 assertions; migration MySQL mới còn Pending.
- 09/11/2026: Hoàn tất Task 6.1; chốt inventory, route allowlist, tree/link contract, RBAC, audit, cache và schema direction. Chưa thay đổi runtime.
- 09/11/2026: Rà soát hiện trạng, tạo roadmap 8 task và đồng bộ PM board; chưa bắt đầu code runtime.
