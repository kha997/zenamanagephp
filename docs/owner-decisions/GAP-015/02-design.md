---
work_id: GAP-015
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md
  plan: docs/superpowers/plans/2026-10-29-gap-015-work-instance-screens.md
  branch: docs/GAP-015-worktemplate-ui-owner
  pr: https://github.com/kha997/zenamanagephp/pull/387
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T18:21:44+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-015 Gate 2 Option A'. Reviewed design head: d322a0e6da8e9d4e6e83fa6987f3fbf29d6a637c. Approves Option A scope (WorkInstance execution web screens using the existing APIs; work.* default grants for Project Manager and Project Member; no API, business-rule or authoring change). Per the packet, the exact file allowlist and tests are added as a supplement for Owner confirmation before any code; not Gate 3, merge, release, deployment or environment seeding."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T18:20:19+07:00"
  updated_at: "2026-10-29T18:40:07+07:00"
generated_by: agent
---

# GAP-015 — WorkTemplate / WorkInstance screens: Gate 2 design (scope choice)

## OWNER GATE 2: APPROVED — OPTION A (scope)

Owner approved Option A in-session on 2026-10-29 against reviewed design head
`d322a0e6da8e9d4e6e83fa6987f3fbf29d6a637c`. As the packet states, the exact allowlist and tests follow in the supplement
below and need Owner confirmation before implementation; this does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Đây là quyết định sản phẩm, chia nhiều lần phát hành. Đề xuất **Phương án A — làm trước màn hình THỰC HIỆN công việc theo
mẫu** (người ngoài hiện trường dùng hằng ngày), để màn hình SOẠN mẫu cho lần sau (ít người dùng, đang có nhập/xuất gói qua
API):

1. **Trong trang dự án**, thêm mục **"Công việc theo mẫu"**: danh sách các phiên làm việc (WorkInstance) đã áp dụng cho dự
   án, tiến độ (số bước xong / tổng).
2. **Trang chi tiết một phiên**: danh sách bước; mỗi bước xem / cập nhật trạng thái, người làm, hạn, các ô dữ liệu của
   bước; tải lên / xoá file đính kèm; **duyệt bước** (người có quyền duyệt); nút xuất báo cáo.
3. **Quyền mặc định**: PM có `work.view`, `work.update`, `work.approve`, `work.export`; Project Member có `work.view`,
   `work.update` (không duyệt). Admin giữ nguyên.
4. Màn hình chỉ gọi các API đã có (không đổi luật nghiệp vụ). Không đổi API soạn mẫu.

## So sánh phương án

| Phương án | Lần này làm gì | Kết luận |
|---|---|---|
| **A. Màn hình thực hiện công việc theo mẫu** | Danh sách + chi tiết phiên, cập nhật / duyệt bước, đính kèm, xuất; cấp quyền `work.*` cho PM / Member | **Đề xuất** — giá trị vận hành cao nhất, dùng API sẵn có |
| B. Màn hình soạn mẫu | Danh sách mẫu, tạo / sửa nháp (phase, bước, ô dữ liệu, checklist), phát hành, xoá; sửa quyền PM (`template.edit_draft`, `template.publish`) | Lớn hơn A (trình soạn nhiều cấp); ít người dùng |
| C. Cả A và B một lần | Như trên | Rất lớn, rủi ro cao — không nên gộp |
| D. Không xây | Ghi nhận "mẫu công việc chỉ dùng qua API" là phạm vi chủ đích, đóng GAP-015 | Không tốn công; người dùng web vẫn không làm được bước |

## Thiết kế sơ bộ: Phương án A (chi tiết file và test ở Gate 2 bổ sung nếu anh/chị chọn A)

- Route web mới dưới `/projects/{project}/work-instances` (danh sách, chi tiết) và các POST cho cập nhật bước, duyệt, đính
  kèm, xuất — mỗi route gắn `rbac:work.*` tương ứng, controller web gọi `WorkInstanceController` qua cách delegate đã dùng ở
  các trang khác (`DelegatesToApiControllers`).
- View Blade theo layout operator hiện có; trang dự án có link tới mục mới.
- Seeder: thêm cấp quyền `work.*` cho PM / Member (cùng cách GAP-030).
- Test: trang danh sách / chi tiết hiển thị đúng; cập nhật / duyệt / đính kèm qua web thành công và bị chặn đúng quyền;
  tenant khác 404; seeder cấp đúng quyền.

**Không đổi:** API, luật nghiệp vụ bước / duyệt, soạn mẫu (B), dữ liệu cũ.

## Decision Needed

