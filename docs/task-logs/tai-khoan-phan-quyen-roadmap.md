# Roadmap quản lý tài khoản và phân quyền người dùng

> Ngày lập kế hoạch: 29/10/2026  
> Trạng thái tổng thể: Hoàn thành toàn bộ roadmap IAM và enhancement Task 4.12  
> Quy ước: `[ ]` Chưa làm · `[-]` Đang làm · `[x]` Hoàn thành · `[!]` Bị chặn

## 1. Mục tiêu

Xây dựng module Identity & Access Management (IAM) cho khu vực admin, bao gồm:

1. Quản lý tài khoản quản trị/nhân viên một cách an toàn.
2. Quản lý role và ma trận permission trên database hiện có.
3. Gán nhiều role cho tài khoản và tính quyền hợp nhất từ các role.
4. Bảo vệ super admin, role hệ thống và người đang thao tác.
5. Ghi lại lịch sử thay đổi tài khoản, role và permission.
6. Có kiểm thử phân quyền, bảo mật và hồi quy đầy đủ.

Module này không thay thế module **Khách hàng**. Tài khoản khách tiếp tục được quản lý tại `/admin/customers`; module IAM tập trung vào tài khoản có quyền truy cập khu vực quản trị.

## 2. Hiện trạng đã rà soát

### Dữ liệu và model hiện có

- Bảng `users` có `username`, `email`, tên, điện thoại, password và trạng thái `active/inactive/blocked`.
- Bảng `roles`, `permissions`, `user_roles`, `role_permissions` đã tồn tại.
- `User`, `Role`, `Permission` đã có quan hệ many-to-many và helper kiểm tra role/permission.
- `RolePermissionSeeder` tạo 5 role hệ thống và 30 permission, bao gồm `audit.view`.
- `AdminUserSeeder` tạo super admin từ cấu hình `.env`.
- Super admin đang được `Gate::before` cho phép vượt qua permission thông thường.
- Middleware `active`, `admin` và `permission:*` đã bảo vệ route admin.

### Permission đã có thể tái sử dụng

- `users.view`, `users.create`, `users.update`, `users.delete`.
- `roles.view`, `roles.manage`.
- `permissions.manage`.

### Khoảng trống cần xử lý

- Chưa có route, controller và giao diện quản lý tài khoản admin.
- Chưa có CRUD role hoặc giao diện ma trận permission.
- Chưa có audit log cho thay đổi phân quyền.
- `Role::ADMIN_ROLES` đang là danh sách tĩnh; role mới tạo từ UI sẽ không tự được nhận diện là role quản trị.
- Chưa có bảo vệ “super admin cuối cùng”.
- Chưa có quy tắc chống tự khóa, tự xóa hoặc tự gỡ quyền quản trị.
- Role/permission hiện không có timestamp hoặc cờ phân biệt bản ghi hệ thống.

## 3. Quyết định nghiệp vụ và bảo mật

### Phạm vi tài khoản

- Danh sách IAM mặc định chỉ hiển thị user có ít nhất một role quản trị.
- Tài khoản không có role quản trị tiếp tục thuộc module Khách hàng.
- Tạo tài khoản trong IAM đồng nghĩa tạo tài khoản nhân viên/admin và bắt buộc gán ít nhất một role quản trị.
- Một user có thể có nhiều role; quyền hiệu lực là hợp của permission từ tất cả role.

### Bảo vệ super admin

- Chỉ super admin được gán hoặc gỡ role `super_admin`.
- Không được khóa, xóa hoặc gỡ role của super admin cuối cùng.
- Người đang đăng nhập không được tự khóa, tự xóa hoặc tự gỡ toàn bộ quyền quản trị.
- Không hiển thị hoặc ghi log password dạng rõ.

### Role và permission

- Bổ sung `roles.is_admin` để thay thế phụ thuộc vào danh sách role tĩnh.
- Bổ sung `roles.is_system` để bảo vệ các role seed mặc định khỏi xóa/đổi tên ngoài ý muốn.
- Permission là danh mục do code/seeder quản lý; giai đoạn này không cho tạo/xóa permission tùy ý từ UI.
- UI permission chỉ cho gán hoặc gỡ permission khỏi role được phép chỉnh sửa.
- Role hệ thống có thể giới hạn trường được sửa; `super_admin` luôn có toàn quyền bằng cơ chế bypass.
- Mọi thao tác sync role/permission phải chạy trong transaction.

### Password và session

- Password tạo/cập nhật phải đi qua Laravel hashing và validation xác nhận.
- Form sửa tài khoản để trống password nghĩa là giữ nguyên password hiện tại.
- Tùy chọn đặt password tạm thời phải đánh dấu `must_change_password`; không gửi hoặc log password rõ.
- Tài khoản bị khóa sẽ bị middleware `active` đăng xuất ở request kế tiếp.
- Thu hồi toàn bộ session ngay lập tức chỉ triển khai nếu session driver hỗ trợ truy vấn theo user; nếu chưa, phải ghi thành giới hạn kỹ thuật.

### Audit

- Ghi người thực hiện, hành động, đối tượng, dữ liệu trước/sau, IP, user agent và thời gian.
- Không lưu password, remember token hoặc secret trong audit payload.
- Audit log là append-only trong ứng dụng; không cung cấp nút sửa/xóa.

## 4. Thiết kế route dự kiến

