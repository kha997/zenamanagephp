---
work_id: GAP-056
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-29-gap-056-script-mysql-password-evidence.md
  plan: null
  branch: docs/GAP-056-script-mysql-password-argv
  pr: https://github.com/kha997/zenamanagephp/pull/323
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-29T07:50:39+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-29: 'APPROVE GAP-056 Gate 2 Option 1'. Reviewed design head: 2e33741be5a26ac4e67cf9c3e5128078f124b74c. Approves Option 1 and its exact contracts/allowlist (shared 0600 option-file helper, in-container root option file, no fallback passwords, setup-production credential file + credential-free cron script, no env files in backups, shell-script architecture guard and helper tests); not Gate 3, merge, release, deployment, credential rotation or host cleanup."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T07:48:52+07:00"
  updated_at: "2026-09-29T07:50:39+07:00"
generated_by: agent
---

# GAP-056 — Shell scripts expose the MySQL password: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION 1

Owner approved Option 1 in-session on 2026-09-29 against reviewed design head
`2e33741be5a26ac4e67cf9c3e5128078f124b74c`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, deployment, credential rotation
or host cleanup.

## Owner Summary

Sửa tại chỗ cả 11 script (không xoá script nào, vì chưa biết đội vận hành có
đang dùng hay không): mật khẩu được đưa cho MySQL qua một file tạm chỉ chủ sở
hữu đọc được (cùng cách GAP-054 đã dùng trong ứng dụng), không bao giờ nằm trên
dòng lệnh; bỏ mật khẩu viết sẵn `root_password`; script cài đặt máy chủ không
còn ghi mật khẩu vào file ai cũng đọc được; bản sao lưu không còn chứa file bí
mật; và thêm kiểm thử tự động chặn lỗi này tái xuất hiện trong mọi file `.sh`.
Đề xuất **Phương án 1**.

## So sánh phương án

| Phương án | Cách làm | Kết luận |
|---|---|---|
| **1. Sửa tại chỗ, dùng option file 0600** | Helper dùng chung tạo file `[client]` quyền 0600 (bằng lệnh có sẵn của shell, không lộ qua dòng lệnh), gọi `--defaults-extra-file`, xoá khi kết thúc (`trap`) | **Đề xuất** — giống GAP-054 `MysqlClient`; không đổi hành vi vận hành |
| 2. Xoá các script cũ | Gỡ những script không nằm trên đường chính thức | Chưa đủ căn cứ: Owner chưa xác nhận script có được dùng không; xoá có thể phá quy trình thủ công. Có thể làm sau như việc riêng |
| 3. Biến môi trường `MYSQL_PWD` | Đặt `MYSQL_PWD` thay cho `-p` | Loại: MySQL 8.0 đã đánh dấu deprecated; lộ qua `/proc/<pid>/environ` |

## Thiết kế: Phương án 1

### Helper mới `scripts/lib/mysql-credentials.sh`

- `mysql_option_file <user> <password> [host] [port]` → tạo file bằng
  `mktemp`, `chmod 600` trước khi ghi, ghi bằng `printf` (builtin, không tạo
  process mang mật khẩu), in đường dẫn; người gọi đăng ký `trap` xoá.
- `mysql_container_client <container> <user> <password> <client> [args…]` →
  tạo option file như trên, `docker cp` vào container tới đường dẫn tạm, chạy
  `<client> --defaults-extra-file=<path> …` trong container, xoá file trong
  container và trên host (cả khi lỗi).
- `mysql_container_root_client <compose-args> <service> <client> [args…]` → cho
  hai chỗ dùng `root`: dựng option file **bên trong container** từ biến
  `MYSQL_ROOT_PASSWORD` sẵn có của container (`sh -c` + `printf`), nên mật khẩu
  root không đi qua host và không cần giá trị dự phòng.
- Hàm fail-closed: thiếu user/password → dừng với thông báo, không bao giờ dùng
  mật khẩu mặc định.

### Thay 31 chỗ

