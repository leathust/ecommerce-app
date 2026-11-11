# Roadmap nâng cấp Menu động tối đa 4 cấp

## 1. Mục tiêu

- Nâng module Menu động hiện tại từ tối đa 2 cấp lên tối đa 4 cấp.
- Giữ nguyên contract location `header`/`footer`, route allowlist, URL security, locale, cache, audit và RBAC đã hoàn thành ở phase trước.
- Cho phép quản trị viên tạo, sửa, di chuyển và sắp xếp cây nhiều cấp mà không tạo cycle hoặc vượt độ sâu.
- Desktop, mobile và footer render cùng một cây published; khác nhau ở markup/trải nghiệm tương tác.
- Không phá dữ liệu menu 2 cấp hiện có và không yêu cầu migration nếu adjacency list `parent_id` hiện tại đã đủ.

## 2. Quyết định phạm vi

- Giới hạn cứng: tối đa `4` cấp, root là cấp 1.
- Không triển khai cây vô hạn hoặc mega-menu kéo thả tự do trong phase này.
- Schema tiếp tục dùng adjacency list `menus.parent_id`; depth được tính từ cây, không lưu cột `depth` để tránh sai lệch dữ liệu.
- Một nhánh được phép di chuyển khi `depth mới của parent + chiều cao nhánh <= 4`.
- Không được chọn chính item hoặc bất kỳ hậu duệ nào làm parent.
- Parent và child phải cùng location.
- Khi ancestor bị tắt, toàn bộ descendant bị loại khỏi published tree nhưng trạng thái database của descendant không bị sửa.
- Xóa node chỉ hợp lệ khi node không còn child; không cascade ngầm.
- Reorder chỉ hoạt động trong một sibling scope; thao tác đổi parent là mutation riêng có validation đầy đủ.

## 3. Rủi ro chính

- Query đệ quy không kiểm soát có thể gây N+1; published tree phải eager-load có giới hạn tới cấp 4.
- Di chuyển một node có thể làm cả nhánh vượt depth; phải tính chiều cao subtree trước transaction.
- Submenu desktop cấp 3–4 có thể tràn viewport; cần hướng mở trái/phải và kiểm tra responsive.
- Root pagination không được tách descendant sang trang khác.
- Keyboard navigation nhiều cấp phức tạp hơn hover; phải duy trì focus, Escape và `aria-expanded` nhất quán.

## 4. Kế hoạch triển khai

### [x] Task 7.1 — Chốt contract cây Menu 4 cấp

**Dependency:** Phase Dynamic Storefront Menus 6.1–6.8 đã hoàn thành.

- [x] Rà soát toàn bộ guard đang giả định parent chỉ là root.
- [x] Chốt cách tính depth, subtree height và điều kiện di chuyển nhánh.
- [x] Chốt published DTO đệ quy và chiến lược eager-load tối đa 4 cấp.
- [x] Chốt UX admin cho indent, breadcrumb parent và lựa chọn parent hợp lệ.
- [x] Chốt hành vi desktop/mobile/footer ở cấp 3–4.
- [x] Chốt test matrix và tiêu chí hiệu năng/query.

**Kết quả cần đạt:** backend, admin và storefront dùng cùng định nghĩa depth/cycle/move; không diễn giải khác nhau.

**Kế hoạch test:** static review schema/service/view, route/config và test hiện hữu; chưa thay đổi runtime.

#### Kết quả Task 7.1

##### 1. Inventory giả định hai cấp hiện tại

