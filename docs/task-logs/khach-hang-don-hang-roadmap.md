# Nhật ký triển khai quản lý khách hàng và đơn hàng

> Ngày lập kế hoạch: 29/10/2026  
> Trạng thái tổng thể: Hoàn thành toàn bộ Giai đoạn 1, 2 và 3  
> Quy ước: `[ ]` Chưa làm · `[-]` Đang làm · `[x]` Hoàn thành · `[!]` Bị chặn

## Mục tiêu

Xây dựng theo thứ tự:

1. Quản lý khách hàng cơ bản.
2. Quản lý đơn hàng hoàn chỉnh.
3. Nâng cấp hồ sơ và thống kê khách hàng từ dữ liệu đơn hàng.

Mỗi task chỉ được đánh dấu hoàn thành sau khi code, kiểm thử liên quan và kiểm tra hồi quy đều đạt.

## Giai đoạn 1 — Quản lý khách hàng cơ bản

### [x] Task 1.1 — Rà soát dữ liệu và nghiệp vụ khách hàng

- [x] Kiểm tra migration `users`, model `User` và các quan hệ.
- [x] Xác định chính xác cách phân biệt khách hàng với tài khoản quản trị.
- [x] Kiểm tra các trường trạng thái, họ tên, username, email và điện thoại.
- [x] Chốt quy tắc khóa/mở tài khoản.
- [x] Không cho quản trị viên tự khóa chính mình.
- [x] Không hiển thị tài khoản có role quản trị trong danh sách khách hàng.

**Kết quả cần đạt:** có tài liệu/quy tắc query và bảo mật rõ ràng trước khi làm giao diện.

#### Kết quả rà soát và quyết định nghiệp vụ

**Cấu trúc hiện có**

- `users` đã có đủ trường nền: `name`, `username`, `email`, `email_verified_at`, `password`, `first_name`, `last_name`, `phone`, `status`, timestamps và `remember_token`.
- `username` là duy nhất; trên MySQL/MariaDB đã được migration chuyển thành `NOT NULL`. `email` cũng là duy nhất.
- `first_name`, `last_name` và `phone` được phép `NULL`; `phone` hiện không có ràng buộc duy nhất.
- `status` nhận một trong ba giá trị: `active`, `inactive`, `blocked`; mặc định là `active`.
- `User` đã có quan hệ `roles`, `orders`, `carts`, `wishlistItems`, `wishlistProducts` và `updatedSettings`.
- `orders.user_id` được phép `NULL` và dùng `ON DELETE SET NULL`; lịch sử đơn không mất nếu sau này tài khoản bị xóa.
- Module khách hàng hiện mới là trang placeholder trong `AdminController`; route đã được bảo vệ bởi `customers.view`.

**Định nghĩa khách hàng**

- Khách hàng là user **không sở hữu bất kỳ role quản trị nào** trong `Role::ADMIN_ROLES`.
- Query chuẩn sẽ dùng `whereDoesntHave('roles', ...)` với danh sách `Role::ADMIN_ROLES`.
- Không dùng điều kiện “không có role”, vì sau này khách hàng có thể được gán role thành viên hoặc loyalty mà vẫn phải xuất hiện trong module khách hàng.
- Mọi route xem/cập nhật chi tiết phải áp dụng lại điều kiện này ở backend; chỉ ẩn trên giao diện là không đủ.

**Quy tắc tên và tìm kiếm**

- `name` là tên hiển thị dự phòng và luôn có theo schema gốc.
- Nếu có `first_name`/`last_name`, giao diện ưu tiên tên ghép; nếu cả hai trống thì dùng `name`.
- Tìm kiếm sẽ bao phủ `name`, `first_name`, `last_name`, `username`, `email` và `phone`.
- Không giả định số điện thoại là duy nhất.

**Ý nghĩa trạng thái và thao tác**

- `active`: tài khoản được phép hoạt động.
- `inactive`: tài khoản tạm ngừng/không hoạt động; vẫn hiển thị trong danh sách để quản lý.
- `blocked`: tài khoản bị quản trị viên khóa.
- Chức năng khóa/mở ở Task 1.4 chỉ chuyển sang `blocked` hoặc `active`; không xóa tài khoản.
- Tài khoản `inactive` có thể được mở lại thành `active`, nhưng chức năng này không chủ động đặt user thành `inactive`.
- Middleware `EnsureUserIsActive` hiện coi cả `inactive` và `blocked` là không hoạt động, đăng xuất user và hủy session khi truy cập route có middleware `active`.

**Quy tắc bảo mật và phân quyền**

- Danh sách/chi tiết dùng `customers.view`; thay đổi trạng thái dùng `customers.update`.
- Theo ma trận hiện tại, `admin` và `super_admin` có thể cập nhật; `order_manager` và `customer_support` chỉ có quyền xem.
- Module khách hàng tuyệt đối không được cập nhật user có role thuộc `Role::ADMIN_ROLES`.
- Backend vẫn kiểm tra và từ chối thao tác nếu target trùng user đang đăng nhập, dù query khách hàng đã loại admin; đây là lớp phòng vệ bổ sung.
- Không triển khai xóa khách hàng trong giai đoạn này.

**Query chuẩn dự kiến**

```php
User::query()
    ->whereDoesntHave('roles', fn ($query) =>
        $query->whereIn('roles.name', Role::ADMIN_ROLES)
    );
```

**Kết luận schema**

