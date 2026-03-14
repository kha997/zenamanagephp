# Repo Handoff Evidence Pack

## Purpose
Produce end-of-round handoffs that preserve facts, evidence, and locked decisions.

## Trigger conditions
- End of a round
- Preparing to switch threads
- Preparing PR summary or maintainer handoff
- A merge just completed

## Minimum required input
- Current branch and HEAD SHA
- Files touched
- Commands actually run
- Workflow run IDs and conclusions, if available
- Locked decisions
- Unknowns

## Operating procedure
1. Write a short context snapshot of the round.
2. List the round invariants and do-not-relitigate decisions.
3. Record branch, commits, files touched, and root-cause chain.
4. Record fixes applied and verified evidence only.
5. Separate facts from recommendations and UNKNOWNs.
6. Provide a minimal next-thread starter that prevents context loss.

## Output contract
- Context Snapshot
- SSOT Invariants
- Working Branch / Commits / Files
- Root Cause Chain
- Fixes Applied
- Verified Evidence
- Locked Decisions
- Current Status
- Risks / Follow-Up Debt
- Recommended Next Step
- Next Thread Starter

## Guardrails
- Do not write unverified claims as facts.
- Do not omit UNKNOWN items just to make the handoff look cleaner.
- Do not mix recommendations into the evidence section.

## Repo-specific notes
- This repo relies heavily on evidence-rich handoff packs to survive long-running work across threads.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Context Snapshot
- SSOT Invariants
- Working Branch / Commits / Files
- Root Cause Chain
- Verified Evidence
- Locked Decisions
- Current Status
- Recommended Next Step
