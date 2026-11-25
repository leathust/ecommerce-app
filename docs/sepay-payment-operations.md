# SePay payment operations

## Payment email worker

Payment confirmation email chạy độc lập trên Redis queue `emails`:

```powershell
php artisan queue:work redis --queue=emails --tries=3 --backoff=10,60,300
```

Worker phải được khởi động lại sau deploy:

```powershell
php artisan queue:restart
```

Scheduler phải chạy liên tục để health check và automatic retry hoạt động:

```powershell
php artisan schedule:work
```

Không gộp worker email với queue `payments` nếu cần cô lập lỗi SMTP. Webhook và trạng thái payment đã xác nhận không phụ thuộc việc email gửi thành công.

## Failed email và retry

Kiểm tra failed jobs của Laravel:

```powershell
php artisan queue:failed
```

Kiểm tra ngưỡng failed payment email:

```powershell
php artisan payments:email-health --threshold=10
```

Retry một payment email đã failed bằng public ID của payment attempt:

```powershell
php artisan payments:retry-email <payment-public-id>
```

Hệ thống cũng tự retry failed delivery mỗi 5 phút:

```powershell
php artisan payments:retry-failed-emails --limit=100
```

Mỗi lần job thử tối đa 3 lần nhanh; tổng số lần thử mặc định tối đa 6 lần (`SEPAY_EMAIL_MAX_ATTEMPTS`). Delivery failed chỉ được tự đưa lại queue sau khoảng nghỉ cấu hình (`SEPAY_EMAIL_AUTO_RETRY_AFTER_MINUTES`, mặc định 5 phút). Lệnh retry thủ công chỉ nhận payment email ở trạng thái `failed`, đưa lại job vào queue `emails` và ghi operational audit vào log. Không ghi email người nhận, credential, token hoặc raw SePay payload vào log.

## Mail transport

SMTP/mail transport chỉ cấu hình qua biến môi trường `MAIL_*`. Không đưa mật khẩu SMTP, API key hoặc secret vào source code, dashboard hay log. Có thể dùng mailer `log`/Mailpit trong local để kiểm tra template mà không gửi email thật.

## Guest và customer

- Guest chọn SePay phải nhập email hợp lệ; email được snapshot vào order.
- Customer dùng email tài khoản do server resolve và snapshot tại checkout.
- Guest COD không bắt buộc email.
- Email chỉ sử dụng order snapshot và không chứa raw webhook payload hoặc credential SePay.

## Sandbox/live rollout checklist

Task 14.11 chỉ được coi là đạt sau khi chạy sandbox E2E và lưu bằng chứng ở reconciliation run, webhook receipt, queue log đã redact và SePay dashboard history. Không dùng token, HMAC secret, số tài khoản đầy đủ hoặc payload raw trong issue, screenshot, dashboard hay log.

### 1. Tách profile sandbox và live

Mỗi deployment chỉ chọn một environment bằng `SEPAY_MODE`; không cho browser hoặc admin truyền environment. Sandbox và live phải có token API, webhook credential, bank code, account number và dashboard riêng:

```dotenv
SEPAY_MODE=sandbox
SEPAY_ENABLED=true
SEPAY_RECONCILIATION_ENABLED=true

SEPAY_SANDBOX_API_TOKEN=<sandbox-api-token>
SEPAY_SANDBOX_API_BASE_URL=https://userapi-sandbox.sepay.vn
SEPAY_SANDBOX_API_TRANSACTIONS_PATH=v2/transactions
SEPAY_SANDBOX_WEBHOOK_ENABLED=true
SEPAY_SANDBOX_WEBHOOK_AUTH=hmac
SEPAY_SANDBOX_WEBHOOK_SECRET=<sandbox-webhook-secret>
SEPAY_SANDBOX_BANK_CODE=<sandbox-bank-code>
SEPAY_SANDBOX_ACCOUNT_NUMBER=<sandbox-account-number>
SEPAY_SANDBOX_ACCOUNT_NAME=<sandbox-account-name>
```

Trước mỗi lần đổi profile:

```powershell
php artisan config:clear
php artisan optimize:clear
php artisan sepay:check --connect
php artisan route:list | Select-String "webhooks/sepay|admin/payments"
```

`SEPAY_ENABLED=false` là kill switch cho checkout mới. Webhook endpoint, `sepay:reconcile`, expiry và xử lý attempt đang tồn tại vẫn phải được phép tiếp tục; vì vậy không tắt `SEPAY_RECONCILIATION_ENABLED` khi đang recovery.

### 2. Sandbox E2E bắt buộc

Chạy theo thứ tự sau, dùng giao dịch mô phỏng/Test mode của SePay:

