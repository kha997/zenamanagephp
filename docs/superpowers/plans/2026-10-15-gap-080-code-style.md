---
work_id: GAP-080
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-080/02-design.md
---

# GAP-080 — php-cs-fixer style debt: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-080/02-design.md`).

1. **Baseline** — save `php artisan route:list --json` on the base.
2. **Config + tool** — disable the three content-changing rules in `.php-cs-fixer.php`; add
   `scripts/dev/ast-equivalence-check.php`.
3. **Reformat** — one `php-cs-fixer fix` run, committed alone with no hand edits.
4. **Prove** — fixer dry-run clean; `php -l`; AST-equivalence check against the base; PHPStan; identical
   route list; Unit/Feature/Integration suites.
5. **CI** — code-quality job uses the locked fixer and fails on PR-changed files with style issues;
   red/green proof with a throw-away mis-formatted commit and its revert.
6. **Gate 3** packet.
