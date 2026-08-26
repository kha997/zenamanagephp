---
work_id: GAP-047
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-26-gap-047-owner-governance-lint-defects-design.md
  plan: null
  branch: docs/GAP-047-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/290"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-26T13:43:00+07:00"
  owner_response_reference: "Owner Gate 2 Round 1 decision on PR #290, reviewed exact head eb4a6fa49466dabe69a37ba08e389c97b3e2ab30 (canonical main 6a371405feeb44b644dcf16e76ee1c1a214c7134, LIVE Owner Governance Lint SUCCESS, LIVE test-routes-guardrails SUCCESS): 'DECISION: CHANGES REQUIRED. The overall architecture is accepted in principle: Defect A: A1 remains the preferred solution. Defect B: B3 remains the preferred solution. However Gate 2 is NOT approved yet because B3 contains three material design inconsistencies that must be corrected before implementation authorization. Do not implement anything. Do not touch GAP-046 / PR #288. Revise PR #290 only. CORRECTION 1 — remove the self-referential snapshot SHA from the grandfather file; a file cannot deterministically contain the SHA of the commit whose SHA is itself determined by that file's contents; replace with a non-self-referential snapshot_baseline_sha contract: fetch origin, verify canonical origin/main, record that exact SHA as snapshot_baseline_sha, cut the implementation branch from that exact baseline, compute the historical non-frontmatter path set from that baseline tree before making implementation mutations; if main has moved by implementation time, use the newly verified canonical main SHA, not the Gate-2 base; the final implementation-head tests must separately prove every currently scanned spec/plan either has complete governance frontmatter or is on the exact grandfather-path list. CORRECTION 2 — filename parsing must have zero exemption authority; remove the widened-regex-infers-legacy-ID-therefore-PASS path entirely; the corrected missing-frontmatter decision is: (A) complete frontmatter exists -> proceed through existing Gate-ordering logic; (B) frontmatter exists but incomplete -> FAIL with existing incomplete-governance-frontmatter rule; (C) no usable governance frontmatter -> exact relative path present in grandfathered-nonfrontmatter-documents.txt -> PASS only through that explicit historical exemption, else FAIL with missing-governance-frontmatter; bulk scan vs explicit scan, filename casing, and presence/absence of a recognizable Work-ID token must none of them change this result; legacy-work-ids.txt may continue its current established purposes where the Work ID is already authoritatively known but must not become an alternate missing-frontmatter escape derived only from a filename; GAP-032's exact existing spec path is already part of the grandfather snapshot and needs no filename-derived legacy exemption; if a filename regex is retained at all it may only improve diagnostic message wording — filename parsing has zero pass/fail authority for missing-frontmatter enforcement. CORRECTION 3 — resolve the generator-artifact contradiction between a proposed permanent generator script and a described one-script-one-data-file implementation; make an explicit choice: preferred minimal design is no permanent generator script, a documented deterministic one-off generation command in the implementation plan, the grandfather file generated from the verified snapshot baseline, and regression tests proving the list is complete and no path was hand-invented; a committed generator script is acceptable only if Gate 2 explicitly includes it as an implementation artifact and updates scope/affected-file list/testing/rollback/risk-analysis; whichever option, a NEW no-frontmatter file introduced after the snapshot must not be silently appended to the grandfather list as routine behavior — it is a frozen historical snapshot, not a rolling allowlist. REQUIRED TEST-MATRIX CHANGES — keep the existing 14-case matrix, revise the B3-specific additions: remove legacy-ID-via-widened-regex => PASS; add case 15 (historical exact GAP-032 spec path, no frontmatter, exact path on grandfather list => PASS via path grandfather only), case 16 (a NEW non-grandfathered file whose filename appears to map to legacy GAP-032, e.g. lowercase/no-hyphen GAP032-shaped filename, no frontmatter => FAIL missing-governance-frontmatter, proving legacy-looking filename text cannot create exemption), case 17 (diagnostic regex, if retained: two otherwise identical non-grandfathered files, one with a recognizable Work-ID token in the filename and one without, must have the SAME result FAIL, only message wording may differ), case 18 (snapshot completeness: at implementation head every file in docs/superpowers/specs/*.md and docs/superpowers/plans/*.md either has complete frontmatter or its exact path occurs in the grandfather file), case 19 (grandfather immutability: adding a new no-frontmatter spec/plan after the snapshot without explicitly modifying the grandfather configuration => FAIL); the current 103 historical files may remain byte-unchanged; do not reopen GAP-032/037/038/039/040/043/044. GRANDFATHER LIST SEMANTICS — the snapshot may contain all existing no-frontmatter paths needed to keep the corrected bulk scan green; the currently observed number is 103 at the Gate-2 baseline but Gate 2 must not bind implementation correctness to that number; at implementation time the exact generated set is determined from the newly verified snapshot baseline; no historical file should be rewritten just to reduce that number; no future newly-created file may qualify merely because it resembles one of those historical filenames. DEFECT A — A1 remains accepted in principle: add only docs/audits/ to the existing explicit design-only path-prefix set; do not broaden to docs/**; do not add docs/owner-governance/**, scripts/**, tests/**, app/**, src/**, .github/**; do not change the paginated changed-files completeness/fail-closed mechanism. MECHANICAL CLEANUP — update references.pr in docs/owner-decisions/GAP-047/02-design.md to the actual PR #290 URL if repository convention permits/uses that field for an already-open PR; do not self-approve Gate 2; the revised packet must return/persist as gate_status: awaiting_owner, owner_decision.value: none, owner_decision.authority: human_owner; preserve truthful provenance of this review/revision; do not erase historical decision context. VERIFY AND STOP — push only the corrected Gate-2 documentation, then verify LIVE on the new exact head: canonical main SHA/drift, PR #290 state, exact head SHA, exact changed files, Owner Governance Lint, test-routes-guardrails.'"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-26T13:43:00+07:00"
  updated_at: "2026-08-26T15:20:00+07:00"
