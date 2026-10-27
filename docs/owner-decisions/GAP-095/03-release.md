---
work_id: GAP-095
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-27-gap-095-treasury-s4b2-contract-payment-tracing-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-095-treasury-s4b2-contract-payment-tracing.md
  branch: docs/GAP-095-treasury-s4b2-contract-payment-tracing
  pr: https://github.com/kha997/zenamanagephp/pull/374
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T11:24:44+07:00"
  updated_at: "2026-10-27T12:11:17+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTED after Owner amendment 1 (AskUserQuestion, 'Sửa trong PR này (Recommended)'): the Owner Gate-3 approval bound to subject cc7513ce04d4ecaf7c1964393e6791a77acebb6f / digest 01caa5dffefe1ee6b4292bf5ce9731f5e4766d6d47920cb8f57dfd2d216addf7 is superseded because a736ee73 changed the tree to answer a Codex P2 finding: after a duplicate warning the Truy vet form reset to the full remaining amount and the first wallet, so confirming could silently change the allocation; the form now carries the payment id and reopens with the submitted amount and destination for that payment; new test test_a_retry_after_a_duplicate_warning_keeps_the_entered_amount_and_wallet is red before the fix and green after. Local after the fix: tests/Feature/Treasury 154 OK; PHPStan, php-cs-fixer, SSOT lint clean. Exact-head CI at a736ee73: 34/35 green; Owner Governance Lint red only because the packet was bound to the superseded digest, which this re-presented packet resolves. History below describes cc7513ce. GAP-095 (Treasury S4b-2) at subject a736ee734de9c4f0ae5f6baaa0628c2bfe7188c7 (Gate 2 Option A plus Owner amendment 1). TreasuryRouteService::createContractPaymentRoute traces part or all of a paid contract payment of the project to an expected destination wallet (v17 4.1 Case A, B2-T): no financial document, linked_contract_payment_id + expected_destination_wallet_id, allocation SUM(non-cancelled routes) <= payment amount read under a lock of the contract_payments row (class 1). Legs, leg reversal and cancel reuse S4b-1 with branches for a route without a document: a leg locks the payment (class 1, after the class-0 wallet lock) and requires status paid with paid_at (v17 4.2a); a leg reversal locks it without the paid check; completion uses the expected destination; cancel refused once completed. Reminder list contractPayments(): paid payments of the project and any payment with routes, with traced / arrived / remaining and flags not_fully_traced, no_longer_paid, over_traced. Option A duplicate warning both ways: declareFunding (and S4b-1 funding routes, through guardDuplicate) warn when a paid contract payment of the project has the same amount and paid_at date; tracing warns when a posted or in-transit funding / owner contribution has the same amount and transaction date; both confirmable. TreasuryDuplicateSuspected now carries a document or a contract payment. Summary line contract_payments_arrived (completed contract-payment routes). API: GET contract-payments, POST contract-payments/{payment}/routes. Operator: 'Thanh toán hợp đồng' card with flags and a Truy vết form, summary line, Case A routes on the route page. ContractPayment, its controller, contract pages and reports unchanged; no schema change; no new permission. Tests: TreasuryContractPaymentRouteTest 11, API 2, web 2; real-MySQL race (two traces of one payment past its amount) whose class-1 lock-removal mutation goes red. Local: tests/Feature/Treasury 153 OK; Api 806, Web 50, Zena, Architecture, Deployment OK; Unit OK except the known root-only GrandfatherLoaderTest; Treasury MySQL concurrency job 6/6; PHPStan no errors; php-cs-fixer 0 files; SSOT lint, route guard, governance and docs lints pass. Exact-head CI at cc7513ce: 35/35 green."
technical_evidence:
  base_sha: "de952191e9f533cec95ea6c0ecdf960ef7f83a0c"
  subject_sha: "a736ee734de9c4f0ae5f6baaa0628c2bfe7188c7"
  implementation_tree_digest: "210efecb73fa8e4a818ff9310beddbce1cafcaa6ecdf07bc719b23c97f7b4dde"
  verified_pr_head_sha: "a736ee734de9c4f0ae5f6baaa0628c2bfe7188c7"
  verified_at: "2026-10-27T12:11:17+07:00"
owner_decision_binding:
  implementation_tree_digest: "210efecb73fa8e4a818ff9310beddbce1cafcaa6ecdf07bc719b23c97f7b4dde"
  decision_recorded_at: null
---

# GAP-095 — Gate 3 release decision (Treasury S4b-2 contract-payment traceability)

