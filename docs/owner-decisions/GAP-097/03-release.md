---
work_id: GAP-097
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
  spec: docs/audits/2026-10-27-gap-097-treasury-s6-dashboard-reports-readiness.md
  plan: docs/superpowers/plans/2026-10-27-gap-097-treasury-s6-dashboard-reports.md
  branch: docs/GAP-097-treasury-s6-dashboard-reports
  pr: https://github.com/kha997/zenamanagephp/pull/376
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T19:51:53+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-097 Gate 3'. Given on the packet at PR head b3e9277858f47275cbcfad4d0990c6310e4d89fe; bound to implementation subject 10e538febe86ebe9673b390cd64b11a0e29953ff and implementation-tree digest dc87a81f44def6f02c61b66b4422cbd35c24981bffeb0139fe46ab48840db76e (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-27T18:32:22+07:00"
  updated_at: "2026-10-27T19:51:53+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-097 (Treasury S6) at subject 10e538febe86ebe9673b390cd64b11a0e29953ff (Gate 2 Option A). Read-only. New TreasuryDashboardService: cards (investor funding, funding via completed routes, contract payments arrived, owner contribution, posted expenses and the self-approved part, held total, unreconciled funding, pending approvals count and amount, outstanding advances), wallet table (custodian, balance, reconciled / unreconciled, outstanding advances paid from the wallet, last ledger activity) and alerts with the Owner's fixed thresholds: funding / owner contribution posted_unreconciled more than 30 days after its transaction date, submitted expenses and advances, open settlement requests, advances in the S5 over_90 bucket (90 days inclusive), negative wallets, planned or partial routes; overview rows per visible project. New TreasuryReportService: register query with filters (date from / to, type, status, wallet, party, creator, approver, self-approved, reconciled), 50 per page, CSV of the filtered register; ten reports as headers + rows (cashflow by month of transaction date with legs by leg date, internal moves excluded and reversals netted into the original's month; wallets; custodians; advance ageing; expenses by category from net cost allocations; expenses by party, direct or via advance; self-approved expenses, advances and settlements; unreconciled ledger entries with age; open routes with money held on the way; reversals and adjustments); CSV with UTF-8 BOM and formula-injection escaping. New TreasuryTimelineService: one sentence per document, route leg and advance settlement of a month, oldest first, linked to route or advance. API api.zena.treasury.reports.* (overview, dashboard, register, timeline, reports/{report}); operator: dashboard section on the project page, register filters + pagination + CSV, report page, timeline page, overview columns on the Treasury index. View with treasury.view and project scope, CSV with treasury.export. Register filter parsing lives in TreasuryReportService (deptrac: API controllers must not depend on web controllers; first CI run at 05fdcb29 failed on that). No schema change, no permission change, no existing test changed. Exact-head CI at 21548a79 (10e538fe plus this packet only): 35/35 green. Tests: TreasuryReportServiceTest 7 (dashboard, alerts at 30/31 and 89/90 days, cashflow equals held total, reports, register filters, timeline, overview, other tenant never shown), TreasuryReportWebTest 4 (pages, CSV and export permission, overview, API)."
technical_evidence:
  base_sha: "129f3f390704a23447229a72cf5d057c9d0174c6"
  subject_sha: "10e538febe86ebe9673b390cd64b11a0e29953ff"
  implementation_tree_digest: "dc87a81f44def6f02c61b66b4422cbd35c24981bffeb0139fe46ab48840db76e"
  verified_pr_head_sha: "10e538febe86ebe9673b390cd64b11a0e29953ff"
  verified_at: "2026-10-27T18:35:01+07:00"
owner_decision_binding:
  implementation_tree_digest: "dc87a81f44def6f02c61b66b4422cbd35c24981bffeb0139fe46ab48840db76e"
  decision_recorded_at: "2026-10-27T19:51:53+07:00"
---