| Method | URL | Route name | Permission | Mục đích |
|---|---|---|---|---|
| GET | `/admin/users` | `admin.users.index` | `users.view` | Danh sách tài khoản quản trị |
| GET | `/admin/users/create` | `admin.users.create` | `users.create` | Form tạo tài khoản |
| POST | `/admin/users` | `admin.users.store` | `users.create` | Lưu tài khoản và role |
| GET | `/admin/users/{user}` | `admin.users.show` | `users.view` | Hồ sơ tài khoản và quyền hiệu lực |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | `users.update` | Form cập nhật |
| PUT | `/admin/users/{user}` | `admin.users.update` | `users.update` | Cập nhật hồ sơ, trạng thái, role |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | `users.delete` | Xóa tài khoản hợp lệ |
| GET | `/admin/roles` | `admin.roles.index` | `roles.view` | Danh sách role |
| GET/POST | `/admin/roles/create`, `/admin/roles` | `admin.roles.create/store` | `roles.manage` | Tạo role |
| GET/PUT | `/admin/roles/{role}/edit`, `/admin/roles/{role}` | `admin.roles.edit/update` | `roles.manage` | Sửa role và ma trận quyền |
| DELETE | `/admin/roles/{role}` | `admin.roles.destroy` | `roles.manage` | Xóa role không hệ thống/không được dùng |
| GET | `/admin/access-logs` | `admin.access-logs.index` | `audit.view` | Xem audit phân quyền |

Tất cả route tiếp tục nằm trong nhóm `auth`, `active`, `admin`; backend phải kiểm tra lại target và quy tắc super admin, không chỉ ẩn nút trên giao diện.

## 5. Kế hoạch triển khai theo task

## Giai đoạn A — Nền tảng IAM

### [x] Task 4.1 — Rà soát và chốt ma trận quản trị

- [x] Kiểm kê role, permission, user quản trị và route đang sử dụng.
- [x] Chốt phạm vi giữa module IAM và module Khách hàng.
- [x] Chốt quyền của `super_admin`, `admin` và các manager.
- [x] Chốt quy tắc tự thao tác và super admin cuối cùng.
- [x] Chốt quy tắc role hệ thống và role tùy chỉnh.
- [x] Lập bảng permission theo module/action để đối chiếu route.

**Kết quả cần đạt:** tài liệu nghiệp vụ và security matrix không còn điểm mơ hồ trước khi thay schema.

#### Kết quả rà soát hiện trạng

- Hệ thống có 5 role seed: `super_admin`, `admin`, `catalog_manager`, `order_manager`, `customer_support`.
- Seeder hiện có 29 permission. Trong đó 20 permission đã bảo vệ route nghiệp vụ; 9 permission `users.*`, `roles.*`, `permissions.manage`, `settings.*` đã có dữ liệu nhưng chưa có route module tương ứng.
- Có 47 route dưới prefix `/admin`; toàn bộ route nghiệp vụ đang nằm sau `auth`, `active`, `admin` và permission theo module.
- `EnsureUserHasPermission` dùng chiến lược **OR** khi truyền nhiều permission. Route cập nhật trạng thái đơn dùng `orders.update|orders.cancel`, controller tiếp tục kiểm tra permission theo transition cụ thể.
- `Gate::before` gọi `User::hasPermission()`; `super_admin` bypass cả permission chưa tồn tại trong database.
- `User::isAdmin()` hiện dựa trên `Role::ADMIN_ROLES`, vì vậy role quản trị tùy chỉnh chưa thể vượt qua middleware `admin` nếu không sửa code.
- Tại thời điểm Task 4.1, `admin` được seed toàn bộ 29 permission, bao gồm `roles.manage` và `permissions.manage`; Task 4.2 đã thu hẹp theo security matrix bên dưới.
- Chưa có route/menu IAM, chưa có audit log và chưa có guard bảo vệ super admin cuối cùng.

#### Phạm vi tài khoản đã chốt

- Module IAM chỉ liệt kê user có ít nhất một role với `roles.is_admin = true`.
- Module Khách hàng tiếp tục liệt kê user không có role quản trị; hai module không được hiển thị trùng tài khoản.
- Form IAM chỉ tạo tài khoản quản trị/nhân viên và bắt buộc gán ít nhất một role quản trị.
- Role không quản trị như loyalty/member vẫn có thể tồn tại, nhưng không cấp quyền vào `/admin`.
- Nếu user có cả role thường và role quản trị, user thuộc module IAM.

#### Ma trận quyền quản trị đã chốt

| Nhóm permission | `super_admin` | `admin` | `catalog_manager` | `order_manager` | `customer_support` |
|---|---|---|---|---|---|
| `dashboard.view` | Bypass | Có | Có | Có | Có |
| `products.*`, `categories.*`, `brands.*` | Bypass | Đầy đủ | Đầy đủ | Không | Không |
| `orders.view/update/cancel` | Bypass | Đầy đủ | Không | Đầy đủ | Chỉ `view` |
| `customers.view/update` | Bypass | Đầy đủ | Không | Chỉ `view` | Chỉ `view` |
| `contacts.view/update` | Bypass | Đầy đủ | Không | Không | Đầy đủ |
| `users.view/create/update/delete` | Bypass | Có, trừ target super admin | Không | Không | Không |
| `roles.view` | Bypass | Có | Không | Không | Không |
| `roles.manage` | Bypass | Không | Không | Không | Không |
| `permissions.manage` | Bypass | Không | Không | Không | Không |
| `audit.view` | Bypass | Có | Không | Không | Không |
| `settings.view/update` | Bypass | Đầy đủ | Không | Không | Không |

