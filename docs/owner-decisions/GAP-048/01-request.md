---
work_id: GAP-048
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: docs/audits/2026-08-29-gap-048-crm-classification-gates-audit.md
  plan: null
  branch: docs/GAP-048-gate1-crm-classification-audit
  pr: "https://github.com/kha997/zenamanagephp/pull/293"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-29T17:01:23Z"
  owner_response_reference: "GAP-048 Gate 1 Round 1 (relayed via coordinator session, reviewed exact PR head 47bd1ba547e2c4ddfd44534de1f12df4b869967e, canonical main at review time 87bb7d36128f878d8b6291705fed2c4262b11819): 'DECISION: MORE INFO / TARGETED CORRECTION REQUESTED. This is NOT a rejection of GAP-048. The core problem is substantively validated. Do NOT start over. Do NOT create a new Work ID. Continue the SAME GAP-048 session. Continue on PR #293. Keep PR #293 Draft. No implementation is authorized.' Owner directed two corrections, both within the existing Gate-1 scope (no reopening of the accepted core findings): (1) the Gate-1 packet's Bằng chứng section stated the team 'tự chạy lại bài test hồi quy hiện có của GAP-046' (reran the existing GAP-046 regression test), which contradicted the audit document's own §2 Method note stating no runtime PHPUnit reproduction was performed in this session (no vendor/ installed in this worktree) — corrected to distinguish (A) the test file was read and its control flow traced, (B) GAP-046's own previously-recorded Gate-3 release evidence (docs/owner-decisions/GAP-046/03-release.md) establishes the test passed on the released baseline via live CI, (C) this GAP-048 Gate-1 session itself did not rerun PHPUnit/runtime reproduction; static deterministic control-flow evidence accepted by Owner as sufficient. (2) the Quote-side audit was materially incomplete — it inferred 'formal Quote creation' enforcement from CrmPageController::storeQuote() (DRAFT creation) alone, without auditing the fuller native lifecycle (reviseQuote/sendQuote/acceptQuote/rejectQuote, the shared QuoteLifecycleService::accept()/reject()), the client-portal acceptance/rejection path (PortalQuoteController::accept()/reject()), or the zena-boq-core external Quote integration (linkExternalBoqProject/syncExternalQuote/createContract's dual native-or-external-accepted-Quote acceptance) — corrected with a complete lifecycle matrix; H8 reframed accordingly; the Gate-2 boundary wording corrected to no longer assert storeQuote() as the single centralized enforcement point (that placement decision is deferred to Gate 2). Owner explicitly reaffirmed 13 core findings as accepted and not requiring re-investigation (see audit document's now-updated root-cause matrix and this file's Vấn đề vận hành/Tác động sections, substantively unchanged). Correction scope strictly bounded to docs/audits/2026-08-29-gap-048-crm-classification-gates-audit.md and docs/owner-decisions/GAP-048/01-request.md; no app/**, src/**, routes/**, resources/**, database/**, tests/**, config/**, .github/** change authorized or made. PR #293 remains Draft throughout; no Gate 2 start, no design spec, no implementation plan, no merge authorized by this decision."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-29T15:20:55Z"
  updated_at: "2026-08-29T17:01:23Z"
generated_by: agent
---

## Owner Decision History — Round 1 (permanent record, never erased)

**Owner Gate 1 Round 1 decision: MORE INFO / TARGETED CORRECTION REQUESTED** (not a rejection — reviewed exact PR head `47bd1ba547e2c4ddfd44534de1f12df4b869967e`, canonical main `87bb7d36128f878d8b6291705fed2c4262b11819`). Full verbatim directive preserved in this file's frontmatter `decision_provenance.owner_response_reference` above. Two corrections directed, both addressed in this re-presentation (see the audit document's now-updated §2/§9/§15/§19 and this file's corrected Bằng chứng section below):

