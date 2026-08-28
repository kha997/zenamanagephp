---
work_id: GAP-046
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md
  plan: null
  branch: docs/GAP-046-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/288"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-28T00:10:00Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-25T05:54:54Z"
  updated_at: "2026-08-28T00:10:00Z"
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

**Sau (nếu Gate 2 được duyệt, rồi một phiên triển khai riêng thực hiện đúng ranh giới §11):** 1. Hai bảng mới `opportunity_service_lines`/`project_service_lines` (Option B, §3 tài liệu thiết kế — Owner đã LOCK IN PRINCIPLE ở Round 1) cho phép một Opportunity/Project mang nhiều Service Line cùng lúc. 2. Cột `provenance` giữ enum khai báo 4 trạng thái CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN theo SSOT, nhưng một dòng phân loại chỉ tồn tại khi Service Line chuẩn (DESIGN/CONSTRUCTION/INSPECTION) thực sự được biết/suy luận cụ thể — không có dòng "giữ chỗ" cho NEEDS_REVIEW/UNKNOWN (§4, §7 đã sửa theo Round 1). 3. Dữ liệu cũ trên `Opportunity.service_category`: `architecture/interior/landscape/structure/mep` → DESIGN/INFERRED, `construction` → CONSTRUCTION/INFERRED; `inspection`/`consulting`/`combined_package`/null KHÔNG tạo dòng phân loại nào (giữ nguyên `service_category` làm bằng chứng cho slice rà soát tương lai) — không bao giờ gán CONFIRMED. 4. `service_category` và toàn bộ hành vi hiện tại (báo cáo CRM, gợi ý AI) giữ nguyên không đổi — nền tảng mới chỉ cộng thêm, chưa có nơi nào đọc/ghi nó ở runtime. 5. Project lịch sử (đã chuyển đổi từ Opportunity trước khi nền tảng này tồn tại) KHÔNG được backfill (Option A, Owner đã quyết Round 1) — vẫn UNKNOWN-do-thiếu-dữ-liệu.

## Vai trò bị ảnh hưởng
Không có vai trò người dùng cuối nào thấy thay đổi ở bước này — đây là nền tảng dữ liệu thuần backend, chưa có UI/gate/luồng nghiệp vụ nào nối vào. Đội kỹ thuật của các slice tương lai (CRM Classification UX, Opportunity→Project Propagation, Portfolio Membership...) sẽ là bên tiêu thụ nền tảng này.

## Được phép / Không được phép
Được phép (nếu duyệt): tạo 2 bảng mới + 2 model mỏng + quan hệ đọc `serviceLines()` + lệnh backfill 1 chiều phía Opportunity. Không được phép: sửa giá trị mặc định "architecture" hiện tại; nối runtime Opportunity→Project; sửa CRM form/gate; đụng vào Quote/Contract/Portfolio/OPPM/Control Tower/Finance/Treasury.

## Trạng thái và bước tiếp theo
Lifecycle đúng (sửa theo Owner Round 1, không còn coi Gate 3 là bước triển khai): Gate 1 (approved, merged to main) → **Gate 2 (tài liệu này, sau khi sửa Round 1, đã trở lại awaiting_owner cho Round 2)** → nếu Owner duyệt Gate 2: một **phiên triển khai mới** (implementation session) → implementation plan → triển khai theo TDD → verify kỹ thuật (bao gồm bằng chứng CI thật trên MySQL) → gói Gate 3 được trình `awaiting_owner` → Owner review Gate 3 → chỉ release/merge sau khi Gate 3 được duyệt. Duyệt Gate 2 chỉ cho phép triển khai bắt đầu trong ranh giới §11 của tài liệu thiết kế ở một phiên/PR riêng sau này — **Gate 3 là cổng release, không phải nơi bắt đầu code**. Gate 3 chưa bắt đầu.

