---
work_id: GAP-115
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-06-gap-115-deploy-readiness-evidence.md
  plan: docs/superpowers/plans/2026-11-06-gap-115-deploy-readiness.md
  branch: docs/GAP-115-deploy-readiness
  pr: https://github.com/kha997/zenamanagephp/pull/405
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-06T01:23:50+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-06, verbatim: 'APPROVE GAP-115 Gate 2 M-1 R-1 U-1 W-1 D-1'. Reviewed design head: 8cc3eeace5e4d60db631fb001bffcc946b8c2e1f. Approves M-1 (classify the 9 GAP-106..114 migrations as expand; test that every migration is classified), R-1 (production:rbac command: canonical roles, permission-only seeders, work-template baseline when a tenant exists, no users or demo data, idempotent, --dry-run, summary), U-1 (production:user command: create user with canonical role in the tenant, generated one-time password, --reset-password, audited), W-1 (remove the tag-triggered production deploy job from release-management.yml), D-1 (docs/runbooks/go-live.md single runbook; outdated banners on legacy deploy docs and scripts), one PR / one Gate 3, with the exact allowlist in this packet; no migration; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-06T01:50:00+07:00"
  updated_at: "2026-11-06T01:23:50+07:00"
generated_by: agent
---

# GAP-115 — Deploy readiness and production RBAC bootstrap: Gate 2 design

## OWNER GATE 2: APPROVED — M-1, R-1, U-1, W-1, D-1

Owner approved M-1, R-1, U-1, W-1 and D-1 in-session on 2026-11-06 against reviewed design head
`8cc3eeace5e4d60db631fb001bffcc946b8c2e1f`. This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3, merge,
release, or deployment.

## Owner Summary

Anh/chị cần chọn **5 điểm**:

1. **Migration** — đề xuất **M-1**: phân loại 9 migration là `expand` (chỉ thêm bảng / cột mới, bản đang chạy không bị
   ảnh hưởng). Thêm test để CI **báo đỏ** khi có migration mới quên phân loại.
2. **Nạp quyền** — đề xuất **R-1**: lệnh mới `php artisan production:rbac`.
   - Tạo các vai trò chuẩn: Owner, kế toán, PM, kỹ sư công trường, thành viên dự án.
   - Nạp toàn bộ quyền và gán quyền cho từng vai trò.
   - **Không tạo người dùng, không có dữ liệu mẫu.**
   - Chạy nhiều lần vẫn an toàn.
   - Có chế độ **chạy thử** (`--dry-run`) chỉ in ra sẽ làm gì.
3. **Tạo tài khoản** — đề xuất **U-1**: lệnh mới `php artisan production:user` để tạo tài khoản kế toán / PM / nhân viên
   kèm vai trò. Mật khẩu tạo ngẫu nhiên, chỉ hiện một lần. Có tuỳ chọn đặt lại mật khẩu.
4. **Đường deploy cũ** — đề xuất **W-1**: gỡ bước "deploy lên production" trong workflow phát hành theo tag. Chỉ còn
   **một** đường deploy: bấm tay theo đúng commit (GAP-049).
5. **Tài liệu** — đề xuất **D-1**: một runbook duy nhất **"Lên chạy lần đầu"** (`docs/runbooks/go-live.md`), từng bước
   có lệnh cụ thể. Gắn dòng "đã cũ, xem go-live.md" vào các tài liệu / script deploy cũ.

## Điểm 1 — Migration

| Phương án | Nội dung |
|---|---|
| **M-1 (đề xuất)** | Ghi `expand` cho 9 migration GAP-106 → GAP-114 (đã đọc từng file: 7 tạo bảng mới, 2 thêm cột cho phép trống). Thêm test: mọi file trong `database/migrations` phải có trong `classifications.json`; thiếu là CI đỏ. |
| M-2 | Chỉ phân loại, không thêm test. Lần sau dễ quên lại. |

## Điểm 2 — Nạp quyền

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | Lệnh `production:rbac` làm ba việc. **(a)** Tạo nếu chưa có các vai trò: `super_admin` (Owner), `Finance` (kế toán), `Project Manager`, `site_engineer`, `Project Member`. **(b)** Chạy đúng các seeder chỉ chứa quyền: danh mục quyền, quyền Admin, Ngân quỹ, Lương, PM, bổ sung quyền V1. **(c)** Nếu đã có công ty thì nạp mẫu công việc chuẩn. Không bao giờ chạy seeder tạo công ty / người dùng / dữ liệu mẫu. In bảng tổng kết: vai trò → số quyền. `--dry-run` chỉ in, không ghi gì. |
| R-2 | Sửa `DatabaseSeeder` để tự bỏ phần dữ liệu mẫu khi ở production. Dễ chạy nhầm, khó kiểm chứng. |

