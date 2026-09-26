---
work_id: GAP-054
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-054/02-design.md
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
  spec: docs/audits/2026-09-26-gap-054-scheduler-production-safety-evidence.md
  plan: docs/superpowers/plans/2026-09-26-gap-054-scheduler-production-safety-implementation.md
  branch: docs/GAP-054-scheduler-production-safety-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/318
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
  created_at: "2026-09-26T14:23:01+07:00"
  updated_at: "2026-09-26T14:23:01+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "The approved scheduler/backup safety design is implemented proof-first; every behavior test was observed failing on the unchanged code and passing after; per-task and whole-branch reviews closed with no open Critical/Important finding; all 33 exact-head PR checks passed, including PHPStan and every real-MySQL job."
technical_evidence:
  base_sha: "adacc5cc5fb8a08353cc90576076724e45e6e8bc"
  subject_sha: "cafa0a983ae1478e7d8b091141df3b55a5726fb4"
  implementation_tree_digest: "7108b2925e8c8300207afecb7994e2c300cd9d9778746670112c8a5c6c29cce7"
  verified_pr_head_sha: "cafa0a983ae1478e7d8b091141df3b55a5726fb4"
  verified_at: "2026-09-26T14:23:01+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-054 — Gate 3 release evidence

## Gói quyết định phát hành

**1. Vấn đề đã xảy ra là gì?** Khi bật bộ việc tự động (cấu hình Docker
production bật sẵn), hệ thống chạy 2 việc không tồn tại, xoá sạch bộ nhớ đệm
mỗi đêm (xoá luôn lớp chống dò mật khẩu), chép file bí mật vào mọi bản sao lưu,
để lộ mật khẩu cơ sở dữ liệu trên dòng lệnh, và chỉ giữ bản sao lưu ~2 ngày.

**2. Người dùng nào bị ảnh hưởng?** Chưa có người dùng thật bị ảnh hưởng (chưa
thấy máy chủ production nào đã bật). Rủi ro chạm tới mọi khách hàng, người đăng
nhập và chủ doanh nghiệp ngay khi bộ việc tự động được bật.

**3. Bây giờ hệ thống làm gì?** Lịch chỉ còn 6 việc chạy được. Không việc tự
động hay lệnh bảo trì chạy tay nào xoá toàn bộ bộ nhớ đệm (lệnh bảo trì bộ nhớ
đệm từ chối và giải thích). Bản sao lưu không bao giờ chứa file bí mật, kể cả
qua đường tắt (liên kết) trỏ tới file bí mật. Mật khẩu cơ sở dữ liệu không xuất
hiện trên dòng lệnh ở mọi chỗ sao lưu/khôi phục trong ứng dụng. Bản đầy đủ giữ
30 ngày, bản cơ sở dữ liệu giữ 7 ngày, đếm riêng; bản sao lưu cũ có sẵn không bị
đụng tới. Chưa cấu hình nơi lưu thì bản sao lưu nằm đúng chỗ như trước; có cấu
hình thì lưu đúng nơi đó, và không bao giờ chứa lồng bản sao lưu cũ.

**4. Rủi ro nào đã được đóng lại?** Lộ toàn bộ bí mật qua bản sao lưu; lộ mật
khẩu cơ sở dữ liệu trên máy chủ; kẻ dò mật khẩu được "làm lại từ đầu" mỗi đêm;
mất bản sao lưu cũ hơn 2 ngày; lỗi vô nghĩa trong nhật ký.

**5. Đã kiểm thử những gì?** Mỗi lỗi có một bài kiểm thử được chạy **trước khi
sửa và thấy thất bại**, sau khi sửa thì đạt. Toàn bộ 33 bước kiểm tra tự động
của thay đổi này đều đạt, kể cả các bài chạy trên cơ sở dữ liệu thật. Mỗi phần
việc được một người kiểm tra độc lập rà lại, và toàn bộ thay đổi được rà một
lần cuối; ba lỗi họ phát hiện đã được sửa và kiểm tra lại (xem mục kỹ thuật).

**6. Điều gì KHÔNG nằm trong phạm vi lần này?** Không bật bộ việc tự động ở
đâu, không đổi biến môi trường, lịch cron, cấu hình Docker hay quy trình
triển khai; không đổi mật khẩu; không xoá bản sao lưu cũ; không thêm "nhịp
tim", dọn nhật ký hệ thống hay chạy nhiều máy chủ.

