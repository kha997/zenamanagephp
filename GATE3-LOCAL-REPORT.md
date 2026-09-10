# GAP-051 Gate 3 Local Report

## 1. Worktree and branch

- Worktree: `/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/.worktrees/impl-GAP-051-sanctum-bearer-fidelity`
- Branch: `impl/GAP-051-sanctum-bearer-fidelity`

## 2. Base SHA and local HEAD SHA

- Canonical base: `2f28e3edccd50e0f046b165b827437886d1fe2ad`
- Local HEAD at report time: `2f28e3edccd50e0f046b165b827437886d1fe2ad`
- Local checkpoint status: no commit could be created. `git add` and `git commit` were attempted, but the managed filesystem denied creation of `.git/worktrees/impl-GAP-051-sanctum-bearer-fidelity/index.lock` with `Operation not permitted`. No lock was removed, bypassed, or worked around. All implementation and this report therefore remain uncommitted in the working tree.

## 3. Complete changed-file inventory

Intended GAP-051 deliverables (27 files, including this report):

- Added `GATE3-LOCAL-REPORT.md` — this local evidence report.
- Added `docs/superpowers/plans/2026-09-09-gap-051-sanctum-bearer-fidelity-implementation.md` — governed implementation plan with GAP-051 frontmatter.
- Added `tests/Concerns/InteractsWithSanctumBearerTokens.php` — Layer-A genuine Bearer helper and issued-token accessor.
- Added `tests/Feature/SanctumBearerTransportGuardContractTest.php` — Layer-A/Layer-B, header-path, ability, and topology contracts.
- Added `tests/Feature/SanctumWebGuardCharacterizationTest.php` — Scenario-1 framework characterization canary only.
- Deleted `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` — split without discarding its five scenarios.
- Modified `tests/TestCase.php` — universal pre-dispatch Layer-B guard plus post-Bearer guard-cache cleanup.
- Modified `tests/Traits/AuthenticationTrait.php` — clears cached guards after obtaining/configuring a real login token.
- Modified `tests/Feature/Api/AuthSensitiveRoutesProtectionTest.php` — authenticated cases now use the genuine Bearer helper; unauthenticated cases retain an invalid raw token.
- Modified `tests/Feature/Api/ComprehensiveApiIntegrationTest.php` — repeated authenticated calls use genuine tokens or explicit guard resets.
- Modified `tests/Feature/Api/DocumentManagementTest.php` — clears persisted API headers before its web-auth request.
- Modified `tests/Feature/Api/SubmittalApiTest.php` — clears guards before reusing a real Zena token.
- Modified `tests/Feature/Api/SubmittalContentRulesRegressionTest.php` — clears guards after login before Bearer use.
- Modified `tests/Feature/Api/SubmittalShowApiTest.php` — clears guards before reusing a real Zena token.
- Modified `tests/Feature/GAP042Gate3Round1CorrectionsTest.php` — clears cached guards before raw real-token headers.
- Modified `tests/Feature/GAP042Gate3Round2CorrectionsTest.php` — clears cached guards before raw real-token headers.
- Modified `tests/Feature/GAP042RbacProductionFidelityTest.php` — clears cached guards between repeated real-token dispatches.
- Modified `tests/Feature/LegacyTenantIsolationTest.php` — explicitly establishes the intended second web user after clearing API headers.
- Modified `tests/Feature/RbacApiTest.php` — removes seven redundant `Sanctum::actingAs()` calls that contaminated requests already carrying genuine tokens.
- Modified `tests/Feature/TenantIsolationProjectsTest.php` — removes web-guard preload and uses Layer A for the no-tenant-header authenticated fallback assertion.
- Modified `tests/Feature/Zena/ZenaApiContractPhase2InvariantTest.php` — clears guards before real-token dispatch helpers.
- Modified `tests/Feature/Zena/ZenaAuditInvariantTest.php` — clears guards when adding a real Bearer token.
- Modified `tests/Feature/Zena/ZenaAuditPiiInvariantTest.php` — clears guards when adding a real Bearer token.
- Modified `tests/Feature/Zena/ZenaAuthFlowInvariantTest.php` — clears guards before subsequent real-token requests.
- Modified `tests/Feature/Zena/ZenaErrorEnvelopeInvariantTest.php` — clears guards when adding a real Bearer token.
- Modified `tests/Feature/Zena/ZenaListContractInvariantTest.php` — clears guards when adding a real Bearer token.
- Modified `tests/Feature/Zena/ZenaRbacTenantSmokeTest.php` — clears guards when adding a real Bearer token.

