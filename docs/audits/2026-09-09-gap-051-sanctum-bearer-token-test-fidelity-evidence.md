# GAP-051 Gate 1 Evidence — Sanctum Bearer-Token Authentication Test Fidelity

**Date:** 2026-09-09.
**Canonical main SHA used:** `9585eed9b701cd37a7188789d1754206545ad274` (verified: this worktree was checked out at this exact commit before any investigation began; `git status` was clean; no drift).
**Method:** Static repository evidence (full reads of `config/auth.php`, `config/sanctum.php`, `app/Http/Kernel.php`, `app/Providers/RouteServiceProvider.php`, the real Laravel Sanctum `Guard`/`Sanctum` source at the exact locked version, and every test-support trait involved) **plus real local execution** of a disposable PHPUnit evidence harness (`tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php`, committed on this branch as an evidence artifact per this repo's GAP-043 precedent) against SQLite (this repo's default `phpunit.xml` testing driver). No production system, secret, or database was touched. No app/middleware/guard/test-support code was modified — this document and the disposable evidence test are the only new files.
**Scope discipline:** This is a Gate-1 forensic audit only. No remediation was implemented. GAP-050 (MySQL transaction isolation / process isolation) was not touched, re-opened, or referenced beyond this document's own citation of it as a separate, already-closed Work ID. No RBAC/Tenant work was expanded.

**Lead inherited from GAP-050 (verified independently, not assumed true):** "Some tests that claim to exercise Bearer-token/Sanctum authentication may actually succeed through residual `web` session authentication rather than the token being presented." This audit confirms the *mechanism* is real and reproducible, but finds **no currently-committed test in the repository that is actually exploiting it** (see §4 and §6).

---

## 1. Sanctum version and its own documented testing guidance

`composer.json:18` pins `"laravel/sanctum": "^4.0"`; `composer.lock` resolves it to a 4.x release identical across every sibling worktree checked (`vendor/laravel/sanctum` content byte-identical, confirmed via a `composer.lock` diff against a same-SHA sibling worktree before reusing its `vendor/` directory locally — no network install was needed or performed).

Laravel Sanctum ships two first-class testing mechanisms, both present and in active use in this repo:

1. **`Laravel\Sanctum\Sanctum::actingAs($user, $abilities, $guard = 'sanctum')`** — the framework's own recommended helper for testing **ability/authorization logic**. Per `vendor/laravel/sanctum/src/Sanctum.php:70-93`, it mocks a `PersonalAccessToken`, attaches it to the user via `withAccessToken()`, and calls `app('auth')->guard($guard)->setUser($user)` directly. **This never touches the HTTP `Authorization` header or `PersonalAccessToken::findToken()` at all** — by Sanctum's own design, it is a shortcut for testing "does my controller/policy accept this ability," not "does my token-transport layer work."
2. **Real end-to-end token issuance + a real `Authorization: Bearer <token>` header** — obtained via `HasApiTokens::createToken()` (Sanctum's own token-issuance API) and presented as a genuine bearer header on a request with **no** `actingAs()` call in the same test method. This is the only mechanism that actually exercises `Laravel\Sanctum\Guard::__invoke()`'s token-lookup branch (`vendor/laravel/sanctum/src/Guard.php:40-61`).

Both patterns are used in this repo (see §4). Neither is a repo-invented ad hoc mechanism — both are Sanctum's own documented patterns, used correctly for what each is meant to prove. `WithoutMiddleware` is not used anywhere in a way relevant to Sanctum-guarded routes (checked, no matches on Sanctum-adjacent tests).

## 2. The actual guard-resolution mechanism (root of the inherited lead)

`config/auth.php:15-17` sets `'defaults' => ['guard' => 'web']`. `config/auth.php:37-46` defines `'api' => ['driver' => 'sanctum', 'provider' => 'users']`. `config/sanctum.php:36` sets `'guard' => ['web']` — **this is Sanctum's own shipped default value, unmodified by this repo.**

`vendor/laravel/sanctum/src/Guard.php:30-38` (`Guard::__invoke()`):

```php
foreach (Arr::wrap(config('sanctum.guard', 'web')) as $guard) {
    if ($user = $this->auth->guard($guard)->user()) {
        return $this->supportsTokens($user)
            ? $user->withAccessToken(new TransientToken)
            : $user;
    }
}
// ... only falls through to real bearer-token lookup if the loop above found nothing
```

This means: **for every route protected by `auth:sanctum` in this repo, if `Auth::guard('web')->user()` resolves a user, that user authenticates the request — the Bearer token is never even read.** This is a documented, intentional Sanctum feature (it is what lets a first-party SPA using session cookies call `auth:sanctum` API routes without a token), not a misconfiguration unique to this repo.

## 3. Why this is NOT exploitable in real production traffic (production-risk assessment)

`app/Providers/RouteServiceProvider.php:34-36` registers `/api/*` (which is where every `auth:sanctum` route in this repo lives — see §5) with **only** `Route::middleware('api')`, never `'web'`. `app/Http/Kernel.php:30-45`'s `'api'` middleware group is `[SubstituteBindings, SecurityHeadersMiddleware, ErrorEnvelopeMiddleware]` — **no `StartSession`, no `EncryptCookies`, no session middleware of any kind.** `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful` is not registered anywhere in `app/Http/Kernel.php` (confirmed via repo-wide `grep`); a repo-authored `App\Http\Middleware\CustomEnsureFrontendRequestsAreStateful` class exists at `app/Http/Middleware/CustomEnsureFrontendRequestsAreStateful.php` but has **zero references anywhere else in the repository** (checked via `grep -rn` across the full tree) — it is dead, unregistered code, not part of any live middleware group.

Consequence: in a real production HTTP request to any `/api/*` route, `Auth::guard('web')->user()` has no session to resolve from (no session middleware ran, no cookie was decrypted) and will not resolve a user under any circumstance reachable by an external request. **The `sanctum.guard => ['web']` fallback is therefore inert in this repo's real request lifecycle.** This is a **test-fidelity-only risk class**, not a production authentication defect — the two risk classes the task asked to be kept distinct.

The only way `Auth::guard('web')->user()` resolves without a real session is inside a PHPUnit test process, where `Illuminate\Foundation\Testing\Concerns\MakesHttpRequests::actingAs($user)` (default guard, i.e. `'web'` per `config('auth.defaults.guard')`) calls `Auth::guard('web')->setUser($user)` directly in memory, and Laravel's `SessionGuard` caches that user on the guard **instance** for the rest of that test method's lifetime (the same PHP process / same service container is reused across every HTTP-like call inside one test method, unlike real stateless production workers).

## 4. Real execution: the disposable evidence harness and its results

`tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` (committed on this branch as an evidence artifact only — not a permanent regression test, not remediation, registers its own throwaway probe route in `setUp()` rather than touching any production route). Run locally against SQLite (`phpunit.xml`'s default testing driver):

```
$ ./vendor/bin/phpunit tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php --testdox
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.32

.....                                                               5 / 5 (100%)

Time: 00:05.165, Memory: 83.00 MB

Gap051Sanctum Web Guard Leak Evidence (Tests\Feature\Gap051SanctumWebGuardLeakEvidence)
 ✔ Web guard actingAs leak authenticates sanctum route with no token
 ✔ Missing bearer token without any web guard state is rejected
 ✔ Invalid bearer token without any web guard state is rejected
 ✔ Valid bearer token authenticates through sanctum itself
 ✔ Sanctum acting as also bypasses real token lookup by design

OK (5 tests, 8 assertions)
```

Result-by-scenario:

| # | Scenario | Setup | Bearer header sent | Actual result | Expected if token were authoritative |
|---|---|---|---|---|---|
| 1 | **The confirmed hazard** | `$this->actingAs($user)` (plain Laravel, web guard) | **none at all** | `200`, `user_id` = the acted-as user | `401` |
| 2 | Negative control | no `actingAs()` anywhere in the test method | none | `401` | `401` (correct) |
| 3 | Negative control | no `actingAs()` anywhere in the test method | garbage string | `401` | `401` (correct) |
| 4 | Positive control | no `actingAs()`, real `$user->createToken(...)->plainTextToken` | real token | `200`, `user_id` = token owner | `200` (correct, via real token lookup) |
| 5 | Sanctum's own official helper | `Sanctum::actingAs($user, ['*'])` | none | `200` | Sanctum's documented, intended behavior — not testing token transport by design |

**Scenario 1 is the deterministic proof requested in the task**: a test method that calls Laravel's own `actingAs()` and then hits a `auth:sanctum` route with **zero** `Authorization` header currently gets `200`, not `401`. This is not hypothetical — it is real, current, reproducible behavior of this repo's exact `config/auth.php` + `config/sanctum.php` + `app/Http/Kernel.php` + the exact locked Sanctum version, executed against the exact canonical SHA.

## 5. Blast-radius quantification

- **Guarded surface:** `auth:sanctum` appears in **54** middleware-group declarations across `routes/api.php`, `routes/api_zena.php`, `routes/api-simple.php`, `routes/legacy/api_v1.php` — effectively all of `/api/v1/*` and `/api/zena/*`, i.e. the entire token-guarded API surface described in `PROJECT_CONSTITUTION.md` Appendix A.1.
- **Tests using plain `actingAs(` (web guard) anywhere:** 117 files.
- **Tests using `Sanctum::actingAs()` (Sanctum's own ability-testing helper):** 12 files — `tests/Feature/RbacApiTest.php`, `GAP042Gate3Round2CorrectionsTest.php`, `GAP042Gate3Round1CorrectionsTest.php`, `SecurityPenetrationTest.php`, `GAP042RbacProductionFidelityTest.php`, `Deployment/TwoTenantIsolationEvidenceTest.php`, `Api/SecurityTest.php`, `Api/InvitationApiTest.php`, `Api/CompensationRouteInvariantTest.php`, `Api/ComprehensiveApiIntegrationTest.php`, `Api/AuthSensitiveRoutesProtectionTest.php`, `Invitation/InvitationCrossTenantTest.php`. None of these mixes plain `actingAs(` in the same file, so none is at risk of the Scenario-1 leak (verified via `grep -c` per file, zero plain-`actingAs` matches in all 12).
- **Tests presenting a real Bearer token via genuine `createToken()`:** 51 pre-existing files (52 with this audit's own disposable harness) — this is the population that actually exercises `Guard::__invoke()`'s token-lookup branch.
- **Files containing both a literal `Bearer ` string and an `actingAs(` call anywhere in the file (the candidate set manually inspected for the Scenario-1 anti-pattern):** 9 files — `RbacApiTest.php`, `GAP042Gate3Round2CorrectionsTest.php`, `GAP042Gate3Round1CorrectionsTest.php`, `GAP042RbacProductionFidelityTest.php`, `SecurityPenetrationTest.php`, `Crm/ServiceLineGateTest.php`, `Api/SecurityTest.php`, `Api/ComprehensiveApiIntegrationTest.php`, `Api/AuthSensitiveRoutesProtectionTest.php`. Eight of the nine are in the `Sanctum::actingAs()` set above (no plain `actingAs` present). The ninth, `tests/Feature/Crm/ServiceLineGateTest.php`, was read in full: its two plain-`actingAs()` calls (lines 135, 148) exercise **web** routes (`operator.crm.quotes.send`) in their own dedicated test methods, and its `authHeaders()` helper (lines 116-126, using real `createToken()`) is used only in separate test methods that call `postJson()` directly with no preceding `actingAs()` in that same method. **No leak in this file.**
- **`tests/Traits/AuthenticatesUsers.php`:** produces genuine non-Sanctum JWTs via `src/RBAC/Services/AuthService.php::createTokenForUser()` (a real `firebase/php-jwt`-style token, unrelated to `personal_access_tokens`). Checked for live callers repo-wide (`grep -rl` for the trait's `use` statement and for each of its public methods outside its own definition): **zero test files consume this trait.** It is dead code — a latent risk if reused later without realizing a JWT will not satisfy `PersonalAccessToken::findToken()`, but it poses no currently-active test-fidelity risk.
- **`tests/Feature/Api/ChangeRequestApiTest.php`'s locally-defined `generateJwtToken()`** (line 571) is misleadingly named but its body is `$user->createToken('change-request-tests')->plainTextToken` — a **real** Sanctum token, not a JWT. Naming debt only, not a defect.

**Conclusion: 0 currently-committed tests were found to be false-green via the Scenario-1 mechanism.** The hazard is real, executable, and confirmed by direct reproduction (§4), but it is a **live landmine in the test harness that no committed test has yet stepped on** — every test file that both claims Bearer/Sanctum coverage and hits an `auth:sanctum` route was individually traced to either (a) using Sanctum's own `Sanctum::actingAs()` correctly for ability-testing, (b) using a real `createToken()`-issued Bearer header with no confounding `actingAs()` in the same method, or (c) using `actingAs()` only against unrelated web-session routes in dedicated test methods. This audit found no case of (a)/(b)/(c) being violated within a single test method for any Sanctum-guarded assertion.

## 6. Test-fidelity defect vs. production authentication defect — explicit distinction

- **Test-fidelity defect (CONFIRMED, latent):** Any *future* test that calls `$this->actingAs($user)` and then, in the same test method, asserts anything about Bearer-token behavior (missing/invalid/revoked token → expect 401; or "does the token itself grant access") against a `auth:sanctum` route will silently pass regardless of the token, because the web guard resolves first. This is real and reproducible today (§4, Scenario 1). Nothing currently committed does this, but nothing currently prevents it either — see §7 remediation options.
- **Production authentication defect:** **NOT FOUND.** `/api/*` carries no session middleware in this repo (§3), so `Auth::guard('web')->user()` cannot resolve for a real external request to any Sanctum-guarded endpoint. The `sanctum.guard => ['web']` config value is Sanctum's own shipped default and is inert on this repo's actual `/api/*` middleware stack. No change to any guard, middleware, or auth semantics is proposed or warranted by this finding.

These two are not conflated anywhere in this document, per the task's explicit instruction.

## 7. Secondary finding (naming/documentation debt, not a defect)

Test-helper names and docblocks across `tests/Traits/AuthenticationTestTrait.php` (`generateJwtToken`, `getAuthHeaders`, `assertValidJwtToken`, "Test JWT authentication") and `tests/Feature/Api/ChangeRequestApiTest.php` (`generateJwtToken`) describe "JWT" tokens, but the actual live login path they exercise (`POST /api/auth/login` → `app/Http/Controllers/Api/AuthenticationController::login()` → `app/Services/AuthenticationService::generateToken()` at line 114: `$user->createToken($tokenName, ['*'], $expiresAt)`) issues **real Laravel Sanctum personal-access tokens**, not JWTs. A second, genuinely-JWT-producing implementation exists (`src/RBAC/Services/AuthService.php::createTokenForUser()`/`generateToken()`, using `reloadJwtConfig()`) but is consumed only by the dead `tests/Traits/AuthenticatesUsers.php` trait (§5). This is stale naming left over from what was presumably an earlier, since-replaced JWT-based auth system — worth a documentation/naming cleanup, but it does not itself cause any false-green result, since every live caller of the misleadingly-named helpers actually receives and sends a real Sanctum token.

## 8. Remediation candidates (Gate-2 direction only — none implemented)

1. **CI/lint guard test (regression-prevention, matches the task's suggested direction):** a static test (in the spirit of this repo's existing `tests/Feature/DebugRouteDocumentationInvariantTest.php`, `RouteMiddlewareSecurityContractTest`, `ZenaRouteSurfaceInvariantTest` pattern) that parses test files under `tests/Feature/Api/**` and `tests/Feature/Zena/**`, flags any test *method* that both calls `$this->actingAs(` and asserts a `401`/token-related expectation against a route resolvable to an `auth:sanctum` middleware group, and fails CI. Tradeoff: static parsing of PHP test-method bodies is nontrivial (needs an AST walk, not just line-based grep) but gives permanent, cheap prevention.
2. **`tests/TestCase.php`-level runtime guard:** override `actingAs()` in the shared base `TestCase` to record that the web guard was set, and add a helper assertion (`assertSanctumTokenAuthenticated($response)`) that test authors must call in Sanctum-token tests, which fails loudly if `Auth::guard('web')` has a cached user at assertion time. Tradeoff: requires every Bearer-token test to adopt the new helper; more invasive than option 1 but catches the defect at the point of use rather than via static analysis.
3. **Do nothing beyond documentation** (record this Gate-1 finding, rely on the population trace in §5 showing zero live instances today, revisit only if a new test introduces the pattern). Tradeoff: cheapest, but leaves the landmine live for any future contributor — proportionate only if Owner judges the current zero-live-instance finding sufficient given the constitution's priority ordering (Tier 2 security/RBAC/auditability vs. this being a **test-harness-only**, non-production-exploitable issue).
4. **Rename the misleading "JWT" test-helper identifiers (§7) to reflect real Sanctum usage**, and either wire up or delete the dead `AuthenticatesUsers` trait, as a small independent documentation-debt cleanup — not required to close the Scenario-1 hazard, but reduces confusion for whoever eventually authors option 1 or 2's guard test.

## 9. Recommended Gate-2 direction

Recommend proceeding to Gate 2 to design **Option 1** (static CI guard test) as the primary regression-prevention mechanism, informed by this repo's precedent of similar invariant tests for route/middleware contracts, with Option 4's naming cleanup folded in as a low-risk companion change if Owner authorizes it in the same Gate-2 design. Recommend **not** pursuing Option 2 (runtime `TestCase` override) as primary, since it requires migrating all 51+ existing Bearer-token tests to a new helper for no defect currently found to justify that migration cost; it remains available as a fallback if Option 1's static analysis proves too fragile at Gate-2 design time.

## 10. What is explicitly NOT claimed by this document

- No claim that any currently-committed, currently-green test is a false positive today (verified negative — see §5's population trace).
- No claim of a production authentication vulnerability (see §6).
- No claim about GAP-050's MySQL/process-isolation scope — unrelated mechanism, not reopened, not referenced beyond acknowledging it as the (unrelated) originating Work ID for this lead.
- No architecture, guard, or middleware change is proposed or requested at this Gate.
