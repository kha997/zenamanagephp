# Smoke Handoff Pack

## Purpose
Prepare minimal smoke verification for deploy-like and reachability-sensitive changes.

## Trigger conditions
- Deploy workflow or smoke script changes
- Route reachability or app boot concerns
- Need to prove the app still “breathes” after a change
- Staging or smoke sanity checks are needed

## Minimum required input
- Relevant smoke workflow(s)
- Relevant smoke scripts
- Changed surfaces
- Expected endpoints/pages/artifacts

## Operating procedure
1. Define the exact smoke scope and what success looks like.
2. Choose the smallest manual and/or CI smoke path that covers that scope.
3. List the artifacts and logs that must be kept.
4. State clearly what the smoke does not prove.

## Output contract
- Smoke Scope
- Exact Commands / Workflow
- Expected Success Signals
- Artifacts To Keep
- What This Does Not Prove

## Guardrails
- Do not turn smoke into a full regression run.
- Do not use smoke success as a substitute for invariant or security verification.
- Do not run deploy on PR if governance forbids it.

## Repo-specific notes
- Use this to package deploy-like confidence without exploding scope.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Smoke Scope
- Exact Commands / Workflow
- Expected Success Signals
- Artifacts To Keep
- What This Does Not Prove
