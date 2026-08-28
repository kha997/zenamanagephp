# GAP-047 — Owner Governance Lint gate-ordering false-red + silent-skip evidence

**Status:** Gate 1 evidence only. No governance script, workflow, schema, test, application, migration, or runtime behavior is changed by this document.

**Canonical baseline:** `main` at `f913f040063fc628ad8f425b5f01ff5da960d742`, independently re-verified before this Work ID was allocated on 2026-08-26.

**Work ID allocation:** `GAP-047` was searched across repository content, branches, GitHub issues, and pull requests before use; no prior use was found.

## 1. Trigger and observed failure

GAP-046 exposed the defect while presenting its Gate 2 design in the original combined PR #287.

At PR #287 head `7fe8b8d73b8a63b70a1e142d08cf98a97cda2878`:

- the submission was docs-only;
- `docs/owner-decisions/GAP-046/02-design.md` was correctly `gate_status: awaiting_owner` / `owner_decision.value: none`;
- the governed design spec carried the required governance frontmatter;
- structural owner-governance validation passed with 0 violations;
- `test-routes-guardrails` passed;
- Owner Governance Lint failed only in gate-ordering enforcement with:

`owner-governance-lint [gate-2-not-approved]: Document declares work_id 'GAP-046', whose Gate 2 packet exists but owner_decision.value is not 'approved'.`

The combined PR changed four documentation files: the Gate 1 audit under `docs/audits/**`, the Gate 1 packet, the Gate 2 packet, and the Gate 2 design spec. Splitting Gate 1 into PR #287 and reopening Gate 2 alone as PR #288 removed the `docs/audits/**` file from the Gate 2 diff; the same Gate 2 design then passed both required checks without any lint-script modification. This establishes that the failure was caused by the changed-file classification path, not by malformed GAP-046 Gate 2 artifacts.

## 2. Root cause A — design-only exemption excludes `docs/audits/**`

`owner_governance_changed_files_are_design_only()` uses the constant `OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES`.

On canonical main the allowlist is exactly:

- `docs/owner-decisions/`
- `docs/superpowers/specs/`
- `docs/superpowers/plans/`

`docs/audits/` is absent.

The OWN-2026-005 exemption allows a governed design spec to reference its own Gate 2 packet while that packet is `awaiting_owner` only when every changed file is classified as design-only. Therefore, any otherwise-valid docs-only Gate 2 submission that also carries its conventional Gate 1 evidence under `docs/audits/**` is classified as non-design-only and receives `gate-2-not-approved`.

This is a false-red in the governance enforcement layer. It does not indicate application-code drift, unauthorized implementation, or a malformed Gate 2 packet.

### Existing regression-coverage gap

`tests/Unit/OwnerGovernance/GateOrderingDesignOnlyExemptionTest.php` correctly proves the basic awaiting-owner design exemption with a changed-file set containing the Gate 2 packet and the governed spec. Its positive fixture does not include a `docs/audits/**` evidence file, so the conventional combined Gate-1/Gate-2 documentation shape was never exercised.

## 3. Root cause B — bulk governed-document scan can silently skip new lowercase-named specs/plans lacking frontmatter

The written frontmatter contract in `docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md` is explicit:

- every new `docs/superpowers/specs/*.md` and `docs/superpowers/plans/*.md` file after the enforcement effective date must declare governance frontmatter;
- filename parsing is fallback-only for pre-existing legacy documents;
- a new governed spec/plan without required frontmatter must fail with `missing-governance-frontmatter`;
- omission of frontmatter must never create de facto legacy status.

The bulk-scan implementation does not fully enforce that contract.

When a scanned file has no frontmatter work ID, the fallback filename regex is:

`/\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b/`

The regex is case-sensitive. Repository spec/plan naming convention commonly uses lowercase Work-ID text inside filenames, e.g.:

- `docs/superpowers/specs/2026-08-21-gap-043-performance-test-mysql-portability-design.md`
- `docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md`

Both current files begin directly with a Markdown heading and have no governance YAML frontmatter.

For a bulk directory scan, when the case-sensitive regex does not recognize a Work ID, the lint executes `continue` rather than emitting `missing-governance-frontmatter`. Consequently a post-effective-date lowercase-named governed document can be silently skipped.

This is a false-green / false-negative path in the governance enforcement layer.

### Historical illustration, not a request to reopen GAP-043 or GAP-044