- Task 4.2 đã thêm permission mới `audit.view`.
- `admin` được quản lý tài khoản không phải super admin nhưng không được thay đổi cấu trúc role hoặc ma trận permission.
- Chỉ `super_admin` được tạo/sửa role tùy chỉnh và sync permission.
- Permission hiệu lực của user là hợp permission từ tất cả role; không có permission phủ định ở giai đoạn này.

#### Ma trận actor–target đã chốt

| Actor | Target | Xem | Sửa hồ sơ | Đổi trạng thái | Gán role | Xóa |
|---|---|---:|---:|---:|---:|---:|
| `super_admin` | Chính mình | Có | Có | Không tự khóa | Không tự gỡ `super_admin` | Không |
| `super_admin` | Super admin khác | Có | Có | Có nếu còn super admin active khác | Có | Có nếu còn super admin active khác |
| `super_admin` | Admin/manager | Có | Có | Có | Có | Có |
| `admin` | Chính mình | Có | Chỉ hồ sơ cá nhân | Không | Không | Không |
| `admin` | Super admin | Có | Không | Không | Không | Không |
| `admin` | Admin/manager khác | Có | Có | Có | Chỉ role không chứa quyền governance | Có |
| Manager/support | Bất kỳ tài khoản IAM | Không | Không | Không | Không | Không |

Quy tắc “còn super admin” chỉ đếm user `active` đang giữ role `super_admin`, và phải kiểm tra trong transaction kèm row lock để tránh hai request đồng thời cùng loại bỏ người cuối cùng.

#### Quy tắc role đã chốt

- Năm role seed mặc định là `is_system = true`; không được đổi `name` hoặc xóa qua UI.
- Ma trận permission của role hệ thống là **seed-owned/read-only** trên UI. Muốn thay đổi phải sửa `RolePermissionSeeder`, review code và chạy test.
- Role tùy chỉnh có `is_system = false`; IAM tạo role quản trị với `is_admin = true` và cho super admin chỉnh permission.
- `super_admin` luôn bypass permission; không sync/gỡ ma trận của role này qua UI.
- Không xóa role tùy chỉnh đang có user. Phải chuyển/gỡ user khỏi role trước.
- Tên role dùng snake_case, duy nhất, không được trùng tên hệ thống hoặc tên reserved.
- Khi admin gán role cho user, chỉ cho gán role không phải `super_admin`, không có permission governance (`roles.manage`, `permissions.manage`) và permission của role phải là tập con quyền hiệu lực của actor.

#### Quyết định schema cho Task 4.2

- Thêm `roles.is_admin` mặc định `false`; backfill 5 role trong `Role::ADMIN_ROLES` thành `true`.
- Thêm `roles.is_system` mặc định `false`; backfill 5 role seed thành `true`.
- Thêm `users.must_change_password` boolean mặc định `false` để hỗ trợ password tạm thời.
- Thêm `users.last_login_at` nullable timestamp và cập nhật sau đăng nhập admin thành công.
- Tạo `admin_audit_logs`; payload before/after dùng JSON nhưng loại bỏ `password`, `remember_token`, session/token và secret.
- Thêm permission `audit.view`; chưa tạo CRUD permission tùy ý.

#### Kết quả xác minh

- `php artisan route:list --path=admin`: 47 route admin được tải thành công.
- `AdminAuthorizationMiddlewareTest`: `6 passed (12 assertions)`.
- `UserAuthorizationModelTest`: `5 passed (19 assertions)`.
- Đây là task rà soát/tài liệu; chưa thay đổi schema hoặc runtime nên chưa cần chạy migration/full regression.

### [x] Task 4.2 — Bổ sung schema IAM và audit log

- [x] Tạo migration thêm `roles.is_admin`, `roles.is_system`.
- [x] Backfill các role trong `Role::ADMIN_ROLES` là role quản trị và role hệ thống.
- [x] Thêm `users.must_change_password`, `users.last_login_at` theo quyết định Task 4.1.
- [x] Tạo bảng `admin_audit_logs` có actor, action, subject, before/after, IP, user agent, timestamp.
- [x] Bảo toàn toàn bộ role, permission và liên kết hiện có.
- [x] Viết test migration `down → up` và dữ liệu backfill.

**Kết quả cần đạt:** schema hỗ trợ role động, bảo vệ role hệ thống và truy vết thay đổi.

#### Kết quả triển khai

- Migration `2026_10_30_000010_add_iam_fields_and_admin_audit_logs` đã thêm cờ `roles.is_admin`, `roles.is_system` và index tra cứu role quản trị.
- Năm role seed được backfill thành role quản trị/hệ thống; role tùy chỉnh giữ mặc định `false` và không bị seeder ghi đè.
- `users` có `must_change_password` mặc định `false` và `last_login_at` nullable.
- Tạo bảng `admin_audit_logs` với actor nullable `SET NULL`, subject polymorphic, action, JSON before/after, IP, user agent và các index tra cứu.
- Thêm model `AdminAuditLog`, relation actor/subject và `User::adminAuditLogs()`; JSON/time được cast đúng kiểu.
- Chuyển cast của `User` sang thuộc tính `$casts` tương thích Laravel 10, đảm bảo datetime/boolean/hashed password hoạt động.
- Thêm permission `audit.view`; thu hẹp role `admin` để không còn `roles.manage` và `permissions.manage`, nhưng vẫn có `roles.view`, `audit.view` và quyền quản lý user.
- Migration không phụ thuộc model runtime; danh sách role backfill được đóng băng trong migration để giữ tính lịch sử.
- Migration đã chạy trên MySQL ở batch 4 và `RolePermissionSeeder` đã đồng bộ thành công; hiện có `20/20` migration đã chạy.
- Kiểm thử `IamSchemaTest`: `5 passed (31 assertions)`.
- Toàn bộ regression suite: `115 passed (1003 assertions)`.

