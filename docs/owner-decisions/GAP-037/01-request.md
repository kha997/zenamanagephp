---
work_id: GAP-037
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: null
  plan: null
  branch: docs/GAP-037-project-treasury-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/263
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-16T00:27:36+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-16T00:27:36+07:00"
  updated_at: "2026-08-16T00:27:36+07:00"
generated_by: agent
---

## Owner Summary
ZENA chưa có cơ chế theo dõi dòng tiền cấp dự án (project cash) một cách đáng tin cậy — "chi phí" hiện nằm rải rác ở 2-3 nguồn không liên kết, không có nguồn nào ghi nhận "đã trả tiền thật hay chưa". GAP-037 xin phép chuẩn bị Gate 1 cho một Work ID Project Treasury mới, triển khai theo canonical SSOT §§7–8 (đã Owner-approved, merged), dùng PR #245 chỉ làm bằng chứng thiết kế non-normative — không phải thẩm quyền triển khai.

## Vấn đề vận hành
Audit runtime hiện tại (dưới đây) xác nhận: không có nguồn "cost" hay "cash" thống nhất nào tồn tại cho một dự án. Cụ thể:
- `ContractExpense` (`app/Models/ContractExpense.php:33-40`) ghi "chi phí" thủ công (labor/subcontractor/design_outsource/misc) nhưng **cố ý không** cho phép nhập vật tư (docblock dòng 10-14: "Material cost is NOT entered here... a manual materials category would double-count it") và **không có** trường trạng thái thanh toán (`status`/`paid_at`) — chỉ có `expense_date`, không phân biệt được "đã ghi nhận" với "đã trả tiền".
- Chi phí vật tư nằm ở `MaterialReceiptLine` (`app/Models/MaterialReceiptLine.php`), qua `Api\ContractController::costSummary()` (`ContractController.php:268-306`) — cũng chỉ có `unit_cost`/`quantity_received` tại thời điểm nhận hàng, **không có** trường thanh toán nào.
- `costSummary()` **không** cộng gộp `ContractExpense` với chi phí vật tư — đây là 2 con số tách biệt hoàn toàn, không có nơi nào trong code hiện tại cộng chúng lại thành "tổng chi phí dự án".
- `Project.actual_cost`/`budget_actual` (`app/Models/Project.php:44-59`) là field có thể ghi (`$fillable`) nhưng grep toàn repo không tìm thấy service nào tự động tính nó từ `ContractExpense` hay `MaterialReceiptLine` — hiện tại field này không có nguồn tính toán canonical, chỉ có thể được set thủ công nếu có.
- Phía "cash" duy nhất có ý nghĩa thật là `ContractPayment` (`status`: planned/paid/overdue + `paid_at`) — nhưng chỉ theo dõi tiền **thu vào** từ khách hàng qua hợp đồng, không theo dõi tiền **chi ra** cho nhà cung cấp/nhân công.
- `ReportPageController::cashflow()` (route `operator.reports.cashflow`, `ReportPageController.php:44-119`) là công cụ cashflow công ty đã có — phía "thu" (`thu`) đúng cash-basis (dùng `ContractPayment.status===paid`+`paid_at`), nhưng phía "chi" (`chi`) cộng `ContractExpense.amount` vô điều kiện theo `expense_date` — tức accrual, không phải cash-basis. Canonical SSOT §6.3/§8 đã ghi nhận đây là vi phạm bất biến Cost≠Cash đang tồn tại trong code, và cấm coi `ContractExpense` là "đã trả tiền" cho đến khi có quyết định kiến trúc riêng.

## Người dùng bị ảnh hưởng
- PM/Owner cần biết dự án đã chi bao nhiêu tiền mặt thật, còn nợ nhà cung cấp bao nhiêu — hiện không thể trả lời chính xác.
- Kế toán/thủ quỹ (nếu có) không có nơi ghi nhận tạm ứng, hoàn ứng, chuyển khoản nội bộ giữa các ví/tài khoản dự án.
- Các slice tương lai (Finance Control, Project OPPM) phụ thuộc vào một nguồn cash đáng tin cậy mà hiện chưa tồn tại — canonical SSOT §7.3 đã quy định chúng phải chờ Treasury "canonical" trước khi dùng số liệu Treasury thật.

## Bằng chứng
- Canonical SSOT: `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` §§6.3, 7, 8, 12 (items 2 và 7), 13, 14 — đọc tại `main` `4016e601ba8ca967a02b28ed7cf21ebfa1292e08` (đã fetch lại, xác nhận không drift).
- PR #245 pinned head `cd8b79d861f4c1bae5278b6c57f29cd14e505594` (OPEN, Draft, không đổi) — `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md`, 12-bảng ledger model (financial_parties, project_wallets, financial_documents, ledger_entries, payment_routes/legs, fund_chains, advances/advance_settlements, expense_approvals, reconciliations), §17 13 quyết định Owner đã chốt trong tài liệu gốc (posting ngay `posted_unreconciled`, expense cần duyệt, X có thể tự duyệt nhưng phải audit, internal transfer không ảnh hưởng revenue/expense/profit, route đa chặng chỉ đếm 1 lần, bản ghi đã post là bất biến).
- Issue #244 (`kha997/zenamanagephp#244`) — thân bài gốc, 0 comment, cùng 13 quyết định trên; không có amendment nào cần đọc thêm.
- Runtime audit (file:line ở trên): `ContractExpense.php`, `ContractPayment.php`, `MaterialReceipt.php`, `MaterialReceiptLine.php`, `Api\ContractController::costSummary()`, `ReportPageController::cashflow()`, `Project.php` — không có model/service `Treasury`/`Wallet`/`Ledger` nào tồn tại (grep `app/`, `database/migrations/`, `routes/`, `src/` → 0 kết quả).
- Ghi nhận riêng (không xử lý ở đây): canonical SSOT trên `main` hiện còn dòng trạng thái đầu tài liệu ghi "Gate 2 preparing/awaiting Owner review. Gate 3 not started" dù OWN-2026-009 đã qua đủ Gate 1→2→3 và merge — đây là stale documentation metadata, đề xuất xử lý bằng một governance/docs cleanup work item riêng, không gộp vào GAP-037.