- Không cần migration mới cho module khách hàng cơ bản.
- Task 1.2 có thể thay placeholder bằng `AdminCustomerController` và dùng trực tiếp schema hiện tại.

### [x] Task 1.2 — Danh sách khách hàng

- [x] Tạo `AdminCustomerController`.
- [x] Thay route placeholder `/admin/customers`.
- [x] Áp dụng `auth`, `active`, `admin`, `permission:customers.view`.
- [x] Hiển thị danh sách có phân trang.
- [x] Tìm theo họ tên, username, email và số điện thoại.
- [x] Lọc theo trạng thái hoạt động hoặc bị khóa.
- [x] Giữ bộ lọc khi chuyển trang.
- [x] Chỉ hiện menu khách hàng khi có quyền phù hợp.

**Kết quả cần đạt:** admin có quyền xem được danh sách, tìm kiếm và lọc khách hàng.

#### Kết quả triển khai

- Thêm `AdminCustomerController@index` và `AdminCustomerIndexRequest`.
- Thay route placeholder nhưng giữ tên route `admin.customers` để tương thích các liên kết hiện tại.
- Thêm `User::customers()` để loại toàn bộ role quản trị và `User::searchCustomers()` để tái sử dụng query.
- Thêm accessor `display_name` và danh sách nhãn trạng thái tập trung trong model.
- Giao diện hiển thị 15 khách hàng mỗi trang, hỗ trợ trạng thái rỗng và responsive table.
- Bộ lọc được validate; query string được giữ khi chuyển trang.
- Menu sidebar tiếp tục phụ thuộc `customers.view` và nhận diện cả các route con trong tương lai.
- Tài khoản có role không thuộc nhóm admin (ví dụ loyalty) vẫn được nhận diện là khách hàng.
- File chính: `AdminCustomerController.php`, `AdminCustomerIndexRequest.php`, `User.php`, `admin/customers/index.blade.php`, `routes/web.php` và `AdminCustomerTest.php`.
- Kiểm thử: `66 passed (365 assertions)`; riêng module khách hàng có 4 test và 12 assertions.

### [x] Task 1.3 — Trang chi tiết khách hàng

- [x] Hiển thị thông tin tài khoản.
- [x] Hiển thị ngày đăng ký và ngày cập nhật gần nhất.
- [x] Hiển thị trạng thái tài khoản.
- [x] Chuẩn bị khu vực thống kê đơn hàng.
- [x] Áp dụng route model binding.
- [x] Trả về `404` nếu tài khoản không phải khách hàng.

**Kết quả cần đạt:** có hồ sơ khách hàng để liên kết từ module đơn hàng.

#### Kết quả triển khai

- Thêm route `admin.customers.show` sử dụng implicit route model binding với tham số `{customer}`.
- Backend gọi `User::isCustomer()` và trả về `404` nếu target có bất kỳ role quản trị nào.
- Danh sách khách hàng có nút **Xem** dẫn tới hồ sơ chi tiết.
- Trang chi tiết hiển thị tên, username, email/xác minh email, điện thoại, trạng thái, ID và các mốc thời gian.
- Khu vực tổng quan đơn hàng hiển thị tổng số đơn, số đơn đang chờ và số đơn đã giao; chưa triển khai doanh thu/lịch sử chi tiết để giữ đúng phạm vi giai đoạn 3.
- Route tiếp tục được bảo vệ bởi `auth`, `active`, `admin` và `customers.view`.
- Test bao phủ dữ liệu hồ sơ, thống kê cơ bản, link từ danh sách, quyền truy cập, ID không tồn tại và che tài khoản admin bằng `404`.
- File chính: `AdminCustomerController.php`, `User.php`, `admin/customers/index.blade.php`, `admin/customers/show.blade.php`, `routes/web.php` và `AdminCustomerTest.php`.
- Kiểm thử: `69 passed (375 assertions)`; riêng module khách hàng có 7 test và 22 assertions.

### [x] Task 1.4 — Khóa và mở tài khoản

- [x] Tạo Form Request kiểm tra trạng thái đầu vào.
- [x] Tạo route `PATCH` và middleware `customers.update`.
- [x] Không cho cập nhật tài khoản admin qua module khách hàng.
- [x] Không cho admin tự khóa chính mình.
- [x] Hiển thị thông báo thành công hoặc lỗi phù hợp.
- [x] Xác nhận middleware trạng thái chặn tài khoản đã khóa.

**Kết quả cần đạt:** trạng thái khách hàng được quản lý an toàn.

#### Kết quả triển khai

- Thêm `UpdateCustomerStatusRequest`, chỉ chấp nhận `active` hoặc `blocked`.
- Thêm route `PATCH admin/customers/{customer}/status` với quyền `customers.update`.
- Trang chi tiết hiển thị nút **Khóa tài khoản** hoặc **Mở tài khoản** theo trạng thái hiện tại và yêu cầu xác nhận trước khi gửi.
- Tài khoản `inactive` có thể được mở lại thành `active`; giao diện không chủ động đặt trạng thái `inactive`.
- Backend trả `403` khi target là chính user đang đăng nhập và trả `404` khi target là tài khoản quản trị khác.
- User chỉ có `customers.view` không thể cập nhật trạng thái.
- Đã xác nhận `EnsureUserIsActive` đăng xuất, hủy session của tài khoản bị khóa khi truy cập route được bảo vệ.
- File chính: `UpdateCustomerStatusRequest.php`, `AdminCustomerController.php`, `admin/customers/show.blade.php`, `routes/web.php` và `AdminCustomerTest.php`.
- Kiểm thử: `72 passed (393 assertions)`; riêng module khách hàng có 10 test và 40 assertions.

