---
work_id: GAP-074
gate: 2
gate_status: approved
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
  recorded_at: "2026-10-09T07:08:39+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-09, verbatim: 'APPROVE GAP-074 Gate 2 Option B'. Reviewed design head: f4b53b1d226e129ff3c36fe8c11cdd4c9451eb62. Approves Option B and its exact allowlist (new docker/nginx/nginx.single-container.conf; Dockerfile.prod nginx COPY, EXPOSE 80, HEALTHCHECK on /api/health; supervisord nginx program without user=nginx; docker-security smoke step with nginx -t and a runtime container check of /robots.txt and /api/health); stop and report if the runtime smoke exposes defects outside these files; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-09T07:07:15+07:00"
  updated_at: "2026-10-09T07:08:39+07:00"
generated_by: agent
---

# GAP-074 — Production image nginx and HEALTHCHECK: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION B

Owner approved Option B in-session on 2026-10-09 against reviewed design head
`f4b53b1d226e129ff3c36fe8c11cdd4c9451eb62`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Cả hai phương án đều: thêm file cấu hình nginx riêng cho image production (nghe
cổng 80, chuyển PHP tới php-fpm `127.0.0.1:9000`), cho supervisord chạy nginx
bằng root (tiến trình con vẫn là user `nginx`), `EXPOSE 80`, HEALTHCHECK gọi
`http://127.0.0.1/api/health`, và thêm `nginx -t` vào bước kiểm tra image
trong CI. File `docker/nginx/nginx.conf` của docker-compose giữ nguyên.

- **A — sửa cấu hình + kiểm tra tĩnh:** CI chỉ kiểm `nginx -t` trong image.
  Nhanh, ít rủi ro CI, nhưng chưa chứng minh image thực sự trả lời HTTP.
- **B — như A + chạy thử container (khuyến nghị):** CI khởi động container
  (biến môi trường tối thiểu: `APP_KEY` sinh tạm, SQLite), chờ HEALTHCHECK,
  rồi gọi `/robots.txt` (nginx) và `/api/health` (nginx → php-fpm → Laravel),
  cả hai phải trả 200. Chứng minh đầy đủ; nếu chạy thử lộ ra lỗi khởi động khác
  ngoài danh sách cho phép, mình dừng và báo lại.

Đề xuất **Phương án B**.

## Prototype (local, not committed)

The config below passes `nginx -t` on stock `nginx:alpine` and serves
`/robots.txt` with 200. (An IPv6 `listen [::]:80` line was dropped: it fails
where the runtime has no IPv6, which was the case here.)

```nginx
user nginx;
worker_processes auto;
pid /var/run/nginx/nginx.pid;
error_log /dev/stderr warn;
events { worker_connections 1024; }
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log /dev/stdout;
    sendfile on; keepalive_timeout 65; server_tokens off; client_max_body_size 20m;
    server {
        listen 80 default_server; server_name _;
        root /var/www/html/public; index index.php;
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        location / { try_files $uri $uri/ /index.php?$query_string; }
        location ~ \.php$ {
            try_files $uri =404;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params; fastcgi_read_timeout 300;
        }
        location ~ /\.(?!well-known) { deny all; }
    }
}
```

## Design: Option B (exact allowlist)

1. New `docker/nginx/nginx.single-container.conf` (the config above).
2. `Dockerfile.prod` (production stage): copy that file to
   `/etc/nginx/nginx.conf` instead of `docker/nginx/nginx.conf`; `EXPOSE 80`;
   `HEALTHCHECK ... CMD curl -fsS http://127.0.0.1/api/health || exit 1`.
   Nothing else in the Dockerfile changes.
3. `docker/supervisor/supervisord.conf` `[program:nginx]`: drop `user=nginx`
   (master runs as root; workers drop to `nginx` via the `user` directive).
   Other programs unchanged.
4. `.github/workflows/code-quality-security.yml`, `docker-security` job smoke
   step: add `nginx -t`; then run the image detached with `APP_KEY` (generated
   in the step), `APP_ENV=production`, `DB_CONNECTION=sqlite`, wait up to 60 s for
   `/api/health` and require 200 for `/robots.txt` and `/api/health`; print
   container logs on failure; stop the container.
5. Verification: `nginx -t` red on main (already shown) → green; runtime smoke
   green; Docker scan still 0; all other CI green.
6. Stop rule: if the runtime smoke exposes another startup defect outside these
   files, stop and report (no widening).

## Rollback

Revert the squash commit.

## Out of scope

`docker/nginx/nginx.conf` and `nginx.prod.conf`, docker-compose files,
`Dockerfile`, HTTPS/TLS in the image, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-074 Gate 2 Option B` (khuyến nghị) / Option A /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