### [x] Task 4.3 — Service, policy và helper IAM

- [x] Đổi `User::isAdmin()` sang kiểm tra role có `is_admin = true`.
- [x] Giữ tương thích với seeder và tài khoản hiện có.
- [x] Tạo service giao dịch để sync role/permission và ghi audit.
- [x] Tạo guard kiểm tra super admin cuối cùng bằng transaction/row lock.
- [x] Tạo helper lấy permission hiệu lực không phát sinh N+1.
- [x] Chuẩn hóa exception/thông báo khi vi phạm quy tắc bảo mật.

**Kết quả cần đạt:** nghiệp vụ bảo mật tập trung, controller không chứa logic phân quyền rời rạc.

#### Kết quả triển khai

- `User::isAdmin()`, scope `administrators()` và scope `customers()` dùng `roles.is_admin`; role quản trị tùy chỉnh hoạt động mà không sửa hằng số PHP.
- `hasRole()`/`hasAnyRole()` tận dụng relation đã load; `effectivePermissionNames()` hợp quyền từ nhiều role và không query thêm khi đã eager-load `roles.permissions`.
- Tạo `IamService` để sync user-role, sync role-permission và cập nhật trạng thái trong transaction.
- Service khóa target/role bằng `lockForUpdate`, kiểm tra ID hợp lệ trước sync và rollback toàn bộ nếu vi phạm.
- Admin không thể gán `super_admin`, role governance hoặc permission cao hơn quyền hiệu lực của mình.
- Guard super admin cuối cùng chỉ đếm user `active`, khóa các bản ghi liên quan và chặn hạ trạng thái/gỡ role nếu không còn người thay thế.
- Tạo `AdminAuditService`; dữ liệu audit loại bỏ password, token, API key và secret kể cả trong mảng lồng nhau.
- Tạo `IamOperationException` với thông báo nghiệp vụ thống nhất cho self-action, last super admin, role không hợp lệ và system role read-only.
- Tạo `AdminUserPolicy`, `RolePolicy` và đăng ký trong `AuthServiceProvider`.
- `Gate::before` chỉ bypass ability dạng permission `module.action`; policy theo đối tượng như `update` vẫn được áp dụng cho super admin, nhờ vậy role hệ thống không bị sửa qua policy.
- Test `IamServiceTest`: `7 passed (33 assertions)`.
- Nhóm hồi quy authorization/customer/schema: `31 passed (130 assertions)`.
- Toàn bộ regression suite: `122 passed (1036 assertions)`.
- Không có migration mới trong Task 4.3.

## Giai đoạn B — Quản lý tài khoản quản trị

### [x] Task 4.4 — Danh sách và chi tiết tài khoản

- [x] Tạo `AdminUserController@index/show` và Form Request cho bộ lọc.
- [x] Danh sách phân trang, tìm theo tên/username/email/phone.
- [x] Lọc trạng thái, role và loại tài khoản.
- [x] Eager load role, không phát sinh N+1.
- [x] Trang chi tiết hiển thị hồ sơ, role, permission hiệu lực và audit gần nhất.
- [x] Thêm tab menu **Tài khoản & quyền** theo permission.

**Kết quả cần đạt:** admin được phép có thể tra cứu chính xác tài khoản và nguồn quyền.

#### Kết quả triển khai

- Thêm route `admin.users.index` và `admin.users.show`, cùng được bảo vệ bởi nhóm `auth`, `active`, `admin` và `users.view`.
- `AdminUserIndexRequest` chuẩn hóa/validate từ khóa, trạng thái và chỉ chấp nhận `role_id` có `is_admin = true`.
- Danh sách chỉ lấy `User::administrators()`, phân trang 15 tài khoản, tìm theo tên/username/email/phone và kết hợp lọc trạng thái/role.
- Role được eager-load và sắp xếp, tránh truy vấn N+1 trong bảng danh sách.
- Refactor scope tìm kiếm dùng chung thành `searchDirectory()`; `searchCustomers()` tiếp tục tương thích bằng cách ủy quyền cho scope mới.
- Trang chi tiết ẩn customer bằng `404`, hiển thị hồ sơ, trạng thái password, last login, role, system/admin flags và permission hiệu lực nhóm theo module.
- Hiển thị tối đa 10 audit gần nhất tác động trực tiếp đến user; actor được eager-load và vẫn hiển thị an toàn khi actor đã bị xóa.
- Thêm menu **Tài khoản & quyền** trên desktop/mobile, chỉ hiển thị khi có `users.view`.
- Route IAM tăng từ 47 lên 49 route admin.
- Test `AdminUserTest`: `6 passed (31 assertions)`.
- Toàn bộ regression suite: `128 passed (1067 assertions)`.
- Không có migration mới trong Task 4.4.

### [x] Task 4.5 — Tạo và cập nhật tài khoản quản trị

- [x] Validate username/email duy nhất, password mạnh và xác nhận password.
- [x] Tạo user và gán role trong một transaction.
- [x] Sửa hồ sơ mà không bắt buộc đổi password.
- [x] Cập nhật trạng thái active/blocked theo quy tắc an toàn.
- [x] Không cho tự khóa hoặc tự gỡ toàn bộ role quản trị.
- [x] Chỉ super admin được gán/gỡ `super_admin`.
- [x] Ghi audit, không ghi password.

