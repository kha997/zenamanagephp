---
work_id: OWN-2026-012
recorded_at: "2026-09-13T20:08:26+07:00"
canonical_main: "60a37b8a7b6f61fa607d36ad8ec9616f84638899"
record_type: gate_1_read_only_evidence
authorization_state: awaiting_owner_gate_1
---

# OWN-2026-012 — Gate-1 backlog-governance reconnaissance

## 1. Status and reading rules

This document is read-only Gate-1 evidence. It records repository and GitHub
facts observed on 2026-09-13 and recommendations for a possible later design.
It is not an execution record, implementation plan, Owner approval, or
authorization to mutate the operational register or GitHub backlog.

The three evidence classes used below are deliberately separate:

- **VERIFIED CURRENT FACT** — directly supported by current repository state,
  merge history, Owner records, or current GitHub state.
- **RECOMMENDED ADMINISTRATIVE ACTION** — a proposal that may be designed only
  if Owner approves Gate 1, and may not be executed before later gates permit.
- **NOT YET AUTHORIZED** — no agent may infer approval or perform the action
  from this audit.

The earlier premature reconciliation commit was normally reverted by
`be4701b6`; the operational register is byte-equivalent to canonical base.
PRs #264, #283, #285, and #297 were reopened solely to restore the pre-Gate
state. Those restorative actions do not decide their eventual disposition.

## 2. Baseline and Work ID evidence — VERIFIED CURRENT FACTS

- `origin/main` at reconnaissance was exactly
  `60a37b8a7b6f61fa607d36ad8ec9616f84638899`, matching the expected SHA.
- The branch was created in a fresh linked worktree directly from that SHA.
- Before reservation, `OWN-2026-012` was absent from the `origin/main` tree,
  practical reachable-history search (`git log --all -S`), local/remote refs,
  all-state GitHub Issues, and all-state GitHub PRs. The candidate branch was
  also absent locally and remotely.
- No feature implementation or production deployment is part of this work.

## 3. Open GitHub Issues — complete inventory and verified facts

GitHub currently returns exactly two open Issues. Both remain open.

