---
work_id: GAP-047
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md
  plan: null
  branch: docs/GAP-047-owner-governance-lint-evidence
  pr: "https://github.com/kha997/zenamanagephp/pull/289"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-26T13:37:00+07:00"
  owner_response_reference: "Owner directive, 2026-08-26: 'OWNER DECISION: APPROVE GATE 1' for GAP-047 on the reviewed exact head 4991c68c20bcf3255def47ed5eba38333e0a3bc3 of PR #289 (base main f913f040063fc628ad8f425b5f01ff5da960d742, state OPEN/Draft/mergeable, diff limited to docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md and docs/owner-decisions/GAP-047/01-request.md, LIVE Owner Governance Lint SUCCESS, LIVE Routes Guardrails SUCCESS). Owner confirmed both governance defects are sufficiently proven: (A) the OWN-2026-005 awaiting-owner design-only classification excludes docs/audits/**, so an otherwise legitimate docs-only Gate 2 presentation that also carries conventional Gate 1 audit evidence can incorrectly fail with gate-2-not-approved; (B) bulk governed-document discovery can silently skip a new spec/plan lacking required governance frontmatter because filename fallback recognition is case-sensitive and the bulk-scan unrecognized-file branch continues without emitting missing-governance-frontmatter. This approval authorizes GATE 2 DESIGN ONLY; it does not authorize implementation. Owner directed: record this Gate-1 approval in 01-request.md only; do not rewrite or reinterpret the Gate-1 evidence document; do not modify owner_governance_lint.php, workflows, governance schema, tests, application code, migrations, GAP-046 artifacts, or PR #288; after the approval-record head is clean and exact-head CI is green, make PR #289 ready and merge it as a Gate-1-only docs record using the repository's normal squash-merge method (this is a documentation merge, not a production deployment); Gate 2 must be filed on a fresh branch cut from the post-Gate-1 canonical main, not on the PR #289 branch, and must remain gate_status: awaiting_owner / owner_decision.value: none pending a separate Owner Gate-2 decision. Owner's stated architectural preference for Gate 2 is option A1 (narrowly add docs/audits/ to the existing design-only prefix set) for Defect A and option B3 (explicit path-level grandfathering of historical non-frontmatter governed documents, then fail-closed for every non-grandfathered spec/plan lacking frontmatter regardless of filename casing or Work-ID recognizability) for Defect B, unless Gate 2's evidence disproves either preference."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-26T12:43:00+07:00"
  updated_at: "2026-08-26T13:37:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-047 Gate 1 at exact head `4991c68c20bcf3255def47ed5eba38333e0a3bc3` of PR #289 (base `main` `f913f040063fc628ad8f425b5f01ff5da960d742`, state OPEN/Draft/mergeable at review time, diff limited to `docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md` and this file, LIVE `Owner Governance Lint` SUCCESS, LIVE `test-routes-guardrails` SUCCESS). Owner independently reverified this exact state before issuing the decision.

The Gate-1 evidence is accepted as sufficient and complete for both defects:

1. **Defect A (false-red)** — the OWN-2026-005 awaiting-owner design-only classification excludes `docs/audits/**`, so an otherwise legitimate docs-only Gate 2 presentation that also carries conventional Gate 1 audit evidence can incorrectly fail with `gate-2-not-approved`.
2. **Defect B (false-green)** — bulk governed-document discovery can silently skip a new spec/plan lacking required governance frontmatter because filename fallback recognition is case-sensitive and the bulk-scan unrecognized-file branch `continue`s without emitting `missing-governance-frontmatter`.

**Scope of this approval:** confirms the Gate-1 problem statement and evidence are sound and complete for both defects. Authorizes **Gate 2 design only** — comparing alternatives for both defects and producing a governed engineering design spec. Does **not** authorize implementation, does **not** select the exact correction, and does **not** change any Owner decision semantics or packet enum meanings.

**Owner's stated architectural preference for Gate 2**, to be confirmed or disproven by evidence rather than assumed:

- Defect A: option A1 — narrowly add `docs/audits/` to the existing design-only documentation prefix set (not a broader `docs/**` exemption, and not a wholesale replacement of path-prefix classification).
- Defect B: option B3 — explicit, finite, reviewable path-level grandfathering of the existing historical non-frontmatter governed-document paths, then fail-closed for every non-grandfathered spec/plan lacking required frontmatter, regardless of filename casing or whether the filename contains a recognizable Work-ID token.

**Explicit process directives bound to this approval:**

- Record this Gate-1 approval in `01-request.md` only; the companion Gate-1 evidence document (`docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md`) is **not** rewritten or reinterpreted and remains byte-unchanged by this approval.
- No change is authorized to `scripts/ssot/owner_governance_lint.php`, workflows, governance schema, tests, application code, migrations, GAP-046 artifacts, or PR #288.
- After this approval-record head is clean and exact-head CI is verified green, PR #289 is made ready and merged as a **Gate-1-only docs record** using the repository's normal squash-merge method. This is a documentation merge, not a production deployment.
- Gate 2 must be filed on a **fresh branch cut from the post-Gate-1 canonical `main`**, not on the PR #289 branch, and must remain `gate_status: awaiting_owner` / `owner_decision.value: none` pending a separate Owner Gate-2 decision. Gate 2 is not self-approved by the agent executing this workflow.

