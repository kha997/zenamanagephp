---
work_id: GAP-047
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-047/02-design.md
---

# GAP-047 — Owner Governance Lint: design-only classification + missing-frontmatter silent-skip correction

Status: Gate 2 design, `awaiting_owner`. This document compares alternatives and
recommends a bounded correction for both defects recorded at Gate 1
(`docs/owner-decisions/GAP-047/01-request.md`,
`docs/audits/2026-08-26-gap-047-owner-governance-lint-evidence.md`). It does
not implement anything. All line references are to
`scripts/ssot/owner_governance_lint.php` as of commit `6a371405feeb44b644dcf16e76ee1c1a214c7134`
(post-Gate-1-merge `main`).

## 1. Current implementation, grounded in source

### 1.1 Design-only exemption (Defect A surface)

`OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES` (lines 432–436):

```php
const OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES = [
    'docs/owner-decisions/',
    'docs/superpowers/specs/',
    'docs/superpowers/plans/',
];
```

`owner_governance_changed_files_are_design_only()` (lines 445–464) returns
`true` only when every changed file starts with one of these three prefixes.
It is consumed at line 601–603 inside
`owner_governance_enforce_gate_ordering()`, gating the OWN-2026-005
"referenced-but-not-yet-approved Gate 2 packet" exemption. `docs/audits/**`
is not in the list, so a PR that is otherwise 100% documentation — a Gate 1
evidence file under `docs/audits/**` plus an owner-decision packet under
`docs/owner-decisions/**` — is *not* classified design-only, and if it also
carries a spec/plan referencing an `awaiting_owner` Gate 2 packet, the
exemption at line 605 does not apply and `gate-2-not-approved` fires. This is
exactly the mechanism GAP-046 hit at PR #287 (see Gate-1 evidence document
§ Key verified facts).

### 1.2 Frontmatter recognition and the bulk-scan silent skip (Defect B surface)

`owner_governance_enforce_gate_ordering()` (lines 499–612), per document:

1. Try to parse YAML frontmatter and read `work_id` (lines 518–524). If
   present, `$hasGovernanceFrontmatter` requires **both**
   `owner_governance_version` and `owner_gate_2_record` to also be set
   (line 522).
2. If there is no frontmatter `work_id` (line 526), fall back to a
   **case-sensitive, hyphen-required** filename regex:
   `'/\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b/'` (line 529). This matches
   `GAP-047` but not `gap-047`, `gap047`, or `GAP047`.
3. If the filename regex matches and the extracted ID is on
   `legacy-work-ids.txt`, `continue` (exempt) — line 532.
4. If the filename regex matches and the ID is **not** legacy, fail with
   `missing-governance-frontmatter` (lines 540–545). This path works
   correctly today, but only for uppercase-hyphenated filenames.