### [x] Task 1.5 — Seeder và kiểm thử khách hàng

- [x] Tạo nhiều khách hàng mẫu.
- [x] Seeder chạy lại không nhân đôi dữ liệu.
- [x] Test phân trang, tìm kiếm và lọc.
- [x] Test không hiển thị tài khoản admin.
- [x] Test quyền `customers.view` và `customers.update`.
- [x] Test khóa/mở tài khoản.
- [x] Test không thao tác được với tài khoản admin.
- [x] Test khách hàng không tồn tại/không hợp lệ trả về `404`.
- [x] Chạy toàn bộ test suite.

**Kết quả cần đạt:** module khách hàng cơ bản hoàn chỉnh và có kiểm thử hồi quy.

#### Kết quả triển khai

- Thêm `CustomerSeeder` tạo 45 khách hàng demo với email `customer001@example.test` đến `customer045@example.test`.
- Phân bố trạng thái: 35 `active`, 4 `inactive`, 6 `blocked`; có đầy đủ tên, username và điện thoại.
- Tài khoản demo mới dùng password `Customer@123`; seeder không đặt lại password của bản ghi đã tồn tại.
- Seeder không gán role, vì vậy các tài khoản mẫu được nhận diện là khách hàng.
- Dùng `firstOrNew` theo email nên chạy lại không nhân đôi; được đăng ký trong `DatabaseSeeder`.
- Thêm `CustomerSeederTest` kiểm tra số lượng, trạng thái, dữ liệu liên hệ, password, phân loại khách hàng, không tạo role và tính idempotent.
- Các test từ Task 1.2–1.4 đã bao phủ phân trang, tìm kiếm, lọc, loại admin, quyền, khóa/mở và 404.
- File chính: `CustomerSeeder.php`, `DatabaseSeeder.php`, `CustomerSeederTest.php` và `AdminCustomerTest.php`.
- Kiểm thử: `73 passed (404 assertions)`; nhóm khách hàng/seeder có 11 test và 51 assertions.

## Giai đoạn 2 — Quản lý đơn hàng

### [x] Task 2.1 — Rà soát và bổ sung schema đơn hàng

- [x] Kiểm tra `orders`, `order_items` và các model.
- [x] Chuẩn hóa danh sách trạng thái và luồng chuyển trạng thái.
- [x] Bổ sung bảng `order_status_histories`.
- [x] Đánh giá các cột người cập nhật, lý do hủy và mốc thời gian nghiệp vụ.
- [x] Migration mới phải bảo toàn dữ liệu hiện tại.

**Kết quả cần đạt:** schema đủ cho quy trình xử lý và truy vết đơn hàng.

#### Kết quả rà soát và triển khai

**Workflow đã chốt**

```text
pending → confirmed → processing → shipped → delivered
    └→ cancelled      └→ cancelled
             └→ cancelled
```

- Cho phép hủy khi đơn đang `pending`, `confirmed` hoặc `processing`.
- Đơn `shipped` chỉ được chuyển sang `delivered`; không hủy sau khi đã bàn giao vận chuyển trong workflow chuẩn.
- `delivered` và `cancelled` là trạng thái kết thúc.
- `Order::TRANSITIONS`, `canTransitionTo()` và `allowedTransitions()` là nguồn quy tắc dùng chung cho Task 2.5.
- Nhãn trạng thái đơn, trạng thái thanh toán và phương thức thanh toán được tập trung trong model `Order`.

**Schema bổ sung**

- Thêm `cancel_reason`.
- Thêm các mốc `confirmed_at`, `processing_at`, `shipped_at`, `delivered_at`, `cancelled_at`.
- Thêm `stock_deducted_at` và `stock_restored_at` để Task 2.6 chống trừ/hoàn kho lặp lại.
- Thêm index `(status, created_at)` và `(payment_status, created_at)` phục vụ danh sách/bộ lọc.
- Tạo bảng `order_status_histories`: `order_id`, `from_status`, `to_status`, `changed_by`, `note`, `created_at`.
- Xóa đơn sẽ cascade lịch sử; xóa user thao tác chỉ đặt `changed_by = NULL`, không làm mất lịch sử.
- Model `OrderStatusHistory` và các quan hệ từ `Order`, `User` đã được bổ sung.

**Bảo toàn dữ liệu**

- Migration chỉ thêm cột/bảng/index, không thay đổi các cột đơn hàng cũ.
- Mọi đơn đã tồn tại được backfill một lịch sử khởi tạo với trạng thái hiện tại, `from_status = NULL`, `changed_by = NULL` và thời gian tạo đơn.
- Test đã chạy migration `down → up` trên đơn tồn tại và xác nhận đơn còn nguyên cùng bản ghi lịch sử khởi tạo.

**File và kiểm thử**

- Migration: `2026_10_29_000010_add_workflow_fields_to_orders_table.php`.
- Model: `Order.php`, `OrderStatusHistory.php`, `User.php`.
- Test: `OrderWorkflowSchemaTest.php`, `EcommerceSchemaTest.php`.
- Kết quả: `77 passed (422 assertions)`; riêng workflow schema có 4 test và 16 assertions.

### [x] Task 2.2 — Seeder đơn hàng mẫu

