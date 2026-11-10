---
work_id: GAP-120
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-10-gap-120-month-end-reminders-evidence.md
  plan: docs/superpowers/plans/2026-11-10-gap-120-month-end-reminders.md
  branch: docs/GAP-120-month-end-reminders
  pr: https://github.com/kha997/zenamanagephp/pull/410
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-10T14:18:55+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-10, verbatim: 'APPROVE GAP-120 Gate 2 W-1 T-1 C-1 R-1'. Reviewed design head: e1d9bccaeb58fbcfe41b71e415d7af61a26a4d1b. Approves W-1 (time-review reminder to the project PM, own-PM entries and PM-less projects to payroll.close holders, non-project entries to payroll.manage holders; payroll-not-closed and overhead-not-closed reminders for the previous month to payroll.close holders, only for tenants using payroll), T-1 (daily 08:00 Asia/Ho_Chi_Minh; review reminders from three days before month end to day 5 of the next month; close reminders from day 1 until closed; at most one per person, kind and month per day via event_key), C-1 (in-app notifications on the Today page with links), R-1 (reminders:month-end command with --dry-run and --date, scheduled in Console\\Kernel behind ENABLE_SCHEDULER, go-live runbook step), one PR / one Gate 3, with the exact allowlist in this packet; no migration; not Gate 3, merge, release, deployment, or enabling the scheduler on the server."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T14:17:27+07:00"
  updated_at: "2026-11-10T14:40:36+07:00"
generated_by: agent
---

# GAP-120 — Month-end reminders: Gate 2 design

## OWNER GATE 2: APPROVED — W-1, T-1, C-1, R-1

Owner approved W-1, T-1, C-1 and R-1 in-session on 2026-11-10 against reviewed design head
`e1d9bccaeb58fbcfe41b71e415d7af61a26a4d1b`. This authorizes only the bounded implementation defined by this packet; it does not authorize Gate 3,
merge, release, or deployment.

## Owner Summary

Anh/chị cần chọn **4 điểm**:

1. **Nhắc ai, nhắc gì** — đề xuất **W-1**, gồm 3 loại nhắc:
   - **Giờ chờ duyệt** → PM của công trình; giờ không thuộc công trình → kế toán.
   - **Chưa chốt lương tháng trước** → người có quyền chốt lương.
   - **Đã chốt lương nhưng chưa chốt chi phí chung** → người có quyền chốt lương.
2. **Khi nào** — đề xuất **T-1**:
   - mỗi sáng 8:00, **chỉ khi còn việc**;
   - giờ chờ duyệt: từ 3 ngày cuối tháng đến ngày 5 tháng sau;
   - chốt lương / chi phí chung: từ ngày 1 tháng sau cho tới khi chốt xong;
   - mỗi người mỗi loại tối đa **một lần một ngày**.
3. **Kênh** — đề xuất **C-1**: thông báo trong ứng dụng, hiện ở "Cập nhật chưa đọc" trên trang **"Hôm nay"**, bấm vào là tới
   đúng trang cần làm. Email làm sau khi máy chủ thật đã cài email.
4. **Cách chạy** — đề xuất **R-1**:
   - lệnh `reminders:month-end`, có `--dry-run` để xem trước và `--date` để chạy thử cho một ngày bất kỳ;
   - lịch chạy 8:00 sáng giờ Việt Nam;
   - hướng dẫn go-live thêm bước bật `ENABLE_SCHEDULER=true`. Việc bật trên máy chủ do anh/chị làm.

**Không có migration**, không đổi quy tắc duyệt hay chốt. **Phát hành:** một PR, một Gate 3.

## Điểm 1 — Nhắc ai, nhắc gì