| Lớp | Giả định hiện tại cần thay ở task sau |
|---|---|
| `config/menus.php` | `max_depth = 2` |
| `MenuService::assertValidParent()` | Từ chối mọi parent có `parent_id`, nên parent bắt buộc là root |
| `MenuService::updateMenu()` | Node có child không thể nhận parent và không thể đổi location |
| `MenuService::published()` | Chỉ eager-load một relation `children`; hàm DTO có đệ quy nhưng dữ liệu cấp sâu chưa được load |
| `SaveMenuRequest` | Lặp lại guard parent phải là root, thông báo “tối đa hai cấp” |
| `AdminMenuController` | Chỉ eager-load/search `children`; parent selector chỉ lấy roots; sibling scope chỉ dựng cho root và child |
| Admin Blade | `index` chỉ lặp root rồi child; cảnh báo chỉ xét direct parent; form ghi rõ chỉ root được làm cha |
| Admin JavaScript | Root block chỉ gom row level 1; status warning và DOM move chỉ hiểu level 0/1 |
| Storefront Blade | Desktop/mobile/footer đều lặp children đúng một lần, chưa dùng partial đệ quy |
| Storefront CSS/JS | Chỉ định vị một submenu; toggle đóng/mở ở root, chưa quản lý nested focus/viewport |

Schema `menus.parent_id` tự tham chiếu đã biểu diễn được 4 cấp. Không cần migration hoặc cột `depth`; thay đổi nằm ở service/query/presentation và test.

##### 2. Contract depth, ancestor và subtree

- Root có `depth = 1`; child trực tiếp là 2; sâu nhất hợp lệ là 4.
- Leaf có `subtree_height = 1`; node có con bằng `1 + max(subtree_height của child)`.
- Tạo leaf dưới parent hợp lệ khi `parent.depth + 1 <= 4`.
- Di chuyển branch dưới parent mới hợp lệ khi `parent.depth + subject.subtree_height <= 4`.
- Đưa branch về root hợp lệ khi `subject.subtree_height <= 4`.
- Helper ancestor/subtree phải giữ tập ID đã thăm. Mutation gặp ID lặp phải từ chối như cycle; published tree gặp dữ liệu legacy lỗi phải dừng ở depth 4 và loại nhánh lỗi, không lặp vô hạn.
- Parent mới không được là subject hoặc bất kỳ descendant nào của subject.
- Parent mới phải cùng location với subject; create/update/move đều kiểm tra lại trong service transaction, không chỉ dựa vào FormRequest.
- Đổi location chỉ cho leaf. Branch có descendant không được cascade location ngầm; quản trị viên phải xử lý/move các node con trước.
- Xóa vẫn chỉ cho leaf. Tắt ancestor không sửa status descendant nhưng toàn bộ descendant không được publish.

##### 3. Contract mutation, lock và audit

- `updateMenu()` chỉ cập nhật metadata/link/status/sort order; parent/location không được đổi ngầm khi edit metadata.
- Tạo mutation riêng `moveMenu()` và endpoint PATCH dùng `menus.update` để đổi parent/location.
- Move transaction phải lock subject, parent mới, ancestor chain cần kiểm tra, descendants của subject và sibling scope nguồn/đích.
- Khi move thành công, subject nhận `sort_order` cuối scope đích theo `sort_step`; reorder sau đó có thể điều chỉnh vị trí.
- Audit action mới `menu.moved` ghi before/after gồm `parent_id`, `location`, `depth`, `sort_order`; không tạo audit row cho từng descendant vì branch không cascade location.
- Reorder tiếp tục dùng `menu.reordered`, nhưng `parent_id` có thể là node cấp 1–3. Payload vẫn phải chứa đúng toàn bộ sibling ID trong scope.
- Cache chỉ invalid sau transaction/audit thành công; rollback không xóa cache.

##### 4. Contract query và DTO