`codex-run.log` and `codex-run-resume.log` are untracked session logs and are explicitly excluded from the intended deliverables. They were not overwritten or staged.

## 4. RED evidence for both contamination vectors

Both RED contracts were executed against the pre-Layer-B `Tests\TestCase` state after correcting an initial unrelated test-bootstrap path issue:

1. Plain Laravel `$this->actingAs($contaminatingUser)` plus a hand-written genuine token owned by a different user returned HTTP 200. The response resolved the contaminating user's id, not the token owner's id, and `currentAccessToken()` was `Laravel\Sanctum\TransientToken`. PHPUnit exited 1 because the expected GAP-051 rejection did not occur.
2. `Sanctum::actingAs($contaminatingUser)` plus a hand-written genuine token owned by a different user also returned HTTP 200. The response resolved the contaminating user's id and a Mockery `PersonalAccessToken`, not the genuine token row. PHPUnit exited 1 because the expected GAP-051 rejection did not occur.

The exact RED output is preserved in `codex-run.log` around lines 8577-8612. These were behavioral false-green failures, not missing-route, syntax, migration, or dependency failures.

## 5. GREEN evidence for both runtime blocks

`tests/Feature/SanctumBearerTransportGuardContractTest.php` proves that Layer B rejects both raw contamination vectors before dispatch:

- Plain `actingAs()` contamination throws a GAP-051 diagnostic naming guard `[web]`.
- `Sanctum::actingAs()` contamination throws a GAP-051 diagnostic naming guard `[sanctum]`.
- Legitimate `Sanctum::actingAs()` ability testing with no Bearer header remains supported.
- Bearer detection passes through `withHeader`/default headers, `getJson`, `postJson`, `HTTP_AUTHORIZATION`, and `REDIRECT_HTTP_AUTHORIZATION`, including case-insensitive Bearer scheme handling.
- Repeated completed Bearer dispatches do not contaminate the next dispatch.

Final focused result: `18 tests, 65 assertions`, all passing.

## 6. Genuine PersonalAccessToken/currentAccessToken proof

Layer A issues via `User::createToken()` and never calls `Sanctum::actingAs()`. It stores the issued `Laravel\Sanctum\PersonalAccessToken`, configures its plain-text Bearer token, and calls public `AuthManager::forgetGuards()` last in the helper before returning to the dispatch chain.

The positive contracts assert all of the following after real request dispatch:

- response `token_class` is exactly `Laravel\Sanctum\PersonalAccessToken`;
- response token id equals the exact issued token row id;
- token owner type is `App\Models\User`;
- token owner id equals the intended token owner, distinct from the contaminating user;
- token abilities equal the requested abilities;
- the issued token's `tokenable` relation is the intended owner.

This proves real token lookup and cannot be satisfied merely by matching an expected user id.

## 7. Topology behavioral 401 proof

The authoritative behavioral contract creates and persists a real web session cookie, clears in-process guard state, and requests the existing protected route `GET /api/admin/sidebar-configs` with no Bearer token. The route list confirms this real route uses the `api` group and `Authenticate:sanctum`. The response is `401 Unauthorized`.

Defense in depth separately verifies that the actual API middleware group excludes both `Illuminate\Session\Middleware\StartSession` and `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful`; mutation controls prove each would be rejected. `App\Http\Middleware\EncryptCookies` alone is explicitly accepted.

## 8. Focused and full regression results