1. Tạo order SePay guest và customer; ghi lại `order_code`, `payment_code`, amount và public payment URL, không ghi access token.
2. Gửi giao dịch sandbox đúng account, đúng amount và đúng payment code. Kiểm tra webhook trả `{ "success": true }`, receipt chuyển `queued`, worker `payments` xử lý và order chuyển `paid`.
3. Gửi lại cùng event để kiểm tra replay/idempotency: receipt không tăng, transaction không nhân đôi, event/payment history chỉ phát một lần.
4. Gửi body đã sửa, timestamp hết hạn, thiếu chữ ký hoặc sai environment: request bị từ chối, không tạo receipt mới.
5. Tạo giao dịch không có code, sai code, thiếu/thừa amount, sai account và outgoing: không auto-paid; transaction vào `manual_review` hoặc `ignored_outgoing`, admin thấy trong `/admin/payments/reconciliation`.
6. Tạo một order rồi cố ý không cho webhook đến ứng dụng. Sau khi giao dịch xuất hiện trong SePay API, chạy đối soát:

```powershell
$from = (Get-Date).AddMinutes(-30).ToString('yyyy-MM-dd HH:mm:ss')
$to = (Get-Date).AddMinutes(5).ToString('yyyy-MM-dd HH:mm:ss')
php artisan sepay:reconcile --environment=sandbox --from="$from" --to="$to" --sync
```

   Kết quả phải có run `completed`, `fetched_count` tăng, transaction gắn `reconciliation_run_id`, matcher xác nhận order và không tạo bản ghi trùng khi chạy lại cùng khoảng thời gian.
7. Chạy command không có `--sync`, xác nhận run nằm trên queue `payments`, restart worker giữa chừng rồi kiểm tra job tiếp tục/resume. Với run lỗi, dùng `--run-id=<public-id> --sync` sau khi khôi phục API.

### 3. Smoke test hạ tầng

Mở các tiến trình riêng trên Windows/XAMPP:

```powershell
php artisan schedule:work
php artisan queue:work redis --queue=payments --tries=3 --backoff=60,300,900
php artisan queue:work redis --queue=broadcasts --tries=3
php artisan queue:work redis --queue=emails --tries=3 --backoff=10,60,300
```

Kiểm tra lần lượt: Redis restart → job còn trong queue/failed có thể retry; Pusher outage → payment vẫn paid và polling vẫn trả trạng thái server; worker restart → job không mất; scheduler chạy `sepay:expire-payments` mỗi phút và `sepay:reconcile` mỗi 15 phút. Không chạy một worker duy nhất cho production nếu cần cô lập lỗi queue.

### 4. Cloudflare/HTTPS

Webhook sandbox có thể dùng tunnel HTTPS để test, nhưng Live phải dùng hostname ổn định (Named Tunnel hoặc reverse proxy cố định), certificate hợp lệ và `APP_URL`/trusted proxy khớp hostname. Không cấu hình webhook Live vào Quick Tunnel vì hostname đổi sau khi restart. Kiểm tra từ bên ngoài:

```powershell
curl.exe -i https://<stable-host>/ecommerce-app/webhooks/sepay/sandbox
```

GET không phải webhook hợp lệ nhưng phải đi vào route đúng (không rơi vào 404 Apache); POST JSON có HMAC mới được ACK.

### 5. Checklist chuyển Live

- Backup MySQL và `storage` trước deploy; kiểm tra `php artisan migrate:status`, không dùng `migrate:fresh`.
- Xác nhận Cart quality gate, sandbox E2E, worker Redis, scheduler, Pusher và mail transport đều xanh.
- Đặt `APP_ENV=production`, `APP_DEBUG=false`, HTTPS và hostname cố định.
- Nhập riêng live API token/webhook secret/bank account; không sao chép giá trị sandbox.
- Kiểm tra payment code/account filter với số tiền nhỏ, chạy một smoke transaction và đối chiếu dashboard SePay với order nội bộ.
- Theo dõi webhook ACK, queue lag, failed jobs, reconciliation runs, Pusher connection và email delivery trong ít nhất một chu kỳ expiry.
- Chỉ bật checkout Live sau khi các bước trên có bằng chứng; nếu lỗi, đặt `SEPAY_ENABLED=false` để chặn order mới nhưng vẫn giữ webhook/reconciliation cho attempt đang chờ.

### 6. Rotation, rollback và incident response

**Secret rotation:** tạo credential mới trên SePay, cập nhật đúng `SEPAY_SANDBOX_*` hoặc `SEPAY_LIVE_*`, kiểm tra `config:clear` → `sepay:check --connect`, gửi một webhook test, sau đó revoke credential cũ. Không log giá trị cũ/mới.

**Webhook lỗi:** giữ nguyên receipt và raw retention, kiểm tra HMAC/timestamp/environment, queue failed và retry; không xóa transaction để “chạy lại”. Nếu mất webhook, chạy reconciliation theo cửa sổ có overlap.

**Redis/worker lỗi:** giữ checkout kill switch nếu cần, khởi động Redis/worker, kiểm tra `queue:failed`, retry queue `payments`; không đánh dấu paid bằng tay ngoài manual exact-guard.

**Pusher lỗi:** không rollback payment; xác nhận bằng GET status/polling, rồi kiểm tra broadcast queue sau khi Pusher hồi phục.

**Rollback release:** backup trước, đưa code về bản tương thích và chạy `php artisan optimize:clear`; không drop bảng payment, không xóa receipts/transactions/runs và không rollback migration đã chứa dữ liệu thanh toán. Sau rollback phải chạy lại `sepay:check --connect`, `migrate:status` và một reconciliation recovery test.
