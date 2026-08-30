---
work_id: GAP-048
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-30-gap-048-crm-classification-ux-gates-design.md
  plan: null
  branch: docs/GAP-048-gate2-crm-classification-design
  pr: "https://github.com/kha997/zenamanagephp/pull/294"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-30T01:14:59Z"
  owner_response_reference: "GAP-048 Gate 2 Round 1 (relayed via coordinator session, reviewed exact PR head deaa7a81ff2a43545c21bf73e703ad661bcf9774, canonical main at review time e71b5508d29f12abb461e34c61ad2fe42b23db17): 'DECISION: CHANGES REQUESTED. This is a targeted Gate-2 design correction, not a rejection. Do NOT restart GAP-048. Continue the SAME GAP-048 Gate-2 session on Draft PR #294. No implementation is authorized.' Owner independently verified PR #294 OPEN/Draft/unmerged/mergeable, diff exactly the two expected Gate-2 docs, no main drift, Owner Governance Lint + Routes Guardrails green on the reviewed head, and confirmed the overall design direction is accepted subject to corrections. Owner explicitly KEPT (not reopened) 15 prior decisions: canonical 3-value Service Line set; UNKNOWN/NEEDS_REVIEW as subject-level non-membership states; only CONFIRMED satisfies gates; the nullable service_category migration strategy (remove DB default + both application fallbacks, no historical reclassification); pipeline gate placement in OpportunityStageTransitionService::transition() for scope_defined/proposal_draft/proposal_sent/negotiation/contracting/won; always-allowed exits lost/no_bid/nurture; Quote DRAFT creation/revision remaining ungated; the native formal-Quote gate at DRAFT->SENT; the independent createContract() gate covering both native-accepted and external-zena-boq-accepted-snapshot; Quote rejection always allowed; defense-in-depth WON conversion checks; no Opportunity->Project propagation; no zena-boq-core change; reuse of crm.manage with no new permission model; no historical Project backfill. Seven corrections directed: (1) the in-flight/grace policy must be Owner-decided now, not deferred to Gate 3 — binding rule: existing Opportunities are not retroactively invalid for their CURRENT stage, but their NEXT gated action (negotiation->contracting, contracting->won, existing-won->convert()/createContract(), sendQuote()) requires >=1 CONFIRMED line; lost/no_bid/nurture/reject remain always allowed; the existing GAP-046 backfill may seed INFERRED as a user aid only, INFERRED never constitutes grace and never satisfies a gate; no grandfather bypass, no time-based grace period, no automatic CONFIRMED promotion, no gate bypass based on record age or pre-existing stage. (2) Define one coherent legacy->canonical synchronization contract for ALL THREE active service_category writers (OpportunityController::store(), LeadController::convert(), OpportunityController::update()), not Lead conversion alone — one shared mapping source consumed by the backfill command and every runtime call site; on create/conversion, unambiguous legacy values map to DESIGN/INFERRED or CONSTRUCTION/INFERRED, ambiguous/null produces no row; on legacy-scalar UPDATE, mapper-owned INFERRED membership may be reconciled to the new scalar, but CONFIRMED rows are NEVER overwritten/demoted/deleted by the legacy mapper; the CLI backfill remains idempotent and INFERRED-only. (3) Classification mutation must be corrected to an ATOMIC desired-set reconciliation operation with an explicit resulting-state invariant, correcting an error in the prior design: EnforcesServiceLineIntegrity hooks Eloquent saving (create/update only), a DELETE does not pass through saving, so a naked child-row delete endpoint cannot rely on that trait for correctness; the corrected design is one DB transaction that reconciles the full desired canonical set and writes the audit EventRecord(s) together; binding invariant: if pipeline_stage is in scope_defined/proposal_draft/proposal_sent/negotiation/contracting/won (or the Opportunity has a native Quote SENT/ACCEPTED, an external accepted snapshot the application relies on, or is WON), the resulting set after the transaction must retain >=1 CONFIRMED line; pre-scope Opportunities may legitimately return to zero; atomic replacement of one confirmed line with another must succeed without a transient zero-confirmed state; delete/removal authorization and tenant-scoping must be explicit in the operation itself, not delegated to the saving-hook trait. (4) Make the UI write contract unambiguous: the multi-select is an unpersisted form draft, selecting values creates nothing, only the explicit Confirm-classification submission writes the desired set (selected lines become/remain CONFIRMED, unselected mapper-owned INFERRED rows are reconciled/removed per the atomic rules), no UI action creates a manual INFERRED row, and the prior design's speculative reference to a future manual-add-without-confirm path must be removed as out of this Work ID. (5) Fully define both legacy-consumer behaviors for genuinely-unclassified/multi-line/INFERRED-only/CONFIRMED cases: BusinessKpiService keeps its narrow, unchanged compatibility bridge (NULL becomes an explicit Unclassified bucket, not multi-Service-Line-aware in GAP-048, stated as a deliberate temporary decision) — but DesignItemPageController/AiAssistService's prior wording (\"CONFIRMED DESIGN-family classification\") is non-canonical and wrongly collapses a set to one line; corrected required behavior: read ALL CONFIRMED lines, stable canonical order DESIGN/CONSTRUCTION/INSPECTION, pass the complete set as AI context when >=1 CONFIRMED line exists, fall back to the nullable legacy scalar only when zero CONFIRMED lines exist, CONFIRMED set always wins over a conflicting legacy scalar when both exist, INFERRED-only does NOT outrank the legacy fallback. (6) Clarify external Quote semantics: keep the decision not to modify zena-boq-core, but state explicitly that link/sync is evidence ingestion of an external fact this application does not control, so GAP-048 does not reject or hide synchronization solely for incomplete local classification — only the first consequential LOCAL action relying on an accepted external Quote, createContract(), must fail closed without >=1 CONFIRMED line; the design must not imply createContract() somehow prevents the external system from issuing/accepting a Quote. (7) Remove the undecided \"gates initially inert\" rollout mode entirely — it implies an undesigned activation/feature-flag mechanism and a hidden bypass state; when GAP-048 is actually deployed/released its gates are active with no separate enable step; the pre-existing backfill may be run as a rollout aid but is not required to fabricate CONFIRMED and does not bypass gates; if production backfill execution cannot be proven at Gate 3 that must be reported truthfully, never claimed without proof. Additionally required: (10) state one canonical shared CONFIRMED-predicate semantic (\"Opportunity has at least one CONFIRMED canonical Service-Line membership\") backing the pipeline/sendQuote()/convert()/createContract()/reclassification-invariant checks via one shared domain predicate/helper, not independently-diverging queries — exact class/method name remains an implementation detail. (11) Expand the future test-strategy matrix to 15 additional discriminating cases (A-O) covering all three legacy writers, the atomic mutation invariant (including active-stage last-CONFIRMED-removal rejection and atomic replacement success), the complete-set AI-context behavior, the external-sync-vs-createContract distinction, the no-grandfather policy applied to an already-WON legacy Opportunity, and the unconditional lost/no_bid/nurture/reject exemptions. Correction scope strictly bounded to docs/superpowers/specs/2026-08-30-gap-048-crm-classification-ux-gates-design.md and docs/owner-decisions/GAP-048/02-design.md; no app/**, routes/**, resources/**, database/**, tests/**, config/**, .github/** change authorized or made; no implementation plan, no code, no migrations, no TDD; PR #294 remains Draft throughout; no Ready-flip or merge authorized by this decision.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-29T18:00:21Z"
  updated_at: "2026-08-30T01:14:59Z"
