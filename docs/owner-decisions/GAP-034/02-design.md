---
work_id: GAP-034
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-07-gap-034-export-tenant-isolation-design.md
  plan: null
  branch: docs/GAP-034-gate2-export-tenant-isolation
  pr: https://github.com/kha997/zenamanagephp/pull/249
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: "ChatGPT project conversation — Owner-authorized GAP-034 Gate 2 design preparation after explicit Gate 1 approval; review round 1 returned CHANGES REQUESTED for Task scalar foreign-reference leakage; review round 2 returned CHANGES REQUESTED for incomplete Project scalar-reference inventory/projection; this revision re-presents the design without recording approval"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T22:17:08+07:00"
  updated_at: "2026-08-07T23:00:57+07:00"
generated_by: agent
---

## OWNER GATE 2: AWAITING OWNER

GAP-034 bảo vệ cả hai endpoint bulk export task/project khỏi rò rỉ dữ liệu giữa tenant, cho CSV, Excel và JSON. Thiết kế chỉ giới hạn dữ liệu được chọn và dữ liệu liên quan/tổng hợp được đưa vào export; không thay đổi RBAC, không triển khai GAP-010b và chưa cho phép sửa mã nguồn.

## Lịch sử review

- Owner review round 1, reviewed head `96dc086283e021e007d627d62c0061ecb330f2ab`: **CHANGES REQUESTED**.
- Finding round 1: relation scoping không ngăn Task scalar foreign identifiers đi vào CSV/JSON qua `assignee_id` và unrestricted `toArray()`; Task-side revision được Owner chấp nhận về hướng.
- Owner review round 2, reviewed head `08d48bd9c9e02712365f0ad5248c374aa8463d00`: **CHANGES REQUESTED**.
- Finding round 2: Project JSON vẫn cần inventory/projection cụ thể cho `client_id`, `pm_id`, `created_by` và các reference-bearing Project columns khác.
- Revision này giữ nguyên Task policy đã được chấp nhận và bổ sung tenant-safe explicit Project projection. Gate 2 vẫn **AWAITING OWNER**; không ghi nhận approval.

## Trước / Sau

**Trước:**

1. `tenant.isolation` xác minh tenant của request nhưng export query không dùng tenant đó.
2. Caller có thể gửi ID/filter của tenant khác; base query có thể lấy record tenant khác.
3. Eager-loaded Project/Task data, task-count aggregates và scalar foreign identifiers trên Task có thể mang dữ liệu tenant khác nếu database có row tham chiếu không nhất quán.

**Sau (nếu Owner duyệt thiết kế):**

1. Export lấy tenant duy nhất từ request attribute `tenant_id` do `tenant.isolation` đã thiết lập sau khi xác minh user/header/tenant.
2. Nếu trusted tenant context thiếu hoặc rỗng, export fail closed trước khi chạy query hoặc tạo file.
3. Base Task/Project query luôn có tenant predicate trước mọi ID/filter do caller cung cấp.
4. Task chỉ eligible nếu Project bắt buộc tồn tại và thuộc trusted tenant; Project relation, Task relation/assignment data và aggregates đều bị giới hạn cùng tenant.
5. Task và Project JSON dùng explicit tenant-safe projections; không dùng unrestricted `$tasks->toArray()` hoặc `$projects->toArray()`.
6. Optional scalar references chỉ được giữ sau tenant/project validation; foreign/stale values thành `null`, `[]` hoặc fallback không định danh.
7. ID tenant khác bị lọc im lặng: không record, không related data, không scalar-reference leak và không existence oracle.

## Quy tắc nghiệp vụ/bảo mật đã chốt trong thiết kế

- Tenant A không bao giờ xuất được Task/Project của Tenant B hay dữ liệu B-derived, bất kể ID, filter hoặc format.
- Không tin request body/query `tenant_id`; không suy tenant từ record ID.
- Mixed A+B IDs chỉ xuất A; B-only IDs tạo tập kết quả rỗng, không tiết lộ record B có tồn tại.
- `filters.project_id` luôn kết hợp bằng `AND` sau tenant predicate.
- Project là structural parent: Task A → Project B hoặc Project missing/stale làm Task không eligible ở query level.
- Assignee/User references cross-tenant hoặc stale được xuất như `Unassigned` trong CSV/Excel và `null` trong JSON.
- `component_id` và `phase_id` chỉ được giữ khi target thuộc cùng eligible Project; nếu không thì `null`.
- `dependencies_json` chỉ giữ Task IDs thuộc trusted tenant, có tenant-consistent Project và cùng Project với Task nguồn; foreign/stale IDs bị loại.
- `assigned_to`, `created_by`, `updated_by`, `watchers`, `parent_id`, `work_instance_id` và `work_instance_step_id` cũng được tenant-safe validation trước JSON projection.
- Project `client_id`, `pm_id`, `created_by` là optional User references: Project vẫn eligible; same-tenant ID được giữ, foreign/stale ID thành `null`.
- Project `template_id` là reference-shaped column nhưng repository không có FK/relation/target contract; không được allowlist vào export cho đến khi có review riêng.
- Project `tags` là string labels và `settings` là business flags/config theo request/factory evidence; GAP-034 coi là non-reference metadata và giữ nguyên.
- Missing trusted context không được phép rơi về query không scope.
- Header/user mismatch vẫn do middleware xử lý và không bị thay đổi.

