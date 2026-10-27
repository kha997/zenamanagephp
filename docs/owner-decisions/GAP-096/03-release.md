---
work_id: GAP-096
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-27-gap-096-treasury-s5-advances-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-096-treasury-s5-advances.md
  branch: docs/GAP-096-treasury-s5-advances
  pr: https://github.com/kha997/zenamanagephp/pull/375
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T14:06:17+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-096 Gate 3'. Given on the packet at PR head 8ce9b2ce00dbe8a8ad5ec30b9dde2c79bf2c3ee9; bound to implementation subject 629862b13018835d3e7cc35a9c3588aa62facb6b and implementation-tree digest 234e157841bc86b90d2ea0a5ae76cb810144bee2c0007c26b7bf4871ecf39578 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T13:31:36+07:00"
  updated_at: "2026-10-27T14:06:17+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-096 (Treasury S5) at subject 629862b13018835d3e7cc35a9c3588aa62facb6b (Gate 2 Option A). New TreasuryAdvanceService: advance documents (wallet -> party, direct) follow the S3 draft / submit / approve / reject / copy flow with the S3 self-approval and wallet-holder rules; approval locks the wallet (class 0), re-checks the balance, locks the document (5), posts the debit and inserts the treasury_advances row in one transaction (v17 7.5); the approval event is advance_approved so S3's self-approved expense total stays expense-only. Outstanding = amount - sum(apply) + sum(reverse), 0 once the advance is reversed (6.5/6.6). Option A: a settlement request is an append-only settlement_requested event (amount + allocations in context) on the advance document's treasury_expense_approvals log, closed by settlement_withdrawn (filer only), settlement_rejected (note required) or settlement_approved; one open request per advance. Approval locks cost sources (2), the advance (3) and the document (5), re-checks amount <= outstanding and each cost's remaining (shared S3 net allocation, locking read), then writes one approved_expense/apply settlement and its allocations (advance_settlement_id); no ledger entry. Removal (treasury.reverse, reason required) writes an approved_expense/reverse settlement and one reverse allocation per apply. Advance return (treasury.reverse, direct only): wallet (0) -> advance (3), amount <= outstanding, advance_return document + credit + cash_return/apply in one write. TreasuryPostingService::reverse now accepts advance (advance locked first, refused unless settlement net is 0, 2.2d) and advance_return (coupled cash_return/reverse, 2.2b). Ageing list: posted advances still owed, days since posting, buckets under_30 / 30_60 / 60_90 / over_90 (lower bound inclusive). API api.zena.treasury.advances.* (15 routes); operator: Tam ung card on the project page, advance page, register labels. No schema change, no new permission. Tests: TreasuryAdvanceServiceTest 12, API 2, web 2; real-MySQL races (settlement vs return on one advance; settlement vs S3 expense on one cost). Exact-head CI at 73632733 (629862b1 plus this packet only): 35/35 green."
technical_evidence:
  base_sha: "c7b8efffa06a4b81ca98134b1870ebde3bd59cc9"
  subject_sha: "629862b13018835d3e7cc35a9c3588aa62facb6b"
  implementation_tree_digest: "234e157841bc86b90d2ea0a5ae76cb810144bee2c0007c26b7bf4871ecf39578"
  verified_pr_head_sha: "629862b13018835d3e7cc35a9c3588aa62facb6b"
  verified_at: "2026-10-27T13:31:36+07:00"
owner_decision_binding:
  implementation_tree_digest: "234e157841bc86b90d2ea0a5ae76cb810144bee2c0007c26b7bf4871ecf39578"
  decision_recorded_at: "2026-10-27T14:06:17+07:00"
---

