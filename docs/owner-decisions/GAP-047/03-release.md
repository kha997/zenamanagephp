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
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-27T12:42:00+07:00"
  updated_at: "2026-08-27T12:42:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-047 implementation (Defect A: docs/audits/ added to the design-only prefix allowlist; Defect B: B3 fail-closed exact-path frontmatter grandfather mechanism) is technically complete and verified. Full TDD RED->GREEN evidence recorded for both defects and the grandfather-config loader. All 19 Owner-specified regression cases (1-19) plus 10 loader fail-closed cases pass. tests/Unit/OwnerGovernance/ = 187/187 pass; full Unit testsuite = 904/904 pass, 0 failures. Local `php scripts/ssot/owner_governance_lint.php` and `--enforce-gate-ordering` both PASS against the real repository tree (93 files scanned, 0 violations; grandfather list byte-verified against the independently recomputed set). LIVE CI on implementation PR #291 exact head b23a6a011f56c8c0b318d204557c64efed623ba5 is fully green across all 31 checks, including the two Owner-designated required checks: Owner Governance Lint (pass) and test-routes-guardrails (pass). No workflow, application, database, route, or resource file was touched; docs/owner-governance/legacy-work-ids.txt is unmodified; GAP-046 and PR #288 are untouched; the 7 historical work items referenced by the grandfather file (GAP-032/037/038/039/040/043/044) are byte-unchanged. This Gate 3 packet records technical readiness only; it does not request or imply Ready-for-review, merge, release, or deployment authorization, which remain pending explicit Owner instruction."
technical_evidence:
  subject_sha: "b23a6a011f56c8c0b318d204557c64efed623ba5"
  implementation_tree_digest: "1273b9c9402cbf859763798291c3c1ced91224477aa75ce7a7b27ae0ec741c08"
  verified_pr_head_sha: "b23a6a011f56c8c0b318d204557c64efed623ba5"
  verified_at: "2026-08-27T12:42:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-047 — Owner Governance Lint: Gate 3 Release Request

## Status: AWAITING OWNER

This packet is prepared per the agent's implementation directive (GAP-047,
following the Owner-approved Gate 2 design in
`docs/owner-decisions/GAP-047/02-design.md`, source spec
`docs/superpowers/specs/2026-08-26-gap-047-owner-governance-lint-defects-design.md`,
merged Gate 2 record PR #290). It records technical readiness for Owner
review only. **It does not authorize Ready-for-review, merge, release, or
production deployment** — those require a separate, explicit Owner
instruction after this packet is reviewed.

## What changed (the release-candidate diff, `e5cbcce508cf5f3b4344f0117dbe08972bb4bc7e` → `b23a6a011f56c8c0b318d204557c64efed623ba5`)

```
 docs/owner-governance/grandfathered-nonfrontmatter-documents.txt          |  130 ++ (new)
 docs/superpowers/plans/2026-08-27-gap-047-owner-governance-lint-implementation.md | 1268 ++ (new)
 scripts/ssot/owner_governance_lint.php                                    |  185 +-
 tests/Unit/OwnerGovernance/GateOrderingDesignOnlyExemptionTest.php        |   98 ++
 tests/Unit/OwnerGovernance/GateOrderingFrontmatterGrandfatherTest.php     |  338 ++ (new)
 tests/Unit/OwnerGovernance/GrandfatherLoaderTest.php                      |  128 ++ (new)
```

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
   is computed against):** `b23a6a011f56c8c0b318d204557c64efed623ba5`
   (PR #291 head at the time this Gate 3 packet was authored, Draft).
3. This packet's own commit (adding this file) is excluded from the
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
  regression cases plus 11 grandfather-loader fail-closed cases).
- `vendor/bin/phpunit --testsuite Unit`: 904/904 pass, 0 failures.
- `php scripts/ssot/owner_governance_lint.php`: PASS (93 files, 0 violations).
- `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`: PASS
  (real repository tree, real grandfather file, zero
  `missing-governance-frontmatter` violations).
- LIVE CI on PR #291 exact head `b23a6a011f56c8c0b318d204557c64efed623ba5`:
  all 31 checks green, including `Owner Governance Lint` and
  `test-routes-guardrails`.

## Decision needed

Owner decision requested: approve Gate 3 (authorizing a subsequent, separate
Ready-for-review/merge instruction) / request correction / defer.

## What the Owner is NOT being asked to decide here

This packet does not request Ready-for-review, merge, release, or production
deployment authorization. Those remain separate, explicit Owner decisions to
be made after this Gate 3 technical-readiness review, per this repository's
established Gate 3 convention (see e.g. GAP-043's Gate 3 record).