| Phương án | Nội dung |
|---|---|
| **W-1 (đề xuất)** | **(a) Giờ chờ duyệt** (dòng giờ `submitted` có ngày làm việc từ đầu tháng trước tới hôm nay): giờ thuộc công trình → **PM của công trình đó**. Nếu công trình chưa có PM, hoặc đó là giờ của chính PM (PM không tự duyệt được) → người có `payroll.close`. Giờ không thuộc công trình → người có `payroll.manage`. Mỗi người một thông báo: "Còn N dòng giờ chờ duyệt (cũ nhất dd/mm)", link tới trang **Duyệt giờ** đúng tuần cũ nhất. **(b) Chưa chốt lương** tháng trước → người có `payroll.close`, link tới trang lương tháng đó. **(c) Đã chốt lương, chưa chốt chi phí chung** tháng trước → người có `payroll.close`, link tới trang chi phí chung. (b) và (c) chỉ áp dụng cho công ty đã dùng phần lương (có hồ sơ nhân sự trong phần lương). |
| W-2 | Như W-1, thêm nhắc **nhân viên** có dòng giờ bị từ chối chưa sửa. Thêm một loại nhắc; nhân viên vốn đã thấy trạng thái "Từ chối" ở trang "Bảng giờ". |

## Điểm 2 — Khi nào

| Phương án | Nội dung |
|---|---|
| **T-1 (đề xuất)** | Chạy mỗi ngày lúc 8:00 (giờ Việt Nam). **(a)** chỉ gửi từ **3 ngày cuối tháng đến hết ngày 5 tháng sau**, và chỉ khi còn giờ chờ. **(b)/(c)** gửi **từ ngày 1 tháng sau, mỗi ngày cho tới khi chốt xong**. Không gửi trùng: cùng người, cùng loại, cùng tháng thì tối đa một thông báo một ngày (khoá theo `event_key`). Chạy lại lệnh trong ngày không tạo thêm. |
| T-2 | Chỉ nhắc 2 lần: ngày làm việc cuối tháng và ngày 3 tháng sau. Ít làm phiền hơn, nhưng dễ bị bỏ qua. |

## Điểm 3 — Kênh

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Thông báo trong ứng dụng (bảng `notifications`): hiện ở "Cập nhật chưa đọc" trên trang "Hôm nay", có link, ưu tiên "normal". Không cần cài thêm gì trên máy chủ. |
| C-2 | Như C-1, gửi thêm **email**. Cần máy chủ thật đã cài email. Thêm một mẫu email và hàng đợi. |

## Điểm 4 — Cách chạy

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | **Lệnh:** `php artisan reminders:month-end [--dry-run] [--date=YYYY-MM-DD]`. Chạy cho mọi công ty. `--dry-run` liệt kê ai sẽ nhận gì mà không ghi. **Lịch:** đăng ký trong `Console\Kernel` lúc 08:00 `Asia/Ho_Chi_Minh`, `withoutOverlapping`. Như các việc định kỳ khác, chỉ chạy khi `ENABLE_SCHEDULER=true`. **Runbook:** `docs/runbooks/go-live.md` thêm bước đặt `ENABLE_SCHEDULER=true` trong `.env` máy chủ (hiện runbook có cron nhưng chưa nhắc biến này) và cách chạy thử `--dry-run`. |
| R-2 | Không đặt lịch; Owner tự chạy lệnh khi muốn. |

## Thiết kế chi tiết (theo W-1, T-1, C-1, R-1)

### Dịch vụ `App\Services\Reminders\MonthEndReminderService`

**`plan(Carbon $today): list<Reminder>`** — chỉ đọc, dùng cho `--dry-run` và test.
- Duyệt từng công ty.
- Giờ chờ duyệt:
  - một truy vấn gom theo (người nhận, tháng): số dòng và ngày cũ nhất;
  - quy tắc người nhận như W-1.
- Lương / chi phí chung:
  - tháng trước theo `payroll_periods` / `overhead_periods`;
  - chỉ cho công ty có hồ sơ nhân sự trong phần lương.
- Người có quyền: lọc người dùng còn hoạt động của công ty bằng `hasPermission`.

**`send(Carbon $today): int`** — ghi `Notification`:
- `tenant_id`, `user_id`;
- `type`: `month_end.time_review`, `month_end.payroll_close` hoặc `month_end.overhead_close`;
- `title`, `body`, `link_url`, `priority = normal`, `channel = inapp`;
- `event_key = "<type>:<tháng>:<ngày>"`.