**Kết quả cần đạt:** vòng đời tài khoản quản trị được quản lý an toàn và có thể truy vết.

#### Kết quả triển khai Task 4.5

- Bổ sung form tạo/sửa dùng chung, nút thao tác trên danh sách/chi tiết và 4 route `create`, `store`, `edit`, `update` có permission tương ứng.
- Username/email được chuẩn hóa và kiểm tra duy nhất; password tối thiểu 12 ký tự, có chữ hoa, chữ thường, số, ký tự đặc biệt và xác nhận. Username tương thích dữ liệu cũ có dấu chấm.
- `IamService` tạo/cập nhật user, đồng bộ role và ghi audit trong cùng transaction; cập nhật để trống password giữ nguyên hash hiện tại.
- Non-super admin chỉ thấy và gán role nằm trong tập quyền của chính mình; không thể gán role governance hoặc `super_admin`.
- Người đang đăng nhập chỉ được sửa hồ sơ/mật khẩu của chính mình; backend chặn tự đổi role hoặc tự chuyển khỏi trạng thái active.
- Customer bị ẩn bằng `404`; password rõ không xuất hiện trong audit payload.
- Test mục tiêu `AdminUserTest`: `12 passed (74 assertions)`.
- Toàn bộ regression suite: `134 passed (1110 assertions)`.
- Không có migration mới trong Task 4.5.

### [x] Task 4.6 — Xóa tài khoản và bảo vệ dữ liệu liên quan

- [x] Chặn tự xóa.
- [x] Chặn xóa super admin cuối cùng.
- [x] Chặn người không đủ quyền xóa super admin.
- [x] Rà soát FK `SET NULL`/cascade đối với order history, settings và audit.
- [x] Dùng transaction và hiển thị thông báo rõ ràng.
- [x] Ưu tiên khóa tài khoản nếu xóa làm mất dấu vết nghiệp vụ.

**Kết quả cần đạt:** không làm mất lịch sử hoặc khóa hệ thống khỏi quyền quản trị.

#### Kết quả triển khai Task 4.6

- Bổ sung route `DELETE /admin/users/{user}` dùng `users.delete`, Policy theo target và nút **Xóa / khóa** trên trang chi tiết.
- `IamService::deleteAdminUser()` khóa bản ghi và xử lý toàn bộ quyết định trong transaction; backend chặn customer, tự xóa, non-super xóa super admin và xóa super admin active cuối cùng.
- Chỉ xóa cứng tài khoản IAM chưa có dấu vết nghiệp vụ. Trước khi xóa, hệ thống ghi audit `user.deleted`; pivot `user_roles` được cascade theo FK.
- Nếu tài khoản có orders, order status histories, settings, audit actor, carts hoặc wishlists, hệ thống giữ nguyên user/role và chuyển trạng thái sang `blocked`, đồng thời ghi `user.blocked_instead_of_deleted` cùng danh sách quan hệ được bảo toàn.
- Rà soát FK hiện tại: `orders.user_id`, `order_status_histories.changed_by`, `settings.updated_by`, `admin_audit_logs.actor_id` dùng `SET NULL`; `user_roles`, `carts`, `wishlists` dùng cascade. `admin_audit_logs.subject_id` không có FK nên audit của subject đã xóa vẫn tồn tại.
- Giao diện hiển thị thông báo riêng cho kết quả xóa cứng, khóa thay thế hoặc lỗi safeguard.
- Test mục tiêu `AdminUserTest`: `17 passed (102 assertions)`.
- Toàn bộ regression suite: `139 passed (1138 assertions)`.
- Không có migration mới trong Task 4.6.

## Giai đoạn C — Role và ma trận permission

### [x] Task 4.7 — Danh sách và CRUD role

- [x] Hiển thị role, mô tả, loại system/custom, số user và số permission.
- [x] Tạo role quản trị tùy chỉnh với tên duy nhất.
- [x] Sửa tên/mô tả role tùy chỉnh.
- [x] Không đổi tên hoặc xóa role hệ thống.
- [x] Không xóa role đang được gán nếu chưa có phương án chuyển user.
- [x] Không cho tạo role tên trùng hoặc tên reserved.

**Kết quả cần đạt:** role tùy chỉnh hoạt động với middleware admin mà không sửa code danh sách tĩnh.

#### Kết quả triển khai Task 4.7

- Bổ sung 6 route `admin.roles.*`, menu desktop/mobile theo `roles.view`, danh sách phân trang 15 role và tìm kiếm theo tên/mô tả.
- Danh sách hiển thị system/custom, admin/thông thường, `users_count` và `permissions_count` bằng aggregate query, không phát sinh N+1.
- Super admin có thể tạo, sửa và xóa role tùy chỉnh; admin thường chỉ được xem vì security matrix không cấp `roles.manage`.
- Tên role được chuẩn hóa về `snake_case`, kiểm tra duy nhất, đúng định dạng và không thuộc danh sách reserved của năm system role.
- Role mới luôn có `is_admin = true`, `is_system = false`; role được middleware admin nhận diện động mà không sửa danh sách PHP tĩnh.
- Role hệ thống là seed-owned/read-only. Role tùy chỉnh đang được gán cho user không thể xóa; giao diện khóa nút và service kiểm tra lại trong transaction.
- Create/update/delete role đều đi qua `IamService`, khóa bản ghi khi cần và ghi audit `role.created`, `role.updated`, `role.deleted`. Permission matrix chưa được sửa trong task này.
- Test mục tiêu `AdminRoleTest`: `6 passed (49 assertions)`.
- Toàn bộ regression suite: `145 passed (1187 assertions)`.
- Không có migration mới trong Task 4.7.

