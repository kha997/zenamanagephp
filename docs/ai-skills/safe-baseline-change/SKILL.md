# Safe Baseline Change

## Purpose
Treat baseline and fixture edits as high-risk, evidence-gated changes.

## Trigger conditions
- Any edit to scripts/ssot/baselines/*
- Any edit to tests/Fixtures/middleware_gate_baseline.json
- Any change to allowlists or skip inventories
- A proposal to “just update the baseline” appears

## Minimum required input
- The exact baseline or fixture file
- The proposed diff
- The reason for the change
- Runtime evidence proving the debt is real or stale
- Relevant strict-mode command or guard script

## Operating procedure
1. Classify the change as adding tracked debt, removing stale debt, or hiding a new failure.
2. Demand runtime evidence before allowing the change.
3. Run strict-mode or the most specific baseline verification path.
4. Allow only the minimal exact file edits needed.
5. Spell out what remains explicitly forbidden in the same round.

## Output contract
- Allow / Deny Decision
- Reason
- Required Verification
- Exact Files Allowed To Change
- Explicitly Forbidden Moves

## Guardrails
- Default stance is deny until proven safe.
- Do not accept a baseline edit just because CI is red.
- Do not hide opportunistic cleanup inside a baseline edit.

## Repo-specific notes
- Baseline files in this repo are first-class SSOT inputs, not throwaway housekeeping.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Allow / Deny
- Reason
- Required Verification
- Exact Files Allowed To Change
- Explicitly Forbidden Moves
