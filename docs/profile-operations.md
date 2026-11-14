# Vận hành hồ sơ khách hàng storefront

## Contract

- Trang canonical: `GET /account` (`account`), chỉ customer active.
- Cập nhật cá nhân: `PATCH /account/profile` (`storefront.profile.update`). Chỉ nhận `first_name`, `last_name`, `phone`; `name` do server đồng bộ.
- Đổi mật khẩu: `PUT /account/password` (`storefront.password.update`). Yêu cầu mật khẩu hiện tại, confirmation và mật khẩu mạnh khác mật khẩu cũ.
- Đăng xuất: `POST /logout`. Tất cả mutation dùng CSRF, `customer` middleware và `profile-mutations` throttle.
- HTML redirect/error bag là fallback; AJAX chỉ là progressive enhancement và không lưu/broadcast PII hoặc credential.

## Bảo mật và dữ liệu

- Không cho storefront sửa email, username, status, role, permission hoặc metadata đăng nhập.
- Không log, flash, đưa vào JSON/DOM storage mật khẩu hiện tại hoặc mật khẩu mới.
- Đổi password regenerate session ID và CSRF token, giữ phiên hiện tại đăng nhập.
- Không seed hồ sơ cá nhân, password hoặc Cart/Wishlist customer vào production. `customerProfile()` và `inactiveCustomer()` chỉ phục vụ test/demo có chủ đích.

## Kiểm tra và xử lý sự cố

```powershell
php artisan route:list --path=account -v
php artisan test --filter="StorefrontProfile|StorefrontPassword|StorefrontAuthentication"
php artisan view:clear
php artisan cache:clear
```

- `403`: session thuộc admin, không phải customer.
- Bị chuyển về login: guest hoặc tài khoản inactive/blocked.
- `422`: kiểm tra error bag/JSON `errors`; password tối thiểu 12 ký tự có hoa, thường, số, ký hiệu.
- `429`: chờ hết cửa sổ throttle; không tăng giới hạn để che lỗi gửi lặp ở frontend.
- AJAX lỗi vẫn có thể tắt JavaScript và submit native form.

## Ngoài phạm vi

Đổi/xác minh email, avatar, address book, xóa tài khoản, export dữ liệu, 2FA/passkey, social account management và thu hồi mọi phiên/thiết bị cần roadmap riêng.