### [x] Task 4.8 — Ma trận permission theo module

- [x] Nhóm permission theo prefix: dashboard, catalog, orders, customers, contacts, users, roles, settings.
- [x] Giao diện chọn tất cả theo module và trạng thái indeterminate.
- [x] Validate tất cả permission ID tồn tại.
- [x] Sync permission trong transaction và ghi audit before/after.
- [x] Không cho hạ quyền `super_admin` qua ma trận.
- [x] Chặn người thao tác tự gỡ permission cần thiết khỏi role của chính mình nếu gây lockout.

**Kết quả cần đạt:** ma trận dễ hiểu, cập nhật nguyên tử và không tạo lỗ hổng leo thang đặc quyền.

#### Kết quả triển khai Task 4.8

- Ma trận được hiển thị trong trang sửa custom role và bao phủ toàn bộ permission catalog đúng một lần.
- `products.*`, `categories.*`, `brands.*` được gom vào Catalog; `permissions.*` và `audit.*` thuộc nhóm Vai trò & phân quyền.
- Có chọn tất cả toàn ma trận, chọn theo module, số quyền đã chọn và trạng thái checkbox `indeterminate`.
- Endpoint `admin.roles.permissions.update` dùng Form Request để kiểm tra mảng, ID tồn tại và không trùng lặp.
- IAM service khóa role, kiểm tra system role, chống self-lockout, sync pivot và ghi audit before/after trong cùng transaction.
- Role hệ thống, bao gồm `super_admin`, không thể bị hạ quyền qua UI; chỉ super admin được quản lý ma trận.
- Test mục tiêu `AdminRolePermissionMatrixTest`: `6 passed (42 assertions)`.
- Toàn bộ regression suite: `151 passed (1229 assertions)`.
- Không có migration mới trong Task 4.8.

### [x] Task 4.9 — Audit log và lịch sử thay đổi

- [x] Danh sách audit phân trang, mới nhất trước.
- [x] Lọc theo actor, action, loại subject và khoảng ngày.
- [x] Xem chi tiết before/after dưới dạng an toàn, dễ đọc.
- [x] Ẩn field nhạy cảm bằng allowlist/denylist ở service.
- [x] Gắn liên kết audit từ trang user và role.
- [x] Chỉ người có `permissions.manage` được xem.

**Kết quả cần đạt:** mọi thay đổi quyền quan trọng đều trả lời được ai làm, làm gì và khi nào.

#### Kết quả triển khai Task 4.9

- Thêm danh sách `/admin/access-logs`, phân trang 20 sự kiện và sắp xếp mới nhất trước.
- Bộ lọc hỗ trợ actor, action, alias subject an toàn (`user`/`role`), subject ID và khoảng ngày; query string được giữ khi phân trang.
- Trang chi tiết trình bày actor, thời gian, subject, IP, user agent và payload before/after dạng cây dễ đọc.
- Payload được sanitize khi ghi và lọc lại bằng root allowlist + sensitive-key denylist khi đọc; Blade escape nội dung chống thực thi HTML/XSS.
- Menu desktop/mobile và liên kết lịch sử theo subject đã được thêm từ trang tài khoản và role.
- Chỉ người có `permissions.manage` truy cập được danh sách và chi tiết; không có route sửa/xóa audit.
- Test mục tiêu `AdminAuditLogTest`: `7 passed (30 assertions)`.
- Toàn bộ regression suite: `158 passed (1259 assertions)`.
- Không có migration mới trong Task 4.9.

## Giai đoạn D — Kiểm thử và hardening

### [x] Task 4.10 — Kiểm thử tích hợp IAM

- [x] Test quyền xem/tạo/sửa/xóa user.
- [x] Test role mới được nhận diện là admin bằng `is_admin`.
- [x] Test hợp quyền từ nhiều role.
- [x] Test super admin bypass và chỉ super admin được quản lý super admin.
- [x] Test không tự khóa, tự xóa, tự gỡ quyền quản trị.
- [x] Test không xóa/gỡ super admin cuối cùng.
- [x] Test role hệ thống không bị đổi tên/xóa.
- [x] Test sync permission rollback khi lỗi.
- [x] Test audit không chứa password/secret.
- [x] Test middleware từ chối ngay sau khi role/quyền bị gỡ.
- [x] Chạy toàn bộ regression suite.

**Kết quả cần đạt:** module IAM an toàn, không phá authentication, customer hoặc các permission module hiện có.

#### Kết quả triển khai Task 4.10

- Thêm `IamIntegrationTest` để kiểm tra xuyên controller, middleware, policy, model và IAM service thay vì chỉ kiểm thử từng lớp riêng lẻ.
- Xác nhận custom role `is_admin` hoạt động mà không cần sửa hằng số; quyền hiệu lực là hợp của nhiều role.
- Xác nhận gỡ permission hoặc admin role bị middleware từ chối ngay ở request kế tiếp, không cần đăng nhập lại.
- Xác nhận ma trận role tối thiểu, super-admin bypass, object policy, self-action, last-super-admin và system-role guard.
- Xác nhận tạo user + gán role + audit nguyên tử và audit không chứa mật khẩu rõ hoặc hash.
- Rollback permission khi audit lỗi được bao phủ bởi `AdminRolePermissionMatrixTest`; audit allowlist/denylist được bao phủ bởi `AdminAuditLogTest`.
- Test mục tiêu `IamIntegrationTest`: `6 passed (39 assertions)`.
- Toàn bộ regression suite: `164 passed (1298 assertions)`.
- Không có migration mới trong Task 4.10.

