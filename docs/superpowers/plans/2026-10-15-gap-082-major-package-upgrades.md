---
work_id: GAP-082
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-082/02-design.md
---

# GAP-082 — Major package upgrades: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-082/02-design.md`).

1. **Baseline** — `route:list --json`; `l5-swagger:generate` with the current stack (sorted JSON kept outside the repo).
2. **dbal** — `composer require doctrine/dbal:^4.5 -w`; commit.
3. **tinker** — `composer require laravel/tinker:^3.0 -w`; `php artisan tinker --execute` smoke; commit.
4. **l5-swagger** — `composer require darkaonline/l5-swagger:^11.2 -w`; convert the four files' `@OA` docblocks to
   attributes; adjust `config/l5-swagger.php` only if v11 requires; regenerate and compare docs with the baseline;
   commit the regenerated `api-docs.json`.
5. **Verify** — composer validate/audit, PHPStan, cs-fixer on changed files, route list, test suites, lints, CI.
