# OWN-2026-020 — Register and roadmap reconciliation: Gate-1 evidence

**Date:** 2026-10-28 (+07:00)

**Canonical base:** `2fa819d0bbe240174c5ada93a1f8b5f84664a3d4` (GAP-065 released)

**Branch:** `docs/OWN-2026-020-register-roadmap-reconciliation`

**Scope:** Read-only comparison of the planning records with the released state on `main`. No code change.

## 1. `OPERATIONAL_GAP_REGISTER.md` rows that are stale

| Row | Register says | Released state on `main` |
|---|---|---|
| GAP-012 | OPEN (self-declared in backlog) | Gate 3 approved; PR #370, squash `588daf8e` |
| GAP-013 | OPEN (self-declared in backlog) | Gate 3 approved; PR #371, squash `68104387` |
| GAP-014b | OPEN (verified 2026-08-06) | Gate 3 approved; PR #372, squash `740b8450` |
| GAP-014 (parent) | PARTIALLY RESOLVED; 014b and 014c open | 014a and 014b resolved, only 014c open (parent stays PARTIALLY RESOLVED, note to update) |
| GAP-065 | OPEN (Gate 1 awaiting Owner since 2026-10-07) | Gate 3 approved; PR #338, squash `2fa819d0` |

## 2. Released Work IDs with no register row

- **Since OWN-2026-019 (the last reconciliation):** GAP-094 (PR #373, `de952191`), GAP-095 (PR #374, `c7b8efff`),
  GAP-096 (PR #375, `129f3f39`), GAP-097 (PR #376, `2b336bce`) — Treasury S4b-1 … S6.
- **Older, never registered:** GAP-035, GAP-037, GAP-038, GAP-039, GAP-046, GAP-047, GAP-048, GAP-049, GAP-050
  (packets in `docs/owner-decisions/`; OWN-2026-019 started from GAP-063).

## 3. GAP-031 packet inconsistency

- `docs/owner-decisions/GAP-031/03-release-v2.md` is still `gate_status: awaiting_owner`.
- PR #238 was merged on 2026-08-05 and its description records the Owner's Gate-3 approval ("OWNER GATE 3: APPROVED",
  approved exact head `b11c8c3a87e4f50d6a4c9e82b52368a26e667b37`, decision given in a ChatGPT project conversation).
- The register already marks GAP-031 RESOLVED. GAP-031 is on the legacy allowlist (`legacy-work-ids.txt`), and the
  same packet is the worked example referenced by `docs/owner-governance/examples/GAP-031-owner-release-packet.md`.
  Its `technical_evidence` holds a placeholder subject SHA and the SHA-256 of the empty string as digest.

## 4. Roadmap and backlog

- `docs/roadmap/canonical-roadmap.md` — "Last updated: 2026-03-19"; nothing since (Treasury, notifications, governance,
  CI work) is reflected, and its sequencing sections describe March priorities.
- `docs/roadmap/backlog.yaml` — 29 items, all `done`; it carries no remaining work.

## 5. GitHub items

- Issue #244 (Project Treasury): every slice S1–S6 is released (GAP-063 … GAP-097); still open.
- PR #245 (draft, Treasury spec, 2026-08-06): the spec it adds was used as reference; never merged.
- Issue #248 and draft PR #257 (One-Page Project Manager design, 2026-08): no Gate 1 opened.