- Không dùng recursive SQL/CTE trong phase này để giữ tương thích MySQL/SQLite test và giới hạn nhỏ cố định.
- Published tree eager-load cố định: `children`, `children.children`, `children.children.children`, mỗi relation áp `active()->ordered()`.
- Cold-cache published query tối đa 4 query bảng `menus` cho một location/locale, không tăng theo số node; warm cache là 0 query bảng `menus`.
- Admin paginate theo root. Sau query count/root, eager-load descendants theo ba tầng để một root và toàn bộ subtree luôn cùng trang.
- Search root dùng relation condition đệ quy tối đa ba tầng; node khớp ở cấp 4 phải trả về root và render/mở ancestor path.
- DTO published giữ các field hiện tại và bổ sung `depth`; `children` luôn là array đệ quy. `active` của ancestor là `self_active || bất kỳ descendant active` và được tính sau cache.
- Container `none` chỉ render khi còn ít nhất một descendant branch hợp lệ; ancestor inactive cắt toàn bộ subtree.

##### 5. Contract UX admin

- Bảng dùng partial row đệ quy, indent theo `depth - 1`, badge `Cấp 1…4` và breadcrumb ancestor dạng `Root / … / Parent`.
- Root pagination giữ trọn subtree. Search/filter tự mở nhánh chứa kết quả; không tách descendant thành paginator riêng.
- Parent selector là danh sách phẳng có indent/breadcrumb, chỉ gồm node cùng location ở depth 1–3.
- Khi tạo: loại node cấp 4 khỏi parent options.
- Khi move: loại subject, toàn bộ subtree và mọi candidate làm `candidate.depth + subject.subtree_height > 4`.
- Edit metadata và Move branch là hai form/action tách biệt để audit và lỗi nghiệp vụ rõ ràng.
- Cảnh báo “ancestor đang ẩn” dựa trên toàn ancestor chain, không chỉ direct parent.
- Reorder row ở mọi level chỉ tác động sibling scope. Khi di chuyển root/branch trong DOM, block gồm row hiện tại và mọi row sau cho đến row có level nhỏ hơn hoặc bằng level hiện tại.

##### 6. Contract storefront cấp 3–4

- Desktop dùng partial đệ quy. Mỗi item có children phải có toggle riêng với `aria-expanded`/`aria-controls`; link và toggle không nhập làm một để link vẫn điều hướng được.
- Submenu cấp 2 mở xuống; cấp 3–4 mặc định mở sang phải và đổi class mở trái khi JS phát hiện tràn viewport.
- Tab đi theo thứ tự DOM; Enter/Space toggle; Escape đóng submenu sâu nhất và đưa focus về toggle. Click ngoài đóng toàn bộ.
- Mobile dùng `<details>/<summary>` đệ quy. Mở group không đóng drawer; chỉ click link đích mới đóng drawer. Active ancestor được mở mặc định.
- Footer root vẫn là cột; descendant cấp 2–4 dùng `<ul>` lồng và indent. Không biến footer thành desktop flyout.
- Cả ba renderer dùng cùng DTO, escape label, giữ `aria-current`, `_blank` kèm `noopener noreferrer` và không query trong Blade.

##### 7. Test matrix đã chốt

- Tree: tạo đủ cấp 1–4; từ chối cấp 5; depth/subtree height; self/cycle/descendant parent; khác location; dữ liệu legacy cycle.
- Move: leaf/branch về root hoặc parent mới; vượt depth; branch đổi location bị chặn; leaf đổi location; sibling order nguồn/đích; lock/rollback/audit/cache.
- Admin: parent options, breadcrumb/indent/level, search cấp 4, root pagination, ancestor hidden warning, reorder mọi level, 403/404/422 và HTML fallback.
- Storefront: DTO/markup 4 cấp, active descendant, locale, hidden ancestor, invalid link, XSS, target security, empty/fallback, desktop direction class, mobile nested details và keyboard contract.
- Hiệu năng: published cold cache không quá 4 menu query/location, warm cache 0; admin tree query count không tăng theo số node cùng depth.
- Regression: sáu suite Menu hiện tại, suite `MenuMultiLevelTest`, route list, migration status và full `php artisan test`.

##### 8. Kết luận