- [x] Tạo đơn của khách có tài khoản và khách vãng lai.
- [x] Phân bố nhiều trạng thái đơn và thanh toán.
- [x] Tạo `order_items` bằng dữ liệu snapshot.
- [x] Đảm bảo tổng tiền khớp số lượng, giá và phí vận chuyển.
- [x] Không thay đổi tồn kho khi seed dữ liệu demo.
- [x] Seeder chạy lại không nhân đôi.

**Kết quả cần đạt:** đủ dữ liệu thực tế để phát triển và test giao diện.

#### Kết quả triển khai

- Thêm `OrderSeeder` tạo 48 đơn từ `DEMO-ORDER-001` đến `DEMO-ORDER-048`.
- Có 36 đơn gắn tài khoản khách hàng và 12 đơn khách vãng lai.
- Mỗi trạng thái `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled` có 8 đơn.
- Phân bố đủ phương thức và trạng thái thanh toán; đơn hủy có lý do hủy.
- Mỗi đơn có 1–4 `order_items`; tên, SKU, giá và thành tiền là snapshot tại thời điểm seed.
- `subtotal` được tính từ các dòng hàng; `total_amount = subtotal + shipping_fee`.
- Mỗi đơn có timeline `order_status_histories` hợp lệ, bản ghi cuối khớp trạng thái hiện tại.
- Seeder chủ động để `stock_deducted_at` và `stock_restored_at` là `NULL` và không cập nhật `products.quantity`.
- Chạy riêng được: tự gọi `CustomerSeeder`/`ProductSeeder` khi thiếu dữ liệu demo.
- Chạy lại dùng mã đơn cố định, thay thế items/lịch sử của bộ demo thay vì nhân đôi.
- Đã đăng ký `OrderSeeder` sau `CustomerSeeder` và `ProductSeeder` trong `DatabaseSeeder`.
- File chính: `OrderSeeder.php`, `DatabaseSeeder.php`, `OrderSeederTest.php`.
- Kiểm thử: `79 passed (775 assertions)`; riêng seeder đơn hàng có 2 test và 353 assertions.

### [x] Task 2.3 — Danh sách đơn hàng

- [x] Tạo `AdminOrderController`.
- [x] Phân trang danh sách.
- [x] Tìm theo mã đơn, tên người nhận, điện thoại và email tài khoản.
- [x] Lọc theo trạng thái đơn, thanh toán và phương thức thanh toán.
- [x] Lọc theo khoảng ngày.
- [x] Hiển thị rõ khách có tài khoản và khách vãng lai.
- [x] Giữ query khi chuyển trang.

**Kết quả cần đạt:** admin tìm kiếm và theo dõi đơn hàng thuận tiện.

#### Kết quả triển khai

- Thêm `AdminOrderController@index` và thay route placeholder nhưng giữ tên `admin.orders`.
- Thêm `AdminOrderIndexRequest` để chuẩn hóa và validate toàn bộ query filter.
- Danh sách sắp xếp theo `created_at`, `id` mới nhất và phân trang 15 đơn.
- Tìm kiếm theo mã đơn, tên người nhận, điện thoại và email tài khoản liên kết.
- Lọc kết hợp theo trạng thái đơn, trạng thái thanh toán, phương thức và khoảng ngày.
- Kiểm tra ngày đúng định dạng `Y-m-d` và `date_from <= date_to`.
- Eager load user và `withCount('items')` để tránh N+1.
- Giao diện hiển thị mã đơn, người nhận, loại khách, thời gian, số dòng hàng, tổng tiền, thanh toán và trạng thái.
- Khách có tài khoản hiển thị email; đơn `user_id = NULL` được ghi rõ **Khách vãng lai**.
- Trạng thái rỗng, responsive table, nhãn màu và giữ query string khi phân trang đã hoàn tất.
- Route tiếp tục được bảo vệ bởi `orders.view`; sidebar nhận diện cả route con trong tương lai.
- File chính: `AdminOrderController.php`, `AdminOrderIndexRequest.php`, `admin/orders/index.blade.php`, `routes/web.php`, `AdminOrderTest.php`.
- Kiểm thử: `83 passed (796 assertions)`; riêng danh sách đơn có 4 test và 21 assertions.

### [x] Task 2.4 — Chi tiết đơn hàng

- [x] Hiển thị người nhận, điện thoại và địa chỉ giao hàng.
- [x] Hiển thị sản phẩm snapshot, SKU, giá, số lượng và thành tiền.
- [x] Hiển thị tạm tính, phí giao hàng và tổng tiền.
- [x] Hiển thị trạng thái/phương thức thanh toán.
- [x] Liên kết hồ sơ khách hàng khi có `user_id`.
- [x] Hiển thị lịch sử trạng thái.
- [x] Không phụ thuộc dữ liệu hiện tại của bảng `products`.

**Kết quả cần đạt:** admin có đủ thông tin để xử lý một đơn hàng.

#### Kết quả triển khai

