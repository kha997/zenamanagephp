# Handoff #5 cho opencode — ZenaManage (2026-07-14)

Slice IPC đã được review: **đạt về chức năng** (lũy kế, lock rule, workflow, ContractPayment tự sinh đều xác minh đúng), nhưng có **1 finding vi phạm chính sách PHPStan phải sửa** (Task P). Ràng buộc handoff #1 vẫn áp dụng. Branch: `worktree-zena-project-model-consolidation` (PR #163), HEAD `42b2e1c3`.

## Task P — Gỡ việc nới baseline PHPStan (finding từ review IPC, làm trước)

**Vấn đề:** commit `fa16811c`/`33c9872b` đã TĂNG count trong `phpstan-baseline.neon` cho 2 pattern ở `ContractPageController.php` (`auth()->id()`: 1→4, `auth()->user()`: 6→15) để nuốt 12 chỗ dùng helper `auth()` trong code MỚI. Chính sách phương án A là "lỗi mới phải chặn" — tăng count baseline chính là baseline hóa lỗi mới, không được phép. (Việc thêm `@property` docblock cho model thì HỢP LỆ — giữ nguyên, đừng revert nhầm.)

**Quy tắc từ nay (ghi nhớ vĩnh viễn):** count trong `phpstan-baseline.neon` KHÔNG BAO GIỜ được tăng. Code mới phải sạch; count chỉ được giảm.

**Cách sửa:**
1. Trong `phpstan-baseline.neon`, trả 2 count về giá trị gốc: `auth()->id()` count 1, `auth()->user()` count 6 (path `app/Http/Controllers/Web/ContractPageController.php`).
2. Chạy `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` — sẽ liệt kê chính xác các dòng mới vi phạm.
3. Sửa từng chỗ trong các method MỚI (storeExpense, deleteExpense, block/unblock nếu có, 8 method IPC): thay helper `auth()->user()` → `\Illuminate\Support\Facades\Auth::user()` và `auth()->id()` → `\Illuminate\Support\Facades\Auth::id()` (facade có type đúng, không dính lỗi `Contracts\Auth\Factory`). Method nào đã nhận `Request $request` thì dùng `$request->user()` càng tốt. KHÔNG sửa các method cũ (6+1 chỗ gốc nằm trong baseline hợp lệ).
4. phpstan exit 0 với baseline count gốc → chạy `php artisan test tests/Feature/Zena/PaymentCertificateFlowTest.php tests/Feature/Zena/ContractExpenseEndpointsTest.php tests/Feature/Zena/BlockerTest.php` → PASS.
5. Commit `fix(phpstan): remove baseline count inflation — new code uses typed Auth facade`.

## Task Q — Đồng bộ tài liệu kiến trúc với thực tế sau các đợt 4-5

Tài liệu đang kể chuyện cũ ở vài chỗ:
1. `docs/architecture/project-model-reference-inventory.md`: mục ZenaProject (14 files) — cập nhật còn lại thực tế (class + factory + migration; xác minh bằng grep). Mục 10 file dead — đánh dấu "REMOVED 2026-07-13 (commit 272ba5fe)".
2. `docs/change-proposals/2026-07-13-dead-project-model-files-removal.md` và `2026-07-13-repo-root-cleanup.md`: đổi Status đầu file thành "EXECUTED" kèm commit hash tương ứng.
3. `docs/architecture/module-ownership-ssot.md`: các dòng nói alias "remains in tests" cho ZenaTask/ZenaSubmittal/ZenaRfi/ZenaNotification/ZenaChangeRequest/ZenaProject — cập nhật hiện trạng (alias còn tồn tại nhưng không còn tham chiếu test; grep xác minh từng cái trước khi viết).
4. Commit `docs(architecture): sync inventory, SSOT and proposals with executed batches`.

## Task R — Đề xuất khai tử các alias Zena* (CHỈ đề xuất, KHÔNG xóa)

Sau Task N đợt 4, các alias có thể đã hết người dùng. Với TỪNG class trong: `ZenaProject`, `ZenaTask`, `ZenaSubmittal`, `ZenaRfi`, `ZenaNotification`, `ZenaChangeRequest`, `ZenaPermission`, `ZenaRole` (+ factory tương ứng nếu có):
1. Đếm tham chiếu còn lại (grep cả `app/ src/ tests/ database/ routes/ resources/ config/`), phân loại: tự thân / factory / migration lịch sử / khác.
2. Đối chiếu chính sách freeze trong SSOT — SSOT yêu cầu "reference inventory and tests prove removal safe" trước khi xóa alias.
3. Viết `docs/change-proposals/2026-07-14-zena-alias-retirement.md`: bảng từng class → refs còn lại → verdict (safe to delete / keep vì X) → lệnh `git rm` đề xuất. Migration lịch sử tham chiếu alias thì alias đó PHẢI GIỮ (migration không được sửa) — ghi rõ. Kết: "Chưa xóa gì — chờ user duyệt."
4. Commit `docs(change-proposals): ...`.

## Kiểm chứng chuẩn sau MỖI task (báo cáo cuối kèm 3 con số lần chạy cuối)

```bash
php artisan test tests/Feature/Architecture/     # baseline 29
php artisan test --testsuite=Feature             # baseline 884
vendor/bin/phpstan analyse --memory-limit=1G     # exit 0, KHÔNG tăng count baseline
```
