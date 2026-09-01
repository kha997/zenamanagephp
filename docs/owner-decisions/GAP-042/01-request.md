---
work_id: GAP-042
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-01-gap-042-rbac-production-fidelity-evidence.md
  plan: null
  branch: docs/GAP-042-gate1-production-fidelity
  pr: "https://github.com/kha997/zenamanagephp/pull/297"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-01T21:26:00+07:00"
  owner_response_reference: "GAP-042 Gate 1 decision (relayed via coordinator session, not a directly witnessed live Owner chat interaction in this agent session — recorded honestly as such per decision_provenance.trust_level: claimed_repo_record): 'The Owner has reviewed your GAP-042 Gate 1 evidence and made a decision: APPROVED. The reproduced production-fidelity defect and the Gate-1 problem boundary are approved as you documented them.' Relayed at exact PR #297 head 0f4c85db012ed9dd562601c94afcd3f3fbac1974 (canonical main at submission time ed8ca00b120064165f54c2ee9c8c44e946a0ef88, PR state OPEN/Draft/mergeable, diff limited to docs/audits/2026-09-01-gap-042-rbac-production-fidelity-evidence.md + docs/owner-decisions/GAP-042/01-request.md, LIVE Owner Governance Lint SUCCESS, LIVE test-routes-guardrails SUCCESS on that exact head after a metadata-only PR-body fix — first non-empty line corrected to the literal 'Work ID: GAP-042' declaration required by scripts/ci/extract-work-id.sh; no evidence content was changed to obtain this). This approval accepts: the Gate-1 finding (Src\\RBAC\\Models\\Role/Permission, tables zena_roles/zena_permissions, are live-routed via /api/v1/rbac/* and broken on any correctly-migrated production MySQL 8.0 database — LIVE-reproduced via clean migrate:fresh, real Eloquent probe, and a full real-HTTP/Sanctum-auth/tenant round trip returning HTTP 500) and the proposed smallest Gate 2 problem boundary (Src\\RBAC\\Models\\Role/Permission and their direct consumers — Src\\RBAC\\Services\\RBACManager and the 5 controllers in src/RBAC/Controllers/ — explicitly excluding the two incidental adjacent defects noted in the evidence: missing AssignmentController::getUserRoles(), and the CompensationController/Src\\RBAC\\Middleware\\RBACMiddleware constructor-wiring defect). This approval authorizes GATE 2 DESIGN ONLY; it does not authorize implementation. Owner directed: record this Gate-1 approval in 01-request.md only, do not rewrite or reinterpret the Gate-1 evidence document; keep PR #297 as Draft (it is the Gate-1 historical record, not to be merged); Gate 2 design work must evaluate at minimum (A) converging Src\\RBAC consumers onto the canonical roles/permissions/role_permissions/user_roles tables/models, (B) a thin compatibility/adapter layer preserving Src\\RBAC classes/API against the standard schema, and (C) retiring/de-duplicating the Src\\RBAC model/service path in favor of App\\Models\\Role/Permission — a legacy-compatibility-table/view approach may be analyzed but recreating permanent zena_roles/zena_permissions duplicate authorities merely to make old code/tests pass is not to be recommended absent compelling evidence; Gate 2 must not start implementation, must not touch GAP-041/GAP-045 or production deployment, and must remain gate_status: awaiting_owner / owner_decision.value: none pending a separate Owner Gate-2 decision."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-01T00:00:00+07:00"
  updated_at: "2026-09-01T21:26:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-042 Gate 1 (decision relayed via the coordinating session — see `decision_provenance.owner_response_reference` above for the exact, honestly-attributed text and reviewed head). Reviewed exact PR #297 head `0f4c85db012ed9dd562601c94afcd3f3fbac1974`, canonical main at submission time `ed8ca00b120064165f54c2ee9c8c44e946a0ef88`, both `Owner Governance Lint` and `test-routes-guardrails` LIVE-green on that exact head after a metadata-only PR-body fix (first non-empty line corrected to the literal `Work ID: GAP-042` declaration `scripts/ci/extract-work-id.sh` requires; no evidence content changed).

