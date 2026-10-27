# GAP-073 — PHPStan 2.3 upgrade and the 10 findings it reports: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `af7d5968078d2e50caeba38a5cb8d62261e7e6cd` (origin/main, after GAP-072)

**Branch:** `docs/GAP-073-phpstan-2-3`

**Scope:** Read-only investigation and Gate-1 documentation. No code, lockfile,
baseline or config change.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-072. `git grep GAP-073` over
every `origin/*` branch: no match.

## Source

GAP-072 held `phpstan/phpstan` at 2.2.5 because the in-constraint 2.3.0 reports
10 errors on unchanged code (GAP-072 Gate-2 trial, PHPStan scope `app`,
`routes`, `database`).

## Finding — the 10 errors, by cause

| # | File:line | Error | Assessment |
|---|---|---|---|
| 1–3 | `app/Http/Controllers/Admin/BasicSidebarController.php:57,81,82` | `$dbConfig` undefined (+ baseline count mismatch) | Real bug (the lookup line is missing), **but the class is not referenced by any route** (dead code) |
| 4–6 | `app/Http/Requests/UpdateInteractionLogRequest.php:79,81,92` | `$interactionLog` undefined inside validation closures (+ baseline count mismatch) | Real bug (closures lack `use ($interactionLog)`), **but this request is only used by `App\Http\Controllers\InteractionLogController`, which no route references**; the routed API uses `Src\InteractionLogs\…` classes |
| 7–9 | `app/Http/Controllers/Api/App/SettingsController.php:116,189,286` | closure `use ($validator)` unused | Harmless dead capture in routed `/api/.../settings` handlers |
| 10 | `app/Services/HealthCheckService.php:255` | `Redis::set()` 3rd param expects array | Runtime goes through Laravel's `PhpRedisConnection::set($key, $value, $expireResolution, $expireTTL)`, which accepts `'EX', 60`; the error is a typing mismatch. An equivalent call without ambiguity is `setex($key, 60, $value)` |

Notes:

- `phpstan-baseline.neon` already contains entries for the undefined
  `$dbConfig`; PHPStan 2.3 now also reports the occurrences inside closures,
  so the expected counts no longer match.
- Items 1–6 sit in classes reachable from no route; fixing or deleting them
  has no user-visible effect today.

## Proposed direction (for Gate 2)

Upgrade `phpstan/phpstan` to 2.3.x (lockfile) and resolve the 10 findings
without suppressing them (no new baseline entries, no `@phpstan-ignore`),
choosing for items 1–6 between fixing the code and removing the unreachable
classes.
