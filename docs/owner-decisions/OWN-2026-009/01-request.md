---
work_id: OWN-2026-009
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/OWN-2026-009-one-page-management-ssot-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/262
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-15T00:55:25+07:00"
  owner_response_reference: "Owner Gate 1 decision — APPROVE, recorded in-session on 2026-08-15 against reviewed PR #262 head 5441195ade2a416d6d3f3870fa6fd6cde38a2f02: 'OWN-2026-009 — Gate 1 Owner Decision: APPROVE. Tôi phê duyệt Gate 1 cho phạm vi docs-only canonicalization of ZENA One-Page Management shared semantics / canonical SSOT như trình bày trong docs/owner-decisions/OWN-2026-009/01-request.md tại PR #262, current reviewed head 5441195ade2a416d6d3f3870fa6fd6cde38a2f02. Binding scope — In scope: one canonical CRM/Project semantic spine; multi-value Service Line taxonomy DESIGN/CONSTRUCTION/INSPECTION; Service Scope/Discipline kept separate from Service Line; Project Health shared read-model semantics; Project OPPM as consumer/read-drilldown, not a second PM system; Contract lifecycle != Contract attention; Cost != Cash != Revenue != Profit; missing finance data != zero/green/paid/certain; Treasury vs Finance Control vs Contract Control boundaries; implementation-vs-design matrix; recommended future implementation-slice decomposition; explicit source references to PR #257 and PR #245 exact pinned heads and Issue #248 amendments. Out of scope: any migration, model, controller, service, route or UI/runtime change; fixing Opportunity.service_category default; GAP-036; implementation of Service Line, CRM stages, Quote snapshot, Project OPPM, Control Tower, Contract Control, Finance Control or Treasury; closing or merging PR #257/#245; Today Workspace; production/deployment work; unrelated operational gaps. PR #257 and PR #245 remain active design sources only, not implementation or release authority. Authorization after this approval: record this Gate 1 approval in the active 01-request.md according to governance; after the Gate-1-record-only commit is pushed and governance lint is green, Gate 2 design preparation is authorized; Gate 2 must define the actual canonical SSOT document structure and exact normative semantics and must not expand into runtime implementation; keep PR #262 Draft; no merge/release authorization is granted; do not infer Gate 2 approval.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-15T00:47:13+07:00"
  updated_at: "2026-08-15T00:55:25+07:00"
generated_by: agent
---

## Owner Summary
PR #257 và PR #245 mô tả một kiến trúc quản lý "One-Page Management" (Control Tower, Portfolio Thiết kế/Thi công/Kiểm định, Service-Line taxonomy, Contract & Finance Control, Project Treasury) trải dài 9+ slice triển khai tương lai, nhưng bản thân #257/#245 không phải là gói Gate 1 có thể merge — chúng là 4 tài liệu thiết kế hội thoại, chưa có Work ID, chưa qua vòng đời 3 cổng. OWN-2026-009 xin phép chuẩn bị một tài liệu SSOT **chỉ-tài-liệu** hợp nhất đúng phần ngữ nghĩa dùng chung (shared semantics) mà mọi slice tương lai sẽ tham chiếu, từ current main, dẫn chiếu #257/#245 làm bằng chứng nguồn — không merge, không đổi runtime.

