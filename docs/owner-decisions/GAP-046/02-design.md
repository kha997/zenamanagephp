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
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-26T00:29:24Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-25T05:54:54Z"
  updated_at: "2026-08-26T00:29:24Z"
generated_by: agent
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
