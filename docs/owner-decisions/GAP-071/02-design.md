---
work_id: GAP-071
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-08-gap-071-docker-image-vulnerabilities-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-071-docker-image-vulnerabilities.md
  branch: docs/GAP-071-docker-image-vulnerabilities
  pr: https://github.com/kha997/zenamanagephp/pull/344
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T17:43:42+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-08, verbatim: 'APPROVE GAP-071 Gate 2 Option A'. Reviewed design head: 0bb7bdd7aa473a07d0c2b731b9e4628cf2fbacec. Approves Option A and its exact allowlist (Dockerfile.prod production stage: apk upgrade, build deps in a removable virtual group, explicit runtime libs incl. icu-data-full, drop git/zip/unzip/redis server/imagemagick/linux-headers and PECL imagick; docker-security job of code-quality-security.yml: no-cache-filters production and a php -m / nginx -t smoke step); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T17:42:32+07:00"
  updated_at: "2026-10-08T17:43:42+07:00"
generated_by: agent
---

# GAP-071 — Docker image OS-package vulnerabilities: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-08 against reviewed design head
`0bb7bdd7aa473a07d0c2b731b9e4628cf2fbacec`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Ba phương án, đều chỉ sửa `Dockerfile.prod` (và một bước nhỏ trong job quét
CI):

- **A — cập nhật gói + gỡ gói thừa (khuyến nghị):** thêm `apk upgrade`; gói
  `-dev` chỉ dùng lúc build rồi gỡ; bỏ `linux-headers`, `git`, `zip`,
  `unzip`, Redis server, `imagemagick` + extension `imagick` (không thấy code
  dùng). Image nhỏ hơn, ít lỗ hổng nhất.
- **B — chỉ cập nhật gói:** thêm `apk upgrade`, giữ nguyên danh sách gói. Ít
  rủi ro thay đổi nhất nhưng chỉ xoá được lỗ hổng đã có bản vá; python (từ
  supervisor) và các gói thừa vẫn còn.
- **C — như A nhưng giữ `imagemagick`/`imagick`:** nếu Owner muốn giữ khả năng
  xử lý ảnh bằng Imagick cho tương lai.

Cả ba đều sửa job quét CI để build lại tầng cài gói mỗi lần (không lấy bộ nhớ
đệm cũ) và kiểm tra image chạy được (`php -m`, `nginx -t`).

Đề xuất **Phương án A**.

## Facts the design relies on

- `Dockerfile.prod` is built only by GitHub workflows
  (`code-quality-security.yml` scan, `automated-deployment.yml`,
  `release-management.yml`). `docker-compose*.yml` and `scripts/deploy.sh` use
  `Dockerfile`, not `Dockerfile.prod`.
- No workflow runs `composer`, `git`, `zip`/`unzip`, `redis-server` or
  ImageMagick inside the production image; `vendor/` is copied from the
  composer stage.
- `supervisord.conf` starts `php-fpm`, `nginx`, `queue:work`, `schedule:work`
  only. `supervisor` itself needs `python3` (Alpine package), so python stays.
- App code uses `mysqldump`/`mysql` (backup) → `mysql-client` stays; `curl`
  is used by `HEALTHCHECK` → stays; `ZipArchive` uses the PHP `zip`
  extension (libzip), not the `zip`/`unzip` CLI.
- No PHP code references `Imagick`.

## Options

| Option | Content | Verdict |
|---|---|---|
| **A. Upgrade + slim** | `apk upgrade`; build deps in a virtual group removed after building extensions; runtime libs kept explicitly; drop unused packages incl. ImageMagick | **Recommended** |
| B. Upgrade only | `apk upgrade`, package list unchanged | Leaves unused packages and their CVEs |
| C. Upgrade + slim, keep ImageMagick | As A, keep `imagemagick` + PECL `imagick` | Keeps an unused, CVE-heavy library |

## Design: Option A (exact allowlist)

1. `Dockerfile.prod`, production stage only (composer and node stages
   unchanged):
   - First step: `apk upgrade --no-cache`.
   - Runtime packages: `nginx supervisor curl mysql-client` plus the runtime
     libraries of the PHP extensions: `libpng libjpeg-turbo freetype libzip
     icu-libs icu-data-full oniguruma libxml2`.
   - Build-only packages in `apk add --virtual .build-deps`: `$PHPIZE_DEPS
     linux-headers oniguruma-dev libxml2-dev libpng-dev libjpeg-turbo-dev
     freetype-dev libzip-dev icu-dev`; build the same PHP extensions as today
     (`pdo_mysql mbstring xml zip intl gd opcache bcmath sockets`) and PECL
     `redis`; then `apk del .build-deps`.
   - Removed: `git zip unzip redis imagemagick linux-headers` and PECL
     `imagick`.
   - Everything else (users, permissions, config copies, healthcheck, CMD)
     unchanged.
2. `.github/workflows/code-quality-security.yml`, `docker-security` job only:
   - `no-cache-filters: production` on the build step, so the OS-package
     layer is rebuilt every run while the composer/node stages keep the cache.
   - A smoke step after the build:
     `docker run --rm zenamanage:test php -m` must list the nine extensions
     plus `redis`, and `docker run --rm zenamanage:test nginx -t` must pass.
3. Verification:
   - Red first: full Trivy list on main (32, captured from the PR run).
   - After: Trivy shows **0 vulnerabilities with an available fix**; any
     remaining unfixed ones are listed by package in Gate 3.
   - Smoke step green; all other CI green (exact head, incl. MySQL jobs and
     `browser-tests`).
4. Files: `Dockerfile.prod`, the `docker-security` job of
   `code-quality-security.yml`, governed plan file, this packet and
   03-release. No application code, config, migration or other workflow
   change.

## Risks

- A runtime library missing after removing `-dev` packages would break an
  extension → caught by the `php -m` smoke step.
- `icu-data-full` is kept so `intl` keeps all locales (vi).
- Python CVEs without an Alpine fix cannot be cleared without replacing
  `supervisor`; that is out of scope and would be reported in Gate 3.

## Rollback

Revert the squash commit (restores the previous Dockerfile and job).

## Out of scope

PHP or Alpine version change, replacing supervisor, `Dockerfile`,
`Dockerfile.websocket`, making the scan blocking (step 5), deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-071 Gate 2 Option A` (khuyến nghị) / Option B /
Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
