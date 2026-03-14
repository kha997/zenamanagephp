# ZenaManage AI Skills Pack v1

This directory is the **repo-side source of truth** for the ZenaManage skill pack.

The goal is simple: stop paying the same cognitive tax every round.
These skills encode the repo's operating laws, verify order, CI triage logic, SSOT guardrails,
and handoff discipline so assistants can work faster **without** getting reckless.

## What this is

- A versioned, reviewable skill pack that lives in the repo.
- A bridge between repo knowledge and product-installed skills.
- A way to keep prompts, guardrails, and recurring workflows auditable.

## What this is not

- Not a substitute for runtime proof.
- Not permission to change domain logic just to satisfy tests or CI.
- Not a blanket instruction to update baselines whenever they are inconvenient.

## Repo laws that apply to every skill

1. Do not guess. Unknown stays UNKNOWN until proven.
2. Route and middleware claims require `php artisan route:list` evidence.
3. Do not change domain logic just to make tests pass.
4. Prefer narrow debug lanes before the full matrix.
5. Baseline files are first-class SSOT inputs, not a dumping ground.
6. Separate Codex work from Terminal work.
7. Handoffs must preserve facts, evidence, and locked decisions.

## Suggested layout

- `skills-index.yaml` — machine-readable index of the pack
- `<skill-name>/SKILL.md` — each skill in a single durable file
- `INSTALL-TO-CHATGPT-CODEX.md` — practical notes for taking repo-side skills into product runtime
- `APPLY-TO-REPO.md` — how to copy this pack into the live repo

## Priority order for rollout

1. `repo-bootstrap-ssot`
2. `ci-triage-lane-selector`
3. `ssot-lint-triage`
4. `safe-baseline-change`
5. `repo-handoff-evidence-pack`
6. `route-surface-guard`
7. `zena-invariants-parity`
8. `smoke-handoff-pack`

## Facts to memorize for this repo

- `docs/roadmap/canonical-roadmap.md` is the execution SSOT.
- `docs/roadmap/backlog.yaml` is the governance/backlog SSOT.
- Default verify order is `php artisan optimize:clear -> composer ssot:lint -> composer test:fast` unless a narrower skill overrides it.
- `composer ssot:lint` is a composite gate, not a single lint step.
- `api/zena` is strict; public exceptions are tiny and explicit.
- `api/v1` still carries baseline-tracked debt and should not be cleaned blindly.

## Current pack status

This v1 pack is strong enough to commit into the repo immediately.
A few runtime facts should still be verified with Terminal before treating every workflow statement as final product truth:

- which checks are currently required by branch protection
- whether `ci-cd.yml` is still enforced or mostly adjacent/legacy
- current run hotspots from GitHub Actions
- live skip/orphan inventories

See `UNKNOWNS-TO-VERIFY.md` for that follow-up list.