## Vấn đề vận hành
Sau khi audit đầy đủ #257 (3 spec: control-tower, contract-finance, service-line-taxonomy) và #245 (Treasury) đối chiếu với current `origin/main` (`d0d89e84a858e8038e99ffbbf48e536ee297d8e0`) và Issue #248 (3 amendment do chính Owner viết), kết quả cho thấy:
- Không có xung đột kiến trúc nghiêm trọng giữa #257 và #245 — ranh giới Treasury (transaction-level, project) vs Finance Control (company-wide) vốn đã tách bạch theo phạm vi, chỉ thiếu một đoạn tham chiếu chéo tường minh.
- Có đúng 1 xung đột runtime đang hoạt động: `Opportunity.service_category` mặc định `'architecture'` (`database/migrations/2026_07_09_100000_create_leads_table.php:47-48`) — vi phạm trực tiếp quy tắc "không bao giờ mặc định về Design/Architecture" mà cả 3 spec của #257 lặp lại nhiều lần.
- Toàn bộ phần OPPM/Control Tower/Portfolio/Treasury là DESIGN_ONLY_NOT_IMPLEMENTED — repo hiện tại là nền sạch (clean slate), không có cột `service_line`/`project_type`, không có bảng Treasury/Wallet/Ledger nào tồn tại.
- Có rủi ro trùng lặp mô hình/tính toán nếu triển khai vội: `BusinessKpiService::outstandingDebt()` và `ReportPageController::cashflow()` đã là "receivables aging"/"company cashflow" thật, gần khớp công thức mà #257 đề xuất — slice tương lai phải tái sử dụng, không viết lại.

Nếu không có một tài liệu SSOT hợp nhất, mỗi slice tương lai (CRM classification, Project OPPM, Contract Control, Finance Control, Treasury...) có nguy cơ tự suy diễn lại ngữ nghĩa dùng chung khác nhau, hoặc dùng nhầm #257/#245 như thể chúng đã là authority triển khai.

## Người dùng bị ảnh hưởng
- Owner — người sẽ phải ra quyết định Gate 1/2/3 cho từng slice triển khai tương lai (CRM, Project OPPM, Contract Control, Finance Control, Treasury...) và cần một điểm tham chiếu ngữ nghĩa duy nhất, không phải đọc lại 4 tài liệu thiết kế mỗi lần.
- Các agent kỹ thuật thực hiện từng slice sau này — cần một SSOT để không tự phát minh lại field/khái niệm đã có (`project_type`, "Contract attention", receivables aging...).
- PM/Sales dùng CRM — bị ảnh hưởng gián tiếp bởi lỗi mặc định `service_category = architecture` hiện tại (mục Bằng chứng).

