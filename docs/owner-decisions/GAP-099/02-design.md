---
work_id: GAP-099
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-28-gap-099-broken-v1-project-and-change-request-evidence.md
  plan: null
  branch: docs/GAP-099-broken-v1-project-and-change-request
  pr: https://github.com/kha997/zenamanagephp/pull/380
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-28T22:06:07+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-28, verbatim: 'APPROVE GAP-099 Gate 2 Option A'. Reviewed design head: f4c5550b14aedf5ee716660b2ccd5c9bbf77a03b. Approves Option A and its exact allowlist (UpdateProjectRequest status rule uses Project::VALID_STATUSES; remove 10 /api/v1/change-requests route declarations, keep POST store; new V1ProjectAndChangeRequestRoutesTest; plan and packets); no controller, service, model, migration, /api/zena or other v1 change; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-28T22:04:43+07:00"
  updated_at: "2026-10-28T22:08:19+07:00"
generated_by: agent
---

# GAP-099 — Broken `/api/v1` project update and change-request routes: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-28 against reviewed design head
`f4c5550b14aedf5ee716660b2ccd5c9bbf77a03b`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đề xuất **Phương án A**:

1. **Sửa dự án v1**: sửa 1 dòng để dùng đúng danh sách trạng thái `VALID_STATUSES` → `PUT/PATCH /api/v1/projects/{id}` chạy
   được.
2. **Change request v1**: **gỡ 10 route chưa từng chạy được** (danh sách, xem, sửa, xoá, nộp, duyệt, từ chối, thống kê, theo
   dự án, chờ duyệt) — sau khi gỡ, gọi vào sẽ nhận 404 thay vì lỗi 500. **Giữ route tạo mới** (`POST /api/v1/change-requests`)
   vì đang chạy và có test dùng. Quản lý change request tiếp tục qua API chuẩn `/api/zena/change-requests`.

Không ai đang dùng được 10 route này hôm nay (chúng chỉ trả 500 hoặc không khớp bản ghi thật nào).

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Sửa project update + gỡ 10 route CR v1 hỏng** | như trên | **Đề xuất** — nhỏ, không nuôi API thứ hai cho change request (roadmap: hạn chế phát triển thêm trên `/api/v1`) |
| B. Sửa project update + sửa cả 10 route CR v1 | Thêm 3 method, đổi chữ ký 4 method, đổi kiểu id ở service, nới ràng buộc id cho ULID | Lớn, nhân đôi chức năng `/api/zena/change-requests` đang chạy tốt |
| C. Chỉ sửa project update | CR v1 để nguyên (vẫn 500) | Để lại 10 route trả 500 |

## Thiết kế: Phương án A

### 1. Project update

`src/CoreProject/Requests/UpdateProjectRequest.php:54`: `array_keys(Project::STATUSES)` → `Project::VALID_STATUSES` (giống
`StoreProjectRequest.php:61`).

### 2. Change request v1

`src/ChangeRequest/routes/api.php`: xoá khai báo 10 route; giữ `POST /` (`change-requests.store`). Controller và service
**không đổi** (các method không còn route gọi tới được để nguyên; dọn dẹp code chết là việc riêng nếu Owner muốn).

### 3. Test (đỏ trước / xanh sau)

`tests/Feature/Api/V1ProjectAndChangeRequestRoutesTest.php`:

- `PATCH /api/v1/projects/{id}` với tên mới → 200 và DB đổi (trước: 500).
- 10 route CR v1 đã gỡ không còn trong bảng route (trước: có).
- `POST /api/v1/change-requests` vẫn 201.

Các test hiện có dùng `api/v1/change-requests` (`IntegrationTest`, `RouteHygieneTest`, `ModuleOwnership*InvariantTest`) chỉ dựa
vào route tạo mới / URI — vẫn đúng.

### 4. Files (allowlist)

`src/CoreProject/Requests/UpdateProjectRequest.php` (1 dòng); `src/ChangeRequest/routes/api.php` (xoá 10 khai báo); test mới;
plan `docs/superpowers/plans/2026-10-28-gap-099-broken-v1-routes.md`; packet và evidence GAP-099.

**Không đổi:** controller, service, model, migration, `/api/zena/*`, các nhóm v1 khác, kiểm tra vai trò `rbac` không tham số.

## Verification required at Gate 3

Đỏ trước / xanh sau cho test mới; Feature/Api, Architecture, IntegrationTest, RouteHygieneTest, change request và project
suites; PHPStan, php-cs-fixer, SSOT lint (gồm orphan-route), route guard, deptrac; exact-head CI xanh.

## Rollback

Revert squash commit (các route quay lại, vẫn hỏng như trước).

## Decision Needed

Owner chọn: `APPROVE GAP-099 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge, phát hành; không xoá controller / service change request v1.

## Owner amendment 1 (2026-10-28)

After the one-line request fix, `PATCH /api/v1/projects/{id}` worked for plain field changes but still returned 500 when
`status`, `progress` or `actual_cost` changed, and `DELETE /api/v1/projects/{id}` returned 500: the v1
`ProjectController` dispatched `Src\CoreProject\Events\ProjectUpdated` with a `Src\CoreProject\Models\Project` where the
event requires `App\Models\Project`, passed the changed-field list as the `?User` argument, and in `destroy()` used an
undefined `$oldData`. The event has no listener. Owner answer in-session on 2026-10-28 (AskUserQuestion), verbatim:
"Sửa 2 chỗ phát event (Recommended)". The allowlist gains: in `src/CoreProject/Controllers/ProjectController.php`
`update()` and `destroy()`, dispatch the event with the `App\Models\Project` built from the same row's attributes and
without the wrong third argument, and capture `$oldData` before deleting; the new test also covers a status change and a
v1 delete.
