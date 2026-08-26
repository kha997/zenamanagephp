---
work_id: GAP-046
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-25-gap-046-service-line-semantics-audit.md
  plan: null
  branch: docs/GAP-046-service-line-semantics-audit
  pr: "https://github.com/kha997/zenamanagephp/pull/287"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-25T05:54:54Z"
  owner_response_reference: "Owner decision relayed via coordinator session, 2026-08-25: 'GAP-046 GATE 1 — OWNER DECISION / Decision: APPROVE. I approve GAP-046 Gate 1 on the currently verified Gate-1 submission: PR #287, Gate-1 reviewed head 6f9cd2d9cb9162d807c9e3688b766fb097912554. Canonical main independently re-verified by Owner: c3a1226059bcf5a573aad1eebf8f1333331d9ad2, zero drift. LIVE CI on the exact reviewed head has now completed: Owner Governance Lint: SUCCESS, Routes Guardrails: SUCCESS. This approval confirms: the problem is real; the Phase-A semantics audit is sufficient; GAP-046 may proceed to Gate 2 design. It DOES NOT authorize implementation.' Both CI results independently re-verified live by the agent via `gh pr checks 287` at the same head immediately before this record was written (Owner Governance Lint: pass 27s; test-routes-guardrails: pass 1m56s) — both matched the Owner's stated results. Owner additionally issued a MANDATORY OWNER SCOPE CLARIFICATION FOR GATE 2, binding on the Gate-2 design that follows this approval: GAP-046 Phase B owns only the canonical Service-Line value set, the Opportunity/Project membership persistence mechanism, the CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN provenance representation, migration/backfill mechanics strictly necessary to establish that foundation, and narrowly necessary compatibility mechanics — and explicitly must NOT implement: (1) fixing the live 'architecture' default write-path (assigned to the future 'CRM Classification UX & Gates' slice), (2) runtime Opportunity→Project Service-Line propagation (assigned to the future 'Opportunity→Project Propagation & Project Classification UX' slice), (3) CRM stage gates/classification UX, (4) Quote Scope Snapshot, (5) Contract Service-Line implementation, (6) Portfolio Membership behavior, (7) Project OPPM, (8) Operations Control Tower, (9) Finance/Treasury behavior. Owner also directed that legacy `architecture` rows must NOT be backfilled as CONFIRMED DESIGN — provenance uncertainty must be preserved per the canonical model. This packet's evidence document (docs/audits/2026-08-25-gap-046-service-line-semantics-audit.md) is preserved byte-unchanged by this decision record, per this repository's established convention (see GAP-044/01-request.md)."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-25T05:34:18Z"
  updated_at: "2026-08-25T05:54:54Z"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-046 Gate 1 at reviewed head `6f9cd2d9cb9162d807c9e3688b766fb097912554` (PR #287), against independently re-verified canonical main `c3a1226059bcf5a573aad1eebf8f1333331d9ad2` (zero drift), with both LIVE CI checks on that exact head passing (`Owner Governance Lint`, `test-routes-guardrails`) — re-confirmed by the agent via `gh pr checks 287` immediately before this record was written. This approval confirms the problem is real and the Phase-A semantics audit is sufficient; it authorizes proceeding to Gate 2 design only, and does **not** authorize any implementation.

**Binding Gate-2 scope clarification issued with this approval** (full verbatim text in `decision_provenance.owner_response_reference` above): GAP-046 Phase B's Gate 2 design must confine itself to the canonical Service-Line value set, the Opportunity/Project membership persistence mechanism, the CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN provenance representation, and migration/backfill mechanics strictly necessary to establish that foundation (backfilling legacy values conservatively, never as CONFIRMED). It must not design or scope: the live-default write-path fix, runtime Opportunity→Project propagation wiring, CRM classification UX/gates, Quote Scope Snapshot, Contract Service-Line implementation, Portfolio Membership, Project OPPM, Control Tower, or Finance/Treasury — each belongs to its own future Work ID per the SSOT §14 roadmap. `BusinessKpiService`/`DesignItemPageController` (Gate 1's discovered consumers) are not automatically implementation surfaces; compatibility treatment for them is in scope only if Gate 2 proves it strictly necessary.

## Owner Summary
Đội kỹ thuật đã rà soát toàn bộ hệ thống phân loại "Service Line" (Design/Construction/Inspection) từ Lead → Opportunity → Quote → Contract → Project. Hiện tại chỉ có một cột duy nhất (`Opportunity.service_category`), là giá trị đơn (không đa giá trị), và tự động mặc định thành "architecture" một cách âm thầm ở 3 chỗ trong code — vi phạm đúng quy tắc đã được Owner duyệt trong tài liệu SSOT ngày 2026-08-15. Đây là Phase A (chỉ điều tra, không sửa gì) của một Work ID lớn hơn đã được chính SSOT đó lên kế hoạch trước.