## Ngoại lệ
Câu hỏi ranh giới về backfill phía Project (§7 tài liệu thiết kế) đã được Owner **QUYẾT ĐỊNH ở Round 1**, không còn là câu hỏi mở: **Option A — KHÔNG backfill lịch sử phía Project** trong GAP-046. Mọi Project hiện có chưa có phân loại chuẩn giữ nguyên UNKNOWN-do-thiếu-dữ-liệu; thiết kế này không suy luận thành viên Project từ dữ liệu Opportunity lịch sử trong GAP-046. Không còn "mặc định bảo thủ chờ xác nhận" — đây là quyết định ràng buộc.

## Hành vi người dùng nhìn thấy
Không có gì thay đổi trên màn hình ở giai đoạn này — đây là nền tảng dữ liệu, chưa có UI nào đọc/ghi nó.

## Kịch bản chấp nhận
Xem mục 12 ("Acceptance tests") của `docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md` — 8 tiêu chí gốc (cách ly tenant, ràng buộc duy nhất, validate provenance, round-trip migration LIVE trên MySQL thật, backfill idempotent, backfill không bao giờ tạo CONFIRMED, 2 test suite hiện tại (`BusinessKpiServiceTest`, gợi ý AI) không bị ảnh hưởng, quan hệ Eloquent trả đúng dữ liệu) **cộng thêm 11 tiêu chí bổ sung theo Owner Round 1 (mục A–K)**: A) `service_line` chỉ nhận đúng DESIGN/CONSTRUCTION/INSPECTION; B) giá trị không hợp lệ bị từ chối; C) nhóm architecture-family chỉ tạo DESIGN/INFERRED; D) `construction` chỉ tạo CONSTRUCTION/INFERRED; E) `inspection`/`consulting`/`combined_package` không tạo dòng nào; F) null/không nhận diện được không tạo dòng nào, vẫn UNKNOWN-do-thiếu-dữ-liệu; G) backfill không bao giờ tạo CONFIRMED; H) backfill idempotent; I) ghi cross-tenant giữa child và parent bị từ chối; J) số lượng backfill lịch sử phía Project đúng bằng KHÔNG; K) không có runtime propagation Opportunity→Project — tất cả sẽ trở thành checklist Gate 3.

## Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1 + làm rõ thêm theo chỉ đạo Owner Round 1: không sửa write-path mặc định "architecture"; không nối runtime Opportunity→Project; không CRM UX/gate hay bất kỳ luồng rà soát/remediation runtime nào cho các trường hợp "không tạo dòng" (`inspection`/`consulting`/`combined_package`/null); không Quote Scope Snapshot; không Contract Service-Line; không Portfolio Membership; không Project OPPM; không Control Tower; không Finance/Treasury; **không backfill phía Project cho các dự án đã chuyển đổi trước đó — đã QUYẾT ĐỊNH loại trừ (Option A, Owner Round 1), không còn chờ xác nhận**; không sửa đổi schema `projects.tenant_id` rộng hơn phạm vi (§5 tài liệu thiết kế).

## Decision Needed
Owner Round 1 đã LOCK Option B (§3, in principle) và Option A ở §7 (loại trừ backfill phía Project, đã quyết định). Tài liệu đã sửa này (trình lại Round 2) yêu cầu Owner chọn một trong: **Duyệt** toàn bộ thiết kế đã sửa (§2–§13 tài liệu thiết kế, gồm ngữ nghĩa UNKNOWN/NEEDS_REVIEW đã sửa ở §4/§7, bất biến tenant-parent đã sửa ở §5, lifecycle Gate 2→triển khai→Gate 3 đã sửa ở §11, và ma trận acceptance mở rộng A–K ở §12) để cho phép triển khai đúng ranh giới §11 / **Yêu cầu thay đổi thiết kế thêm** / **Từ chối**.

## What the owner is NOT being asked to decide
Owner không được yêu cầu quyết định tên cột/model/migration file cụ thể, hay cấu trúc file test cụ thể — đó là chi tiết triển khai trong ranh giới đã duyệt. Owner cũng không được yêu cầu quyết định gì về các slice bị loại trừ (CRM UX, propagation, Portfolio, OPPM, Control Tower, Finance/Treasury) — mỗi cái có vòng đời Gate 1→2→3 riêng trong tương lai.
