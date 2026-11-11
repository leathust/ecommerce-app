# Quy tắc làm việc dành cho Codex

File này áp dụng cho toàn bộ repository Laravel này. Codex phải đọc và tuân thủ các quy tắc dưới đây trong mọi phiên làm việc tiếp theo.

## 1. Nguyên tắc chung

- Đọc cấu trúc và code hiện tại trước khi sửa; không suy đoán tên route, bảng, cột, permission hoặc quan hệ model.
- Giữ nguyên các thay đổi không liên quan của người dùng trong working tree.
- Không đọc, sao chép hoặc đưa secret trong `.env` vào tài liệu, log, test output hay source code.
- Không chạy thao tác phá hủy dữ liệu như `migrate:fresh`, `db:wipe`, xóa storage hoặc reset Git nếu người dùng chưa yêu cầu rõ ràng.
- Ưu tiên thay đổi nhỏ, tương thích với Laravel 10 và kiến trúc hiện tại.
- Mọi chức năng admin mới phải tuân thủ authentication, trạng thái tài khoản và permission middleware hiện có.

## 2. Nguồn quản lý công việc

- Roadmap chi tiết nằm trong `docs/task-logs/*roadmap.md`.
- Dashboard quản lý trực quan nằm tại `docs/project-management-board.html`.
- Dữ liệu dashboard nằm tại `docs/assets/pm-board/pm-board.js`.
- Log triển khai dashboard nằm tại `docs/task-logs/project-management-dashboard.md`.
- Roadmap Markdown là nguồn mô tả, checklist và phạm vi nghiệp vụ chính.
- `pm-board.js` là bản phản ánh trực quan và phải luôn đồng bộ với tất cả roadmap.

## 3. Quy trình bắt buộc khi nhận một task roadmap

Trước khi code:

1. Đọc toàn bộ task được yêu cầu và các dependency liên quan trong roadmap.
2. Kiểm tra task tương ứng đã tồn tại trong mảng `tasks` của `pm-board.js` chưa.
3. Nếu chưa tồn tại, thêm task với ID duy nhất, module, priority, description, files dự kiến, checklist và trạng thái phù hợp.
4. Khi bắt đầu triển khai, đổi trạng thái dashboard sang `in_progress` nếu công việc kéo dài qua nhiều lượt. Không đánh dấu `[x]` trong roadmap ở bước này.
5. Nêu ngắn gọn phạm vi sẽ thay đổi và cách kiểm thử.

Trong khi code:

1. Bám đúng checklist và dependency của roadmap.
2. Nếu phát sinh thay đổi nghiệp vụ hoặc kiến trúc, cập nhật `decisions` trong `pm-board.js`.
3. Nếu phát hiện rủi ro, giới hạn hoặc technical debt chưa xử lý, cập nhật `risks` trong `pm-board.js`.
4. Không tự nhận task đã hoàn thành khi chức năng mới chỉ được tạo file nhưng chưa kiểm thử.

Sau khi code:

1. Chạy kiểm thử phù hợp với phạm vi thay đổi.
2. Nếu test đạt, đánh dấu task và checklist tương ứng `[x]` trong roadmap.
3. Cập nhật task tương ứng trong `pm-board.js`:
   - `status` thành `done`;
   - mô tả đúng kết quả đã triển khai;
   - danh sách `files` đúng với file thực tế;
   - `test` ghi kết quả kiểm thử thực tế;
   - `checklist` phản ánh các phần đã hoàn thành;
   - `decisions` ghi lại quy tắc nghiệp vụ quan trọng nếu có.
4. Thêm một mục mới vào `activities` trong `pm-board.js`, gồm loại hoạt động, task đã hoàn thành, kết quả chính và thời gian thực tế.
5. Cập nhật số liệu trong Test Center chỉ khi có kết quả lệnh test thực tế. Không được ước lượng hoặc bịa số test/assertion.
6. Nếu có migration mới hoặc vừa chạy migration, cập nhật danh sách/trạng thái migration trên dashboard theo kết quả thực tế.
7. Cập nhật `docs/task-logs/project-management-dashboard.md` nếu cấu trúc hoặc cách vận hành dashboard thay đổi.

## 4. Quy tắc khi tạo roadmap mới

