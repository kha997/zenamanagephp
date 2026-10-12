---
work_id: GAP-074
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-074/02-design.md
---

# GAP-074 — Production image nginx and HEALTHCHECK: implementation plan

Executes approved Gate 2 Option B (`docs/owner-decisions/GAP-074/02-design.md`).

1. **Red first** — `docker/nginx/nginx.conf` as main config fails `nginx -t` (Gate-1 evidence).
2. **Config** — add `docker/nginx/nginx.single-container.conf`; `Dockerfile.prod` copies it, `EXPOSE 80`,
   HEALTHCHECK `curl -fsS http://127.0.0.1/api/health`; supervisord `[program:nginx]` without `user=nginx`.
3. **CI** — docker-security smoke: `nginx -t`; run the image with a generated `APP_KEY` and SQLite, require 200
   from `/robots.txt` and `/api/health`, print logs on failure.
4. **Verify** — exact-head CI green incl. the runtime smoke and Docker scan 0. Stop and report on defects
   outside the allowlist.
