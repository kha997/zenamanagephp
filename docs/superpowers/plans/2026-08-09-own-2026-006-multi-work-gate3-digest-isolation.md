---
work_id: OWN-2026-006
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/OWN-2026-006/02-design.md
---

# OWN-2026-006 implementation plan

1. Create an isolated branch from exact main SHA `5a9b205123cb305381aab2dbcbffea6ad6abeb0b` and preserve unrelated work.
2. Add a narrow temporary-Git-repository regression covering the approved 11-case matrix and run it before production modification. Record the expected cross-work failures.
3. In `owner_governance_compute_implementation_tree_digest()`, retain own active-packet resolution and skip recognized packets belonging to other work directories while assembling digest lines.
4. Rerun the focused regression and existing OwnerGovernance verification, then run structural lint, gate-ordering lint, and `git diff --check`.
5. Commit normally, push the isolated branch, open a Draft PR to `main`, inspect exact-head CI, and stop without creating a Gate 3 packet, marking Ready, merging, or releasing.

Rollback is a normal revert of this governance-tooling PR; no application data or runtime behavior is changed.