- Thêm route `admin.orders.show` dùng implicit route model binding `{order}` và quyền `orders.view`.
- Danh sách đơn có nút **Xem** dẫn tới trang chi tiết.
- Trang chi tiết hiển thị snapshot người nhận, điện thoại, địa chỉ và ghi chú tại thời điểm đặt hàng.
- Nếu `user_id` là khách hàng hợp lệ, hiển thị email và liên kết tới `admin.customers.show`; khách vãng lai được ghi rõ, tài khoản admin không được liên kết vào module khách hàng.
- Bảng sản phẩm dùng hoàn toàn `order_items.product_name`, `sku`, `price`, `quantity`, `total_price`.
- Nếu sản phẩm gốc đã bị xóa (`product_id = NULL`), snapshot vẫn hiển thị và có chú thích rõ ràng.
- Hiển thị tạm tính, phí giao hàng, tổng cộng, phương thức và trạng thái thanh toán.
- Hiển thị các mốc xác nhận, xử lý, vận chuyển, giao thành công, hủy và lý do hủy.
- Timeline lịch sử hiển thị chuyển trạng thái, thời gian, người thực hiện/hệ thống và ghi chú.
- Eager load `user`, `items`, `statusHistories.changedBy` để tránh N+1.
- Test bao phủ link từ danh sách, snapshot sau khi xóa sản phẩm, tổng tiền, khách đăng nhập/vãng lai, timeline, quyền và 404.
- File chính: `AdminOrderController.php`, `admin/orders/index.blade.php`, `admin/orders/show.blade.php`, `routes/web.php`, `AdminOrderTest.php`.
- Kiểm thử: `86 passed (813 assertions)`; riêng module danh sách/chi tiết đơn có 7 test và 38 assertions.

### [x] Task 2.5 — Quy trình cập nhật trạng thái

Luồng dự kiến:

```text
pending → confirmed → processing → shipped → delivered
    └──────────────────────────────────────→ cancelled
```

- [x] Chặn chuyển trạng thái ngược hoặc không hợp lệ.
- [x] Ghi lịch sử mỗi lần thay đổi.
- [x] Lưu người thực hiện và thời gian.
- [x] Yêu cầu lý do khi hủy nếu nghiệp vụ chốt như vậy.
- [x] Phân quyền `orders.update` và `orders.cancel`.

**Kết quả cần đạt:** trạng thái nhất quán và có thể truy vết.

#### Kết quả triển khai

- Thêm `OrderStatusService` làm nguồn duy nhất thực hiện chuyển trạng thái trong transaction.
- Service dùng `lockForUpdate()` để tuần tự hóa cập nhật đồng thời trên cùng đơn hàng.
- Kiểm tra `Order::canTransitionTo()` sau khi khóa bản ghi; chuyển nhảy bước, chuyển ngược và gửi lặp đều bị từ chối.
- Mỗi lần thành công cập nhật đúng cột thời gian nghiệp vụ và tạo `order_status_histories` với trạng thái cũ/mới, actor, thời gian, ghi chú.
- Hủy đơn bắt buộc `cancel_reason`; lý do được lưu ở cả đơn hàng và timeline khi không có ghi chú riêng.
- `UpdateOrderStatusRequest` validate trạng thái, ghi chú và lý do hủy tối đa 1000 ký tự.
- Route `PATCH admin/orders/{order}/status` yêu cầu ít nhất một trong `orders.update|orders.cancel`.
- Controller kiểm tra riêng: trạng thái thường cần `orders.update`, đích `cancelled` cần `orders.cancel`; có một quyền không thể sử dụng quyền còn lại.
- Trang chi tiết chỉ hiển thị các bước hợp lệ theo trạng thái hiện tại và quyền user; trạng thái kết thúc không có form cập nhật.
- Form tự hiện/ẩn và bắt buộc lý do khi chọn hủy, đồng thời khóa nút khi submit để hạn chế gửi lặp từ UI.
- Task này chưa trừ/hoàn tồn kho; phần đó được giữ riêng cho Task 2.6.
- File chính: `OrderStatusService.php`, `UpdateOrderStatusRequest.php`, `AdminOrderController.php`, `admin/orders/show.blade.php`, `routes/web.php`, `OrderStatusTransitionTest.php`.
- Kiểm thử: `91 passed (853 assertions)`; riêng state machine có 5 test và 40 assertions.

### [x] Task 2.6 — Tồn kho và hủy đơn

- [x] Chốt thời điểm trừ kho.
- [x] Dùng transaction và khóa bản ghi sản phẩm.
- [x] Không cho tồn kho âm.
- [x] Hoàn kho đúng một lần khi hủy.
- [x] Không hoàn kho nếu đơn chưa từng trừ kho.
- [x] Xử lý an toàn khi `order_items.product_id` là `NULL`.
- [x] Chống gửi yêu cầu cập nhật lặp lại.

**Kết quả cần đạt:** tồn kho không sai lệch khi xử lý hoặc hủy đơn.

#### Kết quả triển khai

- Chốt thời điểm trừ kho khi đơn lần đầu đi vào workflow đã xác nhận (`confirmed`).
- Với đơn cũ/seed đang ở bước sau nhưng `stock_deducted_at = NULL`, lần chuyển tiếp kế tiếp sẽ tự trừ kho trước khi đổi trạng thái.
- Thêm `OrderInventoryService`, được gọi bên trong cùng transaction và lock của `OrderStatusService`.
- Các dòng trùng `product_id` được gộp số lượng trước khi kiểm tra/trừ/hoàn.
- Sản phẩm được khóa `lockForUpdate()` theo thứ tự ID ổn định để hạn chế race condition và deadlock.
- Kiểm tra đủ tồn kho cho toàn bộ sản phẩm trước khi thay đổi bất kỳ số lượng nào; nếu một sản phẩm thiếu, transaction rollback kho, trạng thái, timestamp và history.
- Xác nhận đơn rỗng hoặc có `product_id = NULL` bị từ chối vì không thể đảm bảo tồn kho.
- Khi hủy, chỉ hoàn nếu đã có `stock_deducted_at` và chưa có `stock_restored_at`.
- Hủy `pending` không hoàn kho; gửi hủy lặp bị state machine từ chối và không cộng kho lần hai.
- Nếu sản phẩm bị xóa sau khi đã trừ kho, dòng `product_id = NULL` được bỏ qua an toàn khi hoàn; `stock_restored_at` vẫn được ghi để tránh xử lý lặp.
- Trang chi tiết hiển thị thời điểm trừ và hoàn tồn kho.
- Không cần migration mới vì marker tồn kho đã được tạo ở Task 2.1.
- File chính: `OrderInventoryService.php`, `OrderStatusService.php`, `admin/orders/show.blade.php`, `OrderInventoryTest.php`, `OrderStatusTransitionTest.php`.
- Kiểm thử: `97 passed (885 assertions)`; riêng tồn kho có 6 test, nhóm tồn kho + state machine có 11 test và 72 assertions.

