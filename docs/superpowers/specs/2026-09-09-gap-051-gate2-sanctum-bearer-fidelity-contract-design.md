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

**Verdict: rejected as the primary mechanism.** Was also evaluated as a
supplementary defense-in-depth layer (§4.1) and, in that narrower role,
is now **also rejected** by a later Gate-2 correction — see §4.1's
verdict for why: once the helper (Option 2) plus the runtime guard-state
check (§4) are both in place, the source-text scan adds no unique
safety property, since the runtime check structurally catches the hazard
at the moment of actual request dispatch regardless of source-code shape.
Kept in this document as evaluated-and-rejected history, not carried into
Gate-3 required scope.

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

**Final architecture (this correction): exactly two layers, not three.**

- **Layer A — primary, structural.** Option 2 (`AssertsSanctumBearerTransport`
  trait / `actingAsSanctumBearerToken()` helper): a real `createToken()`-issued
  token plus `AuthManager::forgetGuards()`, guaranteeing a clean guard
  slate immediately before dispatch.
- **Layer B — secondary, universal runtime guard.** §4's pre-dispatch
  guard-state check in `Tests\TestCase`'s request-dispatch override,
  fixed by this correction to check every guard actually in play
  (`unique(config('sanctum.guard', []) + ['sanctum'])`, using the guard's
  public `hasUser()`), which fires for **any** request carrying a Bearer
  `Authorization` header if pre-existing auth state is already cached on
  one of those guards — regardless of which mechanism put that state
  there (`$this->actingAs()`, `Sanctum::actingAs()`, or a hand-rolled
  request with no helper at all). See §4 for the full mechanism and the
  guard-list fix.

**Correction history, resolved this revision.** An earlier version of
this packet proposed a **third** mechanism — a narrow PHPUnit
static-source-text tripwire (§4.1, built on Option 1) scanning for the
specific two-call anti-pattern (`actingAsSanctumBearerToken(` co-occurring
with `->actingAs(` in one method). That tripwire is **removed from
required Gate-3 scope** by this correction: once Layer A (helper) and a
correctly-scoped Layer B (runtime check, now fixed per §4 to cover both
`actingAs()` and `Sanctum::actingAs()` contamination) are in place, the
source-text scan adds no unique safety property — the runtime check
already structurally catches both hazard patterns at the moment of actual
request dispatch, independent of source-code shape — while introducing
its own false-positive/maintenance burden as a purely heuristic,
pattern-matching mechanism (the same class of concern that already
disqualified Option 1 as a primary mechanism in §2). §4.1 keeps the full
original analysis and its own verdict, for history; it is evaluated and
rejected, not implemented.

Rejected as primary or secondary: Option 1 / the §4.1 static tripwire
(heuristic, no runtime proof, redundant with Layer B, new tooling
surface), Option 3 alone (higher fragility/cost for coverage this repo's
traced test population doesn't currently need — Layer B above is a
narrower, cheaper instrument that closes the same class of gap Option 3
was evaluated for), Option 4 alone (no regression signal, restates the
status quo that produced GAP-051).

### Why this satisfies Constraints A-G

- **A**: four approaches compared above, not defaulted to grep.
- **B**: the primary mechanism (Option 2) is a positive contract — a test
  either used the helper (and is therefore structurally guaranteed a clean
  guard slate) or it didn't; nothing about it depends on inferring intent
  from surrounding source text.
- **C**: `Sanctum::actingAs()` used on its own (Gate 1's own Scenario 5
  documents it as intentionally bypassing token lookup for ability tests)
  remains untouched and unflagged — that stays true and is not treated as
  a hazard. Real Bearer-token transport testing is exactly what the new
  helper produces. Plain `actingAs()` remains fully legal for `web`-guard
  tests. Either only becomes a build failure (via §4's runtime guard-state
  check) at the moment a request carrying a real Bearer `Authorization`
  header is actually dispatched while that guard still has a cached user
  — i.e., when a test is trying to claim both things (a leftover
  session/ability-test identity and a real Bearer-transport check) at
  once, not merely for appearing in the same file or method.