## Giai đoạn E — Nâng cấp trải nghiệm quản trị role

### [x] Task 4.11 — Tích hợp permission vào form tạo/sửa role

- [x] Hiển thị ma trận permission trên cả form tạo và form sửa role.
- [x] Tách ma trận thành Blade partial và JavaScript dùng chung.
- [x] Validate permission ID tồn tại, không trùng và cho phép mảng rỗng.
- [x] Tạo role và gán permission trong cùng transaction.
- [x] Cập nhật metadata và permission trong cùng transaction.
- [x] Ghi một audit snapshot role + permission trước/sau cho mỗi lần lưu.
- [x] Giữ endpoint sync permission riêng để tương thích ngược.
- [x] Bảo toàn system-role guard, self-lockout guard và quyền Super Admin.
- [x] Kiểm thử rollback, validation, authorization và full regression.

**Kết quả cần đạt:** quản trị viên tạo hoặc sửa custom role và chọn permission trong một form duy nhất; dữ liệu role, pivot và audit luôn nhất quán.

#### Kết quả triển khai Task 4.11

- Form tạo và sửa role dùng chung ma trận permission, một nút submit và giữ lại checkbox qua `old()` khi validation lỗi.
- Ma trận được tách thành `admin.roles.partials.permission-matrix`; logic chọn tất cả/module/indeterminate nằm trong `role-permission-matrix.js`.
- Permission nhạy cảm (`roles.manage`, `permissions.manage`, `users.delete`) có cảnh báo trực quan.
- `StoreRoleRequest` và `UpdateRoleRequest` validate mảng permission tồn tại, không trùng và cho phép rỗng.
- `IamService` tạo/cập nhật metadata, sync pivot và ghi một audit snapshot role + permissions trong cùng transaction.
- Endpoint `admin.roles.permissions.update` vẫn hoạt động để tương thích ngược nhưng form mới không phụ thuộc endpoint này.
- Test mục tiêu role: `14 passed (112 assertions)`.
- Toàn bộ regression suite: `166 passed (1319 assertions)`.
- Không có migration mới trong Task 4.11.

### [x] Task 4.12 — Tinh gọn giao diện danh sách role và permission

- [x] Làm rõ nút sửa/phân quyền trên custom role.
- [x] Hiển thị trạng thái khóa và lý do khi actor không đủ quyền sửa.
- [x] Thu gọn permission theo accordion module.
- [x] Hiển thị số permission đã chọn theo từng module và toàn bộ ma trận.
- [x] Bổ sung tìm kiếm permission phía client.
- [x] Giữ select-all, module toggle và trạng thái indeterminate.
- [x] Kiểm thử authorization, Blade và JavaScript liên quan.

**Kết quả cần đạt:** thao tác role rõ ràng, ma trận permission gọn, dễ quét và không làm yếu quy tắc phân quyền hiện có.

#### Kết quả triển khai Task 4.12

- Custom role có nút chính `Sửa & phân quyền`; actor không đủ quyền thấy nút khóa và tooltip giải thích chỉ Super Admin được sửa.
- System role tiếp tục hiển thị trạng thái seeder quản lý và không có thao tác sửa.
- Permission được thu gọn theo accordion module, mỗi nhóm có counter đã chọn/tổng số.
- Có tìm kiếm theo tên/mô tả, tự mở module có kết quả, nút xóa tìm kiếm và empty state.
- Select-all toàn bộ/module, `indeterminate` và counter tổng tiếp tục được đồng bộ bằng JavaScript.
- Test mục tiêu role: `14 passed (118 assertions)`.
- Toàn bộ regression suite: `166 passed (1325 assertions)`.
- Không có migration mới trong Task 4.12.

### [x] Task 4.13 — Cho phép Super Admin quản lý role hệ thống

- [x] Chỉ khóa tuyệt đối role `super_admin` trên policy, service và giao diện.
- [x] Cho phép Super Admin sửa mô tả và permission của các role hệ thống còn lại.
- [x] Khóa tên role chuẩn; chỉ khóa xóa `super_admin`, role khác chỉ được xóa khi chưa gán tài khoản.
- [x] Làm rõ trạng thái và thao tác trên danh sách/form role.
- [x] Bảo đảm seeder không ghi đè ma trận permission đã tùy chỉnh.
- [x] Kiểm thử authorization, validation, audit, seeder và full regression.

**Kết quả cần đạt:** Super Admin có thể điều chỉnh quyền và xóa role chưa được sử dụng; riêng `super_admin` vẫn bất biến, còn tên các role chuẩn không thể đổi.

#### Kết quả triển khai Task 4.13

- Policy và IAM service chỉ chặn cập nhật role có tên `super_admin`; system role khác được Super Admin sửa mô tả và permission.
- Tên mọi system role được render `readonly`, validate phía server và kiểm tra lại trong service; chỉ `super_admin` bị khóa xóa, role khác phải chưa được gán tài khoản mới có thể xóa.
- Danh sách hiển thị nút `Sửa & phân quyền` cho system role có thể chỉnh và trạng thái `Bảo vệ tuyệt đối` cho `super_admin`.
- Seeder chỉ gán ma trận mặc định khi tạo system role lần đầu, không ghi đè permission đã tùy chỉnh khi chạy lại.
- Bản vá regression nhận diện role hệ thống theo tên reserved thay vì chỉ dựa vào `is_system`, tránh lỗi validate tên khi dữ liệu cờ legacy bị lệch.
- Test mục tiêu: `AdminRoleTest` 7 test/76 assertions; `AdminRolePermissionMatrixTest` 8 test/64 assertions; `IamIntegrationTest` 6 test/44 assertions; `IamServiceTest` 7 test/35 assertions.
- Toàn bộ regression suite: `167 passed (1354 assertions)`.
- Không có migration mới trong Task 4.13.

