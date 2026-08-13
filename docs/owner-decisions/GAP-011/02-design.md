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
  recorded_at: "2026-08-13T16:36:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-13T16:36:00+07:00"
  updated_at: "2026-08-13T16:36:00+07:00"
generated_by: agent
---

## OWNER GATE 2: AWAITING OWNER DECISION — design surface for Class A + Class B only

This packet does not implement anything. No route, middleware, test, or file has changed. It designs the canonical protection boundary for GAP-011's Owner-approved scope (Class A: 21 gated `/_debug/*` routes; Class B: 7 compatibility redirects) and asks the Owner to choose a structural architecture, a per-route disposition, and the invariant that will guard the boundary going forward. **Class C is out of scope by binding Gate 1 governance clarification and is not designed, decided, or bundled here.**

## Gate 2 baseline reconfirmation

- `origin/main` fetched and confirmed at `1024b68640c2aeddc924620ef7be2885339fecec` — unchanged since Gate 1 freeze.
- `docs/GAP-011-debug-route-cleanup-gate1-prep` (this branch) is exactly Gate 1's 4 commits (`a9afa39c`..`98028671`) on top of that baseline — no drift, no reconciliation required, nothing to merge forward.
- Gate 1 approved scope (Class A + Class B only, Class C excluded) is unchanged and carried forward verbatim into this design.

## Authorization boundary

- Gate 1: **APPROVED** (scope: Class A + Class B only)
- Gate 2: **AWAITING OWNER DECISION**
- Gate 3: **NOT STARTED**
- Implementation authorized: **NO**
- Merge/release authorized: **NO**

---

## 1. Class A retention matrix — all 21 `/_debug/*` routes

Method: read `routes/web.php:590-778` (the single `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])` group) directly for source/handler, then `grep -r` across `tests/`, `resources/views/`, `scripts/`, `.github/workflows/`, `database/` for each route's URI to find consumers. No route is marked KEEP merely for existing, and none is marked REMOVE merely for looking old — each disposition below cites the concrete evidence found (or its concrete absence).

