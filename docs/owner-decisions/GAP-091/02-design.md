---
work_id: GAP-091
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-22-gap-091-governance-lint-wait-evidence.md
  plan: null
  branch: docs/GAP-091-governance-lint-wait
  pr: https://github.com/kha997/zenamanagephp/pull/364
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-22T19:34:13+07:00"
  updated_at: "2026-10-22T19:34:13+07:00"
generated_by: agent
---

# GAP-091 — Owner Governance Lint false red on Gate 3 packets: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

**Phương án A (khuyến nghị): chờ lâu hơn và đỏ ngay khi có check đỏ.**
- Lint chờ các check khác tối đa **60 phút** (hiện là 5 phút). Browser-tests mất khoảng 20 phút
  nên dư thời gian.
- Lint tách hai loại trạng thái:
  - **đang chạy:** tiếp tục chờ;
  - **đã đỏ hoặc bị hủy:** lint **đỏ ngay**, không chờ thêm.
- Hết 60 phút mà vẫn còn check đang chạy thì lint đỏ, như hiện nay.
- Kiểm tra digest và mọi luật governance khác giữ nguyên.
- Giá phải trả: lint chiếm một runner tới khoảng 20 phút, nhưng chỉ ở commit trình Gate 3.

**Phương án B: chỉ đỏ khi có check đã đỏ.** Khi các check khác còn đang chạy, lint chỉ cảnh báo rồi
xanh. Lint chạy nhanh, nhưng yếu hơn: nếu sau đó một check đỏ thì lint đã xanh rồi, không ai bắt lại.

**Phương án C: giữ nguyên**, tiếp tục chạy lại lint bằng tay mỗi lần trình Gate 3.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`scripts/ssot/owner_governance_lint.php`**: add
   `owner_governance_count_failed_checks(array $checks): int`. It counts checks whose `bucket` is `fail` or
   `cancel`. `owner_governance_count_blocking_checks` is unchanged.
2. **`scripts/ci/check-evidence-freshness.sh`**, live check only (`gate_status == awaiting_owner`):
   - poll up to **240 × 15 s = 60 min**, instead of 20 × 15 s;
   - on each poll, if `owner_governance_count_failed_checks` is greater than 0, print the failing checks and exit 1
     at once;
   - otherwise, if `owner_governance_count_blocking_checks` is 0, pass; if it is still above 0, wait and poll again;
   - after 60 min, fail as today. The error message names the pending checks.
   - The structural digest checks and the self-exclusion of "Owner Governance Lint" are unchanged.
3. **`.github/workflows/owner-governance-lint.yml`**: the job gets `timeout-minutes: 75`, so a stuck poll cannot hold
   a runner for the default 6 hours. No other change.
4. **Tests**:
   - `tests/Unit/OwnerGovernance/EvidenceBindingTest.php`: unit tests for `owner_governance_count_failed_checks`
     (`fail` and `cancel` count; `pass`, `pending` and `skipping` do not).
   - New `tests/Unit/OwnerGovernance/EvidenceFreshnessLiveCheckTest.php` runs the real script with a fake `gh` on
     `PATH`, following the pattern of `FetchPrChangedFilesTest.php`. Its cases use a temporary repository with an
     `awaiting_owner` Gate 3 fixture:
     - pending, then all pass: exits 0 after more than one poll;
     - pending plus one failed: exits 1 on the first poll, without waiting;
     - always pending: exits 1 after the last poll.

     The poll count and interval are read from optional environment variables
     (`OWNER_GOV_LIVE_POLL_ATTEMPTS`, `OWNER_GOV_LIVE_POLL_INTERVAL`), so the tests run in seconds. They default to
     240 and 15.
   - `EnforcementBoundaryTest` asserts the script references `owner_governance_count_failed_checks`.
5. **Docs**: plan, this packet, 03-release.

**Option B:** item 2 instead passes, with a `::warning::`, when only pending checks remain, and fails only on failed
checks. There is no long wait and no item 3.

**Not changed:**
- the packet schema, gate statuses, the digest functions and checks, gate-ordering, and gate-3-before-ready;
- `owner_governance_count_blocking_checks`;
- other workflows.

## Verification

- `php artisan test tests/Unit/OwnerGovernance` passes locally and in CI.
- **Live proof, green:** this PR's own Gate 3 packet commit is an `awaiting_owner` packet with about 20 minutes of
  other checks. Owner Governance Lint must go green on its **first** attempt. The run is linked in Gate 3.
- **Live proof, red:** covered by the fake-`gh` test (one failed check exits 1 on the first poll). It is not
  re-staged on GitHub, since that would need a fake `awaiting_owner` packet.
- actionlint shows no new findings. Governance and docs lints pass. Exact-head CI is green.

## Rollback

Revert the squash commit.

## Out of scope

- Moving the live check to a separate workflow, and any other governance rule.
- Codex review hangs.

## Decision Needed

Owner chọn: `APPROVE GAP-091 Gate 2 Option A` (khuyến nghị) / Option B / Option C /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
