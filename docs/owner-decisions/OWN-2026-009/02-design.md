---
work_id: OWN-2026-009
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md
  plan: null
  branch: docs/OWN-2026-009-one-page-management-ssot-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/262
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-15T00:57:51+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-15T00:57:51+07:00"
  updated_at: "2026-08-15T00:57:51+07:00"
generated_by: agent
---

# OWN-2026-009 — ZENA One-Page Management Canonical SSOT: Gate 2 Owner Packet

**Status:** Gate 1 approved (2026-08-15, binding scope recorded in `01-request.md`). Gate 2 awaiting Owner review. No implementation, migration, schema change, route, controller, service, test, or merge is authorized until Gate 3 — and this work item authorizes no implementation at all; it only canonicalizes shared semantics for future slices to reference.

Full normative content: `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` (15 sections). This packet summarizes it for Owner decision; it does not restate every rule.

---

## 1. Owner Summary

PR #257 and PR #245 describe a whole future architecture (One-Page Management, Service Line taxonomy, Contract/Finance Control, Project Treasury) across ~2,300 lines of conversational design. Gate 2 proposes freezing the parts of that architecture that must stay consistent across every future implementation slice — one canonical SSOT document — so no future slice re-derives or drifts on the same semantics differently. The SSOT restates nothing about UI, exact field names, or thresholds; it only fixes the boundaries and invariants that, if inconsistent between slices, would corrupt data or mislead the business (e.g. a slice inventing its own "profit" definition, or two slices building two receivables engines).

## 2. Trước / Sau

**Trước:** Shared semantics exist only inside 4 long conversational design documents (PR #257 ×3, PR #245 ×1) plus 3 Owner comments on Issue #248 — no single normative reference; each future slice's Gate 2 would have to re-read and re-interpret all of them.

**Sau:** One governed document (`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`) is the SSOT for: product hierarchy, Service Line taxonomy, classification-maturity funnel, Project Health read-model contract, Project OPPM boundary, Contract/Finance/Treasury boundaries, and the financial invariants (Cost≠Cash≠Revenue≠Profit, etc.). Every future slice's own Gate 2 cites this document instead of re-deriving these rules from the 4 source PRs.

## 3. Vai trò bị ảnh hưởng

Không có vai trò người dùng cuối nào bị ảnh hưởng — đây là tài liệu quản trị nội bộ (governance), không có runtime/UI. Vai trò bị ảnh hưởng là quy trình: agent/kỹ sư kỹ thuật thực hiện các slice tương lai (CRM, Project OPPM, Contract Control, Finance Control, Treasury...) và Owner khi duyệt Gate 1/2 cho từng slice đó.

## 4. Được phép / Không được phép

**Được phép (sau khi Gate 2 này được duyệt):** trích dẫn `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` làm SSOT trong Gate 1/2 packet của bất kỳ slice tương lai nào (§14 của SSOT); chuẩn bị Gate 3 cho chính OWN-2026-009 (chỉ merge tài liệu, không có runtime).

**Không được phép:** bất kỳ implementation nào dựa trên tài liệu này mà không qua Work ID + Gate lifecycle riêng của chính slice đó; merge hoặc đóng PR #257/#245; tự phê duyệt Gate 3 của chính OWN-2026-009; mở rộng phạm vi sang GAP-036 hay bất kỳ gap vận hành nào khác.

## 5. Trạng thái và bước tiếp theo

- Gate 1: APPROVED (2026-08-15).
- Gate 2: awaiting_owner (packet này).
- Nếu Owner Approve: chuẩn bị Gate 3 (release quyết định merge PR #262 vào `main` — vẫn chỉ merge tài liệu, không có runtime, không có migration).
- Nếu Owner yêu cầu changes: sửa `docs/superpowers/specs/2026-08-15-...-canonical-semantics.md` theo phản hồi, tạo lại Gate 2 packet mới (supersedes bản này) theo đúng `OWNER_DECISION_RULES.md`.
- Nếu Owner Decline: PR #262 giữ Draft, không merge, không tiếp tục.

## 6. Ngoại lệ

- 6 mục "Known conflicts and open items" (SSOT §12) được cố ý để lại chưa giải quyết ở đây — chúng thuộc thẩm quyền Gate 2 của từng slice tương lai (Opportunity default-Architecture, Treasury↔ContractExpense boundary, INFERRED visibility policy, Contract multi-value Service Line, schema đặt tên, GAP-036).
- Legacy `service_category = inspection` (hiện hiển thị "Giám sát" trên UI) không được tự động suy ra là Inspection độc lập — giữ nguyên `NEEDS_REVIEW` cho đến khi có audit riêng.

## 7. Hành vi người dùng nhìn thấy

Không có — tài liệu governance, không có màn hình, không có thông báo, không có API thay đổi.

## 8. Kịch bản chấp nhận

- Given một slice tương lai (vd. Portfolio Membership Migration) đang chuẩn bị Gate 2, when tác giả cần định nghĩa "unique active projects" vs "projects by service line", then tài liệu này (§9.2) phải là nguồn duy nhất được trích dẫn, không tự định nghĩa lại.
- Given một slice tương lai đang tính "company cashflow", when tác giả cân nhắc viết lại phép tính, then tài liệu này (§6.3) phải chỉ rõ bắt buộc tái sử dụng `ReportPageController::cashflow()`, không viết mới.
- Given PR #257 hoặc PR #245 sau này được đóng, when lý do đóng được ghi lại, then phải trích dẫn tài liệu này làm bản thay thế đã merge (supersession), không đóng vì "đã dùng xong".

## 9. Loại trừ phạm vi

Kế thừa nguyên vẹn từ Gate 1 (`01-request.md`): không migration/model/controller/service/route/UI; không sửa `Opportunity.service_category` default; không GAP-036; không implementation của bất kỳ slice nào liệt kê ở SSOT §14; không đóng/merge PR #257/#245; không Today Workspace; không production/deployment; không gap vận hành khác.

## Decision Needed
Owner chọn một: Approve to proceed to implementation *(lưu ý: "implementation" ở đây chỉ nghĩa là chuẩn bị Gate 3 — merge tài liệu — vì work item này không có runtime implementation)* / Request changes to the design / Decline.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ implementation runtime nào (không có trong work item này); không được yêu cầu duyệt trước bất kỳ Gate 1/2/3 nào của các slice tương lai liệt kê ở SSOT §14 — mỗi slice đó là quyết định Owner riêng, sau này; không được yêu cầu quyết định đóng PR #257/#245 (đó là quyết định riêng, chỉ đặt ra sau khi bản SSOT này được merge); không được yêu cầu duyệt GAP-036 (báo cáo riêng).
