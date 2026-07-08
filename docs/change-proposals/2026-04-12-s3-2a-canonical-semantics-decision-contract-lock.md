# S3.2a Canonical Semantics Decision Contract Lock

Date: 2026-04-12
Status: docs-only decision-contract lock

## Why this memo exists

`S3.2a` is still the only meaningful future candidate with a real story key/title and a real canonical owner anchor, but it is still blocked.

The blocker is no longer "maybe code archaeology will answer it." The blocker is that the canonical owner path still does not define the broader recipient semantics that `S3.2a` needs.

This memo records the smallest honest SSOT contract for that gap.

This memo does not patch runtime.

This memo does not patch tests.

This memo does not reopen implementation.

This memo does not invent recipient semantics.

## Canonical owner boundary already proved

The canonical owner remains `/api/zena/change-requests` under `App\Http\Controllers\Api\ChangeRequestController` with `App\Models\ChangeRequest`.

Evidence:

- `docs/architecture/module-ownership-ssot.md:51-51` records Change Requests as canonically owned by `/api/zena/change-requests`, `App\Http\Controllers\Api\ChangeRequestController`, and `App\Models\ChangeRequest`, while explicitly freezing legacy approval-chain residue as non-canonical.
- `docs/roadmap/backlog.yaml:263-273` keeps `S3.2a` as the follow-up story for broader approver/stakeholder semantics on the canonical change-request owner path.
- `docs/change-proposals/2026-04-12-s3-2a-change-request-approval-residue-lock.md:20-31` re-locks the current canonical truth to `/api/zena/change-requests` only.

## Proved current semantics

Current canonical recipient semantics are limited to the exact owner-backed behavior already proved on the canonical controller and tests:

- `submit -> assigned_to`
- `approve -> requested_by`
- `reject -> requested_by`
- `apply -> no notification proof`

Evidence:

- `app/Models/ChangeRequest.php:85-99` exposes `requested_by`, `assigned_to`, and `approved_by` as model fields, while `app/Models/ChangeRequest.php:168-189` defines canonical requester and assignee relations.
- `app/Http/Controllers/Api/ChangeRequestController.php:567-613` proves `submit()` writes `submitted` and sends one in-app notification only when `assigned_to` is a non-empty string.
- `app/Http/Controllers/Api/ChangeRequestController.php:638-714` proves `approve()` sends one in-app notification only to `requested_by`.
- `app/Http/Controllers/Api/ChangeRequestController.php:741-799` proves `reject()` sends one in-app notification only to `requested_by`.
- `tests/Feature/Api/ChangeRequestApiTest.php:150-175` and `tests/Feature/ChangeRequestApiTest.php:165-193` prove the submit recipient is the explicit assignee fixture.
- `tests/Feature/Api/ChangeRequestApiTest.php:187-217` and `tests/Feature/ChangeRequestApiTest.php:203-239` prove the approve recipient is the requester.
- `tests/Feature/Api/ChangeRequestApiTest.php:229-259` and `tests/Feature/ChangeRequestApiTest.php:249-287` prove the reject recipient is the requester.
- `tests/Feature/ChangeRequestApiTest.php:394-420` proves `apply()` still has no notification proof in the current canonical round.
- `docs/roadmap/backlog.yaml:249-252` and `docs/change-proposals/2026-04-12-s3-2a-change-request-approval-residue-lock.md:24-31` already describe the same bounded truth in SSOT.

## Exact owner-backed recipient sources currently allowed

Under current canonical proof, only the following recipient sources are allowed as canonical inputs for notification claims on `/api/zena/change-requests`:

| Source | Allowed scope today | Evidence |
| --- | --- | --- |
| `change_requests.assigned_to` | allowed only for the already-proved `submit` direct-recipient slice | `app/Http/Controllers/Api/ChangeRequestController.php:605-613`; `tests/Feature/ChangeRequestApiTest.php:165-193` |
| `change_requests.requested_by` | allowed only for the already-proved `approve` and `reject` direct-recipient slice | `app/Http/Controllers/Api/ChangeRequestController.php:705-713`; `app/Http/Controllers/Api/ChangeRequestController.php:791-799`; `tests/Feature/ChangeRequestApiTest.php:203-239`; `tests/Feature/ChangeRequestApiTest.php:249-287` |

No other recipient source is currently owner-backed enough to support canonical notification claims.

