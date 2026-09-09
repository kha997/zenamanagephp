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
  method (the §4 tripwire), i.e., when a test is trying to claim both
  things at once.
- **D**: no change to `config/sanctum.php`, `config/auth.php`,
  `app/Http/Kernel.php`, any middleware, or any guard registration.
  Everything proposed lives under `tests/`.
- **E**: explicit lifecycle decision in §5.
- **F**: explicit topology tripwire in §6.
- **G**: JWT-naming debt explicitly excluded from this Gate-2 scope in
  §8.

## 4. Defense-in-depth tripwire (secondary, narrow-scope Option-1 usage)

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

## 5. Evidence-harness lifecycle decision (Constraint E)

**Decision: convert into a permanent regression test, not remove, not
merely replace.**

`tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php`'s five scenarios
map directly onto the three categories in §1 and are worth keeping exactly
because they are the only place in the repo that pins Sanctum's
guard-check-order behavior with a real executed assertion:

- Scenario 1 (`actingAs()` + no header → currently 200) is **kept
  unchanged as a documented, permanently-red-if-ever-"fixed"-elsewhere
  canary**: it is not something Gate 3 patches away (Constraint D forbids
  touching Sanctum guard config), so this assertion should keep passing
  forever, proving the underlying framework behavior — that Sanctum
  authenticates via `web` when it's cached — hasn't silently changed
  upstream. Its docblock will be updated to point at the new helper and
  clarify it is now a permanent regression/canary test, not merely
  Gate-1-disposable evidence.
- Scenarios 2 and 3 (negative controls) are kept as-is.
- Scenario 4 (real Bearer token, isolated) is **rewritten to use the new
  `actingAsSanctumBearerToken()` helper**, turning it into the first real
  consumer/regression test of the Gate-3 helper itself.
- Scenario 5 (`Sanctum::actingAs()`) is kept as-is — it documents the
  Category-1 (non-hazard) behavior from §1 and guards against a future
  Sanctum upgrade silently changing that helper's semantics.

The file is renamed from `Gap051SanctumWebGuardLeakEvidenceTest` to
`SanctumBearerTransportGuardContractTest` (or similar) in Gate 3 to reflect
its new permanent role, with its docblock rewritten to drop the "disposable
evidence, not a regression test" framing. No scenario is deleted.

## 6. Future-topology regression safeguard (Constraint F)

A dedicated, small assertion test (extending the existing static-invariant
convention, e.g. alongside `ZenaRouteSurfaceInvariantTest`) that asserts
the **current** composition of `App\Http\Kernel::$middlewareGroups['api']`
is exactly:

```php
[
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\SecurityHeadersMiddleware::class,
    \App\Http\Middleware\ErrorEnvelopeMiddleware::class,
]
```

with an explicit failure message pointing back at this GAP: *"The `api`
middleware group has changed. GAP-051 Gate 1's 'no production exposure'
finding was conditioned on `/api/*` carrying no session/stateful
middleware (see docs/audits/2026-09-09-gap-051-...). If this change adds
`StartSession`, `EncryptCookies`, or any other stateful/session middleware
to `/api/*`, GAP-051's production-exposure conclusion must be reassessed —
do not simply update this assertion without opening a follow-up Work ID."*

This makes the "bound to current topology, not eternal" caveat in the
Gate-1 packet an enforced tripwire instead of a sentence that can go stale
silently. It fails loudly and immediately (not "eventually, if someone
remembers") the moment the topology assumption breaks.

## 7. False-positive / false-negative analysis of the recommended mechanism

**False positives (wrongly flags a valid test):**
- A test that uses `actingAsSanctumBearerToken()` and, *later in the same
  method*, calls `actingAs()` deliberately for an unrelated reason (e.g.,
  testing a mixed-auth edge case) would trip the §4 tripwire. This is
  judged an acceptable false positive: such a test should be split into
  two methods or the ordering made explicit and reviewed — the pattern
  itself is exactly what Gate 1 showed is dangerous to leave ambiguous, so
  erring toward "flag and force explicitness" here is intentional.
- `Sanctum::actingAs()` is never matched by the §4 regex (it specifically
  excludes `Sanctum::actingAs(`), so no false positive there.

**False negatives (wrongly misses a real hazard):**
- A test author who never adopts the new helper at all, and instead
  hand-rolls a raw `withHeaders(['Authorization' => ...])->getJson(...)`
  call alongside `actingAs()`, is **not** caught by Option 2 (no helper
  used, `forgetGuards()` never runs) and **is** caught by the §4 tripwire
  only if they also happen to reference the helper's name in the same
  method (they won't, if they didn't use it) — so this specific
  hand-rolled case is a genuine residual gap. Mitigation: code-review
  guidance recorded alongside the helper's docblock ("always use this
  helper for Bearer-transport tests; a raw `withHeaders(Authorization)`
  call alongside `actingAs()` is exactly the GAP-051 hazard and is not
  covered by automated tooling") plus the permanent canary in §5
  (Scenario 1) keeping the underlying risk visible in the test suite so
  it isn't forgotten. This residual gap is judged acceptable because it
  requires actively avoiding the provided, easier-to-use helper — the
  same category of gap Option 1's broader static scanning was meant to
  close but at a much higher tooling-investment cost and its own
  false-negative exposure (§2, Option 1 cons).
- A future contamination vector that is *not* `actingAs()` (e.g. some
  other test helper that also caches a user on a `web`-family guard)
  would still be closed by `forgetGuards()` in the helper itself (it
  purges *all* cached guards, not just ones set by `actingAs()`
  specifically) — so Option 2's structural protection is broader than the
  §4 tripwire's pattern-specific scope, which is intentional per §3.

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
real `auth:sanctum` route) and asserts, using the §4 introspection test's
underlying logic applied manually, that this pattern is undetectable today
— i.e., today there is no helper, no tripwire, and Scenario 1 already
proves the response is 200 regardless of whether a real token was even
checked. Concretely: the existing Gate-1 evidence Scenario 1 **is** the RED
proof — it demonstrates the false-green condition passes silently with
zero automated signal in the current (pre-Gate-3) repo state.

