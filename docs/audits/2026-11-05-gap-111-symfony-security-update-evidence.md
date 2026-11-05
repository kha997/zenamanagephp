# GAP-111 — Symfony security advisories in composer.lock: discovery evidence

Date: 2026-11-05. Base: `main` at `ce41d610d28cbfe5f9a995b4219c51da1fbb67b9` (after GAP-110).

Method: `composer audit --locked` and `composer update --dry-run` on the base lockfile. No production data was used.

## 1. Trigger

The CI check **Security Vulnerability Scan** went red on PR #400 (GAP-110). The job runs `composer audit`, which reports
9 advisories in 6 packages.

The same scan was green on PR #399 the day before, and PR #400 did not touch `composer.json` or `composer.lock`. So the
advisories were published after PR #399's run, and `main` is affected too.

The Owner chose (AskUserQuestion, 2026-11-05): "Merge, then fix Symfony (Recommended)". GAP-110 is merged; this Work ID is
the fix.

## 2. Advisories (all fixed in 7.4.23; the lockfile is on Symfony 7.4.x)

| Package | Locked | Advisory |
|---|---|---|
| `symfony/config` | 7.4.17 | GHSA-5jff-8h8q-r5qx — XXE (local file read) in `XmlUtils::parse()` |
| `symfony/filesystem` | 7.4.18 | GHSA-6prc-h956-hxjq — any prefix before `://` treated as a root, paths escape their base |
| `symfony/http-foundation` | 7.4.20 | CVE-2026-118514 — HEAD requests bypass the `methods` option of access-control rules |
| `symfony/http-foundation` | 7.4.20 | GHSA-3h27-hjv7-fqjx — path rules in front of PHP bypassed by appending the path to the script name |
| `symfony/http-foundation` | 7.4.20 | GHSA-xg2q-4rrq-657q — `private_ranges` trusted-proxy shortcut trusts IPv4-mapped / 6to4 / Teredo / NAT64 clients |
| `symfony/http-foundation` | 7.4.20 | GHSA-5mx8-mwrp-5693 — generated URLs can point to a client-chosen host |
| `symfony/http-kernel` | 7.4.20 | GHSA-wg5q-m2ww-f3c9 — inline fragments let untrusted forwarded headers set host / scheme / port |
| `symfony/routing` | 7.4.20 | CVE-2026-118516 — host placeholders can match the start of the path in compiled matchers |
| `symfony/yaml` | 7.4.21 | GHSA-hwfg-qwvh-2wfx — exponential / quadratic parse time |

Laravel uses `http-foundation`, `http-kernel` and `routing` on every request. Whether a given advisory is exploitable here
depends on configuration (trusted proxies, URL generation behind a proxy, YAML input). That analysis is not needed to
justify the update: the fix is a patch release in the same minor line.

## 3. Upgrade path (dry run)

`composer update symfony/config symfony/filesystem symfony/http-foundation symfony/http-kernel symfony/routing
symfony/yaml --with-dependencies` resolves without changing `composer.json`. It produces **12 patch-level upgrades**, all
inside the same major / minor:

| Package | From | To |
|---|---|---|
| symfony/config | 7.4.17 | 7.4.23 |
| symfony/filesystem | 7.4.18 | 7.4.23 |
| symfony/http-foundation | 7.4.20 | 7.4.23 |
| symfony/http-kernel | 7.4.20 | 7.4.23 |
| symfony/routing | 7.4.20 | 7.4.23 |
| symfony/yaml | 7.4.21 | 7.4.23 |
| symfony/error-handler | 7.4.20 | 7.4.22 |
| symfony/process | 7.4.19 | 7.4.22 |
| symfony/var-dumper | 7.4.21 | 7.4.22 |
| symfony/deprecation-contracts | 3.7.1 | 3.8.1 |
| symfony/event-dispatcher-contracts | 3.7.1 | 3.8.1 |
| symfony/polyfill-mbstring | 1.43.0 | 1.43.3 |

No package is removed or added.

## 4. Open points for Gate 2

1. Update scope:
   - only the 6 affected packages plus their required dependencies (the 12 above); or
   - a general `composer update` of everything (larger and riskier).
2. Whether to look for any extra hardening (for example trusted-proxy configuration) beyond the version bump.
3. Verification:
   - full test suites;
   - `composer audit` clean;
   - CI Security Vulnerability Scan green on the PR head.

## 5. Out of scope

- Laravel or PHP version changes; npm dependencies (the npm audit part of the scan is green).
- Application code changes, unless a test proves a patch release broke behaviour.
- Deployment.
