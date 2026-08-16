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
  recorded_at: "2026-08-16T09:28:13+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-16T00:53:25+07:00"
  updated_at: "2026-08-16T09:28:13+07:00"
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

**B. Cash authority** — 2 options considered: **Option B1** — Treasury absorbs `ContractPayment` too (high migration risk, contradicts SSOT §7.2's "not a duplicate of the Contract payment lifecycle") — **not recommended, not selected**; **Option B2 (recommended)** — Treasury becomes canonical only for cash movements `ContractPayment` doesn't already cover — supplier/labor/material payments, owner contribution, internal transfers, advances/settlements. `ContractPayment` keeps owning client-payment cash-in, untouched. **§B2-T (renamed from an earlier "§B.1" — unrelated to, and not to be confused with, rejected Option B1), added this round:** Treasury may record custody/route/wallet facts about a `ContractPayment`-sourced event (the A→C→Y multi-leg case) — corrected this round to conserve at the **allocation** level, not by summing route legs: `sum(economic allocation across all Treasury routes for a ContractPayment) <= ContractPayment.amount`. Serial leg amounts (A→C, then C→Y) are movement history of the *same* allocated amount moving hop-to-hop, never additive — summing them would wrongly multiply a single payment's value by its hop count. The commercial "paid" fact is asserted exactly once, in `ContractPayment`; Treasury's route/wallet facts are a location projection of it, never a second funding event.

**C. Economic-event / no-double-posting** — under the A3/B2 recommendation, every existing source gets an explicit role, never a second copy. **Corrected this round:** the earlier "unlinked cash-out for any genuinely new manual entry" language was a generic escape hatch that could let Treasury quietly become a cost authority. Now: an unlinked cash-out is valid **only** for movements that are not, by definition, cost/expense settlements — internal transfer, advance, owner financing. A genuine expense settlement with no existing cost record must create that cost record (`ContractExpense`/material-cost) in the **same atomic operation** as the cash-out, never as a bare unlinked posting. **§A.5, added this round:** the cost↔cash relationship must support one-cost/many-payments, one-payment/many-costs, partial allocation, and reversal-with-allocation-auditability — a single 1:1 foreign key cannot represent this and must not be assumed at schema time. `Component`/`Project` rollup stays excluded entirely, with the binding rule that Treasury code must never call `Project::recalculateActualCost()` or `ProjectCalculationListener::recalculateProjectCost()`, and must never write `Component.actual_cost`/`Project.actual_cost`/`budget_actual`.

**D. Company cashflow integration** — recommendation: GAP-037 does not touch `ReportPageController::cashflow()` at all, in either direction. Deciding what to do about that controller's existing `chi`-side accrual/cash mislabeling stays explicitly assigned to a future Finance Control slice's own Gate 2 (per SSOT §6.3), not to Treasury.

## 3. Migration / no-double-count implications of the recommended set
**Corrected in Round 1 — "none" was imprecise.** No migration of *existing* data or tables is required to adopt the recommended set: `ContractPayment`, `ContractExpense`, `MaterialReceiptLine`, `Component`, and `Project` are all either read-only from Treasury's perspective or (for Component/Project) untouched, and none of their current rows or schema change. This does **not** mean Treasury needs zero migrations overall — implementing it still requires additive schema migrations for Treasury's own new tables (ledger/wallet/allocation/route, per PR #245 revised by §A.5/§B2-T); those migrations are simply not designed or proposed in this Gate 2 revision, only in a later schema-proposal step.

## 4. Trạng thái và bước tiếp theo
- Nếu Owner Approve: Gate 2 chuyển sang chuẩn bị một revision kế tiếp đề xuất schema cụ thể dựa trên A3/A4-a/B2/D — vẫn là Gate 2 (chưa Gate 3, chưa implementation), theo đúng quy tắc "finding sau Gate 2 approval cần Gate 2 revision mới" nếu có phát sinh.
- Nếu Owner Request changes: nêu rõ option nào cần thay đổi (A/B/C/D) hoặc trade-off nào chưa thoả đáng; sẽ sửa đúng phần đó, giữ nguyên phần còn lại, đưa lại awaiting_owner.
- Nếu Owner Decline: dừng GAP-037 ở đây, không tiếp tục.

## 5. Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1: không migration/schema/model/controller/service/route/UI; không implementation plan dựa trên option chưa duyệt; không GAP-036; không Today Workspace; không sửa canonical SSOT (kể cả stale metadata); không sửa/merge/đóng PR #245 hoặc #257; không production/deployment. Việc chọn một option ở đây không tự nó là schema — schema cụ thể (bảng, cột, migration) vẫn cần một Gate 2 revision hoặc Gate 3 riêng, tuỳ theo mức độ Owner muốn duyệt từng bước.

## Revision log
- **Round 1 (PR head `1748303e262bb4f03a1486c4ea29e5b8eab5d0e6`):** Owner REQUEST CHANGES — verbatim decision recorded at commit `f71e9c7bb19e78d9767ca999181c1aa644b3c9f6`.
- **Round 2 (PR head `6f7dd74402ce29acd96ce6b117054e064c192e58`):**
  1. Spec §B.1 (new at the time): resolved ContractPayment↔Treasury funding traceability for the A→C→Y multi-leg case — binding relationship, not deferred.
  2. Spec §A.3 (corrected): removed the generic unlinked-expense escape hatch; unlinked cash-outs now valid only for non-cost movements; genuine expense settlements must create their cost record atomically with the cash-out.
  3. Spec §A.5 (new): settlement-cardinality domain requirement — one-cost/many-payments, one-payment/many-costs, partial allocation, reversal auditability; explicit ban on assuming a 1:1 link.
  4. PR #263 body updated to reflect Gate 1 approved / Gate 2 awaiting_owner; "Migration implications: none" corrected to distinguish "no existing-data migration" from "Treasury's own additive migrations still needed, just not proposed here."
  - Owner REQUEST CHANGES on this round — verbatim decision recorded at commit `ccd12e81cf0ee1372f645a12d40537b1bfecc36a`.
- **Round 3 (this revision):**
  1. Spec §B.1 renamed to **§B2-T** everywhere, with every remaining "B1" occurrence disambiguated as explicitly the rejected Option B1 — no more shared identifier between the two.
  2. Funding-route conservation invariant corrected: replaced `sum(route legs) <= ContractPayment.amount` (wrong — double-counts serial hops of the same payment) with an allocation-level invariant: `sum(economic allocation across all Treasury routes for a ContractPayment) <= ContractPayment.amount`, with route legs explicitly defined as non-additive movement history, and current custody state required to reconcile to the allocated amount net of reversals/refunds.

## Decision Needed
**Round 1 (đã xử lý):** Owner Request changes, tại PR #263 head `1748303e262bb4f03a1486c4ea29e5b8eab5d0e6` (2026-08-16). Đồng ý nguyên tắc A3/A4-a/B2/D nhưng yêu cầu 4 điểm bổ sung trước khi duyệt: (1) ContractPayment↔Treasury funding traceability; (2) cấm unlinked expense escape hatch; (3) settlement cardinality (partial/many-to-many/reversal); (4) documentation accuracy (PR body, migration wording). Chi tiết nguyên văn lưu tại commit `f71e9c7bb19e78d9767ca999181c1aa644b3c9f6`. Xem `## Revision log` phía trên cho tình trạng xử lý.

**Round 2 (đã xử lý):** Owner Request changes, tại PR #263 head `6f7dd74402ce29acd96ce6b117054e064c192e58` (2026-08-16) — xác nhận 4/5 điểm Round 1 đạt yêu cầu; 2 correction bắt buộc còn lại: (1) sửa funding-route conservation invariant (không cộng leg amount, dùng allocation-level conservation); (2) đổi tên §B.1 thành §B2-T để không xung đột với Option B1 (không được chọn). Chi tiết nguyên văn lưu tại commit `ccd12e81cf0ee1372f645a12d40537b1bfecc36a`. Xem `## Revision log` phía trên cho tình trạng xử lý.

**Round 3 (đang chờ):** Owner chọn một: Approve recommended set (A3 + A4-a + A.5 / B2 + funding-traceability extension B2-T / C / D — Option B1 KHÔNG được chọn) to proceed toward a concrete schema proposal / Request changes to one or more decisions / Decline.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ tên bảng/cột/migration cụ thể nào — đó là nội dung của Gate 2 revision kế tiếp (schema proposal), chưa có ở đây. Owner cũng không được yêu cầu duyệt cách Finance Control tương lai sẽ tích hợp Treasury — đó là quyết định của chính slice Finance Control, chỉ được ghi nhận ranh giới ở đây (Decision D). Owner cũng không được yêu cầu quyết định số phận của `Component.actual_cost`/`Project.actual_cost` rollup — A4-a chỉ đề xuất hoãn lại, không giải quyết.