## Vấn đề vận hành
Khi tạo Opportunity (qua API, hoặc qua chuyển đổi từ Lead) mà người dùng không chọn rõ loại dịch vụ, hệ thống tự gán "architecture" — không có cách nào phân biệt "người dùng thực sự chọn Architecture" với "không ai chọn gì cả." Giá trị mặc định sai này sau đó không được sao chép sang Project/Contract khi chuyển đổi (Project không có trường phân loại nào), nhưng lại được đọc lại ở 2 nơi đang chạy thật: báo cáo hiệu suất theo loại dịch vụ (`BusinessKpiService`) và gợi ý AI cho hạng mục thiết kế (`DesignItemPageController`) — cả hai đều bị lệch về phía "Architecture" một cách âm thầm.

## Người dùng bị ảnh hưởng
Nhân viên sales tạo Opportunity/chuyển đổi Lead mà quên chọn loại dịch vụ (mặc định âm thầm thành Architecture); người xem báo cáo hiệu suất CRM theo loại dịch vụ (số liệu bị lệch); người dùng tính năng gợi ý AI cho hạng mục thiết kế trên các dự án không phải kiến trúc (gợi ý bị lệch ngữ cảnh).

## Bằng chứng
Đọc trực tiếp mã nguồn hiện tại trên `main` (không suy đoán): 3 nơi độc lập gán mặc định "architecture" (2 controller + 1 cột database); không có trường phân loại nào trên Project/Quote/Contract; test hiện tại gán cứng "architecture" ở hơn 10 file test thay vì kiểm thử đa dạng loại dịch vụ; không có factory/seeder nào từng thiết lập trường này một cách có chủ đích. Toàn bộ chi tiết, kèm số dòng file cụ thể, trong `docs/audits/2026-08-25-gap-046-service-line-semantics-audit.md`.

## Tác động nếu không xử lý
Không thể triển khai bất kỳ tính năng nào cần biết dự án thuộc loại dịch vụ nào một cách tin cậy (OPPM, Portfolio, Control Tower — đều đã được SSOT quy hoạch dựa trên giả định có phân loại đáng tin cậy). Báo cáo CRM và gợi ý AI tiếp tục sai lệch âm thầm. Mỗi slice tương lai phụ thuộc vào phân loại dịch vụ sẽ phải tự vá lỗi này một cách rời rạc thay vì có một nền tảng chung.

## Phạm vi đề xuất
Xác nhận phạm vi Phase A (điều tra) đã hoàn tất và đầy đủ bằng chứng; nếu được duyệt, Phase B (xây nền tảng: giá trị Service Line đa giá trị + cơ chế thành viên Opportunity/Project + trường nguồn gốc dữ liệu CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN) sẽ là một Gate 2 riêng, thiết kế schema cụ thể chưa được quyết ở đây.

## Loại trừ rõ ràng
Không đề xuất bất kỳ thay đổi migration/model/controller/service/route/UI nào ở bước này. Không gộp: CRM Classification UX & Gates, Quote Scope Snapshot, Contract Service-Line migration, Portfolio Membership Migration, Project OPPM (Issue #248), Operations Control Tower, Finance Control, Project Treasury, GAP-041/GAP-042/GAP-045 (đều là các work item CI/test-infrastructure không liên quan, đã xác nhận qua hồ sơ quyết định riêng của chúng). Không xác định lại lịch sử phân bố dữ liệu production thật — dữ liệu đó hiện KHÔNG có sẵn trong repo để kiểm tra và được báo cáo rõ là chưa biết, không suy đoán số liệu.

## Đề xuất
Đội kỹ thuật đề xuất: Owner phê duyệt Gate 1 để tiến sang Gate 2 (thiết kế Phase B) — không phát hiện xung đột kiến trúc hay rào cản nào khiến ranh giới phạm vi đề xuất trở nên bất khả thi; khoảng trống bằng chứng duy nhất (phân bố dữ liệu production thật) là câu hỏi cần xử lý ở thời điểm Phase B, không phải rào cản Gate 1/Gate 2.

## Decision Needed
Owner chọn một trong: Approve để tiến sang thiết kế (Gate 2) / Yêu cầu thêm thông tin / Từ chối / Hoãn lại.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ thay đổi code, schema, migration, hay cơ chế kỹ thuật cụ thể nào ở bước này — chỉ xác nhận vấn đề có thật, phạm vi điều tra đã đủ, và đáng để tiến hành thiết kế Gate 2. Owner cũng không được yêu cầu quyết định thiết kế schema chính xác (giá trị đa chọn được lưu thế nào, tên bảng join, v.v.) — đó là quyết định của Gate 2.