- **D**: no change to `config/sanctum.php`, `config/auth.php`,
  `app/Http/Kernel.php`, any middleware, or any guard registration.
  Everything proposed lives under `tests/`.
- **E**: explicit lifecycle decision in §5.
- **F**: explicit topology tripwire in §6.
- **G**: JWT-naming debt explicitly excluded from this Gate-2 scope in
  §8.

## 4. Defense-in-depth layer (secondary, final architecture): runtime guard-state check

**The gaps being closed.** Option 2's helper (§2) only protects requests
that go through it. Two distinct contamination vectors leave a request
unprotected if a test never adopts the helper:

1. **`$this->actingAs()` contamination.** Laravel's own web-guard test
   helper sets a user directly on the `web` `SessionGuard`, cached by the
   `AuthManager` for the rest of the test method (Gate 1's confirmed
   mechanism):

   ```php
   $this->actingAs($user);
   // ... later in the same method, no reference to any helper ...
   $this->withHeaders(['Authorization' => 'Bearer ' . $token])->getJson('/api/v1/...');
   ```

2. **`Sanctum::actingAs()` contamination (identified by this correction).**
   Sanctum's own ability-testing shortcut calls `guard('sanctum')->setUser()`
   directly — it preloads auth state on the `sanctum` guard itself, not
   `web`. A test that calls `Sanctum::actingAs($otherUser, [...])` for an
   earlier assertion and then, in the same method, sends a hand-rolled
   Bearer request intended to exercise a *different* user's real token
   transport would silently be satisfied by the leftover `sanctum`-guard
   state instead — the same class of false-green hazard Gate 1 found for
   `web`, just on the guard Sanctum itself uses for real Bearer-token
   authentication.

Neither vector is caught by Option 2's helper (never invoked in the
hand-rolled case). This is not a hypothetical — vector 1 is the literal
shape of Gate 1's Scenario-1 hazard, hand-written instead of routed
through any helper; vector 2 is the same class of hazard on a different
guard, missed by an earlier version of this packet's runtime-check draft
because that draft hard-coded a `web`-only, `sanctum`-excluded guard list.
This correction fixes that.

**Mechanism (corrected this revision).** In `Tests\TestCase` (which every
feature test already extends), check — immediately before dispatching any
request that carries a Bearer `Authorization` header — whether **any**
guard that could plausibly hold pre-existing, request-invalidating auth
state already has a user cached at the moment of dispatch. The guard list
is derived, not hard-coded:

```
guards_to_check = unique(config('sanctum.guard', []) + ['sanctum'])
```

In this repo, `config('sanctum.guard')` currently resolves to `['web']`
(`config/sanctum.php:36`, unmodified default), so `guards_to_check` is
currently `['web', 'sanctum']` — but the spec derives it from config
rather than hard-coding `'web'`, so it tracks this repo's actual Sanctum
configuration if that array is ever changed, and it explicitly includes
`'sanctum'` itself rather than excluding it (an earlier draft excluded
`sanctum` on the mistaken assumption that only non-Sanctum guards could
hold contaminating state — vector 2 above disproves that assumption).

