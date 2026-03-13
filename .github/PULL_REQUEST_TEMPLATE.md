## SSOT Story Reference
- Backlog SSOT: `docs/roadmap/backlog.yaml`
- Story ID: **Sx.y** (e.g. S2.2)
- Epic: **EPIC-x**
- Scope: **ONE Story per PR** (unless explicitly approved)

## Summary
- What changed:
- Why:
- User-facing impact (if any):

## Invariants Checklist (MUST)
- [ ] No domain/business logic change just to pass tests
- [ ] Tenant isolation + RBAC + middleware gate preserved
- [ ] Routes mounted via `routes/api.php` (no auto-load via provider)
- [ ] No tests disabled/removed to pass CI
- [ ] No secrets required for PR jobs (gate/skip gracefully if needed)
- [ ] Deploy does NOT run on PR (only push main / workflow_dispatch)

## Acceptance Criteria (from backlog.yaml)
Paste the Story acceptance criteria here and mark each as done:
- [ ] AC1:
- [ ] AC2:
- [ ] AC3:

## Evidence / Verification (Copy-paste commands)
> Provide the exact commands used and their outcomes.
- [ ] `composer ssot:lint`
- [ ] Targeted tests (list):
  - [ ] `php artisan test ...`
- [ ] Any relevant scripts:
  - [ ] `php check_duplicate_imports.php`
  - [ ] `bash scripts/...` (if applicable)

## CI Checks (Links)
- PR checks summary:
- Failing logs (if any):
- Key runs referenced:

## SSOT Backlog Update (REQUIRED)
- [ ] Updated `docs/roadmap/backlog.yaml`:
  - [ ] Story `status` set to `done`
  - [ ] Added `done_evidence` (PR link + CI checks + verify commands summary)

## Change Proposal (ONLY if needed)
> If anything conflicts with governance/invariants, explicitly document it here.
- Proposal:
- Rationale:
- Impact:
- Rollback plan:

## Notes / Follow-ups
- Tech debt / refactors deferred:
- Next Story suggestion:
