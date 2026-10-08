---
work_id: GAP-070
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-070/02-design.md
---

# GAP-070 — Clear the 20 Composer security advisories: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-070/02-design.md`); lockfile only.

1. **Red first** — `composer audit` on the base reports 20 advisories in 4 packages.
2. **Update** — `composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises league/commonmark
   laravel/framework league/flysystem`; verify the lock diff moves exactly these six packages
   (anything else: stop and report).
3. **Verify** — `composer audit` 0; Treasury, Architecture, Feature, Unit, Zena, Services suites; PHPStan;
   SSOT / governance / docs lints; exact-head CI (incl. real-MySQL jobs and browser-tests).