### [x] Task 2.7 — In hóa đơn

- [x] Tạo layout tối ưu cho in, không có menu admin.
- [x] Dùng dữ liệu snapshot của đơn hàng.
- [x] Bảo vệ bằng `orders.view`.
- [x] Thêm nút in từ trang chi tiết.
- [x] Để PDF thành phần nâng cấp sau nếu chưa cần.

**Kết quả cần đạt:** in hóa đơn trực tiếp từ trình duyệt.

#### Kết quả triển khai

- Thêm route `admin.orders.print` và action `printInvoice`, dùng implicit binding và quyền `orders.view`.
- Trang chi tiết có nút **In hóa đơn**, mở bản in ở tab mới với `noopener`.
- Tạo Blade standalone, không kế thừa admin layout nên không tải sidebar/header/dashboard scripts.
- CSS in A4 có `@page`, nền trắng, loại bỏ shadow và ẩn toàn bộ nút điều khiển bằng `.no-print`.
- Bản in hiển thị mã/ngày đơn, người nhận, địa chỉ, email nếu có, trạng thái, thanh toán và loại khách.
- Sản phẩm lấy từ snapshot `order_items`; sản phẩm gốc đã xóa vẫn hiển thị tên, SKU, giá, số lượng và thành tiền.
- Hiển thị tạm tính, phí giao hàng, tổng cộng, ghi chú và thời gian in theo timezone ứng dụng.
- Có nút quay lại đơn hàng và `window.print()`; chưa thêm thư viện PDF để giữ phạm vi gọn và không phát sinh dependency.
- Responsive cho màn hình nhỏ, nhưng ưu tiên khổ A4 khi in.
- Test bao phủ link từ chi tiết, dữ liệu snapshot, tổng tiền, không có admin layout, quyền và 404.
- File chính: `AdminOrderController.php`, `admin/orders/show.blade.php`, `admin/orders/print.blade.php`, `routes/web.php`, `AdminOrderTest.php`.
- Kiểm thử: `99 passed (898 assertions)`; riêng module danh sách/chi tiết/in đơn có 9 test và 51 assertions.

### [x] Task 2.8 — Kiểm thử module đơn hàng

- [x] Test danh sách, bộ lọc, tìm kiếm và phân trang.
- [x] Test chi tiết khách hàng và khách vãng lai.
- [x] Test chuyển trạng thái hợp lệ/không hợp lệ.
- [x] Test phân quyền cập nhật và hủy.
- [x] Test lịch sử trạng thái.
- [x] Test trừ/hoàn tồn kho.
- [x] Test rollback khi thiếu tồn kho.
- [x] Test chống hoàn kho hai lần.
- [x] Test trang in hóa đơn.
- [x] Chạy toàn bộ test suite.

**Kết quả cần đạt:** module đơn hàng đủ an toàn để sử dụng thực tế.

#### Kết quả audit và kiểm thử

- `AdminOrderTest`: phân trang, giữ query, tìm kiếm 4 trường, lọc kết hợp, ngày, khách có tài khoản/vãng lai, chi tiết snapshot, quyền, 404 và hóa đơn.
- `OrderStatusTransitionTest`: chuyển hợp lệ, nhảy bước, gửi lặp, full workflow, trạng thái kết thúc, lý do hủy và tách quyền update/cancel.
- `OrderInventoryTest`: gộp dòng hàng, trừ kho, rollback thiếu kho, hoàn đúng một lần, hủy pending, đơn cũ thiếu marker, sản phẩm đã xóa, đơn rỗng và quantity không dương.
- `OrderWorkflowSchemaTest`: constants, quan hệ lịch sử, FK cascade/set-null và migration bảo toàn/backfill dữ liệu cũ.
- `OrderSeederTest`: khách đăng nhập/vãng lai, snapshot, tổng tiền, timeline, idempotent và không đổi tồn kho.
- Thêm `OrderModuleIntegrationTest` chạy xuyên suốt: tìm danh sách → chi tiết → xác nhận/trừ kho → xử lý → hủy/hoàn kho → timeline → hóa đơn.
- Bổ sung validation trong `OrderInventoryService` để đơn rỗng hoặc item có `quantity <= 0` không thể được xác nhận.
- Xác nhận migration `2026_10_29_000010_add_workflow_fields_to_orders_table` đã `Ran` trên MySQL hiện tại.
- Module đơn hàng có 28 test chuyên biệt, bao phủ cả từng lớp nghiệp vụ và luồng tích hợp xuyên suốt.
- Toàn bộ hệ thống: `101 passed (927 assertions)`.
- Không còn task bắt buộc nào của Giai đoạn 2; PDF vẫn là nâng cấp tùy chọn ngoài phạm vi đã chốt.

