---
work_id: GAP-090
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-22-gap-090-dusk-flakes-evidence.md
  plan: docs/superpowers/plans/2026-10-22-gap-090-dusk-flakes.md
  branch: docs/GAP-090-dusk-flakes
  pr: https://github.com/kha997/zenamanagephp/pull/363
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-22T09:03:01+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-22, verbatim: 'APPROVE GAP-090 Gate 2 Option A'. Reviewed design head: 593b0f342f70d6a02dbce7c2969ff41ae02da7f5. Approves Option A and its exact allowlist (button-tests.yml browser-tests job: nginx + php-fpm replace php -S and its supervisor, healthcheck pid checks and diagnostics/artifacts follow the new logs, Dusk runs once without the retry loop; ProjectCreateTest cancel flow scrolls to the top before clicking @project-cancel; temporary 8-copy matrix and php-fpm SIGSEGV proof reverted; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-22T09:01:23+07:00"
  updated_at: "2026-10-22T09:03:01+07:00"
generated_by: agent
---

# GAP-090 — Dusk browser-tests flakes: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-22 against reviewed design head
`593b0f342f70d6a02dbce7c2969ff41ae02da7f5`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

**Server.** Hiện tại job dùng `php -S`, server dựng sẵn của PHP. Server này chỉ có một tiến trình và
bị crash khi Dusk gọi liên tục. Đề xuất thay bằng **nginx + php-fpm**, cùng bộ server mà production
dùng. `setup-php` đã cài sẵn `php8.2-fpm`. Với php-fpm, nếu một tiến trình con chết thì tiến trình chính
tự sinh lại, và cổng 8000 vẫn mở suốt. Không còn khoảng "server đang khởi động lại" khiến trình duyệt
bị từ chối kết nối.

**Nút Hủy.** Nút "Quay lại" (`@project-cancel`) nằm ngay dưới topbar dính đầu trang. Sau khi gõ vào
ô mô tả, trang có thể đã cuộn xuống. Khi Dusk click, trình duyệt chỉ cuộn vừa đủ để nút lộ ra ở mép
trên, đúng chỗ topbar che. Đề xuất cuộn trang về đầu (`window.scrollTo(0, 0)`) ngay trước khi click.
Chỉ sửa test, không sửa giao diện.

**Chứng minh.** Một commit tạm chạy job browser-tests thành **8 bản song song**, mỗi bản chỉ chạy
**một lần**, không chạy lại. Cả 8 phải xanh. Với tỉ lệ hỏng hiện tại 48%, xác suất 8/8 xanh do may mắn
chỉ khoảng 0,5%. Sau đó commit tạm được hoàn tác.

**Phương án A (khuyến nghị):** nginx + php-fpm, sửa click nút Hủy, **bỏ lần chạy lại**.
Một lỗi thật sẽ đỏ ngay. Job nhanh hơn, vì không bao giờ phải chạy thêm khoảng 18 phút.

**Phương án B:** như A nhưng **giữ lần chạy lại** làm lưới an toàn. An toàn hơn nếu vẫn còn lỗi chập
chờn hiếm gặp. Đổi lại, lỗi đó sẽ bị che như hiện nay.

**Phương án C:** giữ `php -S`. Chỉ sửa click nút Hủy, và thêm bước chờ server trước mỗi lần mở trang.
Ít thay đổi nhất, nhưng không xử lý được request đang chạy dở lúc server crash, nên lỗi
`ERR_CONNECTION_REFUSED` và hết giờ vẫn còn.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`.github/workflows/button-tests.yml`, job `browser-tests` only**:
   - **"Start Laravel server"** is replaced:
     - write `/tmp/dusk-fpm.conf`: one pool listening on `/tmp/dusk-fpm.sock`, `pm = static`,
       `pm.max_children = 4`, `clear_env = no`, `env[APP_ENV] = testing`, `catch_workers_output = yes`, errors to
       `/tmp/php-fpm.log`. The pool sets `php_admin_value` for `pcov.enabled=0`, `max_execution_time=120` and
       `pcre.jit=0`, the same ini values as the `php -S` command line today.
     - write `/tmp/dusk-nginx.conf`: listen `127.0.0.1:8000`, root `$GITHUB_WORKSPACE/public`,
       `try_files $uri /index.php?$query_string`, and `fastcgi_pass unix:/tmp/dusk-fpm.sock`. The pid, temp paths
       and logs are all under `/tmp`, so nginx runs as the runner user.
     - start `php-fpm8.2 --fpm-config /tmp/dusk-fpm.conf` and `nginx -c /tmp/dusk-nginx.conf -e /tmp/nginx-error.log`.
       nginx is installed with `apt-get` only if the runner image lacks it.
     - the `php -S` router, the supervisor loop and `/tmp/laravel-serve.pid` are removed.
   - **"Wait for server healthcheck"** keeps its curl loop and 5xx fail-fast. The early-exit check reads the
     php-fpm and nginx pid files instead of `/tmp/laravel-serve.pid`. Diagnostics print `/tmp/php-fpm.log` and
     `/tmp/nginx-error.log`.
   - **"Run Browser Tests"** runs `php artisan dusk tests/Browser/Projects/ tests/Browser/Crm/ --env=testing
     --without-tty --stop-on-failure` **once**. The 2-attempt loop is removed.
   - **"Prepare Dusk artifacts"** and **"Browser test diagnostics"** copy and print `/tmp/php-fpm.log`,
     `/tmp/nginx-error.log` and `/tmp/nginx-access.log` in place of `/tmp/laravel-serve.log`.
   - No other job or step changes.
2. **`tests/Browser/Projects/ProjectCreateTest.php`**, `test_project_create_cancel_flow` only:
   `->script('window.scrollTo(0, 0)')` before `->click('@project-cancel')`. A comment explains the sticky topbar.
3. **Docs**: plan, this packet, 03-release.

**Option B:** identical except item 1 keeps the 2-attempt loop in "Run Browser Tests".

**Option C:** item 1 keeps `php -S` and its supervisor; item 2 as above. A `DuskTestCase` helper waits until
`127.0.0.1:8000` accepts a connection before each `visit`.

**Not changed:**
- application code, views and CSS;
- other Dusk tests, `DuskTestCase` (Options A and B), and other jobs and workflows;
- `waitFor`/`waitForLocation` timeouts.

## Verification

- **Proof (temporary commit, reverted):** `browser-tests` gets `strategy.matrix.copy: [1..8]` with
  `fail-fast: false`. All 8 copies must pass in one attempt. The run is linked in Gate 3. The commit is then reverted,
  and the final tree must equal the implementation commit.
- **Server fault tolerance:** the proof commit also adds a step before Dusk that kills every php-fpm worker with
  `kill -SEGV`. Right after, port 8000 must still accept connections, and within a few seconds a request must
  return HTTP 200 again, because the master respawns the workers. This shows that the
  `ERR_CONNECTION_REFUSED` window is gone.
- actionlint reports no new findings. `php scripts/ci/lint-mysql-claim-truthfulness.php` passes (the job still
  sources `mysql-fail-closed.sh`).
- Exact-head CI green; governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Other Dusk suites that this job does not run (`tests/Browser/*.php`, `Buttons/`, `Pages/`).
- Changing the UI so the topbar never overlaps content (for example `scroll-padding-top`).
- Other workflows, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-090 Gate 2 Option A` (khuyến nghị) / Option B / Option C /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
