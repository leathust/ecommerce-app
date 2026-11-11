# Project Management Dashboard — Implementation Log

## Mục tiêu

Tạo một dashboard HTML tĩnh mô phỏng Trello để PM theo dõi toàn bộ tiến độ, task, test, migration, activity, risk và decision của dự án ecommerce.

## Trạng thái: Hoàn tất

- [x] Tạo shell dashboard responsive trong `docs/project-management-board.html`.
- [x] Tạo design system sáng/tối, board ngang và print stylesheet.
- [x] Tổng hợp 36 task từ foundation, customer, order, analytics và IAM roadmap.
- [x] Tạo 7 cột trạng thái: Backlog, Ready, In progress, Review, Testing, Done, Blocked.
- [x] Thêm tìm kiếm, lọc module, lọc priority và reset filter.
- [x] Thêm modal chi tiết task, checklist, file chính, kiểm thử và quyết định nghiệp vụ.
- [x] Thêm Roadmap 4 phase với tiến độ tính từ task thực tế.
- [x] Thêm Test Center: 166 tests, 1325 assertions, 20/20 migration đã chạy.
- [x] Thêm Activity timeline, Risk register và Architecture Decision Log.
- [x] Hỗ trợ dark mode, mobile sidebar, keyboard navigation và chế độ in báo cáo.
- [x] Không dùng CDN, framework frontend hay đọc dữ liệu nhạy cảm từ `.env`.

## Nguồn dữ liệu

- `docs/task-logs/khach-hang-don-hang-roadmap.md`
- `README.md`
- Các migration, model, controller, service, seeder và test hiện có trong project.
- Kết quả regression gần nhất: **166 tests passed, 1325 assertions**.

## Quy ước cập nhật

Dashboard hiện dùng dữ liệu tĩnh trong `docs/assets/pm-board/pm-board.js`. Khi hoàn tất task mới:

Quy trình bắt buộc dành cho Codex đã được định nghĩa tại `AGENTS.md` ở thư mục gốc và áp dụng cho toàn bộ repository.

1. Đổi `status` của task sang `done`.
2. Bổ sung task kế tiếp nếu roadmap thay đổi.
3. Cập nhật `tests`, `migrations` và `activities` theo kết quả thực tế.
4. Cập nhật risk/decision nếu có thay đổi nghiệp vụ hoặc kiến trúc.
5. Chạy `node --check docs/assets/pm-board/pm-board.js` trước khi commit.

## Roadmap đang hoạt động

- `khach-hang-don-hang-roadmap.md`: hoàn thành toàn bộ 3 giai đoạn.
- `tai-khoan-phan-quyen-roadmap.md`: hoàn thành toàn bộ 12 task IAM và enhancement (Task 4.1–4.12).

## Files tạo mới

- `docs/project-management-board.html`
- `docs/assets/pm-board/pm-board.css`
- `docs/assets/pm-board/pm-board.js`
- `docs/task-logs/project-management-dashboard.md`
- `docs/task-logs/tai-khoan-phan-quyen-roadmap.md`
- `AGENTS.md` — quy tắc đồng bộ roadmap, dashboard, activity và kết quả test.

