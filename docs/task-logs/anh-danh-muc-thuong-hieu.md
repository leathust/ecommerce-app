# Ảnh Danh mục và Thương hiệu

Trong Admin → Danh mục / Thương hiệu → Thêm hoặc Sửa:

- Chọn một ảnh JPEG, PNG hoặc WebP, tối đa 2 MB, kích thước tối đa 10.000 × 10.000 pixel.
- Có xem trước ảnh mới và thumbnail trong danh sách.
- Nhấn Lưu để áp dụng. Nếu form lỗi, chọn lại file upload.
- Để thay ảnh, chọn file mới. Hệ thống dọn file upload cũ sau khi lưu database thành công.
- Để xóa ảnh, chọn Xóa ảnh hiện tại, bỏ trống ô URL và không chọn file mới, rồi lưu.
- Vẫn hỗ trợ URL HTTP/HTTPS. Thứ tự ưu tiên khi lưu: file mới → URL đã nhập → xóa nếu được chọn → giữ ảnh hiện tại. File từ URL bên ngoài không bị xóa trên server bên ngoài.

File lưu trong `storage/app/catalog-images/categories` hoặc `storage/app/catalog-images/brands`, đường dẫn tương đối lưu ở cột `image_url` có sẵn. Không cần migration hoặc `storage:link`. Route `/media/catalog/{type}/{id}` phục vụ file; ảnh của bản ghi ẩn chỉ cho admin active có quyền xem/sửa tương ứng.

Khi xóa bản ghi thành công, file upload được dọn. Danh mục còn sản phẩm bị chặn xóa thì ảnh vẫn được giữ. Lỗi dọn file được ghi log; không xóa các đường dẫn ngoài định dạng uploader quản lý.

Cấu hình PHP cần `file_uploads=On`, `upload_max_filesize` ít nhất `2M`, `post_max_size` lớn hơn tổng request và quyền ghi thư mục storage. Khởi động lại Apache khi sửa php.ini.

```powershell
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan test --filter=CatalogImageUploadTest
```

Tests dùng SQLite in-memory và storage fake, không thay đổi dữ liệu MySQL hay ảnh thật.