## Prohibited evidence sources

The following sources are explicitly prohibited as canonical proof for `S3.2a` semantics:

- `change_request_approvals` table/model/factory residue
- legacy `/api/v1/change-requests/pending-approval`
- legacy `src/ChangeRequest/*` approval-chain or stakeholder notification logic
- compatibility-only `/api/v1/*` behavior
- inference from fields that exist on the model but are not used by the canonical controller/tests as recipient sources

Evidence:

- `docs/change-proposals/2026-04-12-s3-2a-change-request-approval-residue-lock.md:33-72` explicitly locks `change_request_approvals`, `/pending-approval`, and `src/ChangeRequest/*` approval workflow/listener logic as residue-only and invalid unlock evidence.
- `docs/architecture/module-ownership-ssot.md:51-51` states the approval-chain residue is non-canonical and cannot unlock broader `S3.2a` semantics without fresh canonical proof.

## Exact unresolved canonical decisions

The open questions below are not implementation details. They are still canonical decision gaps.

### 1. Approver discovery semantics

Current proof shows only one explicit assignee fixture on submit. It does not define whether canonical approver discovery means:

- exactly `assigned_to`
- one or more recipients derived from another owner-backed field
- a project-role or policy-driven lookup
- no notification when no canonical approver source is present

Current status: `UNKNOWN`

Required future evidence or decision:

- either fresh canonical controller/test proof on `/api/zena/change-requests`, or
- an explicit product decision memo that names the allowed owner-backed source and the no-recipient behavior

### 2. Stakeholder recipient semantics

Current proof does not define who counts as a stakeholder on canonical change requests.

Current status: `UNKNOWN`

Required future evidence or decision:

- an explicit product decision memo that names the stakeholder classes and their canonical owner-backed source, and
- matching canonical runtime proof if the story later claims end-to-end fan-out

### 3. Meaning of `assigned_to`

The canonical path proves `assigned_to` can receive the submit notification.

It does not prove whether `assigned_to` means:

- sole approver
- first approver in a broader chain
- workflow assignee who is not necessarily the approver
- fallback recipient when broader approver discovery is absent

Current status: `UNKNOWN` beyond the already-proved submit recipient

Required future evidence or decision:

- explicit semantic definition tied to `/api/zena/change-requests`, not inferred from residue or field naming

### 4. Apply notification semantics

`apply()` is part of the canonical workflow state machine, but current canonical proof still records no notification behavior there.

Current status: deferred and unproved

Required future evidence or decision:

- an explicit decision whether `apply` belongs inside `S3.2a`, a later story, or remains intentionally silent, and
- canonical proof if any notification claim is later made

### 5. Exact owner-backed recipient sources allowed on canonical `/api/zena/change-requests`

Current proof only allows `assigned_to` and `requested_by` for the already-proved narrow cases.

It does not define whether any of the following may become valid canonical recipient sources later:

- `approved_by`
- `rejected_by`
- `decided_by`
- project-owner or project-role lookups
- task/component/document-linked parties
- watchers, stakeholders, or notification-rule outputs

Current status: `UNKNOWN`

Required future evidence or decision:

- an explicit allow-list in a future decision memo or in fresh canonical controller/test proof

## Locked decision contract

Until a later explicit decision or fresh canonical proof exists, future threads must treat the following statements as locked:

1. `S3.2a` is blocked by undefined canonical semantics, not by missing residue mining.
2. Current canonical recipient proof is limited to `submit -> assigned_to`, `approve/reject -> requested_by`, and `apply -> no notification proof`.
3. No broader approver discovery, stakeholder fan-out, or `assigned_to` meaning may be claimed from current repo truth.
4. No new recipient source may be treated as canonical on `/api/zena/change-requests` unless it is explicitly decision-backed or freshly proved on the canonical owner path.
5. `change_request_approvals` and legacy `src/ChangeRequest/*` approval-chain logic remain invalid unlock evidence.

## Explicit non-claims

This memo does not claim:

- that the unresolved questions are now answered
- that `S3.2a` is runtime-ready
- that backlog order changed
- that any runtime recipient rule should now be implemented

## Verdict

`S3.2a` now has a locked canonical decision contract: the unresolved recipient semantics are explicit, the prohibited evidence sources are explicit, and the current canonical proof ceiling is explicit.
