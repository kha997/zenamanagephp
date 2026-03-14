# CI Triage Lane Selector

## Purpose
Choose the smallest correct workflow or verify lane before escalating.

## Trigger conditions
- Any CI lane is red
- A workflow YAML changed
- A narrow lane exists and a full rerun would be expensive
- The repo has overlapping workflows and the assistant needs the right debug surface

## Minimum required input
- .github/workflows/
- docs/engineering/testing-matrix.md
- composer.json
- Changed files in the current branch/PR
- Recent workflow run history, if available

## Operating procedure
1. Map changed files to likely affected workflow lanes.
2. Classify the failure: workflow syntax/trigger, runtime/env bootstrap, route/reference drift, baseline drift, or functional regression.
3. Prefer the narrowest lane that can prove or disprove the failure class.
4. Escalate to the full matrix only when the narrow lane cannot produce enough evidence.
5. Spell out the first verify commands and the escalation rule.

## Output contract
- Chosen Lane
- Why This Lane
- First Verify Commands
- Escalation Rule
- Things Not To Do

## Guardrails
- Do not rerun the full matrix first just because it is familiar.
- Do not assume a lane is required by branch protection until GitHub confirms it.
- Do not merge based only on a narrow debug lane when required checks are still unclear.

## Repo-specific notes
- In this repo, the broad canonical lane appears to be automated-testing.yml; routes-guardrails.yml, staging-smoke.yml, and ci-cd-code-quality-debug.yml are narrower tools.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Chosen Lane
- Why This Lane
- First Verify Commands
- Escalation Rule
- Things Not To Do