# GAP-097 — Gate 3 release decision (Treasury S6 dashboard and reports)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-27 ("APPROVE GAP-097 Gate 3"), bound to implementation subject
`10e538febe86ebe9673b390cd64b11a0e29953ff` and implementation-tree digest `dc87a81f44def6f02c61b66b4422cbd35c24981bffeb0139fe46ab48840db76e`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Dữ liệu tiền S1–S5 đã đủ nhưng chưa có chỗ xem tổng quan, sổ giao dịch chỉ 100 dòng mới nhất,
không xuất được số liệu (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — subject `10e538fe`, chỉ đọc:
- **Trang ngân quỹ dự án**, phần "Tổng quan ngân quỹ" ở đầu trang:
  - Cảnh báo: tiền nhận chưa đối soát quá 30 ngày, khoản chờ duyệt, đề nghị quyết toán chờ duyệt, tạm ứng còn nợ từ
    90 ngày, ví âm, tuyến chưa về đủ.
  - Thẻ tổng và bảng ví (người giữ, số dư, chưa đối soát, tạm ứng còn nợ, phát sinh cuối).
  - Nút "Báo cáo ngân quỹ" và "Dòng thời gian".
- **Sổ giao dịch:** lọc theo ngày, loại, trạng thái, ví, đối tác, người lập, người duyệt, chỉ tự duyệt; 50 dòng / trang;
  "Xuất CSV" theo bộ lọc (chủ DN, kế toán).
- **Trang báo cáo:** 10 báo cáo xem trên trang; "Xuất CSV" chỉ hiện với chủ DN, kế toán.
- **Dòng thời gian:** theo tháng, mỗi khoản một câu, có link tới tuyến / tạm ứng.
- **Trang Ngân quỹ chung:** mỗi dự án thêm đang giữ, chi phí, chưa đối soát, tạm ứng còn nợ, số cảnh báo.
- Không đổi cơ sở dữ liệu, không đổi quyền, không đổi cách ghi sổ.

**3. Bằng chứng** — base `129f3f39`; subject `10e538febe86ebe9673b390cd64b11a0e29953ff`;
digest `dc87a81f44def6f02c61b66b4422cbd35c24981bffeb0139fe46ab48840db76e`.
- Test mới: 7 service (tổng dòng tiền bằng tiền đang giữ; ngưỡng 30/31 và 89/90 ngày; mỗi bộ lọc; dữ liệu doanh nghiệp
  khác không lọt vào), 4 web / API (CSV, quyền xuất, bảng tổng).
- Trên máy: 207 test Treasury + Architecture; Api, Web, Zena, Deployment, Unit qua (trừ `GrandfatherLoaderTest` chỉ
  hỏng khi chạy bằng root); PHPStan, php-cs-fixer, SSOT lint, route guard, governance và docs lint sạch.
- CI trên PR head `21548a79` (= `10e538fe` + riêng file hồ sơ này): **35/35 xanh**, gồm `code-quality` (deptrac) và
  Owner Governance Lint.

**4. Công khai**
- **Dòng tiền theo ngày giao dịch;** tiền nhận qua trung gian được tính khi chặng từ bên ngoài về ví (theo ngày chặng),
  còn thẻ "Nhà đầu tư đã trả" tính khi tuyến hoàn tất — hai con số có thể lệch trong lúc tuyến đang đi.
- **Tạm ứng quá hạn tính từ đủ 90 ngày** (đúng nhóm "trên 90 ngày" của S5, như thiết kế §1); phần test của thiết kế
  ghi mốc "90 vs 91", test thực tế kiểm 89 vs 90.
- "Tạm ứng còn nợ" ở bảng ví là tạm ứng **chi từ ví đó**; báo cáo "Tiền theo người giữ" cộng theo người nhận tạm ứng.
- Nghi trùng không thành cảnh báo vì hệ thống không lưu dấu nghi trùng.
- Kế hoạch ghi một file test cho mỗi service / API; thực tế gộp vào 2 file (`TreasuryReportServiceTest`,
  `TreasuryReportWebTest`), cùng phạm vi kiểm tra.
- Không sửa test hiện có; mô tả trang Ngân quỹ chung đổi chữ.
- CI lần đầu (subject `05fdcb29`) đỏ ở `code-quality`: deptrac cấm controller API phụ thuộc controller web (hàm đọc bộ
  lọc sổ giao dịch). Đã chuyển hàm đó vào `TreasuryReportService` (cùng file trong allowlist), hành vi không đổi.

**5. Ngoài phạm vi** — Chứng từ đính kèm, BOQ / ngân sách, chuỗi quỹ, công nợ theo hợp đồng, ngưỡng cấu hình, Excel,
biểu đồ, deploy.

**6. Rủi ro còn lại** — Thấp: chỉ đọc; số liệu lấy lại từ các service đã có test.

**7. Hoàn tác** — Revert squash commit (không có migration).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-097 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release.
