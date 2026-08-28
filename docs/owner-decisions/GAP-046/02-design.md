---
work_id: GAP-046
gate: 2
gate_status: changes_requested
owner_decision:
  value: changes_requested
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md
  plan: null
  branch: docs/GAP-046-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/288"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-28T00:00:00Z"
  owner_response_reference: "Owner Gate 2 Round 1 decision — CHANGES REQUESTED, reviewed exact PR head b4073f771db10f8f3aeac7cd0f56b6776a5ee532 (canonical main at time of review: f36cfaf7dc4100ed41cc7f7262ba81fdef7bd99a). Owner LOCKS two design choices now: (1) Persistence architecture — OPTION B APPROVED IN PRINCIPLE, explicit non-polymorphic opportunity_service_lines/project_service_lines join tables; do not reopen polymorphic/JSON/boolean-column alternatives. (2) Historical Project backfill — OPTION A SELECTED, NO one-time Project historical backfill; existing Projects with no canonical classification remain UNKNOWN-by-absence; do not infer Project membership from historical Opportunity data in GAP-046. Gate 2 as a whole is NOT approved yet; three corrections required before re-presentation: (A) UNKNOWN/NEEDS_REVIEW semantics — canonical service_line stays exactly DESIGN/CONSTRUCTION/INSPECTION, no fourth value; a membership row may exist only when a canonical line is actually known/candidate-specific; legacy architecture/interior/landscape/structure/mep -> DESIGN/INFERRED, construction -> CONSTRUCTION/INFERRED, inspection/consulting/combined_package -> create NO membership row (preserve legacy service_category as evidence; legacy 'inspection' UI value denotes ambiguous 'Giám sát' per SSOT 2026-08-15 canonical-semantics doc §2.5, not standalone INSPECTION), null/unrecognized -> create NO row (UNKNOWN-by-absence); no runtime CRM UX/remediation workflow authorized in this Work ID. (B) tenant-parent integrity — child.tenant_id must be derived from its parent, never independently trusted from request/caller input; two independent FKs (child->tenant, child->parent) do not by themselves prove tenant congruence; Opportunity backfill must copy tenant_id from the Opportunity; future relation-based writes must enforce parent tenant identity and fail closed on mismatch; add a cross-tenant rejection acceptance case; no broad projects.tenant_id schema remediation in GAP-046, state the portability limitation explicitly if a portable composite FK cannot be added without touching that legacy schema. (C) governance lifecycle wording — remove any wording implying Gate 3 is the implementation step; correct lifecycle is Gate 2 Owner approval -> NEW implementation session -> implementation plan -> TDD implementation -> technical verification -> Gate 3 packet awaiting_owner -> Owner Gate 3 review -> release/merge only after Gate 3 approval. Acceptance matrix must add explicit coverage for: canonical service_line accepts exactly DESIGN/CONSTRUCTION/INSPECTION; invalid canonical value rejected; legacy architecture-family creates only DESIGN/INFERRED; legacy construction creates only CONSTRUCTION/INFERRED; legacy inspection/consulting/combined_package creates no row; null/unrecognized creates no row and stays UNKNOWN-by-absence; backfill never creates CONFIRMED; backfill is idempotent; cross-tenant parent/child write is rejected; Project historical backfill count is exactly zero; runtime Opportunity->Project propagation remains absent. No implementation authorized by this decision. Do not merge, do not self-approve. Corrections to be executed exactly, then Gate 2 re-presented as awaiting_owner for Owner Round 2 review, with this Round 1 record preserved permanently in the packet body."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-25T05:54:54Z"
  updated_at: "2026-08-28T00:00:00Z"
generated_by: agent
---

## Owner Decision History (permanent record — never erased)

### Round 1 — CHANGES REQUESTED (recorded 2026-08-28)

Owner reviewed exact PR head `b4073f771db10f8f3aeac7cd0f56b6776a5ee532` (canonical main at review time: `f36cfaf7dc4100ed41cc7f7262ba81fdef7bd99a`). Decision: **CHANGES REQUESTED**. Full text of the decision is recorded verbatim in this file's frontmatter `decision_provenance.owner_response_reference` above. Summary:

