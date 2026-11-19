# GAP-127 — Security Vulnerability Scan green again: implementation plan

Gate 2: `docs/owner-decisions/GAP-127/02-design.md` (approved 2026-11-20, L-1 and P-1).

## L-1 — laravel/framework

1. Record a baseline: full Unit + Feature + Integration on the branch before the change, `vendor/` at 12.69.3 (junit log kept).
2. `composer require "laravel/framework:^12.71.0" --no-install`.
   Check: `composer.json` changes one line; in `composer.lock` only `laravel/framework` (v12.69.3 → v12.73.0) and `content-hash` change.
3. `composer install`, then `composer audit --locked` exits 0.
4. Run the full suite again. Compare to the baseline per test: same test count, and no test that passed before fails now.

## P-1 — picomatch

Re-checked during implementation (2026-11-20): GitHub **withdrew** GHSA-fc5q-39xc-vjpf on 2026-11-18 15:38 UTC.
`npm audit --package-lock-only` on this branch now reports 0 vulnerabilities, and the latest `main` run (44601911747, 2026-11-19) fails only on Composer.
An exception for a withdrawn advisory is not needed. Whether P-1 is still applied goes back to the Owner as a Gate 2 amendment; it is not changed silently.

## Verification at Gate 3

- Security Vulnerability Scan green on the exact PR head.
- Suite comparison from L-1 step 4.
- The diff touches only allowlisted files.
