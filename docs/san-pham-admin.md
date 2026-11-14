# Quản lý sản phẩm trong admin

Mở `http://localhost/ecommerce-app/admin/products` sau khi đăng nhập.

- Danh sách tìm theo tên tiếng Việt, tiếng Anh hoặc SKU, phân trang 15 bản ghi.
- Thêm/sửa gồm tên song ngữ, SKU, slug, danh mục, thương hiệu, mô tả, giá, tồn kho, trạng thái hiển thị và nổi bật.
- Slug để trống sẽ được tạo từ tên tiếng Việt. Slug và SKU phải duy nhất.
- Danh mục bắt buộc phải tồn tại trong bảng `categories`; thương hiệu không bắt buộc. Danh mục và thương hiệu có module CRUD riêng.
- Upload tối đa 10 ảnh mới mỗi lần lưu, định dạng JPEG/PNG/WebP, tối đa 2 MB/ảnh và kích thước tối đa 10.000 × 10.000 pixel. Chọn ảnh đại diện; nhập mô tả ảnh mới, chỉnh mô tả/thứ tự và đánh dấu xóa ảnh hiện có trong form sửa.
- Nhấn Lưu sản phẩm mới áp dụng thay đổi ảnh. Lỗi validation yêu cầu chọn lại file upload. Nếu chưa có ảnh đại diện hoặc xóa ảnh đại diện, hệ thống tự chọn ảnh còn lại.
- File lưu tại `storage/app/product-images/{product_id}` qua disk `product_images`; database lưu đường dẫn tương đối trong `product_images.image_url`. URL hiển thị qua `/media/product-images/{image}`; không cần `storage:link`, không mở chặn `/storage` trong .htaccess. Ảnh sản phẩm hiển thị công khai khi sản phẩm đang bật; sản phẩm ẩn yêu cầu tài khoản admin active có quyền xem/sửa sản phẩm.
- Xóa ảnh hoặc xóa sản phẩm thành công sẽ dọn file do uploader này quản lý. Nếu xóa sản phẩm bị khóa ngoại chặn, file vẫn được giữ. File cũ có image_url ngoài định dạng uploader không bị xóa tự động. Khi lỗi xóa file filesystem, lỗi được ghi log để xử lý file thừa.
- Xóa có xác nhận. Nếu sản phẩm còn được tham chiếu bởi giỏ hàng, hệ thống báo lỗi; có thể sửa trạng thái thành ẩn thay thế.
- Khi xóa thành công, khóa ngoại xóa ảnh/liên kết yêu thích, đặt product_id của order_items về NULL và giữ tên/giá snapshot của đơn hàng.

Quyền theo thao tác: `products.view`, `products.create`, `products.update`, `products.delete`. Role catalog_manager/admin đã được cấp trong seeder; super_admin được toàn quyền.

Không cần migration mới. Nếu database chưa có các permission đã định nghĩa, kiểm tra ma trận trong `RolePermissionSeeder` trước khi chạy lại vì seeder đồng bộ lại quyền của role mẫu.

Kiểm tra:

```powershell
php artisan route:clear
php artisan view:clear
php artisan test --filter=AdminProductTest
php artisan test --filter=ProductImageUploadTest
```

Test tạo dữ liệu trong SQLite in-memory, không thay đổi dữ liệu MySQL. Tạo danh mục trên database development trước khi thử thêm sản phẩm; không chèn ID danh mục giả.

PHP/XAMPP phải cho phép upload: `file_uploads=On`, `upload_max_filesize` ít nhất `2M`, `post_max_size` đủ cho toàn bộ form (ví dụ `32M` cho 10 ảnh) và `max_file_uploads` ít nhất 10. Nếu đổi php.ini, khởi động lại Apache. Thư mục storage cần có quyền ghi. File upload được đặt tên ngẫu nhiên, không dùng tên gốc làm đường dẫn.

Sau khi cập nhật code, chạy `php artisan config:clear`, `php artisan route:clear` và `php artisan view:clear`. Không cần migration mới. Mở thêm/sửa sản phẩm, chọn ảnh, xem preview, chọn ảnh đại diện rồi lưu; vào sửa lại để đổi thứ tự/mô tả hoặc đánh dấu xóa từng ảnh và lưu.