Trước khi ghi, bỏ qua nếu đã có thông báo cùng `user_id` + `event_key`, nên chạy lại trong ngày là an toàn.

### Lệnh `App\Console\Commands\MonthEndRemindersCommand`
- Chữ ký: `reminders:month-end {--dry-run} {--date=}`.
- In bảng: công ty, người nhận, loại, nội dung.
- Trả mã 0.

### Lịch
`app/Console/Kernel.php`: `$schedule->command('reminders:month-end')->dailyAt('08:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();`

### Test (đỏ trước / xanh sau) — `tests/Feature/Reminders/MonthEndRemindersTest.php`

**Giờ chờ duyệt**
- PM nhận đúng số dòng và ngày cũ nhất.
- Giờ của chính PM → người có `payroll.close`.
- Giờ không thuộc công trình → `payroll.manage`.
- Ngoài khoảng ngày thì không gửi.
- Hết giờ chờ thì không gửi.

**Lương / chi phí chung**
- Tháng trước chưa chốt → nhắc.
- Đã chốt lương, chưa chốt chi phí chung → nhắc loại (c).
- Chốt cả hai → không nhắc.
- Công ty không dùng phần lương → không nhắc.

**Chung**
- Không trùng khi chạy hai lần cùng ngày; ngày hôm sau gửi lại nếu vẫn còn.
- Cách ly công ty.
- `--dry-run` không ghi gì.
- Link đúng route.
- Lịch có đăng ký lệnh lúc 08:00.

## Files (allowlist)

- `app/Services/Reminders/MonthEndReminderService.php` (mới)
- `app/Console/Commands/MonthEndRemindersCommand.php` (mới)
- `app/Console/Kernel.php`
- `tests/Feature/Reminders/MonthEndRemindersTest.php` (mới)
- `docs/runbooks/go-live.md`
- `docs/owner-decisions/GAP-120/*`, `docs/audits/2026-11-10-gap-120-month-end-reminders-evidence.md`,
  `docs/superpowers/plans/2026-11-10-gap-120-month-end-reminders.md`

File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Owner amendment 1 (2026-11-10, CI trên commit mã)

**Vấn đề:** CI "Feature Tests" đỏ. `tests/Feature/Console/ScheduleContractTest::test_approved_workloads_remain_scheduled`
giữ một danh sách cố định các việc chạy định kỳ đã được duyệt. Lịch `reminders:month-end` mới (R-1) chưa có trong danh sách
nên test báo lệch. Lỗi tái hiện được trên máy.

**Owner chọn (AskUserQuestion, 2026-11-10):** "Bổ sung allowlist, sửa test (Recommended)".

**Thay đổi:** thêm `'reminders:month-end'` vào danh sách việc định kỳ đã duyệt trong test đó. Không đổi gì khác.

**File thêm vào allowlist:** `tests/Feature/Console/ScheduleContractTest.php`.

## Ngoài phạm vi

- Email (C-2).
- Nhắc nhân viên (W-2).
- SMS / Zalo / push.
- Trang cài đặt thông báo.
- Đổi quy tắc duyệt / chốt.
- Bật bộ chạy định kỳ trên máy chủ.
- Deploy.

## Verification required at Gate 3

- Test mới: đỏ trước khi làm, xanh sau.
- Toàn bộ Unit + Feature + Integration và Feature/Api.
- Chạy thử `--dry-run` và chạy thật trên dữ liệu mẫu, kèm kết quả.
- PHPStan, php-cs-fixer, `ssot:lint`, deptrac, governance lint.
- CI trên đúng head.

## Rollback

Revert PR. Thông báo đã gửi vẫn còn trong bảng `notifications` (người dùng đánh dấu đã đọc như thường). Không migration.

## Decision Needed

Owner chọn: `APPROVE GAP-120 Gate 2 W-1 T-1 C-1 R-1` (hoặc phương án khác cho từng điểm) / Request more information /
Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy, bật bộ chạy định kỳ trên máy chủ.
