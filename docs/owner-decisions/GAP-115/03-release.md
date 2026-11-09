---
work_id: GAP-115
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-06-gap-115-deploy-readiness-evidence.md
  plan: docs/superpowers/plans/2026-11-06-gap-115-deploy-readiness.md
  branch: docs/GAP-115-deploy-readiness
  pr: https://github.com/kha997/zenamanagephp/pull/405
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-06T03:10:00+07:00"
  updated_at: "2026-11-09T22:57:57+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-115 at subject a9415774f16f71faefebe39d5e3e2b7ac6b62c9b (Gate 2 M-1, R-1, U-1, W-1, D-1 plus Owner amendments 1 and 2; exact allowlist; one PR, two implementation commits: 13d8d199, then a9415774 fixing two Codex findings — P1: PermissionSeeder sync()s Project Manager / Project Member, so production:rbac now re-runs ZenaPermissionsSeeder right after it and PM keeps template.view / template.apply / change-request.*; P2: additive baseline grants after the seeders (task.view for Finance, Project Manager, site_engineer, Project Member; project.view for the three project roles) so production:user accounts reach time logging / review; two new tests failing before; empty-MySQL 8 rehearsal role counts stable across runs: super_admin 220, Finance 15, Project Manager 62, site_engineer 18, Project Member 32; no migration). M-1: the 9 GAP-106..114 migrations (7 create-table, 2 nullable add-column; read individually) are classified expand in database/migrations/classifications.json (append-only edit); MigrationClassificationCoverageTest fails if any migration file lacks a classification. R-1: production:rbac {--dry-run} creates the canonical roles super_admin, Finance, Project Manager, site_engineer, Project Member (firstOrCreate) and runs only ZenaPermissionsSeeder, PermissionSeeder (ordered after the catalogue so the first run converges), ZenaAdminRolePermissionSeeder, TreasuryRolePermissionSeeder, PayrollRolePermissionSeeder, ZenaProjectManagerRolePermissionSeeder, V1PermissionGapSeeder in one transaction, plus WorkTemplateBaselineSeeder once a tenant exists; refuses before migrations; prints role -> permission counts; never references DatabaseSeeder / user / tenant / demo seeders or db:seed (asserted). U-1: production:user --email --name --role [--tenant] [--reset-password] creates an active user in the single (or named) tenant with a canonical, already-loaded role and a Str::password(20) shown once (console-escaped), refuses invalid / duplicate email, unknown or unloaded role, missing tenant or ambiguity, and audits production.user.created / password_reset. W-1: release-management.yml loses its deploy-release / rollback-release / cleanup-release jobs (already if: false since GAP-049) with a pointer comment; YAML parses; ReleaseWorkflowNoProductionDeployTest asserts no production host / SSH key / git pull / production environment remain; the existing DeploymentGuardTest stays green. D-1: docs/runbooks/go-live.md (host, shared/.env, secrets, first deploy by SHA, production:rbac, production:bootstrap, production:user, checks, monthly order, later deploys / rollback); outdated banners on DEPLOYMENT_GUIDE.md, DOCKER_PRODUCTION_GUIDE.md, docs/GO_LIVE_CHECKLIST.md, the three legacy deploy scripts (bash -n clean), pointer in docs/deploy-runbook.md. Amendment 1: MigrationClassificationService treats a missing migrations table as nothing ran. Amendment 2: on a fresh database (no or empty migrations table) deploy:migrate prints 'Fresh install' and skips the breaking gate; databases with history keep exits 2 / 3. Tests: 4 new Deployment test files (10 tests) plus 2 new DeployMigrateCommandTest cases, red before and green after; Deployment suite 69 green on SQLite and on MySQL 8. Empty-MySQL 8 rehearsal in runbook order: deploy:migrate (full history, exit 0; second run 'No pending migrations') -> production:rbac --dry-run (writes nothing) -> production:rbac (super_admin 220, Finance 14, Project Manager 54, site_engineer 16, Project Member 31 permissions) -> production:bootstrap -> production:rbac (3 baseline work templates) -> production:user Finance: 2 users, no admin@zena.local. PHPStan, php-cs-fixer, docs-lint, SSOT test lint, ssot:lint, deptrac pass. Unit + Feature + Integration 2994 tests with only the 2 known root-only failures; Feature/Api 824 green. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "a9ff611acb4b033ac96a5cd14426b2dccd4d4c38"
  subject_sha: "a9415774f16f71faefebe39d5e3e2b7ac6b62c9b"
  implementation_tree_digest: "02e648f4e71b7586c5a5849e5c5a844a3494502ca29c4004ce35a16a13dc126f"
  verified_pr_head_sha: "a9415774f16f71faefebe39d5e3e2b7ac6b62c9b"
  verified_at: "2026-11-09T22:57:57+07:00"
