---
work_id: GAP-047
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-08-26-gap-047-owner-governance-lint-defects-design.md
  plan: docs/superpowers/plans/2026-08-27-gap-047-owner-governance-lint-implementation.md
  branch: fix/GAP-047-owner-governance-lint
  pr: "https://github.com/kha997/zenamanagephp/pull/291"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-27T14:00:00+07:00"
  owner_response_reference: "Owner Gate 3 review, GAP-047, reviewed exact head 7857827e4221950e40b9c33d680c2bbe3efb8433 (base e5cbcce508cf5f3b4344f0117dbe08972bb4bc7e, state OPEN/Draft/mergeable, implementation-tree digest 1273b9c9402cbf859763798291c3c1ced91224477aa75ce7a7b27ae0ec741c08, 7 changed files): 'DECISION: CORRECTION REQUESTED. The implementation itself is accepted in principle: A1 implementation appears correct; B3 implementation appears correct; R=103/C=103/NEW=0 evidence is accepted; grandfather set=103 is accepted; scope discipline appears correct; final-head evidence-freshness check currently PASSes; implementation behavior does NOT need redesign. Gate 3 is NOT approved yet because three evidence/governance artifacts need correction: (1) the implementation plan (docs/superpowers/plans/2026-08-27-gap-047-owner-governance-lint-implementation.md) Task 7/case-18 recovery instruction contradicts Binding Clarification A by suggesting regeneration of the grandfather file against the current tree on failure, instead of the required fail-closed NEW-must-be-empty-or-STOP sequence; (2) 03-release.md contains an internal test-count inconsistency (mandatory summary says 19 cases + 10 loader fail-closed cases; body says 19 + 11 grandfather-loader fail-closed cases) that must be corrected to the true breakdown (GrandfatherLoaderTest.php: 11 tests total — 8 fail-closed rejection cases plus 3 acceptance/normal-semantics cases), never inflated or approximated; (3) the PR #291 body is stale (says Gate 3 not yet prepared, which is now false) and must be corrected as metadata only. Do not merge. Do not mark PR ready. Do not deploy. Do not change owner_governance_lint.php, the grandfather file, or any test file unless the remediation genuinely proves a technical defect. Correcting the implementation plan changes the implementation-tree digest (the plan is part of the implementation tree); the digest must be refreshed after the plan-only correction and this packet re-presented as gate_status: awaiting_owner / owner_decision.value: none / decision_requested: approve_or_correction_or_defer, preserving this correction-requested decision in historical provenance without erasure, with owner_decision_binding remaining unapproved/null throughout (this is not an approval decision).'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-27T12:42:00+07:00"
  updated_at: "2026-08-27T14:37:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-047 implementation (Defect A: docs/audits/ added to the design-only prefix allowlist; Defect B: B3 fail-closed exact-path frontmatter grandfather mechanism) is technically complete and verified; unchanged since Owner's correction-requested review (Correction Round 1 above) — Owner accepted A1/B3/R=103/C=103/NEW=0/grandfather=103/scope discipline. This re-presentation corrects only the two documentation/governance artifacts the Owner identified: (1) the implementation plan's Task 7/case-18 recovery instruction now specifies the explicit fail-closed NEW-must-be-empty-or-STOP sequence instead of a current-tree regeneration; (2) this packet's test-count claims are corrected to the true, freshly re-verified breakdown. Because the implementation plan is part of the implementation tree, this correction moved the implementation-tree digest from 1273b9c9402cbf859763798291c3c1ced91224477aa75ce7a7b27ae0ec741c08 (Owner-reviewed head) to 31f595749905dc4ee03d6208f3e647fb59b7b8b776e791c3d8c43e8188247657 (new subject_sha dd32e6e2f0f49364bc5153dceb8552f0e4f1a516) — verified by recomputing the digest independently at both dd32e6e2 and the current PR head, confirming byte-for-byte equality (only this Gate-3 record file differs between them, which is excluded from the digest by construction). Fresh re-verification after both corrections: all 19 Owner-specified regression cases (1-19) pass, plus all 11 GrandfatherLoaderTest.php tests (8 fail-closed rejection cases: missing file, unreadable file, absolute path, traversal, wrong root, non-.md, duplicate entry, malformed/control-character entry; and 3 acceptance/normal-semantics cases: blank/comment handling, valid exact paths, empty-after-comments behavior) — freshly run standalone: OK (11 tests, 11 assertions). tests/Unit/OwnerGovernance/ = 187/187 pass (fresh run); full Unit testsuite = 904/904 pass, 0 failures (fresh run, 27 pre-existing unrelated skips). Local `php scripts/ssot/owner_governance_lint.php` and `--enforce-gate-ordering` both PASS against the real repository tree (94 files scanned, 0 violations — grandfather list unchanged, byte-verified against the independently recomputed set; R=103/C=103/NEW=0 freshly reconfirmed against unchanged origin/main e5cbcce508cf5f3b4344f0117dbe08972bb4bc7e). No workflow, application, database, route, or resource file was touched in either round; docs/owner-governance/legacy-work-ids.txt, scripts/ssot/owner_governance_lint.php, the grandfather file, and every test file remain byte-identical to the Owner-reviewed head — this remediation touched only the implementation plan and this Gate-3 record, per Owner's scope lock. GAP-046 and PR #288 are untouched; the 7 historical work items referenced by the grandfather file (GAP-032/037/038/039/040/043/044) remain byte-unchanged. This Gate 3 packet records technical readiness only; it does not request or imply Ready-for-review, merge, release, or deployment authorization, which remain pending explicit Owner instruction."
