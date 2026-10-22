---
work_id: GAP-089
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
  spec: docs/audits/2026-10-21-gap-089-dockerfiles-build-evidence.md
  plan: docs/superpowers/plans/2026-10-21-gap-089-dockerfiles-build.md
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
  created_at: "2026-10-21T21:47:09+07:00"
  updated_at: "2026-10-22T08:12:46+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTED after the Owner amendment of 2026-10-21T22:45:52+07:00: the Owner approval of 2026-10-21 (verbatim 'APPROVE GAP-089 Gate 3', bound to subject b1b280ce4538f634931fbbbbc6f9625d5e443b44 / digest c933ac9e957ee53fd9e25eebcdcd6b26448c8b536422e82b894e9b8f267d83b1) is superseded because 834ff305 changed the tree in answer to a Codex P2 finding. GAP-089 at subject 834ff305. 1813043a (Gate 2 Option A): Dockerfile installs libicu-dev, runs 'composer install --no-dev --no-scripts --no-autoloader' before COPY . . and 'composer dump-autoload --optimize --no-dev' after it, and copies docker/php-fpm.conf (the path that exists); Dockerfile.websocket deleted, together with the 'Build and push WebSocket image' step and the matching '-websocket' line in the release notes of release-management.yml, and the websocket service of docker-compose.yml (the websocket server cannot start: Ratchet/cboden is not a dependency). New .github/workflows/docker-dev-image.yml builds Dockerfile and checks php -m lists redis and intl and php artisan --version runs. 834ff305 (Owner amendment 'Bỏ bộ lọc, chạy mọi PR (Recommended)'): the workflow's paths filter is removed, so it runs on every pull_request and every push to main, because the Dockerfile copies the whole repository and boots Artisan. Proof: temporary 6e138900 restored the wrong php-fpm.conf path and Build Dockerfile went red ('/docker/php/php-fpm.conf: not found'); b1b280ce reverted it. Static: actionlint shows no new findings; docker compose -f docker-compose.yml config succeeds (only the pre-existing 'version is obsolete' warning). Exact-head CI at 834ff305: 34/35 green, including Build Dockerfile (image built, redis and intl loaded, artisan boots); Owner Governance Lint red only because the packet was still bound to the superseded digest, which this re-presented packet resolves. Disclosed, out of scope: docker-compose.prod.yml builds a 'production' target that Dockerfile does not have and runs the non-existent websockets:serve; docker-compose.loadbalancer.yml needs Dockerfile.monitoring, which does not exist; docs that mention Dockerfile.websocket are historical records and are not edited."
technical_evidence:
  base_sha: "8e6523e2ea31b7d9139ede916af11d7eacefa17a"
  subject_sha: "834ff30503b8f733e10f73dd9df0d31150fcf0ae"
  implementation_tree_digest: "17deb4d10b5819634876bc26b8bdbc7c7ad25c920f56b1ed3c11259703f221ad"
  verified_pr_head_sha: "834ff30503b8f733e10f73dd9df0d31150fcf0ae"
  verified_at: "2026-10-22T08:12:46+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-089 — Gate 3 release decision (Dockerfile builds, Dockerfile.websocket retired)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 tại subject `b1b280ce`. Sau đó Codex review báo lỗi P2: bộ lọc
`paths` của workflow mới quá hẹp. Owner chọn "Bỏ bộ lọc, chạy mọi PR (Recommended)"
(Owner amendment). Vì sửa nên cây file đổi, và lần duyệt trên **không còn hiệu lực**.

**1. Vấn đề là gì?** `Dockerfile` và `Dockerfile.websocket` không build được trên main.
Server websocket cũng không thể chạy, vì thiếu thư viện Ratchet (Gate 1, Gate 2).

**2. Sau thay đổi (Gate 2 Phương án A và Owner amendment):**

| Commit | Thay đổi |
|---|---|
| `1813043a` | `Dockerfile` build được: thêm `libicu-dev`, chạy script composer sau khi copy mã, sửa đường dẫn `php-fpm.conf`. Bỏ `Dockerfile.websocket`, bước build image websocket và dòng ghi chú tương ứng trong mô tả release, cùng dịch vụ `websocket` trong `docker-compose.yml`. Thêm workflow **Docker Dev Image** |
| `6e138900` / `b1b280ce` | Commit tạm để thử đỏ, rồi hoàn tác |
| `834ff305` | Owner amendment: workflow Docker Dev Image chạy trên **mọi PR** và mọi lần push lên main, không còn bộ lọc `paths` (theo Codex) |

**3. Bằng chứng** — base `8e6523e2`; subject `834ff30503b8f733e10f73dd9df0d31150fcf0ae`;
digest `17deb4d10b5819634876bc26b8bdbc7c7ad25c920f56b1ed3c11259703f221ad`.

- Phép thử đỏ: đưa lại đường dẫn `php-fpm.conf` sai. Job **Build Dockerfile** đỏ đúng chỗ đó.
- CI chạy đúng trên commit `834ff305`: **34/35 xanh**. Trong đó Build Dockerfile build được
  image, image có `redis` và `intl`, và `php artisan --version` chạy được.
  Riêng Owner Governance Lint đỏ, vì gói Gate 3 lúc đó còn gắn digest cũ. Gói trình lại này
  xử lý lỗi đó.
- `actionlint` không có cảnh báo mới. `docker compose config` hợp lệ.

**4. Công khai (không sửa trong GAP này)**

- `docker-compose.prod.yml` build stage `production`, nhưng `Dockerfile` không có stage này.
  Dịch vụ websocket trong file đó cũng gọi lệnh không tồn tại. Production thật vẫn chạy bằng
  `Dockerfile.prod` qua workflow `production.yml`.
- `docker-compose.loadbalancer.yml` cần `Dockerfile.monitoring`, file này không tồn tại.
- Vài tài liệu cũ (audit, change proposal) còn nhắc `Dockerfile.websocket`. Đó là hồ sơ lịch sử
  nên không sửa.
- Ngoài bước build, mình bỏ thêm dòng `-websocket` trong mô tả release, vì image đó không còn
  được build.

**5. Ảnh hưởng**

- `docker-compose.yml` local build được (không còn dịch vụ websocket).
- Workflow release không còn hỏng ở bước build image websocket.
- **Mỗi PR** chạy thêm job build image, khoảng 4–5 phút, chạy song song với các job khác.

**6. Rủi ro còn lại** — Thấp:
- `docker-compose.prod.yml` và `docker-compose.loadbalancer.yml` vẫn hỏng (xem mục 4).
- Không còn image websocket. Nếu cần lại thì phải thêm Ratchet trước.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-089 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không sửa `docker-compose.prod.yml`,
`docker-compose.loadbalancer.yml` hay thêm Ratchet.
