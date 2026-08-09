# Adoption Runbook — How a New Work Item Moves Through the Owner Control Layer

Worked walkthrough for the next new work item (`OWN-2026-001`, hypothetical) moving through Gate 1 → Gate 2 → implementation → Gate 3.

## 1. Gate 1

1. Agent identifies an operational problem, assigns `work_id: OWN-2026-001` (owner-raised — see `OWNER_OPERATING_MODEL.md` on ID prefixes).
2. Agent copies `docs/owner-governance/templates/gate-1-business-request.md` to `docs/owner-decisions/OWN-2026-001/01-request.md`, fills in every field, sets `gate_status: preparing`.
3. Agent runs `php scripts/ssot/owner_governance_lint.php docs/owner-decisions/OWN-2026-001/01-request.md` — must PASS before presenting to the owner.
4. Agent presents the packet in the working conversation (no notification infrastructure in this phase — `OWNER_OPERATING_MODEL.md` §"Owner Decision Packets").
5. Owner responds. Agent updates the same file: `gate_status: awaiting_owner` → owner decides → agent records `owner_decision.value: approved`, `gate_status: approved`, and a real `decision_provenance` block (`trust_level: claimed_repo_record`, `recorded_by`, `recorded_at`, `owner_response_reference` pointing at this conversation).
6. Re-run the lint. Must PASS.

## 2. Gate 2

Same mechanics with `docs/owner-governance/templates/gate-2-business-design.md` → `02-design.md`. **Do not create any file under `docs/superpowers/plans/` until this file's `owner_decision.value` is `approved`** — `owner_governance_lint.php --enforce-gate-ordering` will fail the PR otherwise (Task 9), for any work_id not on the legacy-exempt list.

**Correction 3 — the spec/plan file itself must declare frontmatter, not just exist:** when the agent creates `docs/superpowers/plans/2026-MM-DD-own-2026-001-<slug>.md`, it must open with:

```yaml
---
work_id: OWN-2026-001
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/OWN-2026-001/02-design.md
---
```

Filename text alone (even if it happens to contain `OWN-2026-001`) is never sufficient for a new work item — see `docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md`. Omitting this frontmatter fails `--enforce-gate-ordering` with `missing-governance-frontmatter`, distinct from (and checked before) the `gate-2-before-plan`/`gate-2-not-approved` rules.

## 3. Implementation (Gate 2 approved → Gate 3 awaiting_owner)

