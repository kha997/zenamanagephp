---
work_id: GAP-011
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: null
  plan: null
  branch: docs/GAP-011-debug-route-cleanup-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/260
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-13T17:00:30+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: "docs/owner-decisions/GAP-011/02-design-v2.md"
superseded_by: null
timestamps:
  created_at: "2026-08-13T17:00:30+07:00"
  updated_at: "2026-08-13T17:00:30+07:00"
generated_by: agent
---

## OWNER GATE 2: AWAITING OWNER DECISION (round 3) — design surface for Class A + Class B only

This packet does not implement anything. No route, middleware, test, or file has changed. It designs the canonical protection boundary for GAP-011's Owner-approved scope (Class A: 21 gated `/_debug/*` routes; Class B: 7 compatibility redirects) and asks the Owner to choose a structural architecture, a per-route disposition, and the invariant that will guard the boundary going forward. **Class C is out of scope by binding Gate 1 governance clarification and is not designed, decided, or bundled here.**

This is round 3. Round 2 (`docs/owner-decisions/GAP-011/02-design-v2.md`, frozen) received a second **CHANGES REQUESTED** decision — not on the substantive retention/architecture direction, which the Owner accepted in principle, but on this work item's governance-history immutability and one implementation-topology correction. §0 below maps each of the 8 round-3 correction points to where it is resolved; §0a is the supersession-chain repair record itself.

## 0. Round-3 corrections applied

| # | Owner correction (round 2 decision) | Where resolved |
|---|---|---|
| 1 | Repair Gate 2 decision immutability — round 1's CHANGES REQUESTED was never recorded in frontmatter; freeze round 1 and round 2 as versioned, superseded packets; create this file as the active round-3 packet | §0a below; `02-design.md` and `02-design-v2.md` corrected in the same commit as this file |
| 2 | Correct chronology to authoritative Git commit timestamps for historical content, fresh wall-clock for decisions recorded now; do not preserve the impossible `17:20:00` timestamp | §0a below |
| 3 | Correct Option C's registration architecture — `routes/debug.php` and its `RouteServiceProvider` loader already exist; repurpose them, do not add a second `require` | §2, Option C — rewritten |
| 4 | Preserve the accepted 2 KEEP / 19 REMOVE retention decision unchanged by the loader-topology correction | §1 — unchanged from round 2 |
| 5 | Do not remove `VerifyCsrfToken::$except`'s bare `test-login-simple` entry under GAP-011 | §1 row 4 — correction applied |
| 6 | Be conservative with view-file deletion — verify orphan status by direct search before deleting | §1 rows and §9 — conservative language added |
| 7 | Preserve the full invariant contract, restated for the corrected RouteServiceProvider-owned topology | §3 |
| 8 | Re-present with corrected supersession chain, timestamps, head SHA, loader topology, implementation files, unchanged matrices, exact-head CI | §0a, §9, this document as a whole |

### 0a. Supersession-chain repair record

Both prior rounds received Owner **CHANGES REQUESTED** decisions that this work item's packet frontmatter failed to record at the time — each commit left `gate_status: awaiting_owner` unchanged instead of transitioning to `changes_requested`, so the packet history did not reflect what had actually been decided. This is corrected as follows, using authoritative Git commit timestamps for historical content and a fresh wall-clock timestamp for the correction itself (round-2 correction #2 — no timestamp below is estimated or incremented manually):

