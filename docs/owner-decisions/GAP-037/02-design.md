---
work_id: GAP-037
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md
  plan: null
  branch: docs/GAP-037-project-treasury-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/263
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-16T00:53:25+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-16T00:53:25+07:00"
  updated_at: "2026-08-16T00:53:25+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Owner Packet (Architecture Decisions A–D)

**Status:** Gate 1 approved (2026-08-16, 2 rounds). Gate 2 is an **investigation packet** — it presents options, trade-offs, and a recommendation for each of the 4 bound decisions; it does **not** self-select an answer and does **not** propose a schema. No implementation, migration, model, controller, service, route, or UI exists or is authorized by this packet.

Full analysis: `docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md`. This packet summarizes it for Owner decision; it does not restate every option/trade-off.

---

## 1. Owner Summary
Gate 1 bound Gate 2 to answer 4 architecture questions before any Treasury schema could be proposed: who owns "cost," who owns "cash," how is double-posting prevented, and how does Treasury relate to the existing company cashflow report. This packet lays out real options with trade-offs for each — informed by the same runtime audit from Gate 1 (`ContractExpense`, `ContractPayment`, `MaterialReceiptLine`, the `Component`→`Project` cost rollup) — and gives a recommended set, but the actual choice is the Owner's to make, not inferred from this packet's existence.

## 2. The 4 decisions, condensed (full detail + trade-offs in the spec)

**A. Cost authority** — 3 options considered: (A1) Treasury fully replaces `ContractExpense` as cost system of record (high migration risk); (A2) Treasury is a pure read-aggregator, never posts cost itself (contradicts PR #245's own approval-workflow intent); (A3, **recommended**) Treasury posts only the *cash-out* side of a cost event, referencing back to the existing `ContractExpense`/`MaterialReceiptLine` record — cost data never moves. A separate sub-decision (A.4) covers `Component.actual_cost`/`Project.actual_cost` rollup: recommended is to explicitly exclude it from Treasury's first release entirely (not resolve its semantics now, not touch it).

**B. Cash authority** — 2 options considered: (B1) Treasury absorbs `ContractPayment` too (high migration risk, contradicts SSOT §7.2's "not a duplicate of the Contract payment lifecycle"); (B2, **recommended**) Treasury becomes canonical only for cash movements `ContractPayment` doesn't already cover — supplier/labor/material payments, owner contribution, internal transfers, advances/settlements. `ContractPayment` keeps owning client-payment cash-in, untouched.

**C. Economic-event / no-double-posting** — under the A3/B2 recommendation, every existing source gets an explicit role (input/reference, never a second copy): `ContractPayment` referenced-only; `ContractExpense`/`MaterialReceiptLine` referenced by a mandatory link field on every Treasury cash-out entry; `Component`/`Project` rollup excluded entirely, with a binding rule (regardless of which option is chosen) that Treasury code must never call `Project::recalculateActualCost()` or `ProjectCalculationListener::recalculateProjectCost()`, and must never write `Component.actual_cost`/`Project.actual_cost`/`budget_actual`.

**D. Company cashflow integration** — recommendation: GAP-037 does not touch `ReportPageController::cashflow()` at all, in either direction. Deciding what to do about that controller's existing `chi`-side accrual/cash mislabeling stays explicitly assigned to a future Finance Control slice's own Gate 2 (per SSOT §6.3), not to Treasury.

## 3. Migration / no-double-count implications of the recommended set
Every recommended option (A3, A4-a, B2) is purely additive: new Treasury tables plus a link-field pattern referencing existing records. Zero changes to `ContractExpense`, `ContractPayment`, `MaterialReceiptLine`, `Component`, or `Project` schema or data. Zero existing UI/report changes. This is a direct consequence of choosing the "reference, don't replace" options over the "replace" options (A1/B1) — those alternatives exist in the spec and remain available if the Owner prefers a different risk/completeness trade-off.

## 4. Trạng thái và bước tiếp theo
- Nếu Owner Approve: Gate 2 chuyển sang chuẩn bị một revision kế tiếp đề xuất schema cụ thể dựa trên A3/A4-a/B2/D — vẫn là Gate 2 (chưa Gate 3, chưa implementation), theo đúng quy tắc "finding sau Gate 2 approval cần Gate 2 revision mới" nếu có phát sinh.
- Nếu Owner Request changes: nêu rõ option nào cần thay đổi (A/B/C/D) hoặc trade-off nào chưa thoả đáng; sẽ sửa đúng phần đó, giữ nguyên phần còn lại, đưa lại awaiting_owner.
- Nếu Owner Decline: dừng GAP-037 ở đây, không tiếp tục.

## 5. Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1: không migration/schema/model/controller/service/route/UI; không implementation plan dựa trên option chưa duyệt; không GAP-036; không Today Workspace; không sửa canonical SSOT (kể cả stale metadata); không sửa/merge/đóng PR #245 hoặc #257; không production/deployment. Việc chọn một option ở đây không tự nó là schema — schema cụ thể (bảng, cột, migration) vẫn cần một Gate 2 revision hoặc Gate 3 riêng, tuỳ theo mức độ Owner muốn duyệt từng bước.

## Decision Needed
Owner chọn một: Approve recommended set (A3/A4-a/B2/D) to proceed toward a concrete schema proposal / Request changes to one or more decisions / Decline.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ tên bảng/cột/migration cụ thể nào — đó là nội dung của Gate 2 revision kế tiếp (schema proposal), chưa có ở đây. Owner cũng không được yêu cầu duyệt cách Finance Control tương lai sẽ tích hợp Treasury — đó là quyết định của chính slice Finance Control, chỉ được ghi nhận ranh giới ở đây (Decision D). Owner cũng không được yêu cầu quyết định số phận của `Component.actual_cost`/`Project.actual_cost` rollup — A4-a chỉ đề xuất hoãn lại, không giải quyết.
