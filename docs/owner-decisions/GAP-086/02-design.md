---
work_id: GAP-086
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-20-gap-086-npm-audit-high-gate-evidence.md
  plan: null
  branch: docs/GAP-086-npm-audit-high-gate
  pr: https://github.com/kha997/zenamanagephp/pull/359
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
  created_at: "2026-10-20T19:54:52+07:00"
  updated_at: "2026-10-20T19:54:52+07:00"
generated_by: agent
---

# GAP-086 — npm audit gate at high: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

- **Phương án A (khuyến nghị):**
  - Kiểm tra "Security Vulnerability Scan" đỏ khi `npm audit` có lỗ hổng
    **high hoặc critical**.
  - Có thêm file ngoại lệ `.npm-audit-ignore`: mỗi dòng một mã lỗ hổng
    (GHSA) kèm lý do. Lỗ hổng có trong file được bỏ qua. File này nằm trong
    repo nên thêm ngoại lệ luôn phải qua review PR, giống Composer và Trivy.
  - Hiện npm có 0 lỗ hổng, nên không PR nào bị đỏ.
- **Phương án B:** như A nhưng không có file ngoại lệ. Nếu có lỗ hổng high chưa
  vá thì phải ghim phiên bản gói hoặc sửa workflow mới qua được.

**Kiểm chứng:** mỗi trường hợp dùng một commit tạm, rồi hoàn tác:

1. thêm một gói có lỗ hổng **high** (không critical): CI phải **đỏ**;
2. ghi mã lỗ hổng đó vào `.npm-audit-ignore`: CI phải **xanh**.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`.github/workflows/code-quality-security.yml`**, Security Vulnerability Scan, final gate step:
   - The gate counts distinct advisories with severity `high` or `critical`.
     It reads them from `.vulnerabilities[].via[]` objects in `npm-audit.json`,
     and the GHSA id is the last segment of the advisory URL.
   - It excludes ids listed in `.npm-audit-ignore`, if that file exists. Each
     line holds a GHSA id followed by `#` and a reason; blank lines and
     comment-only lines are skipped.
   - It fails when the remaining count is above 0, and prints the
     advisories it counted.
   - A missing or unreadable report still fails.
   - The step name becomes "Fail on Composer advisories or npm high/critical vulnerabilities".
   - The step summary also lists the ignored advisories.
   - Composer and Trivy gates are unchanged.
2. **`.npm-audit-ignore`**: not created now. The mechanism is documented in
   the workflow comment and in this packet.
3. **Docs**: plan, this packet, 03-release.

## Verification

- Red/green proof on the PR with temporary commits, each reverted:
  - a dependency with a known **high**, non-critical advisory → Security Vulnerability Scan red, listing it;
  - the same dependency plus its GHSA id in `.npm-audit-ignore` → green.
- The final head has none of it. Exact-head CI is green; governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Moderate and low findings (they stay report-only).
- Composer and Trivy gates.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-086 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
