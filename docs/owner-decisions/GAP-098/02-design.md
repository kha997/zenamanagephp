---
work_id: GAP-098
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-28-gap-098-unseeded-v1-permissions-evidence.md
  plan: null
  branch: docs/GAP-098-unseeded-v1-permissions
  pr: https://github.com/kha997/zenamanagephp/pull/379
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-28T20:55:52+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-28, verbatim: 'APPROVE GAP-098 Gate 2 Option A'. Reviewed design head: 4dfe6fc0ab49f9e7b599025ab66ebb5e3656d272. Approves Option A and its exact allowlist (rename 5 codes on 7 routes in src/CoreProject/routes/api.php and src/ChangeRequest/routes/api.php; new V1PermissionGapSeeder creating 27 codes with the stated default role grants, one line in DatabaseSeeder; new RouteRbacCodesSeededTest and V1PermissionGapTest; plan and packets); no controller, migration, environment data, rbac:admin, zena/operator route or GAP-042 test change; not Gate 3, merge, seeding any environment, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-28T20:54:25+07:00"
  updated_at: "2026-10-28T20:55:52+07:00"
generated_by: agent
---

# GAP-098 — Unseeded `/api/v1` permission codes: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-28 against reviewed design head
`4dfe6fc0ab49f9e7b599025ab66ebb5e3656d272`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **Phương án A — kết hợp**:

- **Đổi tên 5 mã** sang mã đã có cùng nghĩa (7 route): `project.edit`→`project.update`,
  `component.edit`→`component.update`, `change_request.view` và `.stats`→`change_request.read`,
  `change_request.edit`→`change_request.update`.
- **Tạo 27 mã còn lại đúng như tên route đang dùng** (62 route) bằng một seeder mới, và gán mặc định cho vai trò:
  Admin có tất cả; PM có baseline, nộp change request, xem/ước tính compensation, tạo/sửa mẫu, xem người dùng; thành viên
  dự án xem baseline và compensation; kế toán xem compensation. **Quản trị vai trò / quyền và xoá change request chỉ
  Admin.**
- Thêm **test bảo vệ**: mọi mã quyền mà route đòi phải được seeder tạo ra — lỗi kiểu GAP-021 / GAP-098 sẽ làm CI đỏ.

Giữ nguyên tên `role.*`, `permission.*`, `user.view`, `audit.view` vì GAP-042 (đã duyệt) dùng đúng các mã này cho API quản
trị quyền.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Đổi tên khi có mã cùng nghĩa + seed phần còn lại + test bảo vệ** | như trên | **Đề xuất** — không tạo mã trùng nghĩa, không phá hợp đồng GAP-042 |
| B. Seed nguyên 32 mã, không đổi route | Ít đụng route nhất | Tạo cặp trùng nghĩa (`project.edit` / `project.update`…), lặp lại nợ GAP-021 đã gỡ |
| C. Gỡ hẳn 6 nhóm API v1 (không trang nào dùng) | Gọn nhất | Phá client ngoài (chưa rõ), baseline / compensation / quản trị quyền chưa có API chuẩn thay thế |

## Thiết kế: Phương án A

### 1. Đổi tên (route files, 7 route)

| Route | Từ | Sang |
|---|---|---|
| `PUT|PATCH api/v1/projects/{project}` | `project.edit` | `project.update` |
| `PATCH api/v1/components/{id}` | `component.edit` | `component.update` |
| `GET api/v1/change-requests`, `/{id}`, `/project/{projectId}` | `change_request.view` | `change_request.read` |
| `GET api/v1/change-requests/statistics/{projectId}` | `change_request.stats` | `change_request.read` |
| `PUT api/v1/change-requests/{id}` | `change_request.edit` | `change_request.update` |

Các mã đích đều do `PermissionSeeder` tạo (đường `DatabaseSeeder`).

### 2. Seeder mới `database/seeders/V1PermissionGapSeeder.php` (27 mã)

Tạo mã (firstOrCreate theo `code`, có `name`), gán **cộng thêm** (`syncWithoutDetaching`) theo tên vai trò như
`TreasuryRolePermissionSeeder` (khớp mọi tên tương đương, không phân biệt hoa thường). Thêm vào `DatabaseSeeder` sau các
seeder quyền hiện có.

| Vai trò | Mã |
|---|---|
| Admin (`System Admin`, `Admin`, `super_admin`, `system_admin`) | cả 27 |
| PM (`PM`, `Project Manager`, `project_manager`, `SiteEngineer`, `site_engineer`) | `baseline.view/create/edit/delete`, `change_request.submit`, `compensation.view/preview/stats`, `template.create`, `template.edit`, `user.view` |
| Kế toán (`Finance`, `accountant`) | `compensation.view`, `compensation.stats` |
| Thành viên / xem (`Project Member`, `project_member`, `Designer`, `QC`, `quality_inspector`, `Procurement`) | `baseline.view`, `compensation.view` |
| Chỉ Admin | `role.*` (5), `permission.*` (6), `audit.view`, `change_request.delete`, `compensation.edit/apply/sync` |

Môi trường đã có dữ liệu cần chạy `php artisan db:seed --class=V1PermissionGapSeeder` (giống cách seeder Ngân quỹ được
áp dụng); PR này không chạy trên môi trường nào.

### 3. Test (đỏ trước / xanh sau)

- `tests/Feature/Architecture/RouteRbacCodesSeededTest.php`: chạy các seeder quyền của `DatabaseSeeder`, rồi khẳng định
  mọi `rbac:<mã có dấu chấm>` trên route đều có trong bảng `permissions`. Đỏ trước: liệt kê đúng 32 mã.
- `tests/Feature/Api/V1PermissionGapTest.php` (user **không** phải super admin, vai trò được seeder mới gán): PM xem
  baseline của dự án → 200 (trước: 403); người có `project.update` sửa `/api/v1/projects/{id}` → 200 (trước: 403);
  Admin xem `/api/v1/rbac/roles` → 200; thành viên dự án tạo vai trò → 403.

### 4. Files (allowlist)

`src/CoreProject/routes/api.php`, `src/ChangeRequest/routes/api.php` (chỉ dòng middleware của 5 mã); seeder mới;
`database/seeders/DatabaseSeeder.php` (1 dòng); 2 test mới; plan `docs/superpowers/plans/2026-10-28-gap-098-v1-permission-gap.md`;
packet và evidence GAP-098.

**Không đổi:** controller, migration, dữ liệu trên môi trường thật, `rbac:admin`, các route `/api/zena` và `/operator`,
test GAP-042.

## Verification required at Gate 3

Đỏ trước / xanh sau cho 2 test mới; Feature/Api, Architecture, RBAC/GAP-042, change request, baseline, compensation,
template suites; PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac; exact-head CI xanh.

## Rollback

Revert squash commit; mã quyền đã seed trên môi trường nào thì giữ lại vô hại (không route nào dùng sau revert) hoặc xoá bằng
tay.

## Decision Needed

Owner chọn: `APPROVE GAP-098 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, chạy seeder trên môi trường thật hay deploy; không đụng nhóm `rbac:admin`.