## Vai trò bị ảnh hưởng

Không thay đổi vai trò hoặc permission. Mọi caller đã vượt qua `auth:sanctum`, `tenant.isolation` và `rbac` vẫn dùng endpoint như hiện nay, nhưng chỉ nhận dữ liệu tenant đã được middleware xác minh.

## Hành vi người dùng nhìn thấy

- Request không truyền ID: chỉ record tenant hiện tại.
- ID tenant khác hoặc mixed IDs: tenant khác bị bỏ qua, response không phân biệt “không tồn tại” với “không thuộc tenant”.
- CSV, Excel và JSON tuân cùng data-selection boundary.
- JSON giữ envelope và Task field names nhưng được tạo từ allowlisted explicit projection; không serialize unrestricted model attributes/relations.
- Project JSON giữ safe business fields; optional User references chỉ được đưa vào sau tenant validation, Task children dùng Task-safe projection, counts dùng tenant-constrained aggregates.
- Missing tenant context: request thất bại, không có success response/download artifact.

## Kịch bản chấp nhận

1. Tenant A export Task không truyền IDs → chỉ Task A.
2. A truyền Task B ID → không có Task B hoặc B-derived data.
3. A truyền mixed Task A+B IDs → chỉ Task A.
4. A dùng `filters.project_id` của B → không thoát tenant predicate.
5. Task A tham chiếu Project B hoặc Project stale → Task A bị loại khỏi CSV/Excel/JSON; Project B ULID/name/data không xuất hiện.
6. Tenant A export Project không truyền IDs → chỉ Project A.
7. A truyền Project B ID → không có Project B.
8. A truyền mixed Project A+B IDs → chỉ Project A.
9. Task B tham chiếu Project A không nhất quán → không làm tăng Total/Completed Tasks của Project A.
10. Ma trận CSV/Excel/JSON → không format nào bỏ qua isolation.
11. Caller-supplied tenant-like input → không override tenant A do middleware thiết lập.
12. Missing trusted tenant context → fail closed trước query/output.
13. Tenant-header mismatch behavior hiện tại của middleware vẫn giữ nguyên.
14. Task A có `assignee_id = User B` → Task vẫn eligible, CSV/Excel dùng `Unassigned`, JSON dùng `null`, User B ULID/attributes không xuất hiện.
15. Foreign/stale component, phase, dependency, user/audit/watcher, parent hoặc work-instance references → áp dụng policy đã chốt theo inventory; foreign ULID không xuất hiện ở bất kỳ payload nào.
16. Project A có foreign/stale `client_id`, `pm_id` hoặc `created_by` → Project A vẫn export; field tương ứng thành `null`; User B ULID/attributes không xuất hiện.
17. Project A có valid same-tenant User references → IDs được giữ nguyên; future/unexpected Project reference column không tự động đi vào JSON.

## Tương tác với GAP-010b

GAP-034 chỉ sở hữu tenant isolation của selection, relations, aggregates và emitted scalar references. GAP-010b tiếp tục sở hữu Request import, CSV safety, streaming/chunking, tags, writer, atomic publication và row-count correctness. Hai thiết kế ghép lại bằng tenant-scoped query/relations/count closures và tenant-safe projection trước format generation; không mở lại bounded-memory design của GAP-010b. Cả hai work item vẫn là hard release blockers cho hai endpoint.

## Loại trừ phạm vi

Không global model scope, không rollout `TenantScope`, không sửa model-wide `scopeForTenant(int ...)`, không RBAC redesign, migration, dependency, tenant-ID migration, route change, application code, test implementation, GAP-010b implementation, Gate 3, merge hoặc release.

## Decision Needed

Owner chọn một trong: **Approve để cho phép chuẩn bị implementation plan/implementation authorization riêng** / **Request changes** / **Decline**.

## What the owner is NOT being asked to decide

Owner chưa được yêu cầu phê duyệt class/method/test mechanics, sửa model scope, implementation, Gate 3, merge hoặc release. Implementation authorized: **NO**. Gate 3: **NOT STARTED**. Merge/release authorized: **NO**.
