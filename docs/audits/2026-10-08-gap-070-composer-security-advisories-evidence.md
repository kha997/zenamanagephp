# GAP-070 — 20 Composer security advisories in the locked dependencies: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `c8a38b8ff5312c2a40b72184858c8c8b7411508c` (origin/main)

**Branch:** `docs/GAP-070-composer-security-advisories`

**Scope:** Read-only investigation and Gate-1 documentation. `composer audit`
and `composer update --dry-run` only; `composer.lock` is unchanged.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-068; GAP-065 is Draft PR #338,
GAP-069 is PR #342 (awaiting merge). `git grep GAP-070` over every `origin/*`
branch: no match.

## Source

The "Security Scan Report" bot comment on every PR (`code-quality-security.yml`,
"Security Vulnerability Scan" job) lists "Composer Security Issues: 20
vulnerabilities found". The job is informational (the PR checks stay green).

## Finding 1 — the advisories (`composer audit`, base `c8a38b8f`)

| Package | Locked | Advisories | Severity | Fixed in |
|---|---|---|---|---|
| `league/commonmark` | 2.8.2 | 12 | 9 high, 3 medium (DoS via crafted Markdown, XSS / unsafe-link filter bypasses) | ≥ 2.10.2 |
| `guzzlehttp/guzzle` | 7.13.2 | 6 | 1 high, 5 medium (host/cookie scope, Referer fragment leak, Proxy-Authorization leak, cookie DoS) | ≥ 7.15.2 |
| `laravel/framework` | v12.63.0 | 1 | low (XSS in debug page) | ≥ 12.69.0 |
| `league/flysystem` | 3.35.2 | 1 | low (control characters in paths) | > 3.35.2 |

The same Guzzle advisories also appear in the "Docker Security Scan" report
(it scans `vendor/composer/installed.json` inside the image).

## Finding 2 — all fixes fit the existing constraints (dry run)

`composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises
league/commonmark laravel/framework league/flysystem --dry-run`:

```
guzzlehttp/guzzle   7.13.2  => 7.15.5
guzzlehttp/promises 2.5.0   => 2.5.3
guzzlehttp/psr7     2.12.3  => 2.13.1
laravel/framework   v12.63.0 => v12.69.3
league/commonmark   2.8.2   => 2.10.3
league/flysystem    3.35.2  => 3.36.0
```

No `composer.json` change is needed (`^7.2`, `^12.0` and the transitive
ranges already allow these versions). A broader
`--with-dependencies` update would move about 90 packages; that is not
required to clear the advisories.

## Exposure note

`league/commonmark` is reached through Laravel's `Str::markdown()` /
Markdown mail; Guzzle through the HTTP client. Whether user-supplied input
reaches these paths was not audited here; the advisories are cleared by the
update either way.

## Proposed direction (for Gate 2)

Targeted lockfile-only update of the six packages above, full test suite and
exact-head CI, `composer audit` must report 0. Optional extras (CI gate on
`composer audit`, Dependabot) are separate steps of the remediation plan.
