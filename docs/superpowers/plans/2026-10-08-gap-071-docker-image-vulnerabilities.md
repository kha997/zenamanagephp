---
work_id: GAP-071
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-071/02-design.md
---

# GAP-071 — Upgrade and slim the production Docker image: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-071/02-design.md`).

1. **Red first** — record the Trivy result of the current `Dockerfile.prod` (32 OS-package findings).
2. **Dockerfile.prod** (production stage only) — `apk upgrade --no-cache`; runtime packages and extension
   runtime libs (incl. `icu-data-full`); build deps in `.build-deps` removed after building the same PHP
   extensions and PECL `redis`; drop `git zip unzip redis imagemagick linux-headers` and PECL `imagick`.
3. **docker-security job** — `no-cache-filters: production`; smoke step `php -m` (ten extensions) and `nginx -t`.
4. **Verify** — Trivy: 0 findings with an available fix (list any unfixed); smoke green; exact-head CI green.
