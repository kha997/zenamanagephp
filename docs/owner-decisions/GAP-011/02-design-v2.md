---
work_id: GAP-011
gate: 2
gate_status: changes_requested
owner_decision:
  value: changes_requested
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/GAP-011-debug-route-cleanup-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/260
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-13T16:58:30+07:00"
  owner_response_reference: "Owner Gate 2 round 2 decision — CHANGES REQUESTED, recorded in-session on 2026-08-13 at fresh wall-clock time 2026-08-13T16:58:30+07:00 (this is the actual time the decision was recorded, not an estimate). Substantive design direction accepted in principle: Option C remains preferred; Class A retention 2 KEEP (_debug/dashboard-data, _debug/test-login/{email}) / 19 REMOVE / 0 UNKNOWN accepted; testing-suite RETIRE accepted; Class B 6 REMOVE / 1 KEEP (/debug/{path?}) accepted; Class C scope lock accepted; per-target production route:cache evidence accepted, no longer an open Gate 3 question. Gate 2 not approved because governance history and implementation topology require correction, per 8 points: (1) repair Gate 2 decision immutability — round 1 already received a CHANGES REQUESTED decision that this packet's frontmatter never recorded; 02-design.md must be frozen as the round-1 packet (gate_status: changes_requested, owner_decision.value: changes_requested, superseded_by: 02-design-v2.md), this file (round 2, previously 02-design.md at commit 832da3ffdc862d5acbcb46fff7a3ebc503123ac0) must be frozen as 02-design-v2.md recording this round-2 CHANGES REQUESTED decision with superseded_by: 02-design-v3.md, and a new 02-design-v3.md created as the active awaiting_owner packet; (2) correct chronology to authoritative Git commit timestamps for historical content and fresh wall-clock timestamps for decisions recorded now — the prior recorded_at of 2026-08-13T17:20:00+07:00 postdated this file's actual commit timestamp of 2026-08-13T16:47:04+07:00, which is not a valid chronology and must not be preserved; (3) correct Option C's registration architecture — routes/debug.php already exists and RouteServiceProvider.php already conditionally loads it under 'local' only; Option C must repurpose this existing loader (widen its environment condition to ['local','testing','development']) rather than add a second require inside routes/web.php, guaranteeing routes/debug.php is registered exactly once per allowed environment; (4) preserve the accepted 2 KEEP / 19 REMOVE retention decision unchanged by the loader-topology correction; (5) do not remove VerifyCsrfToken::$except's bare 'test-login-simple' entry under GAP-011 — it does not URI-match the Class A route '_debug/test-login-simple' (different path, no prefix), so it is not established as belonging to the route being removed; treat it as separate discovered cleanup evidence only, leave the file untouched; (6) be conservative with view-file deletion — delete a view file only if a direct search (view name, Blade include/extends/component reference, controller/route view() reference) proves it orphaned outside the removed GAP-011 route, otherwise leave it in place; (7) preserve the full invariant contract restated for the corrected RouteServiceProvider-owned topology; (8) re-present Gate 2 as round 3 with the corrected supersession chain, authoritative timestamps, exact head SHA, Option C loader topology, anticipated implementation files, unchanged retention matrices, and exact-head CI status. Do not implement; do not edit routes/tests/runtime code; do not mark PR ready; do not merge; do not self-approve Gate 2."
  reconciliation_required: false
supersedes: null
superseded_by: "docs/owner-decisions/GAP-011/02-design-v3.md"
timestamps:
  created_at: "2026-08-13T16:47:04+07:00"
  updated_at: "2026-08-13T16:58:30+07:00"
generated_by: agent
---

## [FROZEN — ROUND 2, SUPERSEDED BY 02-design-v3.md] OWNER GATE 2: CHANGES REQUESTED — design surface for Class A + Class B only