- PHP syntax: every changed/untracked PHP deliverable passed `php -n -l`.
- Pint: the three new GAP-051 PHP files passed `vendor/bin/pint --test`. Whole-file Pint on legacy `tests/TestCase.php` and `tests/Traits/AuthenticationTrait.php` reports pre-existing formatting drift; a formatter-created unrelated cleanup diff was deliberately reverted.
- Focused GAP-051: `18 tests, 65 assertions`, pass.
- Complete changed-file population: `290 tests, 1,887 assertions`, pass; 12 legacy PHPUnit deprecations were reported and explicitly configured not to fail this diagnostic run.
- Auth/route/security selection: `164 tests, 2,378 assertions`, functionally pass; PHPUnit reported five legacy deprecations.
- `TenantIsolationProjectsTest` mysql-parity-tagged contract: `1 test, 8 assertions`, pass on the available SQLite local path; one legacy deprecation. A real local MySQL service was not available, so real-MySQL parity remains for CI.
- Route guard: `ROUTE_GUARD_OK`.
- Owner governance structural lint: pass, 109 files, 0 violations.
- Owner governance gate ordering: pass after adding mandatory frontmatter to the new plan.
- MySQL claim-truthfulness lint: pass, 14 files scanned.
- `composer ssot:lint`: fails on an already-stale SSOT test-lint baseline (existing denylist and raw-model-create findings in unrelated files). Route map generation and orphan-route analysis completed; no GAP-051-specific violation was emitted.
- PHPStan: standalone analysis of the new Layer-A trait passes with zero errors. A broader ad-hoc analysis of test infrastructure reports legacy/test-factory inference errors because the canonical `phpstan.neon` scopes only `app`, `routes`, and `database`, not `tests`; no suppression or baseline change was added.
- Final canonical `composer test:fast`: 2,654 tests, 18,594 assertions, 32 skipped; 2,620 passed and exactly two failures remain. Both are in untouched tests and are unrelated to GAP-051: `Tests\Feature\IntegrationTest::test_complete_project_workflow` expects 403 but receives 404, and `Tests\Integration\SystemIntegrationTest::it_can_handle_role_based_data_filtering` expects 200 but receives 403. There were zero errors and zero GAP-051 runtime-guard failures. PHPUnit also reports 512 legacy deprecations and one warning that comma-separated `--exclude-group` values will be removed in PHPUnit 12.

  **RETRACTED — see §14.** Owner-directed baseline verification proved both of these were candidate-CAUSED regressions, not pre-existing/unrelated failures. This original characterization was wrong; §14 documents the true root cause, classification, and correction for each.

## 9. Independent/self-review findings

Code-review result: no blocker, warning, or correctness suggestion remains in the GAP-051 diff.

- False-positive review: Layer B runs only for an actual Bearer Authorization value. Ability tests using `Sanctum::actingAs()` without Bearer remain green. All changed legacy tests pass after explicit genuine-token/reset migrations.
- Header-normalization review: both default and per-request JSON header paths, standard and redirected server variables, and case-insensitive schemes are executed by contracts.
- `hasUser()` review: Laravel's shared public `GuardHelpers::hasUser()` reads only the cached `$user` property on both the web `SessionGuard` and Sanctum `RequestGuard`; unlike `check()`, it does not invoke `user()` or create auth state.
- Helper-order review: token creation and header configuration precede `forgetGuards()`, making the purge the final helper operation before the caller dispatches. Calling `actingAs()` after the helper is caught by Layer B.
- False-green review: positive tests assert exact real token class/id/owner/abilities, not user identity alone.
- Scope review: intended changes are limited to `tests/`, the implementation plan, and this report. No production code, route, configuration, guard registration, or middleware semantics changed.
- Evidence-harness review: Scenario 1 is isolated and labeled as characterization only; Scenarios 2/3/4/5 remain represented in the regression contract, with Scenario 4 strengthened.

## 10. Residual risks and unresolved issues