1. **Runtime-evidence narrative contradiction** — the prior version of this packet's Bằng chứng section claimed the regression test was rerun in this session, contradicting the audit's own honest §2 statement that no runtime PHPUnit reproduction was performed (no `vendor/` installed in this worktree). Corrected to distinguish source-reading/control-flow-tracing from an actual rerun, and to cite GAP-046's own previously-recorded Gate-3 CI evidence as the basis for "this test passed on the released baseline," not a fresh run in this session.
2. **Incomplete Quote lifecycle audit** — the prior audit inferred a single "formal Quote creation" enforcement point at `CrmPageController::storeQuote()` (DRAFT creation only) without auditing the full native lifecycle (revise/send/accept/reject, `QuoteLifecycleService`), the client-portal acceptance path, or the zena-boq-core external Quote integration. Corrected with a complete lifecycle matrix in the audit document; H8 reframed; the Gate-2 boundary wording no longer pre-selects `storeQuote()` as the enforcement point.

All 13 other findings from Round 0 were explicitly reaffirmed by Owner as accepted and not requiring re-investigation. This Round 1 record is preserved permanently and must not be removed by any future revision.

---

## Owner Summary
Đội kỹ thuật đã điều tra toàn bộ luồng phân loại "Loại dịch vụ" của CRM (Lead → Opportunity → Quote → chuyển đổi thành Project). Kết quả: hệ thống hiện tại vẫn dùng đúng cơ chế cũ mà tài liệu SSOT (2026-08-15) đã ghi nhận là vi phạm quy tắc — tự động gán "Kiến trúc" (architecture) khi người dùng không chọn gì — và nền tảng Service Line chuẩn mới do GAP-046 xây (đã release) hiện chưa được bất kỳ màn hình, luồng bán hàng, hay cổng kiểm soát nào sử dụng.

## Vấn đề vận hành
Khi nhân viên sales chuyển đổi Lead thành Opportunity mà không chọn loại dịch vụ, hệ thống âm thầm gán "architecture" (2 vị trí code xác nhận: `OpportunityController.php:217`, `LeadController.php:304`). Từ đó, Opportunity/Quote/chuyển đổi thắng thầu (WON) sang Project đều tiến hành bình thường mà không có bất kỳ kiểm tra nào về việc phân loại có thật/đáng tin hay không — kể cả khi phân loại chuẩn (GAP-046) hoàn toàn trống hoặc chỉ ở trạng thái suy luận (INFERRED), không có xác nhận (CONFIRMED) nào từng tồn tại trong hệ thống hôm nay vì chưa có màn hình nào cho phép tạo ra nó.

## Người dùng bị ảnh hưởng
Nhân viên sales tạo/chuyển đổi Opportunity (bị gán sai loại dịch vụ một cách âm thầm); người xem báo cáo KPI theo loại dịch vụ (`BusinessKpiService`, số liệu bị lệch về Architecture); người dùng tính năng gợi ý AI cho hạng mục thiết kế (`DesignItemPageController`, gợi ý bị lệch ngữ cảnh cho các dự án không phải kiến trúc); mọi slice tương lai phụ thuộc vào phân loại đáng tin cậy (Portfolio, OPPM, Control Tower) sẽ kế thừa vấn đề này nếu không xử lý trước.

## Bằng chứng
Đọc trực tiếp mã nguồn hiện tại trên baseline `87bb7d36` (không suy đoán), có trích dẫn file:line đầy đủ cho từng phát hiện — bao gồm việc đọc toàn văn bài test hồi quy hiện có của GAP-046 (`OpportunityConversionUnchangedTest.php`) và truy vết luồng điều khiển của nó, không phải chạy lại PHPUnit trong phiên Gate-1 này (worktree phiên này không có `vendor/` cài đặt, `php artisan test` không chạy được). Bằng chứng "WON→Project hôm nay không đọc/ghi dòng phân loại chuẩn nào" dựa trên hai nguồn độc lập, cả hai đều tường minh: (1) đọc trực tiếp luồng điều khiển của `OpportunityController::convert()`, không có nhánh nào tham chiếu Service Line; (2) hồ sơ Gate 3 đã ghi nhận trước đó của GAP-046 (`docs/owner-decisions/GAP-046/03-release.md`) xác nhận bài test này đã PASS trên baseline đã release qua CI thật. Toàn bộ chi tiết — ma trận 11 giả thuyết gốc rễ (H1–H11), bảng route/write-path, bảng consumer của `service_category`, bằng chứng UI, bằng chứng cổng pipeline/vòng đời Quote đầy đủ (native + portal + external)/WON, và các khoảng trống chưa biết — trong `docs/audits/2026-08-29-gap-048-crm-classification-gates-audit.md`.