## Giai đoạn 3 — Nâng cấp khách hàng

### [x] Task 3.1 — Thống kê khách hàng

- [x] Tổng số đơn.
- [x] Số đơn giao thành công và bị hủy.
- [x] Tổng chi tiêu từ đơn `delivered`.
- [x] Giá trị đơn trung bình.
- [x] Ngày mua gần nhất.

**Kết quả triển khai:**

- Trang chi tiết khách hàng hiển thị 6 KPI: tổng số đơn, số đơn giao thành công, số đơn bị hủy, tổng chi tiêu, giá trị đơn trung bình và lần mua gần nhất.
- Tổng chi tiêu và giá trị trung bình chỉ tính `total_amount` của đơn `delivered`; đơn `pending`, đang xử lý và `cancelled` không làm tăng doanh thu.
- Lần mua gần nhất lấy thời gian tạo của đơn mới nhất không bị hủy; khách chưa có đơn hiển thị trạng thái rỗng an toàn.
- Backend dùng một aggregate query với `CASE WHEN`, không tải toàn bộ collection đơn hàng vào bộ nhớ.
- File chính: `AdminCustomerController.php`, `admin/customers/show.blade.php` và `AdminCustomerTest.php`.
- Kiểm thử module khách hàng: `11 passed (47 assertions)`.
- Toàn bộ regression suite: `102 passed (934 assertions)`.

### [x] Task 3.2 — Lịch sử mua hàng

- [x] Danh sách đơn trong trang khách hàng.
- [x] Phân trang độc lập.
- [x] Liên kết tới chi tiết đơn.
- [x] Lọc theo trạng thái.

**Kết quả triển khai:**

- Hồ sơ khách hàng hiển thị lịch sử đơn mới nhất trước, 10 đơn mỗi trang.
- Paginator dùng page name riêng `orders_page`, không xung đột với phân trang khác và giữ bộ lọc trong query string.
- Bộ lọc `order_status` được chuẩn hóa và validate bằng `AdminCustomerShowRequest`; trạng thái ngoài `Order::STATUSES` bị từ chối.
- Bảng hiển thị mã đơn, ngày đặt, người nhận, tổng tiền, thanh toán và trạng thái; nút **Xem đơn** chỉ hiện khi có `orders.view`.
- Trạng thái rỗng phân biệt trường hợp chưa có đơn hoặc không có kết quả phù hợp.
- File chính: `AdminCustomerShowRequest.php`, `AdminCustomerController.php`, `admin/customers/show.blade.php` và `AdminCustomerTest.php`.
- Kiểm thử module khách hàng: `13 passed (58 assertions)`.
- Toàn bộ regression suite: `104 passed (945 assertions)`.

### [x] Task 3.3 — Phân nhóm khách hàng

- [x] Khách mới.
- [x] Khách quay lại.
- [x] Khách thân thiết.
- [x] Khách có nhiều đơn bị hủy.
- [x] Ưu tiên tính toán động trước khi tạo schema cố định.

**Kết quả triển khai và quy tắc phân nhóm:**

- `Khách mới`: chưa có hoặc mới có một đơn `delivered`.
- `Khách quay lại`: có từ hai đơn `delivered` nhưng chưa đạt ngưỡng thân thiết.
- `Khách thân thiết`: có ít nhất năm đơn `delivered` hoặc tổng chi tiêu delivered từ 5.000.000 đồng.
- `Nhiều đơn bị hủy`: có ít nhất ba đơn `cancelled` và tỷ lệ hủy từ 50%; nhóm này được ưu tiên trước các nhóm doanh thu.
- Quy tắc và ngưỡng được tập trung trong `User::customerSegment()` cùng các hằng số; kết quả được tính từ aggregate hiện tại, không thêm cột/migration.
- Danh sách khách hàng dùng `withCount`/`withSum` để tránh N+1 và hiển thị nhãn nhóm; hồ sơ khách hàng tái sử dụng thống kê Task 3.1.
- File chính: `User.php`, `AdminCustomerController.php`, `admin/customers/index.blade.php`, `admin/customers/show.blade.php` và `AdminCustomerTest.php`.
- Kiểm thử module khách hàng: `15 passed (68 assertions)`.
- Toàn bộ regression suite: `106 passed (955 assertions)`.

### [x] Task 3.4 — Kiểm thử tích hợp khách hàng–đơn hàng

- [x] Thống kê chỉ tính đúng trạng thái.
- [x] Đơn khách vãng lai không gán nhầm tài khoản.
- [x] Xóa tài khoản không làm mất lịch sử đơn.
- [x] Snapshot đơn không đổi khi hồ sơ khách hàng thay đổi.
- [x] Chạy toàn bộ test suite.

**Kết quả kiểm thử tích hợp:**

- Tạo `CustomerOrderIntegrationTest` gồm bốn luồng xuyên suốt giữa hồ sơ khách hàng và đơn hàng.
- Xác nhận doanh thu/AOV chỉ tính `delivered`, số đơn hủy chỉ tính `cancelled`, và lần mua gần nhất loại đơn hủy.
- Xác nhận đơn vãng lai có `user_id = NULL` không xuất hiện trong thống kê hoặc lịch sử của khách đã đăng ký.
- Xác nhận xóa customer làm `orders.user_id = NULL` nhưng vẫn giữ nguyên đơn và snapshot người nhận.
- Xác nhận thay đổi tên, email, điện thoại của tài khoản không làm đổi `recipient_name`, `recipient_phone` và `shipping_address` đã chốt trên đơn.
- Test tích hợp riêng: `4 passed (17 assertions)`.
- Toàn bộ regression suite: `110 passed (972 assertions)`.
- Không cần migration hoặc thay đổi schema.