- Contract 4 cấp đã chốt mà không thay schema và không sửa runtime trong Task 7.1.
- Task 7.2 sẽ là nơi đầu tiên đổi `max_depth`, service/helper, recursive eager-load và test invariant.
- Baseline sáu suite Menu hiện hữu đạt `32 tests / 303 assertions`; `8` route admin Menu vẫn đủ authentication, trạng thái, admin và permission middleware.
- MySQL hiện tại có `21/21` migration ở trạng thái `Ran`; Task 7.1 không phát sinh migration hoặc command dữ liệu.
- `node --check docs/assets/pm-board/pm-board.js` đạt sau khi đồng bộ roadmap/dashboard.

### [x] Task 7.2 — Nâng cấp tree service và invariant 4 cấp

**Dependency:** Task 7.1.

- [x] Đổi `config('menus.max_depth')` từ 2 thành 4.
- [x] Xây helper tính ancestors, depth và subtree height có guard cycle dữ liệu legacy.
- [x] Cho phép parent ở cấp 1–3, cùng location và không thuộc subtree của subject.
- [x] Chặn create/update/move làm nhánh vượt cấp 4.
- [x] Dựng published tree đệ quy tối đa 4 cấp, lọc ancestor inactive và link không an toàn.
- [x] Giữ active state tính sau cache và invalid cache sau transaction thành công.

**Kết quả cần đạt:** service là nguồn sự thật duy nhất cho cây 4 cấp và chống dữ liệu bất hợp lệ.

**Kế hoạch test:** depth 1–4, từ chối cấp 5, cycle/self/descendant, khác location, ancestor inactive, corrupt legacy cycle và query count.

#### Kết quả Task 7.2

- `MenuService` hiện là nguồn kiểm tra cuối cho depth, ancestor, subtree height, cycle, location và giới hạn cấp 4; dữ liệu legacy có cycle hoặc orphan parent bị từ chối thay vì lặp vô hạn.
- Tạo/cập nhật cho phép parent cấp 1–3; cấp 5, self-parent, descendant-parent, khác location và branch overflow đều bị chặn trong transaction.
- Published tree eager-load cố định `children` tới ba tầng con, trả DTO có `depth`, loại ancestor inactive và không phát sinh query theo số node.
- Cold cache dùng tối đa 4 query bảng `menus` cho một location; lần đọc warm cache không query lại bảng `menus`.
- Cache namespace được tăng từ `menus.published.v1` lên `menus.published.v2` để không tái sử dụng DTO hai cấp sau deploy.
- Test mục tiêu `MenuMultiLevelTest + MenuServiceTest`: `12 passed / 76 assertions`.
- Toàn bộ 7 suite Menu: `37 passed / 332 assertions`; full regression: `238 passed / 2545 assertions`.
- Không có migration mới và không cần chạy command dữ liệu. Admin tree UI và storefront renderer vẫn được nâng cấp riêng ở Task 7.3–7.5.

### [x] Task 7.3 — Giao diện quản trị cây 4 cấp

**Dependency:** Task 7.2.

- [x] Hiển thị descendant đệ quy với indent, level badge và đường dẫn ancestor dễ đọc.
- [x] Parent selector hiển thị node hợp lệ tới cấp 3, loại subject/subtree/sai location.
- [x] Cảnh báo trước khi lựa chọn parent làm nhánh vượt depth.
- [x] Giữ root pagination nhưng render nguyên cây descendant của từng root.
- [x] Search/filter trả root chứa node khớp và mở đúng nhánh.
- [x] Giữ accessibility, permission và Bootstrap confirm conventions hiện tại.

**Kết quả cần đạt:** admin hiểu vị trí từng node và tạo/sửa cây 4 cấp không nhầm parent.

**Kế hoạch test:** form options, indent/level, search descendant sâu, pagination root, validation 403/404/422 và responsive admin.

#### Kết quả Task 7.3

