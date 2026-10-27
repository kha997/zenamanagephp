# GAP-086 — Evidence: npm audit gate is critical-only

Base: `78fec1c22bbe6ba4fd78e1b6c9fdf59a266ca488` (main after GAP-085).

## Gate today

`.github/workflows/code-quality-security.yml` → Security Vulnerability Scan → "Fail on Composer advisories or npm critical vulnerabilities".
- The step reads `.metadata.vulnerabilities.critical` from `npm audit --package-lock-only --json`.
- It fails only when that count is above 0.
- High, moderate and low findings appear only in the step summary.

GAP-083 Gate 2 chose critical-only because main had 12 high findings at the time.

## Findings on the base

`npm audit --package-lock-only`: `{"info":0,"low":0,"moderate":0,"high":0,"critical":0,"total":0}`.
GAP-084 and GAP-085 cleared every finding, so a stricter gate would not turn any current branch red.

## Exception path

`npm audit` has no ignore list of its own; Composer has `config.audit.ignore` and Trivy has `.trivyignore`.
Under a high gate, an unfixable high finding would block every PR until one of the following lands:
- an `overrides` pin in `package.json`;
- a dependency change;
- a gate exception added in the workflow.
