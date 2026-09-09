---
work_id: GAP-051
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-051/02-design.md
---

# GAP-051 Gate 2 — Sanctum Bearer-Token Test-Fidelity Regression-Prevention Design

Status: Gate 2 design/decision packet. No implementation in this document
beyond illustrative snippets. Base: canonical `main`
`e2013751f41a3bb367168705e773f01bb434a641` (GAP-051 Gate 1, PR #306,
merged).

## 0. Recap of the Gate-1 finding (binding facts, not re-litigated here)

- Laravel Sanctum's own shipped default (`config/sanctum.php:36`,
  `'guard' => ['web']`, unmodified by this repo) makes
  `Laravel\Sanctum\Guard::__invoke()` check the `web` session guard
  **before** ever reading a Bearer token.
- In a PHPUnit test, `$this->actingAs($user)` (Laravel's own web-guard test
  helper) sets a user directly on the `web` `SessionGuard` instance held by
  the container's `AuthManager`. That instance is retained by the
  `AuthManager`'s `$guards` cache for the rest of the test method, so a
  later request to an `auth:sanctum` route resolves via that leftover
  `web`-guard state even with **zero** Authorization header.
- This is reproduced and proven with a real, executed disposable evidence
  test: `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` (5/5
  scenarios pass; Scenario 1 is the confirmed hazard, Scenarios 2-5 are
  controls).
- Zero currently-committed tests in this repo are actually exploiting the
  hazard (117 `actingAs(` files, 12 `Sanctum::actingAs()` files, 51+ real
  `createToken()` files traced; the one file mixing patterns,
  `tests/Feature/Crm/ServiceLineGateTest.php`, keeps them in separate test
  methods and is safe).
- No production exposure: `/api/*` carries only the `api` middleware group
  (`app/Http/Kernel.php:40-44` — `SubstituteBindings`,
  `SecurityHeadersMiddleware`, `ErrorEnvelopeMiddleware`), with no
  `StartSession`/session-cookie middleware, so `Auth::guard('web')->user()`
  can never resolve on a real stateless request today. This is bound to
  the **current** middleware topology (see §6, Constraint F).

## 1. Categories that must not be conflated (Owner Constraint C)

| Category | What it means | Hazard? |
|---|---|---|
| `Sanctum::actingAs($user, $abilities)` | Laravel/Sanctum's own documented ability-testing shortcut. Directly sets the user on the `sanctum` guard, bypassing real token lookup **by design**. | Not a hazard — valid, intentional, for authorization/ability tests, not transport tests. |
| Real Bearer-token transport testing | A test that claims to exercise the actual HTTP `Authorization: Bearer <token>` header and Sanctum's real `PersonalAccessToken::findToken()` lookup through the `sanctum` guard. | The category GAP-051 is about. Must not be satisfiable by any web-guard fallback. |
| Plain `$this->actingAs($user)` | Laravel's web-guard test helper. Valid for testing `web`-guarded routes/session-based flows. | Hazard **only** when it silently backstops a test claiming to prove category 2, in the same test method/setUp chain. |

Any Gate-2 mechanism must be able to tell these three apart. A mechanism
that merely detects "does this test call `actingAs()` anywhere" without
regard to what it's asserting would produce false positives against
legitimate `web`-guard tests and legitimate mixed-guard test classes (this
repo already has one: `ServiceLineGateTest`).

## 2. Alternatives compared

### Option 1 — AST/static invariant detection (custom PHPStan rule or AST-walking script)

Flags a test method that calls a Bearer-transport-claiming helper (or
asserts on a `sanctum`-guarded route with a real `Authorization` header)
**and** also calls plain `$this->actingAs()` in the same method or in a
shared `setUp()`/trait it uses.

**Pros**
- Catches the hazard before the test even runs (fails at `phpstan analyse`
  or a dedicated CI step, not at runtime).
- Can scan the whole test suite in one pass, including test methods nobody
  runs in CI locally before pushing.