For each guard in that list, the check calls the guard's public
`hasUser(): bool` method, **not** `check()`. `check()` is implemented (in
`Illuminate\Auth\GuardHelpers`, used by both `SessionGuard` for `web` and
`Illuminate\Auth\RequestGuard` — the class Sanctum's `SanctumServiceProvider`
registers the `sanctum` guard through via `Auth::viaRequest('sanctum', ...)`)
as `! is_null($this->user())`, meaning it calls `user()`, which can itself
trigger guard resolution (invoking the guard's resolution callback) as a
side effect — GAP-051's check must observe state that is *already*
present before this request, without itself causing new resolution that
could mask or alter what it's trying to detect. `hasUser()` (also public,
also on `GuardHelpers`, and therefore available on both `web`'s
`SessionGuard` and `sanctum`'s `RequestGuard` in this repo's pinned
`laravel/framework: v12.63.0` / `laravel/sanctum: v4.3.2`, per
`composer.lock`) returns `! is_null($this->user)` — the cached property
directly, no resolution triggered. This is the API Owner's directive
specifies; if a future guard type in this chain genuinely lacked a public
`hasUser()`, that would need to be flagged and the check adjusted for
that guard specifically, but no such case exists in this repo's current
guard chain.

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

    $guardsToCheck = array_unique(array_merge(
        (array) config('sanctum.guard', []),
        ['sanctum']
    ));

    foreach ($guardsToCheck as $guardName) {
        if (Auth::guard($guardName)->hasUser()) {
            throw new \RuntimeException(sprintf(
                'GAP-051: request carries a Bearer Authorization header while '
                . 'guard [%s] already has a cached authenticated user from prior '
                . 'test state (actingAs() or Sanctum::actingAs()). This is the '
                . 'confirmed GAP-051 guard-contamination hazard. Use '
                . 'actingAsSanctumBearerToken() (which calls forgetGuards() before '
                . 'issuing its request) instead of mixing actingAs()/'
                . 'Sanctum::actingAs() with a hand-written Bearer header, or call '
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

**False positives.** Could a legitimate test intentionally have both a
Bearer header and a still-cached `web`- or `sanctum`-guard user in the
same request? A test using the new helper is unaffected —
`actingAsSanctumBearerToken()` calls `forgetGuards()` before sending its
request, so by the time the runtime check runs, no guard has cached
state, and there is no false positive for any helper-based usage. The
remaining exposure is a hand-written test that *deliberately* wants a
real Bearer header sent alongside an already-authenticated guard, to
assert some specific mixed-auth behavior. This repo's traced test
population (Gate 1: 117 `actingAs(` files, 12 `Sanctum::actingAs()`
files, 51+ real `createToken()` files) contains no such case today — the
one file that mixes patterns (`tests/Feature/Crm/ServiceLineGateTest.php`)
already keeps them in separate test methods. Forcing that scenario to be
made explicit (call `forgetGuards()` deliberately, or split into two test
methods) is judged the correct outcome, not a defect — it is exactly the
kind of ambiguity Gate 1 showed is dangerous to leave implicit.

**Framework-version stability.** `Auth::guard($name)->hasUser()` is core,
long-stable Laravel Auth facade surface (part of `GuardHelpers`, used by
every stock guard implementation) — no less stable than `forgetGuards()`.
Overriding `TestCase::call()` a second time is not a new risk category:
this repo's `Tests\TestCase` already overrides `call()` for an unrelated
cross-cutting concern (CSRF token injection), so a Laravel upgrade that
broke this pattern would already be breaking an existing, in-production
convention here, not a new one this design introduces.

**Verdict: RECOMMENDED as the secondary, final defense-in-depth layer**,
added alongside (not replacing) the helper:

1. The helper (§2) structurally prevents the common case for any author
   who adopts it.
2. This runtime guard-state check catches both the `actingAs()`-on-`web`
   vector and the `Sanctum::actingAs()`-on-`sanctum` vector for any test
   that skips the helper — regardless of which path (if any) got there —
   at negligible implementation cost (one private method in an
   already-overridden test-infrastructure hook) and no meaningful new
   false-positive exposure given this repo's actual traced test
   population.

No disqualifying flaw was found: the mechanism is cheap, uses stable
public API, extends an existing override point rather than introducing a
new one, and its only false-positive class (deliberate mixed-auth tests)
does not exist in this repo today.

### 4.1 Static tripwire — evaluated and rejected (not in Gate-3 required scope)

**Kept here as history and alternatives analysis; not part of Gate-3's
required implementation or acceptance criteria.**

An earlier version of this packet proposed, as an additional secondary
layer alongside the helper, a PHPUnit test (not a PHPStan rule — matching
this repo's existing static-invariant-test convention rather than
introducing new PHPStan tooling) that would scan `tests/**/*Test.php`
source for any test **method** containing a call to
`actingAsSanctumBearerToken(` (or whatever the Gate-3-finalized helper
name is) **and** a call to `->actingAs(` (Laravel's plain helper, not
`Sanctum::actingAs(`) within the same method body — a narrow,
single-pattern scan intended to catch someone hand-rolling the vulnerable
pattern while also referencing the new helper's name.

This has a real, acknowledged false-negative: a test that never
references the helper's name at all is invisible to it (the exact
residual gap §4 above closes with the runtime check instead), and it
never covered the `Sanctum::actingAs()` vector at all (its pattern only
matched plain `->actingAs(`).

**Verdict (this correction): REMOVED from required Gate-3 scope.** Once
Layer A (the helper, §2) and a correctly-scoped Layer B (the §4 runtime
check, which now covers both contamination vectors at the moment of
actual request dispatch, independent of source-code shape) are in place,
this static scan adds no unique safety property — anything it could catch
is already caught by the runtime check, and anything the runtime check
doesn't catch (the narrow deliberate-`forgetGuards()`-bypass case in §7)
wouldn't be caught by this pattern-match either, since that case by
definition never references the vulnerable pattern's structure this scan
looks for. As a purely heuristic, pattern-matching mechanism it also
carries the same class of false-positive/maintenance-burden risk already
used to disqualify Option 1 as a primary mechanism in §2. Retained in
this document as evaluated-and-rejected history per Owner's request, not
deleted, but explicitly **not** a Gate-3 deliverable or acceptance
criterion.

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
  **new tests from §4 and §9** (the `forgetGuards()` structural proof, the
  §4 runtime-check proof for both the `actingAs()`/`web` vector and the
  `Sanctum::actingAs()`/`sanctum` vector, the §6 topology/behavioral
  proof) **are** genuine GAP-051 security/test-fidelity regression
  contracts — they must FAIL if unsafe test contamination, or a missing
  defense-in-depth layer, is ever reintroduced.

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
  regression tests from §4/§6/§9, docblock explicit that failures here
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

**Revised safeguard — a risk-focused invariant, not a full-array pin
(corrected this revision: target class list fixed, `EncryptCookies`
removed):**

1. **Absence assertion.** Assert that no middleware which actually
   *enables* session/stateful authentication is present in
   `App\Http\Kernel::$middlewareGroups['api']`, rather than asserting the
   full exact composition. The general principle: target middleware that
   materially establishes session-auth state, not merely middleware that
   touches cookies incidentally. Concretely, the two named classes to
   check for absence are:
   - `Illuminate\Session\Middleware\StartSession` — starts the session
     that `Auth::guard('web')` (a `SessionGuard`) reads its authenticated
     user from; without it, no session-backed guard can resolve a user on
     a stateless API request.
   - `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful` —
     Sanctum's own middleware that opts a request *into* cookie/session-based
     "first-party SPA" authentication instead of pure Bearer-token
     authentication; its presence on `/api/*` is precisely the condition
     that would reopen the web-guard-fallback question GAP-051 is about.

   **Correction (this revision): `EncryptCookies` alone is explicitly
   NOT the target.** An earlier draft named `EncryptCookies` (part of
   Sanctum's `middleware.encrypt_cookies` config, currently
   `App\Http\Middleware\EncryptCookies`) as (part of) what this assertion
   checks for absence of. That is wrong and has been corrected:
   `EncryptCookies` merely decrypts/encrypts whatever cookies happen to be
   present on a request — it does not itself start a session, authenticate
   anything, or make `Auth::guard('web')` resolvable. Its presence or
   absence is a weakly-correlated proxy for the actual risk (the same
   class of over-brittle proxy problem the original exact-array-pin
   design had), and checking for it alone could both false-flag a harmless
   addition and, worse, false-clear a real risk if `StartSession` or
   `EnsureFrontendRequestsAreStateful` were added without `EncryptCookies`
   also being added. This design does not check for `EncryptCookies` at
   all. Any other middleware later found to materially establish
   session-auth state on par with these two should be added to this list
   by a reviewed follow-up, not inferred by this document.

   A harmless addition to the `api` group (anything that is not one of
   the above, or a reviewed equivalent) no longer breaks this tripwire;
   only the one condition that actually reopens GAP-051's
   production-exposure question does. Failure message unchanged in spirit
   — cites GAP-051 by name and directs reassessment, not a silent
   assertion update:

   *"Session/stateful-authentication middleware (`StartSession`/
   `EnsureFrontendRequestsAreStateful`/a reviewed equivalent) was added to
   the `api` middleware group. GAP-051 Gate 1's 'no production exposure'
   finding was conditioned on `/api/*` carrying no such middleware (see
   docs/audits/2026-09-09-gap-051-...). This changes that finding's
   premise — do not silently update this assertion; open a follow-up Work
   ID to reassess GAP-051's production-exposure conclusion first."*

   The behavioral 401 contract test in item 2 below remains the
   **authoritative** protection regardless of this static list's
   completeness — it exercises the real risk directly rather than relying
   on naming every possible offending middleware class correctly.

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
- A hand-written test that *deliberately* wants a real Bearer header sent
  alongside an already-authenticated `web` or `sanctum` guard (a genuine
  mixed-auth scenario) would trip §4's runtime guard-state check. This is
  judged an acceptable false positive: such a test should call
  `app('auth')->forgetGuards()` explicitly first, or split into two test
  methods — the pattern itself is exactly what Gate 1 showed is dangerous
  to leave ambiguous, so erring toward "flag and force explicitness" here
  is intentional. This repo's traced test population (Gate 1: 117
  `actingAs(` files, 12 `Sanctum::actingAs()` files, 51+ real
  `createToken()` files) contains no such case today.
- A test using `actingAsSanctumBearerToken()` is never affected, at any
  point in the same method: the helper's `forgetGuards()` call runs
  immediately before its own request is dispatched, so by the time §4's
  check runs for *that* request, no guard has cached state regardless of
  what happened earlier in the method.

**False negatives (wrongly misses a real hazard):**
- **Both contamination vectors are now closed.** A test author who never
  adopts the helper at all, and instead hand-rolls a raw
  `withHeaders(['Authorization' => ...])->getJson(...)` call alongside
  either `$this->actingAs()` (caches a user on `web`) or
  `Sanctum::actingAs()` (caches a user directly on `sanctum`), is caught
  by §4's runtime guard-state check in both cases: it inspects actual
  cached guard state, for every guard in
  `unique(config('sanctum.guard', []) + ['sanctum'])`, at request-dispatch
  time, regardless of which helper (if any) or which of the two vectors a
  test used to get there. Neither vector is treated as an accepted
  residual gap; the runtime check exists specifically to close both.
- The one narrower residual false negative that remains even with the
  runtime check in place: a *deliberate* mixed-auth test that calls
  `app('auth')->forgetGuards()` itself (or otherwise clears guard state)
  before sending a hand-rolled Bearer request alongside a since-cleared
  `actingAs()`/`Sanctum::actingAs()` call would not trip §4 (no guard is
  authenticated at dispatch time). This is judged acceptable: it requires
  actively reproducing the helper's own safety mechanism
  (`forgetGuards()`) by hand while still not using the helper, which is a
  vanishingly narrow and self-defeating case — a test author who already
  knows to call `forgetGuards()` has, in effect, reimplemented the fix.
- A future contamination vector that is neither `actingAs()` nor
  `Sanctum::actingAs()` (e.g. some other test helper that also caches a
  user on a guard in the checked list) would be closed by §4's runtime
  check independently of source-code shape, since it inspects guard state
  generically (any guard in `unique(config('sanctum.guard', []) +
  ['sanctum'])`, not specifically `actingAs()`- or `Sanctum::actingAs()`-shaped
  state) rather than pattern-matching specific call names. This is the
  concrete reason §4.1's static-pattern approach was removed from
  required scope (§4.1's verdict): it could only ever match the exact
  call shapes it was written for, while the runtime check generalizes
  across the whole checked-guard list without needing to be updated per
  new contamination vector.

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
real `auth:sanctum` route) and observe that this pattern is undetectable
today — i.e., today there is no helper and no runtime guard-state check,
and Scenario 1 already proves the response is 200 regardless of whether a
real token was even checked. Concretely: the existing Gate-1 evidence
Scenario 1 **is** the RED proof — it demonstrates the false-green
condition passes silently with zero automated signal in the current
(pre-Gate-3) repo state. The same RED condition holds symmetrically for
`Sanctum::actingAs($user)` followed by the same raw hand-rolled Bearer
request in place of `actingAs()` — today nothing detects that variant
either.

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
   state. This is the direct structural proof the fix works.
3. §4's runtime guard-state check is proven, as two separate proof points
   covering both confirmed contamination vectors, plus a positive control:
   - **Vector 1 (plain `actingAs()`).** A test that calls
     `$this->actingAs($user)` then issues a raw
     `$this->withHeaders(['Authorization' => 'Bearer ' . $rawToken])->getJson(...)`
     with **no** helper involved is shown to throw the GAP-051
     contamination exception — proving §4 catches the exact hand-rolled
     `web`-guard case the original design left uncovered.
   - **Vector 2 (`Sanctum::actingAs()`) — new proof point added by this
     correction.** A test that calls `Sanctum::actingAs($user, [...])`
     then issues the same kind of raw hand-rolled Bearer request (no
     helper involved) is **also** shown to throw the GAP-051 contamination
     exception — proving §4's guard list (`unique(config('sanctum.guard',
     []) + ['sanctum'])`) actually includes `sanctum` itself and catches
     contamination on that guard, not only on `web`.
   - **Positive control (helper path stays clean).** A companion test
     confirms the check stays silent for the same kind of request issued
     via `actingAsSanctumBearerToken()` (no false positive on the helper
     path), and that `currentAccessToken()` resolves to a genuine
     `PersonalAccessToken` for that request per item 1 above.
4. The §6 absence-assertion test passes against the current `api` group
   composition and is shown (by temporarily mutating the array value in
   the test, not the Kernel) to fail if `StartSession` or
   `EnsureFrontendRequestsAreStateful` is added — proving it isn't a
   no-op assertion, and that it does not rely on `EncryptCookies` as its
   signal. The §6 behavioral contract test independently passes today (a
   `web`-guard-authenticated user with no Bearer token gets `401` from a
   representative `auth:sanctum` route under the real `api` middleware
   stack), proving the actual risk is closed under the current topology,
   not just the array literal.

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
   the new test from item 3 below. No scenario deleted.
3. §4's runtime guard-state check added to `Tests\TestCase`'s
   request-dispatch path (or equivalent, reviewed override point), using
   `unique(config('sanctum.guard', []) + ['sanctum'])` as the checked
   guard list and each guard's public `hasUser()` (not `check()`) as the
   detection primitive, proven per §9.3 to throw on **both** confirmed
   contamination vectors — plain `$this->actingAs()` and
   `Sanctum::actingAs()` — with no helper involved in either case, and
   proven to stay silent for the helper-based path and for the rest of
   the current committed test suite (no false positives introduced
   against existing tests). The §4.1 static-source-text tripwire is
   explicitly **not** part of this criterion or Gate-3's required scope —
   it remains documented in §4.1 as evaluated and rejected.
4. §6 safeguard added per the corrected design: (a) an absence assertion
   that neither `Illuminate\Session\Middleware\StartSession` nor
   `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful`
   (nor a reviewed equivalent that materially establishes session-auth
   state) is present in the `api` middleware group — explicitly **not**
   checking for `EncryptCookies` — with a failure message citing GAP-051
   and directing reassessment rather than a silent assertion update; and
   (b) a behavioral contract test, treated as the authoritative
   protection, proving a `web`-guard-authenticated user with no Bearer
   token receives `401` from a representative `auth:sanctum` route under
   the real `api` middleware stack.
5. Rewritten Scenario 4 (and the new forgetGuards()-purge proof, §9.2)
   assert genuine Sanctum state — `currentAccessToken()` resolves to a
   `Laravel\Sanctum\PersonalAccessToken` matching the issued token's id
   and abilities — not merely a matching response user ID.
6. All new/modified tests pass on real MySQL parity per this repo's
   standard CI invocation; no existing test file's behavior changes except
   the evidence-harness split in item 2.
7. No changes anywhere under `config/`, `app/Http/Kernel.php`,
   `app/Http/Middleware/`, `app/Providers/RouteServiceProvider.php`, or any
   Sanctum/guard/auth registration.
8. JWT-naming debt (§8) is not touched; optionally, a one-line
   `OPERATIONAL_GAP_REGISTER.md`-style note recommending a future Work ID
   is acceptable but not required for Gate-3 closure.
9. PR body/commit messages cite this Gate-2 packet and record that Gate 3
   is test-fidelity-only, no production auth semantics changed (mirroring
   §11 below).

## 11. Production-semantics non-impact statement

This design makes **no** change to: `config/sanctum.php`,
`config/auth.php`, `app/Http/Kernel.php`'s middleware groups or aliases,
any controller, any route definition, `app/Providers/RouteServiceProvider.php`,
or any Sanctum/Illuminate guard/auth class. Every artifact proposed (the
transport-testing trait, the split evidence-harness files, the §4 runtime
guard-state check added to `Tests\TestCase`, and the §6 topology
safeguard) lives entirely under `tests/`. §4.1's static tripwire is
evaluated-and-rejected history, not a proposed artifact. Real production
requests continue to authenticate exactly as they do today; the
`forgetGuards()` call used in the proposed helper and the
`Auth::guard(...)->hasUser()` call used in the §4 runtime check are
invoked only from test code, inside PHPUnit's in-process container, and
have no effect on any real HTTP request cycle (each real request already
gets a fresh application/container instance in production —
`forgetGuards()` is meaningful specifically because PHPUnit reuses one
container across assertions within a test method, which production never
does; the §4 check similarly only ever runs inside `Tests\TestCase::call()`,
which no production request path invokes).

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
    `PersonalAccessToken` state per §9/§10 item 5), 5, plus the
    `forgetGuards()`-purge structural proof from §9.2.
  (Exact file names to be finalized in Gate 3.)
- Modified: `tests/TestCase.php` — add the §4 runtime guard-state check
  to the existing `call()` override (alongside the existing CSRF-injection
  logic), deriving the checked-guard list from
  `unique(config('sanctum.guard', []) + ['sanctum'])` and using each
  guard's `hasUser()`, plus a dedicated test proving it fires on **both**
  the plain-`actingAs()` and the `Sanctum::actingAs()` raw hand-rolled
  anti-patterns and stays silent on the helper path and the rest of the
  current suite (e.g. `tests/Feature/SanctumBearerRuntimeGuardCheckTest.php`).
- New: §6 safeguard tests, e.g. added to or alongside
  `tests/Feature/Zena/ZenaRouteSurfaceInvariantTest.php` or as their own
  `tests/Feature/ApiMiddlewareGroupTopologyInvariantTest.php` (absence
  assertion targeting `StartSession`/`EnsureFrontendRequestsAreStateful`,
  not `EncryptCookies`) and `tests/Feature/ApiSanctumWebGuardNoLeakContractTest.php`
  (behavioral 401 contract test — the authoritative protection), exact
  names to be finalized in Gate 3.
- Not in scope (evaluated and rejected, §4.1): no static/source-text
  tripwire test is added.
- No other files. No production code, config, or route files.