# GAP-096 — Gate 3 release decision (Treasury S5 advances and settlements)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-27 ("APPROVE GAP-096 Gate 3"), bound to implementation subject
`629862b13018835d3e7cc35a9c3588aa62facb6b` and implementation-tree digest `234e157841bc86b90d2ea0a5ae76cb810144bee2c0007c26b7bf4871ecf39578`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Ngân quỹ không ghi được tiền tạm ứng: không biết ai đang giữ bao nhiêu, đã quyết toán vào chi
phí nào, còn nợ bao lâu (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `629862b1`:
- **Tạm ứng:** lập đề nghị → gửi duyệt → duyệt thì tiền ra khỏi ví và mở khoản "còn nợ". Từ chối, sao chép, tự duyệt
  và quy tắc "chỉ chi từ ví mình giữ" giống chi phí S3.
- **Quyết toán bằng chi phí:** người giữ tạm ứng lập đề nghị gắn vào chi phí hợp đồng / dòng phiếu nhập → duyệt thì số
  còn nợ giảm. Đề nghị nằm trong nhật ký duyệt của tạm ứng; mỗi tạm ứng một đề nghị chờ; người lập rút được, người
  duyệt từ chối phải ghi lý do.
- **Không vượt:** khi duyệt kiểm tra lại có khoá: không vượt số còn nợ, không vượt chi phí phát sinh (tính chung với
  chi phí S3).
- **Gỡ quyết toán** (có lý do) trả lại chi phí; **nộp lại tiền thừa** thẳng vào ví dự án; "Đảo" nộp lại khôi phục số
  còn nợ; **tạm ứng chỉ đảo được khi đã gỡ hết quyết toán và nộp lại**.
- **Danh sách còn nợ theo tuổi nợ** (dưới 30 / 30–60 / 60–90 / trên 90 ngày) trên thẻ "Tạm ứng" của trang ngân quỹ dự
  án; trang chi tiết tạm ứng có quyết toán, nộp lại, nhật ký duyệt. Sổ giao dịch hiện "Tạm ứng", "Nộp lại tạm ứng".
- Không đổi cơ sở dữ liệu, không thêm quyền.

**3. Bằng chứng** — base `c7b8efff`; subject `629862b13018835d3e7cc35a9c3588aa62facb6b`;
digest `234e157841bc86b90d2ea0a5ae76cb810144bee2c0007c26b7bf4871ecf39578`.
- Test mới: 12 service, 2 API, 2 web; 2 test tranh chấp **MySQL thật** (quyết toán và nộp lại cùng lúc trên một tạm
  ứng; quyết toán và chi phí S3 cùng lúc trên một chi phí).
- Thử đột biến trên MySQL: bỏ khoá tạm ứng **cùng** lần đọc có khoá số còn nợ → test đỏ; bỏ khoá chi phí **cùng** lần
  đọc có khoá phân bổ → test đỏ. Bỏ riêng khoá (giữ lần đọc có khoá) → vẫn xanh, vì lần đọc có khoá cũng chờ dòng vừa
  ghi của bên kia; khoá lớp 2 / 3 giữ thứ tự rõ ràng và tránh deadlock.
- Trên máy: 166+ test Treasury (196 cùng Architecture); Api, Web, Zena, Deployment, Unit qua (trừ
  `GrandfatherLoaderTest` chỉ hỏng khi chạy bằng root); job tranh chấp MySQL 8/8; PHPStan, php-cs-fixer, SSOT lint,
  route guard, governance và docs lint sạch.
- CI trên PR head `73632733` (= `629862b1` + riêng file hồ sơ này): **35/35 xanh**, gồm job tranh chấp Treasury
  MySQL thật và Owner Governance Lint.

**4. Công khai**
- **Quyền:** tóm tắt Gate 2 ghi "lập/gửi: … kế toán". Theo mã quyền đã duyệt (`create_expense` / `submit_expense`,
  không thêm quyền), **kế toán không lập / gửi đề nghị tạm ứng hay quyết toán** (giống S3: kế toán chỉ duyệt); chủ DN,
  PM, kỹ sư lập được.
- Sự kiện duyệt tạm ứng tên `advance_approved` (khác `approved` của S3) để dòng "Trong đó tự duyệt" của chi phí không
  tính tạm ứng; tự duyệt tạm ứng vẫn được ghi và hiện nhãn trên trang tạm ứng.
- Đảo tạm ứng / nộp lại: khoá tạm ứng (3) rồi chứng từ (5); không khoá ví vì bút toán đảo được miễn kiểm tra số dư như
  S2.
- `TreasuryExpenseService`: ba hàm hỗ trợ (`requireCostSourceOf`, `lockCostSources`, `transition`) chuyển sang public,
  hành vi không đổi.
- Thẻ "Tạm ứng" lấy dữ liệu qua `@inject` trong view, nên `TreasuryPageController` không đổi.
- Test kiến trúc tài liệu nhận nhầm biến `$document` ở controller mới; đã đổi tên biến, không sửa test.
- Lệnh ẩn `treasury:concurrency-test-advance` chỉ dùng cho test.

**5. Ngoài phạm vi** — Nộp lại qua tuyến trung gian, hạn quyết toán riêng từng tạm ứng, báo cáo (S6), deploy.

**6. Rủi ro còn lại** — Thấp: không đổi lược đồ, không đổi quyền; luồng duyệt dùng lại S3; giới hạn chứng minh trên MySQL.

**7. Hoàn tác** — Revert squash commit (không có migration).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-096 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm S6.