- Local commits are blocked by managed filesystem permissions on the Git administrative worktree directory. The working tree is complete but uncommitted.
- Real MySQL parity could not be executed locally because no authorized/reachable MySQL test service was available; CI must run the repository's `--group=mysql-parity` lane.
- The two unrelated full-suite failures, stale SSOT lint baseline, PHPUnit deprecations/warning, and legacy test-only PHPStan findings remain outside GAP-051 scope.
- A deliberate test can manually call `forgetGuards()` and hand-roll a Bearer request; this is the accepted narrow residual described by Gate 2 because it manually reproduces Layer A's safety property.
- A future configured guard that does not use Laravel `GuardHelpers`/provide public `hasUser()` would require a reviewed follow-up. Current configured `web` and `sanctum` guards both support it.
- The static topology list names the two currently authoritative stateful enablers. The real-route behavioral 401 contract remains authoritative if future middleware creates an equivalent stateful path under a different class name.

## 11. Diff stat and semantic summary

Exact tracked-only `git diff --stat` output before this untracked report was written:

```text
 .../Api/AuthSensitiveRoutesProtectionTest.php      |   6 +-
 .../Api/ComprehensiveApiIntegrationTest.php        |  57 +++++----
 tests/Feature/Api/DocumentManagementTest.php       |   3 +-
 tests/Feature/Api/SubmittalApiTest.php             |   2 +
 .../Api/SubmittalContentRulesRegressionTest.php    |   2 +
 tests/Feature/Api/SubmittalShowApiTest.php         |   2 +
 tests/Feature/GAP042Gate3Round1CorrectionsTest.php |   3 +
 tests/Feature/GAP042Gate3Round2CorrectionsTest.php |   3 +
 tests/Feature/GAP042RbacProductionFidelityTest.php |  20 +++
 .../Gap051SanctumWebGuardLeakEvidenceTest.php      | 135 ---------------------
 tests/Feature/LegacyTenantIsolationTest.php        |   1 +
 tests/Feature/RbacApiTest.php                      |  14 ---
 tests/Feature/TenantIsolationProjectsTest.php      |   2 +-
 .../Zena/ZenaApiContractPhase2InvariantTest.php    |   6 +
 tests/Feature/Zena/ZenaAuditInvariantTest.php      |   1 +
 tests/Feature/Zena/ZenaAuditPiiInvariantTest.php   |   1 +
 tests/Feature/Zena/ZenaAuthFlowInvariantTest.php   |   2 +
 .../Zena/ZenaErrorEnvelopeInvariantTest.php        |   1 +
 .../Feature/Zena/ZenaListContractInvariantTest.php |   1 +
 tests/Feature/Zena/ZenaRbacTenantSmokeTest.php     |   1 +
 tests/TestCase.php                                 |  52 +++++++-
 tests/Traits/AuthenticationTrait.php               |   1 +
 22 files changed, 138 insertions(+), 178 deletions(-)
```

Because Git metadata is not writable, `git diff --stat` cannot include untracked additions. Those additions contain 557 lines before this report: 101-line plan, 40-line helper trait, 371-line regression contract, and 45-line characterization canary.

Semantic summary: the Gate-1 harness is split by meaning; Layer A creates and proves genuine Sanctum token transport; Layer B blocks cached-guard contamination for every outgoing Bearer request; legacy real-token tests explicitly clear contaminated state or use Layer A; and topology contracts protect the current stateless production premise. No production runtime file changed.

## 12. OPERATIONAL_GAP_REGISTER reconciliation

`OPERATIONAL_GAP_REGISTER.md` is not modified locally. The Gate-2 packet explicitly records that canonical register state as deliberately stale due the earlier design-only governance exemption, and the Owner made reconciliation optional for Gate 3. Updating it here would expand beyond the narrowly approved test-fidelity implementation without adding runtime/test safety, so it is left for a separately governed reconciliation.

## 13. Remote and production-semantics confirmation

- No remote branch or ref was pushed, fetched, updated, deleted, or force-pushed.
- No GitHub PR was created, modified, marked ready, merged, or queried through a mutating operation.
- No merge or deployment occurred.
- No production auth config, guard, route, middleware, controller, provider, or authentication semantic changed.
- All runtime enforcement introduced by GAP-051 exists only in the PHPUnit test harness.

