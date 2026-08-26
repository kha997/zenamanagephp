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
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-26T13:43:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-26T13:43:00+07:00"
  updated_at: "2026-08-26T13:43:00+07:00"
generated_by: agent
---

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

B3's mechanism, in brief: a new committed file,
`docs/owner-governance/grandfathered-nonfrontmatter-documents.txt`
(generated once, snapshot-style, same discipline as the existing
`legacy-work-ids.txt`), lists the exact relative paths of every
`docs/superpowers/{plans,specs}/*.md` file that lacks complete frontmatter
at implementation time. Filename-token regex parsing is demoted from
"decides pass/fail" to "diagnostic message text only" (plus one narrow,
justified reuse: recognizing that a file's Work ID is already
independently legacy via `legacy-work-ids.txt`, for the one real case that
needs it — GAP-032's spec). Every other non-grandfathered, non-legacy-ID
file with incomplete frontmatter fails, unconditionally, regardless of
casing, hyphenation, or whether any recognizable token exists at all.

## Historical inventory (headline numbers; full table in the spec)

114 total `docs/superpowers/{specs,plans}/*.md` files. 11 already carry
complete frontmatter (unaffected). 103 do not, classified as: ~89
pre-governance-boundary feature docs with no Work-ID token at all
(out of scope — this is the corpus the original `continue` branch was
written to tolerate); 1 file (GAP-032's spec) with a legacy Work ID whose
filename token the current regex cannot see (`gap032`, no hyphen); 8 files
across GAP-037/038/039/040/043/044 — all already fully Gate-3-released —
whose lowercase and/or hyphen-less filenames are exactly Defect B's real,
demonstrated blast radius. None of these 103 files are modified by this
design; B3's grandfather-list snapshot at implementation time is what
keeps `main` from going red the moment the correction lands (this claim
is required to be proven by a snapshot-completeness regression test, not
just asserted — see spec §5.1).

## Regression test matrix

All 14 Owner-specified scenarios plus 3 design-specific additions
(snapshot-completeness, legacy-ID-via-widened-regex, diagnostic-only-regex)
are enumerated with expected result and mechanism in spec §5.
`GateOrderingDesignOnlyExemptionTest.php` is extended for the Defect A
cases; the existing Correction-3 frontmatter-presence fixture tests are
extended for straightforward presence/casing/malformedness cases; a new
focused class (e.g. `GateOrderingFrontmatterGrandfatherTest.php`) is
warranted for the grandfather-list mechanism itself, since it is new
mechanism rather than an extension of either existing surface.

## Workflow / configuration changes

None required or proposed. Both corrections live entirely inside
`scripts/ssot/owner_governance_lint.php` plus one new data file under
`docs/owner-governance/`. `.github/workflows/owner-governance-lint.yml` is
unchanged.

## Rollback and risk

Single-script-plus-one-data-file change; plain `git revert` rollback, no
schema/runtime/migration involvement. False-positive risk bounded by a
tested-complete grandfather snapshot. False-negative risk (the actual
defect) is eliminated for the entire `docs/superpowers/{plans,specs}`
corpus going forward — every file now resolves to one of two explicit
exemption lists or fails, with no remaining "unrecognized filename shape →
silently skipped" branch. Full detail in spec §8.

## What this Gate 2 does NOT authorize

- No implementation of either correction.
- No modification of `scripts/ssot/owner_governance_lint.php`, any
  workflow file, `legacy-work-ids.txt`, or any of the 103 historical
  spec/plan files identified in the inventory.
- No reopening or reinterpretation of GAP-032/037/038/039/040/043/044's
  released Gate history.
- No GAP-046 artifact is read or touched.
- This packet itself stays `gate_status: awaiting_owner` /
  `owner_decision.value: none` until a separate Owner Gate 2 decision is
  recorded here.

## Decision needed

Owner decision requested: approve this Gate 2 design (authorizing an
implementation plan and PR for A1 + B3 exactly as specified) / request
changes / decline.
