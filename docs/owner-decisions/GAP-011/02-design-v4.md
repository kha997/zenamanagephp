---
work_id: GAP-011
gate: 2
gate_status: approved
owner_decision:
  value: approved
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
  recorded_at: "2026-08-13T17:35:20+07:00"
  owner_response_reference: "Owner Gate 2 round 4 decision — APPROVE, recorded in-session on 2026-08-13 at fresh wall-clock time 2026-08-13T17:35:20+07:00 (actual time the decision was recorded, not an estimate). Approved and binding, per docs/owner-decisions/GAP-011/02-design-v4.md as written: Option C architecture (existing routes/debug.php, existing single loader in RouteServiceProvider, RouteServiceProvider as sole registration owner, file-level registration permitted at local/testing/development only, absent from production); Class A 2 KEEP (_debug/dashboard-data, _debug/test-login/{email}) / 19 REMOVE / 0 UNKNOWN; Class B 6 REMOVE / 1 KEEP (/debug/{path?}, local-only developer convenience alias); testing-suite and its cluster RETIRE; VerifyCsrfToken.php not modified under GAP-011; Class C strictly out of scope; dashboard-content.blade.php's adjacent _debug/dashboard-data defect flagged only, not fixed under GAP-011; view-file deletion only where implementation-time orphan verification proves the file is actually orphaned; GAP-027 documentation invariant preserved by updating ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md and DebugRouteDocumentationInvariantTest together, not by deleting test expectations alone. Two binding implementation clarifications added at approval (see §13 of this file for the full record): (1) the static anti-drift guard must catch both future Class A route-declaration drift (any _debug/* route declared outside routes/debug.php) AND future Class B alias drift (any Route::redirect()/Route::permanentRedirect() or equivalent declared outside routes/debug.php whose destination targets /_debug/*) — both must fail CI, not only the Class A case; (2) the production route:cache regression test must run in an isolated/controlled process and must always restore/clear the generated route cache afterward, so it never pollutes subsequent test runs. Gate 2 approval authorizes implementation, testing, and technical review. It does NOT authorize mark-ready, merge, release, or production deployment — Gate 3 (Owner release decision) remains mandatory before merge/release and is NOT STARTED. Implementation scope: app/Providers/RouteServiceProvider.php, existing routes/debug.php, routes/web.php, ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md, tests/Feature/DebugRouteDocumentationInvariantTest.php, tests/Feature/LegacyDebugRootRedirectTest.php, new architecture/boundary tests as designed, view files only where orphan verification passes. Not to be modified: DebugGateMiddleware (unless implementation evidence unexpectedly proves a change required — the current contract already matches it as designed), VerifyCsrfToken.php, Class C, dashboard-content.blade.php, the dated docs/audits/2026-03-19-debug-route-inventory.md. Before presenting Gate 3: prove exact route counts/dispositions after implementation, prove the local/testing/development/production environment matrix, prove cached and uncached production absence, prove the wildcard's local-only behavior, prove future Class A and Class B drift mutations are caught, prove the GAP-027 document/runtime invariant remains meaningful, run the full relevant regression suite, compute the Gate 3 implementation-tree digest, and present Gate 3 as awaiting_owner. Keep PR #260 draft throughout implementation/review. Do not self-approve Gate 3. Do not mark ready. Do not merge. Do not deploy."
  reconciliation_required: false
supersedes: "docs/owner-decisions/GAP-011/02-design-v3.md"
superseded_by: null
timestamps:
  created_at: "2026-08-13T17:09:56+07:00"
  updated_at: "2026-08-13T17:35:20+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED (round 4) — design surface for Class A + Class B only

This packet does not implement anything. No route, middleware, test, or file has changed. It designs the canonical protection boundary for GAP-011's Owner-approved scope (Class A: 21 gated `/_debug/*` routes; Class B: 7 compatibility redirects). **Class C is out of scope by binding Gate 1 governance clarification and is not designed, decided, or bundled here.**

