---
work_id: GAP-042
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md
  plan: null
  branch: docs/GAP-042-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/298"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-01T23:40:00+07:00"
  owner_response_reference: "GAP-042 Gate 2 Round 1 decision (relayed via coordinator session, not a directly witnessed live Owner chat interaction in this agent session — recorded honestly as such per decision_provenance.trust_level: claimed_repo_record), on PR #298 head 5fdcaff9779f0b81b2a26db2ec1ed29bbfb994a0 (canonical main ed8ca00b120064165f54c2ee9c8c44e946a0ef88, PR state OPEN/Draft/mergeable, diff limited to docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md + docs/owner-decisions/GAP-042/02-design.md, LIVE Owner Governance Lint SUCCESS, LIVE test-routes-guardrails SUCCESS on that reviewed head): 'Owner Gate-2 review on PR #298: CHANGES REQUESTED. Option A remains the preferred architectural direction (converge onto canonical roles/permissions/role_permissions/user_roles) — do NOT resurrect zena_* compat tables/views, do NOT pivot to Option C. But the design is not implementation-ready. Revise in this SAME session, in PR #298. Mandatory corrections: (1) Resolve custom_user_roles at Gate 2 — do not defer to implementation planning; trace the intended System/Custom/Project semantics and all evidence for whether the custom layer is genuinely part of the intended live design; preserve the existing 3-layer semantics unless evidence proves that layer is obsolete; if the layer is intended and the defect is simply a missing schema object, design the smallest proper custom_user_roles migration using established sibling-table conventions; do not silently remove or bypass the custom layer; make ONE explicit Gate-2 decision on this. (2) Correct the tenant-isolation design — tenant.isolation establishes authenticated tenant context but does not automatically scope arbitrary Role::query() calls; Src\\RBAC\\RoleController contains unscoped list/show/update/delete/store/sync behavior; after Option A starts reading the real roles table, prove exactly what the intended tenant semantics are for tenant-owned roles, nullable/global/system roles if legitimately supported, index/show, create, update/delete, permission sync, duplicate-name validation, role assignments; design explicit fail-closed query/write scoping; do not classify cross-tenant exposure as unrelated cleanup if Option A would make that exposure newly reachable. (3) Re-audit every live /api/v1/rbac/* route through controller to service/model after applying Option A mentally, at minimum independently reconcile RBACController::getUserEffectivePermissions() vs actual RBACManager public methods, RBACController::checkUserPermission() vs actual service methods, bulk/system/project/custom assignment calls and argument ordering, all direct consumers already inside the approved Gate-1 boundary; do NOT automatically absorb the two previously excluded incidental defects (missing AssignmentController::getUserRoles(); CompensationController/Src RBACMiddleware wiring) — keep those separate unless current governance requires otherwise; but any NEWLY identified defect inside the already-approved Src\\RBAC direct-consumer boundary that prevents the Gate-2 objective from being true must be explicitly designed or explicitly narrowed with a truthful objective; do not claim the whole live RBAC surface is restored if known routes remain broken. (4) Replace the 2-file implementation surface claim with the truthful exact authorized surface — separate production behavior files, schema/migration files if custom_user_roles is retained, tests/support cleanup (ensureSqliteZenaRbacTables, RbacApiTest), CI production-fidelity proof; no implementation yet, design/spec only. (5) Strengthen the acceptance contract with discriminating tests for: tenant A cannot list/show/update/delete/sync tenant B's role; role creation binds to the authoritative tenant per decided semantics; effective-permissions/check-permission live routes no longer fail on missing tables or service-contract mismatch; System/Custom/Project permission computation works per preserved 3-layer semantics; the custom_user_roles decision is proven on genuine MySQL; representative assignment paths write to the intended canonical assignment tables; no test-only shim can manufacture production-impossible RBAC schema. Also required: reconcile the Gate-2 Owner packet to gate_status: changes_requested and record this Owner correction truthfully WITHOUT erasing Round-0 history (append, don't overwrite). Constraints: after revision, re-present Gate 2 at awaiting_owner; do NOT self-approve, do NOT create an implementation plan, do NOT write code/tests/migrations, do NOT merge, do NOT start another Work ID.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-01T22:10:00+07:00"
  updated_at: "2026-09-01T23:40:00+07:00"