| File | Cách thay |
|---|---|
| `docker-manage.sh:160,207` | `mysql_container_root_client` (bỏ `root_password`) |
| `scripts/deploy.sh:75,268` | `mysql_container_root_client` (bỏ `root_password`) |
| `scripts/backup-database.sh:46` | `mysql_container_client` |
| `scripts/backup-system.sh`, `deploy-production.sh`, `dr-automation.sh`, `maintenance-database.sh`, `monitor-system.sh`, `performance-monitor.sh`, `setup-replication.sh` | option file host + `--defaults-extra-file` |
| `scripts/setup-production.sh:358` | xem dưới |

### `scripts/setup-production.sh::create_backup_script()`

- Ghi credential vào `/etc/zenamanage/backup.cnf` (root:root, 0600) bằng
  `printf` qua `sudo install -m 600`/`sudo tee` với umask 077.
- Script sinh ra `/usr/local/bin/zenamanage-backup` **không chứa mật khẩu**
  (heredoc có trích dẫn, không mở rộng biến bí mật), quyền 0700, dùng
  `mysqldump --defaults-extra-file=/etc/zenamanage/backup.cnf`.
- Thư mục sao lưu `chmod 700`; `tar` loại `.env` và `*.env`
  (`--exclude`).

### `docker-manage.sh::create_backup()`

- Bỏ `cp production.env`; giữ `cp` file compose (không chứa bí mật — compose
  prod dùng `${…}`). Không đổi đường tự động nào khác.

### Kiểm thử

1. Mở rộng `tests/Architecture/NoCommandLineDatabasePasswordTest.php`: thêm
   test quét mọi file `.sh` được git theo dõi (ngoài `vendor`, `node_modules`)
   bằng cùng biểu thức `-p$…`/`-p"$…"`/`--password=` trong ngữ cảnh
   `mysql`/`mysqldump`, và cấm chuỗi fallback `:-root_password`. Không miễn trừ
   file nào. Đỏ ở base (31 chỗ), xanh sau sửa.
2. Test mới `tests/Feature/GAP056/MysqlCredentialsHelperTest.php`: chạy helper
   thật với `mysqldump`/`docker` giả trên `PATH` ghi lại argv → argv không chứa
   mật khẩu; option file quyền 0600 lúc chạy và bị xoá sau đó (kể cả khi client
   lỗi); thiếu password → exit ≠ 0, không gọi client.
3. Test `setup-production.sh`: trích hàm `create_backup_script` chạy với
   `sudo`/`crontab` giả và thư mục tạm → script sinh ra không chứa mật khẩu,
   quyền 0700; option file 0600; `tar` có `--exclude=.env`.
4. `bash -n` cho cả 12 file `.sh` bị sửa/thêm.

## File allowlist

11 script ở trên, `scripts/lib/mysql-credentials.sh` (mới),
`tests/Architecture/NoCommandLineDatabasePasswordTest.php`,
`tests/Feature/GAP056/*` (mới),
`docs/superpowers/plans/2026-09-29-gap-056-script-mysql-password-implementation.md`
(có governance frontmatter), các Gate packet/evidence của GAP-056. Không sửa
`OPERATIONAL_GAP_REGISTER.md` (đối soát sau phát hành).

## Verification trước Gate 3

Test mới đỏ ở base, xanh ở subject; `bash -n` sạch; `tests/Unit/OwnerGovernance`
và governance lint xanh; `git grep` 31 mẫu cũ → 0; toàn bộ required CI xanh ở
exact head; digest canonical.

## Rollback

Revert squash commit. Host đã chạy bản cũ không tự được dọn (xem dưới).

## Explicit Exclusions

- **Không** đổi mật khẩu, vào máy chủ, xoá `/usr/local/bin/zenamanage-backup`
  hay `backups/*/production.env` đã tồn tại — việc vận hành; câu hỏi Gate 1
  (script có từng chạy trên máy thật?) vẫn mở.
- Không xoá script nào.
- Mật khẩu SMTP trên dòng lệnh (`configure-production-smtp.sh`) — việc riêng.
- Không deploy.

## Decision Needed

Owner chọn: Approve Phương án 1 / Approve Phương án 2 / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge, release, deployment, hay việc dọn máy chủ.
