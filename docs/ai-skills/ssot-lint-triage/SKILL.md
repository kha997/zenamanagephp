# SSOT Lint Triage

## Purpose
Break down composer ssot:lint by subsystem and route to the safe fix class.

## Trigger conditions
- composer ssot:lint fails
- Orphan route report appears
- Skip inventory or hardcoded path debt is suspected
- Domain ownership lint fails

## Minimum required input
- composer.json
- scripts/ci/verify-ssot-baselines.sh
- scripts/ssot/find_orphan_test_routes.php
- scripts/ssot/lint_tests.sh
- scripts/ci/lint-domain-ownership.php
- Relevant baseline files and allowlists

## Operating procedure
1. Classify the failure into one of the known SSOT subsystems.
2. Locate the exact file(s) and evidence causing the failure.
3. Compare current evidence with baselines/allowlists.
4. Recommend a safe fix class: stale reference fix, env/runtime prep fix, baseline edit with proof, or domain ownership correction.
5. Require route:list proof for any route or middleware conclusion.

## Output contract
- Failure Class
- Files To Inspect
- Evidence Needed
- Likely Safe Fix Class
- Unsafe Shortcuts To Avoid

## Guardrails
- Never suggest updating a baseline first.
- Do not convert a stale test reference into new baseline debt.
- Do not change domain logic when the failure sits in workflow, test, fixture, or reference drift.

## Repo-specific notes
- composer ssot:lint in this repo is a composite gate, not a single concern.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Failure Class
- Files To Inspect
- Evidence Needed
- Likely Safe Fix Class
- Unsafe Shortcuts To Avoid