**7. Vì sao các gap liên quan vẫn để riêng?** Trong lúc làm, đội kỹ thuật phát
hiện thêm (chi tiết ở mục "Phát hiện ngoài phạm vi"): nút "xoá bộ nhớ đệm" trong
trang quản trị vẫn xoá toàn bộ bộ nhớ đệm, và một số script vận hành ngoài ứng
dụng vẫn đưa mật khẩu lên dòng lệnh. Hai việc này nằm ngoài hợp đồng kỹ thuật
đã duyệt nên được đề xuất thành việc riêng, không sửa lén trong lần này.

**8. Rủi ro còn lại là gì?** Thấp. (a) Bản sao lưu cũ tạo trước thay đổi này có
thể chứa file bí mật và không tự bị xoá — hướng dẫn bật yêu cầu kiểm tra tay.
(b) Khi đã cấu hình nơi lưu khác mà nơi đó hỏng lâu ngày, bản sao lưu nằm lại
máy hiện tại và không tự dọn. (c) Một lần sao lưu cơ sở dữ liệu nay dừng sau 1
giờ nếu chưa xong (trước đây không giới hạn). (d) Chạy bằng Docker có thể chậm
hơn một chút vì không còn dựng lại cấu hình tối ưu hằng ngày.

**9. Có thể hoàn tác không?** Có. Thay đổi chỉ ở mã nguồn, cấu hình mặc định và
tài liệu; không có thay đổi dữ liệu hay cấu trúc cơ sở dữ liệu. Hoàn tác bằng
cách quay lại phiên bản trước.

**10. Đề xuất của đội kỹ thuật:** Phát hành. Sau khi phát hành, việc bật bộ
việc tự động ở production vẫn là quyết định vận hành riêng, làm theo hướng dẫn
mới trong runbook.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## Implemented change (base `adacc5cc`, subject `cafa0a98`)

| Commit | Change |
|---|---|
| `678ef3255` | Schedule: removed `session:gc`, `cache:optimize`, argument-less `queue:monitor` (Q1), `maintenance:run --task=cache`, daily `route:cache`/`view:cache`/`config:cache`. 6 approved workloads unchanged. |
| `fd79886ea` | `maintenance:run --task=cache` fails closed (exit 1, no mutation); `--task=all` no longer touches the cache. |
| `d4835273a` | New `App\Services\Backup\MysqlClient`: credentials only in a temporary `0600` option file (`--defaults-extra-file` first, removed in `finally`), argument arrays, `--result-file`, restore via input stream. Routed: `BackupCommand`, `MaintenanceCommand::createBackup()`, `Admin\MaintenanceController::backupDatabase()`, `DatabaseBackupService` (full, incremental, restore). Architecture guard test. |
| `7d3596fa6`, `83d80d1cb` | New `App\Services\Backup\BackupArchiveStore`: typed names `backup_{full,database,files,config}_<ts>.tar.gz`, per-type retention (Q3 30d/30 full, 7d/28 database), legacy untyped archives never touched, limits floored at 1 and the just-stored archive never pruned; `BACKUP_DISK` unset keeps `storage/backups` (Q4), set → configured disk; failed store exits 1 and prunes nothing; launch checklist reads the configured location. |
| `45417526d`, `e91b28fa9` | Backups never collect `.env`/`.env.*`; file collection excludes backup staging/storage paths (no nested backups) and never follows symlinks. |
| `e81770a9f` | Runbook "Enable the scheduler (optional, GAP-054)" section; accurate `.env.example` scheduler comment. |
| `4910cba2c` | Final-review hardening: newline/CR escaping in option files, fail-closed launch-checklist backup check, exact escaped-password and restore-input test evidence. |
| `cafa0a983` | `tests/Unit/BackupConfigTest` aligned with the approved Q4 default (`disk` null) — pre-existing test the plan missed; caught by CI. |

## Proof-first verification (RED on unchanged code → GREEN)

