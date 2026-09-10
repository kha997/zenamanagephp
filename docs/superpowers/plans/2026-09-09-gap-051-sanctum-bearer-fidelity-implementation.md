---
work_id: GAP-051
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-051/02-design.md
---

# GAP-051 Sanctum Bearer Fidelity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent Bearer-token feature tests from passing through cached Laravel or Sanctum guard users while preserving legitimate Sanctum ability tests and proving real personal-access-token lookup.

**Architecture:** Add exactly two test-only layers: a real-token helper that issues via `createToken()` and clears cached guards immediately before dispatch, plus a universal Bearer-aware pre-dispatch check in `Tests\TestCase::call()` that inspects configured Sanctum guards and the `sanctum` guard with `hasUser()`. Split the Gate-1 evidence harness into a framework characterization canary and genuine GAP-051 contracts, including the current production-topology safeguards.

**Tech Stack:** PHP 8.2, Laravel 12, Laravel Sanctum 4, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-09-09-gap-051-gate2-sanctum-bearer-fidelity-contract-design.md`

## Global Constraints

- All runtime/helper changes remain under `tests/`; production auth/config/guard/middleware semantics are untouched.
- Implement exactly Layer A and Layer B; do not add a source-text tripwire or custom PHPStan rule.
- Detect pre-existing guard state with public `hasUser()`, never `check()`.
- Preserve all five Gate-1 scenarios with Scenario 1 alone in the characterization test.
- The topology behavioral proof uses a real existing `auth:sanctum` API route and the actual API middleware stack.
- Do not touch JWT helper naming, GAP-050, remote refs, PRs, merges, or worktree locks.

---

### Task 1: RED contamination contracts

**Files:**
- Create: `tests/Feature/SanctumBearerTransportGuardContractTest.php`

**Interfaces:**
- Consumes: `Tests\TestCase::call()` and Laravel/Sanctum authentication helpers.
- Produces: executable contracts expecting a GAP-051 exception naming `web` or `sanctum` before a raw Bearer request dispatches.

- [ ] Write one test for plain `$this->actingAs()` plus a hand-written real Bearer request and one test for `Sanctum::actingAs()` plus a hand-written real Bearer request.
- [ ] Run each test separately against the unmodified `Tests\TestCase` and capture the intended failure: no GAP-051 exception is thrown because cached guard state satisfies the request.
- [ ] Confirm both failures are behavioral contract failures, not setup, syntax, migration, or bootstrap errors.

### Task 2: Layer B universal pre-dispatch guard

**Files:**
- Modify: `tests/TestCase.php`
- Test: `tests/Feature/SanctumBearerTransportGuardContractTest.php`

**Interfaces:**
- Consumes: request server headers and `config('sanctum.guard', [])`.
- Produces: `guardAgainstGap051BearerContamination(array $server): void`, invoked at the start of `call()`, throwing a diagnostic `RuntimeException` for a contaminated guard.

- [ ] Add the minimal pre-dispatch call guard: recognize actual Bearer Authorization headers, derive unique configured guards plus `sanctum`, and call only `hasUser()`.
- [ ] Run the two RED tests and verify both pass with diagnostics that name GAP-051 and the contaminated guard.
- [ ] Add focused coverage for `withHeader`, `withHeaders`, JSON helper request paths, default headers, and non-Bearer requests.
- [ ] Run the focused Layer-B contracts.

### Task 3: Layer A genuine Bearer helper and harness split

**Files:**
- Create: `tests/Concerns/InteractsWithSanctumBearerTokens.php`
- Create: `tests/Feature/SanctumWebGuardCharacterizationTest.php`
- Modify: `tests/Feature/SanctumBearerTransportGuardContractTest.php`
- Delete: `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php`

**Interfaces:**
- Produces: `actingAsSanctumBearerToken(User $user, array $abilities = ['*']): static`, real token metadata accessible to the contract test, and preserved Gate-1 scenarios split by meaning.

- [ ] Write helper-path tests that first contaminate guard state, dispatch via the helper, and assert a real `PersonalAccessToken` with exact issued token id, owner, and abilities.
- [ ] Run them RED and confirm failure is the missing helper/contract.
- [ ] Implement the helper with `createToken()` and `AuthManager::forgetGuards()` immediately before returning the Bearer-configured request client; do not call `Sanctum::actingAs()`.
- [ ] Split Scenario 1 into the characterization canary and move Scenarios 2/3/4/5 into the contract file without weakening them.
- [ ] Run the split focused tests and verify the helper path is GREEN.

### Task 4: Production-topology safeguards

**Files:**
- Modify: `tests/Feature/SanctumBearerTransportGuardContractTest.php`

**Interfaces:**
- Consumes: `App\Http\Kernel` middleware groups and a real existing `auth:sanctum` API route.
- Produces: focused absence checks for `StartSession` and `EnsureFrontendRequestsAreStateful`, plus authoritative behavioral 401 proof with no Bearer token.

- [ ] Add the risk-focused static invariant without pinning the full middleware array and without treating `EncryptCookies` as exposure.
- [ ] Add the behavioral request-cycle contract using a real protected API route and session cookie state rather than cached test guard state.
- [ ] Run the focused topology contracts and route-list evidence.

### Task 5: Regression, review, commit, and local report

**Files:**
- Create: `GATE3-LOCAL-REPORT.md`
- Optionally modify: `OPERATIONAL_GAP_REGISTER.md` only if local reconciliation is justified.

**Interfaces:**
- Produces: verified local commit(s) and the required evidence report; no remote mutation.

- [ ] Run focused GAP-051, Sanctum/auth, Feature/Unit, routes/security guardrails, `composer ssot:lint`, affected static analysis, formatting, and diff checks.
- [ ] Self-review false positives, header normalization, `SessionGuard`/`RequestGuard::hasUser()`, helper ordering, token-identity strength, changed-file scope, and production semantic drift.
- [ ] Make local checkpoint commits only after fresh verification.
- [ ] Write `GATE3-LOCAL-REPORT.md` with all thirteen required report fields, then make the final local report commit.
- [ ] Confirm branch/path/base/head, clean intended diff, no remote/PR operations, and stop.
