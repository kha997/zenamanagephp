---
work_id: GAP-076
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-13-gap-076-prod-php-ini-evidence.md
  plan: docs/superpowers/plans/2026-10-13-gap-076-prod-php-ini.md
  branch: docs/GAP-076-prod-php-ini
  pr: https://github.com/kha997/zenamanagephp/pull/349
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-13T22:50:05+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-13, verbatim: 'APPROVE GAP-076 Gate 2 Option A'. Reviewed design head: f6f6f93c3db565a4d7447eb1417bd97f17e4a083. Approves Option A and its exact allowlist (docker/php/php.ini: remove disable_functions and the [security] duplicate, extension= lines, [imagick], [opcache], redis session handler/path, error_log path, duplicated and obsolete directives; docker/php/opcache.ini: save_comments=1, interned_strings_buffer=32, drop file_cache, error_log, huge_code_pages, fast_shutdown; code-quality-security.yml docker-security smoke: PHP startup without warnings, proc_open/exec/curl_exec callable, OPcache with save_comments=1, php-fpm -t); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-13T22:49:23+07:00"
  updated_at: "2026-10-13T22:50:05+07:00"
generated_by: agent
---

# GAP-076 — Production php.ini: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-13 against reviewed design head
`f6f6f93c3db565a4d7447eb1417bd97f17e4a083`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Hai phương án cho `disable_functions` (các phần còn lại giống nhau):

- **A — bỏ hẳn `disable_functions` (khuyến nghị):** giống mặc định của PHP.
  Ngoài `proc_open`/`exec`/`curl_exec` mà app cần, thư viện cũng dùng các
  hàm khác trong danh sách: AWS SDK dùng `parse_ini_file` và `shell_exec`,
  laravel/prompts dùng `shell_exec`, ramsey/uuid dùng `passthru`. Giữ lại một
  phần danh sách sẽ tiếp tục gây lỗi khó đoán.
- **B — chỉ mở `proc_open`, `exec`, `curl_exec`, `curl_multi_exec`:** giữ chặn
  `passthru, shell_exec, system, popen, show_source, parse_ini_file`. Ít thay
  đổi hơn nhưng AWS SDK (đọc file cấu hình) và một số chức năng thư viện vẫn có
  thể hỏng.

Đề xuất **Phương án A**. Bảo mật thực sự nằm ở việc không cho chạy mã tuỳ ý,
không phải danh sách hàm bị chặn.

## Design (common to A and B)

Files: `docker/php/php.ini`, `docker/php/opcache.ini`,
`.github/workflows/code-quality-security.yml`, plan, this packet, 03-release.

1. `php.ini`
   - `disable_functions`: removed (A) or reduced as above (B); the duplicate
     `[security]` section is removed.
   - Remove all `extension=` lines (extensions are enabled by the image's
     `docker-php-ext-*` conf.d files; openssl/curl are built in; imagick is not
     installed) and the `[imagick]` section.
   - Remove the `[opcache]` section (opcache is configured only in
     `opcache.ini`).
   - Remove `session.save_handler = redis` / `session.save_path` (Laravel
     manages its own sessions; PHP default `files` applies if native sessions
     are ever used).
   - Remove `error_log = /var/log/php_errors.log` (not writable by the worker
     user) so errors go to the container log; remove duplicated
     `post_max_size`/`upload_max_filesize`/`max_file_uploads` lines and
     directives removed from PHP 8 (`track_errors`, `log_errors_max_len`).
2. `opcache.ini`
   - `opcache.save_comments=1` (l5-swagger reads `@OA` docblocks).
   - `opcache.interned_strings_buffer=32` (the GAP-074 smoke logged buffer
     overflow warnings at 16).
   - Remove `opcache.file_cache`/`file_cache_only` (directory never created),
     `opcache.error_log=/var/log/opcache.log` (not writable), `huge_code_pages`
     and the obsolete `fast_shutdown`.
   - Other values unchanged (memory 256, validate_timestamps=0, JIT).
3. CI `docker-security` smoke: run the built image and require
   - no "Warning"/"Unable to load" on PHP startup,
   - `proc_open`, `exec`, `curl_exec` callable (A) — or the four reopened
     functions (B),
   - `Zend OPcache` loaded with `opcache.save_comments=1`,
   - `php-fpm -t` passes; existing HTTP smoke stays.

## Impact

`Dockerfile`, `Dockerfile.websocket` and `docker-compose.yml` also use
`php.ini`; they get the same fixes (no opcache block there any more, so
development keeps PHP's default opcache behaviour).

## Verification

- Red first: the new CI assertions fail on the current files (local run on
  `php:8.2-fpm-alpine` already shows the disabled functions and 10 startup
  warnings).
- After: exact-head CI green including the extended Docker smoke; lints.

## Rollback

Revert the squash commit.

## Out of scope

Application code, nginx/supervisor, package upgrades, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-076 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