- **Schedule contract** (`tests/Feature/Console/ScheduleContractTest.php`): RED 4 failures (`scheduled command 'session:gc' is not registered`, forbidden cache entries, `queue:monitor`, exact list) → GREEN 5/5. Runtime: `ENABLE_SCHEDULER=true php artisan schedule:list` lists exactly the 6 approved entries.
- **Cache fail-closed** (`CacheMaintenanceFailClosedTest`): RED exit 0 and probe key flushed → GREEN; rate-limiter attempts preserved. Runtime: `maintenance:run --task=cache` → exit 1 with explanation.
- **Credentials** (`MysqlClientTest`, `NoCommandLineDatabasePasswordTest`): RED architecture test listed the offending files, client class absent → GREEN; hostile password `p@ss w0rd;$(rm -rf /)"'`&` never in argv, option file mode `0600` during execution and absent after success and failure, exact escaped line asserted; newline password cannot inject options.
- **Retention/storage** (`BackupRetentionTest`, `BackupStorageLocationTest`): RED 8/9 → GREEN, plus floor/keep-new tests (max_backups=0 RED deterministic; max_age_days=0 RED was timing-dependent — covered by the keep-new guard).
- **Contents** (`BackupContentsSafetyTest`): RED `.env` entry and nested probe archive present; symlink-to-secret RED (link entry archived) → GREEN.
- **Launch checklist** (`LaunchChecklistServiceBackupTest`): unknown `BACKUP_DISK` RED exception → GREEN returns false.
- Exact-head CI at `cafa0a983`: 33/33 checks passed (PHPStan/Code Quality, Unit, Feature, API, Integration, Security, browser, staging-smoke, RBAC/tenant invariants incl. MySQL parity, all real-MySQL concurrency jobs, Owner Governance Lint).

## Review record

Seven per-task reviews and one whole-branch review (most capable model). Three
real defects were caught and fixed with RED tests: retention could delete every
archive of a type (including the new one) on an empty/zero limit; a symlink to
`.env` inside `storage/app` leaked the secret into the archive; the readiness
check could throw on an unreachable backup disk.

## Scope notes and disclosures

- **Two extra command-line-password sites fixed** under the approved rule
  "no database password on the command line": `MaintenanceCommand::createBackup()`
  and `Admin\MaintenanceController::backupDatabase()` (not listed in Gate 1).
- **Behavior changes to note:** dumps now time out after 3600 s (previously
  unlimited `exec`; `DatabaseBackupService` previously used Laravel's 60 s
  default); symlinks under `storage/app` / `public/uploads` are no longer
  backed up; retention cannot be configured below 1; the incremental dump now
  uses mysqldump's required `database table…` order; `config/backup.php` keeps
  the now-unread legacy `max_backups`/`max_age_days` keys.
- **Guard limits (not overclaimed):** the architecture test scans `app/` files
  mentioning mysql for `--password=` / `-p$`-style forms; it does not prove the
  absence of every conceivable form.
- **Pre-existing, unchanged:** a failed backup run can leave an uncompressed
  `backup_<type>_<ts>/` directory that the default-location readiness check
  still counts as recent.

## Phát hiện ngoài phạm vi (đề xuất việc riêng)

1. `app/Http/Controllers/Admin/MaintenanceController.php` `clearCache()` vẫn
   gọi `Cache::flush()` khi quản trị viên bấm nút — cùng tác hại như việc bảo
   trì đã sửa, nhưng là thao tác web chạy tay nằm ngoài hợp đồng kỹ thuật.
2. `scripts/backup-system.sh:115`, `scripts/dr-automation.sh:108,159,193`,
   `scripts/deploy-production.sh:86`, `scripts/backup-database.sh:46`,
   `scripts/setup-production.sh:358`, `scripts/deploy.sh:75` truyền
   `-p"$DB_PASSWORD"` trên dòng lệnh (ngoài `app/`, loại trừ theo Gate 2).

## Rollback

Revert the PR's squash commit. No migration, data change, or environment
change is involved; `BACKUP_*` environment keys are optional and default to the
pre-change location.

## What the owner is NOT being asked to decide

Owner không được yêu cầu đọc nhật ký CI, mã nguồn hay bình luận review; không
quyết định bật bộ việc tự động ở production, đổi mật khẩu hay mở các việc riêng
ở mục "Phát hiện ngoài phạm vi". Chỉ quyết định: hành vi đã chứng minh và rủi ro
còn lại có chấp nhận được để phát hành hay không (Phát hành / Yêu cầu chỉnh sửa /
Hoãn).
