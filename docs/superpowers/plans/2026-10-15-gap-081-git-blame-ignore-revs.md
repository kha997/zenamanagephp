---
work_id: GAP-081
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-081/02-design.md
---

# GAP-081 — git blame ignore file: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-081/02-design.md`).

1. Add `.git-blame-ignore-revs` with the approved header and hash `40d19ab10f51c77587127f2ab5c477724f4ab7cf`.
2. Verify the hash resolves to a commit and `git blame --ignore-revs-file` skips it on a reformatted file.
3. Lints, exact-head CI, Gate 3 packet.