## Điểm 3 — Tạo tài khoản

| Phương án | Nội dung |
|---|---|
| **U-1 (đề xuất)** | `php artisan production:user --email= --name= --role=` tạo tài khoản trong công ty (một công ty thì tự chọn; nhiều thì bắt buộc `--tenant=`), gán vai trò. Mật khẩu ngẫu nhiên 20 ký tự, chỉ hiện một lần. Từ chối email đã có hoặc vai trò không thuộc danh sách chuẩn. `--reset-password` để cấp lại mật khẩu cho tài khoản đã có. Có nhật ký. |
| U-2 | Dùng luồng mời qua email đã có sẵn. Cần cấu hình mail và chưa được kiểm chứng đầu-cuối. Để sau. |

## Điểm 4 — Đường deploy cũ

| Phương án | Nội dung |
|---|---|
| **W-1 (đề xuất)** | Xoá job `deploy-release` (SSH, `git pull origin main`, docker-compose) khỏi `release-management.yml`; giữ phần tạo bản phát hành. Production chỉ deploy qua `production.yml` (bấm tay, đúng commit, có kiểm tra migration). |
| W-2 | Giữ nguyên. Đặt nhầm secrets là đẩy tag sẽ deploy thẳng nhánh main. |

## Điểm 5 — Tài liệu

| Phương án | Nội dung |
|---|---|
| **D-1 (đề xuất)** | Viết `docs/runbooks/go-live.md`, có lệnh cụ thể cho từng bước. Gắn dòng cảnh báo "đã cũ — xem `docs/runbooks/go-live.md`" ở đầu `DEPLOYMENT_GUIDE.md`, `DOCKER_PRODUCTION_GUIDE.md`, `docs/GO_LIVE_CHECKLIST.md`, `deploy-production.sh`, `scripts/deploy-production.sh`, `scripts/deploy.sh`; `docs/deploy-runbook.md` thêm link. |
| D-2 | Xoá hẳn các tài liệu / script cũ. Có thể mất thông tin còn dùng được. |

**Các bước trong `go-live.md`:**
1. Chuẩn bị máy chủ (link runbook GAP-049).
2. Đặt secrets GitHub cho `production.yml`.
3. Tạo `shared/.env` (production, `APP_DEBUG=false`, queue không `sync`).
4. Deploy lần đầu đúng commit.
5. `deploy:migrate`.
6. `production:rbac --dry-run` rồi `production:rbac`.
7. `production:bootstrap` (công ty + Owner).
8. `production:user` cho kế toán / PM / nhân viên.
9. `deploy:queue-canary`, health check, sao lưu thử và khôi phục thử.
10. Việc hằng tháng: duyệt giờ → chốt lương → chi phí chung → chốt chi phí chung.
11. Cách quay lại bản cũ.

## Thiết kế chi tiết (theo M-1, R-1, U-1, W-1, D-1)

- **`ProductionRbacCommand`** (`production:rbac {--dry-run}`):
  - danh sách vai trò chuẩn và danh sách seeder được phép là hằng số trong lệnh;
  - mọi việc trong một transaction;
  - in bảng vai trò → số quyền;
  - mã thoát khác 0 nếu thiếu bảng (chưa migrate).
- **`ProductionUserCommand`** (`production:user`):
  - kiểm tra email hợp lệ, chưa tồn tại;
  - vai trò thuộc danh sách chuẩn và đã có (yêu cầu chạy `production:rbac` trước);
  - mật khẩu `Str::password(20)`;
  - ghi `AuditLog`.
- Không đổi `production:bootstrap`. Nhờ `production:rbac` tạo sẵn vai trò `super_admin` có đủ quyền, Owner do bootstrap
  tạo ra có quyền ngay.

### Test (đỏ trước / xanh sau)
- **`MigrationClassificationCoverageTest`:** mọi migration đều được phân loại.
- **`ProductionRbacCommandTest`:**
  - tạo đủ vai trò và quyền; chạy hai lần không trùng;
  - `--dry-run` không ghi gì;
  - không tạo công ty / người dùng;
  - Owner sau bootstrap có `payroll.close`, `treasury.view`; kế toán có `payroll.manage`, không có `payroll.close`.
