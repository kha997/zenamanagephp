# GAP-074 — Production image: nginx cannot start and the HEALTHCHECK is wrong: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `92f0a04405de62f62f8c54f3df28ee67148ac8e8` (origin/main, after GAP-073)

**Branch:** `docs/GAP-074-prod-image-nginx`

**Scope:** Read-only investigation and Gate-1 documentation. No Dockerfile,
config or workflow change.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-073. `git grep GAP-074` over
every `origin/*` branch: no match.

## Source

Found during GAP-071 (production-image hardening): the Owner dropped an
`nginx -t` smoke step because the config already fails on main, and asked for
this follow-up.

## Finding 1 — the image installs a server-level file as the main nginx config

`Dockerfile.prod`: `COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf`.

`docker/nginx/nginx.conf` is a **server-level** snippet (top-level `upstream`,
`limit_req_zone`, `server` blocks, no `events {}` / `http {}`); it is written
for `docker-compose.yml`, which mounts it as
`/etc/nginx/conf.d/default.conf`. As a main config it is invalid. Verified with
stock `nginx:alpine`:

```
nginx: [emerg] "upstream" directive is not allowed here in /etc/nginx/nginx.conf:4
nginx: configuration file /etc/nginx/nginx.conf test failed
```

Even as a server snippet it does not fit the single-container image: it
proxies to `127.0.0.1:8001-8005` (a load-balancer layout); nothing in the
image listens there. `docker/nginx/nginx.prod.conf` (used by
`docker-compose.prod.yml`) is a full config but expects TLS certificates and
an `app:9000` upstream in a separate container, so it does not fit either.

## Finding 2 — supervisord starts nginx as a non-root user

`docker/supervisor/supervisord.conf` `[program:nginx]` has `user=nginx`; an
nginx master running as `nginx` cannot bind port 80.

## Finding 3 — the HEALTHCHECK probes the wrong thing

```
HEALTHCHECK ... CMD curl -f http://localhost:9000/health || exit 1
EXPOSE 9000
```

Port 9000 is php-fpm (FastCGI), not HTTP, so the check can never succeed and
the container is always reported unhealthy. The Laravel liveness route is
`GET /api/health` (`routes/api.php`), served through nginx on port 80.

## Who uses this image

`Dockerfile.prod` is built by `automated-deployment.yml` and
`release-management.yml` (and scanned by `code-quality-security.yml`).
`docker-compose*.yml` and `scripts/deploy.sh` use `Dockerfile` and are not
affected. Together the findings mean the production image as built today
cannot serve HTTP.

## Proposed direction (for Gate 2)

A dedicated single-container nginx config for `Dockerfile.prod` (listen 80,
`root /var/www/html/public`, FastCGI to `127.0.0.1:9000`), nginx master run as
root by supervisord (workers stay unprivileged), `EXPOSE 80`, HEALTHCHECK on
`http://127.0.0.1/api/health`, and a CI smoke that runs `nginx -t` and, if
approved, starts the container and requests a page. `docker/nginx/nginx.conf`
stays as it is for docker-compose.
