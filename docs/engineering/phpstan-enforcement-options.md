# PHPStan Enforcement Options

**Date:** 2026-07-13
**Context:** PHPStan runs at level 6 in CI with `continue-on-error: true` — errors never block deployment. This document presents 3 neutral options for the team to choose from.

---

## Current State

| Metric | Value |
|--------|-------|
| PHPStan level | 6 (out of max 10) |
| Total errors (outside baseline) | 2,154 |
| Baseline size | 42,559 ignored errors |
| CI enforcement | `continue-on-error: true` — never blocks |
| Paths analyzed | `app/`, `routes/`, `database/` |
| Paths NOT analyzed | `src/`, `tests/`, `config/`, `bootstrap/` |

### Error Distribution by Directory

| Directory | Errors |
|-----------|--------|
| `app/` | 2,080 (96.6%) |
| `database/` | 65 (3.0%) |
| `routes/` | 9 (0.4%) |

### Top 10 Violated Rules

| Rule | Count | Description |
|------|-------|-------------|
| `missingType.generics` | 713 | Missing generic type parameters (e.g., `Collection` → `Collection<User>`) |
| `property.notFound` | 654 | Access to undefined properties (mostly Eloquent `$fillable`/`$casts` patterns) |
| `method.notFound` | 205 | Calls to undefined methods (Eloquent relations, query builders) |
| `staticMethod.notFound` | 108 | Calls to undefined static methods |
| `missingType.iterableValue` | 99 | Missing value type for iterable types |
| `return.type` | 79 | Return type mismatches |
| `argument.type` | 70 | Argument type mismatches |
| `nullsafe.neverNull` | 31 | Null-safe operator on non-nullable type |
| `missingType.parameter` | 22 | Missing parameter type hints |
| `argument.templateType` | 21 | Template type argument issues |

**Key observation:** 75%+ of errors are type-related (generics, property, method) — these are mostly Laravel/Eloquent patterns where PHPStan can't infer types without extensions like `larastan`.

---

## Option A: Regenerate Baseline + Block New Errors

**Goal:** Prevent new errors from being introduced while leaving existing debt untouched.

**Effort:** Low (1-2 hours)
**Risk:** Low — no code changes, only CI config

**What to do:**

1. Regenerate baseline to capture all 2,154 current errors:
   ```bash
   vendor/bin/phpstan analyse --error-format=json --generate-baseline
   ```

2. Flip `continue-on-error: true` → `false` in `.github/workflows/automated-testing.yml` line 1213:
   ```yaml
   - name: Run PHPStan Static Analysis
     continue-on-error: false   # ← was true
   ```

3. Commit and push. CI will now **block merges** if any new error is introduced.

**Pros:**
- Immediate enforcement — no new errors allowed
- Zero code changes required
- Baseline provides a clear "debt ceiling"

**Cons:**
- Existing 2,154 errors remain ignored (baseline grows but never shrinks)
- Developers may add `@phpstan-ignore` comments to bypass new errors
- If baseline is regenerated incorrectly, new errors may slip through

**Work required:**
- Regenerate baseline once
- Change 1 line in CI config
- Document the policy in README

---

## Option B: Keep Advisory (No Enforcement)

**Goal:** Continue running PHPStudio as a reporting tool only — never block CI.

**Effort:** Zero (status quo)
**Risk:** None — no changes

**What to do:**

1. Keep `continue-on-error: true` as-is.
2. Add a section to `README.md` or `CONTRIBUTING.md` documenting:
   - PHPStan runs at level 6
   - 2,154 errors exist in baseline
   - Developers should run `vendor/bin/phpstan analyse` locally and fix errors they introduce
   - No enforcement — purely advisory

**Pros:**
- No disruption to existing workflow
- Developers can fix errors incrementally without pressure
- No risk of CI failures blocking urgent deploys

**Cons:**
- No guarantee new errors won't be introduced
- Technical debt may grow unchecked
- New contributors may not know about the advisory

**Work required:**
- Add documentation only

---

## Option C: Pay Down by Module (Gradual Enforcement)

**Goal:** Fix errors module-by-module, then enforce per-module as each reaches zero.

**Effort:** High (multiple sessions, estimate 8-16 hours total)
**Risk:** Medium — requires sustained effort over time

**What to do:**

1. **Phase 1 — Inventory (done):** This document provides the breakdown.

2. **Phase 2 — Fix `routes/` first (9 errors):**
   - Smallest directory — quick win
   - Fix all 9 errors, remove from baseline
   - Add routes-specific CI check or keep global

3. **Phase 3 — Fix `database/` (65 errors):**
   - Mostly migration files and seeders
   - Fix, remove from baseline

4. **Phase 4 — Fix `app/` by subdirectory:**
   - Split `app/` into logical groups (Controllers, Models, Services, etc.)
   - Fix one group at a time, remove from baseline
   - Prioritize by error density (most errors first)

5. **Phase 5 — Enable enforcement:**
   - Once baseline is small enough, flip `continue-on-error: false`
   - Or keep baseline but set a "max baseline size" policy

**Pros:**
- Errors actually get fixed (not just ignored)
- Each phase delivers value
- Can stop at any point — partial progress is still progress

**Cons:**
- High effort — requires developer time
- Risk of "never finishing" if priority shifts
- May require installing `larastan` extension for Eloquent-specific rules

**Work required:**
- Fix ~2,154 errors across multiple sessions
- Update baseline after each phase
- Possibly install/configure `larastan` for better Eloquent support

---

## Recommendation

**For most teams:** Option A (regenerate baseline + block new) is the pragmatic choice — it prevents debt from growing while allowing incremental payoff. It takes 1-2 hours and delivers immediate value.

**If team has bandwidth:** Option C (gradual enforcement) is the gold standard but requires sustained commitment.

**If team is resource-constrained:** Option B (advisory) with documented policy is fine — just be explicit about expectations.

---

## Next Steps

**Awaiting user decision.** Once a option is chosen:
- Option A: regenerate baseline + flip CI config
- Option B: add documentation
- Option C: start with routes/ (9 errors), then database/, then app/ by subdirectory

**DO NOT modify `.github/workflows/*` until user decides.**
