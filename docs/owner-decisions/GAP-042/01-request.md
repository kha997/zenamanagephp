---
work_id: GAP-042
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: docs/audits/2026-09-01-gap-042-rbac-production-fidelity-evidence.md
  plan: null
  branch: docs/GAP-042-gate1-production-fidelity
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-01T00:00:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-01T00:00:00+07:00"
  updated_at: "2026-09-01T00:00:00+07:00"
generated_by: agent
---

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
