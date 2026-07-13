# Handoff #4 cho opencode — ZenaManage (2026-07-13)

Đợt 3 đã review: **đạt có điều kiện** — phát hiện 1 lỗi hồi quy CI phải sửa NGAY (Task L). Ràng buộc handoff #1 vẫn áp dụng. Branch: `worktree-zena-project-model-consolidation` (PR #163).

## Task L — KHẨN: sửa 12 lỗi PHPStan đang làm CI đỏ (làm đầu tiên, trước mọi thứ khác)

**Hiện trạng:** `vendor/bin/phpstan analyse --memory-limit=1G` đang exit khác 0 với **12 lỗi** trong `database/factories/ZenaSubmittalFactory.php` và `database/seeders/ZenaRbacSeeder.php` (dạng `Call to an undefined static method App\Models\Project::find()` và tương tự). Vì K2 đã bật enforce, CI giờ chặn mọi merge.

**Nguyên nhân gốc (đã trace):** trình tự K2 → I gây lệch — baseline được regenerate KHI code còn gọi `ZenaProject::find()` (lỗi nằm trong baseline dưới symbol đó); Task I sau đó đổi symbol sang `Project::find()` nên baseline không khớp nữa. Không có larastan nên PHPStan không hiểu magic static của Eloquent.

**Cách sửa BẮT BUỘC** (không được thêm baseline entry, không `@phpstan-ignore`, không thêm `@method` docblock):
1. Chạy `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` để có danh sách 12 lỗi chính xác (file:line).
2. Với từng chỗ: đổi magic static sang query builder — `Project::find($id)` → `Project::query()->find($id)`, `Project::where(...)` → `Project::query()->where(...)`, v.v. Đây là idiom chuẩn của repo (xem các model khác đều dùng `Model::query()->...`).
3. Chạy lại phpstan toàn bộ: kỳ vọng **[OK] No errors** và exit 0.
4. Chạy test 2 file bị sửa gián tiếp: `php artisan test --filter=Submittal` và bất kỳ test nào seed `ZenaRbacSeeder` (grep để tìm).
5. Commit `fix(phpstan): replace magic static calls introduced by ZenaProject migration`.

**Bài học ghi nhớ cho các đợt sau:** sau BẤT KỲ commit nào đổi code PHP, nếu PHPStan đã enforce thì phải chạy lại phpstan trước khi push — thêm điều này vào checklist kiểm chứng chuẩn của bạn, ngang hàng với chạy test.

## Task M — Thực thi dọn thư mục gốc (CHỈ khi user đã duyệt trong tin nhắn giao việc)

Nếu user duyệt `docs/change-proposals/2026-07-13-repo-root-cleanup.md`:
1. Thực hiện theo đúng thứ tự trong mục "Proposed Actions" của proposal, MỖI category một commit riêng (xóa cookies/test_output trước, rồi scripts, rồi archive .md).
2. Trước mỗi `git rm`/`git mv`: chạy lại grep tham chiếu (composer.json, package.json, .github/workflows, Dockerfile*, docker-compose*, *.sh, phpunit.xml) cho chính file đó — proposal có thể sót.
3. Sau mỗi commit: `php artisan route:list > /dev/null && php artisan test tests/Feature/Architecture/` rồi mới commit tiếp. Sau toàn bộ: full Feature suite + **phpstan** (bài học Task L).
4. Cập nhật `.gitignore` theo mục 6 của proposal.

Nếu user CHƯA duyệt — bỏ qua hoàn toàn.

## Task N — Giảm tham chiếu các alias Zena* còn lại (mechanical, như Task I đợt trước)

SSOT liệt kê các thin alias đóng băng: `ZenaTask`, `ZenaSubmittal`, `ZenaRfi`, `ZenaNotification`, `ZenaChangeRequest` (xem mục "Freeze thin model aliases" trong `docs/architecture/module-ownership-ssot.md`). Làm từng alias một, THEO ĐÚNG quy trình Task I đợt 3:

1. Kiểm kê tham chiếu alias đó (`grep -rln "App\\\\Models\\\\ZenaTask\b" app/ src/ tests/ database/ routes/ resources/`).
2. Chỉ migrate tham chiếu trong tests/factories/seeders. **Nếu alias được dùng trong code runtime (app/src/routes) — DỪNG alias đó, ghi lại, không migrate** (khác Task I: các alias này có thể còn sống trong runtime).
3. So sánh factory tương ứng trước khi đổi lời gọi factory. Không đụng migration lịch sử.
4. **Sau mỗi alias: chạy test các file đã sửa + PHPStan** (đây chính là loại thay đổi đã gây ra Task L — đừng lặp lại).
5. Cập nhật inventory/SSOT ghi chú số tham chiếu còn lại. Mỗi alias một commit `refactor(tests): migrate N files off <Alias>`.

## Task O — Xóa file KpiController/KpiService chết (nhỏ)

Bối cảnh: PR #162 (2026-07-12) đã gỡ 5 route trỏ tới `KpiController` mock (trả số liệu hardcode) nhưng không xóa được file vì phiên đó bị chặn quyền xóa. File vẫn nằm trên đĩa, không được route nào trỏ tới.

1. Tìm file: `grep -rn "class KpiController\|class KpiService" app/ src/ --include="*.php" -l`.
2. Re-verify dead theo đủ 5 bước methodology (Route::, `require base_path`, providers, DI container bindings, tests). Chú ý: `LaunchChecklistService` từng tham chiếu ĐƯỜNG DẪN file KpiService trong một check tồn-tại-file — PR #162 đã repoint sang `BusinessKpiService.php`, xác nhận lại không còn tham chiếu đường dẫn cũ.
3. Nếu sạch: `git rm` + chạy architecture tests + full suite + phpstan. Nếu còn tham chiếu ở đâu đó: viết 3 dòng vào change-proposal thay vì xóa.
4. Commit `chore: remove unrouted mock KpiController/KpiService left from PR #162`.

## Thứ tự & kiểm chứng

L (bắt buộc trước) → M (nếu được duyệt) → N → O.

Checklist kiểm chứng chuẩn MỚI (áp dụng từ nay):
```bash
php artisan test tests/Feature/Architecture/
php artisan test --testsuite=Feature
vendor/bin/phpstan analyse --memory-limit=1G --no-progress   # exit 0 bắt buộc
```