## Bằng chứng
- Provenance: `origin/main` = `d0d89e84a858e8038e99ffbbf48e536ee297d8e0` (xác nhận lại ngay trước khi branch, khớp với SHA đã audit); PR #257 head = `ded7cf9f558bd7960b5eff5836140b1e15255b9a` (OPEN, Draft); PR #245 head = `cd8b79d861f4c1bae5278b6c57f29cd14e505594` (OPEN, Draft) — cả hai xác nhận qua `gh pr view`, không có drift so với lúc audit.
- 3 file spec của #257 tại pinned head: `docs/superpowers/specs/2026-08-12-zena-one-page-management-control-tower-design.md`, `docs/superpowers/specs/2026-08-12-zena-contract-finance-one-page-control-design.md`, `docs/superpowers/specs/2026-08-12-zena-service-line-taxonomy-design.md` — mỗi file tự ghi rõ: "conversational design approved by the Owner; not a repository Owner-Gate packet and not implementation authorization."
- Issue #248, 3 comment do Owner viết (association: owner) — xác nhận Issue #248 chỉ sở hữu phạm vi Project OPPM một-dự-án, không sở hữu Contract Control/Finance Control/Portfolio/Service-Line CRUD.
- `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md` (PR #245 pinned head) §17 — 13 quyết định cuối cùng đã được Owner phê duyệt trong chính tài liệu đó; Issue #244 (0 comment, thân bài rõ ràng).
- Repo hiện tại: `app/Models/Project.php:44-59` (không có cột phân loại service-line/project-type); `app/Models/Opportunity.php:87-90` (`service_category` đơn giá trị, mặc định `architecture`); `app/Services/BusinessKpiService.php:59-104` (aging engine đã tồn tại); `app/Http/Controllers/Web/ReportPageController.php:44-119` (cashflow công ty đã tồn tại, không nhầm `net` thành `profit`); grep toàn repo cho `Treasury|Wallet|Ledger` tại `app/`, `database/migrations/`, `routes/`, `src/` → 0 kết quả.
- `docs/owner-governance/packet-schema.yml`, `docs/owner-decisions/*` (15 work item hiện có) — xác nhận `GAP-036` và `OWN-2026-009` là ID kế tiếp chưa cấp phát, không trùng.

## Tác động nếu không xử lý
Mỗi slice triển khai tương lai (dự kiến ít nhất 9-12 slice theo phân rã trong #257/#245) sẽ phải tự đọc lại 4 tài liệu thiết kế dài (~2,300+ dòng cộng lại) và tự diễn giải ranh giới — rủi ro diễn giải khác nhau giữa các agent/phiên làm việc khác nhau, lặp lại tính toán đã có (aging, cashflow), hoặc vô tình vi phạm bất biến đã Owner chốt (Cost≠Cash≠Revenue≠Profit, Contract lifecycle≠attention, không mặc định Design/Architecture).

## Phạm vi đề xuất
Chuẩn bị Gate 2 (design) cho một tài liệu SSOT chỉ-tài-liệu duy nhất, đặt tại `docs/superpowers/specs/` (tên file/vị trí chính xác sẽ chốt ở Gate 2), hợp nhất:
- Nguyên tắc sản phẩm gốc: một CRM pipeline, một Project canonical, Service Line đa giá trị (DESIGN/CONSTRUCTION/INSPECTION), Scope/Discipline là chiều riêng.
- Ranh giới Issue #248 (Project OPPM) vs Contract Control vs Finance Control vs Control Tower vs Portfolio — y nguyên như 3 comment Owner đã chốt trên Issue #248.
- Bất biến tài chính dùng chung: Cost≠Cash≠Revenue≠Profit; Contract lifecycle≠Contract attention; dữ liệu thiếu ≠ 0/xanh/chắc chắn.
- Ranh giới Project Treasury vs Finance Control vs Contract Control (bổ sung đoạn tham chiếu chéo còn thiếu, theo đúng layering đã có sẵn trong tài liệu Contract-Finance §7).
- Bản đồ implementation-vs-design (matrix) đã audit trong OWN-2026-009 Gate 1 này, làm phụ lục bằng chứng.
- Thứ tự phân rã slice khuyến nghị (đã trình bày ở báo cáo audit gửi Owner) — chỉ mang tính khuyến nghị, mỗi slice vẫn cần Work ID/Gate lifecycle riêng.

## Loại trừ rõ ràng
Không có migration, model, controller, route, service, UI nào được tạo hoặc sửa. Không đổi runtime behavior. Không merge #257 hoặc #245. Không tạo Gate 2 packet ở bước này (chỉ xin phép chuẩn bị Gate 2 sau khi Gate 1 được Owner duyệt). Không xử lý GAP-036 (báo cáo riêng, không gộp vào work item này). Không đụng đến GAP-010b, GAP-034, GAP-035, GAP-011, Today Workspace implementation, production secrets, deployment workflows, Redis DashboardApiTest defect, hay bất kỳ gap vận hành nào khác. Không tự sửa `OPERATIONAL_GAP_REGISTER.md`.

## Đề xuất
Đội kỹ thuật đề xuất: tiến hành (fix now) — rủi ro thấp (chỉ tài liệu, không runtime), có bằng chứng đầy đủ từ audit, và có tiền lệ trực tiếp thành công cho việc chuẩn bị Gate 1 dạng "docs-only canonical SSOT" (`GAP-035` Gate 1, `OWN-2026-008`).

## Decision Needed
**Owner đã chọn: Approve to proceed to design (Gate 2)**, với binding scope liệt kê trong `decision_provenance.owner_response_reference` — chốt tại PR #262 head `5441195ade2a416d6d3f3870fa6fd6cde38a2f02`. Gate 2 design preparation được uỷ quyền; Gate 2 owner_decision vẫn phải là quyết định riêng, không được suy luận từ phê duyệt Gate 1 này.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ implementation nào (không có), không được yêu cầu duyệt Gate 2 content cụ thể (chưa soạn), không được yêu cầu quyết định đóng #257/#245 (đó là quyết định riêng, chỉ đặt ra sau khi bản SSOT được merge), và không được yêu cầu duyệt GAP-036 (báo cáo riêng, tách biệt work item).