technical_evidence:
  subject_sha: "dd32e6e2f0f49364bc5153dceb8552f0e4f1a516"
  implementation_tree_digest: "31f595749905dc4ee03d6208f3e647fb59b7b8b776e791c3d8c43e8188247657"
  verified_pr_head_sha: "dd32e6e2f0f49364bc5153dceb8552f0e4f1a516"
  verified_at: "2026-08-27T14:37:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-047 — Owner Governance Lint: Gate 3 Release Request

## Correction Round 1 — Owner Gate 3 Review (2026-08-27)

**Owner Gate 3 decision: CORRECTION REQUESTED**, reviewed at exact head
`7857827e4221950e40b9c33d680c2bbe3efb8433` (implementation-tree digest
`1273b9c9402cbf859763798291c3c1ced91224477aa75ce7a7b27ae0ec741c08`, 7
changed files). The Owner accepted the implementation itself in principle —
A1 and B3 correct, R=103/C=103/NEW=0 accepted, grandfather set=103 accepted,
scope discipline correct, evidence-freshness passing, no redesign needed —
but required three evidence/governance corrections before Gate 3 approval:

1. **Implementation-plan contradiction:** the plan's Task 7 / case-18
   recovery instruction (docs/superpowers/plans/2026-08-27-gap-047-owner-governance-lint-implementation.md)
   suggested regenerating the grandfather file against the current tree on
   a case-18 failure, which contradicts Binding Clarification A (the
   grandfather set may never automatically grow). Corrected to an explicit
   fail-closed NEW-must-be-empty-or-STOP sequence.
2. **Internal test-count inconsistency in this packet:** the mandatory
   summary and body disagreed on the `GrandfatherLoaderTest.php` case count
   (10 vs 11) and both mischaracterized all cases as "fail-closed." Corrected
   to the true, freshly re-verified breakdown: 11 tests total — 8 fail-closed
   rejection cases + 3 acceptance/normal-semantics cases.
3. **Stale PR body:** PR #291's body said "Gate 3: Not yet prepared,"
   which was no longer true once the Gate 3 packet existed. Corrected as a
   metadata-only PR body update.

Per Owner's explicit scope lock: this is a documentation/governance
correction only. `scripts/ssot/owner_governance_lint.php`, the grandfather
file, and every test file remain unchanged from the reviewed head — no
technical defect was found or claimed. Because the implementation plan is
part of the implementation tree, correcting it changes the
implementation-tree digest; the refreshed subject_sha/digest are recorded
below, superseding (not erasing) the reviewed-head values above as
historical evidence of this correction round.

This decision is preserved here as permanent provenance and is not erased
by the packet's return to `awaiting_owner` below.

## Status: AWAITING OWNER (re-presented after Correction Round 1 remediation above)

This packet is prepared per the agent's implementation directive (GAP-047,
following the Owner-approved Gate 2 design in
`docs/owner-decisions/GAP-047/02-design.md`, source spec
`docs/superpowers/specs/2026-08-26-gap-047-owner-governance-lint-defects-design.md`,
merged Gate 2 record PR #290). It records technical readiness for Owner
review only. **It does not authorize Ready-for-review, merge, release, or
production deployment** — those require a separate, explicit Owner
instruction after this packet is reviewed.

## What changed (the release-candidate diff, `e5cbcce508cf5f3b4344f0117dbe08972bb4bc7e` → `dd32e6e2f0f49364bc5153dceb8552f0e4f1a516`)

```
 docs/owner-governance/grandfathered-nonfrontmatter-documents.txt          |  130 ++ (new)
 docs/superpowers/plans/2026-08-27-gap-047-owner-governance-lint-implementation.md | 1278 ++ (new; Correction Round 1 replaced the 1-line case-18 recovery instruction with the explicit 10-line fail-closed sequence, net +10 lines vs the Owner-reviewed head)
 scripts/ssot/owner_governance_lint.php                                    |  185 +-
 tests/Unit/OwnerGovernance/GateOrderingDesignOnlyExemptionTest.php        |   98 ++
 tests/Unit/OwnerGovernance/GateOrderingFrontmatterGrandfatherTest.php     |  338 ++ (new)
 tests/Unit/OwnerGovernance/GrandfatherLoaderTest.php                      |  128 ++ (new)
```

