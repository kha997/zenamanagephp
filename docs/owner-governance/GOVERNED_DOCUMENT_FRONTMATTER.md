# Governed Document Frontmatter Contract

Applies to every `docs/superpowers/specs/*.md` and `docs/superpowers/plans/*.md` file created on or after the enforcement effective date (`docs/owner-governance/enforcement-boundary.yml`), for a work item that is not on the legacy exemption list.

## Required frontmatter (minimum)

```yaml
---
work_id: OWN-2026-001
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/OWN-2026-001/02-design.md
---
```

- `work_id` — the canonical ID (Correction 3, §6.4 of the approved design's identity model). **This is now the primary, authoritative source `owner_governance_lint.php` reads to associate a spec/plan file with a work item.** It must also appear consistently in that work item's Gate packets, its git branch name (as a substring), and its PR body/title — `owner_governance_lint.php --enforce-gate-ordering` cross-checks this (Step 4 below).
- `owner_governance_version` — schema-contract version this document was authored against. Fixed at `1` for the lifetime of this plan; exists so a future schema revision has a documented version field to bump rather than needing to invent one retroactively.
- `owner_gate_2_record` — direct path to the Gate 2 packet this plan/spec is downstream of. Redundant with deriving `docs/owner-decisions/<work_id>/02-design.md` from `work_id` alone, but stated explicitly rather than implicitly, so the lint can flag a mismatch (e.g. a copy-pasted `work_id` whose Gate 2 path was not updated to match) as its own distinct violation instead of silently trusting the derived path.

## Enforcement rule (fallback boundary, Correction 3)

- **Plan-filename regex parsing is fallback-only, used exclusively for documents that predate the enforcement effective date** (the pre-existing corpus this plan found via `docs/superpowers/plans/*.md`/`docs/superpowers/specs/*.md`, verified fact #17).
- **A new spec or plan file, for a work item not on the legacy exemption list, that lacks this frontmatter block fails `owner_governance_lint.php --enforce-gate-ordering` outright** — with a distinct violation rule (`missing-governance-frontmatter`) from the pre-existing `gate-2-before-plan` rule, so the two failure modes ("no frontmatter at all" vs. "frontmatter present, Gate 2 missing/not approved") are never conflated in CI output.
- **A work item cannot claim legacy status by omission.** The legacy exemption list (`docs/owner-governance/legacy-work-ids.txt`) is the only source of legacy status — a new plan file simply not having frontmatter does not fall back to being treated as legacy; it fails.
- **The legacy allowlist is not automatically expanded during normal feature work.** `scripts/ssot/generate_legacy_work_ids.php` (Step 1) is a one-time generation script run once, at this plan's execution time, against `OPERATIONAL_GAP_REGISTER.md` as it existed on the effective date. No task in this plan, and no CI step, re-runs it automatically or appends to `legacy-work-ids.txt` as a side effect of any other operation — adding an ID to that file is only ever a manual, reviewable, CODEOWNERS-covered edit (the file lives under `docs/owner-governance/`).
