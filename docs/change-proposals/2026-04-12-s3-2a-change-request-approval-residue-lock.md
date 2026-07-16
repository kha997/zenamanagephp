# S3.2a Change Request Approval Residue Lock

Date: 2026-04-12
Status: docs-only residue lock

## Why this memo exists

`S3.2a` is still blocked.

The repo was re-read specifically to test whether `change_request_approvals` or related legacy approval-chain code can now serve as canonical unlock evidence for broader canonical change-request approver/stakeholder semantics.

This memo locks the current SSOT answer so later threads do not reopen `S3.2a` from residue.

This memo does not patch runtime.

This memo does not patch tests.

This memo does not reopen `S3.2a`.

## Canonical truth re-checked

The canonical owner surface remains `/api/zena/change-requests` under `App\Http\Controllers\Api\ChangeRequestController` with `App\Models\ChangeRequest`.

Current canonical recipient semantics still prove only:

- `submit -> assigned_to`
- `approve -> requested_by`
- `reject -> requested_by`
- `apply -> no notification proof`

No fresh canonical `/api/zena/change-requests` route/controller/model/test wiring in this review proves broader approver discovery, stakeholder fan-out, or canonical approval-chain ownership.

## Residue inventory re-checked

The following residue still exists in repo state:

- `database/migrations/2025_09_17_162450_create_change_request_approvals_table.php` creates `change_request_approvals`
- `app/Models/ChangeRequestApproval.php` still models that table
- `database/factories/ChangeRequestApprovalFactory.php` still seeds that table
- `src/ChangeRequest/routes/api.php` still mounts legacy `/api/v1/change-requests/pending-approval`
- `src/ChangeRequest/Listeners/ChangeRequestEventListener.php` still contains legacy approver/stakeholder notification and approval-workflow logic

These artifacts prove residue exists.

They do not prove canonical ownership for `/api/zena/change-requests`.

## Locked SSOT decision

Under current repo truth:

- `change_request_approvals` is residue-only
- legacy approval-chain logic in `src/ChangeRequest/*` is non-canonical compatibility/debt residue
- neither artifact family may be used as unlock evidence for `S3.2a`
- `S3.2a` remains blocked until fresh canonical evidence exists on `/api/zena/change-requests`

## Exact unlock bar

Future reconsideration is allowed only if fresh canonical wiring is proved through the canonical owner path itself.

Required evidence must come from some combination of:

- canonical `/api/zena/change-requests*` route truth
- `App\Http\Controllers\Api\ChangeRequestController`
- `App\Models\ChangeRequest`
- canonical feature/invariant tests that directly prove the broader recipient semantics being claimed

Invalid unlock evidence under the current lock:

- `change_request_approvals` table/model/factory existence alone
- legacy `/api/v1/change-requests/pending-approval`
- legacy `src/ChangeRequest/*` approval workflow or notification listener behavior
- inference from compatibility wiring, residue models, or stale approval-chain code paths

## Explicit non-claims

This memo does not claim:

- that `change_request_approvals` should be deleted now
- that the legacy approval residue is harmless
- that `S3.2a` has enough evidence to start runtime work
- that roadmap order changed

## Verdict

`change_request_approvals` and related legacy approval-chain residue are now explicitly locked as non-canonical under current SSOT and cannot be used to unlock `S3.2a` unless fresh canonical `/api/zena/change-requests` wiring is proved later.
