---
work_id: OWN-2026-009
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md
  plan: null
  branch: docs/OWN-2026-009-one-page-management-ssot-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/262
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-15T14:55:45+07:00"
  owner_response_reference: "Owner Gate 2 Round 3 decision — APPROVE, recorded in-session on 2026-08-15 against reviewed PR #262 head d45adecd4cd471091d7bf732adebee36c8fedde5: 'OWN-2026-009 — Gate 2 Round 3 Owner Decision: APPROVE. Tôi, Owner, APPROVE Gate 2 của OWN-2026-009 tại PR #262, reviewed head d45adecd4cd471091d7bf732adebee36c8fedde5. Tôi xác nhận các yêu cầu thay đổi của Gate 2 Round 1 và Round 2 đã được xử lý đạt yêu cầu. Canonical design được phê duyệt là docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md tại exact reviewed head nêu trên. Approval này xác nhận các shared semantics và boundaries trong SSOT, bao gồm: one CRM pipeline, one canonical Project; multi-value Service Line DESIGN / CONSTRUCTION / INSPECTION; Service Scope/Discipline tách khỏi Service Line; classification provenance và maturity rules; shared Project Health semantics; Project OPPM consumer boundary; Contract / Finance / Project Treasury ownership boundaries; Cost != Cash != Revenue != Profit và missing data != zero/green/certain; RBAC qualifiers giữ nguyên authorization model hiện hữu; ReportPageController::cashflow() là reuse target nhưng chi từ ContractExpense chưa được chứng minh là cash-basis và phải được audit trước Finance Control; roadmap 12 slice, trong đó Canonical Service-Line Foundation là một slice duy nhất, với Phase A Semantics Audit và Phase B Foundation Build. Authorization từ quyết định này: record Gate 2 approval với provenance nguyên văn quyết định Owner này và được phép chuẩn bị Gate 3 cho OWN-2026-009. Vì OWN-2026-009 là docs-only, Gate 3 chỉ được xem xét việc merge canonical documentation của PR #262. Approval Gate 2 này không tự nó cho phép merge PR #262 và không cho phép bất kỳ runtime implementation nào. Mọi implementation slice trong §14 vẫn phải có Work ID riêng và Gate 1 → Gate 2 → Gate 3 riêng. Không được dùng approval này làm implementation authorization. Giữ nguyên các exclusion: không GAP-036; không sửa/merge/đóng #257 hoặc #245; không Today Workspace; không runtime migration/model/controller/service/route/UI; không production/deployment. Khi record Gate 2 approval / chuẩn bị Gate 3, cập nhật PR #262 body để phản ánh chính xác Gate 2 Round 3 = APPROVED / Gate 3 preparation, thay cho wording Round 2 hiện còn stale. Không được suy luận Gate 3 approval. Không được merge cho đến khi Owner phê duyệt Gate 3.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-15T00:57:51+07:00"
  updated_at: "2026-08-15T14:55:45+07:00"
generated_by: agent
---

# OWN-2026-009 — ZENA One-Page Management Canonical SSOT: Gate 2 Owner Packet

**Status:** Gate 1 approved (2026-08-15, binding scope recorded in `01-request.md`). Gate 2: Round 1 changes requested (4 items); Round 2 changes requested (1 remaining item); **Round 3 — APPROVED by Owner (2026-08-15)** against reviewed head `d45adecd4cd471091d7bf732adebee36c8fedde5`. Gate 3 preparation is authorized; Gate 2 approval does not itself authorize merging PR #262 or any runtime implementation — see Gate 3 packet (`03-release.md`) for the merge-only decision now being prepared. No implementation, migration, schema change, route, controller, service, test, or merge is authorized by this work item at any point; it only canonicalizes shared semantics for future slices to reference.

Full normative content: `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` (15 sections). This packet summarizes it for Owner decision; it does not restate every rule.

## Revision log

- **Round 1 (PR head `4836e7d9b886ad7b4537c4c1e71650984652794e`):** Owner REQUEST CHANGES — verbatim decision recorded at commit `1f64703c3a482a0f76ff6879051a69f685820daa`.
- **Round 2 (PR head `3c3b2d5f3f20993410e046b9f65813bbc865b1ad`):**
  1. SSOT §6.3/§8/§12: corrected cashflow claim — `ContractExpense` has no paid/status field (`app/Models/ContractExpense.php:33-40`, migration `2026_07_13_110100_create_contract_expenses_table.php:14-21`); `ReportPageController::cashflow()`'s `chi` side sums every row unconditionally by `expense_date` (`ReportPageController.php:87-96`), i.e. accrual-basis, not cash-basis. Reuse/no-duplication principle for the `thu` (cash-in, genuinely cash-basis via `ContractPayment.status`) side is preserved; the `chi` side now carries an explicit "must be audited before Finance Control treats it as canonical cash-out" flag instead of a false "already correct" claim. — **Owner confirmed adequate in Round 2.**
  2. SSOT §11: restored full qualifiers from the source design (PR #257 control-tower spec §12, verbatim) — Owner/Admin remain subject to RBAC; Staff have Today/My Work plus whatever additional project/resource data existing RBAC/project-visibility rules already grant. This document does not narrow or replace the existing authorization model. — **Owner confirmed adequate in Round 2.**
  3. PR #262 body: first non-empty line is now the authoritative `Work ID: OWN-2026-009` declaration (required by `scripts/ci/extract-work-id.sh`, confirmed root cause of the prior Owner Governance Lint CI failure); stale "No Gate 2 has been created" bullet removed. — **Owner confirmed adequate in Round 2.**
  4. SSOT §14: split the former single item 1 into "Service-Line Taxonomy & Semantics Audit" and "Canonical Service-Line Foundation" as two distinct slices. — **Owner rejected in Round 2**: Round 1 asked for one unified name, not a second slice; roadmap grew from 12 to 13 items. Verbatim decision recorded at commit `e0bcb2d5be896186e65f0375a6098c8f9d2082f5`.
- **Round 3 (this revision):** reverted §14 item 1 back to a single slice — **Canonical Service-Line Foundation** — with the audit and build work described as internal Phase A / Phase B of that one slice, not separate slices (roadmap back to 12 items); §12 item 5's cross-reference updated to point at "Phase B" of that slice instead of a second item. Fixed this Revision log's Round 1 line, which incorrectly pointed at `decision_provenance.owner_response_reference` (a field that gets reset to `null` each time gate_status returns to `awaiting_owner`) instead of the immutable git commit where that round's decision was actually recorded.

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
**Round 1 (đã xử lý):** Owner Request changes to the design, tại PR #262 head `4836e7d9b886ad7b4537c4c1e71650984652794e` (2026-08-15) — 4 nhóm thay đổi bắt buộc, chi tiết nguyên văn còn lưu trong lịch sử git tại commit `1f64703c3a482a0f76ff6879051a69f685820daa`. Xem `## Revision log` phía trên cho tình trạng xử lý từng mục.

**Round 2 (đã xử lý):** Owner Request changes to the design, tại PR #262 head `3c3b2d5f3f20993410e046b9f65813bbc865b1ad` (2026-08-15) — xác nhận 3/4 mục Round 1 đạt yêu cầu (cashflow/Cost-vs-Cash, RBAC, PR metadata/CI); 01 mục blocking còn lại: roadmap naming bị mở rộng thành 2 slice thay vì thống nhất tên 1 slice. Chi tiết nguyên văn lưu trong lịch sử git tại commit `e0bcb2d5be896186e65f0375a6098c8f9d2082f5`. Đã hoàn nguyên về 1 slice duy nhất với sub-step audit→build bên trong (xem `## Revision log`).

**Round 3 (đã quyết định): Owner APPROVE**, tại PR #262 head `d45adecd4cd471091d7bf732adebee36c8fedde5` (2026-08-15). Chi tiết nguyên văn tại `decision_provenance.owner_response_reference`. Approval này KHÔNG tự nó cho phép merge PR #262 — chỉ cho phép chuẩn bị Gate 3 (`03-release.md`), quyết định merge tài liệu riêng, do Owner phê duyệt riêng.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ implementation runtime nào (không có trong work item này); không được yêu cầu duyệt trước bất kỳ Gate 1/2/3 nào của các slice tương lai liệt kê ở SSOT §14 — mỗi slice đó là quyết định Owner riêng, sau này; không được yêu cầu quyết định đóng PR #257/#245 (đó là quyết định riêng, chỉ đặt ra sau khi bản SSOT này được merge); không được yêu cầu duyệt GAP-036 (báo cáo riêng).
