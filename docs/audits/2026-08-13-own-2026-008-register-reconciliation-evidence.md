# OWN-2026-008 — Register Reconciliation Evidence

*Supports Gate 2 design for OWN-2026-008. Documentation-only reconciliation of 4 stale rows in `OPERATIONAL_GAP_REGISTER.md` against verified current `origin/main` state. No application code, migration, route, test behavior, runtime behavior, or production data was inspected in a way that changes any of those — only source files were read.*

## GAP-027 — RESOLVED (verified 2026-08-13)

**Register said:** `UNVERIFIED` — "Không có test bất biến so khớp route `/_debug/*` đang mount với inventory snapshot."

**Live evidence on `origin/main`:** `tests/Feature/DebugRouteDocumentationInvariantTest.php` exists and implements exactly the invariant the gap asked for:
- `test_current_page_tree_active_debug_claims_have_runtime_route_evidence()` — asserts every claimed-active `_debug/*` URI (`_debug/dashboard-data`, `_debug/test-permissions`, `_debug/test-session-auth`, `_debug/test-login/{email}`, `_debug/test-login-simple`) exists in real `route:list` output.
- `test_current_page_tree_archived_debug_claims_do_not_have_runtime_route_evidence()` — asserts every claimed-archived URI (`_debug/info`, `_debug/projects-test`, `_debug/users-debug`, `_debug/tasks-debug`, `_debug/frontend-test`, `_debug/login-test`, `_debug/simple-test`, `_debug/navigation-test`, `_debug/api-docs`, `_debug/api-docs.json`, `_debug/test-api-admin-dashboard`) does NOT exist in real `route:list` output.

**Conclusion:** the drift-matching invariant test the gap requested already exists and runs. Terminal.

## GAP-028 — RESOLVED (verified 2026-08-13)

**Register said:** `UNVERIFIED` — "README.md và SYSTEM_DOCUMENTATION.md mô tả kiến trúc đã lỗi thời (Vue 3, microservices, universal-frame là UI chính)."

**Live evidence on `origin/main`:**
- `git show origin/main:README.md | grep -niE "\bvue\b|microservice"` → zero matches.
- `SYSTEM_DOCUMENTATION.md` no longer exists at repo root; only an archived copy remains at `docs/archive/reports/SYSTEM_DOCUMENTATION.md`.
- Last README architecture-relevant commit: `f9eaea0f "Docs: codify routing architecture rules (single source of truth)"`.

**Conclusion:** the outdated-architecture description the gap flagged is gone from the live-facing document; the stale copy is explicitly archived, not presented as current. Terminal.

## GAP-029 — RESOLVED (verified 2026-08-13)

**Register said:** `OPEN (verified)` — "Operator web UI chưa có đường mở lại để sửa/nộp lại cho Submittal bị reject."

**Live evidence on `origin/main`:**
- `resources/views/submittals/show.blade.php` has full `rejected`/`revising` branches and a "Sửa lại" button posting to `operator.submittals.start-revision`.
- `git log --oneline --grep GAP-029` → `d6ca498b feat(submittal): GAP-029 — operator web UI for resubmit flow (#230)`.

**Conclusion:** the UI gap described is closed and merged. Terminal.

## GAP-033 — RESOLVED (verified 2026-08-13)

**Register said:** `OPEN (re-verified 2026-08-12)` — "Document has no mechanism to designate a specific approver/action-owner per document."

**Live evidence on `origin/main`:**
- `docs/owner-decisions/GAP-033/03-release.md` records `gate: 3`, `gate_status: approved`, `owner_decision.value: approved`, with the Owner's explicit "PHÁT HÀNH (APPROVED)" quote.
- Merge commit `30a609a9390524f3294a2eb579141f7d013064fb` is the current `origin/main` tip and contains the full implementation: `DocumentApproverAssignment` model, service, policy checks, migrations, and tests.

**Conclusion:** GAP-033 completed its full three-gate lifecycle and is merged. Terminal.

## Scope confirmation

No other `OPERATIONAL_GAP_REGISTER.md` row is affected by this evidence or by the reconciliation it supports. Rows re-checked and found to remain genuinely non-terminal in the same review pass (GAP-011, GAP-012, GAP-013, GAP-014b, GAP-014c, GAP-015, GAP-016, GAP-017, GAP-018, GAP-019, GAP-020, GAP-021, GAP-026, GAP-030) are explicitly out of scope for OWN-2026-008 and are not touched by this work item.
