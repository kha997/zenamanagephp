---
work_id: GAP-111
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-111/02-design.md
---

# GAP-111 — Symfony security advisories: implementation plan

Executes Gate 2 U-1 (`docs/owner-decisions/GAP-111/02-design.md`).

1. Red: `composer audit --locked` on the base lockfile reports 9 advisories in 6 packages.
2. Change: `composer update symfony/config symfony/filesystem symfony/http-foundation symfony/http-kernel symfony/routing
   symfony/yaml --with-dependencies` (composer.lock only).
3. Green:
   - `composer audit --locked` clean;
   - Unit + Feature + Integration and `tests/Feature/Api` with no new failures;
   - PHPStan, php-cs-fixer, SSOT lint, ssot:lint, route guard, deptrac;
   - app boots (route list, login page).
4. Any behaviour change found → stop and raise an Owner amendment; no application code change in this plan.