The GAP-043 and GAP-044 design specs above illustrate the silent-skip path: both lack the frontmatter required by the written contract, yet their lowercase filenames do not match the uppercase-only fallback token. This Work ID does **not** claim their released engineering fixes are invalid and does not reopen either work item. Their documents are evidence that the lint did not evaluate a condition it was intended to enforce.

### Existing regression-coverage gap

`tests/Unit/OwnerGovernance/EnforcementBoundaryTest.php` verifies that the `missing-governance-frontmatter` rule exists, and the repository has an explicit missing-frontmatter fixture. However the dangerous production path is the default **bulk directory scan** combined with a lowercase Work-ID filename. Existing coverage does not pin that exact case strongly enough to prevent the silent `continue` behavior observed in current source.

## 4. Why the two defects belong in one Work ID

Both defects are in the same enforcement surface: `scripts/ssot/owner_governance_lint.php` gate-ordering behavior for governed design submissions.

They are complementary truthfulness failures:

1. Root cause A is **fail-closed in the wrong place**: a valid docs-only Gate 2 presentation becomes red because conventional audit evidence is not recognized as documentation eligible for the design-only exemption.
2. Root cause B is **fail-open in the wrong place**: a new governed document can bypass the required-frontmatter rule solely because its filename uses lowercase Work-ID text.

Fixing only A would remove the newly exposed false-red while leaving a proven silent false-green. Fixing only B would improve governed-document discovery while leaving conventional combined Gate documentation structurally impossible under the intended exemption. A single bounded governance-lint Work ID should restore both sides of the intended contract together.

## 5. User / operational impact

These are governance-system defects, not end-user product defects.

Current impacts are:

- legitimate design-only Gate 2 submissions can be blocked even when no implementation file changed;
- teams are incentivized to split otherwise-related documentation or to discover accidental bypasses merely to satisfy CI;
- new governed specs/plans can escape gate-ordering validation without frontmatter, creating false confidence that CI enforced the Owner Control Layer;
- historical green CI cannot be interpreted as proof that every governed spec/plan was actually evaluated by gate-ordering enforcement.

## 6. Scope proposed for GAP-047

Gate 1 asks the Owner to confirm that both root causes are real and should proceed to a dedicated Gate 2 design.

A future Gate 2 should design the smallest correction that:

- makes legitimate docs-only evidence/design submissions classifiable without weakening the rule that implementation/tooling/CI/test changes forfeit the awaiting-owner exemption;
- makes bulk scanning fail closed for post-effective-date governed specs/plans missing required frontmatter regardless of filename casing;
- preserves genuine legacy exemptions only through the canonical legacy mechanism;
- adds regression coverage for the exact false-red and silent-skip paths;
- preserves the existing rule that `owner_gate_2_record` is a reference, not approval authority;
- does not retroactively rewrite historical Gate records merely to cosmetically normalize old files.

No exact code change, regex form, path allowlist shape, or historical remediation strategy is selected at Gate 1.

## 7. Explicit exclusions

GAP-047 does not own or modify:

- GAP-046 Service-Line design or implementation;
- GAP-041, GAP-042, GAP-045, accessibility/performance work;
- application behavior;
- production deployment;
- Owner decision semantics or packet enum meanings;
- branch protection policy beyond changes strictly necessary to correct the lint behavior if Gate 2 later proves such changes are required.

## 8. Gate-2 acceptance questions

Before implementation authorization, Gate 2 must answer at least:

1. What precise documentation paths should qualify for the awaiting-owner design-only exemption, and why does that not admit implementation/tooling changes?
2. How will bulk scanning identify a new governed spec/plan missing frontmatter independent of filename case without converting unrelated documents into false positives?
3. How are true pre-effective-date legacy documents distinguished from post-effective-date omissions?
4. What regression tests prove both false-red and false-green paths?
5. Are GAP-043/GAP-044 historical files left frozen as history, explicitly grandfathered, or corrected under a separate provenance-preserving mechanism?
6. Does the correction change only lint/test/docs surfaces, or does any workflow/config change become necessary?

## 9. Gate 1 recommendation

**Recommendation: PROCEED TO GATE 2.**

The two failures are independently traceable to current source and conflict with the written governance contract. The proposed Work ID is bounded to the Owner Governance Lint enforcement layer and does not require product-domain or runtime changes to establish the problem.
