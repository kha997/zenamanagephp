---
work_id: GAP-089
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-21-gap-089-dockerfiles-build-evidence.md
  plan: null
  branch: docs/GAP-089-dockerfiles-build
  pr: https://github.com/kha997/zenamanagephp/pull/362
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
  created_at: "2026-10-21T19:37:36+07:00"
  updated_at: "2026-10-21T19:37:36+07:00"
generated_by: agent
---

# GAP-089 — Dockerfile and Dockerfile.websocket: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Khi soạn thiết kế, mình phát hiện thêm 2 điểm:

- **Server websocket không chạy được kể cả khi build xong.** Lệnh `websocket:serve`
  dùng thư viện **Ratchet** (`cboden/ratchet`), nhưng thư viện này không có trong
  `composer.lock`. Sửa Dockerfile thôi thì chưa đủ để websocket chạy.
- **`docker-compose.prod.yml` hỏng theo cách khác.** File này build `Dockerfile` với
  `target: production`, nhưng `Dockerfile` không có stage nào tên `production`. Dịch vụ
  websocket trong file này cũng gọi lệnh `websockets:serve` không tồn tại. Thực tế
  production chạy bằng `Dockerfile.prod` qua workflow `production.yml`, không qua file này.

**Phương án A (khuyến nghị):**
- **`Dockerfile`** (`docker-compose.yml` dùng cho local) **được sửa để build và chạy được:**
  - copy đúng `docker/php-fpm.conf`;
  - thêm `libicu-dev` cho extension `intl`;
  - chạy `composer install --no-scripts` trước khi copy mã nguồn, rồi chạy
    `composer dump-autoload --optimize` sau khi copy.
- **`Dockerfile.websocket` bị bỏ.** Xoá file này, xoá bước build image websocket trong
  `release-management.yml` (bước này đang làm release hỏng), và xoá dịch vụ `websocket`
  trong `docker-compose.yml`. Server websocket không thể chạy khi chưa có Ratchet;
  nếu sau này cần thì mở GAP riêng.
- **Thêm job CI build `Dockerfile` trên PR**, chỉ chạy khi PR đổi `Dockerfile`,
  `composer.json`/`composer.lock` hoặc `docker/**`. Job kiểm tra image có extension
  `redis`, và `php artisan --version` chạy được. Như vậy file này không hỏng lại mà
  không ai biết.
- `docker-compose.prod.yml` và `docker-compose.loadbalancer.yml` **không sửa** ở đây.
  Hai file này hỏng theo cách khác (stage `production` không có, `Dockerfile.monitoring`
  không có) và không workflow nào dùng để build. Gate 3 sẽ ghi rõ.

**Phương án B:** sửa cả hai Dockerfile cho build được: thêm gói cho websocket, sửa
lệnh và cổng. Server websocket vẫn hỏng khi chạy vì thiếu Ratchet. Không thêm job CI.

**Phương án C:** bỏ hẳn cả `Dockerfile` lẫn `Dockerfile.websocket`, cùng 3 file
`docker-compose*.yml`. Mất môi trường docker-compose local, và phải sửa thêm tài liệu cài đặt.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`Dockerfile`**:
   - `apt-get install` also installs `libicu-dev`.
   - `COPY composer.json composer.lock ./` is followed by
     `composer install --no-dev --no-scripts --no-autoloader --no-interaction`.
   - After `COPY . .`, add `RUN composer dump-autoload --optimize --no-dev`. This runs `post-autoload-dump`,
     including `package:discover`.
   - `COPY docker/php/php-fpm.conf ...` becomes `COPY docker/php-fpm.conf ...`.
   - Every other line is unchanged.
2. **`Dockerfile.websocket`**: deleted.
3. **`.github/workflows/release-management.yml`**: the "Build and push WebSocket image" step is removed.
4. **`docker-compose.yml`**: the `websocket` service is removed.
5. **New `.github/workflows/docker-dev-image.yml`**:
   - Trigger: `pull_request` and `push` to main, with `paths` filter `Dockerfile`, `composer.json`,
     `composer.lock`, `docker/**` and the workflow file itself.
   - Steps: `docker build -f Dockerfile`, then in the image:
     - `php -m` must list `redis` and `intl`;
     - `php artisan --version` must succeed.
6. **Docs**: plan, this packet, 03-release.

**Not changed:**
- `Dockerfile.prod`;
- `docker-compose.prod.yml` and `docker-compose.loadbalancer.yml`, which are disclosed;
- application code and `composer.json`.

## Verification

- The new workflow is green on the PR. Because this PR changes `Dockerfile`, it runs on the PR.
- Red proof: a temporary commit puts back the wrong `php-fpm.conf` path. The new job must turn red. The commit
  is reverted afterwards.
- actionlint reports no new findings; `docker compose -f docker-compose.yml config` succeeds.
- `release-management.yml` no longer references `Dockerfile.websocket`.
- No file in the repository references `Dockerfile.websocket` any more, except docs that are disclosed.
- Exact-head CI is green; governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Adding Ratchet or restoring a websocket server.
- `docker-compose.prod.yml` and `docker-compose.loadbalancer.yml`.
- `Dockerfile.prod`.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-089 Gate 2 Option A` (khuyến nghị) / Option B / Option C /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