**GREEN (passes under the new approach):**
1. `actingAsSanctumBearerToken()` helper exists and Scenario 4 (rewritten)
   passes, proving real Bearer transport works end-to-end through the
   helper.
2. A new test constructed as: call `$this->actingAs($user)` **then**
   `$this->actingAsSanctumBearerToken($otherUser)->getJson(...)` in the
   same method — asserting the response reflects `$otherUser` (the token
   owner), not the `actingAs()` user, proving `forgetGuards()` actually
   purged the leaked state. This is the direct structural proof the fix
   works, independent of the §4 tripwire.
3. The §4 tripwire test itself, run against a **deliberately reintroduced**
   copy of the vulnerable pattern (a temporary fixture file, deleted after
   the assertion, or an inline string fixture rather than a committed
   file) fails loudly, proving the tripwire fires on the exact
   Gate-1-confirmed anti-pattern.
4. The §6 topology-tripwire test passes against the current `api` group
   composition and is shown (by temporarily mutating the array value in
   the test, not the Kernel) to fail if `StartSession`/`EncryptCookies` is
   added — proving it isn't a no-op assertion.

## 10. Gate-3 acceptance criteria

1. `AssertsSanctumBearerTransport` trait (or equivalently named) added
   under `tests/`, with `actingAsSanctumBearerToken()` implemented exactly
   as sketched in §2 Option 2 (or a reviewed equivalent using
   `forgetGuards()` for the structural purge) — no production code touched.
2. `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` renamed and
   updated per §5 (permanent regression test, Scenario 1 kept as
   documented canary, Scenario 4 rewritten to use the new helper, no
   scenario deleted).
3. §4 tripwire test added, proven to fire against the Gate-1-confirmed
   anti-pattern (via a disposable fixture per §9.3) and to stay silent
   against the current committed test suite.
4. §6 topology-tripwire test added, asserting the exact current `api`
   middleware group composition, with a failure message citing GAP-051 and
   directing reassessment rather than a silent assertion update.
5. All new/modified tests pass on real MySQL parity per this repo's
   standard CI invocation; no existing test file's behavior changes except
   the evidence-harness rewrite in item 2.
6. No changes anywhere under `config/`, `app/Http/Kernel.php`,
   `app/Http/Middleware/`, `app/Providers/RouteServiceProvider.php`, or any
   Sanctum/guard/auth registration.
7. JWT-naming debt (§8) is not touched; optionally, a one-line
   `OPERATIONAL_GAP_REGISTER.md`-style note recommending a future Work ID
   is acceptable but not required for Gate-3 closure.
8. PR body/commit messages cite this Gate-2 packet and record that Gate 3
   is test-fidelity-only, no production auth semantics changed (mirroring
   §11 below).

## 11. Production-semantics non-impact statement

This design makes **no** change to: `config/sanctum.php`,
`config/auth.php`, `app/Http/Kernel.php`'s middleware groups or aliases,
any controller, any route definition, `app/Providers/RouteServiceProvider.php`,
or any Sanctum/Illuminate guard/auth class. Every artifact proposed
(the transport-testing trait, the renamed evidence test, both tripwire
tests) lives entirely under `tests/`. Real production requests continue to
authenticate exactly as they do today; the `forgetGuards()` call used in
the proposed helper is invoked only from test code, inside PHPUnit's
in-process container, and has no effect on any real HTTP request cycle
(each real request already gets a fresh application/container instance in
production — `forgetGuards()` is meaningful specifically because PHPUnit
reuses one container across assertions within a test method, which
production never does).

## 12. Scope/files likely affected in Gate-3 implementation

- New: `tests/Support/AssertsSanctumBearerTransport.php` (or similar path
  matching this repo's existing `tests/` trait conventions) — the helper
  trait.
- Modified/renamed:
  `tests/Feature/Gap051SanctumWebGuardLeakEvidenceTest.php` →
  `tests/Feature/SanctumBearerTransportGuardContractTest.php` (exact name
  to be finalized in Gate 3).
- New: a §4 tripwire test, e.g.
  `tests/Feature/SanctumBearerTransportAntiPatternTest.php`.
- New: a §6 topology tripwire test, e.g. added to or alongside
  `tests/Feature/Zena/ZenaRouteSurfaceInvariantTest.php` or as its own
  `tests/Feature/ApiMiddlewareGroupTopologyInvariantTest.php`.
- No other files. No production code, config, or route files.
