---
work_id: OWN-2026-006
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/OWN-2026-006/02-design.md
---

# OWN-2026-006 Multi-work-item Gate 3 digest isolation design

## Goal

Make implementation-tree evidence stable when release-decision packets for multiple governed work items coexist, without reducing sensitivity to implementation or governance inputs.

## Digest classification

For target work `W`:

- Resolve `W`'s active recognized `03-release*.md` with the existing version-aware resolver and exclude only that active packet.
- Keep `W`'s older recognized release packets in the digest.
- Exclude every recognized `03-release.md` and `03-release-vN.md` directly under another work item's owner-decision directory.
- Keep every other blob in the Git tree, including Gate 1, Gate 2, specs, plans, governance schema/scripts, CI, tests, application code, routes, migrations, and dependency files.

Recognition is path-based and does not introduce a combined Work-ID format. Unrecognized release-like names remain sensitive.

## Verification design

A focused test class creates real temporary Git repositories and commits. It proves unchanged single-work behavior, version-aware own-packet behavior, bidirectional cross-work isolation, exclusion of all recognized versions for another work, sensitivity of every required non-release category, and invalidation of both target digests after a runtime change.

The regression must be observed failing before the production function changes. Existing evidence-binding and stale-decision tests must remain green.

## Non-goals

No schema redesign, freshness-check weakening, PR Work-ID extraction change, combined identity, application change, or modification of GAP-010b/GAP-034 implementation is allowed.
