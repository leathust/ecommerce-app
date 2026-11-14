# Vận hành Banner trang chủ

## Phạm vi

`home_banners` quản lý hai vị trí độc lập:

- `hero`: tối đa 6 banner trên khối đầu trang.
- `discount`: tối đa 2 banner quảng bá bên dưới Trending.

Khi một vị trí chưa có banner đang hiệu lực, storefront tiếp tục dùng dữ liệu catalog cũ làm fallback. Vì vậy không cần backfill và không tạo nội dung marketing giả khi triển khai.

## Triển khai

```powershell
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan optimize:clear
```

Seeder permission là idempotent. Role mặc định chưa bị tùy chỉnh sẽ nhận nhóm quyền `banners.*`; role tùy chỉnh không bị ghi đè.

## Quy tắc xuất bản

- `status = 1`.
- `starts_at` rỗng hoặc nhỏ hơn/bằng thời điểm hiện tại.
- `ends_at` rỗng hoặc lớn hơn thời điểm hiện tại; đúng thời điểm kết thúc banner hết hiệu lực.
- Thứ tự tăng dần theo `sort_order`, sau đó theo `id`.
- Nội dung ưu tiên locale hiện tại và fallback sang tiếng Việt rồi tiếng Anh.
- Liên kết chỉ nhận `shop`, filter category/brand hoặc `products/slug`; không nhận domain ngoài hay JavaScript URL.

Mọi create/update/delete đều ghi `admin_audit_logs` và làm mới cache trang chủ. Thay đổi Product, Category, Brand, ProductImage hoặc HomeBanner cũng invalid cache tự động.

## Rollback

```powershell
php artisan migrate:rollback --step=1
```

Rollback xóa bảng banner nhưng storefront vẫn dùng fallback catalog. Chỉ rollback khi đã sao lưu dữ liệu banner cần giữ.