- **`ProductionUserCommandTest`:**
  - tạo tài khoản + vai trò, mật khẩu đăng nhập được;
  - từ chối email trùng / vai trò lạ / chưa nạp quyền;
  - nhiều công ty thì bắt buộc chọn;
  - đặt lại mật khẩu; nhật ký.
- **Workflow:** test đọc `release-management.yml`, xác nhận không còn bước SSH deploy production.

### Files (allowlist)
- Mới:
  - `app/Console/Commands/ProductionRbacCommand.php`, `ProductionUserCommand.php`;
  - `tests/Feature/Deployment/MigrationClassificationCoverageTest.php`, `ProductionRbacCommandTest.php`,
    `ProductionUserCommandTest.php`, `ReleaseWorkflowNoProductionDeployTest.php`;
  - `docs/runbooks/go-live.md`.
- Sửa:
  - `database/migrations/classifications.json`;
  - `.github/workflows/release-management.yml`;
  - `app/Console/Kernel.php` chỉ khi lệnh mới không tự được nạp;
  - dòng cảnh báo ở `DEPLOYMENT_GUIDE.md`, `DOCKER_PRODUCTION_GUIDE.md`, `docs/GO_LIVE_CHECKLIST.md`,
    `docs/deploy-runbook.md`, `deploy-production.sh`, `scripts/deploy-production.sh`, `scripts/deploy.sh`.
- Plan `docs/superpowers/plans/2026-11-06-gap-115-deploy-readiness.md`; packet và evidence GAP-115.

Không migration mới, không phụ thuộc mới, không quyền mới, không đổi seeder hiện có.

## Owner amendment 1 — 2026-11-06 (first deploy on an empty database)

Owner answer via AskUserQuestion on 2026-11-06T05:02:42+07:00, verbatim: "Fix now, amend allowlist (Recommended)".

Rehearsing the go-live runbook on a real empty MySQL 8 database showed that `php artisan deploy:migrate` crashes:
`MigrationClassificationService::pendingMigrationFiles()` reads the `migrations` table before it exists. The first
production deploy would therefore fail at the migration step. Fix: when the `migrations` table does not exist, treat every
migration as pending. Laravel's migrator then creates the table, and the classification check covers all files.

Allowlist addition:
- `app/Services/Deployment/MigrationClassificationService.php`;
- `tests/Feature/Deployment/DeployMigrateCommandTest.php` (a red / green test for the empty-database case).

## Owner amendment 2 — 2026-11-06 (breaking gate on a fresh install)

Owner answer via AskUserQuestion on 2026-11-06T05:05:13+07:00, verbatim: "Fix now, amend allowlist (Recommended)".

After amendment 1, the empty-database rehearsal stopped again. `deploy:migrate` exits 2 because the migration history
holds 6 old migrations classified `breaking`. Those require maintenance mode on a running release, and a first install
has neither a running release nor data to protect. Fix: when no migration has ever run (no `migrations` table, or an
empty one), `deploy:migrate` prints "fresh install" and skips the breaking gate. A database with migration history keeps
exits 2 / 3 unchanged.

Allowlist addition: `app/Console/Commands/DeployMigrateCommand.php` (tests in the already-added
`tests/Feature/Deployment/DeployMigrateCommandTest.php`).

## Ngoài phạm vi

- Cài máy chủ, đặt secrets, deploy, chạy lệnh trên database thật (Owner làm theo runbook).
- Luồng mời qua email (U-2).
- Giao diện quản lý người dùng.

## Verification required at Gate 3

- Đỏ trước / xanh sau.
- Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới.
- Chạy thử trên MySQL 8 thật, database trống, đúng thứ tự runbook:
  1. `migrate`;
  2. `production:rbac`;
  3. `production:bootstrap`;
  4. `production:user`;
  5. đăng nhập Owner / kế toán vào trang lương.
- PHPStan, php-cs-fixer, SSOT lint, deptrac sạch.
- CI trên đúng head xanh.

## Rollback

Revert squash commit. Lệnh mới chỉ thêm, không xoá dữ liệu.

## Decision Needed

Owner chọn: `APPROVE GAP-115 Gate 2 M-1 R-1 U-1 W-1 D-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, và deploy thật.