generated_by: agent
---

## Owner Decision History — Round 1 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate 2 Round 1 decision: CHANGES REQUESTED** — a targeted design
correction, not a rejection; overall design direction accepted (reviewed
exact PR head `deaa7a81ff2a43545c21bf73e703ad661bcf9774`, canonical main
`e71b5508d29f12abb461e34c61ad2fe42b23db17`). Full verbatim directive
preserved in this file's frontmatter `decision_provenance.owner_response_reference`
above. 15 prior design decisions were explicitly kept/not reopened (3-value
Service Line set; UNKNOWN/NEEDS_REVIEW as non-membership states; only
CONFIRMED satisfies gates; the nullable-migration strategy; pipeline gate
placement and stage list; always-allowed lost/no_bid/nurture exits; Quote
DRAFT/revision ungated; the native formal-Quote gate at DRAFT→SENT; the
independent `createContract()` gate covering native+external; Quote
rejection always allowed; defense-in-depth WON checks; no propagation; no
zena-boq-core change; `crm.manage` reuse; no historical Project backfill).
Seven corrections directed, all applied in this re-presentation: (1)
in-flight/grace policy resolved now — no grandfather, no time-based grace,
no automatic promotion (see design §17); (2) a complete legacy→canonical
synchronization contract for all three active writers, not Lead conversion
alone (design §4, new); (3) classification mutation corrected to an atomic
desired-set reconciliation with an explicit lifecycle invariant and
explicit delete/tenant safety, correcting an over-claim about
`EnforcesServiceLineIntegrity` covering deletes (design §5); (4) the
multi-select UI write contract made unambiguous, speculative future
manual-add path removed (design §3); (5) both legacy consumers' full
behavior matrix defined, correcting the `DesignItemPageController` wording
to the full-CONFIRMED-set rule (design §14); (6) external Quote
ingestion-vs-local-use semantics made explicit (design §13); (7) the
"gates initially inert" rollout mode removed entirely (design §20). A
shared `CONFIRMED`-predicate requirement and an expanded 15-case test
matrix were also added. This Round 1 record is preserved permanently and
must not be removed by any future revision.

