# Roadmap — Tích hợp thanh toán online SePay

> **Trạng thái:** ACTIVE — Task **14.5 đang được mở lại** ngày 19/11/2026 vì code/test QR đã có nhưng SePay Checkout vẫn bị khóa ở runtime, khách chưa chọn được phương thức SePay và chưa có bằng chứng QR thật tải/quét thành công. Task 14.6 chờ 14.5 được nghiệm thu lại; Live vẫn chờ sandbox E2E và quality gate 14.12.

## 1. Mục tiêu

Xây dựng luồng Cart → Checkout → tạo đơn → thanh toán chuyển khoản QR qua SePay cho cả khách vãng lai và customer đã đăng nhập. Hệ thống phải chạy tách biệt ở `sandbox` và `live`, nhận webhook an toàn/idempotent, tự đối soát giao dịch ngân hàng, cập nhật trang thanh toán thời gian thực qua Pusher và gửi email bằng Redis queue sau khi thanh toán được xác nhận.

Kết quả cuối phase phải đáp ứng:

- Không tin số tiền, order ID, payment status hoặc account từ browser.
- Không xác nhận thanh toán chỉ dựa trên redirect hoặc Pusher client.
- Webhook được xác thực HMAC-SHA256 trên raw body, chống replay và chống xử lý trùng ở database.
- Sandbox và Live dùng cùng code path nhưng khác credentials, tài khoản, webhook secret và dữ liệu.
- Mọi thay đổi `order/payment/inventory` chạy trong transaction có lock và có thể audit.
- Pusher chỉ cải thiện UX; polling trạng thái là fallback bắt buộc.
- Email được queue qua Redis sau commit; lỗi Pusher/email không rollback giao dịch đã xác nhận.
- Có đối soát định kỳ, hàng đợi ngoại lệ và thao tác admin có audit.

## 2. Tài liệu SePay đã nghiên cứu