## Thứ tự triển khai

```text
1.1 → 1.2 → 1.3 → 1.4 → 1.5
→ 2.1 → 2.2 → 2.3 → 2.4
→ 2.5 → 2.6 → 2.7 → 2.8
→ 3.1 → 3.2 → 3.3 → 3.4
```

## Nhật ký cập nhật

| Thời gian | Task | Trạng thái | Thay đổi chính | Kiểm thử |
|---|---|---|---|---|
| 29/10/2026 | Lập roadmap | Hoàn thành | Tạo kế hoạch và checklist triển khai | Không áp dụng |
| 29/10/2026 | 1.1 | Hoàn thành | Rà soát schema/model/role/middleware; chốt định nghĩa khách hàng, query, trạng thái và quy tắc bảo mật | Rà soát tĩnh, không thay đổi mã nguồn thực thi |
| 29/10/2026 | 1.2 | Hoàn thành | Tạo danh sách khách hàng, phân trang, tìm kiếm, lọc trạng thái và loại tài khoản quản trị | 66 test đạt, 365 assertions |
| 29/10/2026 | 1.3 | Hoàn thành | Tạo hồ sơ chi tiết, route model binding, thống kê đơn cơ bản và bảo vệ admin bằng 404 | 69 test đạt, 375 assertions |
| 29/10/2026 | 1.4 | Hoàn thành | Thêm khóa/mở khách hàng, validation, phân quyền và bảo vệ tài khoản quản trị | 72 test đạt, 393 assertions |
| 29/10/2026 | 1.5 | Hoàn thành | Tạo 45 khách hàng demo idempotent và hoàn tất kiểm thử hồi quy Giai đoạn 1 | 73 test đạt, 404 assertions |
| 29/10/2026 | 2.1 | Hoàn thành | Bổ sung workflow, lịch sử trạng thái, mốc nghiệp vụ, dấu vết tồn kho và backfill đơn cũ | 77 test đạt, 422 assertions |
| 29/10/2026 | 2.2 | Hoàn thành | Tạo 48 đơn demo có snapshot, timeline, khách đăng nhập/vãng lai và không đổi tồn kho | 79 test đạt, 775 assertions |
| 29/10/2026 | 2.3 | Hoàn thành | Tạo danh sách đơn, phân trang, tìm kiếm, bộ lọc kết hợp và phân biệt khách vãng lai | 83 test đạt, 796 assertions |
| 29/10/2026 | 2.4 | Hoàn thành | Tạo chi tiết đơn dùng snapshot, liên kết khách hàng, thanh toán, mốc workflow và timeline | 86 test đạt, 813 assertions |
| 29/10/2026 | 2.5 | Hoàn thành | Tạo state machine có transaction/lock, audit history, lý do hủy và tách quyền update/cancel | 91 test đạt, 853 assertions |
| 29/10/2026 | 2.5 UI | Hoàn thành | Cân đối lại panel cập nhật trạng thái, responsive grid, trạng thái hiện tại và vùng cảnh báo hủy | 91 test đạt, 853 assertions |
| 29/10/2026 | 2.6 | Hoàn thành | Trừ kho khi xác nhận, rollback khi thiếu, hoàn đúng một lần khi hủy và xử lý sản phẩm đã xóa | 97 test đạt, 885 assertions |
| 29/10/2026 | 2.7 | Hoàn thành | Tạo hóa đơn A4 standalone từ snapshot, nút in trình duyệt và bảo vệ bằng orders.view | 99 test đạt, 898 assertions |
| 29/10/2026 | 2.8 | Hoàn thành | Audit ma trận test, thêm validation item biên và test tích hợp xuyên suốt toàn module đơn hàng | 101 test đạt, 927 assertions |
| 29/10/2026 | 3.1 | Hoàn thành | Thêm 6 KPI khách hàng bằng aggregate query; doanh thu/AOV chỉ tính đơn delivered và lần mua gần nhất loại đơn hủy | 102 test đạt, 934 assertions |
| 29/10/2026 | 3.2 | Hoàn thành | Thêm lịch sử mua hàng có paginator orders_page, lọc trạng thái được validate và liên kết chi tiết theo quyền orders.view | 104 test đạt, 945 assertions |
| 29/10/2026 | 3.3 | Hoàn thành | Phân nhóm động khách mới, quay lại, thân thiết và rủi ro hủy bằng aggregate; không thêm schema | 106 test đạt, 955 assertions |
| 29/10/2026 | 3.4 | Hoàn thành | Thêm test tích hợp thống kê trạng thái, guest order, xóa customer giữ đơn và snapshot bất biến | 110 test đạt, 972 assertions |

## Mẫu cập nhật sau mỗi task

Khi hoàn thành một task:

1. Đổi tiêu đề task từ `[ ]` hoặc `[-]` thành `[x]`.
2. Đánh dấu từng tiêu chí đã hoàn thành.
3. Thêm một dòng vào bảng **Nhật ký cập nhật**.
4. Ghi rõ file chính đã thay đổi và kết quả test.
5. Nếu thay đổi quyết định nghiệp vụ, ghi thêm lý do ngay dưới task tương ứng.
