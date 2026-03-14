# Zena Invariants Parity

## Purpose
Force auth/tenant/RBAC and schema-sensitive changes through SQLite and MySQL reasoning.

## Trigger conditions
- Changes touching auth, tenant isolation, RBAC, route stack, schema casts, or Zena invariants
- SQLite passes but MySQL risk remains
- A change might affect driver-specific behavior

## Minimum required input
- ./scripts/ci/zena-invariants
- ./scripts/ci/zena-invariants-mysql
- Relevant Zena/security tests
- Changed files

## Operating procedure
1. Assess whether the change is invariant-affecting.
2. Require SQLite invariant verification first.
3. Require MySQL parity verification if auth, route, schema, cast, or tenant behavior may diverge across drivers.
4. Block merge-readiness until parity reasoning is complete.

## Output contract
- Risk Class
- SQLite Result
- MySQL Parity Requirement
- Blocking Concern
- Merge Readiness

## Guardrails
- Do not skip MySQL parity on invariant-affecting changes.
- Do not use SQLite success as proof of total correctness.
- Do not relax policy/RBAC logic just to satisfy the invariant scripts.

## Repo-specific notes
- This skill exists because the repo explicitly values MySQL parity for Zena/security-sensitive work.

## Codex vs Terminal split
- **Codex**: read files, synthesize evidence, map likely fix classes, draft safe plans, reason about diffs.
- **Terminal**: prove runtime truth, run commands, inspect GitHub state, verify routes, run targeted tests, confirm merge/readiness.

## Refusal / pause conditions
- Stop and mark the item as `UNKNOWN` if a runtime claim cannot be proven from the available evidence.
- Pause before any code change if the problem can still be narrowed by a Terminal proof step.
- Refuse any request to hide debt by baseline changes without evidence.

## Example output shape
- Risk Class
- SQLite Result
- MySQL Parity Requirement
- Blocking Concern
- Merge Readiness
