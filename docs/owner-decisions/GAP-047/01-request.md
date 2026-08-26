---
work_id: GAP-047
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md
  plan: null
  branch: docs/GAP-047-owner-governance-lint-evidence
  pr: "https://github.com/kha997/zenamanagephp/pull/289"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-26T12:43:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-26T12:43:00+07:00"
  updated_at: "2026-08-26T12:48:00+07:00"
generated_by: agent
---

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
