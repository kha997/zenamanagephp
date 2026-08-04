# Branch Protection Activation Runbook (Task 8b — NOT executed by this plan)

This runbook is the exact procedure for activating `required_pull_request_reviews.require_code_owner_reviews` on `main`, once — and only once — every precondition below is independently verified true. **This plan (Tasks 1–10) does not execute this runbook.** It is a separately authorized operation for whoever administers this repository once the identity topology changes.

## Activation preconditions (ALL must be true)

1. **At least one distinct trusted GitHub user or team exists**, other than the identity currently used by the coding workflow.
2. **That identity has repository review access** (collaborator with `pull` permission at minimum — GitHub allows reviewing with read access).
3. **The identity is covered by `.github/CODEOWNERS`** for `/docs/owner-decisions/`, `/docs/owner-governance/`, `/PROJECT_CONSTITUTION.md`.
4. **A test Draft PR proves the author cannot satisfy the required review themselves** — see "Precondition test" below.
5. **An independent reviewer can approve and unblock the PR** — demonstrated by the same test.
6. **Rollback instructions are documented and understood** — see "Rollback" below, before activating.

## Precondition verification command

```bash
bash scripts/ci/verify-distinct-reviewer-identity.sh
```

Must print `✅ Task 8b activation precondition (distinct, CODEOWNERS-covered reviewer identity) is satisfied.` and exit `0` before proceeding to activation. If it exits `1`, **do not activate** — the current identity topology (verified facts #7/#8) still applies.

## Precondition test (run once the verification command passes, before activating)

1. Open a Draft PR touching a file under `/docs/owner-decisions/` from the current coding-workflow identity.
2. Attempt to approve it using that same identity. Confirm GitHub either disallows this (cannot approve your own PR) or that it is procedurally never done.
3. Have the distinct reviewer identity (precondition 1) approve the PR.
4. Confirm the PR becomes mergeable only after that distinct approval, not before.

## Activation command (run only after all preconditions pass)

```bash
gh api --method PUT repos/kha997/zenamanagephp/branches/main/protection \
  -F required_status_checks[strict]=true \
  -F 'required_status_checks[contexts][]=test-routes-guardrails' \
  -F required_pull_request_reviews[require_code_owner_reviews]=true \
  -F required_pull_request_reviews[required_approving_review_count]=1 \
  -F enforce_admins=true \
  -F required_linear_history=false \
  -F allow_force_pushes=false \
  -F allow_deletions=false \
  -F block_creations=false \
  -F required_conversation_resolution=false
```

This re-states every currently-configured branch-protection field explicitly (this endpoint replaces the whole protection object, it does not patch), copied field-for-field from the JSON captured in verified fact #6, with only `required_pull_request_reviews` added. **Before running: re-fetch current branch protection with `gh api repos/kha997/zenamanagephp/branches/main/protection` and confirm the fields above still match — if branch protection changed since this runbook was written, rebuild this command from the fresh `GET`, do not run it stale.**

## Verification command (run immediately after activation)

```bash
gh api repos/kha997/zenamanagephp/branches/main/protection --jq '.required_pull_request_reviews.require_code_owner_reviews'
```

Expected output: `true`.

## Rollback

```bash
gh api --method PUT repos/kha997/zenamanagephp/branches/main/protection \
  -F required_status_checks[strict]=true \
  -F 'required_status_checks[contexts][]=test-routes-guardrails' \
  -F enforce_admins=true \
  -F required_linear_history=false \
  -F allow_force_pushes=false \
  -F allow_deletions=false \
  -F block_creations=false \
  -F required_conversation_resolution=false
```

Omitting `required_pull_request_reviews` entirely from the PUT body removes the requirement, restoring the exact pre-activation state captured in verified fact #6. This does not delete `.github/CODEOWNERS` or any decision record — it only reverts the branch-protection requirement.
