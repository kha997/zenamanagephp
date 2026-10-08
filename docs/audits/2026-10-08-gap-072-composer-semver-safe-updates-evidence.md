# GAP-072 — 88 outdated Composer packages: Gate-1 evidence

**Date:** 2026-10-08 (+07:00)

**Canonical base:** `0f0b8ac9fe9164017aacf101533b85dcbd2fbee8` (origin/main, after GAP-071)

**Branch:** `docs/GAP-072-composer-semver-safe-updates`

**Scope:** Read-only investigation and Gate-1 documentation (`composer outdated`
and `composer update --dry-run` only). `composer.lock` is unchanged.

## Candidate-ID audit

`docs/owner-decisions/` on main tops out at GAP-071. `git grep GAP-072` over
every `origin/*` branch: no match.

## Source

The "Dependency Scan Report" bot comment on every PR lists "Outdated Packages:
88 packages need updates". The job is informational. After GAP-070 none of them
carries a known security advisory (`composer audit`: 0).

## Finding 1 — classification (`composer outdated --locked`)

| Status | Count | Meaning |
|---|---|---|
| semver-safe-update | 72 | newer version allowed by the current constraints |
| update-possible | 15 | newer version needs a constraint change (major) |
| up-to-date | 1 | `doctrine/annotations` (abandoned, listed only because of that) |

21 of the 88 are direct dependencies (`composer.json`); the rest are
transitive.

## Finding 2 — what an in-constraint update moves (`composer update --dry-run`)

77 lock updates, 0 installs, 0 removals, no `composer.json` change. Main
groups:

- Symfony 7.4.x patch releases (≈25 packages) and polyfills 1.43.
- Laravel ecosystem: sanctum, serializable-closure, prompts, pint, dusk, sail.
- Runtime libraries: monolog 3.12, carbon 3.14, sentry 4.34 / sentry-laravel
  4.29, predis 3.6, aws-sdk 3.399, google apiclient 2.20, firebase/php-jwt 7.2,
  pusher 7.3, phpdotenv 5.7, ramsey/uuid 4.9.4.
- Dev tools: phpunit 11.5.57, phpstan 2.3.0, php-cs-fixer, mockery 1.6.15,
  deptrac 4.7, **hamcrest 3.0.0** (major bump allowed by mockery's range).

Two moves deserve attention: `phpstan/phpstan` 2.2 → 2.3 (a new minor can
report new errors) and `hamcrest/hamcrest-php` 2 → 3 (major, dev only).

## Finding 3 — the 15 majors (out of scope for this Work ID)

Direct: `doctrine/dbal` 3 → 4, `guzzlehttp/guzzle` 7 → 8, `laravel/tinker`
2 → 3, `darkaonline/l5-swagger` 8 → 11. Transitive ones follow them (guzzle
psr7/promises/uri-template, swagger-php, carbon-doctrine-types, brick/math,
spatie flare/error-solutions, theseer/tokenizer, google apiclient-services
pin). Each needs code review and its own Work ID.

## Proposed direction (for Gate 2)

One lockfile-only update of the in-constraint versions with the full test
suite, PHPStan and exact-head CI; the majors stay separate.