- Danh sách admin render recursive partial tới cấp 4, indent theo level, có badge `Cấp 1…4`, breadcrumb đầy đủ và cảnh báo khi bất kỳ ancestor nào đang ẩn.
- Root tiếp tục là đơn vị phân trang; toàn bộ descendant đã eager-load và luôn ở cùng trang với root.
- Tìm kiếm tên/route/URL và lọc trạng thái kiểm tra cả ba tầng descendant; root cùng toàn bộ ancestor path vẫn được render khi node cấp 4 khớp.
- Parent selector dùng metadata cây tính một lần trong bộ nhớ, hiển thị breadcrumb/indent, loại subject, subtree, dữ liệu cycle/orphan và candidate làm vượt cấp 4.
- Form hiển thị cấp dự kiến cùng cấp sâu nhất của nhánh; FormRequest gọi lại invariant của `MenuService`, nên không tin dữ liệu option phía trình duyệt.
- JavaScript reorder đã nhận diện cả branch theo level; cảnh báo ancestor được tính lại sau AJAX status toggle ở mọi cấp.
- Test mục tiêu `AdminMenuTest + MenuMultiLevelTest`: `14 passed / 138 assertions`.
- Toàn bộ 7 suite Menu: `37 passed / 351 assertions`; full regression: `238 passed / 2564 assertions`.
- Không có route hoặc migration mới. Di chuyển branch nguyên tử thuộc Task 7.4; storefront recursive renderer thuộc Task 7.5.

### [x] Task 7.4 — Di chuyển nhánh và reorder nhiều cấp

**Dependency:** Task 7.2–7.3.

- [x] Reorder hỗ trợ mọi sibling scope ở cấp 1–4.
- [x] Tách thao tác move branch sang parent mới khỏi reorder thuần.
- [x] Lock subject, parent mới và sibling scope trong transaction.
- [x] Validate subtree height trước khi đổi parent/location.
- [x] Ghi một audit event rõ before/after cho move; giữ audit reorder theo scope.
- [x] AJAX có loading/error/live feedback và HTML fallback hoạt động.

**Kết quả cần đạt:** di chuyển/sắp xếp nhánh nguyên tử, không mất node, không cycle và không vượt depth.

**Kế hoạch test:** move cùng/khác parent, đổi location hợp lệ/bị chặn, subtree depth, concurrent-like stale payload, audit, rollback và cache.

#### Kết quả Task 7.4

- Thêm mutation `moveMenu()` riêng; form cập nhật metadata không còn được phép đổi ngầm `parent_id` hoặc `location`.
- Move transaction lock subject, descendants, ancestor chain và sibling scope nguồn/đích; stale source payload bị từ chối và yêu cầu tải lại.
- Branch được chuyển nguyên khối khi cùng location và không vượt cấp 4; chỉ leaf được đổi giữa `header`/`footer`.
- Node sau move được đặt cuối scope đích theo `sort_step`; reorder tiếp tục nhận full sibling scope ở mọi cấp.
- Audit `menu.moved` ghi before/after gồm parent, location, depth và sort order. Audit thất bại rollback database và không invalid cache.
- Admin có nút/modal Di chuyển dùng AJAX với loading/error feedback; link mở trang di chuyển riêng là HTML fallback khi không có JavaScript.
- Parent option loại self, descendant, sai location của branch và candidate làm subtree vượt depth; request vẫn được service kiểm tra lại.
- Thêm `GET/PATCH admin/menus/{menu}/move`; tổng cộng `10` route Menu đều có authentication, active, admin và permission middleware.
- Test mục tiêu `AdminMenuTest + MenuServiceTest + MenuMultiLevelTest`: `24 passed / 228 assertions`.
- Toàn bộ 7 suite Menu: `40 passed / 389 assertions`; full regression: `241 passed / 2602 assertions`.
- Không có migration mới hoặc command dữ liệu cần chạy.

### [x] Task 7.5 — Renderer storefront đệ quy 4 cấp

**Dependency:** Task 7.2.

