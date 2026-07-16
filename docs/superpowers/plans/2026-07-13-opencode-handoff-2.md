# Handoff #2 cho opencode — ZenaManage (2026-07-13)

Đợt 1 (`docs/superpowers/plans/2026-07-13-opencode-handoff.md`) đã được review và xác nhận đạt — cả 4 task A-D. **Mọi ràng buộc cứng, gotchas, và quy trình TDD trong file handoff #1 vẫn áp dụng nguyên vẹn cho đợt này** — đọc lại nó trước khi bắt đầu.

Branch: `worktree-zena-project-model-consolidation` (PR #163), HEAD hiện tại `1e7d4473`.

## Task E — UI cho block/unblock (làm trước, gap thật đang chặn người dùng)

Đợt trước đã tạo endpoint + test cho block/unblock nhưng **chưa có nút nào trên giao diện** — operator không thể dùng tính năng này từ trình duyệt. Routes đã tồn tại:

- `operator.design-items.block` / `operator.design-items.unblock` (POST `/design-items/{id}/(un)block`, rbac `design-item.manage`)
- `app.tasks.block` / `app.tasks.unblock` (POST `/tasks/{task}/(un)block`, rbac `task.update`)

Việc cần làm (TDD — test assert nội dung render trước):

1. **`resources/views/design-items/show.blade.php`**: thêm khối "Vướng mắc" sau card "Thông tin":
   - Nếu `$item->blocked_at`: banner đỏ hiển thị `blocker_note`, ngày block, và form POST tới `operator.design-items.unblock` (nút "Gỡ vướng"), bọc trong `@if(auth()->user()?->hasPermission('design-item.manage'))`.
   - Nếu chưa block: form nhỏ (textarea `blocker_note` bắt buộc, max 1000 + nút "Báo vướng") POST tới `operator.design-items.block`, cùng điều kiện quyền.
   - Nhớ `@csrf` trong mọi form. Style theo pattern có sẵn trong file (x-ui.card, operator-input, các badge đỏ `bg-red-100 text-red-800` như partial `projects/_design-progress.blade.php`).
2. **Trang chi tiết Task**: tìm view mà `Web\TaskController::show()` render (đọc method để lấy tên view chính xác — đừng đoán), thêm khối tương tự với route `app.tasks.block|unblock`, quyền `task.update`.
3. **Badge trên danh sách**: thêm badge "Vướng" (đỏ) cạnh status trong `resources/views/design-items/index.blade.php` và view danh sách task tương ứng khi `blocked_at` khác null.
4. Test mới `tests/Feature/Zena/BlockerUiTest.php`: (a) trang design-item bị block hiển thị note + nút "Gỡ vướng"; (b) trang chưa block hiển thị form "Báo vướng"; (c) user không có quyền không thấy form (vẫn thấy badge); (d) tương tự cho task show. Setup copy từ `tests/Feature/Zena/BlockerTest.php` (nhớ `$this->get('/login');`).
5. Regression: `php artisan test tests/Feature/Zena/BlockerTest.php --filter=DesignItem` và `--filter=Task`.

## Task F — Chuẩn bị số liệu cho quyết định PHPStan (CHỈ thu thập, KHÔNG quyết)

User sẽ tự quyết chính sách; nhiệm vụ của bạn là làm số liệu sạch:

1. Chạy PHPStan fresh: `vendor/bin/phpstan analyse --memory-limit=1G 2>&1 | tail -30` (nếu thiếu RAM/timeout, chạy theo thư mục: `app/`, `src/`, `routes/`).
2. Ghi nhận: tổng lỗi mới NGOÀI baseline hiện tại; phân bố theo thư mục (app vs src vs tests); 10 rule bị vi phạm nhiều nhất.
3. Viết `docs/engineering/phpstan-enforcement-options.md` trình bày trung lập 3 phương án: (A) regenerate baseline + flip `continue-on-error: false` để chặn lỗi MỚI; (B) giữ advisory, ghi rõ vào README CI; (C) trả nợ dần theo module. Mỗi phương án: effort ước tính, rủi ro, việc phải làm. KHÔNG sửa `.github/workflows/*` — file đó chờ user quyết.
4. Commit `docs(engineering): ...`.

## Task G — Đề xuất xóa 10 file DEAD đã trace (CHỈ viết đề xuất, KHÔNG xóa)

Inventory giờ có 10 file verdict `dead`. Lịch sử repo này từng có kết luận "dead" sai (nửa bảng reachability đầu tiên sai vì mount `require base_path`), nên việc xóa cần user duyệt từng file.

1. Với TỪNG file trong 10 file dead: re-verify độc lập theo methodology note của inventory (Route:: + require base_path + event providers + composer autoload + test references). Ghi bằng chứng MỚI của bạn, không copy verdict cũ.
2. Viết `docs/change-proposals/2026-07-13-dead-project-model-files-removal.md`: bảng từng file — đường dẫn, bằng chứng dead (file:line từng mount đã kiểm), rủi ro còn lại, lệnh `git rm` tương ứng. Kết bằng câu: "Chưa xóa file nào — chờ user duyệt."
3. Nếu bất kỳ file nào hóa ra KHÔNG dead → sửa lại inventory + ghi rõ trong đề xuất. Đây là kết quả giá trị, không phải thất bại.
4. Commit `docs(change-proposals): ...`.

## Thứ tự & kiểm chứng

Làm E → F → G. Sau mỗi task: commit riêng, push. Trước commit cuối:

```bash
php artisan test tests/Feature/Architecture/    # phải pass
php artisan test --testsuite=Feature            # baseline mới sau khi tách nhóm performance: ~938-960 passed / vài skipped — ghi lại con số bạn đo được
```

Nếu gặp điều mâu thuẫn với handoff #1, hoặc một bước cần quyết định chính sách — DỪNG, ghi lại câu hỏi, không tự quyết.