- **`docs/owner-decisions/GAP-011/02-design.md`** — now frozen as the round-1 packet. Body reconstructed verbatim from its exact pre-round-2 commit, `c2f8f4455d8ce27a8df88dad1a5fe6911799e6db` (`2026-08-13T16:38:44+07:00`, the authoritative Git commit timestamp for that content). Frontmatter corrected: `gate_status: changes_requested`, `owner_decision.value: changes_requested`, `superseded_by: docs/owner-decisions/GAP-011/02-design-v2.md`, `decision_provenance` recording the round-1 Owner decision (the nine corrections that produced round 2), with `recorded_at` set to the fresh wall-clock time this repair was made (`2026-08-13T16:58:30+07:00`) since the decision itself occurred in-session and its exact original wall-clock moment was not independently captured — the frontmatter is explicit that this is a retroactive record, not a claim of when the Owner literally typed the message.
- **`docs/owner-decisions/GAP-011/02-design-v2.md`** — now frozen as the round-2 packet. Body reconstructed verbatim from `832da3ffdc862d5acbcb46fff7a3ebc503123ac0` (`2026-08-13T16:47:04+07:00`), where it was previously filed under the path `02-design.md`. Frontmatter corrected: `gate_status: changes_requested`, `owner_decision.value: changes_requested`, `superseded_by: docs/owner-decisions/GAP-011/02-design-v3.md`, `decision_provenance` recording round 2's actual CHANGES REQUESTED decision (this current message) at `recorded_at: 2026-08-13T16:58:30+07:00` — a fresh, real-time timestamp, since this decision is being recorded as it happens, not retroactively. Its prior `recorded_at` of `2026-08-13T17:20:00+07:00` postdated its own commit time and has been removed as an invalid chronology, per round-2 correction #2.
- **`docs/owner-decisions/GAP-011/02-design-v3.md`** (this file) — the new active packet, `gate_status: awaiting_owner`, `owner_decision.value: none`, `supersedes: docs/owner-decisions/GAP-011/02-design-v2.md`, `created_at`/`updated_at` at `2026-08-13T17:00:30+07:00`.

Neither `02-design.md` nor `02-design-v2.md` will be overwritten again after this commit — they are frozen historical record. All future corrections happen in new versioned files (`02-design-v4.md` and onward) with `supersedes`/`superseded_by` chaining, exactly as this repair establishes.

## Gate 2 baseline reconfirmation (round 3)

- `origin/main` re-fetched and confirmed still at `1024b68640c2aeddc924620ef7be2885339fecec` — unchanged since Gate 1 freeze and unchanged across all three rounds.
- `docs/GAP-011-debug-route-cleanup-gate1-prep` remains Gate 1's 4 commits plus the round-1 and round-2 design commits, plus this round's supersession-repair commit, on top of that baseline — no drift, nothing to reconcile.
- CI at PR #260's head as of round 2 (`832da3ff...`): `Owner Governance Lint` pass, `test-routes-guardrails` pass. This round's commit will be re-checked at its own head before Owner re-review — see §10.

## Authorization boundary

- Gate 1: **APPROVED** (scope: Class A + Class B only)
- Gate 2: **AWAITING OWNER DECISION** (round 3)
- Gate 3: **NOT STARTED**
- Implementation authorized: **NO**
- Merge/release authorized: **NO**

---

## 1. Class A retention matrix — all 21 `/_debug/*` routes (unchanged from round 2, per correction #4)

The Owner accepted this matrix in principle in round 2 ("Class A retention: 2 KEEP / 19 REMOVE / 0 UNKNOWN is accepted"). It is carried forward without re-evaluation, with exactly one correction applied to a single row's anticipated-cleanup note (row 4, `VerifyCsrfToken`, correction #5) and conservative-deletion language added throughout (correction #6) — no disposition changes.