## Tác động nếu không xử lý
Vấn đề mặc định sai âm thầm tiếp tục lan rộng mỗi khi có Opportunity mới; các slice tương lai đã được SSOT quy hoạch (Opportunity→Project Propagation, Quote Scope Snapshot, Portfolio Membership, Project OPPM, Control Tower) đều giả định có phân loại đáng tin cậy làm nền — nếu GAP-048 không được xử lý trước, mỗi slice đó sẽ phải tự vá vấn đề này một cách rời rạc.

## Phạm vi đề xuất
Xác nhận Gate 1 (điều tra + bằng chứng) đã hoàn tất và đầy đủ; nếu Owner duyệt, Gate 2 sẽ thiết kế cụ thể: UX phân loại trung thực cho Opportunity (0..N Service Line chuẩn), luồng xác nhận CONFIRMED tường minh cho người dùng, cổng kiểm soát ở scope_defined/Quote chính thức/WON dựa trên Service Line đã CONFIRMED, gỡ bỏ mặc định "architecture" âm thầm, và khả năng tương thích hẹp cho 2 consumer hiện đang dùng `service_category` (`BusinessKpiService`, `DesignItemPageController`). Ranh giới ứng viên chi tiết ở mục 19-20 của tài liệu audit.

## Loại trừ rõ ràng
Không đề xuất bất kỳ thay đổi migration/model/controller/service/route/UI nào ở Gate 1 này. Không gộp: Opportunity→Project Service-Line propagation, Project classification UX, historical Project backfill, Quote Scope Snapshot persistence, Contract multi-Service-Line, Portfolio Membership, Project OPPM, Operations Control Tower, Finance/Treasury, retirement cuối cùng của taxonomy cũ — mỗi cái đã có (hoặc sẽ có) Work ID + vòng đời Gate 1→2→3 riêng theo SSOT §14. Không đụng vào GAP-041/GAP-042/GAP-045 (work item CI/test-infrastructure không liên quan). Không mở lại GAP-046 hay GAP-047 (đã release). Không xác định lại phân bố dữ liệu production thật — hiện KHÔNG có sẵn để kiểm tra, được báo cáo rõ là UNKNOWN, không suy đoán.

## Đề xuất
Đội kỹ thuật đề xuất: Owner phê duyệt Gate 1 để tiến sang Gate 2 (thiết kế UX + cổng kiểm soát) — vấn đề đã được chứng minh bằng bằng chứng trực tiếp (không suy đoán), ranh giới phạm vi đã được đối chiếu với phụ thuộc thực tế trong repo và không phát hiện phụ thuộc ẩn nào buộc phải mở rộng phạm vi ra ngoài danh sách ứng viên đã liệt kê.

## Decision Needed
Owner chọn một trong: Approve để tiến sang thiết kế (Gate 2) / Yêu cầu thêm thông tin / Từ chối / Hoãn lại.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ thay đổi code, schema, migration, UI cụ thể, hay cơ chế kỹ thuật nào ở bước này — chỉ xác nhận vấn đề có thật, phạm vi điều tra đã đủ, và đáng để tiến hành thiết kế Gate 2. Owner cũng không được yêu cầu quyết định thiết kế UX/cổng kiểm soát cụ thể, tên trường, hay cơ chế tương thích ngược chính xác cho `service_category` — đó là quyết định của Gate 2.
