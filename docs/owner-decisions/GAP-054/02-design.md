---
work_id: GAP-054
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-26-gap-054-scheduler-production-safety-evidence.md
  plan: null
  branch: docs/GAP-054-scheduler-production-safety-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/318
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-26T08:57:40+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-26: 'duyệt theo khuyến nghị' (approve per the recommendations). Approval is bound to exact reviewed Draft PR #318 head 6933cb0a2b4e84a96e6070c12390f909a7ad3157, with every Owner choice point resolved to its recommended option: Q1 include (remove argument-less queue:monitor), Q2 never back up secrets, Q3 full 30 days / database 7 days per-type retention, Q4 honor configured disk, off-host not mandatory. Authorizes a separate implementation plan and implementation within this design only; does not authorize Gate-3 approval, Ready state, merge, release, deployment, scheduler enablement, environment change, or credential rotation."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-26T08:54:10+07:00"
  updated_at: "2026-09-26T08:57:40+07:00"
generated_by: agent
---

# GAP-054 — Gate 2 scheduler & backup production-safety design

## Owner decision — APPROVED

Owner approved this exact Gate-2 design in-session on 2026-09-26 ("duyệt theo
khuyến nghị"), bound to reviewed Draft PR #318 head
`6933cb0a2b4e84a96e6070c12390f909a7ad3157`. Every Owner choice point is resolved to its recommended option:

- **Q1 — included:** the argument-less `queue:monitor` entry is removed from the
  schedule; no replacement monitor.
- **Q2 — never:** backups never contain `.env` / `.env.*`, encrypted or not.
- **Q3 — per-type retention:** full archives 30 days / 30 archives; database
  archives 7 days / 28 archives.
- **Q4 — configured disk honored, off-host not mandatory:** `BACKUP_DISK` unset
  keeps today's `storage/backups` location.

Implementation is authorized only within the design and proof-first contract
below. Gate-3 approval, Ready state, merge, release, deployment, scheduler
enablement, environment change, and credential rotation remain unauthorized.

## Owner Summary

Sửa bộ việc tự động để khi bật ở production nó không còn gây hại: bỏ các việc
hỏng, không xoá bộ nhớ đệm nữa, bản sao lưu không chứa bí mật và không lộ mật
khẩu, giữ bản sao lưu đúng số ngày Owner chọn và đúng nơi đã cấu hình. Thay đổi
này không tự bật bộ việc tự động ở đâu cả; việc bật vẫn là quyết định vận hành
riêng.

## Decision boundary and evidence binding

Design-only packet. Bound to the approved Gate-1 record
(`docs/owner-decisions/GAP-054/01-request.md`, Owner-approved 2026-09-26 at
PR #318 head `fb354b666b33cd5fe63fd699f623c1b54a7f9263`, recorded at
`87a897b2a`), the linked evidence audit, and canonical base
`adacc5cc5fb8a08353cc90576076724e45e6e8bc`. This approved packet
authorizes a separate implementation plan and implementation within its exact
boundary; it does not authorize Gate-3 approval, Ready state, merge, release,
deployment, environment change, or credential rotation.

## Owner choices embedded in this design

The design below uses the **recommended** option for each point. The Owner may
approve as-is or approve with a different choice for any point.

| # | Câu hỏi cho Owner | Khuyến nghị (dùng trong thiết kế) | Lựa chọn khác |
|---|---|---|---|
| Q1 | Có đưa thêm việc "theo dõi hàng đợi" vào phạm vi không? Kiểm tra ở Gate 2 cho thấy việc này cũng được lên lịch mỗi 5 phút và **luôn lỗi** vì thiếu thông tin cần theo dõi hàng đợi nào — cùng loại lỗi với 2 việc không tồn tại ở Gate 1. | **Có** — bỏ khỏi lịch, không thêm cơ chế theo dõi mới. | Không — để nguyên, xử lý ở một GAP khác. |
| Q2 | Bản sao lưu có được chứa file bí mật (đã mã hoá) không? | **Không bao giờ.** Bí mật được giữ riêng theo cách GAP-049 đã quy định (file bí mật trên máy chủ, quyền chỉ chủ sở hữu). Khi khôi phục, dùng bản bí mật giữ riêng. | Chứa nhưng mã hoá — cần quản lý thêm một khoá giải mã, phức tạp hơn. |
| Q3 | Giữ bản sao lưu bao lâu? | **Bản đầy đủ (hằng ngày): 30 ngày. Bản chỉ cơ sở dữ liệu (6 giờ/lần): 7 ngày.** Hai loại được đếm riêng, không đẩy nhau ra. | Số ngày khác do Owner chọn; đều chỉnh được qua cấu hình. |
| Q4 | Có bắt buộc lưu bản sao lưu ở máy khác trước khi bật ở production không? | **Không bắt buộc ở GAP này.** Hệ thống sẽ tôn trọng nơi lưu đã cấu hình (có thể là máy/dịch vụ khác); mặc định vẫn là máy hiện tại. Hướng dẫn bật ở production ghi rõ khuyến nghị lưu ở nơi khác. | Bắt buộc — phải chọn nhà cung cấp lưu trữ và cấp quyền truy cập trước, nằm ngoài GAP này. |

## Trước / Sau

**Trước (khi bật bộ việc tự động):**
1. Mỗi ngày có 2 việc không tồn tại thất bại; việc theo dõi hàng đợi thất bại
   mỗi 5 phút.
2. 2 giờ sáng: xoá sạch bộ nhớ đệm → bộ đếm chặn dò mật khẩu về 0, người đang
   đăng nhập qua tài khoản liên kết bị lỗi, khoá chống chạy trùng bị gỡ; cấu
   hình/đường dẫn đã tối ưu lúc triển khai bị xoá, rồi 10–12 giờ trưa mới dựng
   lại.
3. 1 giờ sáng: bản sao lưu đầy đủ chép file bí mật; mật khẩu cơ sở dữ liệu nằm
   trên dòng lệnh trong lúc sao lưu.
4. 5 bản sao lưu/ngày dùng chung giới hạn 10 bản → chỉ còn ~2 ngày; nơi lưu cấu
   hình bị bỏ qua.

**Sau:**
1. Lịch chỉ còn các việc chạy được. Việc không tồn tại và việc theo dõi hàng
   đợi hỏng (nếu Q1 = Có) bị bỏ khỏi lịch.
2. Không còn việc tự động nào xoá bộ nhớ đệm. Việc tối ưu cấu hình/đường dẫn/
   giao diện chỉ làm lúc triển khai (như GAP-049 đã làm), không lặp lại hằng
   ngày. Lệnh bảo trì bộ nhớ đệm chạy tay sẽ **từ chối** và giải thích lý do,
   không xoá gì.
3. Bản sao lưu không bao giờ chứa file bí mật. Mật khẩu cơ sở dữ liệu không
   xuất hiện trên dòng lệnh ở cả sao lưu lẫn khôi phục; mật khẩu có ký tự đặc
   biệt vẫn sao lưu được.
4. Bản đầy đủ và bản chỉ cơ sở dữ liệu được đặt tên và giữ riêng theo số ngày ở
   Q3. Bản sao lưu được lưu vào đúng nơi đã cấu hình; bước kiểm tra sẵn sàng
   phát hành đọc đúng nơi đó.

## Vai trò bị ảnh hưởng

- **Vận hành viên / người triển khai:** có hướng dẫn rõ ràng để bật bộ việc tự
  động ở cả hai cách triển khai; không còn bị lỗi vô nghĩa trong nhật ký; phải
  giữ bản bí mật riêng để khôi phục.
- **Chủ doanh nghiệp:** bản sao lưu giữ đúng số ngày đã chọn; bí mật không nằm
  trong bản sao lưu.
- **Người dùng cuối, khách hàng cổng, người được mời:** không thấy thay đổi giao
  diện; lớp chống dò mật khẩu không còn bị xoá mỗi đêm.

## Được phép / Không được phép

- Được phép: bật bộ việc tự động ở production sau khi thay đổi này được phát
  hành (vẫn là thao tác vận hành riêng, không nằm trong thay đổi này).
- Không được phép: bất kỳ việc tự động hay lệnh bảo trì nào xoá toàn bộ bộ nhớ
  đệm của ứng dụng; bất kỳ bản sao lưu nào chứa file bí mật; mật khẩu cơ sở dữ
  liệu trên dòng lệnh.
- Không đổi: ai được đăng nhập, ai được làm gì, dữ liệu khách hàng nào nhìn
  thấy.

## Trạng thái và bước tiếp theo

Không có trạng thái nghiệp vụ mới. Bộ việc tự động vẫn có hai trạng thái như
hiện tại: **tắt** (mặc định) và **bật** (vận hành viên chủ động bật). Sau thay
đổi này, trạng thái **bật** an toàn để dùng.

## Ngoại lệ

- Máy chủ đã từng chạy bản sao lưu cũ: các bản cũ (có thể chứa bí mật) **không
  bị tự động xoá hay sửa** bởi thay đổi này. Hướng dẫn bật sẽ ghi rõ: kiểm tra
  và xử lý thủ công các bản sao lưu cũ trong thư mục sao lưu.
- Nơi lưu cấu hình không ghi được (hết quyền, hết chỗ): sao lưu **báo thất
  bại** rõ ràng, không âm thầm bỏ qua, không xoá bản cũ.
- Mật khẩu cơ sở dữ liệu trống: sao lưu vẫn chạy như hiện nay.
- Triển khai bằng Docker: hiện chỉ có lịch tự động dựng lại cấu hình tối ưu
  hằng ngày. Sau thay đổi này việc đó không còn, nên hệ thống chạy Docker có
  thể chậm hơn một chút cho tới khi cách triển khai Docker tự làm bước này lúc
  khởi động (ngoài phạm vi, ghi nhận cho việc tiếp theo của GAP-049). Không ảnh
  hưởng tính đúng đắn.

## Hành vi người dùng nhìn thấy

Không có thay đổi trên màn hình. Thay đổi nằm ở nhật ký vận hành, nội dung bản
sao lưu, và thông báo của lệnh bảo trì chạy tay.

## Technical design contract

### Scheduler registration (`app/Console/Kernel.php`)

- Remove `session:gc` and `cache:optimize` entries (Defect 1).
- Q1 = include: remove the argument-less `queue:monitor` entry (it exits 1:
  `Not enough arguments (missing: "queues")`). No replacement monitor.
- Remove `maintenance:run --task=cache` and the daily `route:cache`,
  `view:cache`, `config:cache` entries. Compiled caches are owned by the
  deploy step (`.github/workflows/production.yml:232-234`). Known
  consequence: the Docker shape (`Dockerfile*`, `docker/`) has no
  build/start-time caching step, so under Docker the compiled config/route/
  view caches will no longer be rebuilt daily — a performance-only effect,
  recorded for the GAP-049 Docker follow-up, not fixed here.
- Unchanged: `maintenance:run --task=metrics|database|logs`,
  `backup:run --type=all`, `backup:run --type=database`, `queue:restart`,
  every `withoutOverlapping()` call, and the `enable_scheduler` gate.

### Cache maintenance (`app/Console/Commands/MaintenanceCommand.php`)

- `--task=cache` fails closed: performs **no** mutation (no `cache:clear`,
  `config:clear`, `route:clear`, `view:clear`, no `Cache::flush()`), prints an
  explanation that no maintenance-owned cache namespace exists, exits non-zero.
- `--task=all` no longer invokes cache maintenance; it runs the remaining tasks.
- No other task changes behavior.

### Backup contents and credentials

- `backupConfig()` never collects `.env` or `.env.*`. Remaining collected
  files (config PHP files, composer manifests) are unchanged.
- MySQL credentials for `mysqldump`/`mysql` are passed through a temporary
  client option file (`--defaults-extra-file`) created with mode `0600` and
  removed in `finally` on every path (`--defaults-extra-file` must be the
  first option, as the MySQL clients require). No credential appears in
  process arguments. All remaining arguments are passed without shell interpolation
  (argument array or `escapeshellarg`).
- Applies to `BackupCommand::backupDatabase()` and all
  `DatabaseBackupService` builders (full dump, incremental dump, restore).

### Retention and storage

- Archive names carry their type: `backup_full_<ts>.tar.gz` (from
  `--type=all`) and `backup_database_<ts>.tar.gz`, `backup_files_<ts>`,
  `backup_config_<ts>` for single-type runs. All still match the existing
  `backup_*` launch-checklist pattern.
- Retention is evaluated **per type**, with per-type count and age limits in
  `config/backup.php` (env-overridable). Defaults per Q3: full = 30 days /
  30 archives, database = 7 days / 28 archives; files/config fall back to the
  full-archive limits. Legacy untyped `backup_<ts>.tar.gz` archives are
  **neither counted nor deleted** by the new retention.
- Storage location: when `BACKUP_DISK` is **unset**, archives stay exactly
  where they are today (`storage/backups`) — no silent move. When
  `BACKUP_DISK` is set, the finished archive is written to that Laravel
  filesystem disk under `config('backup.path')`. The `config/backup.php`
  `disk` default therefore changes from `filesystems.default` to `null`
  ("legacy local directory"). The staging directory stays under `storage/`.
  A write failure makes the command exit non-zero and skips retention.
- The files collection (`backupFiles()` copies all of `storage/app`, and the
  `local` disk root is `storage/app` — `config/filesystems.php:52`) always
  excludes the backup staging directory and, when the configured backup disk
  is a local disk, the configured backup path — so a backup can never contain
  earlier backups.
- `LaunchChecklistService::getLatestBackupTimestamp()` reads the newest
  backup from the configured disk/path instead of a hard-coded
  `storage_path('backups')`.

### Deployment-shape documentation

- `docs/runbooks/gap-049-host-provisioning.md`: add an explicit, optional
  "Enable the scheduler" section (single scheduler host, Redis cache,
  `ENABLE_SCHEDULER=true`, per-minute `schedule:run` cron, recommended
  off-host backup disk, manual review of pre-existing legacy archives).
- `.env.example:34-36`: replace the comment with an accurate description (off
  by default; see runbook before enabling).
- `docker-compose.prod.yml` unchanged (it may keep `ENABLE_SCHEDULER=true`
  once the workloads are safe). No cron, supervisor, or workflow change.

## Alternatives considered

- **Keep scheduled cache maintenance but clear only "safe" keys** — rejected:
  no maintenance-owned cache namespace exists (evidence §Defect 2), so any
  targeted list would be guesswork.
- **Retire the application backup and rely on GAP-049 `scripts/deploy/backup.sh`**
  — rejected: that script is a database-only pre-deploy snapshot; only the
  application backup covers uploaded files.
- **Merge the July `fix/scheduler-production-safety` branch** — rejected: 109
  commits behind, never gated, and it adds heartbeat/log-retention scope
  excluded at Gate 1. Its credential-file approach is adopted as a pattern
  only.
- **Encrypt `.env` inside backups** — offered as Q2 alternative; not
  recommended (adds key management).

## Proof-first / TDD implementation contract

Each defect gets a test that is observed **failing on unchanged canonical main**
before the fix and passing after it:

1. Schedule contract: with `enable_scheduler=true`, every scheduled command
   name resolves to a registered command, and the schedule contains no
   `maintenance:run --task=cache`, `route:cache`, `view:cache`,
   `config:cache` (and, if Q1 = include, no argument-less `queue:monitor`).
2. Cache fail-closed: seed a rate-limiter hit and an arbitrary cache key; run
   `maintenance:run --task=cache` → non-zero exit, both keys still present;
   `--task=all` also leaves them present.
3. No secrets in backup: with a sentinel `.env`, run `backup:run --type=all`
   → extracted archive contains no `.env`/`.env.*`, and no file content
   contains the sentinel value.
4. No password in process arguments: capture the executed dump/restore
   command (process seam) with a password containing shell metacharacters →
   the password never appears in the argument list, the option file is mode
   `0600` during execution and absent afterwards (success and failure paths).
5. Retention: create typed archives spanning > 30 days plus legacy untyped
   archives → full archives within 30 days/30 count kept, database archives
   within 7 days/28 count kept, legacy archives untouched.
6. Storage: with `BACKUP_DISK` unset the archive lands in `storage/backups`
   as today; with a faked non-default disk and path it lands there; the
   launch-checklist backup check reads the configured location; a failing
   disk makes the command exit non-zero and deletes nothing.
7. No nested backups: with the backup path on the `local` disk and a prior
   archive present, a `--type=all` archive contains no earlier archive.

Existing callers to update in the same change: `tests/Feature/BackupCommandTest.php`,
`tests/Feature/QualityAssuranceTest.php:227`, `tests/Feature/FinalSystemTest.php:821`
(cache task now fails closed), `tests/Unit/LaunchChecklistServiceBackupTest.php`.

## Kịch bản chấp nhận

1. Cho bộ việc tự động đang bật, khi liệt kê lịch, thì mọi việc trong lịch đều
   là lệnh có thật, và không còn việc nào xoá bộ nhớ đệm hay dựng lại cấu hình
   hằng ngày.
2. Cho một người vừa bị chặn vì đăng nhập sai nhiều lần, khi lệnh bảo trì bộ
   nhớ đệm được chạy (tự động hoặc tay), thì người đó vẫn đang bị chặn và lệnh
   báo từ chối.
3. Cho file bí mật có một giá trị đánh dấu, khi chạy sao lưu đầy đủ, thì bản sao
   lưu không chứa file bí mật và không chứa giá trị đánh dấu ở bất kỳ file nào.
4. Cho mật khẩu cơ sở dữ liệu có ký tự đặc biệt, khi sao lưu hoặc khôi phục, thì
   mật khẩu không xuất hiện trên dòng lệnh, sao lưu thành công, và file tạm chứa
   mật khẩu bị xoá kể cả khi thất bại.
5. Cho bản sao lưu nhiều ngày, khi dọn bản cũ, thì bản đầy đủ giữ đủ 30 ngày,
   bản cơ sở dữ liệu giữ đủ 7 ngày (hoặc theo số Owner chọn), và bản cũ tạo
   trước thay đổi này không bị đụng tới.
6. Cho nơi lưu được cấu hình khác mặc định, khi sao lưu, thì bản sao lưu nằm ở
   đúng nơi đó và bước kiểm tra sẵn sàng phát hành nhận ra nó; nếu nơi lưu lỗi
   thì sao lưu báo thất bại và không xoá gì.
7. Cho chưa cấu hình nơi lưu, khi sao lưu, thì bản sao lưu vẫn nằm đúng chỗ như
   hiện nay; và không bản sao lưu nào chứa lồng bản sao lưu cũ bên trong.
8. Toàn bộ bộ kiểm thử tự động hiện có vẫn xanh (sau khi cập nhật các bài kiểm
   thử đang mong đợi hành vi xoá bộ nhớ đệm cũ).

## Loại trừ phạm vi

- Không bật/tắt bộ việc tự động trên máy chủ nào, không đổi biến môi trường
  production, cron, supervisor, cấu hình Docker hay quy trình deploy.
- Không đổi mật khẩu hay khoá; không tự xoá/sửa bản sao lưu cũ đã có.
- Không thêm cơ chế "nhịp tim" của bộ việc tự động, không thêm dọn nhật ký hệ
  thống, không đổi lịch tối ưu cơ sở dữ liệu, thu thập số liệu hay khởi động lại
  hàng đợi.
- Không xử lý chạy nhiều máy chủ cùng lúc; không chọn cách triển khai thay
  Owner; không mở lại quyết định GAP-049; không sửa lại tài liệu bằng chứng lịch
  sử của GAP-049.
- Không thêm nhà cung cấp lưu trữ ngoài hay thông tin truy cập nào.
- Không đưa nhánh làm dở tháng 7 vào main.

## Decision result

Approved for implementation under this exact design with Q1–Q4 resolved as
recommended. A future Gate-3 packet must present the completed evidence and
receive a separate Owner release decision before Ready state, merge, release,
or deployment.

## What the owner is NOT being asked to decide

Owner không được yêu cầu duyệt tên lớp, cách truyền mật khẩu, định dạng tên
file, hay cách viết bài kiểm thử — chỉ duyệt rằng các quy tắc trên (không xoá bộ
nhớ đệm, không có bí mật trong sao lưu, không lộ mật khẩu, thời hạn giữ Q3, nơi
lưu Q4, phạm vi Q1) đúng với điều doanh nghiệp muốn. Không duyệt merge, phát
hành hay bật bộ việc tự động ở production.