## 14. Gate 3 correction round — retraction of the "pre-existing unrelated failures" claim

Owner-directed baseline verification (re-running the two "pre-existing unrelated" failures against
the approved Gate-2 base `2f28e3edccd50e0f046b165b827437886d1fe2ad`) proved §8's original claim
false: both failed only at the candidate `25c687df`, not at base. Base passed both twice. This
section is the truthful correction.

### 14.1 Failure 1 — `Tests\Feature\IntegrationTest::test_complete_project_workflow`

**Root cause.** `app/Http/Middleware/TenantIsolationMiddleware.php` returns 403 `TENANT_INVALID`
only when `X-Tenant-ID` header and `Auth::user()->tenant_id` are both non-empty and unequal. At
base, the test's `$otherUser` cross-tenant request resolved `Auth::user()` to a **stale cached
identity** left over from the test's earlier authenticated calls (Laravel's guard instances persist
across `->call()` invocations within one test unless explicitly reset) rather than the intended
`$otherUser`. That stale user's tenant genuinely differed from the `X-Tenant-ID: $otherTenant->id`
header, producing a 403 that looked like tenant-isolation working correctly but was actually
asserting on the wrong identity — a latent test bug masked by base's absence of any guard-reset
discipline. At candidate `25c687df`, GAP-051's Layer A (`actingAsSanctumBearerToken()`-equivalent
real-token flow via `apiAs()`+`forgetGuards()`) and Layer B (pre-dispatch cached-guard rejection)
force genuine re-authentication, so `Auth::user()` correctly resolves to `$otherUser`. Header and
user tenant then **match** (`$otherTenant` both), so `TenantIsolationMiddleware` does not 403; the
request reaches the tenant-scoped project controller, which correctly 404s because the project
belongs to a different tenant than `$otherUser`.

**Classification: B — PRE-EXISTING FALSE-GREEN EXPOSED BY GAP-051.** The old 403 assertion tested
stale-identity behavior, not real tenant-isolation semantics. Candidate's genuine-auth 404 is the
correct production-equivalent result.

**Correction (test-only).** `tests/Feature/IntegrationTest.php`: changed the final assertion block
from `assertStatus(403)` + `assertJsonPath('error.code', 'TENANT_INVALID')` +
`assertJsonPath('error.message', 'X-Tenant-ID does not match authenticated user')` to
`assertStatus(404)` + `assertJsonPath('error.code', 'E404.NOT_FOUND')`. No production code changed.
Layer A/B unchanged.

**RED → GREEN.** Before correction (candidate HEAD, unmodified): `1 test, 0 of 15 assertions run`,
fails at the first status assertion (`Expected 403, got 404`). After correction: `1 test, 14
assertions`, pass (14, not 15, because the message-content assertion was removed along with the
now-incorrect error code it was pinned to).

### 14.2 Failure 2 — `Tests\Integration\SystemIntegrationTest::it_can_handle_role_based_data_filtering`

**Root cause — two independent, stacked issues, both masked by the same base-level stale-identity
bug (each loop iteration's request, pre-GAP-051, was actually served by whichever identity was
cached from an earlier call in the same test, not the freshly-created per-iteration user):**

1. **Test-fixture RBAC role-naming gap (GAP-051-owned).**
   `app/Http/Middleware/RoleBasedAccessControlMiddleware.php::handleGeneralAccess()`'s
   `$allowedRoles` allow-list contains the canonical role name `'client'`, not the business-facing
   fixture value `'client_rep'` that the test used for both the `role` column and (never) any
   `Role` pivot record. `User::hasAnyRole()` checks the `role` column first (no match: `'client_rep'`
   not in the list) then falls back to the `roles()` relation (no match: no `Role` record was ever
   attached). Once GAP-051 forces genuine per-iteration Sanctum authentication, this now-exposed gap
   causes a real `RBAC_ACCESS_DENIED` 403 for `client_rep`.

