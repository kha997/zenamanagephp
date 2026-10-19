# GAP-083 — Evidence: security scans never fail CI

Base: `16dcad79aa731db8f18091fe16d239c22dde5e0c` (main after GAP-082).
Sources: the workflow files on that base, the PR #355 CI run (head
`d408e7f0`, all 34 checks green), and local `composer audit` / `npm audit`
on the base lock files.

## Where findings are reported but cannot fail a check

| Workflow → job | Step | Why it never fails |
|---|---|---|
| `code-quality-security.yml` → Security Vulnerability Scan | `composer audit --format=json > composer-audit.json \|\| true` | `\|\| true` |
| `code-quality-security.yml` → Security Vulnerability Scan | PHP Security Checker (`enlightn/security-checker`) | `continue-on-error: true` |
| `code-quality-security.yml` → Security Vulnerability Scan | PHPStan Security Rules | `continue-on-error: true`; it also always errors: `composer require --dev phpstan/phpstan-security-rules` → "Could not find a matching version of package phpstan/phpstan-security-rules" (PR #355 log), so it has never produced a report |
| `code-quality-security.yml` → Docker Security Scan | Trivy (SARIF and JSON) | `aquasecurity/trivy-action` default `exit-code: 0` |
| `automated-testing.yml` → security-tests | `composer audit \|\| true` | `\|\| true` |
| `release-management.yml` → security-scan-release | Trivy | default `exit-code: 0` |

Findings only reach a PR comment (GAP-075: posted only when non-empty) and
the Security tab (SARIF).

## Current findings on the base

- `composer audit`: `{"advisories": [], "abandoned": []}`, exit 0 (GAP-070
  cleared the last advisories).
- Trivy on the built image: PR #355 got no `zena-ci:docker` comment, which
  is posted only when the JSON report lists at least one vulnerability
  (GAP-071 cleared the image).
- `npm audit` (not run by any workflow): 17 findings — 12 high, 4
  moderate, 1 low, 0 critical. Several fixes need major upgrades
  (`vite` 8, `tailwindcss` 4, `laravel-vite-plugin` 3); others
  (`axios`, `postcss`, transitive packages) have in-range fixes.

## Consequence

A dependency or base-image change that introduces a known vulnerability
merges with every check green; the only signal is a bot comment.
