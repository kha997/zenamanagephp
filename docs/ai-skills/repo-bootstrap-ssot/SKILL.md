# Repo Bootstrap SSOT

## Purpose
Load the repo operating model fast and safely at the start of a round.

## Trigger conditions
- New chat or cold start into the repo
- A handoff pack was provided
- Before touching CI, tests, baselines, routes, or workflows
- When the assistant is at risk of acting without the repo’s SSOT loaded

## Minimum required input
- docs/roadmap/canonical-roadmap.md
- docs/roadmap/backlog.yaml
- docs/agent-ssot-rules.md
- docs/engineering/testing-matrix.md
- composer.json
- Latest handoff pack, if available

## Operating procedure
1. Identify the execution SSOT, backlog/governance SSOT, and verify-order SSOT.
2. Summarize the practical branch/PR/merge model in use.
3. State the default verify order for this repo.
4. Separate Codex work from Terminal work before proposing any action.
5. List the repo invariants that must not be broken this round.
6. List anything still UNKNOWN and require Terminal proof for runtime truth.

## Output contract
- Context Snapshot
- SSOT Invariants
- Default Verify Order
- Codex vs Terminal Split
- Unknowns needing Terminal

## Guardrails
- Do not infer branch protection or required checks from YAML alone.
- Do not treat docs as runtime truth when Terminal can verify it.
- Do not jump into code edits from this skill.

## Repo-specific notes
- This skill exists to stop repeated “reloading the repo into memory” overhead.
- Default verify order for this repo is optimize:clear -> ssot:lint -> test:fast unless a more specific skill overrides it.

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
- Default Verify Order
- Codex vs Terminal Split
- Unknowns needing Terminal