**Cons**
- This repo has **zero** existing custom PHPStan rules (`phpstan.neon` +
  `phpstan-baseline.neon` only use stock/community rules — verified via
  repo search). Introducing a custom `Rule` class is new tooling surface,
  with its own test-the-rule burden and PHPStan-version coupling
  (`docs/engineering/phpstan-enforcement-options.md` already documents
  this repo's caution around PHPStan convention debt).
- Pure heuristic: it can only recognize "these two calls appear near each
  other" — it cannot verify that the Bearer-token path was **actually**
  exercised at runtime. A test could satisfy the AST pattern (no
  `actingAs()` present) yet still accidentally leak web-guard state via a
  shared trait, a parent `setUp()` several classes up, or dynamic dispatch
  the AST walker doesn't follow — false negatives are easy to construct.
- This is explicitly the "grep/lint" default the Owner asked not to
  default to (Constraint A/B).

**Verdict: rejected as the primary mechanism.** Considered as a
supplementary defense-in-depth layer in §4, not the core contract.

### Option 2 — Dedicated real-Bearer transport testing contract/helper

A `TestCase` trait method, e.g. `actingAsSanctumBearerToken(User $user,
array $abilities = ['*']): TestResponse` (or a `withSanctumBearerToken()`
request-builder), that:

1. Issues a **real** `$user->createToken(...)` (same mechanism
   `app/Services/AuthenticationService.php:114` uses for the production
   login endpoint) and captures the real `plainTextToken`.
2. **Purges any cached guard state** before sending the request, via
   Laravel's own public `AuthManager::forgetGuards()`
   (`vendor/laravel/framework/.../Illuminate/Auth/AuthManager.php`,
   confirmed present in this repo's pinned `laravel/framework: v12.63.0`).
   `forgetGuards()` clears the `AuthManager`'s internal `$guards` instance
   cache, so any `SessionGuard` a prior `actingAs()` call had populated
   with a cached user is discarded — the next `guard('web')` resolution in
   this test creates a **fresh** `SessionGuard` with no cookie, hence no
   user.
3. Sends the request with a real `Authorization: Bearer <token>` header
   and nothing else.
4. Optionally asserts post-hoc that the resolved user/ability matches the
   token's owner, so a caller can chain normal response assertions.

Illustrative sketch (not final Gate-3 code):

```php
trait AssertsSanctumBearerTransport
{
    protected function actingAsSanctumBearerToken(User $user, array $abilities = ['*']): static
    {
        $token = $user->createToken('test-bearer-transport', $abilities)->plainTextToken;

        // Structurally prevents any prior actingAs()/Sanctum::actingAs()
        // call in this test method from leaking into this request: discards
        // every cached guard instance so 'web' (and 'sanctum') are
        // re-resolved from scratch on the next call, with only the
        // Authorization header below able to satisfy the sanctum guard.
        app('auth')->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }
}
```

A test would then write:
`$this->actingAsSanctumBearerToken($user)->getJson('/api/v1/...')`
— structurally, **no** amount of a prior `actingAs()` call earlier in the
same method can survive `forgetGuards()`, because the leak mechanism
(a cached `SessionGuard` instance with `$user` set) is the exact thing
`forgetGuards()` discards.

**Pros**
- Positive contract, not a heuristic scan (Constraint B) — it does not
  need to know what a test author did earlier; it structurally guarantees
  a clean guard slate for **this** request.
- Uses only stable, public Laravel API (`forgetGuards()`,
  `createToken()`), no monkey-patching, no internal event coupling.
- Cheap: one trait, no new CI job, no new tooling dependency.
- Directly usable as the Gate-3 replacement for the Gate-1 evidence
  harness's "positive" scenarios (4 and partially 5).
- Self-documenting: a reviewer sees `actingAsSanctumBearerToken()` in a
  diff and immediately knows "this test is claiming real Bearer
  transport," making category confusion (Constraint C) visible at the call
  site instead of buried in prose comments.

**Cons**
- Only protects tests that **use the helper**. A test author who keeps
  writing raw `$this->withHeaders([...])->getJson(...)` by hand, ignoring
  the helper, gets no protection — this is an adoption/convention problem,
  not a technical gap in the helper itself.
- Does not, by itself, stop someone from calling `actingAs()` **after**
  the helper (order matters) — needs a documented convention ("call this
  last") plus the defense-in-depth layer in §4 to catch the ordering
  mistake.
- `forgetGuards()` is a real but slightly obscure Laravel API; future
  Laravel major-version upgrades could in principle change its semantics
  (low risk — it has been stable since guard caching was introduced).

### Option 3 — Test-only runtime/guard-resolution assertion mechanism

Instrument `Auth::guard()` resolution during testing (e.g. a
`TestCase`-registered listener on guard resolution, or wrapping
`AuthManager` in testing to record which named guard actually authenticated
each request) and assert, per test, that a request to an `auth:sanctum`
route resolved via the `sanctum` guard specifically, throwing/failing loud
if it resolved via `web`.

**Pros**
- In principle the most airtight: it observes the **actual** runtime
  resolution path rather than structurally preventing contamination or
  scanning source text, so it would also catch hazards Option 2 doesn't
  structurally close (e.g., contamination introduced by a helper other
  than `actingAs()` that also caches a user on `web`).

**Cons**
- No clean instrumentation point ships with Laravel/Sanctum for "which
  guard resolved this specific request." Reliable implementation would
  need either: (a) swapping in a decorated `AuthManager`/`Guard` in the
  test container to intercept `user()` calls per guard — invasive, and the
  swap itself risks behaving differently from the real Sanctum guard chain
  it's supposed to be verifying, undermining the very fidelity it's meant
  to test; or (b) hanging off `Illuminate\Auth\Events\Authenticated`,
  which fires once a guard's `user()` resolves truthy — usable, but its
  payload does not cleanly expose "which named guard among several
  configured on `sanctum.guard` actually matched" without additional
  bookkeeping, and firing/ordering guarantees are not part of Sanctum's
  or Illuminate's tested public contract.
- Higher fragility across Sanctum/Laravel version bumps than a public,
  documented API like `forgetGuards()` — this is exactly the kind of
  "test-only runtime magic" that tends to silently stop firing after a
  dependency upgrade with no CI signal, until someone notices tests got
  quieter.
- Higher implementation and review cost for marginal additional coverage
  over Option 2 in this repo's actual test population (Gate 1 already
  traced 100% of Bearer/Sanctum-claiming tests and found the leak requires
  `actingAs()` specifically — no other web-guard-caching helper is in use
  today).

**Verdict: rejected as the primary mechanism**, on cost/fragility versus
marginal-coverage grounds, not because it is technically unsound. Noted as
a candidate for future hardening if a second, structurally-different
contamination vector is ever found that Option 2 doesn't close (see §7).

### Option 4 — Documentation-only / no-code baseline

Write down the hazard (a section in `CONTRIBUTING`/`CLAUDE.md`/a testing
guide: "never call `actingAs()` in a test that also claims to test
Sanctum Bearer transport"), rely on human/agent code review to catch
violations.

**Pros**
- Zero implementation cost, zero new test/CI surface, zero risk of the
  mechanism itself having bugs.

**Cons**
- This is precisely the status quo that let the hazard exist undetected
  until an unrelated GAP-050 investigation stumbled on it. A documented
  hazard with 234+ existing Bearer/Sanctum-claiming test files and an
  unbounded number of future ones is not something code review reliably
  catches — Gate 1 itself only found this by accident, not by review.
- No regression signal at all: if a future test reintroduces the pattern,
  nothing fails, nothing turns red, CI stays green, and the false-green
  state Gate 1 was opened to prevent recurs exactly as before.

**Verdict: rejected**, exactly as the Owner expected it would be, but
evaluated honestly per Constraint A: it is the cheapest option and would
be adequate only if the hazard were extremely unlikely to recur or
extremely cheap to catch by eye — neither is true here (Sanctum's
guard-order behavior is not obvious from reading a test, and the
population is large and growing).

## 3. Recommended architecture

**Primary: Option 2** (`AssertsSanctumBearerTransport` trait /
`actingAsSanctumBearerToken()` helper), **with Option 1 kept as a narrow,
existing-pattern-consistent defense-in-depth net**, described below —
not as the primary mechanism, and not a new PHPStan rule, but a PHPUnit
static-introspection test in the same family as the two static-invariant
tests this repo already ships (`tests/Feature/RouteMiddlewareSecurityContractTest.php`,
`tests/Feature/Zena/ZenaRouteSurfaceInvariantTest.php`), which scans test
source only for the **specific already-confirmed pattern** (a test method
calling both the new Bearer-transport helper and plain `actingAs()`) as a
tripwire against someone hand-rolling the vulnerable pattern instead of
using the helper. This is deliberately narrow — it is not asked to detect
every conceivable contamination vector (that would repeat Option 1's
false-negative problem at full scope); it exists only to catch the one
concrete anti-pattern Option 2's helper doesn't structurally prevent
(calling `actingAs()` in the same method, in either order, without ever
routing through the helper).

**Correction (this revision): a third layer is added.** §2/§4's original
combination (helper + static tripwire) left an acknowledged residual
false-negative — a test author who never references the helper's name at
all (raw `withHeaders(['Authorization' => ...])` alongside `actingAs()`)
is invisible to both the structural helper (never invoked) and the static
tripwire (nothing to match on). §4.2 below evaluates a low-complexity
runtime guard-state check in `Tests\TestCase`'s request-dispatch path as
a closer for exactly this gap, and recommends adding it as a **third**
defense-in-depth layer: the helper structurally prevents the common case,
the static tripwire catches the specific two-call anti-pattern, and the
new runtime check catches the raw hand-rolled case regardless of which
helper (if any) a test used to get there. See §4.2 for the full
comparison and verdict.

Rejected as primary: Option 1 alone (heuristic, no runtime proof, new
tooling surface), Option 3 alone (higher fragility/cost for coverage this
repo's traced test population doesn't currently need), Option 4 alone
(no regression signal, restates the status quo that produced GAP-051).

### Why this satisfies Constraints A-G

- **A**: four approaches compared above, not defaulted to grep.
- **B**: the primary mechanism (Option 2) is a positive contract — a test
  either used the helper (and is therefore structurally guaranteed a clean
  guard slate) or it didn't; nothing about it depends on inferring intent
  from surrounding source text.
- **C**: `Sanctum::actingAs()` is untouched and unflagged (Gate 1's own
  Scenario 5 documents it as intentionally bypassing token lookup — that
  stays true and is not treated as a hazard). Real Bearer-token transport
  testing is exactly what the new helper produces. Plain `actingAs()`
  remains fully legal for `web`-guard tests; it only becomes a build
  failure when it appears alongside the new helper's call in the same test
  method (the §4.1 tripwire), i.e., when a test is trying to claim both
  things at once.
- **D**: no change to `config/sanctum.php`, `config/auth.php`,
  `app/Http/Kernel.php`, any middleware, or any guard registration.
  Everything proposed lives under `tests/`.
- **E**: explicit lifecycle decision in §5.
- **F**: explicit topology tripwire in §6.
- **G**: JWT-naming debt explicitly excluded from this Gate-2 scope in
  §8.

## 4. Defense-in-depth layers (secondary and tertiary)

### 4.1 Static tripwire (secondary, narrow-scope Option-1 usage)

A PHPUnit test (not a PHPStan rule — matching this repo's existing
static-invariant-test convention rather than introducing new PHPStan
tooling) that scans `tests/**/*Test.php` source for any test **method**
that contains a call to `actingAsSanctumBearerToken(` (or whatever the
Gate-3-finalized helper name is) **and** a call to `->actingAs(` (Laravel's
plain helper, not `Sanctum::actingAs(`) within the same method body. This
is a narrow, single-pattern scan — it does not attempt general contamination
detection (that remains Option 3's rejected, broader ambition). Its only
job is to make it loud and immediate if someone hand-writes the exact
anti-pattern Gate 1 found, whether or not they also happen to use the new
helper incorrectly.

As acknowledged honestly in §7 (and by the original version of this
packet), this static tripwire has a real false-negative: a test that
never references the helper's name at all is invisible to it. §4.2
evaluates a mechanism that closes that specific gap.

### 4.2 Runtime guard-state check (tertiary — new in this correction)

**The gap being closed.** Neither Option 2's helper (never invoked) nor
§4.1's tripwire (nothing to pattern-match — the helper's name never
appears) catches a test author who ignores the new helper entirely and
hand-writes:

```php
$this->actingAs($user);
// ... later in the same method, no reference to the helper anywhere ...
$this->withHeaders(['Authorization' => 'Bearer ' . $token])->getJson('/api/v1/...');
```

This is not a hypothetical — it is the literal shape of Gate 1's
Scenario-1 hazard, just hand-written instead of routed through any
helper. The original version of this Gate-2 packet named this gap in §7
and accepted it as residual without seriously evaluating a concrete
automatic alternative. That is the defect this correction fixes.

**Evaluated mechanism.** In `Tests\TestCase` (which every feature test
already extends), check — immediately before dispatching any request that
carries a Bearer `Authorization` header — whether the `web` guard (the
confirmed leak vector; more generally, any guard in `config('sanctum.guard')`'s
fallback chain other than `sanctum` itself) is already authenticated from
cached test state at the moment of dispatch:

```php
// Illustrative sketch only — not final Gate-3 code.
public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
{
    $this->guardAgainstGap051BearerContamination($server);

    if ($this->shouldAutoAppendCsrfToken() && $this->shouldAppendCsrfToken($method, $server)) {
        $parameters = $this->ensureCsrfToken($parameters);
    }

    return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
}

private function guardAgainstGap051BearerContamination(array $server): void
{
    $authHeader = $server['HTTP_AUTHORIZATION'] ?? '';

    if (!str_starts_with($authHeader, 'Bearer ')) {
        return;
    }

    foreach (array_merge(['web'], (array) config('sanctum.guard', [])) as $guardName) {
        if ($guardName === 'sanctum') {
            continue;
        }

        if (Auth::guard($guardName)->check()) {
            throw new \RuntimeException(sprintf(
                'GAP-051: request carries a Bearer Authorization header while '
                . 'guard [%s] is already authenticated from cached test state. '
                . 'This is the confirmed GAP-051 web-guard/Bearer-transport '
                . 'contamination hazard. Use actingAsSanctumBearerToken() (which '
                . 'calls forgetGuards() before issuing its request) instead of '
                . 'mixing actingAs() with a hand-written Bearer header, or call '
                . 'app(\'auth\')->forgetGuards() yourself if this is a deliberate '
                . 'mixed-auth test.',
                $guardName
            ));
        }
    }
}
```

This lives entirely in the existing request-dispatch override point —
`Tests\TestCase::call()` is **already overridden today** (for CSRF-token
auto-injection, see the top of `tests/TestCase.php`), so adding a second,
independent check to the same override point is not a new pattern for
this codebase; it extends a convention already in production use here,
not an invasive new one.

**Comparison against Option 2 + §4.1:**

| Dimension | Helper + static tripwire (§2/§4.1) | Runtime guard-state check (§4.2) |
|---|---|---|
| Fidelity vs. raw hand-rolled hazard | Misses it entirely (no helper call, no tripwire match) | Catches it — the check runs at request-dispatch time based on actual cached guard state, independent of which helper (if any) was used to get there |
| Mechanism type | Structural prevention (helper) + source-text pattern match (tripwire) | Runtime state assertion at the exact moment of risk |
| Public API relied on | `AuthManager::forgetGuards()` | `Auth::guard($name)->check()` (core Auth facade, arguably even more stable/central than `forgetGuards()`) plus overriding `TestCase::call()` (already done in this repo for CSRF) |
| New tooling surface | One trait, one PHPUnit static-scan test | One additional private method in an already-overridden `TestCase::call()` — no new file, no new CI job |
| False-positive risk | §7's existing analysis (helper + `actingAs()` in the same method) | A test that *deliberately* keeps a cached `web`-session **and** intentionally sends a Bearer header in the same request (a genuine mixed-auth scenario) would trip this check. See below. |

**False positives.** Could a legitimate test intentionally have both a
Bearer header and a still-cached `web` session in the same request? A
test using the new helper is unaffected — `actingAsSanctumBearerToken()`
calls `forgetGuards()` before sending its request, so by the time the
runtime check runs, no guard has cached state, and there is no false
positive for any helper-based usage. The remaining exposure is a
hand-written test that *deliberately* wants a real Bearer header sent
alongside an already-authenticated `web` guard, to assert some specific
"stateful cookie present but Bearer takes precedence" behavior. This
repo's traced test population (Gate 1: 117 `actingAs(` files, 12
`Sanctum::actingAs()` files, 51+ real `createToken()` files) contains no
such case today — the one file that mixes patterns
(`tests/Feature/Crm/ServiceLineGateTest.php`) already keeps them in
separate test methods. Judged the same way §7 already judges the
static tripwire's analogous false positive: forcing that scenario to be
made explicit (call `forgetGuards()` deliberately, or split into two test
methods) is the correct outcome, not a defect — it is exactly the kind
of ambiguity Gate 1 showed is dangerous to leave implicit.

**Framework-version stability.** `Auth::guard($name)->check()` is core,
long-stable Laravel Auth facade surface — no less stable than
`forgetGuards()`, arguably more central and therefore less likely to
change. Overriding `TestCase::call()` a second time is not a new risk
category: this repo's `Tests\TestCase` already overrides `call()` for an
unrelated cross-cutting concern (CSRF token injection), so a Laravel
upgrade that broke this pattern would already be breaking an existing,
in-production convention here, not a new one this design introduces.

**Verdict: RECOMMENDED as a third defense-in-depth layer**, added
alongside (not replacing) the helper and the §4.1 static tripwire:

1. The helper (§2) structurally prevents the common case for any author
   who adopts it.
2. The §4.1 static tripwire catches the specific two-call anti-pattern
   (helper referenced, but `actingAs()` also present) for authors who
   partially adopt the helper incorrectly.
3. The §4.2 runtime guard-state check catches the raw hand-rolled case —
   the exact residual gap the original version of this packet admitted
   and did not seriously evaluate closing — regardless of which path (if
   any) got there, at negligible implementation cost (one private method
   in an already-overridden test-infrastructure hook) and no meaningful
   new false-positive exposure given this repo's actual traced test
   population.

No disqualifying flaw was found: the mechanism is cheap, uses stable
public API, extends an existing override point rather than introducing a
new one, and its only false-positive class (deliberate mixed-auth tests)
does not exist in this repo today and is the same kind of "flag and force
explicitness" trade-off already accepted for the static tripwire in §7.

## 5. Evidence-harness lifecycle decision (Constraint E)

**Decision (corrected this revision): keep all five scenarios permanently
(nothing removed), but stop calling the whole file one undifferentiated
"permanent regression test" — split it by what each scenario actually
proves, because two fundamentally different kinds of claims were being
conflated.**

The original version of this packet called the entire renamed file a
single "permanent regression test." That blurred a real distinction:

- **Scenario 1** (`actingAs()` + no header → currently 200) is a
  **framework-characterization/canary test**, not a security regression
  contract. It intentionally asserts the *hazardous* framework behavior
  keeps happening — it must keep returning 200 forever, specifically
  because Constraint D forbids ever "fixing" Sanctum's web-first
  guard-check order at the config/guard level. Calling this a "regression
  test" is misleading: nothing has "regressed" if it keeps passing, and a
  reviewer skimming CI green for this file could easily mistake its
  permanent-pass state for "GAP-051 is fixed" when the opposite is true —
  it is documenting that the underlying hazardous mechanism is still
  exactly as Gate 1 found it, unpatched by design.
- **Scenarios 2, 3, and 5** are controls/documentation of the non-hazard
  categories from §1 (kept as-is).
- **Scenario 4** (rewritten to use `actingAsSanctumBearerToken()`) and the
  **new tests from §4.2 and §9** (the `forgetGuards()` structural proof,
  the §4.1 tripwire-fires-on-reintroduction proof, the §4.2 runtime-check
  proof, the §6 topology/behavioral proof) **are** genuine GAP-051
  security/test-fidelity regression contracts — they must FAIL if unsafe
  test contamination, or a missing defense-in-depth layer, is ever
  reintroduced.

**Structural decision: split into two files**, since the two kinds of
tests have opposite pass/fail meanings and mixing them in one file under
one generic "regression test" label is worse for maintenance than keeping
that distinction explicit in the filesystem:

- `tests/Feature/SanctumWebGuardCharacterizationTest.php` — Scenario 1
  only, docblock explicit that this is an intentional, permanent
  framework-behavior canary that must keep passing, and a change to red
  here signals an *upstream Sanctum/Laravel behavior change* to
  investigate, not a GAP-051 regression to "fix" in this repo.
- `tests/Feature/SanctumBearerTransportGuardContractTest.php` — Scenarios
  2, 3, 4 (rewritten), 5, plus the new structural/runtime/topology
  regression tests from §4.2/§6/§9, docblock explicit that failures here
  ARE GAP-051 regressions requiring investigation.

No scenario is deleted or weakened; this is a file-level reorganization
plus a docblock/naming correction so "permanently green" and "must never
regress" are never conflated under a single ambiguous label again.

## 6. Future-topology regression safeguard (Constraint F)

**Correction (this revision):** the original version of this section
pinned the *exact* full `App\Http\Kernel::$middlewareGroups['api']` array
(`SubstituteBindings`, `SecurityHeadersMiddleware`, `ErrorEnvelopeMiddleware`,
in that literal order). Re-reading `app/Http/Kernel.php` and
`app/Providers/RouteServiceProvider.php` confirms that reality as of this
correction, but exact-array pinning is unnecessarily brittle for the risk
this safeguard actually exists to guard against: it would fail loudly for
a harmless, unrelated addition to the `api` group (e.g. a new stateless
rate-limiting or observability middleware with zero session/auth
implications), forcing an unrelated future PR to "reassess GAP-051" when
nothing about GAP-051's risk has actually changed. No compelling reason
was found to keep exact-array pinning — the real risk this safeguard must
catch is narrower and better expressed directly.

**Revised safeguard — a risk-focused invariant, not a full-array pin:**

1. **Absence assertion.** Assert that no session/stateful-authentication
   middleware (`Illuminate\Session\Middleware\StartSession`,
   `Illuminate\Cookie\Middleware\EncryptCookies`, or any other
   session-cookie middleware) is present in
   `App\Http\Kernel::$middlewareGroups['api']`, rather than asserting the
   full exact composition. A harmless addition to the group (anything that
   is not session/stateful-auth middleware) no longer breaks this
   tripwire; only the one condition that actually reopens GAP-051's
   production-exposure question does. Failure message unchanged in spirit
   — cites GAP-051 by name and directs reassessment, not a silent
   assertion update:

   *"Session/stateful middleware (`StartSession`/`EncryptCookies`/...) was
   added to the `api` middleware group. GAP-051 Gate 1's 'no production
   exposure' finding was conditioned on `/api/*` carrying no such
   middleware (see docs/audits/2026-09-09-gap-051-...). This changes that
   finding's premise — do not silently update this assertion; open a
   follow-up Work ID to reassess GAP-051's production-exposure conclusion
   first."*

2. **Behavioral contract test (new, in addition to the static absence
   assertion).** Where feasible, test the actual risk directly rather than
   only an implementation detail: a session-authenticated / `web`-guard
   user, with **no** Bearer token, still receives `401` from a
   representative `auth:sanctum` API route, dispatched through the real,
   production-like `api` middleware stack (not a bespoke test-only route,
   and not with guard state forced/mocked). This tests whether `web`-guard
   state can leak into a real HTTP request cycle under the `api` group as
   it is actually wired today — the concrete question GAP-051 is about —
   rather than pinning an implementation detail that could change for
   unrelated reasons.

Together, (1) fails loudly and immediately if the topology assumption
itself is broken (a stateful middleware is added to `/api/*`), and (2)
independently proves the behavioral guarantee that assumption protects
(no session leak into a real `auth:sanctum` request) under the actual
current stack — so the tripwire is anchored to risk, not to an incidental
array literal that unrelated future changes could trip for no reason.

## 7. False-positive / false-negative analysis of the recommended mechanism

**False positives (wrongly flags a valid test):**
- A test that uses `actingAsSanctumBearerToken()` and, *later in the same
  method*, calls `actingAs()` deliberately for an unrelated reason (e.g.,
  testing a mixed-auth edge case) would trip the §4.1 tripwire. This is
  judged an acceptable false positive: such a test should be split into
  two methods or the ordering made explicit and reviewed — the pattern
  itself is exactly what Gate 1 showed is dangerous to leave ambiguous, so
  erring toward "flag and force explicitness" here is intentional.
- `Sanctum::actingAs()` is never matched by the §4.1 regex (it specifically
  excludes `Sanctum::actingAs(`), so no false positive there.

**False negatives (wrongly misses a real hazard):**
- **Corrected in this revision.** The original version of this section
  admitted that a test author who never adopts the new helper at all, and
  instead hand-rolls a raw `withHeaders(['Authorization' => ...])->getJson(...)`
  call alongside `actingAs()`, is caught by neither Option 2 (no helper
  used, `forgetGuards()` never runs) nor the §4.1 tripwire (nothing to
  pattern-match, since the helper's name never appears) — and accepted
  this as residual without seriously evaluating a concrete automatic
  alternative. That gap is now closed: §4.2's runtime guard-state check,
  added as a third defense-in-depth layer, catches this exact case,
  because it inspects actual cached guard state at request-dispatch time
  regardless of which helper (if any) a test used to get there. This is
  no longer treated as an accepted residual gap; the runtime check exists
  specifically to close it.
- The one narrower residual false negative that remains even with all
  three layers: a *deliberate* mixed-auth test that calls
  `app('auth')->forgetGuards()` itself (or otherwise clears guard state)
  before sending a hand-rolled Bearer request alongside a since-cleared
  `actingAs()` call would not trip §4.2 (no guard is authenticated at
  dispatch time) and would not trip §4.1 (no helper name referenced). This
  is judged acceptable: it requires actively reproducing the helper's own
  safety mechanism (`forgetGuards()`) by hand while still not using the
  helper, which is a vanishingly narrow and self-defeating case — a test
  author who already knows to call `forgetGuards()` has, in effect,
  reimplemented the fix.
- A future contamination vector that is *not* `actingAs()` (e.g. some
  other test helper that also caches a user on a `web`-family guard)
  would be closed by all three layers independently: `forgetGuards()` in
  the helper itself purges *all* cached guards (not just ones set by
  `actingAs()` specifically), and §4.2's runtime check inspects guard
  state generically (any guard in `config('sanctum.guard')`'s fallback
  chain, not specifically `actingAs()`-shaped state) — so both are broader
  than the §4.1 tripwire's pattern-specific scope, which remains
  intentional per §3.

## 8. JWT-naming debt (Constraint G)

Explicitly **excluded** from this Gate-2's remediation scope. Gate 1's
finding (`generateJwtToken`/`assertValidJwtToken` helpers actually issue
real Sanctum tokens via `createToken()`, not JWTs) is a naming/debt issue,
not a false-green or security hazard — the underlying mechanism is
correctly Sanctum either way. No strong, low-risk reason was found to
bundle a rename into this Gate's scope: a rename touches an unrelated,
already-large helper-usage surface with its own review cost and no
regression-prevention benefit for the GAP-051 defect class. Recorded here
as separately-trackable debt for a future Work ID; not carried into Gate-3
acceptance criteria below.

## 9. RED/GREEN regression strategy

**RED (fails today, under the old/no-helper approach):** Write a test that
calls `$this->actingAs($user)` then makes a raw `withHeaders(['Authorization'
=> 'Bearer <valid-token>'])->getJson('/__gap051_probe')` (or an equivalent
real `auth:sanctum` route) and asserts, using the §4.1 introspection test's
underlying logic applied manually, that this pattern is undetectable today
— i.e., today there is no helper, no tripwire, and Scenario 1 already
proves the response is 200 regardless of whether a real token was even
checked. Concretely: the existing Gate-1 evidence Scenario 1 **is** the RED
proof — it demonstrates the false-green condition passes silently with
zero automated signal in the current (pre-Gate-3) repo state.

**GREEN (passes under the new approach):**
1. `actingAsSanctumBearerToken()` helper exists and Scenario 4 (rewritten,
   now in `SanctumBearerTransportGuardContractTest`) passes, proving real
   Bearer transport works end-to-end through the helper. **Strengthened
   positive proof (corrected this revision):** the rewritten Scenario 4
   must assert the authenticated context carries genuine Sanctum state —
   e.g. `$user->currentAccessToken()` returns a
   `Laravel\Sanctum\PersonalAccessToken` instance whose `id` matches the
   token issued by `createToken()` in the helper, and whose `abilities`
   match what was requested — not merely that the response's returned
   user ID matches the token owner. Matching user IDs alone is a weaker
   proof: it is also what the hazardous web-guard fallback would produce
   if it silently backstopped the request (Scenario 1 shows exactly this
   — a 200 with a resolved user despite no real token check). Asserting a
   genuine `PersonalAccessToken` instance with the expected token id and
   abilities proves the `sanctum` guard specifically — not any fallback
   guard — actually resolved the request.
2. A new test constructed as: call `$this->actingAs($user)` **then**
   `$this->actingAsSanctumBearerToken($otherUser)->getJson(...)` in the
   same method — asserting the response reflects `$otherUser` (the token
   owner) with `$otherUser->currentAccessToken()` resolvable as a real
   `PersonalAccessToken` matching the issued token id, not the
   `actingAs()` user, proving `forgetGuards()` actually purged the leaked
   state. This is the direct structural proof the fix works, independent
   of the §4.1 tripwire.
3. The §4.1 tripwire test itself, run against a **deliberately reintroduced**
   copy of the vulnerable pattern (a temporary fixture file, deleted after
   the assertion, or an inline string fixture rather than a committed
   file) fails loudly, proving the tripwire fires on the exact
   Gate-1-confirmed anti-pattern.
4. The §4.2 runtime guard-state check, exercised by a test that calls
   `$this->actingAs($user)` then issues a raw
   `$this->withHeaders(['Authorization' => 'Bearer ' . $rawToken])->getJson(...)`
   with **no** helper involved, is shown to throw the GAP-051
   contamination exception — proving it catches the exact hand-rolled
   case the original design left uncovered. A companion test confirms it
   stays silent for the same request issued via
   `actingAsSanctumBearerToken()` (no false positive on the helper path).
5. The §6 absence-assertion test passes against the current `api` group
   composition and is shown (by temporarily mutating the array value in
   the test, not the Kernel) to fail if `StartSession`/`EncryptCookies` is
   added — proving it isn't a no-op assertion. The §6 behavioral contract
   test independently passes today (a `web`-guard-authenticated user with
   no Bearer token gets `401` from a representative `auth:sanctum` route
   under the real `api` middleware stack), proving the actual risk is
   closed under the current topology, not just the array literal.

## 10. Gate-3 acceptance criteria

1. `AssertsSanctumBearerTransport` trait (or equivalently named) added
   under `tests/`, with `actingAsSanctumBearerToken()` implemented exactly
   as sketched in §2 Option 2 (or a reviewed equivalent using
   `forgetGuards()` for the structural purge) — no production code touched.
2. Evidence-harness split per §5: `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php`
   replaced by two files — a characterization/canary test containing only
   Scenario 1 (docblock explicit it is a permanent, intentional
   framework-behavior canary, not a regression contract), and a
   regression-contract test containing Scenarios 2/3/4(rewritten)/5 plus
   the new tests from items 3-4 below. No scenario deleted.
3. §4.1 tripwire test added, proven to fire against the Gate-1-confirmed
   anti-pattern (via a disposable fixture per §9.3) and to stay silent
   against the current committed test suite.
4. §4.2 runtime guard-state check added to `Tests\TestCase`'s
   request-dispatch path (or equivalent, reviewed override point),
   proven per §9.4 to throw on the raw hand-rolled anti-pattern with no
   helper involved, and proven to stay silent for the helper-based path
   and for the rest of the current committed test suite (no false
   positives introduced against existing tests).
5. §6 safeguard added per the corrected design: (a) an absence assertion
   that no session/stateful-authentication middleware is present in the
   `api` middleware group, with a failure message citing GAP-051 and
   directing reassessment rather than a silent assertion update; and (b) a
   behavioral contract test proving a `web`-guard-authenticated user with
   no Bearer token receives `401` from a representative `auth:sanctum`
   route under the real `api` middleware stack.
6. Rewritten Scenario 4 (and the new forgetGuards()-purge proof, §9.2)
   assert genuine Sanctum state — `currentAccessToken()` resolves to a
   `Laravel\Sanctum\PersonalAccessToken` matching the issued token's id
   and abilities — not merely a matching response user ID.
7. All new/modified tests pass on real MySQL parity per this repo's
   standard CI invocation; no existing test file's behavior changes except
   the evidence-harness split in item 2.
8. No changes anywhere under `config/`, `app/Http/Kernel.php`,
   `app/Http/Middleware/`, `app/Providers/RouteServiceProvider.php`, or any
   Sanctum/guard/auth registration.
9. JWT-naming debt (§8) is not touched; optionally, a one-line
   `OPERATIONAL_GAP_REGISTER.md`-style note recommending a future Work ID
   is acceptable but not required for Gate-3 closure.
10. PR body/commit messages cite this Gate-2 packet and record that Gate 3
    is test-fidelity-only, no production auth semantics changed (mirroring
    §11 below).

## 11. Production-semantics non-impact statement

This design makes **no** change to: `config/sanctum.php`,
`config/auth.php`, `app/Http/Kernel.php`'s middleware groups or aliases,
any controller, any route definition, `app/Providers/RouteServiceProvider.php`,
or any Sanctum/Illuminate guard/auth class. Every artifact proposed (the
transport-testing trait, the split evidence-harness files, the §4.1
static tripwire, the §4.2 runtime guard-state check added to
`Tests\TestCase`, and the §6 topology safeguard) lives entirely under
`tests/`. Real production requests continue to authenticate exactly as
they do today; the `forgetGuards()` call used in the proposed helper and
the `Auth::guard(...)->check()` call used in the §4.2 runtime check are
invoked only from test code, inside PHPUnit's in-process container, and
have no effect on any real HTTP request cycle (each real request already
gets a fresh application/container instance in production —
`forgetGuards()` is meaningful specifically because PHPUnit reuses one
container across assertions within a test method, which production never
does; the §4.2 check similarly only ever runs inside
`Tests\TestCase::call()`, which no production request path invokes).

## 12. Scope/files likely affected in Gate-3 implementation

- New: `tests/Support/AssertsSanctumBearerTransport.php` (or similar path
  matching this repo's existing `tests/` trait conventions) — the helper
  trait.
- Removed/split (per §5's corrected lifecycle decision):
  `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` replaced by:
  - New: `tests/Feature/SanctumWebGuardCharacterizationTest.php` —
    Scenario 1 only, docblock explicit this is a permanent, intentional
    framework-behavior canary, not a regression contract.
  - New: `tests/Feature/SanctumBearerTransportGuardContractTest.php` —
    Scenarios 2, 3, 4 (rewritten to use the helper and assert genuine
    `PersonalAccessToken` state per §9/§10 item 6), 5, plus the
    `forgetGuards()`-purge structural proof from §9.2.
  (Exact file names to be finalized in Gate 3.)
- New: a §4.1 tripwire test, e.g.
  `tests/Feature/SanctumBearerTransportAntiPatternTest.php`.
- Modified: `tests/TestCase.php` — add the §4.2 runtime guard-state check
  to the existing `call()` override (alongside the existing CSRF-injection
  logic), plus a dedicated test proving it fires on the raw hand-rolled
  anti-pattern and stays silent on the helper path and the rest of the
  current suite (e.g. `tests/Feature/SanctumBearerRuntimeGuardCheckTest.php`).
- New: §6 safeguard tests, e.g. added to or alongside
  `tests/Feature/Zena/ZenaRouteSurfaceInvariantTest.php` or as their own
  `tests/Feature/ApiMiddlewareGroupTopologyInvariantTest.php` (absence
  assertion) and `tests/Feature/ApiSanctumWebGuardNoLeakContractTest.php`
  (behavioral 401 contract test), exact names to be finalized in Gate 3.
- No other files. No production code, config, or route files.