(Unchanged since Correction Round 1: `scripts/ssot/owner_governance_lint.php` and all three test files are byte-identical to the Owner-reviewed head `7857827e4221950e40b9c33d680c2bbe3efb8433` — only the implementation plan changed, per Owner's scope lock.)

**Defect A (A1):** `docs/audits/` added to `OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES`
(one-line, additive). **Defect B (B3):** the case-sensitive-filename-driven
bulk-scan silent-skip is replaced with a strict three-way frontmatter
decision tree (complete / incomplete / no-frontmatter-so-check-grandfather-list-or-fail),
backed by a new fail-closed loader `owner_governance_load_grandfathered_paths()`
and a frozen, generated 103-entry grandfather snapshot file. Filename
parsing retains zero pass/fail authority — diagnostic wording only.

No `.github/workflows/**`, `app/**`, `database/**`, `routes/**`,
`resources/**` file was touched. `docs/owner-governance/legacy-work-ids.txt`
is unmodified. GAP-046 and PR #288 are not referenced or touched. The seven
historical work items whose spec/plan files appear in the new grandfather
list (GAP-032, GAP-037, GAP-038, GAP-039, GAP-040, GAP-043, GAP-044) are
byte-unchanged — only their exact paths are listed as grandfather entries.

## Three SHAs — do not conflate

1. **Canonical baseline / snapshot_baseline_sha:** `e5cbcce508cf5f3b4344f0117dbe08972bb4bc7e`
   (main, prior to this work; also the approved Gate-2 PR #290 squash-merge SHA).
2. **Release-candidate implementation commit (subject_sha, what would merge
   if approved, and what this packet's `technical_evidence.implementation_tree_digest`
   is computed against):** `dd32e6e2f0f49364bc5153dceb8552f0e4f1a516`
   (the last commit to change non-Gate-3-record content — the Correction
   Round 1 plan fix — on PR #291, Draft). Superseded from the originally
   reviewed `b23a6a011f56c8c0b318d204557c64efed623ba5` because the plan
   correction changed the implementation tree; the digest was refreshed
   accordingly (see Correction Round 1 above and `mandatory_technical_gate_summary`).
3. This packet's own commits (adding/editing this file) are excluded from the
   implementation-tree digest by construction (`owner_governance_compute_implementation_tree_digest()`
   excludes exactly `docs/owner-decisions/GAP-047/03-release*.md`), so the
   digest is unchanged whether it is recomputed before or after this
   packet's own commit — only a change to any OTHER file would move it.

## Regression / governance evidence summary

- **R** (Owner-reviewed historical no-complete-frontmatter path set at Gate-1
  merge `6a371405feeb44b644dcf16e76ee1c1a214c7134`): 103 paths.
- **C** (same computation at `snapshot_baseline_sha` `e5cbcce508cf5f3b4344f0117dbe08972bb4bc7e`):
  103 paths, byte-identical to R.
- **NEW** = C − R: 0 (empty — no drift, no STOP condition triggered).
- Final grandfather-file entry count: 103, independently re-verified
  byte-identical to the computed set via `diff` (exit 0).
- `tests/Unit/OwnerGovernance/`: 187/187 pass (includes all 19 Owner-specified
  regression cases plus all 11 `GrandfatherLoaderTest.php` tests: 8
  fail-closed rejection cases — missing file, unreadable file, absolute
  path, traversal, wrong root, non-.md, duplicate entry,
  malformed/control-character entry — and 3 acceptance/normal-semantics
  cases — blank/comment handling, valid exact paths, empty-after-comments
  behavior. Freshly re-verified via `vendor/bin/phpunit tests/Unit/OwnerGovernance/GrandfatherLoaderTest.php`
  → `OK (11 tests, 11 assertions)`.).
- `vendor/bin/phpunit --testsuite Unit`: 904/904 pass, 0 failures (fresh
  re-run after Correction Round 1).
- `php scripts/ssot/owner_governance_lint.php`: PASS (94 files scanned —
  the count includes this Gate-3 record itself; 0 violations).
- `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`: PASS
  (real repository tree, real grandfather file, zero
  `missing-governance-frontmatter` violations; fresh re-run).
- **Round 1** LIVE CI, PR #291 exact reviewed head
  `7857827e4221950e40b9c33d680c2bbe3efb8433`: 31/31 checks green (including
  `Owner Governance Lint` and `test-routes-guardrails`), `deploy` correctly
  skipping. A transient CI-timing artifact was observed and resolved on this
  head: the first automated run of `Owner Governance Lint` failed only with
  an evidence-freshness 300-second wait-timeout message while sibling jobs
  were still legitimately running; re-running just that job
  (`gh run rerun 33043412424 --failed`) passed clean with zero code/content
  changes — an orchestration timing artifact, not a defect. **Round 2**
  (post-remediation) LIVE CI on the new exact head is pending push and will
  be verified before this packet is considered ready for Owner re-review;
  see the implementation session's live verification for the confirmed
  fresh result.

## Decision needed

Owner decision requested: approve Gate 3 (authorizing a subsequent, separate
Ready-for-review/merge instruction) / request correction / defer.

## What the Owner is NOT being asked to decide here

This packet does not request Ready-for-review, merge, release, or production
deployment authorization. Those remain separate, explicit Owner decisions to
be made after this Gate 3 technical-readiness review, per this repository's
established Gate 3 convention (see e.g. GAP-043's Gate 3 record).