This is round 4. Round 3 (`docs/owner-decisions/GAP-011/02-design-v3.md`, frozen) received a third **CHANGES REQUESTED** decision — again not on substance (Option C's corrected topology, the 2/19/0 Class A matrix, the 6/1 Class B disposition, `testing-suite` RETIRE, `VerifyCsrfToken.php` untouched, and conservative view deletion are all explicitly **not reopened**), but on four remaining contract details. §0 maps each to where it is resolved.

## 0. Round-4 corrections applied

| # | Owner correction (round 3 decision) | Where resolved |
|---|---|---|
| 1 | Freeze round 3 correctly with schema-valid Gate 2 vocabulary; create this file as the new active packet | §0a below; `02-design-v3.md` corrected in the same commit as this file |
| 2 | Correct environment semantics — the two KEEP Class A routes are local/testing/development; `/debug/{path?}` is local-only; everything is production-absent; add an explicit development acceptance test | §2 (Option C), §3 (contract), §5 (tests), §6 (acceptance scenarios) — all restated with a three-tier environment matrix |
| 3 | Correct the wildcard redirect invariant — cannot require literal `_debug/{path}` destination membership for an arbitrary `{path}` | §3 item 7(c)→ removed, §5 item 4 — rewritten around static ownership + local-only registration + representative regression examples |
| 4 | Preserve GAP-027 documentation-invariant semantics — update `ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md`'s annotations alongside `DebugRouteDocumentationInvariantTest`, not just shrink the test | §9 (new) — full annotation-update plan with exact line references |
| 5 | Correct lifecycle wording — orphan-view verification happens during implementation, after Gate 2 approval and before the Gate 3 release decision, not "at Gate 3" | §10 — corrected |

### 0a. Supersession-chain repair record (round 4)

`docs/owner-decisions/GAP-011/02-design-v3.md` is now frozen as the round-3 packet. Its body is preserved verbatim as committed at `869fc6f1e6fbff8a1a0b5e7e2b3e321ece1ade3a` (`2026-08-13T17:03:25+07:00`, authoritative Git commit timestamp). Its frontmatter is corrected in the same commit as this file: `gate_status: changes_requested`, `owner_decision.value: changes_requested` (schema-valid Gate 2 vocabulary), `superseded_by: docs/owner-decisions/GAP-011/02-design-v4.md`, `decision_provenance` recording this round's actual CHANGES REQUESTED decision at a fresh wall-clock `recorded_at` (`2026-08-13T17:09:28+07:00` — the real time this decision was recorded, not an estimate).

This file (`02-design-v4.md`) is the new active packet: `gate_status: awaiting_owner`, `owner_decision.value: none`, `supersedes: docs/owner-decisions/GAP-011/02-design-v3.md`, `created_at`/`updated_at` at `2026-08-13T17:09:56+07:00`.

`02-design.md`, `02-design-v2.md`, and `02-design-v3.md` will not be rewritten again — they are frozen historical record. Any further correction happens in `02-design-v5.md` and onward.

## Gate 2 baseline reconfirmation (round 4)

- `origin/main` re-fetched and confirmed still at `1024b68640c2aeddc924620ef7be2885339fecec` — unchanged across all four rounds.
- `docs/GAP-011-debug-route-cleanup-gate1-prep` remains Gate 1's 4 commits plus the round-1/2/3 design commits, plus this round's commit, on top of that baseline — no drift, nothing to reconcile.
- CI at PR #260's head as of round 3 (`869fc6f1...`): both `Owner Governance Lint` and `test-routes-guardrails` were queued/pending at push time; not yet confirmed green at that head before this round's commit supersedes it. This round's own commit SHA and its CI result are reported in §11 once available.

## Authorization boundary

- Gate 1: **APPROVED** (scope: Class A + Class B only)
- Gate 2: **APPROVED** (round 4, `2026-08-13T17:35:20+07:00`)
- Gate 3: **NOT STARTED**
- Implementation, testing, and technical review authorized: **YES**
- Mark-ready / merge / release / production deployment authorized: **NO** — Gate 3 (Owner release decision) remains mandatory before any of these.

---

## 1. Class A retention matrix — unchanged, not reopened (per round-3 decision)

2 KEEP (`_debug/dashboard-data`, `_debug/test-login/{email}`) / 19 REMOVE / 0 UNKNOWN. See `02-design-v3.md` §1 for the full 21-row matrix with evidence — carried forward without re-evaluation. The only change in round 4 touching this section is the explicit three-tier environment matrix below (§1a, new), which states presence/absence per environment for the 2 survivors precisely, correcting an ambiguity in how round 3 described their environment scope.

### 1a. Explicit environment matrix for all GAP-011 survivors (new in round 4, per correction #2)

| Route/redirect | `local` | `testing` | `development` | `production` |
|---|---|---|---|---|
| `_debug/dashboard-data` (Class A, KEEP) | **present** | **present** | **present** | **absent** |
| `_debug/test-login/{email}` (Class A, KEEP) | **present** | **present** | **present** | **absent** |
| `/debug/{path?}` (Class B, KEEP) | **present** | **absent** | **absent** | **absent** |

This is the single authoritative environment contract for this design. Every other section (§2, §3, §5, §6) restates or references this table rather than introducing an independent description — round 3's error was describing the wildcard's environment scope correctly in one place (§4's redirect table) while other sections (§3 item 5, §5 items 4/6, §6) spoke of "the 2 KEEP routes and the 1 KEEP redirect" as a single local/testing/development-scoped group, which was wrong for the redirect.

---

## 2. Structural-boundary option — Option C, corrected topology (unchanged from round 3, environment wording tightened)

Unchanged in substance from round 3 (not reopened): `routes/debug.php` and its `RouteServiceProvider.php:56-59` loader already exist; Option C repurposes them (widens the existing environment condition) rather than adding a second `require` in `routes/web.php`.

**Environment wording, corrected per §1a:** `RouteServiceProvider.php`'s file-level condition widens from `app()->environment('local')` to `app()->environment(['local', 'testing', 'development'])` — this governs whether `routes/debug.php` loads *at all*, and therefore governs the 2 Class A routes declared unconditionally inside it. The `/debug/{path?}` wildcard, declared in the same file, keeps its own **separate, narrower, nested** condition — `app()->environment('local')` — exactly as it exists today at `web.php:583` today, unchanged, simply relocated. This is a two-tier gate, not one: the outer file-level condition (local/testing/development) determines whether the file's contents can exist at all; the inner per-route condition (local only, on the wildcard alone) is stricter than the outer gate and further restricts just that one route. A route inside the file is never broader than the outer gate, but the wildcard is deliberately narrower than it.

All other Option C properties (production safety, discoverability, route-cache implications, migration/rollback simplicity) are unchanged from round 3 — see `02-design-v3.md` §2 for the full comparison, not reopened.

---

## 3. Canonical protection contract (round 4 — environment semantics corrected, wildcard invariant corrected)

1. **Where may GAP-011 debug routes legally be declared?** Only inside `routes/debug.php`. No `_debug/*` route, and no legacy alias into it, may be declared in `routes/web.php`, `routes/api.php`, or any other route file.
2. **Who registers `routes/debug.php`, and under what condition?** `app/Providers/RouteServiceProvider.php` is the sole registration owner, gated by `app()->environment(['local', 'testing', 'development'])`. No other file may independently register it.
3. **In which environments is the file itself loaded?** `local`, `testing`, `development` — this is the outer gate (§2). It determines whether `_debug/dashboard-data` and `_debug/test-login/{email}` can exist at all.
4. **In which environments is `/debug/{path?}` additionally present?** `local` **only** — a second, narrower, nested condition inside the same file, independent of the outer gate. It is **absent in `testing` and `development`** even though the file itself loads there, because its own inner condition excludes those environments. This is the correction to round 3, which did not state this distinction explicitly enough and let later sections describe the wildcard as if it shared the 2 KEEP routes' broader scope.
5. **Must every Class A route carry `DebugGateMiddleware` even though environment-registration already excludes production?** Yes — defense-in-depth, worded precisely (unchanged from round 3): **environment-gated registration** protects against accidental route *presence* when `APP_ENV` is correctly configured; **`DebugGateMiddleware`** protects against *declaration/mounting mistakes* by denying the request at execution time even if a route ends up present. **Neither layer, independently or together, protects a production host whose `APP_ENV` is itself falsely resolved to `local`, `testing`, or `development`** — both read the same environment state. This is a deployment/configuration-contract requirement: production must never resolve to one of the three permitted names.
6. **Required production behavior:** route **absent** — zero `_debug/*` entries and zero surviving Class B entries in `php artisan route:list` under `APP_ENV=production`, verified both uncached and after `route:cache`. Applies uniformly to all three survivors (§1a) — the wildcard's stricter local-only scope doesn't change its production-absence requirement, only its testing/development requirement.
7. **Required non-production behavior**, per §1a exactly:
   - `local`: `_debug/dashboard-data`, `_debug/test-login/{email}`, and `/debug/{path?}` all present and functional.
   - `testing`: `_debug/dashboard-data` and `_debug/test-login/{email}` present and functional; `/debug/{path?}` **absent**.
   - `development`: identical to `testing` — `_debug/dashboard-data` and `_debug/test-login/{email}` present and functional; `/debug/{path?}` **absent**.
8. **What must CI fail on?** Any of: (a) a `_debug/*`-prefixed route whose declaring file (static source scan of `routes/*.php`) is not `routes/debug.php`; (b) `routes/debug.php` registered from anywhere other than `RouteServiceProvider`'s single call site; (c) a route inside `routes/debug.php`'s `_debug` group missing `DebugGateMiddleware` in its resolved runtime middleware stack; (d) `_debug/dashboard-data` or `_debug/test-login/{email}` appearing in `route:list` under `APP_ENV=production`, uncached or cached; (e) `/debug/{path?}` appearing in `route:list` under `APP_ENV` of `production`, `testing`, *or* `development`, uncached or cached — a stricter check than (d) since the wildcard's permitted scope is narrower. **Round-3's item (c)-equivalent — "no compatibility redirect points at a destination outside the validated `_debug/*` route set" — is removed from this list**, per correction #3 below; it does not apply to a wildcard mapper.

---

## 4. Class B disposition — unchanged, not reopened (per round-3 decision)

6 REMOVE / 1 KEEP. `/debug/{path?}` KEEPs as an explicit **local-only** developer convenience alias (its own inner condition, §1a/§2/§3) — this was already correctly stated in round 3's §4 table; rounds 3's error was in *other* sections (§3, §5, §6) not consistently carrying that same local-only scope forward. See `02-design-v3.md` §4 for the full 7-row table — carried forward without re-evaluation.

---

## 5. Anti-drift tests (round 4 — wildcard invariant corrected, development test added explicitly)

1. **Static declaration-site guard** (unchanged): scan `routes/*.php` (excluding `routes/debug.php`) for any route whose path begins with `_debug`; fail if found. Scan `routes/debug.php` and assert every `_debug/*` route inside it is inside the `DebugGateMiddleware` group.
2. **Single-loader guard** (unchanged): statically confirm `routes/debug.php` is registered from exactly one location (`RouteServiceProvider`), nowhere else.
3. **Runtime middleware-presence guard** (unchanged): for every route matching `_debug/*` in the booted route table, assert `DebugGateMiddleware` is present in its resolved middleware stack.
4. **Wildcard-redirect invariant, corrected (Owner correction #3, replaces round 3's destination-membership test):** `/debug/{path?}` is a wildcard mapper — `/debug/{path} → /_debug/{path}` for an arbitrary caller-supplied `{path}`, not a fixed redirect to one literal destination. It cannot be validated by asserting its destination is a member of some enumerated `_debug/*` route set, because most `{path}` values it would forward to are not themselves registered routes (forwarding to a nonexistent `_debug/*` path is expected, correct behavior for a generic path-forwarding alias, not a defect). The corrected invariant, in three parts:
   - **Static ownership:** the wildcard's declaration exists only in `routes/debug.php` (covered by test #1, no separate test needed — it is just another route inside that file for declaration-site purposes).
   - **Registration scope:** a dedicated assertion that this specific route is registered under `local` only and absent under `testing`/`development`/`production` — this is the narrower, second-tier check §3 item 4/7 describes, distinct from and in addition to test #1's file-level ownership check.
   - **Representative regression examples**, not exhaustive path validation: (a) `/debug/dashboard-data` → 301 to `/_debug/dashboard-data` (mirrors one of the 2 surviving Class A routes, confirming the mechanical prefix-forwarding is correct); (b) a representative login-path example, e.g. `/debug/test-login/someone@example.com` → 301 to `/_debug/test-login/someone@example.com` (confirms the alias correctly preserves multi-segment/parameterized paths, not just simple ones). Neither example requires the *destination* to resolve successfully as its own separate assertion — the wildcard's job is provably correct path-prefix substitution, not destination validity, which is a different route's concern.
5. **Production-absence test, uncached and cached** (unchanged): boot with `APP_ENV=production`, assert zero `_debug/*` entries and zero surviving Class B entries in `route:list`, both uncached and after `route:cache`.
6. **`local`-presence test** (scope corrected): boot with `APP_ENV=local`, assert `_debug/dashboard-data`, `_debug/test-login/{email}`, **and** `/debug/{path?}` all exist and resolve as designed — this is the one environment where all three survivors are simultaneously present.
7. **`testing`-presence test, wildcard-absence assertion added (Owner correction #2):** boot with `APP_ENV=testing`, assert `_debug/dashboard-data` and `_debug/test-login/{email}` exist and resolve as designed, **and explicitly assert `/debug/{path?}` is absent** (a route-list check, not merely "not tested") — this environment is where existing tests (`phpunit.xml` sets `APP_ENV=testing`) already run, so this test is the one most likely to catch an accidental widening of the wildcard's scope.
8. **`development`-presence test, added explicitly (Owner correction #2 — do not infer from local/testing):** boot with `APP_ENV=development`, assert `_debug/dashboard-data` and `_debug/test-login/{email}` exist and resolve as designed, and assert `/debug/{path?}` is absent — round 3 only implicitly covered this by grouping "local/testing/development" together in prose without a corresponding explicit test; this is a distinct, standalone test, not inferred or skipped because `local`/`testing` already cover "most" of the environment list.

---

## 6. Acceptance scenarios (round 4 — restated in full against §1a's environment matrix)

### Production
- Direct request to `_debug/dashboard-data`, `_debug/test-login/{email}`, or `/debug/{path?}` (any path) → route **absent** from the table entirely, both uncached and after `route:cache`. Standard Laravel 404 (route-not-found) to the client in all three cases.
- Direct request to any of the 6 removed Class B redirects → standard 404, route no longer registered anywhere, in any environment.
- `route:list --path=_debug` and a check for the wildcard's route name/pattern, both under `APP_ENV=production`, uncached and cached → empty in all cases.

### Local
- `_debug/dashboard-data` returns its mock JSON, matching current behavior exactly.
- `_debug/test-login/{email}` logs the requested user in and redirects to `/app/dashboard`, matching current behavior exactly — the route the login page's demo links (rendered only in `local`/`testing`/`development`) depend on.
- `/debug/{path?}` continues to redirect to `/_debug/{path}` under `local` only — this is the **only** environment where it is expected to respond at all.
- Requesting any of the 19 REMOVE routes or 6 REMOVE redirects returns a standard 404.

### Testing
- `_debug/dashboard-data` and `_debug/test-login/{email}` resolve as designed (they must, since `phpunit.xml` runs under `APP_ENV=testing` and existing tests depend on them).
- **`/debug/{path?}` returns a standard 404 under `testing`** — this is now an explicit acceptance criterion, not an assumption; round 3 did not state this scenario at all.
- `tests/Feature/DebugRouteDocumentationInvariantTest.php` and `tests/Feature/LegacyDebugRootRedirectTest.php` are updated at implementation time per §9's documentation-annotation plan (documentation and test updated together) and the 6-removed-redirect assertions from round 2/3.

### Development (new section, Owner correction #2)
- `_debug/dashboard-data` and `_debug/test-login/{email}` resolve as designed under `APP_ENV=development`, identically to `testing`.
- `/debug/{path?}` returns a standard 404 under `development`, identically to `testing`.
- This scenario exists as its own acceptance criterion precisely because `development` is not otherwise exercised by any existing automated check in this repository (unlike `testing`, which every PHPUnit run already covers) — without an explicit assertion here, a regression specific to `development` would have no test surface to catch it.

### Rollback criteria
- If, after implementation, either of the 2 KEEP Class A routes stops resolving in `local`/`testing`/`development`, or `/debug/{path?}` stops resolving in `local`, or the login page's demo links break, revert `routes/debug.php`'s extraction and the `RouteServiceProvider` condition widening — both are single, independently revertible changes.
- If the `RouteServiceProvider` environment-widening surfaces an unintended side effect specific to `testing` or `development` distinct from the route-cache risk (already resolved with `deploy-production.sh` evidence, §2 of `02-design-v3.md`), narrow the condition back rather than reverting the single-loader topology itself.
- `DebugGateMiddleware` is never part of what could need rolling back at any step.

---

## 7. Scope lock (unchanged, reconfirmed round 4)

Unchanged from round 3, not reopened: `local/dev-login/operator`, `routes/debug_api.php`, `routes/api-simple.php`, Laravel Dusk's `_dusk/*` routes, bare `login`/`logout`/`password/reset` and core auth architecture, all Class C findings, any unrelated production business route, `resources/views/app/dashboard-content.blade.php`'s adjacent `fetch('/_debug/dashboard-data')` defect (flagged only), and `app/Http/Middleware/VerifyCsrfToken.php`'s bare `'test-login-simple'` exemption (confirmed not to URI-match the route being removed, left untouched).

No new Work ID is minted for any of these findings during this task.

---

## 8. `VerifyCsrfToken` non-removal and view-file deletion conservatism — unchanged, not reopened

See `02-design-v3.md` §8-§9 for the full record — carried forward verbatim, not reopened. §10 below corrects only the lifecycle wording that described *when* the view-file orphan check happens, not *whether* it is required.

---

## 9. GAP-027 documentation-invariant preservation plan (new in round 4, per correction #4)

`tests/Feature/DebugRouteDocumentationInvariantTest.php` (closed for GAP-027) asserts that `ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md`'s documented claims about which `_debug/*` routes are still runtime-backed match `route:list` reality. Its `test_current_page_tree_active_debug_claims_have_runtime_route_evidence()` currently expects exactly 5 URIs to exist: `_debug/dashboard-data`, `_debug/test-permissions`, `_debug/test-session-auth`, `_debug/test-login/{email}`, and (as a separate, POST-only assertion) `_debug/test-login-simple`.

GAP-011 removes 3 of these 5 (`_debug/test-permissions`, `_debug/test-login-simple`, `_debug/test-session-auth`), keeping 2 (`_debug/dashboard-data`, `_debug/test-login/{email}`). **Round 3's implicit plan — reduce the test's expectation array to the 2 survivors — is corrected: that would make the test pass by deleting expectations, without the documentation it's supposed to check ever being updated to match.** `ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md` currently states, in three separate locations, that all 5 are "still active"/"still evidenced from this snapshot":

- Line 128 (Mermaid diagram node `DEBUG_ACTIVE`): `"Claims from this snapshot still backed by 2026-03-19 runtime evidence<br/>/_debug/dashboard-data<br/>/_debug/test-permissions<br/>POST /_debug/test-login-simple<br/>/_debug/test-session-auth<br/>/_debug/test-login/{email}"`
- Line 240: `` - ✅ **Still active from this snapshot:** `/_debug/dashboard-data`, `/_debug/test-permissions`, `POST /_debug/test-login-simple`, `/_debug/test-session-auth`, `/_debug/test-login/{email}` ``
- Line 349: `` - ✅ **Still evidenced from this snapshot:** `/_debug/dashboard-data`, `/_debug/test-permissions`, `POST /_debug/test-login-simple`, `/_debug/test-session-auth`, `/_debug/test-login/{email}` ``

Each of these three locations also has an adjacent "archived/unsupported" list (lines 129, 241, 350) documenting claims from the same 2026-03-19 snapshot that were *already* archived/unsupported by runtime evidence at the time this document was written, for unrelated reasons predating GAP-011.

**Corrected implementation plan (design only — not executed under Gate 2):**
1. At all three locations (lines 128, 240, 349), shrink the "still active"/"still evidenced" list to exactly `/_debug/dashboard-data` and `/_debug/test-login/{email}`.
2. At all three locations' adjacent archived lists (lines 129, 241, 350), **add** `/_debug/test-permissions`, `POST /_debug/test-login-simple`, and `/_debug/test-session-auth` — but under a distinct annotation from the pre-existing archived entries, since their provenance is different: the pre-existing archived claims (`_debug/info`, `_debug/projects-test`, etc.) were already unsupported by runtime evidence as of the 2026-03-19 snapshot itself, whereas these three *were* runtime-backed as of 2026-03-19 and were deliberately removed later, by GAP-011. A suggested distinguishing label: **"Removed by GAP-011 (was runtime-backed as of the 2026-03-19 snapshot; deliberately retired, see `docs/owner-decisions/GAP-011/`)"**, kept visually/textually separate from the "archived or unsupported by runtime [as of 2026-03-19]" list already there.
3. **Do not alter the dated 2026-03-19 snapshot content itself** (the historical page-tree structure, the `docs/audits/2026-03-19-debug-route-inventory.md` cross-reference, or any other part of the file describing what the system looked like on that date) — only the "still active"/"archived" annotation lists, which are explicitly forward-looking runtime-status trackers layered on top of the frozen historical snapshot, not the snapshot's own content. This preserves history as it actually was while keeping the annotation accurate about what's true now.
4. Update `DebugRouteDocumentationInvariantTest::test_current_page_tree_active_debug_claims_have_runtime_route_evidence()` (and the adjacent archived-claims test) to match the corrected annotation — its `$expectedActiveClaims` array shrinks to the 2 survivors, and a new assertion (or an extension of the existing archived-claims test) confirms the 3 newly-removed claims are **absent** from `route:list` **and** correctly reflected as GAP-011-removed in the document, keeping the test's actual purpose (proving document ↔ runtime correspondence) intact rather than just deleting expectations until the test passes.

`ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md` is added to the anticipated implementation-file list (§11) as a result of this plan.

---

## 10. Lifecycle wording correction (Owner correction #5)

Round 3's §9 stated view-file orphan verification is "deferred to implementation time (Gate 3)," conflating two different things. Corrected lifecycle, stated precisely:

**Gate 2 approval → implementation (code changes, test changes, technical review) → Gate 3 (Owner release decision) → merge/release.**

Gate 3 is a *decision point* (the Owner deciding whether to authorize release), not a phase of work. The view-file orphan-verification search (§8, unchanged from round 3) occurs **during implementation** — after Gate 2 is approved and before the work is presented to the Owner for the Gate 3 release decision — not "at Gate 3" or "as part of Gate 3." This correction is wording-only; it does not change what the orphan check requires (§8, `02-design-v3.md` §9, unchanged) or when in the overall timeline it practically happens — only removes a phrase that could be read as making the orphan check itself a Gate 3 activity.

---

## 11. Anticipated implementation files (round 4, updated)

Unchanged from `02-design-v3.md` §10 except one addition:

- `app/Providers/RouteServiceProvider.php` — widen the existing condition to `['local', 'testing', 'development']`.
- `routes/debug.php` — existing file, repurposed: the 2 KEEP Class A routes (unconditional within the file, gated by the outer `RouteServiceProvider` condition) and `/debug/{path?}` (its own separate, nested `local`-only condition, §1a/§2/§3).
- `routes/web.php` — removes the old inline Class A block and the 6 REMOVE Class B redirects; does not gain a new loader.
- `tests/Feature/DebugRouteDocumentationInvariantTest.php` — expectation-array update **and** archived-claims assertion update, per §9's document/test-together plan (not a standalone data trim).
- `tests/Feature/LegacyDebugRootRedirectTest.php` — expectation-array update; new assertions for the 6 removed redirects now 404ing.
- **`ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md` (new in round 4, §9)** — annotation updates at lines 128/129, 240/241, 349/350: shrink the "still active" lists to the 2 survivors, add the 3 removed claims to the archived lists under a distinct "Removed by GAP-011" provenance label. The dated 2026-03-19 historical snapshot content itself is not altered.
- New test file (name chosen at implementation time) — §5's static declaration-site guard, single-loader guard, runtime middleware-presence guard, the corrected wildcard-invariant tests (static ownership + registration-scope + representative regression examples), and both production-absence variants (uncached + `route:cache`d).
- New test coverage for `/debug/{path?}`'s `local`-presence / `testing`-absence / `development`-absence / `production`-absence, per §5 items 6-8.
- `resources/views/testing-suite.blade.php` and 5 sibling view files — candidates for deletion, subject to the (unchanged, `02-design-v3.md` §9) orphan-verification search **during implementation, before the Gate 3 release decision** (§10 — corrected phrasing only).

---

## 12. Exact-head CI status

CI at PR #260's most recent pushed head (`869fc6f1e6fbff8a1a0b5e7e2b3e321ece1ade3a`, round 3's commit) was queued/pending (`Owner Governance Lint`, `test-routes-guardrails`) at the time it was pushed; not yet confirmed resolved before this round's commit supersedes that head. This round's own commit SHA and its CI result will be current on PR #260 once pushed — `gh pr checks 260` against the latest head is the authoritative source at review time, not a value restated in this file (which would go stale the moment CI re-runs).

---

## 13. Binding implementation clarifications added at approval (Owner, round 4 approval)

Two clarifications the Owner added at the moment of approval — binding on implementation, not reopening any design content above:

### 13a. The static anti-drift guard must catch Class B alias drift, not only Class A route-declaration drift

§5's static declaration-site guard (items 1-2) was designed around Class A: catching a future `_debug/*` route declared outside `routes/debug.php`. The Owner's binding clarification: the same guard must **also** catch the Class B-shaped mistake — a future `Route::redirect(...)`/`Route::permanentRedirect(...)` (or equivalent aliasing mechanism) declared **anywhere other than `routes/debug.php`** whose destination targets `/_debug/*`. Concretely, the static scan of `routes/*.php` (excluding `routes/debug.php`) must fail CI on either of:
- any route declaration whose own path begins with `_debug` (the existing Class A check), **or**
- any redirect/alias declaration whose **destination** matches `_debug/*` (the added Class B check) — regardless of what the alias's own source path looks like.

This closes the exact gap the round-1/2/3 designs implicitly left open: a contributor could not add a new bare `_debug/*` route outside `routes/debug.php` (caught), but could still add a new compatibility redirect somewhere else in `routes/web.php` pointing back into `/_debug/*` without any test catching it, reintroducing the same class of drift GAP-011 exists to close for Class B specifically. The corrected static guard treats both as the same category of violation.

### 13b. Production route:cache regression tests must be isolated and self-cleaning

§5 item 5 (production-absence test, cached variant) and the route-cache confirmation from `02-design-v3.md` §2 require actually running `php artisan route:cache` under `APP_ENV=production` as part of the test suite. Binding clarification: this test must run in an **isolated/controlled process** (not mutating the shared route cache state of the process running the rest of the suite) and must **always restore or clear the generated route cache afterward** — in a `finally`/teardown, not only on the success path — so that a route cache artifact generated for this one assertion never leaks into or pollutes any test that runs after it in the same suite or CI job.

---

## Decision recorded

**APPROVED** by the Owner, round 4, `2026-08-13T17:35:20+07:00` (verbatim decision text in `decision_provenance.owner_response_reference` above). Gate 2 is closed. Implementation, testing, and technical review are authorized within the scope stated in §11 (as amended by §13) and the Owner's explicit "not to be modified" list (`decision_provenance.owner_response_reference`: `DebugGateMiddleware` unless implementation evidence unexpectedly requires otherwise, `VerifyCsrfToken.php`, Class C, `dashboard-content.blade.php`, the dated `docs/audits/2026-03-19-debug-route-inventory.md`). Mark-ready, merge, release, and production deployment remain **not authorized** — Gate 3 (Owner release decision) is mandatory before any of those and is **NOT STARTED**. No `02-design-v5.md` was created for this approval, per the Owner's explicit instruction — this file (`02-design-v4.md`) is the final, approved Gate 2 packet.
