# GAP-091 — Evidence: Owner Governance Lint fails on every Gate 3 packet commit

Base: `5aae9f70c9289fb25d5e078117b8af5dba1af096` (main after GAP-090).

## The check

`.github/workflows/owner-governance-lint.yml`, step "Evidence-freshness check", runs
`scripts/ci/check-evidence-freshness.sh`. While the Gate 3 packet has `gate_status: awaiting_owner`, the script's
"live" check requires every other check on the PR head to be green:

- it polls `gh pr checks` every 15 s, at most 20 times, so it waits up to **5 minutes**;
- it counts every check that is not `pass` or `skipping`, so a pending check and a failed check count the same;
- if the count is not 0 after 5 minutes, the job fails.

The Gate 3 packet commit changes only `docs/owner-decisions/<id>/03-release.md`. For `pull_request` events, path
filters are evaluated against the whole PR diff, so that commit re-runs the full CI. That includes `browser-tests`,
which starts after `feature-tests` and takes about 17–20 minutes. Five minutes is never enough.

## Measurement: Owner Governance Lint pull_request runs #865–#929 (2026-10-20 to 2026-10-22, 60 runs)

| Commit type | Runs | First attempt failed |
|---|---|---|
| Gate 3 packet presented or re-presented (`awaiting_owner`) | 8 (GAP-085, 086 ×2, 087, 088, 089 ×2, 090) | **8** |
| Any other commit | 52 | 4 |

- The 8 Gate 3 failures each ran about 6 minutes: 5 minutes of polling plus setup. For #928 (GAP-090) the log reads:
  `gate_status is 'awaiting_owner' but 3 other check(s) ... are not green (pending or failed) after waiting up to 300s`.
  Every one went green when the job was re-run by hand after the other checks finished.
- The 4 other failures (#879–#881 on GAP-086, #918 on GAP-089) took under 1 minute. They were genuine: the
  implementation changed after a Gate 3 approval, so the recorded digest was stale. That is the check working as
  intended.

So the live check has never caught a real problem in this window. Every first run of a Gate 3 presentation fails,
and someone has to re-run the job by hand after the rest of CI finishes.

## Consequences

- Each Gate 3 presentation shows a red check until the manual re-run.
- The red result looks the same as a genuine failure (a stale digest or a real failed check), so a reader has to
  open the log to tell them apart.
- If nobody re-runs it, the PR stays red.

## Notes for Gate 2

- Make the wait long enough for the slowest check, for example up to 45–60 minutes. Fail at once as soon as any
  other check has **failed**, rather than waiting while checks are only pending.
- Or move the live check out of the lint job into a step that runs only after the other checks finish.
- Whichever design is chosen, the check must stay fail-closed: a packet that claims `awaiting_owner` while a check
  has failed must still go red.