generated_by: agent
---

## Revision log

- **Round 1 (PR #290 head `eb4a6fa49466dabe69a37ba08e389c97b3e2ab30`):** Owner
  **CHANGES REQUIRED**. Architecture accepted in principle — Defect A: A1
  remains preferred; Defect B: B3 remains preferred — but B3 contained three
  material design inconsistencies that had to be corrected before
  implementation authorization:
  1. The grandfather file's header claimed to record "the exact commit SHA
     this correction is implemented at" inside itself — self-referential and
     undeterminable. Corrected to a non-self-referential
     `snapshot_baseline_sha` contract (see §3.3 Part 1 below).
  2. B3 gave filename-regex parsing a second, real pass/fail role (widened
     regex → infer legacy Work ID → consult `legacy-work-ids.txt` → PASS)
     alongside its stated "diagnostic-only" role. Removed entirely; the
     missing-frontmatter decision is now a strict A/B/C path-only tree with
     zero filename-derived exemption authority (see §3.3 Part 2 below).
  3. The design simultaneously proposed a committed generator script
     (`scripts/ssot/generate_grandfathered_nonfrontmatter_documents.php`)
     and described the change as "one existing lint script + one new data
     file," which cannot both be true. Resolved: no permanent generator
     script; a documented one-off generation command instead (see §3.3
     Part 1 below).
  The Owner also required test cases 15–19 (replacing the removed
  legacy-ID-via-widened-regex case), clarified that the 103-file count is
  descriptive at the Gate 2 baseline only and must not be hard-bound as an
  implementation correctness target, and directed the `references.pr` field
  above be updated to the actual open PR #290 URL. Full verbatim directive
  preserved in `decision_provenance.owner_response_reference` below. This
  revision (same PR #290, new commit) addresses all three corrections and
  the required test-matrix changes; it does **not** change the A1/B3
  architecture choice itself, which the Owner already accepted. `gate_status`
  returns to `awaiting_owner` / `owner_decision.value: none` for a fresh
  Owner review of the corrected design — this round is not a new decision by
  this packet, only the requested revision responding to the Round 1
  decision above.

## Owner Summary

GAP-047 Gate 1 (Owner approved, merged as `6a371405feeb44b644dcf16e76ee1c1a214c7134`)
established two real defects in `scripts/ssot/owner_governance_lint.php`'s
gate-ordering enforcement:

- **Defect A (false-red):** the OWN-2026-005 `awaiting_owner` design-only
  changed-file exemption excludes `docs/audits/**`, so a legitimate
  docs-only Gate 2 presentation that also carries conventional Gate 1
  audit evidence can incorrectly fail with `gate-2-not-approved`.
- **Defect B (false-green):** bulk governed-document discovery
  (`docs/superpowers/plans/*.md` + `.../specs/*.md`, scanned unconditionally
  on every CI run — see `.github/workflows/owner-governance-lint.yml`) can
  silently skip a new spec/plan lacking required governance frontmatter,
  because filename fallback Work-ID recognition is case-sensitive and
  hyphen-required, and the bulk-scan's unrecognized-file branch `continue`s
  instead of emitting `missing-governance-frontmatter`.

This Gate 2 is **design only**. It compares alternatives for both defects,
recommends a bounded correction, and specifies the required regression
test matrix. It does not implement anything and does not choose to modify
`scripts/ssot/owner_governance_lint.php`, any workflow file, or any
historical Gate artifact by itself — only the follow-on implementation PR
(subject to a separate Owner Gate 2 APPROVED decision) would do that.

The complete engineering design — including the real, computed historical
inventory of all 114 `docs/superpowers/{specs,plans}/*.md` files, classified
by frontmatter completeness, filename casing, legacy-list membership, and
Gate-record existence — is in the referenced spec:
`docs/superpowers/specs/2026-08-26-gap-047-owner-governance-lint-defects-design.md`.

## Alternatives compared

**Defect A** — A1 (add `docs/audits/` to the existing 3-prefix design-only
allowlist, one line), A2 (broaden to `docs/**` with exclusions — rejected,
inverts the fail-closed burden of proof OWN-2026-005 established), A3
(replace path-prefix classification entirely — rejected, reopens an
already-Gate-2-approved mechanism for no defect-specific benefit). **A1
recommended**, matching Owner's stated preference; nothing in the evidence
disproves it.

**Defect B** — B1 (case-insensitive filename regex only — rejected,
Owner-flagged as inadequate; independently confirmed inadequate here since
3 of the 9 real affected files, `gap032`/`gap037`/`gap038`, have no hyphen
at all and wouldn't match even case-insensitively), B2
(changed-files-only enforcement — rejected, weakens the always-bulk `push`
path and creates a second, narrower "which files does this rule apply to"
notion alongside the existing OWN-2026-005 changed-files check), B3
(explicit path-level grandfather list of the historical corpus, then
unconditional fail-closed for everything else regardless of filename shape
— **recommended**), B4 (retroactively add frontmatter to 103 historical
files — rejected per Owner's explicit Historical Policy directive).

B3's mechanism, in brief (**revised per Owner Round 1 — see Revision log
above**): a new committed file,
`docs/owner-governance/grandfathered-nonfrontmatter-documents.txt`, lists
the exact relative paths of every `docs/superpowers/{plans,specs}/*.md`
file that lacked complete frontmatter at a verified `snapshot_baseline_sha`
(the exact canonical `origin/main` SHA fetched and verified at
implementation start — never a self-referential claim about the
implementation commit's own SHA). The file is produced by a **documented
one-off generation command**, not a permanent committed generator script
(see §3.3 Part 1 in the spec for the full non-self-referential contract and
the generator-artifact resolution). Filename-token regex parsing has **zero
pass/fail authority** — it is diagnostic-message text only, full stop; the
missing-frontmatter decision is the strict A/B/C path-only tree in spec
§3.3 Part 2. GAP-032's spec is grandfathered by its **exact path**, like
every other historical file — it does not need, and does not use, any
filename-derived exemption. Every non-grandfathered file lacking complete
frontmatter fails, unconditionally, regardless of casing, hyphenation, or
whether any recognizable token exists at all.

## Historical inventory (headline numbers; full table in the spec)

114 total `docs/superpowers/{specs,plans}/*.md` files at the Gate 2 baseline
(`6a371405`). 11 already carry complete frontmatter (unaffected). 103 do
not at this baseline, classified as: ~89 pre-governance-boundary feature
docs with no Work-ID token at all (out of scope — this is the corpus the
original `continue` branch was written to tolerate); 1 file (GAP-032's
spec) with a legacy Work ID whose filename token the current regex cannot
see (`gap032`, no hyphen), grandfathered by exact path; 8 files across
GAP-037/038/039/040/043/044 — all already fully Gate-3-released — whose
lowercase and/or hyphen-less filenames are exactly Defect B's real,
demonstrated blast radius, also grandfathered by exact path. None of these
103 files are modified by this design.

**The 103/114 figures are descriptive of the Gate 2 baseline only — per
Owner Round 1, Gate 2 does not bind implementation correctness to this
exact count.** At implementation time, the grandfather set is whatever a
fresh `snapshot_baseline_sha` verification and directory scan actually
produce (fewer or more, if other PRs land first) — the count is generated,
never hand-typed or hard-coded, and no historical file is rewritten merely
to change it. What keeps `main` from going red the moment the correction
lands is the grandfather list being a complete, generated snapshot of
*whatever* the non-frontmatter set is at that exact verified baseline —
proven by the snapshot-completeness regression test (spec §5.1, case 18),
not by matching a specific number asserted here.

## Regression test matrix

All 14 Owner-specified scenarios (unchanged) plus **5 revised B3-specific
additions, cases 15–19** (spec §5.1), replacing the Round-1 draft's
rejected "legacy-ID-via-widened-regex ⇒ PASS" case:

- **15 — Historical exact GAP-032 spec path, no frontmatter, exact path on
  grandfather list** ⇒ PASS via path grandfather only (no filename/regex
  involvement).
- **16 — A NEW non-grandfathered file whose filename resembles legacy
  GAP-032** (e.g. lowercase/no-hyphen GAP032-shaped filename), no
  frontmatter ⇒ FAIL `missing-governance-frontmatter` — proves a
  legacy-looking filename cannot manufacture an exemption.
- **17 — Diagnostic-regex-if-retained equivalence**: two otherwise
  identical non-grandfathered files, one with a recognizable Work-ID token
  in its filename and one without ⇒ both FAIL, identically; only message
  wording may differ.
- **18 — Snapshot completeness**: at the implementation head, every file
  matched by `docs/superpowers/specs/*.md` and `docs/superpowers/plans/*.md`
  either has complete frontmatter or its exact path occurs in the
  grandfather file.
- **19 — Grandfather immutability**: adding a new no-frontmatter spec/plan
  after the snapshot, without explicitly modifying the grandfather
  configuration, ⇒ FAIL.

All 19 cases are enumerated with expected result and mechanism in spec §5
and §5.1. `GateOrderingDesignOnlyExemptionTest.php` is extended for the
Defect A cases (1–5); the existing Correction-3 frontmatter-presence
fixture tests are extended for straightforward presence/casing/
malformedness cases (6, 7, 11, 12); a new focused class (e.g.
`GateOrderingFrontmatterGrandfatherTest.php`) is warranted for the
grandfather-list mechanism itself (8, 9, 10, 15–19), since it is new
mechanism rather than an extension of either existing surface.

## Workflow / configuration changes

None required or proposed. Both corrections live entirely inside
`scripts/ssot/owner_governance_lint.php` plus one new data file under
`docs/owner-governance/`, generated by a **documented one-off command run
at implementation time** (not a permanent committed generator script — see
Revision log, Correction 3, and spec §3.3 Part 1). No new permanent script
is added to `scripts/ssot/`. `.github/workflows/owner-governance-lint.yml`
is unchanged.

## Rollback and risk

Single-script-plus-one-data-file change (no permanent generator script);
plain `git revert` rollback, no schema/runtime/migration involvement.
False-positive risk bounded by a tested-complete grandfather snapshot,
proven at the implementation head by the snapshot-completeness test (case
18) rather than asserted against a fixed count. False-negative risk (the
actual defect) is eliminated for the entire `docs/superpowers/{plans,specs}`
corpus going forward — every file now resolves to one of two explicit
exemption lists or fails, with no remaining "unrecognized filename shape →
silently skipped" branch, and no filename-derived pass path of any kind
(case 19 guards against silent rolling-allowlist drift). Full detail in
spec §8.

## What this Gate 2 does NOT authorize

- No implementation of either correction.
- No modification of `scripts/ssot/owner_governance_lint.php`, any
  workflow file, `legacy-work-ids.txt`, or any of the historical
  spec/plan files identified in the inventory.
- No permanent generator script is authorized under `scripts/ssot/`; only a
  documented one-off generation command at implementation time.
- No filename-derived pass/fail authority of any kind — filename regex, if
  retained, may only affect diagnostic message text, never the
  missing-frontmatter pass/fail outcome.
- No reopening or reinterpretation of GAP-032/037/038/039/040/043/044's
  released Gate history.
- No GAP-046 artifact is read or touched; PR #288 is not touched.
- This packet itself stays `gate_status: awaiting_owner` /
  `owner_decision.value: none` until a separate Owner Gate 2 decision is
  recorded here.

## Decision needed

Owner decision requested: approve this Gate 2 design (authorizing an
implementation plan and PR for A1 + B3 exactly as specified) / request
changes / decline.