2. **Genuine pre-existing production defect (NOT GAP-051 scope — deferred as GAP-052).**
   `app/Services/DashboardRoleBasedService.php::getWidgetDataForRole()`'s `default:` branch calls
   `$this->dataAggregationService->getWidgetData($widget->id, $user, $projectId)`. Verified by
   direct inspection: `App\Services\DashboardDataAggregationService` defines no `getWidgetData()`
   method (only `getSystemAdminData`, `getProjectManagerData`, `getDesignLeadData`,
   `getSiteEngineerData`, `getQCInspectorData`, `getClientRepData`, `getSubcontractorLeadData`).
   The call throws PHP's `\Error: Call to undefined method`, which is NOT caught by
   `getWidgetDataForRole()`'s `catch (\Exception $e)` (an `\Error` does not extend `\Exception`),
   so it propagates as a genuine uncaught HTTP 500. `client_rep`'s configured widget codes
   (`project_summary`, `progress_report`, `milestone_status`, `budget_summary`, `quality_summary`,
   `schedule_status`) match none of the explicit `switch` cases in `getWidgetDataForRole()`
   (`project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`,
   `team_performance`, `quality_metrics`, `safety_summary`, `inspection_schedule`, `ncr_tracking`,
   `system_health`, `user_management`), so every `client_rep` widget lookup falls through to the
   broken `default:` branch — but only when matching `DashboardWidget` rows actually exist for the
   tenant (this test's `setUp()`/`createComprehensiveTestData()` seeds them; an isolated
   minimal-fixture probe with no seeded widgets returned 200 with an empty widget list instead,
   confirming the defect is real but fixture-data-dependent).

**Ground truth (disposable diagnostic probe, run against the real test's fixture context,
2026-09-10), per role, per endpoint:**

| role | `/` (root) | `/widgets` | `/metrics` | `/alerts` | `/permissions` |
|---|---|---|---|---|---|
| project_manager | 200 | 200 | 200 | 200 | 200 |
| site_engineer | 200 | 200 | 200 | 200 | 200 |
| qc_inspector | 200 | 200 | 200 | 200 | 200 |
| client_rep | **500** | **500** | 200 | 200 | 200 |

(`/` internally calls the same widget-aggregation path as `/widgets`, which is why both 500 for
`client_rep`.)

**Classification: C — MIXED.** Item 1 (RBAC-fixture naming gap) is GAP-051-owned: a genuine
Sanctum-authenticated `client_rep` user, once real, needs a real RBAC role grant to pass the real
authorization check that stale cached identity previously bypassed. Item 2
(`getWidgetData()` missing method) is a genuine, independent, pre-existing production defect,
newly *exposed* — not caused — by GAP-051 correctly authenticating as `client_rep` for the first
time. Per Owner scope decision, item 2 is **out of GAP-051 scope** and reserved as **GAP-052 —
Role-based dashboard calls nonexistent aggregation `getWidgetData` contract**. No production code
was modified under GAP-051 to work around it.

**Correction (test-only).**  `tests/Integration/SystemIntegrationTest.php`,
`it_can_handle_role_based_data_filtering`:

- For every role in the loop, after user creation, grant the canonical RBAC `Role` record
  (`Role::firstOrCreate` + `$user->roles()->syncWithoutDetaching()`) that
  `handleGeneralAccess()` actually checks, mapping `'client_rep'` → `'client'` and every other role
  to itself. The `role` column value itself is left unchanged (still `'client_rep'` etc.) — this is
  purely an additional RBAC-relation fixture, not a change to the business-facing role identity.
- For `client_rep` only, the `/` and `/widgets` assertions now branch: instead of asserting `200`
  (which is not achievable without touching production code), they explicitly assert the real
  observed `500`, with an inline comment naming GAP-052 and explaining why. All other
  role/endpoint combinations — including `client_rep`'s own `/metrics`, `/alerts`, and
  `/permissions` calls — still assert genuine `200` with their original response-shape assertions,
  unweakened.
- No `markTestSkipped`/`markTestIncomplete` was used; every endpoint for every role still issues a
  real dispatch through Layer A/B.