| # | Method | URI | Purpose (source) | Handler | Evidence classification | Usage evidence found | Disposition |
|---|---|---|---|---|---|---|---|
| 1 | GET | `_debug/dashboard-data` | Mock dashboard KPI JSON fixture (closure, `web.php:592`) | inline closure | **Script/tool/view/code consumer** — real, but currently non-functional in production | `resources/views/app/dashboard-content.blade.php:355` (`fetch('/_debug/dashboard-data')`), reachable from `resources/views/layouts/app-layout.blade.php:83`, the production-rendered app layout. Called from `retry()`/`refreshNotifications()` (user-triggered, not on page load). Already 404s in production today via `DebugGateMiddleware`. | **KEEP** — real code consumer exists. The frontend call itself is a separate, adjacent defect (§7 scope lock) — **not repaired under GAP-011, per Owner correction #4/round-2 acceptance.** |
| 2 | GET | `_debug/test-permissions` | Renders `test-permissions` view | closure → view | **Test-only preservation** | Repo-wide search outside `tests/`/`routes/web.php` — zero real-consumer matches. | **REMOVE** |
| 3 | GET | `_debug/test-api-admin-stats` | Calls the real `Api\Admin\DashboardController::getStats` with no auth | `Api\Admin\DashboardController@getStats` | **Test-only preservation** | Repo-wide search — zero real-consumer matches. The real controller remains separately reachable through its authenticated production route. | **REMOVE** |
| 4 | POST | `_debug/test-login-simple` | Hardcoded-password (`zena1234`) demo login for 3 fixed emails | closure | **Test-only preservation** | Repo-wide search found no form/script consumer. **Correction (Owner point 5, round 2):** `app/Http/Middleware/VerifyCsrfToken.php:14` lists a bare `'test-login-simple'` CSRF exemption — this does **not** URI-match the Class A route `_debug/test-login-simple` (Laravel's `VerifyCsrfToken::inExceptArray()` matches `$request->is($pattern)` against the literal request path; `test-login-simple` matches only a request to exactly that bare path, never `_debug/test-login-simple`). It is therefore not established as belonging to this route and is **not** an implementation file for its removal. | **REMOVE** (route only). The `VerifyCsrfToken` bare exemption entry is **left untouched under GAP-011** — recorded as separate discovered cleanup evidence (an apparently orphaned exemption for a path that does not currently correspond to any registered route), not remediated here. |
| 5 | GET | `_debug/test-session-auth` | Reads `session('user')`, echoes session-auth state | closure | **Test-only preservation** | Repo-wide search — zero real-consumer matches. | **REMOVE** |
| 6 | GET | `_debug/test` | Static "Server is working!" HTML | closure | No evidence of any kind | None. | **REMOVE** |
| 7 | GET | `_debug/test-mobile-optimization` | Renders `test-mobile-optimization` view | closure → view | **Documentation-only / dead reference** | `testing-suite.blade.php:332` links the unprefixed (dead) path. | **REMOVE** — RETIRE cluster, §1a. View file `resources/views/test-mobile-optimization.blade.php`: subject to §9's orphan-verification step before deletion. |
| 8 | GET | `_debug/test-mobile-simple` | Renders `test-mobile-simple` view | closure → view | **Documentation-only / dead reference** | `testing-suite.blade.php:338`, dead link. | **REMOVE** — RETIRE cluster, §1a. View file: subject to §9. |
| 9 | GET | `_debug/test-accessibility` | Renders `test-accessibility` view | closure → view | **Documentation-only / dead reference** | `testing-suite.blade.php:344`, dead link. | **REMOVE** — RETIRE cluster, §1a. View file: subject to §9. |
| 10 | GET | `_debug/test-simple` | Static "Simple Test" HTML | closure | None found | None. | **REMOVE** |
| 11 | GET | `_debug/test-auth` | Static text, `->middleware(['auth'])` | closure | None found | None. | **REMOVE** |
| 12 | GET | `_debug/test-auth-direct` | Static text, stale inaccurate comment | closure | None found | None. | **REMOVE** |
| 13 | GET | `_debug/test-minimal` | Static text, `->middleware([])` at route level | closure | None found | None. | **REMOVE** |
| 14 | GET | `_debug/test-bypass` | Static text, stale inaccurate comment | closure | None found | None. | **REMOVE** |
| 15 | GET | `_debug/test-web-guard` | Static text, `->middleware(['auth:web'])` | closure | None found | None. | **REMOVE** |
| 16 | GET | `_debug/admin-dashboard-test` | Static "Admin Dashboard Test" HTML | closure | **Documentation-only / dead reference** | `testing-suite.blade.php:350`, dead link. | **REMOVE** — RETIRE cluster, §1a. |
| 17 | GET | `_debug/testing-suite` | Renders `testing-suite.blade.php`, self-test hub with dead links | closure → view | **Documentation-only / dead reference** | No test, no code consumer; own links to children are broken. | **REMOVE (RETIRE)** — Owner decision, confirmed unchanged in round 2. View file `resources/views/testing-suite.blade.php`: subject to §9. |
| 18 | GET | `_debug/performance-optimization` | Renders `performance-optimization` view | closure → view | None found | None. | **REMOVE**. View file: subject to §9. |
| 19 | GET | `_debug/final-integration` | Renders `final-integration` view | closure → view | None found (confirmed false-positive against unrelated `api/v1/final-integration/*`) | None. | **REMOVE**. View file: subject to §9. |
| 20 | GET | `_debug/tenant-dashboard-test` | Static "Tenant Dashboard Test" HTML | closure | **Documentation-only / dead reference** | `testing-suite.blade.php:356`, dead link. | **REMOVE** — RETIRE cluster, §1a. |
| 21 | GET | `_debug/test-login/{email}` | Looks up `User::where('email', $email)`, `Auth::login($user)`, redirects to `/app/dashboard` | closure | **Real workflow consumer — local/testing/development only** | `resources/views/auth/login.blade.php:184-199`'s 6 demo-user quick-login links, themselves wrapped in `@if(app()->environment(['local', 'testing', 'development']))`. | **KEEP** — verified, environment-scoped developer-login workflow. Confirmed unchanged in round 2. |

### 1a. The `testing-suite.blade.php` self-test hub — RETIRE (unchanged, confirmed round 2)

Unchanged from round 2: `_debug/testing-suite` and its five dead-linked siblings (`test-mobile-optimization`, `test-mobile-simple`, `test-accessibility`, `admin-dashboard-test`, `tenant-dashboard-test`) are all **REMOVE**. The Owner explicitly confirmed RETIRE (not repair) in round 2.

### 1b. Summary counts (unchanged)

- **KEEP: 2** — `dashboard-data`, `test-login/{email}`
- **REMOVE: 19**
- **UNKNOWN: 0**

---

## 2. Structural-boundary options

Options A and B are unchanged in substance from round 2 (see prior packets for their full comparison tables) — this round's correction is entirely within Option C's mechanics.

### Option C — Repurpose the existing `routes/debug.php` + `RouteServiceProvider` loader, widen the environment gate, middleware defense-in-depth (recommended, topology corrected)

**Round-2 correction (Owner point 3):** `routes/debug.php` already exists in this repository (currently disabled — its content is entirely commented out, "EMERGENCY: Debug routes completely disabled"), and `app/Providers/RouteServiceProvider.php:56-59` already conditionally loads it:

```php
// Debug routes (only in local environment)
if (app()->environment('local')) {
    Route::middleware('web')
        ->group(base_path('routes/debug.php'));
}
```

Round 1 and round 2's designs incorrectly proposed adding a *second*, new `require`/`Route::group` call for `routes/debug.php` from inside `routes/web.php`. This was wrong: it would either duplicate route registration (if both loaders fired) or create two competing, easily-desynchronized places that decide whether debug routes load at all. The corrected design uses **one canonical loader, in one place**:

- **`app/Providers/RouteServiceProvider.php`** is the sole registration owner. Its existing conditional (`app()->environment('local')`) is widened to `app()->environment(['local', 'testing', 'development'])` — a one-line change to an existing condition, not a new registration path. The surrounding `Route::middleware('web')->group(base_path('routes/debug.php'))` call is otherwise unchanged.
- **`routes/debug.php`** is repurposed (its existing "EMERGENCY: disabled" placeholder content replaced), not newly created. Inside it, the 2 surviving Class A routes are declared under `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])->group(...)`, exactly as they are today inside `routes/web.php`, just relocated. The `/debug/{path?}` wildcard redirect (§4, KEEP) is declared in this same file, keeping its own existing `local`-only condition exactly as-is (it does not widen to testing/development — its stated purpose, §4, is a `local`-only typing convenience, and nothing requires it to be reachable in testing or development).
- **`routes/web.php`** loses the old inline Class A route block and the 6 REMOVE Class B redirects, but does **not** gain any new loader statement — `routes/debug.php` was never its responsibility and is not becoming its responsibility now.

This guarantees `routes/debug.php` is registered **exactly once**, in exactly one file, under exactly one condition — eliminating the dual-loader risk the original (round 1/2) design would have introduced.

- **Production safety:** Unchanged from round 2's assessment — in production, the 2 surviving Class A routes and the 1 surviving Class B redirect do not exist in the route table at all, only `DebugGateMiddleware`-gated in the three named non-production environments.
- **Discoverability/maintainability:** Improved over round 1/2's proposal — a contributor auditing "where do debug routes get registered" finds exactly one call site (`RouteServiceProvider::boot()`), not two.
- **Failure mode on a wrong future addition:** Unchanged in kind from round 2 — protected by the static declaration-site guard (§3, §5) plus `DebugGateMiddleware`; a single-loader topology makes the guard's job simpler (one condition to verify, not two).
- **Compatibility with local/testing/development:** `routes/debug.php` now loads in all three named environments (previously `local` only) — this is the deliberate widening; `local`'s existing behavior for the 2 KEEP routes and the wildcard redirect is unchanged, `testing`/`development` gain the same behavior `local` already had.
- **Effect on automated tests:** `phpunit.xml` sets `APP_ENV=testing` — under the corrected condition, `routes/debug.php` now loads during the test suite for the first time (previously it did not, since the old condition was `local`-only and tests run under `testing`). This is a deliberate, necessary consequence: `DebugRouteDocumentationInvariantTest` and `LegacyDebugRootRedirectTest` (and this design's new §5 tests) all assume the 2 KEEP routes resolve under `APP_ENV=testing`, which requires this widening to actually take effect.
- **Route-cache/config-cache implications:** Resolved with direct repo evidence in round 2, unchanged in round 3 — `deploy-production.sh:143-160` runs `route:cache` via `docker-compose exec app` on the target host itself, per-environment, confirming Option C's route-absence property survives caching in this repository's actual deployment path.
- **Migration complexity:** Lower than round 1/2's proposal — one existing file's condition widened, one existing (currently-disabled) file's content replaced, no new route file, no new loader call.
- **Rollback simplicity:** High — revert `RouteServiceProvider.php`'s condition back to `'local'` and restore `routes/debug.php`'s disabled placeholder; `routes/web.php`'s removed block would need restoring from Git history, same as any other option.

### Recommendation

**Option C, corrected topology.** The substance is unchanged from round 2 (Owner already accepted it in principle); only the mechanism for "where debug routes are loaded" is corrected to reuse the repository's existing, single loader rather than introduce a second one.

---

## 3. Canonical protection contract (round 3 — restated for the corrected topology, per Owner point 7)

1. **Where may GAP-011 debug routes legally be declared?** Only inside `routes/debug.php`, inside the `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])->group(...)` block. No `_debug/*` route may be declared in `routes/web.php`, `routes/api.php`, or any other route file. **`routes/debug.php` is the sole legal declaration site for Class A.**
2. **Who registers it, and under what condition?** `app/Providers/RouteServiceProvider.php` is the **sole registration owner** — the single existing `Route::middleware('web')->group(base_path('routes/debug.php'))` call, gated by `app()->environment(['local', 'testing', 'development'])`. No other file may independently `require` or group-register `routes/debug.php`.
3. **In which environments may it be registered?** Only `local`, `testing`, `development` — checked once, at the single `RouteServiceProvider` call site.
4. **Must every Class A route carry `DebugGateMiddleware` even though environment-registration already excludes production?** Yes — defense-in-depth, worded precisely per round 2's correction: **environment-gated registration** (item 3 above) protects against accidental route *presence* when `APP_ENV` is correctly configured as production or anything outside the three named environments. **`DebugGateMiddleware`** protects against *declaration/mounting mistakes* — a route accidentally declared outside `routes/debug.php`, or a future change to how/when it loads — by denying the request at execution time even if the route ends up present. **Neither layer, independently or together, protects a production host whose `APP_ENV` is itself falsely resolved to `local`, `testing`, or `development`** — both checks read the same `app()->environment(...)` state. This is a deployment/configuration-contract requirement: **production must never resolve its environment to one of the three permitted names.**
5. **Required production behavior:** route **absent** — zero `_debug/*` entries in `php artisan route:list` under `APP_ENV=production`, verified both uncached and after `route:cache`.
6. **Required local/testing/development behavior:** the 2 KEEP routes and the 1 KEEP redirect present and functional, identical to current behavior.
7. **What must CI fail on?** Any of: (a) a `_debug/*`-prefixed route whose declaring file (via a **deterministic static source scan of `routes/*.php`**, not `route:list` action metadata) is not `routes/debug.php`; (b) `routes/debug.php` being registered from anywhere other than `app/Providers/RouteServiceProvider.php`'s single call site (a static check of the provider's source, alongside the `routes/*.php` scan); (c) a route inside `routes/debug.php`'s group missing `DebugGateMiddleware` in its resolved runtime middleware stack; (d) `routes/debug.php`'s routes appearing in `route:list` under `APP_ENV=production`, both uncached and after `route:cache`.

---

## 4. Class B disposition — 7 redirects (unchanged from round 2, per correction #4)

| # | Method | Source URI | Target | Disposition |
|---|---|---|---|---|
| 1 | GET\|HEAD | `/dashboard-data` | `/_debug/dashboard-data` | **REMOVE** — no verified consumer beyond `LegacyDebugRootRedirectTest`. |
| 2 | GET\|HEAD | `/test-api-admin-dashboard` | `/_debug/test-api-admin-stats` | **REMOVE** — no verified consumer; target route also REMOVE. |
| 3 | GET\|HEAD | `/test-permissions` | `/_debug/test-permissions` | **REMOVE** — no verified consumer; target route also REMOVE. |
| 4 | GET\|HEAD | `/test-api-admin-stats` | `/_debug/test-api-admin-stats` | **REMOVE** — no verified consumer; target route also REMOVE. |
| 5 | GET\|HEAD | `/test-session-auth` | `/_debug/test-session-auth` | **REMOVE** — no verified consumer; target route also REMOVE. |
| 6 | GET\|HEAD | `/test-login/{email}` | `/_debug/test-login/{email}` | **REMOVE** — no verified consumer; `login.blade.php`'s real consumer of the target bypasses this redirect and links `_debug/` directly. |
| 7 | GET\|HEAD | `/debug/{path?}` (wildcard) | `/_debug/{path}` | **KEEP, explicit local developer convenience alias** — stated purpose: a `local`-only typing convenience, not a legacy-URL/production compatibility concern. Declared inside `routes/debug.php` (§2) with its own existing `local`-only condition unchanged. New regression coverage required (§5), since none exists today. |

---

## 5. Anti-drift tests (design, not implementation — restated for the corrected topology)

Unchanged in substance from round 2, with test #1 and a new test #1a reflecting the single-loader topology:

1. **Static declaration-site guard:** scan `routes/*.php` (excluding `routes/debug.php`) for any route definition whose path begins with `_debug`; fail if found. Scan `routes/debug.php` and assert every route inside it is under the `_debug` prefix and inside the `DebugGateMiddleware` group.
2. **Single-loader guard (new in round 3):** statically confirm `routes/debug.php` is `require`'d/grouped from exactly one location in the application's route/provider registration (`app/Providers/RouteServiceProvider.php`), and nowhere else (e.g. not also from `routes/web.php` or any other provider) — this is the structural test that makes Owner correction #3 durable against future drift, not just a one-time fix.
3. **Runtime middleware-presence guard:** for every route whose URI matches `_debug/*` in the currently-booted route table, assert `DebugGateMiddleware` is present in its resolved middleware stack.
4. **Class B redirect-destination guard:** for the one surviving redirect (`/debug/{path?}` → `/_debug/{path}`), assert the destination is present in the validated `_debug/*` route set.
5. **Production-absence test, uncached and cached:** boot with `APP_ENV=production` and assert `route:list --path=_debug` returns zero entries, both against live route resolution and after `php artisan route:cache`.
6. **Local/testing-presence test:** boot with `APP_ENV=local` and `APP_ENV=testing` and assert the 2 KEEP routes and the 1 KEEP redirect exist and resolve as designed.

---

## 6. Acceptance scenarios

Unchanged in substance from round 2 (see prior packet for full detail) — production/local/testing scenarios and rollback criteria all still apply against the corrected topology. One addition:

- **Rollback criteria (addition):** if the `RouteServiceProvider` environment-widening (`'local'` → `['local', 'testing', 'development']`) is found to have an unintended side effect specific to `testing` or `development` (distinct from the `route:cache` risk already addressed), the rollback is narrowing the condition back, not reverting the single-loader topology itself — the topology correction (one loader, not two) is independently correct regardless of which environments end up included.

---

## 7. Scope lock (unchanged, reconfirmed round 3)

Unchanged from round 2. Explicitly excluded: `local/dev-login/operator`, `routes/debug_api.php` (all routes), `routes/api-simple.php` (already remediated separately), Laravel Dusk's `_dusk/*` routes, bare `login`/`logout`/`password/reset` and core auth architecture, all Class C findings, any unrelated production business route, and `resources/views/app/dashboard-content.blade.php`'s `fetch('/_debug/dashboard-data')` call (adjacent defect, flagged only). **Added in round 3:** `app/Http/Middleware/VerifyCsrfToken.php`'s bare `'test-login-simple'` CSRF exemption entry — confirmed not to URI-match the route being removed, left untouched, recorded as separate discovered cleanup evidence only (§1 row 4).

No new Work ID is minted for any of these findings during this task.

---

## 8. VerifyCsrfToken correction (Owner point 5, standalone record)

Round 1 and round 2 both anticipated removing `'test-login-simple'` from `VerifyCsrfToken::$except` as part of removing the `_debug/test-login-simple` route. This is corrected: Laravel's CSRF exemption matching (`VerifyCsrfToken::inExceptArray()` → `$request->is($pattern)`) compares the exemption pattern against the literal request path. The configured pattern is the bare string `'test-login-simple'`, which matches only a request to exactly `/test-login-simple` — it does **not** match `/_debug/test-login-simple`, the actual Class A route's path (with its `_debug` prefix). No framework-level evidence establishes this exemption as belonging to the route being removed under GAP-011. **`app/Http/Middleware/VerifyCsrfToken.php` is left untouched under GAP-011.** The apparently orphaned bare exemption (it does not correspond to any currently-registered route in the repository) is recorded here as separate discovered cleanup evidence, available for a future, independent decision if the Owner wants it pursued — not implied, scoped, or actioned by this design.

## 9. View-file deletion conservatism (Owner point 6)

Round 1 and round 2 listed 5 orphaned-looking view files as anticipated deletions (`testing-suite.blade.php`, `final-integration.blade.php`, `performance-optimization.blade.php`, `test-accessibility.blade.php`, `test-mobile-optimization.blade.php`, `test-mobile-simple.blade.php`) without an explicit verification step. Corrected: **at implementation time, before deleting any view file**, a direct search must confirm it is orphaned outside the removed GAP-011 route — specifically: (a) no other Blade file `@include`s, `@extends`, or references it as a component; (b) no controller or route closure elsewhere in the application calls `view('<name>')` on it; (c) it is not referenced by name in any retained documentation that treats it as current (not historical/archived). If any such reference is found, the view file is **left in place** even though its GAP-011 route is removed — GAP-011 removes routes, not views, and does not widen into general dead-view cleanup. This check is deferred to implementation time (Gate 3), not performed as part of this design packet, but is recorded here as a binding constraint on implementation.

## 10. Anticipated implementation files (round 3, corrected)

- **`app/Providers/RouteServiceProvider.php`** — widen the existing debug-route condition from `app()->environment('local')` to `app()->environment(['local', 'testing', 'development'])`. **This is the one file added to the anticipated list in round 3** that round 1/2 omitted (they proposed a new `require` in `routes/web.php` instead).
- **`routes/debug.php`** — **existing file, repurposed, not new.** Its current disabled placeholder content is replaced with the 2 KEEP Class A routes (under `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])`) and the 1 KEEP Class B redirect (`/debug/{path?}`, own `local`-only condition).
- **`routes/web.php`** — removes the old inline Class A route block and the 6 REMOVE Class B redirects. **Does not** gain a new loader for `routes/debug.php` — that responsibility stays exclusively with `RouteServiceProvider.php`.
- ~~`app/Http/Middleware/VerifyCsrfToken.php`~~ — **removed from this list in round 3** (Owner point 5, §8) — left untouched.
- `tests/Feature/DebugRouteDocumentationInvariantTest.php` — expectation-array update to the 2-route surviving set.
- `tests/Feature/LegacyDebugRootRedirectTest.php` — expectation-array update; new assertions for the 6 removed redirects now 404ing.
- New test file (name chosen at implementation time) — §5's static declaration-site guard, single-loader guard, runtime middleware-presence guard, redirect-destination guard, and both production-absence variants (uncached + `route:cache`d).
- New test file or extension — regression coverage for `/debug/{path?}`, which has none today.
- `resources/views/testing-suite.blade.php` and 5 sibling view files — **candidates for deletion, subject to the §9 orphan-verification search at implementation time**, not an unconditional deletion list.

---

## Decision Needed

Owner chooses one:
- **APPROVE** — Option C with the corrected single-loader topology (`RouteServiceProvider` widened, `routes/debug.php` repurposed, no new `require` in `routes/web.php`), the unchanged 2 KEEP / 19 REMOVE / 0 UNKNOWN retention matrix, the unchanged 6 REMOVE / 1 KEEP Class B disposition, the `VerifyCsrfToken` non-removal, and the conservative view-deletion constraint, as the design to carry into an implementation plan.
- **CHANGES** — name what should still change.
- **DECLINE** — do not proceed with GAP-011 Class A/B remediation at this time.
- **DEFER** — revisit at a later date; no design decision recorded now.

## What the owner is NOT being asked to decide

Not being asked to re-approve the retention matrix or Class B disposition (already accepted in round 2 and unchanged here) — only: confirm the corrected Option C loader topology (`RouteServiceProvider`-owned, single registration site), the `VerifyCsrfToken` non-removal, and the conservative view-deletion constraint. Also not being asked to decide anything about Class C or the `dashboard-content.blade.php` adjacent defect — both remain out of scope.