**Governance note (added at `2026-08-13T16:58:30+07:00`, as part of repairing this work item's decision-immutability chain — see `02-design-v3.md` §0 for the full repair record):** this file's body below is preserved verbatim as originally committed at `832da3ffdc862d5acbcb46fff7a3ebc503123ac0` (`2026-08-13T16:47:04+07:00`), where it was filed under the path `02-design.md`. It has been relocated to `02-design-v2.md` and its frontmatter corrected to reflect the Owner's round-2 **CHANGES REQUESTED** decision (verbatim in `decision_provenance.owner_response_reference` above) — this file's original frontmatter incorrectly claimed `gate_status: awaiting_owner` with a `recorded_at` timestamp (`2026-08-13T17:20:00+07:00`) that postdated its own commit time, an invalid chronology now corrected. **No body text below has been altered.** The corrected, superseding design is `02-design-v3.md`.

## OWNER GATE 2 (round 2, as originally presented): AWAITING OWNER DECISION — design surface for Class A + Class B only

This packet does not implement anything. No route, middleware, test, or file has changed. It designs the canonical protection boundary for GAP-011's Owner-approved scope (Class A: 21 gated `/_debug/*` routes; Class B: 7 compatibility redirects) and asks the Owner to choose a structural architecture, a per-route disposition, and the invariant that will guard the boundary going forward. **Class C is out of scope by binding Gate 1 governance clarification and is not designed, decided, or bundled here.**

This is round 2, incorporating the Owner's round-1 CHANGES REQUESTED review (recorded verbatim in `decision_provenance.owner_response_reference` above). Every correction is applied below; §0 maps each of the 9 points to where it was resolved.

## 0. Round-1 corrections applied

| # | Owner correction | Where resolved |
|---|---|---|
| 1 | Test/redirect existence alone is not consumer evidence — re-evaluate `test-permissions`, `test-api-admin-stats`, `test-login-simple`, `test-session-auth` | §1, rows 2-5: re-evaluated, all 4 changed from KEEP to **REMOVE** |
| 2 | `test-login/{email}` stays KEEP, but rationale must say local/testing/development-only, not production | §1, row 21: rationale corrected |
| 3 | `testing-suite` = RETIRE, not repair; take its 5 dead-linked siblings with it | §1, row 17 + §1a: now **REMOVE**, no UNKNOWN remains |
| 4 | Resolve the route-cache question in Gate 2, not defer to Gate 3 | §2, Option C: resolved with `deploy-production.sh` evidence, no longer an open question |
| 5 | Tighten defense-in-depth wording — both layers share `APP_ENV` dependence | §3, item 3: reworded precisely |
| 6 | Re-evaluate all 7 Class B redirects on real-consumer evidence, default REMOVE | §4: 6 of 7 now **REMOVE**, `/debug/{path?}` KEEP with stated purpose + new test requirement |
| 7 | Make the invariant executable — static source-level guard, not `route:list` action-metadata inference | §3 item 6, §5: rewritten around a deterministic static scan |
| 8 | Preserve Class C scope lock, do not touch `dashboard-content.blade.php` | §1 row 1, §7: unchanged, still flag-only |
| 9 | Re-present with zero UNKNOWN | §1b: 0 UNKNOWN, 2 KEEP / 19 REMOVE |

## Gate 2 baseline reconfirmation (round 2)

- `origin/main` re-fetched and confirmed still at `1024b68640c2aeddc924620ef7be2885339fecec` — unchanged since Gate 1 freeze and since round 1.
- `docs/GAP-011-debug-route-cleanup-gate1-prep` remains Gate 1's 4 commits plus round 1's design commit on top of that baseline — no drift, nothing to reconcile.
- CI at PR #260's current head: `Owner Governance Lint` pass, `test-routes-guardrails` pass.

## Authorization boundary

- Gate 1: **APPROVED** (scope: Class A + Class B only)
- Gate 2: **AWAITING OWNER DECISION** (round 2)
- Gate 3: **NOT STARTED**
- Implementation authorized: **NO**
- Merge/release authorized: **NO**

---

## 1. Class A retention matrix — all 21 `/_debug/*` routes (round 2)

Method unchanged from round 1: read `routes/web.php:590-778` for source/handler, then search `tests/`, `resources/views/`, `app/`, `scripts/`, `.github/workflows/`, `database/` for each route's URI. **Round-2 correction applied throughout:** a regression test asserting a route exists, or a Class B redirect landing on it, is test-only preservation evidence, not a real consumer — it is labeled as such explicitly in the "Evidence classification" column below and never used alone to justify KEEP. Every row's evidence is classified into exactly one of: **real workflow consumer** (a human clicks/types it as part of an actual dev workflow), **script/tool/view/code consumer** (another part of the codebase calls it programmatically), **documentation-only reference** (mentioned in docs/audits, not executed), or **test-only preservation** (exists only because a test asserts it exists).

| # | Method | URI | Purpose (source) | Handler | Evidence classification | Usage evidence found | Disposition |
|---|---|---|---|---|---|---|---|
| 1 | GET | `_debug/dashboard-data` | Mock dashboard KPI JSON fixture (closure, `web.php:592`) | inline closure | **Script/tool/view/code consumer** — real, but currently non-functional in production | `resources/views/app/dashboard-content.blade.php:355` (`fetch('/_debug/dashboard-data')`), reachable from `resources/views/layouts/app-layout.blade.php:83`, the production-rendered app layout. Called from `retry()`/`refreshNotifications()` (user-triggered, not on page load). Already 404s in production today via `DebugGateMiddleware`, so this call is currently a silent no-op there — not a byte of production behavior changes if the route is removed today. Also test-only preservation in `DebugRouteDocumentationInvariantTest`/`LegacyDebugRootRedirectTest` (redirect-target assertion). | **KEEP** — real code consumer exists (even though presently broken in the one environment where it would matter). The frontend call itself is a separate, adjacent defect — flagged, not touched, per §7 scope lock. |
| 2 | GET | `_debug/test-permissions` | Renders `test-permissions` view | closure → view | **Test-only preservation** | Searched `*.php`/`*.blade.php`/`*.js` repo-wide (excluding `vendor/`, `tests/`, `node_modules/`) for `test-permissions` — **zero** matches outside `tests/Feature/DebugRouteDocumentationInvariantTest.php` and `tests/Feature/LegacyDebugRootRedirectTest.php`. No form, link, script, or documented developer workflow reaches it. The only reason it "exists" from a usage standpoint is that a Class B redirect points at it and a test locks that redirect in place — both are test-only preservation, not consumer evidence. | **REMOVE** — no real consumer beyond tests/legacy redirect; default per Owner correction #1. |
| 3 | GET | `_debug/test-api-admin-stats` | Calls the real `Api\Admin\DashboardController::getStats` with no auth | `Api\Admin\DashboardController@getStats` | **Test-only preservation** | Same repo-wide search for `test-api-admin` — zero matches outside the two test files (which assert two Class B redirects landing here). No script, view, or documented workflow calls this URI directly; the real controller it delegates to is separately reachable through its authenticated production route and is unaffected by removing this bypass. | **REMOVE** — no real consumer beyond tests/legacy redirects; default per Owner correction #1. |
| 4 | POST | `_debug/test-login-simple` | Hardcoded-password (`zena1234`) demo login for 3 fixed emails, session-only fake user object (no `Auth::login`) | closure | **Test-only preservation, plus one piece of orphaned infrastructure** | Repo-wide search for `test-login-simple` outside `tests/` and `routes/web.php` found exactly one hit: `app/Http/Middleware/VerifyCsrfToken.php:14`, which lists `'test-login-simple'` in the CSRF-exempt URI list. No form or script anywhere in `resources/` posts to this URI — the CSRF exemption is itself orphaned (configured for a caller that does not exist in the repo). No real consumer found. | **REMOVE** — no real consumer beyond tests; the orphaned CSRF exemption entry is additional evidence of dead surface, not a reason to keep it. Removing the route should also remove the now-fully-orphaned `'test-login-simple'` entry from `VerifyCsrfToken::$except` (anticipated implementation file, §9). |
| 5 | GET | `_debug/test-session-auth` | Reads `session('user')`, echoes session-auth state | closure | **Test-only preservation** | Repo-wide search for `test-session-auth` outside `tests/` and `routes/web.php` — zero matches. No real consumer. | **REMOVE** — no real consumer beyond tests/legacy redirect; default per Owner correction #1. |
| 6 | GET | `_debug/test` | Static "Server is working!" HTML | closure | No evidence of any kind beyond incidental substring matches inside unrelated `_debug/test-login/...` strings | None. | **REMOVE** (unchanged from round 1) |
| 7 | GET | `_debug/test-mobile-optimization` | Renders `test-mobile-optimization` view | closure → view | **Documentation-only / dead reference** | Linked only from `testing-suite.blade.php:332`, and that link is missing the `_debug/` prefix — not a registered route, dead on click. | **REMOVE** — part of the `testing-suite` RETIRE decision, §1a. |
| 8 | GET | `_debug/test-mobile-simple` | Renders `test-mobile-simple` view | closure → view | **Documentation-only / dead reference** | Same defect — `testing-suite.blade.php:338`, dead link. | **REMOVE** — RETIRE cluster, §1a. |
| 9 | GET | `_debug/test-accessibility` | Renders `test-accessibility` view | closure → view | **Documentation-only / dead reference** | Same defect — `testing-suite.blade.php:344`, dead link. | **REMOVE** — RETIRE cluster, §1a. |
| 10 | GET | `_debug/test-simple` | Static "Simple Test" HTML | closure | None found | None. | **REMOVE** (unchanged) |
| 11 | GET | `_debug/test-auth` | Static text, `->middleware(['auth'])` | closure | None found | None. | **REMOVE** (unchanged) |
| 12 | GET | `_debug/test-auth-direct` | Static text, stale comment claims it "bypasses web middleware" (inaccurate — same `web` group as everything else) | closure | None found | None. | **REMOVE** (unchanged) |
| 13 | GET | `_debug/test-minimal` | Static text, `->middleware([])` at route level (group middleware, incl. `DebugGateMiddleware`, still applies) | closure | None found | None. | **REMOVE** (unchanged) |
| 14 | GET | `_debug/test-bypass` | Static text, stale comment claims it "bypasses ALL middleware" (inaccurate — no functional bypass exists) | closure | None found | None. | **REMOVE** (unchanged) |
| 15 | GET | `_debug/test-web-guard` | Static text, `->middleware(['auth:web'])` | closure | None found | None. | **REMOVE** (unchanged) |
| 16 | GET | `_debug/admin-dashboard-test` | Static "Admin Dashboard Test" HTML | closure | **Documentation-only / dead reference** | `testing-suite.blade.php:350` links to unprefixed `/admin-dashboard-test` — dead link, same defect as #7-9. | **REMOVE** — RETIRE cluster, §1a. |
| 17 | GET | `_debug/testing-suite` | Renders `testing-suite.blade.php`, the self-test hub whose own links (to #7,#8,#9,#16,#20) are dead (missing `_debug/` prefix) plus one link (`/test-universal-frame`) to a route that never existed at all | closure → view | **Documentation-only / dead reference** — the hub itself has no external consumer, and its own consumers are broken | No test references it; no code calls it; its own linked children are unreachable through it as currently written. | **REMOVE (RETIRE)** — Owner decision, round 2 correction #3: do not repair, retire the hub and its whole dead-linked cluster. |
| 18 | GET | `_debug/performance-optimization` | Renders `performance-optimization` view | closure → view | None found | None. | **REMOVE** (unchanged) |
| 19 | GET | `_debug/final-integration` | Renders `final-integration` view | closure → view | None found. (Confirmed false-positive against unrelated `api/v1/final-integration/*` production API namespace.) | None. | **REMOVE** (unchanged) |
| 20 | GET | `_debug/tenant-dashboard-test` | Static "Tenant Dashboard Test" HTML | closure | **Documentation-only / dead reference** | `testing-suite.blade.php:356` links to unprefixed `/tenant-dashboard-test` — dead link, same defect as #7-9/16. | **REMOVE** — RETIRE cluster, §1a. |
| 21 | GET | `_debug/test-login/{email}` | Looks up `User::where('email', $email)`, `Auth::login($user)` directly (no password), redirects to `/app/dashboard` | closure | **Real workflow consumer — local/testing/development only, corrected** | `resources/views/auth/login.blade.php:184-199` renders 6 "demo user" quick-login links directly targeting this route — but that whole block is itself wrapped in `@if(app()->environment(['local', 'testing', 'development']))` in `login.blade.php` (verified by re-reading the surrounding Blade conditional, not just the link lines). **Correction from round 1: this is not a production consumer.** It is a verified, currently-functioning **local/testing/development developer-login workflow** — the login page only ever renders these links in the same three environments Class A routes are gated to, so the consumer and the route share the identical environment scope. | **KEEP** — real, environment-scoped developer workflow consumer, not a production one. Its current non-production behavior must be preserved exactly under any structural option chosen. |

### 1a. The `testing-suite.blade.php` self-test hub — RETIRE (Owner decision, round 2)

`_debug/testing-suite` (#17) renders a page whose own JavaScript `routeTests` array links to five sibling debug routes (#7, #8, #9, #16, #20) using paths missing the `_debug/` prefix (dead, 404 on click) plus a sixth entry (`Universal Frame Demo`, `/test-universal-frame`) matching no route at all. The Owner has decided: **do not repair these links.** The hub has no verified external consumer, and repairing a dead self-test surface purely to justify keeping its children would be spending GAP-011 scope on the exact kind of manufactured-justification the retention-evidence rule (Owner correction #1) exists to prevent. All six routes in this cluster (`testing-suite`, `test-mobile-optimization`, `test-mobile-simple`, `test-accessibility`, `admin-dashboard-test`, `tenant-dashboard-test`) are **REMOVE**.

### 1b. Summary counts (round 2)

- **KEEP: 2** — `dashboard-data` (real code consumer, currently broken in production, adjacent bug flagged not fixed), `test-login/{email}` (real local/testing/development-only developer-login workflow)
- **REMOVE: 19** — every other route in the matrix above
- **UNKNOWN: 0**

---

## 2. Structural-boundary options

All three options are compatible with the retention-matrix outcome above — they are orthogonal decisions (which 2 routes survive vs. where they are legally declared).

### Option A — Keep inline in `routes/web.php`, strengthen tests only

Retain the current `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])->group(...)` block (pruned to the 2 KEEP routes) and add the general invariant tests from §5 without moving any code.

- **Production safety:** Unchanged — routes still register in the production route table, still 404 at request time via `DebugGateMiddleware`.
- **Discoverability/maintainability:** Worst of the three — `_debug/*` routes remain interleaved with real application routes.
- **Failure mode on a wrong future addition:** A route added *inside* the existing group inherits the gate automatically. A route added *outside* it (anywhere else under `_debug/*`) is caught only by the §5 static guard test, not by structure.
- **Compatibility with local/testing/development:** No change.
- **Effect on automated tests:** None beyond the new invariant tests.
- **Route-cache/config-cache implications:** None.
- **Migration complexity:** None.
- **Rollback simplicity:** N/A.

### Option B — Extract to `routes/debug.php`, always registered, middleware defense stays

Move the 2 surviving Class A routes into a new `routes/debug.php`, `require`'d unconditionally, wrapped in the same `DebugGateMiddleware` group.

- **Production safety:** Same as Option A — routes still exist in the production route table, still 404 at request time.
- **Discoverability/maintainability:** Better — one file, one purpose.
- **Failure mode on a wrong future addition:** Same class of mistake as Option A, scoped to a smaller, more obviously debug-only file.
- **Compatibility with local/testing/development:** No change.
- **Effect on automated tests:** `DebugRouteDocumentationInvariantTest`'s route-table assertions unaffected (source location moved, route table identical).
- **Route-cache/config-cache implications:** None — unconditional `require` behaves identically to inline routes.
- **Migration complexity:** Low — mechanical cut/paste plus one `require` line.
- **Rollback simplicity:** High.

### Option C — Dedicated route file + environment-gated registration + middleware defense-in-depth (recommended)

Same extraction as Option B, but `routes/debug.php` is only `require`'d when `app()->environment(['local', 'testing', 'development'])` is true — the same pattern already used for the retained `/debug/{path?}` wildcard redirect (`web.php:583`) and for every Class C helper. `DebugGateMiddleware` is **kept** on the route group, unchanged.

- **Production safety:** Strongest — in production, the 2 surviving Class A routes **do not exist in the route table at all**, not merely 404 at request time. `route:list` in production shows zero `_debug/*` entries.
- **Discoverability/maintainability:** Same file-organization win as Option B, plus a single, auditable environment condition at the `require` call site.
- **Failure mode on a wrong future addition:** Protected by two layers — see §3 item 3 for the precise (corrected) relationship between them.
- **Compatibility with local/testing/development:** No change — `debug.php` still loads in all three named environments.
- **Effect on automated tests:** `DebugRouteDocumentationInvariantTest` continues to run under `APP_ENV=testing` (already true today via `phpunit.xml`), unaffected. A new production-focused test becomes possible: asserting the production route table has zero `_debug/*` entries (§5 item 4).
- **Route-cache/config-cache implications — resolved in this round, no longer an open question:** `deploy-production.sh:143-160` is this repository's authoritative production deployment path. It runs `docker-compose -f "$DOCKER_COMPOSE_FILE" exec app php artisan migrate --force`, then `config:cache`, `route:cache`, `view:cache` — all executed via `exec` **inside the running container on the target host**, against that host's own `production.env`/`APP_ENV`. This is a per-environment build, not a single shared artifact distributed across environments: `git pull` → `composer`/`npm` install → `migrate` → `config:cache` → `route:cache` → `view:cache`, all on the production host itself. No conflicting deploy path was found (`deploy.sh`, `scripts/deploy-production.sh`, `.github/workflows/ci-cd.yml` were also checked — CI only ever runs tests under `APP_ENV=testing`/`ci`, it does not build or ship a production route cache). **Option C is confirmed compatible with this repository's current production-deployment architecture.** This removes the round-1 "unresolved until Gate 3" framing entirely; what remains for implementation time (not a Gate 2 blocker) is writing the test from §5 item 4 that proves route absence survives an actual `route:cache` run, not just an uncached route resolution.
- **Migration complexity:** Same as Option B plus one `if` condition.
- **Rollback simplicity:** High — revert is a single file move back plus removing the `if`.

### Recommendation

**Option C**, unchanged from round 1 and now on firmer ground: the route-cache risk that was the one open item is resolved by direct evidence (`deploy-production.sh`) rather than left as a hypothetical. Option C is strictly more defensive than A or B at roughly Option B's migration cost, and gives Class A the same environment-gated-absence property Class B's `/debug/{path?}` wildcard already has.

---

## 3. Canonical protection contract (round 2 — wording corrected on item 3)

1. **Where may GAP-011 debug routes legally be declared?** Only inside `routes/debug.php`, inside the `Route::prefix('_debug')->middleware([DebugGateMiddleware::class])->group(...)` block. No `_debug/*` route may be declared in `routes/web.php`, `routes/api.php`, or any other route file.
2. **In which environments may they be registered?** Only when `app()->environment(['local', 'testing', 'development'])` is true, checked once at the `require` call site.
3. **Must every Class A route carry `DebugGateMiddleware` even though environment-registration already excludes production?** Yes — but the two layers are **not independent defenses against a misconfigured `APP_ENV`**, and this design must not claim they are. Corrected statement of what each layer actually does:
   - **Environment-gated registration** protects against accidental route *presence* when `APP_ENV` is correctly configured as `production` (or anything outside the three named environments) — it prevents the route from ever entering the route table in a correctly-configured environment.
   - **`DebugGateMiddleware`** protects against *declaration/mounting mistakes* — a route accidentally declared outside `routes/debug.php`, or a future refactor that changes how/when `routes/debug.php` is loaded — by denying the request at execution time even if the route does end up present.
   - **Neither layer, independently or together, protects a production host whose `APP_ENV` is itself falsely resolved to `local`, `testing`, or `development`.** Both checks read the same `app()->environment(...)` state; a misconfigured environment defeats both simultaneously, not just one. This is a configuration-contract requirement, not a code-level one: **the production deployment/configuration contract must guarantee that a production host never resolves `APP_ENV` (or equivalent environment detection) to `local`, `testing`, or `development`.** This is already an implicit assumption the codebase relies on everywhere `DebugGateMiddleware` and every Class C env-gate depend on — GAP-011 does not introduce this dependency, it inherits and makes it explicit.
4. **Required production behavior:** route **absent** — zero `_debug/*` entries in `php artisan route:list` under `APP_ENV=production` (or any environment not in `['local', 'testing', 'development']`), verified **after** `route:cache` (not only against uncached route resolution — see §5 item 4).
5. **Required local/testing/development behavior:** route **present and functional**, identical to current behavior for the 2 KEEP routes.
6. **What must CI fail on?** Any of: (a) a `_debug/*`-prefixed route whose declaring file (determined by a **deterministic static source scan of `routes/*.php`**, not `route:list`'s `action` metadata — see rationale below) is not `routes/debug.php`; (b) a route inside `routes/debug.php`'s group missing `DebugGateMiddleware` in its resolved middleware stack (checked at runtime via `Route::gatherMiddleware()`, which is the correct tool for this specific check since middleware resolution genuinely is a runtime property); (c) `routes/debug.php`'s routes appearing in `route:list` under `APP_ENV=production`, checked both uncached and after `route:cache`.

**Why declaration-site ownership must be a static source guard, not `route:list` action metadata (Owner correction #7):** `route:list --json`'s `action` field reports a closure's file and line, or a controller class — this is accurate for *where the closure/controller is defined*, but is not guaranteed to reflect *which file called `Route::get(...)`* in every case (e.g. a route registered via a helper function, a macro, or programmatically from a loop) — inferring "declared in `routes/debug.php`" from `action` metadata alone risks both false negatives (a legitimately-declared route whose action metadata doesn't cleanly resolve to the file) and false positives (a route whose closure happens to be defined in a file that isn't actually where `Route::get()` was called). The reliable, deterministic check is a static text/AST scan of `routes/*.php` (excluding `routes/debug.php` itself) asserting no file contains a route definition whose first path segment is `_debug`, combined with the reverse — every `_debug/*` route in `route:list`'s output is confirmed to exist by grep in `routes/debug.php`'s source and nowhere else in `routes/*.php`. Implementation detail (regex vs. `nikic/php-parser` AST scan) is left to implementation time; Gate 2 fixes only that the check must be source-level and file-based, not `route:list` action-metadata-based.

---

## 4. Class B disposition — 7 redirects (round 2, re-evaluated on real-consumer evidence)

| # | Method | Source URI | Target | Evidence classification | Real consumer evidence | Proposed disposition |
|---|---|---|---|---|---|---|
| 1 | GET\|HEAD | `/dashboard-data` | `/_debug/dashboard-data` | **Test-only preservation** | Repo-wide search for the bare `/dashboard-data` path outside `tests/` and `routes/web.php` found no caller — the one real consumer of the *target* (`dashboard-content.blade.php`) calls `/_debug/dashboard-data` directly, never this redirect. Only `LegacyDebugRootRedirectTest` exercises it. | **REMOVE** — no verified consumer; target route survives (§1 row 1) but this legacy alias to it does not. |
| 2 | GET\|HEAD | `/test-api-admin-dashboard` | `/_debug/test-api-admin-stats` | **Test-only preservation** | No caller found outside `tests/`. Target route itself is now REMOVE (§1 row 3). | **REMOVE** — no verified consumer, and target no longer exists. |
| 3 | GET\|HEAD | `/test-permissions` | `/_debug/test-permissions` | **Test-only preservation** | No caller found outside `tests/`. Target route is now REMOVE (§1 row 2). | **REMOVE** |
| 4 | GET\|HEAD | `/test-api-admin-stats` | `/_debug/test-api-admin-stats` | **Test-only preservation** | No caller found outside `tests/`. Target route is now REMOVE (§1 row 3). | **REMOVE** |
| 5 | GET\|HEAD | `/test-session-auth` | `/_debug/test-session-auth` | **Test-only preservation** | No caller found outside `tests/`. Target route is now REMOVE (§1 row 5). | **REMOVE** |
| 6 | GET\|HEAD | `/test-login/{email}` | `/_debug/test-login/{email}` | **Test-only preservation** | No caller found outside `tests/`. The real consumer of the *target* (`login.blade.php`'s demo-user links) points directly at `/_debug/test-login/{email}`, bypassing this bare-path redirect entirely — the redirect has never been what the login page actually uses. | **REMOVE** — the target route survives (§1 row 21) but this specific alias to it has no verified consumer; the one real consumer already skips it. |
| 7 | GET\|HEAD | `/debug/{path?}` (wildcard) | `/_debug/{path}` | **No repo evidence either way — explicit developer convenience, stated purpose** | Already `local`-only at registration (unlike #1-6, which are registered in every environment). No test coverage exists today. Its purpose, stated plainly: a **local-only typing convenience** for a developer who types `/debug/x` and expects it forwarded to the canonical `/_debug/x` namespace — not a production compatibility alias, not a legacy-URL concern (there is no evidence anything outside a developer's own browser bar ever typed the bare `/debug/` form). | **KEEP, explicit local developer convenience alias** — retained under the same environment-gated boundary as its (now much smaller) target set, with the stated purpose above recorded as its reason for existing, and with new regression test coverage added (§5, since none exists today). |

**Rationale for REMOVE-by-default on #1-6 (Owner correction #6):** a fixed regression test asserting a redirect exists and 301s is proof the test locks current behavior in place — it is not proof anyone still needs that behavior. Repo evidence cannot prove the negative "no external bookmark ever hits this," but it can and does show the affirmative "no internal caller, script, view, or documented workflow reaches it" for all six, which is the standard Owner correction #1 sets for Class A and which this design applies identically to Class B. Carrying six legacy aliases into the new canonical boundary, when none has verified value and the routes they redirect to are themselves removed for four of the six, would be preserving dead surface for its own sake — exactly what this round's corrections are designed to prevent.

---

## 5. Anti-drift tests (design, not implementation — round 2, executable-invariant wording)

The existing `DebugRouteDocumentationInvariantTest` (GAP-027) stays, unmodified, scoped to what it already does: a documentation-consistency check against `ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md`. It is not a security/architecture guard and GAP-011 does not use it as one.

Gate 2 specifies a new, separate test suite binding the implementation contract from §3 exactly:

1. **Static declaration-site guard (source-level, not `route:list`-based, per §3's rationale):** scan `routes/*.php` (excluding `routes/debug.php`) for any route definition whose path begins with `_debug`; fail if found. Separately, scan `routes/debug.php` and assert every route inside it is under the `_debug` prefix and inside the `DebugGateMiddleware` group — this is a static/source check, runs regardless of `APP_ENV`, and is what makes "declaration site" a build-time-verifiable property rather than an inference from runtime route metadata.
2. **Runtime middleware-presence guard:** for every route whose URI matches `_debug/*` in the currently-booted route table, assert `DebugGateMiddleware` is present in its resolved middleware stack (`Route::gatherMiddleware()` or equivalent). This one is legitimately runtime-based, since middleware resolution is a runtime property — unlike declaration-site ownership, which test #1 checks statically.
3. **Class B redirect-destination guard:** for every `Route::permanentRedirect(...)`/`Route::redirect(...)` registered whose destination matches `_debug/*`, assert the destination is present in the `_debug/*` route set validated by tests #1/#2 — no compatibility redirect may point at an unprotected or nonexistent debug destination. With the round-2 disposition (§4), this test's steady-state assertion set is exactly one row: `/debug/{path?}` → `/_debug/{path}`.
4. **Production-absence test, uncached and cached:** two variants. (a) Boot with `APP_ENV=production` (default, no `.env`, matching Gate 1's evidence methodology) and assert `route:list --path=_debug` returns zero entries against live route resolution. (b) Run `php artisan route:cache` under `APP_ENV=production`, then assert the **cached** route table also resolves zero `_debug/*` entries — this is the test that proves §3 item 4's "verified after `route:cache`, not only uncached" requirement, and is the concrete implementation-time proof that Option C's production-deployment path (§2, resolved via `deploy-production.sh`) actually produces the intended empty production route table once cached, not just in an uncached local check.
5. **Local/testing-presence test:** boot with `APP_ENV=local` and `APP_ENV=testing` and assert the 2 KEEP routes and the 1 KEEP redirect (`/debug/{path?}`) exist and resolve as designed — this is the generalized, post-retention-matrix successor to what `LegacyDebugRootRedirectTest`/`DebugRouteDocumentationInvariantTest` already assert for the current (larger) inventory.

Tests #1-#3 assert structural properties (declaration file, middleware presence, redirect-destination membership) rather than enumerating specific URIs — a future contributor adding an ungated `_debug/*` route anywhere outside `routes/debug.php`, or inside it without the middleware, fails #1 or #2 automatically without this suite needing a hand-maintained allowlist update. This satisfies "adding a future unprotected `_debug/*` route makes CI fail automatically" generically.

---

## 6. Acceptance scenarios (round 2 — updated for the 2-route KEEP set)

### Production
- Direct request to `_debug/test-login/{email}` or `_debug/dashboard-data` → route **absent** from the table entirely (Option C), both uncached and after `route:cache` (§5 item 4). Request returns a standard Laravel 404 (route-not-found), not a middleware-issued 404 — both are 404s to the client, but only `route:list` distinguishes "absent" from "gated," which is exactly what §5 item 4 verifies.
- Direct request to `/debug/{path?}` (any path) → route absent (already `local`-only today, unchanged, now co-located under the same boundary).
- Direct request to any of the 6 removed Class B redirects (`/dashboard-data`, `/test-api-admin-dashboard`, `/test-permissions`, `/test-api-admin-stats`, `/test-session-auth`, `/test-login/{email}`) → standard 404, route no longer registered anywhere, in any environment.
- `route:list --path=_debug` under `APP_ENV=production`, both uncached and after `route:cache` → empty in both cases.

### Local
- `_debug/dashboard-data` returns its mock JSON, matching current behavior exactly.
- `_debug/test-login/{email}` logs the requested user in and redirects to `/app/dashboard`, matching current behavior exactly — this is the route the login page's demo links (rendered only in `local`/`testing`/`development`, per the corrected §1 row 21 rationale) depend on.
- `/debug/{path?}` continues to redirect to `/_debug/{path}` under `local` only, now with new test coverage (§5 item 5) — previously untested, now covered for the first time.
- Requesting any of the 19 REMOVE routes or 6 REMOVE redirects under `local` returns a standard 404 (route no longer exists) — this is the explicit acceptance criterion that the retention-matrix pruning actually took effect, not just that the survivors still work.

### Testing
- `tests/Feature/DebugRouteDocumentationInvariantTest.php` and `tests/Feature/LegacyDebugRootRedirectTest.php` are updated at implementation time to reflect deliberate removal — their expectation arrays shrink to the 2-route/1-redirect surviving set, and (for `LegacyDebugRootRedirectTest`) gain new assertions that the 6 removed redirects now 404. This is a deliberate content change recording the retention-matrix decision, not incidental test breakage.
- A deliberately-introduced, implementation-time-only test fixture registering an ungated `_debug/*` route outside `routes/debug.php` is used to prove tests #1/#2 from §5 actually fail before the real implementation ships.
- A second deliberately-introduced fixture — a route inside `routes/debug.php` with `DebugGateMiddleware` stripped from its definition — proves test #2 catches a missing-middleware mutation independent of declaration-site correctness.

### Rollback criteria
- If, after implementation, either of the 2 KEEP routes (or the 1 KEEP redirect) stops resolving in `local`/`testing`/`development`, or the login page's demo links break, revert `routes/debug.php`'s extraction — a single file move back, per §2.
- `DebugGateMiddleware` is never part of what could need rolling back at any step.
- No route-cache-specific rollback path is anticipated given §2's resolved evidence, but if implementation-time testing under an actual `route:cache` run (§5 item 4b) surfaces an unexpected discrepancy, the immediate mitigation is dropping the environment condition on the `require` (falling back to Option B's always-registered-but-gated behavior) while the discrepancy is root-caused — this does not require re-inlining into `web.php`.

---

## 7. Scope lock (carried from Gate 1, unchanged, reconfirmed round 2)

Explicitly excluded from this Gate 2 design and from GAP-011 implementation entirely:
- `local/dev-login/operator`
- `routes/debug_api.php` (all routes, including `api/login`, `api/v1/upload-document`)
- `routes/api-simple.php` (already remediated separately, see Gate 1 evidence citing commit `7d33620e`)
- Laravel Dusk's self-registered `_dusk/*` routes (package-owned, not GAP-011's code)
- Bare `login`/`logout`/`password/reset` and core auth architecture questions
- All Class C findings (view-only local helpers, internal redirects to real routes, `api/test*` helpers) — listed in Gate 1 as discovered evidence only
- Any production business route unrelated to Class B
- **`resources/views/app/dashboard-content.blade.php`'s `fetch('/_debug/dashboard-data')` call** (§1 row 1) — confirmed adjacent defect, explicitly not modified under GAP-011, recorded only as a separate follow-up candidate per Owner correction #8

No new Work ID is minted for Class C findings, or for the `dashboard-content.blade.php` finding, during this task.

---

## 8. Anticipated implementation files (for scoping only — not authorized to create/edit yet)

- `routes/debug.php` (new) — the 2 KEEP Class A routes + the 1 KEEP Class B redirect, environment-gated `require`.
- `routes/web.php` — removal of the extracted block, removal of the 6 REMOVE Class B redirects, addition of the environment-gated `require routes/debug.php`.
- `app/Http/Middleware/VerifyCsrfToken.php` — removal of the now-fully-orphaned `'test-login-simple'` CSRF exemption entry (§1 row 4).
- `tests/Feature/DebugRouteDocumentationInvariantTest.php` — expectation-array update to the 2-route surviving set (data change, not assertion-shape change).
- `tests/Feature/LegacyDebugRootRedirectTest.php` — expectation-array update; new assertions for the 6 removed redirects now 404ing.
- New test file (name chosen at implementation time, e.g. `tests/Architecture/DebugRouteBoundaryInvariantTest.php`) — §5's static declaration-site guard, runtime middleware-presence guard, redirect-destination guard, and both production-absence variants (uncached + `route:cache`d).
- New test file or extension of an existing one — regression coverage for `/debug/{path?}` (§4 row 7, §5 item 5), which has none today.
- `resources/views/testing-suite.blade.php` and its `_debug/testing-suite` route — deleted, not repaired (§1a).
- `resources/views/final-integration.blade.php`, `performance-optimization.blade.php`, `test-accessibility.blade.php`, `test-mobile-optimization.blade.php`, `test-mobile-simple.blade.php` — orphaned view files deleted alongside their removed routes.

---

## Decision Needed

Owner chooses one:
- **APPROVE** — Option C, the round-2 retention matrix (2 KEEP / 19 REMOVE / 0 UNKNOWN), the `testing-suite` RETIRE decision, the Class B disposition (6 REMOVE / 1 KEEP-as-local-alias), and the corrected canonical invariant, as the design to carry into an implementation plan.
- **CHANGES** — name which retention-matrix rows, which structural option, or which invariant-contract clause should still change.
- **DECLINE** — do not proceed with GAP-011 Class A/B remediation at this time.
- **DEFER** — revisit at a later date; no design decision recorded now.

## What the owner is NOT being asked to decide

Not being asked to approve specific class/file names, the exact PHP test-writing mechanics (regex vs. AST scan for the static guard), or the literal contents of `routes/debug.php` beyond which 2 routes and 1 redirect it contains — only: confirm Option C, confirm the 2/19/0 retention split, confirm the `testing-suite` RETIRE and the 6-of-7 Class B removal. Also not being asked to decide anything about Class C (§7) or about the `dashboard-content.blade.php` adjacent defect (§1 row 1, §7) — both remain explicitly out of scope and would need their own separate request if the Owner wants them pursued.
