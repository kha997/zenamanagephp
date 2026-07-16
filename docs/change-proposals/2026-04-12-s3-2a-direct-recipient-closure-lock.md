# S3.2a Direct Recipient Closure Lock

Date: 2026-04-12
Status: implementation-bound closure lock

## Why this memo exists

The earlier `S3.2a` stop-line was semantic, not technical.

This round carries an authoritative business decision that resolves those previously open canonical questions to the smallest bounded slice that can honestly close now.

This memo records that closure boundary.

## Authoritative decision applied in this round

For this bounded slice on canonical `/api/zena/change-requests`:

- `assigned_to` means the current review/decision owner
- `submit` notifies only `assigned_to`
- `approve` notifies only `requested_by`
- `reject` notifies only `requested_by`
- `apply` notifies nobody
- there is no stakeholder fan-out
- the only allowed recipient sources are `assigned_to` and `requested_by`

The following remain prohibited as proof or semantics sources:

- `change_request_approvals`
- legacy `src/ChangeRequest/*`
- compatibility `/api/v1/*`
- watcher, stakeholder, or approval-chain semantics outside the canonical path

## Runtime result

Current canonical controller behavior already matched the decision boundary:

- `submit()` writes one direct in-app notification only to `assigned_to`
- `approve()` writes one direct in-app notification only to `requested_by`
- `reject()` writes one direct in-app notification only to `requested_by`
- `apply()` does not write a change-request notification

This implementation round therefore hardens proof rather than widening runtime behavior.

## Evidence

- route truth: `routes/api_zena.php`; `php artisan route:list --except-vendor -v --path=api/zena/change-requests`
- canonical controller: `app/Http/Controllers/Api/ChangeRequestController.php`
- canonical model surface: `app/Models/ChangeRequest.php`; `app/Models/Notification.php`
- hardened feature proof: `tests/Feature/ChangeRequestApiTest.php`; `tests/Feature/Api/ChangeRequestApiTest.php`
- prohibited evidence lock: `docs/change-proposals/2026-04-12-s3-2a-change-request-approval-residue-lock.md`
- prior decision contract: `docs/change-proposals/2026-04-12-s3-2a-canonical-semantics-decision-contract-lock.md`

## Exact claims now proved

- `submit -> assigned_to only`
- `approve -> requested_by only`
- `reject -> requested_by only`
- `apply -> no notification`
- no broader recipient discovery inside this slice
- no stakeholder fan-out inside this slice
- no residue or compatibility surface used as proof

## Explicit non-claims

This memo does not claim:

- broader approver discovery beyond `assigned_to`
- stakeholder or watcher semantics
- notification-rule ownership
- email/job/mail dispatch
- any `apply` fan-out

## Verdict

`S3.2a` is now closure-ready and closed as a bounded canonical direct-recipient semantics slice only.