1. Agent creates the Gate 3 packet immediately, `gate_status: preparing` — do not wait until the end. Include a `technical_evidence` block from the start (`subject_sha` of the branch's current HEAD, `implementation_tree_digest: "not_computed_while_blocked"` or `"not_computed_while_preparing"`, `verified_pr_head_sha: null`, `verified_at: null`), and an `owner_decision_binding` block with both fields `null`.
2. Implementation, tests, review, CI proceed per the normal `superpowers:writing-plans` → `superpowers:subagent-driven-development` flow — nothing about this changes. **This includes committing packet-only updates to the Gate 3 record itself as work progresses** — per Corrections 2 and OWN-2026-006, a target work item's active Gate 3 packet and every recognized Gate 3 packet for other work IDs are excluded from its `implementation_tree_digest`; the target's superseded packets remain included. This prevents release-decision metadata for parallel work items from invalidating one another without reducing sensitivity to implementation, Gate 1/Gate 2, schema, script, or CI changes.
3. If a mandatory technical gate is red at any point, set `gate_status: blocked_technical`, `technical_readiness.value: blocked`, fill in `mandatory_technical_gate_summary` in plain language. This packet is visible to the owner (labeled `BLOCKED — OWNER ACTION NOT REQUIRED`) but requests nothing.
4. Once every mandatory gate is green: create a **new** file (`03-release-v2.md` if a blocked one preceded it, else `03-release.md` directly), `gate_status: awaiting_owner`, `technical_readiness.value: ready`, `decision_requested` set, `supersedes` pointing at the blocked file if one exists, and a real `technical_evidence` block: `subject_sha` = the branch's actual current HEAD, `implementation_tree_digest` = `owner_governance_compute_implementation_tree_digest($sha, $workId, $repoRoot)` (a git-tree hash — no `gh` call needed for this half), `verified_pr_head_sha` = the PR head whose live CI was actually inspected via `gh pr checks`, `verified_at` = now.
5. `bash scripts/ci/check-gate3-before-ready.sh` (with `PR_NUMBER` set) confirms the packet exists before the PR may be marked Ready for review, for non-exempt work IDs. `bash scripts/ci/check-evidence-freshness.sh` (with `PR_NUMBER`/`WORK_ID` set) independently confirms both that the recomputed digest matches and that every check on the current PR head is actually green — a byte-identical tree with pending/red CI must not reach `awaiting_owner`.

## 4. Gate 3 decision and release

1. Agent presents the `awaiting_owner` packet in conversation.
2. Owner decides. Agent records `owner_decision.value`, `gate_status`, and provenance, exactly as Gate 1/2 — **and, per Correction 2, also copies `technical_evidence.implementation_tree_digest` into `owner_decision_binding.implementation_tree_digest`, and sets `owner_decision_binding.decision_recorded_at`, in the same edit.** An `owner_decision.value` set without a matching `owner_decision_binding` fails `owner_governance_lint.php`'s `evidence-binding-required-once-decided` rule.
3. If `approved`: PR may be merged once repository requirements (required CI; CODEOWNERS review only once Task 8b is activated — see below) are *also* independently satisfied — the lint does not merge anything itself. Release eligibility requires, all at once: current `implementation_tree_digest` = `technical_evidence.implementation_tree_digest` = `owner_decision_binding.implementation_tree_digest`; every mandatory CI check on the current PR head green; `gate_status: approved`; `technical_readiness.value: ready`; `owner_decision.value: approved`.
4. If `correction_requested` or `deferred`: `gate_status` moves to `changes_requested`/`deferred`, work returns to `preparing`.
5. **If any commit lands on the branch after step 2 that touches anything other than the Gate 3 record itself** (a hotfix, a real rebase that changes implementation content, a fix to a governance script or CI workflow), `scripts/ci/check-evidence-freshness.sh` will detect the mismatch between the new `technical_evidence.implementation_tree_digest` and the still-old `owner_decision_binding.implementation_tree_digest` on the next CI run, and fail with a clear message. **The correct response is a new packet revision** (`03-release-v3.md`, `supersedes` the stale one, `gate_status: preparing` or `blocked_technical` as appropriate) that goes back through step 3/4 above — never manually editing `owner_decision_binding` to "fix" the mismatch without an actual fresh owner decision. Note: a commit that touches ONLY the Gate 3 record itself (e.g. fixing a typo in the narrative) does NOT trigger this — the digest structurally excludes that one file.

## Branch-protection activation (Task 8b) — separate from this runbook's Gate 1–3 flow

This plan does **not** activate `required_pull_request_reviews.require_code_owner_reviews`. If and when a distinct trusted reviewer identity becomes available, follow `docs/owner-governance/BRANCH_PROTECTION_ACTIVATION_RUNBOOK.md` in full — including its precondition-verification command (`scripts/ci/verify-distinct-reviewer-identity.sh`) and its precondition test (a Draft PR proving the author cannot self-approve). Until then, governance-path PRs merge under the same rules as any other PR in this repository today (one required status check, no required review) — `.github/CODEOWNERS` documents responsibility, it does not yet gate merges.

## Rollback of governance enforcement (without deleting decision records)

If `owner-governance-lint` CI integration causes unintended blockage (a false positive blocking an unrelated PR, a schema bug):

1. **This workflow was never added to required status checks by this plan** (Task 9's note; Task 8b governs any future required-check addition, same precondition-gated process as CODEOWNERS-review activation) — so the immediate mitigation for a false positive is simply: the failing check does not block merge today. No branch-protection change is needed to "unblock" anything.
2. **If the workflow itself needs to stop running**, add `docs/owner-governance/**`, `docs/owner-decisions/**`, `docs/superpowers/plans/**` to a temporary `paths-ignore` in `.github/workflows/owner-governance-lint.yml`, or set the job to `if: false` — a normal, revertible file edit.
3. **Never delete `docs/owner-decisions/**` content to "fix" a blocked PR.** Decision records are historical evidence (design §6.5, immutable-by-convention) — a bad lint rule is a lint bug, not a reason to erase a real recorded decision. Fix the lint (`scripts/ssot/owner_governance_lint.php`) or its schema (`packet-schema.yml`), add a regression fixture, and re-enable.
4. **Never manually edit `owner_decision_binding` to silence a `stale-decision-digest-mismatch` failure without a real, freshly recorded owner decision.** That is the exact failure mode Correction 2 exists to prevent — treat it the same as deleting a decision record.

## Verification record (Task 10, run 2026-08-05)

Every command below was run for real in this worktree; exact output is recorded in `.superpowers/sdd/task-10-report.md`. Summary:

| # | Check | Command | Result |
|---|---|---|---|
| 1a | Fixture suite | `./vendor/bin/phpunit --filter OwnerGovernanceLintFixtureTest` | PASS (14/14) |
| 1b | Real packet scan | `php scripts/ssot/owner_governance_lint.php` | PASS (4 files, 0 violations) |
| 2 | Invalid transition | `php scripts/ssot/owner_governance_lint.php tests/.../invalid-status-decision-contradiction.md` | PASS — exit 1, `status-decision-contradiction` reported as expected |
| 3 | Blocked Gate 3 visibility | `php scripts/ssot/owner_governance_lint.php docs/owner-decisions/GAP-031/03-release.md` | PASS — `decision_requested: null`, `owner_decision.value: none` confirmed manually |
| 4 | Stale decision (structural) | `php scripts/ssot/owner_governance_lint.php tests/.../invalid-stale-decision-digest-mismatch.md` | PASS — exit 1, `stale-decision-digest-mismatch` reported. **Live half** (`check-evidence-freshness.sh` against a real regressing PR) is genuinely untested end-to-end — recorded as Implementation Risk #6, not assumed proven. |
| 5 | Schema/readiness separation | `./vendor/bin/phpunit --filter OwnerGovernanceSchemaFixtureTest` | PASS (6/6) |
| 6 | Work-ID compatibility | `./vendor/bin/phpunit --filter EnforcementBoundaryTest` | PASS (8/8) |
| 7 | PR-template behavior | `./vendor/bin/phpunit --filter PrTemplateTest` + `head -n1 .github/PULL_REQUEST_TEMPLATE.md` | PASS (3/3); first line is `## Owner Summary (read this first — no code required)` |
| 8 | Agent stop-report format | `./vendor/bin/phpunit --filter ConstitutionAmendmentTest` | PASS (5/5) |
| 9 | CI dry run | `php scripts/ssot/owner_governance_lint.php` + `--enforce-gate-ordering` | PASS both (exit 0) |
| 10 | No prod-code/DB-schema changes | `git diff --stat HEAD~9..HEAD -- app/ database/migrations/` | PASS — empty output |
| 11 | Owner readability (manual) | Re-read `docs/owner-decisions/GAP-031/03-release-v2.md` body | PASS — no class/method name, SQL, HTTP status, or CI job name present; decision is answerable from the body alone |
| — | `composer ssot:lint` | `composer ssot:lint` | **Known environment limitation, not a regression from this plan.** Fails with `Invalid route map JSON: .../storage/app/ssot/routes.json` inside `find_orphan_test_routes.php`, a pre-existing worktree/Laravel-bootstrap issue confirmed independently by Task 9's implementer and reproduced again here. Unrelated to any file this plan touches (`app/`, `database/migrations/` diff is empty per check #10 above). |
| — | Step 3 full suite | `./vendor/bin/phpunit --filter "OwnerGovernance"` | PASS — 54/54 tests, matching the expected per-class breakdown exactly |

**Implementation Risk #6 acknowledgment:** the live half of stale-decision detection (`scripts/ci/check-evidence-freshness.sh` recomputing a digest against a real, currently-regressing PR) has never been exercised end-to-end by this repository, because no real non-legacy (`OWN-*`/`ZMC-*`/`WP-*`) work item has reached Gate 3 yet — GAP-031 is legacy-exempt. This is not silently glossed over: it is the same risk the plan itself names, and this runbook's §3–4 above is the first full worked walkthrough of the pipeline that risk describes, on paper, for the next real work item to follow. The first genuine end-to-end proof happens only when a real new work item actually reaches Gate 3 under this system.

**`--enforce-gate-ordering` known, accepted limitation (from Task 9's review, Important-but-accepted):** bulk/CI-scan mode still silently skips a new plan/spec file whose filename has no ID token *and* has no frontmatter — this repository's own real naming convention (e.g. lowercase `gap031`-style slugs) can hit this. This is not a bug introduced or left unaddressed by this task; it is the pre-documented plan-level limitation already named as **Implementation Risk #5** in the plan document (`docs/superpowers/plans/2026-08-04-owner-control-layer-repo-governance-foundation.md`, "Implementation Risks" §5), and remains explicitly accepted rather than solved by this final task.
