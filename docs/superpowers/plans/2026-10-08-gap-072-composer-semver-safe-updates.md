---
work_id: GAP-072
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-072/02-design.md
---

# GAP-072 — In-constraint Composer updates (PHPStan held): implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-072/02-design.md`); lockfile only.

1. **Baseline** — `composer outdated --locked`: 88 (72 semver-safe, 15 majors, 1 abandoned).
2. **Update** — `composer update --with phpstan/phpstan:2.2.5`; verify the lock diff is exactly the 76
   in-constraint updates of the trial (anything else: stop and report); `composer.json` unchanged.
3. **Verify** — `composer audit` 0; `composer validate`; PHPStan clean; Unit, Feature, Integration suites;
   SSOT / governance / docs lints; exact-head CI (incl. real-MySQL jobs and browser-tests).