## Owner Summary

GAP-047 records two independently verified defects in `scripts/ssot/owner_governance_lint.php` that were exposed while splitting GAP-046 Gate 1 and Gate 2. One defect produces a false-red for a legitimate docs-only Gate 2 presentation that also carries conventional Gate 1 evidence under `docs/audits/**`; the other can silently skip a new governed spec/plan lacking required frontmatter when its filename contains a lowercase Work ID.

This Gate 1 is evidence-only. It does not authorize any change to the lint script, workflow, governance schema, tests, application code, or runtime behavior.

## Problem

### Defect A — false-red in the design-only exemption

The OWN-2026-005 awaiting-owner design-only exemption recognizes only these documentation prefixes:

- `docs/owner-decisions/`
- `docs/superpowers/specs/`
- `docs/superpowers/plans/`

It excludes `docs/audits/**`, even though that directory is the repository's established location for Gate 1 evidence. As a result, a docs-only PR containing Gate 1 audit evidence plus a correct Gate 2 packet/spec cannot satisfy `owner_governance_changed_files_are_design_only()` and receives `gate-2-not-approved` despite containing no implementation change.

### Defect B — silent false-green in bulk governed-document discovery

The written governed-document frontmatter contract requires every new post-effective-date spec/plan to carry governance frontmatter and says omission must fail with `missing-governance-frontmatter`.

The bulk-scan fallback recognizes filename Work IDs only with a case-sensitive uppercase regex. Lowercase filenames such as the current GAP-043 and GAP-044 design-spec names do not match; when a bulk scan cannot extract a Work ID, the implementation executes `continue`, silently skipping the document instead of enforcing the required-frontmatter rule.

## Evidence

The complete source and historical evidence is recorded in:

`docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md`

Key verified facts include:

- canonical baseline `main`: `f913f040063fc628ad8f425b5f01ff5da960d742`;
- GAP-046 PR #287 head `7fe8b8d73b8a63b70a1e142d08cf98a97cda2878` failed Owner Governance Lint only at gate-ordering enforcement with `gate-2-not-approved` while its structural lint passed;
- after Gate 1 evidence was landed separately and Gate 2 reopened without the `docs/audits/**` path in PR #288, the unchanged design passed the required CI checks;
- current source directly shows the three-prefix design-only allowlist and the case-sensitive fallback regex + bulk-scan `continue` behavior;
- `docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md` explicitly requires new governed specs/plans without frontmatter to fail;
- current GAP-043 and GAP-044 design specs have lowercase Work-ID filenames and no YAML governance frontmatter, illustrating that the false-negative path has existed in real repository history.

## Impact

The governance layer can currently fail in both directions:

1. **false-red:** block a legitimate docs-only Gate 2 presentation;
2. **false-green:** fail to evaluate a governed document that the written policy says must be rejected.

This weakens confidence in Owner Control Layer CI and creates pressure to restructure documentation around implementation quirks rather than governance intent.

## Proposed Work-ID Boundary

GAP-047 should own only the governance-lint correction necessary to restore the written gate-ordering/frontmatter contract, plus regression tests and narrowly necessary governance documentation/workflow adjustments proven at Gate 2.

Gate 2 must determine the exact correction. Gate 1 does not select a regex implementation, path allowlist expansion, scanner redesign, or historical-file remediation policy.

The correction must preserve fail-closed behavior for real implementation/tooling/CI/test changes and must not weaken Owner approval authority.

## Historical Scope Clarification

GAP-043 and GAP-044 are evidence of the silent-skip mechanism only. This Work ID does not reopen their released engineering fixes and does not declare their prior Owner decisions invalid. Any historical frontmatter cleanup, if desirable, must be explicitly justified and provenance-preserving rather than silently rewriting old governance history.

## Explicit Exclusions

GAP-047 does not own GAP-046 Service-Line semantics/design/implementation, GAP-041/GAP-042/GAP-045, accessibility/performance remediation, application code, production deployment, or unrelated owner-governance enhancements.

## Recommendation

**PROCEED TO GATE 2.**

Both defects are source-verifiable, bounded to the same gate-ordering enforcement surface, and serious enough to merit a dedicated governed correction. Gate 2 should compare the smallest safe approaches and define regression proof before any implementation change.

## Decision Needed

Owner decision requested: approve Gate 1 to proceed to Gate 2 design / request more information / decline / defer.

## What the owner is NOT being asked to decide

This Gate 1 does not ask the Owner to approve implementation, choose the exact code fix, modify CI policy, rewrite historical files, or merge any governance change. Those decisions belong to later gates after a design is presented and independently verified.
