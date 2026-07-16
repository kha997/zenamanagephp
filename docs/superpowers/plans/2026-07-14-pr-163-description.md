## Summary

Project model consolidation from legacy `Src\CoreProject` into `App\Models`, followed by six feature slices and aggressive repo cleanup. This branch modernises the core Project model path, delivers contract-level finance management with payment certificates, cleans ~270 dead files from the repo root, retires 6 thin Zena* aliases, and enforces PHPStan in CI to prevent regression.

### Slices delivered

| Slice | What it does | Key commits | Spec / Plan |
|-------|-------------|-------------|-------------|
| **Consolidation discovery** | Trace 40+ `Src\CoreProject` refs; migrate 6 controllers to `App\Models\Project` | `3963cc62`..`a3079be0` | `docs/roadmap/...project-model-consolidation.md` |
| **Hardening** | Rate-limit AI endpoints, `TenantScope` global on Lead/Account/Opportunity/DesignItem, delete 8 stray route files, exclude `@group performance` from default suite | `7b6a6728`..`18f0ad55` | `docs/superpowers/plans/...harden-slice.md` |
| **R-DPM (design-PM)** | Revision history, block/unblock, design-item sections per project | `61257b16`..`de9c6556` | `docs/specs/...design-pm-completion.md` |
| **R-CTR (contract management)** | `contract_type`, per-type progress, finance block, expenses, project finance rollup card | `64488a79`..`538c6f5c` | `docs/specs/...contract-centric-management.md` |
| **IPC (payment certificates)** | Contract-scoped BOQ, BOQ line CRUD, PaymentCertificate lifecycle, cumulative summary, deductions UI | `552959b5`..`df758f00` | `docs/specs/...interim-payment-certificates.md` |
| **Retention & advance deductions** | Finance settings (retention %, advance amount/recovery %), deduction calculations, `net_payable`, UI summary blocks | `197ee6a1`..`df758f00` | `docs/superpowers/plans/...retention-advance-deductions.md` |
| **Repo root cleanup** | Archive 206 report files, remove 46 PHP scripts, 7 shell scripts, temp/cookie files, 11 dead files | `b904b67b`..`272ba5fe` | `docs/change-proposals/...repo-root-cleanup.md` |
| **Alias retirement** | Migrate 5 test/seed files off Zena* aliases, delete 6 thin aliases + factories, enforce `Model::query()` in CI | `f95f3243`..`184ed803` | `docs/change-proposals/...zena-alias-retirement.md` |
| **PHPStan enforcement** | Enforce in CI, regenerate baseline, remove `continue-on-error` | `6e98dbb4` | `docs/engineering/...phpstan-enforcement.md` |

### Verification

```bash
php artisan test --testsuite=Feature          # 897 passed (8099 assertions)
php artisan test tests/Feature/Architecture/  # 29 passed (516 assertions)
vendor/bin/phpstan analyse --memory-limit=1G  # 0 errors
vendor/bin/deptrac analyse --no-progress      # 0 violations, 3 skipped, 9779 uncovered
scripts/ssot/lint_tests.sh                    # PASS — no new violations beyond baseline
scripts/ci/lint-domain-ownership.php          # PASS — legacy debt only, no new drift
```

> Default test suite excludes `@group performance` (~55 tagged methods/classes). All architecture, feature, and PHPStan checks pass clean.

### Breaking / behavior changes

| Change | Impact |
|--------|--------|
| **Retention deduction calculation** | Certificate advance deduction now equals `net_payable` (previously 100% of billed amount). Retention % applied to net before advance deduction. |
| **PHPStan enforced in CI** | New static-analysis errors will block merges (was `continue-on-error: true`). |
| **6 Zena* aliases removed** | `ZenaTask`, `ZenaSubmittal`, `ZenaRfi`, `ZenaNotification`, `ZenaChangeRequest`, `ZenaProject` — use canonical model names directly. |
| **~270 files removed from repo root** | Historical reports, one-shot scripts, debug artifacts, cookie jars archived/removed. |
| **`Src\CoreProject\Models\Project` references eliminated** | All controllers now use `App\Models\Project` directly; legacy path still exists in `vendor/` but is no longer imported in app code. |
| **Rate limiting on AI suggestion endpoints** | `ai.suggest` endpoints throttled per-user to prevent abuse. |
| **TenantScope on Lead/Account/Opportunity/DesignItem** | Global scope auto-filters by `tenant_id`; raw static calls will return empty without scope. |

### Repo cleanup stats

- **206** historical report markdown files → `docs/archive/reports/`
- **46** one-shot PHP fix scripts removed
- **7** unreferenced shell scripts removed
- **11** dead files referencing `Src\CoreProject\Models\Project` removed
- **6** thin Zena* aliases + factories deleted
- **~270** total files removed/archived from repo root