generated_by: agent
---

## Revision log

- **Round 0 (PR #298 head `5fdcaff9779f0b81b2a26db2ec1ed29bbfb994a0`):** Initial Gate-2 submission. Recommended Option A. Deferred the `custom_user_roles` schema gap to implementation planning. Did not design tenant-isolation scoping. Did not re-audit controller→service call contracts beyond the table-rename defect. Claimed a "2-file implementation surface."
- **Round 1 (this revision, PR #298, same branch):** Owner **CHANGES REQUESTED** — full verbatim directive recorded in `decision_provenance.owner_response_reference` above. Option A confirmed as the preferred direction; no pivot to Option C, no `zena_*` compat tables/views. Five mandatory corrections required before the design can be considered implementation-ready: (1) resolve `custom_user_roles` explicitly, not defer it; (2) design explicit fail-closed tenant scoping for `Role` reads/writes; (3) re-audit every live route's controller→service contract, explicitly designing or narrowing any newly-found in-boundary defect; (4) replace the "2-file" claim with a truthful, category-separated implementation surface; (5) strengthen the acceptance matrix with discriminating tenant/contract/3-layer/assignment tests. All five are addressed in this revision — see the corrected sections below and `docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md`'s own Round-1 revision note. `gate_status` returns to `awaiting_owner` per Owner instruction; this revision does not self-approve.

## Owner Summary

Module `Src\RBAC` bị lỗi vì 2 model (`Role`, `Permission`) trỏ vào 2 bảng CSDL đã bị đổi tên vĩnh viễn (`zena_roles`/`zena_permissions`) từ tháng 9/2025. Bảng chuẩn (`roles`/`permissions`) đã tồn tại, đã có dữ liệu thật, và chính là bảng mà cổng kiểm tra quyền (middleware `rbac:`) đang dùng — 2 model của `Src\RBAC` chỉ cần trỏ đúng vào 2 bảng đó là xong; không cần tạo bảng mới, không cần migration mới cho lỗi chính.

**Cập nhật Round 1:** việc chỉ trỏ lại bảng KHÔNG đủ để phục hồi toàn bộ mặt API RBAC — rà soát sâu hơn phát hiện 3 lỗi hợp đồng gọi hàm (controller gọi sai tên/thứ tự tham số của service) độc lập với lỗi đổi tên bảng, và việc trỏ lại bảng làm dữ liệu Role thật lần đầu tiên có thể truy cập được — nghĩa là phải thiết kế luôn cơ chế cách ly tenant (trước đây RoleController hoàn toàn không lọc theo tenant, chỉ vì bảng chưa tồn tại nên chưa từng lộ ra). Cả 2 điều này giờ đã được thiết kế tường minh (xem các mục bên dưới), không còn để ngỏ cho bước triển khai.

## Vấn đề vận hành

Xem `docs/owner-decisions/GAP-042/01-request.md` (Gate 1, đã được Owner phê duyệt) và `docs/audits/2026-09-01-gap-042-rbac-production-fidelity-evidence.md`.

## Phương án đã cân nhắc

**A — Trỏ lại `Src\RBAC\Models\Role`/`Permission` vào bảng chuẩn `roles`/`permissions`/`role_permissions`/`user_roles` (giữ nguyên tên class, chỉ đổi `$table` + tên bảng trung gian).** ĐỀ XUẤT CHỌN, Owner đã xác nhận giữ nguyên hướng này ở Round 1. Lý do: 2 cặp bảng vốn CÙNG MỘT bảng vật lý trong lịch sử (chỉ bị đổi tên, không phải 2 schema phát triển độc lập) — cột dữ liệu tương thích 100% (đã kiểm chứng), không cần migration mới cho lỗi chính, diff nhỏ nhất, sửa xong là hết split-brain ngay.

**B — Giữ class/API của `Src\RBAC` nhưng bọc qua một lớp adapter tương thích.** Không đề xuất — thêm độ phức tạp không cần thiết so với A, vì 2 schema đã tương thích sẵn.

**C — Gỡ bỏ hẳn model/service của `Src\RBAC`, chuyển toàn bộ sang dùng `App\Models\Role`/`Permission` trực tiếp.** Không đề xuất — Owner xác nhận Round 1: KHÔNG chuyển sang phương án này. Đây là thay đổi lớn nhất, đụng tới toàn bộ 5 controller + service, và có thể làm mất mô hình phân quyền 3 lớp (System/Custom/Project) hiện có nếu không port cẩn thận — cần một quyết định nghiệp vụ riêng, không phải quyết định kỹ thuật.

**Phương án bảng/view tương thích ngược (tái tạo `zena_roles`/`zena_permissions`) — Owner xác nhận Round 1: KHÔNG được làm.** Tái tạo 2 bảng cũ sẽ tạo lại đúng vấn đề "2 nguồn sự thật" mà Gate 1 đã phát hiện, và không có consumer nào thực sự cần tồn tại đúng cái TÊN `zena_roles`/`zena_permissions` — chỉ cần dữ liệu Role/Permission hoạt động đúng.

Chi tiết đầy đủ, bằng chứng tương thích schema, ma trận consumer, hợp đồng test chấp nhận: `docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md` (revised, Round 1).

## `custom_user_roles` — quyết định tường minh (Round 1, thay thế mục "chưa giải quyết" của Round 0)

**Quyết định: GIỮ NGUYÊN mô hình 3 lớp System/Custom/Project. Lớp "custom" là một phần thiết kế có chủ đích, không phải code chết/lỗi thời. Bổ sung migration `custom_user_roles` còn thiếu, theo đúng khuôn mẫu bảng anh em `project_user_roles`.**

Bằng chứng đã truy vết (chi tiết đầy đủ ở spec §2b): lớp "custom" có mặt nhất quán, đầy đủ ở MỌI tầng — hằng số `Role::SCOPE_CUSTOM`, model `UserRoleCustom` riêng với các trait/scope query riêng, 3 phương thức `RBACManager` (`assignCustomRole`/`getCustomPermissionsWithOverride`/`revokeRole`'s `'custom'` case), và một route/controller-method sống, nối dây đúng (`POST /api/v1/rbac/assign/custom`). Logic tính quyền hiệu lực (`computeEffectivePermissions()`) coi lớp custom là thành phần CỐT LÕI của phép giao (intersection) 3 lớp, không phải nhánh phụ. Không tìm thấy ghi chú "cần xoá", không có cơ chế thay thế, không có route bị gỡ. Điểm duy nhất chống lại: chưa có UI/frontend nào từng dùng lớp này — đây là bằng chứng "chưa được áp dụng", KHÔNG phải bằng chứng "đã quyết định khai tử" (Owner yêu cầu: chỉ khai tử nếu có bằng chứng khai tử thật sự — không có).

Thiết kế migration (không tạo file ở Gate 2 này, chỉ thiết kế): mirror đúng `project_user_roles` (không phải `system_user_roles`, vì `UserRoleCustom` dùng single ULID `id` primary key + cột `deleted_at` riêng, giống `UserRoleProject`, không giống `UserRoleSystem`'s composite key). Không có cột `tenant_id` riêng — việc cách ly tenant đi qua `role_id` → `roles.tenant_id` (giống `project_user_roles` đi qua `project_id` → `projects.tenant_id`).

## Cách ly tenant — thiết kế tường minh, fail-closed (Round 1, thay thế phần "chưa cần giải quyết" của Round 0)

**Tại sao không thể để ngỏ:** `tenant.isolation` middleware chỉ thiết lập tenant context đã xác thực — KHÔNG tự động giới hạn bất kỳ câu query `Role::` nào. Middleware `rbac:<code>` chỉ kiểm tra "user có quyền tên X không" — KHÔNG kiểm tra "request đang nhắm vào ĐÚNG dòng dữ liệu nào". Hôm nay, trên `main` hiện tại, lỗ hổng này KHÔNG thể khai thác được — vì bảng chưa tồn tại nên mọi câu query đều lỗi 500 trước khi kịp trả về bất kỳ dòng nào. Khi Phương án A trỏ lại bảng đúng, các câu query NÀY BẮT ĐẦU THÀNH CÔNG trên dữ liệu tenant thật — nghĩa là lỗ hổng xuyên-tenant lần đầu tiên trở nên khai thác được thật sự. Đây chính xác là điều Owner chỉ ra: sửa lỗi tên bảng mà không sửa luôn phạm vi tenant sẽ đổi một lỗi "không dùng được" thành một lỗi "rò rỉ dữ liệu tenant khác".

**Ngữ nghĩa tenant, truy vết từ bằng chứng có thật (không phải tự nghĩ ra):** `roles.tenant_id` nullable. MỌI role do code production tạo ra từ trước tới nay (qua `RoleSeeder`, kiểm tra toàn repo) đều có `tenant_id = null` — đây là bằng chứng quyết định cho ngữ nghĩa: `tenant_id IS NULL` = role toàn cục/hệ thống (mọi tenant đều thấy/dùng được); `tenant_id = <id>` = role thuộc sở hữu riêng của tenant đó. `RBACController::getRolesByScope()` (đã có sẵn trong phạm vi Gate 1) đã cài đặt MỘT PHẦN đúng ngữ nghĩa này — bằng chứng ngữ nghĩa này vốn đã có trong chính codebase, chỉ áp dụng chưa đồng nhất.

**Thiết kế cụ thể (chi tiết đầy đủ, bảng theo từng operation, ở spec §6):** mọi truy vấn danh sách/đọc/sửa/xoá/sync-permission/import theo ID phải thêm điều kiện `whereNull('tenant_id')->orWhere('tenant_id', $tenantId)` trước khi `find()`/thao tác — một target thuộc tenant khác sẽ tự nhiên rơi vào nhánh 404 "Role không tồn tại" ĐÃ CÓ SẴN của từng method (không cần thêm response code mới, đúng theo quy ước cách ly tenant đã có sẵn trong codebase — `Project::where('tenant_id', $tenantId)` ở `DashboardController`). Khi tạo role (`store`) với scope `custom`/`project`, `tenant_id` PHẢI được gán cứng từ tenant của request đã xác thực — KHÔNG BAO GIỜ nhận `tenant_id` do client tự gửi lên.

**Một ràng buộc CSDL có thật, đã xác nhận, KHÔNG được gỡ bỏ hay né tránh ở thiết kế này:** cột `roles.name` có index unique TOÀN BẢNG (không phải theo từng tenant) — `roles_name_unique`, xác nhận lại từ Gate 1. Nghĩa là 2 tenant khác nhau KHÔNG THỂ cùng tạo 2 role custom trùng tên — đây là ràng buộc schema có sẵn từ trước, GAP-042 không tạo ra và không sửa nó (muốn sửa sẽ cần một migration riêng, được Owner uỷ quyền riêng — không phải phạm vi Gate 2 này).

## Lỗi hợp đồng gọi hàm mới phát hiện (Round 1) — không tự động hấp thụ, không tự động bỏ qua

Rà soát lại toàn bộ route sống `/api/v1/rbac/*` theo yêu cầu Owner (độc lập với lỗi đổi tên bảng) phát hiện **3 lỗi thật, mới, nằm trong đúng phạm vi đã duyệt** (không phải 2 lỗi phụ đã loại trừ ở Gate 1):

1. `RBACController::getUserEffectivePermissions()` gọi phương thức `RBACManager::getUserEffectivePermissions()` — phương thức này KHÔNG TỒN TẠI (tên thật: `calculateEffectivePermissions()`).
2. `RBACController::checkUserPermission()` gọi phương thức `RBACManager::userHasPermission()` — KHÔNG TỒN TẠI (tên thật: `hasPermission()`).
3. `RBACController::bulkAssignRoles()` gọi `RBACManager::assignProjectRole($userId, $projectId, $roleId, ...)` — SAI THỨ TỰ tham số (chữ ký thật: `($userId, $roleId, $projectId)`) — gán role dự án qua API này âm thầm KHÔNG ghi được dòng nào dù API báo thành công.

Và 3 route dưới `assignments/projects/*` trỏ tới các phương thức `AssignmentController` không tồn tại (`getProjectUsers`, `assignProjectRole`, `removeProjectRole`) — cùng dạng lỗi với `getUserRoles()` đã ghi nhận ở Gate 1, nhưng đây là 3 route KHÁC, phát hiện MỚI ở Round 1 này.

**Theo đúng chỉ đạo Owner: KHÔNG tự động gộp sửa các lỗi này vào "xong hết" của Phương án A, nhưng CŨNG KHÔNG bỏ qua** — cả 6 lỗi trên được thiết kế tường minh (mục tiêu sửa: đúng tên/thứ tự tham số, thêm 3 phương thức còn thiếu) trong `docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md` §2a, và liệt kê riêng trong phạm vi triển khai (mục dưới) — KHÔNG lẫn với 2 lỗi phụ đã loại trừ ở Gate 1 (`AssignmentController::getUserRoles()`; `CompensationController`/`Src\RBAC\Middleware\RBACMiddleware`), vẫn giữ nguyên loại trừ theo đúng chỉ đạo Owner.

## Phạm vi triển khai — tách đúng theo từng loại (Round 1, thay thế "2 file" của Round 0)

- **A. File hành vi production:** 2 model (`$table` + tên bảng trung gian) + sửa 3 lỗi hợp đồng gọi hàm ở `RBACController`/`AssignmentController` + cách ly tenant ở `RoleController`/`RBACController::getRolesByScope`/`PermissionMatrixService`.
- **B. File schema/migration:** 1 migration mới `custom_user_roles` (theo quyết định ở trên).
- **C. Dọn dẹp test/support:** xoá `ensureSqliteZenaRbacTables()`/`zenaRbacBootstrapSchema()` khỏi `tests/TestCase.php`; cập nhật `tests/Feature/RbacApiTest.php`; thêm test mới cho ma trận chấp nhận (12 mục, xem spec §10).
- **D. Bằng chứng CI production-fidelity:** một CI job hoặc quy trình tái lập được, chứng minh ma trận chấp nhận trên MySQL 8.0 thật, không qua shim đã xoá.

Chi tiết đầy đủ từng mục: spec §13a.

## Loại trừ rõ ràng

Không code, không test, không migration, không thay đổi cấu hình deploy nào được thực hiện ở Gate 2 này (kể cả bản sửa Round 1 này) — chỉ thiết kế. Không sửa 2 lỗi phụ đã ghi nhận ở Gate 1 (`AssignmentController::getUserRoles()` thiếu method; `CompensationController`/`Src\RBAC\Middleware\RBACMiddleware` thiếu tham số) — giữ nguyên loại trừ theo đúng chỉ đạo Owner. Không đụng GAP-041/GAP-045/deploy production. Không đụng bất kỳ hồ sơ GAP-040/GAP-044/GAP-047 nào. **Không còn loại trừ, đã giải quyết ở Round 1 này:** câu hỏi `custom_user_roles` (đã quyết định: giữ + thêm migration) và thiết kế cách ly tenant (đã thiết kế tường minh) — Round 0 để ngỏ cả 2, Owner yêu cầu giải quyết ngay ở Gate 2, không để tới bước triển khai.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt Phương án A đã sửa (bao gồm quyết định `custom_user_roles`, thiết kế cách ly tenant, và 3 lỗi hợp đồng gọi hàm mới phát hiện) làm thiết kế Gate 2, cho phép chuyển sang bước lập kế hoạch triển khai (implementation plan) — triển khai thật vẫn cần một quyết định Gate 2 APPROVE riêng trước khi bắt đầu viết code.

## Decision Needed

Owner chọn một trong: Approve Option A đã sửa / Request further changes / Decline.

## What the owner is NOT being asked to decide

Không được yêu cầu duyệt bất kỳ dòng code cụ thể nào (đó là bước implementation plan, sau khi Gate 2 được duyệt). Không được yêu cầu quyết định số phận của các controller/service trùng lặp mồ côi (`App\Http\Controllers\{RoleController,...}`) hay 2 lỗi phụ đã loại trừ. Không được yêu cầu quyết định việc `roles.name` có nên chuyển sang unique theo từng tenant hay không — đây là câu hỏi tương lai riêng, không thuộc thiết kế này.
