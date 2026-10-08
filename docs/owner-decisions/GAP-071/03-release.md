---
work_id: GAP-071
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
  spec: docs/audits/2026-10-08-gap-071-docker-image-vulnerabilities-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-071-docker-image-vulnerabilities.md
  branch: docs/GAP-071-docker-image-vulnerabilities
  pr: https://github.com/kha997/zenamanagephp/pull/344
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T19:19:48+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T19:19:48+07:00"
  updated_at: "2026-10-08T19:19:48+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-071 at subject fb2965f8: Dockerfile.prod production stage runs apk upgrade, keeps runtime packages (nginx, supervisor, curl, mysql-client) and extension runtime libs (incl. icu-data-full), builds the same PHP extensions plus PECL redis with removable build deps, and drops git, zip, unzip, redis server, imagemagick, linux-headers and PECL imagick. The docker-security job rebuilds the production stage without layer cache and smoke-tests the image (php -m lists the ten extensions; php-fpm -t passes). Red first: Docker Security Scan 32 OS-package vulnerabilities on the unchanged Dockerfile; after: 0 (at 389cef7b and again at fb2965f8). Final image 71 Alpine packages (was 120). Exact-head PR checks 34/34 green. nginx -t dropped by Owner amendment (docker/nginx/nginx.conf already invalid on main)."
technical_evidence:
  base_sha: "e1bd5de0d13113d2572f951d61bb873a38a3d146"
  subject_sha: "fb2965f83fb3c5178719ae62d70ed101bf8bd4df"
  implementation_tree_digest: "695780041f3a1a2bce6ea77d4663159175db52b9dc2d467da7132917ef959fa9"
  verified_pr_head_sha: "fb2965f83fb3c5178719ae62d70ed101bf8bd4df"
  verified_at: "2026-10-08T19:19:48+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-071 — Gate 3 release decision (lỗ hổng gói hệ thống trong Docker image)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Image production (`Dockerfile.prod`) có 32 lỗ hổng trong gói
Alpine, phần lớn từ gói tự cài thêm và gói chỉ dùng lúc build (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A + sửa đổi của Owner):**

- `Dockerfile.prod`: `apk upgrade`; chỉ giữ gói cần khi chạy (`nginx`,
  `supervisor`, `curl`, `mysql-client`) và thư viện chạy của extension PHP
  (có `icu-data-full`); gói build (`-dev`, `linux-headers`, `$PHPIZE_DEPS`)
  được gỡ sau khi build extension; bỏ `git`, `zip`, `unzip`, Redis server,
  `imagemagick` và extension `imagick`. Các extension PHP giữ nguyên như cũ.
- Job "Docker Security Scan": build lại tầng gói hệ thống mỗi lần
  (`no-cache-filters: production`) và kiểm tra image: `php -m` đủ 10
  extension, `php-fpm -t` đạt.

**3. Khác biệt so với Gate 2 (công khai)**

- Bước kiểm tra `nginx -t` được Owner cho bỏ (sửa đổi ngày 2026-10-08): file
  `docker/nginx/nginx.conf` trên main đã lỗi sẵn (`upstream` nằm ngoài
  `http {}`), kiểm chứng bằng image nginx chuẩn. Lỗi này và `HEALTHCHECK`
  (gọi cổng php-fpm 9000 bằng HTTP) vẫn còn, để cho Work ID riêng.

**4. Bằng chứng kỹ thuật**

- Base `e1bd5de0`; subject `fb2965f83fb3c5178719ae62d70ed101bf8bd4df`; digest
  `695780041f3a1a2bce6ea77d4663159175db52b9dc2d467da7132917ef959fa9`.
- **Đỏ trước:** bot "Docker Security Scan" trên Dockerfile chưa sửa (head
  `5089552`): 32 lỗ hổng.
- **Sau:** 0 lỗ hổng (head `389cef7` và lại ở `fb2965f`). Image còn 71 gói
  Alpine (trước 120).
- Bước kiểm tra image ở `fb2965f`: không thiếu extension nào; `php-fpm -t`:
  "configuration file … test is successful".
- CI exact head `fb2965f`: 34/34 pass (gồm các job MySQL thật và
  `browser-tests`). Lint governance, docs, SSOT, baseline guard đạt; YAML hợp
  lệ.
- Giới hạn: phiên này không build được image (mạng chặn kho gói Alpine và nơi
  lưu artifact của GitHub), nên mọi bằng chứng build/quét là từ CI; danh sách
  chi tiết 32 mục ban đầu không xuất được (bot chỉ in 5 mục đầu).

**5. Ảnh hưởng triển khai** — `Dockerfile.prod` được dùng bởi
`automated-deployment.yml` và `release-management.yml`; lần build production
sau sẽ dùng image mới. `docker-compose` và `scripts/deploy.sh` dùng
`Dockerfile` khác, không đổi. Không deploy trong việc này.

**6. Ngoài phạm vi** — Sửa `nginx.conf`/`HEALTHCHECK`, đổi phiên bản PHP hay
Alpine, `Dockerfile`, `Dockerfile.websocket`, chặn CI theo kết quả quét (bước
5), deploy.

**7. Rủi ro còn lại** — Thấp. Nếu có công cụ nào chạy `git`, `zip`, `unzip`
hay ImageMagick bên trong image production thì sẽ thiếu; đã kiểm tra workflow
và code, không thấy chỗ dùng.

**8. Hoàn tác** — Revert squash commit.

**9. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-071 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
