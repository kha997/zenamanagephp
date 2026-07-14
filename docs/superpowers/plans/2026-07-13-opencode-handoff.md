# Handoff cho opencode — ZenaManage (2026-07-13)

Bạn (opencode) tiếp quản repo này. Đọc kỹ file này TRƯỚC KHI viết bất kỳ dòng code nào.

## Bối cảnh hiện tại

- Branch làm việc: `worktree-zena-project-model-consolidation` (PR #163, github.com/kha997/zenamanagephp). Đã push đến `538c6f5c`.
- Đã hoàn thành và XANH toàn bộ (full Feature suite 959 passed / 6 skipped):
  1. Hardening slice: throttle `ai-suggest` trên 2 endpoint AI; trait `TenantScope` trên `Lead/Account/Opportunity/DesignItem`; xóa 8 file route mồ côi.
  2. R-DPM (đã implement xong): revision history per DesignItem, blocker trên Task+DesignItem, section "Thiết kế & tiến độ" trên trang dự án. Plan: `docs/superpowers/plans/2026-07-13-design-pm-completion.md`.
  3. R-CTR (đã implement xong): `contract_type`, `ContractExpense`, khối "Tài chính hợp đồng", khối tiến độ theo loại HĐ, card rollup trên trang dự án. Plan: `docs/superpowers/plans/2026-07-13-contract-centric-management.md`.
- KHÔNG merge PR, KHÔNG force-push, KHÔNG rebase. Chỉ thêm commit mới lên branch này (hoặc branch mới nếu user yêu cầu).

## Ràng buộc cứng (vi phạm = hỏng hệ thống đang được test bảo vệ)

1. **KHÔNG sửa bất kỳ file nào dưới `src/CoreProject/{Controllers,Services,Listeners}`** và không đổi cách mount `/api/v1/*` — đây là compatibility runtime đóng băng có chủ đích, được khóa bởi `tests/Feature/Architecture/ModuleOwnership*InvariantTest.php` và `docs/architecture/module-ownership-ssot.md`.
2. **Không kết luận file nào là "dead code" chỉ bằng grep `Route::`** — `routes/api.php` mount 7 file route module bằng raw `require base_path(...)`. Phải grep `require base_path` trước. Phương pháp đầy đủ: `docs/architecture/project-model-reference-inventory.md`.
3. **Không thêm `rbac:*` middleware vào route mà controller đã authorize bằng Policy** — sẽ phá friendly-error UX và test hiện có. Trace chuỗi authorization (controller → Policy) trước khi "sửa" một route "thiếu" middleware.
4. **KHÔNG dùng `git stash` trần** — stash stack dùng chung nhiều session. Nếu cần tạm cất: WIP commit.
5. Model mới thuộc tenant: bắt buộc `HasUlids` + `App\Traits\TenantScope` + thêm vào guard list trong `tests/Feature/Models/TenantScopedCrmModelsTest.php`.

## Gotchas đã trả giá — đừng dẫm lại

- **`App\Models\Project::tasks()` trỏ về LEGACY `Src\CoreProject\Models\Task`** (không có relation `assignee`). `Web\ProjectController::show()` có try/catch nuốt mọi Throwable thành 404 — lỗi thật bị che. Load dữ liệu task qua `\App\Models\Task` trực tiếp.
- **`Web\TaskController` import `Src\CoreProject\Models\Task` làm alias `Task`** — trong file đó phải viết `\App\Models\Task` đầy đủ.
- **Test POST web-route bị 419**: `tests/TestCase.php` tự chèn `_token` nhưng cần session — thêm `$this->get('/login');` cuối `setUp()`.
- **`tests/Unit/Models/TaskTest` assert CHÍNH XÁC mảng `$fillable` của Task** — thêm cột mới vào Task thì phải cập nhật test này.
- Prefix tên route: nhóm operator là `operator.*` (contracts, design-items, crm), nhóm app là `app.*` (tasks, projects). Xác minh bằng `php artisan route:list | grep <path>` trước khi viết `route()` trong test.
- Test API dùng pattern `headersFor()` (Bearer token + `X-Tenant-ID`) — copy từ `tests/Feature/Api/DesignItemApiTest.php:404`.
- Môi trường local: warning `imagick`/`memcached` khi chạy artisan là quirk vô hại, bỏ qua.

## Quy trình bắt buộc cho mọi task

1. TDD: viết test fail trước → chạy xác nhận fail đúng lý do → code tối thiểu → test pass → chạy regression liên quan → commit.
2. Commit style: conventional (`feat(scope):`, `fix:`, `test:`, `chore:`, `docs:`), mỗi task một commit.
3. Trước commit cuối của phiên: `php artisan test tests/Feature/Architecture/` phải pass, và chạy `php artisan test --testsuite=Feature` một lần (flake đã biết: các test đo thời gian thực — chạy lại 1 lần trước khi nghi ngờ code mình).
4. Báo cáo trung thực: test fail thì nói fail kèm output, không claim "done" khi chưa chạy xác minh.

## Các task giao cho bạn, theo thứ tự ưu tiên

### Task A — R1: Quyết định tenant-scoping cho `Invitation` (nhỏ, làm trước)

`App\Models\Invitation` là model tenant-scoped duy nhất còn lại chưa có trait `TenantScope` (bị loại có chủ đích khỏi batch trước). Lý do phải trace: flow accept invitation có thể đọc invitation của tenant A trong khi user đang bind tenant B (user chưa thuộc tenant đích) — nếu đúng vậy, global scope sẽ làm invitation "biến mất" giữa chừng.

Các bước:
1. Đọc route accept (quanh `routes/web.php:475` — có middleware `auth, tenant.isolation, throttle:invitation-accept`) và controller xử lý nó; xác định mọi query `Invitation::` trong flow.
2. Trả lời: khi user tenant B accept lời mời vào tenant A, tại thời điểm query invitation, container đang bind tenant nào? (xem `TenantIsolationMiddleware` bind `app('tenant')` từ đâu).
3. Nếu binding là tenant của user hiện tại (khác tenant lời mời) → KHÔNG gắn trait; viết test chứng minh flow accept cross-tenant hoạt động, và ghi 3-4 dòng vào `docs/architecture/` giải thích vì sao Invitation được miễn. Nếu binding luôn là tenant đích → gắn trait + thêm vào guard test + chạy toàn bộ test invitation hiện có.
4. Commit `feat(security): ...` hoặc `docs(architecture): ...` tùy kết quả.

### Task B — R9: Kiểm kê và cách ly test đo thời gian thực (cơ học, an toàn)

~90+ assertion đo wall-clock là nguồn flake tiềm ẩn.

1. Kiểm kê: `grep -rn "assertLessThan\|assertGreaterThan" tests/ | grep -iE "time|duration|ms|seconds|micro"` (tinh chỉnh pattern khi chạy thật).
2. Phân loại từng chỗ: (a) đo hiệu năng thuần (giữ logic, chuyển sang nhóm riêng) vs (b) assertion chức năng tình cờ dùng thời gian (viết lại thành assertion chức năng).
3. Tạo PHPUnit group `@group performance` cho nhóm (a); thêm ghi chú vào `phpunit.xml` hoặc README testing về cách chạy riêng: `php artisan test --group=performance`.
4. KHÔNG xóa test nào. Mỗi file sửa: chạy lại chính file đó. Commit theo lô nhỏ `test(quality): ...`.

### Task C — R2: Thống nhất đường upload Document (trung bình, discovery trước)

Audit 2026-07-12 ghi nhận: 2 trong 4 đường upload Document còn dùng `Src\DocumentManagement\Models\LegacyDocumentAdapter` (subclass rỗng của `App\Models\Document`), và `document_type` được validate 3 kiểu khác nhau (enum 6 giá trị vs free text) trên cùng một cột.

1. Discovery TRƯỚC (không sửa gì): liệt kê đủ 4 đường upload (grep `LegacyDocumentAdapter` và các controller upload Document), ghi bảng: đường nào, model nào, validate `document_type` thế nào. Lưu thành `docs/architecture/document-upload-path-inventory.md`.
2. Canonical theo SSOT: `App\Models\Document` + `SimpleDocumentController`. Đổi các import `LegacyDocumentAdapter` → `App\Models\Document` (adapter là subclass RỖNG — xác minh lại nó vẫn rỗng trước khi đổi: không trait, không override).
3. Chọn validation chuẩn: dùng bộ enum chặt nhất đang có; các đường nhận free text thêm `Rule::in(...)` cùng bộ giá trị. NẾU dữ liệu production có thể chứa giá trị ngoài enum → chỉ siết cho request MỚI, không migrate dữ liệu cũ trong slice này.
4. Test: mỗi đường upload một test khẳng định validate nhất quán; chạy toàn bộ test Document hiện có (`php artisan test --filter=Document`).
5. Guard: thêm architecture test allowlist chặn import mới `LegacyDocumentAdapter` (mẫu: `tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php` — chú ý regex phải có word-boundary/negative-lookahead như file mẫu).

### Task D — R4: Trace tiếp 40 file tham chiếu `Src\CoreProject\Models\Project` (làm theo lô 10 file)

Chỉ TRACE và cập nhật tài liệu — KHÔNG migrate file nào trong task này.

1. Đọc `docs/architecture/project-model-reference-inventory.md` — mục "Not yet traced" là scope; mục methodology là quy trình bắt buộc.
2. Với mỗi file: xác định reachability (route mount nào dẫn tới? kể cả `require base_path`; event/listener provider nào đăng ký?) → verdict `live` / `dead` / `test-only` kèm bằng chứng (file:line của mount).
3. Cập nhật inventory doc (chạy lại `scripts/architecture/dump-project-model-references.sh` để đối chiếu số đếm). Commit `docs(architecture): trace batch N ...` mỗi lô 10 file.

### Ngoài khả năng / KHÔNG tự làm

- Quyết định PHPStan (R3) — cần user chốt chính sách; chỉ chuẩn bị số liệu nếu được hỏi.
- Merge PR, đổi CI, xóa file ngoài danh sách nêu trên, mọi thứ đụng `src/CoreProject` runtime.
- Feature lớn goal #2/#4/#6/#7 — cần brainstorm với user trước.

## Lệnh kiểm chứng chuẩn

```bash
php artisan test tests/Feature/Architecture/          # bất biến kiến trúc — phải pass trước commit cuối
php artisan test --filter=<Tên>                       # regression theo module
php artisan test --testsuite=Feature                  # full suite (~2 phút), baseline: 959 passed / 6 skipped
php artisan route:list | grep <path>                  # xác minh tên route trước khi viết test
```
