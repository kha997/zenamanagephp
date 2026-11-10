---
work_id: GAP-121
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-10-gap-121-month-close-mysql-concurrency-evidence.md
  plan: null
  branch: docs/GAP-121-month-close-mysql-concurrency
  pr: https://github.com/kha997/zenamanagephp/pull/411
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
  created_at: "2026-11-10T22:13:57+07:00"
  updated_at: "2026-11-10T22:13:57+07:00"
generated_by: agent
---

# GAP-121 — Real-MySQL concurrency tests for the month close: Gate 2 design

## Owner Summary

Anh/chị cần chọn **2 điểm**:

1. **Kiểm những tình huống nào** — đề xuất **S-1**: cả **4 tình huống**:
   - đang chốt lương thì có người thêm giờ vào tháng đó (kiểm cả hai thứ tự: chốt trước và thêm giờ trước);
   - hai người cùng chốt lương;
   - chốt chi phí chung đúng lúc mở lại lương;
   - hai người cùng chốt chi phí chung.
2. **Chạy trong CI thế nào** — đề xuất **J-1**: một job CI mới **"Month Close Concurrency (real MySQL)"**, làm y theo 4 job sẵn
   có. Job dựng MySQL thật, chạy test, và **báo đỏ nếu test bị bỏ qua** dù MySQL đang chạy.

**Nguyên tắc:**
- Chỉ thêm test, lệnh hỗ trợ test và job CI. **Không đổi quy tắc chốt.**
- Nếu test lộ ra lỗi thật, tôi dừng, báo và hỏi anh/chị trước khi sửa (ghi thành bổ sung).

**Phát hành:** một PR, một Gate 3.

## Điểm 1 — Tình huống

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | **(1a)** Chốt lương giữ khoá → tiến trình khác thêm giờ (nhập tay) vào tháng đó: phải chờ rồi bị từ chối "tháng đã chốt". Chi phí nhân công đã chia khớp đúng các dòng giờ đã duyệt. **(1b)** Thêm giờ giữ khoá trước → chốt lương: phải chờ rồi bị từ chối "còn giờ chờ duyệt", tháng vẫn mở. **(2)** Hai lần chốt lương cùng tháng: đúng một thành công, phân bổ nhân công chỉ ghi một lần. **(3)** Chốt chi phí chung và mở lại lương cùng tháng, cả hai thứ tự: đúng một thành công, không bao giờ "chi phí chung đã chốt + lương đang mở". **(4)** Hai lần chốt chi phí chung: đúng một thành công, một bộ phân bổ. |
| S-2 | Chỉ (1) và (3), hai tình huống dễ gây sai số nhất. Ít test hơn, chạy nhanh hơn. |

## Điểm 2 — CI

| Phương án | Nội dung |
|---|---|
| **J-1 (đề xuất)** | Job mới trong `.github/workflows/automated-testing.yml`, copy cấu trúc job "Treasury Transfer Concurrency (real MySQL)": dựng MySQL 8, `migrate:fresh`, chạy `scripts/ci/month-close-concurrency-mysql`. Script báo lỗi nếu test SKIP dù MySQL chạy được. |
| J-2 | Gộp vào job Treasury sẵn có. Đỡ một job nhưng trộn hai việc khác nhau, khi đỏ khó nhìn. |

## Thiết kế chi tiết (theo S-1, J-1)

### Lệnh hỗ trợ test (ẩn, chỉ dùng cho test)
Đặt trong `app/Console/Commands/Testing/`, theo mẫu `TreasuryConcurrencyTestTransfer`:
- mỗi lệnh mở transaction, gọi đúng service thật, giữ khoá thêm `--hold` giây rồi commit;
- in `OK …` hoặc `REFUSED <lý do>`.

Các lệnh:
- `payroll:concurrency-test-close {period_id} {actor_id} {--hold=0}` → `PayrollService::close`.
- `payroll:concurrency-test-reopen {period_id} {actor_id} {reason} {--hold=0}` → `PayrollService::reopen`.
- `overhead:concurrency-test-close {period_id} {actor_id} {--hold=0}` → `OverheadService::close`.
- `time:concurrency-test-add {user_id} {work_date} {minutes} {--hold=0}` → `TimeEntryService::addManual`.

### Test `tests/Feature/Concurrency/MonthCloseConcurrencyTest.php` (`#[Group('stress')]`)
- **Chuẩn bị dữ liệu** trên kết nối `mysql`: công ty, Owner (`payroll.manage` + `payroll.close`), nhân viên, nhân sự trong
  phần lương, tháng lương, dòng giờ đã duyệt, dự án.
- **Chạy race:** mỗi tình huống chạy 2 tiến trình `php artisan …`. Tiến trình A có `--hold`; B bắt đầu sau A một chút. Kiểm
  kết quả từng tiến trình và trạng thái cuối trong DB.
- **Bỏ qua (skip)** khi không có MySQL, như các test cùng loại. Script CI biến skip thành lỗi.
- **Dọn dữ liệu** MySQL sau mỗi test.

### CI
- `scripts/ci/month-close-concurrency-mysql` (mới, theo mẫu script Treasury).
- Job mới trong `automated-testing.yml`.

## Files (allowlist)

- `app/Console/Commands/Testing/PayrollConcurrencyTestClose.php` (mới)
- `app/Console/Commands/Testing/PayrollConcurrencyTestReopen.php` (mới)
- `app/Console/Commands/Testing/OverheadConcurrencyTestClose.php` (mới)
- `app/Console/Commands/Testing/TimeConcurrencyTestAdd.php` (mới)
- `tests/Feature/Concurrency/MonthCloseConcurrencyTest.php` (mới)
- `scripts/ci/month-close-concurrency-mysql` (mới)
- `.github/workflows/automated-testing.yml`
- `docs/owner-decisions/GAP-121/*`, `docs/audits/2026-11-10-gap-121-month-close-mysql-concurrency-evidence.md`,
  `docs/superpowers/plans/2026-11-10-gap-121-month-close-mysql-concurrency.md`

File nào khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung (amendment) trong hồ sơ này.

## Ngoài phạm vi

- Đổi quy tắc / khoá chốt tháng. Nếu test lộ lỗi, xử lý bằng bổ sung có Owner duyệt.
- Ngân quỹ.
- Deploy.

## Verification required at Gate 3

- Test chạy trên MySQL 8 thật trong máy: xanh. Thêm một bằng chứng: tạm bỏ một khoá (chỉ trên máy, không commit) thì test
  tương ứng đỏ, để chứng minh test bắt được lỗi.
- Test skip đúng cách khi không có MySQL.
- Toàn bộ Unit + Feature + Integration.
- `WorkflowReferencesExistTest`, ssot:lint, PHPStan, php-cs-fixer, governance lint.
- Job CI mới chạy xanh trên đúng head.

## Rollback

Revert PR. Chỉ test và CI.

## Decision Needed

Owner chọn: `APPROVE GAP-121 Gate 2 S-1 J-1` (hoặc phương án khác cho từng điểm) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