Khi tạo file roadmap mới trong `docs/task-logs/`, Codex phải đồng thời:

1. Đặt tên file theo dạng `<module>-roadmap.md`.
2. Sử dụng task ID không trùng với bất kỳ roadmap hoặc task nào trong `pm-board.js`.
3. Khai báo rõ mục tiêu, dependency, checklist, kết quả cần đạt và kế hoạch test.
4. Thêm toàn bộ task mới vào mảng `tasks` của `pm-board.js`.
5. Thêm phase mới vào mảng `phases` nếu roadmap thuộc một giai đoạn mới.
6. Để task chưa bắt đầu ở `backlog`; task kế tiếp đã đủ dependency ở `ready`.
7. Kiểm tra tiến độ tổng, phase progress, module filter và KPI vẫn được tính đúng.

## 5. Ánh xạ trạng thái

Các trạng thái hợp lệ trong `pm-board.js`:

- `backlog`: chưa sẵn sàng hoặc chưa đến lượt.
- `ready`: dependency đã hoàn tất, có thể bắt đầu.
- `in_progress`: đang code.
- `review`: đang rà soát code hoặc nghiệp vụ.
- `testing`: đang chạy test/QA.
- `done`: code và kiểm thử đã đạt, roadmap đã được cập nhật.
- `blocked`: có trở ngại cụ thể cần người dùng hoặc hệ thống bên ngoài xử lý.

Không chuyển task sang `done` nếu:

- Còn checklist bắt buộc chưa hoàn thành.
- Test liên quan đang thất bại.
- Migration cần thiết chưa được tạo hoặc chưa được xác nhận.
- Roadmap và `pm-board.js` chưa đồng bộ.

## 6. Quy tắc kiểm thử

- Chạy test mục tiêu trước, ví dụ:

```powershell
php artisan test --filter=AdminOrderTest
```

- Chạy full regression khi thay đổi ảnh hưởng nhiều module hoặc khi hoàn tất một phase:

```powershell
php artisan test
```

- Kiểm tra cú pháp dữ liệu dashboard sau mỗi lần sửa:

```powershell
node --check docs/assets/pm-board/pm-board.js
```

- Khi thay đổi route, kiểm tra route liên quan:

```powershell
php artisan route:list
```

- Khi thay đổi migration, ưu tiên kiểm tra trạng thái trước:

```powershell
php artisan migrate:status
```

- Không chạy `migrate:fresh` trên database hiện có trừ khi người dùng cho phép rõ ràng.
- Báo cáo chính xác lệnh đã chạy, số test/assertion và lỗi còn lại. Nếu không thể chạy test, phải nói rõ lý do và không ghi task là đã kiểm thử đạt.

## 7. Quy tắc dữ liệu dashboard

- Không hard-code số phần trăm tiến độ; để JavaScript tính từ trạng thái task.
- Không tạo hai task có cùng `id`.
- Tên file trong task phải là đường dẫn tương đối từ project root.
- Mọi số liệu test, migration và activity phải phản ánh trạng thái repository/database thực tế tại thời điểm cập nhật.
- Không đưa tài khoản, mật khẩu, API key, token, email riêng tư hoặc nội dung `.env` vào dashboard.
- Sau khi cập nhật, xác nhận tối thiểu:
  - `node --check` thành công;
  - task xuất hiện đúng cột;
  - module filter chứa module mới;
  - phase progress và KPI được tính đúng;
  - roadmap và dashboard có cùng trạng thái task.

## 8. Báo cáo hoàn thành cho người dùng

Khi bàn giao một task, Codex phải báo cáo ngắn gọn:

1. Chức năng đã hoàn thành.
2. Các file chính đã tạo hoặc sửa.
3. Roadmap nào đã được cập nhật.
4. Task nào trong `pm-board.js` đã được cập nhật.
5. Các lệnh kiểm thử đã chạy và kết quả thực tế.
6. Migration hoặc command người dùng cần chạy, nếu có.
7. Rủi ro hoặc phần việc tiếp theo còn lại.

Nếu task chưa hoàn thành, không đánh dấu `[x]` hoặc `done`; ghi đúng trạng thái hiện tại và nguyên nhân.