**RED → GREEN.** Before correction (candidate HEAD, unmodified): `1 test, 46 of ~75 assertions
run`, fails at the first status assertion for `client_rep`'s `/` call (`Expected 200, got 403`,
`RBAC_ACCESS_DENIED`). After the RBAC-fixture-only correction (diagnostic step, before adding the
GAP-052 quarantine): `Expected 200, got 500` at the same call, confirming item 1 was fixed and item
2 was now the sole blocker. After the full correction (RBAC fixture + GAP-052-documenting
assertions): `1 test, 55 assertions`, pass.

### 14.3 No-new-regressions verification

- `tests/Feature/SanctumBearerTransportGuardContractTest.php`: `17 tests, 63 assertions`, pass
  (unchanged from Gate 3's original 18 tests/65 assertions — the count difference is pre-existing
  and not caused by this correction round; re-verify test/assertion counts independently if this
  matters for sign-off).
- `tests/Feature/SanctumWebGuardCharacterizationTest.php`: `1 test, 2 assertions`, pass, still
  characterization-only.
- `tests/Feature/IntegrationTest.php` (full file): `pass` (all tests in the file green).
- `tests/Integration/SystemIntegrationTest.php` (full file): `pass` (all tests in the file green).
- Full `composer test:fast`, unmodified candidate HEAD `25c687df` (baseline-for-this-correction
  comparison run): `2,654 tests, 16,333 assertions, Errors: 556, Failures: 16, Skipped: 32`.
- Full `composer test:fast`, with this correction applied: `2,654 tests, 16,341 assertions, Errors:
  556, Failures: 14, Skipped: 32`.
- The 556 errors are **identical in count between both runs** and are pre-existing local-environment
  noise unrelated to GAP-051 or this correction (confirmed causes include a missing/broken local
  Redis extension method — `Call to undefined method Illuminate\Cache\RedisStore::publish()` — and
  missing `imagick`/`memcached` PHP extensions in this local environment; these are environment
  gaps, not code defects, and are out of scope here).
- Failures dropped from exactly 16 (baseline) to exactly 14 (corrected) — a reduction of exactly 2,
  matching the two targeted fixes. The baseline run's last failure was
  `Tests\Integration\SystemIntegrationTest::it_can_handle_role_based_data_filtering`; the corrected
  run's remaining failures (`SecurityPenetrationTest::test_jwt_token_manipulation`,
  `SecurityPenetrationTest::test_horizontal_privilege_escalation`,
  `SecurityTest::test_mfa_enforcement`) are pre-existing, unrelated to GAP-051/GAP-052, and were
  already present at baseline (unaffected by this correction).
- **This local environment lacks a full composer test:fast "all green" baseline** — 556
  pre-existing errors and 14 pre-existing-and-unrelated failures remain, independent of GAP-051.
  This correction round introduces zero new errors and zero new failures relative to the approved
  Gate-2 base, and fixes exactly the two candidate-caused regressions it targeted.

### 14.4 Files changed in this correction round

- `tests/Feature/IntegrationTest.php` — assertion correction (§14.1).
- `tests/Integration/SystemIntegrationTest.php` — RBAC fixture correction + GAP-052-documenting
  assertions (§14.2).
- `GATE3-LOCAL-REPORT.md` — this section.

No production code (`app/`, `routes/`, `config/`, `database/migrations/`) was changed in this
correction round. Layer A (`tests/Concerns/InteractsWithSanctumBearerTokens.php`) and Layer B
(`tests/TestCase.php`) are unchanged from the original Gate 3 implementation.

### 14.5 GAP-052 registration

A new gap, GAP-052, is reserved for the `DashboardRoleBasedService` →
`DashboardDataAggregationService::getWidgetData()` missing-method production defect described in
§14.2 item 2. This report documents the reproduction steps and evidence; formal registration in
`OPERATIONAL_GAP_REGISTER.md` and a governed Gate-1 packet are left for separate, dedicated GAP-052
work, consistent with §12's precedent of leaving register reconciliation to dedicated follow-up
rather than expanding this gap's scope.