- [x] Tạo partial/component đệ quy dùng chung contract cho desktop, mobile và footer.
- [x] Desktop submenu cấp 2–4 có hướng mở phù hợp, không tràn viewport phổ biến.
- [x] Mobile dùng accordion/details lồng nhau, không đóng drawer khi chỉ mở một group.
- [x] Duy trì `aria-expanded`, focus, Escape, active ancestor và `_blank` security.
- [x] Footer render nhiều cấp theo cách không phá layout; giới hạn trình bày nếu cần được ghi rõ.
- [x] Không tăng query theo số node; dùng published cache theo location/locale.

**Kết quả cần đạt:** một cây dữ liệu render nhất quán ở mọi breakpoint tới cấp 4.

**Kế hoạch test:** markup 4 cấp, locale, active descendant, hidden ancestor, keyboard contract, XSS/target, empty state và query/cache.

**Kết quả triển khai:**

- Desktop, mobile và footer dùng các partial đệ quy riêng nhưng cùng published DTO; hỗ trợ đầy đủ depth 1–4 và fallback cũng mang metadata `depth`.
- Desktop mở submenu lồng sang phải hoặc tự đổi sang trái khi gần mép viewport; toggle duy trì `aria-expanded`, đóng bằng click ngoài/Escape và trả focus về nút vừa đóng.
- Mobile dùng `details/summary` lồng nhau, tự mở ancestor của trang active và không đóng drawer khi người dùng chỉ mở một group; footer hiển thị cây sâu bằng indent/border nhẹ.
- Link `_blank` giữ `noopener noreferrer`; active descendant, locale fallback, hidden ancestor, empty state và escaping tiếp tục dùng contract published hiện có.
- Test mục tiêu: `15 passed / 134 assertions`; toàn bộ 7 suite Menu: `41 passed / 413 assertions`; full regression: `242 passed / 2626 assertions`.
- Không có migration, route hoặc command dữ liệu mới cần chạy.

### [x] Task 7.6 — Seeder, dữ liệu hiện hữu và hướng dẫn vận hành

**Dependency:** Task 7.2–7.5.

- [x] Xác nhận menu 2 cấp hiện tại tiếp tục hợp lệ mà không migration dữ liệu.
- [x] Mở rộng test seeder để bảo đảm idempotency với cây sâu nếu defaults phát sinh sau này.
- [x] Viết hướng dẫn kiểm tra depth/cycle trước và sau deploy.
- [x] Ghi quy trình rollback code mà không xóa menu cấp 3–4 đã tạo.
- [x] Cập nhật README và tài liệu kiến trúc về cây 4 cấp.

**Kết quả cần đạt:** nâng cấp an toàn trên MySQL hiện hữu và có phương án rollback không phá dữ liệu.

**Kế hoạch test:** existing DB compatibility, seeder hai lần, config/cache clear, dữ liệu cấp 3–4 sau rollback code và migration status.

**Kết quả triển khai:**

- `MenuSeeder` duyệt defaults đệ quy tới `menus.max_depth`; config vượt giới hạn làm rollback toàn transaction, không để lại cây được seed một phần.
- Location đã có dữ liệu tiếp tục được giữ nguyên tuyệt đối, gồm cả cây 2 cấp cũ và node cấp 3–4; không có migration schema hoặc data migration mới.
- Thêm `menus:check` chỉ đọc để báo cycle, parent thiếu, parent khác location và depth overflow bằng exit code, không tự sửa dữ liệu.
- README và tài liệu kiến trúc đã có trình tự pre/post deploy, cache clear và rollback code mà không xóa hoặc kéo node sâu lên root.
- MySQL hiện tại: `21/21` migration `Ran`; `23` menu, `9` root, depth lớn nhất `2`, không có lỗi invariant trước và sau `optimize:clear`.
- `MenuSeederTest`: `9 passed / 58 assertions`; toàn bộ 7 suite Menu: `46 passed / 432 assertions`; full regression: `247 passed / 2645 assertions`.