- [Tổng quan SePay Webhooks](https://developer.sepay.vn/vi/sepay-webhooks)
- [Tích hợp webhook](https://developer.sepay.vn/vi/sepay-webhooks/tich-hop-webhook)
- [Lập trình webhook PHP](https://developer.sepay.vn/vi/sepay-webhooks/lap-trinh-webhooks/lap-trinh-webhook)
- [Test mode](https://developer.sepay.vn/vi/sepay-webhooks/test-mode/bat-dau-nhanh)
- [So sánh Test mode và Live](https://developer.sepay.vn/vi/sepay-webhooks/test-mode/so-sanh-voi-live)
- [Xác thực webhook](https://developer.sepay.vn/vi/sepay-webhooks/xac-thuc)
- [Xử lý lỗi webhook](https://developer.sepay.vn/vi/sepay-webhooks/xu-ly-loi)
- [Đối soát giao dịch](https://developer.sepay.vn/vi/sepay-webhooks/doi-soat-giao-dich)
- [Tạo QR và form thanh toán](https://developer.sepay.vn/vi/sepay-webhooks/tao-qr-va-form-thanh-toan)

### Contract quan trọng từ tài liệu

- SePay POST payload giao dịch và coi thành công khi endpoint trả HTTP `200/201` với JSON `{"success": true}` trong tối đa 30 giây.
- Payload Sandbox và Live giống nhau; Test mode tách dữ liệu thật và cho phép mô phỏng giao dịch.
- HMAC khuyến nghị dùng `X-SePay-Signature`, `X-SePay-Timestamp`, ký chuỗi `timestamp.raw_body`; timestamp lệch quá 5 phút phải bị từ chối.
- `id` giao dịch không đổi qua retry/replay và phải có unique constraint để chống trùng race-safe.
- SePay có thể retry theo Fibonacci và admin có thể replay thủ công; endpoint bắt buộc idempotent.
- QR dùng số tài khoản, ngân hàng, số tiền và mã thanh toán; mã thanh toán phải duy nhất, dễ trích xuất và khớp cấu hình SePay.
- Đối soát API cần phân trang, chống trùng, tôn trọng giới hạn tối đa 3 request/giây và nên chạy định kỳ.

## 3. Baseline dự án hiện tại

- Laravel 10/PHP 8.1; MySQL trên XAMPP.
- Cart guest/customer, merge khi login, totals server-side và AJAX đã có; checkout COD/SePay và trang QR/status đã có từ Task 14.4–14.5.
- `orders` có snapshot người nhận, item, tổng tiền, `payment_method`, `payment_status`; `user_id` nullable hỗ trợ guest.
- `OrderStatusService` quản lý state transition và `OrderInventoryService` trừ kho khi `confirmed`, hoàn kho khi `cancelled`; checkout SePay giữ kho có hạn và expiry hoàn đúng một lần.
- Đã có `paid_at`, `payment_attempts`, `sepay_webhook_receipts`, `sepay_transactions`, `payment_status_histories`, `payment_reconciliation_runs` từ Task 14.3; chưa có webhook/matching runtime.
- Pusher PHP/JS SDK và Predis đã cài (Task 14.2); chưa có event/channel checkout (14.7–14.8).
- Redis connection/queue separation đã có; worker/runbook vận hành vẫn thuộc 14.9/14.11.
- Route test `/sepay-hook` hiện chỉ ghi JSON Lines; phải được thay thế hoặc gỡ khi triển khai endpoint production ở Task 14.6.
- `payment.bank_transfer_enabled` đã được checkout dùng để bật/tắt SePay; secrets thanh toán vẫn chỉ nằm trong `.env`/secret manager.

## 4. Kiến trúc mục tiêu

```text
Cart
  → CheckoutService (validate lại catalog/stock/price)
  → Order + items snapshot + PaymentAttempt(pending) + giữ/trừ kho có hạn
  → Trang QR /checkout/payments/{public_id}
  → Khách chuyển khoản
  → SePay Webhook (raw body + HMAC + replay guard)
  → WebhookReceipt insert unique, ACK nhanh {success:true}
  → ProcessSePayWebhookJob
  → PaymentMatcher + DB transaction/locks
  → SePayTransaction + PaymentAttempt paid + Order payment_status paid
  → OrderPaymentConfirmed domain event AFTER COMMIT
      ├─ Pusher: private payment.{public_id}
      ├─ Redis: SendPaymentConfirmationEmailJob
      └─ Audit/metrics

Scheduler
  → sepay:reconcile (API, lock, rate limit, checkpoint)
  → cùng PaymentMatcher/idempotency path
  → unmatched/mismatch → Admin exception queue
```

### Quy tắc event

`OrderPaymentConfirmed` là domain event duy nhất phát sau commit. Broadcast Pusher và listener dispatch email Redis cùng nghe event này nhưng độc lập nhau. Không để email phụ thuộc vào trình duyệt/Pusher vì disconnect hoặc lỗi socket không được làm mất email.

## 5. Schema đề xuất

### Bổ sung `orders`

- `paid_at timestamp nullable`.
- Index `payment_status, paid_at`.
- Không lưu credential/provider payload trong `orders`.

### `payment_attempts`

- `id`, `public_id` UUID unique, `order_id` FK.
- `provider` (`sepay`), `environment` (`sandbox|live`).
- `payment_code` unique theo environment; không tái sử dụng.
- `expected_amount unsignedBigInteger` lưu số VND nguyên, `currency` mặc định `VND`; không dùng float để matching.
- `status`: `pending|paid|expired|cancelled|failed|manual_review`.
- Snapshot không bí mật: bank code, account display/masked, account holder.
- `access_token_hash` cho guest/private channel; không lưu raw token.
- `expires_at`, `paid_at`, `failed_at`, timestamps.
- Unique active-attempt rule được service + lock bảo vệ; không dựa riêng vào nullable unique.

### `sepay_webhook_receipts`

- `id`, `environment`, `external_transaction_id` string, raw payload JSON.
- `signature_valid`, `received_at`, `processed_at`, `processing_status`.
- `attempts`, `last_error_code`, `last_error_at`.
- Unique `(environment, external_transaction_id)` để retry/replay trả OK mà không xử lý hai lần.
- Chỉ lưu header allowlist cần audit; tuyệt đối không lưu secret/Authorization/cookie.

### `sepay_transactions`

- `environment`, `external_transaction_id`, `reference_code`, `gateway`, `transaction_at`.
- `account_number_masked`, `sub_account`, `payment_code`, `transfer_type`.
- `amount_in`, `amount_out`, `accumulated` dùng decimal/big integer phù hợp VND.
- `content`, payload JSON, `match_status` (`matched|unmatched|amount_mismatch|account_mismatch|duplicate|ignored_outgoing|manual_review`).
- Nullable FK `payment_attempt_id`, `order_id`; timestamps.
- Unique `(environment, external_transaction_id)` và index code/reference/time.

### `payment_status_histories`

- Payment/order, from/to status, source (`webhook|reconciliation|admin|expiry`), actor nullable, transaction nullable, note/error code, created_at.
- Không trộn với `order_status_histories`, vì trạng thái giao vận và thanh toán là hai state machine khác nhau.

### `sepay_reconciliation_runs`

- Environment, started/finished timestamps, checkpoint/since_id, range, pages, fetched/matched/unmatched/error counts, status và message đã redact.
- Dùng để vận hành, resume và chứng minh kết quả đối soát.

## 6. Quy tắc matching và xử lý ngoại lệ

Chỉ tự động xác nhận khi đồng thời thỏa mãn:

1. HMAC và timestamp hợp lệ.
2. `transferType = in`.
3. Environment/tài khoản nhận khớp cấu hình đang chạy.
4. `payment_code` khớp đúng một attempt còn hợp lệ.
5. Số tiền nhận bằng chính xác `expected_amount`.
6. Giao dịch chưa từng được xử lý.

Không tự cộng dồn nhiều giao dịch ở phase đầu. Thiếu/thừa tiền, thiếu code, sai tài khoản, giao dịch ra, attempt hết hạn hoặc đơn đã hủy được lưu `manual_review` và hiển thị cho admin. Admin có quyền match/ignore/refund-note, mọi thao tác phải audit; không được sửa payload gốc.

## 7. Tồn kho và hết hạn thanh toán

- Checkout phải khóa sản phẩm, kiểm tra lại giá/tồn kho và tạo order snapshot trong một transaction.
- Với SePay, trừ/giữ kho ngay khi tạo payment attempt trong thời hạn thanh toán (đề xuất 15 phút), đánh dấu bằng marker hiện có để tránh trừ lần hai khi confirmed.
- Job hết hạn chỉ hủy attempt/order pending chưa paid và hoàn kho đúng một lần.
- Command `sepay:expire-payments` chạy mỗi phút qua scheduler; status route vẫn lazy-expire để UX đúng khi scheduler trễ.
- Webhook đến đồng thời với expiry phải lock order + attempt; trạng thái đã commit trước quyết định kết quả.
- Nếu tiền đến sau khi order đã hết hạn/hủy, không tự hồi sinh đơn: đưa vào manual review/refund workflow.
- COD giữ hành vi hiện tại: trừ kho khi admin xác nhận.

## 8. Cấu hình Sandbox/Live

Secrets chỉ nằm trong `.env`/secret manager, không đưa vào settings database hoặc dashboard:

```env
SEPAY_ENABLED=false
SEPAY_MODE=sandbox
SEPAY_PAYMENT_TIMEOUT_MINUTES=15
SEPAY_PAYMENT_ACCESS_GRACE_MINUTES=30
SEPAY_QR_BASE_URL=https://vietqr.app/img
SEPAY_QR_TEMPLATE=compact
SEPAY_SANDBOX_WEBHOOK_SECRET=
SEPAY_SANDBOX_API_TOKEN=
SEPAY_SANDBOX_BANK_CODE=
SEPAY_SANDBOX_ACCOUNT_NUMBER=
SEPAY_SANDBOX_ACCOUNT_NAME=
SEPAY_LIVE_WEBHOOK_SECRET=
SEPAY_LIVE_API_TOKEN=
SEPAY_LIVE_BANK_CODE=
SEPAY_LIVE_ACCOUNT_NUMBER=
SEPAY_LIVE_ACCOUNT_NAME=

BROADCAST_DRIVER=pusher
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
```

- `config/sepay.php` chọn bộ cấu hình theo `SEPAY_MODE`, fail closed nếu thiếu biến bắt buộc.
- Mỗi environment tạo webhook riêng trên SePay và có secret riêng.
- Record luôn lưu environment để không bao giờ match transaction sandbox vào order live.
- Chuyển sang live là deployment decision, không phải nút UI tùy ý trong admin.

## 9. Tasks triển khai

### [x] Task 14.1 — Chốt contract Checkout và SePay

**Dependency:** Cart 11.1–11.8; phải đóng quality gate 11.9 trước khi mutation checkout production.

- [x] Kiểm kê Cart, Order, inventory, settings, mail, broadcast, queue và route test SePay.
- [x] Chốt guest/customer checkout, order/payment state machines và timeout.
- [x] Chốt matching exact amount/code/account và manual-review policy.
- [x] Chốt security boundary, response contract và dữ liệu được log.
- [x] Chốt sandbox/live rollout và out-of-scope.

**Kết quả cần đạt:** contract không còn điểm mơ hồ trước migration/code.

**Kế hoạch test:** baseline suites Cart/Order, route/config inventory, migration status, dashboard syntax.

#### Contract đã chốt cho Task 14.1

##### 1. Actor, Cart và quyền truy cập Checkout

- Guest và customer `active` đều được checkout; admin hoặc customer không active bị từ chối. Actor luôn resolve từ Cart identity/request hiện tại, không nhận `user_id`, `cart_id` hoặc owner từ browser.
- Checkout lấy toàn bộ item khả dụng trong Cart hiện tại. Nếu bất kỳ item nào inactive, hết hàng, vượt tồn kho hoặc có giá thay đổi thì request dừng với thông báo để khách xác nhận lại; không âm thầm bỏ dòng khỏi đơn.
- Server khóa Cart, Cart items và Product theo thứ tự ổn định, tính lại subtotal/shipping/minimum-order từ database/settings rồi mới tạo snapshot. Client chỉ gửi thông tin người nhận, phương thức thanh toán và idempotency key.
- `GET /checkout` phát hành idempotency key ngẫu nhiên lưu trong session; submit lặp cùng key trả lại cùng Order/PaymentAttempt, không tạo đơn thứ hai. Key khác không được checkout lại Cart đã được consume.
- Cart chỉ bị clear trong cùng transaction tạo Order thành công. Validation/DB failure giữ nguyên Cart.
- Customer xem payment theo ownership `orders.user_id`. Guest được cấp payment access token entropy 256-bit trong cookie HttpOnly/SameSite=Lax, Secure khi HTTPS; DB chỉ lưu SHA-256. Token có path giới hạn cho payment routes, hết hạn cùng attempt và không được log/đưa vào URL.

##### 2. Tiền, mã thanh toán và snapshot

- Currency phase đầu chỉ là `VND`. `expected_amount` lưu integer VND (`unsignedBigInteger`); tổng `orders.total_amount` phải có phần lẻ `.00`, nếu catalog/settings sinh số VND lẻ thì checkout fail closed thay vì tự làm tròn.
- `payment_code` tách khỏi khóa nội bộ, format `DH` + 12 ký tự uppercase `[A-Z0-9]`, sinh bằng CSPRNG và unique theo environment. Nội dung QR dùng chính xác code này, không chứa tên/email/số điện thoại.
- Order giữ snapshot item, người nhận, subtotal, shipping và total. PaymentAttempt giữ snapshot provider/environment/bank display/expected amount/expiry; không đọc lại giá Cart để matching.
- Phase đầu mỗi Order chỉ có một attempt SePay active. Retry QR của attempt còn hạn dùng lại code cũ; attempt mới chỉ được tạo sau khi attempt trước terminal và phải có code mới.

##### 3. State machine Order, Payment và tồn kho

- Khởi tạo SePay: Order `pending + unpaid`, PaymentAttempt `pending`, stock được trừ/giữ ngay và ghi `stock_deducted_at`; timeout mặc định 15 phút.
- Attempt transitions hợp lệ: `pending → paid|expired|cancelled|failed|manual_review`; `manual_review → paid|failed`; `paid` là terminal. Giao dịch đến cho attempt `expired/cancelled` không hồi sinh attempt mà transaction vào manual review.
- Thanh toán exact-match: attempt `paid`, Order `payment_status=paid`, `paid_at` được set và Order `pending → confirmed` bằng system transition có `changed_by=null`; marker tồn kho ngăn trừ lần hai.
- Expiry chỉ áp dụng khi attempt vẫn pending và Order chưa paid: attempt `expired`, Order `pending → cancelled`, lý do hệ thống và hoàn kho đúng một lần. Manual review không giữ kho quá deadline; expiry vẫn hủy Order còn transaction sai lệch tiếp tục chờ admin xử lý.
- Webhook/payment và expiry cùng lock theo thứ tự PaymentAttempt → Order → Products. Transaction commit trước thắng; phía sau đọc state mới và không thực thi lần hai.
- COD giữ invariant hiện tại: Order pending/unpaid và chỉ trừ kho khi admin chuyển confirmed.

##### 4. Matching và manual review

- Auto-paid chỉ khi HMAC/timestamp hợp lệ, environment và account đích khớp, `transferType=in`, code khớp đúng một attempt, số tiền bằng tuyệt đối expected amount và external transaction chưa xử lý.
- Không cộng dồn partial payment. Underpayment, overpayment, thiếu/không rõ code, sai account, outgoing, late payment, Order đã hủy/paid hoặc nhiều candidate đều được lưu transaction bất biến và phân loại manual review/ignored phù hợp.
- Duplicate cùng `(environment, external_transaction_id)` không tạo history/event/email lần hai và trả success nếu receipt đã được persist.
- Admin resolve phải có permission `payments.resolve`, reason bắt buộc, DB lock và audit. Manual match chỉ dùng transaction đã xác thực/đối soát, không được sửa raw payload; refund phase đầu chỉ là note/quy trình thủ công.

##### 5. Webhook security và response contract

- Production dùng `POST /webhooks/sepay/{environment}` với `{environment}` chỉ nhận `sandbox|live`; route test `/sepay-hook` và log viewer hiện tại phải bị gỡ ở Task 14.6. Mỗi environment có secret riêng và có thể bật/tắt tiếp nhận riêng để hỗ trợ cutover an toàn.
- Chỉ nhận `application/json`, giới hạn body, đọc đúng raw bytes bằng `$request->getContent()`. HMAC là `sha256=` + HMAC-SHA256 của `timestamp.raw_body`; dùng `hash_equals`, timestamp lệch quá 300 giây bị từ chối.
- Auth sai trả `401`, content type sai `415`, payload sai `400`; không trả exception/stack trace. Chỉ trả HTTP `200` với body đúng `{"success":true}` sau khi receipt unique đã persist và processing job đã dispatch/được bảo đảm recover.
- Nếu DB hoặc queue không thể bảo đảm xử lý, trả `5xx` để SePay retry. Khi duplicate receipt còn `pending/failed`, endpoint phải thử dispatch idempotent lại; không ACK rồi làm mất job.
- HMAC là lớp xác thực chính. IP allowlist/WAF là defense-in-depth; không dựa duy nhất vào IP do Cloudflare proxy. Rate limit phải đủ cho burst/retry và không được thay thế unique constraint.

##### 6. Logging, dữ liệu nhạy cảm và retention

- Application log chỉ ghi receipt ID, external transaction ID, environment, result/error code, latency và ID Order/Attempt nếu match. Không ghi HMAC secret, API token, Authorization, cookie, access token, raw body hoặc số tài khoản đầy đủ.
- Raw provider payload được lưu trong database có quyền `payments.view`, UI luôn mask account và không phản chiếu description/content chưa escape.
- Không tự xóa transaction/receipt trước khi có retention/backup policy được phê duyệt. Error message lưu dạng code/redacted message, không lưu exception chứa credential.
- Event Pusher chỉ mang `payment_public_id`, status, paid_at và URL trạng thái nội bộ; không mang người nhận, email, account, amount raw payload hoặc token.

##### 7. Sandbox, Live và feature flag

- Checkout chỉ dùng environment được chọn bởi deployment config; browser/admin không được truyền hoặc đổi environment. Record luôn snapshot environment.
- Sandbox và Live có webhook secret, API token, bank account và webhook dashboard riêng. Không fallback credential từ môi trường này sang môi trường kia.
- `SEPAY_ENABLED=false` chặn tạo checkout/attempt mới nhưng webhook, expiry và reconciliation vẫn tiếp tục cho attempt đã tồn tại; đây là kill switch an toàn.
- Không bật Live trên Quick Tunnel thay đổi hostname. Live yêu cầu hostname ổn định, HTTPS hợp lệ, worker Redis, scheduler và monitoring đang chạy.
- Chuyển Live chỉ sau Sandbox E2E gồm success, duplicate, tamper, under/over, expiry, late payment, queue/Pusher outage và reconciliation recovery.

##### 8. Event, Pusher và email

- `OrderPaymentConfirmed` phát sau commit đúng một lần. Pusher broadcast và listener queue email là hai consumer độc lập.
- Private channel authorization dùng customer ownership hoặc guest payment access token; client event chỉ kích hoạt GET status server-confirmed. Polling là fallback bắt buộc.
- Email job chạy Redis queue `emails`, idempotent theo payment confirmation event, dùng order snapshot và locale; lỗi/retry email không thay đổi payment.

##### 9. Out of scope phase đầu

- Partial/cộng dồn nhiều giao dịch, split payment, đa tiền tệ, recurring payment.
- Refund tự động qua API ngân hàng, chargeback automation.
- Card/e-wallet gateway dù enum Order đã có.
- Guest khôi phục payment trên thiết bị khác, nhiều attempt active cùng lúc.
- Pusher làm source of truth hoặc email phụ thuộc browser nhận socket.

#### Kết quả rà soát thực tế Task 14.1

- Route inventory: 5 Cart routes; chưa có route Checkout; 2 route SePay hiện tại chỉ phục vụ logger test.
- Database: 25 migration hiện đều `Ran`; chưa có schema payment provider.
- Infrastructure: queue default trong example là `sync`; PHP CLI chưa nạp `redis`; project chưa khai báo Pusher PHP/JS SDK.
- Baseline `php artisan test --filter='Cart|Order|SePayWebhook'`: **95 passed, 1050 assertions**.
- `php artisan migrate:status`: **25/25 migration Ran**; không có migration/runtime được thay đổi trong Task 14.1.
- `node --check docs/assets/pm-board/pm-board.js` và `git diff --check`: đạt; dashboard có 122 task ID unique và Phase P14 tham chiếu đủ 12 task.
- Dependency audit: chưa cài `pusher/pusher-php-server`, `laravel-echo`, `pusher-js`; PHP CLI chưa nạp `redis`. PowerShell chặn wrapper `npm.ps1`, nên Task 14.2 dùng `npm.cmd` hoặc điều chỉnh execution policy có kiểm soát.

**Kết quả thực tế:** contract Checkout/SePay đã khóa ở mức đủ để thiết kế migration và hạ tầng mà không cần suy đoán nghiệp vụ. Task 14.2 và 14.3 chuyển `ready`; Task 14.4 vẫn chờ 11.9 và 14.3. Route logger SePay hiện hữu tiếp tục chỉ là công cụ test cho đến khi Task 14.6 thay thế.

### [x] Task 14.2 — Dependencies và hạ tầng Redis/Pusher/SePay

**Dependency:** 14.1.

- [x] Cài `pusher/pusher-php-server`, `laravel-echo`, `pusher-js`; xác minh version với Laravel 10.
- [x] Xác minh Redis server và `phpredis` hoặc chọn `predis/predis` rõ ràng.
- [x] Tạo config SePay typed/fail-closed và env example không chứa secret thật.
- [x] Tách queue `payments`, `emails`, `broadcasts`; cấu hình retry/backoff/failed jobs.
- [x] Tạo health/readiness command kiểm tra config mà không in credential.

**Kết quả thực tế 14/11/2026 — DONE:** đã cài Pusher PHP `7.3.0`, Predis `2.4.1`, Laravel Echo `1.16.1` và Pusher JS `8.4.0`; config, queue và readiness command đã đạt test. Project chọn Predis làm PHP client và Redis container do người dùng vận hành đã kết nối thành công tại runtime; `php artisan sepay:check --connect` xác nhận toàn bộ readiness check đạt.

**Kiểm thử đã chạy:** bộ `SePayInfrastructureTest|SePayWebhookTest|TrustedProxyUrlTest` đạt **9 tests, 37 assertions**; Vite production build thành công; `npm audit --omit=dev` báo **0 vulnerability**; readiness `--connect` đạt. Composer audit nền còn **13 advisories trong 6 package** (có advisory critical của Laravel framework), cần một task nâng cấp bảo mật riêng trước khi bật Live.

**Kết quả cần đạt:** sandbox infrastructure sẵn sàng nhưng chưa nhận tiền thật.

**Kế hoạch test:** config tests, dependency audit, Redis ping, queue/broadcast fake và secret scan.

### [x] Task 14.3 — Schema và model thanh toán

**Dependency:** 14.1.

- [x] Migration additive cho orders và năm bảng payment/SePay đã đề xuất.
- [x] Backfill an toàn cho order cũ; không tự đánh dấu paid.
- [x] Model relationships, casts, enums/constants, factories.
- [x] Unique/index/FK/idempotency constraints cho MySQL và SQLite test.
- [x] Migration `down()` không làm mất dữ liệu ngoài phạm vi bảng mới.

**Kết quả thực tế 14/11/2026 — DONE:** migration `2026_11_14_000030` thêm nullable `orders.paid_at` và tạo `payment_attempts`, `sepay_webhook_receipts`, `sepay_transactions`, `payment_status_histories`, `payment_reconciliation_runs`. Provider ID, payment code và idempotency key được unique theo environment. Sau duyệt nghiệp vụ, migration `2026_11_14_000040` chuyển toàn bộ FK payment → order sang `RESTRICT`: order có dấu vết thanh toán không thể hard-delete. Raw webhook dùng retention cấu hình mặc định 180 ngày để Task 14.6 archive/redact; số tài khoản không mã hóa; chỉ một giao dịch khớp chính xác toàn bộ số tiền mới auto-paid, partial payment vào manual review; không snapshot QR. Hai migration đã chạy trên MySQL; 48 order cũ được giữ nguyên và không order nào bị tự đánh dấu paid.

**Kiểm thử đã chạy:** `PaymentSchemaTest` sau điều chỉnh đạt **5 tests, 27 assertions**; regression mục tiêu gần nhất đạt **10 tests, 66 assertions**; full regression trước điều chỉnh FK đạt **463 tests, 4230 assertions**. PHP lint, dashboard JavaScript và diff check đều đạt.

**Kết quả cần đạt:** dữ liệu provider/audit tách khỏi order snapshot và chống trùng ở DB.

**Kế hoạch test:** schema round-trip, unique race, FK, cast, model relationship, migrate status.

### [x] Task 14.4 — Checkout, tạo đơn và giữ tồn kho

**Dependency:** 11.9, 14.3.

- [x] Checkout FormRequest VI/EN, địa chỉ/điện thoại/payment method allowlist.
- [x] `CheckoutService` lock Cart/products, tính lại giá/tồn kho/settings server-side.
- [x] Tạo Order/items/payment attempt/payment code nguyên tử và idempotency key chống double submit.
- [x] Trừ/giữ kho SePay có expiry; COD giữ workflow cũ.
- [x] Clear Cart chỉ sau commit thành công; lỗi giữ Cart nguyên vẹn.
- [x] Guest/customer ownership và safe redirect.

**Kết quả thực tế 14/11/2026 — DONE:** POST `/checkout` tạo Order snapshot từ Cart trong transaction có row lock, không nhận giá/tổng từ client. COD giữ stock đến bước confirmed; SePay tạo attempt/code/expiry, trừ stock ngay và `expire()` hoàn kho đúng một lần. `checkout_idempotency_key` unique chống double-submit cho cả COD/SePay; Cart chỉ clear ở cuối transaction và rollback giữ nguyên khi lỗi. Guest dùng cookie Cart hiện hữu, customer/admin/inactive được tách đúng boundary; response hỗ trợ JSON và HTML fallback VI/EN.

**Kiểm thử đã chạy:** Checkout đạt **5 tests, 25 assertions**; regression mục tiêu đạt **28 tests, 253 assertions**; full regression đạt **469 tests, 4262 assertions**. Migration `2026_11_14_000050` đã chạy MySQL batch 12; route, PHP lint, dashboard và diff checks đạt.

**Kết quả cần đạt:** tạo một order hợp lệ từ Cart mà không tin totals client.

**Kế hoạch test:** concurrent checkout, price/stock drift, duplicate submit, guest/customer, rollback và inventory expiry.

### [x] Task 14.5 — Trang QR và trạng thái thanh toán

**Dependency:** 14.4.

- [x] Trang checkout/QR responsive VI/EN, hiển thị amount, account, payment code và countdown.
- [x] Sinh QR bằng endpoint được SePay/VietQR tài liệu hóa; encode query an toàn.
- [x] Route trạng thái owner/token-scoped, trả state tối thiểu, `Cache-Control: no-store`.
- [x] No-JS/polling fallback và nút thử lại an toàn; không cho sửa amount/code.
- [x] Success/expired/manual-review UI và accessibility live region.
- [x] Tách readiness Checkout QR khỏi webhook/queue/Pusher để không khóa phương thức thanh toán bởi dependency của task sau.
- [x] Redirect trang payment và URL polling dùng đường dẫn tương đối để giữ nguyên hostname đang sở hữu cookie guest khi tunnel được thay.
- [ ] Checkout runtime hiển thị và cho phép chọn SePay khi master flag, setting chuyển khoản và snapshot ngân hàng hợp lệ.
- [ ] QA luồng thật Cart → Checkout → SePay → PaymentAttempt → trang QR trên desktop/mobile.
- [ ] Xác nhận ảnh QR thật tải được và quét ra đúng account/amount/payment code trong sandbox.

**Trạng thái rà soát lại 19/11/2026 — IN PROGRESS:** phần code/test trước đây đã tạo checkout page, PaymentAttempt, QR/countdown, status owner/token-scoped, polling/no-JS và expiry command. Tuy nhiên `php artisan sepay:check` trên runtime thực tế báo `SePay: DISABLED`; nghiệm thu cũ chưa chứng minh khách có thể chọn SePay và ảnh QR thật tải/quét được. Task được mở lại để tách readiness Checkout khỏi hạ tầng webhook/Pusher/queue và QA end-to-end phần QR trước khi chuyển 14.6 sang ready.

**Kết quả triển khai lại 19/11/2026:** readiness đã tách thành `checkout_ready`, `webhook_ready`, `queue_ready`, `broadcast_ready`; Checkout QR chỉ yêu cầu master flag, environment, bank code/account và QR base URL hợp lệ, không còn bị khóa bởi webhook secret/API token/Redis/Pusher. `sepay:check` hiển thị từng profile và lý do Checkout chưa sẵn sàng mà không in credential. Target mở rộng đạt **48 tests, 871 assertions**; full regression đạt **496 tests, 4427 assertions**. Runtime hiện vẫn `Checkout QR: NOT READY` vì SePay đang tắt; QA chọn SePay và quét QR sandbox còn chờ cấu hình môi trường, nên task chưa được đóng.

**Khắc phục tunnel 19/11/2026:** Checkout redirect và các URL refresh/polling đã chuyển sang relative URL, vì vậy không còn lấy hostname cũ từ `APP_URL`. `CheckoutPageTest|CheckoutPaymentPageTest` đạt **23 tests, 133 assertions**. Tại thời điểm kiểm tra sau `php artisan optimize:clear`, runtime vẫn nạp `app.url` là hostname tunnel cũ; cần lưu đúng một `APP_URL` hostname mới trước khi tạo attempt mới. Attempt guest cũ không thể mở ở hostname mới vì cookie `sepay_payment_access` được scope theo hostname và 404 là cơ chế bảo vệ chủ đích.

**Kiểm thử đã chạy:** `CheckoutPageTest` 11 passed/48 assertions; `CheckoutPaymentPageTest` 12 passed/85 assertions; `PaymentExpiryCommandTest` 2 passed/16 assertions; `PaymentAccessTokenServiceTest` 4 passed/9 assertions; `CheckoutTest` 5 passed/26 assertions → **34 tests/184 assertions** cho phạm vi checkout. Regression mục tiêu `Checkout|Payment|Cart|Order` đạt **135 passed/1269 assertions**; full regression đạt **500 passed/4439 assertions**. `route:list` có 4 route checkout, `schedule:list` có 1 command, Blade cache, `php -l`, `node --check` (main.js + pm-board.js) và `git diff --check` đều đạt.

**Kết quả cần đạt:** khách có thể thanh toán và theo dõi trạng thái ngay cả khi socket lỗi.

**Kế hoạch test:** authorization/token, XSS, QR params, polling, expiry, mobile/keyboard/VI-EN.

### [ ] Task 14.6 — Webhook HMAC và ingestion idempotent

**Dependency:** 14.2, 14.3.

- [ ] Thay route ghi file thử nghiệm bằng controller production chuyên trách.
- [ ] Đọc raw body qua `$request->getContent()`, verify timestamp ±300 giây và HMAC bằng `hash_equals`.
- [ ] Validate content type/payload/transfer fields và giới hạn body.
- [ ] Insert receipt unique trước ACK, dispatch processing after commit.
- [ ] Duplicate trả `{"success":true}`; lỗi auth/payload dùng 401/400; lỗi persist dùng 5xx để SePay retry.
- [ ] Structured logging redact secret/account/PII; rate limit không làm mất traffic SePay hợp lệ.

**Kết quả cần đạt:** endpoint nhanh, an toàn, replay-safe và không xử lý business logic hai lần.

**Kế hoạch test:** HMAC vector, tampered body, expired timestamp, duplicate/concurrent delivery, malformed JSON, retry response contract.

### [ ] Task 14.7 — Matching và xác nhận thanh toán nguyên tử

**Dependency:** 14.4, 14.6.

- [ ] `PaymentMatcher` dùng chung cho webhook và reconciliation.
- [ ] Lock receipt/transaction/attempt/order; exact code/amount/account/environment.
- [ ] Lưu transaction, update payment attempt/order/history đúng một lần.
- [ ] Tích hợp inventory/order transition theo policy đã chốt.
- [ ] Phát `OrderPaymentConfirmed` chỉ sau commit.
- [ ] Phân loại unmatched/late/under/over/outgoing vào manual review.

**Kết quả cần đạt:** trạng thái payment/order nhất quán dưới retry và race.

**Kế hoạch test:** matrix matching, duplicate race, webhook-vs-expiry race, rollback injection và history audit.

### [ ] Task 14.8 — Pusher realtime cho Checkout

**Dependency:** 14.5, 14.7.

- [ ] Broadcast event tối thiểu: public ID, status, paid_at; không PII/account/payload.
- [ ] Private channel `payment.{public_id}` với authorization theo customer hoặc checkout session/token.
- [ ] Laravel Echo/Pusher client subscribe, reconnect và chuyển trang success server-confirmed.
- [ ] Polling fallback khi auth/socket/Pusher thất bại.
- [ ] Tách queue broadcast; lỗi broadcast không rollback/đổi payment.

**Kết quả cần đạt:** checkout cập nhật gần realtime mà không coi socket là source of truth.

**Kế hoạch test:** broadcast fake/payload leak scan/channel authorization, JS reconnect/stale event, polling parity.

### [ ] Task 14.9 — Redis queue và email xác nhận

**Dependency:** 14.2, 14.7.

- [ ] Listener after-commit dispatch `SendPaymentConfirmationEmailJob` lên queue `emails`.
- [ ] Job idempotent theo order/payment event, retry/backoff/timeout và failed job visibility.
- [ ] Mailable VI/EN chỉ dùng order snapshot; không đưa raw bank payload/secret.
- [ ] Worker/Supervisor hoặc Windows service runbook; restart sau deploy.
- [ ] Alert khi failed jobs vượt ngưỡng; retry thủ công có audit.

**Kết quả cần đạt:** email bền vững, không chặn webhook và không gửi trùng.

**Kế hoạch test:** Queue/Mail fake, duplicate event, retry failure, locale, serialization và Redis integration smoke test.

### [ ] Task 14.10 — Đối soát và quản trị ngoại lệ

**Dependency:** 14.3, 14.7.

- [ ] SePay API client timeout/retry/rate-limit ≤3 request/giây và redact token.
- [ ] Command/job `sepay:reconcile` theo time window/checkpoint, `withoutOverlapping` và single-server lock.
- [ ] Scheduler 15–30 phút/lần; dùng cùng matcher/idempotency với webhook.
- [ ] Admin list/detail/filter unmatched, mismatch, late, failed; permission riêng và audit.
- [ ] Manual match/ignore/refund-note có confirm modal, reason bắt buộc và lock.
- [ ] KPI webhook lag, pending quá hạn, mismatch và reconciliation failures.

**Kết quả cần đạt:** phát hiện giao dịch mất webhook và xử lý sai lệch có truy vết.

**Kế hoạch test:** API fake pagination/429/5xx, checkpoint resume, overlapping guard, RBAC/audit/manual race.

### [ ] Task 14.11 — Sandbox, Live rollout và vận hành

**Dependency:** 14.2–14.10.

- [ ] Tạo account/webhook Test mode, HMAC secret và mô phỏng happy/error/replay cases.
- [ ] Runbook Cloudflare HTTPS, worker, scheduler, Pusher, Redis, SePay dashboard history.
- [ ] Checklist chuyển Live: backup, HTTPS, secret rotation, account/code filters, smoke transaction nhỏ.
- [ ] Feature flag/kill switch chỉ chặn checkout mới; vẫn nhận webhook/đối soát cho order đang chờ.
- [ ] Rollback không drop payment data; quy trình rotate secret/token và incident response.

**Kết quả cần đạt:** chuyển môi trường có kiểm soát, không trộn sandbox/live.

**Kế hoạch test:** sandbox E2E bằng giao dịch mô phỏng, replay, worker restart, Pusher outage, Redis outage và reconciliation recovery.

### [ ] Task 14.12 — Quality gate thanh toán SePay

**Dependency:** 14.1–14.11.

- [ ] Full security/idempotency/concurrency test matrix.
- [ ] Cart → Checkout → QR → webhook/reconcile → paid → email/realtime E2E.
- [ ] Full regression Cart/Order/Inventory/Settings/Auth/IAM.
- [ ] Migration/route/config/queue/scheduler/broadcast audit và secret scan.
- [ ] Performance: webhook ACK nhanh, bounded queries, queue lag và reconciliation pagination.
- [ ] Visual/accessibility QA desktop/tablet/mobile VI/EN.
- [ ] Cập nhật operations docs, dashboard, Test Center và đóng phase chỉ khi sandbox E2E đạt.

**Kết quả cần đạt:** module đủ điều kiện pilot Live có giám sát và rollback.

**Kế hoạch test:** targeted suites, full `php artisan test`, sandbox SePay test mode, queue/Redis/Pusher smoke test và manual QA checklist.

## 10. Permission đề xuất

- `payments.view`: xem giao dịch/payment attempt đã che dữ liệu nhạy cảm.
- `payments.reconcile`: chạy đối soát và retry job.
- `payments.resolve`: manual match/ignore/refund-note.
- `payments.settings`: xem readiness không-secret; không hiển thị secret/token.

Super admin vẫn qua Gate theo contract IAM hiện tại. Mọi mutation admin phải ghi `admin_audit_logs`.

## 11. Kế hoạch test tổng thể

| Lớp | Trường hợp bắt buộc |
|---|---|
| Unit | HMAC, payment code, QR URL, state transitions, matcher |
| Feature | checkout ownership, webhook response, admin RBAC, status endpoint |
| Integration | MySQL locks/unique, Redis queue, Pusher broadcast fake/real smoke |
| Concurrency | double checkout, duplicate webhook, webhook-expiry, webhook-reconcile |
| Failure | DB rollback, queue down, Pusher down, Redis down, SePay 429/5xx, late payment |
| Security | replay/tamper, IDOR, CSRF, channel auth, XSS, secret/PII leakage |
| E2E Sandbox | simulated transfer → paid order → socket update → queued email |

## 12. Rủi ro và giới hạn

- Cloudflare Quick Tunnel đổi domain sau khi restart nếu không dùng named tunnel; webhook Live cần hostname ổn định và HTTPS hợp lệ.
- Redis trên XAMPP/Windows cần service và worker chạy bền; `queue:work` thủ công không đủ cho production.
- Trừ kho khi chờ thanh toán thay đổi invariant Order hiện tại; cần test hồi kho và race đặc biệt kỹ.
- Pusher có quota/mất kết nối; polling luôn phải tồn tại.
- Không tự động refund qua ngân hàng trong phase này; chỉ ghi nhận/refund-note và quy trình thủ công.
- Không hỗ trợ partial payment, cộng dồn nhiều giao dịch, split payment, đa tiền tệ hoặc recurring payment ở phase đầu.
- Không lưu số tài khoản đầy đủ trong log/UI nếu không cần; payload gốc chỉ dành cho service/admin có quyền và retention rõ ràng.

## 13. Thứ tự triển khai khuyến nghị

`14.1 → (14.2 || 14.3) → 14.4 → 14.5 → 14.6 → 14.7 → (14.8 || 14.9) → 14.10 → 14.11 → 14.12`

Không chuyển Live trước khi Task 11.9 (Cart quality gate), toàn bộ Task 14.1–14.11 và Sandbox E2E đều hoàn tất.