| Issue | Classification | Verified current fact | Recommended action |
|---|---|---|---|
| [#244 — Project Treasury](https://github.com/kha997/zenamanagephp/issues/244) | `ACTIVE_PRODUCT_WORK` | GAP-037 v17 and GAP-038/#265 provide additive Treasury schema, models, and native CHECK foundations. Current main has no Treasury-specific controller, route, service, policy, dashboard, approval queue, posting workflow, or reconciliation runtime; the product acceptance scope is incomplete. | Preserve as active product work. In a separate future lifecycle, select the smallest usable runtime vertical slice. Treat PR #245 only as non-authoritative source design. |
| [#248 — OPPM](https://github.com/kha997/zenamanagephp/issues/248) | `ACTIVE_PRODUCT_WORK` | No OPPM route, controller, service, view, or test exists on current main. OWN-2026-009 and later gaps provide foundations, not the requested OPPM runtime. | Preserve as active product work. Canonicalize the still-valid PR #257 material in a separate lifecycle before implementation. |

Closing either Issue is **NOT YET AUTHORIZED** and is explicitly outside
OWN-2026-012.

## 4. Open GitHub PRs — complete inventory and verified facts

GitHub currently returns nine open PRs. PR #315 is the Draft envelope for this
Gate-1 submission and is not itself a backlog candidate. The other eight are
classified exactly once below.

| PR | Classification | Verified current fact | Recommended administrative action — pending later authorization |
|---|---|---|---|
| [#315](https://github.com/kha997/zenamanagephp/pull/315) | Current Gate submission | Draft/Open OWN-2026-012 review surface. | Keep Draft/Open; do not merge while Gate 1 awaits Owner. |
| [#245](https://github.com/kha997/zenamanagephp/pull/245) | `DESIGN_REFERENCE_PENDING_CANONICALIZATION` | Its Treasury design predates OWN-2026-009 and GAP-037 v17 but retains unique runtime/UI/workflow intent. OWN-2026-009 explicitly preserves it as non-authoritative source design. | Leave open pending a future #244 canonicalization decision; do not merge as-is. |
| [#257](https://github.com/kha997/zenamanagephp/pull/257) | `DESIGN_REFERENCE_PENDING_CANONICALIZATION` | Shared Service-Line and Finance/Contract semantics are partly superseded; detailed Project OPPM material remains useful but predates current foundations. | Leave open pending extraction of the still-compatible Project OPPM subset; do not merge as-is. |
| [#264](https://github.com/kha997/zenamanagephp/pull/264) | `HISTORICAL_OR_SUPERSEDED_PR` | Historical GAP-038 Gate-1/Gate-2 predecessor; canonical implementation/release PR #265 merged at `cb5cb893d92b8ef0534672d7f5c7bfe35062eb64`. It is currently open after restoration. | Gate 2 should decide whether the evidence is conclusively superseded and whether closure would lose any unique required content. Proposed disposition: close with provenance, only after authorization. |
| [#276](https://github.com/kha997/zenamanagephp/pull/276) | `HISTORICAL_OR_SUPERSEDED_PR` | Its approved GAP-041 Gate-1 packet is unique and still needed because GAP-041 remains live. | Preserve open as historical/reference until a fresh GAP-041 lifecycle safely carries forward its provenance. |
| [#277](https://github.com/kha997/zenamanagephp/pull/277) | `DESIGN_REFERENCE_PENDING_CANONICALIZATION` | Owner-approved Option D remains relevant, but the branch is stale and implementation never occurred. | Preserve open. A fresh GAP-041 session should revalidate the design against current main; do not directly merge it. |
| [#283](https://github.com/kha997/zenamanagephp/pull/283) | `HISTORICAL_OR_SUPERSEDED_PR` | Both payload blobs are byte-identical to current-main copies released through #286 at `c3a1226059bcf5a573aad1eebf8f1333331d9ad2`. It is currently open after restoration. | Proposed disposition: close with provenance after the exact closure policy is designed and authorized. |
| [#285](https://github.com/kha997/zenamanagephp/pull/285) | `HISTORICAL_OR_SUPERSEDED_PR` | All four payload blobs are byte-identical to current-main copies released through #286. It is currently open after restoration. | Proposed disposition: close with provenance after the exact closure policy is designed and authorized. |
| [#297](https://github.com/kha997/zenamanagephp/pull/297) | `HISTORICAL_OR_SUPERSEDED_PR` | Historical GAP-042 Gate-1 predecessor; Gate 2 merged through #298 and implementation/release through #299 at `0872ac856932193a037ce30f00050179374811af`. It is currently open after restoration. | Proposed disposition: close with provenance after the exact closure policy is designed and authorized. |

No closure, merge, branch deletion, or content mutation listed as a
recommendation in this table is currently authorized.

## 5. Register and release reconciliation evidence — VERIFIED CURRENT FACTS

| Gap | Classification | Verified evidence | Register state at canonical base |
|---|---|---|---|
| GAP-040 | `RESOLVED_BUT_REGISTER_STALE` | Gate 3 is approved/ready; PR #272 merged at `aab48a23709534f5111db4580121aec28e66583d` on 2026-08-20. | Still says OPEN. |
| GAP-042 | `RESOLVED_BUT_REGISTER_STALE` | Gate 3 is approved/ready; PR #299 merged at `0872ac856932193a037ce30f00050179374811af` on 2026-09-02. | Still says OPEN/unverified. |
| GAP-044 | `RESOLVED_BUT_REGISTER_STALE` | Gate 3 is approved/ready; PR #286 merged at `c3a1226059bcf5a573aad1eebf8f1333331d9ad2` on 2026-08-25. | Still says OPEN/root-cause-unverified. |

**RECOMMENDED ADMINISTRATIVE ACTION:** if Gate 1 is approved, Gate 2 should
define exact register wording and evidence citations for terminalizing these
three already-released rows without mutating historical Gate evidence.

**NOT YET AUTHORIZED:** no register row is terminalized by this Gate-1 branch.

## 6. Current truth for non-terminal work — VERIFIED CURRENT FACTS

- GAP-041 remains live: the performance file matrix still runs without
  `--group performance`, and nightly jobs still select nonexistent
  `performance_budget` / `performance_heavy` groups. PR #277's approved Option
  D was never implemented. OWN-2026-012 does not fix it.
- GAP-045 remains separate. The CI threshold remains 450ms, the historical
  502.08ms observation is not a verified root cause, and controlled
  reproduction depends on first making GAP-041 truthful.
- GAP-012 remains deferred and `apply` notifications are absent; GAP-013 lacks
  full submit/review/approve/reject fan-out; GAP-014b listeners are not reached
  by canonical event dispatch; GAP-014c lacks a durable NCR-to-CAPA-task link.
- GAP-015 has bounded Project template-apply UI, but lacks confirmed ownership
  of complete WorkTemplate/WorkInstance operational authoring/management UI.
- GAP-016/GAP-017 remain missing-view dead ends. GAP-018/019/020 retain their
  orphan/demo concerns. GAP-021 remains Task API ownership/contract debt.
- GAP-030 still uses `rbac:rfi.escalate` for resolve-escalation and awaits the
  Owner's actor-role capability decision.
- GAP-026 remains externally blocked because repository state cannot identify
  the destination of the generic production `SLACK_WEBHOOK_URL`.

Fixing, reproducing, optimizing, or changing any of these items is **NOT YET
AUTHORIZED** by OWN-2026-012.

## 7. Proposed evidence-backed execution queue — RECOMMENDATION ONLY

This is backlog-ordering evidence, not an implementation plan. Each future
item requires its own fresh session and applicable Owner-Gate lifecycle.

| Priority | Item | Why it remains | Next action | Agent/model | DDP |
|---:|---|---|---|---|---|
| 1 | GAP-041 | CI selectors may report green with zero selected tests. | Fresh lifecycle; revalidate Option D against current main. | Codex / GPT-5, high | No unless business semantics expand. |
| 2 | GAP-045 | Performance observation remains uncontrolled/unverified. | Controlled reproduction only after GAP-041. | Codex / GPT-5, high | Conditional. |
| 3 | GAP-017 | Invitation-expired path remains a user-facing dead end. | Fresh lifecycle for smallest truthful expired state. | Codex / GPT-5, medium | No. |
| 4 | Issue #244 | Treasury foundations exist; usable runtime remains incomplete. | Discover next smallest usable vertical slice. | Codex / GPT-5, high | Yes. |
| 5 | Issue #248 | OPPM runtime remains absent. | Fresh discovery and PR #257 canonicalization. | Codex / GPT-5, high | Yes. |
| 6–9 | GAP-012, GAP-013, GAP-014b, GAP-014c | Notification/CAPA workflow gaps remain. | Separate evidence and lifecycle per gap. | Codex / GPT-5, medium/high | Yes. |
| 10 | GAP-030 | Business capability owner is undecided. | Obtain Owner actor-role decision. | Codex / GPT-5, high | Yes. |
| 11–17 | GAP-015, GAP-016, GAP-011, GAP-020, GAP-021, GAP-018, GAP-019 | Strategic/deferred UI and lower-priority cleanup/debt remain. | Separate bounded lifecycle, preserving current semantics. | Codex / GPT-5, low–high by item | As recorded in future preflight. |
| 18 | GAP-026 | Slack destination is unknowable from repository state. | External Slack administrator supplies routing fact first. | Human Slack admin, then Codex | No. |

## 8. Historical immutability and actions not yet authorized

The historical Gate-3 packet SHA-256 values observed at canonical base are:

- GAP-040: `7f1d96f2ac55bcfdbaba55510c8607d05759c7c76265b3000a3a5ff3c3e7908c`;
- GAP-042: `df11af1f2e77ae36cd50110fd8296a765be0a5d13dc31086a9f08556ede607f3`;
- GAP-044: `c975839682026262ce013460d3c296e3cf1f8dd06578e0f417a527e1d4679564`.

OWN-2026-012 does not authorize any of the following at Gate 1:

- editing `OPERATIONAL_GAP_REGISTER.md`;
- closing or merging any PR;
- closing Issues #244 or #248;
- creating Gate 2, Gate 3, or an implementation plan;
- rewriting, reopening, recomputing, or rebinding historical Owner decisions,
  implementation subjects, or implementation-tree digests;
- modifying feature code, tests, workflows, CI, schema, runtime, or deployment;
- manufacturing Owner provenance or inferring approval from technical facts.

The only decision requested now is whether this verified governance problem is
real and worth advancing to a bounded Gate-2 administrative design.
