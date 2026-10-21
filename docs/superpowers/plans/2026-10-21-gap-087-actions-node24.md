---
work_id: GAP-087
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-087/02-design.md
---

# GAP-087 — GitHub Actions off Node 20: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-087/02-design.md`).

1. **Upgrade** — change only the `uses:` lines of the eight Node 20 actions in the eight workflows, to the versions
   in the design.
2. **Static checks** — actionlint shows no finding beyond the main baseline. Every `with:` key exists in the target
   `action.yml`. The nine Slack payloads parse with js-yaml 5.
3. **Proof** — a temporary `pull_request` workflow runs `docker/metadata-action@v6` and `docker/login-action@v4`
   (ghcr.io with `GITHUB_TOKEN`); it is reverted afterwards.
4. **Verify** — exact-head CI is green with no "Node.js 20 is deprecated" warning for the changed actions; the
   governance and docs lints pass.