---

## Owner Summary
Sau khi Owner duyệt Gate 1 (vấn đề/bằng chứng), đây là thiết kế Gate 2: cách CRM sẽ phân loại Opportunity một cách trung thực bằng Service Line chuẩn (đa giá trị), cách người dùng xác nhận CONFIRMED một cách tường minh, và các cổng kiểm soát ở pipeline/Quote/WON dựa trên phân loại đã xác nhận — chưa viết code nào.

## Trước / Sau
**Trước:** 1. Opportunity chỉ có 1 trường `service_category` (scalar cũ), bị gán "architecture" âm thầm ở 2 vị trí code khi bỏ trống. 2. Nền tảng Service Line chuẩn (GAP-046) tồn tại nhưng không có UI, không có cách xác nhận CONFIRMED, không có cổng kiểm soát nào đọc nó. 3. Pipeline, Quote (cả luồng nội bộ, cổng khách hàng, và đồng bộ ngoài zena-boq-core), và chuyển đổi WON→Project đều tiến hành mà không kiểm tra phân loại.

**Sau (nếu Gate 2 được duyệt, triển khai ở phiên riêng theo đúng ranh giới):** 1. Trang chi tiết Opportunity có bảng chọn đa giá trị Service Line chuẩn (DESIGN/CONSTRUCTION/INSPECTION) + hành động "Xác nhận phân loại" tường minh, tách biệt khỏi việc chỉ tick chọn. 2. Khi chuyển đổi Lead, hệ thống tự động suy luận Service Line INFERRED từ `service_category` cũ (dùng lại đúng bảng ánh xạ của GAP-046, không viết lại) — nhưng INFERRED một mình không đủ để vượt qua bất kỳ cổng nào. 3. Cột `service_category` trở thành nullable, bỏ default 'architecture' ở DB, gỡ 2 chỗ code tự gán 'architecture'. 4. Cổng kiểm soát: pipeline (vào `scope_defined` và các bước bán hàng tiếp theo, trừ lost/no_bid/nurture), Quote (tại `sendQuote()` — thời điểm "báo giá chính thức" theo đúng SSOT — VÀ tại `createContract()` để chặn cả đường Quote ngoài zena-boq-core), và WON→Project (kiểm tra lại độc lập, không chỉ dựa vào cổng pipeline). 5. `BusinessKpiService`/`DesignItemPageController` tiếp tục đọc `service_category` (không viết lại toàn bộ), nhưng KPI report thêm nhóm "Chưa phân loại" tường minh cho NULL, và gợi ý AI ưu tiên Service Line CONFIRMED khi có, chỉ dùng scalar cũ khi chưa có.