| # | Method | URI | Purpose (source) | Handler | Usage evidence found | Breaks a verified workflow if removed? | Disposition |
|---|---|---|---|---|---|---|---|
| 1 | GET | `_debug/dashboard-data` | Mock dashboard KPI JSON fixture (closure, `web.php:592`) | inline closure | **Live frontend consumer**: `resources/views/app/dashboard-content.blade.php:355` (`fetch('/_debug/dashboard-data')`), reachable from `resources/views/layouts/app-layout.blade.php:83`, the layout `AppController@dashboard` renders in production. Called from `retry()` and `refreshNotifications()` (user-triggered, not on page load — `init()` uses server-rendered stats only). Also referenced in `DebugRouteDocumentationInvariantTest` and `LegacyDebugRootRedirectTest` (redirect-target assertion only). | **Yes, but already broken in production today** — `DebugGateMiddleware` already 404s this in production, so a production user clicking "refresh notifications" already gets a silent failure (caught by the `catch` block, stats just don't update). Removing the route changes nothing observable in production; it would matter only if a future fix re-pointed this fetch at a real endpoint. | **KEEP** (route itself), **UNKNOWN — adjacent bug, not GAP-011's to fix**: the production dashboard JS calling a dev-only debug endpoint is a real defect. Flagging as a candidate for a separate follow-up gap (fix or remove the `fetch` call in `dashboard-content.blade.php`) — not in GAP-011 scope to touch that Blade file. |
| 2 | GET | `_debug/test-permissions` | Renders `test-permissions` view | closure → view | Class B redirect target (`/test-permissions`), tested end-to-end (301 + destination) by `LegacyDebugRootRedirectTest`; also asserted present by `DebugRouteDocumentationInvariantTest`. | Yes — redirect target, has test coverage. | **KEEP** |
| 3 | GET | `_debug/test-api-admin-stats` | Calls the **real** `Api\Admin\DashboardController::getStats` with no auth — a genuine no-auth dev shortcut onto production controller logic | `Api\Admin\DashboardController@getStats` | Target of **two** Class B redirects (`/test-api-admin-dashboard` and `/test-api-admin-stats`), both tested by `LegacyDebugRootRedirectTest`. | Yes — two redirects depend on it, tested. | **KEEP** |
| 4 | POST | `_debug/test-login-simple` | Hardcoded-password (`zena1234`) demo login for 3 fixed emails, session-only fake user object (no `Auth::login`) | closure | Directly tested by both `DebugRouteDocumentationInvariantTest` (asserts POST-only) and `LegacyDebugRootRedirectTest` (asserts GET now 404s, confirming no orphaned root redirect). | Yes — has direct test coverage. Duplicates the hardcoded-credential pattern also present in Class C's `api/login` (`routes/debug_api.php`) — noted as an existing overlap, not GAP-011's to reconcile. | **KEEP** |
| 5 | GET | `_debug/test-session-auth` | Reads `session('user')`, echoes session-auth state | closure | Class B redirect target, tested by `LegacyDebugRootRedirectTest`. | Yes. | **KEEP** |
| 6 | GET | `_debug/test` | Static "Server is working!" HTML | closure | Same URI substring also matched inside `login.blade.php`'s `_debug/test-login/...` links and inside the two test files above — but no consumer independently targets bare `_debug/test`. | No evidence found. | **REMOVE** (no consumer; trivially re-creatable if a smoke-check page is ever wanted) |
| 7 | GET | `_debug/test-mobile-optimization` | Renders `test-mobile-optimization` view | closure → view | Referenced only by `resources/views/testing-suite.blade.php:332` — but that JS array uses the **unprefixed** path `/test-mobile-optimization` (missing `_debug/`), which is not a registered route at all. The self-test hub's own link is dead. | No functioning consumer. | **REMOVE** — but see §1a (testing-suite hub) below before acting. |
| 8 | GET | `_debug/test-mobile-simple` | Renders `test-mobile-simple` view | closure → view | Same as above — `testing-suite.blade.php:338` links to unprefixed `/test-mobile-simple`, dead link. | No functioning consumer. | **REMOVE** — see §1a. |
| 9 | GET | `_debug/test-accessibility` | Renders `test-accessibility` view | closure → view | Same pattern — `testing-suite.blade.php:344` links to unprefixed `/test-accessibility`, dead link. | No functioning consumer. | **REMOVE** — see §1a. |
| 10 | GET | `_debug/test-simple` | Static "Simple Test" HTML, explicitly documented as having no middleware beyond the group | closure | None found. | No evidence found. | **REMOVE** |
| 11 | GET | `_debug/test-auth` | Static text, `->middleware(['auth'])` in addition to group gate | closure | None found. | No evidence found. | **REMOVE** |
| 12 | GET | `_debug/test-auth-direct` | Static text, `->middleware(['auth'])`, comment claims it "bypasses web middleware" (comment is stale/inaccurate — it is inside the same `web` group as every other `_debug/*` route) | closure | None found. | No evidence found. | **REMOVE** |
| 13 | GET | `_debug/test-minimal` | Static text, `->middleware([])` at the route level (group middleware, incl. `DebugGateMiddleware`, still applies — Laravel merges route + group middleware, an empty route-level array does not strip it) | closure | None found. | No evidence found. | **REMOVE** |
| 14 | GET | `_debug/test-bypass` | Static text, comment claims it "bypasses ALL middleware including web group" — **comment is inaccurate**: it is registered inside the same `web`-group `_debug` prefix as everything else and is gated identically. No functional bypass exists; only the misleading comment does. | closure | None found. | No evidence found. Comment content is worth correcting or removing regardless of disposition, so a future reader does not mistake this for an actual security bypass. | **REMOVE** |
| 15 | GET | `_debug/test-web-guard` | Static text, `->middleware(['auth:web'])` | closure | None found. | No evidence found. | **REMOVE** |
| 16 | GET | `_debug/admin-dashboard-test` | Static "Admin Dashboard Test" HTML | closure | `testing-suite.blade.php:350` links to unprefixed `/admin-dashboard-test` — dead link, same defect as #7-9. | No functioning consumer. | **REMOVE** — see §1a. |
| 17 | GET | `_debug/testing-suite` | Renders `testing-suite.blade.php` — the self-test hub page that links (with broken paths, see §1a) to several sibling `_debug/*` routes | closure → view | No test references it directly; it is a developer-facing index page, not consumed by other code. | No test-verified workflow depends on it. | **UNKNOWN** — see §1a. Disposition depends on whether the Owner wants the debug-route self-test hub repaired (worth keeping, links fixed) or retired (all consumers below become uncontested REMOVE). |
| 18 | GET | `_debug/performance-optimization` | Renders `performance-optimization` view | closure → view | None found — not linked from `testing-suite.blade.php`'s route-test array, no test coverage. | No evidence found. | **REMOVE** |
| 19 | GET | `_debug/final-integration` | Renders `final-integration` view | closure → view | None found. (A same-name grep hit against `api/v1/final-integration/*` — a real, unrelated production API namespace tested by `V1LegacyRouteHardeningContractTest` — was investigated and confirmed to be a false positive; no relation to this debug view.) | No evidence found. | **REMOVE** |
| 20 | GET | `_debug/tenant-dashboard-test` | Static "Tenant Dashboard Test" HTML | closure | `testing-suite.blade.php:356` links to unprefixed `/tenant-dashboard-test` — dead link, same defect as #7-9/16. | No functioning consumer. | **REMOVE** — see §1a. |
| 21 | GET | `_debug/test-login/{email}` | Looks up `User::where('email', $email)`, `Auth::login($user)` directly (no password), redirects to `/app/dashboard` | closure | **Live production-shipped consumer**: `resources/views/auth/login.blade.php:184-199` renders 6 "demo user" quick-login links directly targeting this route (`admin@zena.local`, `pm@zena.local`, `designer@zena.local`, `site@zena.local`, `qc@zena.local`, `finance@zena.local`). Also target of a Class B redirect and asserted present by both test files. | Yes — the login page itself links to it. Real, currently-functioning dev workflow. | **KEEP** — highest-confidence KEEP in the matrix. |

### 1a. The `testing-suite.blade.php` self-test hub finding

`_debug/testing-suite` (#17) renders a page whose own JavaScript `routeTests` array links to five sibling debug routes (#7 `test-mobile-optimization`, #8 `test-mobile-simple`, #9 `test-accessibility`, #16 `admin-dashboard-test`, #20 `tenant-dashboard-test`) — but every one of those links is missing the `_debug/` prefix (e.g. `url: '/test-mobile-optimization'` instead of `/_debug/test-mobile-optimization'`), and none of those bare paths are registered routes. This means the self-test hub is currently non-functional for that entire link set: clicking any of those five test cards in the running app would 404. There is a sixth entry (`Universal Frame Demo`, `url: '/test-universal-frame'`) that does not correspond to any Class A route at all — evidence the page has drifted from the route table for some time.

This is decision-relevant, not just trivia: it means "referenced by `testing-suite.blade.php`" is **not** usage evidence for #7, #8, #9, #16, #20 — the reference is dead. Two consistent paths forward, both compatible with the REMOVE recommendation above:
- Retire `_debug/testing-suite` and its five broken-linked siblings together (uncontested REMOVE for all six), or
- Repair `_debug/testing-suite`'s links (add the missing `_debug/` prefix) and keep it plus the five routes it then correctly reaches.

Recommendation below (§3) treats this as Owner-decidable at the retention-matrix level, not a structural-architecture question — either choice is compatible with any of Options A/B/C.

### 1b. Summary counts

- **KEEP: 5** — `dashboard-data`, `test-permissions`, `test-api-admin-stats`, `test-login-simple`, `test-login/{email}`
- **REMOVE: 14** — `test`, `test-mobile-optimization`, `test-mobile-simple`, `test-accessibility`, `test-simple`, `test-auth`, `test-auth-direct`, `test-minimal`, `test-bypass`, `test-web-guard`, `admin-dashboard-test`, `performance-optimization`, `final-integration`, `tenant-dashboard-test`
- **UNKNOWN (Owner input needed): 2** — `testing-suite` (repair vs. retire, §1a), and the adjacent-bug flag on `dashboard-data`'s frontend caller (not the route itself, which is KEEP)

---

## 2. Structural-boundary options

All three options are compatible with any retention-matrix outcome above — they are orthogonal decisions (which routes exist vs. where surviving routes are legally declared).

### Option A — Keep inline in `routes/web.php`, strengthen tests only

Retain the current `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])->group(...)` block and the 7 Class B redirects exactly where they are today; add the general invariant test from §4 without moving any code.

- **Production safety:** Unchanged — routes still register in the production route table (visible in `route:list`), still 404 at request time via `DebugGateMiddleware`. Safety depends entirely on that one middleware never being removed from the group.
- **Discoverability/maintainability:** Worst of the three — `_debug/*` routes remain interleaved with real application routes in an 800+ line file; a contributor has to already know to scroll to line 590 to find the boundary.
- **Failure mode on a wrong future addition:** A contributor adding `Route::get('_debug/new-thing', ...)` *inside* the existing group inherits the gate automatically (safe by construction). The failure mode is a route added *outside* the group prefix but still under `_debug/*` (or anywhere else) without the middleware — nothing currently stops this except code review.
- **Compatibility with local/testing/development:** No change — identical to today.
- **Effect on automated tests:** None beyond the new invariant test itself.
- **Route-cache/config-cache implications:** None — nothing environment-conditional changes.
- **Migration complexity:** None — this is the do-nothing-structurally option.
- **Rollback simplicity:** N/A (nothing moved).

### Option B — Extract to `routes/debug.php`, always registered, middleware defense stays

Move the Class A group (post-retention-matrix survivors) into a new `routes/debug.php`, `require`'d unconditionally from `routes/web.php` (or `RouteServiceProvider`) exactly like every other route file today. The whole file is wrapped in `Route::middleware([DebugGateMiddleware::class])->group(...)` (or the existing per-prefix group is preserved verbatim inside the new file).

- **Production safety:** Same as Option A — routes still exist in the production route table, still 404 at request time. The extraction is organizational, not a new defense layer.
- **Discoverability/maintainability:** Better — one file, one purpose, a contributor grepping `routes/` immediately sees `debug.php` as a distinct concern. `routes/web.php` shrinks by ~190 lines.
- **Failure mode on a wrong future addition:** A route added inside `debug.php` but outside its wrapping middleware group is still possible (same class of mistake as Option A, just now scoped to a smaller, more obviously "this is debug code" file — mildly lower probability, not eliminated).
- **Compatibility with local/testing/development:** No change.
- **Effect on automated tests:** `DebugRouteDocumentationInvariantTest`'s `route:list --path=_debug` assertions are unaffected (route table is identical, only source location moved). Any test that greps `routes/web.php` source text (none currently do — confirmed via search) would need updating; no such test exists today.
- **Route-cache/config-cache implications:** None — `require`'ing a route file unconditionally behaves identically to inline routes for `route:cache` purposes; the compiled cache is environment-agnostic either way (see Option C for where this changes).
- **Migration complexity:** Low — cut/paste plus one `require` line; mechanical.
- **Rollback simplicity:** High — revert is a single file move back.

### Option C — Dedicated route file + environment-gated registration + middleware defense-in-depth (recommended)

Same file extraction as Option B, but `routes/debug.php` is only `require`'d when `app()->environment(['local', 'testing', 'development'])` is true (the same pattern the codebase already uses for `routes/debug.php`'s existing empty/disabled state, for the `/debug/{path?}` wildcard redirect at `web.php:583`, and for every Class C helper). `DebugGateMiddleware` is **kept** on the route group inside the file, unchanged — this option adds a layer, it does not trade one for the other.

- **Production safety:** Strongest of the three — in production, the 16 (post-retention-matrix) Class A routes **do not exist in the route table at all** (not just 404 at request time). `route:list` in production shows zero `_debug/*` entries. This closes the gap the Gate 1 evidence explicitly flagged: today, "protected" and "absent" are different things, and Class B's `/debug/{path?}` wildcard is the one existing route that already gets this stronger treatment while the other 20 don't.
- **Discoverability/maintainability:** Same file-organization win as Option B, plus the environment condition is a single, obvious, one-line guard at the `require` call site — easy to audit in one glance (`grep -n "require.*debug.php" routes/web.php`).
- **Failure mode on a wrong future addition:** A route added inside `debug.php` is protected by *two* independent mechanisms now: the environment-gated `require` (route doesn't exist in production) and `DebugGateMiddleware` (404s even if somehow reached). A contributor would have to defeat both to create a production exposure — e.g. registering the route directly in `web.php` outside `debug.php` entirely, which is exactly the class of drift the §4 invariant test is designed to catch regardless of which option is chosen.
- **Compatibility with local/testing/development:** No change — `debug.php` still loads in all three named environments, identical behavior to today.
- **Effect on automated tests:** `DebugRouteDocumentationInvariantTest`'s `route:list --path=_debug` assertions **must run under an environment where the file is loaded** (`local`/`testing`) — this is already implicitly true today since `phpunit.xml` sets `APP_ENV=testing`, so no test behavior changes. A **new** production-focused test becomes possible for the first time under this option: asserting the production route table has zero `_debug/*` entries, which Option A/B cannot express as meaningfully (their production route table always has the 16 entries, gated-but-present).
- **Route-cache/config-cache implications:** This is the one place environment-conditional route registration needs explicit handling: Laravel's `route:cache` compiles whatever the route table looks like *at build time* into a single cached file. **If the deploy pipeline builds one route cache artifact and ships it to every environment, this option silently breaks** (production would ship with the build-environment's route table, whatever that was). If each environment builds its own cache from its own `APP_ENV` (the standard Laravel deploy pattern, and consistent with `DebugGateMiddleware` already being environment-aware at request time rather than needing build-time differentiation), this is a non-issue. **This repo's deploy pipeline must be confirmed before Gate 3** (see Open Question below) — it is the one piece of environment-specific evidence Gate 2 could not verify from the repo alone.
- **Migration complexity:** Same as Option B plus one `if` condition around the `require` — marginal increase.
- **Rollback simplicity:** High — revert is a single file move back plus removing the `if`.

**Open question requiring confirmation before Gate 3 implementation (not before Gate 2 Owner decision):** does this repo's deployment process build a single route cache shared across environments, or does each environment (`local`/`testing`/`development`/`staging`/`production`) run its own `php artisan route:cache` from its own `APP_ENV`? `.github/workflows/ci-cd.yml` was not found to reference `route:cache` in a way that resolves this from the repo alone within Gate 2's evidence scope; this should be confirmed as an implementation-readiness check, not blocking the Owner's Gate 2 architecture choice.

### Recommendation

**Option C.** It is strictly more defensive than A or B at roughly the same migration cost as B (B already pays the extraction cost; C adds one `if` condition), it closes the exact gap the Gate 1 evidence identified (two different protection mechanisms — runtime middleware vs. compile-time env-gate — with no single source of truth), and it makes Class A and Class B's remaining `/debug/{path?}` wildcard consistent with each other for the first time. The route-cache caveat is a real implementation-readiness risk, not a reason to prefer B — it must be confirmed either way before Gate 3, since Option B's "always registered" design would mask the same underlying deploy-pipeline question rather than resolve it.

---

## 3. Canonical protection contract

This is the enforceable invariant Gate 2 asks the Owner to adopt (worded for Option C; Options A/B are strict subsets with the environment-registration clause removed):

1. **Where may GAP-011 debug routes legally be declared?** Only inside `routes/debug.php`, inside the `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])->group(...)` block. No `_debug/*` route may be declared in `routes/web.php`, `routes/api.php`, or any other route file.
2. **In which environments may they be registered?** Only when `app()->environment(['local', 'testing', 'development'])` is true, checked once at the `require` call site — not per-route.
3. **Must every Class A route carry `DebugGateMiddleware` even though environment-registration already excludes production?** Yes — defense-in-depth is deliberate. Environment-gated registration and request-time middleware protect against different failure modes (a misconfigured `APP_ENV` in a non-production-but-not-explicitly-named environment vs. a route accidentally declared outside the intended file). Removing either weakens the other's blast-radius reduction.
4. **Required production behavior:** route **absent** — zero `_debug/*` entries in `php artisan route:list` under `APP_ENV=production` (or any environment not in `['local', 'testing', 'development']`).
5. **Required local/testing/development behavior:** route **present and functional**, identical to current behavior — `DebugGateMiddleware` passes the request through unchanged in these three environments.
6. **What must CI fail on?** Any of: (a) a `_debug/*`-prefixed route registered from a file other than `routes/debug.php`; (b) a route inside `routes/debug.php`'s group missing `DebugGateMiddleware` in its resolved middleware stack; (c) `routes/debug.php` reachable (its routes appearing in `route:list`) under `APP_ENV=production`. See §5 for the concrete test design.

---

## 4. Class B disposition — 7 redirects

| # | Method | Source URI | Target | Registered in | Consumer evidence | Compatibility value | Production necessity | Proposed disposition |
|---|---|---|---|---|---|---|---|---|
| 1 | GET\|HEAD | `/dashboard-data` | `/_debug/dashboard-data` | Always (all envs) | Tested end-to-end (301 + destination) by `LegacyDebugRootRedirectTest`. No other caller found (the live JS consumer, `dashboard-content.blade.php`, calls `_debug/dashboard-data` directly, not this redirect). | Legacy-URL compatibility for any bookmarked/hardcoded link to the pre-namespace path. | None claimed — target already 404s in production via `DebugGateMiddleware`; the redirect itself is harmless (redirects to a route that then 404s). | **Move under canonical debug boundary** — relocate into `routes/debug.php` alongside its target, register only when the target is registered (i.e. under Option C's environment gate). A redirect to a route that doesn't exist outside local/testing/development should itself only exist there — keeping it "always registered" while the target disappears in production (Option C) would silently turn this into a broken redirect-to-nothing in production. |
| 2 | GET\|HEAD | `/test-api-admin-dashboard` | `/_debug/test-api-admin-stats` | Always | Tested by `LegacyDebugRootRedirectTest`. | Same class as #1. | None claimed. | **Move under canonical debug boundary** — same reasoning as #1. |
| 3 | GET\|HEAD | `/test-permissions` | `/_debug/test-permissions` | Always | Tested by `LegacyDebugRootRedirectTest`. | Same class as #1. | None claimed. | **Move under canonical debug boundary** |
| 4 | GET\|HEAD | `/test-api-admin-stats` | `/_debug/test-api-admin-stats` | Always | Tested by `LegacyDebugRootRedirectTest`. | Same class as #1. | None claimed. | **Move under canonical debug boundary** |
| 5 | GET\|HEAD | `/test-session-auth` | `/_debug/test-session-auth` | Always | Tested by `LegacyDebugRootRedirectTest`. | Same class as #1. | None claimed. | **Move under canonical debug boundary** |
| 6 | GET\|HEAD | `/test-login/{email}` | `/_debug/test-login/{email}` | Always | Tested by `LegacyDebugRootRedirectTest`. | Same class as #1 — plus its target is the one route the production login page itself links to (via the `_debug/` path directly, not this redirect). | None claimed for the redirect itself. | **Move under canonical debug boundary** |
| 7 | GET\|HEAD | `/debug/{path?}` (wildcard) | `/_debug/{path}` | Already `local`-only | No test coverage found (grepped `tests/` for `/debug/{path` and literal `'/debug/`/`"/debug/` — none). | Convenience alias for typing `/debug/x` instead of `/_debug/x` during local development. | None — already excluded from production and all other environments today. | **Retain, environment-restricted (unchanged)** — already matches the Option C pattern (env-gated at registration); move into `routes/debug.php` for co-location but keep its `local`-only condition exactly as-is. Recommend adding test coverage under §5 since none exists today despite this being the one Class B route with zero current verification. |

**Rationale for "move" over "retain always" on redirects #1-6:** all six are currently registered in every environment including production, redirecting to a target that already 404s in production. Under Option A/B (target stays "always registered, middleware-gated") this is merely redundant-but-harmless. Under the recommended Option C (target becomes environment-registered), leaving these six "always registered" would create a new, previously-nonexistent failure mode: a production 301 redirect pointing at a 404. Moving them into the same environment-gated file as their targets keeps the contract "redirect exists iff its destination exists" true in every environment, which is not true of the status quo and would become actively wrong under Option C if left unaddressed.

---

## 5. Anti-drift tests (design, not implementation)

The existing `DebugRouteDocumentationInvariantTest` (GAP-027) stays, unmodified, scoped to what it already does: verifying `ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md`'s documentation claims match a fixed list of route URIs via `route:list --json --path=_debug`. It is a **documentation-consistency** test, not a security/architecture guard, and GAP-011 must not lean on it as one — per Gate 1's explicit instruction on this point.

Gate 2 specifies a new, separate test suite (final class name chosen at implementation time, e.g. `DebugRouteBoundaryInvariantTest`) built around **general, structural** assertions rather than another hand-maintained URI allowlist:

1. **Single-declaration-site test:** parse (or use `route:list --json`'s `action`/file evidence, or a static scan of `routes/*.php`) to assert every currently-registered route whose URI starts with `_debug/` resolves to a closure/controller declared inside `routes/debug.php`. Fails if any `_debug/*` route is found declared from `routes/web.php` or any other file. This is what catches "a future contributor adds a new route incorrectly" generically — it does not need to know the route's name in advance, only that its URI prefix is `_debug/`.
2. **Universal-middleware test:** for every route matched by the same `_debug/*` prefix scan, assert `DebugGateMiddleware` is present in `Route::gatherMiddleware()` (or the equivalent resolved middleware list) for that route. Fails on any future `_debug/*` route added without the gate, regardless of name — same generality property as #1.
3. **Class B redirect-destination test:** for every route registered via `Route::permanentRedirect(...)` (or `Route::redirect`) whose destination matches `_debug/*`, assert the destination URI is itself present in the `_debug/*` route list gathered by test #1/#2 (i.e., no redirect points at a debug destination that doesn't exist / isn't gated). Generalizes `LegacyDebugRootRedirectTest`'s current fixed-map assertion into "no compatibility redirect points to an unprotected or nonexistent debug destination," per Gate 1/2's instruction.
4. **Production-absence test:** boot the application (or resolve routes) with `APP_ENV=production` (matching the existing Gate 1 evidence methodology — no `.env`, default resolves to `production`) and assert `route:list --path=_debug` returns zero entries, and that none of the 6 always-registered Class B redirects (or the relocated equivalents) exist either once they move under the environment-gated boundary. This is the test that gives Option C's "route absent, not just 404" claim executable proof, and is the one new capability Option A/B cannot produce (their production route table always has entries by design, so this test would need to assert "present but 404" instead — see acceptance scenarios below for the A/B-equivalent phrasing).
5. **Local/testing-presence test:** boot with `APP_ENV=local` and `APP_ENV=testing` and assert the retained-post-retention-matrix `_debug/*` routes and Class B redirects **do** exist and resolve as designed (this is largely what `LegacyDebugRootRedirectTest` and `DebugRouteDocumentationInvariantTest` already do for the current inventory — test #5 is the generalized shape that survives the retention-matrix pruning in §1).

Because tests #1-#3 assert structural properties (declaration file, middleware presence, redirect-destination membership) rather than enumerating specific URIs, a future contributor adding an ungated `_debug/*` route anywhere else in the app fails test #1 or #2 automatically without this suite needing to be hand-updated — this satisfies §6's "adding a future unprotected `_debug/*` route makes CI fail automatically" requirement generically, independent of which specific routes survive §1's retention matrix.

---

## 6. Acceptance scenarios

### Production
- Direct request to any surviving Class A route (e.g. `_debug/test-login/{email}`) → **Option C:** 404, route does not exist in the route table at all (no route-not-found is distinguishable from a gate-blocked 404 by inspecting `route:list`, which shows zero entries). **Option A/B:** 404 via `DebugGateMiddleware`, route *does* appear in `route:list` but its handler aborts before executing.
- Direct request to any Class B redirect (e.g. `/test-login/{email}`) → **Option C:** 404, redirect route itself absent from the table (moved under the same environment gate as its target). **Option A/B:** 301 to the `_debug/*` target, which then itself 404s per the line above (two-hop failure, both legs already covered by today's `LegacyDebugRootRedirectTest` pattern for the target leg).
- `route:list --path=_debug` under `APP_ENV=production` (default, no `.env`) → **Option C:** empty. **Option A/B:** the post-retention-matrix Class A list (5 or more, depending on the §1a decision), every one middleware-gated.

### Local
- Every retained Class A route responds as designed (200/redirect per its own logic) under `APP_ENV=local`.
- `_debug/test-login/{email}` (the retained login/dev helper) logs the requested user in and redirects to `/app/dashboard`, matching current behavior exactly — this is the route the production login page's demo links depend on, so its local/testing behavior must be byte-for-byte unchanged.
- All 6 always-registered Class B redirects (relocated per §4) 301 to their targets, matching `LegacyDebugRootRedirectTest`'s existing map.
- The `/debug/{path?}` wildcard redirect continues to work under `local` only, now with the new test coverage from §5 item 5 (previously untested).

### Testing
- `tests/Feature/DebugRouteDocumentationInvariantTest.php` and `tests/Feature/LegacyDebugRootRedirectTest.php` continue to pass unmodified against the post-retention-matrix route set (their fixed lists are updated to drop the 14 REMOVE routes and reflect any §1a outcome — this is a data update to those tests' expectation arrays, not a change to what they assert).
- A deliberately-introduced test fixture (temporary, implementation-time-only, not part of the final suite) registering an ungated `_debug/*` route outside `routes/debug.php` is used to prove tests #1/#2 from §5 actually fail before the real implementation ships — this is the concrete demonstration that "future ungated route mutation is caught," not just an assertion that it should be.

### Rollback criteria
- If, after implementation, any of the 5 KEEP routes (or the Owner's §1a-informed set) stops resolving in `local`/`testing`/`development` for end users or the login page's demo links, revert `routes/debug.php`'s extraction (Option B/C's rollback is a single file move, per §2) rather than patching forward.
- If the route-cache open question from §2 turns out to break production route resolution (Option C only), the immediate rollback is dropping the environment condition on the `require` (falling back to Option B's always-registered-but-gated behavior) while the deploy-pipeline question is resolved — this does not require re-inlining into `web.php`.
- Any rollback preserves `DebugGateMiddleware` unchanged and untouched at every step — it is not part of what could need rolling back.

---

## 7. Scope lock (carried from Gate 1, unchanged)

Explicitly excluded from this Gate 2 design and from GAP-011 implementation entirely:
- `local/dev-login/operator`
- `routes/debug_api.php` (all routes, including `api/login`, `api/v1/upload-document`)
- `routes/api-simple.php` (already remediated separately, see Gate 1 evidence citing commit `7d33620e`)
- Laravel Dusk's self-registered `_dusk/*` routes (package-owned, not GAP-011's code)
- Bare `login`/`logout`/`password/reset` and core auth architecture questions
- All Class C findings (view-only local helpers, internal redirects to real routes, `api/test*` helpers) — listed in Gate 1 as discovered evidence only
- Any production business route unrelated to Class B

No new Work ID is minted for Class C findings during this task. If Class C remediation is warranted, it is raised as a separate follow-up gap candidate for a future, independent Gate 1 — not decided, scoped, or implied here.

---

## Decision Needed

Owner chooses one:
- **APPROVE** — Option C (recommended), the retention matrix's 5 KEEP / 14 REMOVE dispositions, the §1a `testing-suite` treatment of your choice (repair or retire — please specify), and the Class B "move under canonical boundary" disposition for all 7 redirects, as the design to carry into an implementation plan.
- **CHANGES** — name which retention-matrix rows, which structural option, or which invariant-contract clause should change before this proceeds.
- **DECLINE** — do not proceed with GAP-011 Class A/B remediation at this time.
- **DEFER** — revisit at a later date; no design decision recorded now.

## What the owner is NOT being asked to decide

Not being asked to approve specific class/file names, the exact PHP test-writing mechanics, or the literal contents of `routes/debug.php` — only: which structural option (A/B/C), which of the 21 Class A routes survive, what happens to the `testing-suite` self-test hub (§1a), and how the 7 Class B redirects are treated. Also not being asked to decide anything about Class C (§7) or about the adjacent `dashboard-content.blade.php` frontend bug flagged in §1 row 1 — both are explicitly out of scope for this decision and would need their own separate request if the Owner wants them pursued.
