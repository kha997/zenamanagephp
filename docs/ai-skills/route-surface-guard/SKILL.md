# Route Surface Guard

## Purpose
Safely reason about route truth, middleware truth, and stale references.

## Trigger conditions
- A route path changes
- A test references an endpoint directly
- A route guard or middleware contract test fails
- The assistant suspects an orphan route or stale reference

## Minimum required input
- php artisan route:list output for the affected area
- tests/Feature/Zena/ZenaRouteSurfaceInvariantTest.php
- tests/Feature/RouteMiddlewareSecurityContractTest.php
- tests/Unit/RouteSsotGuardTest.php
- tests/Feature/Api/ApiSecurityMiddlewareGateTest.php
- Relevant route snapshot / middleware baseline evidence

## Operating procedure
1. Get route truth first using route:list for the affected path prefix.
2. Separate route truth from test/reference truth.
3. Determine whether the path is a live endpoint, a permitted negative probe, or stale debt.
4. If the reference is stale, prefer fixing the reference rather than hiding it in a baseline.
5. If the endpoint is live but the stack is wrong, route to middleware/invariant verification.

## Output contract
- Route Truth
- Reference Truth
- Fix Recommendation
- Relevant Verification Tests
- Why Baseline Update Is / Is Not Allowed

## Guardrails
- Do not declare a route dead without route:list evidence.
- Do not blindly clean up legacy api/v1 debt.
- Do not alter api/zena public surface without invariant proof.

## Repo-specific notes
- This repo treats api/zena as a strict surface and api/v1 as debt-tracked legacy surface.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Route Truth
- Reference Truth
- Fix Recommendation
- Relevant Verification Tests
- Why Baseline Update Is / Is Not Allowed
