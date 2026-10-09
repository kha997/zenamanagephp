---
work_id: GAP-074
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-08-gap-074-prod-image-nginx-evidence.md
  plan: docs/superpowers/plans/2026-10-09-gap-074-prod-image-nginx.md
  branch: docs/GAP-074-prod-image-nginx
  pr: https://github.com/kha997/zenamanagephp/pull/347
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-09T08:11:57+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-09, verbatim: 'APPROVE GAP-074 Gate 3'. Given after the packet (including the two disclosed startup fixes and the out-of-scope php.ini findings) was presented at PR head c6a573e828d361293190931da6089e4fedcf3ead (subject 2b49d094f79132654882ae5d179652359b6ac1fc with 34/34 exact-head checks green); bound to implementation subject 2b49d094f79132654882ae5d179652359b6ac1fc and implementation-tree digest 60a4802e68849bff9647a69fa827342c7e07ce286be3355d786f3a5bbfda1773 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-09T08:08:53+07:00"
  updated_at: "2026-10-09T08:11:57+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-074 at subject 2b49d094: Dockerfile.prod now installs a dedicated single-container nginx config (docker/nginx/nginx.single-container.conf: fastcgi to 127.0.0.1:9000, root /var/www/html/public, logs to stdout/stderr, pid under /var/run/nginx) and the HEALTHCHECK probes http://127.0.0.1/api/health. Red first: nginx -t fails on the image built from main (upstream php-fpm / proxy include of the compose config). The CI docker-security job gains nginx -t and a runtime smoke (container must answer 200 on /api/health and /robots.txt). Disclosed startup fixes under the Owner's 2026-10-09 Gate-2 amendment: create /var/log/supervisor (supervisord exited at 2cf38a6); run the php-fpm master as root under supervisord (php-fpm exit 78, error_log permission denied at d17e455); pools still drop to www-data. Exact-head PR checks 34/34 green incl. Docker Security Scan with runtime smoke. Out-of-scope php.ini defects (disable_functions blocks proc_open/exec/curl_exec, wrong extension lines, opcache as extension) are reported for a new Work ID."
technical_evidence:
  base_sha: "92f0a04405de62f62f8c54f3df28ee67148ac8e8"
  subject_sha: "2b49d094f79132654882ae5d179652359b6ac1fc"
  implementation_tree_digest: "60a4802e68849bff9647a69fa827342c7e07ce286be3355d786f3a5bbfda1773"
  verified_pr_head_sha: "2b49d094f79132654882ae5d179652359b6ac1fc"
  verified_at: "2026-10-09T08:07:50+07:00"
owner_decision_binding:
  implementation_tree_digest: "60a4802e68849bff9647a69fa827342c7e07ce286be3355d786f3a5bbfda1773"
  decision_recorded_at: "2026-10-09T08:11:57+07:00"
---

# GAP-074 — Gate 3 release decision (nginx.conf và healthcheck của image production)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-09 ("APPROVE GAP-074 Gate 3"), bound to implementation subject
`2b49d094f79132654882ae5d179652359b6ac1fc` and implementation-tree digest `60a4802e68849bff9647a69fa827342c7e07ce286be3355d786f3a5bbfda1773`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Image production (`Dockerfile.prod`) chép cấu hình nginx
của docker-compose (trỏ tới container `php-fpm` khác) nên `nginx -t` lỗi và
container không phục vụ được; healthcheck gọi sai địa chỉ (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án B):**

- File mới `docker/nginx/nginx.single-container.conf`: nginx và php-fpm cùng
  container (`fastcgi_pass 127.0.0.1:9000`), root `/var/www/html/public`, log ra
  stdout/stderr, pid ở `/var/run/nginx`. Cấu hình docker-compose không đổi.
- `Dockerfile.prod`: dùng file trên; tạo các thư mục nginx; `HEALTHCHECK` gọi
  `http://127.0.0.1/api/health`.
- CI (job Docker Security Scan): thêm `nginx -t` và bước chạy thử container —
  phải trả 200 cho `/api/health` và `/robots.txt`, in log nếu lỗi.

**3. Khác biệt so với Gate 2 (công khai)** — Bước chạy thử phát hiện 2 lỗi
khởi động; đã sửa theo quyết định bổ sung của Owner ngày 2026-10-09 ("Cho phép
sửa lỗi khởi động"), chỉ trong các file đã duyệt:

- supervisord thoát vì thiếu `/var/log/supervisor` (CI ở `2cf38a6`) → tạo thư
  mục trong `Dockerfile.prod` (`d17e455`).
- php-fpm thoát mã 78 "failed to open error_log … Permission denied" vì
  supervisord chạy tiến trình chính bằng `www-data` (CI ở `d17e455`) → bỏ
  `user=www-data` (và `user=nginx`) trong `supervisord.conf` (`2b49d09`). Tiến
  trình chính chạy root, các worker vẫn chạy bằng `www-data`/`nginx` như cấu
  hình pool/nginx — đây là cách chuẩn của php-fpm và nginx.

**4. Bằng chứng kỹ thuật**

- Base `92f0a044`; subject `2b49d094f79132654882ae5d179652359b6ac1fc`; digest
  `60a4802e68849bff9647a69fa827342c7e07ce286be3355d786f3a5bbfda1773`.
- **Đỏ trước:** `nginx -t` lỗi trên image build từ main; 2 lần chạy thử đỏ nêu
  ở mục 3.
- CI exact head `2b49d09`: 34/34 pass, gồm Docker Security Scan: `nginx -t`
  đạt, container trả 200 cho `/api/health` và `/robots.txt`, quét lỗ hổng 0.
- Lint SSOT, governance, docs đạt.

**5. Phát hiện ngoài phạm vi (đề xuất Work ID mới)** — `docker/php/php.ini`
có lỗi chưa sửa: `disable_functions` chặn `proc_open`/`exec`/`curl_exec`… (làm
hỏng `schedule:work`, backup `mysqldump`, Guzzle/curl); các dòng
`extension=openssl/curl/imagick` không nạp được; `opcache` khai báo bằng
`extension` thay vì `zend_extension`; cảnh báo bộ nhớ opcache và thư mục
`/tmp/opcache` không tồn tại. Image vẫn phục vụ HTTP được, nhưng các chức năng
trên sẽ lỗi khi chạy thật.

**6. Ảnh hưởng** — Chỉ image `Dockerfile.prod` (workflow deploy/release dùng
image này). docker-compose và môi trường dev không đổi. Không deploy.

**7. Rủi ro còn lại** — Thấp: image hiện tại trên main vốn không khởi động
được; bản mới đã được CI chạy thử thật.

**8. Hoàn tác** — Revert squash commit.

**9. Đề xuất** — Duyệt phát hành tại subject/digest trên; sau đó mở Work ID
mới sửa `php.ini`.

## Decision Needed

Owner chọn: `APPROVE GAP-074 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy; không duyệt việc sửa `php.ini` (Work ID riêng).
