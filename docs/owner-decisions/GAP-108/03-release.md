---
work_id: GAP-108
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-04-gap-108-treasury-thresholds-advance-deadline-evidence.md
  plan: docs/superpowers/plans/2026-11-04-gap-108-treasury-thresholds-advance-deadline.md
  branch: docs/GAP-108-treasury-thresholds-advance-deadline
  pr: https://github.com/kha997/zenamanagephp/pull/398
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
  created_at: "2026-11-04T16:06:20+07:00"
  updated_at: "2026-11-04T16:06:20+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-108 at subject 7464feaacc67cbd77fd89d3c32932a26c038d1ab (Gate 2 N-1, S-1, H-1, Q-1, P-1; exact allowlist; one PR, two implementation commits). Thresholds: TreasurySettingsService keeps two tenant-wide values in tenants.settings (treasury_unreconciled_funding_days default 30, treasury_advance_default_term_days default 90), integers 1-365, changed only by treasury.self_approve_expense holders under a tenant row lock with an AuditLog treasury.thresholds.changed (old / new); missing or invalid values fall back to the defaults; form on the company Treasury page (rbac:treasury.self_approve_expense); TreasuryDashboardService reads the funding threshold, returns the thresholds and counts overdue advances by the ageing row flag; alert labels follow the threshold. Due date: one additive migration adds nullable due_date to treasury_financial_documents and treasury_advances (SQLite ALTER TABLE ADD COLUMN, CHECK constraints kept; down() drops both); the advance request takes an optional due date (web and API), not before the request date, copied on rejected-copy and to the advance row at posting; changeDueDate sets / moves / clears it for a posted advance still owed, approver policy, mandatory reason, class-3 lock, AuditLog treasury.advance.due_date_changed; not a posting, so the period lock does not block it and no amount or balance changes. Overdue rule: own due date passed with money owed; without one the advance is due on the last day of the tenant term (default 90 keeps the S6 day-90 rule); ageing rows carry due_date, effective_due_date, overdue, overdue_days; buckets unchanged; the advance table, advance page (due-date form for approvers), ageing report (two new columns) and overdue alert use it; web route and API PUT due-date under rbac:treasury.approve_expense. No new permission, no new dependency. Tests: TreasuryThresholdsTest 4 and TreasuryAdvanceDueDateTest 4, each red before its commit and green after; the 8 also green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8. Treasury feature + architecture suites 253 green. Unit + Feature + Integration 2934 tests with only the 2 known root-only failures; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "08788500cae7107b878f5af061b10f646f1bd556"
  subject_sha: "7464feaacc67cbd77fd89d3c32932a26c038d1ab"
  implementation_tree_digest: "7b51f7fda1d937d2c99046ce0344bf8ceca9010ff47abd4b991f911553bf9462"
  verified_pr_head_sha: "7464feaacc67cbd77fd89d3c32932a26c038d1ab"
  verified_at: "2026-11-04T16:06:20+07:00"
owner_decision_binding: null
---

# GAP-108 — Gate 3 release decision (Treasury configurable thresholds and per-advance due date)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Ngưỡng cảnh báo Ngân quỹ cố định trong mã (30 / 90 ngày); tạm ứng không có hạn hoàn ứng nên không
biết ai trễ hạn.

**2. Sau thay đổi (Gate 2 N-1, S-1, H-1, Q-1, P-1)** — subject `7464feaa`:

**Ngưỡng cảnh báo**
- Trang **Ngân quỹ** chung có ô **"Ngưỡng cảnh báo"** (chỉ Owner thấy và đổi được):
  - tiền nhận chưa đối soát quá **N ngày** thì cảnh báo (mặc định 30);
  - tạm ứng **không có hạn** quá hạn sau **M ngày** (mặc định 90).
- Mỗi giá trị từ 1 đến 365; mỗi lần đổi ghi nhật ký giá trị cũ / mới; áp dụng cho cả công ty.
- Tên cảnh báo hiện đúng số ngày đang cài. Nhóm tuổi nợ 30 / 60 / 90 giữ nguyên.