The Gate-1 finding is accepted as documented: `Src\RBAC\Models\Role`/`Permission` (tables `zena_roles`/`zena_permissions`) are live-routed via `/api/v1/rbac/*` and broken on any correctly-migrated production MySQL 8.0 database, LIVE-reproduced end-to-end (clean `migrate:fresh`, no test harness, real Eloquent probe, and a full real-HTTP/Sanctum-auth/tenant round trip returning HTTP 500 on `GET /api/v1/rbac/roles` and `/permissions`). The proposed smallest Gate 2 problem boundary is accepted: `Src\RBAC\Models\Role`/`Permission` and their direct consumers (`Src\RBAC\Services\RBACManager`, the 5 controllers in `src/RBAC/Controllers/`) — explicitly excluding the two incidental adjacent defects noted in the evidence (missing `AssignmentController::getUserRoles()`; the `CompensationController`/`Src\RBAC\Middleware\RBACMiddleware` constructor-wiring defect).

This approval authorizes **GATE 2 DESIGN ONLY**. Implementation is **not authorized**. PR #297 remains **Draft** — it is the Gate-1 historical record and is not to be merged. Gate 2 must evaluate, at minimum: (A) converging `Src\RBAC` consumers onto the canonical `roles`/`permissions`/`role_permissions`/`user_roles` tables/models; (B) a thin compatibility/adapter layer preserving `Src\RBAC` classes/API against the standard schema; (C) retiring/de-duplicating the `Src\RBAC` model/service path in favor of `App\Models\Role`/`Permission`. A legacy-compatibility-table/view approach may be analyzed, but recreating permanent `zena_roles`/`zena_permissions` duplicate authorities merely to make old code/tests pass is not to be recommended absent compelling evidence. Gate 2 must not start implementation, must not touch GAP-041/GAP-045 or production deployment, and must remain `gate_status: awaiting_owner` / `owner_decision.value: none` pending a separate Owner Gate-2 decision.

## Owner Summary

Module quản lý Vai trò & Quyền (`Src\RBAC`, mounted thật tại `/api/v1/rbac/*`) hiện đang **thật sự lỗi trên production**: các endpoint quản lý roles/permissions (ví dụ `GET /api/v1/rbac/roles`, `GET /api/v1/rbac/permissions`) trả về lỗi máy chủ 500 ngay cả khi người dùng đăng nhập đúng, đúng tenant, đúng quyền — vì đoạn code xử lý bên trong các endpoint này tham chiếu tới 2 bảng CSDL (`zena_roles`, `zena_permissions`) đã bị đổi tên vĩnh viễn thành `roles`/`permissions` từ tháng 9/2025 và không bao giờ được tạo lại. Điều này đã được tái hiện thật (LIVE) trên MySQL 8.0 sạch, HTTP request thật, không dùng bất kỳ cơ chế giả lập nào của bộ test.

## Vấn đề vận hành

Bất kỳ ai gọi tới các API quản lý Role/Permission của module `Src\RBAC` (danh sách roles, danh sách permissions, và ~18 endpoint liên quan khác dưới `/api/v1/rbac/*`) trên một cơ sở dữ liệu production được khởi tạo đúng quy trình (`php artisan migrate:fresh`) sẽ nhận lỗi 500, không phải dữ liệu mong đợi. Cổng kiểm tra quyền (middleware `rbac:role.view`, v.v.) hoạt động đúng và cho qua — lỗi xảy ra ở lớp xử lý nghiệp vụ bên trong, sau khi đã qua được cổng phân quyền.

## Người dùng bị ảnh hưởng

Bất kỳ admin/PM nào cố quản lý Role/Permission qua module `Src\RBAC`'s live API (không phải qua module RBAC "chuẩn" khác trong app, vốn vẫn hoạt động bình thường cho việc đăng nhập/phân quyền cơ bản của người dùng). Chức năng CRUD Role/Permission qua route `/api/v1/rbac/*` không dùng được ngay từ lần deploy production đầu tiên.

## Bằng chứng

