---
work_id: GAP-076
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-13-gap-076-prod-php-ini-evidence.md
  plan: docs/superpowers/plans/2026-10-13-gap-076-prod-php-ini.md
  branch: docs/GAP-076-prod-php-ini
  pr: https://github.com/kha997/zenamanagephp/pull/349
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T00:04:35+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T00:04:35+07:00"
  updated_at: "2026-10-14T00:04:35+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-076 at subject b76f19ca: docker/php/php.ini no longer disables proc_open/exec/curl_exec and the other listed functions, drops the 10 extension= lines that failed or duplicated the image's conf.d, the duplicate [opcache]/[security] sections, [imagick], the redis session handler, the unwritable error_log path and directives removed in PHP 8; docker/php/opcache.ini sets save_comments=1 and interned_strings_buffer=32 and drops the missing file_cache dir, unwritable error_log, huge_code_pages and fast_shutdown. CI docker-security gains a PHP runtime smoke (no startup warnings; proc_open, exec, curl_exec, curl_multi_exec, shell_exec, parse_ini_file callable; OPcache loaded with save_comments=1). Red first: the same smoke on php:8.2-fpm-alpine with the old ini files reported Unable to load openssl/curl/imagick and Invalid library opcache; green after. Exact-head CI green at b76f19ca including the production-image Docker Security Scan (php-fpm -t, nginx -t, runtime smoke, HTTP smoke, 0 vulnerabilities). Also applies to Dockerfile, Dockerfile.websocket and docker-compose, which share php.ini."
technical_evidence:
  base_sha: "e7358e0a9dd9cc35e00bfef1aa6dad1329c4d5ae"
  subject_sha: "b76f19ca685509b55b6168cf801e759ed262cd00"
  implementation_tree_digest: "9832053658d076eef96cce16a686c3443a2b3746454d5f7331195c2a68817506"
  verified_pr_head_sha: "b76f19ca685509b55b6168cf801e759ed262cd00"
  verified_at: "2026-10-14T00:04:35+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-076 — Gate 3 release decision (sửa php.ini của image production)

## Gói quyết định phát hành

**1. Vấn đề là gì?** `php.ini` chặn `proc_open`/`exec`/`curl_exec`… làm hỏng
tác vụ định kỳ, backup `mysqldump` và gọi HTTP ra ngoài; nạp sai 10 extension;
tắt docblock Swagger cần; trỏ cache/log/session vào chỗ không dùng được (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

- `php.ini`: bỏ `disable_functions`, bỏ các dòng `extension=`, mục `[opcache]`,
  `[security]`, `[imagick]`, session redis, đường dẫn log `/var/log`, các dòng
  trùng/lỗi thời.
- `opcache.ini`: `save_comments=1`, `interned_strings_buffer=32`; bỏ
  `file_cache`, log `/var/log`, `huge_code_pages`, `fast_shutdown`.
- CI: thêm bước kiểm tra PHP trong image production.

**3. Bằng chứng**

- Base `e7358e0a`; subject `b76f19ca685509b55b6168cf801e759ed262cd00`; digest `9832053658d076eef96cce16a686c3443a2b3746454d5f7331195c2a68817506`.
- **Đỏ trước:** bước kiểm tra mới chạy với file cũ báo cảnh báo khởi động
  (openssl, curl, imagick, opcache); với file mới thì sạch, các hàm cần thiết
  gọi được, opcache giữ docblock, `php-fpm -t` đạt.
- CI exact head `b76f19c` xanh, gồm Docker Security Scan trên image production
  thật (kiểm tra PHP mới, `php-fpm -t`, `nginx -t`, chạy thử HTTP, 0 lỗ hổng).
- Lint governance, docs đạt.

**4. Ảnh hưởng** — `Dockerfile`, `Dockerfile.websocket`, docker-compose dùng
chung `php.ini` nên cũng được sửa; môi trường dev không còn bị khoá cache
opcache (`validate_timestamps=0` chỉ còn ở image production).

**5. Ngoài phạm vi** — Code ứng dụng, nginx/supervisor, nâng gói, deploy.

**6. Rủi ro còn lại** — Thấp. Bỏ `disable_functions` đưa PHP về mặc định; bảo
mật dựa vào việc không cho chạy mã tuỳ ý, như các image Laravel thông thường.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-076 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