**Hạn hoàn ứng**
- Form lập đề nghị tạm ứng có ô **"Hạn hoàn ứng"** (không bắt buộc, từ ngày đề nghị trở đi). API cũng nhận trường này.
- Duyệt & chi thì hạn chuyển sang khoản tạm ứng. Sao chép đề nghị bị từ chối thì giữ hạn.
- Trang chi tiết tạm ứng có ô **"Đổi hạn hoàn ứng"** cho người duyệt chi:
  - đặt / đổi / bỏ hạn khi khoản còn nợ;
  - bắt buộc ghi lý do, có nhật ký;
  - không phải bút toán nên không đổi số tiền / số dư và vẫn làm được trong kỳ đã khoá.

**Quá hạn**
- Có hạn: quá hạn từ ngày sau ngày hạn, nếu còn nợ.
- Không có hạn: quá hạn từ ngày thứ M sau ngày chi (mặc định 90, đúng như hiện nay).
- Hiển thị:
  - bảng tạm ứng trên trang Ngân quỹ dự án: cột **"Hạn"** và nhãn **"Quá hạn X ngày"**;
  - trang chi tiết: hạn (hoặc hạn tính theo ngưỡng chung) và số ngày quá hạn;
  - báo cáo tuổi nợ: thêm cột **"Hạn"** và **"Quá hạn (ngày)"**;
  - cảnh báo **"Tạm ứng quá hạn"** đếm theo quy tắc này.

Không thêm quyền mới, không thêm thư viện, không đổi số liệu đã ghi sổ. Một migration thêm hai cột ngày cho phép trống.
Chưa đổi cài đặt và chưa nhập hạn thì mọi màn hình và cảnh báo giống hệt trước.

**3. Đã kiểm thử** — base `08788500`; subject `7464feaacc67cbd77fd89d3c32932a26c038d1ab`;
digest `7b51f7fda1d937d2c99046ce0344bf8ceca9010ff47abd4b991f911553bf9462`.
- **Ngưỡng:** 4 test mới, hỏng khi chưa có mã, xanh khi có mã. Kiểm tra:
  - mặc định 30 / 90 cho kết quả như cũ;
  - đổi ngưỡng thì cảnh báo tiền nhận và tạm ứng không có hạn đổi theo, đúng từng ngày biên;
  - giá trị ngoài 1–365 bị từ chối; chỉ Owner đổi được; có nhật ký; công ty khác không bị ảnh hưởng;
  - form, quyền và tên cảnh báo trên web.
- **Hạn tạm ứng:** 4 test mới, hỏng khi chưa có mã, xanh khi có mã. Kiểm tra:
  - nhập hạn khi lập / sửa / sao chép; hạn trước ngày đề nghị bị từ chối; duyệt thì chép hạn;
  - quá hạn và số ngày quá hạn đúng; trả hết thì hết quá hạn; cảnh báo đếm đúng;
  - đổi hạn cần quyền duyệt và lý do, có nhật ký, không đổi số dư, làm được trong kỳ khoá, không đổi được khi hết nợ;
  - trang, form, báo cáo, API; công ty khác không truy cập được.
- **MySQL 8 thật:** migration chạy lên, gỡ, rồi chạy lại đều được; 8 test mới xanh khi chạy với MySQL.
- **Toàn bộ Ngân quỹ và test kiến trúc:** 253 test xanh.
- **Unit + Feature + Integration:** 2.934 test, chỉ 2 lỗi có sẵn do chạy bằng root. **Feature/Api:** 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**4. Lưu ý vận hành**
- Ngưỡng áp dụng chung cho cả công ty, chưa đặt riêng từng dự án được.
- Không có nhắc hạn tự động; người dùng xem trên trang Ngân quỹ, báo cáo và cảnh báo.

**5. Ngoài phạm vi** — nhắc hạn qua email / Slack / Zalo; phạt / lãi trễ hạn; ngưỡng theo dự án; cấu hình nhóm tuổi nợ;
deploy.

**6. Rủi ro còn lại** — Thấp:
- chỉ đổi cách phân loại và hiển thị;
- sổ cái, số tiền và trạng thái chứng từ không đổi;
- mặc định giữ nguyên hành vi cũ.

**7. Hoàn tác** — revert PR. Migration `down()` xoá hai cột `due_date` (mất các hạn đã nhập); sổ cái không đổi. Hai khoá
ngưỡng còn trong cài đặt công ty nhưng không được đọc nữa.

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-108 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
