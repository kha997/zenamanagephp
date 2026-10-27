# GAP-071 — 32 OS-package vulnerabilities in the production Docker image: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `e1bd5de0d13113d2572f951d61bb873a38a3d146` (origin/main, after GAP-070)

**Branch:** `docs/GAP-071-docker-image-vulnerabilities`

**Scope:** Read-only investigation and Gate-1 documentation. No Dockerfile,
workflow or code change.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-070. `git grep GAP-071` over
every `origin/*` branch: no match.

## Source

The "Docker Security Scan" job (`.github/workflows/code-quality-security.yml`)
builds `Dockerfile.prod` as `zenamanage:test`, scans it with Trivy 0.69.3
(all severities, unfixed included) and posts "Docker Security Scan Report" on
every PR. The job is informational (the check stays green). Latest report
(PR #343, after GAP-070):

```
Vulnerabilities Found: 32 total
#### zenamanage:test (alpine 3.24.2)
- CVE-2026-58055  nghttp2  (MEDIUM)
- CVE-2026-103111 pcre2    (HIGH)
- CVE-2026-19445  python   (HIGH)
- CVE-2026-19553  python   (HIGH)
- CVE-2026-82049  python   (HIGH)
```

All 32 are in the single target `alpine 3.24.2` (OS packages). The Composer
advisories that used to appear here were cleared by GAP-070; the Trivy log
shows the `composer-vendor` and `node-pkg` analyzers ran and the report lists
no language-package target.

The report prints only the first five per target. The full JSON is uploaded as
the `trivy-results` artifact, but its storage host is not reachable from this
session; the complete list will be captured in the Gate-2 work (red-first
evidence).

## Finding 1 — the upstream base image contributes 2

Trivy 0.70.0 on `php:8.2-fpm-alpine`
(`sha256:67d419a46a0f68727fa387817eabb448db7ffc48ac8b6146adaa2009974324f4`,
pulled 2026-10-08):

| Package | Installed | Fixed | Severity | CVE |
|---|---|---|---|---|
| nghttp2-libs | 1.69.0-r0 | 1.70.0-r0 | MEDIUM | CVE-2026-58055 |
| zlib | 1.3.2-r0 | 1.3.2-r1 | MEDIUM | CVE-2026-85091 |

Both already have fixed Alpine packages; an `apk upgrade` in our build picks
them up.

## Finding 2 — the other ~30 come from packages `Dockerfile.prod` adds

The production stage installs, and keeps in the final image:

| Group | Packages | Needed at runtime? |
|---|---|---|
| Services run by supervisord | `nginx`, `supervisor` (pulls **python3**), `php-fpm` (base) | yes |
| Used by the app | `mysql-client` (backup `mysqldump`/`mysql`), `curl` (HEALTHCHECK) | yes |
| PHP extension runtime libs | via `libpng-dev`, `libjpeg-turbo-dev`, `freetype-dev`, `libzip-dev`, `icu-dev`, `oniguruma-dev`, `libxml2-dev` | runtime libs yes, **`-dev` headers no** |
| Build-only | `linux-headers` | no |
| Not referenced by app code or supervisord | `git` (pulls **pcre2**, perl …), `zip`, `unzip`, `redis` (server), `imagemagick` + PECL `imagick` | no evidence of use |

- `python3` (3 of the 5 shown CVEs) is a dependency of Alpine's `supervisor`.
- `pcre2` is pulled by `git` (and `nginx`).
- No PHP code uses `Imagick`; `redis` is a client extension (PECL `redis`), the
  Redis **server** package is not started by `supervisord.conf`.

## Finding 3 — fixed packages may not be picked up because of the build cache

The job builds with `cache-from: type=gha`. The `apk add` layer is reused as
long as the base-image digest and the instruction text are unchanged, so
packages fixed in the Alpine repository after the cached layer was built do
not reach the scanned image. There is no `apk upgrade` step.

## Exposure note

The production image is also what `automated-deployment.yml` and
`release-management.yml` build. Changes to `Dockerfile.prod` therefore affect
future production images; deployment itself is not part of this work.

## Proposed direction (for Gate 2)

1. Upgrade OS packages at build time (`apk upgrade --no-cache`) so fixed
   versions are always taken, regardless of the layer cache.
2. Remove build-only and unused packages from the final image (`-dev`
   headers after the extensions are built, `linux-headers`, and — subject to
   Owner choice — `git`, `zip`, `unzip`, `redis` server, `imagemagick`/`imagick`).
3. Red first: capture the full Trivy list before; after: 0 fixable
   vulnerabilities; report any remaining unfixed ones explicitly.
4. Optional (Owner choice): make the scan report "unfixed" separately
   (`ignore-unfixed`) so the bot count reflects actionable items.