## 6. Thứ tự dependency

```text
4.1 → 4.2 → 4.3
          ↓
       4.4 → 4.5 → 4.6
          ↓
       4.7 → 4.8 → 4.9
                      ↓
                    4.10
```

- Task 4.2 chỉ bắt đầu sau khi chốt rule ở 4.1.
- Task 4.4–4.9 phụ thuộc service/guard ở 4.3.
- Task 4.8 phụ thuộc CRUD role ở 4.7.
- Task 4.10 chạy sau khi mọi luồng nghiệp vụ hoàn tất.

## 7. Ma trận kiểm thử tối thiểu

| Vai trò | Xem user | Tạo/sửa user | Gán super admin | Quản lý role | Sync permission | Xem audit |
|---|---:|---:|---:|---:|---:|---:|
| `super_admin` | Có | Có | Có | Có | Có | Có |
| `admin` | Có | Có, trừ super admin | Không | Chỉ xem | Không | Có |
| `catalog_manager` | Không | Không | Không | Không | Không | Không |
| `order_manager` | Không | Không | Không | Không | Không | Không |
| `customer_support` | Không | Không | Không | Không | Không | Không |

Ma trận trên đã được xác nhận ở Task 4.1 và đã đồng bộ vào `RolePermissionSeeder` ở Task 4.2.

## 8. Tiêu chí hoàn thành toàn roadmap

- Không có route IAM nào thiếu middleware và permission phù hợp.
- Không thể tạo trạng thái không còn super admin hoạt động.
- Không thể tự khóa, tự xóa hoặc tự loại mình khỏi khu vực admin.
- Role tùy chỉnh có thể truy cập admin theo `is_admin`, không cần sửa hằng số PHP.
- Sync role/permission nguyên tử, rollback khi lỗi.
- Audit đầy đủ và không chứa secret.
- Không phát sinh N+1 ở danh sách user/role.
- Seeder chạy lại không làm mất role/permission tùy chỉnh ngoài phạm vi quản lý.
- Test mục tiêu và full regression đều đạt.
- Roadmap và `pm-board.js` được cập nhật sau từng task.

## 9. Nhật ký cập nhật

| Thời gian | Task | Trạng thái | Thay đổi chính | Kiểm thử |
|---|---|---|---|---|
| 29/10/2026 | Lập roadmap | Hoàn thành | Rà soát RBAC hiện tại và tạo kế hoạch 10 task cho IAM | Rà soát tĩnh, chưa thay đổi code runtime |
| 30/10/2026 | 4.1 | Hoàn thành | Kiểm kê 29 permission/47 route; chốt phạm vi IAM, ma trận role, actor-target, system role và schema kế tiếp | Authorization middleware: 6 test/12 assertions; authorization model: 5 test/19 assertions |
| 30/10/2026 | 4.2 | Hoàn thành | Thêm role flags, trạng thái password/login, audit log, audit.view và đồng bộ lại ma trận admin | IAM schema: 5 test/31 assertions; full regression: 115 test/1003 assertions |
| 30/10/2026 | 4.3 | Hoàn thành | Thêm dynamic admin scope, IAM transaction service, policy, audit sanitizer, effective permissions và last-super-admin guard | IAM service: 7 test/33 assertions; full regression: 122 test/1036 assertions |
| 30/10/2026 | 4.4 | Hoàn thành | Thêm danh sách/chi tiết tài khoản IAM, bộ lọc, permission hiệu lực, audit gần nhất và menu theo quyền | Admin user: 6 test/31 assertions; full regression: 128 test/1067 assertions |
| 05/11/2026 | 4.10 | Hoàn thành | Thêm quality gate tích hợp IAM, xác nhận permission union/revocation, safeguard và audit an toàn; đóng roadmap IAM | IAM integration: 6 test/39 assertions; full regression: 164 test/1298 assertions |
| 05/11/2026 | 4.11 | Hoàn thành | Hợp nhất thông tin role và ma trận permission trên form create/edit; transaction và audit snapshot thống nhất | Role suites: 14 test/112 assertions; full regression: 166 test/1319 assertions |
| 05/11/2026 | 4.12 | Hoàn thành | Làm rõ action sửa role; thu gọn permission theo accordion, counter và tìm kiếm phía client | Role suites: 14 test/118 assertions; full regression: 166 test/1325 assertions |
| 05/11/2026 | 4.13 | Hoàn thành | Chỉ khóa super_admin; role khác được sửa và xóa khi chưa gán user; tên role chuẩn vẫn khóa và nhận diện an toàn khi cờ legacy bị lệch | 4 IAM suites: 28 test/219 assertions; full regression: 167 test/1354 assertions |

## 10. Mẫu cập nhật sau mỗi task

1. Chuyển task từ `[ ]`/`[-]` thành `[x]` chỉ sau khi test đạt.
2. Đánh dấu từng checklist đã hoàn thành.
3. Ghi quyết định nghiệp vụ hoặc thay đổi security matrix ngay dưới task.
4. Thêm một dòng vào **Nhật ký cập nhật** với kết quả test thực tế.
5. Đồng bộ task, activity, risk, decision và Test Center trong `docs/assets/pm-board/pm-board.js`.