owner_decision_binding: null
---

# GAP-115 — Gate 3 release decision (deploy readiness and production RBAC bootstrap)

## Gate 3 trình lại sau review Codex

Owner đã duyệt Gate 3 ("APPROVE GAP-115 Gate 3.") ở subject `13d8d199`, digest `09331b4d…8d62`. Codex review PR và chỉ
ra 2 lỗi thật về quyền (P1, P2). Mã đã sửa ở `a9415774` (trong allowlist), digest đổi thành `02e648f4…126f`, nên lần duyệt
cũ không còn áp dụng và Gate 3 được trình lại.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Chưa đưa được phần mềm lên máy chủ thật một cách an toàn, và chưa có cách cấp quyền trên môi trường
thật.

**2. Sau thay đổi (Gate 2 M-1, R-1, U-1, W-1, D-1 + 2 bổ sung của Owner)** — subject `a9415774`:

**Deploy**
- 9 migration mới được phân loại `expand`.
- CI báo đỏ nếu sau này có migration quên phân loại.
- **Sửa 2 lỗi chặn lần deploy đầu**, tìm ra khi chạy thử trên database trống:
  - lệnh migrate bị lỗi khi chưa có bảng `migrations`;
  - lệnh đòi chế độ bảo trì cho 6 migration cũ loại `breaking`, trong khi lần cài đầu chưa có bản nào đang chạy.

  Nay lần cài đầu chạy đủ lịch sử, báo "Fresh install". Database đã có dữ liệu vẫn giữ nguyên kiểm tra an toàn.
- Gỡ 3 job deploy cũ theo tag. Các job này đã bị tắt từ GAP-049, nay xoá hẳn. Production chỉ deploy qua workflow bấm
  tay theo đúng commit.

**Lệnh mới**
- `php artisan production:rbac` (có `--dry-run`):
  - tạo 5 vai trò chuẩn, nạp quyền và gán quyền cho từng vai trò;
  - **không tạo người dùng, không có dữ liệu mẫu**;
  - chạy lại vẫn an toàn;
  - khi đã có công ty thì nạp thêm mẫu công việc chuẩn.
- `php artisan production:user`:
  - tạo tài khoản kế toán / PM / kỹ sư / thành viên trong công ty;
  - mật khẩu ngẫu nhiên 20 ký tự, chỉ hiện một lần;
  - `--reset-password` để cấp lại mật khẩu;
  - mọi thao tác đều ghi nhật ký.

**Tài liệu:** `docs/runbooks/go-live.md` là runbook duy nhất: 10 bước có lệnh cụ thể, cộng việc hằng tháng và cách quay
lại bản cũ. Các tài liệu và script cũ được gắn dòng "đã cũ, không dùng".

Không có migration mới, không thêm thư viện, không thêm quyền mới. Seeder hiện có không đổi.

**3. Đã kiểm thử** — base `a9ff611a`; subject `a9415774f16f71faefebe39d5e3e2b7ac6b62c9b`; digest
`02e648f4e71b7586c5a5849e5c5a844a3494502ca29c4004ce35a16a13dc126f`.
- **Sửa theo Codex (commit `a9415774`):**
  - **P1:** seeder cũ ghi đè (`sync`) quyền PM nên PM mất quyền mẫu công việc / yêu cầu thay đổi. Nay nạp lại ngay sau
    bước đó.
  - **P2:** PM, kỹ sư, kế toán chưa có quyền vào Bảng giờ. Nay lệnh **thêm** (không xoá gì):
    - quyền Bảng giờ cho mọi nhân viên;
    - quyền xem dự án cho PM / kỹ sư / thành viên.
  - 2 test mới: hỏng khi chưa sửa, xanh khi đã sửa. Một test dùng tài khoản tạo bằng `production:user` mở trang Bảng giờ
    và Duyệt giờ.
  - Chạy thử MySQL 8 database trống: số quyền ổn định giữa 2 lần chạy (Owner 220, kế toán 15, PM 62, kỹ sư 18, thành viên
    32); tạo được PM, kỹ sư, kế toán; không có tài khoản mẫu.
  - Bộ test Deployment 71 test xanh.