Tái hiện LIVE, từng bước: (1) dựng container MySQL 8.0 sạch hoàn toàn; (2) chạy đúng lệnh migration production thật (`php artisan migrate:fresh`, không qua bất kỳ helper test nào); (3) kiểm tra trực tiếp danh sách bảng — xác nhận `zena_roles`/`zena_permissions` không tồn tại; (4) gọi đúng đoạn code Eloquent mà controller thật sự dùng — nhận lỗi "Base table or view not found"; (5) khởi động server thật, tạo user/tenant/token thật, gọi HTTP request thật tới `/api/v1/rbac/roles` và `/api/v1/rbac/permissions` — cả hai đều trả về HTTP 500 với đúng lỗi đó; (6) chạy lại đúng bộ test hiện có trên CI (`RbacApiTest.php`) trên cùng cơ sở dữ liệu đó — bộ test PASS, vì file test dùng một cơ chế riêng (chỉ có trong test) tự tạo lại 2 bảng đó mỗi lần chạy — cơ chế này không tồn tại ở production. Toàn bộ chi tiết, lệnh, kết quả nguyên văn: `docs/audits/2026-09-01-gap-042-rbac-production-fidelity-evidence.md`.

## Tác động nếu không xử lý

Module Role/Permission Management (`Src\RBAC`) không dùng được trên production thật ngay từ ngày đầu, trong khi CI hiện tại báo xanh (green) một cách sai lệch — bộ test duy nhất phủ surface này chỉ pass nhờ cơ chế giả lập chỉ-dành-cho-test, không phản ánh production thật. Nếu deploy mà không xử lý, bất kỳ thao tác quản lý role/permission nào qua API này sẽ crash ngay lập tức.

## Phạm vi đề xuất

Gate 1 chỉ xác nhận: (1) đây là lỗi thật, tái hiện được end-to-end trên MySQL 8.0 sạch, HTTP thật, không phải suy đoán; (2) phạm vi ảnh hưởng là lớp model/business-logic của module `Src\RBAC` (không phải cổng phân quyền `rbac:` middleware, vẫn hoạt động đúng); (3) cần một quyết định thiết kế ở Gate 2 về cách khắc phục (đổi model trỏ về bảng chuẩn, tạo lại bảng zena_ tương thích ngược, hay phương án khác) — Gate 1 không chọn phương án kỹ thuật.

## Loại trừ rõ ràng

Không sửa bất kỳ code production nào (`src/RBAC/**`, `app/Http/Middleware/**`, migration nào). Không đụng tới `OPERATIONAL_GAP_REGISTER.md`. Không đụng tới bất kỳ hồ sơ/quyết định nào của GAP-040, GAP-041, GAP-044, GAP-045. Không mở Gate 2. Toàn bộ tái hiện LIVE dùng container Docker dùng-một-lần, đã dọn dẹp sau khi thu thập bằng chứng — không có môi trường production nào được tạo/truy cập/thay đổi. Hai lỗi phụ phát hiện tình cờ trong quá trình test (route `AssignmentController::getUserRoles()` thiếu method; middleware `Src\RBAC\Middleware\RBACMiddleware` trên `CompensationController` thiếu tham số bắt buộc) được ghi nhận nhưng **không** thuộc phạm vi GAP-042 — là lỗi độc lập, khác cơ chế.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt Gate 1 để tiến hành Gate 2 thiết kế phương án khắc phục cho lớp model/business-logic của module `Src\RBAC` (phạm vi nhỏ nhất: `Src\RBAC\Models\Role`/`Permission` và các consumer trực tiếp của chúng — `Src\RBAC\Services\RBACManager`, 5 controller trong `src/RBAC/Controllers/`).

## Decision Needed

Owner chọn một trong: Approve (tiến hành Gate 2 thiết kế) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Owner không được yêu cầu phê duyệt bất kỳ thay đổi code, migration, hay cơ chế kỹ thuật cụ thể nào ở bước này — chỉ xác nhận vấn đề là có thật và đáng để thiết kế Gate 2. Owner không được yêu cầu quyết định về GAP-040/GAP-041/GAP-044/GAP-045, hay về 2 lỗi phụ được ghi nhận ở mục "Loại trừ rõ ràng" (những lỗi đó, nếu cần xử lý, phải đăng ký Work ID riêng).
