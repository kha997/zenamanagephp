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
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-01T22:10:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-01T22:10:00+07:00"
  updated_at: "2026-09-01T22:10:00+07:00"
generated_by: agent
---

## Owner Summary

Module `Src\RBAC` bị lỗi vì 2 model (`Role`, `Permission`) trỏ vào 2 bảng CSDL đã bị đổi tên vĩnh viễn (`zena_roles`/`zena_permissions`) từ tháng 9/2025. Bảng chuẩn (`roles`/`permissions`) đã tồn tại, đã có dữ liệu thật, và chính là bảng mà cổng kiểm tra quyền (middleware `rbac:`) đang dùng — 2 model của `Src\RBAC` chỉ cần trỏ đúng vào 2 bảng đó là xong; không cần tạo bảng mới, không cần migration mới cho lỗi chính.

## Vấn đề vận hành

Xem `docs/owner-decisions/GAP-042/01-request.md` (Gate 1, đã được Owner phê duyệt) và `docs/audits/2026-09-01-gap-042-rbac-production-fidelity-evidence.md`.

## Phương án đã cân nhắc

**A — Trỏ lại `Src\RBAC\Models\Role`/`Permission` vào bảng chuẩn `roles`/`permissions`/`role_permissions`/`user_roles` (giữ nguyên tên class, chỉ đổi `$table` + tên bảng trung gian).** ĐỀ XUẤT CHỌN. Lý do: 2 cặp bảng vốn CÙNG MỘT bảng vật lý trong lịch sử (chỉ bị đổi tên, không phải 2 schema phát triển độc lập) — cột dữ liệu tương thích 100% (đã kiểm chứng), không cần migration mới, diff nhỏ nhất, sửa xong là hết split-brain ngay.

**B — Giữ class/API của `Src\RBAC` nhưng bọc qua một lớp adapter tương thích.** Không đề xuất — thêm độ phức tạp không cần thiết so với A, vì 2 schema đã tương thích sẵn.

**C — Gỡ bỏ hẳn model/service của `Src\RBAC`, chuyển toàn bộ sang dùng `App\Models\Role`/`Permission` trực tiếp.** Không đề xuất làm ngay — đây là thay đổi lớn nhất, đụng tới toàn bộ 5 controller + service, và có thể làm mất mô hình phân quyền 3 lớp (System/Custom/Project) hiện có nếu không port cẩn thận — cần một quyết định nghiệp vụ riêng, không phải quyết định kỹ thuật.

**Phương án bảng/view tương thích ngược (tái tạo `zena_roles`/`zena_permissions`) đã được cân nhắc theo yêu cầu Owner — không đề xuất**, vì tái tạo 2 bảng cũ sẽ tạo lại đúng vấn đề "2 nguồn sự thật" mà Gate 1 đã phát hiện, và không có consumer nào thực sự cần tồn tại đúng cái TÊN `zena_roles`/`zena_permissions` — chỉ cần dữ liệu Role/Permission hoạt động đúng.

Chi tiết đầy đủ, bằng chứng tương thích schema, ma trận consumer, hợp đồng test chấp nhận: `docs/superpowers/specs/2026-09-01-gap-042-rbac-model-consolidation-design.md`.

## Phát hiện mới trong lúc thiết kế (chưa giải quyết, cần Owner lưu ý)

`Src\RBAC\Services\RBACManager` (nằm trong phạm vi Gate 1 đã duyệt) còn phụ thuộc bảng `custom_user_roles` — bảng này **chưa từng có migration nào tạo ra**, độc lập với lỗi đổi tên `zena_*`. Đây là một lỗ hổng schema thứ hai, phát hiện trong lúc rà toàn bộ `RBACManager`. Thiết kế này KHÔNG giải quyết câu hỏi đó — chỉ ghi nhận, để lại cho bước lập kế hoạch triển khai quyết định tường minh (tạo migration mới, hay xác nhận lớp "custom" chưa từng được dùng thật).

## Loại trừ rõ ràng

Không code, không test, không migration, không thay đổi cấu hình deploy nào được thực hiện ở Gate 2 này — chỉ thiết kế. Không sửa 2 lỗi phụ đã ghi nhận ở Gate 1 (`AssignmentController::getUserRoles()` thiếu method; `CompensationController`/`Src\RBAC\Middleware\RBACMiddleware` thiếu tham số). Không đụng GAP-041/GAP-045/deploy production. Không đụng bất kỳ hồ sơ GAP-040/GAP-044/GAP-047 nào.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt Phương án A làm thiết kế Gate 2, cho phép chuyển sang bước lập kế hoạch triển khai (implementation plan) — triển khai thật vẫn cần một quyết định Gate 2 APPROVE riêng trước khi bắt đầu viết code.

## Decision Needed

Owner chọn một trong: Approve Option A / Request changes (đề xuất phương án khác hoặc sửa A) / Decline.

## What the owner is NOT being asked to decide

Không được yêu cầu duyệt bất kỳ dòng code cụ thể nào (đó là bước implementation plan, sau khi Gate 2 được duyệt). Không được yêu cầu quyết định câu hỏi `custom_user_roles` ngay bây giờ — chỉ cần xác nhận đã biết vấn đề này tồn tại và sẽ được xử lý tường minh ở bước triển khai. Không được yêu cầu quyết định số phận của các controller/service trùng lặp mồ côi (`App\Http\Controllers\{RoleController,...}`) hay 2 lỗi phụ đã loại trừ.