Owner chọn: `APPROVE GAP-015 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Option D / Request changes / Decline.

Nếu chọn A hoặc B, tôi bổ sung danh sách file chính xác và test vào packet này trước khi code (anh/chị duyệt lại phần bổ
sung); nếu chọn D, tôi cập nhật register / roadmap và đóng GAP-015.

## What the owner is NOT being asked to decide

Chưa duyệt code, Gate 3, merge, phát hành hay seed môi trường thật.

## Supplement — exact allowlist and tests for Option A (needs Owner confirmation before code)

### Web routes (`routes/web.php`, operator group, 7 routes)

| Method + path (under `/projects/{project}/work-instances`) | Name (`operator.` prefix) | Permission | Calls |
|---|---|---|---|
| `GET /` | `projects.work-instances.index` | `work.view` | read-only list (tenant + project scoped) |
| `GET /{instance}` | `projects.work-instances.show` | `work.view` | read-only detail: steps, field values, attachments |
| `POST /{instance}/steps/{step}` | `projects.work-instances.steps.update` | `work.update` | `WorkInstanceController::updateStep` |
| `POST /{instance}/steps/{step}/approve` | `projects.work-instances.steps.approve` | `work.approve` | `WorkInstanceController::approveStep` |
| `POST /{instance}/steps/{step}/attachments` | `projects.work-instances.steps.attachments.store` | `work.update` | `WorkInstanceController::uploadStepAttachment` |
| `POST /{instance}/steps/{step}/attachments/{attachment}/delete` | `projects.work-instances.steps.attachments.destroy` | `work.update` | `WorkInstanceController::deleteStepAttachment` |
| `POST /{instance}/export` | `projects.work-instances.export` | `work.export` | `WorkInstanceController::exportDeliverable` |

Mutations delegate to the existing API controller through `DelegatesToApiControllers` (same pattern as the inspection and
submittal pages); the instance must belong to the route's project and tenant (404 otherwise). The step form offers only the
statuses `pending`, `in_progress`, `completed`, `blocked`; approve / reject goes through the approve button (`work.approve`).

### Files

- New `app/Http/Controllers/Web/WorkInstancePageController.php`.
- New `resources/views/work-instances/index.blade.php`, `resources/views/work-instances/show.blade.php`.
- `resources/views/projects/show.blade.php`: one link "Công việc theo mẫu" (shown with `work.view`).
- `routes/web.php`: the 7 routes above.
- `database/seeders/ZenaProjectManagerRolePermissionSeeder.php`: PM gains `work.view`, `work.update`, `work.approve`,
  `work.export`; Project Member gains `work.view`, `work.update`.
- New `tests/Feature/Zena/WorkInstancePagesTest.php`; update `tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`
  (expected grant lists).
- Plan `docs/superpowers/plans/2026-10-29-gap-015-work-instance-screens.md`; GAP-015 packets and evidence.

### Tests (red before / green after)

`WorkInstancePagesTest`: list shows the project's instances with progress; detail shows steps and field values; updating a
step (status, assignee, deadline, a field value) persists; approve records an approval and sets the step status;
attachment upload then delete; export returns a file; a user without `work.approve` gets 403 on approve; another tenant's
instance or an instance of another project returns 404; unauthenticated redirects to login. Seeder test: PM and Project
Member receive the listed `work.*` codes.

### Not changed

APIs, step / approval rules, template authoring, other roles, existing data.

### Supplement decision: APPROVED

Owner confirmed in-session on 2026-10-29, verbatim: "APPROVE GAP-015 Gate 2 supplement" (recorded 2026-10-29T18:40:07+07:00), against
reviewed head `e8b198fd1a6350a289e556fe89c38bfacc7d28cb`. This authorizes implementation within exactly this allowlist.

## Owner amendment 1 (2026-10-29)

PHPStan reported two new errors because the new page controller uses `DelegatesToApiControllers`, whose
`buildApiRequest()` lacks array value types (existing debt, baselined for every other controller that uses the trait);
adding baseline entries is not allowed and the trait file was not in the allowlist. Owner answer in-session on 2026-10-29
(AskUserQuestion), verbatim: "Thêm @param vào trait (Recommended)". The allowlist gains exactly two PHPDoc `@param`
lines (`array<string, mixed> $payload`, `array<string, mixed> $files`) in
`app/Http/Controllers/Web/Concerns/DelegatesToApiControllers.php`; no logic change.

## Implementation notes (disclosed for Gate 3, within the approved allowlist)

- **Route names:** the routes sit in the existing project route group of `routes/web.php`
  (`Route::prefix('app')->name('app.')`, next to `app.projects.work-templates.*`), so their names carry the `app.` prefix
  (`app.projects.work-instances.*`), not `operator.` as the supplement table says. Paths, permissions and handlers are as
  approved.
- **Missing permission on a web action:** the existing `rbac` middleware answers a browser form post by redirecting back
  with an error message instead of a bare 403, so the test asserts that redirect, the error and the unchanged step.
- **Assignee:** the step form offers users of the current tenant only; the page rejects any other `assignee_id` (the API
  only checks that the user exists).

## Owner amendment 2 (2026-10-29)

Codex (P1, security) on PR #387: the existing `PATCH /api/zena/work-instances/{id}/steps/{stepId}` needs only
`work.update` yet accepts `approved` / `rejected` without checking `work.approve` or recording an approval; this design
grants `work.update` to Project Member, who could then "approve" steps through the API. The API controller was not in the
allowlist ("APIs not changed"). Owner answer in-session on 2026-10-29 (AskUserQuestion), verbatim: "Chặn trong API
(Recommended)". The allowlist gains: in `app/Http/Controllers/Api/WorkInstanceController.php::updateStep`, a request
setting `status` to `approved` or `rejected` returns 403 unless the user has `work.approve` (users with `work.approve`
behave as before), plus the `User` import; covered by a new test in `WorkInstancePagesTest`. Member keeps `work.update`.
