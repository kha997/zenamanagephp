---
work_id: GAP-092
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-23-gap-092-smoke-server-evidence.md
  plan: docs/superpowers/plans/2026-10-23-gap-092-smoke-server.md
  branch: docs/GAP-092-smoke-server
  pr: https://github.com/kha997/zenamanagephp/pull/365
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-23T00:39:35+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-23, verbatim: 'APPROVE GAP-092 Gate 2 Option A'. Reviewed design head: d5c51f11bb6e6d4a116993e89a718f3054729bec. Approves Option A and its exact allowlist (scripts/smoke_staging.sh: SMOKE_SERVER artisan|fpm with start_fpm_server, nginx cleanup, report line; staging-smoke.yml: SMOKE_SERVER fpm, nginx install if missing, single script run without the 3-attempt loop, nginx log uploads; temporary 16-copy matrix proof reverted; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-23T00:38:06+07:00"
  updated_at: "2026-10-23T00:39:35+07:00"
generated_by: agent
---

# GAP-092 — staging-smoke server: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-23 against reviewed design head
`d5c51f11bb6e6d4a116993e89a718f3054729bec`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

**Phương án A (khuyến nghị):**
- Script smoke có thêm lựa chọn `SMOKE_SERVER=fpm`: chạy app bằng **nginx + php-fpm**, cùng cách GAP-090 đã làm cho Dusk.
- Workflow staging-smoke bật lựa chọn này. Khi chạy trên máy, script vẫn mặc định dùng `artisan serve` như cũ.
- **Bỏ việc chạy lại 3 lần.** Smoke chỉ chạy một lần, nên lỗi thật sẽ đỏ ngay.
- Các bước kiểm tra trong smoke (health, login, `/me`, projects) giữ nguyên.

**Phương án B:** như A nhưng **giữ chạy lại 3 lần** làm lưới an toàn. Đổi lại, lỗi chập chờn sẽ bị che như hiện nay.

**Phương án C:** giữ `artisan serve`, chỉ thêm lần chạy lại. Ít thay đổi nhất, nhưng không sửa nguyên nhân.

**Cách chứng minh (A):**
- Một commit tạm chạy job staging-smoke thành **16 bản song song**, mỗi bản chỉ chạy một lần. Cả 16 bản phải xanh.
  Với tỉ lệ hỏng hiện nay khoảng 27% mỗi lần, khả năng 16/16 xanh do may mắn chỉ khoảng 0,6%. Commit tạm sau đó được hoàn tác.
- Chạy script với php-fpm ngay trên máy. Trong lúc chạy, cố ý làm crash các tiến trình php-fpm. Server phải vẫn trả lời
  và smoke vẫn phải xanh.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`scripts/smoke_staging.sh`**:
   - New `SMOKE_SERVER` variable: `artisan` (default, the current behaviour) or `fpm`. Any other value is a bootstrap
     failure.
   - New `start_fpm_server`, used by `start_server` when `SMOKE_SERVER=fpm`:
     - it writes `/tmp/smoke-fpm.conf`: one pool on `/tmp/smoke-fpm.sock`, `pm = static`, `pm.max_children = 4`,
       `clear_env = no`, `env[APP_ENV] = ${SMOKE_ENV_NAME}`, and errors to `${SERVER_LOG}`;
     - it writes `/tmp/smoke-nginx.conf`: listen `${APP_HOST}:${APP_PORT}`, root `public/`,
       `try_files $uri /index.php?$query_string`, and FastCGI to that socket. Pid, temp paths and logs are under `/tmp`,
       with `daemon off`;
     - it starts `php-fpm -F` (php-fpm8.2 if present) and nginx in the background. `PHP_PID` is the php-fpm master, so
       the existing process, listener and readiness checks keep working unchanged. `NGINX_PID` is new;
     - `cleanup` also stops nginx.
   - `SMOKE_SERVER` and the server type are written to the smoke report.
   - Nothing else changes: env file, database, seeding, routes, the checks and the report.
2. **`.github/workflows/staging-smoke.yml`**:
   - sets `SMOKE_SERVER: fpm`;
   - installs nginx with `apt-get` only if it is missing;
   - "Run staging smoke" runs the script **once**. The 3-attempt loop and its runner-image comment are removed;
   - also uploads `/tmp/smoke-nginx-error.log` and `/tmp/smoke-nginx-access.log`.
3. **Docs**: plan, this packet, 03-release.

**Option B:** identical, except item 2 keeps the 3-attempt loop.

**Option C:** no script change; the retry stays as it is today.

**Not changed:** application code, the smoke checks themselves, other workflows, and `SMOKE_SERVER=artisan` behaviour for
local runs.

## Verification

- **Local:** with nginx and php-fpm installed, `SMOKE_SERVER=fpm bash scripts/smoke_staging.sh` passes. A second run
  kills every php-fpm worker with SIGSEGV while the checks run. The port must keep answering and the run must pass.
  `SMOKE_SERVER=artisan` (the default) still passes.
- **CI proof:** a temporary commit runs `staging-smoke` as a 16-copy matrix (`fail-fast: false`, unique artifact names),
  one attempt each. All 16 must pass. The run is linked in Gate 3; the commit is reverted, and the final tree must equal
  the implementation commit.
- `bash -n` and actionlint report no new findings. Exact-head CI is green; governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Other jobs that use `artisan serve` or `php -S`.
- Changing what the smoke test checks.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-092 Gate 2 Option A` (khuyến nghị) / Option B / Option C /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