- **Locked in principle:** Option B (explicit `opportunity_service_lines`/`project_service_lines` join tables) — not reopened.
- **Locked:** Historical Project backfill — Option A, NO one-time Project backfill in GAP-046; existing Projects with no canonical classification remain UNKNOWN-by-absence.
- **Three corrections required** before re-presentation: (A) UNKNOWN/NEEDS_REVIEW membership semantics corrected — no membership row without a known canonical line, legacy `inspection`/`consulting`/`combined_package`/null create no row; (B) tenant-parent integrity invariant corrected — `tenant_id` must be derived from the parent, never independently trusted, with a cross-tenant rejection acceptance case added; (C) governance lifecycle wording corrected — Gate 3 is the release gate, not the implementation step.
- Gate 2 as a whole was **not approved** at this round. This document and its companion spec were revised per the corrections below and the packet is now re-presented as `awaiting_owner` for Owner Round 2 review (see current frontmatter `gate_status`). This Round 1 record is preserved permanently and must not be removed by any future revision.

---

## Provenance note (mechanical split, 2026-08-26)

This packet's design substance is unchanged from its original authoring. Per Owner instruction (2026-08-25), GAP-046's Gate 1 and Gate 2 were split across two PRs to avoid a confirmed lint-tooling gap (the OWN-2026-005 gate-ordering exemption's path-prefix allowlist excludes `docs/audits/**`, which made a same-PR Gate1+Gate2 submission fail `--enforce-gate-ordering` even though the actual diff was 100% documentation). Gate 1 (`01-request.md`, this same content, unchanged) was landed to `main` as PR [#287](https://github.com/kha997/zenamanagephp/pull/287), squash-merged at `f913f040063fc628ad8f425b5f01ff5da960d742`. This Gate 2 packet and its companion spec document are recovered byte-for-byte from the original combined-PR head `7fe8b8d73b8a63b70a1e142d08cf98a97cda2878` (only the `branch`/`pr`/`recorded_at`/`updated_at` frontmatter fields here were mechanically updated to reflect the new branch; no design content, scope, recommendation, or exclusion was altered). `gate_status` remains `awaiting_owner`, `owner_decision.value` remains `none` — no self-approval occurred during this restructuring. The lint-tooling gap itself (and a second, related finding — a case-sensitive filename-regex fallback that lets frontmatter-less spec/plan docs silently evade the same check in bulk-scan CI) are recorded as accepted findings, out of GAP-046's remediation scope per Owner instruction; `scripts/ssot/owner_governance_lint.php` and its CI workflow were not modified.

## Owner Summary
Phase A (Gate 1) đã được Owner duyệt. Tài liệu này là Phase B Gate 2: so sánh 4 phương án lưu trữ dữ liệu cho nền tảng Service Line đa giá trị, đề xuất một phương án cụ thể, đúng ranh giới phạm vi Owner đã chỉ định — chỉ thiết kế, chưa triển khai code nào.

## Trước / Sau
**Trước:** 1. `Opportunity.service_category` là cột đơn giá trị duy nhất, không có bảng/model nào đại diện cho tập giá trị Service Line đa giá trị. 2. Không có trường nào ghi nhận "dữ liệu này được xác nhận thật hay chỉ là suy luận/mặc định."3. `Project` không có trường phân loại nào.

**Sau (nếu Gate 2 được duyệt và Gate 3 triển khai theo đúng ranh giới):** 1. Hai bảng mới `opportunity_service_lines`/`project_service_lines` (Option B, §3 tài liệu thiết kế) cho phép một Opportunity/Project mang nhiều Service Line cùng lúc. 2. Mỗi dòng phân loại có trường `provenance` (CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN). 3. Dữ liệu cũ trên `Opportunity.service_category` được backfill một lần, bảo thủ (không bao giờ gán CONFIRMED). 4. `service_category` và toàn bộ hành vi hiện tại (báo cáo CRM, gợi ý AI) giữ nguyên không đổi — nền tảng mới chỉ cộng thêm, chưa có nơi nào đọc/ghi nó ở runtime.

## Vai trò bị ảnh hưởng
Không có vai trò người dùng cuối nào thấy thay đổi ở bước này — đây là nền tảng dữ liệu thuần backend, chưa có UI/gate/luồng nghiệp vụ nào nối vào. Đội kỹ thuật của các slice tương lai (CRM Classification UX, Opportunity→Project Propagation, Portfolio Membership...) sẽ là bên tiêu thụ nền tảng này.

## Được phép / Không được phép
Được phép (nếu duyệt): tạo 2 bảng mới + 2 model mỏng + quan hệ đọc `serviceLines()` + lệnh backfill 1 chiều phía Opportunity. Không được phép: sửa giá trị mặc định "architecture" hiện tại; nối runtime Opportunity→Project; sửa CRM form/gate; đụng vào Quote/Contract/Portfolio/OPPM/Control Tower/Finance/Treasury.

## Trạng thái và bước tiếp theo
Gate 1 (approved, merged to main) → **Gate 2 (tài liệu này, awaiting_owner)** → nếu Owner duyệt: Gate 3 sẽ là bước triển khai thật + bằng chứng CI thật trên MySQL, đúng đúng ranh giới §11 của tài liệu thiết kế — Gate 3 chưa bắt đầu.

## Ngoại lệ
Câu hỏi ranh giới còn mở, cần Owner xác nhận trước khi triển khai (§7 tài liệu thiết kế): có backfill một lần cho các Project đã được chuyển đổi từ Opportunity TRƯỚC KHI nền tảng này tồn tại hay không? Mặc định bảo thủ của thiết kế này là KHÔNG (để trống, coi là UNKNOWN-do-thiếu-dữ-liệu) vì về mặt cơ chế nó giống hệt runtime propagation đã bị loại trừ, chỉ khác là chạy một lần thay vì liên tục. Câu hỏi này vẫn CHƯA được quyết định qua đợt tái cấu trúc PR này — vẫn chờ Owner.

## Hành vi người dùng nhìn thấy
Không có gì thay đổi trên màn hình ở giai đoạn này — đây là nền tảng dữ liệu, chưa có UI nào đọc/ghi nó.

## Kịch bản chấp nhận
Xem mục 12 ("Acceptance tests") của `docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md` — 8 tiêu chí cụ thể (cách ly tenant, ràng buộc duy nhất, validate provenance, round-trip migration LIVE trên MySQL thật, backfill idempotent, backfill không bao giờ tạo CONFIRMED, 2 test suite hiện tại (`BusinessKpiServiceTest`, gợi ý AI) không bị ảnh hưởng, quan hệ Eloquent trả đúng dữ liệu) — sẽ trở thành checklist Gate 3.

## Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1 + làm rõ thêm theo chỉ đạo Owner: không sửa write-path mặc định "architecture"; không nối runtime Opportunity→Project; không CRM UX/gate; không Quote Scope Snapshot; không Contract Service-Line; không Portfolio Membership; không Project OPPM; không Control Tower; không Finance/Treasury; không backfill phía Project cho các dự án đã chuyển đổi trước đó (mặc định loại trừ, chờ Owner xác nhận — §7 tài liệu thiết kế).

## Decision Needed
Owner chọn một trong: Duyệt Option B (§3 tài liệu thiết kế) + mặc định bảo thủ ở §7 (loại trừ backfill phía Project) để cho phép triển khai đúng ranh giới §11 / Yêu cầu thay đổi thiết kế (ví dụ chọn phương án khác, hoặc lật ngược mặc định §7) / Từ chối.

## What the owner is NOT being asked to decide
Owner không được yêu cầu quyết định tên cột/model/migration file cụ thể, hay cấu trúc file test cụ thể — đó là chi tiết triển khai trong ranh giới đã duyệt. Owner cũng không được yêu cầu quyết định gì về các slice bị loại trừ (CRM UX, propagation, Portfolio, OPPM, Control Tower, Finance/Treasury) — mỗi cái có vòng đời Gate 1→2→3 riêng trong tương lai.