- **12 test mới, đều hỏng khi chưa có mã, xanh khi có mã:**
  - mọi migration đều được phân loại;
  - workflow phát hành không còn chạm production;
  - `production:rbac`:
    - đủ vai trò;
    - chạy 2 lần không trùng;
    - `--dry-run` không ghi;
    - không tạo người dùng;
    - Owner sau bootstrap có đủ quyền;
    - kế toán có `payroll.manage` nhưng không có `payroll.close`;
  - `production:user`:
    - tạo kế toán và kế toán mở được trang lương;
    - từ chối email trùng, vai trò lạ, chưa nạp quyền;
    - nhiều công ty thì bắt buộc chọn;
    - đặt lại mật khẩu;
    - có nhật ký;
  - `deploy:migrate`:
    - database chưa có bảng `migrations`;
    - lần cài đầu có migration `breaking`.
- Bộ test Deployment 69 test xanh trên SQLite và MySQL 8.
- **Chạy thử trên MySQL 8 với database trống, đúng thứ tự runbook:**
  - `deploy:migrate` chạy hết lịch sử; chạy lại báo không còn gì;
  - `production:rbac --dry-run` không ghi gì;
  - `production:rbac` nạp quyền: Owner 220, kế toán 14, PM 54, kỹ sư 16, thành viên 31;
  - `production:bootstrap` tạo công ty và Owner;
  - `production:rbac` lần nữa tạo 3 mẫu công việc;
  - `production:user` tạo kế toán.
  - Kết quả: 2 tài khoản, **không có** `admin@zena.local`.
- PHPStan, php-cs-fixer, docs-lint, SSOT lint, deptrac sạch.
- Unit + Feature + Integration, Feature/Api và CI trên đúng head: xem mục 9.

**4. Lưu ý vận hành**
- Việc **thật** (thuê máy chủ, đặt secrets, bấm deploy, chạy lệnh trên máy chủ) vẫn do anh/chị làm theo
  `docs/runbooks/go-live.md`.
- **Không bao giờ chạy `php artisan db:seed` trên máy chủ thật.**
- Mật khẩu in ra chỉ hiện một lần. Ghi lại ngay, gửi riêng cho từng người.

**5. Ngoài phạm vi**
- Deploy thật.
- Mời người dùng qua email.
- Giao diện quản lý người dùng.

**6. Rủi ro còn lại** — Thấp.
- Thay đổi ở `deploy:migrate` chỉ có tác dụng khi database **chưa từng chạy migration nào**.
- Lệnh `production:user` in mật khẩu ra màn hình máy chủ. Nên chạy trong phiên SSH riêng và không lưu lịch sử terminal ra
  nơi công cộng.

**7. Hoàn tác** — Revert PR. Không đổi dữ liệu, không migration.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ**
- **Trước khi sửa Codex (subject `13d8d199`):**
  - Unit + Feature + Integration 2.994 test, chỉ 2 lỗi có sẵn do chạy bằng root;
  - Feature/Api 824 test xanh.
- **Sau khi sửa (subject `a9415774f16f71faefebe39d5e3e2b7ac6b62c9b`):**
  - CI trên đúng head xanh 34/35: Unit, Feature, Integration, API nhanh / chậm, các job MySQL thật, bảo mật, build Docker…;
  - `ProjectApiTest` cũng xanh lần này (lỗi ngẫu nhiên trước đó không lặp lại);
  - "Owner Governance Lint" đỏ đúng như dự kiến khi hồ sơ đang chờ Owner.
  - Máy làm việc khởi động lại giữa chừng nên bộ test đầy đủ ở máy cục bộ chưa chạy lại. Thay vào đó dùng kết quả CI trên
    đúng commit, cùng bộ test Deployment 71 test chạy lại tại máy.

## Decision Needed

Owner chọn: `APPROVE GAP-115 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy thật, phát hành production.
