---
work_id: GAP-021
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-28-gap-021-tasks-api-parity-evidence.md
  plan: null
  branch: docs/GAP-021-tasks-api-parity
  pr: https://github.com/kha997/zenamanagephp/pull/378
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-28T19:25:11+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-28, verbatim: 'APPROVE GAP-021 Gate 2 Option A'. Reviewed design head: 2d832b644ca1a0a5ae64a3ee416b0fe6a440b911. Approves Option A and its exact allowlist (rbac:task.edit -> rbac:task.update on 2 /api/v1/tasks routes and 3 work-template task routes; input.sanitization + error.envelope on the 6 /api/v1/tasks* routes; new V1TaskUpdatePermissionTest; 2 assertions in TasksContractParityAuditInvariantTest; new OPEN register row GAP-098; plan and packets); no controller, seeder, migration or permission-data change; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-28T19:31:40+07:00"
  updated_at: "2026-10-28T19:25:11+07:00"
generated_by: agent
---

# GAP-021 — Tasks API permission and middleware drift: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-28 against reviewed design head
`2d832b644ca1a0a5ae64a3ee416b0fe6a440b911`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Sửa để người có quyền "sửa công việc" (`task.update`) sửa được qua API cũ và API mẫu công việc, và cho API công việc cũ
cùng lớp bảo vệ đầu vào như API chuẩn. Đề xuất **Phương án A**.

**Phát hiện thêm khi thiết kế:** không chỉ `task.edit` — còn **31 mã quyền khác** được các route `/api/v1/*` đòi nhưng
không seeder nào tạo (baseline, change request, compensation, quản trị RBAC, `project.edit`, `component.edit`,
`template.*`…). Các route đó hiện cũng chỉ super admin dùng được. Đây là việc lớn hơn, cần Work ID riêng; Phương án A chỉ
**ghi thành một dòng OPEN mới trong sổ** (GAP-098), không sửa.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Đổi route sang `task.update` + thêm 2 middleware cho `/api/v1/tasks*` + ghi GAP-098** | như thiết kế dưới | **Đề xuất** — dùng đúng quyền đã seed, cùng tên với API chuẩn; không đổi dữ liệu |
| B. Chỉ đổi route sang `task.update` (+ GAP-098) | Không thêm middleware | Nhỏ hơn, nhưng API cũ vẫn nhận dữ liệu đầu vào chưa làm sạch |
| C. Seed thêm quyền `task.edit` và gán cho các vai trò có `task.update` | Đổi dữ liệu quyền, có 2 mã cùng nghĩa | Không đề xuất: thêm nợ, cần migration dữ liệu cho tenant cũ |

## Thiết kế: Phương án A

### 1. Quyền

- `src/CoreProject/routes/api.php:96-97`: `PUT|PATCH tasks/{task}` → `rbac:task.update`.
- `src/WorkTemplate/routes/api.php:79,82,83`: `PUT {taskId}`, `PUT {taskId}/progress`, `POST {taskId}/toggle-conditional`
  → `rbac:task.update`.

### 2. Middleware

Sáu route `/api/v1/tasks*` (index, store, show, put, patch, destroy) thêm `input.sanitization` và `error.envelope`, giống
`/api/zena/tasks*`. Không thêm cho các route v1 khác.

Ảnh hưởng hợp đồng tương thích: response thành công không đổi (envelope JSend giữ nguyên); response lỗi 4xx/5xx JSON đi
qua `ErrorEnvelopeMiddleware` như API chuẩn; input có mẫu đáng ngờ bị từ chối 400 `SUSPICIOUS_INPUT` như API chuẩn.

### 3. Test (đỏ trước / xanh sau)

Test mới `tests/Feature/Api/V1TaskUpdatePermissionTest.php`, user **không** phải super admin:

- có `task.update`: `PUT` và `PATCH /api/v1/tasks/{task}` → 200 và DB đổi (đỏ trước: 403);
- không có `task.update`: `PUT` → 403;
- route work-template `PUT …/tasks/{taskId}/progress` với `task.update` → không còn 403 (đỏ trước: 403);
- `POST /api/v1/tasks` với input đáng ngờ → 400 `SUSPICIOUS_INPUT` (đỏ trước: không bị chặn).

Sửa assertion hiện có: `tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php:76-77` đang khoá việc v1
**không** có 2 middleware — đổi thành `assertContains` (ghi rõ trong Gate 3).

### 4. Sổ

`OPERATIONAL_GAP_REGISTER.md`: thêm dòng **GAP-098 (OPEN, verified 2026-10-28)** — 31 mã quyền được route `/api/v1/*`
đòi nhưng không seed, liệt kê mã và số route; hành động đề xuất: Work ID riêng. Dòng GAP-021 **không** đổi ở PR này
(cập nhật khi đối soát sau phát hành).

### 5. Files (allowlist)

`src/CoreProject/routes/api.php`; `src/WorkTemplate/routes/api.php`; test mới; `TasksContractParityAuditInvariantTest.php`
(2 dòng); `OPERATIONAL_GAP_REGISTER.md` (1 dòng mới); plan `docs/superpowers/plans/2026-10-28-gap-021-tasks-api-parity.md`;
packet và evidence của GAP-021.

**Không đổi:** controller, request, resource, seeder, migration, dữ liệu quyền, dạng response thành công, các route v1
khác, 31 mã quyền của GAP-098.

## Verification required at Gate 3

Đỏ trước / xanh sau cho test mới; các suite Api, Architecture, Feature liên quan task / work-template; PHPStan,
php-cs-fixer, SSOT test lint, route guard, deptrac; exact-head CI xanh.

## Rollback

Revert squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-021 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay phát hành; không sửa 31 mã quyền của GAP-098; không gộp controller giao việc.
