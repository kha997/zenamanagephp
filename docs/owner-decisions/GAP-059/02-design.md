---
work_id: GAP-059
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-30-gap-059-smtp-password-evidence.md
  plan: null
  branch: docs/GAP-059-smtp-password-argv
  pr: https://github.com/kha997/zenamanagephp/pull/327
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T12:51:40+07:00"
  updated_at: "2026-09-30T12:51:40+07:00"
generated_by: agent
---

# GAP-059 — SMTP setup exposes and corrupts the mail password: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Chỉ còn **một** nơi ghi cấu hình email: lệnh `smtp:configure`, ghi giá trị có
dấu ngoặc và thoát ký tự đúng chuẩn nên mọi mật khẩu được ghi nguyên vẹn
(kiểm tra bằng chính bộ đọc `.env` của Laravel). Mật khẩu được đưa vào lệnh qua
đầu vào chuẩn (stdin), không bao giờ nằm trên dòng lệnh; lệnh từ chối
`--password=`. Script bỏ bước "kiểm tra" ghi đè, không còn dùng `sed` để ghi
bí mật, không để lại `.env.bak`, và bản sao lưu `.env` chỉ chủ sở hữu đọc được.
Đề xuất **Phương án 1**.

## So sánh phương án

| Phương án | Cách làm | Kết luận |
|---|---|---|
| **1. Một bộ ghi an toàn trong lệnh + mật khẩu qua stdin** | `smtp:configure` ghi MAIL_* có quote/escape, `--password-stdin`, từ chối `--password`; script gọi lệnh một lần | **Đề xuất** — sửa cả 4 lỗi, dùng được cho cả đường tương tác |
| 2. Mật khẩu qua biến môi trường | `SMTP_PASSWORD` env cho lệnh | Loại (cùng lý do GAP-056 loại `MYSQL_PWD`): lộ qua `/proc/<pid>/environ` cùng user |
| 3. Chỉ bỏ dòng 176 | Xoá bước "kiểm tra" | Không đủ: còn `sed` lộ + ghi sai, `preg_replace` ở đường tương tác, bản sao bí mật |

## Thiết kế: Phương án 1

### `app/Console/Commands/ConfigureSMTP.php`

- `updateEnvValue()`: ghi `KEY="…"` với `\\` → `\\\\`, `"` → `\\"`, `$` → `\\$`
  (định dạng double-quoted của vlucas/phpdotenv v5), thay dòng bằng
  `preg_replace_callback` (không còn back-reference). Hợp đồng kiểm chứng: giá
  trị đọc lại bằng `Dotenv\Parser\Parser` bằng đúng giá trị gốc.
- Ghi `.env` qua file tạm rồi rename, giữ nguyên quyền của `.env`.
- Đường dẫn: `app()->environmentFilePath()` thay `base_path('.env')` (cùng giá
  trị mặc định; cho phép test dùng thư mục tạm).
- Tuỳ chọn mới `--password-stdin`: đọc mật khẩu từ STDIN (một dòng, bỏ ký tự
  xuống dòng cuối).
- `--password=<giá trị>`: **từ chối** (exit ≠ 0, không ghi gì) kèm hướng dẫn
  dùng `--password-stdin` hoặc `--interactive`.
- Câu hỏi "test SMTP?" chỉ hỏi khi có TTY/không `--no-interaction` (hành vi
  chuẩn của `confirm()` với `--no-interaction` → mặc định không).

### `scripts/configure-production-smtp.sh`

- Backup `.env` tạo với `umask 077` (0600); giữ làm điểm khôi phục, in đường
  dẫn.
- 7 giá trị MAIL_* do người dùng nhập **chỉ** ghi bởi:
  `printf '%s\n' "$SMTP_PASSWORD" | php artisan smtp:configure --no-interaction --password-stdin --provider=… --host=… --port=… --username=… --encryption=… --from-address=… --from-name=…`
  (`printf` là builtin). Bỏ các lời gọi `update_env_var` cho MAIL_*; bỏ bước
  "Testing SMTP configuration" cũ (dòng 176).
- `update_env_var()` (còn dùng cho khoá queue/monitoring cố định và
  `MONITORING_ALERT_EMAIL`): viết lại không dùng `sed` — `awk` nhận giá trị qua
  `ENVIRON`, ghi file tạm 0600 rồi thay `.env` giữ quyền; cùng định dạng
  quote/escape; không tạo `.env.bak`.
- Không đụng `php artisan cache:clear` (ngoài phạm vi, đã ghi nhận).

### `scripts/run-comprehensive-tests.sh:194`

Chuyển sang `--password-stdin` (nếu không, lệnh mới từ chối `--password=`).

### Kiểm thử (TDD — đỏ trước)

1. `tests/Feature/GAP059/ConfigureSmtpWritesEnvSafelyTest.php` — `.env` tạm
   (`useEnvironmentPath`): với mật khẩu `ab/cd`, `ab&cd`, `pl"q`, `p$1w`,
   `a b#c`, `back\slash`, đọc lại bằng `Dotenv\Parser\Parser` ra đúng giá trị;
   các dòng khác giữ nguyên; quyền file giữ nguyên.
2. Cùng file: `--password=x` → exit ≠ 0 và `.env` không đổi;
   `--password-stdin` đọc được mật khẩu (luồng stdin tiêm vào lệnh).
3. `tests/Feature/GAP059/SmtpScriptEnvWriterTest.php` — trích `update_env_var`
   chạy bash thật trên `.env` tạm với các giá trị khó: đọc lại bằng Dotenv
   parser đúng; không còn `.env.bak`; giá trị không xuất hiện trong argv của
   tiến trình con (stub ghi argv).
4. Mở rộng `tests/Architecture/NoCommandLineDatabasePasswordTest.php` (hoặc test
   mới cùng thư mục): không file `.sh` git theo dõi nào truyền `--password=`
   cho `artisan`.

## File allowlist

`app/Console/Commands/ConfigureSMTP.php`,
`scripts/configure-production-smtp.sh`, `scripts/run-comprehensive-tests.sh`,
2 test mới ở trên, test kiến trúc (sửa hoặc thêm 1 file trong
`tests/Architecture/`),
`docs/superpowers/plans/2026-09-30-gap-059-smtp-password-implementation.md`
(governance frontmatter), Gate packet/evidence GAP-059. Nếu lint SSOT yêu cầu
khai báo (như GAP-058) sẽ ghi rõ ở Gate 3. Không sửa register.

## Verification trước Gate 3

Test mới đỏ ở base, xanh ở subject; `bash -n` 2 script; test email/SMTP hiện
có và `tests/Unit/OwnerGovernance`, `tests/Architecture` xanh; PHPStan (CI);
toàn bộ required CI xanh exact head; digest canonical.

## Rollback

Revert squash commit.

## Explicit Exclusions

Đổi mật khẩu SMTP thật; `cache:clear` trong script; xoá script; deploy.

## Decision Needed

Owner chọn: Approve Phương án 1 / Approve Phương án 3 / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge, release hay deployment.