## Tác động nếu không xử lý
Không có nguồn sự thật (source of truth) cho dòng tiền dự án — PM/Owner tiếp tục phải tự tổng hợp thủ công từ nhiều nơi, dễ nhầm "đã ghi nhận chi phí" với "đã trả tiền", và các slice tương lai (Finance Control, Project OPPM) không có gì đáng tin cậy để hiển thị ngoài các con số công ty tổng hợp hiện tại (vốn chính bản thân cũng có vấn đề cash-basis ở phía chi, theo SSOT §6.3).

## Phạm vi đề xuất
**Binding problem statement (không được diễn giải lại thành "Implement Project Treasury theo PR #245"):**

> Triển khai Project Treasury theo canonical SSOT §§7–8, sử dụng PR #245 tại pinned head `cd8b79d861f4c1bae5278b6c57f29cd14e505594` chỉ làm non-normative design evidence; trước mọi schema implementation phải giải quyết ownership/integration boundary giữa Treasury ledger với `ContractPayment`, `ContractExpense`, `MaterialReceiptLine`-derived cost, và existing company cashflow semantics (`ReportPageController::cashflow()`).

Project Treasury được định nghĩa (theo SSOT §7.1-7.2) là: transaction-level project cash mechanics — ledger / wallet / advances / transfers / reconciliation / evidence. Project Treasury **không phải**: statutory accounting, general ledger, revenue recognition, company P&L, duplicate receivables engine, duplicate company cashflow engine, và không được duplicate Contract payment lifecycle.

**Gate 1 này chỉ xin phép:** reconciliation đã thực hiện ở trên; xác định business problem; xác định dependency/boundary; chuẩn bị Gate 1 Owner packet; đề xuất phạm vi điều tra cho Gate 2.

**Gate 2 (nếu Gate 1 được duyệt) bắt buộc phải trả lời đủ 4 quyết định kiến trúc sau trước khi được phép đề xuất bất kỳ schema nào:**

**A. Cost authority** — Xác định canonical source hoặc composition rule của "cost incurred": `ContractExpense`, chi phí vật tư từ `MaterialReceiptLine`, Treasury financial document, hay một shared financial read model tổng hợp cả 3. Phải ngăn double-count chi phí vật tư — runtime hiện đã cố ý không cho nhập vật tư vào `ContractExpense` chính vì lý do này (xem Bằng chứng).

**B. Cash authority** — Xác định Treasury ledger có trở thành canonical source cho actual project cash movements hay không; nếu có, phải định nghĩa quan hệ với `ContractPayment`, thanh toán cho `ContractExpense`, thanh toán nhà cung cấp/vật tư, owner contribution, internal transfers, advances/settlements.

**C. Economic-event / no-double-posting rule** — Với từng nguồn hiện hữu (`ContractPayment`, `ContractExpense`, `MaterialReceiptLine`), Gate 2 phải xác định rõ vai trò: input, projection, reconciliation target, referenced evidence, hay bị Treasury thay thế làm canonical source. Không được để cùng một giao dịch kinh tế bị ghi nhận hai lần chỉ vì tồn tại ở hai model.

**D. Company cashflow integration** — Treasury không được tạo company-level cashflow calculator thứ hai cạnh tranh với `ReportPageController::cashflow()` (SSOT §6.3: đây là reuse target đã xác nhận), nhưng cũng không được sao chép/mặc nhiên kế thừa cách hiểu sai hiện tại rằng `ContractExpense` = tiền đã chi. Gate 2 phải xác định cách Finance Control/shared read semantics tương lai tiêu thụ Treasury cash facts sau khi Treasury trở thành canonical.

## Loại trừ rõ ràng
Không có ở giai đoạn này: migration; schema; model; controller; service; route; UI; permission; test cho runtime feature; seed/data migration; sửa `ReportPageController::cashflow()`; sửa `ContractExpense`; sửa `MaterialReceipt`/`MaterialReceiptLine`; implementation Finance Control; implementation Project OPPM; GAP-036; Today Workspace; production/deployment. Không sửa, merge hoặc đóng PR #245 hoặc PR #257. Không sửa dòng trạng thái stale trên canonical SSOT (ghi nhận riêng, xử lý bằng work item khác nếu cần).

## Đề xuất
Đội kỹ thuật đề xuất: tiến hành (fix now, ở phạm vi Gate 1 → Gate 2 investigation). Vấn đề là có thật và có bằng chứng runtime cụ thể (không có nguồn cost/cash thống nhất); rủi ro double-count đã được xác định trước khi có bất kỳ schema nào, đúng tinh thần "audit trước khi code" mà toàn bộ chuỗi công việc OWN-2026-009 đã thiết lập.

## Decision Needed
Owner chọn một: Approve to proceed to design (Gate 2) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ schema/migration/model/controller/service/route/UI nào (không có ở Gate 1 hay Gate 2 investigation). Owner không được yêu cầu duyệt cách trả lời 4 quyết định kiến trúc A-D — đó là nội dung Gate 2 sẽ đề xuất, Owner chỉ duyệt approach investigation ở đây. Owner cũng không được yêu cầu quyết định việc dọn dẹp stale metadata trên canonical SSOT hay xử lý GAP-036 — cả hai được ghi nhận riêng, tách biệt khỏi GAP-037.
