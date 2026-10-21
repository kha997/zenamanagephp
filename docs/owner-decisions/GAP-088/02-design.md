---
work_id: GAP-088
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-21-gap-088-pecl-retry-evidence.md
  plan: docs/superpowers/plans/2026-10-21-gap-088-pecl-retry.md
  branch: docs/GAP-088-pecl-retry
  pr: https://github.com/kha997/zenamanagephp/pull/361
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T16:34:27+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-088 Gate 2 Option A'. Reviewed design head: c62d2eca7147447f32338f586eb7cd9dcdacb5d9. Approves Option A and its exact allowlist (Dockerfile.prod, Dockerfile.websocket, Dockerfile: pecl install redis replaced by a 5-attempt retry loop around pecl install redis-6.3.0 with 15/30/45/60 s waits, rest of each RUN unchanged; temporary proof workflow and temporary red proof, both reverted; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T16:33:08+07:00"
  updated_at: "2026-10-21T16:34:27+07:00"
generated_by: agent
---

# GAP-088 — Docker builds survive flaky pecl.php.net: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-21 against reviewed design head
`c62d2eca7147447f32338f586eb7cd9dcdacb5d9`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

**Phương án A (khuyến nghị):** sửa cả 3 Dockerfile (`Dockerfile.prod`,
`Dockerfile.websocket`, `Dockerfile`).
- **Ghim phiên bản:** cài `redis-6.3.0`, đúng bản main đang build hôm nay.
  Mọi lần build đều ra cùng một phiên bản.
- **Thử lại:** nếu PECL lỗi thì đợi rồi thử lại, tối đa **5 lần**, lần sau đợi
  lâu hơn lần trước (15, 30, 45, 60 giây; tổng chờ tối đa 2,5 phút). Hết 5 lần
  mới báo lỗi.
- Hai lỗi đã gặp đều tự hết khi chạy lại, nên cách này đủ để CI không còn đỏ
  ngẫu nhiên.

**Phương án B:** bỏ hẳn PECL. Tải mã nguồn phpredis 6.3.0 từ GitHub, kiểm tra mã
băm sha256, rồi tự build. Không còn phụ thuộc pecl.php.net, nhưng Dockerfile dài
hơn, và mỗi lần nâng phiên bản phải cập nhật mã băm.

**Kiểm chứng (cả hai):**
1. CI đã build `Dockerfile.prod` trong Docker Security Scan.
2. **Commit tạm** thêm workflow build `Dockerfile.websocket` và `Dockerfile` trên
   PR, vì không workflow nào của PR build hai file này. Kiểm xong thì hoàn tác.
3. **Commit tạm chứng minh đỏ:** đổi sang một phiên bản không tồn tại. Log phải cho
   thấy thử đủ 5 lần rồi mới đỏ. Kiểm xong thì hoàn tác.
4. Các image build ra phải nạp được extension `redis` (`php -m`).

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`Dockerfile.prod`** (line 76), **`Dockerfile.websocket`** (line 37) and **`Dockerfile`** (line 43):
   - Replace `pecl install redis` with a retry loop around `pecl install redis-6.3.0`.
   - The loop makes 5 attempts. Between attempts it waits 15, 30, 45 and 60 s and logs
     `pecl install redis-6.3.0 failed (attempt N/5)`.
   - After the fifth failure the step exits non-zero.
   - The rest of each `RUN` is unchanged: `docker-php-ext-enable redis`, cleanup, and in `Dockerfile.prod`
     `rm -rf /tmp/pear` and `apk del .build-deps`.
2. **Docs**: plan, this packet, 03-release.

**Not changed:**
- other extensions;
- base images;
- workflows;
- every other Dockerfile line.

## Verification

- Exact-head CI is green, including Docker Security Scan, which builds `Dockerfile.prod` and smoke-tests the image.
- Temporary proof on the PR, reverted afterwards:
  - a workflow on `pull_request` builds `Dockerfile.websocket` and `Dockerfile` with plain `docker build`;
  - it then runs `php -m` in each image and expects `redis` in the output.
- Temporary red proof, reverted afterwards: `Dockerfile.prod` asks for a version that does not exist.
  - Expected: Docker Security Scan is red.
  - Expected: the log shows attempts 1/5 to 5/5.
- Governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Other PECL or PHP extensions.
- Base images.
- Mirroring PECL.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-088 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.

## Owner amendment (2026-10-21T17:27:54+07:00)

Owner selection via AskUserQuestion, verbatim: "Chấp nhận + GAP riêng (Recommended)".

The temporary proof showed that two of the three Dockerfiles do not build on main, for reasons that predate
GAP-088:
- `Dockerfile.websocket`: the `sockets` extension fails (`linux/sock_diag.h` missing). Its `apk add` has
  neither `linux-headers` nor `$PHPIZE_DEPS`, and PECL needs the latter.
  - With both added in the CI workspace only, it built and `php -m` listed `redis`.
- `Dockerfile`:
  - line 80 copies `docker/php/php-fpm.conf`, which does not exist (the file is `docker/php-fpm.conf`);
  - with that worked around in the CI workspace, `docker-php-ext-install` (line 32) still fails, before the
    redis step.
  - The retry loop in `Dockerfile` therefore cannot be run. It is the same shell as in the other two files,
    and it was run locally under `dash` (Debian's `/bin/sh`) with a stub `pecl` that fails 0, 2, 4, 5 and
    9 times.

This amendment accepts that verification for `Dockerfile`, and records both pre-existing breakages for Gate 3.
Fixing them is left to a separate GAP (GAP-089). No file outside the allowlist changes.