5. If the filename regex does **not** match at all: for a **bulk directory
   scan** (`$scanningExplicitFiles === false`, the normal CI case — no CLI
   targets means "scan `docs/superpowers/plans/*.md` +
   `docs/superpowers/specs/*.md`", lines 716–722), the code executes a bare
   `continue` (line 555) with **no violation emitted at all**. Only an
   *explicitly targeted* file (`--enforce-gate-ordering some/file.md`) still
   fails at lines 564–568.

Step 5 is Defect B's exact mechanism: a new governed spec/plan whose
filename uses a lowercase, hyphen-less, or otherwise non-canonical Work-ID
token — or no recognizable token at all — is invisible to the bulk scan that
CI actually runs (`.github/workflows/owner-governance-lint.yml` line 85,
`php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering` with no
explicit targets on `push`, and with `--changed-files-json=...` but still no
explicit *file* targets on `pull_request`, line 83). The scan is always a
bulk directory scan for path-recognition purposes; `$changedFiles` only
affects the *separate* design-only exemption check, not which files are
walked.

### 1.3 What is confirmed NOT a bug in scope

- `owner_governance_validate_packet()` (structural schema/enum validation
  of `docs/owner-decisions/**` packets) is unaffected by either defect and
  is not touched by this design.
- `enforcement-boundary.yml`'s `effective_date: "2026-08-04"` field is
  **not read anywhere in `owner_governance_lint.php`**. Only
  `legacy_exemption_file` is read (line 704). This is a latent gap
  independent of GAP-047's two defects — the written contract in
  `docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md` describes a
  date boundary that the code does not actually enforce; the code enforces
  only "on the `legacy-work-ids.txt` list, or not" today, and this
  design's grandfather mechanism (§3) below is a *path*-level analogue of
  that, not a resurrection of date logic. Flagged here for completeness;
  not part of GAP-047's bounded scope and not proposed for correction in
  this design.

## 2. Defect A — design-only classification

### 2.1 Alternatives considered

**A1 — Narrowly add `docs/audits/` to the existing prefix list.**

```php
const OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES = [
    'docs/owner-decisions/',
    'docs/superpowers/specs/',
    'docs/superpowers/plans/',
    'docs/audits/',
];
```

One-line, additive, reviewable diff. Preserves the existing prefix-list
mechanism verbatim (`owner_governance_changed_files_are_design_only()`'s
logic, tests, and OWN-2026-005 acceptance history are unchanged).
`docs/audits/**` is exclusively Gate 1 evidence documents by established
convention (every existing file under it is a dated `*-evidence.md` audit,
cross-checked against `docs/owner-decisions/**/01-request.md` §Evidence
references across GAP-039 through GAP-047) — it carries the same risk
profile as the three prefixes already exempted: prose, never executable or
config content, never consumed by any CI step other than being *referenced*
from a packet.

**A2 — Broaden to `docs/**` with exclusions.**

E.g. `docs/**` minus `docs/owner-governance/**`. Rejected. This would also
admit `docs/README.md`-adjacent operational runbooks,
`docs/superpowers/skills/**` (if any exist), and any future `docs/`
subdirectory nobody has reasoned about yet, as design-only by default —
inverting the exemption's burden of proof from "explicitly enumerated safe
prefix" to "not explicitly excluded," which is the opposite of the
fail-closed posture OWN-2026-005 established (see its Gate 2 record,
`docs/owner-decisions/OWN-2026-005/02-design.md`: "danh sách trống cũng
KHÔNG được coi là design-only" — absence of evidence is deliberately not
treated as evidence of safety, and by the same reasoning an unenumerated
directory should not be either). A new `docs/` subdirectory created for a
future purpose (e.g. a docs-based deploy runbook, or generated API
documentation with embedded examples) would silently inherit the exemption
with no review step. Rejected per Owner's stated preference and this
independent finding.

**A3 — Replace path-prefix classification with another mechanism**
(e.g. a per-file frontmatter flag, a `.governance-design-only` marker file,
content-based heuristics).

Rejected. Path-prefix-of-changed-files is the *entire* mechanism
`owner_governance_changed_files_are_design_only()` was built and
Gate-2-approved around (OWN-2026-005); replacing it is a materially larger
surface change than either defect requires, reopens an already-approved
design, and every alternative considered (frontmatter flag: trivially
spoofable by the same PR that adds the flag; marker file: same problem one
level up; content heuristics: unauditable, false-negative-prone) is *less*
verifiable than "this changed file's path literally starts with this
literal string," which needs no parsing and cannot be misinterpreted.

### 2.2 Recommendation: A1

Add `docs/audits/` as a fourth literal prefix. No other change to
`owner_governance_changed_files_are_design_only()`,
`owner_governance_enforce_gate_ordering()`'s call site, or the
`--changed-files-json` completeness/fail-closed mechanism
(`owner_governance_load_changed_files_json()`, lines 396–420) — none of
that logic is touched. This satisfies Owner's stated architectural
preference; the evidence above (existing `docs/audits/**` corpus is
uniformly Gate 1 evidence prose; A2's inversion-of-burden risk; A3's larger,
already-settled surface) supports it rather than disproving it.

## 3. Defect B — missing-frontmatter silent skip

### 3.1 Historical inventory (real, computed 2026-08-26 against `main`
at `6a371405`)

`docs/superpowers/specs/*.md`: 50 files. `docs/superpowers/plans/*.md`: 64
files. 114 total.

**Files with complete governance frontmatter (`work_id` +
`owner_governance_version` + `owner_gate_2_record`), 11 total — unaffected
by any change in this design:**

| Path | work_id |
|---|---|
| `docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md` | GAP-010b |
| `docs/superpowers/specs/2026-08-06-operational-gap-remediation-program-design.md` | OWN-2026-002 |
| `docs/superpowers/specs/2026-08-07-gap-034-export-tenant-isolation-design.md` | GAP-034 |
| `docs/superpowers/specs/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation-design.md` | OWN-2026-006 |
| `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` | OWN-2026-009 |
| `docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md` | GAP-037 |
| `docs/superpowers/specs/2026-08-18-gap-039-mysql-testing-integrity-design.md` | GAP-039 |
| `docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md` | GAP-040 |
| `docs/superpowers/plans/2026-08-08-gap-010b-legacy-csv-export-safety-implementation.md` | GAP-010b |
| `docs/superpowers/plans/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation.md` | OWN-2026-006 |
| `docs/superpowers/plans/2026-08-10-gap032-document-status-semantics.md` | GAP-032 |

