---
work_id: GAP-120
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
  spec: docs/audits/2026-11-10-gap-120-month-end-reminders-evidence.md
  plan: docs/superpowers/plans/2026-11-10-gap-120-month-end-reminders.md
  branch: docs/GAP-120-month-end-reminders
  pr: https://github.com/kha997/zenamanagephp/pull/410
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
  created_at: "2026-11-10T14:41:15+07:00"
  updated_at: "2026-11-10T14:41:15+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-120 at subject 670bb384488ca017fc49f813f09fb8d7d4e0c6ef (Gate 2 W-1, T-1, C-1, R-1 plus Owner amendment 1; one PR, implementation commits d8d0deb0 and 5a38cffc; no migration). New App\\Services\\Reminders\\MonthEndReminderService: plan() read-only, send() writes in-app notifications (type month_end.time_review / payroll_close / overhead_close, channel inapp, priority normal, link_url, event_key '<type>:<month>:<date>' so at most one per person, kind and month per day). Time review: submitted entries dated from the start of last month to today; project entries to the project PM, own-PM and PM-less to payroll.close holders, non-project to payroll.manage holders other than the author (fallback payroll.close); only from three days before month end to day 5. Payroll / overhead close for last month to payroll.close holders, every day from day 1 until closed, only for tenants with staff records; links to the payroll period page, the payroll index, or the overhead page. New command reminders:month-end {--dry-run} {--date=}; Console\\Kernel schedules it daily 08:00 Asia/Ho_Chi_Minh behind ENABLE_SCHEDULER. Go-live runbook documents ENABLE_SCHEDULER=true and the dry run. Amendment 1: ScheduleContractTest's approved workload list gains reminders:month-end (CI caught the contract, reproduced locally, fixed). Tests: MonthEndRemindersTest (8) red before, green after on SQLite and MySQL; dry run and real run on sample data (PM received 'Còn 1 dòng giờ chờ bạn duyệt, cũ nhất 24/11', a second run the same day wrote nothing). PHPStan, php-cs-fixer, ssot:lint, deptrac, governance lint pass. Local Unit + Feature + Integration 3023 tests: the 2 known root-only failures plus the schedule contract (since fixed; tests/Feature/Console re-run green apart from the root-only one); Feature/Api 825 green. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "5ff8671a4b9efeead2e496420baf512880842364"
  subject_sha: "670bb384488ca017fc49f813f09fb8d7d4e0c6ef"
  implementation_tree_digest: "eb4a0941d4c34490f34deb8cb5f9a8feb21537bdc205baa9f5cd4b18a21b03e4"
  verified_pr_head_sha: "670bb384488ca017fc49f813f09fb8d7d4e0c6ef"
  verified_at: "2026-11-10T14:41:15+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-120 — Gate 3 release decision (month-end reminders)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Không có gì nhắc PM duyệt giờ, và không có gì nhắc Owner / kế toán chốt lương và chi phí chung cuối
tháng.

**2. Sau thay đổi (Gate 2 W-1, T-1, C-1, R-1 + bổ sung 1)** — subject `670bb384`.

**Mỗi sáng 8:00 (giờ Việt Nam)**, hệ thống ghi thông báo vào mục "Cập nhật chưa đọc" trên trang **"Hôm nay"**. Bấm vào là tới
đúng trang cần làm. Có 3 loại thông báo:

- **Giờ chờ duyệt**:
  - người nhận:
    - giờ thuộc công trình → PM của công trình;
    - giờ của chính PM, hoặc công trình chưa có PM → người chốt lương;
    - giờ không thuộc công trình → kế toán;
  - nội dung: "Còn N dòng giờ chờ bạn duyệt, cũ nhất dd/mm";
  - thời gian: từ 3 ngày cuối tháng đến ngày 5 tháng sau.
- **Chưa chốt lương tháng trước** → người chốt lương, mỗi ngày từ ngày 1 cho tới khi chốt.
- **Đã chốt lương, chưa chốt chi phí chung** → người chốt lương, mỗi ngày cho tới khi chốt.

**Quy tắc chung:**
- Mỗi người, mỗi loại tối đa **một thông báo một ngày**. Chạy lại lệnh trong ngày không tạo thêm.
- Chỉ nhắc chốt lương với công ty đã dùng phần lương.
- Lệnh `reminders:month-end` có `--dry-run` để xem trước.

**Bổ sung 1:** CI có test giữ danh sách cố định các việc chạy định kỳ đã duyệt. Đã thêm việc nhắc cuối tháng vào danh sách đó
theo lựa chọn của Owner.

**3. Đã kiểm thử** — base `5ff8671a`; subject `670bb384488ca017fc49f813f09fb8d7d4e0c6ef`; digest
`eb4a0941d4c34490f34deb8cb5f9a8feb21537bdc205baa9f5cd4b18a21b03e4`.
- **8 test mới**, đỏ trước, xanh sau, chạy cả SQLite lẫn **MySQL**. Kiểm tra:
  - nhắc đúng người, đúng khoảng ngày;
  - không trùng;
  - cách ly công ty;
  - chạy thử không ghi;
  - lịch 8:00.
- **Chạy thử trên dữ liệu mẫu:**
  - PM nhận "Còn 1 dòng giờ chờ bạn duyệt, cũ nhất 24/11";
  - chạy lần 2 cùng ngày không tạo thêm.
- PHPStan, php-cs-fixer, SSOT lint, deptrac, governance lint sạch.

**4. Lưu ý vận hành — việc anh/chị cần làm trên máy chủ:**
- **Đặt `ENABLE_SCHEDULER=true`** trong `.env` máy chủ. Hiện mặc định là tắt, nên mọi việc định kỳ (sao lưu, nhắc việc) đều
  không chạy.
- Cron `php artisan schedule:run` mỗi phút (đã có trong hướng dẫn go-live).
- Có thể chạy `php artisan reminders:month-end --dry-run` để xem trước.

Các bước này đã ghi trong `docs/runbooks/go-live.md`. Không migration.

**5. Ngoài phạm vi**
- Email.
- Nhắc nhân viên.
- SMS / Zalo.
- Trang cài đặt thông báo.
- Bật bộ chạy định kỳ trên máy chủ.
- Deploy.

**6. Rủi ro còn lại** — Thấp. Khi bật bộ chạy định kỳ, các việc định kỳ cũ (sao lưu, dọn log…) cũng bắt đầu chạy theo lịch
đã có sẵn.

**7. Hoàn tác** — Revert PR. Thông báo đã gửi vẫn nằm trong danh sách thông báo, người dùng đánh dấu đã đọc như thường.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ**
- **Unit + Feature + Integration:** 3.023 test.
  - 2 lỗi có sẵn do chạy bằng root (`GrandfatherLoaderTest`, `BackupStorageLocationTest`).
  - Lỗi test lịch định kỳ: đã sửa theo bổ sung 1. Chạy lại `tests/Feature/Console` và test nhắc việc thì xanh, trừ lỗi do
    chạy bằng root.
- **Feature/Api:** 825 test, tất cả xanh.
- **CI trên đúng head:** ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-120 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production, bật bộ chạy định kỳ trên máy chủ.