## OWNER GATE 3: AWAITING OWNER (re-presented)

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 tại digest `01caa5df…` ("APPROVE GAP-095 Gate 3"). Sau đó Codex báo lỗi P2: khi truy vết bị
cảnh báo trùng, form tải lại với số tiền và ví đích mặc định, nên bấm "Vẫn ghi" có thể tạo tuyến sai số tiền, sai ví.
Owner chọn "Sửa trong PR này (Recommended)" (Owner amendment 1). Cây file đổi nên lần duyệt trên **không còn hiệu lực**.

**1. Vấn đề là gì?** Tiền chủ đầu tư trả theo đợt thanh toán hợp đồng không hiện ở ngân quỹ: không biết tiền đang
nằm ở ví nào, hoặc phải khai thêm "tiền nhận" dễ trùng (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — subject `cc7513ce`, rồi `a736ee73`:
- Trang ngân quỹ dự án có thẻ **"Thanh toán hợp đồng"**: các đợt đã thanh toán, đã truy vết, đã về ví đích, còn lại,
  cờ nhắc (chưa truy vết đủ / đợt không còn đã thanh toán / đã truy vết vượt số tiền).
- **"Truy vết"** một đợt: chọn ví đích và số tiền → tạo tuyến; ghi chặng, đảo chặng, huỷ tuyến như S4b-1. Tổng truy
  vết không vượt số tiền đợt; chặng chỉ ghi được khi đợt còn "đã thanh toán".
- Không tạo chứng từ ngân quỹ; không sửa đợt thanh toán, trang hợp đồng, báo cáo dòng tiền.
- **Cảnh báo khai trùng hai chiều** (có "Vẫn ghi") giữa tiền nhận và đợt thanh toán cùng số tiền, cùng ngày.
- Gửi lại sau cảnh báo giữ đúng số tiền và ví đích đã nhập cho đợt đó.
- Dòng mới ở số dư: "Tiền thanh toán hợp đồng đã về ví". Không đổi cơ sở dữ liệu, không thêm quyền.

**3. Bằng chứng** — base `de952191`; subject `a736ee734de9c4f0ae5f6baaa0628c2bfe7188c7`;
digest `210efecb73fa8e4a818ff9310beddbce1cafcaa6ecdf07bc719b23c97f7b4dde`.
- Test mới: 11 service, 2 API, 3 web (thêm test gửi lại sau cảnh báo, đỏ trước khi sửa); 1 test tranh chấp **MySQL
  thật** — gỡ khoá đợt thanh toán thì test đỏ.
- Trên máy: 154 test Treasury; trước đó 806 API, 50 Web, Zena, Architecture, Deployment, Unit qua (trừ test chỉ hỏng
  khi chạy bằng root); job tranh chấp MySQL 6/6; PHPStan, php-cs-fixer, SSOT lint, route guard sạch.
- CI chạy đúng trên `a736ee73`: **34/35 xanh**; Owner Governance Lint đỏ chỉ vì hồ sơ còn gắn digest cũ — gói trình
  lại này xử lý.

**4. Công khai**
- **Owner amendment 1** (Codex P2, form truy vết mất số đã nhập sau cảnh báo): sửa ở `a736ee73`; lần duyệt Gate 3 trước bị thay thế.
- Gate 2: Owner gõ "APPROVE GAP-095 Gate 2 Option" (thiếu tên phương án), rồi chọn "Option A (Recommended)" qua câu
  hỏi xác nhận.
- Cảnh báo khai trùng tiền nhận (S2 và tuyến S4b-1) giờ cũng so với đợt thanh toán hợp đồng đã thanh toán.
- Phía hợp đồng không bị chặn: nếu sửa đợt về chưa thanh toán hoặc giảm số tiền sau khi truy vết, ngân quỹ chỉ hiện
  cờ nhắc và không cho ghi chặng mới.
- Lệnh ẩn `treasury:concurrency-test-trace-payment` chỉ dùng cho test.
- Register cập nhật ở đợt đối soát sau.

**5. Ngoài phạm vi** — Tạm ứng (S5), báo cáo (S6), sửa `ContractPayment`, trang hợp đồng, báo cáo dòng tiền/KPI, deploy.

**6. Rủi ro còn lại** — Thấp: không đổi lược đồ, không đổi quyền; tái dùng luồng tuyến S4b-1 đã có test MySQL.

**7. Hoàn tác** — Revert squash commit (không có migration).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-095 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm S5, S6.