**Files without complete frontmatter, 103 total.** Classified:

**Class 1 — pre-boundary feature-development docs (≈89 files), dated
2026-07-09 through 2026-08-04.** No Work-ID token of any kind in the
filename (e.g. `2026-07-15-quote-commercial-design.md`,
`2026-07-22-rbac-web-friendly-error-design.md`,
`2026-08-04-non-technical-owner-control-layer-design.md`). These predate
the governance system's own existence (`enforcement-boundary.yml`'s
`effective_date: "2026-08-04"`) and predate `GOVERNED_DOCUMENT_FRONTMATTER.md`
itself. They are not silently exploiting the bug — they are the *pre-existing
corpus the bug's `continue` branch was written to tolerate in the first
place* (see the code comment at lines 552–554: "matches original behavior
for truly unrelated docs, and for the pre-existing corpus whose filenames
don't carry a recognizable token, e.g. GAP-031's real plan/spec files").
None have Gate records; none claim to be governed.

**Class 2 — legacy-Work-ID doc whose filename token the current regex
cannot see, 1 file:**

| Path | Apparent Work ID | Casing issue | Legacy-list member? | Gate records exist? |
|---|---|---|---|---|
| `docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md` | GAP-032 (`gap032`, no hyphen) | lowercase + no hyphen | **Yes** (`GAP-032` is on `legacy-work-ids.txt`) | Yes — `docs/owner-decisions/GAP-032/{01-request,02-design,03-release}.md`, all released |

GAP-032's *plan* file (`2026-08-10-gap032-document-status-semantics.md`,
also lowercase/no-hyphen in its filename) already carries full frontmatter
and is unaffected. Only the spec file lacks it. GAP-032 predates the
2026-08-04 boundary as a Work ID (registered before the governance system
existed) even though this particular spec file postdates it by six days —
its Gate 2/3 records are already fully released and approved.

**Class 3 — non-legacy Work-ID docs the bulk scan currently skips
silently, 8 files, all created after the effective-date boundary, none on
`legacy-work-ids.txt`, all with fully released Gate records:**

| Path | Work ID | Filename form | Gate records exist? |
|---|---|---|---|
| `docs/superpowers/plans/2026-08-17-gap037-treasury-schema-migrations.md` | GAP-037 | `gap037` (lowercase, no hyphen) | Yes, released |
| `docs/superpowers/plans/2026-08-18-gap038-treasury-native-check-constraints.md` | GAP-038 | `gap038` (lowercase, no hyphen) | Yes, released (`03-release.md` only — GAP-038 folded into GAP-037's Gate chain) |
| `docs/superpowers/plans/2026-08-19-gap-039-mysql-testing-integrity-implementation.md` | GAP-039 | `gap-039` (lowercase, hyphenated) | Yes, released (spec sibling already has frontmatter; plan does not) |
| `docs/superpowers/plans/2026-08-20-gap-040-testcase-mysql-transaction-isolation.md` | GAP-040 | `gap-040` (lowercase, hyphenated) | Yes, released (spec sibling already has frontmatter; plan does not) |
| `docs/superpowers/specs/2026-08-21-gap-043-performance-test-mysql-portability-design.md` | GAP-043 | `gap-043` (lowercase, hyphenated) | Yes, released |
| `docs/superpowers/plans/2026-08-21-gap-043-performance-test-mysql-portability-implementation.md` | GAP-043 | `gap-043` (lowercase, hyphenated) | Yes, released |
| `docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md` | GAP-044 | `gap-044` (lowercase, hyphenated) | Yes, released |
| `docs/superpowers/plans/2026-08-23-gap-044-testcase-transaction-and-permission-lookup-implementation.md` | GAP-044 | `gap-044` (lowercase, hyphenated) | Yes, released |

**Class 4 — non-legacy Work-ID docs with an uppercase-hyphenated filename
token that the current regex already recognizes correctly, 0 files
found lacking frontmatter** (every file matching `\bGAP-\d{3}\b` /
`\bOWN-\d{4}-\d{3}\b` in the current corpus that lacks the legacy-list
membership already has complete frontmatter — this is expected, since that
is the one path (§1.2 step 4) that already fails loudly and would have
forced correction before merge).

**Summary:** Defect B's demonstrated real-world blast radius is exactly
Class 2 (1 file) + Class 3 (8 files) = 9 files, all for already-fully-released
work items (GAP-032, GAP-037, GAP-038, GAP-039, GAP-040, GAP-043, GAP-044).
Class 1 (89 files) is out of scope by design — it is the pre-governance
corpus, not a defect symptom.

### 3.2 Alternatives considered

**B1 — Case-insensitive filename fallback only.**

E.g. change line 529's pattern to
`'/\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b/i'`. Rejected as a *complete* fix per
Owner's explicit instruction, and independently: it only helps files whose
token is hyphenated-but-wrong-case (`gap-043`, `gap-044`, `gap-039`,
`gap-040` — 6 of the 9 affected files). It does **not** help GAP-032,
GAP-037, or GAP-038's filenames (`gap032`, `gap037`, `gap038` — no hyphen
at all, so even case-insensitive the pattern `GAP-\d{3}` never matches).
More fundamentally, B1 leaves the exact defect mechanism intact: **any**
future filename shape the pattern doesn't anticipate (e.g. `GAP47`,
`gap_047`, a plan named purely by feature slug with the ID only inside the
frontmatter-that-was-forgotten) still falls into the same silent
bulk-scan `continue`. B1 treats a symptom of one specific casing bug, not
the structural defect (trusting filename shape as the sole gate for
whether a violation is even checked).