## Vai trò bị ảnh hưởng
Nhân viên sales/CRM (`crm.manage`): thấy bảng chọn Service Line mới, phải xác nhận tường minh trước khi Opportunity có thể tiến vào các bước bán hàng active/gửi báo giá chính thức/chuyển thành dự án. Không có vai trò mới, không có quyền mới — dùng lại đúng `crm.manage` hiện có.

## Được phép / Không được phép
Được phép (nếu duyệt): mở phiên triển khai riêng, đúng ranh giới tài liệu thiết kế §3-§20 (UI phân loại + xác nhận nguyên tử, hợp đồng đồng bộ legacy→canonical cho cả 3 writer, migration nullable cho `service_category`, cổng pipeline/Quote/WON dùng chung một predicate CONFIRMED, cầu nối tương thích hẹp cho 2 consumer cũ, chính sách không-ân-hạn, rollout không có chế độ cổng-tạm-tắt). Không được phép: Opportunity→Project Service-Line propagation; Project classification UX/backfill lịch sử; Quote Scope Snapshot persistence; Contract multi-Service-Line; Portfolio; Project Health; Commercial/Finance/Resource Control; OPPM; Control Tower; Treasury; retirement taxonomy cũ; sửa đổi zena-boq-core; GAP-041/042/045.

## Trạng thái và bước tiếp theo
Gate 1 (approved, đã merge) → **Gate 2 (tài liệu này, awaiting_owner)** → nếu duyệt: một phiên triển khai mới (implementation session, bắt đầu ở phiên riêng sau khi Gate 2 merge, không phải phiên này) → implementation plan → triển khai theo TDD → verify kỹ thuật → Gate 3 `awaiting_owner` → Owner review Gate 3 → chỉ release/merge sau khi Gate 3 được duyệt. Gate 3 chưa bắt đầu; phiên hiện tại (viết + trình Gate 2) không triển khai code.

## Ngoại lệ
Lost/No-bid/Nurture và từ chối Quote KHÔNG BAO GIỜ bị chặn bởi cổng phân loại (một deal phải luôn có thể bị từ chối/hoãn/archive bất kể đã phân loại hay chưa). **Chính sách deal dở dang — ĐÃ QUYẾT ĐỊNH ở Round 1, không còn là câu hỏi mở:** Opportunity đang xử lý dở (đã ở các bước bán hàng active/đã WON trước khi cổng này tồn tại) không bị chặn hồi tố ở bước hiện tại — vẫn xem/hiển thị bình thường — nhưng bước KẾ TIẾP (negotiation→contracting, contracting→won, `convert()`/`createContract()` cho Opportunity đã WON sẵn, gửi Quote chính thức) bị chặn cho đến khi có ≥1 Service Line CONFIRMED. KHÔNG có ân hạn theo thời gian, KHÔNG có "cho qua" tự động vì đã ở giai đoạn cũ, KHÔNG tự động thăng INFERRED lên CONFIRMED. Lệnh backfill sẵn có của GAP-046 có thể chạy để gieo sẵn INFERRED như một hỗ trợ người dùng, nhưng INFERRED không bao giờ đủ để vượt cổng.