### [x] Task 7.7 — Quality gate Menu 4 cấp

**Dependency:** Task 7.1–7.6.

- [x] Bao phủ RBAC/revocation, CRUD, depth/cycle, move/reorder, audit/cache và seeder.
- [x] Bao phủ storefront desktop/mobile/footer tới cấp 4.
- [x] Kiểm tra XSS, URL/admin leakage, target security và mass assignment hồi quy.
- [x] Kiểm tra query count, root pagination và không N+1.
- [x] Chạy route list, migration status, module suites và full regression.
- [x] Đồng bộ roadmap, PM board, activities, decisions, risks và Test Center.

**Kết quả cần đạt:** module Menu 4 cấp sẵn sàng sử dụng, không phá menu 2 cấp hiện tại hoặc các module khác.

**Kế hoạch test:** toàn bộ suite Menu hiện có, suite multi-level mới và `php artisan test`.

**Kết quả triển khai:**

- Quality gate xác nhận RBAC/revocation tức thời, CRUD, tree invariant, move/reorder nguyên tử, audit rollback, cache invalidation và seeder đệ quy đều được bao phủ.
- Storefront desktop/mobile/footer render cùng cây tới cấp 4; locale, active ancestor, hidden branch, keyboard contract, `_blank` security và escaping đều đạt.
- Root pagination và published query count được kiểm soát; cache ấm không query lại bảng `menus`, không có N+1 theo số node.
- `10/10` route admin Menu có `auth`, active-account, admin và permission middleware đúng thao tác.
- MySQL hiện tại có `21/21` migration `Ran`; `menus:check` xác nhận `23` menu, `9` root, depth lớn nhất `2` và không có invariant lỗi.
- Toàn bộ 7 suite Menu: `46 passed / 432 assertions`; full regression: `247 passed / 2645 assertions`; PHP/JS/dashboard/diff checks đạt.
- Phase Menu 4 cấp hoàn tất `7/7`; không có migration hoặc command ghi dữ liệu mới cần chạy.

## 5. Dependency

```text
7.1 Contract 4 cấp
 └─ 7.2 Tree service + invariant
     ├─ 7.3 Admin tree UI
     │   └─ 7.4 Move + reorder nhiều cấp
     └─ 7.5 Storefront recursive renderer
         └─ 7.6 Seeder + operations
             └─ 7.7 Integration quality gate
```

## 6. File dự kiến chính

- `config/menus.php`
- `app/Models/Menu.php`
- `app/Services/MenuService.php`
- `app/Http/Controllers/AdminMenuController.php`
- `app/Http/Requests/*MenuRequest.php`
- `resources/views/admin/menus/*`
- `resources/views/frontend/includes/menu-*.blade.php`
- `public/assets/admin/js/menu-manager.js`
- `public/assets/frontend/css/styles.css`
- `public/assets/frontend/js/main.js`
- `database/seeders/MenuSeeder.php`
- `tests/Unit/MenuServiceTest.php`
- `tests/Feature/AdminMenuTest.php`
- `tests/Feature/StorefrontMenuTest.php`
- `tests/Feature/MenuMultiLevelTest.php`
- `README.md`
- `docs/kien-truc-he-thong.html`

## 7. Definition of Done

- Tạo và render được cây đủ 4 cấp; cấp 5 bị từ chối rõ ràng.
- Không thể tạo cycle, self-parent, descendant-parent hoặc vượt depth khi di chuyển nhánh.
- Root pagination luôn giữ nguyên subtree.
- Admin và storefront không N+1 theo số node.
- Desktop/mobile/footer dùng cùng published tree và cache contract.
- Permission, audit, security và fallback hiện tại không bị suy giảm.
- Module suites và full regression đều đạt.
- Roadmap và PM board đồng bộ đúng kết quả thực tế.