**B2 — Changed-files-only enforcement for missing frontmatter.**

Only check frontmatter presence for files present in `$changedFiles` (the
current PR's diff), never in a full bulk scan. Rejected. This weakens the
`push`-to-`main` path identically to before (still bulk, still silent) and,
worse, on `pull_request` it would mean a spec file's frontmatter is
*only* checked the one time it is first touched — a file added correctly
once, then never modified again, keeps whatever frontmatter state it had
forever, with no periodic or push-triggered re-verification. It also adds
a second, narrower notion of "which files does this rule apply to"
alongside the OWN-2026-005 design-only `$changedFiles` check that already
exists for a *different* purpose, inviting exactly the kind of
conflation Correction 3's docblock (lines 512–514) says the two failure
modes must never have.

**B3 — Explicit path-level grandfathering of the historical corpus, then
fail-closed for everything else regardless of filename shape.**

Every `docs/superpowers/specs/*.md` and `docs/superpowers/plans/*.md` file
is required to have complete governance frontmatter **unless its exact
relative path is listed in an explicit, committed grandfather file**. No
filename-shape recognition is used to decide pass/fail at all — only to
produce a friendlier diagnostic message. Filename regex parsing is
demoted from "the fallback authority for whether this file must have
frontmatter" to "irrelevant to that decision, useful only for wording the
violation." See §3.3 for the mechanism and §3.4 for main-branch impact.

**B4 — Retroactively add frontmatter to historical files.**

Rejected outright per Owner's explicit Historical Policy directive (§8):
GAP-043 and GAP-044 (and by the same reasoning GAP-032/037/038/039/040)
"do not need to be reopened or cosmetically rewritten." Editing 103
historical files' content to satisfy a lint written after they were
authored and approved would be a provenance-destroying mass edit across
multiple already-released, Owner-approved work items, for zero behavioral
benefit over B3.

### 3.3 Recommendation: B3, with a two-part mechanism

**Part 1 — new grandfather-path file**,
`docs/owner-governance/grandfathered-nonfrontmatter-documents.txt`,
same format/generation discipline as the existing
`docs/owner-governance/legacy-work-ids.txt`:

```
# Generated by scripts/ssot/generate_grandfathered_nonfrontmatter_documents.php
# Snapshot commit: <the exact commit SHA this correction is implemented at>
# Reason: docs/superpowers/specs/*.md and docs/superpowers/plans/*.md files
# that existed at the snapshot commit without complete governance
# frontmatter (work_id + owner_governance_version + owner_gate_2_record).
# Exempt from the missing-governance-frontmatter check ONLY at this exact
# path. A NEW file, even for the same work_id, is NOT covered by this list
# and must carry frontmatter or fail. This file is append-only in the
# narrow sense that removing an entry (because the file gained real
# frontmatter, or was deleted) is always safe; adding a NEW entry for a
# file that did not exist at the snapshot commit requires the same
# reviewable, CODEOWNERS-gated edit as legacy-work-ids.txt and must not
# become a routine way to skip writing frontmatter for new work.
docs/superpowers/plans/2026-07-09-design-item-phase1.md
... (all 103 paths from §3.1, one per line, generated not hand-typed)
```

Generated once, by a one-time script analogous to
`scripts/ssot/generate_legacy_work_ids.php`, run against the exact
implementation commit — never hand-maintained, so the list is provably a
faithful snapshot rather than something silently drifting from what the
repository actually contained. Lives under `docs/owner-governance/`
(governance configuration, deliberately **outside** the design-only
exemption prefixes — any future edit to this file is not itself a
design-only change and must go through its own governed work item, exactly
like `legacy-work-ids.txt` today).

**Part 2 — rewrite the frontmatter-detection branch** (replacing lines
515–570) to:

1. Parse frontmatter as today (lines 518–524).
2. If `work_id` + `owner_governance_version` + `owner_gate_2_record` are
   all present → proceed to today's existing Gate-2-record resolution
   logic (lines 572 onward) **unchanged**, except the legacy-Work-ID
   lookup at line 572 also normalizes the frontmatter `work_id` value
   itself (already canonical since it's typed by hand into YAML, not
   parsed from a filename — no change needed there).
3. If frontmatter is present but incomplete (`work_id` set, but
   `owner_governance_version` or `owner_gate_2_record` missing) → today's
   existing `incomplete-governance-frontmatter` violation (lines 576–583),
   **unchanged**.
4. If there is **no usable frontmatter at all** → check the file's exact
   relative path against the new grandfather list.
   - Path is listed → `continue` (exempt), byte-preserved, no violation.
   - Path is **not** listed → unconditional
     `missing-governance-frontmatter` violation, for **every** case:
     bulk scan or explicit target, filename carries a recognizable
     uppercase token, a lowercase token, a no-hyphen token, or no token
     at all. The `$scanningExplicitFiles` branch distinction (lines 548,
     556) is removed for this rule — it no longer matters *how* the file
     was reached, only whether it is grandfathered.
   - The filename-token regex (widened, see below) is still computed and,
     when it matches, included in the violation message text only —
     e.g. "File appears to reference GAP-047 by filename but is not on
     the frontmatter-grandfather list and has no governance frontmatter"
     vs. the generic message when no token is found — purely to make the
     CI failure easier to triage. It has **zero effect on pass/fail**.
   - The filename-token regex itself is widened to
     `/\b((?:GAP|OWN)-?\d{3,4}(?:-\d{3})?[a-z]?)\b/i` (case-insensitive,
     hyphen-optional) purely for that diagnostic text and for one
     remaining legitimate use: recognizing that a *legacy-work-id* file
     (§3.1 Class 2 — e.g. GAP-032's spec) is exempt via the **existing**
     `legacy-work-ids.txt` mechanism rather than needing a path-list
     entry too. Concretely: if the widened regex finds a token, normalize
     it to canonical form (uppercase, hyphen-inserted per the `GAP-\d{3}`
     / `OWN-\d{4}-\d{3}` shape), and if that normalized ID is on
     `legacy-work-ids.txt`, treat the file as exempt by the *existing*
     ID-level mechanism (this is the "extend the existing Work-ID
     mechanism" option Owner asked to be compared against a path list —
     used here, narrowly, only for files whose Work ID is already
     independently legacy for every other purpose, not as the general
     solution). Every other non-grandfathered, non-legacy-ID file fails,
     full stop.

This keeps two exemption lists with clearly separated authority: the
**existing** `legacy-work-ids.txt` continues to mean "this whole Work ID
predates the governance system for every gate-ordering purpose" (unchanged
scope: `gate_2_before_plan`, `gate_3_before_ready`, and now also
"missing-frontmatter, but only when a filename token identifies it as this
ID"); the **new** grandfather-path list means "this exact historical file,
whether or not its filename carries any recognizable token, is exempt from
frontmatter — nothing else." A file is never grandfathered by both
mechanisms redundantly in practice, but nothing breaks if it happens to
qualify under both (GAP-032's spec, per §3.1 Class 2, is covered by the
Work-ID mechanism already and does not strictly need a path-list entry —
included anyway in the generated snapshot for defense-in-depth, since the
generation script has no reason to special-case it out).

### 3.4 Why this doesn't retroactively break `main`

The grandfather-path file's snapshot generation is exhaustive by
construction: it is produced by `glob()`-ing the exact same two directories
the lint itself scans (`docs/superpowers/plans/*.md`,
`docs/superpowers/specs/*.md`) at the implementation commit, filtering to
files that do **not** already have complete frontmatter, and writing every
one of those paths out — the same 103-file set enumerated by hand in §3.1,
regenerated programmatically at implementation time (the exact count may
differ by the time Gate 2 is implemented if other PRs land first; the
generation script, not this document, is the source of truth at that
moment). Because every currently-non-frontmatter file becomes either (a) a
grandfather-list entry or (b) forced to gain frontmatter as part of this
same implementation PR, a fresh bulk scan run immediately after the
correction lands produces **zero** new violations against the same tree
that produced zero violations before the correction. `main`'s
`push`-triggered lint (line 85, no changed-files scoping at all — a full
bulk scan every time) is therefore never made red by this change, given a
complete snapshot. §7 below specifies the regression test that proves the
snapshot is complete at the moment the correction is proposed, so an
incomplete list is caught in the implementation PR's own CI rather than
discovered later against `main`.

## 4. Invariants preserved

Every invariant listed in the Owner directive §6 is satisfied by design:

- Frontmatter remains the sole authority for *new* governed documents —
  the grandfather list only ever exempts files that existed at a fixed
  historical snapshot, never a mechanism a new PR can add itself to as a
  substitute for frontmatter (adding to
  `docs/owner-governance/grandfathered-nonfrontmatter-documents.txt` is,
  like `legacy-work-ids.txt`, outside the design-only exemption prefixes
  and requires its own governed review).
- Filename parsing is never approval authority under B3 — it is reduced to
  diagnostic text and to recognizing membership in the pre-existing,
  independently-governed `legacy-work-ids.txt` list.
- Absence of frontmatter is never implicit legacy status — a file must be
  on one of the two *explicit* lists (Work-ID legacy list or path
  grandfather list) or it fails, unconditionally.
- New governed specs/plans missing frontmatter fail closed — confirmed by
  regression tests #6–#8, #10 (§7).
- Casing cannot alter enforcement semantics — the pass/fail branch no
  longer depends on the case-sensitive regex at all; only the diagnostic
  text does.
- Unrecognized filename shape cannot silently bypass enforcement — the
  `$scanningExplicitFiles` bulk-scan `continue` (line 555) is removed
  entirely; every reachable file is evaluated against the two explicit
  lists.
- Real legacy/historical exemptions are explicit and reviewable — both
  lists are committed, generated (not hand-edited in bulk), and outside
  the design-only exemption.
- The awaiting-owner design-only exemption (Defect A / A1) remains
  available only for genuinely documentation-only submissions —
  unchanged mechanism, one prefix added.
- Any implementation/tooling/test/CI/governance-enforcement change forfeits
  the design-only exemption — unchanged; `docs/owner-governance/**`
  (including both list files) stays outside
  `OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES`.
- `owner_gate_2_record` remains a reference, never proof of approval —
  §3.3 step 2 does not alter the Gate-2-approval resolution logic
  (lines 572–608) at all.
- No Owner decision semantics or packet enum meanings change — this
  design touches only `owner_governance_enforce_gate_ordering()` and its
  two supporting lists/constants; `owner_governance_validate_packet()`
  and `docs/owner-governance/packet-schema.yml` are untouched.

## 5. Regression test matrix

All 14 Owner-specified cases, with intended result and the concrete
mechanism under this design that produces it:

| # | Scenario | Expected | Mechanism |
|---|---|---|---|
| 1 | `awaiting_owner` Gate 2 + Gate-1 audit under `docs/audits/**` + owner-decision packet + governed spec, all changed files under the 4 exempt prefixes | PASS as docs-only | A1: `docs/audits/` now in `OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES`; `owner_governance_changed_files_are_design_only()` returns true; §3.3's frontmatter logic is unaffected by this axis |
| 2 | Same submission + a changed `app/**` file | FAIL | `owner_governance_changed_files_are_design_only()` returns false (unchanged logic) → `gate-2-not-approved` fires |
| 3 | Same submission + a changed `scripts/**` file | FAIL | same as #2 |
| 4 | Same submission + a changed `tests/**` file | FAIL | same as #2 |
| 5 | Same submission + a changed `docs/owner-governance/**` file | FAIL | `docs/owner-governance/` is deliberately never in the prefix list (A1 does not add it) |
| 6 | New lowercase-named governed spec, no frontmatter | FAIL (`missing-governance-frontmatter`) | §3.3 part 2 step 4: no frontmatter, path not on grandfather list (it's new) → unconditional fail regardless of casing |
| 7 | New uppercase-named governed spec, no frontmatter | FAIL | same mechanism — casing is irrelevant to the pass/fail branch |
| 8 | New governed spec/plan, no recognizable Work-ID token in filename, no frontmatter | FAIL, unless the written contract intentionally allows it | This design does **not** carve out an exception for tokenless new filenames — `GOVERNED_DOCUMENT_FRONTMATTER.md` already states every new spec/plan for a non-legacy work item must have frontmatter regardless of filename; §3.3 step 4 enforces this unconditionally, closing exactly the class-1-shaped loophole a new (not historical) tokenless file would otherwise exploit |
| 9 | Explicitly grandfathered historical document, no frontmatter | PASS, only via the explicit historical mechanism | §3.3 step 4, path found on `grandfathered-nonfrontmatter-documents.txt` |
| 10 | Non-grandfathered file that merely looks old / uses legacy-style naming | FAIL | Path not on either explicit list → fail, regardless of superficial resemblance to a grandfathered file's naming convention |
| 11 | Correct frontmatter + correct Gate-2 reference | PASS (existing behavior preserved) | §3.3 step 2, unchanged code path (lines 572–608) |
| 12 | Malformed/incomplete frontmatter | FAIL | §3.3 step 3, unchanged `incomplete-governance-frontmatter` (lines 576–583) |
| 13 | Declined/deferred Gate 2, design-only changed files | FAIL (design-only does not rescue it) | Unchanged: the A1 exemption only ever bypasses `gate-2-not-approved` when `gate2Status === 'awaiting_owner'` (line 601) — `declined`/`deferred`/`changes_requested`/`superseded` are excluded by that exact equality check, independent of Defect A's fix |
| 14 | Changed-files evidence missing/malformed/incomplete | FAIL closed, exactly as today | `owner_governance_load_changed_files_json()` (lines 396–420) is untouched by this design |

### 5.1 Additional regression tests this design requires beyond the 14

- **Snapshot completeness test**: at the implementation commit, every file
  matched by `glob('docs/superpowers/{plans,specs}/*.md')` either has
  complete frontmatter or appears verbatim in
  `grandfathered-nonfrontmatter-documents.txt` — i.e. a fresh bulk
  `--enforce-gate-ordering` run against that exact commit produces zero
  `missing-governance-frontmatter` violations. This is what makes §3.4's
  "does not retroactively break main" claim a tested fact, not an
  assertion.
- **Legacy-ID-via-widened-regex test**: GAP-032's real spec file
  (`docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md`)
  passes via the widened case/hyphen-insensitive token match resolving to
  `GAP-032` on `legacy-work-ids.txt`, independent of whether it also
  appears on the new grandfather-path list — proving the two exemption
  mechanisms don't conflict when a file legitimately qualifies for both.
- **Diagnostic-only regex test**: a fixture file with a filename token but
  not grandfathered still fails (proving the widened regex is never used
  to grant exemption, only to word the message) — this is effectively
  scenario #10 with an explicit assertion on the violation *rule name*
  (`missing-governance-frontmatter`, not e.g. a different rule that
  silently passes).

### 5.2 Test-class disposition

- `GateOrderingDesignOnlyExemptionTest.php` (OWN-2026-005's existing
  regression class for the design-only exemption): **extend** with cases
  1–5 above — it already owns
  `owner_governance_changed_files_are_design_only()` /
  `owner_governance_enforce_gate_ordering()`'s design-only-exemption
  fixtures and is the natural home for the `docs/audits/**` prefix
  addition's regression coverage.
- The existing Correction-3 gate-ordering fixture tests (frontmatter
  presence/absence, legacy-ID filename fallback — likely
  `OwnerGovernanceLintFixtureTest.php` or equivalent; exact class name to
  be confirmed against the actual test suite at implementation time, not
  invented here) should be **extended** for cases 6, 7, 11, 12 (frontmatter
  presence/casing/malformedness) since they already own that exact
  surface.
- A **new, focused regression class** — e.g.
  `GateOrderingFrontmatterGrandfatherTest.php` — is warranted specifically
  for: the grandfather-path list mechanism (cases 8, 9, 10), the
  snapshot-completeness test (§5.1), and the legacy-ID-via-widened-regex
  interaction test (§5.1). This is new mechanism, not an extension of
  existing exemption or presence/absence logic, and deserves its own file
  so the grandfather-list contract has one canonical, directly-discoverable
  test home (mirroring why `GateOrderingDesignOnlyExemptionTest.php` exists
  as its own file rather than being folded into the general lint test).

## 6. Historical policy

GAP-032, GAP-037, GAP-038, GAP-039, GAP-040, GAP-043, and GAP-044's spec/plan
files are **not** edited, reopened, or reinterpreted by this design. Their
Gate 1/2/3 records remain exactly as released. The only artifact this
correction adds that references them is the generated, byte-exact
grandfather-path list — a new, separate, provenance-preserving file, not a
change to any of those seven work items' own history. This matches
Owner's Historical Policy directive (§8) exactly: freeze historical
content, grandfather the minimum exact paths necessary, treat any future
retroactive normalization (adding frontmatter to these files after the
fact) as a separate, explicit, provenance-preserving decision — not
performed by, or implied by, this design.

## 7. Workflow / configuration changes

**None required.** Both defects are fully addressed inside
`scripts/ssot/owner_governance_lint.php`'s existing PHP logic plus two
data files under `docs/owner-governance/` (one new, one — `legacy-work-ids.txt`
— read but not modified). `.github/workflows/owner-governance-lint.yml`'s
trigger paths, job steps, and `--enforce-gate-ordering` invocation (with or
without `--changed-files-json`) are unchanged; the corrected function
signature and call sites are identical to today's. If implementation
discovers a workflow-level need this design did not anticipate, that
expanded surface must be returned to Owner review before being added —
it is explicitly not pre-authorized here.

## 8. Rollback and risk

**Rollback mechanics:** both changes are a single script file plus one new
generated data file. Reverting is a plain `git revert` of the
implementation commit; no schema, no database, no runtime state, no
migration. `legacy-work-ids.txt` is not modified, so no interaction with
its own rollback story.

**False-positive risk (a legitimate file now fails that shouldn't):**
bounded by snapshot completeness (§3.4, tested per §5.1) for everything
that exists at implementation time. Going forward, any brand-new spec/plan
lacking frontmatter now fails where it previously silently passed — this
is the *intended* effect of closing Defect B, not a false positive, but it
does mean engineers must remember to add frontmatter to every new governed
spec/plan from the implementation commit onward; `GOVERNED_DOCUMENT_FRONTMATTER.md`
already documents this requirement, so this is enforcement catching up to
an already-written contract, not a new obligation being invented.

**False-negative risk (a file that should fail still silently passes):**
eliminated for the `docs/superpowers/{plans,specs}/*.md` corpus specifically
— every file is now evaluated against one of two explicit lists or fails;
there is no remaining "unrecognized filename → silently ignored" branch.
Residual risk: a new file could be *manually* (incorrectly) added to the
grandfather-path list by a future PR, since that list is a plain committed
text file rather than something structurally locked to a fixed historical
commit. This is the same residual trust model `legacy-work-ids.txt` already
carries today (a reviewer must notice an inappropriate addition) — not a
new or larger risk than the existing precedent, and mitigated the same way:
the file lives under `docs/owner-governance/**`, which forfeits the
design-only exemption, so any PR touching it is never eligible for the
`awaiting_owner`-design-only fast path and must go through full review.

**Effect on existing `main`:** none, given a complete snapshot (§3.4);
tested directly (§5.1).

**Effect on PR events:** PRs touching only files already covered by
frontmatter or the two exemption lists are unaffected. A PR adding a new
non-grandfathered, non-legacy spec/plan without frontmatter now correctly
fails `--enforce-gate-ordering`, where before it could pass silently
(Class 3-shaped filenames) or already failed loudly (Class-4-shaped
filenames) — no regression for the cases that already worked.

**Effect on push/local lint execution:** identical mechanism runs on
`push` to `main` (line 85, no `--changed-files-json`) and on local
invocation — both already do a full bulk scan today; both now correctly
enforce presence for every file rather than silently skipping some, with
zero change to invocation syntax.

**Historical-document treatment:** covered in §6 — frozen, not rewritten.

**Proof the correction does not weaken gate ordering:** the Gate-2-approval
resolution logic itself (lines 572–608 — legacy short-circuit,
`incomplete-governance-frontmatter`, `gate-2-before-plan`,
`gate2-record-mismatch`, `gate-2-not-approved` and its design-only
exemption) is **entirely unmodified** by this design; only the *upstream*
decision of "does this file need to go through that logic in the first
place" (frontmatter-or-grandfathered) changes, and it changes strictly in
the fail-closed direction (fewer silent skips, never more).

## 9. Explicit scope boundary

This document authorizes design comparison and a recommendation only. No
code, script, workflow, schema, or test file is modified by this PR. The
two artifacts this design proposes —
`docs/owner-governance/grandfathered-nonfrontmatter-documents.txt` and the
rewritten frontmatter-detection branch in `owner_governance_lint.php` — are
implementation, and require a separate Owner Gate 2 APPROVED decision
before any implementation PR is opened. GAP-046 and its artifacts are not
referenced, read, or touched by this design or by GAP-047's scope
generally (Gate 1 Explicit Exclusions, carried forward unchanged).