## Hành vi người dùng nhìn thấy
Trang chi tiết Opportunity có thêm bảng Service Line (badge trạng thái CONFIRMED/INFERRED) + nút "Xác nhận phân loại" (chọn tick không tự lưu gì — chỉ khi bấm "Xác nhận" mới ghi). Khi cố gửi báo giá chính thức, chuyển bước bán hàng, hoặc tạo hợp đồng mà chưa có Service Line đã xác nhận, hệ thống báo lỗi rõ ràng thay vì tiến hành âm thầm — kể cả với Opportunity/Quote đã tồn tại từ trước khi tính năng này ra mắt.

## Kịch bản chấp nhận
Xem mục 18 ("Test strategy", A-O) của tài liệu thiết kế — sẽ trở thành checklist Gate 3, bao gồm: cả 3 writer path (`store()`, Lead convert, `update()`) ánh xạ nhất quán từ một nguồn map dùng chung; cập nhật `service_category` không bao giờ ghi đè/xoá dòng CONFIRMED; xoá dòng CONFIRMED cuối cùng của Opportunity đang ở giai đoạn active bị từ chối; thay thế nguyên tử một Service Line CONFIRMED bằng Service Line khác thành công không qua trạng thái trung gian rỗng; Opportunity đã WON từ trước vẫn bị chặn `convert()`/`createContract()` cho đến khi CONFIRMED (chứng minh không có ân hạn); gợi ý AI hạng mục thiết kế giữ nguyên toàn bộ tập CONFIRMED (không chỉ 1 dòng); đồng bộ Quote ngoài zena-boq vẫn diễn ra bình thường nhưng `createContract()` vẫn chặn; ghi xuyên-tenant bị từ chối (không chỉ dựa vào `EnforcesServiceLineIntegrity` vì trait đó không bảo vệ thao tác xoá).

## Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1 + làm rõ ở Gate 2 (cả 2 vòng): không có Opportunity→Project Service-Line propagation; không Project classification UX/backfill lịch sử; không Quote Scope Snapshot persistence; không Contract multi-Service-Line; không Portfolio/Project Health/Commercial-Finance-Resource Control/OPPM/Control Tower/Treasury; không retirement taxonomy cũ; không sửa đổi zena-boq-core (cổng được đặt hoàn toàn ở phía `createContract()` trong chính codebase này — đồng bộ Quote ngoài vẫn diễn ra tự do, chỉ hành động LOCAL dựa vào nó mới bị chặn); không GAP-041/042/045; không có chế độ "cổng tạm tắt" khi rollout (đã loại bỏ ở Round 1); không có ân hạn/grace theo thời gian hay theo giai đoạn có sẵn (đã quyết ở Round 1).

## Decision Needed
Owner chọn một trong: Approve để tiến sang triển khai (ở phiên riêng, đúng ranh giới đã sửa) / Yêu cầu sửa đổi thêm / Từ chối.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt tên route/controller/method/migration/event key cụ thể, hay câu chữ UI chính xác — đó là quyết định triển khai trong ranh giới đã duyệt. Owner ĐÃ quyết định (không còn là câu hỏi mở): (1) chính sách không-ân-hạn cho deal dở dang; (2) gỡ bỏ chế độ cổng-tạm-tắt khi rollout; (3) đặt cổng Quote tại `sendQuote()` + `createContract()` thay vì `storeQuote()`; (4) chiến lược migration nullable cho `service_category` (không phải sentinel value); (5) ranh giới ingestion-vs-local-use cho Quote ngoài zena-boq-core (không mở rộng phạm vi sang hệ thống ngoài); (6) quy tắc tập CONFIRMED đầy đủ (không phải 1 dòng tuỳ ý) cho gợi ý AI hạng mục thiết kế; (7) hợp đồng đồng bộ legacy→canonical áp dụng thống nhất cho cả 3 writer path; (8) quy tắc bất biến vòng đời cho thao tác xác nhận/xoá phân loại (đơn giao dịch nguyên tử, tự kiểm tra tenant/parent tường minh vì trait `EnforcesServiceLineIntegrity` không bảo vệ thao tác xoá).
