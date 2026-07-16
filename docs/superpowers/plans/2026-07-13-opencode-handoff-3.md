# Handoff #3 cho opencode — ZenaManage (2026-07-13)

Đợt 2 đã được review: **đạt**, với 2 thiếu sót nhỏ được ghi ở Task H. Mọi ràng buộc trong handoff #1 (`2026-07-13-opencode-handoff.md`) vẫn áp dụng nguyên vẹn. Branch: `worktree-zena-project-model-consolidation` (PR #163).

## Task H — Sửa 2 thiếu sót của đợt 2 (nhỏ, làm trước)

1. **Badge "Vướng" thiếu trên danh sách task**: đợt 2 chỉ thêm badge vào `resources/views/design-items/index.blade.php`. View danh sách task thật là `resources/views/tasks/index.blade.php` (render bởi `Web\TaskController::index`, dòng 46-48). Thêm badge đỏ `Vướng` (pattern y hệt design-items index: `rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800`, điều kiện `$task->blocked_at`) cạnh cột status. Bổ sung 1 test case vào `tests/Feature/Zena/BlockerUiTest.php` assert badge xuất hiện trên trang danh sách task khi có task bị block.
2. **Mâu thuẫn nội bộ trong proposal**: `docs/change-proposals/2026-07-13-dead-project-model-files-removal.md` phần Summary ghi "10 files" nhưng bảng liệt kê và lệnh `git rm` có **11** file. Sửa Summary thành 11 kèm 1 câu giải thích file thứ 11 (`RecalculateProjectRollupJob`) vào diện nào (nó nằm ngoài 10 file verdict-dead của inventory hay là 1 trong 10 — đối chiếu lại với `docs/architecture/project-model-reference-inventory.md` và làm hai tài liệu nhất quán với nhau). Cũng sửa typo đường dẫn `app/routes/api_zena.php` → `routes/api_zena.php`.

Commit: `fix(ui): ...` và `docs(change-proposals): ...` riêng.

## Task I — Giảm tham chiếu alias `App\Models\ZenaProject` (cơ học, chỉ test/factory)

Theo `docs/architecture/project-model-reference-inventory.md`, alias rỗng `ZenaProject` còn 14 tham chiếu, toàn bộ trong tests/factories/seeders/1 migration lịch sử. SSOT quy định alias phải "freeze" — giảm tham chiếu là đúng hướng, **không xóa class**.

1. Với từng file test/factory/seeder trong danh sách 14 (mục "`App\Models\ZenaProject` (frozen thin alias — 14 files)" của inventory): đổi import/usage sang `App\Models\Project`. **KHÔNG đụng file migration lịch sử** (`2025_09_15_144442_unify_projects_table_schema.php`) — migration đã chạy, giữ nguyên.
2. `ZenaProjectFactory`: kiểm tra xem `Project::factory()` đã tồn tại (`database/factories/ProjectFactory.php` — có) — các test đang gọi `ZenaProject::factory()` chuyển sang `Project::factory()`. Nếu 2 factory có default attributes khác nhau, giữ nguyên hành vi test bằng cách truyền override tường minh; so sánh 2 factory TRƯỚC khi đổi.
3. Sau mỗi file: chạy chính file test đó. Sau tất cả: chạy `php artisan test --testsuite=Feature`.
4. Cập nhật inventory: mục ZenaProject còn lại những file nào (kỳ vọng: class + factory + migration).
5. KHÔNG xóa `app/Models/ZenaProject.php` và KHÔNG xóa `ZenaProjectFactory.php` trong đợt này — nếu sau bước 4 chúng hết người dùng, ghi 1 dòng đề xuất xóa vào cuối change-proposal của Task H.2.
6. Commit theo lô: `refactor(tests): migrate N test files off ZenaProject alias`.

## Task J — Kiểm kê rác thư mục gốc repo + đề xuất lưu trữ (CHỈ đề xuất, KHÔNG di chuyển/xóa)

Thư mục gốc repo có hàng trăm file `.md` report một lần (`*_REPORT.md`, `*_SUMMARY.md`, `PHASE*_*.md`...) và script sửa lỗi một lần (`fix_*.php`, `*.php.disabled`, `test_output_*.txt`, `cookies*.txt`...). Chúng gây nhiễu cho mọi lần khám phá repo.

1. Kiểm kê + phân loại: (a) tài liệu sống cần giữ ở gốc (README, DEPLOYMENT_GUIDE, docker, composer...), (b) report lịch sử → đề xuất `git mv` vào `docs/archive/reports/`, (c) script one-shot đã hết vai trò → đề xuất `git mv` vào `scripts/archive/` hoặc xóa, (d) file nghi là secret/rác tuyệt đối (`cookies*.txt`) → đề xuất xóa + kiểm tra có bị commit nội dung nhạy cảm không (nếu có, ghi rõ để user cân nhắc rotate).
2. TRƯỚC khi xếp file nào vào (b)/(c): grep tên file đó trong `composer.json`, `package.json`, `.github/workflows/`, `Dockerfile*`, `docker-compose*`, `*.sh`, `phpunit.xml` — file được tham chiếu thì để nguyên và ghi chú.
3. Viết `docs/change-proposals/2026-07-13-repo-root-cleanup.md`: bảng đầy đủ từng file → phân loại → lệnh đề xuất. Kết bằng "Chưa di chuyển/xóa gì — chờ user duyệt."
4. Commit `docs(change-proposals): ...`.

## Task K — CHỈ THỰC HIỆN NẾU user đã trả lời duyệt trong tin nhắn giao việc

- **K1 (nếu user duyệt xóa dead files):** thực hiện 11 `git rm` theo proposal, chia 2 commit: commit 1 = 7 file dưới `app/`; chạy `php artisan test tests/Feature/Architecture/ && php artisan test --testsuite=Feature` xanh rồi mới commit 2 = 4 file dưới `src/CoreProject/{Middleware,Jobs}` + chạy lại cả hai lệnh. Sau đó cập nhật allowlist trong `ProjectModelReferenceAllowlistTest` và inventory. Bất kỳ test nào đỏ → revert commit đó ngay, ghi lại, không cố sửa tiếp.
- **K2 (nếu user chọn phương án PHPStan A):** regenerate baseline theo đúng các lệnh trong `docs/engineering/phpstan-enforcement-options.md`, flip `continue-on-error: false` cho 2 job PHPStan trong workflow tương ứng, chạy phpstan local xác nhận exit 0, commit `ci(phpstan): ...`. Nếu user chọn B hoặc C — làm theo mô tả phương án đó trong doc.

Nếu user CHƯA duyệt mục nào, bỏ qua Task K hoàn toàn.

## Kiểm chứng chuẩn (như handoff #1)

```bash
php artisan test tests/Feature/Architecture/    # phải pass trước commit cuối
php artisan test --testsuite=Feature            # baseline hiện tại ~876 passed (sau khi thêm test đợt 2)
```
